<?php

require_once __DIR__ . '/../core/Transacao.php';
require_once __DIR__ . '/../core/Historico.php';
require_once __DIR__ . '/../core/ProvedorPagamento.php';
require_once __DIR__ . '/OrdemServicoPagamento.php';
require_once __DIR__ . '/ContaReceber.php';
require_once __DIR__ . '/Comunicacao.php';
require_once __DIR__ . '/Notificacao.php';

/**
 * Cobrança online: o cliente paga parcelas da OS pelo portal, no provedor de pagamento.
 *
 * Garantias:
 *  - UMA cobrança ativa por parcela (índice único no banco): clicar "Pagar" de novo devolve o link da
 *    mesma cobrança em vez de criar outra — sem cobrança duplicada;
 *  - o cliente só paga o que está realmente devido: parcelas de OS já cobradas, ainda pendentes;
 *  - a chamada ao provedor (rede) acontece FORA da transação do banco;
 *  - toda notificação do provedor entra pelo `processarEvento()`: o evento é gravado uma vez
 *    (repetido = ignorado), o valor é conferido, e só então a parcela é baixada;
 *  - pagamento estornado, recusado, cancelado ou expirado atualiza a cobrança e as parcelas;
 *  - dinheiro que chega para parcela JÁ paga no balcão não é contado duas vezes: a cobrança fica
 *    registrada e o administrador é avisado para devolver ao cliente.
 *
 * Nenhum dado de cartão passa por aqui.
 */
final class CobrancaOnline
{
    public const STATUS_LABEL = ['PENDENTE' => 'Aguardando pagamento', 'PAGA' => 'Paga', 'RECUSADA' => 'Recusada', 'CANCELADA' => 'Cancelada', 'ESTORNADA' => 'Estornada', 'EXPIRADA' => 'Expirada'];

    private PDO $conn;
    private ?ProvedorPagamento $provedor;
    private array $cfg;

    public function __construct(?PDO $conn = null, ?ProvedorPagamento $provedor = null, ?array $cfgPagamentos = null)
    {
        $this->conn = $conn ?? new Database();
        $this->cfg = $cfgPagamentos ?? Integracoes::pagamentos();
        $this->provedor = $provedor ?? Provedores::atual($this->cfg);
    }

    public function provedor(): ?ProvedorPagamento
    {
        return $this->provedor;
    }

    public function disponivel(): bool
    {
        return $this->provedor !== null;
    }

    /* ── Consulta ────────────────────────────────────────────────────────── */

    /** Parcelas que o cliente pode pagar agora (cobradas, pendentes) e a cobrança ativa de cada uma, se houver. */
    public function parcelasDaOs(int $osId): array
    {
        $stmt = $this->conn->prepare("
            SELECT p.*, os.status AS os_status,
                   (SELECT co.id_cobranca FROM cobranca_parcelas cp JOIN cobrancas_online co ON co.id_cobranca = cp.cobranca_id
                     WHERE cp.parcela_id = p.id_parcela AND cp.ativo = 1 AND co.status = 'PENDENTE' LIMIT 1) AS cobranca_ativa_id,
                   (SELECT co.checkout_url FROM cobranca_parcelas cp JOIN cobrancas_online co ON co.id_cobranca = cp.cobranca_id
                     WHERE cp.parcela_id = p.id_parcela AND cp.ativo = 1 AND co.status = 'PENDENTE' LIMIT 1) AS checkout_ativo
            FROM ordem_servico_parcelas p JOIN ordens_servico os ON os.id_ordem_servico = p.ordem_servico_id
            WHERE p.ordem_servico_id = ? ORDER BY p.numero
        ");
        $stmt->execute([$osId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM cobrancas_online WHERE id_cobranca = ?');
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function porReferencia(string $ref): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM cobrancas_online WHERE referencia = ?');
        $stmt->execute([$ref]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function daOs(int $osId): array
    {
        $stmt = $this->conn->prepare('SELECT * FROM cobrancas_online WHERE ordem_servico_id = ? ORDER BY id_cobranca DESC');
        $stmt->execute([$osId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ── Criar ───────────────────────────────────────────────────────────── */

    /**
     * Cria (ou devolve, se já existe igual) a cobrança que cobre as parcelas escolhidas.
     *
     * @param int[] $parcelaIds
     * @return array{ok:bool, erro:?string, cobranca_id:?int, url:?string, repetido:bool}
     */
    public function criar(int $osId, array $parcelaIds, ?int $tokenId, string $urlRetorno, string $urlNotificacao): array
    {
        $saida = ['ok' => false, 'erro' => null, 'cobranca_id' => null, 'url' => null, 'repetido' => false];

        if ($this->provedor === null) {
            return array_merge($saida, ['erro' => 'O pagamento online não está disponível. Fale com a oficina.']);
        }

        $parcelaIds = array_values(array_unique(array_map('intval', $parcelaIds)));
        sort($parcelaIds);

        if (!$parcelaIds) {
            return array_merge($saida, ['erro' => 'Escolha o que deseja pagar.']);
        }

        // Fase 1 (banco): valida e reserva as parcelas.
        try {
            $reserva = Transacao::executar($this->conn, function () use ($osId, $parcelaIds, $tokenId) {
                $os = $this->conn->prepare('SELECT id_ordem_servico, numero, status, cliente_id FROM ordens_servico WHERE id_ordem_servico = ? FOR UPDATE');
                $os->execute([$osId]);
                $os = $os->fetch(PDO::FETCH_ASSOC);

                if (!$os) {
                    throw new DomainException('Ordem de serviço não encontrada.');
                }

                if (!in_array($os['status'], ContaReceber::OS_COBRADAS, true)) {
                    throw new DomainException('Esta ordem de serviço ainda não tem cobrança liberada para pagamento.');
                }

                $in = implode(',', array_fill(0, count($parcelaIds), '?'));
                $parcelas = $this->conn->prepare("SELECT * FROM ordem_servico_parcelas WHERE id_parcela IN ({$in}) AND ordem_servico_id = ? ORDER BY id_parcela FOR UPDATE");
                $parcelas->execute([...$parcelaIds, $osId]);
                $parcelas = $parcelas->fetchAll(PDO::FETCH_ASSOC);

                if (count($parcelas) !== count($parcelaIds)) {
                    throw new DomainException('Uma das parcelas escolhidas não pertence a esta ordem de serviço.');
                }

                foreach ($parcelas as $p) {
                    if ((int) $p['pago'] === 1) {
                        throw new DomainException('A parcela ' . $p['numero'] . ' já está paga.');
                    }
                }

                // Já há cobrança ativa para alguma delas?
                $ativas = $this->conn->prepare("
                    SELECT DISTINCT co.* FROM cobranca_parcelas cp JOIN cobrancas_online co ON co.id_cobranca = cp.cobranca_id
                    WHERE cp.ativo = 1 AND cp.parcela_id IN ({$in}) FOR UPDATE
                ");
                $ativas->execute($parcelaIds);
                $ativas = $ativas->fetchAll(PDO::FETCH_ASSOC);

                if ($ativas) {
                    $idsDaAtiva = $this->conn->prepare('SELECT parcela_id FROM cobranca_parcelas WHERE cobranca_id = ? AND ativo = 1 ORDER BY parcela_id');
                    $idsDaAtiva->execute([$ativas[0]['id_cobranca']]);
                    $mesmas = array_map('intval', $idsDaAtiva->fetchAll(PDO::FETCH_COLUMN)) === $parcelaIds;

                    // Cobrança "pendurada" (a chamada ao provedor nunca terminou): cancela e recomeça.
                    $presa = count($ativas) === 1 && $ativas[0]['status'] === 'PENDENTE' && empty($ativas[0]['checkout_url'])
                        && strtotime($ativas[0]['criada_em']) < time() - 120;

                    if ($presa) {
                        $this->liberarCobranca((int) $ativas[0]['id_cobranca'], 'CANCELADA', 'Cobrança abandonada antes de falar com o provedor.');
                    } elseif (count($ativas) === 1 && $mesmas && $ativas[0]['status'] === 'PENDENTE' && !empty($ativas[0]['checkout_url'])) {
                        return ['repetida' => $ativas[0]];
                    } else {
                        throw new DomainException('Já existe um pagamento em andamento para estas parcelas. Conclua-o ou aguarde a confirmação.');
                    }
                }

                $valor = round(array_sum(array_map(fn($p) => (float) $p['valor'], $parcelas)), 2);

                $this->conn->prepare("
                    INSERT INTO cobrancas_online (ordem_servico_id, cliente_id, provedor, referencia, valor, max_parcelas, criada_por_token_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ")->execute([$osId, $os['cliente_id'], $this->provedor->nome(), 'TMP' . bin2hex(random_bytes(8)), $valor, count($parcelaIds) === 1 ? 1 : (int) $this->cfg['max_parcelas'], $tokenId]);
                $id = (int) $this->conn->lastInsertId();

                $referencia = 'MECANIZE-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
                $this->conn->prepare('UPDATE cobrancas_online SET referencia = ? WHERE id_cobranca = ?')->execute([$referencia, $id]);

                $ins = $this->conn->prepare('INSERT INTO cobranca_parcelas (cobranca_id, parcela_id) VALUES (?, ?)');
                foreach ($parcelas as $p) {
                    $ins->execute([$id, $p['id_parcela']]);
                }

                return ['nova' => ['id' => $id, 'referencia' => $referencia, 'valor' => $valor, 'os' => $os, 'max_parcelas' => $this->conn->query("SELECT max_parcelas FROM cobrancas_online WHERE id_cobranca = {$id}")->fetchColumn()]];
            });
        } catch (DomainException $e) {
            return array_merge($saida, ['erro' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[CobrancaOnline criar] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            return array_merge($saida, ['erro' => 'Não foi possível preparar o pagamento. Tente novamente.']);
        }

        if (isset($reserva['repetida'])) {
            return array_merge($saida, ['ok' => true, 'cobranca_id' => (int) $reserva['repetida']['id_cobranca'], 'url' => $reserva['repetida']['checkout_url'], 'repetido' => true]);
        }

        $n = $reserva['nova'];

        // Fase 2 (rede): pede a cobrança ao provedor, sem segurar trava do banco.
        $r = $this->provedor->criarCobranca([
            'referencia' => $n['referencia'], 'valor' => $n['valor'], 'max_parcelas' => (int) $n['max_parcelas'],
            'descricao' => 'Ordem de serviço ' . $n['os']['numero'], 'url_retorno' => $urlRetorno, 'url_notificacao' => $urlNotificacao,
        ]);

        if (!$r['ok']) {
            $this->liberarCobranca($n['id'], 'CANCELADA', $r['erro']);

            return array_merge($saida, ['erro' => 'Não foi possível iniciar o pagamento agora. Tente novamente em instantes.']);
        }

        $this->conn->prepare('UPDATE cobrancas_online SET provedor_cobranca_id = ?, checkout_url = ? WHERE id_cobranca = ?')->execute([$r['id'], $r['url'], $n['id']]);

        return array_merge($saida, ['ok' => true, 'cobranca_id' => $n['id'], 'url' => $r['url']]);
    }

    /** Fecha a cobrança sem pagamento e libera as parcelas para uma nova tentativa. */
    private function liberarCobranca(int $id, string $status, ?string $detalhe): void
    {
        $this->conn->prepare("UPDATE cobrancas_online SET status = ?, detalhe_status = ? WHERE id_cobranca = ? AND status = 'PENDENTE'")->execute([$status, $detalhe !== null ? mb_substr($detalhe, 0, 200) : null, $id]);
        $this->conn->prepare('UPDATE cobranca_parcelas SET ativo = 0 WHERE cobranca_id = ?')->execute([$id]);
    }

    /* ── Webhook ─────────────────────────────────────────────────────────── */

    /**
     * Ponto único de entrada de TODO webhook de pagamento (o real e o simulado passam por aqui).
     * A assinatura é conferida sobre o corpo BRUTO antes de qualquer leitura; sem ela nada é gravado.
     *
     * @param array<string,string> $cabecalhos nomes em minúsculas
     * @return array{http:int, motivo:string, resultados:string[]}
     */
    public function receberWebhook(ProvedorPagamento $prov, string $corpoBruto, array $cabecalhos, array $query = []): array
    {
        if (strlen($corpoBruto) > 262144) {
            return ['http' => 413, 'motivo' => 'corpo_grande', 'resultados' => []];
        }

        if (!$prov->configurado() || !$prov->validarWebhook($corpoBruto, $cabecalhos, $query)) {
            error_log('[webhook pagamento ' . $prov->nome() . '] Assinatura ausente ou inválida — descartado.');
            return ['http' => 401, 'motivo' => 'assinatura_invalida', 'resultados' => []];
        }

        try {
            $eventos = $prov->interpretarWebhook($corpoBruto, $cabecalhos, $query);

            return ['http' => 200, 'motivo' => 'ok', 'resultados' => $this->processarEventos($prov->nome(), $eventos)];
        } catch (\Throwable $e) {
            // 5xx: o provedor reenvia. O evento não ficou registrado como "visto" (a transação desfez).
            error_log('[webhook pagamento ' . $prov->nome() . '] ' . $e->getMessage());
            return ['http' => 500, 'motivo' => 'erro_interno', 'resultados' => []];
        }
    }

    /**
     * Processa os eventos já validados (assinatura conferida por quem chama).
     *
     * @param array<int,array<string,mixed>> $eventos
     * @return array<int,string> o resultado de cada evento
     */
    public function processarEventos(string $provedor, array $eventos): array
    {
        $resultados = [];

        foreach ($eventos as $e) {
            $resultados[] = $this->processarEvento($provedor, $e);
        }

        return $resultados;
    }

    public function processarEvento(string $provedor, array $e): string
    {
        return Transacao::executar($this->conn, function () use ($provedor, $e) {
            $novo = $this->conn->prepare('INSERT IGNORE INTO webhook_eventos (provedor, evento_id, tipo) VALUES (?, ?, ?)');
            $novo->execute([$provedor, mb_substr((string) $e['evento_id'], 0, 190), $e['tipo']]);

            if ($novo->rowCount() === 0) {
                return 'duplicado';
            }

            $eventoId = (int) $this->conn->lastInsertId();
            $resultado = $this->aplicar($provedor, $e);

            $this->conn->prepare('UPDATE webhook_eventos SET resultado = ? WHERE id_evento = ?')->execute([mb_substr($resultado, 0, 160), $eventoId]);

            return $resultado;
        });
    }

    private function aplicar(string $provedor, array $e): string
    {
        $c = null;

        if (!empty($e['referencia'])) {
            $s = $this->conn->prepare('SELECT * FROM cobrancas_online WHERE referencia = ? FOR UPDATE');
            $s->execute([$e['referencia']]);
            $c = $s->fetch(PDO::FETCH_ASSOC);
        }

        if (!$c && !empty($e['provedor_cobranca_id'])) {
            $s = $this->conn->prepare('SELECT * FROM cobrancas_online WHERE provedor = ? AND provedor_cobranca_id = ? FOR UPDATE');
            $s->execute([$provedor, $e['provedor_cobranca_id']]);
            $c = $s->fetch(PDO::FETCH_ASSOC);
        }

        if (!$c) {
            error_log('[CobrancaOnline] Evento ' . $provedor . ' para cobrança desconhecida: ' . ($e['referencia'] ?? $e['provedor_cobranca_id'] ?? '?'));
            return 'cobranca_desconhecida';
        }

        $id = (int) $c['id_cobranca'];

        switch ($e['tipo']) {
            case 'PAGO':
                return $this->aplicarPago($c, $e);

            case 'RECUSADO':
            case 'CANCELADO':
            case 'EXPIRADO':
                if ($c['status'] !== 'PENDENTE') {
                    return 'ignorado_' . strtolower($c['status']);
                }

                $novoStatus = ['RECUSADO' => 'RECUSADA', 'CANCELADO' => 'CANCELADA', 'EXPIRADO' => 'EXPIRADA'][$e['tipo']];
                $this->liberarCobranca($id, $novoStatus, $e['detalhe'] ?? null);

                return 'cobranca_' . strtolower($novoStatus);

            case 'PENDENTE':
                if ($c['status'] === 'PENDENTE' && !empty($e['detalhe'])) {
                    $this->conn->prepare('UPDATE cobrancas_online SET detalhe_status = ? WHERE id_cobranca = ?')->execute([mb_substr((string) $e['detalhe'], 0, 200), $id]);
                }

                return 'pendente';

            case 'ESTORNADO':
                return $this->aplicarEstorno($c, $e);
        }

        return 'tipo_desconhecido';
    }

    private function aplicarPago(array $c, array $e): string
    {
        $id = (int) $c['id_cobranca'];

        if ($c['status'] === 'PAGA') {
            return 'ja_paga';
        }

        if ($c['status'] === 'ESTORNADA') {
            return 'ignorado_estornada';
        }

        // O valor que o provedor confirma precisa ser o da cobrança: pagamento parcial ou de outro valor não baixa nada sozinho.
        if (isset($e['valor']) && abs((float) $e['valor'] - (float) $c['valor']) > 0.009) {
            $this->conn->prepare('UPDATE cobrancas_online SET detalhe_status = ? WHERE id_cobranca = ?')
                ->execute([sprintf('Valor confirmado (R$ %s) diferente do cobrado (R$ %s).', number_format((float) $e['valor'], 2, ',', '.'), number_format((float) $c['valor'], 2, ',', '.')), $id]);
            $this->avisarAdmins($c, 'Pagamento online com valor divergente', 'O provedor confirmou um valor diferente do cobrado. Nada foi baixado: confira no painel do provedor.', 'DIVERGENTE');

            return 'valor_divergente';
        }

        $itens = $this->conn->prepare('SELECT parcela_id FROM cobranca_parcelas WHERE cobranca_id = ? ORDER BY parcela_id');
        $itens->execute([$id]);
        $parcelaIds = array_map('intval', $itens->fetchAll(PDO::FETCH_COLUMN));

        $parcelas = $this->conn->prepare('SELECT id_parcela, valor FROM ordem_servico_parcelas WHERE id_parcela IN (' . implode(',', $parcelaIds ?: [0]) . ') ORDER BY id_parcela');
        $parcelas->execute();
        $parcelas = $parcelas->fetchAll(PDO::FETCH_ASSOC);

        // O plano de pagamento foi refeito depois de a cobrança nascer (as parcelas dela não existem mais): o
        // dinheiro entrou no provedor, mas não há a que baixar. Nada é baixado às cegas — fica registrado e o
        // administrador decide (lançar na parcela nova ou devolver).
        if (!$parcelas) {
            $this->conn->prepare("UPDATE cobrancas_online SET status = 'PAGA', paga_em = NOW(), forma_pagamento = ?, taxa_provedor = ?, detalhe_status = ? WHERE id_cobranca = ?")
                ->execute([$e['forma'] ?? null, round((float) ($e['taxa'] ?? 0), 2), 'Pagamento recebido para parcelas que não existem mais (plano alterado): lançar na parcela nova ou devolver.', $id]);
            $this->avisarAdmins($c, 'Pagamento online sem parcela correspondente', 'O cliente pagou online uma cobrança cujo plano de pagamento foi refeito. Nada foi baixado: lance o valor na parcela certa ou devolva pelo painel do provedor.', 'SEM_PARCELA');

            return 'paga_sem_parcela';
        }

        $total = array_sum(array_map(fn($p) => (float) $p['valor'], $parcelas));
        $taxaTotal = round((float) ($e['taxa'] ?? 0), 2);
        $restanteTaxa = $taxaTotal;
        $pag = new OrdemServicoPagamento($this->conn);
        $duplicadas = 0;
        $ultima = count($parcelas) - 1;

        foreach ($parcelas as $i => $p) {
            $taxa = $i === $ultima ? $restanteTaxa : round($taxaTotal * ((float) $p['valor'] / max($total, 0.01)), 2);
            $restanteTaxa = round($restanteTaxa - $taxa, 2);

            $r = $pag->baixarOnline((int) $p['id_parcela'], $id, $e['forma'] ?? null, $taxa);

            if (!$r['ok']) {
                throw new RuntimeException($r['erro'] ?? 'Falha na baixa online.');
            }

            if ($r['ja_paga']) {
                $duplicadas++;
            }
        }

        $this->conn->prepare("
            UPDATE cobrancas_online SET status = 'PAGA', paga_em = NOW(), forma_pagamento = ?, parcelas_cartao = ?, taxa_provedor = ?, detalhe_status = ?
            WHERE id_cobranca = ?
        ")->execute([$e['forma'] ?? null, $e['parcelas'] ?? null, $taxaTotal, $duplicadas > 0 ? 'Parte das parcelas já estava paga no balcão: devolver ao cliente.' : null, $id]);

        $os = $this->conn->prepare('SELECT os.numero, os.cliente_id, v.placa, CONCAT(mv.nome, " ", mo.nome) AS veiculo FROM ordens_servico os JOIN veiculos v ON v.id_veiculo = os.veiculo_id JOIN marcas_veiculos mv ON mv.id_marca = v.marca_id JOIN modelos_veiculos mo ON mo.id_modelo = v.modelo_id WHERE os.id_ordem_servico = ?');
        $os->execute([$c['ordem_servico_id']]);
        $os = $os->fetch(PDO::FETCH_ASSOC);

        if ($os) {
            Comunicacao::evento($this->conn, 'PAGAMENTO_CONFIRMADO', (int) $os['cliente_id'], ['placa' => $os['placa'], 'veiculo' => $os['veiculo'], 'numero' => $os['numero'], 'valor' => (float) $c['valor']],
                'COBRANCA:' . $id . ':PAGA', 'COBRANCA', $id);
        }

        if ($duplicadas > 0) {
            $this->avisarAdmins($c, 'Pagamento online duplicado', 'O cliente pagou online parcela(s) que já estavam pagas no balcão. Devolva o valor pelo painel do provedor.', 'DUPLICADO');
            return 'paga_com_duplicidade';
        }

        return 'paga';
    }

    private function aplicarEstorno(array $c, array $e): string
    {
        $id = (int) $c['id_cobranca'];

        if ($c['status'] !== 'PAGA') {
            return $c['status'] === 'ESTORNADA' ? 'ja_estornada' : 'estorno_sem_pagamento';
        }

        $itens = $this->conn->prepare('SELECT parcela_id FROM cobranca_parcelas WHERE cobranca_id = ? ORDER BY parcela_id');
        $itens->execute([$id]);
        $pag = new OrdemServicoPagamento($this->conn);

        foreach ($itens->fetchAll(PDO::FETCH_COLUMN) as $parcelaId) {
            $pag->estornarOnline((int) $parcelaId, $id, $e['detalhe'] ?? 'estorno informado pelo provedor');
        }

        $this->conn->prepare("UPDATE cobrancas_online SET status = 'ESTORNADA', detalhe_status = ? WHERE id_cobranca = ?")->execute([mb_substr((string) ($e['detalhe'] ?? 'Estornada pelo provedor'), 0, 200), $id]);
        $this->conn->prepare('UPDATE cobranca_parcelas SET ativo = 0 WHERE cobranca_id = ?')->execute([$id]);

        $this->avisarAdmins($c, 'Pagamento online estornado', 'O provedor estornou um pagamento online. As parcelas voltaram a ser devidas.', 'ESTORNADO');

        $os = $this->conn->prepare('SELECT numero, cliente_id FROM ordens_servico WHERE id_ordem_servico = ?');
        $os->execute([$c['ordem_servico_id']]);
        if ($o = $os->fetch(PDO::FETCH_ASSOC)) {
            Comunicacao::evento($this->conn, 'PAGAMENTO_ESTORNADO', (int) $o['cliente_id'], ['numero' => $o['numero'], 'valor' => (float) $c['valor']], 'COBRANCA:' . $id . ':ESTORNADA', 'COBRANCA', $id);
        }

        return 'estornada';
    }

    private function avisarAdmins(array $c, string $titulo, string $mensagem, string $tipo): void
    {
        Notificacao::paraUsuarios(
            $this->conn, Notificacao::idsDosAdmins($this->conn), 'PAGAMENTO_ONLINE', $titulo, $mensagem,
            './pagamentoOrdemServico?id=' . (int) $c['ordem_servico_id'], 'COBRANCA:' . $c['id_cobranca'] . ':' . $tipo
        );
    }
}
