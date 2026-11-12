<?php
/**
 * Ficha de consulta da peça ou do serviço, no formato das outras fichas: identificação no topo, os números
 * (preço, custo e margem só para o administrador, como antes; estoque e mínimo nas peças), os dados agrupados
 * e, ao lado, as últimas movimentações de estoque com os atalhos de entrada e ajuste.
 * Ações (só ADMIN, como antes): editar; desativar e excluir no menu.
 * Espera: $pr, $movimentos
 */
$titulo = $pr['nome'];
$admin = Contexto::perfil() === 'ADMIN';
$peca = $pr['tipo'] === 'PECA';
$id = (int) $pr['id'];
$qtd = fn($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
$motivos = ['COMPRA' => 'Entrada', 'SAIDA_OS' => 'Saída por OS', 'DEVOLUCAO_OS' => 'Devolução de OS', 'AJUSTE' => 'Ajuste', 'ESTORNO' => 'Estorno'];
$venda = (float) $pr['preco_venda'];
$custo = (float) $pr['preco_custo'];
$margem = $venda > 0 && $pr['preco_custo'] !== null ? ($venda - $custo) / $venda * 100 : null;
$esgotado = $peca && (float) $pr['estoque_atual'] <= 0;
$baixo = $peca && !$esgotado && (float) $pr['estoque_minimo'] > 0 && (float) $pr['estoque_atual'] <= (float) $pr['estoque_minimo'];
$vazio = '<span class="secundario">Não informado</span>';
?>
<header class="ver-topo">
  <div class="ver-topo__id">
    <span class="ver-topo__avatar ver-topo__avatar--quadrado" aria-hidden="true"><?= icone($peca ? 'box' : 'work-order', 30, 1.5) ?></span>
    <div class="ver-topo__texto">
      <p class="ver-topo__voltar"><a href="./produtos"><?= icone('chevron-left', 14, 2) ?> Peças e serviços</a></p>
      <h1><?= e($pr['nome']) ?></h1>
      <p class="ver-topo__meta">
        <span class="ver-chip"><?= icone($peca ? 'part' : 'mechanic', 13) ?> <?= $peca ? 'Peça' : 'Serviço' ?></span>
        <?php if ($pr['categoria']): ?><span class="ver-chip"><?= e($pr['categoria']) ?></span><?php endif; ?>
        <?php if ($pr['codigo_interno']): ?><span class="ver-chip ver-chip--mono"><?= e($pr['codigo_interno']) ?></span><?php endif; ?>
        <?php if (!$pr['ativo']): ?><span class="ver-chip ver-chip--escuro">Inativo</span>
        <?php elseif ($esgotado): ?><span class="badge badge--danger">Esgotado</span>
        <?php elseif ($baixo): ?><span class="badge badge--warning">Estoque baixo</span><?php endif; ?>
      </p>
    </div>
  </div>
  <?php if ($admin): ?>
    <div class="ver-topo__acoes">
      <a class="btn btn--primary btn--sm" href="./produto-form?id=<?= $id ?>"><?= icone('edit') ?> Editar</a>
      <details class="menu-mais">
        <summary class="btn btn--ghost btn--sm btn--icone" aria-label="Mais ações"><?= icone('more-h') ?></summary>
        <div class="menu-mais__lista">
          <form method="post" action="./produto-ativo?id=<?= $id ?>" <?= (int) $pr['ativo'] ? 'data-confirmar="Desativar este item? Ele deixa de aparecer nas listas e nas escolhas, mas o histórico continua."' : '' ?>>
            <?= csrf_field() ?><input type="hidden" name="ativo" value="<?= (int) $pr['ativo'] ? 0 : 1 ?>">
            <button><?= icone($pr['ativo'] ? 'lock' : 'check') ?><?= (int) $pr['ativo'] ? 'Desativar' : 'Reativar' ?></button>
          </form>
          <form method="post" action="./produto-excluir?id=<?= $id ?>" data-confirmar="Excluir este item definitivamente? Só é possível se não houver nada ligado a ele.">
            <?= csrf_field() ?><button><?= icone('trash') ?>Excluir</button>
          </form>
        </div>
      </details>
    </div>
  <?php endif; ?>
</header>

<?php $numeros = 1 + ($admin ? 2 : 0) + ($peca ? 1 : 0); ?>
<div class="ver-numeros ver-numeros--<?= $numeros ?>" aria-label="Preço<?= $peca ? ' e estoque' : '' ?>">
  <div><span>Preço de venda</span><strong><?= dinheiro($venda) ?></strong><small><?= $peca ? 'por unidade' : 'por serviço' ?></small></div>
  <?php if ($admin): ?>
    <div><span><?= $peca ? 'Custo' : 'Custo estimado' ?></span><strong><?= dinheiro($custo) ?></strong><small><?= $peca ? 'atualizado nas entradas' : 'informado no cadastro' ?></small></div>
    <div><span>Margem</span><strong><?= $margem !== null ? number_format($margem, 1, ',', '.') . '%' : '—' ?></strong><small><?= $margem !== null ? ($venda - $custo >= 0 ? 'lucro de ' : 'prejuízo de ') . dinheiro(abs($venda - $custo)) : 'sem preço de venda' ?></small></div>
  <?php endif; ?>
  <?php if ($peca): ?>
    <div><span>Em estoque</span><strong><?= $qtd($pr['estoque_atual']) ?></strong><small><?= (float) $pr['estoque_minimo'] > 0 ? 'mínimo de ' . $qtd($pr['estoque_minimo']) : 'sem mínimo definido' ?></small></div>
  <?php endif; ?>
</div>

<div class="ver-grade<?= $peca ? '' : ' ver-grade--uma' ?>">
  <div class="ver-principal">
    <section class="card ver-dados" aria-label="Dados do item">
      <div class="ver-grupo">
        <h2 class="ver-grupo__titulo"><?= icone('file', 15) ?> Identificação</h2>
        <dl class="ver-lista">
          <div><dt>Categoria</dt><dd><?= $pr['categoria'] ? e($pr['categoria']) : $vazio ?></dd></div>
          <div><dt>Código interno</dt><dd class="tabular"><?= $pr['codigo_interno'] ? e($pr['codigo_interno']) : $vazio ?></dd></div>
          <?php if ($peca): ?><div><dt>Marca</dt><dd><?= $pr['marca'] ? e($pr['marca']) : $vazio ?></dd></div><?php endif; ?>
        </dl>
      </div>
      <?php if ($peca): ?>
        <div class="ver-grupo">
          <h2 class="ver-grupo__titulo"><?= icone('location', 15) ?> No estoque</h2>
          <dl class="ver-lista">
            <div><dt>Localização</dt><dd><?= $pr['localizacao'] ? e($pr['localizacao']) : $vazio ?></dd></div>
            <div><dt>Código de barras</dt><dd class="tabular"><?= $pr['codigo_barras'] ? e($pr['codigo_barras']) : $vazio ?></dd></div>
            <div><dt>Estoque mínimo</dt><dd class="tabular"><?= (float) $pr['estoque_minimo'] > 0 ? $qtd($pr['estoque_minimo']) : $vazio ?></dd></div>
          </dl>
        </div>
      <?php else: ?>
        <div class="ver-grupo">
          <h2 class="ver-grupo__titulo"><?= icone('coins', 15) ?> Cobrança</h2>
          <dl class="ver-lista">
            <div><dt>Preço de venda</dt><dd class="tabular"><?= dinheiro($venda) ?></dd></div>
            <div><dt>Uso</dt><dd>Mão de obra lançada nas ordens de serviço</dd></div>
          </dl>
        </div>
      <?php endif; ?>
      <div class="ver-obs">
        <h2 class="ver-grupo__titulo"><?= icone('file', 15) ?> Descrição</h2>
        <?php if ($pr['descricao']): ?><p><?= e($pr['descricao']) ?></p><?php else: ?><p class="secundario">Nenhuma descrição.</p><?php endif; ?>
      </div>
    </section>
  </div>

  <?php if ($peca): ?>
    <aside class="card ver-lado" aria-labelledby="ver-movs">
      <header class="ver-lado__cabeca">
        <h2 id="ver-movs">Últimas movimentações</h2>
        <?php if ($admin): ?><a class="link-forte" href="./estoque?produto=<?= $id ?>">Ver todas</a><?php endif; ?>
      </header>
      <?php if ($admin && $pr['ativo']): ?>
        <div class="ver-lado__acoes">
          <a class="btn btn--ghost btn--sm" href="./estoque-entrada?produto=<?= $id ?>"><?= icone('download') ?> Entrada</a>
          <a class="btn btn--ghost btn--sm" href="./estoque-ajuste?produto=<?= $id ?>"><?= icone('settings') ?> Ajustar</a>
        </div>
      <?php endif; ?>
      <?php if (!$movimentos): ?>
        <div class="ver-lado__vazio"><?= icone('history', 24, 1.4) ?><span>Nenhuma movimentação.</span></div>
      <?php else: ?>
        <ul class="ver-movs">
          <?php foreach ($movimentos as $m): $d = (float) $m['delta']; ?>
            <li>
              <span class="ver-movs__texto">
                <strong><?= $motivos[$m['motivo']] ?? e($m['motivo']) ?></strong>
                <small><?= data_br($m['criado_em'], true) ?><?= $m['usuario'] ? ' · ' . e($m['usuario']) : '' ?><?= $m['observacao'] ? ' · ' . e($m['observacao']) : '' ?></small>
              </span>
              <b class="ver-movs__delta<?= $d < 0 ? ' is-saida' : '' ?>"><?= $d > 0 ? '+' : '' ?><?= $qtd($d) ?></b>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </aside>
  <?php endif; ?>
</div>
