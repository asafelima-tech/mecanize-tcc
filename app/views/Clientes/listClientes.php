<?php
// Variáveis esperadas do controller:
// $clientes, $totalClientes, $novosMes, $clientesVip, $paginaAtual
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Gestão de Clientes</h1>
        <p class="page-header__subtitle">Gerencie e acompanhe os clientes da oficina.</p>
    </div>
    <div class="page-header__actions">
        <a href="./saveCliente?balcao=1" class="btn btn--ghost" title="Cadastro rápido para atendimento de balcão sem nome do cliente">+ Cliente Balcão</a>
        <a href="./saveCliente" class="btn btn--primary">+ Novo Cliente</a>
    </div>
</div>

<!-- Stat Cards -->
<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Total de Clientes</div>
            <div class="stat-card__icon b1">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $totalClientes ?? 0 ?></div>
        <div class="stat-card__delta">Total de clientes cadastrados</div>
    </div>

    <div class="stat-card stat-card--clickable" id="cardNovosMes" role="button" tabindex="0">
        <div class="stat-card__head">
            <div class="stat-card__label">Novos esse Mês</div>
            <div class="stat-card__icon b2">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                    <circle cx="8.5" cy="7" r="4" />
                    <line x1="20" y1="8" x2="20" y2="14" />
                    <line x1="23" y1="11" x2="17" y2="11" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $novosMes ?? 0 ?></div>
        <div class="stat-card__delta">Clique para ver os novos clientes</div>
    </div>

    <!-- ====== CARD VIP — clicável, com o painel ancorado dentro dele ====== -->
    <div class="stat-card stat-card--clickable" id="cardClientesVip" role="button" tabindex="0">
        <div class="stat-card__head">
            <div class="stat-card__label">Clientes VIP / Recorrentes</div>
            <div class="stat-card__icon b3">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $clientesVip ?? 0 ?></div>
        <div class="stat-card__delta">Clique para ver os clientes VIP</div>
    </div>
    <!-- ====== /CARD VIP ====== -->

</section>

<!-- ============================================================
     PAINEL VIP — fora do fluxo do card de propósito.
     Usa position:fixed + JS (getBoundingClientRect) para se ancorar
     visualmente embaixo do card, sem disputar stacking context com
     nada da página (evita o bug de elementos com position:relative
     "roubando" a pintura por ordem de DOM, como .search-wrap).
     ============================================================ -->
<div class="vip-panel" id="vipPanel">
    <div class="vip-panel__track" id="vipPanelTrack">

        <!-- Tela 1: Lista -->
        <div class="vip-screen" id="vipScreenList">
            <div class="vip-panel__header">
                <div class="vip-panel__title">
                    <span class="vip-panel__icon">★</span>
                    Clientes VIP &amp; Recorrentes
                </div>
                <button class="vip-panel__close" id="vipClose" type="button" aria-label="Fechar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="vip-panel__body" id="vipListBody">
                <div class="vip-loading">Carregando...</div>
            </div>
        </div>

        <!-- Tela 2: Perfil -->
        <div class="vip-screen" id="vipScreenPerfil">
            <div class="vip-panel__header">
                <button class="vip-panel__back" id="vipBack" type="button">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7" />
                    </svg>
                    Voltar
                </button>
                <button class="vip-panel__close" id="vipCloseFromPerfil" type="button" aria-label="Fechar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="vip-panel__body" id="vipPerfilBody">
                <div class="vip-loading">Carregando perfil...</div>
            </div>
        </div>

    </div>
</div>

<!-- ============================================================
     PAINEL NOVOS CLIENTES — mesma estrutura do painel VIP, reaproveitando
     as mesmas classes CSS (genéricas, não são "VIP-específicas").
     ============================================================ -->
<div class="vip-panel" id="panelNovosMes">
    <div class="vip-panel__track">

        <!-- Tela 1: Lista -->
        <div class="vip-screen">
            <div class="vip-panel__header">
                <div class="vip-panel__title">
                    <span class="vip-panel__icon">✦</span>
                    Novos Clientes do Mês
                </div>
                <button class="vip-panel__close" id="novosMesClose" type="button" aria-label="Fechar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="vip-panel__body" id="novosMesListBody">
                <div class="vip-loading">Carregando...</div>
            </div>
        </div>

        <!-- Tela 2: Perfil -->
        <div class="vip-screen">
            <div class="vip-panel__header">
                <button class="vip-panel__back" id="novosMesBack" type="button">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7" />
                    </svg>
                    Voltar
                </button>
                <button class="vip-panel__close" id="novosMesCloseFromPerfil" type="button" aria-label="Fechar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="vip-panel__body" id="novosMesPerfilBody">
                <div class="vip-loading">Carregando perfil...</div>
            </div>
        </div>

    </div>
</div>
<section class="card">

    <!-- Toolbar / Filtros -->
    <form method="GET" action="./listClientes" class="table-toolbar">
        <div class="search-wrap">
            <input
                type="text"
                name="busca"
                value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>"
                placeholder="Buscar por nome, telefone ou e-mail..." />
        </div>

        <select name="status" class="toolbar-select" onchange="this.form.submit()">
            <option value="">Status: Todos</option>
            <option value="ativo" <?= (($_GET['status'] ?? '') === 'ativo') ? 'selected' : '' ?>>Ativo</option>
            <option value="inativo" <?= (($_GET['status'] ?? '') === 'inativo') ? 'selected' : '' ?>>Inativo</option>
        </select>

        <button type="submit" class="btn btn--ghost">Buscar</button>

        <span style="margin-left:auto;font-size:12px;color:var(--color-text-muted);">
            <?= (int)($totalResultados ?? 0) ?> resultado(s)
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
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($clientes)): ?>
                    <?php foreach ($clientes as $cliente): ?>
                        <tr>
                            <!-- Cliente -->
                            <td>
                                <div class="user-cell">
                                    <div class="avatar">
                                        <?= mb_strtoupper(mb_substr($cliente['nome'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <div class="user-cell__name"><?= htmlspecialchars($cliente['nome']) ?></div>
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

                            <!-- Status -->
                            <td>
                                <?php
                                $status = strtolower($cliente['status'] ?? 'ativo');
                                $badgeClass = match ($status) {
                                    'ativo'     => 'badge--success',
                                    'inativo'   => 'badge--danger',
                                    'bloqueado' => 'badge--warning',
                                    default     => 'badge--neutral',
                                };
                                $statusLabel = match ($status) {
                                    'ativo'     => 'Ativo',
                                    'inativo'   => 'Inativo',
                                    'bloqueado' => 'Bloqueado',
                                    default     => ucfirst($status),
                                };
                                ?>
                                <span class="badge <?= $badgeClass ?>">
                                    <span class="badge__dot"></span>
                                    <?= $statusLabel ?>
                                </span>
                            </td>

                            <!-- Ações -->
                            <td>
                                <div class="row-actions">
                                    <a href="./viewCliente?id=<?= $cliente['id_cliente'] ?>" class="row-action-btn primary" title="Visualizar">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </a>
                                    <a href="./editCliente?id=<?= $cliente['id_cliente'] ?>" class="row-action-btn" title="Editar">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                        </svg>
                                    </a>
                                    <?php if (($_SESSION['user_tipo'] ?? '') === 'ADMIN'): ?>
                                    <a
                                        href="./deleteCliente?id=<?= $cliente['id_cliente'] ?>"
                                        class="row-action-btn danger"
                                        title="Desativar"
                                        onclick="return confirm('Deseja desativar este cliente? Ele não será excluído permanentemente.')">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6" />
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                        </svg>
                                    </a>
                                    <?php endif; ?>
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
                                <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                            </svg>
                            Nenhum cliente encontrado.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginação -->
    <div class="pagination">
        <span>
            Mostrando <?= !empty($clientes) ? count($clientes) : 0 ?> de <?= (int)($totalResultados ?? 0) ?> resultados
        </span>
        <div class="pagination__pages">
            <?php
            $pagAtual = (int)($paginaAtual ?? 1);
            // totalPaginas já vem calculado pelo model a partir do total FILTRADO
            // (totalResultados) — não recalcular aqui a partir do total geral de
            // clientes, senão a paginação ignora a busca/filtro ativo.
            $totalPags = max(1, (int)($totalPaginas ?? 1));

            // Preserva busca/status ao trocar de página; só troca "pagina".
            $queryBase = $_GET;
            unset($queryBase['pagina'], $queryBase['url']);

            function hrefPaginaCliente($pagina, $queryBase)
            {
                return './listClientes?' . http_build_query(array_merge($queryBase, ['pagina' => $pagina]));
            }

            $inicio = max(1, $pagAtual - 1);
            $fim    = min($totalPags, $pagAtual + 1);
            ?>
            <button type="button" class="pagination__btn"
                <?= ($pagAtual <= 1) ? 'disabled' : 'onclick="window.location=\'' . hrefPaginaCliente($pagAtual - 1, $queryBase) . '\'"' ?>>‹</button>

            <?php for ($p = $inicio; $p <= $fim; $p++): ?>
                <button type="button" class="pagination__btn <?= $p === $pagAtual ? 'is-active' : '' ?>"
                    onclick="window.location='<?= hrefPaginaCliente($p, $queryBase) ?>'">
                    <?= $p ?>
                </button>
            <?php endfor; ?>

            <button type="button" class="pagination__btn"
                <?= ($pagAtual >= $totalPags) ? 'disabled' : 'onclick="window.location=\'' . hrefPaginaCliente($pagAtual + 1, $queryBase) . '\'"' ?>>›</button>
        </div>
    </div>

</section>

<div class="mt-16">
    <a href="./listClientesDesativados" class="text-secondary fs-13 link-icon">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;">
            <path d="M5 12h14M12 5l7 7-7 7" />
        </svg>
        Ver clientes desativados
    </a>
</div>

<style>
    /* Card precisa ser o ponto de ancoragem do painel */
    .stat-card--clickable {
        position: relative;
        cursor: pointer;
        transition: box-shadow .2s ease, transform .2s ease;
    }

    .stat-card--clickable:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }

    .stat-card--clickable.is-active {
        box-shadow: 0 0 0 2px var(--color-primary), var(--shadow-md);
    }

    /* ===== Painel — fixed, posição calculada via JS (getBoundingClientRect) =====
       Propositalmente NÃO usa position:absolute ancorado no card: como vários
       elementos da página (ex: .search-wrap) têm position:relative sem
       z-index, eles entram na mesma "categoria de pintura" que um ancestral
       do painel com z-index:auto, e a ordem de DOM decide quem fica por
       cima — não o z-index. Com position:fixed o painel sai desse jogo de
       stacking context local e passa a competir só no nível raiz da página. */
    .vip-panel {
        position: fixed;
        width: 360px;
        max-width: calc(100vw - 48px);
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        z-index: 9999;
        overflow: hidden;
        opacity: 0;
        transform: translateY(-6px) scale(.98);
        pointer-events: none;
        transition: opacity .18s ease, transform .18s ease;
        transform-origin: top left;
    }

    .vip-panel.is-open {
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: auto;
    }

    .vip-panel__track {
        display: flex;
        width: 200%;
        transition: transform .28s cubic-bezier(.2, .8, .2, 1);
    }

    .vip-panel.is-perfil .vip-panel__track {
        transform: translateX(-50%);
    }

    .vip-screen {
        width: 50%;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        max-height: 420px;
    }

    .vip-panel__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px;
        border-bottom: 1px solid var(--color-border);
        background: var(--color-bg-alt);
        flex-shrink: 0;
    }

    .vip-panel__title {
        font-size: 13px;
        font-weight: 700;
        color: var(--color-text);
        display: flex;
        align-items: center;
        gap: 7px;
    }

    .vip-panel__icon {
        color: #003BDD;
        font-size: 14px;
    }

    .vip-panel__close {
        width: 26px;
        height: 26px;
        border-radius: var(--radius-sm);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--color-text-muted);
        transition: background .15s, color .15s;
        flex-shrink: 0;
    }

    .vip-panel__close:hover {
        background: var(--color-border);
        color: var(--color-text);
    }

    .vip-panel__back {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 12.5px;
        font-weight: 600;
        color: var(--color-primary);
    }

    .vip-panel__back:hover {
        color: var(--color-primary-hover);
    }

    .vip-panel__body {
        padding: 8px;
        overflow-y: auto;
        flex: 1;
    }

    .vip-loading {
        text-align: center;
        padding: 32px 0;
        color: var(--color-text-muted);
        font-size: 12.5px;
    }

    .vip-empty {
        text-align: center;
        padding: 28px 16px;
        color: var(--color-text-muted);
        font-size: 12.5px;
    }

    /* --- Linha da lista --- */
    .vip-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        border-radius: var(--radius-md);
        cursor: pointer;
        transition: background .15s ease;
    }

    .vip-row:hover {
        background: var(--color-primary-soft);
    }

    .vip-row__info {
        min-width: 0;
        flex: 1;
    }

    .vip-row__name {
        font-weight: 600;
        font-size: 13px;
        color: var(--color-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .vip-row__sub {
        font-size: 11.5px;
        color: var(--color-text-muted);
        margin-top: 1px;
    }

    .vip-row__badge {
        font-size: 11px;
        font-weight: 700;
        color: var(--color-primary);
        background: var(--color-primary-soft);
        padding: 3px 9px;
        border-radius: var(--radius-pill);
        flex-shrink: 0;
    }

    /* --- Perfil --- */
    .vip-perfil-head {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 4px 4px 16px;
        margin-bottom: 12px;
        border-bottom: 1px solid var(--color-border);
    }

    .vip-perfil-name {
        font-size: 14.5px;
        font-weight: 700;
        color: var(--color-text);
    }

    .vip-perfil-tag {
        margin-top: 4px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        font-weight: 700;
        color: var(--color-primary);
        background: var(--color-primary-soft);
        padding: 3px 9px;
        border-radius: var(--radius-pill);
    }

    .vip-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        padding: 0 4px 4px;
        margin-bottom: 14px;
    }

    .vip-info-item {
        background: var(--color-bg-alt);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 8px 10px;
    }

    .vip-info-item--full {
        grid-column: 1 / -1;
    }

    .vip-info-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--color-text-muted);
        font-weight: 700;
        margin-bottom: 2px;
    }

    .vip-info-value {
        font-size: 12.5px;
        color: var(--color-text);
        font-weight: 600;
        word-break: break-word;
    }

    .vip-section-title {
        font-size: 11px;
        font-weight: 700;
        color: var(--color-text-muted);
        text-transform: uppercase;
        letter-spacing: .04em;
        margin: 4px 4px 8px;
    }

    .vip-vehicle {
        display: flex;
        align-items: center;
        gap: 9px;
        background: var(--color-bg-alt);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 8px 10px;
        font-size: 12.5px;
        color: var(--color-text);
        margin: 0 4px 6px;
    }
</style>

<script>
    (function() {
        const PRIMARY = '#003BDD';

        function iniciais(nome) {
            const partes = nome.trim().split(/\s+/);
            return ((partes[0]?.[0] || '') + (partes[1]?.[0] || '')).toUpperCase();
        }

        function formatarData(d) {
            if (!d) return 'Sem registro';
            return new Date(d).toLocaleDateString('pt-BR');
        }

        // --- Gera o HTML de uma linha na lista, adaptando o "sub" e o badge
        //     conforme o tipo de painel (vip = recorrência, novo = data de cadastro) ---
        function criarLinhaCliente(c, tipo) {
            const sub = tipo === 'vip' ?
                `${c.veiculo_principal || 'Sem veículo'} · ${formatarData(c.ultima_visita)}` :
                `${c.veiculo_principal || 'Sem veículo'} · desde ${formatarData(c.criado_em)}`;

            const badge = tipo === 'vip' ?
                `${c.total_visitas}x` :
                'Novo';

            return `
                <div class="vip-row" data-id="${c.id_cliente}">
                    <div class="avatar" style="background:${PRIMARY}">${iniciais(c.nome)}</div>
                    <div class="vip-row__info">
                        <div class="vip-row__name">${c.nome}</div>
                        <div class="vip-row__sub">${sub}</div>
                    </div>
                    <div class="vip-row__badge">${badge}</div>
                </div>
            `;
        }

        // --- Gera o HTML completo da tela de perfil, adaptando só a "tag" do topo ---
        function renderPerfil(c, tipo) {
            const tag = tipo === 'vip' ?
                `★ VIP · ${c.total_visitas} visitas` :
                `✦ Novo cliente · desde ${formatarData(c.criado_em)}`;

            const veiculosHtml = (c.veiculos && c.veiculos.length) ?
                c.veiculos.map(v => `
                    <div class="vip-vehicle">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="${PRIMARY}" stroke-width="2">
                            <path d="M5 17h14M5 17a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm14 0a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM3 17V9l2-5h14l2 5v8" />
                        </svg>
                        ${v.marca} ${v.modelo}${v.ano ? ' · ' + v.ano : ''}
                    </div>
                `).join('') :
                '<div class="vip-empty">Nenhum veículo cadastrado.</div>';

            return `
                <div class="vip-perfil-head">
                    <div class="avatar avatar--lg" style="background:${PRIMARY}">${iniciais(c.nome)}</div>
                    <div>
                        <div class="vip-perfil-name">${c.nome}</div>
                        <div class="vip-perfil-tag">${tag}</div>
                    </div>
                </div>

                <div class="vip-info-grid">
                    <div class="vip-info-item">
                        <div class="vip-info-label">Telefone</div>
                        <div class="vip-info-value">${c.telefone || 'Não informado'}</div>
                    </div>
                    <div class="vip-info-item">
                        <div class="vip-info-label">E-mail</div>
                        <div class="vip-info-value">${c.email || 'Não informado'}</div>
                    </div>
                    <div class="vip-info-item">
                        <div class="vip-info-label">Documento</div>
                        <div class="vip-info-value">${c.cpf_cnpj || 'Não informado'}</div>
                    </div>
                    <div class="vip-info-item">
                        <div class="vip-info-label">Cliente desde</div>
                        <div class="vip-info-value">${formatarData(c.criado_em)}</div>
                    </div>
                    <div class="vip-info-item vip-info-item--full">
                        <div class="vip-info-label">Endereço</div>
                        <div class="vip-info-value">${[c.endereco, c.bairro, c.cidade, c.uf].filter(Boolean).join(', ') || 'Não informado'}</div>
                    </div>
                    <div class="vip-info-item">
                        <div class="vip-info-label">Primeira visita</div>
                        <div class="vip-info-value">${formatarData(c.primeira_visita)}</div>
                    </div>
                    <div class="vip-info-item">
                        <div class="vip-info-label">Última visita</div>
                        <div class="vip-info-value">${formatarData(c.ultima_visita)}</div>
                    </div>
                </div>

                <div class="vip-section-title">Veículos</div>
                ${veiculosHtml}
            `;
        }

        // --- Inicializa um painel completo (card + lista + perfil) a partir de uma config ---
        function initStatPanel(cfg) {
            const card = document.getElementById(cfg.cardId);
            const panel = document.getElementById(cfg.panelId);
            const listBody = document.getElementById(cfg.listBodyId);
            const perfilBody = document.getElementById(cfg.perfilBodyId);

            function posicionarPainel() {
                const rect = card.getBoundingClientRect();
                const margem = 16;
                let left = rect.left;
                const larguraPainel = panel.offsetWidth || 360;

                if (left + larguraPainel > window.innerWidth - margem) {
                    left = Math.max(margem, window.innerWidth - larguraPainel - margem);
                }

                panel.style.top = (rect.bottom + 10) + 'px';
                panel.style.left = left + 'px';
            }

            function abrirPainel() {
                posicionarPainel();
                panel.classList.add('is-open');
                card.classList.add('is-active');
                carregarLista();

                window.addEventListener('scroll', posicionarPainel, true);
                window.addEventListener('resize', posicionarPainel);
            }

            function fecharPainel() {
                panel.classList.remove('is-open', 'is-perfil');
                card.classList.remove('is-active');

                window.removeEventListener('scroll', posicionarPainel, true);
                window.removeEventListener('resize', posicionarPainel);
            }

            function togglePainel(e) {
                e.stopPropagation();
                panel.classList.contains('is-open') ? fecharPainel() : abrirPainel();
            }

            function carregarLista() {
                listBody.innerHTML = '<div class="vip-loading">Carregando...</div>';

                fetch(cfg.listEndpoint)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.sucesso || !data.clientes.length) {
                            listBody.innerHTML = `<div class="vip-empty">${cfg.emptyMessage}</div>`;
                            return;
                        }
                        listBody.innerHTML = data.clientes.map(c => criarLinhaCliente(c, cfg.tipo)).join('');

                        listBody.querySelectorAll('.vip-row').forEach(row => {
                            row.addEventListener('click', () => abrirPerfil(row.dataset.id));
                        });
                    })
                    .catch(() => {
                        listBody.innerHTML = `<div class="vip-empty">${cfg.errorMessage}</div>`;
                    });
            }

            function abrirPerfil(id) {
                panel.classList.add('is-perfil');
                perfilBody.innerHTML = '<div class="vip-loading">Carregando perfil...</div>';

                fetch('./vipPerfil?id=' + encodeURIComponent(id))
                    .then(r => r.json())
                    .then(data => {
                        if (!data.sucesso) {
                            perfilBody.innerHTML = '<div class="vip-empty">Não foi possível carregar o perfil.</div>';
                            return;
                        }
                        perfilBody.innerHTML = renderPerfil(data.cliente, cfg.tipo);
                    })
                    .catch(() => {
                        perfilBody.innerHTML = '<div class="vip-empty">Erro ao carregar perfil.</div>';
                    });
            }

            function voltarLista(e) {
                e.stopPropagation();
                panel.classList.remove('is-perfil');
            }

            card.addEventListener('click', togglePainel);
            document.getElementById(cfg.closeId).addEventListener('click', (e) => {
                e.stopPropagation();
                fecharPainel();
            });
            document.getElementById(cfg.closeFromPerfilId).addEventListener('click', (e) => {
                e.stopPropagation();
                fecharPainel();
            });
            document.getElementById(cfg.backId).addEventListener('click', voltarLista);

            panel.addEventListener('click', (e) => e.stopPropagation());

            document.addEventListener('click', (e) => {
                if (!panel.contains(e.target) && !card.contains(e.target)) {
                    fecharPainel();
                }
            });

            return {
                fecharPainel
            };
        }

        const painelVip = initStatPanel({
            cardId: 'cardClientesVip',
            panelId: 'vipPanel',
            listBodyId: 'vipListBody',
            perfilBodyId: 'vipPerfilBody',
            closeId: 'vipClose',
            closeFromPerfilId: 'vipCloseFromPerfil',
            backId: 'vipBack',
            listEndpoint: './vipList',
            tipo: 'vip',
            emptyMessage: 'Nenhum cliente VIP encontrado ainda.',
            errorMessage: 'Erro ao carregar clientes VIP.'
        });

        const painelNovos = initStatPanel({
            cardId: 'cardNovosMes',
            panelId: 'panelNovosMes',
            listBodyId: 'novosMesListBody',
            perfilBodyId: 'novosMesPerfilBody',
            closeId: 'novosMesClose',
            closeFromPerfilId: 'novosMesCloseFromPerfil',
            backId: 'novosMesBack',
            listEndpoint: './novosMesList',
            tipo: 'novo',
            emptyMessage: 'Nenhum cliente novo este mês ainda.',
            errorMessage: 'Erro ao carregar novos clientes.'
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                painelVip.fecharPainel();
                painelNovos.fecharPainel();
            }
        });
    })();
</script>