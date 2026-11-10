<?php
/** Espera: $pr, $id, $categorias, $erro */
$peca = ($pr['tipo'] ?? 'PECA') === 'PECA';
$titulo = ($id ? 'Editar ' : 'Nova ') . ($peca ? 'peça' : 'serviço');
if (!$id && !$peca) { $titulo = 'Novo serviço'; }
$val = fn($k) => e($pr[$k] ?? '');
$num = fn($k) => isset($pr[$k]) && $pr[$k] !== '' ? e(is_numeric($pr[$k]) ? number_format((float) $pr[$k], 2, ',', '.') : $pr[$k]) : '';
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= $titulo ?></h1>
    <p class="page-header__subtitle"><a href="./produtos">Peças e serviços</a><?= $id ? ' · ' . $val('nome') : '' ?></p>
  </div>
</div>

<form class="card form-card" method="post" action="./produto-salvar<?= $id ? '?id=' . (int) $id : '' ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="tipo" value="<?= $peca ? 'PECA' : 'SERVICO' ?>">
  <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

  <div class="form-section">
    <div class="form-row-4">
      <div class="form-field span-2"><label for="nome">Nome *</label><input id="nome" name="nome" value="<?= $val('nome') ?>" required maxlength="150" autofocus></div>
      <div class="form-field">
        <label for="categoria">Categoria</label>
        <input id="categoria" name="categoria" value="<?= $val('categoria') ?>" list="lista-categorias" maxlength="60">
        <datalist id="lista-categorias"><?php foreach ($categorias as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
      </div>
      <div class="form-field"><label for="codigo_interno">Código interno</label><input id="codigo_interno" name="codigo_interno" value="<?= $val('codigo_interno') ?>" maxlength="40"></div>
    </div>
  </div>

  <div class="form-section">
    <div class="form-row-4">
      <div class="form-field"><label for="preco_venda">Preço de venda (R$) *</label><input id="preco_venda" name="preco_venda" value="<?= $num('preco_venda') ?>" inputmode="decimal" required></div>
      <div class="form-field"><label for="preco_custo"><?= $peca ? 'Custo (R$)' : 'Custo estimado (R$)' ?></label><input id="preco_custo" name="preco_custo" value="<?= $num('preco_custo') ?>" inputmode="decimal"></div>
      <?php if ($peca): ?>
        <div class="form-field"><label for="estoque_minimo">Estoque mínimo</label><input id="estoque_minimo" name="estoque_minimo" value="<?= $val('estoque_minimo') !== '' ? e(rtrim(rtrim((string) $pr['estoque_minimo'], '0'), '.')) : '' ?>" inputmode="decimal"></div>
        <?php if (!$id): ?>
          <div class="form-field"><label for="estoque_inicial">Estoque inicial</label><input id="estoque_inicial" name="estoque_inicial" value="<?= $val('estoque_inicial') ?>" inputmode="decimal" placeholder="0"></div>
        <?php else: ?>
          <div class="form-field"><label>Estoque atual</label><input value="<?= e(rtrim(rtrim((string) ($pr['estoque_atual'] ?? '0'), '0'), '.')) ?>" disabled><span class="form-dica">Muda só por entrada, baixa ou ajuste de estoque.</span></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($peca): ?>
    <div class="form-section">
      <div class="form-row-3">
        <div class="form-field"><label for="marca">Marca</label><input id="marca" name="marca" value="<?= $val('marca') ?>" maxlength="60"></div>
        <div class="form-field"><label for="codigo_barras">Código de barras</label><input id="codigo_barras" name="codigo_barras" value="<?= $val('codigo_barras') ?>" maxlength="40"></div>
        <div class="form-field"><label for="localizacao">Localização</label><input id="localizacao" name="localizacao" value="<?= $val('localizacao') ?>" maxlength="60" placeholder="Ex.: Prateleira B3"></div>
      </div>
    </div>
  <?php endif; ?>

  <div class="form-field"><label for="descricao">Descrição</label><textarea id="descricao" name="descricao" rows="3"><?= $val('descricao') ?></textarea></div>

  <div class="form-acoes">
    <a class="btn btn--ghost" href="<?= $id ? './produto?id=' . (int) $id : './produtos' ?>">Cancelar</a>
    <button class="btn btn--primary">Salvar</button>
  </div>
</form>
