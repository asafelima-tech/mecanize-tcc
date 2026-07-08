<?php

/**
 * Carregador simples de variáveis de ambiente a partir de um arquivo .env
 * na raiz do projeto (linhas "CHAVE=valor", "#" para comentário). Evita
 * depender de uma lib externa (vlucas/phpdotenv) só pra isso.
 */
class Env
{
    private static bool $carregado = false;

    public static function carregar(string $caminho): void
    {
        if (self::$carregado || !is_file($caminho)) {
            self::$carregado = true;
            return;
        }

        foreach (file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
            $linha = trim($linha);

            if ($linha === '' || str_starts_with($linha, '#') || !str_contains($linha, '=')) {
                continue;
            }

            [$chave, $valor] = explode('=', $linha, 2);
            $chave = trim($chave);
            $valor = trim($valor);

            // Remove aspas simples/duplas envolvendo o valor, se houver.
            if (strlen($valor) >= 2 && $valor[0] === $valor[-1] && in_array($valor[0], ['"', "'"], true)) {
                $valor = substr($valor, 1, -1);
            }

            if (getenv($chave) === false) {
                putenv("{$chave}={$valor}");
                $_ENV[$chave] = $valor;
            }
        }

        self::$carregado = true;
    }
}

function env(string $chave, $padrao = null)
{
    $valor = getenv($chave);
    return $valor === false ? $padrao : $valor;
}
