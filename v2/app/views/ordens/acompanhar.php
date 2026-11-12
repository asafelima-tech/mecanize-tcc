<?php
/** Página pública (sem login) que o cliente abre pelo link. Espera: $os */
[$r, $cor] = os_status($os['status']);
$etapas = ['ORCAMENTO' => 'Orçamento', 'APROVADA' => 'Aprovado', 'EM_EXECUCAO' => 'Em execução', 'PRONTA' => 'Pronto', 'ENTREGUE' => 'Entregue'];
$posicao = ['ORCAMENTO' => 0, 'RECUSADA' => 0, 'APROVADA' => 1, 'EM_EXECUCAO' => 2, 'AGUARDANDO_PECA' => 2, 'PRONTA' => 3, 'ENTREGUE' => 4][$os['status']] ?? 0;
$qtd = fn($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= e($os['oficina']) ?> · <?= os_numero($os['numero']) ?></title>
  <?php require V2_RAIZ . '/app/views/layout/_favicon.php'; ?>
  <link rel="stylesheet" href="./css/mecanize.css?v=<?= (int) @filemtime(V2_RAIZ . '/public/css/mecanize.css') ?>">
  <style>.pub { max-width: 640px; margin: 0 auto; padding: 24px 16px 48px; }</style>
</head>
<body>
<main class="pub">
  <p class="form-dica"><?= e($os['oficina']) ?><?= $os['oficina_telefone'] ? ' · ' . e(telefone_fmt($os['oficina_telefone'])) : '' ?></p>
  <div class="card" style="margin-top:8px">
    <h1 style="font-size:21px;font-weight:700;letter-spacing:-0.01em;display:flex;align-items:center;gap:10px;flex-wrap:wrap"><span class="placa-caixa"><?= e($os['placa']) ?></span> <span class="badge badge--<?= $cor ?>"><?= $r ?></span></h1>
    <p class="form-dica"><?= e($os['modelo']) ?> · <?= os_numero($os['numero']) ?> · aberta em <?= data_br($os['criado_em']) ?></p>
    <?php if (!in_array($os['status'], ['RECUSADA'], true)): ?>
      <ol class="stepper" style="margin-top:20px">
        <?php $k = 0; foreach ($etapas as $rot): $estado = $k < $posicao || ($k === $posicao && $os['status'] === 'ENTREGUE') ? 'feita' : ($k === $posicao ? 'atual' : ''); ?>
          <li class="<?= $estado ?>"><span class="stepper__marca"><?= $estado === 'feita' ? icone('check', 12, 2.5) : $k + 1 ?></span><span class="stepper__rotulo"><?= $rot ?></span></li>
        <?php $k++; endforeach; ?>
      </ol>
    <?php endif; ?>
    <?php if ($os['status'] === 'AGUARDANDO_PECA'): ?><p style="margin-top:12px">Estamos aguardando a chegada de uma peça.</p><?php endif; ?>
    <?php if ($os['previsao_conclusao'] && !in_array($os['status'], ['PRONTA', 'ENTREGUE'], true)): ?><p style="margin-top:12px">Previsão: <strong><?= data_br($os['previsao_conclusao'], true) ?></strong></p><?php endif; ?>
    <?php if ($os['status'] === 'PRONTA'): ?><p style="margin-top:12px"><strong>Seu veículo está pronto para retirada.</strong></p><?php endif; ?>
  </div>

  <?php if ($os['itens']): ?>
    <div class="card card--tabela">
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Serviço / peça</th><th class="num">Qtd</th><th class="num">Total</th></tr></thead>
        <tbody><?php foreach ($os['itens'] as $i): ?><tr><td><?= e($i['descricao']) ?></td><td class="num"><?= $qtd($i['quantidade']) ?></td><td class="num"><?= dinheiro($i['valor_total']) ?></td></tr><?php endforeach; ?></tbody>
        <tfoot><tr><td colspan="2" class="num"><strong>Total</strong></td><td class="num"><strong><?= dinheiro($os['valor_total']) ?></strong></td></tr></tfoot>
      </table></div>
    </div>
  <?php endif; ?>
  <p class="form-dica" style="text-align:center;margin-top:16px">Dúvidas ou aprovação do orçamento: fale com a oficina.</p>
</main>
</body>
</html>
