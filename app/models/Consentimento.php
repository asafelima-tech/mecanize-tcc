<?php

/**
 * Consentimento do cliente para receber mensagens (LGPD e regras do WhatsApp).
 *
 * É um LIVRO: cada decisão (aceitou / pediu para parar) é uma linha nova, com origem, data e quem
 * registrou; nunca se edita nem se apaga. O que vale é a decisão mais recente.
 *
 *   WHATSAPP  só se envia com consentimento registrado (a plataforma exige opt-in para mensagens
 *             iniciadas pela empresa).
 *   EMAIL     as mensagens operacionais do serviço contratado vão para o e-mail que o cliente deu,
 *             a menos que ele tenha pedido para parar.
 */
final class Consentimento
{
    public const ORIGENS = ['BALCAO' => 'No balcão', 'TELEFONE' => 'Por telefone', 'PORTAL' => 'Pelo portal', 'WHATSAPP' => 'Pelo próprio WhatsApp', 'OUTRO' => 'Outro'];

    /** @return bool|null true = aceitou, false = pediu para parar, null = nunca foi perguntado */
    public static function vigente(PDO $pdo, int $clienteId, string $canal): ?bool
    {
        $stmt = $pdo->prepare('SELECT concedido FROM consentimentos_comunicacao WHERE cliente_id = ? AND canal = ? ORDER BY id_consentimento DESC LIMIT 1');
        $stmt->execute([$clienteId, $canal]);
        $v = $stmt->fetchColumn();

        return $v === false ? null : (bool) (int) $v;
    }

    /** Pode receber por este canal agora? */
    public static function permite(PDO $pdo, int $clienteId, string $canal): bool
    {
        $v = self::vigente($pdo, $clienteId, $canal);

        return $canal === 'WHATSAPP' ? $v === true : $v !== false;
    }

    /**
     * @return array{ok:bool, erro:?string, repetido:bool}
     */
    public static function registrar(PDO $pdo, int $clienteId, string $canal, bool $concedido, string $origem, ?int $usuarioId, ?string $observacao = null): array
    {
        if (!in_array($canal, ['WHATSAPP', 'EMAIL'], true)) {
            return ['ok' => false, 'erro' => 'Canal inválido.', 'repetido' => false];
        }

        if (!isset(self::ORIGENS[$origem])) {
            $origem = 'OUTRO';
        }

        $existe = $pdo->prepare('SELECT 1 FROM clientes WHERE id_cliente = ?');
        $existe->execute([$clienteId]);

        if (!$existe->fetchColumn()) {
            return ['ok' => false, 'erro' => 'Cliente não encontrado.', 'repetido' => false];
        }

        // Repetir a mesma decisão não suja o histórico.
        if (self::vigente($pdo, $clienteId, $canal) === $concedido) {
            return ['ok' => true, 'erro' => null, 'repetido' => true];
        }

        $pdo->prepare('INSERT INTO consentimentos_comunicacao (cliente_id, canal, concedido, origem, observacao, registrado_por) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$clienteId, $canal, $concedido ? 1 : 0, $origem, $observacao !== null ? mb_substr(trim($observacao), 0, 255) : null, $usuarioId ?: null]);

        return ['ok' => true, 'erro' => null, 'repetido' => false];
    }

    public static function historico(PDO $pdo, int $clienteId): array
    {
        $stmt = $pdo->prepare("
            SELECT c.*, u.nome AS registrado_por_nome FROM consentimentos_comunicacao c
            LEFT JOIN usuarios u ON u.id_usuario = c.registrado_por
            WHERE c.cliente_id = ? ORDER BY c.id_consentimento DESC
        ");
        $stmt->execute([$clienteId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
