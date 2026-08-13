<?php

class PasswordReset
{
    public $conn;

    public function __construct()
    {
        $this->conn = new Database();
    }

    /** Quantos links de recuperação um usuário pode pedir por hora. */
    public const LIMITE_POR_HORA = 3;

    /**
     * Gera um token aleatório de recuperação para o usuário, válido por
     * $minutosValidade minutos (padrão 30).
     *
     * Os tokens anteriores ainda válidos são invalidados: pedir um link novo
     * derruba o antigo. Sem isso, cada pedido deixava mais uma chave viva por
     * 30 minutos — quem pedisse cinco vezes ficava com cinco links capazes de
     * trocar a senha, e um e-mail antigo esquecido na caixa de entrada
     * continuava valendo.
     */
    public function criar($usuarioId, $minutosValidade = 30)
    {
        $this->conn
            ->prepare("UPDATE password_resets SET usado = 1 WHERE usuario_id = ? AND usado = 0")
            ->execute([$usuarioId]);

        $token = bin2hex(random_bytes(32));

        $sql = "INSERT INTO password_resets (usuario_id, token, expira_em)
                VALUES (:usuario_id, :token, DATE_ADD(NOW(), INTERVAL :min MINUTE))";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':token', $token);
        $stmt->bindValue(':min', $minutosValidade, PDO::PARAM_INT);
        $stmt->execute();

        return $token;
    }

    /**
     * Quantos pedidos de recuperação este usuário fez na última hora.
     *
     * Serve para não transformar a tela de "esqueci minha senha" numa forma de
     * encher a caixa de entrada de alguém: basta saber o e-mail da pessoa e
     * apertar o botão em looping.
     */
    public function pedidosNaUltimaHora($usuarioId): int
    {
        $sql = "SELECT COUNT(*) FROM password_resets
                WHERE usuario_id = ?
                  AND criado_em >= (NOW() - INTERVAL 1 HOUR)";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$usuarioId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Retorna os dados do token se ele existir, ainda não tiver sido usado
     * e não estiver expirado. Retorna false caso contrário.
     */
    public function validar($token)
    {
        $sql = "SELECT * FROM password_resets
                WHERE token = :token
                  AND usado = 0
                  AND expira_em >= NOW()
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':token' => $token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Marca o token como usado, para que não possa ser reaproveitado.
     */
    public function marcarUsado($token)
    {
        $sql = "UPDATE password_resets SET usado = 1 WHERE token = :token";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':token' => $token]);
    }
}
