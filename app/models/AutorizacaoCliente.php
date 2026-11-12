<?php

require_once __DIR__ . '/../core/Transacao.php';
require_once __DIR__ . '/Comunicacao.php';
require_once __DIR__ . '/../core/Historico.php';

/**
 * Autorizações do cliente: quem autorizou o quê, por qual canal, até que valor.
 *
 *   ORCAMENTO     o cliente aprovou um orçamento → autoriza até o total dele;
 *   VALOR_ABERTO  o cliente aceitou o serviço "sem limite de valor" (atendimento rápido);
 *   COMPLEMENTAR  o serviço cresceu depois da autorização → pede-se mais.
 *
 * REGRA DE OURO: "aceito qualquer valor" NÃO elimina o controle. A autorização com
 * valor em aberto vale para o ESCOPO avaliado e fica registrada com canal e evidência;
 * a OS carrega `valor_autorizado` (o teto: o limite acordado, ou o total do que foi
 * avaliado) e qualquer valor ACIMA disso exige autorização complementar — a OS não
 * segue para conferência enquanto o total passar do autorizado ou houver pedido sem
 * resposta. Ver bloqueiosDaOs().
 */
class AutorizacaoCliente
{
    public const CANAIS = [
        'PRESENCIAL' => 'Presencial (balcão)',
        'TELEFONE'   => 'Telefone',
        'WHATSAPP'   => 'WhatsApp',
        'PORTAL'     => 'Portal do cliente',
        'OUTRO'      => 'Outro',
    ];

    public const STATUS_LABEL = [
        'SOLICITADA' => 'Aguardando resposta do cliente',
        'APROVADA'   => 'Autorizada',
        'RECUSADA'   => 'Recusada',
        'REVOGADA'   => 'Revogada',
    ];

    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    /* ── Consulta ────────────────────────────────────────────────────────── */

    public function ativaDaOcorrencia(int $ocorrenciaId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT a.*, u.nome AS registrada_por_nome
            FROM autorizacoes_cliente a LEFT JOIN usuarios u ON u.id_usuario = a.registrada_por
            WHERE a.ocorrencia_id = ? AND a.tipo = 'VALOR_ABERTO' AND a.status = 'APROVADA'
            ORDER BY a.id_autorizacao DESC LIMIT 1
        ");
        $stmt->execute([$ocorrenciaId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Todas as autorizações ligadas à ocorrência ou à OS, da mais recente para a mais antiga. */
    public function listar(?int $ocorrenciaId, ?int $osId = null): array
    {
        $stmt = $this->conn->prepare("
            SELECT a.*, u.nome AS registrada_por_nome, d.nome AS decidida_por_nome
            FROM autorizacoes_cliente a
            LEFT JOIN usuarios u ON u.id_usuario = a.registrada_por
            LEFT JOIN usuarios d ON d.id_usuario = a.decidida_por
            WHERE (a.ocorrencia_id = :oc AND :oc2 IS NOT NULL) OR (a.ordem_servico_id = :os AND :os2 IS NOT NULL)
            ORDER BY a.id_autorizacao DESC
        ");
        $stmt->execute([':oc' => $ocorrenciaId, ':oc2' => $ocorrenciaId, ':os' => $osId, ':os2' => $osId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * O que impede a OS de seguir para conferência por falta de autorização do cliente.
     * Lista vazia = pode seguir. OS sem valor_autorizado (aberta direto no balcão) não é controlada.
     *
     * @return string[]
     */
    public function bloqueiosDaOs(int $osId): array
    {
        $stmt = $this->conn->prepare('SELECT valor_total_os, valor_autorizado FROM ordens_servico WHERE id_ordem_servico = ?');
        $stmt->execute([$osId]);
        $os = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$os || $os['valor_autorizado'] === null) {
            return [];
        }

        $bloqueios = [];

        $pend = $this->conn->prepare("SELECT COALESCE(SUM(valor_adicional), 0), COUNT(*) FROM autorizacoes_cliente WHERE ordem_servico_id = ? AND status = 'SOLICITADA'");
        $pend->execute([$osId]);
        [$valorPendente, $qtdPendente] = $pend->fetch(PDO::FETCH_NUM);

        if ((int) $qtdPendente > 0) {
            $bloqueios[] = 'Há ' . $qtdPendente . ' pedido(s) de autorização complementar aguardando a resposta do cliente.';
        }

        $total = (float) $os['valor_total_os'];
        $aut   = (float) $os['valor_autorizado'];

        if ($total > $aut + 0.005) {
            $bloqueios[] = sprintf(
                'O valor da OS (R$ %s) passou do que o cliente autorizou (R$ %s). Solicite uma autorização complementar de R$ %s.',
                number_format($total, 2, ',', '.'),
                number_format($aut, 2, ',', '.'),
                number_format($total - $aut, 2, ',', '.')
            );
        }

        return $bloqueios;
    }

    /* ── Registro ────────────────────────────────────────────────────────── */

    /** A aprovação de um orçamento é, ela mesma, uma autorização — fica registrada. Roda na transação de quem chama. */
    public function registrarDoOrcamento(
        int $orcamentoId,
        int $osId,
        ?int $ocorrenciaId,
        int $clienteId,
        float $total,
        string $canal,
        ?int $usuarioId,
        ?string $evidencia
    ): int {
        $this->conn->prepare("
            INSERT INTO autorizacoes_cliente
                (ocorrencia_id, ordem_servico_id, orcamento_id, cliente_id, tipo, status, escopo, limite_valor, canal, evidencia, registrada_por, decidida_por, decidida_em)
            VALUES (?, ?, ?, ?, 'ORCAMENTO', 'APROVADA', ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $ocorrenciaId, $osId, $orcamentoId, $clienteId,
            'Orçamento aprovado pelo cliente',
            $total,
            isset(self::CANAIS[$canal]) ? $canal : 'OUTRO',
            $evidencia,
            $usuarioId, $usuarioId,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    /**
     * Fluxo rápido: o cliente deixou o carro aceitando o serviço sem fixar valor.
     * Registra QUEM autorizou, o ESCOPO, o LIMITE (se houve), o CANAL e a EVIDÊNCIA.
     *
     * @param array{escopo:string, evidencia:string, canal:string, limite_valor?:mixed} $d
     * @return array{ok:bool, erro:?string, id:?int}
     */
    public function registrarValorAberto(int $ocorrenciaId, array $d, ?int $usuarioId): array
    {
        try {
            return Transacao::executar($this->conn, function () use ($ocorrenciaId, $d, $usuarioId) {
                $stmt = $this->conn->prepare('SELECT status, cliente_id FROM ocorrencias WHERE id_ocorrencia = ? FOR UPDATE');
                $stmt->execute([$ocorrenciaId]);
                $oc = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$oc) {
                    throw new DomainException('Ocorrência não encontrada.');
                }

                if (!in_array($oc['status'], ['AGUARDANDO_AVALIACAO', 'EM_AVALIACAO', 'AGUARDANDO_ORCAMENTO'], true)) {
                    throw new DomainException('A autorização com valor em aberto só vale antes de a OS ser aberta.');
                }

                $escopo    = trim((string) ($d['escopo'] ?? ''));
                $evidencia = trim((string) ($d['evidencia'] ?? ''));
                $canal     = (string) ($d['canal'] ?? '');

                if (mb_strlen($escopo) < 10) {
                    throw new DomainException('Descreva o que o cliente autorizou (o escopo do serviço).');
                }

                if (mb_strlen($evidencia) < 5) {
                    throw new DomainException('Registre a evidência da autorização (quem autorizou, quando e como — ex.: "confirmou ao balcão às 15h, presente a atendente Laura" ou o texto da mensagem).');
                }

                if (!isset(self::CANAIS[$canal])) {
                    throw new DomainException('Informe o canal em que o cliente autorizou.');
                }

                $limite = null;
                if (isset($d['limite_valor']) && trim((string) $d['limite_valor']) !== '') {
                    $limite = round((float) str_replace(',', '.', (string) $d['limite_valor']), 2);

                    if ($limite < 0) {
                        throw new DomainException('O limite de valor não pode ser negativo.');
                    }
                }

                // Uma autorização vigente por ocorrência: a nova substitui a anterior (que fica REVOGADA no histórico).
                $this->conn->prepare("
                    UPDATE autorizacoes_cliente SET status = 'REVOGADA'
                    WHERE ocorrencia_id = ? AND tipo = 'VALOR_ABERTO' AND status = 'APROVADA'
                ")->execute([$ocorrenciaId]);

                $this->conn->prepare("
                    INSERT INTO autorizacoes_cliente
                        (ocorrencia_id, cliente_id, tipo, status, escopo, limite_valor, canal, evidencia, registrada_por, decidida_por, decidida_em)
                    VALUES (?, ?, 'VALOR_ABERTO', 'APROVADA', ?, ?, ?, ?, ?, ?, NOW())
                ")->execute([$ocorrenciaId, $oc['cliente_id'], $escopo, $limite, $canal, $evidencia, $usuarioId, $usuarioId]);

                $id = (int) $this->conn->lastInsertId();

                Historico::registrar(
                    $this->conn, 'OCORRENCIA', $ocorrenciaId, $oc['status'], $oc['status'], $usuarioId,
                    'Autorização do cliente registrada (' . self::CANAIS[$canal] . ')'
                    . ($limite !== null ? ' até R$ ' . number_format($limite, 2, ',', '.') : ' — sem limite informado, vale só para o escopo avaliado'),
                    Historico::ORIGEM_USUARIO,
                    ['autorizacao_id' => $id]
                );

                return ['ok' => true, 'erro' => null, 'id' => $id];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'id' => null];
        } catch (\Throwable $e) {
            error_log('[Autorizacao valor aberto] ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível registrar a autorização.', 'id' => null];
        }
    }

    /**
     * O serviço cresceu: pede-se mais ao cliente. Fica SOLICITADA até alguém registrar a
     * resposta dele; enquanto isso a OS não segue para conferência.
     *
     * @return array{ok:bool, erro:?string, id:?int}
     */
    public function solicitarComplemento(int $osId, float $valorAdicional, string $motivo, ?int $usuarioId): array
    {
        $motivo = trim($motivo);

        try {
            return Transacao::executar($this->conn, function () use ($osId, $valorAdicional, $motivo, $usuarioId) {
                if ($valorAdicional <= 0) {
                    throw new DomainException('Informe o valor adicional que precisa ser autorizado.');
                }

                if (mb_strlen($motivo) < 5) {
                    throw new DomainException('Explique o que mudou e por que é preciso um valor adicional.');
                }

                $stmt = $this->conn->prepare('SELECT status, cliente_id, ocorrencia_id, valor_autorizado, veiculo_id, numero FROM ordens_servico WHERE id_ordem_servico = ? FOR UPDATE');
                $stmt->execute([$osId]);
                $os = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$os) {
                    throw new DomainException('Ordem de serviço não encontrada.');
                }

                if (in_array($os['status'], ['CONCLUIDA', 'CANCELADA'], true)) {
                    throw new DomainException('Esta OS já foi encerrada.');
                }

                $this->conn->prepare("
                    INSERT INTO autorizacoes_cliente
                        (ocorrencia_id, ordem_servico_id, cliente_id, tipo, status, escopo, valor_adicional, canal, registrada_por)
                    VALUES (?, ?, ?, 'COMPLEMENTAR', 'SOLICITADA', ?, ?, 'OUTRO', ?)
                ")->execute([$os['ocorrencia_id'], $osId, $os['cliente_id'], $motivo, round($valorAdicional, 2), $usuarioId]);

                $id = (int) $this->conn->lastInsertId();

                Historico::registrar(
                    $this->conn, 'OS', $osId, $os['status'], $os['status'], $usuarioId,
                    'Autorização complementar solicitada: R$ ' . number_format($valorAdicional, 2, ',', '.') . ' — ' . $motivo,
                    Historico::ORIGEM_USUARIO,
                    ['autorizacao_id' => $id]
                );

                Comunicacao::evento(
                    $this->conn, 'AUTORIZACAO_ADICIONAL', (int) $os['cliente_id'],
                    Comunicacao::dadosDoVeiculo($this->conn, (int) $os['veiculo_id']) + ['numero' => $os['numero'], 'valor' => round($valorAdicional, 2), 'motivo' => $motivo],
                    'AUTORIZACAO:' . $id . ':SOLICITADA', 'OS', $osId, ['escopo' => 'OS', 'entidade' => $osId]
                );

                return ['ok' => true, 'erro' => null, 'id' => $id];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'id' => null];
        } catch (\Throwable $e) {
            error_log('[Autorizacao complemento] ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível registrar o pedido.', 'id' => null];
        }
    }

    /**
     * Registra a resposta do cliente a um pedido complementar. Aprovar aumenta o
     * valor_autorizado da OS; recusar deixa tudo como estava (a OS continua barrada
     * se o total passa do autorizado). Exige canal e evidência, como toda autorização.
     * Idempotente: decidir de novo uma decisão já tomada devolve ok sem repetir.
     *
     * @return array{ok:bool, erro:?string, repetido:bool}
     */
    public function decidir(int $autorizacaoId, bool $aprovar, string $canal, string $evidencia, ?int $usuarioId, string $origem = Historico::ORIGEM_USUARIO): array
    {
        try {
            return Transacao::executar($this->conn, function () use ($autorizacaoId, $aprovar, $canal, $evidencia, $usuarioId, $origem) {
                $stmt = $this->conn->prepare('SELECT * FROM autorizacoes_cliente WHERE id_autorizacao = ? FOR UPDATE');
                $stmt->execute([$autorizacaoId]);
                $a = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$a || $a['tipo'] !== 'COMPLEMENTAR') {
                    throw new DomainException('Pedido de autorização não encontrado.');
                }

                $alvo = $aprovar ? 'APROVADA' : 'RECUSADA';

                if ($a['status'] === $alvo) {
                    return ['ok' => true, 'erro' => null, 'repetido' => true];
                }

                if ($a['status'] !== 'SOLICITADA') {
                    throw new DomainException('Este pedido já foi respondido (' . mb_strtolower(self::STATUS_LABEL[$a['status']]) . ').');
                }

                $evidencia = trim($evidencia);

                if (!isset(self::CANAIS[$canal])) {
                    throw new DomainException('Informe o canal em que o cliente respondeu.');
                }

                if (mb_strlen($evidencia) < 5) {
                    throw new DomainException('Registre a evidência da resposta do cliente.');
                }

                $this->conn->prepare("
                    UPDATE autorizacoes_cliente
                    SET status = ?, canal = ?, evidencia = ?, decidida_por = ?, decidida_em = NOW()
                    WHERE id_autorizacao = ?
                ")->execute([$alvo, $canal, $evidencia, $usuarioId, $autorizacaoId]);

                if ($aprovar) {
                    $os = $this->conn->prepare('SELECT valor_autorizado FROM ordens_servico WHERE id_ordem_servico = ? FOR UPDATE');
                    $os->execute([$a['ordem_servico_id']]);
                    $atual = $os->fetchColumn();

                    if ($atual !== false && $atual !== null) {
                        $this->conn->prepare('UPDATE ordens_servico SET valor_autorizado = valor_autorizado + ? WHERE id_ordem_servico = ?')
                            ->execute([$a['valor_adicional'], $a['ordem_servico_id']]);
                    }
                }

                $st = $this->conn->prepare('SELECT status FROM ordens_servico WHERE id_ordem_servico = ?');
                $st->execute([$a['ordem_servico_id']]);
                $statusOs = (string) $st->fetchColumn();

                Historico::registrar(
                    $this->conn, 'OS', (int) $a['ordem_servico_id'], $statusOs, $statusOs, $usuarioId,
                    'Autorização complementar ' . ($aprovar ? 'APROVADA' : 'RECUSADA') . ' pelo cliente (' . self::CANAIS[$canal] . ')',
                    $origem,
                    ['autorizacao_id' => $autorizacaoId]
                );

                return ['ok' => true, 'erro' => null, 'repetido' => false];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'repetido' => false];
        } catch (\Throwable $e) {
            error_log('[Autorizacao decidir] ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível registrar a resposta.', 'repetido' => false];
        }
    }
}
