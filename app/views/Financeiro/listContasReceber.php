<?php
/**
 * Contas a receber. Espera: $parcelas, $totais, $filtros.
 */
require_once __DIR__ . '/../../models/MovimentacaoCaixa.php';

$e = fn($v) => htmlspecialchars((string) $v);
$brl = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$data = fn($d) => $d ? date('d/m/Y', strtotime($d)) : '—';

$abas = ['abertas' => 'Em aberto', 'vencidas' => 'Vencidas', 'recebidas' => 'Recebidas', 'todas' => 'Todas'];
$situacao = $filtros['situacao'];
$badge = ['PENDENTE' => 'badge--neutral', 'VENCIDA' => 'badge--danger', 'RECEBIDA' => 'badge--success'];
$rotulo = ['PENDENTE' => 'A vencer', 'VENCIDA' => 'Vencida', 'RECEBIDA' => 'Recebida'];

$soma = array_sum(array_map(fn($p) => (float) $p['valor'], $parcelas));
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Contas a receber</h1>
        <p class="page-header__subtitle">Parcelas das OS já cobradas. Só a baixa da parcela é dinheiro recebido.</p>
    </div>
    <div class="page-header__actions"><a href="./painelFinanceiro" class="btn btn--ghost">Painel financeiro</a></div>
</div>

<section class="stat-grid">
    <div class="stat-card"><div class="stat-card__head"><div class="stat-card__label">Total a receber</div><div class="stat-card__icon b2"><i class="bi bi-arrow-down-circle"></i></div></div>
        <div class="stat-card__value"><?= $brl($totais['a_receber']) ?></div><div class="stat-card__delta"><?= (int) $totais['parcelas_pendentes'] ?> parcela(s) · <?= (int) $totais['clientes_devedores'] ?> cliente(s)</div></div>
    <div class="stat-card"><div class="stat-card__head"><div class="stat-card__label">Vencido</div><div class="stat-card__icon b4"><i class="bi bi-exclamation-triangle"></i></div></div>
        <div class="stat-card__value" style="<?= $totais['vencido'] > 0 ? 'color:var(--color-danger);' : '' ?>"><?= $brl($totais['vencido']) ?></div><div class="stat-card__delta"><?= (int) $totais['parcelas_vencidas'] ?> em atraso</div></div>
    <div class="stat-card"><div class="stat-card__head"><div class="stat-card__label">A vencer</div><div class="stat-card__icon b1"><i class="bi bi-calendar-event"></i></div></div>
        <div class="stat-card__value"><?= $brl($totais['a_vencer']) ?></div><div class="stat-card__delta">próximos 7 dias: <?= $brl($totais['proximos_7_dias']) ?></div></div>
</section>

<section class="card">
    <div class="tabs">
        <?php foreach ($abas as $chave => $nome): ?>
            <a class="tabs__item <?= $situacao === $chave ? 'is-active' : '' ?>" href="./listContasReceber?situacao=<?= $chave ?>" style="text-decoration:none;"><?= $e($nome) ?></a>
        <?php endforeach; ?>
    </div>

    <form method="GET" action="./listContasReceber" class="table-toolbar">
        <input type="hidden" name="situacao" value="<?= $e($situacao) ?>">
        <div class="search-wrap"><input type="text" name="busca" value="<?= $e($filtros['busca'] ?? '') ?>" placeholder="Cliente, nº da OS ou placa..."></div>
        <select name="forma" class="toolbar-select" onchange="this.form.submit()">
            <option value="">Qualquer forma</option>
            <?php foreach (MovimentacaoCaixa::FORMAS as $f): ?>
                <option value="<?= $f ?>" <?= ($filtros['forma'] ?? '') === $f ? 'selected' : '' ?>><?= $e(MovimentacaoCaixa::rotuloForma($f)) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="fs-12 text-muted"><?= $situacao === 'recebidas' ? 'Recebidas de' : 'Vencem de' ?></label>
        <input type="date" name="de" class="toolbar-select" value="<?= $e($filtros['de'] ?? '') ?>"> <span class="fs-12 text-muted">até</span> <input type="date" name="ate" class="toolbar-select" value="<?= $e($filtros['ate'] ?? '') ?>">
        <button type="submit" class="btn btn--ghost btn--sm">Filtrar</button>
        <span class="text-muted fs-12"><?= count($parcelas) ?> parcela(s) · <?= $brl($soma) ?></span>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>OS</th><th>Cliente</th><th>Parcela</th><th>Vencimento</th><th style="text-align:right;">Valor</th><th>Forma</th><th>Situação</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($parcelas)): ?>
                <tr><td colspan="8" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">Nenhuma parcela neste filtro.</td></tr>
            <?php else: foreach ($parcelas as $p): ?>
                <tr>
                    <td class="id-col"><a href="./viewOrdemServico?id=<?= (int) $p['id_ordem_servico'] ?>"><?= $e($p['os_numero']) ?></a><div class="text-muted fs-12"><?= $e(strtoupper($p['placa'])) ?></div></td>
                    <td><a href="./viewCliente?id=<?= (int) $p['cliente_id'] ?>"><?= $e($p['cliente_nome']) ?></a></td>
                    <td><?= (int) $p['numero'] ?> de <?= (int) $p['total_parcelas'] ?></td>
                    <td><?= $data($p['vencimento']) ?></td>
                    <td style="text-align:right;"><strong><?= $brl($p['valor']) ?></strong>
                        <?php if ((float) $p['taxa_valor'] > 0): ?><div class="text-muted fs-12">taxa <?= $brl($p['taxa_valor']) ?></div><?php endif; ?></td>
                    <td class="text-muted"><?= $e(MovimentacaoCaixa::rotuloForma($p['pago'] ? $p['forma_pagamento'] : $p['forma_prevista'])) ?>
                        <?php if ($p['pago']): ?><div class="fs-12">em <?= $data($p['data_pagamento']) ?></div><?php endif; ?></td>
                    <td><span class="badge <?= $badge[$p['situacao']] ?>"><span class="badge__dot"></span><?= $e($rotulo[$p['situacao']]) ?></span></td>
                    <td><a class="btn <?= $p['pago'] ? 'btn--ghost' : 'btn--primary' ?> btn--sm" href="./pagamentoOrdemServico?id=<?= (int) $p['id_ordem_servico'] ?>"><?= $p['pago'] ? 'Abrir' : 'Receber' ?></a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
