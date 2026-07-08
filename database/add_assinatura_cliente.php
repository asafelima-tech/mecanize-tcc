<?php

/**
 * Adiciona a coluna `assinatura_cliente` (imagem PNG em base64, capturada
 * num canvas de assinatura na tela) em `orcamentos` e `ordens_servico`.
 *
 * Como rodar:
 *   /c/xampp/php/php.exe database/add_assinatura_cliente.php
 */

require_once __DIR__ . '/../app/core/Database.php';

$pdo = new Database();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function adicionarColuna(PDO $pdo, string $tabela): void
{
    $existe = $pdo->query("SHOW COLUMNS FROM {$tabela} LIKE 'assinatura_cliente'")->fetch();

    if ($existe) {
        echo "Coluna 'assinatura_cliente' já existe em '{$tabela}'.\n";
        return;
    }

    $pdo->exec("ALTER TABLE {$tabela} ADD COLUMN assinatura_cliente MEDIUMTEXT NULL AFTER observacoes");
    echo "Coluna 'assinatura_cliente' adicionada em '{$tabela}'.\n";
}

adicionarColuna($pdo, 'orcamentos');
adicionarColuna($pdo, 'ordens_servico');
