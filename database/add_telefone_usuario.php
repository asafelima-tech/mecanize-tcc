<?php

/**
 * Adiciona a coluna `telefone` em `usuarios` — o campo já existia na tela de
 * perfil (app/views/Usuarios/perfil.php) mas era decorativo: o formulário
 * enviava o valor e o controller simplesmente descartava, porque a coluna
 * nunca existiu.
 *
 * Como rodar:
 *   /c/xampp/php/php.exe database/add_telefone_usuario.php
 */

require_once __DIR__ . '/../app/core/Database.php';

$pdo = new Database();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$existe = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'telefone'")->fetch();

if ($existe) {
    echo "Coluna 'telefone' já existe em 'usuarios'.\n";
} else {
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN telefone VARCHAR(20) NULL AFTER email");
    echo "Coluna 'telefone' adicionada em 'usuarios'.\n";
}
