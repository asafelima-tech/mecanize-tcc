<?php

/**
 * Carrega o .env.testing ANTES de qualquer outra coisa. app/core/Env.php
 * só lê o .env real na primeira chamada de Env::carregar() de todo o
 * processo (guarda estática) — como esta é a primeira chamada (roda antes
 * de qualquer model instanciar Database, que também chama carregar() com o
 * caminho do .env real), esses valores de teste "ganham" e todo o resto do
 * processo do PHPUnit fica preso neles. Sem isso, os testes rodariam contra
 * o banco de produção de verdade.
 */
require_once __DIR__ . '/../app/core/Env.php';
Env::carregar(__DIR__ . '/../.env.testing');

if (getenv('DB_NAME') !== 'mecanize_db_test') {
    fwrite(STDERR, "ABORTADO: DB_NAME não é 'mecanize_db_test' — os testes só podem rodar contra o banco de teste, nunca produção.\n");
    exit(1);
}

require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Validator.php';
require_once __DIR__ . '/../app/core/Csrf.php';

// Precisa iniciar ANTES do PHPUnit imprimir qualquer coisa (o "." de cada
// teste vai pro stdout na hora) — session_start() falha com "headers already
// sent" se chamado depois que algum output já rolou no processo.
session_start();
