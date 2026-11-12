<?php
/**
 * Página pública (sem login) que o cliente abre pelo link. Só a situação, os itens e o total, sem dados internos.
 * Composição de página única: a oficina no topo, a situação em destaque, o andamento em etapas e os itens.
 * Espera: $os
 */
[$r, $cor] = os_status($os['status']);
$etapas = ['ORCAMENTO' => 'Orçamento', 'APROVADA' => 'Aprovado', 'EM_EXECUCAO' => 'Em execução', 'PRONTA' => 'Pronto', 'ENTREGUE' => 'Entregue'];
$posicao = ['ORCAMENTO' => 0, 'RECUSADA' => 0, 'APROVADA' => 1, 'EM_EXECUCAO' => 2, 'AGUARDANDO_PECA' => 2, 'PRONTA' => 3, 'ENTREGUE' => 4][$os['status']] ?? 0;
$qtd = fn($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
$manchete = [
    'ORCAMENTO' => 'Seu orçamento está pronto.', 'APROVADA' => 'Orçamento aprovado.', 'EM_EXECUCAO' => 'Estamos trabalhando no seu veículo.',
    'AGUARDANDO_PECA' => 'Estamos aguardando a chegada de uma peça.', 'PRONTA' => 'Seu veículo está pronto para retirada.',
    'ENTREGUE' => 'Veículo entregue. Obrigado pela confiança.', 'RECUSADA' => 'Orçamento não aprovado.', 'CANCELADA' => 'Este serviço foi cancelado.',
][$os['status']] ?? $r;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= e($os['oficina']) ?> · <?= os_numero($os['numero']) ?></title>
  <?php require V2_RAIZ . '/app/views/layout/_favicon.php'; ?>
  <link rel="stylesheet" href="./css/mecanize.css?v=<?= (int) @filemtime(V2_RAIZ . '/public/css/mecanize.css') ?>">
  <script>try { if (matchMedia('(prefers-color-scheme: dark)').matches) document.documentElement.setAttribute('data-theme', 'dark'); } catch (e) {}</script>
</head>
<body class="pub-corpo">
<main class="pub">
  <header class="pub__oficina">
    <span class="pub__sigla" aria-hidden="true"><?= icone('work-order', 18, 1.75) ?></span>
    <span><strong><?= e($os['oficina']) ?></strong><?php if ($os['oficina_telefone']): ?><a href="tel:<?= e(so_digitos($os['oficina_telefone'])) ?>"><?= e(telefone_fmt($os['oficina_telefone'])) ?></a><?php endif; ?></span>
  </header>

  <section class="pub__destaque" aria-labelledby="pub-titulo">
    <p class="pub__rotulo"><span class="badge badge--<?= $cor ?>"><?= $r ?></span> <span class="tabular"><?= os_numero($os['numero']) ?></span></p>
    <h1 id="pub-titulo"><?= e($manchete) ?></h1>
    <p class="pub__veiculo"><span class="placa-caixa"><?= e($os['placa']) ?></span><span><?= e($os['modelo']) ?><small>aberta em <?= data_br($os['criado_em']) ?></small></span></p>

    <?php if (!in_array($os['status'], ['RECUSADA', 'CANCELADA'], true)): ?>
      <ol class="stepper pub__etapas">
        <?php $k = 0; foreach ($etapas as $rot): $estado = $k < $posicao || ($k === $posicao && $os['status'] === 'ENTREGUE') ? 'feita' : ($k === $posicao ? 'atual' : ''); ?>
          <li class="<?= $estado ?>"><span class="stepper__marca"><?= $estado === 'feita' ? icone('check', 12, 2.5) : $k + 1 ?></span><span class="stepper__rotulo"><?= $rot ?></span></li>
        <?php $k++; endforeach; ?>
      </ol>
    <?php endif; ?>

    <?php if ($os['previsao_conclusao'] && !in_array($os['status'], ['PRONTA', 'ENTREGUE', 'RECUSADA', 'CANCELADA'], true)): ?>
      <p class="pub__previsao"><?= icone('clock', 16) ?> Previsão: <strong class="tabular"><?= data_br($os['previsao_conclusao'], true) ?></strong></p>
    <?php endif; ?>
  </section>

  <?php if ($os['itens']): ?>
    <section class="pub__itens" aria-label="Serviços e peças">
      <h2>Serviços e peças</h2>
      <ul>
        <?php foreach ($os['itens'] as $i): ?>
          <li><span><?= e($i['descricao']) ?><?php if ((float) $i['quantidade'] != 1): ?><small><?= $qtd($i['quantidade']) ?> unidades</small><?php endif; ?></span><b class="tabular"><?= dinheiro($i['valor_total']) ?></b></li>
        <?php endforeach; ?>
      </ul>
      <p class="pub__total"><span>Total</span><strong class="tabular"><?= dinheiro($os['valor_total']) ?></strong></p>
    </section>
  <?php endif; ?>

  <p class="pub__pe">Dúvidas ou aprovação do orçamento: fale com a oficina.</p>
</main>
</body>
</html>
