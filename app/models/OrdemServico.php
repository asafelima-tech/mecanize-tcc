<?php

class OrdemServico
{
    public $conn;

    public const STATUS_KANBAN = ['ABERTA', 'EM_ANDAMENTO', 'AGUARDANDO_PECA', 'CONCLUIDA'];

    public function __construct()
    {
        $this->conn = new Database();
    }

    /**
     * OS concluídas nas últimas N horas (usado no painel modo TV — não há
     * flag de "retirado", então limitamos por tempo pra não acumular pra
     * sempre no quadro de "pronto pra retirada").
     */
    public function listarConcluidasRecentes(int $horas = 48): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                os.id_ordem_servico, os.numero, os.data_conclusao,
                c.nome AS cliente, v.placa
            FROM ordens_servico os
            INNER JOIN clientes c ON c.id_cliente = os.cliente_id
            INNER JOIN veiculos v ON v.id_veiculo  = os.veiculo_id
            WHERE os.status = 'CONCLUIDA'
              AND os.data_conclusao >= (NOW() - INTERVAL :horas HOUR)
            ORDER BY os.data_conclusao DESC
        ");
        $stmt->execute([':horas' => $horas]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna o token de rastreio público da OS, gerando um na hora se ainda
     * não existir (cobre OS's criadas antes desta feature).
     */
    public function getOuCriarToken(int $id): string
    {
        $stmt = $this->conn->prepare("SELECT token_rastreio FROM ordens_servico WHERE id_ordem_servico = ?");
        $stmt->execute([$id]);
        $token = $stmt->fetchColumn();

        if ($token) {
            return $token;
        }

        $token = bin2hex(random_bytes(24));
        $this->conn->prepare("UPDATE ordens_servico SET token_rastreio = ? WHERE id_ordem_servico = ?")
            ->execute([$token, $id]);

        return $token;
    }

    /**
     * Dados mínimos e públicos de uma OS a partir do token de rastreio — sem
     * valores financeiros nem dados do cliente além do primeiro nome, pra
     * ficar seguro mesmo se o link for encaminhado adiante.
     */
    public function getPorTokenRastreio(string $token): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT
                os.numero          AS numero_os,
                os.status,
                os.data_abertura,
                os.data_conclusao,
                v.placa,
                CONCAT(mv.nome, ' ', mo.nome) AS modelo,
                c.nome             AS nome_cliente
            FROM ordens_servico os
            INNER JOIN veiculos          v  ON v.id_veiculo = os.veiculo_id
            INNER JOIN marcas_veiculos   mv ON mv.id_marca  = v.marca_id
            INNER JOIN modelos_veiculos  mo ON mo.id_modelo = v.modelo_id
            INNER JOIN clientes          c  ON c.id_cliente = os.cliente_id
            WHERE os.token_rastreio = ?
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        return $linha ?: null;
    }

    /** Histórico de OS de um veículo (usado na linha do tempo do veículo). */
    public function listarPorVeiculo(int $veiculoId): array
    {
        $stmt = $this->conn->prepare("
            SELECT id_ordem_servico, numero, status, data_abertura, data_conclusao, valor_total_os
            FROM ordens_servico
            WHERE veiculo_id = ?
            ORDER BY data_abertura DESC
        ");
        $stmt->execute([$veiculoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarEmAndamento($filtros = [])
    {
        return $this->listarPorStatus(['EM_ANDAMENTO'], $filtros);
    }

    public function listarAguardandoPeca($filtros = [])
    {
        return $this->listarPorStatus(['AGUARDANDO_PECA'], $filtros);
    }

    /**
     * Move a OS para outro status (usado pelo Kanban, arrastando o card
     * entre colunas). Só mexe nas colunas status/data_conclusao — ao
     * contrário de update(), não reprocessa itens/mão de obra/estoque,
     * então é seguro chamar sem reenviar o formulário inteiro.
     */
    public function atualizarStatus(int $id, string $status): bool
    {
        if (!in_array($status, self::STATUS_KANBAN, true)) {
            return false;
        }

        $stmt = $this->conn->prepare("
            UPDATE ordens_servico
            SET status = :status,
                data_conclusao = CASE
                    WHEN :status2 = 'CONCLUIDA' THEN COALESCE(data_conclusao, NOW())
                    ELSE NULL
                END
            WHERE id_ordem_servico = :id
        ");

        return $stmt->execute([
            ':status'  => $status,
            ':status2' => $status,
            ':id'      => $id,
        ]);
    }

    public function getClientes()
    {
        // "Cliente Balcão" (cliente genérico de balcão) fica sempre no topo da lista
        $sql = "SELECT id_cliente, nome
                FROM clientes
                WHERE ativo = 1
                ORDER BY (nome = 'Cliente Balcão') DESC, nome ASC";

        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVeiculos()
    {
        $stmt = $this->conn->prepare("
        SELECT
            v.id_veiculo AS id,
            v.cliente_id,
            v.placa,
            CONCAT(mv.nome, ' ', mo.nome) AS modelo,
            c.nome AS cliente
        FROM veiculos v
        INNER JOIN marcas_veiculos mv ON mv.id_marca = v.marca_id
        INNER JOIN modelos_veiculos mo ON mo.id_modelo = v.modelo_id
        INNER JOIN clientes c ON c.id_cliente = v.cliente_id
        ORDER BY v.placa
        ");

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMecanicos()
    {
        $sql = "SELECT
                    id_usuario AS id,
                    nome,
                    0 AS valor_hora
                FROM usuarios
                WHERE tipo  = 'TECNICO'
                  AND ativo = 1
                ORDER BY nome ASC";

        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProdutos()
    {
        $sql = "SELECT
                p.id_produto AS id,
                p.nome,
                COALESCE(c.nome, 'Produto') AS categoria,
                p.preco_venda AS preco
            FROM produtos p
            LEFT JOIN categorias_produtos c ON c.id_categoria = p.categorias_produtos_id
            WHERE p.ativo = 1
            ORDER BY p.nome ASC";

        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getServicos()
    {
        return [];
    }

    public function gerarNumeroOS()
    {
        $sql    = "SELECT MAX(id_ordem_servico) AS max_id FROM ordens_servico";
        $stmt   = $this->conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $proximoId = (int)($result['max_id'] ?? 0) + 1;

        return 'OS-' . str_pad($proximoId, 4, '0', STR_PAD_LEFT);
    }

    public function getProximoNumero()
    {
        return $this->gerarNumeroOS();
    }

    public function getResumo($filtros = [])
    {
        return [
            'statusAbertas'        => $this->contarPorStatus('ABERTA'),
            'statusEmAndamento'    => $this->contarPorStatus('EM_ANDAMENTO'),
            'statusAguardandoPeca' => $this->contarPorStatus('AGUARDANDO_PECA'),
            'statusConcluidas'     => $this->contarPorStatus('CONCLUIDA'),
            'ordens'               => $this->listarAtivas($filtros),
            'ordensAbertas'        => $this->listarAbertas($filtros),
            'ordensConcluidas'     => $this->listarConcluidas($filtros),
            'ordensArquivadas'     => $this->listarArquivadas($filtros),
            'paginaAtual'          => 1,
        ];
    }

    public function listarAbertas($filtros = [])
    {
        return $this->listarPorStatus(['ABERTA'], $filtros);
    }

    public function listarAtivas($filtros = [])
    {
        return $this->listarPorStatus(['EM_ANDAMENTO', 'AGUARDANDO_PECA'], $filtros);
    }

    public function listarConcluidas($filtros = [])
    {
        return $this->listarPorStatus(['CONCLUIDA'], $filtros);
    }

    public function listarArquivadas($filtros = [])
    {
        return $this->listarPorStatus(['CANCELADA'], $filtros);
    }

    private function listarPorStatus(array $statusList, $filtros = [])
    {
        $placeholders = implode(',', array_fill(0, count($statusList), '?'));

        $sql = "
        SELECT
            os.id_ordem_servico,
            os.numero,
            os.status,
            os.data_abertura,
            os.data_conclusao,
            os.valor_total_os,
            os.valor_total_pecas,
            os.valor_total_servicos,
            os.defeito_relatado,
            os.diagnostico,
            os.km_veiculo,
            c.nome  AS cliente,
            v.placa,
            CONCAT(
                mv.nome,
                CASE WHEN mo.nome IS NOT NULL AND mo.nome <> ''
                     THEN CONCAT(' ', mo.nome) ELSE '' END,
                CASE WHEN v.ano IS NOT NULL
                     THEN CONCAT(' ', v.ano) ELSE '' END
            ) AS veiculo,
            u.nome  AS mecanico,
            u.id_usuario AS mecanico_id,
            mob.valor_mao_obra,
            mob.descricao AS mao_obra_descricao
        FROM ordens_servico os
        INNER JOIN clientes         c   ON c.id_cliente  = os.cliente_id
        INNER JOIN veiculos         v   ON v.id_veiculo  = os.veiculo_id
        INNER JOIN marcas_veiculos  mv  ON mv.id_marca   = v.marca_id
        INNER JOIN modelos_veiculos mo  ON mo.id_modelo  = v.modelo_id
        LEFT  JOIN ordem_servico_mao_obra mob
            ON mob.ordem_servico_id = os.id_ordem_servico
            AND mob.id_OS_mao = (
                SELECT MIN(id_OS_mao)
                FROM ordem_servico_mao_obra
                WHERE ordem_servico_id = os.id_ordem_servico
                AND tecnico_id IS NOT NULL
            )
        LEFT  JOIN usuarios         u   ON u.id_usuario  = mob.tecnico_id
        WHERE os.status IN ($placeholders)
        ";

        $params = $statusList;

        if (!empty($filtros['busca'])) {
            $sql .= " AND (os.numero LIKE ? OR c.nome LIKE ? OR v.placa LIKE ?)";
            $busca    = '%' . trim($filtros['busca']) . '%';
            $params[] = $busca;
            $params[] = $busca;
            $params[] = $busca;
        }

        $sql .= " ORDER BY os.data_abertura DESC LIMIT 50";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $stmt = $this->conn->prepare("
        SELECT
            os.id_ordem_servico,
            os.numero          AS numero_os,
            os.status,
            os.data_abertura,
            os.data_conclusao,
            os.km_veiculo,
            os.defeito_relatado,
            os.diagnostico,
            os.observacoes,
            os.assinatura_cliente,
            os.valor_total_pecas,
            os.valor_total_servicos,
            os.valor_total_os,
            os.cliente_id,
            os.veiculo_id,
            c.nome             AS nome_cliente,
            c.tipo_pessoa      AS tipo_pessoa_cliente,
            c.cpf              AS cpf_cliente,
            c.cnpj             AS cnpj_cliente,
            c.telefone         AS telefone_cliente,
            c.endereco         AS endereco_cliente,
            c.bairro           AS bairro_cliente,
            c.cidade           AS cidade_cliente,
            c.uf               AS uf_cliente,
            c.cep              AS cep_cliente,
            v.placa,
            v.ano              AS ano_veiculo,
            v.cor              AS cor_veiculo,
            mv.nome            AS marca_veiculo,
            mo.nome            AS modelo_veiculo,
            CONCAT(
                mv.nome,
                CASE WHEN mo.nome IS NOT NULL AND mo.nome <> ''
                     THEN CONCAT(' ', mo.nome) ELSE '' END,
                CASE WHEN v.ano IS NOT NULL
                     THEN CONCAT(' ', v.ano) ELSE '' END
            )                  AS modelo,
            u.nome             AS nome_mecanico,
            mob.tecnico_id     AS mecanico_id,
            mob.valor_mao_obra,
            mob.descricao      AS mao_obra_descricao
        FROM ordens_servico os
        INNER JOIN clientes          c   ON c.id_cliente  = os.cliente_id
        INNER JOIN veiculos          v   ON v.id_veiculo  = os.veiculo_id
        INNER JOIN marcas_veiculos   mv  ON mv.id_marca   = v.marca_id
        INNER JOIN modelos_veiculos  mo  ON mo.id_modelo  = v.modelo_id
        LEFT  JOIN ordem_servico_mao_obra mob
            ON mob.ordem_servico_id = os.id_ordem_servico
            AND mob.id_OS_mao = (
                SELECT MIN(id_OS_mao)
                FROM ordem_servico_mao_obra
                WHERE ordem_servico_id = os.id_ordem_servico
                AND tecnico_id IS NOT NULL
            )
        LEFT  JOIN usuarios          u   ON u.id_usuario  = mob.tecnico_id
        WHERE os.id_ordem_servico = ?
        LIMIT 1
    ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['mao_obra_descricao'])) {
            preg_match('/\((\d+(?:\.\d+)?)%\)/', $row['mao_obra_descricao'], $matches);
            $row['comissao_pct'] = $matches[1] ?? 0;
        } else {
            $row['comissao_pct'] = 0;
        }

        return $row;
    }

    private function contarPorStatus($status)
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM ordens_servico WHERE status = ?"
        );
        $stmt->execute([$status]);
        return (int) $stmt->fetchColumn();
    }

    public function listar($filtros = [])
    {
        $sql = "
            SELECT
                os.id_ordem_servico,
                os.numero,
                os.status,
                os.data_abertura,
                os.data_conclusao,
                os.valor_total_os,
                c.nome  AS cliente,
                v.placa,
                CONCAT(
                    mv.nome,
                    CASE WHEN mo.nome IS NOT NULL AND mo.nome <> ''
                         THEN CONCAT(' ', mo.nome) ELSE '' END,
                    CASE WHEN v.ano IS NOT NULL
                         THEN CONCAT(' ', v.ano) ELSE '' END
                ) AS veiculo
            FROM ordens_servico os
            INNER JOIN clientes          c  ON c.id_cliente = os.cliente_id
            INNER JOIN veiculos          v  ON v.id_veiculo = os.veiculo_id
            INNER JOIN marcas_veiculos   mv ON mv.id_marca  = v.marca_id
            INNER JOIN modelos_veiculos  mo ON mo.id_modelo = v.modelo_id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filtros['busca'])) {
            $sql .= " AND (
                os.numero LIKE ? OR c.nome LIKE ?
                OR v.placa LIKE ? OR mv.nome LIKE ? OR mo.nome LIKE ?
            )";
            $busca    = '%' . trim($filtros['busca']) . '%';
            $params[] = $busca;
            $params[] = $busca;
            $params[] = $busca;
            $params[] = $busca;
            $params[] = $busca;
        }

        if (!empty($filtros['status'])) {
            $sql     .= " AND os.status = ?";
            $params[] = $filtros['status'];
        }

        if (!empty($filtros['data'])) {
            $sql     .= " AND DATE(os.data_abertura) = ?";
            $params[] = $filtros['data'];
        }

        $sql .= " ORDER BY os.data_abertura DESC LIMIT 20";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($data)
    {
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                INSERT INTO ordens_servico (
                    numero, orcamento_id, status, data_abertura, data_conclusao,
                    cliente_id, veiculo_id, km_veiculo,
                    defeito_relatado, diagnostico, observacoes,
                    valor_total_pecas, valor_total_servicos, valor_total_os
                ) VALUES (
                    '', :orcamento_id, :status, :data_abertura, :data_conclusao,
                    :cliente_id, :veiculo_id, :km_veiculo,
                    :defeito_relatado, :diagnostico, :observacoes,
                    :valor_total_pecas, :valor_total_servicos, :valor_total_os
                )
            ");

            $stmt->execute([
                ':orcamento_id'         => !empty($data['orcamento_id']) ? (int)$data['orcamento_id'] : null,
                ':status'               => $data['status']          ?? 'ABERTA',
                ':data_abertura'        => !empty($data['data_abertura'])
                    ? str_replace('T', ' ', $data['data_abertura'])
                    : date('Y-m-d H:i:s'),
                ':data_conclusao'       => !empty($data['data_conclusao'])
                    ? str_replace('T', ' ', $data['data_conclusao'])
                    : null,
                ':cliente_id'           => $data['cliente_id']      ?? null,
                ':veiculo_id'           => $data['veiculo_id']      ?? null,
                ':km_veiculo'           => !empty($data['km_veiculo']) ? $data['km_veiculo'] : null,
                ':defeito_relatado'     => $data['defeito_relatado'] ?? null,
                ':diagnostico'          => $data['diagnostico']      ?? null,
                ':observacoes'          => $data['observacoes']      ?? null,
                ':valor_total_pecas'    => $this->money($data['valor_total_pecas']    ?? 0),
                ':valor_total_servicos' => $this->money($data['valor_total_servicos'] ?? 0),
                ':valor_total_os'       => $this->money($data['valor_total_os']       ?? 0),
            ]);

            $id = $this->conn->lastInsertId();

            $this->conn->prepare(
                "UPDATE ordens_servico SET numero = ? WHERE id_ordem_servico = ?"
            )->execute(['OS-' . str_pad($id, 4, '0', STR_PAD_LEFT), $id]);

            if (!empty($data['itens']) && is_array($data['itens'])) {
                foreach ($data['itens'] as $item) {
                    $tipo = $item['tipo'] ?? '';
                    if ($tipo === 'produto') {
                        $this->salvarItemProduto($id, $item);
                    } elseif ($tipo === 'servico') {
                        $this->salvarItemServico($id, $item);
                    }
                }
            }

            if (!empty($data['mecanico_id'])) {
                $valorMaoObra = $this->money($data['valor_mao_obra'] ?? 0);
                $comissaoPct  = $this->money($data['comissao_pct']  ?? 0);

                $this->conn->prepare("
                    INSERT INTO ordem_servico_mao_obra (
                        ordem_servico_id, tecnico_id, descricao, valor_mao_obra
                    ) VALUES (?, ?, ?, ?)
                ")->execute([$id, $data['mecanico_id'], 'Mão de obra (' . $comissaoPct . '%)', $valorMaoObra]);
            }

            $this->conn->commit();

            // Devolve o id (em vez de só true) pra quem criou a OS conseguir
            // referenciá-la depois — é o que permite a agenda vincular o
            // agendamento à OS gerada. Continua sendo um valor "verdadeiro",
            // então os callers que só testam sucesso não mudam.
            return (int) $id;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('[OS create] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            return false;
        }
    }

    public function getItensByOS($id)
    {
        $stmt = $this->conn->prepare("
        SELECT
            p.id_produto        AS id,
            p.nome,
            COALESCE(c.nome, 'Produto') AS categoria,
            'produto'           AS tipo,
            oi.quantidade       AS qty,
            oi.valor_unitario   AS preco,
            0                   AS custom
        FROM ordem_servico_produtos oi
        INNER JOIN produtos p ON p.id_produto = oi.produto_id
        LEFT JOIN categorias_produtos c ON c.id_categoria = p.categorias_produtos_id
        WHERE oi.ordem_servico_id = ?
    ");
        $stmt->execute([$id]);
        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        try {
            $stmt = $this->conn->prepare("
            SELECT
                COALESCE(servico_id, CONCAT('custom_', id)) AS id,
                nome,
                COALESCE(categoria, 'Serviço') AS categoria,
                'servico'       AS tipo,
                quantidade      AS qty,
                valor_unitario  AS preco,
                personalizado   AS custom
            FROM ordem_servico_servicos
            WHERE ordem_servico_id = ?
        ");
            $stmt->execute([$id]);
            $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $servicos = [];
        }

        // Serviços avulsos salvos via mão de obra (tecnico_id NULL = serviço de item, não comissão)
        $stmt = $this->conn->prepare("
        SELECT
            id_OS_mao           AS id,
            descricao           AS nome,
            'Serviço'           AS categoria,
            'servico'           AS tipo,
            1                   AS qty,
            valor_mao_obra      AS preco,
            1                   AS custom
        FROM ordem_servico_mao_obra
        WHERE ordem_servico_id = ?
          AND tecnico_id IS NULL
    ");
        $stmt->execute([$id]);
        $servicosMaoObra = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_merge($produtos, $servicos, $servicosMaoObra);
    }

    public function getMaoObraByOS($id)
    {
        $stmt = $this->conn->prepare("
            SELECT tecnico_id, valor_mao_obra
            FROM ordem_servico_mao_obra
            WHERE ordem_servico_id = ?
            AND tecnico_id IS NOT NULL
            LIMIT 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($data)
    {
        try {
            $this->conn->beginTransaction();

            $id = $data['id_ordem_servico'];

            if (($data['status'] ?? '') === 'CONCLUIDA' && empty($data['data_conclusao'])) {
                $data['data_conclusao'] = date('Y-m-d H:i:s');
            } elseif (($data['status'] ?? '') !== 'CONCLUIDA') {
                $data['data_conclusao'] = null;
            }

            $stmt = $this->conn->prepare("
                UPDATE ordens_servico SET
                    numero               = :numero,
                    status               = :status,
                    data_abertura        = :data_abertura,
                    data_conclusao       = :data_conclusao,
                    cliente_id           = :cliente_id,
                    veiculo_id           = :veiculo_id,
                    km_veiculo           = :km_veiculo,
                    defeito_relatado     = :defeito_relatado,
                    diagnostico          = :diagnostico,
                    observacoes          = :observacoes,
                    valor_total_pecas    = :valor_total_pecas,
                    valor_total_servicos = :valor_total_servicos,
                    valor_total_os       = :valor_total_os
                WHERE id_ordem_servico = :id
            ");

            $stmt->execute([
                ':id'                   => $id,
                ':numero'               => $data['numero'],
                ':status'               => $data['status'],
                ':data_abertura'        => !empty($data['data_abertura'])  ? str_replace('T', ' ', $data['data_abertura'])  : null,
                ':data_conclusao'       => !empty($data['data_conclusao']) ? str_replace('T', ' ', $data['data_conclusao']) : null,
                ':cliente_id'           => $data['cliente_id']      ?? null,
                ':veiculo_id'           => $data['veiculo_id']      ?? null,
                ':km_veiculo'           => !empty($data['km_veiculo']) ? $data['km_veiculo'] : null,
                ':defeito_relatado'     => $data['defeito_relatado'] ?? null,
                ':diagnostico'          => $data['diagnostico']      ?? null,
                ':observacoes'          => $data['observacoes']      ?? null,
                ':valor_total_pecas'    => $this->money($data['valor_total_pecas']    ?? 0),
                ':valor_total_servicos' => $this->money($data['valor_total_servicos'] ?? 0),
                ':valor_total_os'       => $this->money($data['valor_total_os']       ?? 0),
            ]);

            $stmtAntigos = $this->conn->prepare("
    SELECT produto_id, quantidade
    FROM ordem_servico_produtos
    WHERE ordem_servico_id = ?
");
            $stmtAntigos->execute([$id]);
            $produtosAntigos = $stmtAntigos->fetchAll(PDO::FETCH_ASSOC);

            foreach ($produtosAntigos as $pa) {
                $this->conn->prepare("
        UPDATE produtos
        SET estoque_atual = estoque_atual + ?,
            atualizado_em = NOW()
        WHERE id_produto = ?
    ")->execute([$pa['quantidade'], $pa['produto_id']]);
            }

            $this->conn->prepare("DELETE FROM ordem_servico_produtos WHERE ordem_servico_id = ?")->execute([$id]);
            $this->conn->prepare("DELETE FROM ordem_servico_mao_obra  WHERE ordem_servico_id = ?")->execute([$id]);
            $this->conn->prepare("DELETE FROM ordem_servico_servicos  WHERE ordem_servico_id = ?")->execute([$id]);

            if (!empty($data['itens']) && is_array($data['itens'])) {
                foreach ($data['itens'] as $item) {
                    $tipo = $item['tipo'] ?? '';
                    if ($tipo === 'produto') {
                        $this->salvarItemProduto($id, $item);
                    } elseif ($tipo === 'servico') {
                        $this->salvarItemMaoObra($id, $item);
                    }
                }
            }

            if (!empty($data['mecanico_id'])) {
                $valorMaoObra = $this->money($data['valor_mao_obra'] ?? 0);
                $comissaoPct  = $this->money($data['comissao_pct']  ?? 0);

                $this->conn->prepare("
                    INSERT INTO ordem_servico_mao_obra (
                        ordem_servico_id, tecnico_id, descricao, valor_mao_obra
                    ) VALUES (?, ?, ?, ?)
                ")->execute([$id, $data['mecanico_id'], 'Mão de obra (' . $comissaoPct . '%)', $valorMaoObra]);
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('[OS update] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            return false;
        }
    }

    public function concluir($id, ?string $assinatura = null)
    {
        try {
            $stmt = $this->conn->prepare("
                UPDATE ordens_servico
                SET status = 'CONCLUIDA',
                    data_conclusao = :data_conclusao,
                    assinatura_cliente = COALESCE(:assinatura, assinatura_cliente)
                WHERE id_ordem_servico = :id
            ");

            return $stmt->execute([
                ':id'             => $id,
                ':data_conclusao' => date('Y-m-d H:i:s'),
                ':assinatura'     => $assinatura,
            ]);
        } catch (Exception $e) {
            error_log('[OS concluir] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            return false;
        }
    }

    private function salvarItemMaoObra($ordemId, $item)
    {
        $this->conn->prepare("
            INSERT INTO ordem_servico_mao_obra (ordem_servico_id, tecnico_id, descricao, valor_mao_obra)
            VALUES (?, NULL, ?, ?)
        ")->execute([
            $ordemId,
            $item['nome'] ?? 'Serviço',
            $this->money($item['preco'] ?? 0),
        ]);
    }

    private function salvarItemProduto($ordemId, $item)
    {
        $produtoId = $item['id'] ?? null;

        if (empty($produtoId) || str_starts_with((string) $produtoId, 'custom_')) {
            return;
        }

        $qty = !empty($item['qty']) ? (float) $item['qty'] : 1;

        $this->conn->prepare("
        INSERT INTO ordem_servico_produtos (
            ordem_servico_id, produto_id, quantidade, valor_unitario
        ) VALUES (?, ?, ?, ?)
    ")->execute([
            $ordemId,
            (int) $produtoId,
            $qty,
            $this->money($item['preco'] ?? 0),
        ]);

        // Baixa o estoque
        $this->conn->prepare("
        UPDATE produtos
        SET estoque_atual = estoque_atual - ?,
            atualizado_em = NOW()
        WHERE id_produto = ?
    ")->execute([$qty, (int) $produtoId]);
    }

    private function salvarItemServico($ordemId, $item)
    {
        $isCustom  = ($item['custom'] ?? '0') === '1';
        $servicoId = null;

        if (!$isCustom && !str_starts_with((string)($item['id'] ?? ''), 'custom_')) {
            $servicoId = (int) $item['id'] ?: null;
        }

        $percentual = isset($item['pct']) && $item['pct'] !== ''
            ? (float) $item['pct']
            : null;

        $this->conn->prepare("
            INSERT INTO ordem_servico_servicos (
                ordem_servico_id,
                servico_id,
                nome,
                categoria,
                quantidade,
                valor_unitario,
                personalizado,
                percentual_base
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $ordemId,
            $servicoId,
            $item['nome']      ?? 'Serviço',
            $item['categoria'] ?? null,
            !empty($item['qty']) ? (float) $item['qty'] : 1,
            $this->money($item['preco'] ?? 0),
            $isCustom ? 1 : 0,
            $percentual,
        ]);
    }

    /**
     * Registros de OUTROS fluxos que apontam para a OS. Enquanto existirem, a
     * exclusão é barrada: eles têm vida própria e apagá-los junto destruiria
     * informação que não é da OS.
     *
     * `agendamentos` tem foreign key RESTRICT no banco, então sem esta
     * checagem a exclusão estourava uma PDOException não tratada e o usuário
     * recebia uma tela de erro 500 sem entender o que impediu.
     */
    private const VINCULOS = [
        ['tabela' => 'agendamentos', 'coluna' => 'ordem_servico_id', 'rotulo' => 'agendamentos'],
    ];

    /**
     * Itens que SÃO a OS e saem junto com ela.
     *
     * Nenhuma dessas tabelas tem foreign key para ordens_servico, então o
     * banco não removia nada sozinho: excluir uma OS deixava produtos,
     * serviços, mão de obra e anexos para trás, apontando para um id que não
     * existe mais — sem erro e sem aviso.
     */
    private const ITENS = [
        'ordem_servico_produtos',
        'ordem_servico_servicos',
        'ordem_servico_mao_obra',
        'anexos_ordem_servico',
    ];

    /**
     * Descreve o que impede a exclusão da OS, por extenso ("1 agendamentos").
     * Array vazio = pode excluir.
     *
     * Os nomes de tabela/coluna vêm só da constante acima, nunca da requisição.
     */
    public function vinculos($id): array
    {
        $id      = (int) $id;
        $achados = [];

        foreach (self::VINCULOS as $vinculo) {
            $sql = "SELECT COUNT(*) FROM `{$vinculo['tabela']}` WHERE `{$vinculo['coluna']}` = :id";

            try {
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([':id' => $id]);
                $qtd = (int) $stmt->fetchColumn();
            } catch (\PDOException $e) {
                // Tabela ainda não criada nesta instalação (ex.: agendamentos
                // antes da migração) não pode derrubar a checagem.
                continue;
            }

            if ($qtd > 0) {
                $achados[] = $qtd . ' ' . $vinculo['rotulo'];
            }
        }

        return $achados;
    }

    /**
     * Exclui a OS e os itens que pertencem a ela, tudo numa transação: ou some
     * o conjunto inteiro, ou não some nada. Sem isso, uma falha no meio deixava
     * a OS apagada e os itens vivos (ou o contrário).
     *
     * Retorna false — em vez de estourar PDOException, que virava tela 500 —
     * quando algo impede a exclusão.
     */
    public function delete($id)
    {
        $id = (int) $id;

        try {
            $this->conn->beginTransaction();

            foreach (self::ITENS as $tabela) {
                $this->conn->prepare("DELETE FROM `{$tabela}` WHERE ordem_servico_id = ?")->execute([$id]);
            }

            $stmt = $this->conn->prepare("DELETE FROM ordens_servico WHERE id_ordem_servico = ?");
            $stmt->execute([$id]);
            $removidas = $stmt->rowCount();

            $this->conn->commit();

            return $removidas > 0;
        } catch (\PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log('[OrdemServico delete] ' . $e->getMessage());

            return false;
        }
    }

    private function money($valor)
    {
        if ($valor === null || $valor === '') {
            return 0;
        }
        return (float) str_replace(',', '.', $valor);
    }
}
