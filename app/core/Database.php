<?php
require_once __DIR__ . '/Env.php';

class Database extends PDO {
    public function __construct() {
        Env::carregar(__DIR__ . '/../../.env');


        // Sem fallback pra 'root'/sem senha/'mecanize_db': se o .env estiver
        // ausente ou incompleto, falha explicitamente em vez de conectar
        // silenciosamente num banco local que pode nem ser o certo.
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER'] as $chave) {
            if (env($chave) === null) {
                throw new \RuntimeException("Configuração ausente: {$chave} não definida no .env");
            }
        }

        $host     = env('DB_HOST');
        $dbname   = env('DB_NAME');
        $username = env('DB_USER');
        $password = env('DB_PASS', '');

        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        parent::__construct($dsn, $username, $password);

        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->exec("SET NAMES utf8mb4");
        $this->exec("SET CHARACTER SET utf8mb4");
        $this->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
    }
}