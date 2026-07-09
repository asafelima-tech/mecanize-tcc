<?php

class User
{
    public $conn;

    public function __construct()
    {
        $this->conn = new Database();
    }

    public function login($data)
    {
        if (empty($data['email']) || empty($data['senha'])) {
            return false;
        }

        $sql = "SELECT * FROM usuarios WHERE email = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$data['email']]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user && password_verify($data['senha'], $user['senha']);
    }

    /**
     * Monta a cláusula WHERE + parâmetros a partir dos filtros da tela
     * (busca por nome/e-mail, tipo, status). Usado tanto pelo list()
     * quanto pelo countFiltrado(), pra garantir que a contagem e a
     * listagem sempre respeitem exatamente os mesmos critérios.
     *
     * $filtros = [
     *     'busca'  => string,
     *     'tipo'   => string,
     *     'status' => int|null   (0, 1 ou null = todos)
     * ]
     */
    private function buildFiltro(array $filtros): array
    {
        $where  = " WHERE 1=1";
        $params = [];

        $busca = trim((string) ($filtros['busca'] ?? ''));
        if ($busca !== '') {
            $where   .= " AND (nome LIKE ? OR email LIKE ?)";
            $params[] = '%' . $busca . '%';
            $params[] = '%' . $busca . '%';
        }

        $tipo = trim((string) ($filtros['tipo'] ?? ''));
        if ($tipo !== '') {
            $where   .= " AND tipo = ?";
            $params[] = $tipo;
        }

        $status = $filtros['status'] ?? null;
        if ($status !== null && $status !== '') {
            $where   .= " AND ativo = ?";
            $params[] = (int) $status;
        }

        return [$where, $params];
    }

    /**
     * Lista usuários já filtrados e paginados.
     */
    public function list(array $filtros = [], int $limite = 10, int $offset = 0)
    {
        [$where, $params] = $this->buildFiltro($filtros);

        $sql = "SELECT * FROM usuarios" . $where . " ORDER BY tipo, nome ASC LIMIT ? OFFSET ?";

        $stmt = $this->conn->prepare($sql);

        // Parâmetros de texto/inteiro dos filtros primeiro...
        $posicao = 1;
        foreach ($params as $valor) {
            $stmt->bindValue($posicao, $valor);
            $posicao++;
        }
        // ...depois LIMIT e OFFSET, que precisam ser bindados como inteiro
        // (com execute($array) o PDO manda tudo como string e o MySQL
        // rejeita LIMIT/OFFSET string em modo estrito).
        $stmt->bindValue($posicao, $limite, PDO::PARAM_INT);
        $stmt->bindValue($posicao + 1, $offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista todos os usuários que batem com os filtros, sem paginação.
     * Usado pela exportação CSV (listUsuarios exporta a mesma busca aplicada
     * na tela, não só a página atual).
     */
    public function listAll(array $filtros = []): array
    {
        [$where, $params] = $this->buildFiltro($filtros);

        $sql = "SELECT * FROM usuarios" . $where . " ORDER BY tipo, nome ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Conta quantos registros batem com os filtros atuais.
     * É essa contagem (não o count() da página atual) que alimenta
     * o card "Total de usuários" e o cálculo de $totalPaginas.
     */
    public function countFiltrado(array $filtros = []): int
    {
        [$where, $params] = $this->buildFiltro($filtros);

        $sql  = "SELECT COUNT(*) FROM usuarios" . $where;
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Estatísticas globais dos cards — sempre olham a tabela inteira,
     * independente dos filtros aplicados na busca da tabela abaixo.
     */
    public function countAtivos(): int
    {
        $stmt = $this->conn->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 1");
        return (int) $stmt->fetchColumn();
    }

    public function countInativos(): int
    {
        $stmt = $this->conn->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 0");
        return (int) $stmt->fetchColumn();
    }

    public function countAdmins(): int
    {
        $stmt = $this->conn->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'ADMIN'");
        return (int) $stmt->fetchColumn();
    }

    public function countNovos30d(): int
    {
        $sql  = "SELECT COUNT(*) FROM usuarios WHERE criado_em >= (NOW() - INTERVAL 30 DAY)";
        $stmt = $this->conn->query($sql);
        return (int) $stmt->fetchColumn();
    }

    public function findById($id)
    {
        $sql = "SELECT * FROM usuarios WHERE id_usuario = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function emailExiste($email, $exceto = null)
    {
        // Bloqueia apenas se houver um usuário ATIVO com este e-mail
        $sql    = "SELECT COUNT(*) FROM usuarios WHERE ativo = 1 AND email = ?";
        $params = [trim((string) $email)];

        if ($exceto !== null) {
            $sql      .= " AND id_usuario <> ?";
            $params[]  = $exceto;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() > 0;
    }

    public function create($data)
    {
        $sql = "INSERT INTO usuarios (
                    nome,
                    email,
                    senha,
                    tipo,
                    ativo
                ) VALUES (
                    :nome,
                    :email,
                    :senha,
                    :tipo,
                    :ativo
                )";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ':nome'   => trim($data['nome']),
            ':email'  => trim($data['email']),
            ':senha'  => password_hash($data['senha'], PASSWORD_DEFAULT),
            ':tipo'   => $data['tipo'],
            ':ativo'  => $data['ativo']
        ]);
    }

    public function update($data)
    {
        // telefone só é enviado pela tela de perfil — os demais fluxos (admin
        // editando outro usuário, troca de senha, redefinição por token) não
        // passam essa chave; COALESCE preserva o valor já salvo nesses casos.
        $telefone = array_key_exists('telefone', $data) ? trim((string) $data['telefone']) : null;

        // Atualiza COM senha
        if (!empty($data['senha'])) {

            $sql = "UPDATE usuarios
                    SET
                        nome = ?,
                        email = ?,
                        senha = ?,
                        tipo = ?,
                        ativo = ?,
                        telefone = COALESCE(?, telefone),
                        atualizado_em = NOW()
                    WHERE id_usuario = ?";

            $stmt = $this->conn->prepare($sql);

            return $stmt->execute([
                trim($data['nome']),
                trim($data['email']),
                $data['senha'], // NÃO criptografa novamente
                $data['tipo'],
                $data['ativo'],
                $telefone,
                $data['id_usuario']
            ]);
        }

        // Atualiza SEM senha
        $sql = "UPDATE usuarios
                SET
                    nome = ?,
                    email = ?,
                    tipo = ?,
                    ativo = ?,
                    telefone = COALESCE(?, telefone),
                    atualizado_em = NOW()
                WHERE id_usuario = ?";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            trim($data['nome']),
            trim($data['email']),
            $data['tipo'],
            $data['ativo'],
            $telefone,
            $data['id_usuario']
        ]);
    }

    /** Lista usuários cadastrados nos últimos 30 dias, para o painel de "Novos (30 dias)" */
    public function listarNovos30d(): array
    {
        $sql = "
            SELECT id_usuario, nome, email, tipo, ativo, criado_em
            FROM usuarios
            WHERE criado_em >= (NOW() - INTERVAL 30 DAY)
            ORDER BY criado_em DESC
        ";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Inverte o status (ativo/inativo) de um usuário.
     * Usado pelo clique no badge de status na listagem.
     */
    public function toggleStatus($id)
    {
        $sql = "UPDATE usuarios
                SET ativo = NOT ativo,
                    atualizado_em = NOW()
                WHERE id_usuario = ?";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([$id]);
    }

    public function getUserByEmail($email)
    {
        $sql = "SELECT * FROM usuarios WHERE email = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$email]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function delete($id)
    {
        $sql = "DELETE FROM usuarios WHERE id_usuario = ?";
        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([$id]);
    }
}
