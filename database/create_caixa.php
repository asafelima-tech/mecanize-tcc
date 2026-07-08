<?php

/**
 * Cria a tabela `caixa` (controle de abertura/fechamento de caixa).
 *
 * Cada linha é uma SESSÃO de caixa: registra quem abriu, quando, com qual
 * valor inicial, e — quando encerrada — quem fechou, quando e com qual valor.
 * O caixa pode ser aberto/fechado várias vezes (uma linha por sessão).
 *
 * Como rodar:
 *   /c/xampp/php/php.exe database/create_caixa.php
 *
 * O CLI mysql.exe do XAMPP está quebrado nesta máquina, por isso usamos PDO.
 */

require_once __DIR__ . '/../app/core/Database.php';

$pdo = new Database();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "
CREATE TABLE IF NOT EXISTS caixa (
    id_caixa              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_abertura_id   BIGINT UNSIGNED NOT NULL,
    data_abertura         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valor_abertura        DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    observacao_abertura   VARCHAR(255)        NULL,
    usuario_fechamento_id BIGINT UNSIGNED     NULL,
    data_fechamento       DATETIME            NULL,
    valor_fechamento      DECIMAL(10,2)       NULL,
    observacao_fechamento VARCHAR(255)        NULL,
    status                ENUM('ABERTO','FECHADO') NOT NULL DEFAULT 'ABERTO',
    PRIMARY KEY (id_caixa),
    KEY idx_caixa_status (status),
    CONSTRAINT fk_caixa_usuario_abertura
        FOREIGN KEY (usuario_abertura_id)   REFERENCES usuarios (id_usuario),
    CONSTRAINT fk_caixa_usuario_fechamento
        FOREIGN KEY (usuario_fechamento_id) REFERENCES usuarios (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$pdo->exec($sql);

echo "Tabela 'caixa' criada/verificada com sucesso.\n";

foreach ($pdo->query("SHOW COLUMNS FROM caixa") as $col) {
    echo str_pad($col['Field'], 24) . $col['Type'] . "\n";
}
