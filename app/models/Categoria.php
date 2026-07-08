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

    public function delete($id)
    {
        $sql = "DELETE FROM categorias_produtos WHERE id_categoria = ?";
        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([$id]);
    }
}