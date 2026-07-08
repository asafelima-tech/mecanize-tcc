<?php

/**
 * Adiciona `token_rastreio` (string aleatória, usada na URL pública de
 * rastreamento) em `orcamentos` e `ordens_servico`, e preenche as linhas já
 * existentes que ainda não têm token.
 *
 * Como rodar:
 *   /c/xampp/php/php.exe database/add_token_rastreio.php
 */

require_once __DIR__ . '/../app/core/Database.php';

$pdo = new Database();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function adicionarColuna(PDO $pdo, string $tabela): void
{
    $existe = $pdo->query("SHOW COLUMNS FROM {$tabela} LIKE 'token_rastreio'")->fetch();

    if (!$existe) {
        $pdo->exec("ALTER TABLE {$tabela} ADD COLUMN token_rastreio VARCHAR(48) NULL");
        echo "Coluna 'token_rastreio' adicionada em '{$tabela}'.\n";
    } else {
        echo "Coluna 'token_rastreio' já existe em '{$tabela}'.\n";
    }

    $indiceExiste = $pdo->query("SHOW INDEX FROM {$tabela} WHERE Key_name = 'uq_{$tabela}_token_rastreio'")->fetch();
    if (!$indiceExiste) {
        $pdo->exec("ALTER TABLE {$tabela} ADD UNIQUE KEY uq_{$tabela}_token_rastreio (token_rastreio)");
        echo "Índice único adicionado em '{$tabela}'.\n";
    }
}

function preencherTokens(PDO $pdo, string $tabela, string $coluna_id): void
{
    $stmt = $pdo->query("SELECT {$coluna_id} FROM {$tabela} WHERE token_rastreio IS NULL");
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $upd = $pdo->prepare("UPDATE {$tabela} SET token_rastreio = ? WHERE {$coluna_id} = ?");
    foreach ($ids as $id) {
        $upd->execute([bin2hex(random_bytes(24)), $id]);
    }

    echo count($ids) . " token(s) gerado(s) em '{$tabela}'.\n";
}

adicionarColuna($pdo, 'orcamentos');
adicionarColuna($pdo, 'ordens_servico');

preencherTokens($pdo, 'orcamentos', 'id_orcamento');
preencherTokens($pdo, 'ordens_servico', 'id_ordem_servico');
