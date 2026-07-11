<?php

class Categoria
{
    private $conn;

    public function __construct()
    {
        $this->conn = new Database();
    }

    public function list()
    {
        $sql = "SELECT * FROM categorias_produtos ORDER BY nome ASC";
        $stmt = $this->conn->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id)
    {
        $sql = "SELECT * FROM categorias_produtos WHERE id_categoria = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data)
    {
        $sql = "INSERT INTO categorias_produtos (nome) VALUES (?)";
        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            trim($data['nome'])
        ]);
    }

    public function update($data)
    {
        $sql = "UPDATE categorias_produtos SET nome = ? WHERE id_categoria = ?";
        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            trim($data['nome']),
            $data['id_categoria']
        ]);
    }

    /**
     * Quantos produtos ainda referenciam esta categoria — usado para bloquear
     * a exclusão e evitar produto_id órfão (não há FK no banco garantindo isso).
     */
    public function contarProdutosVinculados($id): int
    {
        $sql = "SELECT COUNT(*) FROM produtos WHERE categorias_produtos_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Exclui a categoria. Retorna false (sem excluir) se ainda houver produtos
     * vinculados — a checagem de uso é feita aqui, não só na tela.
     */
    public function delete($id)
    {
        if ($this->contarProdutosVinculados($id) > 0) {
            return false;
        }

        $sql = "DELETE FROM categorias_produtos WHERE id_categoria = ?";
        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([$id]);
    }
}