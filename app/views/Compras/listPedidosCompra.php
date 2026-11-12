<?php
/**
 * Pedidos de compra. Espera: $pedidos, $contagem, $aba, $busca, $fornecedores, $sucesso.
 */
require_once __DIR__ . '/../../models/PedidoCompra.php';

$e = fn($v) => htmlspecialchars((string) $v);
$qtd = fn($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',') ?: '0';
$brl = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$n = fn(string $s) => (int) ($contagem[$s] ?? 0);

$abas = ['abertos' => ['Em andamento', $n('RASCUNHO') + $n('REALIZADO') + $n('PARCIALMENTE_RECEBIDO')]];
foreach (PedidoCompra::STATUS_LABEL as $chave => $rotulo) {
    $abas[$chave] = [$rotulo, $n($chave)];
}
$abas['todos'] = ['Todos', array_sum($contagem)];

$erroTopo = $_GET['erro'] ?? null;
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Pedidos de compra</h1>
        <p class="page-header__subtitle">O abastecimento da oficina. O estoque só sobe no recebimento.</p>
    </div>
    <div class="page-header__actions">
        <a href="./painelCompras" class="btn btn--ghost">Necessidades</a>
        <a href="./listFornecedores" class="btn btn--ghost">Fornecedores</a>
    </div>
</div>

<?php if ($erroTopo): ?>
    <?php $alertaTipo = 'erro'; $alertaMensagem = $erroTopo; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<section class="card" style="margin-bottom:16px;">
    <div class="card__body" style="padding:14px 20px;">
        <form action="./criarPedidoCompra" method="POST" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
            <?= csrf_field() ?>
            <div class="form-field" style="min-width:260px;margin:0;">
                <label for="fornecedor_id">Novo pedido para</label>
                <select name="fornecedor_id" id="fornecedor_id" data-no-ts required>
                    <option value="">Escolha o fornecedor...</option>
                    <?php foreach ($fornecedores as $f): if (!(int) $f['ativo']) continue; ?>
                        <option value="<?= (int) $f['id_fornecedor'] ?>"><?= $e($f['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn--primary">Criar rascunho</button>
            <?php if (empty($fornecedores)): ?><span class="text-muted fs-12">Nenhum fornecedor ainda: <a href="./saveFornecedor">cadastrar</a>.</span><?php endif; ?>
        </form>
    </div>
</section>

<section class="card">
    <div class="tabs" style="overflow-x:auto;">
        <?php foreach ($abas as $chave => [$rotulo, $q]): ?>
            <a class="tabs__item <?= $aba === $chave ? 'is-active' : '' ?>" href="./listPedidosCompra?aba=<?= urlencode($chave) ?>" style="text-decoration:none;white-space:nowrap;">
                <?= $e($rotulo) ?><?php if ($q > 0): ?> <span class="text-muted fs-12">(<?= (int) $q ?>)</span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <form method="GET" action="./listPedidosCompra" class="table-toolbar">
        <input type="hidden" name="aba" value="<?= $e($aba) ?>">
        <div class="search-wrap"><input type="text" name="busca" value="<?= $e($busca) ?>" placeholder="Número, fornecedor ou referência do pedido..."></div>
        <span class="text-muted fs-12"><?= count($pedidos) ?> resultado(s)</span>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Nº</th><th>Fornecedor</th><th>Referência</th><th>Itens</th><th style="text-align:right;">Total</th><th>Recebido</th><th>Criado em</th><th>Situação</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($pedidos)): ?>
                <tr><td colspan="9" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">Nenhum pedido neste filtro.</td></tr>
            <?php else: foreach ($pedidos as $p): ?>
                <tr>
                    <td class="id-col"><?= $e($p['numero']) ?></td>
                    <td><?= $e($p['fornecedor_nome']) ?></td>
                    <td class="text-muted"><?= $e($p['referencia_externa'] ?? '—') ?></td>
                    <td><?= (int) $p['qtd_itens'] ?></td>
                    <td style="text-align:right;"><?= $brl($p['total']) ?></td>
                    <td class="text-muted"><?= $qtd($p['qtd_recebida']) ?> / <?= $qtd($p['qtd_pedida']) ?></td>
                    <td class="text-muted"><?= date('d/m/Y', strtotime($p['criado_em'])) ?></td>
                    <td><span class="badge <?= PedidoCompra::badge($p['status']) ?>"><span class="badge__dot"></span><?= $e(PedidoCompra::rotulo($p['status'])) ?></span></td>
                    <td><a class="btn btn--ghost btn--sm" href="./viewPedidoCompra?id=<?= (int) $p['id_pedido'] ?>">Abrir</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
