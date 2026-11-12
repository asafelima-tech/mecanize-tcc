<?php

require_once __DIR__ . '/../core/Transacao.php';
require_once __DIR__ . '/../core/Historico.php';
require_once __DIR__ . '/OrdemServico.php';

/**
 * Máquina de estados da ocorrência — o caminho do veículo ANTES de virar OS e,
 * depois, o espelho resumido do que acontece com a OS.
 *
 *   AGUARDANDO_AVALIACAO ──▶ EM_AVALIACAO ──▶ AGUARDANDO_ORCAMENTO ──▶ AGUARDANDO_APROVACAO
 *            ▲                    │ ▲                │  ▲                    │      │
 *            └────────────────────┘ └────────────────┘  └────────────────────┘      ▼
 *                                                       │                        EM_ESPERA
 *                      (autorização com valor em aberto)│                          │  ▲
 *                                                       ▼                          │  │
 *                                                   EM_EXECUCAO ◀── (orçamento aprovado)
 *                                                       │
 *                                                       ▼
 *                                       ENCERRADA (OS entregue) · CANCELADA (OS cancelada ou desistência)
 *
 * Quem decide os destinos "EM_EXECUCAO / ENCERRADA / CANCELADA" depois da OS criada
 * é a própria OS: OrdemServicoFluxo chama sincronizarComOs() na mesma transação.
 * Qualquer destino que não esteja em TRANSICOES é recusado — o mesmo princípio da OS.
 */
final class OcorrenciaFluxo
{
    public const LABEL = [
        'AGUARDANDO_AVALIACAO' => 'Aguardando avaliação',
        'EM_AVALIACAO'         => 'Avaliação em andamento',
        'AGUARDANDO_ORCAMENTO' => 'Aguardando orçamento',
        'AGUARDANDO_APROVACAO' => 'Aguardando aprovação',
        'EM_ESPERA'            => 'Em espera',
        'EM_EXECUCAO'          => 'Em execução (OS aberta)',
        'ENCERRADA'            => 'Encerrada',
        'CANCELADA'            => 'Cancelada',
    ];

    public const BADGE = [
        'AGUARDANDO_AVALIACAO' => 'badge--warning',
        'EM_AVALIACAO'         => 'badge--info',
        'AGUARDANDO_ORCAMENTO' => 'badge--purple',
        'AGUARDANDO_APROVACAO' => 'badge--secondary',
        'EM_ESPERA'            => 'badge--neutral',
        'EM_EXECUCAO'          => 'badge--info',
        'ENCERRADA'            => 'badge--success',
        'CANCELADA'            => 'badge--danger',
    ];

    /** Estados em que a ocorrência ainda está "aberta" (não terminou nem foi cancelada). */
    public const ABERTAS = ['AGUARDANDO_AVALIACAO', 'EM_AVALIACAO', 'AGUARDANDO_ORCAMENTO', 'AGUARDANDO_APROVACAO', 'EM_ESPERA', 'EM_EXECUCAO'];

    public const TRANSICOES = [
        'AGUARDANDO_AVALIACAO' => ['EM_AVALIACAO', 'CANCELADA'],
        'EM_AVALIACAO'         => ['AGUARDANDO_AVALIACAO', 'AGUARDANDO_ORCAMENTO', 'ENCERRADA', 'CANCELADA'],
        'AGUARDANDO_ORCAMENTO' => ['EM_AVALIACAO', 'AGUARDANDO_APROVACAO', 'EM_ESPERA', 'EM_EXECUCAO', 'CANCELADA'],
        'AGUARDANDO_APROVACAO' => ['AGUARDANDO_ORCAMENTO', 'EM_ESPERA', 'EM_EXECUCAO', 'CANCELADA'],
        'EM_ESPERA'            => ['AGUARDANDO_ORCAMENTO', 'CANCELADA'],
        'EM_EXECUCAO'          => ['ENCERRADA', 'CANCELADA'],
        'ENCERRADA'            => [],
        'CANCELADA'            => [],
    ];

    public static function rotulo(?string $status): string
    {
        return self::LABEL[$status] ?? (string) $status;
    }

    public static function badge(?string $status): string
    {
        return self::BADGE[$status] ?? 'badge--neutral';
    }

    /**
     * Leva a ocorrência para $para, DENTRO da transação de quem chama (ou numa nova).
     *
     * Idempotente: se já está no destino, devolve ok sem repetir nada. Recusa
     * transição inexistente. Grava a trilha.
     *
     * @return array{ok:bool, erro:?string, repetido:bool, de:?string}
     */
    public static function transicionar(
        PDO $pdo,
        int $ocorrenciaId,
        string $para,
        ?int $usuarioId,
        ?string $motivo = null,
        string $origem = Historico::ORIGEM_USUARIO
    ): array {
        $saida = ['ok' => false, 'erro' => null, 'repetido' => false, 'de' => null];

        try {
            return Transacao::executar($pdo, function () use ($pdo, $ocorrenciaId, $para, $usuarioId, $motivo, $origem, $saida) {
                $stmt = $pdo->prepare('SELECT status FROM ocorrencias WHERE id_ocorrencia = ? FOR UPDATE');
                $stmt->execute([$ocorrenciaId]);
                $de = $stmt->fetchColumn();

                if ($de === false) {
                    throw new DomainException('Ocorrência não encontrada.');
                }

                $saida['de'] = $de;

                if ($de === $para) {
                    return array_merge($saida, ['ok' => true, 'repetido' => true]);
                }

                if (!in_array($para, self::TRANSICOES[$de] ?? [], true)) {
                    throw new DomainException(sprintf(
                        'A ocorrência não pode passar de "%s" para "%s".',
                        mb_strtolower(self::rotulo($de)),
                        mb_strtolower(self::rotulo($para))
                    ));
                }

                $pdo->prepare('UPDATE ocorrencias SET status = ? WHERE id_ocorrencia = ?')->execute([$para, $ocorrenciaId]);

                Historico::registrar($pdo, 'OCORRENCIA', $ocorrenciaId, $de, $para, $usuarioId, $motivo, $origem);

                return array_merge($saida, ['ok' => true]);
            });
        } catch (DomainException $e) {
            return array_merge($saida, ['erro' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[Ocorrencia fluxo] ' . $e->getMessage());
            return array_merge($saida, ['erro' => 'Não foi possível mudar a etapa da ocorrência.']);
        }
    }

    /**
     * Espelha na ocorrência o que aconteceu com a OS ligada a ela. Chamado por
     * OrdemServicoFluxo na mesma transação da mudança da OS. Sem ocorrência ligada,
     * não faz nada (OS aberta direto no balcão).
     */
    public static function sincronizarComOs(PDO $pdo, int $osId, string $statusOs, ?int $usuarioId): void
    {
        $stmt = $pdo->prepare('SELECT ocorrencia_id FROM ordens_servico WHERE id_ordem_servico = ?');
        $stmt->execute([$osId]);
        $ocorrenciaId = $stmt->fetchColumn();

        if (!$ocorrenciaId) {
            return;
        }

        $alvo = match ($statusOs) {
            'CONCLUIDA' => 'ENCERRADA',
            'CANCELADA' => 'CANCELADA',
            default     => null,
        };

        if ($alvo === null) {
            return;
        }

        $r = self::transicionar($pdo, (int) $ocorrenciaId, $alvo, $usuarioId, 'OS ' . mb_strtolower(OrdemServico::rotuloStatus($statusOs)), Historico::ORIGEM_SISTEMA);

        // Já estar no destino é sucesso; qualquer outra recusa aqui é um defeito de
        // sincronização e não pode passar em silêncio (desfaz a mudança da OS).
        if (!$r['ok']) {
            throw new DomainException('Não foi possível atualizar a ocorrência da OS: ' . $r['erro']);
        }
    }
}
