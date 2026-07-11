<?php

/**
 * Token CSRF único por sessão, validado em toda requisição POST pelo
 * front-controller (public/index.php). O app não tinha nenhuma proteção
 * contra CSRF antes disso — qualquer POST dependia só da sessão ativa.
 */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function valido(?string $tokenRecebido): bool
    {
        return !empty($_SESSION['csrf_token'])
            && !empty($tokenRecebido)
            && hash_equals($_SESSION['csrf_token'], $tokenRecebido);
    }
}

/**
 * Helper pra views: <?= csrf_field() ?> dentro de qualquer <form method="post">.
 * Forms que não chamam isso explicitamente ainda são cobertos pelo
 * public/assets/js/csrf.js, que injeta o campo automaticamente no submit —
 * mas usar o helper é preferível (funciona mesmo com JS desabilitado).
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(Csrf::token(), ENT_QUOTES) . '">';
}
