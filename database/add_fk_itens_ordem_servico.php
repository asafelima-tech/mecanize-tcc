<?php

/**
 * Cria as foreign keys que faltavam entre `ordens_servico` e as tabelas de
 * itens dela, com ON DELETE CASCADE.
 *
 * Por que existe: nenhuma das quatro tabelas de itens referenciava a OS. Um
 * DELETE em `ordens_servico` apagava só a OS e deixava produtos, serviços,
 * mão de obra e anexos para trás, apontando para um id inexistente — sem erro
 * e sem aviso. O OrdemServico::delete() passou a apagar os itens em transação,
 * o que resolve pelo caminho do sistema; esta migração fecha a porta também
 * para escrita direta no banco (phpMyAdmin, script, importação).
 *
 * ON DELETE CASCADE e não RESTRICT: os itens não têm vida própria, são partes
 * da OS. Barrar a exclusão obrigaria a limpar item por item antes — que é
 * justamente o trabalho que a foreign key deveria poupar. Já `agendamentos`
 * continua RESTRICT de propósito: um agendamento é de outro fluxo e some junto
 * seria perda de informação.
 *
 * Só cria o que falta — pode rodar quantas vezes for preciso.
 *
 * Como rodar:
 *   /c/xampp/php/php.exe database/add_fk_itens_ordem_servico.php
 *
 * Se houver linhas órfãs de exclusões antigas, o script PARA e lista o que
 * achou: apagar dado silenciosamente não é decisão de migração. Depois de
 * conferir, rode com --limpar para remover essas linhas e criar as chaves.
 */

require_once __DIR__ . '/../app/core/Database.php';

$pdo = new Database();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$banco  = $pdo->query('SELECT DATABASE()')->fetchColumn();
$limpar = in_array('--limpar', $argv, true);

/** Tabelas de itens: nome da FK => tabela filha. */
$chaves = [
    'fk_os_produtos_os' => 'ordem_servico_produtos',
    'fk_os_servicos_os' => 'ordem_servico_servicos',
    'fk_os_mao_obra_os' => 'ordem_servico_mao_obra',
    'fk_os_anexos_os'   => 'anexos_ordem_servico',
];

echo "Banco: {$banco}\n\n";

// ── 1. Órfãos ────────────────────────────────────────────────────────────────
// A criação da foreign key falha se existir linha apontando pra OS inexistente,
// e o erro do MySQL (errno 150) não diz qual linha é. Melhor checar antes.
$orfaos = [];

foreach ($chaves as $tabela) {
    $sql = "SELECT COUNT(*) FROM `{$tabela}` f
            LEFT JOIN ordens_servico o ON o.id_ordem_servico = f.ordem_servico_id
            WHERE o.id_ordem_servico IS NULL";

    $qtd = (int) $pdo->query($sql)->fetchColumn();

    if ($qtd > 0) {
        $orfaos[$tabela] = $qtd;
    }
}

if ($orfaos) {
    echo "Linhas órfãs encontradas (apontam para uma OS que não existe mais):\n";

    foreach ($orfaos as $tabela => $qtd) {
        printf("  %-26s %d linha(s)\n", $tabela, $qtd);
    }

    if (!$limpar) {
        echo "\nNenhuma chave foi criada. Confira essas linhas e rode de novo com --limpar\n";
        echo "para removê-las e prosseguir.\n";
        exit(1);
    }

    echo "\n--limpar: removendo...\n";

    foreach ($orfaos as $tabela => $qtd) {
        $removidas = $pdo->exec(
            "DELETE f FROM `{$tabela}` f
             LEFT JOIN ordens_servico o ON o.id_ordem_servico = f.ordem_servico_id
             WHERE o.id_ordem_servico IS NULL"
        );
        printf("  %-26s %d removida(s)\n", $tabela, $removidas);
    }

    echo "\n";
} else {
    echo "Nenhuma linha órfã.\n\n";
}

// ── 2. Foreign keys ──────────────────────────────────────────────────────────
$existentes = $pdo->prepare("
    SELECT CONSTRAINT_NAME
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
      AND TABLE_NAME = :tabela
");

$criadas = 0;
$mantidas = 0;

foreach ($chaves as $nome => $tabela) {
    $existentes->execute([':tabela' => $tabela]);
    $jaTem = $existentes->fetchAll(PDO::FETCH_COLUMN);

    if (in_array($nome, $jaTem, true)) {
        printf("  %-26s já tem %s\n", $tabela, $nome);
        $mantidas++;
        continue;
    }

    $pdo->exec("
        ALTER TABLE `{$tabela}`
        ADD CONSTRAINT `{$nome}`
        FOREIGN KEY (`ordem_servico_id`)
        REFERENCES `ordens_servico` (`id_ordem_servico`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
    ");

    printf("  %-26s + %s (ON DELETE CASCADE)\n", $tabela, $nome);
    $criadas++;
}

echo "\n{$criadas} chave(s) criada(s), {$mantidas} já existente(s).\n";
