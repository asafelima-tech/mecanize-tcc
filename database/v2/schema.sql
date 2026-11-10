-- =====================================================================
-- Mecanize v2 — esquema simplificado multi-oficina (SaaS)
-- Aprovado em 10/11/2026. Aplicado por database/v2/migrar.php num banco NOVO.
-- MySQL 8.0.16+ (CHECK constraints são aplicadas a partir dessa versão).
--
-- Regra de isolamento: toda tabela de dados de oficina tem `oficina_id`
-- NOT NULL e UNIQUE (oficina_id, id). Toda referência entre essas tabelas
-- é uma FK COMPOSTA (oficina_id, x_id) → pai(oficina_id, id): o banco
-- recusa qualquer vínculo entre registros de oficinas diferentes.
-- Exclusões: RESTRICT em tudo (cadastros são desativados, não apagados;
-- OS e lançamentos são cancelados, não apagados) → sem registros órfãos.
-- =====================================================================

SET NAMES utf8mb4;

-- 1. OFICINAS — inquilino, dados da oficina, plano e licença ------------
CREATE TABLE oficinas (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome               VARCHAR(150) NOT NULL,
  cnpj               VARCHAR(14)  NULL,
  email              VARCHAR(150) NULL,
  telefone           VARCHAR(20)  NULL,
  endereco           VARCHAR(255) NULL,
  cidade             VARCHAR(100) NULL,
  uf                 CHAR(2)      NULL,
  cep                VARCHAR(8)   NULL,
  horario            VARCHAR(150) NULL,
  logo_caminho       VARCHAR(255) NULL,
  plano              VARCHAR(30)  NOT NULL DEFAULT 'BASICO',   -- limites do plano ficam em constante no código
  limite_usuarios    INT UNSIGNED NOT NULL DEFAULT 3,
  status             ENUM('TESTE','ATIVA','SUSPENSA','CANCELADA') NOT NULL DEFAULT 'TESTE',
  licenca_valida_ate DATE         NOT NULL,
  proximo_numero_os  INT UNSIGNED NOT NULL DEFAULT 1,           -- numeração própria e sem corrida (SELECT ... FOR UPDATE)
  criado_em          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_oficinas_cnpj (cnpj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. USUARIOS — login, perfil, comissão, recuperação de senha ----------
CREATE TABLE usuarios (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  oficina_id          BIGINT UNSIGNED NULL,      -- NULL somente para o perfil PLATAFORMA (gestão do SaaS)
  nome                VARCHAR(120) NOT NULL,
  email               VARCHAR(150) NOT NULL,
  telefone            VARCHAR(20)  NULL,
  senha_hash          VARCHAR(255) NOT NULL,
  perfil              ENUM('PLATAFORMA','ADMIN','ATENDENTE','TECNICO') NOT NULL,
  ativo               TINYINT(1)   NOT NULL DEFAULT 1,
  tentativas_login    INT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_ate       DATETIME     NULL,
  comissao_tipo       ENUM('NENHUMA','PERCENTUAL_SERVICOS','PERCENTUAL_PECAS','PERCENTUAL_TOTAL') NOT NULL DEFAULT 'NENHUMA',
  comissao_percentual DECIMAL(5,2) NOT NULL DEFAULT 0,
  reset_token_hash    CHAR(64)     NULL,
  reset_expira_em     DATETIME     NULL,
  criado_em           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usuarios_email (email),
  UNIQUE KEY uq_usuarios_tenant (oficina_id, id),
  UNIQUE KEY uq_usuarios_reset (reset_token_hash),
  CONSTRAINT fk_usuarios_oficina FOREIGN KEY (oficina_id) REFERENCES oficinas(id),
  CONSTRAINT ck_usuarios_plataforma CHECK ((perfil = 'PLATAFORMA') = (oficina_id IS NULL)),
  CONSTRAINT ck_usuarios_comissao CHECK (comissao_percentual BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. CLIENTES ------------------------------------------------------------
CREATE TABLE clientes (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  oficina_id     BIGINT UNSIGNED NOT NULL,
  tipo_pessoa    ENUM('PF','PJ') NOT NULL,
  nome           VARCHAR(150) NOT NULL,
  documento      VARCHAR(14)  NULL,           -- CPF ou CNPJ, só dígitos
  email          VARCHAR(150) NULL,
  telefone       VARCHAR(20)  NULL,
  endereco       VARCHAR(255) NULL,
  bairro         VARCHAR(100) NULL,
  cidade         VARCHAR(100) NULL,
  uf             CHAR(2)      NULL,
  cep            VARCHAR(8)   NULL,
  aceita_contato TINYINT(1)   NOT NULL DEFAULT 1,
  observacoes    TEXT         NULL,
  ativo          TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_clientes_tenant (oficina_id, id),
  UNIQUE KEY uq_clientes_documento (oficina_id, documento),
  KEY ix_clientes_nome (oficina_id, nome),
  CONSTRAINT fk_clientes_oficina FOREIGN KEY (oficina_id) REFERENCES oficinas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. MODELOS_VEICULOS — catálogo GLOBAL de referência (sem dados de oficina)
CREATE TABLE modelos_veiculos (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  marca        VARCHAR(60)  NOT NULL,
  nome         VARCHAR(80)  NOT NULL,
  combustiveis VARCHAR(100) NULL,
  UNIQUE KEY uq_modelos (marca, nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. VEICULOS ------------------------------------------------------------
CREATE TABLE veiculos (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  oficina_id        BIGINT UNSIGNED NOT NULL,
  cliente_id        BIGINT UNSIGNED NOT NULL,
  modelo_id         BIGINT UNSIGNED NULL,     -- catálogo global
  modelo_texto      VARCHAR(120) NULL,        -- quando o modelo não está no catálogo (substitui "criar modelo")
  placa             VARCHAR(10)  NOT NULL,
  placa_normalizada VARCHAR(7)   NOT NULL,
  ano               SMALLINT UNSIGNED NULL,
  cor               VARCHAR(30)  NULL,
  combustivel       VARCHAR(30)  NULL,
  observacoes       TEXT         NULL,
  ativo             TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_veiculos_tenant (oficina_id, id),
  UNIQUE KEY uq_veiculos_placa (oficina_id, placa_normalizada),
  CONSTRAINT fk_veiculos_cliente FOREIGN KEY (oficina_id, cliente_id) REFERENCES clientes(oficina_id, id),
  CONSTRAINT fk_veiculos_modelo  FOREIGN KEY (modelo_id) REFERENCES modelos_veiculos(id),
  CONSTRAINT ck_veiculos_modelo  CHECK (modelo_id IS NOT NULL OR modelo_texto IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. FORNECEDORES --------------------------------------------------------
CREATE TABLE fornecedores (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  oficina_id  BIGINT UNSIGNED NOT NULL,
  nome        VARCHAR(150) NOT NULL,
  documento   VARCHAR(14)  NULL,
  contato     VARCHAR(100) NULL,
  telefone    VARCHAR(20)  NULL,
  email       VARCHAR(150) NULL,
  observacoes TEXT         NULL,
  ativo       TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fornecedores_tenant (oficina_id, id),
  UNIQUE KEY uq_fornecedores_documento (oficina_id, documento),
  CONSTRAINT fk_fornecedores_oficina FOREIGN KEY (oficina_id) REFERENCES oficinas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. PRODUTOS — catálogo de PEÇAS e SERVIÇOS ----------------------------
CREATE TABLE produtos (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  oficina_id     BIGINT UNSIGNED NOT NULL,
  tipo           ENUM('PECA','SERVICO') NOT NULL,
  nome           VARCHAR(150)  NOT NULL,
  categoria      VARCHAR(60)   NULL,
  codigo_interno VARCHAR(40)   NULL,
  codigo_barras  VARCHAR(40)   NULL,
  marca          VARCHAR(60)   NULL,
  descricao      TEXT          NULL,
  localizacao    VARCHAR(60)   NULL,
  estoque_atual  DECIMAL(10,3) NOT NULL DEFAULT 0,  -- saldo = soma de movimentacoes_estoque.delta (conferido em teste)
  estoque_minimo DECIMAL(10,3) NOT NULL DEFAULT 0,
  preco_custo    DECIMAL(12,2) NOT NULL DEFAULT 0,
  preco_venda    DECIMAL(12,2) NOT NULL DEFAULT 0,
  ativo          TINYINT(1)    NOT NULL DEFAULT 1,
  criado_em      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em  DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_produtos_tenant (oficina_id, id),
  UNIQUE KEY uq_produtos_codigo (oficina_id, codigo_interno),
  KEY ix_produtos_nome (oficina_id, tipo, nome),
  CONSTRAINT fk_produtos_oficina FOREIGN KEY (oficina_id) REFERENCES oficinas(id),
  CONSTRAINT ck_produtos_servico_sem_estoque CHECK (tipo = 'PECA' OR estoque_atual = 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. ORDENS_SERVICO — orçamento + OS no mesmo registro ------------------
CREATE TABLE ordens_servico (
  id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  oficina_id           BIGINT UNSIGNED NOT NULL,
  numero               INT UNSIGNED  NOT NULL,
  cliente_id           BIGINT UNSIGNED NOT NULL,
  veiculo_id           BIGINT UNSIGNED NOT NULL,
  tecnico_id           BIGINT UNSIGNED NULL,
  status               ENUM('ORCAMENTO','APROVADA','EM_EXECUCAO','AGUARDANDO_PECA','PRONTA','ENTREGUE','RECUSADA','CANCELADA')
                       NOT NULL DEFAULT 'ORCAMENTO',
  km_veiculo           INT UNSIGNED  NULL,
  defeito_relatado     TEXT          NULL,
  diagnostico          TEXT          NULL,
  observacoes          TEXT          NULL,
  observacoes_internas TEXT          NULL,
  checklist_entrega    TEXT          NULL,
  validade_orcamento   DATE          NULL,
  previsao_conclusao   DATETIME      NULL,
  -- aprovação / recusa do orçamento
  aprovado_em          DATETIME      NULL,
  aprovado_por_nome    VARCHAR(120)  NULL,
  assinatura_cliente   MEDIUMTEXT    NULL,
  valor_aprovado       DECIMAL(12,2) NULL,   -- total congelado no momento da aprovação
  recusado_em          DATETIME      NULL,
  motivo_recusa        VARCHAR(255)  NULL,
  -- execução / entrega
  concluido_em         DATETIME      NULL,
  conferido_por        BIGINT UNSIGNED NULL,
  conferido_em         DATETIME      NULL,
  entregue_em          DATETIME      NULL,
  cancelado_em         DATETIME      NULL,
  motivo_cancelamento  VARCHAR(255)  NULL,
  -- totais (recalculados a partir dos itens na mesma transação; conferidos em teste)
  valor_pecas          DECIMAL(12,2) NOT NULL DEFAULT 0,
  valor_servicos       DECIMAL(12,2) NOT NULL DEFAULT 0,
  valor_desconto       DECIMAL(12,2) NOT NULL DEFAULT 0,
  valor_total          DECIMAL(12,2) NOT NULL DEFAULT 0,
  token_rastreio       VARCHAR(64)   NULL,   -- link público de acompanhamento (alternativa simples ao portal)
  criado_por           BIGINT UNSIGNED NULL,      -- NULL só em OS migradas da v1 (a v1 não registrava o autor)
  criado_em            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em        DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_os_tenant (oficina_id, id),
  UNIQUE KEY uq_os_numero (oficina_id, numero),
  UNIQUE KEY uq_os_token (token_rastreio),
  KEY ix_os_status (oficina_id, status),
  KEY ix_os_veiculo (oficina_id, veiculo_id),
  CONSTRAINT fk_os_cliente   FOREIGN KEY (oficina_id, cliente_id)    REFERENCES clientes(oficina_id, id),
  CONSTRAINT fk_os_veiculo   FOREIGN KEY (oficina_id, veiculo_id)    REFERENCES veiculos(oficina_id, id),
  CONSTRAINT fk_os_tecnico   FOREIGN KEY (oficina_id, tecnico_id)    REFERENCES usuarios(oficina_id, id),
  CONSTRAINT fk_os_conferido FOREIGN KEY (oficina_id, conferido_por) REFERENCES usuarios(oficina_id, id),
  CONSTRAINT fk_os_criado    FOREIGN KEY (oficina_id, criado_por)    REFERENCES usuarios(oficina_id, id),
  CONSTRAINT ck_os_recusa    CHECK (status <> 'RECUSADA' OR recusado_em IS NOT NULL),
  CONSTRAINT ck_os_aprovacao CHECK (status IN ('ORCAMENTO','RECUSADA','CANCELADA') OR aprovado_em IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. ORDEM_SERVICO_ITENS — peças e serviços com valores congelados -------
CREATE TABLE ordem_servico_itens (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  oficina_id          BIGINT UNSIGNED NOT NULL,
  ordem_servico_id    BIGINT UNSIGNED NOT NULL,
  tipo                ENUM('PECA','SERVICO') NOT NULL,
  origem              ENUM('ORCAMENTO','ADICIONAL') NOT NULL DEFAULT 'ORCAMENTO',  -- adicional = incluído após a aprovação
  produto_id          BIGINT UNSIGNED NULL,       -- referência ao catálogo; os campos abaixo são a cópia histórica
  descricao           VARCHAR(200)  NOT NULL,
  categoria           VARCHAR(60)   NULL,
  quantidade          DECIMAL(10,3) NOT NULL,
  valor_unitario      DECIMAL(12,2) NOT NULL,
  desconto            DECIMAL(12,2) NOT NULL DEFAULT 0,
  valor_total         DECIMAL(12,2) GENERATED ALWAYS AS (ROUND(quantidade * valor_unitario, 2) - desconto) STORED,
  custo_unitario      DECIMAL(12,2) NULL,         -- custo da peça na data (margem nos relatórios)
  tecnico_id          BIGINT UNSIGNED NULL,
  comissao_percentual DECIMAL(5,2)  NOT NULL DEFAULT 0,
  comissao_valor      DECIMAL(12,2) NOT NULL DEFAULT 0,
  comissao_lancamento_id BIGINT UNSIGNED NULL,    -- lançamento a pagar que quitou esta comissão
  estoque_status      ENUM('NAO_SE_APLICA','PENDENTE','BAIXADO','DEVOLVIDO') NOT NULL DEFAULT 'NAO_SE_APLICA',
  executado           TINYINT(1)    NOT NULL DEFAULT 0,
  criado_em           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_osi_tenant (oficina_id, id),
  KEY ix_osi_os (oficina_id, ordem_servico_id),
  CONSTRAINT fk_osi_os      FOREIGN KEY (oficina_id, ordem_servico_id) REFERENCES ordens_servico(oficina_id, id),
  CONSTRAINT fk_osi_produto FOREIGN KEY (oficina_id, produto_id)       REFERENCES produtos(oficina_id, id),
  CONSTRAINT fk_osi_tecnico FOREIGN KEY (oficina_id, tecnico_id)       REFERENCES usuarios(oficina_id, id),
  CONSTRAINT ck_osi_qtd      CHECK (quantidade > 0),
  CONSTRAINT ck_osi_valores  CHECK (valor_unitario >= 0 AND desconto >= 0 AND desconto <= ROUND(quantidade * valor_unitario, 2)),
  CONSTRAINT ck_osi_estoque  CHECK ((tipo = 'SERVICO') = (estoque_status = 'NAO_SE_APLICA'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. AGENDAMENTOS -------------------------------------------------------
CREATE TABLE agendamentos (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  oficina_id       BIGINT UNSIGNED NOT NULL,
  cliente_id       BIGINT UNSIGNED NOT NULL,
  veiculo_id       BIGINT UNSIGNED NULL,
  tecnico_id       BIGINT UNSIGNED NULL,
  data_hora        DATETIME     NOT NULL,
  duracao_min      SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  servico          VARCHAR(200) NOT NULL,
  observacoes      TEXT         NULL,
  status           ENUM('AGENDADO','CONFIRMADO','CONCLUIDO','CANCELADO','FALTOU') NOT NULL DEFAULT 'AGENDADO',
  ordem_servico_id BIGINT UNSIGNED NULL,
  criado_por       BIGINT UNSIGNED NOT NULL,
  criado_em        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ag_tenant (oficina_id, id),
  KEY ix_ag_data (oficina_id, data_hora),
  CONSTRAINT fk_ag_cliente FOREIGN KEY (oficina_id, cliente_id)       REFERENCES clientes(oficina_id, id),
  CONSTRAINT fk_ag_veiculo FOREIGN KEY (oficina_id, veiculo_id)       REFERENCES veiculos(oficina_id, id),
  CONSTRAINT fk_ag_tecnico FOREIGN KEY (oficina_id, tecnico_id)       REFERENCES usuarios(oficina_id, id),
  CONSTRAINT fk_ag_os      FOREIGN KEY (oficina_id, ordem_servico_id) REFERENCES ordens_servico(oficina_id, id),
  CONSTRAINT fk_ag_criado  FOREIGN KEY (oficina_id, criado_por)       REFERENCES usuarios(oficina_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. CAIXAS — sessões de caixa ------------------------------------------
CREATE TABLE caixas (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  oficina_id            BIGINT UNSIGNED NOT NULL,
  status                ENUM('ABERTO','FECHADO') NOT NULL DEFAULT 'ABERTO',
  aberto_por            BIGINT UNSIGNED NOT NULL,
  aberto_em             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  valor_abertura        DECIMAL(12,2) NOT NULL DEFAULT 0,
  observacao_abertura   VARCHAR(255)  NULL,
  fechado_por           BIGINT UNSIGNED NULL,
  fechado_em            DATETIME      NULL,
  valor_esperado        DECIMAL(12,2) NULL,
  valor_fechamento      DECIMAL(12,2) NULL,   -- valor contado na gaveta
  observacao_fechamento VARCHAR(255)  NULL,
  aberto_unico          TINYINT GENERATED ALWAYS AS (IF(status = 'ABERTO', 1, NULL)) STORED,
  UNIQUE KEY uq_caixas_tenant (oficina_id, id),
  UNIQUE KEY uq_caixas_um_aberto (oficina_id, aberto_unico),  -- no máximo 1 caixa aberto por oficina
  CONSTRAINT fk_caixas_aberto  FOREIGN KEY (oficina_id, aberto_por)  REFERENCES usuarios(oficina_id, id),
  CONSTRAINT fk_caixas_fechado FOREIGN KEY (oficina_id, fechado_por) REFERENCES usuarios(oficina_id, id),
  CONSTRAINT ck_caixas_fecho   CHECK (status = 'ABERTO' OR (fechado_em IS NOT NULL AND valor_fechamento IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. LANCAMENTOS — títulos a receber / a pagar (uma linha por parcela) --
CREATE TABLE lancamentos (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  oficina_id       BIGINT UNSIGNED NOT NULL,
  tipo             ENUM('RECEBER','PAGAR') NOT NULL,
  categoria        VARCHAR(60)   NOT NULL,           -- ex.: SERVICOS, PECAS, ALUGUEL, COMISSAO, FORNECEDOR, SALARIO
  descricao        VARCHAR(200)  NOT NULL,
  grupo_id         BIGINT UNSIGNED NULL,             -- parcela 1 do parcelamento (NULL na própria parcela 1 / à vista)
  parcela_numero   SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  parcela_total    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  valor            DECIMAL(12,2) NOT NULL,           -- valor original da parcela
  desconto         DECIMAL(12,2) NOT NULL DEFAULT 0,
  acrescimo        DECIMAL(12,2) NOT NULL DEFAULT 0, -- juros, multa, taxa de parcelamento
  valor_pago       DECIMAL(12,2) NOT NULL DEFAULT 0, -- = baixas − estornos (recalculado na mesma transação; conferido em teste)
  competencia      DATE          NOT NULL,           -- mês do resultado (relatórios por competência)
  vencimento       DATE          NOT NULL,
  status           ENUM('ABERTO','PARCIAL','PAGO','CANCELADO') NOT NULL DEFAULT 'ABERTO',
  forma_prevista   ENUM('DINHEIRO','PIX','CARTAO_DEBITO','CARTAO_CREDITO','BOLETO','TRANSFERENCIA','OUTRO') NULL,
  ordem_servico_id BIGINT UNSIGNED NULL,
  cliente_id       BIGINT UNSIGNED NULL,
  fornecedor_id    BIGINT UNSIGNED NULL,
  funcionario_id   BIGINT UNSIGNED NULL,             -- comissões e salários
  documento        VARCHAR(60)   NULL,               -- nº da nota / boleto
  observacao       VARCHAR(255)  NULL,
  criado_por       BIGINT UNSIGNED NOT NULL,
  criado_em        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cancelado_por    BIGINT UNSIGNED NULL,
  cancelado_em     DATETIME      NULL,
  motivo_cancelamento VARCHAR(255) NULL,
  UNIQUE KEY uq_lanc_tenant (oficina_id, id),
  KEY ix_lanc_venc (oficina_id, tipo, status, vencimento),
  KEY ix_lanc_comp (oficina_id, tipo, competencia),
  CONSTRAINT fk_lanc_grupo       FOREIGN KEY (oficina_id, grupo_id)         REFERENCES lancamentos(oficina_id, id),
  CONSTRAINT fk_lanc_os          FOREIGN KEY (oficina_id, ordem_servico_id) REFERENCES ordens_servico(oficina_id, id),
  CONSTRAINT fk_lanc_cliente     FOREIGN KEY (oficina_id, cliente_id)       REFERENCES clientes(oficina_id, id),
  CONSTRAINT fk_lanc_fornecedor  FOREIGN KEY (oficina_id, fornecedor_id)    REFERENCES fornecedores(oficina_id, id),
  CONSTRAINT fk_lanc_funcionario FOREIGN KEY (oficina_id, funcionario_id)   REFERENCES usuarios(oficina_id, id),
  CONSTRAINT fk_lanc_criado      FOREIGN KEY (oficina_id, criado_por)       REFERENCES usuarios(oficina_id, id),
  CONSTRAINT fk_lanc_cancelado   FOREIGN KEY (oficina_id, cancelado_por)    REFERENCES usuarios(oficina_id, id),
  CONSTRAINT ck_lanc_valores   CHECK (valor > 0 AND desconto >= 0 AND acrescimo >= 0 AND desconto <= valor + acrescimo),
  CONSTRAINT ck_lanc_pago      CHECK (valor_pago >= 0 AND valor_pago <= valor - desconto + acrescimo),
  CONSTRAINT ck_lanc_parcela   CHECK (parcela_numero BETWEEN 1 AND parcela_total),
  CONSTRAINT ck_lanc_status    CHECK (
       (status = 'ABERTO'    AND valor_pago = 0)
    OR (status = 'PARCIAL'   AND valor_pago > 0 AND valor_pago < valor - desconto + acrescimo)
    OR (status = 'PAGO'      AND valor_pago = valor - desconto + acrescimo)
    OR (status = 'CANCELADO' AND valor_pago = 0 AND cancelado_em IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE ordem_servico_itens
  ADD CONSTRAINT fk_osi_comissao FOREIGN KEY (oficina_id, comissao_lancamento_id) REFERENCES lancamentos(oficina_id, id);

-- 13. MOVIMENTACOES_FINANCEIRAS — todo dinheiro que efetivamente entrou/saiu
--     (baixas totais/parciais, estornos, sangria e suprimento de caixa). Imutável:
--     correção = novo registro de ESTORNO apontando para o original.
CREATE TABLE movimentacoes_financeiras (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  oficina_id         BIGINT UNSIGNED NOT NULL,
  natureza           ENUM('BAIXA','ESTORNO','SUPRIMENTO','SANGRIA') NOT NULL,
  direcao            ENUM('ENTRADA','SAIDA') NOT NULL,
  valor              DECIMAL(12,2) NOT NULL,
  taxa               DECIMAL(12,2) NOT NULL DEFAULT 0,   -- taxa de maquininha etc.
  forma_pagamento    ENUM('DINHEIRO','PIX','CARTAO_DEBITO','CARTAO_CREDITO','BOLETO','TRANSFERENCIA','OUTRO') NOT NULL,
  lancamento_id      BIGINT UNSIGNED NULL,
  caixa_id           BIGINT UNSIGNED NULL,
  estorno_de_id      BIGINT UNSIGNED NULL,
  data_movimento     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  descricao          VARCHAR(200)  NULL,
  usuario_id         BIGINT UNSIGNED NOT NULL,
  chave_idempotencia VARCHAR(64)   NULL,                 -- impede duplo clique gerar dois pagamentos
  criado_em          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mf_tenant (oficina_id, id),
  UNIQUE KEY uq_mf_idem (oficina_id, chave_idempotencia),
  UNIQUE KEY uq_mf_estorno_unico (estorno_de_id),        -- cada movimento só pode ser estornado uma vez
  KEY ix_mf_data (oficina_id, data_movimento),
  KEY ix_mf_caixa (oficina_id, caixa_id),
  CONSTRAINT fk_mf_lanc    FOREIGN KEY (oficina_id, lancamento_id) REFERENCES lancamentos(oficina_id, id),
  CONSTRAINT fk_mf_caixa   FOREIGN KEY (oficina_id, caixa_id)      REFERENCES caixas(oficina_id, id),
  CONSTRAINT fk_mf_estorno FOREIGN KEY (oficina_id, estorno_de_id) REFERENCES movimentacoes_financeiras(oficina_id, id),
  CONSTRAINT fk_mf_usuario FOREIGN KEY (oficina_id, usuario_id)    REFERENCES usuarios(oficina_id, id),
  CONSTRAINT ck_mf_valor   CHECK (valor > 0 AND taxa >= 0 AND taxa <= valor),
  CONSTRAINT ck_mf_vinculo CHECK (
       (natureza = 'BAIXA'      AND lancamento_id IS NOT NULL AND estorno_de_id IS NULL)
    OR (natureza = 'ESTORNO'    AND lancamento_id IS NOT NULL AND estorno_de_id IS NOT NULL)
    OR (natureza = 'SUPRIMENTO' AND lancamento_id IS NULL AND caixa_id IS NOT NULL AND direcao = 'ENTRADA' AND forma_pagamento = 'DINHEIRO')
    OR (natureza = 'SANGRIA'    AND lancamento_id IS NULL AND caixa_id IS NOT NULL AND direcao = 'SAIDA'   AND forma_pagamento = 'DINHEIRO')),
  CONSTRAINT ck_mf_dinheiro CHECK (forma_pagamento <> 'DINHEIRO' OR caixa_id IS NOT NULL)  -- dinheiro sempre passa pela gaveta
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. MOVIMENTACOES_ESTOQUE — livro de estoque imutável ------------------
CREATE TABLE movimentacoes_estoque (
  id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  oficina_id             BIGINT UNSIGNED NOT NULL,
  produto_id             BIGINT UNSIGNED NOT NULL,
  motivo                 ENUM('COMPRA','SAIDA_OS','DEVOLUCAO_OS','AJUSTE','ESTORNO') NOT NULL,
  delta                  DECIMAL(10,3) NOT NULL,     -- + entra, − sai
  custo_unitario         DECIMAL(12,2) NULL,
  ordem_servico_item_id  BIGINT UNSIGNED NULL,
  fornecedor_id          BIGINT UNSIGNED NULL,
  documento              VARCHAR(60)   NULL,         -- nº da nota de entrada
  lancamento_id          BIGINT UNSIGNED NULL,       -- conta a pagar gerada pela entrada
  estorno_de_id          BIGINT UNSIGNED NULL,
  observacao             VARCHAR(255)  NULL,
  usuario_id             BIGINT UNSIGNED NULL,   -- NULL só em registros migrados da v1 sem autor
  criado_em              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_me_tenant (oficina_id, id),
  UNIQUE KEY uq_me_estorno_unico (estorno_de_id),
  KEY ix_me_produto (oficina_id, produto_id, criado_em),
  CONSTRAINT fk_me_produto    FOREIGN KEY (oficina_id, produto_id)            REFERENCES produtos(oficina_id, id),
  CONSTRAINT fk_me_item       FOREIGN KEY (oficina_id, ordem_servico_item_id) REFERENCES ordem_servico_itens(oficina_id, id),
  CONSTRAINT fk_me_fornecedor FOREIGN KEY (oficina_id, fornecedor_id)         REFERENCES fornecedores(oficina_id, id),
  CONSTRAINT fk_me_lanc       FOREIGN KEY (oficina_id, lancamento_id)         REFERENCES lancamentos(oficina_id, id),
  CONSTRAINT fk_me_estorno    FOREIGN KEY (oficina_id, estorno_de_id)         REFERENCES movimentacoes_estoque(oficina_id, id),
  CONSTRAINT fk_me_usuario    FOREIGN KEY (oficina_id, usuario_id)            REFERENCES usuarios(oficina_id, id),
  CONSTRAINT ck_me_delta      CHECK (delta <> 0),
  CONSTRAINT ck_me_motivo     CHECK (
       (motivo = 'COMPRA'       AND delta > 0)
    OR (motivo = 'SAIDA_OS'     AND delta < 0 AND ordem_servico_item_id IS NOT NULL)
    OR (motivo = 'DEVOLUCAO_OS' AND delta > 0 AND ordem_servico_item_id IS NOT NULL)
    OR (motivo = 'AJUSTE'       AND observacao IS NOT NULL)
    OR (motivo = 'ESTORNO'      AND estorno_de_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. ANEXOS — fotos da OS e comprovantes financeiros --------------------
--     FK real para cada dono possível (sem "entidade + id" solto) → sem órfãos.
CREATE TABLE anexos (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  oficina_id       BIGINT UNSIGNED NOT NULL,
  ordem_servico_id BIGINT UNSIGNED NULL,
  lancamento_id    BIGINT UNSIGNED NULL,
  categoria        ENUM('FOTO_ANTES','FOTO_DURANTE','FOTO_DEPOIS','PROBLEMA','DOCUMENTO','COMPROVANTE') NOT NULL,
  descricao        VARCHAR(200)  NULL,
  nome_original    VARCHAR(255)  NOT NULL,
  caminho          VARCHAR(255)  NOT NULL,     -- storage/{oficina_id}/... (arquivos também isolados por oficina)
  mime             VARCHAR(100)  NOT NULL,
  tamanho          INT UNSIGNED  NOT NULL,
  enviado_por      BIGINT UNSIGNED NOT NULL,
  enviado_em       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_anexos_tenant (oficina_id, id),
  UNIQUE KEY uq_anexos_caminho (caminho),
  CONSTRAINT fk_anexos_os      FOREIGN KEY (oficina_id, ordem_servico_id) REFERENCES ordens_servico(oficina_id, id),
  CONSTRAINT fk_anexos_lanc    FOREIGN KEY (oficina_id, lancamento_id)    REFERENCES lancamentos(oficina_id, id),
  CONSTRAINT fk_anexos_usuario FOREIGN KEY (oficina_id, enviado_por)      REFERENCES usuarios(oficina_id, id),
  CONSTRAINT ck_anexos_dono    CHECK ((ordem_servico_id IS NOT NULL) + (lancamento_id IS NOT NULL) = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. HISTORICO — trilha de auditoria (status da OS, versões do orçamento,
--     baixas/estornos, ajustes de cadastro, mudanças de licença). Somente INSERT.
CREATE TABLE historico (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  oficina_id       BIGINT UNSIGNED NOT NULL,
  ordem_servico_id BIGINT UNSIGNED NULL,
  lancamento_id    BIGINT UNSIGNED NULL,
  cliente_id       BIGINT UNSIGNED NULL,
  veiculo_id       BIGINT UNSIGNED NULL,
  produto_id       BIGINT UNSIGNED NULL,
  caixa_id         BIGINT UNSIGNED NULL,
  acao             VARCHAR(40)   NOT NULL,      -- ex.: STATUS, ORCAMENTO_VERSAO, APROVACAO, RECUSA, ESTORNO, LICENCA
  estado_anterior  VARCHAR(40)   NULL,
  estado_novo      VARCHAR(40)   NULL,
  motivo           VARCHAR(255)  NULL,
  dados            JSON          NULL,          -- antes/depois; snapshot completo do orçamento a cada versão
  autor_id         BIGINT UNSIGNED NULL,        -- NULL = ação do sistema ou do perfil PLATAFORMA
  criado_em        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_hist_os   (oficina_id, ordem_servico_id, criado_em),
  KEY ix_hist_lanc (oficina_id, lancamento_id),
  CONSTRAINT fk_hist_oficina FOREIGN KEY (oficina_id)                   REFERENCES oficinas(id),
  CONSTRAINT fk_hist_os      FOREIGN KEY (oficina_id, ordem_servico_id) REFERENCES ordens_servico(oficina_id, id),
  CONSTRAINT fk_hist_lanc    FOREIGN KEY (oficina_id, lancamento_id)    REFERENCES lancamentos(oficina_id, id),
  CONSTRAINT fk_hist_cliente FOREIGN KEY (oficina_id, cliente_id)       REFERENCES clientes(oficina_id, id),
  CONSTRAINT fk_hist_veiculo FOREIGN KEY (oficina_id, veiculo_id)       REFERENCES veiculos(oficina_id, id),
  CONSTRAINT fk_hist_produto FOREIGN KEY (oficina_id, produto_id)       REFERENCES produtos(oficina_id, id),
  CONSTRAINT fk_hist_caixa   FOREIGN KEY (oficina_id, caixa_id)         REFERENCES caixas(oficina_id, id),
  CONSTRAINT fk_hist_autor   FOREIGN KEY (oficina_id, autor_id)         REFERENCES usuarios(oficina_id, id),
  -- no máximo um dono; nenhum = evento da própria oficina (licença, configurações)
  CONSTRAINT ck_hist_dono CHECK ((ordem_servico_id IS NOT NULL) + (lancamento_id IS NOT NULL) + (cliente_id IS NOT NULL)
                               + (veiculo_id IS NOT NULL) + (produto_id IS NOT NULL) + (caixa_id IS NOT NULL) <= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. NOTIFICACOES — avisos internos -------------------------------------
CREATE TABLE notificacoes (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  oficina_id         BIGINT UNSIGNED NOT NULL,
  usuario_id         BIGINT UNSIGNED NOT NULL,
  tipo               VARCHAR(40)  NOT NULL,
  titulo             VARCHAR(150) NOT NULL,
  mensagem           VARCHAR(500) NULL,
  link               VARCHAR(255) NULL,
  chave_idempotencia VARCHAR(100) NULL,
  lida_em            DATETIME     NULL,
  criada_em          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_notif_idem (usuario_id, chave_idempotencia),
  KEY ix_notif_usuario (oficina_id, usuario_id, lida_em),
  CONSTRAINT fk_notif_usuario FOREIGN KEY (oficina_id, usuario_id) REFERENCES usuarios(oficina_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
