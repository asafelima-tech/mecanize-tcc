<?php

/**
 * Etapa 2 — estrutura operacional: o caminho do veículo antes e durante a OS.
 *
 *  1. clientes: cadastro PROVISÓRIO (só primeiro nome + telefone) e a marca de
 *     unificação (quando o provisório é fundido num cadastro que já existia).
 *  2. marca/modelo "Não informado": o veículo de um atendimento rápido pode chegar
 *     sem marca/modelo conhecidos; as FKs são obrigatórias, então ele aponta para estes.
 *  3. ocorrencias: o relato do cliente + o veículo + a prioridade, do balcão até a OS.
 *  4. avaliacoes_tecnicas / avaliacao_itens: o que o técnico constatou e recomenda — a
 *     base técnica do orçamento (o atendente não redigita).
 *  5. autorizacoes_cliente: quem autorizou o quê, por qual canal, até que valor
 *     (orçamento aprovado, aceite com valor em aberto, autorização complementar).
 *  6. orcamentos ligados à ocorrência/avaliação + versões (histórico das edições).
 *  7. ordens_servico ligadas à ocorrência e com o valor autorizado pelo cliente.
 *
 * Idempotente. Como rodar:
 *   /c/xampp/php/php.exe database/etapa2_operacao.php
 *   DB_NAME=mecanize_db_test /c/xampp/php/php.exe database/etapa2_operacao.php
 */

require_once __DIR__ . '/_migracao.php';

$m = new Migracao();

/* ── 1. Cadastro provisório ───────────────────────────────────────────── */
$m->titulo('1) Clientes: cadastro provisório e unificação');

$m->adicionarColuna('clientes', 'cadastro_status', "ENUM('COMPLETO','PROVISORIO') NOT NULL DEFAULT 'COMPLETO'");
$m->adicionarColuna('clientes', 'unificado_em_id', 'BIGINT UNSIGNED NULL');
$m->adicionarColuna('clientes', 'unificado_em', 'DATETIME NULL');
$m->adicionarFk('clientes', 'fk_cliente_unificado_em', 'unificado_em_id', 'clientes', 'id_cliente', 'RESTRICT');
$m->adicionarIndice('clientes', 'idx_clientes_cadastro_status', '`cadastro_status`, `ativo`');

/* ── 2. Marca/modelo "Não informado" ──────────────────────────────────── */
$m->titulo('2) Marca e modelo "Não informado"');

$marca = $m->pdo->query("SELECT id_marca FROM marcas_veiculos WHERE nome = 'Não informado' LIMIT 1")->fetchColumn();

if ($marca === false) {
    $m->pdo->exec("INSERT INTO marcas_veiculos (nome) VALUES ('Não informado')");
    $marca = $m->pdo->lastInsertId();
    $m->feitas++;
    echo "  + marca 'Não informado'\n";
} else {
    $m->jaTinha("marca 'Não informado'");
}

$modelo = $m->pdo->prepare("SELECT id_modelo FROM modelos_veiculos WHERE marca_id = ? AND nome = 'Não informado' LIMIT 1");
$modelo->execute([$marca]);

if ($modelo->fetchColumn() === false) {
    $m->pdo->prepare("INSERT INTO modelos_veiculos (marca_id, nome) VALUES (?, 'Não informado')")->execute([$marca]);
    $m->feitas++;
    echo "  + modelo 'Não informado'\n";
} else {
    $m->jaTinha("modelo 'Não informado'");
}

$m->adicionarColuna('veiculos', 'dados_incompletos', 'TINYINT(1) NOT NULL DEFAULT 0');

/* ── 3. Ocorrências ───────────────────────────────────────────────────── */
$m->titulo('3) Ocorrências');

$m->criarTabela('ocorrencias', "
    CREATE TABLE ocorrencias (
        id_ocorrencia         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        numero                VARCHAR(20) NOT NULL DEFAULT '',
        origem                ENUM('PRESENCIAL','RAPIDO','REMOTO') NOT NULL DEFAULT 'PRESENCIAL',
        cliente_id            BIGINT UNSIGNED NOT NULL,
        veiculo_id            BIGINT UNSIGNED NOT NULL,
        relato                TEXT NOT NULL,
        observacoes           TEXT NULL,
        prioridade            ENUM('BAIXA','NORMAL','ALTA','URGENTE') NOT NULL DEFAULT 'NORMAL',
        urgencia_motivo       VARCHAR(255) NULL,
        urgencia_validada_por BIGINT UNSIGNED NULL,
        urgencia_validada_em  DATETIME NULL,
        status                ENUM('AGUARDANDO_AVALIACAO','EM_AVALIACAO','AGUARDANDO_ORCAMENTO',
                                   'AGUARDANDO_APROVACAO','EM_ESPERA','EM_EXECUCAO','ENCERRADA','CANCELADA')
                              NOT NULL DEFAULT 'AGUARDANDO_AVALIACAO',
        tecnico_id            BIGINT UNSIGNED NULL,
        criado_por            BIGINT UNSIGNED NULL,
        chegada_em            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        duracao_estimada_min  SMALLINT UNSIGNED NULL,
        ordem_manual          INT NULL,
        criada_em             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizada_em         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id_ocorrencia),
        KEY idx_ocorr_status (status, prioridade, chegada_em),
        KEY idx_ocorr_cliente (cliente_id),
        KEY idx_ocorr_veiculo (veiculo_id),
        KEY idx_ocorr_tecnico (tecnico_id),
        CONSTRAINT fk_ocorr_cliente  FOREIGN KEY (cliente_id) REFERENCES clientes (id_cliente) ON UPDATE CASCADE,
        CONSTRAINT fk_ocorr_veiculo  FOREIGN KEY (veiculo_id) REFERENCES veiculos (id_veiculo) ON UPDATE CASCADE,
        CONSTRAINT fk_ocorr_tecnico  FOREIGN KEY (tecnico_id) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_ocorr_criador  FOREIGN KEY (criado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_ocorr_urgencia FOREIGN KEY (urgencia_validada_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ── 4. Avaliação técnica ─────────────────────────────────────────────── */
$m->titulo('4) Avaliação técnica');

$m->criarTabela('avaliacoes_tecnicas', "
    CREATE TABLE avaliacoes_tecnicas (
        id_avaliacao         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        ocorrencia_id        BIGINT UNSIGNED NOT NULL,
        tecnico_id           BIGINT UNSIGNED NULL,
        versao               INT UNSIGNED NOT NULL DEFAULT 1,
        defeitos             TEXT NULL,
        observacoes          TEXT NULL,
        duracao_estimada_min SMALLINT UNSIGNED NULL,
        status               ENUM('EM_ANDAMENTO','CONCLUIDA') NOT NULL DEFAULT 'EM_ANDAMENTO',
        iniciada_em          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        concluida_em         DATETIME NULL,
        PRIMARY KEY (id_avaliacao),
        UNIQUE KEY uq_avaliacao_versao (ocorrencia_id, versao),
        KEY idx_avaliacao_tecnico (tecnico_id),
        CONSTRAINT fk_avaliacao_ocorrencia FOREIGN KEY (ocorrencia_id) REFERENCES ocorrencias (id_ocorrencia) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_avaliacao_tecnico    FOREIGN KEY (tecnico_id) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$m->criarTabela('avaliacao_itens', "
    CREATE TABLE avaliacao_itens (
        id_avaliacao_item BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        avaliacao_id      BIGINT UNSIGNED NOT NULL,
        tipo              ENUM('produto','servico') NOT NULL,
        produto_id        BIGINT UNSIGNED NULL,
        nome              VARCHAR(255) NOT NULL,
        categoria         VARCHAR(100) NULL,
        quantidade        DECIMAL(10,3) NOT NULL DEFAULT 1.000,
        preco             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        observacao        VARCHAR(255) NULL,
        PRIMARY KEY (id_avaliacao_item),
        KEY idx_avitem_avaliacao (avaliacao_id),
        CONSTRAINT fk_avitem_avaliacao FOREIGN KEY (avaliacao_id) REFERENCES avaliacoes_tecnicas (id_avaliacao) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_avitem_produto   FOREIGN KEY (produto_id) REFERENCES produtos (id_produto) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ── 5. Autorizações do cliente ───────────────────────────────────────── */
$m->titulo('5) Autorizações do cliente');

$m->criarTabela('autorizacoes_cliente', "
    CREATE TABLE autorizacoes_cliente (
        id_autorizacao    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        ocorrencia_id     BIGINT UNSIGNED NULL,
        ordem_servico_id  BIGINT UNSIGNED NULL,
        orcamento_id      BIGINT UNSIGNED NULL,
        cliente_id        BIGINT UNSIGNED NOT NULL,
        tipo              ENUM('ORCAMENTO','VALOR_ABERTO','COMPLEMENTAR') NOT NULL,
        status            ENUM('SOLICITADA','APROVADA','RECUSADA','REVOGADA') NOT NULL DEFAULT 'APROVADA',
        escopo            TEXT NOT NULL,
        limite_valor      DECIMAL(10,2) NULL,
        valor_adicional   DECIMAL(10,2) NULL,
        canal             ENUM('PRESENCIAL','TELEFONE','WHATSAPP','PORTAL','OUTRO') NOT NULL DEFAULT 'PRESENCIAL',
        evidencia         TEXT NULL,
        registrada_por    BIGINT UNSIGNED NULL,
        registrada_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        decidida_por      BIGINT UNSIGNED NULL,
        decidida_em       DATETIME NULL,
        PRIMARY KEY (id_autorizacao),
        KEY idx_autor_ocorrencia (ocorrencia_id),
        KEY idx_autor_os (ordem_servico_id, status),
        KEY idx_autor_orcamento (orcamento_id),
        KEY idx_autor_cliente (cliente_id),
        CONSTRAINT fk_autor_ocorrencia FOREIGN KEY (ocorrencia_id)    REFERENCES ocorrencias (id_ocorrencia)  ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_autor_os         FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico (id_ordem_servico) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_autor_orcamento  FOREIGN KEY (orcamento_id)     REFERENCES orcamentos (id_orcamento)    ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_autor_cliente    FOREIGN KEY (cliente_id)       REFERENCES clientes (id_cliente)        ON UPDATE CASCADE,
        CONSTRAINT fk_autor_registrou  FOREIGN KEY (registrada_por)   REFERENCES usuarios (id_usuario)        ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_autor_decidiu    FOREIGN KEY (decidida_por)     REFERENCES usuarios (id_usuario)        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ── 6. Orçamentos ligados à ocorrência + versões ─────────────────────── */
$m->titulo('6) Orçamentos: vínculo com a ocorrência e versões');

$m->adicionarColuna('orcamentos', 'ocorrencia_id', 'BIGINT UNSIGNED NULL');
$m->adicionarColuna('orcamentos', 'avaliacao_id', 'BIGINT UNSIGNED NULL');
$m->adicionarColuna('orcamentos', 'enviado_em', 'DATETIME NULL');
$m->adicionarColuna('orcamentos', 'versao', 'INT UNSIGNED NOT NULL DEFAULT 1');
$m->adicionarFk('orcamentos', 'fk_orc_ocorrencia', 'ocorrencia_id', 'ocorrencias', 'id_ocorrencia', 'SET NULL');
$m->adicionarFk('orcamentos', 'fk_orc_avaliacao', 'avaliacao_id', 'avaliacoes_tecnicas', 'id_avaliacao', 'SET NULL');
$m->adicionarIndice('orcamentos', 'idx_orc_ocorrencia', '`ocorrencia_id`');

$m->criarTabela('orcamento_versoes', "
    CREATE TABLE orcamento_versoes (
        id_versao    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        orcamento_id BIGINT UNSIGNED NOT NULL,
        versao       INT UNSIGNED NOT NULL,
        dados        MEDIUMTEXT NOT NULL,
        criado_por   BIGINT UNSIGNED NULL,
        criado_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id_versao),
        UNIQUE KEY uq_orc_versao (orcamento_id, versao),
        CONSTRAINT fk_orcversao_orc FOREIGN KEY (orcamento_id) REFERENCES orcamentos (id_orcamento) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_orcversao_user FOREIGN KEY (criado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ── 7. OS ligada à ocorrência e ao valor autorizado ──────────────────── */
$m->titulo('7) Ordens de serviço: ocorrência e valor autorizado');

$m->adicionarColuna('ordens_servico', 'ocorrencia_id', 'BIGINT UNSIGNED NULL');
$m->adicionarColuna('ordens_servico', 'valor_autorizado', 'DECIMAL(10,2) NULL');
$m->adicionarFk('ordens_servico', 'fk_os_ocorrencia', 'ocorrencia_id', 'ocorrencias', 'id_ocorrencia', 'SET NULL');
$m->adicionarIndice('ordens_servico', 'uq_os_ocorrencia', '`ocorrencia_id`', true);

$m->fim();
