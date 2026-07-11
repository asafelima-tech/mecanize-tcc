<?php
// Variáveis esperadas do controller:
// $MovimentacaoEstoque  array  — lista de movimentações
// $totalmovimentacaoestoque int — total de registros
// $paginaAtual          int    — página atual
// $totalPaginas         int    — total de páginas
?>

<!-- ── Page Header ── -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Movimentações</h1>
        <p class="page-header__subtitle">Gerencie as movimentações de estoque do sistema.</p>
    </div>
    <div class="page-header__actions">
        <a href="./saveMovimentacaoEstoque" class="btn btn--primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Nova Movimentação
        </a>
    </div>
</div>

<!-- ── Tabela ── -->
<section class="card">

    <!-- Toolbar / Filtros -->
    <form method="GET" action="./listMovimentacaoEstoque" class="table-toolbar">

        <div class="search-wrap">
            <input
                type="text"
                name="busca"
                value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>"
                placeholder="Buscar por produto..." />
        </div>

        <select name="tipo" class="toolbar-select" onchange="this.form.submit()">
            <option value="">Tipo: Todos</option>
            <option value="ENTRADA" <?= (($_GET['tipo'] ?? '') === 'ENTRADA') ? 'selected' : '' ?>>Entrada</option>
            <option value="SAIDA"   <?= (($_GET['tipo'] ?? '') === 'SAIDA')   ? 'selected' : '' ?>>Saída</option>
            <option value="AJUSTE"  <?= (($_GET['tipo'] ?? '') === 'AJUSTE')  ? 'selected' : '' ?>>Ajuste</option>
        </select>

        <button type="submit" class="btn btn--ghost">Filtrar</button>

        <span style="margin-left:auto;font-size:12px;color:var(--color-text-muted);">
            <?= $totalmovimentacaoestoque ?? 0 ?> resultado(s)
        </span>

    </form>

    <!-- Tabela -->
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Tipo</th>
                    <th>Quantidade</th>
                    <?php if (($_SESSION['user_tipo'] ?? '') === 'ADMIN'): ?>
                    <th>Custo Unitário</th>
                    <?php endif; ?>
                    <th>Criado em</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($MovimentacaoEstoque)): ?>
                    <?php foreach ($MovimentacaoEstoque as $mov): ?>
                        <tr>
                            <!-- Produto -->
                            <td>
                                <?php
                                // AJUSTE = correção manual de estoque feita direto na tela de
                                // editar produto (auditoria automática, ver Produtos::update()) —
                                // não é Entrada nem Saída, tem cor/ícone própria.
                                $tipoMov = $mov['tipo'] ?? 'SAIDA';
                                $corIcone = match ($tipoMov) {
                                    'ENTRADA' => ['bg' => 'var(--color-success-soft)', 'fg' => 'var(--color-success)'],
                                    'AJUSTE'  => ['bg' => 'var(--color-primary-soft)', 'fg' => 'var(--color-primary)'],
                                    default   => ['bg' => 'var(--color-warning-soft)', 'fg' => 'var(--color-warning)'],
                                };
                                $tituloIcone = match ($tipoMov) {
                                    'ENTRADA' => 'Entrada de estoque',
                                    'AJUSTE'  => 'Ajuste manual de estoque',
                                    default   => 'Saída de estoque',
                                };
                                ?>
                                <div class="user-cell">
                                    <div style="width:34px;height:34px;flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;border-radius:9px;background:<?= $corIcone['bg'] ?>;color:<?= $corIcone['fg'] ?>;"
                                        title="<?= $tituloIcone ?>">
                                        <?php if ($tipoMov === 'ENTRADA'): ?>
                                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 3v12" /><path d="m7 11 5 5 5-5" /><path d="M5 21h14" />
                                            </svg>
                                        <?php elseif ($tipoMov === 'AJUSTE'): ?>
                                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 20h9" /><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z" />
                                            </svg>
                                        <?php else: ?>
                                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 21V9" /><path d="m7 13 5-5 5 5" /><path d="M5 3h14" />
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="user-cell__name"><?= htmlspecialchars($mov['produto_nome'] ?? '-') ?></div>
                                        <div class="user-cell__sub">#<?= htmlspecialchars($mov['id_movimentacao'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>

                            <!-- Tipo -->
                            <td>
                                <?php if ($tipoMov === 'ENTRADA'): ?>
                                    <span class="badge badge--success">
                                        <span class="badge__dot"></span>Entrada
                                    </span>
                                <?php elseif ($tipoMov === 'AJUSTE'): ?>
                                    <span class="badge badge--info">
                                        <span class="badge__dot"></span>Ajuste
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge--warning">
                                        <span class="badge__dot"></span>Saída
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Quantidade -->
<td style="font-weight:500;">
    <?php
    // Mostra até 3 casas (coluna é DECIMAL(10,3), produtos por litro/kg
    // usam fração) sem zeros à direita — "2.5", não "2.500"; "5", não "5.000".
    $quantidade = isset($mov['quantidade']) ? (float) $mov['quantidade'] : 0;
    $qtdFormatada = rtrim(rtrim(number_format($quantidade, 3, ',', '.'), '0'), ',');
    $prefixo = ($tipoMov === 'AJUSTE' && $quantidade > 0) ? '+' : '';
    echo $prefixo . $qtdFormatada;
    ?>
</td>

                            <!-- Custo Unitário -->
                            <?php if (($_SESSION['user_tipo'] ?? '') === 'ADMIN'): ?>
                            <td style="font-weight:500;">
                                <?php
                                $custo = $mov['custo_unitario'] ?? null;
                                echo $custo !== null
                                    ? 'R$ ' . number_format((float)$custo, 2, ',', '.')
                                    : '-';
                                ?>
                            </td>
                            <?php endif; ?>

                            <!-- Criado em -->
                            <td class="text-muted">
                                <?= !empty($mov['criado_em'])
                                    ? date('d/m/Y H:i', strtotime($mov['criado_em']))
                                    : '--' ?>
                            </td>

                            <!-- Ações -->
                            <td>
                                <div class="row-actions">
                                    <a href="./editMovimentacaoEstoque&id=<?= $mov['id_movimentacao'] ?>"
                                        class="row-action-btn"
                                        title="Editar">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                        </svg>
                                    </a>

                                    <?php if (($_SESSION['user_tipo'] ?? '') === 'ADMIN'): ?>
                                    <form method="post" action="./deleteMovimentacaoEstoque" style="display:inline;"
                                        onsubmit="return confirm('Deseja excluir esta movimentação? O estoque do produto será revertido.')">
                                        <input type="hidden" name="id" value="<?= $mov['id_movimentacao'] ?>">
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
                        <td colspan="6" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 10px;display:block;opacity:.4;">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                            </svg>
                            Nenhuma movimentação encontrada.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginação -->
    <div class="pagination">
        <span>
            Mostrando <?= count($MovimentacaoEstoque ?? []) ?> de <?= $totalmovimentacaoestoque ?? 0 ?> resultados
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