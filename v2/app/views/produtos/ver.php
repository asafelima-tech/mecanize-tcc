<?php
/** Espera: $pr, $movimentos */
$titulo = $pr['nome'];
$admin = Contexto::perfil() === 'ADMIN';
$peca = $pr['tipo'] === 'PECA';
$qtd = fn($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
$motivos = ['COMPRA' => 'Entrada', 'SAIDA_OS' => 'Saída por OS', 'DEVOLUCAO_OS' => 'Devolução de OS', 'AJUSTE' => 'Ajuste', 'ESTORNO' => 'Estorno'];
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= e($pr['nome']) ?> <?php if (!$pr['ativo']): ?><span class="badge badge--muted">Inativo</span><?php endif; ?></h1>
    <p class="page-header__subtitle"><a href="./produtos">Peças e serviços</a> · <?= $peca ? 'Peça' : 'Serviço' ?><?= $pr['categoria'] ? ' · ' . e($pr['categoria']) : '' ?></p>
  </div>
  <?php if ($admin): ?>
    <div class="page-header__actions acoes">
      <?php $rotaAtivo = 'produto-ativo'; $rotaExcluir = 'produto-excluir'; $registro = $pr; $nome = 'este item'; require V2_RAIZ . '/app/views/layout/_ativo.php'; ?>
      <a class="btn btn--primary btn--sm" href="./produto-form?id=<?= (int) $pr['id'] ?>"><?= icone('edit') ?> Editar</a>
    </div>
  <?php endif; ?>
</div>

<div class="ficha">
  <div>
    <div class="card">
      <dl class="dados">
        <div><dt>Preço de venda</dt><dd><?= dinheiro($pr['preco_venda']) ?></dd></div>
        <?php if ($admin): ?><div><dt>Custo</dt><dd><?= dinheiro($pr['preco_custo']) ?></dd></div><?php endif; ?>
        <?php if ($peca): ?>
          <div><dt>Estoque</dt><dd><?= $qtd($pr['estoque_atual']) ?> <span class="form-dica">(mínimo <?= $qtd($pr['estoque_minimo']) ?>)</span></dd></div>
          <div><dt>Localização</dt><dd><?= e($pr['localizacao'] ?: '—') ?></dd></div>
          <div><dt>Marca</dt><dd><?= e($pr['marca'] ?: '—') ?></dd></div>
          <div><dt>Código de barras</dt><dd><?= e($pr['codigo_barras'] ?: '—') ?></dd></div>
        <?php endif; ?>
        <div><dt>Código interno</dt><dd><?= e($pr['codigo_interno'] ?: '—') ?></dd></div>
      </dl>
      <?php if ($pr['descricao']): ?><p style="margin-top:14px;font-size:13px;white-space:pre-line"><?= e($pr['descricao']) ?></p><?php endif; ?>
    </div>
  </div>

  <?php if ($peca): ?>
    <div class="card">
      <h2 class="card__titulo">Últimas movimentações <?php if ($admin): ?><a href="./estoque?produto=<?= (int) $pr['id'] ?>">Ver todas</a><?php endif; ?></h2>
      <?php if ($admin && $pr['ativo']): ?>
        <div class="acoes" style="margin-bottom:8px">
          <a class="btn btn--ghost btn--sm" href="./estoque-entrada?produto=<?= (int) $pr['id'] ?>"><?= icone('download') ?> Entrada</a>
          <a class="btn btn--ghost btn--sm" href="./estoque-ajuste?produto=<?= (int) $pr['id'] ?>"><?= icone('settings') ?> Ajustar</a>
        </div>
      <?php endif; ?>
      <?php if (!$movimentos): ?><p class="form-dica">Nenhuma movimentação.</p><?php endif; ?>
      <?php foreach ($movimentos as $m): ?>
        <div class="linha-os">
          <div class="linha-os__topo"><span><?= $motivos[$m['motivo']] ?? e($m['motivo']) ?></span><strong><?= (float) $m['delta'] > 0 ? '+' : '' ?><?= $qtd($m['delta']) ?></strong></div>
          <div class="form-dica"><?= data_br($m['criado_em'], true) ?><?= $m['usuario'] ? ' · ' . e($m['usuario']) : '' ?><?= $m['observacao'] ? ' · ' . e($m['observacao']) : '' ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
