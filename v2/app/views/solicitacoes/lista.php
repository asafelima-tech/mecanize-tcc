<?php
/**
 * Pedidos de material dos técnicos (ADMIN): o que foi pedido, por quem, para qual OS e por quê.
 * Cada pedido em aberto pode ser aprovado, marcado como atendido ou recusado (com resposta ao técnico).
 * Espera: $filtro, $pedidos, $contagens, $p
 */
$qtd = fn($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
$rotulos = ['abertas' => 'pedidos em aberto', 'atendidas' => 'pedidos atendidos', 'recusadas' => 'pedidos recusados', 'todas' => 'pedidos'];
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Pedidos dos técnicos</h1>
    <p class="page-header__subtitle">Peças que acabaram, reposições, compras e problemas avisados pelos técnicos.</p>
  </div>
</div>

<nav class="abas abas--contagem" aria-label="Situação dos pedidos">
  <?php foreach (Solicitacao::FILTROS as $k => [$rotulo]): $ativa = $filtro === $k; ?>
    <a href="<?= e(url_com(['situacao' => $k === 'abertas' ? null : $k, 'pagina' => null])) ?>"<?= $ativa ? ' class="ativa" aria-current="page"' : '' ?>>
      <?= e($rotulo) ?><span class="abas__n"><?= (int) $contagens[$k] ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<div class="card card--tabela">
  <?php if (!$pedidos): ?>
    <div class="vazio"><?= icone('box', 32, 1.25) ?><?= $filtro === 'abertas' ? 'Nenhum pedido esperando resposta.' : 'Nenhum pedido nesta situação.' ?></div>
  <?php else: ?>
    <ul class="pedidos-admin">
      <?php foreach ($pedidos as $s): [$rotSit, $corSit] = Solicitacao::SITUACOES[$s['status']]; $item = $s['produto'] ?? $s['item_descricao']; $aberto = in_array($s['status'], ['RECEBIDA', 'APROVADA'], true); ?>
        <li class="pedidos-admin__item">
          <div class="pedidos-admin__corpo">
            <p class="pedidos-admin__titulo">
              <strong><?= e(Solicitacao::TIPOS[$s['tipo']][0]) ?><?= $item ? ': ' . e($item) : '' ?></strong>
              <?php if ($s['quantidade']): ?><span class="pedidos-admin__qtd"><?= $qtd($s['quantidade']) ?> un.</span><?php endif; ?>
              <span class="badge badge--<?= $corSit ?>"><?= $rotSit ?></span>
            </p>
            <p class="pedidos-admin__texto">“<?= e($s['justificativa']) ?>”</p>
            <p class="pedidos-admin__meta">
              <?= icone('user', 13) ?> <?= e($s['tecnico']) ?> · <?= data_br($s['criado_em'], true) ?>
              <?php if ($s['os_numero']): ?> · <a href="./os?id=<?= (int) $s['ordem_servico_id'] ?>"><?= os_numero($s['os_numero']) ?><?= $s['placa'] ? ' · ' . e($s['placa']) : '' ?></a><?php endif; ?>
              <?php if (!$s['produto'] && $s['item_descricao']): ?> · <span>fora do catálogo</span><?php endif; ?>
            </p>
            <?php if ($s['respondido_em']): ?>
              <p class="pedidos-admin__resposta"><?= e($rotSit) ?> por <?= e($s['respondido_por_nome']) ?> em <?= data_br($s['respondido_em'], true) ?><?= $s['resposta'] ? ': “' . e($s['resposta']) . '”' : '' ?></p>
            <?php endif; ?>
          </div>
          <?php if ($aberto): ?>
            <form class="pedidos-admin__acoes" method="post" action="./solicitacao-responder?id=<?= (int) $s['id'] ?>">
              <?= csrf_field() ?><input type="hidden" name="situacao" value="<?= e($filtro === 'abertas' ? '' : $filtro) ?>">
              <input name="resposta" maxlength="255" placeholder="Resposta ao técnico (obrigatória para recusar)" aria-label="Resposta ao técnico">
              <div class="pedidos-admin__botoes">
                <button class="btn btn--ghost btn--sm" name="status" value="RECUSADA">Recusar</button>
                <?php if ($s['status'] === 'RECEBIDA'): ?><button class="btn btn--contorno btn--sm" name="status" value="APROVADA">Aprovar</button><?php endif; ?>
                <button class="btn btn--primary btn--sm" name="status" value="ATENDIDA"><?= icone('check') ?> Atendido</button>
              </div>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php $rotuloItens = $rotulos[$filtro]; require V2_RAIZ . '/app/views/layout/_rodape.php'; ?>
  <?php endif; ?>
</div>
<p class="form-dica pedidos-admin__dica">Aprovar diz ao técnico que o pedido foi aceito; "Atendido" encerra (a peça chegou ou o problema foi resolvido). A entrada da peça no estoque continua em Estoque › Entrada de peças.</p>
