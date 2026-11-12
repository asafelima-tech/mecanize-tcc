<?php

require_once __DIR__ . '/../core/Transacao.php';
require_once __DIR__ . '/Comunicacao.php';
require_once __DIR__ . '/../core/Historico.php';
require_once __DIR__ . '/MovimentacaoCaixa.php';
require_once __DIR__ . '/Caixa.php';

/**
 * Plano de pagamento de uma ordem de serviço e suas parcelas.
 *
 * Toda OS cobrada tem parcelas — a OS À VISTA é um plano de UMA parcela. Assim
 * o que o cliente deve é sempre a soma das parcelas ainda não pagas, e um
 * recebimento é sempre "a baixa de uma parcela", seja no balcão ou, nas
 * próximas etapas, por confirmação do provedor de pagamento. Concluir ou
 * entregar a OS não dá baixa em nada: cobrança e recebimento são coisas
 * diferentes.
 *
 * A parcela guarda o id do lançamento do caixa que a quitou
 * (movimentacao_caixa_id). Esse vínculo, mais a trava de linha em pagarParcela()
 * e a chave de idempotência do lançamento, impede o mesmo recebimento de entrar
 * duas vezes — por clique repetido, aba antiga ou duas pessoas ao mesmo tempo.
 */
class OrdemServicoPagamento
{
    public $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    /**
     * Valor final de um plano: base + juros (percentual) + taxa (valor fixo).
     *
     * Fica isolado aqui porque a tela, a gravação e a exibição precisam chegar
     * exatamente ao mesmo número — três cálculos soltos divergiriam no
     * arredondamento.
     */
    public static function calcularTotal(float $base, float $jurosPercentual, float $taxa): float
    {
        return round($base + ($base * $jurosPercentual / 100) + $taxa, 2);
    }

    /** Plano de pagamento gravado na OS (sem as parcelas). */
    public function plano(int $ordemId): array
    {
        $stmt = $this->conn->prepare(
            'SELECT valor_total_os, pagamento_tipo, pagamento_forma,
                    pagamento_juros, pagamento_taxa, pagamento_total
             FROM ordens_servico
             WHERE id_ordem_servico = ?'
        );
        $stmt->execute([$ordemId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** Parcelas da OS, em ordem. */
    public function parcelas(int $ordemId): array
    {
        $stmt = $this->conn->prepare(
            'SELECT * FROM ordem_servico_parcelas
             WHERE ordem_servico_id = ?
             ORDER BY numero'
        );
        $stmt->execute([$ordemId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resumo do que já foi pago e do que falta — é o que a tela mostra sem
     * precisar somar parcela por parcela em três lugares diferentes.
     */
    public function resumo(int $ordemId): array
    {
        $parcelas = $this->parcelas($ordemId);

        $pagas     = array_filter($parcelas, fn($p) => (int) $p['pago'] === 1);
        $pendentes = array_filter($parcelas, fn($p) => (int) $p['pago'] !== 1);

        $soma = fn(array $lista) => round(array_sum(array_map(fn($p) => (float) $p['valor'], $lista)), 2);

        $hoje     = date('Y-m-d');
        $atrasadas = array_filter(
            $pendentes,
            fn($p) => !empty($p['vencimento']) && $p['vencimento'] < $hoje
        );

        return [
            'total'           => count($parcelas),
            'qtd_pagas'       => count($pagas),
            'qtd_pendentes'   => count($pendentes),
            'qtd_atrasadas'   => count($atrasadas),
            'valor_pago'      => $soma($pagas),
            'valor_pendente'  => $soma($pendentes),
            'valor_total'     => $soma($parcelas),
            'proximo_venc'    => $pendentes ? (reset($pendentes)['vencimento'] ?? null) : null,
        ];
    }

    /**
     * Grava o plano e (re)gera as parcelas.
     *
     * As parcelas antigas são apagadas e recriadas — menos as que já foram
     * pagas, que ficam intocadas: uma parcela paga já virou dinheiro no caixa,
     * e apagá-la deixaria o lançamento órfão.
     *
     * A divisão joga a sobra de centavos na última parcela: 100,00 em 3x vira
     * 33,33 + 33,33 + 33,34, e a soma continua fechando com o total.
     *
     * OS À VISTA gera uma única parcela (a cobrança existe desde o início).
     */
    public function salvarPlano(int $ordemId, array $dados): bool
    {
        $tipo = ($dados['pagamento_tipo'] ?? 'AVISTA') === 'PARCELADO' ? 'PARCELADO' : 'AVISTA';

        $forma = in_array($dados['pagamento_forma'] ?? '', MovimentacaoCaixa::FORMAS, true)
            ? $dados['pagamento_forma']
            : null;

        $juros = max(0, (float) str_replace(',', '.', (string) ($dados['pagamento_juros'] ?? 0)));
        $taxa  = max(0, (float) str_replace(',', '.', (string) ($dados['pagamento_taxa']  ?? 0)));

        try {
            Transacao::executar($this->conn, function () use ($ordemId, $dados, $tipo, $forma, $juros, $taxa) {
                $trava = $this->conn->prepare('SELECT status FROM ordens_servico WHERE id_ordem_servico = ? FOR UPDATE');
                $trava->execute([$ordemId]);
                $status = $trava->fetchColumn();

                if ($status === false || $status === 'CANCELADA') {
                    throw new DomainException('Esta OS não aceita plano de pagamento.');
                }

                $plano = $this->plano($ordemId);
                $base  = (float) ($plano['valor_total_os'] ?? 0);
                $total = self::calcularTotal($base, $juros, $taxa);

                $this->conn->prepare(
                    'UPDATE ordens_servico
                     SET pagamento_tipo  = ?, pagamento_forma = ?,
                         pagamento_juros = ?, pagamento_taxa  = ?, pagamento_total = ?
                     WHERE id_ordem_servico = ?'
                )->execute([$tipo, $forma, $juros, $taxa, $total, $ordemId]);

                // Pagamento online sobre parcelas que vão ser refeitas: a cobrança em andamento é cancelada e o
                // vínculo com as parcelas antigas é desfeito (a cobrança em si fica no histórico). Se o cliente
                // pagar mesmo assim no provedor, o CobrancaOnline registra o pagamento SEM parcela e avisa o
                // administrador — nada é baixado às cegas.
                $abertas = $this->conn->prepare('SELECT id_parcela FROM ordem_servico_parcelas WHERE ordem_servico_id = ? AND pago = 0 FOR UPDATE');
                $abertas->execute([$ordemId]);
                $idsAbertas = array_map('intval', $abertas->fetchAll(PDO::FETCH_COLUMN));

                if ($idsAbertas) {
                    $in = implode(',', $idsAbertas);
                    $this->conn->exec("
                        UPDATE cobrancas_online SET status = 'CANCELADA', detalhe_status = 'Plano de pagamento alterado pela oficina'
                        WHERE status = 'PENDENTE' AND id_cobranca IN (SELECT cobranca_id FROM cobranca_parcelas WHERE parcela_id IN ({$in}))
                    ");
                    $this->conn->exec("DELETE FROM cobranca_parcelas WHERE parcela_id IN ({$in})");
                }

                // Parcelas já pagas não são recriadas: elas já viraram caixa.
                $this->conn->prepare(
                    'DELETE FROM ordem_servico_parcelas WHERE ordem_servico_id = ? AND pago = 0'
                )->execute([$ordemId]);

                $this->gerarParcelas(
                    $ordemId,
                    $tipo === 'PARCELADO' ? (int) ($dados['parcelas'] ?? 1) : 1,
                    $dados['primeiro_vencimento'] ?? null,
                    $total
                );
            });

            return true;
        } catch (\Throwable $e) {
            error_log('[pagamento OS] Falha ao salvar plano: ' . $e->getMessage());
            return false;
        }
    }

    /** Cria as parcelas que faltam, respeitando as que já foram pagas. */
    private function gerarParcelas(int $ordemId, int $quantidade, ?string $primeiroVencimento, float $total): void
    {
        $quantidade = max(1, min(48, $quantidade));

        $pagas = $this->conn->prepare(
            'SELECT COALESCE(SUM(valor), 0) AS valor, COUNT(*) AS qtd
             FROM ordem_servico_parcelas
             WHERE ordem_servico_id = ? AND pago = 1'
        );
        $pagas->execute([$ordemId]);
        $jaPago = $pagas->fetch(PDO::FETCH_ASSOC);

        $qtdPagas  = (int) ($jaPago['qtd'] ?? 0);
        $valorPago = (float) ($jaPago['valor'] ?? 0);
        $restantes = max(0, $quantidade - $qtdPagas);

        if ($restantes === 0) {
            return;
        }

        $aDistribuir = round($total - $valorPago, 2);
        $valorBase   = floor(($aDistribuir / $restantes) * 100) / 100;

        // Primeiro vencimento: o informado, ou hoje (à vista) / daqui a 30 dias (parcelado).
        $primeiro = !empty($primeiroVencimento)
            ? $primeiroVencimento
            : date('Y-m-d', strtotime($quantidade > 1 ? '+30 days' : 'today'));

        $insert = $this->conn->prepare(
            'INSERT INTO ordem_servico_parcelas (ordem_servico_id, numero, valor, vencimento)
             VALUES (?, ?, ?, ?)'
        );

        // Números já ocupados pelas parcelas pagas (que ficam). Se a 2ª foi paga
        // antes da 1ª, a nova parcela não pode reaproveitar o 2 — a chave
        // (ordem_servico_id, numero) é única.
        $ocupados = $this->conn->prepare('SELECT numero FROM ordem_servico_parcelas WHERE ordem_servico_id = ?');
        $ocupados->execute([$ordemId]);
        $ocupados = array_map('intval', $ocupados->fetchAll(PDO::FETCH_COLUMN));
        $proximo  = 0;

        $acumulado = 0.0;

        for ($i = 1; $i <= $restantes; $i++) {
            do {
                $proximo++;
            } while (in_array($proximo, $ocupados, true));

            $numero = $proximo;

            // A última leva a sobra dos centavos, para a soma fechar com o total.
            $valor = ($i === $restantes)
                ? round($aDistribuir - $acumulado, 2)
                : $valorBase;

            $acumulado += $valor;

            $vencimento = date('Y-m-d', strtotime($primeiro . ' +' . ($i - 1) . ' month'));

            $insert->execute([$ordemId, $numero, $valor, $vencimento]);
        }
    }

    /**
     * Garante que a OS tenha cobrança: se não há nenhuma parcela, cria o plano
     * padrão (à vista, uma parcela, vencendo hoje). Se já existe plano, refaz as
     * parcelas ainda não pagas sobre o valor ATUAL da OS — o total pode ter mudado
     * depois que o plano foi desenhado.
     *
     * Chamado na conferência do administrador, quando os valores são finais.
     * Roda na transação de quem chamou.
     */
    public function garantirPlano(int $ordemId): void
    {
        $parcelas = $this->parcelas($ordemId);

        if (empty($parcelas)) {
            $plano = $this->plano($ordemId);

            $this->salvarPlano($ordemId, [
                'pagamento_tipo'  => $plano['pagamento_tipo'] ?? 'AVISTA',
                'pagamento_forma' => $plano['pagamento_forma'] ?? null,
                'pagamento_juros' => $plano['pagamento_juros'] ?? 0,
                'pagamento_taxa'  => $plano['pagamento_taxa'] ?? 0,
                'parcelas'        => 1,
            ]);

            return;
        }

        $plano = $this->plano($ordemId);

        $this->salvarPlano($ordemId, [
            'pagamento_tipo'  => $plano['pagamento_tipo'] ?? 'AVISTA',
            'pagamento_forma' => $plano['pagamento_forma'] ?? null,
            'pagamento_juros' => $plano['pagamento_juros'] ?? 0,
            'pagamento_taxa'  => $plano['pagamento_taxa'] ?? 0,
            'parcelas'        => count($parcelas),
            'primeiro_vencimento' => $parcelas[0]['vencimento'] ?? null,
        ]);
    }

    /**
     * Marca a parcela como paga e lança a ENTRADA no caixa. Devolve a mensagem de
     * erro quando não dá para pagar, ou null em caso de sucesso.
     *
     * Protegido contra o mesmo recebimento entrar duas vezes, em três camadas:
     *  1. a linha da parcela é travada (FOR UPDATE) — uma segunda requisição
     *     espera a primeira terminar e então enxerga "já paga";
     *  2. o lançamento do caixa leva a chave PARCELA:{id}, única no banco;
     *  3. lançamento e baixa acontecem na MESMA transação: ou os dois, ou nenhum.
     */
    public function pagarParcela(int $parcelaId, string $forma, ?int $usuarioId, ?float $taxa = null): ?string
    {
        if (!in_array($forma, MovimentacaoCaixa::FORMAS, true)) {
            return 'Informe uma forma de pagamento válida.';
        }

        if ($taxa !== null && $taxa < 0) {
            return 'A taxa não pode ser negativa.';
        }

        try {
            return Transacao::executar($this->conn, function () use ($parcelaId, $forma, $usuarioId, $taxa) {
                $stmt = $this->conn->prepare(
                    'SELECT p.*, os.numero AS os_numero, os.status AS os_status, os.cliente_id AS os_cliente_id, os.veiculo_id AS os_veiculo_id
                     FROM ordem_servico_parcelas p
                     JOIN ordens_servico os ON os.id_ordem_servico = p.ordem_servico_id
                     WHERE p.id_parcela = ?
                     FOR UPDATE'
                );
                $stmt->execute([$parcelaId]);
                $parcela = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$parcela) {
                    return 'Parcela não encontrada.';
                }

                if ((int) $parcela['pago'] === 1 || !empty($parcela['movimentacao_caixa_id'])) {
                    return 'Esta parcela já foi paga.';
                }

                if ($parcela['os_status'] === 'CANCELADA') {
                    return 'A OS está cancelada: não há o que receber.';
                }

                if ($taxa !== null && $taxa >= (float) $parcela['valor']) {
                    return 'A taxa precisa ser menor que o valor da parcela.';
                }

                $aberto = (new Caixa($this->conn))->getCaixaAberto();

                if (!$aberto) {
                    return 'Não há caixa aberto para receber o pagamento.';
                }

                // Cada recebimento tem a sua chave: uma parcela estornada pode ser paga de novo, e a
                // chave do primeiro pagamento (PARCELA:{id}, a de sempre) continua valendo para ele.
                $tentativa = (int) $parcela['pagamentos_realizados'] + 1;
                $chave     = $tentativa === 1 ? 'PARCELA:' . $parcelaId : 'PARCELA:' . $parcelaId . ':' . $tentativa;

                $descricao = 'OS ' . ($parcela['os_numero'] ?? '') . ' - parcela ' . $parcela['numero'];

                $idCaixa = (new MovimentacaoCaixa($this->conn))->registrar(
                    $aberto['id_caixa'],
                    'ENTRADA',
                    (float) $parcela['valor'],
                    $forma,
                    'OS',
                    $descricao,
                    $usuarioId,
                    'OS',
                    (int) $parcela['ordem_servico_id'],
                    $chave
                );

                if (!$idCaixa) {
                    return 'Não foi possível lançar o recebimento no caixa.';
                }

                $this->conn->prepare(
                    'UPDATE ordem_servico_parcelas
                     SET pago = 1, data_pagamento = NOW(), forma_pagamento = ?, movimentacao_caixa_id = ?,
                         taxa_valor = ?, pagamentos_realizados = pagamentos_realizados + 1
                     WHERE id_parcela = ?'
                )->execute([$forma, $idCaixa, round((float) ($taxa ?? 0), 2), $parcelaId]);

                Historico::registrar(
                    $this->conn, 'PARCELA_OS', $parcelaId, 'PENDENTE', 'RECEBIDA', $usuarioId,
                    'Recebimento em ' . MovimentacaoCaixa::rotuloForma($forma),
                    Historico::ORIGEM_USUARIO,
                    ['valor' => (float) $parcela['valor'], 'taxa' => round((float) ($taxa ?? 0), 2), 'movimentacao_caixa_id' => $idCaixa, 'tentativa' => $tentativa]
                );

                Comunicacao::evento(
                    $this->conn, 'PAGAMENTO_CONFIRMADO', (int) $parcela['os_cliente_id'],
                    Comunicacao::dadosDoVeiculo($this->conn, (int) $parcela['os_veiculo_id']) + ['numero' => $parcela['os_numero'], 'valor' => (float) $parcela['valor']],
                    'PARCELA:' . $parcelaId . ':PAGA:' . $tentativa, 'OS', (int) $parcela['ordem_servico_id']
                );

                return null;
            });
        } catch (\Throwable $e) {
            error_log('[pagamento OS] Falha ao pagar parcela: ' . $e->getMessage());

            return 'Não foi possível registrar o recebimento.';
        }
    }

    /**
     * Baixa uma parcela por CONFIRMAÇÃO DO PROVEDOR de pagamento (webhook). Não há caixa físico
     * envolvido: o dinheiro entra na conta do provedor, então a parcela fica marcada como recebida
     * ONLINE e ligada à cobrança, sem lançamento no caixa. Os indicadores leem as parcelas, e por
     * isso o recebido, o a receber e as taxas continuam certos.
     *
     * Idempotente: parcela já paga devolve `ja_paga` sem tocar em nada.
     *
     * @return array{ok:bool, erro:?string, ja_paga:bool}
     */
    public function baixarOnline(int $parcelaId, int $cobrancaId, ?string $forma, float $taxa): array
    {
        try {
            return Transacao::executar($this->conn, function () use ($parcelaId, $cobrancaId, $forma, $taxa) {
                $stmt = $this->conn->prepare('SELECT * FROM ordem_servico_parcelas WHERE id_parcela = ? FOR UPDATE');
                $stmt->execute([$parcelaId]);
                $p = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$p) {
                    return ['ok' => false, 'erro' => 'Parcela não encontrada.', 'ja_paga' => false];
                }

                if ((int) $p['pago'] === 1) {
                    return ['ok' => true, 'erro' => null, 'ja_paga' => true];
                }

                $forma = in_array($forma, MovimentacaoCaixa::FORMAS, true) ? $forma : null;

                $this->conn->prepare(
                    "UPDATE ordem_servico_parcelas
                     SET pago = 1, data_pagamento = NOW(), forma_pagamento = ?, movimentacao_caixa_id = NULL, taxa_valor = ?,
                         pagamentos_realizados = pagamentos_realizados + 1, origem_recebimento = 'ONLINE', cobranca_online_id = ?
                     WHERE id_parcela = ?"
                )->execute([$forma, round($taxa, 2), $cobrancaId, $parcelaId]);

                Historico::registrar(
                    $this->conn, 'PARCELA_OS', $parcelaId, 'PENDENTE', 'RECEBIDA', null, 'Pagamento online confirmado pelo provedor',
                    Historico::ORIGEM_WEBHOOK, ['valor' => (float) $p['valor'], 'taxa' => round($taxa, 2), 'cobranca_online_id' => $cobrancaId, 'forma' => $forma]
                );

                return ['ok' => true, 'erro' => null, 'ja_paga' => false];
            });
        } catch (\Throwable $e) {
            error_log('[pagamento OS] Falha na baixa online: ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível registrar a baixa.', 'ja_paga' => false];
        }
    }

    /** Estorno informado pelo provedor: a parcela volta a ser devida. Só mexe em parcela recebida por ESSA cobrança. */
    public function estornarOnline(int $parcelaId, int $cobrancaId, string $motivo): array
    {
        try {
            return Transacao::executar($this->conn, function () use ($parcelaId, $cobrancaId, $motivo) {
                $stmt = $this->conn->prepare('SELECT * FROM ordem_servico_parcelas WHERE id_parcela = ? FOR UPDATE');
                $stmt->execute([$parcelaId]);
                $p = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$p || (int) $p['pago'] !== 1 || $p['origem_recebimento'] !== 'ONLINE' || (int) $p['cobranca_online_id'] !== $cobrancaId) {
                    return ['ok' => true, 'erro' => null, 'repetido' => true];
                }

                $this->conn->prepare(
                    "UPDATE ordem_servico_parcelas
                     SET pago = 0, data_pagamento = NULL, forma_pagamento = NULL, taxa_valor = 0, origem_recebimento = 'BALCAO', cobranca_online_id = NULL,
                         estornada_em = NOW(), estornada_por = NULL, estorno_justificativa = ?
                     WHERE id_parcela = ?"
                )->execute([mb_substr($motivo, 0, 255), $parcelaId]);

                Historico::registrar(
                    $this->conn, 'PARCELA_OS', $parcelaId, 'RECEBIDA', 'PENDENTE', null, 'Pagamento online estornado: ' . $motivo,
                    Historico::ORIGEM_WEBHOOK, ['valor' => (float) $p['valor'], 'cobranca_online_id' => $cobrancaId]
                );

                return ['ok' => true, 'erro' => null, 'repetido' => false];
            });
        } catch (\Throwable $e) {
            error_log('[pagamento OS] Falha no estorno online: ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível estornar.', 'repetido' => false];
        }
    }
    /**
     * Desfaz o RECEBIMENTO de uma parcela (cobrança devolvida, pagamento que falhou, engano): a
     * parcela volta a ser devida e o dinheiro devolvido sai do caixa ABERTO agora — o lançamento
     * original fica onde está (o caixa em que entrou pode já estar fechado) e o estorno é um
     * lançamento novo, ligado a ele por chave. Só administrador; exige justificativa.
     *
     * Idempotente: estornar uma parcela que já voltou a pendente devolve `repetido`.
     *
     * @return array{ok:bool, erro:?string, repetido:bool}
     */
    public function estornarParcela(int $parcelaId, string $justificativa, ?int $usuarioId): array
    {
        $justificativa = trim($justificativa);
        $saida = ['ok' => false, 'erro' => null, 'repetido' => false];

        try {
            return Transacao::executar($this->conn, function () use ($parcelaId, $justificativa, $usuarioId, $saida) {
                $stmt = $this->conn->prepare(
                    'SELECT p.*, os.numero AS os_numero FROM ordem_servico_parcelas p
                     JOIN ordens_servico os ON os.id_ordem_servico = p.ordem_servico_id
                     WHERE p.id_parcela = ? FOR UPDATE'
                );
                $stmt->execute([$parcelaId]);
                $p = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$p) {
                    throw new DomainException('Parcela não encontrada.');
                }

                if ((int) $p['pago'] !== 1) {
                    if (!empty($p['estornada_em'])) {
                        return array_merge($saida, ['ok' => true, 'repetido' => true]);
                    }

                    throw new DomainException('Esta parcela não foi paga: não há recebimento para estornar.');
                }

                if (($p['origem_recebimento'] ?? 'BALCAO') === 'ONLINE') {
                    throw new DomainException('Este recebimento foi feito online: o estorno vem do provedor de pagamento (o sistema atualiza sozinho quando ele avisa).');
                }

                if (mb_strlen($justificativa) < 5) {
                    throw new DomainException('Informe o motivo do estorno do recebimento.');
                }

                $caixa = (new Caixa($this->conn))->getCaixaAberto();

                if (!$caixa) {
                    throw new DomainException('Abra o caixa para registrar a devolução do valor.');
                }

                $tentativa = max(1, (int) $p['pagamentos_realizados']);
                $forma = $p['forma_pagamento'] ?: null;

                $idCaixa = (new MovimentacaoCaixa($this->conn))->registrar(
                    $caixa['id_caixa'], 'SAIDA', (float) $p['valor'], $forma, 'OS',
                    'Estorno do recebimento — OS ' . $p['os_numero'] . ' parcela ' . $p['numero'] . ': ' . mb_substr($justificativa, 0, 120),
                    $usuarioId, 'OS', (int) $p['ordem_servico_id'],
                    'ESTORNO_PARCELA:' . $parcelaId . ':' . $tentativa
                );

                if (!$idCaixa) {
                    throw new DomainException('Não foi possível lançar o estorno no caixa.');
                }

                $this->conn->prepare(
                    'UPDATE ordem_servico_parcelas
                     SET pago = 0, data_pagamento = NULL, forma_pagamento = NULL, movimentacao_caixa_id = NULL, taxa_valor = 0,
                         estornada_em = NOW(), estornada_por = ?, estorno_justificativa = ?
                     WHERE id_parcela = ?'
                )->execute([$usuarioId, mb_substr($justificativa, 0, 255), $parcelaId]);

                Historico::registrar(
                    $this->conn, 'PARCELA_OS', $parcelaId, 'RECEBIDA', 'PENDENTE', $usuarioId, 'Recebimento estornado: ' . $justificativa,
                    Historico::ORIGEM_USUARIO,
                    ['valor' => (float) $p['valor'], 'recebimento_original' => $p['movimentacao_caixa_id'], 'estorno_caixa_id' => $idCaixa]
                );

                return array_merge($saida, ['ok' => true]);
            });
        } catch (DomainException $e) {
            return array_merge($saida, ['erro' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[pagamento OS] Falha ao estornar parcela: ' . $e->getMessage());
            return array_merge($saida, ['erro' => 'Não foi possível estornar o recebimento.']);
        }
    }
}
