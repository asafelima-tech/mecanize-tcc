<?php
// Variáveis esperadas do controller:
// $clientesDesativados, $totalDesativados, $paginaAtual
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Clientes Desativados</h1>
        <p class="page-header__subtitle">Visualize e reative clientes que foram desativados da oficina.</p>
    </div>
    <div class="page-header__actions">
        <a href="./listClientes" class="btn btn--ghost">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:middle;">
                <path d="M19 12H5M12 5l-7 7 7 7" />
            </svg>
            Voltar para Clientes
        </a>
    </div>
</div>

<!-- Stat Cards -->
<section class="stat-grid">

    <!-- Total Desativados -->
    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Total Desativados</div>
            <div class="stat-card__icon b1">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <line x1="17" y1="11" x2="23" y2="11" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $totalDesativados ?? 0 ?></div>
        <div class="stat-card__delta">Clientes com acesso desativado</div>
    </div>

    <!-- Desativados este mês -->
    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Desativados Este Mês</div>
            <div class="stat-card__icon b2">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                    <line x1="10" y1="15" x2="14" y2="15" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $desativadosMes ?? 0 ?></div>
        <div class="stat-card__delta">Novos inativos em <?= date('m/Y') ?></div>
    </div>

    <!-- Com veículos cadastrados -->
    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Com Veículos</div>
            <div class="stat-card__icon b3">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="3" width="15" height="13" rx="2" />
                    <path d="M16 8h4l3 5v3h-7V8z" />
                    <circle cx="5.5" cy="18.5" r="2.5" />
                    <circle cx="18.5" cy="18.5" r="2.5" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $comVeiculos ?? 0 ?></div>
        <div class="stat-card__delta">Clientes com veículo vinculado</div>
    </div>

    <!-- Sem visita há mais de 6 meses -->
    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Inativos há +6 Meses</div>
            <div class="stat-card__icon b4">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12 6 12 12 16 14" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $inativos6Meses ?? 0 ?></div>
        <div class="stat-card__delta">Sem visita registrada recente</div>
    </div>

</section>

<!-- Tabela de Clientes Desativados -->
<section class="card">

    <!-- Toolbar / Filtros -->
    <form method="GET" action="./listClientesDesativados" class="table-toolbar">
        <div class="search-wrap">
            <input
                type="text"
                name="busca"
                value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>"
                placeholder="Buscar por nome, telefone ou e-mail..." />
        </div>

        <button type="submit" class="btn btn--ghost">Buscar</button>

        <span style="margin-left:auto;font-size:12px;color:var(--color-text-muted);">
            <?= !empty($clientesDesativados) ? count($clientesDesativados) : 0 ?> resultado(s)
        </span>
    </form>

    <!-- Tabela -->
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Contato</th>
                    <th>Veículos Cadastrados</th>
                    <th>Última Visita</th>
                    <th>Desativado em</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($clientesDesativados)): ?>
                    <?php foreach ($clientesDesativados as $cliente): ?>
                        <tr style="opacity: .75;">
                            <!-- Cliente -->
                            <td>
                                <div class="user-cell">
                                    <div class="avatar" style="background: var(--color-border); color: var(--color-text-muted);">
                                        <?= strtoupper(substr($cliente['nome'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <div class="user-cell__name" style="color: var(--color-text-muted); text-decoration: line-through;">
                                            <?= htmlspecialchars($cliente['nome']) ?>
                                        </div>
                                        <div class="user-cell__sub">
                                            <?= !empty($cliente['cpf_cnpj']) ? htmlspecialchars($cliente['cpf_cnpj']) : 'Sem documento' ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Contato -->
                            <td>
                                <div style="font-size:13px;line-height:1.8;">
                                    <div class="flex items-center gap-8">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.44C2.11 2.09 3.32 1 4.7 1h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L9.91 8.09a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 24 14.91v3.01z" />
                                        </svg>
                                        <?= htmlspecialchars($cliente['telefone'] ?: 'Sem telefone') ?>
                                    </div>
                                    <div class="text-muted fs-12">
                                        <?= htmlspecialchars($cliente['email'] ?: 'Sem e-mail') ?>
                                    </div>
                                </div>
                            </td>

                            <!-- Veículos -->
                            <td>
                                <?php if (!empty($cliente['veiculo_principal'])): ?>
                                    <?= htmlspecialchars($cliente['veiculo_principal']) ?>
                                    <?php if ((int)$cliente['total_veiculos'] > 1): ?>
                                        <span class="badge badge--neutral">+<?= ((int)$cliente['total_veiculos']) - 1 ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Nenhum veículo</span>
                                <?php endif; ?>
                            </td>

                            <!-- Última Visita -->
                            <td>
                                <?php if (!empty($cliente['ultima_visita'])): ?>
                                    <?= date('d/m/Y', strtotime($cliente['ultima_visita'])) ?>
                                <?php else: ?>
                                    <span class="text-muted">Sem visita</span>
                                <?php endif; ?>
                            </td>

                            <!-- Desativado em -->
                            <td>
                                <?php if (!empty($cliente['desativado_em'])): ?>
                                    <span style="font-size:13px; color: var(--color-text-muted);">
                                        <?= date('d/m/Y', strtotime($cliente['desativado_em'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Ações -->
                            <td>
                                <div class="row-actions">
                                    <!-- Reativar -->
                                    <a
                                        href="./reativarCliente?id=<?= $cliente['id_cliente'] ?>"
                                        class="row-action-btn primary"
                                        title="Reativar cliente"
                                        onclick="return confirm('Deseja reativar este cliente?')">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="17 1 21 5 17 9" />
                                            <path d="M3 11V9a4 4 0 0 1 4-4h14" />
                                            <polyline points="7 23 3 19 7 15" />
                                            <path d="M21 13v2a4 4 0 0 1-4 4H3" />
                                        </svg>
                                    </a>
                                    <!-- Visualizar -->
                                    <a href="./viewCliente?id=<?= $cliente['id_cliente'] ?>" class="row-action-btn" title="Visualizar">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </a>
                                    <!-- Excluir permanentemente -->
                                  
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 12px;display:block;opacity:.4;">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                                <line x1="17" y1="11" x2="23" y2="11" />
                            </svg>
                            Nenhum cliente desativado encontrado.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginação -->
    <div class="pagination">
        <span>
            Mostrando <?= !empty($clientesDesativados) ? count($clientesDesativados) : 0 ?> de <?= $totalDesativados ?? 0 ?> resultados
        </span>
        <div class="pagination__pages">
            <button class="pagination__btn" <?= (($paginaAtual ?? 1) <= 1) ? 'disabled' : '' ?>>‹</button>

            <?php
            $pagAtual  = (int)($paginaAtual ?? 1);
            $totalPags = (int)ceil(($totalDesativados ?? 0) / 10);
            $totalPags = max($totalPags, 1);
            $inicio    = max(1, $pagAtual - 1);
            $fim       = min($totalPags, $pagAtual + 1);

            for ($p = $inicio; $p <= $fim; $p++):
            ?>
                <button class="pagination__btn <?= $p === $pagAtual ? 'is-active' : '' ?>">
                    <?= $p ?>
                </button>
            <?php endfor; ?>

            <button class="pagination__btn" <?= $pagAtual >= $totalPags ? 'disabled' : '' ?>>›</button>
        </div>
    </div>

</section>