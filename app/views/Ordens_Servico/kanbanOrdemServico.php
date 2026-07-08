<?php
// Variável esperada do controller: $colunas (array associativo status => ['label' => ..., 'ordens' => [...]])
$badgeStatus = [
    'ABERTA'          => 'badge--info',
    'EM_ANDAMENTO'    => 'badge--warning',
    'AGUARDANDO_PECA' => 'badge--purple',
    'CONCLUIDA'       => 'badge--success',
];
?>

<style>
    .kanban-board {
        display: flex;
        gap: 16px;
        overflow-x: auto;
        padding-bottom: 8px;
    }

    .kanban-column {
        flex: 0 0 280px;
        background: var(--color-bg-alt);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        display: flex;
        flex-direction: column;
        max-height: calc(100vh - 220px);
    }

    .kanban-column__header {
        padding: 14px 16px;
        border-bottom: 1px solid var(--color-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .kanban-column__title {
        font-size: 13px;
        font-weight: 700;
        color: var(--color-text);
    }

    .kanban-column__body {
        padding: 12px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        overflow-y: auto;
        min-height: 80px;
        transition: background .12s;
    }

    .kanban-column__body.is-dragover {
        background: var(--color-primary-soft);
        border-radius: var(--radius-md);
    }

    .kanban-column__empty {
        font-size: 12px;
        color: var(--color-text-muted);
        text-align: center;
        padding: 20px 8px;
        border: 1px dashed var(--color-border-strong);
        border-radius: var(--radius-md);
    }

    .kanban-card {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-sm);
        padding: 12px 14px;
        cursor: grab;
        transition: box-shadow .12s, opacity .12s, transform .12s;
    }

    .kanban-card:hover {
        box-shadow: var(--shadow-md);
    }

    .kanban-card.is-dragging {
        opacity: .4;
    }

    .kanban-card__numero {
        font-size: 12.5px;
        font-weight: 700;
        color: var(--color-primary);
        margin-bottom: 4px;
    }

    .kanban-card__cliente {
        font-size: 13px;
        font-weight: 600;
        color: var(--color-text);
    }

    .kanban-card__veiculo {
        font-size: 11.5px;
        color: var(--color-text-muted);
        margin-top: 1px;
    }

    .kanban-card__footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 8px;
    }

    .kanban-card__valor {
        font-size: 12px;
        font-weight: 700;
        color: var(--color-text-secondary);
    }

    .kanban-card__link {
        font-size: 11px;
        color: var(--color-primary);
        text-decoration: none;
        font-weight: 600;
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Ordens de Serviço — Kanban</h1>
        <p class="page-header__subtitle">Arraste os cards entre as colunas para mudar o status.</p>
    </div>
    <div class="page-header__actions">
        <a href="./listOrdemServico" class="btn btn--ghost">
            <i class="bi bi-list-ul"></i>
            Ver como lista
        </a>
        <a href="./saveOrdemServico" class="btn btn--primary">
            <i class="bi bi-plus-lg"></i>
            Nova Ordem de Serviço
        </a>
    </div>
</div>

<div class="kanban-board">
    <?php foreach ($colunas as $status => $coluna): ?>
        <div class="kanban-column" data-status="<?= $status ?>">
            <div class="kanban-column__header">
                <span class="kanban-column__title"><?= htmlspecialchars($coluna['label']) ?></span>
                <span class="badge <?= $badgeStatus[$status] ?>">
                    <span class="badge__dot"></span>
                    <span class="kanban-column__count"><?= count($coluna['ordens']) ?></span>
                </span>
            </div>
            <div class="kanban-column__body" data-status="<?= $status ?>">
                <?php if (empty($coluna['ordens'])): ?>
                    <div class="kanban-column__empty">Nenhuma OS aqui.</div>
                <?php else: ?>
                    <?php foreach ($coluna['ordens'] as $os): ?>
                        <div class="kanban-card" draggable="true" data-id="<?= (int) $os['id_ordem_servico'] ?>">
                            <div class="kanban-card__numero">OS #<?= htmlspecialchars($os['numero'] ?? $os['id_ordem_servico']) ?></div>
                            <div class="kanban-card__cliente"><?= htmlspecialchars($os['cliente'] ?? '-') ?></div>
                            <div class="kanban-card__veiculo">
                                <?= htmlspecialchars($os['veiculo'] ?? '-') ?>
                                <?php if (!empty($os['placa'])): ?>
                                    · <?= htmlspecialchars(strtoupper($os['placa'])) ?>
                                <?php endif; ?>
                            </div>
                            <div class="kanban-card__footer">
                                <span class="kanban-card__valor">R$ <?= number_format($os['valor_total_os'] ?? 0, 2, ',', '.') ?></span>
                                <a href="./viewOrdemServico&id=<?= (int) $os['id_ordem_servico'] ?>" class="kanban-card__link">Detalhes →</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script src="./assets/js/kanban.js?v=<?= time() ?>"></script>
