<?php

/**
 * Desfaz a migração v2: apaga SOMENTE o banco novo (<DB_NAME>_v2).
 * O banco antigo (DB_NAME do .env) nunca é tocado.
 *
 * USO: C:\xampp\php\php.exe database\v2\desfazer.php --confirmar
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/core/Env.php';
Env::carregar(__DIR__ . '/../../.env');

$origem  = env('DB_NAME');
$destino = env('DB_DESTINO', $origem . '_v2');

if (!in_array('--confirmar', $argv, true)) {
    echo "Isto apaga o banco '{$destino}' (o '{$origem}' não é tocado). Rode de novo com --confirmar.\n";
    exit(1);
}
if (!preg_match('/^\w+_v2$/', (string) $destino) || $destino === $origem) {
    fwrite(STDERR, "ABORTADO: só apago bancos cujo nome termina em _v2 (recebido '{$destino}').\n");
    exit(1);
}

$pdo = new PDO('mysql:host=' . env('DB_HOST') . ';charset=utf8mb4', env('DB_USER'), env('DB_PASS', ''), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec("DROP DATABASE IF EXISTS `{$destino}`");
echo "Banco '{$destino}' apagado. '{$origem}' continua como estava.\n";
