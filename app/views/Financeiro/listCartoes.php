<?php
/**
 * Cartões corporativos. Espera: $cartoes, $sucesso.
 */
$e = fn($v) => htmlspecialchars((string) $v);
$brl = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$mensagens = ['criado' => 'Cartão cadastrado.', 'salvo' => 'Cartão atualizado.', 'desativado' => 'Cartão desativado (o histórico continua).', 'reativado' => 'Cartão reativado.'];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Cartões corporativos</h1>
        <p class="page-header__subtitle">Limite, fechamento, vencimento e faturas. O sistema não guarda número do cartão nem se conecta ao banco: compras e pagamentos são registrados à mão.</p>
    </div>
    <div class="page-header__actions">
        <a href="./listContasPagar" class="btn btn--ghost">Contas a pagar</a>
        <a href="./saveCartao" class="btn btn--primary">Novo cartão</a>
    </div>
</div>

<?php if (!empty($mensagens[$sucesso ?? ''])): ?>
    <?php $alertaTipo = 'sucesso'; $alertaMensagem = $mensagens[$sucesso]; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<?php if (empty($cartoes)): ?>
    <section class="card"><div class="card__body" style="padding:48px 20px;text-align:center;color:var(--color-text-muted);">Nenhum cartão cadastrado.</div></section>
<?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;">
    <?php foreach ($cartoes as $c): $ativo = (int) $c['ativo'] === 1; ?>
        <section class="card" style="<?= $ativo ? '' : 'opacity:.6;' ?>">
            <div class="card__header">
                <div class="card__title">💳 <?= $e($c['apelido']) ?></div>
                <span class="badge <?= $ativo ? 'badge--success' : 'badge--neutral' ?>"><?= $ativo ? 'Ativo' : 'Inativo' ?></span>
            </div>
            <div class="card__body" style="padding:14px 20px;font-size:13px;">
                <div class="text-muted"><?= $e($c['instituicao']) ?><?= $c['bandeira'] ? ' · ' . $e($c['bandeira']) : '' ?><?= $c['final_cartao'] ? ' · final ' . $e($c['final_cartao']) : '' ?></div>
                <div class="text-muted fs-12">fecha dia <?= (int) $c['dia_fechamento'] ?> · vence dia <?= (int) $c['dia_vencimento'] ?></div>
                <dl style="display:grid;grid-template-columns:1fr auto;gap:4px 10px;margin:12px 0 0;">
                    <dt class="text-muted">Comprometido (não pago)</dt><dd style="margin:0;text-align:right;"><strong><?= $brl($c['comprometido']) ?></strong></dd>
                    <?php if ($c['limite_total'] !== null): ?>
                        <dt class="text-muted">Limite total</dt><dd style="margin:0;text-align:right;"><?= $brl($c['limite_total']) ?></dd>
                        <dt class="text-muted">Limite disponível</dt><dd style="margin:0;text-align:right;<?= $c['limite_excedido'] ? 'color:var(--color-danger);' : '' ?>"><strong><?= $brl($c['limite_disponivel']) ?></strong><?= $c['limite_excedido'] ? ' (excedido)' : '' ?></dd>
                    <?php else: ?>
                        <dt class="text-muted">Limite</dt><dd style="margin:0;text-align:right;" class="text-muted">não informado</dd>
                    <?php endif; ?>
                </dl>
                <div style="display:flex;gap:6px;margin-top:14px;flex-wrap:wrap;">
                    <a class="btn btn--primary btn--sm" href="./viewCartao?id=<?= (int) $c['id_cartao'] ?>">Faturas</a>
                    <a class="btn btn--ghost btn--sm" href="./editCartao?id=<?= (int) $c['id_cartao'] ?>">Editar</a>
                    <form action="./alternarCartao" method="POST"><?= csrf_field() ?><input type="hidden" name="id_cartao" value="<?= (int) $c['id_cartao'] ?>">
                        <button type="submit" class="btn btn--ghost btn--sm"><?= $ativo ? 'Desativar' : 'Reativar' ?></button></form>
                </div>
            </div>
        </section>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
