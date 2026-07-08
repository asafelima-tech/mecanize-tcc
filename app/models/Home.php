<?php

class Home
{
    private $conn;

    public function __construct()
    {
        $this->conn = new Database();
    }

    // private function calcularPorcentagem($atual, $anterior)
    // {
    //     if ($anterior == 0) {
    //         return $atual > 0 ? 100 : 0;
    //     }

    //     return round((($atual - $anterior) / $anterior) * 100);
    // }
    public function getResumo()
    {
        $statusEmAndamento = $this->contarPorStatus('EM_ANDAMENTO');
        $statusAguardandoPeca = $this->contarPorStatus('AGUARDANDO_PECA');
        $statusCanceladas = $this->contarPorStatus('CANCELADA');
        $statusConcluidas = $this->contarPorStatus('CONCLUIDA');
        $statusAbertas = $this->contarPorStatus('ABERTA');

        $totalStatus = $statusEmAndamento
            + $statusAguardandoPeca
            + $statusCanceladas
            + $statusConcluidas
            + $statusAbertas;

        return [
            'faturamentoMensal' => $this->faturamentoMensal(),
            'graficoOS'         => $this->graficoOSSemana(),
            'graficoReceita'    => $this->graficoReceitaSemana(),
            'orcamentos'        => $this->ultimosOrcamentos(),

            'statusEmAndamento' => $statusEmAndamento,
            'statusAguardandoPeca' => $statusAguardandoPeca,
            'statusCanceladas' => $statusCanceladas,
            'statusConcluidas' => $statusConcluidas,
            'statusAbertas' => $statusAbertas,

            'percentualEmAndamento' => $this->calcularPercentual($statusEmAndamento, $totalStatus),
            'percentualAguardandoPeca' => $this->calcularPercentual($statusAguardandoPeca, $totalStatus),
            'percentualCanceladas' => $this->calcularPercentual($statusCanceladas, $totalStatus),
            'percentualConcluidas' => $this->calcularPercentual($statusConcluidas, $totalStatus),
            'percentualAbertas' => $this->calcularPercentual($statusAbertas, $totalStatus),

            'graficoStatus' => [
                $statusConcluidas,      // índice 0 → Concluídas
                $statusEmAndamento,     // índice 1 → Em andamento
                $statusAguardandoPeca,  // índice 2 → Pendentes
                $statusCanceladas,      // índice 3 → Canceladas
                $statusAbertas,         // índice 4 → Abertas
            ],
        ];
    }

    private function contarPorStatus($status)
    {
        $sql = "SELECT COUNT(*) FROM ordens_servico WHERE status = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$status]);

        return (int) $stmt->fetchColumn();
    }

    private function contarPorStatusSemanaAnterior($status)
    {
        $sql = "
        SELECT COUNT(*) 
        FROM ordens_servico 
        WHERE status = ?
        AND data_abertura >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
        AND data_abertura < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$status]);

        return (int) $stmt->fetchColumn();
    }

    private function faturamentoMensal()
    {
        $sql = "
        SELECT COALESCE(SUM(valor_total_os), 0)
        FROM ordens_servico
        WHERE status = 'CONCLUIDA'
        AND MONTH(data_conclusao) = MONTH(CURDATE())
        AND YEAR(data_conclusao) = YEAR(CURDATE())
    ";

        return (float) $this->conn->query($sql)->fetchColumn();
    }

    private function faturamentoMesAnterior()
    {
        $sql = "
        SELECT COALESCE(SUM(valor_total_os), 0)
        FROM ordens_servico
        WHERE status = 'CONCLUIDA'
        AND MONTH(data_conclusao) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        AND YEAR(data_conclusao) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    ";

        return (float) $this->conn->query($sql)->fetchColumn();
    }

    private function calcularPorcentagem($atual, $anterior)
    {
        if ($anterior == 0) {
            return $atual > 0 ? 100 : 0;
        }

        return round((($atual - $anterior) / $anterior) * 100);
    }

    private function calcularPercentual($valor, $total)
    {
        if ($total == 0) {
            return 0;
        }

        return round(($valor / $total) * 100);
    }

    private function graficoOSSemana()
    {
        $dados = [];

        for ($i = 6; $i >= 0; $i--) {
            $sql = "
            SELECT COUNT(*)
            FROM ordens_servico
            WHERE DATE(data_abertura) = DATE_SUB(CURDATE(), INTERVAL {$i} DAY)
        ";

            $dados[] = (int) $this->conn->query($sql)->fetchColumn();
        }

        return $dados;
    }

    private function graficoReceitaSemana()
    {
        $dados = [];

        for ($i = 6; $i >= 0; $i--) {
            $sql = "
            SELECT COALESCE(SUM(valor_total_os), 0)
            FROM ordens_servico
            WHERE status = 'CONCLUIDA'
            AND DATE(data_conclusao) = DATE_SUB(CURDATE(), INTERVAL {$i} DAY)
        ";

            $dados[] = (float) $this->conn->query($sql)->fetchColumn();
        }

        return $dados;
    }

    private function ultimosOrcamentos()
    {
        $sql = "
        SELECT 
            os.id_ordem_servico AS id,
            c.nome AS cliente,
            CONCAT(mv.nome, ' ', mo.nome, ' ', COALESCE(v.ano, '')) AS veiculo,
            os.valor_total_os AS valor,
            os.status,
            os.data_abertura AS data
        FROM ordens_servico os
        INNER JOIN clientes c ON c.id_cliente = os.cliente_id
        INNER JOIN veiculos v ON v.id_veiculo = os.veiculo_id
        INNER JOIN marcas_veiculos mv ON mv.id_marca = v.marca_id
        INNER JOIN modelos_veiculos mo ON mo.id_modelo = v.modelo_id
        ORDER BY os.data_abertura DESC
        LIMIT 5
    ";

        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
