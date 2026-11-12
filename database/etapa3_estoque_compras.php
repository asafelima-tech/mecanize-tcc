<?php

/**
 * Etapa 3 — estoque e compras.
 *
 *  1. Reserva de peça da OS (RESERVADO): quem reservou e quando. O estado já existia no
 *     enum desde a Etapa 1; agora há quem o use.
 *  2. Livro de estoque: o evento RECEBIMENTO (entrada por compra recebida) — o estorno
 *     genérico da tela de movimentações recusa movimentação com evento, então um
 *     recebimento só se desfaz pelo próprio recebimento (que atualiza o pedido junto).
 *  3. Notificações internas (sino): destinatário, tipo, link, lida, e chave de idempotência
 *     (o mesmo fato nunca avisa a mesma pessoa duas vezes).
 *  4. Solicitações de peça: o pedido de reposição de quem está no balcão ou na bancada.
 *     NÃO é compra nem entrada no estoque — é só a necessidade.
 *  5. Fornecedores, cotações, pedidos de compra, itens, recebimentos (parciais ou integrais)
 *     e itens recebidos. O estoque só sobe quando o recebimento é confirmado.
 *
 * Idempotente. Como rodar:
 *   /c/xampp/php/php.exe database/etapa3_estoque_compras.php
 *   DB_NAME=mecanize_db_test /c/xampp/php/php.exe database/etapa3_estoque_compras.php
 */

require_once __DIR__ . '/_migracao.php';

$m = new Migracao();

/* ── 1. Reserva de peça ───────────────────────────────────────────────── */
$m->titulo('1) Reserva de peça da OS');

$m->adicionarColuna('ordem_servico_produtos', 'reservado_em', 'DATETIME NULL');
$m->adicionarColuna('ordem_servico_produtos', 'reservado_por', 'BIGINT UNSIGNED NULL');
$m->adicionarFk('ordem_servico_produtos', 'fk_os_produtos_reservado_por', 'reservado_por', 'usuarios', 'id_usuario', 'SET NULL');

/* ── 2. Evento RECEBIMENTO no livro de estoque ────────────────────────── */
$m->titulo('2) Livro de estoque: evento RECEBIMENTO');

$tipoEvento = (string) $m->tipoDaColuna('movimentacoes_estoque', 'evento');

if (!str_contains($tipoEvento, 'RECEBIMENTO')) {
    $m->executar(
        'movimentacoes_estoque.evento aceita RECEBIMENTO',
        "ALTER TABLE movimentacoes_estoque MODIFY `evento` ENUM('CONSUMO','DEVOLUCAO','RECEBIMENTO') NULL"
    );
} else {
    $m->jaTinha('movimentacoes_estoque.evento com RECEBIMENTO');
}

/* ── 3. Notificações internas ─────────────────────────────────────────── */
$m->titulo('3) Notificações internas');

$m->criarTabela('notificacoes', "
    CREATE TABLE notificacoes (
        id_notificacao     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        usuario_id         BIGINT UNSIGNED NOT NULL,
        tipo               VARCHAR(40) NOT NULL,
        titulo             VARCHAR(150) NOT NULL,
        mensagem           VARCHAR(500) NULL,
        link               VARCHAR(255) NULL,
        chave_idempotencia VARCHAR(120) NOT NULL,
        lida_em            DATETIME NULL,
        criada_em          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id_notificacao),
        UNIQUE KEY uq_notif_usuario_chave (usuario_id, chave_idempotencia),
        KEY idx_notif_usuario_lida (usuario_id, lida_em, id_notificacao),
        CONSTRAINT fk_notif_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id_usuario) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ── 4. Solicitações de peça ──────────────────────────────────────────── */
$m->titulo('4) Solicitações de peça');

$m->criarTabela('solicitacoes_peca', "
    CREATE TABLE solicitacoes_peca (
        id_solicitacao      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        numero              VARCHAR(20) NOT NULL DEFAULT '',
        produto_id          BIGINT UNSIGNED NULL,
        descricao           VARCHAR(200) NOT NULL,
        quantidade          DECIMAL(10,3) NOT NULL,
        quantidade_recebida DECIMAL(10,3) NOT NULL DEFAULT 0.000,
        estoque_conhecido   DECIMAL(10,3) NULL,
        motivo              VARCHAR(255) NOT NULL,
        ordem_servico_id    BIGINT UNSIGNED NULL,
        urgencia            ENUM('BAIXA','NORMAL','ALTA','URGENTE') NOT NULL DEFAULT 'NORMAL',
        observacoes         TEXT NULL,
        solicitante_id      BIGINT UNSIGNED NOT NULL,
        status              ENUM('SOLICITADA','EM_ANALISE','APROVADA_COMPRA','PEDIDO_REALIZADO',
                                 'PARCIALMENTE_RECEBIDA','RECEBIDA','CANCELADA') NOT NULL DEFAULT 'SOLICITADA',
        motivo_decisao      VARCHAR(255) NULL,
        decidido_por        BIGINT UNSIGNED NULL,
        decidido_em         DATETIME NULL,
        aberta_unica        VARCHAR(120) GENERATED ALWAYS AS (
            CASE WHEN status IN ('SOLICITADA','EM_ANALISE','APROVADA_COMPRA','PEDIDO_REALIZADO','PARCIALMENTE_RECEBIDA')
                 THEN CONCAT_WS('|', solicitante_id, COALESCE(produto_id, 0), COALESCE(ordem_servico_id, 0), MD5(LOWER(descricao)))
                 ELSE NULL END
        ) STORED,
        criada_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizada_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id_solicitacao),
        UNIQUE KEY uq_solicitacao_aberta (aberta_unica),
        KEY idx_solic_status (status, urgencia, criada_em),
        KEY idx_solic_produto (produto_id),
        KEY idx_solic_os (ordem_servico_id),
        KEY idx_solic_solicitante (solicitante_id),
        CONSTRAINT fk_solic_produto     FOREIGN KEY (produto_id) REFERENCES produtos (id_produto) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_solic_os          FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico (id_ordem_servico) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_solic_solicitante FOREIGN KEY (solicitante_id) REFERENCES usuarios (id_usuario) ON UPDATE CASCADE,
        CONSTRAINT fk_solic_decidido    FOREIGN KEY (decidido_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ── 5. Fornecedores e compras ────────────────────────────────────────── */
$m->titulo('5) Fornecedores, cotações, pedidos e recebimentos');

$m->criarTabela('fornecedores', "
    CREATE TABLE fornecedores (
        id_fornecedor        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        nome                 VARCHAR(150) NOT NULL,
        documento            VARCHAR(20) NULL,
        documento_normalizado VARCHAR(14) NULL,
        contato              VARCHAR(120) NULL,
        telefone             VARCHAR(30) NULL,
        email                VARCHAR(150) NULL,
        site_url             VARCHAR(255) NULL,
        condicoes_pagamento  VARCHAR(150) NULL,
        prazo_medio_dias     SMALLINT UNSIGNED NULL,
        observacoes          TEXT NULL,
        ativo                TINYINT(1) NOT NULL DEFAULT 1,
        criado_em            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id_fornecedor),
        UNIQUE KEY uq_fornecedor_documento (documento_normalizado),
        KEY idx_fornecedor_ativo (ativo, nome)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$m->criarTabela('pedidos_compra', "
    CREATE TABLE pedidos_compra (
        id_pedido            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        numero               VARCHAR(20) NOT NULL DEFAULT '',
        fornecedor_id        BIGINT UNSIGNED NOT NULL,
        status               ENUM('RASCUNHO','REALIZADO','PARCIALMENTE_RECEBIDO','RECEBIDO','CANCELADO') NOT NULL DEFAULT 'RASCUNHO',
        condicao_pagamento   VARCHAR(150) NULL,
        forma_pagamento      ENUM('DINHEIRO','PIX','CARTAO_DEBITO','CARTAO_CREDITO','BOLETO','TRANSFERENCIA','OUTRO') NULL,
        vencimento_previsto  DATE NULL,
        frete                DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        prazo_previsto       DATE NULL,
        referencia_externa   VARCHAR(80) NULL,
        observacoes          TEXT NULL,
        motivo_encerramento  VARCHAR(255) NULL,
        conta_pagar_id       BIGINT UNSIGNED NULL,
        criado_por           BIGINT UNSIGNED NULL,
        realizado_em         DATETIME NULL,
        recebido_em          DATETIME NULL,
        criado_em            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id_pedido),
        KEY idx_pedido_status (status, criado_em),
        KEY idx_pedido_fornecedor (fornecedor_id),
        CONSTRAINT fk_pedido_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedores (id_fornecedor) ON UPDATE CASCADE,
        CONSTRAINT fk_pedido_criador    FOREIGN KEY (criado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$m->criarTabela('pedido_compra_itens', "
    CREATE TABLE pedido_compra_itens (
        id_pedido_item       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        pedido_id            BIGINT UNSIGNED NOT NULL,
        produto_id           BIGINT UNSIGNED NOT NULL,
        solicitacao_id       BIGINT UNSIGNED NULL,
        quantidade           DECIMAL(10,3) NOT NULL,
        preco_unitario       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        quantidade_recebida  DECIMAL(10,3) NOT NULL DEFAULT 0.000,
        PRIMARY KEY (id_pedido_item),
        KEY idx_pitem_pedido (pedido_id),
        KEY idx_pitem_produto (produto_id),
        KEY idx_pitem_solicitacao (solicitacao_id),
        CONSTRAINT fk_pitem_pedido      FOREIGN KEY (pedido_id) REFERENCES pedidos_compra (id_pedido) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_pitem_produto     FOREIGN KEY (produto_id) REFERENCES produtos (id_produto) ON UPDATE CASCADE,
        CONSTRAINT fk_pitem_solicitacao FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes_peca (id_solicitacao) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$m->criarTabela('recebimentos_compra', "
    CREATE TABLE recebimentos_compra (
        id_recebimento       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        pedido_id            BIGINT UNSIGNED NOT NULL,
        chave_idempotencia   VARCHAR(80) NOT NULL,
        documento_fiscal     VARCHAR(60) NULL,
        observacao           VARCHAR(255) NULL,
        recebido_por         BIGINT UNSIGNED NULL,
        recebido_em          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        estornado_em         DATETIME NULL,
        estornado_por        BIGINT UNSIGNED NULL,
        estorno_justificativa VARCHAR(255) NULL,
        PRIMARY KEY (id_recebimento),
        UNIQUE KEY uq_recebimento_chave (chave_idempotencia),
        KEY idx_recebimento_pedido (pedido_id),
        CONSTRAINT fk_recebimento_pedido  FOREIGN KEY (pedido_id) REFERENCES pedidos_compra (id_pedido) ON UPDATE CASCADE,
        CONSTRAINT fk_recebimento_usuario FOREIGN KEY (recebido_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_recebimento_estorno FOREIGN KEY (estornado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$m->criarTabela('recebimento_itens', "
    CREATE TABLE recebimento_itens (
        id_recebimento_item  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        recebimento_id       BIGINT UNSIGNED NOT NULL,
        pedido_item_id       BIGINT UNSIGNED NOT NULL,
        quantidade           DECIMAL(10,3) NOT NULL,
        custo_unitario       DECIMAL(10,2) NOT NULL,
        movimentacao_id      BIGINT UNSIGNED NULL,
        PRIMARY KEY (id_recebimento_item),
        UNIQUE KEY uq_recitem_movimentacao (movimentacao_id),
        KEY idx_recitem_recebimento (recebimento_id),
        KEY idx_recitem_pedido_item (pedido_item_id),
        CONSTRAINT fk_recitem_recebimento FOREIGN KEY (recebimento_id) REFERENCES recebimentos_compra (id_recebimento) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_recitem_pedido_item FOREIGN KEY (pedido_item_id) REFERENCES pedido_compra_itens (id_pedido_item) ON UPDATE CASCADE,
        CONSTRAINT fk_recitem_movimentacao FOREIGN KEY (movimentacao_id) REFERENCES movimentacoes_estoque (id_movimentacao) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$m->criarTabela('cotacoes_compra', "
    CREATE TABLE cotacoes_compra (
        id_cotacao           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        solicitacao_id       BIGINT UNSIGNED NULL,
        produto_id           BIGINT UNSIGNED NULL,
        descricao            VARCHAR(200) NOT NULL,
        fornecedor_id        BIGINT UNSIGNED NOT NULL,
        quantidade           DECIMAL(10,3) NOT NULL,
        preco_unitario       DECIMAL(10,2) NOT NULL,
        frete                DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        prazo_dias           SMALLINT UNSIGNED NULL,
        condicao_pagamento   VARCHAR(150) NULL,
        validade_ate         DATE NULL,
        observacao           VARCHAR(255) NULL,
        registrada_por       BIGINT UNSIGNED NULL,
        criada_em            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id_cotacao),
        KEY idx_cotacao_solicitacao (solicitacao_id),
        KEY idx_cotacao_produto (produto_id),
        KEY idx_cotacao_fornecedor (fornecedor_id),
        CONSTRAINT fk_cotacao_solicitacao FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes_peca (id_solicitacao) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_cotacao_produto     FOREIGN KEY (produto_id) REFERENCES produtos (id_produto) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_cotacao_fornecedor  FOREIGN KEY (fornecedor_id) REFERENCES fornecedores (id_fornecedor) ON UPDATE CASCADE,
        CONSTRAINT fk_cotacao_usuario     FOREIGN KEY (registrada_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$m->fim();
