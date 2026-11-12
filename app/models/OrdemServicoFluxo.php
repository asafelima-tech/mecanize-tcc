<?php

require_once __DIR__ . '/../core/Transacao.php';
require_once __DIR__ . '/Comunicacao.php';
require_once __DIR__ . '/../core/Historico.php';
require_once __DIR__ . '/../core/Permissao.php';
require_once __DIR__ . '/OrdemServico.php';
require_once __DIR__ . '/OrdemServicoPagamento.php';
require_once __DIR__ . '/ConsumoEstoque.php';
require_once __DIR__ . '/AutorizacaoCliente.php';
require_once __DIR__ . '/OcorrenciaFluxo.php';

/**
 * Máquina de estados da ordem de serviço.
 *
 *   ABERTA ⇄ EM_ANDAMENTO ⇄ AGUARDANDO_PECA
 *                 │
 *                 ▼  (técnico termina)
 *       AGUARDANDO_CONFERENCIA ──(administrador aprova)──▶ AGUARDANDO_RETIRADA ──▶ CONCLUIDA
 *                 │                                              │
 *                 └────────(administrador devolve)───▶ EM_ANDAMENTO ◀──┘
 *
 *   qualquer etapa aberta ──(administrador cancela, com motivo)──▶ CANCELADA
 *
 * Toda mudança de estado do sistema passa por transicionar(). É ele que:
 *  - recusa transições que não existem (ABERTA → CONCLUIDA, reabrir OS entregue…);
 *  - confere o papel de quem pede (conferir, devolver e cancelar são do ADMIN;
 *    técnico e atendente têm as mesmas permissões);
 *  - aplica os efeitos da etapa (comissão, plano de cobrança, devolução de peças…)
 *    na MESMA transação da mudança — ou tudo, ou nada;
 *  - é idempotente: pedir um estado em que a OS já está não repete nenhum efeito
 *    (clique duplo, reenvio, aba antiga);
 *  - grava a trilha (historico_estados).
 *
 * Nada aqui cria dinheiro "recebido": conferir a OS gera a COBRANÇA (parcelas),
 * e o recebimento é um passo separado (OrdemServicoPagamento::pagarParcela).
 */
final class OrdemServicoFluxo
{
    /** estado atual => [estado de destino => nível de acesso exigido] */
    public const TRANSICOES = [
        'ABERTA'                 => ['EM_ANDAMENTO' => 'EQUIPE', 'AGUARDANDO_PECA' => 'EQUIPE', 'CANCELADA' => 'ADMIN'],
        'EM_ANDAMENTO'           => ['ABERTA' => 'EQUIPE', 'AGUARDANDO_PECA' => 'EQUIPE', 'AGUARDANDO_CONFERENCIA' => 'EQUIPE', 'CANCELADA' => 'ADMIN'],
        'AGUARDANDO_PECA'        => ['ABERTA' => 'EQUIPE', 'EM_ANDAMENTO' => 'EQUIPE', 'AGUARDANDO_CONFERENCIA' => 'EQUIPE', 'CANCELADA' => 'ADMIN'],
        'AGUARDANDO_CONFERENCIA' => ['AGUARDANDO_RETIRADA' => 'ADMIN', 'EM_ANDAMENTO' => 'ADMIN', 'CANCELADA' => 'ADMIN'],
        'AGUARDANDO_RETIRADA'    => ['CONCLUIDA' => 'EQUIPE', 'EM_ANDAMENTO' => 'ADMIN', 'CANCELADA' => 'ADMIN'],
        'CONCLUIDA'              => [],
        'CANCELADA'              => [],
    ];

    private PDO $conn;
    private OrdemServico $os;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
        $this->os   = new OrdemServico($this->conn);
    }

    /** Estados para os quais $papel pode levar uma OS que está em $de. */
    public static function destinosPermitidos(string $de, ?string $papel): array
    {
        $destinos = [];

        foreach (self::TRANSICOES[$de] ?? [] as $para => $nivel) {
            if (Permissao::permite($papel, $nivel)) {
                $destinos[] = $para;
            }
        }

        return $destinos;
    }

    /**
     * Leva a OS para o estado $para.
     *
     * $contexto aceita:
     *   motivo               obrigatório para cancelar e para devolver à execução
     *   assinatura_cliente   PNG em data URI (entrega)
     *   assinatura_tecnico   PNG em data URI (término do serviço)
     *   observacoes_tecnicas, checklist_entrega   (término do serviço)
     *   receber_forma        forma de pagamento para já receber a cobrança na entrega
     *   origem               USUARIO (padrão), PORTAL, WEBHOOK, SISTEMA
     *
     * @return array{ok:bool, erro:?string, repetido:bool, de:?string, para:string, avisos:string[]}
     */
    public function transicionar(int $osId, string $para, ?int $usuarioId, ?string $papel, array $contexto = []): array
    {
        $saida = ['ok' => false, 'erro' => null, 'repetido' => false, 'de' => null, 'para' => $para, 'avisos' => []];

        try {
            return Transacao::executar($this->conn, function () use ($osId, $para, $usuarioId, $papel, $contexto, $saida) {
                $stmt = $this->conn->prepare('SELECT * FROM ordens_servico WHERE id_ordem_servico = ? FOR UPDATE');
                $stmt->execute([$osId]);
                $os = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$os) {
                    throw new DomainException('Ordem de serviço não encontrada.');
                }

                $de = (string) $os['status'];
                $saida['de'] = $de;

                if ($de === $para) {
                    // Idempotência: já está lá. Nenhum efeito é repetido.
                    $saida['ok'] = true;
                    $saida['repetido'] = true;
                    return $saida;
                }

                $nivel = self::TRANSICOES[$de][$para] ?? null;

                if ($nivel === null) {
                    throw new DomainException(sprintf(
                        'Não é possível passar a OS de "%s" para "%s".',
                        OrdemServico::rotuloStatus($de),
                        OrdemServico::rotuloStatus($para)
                    ));
                }

                if (!Permissao::permite($papel, $nivel)) {
                    throw new DomainException('Esta etapa só pode ser feita pelo administrador.');
                }

                $motivo = trim((string) ($contexto['motivo'] ?? ''));

                $this->aplicarEfeitos($os, $de, $para, $usuarioId, $motivo, $contexto, $saida);

                $this->conn->prepare("
                    UPDATE ordens_servico
                    SET status = :status,
                        data_conclusao = CASE WHEN :alvo = 'CONCLUIDA' THEN NOW() ELSE NULL END
                    WHERE id_ordem_servico = :id
                ")->execute([':status' => $para, ':alvo' => $para, ':id' => $osId]);

                // A ocorrência de onde a OS veio acompanha o desfecho (entregue → encerrada, cancelada…).
                OcorrenciaFluxo::sincronizarComOs($this->conn, $osId, $para, $usuarioId);

                $historicoId = Historico::registrar(
                    $this->conn,
                    'OS',
                    $osId,
                    $de,
                    $para,
                    $usuarioId,
                    $motivo !== '' ? $motivo : null,
                    $contexto['origem'] ?? Historico::ORIGEM_USUARIO
                );

                $this->avisarCliente($os, $de, $para, $historicoId);

                $saida['ok'] = true;
                return $saida;
            });
        } catch (DomainException $e) {
            $saida['erro'] = $e->getMessage();
            return $saida;
        } catch (\Throwable $e) {
            error_log('[OS fluxo] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            $saida['erro'] = 'Não foi possível concluir esta etapa. Nada foi alterado.';
            return $saida;
        }
    }

    /**
     * A mudança de estado REAL vira mensagem ao cliente (na mesma transação). A chave carrega o id da
     * linha do histórico: cada transição genuína avisa uma vez, e repetir a requisição (que cai em
     * `repetido` antes de chegar aqui) não avisa de novo.
     */
    private function avisarCliente(array $os, string $de, string $para, int $historicoId): void
    {
        $evento = match (true) {
            $de === 'ABERTA' && $para === 'EM_ANDAMENTO' => 'SERVICO_INICIADO',
            $para === 'AGUARDANDO_RETIRADA'              => 'PRONTO_RETIRADA',
            $para === 'CONCLUIDA'                        => 'OS_CONCLUIDA',
            default                                       => null,
        };

        if ($evento === null) {
            return;
        }

        $saldo = $this->conn->prepare('SELECT COALESCE(SUM(valor), 0) FROM ordem_servico_parcelas WHERE ordem_servico_id = ? AND pago = 0');
        $saldo->execute([$os['id_ordem_servico']]);

        $dados = Comunicacao::dadosDoVeiculo($this->conn, (int) $os['veiculo_id']) + ['numero' => $os['numero']];

        if (!empty($os['previsao_conclusao'])) {
            $dados['previsao'] = date('d/m/Y H:i', strtotime($os['previsao_conclusao']));
        }

        if ($evento === 'PRONTO_RETIRADA') {
            $dados['saldo'] = round((float) $saldo->fetchColumn(), 2);
        }

        Comunicacao::evento(
            $this->conn, $evento, (int) $os['cliente_id'], $dados,
            $evento === 'SERVICO_INICIADO' ? 'OS:' . $os['id_ordem_servico'] . ':INICIADO' : 'OS:' . $os['id_ordem_servico'] . ':' . $para . ':' . $historicoId,
            'OS', (int) $os['id_ordem_servico'], ['escopo' => 'OS', 'entidade' => (int) $os['id_ordem_servico']]
        );
    }

    /**
     * Efeitos de cada destino. Qualquer recusa é uma DomainException: a
     * transação inteira desfaz, inclusive o que este método já tivesse gravado.
     */
    private function aplicarEfeitos(array $os, string $de, string $para, ?int $usuarioId, string $motivo, array $ctx, array &$saida): void
    {
        $osId = (int) $os['id_ordem_servico'];

        switch ($para) {
            case 'AGUARDANDO_CONFERENCIA':
                $this->exigirAutorizacaoDoCliente($osId);

                $this->conn->prepare("
                    UPDATE ordens_servico
                    SET assinatura_tecnico       = COALESCE(:assinatura, assinatura_tecnico),
                        tecnico_finalizacao_id   = :tecnico,
                        data_finalizacao_tecnica = NOW(),
                        observacoes_tecnicas     = COALESCE(:obs, observacoes_tecnicas),
                        checklist_entrega        = COALESCE(:checklist, checklist_entrega)
                    WHERE id_ordem_servico = :id
                ")->execute([
                    ':assinatura' => self::pngOuNull($ctx['assinatura_tecnico'] ?? null),
                    ':tecnico'    => $usuarioId && $this->usuarioExiste($usuarioId) ? $usuarioId : null,
                    ':obs'        => ($o = trim((string) ($ctx['observacoes_tecnicas'] ?? ''))) !== '' ? $o : null,
                    ':checklist'  => $ctx['checklist_entrega'] ?? null,
                    ':id'         => $osId,
                ]);
                break;

            case 'AGUARDANDO_RETIRADA':
                $this->conferir($osId, $usuarioId, $saida);
                break;

            case 'CONCLUIDA':
                $this->entregar($osId, $usuarioId, $ctx);
                break;

            case 'CANCELADA':
                $this->cancelar($osId, $usuarioId, $motivo);
                break;

            case 'EM_ANDAMENTO':
                if (in_array($de, ['AGUARDANDO_CONFERENCIA', 'AGUARDANDO_RETIRADA'], true)) {
                    $this->devolverParaExecucao($osId, $motivo);
                }
                break;
        }
    }

    /**
     * Aprovação do administrador: os valores viram definitivos.
     *  1. nada pode ficar "planejado e não usado" — peça prevista que não foi
     *     consumida precisa ser consumida ou tirada da OS;
     *  2. totais recalculados dos itens;
     *  3. comissão de cada técnico calculada e travada;
     *  4. cobrança gerada (as parcelas) — ainda NÃO é dinheiro recebido.
     */
    private function conferir(int $osId, ?int $usuarioId, array &$saida): void
    {
        $this->exigirAutorizacaoDoCliente($osId);

        $pendentes = ConsumoEstoque::pendentesDaOs($this->conn, $osId);

        if ($pendentes) {
            $nomes = array_map(fn($p) => $p['nome'], array_slice($pendentes, 0, 3));
            $mais  = count($pendentes) > 3 ? ' e mais ' . (count($pendentes) - 3) : '';

            throw new DomainException(
                'Há ' . count($pendentes) . ' peça(s) lançada(s) e ainda não consumida(s): '
                . implode(', ', $nomes) . $mais
                . '. Confirme o consumo ou retire-as da OS antes de conferir.'
            );
        }

        $totais = $this->os->recalcularTotais($osId);

        if ($totais['total'] <= 0) {
            $saida['avisos'][] = 'A OS está com valor zero: nenhuma cobrança relevante foi gerada.';
        }

        $this->os->calcularComissoesNaConclusao($osId);

        (new OrdemServicoPagamento($this->conn))->garantirPlano($osId);

        $this->conn->prepare("
            UPDATE ordens_servico SET conferido_por = ?, data_conferencia = NOW() WHERE id_ordem_servico = ?
        ")->execute([$usuarioId && $this->usuarioExiste($usuarioId) ? $usuarioId : null, $osId]);
    }

    /**
     * O serviço não pode seguir enquanto o valor passar do que o cliente autorizou, ou houver
     * pedido de autorização complementar sem resposta (ver AutorizacaoCliente). OS aberta direto
     * no balcão (sem valor autorizado) não é controlada.
     */
    private function exigirAutorizacaoDoCliente(int $osId): void
    {
        $bloqueios = (new AutorizacaoCliente($this->conn))->bloqueiosDaOs($osId);

        if ($bloqueios) {
            throw new DomainException(implode(' ', $bloqueios));
        }
    }

    /**
     * Entrega ao cliente. Pode, no mesmo ato, receber a cobrança no balcão
     * (receber_forma) — mas só se der certo: um recebimento recusado (sem caixa
     * aberto, por exemplo) desfaz a entrega e o usuário tenta de novo, em vez de a
     * OS sair como "entregue" com a cobrança em aberto sem ninguém perceber.
     */
    private function entregar(int $osId, ?int $usuarioId, array $ctx): void
    {
        $assinatura = self::pngOuNull($ctx['assinatura_cliente'] ?? null);

        if ($assinatura !== null) {
            $this->conn->prepare("UPDATE ordens_servico SET assinatura_cliente = ? WHERE id_ordem_servico = ?")
                ->execute([$assinatura, $osId]);
        }

        $forma = (string) ($ctx['receber_forma'] ?? '');

        if ($forma === '') {
            return;
        }

        $pagamento = new OrdemServicoPagamento($this->conn);
        $pendentes = array_values(array_filter($pagamento->parcelas($osId), fn($p) => (int) $p['pago'] !== 1));

        if (empty($pendentes)) {
            return; // nada a receber: já está quitada.
        }

        $erro = $pagamento->pagarParcela((int) $pendentes[0]['id_parcela'], $forma, $usuarioId);

        if ($erro !== null) {
            throw new DomainException('Não foi possível registrar o recebimento: ' . $erro . ' A entrega não foi concluída.');
        }
    }

    /**
     * Cancelamento (ADMIN, com motivo). Devolve ao estoque o que foi consumido,
     * cancela o que estava só previsto, anula as comissões não pagas e remove a
     * cobrança ainda não recebida. Dinheiro JÁ recebido impede o cancelamento
     * direto: precisa de estorno, que é decisão financeira explícita.
     */
    private function cancelar(int $osId, ?int $usuarioId, string $motivo): void
    {
        if ($motivo === '') {
            throw new DomainException('Informe o motivo do cancelamento.');
        }

        $recebidas = $this->conn->prepare('SELECT COUNT(*) FROM ordem_servico_parcelas WHERE ordem_servico_id = ? AND pago = 1');
        $recebidas->execute([$osId]);

        if ((int) $recebidas->fetchColumn() > 0) {
            throw new DomainException('Esta OS já tem recebimento registrado. Estorne o recebimento antes de cancelar.');
        }

        if ($this->os->temComissaoPaga($osId)) {
            throw new DomainException('A comissão desta OS já foi paga a um técnico e não pode ser desfeita por aqui.');
        }

        ConsumoEstoque::desfazerTudoDaOs($this->conn, $osId, $usuarioId, 'OS cancelada: ' . $motivo);
        $this->os->anularComissoesNaoPagas($osId);
        $this->conn->prepare('DELETE FROM ordem_servico_parcelas WHERE ordem_servico_id = ? AND pago = 0')->execute([$osId]);
    }

    /**
     * Conferência reprovada (ou OS reaberta depois de liberada): volta para a
     * execução. A cobrança não recebida e a comissão não paga são desfeitas — serão
     * refeitas, sobre os valores finais, na próxima conferência.
     */
    private function devolverParaExecucao(int $osId, string $motivo): void
    {
        if ($motivo === '') {
            throw new DomainException('Informe o motivo para devolver a OS à execução.');
        }

        $recebidas = $this->conn->prepare('SELECT COUNT(*) FROM ordem_servico_parcelas WHERE ordem_servico_id = ? AND pago = 1');
        $recebidas->execute([$osId]);

        if ((int) $recebidas->fetchColumn() > 0) {
            throw new DomainException('Esta OS já tem recebimento registrado e não pode voltar à execução.');
        }

        if ($this->os->temComissaoPaga($osId)) {
            throw new DomainException('A comissão desta OS já foi paga e a OS não pode voltar à execução.');
        }

        $this->os->reabrirComissoesNaoPagas($osId);
        $this->conn->prepare('DELETE FROM ordem_servico_parcelas WHERE ordem_servico_id = ? AND pago = 0')->execute([$osId]);
        $this->conn->prepare('UPDATE ordens_servico SET conferido_por = NULL, data_conferencia = NULL WHERE id_ordem_servico = ?')->execute([$osId]);
    }

    /* ── utilitários ───────────────────────────────────────────────────── */

    /**
     * Só PNG: é o único formato que o canvas de assinatura emite, e o valor vai
     * direto para uma <img> em PDF. Aceitar outros tipos (SVG, por exemplo) seria
     * superfície de ataque sem nenhum ganho.
     */
    public static function pngOuNull(?string $dataUri): ?string
    {
        $dataUri = trim((string) $dataUri);

        return ($dataUri !== '' && str_starts_with($dataUri, 'data:image/png;base64,')) ? $dataUri : null;
    }

    private function usuarioExiste(int $usuarioId): bool
    {
        $stmt = $this->conn->prepare('SELECT 1 FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $stmt->execute([$usuarioId]);

        return (bool) $stmt->fetchColumn();
    }
}
