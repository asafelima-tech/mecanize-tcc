<?php
/**
 * Painel financeiro (administrador). Espera: $ind, $fluxo, $vencimentos, $receberProx, $opcao, $inicio, $fim.
 * Cada indicador tem UMA definição (ver Financeiro): a seção "Como lemos cada número" a repete na tela.
 */
$e = fn($v) => htmlspecialchars((string) $v);
$brl = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$data = fn($d) => $d ? date('d/m/Y', strtotime($d)) : '—';

$opcoes = ['mes' => 'Este mês', 'mes_passado' => 'Mês passado', '30d' => 'Últimos 30 dias', 'ano' => 'Este ano', 'custom' => 'Período livre'];

$receber = $ind['a_receber'];
$pagar   = $ind['a_pagar'];
$desp    = $ind['despesas_pagas'];
$custos  = $ind['custos'];

$cor = fn($v) => $v < 0 ? 'color:var(--color-danger);' : '';
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Financeiro</h1>
        <p class="page-header__subtitle">Faturado, recebido, a receber, a pagar e lucro — cada um separado, cada um com a sua conta.</p>
    </div>
    <div class="page-header__actions">
        <a href="./listContasReceber" class="btn btn--ghost">A receber</a>
        <a href="./listContasPagar" class="btn btn--ghost">A pagar</a>
        <a href="./listCartoes" class="btn btn--ghost">Cartões</a>
        <a href="./saveDespesa" class="btn btn--primary">Nova despesa</a>
    </div>
</div>

<form method="GET" action="./painelFinanceiro" class="table-toolbar" style="margin-bottom:16px;">
    <select name="periodo" class="toolbar-select" onchange="document.getElementById('periodoLivre').style.display = this.value === 'custom' ? 'flex' : 'none'; if (this.value !== 'custom') this.form.submit();">
        <?php foreach ($opcoes as $chave => $rotulo): ?>
            <option value="<?= $chave ?>" <?= $opcao === $chave ? 'selected' : '' ?>><?= $e($rotulo) ?></option>
        <?php endforeach; ?>
    </select>
    <span id="periodoLivre" style="display:<?= $opcao === 'custom' ? 'flex' : 'none' ?>;gap:6px;align-items:center;">
        <input type="date" name="de" class="toolbar-select" aria-label="De" value="<?= $e($inicio) ?>"> até <input type="date" name="ate" class="toolbar-select" aria-label="Até" value="<?= $e($fim) ?>">
        <button type="submit" class="btn btn--ghost btn--sm">Aplicar</button>
    </span>
    <span class="text-muted fs-12">Período: <?= $data($inicio) ?> a <?= $data($fim) ?></span>
</form>

<h2 style="font-size:13px;text-transform:uppercase;letter-spacing:.04em;color:var(--color-text-muted);margin:0 0 8px;">No período</h2>
<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-card__head"><div class="stat-card__label">Faturamento bruto</div><div class="stat-card__icon b1"><i class="bi bi-receipt"></i></div></div>
        <div class="stat-card__value"><?= $brl($ind['faturamento_bruto']) ?></div>
        <div class="stat-card__delta"><?= (int) $ind['os_entregues'] ?> OS entregues · não é dinheiro recebido</div>
    </div>
    <div class="stat-card">
        <div class="stat-card__head"><div class="stat-card__label">Recebido (bruto)</div><div class="stat-card__icon b2"><i class="bi bi-cash-coin"></i></div></div>
        <div class="stat-card__value"><?= $brl($ind['recebido_bruto']) ?></div>
        <div class="stat-card__delta"><?= (int) $ind['parcelas_recebidas'] ?> parcela(s) baixada(s)</div>
    </div>
    <div class="stat-card">
        <div class="stat-card__head"><div class="stat-card__label">Taxas financeiras</div><div class="stat-card__icon b3"><i class="bi bi-percent"></i></div></div>
        <div class="stat-card__value"><?= $brl($ind['taxas']) ?></div>
        <div class="stat-card__delta">Líquido recebido: <strong><?= $brl($ind['recebido_liquido']) ?></strong></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__head"><div class="stat-card__label">Despesas pagas</div><div class="stat-card__icon b4"><i class="bi bi-wallet2"></i></div></div>
        <div class="stat-card__value"><?= $brl($desp['total']) ?></div>
        <div class="stat-card__delta">contas <?= $brl($desp['contas_a_pagar']) ?> · folha <?= $brl($desp['folha_comissoes']) ?> · caixa <?= $brl($desp['caixa_direto']) ?></div>
    </div>
</section>

<section class="stat-grid" style="margin-top:12px;">
    <div class="stat-card">
        <div class="stat-card__head"><div class="stat-card__label">Fluxo de caixa realizado</div><div class="stat-card__icon b2"><i class="bi bi-arrow-left-right"></i></div></div>
        <div class="stat-card__value" style="<?= $cor($ind['fluxo_realizado']) ?>"><?= $brl($ind['fluxo_realizado']) ?></div>
        <div class="stat-card__delta">recebido líquido + outras entradas − despesas pagas</div>
    </div>
    <div class="stat-card">
        <div class="stat-card__head"><div class="stat-card__label">Resultado operacional estimado</div><div class="stat-card__icon b1"><i class="bi bi-graph-up"></i></div></div>
        <div class="stat-card__value" style="<?= $cor($ind['resultado_operacional_estimado']) ?>"><?= $brl($ind['resultado_operacional_estimado']) ?></div>
        <div class="stat-card__delta">inclui o que ainda vai entrar · estimativa</div>
    </div>
    <div class="stat-card">
        <div class="stat-card__head"><div class="stat-card__label">Lucro efetivo</div><div class="stat-card__icon b3"><i class="bi bi-piggy-bank"></i></div></div>
        <div class="stat-card__value" style="<?= $cor($ind['lucro_efetivo']) ?>"><?= $brl($ind['lucro_efetivo']) ?></div>
        <div class="stat-card__delta">só o que já entrou das OS do período</div>
    </div>
</section>

<h2 style="font-size:13px;text-transform:uppercase;letter-spacing:.04em;color:var(--color-text-muted);margin:20px 0 8px;">Hoje</h2>
<section class="stat-grid">
    <a class="stat-card" href="./listContasReceber" style="text-decoration:none;color:inherit;">
        <div class="stat-card__head"><div class="stat-card__label">A receber</div><div class="stat-card__icon b2"><i class="bi bi-arrow-down-circle"></i></div></div>
        <div class="stat-card__value"><?= $brl($receber['a_receber']) ?></div>
        <div class="stat-card__delta"><?= (int) $receber['parcelas_pendentes'] ?> parcela(s) · <?= (int) $receber['clientes_devedores'] ?> cliente(s)</div>
    </a>
    <a class="stat-card" href="./listContasReceber?situacao=vencidas" style="text-decoration:none;color:inherit;">
        <div class="stat-card__head"><div class="stat-card__label">A receber vencido</div><div class="stat-card__icon b4"><i class="bi bi-exclamation-triangle"></i></div></div>
        <div class="stat-card__value" style="<?= $receber['vencido'] > 0 ? 'color:var(--color-danger);' : '' ?>"><?= $brl($receber['vencido']) ?></div>
        <div class="stat-card__delta"><?= (int) $receber['parcelas_vencidas'] ?> parcela(s) em atraso</div>
    </a>
    <a class="stat-card" href="./listContasPagar" style="text-decoration:none;color:inherit;">
        <div class="stat-card__head"><div class="stat-card__label">A pagar</div><div class="stat-card__icon b3"><i class="bi bi-arrow-up-circle"></i></div></div>
        <div class="stat-card__value"><?= $brl($pagar['pendente']) ?></div>
        <div class="stat-card__delta"><?= (int) $pagar['parcelas_pendentes'] ?> parcela(s) · próximos 7 dias: <?= $brl($pagar['proximos_7_dias']) ?></div>
    </a>
    <a class="stat-card" href="./listContasPagar?situacao=vencidas" style="text-decoration:none;color:inherit;">
        <div class="stat-card__head"><div class="stat-card__label">A pagar vencido</div><div class="stat-card__icon b4"><i class="bi bi-exclamation-octagon"></i></div></div>
        <div class="stat-card__value" style="<?= $pagar['vencido'] > 0 ? 'color:var(--color-danger);' : '' ?>"><?= $brl($pagar['vencido']) ?></div>
        <div class="stat-card__delta"><?= (int) $pagar['parcelas_vencidas'] ?> parcela(s) em atraso</div>
    </a>
</section>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px;margin-top:16px;">
    <section class="card">
        <div class="card__header"><div class="card__title">Fluxo projetado</div><span class="text-muted fs-12">parcelas a receber − parcelas a pagar, por mês de vencimento</span></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Mês</th><th style="text-align:right;">Entradas</th><th style="text-align:right;">Saídas</th><th style="text-align:right;">Saldo</th><th style="text-align:right;">Acumulado</th></tr></thead>
                <tbody>
                <?php foreach ($fluxo as $l): ?>
                    <tr>
                        <td><?= $e($l['rotulo']) ?></td>
                        <td style="text-align:right;"><?= $brl($l['entradas']) ?></td>
                        <td style="text-align:right;"><?= $brl($l['saidas']) ?></td>
                        <td style="text-align:right;<?= $cor($l['saldo']) ?>"><?= $brl($l['saldo']) ?></td>
                        <td style="text-align:right;<?= $cor($l['acumulado']) ?>"><strong><?= $brl($l['acumulado']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card__header"><div class="card__title">Próximos vencimentos a pagar</div><a class="fs-12" href="./listContasPagar">ver todos</a></div>
        <div class="card__body" style="padding:8px 20px 16px;">
            <?php if (empty($vencimentos)): ?>
                <p class="text-muted" style="font-size:13px;margin:8px 0;">Nada a pagar por enquanto.</p>
            <?php else: foreach ($vencimentos as $v): $atrasado = $v['vencimento'] < date('Y-m-d'); ?>
                <div style="display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid var(--color-border);font-size:13px;">
                    <div>
                        <?php if ($v['tipo'] === 'FATURA'): ?>
                            <a href="./viewFatura?id=<?= (int) $v['id'] ?>"><?= $e($v['titulo']) ?></a>
                        <?php else: ?>
                            <a href="./viewDespesa?id=<?= (int) $v['id_despesa'] ?>"><?= $e($v['titulo']) ?></a>
                        <?php endif; ?>
                        <div class="fs-12 <?= $atrasado ? '' : 'text-muted' ?>" style="<?= $atrasado ? 'color:var(--color-danger);' : '' ?>"><?= $atrasado ? 'venceu em ' : 'vence em ' ?><?= $data($v['vencimento']) ?></div>
                    </div>
                    <strong><?= $brl($v['valor']) ?></strong>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </section>

    <section class="card">
        <div class="card__header"><div class="card__title">Próximos a receber</div><a class="fs-12" href="./listContasReceber">ver todos</a></div>
        <div class="card__body" style="padding:8px 20px 16px;">
            <?php if (empty($receberProx)): ?>
                <p class="text-muted" style="font-size:13px;margin:8px 0;">Nenhuma parcela a receber.</p>
            <?php else: foreach ($receberProx as $p): ?>
                <div style="display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid var(--color-border);font-size:13px;">
                    <div>
                        <a href="./pagamentoOrdemServico?id=<?= (int) $p['id_ordem_servico'] ?>">OS <?= $e($p['os_numero']) ?> · parcela <?= (int) $p['numero'] ?>/<?= (int) $p['total_parcelas'] ?></a>
                        <div class="fs-12 text-muted"><?= $e($p['cliente_nome']) ?> · <?= $p['situacao'] === 'VENCIDA' ? 'venceu' : 'vence' ?> em <?= $data($p['vencimento']) ?></div>
                    </div>
                    <strong><?= $brl($p['valor']) ?></strong>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </section>
</div>

<details class="card" style="margin-top:16px;">
    <summary class="card__header" style="cursor:pointer;"><span class="card__title">Como lemos cada número</span></summary>
    <div class="card__body" style="padding:16px 20px;font-size:13px;display:flex;flex-direction:column;gap:8px;">
        <p style="margin:0;"><strong>Faturamento bruto</strong> — valor das OS entregues no período, pela data da entrega. Entregar ou concluir uma OS <em>não</em> é receber.</p>
        <p style="margin:0;"><strong>Recebido</strong> — parcelas de OS baixadas no período. As <strong>taxas</strong> do meio de pagamento (maquininha) são registradas à parte; recebido líquido = bruto − taxas.</p>
        <p style="margin:0;"><strong>A receber</strong> — parcelas pendentes de OS já cobradas (conferidas pelo administrador). Depois que a 1ª de 5 parcelas é recebida, as outras 4 seguem aqui.</p>
        <p style="margin:0;"><strong>A pagar</strong> — parcelas pendentes das despesas. A compra no cartão, as parcelas e o pagamento da fatura são <em>uma</em> despesa: cada parcela conta uma vez, e a fatura é só o agrupamento delas.</p>
        <p style="margin:0;"><strong>Despesas pagas</strong> — parcelas quitadas no período (direto ou pela fatura) + fechamentos de comissão/salário + saídas do caixa lançadas à mão que não pertencem a uma conta a pagar.</p>
        <p style="margin:0;"><strong>Fluxo realizado</strong> — recebido líquido + outras entradas do caixa − despesas pagas. <strong>Fluxo projetado</strong> — o que vence nos próximos meses.</p>
        <p style="margin:0;"><strong>Resultado operacional estimado</strong> — faturamento − custo das peças consumidas (<?= $brl($custos['pecas']) ?>) − comissões (<?= $brl($custos['comissoes']) ?>) − despesas operacionais (<?= $brl($custos['despesas_operacionais']) ?>) − salário fixo (<?= $brl($custos['salario_fixo']) ?>) − taxas. Compra de peças para estoque não entra aqui: o custo entra quando a peça é usada na OS.</p>
        <p style="margin:0;"><strong>Lucro efetivo</strong> — só o que <em>já entrou</em> das OS do período (<?= $brl($ind['recebido_das_os_do_periodo']) ?>), menos os custos delas e as despesas operacionais pagas. Parcela a receber não é lucro.</p>
    </div>
</details>
