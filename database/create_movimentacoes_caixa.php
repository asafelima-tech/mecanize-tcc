<?php

/**
 * Cria a tabela `movimentacoes_caixa` (livro-caixa: lançamentos intradiários).
 *
 * Cada linha é uma ENTRADA ou SAÍDA registrada dentro de uma SESSÃO de caixa
 * (tabela `caixa`). As entradas guardam a forma de pagamento (dinheiro, pix,
 * cartão, boleto); as saídas guardam a categoria (peças, banco, outro).
 *
 * O "caixa atual" (dinheiro físico na gaveta) é calculado em PHP a partir
 * destes lançamentos: valor_abertura + entradas em DINHEIRO − todas as saídas.
 * PIX/cartão/boleto NÃO entram nesse cálculo (não são dinheiro na gaveta).
 *
 * Como rodar:
 *   /c/xampp/php/php.exe database/create_movimentacoes_caixa.php
 *
 * O CLI mysql.exe do XAMPP está quebrado nesta máquina, por isso usamos PDO.
 */

require_once __DIR__ . '/../app/core/Database.php';

$pdo = new Database();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "
CREATE TABLE IF NOT EXISTS movimentacoes_caixa (
    id_movimentacao   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    caixa_id          BIGINT UNSIGNED NOT NULL,
    tipo              ENUM('ENTRADA','SAIDA') NOT NULL,
    forma_pagamento   ENUM('DINHEIRO','PIX','CARTAO','BOLETO')    NULL,
    categoria         ENUM('OS','VENDA','PECAS','BANCO','OUTRO')  NULL,
    valor             DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    descricao         VARCHAR(255)        NULL,
    usuario_id        BIGINT UNSIGNED NOT NULL,
    origem            ENUM('MANUAL','OS') NOT NULL DEFAULT 'MANUAL',
    ordem_servico_id  BIGINT UNSIGNED     NULL,
    data_movimentacao DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_movimentacao),
    KEY idx_mov_caixa (caixa_id),
    KEY idx_mov_data (data_movimentacao),
    CONSTRAINT fk_mov_caixa
        FOREIGN KEY (caixa_id)   REFERENCES caixa (id_caixa),
    CONSTRAINT fk_mov_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$pdo->exec($sql);

echo "Tabela 'movimentacoes_caixa' criada/verificada com sucesso.\n";

foreach ($pdo->query("SHOW COLUMNS FROM movimentacoes_caixa") as $col) {
    echo str_pad($col['Field'], 20) . $col['Type'] . "\n";
}
