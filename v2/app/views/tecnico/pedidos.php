<?php
/**
 * Pedidos de material do técnico (celular primeiro): fazer um pedido novo (peça que acabou, reposição,
 * peça indisponível, compra, material sem cadastro, problema) e acompanhar a resposta do administrador.
 * Espera: $pedidos, $abertas (OS em aberto dele), $catalogo, $novo (tipo e OS pré-escolhidos)
 */
$qtd = fn($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
$abrirForm = !$pedidos || !empty($_GET['novo']) || $novo['tipo'] !== '' || $novo['ordem_servico_id'] > 0;
$tons = ['RECEBIDA' => 'espera', 'APROVADA' => 'andamento', 'ATENDIDA' => 'feito', 'RECUSADA' => 'alerta'];
$explica = ['RECEBIDA' => 'O administrador recebeu e vai analisar.', 'APROVADA' => 'Aprovado. Aguarde a peça ou a providência.',
            'ATENDIDA' => 'Resolvido.', 'RECUSADA' => 'Recusado.'];
?>
<div class="tec">
  <header class="tec-cabeca">
    <h1 class="tec-cabeca__titulo">Pedidos de material</h1>
    <p class="tec-cabeca__sub">Avise o que acabou, peça reposição ou compra e conte problemas. O administrador responde aqui.</p>
  </header>

  <div class="tec-pedidos">
  <div class="tec-pedidos__form">
  <h2 class="tec-bloco__titulo tec-pedidos__titulo">Novo pedido</h2>
  <details class="tec-form tec-form--destaque" data-aberto-no-computador <?= $abrirForm ? 'open' : '' ?>>
    <summary class="tec-botao"><?= icone('plus', 18, 2) ?> Novo pedido</summary>
    <form method="post" action="./pedido-criar" class="tec-form__corpo">
      <?= csrf_field() ?>
      <fieldset class="tec-tipos">
        <legend class="tec-campo__rotulo">O que aconteceu?</legend>
        <?php foreach (Solicitacao::TIPOS as $k => [$rot, $dica]): ?>
          <label><input type="radio" name="tipo" value="<?= $k ?>" required <?= $novo['tipo'] === $k ? 'checked' : '' ?>><span><?= e($rot) ?><small><?= e($dica) ?></small></span></label>
        <?php endforeach; ?>
      </fieldset>
      <?php if ($abertas): ?>
        <div class="tec-campo"><label class="tec-campo__rotulo" for="ped-os">Serviço relacionado (opcional)</label>
          <select id="ped-os" name="ordem_servico_id">
            <option value="">Nenhum, é para o estoque</option>
            <?php foreach ($abertas as $o): ?><option value="<?= (int) $o['id'] ?>" <?= $novo['ordem_servico_id'] === (int) $o['id'] ? 'selected' : '' ?>><?= os_numero($o['numero']) ?> · <?= e($o['placa']) ?><?= $o['modelo'] ? ' · ' . e($o['modelo']) : '' ?></option><?php endforeach; ?>
          </select></div>
      <?php endif; ?>
      <?php $pecaId = 'np'; $pecaRotulo = 'Peça do catálogo (se tiver)'; require __DIR__ . '/_peca.php'; ?>
      <div class="tec-campo"><label class="tec-campo__rotulo" for="np-item">Ou escreva o nome da peça</label><input id="np-item" name="item_descricao" maxlength="150" placeholder="Ex.: óleo 5W30 sintético"></div>
      <div class="tec-campo"><label class="tec-campo__rotulo" for="np-qtd">Quantidade (opcional)</label><input id="np-qtd" name="quantidade" inputmode="decimal" placeholder="1"></div>
      <div class="tec-campo"><label class="tec-campo__rotulo" for="np-just">Explique</label><textarea id="np-just" name="justificativa" rows="3" maxlength="500" required placeholder="Ex.: acabou a última caixa na prateleira"></textarea></div>
      <button class="tec-botao"><?= icone('send', 18) ?> Enviar ao administrador</button>
    </form>
  </details>
  </div>

  <div class="tec-pedidos__lista">
  <h2 class="tec-bloco__titulo tec-bloco__titulo--solto">Meus pedidos</h2>
  <?php if (!$pedidos): ?>
    <div class="tec-vazio"><?= icone('box', 32, 1.25) ?><strong>Nenhum pedido ainda.</strong><span>Os pedidos que você enviar aparecem aqui com a resposta do administrador.</span></div>
  <?php else: ?>
    <ul class="tec-lista">
      <?php foreach ($pedidos as $p): [$rp] = Solicitacao::SITUACOES[$p['status']]; $nome = $p['produto'] ?? $p['item_descricao']; ?>
        <li class="tec-pedido tec-cartao--<?= $tons[$p['status']] ?>">
          <span class="tec-cartao__topo"><span class="tec-situacao tec-situacao--<?= $tons[$p['status']] ?>"><?= e($rp) ?></span><span class="tec-cartao__os"><?= data_br($p['criado_em'], true) ?></span></span>
          <strong class="tec-pedido__titulo"><?= e(Solicitacao::TIPOS[$p['tipo']][0]) ?><?= $nome ? ': ' . e($nome) : '' ?><?= $p['quantidade'] ? ' · ' . $qtd($p['quantidade']) . ' un.' : '' ?></strong>
          <span class="tec-pedido__texto"><?= e($p['justificativa']) ?></span>
          <?php if ($p['os_numero']): ?><a class="tec-pedido__os" href="./servico?id=<?= (int) $p['ordem_servico_id'] ?>"><?= icone('work-order', 14) ?> <?= os_numero($p['os_numero']) ?><?= $p['placa'] ? ' · ' . e($p['placa']) : '' ?></a><?php endif; ?>
          <span class="tec-pedido__resposta"><?= $p['resposta'] ? '“' . e($p['resposta']) . '”' . ($p['respondido_por_nome'] ? ' · ' . e($p['respondido_por_nome']) : '') : e($explica[$p['status']]) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  </div>
  </div>
</div>
<?php require __DIR__ . '/_peca_script.php'; ?>
<script>
  // No computador o formulário fica aberto ao lado da lista (não há por que esconder).
  if (window.matchMedia('(min-width: 1100px)').matches) document.querySelectorAll('[data-aberto-no-computador]').forEach(function (d) { d.open = true; });
</script>
