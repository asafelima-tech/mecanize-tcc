<?php

/**
 * Prepara um banco SÓ de teste (<banco v2>_teste), criado do zero a cada execução a partir de
 * database/v2/schema.sql, com duas oficinas (A e B). Nunca toca no mecanize_db nem no mecanize_db_v2.
 *
 * Rodar (na raiz do projeto, no XAMPP):
 *   C:\xampp\php\php.exe vendor\bin\phpunit -c v2\phpunit.xml
 */

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$bancoTeste = (str_ends_with(Banco::nome(), '_teste') ? Banco::nome() : Banco::nome() . '_teste');
if (!preg_match('/^\w+_teste$/', $bancoTeste)) {
    fwrite(STDERR, "Banco de teste com nome inesperado: {$bancoTeste}\n");
    exit(1);
}

$pdo = new PDO('mysql:host=' . env('DB_HOST') . ';charset=utf8mb4', (string) env('DB_USER'), (string) env('DB_PASS', ''), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec("DROP DATABASE IF EXISTS `{$bancoTeste}`");
$pdo->exec("CREATE DATABASE `{$bancoTeste}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$bancoTeste}`");

$schema = (string) file_get_contents(PROJETO_RAIZ . '/database/v2/schema.sql');
$schema = preg_replace(['/^\s*--.*$/m', '/\s--\s.*$/m'], '', $schema);
foreach (array_filter(array_map('trim', explode(";\n", $schema))) as $sql) {
    $pdo->exec($sql);
}

putenv("DB_NAME_V2={$bancoTeste}");
Banco::definir(null);

require __DIR__ . '/Cenario.php';
