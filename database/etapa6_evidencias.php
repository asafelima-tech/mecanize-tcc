<?php

/**
 * Etapa 6 — Evidências fotográficas da OS (registro do que o técnico encontrou e fez).
 *
 * Idempotente. Rode nos dois bancos:
 *   /c/xampp/php/php.exe database/etapa6_evidencias.php
 *   DB_NAME=mecanize_db_test /c/xampp/php/php.exe database/etapa6_evidencias.php
 *
 * As fotos ficam em storage/evidencias/ (fora da pasta pública, negada pelo Apache) e só saem por rota
 * autorizada. A linha do banco guarda o caminho ALEATÓRIO, o tipo (decidido pelo conteúdo), quem enviou e
 * quando. Evidência não se edita nem se apaga: é registro. Por isso a OS que tem foto não pode ser excluída
 * (ver OrdemServico::VINCULOS).
 */

require_once __DIR__ . '/_migracao.php';

$m = new Migracao();

$m->titulo('1) Evidências da OS');

$m->criarTabela('evidencias_os', "
    CREATE TABLE evidencias_os (
        id_evidencia     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        ordem_servico_id BIGINT UNSIGNED NOT NULL,
        tipo             ENUM('ANTES','DURANTE','DEPOIS','PROBLEMA') NOT NULL DEFAULT 'DURANTE',
        descricao        VARCHAR(255) NULL,
        nome_original    VARCHAR(150) NOT NULL,
        caminho          VARCHAR(255) NOT NULL,
        mime             VARCHAR(60) NOT NULL,
        tamanho          INT UNSIGNED NOT NULL,
        enviado_por      BIGINT UNSIGNED NULL,
        enviado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id_evidencia),
        UNIQUE KEY uq_evidencia_caminho (caminho),
        KEY idx_evidencia_os (ordem_servico_id, id_evidencia),
        CONSTRAINT fk_evidencia_os      FOREIGN KEY (ordem_servico_id) REFERENCES ordens_servico (id_ordem_servico) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_evidencia_usuario FOREIGN KEY (enviado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "\nConcluído: {$m->feitas} alteração(ões).\n";
