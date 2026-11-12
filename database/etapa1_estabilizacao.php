<?php

/**
 * Etapa 1 — estabilização: estrutura que sustenta as correções da auditoria
 * de 08/10/2026 (ver MELHORIAS_2026-10-08.md).
 *
 *  1. Estados da OS: separa "técnico terminou" (AGUARDANDO_CONFERENCIA) de
 *     "administrador liberou" (AGUARDANDO_RETIRADA) e de "entregue"
 *     (CONCLUIDA). PRONTA_ENTREGA deixa de existir — as linhas viram
 *     AGUARDANDO_CONFERENCIA, que é o que elas de fato eram.
 *  2. historico_estados: trilha de toda mudança de estado (quem, quando, por quê).
 *  3. Itens de peça da OS com ciclo de vida (PLANEJADO → CONSUMIDO/DEVOLVIDO):
 *     abrir uma OS deixa de baixar estoque. As OS antigas já tinham baixado, então
 *     os itens delas nascem CONSUMIDO.
 *  4. Movimentações de estoque imutáveis: delta com sinal, estorno e uma
 *     movimentação por (item, evento) — reprocessar não baixa duas vezes.
 *  5. Caixa: chave de idempotência nos lançamentos e no máximo UM caixa aberto,
 *     garantido pelo banco (antes só pelo código, com corrida possível).
 *  6. Foreign keys que faltavam nas relações centrais.
 *
 * Idempotente. Como rodar:
 *   /c/xampp/php/php.exe database/etapa1_estabilizacao.php
 *   DB_NAME=mecanize_db_test /c/xampp/php/php.exe database/etapa1_estabilizacao.php
 */

require_once __DIR__ . '/_migracao.php';

$m = new Migracao();

/* ── 1. Estados da OS ─────────────────────────────────────────────────── */
$m->titulo('1) Estados da ordem de serviço');

$tipoStatus = $m->tipoDaColuna('ordens_servico', 'status');

if (str_contains((string) $tipoStatus, 'PRONTA_ENTREGA')) {
    $m->executar(
        'status: aceita os novos valores (transição)',
        "ALTER TABLE ordens_servico MODIFY status ENUM('ABERTA','EM_ANDAMENTO','AGUARDANDO_PECA','PRONTA_ENTREGA',
            'AGUARDANDO_CONFERENCIA','AGUARDANDO_RETIRADA','CONCLUIDA','CANCELADA') NOT NULL DEFAULT 'ABERTA'"
    );
    $m->executar(
        'PRONTA_ENTREGA → AGUARDANDO_CONFERENCIA',
        "UPDATE ordens_servico SET status = 'AGUARDANDO_CONFERENCIA' WHERE status = 'PRONTA_ENTREGA'"
    );
    $m->executar(
        'status: remove PRONTA_ENTREGA',
        "ALTER TABLE ordens_servico MODIFY status ENUM('ABERTA','EM_ANDAMENTO','AGUARDANDO_PECA',
            'AGUARDANDO_CONFERENCIA','AGUARDANDO_RETIRADA','CONCLUIDA','CANCELADA') NOT NULL DEFAULT 'ABERTA'"
    );
} else {
    $m->jaTinha('enum de status atualizado');
}

$m->adicionarColuna('ordens_servico', 'conferido_por', 'BIGINT UNSIGNED NULL');
$m->adicionarColuna('ordens_servico', 'data_conferencia', 'DATETIME NULL');
$m->adicionarFk('ordens_servico', 'fk_os_conferido_por', 'conferido_por', 'usuarios', 'id_usuario', 'SET NULL');
$m->adicionarIndice('ordens_servico', 'idx_os_status_abertura', '`status`, `data_abertura`');

/* ── 2. Trilha de estados ─────────────────────────────────────────────── */
$m->titulo('2) Trilha de mudanças de estado');

$m->criarTabela('historico_estados', "
    CREATE TABLE historico_estados (
        id_historico    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        entidade        VARCHAR(30)  NOT NULL,
        entidade_id     BIGINT UNSIGNED NOT NULL,
        estado_anterior VARCHAR(40)  NULL,
        estado_novo     VARCHAR(40)  NOT NULL,
        usuario_id      BIGINT UNSIGNED NULL,
        origem          VARCHAR(20)  NOT NULL DEFAULT 'USUARIO',
        motivo          VARCHAR(500) NULL,
        dados           TEXT NULL,
        criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id_historico),
        KEY idx_hist_entidade (entidade, entidade_id, id_historico),
        KEY idx_hist_usuario (usuario_id),
        CONSTRAINT fk_hist_usuario FOREIGN KEY (usuario_id)
            REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ── 3. Itens de peça da OS ───────────────────────────────────────────── */
$m->titulo('3) Ciclo de vida das peças da OS');

$novo = !$m->colunaExiste('ordem_servico_produtos', 'status');

$m->adicionarColuna(
    'ordem_servico_produtos',
    'status',
    "ENUM('PLANEJADO','RESERVADO','CONSUMIDO','DEVOLVIDO','CANCELADO') NOT NULL DEFAULT 'PLANEJADO'"
);
$m->adicionarColuna('ordem_servico_produtos', 'consumido_em', 'DATETIME NULL');
$m->adicionarColuna('ordem_servico_produtos', 'consumido_por', 'BIGINT UNSIGNED NULL');
$m->adicionarColuna('ordem_servico_produtos', 'devolvido_em', 'DATETIME NULL');

if ($novo) {
    // As OS que existem hoje baixaram o estoque ao serem salvas: o item delas já foi consumido.
    $m->executar(
        'itens antigos → CONSUMIDO (o estoque já tinha sido baixado)',
        "UPDATE ordem_servico_produtos p
         JOIN ordens_servico o ON o.id_ordem_servico = p.ordem_servico_id
         SET p.status = 'CONSUMIDO', p.consumido_em = o.data_abertura"
    );
}

$m->adicionarFk('ordem_servico_produtos', 'fk_os_produtos_usuario', 'consumido_por', 'usuarios', 'id_usuario', 'SET NULL');
$m->adicionarFk('ordem_servico_produtos', 'fk_os_produtos_produto', 'produto_id', 'produtos', 'id_produto', 'RESTRICT');

/* ── 4. Movimentações de estoque ──────────────────────────────────────── */
$m->titulo('4) Movimentações de estoque imutáveis');

$m->adicionarColuna('movimentacoes_estoque', 'os_produto_id', 'INT(11) NULL');
$m->adicionarColuna('movimentacoes_estoque', 'evento', "ENUM('CONSUMO','DEVOLUCAO') NULL");
$m->adicionarColuna('movimentacoes_estoque', 'delta', 'DECIMAL(10,3) NULL');
$m->adicionarColuna('movimentacoes_estoque', 'estorno_de_id', 'BIGINT UNSIGNED NULL');
$m->adicionarColuna('movimentacoes_estoque', 'justificativa', 'VARCHAR(255) NULL');

if (!str_contains((string) $m->tipoDaColuna('movimentacoes_estoque', 'origem'), 'ESTORNO')) {
    $m->executar(
        'origem aceita ESTORNO e COMPRA',
        "ALTER TABLE movimentacoes_estoque MODIFY origem ENUM('MANUAL','OS','CADASTRO','ESTORNO','COMPRA') NOT NULL DEFAULT 'MANUAL'"
    );
}

// delta = efeito real no saldo, com sinal. Linhas antigas: ENTRADA soma, SAIDA
// subtrai e AJUSTE já vinha gravado COM SINAL (Produtos::update guardava a
// diferença: positiva = subiu, negativa = desceu), então vale como está.
if ((int) $m->pdo->query('SELECT COUNT(*) FROM movimentacoes_estoque WHERE delta IS NULL')->fetchColumn() > 0) {
    $m->executar(
        'delta das movimentações antigas',
        "UPDATE movimentacoes_estoque
         SET delta = CASE tipo WHEN 'ENTRADA' THEN quantidade WHEN 'AJUSTE' THEN quantidade ELSE -quantidade END
         WHERE delta IS NULL"
    );
} else {
    $m->jaTinha('delta das movimentações');
}

$m->adicionarIndice('movimentacoes_estoque', 'uq_mov_item_evento', '`os_produto_id`, `evento`', true);
$m->adicionarIndice('movimentacoes_estoque', 'uq_mov_estorno_de', '`estorno_de_id`', true);
$m->adicionarFk('movimentacoes_estoque', 'fk_mov_estoque_os_produto', 'os_produto_id', 'ordem_servico_produtos', 'id_OS_produto', 'SET NULL');
$m->adicionarFk('movimentacoes_estoque', 'fk_mov_estoque_estorno', 'estorno_de_id', 'movimentacoes_estoque', 'id_movimentacao', 'RESTRICT');
$m->adicionarFk('movimentacoes_estoque', 'fk_mov_estoque_produto', 'produto_id', 'produtos', 'id_produto', 'RESTRICT');

/* ── 5. Caixa ─────────────────────────────────────────────────────────── */
$m->titulo('5) Caixa: idempotência e caixa único');

$m->adicionarColuna('movimentacoes_caixa', 'chave_idempotencia', 'VARCHAR(80) NULL');
$m->adicionarIndice('movimentacoes_caixa', 'uq_mov_caixa_chave', '`chave_idempotencia`', true);

if (!$m->colunaExiste('caixa', 'aberto_unico')) {
    $abertos = (int) $m->pdo->query("SELECT COUNT(*) FROM caixa WHERE status = 'ABERTO'")->fetchColumn();

    if ($abertos > 1) {
        fwrite(STDERR, "ABORTADO: há {$abertos} caixas ABERTO ao mesmo tempo. Feche os excedentes e rode de novo.\n");
        exit(1);
    }

    $m->executar(
        'caixa.aberto_unico (no máximo um caixa ABERTO)',
        "ALTER TABLE caixa
            ADD COLUMN aberto_unico TINYINT(1) AS (IF(status = 'ABERTO', 1, NULL)) PERSISTENT,
            ADD UNIQUE KEY uq_caixa_um_aberto (aberto_unico)"
    );
} else {
    $m->jaTinha('caixa.aberto_unico');
}

$m->adicionarFk('movimentacoes_caixa', 'fk_mov_caixa_os', 'ordem_servico_id', 'ordens_servico', 'id_ordem_servico', 'RESTRICT');

/* ── 6. Integridade referencial ───────────────────────────────────────── */
$m->titulo('6) Foreign keys das relações centrais');

$m->adicionarFk('ordens_servico', 'fk_os_cliente', 'cliente_id', 'clientes', 'id_cliente', 'RESTRICT');
$m->adicionarFk('ordens_servico', 'fk_os_veiculo', 'veiculo_id', 'veiculos', 'id_veiculo', 'RESTRICT');
$m->adicionarFk('veiculos', 'fk_veiculo_cliente', 'cliente_id', 'clientes', 'id_cliente', 'RESTRICT');
$m->adicionarFk('veiculos', 'fk_veiculo_marca', 'marca_id', 'marcas_veiculos', 'id_marca', 'RESTRICT');
$m->adicionarFk('veiculos', 'fk_veiculo_modelo', 'modelo_id', 'modelos_veiculos', 'id_modelo', 'RESTRICT');
$m->adicionarFk('modelos_veiculos', 'fk_modelo_marca', 'marca_id', 'marcas_veiculos', 'id_marca', 'RESTRICT');
$m->adicionarFk('ordem_servico_mao_obra', 'fk_mao_obra_tecnico', 'tecnico_id', 'usuarios', 'id_usuario', 'RESTRICT');
$m->adicionarFk('produtos', 'fk_produto_categoria', 'categorias_produtos_id', 'categorias_produtos', 'id_categoria', 'RESTRICT');

$m->fim();
