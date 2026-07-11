<?php

class Relatorio
{
    private $conn;

    public function __construct()
    {
        $this->conn = new Database();
    }

    public function getResumo()
    {
        $status = $this->statusResumo();

        return [
            'faturamentoMensal'       => $this->faturamentoMensal(),
            'faturamentoPorcentagem'  => $this->faturamentoPorcentagem(),
            'statusConcluidas'        => $status['CONCLUIDA'] ?? 0,
            'ticketMedio'             => $this->ticketMedio(),
            'estoqueBaixo'            => $this->estoqueBaixo(),

            'labelsGrafico'           => ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'],
            'graficoOS'               => $this->graficoOSSemana(),
            'graficoReceita'          => $this->graficoReceitaSemana(),

            'graficoStatus' => [
                'concluidas'      => $status['CONCLUIDA'] ?? 0,
                'em_manutencao'   => $status['EM_ANDAMENTO'] ?? 0,
                'aguardando_peca' => $status['AGUARDANDO_PECA'] ?? 0,
                'diagnostico'     => $status['DIAGNOSTICO'] ?? 0,
                'arquivadas'      => $status['CANCELADA'] ?? 0
            ],

            'clientesAtendidos' => $this->clientesAtendidos(),
            'ordensConcluidas'  => $this->ultimasConcluidas()
        ];
    }

    private function statusResumo()
    {
        $stmt = $this->conn->query("
            SELECT status, COUNT(*) total
            FROM ordens_servico
            GROUP BY status
        ");

        $dados = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dados[$row['status']] = (int)$row['total'];
        }

        return $dados;
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

        return (float)$this->conn->query($sql)->fetchColumn();
    }

    /**
     * Faturamento do mês anterior (mesma base de faturamentoMensal(), um mês
     * antes) — usado só pra calcular a variação percentual real.
     */
    private function faturamentoMesAnterior()
    {
        $sql = "
            SELECT COALESCE(SUM(valor_total_os), 0)
            FROM ordens_servico
            WHERE status = 'CONCLUIDA'
            AND MONTH(data_conclusao) = MONTH(CURDATE() - INTERVAL 1 MONTH)
            AND YEAR(data_conclusao) = YEAR(CURDATE() - INTERVAL 1 MONTH)
        ";

        return (float) $this->conn->query($sql)->fetchColumn();
    }

    /**
     * Variação % do faturamento do mês atual em relação ao mês anterior —
     * antes disso era um valor fixo em 0 (não calculado, embora os dados pra
     * calcular já existissem), mostrado como se fosse real na tela de Relatórios.
     */
    private function faturamentoPorcentagem(): float
    {
        $atual    = $this->faturamentoMensal();
        $anterior = $this->faturamentoMesAnterior();

        if ($anterior <= 0) {
            return $atual > 0 ? 100.0 : 0.0;
        }

        return round((($atual - $anterior) / $anterior) * 100, 1);
    }

    private function ticketMedio()
    {
        $sql = "
            SELECT COALESCE(AVG(valor_total_os), 0)
            FROM ordens_servico
            WHERE status = 'CONCLUIDA'
        ";

        return (float)$this->conn->query($sql)->fetchColumn();
    }

    private function estoqueBaixo()
    {
        $sql = "
            SELECT COUNT(*)
            FROM produtos
            WHERE ativo = 1
            AND estoque_atual <= estoque_minimo
        ";

        return (int)$this->conn->query($sql)->fetchColumn();
    }

    private function graficoOSSemana()
    {
        $dados = [];

        for ($i = 6; $i >= 0; $i--) {
            $sql = "
                SELECT COUNT(*)
                FROM ordens_servico
                WHERE DATE(data_abertura) =
                DATE_SUB(CURDATE(), INTERVAL {$i} DAY)
            ";

            $dados[] = (int)$this->conn->query($sql)->fetchColumn();
        }

        return $dados;
    }

    private function graficoReceitaSemana()
    {
        $dados = [];

        for ($i = 6; $i >= 0; $i--) {
            $sql = "
                SELECT COALESCE(SUM(valor_total_os),0)
                FROM ordens_servico
                WHERE status = 'CONCLUIDA'
                AND DATE(data_conclusao) =
                DATE_SUB(CURDATE(), INTERVAL {$i} DAY)
            ";

            $dados[] = (float)$this->conn->query($sql)->fetchColumn();
        }

        return $dados;
    }

    private function clientesAtendidos()
    {
        $sql = "
        SELECT
            c.nome,
            COUNT(os.id_ordem_servico) AS total_os,
            MAX(
                CONCAT(
                    mv.nome, ' ',
                    mo.nome, ' ',
                    COALESCE(v.ano, '')
                )
            ) AS veiculo
        FROM ordens_servico os
        INNER JOIN clientes c
            ON c.id_cliente = os.cliente_id
        INNER JOIN veiculos v
            ON v.id_veiculo = os.veiculo_id
        INNER JOIN marcas_veiculos mv
            ON mv.id_marca = v.marca_id
        INNER JOIN modelos_veiculos mo
            ON mo.id_modelo = v.modelo_id
        GROUP BY c.id_cliente, c.nome
        ORDER BY total_os DESC
        LIMIT 5
    ";

        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function ultimasConcluidas()
    {
        $sql = "
            SELECT
                os.id_ordem_servico,
                os.numero,
                os.data_conclusao,
                os.valor_total_os,
                c.nome AS cliente,
                CONCAT(
                    mv.nome, ' ',
                    mo.nome, ' ',
                    COALESCE(v.ano, '')
                ) AS veiculo
            FROM ordens_servico os
            INNER JOIN clientes c
                ON c.id_cliente = os.cliente_id
            INNER JOIN veiculos v
                ON v.id_veiculo = os.veiculo_id
            INNER JOIN marcas_veiculos mv
                ON mv.id_marca = v.marca_id
            INNER JOIN modelos_veiculos mo
                ON mo.id_modelo = v.modelo_id
            WHERE os.status = 'CONCLUIDA'
            ORDER BY os.data_conclusao DESC
            LIMIT 8
        ";

        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
