<?php

/**
 * Etapa 5 — comunicação com o cliente, portal e pagamento online.
 *
 *  1. Consentimentos: registro (com origem, data e quem registrou) de que o cliente aceita receber
 *     mensagens por WhatsApp/e-mail. Só se acrescenta; o vigente é o mais recente.
 *  2. Central de conversas: `conversas` (uma aberta por canal+cliente+contato) e `mensagens`
 *     (entradas, saídas, notas internas e as automáticas — esta tabela também é a FILA de envio,
 *     com a chave de idempotência do fato que gerou a mensagem).
 *  3. Webhooks recebidos: cada evento externo é gravado uma vez (provedor + id do evento).
 *  4. Portal do cliente: tokens que guardam só o HASH (o link bruto existe uma vez, na mensagem),
 *     com validade, revogação e contagem de acesso; e o registro de cada decisão tomada pelo portal.
 *  5. Pagamento online: cobranças (uma ativa por parcela garantida pelo banco), as parcelas que
 *     cada cobrança cobre, e as marcas de que a parcela foi recebida online.
 *  6. Previsão de conclusão da OS (informada pela equipe, exibida ao cliente).
 *
 * Idempotente. Como rodar:
 *   /c/xampp/php/php.exe database/etapa5_comunicacao_portal.php
 *   DB_NAME=mecanize_db_test /c/xampp/php/php.exe database/etapa5_comunicacao_portal.php
 */

require_once __DIR__ . '/_migracao.php';

$m = new Migracao();

/* ── 1. Consentimentos ────────────────────────────────────────────────── */
$m->titulo('1) Consentimentos de comunicação');

$m->criarTabela('consentimentos_comunicacao', "
    CREATE TABLE consentimentos_comunicacao (
        id_consentimento BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        cliente_id       BIGINT UNSIGNED NOT NULL,
        canal            ENUM('WHATSAPP','EMAIL') NOT NULL,
        concedido        TINYINT(1) NOT NULL,
        origem           ENUM('BALCAO','TELEFONE','PORTAL','WHATSAPP','OUTRO') NOT NULL DEFAULT 'BALCAO',
        observacao       VARCHAR(255) NULL,
        registrado_por   BIGINT UNSIGNED NULL,
        registrado_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id_consentimento),
        KEY idx_consent_cliente_canal (cliente_id, canal, id_consentimento),
        CONSTRAINT fk_consent_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id_cliente) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_consent_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ── 2. Conversas e mensagens ─────────────────────────────────────────── */
$m->titulo('2) Central de conversas');

$m->criarTabela('conversas', "
    CREATE TABLE conversas (
        id_conversa        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        cliente_id         BIGINT UNSIGNED NULL,
        canal              ENUM('WHATSAPP','EMAIL','MANUAL') NOT NULL,
        identificador      VARCHAR(150) NULL,
        nome_contato       VARCHAR(150) NULL,
        veiculo_id         BIGINT UNSIGNED NULL,
        ocorrencia_id      BIGINT UNSIGNED NULL,
        orcamento_id       BIGINT UNSIGNED NULL,
        ordem_servico_id   BIGINT UNSIGNED NULL,
        responsavel_id     BIGINT UNSIGNED NULL,
        status             ENUM('ABERTA','AGUARDANDO_CLIENTE','ENCERRADA') NOT NULL DEFAULT 'ABERTA',
        assunto            VARCHAR(160) NULL,
        ultima_mensagem_em DATETIME NULL,
        ultima_entrada_em  DATETIME NULL,
        nao_lidas          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        aberta_unica       VARCHAR(220) GENERATED ALWAYS AS (
            CASE WHEN status <> 'ENCERRADA' THEN CONCAT(canal, '|', COALESCE(cliente_id, 0), '|', COALESCE(identificador, '')) ELSE NULL END
        ) STORED,
        criada_em          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizada_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id_conversa),
        UNIQUE KEY uq_conversa_aberta (aberta_unica),
        KEY idx_conversa_status (status, ultima_mensagem_em),
        KEY idx_conversa_cliente (cliente_id),
        KEY idx_conversa_responsavel (responsavel_id),
        CONSTRAINT fk_conversa_cliente     FOREIGN KEY (cliente_id) REFERENCES clientes (id_cliente) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_conversa_veiculo     FOREIGN KEY (veiculo_id) REFERENCES veiculos (id_veiculo) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_conversa_ocorrencia  FOREIGN KEY (ocorrencia_id) REFERENCES ocorrencias (id_ocorrencia) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_conversa_orcamento   FOREIGN KEY (orcamento_id) REFERENCES orcamentos (id_orcamento) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_conversa_os          FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico (id_ordem_servico) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_conversa_responsavel FOREIGN KEY (responsavel_id) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$m->criarTabela('mensagens', "
    CREATE TABLE mensagens (
        id_mensagem          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        conversa_id          BIGINT UNSIGNED NOT NULL,
        direcao              ENUM('ENTRADA','SAIDA','NOTA') NOT NULL,
        tipo                 ENUM('TEXTO','TEMPLATE','AUTOMATICA') NOT NULL DEFAULT 'TEXTO',
        evento               VARCHAR(40) NULL,
        canal                ENUM('WHATSAPP','EMAIL','MANUAL') NOT NULL,
        destino              VARCHAR(150) NULL,
        corpo                TEXT NOT NULL,
        status               ENUM('PENDENTE','ENVIADA','ENTREGUE','LIDA','FALHA','NAO_CONFIGURADO','SEM_CONSENTIMENTO','SEM_CONTATO','CANCELADA','RECEBIDA','REGISTRADA') NOT NULL,
        erro                 VARCHAR(255) NULL,
        autor_id             BIGINT UNSIGNED NULL,
        contexto_tipo        VARCHAR(20) NULL,
        contexto_id          BIGINT UNSIGNED NULL,
        provider_message_id  VARCHAR(160) NULL,
        chave_idempotencia   VARCHAR(190) NULL,
        tentativas           TINYINT UNSIGNED NOT NULL DEFAULT 0,
        enviada_em           DATETIME NULL,
        entregue_em          DATETIME NULL,
        lida_em              DATETIME NULL,
        criada_em            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id_mensagem),
        UNIQUE KEY uq_mensagem_provider (provider_message_id),
        UNIQUE KEY uq_mensagem_chave (chave_idempotencia),
        KEY idx_mensagem_conversa (conversa_id, id_mensagem),
        KEY idx_mensagem_fila (status, id_mensagem),
        CONSTRAINT fk_mensagem_conversa FOREIGN KEY (conversa_id) REFERENCES conversas (id_conversa) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_mensagem_autor    FOREIGN KEY (autor_id) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$m->adicionarColuna('mensagens', 'parametros_template', 'VARCHAR(500) NULL');

/* ── 3. Webhooks ──────────────────────────────────────────────────────── */
$m->titulo('3) Eventos externos (webhooks) recebidos');

$m->criarTabela('webhook_eventos', "
    CREATE TABLE webhook_eventos (
        id_evento    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        provedor     VARCHAR(30) NOT NULL,
        evento_id    VARCHAR(190) NOT NULL,
        tipo         VARCHAR(60) NULL,
        resultado    VARCHAR(160) NULL,
        recebido_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id_evento),
        UNIQUE KEY uq_webhook_evento (provedor, evento_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ── 4. Portal do cliente ─────────────────────────────────────────────── */
$m->titulo('4) Portal do cliente');

$m->criarTabela('portal_tokens', "
    CREATE TABLE portal_tokens (
        id_token        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        cliente_id      BIGINT UNSIGNED NOT NULL,
        escopo          ENUM('ORCAMENTO','OS','OCORRENCIA') NOT NULL,
        entidade_id     BIGINT UNSIGNED NOT NULL,
        token_hash      CHAR(64) NOT NULL,
        criado_por      BIGINT UNSIGNED NULL,
        criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expira_em       DATETIME NOT NULL,
        revogado_em     DATETIME NULL,
        ultimo_acesso_em DATETIME NULL,
        acessos         INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id_token),
        UNIQUE KEY uq_portal_token_hash (token_hash),
        KEY idx_portal_entidade (escopo, entidade_id),
        CONSTRAINT fk_portal_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id_cliente) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_portal_usuario FOREIGN KEY (criado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$m->criarTabela('decisoes_portal', "
    CREATE TABLE decisoes_portal (
        id_decisao   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        token_id     BIGINT UNSIGNED NOT NULL,
        cliente_id   BIGINT UNSIGNED NOT NULL,
        tipo         ENUM('ORCAMENTO','AUTORIZACAO') NOT NULL,
        entidade_id  BIGINT UNSIGNED NOT NULL,
        decisao      ENUM('APROVADO','RECUSADO') NOT NULL,
        motivo       VARCHAR(255) NULL,
        ip           VARCHAR(45) NULL,
        user_agent   VARCHAR(200) NULL,
        criada_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id_decisao),
        KEY idx_decisao_entidade (tipo, entidade_id),
        CONSTRAINT fk_decisao_token   FOREIGN KEY (token_id) REFERENCES portal_tokens (id_token) ON UPDATE CASCADE,
        CONSTRAINT fk_decisao_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id_cliente) ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ── 5. Pagamento online ──────────────────────────────────────────────── */
$m->titulo('5) Pagamento online');

$m->criarTabela('cobrancas_online', "
    CREATE TABLE cobrancas_online (
        id_cobranca          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        ordem_servico_id     BIGINT UNSIGNED NOT NULL,
        cliente_id           BIGINT UNSIGNED NOT NULL,
        provedor             VARCHAR(30) NOT NULL,
        provedor_cobranca_id VARCHAR(160) NULL,
        referencia           VARCHAR(80) NOT NULL,
        valor                DECIMAL(12,2) NOT NULL,
        max_parcelas         TINYINT UNSIGNED NOT NULL DEFAULT 1,
        parcelas_cartao      TINYINT UNSIGNED NULL,
        forma_pagamento      VARCHAR(40) NULL,
        status               ENUM('PENDENTE','PAGA','RECUSADA','CANCELADA','ESTORNADA','EXPIRADA') NOT NULL DEFAULT 'PENDENTE',
        detalhe_status       VARCHAR(200) NULL,
        checkout_url         VARCHAR(600) NULL,
        taxa_provedor        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        criada_por_token_id  BIGINT UNSIGNED NULL,
        criada_em            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        paga_em              DATETIME NULL,
        atualizada_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id_cobranca),
        UNIQUE KEY uq_cobranca_referencia (referencia),
        UNIQUE KEY uq_cobranca_provedor_id (provedor, provedor_cobranca_id),
        KEY idx_cobranca_os (ordem_servico_id, status),
        CONSTRAINT fk_cobranca_os      FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico (id_ordem_servico) ON UPDATE CASCADE,
        CONSTRAINT fk_cobranca_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id_cliente) ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$m->criarTabela('cobranca_parcelas', "
    CREATE TABLE cobranca_parcelas (
        id_cobranca_parcela BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        cobranca_id         BIGINT UNSIGNED NOT NULL,
        parcela_id          BIGINT UNSIGNED NOT NULL,
        ativo               TINYINT(1) NOT NULL DEFAULT 1,
        alvo_ativo          BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN ativo = 1 THEN parcela_id ELSE NULL END) STORED,
        PRIMARY KEY (id_cobranca_parcela),
        UNIQUE KEY uq_cobranca_parcela_ativa (alvo_ativo),
        KEY idx_cobparc_cobranca (cobranca_id),
        CONSTRAINT fk_cobparc_cobranca FOREIGN KEY (cobranca_id) REFERENCES cobrancas_online (id_cobranca) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_cobparc_parcela  FOREIGN KEY (parcela_id) REFERENCES ordem_servico_parcelas (id_parcela) ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$m->adicionarColuna('ordem_servico_parcelas', 'origem_recebimento', "ENUM('BALCAO','ONLINE') NOT NULL DEFAULT 'BALCAO'");
$m->adicionarColuna('ordem_servico_parcelas', 'cobranca_online_id', 'BIGINT UNSIGNED NULL');
$m->adicionarFk('ordem_servico_parcelas', 'fk_parcela_cobranca_online', 'cobranca_online_id', 'cobrancas_online', 'id_cobranca', 'SET NULL');

/* ── 6. Previsão de conclusão ─────────────────────────────────────────── */
$m->titulo('6) Previsão de conclusão da OS');

$m->adicionarColuna('ordens_servico', 'previsao_conclusao', 'DATETIME NULL');

$m->fim();
