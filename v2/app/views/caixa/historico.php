<?php /** Espera: $caixas, $p */ $titulo = 'Sessões de caixa'; ?>
<div class="page-header">
  <div><h1 class="page-header__title">Sessões de caixa</h1><p class="page-header__subtitle">Aberturas e fechamentos da gaveta, com a conferência de cada um.</p></div>
  <div class="page-header__actions"><a class="btn btn--contorno" href="./caixa"><?= icone('arrow-left') ?> Caixa atual</a></div>
</div>
<div class="card card--tabela">
  <?php if (!$caixas): ?><div class="vazio"><?= icone('cash', 32, 1.25) ?>Nenhuma sessão de caixa ainda.</div><?php else: require __DIR__ . '/_sessoes.php'; endif; ?>
  <?php $rotuloItens = 'sessões'; require V2_RAIZ . '/app/views/layout/_rodape.php'; ?>
</div>
