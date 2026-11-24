<?php

/**
 * Migração v2 de 24/11/2026 — área do TÉCNICO (execução, validação, materiais e solicitações).
 *
 *   1. ordens_servico.status ganha AGUARDANDO_VALIDACAO (o técnico terminou; o ADMIN ainda não validou).
 *   2. ordens_servico ganha as colunas da execução: iniciada, enviada, relato, devolvida e motivo.
 *   3. Tabela os_materiais: peças que o técnico informou ter usado (baixa só na validação).
 *   4. Tabela solicitacoes_material: pedidos e avisos do técnico ao ADMIN.
 *
 * O mesmo conteúdo já está em database/v2/schema.sql (bancos novos e os testes nascem com ele).
 * Idempotente: pergunta ao banco antes de cada passo, então pode rodar quantas vezes for preciso.
 * Faça backup antes (mysqldump do banco v2).
 *
 * USO (na raiz do projeto, no XAMPP):
 *   C:\Users\SENAI_2025\Progs\xampp\php\php.exe database\v2\migracao_2026_11_24_tecnico.php
 * Outro banco v2 (ex.: cópia): DB_NAME_V2=outro_v2 ...php database\v2\migracao_2026_11_24_tecnico.php
 */

declare(strict_types=1);

require __DIR__ . '/../../v2/app/bootstrap.php';

$banco = Banco::nome();
if (!preg_match('/^\w+_v2(_\w+)?$/', $banco)) {
    fwrite(STDERR, "ABORTADO: '{$banco}' não parece um banco da v2.\n");
    exit(1);
}

$pdo = Banco::conexao();
echo "Banco: {$banco}\n";
$feitas = 0;

$coluna = function (string $tabela, string $col) use ($pdo): ?array {
    $s = $pdo->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $s->execute([$tabela, $col]);
    $r = $s->fetch();

    return $r ?: null;
};
$tabela = function (string $nome) use ($pdo): bool {
    $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $s->execute([$nome]);

    return (int) $s->fetchColumn() > 0;
};
$passo = function (string $texto, bool $precisa, string $sql) use ($pdo, &$feitas): void {
    if (!$precisa) {
        echo "  = {$texto} (já estava)\n";
        return;
    }
    $pdo->exec($sql);
    $feitas++;
    echo "  + {$texto}\n";
};

// 1. Nova situação da OS
$tipo = (string) ($coluna('ordens_servico', 'status')['COLUMN_TYPE'] ?? '');
$passo('situação AGUARDANDO_VALIDACAO na OS', !str_contains($tipo, "'AGUARDANDO_VALIDACAO'"),
    "ALTER TABLE ordens_servico MODIFY status
       ENUM('ORCAMENTO','APROVADA','EM_EXECUCAO','AGUARDANDO_PECA','AGUARDANDO_VALIDACAO','PRONTA','ENTREGUE','RECUSADA','CANCELADA')
       NOT NULL DEFAULT 'ORCAMENTO'");

// 2. Colunas da execução
$novas = [
    'execucao_iniciada_em'      => 'DATETIME NULL AFTER conferido_em',
    'execucao_enviada_em'       => 'DATETIME NULL AFTER execucao_iniciada_em',
    'execucao_observacao'       => 'TEXT NULL AFTER execucao_enviada_em',
    'execucao_devolvida_em'     => 'DATETIME NULL AFTER execucao_observacao',
    'execucao_devolucao_motivo' => 'VARCHAR(255) NULL AFTER execucao_devolvida_em',
];
foreach ($novas as $nome => $def) {
    $passo("coluna ordens_servico.{$nome}", $coluna('ordens_servico', $nome) === null, "ALTER TABLE ordens_servico ADD COLUMN {$nome} {$def}");
}

// 3 e 4. Tabelas novas: o CREATE vem do próprio schema.sql, para não existirem duas versões da estrutura.
$schema = (string) file_get_contents(__DIR__ . '/schema.sql');
$schema = preg_replace(['/^\s*--.*$/m', '/\s--\s.*$/m'], '', $schema);
foreach (['os_materiais', 'solicitacoes_material'] as $nova) {
    if (!preg_match('/CREATE TABLE ' . $nova . ' \(.*?\) ENGINE=InnoDB[^;]*;/s', $schema, $m)) {
        fwrite(STDERR, "ABORTADO: CREATE TABLE {$nova} não encontrado no schema.sql.\n");
        exit(1);
    }
    $passo("tabela {$nova}", !$tabela($nova), rtrim($m[0], ';'));
}

echo $feitas ? "\nPronto: {$feitas} alteração(ões).\n" : "\nNada a fazer: o banco já estava atualizado.\n";
