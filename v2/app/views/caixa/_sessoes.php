<?php /** Tabela de sessões de caixa (tela 15 do Design System). Espera: $caixas */ ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th>#</th><th>Situação</th><th>Aberto por</th><th>Abertura</th><th class="num">Valor abertura</th><th>Fechado por</th><th>Fechamento</th><th class="num">Valor fechamento</th><th class="num">Diferença</th></tr></thead>
  <tbody>
    <?php foreach ($caixas as $c): $dif = $c['status'] === 'FECHADO' && $c['valor_esperado'] !== null ? (float) $c['valor_fechamento'] - (float) $c['valor_esperado'] : null; ?>
      <tr data-href="./caixa-ver?id=<?= (int) $c['id'] ?>">
        <td><a href="./caixa-ver?id=<?= (int) $c['id'] ?>">#<?= (int) $c['id'] ?></a></td>
        <td><?= $c['status'] === 'ABERTO' ? '<span class="badge badge--success">Aberto</span>' : '<span class="badge badge--neutral">Fechado</span>' ?></td>
        <td><?= e($c['aberto_por_nome'] ?? '—') ?></td>
        <td class="secundario tabular"><?= date('d/m H:i', strtotime($c['aberto_em'])) ?></td>
        <td class="num"><?= dinheiro($c['valor_abertura']) ?></td>
        <td><?= e($c['fechado_por_nome'] ?? '—') ?></td>
        <td class="secundario tabular"><?= $c['fechado_em'] ? date('d/m H:i', strtotime($c['fechado_em'])) : '—' ?></td>
        <td class="num"><?= $c['valor_fechamento'] !== null ? dinheiro($c['valor_fechamento']) : '—' ?></td>
        <td class="num"><?php if ($dif === null): ?>—<?php elseif (abs($dif) < 0.005): ?><strong><?= dinheiro(0) ?></strong><?php else: ?><strong><?= $dif > 0 ? '+ ' : '− ' ?><?= dinheiro(abs($dif)) ?></strong> <span class="secundario"><?= $dif > 0 ? 'sobra' : 'falta' ?></span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table></div>
