<?php

/**
 * Avisos internos, por usuário. Na v1 quase todos vinham de módulos que saíram (solicitação de peça,
 * portal, conversas, cobrança online). Na v2 sobra a alternativa simples à solicitação de peça: quando
 * uma OS para por falta de peça, os administradores recebem um aviso com o link da OS.
 *
 * A chave de idempotência (única por usuário) impede o mesmo aviso duas vezes.
 */
final class Notificacao extends Modelo
{
    public function listar(int $limite = 50): array
    {
        $limite = max(1, min(200, $limite));

        return $this->lista(
            "SELECT * FROM notificacoes WHERE oficina_id = :oficina AND usuario_id = :u
             ORDER BY lida_em IS NULL DESC, criada_em DESC, id DESC LIMIT {$limite}",
            ['u' => Contexto::usuarioId()]
        );
    }

    public function naoLidas(): int
    {
        return (int) $this->valor('SELECT COUNT(*) FROM notificacoes WHERE oficina_id = :oficina AND usuario_id = :u AND lida_em IS NULL',
            ['u' => Contexto::usuarioId()]);
    }

    /** Marca como lida e devolve o link (ou null se o aviso não é deste usuário). */
    public function ler(int $id): ?string
    {
        $n = $this->linha('SELECT link FROM notificacoes WHERE id = :id AND oficina_id = :oficina AND usuario_id = :u',
            ['id' => $id, 'u' => Contexto::usuarioId()]);
        if (!$n) {
            return null;
        }
        $this->consultar('UPDATE notificacoes SET lida_em = COALESCE(lida_em, NOW()) WHERE id = :id AND oficina_id = :oficina AND usuario_id = :u',
            ['id' => $id, 'u' => Contexto::usuarioId()]);

        return $n['link'] ?: './inicio';
    }

    public function lerTodas(): int
    {
        return $this->consultar('UPDATE notificacoes SET lida_em = NOW() WHERE oficina_id = :oficina AND usuario_id = :u AND lida_em IS NULL',
            ['u' => Contexto::usuarioId()])->rowCount();
    }

    /** Avisa todos os administradores ativos da oficina (menos quem causou o aviso). */
    public function paraAdmins(string $tipo, string $titulo, ?string $mensagem, ?string $link, string $chave): int
    {
        $ids = array_column($this->lista("SELECT id FROM usuarios WHERE oficina_id = :oficina AND perfil = 'ADMIN' AND ativo = 1 AND id <> :eu",
            ['eu' => (int) Contexto::usuarioId()]), 'id');
        foreach ($ids as $uid) {
            // Mesmo aviso (mesma chave) não entra duas vezes para a mesma pessoa; outros erros continuam aparecendo.
            $this->consultar(
                'INSERT INTO notificacoes (oficina_id, usuario_id, tipo, titulo, mensagem, link, chave_idempotencia)
                 VALUES (:oficina, :u, :tipo, :titulo, :msg, :link, :chave)
                 ON DUPLICATE KEY UPDATE id = id',
                ['u' => (int) $uid, 'tipo' => $tipo, 'titulo' => mb_substr($titulo, 0, 150),
                 'msg' => $mensagem !== null ? mb_substr($mensagem, 0, 500) : null, 'link' => $link, 'chave' => mb_substr($chave, 0, 100)]
            );
        }

        return count($ids);
    }
}
