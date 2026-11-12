<?php
/**
 * Contas a pagar (parcelas). Espera: $parcelas, $totais, $filtros, $fornecedores, $cartoes, $chaveRapida, $sucesso, $erro.
 */
require_once __DIR__ . '/../../models/Despesa.php';
require_once __DIR__ . '/../../models/ContaPagar.php';

$e = fn($v) => htmlspecialchars((string) $v);
$brl = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$data = fn($d) => $d ? date('d/m/Y', strtotime($d)) : '—';

$abas = ['abertas' => 'Em aberto', 'vencidas' => 'Vencidas', 'pagas' => 'Pagas', 'todas' => 'Todas'];
$situacao = $filtros['situacao'];
$badge = ['PENDENTE' => 'badge--neutral', 'VENCIDA' => 'badge--danger', 'PAGA' => 'badge--success'];
$rotulo = ['PENDENTE' => 'A vencer', 'VENCIDA' => 'Vencida', 'PAGA' => 'Paga'];
$soma = array_sum(array_map(fn($p) => (float) $p['valor'], $parcelas));

$mensagens = ['pago' => 'Pagamento registrado.'];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Contas a pagar</h1>
        <p class="page-header__subtitle">Cada parcela conta uma vez. Parcela de cartão é quitada quando a fatura é paga.</p>
    </div>
    <div class="page-header__actions">
        <a href="./listCartoes" class="btn btn--ghost">Cartões e faturas</a>
        <a href="./painelFinanceiro" class="btn btn--ghost">Painel financeiro</a>
        <a href="./saveDespesa" class="btn btn--primary">Nova despesa</a>
    </div>
</div>

<?php if (!empty($mensagens[$sucesso ?? ''])): ?>
    <?php $alertaTipo = 'sucesso'; $alertaMensagem = $mensagens[$sucesso]; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>
<?php if (!empty($erro)): ?>
    <?php $alertaTipo = 'erro'; $alertaMensagem = $erro; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<section class="stat-grid">
    <div class="stat-card"><div class="stat-card__head"><div class="stat-card__label">Total a pagar</div><div class="stat-card__icon b3"><i class="bi bi-arrow-up-circle"></i></div></div>
        <div class="stat-card__value"><?= $brl($totais['pendente']) ?></div><div class="stat-card__delta"><?= (int) $totais['parcelas_pendentes'] ?> parcela(s)</div></div>
    <div class="stat-card"><div class="stat-card__head"><div class="stat-card__label">Vencido</div><div class="stat-card__icon b4"><i class="bi bi-exclamation-octagon"></i></div></div>
        <div class="stat-card__value" style="<?= $totais['vencido'] > 0 ? 'color:var(--color-danger);' : '' ?>"><?= $brl($totais['vencido']) ?></div><div class="stat-card__delta"><?= (int) $totais['parcelas_vencidas'] ?> em atraso</div></div>
    <div class="stat-card"><div class="stat-card__head"><div class="stat-card__label">Próximos 7 dias</div><div class="stat-card__icon b1"><i class="bi bi-calendar-event"></i></div></div>
        <div class="stat-card__value"><?= $brl($totais['proximos_7_dias']) ?></div><div class="stat-card__delta">a vencer no total: <?= $brl($totais['a_vencer']) ?></div></div>
</section>

<section class="card">
    <div class="tabs">
        <?php foreach ($abas as $chave => $nome): ?>
            <a class="tabs__item <?= $situacao === $chave ? 'is-active' : '' ?>" href="./listContasPagar?situacao=<?= $chave ?>" style="text-decoration:none;"><?= $e($nome) ?></a>
        <?php endforeach; ?>
    </div>

    <form method="GET" action="./listContasPagar" class="table-toolbar">
        <input type="hidden" name="situacao" value="<?= $e($situacao) ?>">
        <div class="search-wrap"><input type="text" name="busca" value="<?= $e($filtros['busca'] ?? '') ?>" placeholder="Descrição, nº ou fornecedor..."></div>
        <select name="fornecedor_id" class="toolbar-select" onchange="this.form.submit()">
            <option value="">Todos os fornecedores</option>
            <?php foreach ($fornecedores as $f): ?><option value="<?= (int) $f['id_fornecedor'] ?>" <?= (int) ($filtros['fornecedor_id'] ?? 0) === (int) $f['id_fornecedor'] ? 'selected' : '' ?>><?= $e($f['nome']) ?></option><?php endforeach; ?>
        </select>
        <select name="cartao_id" class="toolbar-select" onchange="this.form.submit()">
            <option value="">Todos os cartões</option>
            <?php foreach ($cartoes as $c): ?><option value="<?= (int) $c['id_cartao'] ?>" <?= (int) ($filtros['cartao_id'] ?? 0) === (int) $c['id_cartao'] ? 'selected' : '' ?>><?= $e($c['apelido']) ?></option><?php endforeach; ?>
        </select>
        <input type="date" name="de" class="toolbar-select" value="<?= $e($filtros['de'] ?? '') ?>" aria-label="Vencimento de"> <span class="fs-12 text-muted">até</span> <input type="date" name="ate" class="toolbar-select" value="<?= $e($filtros['ate'] ?? '') ?>" aria-label="Vencimento até">
        <button type="submit" class="btn btn--ghost btn--sm">Filtrar</button>
        <span class="text-muted fs-12"><?= count($parcelas) ?> parcela(s) · <?= $brl($soma) ?></span>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Despesa</th><th>Fornecedor / cartão</th><th>Parcela</th><th>Vencimento</th><th style="text-align:right;">Valor</th><th>Situação</th><th>Ação</th></tr></thead>
            <tbody>
            <?php if (empty($parcelas)): ?>
                <tr><td colspan="7" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">Nenhuma parcela neste filtro.</td></tr>
            <?php else: foreach ($parcelas as $p): ?>
                <tr>
                    <td>
                        <a href="./viewDespesa?id=<?= (int) $p['id_despesa'] ?>"><strong><?= $e($p['descricao']) ?></strong></a>
                        <div class="text-muted fs-12"><?= $e($p['despesa_numero']) ?> · <?= $e(Despesa::rotuloCategoria($p['categoria'])) ?></div>
                    </td>
                    <td class="text-muted"><?= $e($p['fornecedor_nome'] ?? '—') ?><?php if ($p['cartao_apelido']): ?><div class="fs-12">💳 <?= $e($p['cartao_apelido']) ?></div><?php endif; ?></td>
                    <td><?= (int) $p['numero'] ?> de <?= (int) $p['qtd_parcelas'] ?></td>
                    <td><?= $data($p['vencimento']) ?></td>
                    <td style="text-align:right;"><strong><?= $brl($p['valor']) ?></strong></td>
                    <td><span class="badge <?= $badge[$p['situacao']] ?>"><span class="badge__dot"></span><?= $e($rotulo[$p['situacao']]) ?></span>
                        <?php if ($p['status'] === 'PAGA'): ?><div class="text-muted fs-12">em <?= $data($p['pago_em']) ?></div><?php endif; ?></td>
                    <td>
                        <?php if ($p['status'] === 'PENDENTE' && $p['via_fatura']): ?>
                            <a class="btn btn--ghost btn--sm" href="./viewFatura?id=<?= (int) $p['fatura_id'] ?>">Pagar pela fatura</a>
                        <?php elseif ($p['status'] === 'PENDENTE'): ?>
                            <details>
                                <summary class="btn btn--primary btn--sm" style="list-style:none;cursor:pointer;">Pagar</summary>
                                <form action="./pagarParcelaConta" method="POST" style="margin-top:8px;min-width:220px;" onsubmit="return confirm('Registrar o pagamento desta parcela?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id_parcela" value="<?= (int) $p['id_conta_parcela'] ?>"><input type="hidden" name="id_despesa" value="<?= (int) $p['id_despesa'] ?>">
                                    <input type="hidden" name="chave" value="<?= $e($chaveRapida . '-' . $p['id_conta_parcela']) ?>">
                                    <select name="forma" data-no-ts required>
                                        <?php foreach (ContaPagar::FORMAS as $chave => $nome): ?><option value="<?= $e($chave) ?>"><?= $e($nome) ?></option><?php endforeach; ?>
                                    </select>
                                    <label class="fs-12" style="display:flex;gap:6px;align-items:center;margin-top:6px;"><input type="checkbox" name="lancar_no_caixa" value="1"> Lançar a saída no caixa aberto</label>
                                    <button type="submit" class="btn btn--primary btn--sm" style="margin-top:6px;">Confirmar</button>
                                </form>
                            </details>
                        <?php else: ?>
                            <a class="btn btn--ghost btn--sm" href="./viewDespesa?id=<?= (int) $p['id_despesa'] ?>">Abrir</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
