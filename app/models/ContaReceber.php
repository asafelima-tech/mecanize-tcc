<?php

/**
 * Contas a receber: as parcelas das ordens de serviço que já foram COBRADAS.
 *
 * O que entra aqui: parcelas de OS conferidas (AGUARDANDO_RETIRADA ou CONCLUIDA) — a cobrança
 * existe desde a conferência do administrador. Parcela de OS ainda em execução não é "a receber":
 * o trabalho não foi entregue nem conferido. Concluir a OS ou aprovar o orçamento NÃO é dinheiro
 * recebido; só a baixa da parcela (OrdemServicoPagamento::pagarParcela) é.
 *
 * Situação: RECEBIDA (paga), VENCIDA (pendente com vencimento passado) ou PENDENTE. Depois que a
 * 1ª de 5 parcelas é recebida, as outras 4 seguem pendentes e somam no "a receber".
 */
class ContaReceber
{
    /** Status da OS cujas parcelas já são cobrança. */
    public const OS_COBRADAS = ['AGUARDANDO_RETIRADA', 'CONCLUIDA'];

    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    /**
     * @param array{situacao?:string, de?:string, ate?:string, forma?:string, cliente_id?:int, busca?:string} $f
     *   situacao: abertas (padrão) · vencidas · recebidas · todas. Em "recebidas", de/ate filtram a
     *   data do recebimento; nas demais, o vencimento.
     */
    public function listar(array $f = [], int $limite = 300): array
    {
        $situacao = $f['situacao'] ?? 'abertas';
        $where = ["os.status IN ('AGUARDANDO_RETIRADA','CONCLUIDA')"];
        $params = [];

        switch ($situacao) {
            case 'vencidas':
                $where[] = 'p.pago = 0 AND p.vencimento < CURDATE()';
                break;
            case 'recebidas':
                $where[] = 'p.pago = 1';
                break;
            case 'todas':
                break;
            default:
                $where[] = 'p.pago = 0';
        }

        $colunaData = $situacao === 'recebidas' ? 'DATE(p.data_pagamento)' : 'p.vencimento';

        if (!empty($f['de'])) {
            $where[] = "{$colunaData} >= ?";
            $params[] = $f['de'];
        }

        if (!empty($f['ate'])) {
            $where[] = "{$colunaData} <= ?";
            $params[] = $f['ate'];
        }

        if (!empty($f['forma'])) {
            $where[] = '(p.forma_pagamento = ? OR (p.pago = 0 AND os.pagamento_forma = ?))';
            array_push($params, $f['forma'], $f['forma']);
        }

        if (!empty($f['cliente_id'])) {
            $where[] = 'os.cliente_id = ?';
            $params[] = (int) $f['cliente_id'];
        }

        if (!empty($f['busca'])) {
            $where[] = '(c.nome LIKE ? OR os.numero LIKE ? OR v.placa LIKE ?)';
            $t = '%' . trim($f['busca']) . '%';
            array_push($params, $t, $t, $t);
        }

        $stmt = $this->conn->prepare("
            SELECT p.*, os.id_ordem_servico, os.numero AS os_numero, os.status AS os_status, os.cliente_id, os.pagamento_forma AS forma_prevista,
                   os.pagamento_total, c.nome AS cliente_nome, v.placa,
                   (SELECT COUNT(*) FROM ordem_servico_parcelas x WHERE x.ordem_servico_id = os.id_ordem_servico) AS total_parcelas,
                   CASE WHEN p.pago = 1 THEN 'RECEBIDA' WHEN p.vencimento < CURDATE() THEN 'VENCIDA' ELSE 'PENDENTE' END AS situacao
            FROM ordem_servico_parcelas p
            JOIN ordens_servico os ON os.id_ordem_servico = p.ordem_servico_id
            JOIN clientes c ON c.id_cliente = os.cliente_id
            JOIN veiculos v ON v.id_veiculo = os.veiculo_id
            WHERE " . implode(' AND ', $where) . '
            ORDER BY ' . ($situacao === 'recebidas' ? 'p.data_pagamento DESC' : 'p.vencimento ASC') . ', p.id_parcela LIMIT ' . (int) $limite
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Totais de contas a receber (snapshot de hoje, sem período): o que ainda falta entrar.
     *
     * @return array{a_receber:float, vencido:float, a_vencer:float, proximos_7_dias:float, parcelas_pendentes:int, parcelas_vencidas:int, clientes_devedores:int}
     */
    public function totais(): array
    {
        $r = $this->conn->query("
            SELECT COALESCE(SUM(p.valor), 0) AS pendente,
                   COALESCE(SUM(CASE WHEN p.vencimento < CURDATE() THEN p.valor END), 0) AS vencido,
                   COALESCE(SUM(CASE WHEN p.vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN p.valor END), 0) AS proximos,
                   COUNT(*) AS qtd, COALESCE(SUM(p.vencimento < CURDATE()), 0) AS qtd_vencidas, COUNT(DISTINCT os.cliente_id) AS clientes
            FROM ordem_servico_parcelas p
            JOIN ordens_servico os ON os.id_ordem_servico = p.ordem_servico_id
            WHERE p.pago = 0 AND os.status IN ('AGUARDANDO_RETIRADA','CONCLUIDA')
        ")->fetch(PDO::FETCH_ASSOC);

        return [
            'a_receber'          => round((float) $r['pendente'], 2),
            'vencido'            => round((float) $r['vencido'], 2),
            'a_vencer'           => round((float) $r['pendente'] - (float) $r['vencido'], 2),
            'proximos_7_dias'    => round((float) $r['proximos'], 2),
            'parcelas_pendentes' => (int) $r['qtd'],
            'parcelas_vencidas'  => (int) $r['qtd_vencidas'],
            'clientes_devedores' => (int) $r['clientes'],
        ];
    }

    /** Situação de uma parcela já carregada (a mesma regra do SQL, para as telas da OS). */
    public static function situacao(array $parcela, ?string $hoje = null): string
    {
        if ((int) $parcela['pago'] === 1) {
            return 'RECEBIDA';
        }

        return !empty($parcela['vencimento']) && $parcela['vencimento'] < ($hoje ?? date('Y-m-d')) ? 'VENCIDA' : 'PENDENTE';
    }
}
