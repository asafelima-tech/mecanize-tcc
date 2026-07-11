<?php

/**
 * Adiciona `valor_esperado` em `caixa` — o valor que o sistema calculava
 * (abertura + entradas em dinheiro − saídas) NO MOMENTO do fechamento, pra
 * poder comparar depois com o que foi realmente contado (valor_fechamento).
 *
 * Sem essa coluna, o histórico de caixa (listCaixa.php) só conseguia mostrar
 * "fechamento − abertura" (variação líquida do dia) como "Diferença", que não
 * é a mesma coisa que "sobra/falta na contagem" — a tela de fechamento ao
 * vivo já calcula certo (contado − esperado), mas não guardava o esperado
 * pra o histórico reaproveitar depois.
 *
 * Sessões já fechadas antes desta coluna existir ficam com valor_esperado
 * NULL — o histórico trata isso como "não disponível", não como zero.
 *
 * Como rodar:
 *   /c/xampp/php/php.exe database/add_valor_esperado_caixa.php
 */

require_once __DIR__ . '/../app/core/Database.php';

$pdo = new Database();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$existe = $pdo->query("SHOW COLUMNS FROM caixa LIKE 'valor_esperado'")->fetch();

if ($existe) {
    echo "Coluna 'valor_esperado' já existe em 'caixa'.\n";
} else {
    $pdo->exec("ALTER TABLE caixa ADD COLUMN valor_esperado DECIMAL(10,2) NULL AFTER valor_fechamento");
    echo "Coluna 'valor_esperado' adicionada em 'caixa'.\n";
}
