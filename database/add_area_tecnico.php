<?php

/**
 * Área do Técnico — execução, checklist de entrega e assinatura do técnico.
 *
 * O que esta migração resolve:
 *
 * 1) STATUS — entra 'PRONTA_ENTREGA' entre 'AGUARDANDO_PECA' e 'CONCLUIDA'.
 *    É o momento em que o técnico terminou o serviço e o carro está pronto
 *    pra ser retirado; 'CONCLUIDA' passa a significar especificamente
 *    "entregue" (ação exclusiva de ADMIN/ATENDENTE, sem mudança de
 *    comportamento — continua sendo o concluir() que já existe).
 *
 * 2) FINALIZAÇÃO TÉCNICA — assinatura do técnico, quem finalizou, quando,
 *    observações técnicas (distintas de observacoes/diagnostico, que são
 *    preenchidas por ADMIN/ATENDENTE na abertura da OS) e o checklist de
 *    entrega (JSON de itens fixos, ver OrdemServico::CHECKLIST_ENTREGA).
 *
 * 3) SERVIÇOS EXECUTADOS — o técnico confirma, item a item, quais serviços
 *    planejados em ordem_servico_servicos foram de fato realizados.
 *
 * Como rodar:
 *   php database/add_area_tecnico.php
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

echo "1) Novo status PRONTA_ENTREGA\n";

$pdo->exec("ALTER TABLE ordens_servico
            MODIFY COLUMN status ENUM('ABERTA','EM_ANDAMENTO','AGUARDANDO_PECA','PRONTA_ENTREGA','CONCLUIDA','CANCELADA')
            NOT NULL DEFAULT 'ABERTA'");
echo "  ~ ordens_servico.status ampliado\n";

echo "\n2) Finalização técnica\n";

coluna($pdo, 'ordens_servico', 'assinatura_tecnico',
    "assinatura_tecnico MEDIUMTEXT NULL AFTER assinatura_cliente");

coluna($pdo, 'ordens_servico', 'tecnico_finalizacao_id',
    "tecnico_finalizacao_id BIGINT UNSIGNED NULL AFTER assinatura_tecnico");

coluna($pdo, 'ordens_servico', 'data_finalizacao_tecnica',
    "data_finalizacao_tecnica DATETIME NULL AFTER tecnico_finalizacao_id");

coluna($pdo, 'ordens_servico', 'observacoes_tecnicas',
    "observacoes_tecnicas TEXT NULL AFTER observacoes");

coluna($pdo, 'ordens_servico', 'checklist_entrega',
    "checklist_entrega TEXT NULL AFTER observacoes_tecnicas");

// Apagar o usuário não pode apagar o registro de quem finalizou a OS.
$existeFk = $pdo->query(
    "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE()
       AND TABLE_NAME = 'ordens_servico'
       AND CONSTRAINT_NAME = 'fk_os_tecnico_finalizacao'"
)->fetch();

if ($existeFk) {
    echo "  = chave fk_os_tecnico_finalizacao já existe\n";
} else {
    $pdo->exec("ALTER TABLE ordens_servico
                ADD CONSTRAINT fk_os_tecnico_finalizacao
                FOREIGN KEY (tecnico_finalizacao_id) REFERENCES usuarios(id_usuario)
                ON DELETE SET NULL");
    echo "  + chave fk_os_tecnico_finalizacao\n";
}

echo "\n3) Confirmação de serviço executado\n";

coluna($pdo, 'ordem_servico_servicos', 'executado',
    "executado TINYINT(1) NOT NULL DEFAULT 0 AFTER percentual_base");

echo "\nMigração concluída.\n";
