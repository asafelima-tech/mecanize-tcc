<?php

require_once __DIR__ . '/../core/Transacao.php';
require_once __DIR__ . '/../core/Historico.php';

class Cliente
{
    private $conn;
    private $lastError = '';

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
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
        $totalPaginas    = max(1, (int)ceil($totalResultados / 10));

        // Se o filtro/aba mudou e a página pedida ficou fora do intervalo
        // (ex.: estava na página 5 e agora só há 2), reposiciona na última
        // válida em vez de devolver uma lista vazia.
        $pagina = min(max(1, (int)($filtros['pagina'] ?? 1)), $totalPaginas);
        $filtros['pagina'] = $pagina;

        return [
            'totalClientes'   => $this->totalClientes(),
            'novosMes'        => $this->novosEsteMes(),
            'clientesVip'     => $this->clientesVipRecorrentes(),
            'clientes'        => $this->listarClientes($filtros),
            'totalResultados' => $totalResultados,
            'paginaAtual'     => $pagina,
            'totalPaginas'    => $totalPaginas,

            // Contadores das abas: sempre globais, independentes da busca —
            // são o total de cada recorte, não do resultado filtrado.
            'totalAtivos'     => $this->countAtivos(),
            'totalInativos'   => $this->countInativos(),
        ];
    }

    public function countAtivos(): int
    {
        return (int) $this->conn->query("SELECT COUNT(*) FROM clientes WHERE ativo = 1")->fetchColumn();
    }

    public function countInativos(): int
    {
        return (int) $this->conn->query("SELECT COUNT(*) FROM clientes WHERE ativo = 0")->fetchColumn();
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
            c.cadastro_status,
            c.criado_em,
            c.desativado_em,
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
            c.cadastro_status,
            c.criado_em,
            c.desativado_em,
            c.cpf,
            c.cnpj
    ";

        // Na aba de inativos o que importa é o que saiu por último — mesma
        // ordenação que a tela separada de desativados usava. Nos ativos,
        // ordem alfabética com o Cliente Balcão fixo no topo.
        $sql .= ($status === 'inativo')
            ? " ORDER BY c.desativado_em DESC, c.nome ASC"
            : " ORDER BY (c.nome = 'Cliente Balcão') DESC, c.nome ASC";

        $sql .= " LIMIT ? OFFSET ?";

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
        // bagunçando a ordenação da aba de inativos (ORDER BY
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

    /**
     * Busca para o balcão: por nome, telefone (só dígitos bastam) ou placa. Devolve o
     * cliente com seus veículos, para o atendente escolher em vez de cadastrar de novo.
     * Só clientes ativos.
     *
     * @return array<int,array{id:int,nome:string,telefone:?string,cadastro_status:string,veiculos:array}>
     */
    public function buscar(string $termo, int $limite = 8): array
    {
        $termo = trim($termo);

        if (mb_strlen($termo) < 2) {
            return [];
        }

        $like    = '%' . $termo . '%';
        $digitos = preg_replace('/\\D+/', '', $termo);
        $placa   = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $termo));

        $stmt = $this->conn->prepare("
            SELECT DISTINCT c.id_cliente AS id, c.nome, c.telefone, c.cadastro_status
            FROM clientes c
            LEFT JOIN veiculos v ON v.cliente_id = c.id_cliente
            WHERE c.ativo = 1
              AND (c.nome LIKE :like
                   OR (:dig1 <> '' AND c.uniq_telefone LIKE CONCAT('%', :dig2, '%'))
                   OR (:placa1 <> '' AND v.placa_normalizada LIKE CONCAT('%', :placa2, '%')))
            ORDER BY c.nome
            LIMIT " . (int) $limite
        );
        $stmt->execute([':like' => $like, ':dig1' => $digitos, ':dig2' => $digitos, ':placa1' => $placa, ':placa2' => $placa]);
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $veic = $this->conn->prepare("
            SELECT v.id_veiculo AS id, v.placa, CONCAT(mv.nome, ' ', mo.nome) AS modelo
            FROM veiculos v
            JOIN marcas_veiculos mv ON mv.id_marca = v.marca_id
            JOIN modelos_veiculos mo ON mo.id_modelo = v.modelo_id
            WHERE v.cliente_id = ? ORDER BY v.placa
        ");

        foreach ($clientes as &$c) {
            $c['id'] = (int) $c['id'];
            $veic->execute([$c['id']]);
            $c['veiculos'] = $veic->fetchAll(PDO::FETCH_ASSOC);
        }

        return $clientes;
    }

    /** Cadastros provisórios (atendimento rápido) que ainda precisam ser completados. */
    public function listarProvisorios(): array
    {
        return $this->conn->query("
            SELECT c.id_cliente AS id, c.nome, c.telefone, c.criado_em,
                   (SELECT COUNT(*) FROM ocorrencias o WHERE o.cliente_id = c.id_cliente) AS ocorrencias
            FROM clientes c WHERE c.ativo = 1 AND c.cadastro_status = 'PROVISORIO' ORDER BY c.criado_em DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Marca o cadastro como COMPLETO (depois de o formulário completo ter sido salvo por update()). */
    public function marcarCompleto(int $id): bool
    {
        return $this->conn->prepare("UPDATE clientes SET cadastro_status = 'COMPLETO' WHERE id_cliente = ?")->execute([$id]);
    }

    /**
     * Funde um cadastro PROVISÓRIO em um cadastro que já existia, preservando todo o histórico.
     *
     * Para não misturar pessoas diferentes, só se funde um provisório num cliente ativo e
     * diferente dele, e a decisão é explícita (quem chama escolhe o destino). O provisório
     * não é apagado: fica desativado e apontando para o destino (`unificado_em_id`). Veículos,
     * ocorrências, orçamentos, OS, agendamentos e autorizações passam para o destino.
     *
     * @return array{ok:bool, erro:?string, movidos:array<string,int>}
     */
    public function unificar(int $provisorioId, int $destinoId, ?int $usuarioId): array
    {
        $vazio = ['ok' => false, 'erro' => null, 'movidos' => []];

        try {
            return Transacao::executar($this->conn, function () use ($provisorioId, $destinoId, $usuarioId, $vazio) {
                if ($provisorioId === $destinoId) {
                    throw new DomainException('Escolha um cadastro diferente do provisório.');
                }

                // Trava os dois sempre na mesma ordem (menor id primeiro): duas unificações
                // cruzadas não se travam mutuamente.
                $ids = [$provisorioId, $destinoId];
                sort($ids);
                $stmt = $this->conn->prepare('SELECT * FROM clientes WHERE id_cliente IN (?, ?) ORDER BY id_cliente FOR UPDATE');
                $stmt->execute($ids);
                $linhas = [];
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
                    $linhas[(int) $l['id_cliente']] = $l;
                }

                $prov = $linhas[$provisorioId] ?? null;
                $dest = $linhas[$destinoId] ?? null;

                if (!$prov || !$dest) {
                    throw new DomainException('Cliente não encontrado.');
                }

                if ($prov['cadastro_status'] !== 'PROVISORIO' || (int) $prov['ativo'] !== 1) {
                    throw new DomainException('Só um cadastro provisório ativo pode ser unificado.');
                }

                if ((int) $dest['ativo'] !== 1 || $dest['unificado_em_id'] !== null) {
                    throw new DomainException('O cadastro de destino precisa estar ativo.');
                }

                // Desativa o provisório ANTES de mexer no telefone do destino: as colunas únicas
                // só valem para ativos, e o telefone dele pode ser o mesmo.
                $this->conn->prepare("
                    UPDATE clientes
                    SET ativo = 0, desativado_em = NOW(), unificado_em_id = ?, unificado_em = NOW(),
                        observacoes = CONCAT(COALESCE(CONCAT(observacoes, '\n'), ''), ?)
                    WHERE id_cliente = ?
                ")->execute([$destinoId, 'Cadastro provisório unificado em #' . $destinoId . ' (' . $dest['nome'] . ')', $provisorioId]);

                if (empty($dest['telefone']) && !empty($prov['telefone'])) {
                    $this->conn->prepare('UPDATE clientes SET telefone = ? WHERE id_cliente = ?')->execute([$prov['telefone'], $destinoId]);
                }

                $movidos = [];
                foreach (['veiculos', 'ocorrencias', 'orcamentos', 'ordens_servico', 'agendamentos', 'autorizacoes_cliente'] as $tabela) {
                    $u = $this->conn->prepare("UPDATE `{$tabela}` SET cliente_id = ? WHERE cliente_id = ?");
                    $u->execute([$destinoId, $provisorioId]);
                    $movidos[$tabela] = $u->rowCount();
                }

                Historico::registrar(
                    $this->conn, 'CLIENTE', $provisorioId, 'PROVISORIO', 'UNIFICADO', $usuarioId,
                    'Unificado no cadastro #' . $destinoId . ' (' . $dest['nome'] . ')',
                    Historico::ORIGEM_USUARIO,
                    ['destino_id' => $destinoId, 'movidos' => $movidos]
                );

                return array_merge($vazio, ['ok' => true, 'movidos' => $movidos]);
            });
        } catch (DomainException $e) {
            return array_merge($vazio, ['erro' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[Cliente unificar] ' . $e->getMessage());
            return array_merge($vazio, ['erro' => 'Não foi possível unificar os cadastros. Nada foi alterado.']);
        }
    }
}
