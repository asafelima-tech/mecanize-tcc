<?php

class Documento
{
    private $conn;

    public function __construct()
    {
        $this->conn = new Database();
    }

    public function getResumo()
    {
        $ordens = $this->listar();

        $totalValor = array_sum(array_column($ordens, 'valor_total_os'));
        $total      = count($ordens);

        return [
            'ordens'          => $ordens,
            'totalDocumentos' => $total,
            'totalValor'      => $totalValor,
            'ticketMedio'     => $total > 0 ? $totalValor / $total : 0,
            'paginaAtual'     => 1
        ];
    }

    public function listar()
    {
        $stmt = $this->conn->prepare("
    SELECT
        os.id_ordem_servico         AS id,
        os.numero,
        os.data_abertura,
        os.data_conclusao,
        os.valor_total_os,
        os.defeito_relatado,
        os.diagnostico,

        c.nome                      AS cliente,

        CONCAT(
            mv.nome, ' ',
            mo.nome, ' ',
            COALESCE(v.ano, '')
        )                           AS veiculo,

        v.placa,

        u.nome                      AS mecanico

    FROM ordens_servico os

    INNER JOIN clientes c
        ON c.id_cliente = os.cliente_id

    INNER JOIN veiculos v
        ON v.id_veiculo = os.veiculo_id

    INNER JOIN marcas_veiculos mv
        ON mv.id_marca = v.marca_id

    INNER JOIN modelos_veiculos mo
        ON mo.id_modelo = v.modelo_id

    LEFT JOIN ordem_servico_mao_obra mob
        ON mob.ordem_servico_id = os.id_ordem_servico
        AND mob.id_OS_mao = (
            SELECT MIN(id_OS_mao)
            FROM ordem_servico_mao_obra
            WHERE ordem_servico_id = os.id_ordem_servico
            AND tecnico_id IS NOT NULL
        )

    LEFT JOIN usuarios u
        ON u.id_usuario = mob.tecnico_id

    WHERE os.status = 'CONCLUIDA'

    ORDER BY os.data_conclusao DESC

    LIMIT 20
");

        $stmt->execute();

        $ordens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($ordens as &$os) {
            $os['itens'] = $this->buscarItens((int) $os['id']);
        }

        return $ordens;
    }

    /**
     * Retorna uma única OS (com seus itens) para a tela de geração de documento.
     * Mesma estrutura de dados que listar(), porém filtrada por id.
     */
    public function getOSParaOrcamento($osId)
    {
        $stmt = $this->conn->prepare("
            SELECT
                os.id_ordem_servico AS id,
                os.numero,
                os.data_abertura,
                os.data_conclusao,
                os.valor_total_os,
                os.defeito_relatado,
                os.diagnostico,
                c.nome AS cliente,
                CONCAT(mv.nome, ' ', mo.nome, ' ', COALESCE(v.ano, '')) AS veiculo,
                v.placa,
                u.nome AS mecanico
            FROM ordens_servico os
            INNER JOIN clientes c ON c.id_cliente = os.cliente_id
            INNER JOIN veiculos v ON v.id_veiculo = os.veiculo_id
            INNER JOIN marcas_veiculos mv ON mv.id_marca = v.marca_id
            INNER JOIN modelos_veiculos mo ON mo.id_modelo = v.modelo_id
            LEFT JOIN ordem_servico_mao_obra mob
                ON mob.ordem_servico_id = os.id_ordem_servico
                AND mob.id_OS_mao = (
                    SELECT MIN(id_OS_mao)
                    FROM ordem_servico_mao_obra
                    WHERE ordem_servico_id = os.id_ordem_servico
                    AND tecnico_id IS NOT NULL
                )
            LEFT JOIN usuarios u ON u.id_usuario = mob.tecnico_id
            WHERE os.id_ordem_servico = ?
            LIMIT 1
        ");
        $stmt->execute([$osId]);
        $os = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$os) {
            return null;
        }

        $os['itens'] = $this->buscarItens((int) $os['id']);

        return $os;
    }

    private function buscarItens($osId)
    {
        $stmtProd = $this->conn->prepare("
        SELECT
            p.nome,
            'produto'              AS tipo,
            CAST(oi.quantidade AS UNSIGNED) AS qty,
            oi.valor_unitario      AS preco
        FROM ordem_servico_produtos oi
        INNER JOIN produtos p ON p.id_produto = oi.produto_id
        WHERE oi.ordem_servico_id = ?
    ");
        $stmtProd->execute([$osId]);
        $produtos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

        $stmtMao = $this->conn->prepare("
        SELECT
            descricao         AS nome,
            'servico'         AS tipo,
            1                 AS qty,
            valor_mao_obra    AS preco
        FROM ordem_servico_mao_obra
        WHERE ordem_servico_id = ?
    ");
        $stmtMao->execute([$osId]);
        $maoObra = $stmtMao->fetchAll(PDO::FETCH_ASSOC);

        return array_merge($produtos, $maoObra);
    }

    public function finalizar($osId)
    {
        $stmt = $this->conn->prepare("
            SELECT status
            FROM ordens_servico
            WHERE id_ordem_servico = ?
        ");
        $stmt->execute([$osId]);
        $os = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$os || $os['status'] !== 'CONCLUIDA') {
            return false;
        }

        // Se o seu projeto tiver uma tabela de documentos gerados,
        // registre aqui. Exemplo:
        //
        // $stmtInsert = $this->conn->prepare("
        //     INSERT INTO documentos (ordem_servico_id, gerado_em)
        //     VALUES (?, NOW())
        // ");
        // return $stmtInsert->execute([$osId]);

        return true;
    }
}
