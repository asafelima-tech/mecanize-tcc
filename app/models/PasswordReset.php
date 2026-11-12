<?php

class PasswordReset
{
    public $conn;

    public function __construct()
    {
        $this->conn = new Database();
    }

    /**
     * Gera um token aleatório de recuperação para o usuário, válido por
     * $minutosValidade minutos (padrão 30).
     */
    public function criar($usuarioId, $minutosValidade = 30)
    {
        // Pedir um link novo invalida os anteriores. Sem isto, cada pedido
        // deixava mais um token vivo por 30 minutos: um link antigo que tivesse
        // vazado (caixa de e-mail alheia, histórico, encaminhamento) continuava
        // abrindo a conta. É também o que a pessoa espera ao pedir outro link.
        $limpeza = $this->conn->prepare(
            "UPDATE password_resets SET usado = 1 WHERE usuario_id = ? AND usado = 0"
        );
        $limpeza->execute([$usuarioId]);

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
     * Quantos pedidos de recuperação essa conta fez na última hora.
     *
     * Serve para não transformar o formulário público de "esqueci a senha" num
     * botão de spam contra a caixa de e-mail de qualquer usuário cadastrado.
     */
    public function pedidosNaUltimaHora(int $usuarioId): int
    {
        $sql = "SELECT COUNT(*) FROM password_resets
                WHERE usuario_id = ?
                  AND criado_em >= NOW() - INTERVAL 1 HOUR";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$usuarioId]);

        return (int) $stmt->fetchColumn();
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
