<?php /** Totais por forma. Espera: $resumo, $caixa */ ?>
<dl class="dados" style="grid-template-columns:1fr">
  <div><dt>Abertura</dt><dd><?= dinheiro($caixa['valor_abertura']) ?></dd></div>
  <?php foreach (Lancamento::FORMAS as $k => $r): if (!isset($resumo['por_forma'][$k])) continue; $x = $resumo['por_forma'][$k]; ?>
    <div><dt><?= $r ?></dt><dd><?= isset($x['ENTRADA']) ? '+' . dinheiro($x['ENTRADA']) : '' ?> <?= isset($x['SAIDA']) ? '−' . dinheiro($x['SAIDA']) : '' ?></dd></div>
  <?php endforeach; ?>
  <div><dt>Dinheiro esperado na gaveta</dt><dd><strong><?= dinheiro($resumo['esperado']) ?></strong></dd></div>
  <?php if ($caixa['status'] === 'FECHADO'): $dif = (float) $caixa['valor_fechamento'] - (float) $caixa['valor_esperado']; ?>
    <div><dt>Contado no fechamento</dt><dd><?= dinheiro($caixa['valor_fechamento']) ?>
      <?php if (abs($dif) >= 0.005): ?><span class="badge badge--<?= $dif > 0 ? 'info' : 'danger' ?>"><?= $dif > 0 ? 'sobra ' : 'falta ' ?><?= dinheiro(abs($dif)) ?></span><?php else: ?><span class="badge badge--success">conferido</span><?php endif; ?></dd></div>
  <?php endif; ?>
</dl>
