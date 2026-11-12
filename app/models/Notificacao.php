<?php

/**
 * Notificações internas (o sino do sistema).
 *
 * Cada notificação nasce de um FATO (uma solicitação criada, um pedido recebido) e carrega
 * uma chave de idempotência desse fato: reprocessar o mesmo evento — clique repetido, retry —
 * nunca avisa a mesma pessoa duas vezes (chave única por destinatário).
 *
 * Os métodos estáticos recebem o PDO para entrar na transação de quem notifica: se a
 * mudança que gerou o aviso desfaz, o aviso desfaz junto. Aviso de algo que não aconteceu
 * é pior do que aviso nenhum.
 *
 * Isto é só o canal DENTRO do sistema. WhatsApp/e-mail são a Etapa 5 e, quando existirem,
 * saem desta mesma origem de fatos.
 */
class Notificacao
{
    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    /** @return bool true se criou; false se já existia (mesma chave para o mesmo usuário) */
    public static function criar(PDO $pdo, int $usuarioId, string $tipo, string $titulo, ?string $mensagem, ?string $link, string $chave): bool
    {
        $stmt = $pdo->prepare("
            INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem, link, chave_idempotencia)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE id_notificacao = id_notificacao
        ");
        $stmt->execute([
            $usuarioId,
            mb_substr($tipo, 0, 40),
            mb_substr($titulo, 0, 150),
            $mensagem !== null ? mb_substr($mensagem, 0, 500) : null,
            $link,
            mb_substr($chave, 0, 120),
        ]);

        // 1 = inserida, 0 = já existia (ON DUPLICATE sem mudança)
        return $stmt->rowCount() === 1;
    }

    /**
     * @param int[] $usuarioIds
     * @return int quantos avisos novos foram criados
     */
    public static function paraUsuarios(PDO $pdo, array $usuarioIds, string $tipo, string $titulo, ?string $mensagem, ?string $link, string $chave): int
    {
        $criadas = 0;

        foreach (array_unique(array_map('intval', $usuarioIds)) as $id) {
            if ($id > 0 && self::criar($pdo, $id, $tipo, $titulo, $mensagem, $link, $chave)) {
                $criadas++;
            }
        }

        return $criadas;
    }

    /** Todos os administradores ativos (exceto, opcionalmente, quem originou o fato). */
    public static function idsDosAdmins(PDO $pdo, ?int $exceto = null): array
    {
        $ids = $pdo->query("SELECT id_usuario FROM usuarios WHERE tipo = 'ADMIN' AND ativo = 1")->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_filter(array_map('intval', $ids), fn($id) => $id !== $exceto));
    }

    public function listar(int $usuarioId, bool $soNaoLidas = false, int $limite = 60): array
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM notificacoes
            WHERE usuario_id = :u" . ($soNaoLidas ? ' AND lida_em IS NULL' : '') . "
            ORDER BY id_notificacao DESC
            LIMIT " . (int) $limite
        );
        $stmt->execute([':u' => $usuarioId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarNaoLidas(int $usuarioId): int
    {
        $stmt = $this->conn->prepare('SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida_em IS NULL');
        $stmt->execute([$usuarioId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Marca como lida e devolve o link de destino. Só mexe em notificação do próprio usuário:
     * o id vem do formulário e, sem essa condição, qualquer um marcaria a dos outros.
     */
    public function marcarLida(int $id, int $usuarioId): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM notificacoes WHERE id_notificacao = ? AND usuario_id = ?');
        $stmt->execute([$id, $usuarioId]);
        $n = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$n) {
            return null;
        }

        $this->conn->prepare('UPDATE notificacoes SET lida_em = COALESCE(lida_em, NOW()) WHERE id_notificacao = ?')->execute([$id]);

        return $n;
    }

    public function marcarTodas(int $usuarioId): int
    {
        $stmt = $this->conn->prepare('UPDATE notificacoes SET lida_em = NOW() WHERE usuario_id = ? AND lida_em IS NULL');
        $stmt->execute([$usuarioId]);

        return $stmt->rowCount();
    }
}
