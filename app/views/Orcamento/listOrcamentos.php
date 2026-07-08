<?php
// Variáveis esperadas do controller:
// $orcamentosPendentes  — array
// $orcamentosAprovados  — array
// $orcamentosRecusados  — array
// $totalPendentes       — int
// $totalAprovados       — int
// $totalRecusados       — int
// $valorTotalPendente   — float
// $ticketMedio          — float

$orcamentosPendentes = $orcamentosPendentes ?? [];
$orcamentosAprovados = $orcamentosAprovados ?? [];
$orcamentosRecusados = $orcamentosRecusados ?? [];
$totalPendentes      = $totalPendentes      ?? count($orcamentosPendentes);
$totalAprovados      = $totalAprovados      ?? count($orcamentosAprovados);
$totalRecusados      = $totalRecusados      ?? count($orcamentosRecusados);
$valorTotalPendente  = $valorTotalPendente  ?? array_sum(array_column($orcamentosPendentes, 'valor_total'));
$ticketMedio         = $ticketMedio         ?? (($totalPendentes + $totalAprovados) ? ($valorTotalPendente / max($totalPendentes, 1)) : 0);
?>

<!-- ── Page Header ── -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Orçamentos</h1>
        <p class="page-header__subtitle">Crie orçamentos para clientes. Ao aprovar, uma OS é gerada automaticamente.</p>
    </div>
    <div class="page-header__actions">
        <a href="./novoOrcamento" class="btn btn--primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Novo Orçamento
        </a>
    </div>
</div>

<!-- ── Stat Cards ── -->
<section class="stat-grid" style="grid-template-columns:repeat(4,1fr);">

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Pendentes</div>
            <div class="stat-card__icon b2">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12 6 12 12 16 14" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $totalPendentes ?></div>
        <div class="stat-card__delta neutral">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Aguardando decisão
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Aprovados</div>
            <div class="stat-card__icon b1">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $totalAprovados ?></div>
        <div class="stat-card__delta">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
                <polyline points="17 6 23 6 23 12" />
            </svg>
            OS geradas
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Recusados</div>
            <div class="stat-card__icon" style="background:#E0EBFF;color:#1E46C1;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $totalRecusados ?></div>
        <div class="stat-card__delta neutral">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Não convertidos
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Total Pendente</div>
            <div class="stat-card__icon b3">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23" />
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value">R$ <?= number_format($valorTotalPendente, 2, ',', '.') ?></div>
        <div class="stat-card__delta neutral">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Soma dos orçamentos pendentes
        </div>
    </div>

</section>

<!-- ── Main Card ── -->
<section class="card">

    <div class="tabs">
        <button class="tabs__item is-active" data-tab="pendentes">
            Pendentes
            <?php if ($totalPendentes > 0): ?>
                <span style="margin-left:6px;background:var(--color-primary, #094FE4);color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:99px;"><?= $totalPendentes ?></span>
            <?php endif; ?>
        </button>
        <button class="tabs__item" data-tab="aprovados">Aprovados</button>
        <button class="tabs__item" data-tab="recusados">Recusados</button>
    </div>

    <!-- ════ PANEL: PENDENTES ════ -->
    <div class="tab-panel is-active" id="panel-pendentes">

        <?php if (!empty($error ?? '')): ?>
            <div style="margin:16px 24px 0;background:var(--color-danger-soft);color:#991B1B;border:1px solid #FECACA;border-radius:var(--radius-md);padding:12px 16px;font-size:13px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="table-toolbar">
            <div class="search-wrap">
                <input type="text" id="busca-pendentes" placeholder="Buscar por cliente, veículo ou orçamento..." oninput="filtrarOrc('pendentes')">
            </div>
            <input type="date" id="filtro-data-pendentes" class="toolbar-select" onchange="filtrarOrc('pendentes')">
            <span class="text-muted fs-12" id="contador-pendentes"><?= count($orcamentosPendentes) ?> resultado(s)</span>
        </div>

        <!-- Banner informativo -->
        <div style="padding:10px 24px;background:var(--color-primary-soft);border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:8px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <span style="font-size:12px;color:var(--color-primary);font-weight:500;">
                Aprovar um orçamento cria automaticamente uma OS com status <strong>Em Andamento</strong>.
            </span>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Orçamento</th>
                        <th>Cliente</th>
                        <th>Veículo / Placa</th>
                        <th>Mecânico</th>
                        <th>Criado em</th>
                        <th>Valor Total</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="tbody-pendentes">
                    <?php if (!empty($orcamentosPendentes)): ?>
                        <?php foreach ($orcamentosPendentes as $orc):
                            $id = $orc['id_orcamento'] ?? $orc['id'] ?? 0;
                        ?>
                            <tr data-cliente="<?= strtolower($orc['cliente'] ?? '') ?>"
                                data-veiculo="<?= strtolower($orc['veiculo'] ?? '') ?>"
                                data-numero="<?= strtolower($orc['numero'] ?? '') ?>"
                                data-data="<?= substr($orc['data_criacao'] ?? '', 0, 10) ?>">

                                <td class="id-col" style="color:var(--color-primary);font-weight:700;">
                                    <?= htmlspecialchars($orc['numero'] ?? '#' . $id) ?>
                                </td>
                                <td style="font-weight:500;"><?= htmlspecialchars($orc['cliente'] ?? '-') ?></td>
                                <td>
                                    <div style="font-weight:500;"><?= htmlspecialchars($orc['veiculo'] ?? '-') ?></div>
                                    <?php if (!empty($orc['placa'])): ?>
                                        <div class="text-muted fs-12"><?= htmlspecialchars($orc['placa']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= htmlspecialchars($orc['mecanico'] ?? '-') ?></td>
                                <td><?= !empty($orc['data_criacao']) ? date('d/m/Y H:i', strtotime($orc['data_criacao'])) : '-' ?></td>
                                <td>
                                    <span style="font-size:15px;font-weight:700;color:var(--color-primary);">
                                        R$ <?= number_format($orc['valor_total'] ?? 0, 2, ',', '.') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a href="./viewOrcamento&id=<?= $id ?>" class="row-action-btn primary" title="Visualizar">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        </a>
                                        <a href="./editOrcamento&id=<?= $id ?>" class="row-action-btn" title="Editar">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                            </svg>
                                        </a>
                                        <a href="./imprimirOrcamento&id=<?= $id ?>" target="_blank" class="row-action-btn" title="Imprimir">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="6 9 6 2 18 2 18 9" />
                                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                                                <rect x="6" y="14" width="12" height="8" />
                                            </svg>
                                        </a>
                                        <a href="./exportOrcamentoPdf&id=<?= $id ?>" class="row-action-btn" title="Baixar PDF">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                        </a>
                                        <!-- Aprovar -->
                                        <button type="button"
                                            class="row-action-btn"
                                            style="background:var(--color-success-soft);color:var(--color-success);border-color:var(--color-success);"
                                            title="Aprovar e gerar OS"
                                            onclick="confirmarAprovar(<?= $id ?>, '<?= htmlspecialchars($orc['numero'] ?? '#' . $id, ENT_QUOTES) ?>', '<?= htmlspecialchars($orc['cliente'] ?? '', ENT_QUOTES) ?>')">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="20 6 9 17 4 12" />
                                            </svg>
                                        </button>
                                        <!-- Recusar -->
                                        <button type="button"
                                            class="row-action-btn danger"
                                            title="Recusar orçamento"
                                            onclick="confirmarRecusar(<?= $id ?>, '<?= htmlspecialchars($orc['numero'] ?? '#' . $id, ENT_QUOTES) ?>')">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="18" y1="6" x2="6" y2="18" />
                                                <line x1="6" y1="6" x2="18" y2="18" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 10px;display:block;opacity:.4;">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                    <polyline points="14 2 14 8 20 8" />
                                </svg>
                                Nenhum orçamento pendente.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <span id="pag-info-pendentes"><?= count($orcamentosPendentes) ?> resultado(s)</span>
            <div class="pagination__pages">
                <button class="pagination__btn">‹</button>
                <button class="pagination__btn is-active">1</button>
                <button class="pagination__btn">›</button>
            </div>
        </div>
    </div>
    <!-- fim panel-pendentes -->

    <!-- ════ PANEL: APROVADOS ════ -->
    <div class="tab-panel" id="panel-aprovados">
        <div class="table-toolbar">
            <div class="search-wrap">
                <input type="text" id="busca-aprovados" placeholder="Buscar por cliente, veículo ou orçamento..." oninput="filtrarOrc('aprovados')">
            </div>
            <input type="date" id="filtro-data-aprovados" class="toolbar-select" onchange="filtrarOrc('aprovados')">
            <span class="text-muted fs-12" id="contador-aprovados"><?= count($orcamentosAprovados) ?> resultado(s)</span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Orçamento</th>
                        <th>Cliente</th>
                        <th>Veículo / Placa</th>
                        <th>Mecânico</th>
                        <th>Aprovado em</th>
                        <th>OS Gerada</th>
                        <th>Valor Total</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="tbody-aprovados">
                    <?php if (!empty($orcamentosAprovados)): ?>
                        <?php foreach ($orcamentosAprovados as $orc):
                            $id = $orc['id_orcamento'] ?? $orc['id'] ?? 0;
                        ?>
                            <tr data-cliente="<?= strtolower($orc['cliente'] ?? '') ?>"
                                data-veiculo="<?= strtolower($orc['veiculo'] ?? '') ?>"
                                data-numero="<?= strtolower($orc['numero'] ?? '') ?>"
                                data-data="<?= substr($orc['data_aprovacao'] ?? '', 0, 10) ?>">

                                <td class="id-col" style="color:var(--color-primary);font-weight:700;">
                                    <?= htmlspecialchars($orc['numero'] ?? '#' . $id) ?>
                                </td>
                                <td style="font-weight:500;"><?= htmlspecialchars($orc['cliente'] ?? '-') ?></td>
                                <td>
                                    <div style="font-weight:500;"><?= htmlspecialchars($orc['veiculo'] ?? '-') ?></div>
                                    <?php if (!empty($orc['placa'])): ?>
                                        <div class="text-muted fs-12"><?= htmlspecialchars($orc['placa']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= htmlspecialchars($orc['mecanico'] ?? '-') ?></td>
                                <td><?= !empty($orc['data_aprovacao']) ? date('d/m/Y H:i', strtotime($orc['data_aprovacao'])) : '-' ?></td>
                                <td>
                                    <?php if (!empty($orc['os_numero'])): ?>
                                        <a href="./viewOrdemServico&id=<?= $orc['os_id'] ?? '' ?>"
                                            style="font-weight:700;color:var(--color-success);text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                <polyline points="20 6 9 17 4 12" />
                                            </svg>
                                            <?= htmlspecialchars($orc['os_numero']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size:15px;font-weight:700;color:var(--color-primary);">
                                        R$ <?= number_format($orc['valor_total'] ?? 0, 2, ',', '.') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a href="./viewOrcamento&id=<?= $id ?>" class="row-action-btn primary" title="Visualizar">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        </a>
                                        <?php if (!empty($orc['os_id'])): ?>
                                            <a href="./viewOrdemServico&id=<?= $orc['os_id'] ?>" class="row-action-btn" title="Ver OS" style="color:var(--color-success);">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                    <polyline points="14 2 14 8 20 8" />
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">
                                Nenhum orçamento aprovado ainda.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- fim panel-aprovados -->

    <!-- ════ PANEL: RECUSADOS ════ -->
    <div class="tab-panel" id="panel-recusados">
        <div class="table-toolbar">
            <div class="search-wrap">
                <input type="text" id="busca-recusados" placeholder="Buscar por cliente, veículo ou orçamento..." oninput="filtrarOrc('recusados')">
            </div>
            <input type="date" id="filtro-data-recusados" class="toolbar-select" onchange="filtrarOrc('recusados')">
            <span class="text-muted fs-12" id="contador-recusados"><?= count($orcamentosRecusados) ?> resultado(s)</span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Orçamento</th>
                        <th>Cliente</th>
                        <th>Veículo / Placa</th>
                        <th>Mecânico</th>
                        <th>Recusado em</th>
                        <th>Motivo</th>
                        <th>Valor Total</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="tbody-recusados">
                    <?php if (!empty($orcamentosRecusados)): ?>
                        <?php foreach ($orcamentosRecusados as $orc):
                            $id = $orc['id_orcamento'] ?? $orc['id'] ?? 0;
                        ?>
                            <tr data-cliente="<?= strtolower($orc['cliente'] ?? '') ?>"
                                data-veiculo="<?= strtolower($orc['veiculo'] ?? '') ?>"
                                data-numero="<?= strtolower($orc['numero'] ?? '') ?>"
                                data-data="<?= substr($orc['data_recusa'] ?? '', 0, 10) ?>">

                                <td class="id-col" style="color:var(--color-text-muted);font-weight:700;text-decoration:line-through;">
                                    <?= htmlspecialchars($orc['numero'] ?? '#' . $id) ?>
                                </td>
                                <td style="font-weight:500;"><?= htmlspecialchars($orc['cliente'] ?? '-') ?></td>
                                <td>
                                    <div style="font-weight:500;"><?= htmlspecialchars($orc['veiculo'] ?? '-') ?></div>
                                    <?php if (!empty($orc['placa'])): ?>
                                        <div class="text-muted fs-12"><?= htmlspecialchars($orc['placa']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= htmlspecialchars($orc['mecanico'] ?? '-') ?></td>
                                <td><?= !empty($orc['data_recusa']) ? date('d/m/Y H:i', strtotime($orc['data_recusa'])) : '-' ?></td>
                                <td>
                                    <?php if (!empty($orc['motivo_recusa'])): ?>
                                        <span class="text-muted fs-12" style="max-width:180px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                                            title="<?= htmlspecialchars($orc['motivo_recusa']) ?>">
                                            <?= htmlspecialchars($orc['motivo_recusa']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size:15px;font-weight:700;color:var(--color-text-muted);">
                                        R$ <?= number_format($orc['valor_total'] ?? 0, 2, ',', '.') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a href="./viewOrcamento&id=<?= $id ?>" class="row-action-btn primary" title="Visualizar">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">
                                Nenhum orçamento recusado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- fim panel-recusados -->

</section>


<!-- ══════════════════════════════════════════════════════
     MODAL — CONFIRMAR APROVAÇÃO
     ══════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-aprovar" style="display:none;">
    <div class="modal" style="max-width:460px;">
        <div class="modal__header">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:40px;height:40px;background:var(--color-success-soft);color:var(--color-success);border-radius:var(--radius-md);display:inline-flex;align-items:center;justify-content:center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                </div>
                <div>
                    <div class="modal__title">Aprovar Orçamento</div>
                    <div class="modal__subtitle" id="aprovar-ref">Uma OS será criada automaticamente.</div>
                </div>
            </div>
            <button type="button" class="modal__close" onclick="fecharAprovar()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
        </div>
        <div class="modal__body" style="padding:20px 24px;">
            <p style="font-size:14px;color:var(--color-text);line-height:1.6;">
                Ao confirmar, o orçamento <strong id="aprovar-numero" style="color:var(--color-primary);"></strong>
                do cliente <strong id="aprovar-cliente"></strong> será <span style="color:var(--color-success);font-weight:600;">aprovado</span>
                e uma nova <strong>Ordem de Serviço</strong> será criada com status <span style="color:var(--color-primary);font-weight:600;">Em Andamento</span>.
            </p>
            <p style="font-size:12px;color:var(--color-text-muted);margin-top:12px;">Esta ação não poderá ser desfeita.</p>

            <div style="margin-top:16px;">
                <label style="font-size:11px;color:var(--color-text-muted);font-weight:600;">
                    Assinatura do cliente <span style="font-weight:400;">(opcional)</span>
                </label>
                <canvas id="canvas-assinatura-aprovar" width="380" height="130"
                    style="width:100%;height:130px;margin-top:6px;background:#fff;border:1.5px dashed var(--color-border-strong);
                           border-radius:var(--radius-md);cursor:crosshair;touch-action:none;"></canvas>
                <button type="button" class="btn btn--ghost btn--sm" style="margin-top:8px;" onclick="assinaturaAprovar && assinaturaAprovar.limpar()">
                    Limpar assinatura
                </button>
            </div>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn--ghost btn--sm" onclick="fecharAprovar()">Cancelar</button>
            <form id="form-aprovar" action="./aprovarOrcamento" method="POST" style="display:inline;"
                onsubmit="document.getElementById('h-aprovar-assinatura').value = (assinaturaAprovar ? assinaturaAprovar.dataUrl() : '');">
                <input type="hidden" name="id_orcamento" id="h-aprovar-id">
                <input type="hidden" name="assinatura_cliente" id="h-aprovar-assinatura">
                <button type="submit" class="btn btn--sm" style="background:var(--color-success);color:#fff;border-color:var(--color-success);">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    Confirmar e Gerar OS
                </button>
            </form>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════
     MODAL — RECUSAR COM MOTIVO
     ══════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-recusar" style="display:none;">
    <div class="modal" style="max-width:460px;">
        <div class="modal__header">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:40px;height:40px;background:#FEE2E2;color:#DC2626;border-radius:var(--radius-md);display:inline-flex;align-items:center;justify-content:center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </div>
                <div>
                    <div class="modal__title">Recusar Orçamento</div>
                    <div class="modal__subtitle" id="recusar-ref">Informe o motivo da recusa.</div>
                </div>
            </div>
            <button type="button" class="modal__close" onclick="fecharRecusar()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
        </div>
        <div class="modal__body" style="padding:20px 24px;">
            <div class="form-field">
                <label>Motivo da recusa <span style="font-size:11px;color:var(--color-text-muted);">(opcional)</span></label>
                <textarea id="recusar-motivo" rows="4" placeholder="Ex.: Cliente não aceitou o valor, optou por outra oficina..."></textarea>
            </div>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn--ghost btn--sm" onclick="fecharRecusar()">Cancelar</button>
            <form id="form-recusar" action="./recusarOrcamento" method="POST" style="display:inline;">
                <input type="hidden" name="id_orcamento" id="h-recusar-id">
                <input type="hidden" name="motivo_recusa" id="h-recusar-motivo">
                <button type="submit" class="btn btn--danger btn--sm"
                    onclick="document.getElementById('h-recusar-motivo').value = document.getElementById('recusar-motivo').value;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                    Recusar Orçamento
                </button>
            </form>
        </div>
    </div>
</div>


<!-- ── JavaScript ── -->
<script src="./assets/js/assinatura.js?v=<?= time() ?>"></script>
<script>
    /* ── Tabs ── */
    document.querySelectorAll('.tabs__item[data-tab]').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.tabs__item[data-tab]').forEach(t => t.classList.remove('is-active'));
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('is-active'));
            tab.classList.add('is-active');
            document.getElementById('panel-' + tab.dataset.tab).classList.add('is-active');
        });
    });

    /* ── Filtro por painel ── */
    function filtrarOrc(painel) {
        const busca = (document.getElementById('busca-' + painel)?.value ?? '').toLowerCase();
        const data = document.getElementById('filtro-data-' + painel)?.value ?? '';
        const linhas = document.querySelectorAll('#tbody-' + painel + ' tr');
        let visiveis = 0;
        linhas.forEach(tr => {
            const ok = (!busca || tr.dataset.cliente?.includes(busca) || tr.dataset.veiculo?.includes(busca) || tr.dataset.numero?.includes(busca)) &&
                (!data || tr.dataset.data === data);
            tr.style.display = ok ? '' : 'none';
            if (ok) visiveis++;
        });
        const el = document.getElementById('contador-' + painel);
        if (el) el.textContent = visiveis + ' resultado(s)';
    }

    /* ── Modal Aprovar ── */
    var assinaturaAprovar = initAssinatura('canvas-assinatura-aprovar');

    function confirmarAprovar(id, numero, cliente) {
        document.getElementById('h-aprovar-id').value = id;
        document.getElementById('aprovar-numero').textContent = numero;
        document.getElementById('aprovar-cliente').textContent = cliente;
        if (assinaturaAprovar) assinaturaAprovar.limpar();
        document.getElementById('modal-aprovar').style.display = 'flex';
    }

    function fecharAprovar() {
        document.getElementById('modal-aprovar').style.display = 'none';
    }

    /* ── Modal Recusar ── */
    function confirmarRecusar(id, numero) {
        document.getElementById('h-recusar-id').value = id;
        document.getElementById('recusar-ref').textContent = 'Orçamento ' + numero;
        document.getElementById('recusar-motivo').value = '';
        document.getElementById('modal-recusar').style.display = 'flex';
    }

    function fecharRecusar() {
        document.getElementById('modal-recusar').style.display = 'none';
    }

    /* Fechar ao clicar fora */
    ['modal-aprovar', 'modal-recusar'].forEach(id => {
        document.getElementById(id).addEventListener('click', function(e) {
            if (e.target === this) this.style.display = 'none';
        });
    });
</script>