<?php

/**
 * UMA conexão por requisição.
 *
 * Na v1 cada model fazia `new Database()` e abria a sua própria conexão. Duas conexões não enxergam
 * o que a outra ainda não gravou, e uma transação de uma travava a outra — por isso dinheiro e
 * estoque precisavam receber o PDO por parâmetro. Aqui todo mundo usa a mesma conexão.
 *
 * Banco usado: DB_NAME_V2 do .env; sem ele, o DB_NAME com "_v2" no fim (mecanize_db → mecanize_db_v2).
 * Assim a v2 roda ao lado da v1 sem mexer no .env, e continua certa depois da troca.
 */
final class Banco
{
    private static ?PDO $conexao = null;

    public static function nome(): string
    {
        $explicito = env('DB_NAME_V2');
        if ($explicito !== null && $explicito !== '') {
            return (string) $explicito;
        }

        $nome = (string) (env('DB_NAME') ?? '');
        if ($nome === '') {
            throw new RuntimeException('Configuração ausente: DB_NAME não definida no .env');
        }

        return str_ends_with($nome, '_v2') ? $nome : $nome . '_v2';
    }

    public static function conexao(): PDO
    {
        if (self::$conexao === null) {
            foreach (['DB_HOST', 'DB_USER'] as $chave) {
                if (env($chave) === null) {
                    throw new RuntimeException("Configuração ausente: {$chave} não definida no .env");
                }
            }

            self::$conexao = new PDO(
                'mysql:host=' . env('DB_HOST') . ';dbname=' . self::nome() . ';charset=utf8mb4',
                (string) env('DB_USER'),
                (string) env('DB_PASS', ''),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
            self::$conexao->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '-03:00'");
        }

        return self::$conexao;
    }

    /** Só para os testes: troca/zera a conexão compartilhada. */
    public static function definir(?PDO $pdo): void
    {
        self::$conexao = $pdo;
    }
}
