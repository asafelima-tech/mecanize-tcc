<?php
/**
 * Consulta de uma sessão de caixa, no formato das outras fichas: identificação no topo (situação, quem abriu e
 * fechou), os números da sessão (abertura, entradas, saídas e o dinheiro esperado ou contado), os movimentos
 * e, ao lado, os totais por forma e as observações. Os valores vêm do mesmo resumo de antes.
 * Espera: $caixa, $resumo, $movimentos
 */
$titulo = 'Caixa de ' . data_br($caixa['aberto_em']);
$fechado = $caixa['status'] === 'FECHADO';
$entradas = 0.0; $saidas = 0.0;
foreach ($resumo['por_forma'] as $x) { $entradas += $x['ENTRADA'] ?? 0; $saidas += $x['SAIDA'] ?? 0; }
$dif = $fechado ? (float) $caixa['valor_fechamento'] - (float) $caixa['valor_esperado'] : 0.0;
?>
<header class="ver-topo">
  <div class="ver-topo__id">
    <span class="ver-topo__avatar ver-topo__avatar--quadrado" aria-hidden="true"><?= icone('cash', 30, 1.5) ?></span>
    <div class="ver-topo__texto">
      <p class="ver-topo__voltar"><a href="./caixas"><?= icone('chevron-left', 14, 2) ?> Sessões de caixa</a></p>
      <h1>Caixa de <?= data_br($caixa['aberto_em']) ?></h1>
      <p class="ver-topo__meta">
        <span class="ver-chip<?= $fechado ? '' : ' ver-chip--escuro' ?>"><?= $fechado ? 'Fechado' : 'Aberto' ?></span>
        <span class="ver-chip"><?= icone('clock', 13) ?> Aberto por <?= e($caixa['aberto_por_nome'] ?? '—') ?> às <?= date('H:i', strtotime($caixa['aberto_em'])) ?></span>
        <?php if ($fechado): ?><span class="ver-chip"><?= icone('lock', 13) ?> Fechado por <?= e($caixa['fechado_por_nome'] ?? '—') ?> em <?= data_br($caixa['fechado_em'], true) ?></span><?php endif; ?>
      </p>
    </div>
  </div>
  <?php if (!$fechado): ?><div class="ver-topo__acoes"><a class="btn btn--primary btn--sm" href="./caixa"><?= icone('cash') ?> Ir para o caixa atual</a></div><?php endif; ?>
</header>

<div class="ver-numeros" aria-label="Números da sessão">
  <div><span>Abertura</span><strong><?= dinheiro($caixa['valor_abertura']) ?></strong><small>troco inicial em dinheiro</small></div>
  <div><span>Entradas</span><strong><?= dinheiro($entradas) ?></strong><small>todas as formas</small></div>
  <div><span>Saídas</span><strong><?= dinheiro($saidas) ?></strong><small>todas as formas</small></div>
  <?php if ($fechado): ?>
    <div><span>Contado no fechamento</span><strong><?= dinheiro($caixa['valor_fechamento']) ?></strong>
      <small><?= abs($dif) >= 0.005 ? ($dif > 0 ? 'sobra de ' : 'falta de ') . dinheiro(abs($dif)) : 'conferido com o esperado' ?></small></div>
  <?php else: ?>
    <div><span>Dinheiro esperado</span><strong><?= dinheiro($resumo['esperado']) ?></strong><small>na gaveta agora</small></div>
  <?php endif; ?>
</div>

<div class="ver-grade">
  <div class="ver-principal">
    <section class="card card--tabela ver-historico" aria-labelledby="ver-movs">
      <header class="ver-historico__cabeca">
        <div><h2 id="ver-movs">Movimentos</h2><p><?= $movimentos ? count($movimentos) . ' movimento(s), do mais recente para o mais antigo.' : 'Recebimentos, pagamentos, suprimentos e sangrias da sessão.' ?></p></div>
      </header>
      <?php require __DIR__ . '/_movimentos.php'; ?>
    </section>
  </div>

  <aside class="card ver-lado" aria-labelledby="ver-formas">
    <header class="ver-lado__cabeca"><h2 id="ver-formas">Por forma</h2></header>
    <dl class="form-ficha__dados form-ficha__dados--lista caixa-formas">
      <div><dt>Abertura</dt><dd><?= dinheiro($caixa['valor_abertura']) ?></dd></div>
      <?php foreach (Lancamento::FORMAS as $k => $r): if (!isset($resumo['por_forma'][$k])) continue; $x = $resumo['por_forma'][$k]; ?>
        <div><dt><?= $r ?></dt><dd><?= isset($x['ENTRADA']) ? '+' . dinheiro($x['ENTRADA']) : '' ?><?= isset($x['ENTRADA'], $x['SAIDA']) ? ' · ' : '' ?><?= isset($x['SAIDA']) ? '−' . dinheiro($x['SAIDA']) : '' ?></dd></div>
      <?php endforeach; ?>
    </dl>
    <div class="resumo-preco">
      <span>Dinheiro esperado na gaveta</span>
      <strong><?= dinheiro($resumo['esperado']) ?></strong>
    </div>
    <?php if ($fechado): ?>
      <p class="caixa-conferencia">
        <?php if (abs($dif) >= 0.005): ?><span class="badge badge--<?= $dif > 0 ? 'neutral' : 'danger' ?>"><?= $dif > 0 ? 'Sobra' : 'Falta' ?> de <?= dinheiro(abs($dif)) ?></span>
        <?php else: ?><span class="badge badge--success">Conferido</span><?php endif; ?>
        <span>Contado: <?= dinheiro($caixa['valor_fechamento']) ?></span>
      </p>
    <?php endif; ?>
    <?php foreach (['observacao_abertura' => 'Na abertura', 'observacao_fechamento' => 'No fechamento'] as $k => $r): if ($caixa[$k]): ?>
      <div class="ver-lado__bloco">
        <h3 class="ver-grupo__titulo"><?= icone('file', 15) ?> Observação · <?= $r ?></h3>
        <p class="ver-lado__texto"><?= e($caixa[$k]) ?></p>
      </div>
    <?php endif; endforeach; ?>
  </aside>
</div>
