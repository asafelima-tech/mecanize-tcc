<?php
/**
 * Fornecedores. Espera: $fornecedores, $busca, $sucesso.
 */
$e = fn($v) => htmlspecialchars((string) $v);
$mensagens = ['criado' => 'Fornecedor cadastrado.', 'salvo' => 'Fornecedor atualizado.', 'desativado' => 'Fornecedor desativado. O histórico de compras continua.', 'reativado' => 'Fornecedor reativado.'];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Fornecedores</h1>
        <p class="page-header__subtitle">De quem a oficina compra para o próprio abastecimento. O Mecanize não vende peças.</p>
    </div>
    <div class="page-header__actions">
        <a href="./painelCompras" class="btn btn--ghost">Compras</a>
        <a href="./saveFornecedor" class="btn btn--primary">Novo fornecedor</a>
    </div>
</div>

<?php if (!empty($mensagens[$sucesso ?? ''])): ?>
    <?php $alertaTipo = 'sucesso'; $alertaMensagem = $mensagens[$sucesso]; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<section class="card">
    <form method="GET" action="./listFornecedores" class="table-toolbar">
        <div class="search-wrap"><input type="text" name="busca" value="<?= $e($busca) ?>" placeholder="Nome, documento ou contato..."></div>
        <span class="text-muted fs-12"><?= count($fornecedores) ?> fornecedor(es)</span>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Fornecedor</th><th>Contato</th><th>Pagamento / prazo</th><th>Site</th><th>Pedidos</th><th>Situação</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($fornecedores)): ?>
                <tr><td colspan="7" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">Nenhum fornecedor cadastrado.</td></tr>
            <?php else: foreach ($fornecedores as $f): $ativo = (int) $f['ativo'] === 1; ?>
                <tr style="<?= $ativo ? '' : 'opacity:.6;' ?>">
                    <td><strong><?= $e($f['nome']) ?></strong><div class="text-muted fs-12"><?= $e($f['documento'] ?? '') ?></div></td>
                    <td>
                        <?= $e($f['contato'] ?? '—') ?>
                        <div class="text-muted fs-12"><?= $e($f['telefone'] ?? '') ?> <?= $e($f['email'] ?? '') ?></div>
                    </td>
                    <td class="text-muted fs-12"><?= $e($f['condicoes_pagamento'] ?? '—') ?><?= $f['prazo_medio_dias'] !== null ? '<div>prazo médio ' . (int) $f['prazo_medio_dias'] . ' dia(s)</div>' : '' ?></td>
                    <td><?php if (!empty($f['site_url'])): ?><a href="<?= $e($f['site_url']) ?>" target="_blank" rel="noopener noreferrer">abrir site</a><?php else: ?>—<?php endif; ?></td>
                    <td><?= (int) $f['total_pedidos'] ?></td>
                    <td><span class="badge <?= $ativo ? 'badge--success' : 'badge--neutral' ?>"><?= $ativo ? 'Ativo' : 'Inativo' ?></span></td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <a class="btn btn--ghost btn--sm" href="./editFornecedor?id=<?= (int) $f['id_fornecedor'] ?>">Editar</a>
                            <form action="./alternarFornecedor" method="POST">
                                <?= csrf_field() ?><input type="hidden" name="id_fornecedor" value="<?= (int) $f['id_fornecedor'] ?>">
                                <button type="submit" class="btn btn--ghost btn--sm"><?= $ativo ? 'Desativar' : 'Reativar' ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
