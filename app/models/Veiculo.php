<?php

class Veiculo
{
    private $conn;
    private $lastError = '';

    public function __construct()
    {
        $this->conn = new Database();
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Verifica se já existe veículo com a mesma placa (comparando a forma
     * normalizada, sem traço/maiúsculas). $exceto = id a ignorar (na edição).
     */
    public function placaExiste(string $placaNormalizada, $exceto = null): bool
    {
        $sql    = "SELECT COUNT(*) FROM veiculos WHERE placa_normalizada = ?";
        $params = [$placaNormalizada];

        if ($exceto !== null) {
            $sql      .= " AND id_veiculo <> ?";
            $params[]  = $exceto;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function getResumo($filtros = [])
    {
        $totalResultados = $this->contarVeiculosFiltrados($filtros);

        return [
            'totalVeiculos'    => $this->totalVeiculos(),
            'novosMes'         => $this->novosMes(),
            'marcasfrequentes' => $this->marcaMaisFrequente(),
            'marcas_veiculos'  => $this->listarMarcas(),
            'modelos_veiculos' => $this->listarModelos(),
            'veiculos'         => $this->listarVeiculos($filtros),
            'totalResultados'  => $totalResultados,
            'paginaAtual'      => max(1, (int) ($filtros['pagina'] ?? 1)),
            'totalPaginas'     => max(1, (int) ceil($totalResultados / 10)),
        ];
    }

    /**
     * Monta a cláusula WHERE + parâmetros a partir dos filtros de busca —
     * usada tanto por listarVeiculos() quanto por contarVeiculosFiltrados(),
     * pra manter a contagem (e, por tabela, a paginação) sempre consistente
     * com a listagem de fato.
     */
    private function buildFiltroVeiculos(array $filtros): array
    {
        $where  = '';
        $params = [];

        if (!empty($filtros['busca'])) {
            $where .= " AND (
                v.placa                LIKE ?
                OR v.placa_normalizada LIKE ?
                OR c.nome              LIKE ?
                OR ma.nome             LIKE ?
                OR mo.nome             LIKE ?
            )";

            $busca = '%' . $filtros['busca'] . '%';
            $params[] = $busca;
            $params[] = $busca;
            $params[] = $busca;
            $params[] = $busca;
            $params[] = $busca;
        }

        if (!empty($filtros['marca'])) {
            $where   .= ' AND v.marca_id = ?';
            $params[] = $filtros['marca'];
        }

        if (!empty($filtros['modelo'])) {
            $where   .= ' AND v.modelo_id = ?';
            $params[] = $filtros['modelo'];
        }

        if (!empty($filtros['cliente'])) {
            $where   .= ' AND v.cliente_id = ?';
            $params[] = $filtros['cliente'];
        }

        return [$where, $params];
    }

    /** Conta quantos veículos batem com os filtros — base do total de páginas. */
    public function contarVeiculosFiltrados(array $filtros = []): int
    {
        [$where, $params] = $this->buildFiltroVeiculos($filtros);

        $sql = "
            SELECT COUNT(*)
            FROM veiculos v
            INNER JOIN clientes c          ON c.id_cliente  = v.cliente_id
            INNER JOIN marcas_veiculos ma  ON ma.id_marca   = v.marca_id
            INNER JOIN modelos_veiculos mo ON mo.id_modelo  = v.modelo_id
            WHERE 1 = 1 $where
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** Lista veículos cadastrados neste mês, para o painel de "Novos este Mês" */
    public function listarNovosEsteMes(): array
    {
        $sql = "
            SELECT
                v.id_veiculo,
                v.placa,
                v.ano,
                v.cor,
                v.combustivel,
                v.criado_em,
                c.nome  AS cliente_nome,
                ma.nome AS marca_nome,
                mo.nome AS modelo_nome
            FROM veiculos v
            INNER JOIN clientes c          ON c.id_cliente  = v.cliente_id
            INNER JOIN marcas_veiculos ma  ON ma.id_marca   = v.marca_id
            INNER JOIN modelos_veiculos mo ON mo.id_modelo  = v.modelo_id
            WHERE MONTH(v.criado_em) = MONTH(CURDATE())
              AND YEAR(v.criado_em)  = YEAR(CURDATE())
            ORDER BY v.criado_em DESC
        ";

        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function novosMes()
    {
        $stmt = $this->conn->prepare("
        SELECT COUNT(*) FROM veiculos
        WHERE MONTH(criado_em) = MONTH(CURDATE())
          AND YEAR(criado_em)  = YEAR(CURDATE())
    ");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    private function marcaMaisFrequente()
    {
        $stmt = $this->conn->prepare("
        SELECT ma.nome
        FROM veiculos v
        INNER JOIN marcas_veiculos ma ON ma.id_marca = v.marca_id
        GROUP BY v.marca_id
        ORDER BY COUNT(*) DESC
        LIMIT 1
    ");
        $stmt->execute();
        return $stmt->fetchColumn() ?: '—';
    }

    private function totalVeiculos()
    {
        $sql = "SELECT COUNT(*) FROM veiculos";
        return (int) $this->conn->query($sql)->fetchColumn();
    }

    public function listarMarcas()
    {
        $sql = "SELECT id_marca, nome FROM marcas_veiculos ORDER BY nome ASC";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarModelos()
    {
        $sql = "SELECT id_modelo, nome, marca_id FROM modelos_veiculos ORDER BY nome ASC";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarVeiculos($filtros = [])
    {
        [$where, $params] = $this->buildFiltroVeiculos($filtros);

        $pagina    = max(1, (int) ($filtros['pagina'] ?? 1));
        $porPagina = 10;
        $offset    = ($pagina - 1) * $porPagina;

        $sql = "
            SELECT
                v.id_veiculo,
                v.placa,
                v.placa_normalizada,
                v.ano,
                v.cor,
                v.combustivel,
                v.observacoes,
                c.nome  AS cliente_nome,
                ma.nome AS marca_nome,
                mo.nome AS modelo_nome
            FROM veiculos v
            INNER JOIN clientes c
                ON c.id_cliente = v.cliente_id
            INNER JOIN marcas_veiculos ma
                ON ma.id_marca = v.marca_id
            INNER JOIN modelos_veiculos mo
                ON mo.id_modelo = v.modelo_id
            WHERE 1 = 1 $where
            ORDER BY c.nome ASC, v.placa ASC
            LIMIT ? OFFSET ?
        ";

        $params[] = $porPagina;
        $params[] = $offset;

        $stmt = $this->conn->prepare($sql);

        // LIMIT/OFFSET precisam ser bindados como inteiro — com execute($array)
        // o PDO manda tudo como string e o MySQL rejeita em modo estrito.
        foreach ($params as $i => $valor) {
            $tipo = (($i === count($params) - 1) || ($i === count($params) - 2)) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($i + 1, $valor, $tipo);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $sql = "
            SELECT
                v.id_veiculo,
                v.placa,
                v.placa_normalizada,
                v.ano,
                v.cor,
                v.combustivel,
                v.observacoes,
                v.criado_em,
                v.cliente_id,
                v.marca_id,
                v.modelo_id,
                c.nome  AS cliente_nome,
                ma.nome AS marca_nome,
                mo.nome AS modelo_nome
            FROM veiculos v
            INNER JOIN clientes c          ON c.id_cliente  = v.cliente_id
            INNER JOIN marcas_veiculos ma  ON ma.id_marca   = v.marca_id
            INNER JOIN modelos_veiculos mo ON mo.id_modelo  = v.modelo_id
            WHERE v.id_veiculo = ?
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data)
    {
        $this->lastError = '';

        $placaNormalizada = $this->normalizarPlaca($data['placa'] ?? '');
        if ($placaNormalizada !== '' && $this->placaExiste($placaNormalizada)) {
            $this->lastError = 'Já existe um veículo cadastrado com esta placa.';
            return false;
        }

        $sql = "INSERT INTO veiculos (
                cliente_id,
                marca_id,
                modelo_id,
                placa,
                placa_normalizada,
                ano,
                cor,
                combustivel,
                observacoes
            ) VALUES (
                :cliente_id,
                :marca_id,
                :modelo_id,
                :placa,
                :placa_normalizada,
                :ano,
                :cor,
                :combustivel,
                :observacoes
            )";

        $stmt = $this->conn->prepare($sql);

        try {
            return $stmt->execute([
                ':cliente_id'        => $data['cliente_id'],
                ':marca_id'          => $data['marca_id'],
                ':modelo_id'         => $data['modelo_id'],
                ':placa'             => strtoupper(trim($data['placa'])),
                ':placa_normalizada' => $this->normalizarPlaca($data['placa']),
                ':ano'               => $data['ano'] ?: null,
                ':cor'               => $data['cor'] ?: null,
                ':combustivel'       => $data['combustivel'] ?: null,
                ':observacoes'       => $data['observacoes'] ?: null,
            ]);
        } catch (\PDOException $e) {
            // Backstop do índice único uq_veiculos_placa_normalizada — ver
            // comentário no update() acima.
            if ($e->getCode() === '23000') {
                $this->lastError = 'Já existe um veículo cadastrado com esta placa.';
                return false;
            }
            throw $e;
        }
    }

    public function update($id, $data)
    {
        $this->lastError = '';

        $placaNormalizada = $this->normalizarPlaca($data['placa'] ?? '');
        if ($placaNormalizada !== '' && $this->placaExiste($placaNormalizada, $id)) {
            $this->lastError = 'Já existe outro veículo cadastrado com esta placa.';
            return false;
        }

        $sql = "UPDATE veiculos SET
                cliente_id        = :cliente_id,
                marca_id          = :marca_id,
                modelo_id         = :modelo_id,
                placa             = :placa,
                placa_normalizada = :placa_normalizada,
                ano               = :ano,
                cor               = :cor,
                combustivel       = :combustivel,
                observacoes       = :observacoes
            WHERE id_veiculo = :id_veiculo";

        $stmt = $this->conn->prepare($sql);

        try {
            return $stmt->execute([
                ':cliente_id'        => $data['cliente_id'],
                ':marca_id'          => $data['marca_id'],
                ':modelo_id'         => $data['modelo_id'],
                ':placa'             => strtoupper(trim($data['placa'])),
                ':placa_normalizada' => $this->normalizarPlaca($data['placa']),
                ':ano'               => $data['ano'] ?: null,
                ':cor'               => $data['cor'] ?: null,
                ':combustivel'       => $data['combustivel'] ?: null,
                ':observacoes'       => $data['observacoes'] ?: null,
                ':id_veiculo'        => $id,
            ]);
        } catch (\PDOException $e) {
            // Backstop do índice único uq_veiculos_placa_normalizada pegando
            // uma corrida que placaExiste() não pegou — vira a mesma
            // mensagem amigável em vez de erro 500 genérico.
            if ($e->getCode() === '23000') {
                $this->lastError = 'Já existe um veículo cadastrado com esta placa.';
                return false;
            }
            throw $e;
        }
    }

    /**
     * Exclusão em cascata: remove o veículo E todos os orçamentos/OS vinculados
     * (e seus itens). Operação destrutiva e irreversível — feita em transação.
     *
     * Ordem (filhos -> pais), respeitando as FKs:
     *   itens de OS -> OS -> itens de orçamento -> orçamentos -> veículo
     */
    public function delete($id)
    {
        try {
            $this->conn->beginTransaction();

            // Orçamentos do veículo
            $stmt = $this->conn->prepare("SELECT id_orcamento FROM orcamentos WHERE veiculo_id = ?");
            $stmt->execute([$id]);
            $orcamentos = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // OS vinculadas: direto ao veículo OU geradas por um desses orçamentos
            $osSql    = "SELECT id_ordem_servico FROM ordens_servico WHERE veiculo_id = ?";
            $osParams = [$id];
            if (!empty($orcamentos)) {
                $in        = implode(',', array_fill(0, count($orcamentos), '?'));
                $osSql    .= " OR orcamento_id IN ($in)";
                $osParams  = array_merge($osParams, $orcamentos);
            }
            $stmt = $this->conn->prepare($osSql);
            $stmt->execute($osParams);
            $ordens = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Itens das OS e as próprias OS (libera o FK ordens_servico.orcamento_id)
            if (!empty($ordens)) {
                $in = implode(',', array_fill(0, count($ordens), '?'));
                $this->conn->prepare("DELETE FROM ordem_servico_produtos WHERE ordem_servico_id IN ($in)")->execute($ordens);
                $this->conn->prepare("DELETE FROM ordem_servico_mao_obra  WHERE ordem_servico_id IN ($in)")->execute($ordens);
                $this->conn->prepare("DELETE FROM ordem_servico_servicos  WHERE ordem_servico_id IN ($in)")->execute($ordens);
                $this->conn->prepare("DELETE FROM ordens_servico          WHERE id_ordem_servico IN ($in)")->execute($ordens);
            }

            // Itens dos orçamentos e os próprios orçamentos (libera o FK orcamentos.veiculo_id)
            if (!empty($orcamentos)) {
                $in = implode(',', array_fill(0, count($orcamentos), '?'));
                $this->conn->prepare("DELETE FROM orcamento_itens WHERE orcamento_id IN ($in)")->execute($orcamentos);
                $this->conn->prepare("DELETE FROM orcamentos      WHERE id_orcamento IN ($in)")->execute($orcamentos);
            }

            // Finalmente, o veículo
            $this->conn->prepare("DELETE FROM veiculos WHERE id_veiculo = ?")->execute([$id]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('[Veiculo delete cascata] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            return false;
        }
    }

    private function normalizarPlaca($placa)
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $placa));
    }

    public function getByCliente($cliente_id)
    {
        $sql = "
        SELECT
            v.id_veiculo,
            v.placa,
            v.ano,
            v.cor,
            ma.nome AS marca_nome,
            mo.nome AS modelo_nome,
            CONCAT(ma.nome, ' ', mo.nome) AS modelo
        FROM veiculos v
        INNER JOIN marcas_veiculos ma  ON ma.id_marca  = v.marca_id
        INNER JOIN modelos_veiculos mo ON mo.id_modelo = v.modelo_id
        WHERE v.cliente_id = ?
        ORDER BY mo.nome ASC
    ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$cliente_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarModelosPorMarca(int $marca_id): array
    {
        $sql  = "SELECT id_modelo, nome, combustiveis FROM modelos_veiculos WHERE marca_id = ? ORDER BY nome ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$marca_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cria um modelo "na hora" para uma marca (usado quando a marca não tem
     * modelos cadastrados — ex.: Abarth). Se já existir um modelo com o mesmo
     * nome na marca, devolve o existente em vez de duplicar.
     *
     * @return array|null  ['id_modelo', 'nome', 'combustiveis'] ou null se inválido.
     */
    public function criarModelo($marca_id, $nome)
    {
        $marca_id = (int) $marca_id;
        $nome     = trim((string) $nome);

        if ($marca_id <= 0 || $nome === '') {
            return null;
        }

        // Evita duplicar (mesma marca + mesmo nome, case-insensitive)
        $stmt = $this->conn->prepare(
            "SELECT id_modelo, nome, combustiveis
             FROM modelos_veiculos
             WHERE marca_id = ? AND LOWER(nome) = LOWER(?)
             LIMIT 1"
        );
        $stmt->execute([$marca_id, $nome]);
        $existente = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existente) {
            return $existente;
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO modelos_veiculos (marca_id, nome) VALUES (?, ?)"
        );
        $stmt->execute([$marca_id, $nome]);

        return [
            'id_modelo'    => (int) $this->conn->lastInsertId(),
            'nome'         => $nome,
            'combustiveis' => null,
        ];
    }
}
