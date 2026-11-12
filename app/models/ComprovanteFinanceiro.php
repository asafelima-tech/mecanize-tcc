<?php

require_once __DIR__ . '/../core/Armazenamento.php';

/**
 * Comprovantes das despesas e dos pagamentos (nota fiscal, recibo, boleto pago, extrato).
 * O arquivo fica em storage/ (fora da área pública); aqui só o registro e a autorização.
 */
class ComprovanteFinanceiro
{
    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    /**
     * @param array{despesa_id?:int, pagamento_id?:int} $alvo exatamente um dos dois
     * @return array{ok:bool, erro:?string, id:?int}
     */
    public function anexar(array $alvo, array $arquivo, ?int $usuarioId, bool $exigirUpload = true): array
    {
        $despesaId = !empty($alvo['despesa_id']) ? (int) $alvo['despesa_id'] : null;
        $pagamentoId = !empty($alvo['pagamento_id']) ? (int) $alvo['pagamento_id'] : null;

        try {
            if (($despesaId === null) === ($pagamentoId === null)) {
                throw new DomainException('O comprovante precisa estar ligado a uma despesa ou a um pagamento.');
            }

            if ($despesaId !== null) {
                $ok = $this->conn->prepare('SELECT 1 FROM despesas WHERE id_despesa = ?');
                $ok->execute([$despesaId]);
            } else {
                $ok = $this->conn->prepare('SELECT 1 FROM pagamentos_pagar WHERE id_pagamento = ?');
                $ok->execute([$pagamentoId]);
            }

            if (!$ok->fetchColumn()) {
                throw new DomainException('Registro não encontrado.');
            }

            $meta = Armazenamento::guardar($arquivo, 'comprovantes', $exigirUpload);

            $this->conn->prepare('INSERT INTO comprovantes_financeiros (despesa_id, pagamento_id, nome_original, caminho, mime, tamanho, enviado_por) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$despesaId, $pagamentoId, $meta['nome_original'], $meta['caminho'], $meta['mime'], $meta['tamanho'], $usuarioId]);

            return ['ok' => true, 'erro' => null, 'id' => (int) $this->conn->lastInsertId()];
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'id' => null];
        } catch (\Throwable $e) {
            error_log('[ComprovanteFinanceiro anexar] ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível guardar o comprovante.', 'id' => null];
        }
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM comprovantes_financeiros WHERE id_comprovante = ?');
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Comprovantes da despesa: os dela e os dos pagamentos que quitaram as parcelas dela. */
    public function daDespesa(int $despesaId): array
    {
        $stmt = $this->conn->prepare("
            SELECT c.*, u.nome AS enviado_por_nome
            FROM comprovantes_financeiros c LEFT JOIN usuarios u ON u.id_usuario = c.enviado_por
            WHERE c.despesa_id = :d
               OR c.pagamento_id IN (
                    SELECT pg.id_pagamento FROM pagamentos_pagar pg
                    WHERE pg.parcela_id IN (SELECT id_conta_parcela FROM contas_pagar_parcelas WHERE despesa_id = :d2)
                       OR pg.fatura_id IN (SELECT fatura_id FROM contas_pagar_parcelas WHERE despesa_id = :d3 AND fatura_id IS NOT NULL)
               )
            ORDER BY c.id_comprovante DESC
        ");
        $stmt->execute([':d' => $despesaId, ':d2' => $despesaId, ':d3' => $despesaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function doPagamento(int $pagamentoId): array
    {
        $stmt = $this->conn->prepare('SELECT * FROM comprovantes_financeiros WHERE pagamento_id = ? ORDER BY id_comprovante DESC');
        $stmt->execute([$pagamentoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
