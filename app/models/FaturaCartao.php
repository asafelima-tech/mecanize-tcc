<?php

/**
 * Faturas dos cartões corporativos.
 *
 * Uma fatura NÃO é uma despesa: é o agrupamento, por mês, das parcelas das compras feitas no
 * cartão. Os valores aqui são sempre derivados dessas parcelas — o total da fatura é a soma
 * delas — e pagar a fatura (ContaPagar::pagarFatura) quita as parcelas, sem criar outra despesa.
 *
 * Situação (calculada, não gravada, exceto a data do pagamento):
 *   PAGA     tem data de pagamento;
 *   VENCIDA  passou do vencimento sem pagar;
 *   FECHADA  já fechou (não entram mais compras) e aguarda pagamento;
 *   ABERTA   ainda recebe compras.
 */
class FaturaCartao
{
    public const SITUACAO_LABEL = ['ABERTA' => 'Aberta', 'FECHADA' => 'Fechada', 'VENCIDA' => 'Vencida', 'PAGA' => 'Paga'];
    public const SITUACAO_BADGE = ['ABERTA' => 'badge--info', 'FECHADA' => 'badge--warning', 'VENCIDA' => 'badge--danger', 'PAGA' => 'badge--success'];

    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    public static function situacao(array $f, ?string $hoje = null): string
    {
        $hoje = $hoje ?? date('Y-m-d');

        if (!empty($f['paga_em'])) {
            return 'PAGA';
        }

        if ($f['vence_em'] < $hoje) {
            return 'VENCIDA';
        }

        return $f['fecha_em'] < $hoje ? 'FECHADA' : 'ABERTA';
    }

    private const SELECT_BASE = "
        SELECT f.*, c.apelido AS cartao_apelido, c.instituicao,
               COALESCE((SELECT SUM(p.valor) FROM contas_pagar_parcelas p JOIN despesas d ON d.id_despesa = p.despesa_id
                          WHERE p.fatura_id = f.id_fatura AND p.status <> 'CANCELADA' AND d.status = 'ATIVA'), 0) AS total,
               COALESCE((SELECT SUM(p.valor) FROM contas_pagar_parcelas p JOIN despesas d ON d.id_despesa = p.despesa_id
                          WHERE p.fatura_id = f.id_fatura AND p.status = 'PENDENTE' AND d.status = 'ATIVA'), 0) AS pendente,
               (SELECT COUNT(*) FROM contas_pagar_parcelas p JOIN despesas d ON d.id_despesa = p.despesa_id
                 WHERE p.fatura_id = f.id_fatura AND p.status <> 'CANCELADA' AND d.status = 'ATIVA') AS qtd_compras
        FROM faturas_cartao f
        JOIN cartoes_corporativos c ON c.id_cartao = f.cartao_id
    ";

    private function completar(array $f): array
    {
        $f['total'] = round((float) $f['total'], 2);
        $f['pendente'] = round((float) $f['pendente'], 2);
        $f['situacao'] = self::situacao($f);

        return $f;
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare(self::SELECT_BASE . ' WHERE f.id_fatura = ?');
        $stmt->execute([$id]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        return $f ? $this->completar($f) : null;
    }

    /** Faturas de um cartão (as que têm compras), da mais recente para a mais antiga. */
    public function doCartao(int $cartaoId): array
    {
        $stmt = $this->conn->prepare(self::SELECT_BASE . ' WHERE f.cartao_id = ? HAVING qtd_compras > 0 ORDER BY f.referencia DESC');
        $stmt->execute([$cartaoId]);

        return array_map(fn($f) => $this->completar($f), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Parcelas que compõem a fatura, com a compra de origem. */
    public function compras(int $faturaId): array
    {
        $stmt = $this->conn->prepare("
            SELECT p.*, d.id_despesa, d.numero AS despesa_numero, d.descricao, d.data_compra, d.qtd_parcelas, d.valor_total, fo.nome AS fornecedor_nome
            FROM contas_pagar_parcelas p
            JOIN despesas d ON d.id_despesa = p.despesa_id
            LEFT JOIN fornecedores fo ON fo.id_fornecedor = d.fornecedor_id
            WHERE p.fatura_id = ? AND p.status <> 'CANCELADA' AND d.status = 'ATIVA'
            ORDER BY d.data_compra, p.id_conta_parcela
        ");
        $stmt->execute([$faturaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Pagamentos da fatura (inclusive os estornados, para o histórico). */
    public function pagamentos(int $faturaId): array
    {
        $stmt = $this->conn->prepare("
            SELECT pg.*, u.nome AS usuario_nome, ue.nome AS estornado_por_nome
            FROM pagamentos_pagar pg
            LEFT JOIN usuarios u ON u.id_usuario = pg.usuario_id
            LEFT JOIN usuarios ue ON ue.id_usuario = pg.estornado_por
            WHERE pg.fatura_id = ? ORDER BY pg.id_pagamento DESC
        ");
        $stmt->execute([$faturaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
