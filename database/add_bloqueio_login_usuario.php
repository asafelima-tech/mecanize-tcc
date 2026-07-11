<?php

/**
 * Adiciona colunas de controle de tentativas de login em `usuarios`, pra
 * bloquear temporariamente uma conta depois de várias senhas erradas
 * seguidas (não existia nenhum limite antes — dava pra tentar senha
 * ilimitadamente contra um e-mail conhecido).
 *
 * Como rodar:
 *   /c/xampp/php/php.exe database/add_bloqueio_login_usuario.php
 */

require_once __DIR__ . '/../app/core/Database.php';

$pdo = new Database();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$colunas = [
    'tentativas_login' => "ALTER TABLE usuarios ADD COLUMN tentativas_login INT NOT NULL DEFAULT 0 AFTER senha",
    'bloqueado_ate'     => "ALTER TABLE usuarios ADD COLUMN bloqueado_ate DATETIME NULL AFTER tentativas_login",
];

foreach ($colunas as $nome => $sql) {
    $existe = $pdo->query("SHOW COLUMNS FROM usuarios LIKE '{$nome}'")->fetch();

    if ($existe) {
        echo "Coluna '{$nome}' já existe em 'usuarios'.\n";
        continue;
    }

    $pdo->exec($sql);
    echo "Coluna '{$nome}' adicionada em 'usuarios'.\n";
}
