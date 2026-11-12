<?php

require_once __DIR__ . '/../core/Integracoes.php';

/**
 * Acesso do cliente ao portal por link seguro.
 *
 * O link leva um token aleatório de 256 bits. O banco guarda só o HASH SHA-256 — quem lê o banco
 * (ou um backup) não consegue montar um link. O token bruto existe uma vez, no texto da mensagem
 * enviada ao cliente.
 *
 *   escopo ORCAMENTO   ver e decidir aquele orçamento (e acompanhar o caso a que pertence);
 *   escopo OS          acompanhar a OS, decidir autorizações adicionais e pagar as parcelas dela;
 *   escopo OCORRENCIA  o caso inteiro: avaliação, orçamentos, OS, autorizações e pagamentos.
 *
 * Todo link tem validade e pode ser REVOGADO (a equipe corta o acesso a qualquer momento). Link
 * expirado, revogado, inexistente ou malformado dão a MESMA resposta ao visitante: nada confirma
 * que um token existiu.
 */
final class PortalToken
{
    public const ESCOPOS = ['ORCAMENTO', 'OS', 'OCORRENCIA'];

    /** @return string o token bruto (única vez em que existe) */
    public static function emitir(PDO $pdo, int $clienteId, string $escopo, int $entidadeId, ?int $criadoPor = null, ?int $validadeDias = null): string
    {
        if (!in_array($escopo, self::ESCOPOS, true)) {
            throw new InvalidArgumentException('Escopo de portal inválido.');
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $dias = $validadeDias ?? Integracoes::validadePortalDias();

        $pdo->prepare('INSERT INTO portal_tokens (cliente_id, escopo, entidade_id, token_hash, criado_por, expira_em) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$clienteId, $escopo, $entidadeId, hash('sha256', $token), $criadoPor ?: null, date('Y-m-d H:i:s', time() + $dias * 86400)]);

        return $token;
    }

    public static function url(string $token): string
    {
        return Integracoes::appUrl() . '/portal?t=' . $token;
    }

    public static function formatoValido(string $token): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{43}$/', $token) === 1;
    }

    /**
     * O registro do token se ele vale agora; null caso contrário. Conta o acesso.
     *
     * @return array<string,mixed>|null
     */
    public static function resolver(PDO $pdo, string $token, bool $contarAcesso = true): ?array
    {
        if (!self::formatoValido($token)) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM portal_tokens WHERE token_hash = ?');
        $stmt->execute([hash('sha256', $token)]);
        $t = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$t || $t['revogado_em'] !== null || strtotime($t['expira_em']) < time()) {
            return null;
        }

        if ($contarAcesso) {
            $pdo->prepare('UPDATE portal_tokens SET acessos = acessos + 1, ultimo_acesso_em = NOW() WHERE id_token = ?')->execute([$t['id_token']]);
        }

        return $t;
    }

    /** Corta os links ativos de uma entidade. Devolve quantos foram revogados. */
    public static function revogarDaEntidade(PDO $pdo, string $escopo, int $entidadeId): int
    {
        $stmt = $pdo->prepare('UPDATE portal_tokens SET revogado_em = NOW() WHERE escopo = ? AND entidade_id = ? AND revogado_em IS NULL');
        $stmt->execute([$escopo, $entidadeId]);

        return $stmt->rowCount();
    }

    /** Links (sem o token bruto) de uma entidade, para a equipe ver quantos existem e se foram usados. */
    public static function listarDaEntidade(PDO $pdo, string $escopo, int $entidadeId): array
    {
        $stmt = $pdo->prepare("
            SELECT id_token, criado_em, expira_em, revogado_em, ultimo_acesso_em, acessos,
                   CASE WHEN revogado_em IS NOT NULL THEN 'REVOGADO' WHEN expira_em < NOW() THEN 'EXPIRADO' ELSE 'ATIVO' END AS situacao
            FROM portal_tokens WHERE escopo = ? AND entidade_id = ? ORDER BY id_token DESC
        ");
        $stmt->execute([$escopo, $entidadeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
