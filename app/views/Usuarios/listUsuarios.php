<?php
// Variáveis esperadas do controller:
// $usuarios         array — lista de usuários (da página atual)
// $totalUsuarios    int   — total de registros (todas as páginas)
// $paginaAtual      int   — página atual
// $totalPaginas     int   — total de páginas
//
// Opcionais (se o controller já tiver esses agregados, melhor performance;
// caso não sejam enviados, os cards fazem fallback contando $usuarios da página atual):
// $totalAtivos      int
// $totalInativos    int
// $totalAdmins      int
// $totalNovos30d    int   — usuários criados nos últimos 30 dias

// ── Fallbacks para os cards de estatística ──
$totalAtivos   = $totalAtivos   ?? count(array_filter($usuarios ?? [], fn($u) => (int)($u['ativo'] ?? 0) === 1));
$totalInativos = $totalInativos ?? count(array_filter($usuarios ?? [], fn($u) => (int)($u['ativo'] ?? 0) === 0));
$totalAdmins   = $totalAdmins   ?? count(array_filter($usuarios ?? [], fn($u) => ($u['tipo'] ?? '') === 'ADMIN'));
$totalNovos30d = $totalNovos30d ?? count(array_filter($usuarios ?? [], fn($u) => !empty($u['criado_em']) && strtotime($u['criado_em']) >= strtotime('-30 days')));

$buscaAtual  = $_GET['busca'] ?? '';
$tipoAtual   = $_GET['tipo'] ?? '';
$statusAtual = $_GET['status'] ?? '';
$temFiltro   = ($buscaAtual !== '' || $tipoAtual !== '' || $statusAtual !== '');

// Retorno das ações de exclusão (UserController::delete)
$flashErro = [
    'vinculos' => 'Este usuário não pode ser excluído porque tem histórico na oficina'
        . (!empty($_GET['detalhe']) ? ' (' . htmlspecialchars($_GET['detalhe']) . ')' : '')
        . '. Apagá-lo destruiria a autoria desses registros — use o botão de status para desativá-lo.',
    'proprio'  => 'Você não pode excluir a sua própria conta.',
    'exclusao' => 'Não foi possível excluir este usuário.',
][$_GET['erro'] ?? ''] ?? null;

$flashOk = (($_GET['ok'] ?? '') === 'excluido') ? 'Usuário excluído.' : null;
?>

<?php if ($flashErro !== null): ?>
    <div style="background:var(--color-danger-soft);color:#991B1B;border:1px solid #FECACA;border-radius:var(--radius-md);padding:12px 16px;margin-bottom:16px;font-size:13px;">
        <?= $flashErro ?>
    </div>
<?php endif; ?>
<?php if ($flashOk !== null): ?>
    <div style="background:var(--color-success-soft);color:#166534;border:1px solid #BBF7D0;border-radius:var(--radius-md);padding:12px 16px;margin-bottom:16px;font-size:13px;">
        <?= $flashOk ?>
    </div>
<?php endif; ?>

<!-- ── Page Header ── -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Usuários</h1>
        <p class="page-header__subtitle">Gerencie contas, permissões e status de acesso do sistema.</p>
    </div>
    <div class="page-header__actions">
        <a href="./exportUsuarios<?= $temFiltro ? '?' . http_build_query($_GET) : '' ?>" class="btn btn--ghost">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
            Exportar
        </a>
        <a href="./saveUsuario" class="btn btn--primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Novo Usuário
        </a>
    </div>
</div>

<!-- ── Cards de resumo ── -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-card__head">
            <span class="stat-card__label">Total de usuários</span>
            <div class="stat-card__icon b1">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $totalUsuarios ?? 0 ?></div>
        <span class="stat-card__delta neutral">Cadastrados no sistema</span>
    </div>

    <div class="stat-card">
        <div class="stat-card__head">
            <span class="stat-card__label">Ativos</span>
            <div class="stat-card__icon b2">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                    <polyline points="22 4 12 14.01 9 11.01" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $totalAtivos ?></div>
        <span class="stat-card__delta">Com acesso liberado</span>
    </div>

    <div class="stat-card">
        <div class="stat-card__head">
            <span class="stat-card__label">Administradores</span>
            <div class="stat-card__icon b3">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $totalAdmins ?></div>
        <span class="stat-card__delta neutral">Perfil com acesso total</span>
    </div>

    <!-- ====== CARD "Novos (30 dias)" — agora clicável ====== -->
    <div class="stat-card stat-card--clickable" id="cardUsuariosNovos30d" role="button" tabindex="0">
        <div class="stat-card__head">
            <span class="stat-card__label">Novos (30 dias)</span>
            <div class="stat-card__icon b4">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $totalNovos30d ?></div>
        <span class="stat-card__delta">Clique para ver os novos usuários</span>
    </div>
    <!-- ====== /CARD ====== -->
</div>

<!-- ============================================================
     PAINEL "Novos Usuários" — fixed + posicionado via JS, mesmo
     padrão usado nas telas de Clientes e Veículos.
     ============================================================ -->
<div class="usr-panel" id="panelUsuariosNovos30d">
    <div class="usr-panel__track">

        <!-- Tela 1: Lista -->
        <div class="usr-screen">
            <div class="usr-panel__header">
                <div class="usr-panel__title">
                    <span class="usr-panel__icon">✦</span>
                    Novos Usuários (30 dias)
                </div>
                <button class="usr-panel__close" id="usrNovosClose" type="button" aria-label="Fechar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="usr-panel__body" id="usrNovosListBody">
                <div class="usr-loading">Carregando...</div>
            </div>
        </div>

        <!-- Tela 2: Perfil do usuário -->
        <div class="usr-screen">
            <div class="usr-panel__header">
                <button class="usr-panel__back" id="usrNovosBack" type="button">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7" />
                    </svg>
                    Voltar
                </button>
                <button class="usr-panel__close" id="usrNovosCloseFromPerfil" type="button" aria-label="Fechar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="usr-panel__body" id="usrNovosPerfilBody">
                <div class="usr-loading">Carregando perfil...</div>
            </div>
        </div>

    </div>
</div>

<!-- ── Tabela ── -->
<section class="card">

    <!-- Toolbar / Filtros -->
    <form method="GET" action="./listUsuarios" class="table-toolbar">

        <div class="search-wrap">
            <input
                type="text"
                name="busca"
                value="<?= htmlspecialchars($buscaAtual) ?>"
                placeholder="Buscar por nome ou e-mail..." />
        </div>

        <select name="tipo" class="toolbar-select" onchange="this.form.submit()">
            <option value="">Tipo: Todos</option>
            <option value="ADMIN" <?= ($tipoAtual === 'ADMIN') ? 'selected' : '' ?>>Admin</option>
            <option value="TECNICO" <?= ($tipoAtual === 'TECNICO') ? 'selected' : '' ?>>Técnico</option>
            <option value="ATENDENTE" <?= ($tipoAtual === 'ATENDENTE') ? 'selected' : '' ?>>Atendente</option>
        </select>

        <select name="status" class="toolbar-select" onchange="this.form.submit()">
            <option value="">Status: Todos</option>
            <option value="1" <?= ($statusAtual === '1') ? 'selected' : '' ?>>Ativo</option>
            <option value="0" <?= ($statusAtual === '0') ? 'selected' : '' ?>>Inativo</option>
        </select>

        <button type="submit" class="btn btn--ghost">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
            </svg>
            Filtrar
        </button>

        <?php if ($temFiltro): ?>
            <a href="./listUsuarios" class="btn btn--ghost" style="color:var(--color-danger);">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
                Limpar
            </a>
        <?php endif; ?>

        <span style="margin-left:auto;font-size:12px;color:var(--color-text-muted);white-space:nowrap;">
            <?= count($usuarios ?? []) ?> resultado(s)
        </span>

    </form>

    <!-- Tabela de usuários -->
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Usuário</th>
                    <th>Contato</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Criado em</th>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($usuarios)): ?>
                    <?php foreach ($usuarios as $usuario): ?>
                        <?php
                        $iniciais = strtoupper(implode('', array_map(
                            fn($p) => $p[0],
                            array_slice(explode(' ', trim($usuario['nome'] ?? '')), 0, 2)
                        )));
                        $ativo = (int)($usuario['ativo'] ?? 0);

                        // Cor de avatar por tipo — reforça a hierarquia visual sem
                        // sair da paleta azul monocromática do design system.
                        $avatarStyle = match ($usuario['tipo'] ?? '') {
                            'ADMIN'   => 'background:linear-gradient(135deg,#111826 0%,#1E293B 100%);',
                            'TECNICO' => '',
                            default   => 'background:linear-gradient(135deg,#F59E0B 0%,#D97706 100%);',
                        };
                        ?>
                        <tr>
                            <!-- Usuário -->
                            <td>
                                <div class="user-cell">
                                    <div class="avatar avatar--sm" style="<?= $avatarStyle ?>"><?= htmlspecialchars($iniciais) ?></div>
                                    <div>
                                        <div class="user-cell__name"><?= htmlspecialchars($usuario['nome'] ?? '-') ?></div>
                                        <div class="user-cell__sub">#<?= htmlspecialchars($usuario['id_usuario'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>

                            <!-- Contato -->
                            <td>
                                <div style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--color-text-muted);">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                        <polyline points="22,6 12,13 2,6" />
                                    </svg>
                                    <?= htmlspecialchars($usuario['email'] ?? '-') ?>
                                </div>
                            </td>

                            <!-- Tipo -->
                            <td>
                                <?php if (($usuario['tipo'] ?? '') === 'ADMIN'): ?>
                                    <span class="badge badge--info">
                                        <span class="badge__dot"></span>Admin
                                    </span>
                                <?php elseif (($usuario['tipo'] ?? '') === 'TECNICO'): ?>
                                    <span class="badge badge--secondary">
                                        <span class="badge__dot"></span>Técnico
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge--warning">
                                        <span class="badge__dot"></span>Atendente
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Status (clicável, sem sair da página) -->
                            <td>
                                <a href="./toggleStatusUsuario&id=<?= $usuario['id_usuario'] ?>"
                                    title="Clique para <?= $ativo === 1 ? 'desativar' : 'ativar' ?>"
                                    onclick="return confirm('<?= $ativo === 1 ? 'Desativar' : 'Ativar' ?> este usuário?')"
                                    style="text-decoration:none;">
                                    <?php if ($ativo === 1): ?>
                                        <span class="badge badge--success">
                                            <span class="badge__dot"></span>Ativo
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge--danger">
                                            <span class="badge__dot"></span>Inativo
                                        </span>
                                    <?php endif; ?>
                                </a>
                            </td>

                            <!-- Criado em -->
                            <td class="text-muted">
                                <?= !empty($usuario['criado_em'])
                                    ? date('d/m/Y', strtotime($usuario['criado_em']))
                                    : '--' ?>
                            </td>

                            <!-- Ações -->
                            <td>
                                <div class="row-actions" style="justify-content:flex-end;">
                                    <a href="./viewUsuario&id=<?= $usuario['id_usuario'] ?>"
                                        class="row-action-btn primary"
                                        title="Visualizar">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </a>

                                    <a href="./editUsuario&id=<?= $usuario['id_usuario'] ?>"
                                        class="row-action-btn"
                                        title="Editar">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                        </svg>
                                    </a>

                                    <a href="./deleteUsuario&id=<?= $usuario['id_usuario'] ?>"
                                        class="row-action-btn danger"
                                        title="Excluir"
                                        onclick="return confirm('Deseja excluir este usuário? Esta ação não pode ser desfeita.')">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="3 6 5 6 21 6" />
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center;padding:56px 20px;color:var(--color-text-muted);">
                            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 12px;display:block;opacity:.4;">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                            </svg>
                            <div style="font-weight:600;color:var(--color-text-secondary);margin-bottom:4px;">
                                Nenhum usuário encontrado
                            </div>
                            <div style="font-size:12px;margin-bottom:16px;">
                                <?= $temFiltro
                                    ? 'Tente ajustar os filtros de busca.'
                                    : 'Comece cadastrando o primeiro usuário do sistema.' ?>
                            </div>
                            <?php if ($temFiltro): ?>
                                <a href="./listUsuarios" class="btn btn--ghost btn--sm">Limpar filtros</a>
                            <?php else: ?>
                                <a href="./saveUsuario" class="btn btn--primary btn--sm">Novo Usuário</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginação -->
    <div class="pagination table-footer">
        <span>
            Mostrando <?= count($usuarios ?? []) ?> de <?= $totalUsuarios ?? 0 ?> resultados
        </span>

        <?php if (!empty($totalPaginas) && $totalPaginas > 1): ?>
            <div class="pagination__pages">
                <button
                    class="pagination__btn"
                    <?= ($paginaAtual <= 1) ? 'disabled' : '' ?>
                    onclick="window.location='?<?= http_build_query(array_merge($_GET, ['pagina' => ($paginaAtual - 1)])) ?>'">‹</button>

                <?php
                // Paginação compacta: mostra sempre a 1ª, a última, a atual e
                // vizinhas próximas — evita uma lista infinita de botões
                // quando há muitas páginas.
                $janela = 1;
                $ultimaImpressa = 0;
                for ($p = 1; $p <= $totalPaginas; $p++):
                    $exibir = ($p === 1 || $p === $totalPaginas || abs($p - $paginaAtual) <= $janela);
                    if (!$exibir) continue;
                    if ($ultimaImpressa && $p - $ultimaImpressa > 1): ?>
                        <span class="pagination__btn" style="border:none;cursor:default;">…</span>
                    <?php endif; ?>
                    <button
                        class="pagination__btn <?= ($p == $paginaAtual) ? 'is-active' : '' ?>"
                        onclick="window.location='?<?= http_build_query(array_merge($_GET, ['pagina' => $p])) ?>'"><?= $p ?></button>
                <?php $ultimaImpressa = $p;
                endfor; ?>

                <button
                    class="pagination__btn"
                    <?= ($paginaAtual >= $totalPaginas) ? 'disabled' : '' ?>
                    onclick="window.location='?<?= http_build_query(array_merge($_GET, ['pagina' => ($paginaAtual + 1)])) ?>'">›</button>
            </div>
        <?php endif; ?>
    </div>

</section>

<style>
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

    /* ===== Painel — fixed, posição calculada via JS (getBoundingClientRect) ===== */
    .usr-panel {
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

    .usr-panel.is-open {
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: auto;
    }

    .usr-panel__track {
        display: flex;
        width: 200%;
        transition: transform .28s cubic-bezier(.2, .8, .2, 1);
    }

    .usr-panel.is-perfil .usr-panel__track {
        transform: translateX(-50%);
    }

    .usr-screen {
        width: 50%;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        max-height: 440px;
    }

    .usr-panel__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px;
        border-bottom: 1px solid var(--color-border);
        background: var(--color-bg-alt);
        flex-shrink: 0;
    }

    .usr-panel__title {
        font-size: 13px;
        font-weight: 700;
        color: var(--color-text);
        display: flex;
        align-items: center;
        gap: 7px;
    }

    .usr-panel__icon {
        color: var(--color-primary);
        font-size: 14px;
    }

    .usr-panel__close {
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

    .usr-panel__close:hover {
        background: var(--color-border);
        color: var(--color-text);
    }

    .usr-panel__back {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 12.5px;
        font-weight: 600;
        color: var(--color-primary);
    }

    .usr-panel__back:hover {
        color: var(--color-primary-hover);
    }

    .usr-panel__body {
        padding: 8px;
        overflow-y: auto;
        flex: 1;
    }

    .usr-loading {
        text-align: center;
        padding: 32px 0;
        color: var(--color-text-muted);
        font-size: 12.5px;
    }

    .usr-empty {
        text-align: center;
        padding: 28px 16px;
        color: var(--color-text-muted);
        font-size: 12.5px;
    }

    /* --- Linha da lista --- */
    .usr-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        border-radius: var(--radius-md);
        cursor: pointer;
        transition: background .15s ease;
    }

    .usr-row:hover {
        background: var(--color-primary-soft);
    }

    .usr-row__info {
        min-width: 0;
        flex: 1;
    }

    .usr-row__name {
        font-weight: 600;
        font-size: 13px;
        color: var(--color-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .usr-row__sub {
        font-size: 11.5px;
        color: var(--color-text-muted);
        margin-top: 1px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .usr-row__badge {
        font-size: 11px;
        font-weight: 700;
        color: var(--color-primary);
        background: var(--color-primary-soft);
        padding: 3px 9px;
        border-radius: var(--radius-pill);
        flex-shrink: 0;
    }

    /* --- Perfil --- */
    .usr-perfil-head {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 4px 4px 16px;
        margin-bottom: 12px;
        border-bottom: 1px solid var(--color-border);
    }

    .usr-perfil-name {
        font-size: 14.5px;
        font-weight: 700;
        color: var(--color-text);
    }

    .usr-perfil-tag {
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

    .usr-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        padding: 0 4px 4px;
    }

    .usr-info-item {
        background: var(--color-bg-alt);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 8px 10px;
    }

    .usr-info-item--full {
        grid-column: 1 / -1;
    }

    .usr-info-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--color-text-muted);
        font-weight: 700;
        margin-bottom: 2px;
    }

    .usr-info-value {
        font-size: 12.5px;
        color: var(--color-text);
        font-weight: 600;
        word-break: break-word;
    }
</style>

<script>
    (function() {
        const TIPO_LABEL = {
            ADMIN: 'Admin',
            TECNICO: 'Técnico',
            ATENDENTE: 'Atendente',
        };

        const TIPO_COR = {
            ADMIN: 'linear-gradient(135deg,#111826 0%,#1E293B 100%)',
            TECNICO: '#003BDD',
            ATENDENTE: 'linear-gradient(135deg,#F59E0B 0%,#D97706 100%)',
        };

        function iniciais(nome) {
            const partes = (nome || '').trim().split(/\s+/);
            return ((partes[0]?.[0] || '') + (partes[1]?.[0] || '')).toUpperCase();
        }

        function formatarData(d) {
            if (!d) return 'Sem registro';
            return new Date(d).toLocaleDateString('pt-BR');
        }

        function corPorTipo(tipo) {
            return TIPO_COR[tipo] || '#003BDD';
        }

        function labelTipo(tipo) {
            return TIPO_LABEL[tipo] || (tipo || '—');
        }

        function criarLinhaUsuario(u) {
            return `
                <div class="usr-row" data-id="${u.id_usuario}">
                    <div class="avatar avatar--sm" style="background:${corPorTipo(u.tipo)}">${iniciais(u.nome)}</div>
                    <div class="usr-row__info">
                        <div class="usr-row__name">${u.nome}</div>
                        <div class="usr-row__sub">${labelTipo(u.tipo)} · desde ${formatarData(u.criado_em)}</div>
                    </div>
                    <div class="usr-row__badge">Novo</div>
                </div>
            `;
        }

        function renderPerfilUsuario(u) {
            const statusLabel = (parseInt(u.ativo, 10) === 1) ? 'Ativo' : 'Inativo';

            return `
                <div class="usr-perfil-head">
                    <div class="avatar avatar--lg" style="background:${corPorTipo(u.tipo)}">${iniciais(u.nome)}</div>
                    <div>
                        <div class="usr-perfil-name">${u.nome}</div>
                        <div class="usr-perfil-tag">✦ Novo · desde ${formatarData(u.criado_em)}</div>
                    </div>
                </div>

                <div class="usr-info-grid">
                    <div class="usr-info-item">
                        <div class="usr-info-label">Tipo</div>
                        <div class="usr-info-value">${labelTipo(u.tipo)}</div>
                    </div>
                    <div class="usr-info-item">
                        <div class="usr-info-label">Status</div>
                        <div class="usr-info-value">${statusLabel}</div>
                    </div>
                    <div class="usr-info-item usr-info-item--full">
                        <div class="usr-info-label">E-mail</div>
                        <div class="usr-info-value">${u.email || 'Não informado'}</div>
                    </div>
                    <div class="usr-info-item">
                        <div class="usr-info-label">Cadastrado em</div>
                        <div class="usr-info-value">${formatarData(u.criado_em)}</div>
                    </div>
                    <div class="usr-info-item">
                        <div class="usr-info-label">Última atualização</div>
                        <div class="usr-info-value">${formatarData(u.atualizado_em)}</div>
                    </div>
                </div>
            `;
        }

        const card = document.getElementById('cardUsuariosNovos30d');
        const panel = document.getElementById('panelUsuariosNovos30d');
        const listBody = document.getElementById('usrNovosListBody');
        const perfilBody = document.getElementById('usrNovosPerfilBody');

        function posicionarPainel() {
            const rect = card.getBoundingClientRect();
            const margem = 16;
            let left = rect.left;
            const larguraPainel = panel.offsetWidth || 360;

            // Card fica à direita no grid — evita vazar pela borda direita da tela.
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
            listBody.innerHTML = '<div class="usr-loading">Carregando...</div>';

            fetch('./listUsuariosNovos30d')
                .then(r => r.json())
                .then(data => {
                    if (!data.sucesso || !data.usuarios.length) {
                        listBody.innerHTML = '<div class="usr-empty">Nenhum usuário novo nos últimos 30 dias.</div>';
                        return;
                    }
                    listBody.innerHTML = data.usuarios.map(criarLinhaUsuario).join('');

                    listBody.querySelectorAll('.usr-row').forEach(row => {
                        row.addEventListener('click', () => abrirPerfil(row.dataset.id));
                    });
                })
                .catch(() => {
                    listBody.innerHTML = '<div class="usr-empty">Erro ao carregar novos usuários.</div>';
                });
        }

        function abrirPerfil(id) {
            panel.classList.add('is-perfil');
            perfilBody.innerHTML = '<div class="usr-loading">Carregando perfil...</div>';

            fetch('./usuarioPerfilJson?id=' + encodeURIComponent(id))
                .then(r => r.json())
                .then(data => {
                    if (!data.sucesso) {
                        perfilBody.innerHTML = '<div class="usr-empty">Não foi possível carregar o perfil.</div>';
                        return;
                    }
                    perfilBody.innerHTML = renderPerfilUsuario(data.usuario);
                })
                .catch(() => {
                    perfilBody.innerHTML = '<div class="usr-empty">Erro ao carregar perfil.</div>';
                });
        }

        function voltarLista(e) {
            e.stopPropagation();
            panel.classList.remove('is-perfil');
        }

        card.addEventListener('click', togglePainel);
        document.getElementById('usrNovosClose').addEventListener('click', (e) => {
            e.stopPropagation();
            fecharPainel();
        });
        document.getElementById('usrNovosCloseFromPerfil').addEventListener('click', (e) => {
            e.stopPropagation();
            fecharPainel();
        });
        document.getElementById('usrNovosBack').addEventListener('click', voltarLista);

        panel.addEventListener('click', (e) => e.stopPropagation());

        document.addEventListener('click', (e) => {
            if (!panel.contains(e.target) && !card.contains(e.target)) {
                fecharPainel();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') fecharPainel();
        });
    })();
</script>