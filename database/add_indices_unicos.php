<?php

/**
 * Backstop no banco pras checagens de duplicidade que hoje só existem a
 * nível de aplicação (SELECT antes do INSERT em Cliente/Veiculo) — sem
 * índice único de verdade, duas requisições quase simultâneas (duplo clique,
 * retry de rede) podiam ambas passar na checagem e criar registros duplicados.
 *
 * `veiculos.placa_normalizada` não tem conceito de "inativo" — índice único
 * direto na coluna.
 *
 * `clientes` (email/telefone/cpf/cnpj) PRECISA continuar permitindo
 * duplicidade entre um cliente ATIVO e um INATIVO (comportamento já existente
 * e intencional — reativar um cliente antigo não pode ser bloqueado por um
 * cliente novo que reusou o mesmo contato). Um UNIQUE KEY direto na coluna
 * quebraria isso. Solução: uma coluna gerada (STORED) que só reproduz o
 * valor normalizado quando `ativo = 1` e o campo não está vazio — senão vira
 * NULL, e o InnoDB não conta múltiplos NULLs como duplicata num índice único.
 * O índice único é criado em cima dessa coluna gerada, nunca na coluna real.
 *
 * Como rodar:
 *   /c/xampp/php/php.exe database/add_indices_unicos.php
 *
 * Pré-requisito: rodar isso só depois de resolver duplicatas já existentes
 * nos dados (o script AVISA e para, sem alterar nada, se achar alguma —
 * ver seção de checagem abaixo).
 */

require_once __DIR__ . '/../app/core/Database.php';

$pdo = new Database();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function colunaExiste(PDO $pdo, string $tabela, string $coluna): bool
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$tabela` LIKE ?");
    $stmt->execute([$coluna]);
    return (bool) $stmt->fetch();
}

function indiceExiste(PDO $pdo, string $tabela, string $indice): bool
{
    $stmt = $pdo->prepare("SHOW INDEX FROM `$tabela` WHERE Key_name = ?");
    $stmt->execute([$indice]);
    return (bool) $stmt->fetch();
}

// ── 1) Checagem de duplicatas já existentes — para com uma mensagem clara
//        em vez de deixar o ALTER TABLE falhar com um erro genérico do MySQL. ──
$checagens = [
    'e-mail'   => "SELECT LOWER(TRIM(email)) v, COUNT(*) c FROM clientes WHERE ativo=1 AND email IS NOT NULL AND TRIM(email)<>'' GROUP BY v HAVING c>1",
    'telefone' => "SELECT REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone,'(',''),')',''),'-',''),' ',''),'.',''),'+','') v, COUNT(*) c FROM clientes WHERE ativo=1 AND telefone IS NOT NULL GROUP BY v HAVING c>1 AND v<>''",
    'CPF'      => "SELECT REPLACE(REPLACE(cpf,'.',''),'-','') v, COUNT(*) c FROM clientes WHERE ativo=1 AND cpf IS NOT NULL GROUP BY v HAVING c>1 AND v<>''",
    'CNPJ'     => "SELECT REPLACE(REPLACE(REPLACE(cnpj,'.',''),'/',''),'-','') v, COUNT(*) c FROM clientes WHERE ativo=1 AND cnpj IS NOT NULL GROUP BY v HAVING c>1 AND v<>''",
    'placa'    => "SELECT placa_normalizada v, COUNT(*) c FROM veiculos GROUP BY v HAVING c>1",
];

$temDuplicata = false;
foreach ($checagens as $nome => $sql) {
    $dups = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    if ($dups) {
        $temDuplicata = true;
        echo "ATENÇÃO: duplicata de {$nome} encontrada — resolva antes de rodar este script:\n";
        print_r($dups);
    }
}

if ($temDuplicata) {
    echo "Nenhum índice foi criado. Resolva as duplicatas acima e rode de novo.\n";
    exit(1);
}

// ── 2) Colunas geradas + índices únicos em `clientes` ──
$colunasGeradas = [
    'uniq_email' => "ALTER TABLE clientes ADD COLUMN uniq_email VARCHAR(255)
        GENERATED ALWAYS AS (
            CASE WHEN ativo = 1 AND email IS NOT NULL AND TRIM(email) <> ''
                 THEN LOWER(TRIM(email)) ELSE NULL END
        ) STORED",
    'uniq_telefone' => "ALTER TABLE clientes ADD COLUMN uniq_telefone VARCHAR(20)
        GENERATED ALWAYS AS (
            CASE WHEN ativo = 1 AND telefone IS NOT NULL
                 AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone,'(',''),')',''),'-',''),' ',''),'.',''),'+','') <> ''
                 THEN REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone,'(',''),')',''),'-',''),' ',''),'.',''),'+','')
                 ELSE NULL END
        ) STORED",
    'uniq_cpf' => "ALTER TABLE clientes ADD COLUMN uniq_cpf VARCHAR(20)
        GENERATED ALWAYS AS (
            CASE WHEN ativo = 1 AND cpf IS NOT NULL AND REPLACE(REPLACE(cpf,'.',''),'-','') <> ''
                 THEN REPLACE(REPLACE(cpf,'.',''),'-','') ELSE NULL END
        ) STORED",
    'uniq_cnpj' => "ALTER TABLE clientes ADD COLUMN uniq_cnpj VARCHAR(20)
        GENERATED ALWAYS AS (
            CASE WHEN ativo = 1 AND cnpj IS NOT NULL AND REPLACE(REPLACE(REPLACE(cnpj,'.',''),'/',''),'-','') <> ''
                 THEN REPLACE(REPLACE(REPLACE(cnpj,'.',''),'/',''),'-','') ELSE NULL END
        ) STORED",
];

foreach ($colunasGeradas as $coluna => $sql) {
    if (colunaExiste($pdo, 'clientes', $coluna)) {
        echo "Coluna '{$coluna}' já existe em 'clientes'.\n";
        continue;
    }
    $pdo->exec($sql);
    echo "Coluna gerada '{$coluna}' adicionada em 'clientes'.\n";
}

$indicesClientes = [
    'uq_clientes_email_ativo'    => 'uniq_email',
    'uq_clientes_telefone_ativo' => 'uniq_telefone',
    'uq_clientes_cpf_ativo'      => 'uniq_cpf',
    'uq_clientes_cnpj_ativo'     => 'uniq_cnpj',
];

foreach ($indicesClientes as $indice => $coluna) {
    if (indiceExiste($pdo, 'clientes', $indice)) {
        echo "Índice '{$indice}' já existe.\n";
        continue;
    }
    $pdo->exec("ALTER TABLE clientes ADD UNIQUE KEY `{$indice}` (`{$coluna}`)");
    echo "Índice único '{$indice}' criado em clientes({$coluna}).\n";
}

// ── 3) Índice único em `veiculos.placa_normalizada` (sem conceito de inativo) ──
if (indiceExiste($pdo, 'veiculos', 'uq_veiculos_placa_normalizada')) {
    echo "Índice 'uq_veiculos_placa_normalizada' já existe.\n";
} else {
    $pdo->exec("ALTER TABLE veiculos ADD UNIQUE KEY uq_veiculos_placa_normalizada (placa_normalizada)");
    echo "Índice único 'uq_veiculos_placa_normalizada' criado em veiculos(placa_normalizada).\n";
}

echo "Concluído.\n";
