<div class="page-header">
    <div>
        <h1 class="page-header__title">Categorias de Produtos</h1>
        <p class="page-header__subtitle">Gerencie as categorias usadas no cadastro de produtos.</p>
    </div>
    <div class="page-header__actions">
        <a href="./listProduto" class="btn btn--ghost">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5M12 5l-7 7 7 7" />
            </svg>
            Voltar
        </a>
        <a href="./saveCategoria" class="btn btn--primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Nova Categoria
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div style="background:var(--color-danger-soft);color:#991B1B;border:1px solid #FECACA;border-radius:var(--radius-md);padding:12px 16px;margin-bottom:20px;font-size:13px;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<section class="card">

    <div class="table-toolbar">
        <div class="search-wrap">
            <input type="text" id="busca-categorias"
                placeholder="Buscar por nome..."
                oninput="filtrarCategorias()">
        </div>
        <span class="text-muted fs-12" id="contador-categorias"><?= count($categorias ?? []) ?> resultado(s)</span>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="tbody-categorias">
                <?php if (!empty($categorias)): ?>
                    <?php foreach ($categorias as $categoria): ?>
                        <tr data-nome="<?= strtolower(htmlspecialchars($categoria['nome'])) ?>">
                            <td>
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <div style="
                                        width:36px;height:36px;
                                        background:var(--color-primary-soft);
                                        color:var(--color-primary);
                                        border-radius:var(--radius-md);
                                        display:flex;align-items:center;justify-content:center;
                                        flex-shrink:0;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" />
                                            <line x1="7" y1="7" x2="7.01" y2="7" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div style="font-weight:600;font-size:13px;">
                                            <?= htmlspecialchars($categoria['nome']) ?>
                                        </div>
                                        <div class="text-muted fs-12">ID #<?= $categoria['id_categoria'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="./editCategoria&id=<?= $categoria['id_categoria'] ?>"
                                        class="row-action-btn" title="Editar">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                        </svg>
                                    </a>
                                    <?php if (($_SESSION['user_tipo'] ?? '') === 'ADMIN'): ?>
                                    <form method="post" action="./deleteCategoria" style="display:inline;"
                                        onsubmit="return confirm('Deseja realmente excluir esta categoria?')">
                                        <input type="hidden" name="id" value="<?= $categoria['id_categoria'] ?>">
                                        <button type="submit" class="row-action-btn danger" title="Excluir">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6" />
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                            </svg>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="tr-vazio">
                        <td colspan="2" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">
                            Nenhuma categoria encontrada.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINAÇÃO -->
    <div class="table-footer" id="paginacao-wrap">
        <span class="text-muted fs-12" id="paginacao-info"></span>
        <div class="pagination" id="paginacao-btns"></div>
    </div>

</section>

<script>
    const POR_PAGINA = 8;
    let paginaAtual = 1;
    let linhasFiltradas = [];

    function todasAsLinhas() {
        return Array.from(document.querySelectorAll('#tbody-categorias tr[data-nome]'));
    }

    function filtrarCategorias() {
        const busca = document.getElementById('busca-categorias').value.toLowerCase().trim();
        const todas = todasAsLinhas();

        linhasFiltradas = todas.filter(tr => !busca || tr.dataset.nome.includes(busca));

        todas.forEach(tr => tr.style.display = 'none');

        paginaAtual = 1;
        renderPagina();
    }

    function renderPagina() {
        const total = linhasFiltradas.length;
        const totalPaginas = Math.max(1, Math.ceil(total / POR_PAGINA));
        paginaAtual = Math.min(paginaAtual, totalPaginas);

        const inicio = (paginaAtual - 1) * POR_PAGINA;
        const fim = inicio + POR_PAGINA;

        todasAsLinhas().forEach(tr => tr.style.display = 'none');
        linhasFiltradas.forEach((tr, i) => {
            tr.style.display = (i >= inicio && i < fim) ? '' : 'none';
        });

        const vazio = document.getElementById('tr-vazio');
        if (vazio) vazio.style.display = total === 0 ? '' : 'none';

        document.getElementById('contador-categorias').textContent = total + ' resultado(s)';

        const info = document.getElementById('paginacao-info');
        const wrap = document.getElementById('paginacao-wrap');

        if (total === 0) {
            wrap.style.display = 'none';
            return;
        }

        wrap.style.display = 'flex';
        const de = inicio + 1;
        const ate = Math.min(fim, total);
        info.textContent = `Exibindo ${de}–${ate} de ${total}`;

        renderBotoes(totalPaginas);
    }

    function renderBotoes(totalPaginas) {
        const container = document.getElementById('paginacao-btns');
        container.innerHTML = '';

        const btn = (label, pagina, desabilitado, ativo) => {
            const b = document.createElement('button');
            b.innerHTML = label;
            b.disabled = desabilitado;
            if (ativo) b.classList.add('active');
            if (!desabilitado && !ativo) b.onclick = () => irPara(pagina);
            return b;
        };

        container.appendChild(btn('&#8592;', paginaAtual - 1, paginaAtual === 1, false));

        const delta = 1;
        const paginas = new Set();
        paginas.add(1);
        paginas.add(totalPaginas);
        for (let i = paginaAtual - delta; i <= paginaAtual + delta; i++) {
            if (i >= 1 && i <= totalPaginas) paginas.add(i);
        }

        let anterior = 0;
        [...paginas].sort((a, b) => a - b).forEach(p => {
            if (anterior && p - anterior > 1) {
                const sep = document.createElement('button');
                sep.textContent = '…';
                sep.disabled = true;
                container.appendChild(sep);
            }
            container.appendChild(btn(p, p, false, p === paginaAtual));
            anterior = p;
        });

        container.appendChild(btn('&#8594;', paginaAtual + 1, paginaAtual === totalPaginas, false));
    }

    function irPara(pagina) {
        paginaAtual = pagina;
        renderPagina();
    }

    // init
    linhasFiltradas = todasAsLinhas();
    renderPagina();
</script>