<?php
/**
 * Faturas de um cartão. Espera: $cartao, $faturas.
 */
require_once __DIR__ . '/../../models/FaturaCartao.php';

$e = fn($v) => htmlspecialchars((string) $v);
$brl = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$data = fn($x) => $x ? date('d/m/Y', strtotime($x)) : '—';
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">💳 <?= $e($cartao['apelido']) ?></h1>
        <p class="page-header__subtitle"><?= $e($cartao['instituicao']) ?> · fecha dia <?= (int) $cartao['dia_fechamento'] ?> · vence dia <?= (int) $cartao['dia_vencimento'] ?></p>
    </div>
    <div class="page-header__actions">
        <a href="./listCartoes" class="btn btn--ghost">Voltar</a>
        <a href="./saveDespesa" class="btn btn--primary">Nova compra</a>
    </div>
</div>

<section class="stat-grid">
    <div class="stat-card"><div class="stat-card__head"><div class="stat-card__label">Comprometido</div></div><div class="stat-card__value"><?= $brl($cartao['comprometido']) ?></div><div class="stat-card__delta">parcelas ainda não pagas, inclusive futuras</div></div>
    <div class="stat-card"><div class="stat-card__head"><div class="stat-card__label">Limite disponível</div></div>
        <div class="stat-card__value" style="<?= $cartao['limite_excedido'] ? 'color:var(--color-danger);' : '' ?>"><?= $cartao['limite_disponivel'] !== null ? $brl($cartao['limite_disponivel']) : '—' ?></div>
        <div class="stat-card__delta"><?= $cartao['limite_total'] !== null ? 'de ' . $brl($cartao['limite_total']) : 'limite não informado' ?></div></div>
</section>

<section class="card" style="margin-top:16px;">
    <div class="card__header"><div class="card__title">Faturas</div></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Fatura</th><th>Fecha em</th><th>Vence em</th><th style="text-align:right;">Total</th><th style="text-align:right;">Pendente</th><th>Situação</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($faturas)): ?>
                <tr><td colspan="7" style="text-align:center;padding:40px 20px;color:var(--color-text-muted);">Nenhuma compra neste cartão ainda.</td></tr>
            <?php else: foreach ($faturas as $f): ?>
                <tr>
                    <td><strong><?= date('m/Y', strtotime($f['vence_em'])) ?></strong><div class="text-muted fs-12"><?= (int) $f['qtd_compras'] ?> parcela(s)</div></td>
                    <td><?= $data($f['fecha_em']) ?></td>
                    <td><?= $data($f['vence_em']) ?></td>
                    <td style="text-align:right;"><?= $brl($f['total']) ?></td>
                    <td style="text-align:right;"><?= $brl($f['pendente']) ?></td>
                    <td><span class="badge <?= FaturaCartao::SITUACAO_BADGE[$f['situacao']] ?>"><span class="badge__dot"></span><?= $e(FaturaCartao::SITUACAO_LABEL[$f['situacao']]) ?></span>
                        <?php if ($f['paga_em']): ?><div class="text-muted fs-12">em <?= $data($f['paga_em']) ?></div><?php endif; ?></td>
                    <td><a class="btn btn--ghost btn--sm" href="./viewFatura?id=<?= (int) $f['id_fatura'] ?>">Abrir</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
