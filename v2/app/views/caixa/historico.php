<?php /** Espera: $caixas, $p */ $titulo = 'Sessões de caixa'; ?>
<div class="page-header">
  <div><h1 class="page-header__title">Sessões de caixa</h1><p class="page-header__subtitle"><a href="./caixa">Caixa</a> · aberturas e fechamentos</p></div>
</div>
<div class="card card--tabela">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Abertura</th><th>Fechamento</th><th class="num">Abertura</th><th class="num">Esperado</th><th class="num">Contado</th><th>Conferência</th></tr></thead>
    <tbody>
      <?php foreach ($caixas as $c): $dif = $c['status'] === 'FECHADO' && $c['valor_esperado'] !== null ? (float) $c['valor_fechamento'] - (float) $c['valor_esperado'] : null; ?>
        <tr data-href="./caixa-ver?id=<?= (int) $c['id'] ?>">
          <td><a href="./caixa-ver?id=<?= (int) $c['id'] ?>"><?= data_br($c['aberto_em'], true) ?></a><span class="sub"><?= e($c['aberto_por_nome'] ?? '') ?></span></td>
          <td><?= $c['status'] === 'ABERTO' ? '<span class="badge badge--success">Aberto</span>' : data_br($c['fechado_em'], true) . '<span class="sub">' . e($c['fechado_por_nome'] ?? '') . '</span>' ?></td>
          <td class="num"><?= dinheiro($c['valor_abertura']) ?></td>
          <td class="num"><?= $c['valor_esperado'] !== null ? dinheiro($c['valor_esperado']) : '—' ?></td>
          <td class="num"><?= $c['valor_fechamento'] !== null ? dinheiro($c['valor_fechamento']) : '—' ?></td>
          <td><?php if ($dif === null): ?>—<?php elseif (abs($dif) < 0.005): ?><span class="badge badge--success">conferido</span><?php else: ?><span class="badge badge--<?= $dif > 0 ? 'info' : 'danger' ?>"><?= $dif > 0 ? 'sobra ' : 'falta ' ?><?= dinheiro(abs($dif)) ?></span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php require V2_RAIZ . '/app/views/layout/_paginacao.php'; ?>
</div>
