<?php

/**
 * Quem está usando o sistema NESTA requisição: usuário, perfil e oficina.
 *
 * É a única fonte do `oficina_id` usado nas consultas. Ele vem da conta do usuário logado (relida do
 * banco a cada requisição pelo front controller), NUNCA de formulário, URL ou cookie — então não há
 * como um usuário "pedir" os dados de outra oficina.
 */
final class Contexto
{
    private static ?array $usuario = null;
    private static ?array $oficina = null;

    /**
     * @param array $usuario linha de usuarios (id, oficina_id, nome, email, perfil, ...)
     * @param array|null $oficina linha de oficinas (null para o perfil PLATAFORMA)
     */
    public static function definir(array $usuario, ?array $oficina): void
    {
        if ($usuario['perfil'] !== 'PLATAFORMA' && ($oficina === null || (int) $oficina['id'] !== (int) $usuario['oficina_id'])) {
            throw new LogicException('Usuário de oficina sem a oficina correspondente.');
        }

        self::$usuario = $usuario;
        self::$oficina = $oficina;
    }

    public static function limpar(): void
    {
        self::$usuario = null;
        self::$oficina = null;
    }

    public static function logado(): bool
    {
        return self::$usuario !== null;
    }

    public static function usuario(): ?array
    {
        return self::$usuario;
    }

    public static function usuarioId(): ?int
    {
        return self::$usuario ? (int) self::$usuario['id'] : null;
    }

    public static function perfil(): ?string
    {
        return self::$usuario['perfil'] ?? null;
    }

    public static function oficina(): ?array
    {
        return self::$oficina;
    }

    /**
     * Oficina da requisição. Lança exceção se não houver — uma consulta de dados de oficina feita
     * sem oficina definida é defeito de programação e não pode "devolver tudo".
     */
    public static function oficinaId(): int
    {
        if (self::$oficina === null) {
            throw new LogicException('Nenhuma oficina definida para esta requisição.');
        }

        return (int) self::$oficina['id'];
    }

    /** Barra gravações quando a licença da oficina não permite (somente leitura). */
    public static function exigirEscrita(): void
    {
        if (self::$oficina !== null && !Licenca::permiteEscrita(self::$oficina)) {
            throw new LicencaBloqueada(Licenca::mensagem(self::$oficina));
        }
    }
}

/** Tentativa de gravar com a licença vencida, suspensa ou cancelada. */
final class LicencaBloqueada extends RuntimeException
{
}
