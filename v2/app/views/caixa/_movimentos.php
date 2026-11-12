<?php /** Tabela de movimentos de uma sessão de caixa. Espera: $movimentos */ ?>
<?php if (!$movimentos): ?><div class="vazio">Nenhum movimento nesta sessão.</div><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Hora</th><th>Movimento</th><th>Forma</th><th>Por</th><th class="num">Valor</th></tr></thead>
    <tbody>
      <?php foreach ($movimentos as $m):
        $rot = ['BAIXA' => $m['lancamento_tipo'] === 'PAGAR' ? 'Pagamento' : 'Recebimento', 'ESTORNO' => 'Estorno', 'SUPRIMENTO' => 'Suprimento', 'SANGRIA' => 'Sangria'][$m['natureza']]; ?>
        <tr>
          <td><?= date('d/m H:i', strtotime($m['data_movimento'])) ?></td>
          <td><?= $rot ?><span class="sub"><?php if ($m['lancamento_id']): ?><a href="./<?= Contexto::perfil() === 'ADMIN' ? 'conta?id=' . (int) $m['lancamento_id'] : 'os?id=' . (int) $m['ordem_servico_id'] ?>"><?= e($m['lancamento']) ?></a><?php endif; ?><?= $m['descricao'] ? ' ' . e($m['descricao']) : '' ?></span></td>
          <td><?= e(Lancamento::FORMAS[$m['forma_pagamento']] ?? $m['forma_pagamento']) ?></td>
          <td><?= e($m['usuario'] ?? '—') ?></td>
          <td class="num"><?= $m['direcao'] === 'ENTRADA' ? '+' : '−' ?><?= dinheiro($m['valor']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
