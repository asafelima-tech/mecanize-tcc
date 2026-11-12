<?php
/**
 * Tabela de sessões de caixa (histórico e últimas sessões no caixa atual). Espera: $caixas
 * Cinco colunas em vez de nove: quem abriu e quando, quem fechou e quando, a gaveta (abertura → contado)
 * e a conferência (sobra, falta ou conferido). Os dados são os mesmos de antes.
 */
?>
<div class="table-wrap"><table class="table tabela-sessoes">
  <thead><tr><th>Sessão</th><th>Abertura</th><th>Fechamento</th><th class="num">Gaveta</th><th class="num">Conferência</th></tr></thead>
  <tbody>
    <?php foreach ($caixas as $c): $dif = $c['status'] === 'FECHADO' && $c['valor_esperado'] !== null ? (float) $c['valor_fechamento'] - (float) $c['valor_esperado'] : null; $aberto = $c['status'] === 'ABERTO'; ?>
      <tr data-href="./caixa-ver?id=<?= (int) $c['id'] ?>">
        <td>
          <span class="tabela-sessoes__id">
            <a href="./caixa-ver?id=<?= (int) $c['id'] ?>"><strong class="tabular">#<?= (int) $c['id'] ?></strong></a>
            <?= $aberto ? '<span class="badge badge--success">Aberto</span>' : '<span class="badge badge--neutral">Fechado</span>' ?>
          </span>
        </td>
        <td><?= e($c['aberto_por_nome'] ?? '—') ?><span class="sub tabular"><?= date('d/m/Y H:i', strtotime($c['aberto_em'])) ?></span></td>
        <td><?php if ($aberto): ?><span class="secundario">Em uso</span><?php else: ?><?= e($c['fechado_por_nome'] ?? '—') ?><span class="sub tabular"><?= $c['fechado_em'] ? date('d/m/Y H:i', strtotime($c['fechado_em'])) : '—' ?></span><?php endif; ?></td>
        <td class="num"><?= dinheiro($c['valor_abertura']) ?><span class="sub"><?= $c['valor_fechamento'] !== null ? 'contado ' . dinheiro($c['valor_fechamento']) : 'troco inicial' ?></span></td>
        <td class="num">
          <?php if ($dif === null): ?><span class="secundario">—</span>
          <?php elseif (abs($dif) < 0.005): ?><span class="tabela-sessoes__ok"><?= icone('check', 13, 2.25) ?> Conferido</span>
          <?php else: ?><strong><?= $dif > 0 ? '+ ' : '− ' ?><?= dinheiro(abs($dif)) ?></strong><span class="sub"><?= $dif > 0 ? 'sobra na gaveta' : 'falta na gaveta' ?></span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table></div>
