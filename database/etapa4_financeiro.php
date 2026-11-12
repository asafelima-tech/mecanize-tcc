<?php

/**
 * Etapa 4 — financeiro de verdade.
 *
 *  1. Contas a receber: a parcela da OS passa a registrar a TAXA do meio de pagamento (separada
 *     do valor), quantas vezes já foi paga (uma parcela estornada pode ser paga de novo, e cada
 *     pagamento tem a sua chave no caixa) e o estorno (quem, quando, por quê).
 *  2. Cartões corporativos e faturas: cartão (limite, fechamento, vencimento) e a fatura de cada
 *     mês, que NASCE das parcelas das compras — não é uma despesa à parte.
 *  3. Contas a pagar: a DESPESA (a compra: o evento econômico, registrado uma vez), suas PARCELAS
 *     (as obrigações, cada uma com vencimento) e os PAGAMENTOS (o dinheiro que saiu). As parcelas
 *     de cartão são quitadas pelo pagamento da FATURA, nunca uma a uma: compra, parcela e fatura
 *     são etapas da MESMA despesa, e o dinheiro sai uma vez só.
 *  4. Comprovantes: arquivos guardados FORA da pasta pública, servidos por rota autorizada.
 *  5. O pedido de compra aponta para a despesa que o cobra (pedidos_compra.conta_pagar_id).
 *
 * Idempotente. Como rodar:
 *   /c/xampp/php/php.exe database/etapa4_financeiro.php
 *   DB_NAME=mecanize_db_test /c/xampp/php/php.exe database/etapa4_financeiro.php
 */

require_once __DIR__ . '/_migracao.php';

$m = new Migracao();

/* ── 1. Contas a receber ──────────────────────────────────────────────── */
$m->titulo('1) Parcelas da OS: taxa, tentativas de pagamento e estorno');

$m->adicionarColuna('ordem_servico_parcelas', 'taxa_valor', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
$m->adicionarColuna('ordem_servico_parcelas', 'pagamentos_realizados', 'SMALLINT UNSIGNED NOT NULL DEFAULT 0');
$m->adicionarColuna('ordem_servico_parcelas', 'estornada_em', 'DATETIME NULL');
$m->adicionarColuna('ordem_servico_parcelas', 'estornada_por', 'BIGINT UNSIGNED NULL');
$m->adicionarColuna('ordem_servico_parcelas', 'estorno_justificativa', 'VARCHAR(255) NULL');
$m->adicionarFk('ordem_servico_parcelas', 'fk_parcela_estornada_por', 'estornada_por', 'usuarios', 'id_usuario', 'SET NULL');

// Parcelas já pagas antes desta etapa contam como "1 pagamento realizado".
$m->pdo->exec('UPDATE ordem_servico_parcelas SET pagamentos_realizados = 1 WHERE pago = 1 AND pagamentos_realizados = 0');

/* ── 2. Cartões e faturas ─────────────────────────────────────────────── */
$m->titulo('2) Cartões corporativos e faturas');

$m->criarTabela('cartoes_corporativos', "
    CREATE TABLE cartoes_corporativos (
        id_cartao       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        apelido         VARCHAR(80) NOT NULL,
        instituicao     VARCHAR(80) NOT NULL,
        bandeira        VARCHAR(30) NULL,
        final_cartao    CHAR(4) NULL,
        limite_total    DECIMAL(12,2) NULL,
        dia_fechamento  TINYINT UNSIGNED NOT NULL,
        dia_vencimento  TINYINT UNSIGNED NOT NULL,
        ativo           TINYINT(1) NOT NULL DEFAULT 1,
        criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id_cartao),
        UNIQUE KEY uq_cartao_apelido (apelido)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$m->criarTabela('faturas_cartao', "
    CREATE TABLE faturas_cartao (
        id_fatura   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        cartao_id   BIGINT UNSIGNED NOT NULL,
        referencia  DATE NOT NULL,
        fecha_em    DATE NOT NULL,
        vence_em    DATE NOT NULL,
        paga_em     DATETIME NULL,
        criada_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id_fatura),
        UNIQUE KEY uq_fatura_cartao_mes (cartao_id, referencia),
        KEY idx_fatura_vencimento (vence_em, paga_em),
        CONSTRAINT fk_fatura_cartao FOREIGN KEY (cartao_id) REFERENCES cartoes_corporativos (id_cartao) ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ── 3. Contas a pagar ────────────────────────────────────────────────── */
$m->titulo('3) Despesas, parcelas a pagar e pagamentos');

$m->criarTabela('despesas', "
    CREATE TABLE despesas (
        id_despesa        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        numero            VARCHAR(20) NOT NULL DEFAULT '',
        descricao         VARCHAR(200) NOT NULL,
        categoria         VARCHAR(30) NOT NULL,
        afeta_resultado   TINYINT(1) NOT NULL DEFAULT 1,
        fornecedor_id     BIGINT UNSIGNED NULL,
        origem            ENUM('MANUAL','PEDIDO_COMPRA') NOT NULL DEFAULT 'MANUAL',
        origem_id         BIGINT UNSIGNED NULL,
        valor_total       DECIMAL(12,2) NOT NULL,
        data_compra       DATE NOT NULL,
        meio_pagamento    ENUM('CARTAO_CREDITO','BOLETO','PIX','TRANSFERENCIA','DINHEIRO','CARTAO_DEBITO','OUTRO') NOT NULL,
        cartao_id         BIGINT UNSIGNED NULL,
        qtd_parcelas      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        status            ENUM('ATIVA','CANCELADA') NOT NULL DEFAULT 'ATIVA',
        observacoes       TEXT NULL,
        chave_idempotencia VARCHAR(80) NULL,
        pedido_ativo      BIGINT UNSIGNED GENERATED ALWAYS AS (
            CASE WHEN origem = 'PEDIDO_COMPRA' AND status = 'ATIVA' THEN origem_id ELSE NULL END
        ) STORED,
        criado_por        BIGINT UNSIGNED NULL,
        criada_em         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        cancelada_em      DATETIME NULL,
        cancelada_por     BIGINT UNSIGNED NULL,
        cancelamento_motivo VARCHAR(255) NULL,
        PRIMARY KEY (id_despesa),
        UNIQUE KEY uq_despesa_chave (chave_idempotencia),
        UNIQUE KEY uq_despesa_pedido_ativo (pedido_ativo),
        KEY idx_despesa_data (data_compra),
        KEY idx_despesa_fornecedor (fornecedor_id),
        KEY idx_despesa_cartao (cartao_id),
        CONSTRAINT fk_despesa_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedores (id_fornecedor) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_despesa_cartao     FOREIGN KEY (cartao_id) REFERENCES cartoes_corporativos (id_cartao) ON UPDATE CASCADE,
        CONSTRAINT fk_despesa_criador    FOREIGN KEY (criado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_despesa_cancelador FOREIGN KEY (cancelada_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$m->criarTabela('contas_pagar_parcelas', "
    CREATE TABLE contas_pagar_parcelas (
        id_conta_parcela BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        despesa_id       BIGINT UNSIGNED NOT NULL,
        numero           SMALLINT UNSIGNED NOT NULL,
        valor            DECIMAL(12,2) NOT NULL,
        vencimento       DATE NOT NULL,
        status           ENUM('PENDENTE','PAGA','CANCELADA') NOT NULL DEFAULT 'PENDENTE',
        fatura_id        BIGINT UNSIGNED NULL,
        pago_em          DATETIME NULL,
        pagamento_id     BIGINT UNSIGNED NULL,
        PRIMARY KEY (id_conta_parcela),
        UNIQUE KEY uq_cp_despesa_numero (despesa_id, numero),
        KEY idx_cp_status_venc (status, vencimento),
        KEY idx_cp_fatura (fatura_id),
        CONSTRAINT fk_cp_despesa FOREIGN KEY (despesa_id) REFERENCES despesas (id_despesa) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_cp_fatura  FOREIGN KEY (fatura_id) REFERENCES faturas_cartao (id_fatura) ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$m->criarTabela('pagamentos_pagar', "
    CREATE TABLE pagamentos_pagar (
        id_pagamento        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tipo                ENUM('PARCELA','FATURA') NOT NULL,
        parcela_id          BIGINT UNSIGNED NULL,
        fatura_id           BIGINT UNSIGNED NULL,
        valor               DECIMAL(12,2) NOT NULL,
        forma_pagamento     ENUM('DINHEIRO','PIX','CARTAO_DEBITO','BOLETO','TRANSFERENCIA','OUTRO') NOT NULL,
        pago_em             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        usuario_id          BIGINT UNSIGNED NULL,
        movimentacao_caixa_id BIGINT UNSIGNED NULL,
        estorno_caixa_id    BIGINT UNSIGNED NULL,
        observacao          VARCHAR(255) NULL,
        chave_idempotencia  VARCHAR(80) NOT NULL,
        estornado_em        DATETIME NULL,
        estornado_por       BIGINT UNSIGNED NULL,
        estorno_justificativa VARCHAR(255) NULL,
        alvo_ativo          VARCHAR(30) GENERATED ALWAYS AS (
            CASE WHEN estornado_em IS NULL THEN COALESCE(CONCAT('P', parcela_id), CONCAT('F', fatura_id)) ELSE NULL END
        ) STORED,
        PRIMARY KEY (id_pagamento),
        UNIQUE KEY uq_pagpagar_chave (chave_idempotencia),
        UNIQUE KEY uq_pagpagar_alvo_ativo (alvo_ativo),
        KEY idx_pagpagar_data (pago_em),
        CONSTRAINT fk_pagpagar_parcela  FOREIGN KEY (parcela_id) REFERENCES contas_pagar_parcelas (id_conta_parcela) ON UPDATE CASCADE,
        CONSTRAINT fk_pagpagar_fatura   FOREIGN KEY (fatura_id) REFERENCES faturas_cartao (id_fatura) ON UPDATE CASCADE,
        CONSTRAINT fk_pagpagar_usuario  FOREIGN KEY (usuario_id) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_pagpagar_caixa    FOREIGN KEY (movimentacao_caixa_id) REFERENCES movimentacoes_caixa (id_movimentacao) ON DELETE SET NULL,
        CONSTRAINT fk_pagpagar_estorno_caixa FOREIGN KEY (estorno_caixa_id) REFERENCES movimentacoes_caixa (id_movimentacao) ON DELETE SET NULL,
        CONSTRAINT fk_pagpagar_estornador FOREIGN KEY (estornado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$m->adicionarFk('contas_pagar_parcelas', 'fk_cp_pagamento', 'pagamento_id', 'pagamentos_pagar', 'id_pagamento', 'SET NULL');

/* ── 4. Comprovantes ──────────────────────────────────────────────────── */
$m->titulo('4) Comprovantes financeiros');

$m->criarTabela('comprovantes_financeiros', "
    CREATE TABLE comprovantes_financeiros (
        id_comprovante   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        despesa_id       BIGINT UNSIGNED NULL,
        pagamento_id     BIGINT UNSIGNED NULL,
        nome_original    VARCHAR(150) NOT NULL,
        caminho          VARCHAR(255) NOT NULL,
        mime             VARCHAR(60) NOT NULL,
        tamanho          INT UNSIGNED NOT NULL,
        enviado_por      BIGINT UNSIGNED NULL,
        enviado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id_comprovante),
        UNIQUE KEY uq_comprovante_caminho (caminho),
        KEY idx_comp_despesa (despesa_id),
        KEY idx_comp_pagamento (pagamento_id),
        CONSTRAINT fk_comp_despesa   FOREIGN KEY (despesa_id) REFERENCES despesas (id_despesa) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_comp_pagamento FOREIGN KEY (pagamento_id) REFERENCES pagamentos_pagar (id_pagamento) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_comp_usuario   FOREIGN KEY (enviado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ── 5. Pedido de compra → conta a pagar ──────────────────────────────── */
$m->titulo('5) Pedido de compra aponta para a despesa');

$m->adicionarFk('pedidos_compra', 'fk_pedido_despesa', 'conta_pagar_id', 'despesas', 'id_despesa', 'SET NULL');

$m->fim();
