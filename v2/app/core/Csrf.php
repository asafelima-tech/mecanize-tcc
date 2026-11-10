<?php

/** Token CSRF por sessão; conferido em TODO POST pelo front controller. */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function valido(?string $recebido): bool
    {
        return !empty($_SESSION['csrf_token']) && is_string($recebido) && $recebido !== ''
            && hash_equals($_SESSION['csrf_token'], $recebido);
    }
}
