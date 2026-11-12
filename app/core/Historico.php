<?php

/**
 * Trilha de auditoria das mudanças de estado (tabela historico_estados).
 *
 * Toda transição relevante — OS, orçamento, e as entidades das próximas etapas —
 * grava UMA linha: de onde saiu, para onde foi, quem fez, por quê e por qual
 * caminho (usuário, portal do cliente, webhook, sistema). A linha é gravada na
 * MESMA transação da mudança: se a mudança desfaz, a trilha desfaz junto, e nunca
 * há mudança sem rastro nem rastro sem mudança.
 *
 * É só de escrita pelo sistema: não existe tela nem método para editar ou apagar.
 */
final class Historico
{
    public const ORIGEM_USUARIO = 'USUARIO';
    public const ORIGEM_SISTEMA = 'SISTEMA';
    public const ORIGEM_PORTAL  = 'PORTAL';
    public const ORIGEM_WEBHOOK = 'WEBHOOK';

    /**
     * @param array<string,mixed>|null $dados contexto adicional (vira JSON)
     */
    public static function registrar(
        PDO $pdo,
        string $entidade,
        int $entidadeId,
        ?string $de,
        string $para,
        ?int $usuarioId = null,
        ?string $motivo = null,
        string $origem = self::ORIGEM_USUARIO,
        ?array $dados = null
    ): int {
        $pdo->prepare(
            'INSERT INTO historico_estados
                (entidade, entidade_id, estado_anterior, estado_novo, usuario_id, origem, motivo, dados)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $entidade,
            $entidadeId,
            $de,
            $para,
            self::usuarioExistente($pdo, $usuarioId),
            $origem,
            $motivo !== null ? mb_substr(trim($motivo), 0, 500) : null,
            $dados !== null ? json_encode($dados, JSON_UNESCAPED_UNICODE) : null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** Linha do tempo de uma entidade, da mais antiga para a mais recente. */
    public static function da(PDO $pdo, string $entidade, int $entidadeId): array
    {
        $stmt = $pdo->prepare(
            'SELECT h.*, u.nome AS usuario_nome
             FROM historico_estados h
             LEFT JOIN usuarios u ON u.id_usuario = h.usuario_id
             WHERE h.entidade = ? AND h.entidade_id = ?
             ORDER BY h.id_historico ASC'
        );
        $stmt->execute([$entidade, $entidadeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Um id de usuário que sumiu (sessão antiga) não pode derrubar a transação
     * inteira por causa de um campo de autoria — mesmo cuidado de
     * MovimentacaoEstoque::usuarioValido().
     */
    private static function usuarioExistente(PDO $pdo, ?int $usuarioId): ?int
    {
        if (!$usuarioId || $usuarioId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT 1 FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $stmt->execute([$usuarioId]);

        return $stmt->fetchColumn() ? $usuarioId : null;
    }
}
