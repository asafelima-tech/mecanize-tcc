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

$buscaAtual = $_GET['busca'] ?? '';
$tipoAtual  = $_GET['tipo'] ?? '';

// Aba visível (ativos por padrão). O recorte por status é da aba, não é mais
// um filtro da toolbar — quem decide de verdade é UserController::abaAtual();
// aqui é só o que a tela usa pra marcar a aba selecionada.
$abaAtual = $aba ?? 'ativos';

// Busca/tipo são os únicos filtros da toolbar agora.
$temFiltro = ($buscaAtual !== '' || $tipoAtual !== '');

// A exportação tem que sair igual ao que está na tela: mesma aba e mesmos
// filtros (o controller usa filtrosDaTela() nos dois casos).
$queryExport = array_filter([
    'aba'   => $abaAtual,
    'busca' => $buscaAtual,
    'tipo'  => $tipoAtual,
], fn($v) => $v !== '');

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
        <a href="./exportUsuarios?<?= htmlspecialchars(http_build_query($queryExport)) ?>" class="btn btn--ghost" id="usuarios-export">
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
        <div class="stat-card__value"><?= $totalGeral ?? (($totalAtivos ?? 0) + ($totalInativos ?? 0)) ?></div>
        <span class="stat-card__delta neutral">Ativos e inativos</span>
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

<!-- ── Abas: Ativos / Inativos ── -->
<div class="usr-abas" role="tablist" aria-label="Visualização de usuários">
    <a href="./listUsuarios?aba=ativos"
       class="usr-aba<?= $abaAtual === 'ativos' ? ' usr-aba--ativa' : '' ?>"
       role="tab" aria-selected="<?= $abaAtual === 'ativos' ? 'true' : 'false' ?>"
       data-usuarios-aba="ativos">
        Usuários Ativos
        <span class="usr-aba__contador"><?= (int) ($totalAtivos ?? 0) ?></span>
    </a>
    <a href="./listUsuarios?aba=inativos"
       class="usr-aba<?= $abaAtual === 'inativos' ? ' usr-aba--ativa' : '' ?>"
       role="tab" aria-selected="<?= $abaAtual === 'inativos' ? 'true' : 'false' ?>"
       data-usuarios-aba="inativos">
        Usuários Inativos
        <span class="usr-aba__contador"><?= (int) ($totalInativos ?? 0) ?></span>
    </a>
</div>

<!-- ── Tabela ── -->
<section class="card">
    <div id="usuarios-conteudo" aria-busy="false">
        <?php require __DIR__ . '/_tabelaUsuarios.php'; ?>
    </div>
</section>

<style>
    /* ===== Abas Ativos / Inativos ===== */
    .usr-abas {
        display: inline-flex;
        gap: 4px;
        background: var(--color-bg-subtle, #F1F5F9);
        border-radius: var(--radius-md);
        padding: 4px;
        margin-bottom: 16px;
    }

    .usr-aba {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: calc(var(--radius-md) - 2px);
        font-size: 13px;
        font-weight: 600;
        color: var(--color-text-muted);
        text-decoration: none;
        transition: background .12s ease, color .12s ease;
        white-space: nowrap;
    }

    .usr-aba:hover { color: var(--color-text); }

    .usr-aba--ativa {
        background: var(--color-surface, #FFF);
        color: var(--color-primary, #094FE4);
        box-shadow: 0 1px 2px rgba(0, 0, 0, .08);
    }

    .usr-aba__contador {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        border-radius: 99px;
        background: var(--color-border, #E2E8F0);
        color: var(--color-text-muted);
        font-size: 11px;
        font-weight: 700;
    }

    .usr-aba--ativa .usr-aba__contador {
        background: var(--color-primary, #094FE4);
        color: #fff;
    }

    /* Enquanto o fetch da aba/página não volta, o conteúdo antigo esmaece
       em vez de sumir — evita o "pulo" de altura da tabela. */
    #usuarios-conteudo[aria-busy="true"] {
        opacity: .5;
        pointer-events: none;
        transition: opacity .12s ease;
    }

    @media (max-width: 560px) {
        .usr-abas { display: flex; width: 100%; }
        .usr-aba { flex: 1; justify-content: center; }
    }

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
<script>
    /**
     * Troca entre "Usuários Ativos" e "Usuários Inativos" sem sair da página.
     *
     * Busca/paginação continuam server-side (é o banco que filtra e pagina),
     * então a troca não pode ser só esconder linhas no DOM: o navegador só
     * tem a página atual. A aba pede o miolo da tabela à MESMA rota
     * (./listUsuarios com ?fragmento=1) e substitui só o #usuarios-conteudo.
     *
     * Tudo é progressivo: abas, paginação e "limpar" são <a> de verdade e o
     * filtro é um <form> GET normal — sem JS, a tela funciona recarregando.
     */
    (function () {
        const conteudo = document.getElementById('usuarios-conteudo');
        const abas     = Array.from(document.querySelectorAll('[data-usuarios-aba]'));
        const exportar = document.getElementById('usuarios-export');

        if (!conteudo || !abas.length) return;

        let requisicaoAtual = 0;

        function abaDaUrl(url) {
            return new URL(url, window.location.href).searchParams.get('aba') === 'inativos'
                ? 'inativos'
                : 'ativos';
        }

        function marcarAba(aba) {
            abas.forEach(function (a) {
                const ehEsta = a.dataset.usuariosAba === aba;
                a.classList.toggle('usr-aba--ativa', ehEsta);
                a.setAttribute('aria-selected', ehEsta ? 'true' : 'false');
            });
        }

        // O CSV tem que sair igual ao que está na tela (mesma aba e filtros).
        function sincronizarExport(url) {
            if (!exportar) return;

            const origem  = new URL(url, window.location.href).searchParams;
            const destino = new URLSearchParams();

            ['aba', 'busca', 'tipo'].forEach(function (chave) {
                const valor = origem.get(chave);
                if (valor) destino.set(chave, valor);
            });

            exportar.href = './exportUsuarios?' + destino.toString();
        }

        async function carregar(url, comHistorico) {
            const id = ++requisicaoAtual;

            conteudo.setAttribute('aria-busy', 'true');

            try {
                const alvo = new URL(url, window.location.href);
                alvo.searchParams.set('fragmento', '1');

                const resposta = await fetch(alvo, {
                    headers: { 'X-Requested-With': 'fetch' },
                    credentials: 'same-origin',
                });

                if (!resposta.ok) throw new Error('HTTP ' + resposta.status);

                // O front-controller redireciona (302) quando a sessão caiu ou
                // o caixa foi fechado em outra aba. O fetch segue o redirect
                // calado, e sem isto a tela de login/abertura de caixa acabaria
                // injetada dentro da tabela. Recarrega de verdade nesses casos.
                if (resposta.redirected && !/[?&]fragmento=1/.test(resposta.url)) {
                    window.location.href = resposta.url;
                    return;
                }

                const html = await resposta.text();

                // Uma resposta antiga não pode sobrescrever uma mais nova
                // (cliques rápidos entre as abas).
                if (id !== requisicaoAtual) return;

                conteudo.innerHTML = html;
                marcarAba(abaDaUrl(url));
                sincronizarExport(url);

                if (comHistorico) {
                    // URL sem "fragmento": recarregar a página na mão tem que
                    // devolver a tela inteira na mesma aba.
                    history.pushState({ usuarios: true }, '', url);
                }
            } catch (erro) {
                if (id !== requisicaoAtual) return;
                // Falhou o fetch (offline, sessão expirada): cai na navegação
                // normal em vez de deixar a tela travada.
                window.location.href = url;
            } finally {
                if (id === requisicaoAtual) {
                    conteudo.setAttribute('aria-busy', 'false');
                }
            }
        }

        // ── Abas ──
        abas.forEach(function (aba) {
            aba.addEventListener('click', function (e) {
                if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
                e.preventDefault();
                carregar(aba.getAttribute('href'), true);
            });
        });

        // ── Paginação e "limpar filtros" (vêm dentro do fragmento trocado,
        //    por isso delegação em vez de listener por elemento) ──
        conteudo.addEventListener('click', function (e) {
            const link = e.target.closest('[data-usuarios-link]');
            if (!link) return;
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;

            e.preventDefault();
            carregar(link.getAttribute('href'), true);
        });

        // ── Formulário de busca/tipo ──
        conteudo.addEventListener('submit', function (e) {
            const form = e.target.closest('[data-usuarios-filtro]');
            if (!form) return;

            e.preventDefault();

            const dados = new URLSearchParams(new FormData(form));
            // Filtro novo recomeça da primeira página.
            dados.delete('pagina');

            carregar('./listUsuarios?' + dados.toString(), true);
        });

        // ── Voltar/avançar do navegador ──
        window.addEventListener('popstate', function () {
            carregar(window.location.href, false);
        });
    })();
</script>
