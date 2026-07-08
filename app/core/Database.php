<?php
require_once __DIR__ . '/Env.php';

class Database extends PDO {
    public function __construct() {
        Env::carregar(__DIR__ . '/../../.env');


        $host     = env('DB_HOST', 'localhost');
        $dbname   = env('DB_NAME', 'mecanize_db');
        $username = env('DB_USER', 'root');
        $password = env('DB_PASS', '');

        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        parent::__construct($dsn, $username, $password);

        $this->exec("SET NAMES utf8mb4");
        $this->exec("SET CHARACTER SET utf8mb4");
        $this->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
    }
}