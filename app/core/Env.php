<?php

/**
 * Carregador simples de variáveis de ambiente a partir de um arquivo .env
 * na raiz do projeto (linhas "CHAVE=valor", "#" para comentário). Evita
 * depender de uma lib externa (vlucas/phpdotenv) só pra isso.
 *
 * ATENÇÃO — por que NÃO usa putenv():
 * o PHP deste projeto roda dentro do Apache em modo multi-thread (ZTS). O ambiente
 * do processo é UM só, compartilhado por todas as threads, e putenv() não é seguro
 * contra leitura simultânea: com três requisições chegando juntas, uma thread lia o
 * ambiente enquanto outra o reescrevia e recebia "DB_HOST não definida" — erro 500
 * intermitente, que só aparecia com mais de um usuário (ou uma página disparando
 * vários fetch ao mesmo tempo). Agora os valores do .env ficam numa propriedade
 * estática, que no ZTS é própria de cada requisição.
 *
 * Precedência: variável de ambiente REAL (ex.: DB_NAME=mecanize_db_test php …) vence
 * o arquivo .env, como sempre foi.
 */
class Env
{
    private static bool $carregado = false;

    /** @var array<string,string> */
    private static array $valores = [];

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

            self::$valores[$chave] = $valor;
        }

        self::$carregado = true;
    }

    public static function obter(string $chave, $padrao = null)
    {
        // Só LEITURA do ambiente real (thread-safe enquanto ninguém chama putenv).
        $real = getenv($chave);

        if ($real !== false) {
            return $real;
        }

        return self::$valores[$chave] ?? $padrao;
    }
}

function env(string $chave, $padrao = null)
{
    return Env::obter($chave, $padrao);
}
