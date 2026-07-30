<?php

/**
 * Cria a tabela `agendamentos` (agenda de serviços da oficina).
 *
 * Cada linha é um horário marcado para um cliente/veículo, antes de virar
 * Ordem de Serviço. O ciclo é:
 *   AGENDADO → CONFIRMADO → (vira OS) CONCLUIDO
 *                        ↘ CANCELADO / FALTOU
 *
 * `ordem_servico_id` é preenchido quando o agendamento é convertido em OS —
 * é o que liga a agenda ao resto do sistema e evita converter duas vezes.
 *
 * `duracao_min` existe para a agenda saber onde um horário termina e poder
 * detectar sobreposição (Agendamento::temConflito()).
 *
 * Como rodar:
 *   php database/create_agendamentos.php
 *
 * O CLI mysql.exe do XAMPP está quebrado nesta máquina, por isso usamos PDO.
 */

require_once __DIR__ . '/../app/core/Database.php';

$pdo = new Database();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "
CREATE TABLE IF NOT EXISTS agendamentos (
    id_agendamento   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cliente_id       BIGINT UNSIGNED NOT NULL,
    veiculo_id       BIGINT UNSIGNED     NULL,
    mecanico_id      BIGINT UNSIGNED     NULL,
    data_hora        DATETIME        NOT NULL,
    duracao_min      SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    servico          VARCHAR(160)    NOT NULL,
    observacoes      VARCHAR(500)        NULL,
    status           ENUM('AGENDADO','CONFIRMADO','CONCLUIDO','CANCELADO','FALTOU')
                     NOT NULL DEFAULT 'AGENDADO',
    ordem_servico_id BIGINT UNSIGNED     NULL,
    usuario_criacao_id BIGINT UNSIGNED   NULL,
    criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_agendamento),
    KEY idx_agenda_data (data_hora),
    KEY idx_agenda_status (status),
    KEY idx_agenda_cliente (cliente_id),
    CONSTRAINT fk_agenda_cliente
        FOREIGN KEY (cliente_id)  REFERENCES clientes (id_cliente),
    CONSTRAINT fk_agenda_veiculo
        FOREIGN KEY (veiculo_id)  REFERENCES veiculos (id_veiculo),
    CONSTRAINT fk_agenda_mecanico
        FOREIGN KEY (mecanico_id) REFERENCES usuarios (id_usuario),
    CONSTRAINT fk_agenda_os
        FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico (id_ordem_servico)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$pdo->exec($sql);

echo "Tabela 'agendamentos' criada/verificada com sucesso.\n";

foreach ($pdo->query("SHOW COLUMNS FROM agendamentos") as $col) {
    echo str_pad($col['Field'], 20) . $col['Type'] . "\n";
}
