<?php
// Variáveis esperadas do controller:
// $clientes, $totalClientes, $novosMes, $clientesVip, $paginaAtual,
// $totalResultados, $totalPaginas, $totalAtivos, $totalInativos, $aba

// Aba visível (ativos por padrão). O recorte por status é da aba, não é mais
// um filtro da toolbar — quem decide de verdade é ClienteController::abaAtual();
// aqui é só o que a tela usa pra marcar a aba selecionada.
$abaAtual = $aba ?? 'ativos';
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
<!-- ── Abas: Ativos / Inativos ── -->
<div class="abas" role="tablist" aria-label="Visualização de clientes">
    <a href="./listClientes?aba=ativos"
       class="aba<?= $abaAtual === 'ativos' ? ' aba--ativa' : '' ?>"
       role="tab" aria-selected="<?= $abaAtual === 'ativos' ? 'true' : 'false' ?>"
       data-clientes-aba="ativos">
        Clientes Ativos
        <span class="aba__contador"><?= (int) ($totalAtivos ?? 0) ?></span>
    </a>
    <a href="./listClientes?aba=inativos"
       class="aba<?= $abaAtual === 'inativos' ? ' aba--ativa' : '' ?>"
       role="tab" aria-selected="<?= $abaAtual === 'inativos' ? 'true' : 'false' ?>"
       data-clientes-aba="inativos">
        Clientes Inativos
        <span class="aba__contador"><?= (int) ($totalInativos ?? 0) ?></span>
    </a>
</div>

<!-- ── Tabela ── -->
<section class="card">
    <div id="clientes-conteudo" data-aba-conteudo aria-busy="false">
        <?php require __DIR__ . '/_tabelaClientes.php'; ?>
    </div>
</section>

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

        // Escapa na saída porque as iniciais também vão parar no HTML: um nome
        // começando com "<" devolveria "<" cru dentro da bolinha do avatar.
        function iniciais(nome) {
            const partes = String(nome || '').trim().split(/\s+/);
            return escaparHtml(((partes[0]?.[0] || '') + (partes[1]?.[0] || '')).toUpperCase());
        }

        function formatarData(d) {
            if (!d) return 'Sem registro';
            return new Date(d).toLocaleDateString('pt-BR');
        }

        // --- Gera o HTML de uma linha na lista, adaptando o "sub" e o badge
        //     conforme o tipo de painel (vip = recorrência, novo = data de cadastro) ---
        function criarLinhaCliente(c, tipo) {
            const veiculo = escaparHtml(c.veiculo_principal) || 'Sem veículo';

            const sub = tipo === 'vip' ?
                `${veiculo} · ${formatarData(c.ultima_visita)}` :
                `${veiculo} · desde ${formatarData(c.criado_em)}`;

            const badge = tipo === 'vip' ?
                `${parseInt(c.total_visitas, 10) || 0}x` :
                'Novo';

            return `
                <div class="vip-row" data-id="${parseInt(c.id_cliente, 10)}">
                    <div class="avatar" style="background:${PRIMARY}">${iniciais(c.nome)}</div>
                    <div class="vip-row__info">
                        <div class="vip-row__name">${escaparHtml(c.nome)}</div>
                        <div class="vip-row__sub">${sub}</div>
                    </div>
                    <div class="vip-row__badge">${badge}</div>
                </div>
            `;
        }

        // --- Gera o HTML completo da tela de perfil, adaptando só a "tag" do topo ---
        function renderPerfil(c, tipo) {
            const tag = tipo === 'vip' ?
                `★ VIP · ${parseInt(c.total_visitas, 10) || 0} visitas` :
                `✦ Novo cliente · desde ${formatarData(c.criado_em)}`;

            const veiculosHtml = (c.veiculos && c.veiculos.length) ?
                c.veiculos.map(v => `
                    <div class="vip-vehicle">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="${PRIMARY}" stroke-width="2">
                            <path d="M5 17h14M5 17a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm14 0a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM3 17V9l2-5h14l2 5v8" />
                        </svg>
                        ${escaparHtml(v.marca)} ${escaparHtml(v.modelo)}${v.ano ? ' · ' + escaparHtml(v.ano) : ''}
                    </div>
                `).join('') :
                '<div class="vip-empty">Nenhum veículo cadastrado.</div>';

            return `
                <div class="vip-perfil-head">
                    <div class="avatar avatar--lg" style="background:${PRIMARY}">${iniciais(c.nome)}</div>
                    <div>
                        <div class="vip-perfil-name">${escaparHtml(c.nome)}</div>
                        <div class="vip-perfil-tag">${tag}</div>
                    </div>
                </div>

                <div class="vip-info-grid">
                    <div class="vip-info-item">
                        <div class="vip-info-label">Telefone</div>
                        <div class="vip-info-value">${escaparHtml(c.telefone) || 'Não informado'}</div>
                    </div>
                    <div class="vip-info-item">
                        <div class="vip-info-label">E-mail</div>
                        <div class="vip-info-value">${escaparHtml(c.email) || 'Não informado'}</div>
                    </div>
                    <div class="vip-info-item">
                        <div class="vip-info-label">Documento</div>
                        <div class="vip-info-value">${escaparHtml(c.cpf_cnpj) || 'Não informado'}</div>
                    </div>
                    <div class="vip-info-item">
                        <div class="vip-info-label">Cliente desde</div>
                        <div class="vip-info-value">${formatarData(c.criado_em)}</div>
                    </div>
                    <div class="vip-info-item vip-info-item--full">
                        <div class="vip-info-label">Endereço</div>
                        <div class="vip-info-value">${[c.endereco, c.bairro, c.cidade, c.uf].filter(Boolean).map(escaparHtml).join(', ') || 'Não informado'}</div>
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

<script>
    /**
     * Troca entre "Clientes Ativos" e "Clientes Inativos" sem sair da página.
     *
     * Mesmo mecanismo da tela de Usuários: busca/paginação continuam
     * server-side (é o banco que filtra e pagina), então a troca não pode ser
     * só esconder linhas no DOM — o navegador só tem a página atual. A aba
     * pede o miolo da tabela à MESMA rota (./listClientes com ?fragmento=1) e
     * substitui só o #clientes-conteudo.
     *
     * Tudo é progressivo: abas, paginação e "limpar" são <a> de verdade e o
     * filtro é um <form> GET normal — sem JS, a tela funciona recarregando.
     */
    (function () {
        const conteudo = document.getElementById('clientes-conteudo');
        const abas     = Array.from(document.querySelectorAll('[data-clientes-aba]'));

        if (!conteudo || !abas.length) return;

        let requisicaoAtual = 0;

        function abaDaUrl(url) {
            return new URL(url, window.location.href).searchParams.get('aba') === 'inativos'
                ? 'inativos'
                : 'ativos';
        }

        function marcarAba(aba) {
            abas.forEach(function (a) {
                const ehEsta = a.dataset.clientesAba === aba;
                a.classList.toggle('aba--ativa', ehEsta);
                a.setAttribute('aria-selected', ehEsta ? 'true' : 'false');
            });
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
                // calado, e sem isto a tela de login acabaria injetada dentro
                // da tabela. Recarrega de verdade nesses casos.
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

                if (comHistorico) {
                    // URL sem "fragmento": recarregar a página na mão tem que
                    // devolver a tela inteira na mesma aba.
                    history.pushState({ clientes: true }, '', url);
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

        // ── Paginação e "limpar busca" (vêm dentro do fragmento trocado,
        //    por isso delegação em vez de listener por elemento) ──
        conteudo.addEventListener('click', function (e) {
            const link = e.target.closest('[data-clientes-link]');
            if (!link) return;
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;

            e.preventDefault();
            carregar(link.getAttribute('href'), true);
        });

        // ── Formulário de busca ──
        conteudo.addEventListener('submit', function (e) {
            const form = e.target.closest('[data-clientes-filtro]');
            if (!form) return;

            e.preventDefault();

            const dados = new URLSearchParams(new FormData(form));
            // Busca nova recomeça da primeira página.
            dados.delete('pagina');

            carregar('./listClientes?' + dados.toString(), true);
        });

        // ── Voltar/avançar do navegador ──
        window.addEventListener('popstate', function () {
            carregar(window.location.href, false);
        });
    })();
</script>
