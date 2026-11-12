<?php
// Variáveis esperadas do controller:
// $MovimentacaoEstoque  array  — lista de movimentações
// $totalmovimentacaoestoque int — total de registros
// $paginaAtual          int    — página atual
// $totalPaginas         int    — total de páginas

$flashOk = ['estornada' => 'Movimentação estornada. O saldo foi corrigido e o histórico foi mantido.',
            'jaestornada' => 'Esta movimentação já tinha sido estornada.'][$_GET['ok'] ?? ''] ?? null;
$flashErro = ($_GET['erro'] ?? '') === 'estorno' ? ($_GET['detalhe'] ?? 'Não foi possível estornar.') : null;
?>

<?php if ($flashOk !== null): ?>
    <?php $alertaTipo = 'sucesso'; $alertaMensagem = $flashOk; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>
<?php if ($flashErro !== null): ?>
    <?php $alertaTipo = 'erro'; $alertaMensagem = $flashErro; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

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
                    <th>Origem</th>
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

                            <!-- Origem: de onde veio a movimentação. Quando nasce de uma
                                 OS, o número dela vira link — é o rastro que liga a peça
                                 que saiu ao serviço que a consumiu. -->
                            <td>
                                <?php $origem = $mov['origem'] ?? 'MANUAL'; ?>
                                <?php if ($origem === 'OS' && !empty($mov['ordem_servico_id'])): ?>
                                    <a href="./viewOrdemServico?id=<?= (int) $mov['ordem_servico_id'] ?>"
                                       style="font-size:12px;font-weight:600;">
                                        OS <?= htmlspecialchars($mov['os_numero'] ?? ('#' . $mov['ordem_servico_id'])) ?>
                                    </a>
                                <?php elseif ($origem === 'CADASTRO'): ?>
                                    <span class="text-muted" style="font-size:12px;">Cadastro do produto</span>
                                <?php elseif ($origem === 'COMPRA' && !empty($mov['pedido_id'])): ?>
                                    <?php if ($_SESSION['user_tipo'] === 'ADMIN'): ?>
                                        <a href="./viewPedidoCompra?id=<?= (int) $mov['pedido_id'] ?>" style="font-size:12px;font-weight:600;">Recebimento · <?= htmlspecialchars($mov['pedido_numero']) ?></a>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:12px;">Recebimento de compra</span>
                                    <?php endif; ?>
                                <?php elseif ($origem === 'COMPRA'): ?>
                                    <span class="text-muted" style="font-size:12px;">Compra no balcão</span>
                                <?php elseif ($origem === 'ESTORNO'): ?>
                                    <span class="text-muted" style="font-size:12px;">Estorno</span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:12px;">Lançamento manual</span>
                                <?php endif; ?>

                                <?php if (!empty($mov['movimentacao_caixa_id'])): ?>
                                    <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px;">
                                        <i class="bi bi-cash-coin"></i> lançada no caixa
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Quantidade -->
<td style="font-weight:500;">
    <?php
    // Mostra até 3 casas (coluna é DECIMAL(10,3), produtos por litro/kg
    // usam fração) sem zeros à direita — "2.5", não "2.500"; "5", não "5.000".
    // Ajuste mostra o sinal do efeito real no saldo (delta); entrada e saída mostram só a
    // quantidade — o tipo já diz o sentido.
    $quantidade = isset($mov['quantidade']) ? abs((float) $mov['quantidade']) : 0;
    $qtdFormatada = rtrim(rtrim(number_format($quantidade, 3, ',', '.'), '0'), ',');
    $deltaMov = isset($mov['delta']) ? (float) $mov['delta'] : null;
    $prefixo = '';
    if ($tipoMov === 'AJUSTE') {
        $prefixo = ($deltaMov ?? (float) $mov['quantidade']) >= 0 ? '+' : '−';
    }
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

                            <!-- Ações. O histórico não se edita nem se apaga: o erro é corrigido
                                 por um ESTORNO (nova movimentação oposta, com motivo), só do administrador. -->
                            <td>
                                <div class="row-actions">
                                    <?php
                                    $ehEstorno = !empty($mov['estorno_de_id']);
                                    $podeEstornar = ($_SESSION['user_tipo'] ?? '') === 'ADMIN'
                                        && !$ehEstorno
                                        && empty($mov['estornada'])
                                        && empty($mov['evento'])
                                        && in_array($mov['origem'] ?? '', ['MANUAL', 'COMPRA'], true);
                                    ?>
                                    <?php if ($ehEstorno): ?>
                                        <span class="badge badge--neutral" title="<?= htmlspecialchars($mov['justificativa'] ?? '') ?>">Estorno de #<?= (int) $mov['estorno_de_id'] ?></span>
                                    <?php elseif (!empty($mov['estornada'])): ?>
                                        <span class="badge badge--neutral">Estornada</span>
                                    <?php elseif ($podeEstornar): ?>
                                        <button type="button" class="row-action-btn danger" title="Estornar esta movimentação"
                                            data-id="<?= (int) $mov['id_movimentacao'] ?>"
                                            data-produto="<?= htmlspecialchars($mov['produto_nome'] ?? '') ?>"
                                            onclick="abrirEstorno(this)">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted fs-12">—</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($mov['justificativa']) && !$ehEstorno): ?>
                                    <div class="text-muted" style="font-size:11px;margin-top:2px;max-width:200px;"><?= htmlspecialchars($mov['justificativa']) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">
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

<!-- ══ ESTORNO ═════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-estorno" style="display:none;">
    <div class="modal" style="max-width:460px;">
        <div class="modal__header">
            <div>
                <div class="modal__title">Estornar movimentação</div>
                <div class="modal__subtitle" id="estorno-ref"></div>
            </div>
            <button type="button" class="modal__close" onclick="document.getElementById('modal-estorno').style.display='none'" aria-label="Fechar">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
            </button>
        </div>
        <form action="./estornarMovimentacaoEstoque" method="POST">
            <div class="modal__body" style="padding:20px 24px;">
                <input type="hidden" name="id" id="estorno-id">
                <p style="font-size:12px;color:var(--color-text-muted);margin:0 0 10px;">
                    Lança a movimentação oposta e mantém a original no histórico. O lançamento de caixa de uma compra não é alterado.
                </p>
                <div class="form-field">
                    <label>Motivo do estorno <span style="color:var(--color-danger)">*</span></label>
                    <textarea name="justificativa" rows="3" maxlength="255" required
                              placeholder="Ex.: lançada no produto errado; compra devolvida ao fornecedor."></textarea>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--ghost btn--sm" onclick="document.getElementById('modal-estorno').style.display='none'">Voltar</button>
                <button type="submit" class="btn btn--primary btn--sm">Estornar</button>
            </div>
        </form>
    </div>
</div>
<script>
    function abrirEstorno(botao) {
        document.getElementById('estorno-id').value = botao.dataset.id;
        document.getElementById('estorno-ref').textContent = '#' + botao.dataset.id + ' · ' + botao.dataset.produto;
        document.getElementById('modal-estorno').style.display = 'flex';
    }
</script>
