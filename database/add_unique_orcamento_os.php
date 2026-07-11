<?php

/**
 * Backstop pra Orcamento::aprovar(): mesmo com o SELECT ... FOR UPDATE que já
 * serializa aprovações concorrentes do mesmo orçamento, um índice único no
 * banco garante que o próprio MySQL rejeite uma segunda Ordem de Serviço para
 * o mesmo orcamento_id, caso o lock falhe por algum motivo (ex.: uma conexão
 * fora da transação, uma chamada direta ao model). NULL continua permitido
 * (OS que não vieram de orçamento) — MySQL/InnoDB não conta múltiplos NULLs
 * como duplicata num índice único.
 *
 * Como rodar:
 *   /c/xampp/php/php.exe database/add_unique_orcamento_os.php
 */

require_once __DIR__ . '/../app/core/Database.php';

$pdo = new Database();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$existe = $pdo->query("SHOW INDEX FROM ordens_servico WHERE Key_name = 'uq_ordens_servico_orcamento_id'")->fetch();

if ($existe) {
    echo "Índice 'uq_ordens_servico_orcamento_id' já existe.\n";
} else {
    $pdo->exec("ALTER TABLE ordens_servico ADD UNIQUE KEY uq_ordens_servico_orcamento_id (orcamento_id)");
    echo "Índice único 'uq_ordens_servico_orcamento_id' adicionado em ordens_servico(orcamento_id).\n";
}
