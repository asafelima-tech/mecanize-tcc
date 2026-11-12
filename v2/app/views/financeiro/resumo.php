<?php
/**
 * Financeiro · visão geral (Design System, tela 16): dinheiro que entrou e saiu no período,
 * evolução, entradas por forma, saídas por categoria e os pagamentos e recebimentos.
 * Espera: $agrupar, $de, $ate, $busca, $caixa, $anterior, $sessoes, $fluxo, $maiorSaida, $movimentos, $p, $abertas
 */
$titulo = 'Financeiro';
$meses = ['', 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$base = ['agrupar' => $agrupar, 'de' => $de->format('Y-m-d'), 'ate' => $ate->format('Y-m-d')];
$variacao = function (float $atual, float $antes): ?string {
    if (abs($antes) < 0.005) {
        return null;
    }
    $v = ($atual - $antes) / abs($antes) * 100;

    return ($v >= 0 ? '+' : '−') . number_format(abs($v), 0, ',', '.') . '%';
};
$resultado = $caixa['recebido'] - $caixa['taxas'] - $caixa['pago'];
$resultadoAntes = $anterior['recebido'] - $anterior['taxas'] - $anterior['pago'];
$periodoCurto = $de->format('Y') === $ate->format('Y')
    ? $de->format('d/m') . ' – ' . $ate->format('d/m/Y')
    : $de->format('d/m/Y') . ' – ' . $ate->format('d/m/Y');

$maxFluxo = max(array_merge([0.0], array_column($fluxo, 'entradas'), array_column($fluxo, 'saidas')));
$passo = $maxFluxo > 0 ? (float) pow(10, floor(log10($maxFluxo / 4))) : 1.0;
foreach ([1, 2, 2.5, 5, 10] as $m) {
    if ($passo * $m * 4 >= $maxFluxo) { $passo *= $m; break; }
}
$topo = max($passo * 4, 1);
$escala = fn (float $v): string => $v >= 1000 ? number_format($v / 1000, fmod($v, 1000) ? 1 : 0, ',', '.') . ' mil' : number_format($v, 0, ',', '.');
$iconeForma = ['DINHEIRO' => 'cash', 'PIX' => 'qr-code', 'CARTAO_DEBITO' => 'credit-card', 'CARTAO_CREDITO' => 'credit-card',
               'BOLETO' => 'barcode', 'TRANSFERENCIA' => 'bank', 'OUTRO' => 'more-h'];
$iconeCategoria = ['PECAS' => 'part', 'COMISSAO' => 'percent', 'SALARIO' => 'users', 'ALUGUEL' => 'home', 'CONTAS_CONSUMO' => 'receipt',
                   'IMPOSTOS' => 'file', 'FERRAMENTAS' => 'key', 'OUTRAS_DESPESAS' => 'more-h'];
$pct = fn (float $v, float $total): string => $total > 0 ? number_format($v / $total * 100, 0) . '%' : '—';
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Financeiro</h1>
    <p class="page-header__subtitle">Entradas, saídas e resultado da oficina por período.</p>
  </div>
  <div class="page-header__actions">
    <a class="btn btn--contorno" href="./caixas"><?= icone('history') ?> Histórico de caixa</a>
    <a class="btn btn--ghost" href="./comissoes"><?= icone('percent') ?> Comissões</a>
    <details class="menu-mais"><summary class="btn btn--primary"><?= icone('plus') ?> Nova conta<?= icone('chevron-down', 14) ?></summary>
      <div class="menu-mais__lista">
        <a href="./conta-form?tipo=RECEBER">Conta a receber</a>
        <a href="./conta-form?tipo=PAGAR">Conta a pagar</a>
      </div>
    </details>
  </div>
</div>

<nav class="abas abas--contagem" aria-label="Financeiro">
  <a href="./financeiro" class="ativa" aria-current="page">Visão geral</a>
  <a href="./financeiro?tipo=RECEBER">A receber<span class="abas__n"><?= (int) $abertas['RECEBER']['n'] ?></span></a>
  <a href="./financeiro?tipo=PAGAR">A pagar<span class="abas__n"><?= (int) $abertas['PAGAR']['n'] ?></span></a>
</nav>

<form class="barra-periodo" method="get" action="./financeiro">
  <input type="hidden" name="agrupar" value="<?= e($agrupar) ?>">
  <div class="barra-periodo__nav">
    <nav class="abas abas--compactas" aria-label="Agrupar por">
      <?php foreach (['dia' => 'Diário', 'semana' => 'Semanal', 'mes' => 'Mensal', 'ano' => 'Anual'] as $k => $r): ?>
        <a href="./financeiro?agrupar=<?= $k ?>"<?= $agrupar === $k ? ' class="ativa" aria-current="page"' : '' ?>><?= $r ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
  <div class="barra-periodo__filtros barra-periodo__filtros--largo">
    <label class="campo-periodo"><?= icone('calendar') ?>
      <input type="date" name="de" value="<?= e($de->format('Y-m-d')) ?>" aria-label="De">
      <span>–</span>
      <input type="date" name="ate" value="<?= e($ate->format('Y-m-d')) ?>" aria-label="Até">
    </label>
    <button class="btn btn--ghost" type="submit">Aplicar</button>
  </div>
</form>

<div class="stat-grid stat-grid--icone">
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Total de entradas</span><?php if ($v = $variacao($caixa['recebido'], $anterior['recebido'])): ?><span class="stat-card__chip" title="Comparado ao período anterior de mesmo tamanho"><?= $v ?></span><?php endif; ?></div>
    <span class="stat-card__value"><?= dinheiro($caixa['recebido']) ?></span>
    <span class="stat-card__pe"><?= e($periodoCurto) ?><?= $caixa['taxas'] > 0 ? ' · taxas ' . dinheiro($caixa['taxas']) : '' ?></span>
  </div>
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Total de saídas</span><?php if ($v = $variacao($caixa['pago'], $anterior['pago'])): ?><span class="stat-card__chip" title="Comparado ao período anterior de mesmo tamanho"><?= $v ?></span><?php endif; ?></div>
    <span class="stat-card__value"><?= dinheiro($caixa['pago']) ?></span>
    <span class="stat-card__pe">contas pagas no período</span>
  </div>
  <div class="stat-card stat-card--destaque">
    <div class="stat-card__topo"><span class="stat-card__label">Resultado do período</span><?php if ($v = $variacao($resultado, $resultadoAntes)): ?><span class="stat-card__chip" title="Comparado ao período anterior de mesmo tamanho"><?= $v ?></span><?php endif; ?></div>
    <span class="stat-card__value"><?= $resultado < 0 ? '−' : '' ?><?= dinheiro(abs($resultado)) ?></span>
    <span class="stat-card__pe">entradas − taxas − saídas</span>
  </div>
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Sessões de caixa</span><?= icone('cash') ?></div>
    <span class="stat-card__value"><?= (int) $sessoes['n'] ?></span>
    <span class="stat-card__pe"><?= (int) $sessoes['diferenca'] ? (int) $sessoes['diferenca'] . ' fechada(s) com sobra ou falta' : 'nenhuma diferença na gaveta' ?></span>
  </div>
</div>

<section class="card quadro card--espaco">
  <div class="quadro__topo">
    <h2 class="card__title">Evolução</h2>
    <div class="quadro__legenda"><span><span class="legenda-cor"></span>Entradas</span><span><span class="legenda-cor legenda-cor--clara"></span>Saídas</span></div>
  </div>
  <div class="barras barras--duplas" role="img" aria-label="Entradas e saídas: <?= e(implode('; ', array_map(fn ($b) => $b['rotulo'] . ' entradas ' . dinheiro($b['entradas']) . ', saídas ' . dinheiro($b['saidas']), $fluxo))) ?>">
    <div class="barras__escala"><?php for ($i = 4; $i >= 0; $i--): ?><span><?= $escala($topo / 4 * $i) ?></span><?php endfor; ?></div>
    <div class="barras__area">
      <?php for ($i = 0; $i < 5; $i++): ?><span class="barras__grade" style="bottom:calc(18px + (100% - 18px) * <?= $i / 4 ?>)"></span><?php endfor; ?>
      <?php $rotular = max(1, (int) ceil(count($fluxo) / 14)); $i = 0; foreach ($fluxo as $b): ?>
        <div class="barras__coluna" title="<?= e($b['rotulo'] . ' · entradas ' . dinheiro($b['entradas']) . ' · saídas ' . dinheiro($b['saidas'])) ?>">
          <span class="barras__par">
            <span class="barras__barra" style="height:<?= round($b['entradas'] / $topo * 100, 1) ?>%"></span>
            <span class="barras__barra barras__barra--clara" style="height:<?= round($b['saidas'] / $topo * 100, 1) ?>%"></span>
          </span>
          <?php if ($i++ % $rotular === 0): ?><span class="barras__rotulo"><?= e($b['rotulo']) ?></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<div class="grade-2 grade-2--espaco">
  <section class="card quadro">
    <div class="quadro__topo"><h2 class="card__title">Entradas por forma</h2><span class="etiqueta"><?= dinheiro($caixa['recebido']) ?></span></div>
    <?php if (!$caixa['por_forma']): ?><p class="form-dica">Nenhum recebimento no período.</p><?php endif; ?>
    <ul class="barras-lista">
      <?php foreach ($caixa['por_forma'] as $forma => $v): ?>
        <li><span class="barras-lista__nome"><?= icone($iconeForma[$forma] ?? 'coins') ?><?= e(Lancamento::FORMAS[$forma] ?? $forma) ?></span>
          <span class="ranking__trilho"><span style="width:<?= $caixa['recebido'] > 0 ? round(max(0, $v) / $caixa['recebido'] * 100, 1) : 0 ?>%"></span></span>
          <b><?= dinheiro($v) ?></b><span class="secundario"><?= $pct($v, $caixa['recebido']) ?></span></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <section class="card quadro">
    <div class="quadro__topo"><h2 class="card__title">Saídas por categoria</h2><span class="etiqueta"><?= dinheiro($caixa['pago']) ?></span></div>
    <?php if (!$caixa['despesas']): ?><p class="form-dica">Nenhum pagamento no período.</p><?php endif; ?>
    <ul class="barras-lista">
      <?php foreach ($caixa['despesas'] as $cat => $v): ?>
        <li><span class="barras-lista__nome"><?= icone($iconeCategoria[$cat] ?? 'receipt') ?><?= e(Lancamento::categoria('PAGAR', $cat)) ?></span>
          <span class="ranking__trilho"><span style="width:<?= $caixa['pago'] > 0 ? round(max(0, $v) / $caixa['pago'] * 100, 1) : 0 ?>%"></span></span>
          <b><?= dinheiro($v) ?></b><span class="secundario"><?= $pct($v, $caixa['pago']) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($maiorSaida): ?>
      <div class="linha-total"><span>Maior saída</span><b><?= e($maiorSaida['pessoa'] ?: $maiorSaida['descricao']) ?> · <?= dinheiro($maiorSaida['valor']) ?></b></div>
    <?php endif; ?>
  </section>
</div>

<div class="card card--tabela card--espaco">
  <div class="card__header">
    <span class="card__title">Lançamentos do período</span>
    <form method="get" action="./financeiro" class="filtros__busca filtros__busca--compacta">
      <?php foreach ($base as $k => $v): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endforeach; ?>
      <?= icone('search') ?><input type="search" name="busca" value="<?= e($busca) ?>" placeholder="Buscar lançamento" aria-label="Buscar lançamento">
    </form>
  </div>
  <?php if (!$movimentos): ?>
    <div class="vazio"><?= icone('wallet', 32, 1.25) ?>Nenhum pagamento ou recebimento <?= $busca !== '' ? 'com essa busca ' : '' ?>no período.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Data/hora</th><th>Tipo</th><th>Forma / categoria</th><th>Descrição</th><th>Registrado por</th><th class="num">Valor</th></tr></thead>
        <tbody>
          <?php foreach ($movimentos as $m): $entrada = $m['direcao'] === 'ENTRADA'; $estorno = $m['natureza'] === 'ESTORNO'; ?>
            <tr data-href="./conta?id=<?= (int) $m['lancamento_id'] ?>">
              <td class="secundario tabular"><?= date('d/m H:i', strtotime($m['data_movimento'])) ?></td>
              <td><span class="badge badge--plain badge--sinal"><?= $estorno ? icone('refresh', 12, 2) . 'Estorno' : ($entrada ? icone('plus', 12, 2) . 'Entrada' : icone('minus', 12, 2) . 'Saída') ?></span></td>
              <td><?= e($m['tipo'] === 'RECEBER' ? (Lancamento::FORMAS[$m['forma_pagamento']] ?? $m['forma_pagamento']) : Lancamento::categoria('PAGAR', $m['categoria'])) ?></td>
              <td><a href="./conta?id=<?= (int) $m['lancamento_id'] ?>"><?= e($m['descricao']) ?></a><?= $m['pessoa'] ? ' · ' . e($m['pessoa']) : '' ?></td>
              <td><?= e($m['usuario'] ?? '—') ?></td>
              <td class="num"><strong><?= $entrada ? '' : '− ' ?><?= dinheiro($m['valor']) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?php $rotuloItens = 'lançamentos'; require V2_RAIZ . '/app/views/layout/_rodape.php'; ?>
</div>
