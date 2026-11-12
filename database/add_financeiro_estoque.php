<?php

/**
 * Integração financeira entre Produtos, Estoque, OS, Caixa e Movimentações.
 *
 * O que esta migração resolve:
 *
 * 1) FORMAS DE PAGAMENTO — o enum aceitava só DINHEIRO/PIX/CARTAO/BOLETO.
 *    Passa a aceitar débito e crédito separados (crédito é o que costuma ter
 *    parcelamento e taxa, então somar os dois num "CARTAO" só escondia isso)
 *    e transferência. 'CARTAO' continua no enum porque já existe lançamento
 *    gravado com esse valor — a tela deixa de oferecer, o histórico não quebra.
 *
 * 2) MOVIMENTAÇÕES DE ESTOQUE — a tabela guardava só produto/tipo/quantidade.
 *    Ganha origem (de onde veio a movimentação), a OS que a gerou, a forma de
 *    pagamento da compra, quem lançou e o vínculo com a saída no caixa. Esse
 *    último é o que impede lançamento duplicado: se a movimentação já tem
 *    caixa vinculado, ela não lança de novo.
 *
 * 3) PARCELAMENTO DA OS — nova tabela `ordem_servico_parcelas` e as colunas do
 *    plano de pagamento em `ordens_servico`. Numa OS parcelada, o caixa passa
 *    a receber cada parcela quando ela é paga, em vez do valor cheio na
 *    conclusão: o livro-caixa deixa de mostrar dinheiro que ainda não entrou.
 *
 * Como rodar:
 *   php database/add_financeiro_estoque.php
 */

require_once __DIR__ . '/../app/core/Database.php';

$pdo = new Database();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/** Enum único de formas de pagamento, usado em três tabelas. */
$FORMAS = "'DINHEIRO','PIX','CARTAO_DEBITO','CARTAO_CREDITO','BOLETO','TRANSFERENCIA','CARTAO'";

/** Executa o ALTER só se a coluna ainda não existir. */
function coluna(PDO $pdo, string $tabela, string $coluna, string $definicao): void
{
    $existe = $pdo->query("SHOW COLUMNS FROM `{$tabela}` LIKE " . $pdo->quote($coluna))->fetch();

    if ($existe) {
        echo "  = {$tabela}.{$coluna} já existe\n";
        return;
    }

    $pdo->exec("ALTER TABLE `{$tabela}` ADD COLUMN {$definicao}");
    echo "  + {$tabela}.{$coluna}\n";
}

echo "1) Formas de pagamento\n";

$pdo->exec("ALTER TABLE movimentacoes_caixa
            MODIFY COLUMN forma_pagamento ENUM({$FORMAS}) NULL");
echo "  ~ movimentacoes_caixa.forma_pagamento ampliado\n";

echo "\n2) Movimentações de estoque\n";

coluna($pdo, 'movimentacoes_estoque', 'origem',
    "origem ENUM('MANUAL','OS','CADASTRO') NOT NULL DEFAULT 'MANUAL' AFTER tipo");

coluna($pdo, 'movimentacoes_estoque', 'ordem_servico_id',
    "ordem_servico_id BIGINT UNSIGNED NULL AFTER origem");

coluna($pdo, 'movimentacoes_estoque', 'forma_pagamento',
    "forma_pagamento ENUM({$FORMAS}) NULL AFTER custo_unitario");

coluna($pdo, 'movimentacoes_estoque', 'usuario_id',
    "usuario_id BIGINT UNSIGNED NULL AFTER forma_pagamento");

coluna($pdo, 'movimentacoes_estoque', 'observacao',
    "observacao VARCHAR(255) NULL AFTER usuario_id");

coluna($pdo, 'movimentacoes_estoque', 'movimentacao_caixa_id',
    "movimentacao_caixa_id BIGINT UNSIGNED NULL AFTER observacao");

// ON DELETE SET NULL nos dois: apagar a OS ou o lançamento do caixa não pode
// apagar o histórico de estoque — o item saiu da prateleira de qualquer jeito.
foreach ([
    ['fk_mov_estoque_os', 'ordem_servico_id', 'ordens_servico', 'id_ordem_servico'],
    ['fk_mov_estoque_caixa', 'movimentacao_caixa_id', 'movimentacoes_caixa', 'id_movimentacao'],
    ['fk_mov_estoque_usuario', 'usuario_id', 'usuarios', 'id_usuario'],
] as [$nome, $coluna, $tabelaRef, $colunaRef]) {
    $existe = $pdo->query(
        "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND TABLE_NAME = 'movimentacoes_estoque'
           AND CONSTRAINT_NAME = " . $pdo->quote($nome)
    )->fetch();

    if ($existe) {
        echo "  = chave {$nome} já existe\n";
        continue;
    }

    $pdo->exec("ALTER TABLE movimentacoes_estoque
                ADD CONSTRAINT {$nome}
                FOREIGN KEY ({$coluna}) REFERENCES {$tabelaRef}({$colunaRef})
                ON DELETE SET NULL");
    echo "  + chave {$nome}\n";
}

echo "\n3) Plano de pagamento da OS\n";

coluna($pdo, 'ordens_servico', 'pagamento_tipo',
    "pagamento_tipo ENUM('AVISTA','PARCELADO') NOT NULL DEFAULT 'AVISTA' AFTER valor_total_os");

coluna($pdo, 'ordens_servico', 'pagamento_forma',
    "pagamento_forma ENUM({$FORMAS}) NULL AFTER pagamento_tipo");

coluna($pdo, 'ordens_servico', 'pagamento_juros',
    "pagamento_juros DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER pagamento_forma");

coluna($pdo, 'ordens_servico', 'pagamento_taxa',
    "pagamento_taxa DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER pagamento_juros");

coluna($pdo, 'ordens_servico', 'pagamento_total',
    "pagamento_total DECIMAL(10,2) NULL AFTER pagamento_taxa");

echo "\n4) Parcelas da OS\n";

$temTabela = $pdo->query("SHOW TABLES LIKE 'ordem_servico_parcelas'")->fetch();

if ($temTabela) {
    echo "  = tabela ordem_servico_parcelas já existe\n";
} else {
    $pdo->exec("
        CREATE TABLE ordem_servico_parcelas (
            id_parcela            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ordem_servico_id      BIGINT UNSIGNED NOT NULL,
            numero                SMALLINT UNSIGNED NOT NULL,
            valor                 DECIMAL(10,2) NOT NULL,
            vencimento            DATE NULL,
            pago                  TINYINT(1) NOT NULL DEFAULT 0,
            data_pagamento        DATETIME NULL,
            forma_pagamento       ENUM({$FORMAS}) NULL,
            movimentacao_caixa_id BIGINT UNSIGNED NULL,
            criado_em             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_parcela),
            -- Uma OS não pode ter duas parcelas com o mesmo número.
            UNIQUE KEY uq_os_parcela (ordem_servico_id, numero),
            KEY idx_parcela_pendente (pago, vencimento),
            CONSTRAINT fk_parcela_os FOREIGN KEY (ordem_servico_id)
                REFERENCES ordens_servico(id_ordem_servico) ON DELETE CASCADE,
            CONSTRAINT fk_parcela_caixa FOREIGN KEY (movimentacao_caixa_id)
                REFERENCES movimentacoes_caixa(id_movimentacao) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  + tabela ordem_servico_parcelas\n";
}

echo "\nMigração concluída.\n";
