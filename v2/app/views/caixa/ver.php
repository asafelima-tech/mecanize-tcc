<?php /** Espera: $caixa, $resumo, $movimentos */ $titulo = 'Caixa de ' . data_br($caixa['aberto_em']); ?>
<div class="page-header">
  <div><h1 class="page-header__title">Caixa de <?= data_br($caixa['aberto_em']) ?></h1>
    <p class="page-header__subtitle"><a href="./caixas">Sessões de caixa</a> · aberto por <?= e($caixa['aberto_por_nome'] ?? '') ?> às <?= date('H:i', strtotime($caixa['aberto_em'])) ?>
      <?= $caixa['fechado_em'] ? ' · fechado por ' . e($caixa['fechado_por_nome'] ?? '') . ' em ' . data_br($caixa['fechado_em'], true) : '' ?></p></div>
</div>
<div class="ficha">
  <div class="card card--tabela"><div class="card__header"><span class="card__title">Movimentos</span></div><?php require __DIR__ . '/_movimentos.php'; ?></div>
  <div class="card"><?php require __DIR__ . '/_resumo.php'; ?>
    <?php foreach (['observacao_abertura' => 'Obs. da abertura', 'observacao_fechamento' => 'Obs. do fechamento'] as $k => $r): if ($caixa[$k]): ?><p class="form-dica" style="margin-top:10px"><?= $r ?>: <?= e($caixa[$k]) ?></p><?php endif; endforeach; ?></div>
</div>
