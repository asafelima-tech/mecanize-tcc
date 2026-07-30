<?php

/**
 * Recria o banco de teste (mecanize_db_test) com o schema do banco real,
 * SEM copiar nenhum dado.
 *
 * Existe porque o mysql.exe/mysqldump do XAMPP está quebrado nesta máquina
 * (ERROR 1156: caching_sha2_password.dll não encontrado), então o caminho
 * documentado no .env.testing (`mysqldump ... | mysql ...`) não roda. Aqui
 * a cópia do schema é feita via PDO: SHOW CREATE TABLE no banco de origem,
 * CREATE TABLE no de destino.
 *
 * Uso:  php database/create_test_db.php
 *
 * Só cria o que falta. Para forçar a recriação de tudo (descartando o banco
 * de teste inteiro):  php database/create_test_db.php --recriar
 */

require_once __DIR__ . '/../app/core/Env.php';

Env::carregar(__DIR__ . '/../.env');

$host   = env('DB_HOST', 'localhost');
$user   = env('DB_USER', 'root');
$pass   = env('DB_PASS', '');
$origem = env('DB_NAME');

if ($origem === null) {
    fwrite(STDERR, "DB_NAME não definido no .env\n");
    exit(1);
}

$destino = 'mecanize_db_test';

// Trava de segurança: este script só escreve no banco de teste. Se alguém
// apontar o .env pro próprio banco de teste, abortar antes de qualquer DROP.
if ($origem === $destino) {
    fwrite(STDERR, "ABORTADO: o .env aponta para '{$destino}' — a origem não pode ser o próprio banco de teste.\n");
    exit(1);
}

$recriar = in_array('--recriar', $argv, true);

$pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

if ($recriar) {
    echo "Descartando {$destino}...\n";
    $pdo->exec("DROP DATABASE IF EXISTS `{$destino}`");
}

$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$destino}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "Banco {$destino} pronto.\n";

$pdo->exec("USE `{$origem}`");
$tabelas = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);

// FKs desligadas: as tabelas são criadas em ordem alfabética, não em ordem
// de dependência, então uma FK pode apontar pra tabela que ainda não existe.
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

$criadas = 0;
$puladas = 0;

foreach ($tabelas as $tabela) {
    $pdo->exec("USE `{$origem}`");
    $ddl = $pdo->query("SHOW CREATE TABLE `{$tabela}`")->fetch(PDO::FETCH_ASSOC)['Create Table'];

    $pdo->exec("USE `{$destino}`");

    $existe = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tabela))->fetchColumn();

    if ($existe !== false) {
        echo "  = {$tabela} (já existe)\n";
        $puladas++;
        continue;
    }

    // AUTO_INCREMENT do banco real não faz sentido numa cópia vazia.
    $ddl = preg_replace('/\s+AUTO_INCREMENT=\d+/', '', $ddl);

    $pdo->exec($ddl);
    echo "  + {$tabela}\n";
    $criadas++;
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "\n{$criadas} tabela(s) criada(s), {$puladas} já existente(s).\n";
echo "Nenhum dado copiado — o banco de teste fica vazio de propósito.\n";
