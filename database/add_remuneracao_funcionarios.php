<?php

/**
 * Remuneração de técnicos/funcionários — regra por funcionário, aplicada
 * automaticamente na OS, calculada e travada na finalização.
 *
 * O que esta migração resolve:
 *
 * 1) REGRA DE REMUNERAÇÃO — nova tabela `tecnico_remuneracao_regra`, uma
 *    linha ATIVA por técnico (editar sobrescreve; o histórico não mora
 *    aqui, mora na OS — ver item 3). `tecnico_remuneracao_regra_categoria`
 *    é a sub-tabela usada só pelo tipo PERCENTUAL_TIPO_SERVICO, onde cada
 *    categoria de serviço tem seu próprio percentual.
 *
 * 2) MÚLTIPLOS TÉCNICOS POR OS — `ordem_servico_mao_obra` já era a tabela
 *    que liga técnico↔OS (usada pela Área do Técnico); o schema em si já
 *    aceitava várias linhas por OS, só a query/UI que assumiam uma. Esta
 *    migração não mexe nisso — só nas colunas abaixo.
 *
 * 3) SNAPSHOT DA COMISSÃO — `ordem_servico_mao_obra` ganha as colunas que
 *    faltavam pra guardar o que foi realmente calculado (tipo, %, base em
 *    R$) no momento da finalização, em vez do que existia antes: só o
 *    valor final em R$, com a % embutida como texto dentro de `descricao`
 *    e recuperada por regex. `calculado_em` é a guarda contra duplicidade
 *    (recalcular só roda se estiver NULL); `fechamento_pagamento_id`
 *    marca quando aquela comissão entrou num pagamento — e é isso que
 *    tranca a linha contra edição/exclusão depois.
 *
 * 4) FECHAMENTO/PAGAMENTO — nova tabela `funcionario_fechamento_pagamento`,
 *    um registro por lote pago a um técnico.
 *
 * Como rodar (as DUAS bases — dev e teste):
 *   php database/add_remuneracao_funcionarios.php
 *   DB_HOST=localhost DB_NAME=mecanize_db_test DB_USER=root DB_PASS= php database/add_remuneracao_funcionarios.php
 */

require_once __DIR__ . '/../app/core/Database.php';

$pdo = new Database();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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

/** Adiciona uma FK só se ela ainda não existir. */
function chave(PDO $pdo, string $tabela, string $nome, string $definicao): void
{
    $existe = $pdo->query(
        "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND TABLE_NAME = " . $pdo->quote($tabela) . "
           AND CONSTRAINT_NAME = " . $pdo->quote($nome)
    )->fetch();

    if ($existe) {
        echo "  = chave {$nome} já existe\n";
        return;
    }

    $pdo->exec("ALTER TABLE `{$tabela}` ADD CONSTRAINT {$nome} {$definicao}");
    echo "  + chave {$nome}\n";
}

$TIPOS_REGRA = "'PERCENTUAL_TOTAL','PERCENTUAL_SERVICOS','PERCENTUAL_PECAS',
                 'PERCENTUAL_TIPO_SERVICO','VALOR_FIXO_SERVICO','SALARIO_FIXO',
                 'SALARIO_FIXO_MAIS_COMISSAO'";

echo "1) Regra de remuneração por técnico\n";

$temTabelaRegra = $pdo->query("SHOW TABLES LIKE 'tecnico_remuneracao_regra'")->fetch();

if ($temTabelaRegra) {
    echo "  = tabela tecnico_remuneracao_regra já existe\n";
} else {
    $pdo->exec("
        CREATE TABLE tecnico_remuneracao_regra (
            id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tecnico_id           BIGINT UNSIGNED NOT NULL,
            tipo                 ENUM({$TIPOS_REGRA}) NOT NULL,
            percentual           DECIMAL(5,2) NULL,
            valor_fixo           DECIMAL(10,2) NULL,
            comissao_base_extra  ENUM('TOTAL','SERVICOS','PECAS') NULL,
            observacoes          VARCHAR(255) NULL,
            ativo                TINYINT(1) NOT NULL DEFAULT 1,
            criado_em            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_regra_tecnico (tecnico_id),
            CONSTRAINT fk_regra_tecnico FOREIGN KEY (tecnico_id)
                REFERENCES usuarios(id_usuario) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  + tabela tecnico_remuneracao_regra\n";
}

$temTabelaCategoria = $pdo->query("SHOW TABLES LIKE 'tecnico_remuneracao_regra_categoria'")->fetch();

if ($temTabelaCategoria) {
    echo "  = tabela tecnico_remuneracao_regra_categoria já existe\n";
} else {
    $pdo->exec("
        CREATE TABLE tecnico_remuneracao_regra_categoria (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            regra_id    BIGINT UNSIGNED NOT NULL,
            categoria   VARCHAR(100) NOT NULL,
            percentual  DECIMAL(5,2) NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_regra_categoria FOREIGN KEY (regra_id)
                REFERENCES tecnico_remuneracao_regra(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  + tabela tecnico_remuneracao_regra_categoria\n";
}

echo "\n2) Snapshot da comissão em ordem_servico_mao_obra\n";

coluna($pdo, 'ordem_servico_mao_obra', 'regra_tipo',
    "regra_tipo VARCHAR(30) NULL AFTER valor_mao_obra");

coluna($pdo, 'ordem_servico_mao_obra', 'regra_percentual',
    "regra_percentual DECIMAL(5,2) NULL AFTER regra_tipo");

coluna($pdo, 'ordem_servico_mao_obra', 'base_valor',
    "base_valor DECIMAL(10,2) NULL AFTER regra_percentual");

coluna($pdo, 'ordem_servico_mao_obra', 'status_pagamento',
    "status_pagamento ENUM('PENDENTE','PAGO') NULL AFTER base_valor");

coluna($pdo, 'ordem_servico_mao_obra', 'ajustado_manualmente',
    "ajustado_manualmente TINYINT(1) NOT NULL DEFAULT 0 AFTER status_pagamento");

coluna($pdo, 'ordem_servico_mao_obra', 'observacao_ajuste',
    "observacao_ajuste VARCHAR(255) NULL AFTER ajustado_manualmente");

coluna($pdo, 'ordem_servico_mao_obra', 'calculado_em',
    "calculado_em DATETIME NULL AFTER observacao_ajuste");

coluna($pdo, 'ordem_servico_mao_obra', 'fechamento_pagamento_id',
    "fechamento_pagamento_id BIGINT UNSIGNED NULL AFTER calculado_em");

echo "\n3) Fechamento/pagamento\n";

$temTabelaFechamento = $pdo->query("SHOW TABLES LIKE 'funcionario_fechamento_pagamento'")->fetch();

if ($temTabelaFechamento) {
    echo "  = tabela funcionario_fechamento_pagamento já existe\n";
} else {
    $pdo->exec("
        CREATE TABLE funcionario_fechamento_pagamento (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tecnico_id       BIGINT UNSIGNED NOT NULL,
            periodo_inicio   DATE NOT NULL,
            periodo_fim      DATE NOT NULL,
            valor_total      DECIMAL(10,2) NOT NULL,
            data_pagamento   DATETIME NOT NULL,
            forma_pagamento  ENUM('DINHEIRO','PIX','CARTAO_DEBITO','CARTAO_CREDITO','BOLETO','TRANSFERENCIA','CARTAO') NOT NULL,
            observacoes      TEXT NULL,
            criado_por       BIGINT UNSIGNED NULL,
            criado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            CONSTRAINT fk_fechamento_tecnico FOREIGN KEY (tecnico_id)
                REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
            CONSTRAINT fk_fechamento_criador FOREIGN KEY (criado_por)
                REFERENCES usuarios(id_usuario) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  + tabela funcionario_fechamento_pagamento\n";
}

// Só depois da tabela existir — a FK referencia ela.
chave($pdo, 'ordem_servico_mao_obra', 'fk_mao_obra_fechamento',
    "FOREIGN KEY (fechamento_pagamento_id) REFERENCES funcionario_fechamento_pagamento(id) ON DELETE SET NULL");

echo "\nMigração concluída.\n";
