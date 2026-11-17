<?php
/**
 * Tabela de sessões de caixa (histórico e últimas sessões no caixa atual). Espera: $caixas (Caixa::historico)
 * Quem abriu e quando, quem fechou e quando, e o fechamento calculado: saldo anterior, entradas e saídas
 * e o saldo final (com o dinheiro que ficou na gaveta).
 */
?>
<div class="table-wrap"><table class="table tabela-sessoes">
  <thead><tr><th>Sessão</th><th>Abertura</th><th>Fechamento</th><th class="num">Saldo anterior</th><th class="num">Entradas e saídas</th><th class="num">Saldo final</th></tr></thead>
  <tbody>
    <?php foreach ($caixas as $c): $aberto = $c['status'] === 'ABERTO'; ?>
      <tr data-href="./caixa-ver?id=<?= (int) $c['id'] ?>">
        <td>
          <span class="tabela-sessoes__id">
            <a href="./caixa-ver?id=<?= (int) $c['id'] ?>"><strong class="tabular">#<?= (int) $c['id'] ?></strong></a>
            <?= $aberto ? '<span class="badge badge--success">Aberto</span>' : '<span class="badge badge--neutral">Fechado</span>' ?>
          </span>
        </td>
        <td><?= e($c['aberto_por_nome'] ?? '—') ?><span class="sub tabular"><?= date('d/m/Y H:i', strtotime($c['aberto_em'])) ?></span></td>
        <td><?php if ($aberto): ?><span class="secundario">Em uso</span><?php else: ?><?= e($c['fechado_por_nome'] ?? '—') ?><span class="sub tabular"><?= $c['fechado_em'] ? date('d/m/Y H:i', strtotime($c['fechado_em'])) : '—' ?></span><?php endif; ?></td>
        <td class="num tabular"><?= dinheiro($c['valor_abertura']) ?><span class="sub">em dinheiro</span></td>
        <td class="num tabular">+ <?= dinheiro($c['entradas']) ?><span class="sub">− <?= dinheiro($c['saidas']) ?></span></td>
        <td class="num tabular"><strong><?= ($c['saldo_final'] < 0 ? '− ' : '') . dinheiro(abs($c['saldo_final'])) ?></strong><span class="sub"><?= $aberto ? 'até agora' : dinheiro($c['dinheiro']) . ' na gaveta' ?></span></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table></div>
