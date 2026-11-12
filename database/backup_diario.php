<?php

/**
 * Backup diário do banco, para o Agendador de Tarefas do Windows ("Mecanize - Backup diario").
 *
 * Roda database/backup_pdo.php, confere que o arquivo saiu inteiro, guarda uma cópia em
 * database/backups/diario/ e mantém só os últimos DIAS_GUARDADOS dias DESSA pasta. Os backups feitos
 * à mão em database/backups/ nunca são apagados por aqui.
 *
 * Resultado de cada execução em database/backups/diario/backup_diario.log. Sai com código 1 se o banco
 * estiver parado ou o arquivo vier incompleto (o Agendador mostra a falha em vez de fingir que deu certo).
 *
 *   /c/xampp/php/php.exe database/backup_diario.php
 */

const DIAS_GUARDADOS = 14;

$base  = __DIR__ . '/backups';
$pasta = $base . '/diario';

if (!is_dir($pasta) && !mkdir($pasta, 0777, true) && !is_dir($pasta)) {
    fwrite(STDERR, "Não foi possível criar {$pasta}\n");
    exit(1);
}

$log = static function (string $mensagem) use ($pasta): void {
    file_put_contents($pasta . '/backup_diario.log', date('Y-m-d H:i:s') . ' ' . $mensagem . "\n", FILE_APPEND);
    echo $mensagem . "\n";
};

$antes = glob($base . '/*.sql') ?: [];

exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/backup_pdo.php') . ' 2>&1', $saida, $codigo);

$novos   = array_values(array_diff(glob($base . '/*.sql') ?: [], $antes));
$arquivo = $novos ? end($novos) : null;

// O backup_pdo.php termina o arquivo com "SET FOREIGN_KEY_CHECKS = 1;": se não estiver lá, o dump foi cortado.
$inteiro = $arquivo && filesize($arquivo) > 1024 && str_ends_with(rtrim((string) file_get_contents($arquivo)), 'SET FOREIGN_KEY_CHECKS = 1;');

if ($codigo !== 0 || !$inteiro) {
    if ($arquivo) {
        @unlink($arquivo);
    }

    $log('FALHA no backup (código ' . $codigo . '): ' . trim(implode(' | ', array_slice($saida, -3))));
    exit(1);
}

$destino = $pasta . '/' . basename($arquivo);

if (!rename($arquivo, $destino)) {
    $log('FALHA ao guardar ' . basename($arquivo) . ' em backups/diario/');
    exit(1);
}

$apagados = 0;

foreach (glob($pasta . '/*.sql') ?: [] as $velho) {
    if ($velho !== $destino && filemtime($velho) < time() - DIAS_GUARDADOS * 86400) {
        $apagados += unlink($velho) ? 1 : 0;
    }
}

$log('OK ' . basename($destino) . ' (' . number_format(filesize($destino)) . ' bytes)' . ($apagados ? ", {$apagados} antigo(s) removido(s)" : ''));
exit(0);
