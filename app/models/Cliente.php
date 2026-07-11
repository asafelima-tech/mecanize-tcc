<?php

class Cliente
{
    private $conn;
    private $lastError = '';

    public function __construct()
    {
        $this->conn = new Database();
    }

    /** Lista clientes cadastrados neste mês, para o modal de "Novos esse Mês" */
    public function listarNovosEsteMes(): array
    {
        $sql = "
            SELECT
                c.id_cliente,
                c.nome,
                c.telefone,
                c.criado_em,
                (
                    SELECT CONCAT(mv.nome, ' ', mo.nome)
                    FROM veiculos v2
                    INNER JOIN marcas_veiculos mv ON mv.id_marca  = v2.marca_id
                    INNER JOIN modelos_veiculos mo ON mo.id_modelo = v2.modelo_id
                    WHERE v2.cliente_id = c.id_cliente
                    ORDER BY v2.id_veiculo ASC
                    LIMIT 1
                ) AS veiculo_principal

            FROM clientes c
            WHERE c.ativo = 1
              AND MONTH(c.criado_em) = MONTH(CURDATE())
              AND YEAR(c.criado_em)  = YEAR(CURDATE())
            ORDER BY c.criado_em DESC
        ";

        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Lista todos os clientes VIP/recorrentes (2+ OS), para o modal de listagem */
    public function listarVipRecorrentes(): array
    {
        $sql = "
            SELECT
                c.id_cliente,
                c.nome,
                c.email,
                c.telefone,
                COUNT(os.data_abertura) AS total_visitas,
                MAX(os.data_abertura)   AS ultima_visita,
                (
                    SELECT CONCAT(mv.nome, ' ', mo.nome)
                    FROM veiculos v2
                    INNER JOIN marcas_veiculos mv ON mv.id_marca  = v2.marca_id
                    INNER JOIN modelos_veiculos mo ON mo.id_modelo = v2.modelo_id
                    WHERE v2.cliente_id = c.id_cliente
                    ORDER BY v2.id_veiculo ASC
                    LIMIT 1
                ) AS veiculo_principal

            FROM clientes c
            INNER JOIN ordens_servico os ON os.cliente_id = c.id_cliente
            WHERE c.ativo = 1

            GROUP BY c.id_cliente, c.nome, c.email, c.telefone
            HAVING COUNT(os.data_abertura) >= 2
            ORDER BY total_visitas DESC, c.nome ASC
        ";

        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Perfil completo de um cliente para o modal de detalhes */
    public function getPerfilCompleto(int $id): ?array
    {
        $cliente = $this->findById($id);

        if (!$cliente) {
            return null;
        }

        $sqlVeiculos = "
            SELECT v.id_veiculo, mv.nome AS marca, mo.nome AS modelo, v.ano
            FROM veiculos v
            INNER JOIN marcas_veiculos mv ON mv.id_marca  = v.marca_id
            INNER JOIN modelos_veiculos mo ON mo.id_modelo = v.modelo_id
            WHERE v.cliente_id = ?
            ORDER BY v.id_veiculo ASC
        ";
        $stmtV = $this->conn->prepare($sqlVeiculos);
        $stmtV->execute([$id]);
        $veiculos = $stmtV->fetchAll(PDO::FETCH_ASSOC);

        $sqlStats = "
            SELECT
                COUNT(os.data_abertura) AS total_visitas,
                MIN(os.data_abertura)   AS primeira_visita,
                MAX(os.data_abertura)   AS ultima_visita
            FROM ordens_servico os
            WHERE os.cliente_id = ?
        ";
        $stmtS = $this->conn->prepare($sqlStats);
        $stmtS->execute([$id]);
        $stats = $stmtS->fetch(PDO::FETCH_ASSOC) ?: [
            'total_visitas'    => 0,
            'primeira_visita'  => null,
            'ultima_visita'    => null,
        ];

        return [
            'id_cliente'      => $cliente['id_cliente'],
            'nome'            => $cliente['nome'],
            'email'           => $cliente['email'],
            'telefone'        => $cliente['telefone'],
            'endereco'        => $cliente['endereco'],
            'bairro'          => $cliente['bairro'],
            'cidade'          => $cliente['cidade'],
            'uf'              => $cliente['uf'],
            'cep'             => $cliente['cep'],
            'cpf_cnpj'        => $cliente['cpf'] ?: $cliente['cnpj'],
            'criado_em'       => $cliente['criado_em'],
            'total_visitas'   => (int) $stats['total_visitas'],
            'primeira_visita' => $stats['primeira_visita'],
            'ultima_visita'   => $stats['ultima_visita'],
            'veiculos'        => $veiculos,
        ];
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    private function soDigitos($valor): string
    {
        return preg_replace('/\D+/', '', (string) $valor);
    }

    /** E-mail já usado por OUTRO cliente ATIVO? ($exceto = id a ignorar na edição) */
    public function emailExisteAtivo($email, $exceto = null): bool
    {
        $email = trim((string) $email);
        if ($email === '') return false;

        $sql    = "SELECT COUNT(*) FROM clientes WHERE ativo = 1 AND LOWER(email) = LOWER(?)";
        $params = [$email];
        if ($exceto !== null) {
            $sql      .= " AND id_cliente <> ?";
            $params[]  = $exceto;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Telefone já usado por OUTRO cliente ATIVO? (compara só os dígitos) */
    public function telefoneExisteAtivo($telefone, $exceto = null): bool
    {
        $tel = $this->soDigitos($telefone);
        if ($tel === '') return false;

        // Remove a máscara da coluna para comparar somente os dígitos
        $col = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone,'(',''),')',''),'-',''),' ',''),'.',''),'+','')";

        $sql    = "SELECT COUNT(*) FROM clientes WHERE ativo = 1 AND $col = ?";
        $params = [$tel];
        if ($exceto !== null) {
            $sql      .= " AND id_cliente <> ?";
            $params[]  = $exceto;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** CPF já usado por OUTRO cliente ATIVO? (compara só os dígitos) */
    public function cpfExisteAtivo($cpf, $exceto = null): bool
    {
        $doc = $this->soDigitos($cpf);
        if ($doc === '') return false;

        $col = "REPLACE(REPLACE(cpf,'.',''),'-','')";

        $sql    = "SELECT COUNT(*) FROM clientes WHERE ativo = 1 AND $col = ?";
        $params = [$doc];
        if ($exceto !== null) {
            $sql      .= " AND id_cliente <> ?";
            $params[]  = $exceto;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** CNPJ já usado por OUTRO cliente ATIVO? (compara só os dígitos) */
    public function cnpjExisteAtivo($cnpj, $exceto = null): bool
    {
        $doc = $this->soDigitos($cnpj);
        if ($doc === '') return false;

        $col = "REPLACE(REPLACE(REPLACE(cnpj,'.',''),'/',''),'-','')";

        $sql    = "SELECT COUNT(*) FROM clientes WHERE ativo = 1 AND $col = ?";
        $params = [$doc];
        if ($exceto !== null) {
            $sql      .= " AND id_cliente <> ?";
            $params[]  = $exceto;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function getResumo($filtros = [])
    {
        $totalResultados = $this->contarClientesFiltrados($filtros);

        return [
            'totalClientes'   => $this->totalClientes(),
            'novosMes'        => $this->novosEsteMes(),
            'clientesVip'     => $this->clientesVipRecorrentes(),
            'clientes'        => $this->listarClientes($filtros),
            'totalResultados' => $totalResultados,
            'paginaAtual'     => max(1, (int)($filtros['pagina'] ?? 1)),
            'totalPaginas'    => max(1, (int)ceil($totalResultados / 10)),
        ];
    }

    private function totalClientes()
    {
        $sql = "SELECT COUNT(*) FROM clientes WHERE ativo = 1";
        return (int) $this->conn->query($sql)->fetchColumn();
    }

    private function novosEsteMes()
    {
        $sql = "
            SELECT COUNT(*)
            FROM clientes
            WHERE ativo = 1
              AND MONTH(criado_em) = MONTH(CURDATE())
              AND YEAR(criado_em)  = YEAR(CURDATE())
        ";
        return (int) $this->conn->query($sql)->fetchColumn();
    }

    private function clientesVipRecorrentes()
    {
        $sql = "
            SELECT COUNT(*)
            FROM (
                SELECT os.cliente_id
                FROM ordens_servico os
                INNER JOIN clientes c ON c.id_cliente = os.cliente_id
                WHERE c.ativo = 1
                GROUP BY os.cliente_id
                HAVING COUNT(*) >= 2
            ) AS recorrentes
        ";
        return (int) $this->conn->query($sql)->fetchColumn();
    }

    public function listarClientes($filtros = [])
    {
        $busca  = trim($filtros['busca'] ?? '');
        $status = $filtros['status'] ?? '';

        $pagina    = max(1, (int)($filtros['pagina'] ?? 1));
        $porPagina = 10;
        $offset    = ($pagina - 1) * $porPagina;

        $sql = "
        SELECT
            c.id_cliente,
            c.nome,
            c.email,
            c.telefone,
            c.ativo,
            c.criado_em,
            COALESCE(NULLIF(c.cpf, ''), NULLIF(c.cnpj, ''), '') AS cpf_cnpj,

            CASE
                WHEN c.ativo = 1 THEN 'ativo'
                ELSE 'inativo'
            END AS status,

            COUNT(DISTINCT v.id_veiculo) AS total_veiculos,
            MAX(os.data_abertura)        AS ultima_visita,

            (
                SELECT CONCAT(mv.nome, ' ', mo.nome, ' ', COALESCE(v2.ano, ''))
                FROM veiculos v2
                INNER JOIN marcas_veiculos mv ON mv.id_marca  = v2.marca_id
                INNER JOIN modelos_veiculos mo ON mo.id_modelo = v2.modelo_id
                WHERE v2.cliente_id = c.id_cliente
                ORDER BY v2.id_veiculo ASC
                LIMIT 1
            ) AS veiculo_principal

        FROM clientes c
        LEFT JOIN veiculos v        ON v.cliente_id  = c.id_cliente
        LEFT JOIN ordens_servico os ON os.cliente_id = c.id_cliente
        WHERE 1 = 1
    ";

        $params = [];

        if ($busca !== '') {
            $sql .= " AND (
            c.nome LIKE ?
            OR c.telefone LIKE ?
            OR c.email LIKE ?
            OR c.cpf LIKE ?
            OR c.cnpj LIKE ?
        )";

            $termo = '%' . $busca . '%';

            $params[] = $termo;
            $params[] = $termo;
            $params[] = $termo;
            $params[] = $termo;
            $params[] = $termo;
        }

        if ($status === 'inativo') {
            $sql .= " AND c.ativo = 0";
        } else {
            $sql .= " AND c.ativo = 1";
        }

        $sql .= "
        GROUP BY
            c.id_cliente,
            c.nome,
            c.email,
            c.telefone,
            c.ativo,
            c.criado_em,
            c.cpf,
            c.cnpj

        ORDER BY (c.nome = 'Cliente Balcão') DESC, c.nome ASC
        LIMIT ? OFFSET ?
    ";

        $stmt = $this->conn->prepare($sql);

        $i = 1;

        foreach ($params as $param) {
            $stmt->bindValue($i, $param, PDO::PARAM_STR);
            $i++;
        }

        $stmt->bindValue($i, $porPagina, PDO::PARAM_INT);
        $stmt->bindValue($i + 1, $offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function contarClientesFiltrados($filtros = [])
    {
        $busca  = trim($filtros['busca'] ?? '');
        $status = $filtros['status'] ?? '';

        $sql = "
        SELECT COUNT(*)
        FROM clientes c
        WHERE 1 = 1
    ";

        $params = [];

        if ($busca !== '') {
            $sql .= " AND (
            c.nome LIKE ?
            OR c.telefone LIKE ?
            OR c.email LIKE ?
            OR c.cpf LIKE ?
            OR c.cnpj LIKE ?
        )";

            $termo = '%' . $busca . '%';

            $params[] = $termo;
            $params[] = $termo;
            $params[] = $termo;
            $params[] = $termo;
            $params[] = $termo;
        }

        if ($status === 'inativo') {
            $sql .= " AND c.ativo = 0";
        } else {
            $sql .= " AND c.ativo = 1";
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public function findById($id)
    {
        $sql = "
            SELECT *
            FROM clientes
            WHERE id_cliente = ?
            LIMIT 1
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getDesativados(string $busca = '', int $pagina = 1): array
    {
        $porPagina = 10;
        $offset    = ($pagina - 1) * $porPagina;
        $like      = '%' . $busca . '%';

        $sqlTotal = "
            SELECT COUNT(*)
            FROM clientes
            WHERE ativo = 0
              AND (nome LIKE ? OR telefone LIKE ? OR email LIKE ?)
        ";
        $stmtTotal = $this->conn->prepare($sqlTotal);
        $stmtTotal->execute([$like, $like, $like]);
        $total = (int) $stmtTotal->fetchColumn();

        $sql = "
            SELECT
                c.id_cliente,
                c.nome,
                c.email,
                c.telefone,
                c.desativado_em,
                c.criado_em,

                COUNT(DISTINCT v.id_veiculo) AS total_veiculos,
                MAX(os.data_abertura)        AS ultima_visita,

                (
                    SELECT CONCAT(mv.nome, ' ', mo.nome, ' ', COALESCE(v2.ano, ''))
                    FROM veiculos v2
                    INNER JOIN marcas_veiculos mv ON mv.id_marca  = v2.marca_id
                    INNER JOIN modelos_veiculos mo ON mo.id_modelo = v2.modelo_id
                    WHERE v2.cliente_id = c.id_cliente
                    ORDER BY v2.id_veiculo ASC
                    LIMIT 1
                ) AS veiculo_principal

            FROM clientes c
            LEFT JOIN veiculos v        ON v.cliente_id  = c.id_cliente
            LEFT JOIN ordens_servico os ON os.cliente_id = c.id_cliente

            WHERE c.ativo = 0
              AND (c.nome LIKE ? OR c.telefone LIKE ? OR c.email LIKE ?)

            GROUP BY
                c.id_cliente, c.nome, c.email,
                c.telefone, c.desativado_em, c.criado_em

            ORDER BY c.desativado_em DESC

            LIMIT ? OFFSET ?
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(1, $like,      PDO::PARAM_STR);
        $stmt->bindValue(2, $like,      PDO::PARAM_STR);
        $stmt->bindValue(3, $like,      PDO::PARAM_STR);
        $stmt->bindValue(4, $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(5, $offset,    PDO::PARAM_INT);
        $stmt->execute();

        return [
            'clientesDesativados' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'totalDesativados'    => $total,
            'paginaAtual'         => $pagina,
        ];
    }

    public function reativar(int $id): bool
    {
        $sql  = "UPDATE clientes SET ativo = 1, desativado_em = NULL WHERE id_cliente = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$id]);
    }

    public function create(array $dados): bool
    {
        $this->lastError = '';

        if ($this->emailExisteAtivo($dados['email'] ?? '')) {
            $this->lastError = 'Já existe um cliente ativo com este e-mail.';
            return false;
        }
        if ($this->telefoneExisteAtivo($dados['telefone'] ?? '')) {
            $this->lastError = 'Já existe um cliente ativo com este telefone.';
            return false;
        }

        $sql = "
        INSERT INTO clientes (tipo_pessoa, nome, cpf, cnpj, email, telefone, endereco, bairro, cidade, uf, cep, observacoes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

        $stmt = $this->conn->prepare($sql);

        try {
            return $stmt->execute([
                $dados['tipo_pessoa'] ?? null,
                $dados['nome']        ?? null,
                $dados['cpf']         ?? null,
                $dados['cnpj']        ?? null,
                $dados['email']       ?? null,
                $dados['telefone']    ?? null,
                $dados['endereco']    ?? null,
                $dados['bairro']      ?? null,
                $dados['cidade']      ?? null,
                $dados['uf']          ?? null,
                $dados['cep']         ?? null,
                $dados['observacoes'] ?? null,
            ]);
        } catch (\PDOException $e) {
            // Backstop dos índices únicos (uq_clientes_*_ativo) pegando uma
            // corrida que a checagem de aplicação acima não pegou (duplo
            // clique quase simultâneo) — sqlstate 23000 é violação de
            // integridade/unicidade. Vira a mesma mensagem amigável de
            // sempre, em vez de estourar como erro 500 genérico.
            if ($e->getCode() === '23000') {
                $this->lastError = 'Já existe outro cliente ativo com o mesmo e-mail, telefone, CPF ou CNPJ.';
                return false;
            }
            throw $e;
        }
    }

    public function listarAtivos(): array
    {
        // "Cliente Balcão" (cliente genérico de balcão) fica sempre no topo da lista
        $sql = "SELECT id_cliente, nome FROM clientes WHERE ativo = 1 ORDER BY (nome = 'Cliente Balcão') DESC, nome ASC";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(int $id, array $dados): bool
    {
        $this->lastError = '';

        if ($this->emailExisteAtivo($dados['email'] ?? '', $id)) {
            $this->lastError = 'Já existe outro cliente ativo com este e-mail.';
            return false;
        }
        if ($this->telefoneExisteAtivo($dados['telefone'] ?? '', $id)) {
            $this->lastError = 'Já existe outro cliente ativo com este telefone.';
            return false;
        }

        // Mantém desativado_em coerente com o "Ativo" da tela de Editar, do
        // mesmo jeito que delete()/reativar() já fazem — sem isso, alternar
        // esse campo por aqui deixava a data de desativação desatualizada,
        // bagunçando a ordenação de listClientesDesativados (ORDER BY
        // desativado_em DESC).
        $ativo = (int) ($dados['ativo'] ?? 1);

        $sql = "
        UPDATE clientes SET
            tipo_pessoa = ?,
            nome = ?,
            cpf = ?,
            cnpj = ?,
            email = ?,
            telefone = ?,
            endereco = ?,
            bairro = ?,
            cidade = ?,
            uf = ?,
            cep = ?,
            observacoes = ?,
            ativo = ?,
            desativado_em = CASE WHEN ? = 0 THEN NOW() ELSE NULL END,
            atualizado_em = NOW()
        WHERE id_cliente = ?
    ";

        $stmt = $this->conn->prepare($sql);

        try {
            return $stmt->execute([
                $dados['tipo_pessoa'] ?? null,
                $dados['nome']        ?? null,
                $dados['cpf']         ?? null,
                $dados['cnpj']        ?? null,
                $dados['email']       ?? null,
                $dados['telefone']    ?? null,
                $dados['endereco']    ?? null,
                $dados['bairro']      ?? null,
                $dados['cidade']      ?? null,
                $dados['uf']          ?? null,
                $dados['cep']         ?? null,
                $dados['observacoes'] ?? null,
                $ativo,
                $ativo,
                $id
            ]);
        } catch (\PDOException $e) {
            // Mesmo backstop de create() — ver comentário lá.
            if ($e->getCode() === '23000') {
                $this->lastError = 'Já existe outro cliente ativo com o mesmo e-mail, telefone, CPF ou CNPJ.';
                return false;
            }
            throw $e;
        }
    }
    public function delete(int $id): bool
    {
        $sql  = "UPDATE clientes SET ativo = 0, desativado_em = NOW() WHERE id_cliente = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$id]);
    }
}
