<?php
// Variáveis esperadas:
// $produtos        array  — lista de produtos
// $categorias      array  — lista de categorias [id_categoria, nome]
// $totalEstoque    int    — total de unidades em estoque
// $estoqueBaixo    int    — quantidade de produtos abaixo do mínimo
// $valorInventario float  — valor total calculado pelo preço de custo
// $error           string — mensagem de erro opcional
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Gestão de Produtos</h1>
        <p class="page-header__subtitle">Gerencie seus produtos e acompanhe os níveis de inventário.</p>
    </div>

    <div class="page-header__actions">
        <a href="./listCategoria" class="btn btn--ghost">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="8" y1="6" x2="21" y2="6" />
                <line x1="8" y1="12" x2="21" y2="12" />
                <line x1="8" y1="18" x2="21" y2="18" />
                <line x1="3" y1="6" x2="3.01" y2="6" />
                <line x1="3" y1="12" x2="3.01" y2="12" />
                <line x1="3" y1="18" x2="3.01" y2="18" />
            </svg>
            Categorias
        </a>
        <a href="./saveProduto" class="btn btn--primary">
            + Novo Produto
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <?php $alertaTipo = 'erro'; $alertaMensagem = $error;
                  require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<!-- ── Stat Cards ── -->
<section class="stat-grid">

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Total em Estoque</div>
            <div class="stat-card__icon b1">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                    <line x1="12" y1="22.08" x2="12" y2="12" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= number_format($totalEstoque ?? 0, 0, ',', '.') ?></div>
        <div class="stat-card__delta">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
                <polyline points="17 6 23 6 23 12" />
            </svg>
            unidades em estoque
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Estoque Baixo</div>
            <div class="stat-card__icon b2">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                    <line x1="12" y1="9" x2="12" y2="13" />
                    <line x1="12" y1="17" x2="12.01" y2="17" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $estoqueBaixo ?? 0 ?></div>
        <div class="stat-card__delta negative">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="23 18 13.5 8.5 8.5 13.5 1 6" />
                <polyline points="17 18 23 18 23 12" />
            </svg>
            abaixo do mínimo
        </div>
    </div>

    <?php if (isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'ADMIN'): ?>

        <div class="stat-card">
            <div class="stat-card__head">
                <div class="stat-card__label">Valor em Inventário</div>
                <div class="stat-card__icon b3">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23" />
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                    </svg>
                </div>
            </div>
            <div class="stat-card__value">R$ <?= number_format($valorInventario ?? 0, 2, ',', '.') ?></div>
            <div class="stat-card__delta">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
                    <polyline points="17 6 23 6 23 12" />
                </svg>
                baseado no preço de custo
            </div>
        </div>

    <?php endif; ?>

</section>

<!-- ── Tabela ── -->
<section class="card">

    <!-- Toolbar / Filtros -->
    <form method="GET" action="./listProduto" class="table-toolbar">

        <div class="search-wrap">
            <input
                type="text"
                name="busca"
                value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>"
                placeholder="Buscar por nome do produto, SKU ou código..." />
        </div>

        <div class="search-wrap" style="flex:0 1 260px;">
            <input
                type="text"
                name="aplicacao"
                value="<?= htmlspecialchars($_GET['aplicacao'] ?? '') ?>"
                placeholder="Aplicação (ex.: Gol, flex, passeio)..." />
        </div>

        <select name="categoria" class="toolbar-select" onchange="this.form.submit()">
            <option value="">Categoria: Todas</option>
            <?php foreach (($categorias ?? []) as $cat): ?>
                <option
                    value="<?= htmlspecialchars($cat['id_categoria']) ?>"
                    <?= (($_GET['categoria'] ?? '') == $cat['id_categoria']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="status" class="toolbar-select" onchange="this.form.submit()">
            <option value="">Status: Todos</option>
            <option value="ativo" <?= (($_GET['status'] ?? '') === 'ativo')          ? 'selected' : '' ?>>Ativo</option>
            <option value="inativo" <?= (($_GET['status'] ?? '') === 'inativo')        ? 'selected' : '' ?>>Inativo</option>
            <option value="em_estoque" <?= (($_GET['status'] ?? '') === 'em_estoque')     ? 'selected' : '' ?>>Em Estoque</option>
            <option value="baixo_estoque" <?= (($_GET['status'] ?? '') === 'baixo_estoque')  ? 'selected' : '' ?>>Baixo Estoque</option>
            <option value="esgotado" <?= (($_GET['status'] ?? '') === 'esgotado')       ? 'selected' : '' ?>>Esgotado</option>
        </select>

        <button type="submit" class="btn btn--ghost">Filtrar</button>

        <span style="margin-left:auto;font-size:12px;color:var(--color-text-muted);">
            <?= count($produtos ?? []) ?> resultado(s)
        </span>

    </form>

    <!-- Tabela de produtos -->
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Código / SKU</th>
                    <th>Nome do Produto</th>
                    <th>Categoria</th>
                    <th>Quantidade</th>
                    <th>Preço de Venda</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($produtos)): ?>
                    <?php foreach ($produtos as $produto): ?>
                        <?php
                        $estoqueAtual  = (float) ($produto['estoque_atual']  ?? 0);
                        $estoqueMinimo = (float) ($produto['estoque_minimo'] ?? 0);
                        $ativo         = (int)   ($produto['ativo']          ?? 1);

                        if ($ativo !== 1) {
                            $statusTexto = 'Inativo';
                            $badgeClass  = 'badge--neutral';
                        } elseif ($estoqueAtual <= 0) {
                            $statusTexto = 'Esgotado';
                            $badgeClass  = 'badge--danger';
                        } elseif ($estoqueAtual <= $estoqueMinimo) {
                            $statusTexto = 'Estoque Baixo';
                            $badgeClass  = 'badge--warning';
                        } else {
                            $statusTexto = 'Em Estoque';
                            $badgeClass  = 'badge--success';
                        }
                        ?>
                        <tr>
                            <td class="id-col"><?= htmlspecialchars($produto['codigo_interno'] ?? '-') ?></td>

                            <td style="font-weight:500;"><?= htmlspecialchars($produto['nome'] ?? '-') ?></td>

                            <td style="color:var(--color-text-muted);"><?= htmlspecialchars($produto['categoria'] ?? 'Sem categoria') ?></td>

                            <td><?= number_format($estoqueAtual, 0, ',', '.') ?></td>

                            <td>R$ <?= number_format((float) ($produto['preco_venda'] ?? 0), 2, ',', '.') ?></td>

                            <td>
                                <span class="badge <?= $badgeClass ?>">
                                    <span class="badge__dot"></span>
                                    <?= $statusTexto ?>
                                </span>
                            </td>

                            <td>
                                <div class="row-actions">
                                    <!-- Visualizar -->
                                    <a href="./viewProduto?id=<?= $produto['id_produto'] ?>" class="row-action-btn primary" title="Visualizar">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </a>

                                    <!-- Editar -->
                                    <a href="./editProduto?id=<?= $produto['id_produto'] ?>" class="row-action-btn" title="Editar">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                        </svg>
                                    </a>

                                    <!-- Excluir -->
                                    <?php if (($_SESSION['user_tipo'] ?? '') === 'ADMIN'): ?>
                                    <form
                                        method="POST"
                                        action="./deleteProduto"
                                        style="display:inline;"
                                        onsubmit="return confirm('Deseja realmente arquivar este produto?')">
                                        <input type="hidden" name="id_produto" value="<?= htmlspecialchars($produto['id_produto']) ?>">
                                        <button type="submit" class="row-action-btn danger" title="Excluir">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                    <tr>
                        <td colspan="7" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 10px;display:block;opacity:.4;">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                            </svg>
                            Nenhum produto encontrado.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Rodapé / Paginação -->
    <div class="pagination">
        <span>
            <?= !empty($produtos)
                ? 'Mostrando ' . count($produtos) . ' produto(s)'
                : 'Nenhum resultado' ?>
        </span>

        <?php if (!empty($totalPaginas) && $totalPaginas > 1): ?>
            <div class="pagination__pages">
                <button
                    class="pagination__btn"
                    <?= ($paginaAtual <= 1) ? 'disabled' : '' ?>
                    onclick="window.location='?<?= http_build_query(array_merge($_GET, ['pagina' => ($paginaAtual - 1)])) ?>'">‹</button>

                <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                    <button
                        class="pagination__btn <?= ($p == $paginaAtual) ? 'is-active' : '' ?>"
                        onclick="window.location='?<?= http_build_query(array_merge($_GET, ['pagina' => $p])) ?>'"><?= $p ?></button>
                <?php endfor; ?>

                <button
                    class="pagination__btn"
                    <?= ($paginaAtual >= $totalPaginas) ? 'disabled' : '' ?>
                    onclick="window.location='?<?= http_build_query(array_merge($_GET, ['pagina' => ($paginaAtual + 1)])) ?>'">›</button>
            </div>
        <?php endif; ?>
    </div>

</section>