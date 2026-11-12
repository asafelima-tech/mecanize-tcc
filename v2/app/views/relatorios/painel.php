<?php
/**
 * Relatórios: leitura do período escolhido, de cima para baixo, do resumo ao detalhe.
 *   1. Resultado do período — faturamento (com a divisão serviços/peças), OS entregues, ticket médio
 *      e peças em baixa, comparados ao período anterior de mesma duração.
 *   2. Faturamento no tempo e as ordens de serviço do período (aprovação, situação, cancelamentos, agenda).
 *   3. Dinheiro no período — entradas por forma, saídas por categoria e o resultado (recebido − taxas − pago).
 *   4. Equipe e clientes — desempenho por técnico e clientes que mais compraram.
 *   5. Situação hoje — contas e OS em aberto (não dependem do período).
 *   6. Detalhamento — peças e serviços mais vendidos e o estoque de hoje.
 * Mesmos dados e filtros de antes; imprimir/salvar PDF pelo navegador.
 * Espera: $ini, $fim, $atalho, $faturamento, $tempo, $ordens, $situacao, $caixa, $contas, $itens, $tecnicos, $clientes, $estoque, $agenda
 */
$titulo = 'Relatórios';
$fat = $faturamento;
$serie = $tempo['serie'];
$max = max(array_merge([0.0], array_column($serie, 'total')));
$qtd = fn($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
$pct = fn(?float $v) => $v === null ? '—' : number_format($v, 1, ',', '.') . '%';
$abertasHoje = array_sum($ordens['abertas_hoje']);
$variacao = function (float $atual, float $antes): ?array {
    if ($antes <= 0) {
        return null;
    }
    $v = ($atual - $antes) / $antes * 100;

    return [($v >= 0 ? '+' : '−') . number_format(abs($v), 0, ',', '.') . '%', $v >= 0];
};
$chip = function (?array $v): string {
    if (!$v) return '';
    return '<span class="rel-delta' . ($v[1] ? '' : ' rel-delta--baixa') . '">' . icone($v[1] ? 'trend-up' : 'trend-down', 12, 2) . e($v[0]) . '</span>';
};
$iniciais = function (string $nome): string {
    $p = preg_split('/\s+/', trim($nome));
    return mb_strtoupper(mb_substr($p[0], 0, 1) . (count($p) > 1 ? mb_substr(end($p), 0, 1) : ''));
};
$passo = $max > 0 ? (float) pow(10, floor(log10($max / 4))) : 1.0;
foreach ([1, 2, 2.5, 5, 10] as $m) {
    if ($passo * $m * 4 >= $max) { $passo *= $m; break; }
}
$topo = max($passo * 4, 1);
$escala = fn (float $v): string => $v >= 1000 ? number_format($v / 1000, fmod($v, 1000) ? 1 : 0, ',', '.') . ' mil' : number_format($v, 0, ',', '.');
$maxSituacao = max(array_merge([1], array_values($situacao)));
$maxCliente = max(array_merge([1.0], array_map('floatval', array_column($clientes, 'total'))));
$porForma = $caixa['por_forma'];
$composicao = $fat['servicos'] + $fat['pecas'];
$totalItens = array_sum(array_map('floatval', array_column($itens, 'total')));
$periodoCurto = data_br($ini) . ' a ' . data_br($fim);
$hoje = date('d/m/Y');
$picoIndice = $max > 0 ? array_search($max, array_column($serie, 'total'), true) : null;
?>
<div class="page-header rel-cabecalho">
  <div>
    <h1 class="page-header__title">Relatórios</h1>
    <p class="page-header__subtitle">Desempenho financeiro, operacional e de estoque da oficina.</p>
  </div>
  <div class="page-header__actions nao-imprimir">
    <button type="button" class="btn btn--primary" onclick="window.print()"><?= icone('print') ?> Imprimir / salvar PDF</button>
  </div>
</div>

<form class="rel-filtros nao-imprimir" method="get" action="./relatorios">
  <nav class="abas abas--compactas" aria-label="Período">
    <?php foreach (RelatoriosController::ATALHOS as $k => $r): ?><a href="./relatorios?p=<?= e($k) ?>"<?= $atalho === (string) $k ? ' class="ativa" aria-current="page"' : '' ?>><?= e($r) ?></a><?php endforeach; ?>
  </nav>
  <span class="rel-filtros__sep" aria-hidden="true"></span>
  <div class="rel-filtros__datas">
    <label class="campo-periodo"><?= icone('calendar') ?>
      <input type="date" name="ini" value="<?= e($ini) ?>" aria-label="De"><span>até</span><input type="date" name="fim" value="<?= e($fim) ?>" aria-label="Até">
    </label>
    <button class="btn btn--ghost">Aplicar</button>
  </div>
</form>
<p class="rel-impresso">Período: <?= $periodoCurto ?> · emitido em <?= $hoje ?></p>

<!-- 1. Resultado do período -->
<section class="rel-secao" aria-labelledby="rel-resultado">
  <header class="rel-secao__cabeca"><h2 id="rel-resultado">Resultado do período</h2><span><?= $periodoCurto ?> · comparado ao período anterior de mesma duração</span></header>
  <div class="card rel-kpis">
    <div class="rel-kpi rel-kpi--principal">
      <div class="rel-kpi__topo"><span>Faturamento total</span><?= $chip($variacao($fat['total'], $fat['anterior'])) ?></div>
      <strong class="rel-kpi__valor"><?= dinheiro($fat['total']) ?></strong>
      <span class="rel-kpi__pe">vs. <?= dinheiro($fat['anterior']) ?> no período anterior</span>
      <?php if ($composicao > 0): $ps = $fat['servicos'] / $composicao * 100; ?>
        <div class="rel-composicao" role="img" aria-label="Serviços <?= e(dinheiro($fat['servicos'])) ?>, peças <?= e(dinheiro($fat['pecas'])) ?>">
          <span class="rel-composicao__barra"><span style="width:<?= round($ps, 1) ?>%"></span></span>
          <span class="rel-composicao__legenda"><span><i class="rel-ponto"></i>Serviços <b><?= dinheiro($fat['servicos']) ?></b></span><span><i class="rel-ponto rel-ponto--claro"></i>Peças <b><?= dinheiro($fat['pecas']) ?></b></span></span>
        </div>
      <?php else: ?>
        <span class="rel-kpi__pe">Serviços <?= dinheiro($fat['servicos']) ?> · peças <?= dinheiro($fat['pecas']) ?></span>
      <?php endif; ?>
    </div>
    <div class="rel-kpi">
      <div class="rel-kpi__topo"><span>OS entregues</span><?= $chip($variacao($fat['os'], $fat['os_anterior'])) ?></div>
      <strong class="rel-kpi__valor"><?= (int) $fat['os'] ?></strong>
      <span class="rel-kpi__pe">vs. <?= (int) $fat['os_anterior'] ?> no período anterior</span>
    </div>
    <div class="rel-kpi">
      <div class="rel-kpi__topo"><span>Ticket médio</span><?= $chip($variacao($fat['ticket'], $fat['ticket_anterior'])) ?></div>
      <strong class="rel-kpi__valor"><?= dinheiro($fat['ticket']) ?></strong>
      <span class="rel-kpi__pe">vs. <?= dinheiro($fat['ticket_anterior']) ?> no período anterior</span>
    </div>
    <a class="rel-kpi rel-kpi--link" href="./produtos?situacao=baixo">
      <div class="rel-kpi__topo"><span>Produtos em baixa</span><span class="rel-tag">hoje</span></div>
      <strong class="rel-kpi__valor"><?= (int) $estoque['abaixo_n'] ?></strong>
      <span class="rel-kpi__pe">no estoque mínimo ou abaixo <?= icone('chevron-right', 12, 2) ?></span>
    </a>
  </div>
</section>

<!-- 2. Faturamento no tempo + ordens do período -->
<div class="rel-grade rel-grade--grafico">
  <section class="card rel-cartao" aria-labelledby="rel-tempo">
    <header class="rel-cartao__cabeca">
      <div><h3 id="rel-tempo">Faturamento <?= $tempo['por_mes'] ? 'por mês' : 'por dia' ?></h3><p>OS entregues no período, pelo valor total.</p></div>
      <span class="etiqueta"><?= $tempo['por_mes'] ? 'Mensal' : 'Diário' ?></span>
    </header>
    <?php if ($max <= 0): ?>
      <div class="vazio"><?= icone('chart', 32, 1.25) ?>Nenhuma OS entregue no período.</div>
    <?php else: ?>
      <div class="barras rel-barras" role="img" aria-label="Faturamento <?= $tempo['por_mes'] ? 'por mês' : 'por dia' ?>, máximo de <?= e(dinheiro($max)) ?>">
        <div class="barras__escala"><?php for ($i = 4; $i >= 0; $i--): ?><span><?= $escala($topo / 4 * $i) ?></span><?php endfor; ?></div>
        <div class="barras__area">
          <?php for ($i = 0; $i < 5; $i++): ?><span class="barras__grade" style="bottom:calc(18px + (100% - 18px) * <?= $i / 4 ?>)"></span><?php endfor; ?>
          <?php $rotular = max(1, (int) ceil(count($serie) / 12)); foreach ($serie as $i => $s): ?>
            <div class="barras__coluna<?= $i === $picoIndice ? ' is-pico' : '' ?>" title="<?= e($s['rotulo'] . ' · ' . dinheiro($s['total']) . ' · ' . (int) $s['os'] . ' OS') ?>">
              <span class="barras__barra" style="height:<?= round($s['total'] / $topo * 100, 1) ?>%"><?php if ($i === $picoIndice): ?><span class="rel-pico"><?= e(dinheiro($s['total'])) ?></span><?php endif; ?></span>
              <?php if ($i % $rotular === 0): ?><span class="barras__rotulo"><?= e($s['rotulo']) ?></span><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <details class="rel-tabela-oculta nao-imprimir"><summary><?= icone('chevron-down', 14, 2) ?> Ver em tabela</summary>
        <div class="table-wrap"><table class="table"><thead><tr><th><?= $tempo['por_mes'] ? 'Mês' : 'Dia' ?></th><th class="num">OS</th><th class="num">Faturado</th></tr></thead><tbody>
          <?php foreach ($serie as $s): if ($s['os'] === 0) continue; ?><tr><td><?= e($s['rotulo']) ?></td><td class="num"><?= (int) $s['os'] ?></td><td class="num"><?= dinheiro($s['total']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      </details>
    <?php endif; ?>
  </section>

  <section class="card rel-cartao" aria-labelledby="rel-os">
    <header class="rel-cartao__cabeca"><div><h3 id="rel-os">Ordens de serviço no período</h3><p>Abertas no período, pela situação de agora.</p></div></header>
    <div class="rel-aprovacao">
      <strong><?= $pct($ordens['aprovacao']) ?></strong>
      <span>de aprovação de orçamentos<br><b><?= (int) $ordens['aprovadas'] ?></b> aprovado(s) · <b><?= (int) $ordens['recusadas'] ?></b> recusado(s)</span>
    </div>
    <ul class="ranking rel-ranking">
      <?php foreach ($situacao as $rotulo => $n): ?>
        <li><span class="ranking__nome"><?= e($rotulo) ?></span><b><?= (int) $n ?></b>
          <span class="ranking__trilho"><span style="width:<?= round($n / $maxSituacao * 100, 1) ?>%"></span></span></li>
      <?php endforeach; ?>
    </ul>
    <dl class="rel-mini">
      <div><dt>OS canceladas no período</dt><dd><?= (int) $ordens['canceladas'] ?></dd></div>
      <div><dt>Atendimentos na agenda</dt><dd><?= (int) $agenda['CONCLUIDO'] ?></dd></div>
      <div><dt>Faltas</dt><dd><?= (int) $agenda['FALTOU'] ?></dd></div>
      <div><dt>Agendamentos cancelados</dt><dd><?= (int) $agenda['CANCELADO'] ?></dd></div>
    </dl>
  </section>
</div>

<!-- 3. Dinheiro no período -->
<section class="rel-secao" aria-labelledby="rel-dinheiro">
  <header class="rel-secao__cabeca"><h2 id="rel-dinheiro">Dinheiro no período</h2><span>Recebimentos e pagamentos registrados, estornos descontados</span></header>
  <div class="card rel-dinheiro">
    <div class="rel-dinheiro__col">
      <div class="rel-dinheiro__topo"><h3>Entradas por forma de pagamento</h3><b><?= dinheiro($caixa['recebido']) ?></b></div>
      <?php if (!$porForma): ?><p class="form-dica">Nada recebido no período.</p><?php endif; ?>
      <ul class="barras-lista">
        <?php foreach ($porForma as $forma => $v): ?>
          <li><span class="barras-lista__nome"><?= e(Lancamento::FORMAS[$forma] ?? $forma) ?></span>
            <span class="ranking__trilho"><span style="width:<?= $caixa['recebido'] > 0 ? round(max(0, $v) / $caixa['recebido'] * 100, 1) : 0 ?>%"></span></span>
            <b><?= dinheiro($v) ?></b><span class="secundario"><?= $caixa['recebido'] > 0 ? $pct($v / $caixa['recebido'] * 100) : '' ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="rel-dinheiro__col">
      <div class="rel-dinheiro__topo"><h3>Saídas por categoria</h3><b><?= dinheiro($caixa['pago']) ?></b></div>
      <?php if (!$caixa['despesas']): ?><p class="form-dica">Nada pago no período.</p><?php endif; ?>
      <ul class="barras-lista">
        <?php foreach ($caixa['despesas'] as $cat => $v): ?>
          <li><span class="barras-lista__nome"><?= e(Lancamento::categoria('PAGAR', $cat)) ?></span>
            <span class="ranking__trilho"><span style="width:<?= $caixa['pago'] > 0 ? round(max(0, $v) / $caixa['pago'] * 100, 1) : 0 ?>%"></span></span>
            <b><?= dinheiro($v) ?></b><span class="secundario"><?= $caixa['pago'] > 0 ? $pct($v / $caixa['pago'] * 100) : '' ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="rel-dinheiro__col rel-extrato">
      <h3>Resultado</h3>
      <dl>
        <div><dt>Recebido</dt><dd><?= dinheiro($caixa['recebido']) ?></dd></div>
        <div><dt>Taxas</dt><dd>− <?= dinheiro($caixa['taxas']) ?></dd></div>
        <div><dt>Pago</dt><dd>− <?= dinheiro($caixa['pago']) ?></dd></div>
        <div class="rel-extrato__total"><dt>Saldo<small>recebido − taxas − pago</small></dt><dd><?= dinheiro($caixa['saldo']) ?></dd></div>
      </dl>
    </div>
  </div>
</section>

<!-- 4. Equipe e clientes -->
<div class="rel-grade rel-grade--equipe">
  <section class="card card--tabela rel-cartao rel-cartao--tabela" aria-labelledby="rel-tecnicos">
    <header class="rel-cartao__cabeca"><div><h3 id="rel-tecnicos">Desempenho por técnico</h3><p>OS entregues no período por responsável.</p></div></header>
    <?php if (!$tecnicos): ?><div class="vazio">Nada no período.</div><?php else: ?>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Técnico</th><th class="num">OS</th><th class="num">Vendido</th><th class="num">Comissão</th><th class="num">Tempo médio / OS</th><th>Prontas no prazo</th></tr></thead>
        <tbody><?php foreach ($tecnicos as $t): $prazo = $t['com_previsao'] > 0 ? $t['no_prazo'] / $t['com_previsao'] * 100 : null; ?>
          <tr>
            <td><span class="celula-pessoa"><span class="avatar"><?= e($iniciais($t['tecnico'])) ?></span><?= e($t['tecnico']) ?></span></td>
            <td class="num"><strong><?= (int) $t['os'] ?></strong></td>
            <td class="num"><strong><?= dinheiro($t['total']) ?></strong></td>
            <td class="num"><?= dinheiro($t['comissao']) ?></td>
            <td class="num"><?= $t['dias'] === null ? '—' : number_format($t['dias'], 1, ',', '.') . ' dias' ?></td>
            <td><?php if ($prazo === null): ?><span class="secundario">sem previsão</span><?php else: ?><span class="prazo"><span class="ranking__trilho"><span style="width:<?= round($prazo) ?>%"></span></span><b><?= number_format($prazo, 0) ?>%</b></span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?></tbody>
      </table></div>
    <?php endif; ?>
  </section>

  <section class="card rel-cartao" aria-labelledby="rel-clientes">
    <header class="rel-cartao__cabeca"><div><h3 id="rel-clientes">Clientes que mais compraram</h3><p>Pelo valor das OS entregues.</p></div><a class="link-forte nao-imprimir" href="./clientes">Ver clientes<?= icone('chevron-right', 12, 2) ?></a></header>
    <?php if (!$clientes): ?><p class="form-dica">Nada no período.</p><?php endif; ?>
    <ol class="ranking-pessoas rel-clientes">
      <?php foreach (array_slice($clientes, 0, 5) as $pos => $c): ?>
        <li><span class="rel-posicao"><?= $pos + 1 ?></span>
          <span class="ranking-pessoas__meio"><a href="./cliente?id=<?= (int) $c['id'] ?>" class="link-discreto"><?= e($c['nome']) ?></a>
            <span class="ranking__trilho"><span style="width:<?= round((float) $c['total'] / $maxCliente * 100, 1) ?>%"></span></span></span>
          <b><?= dinheiro($c['total']) ?><small><?= (int) $c['os'] ?> OS</small></b></li>
      <?php endforeach; ?>
    </ol>
  </section>
</div>

<!-- 5. Situação hoje -->
<section class="rel-secao" aria-labelledby="rel-hoje">
  <header class="rel-secao__cabeca"><h2 id="rel-hoje">Situação hoje</h2><span><?= $hoje ?> · não depende do período escolhido</span></header>
  <div class="card rel-hoje">
    <div class="rel-hoje__bloco">
      <div class="rel-dinheiro__topo"><h3>Contas em aberto</h3><a href="./financeiro?tipo=RECEBER&amp;situacao=vencidos" class="link-forte nao-imprimir">Ver vencidas<?= icone('chevron-right', 12, 2) ?></a></div>
      <div class="rel-numeros">
        <div><span>A receber</span><strong><?= dinheiro($contas['RECEBER']['saldo']) ?></strong><small><?= (int) $contas['RECEBER']['n'] ?> conta(s)</small></div>
        <div><span>Vencido a receber</span><strong><?= dinheiro($contas['RECEBER']['vencido']) ?></strong><small><?= (int) $contas['RECEBER']['n_vencidas'] ?> conta(s)</small></div>
        <div><span>A pagar</span><strong><?= dinheiro($contas['PAGAR']['saldo']) ?></strong><small><?= (int) $contas['PAGAR']['n'] ?> conta(s)</small></div>
        <div><span>Vencido a pagar</span><strong><?= dinheiro($contas['PAGAR']['vencido']) ?></strong><small><?= (int) $contas['PAGAR']['n_vencidas'] ?> conta(s)</small></div>
      </div>
    </div>
    <div class="rel-hoje__bloco">
      <div class="rel-dinheiro__topo"><h3>Ordens de serviço em aberto <span class="secundario">· <?= (int) $abertasHoje ?></span></h3><a href="./ordens" class="link-forte nao-imprimir">Ver todas<?= icone('chevron-right', 12, 2) ?></a></div>
      <?php if (!$abertasHoje): ?><p class="form-dica">Nenhuma OS em aberto.</p><?php else: ?>
        <div class="rel-numeros rel-numeros--os">
          <?php foreach ($ordens['abertas_hoje'] as $st => $n): ?><div><span><?= e(os_status($st)[0]) ?></span><strong><?= (int) $n ?></strong></div><?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- 6. Detalhamento -->
<section class="rel-secao" aria-labelledby="rel-detalhe">
  <header class="rel-secao__cabeca"><h2 id="rel-detalhe">Detalhamento</h2><span>Itens das OS entregues no período e estoque de hoje</span></header>
  <div class="rel-grade rel-grade--detalhe">
    <section class="card card--tabela rel-cartao rel-cartao--tabela" aria-labelledby="rel-itens">
      <header class="rel-cartao__cabeca"><div><h3 id="rel-itens">Peças e serviços mais vendidos</h3><p>A barra mostra a fatia de cada item no total vendido.</p></div></header>
      <?php if (!$itens): ?><div class="vazio">Nenhuma OS entregue no período.</div><?php else: ?>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Item</th><th>Tipo</th><th class="num">Quantidade</th><th class="num">Vendido</th><th class="num">Margem das peças</th></tr></thead>
          <tbody><?php foreach ($itens as $it): ?>
            <tr><td><span class="rel-item"><?= e($it['descricao']) ?><?php if ($totalItens > 0): ?><span class="rel-item__barra"><span style="width:<?= round((float) $it['total'] / $totalItens * 100, 1) ?>%"></span></span><?php endif; ?></span></td>
              <td><span class="etiqueta<?= $it['tipo'] === 'PECA' ? '' : ' etiqueta--contorno' ?>"><?= $it['tipo'] === 'PECA' ? 'Peça' : 'Serviço' ?></span></td>
              <td class="num"><?= $qtd($it['quantidade']) ?></td><td class="num"><strong><?= dinheiro($it['total']) ?></strong></td>
              <td class="num"><?= $it['margem'] === null ? '—' : dinheiro($it['margem']) ?></td></tr>
          <?php endforeach; ?></tbody>
        </table></div>
      <?php endif; ?>
    </section>

    <section class="card card--tabela rel-cartao rel-cartao--tabela" aria-labelledby="rel-estoque">
      <header class="rel-cartao__cabeca"><div><h3 id="rel-estoque">Estoque hoje</h3><p><?= (int) $estoque['pecas'] ?> peça(s) cadastrada(s).</p></div></header>
      <div class="rel-numeros rel-numeros--estoque">
        <div><span>Valor pelo custo</span><strong><?= dinheiro($estoque['valor_custo']) ?></strong></div>
        <div><span>Valor pelo preço de venda</span><strong><?= dinheiro($estoque['valor_venda']) ?></strong></div>
      </div>
      <?php if (!$estoque['abaixo']): ?><div class="vazio">Nenhuma peça no mínimo ou abaixo dele.</div><?php else: ?>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Peça no mínimo ou abaixo</th><th class="num">Saldo</th><th class="num">Mínimo</th></tr></thead>
          <tbody><?php foreach ($estoque['abaixo'] as $p): ?><tr data-href="./produto?id=<?= (int) $p['id'] ?>"><td><a href="./produto?id=<?= (int) $p['id'] ?>"><?= e($p['nome']) ?></a></td><td class="num"><strong><?= $qtd($p['estoque_atual']) ?></strong></td><td class="num secundario"><?= $qtd($p['estoque_minimo']) ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
      <?php endif; ?>
    </section>
  </div>
</section>
