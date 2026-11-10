<?php
/** Espera: $ini, $fim, $atalho, $faturamento, $tempo, $ordens, $caixa, $contas, $itens, $tecnicos, $clientes, $estoque, $agenda */
$titulo = 'Relatórios';
$fat = $faturamento;
$serie = $tempo['serie'];
$max = max(array_merge([0.0], array_column($serie, 'total')));
$qtd = fn($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
$pct = fn(?float $v) => $v === null ? '—' : number_format($v, 1, ',', '.') . '%';
$porForma = $caixa['por_forma'];
$abertasHoje = array_sum($ordens['abertas_hoje']);
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Relatórios</h1>
    <p class="page-header__subtitle"><?= data_br($ini) ?> a <?= data_br($fim) ?> · faturamento pelas OS entregues; recebido e pago pelo dinheiro que de fato entrou e saiu</p>
  </div>
  <button type="button" class="btn btn--ghost" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
</div>

<div class="card card--tabela nao-imprimir" style="margin-bottom:16px">
  <form class="filtros" method="get" action="./relatorios">
    <div class="abas abas--compactas">
      <?php foreach (RelatoriosController::ATALHOS as $k => $r): ?><a href="./relatorios?p=<?= e($k) ?>" class="<?= $atalho === (string) $k ? 'ativa' : '' ?>"><?= e($r) ?></a><?php endforeach; ?>
    </div>
    <label class="form-dica" for="ini">De</label><input class="filtros__data" type="date" id="ini" name="ini" value="<?= e($ini) ?>">
    <label class="form-dica" for="fim">até</label><input class="filtros__data" type="date" id="fim" name="fim" value="<?= e($fim) ?>">
    <button class="btn btn--ghost btn--sm">Aplicar</button>
  </form>
</div>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-card__label">Faturamento</div>
    <div class="stat-card__value"><?= dinheiro($fat['total']) ?></div>
    <div class="form-dica"><?= $fat['variacao'] === null ? 'sem base no período anterior' : ($fat['variacao'] >= 0 ? '▲ ' : '▼ ') . $pct(abs($fat['variacao'])) . ' sobre o período anterior (' . dinheiro($fat['anterior']) . ')' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-card__label">OS entregues · ticket médio</div>
    <div class="stat-card__value"><?= (int) $fat['os'] ?> · <?= dinheiro($fat['ticket']) ?></div>
    <div class="form-dica">Serviços <?= dinheiro($fat['servicos']) ?> · peças <?= dinheiro($fat['pecas']) ?><?= $fat['descontos'] > 0 ? ' · descontos ' . dinheiro($fat['descontos']) : '' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-card__label">Recebido − pago</div>
    <div class="stat-card__value"><?= dinheiro($caixa['saldo']) ?></div>
    <div class="form-dica">Recebido <?= dinheiro($caixa['recebido']) ?><?= $caixa['taxas'] > 0 ? ' (taxas ' . dinheiro($caixa['taxas']) . ')' : '' ?> · pago <?= dinheiro($caixa['pago']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-card__label">Aprovação de orçamentos</div>
    <div class="stat-card__value"><?= $pct($ordens['aprovacao']) ?></div>
    <div class="form-dica"><?= (int) $ordens['aprovadas'] ?> aprovado(s) · <?= (int) $ordens['recusadas'] ?> recusado(s) · <?= (int) $ordens['abertas'] ?> aberto(s) no período</div>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <h2 class="card__titulo">Faturamento <?= $tempo['por_mes'] ? 'por mês' : 'por dia' ?></h2>
  <?php if ($max <= 0): ?>
    <div class="vazio"><i class="bi bi-bar-chart"></i>Nenhuma OS entregue no período.</div>
  <?php else: ?>
    <div class="grafico" role="img" aria-label="Faturamento <?= $tempo['por_mes'] ? 'por mês' : 'por dia' ?>, máximo de <?= e(dinheiro($max)) ?>">
      <div class="grafico__eixo"><span><?= e(dinheiro($max)) ?></span><span><?= e(dinheiro($max / 2)) ?></span><span>R$ 0</span></div>
      <div class="grafico__barras" style="--n:<?= count($serie) ?>">
        <?php foreach ($serie as $i => $s): $h = $s['total'] > 0 ? max(1.5, $s['total'] / $max * 100) : 0; ?>
          <div class="grafico__col" tabindex="0">
            <div class="grafico__barra" style="height:<?= number_format($h, 2, '.', '') ?>%"></div>
            <div class="grafico__dica"><strong><?= e($s['rotulo']) ?></strong><br><?= e(dinheiro($s['total'])) ?> · <?= (int) $s['os'] ?> OS</div>
            <?php $passo = max(1, (int) ceil(count($serie) / 10)); if ($i % $passo === 0): ?><span class="grafico__rotulo"><?= e($s['rotulo']) ?></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <details class="nao-imprimir" style="margin-top:12px"><summary class="form-dica">Ver em tabela</summary>
      <div class="table-wrap"><table class="table"><thead><tr><th><?= $tempo['por_mes'] ? 'Mês' : 'Dia' ?></th><th class="num">OS</th><th class="num">Faturado</th></tr></thead><tbody>
        <?php foreach ($serie as $s): if ($s['os'] === 0) continue; ?><tr><td><?= e($s['rotulo']) ?></td><td class="num"><?= (int) $s['os'] ?></td><td class="num"><?= dinheiro($s['total']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </details>
  <?php endif; ?>
</div>

<div class="grade-2" style="margin-top:16px">
  <div class="card card--tabela">
    <div class="card__header"><span class="card__title">Entradas por forma de pagamento</span></div>
    <?php if (!$porForma): ?><div class="vazio">Nada recebido no período.</div><?php else: ?>
      <div class="table-wrap"><table class="table"><tbody>
        <?php foreach ($porForma as $forma => $v): ?><tr><td><?= e(Lancamento::FORMAS[$forma] ?? $forma) ?></td><td class="num"><?= dinheiro($v) ?></td><td class="num form-dica" style="width:70px"><?= $caixa['recebido'] > 0 ? $pct($v / $caixa['recebido'] * 100) : '' ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </div>
  <div class="card card--tabela">
    <div class="card__header"><span class="card__title">Saídas por categoria</span></div>
    <?php if (!$caixa['despesas']): ?><div class="vazio">Nada pago no período.</div><?php else: ?>
      <div class="table-wrap"><table class="table"><tbody>
        <?php foreach ($caixa['despesas'] as $cat => $v): ?><tr><td><?= e(Lancamento::categoria('PAGAR', $cat)) ?></td><td class="num"><?= dinheiro($v) ?></td><td class="num form-dica" style="width:70px"><?= $caixa['pago'] > 0 ? $pct($v / $caixa['pago'] * 100) : '' ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </div>
</div>

<div class="grade-2" style="margin-top:16px">
  <div class="card">
    <h2 class="card__titulo">Contas em aberto hoje <a href="./financeiro?situacao=vencidos" class="nao-imprimir">ver vencidas</a></h2>
    <dl class="dados">
      <div><dt>A receber</dt><dd><?= dinheiro($contas['RECEBER']['saldo']) ?> <span class="form-dica">(<?= (int) $contas['RECEBER']['n'] ?>)</span></dd></div>
      <div><dt>Vencido a receber</dt><dd><?= dinheiro($contas['RECEBER']['vencido']) ?> <span class="form-dica">(<?= (int) $contas['RECEBER']['n_vencidas'] ?>)</span></dd></div>
      <div><dt>A pagar</dt><dd><?= dinheiro($contas['PAGAR']['saldo']) ?> <span class="form-dica">(<?= (int) $contas['PAGAR']['n'] ?>)</span></dd></div>
      <div><dt>Vencido a pagar</dt><dd><?= dinheiro($contas['PAGAR']['vencido']) ?> <span class="form-dica">(<?= (int) $contas['PAGAR']['n_vencidas'] ?>)</span></dd></div>
    </dl>
  </div>
  <div class="card">
    <h2 class="card__titulo">Ordens de serviço em aberto hoje <a href="./ordens" class="nao-imprimir">ver todas</a></h2>
    <?php if (!$abertasHoje): ?><p class="form-dica">Nenhuma OS em aberto.</p><?php else: ?>
      <dl class="dados">
        <?php foreach ($ordens['abertas_hoje'] as $st => $n): ?><div><dt><?= e(os_status($st)[0]) ?></dt><dd><?= (int) $n ?></dd></div><?php endforeach; ?>
      </dl>
    <?php endif; ?>
    <p class="form-dica" style="margin-top:12px">No período: <?= (int) $ordens['canceladas'] ?> cancelada(s) · agenda com <?= (int) ($agenda['CONCLUIDO']) ?> atendimento(s), <?= (int) $agenda['FALTOU'] ?> falta(s) e <?= (int) $agenda['CANCELADO'] ?> cancelamento(s).</p>
  </div>
</div>

<div class="card card--tabela" style="margin-top:16px">
  <div class="card__header"><span class="card__title">Peças e serviços mais vendidos</span></div>
  <?php if (!$itens): ?><div class="vazio">Nenhuma OS entregue no período.</div><?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Item</th><th>Tipo</th><th class="num">Quantidade</th><th class="num">Vendido</th><th class="num">Margem das peças</th></tr></thead>
      <tbody><?php foreach ($itens as $it): ?>
        <tr><td><?= e($it['descricao']) ?></td><td><?= $it['tipo'] === 'PECA' ? 'Peça' : 'Serviço' ?></td><td class="num"><?= $qtd($it['quantidade']) ?></td><td class="num"><?= dinheiro($it['total']) ?></td>
          <td class="num"><?= $it['margem'] === null ? '—' : dinheiro($it['margem']) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="grade-2" style="margin-top:16px">
  <div class="card card--tabela">
    <div class="card__header"><span class="card__title">Produção por técnico</span></div>
    <?php if (!$tecnicos): ?><div class="vazio">Nada no período.</div><?php else: ?>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Técnico</th><th class="num">OS</th><th class="num">Serviços</th><th class="num">Comissão</th></tr></thead>
        <tbody><?php foreach ($tecnicos as $t): ?><tr><td><?= e($t['tecnico']) ?></td><td class="num"><?= (int) $t['os'] ?></td><td class="num"><?= dinheiro($t['servicos']) ?></td><td class="num"><?= dinheiro($t['comissao']) ?></td></tr><?php endforeach; ?></tbody>
      </table></div>
    <?php endif; ?>
  </div>
  <div class="card card--tabela">
    <div class="card__header"><span class="card__title">Clientes que mais compraram</span></div>
    <?php if (!$clientes): ?><div class="vazio">Nada no período.</div><?php else: ?>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Cliente</th><th class="num">OS</th><th class="num">Total</th></tr></thead>
        <tbody><?php foreach ($clientes as $c): ?><tr data-href="./cliente?id=<?= (int) $c['id'] ?>"><td><a href="./cliente?id=<?= (int) $c['id'] ?>"><?= e($c['nome']) ?></a></td><td class="num"><?= (int) $c['os'] ?></td><td class="num"><?= dinheiro($c['total']) ?></td></tr><?php endforeach; ?></tbody>
      </table></div>
    <?php endif; ?>
  </div>
</div>

<div class="card card--tabela" style="margin-top:16px">
  <div class="card__header"><span class="card__title">Estoque hoje · <?= (int) $estoque['pecas'] ?> peça(s) · <?= dinheiro($estoque['valor_custo']) ?> pelo custo · <?= dinheiro($estoque['valor_venda']) ?> pelo preço de venda</span></div>
  <?php if (!$estoque['abaixo']): ?><div class="vazio">Nenhuma peça no mínimo ou abaixo dele.</div><?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Peça no mínimo ou abaixo</th><th class="num">Saldo</th><th class="num">Mínimo</th></tr></thead>
      <tbody><?php foreach ($estoque['abaixo'] as $p): ?><tr data-href="./produto?id=<?= (int) $p['id'] ?>"><td><a href="./produto?id=<?= (int) $p['id'] ?>"><?= e($p['nome']) ?></a></td><td class="num"><?= $qtd($p['estoque_atual']) ?></td><td class="num"><?= $qtd($p['estoque_minimo']) ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
  <?php endif; ?>
</div>
