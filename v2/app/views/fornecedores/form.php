<?php /** Espera: $fo, $id, $erro */ $titulo = $id ? 'Editar fornecedor' : 'Novo fornecedor'; $val = fn($k) => e($fo[$k] ?? ''); ?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= $titulo ?></h1>
    <p class="page-header__subtitle"><a href="./fornecedores">Fornecedores</a></p>
  </div>
</div>
<form class="card form-card" method="post" action="./fornecedor-salvar<?= $id ? '?id=' . (int) $id : '' ?>">
  <?= csrf_field() ?>
  <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>
  <div class="form-section">
    <div class="form-row-3">
      <div class="form-field span-2"><label for="nome">Nome *</label><input id="nome" name="nome" value="<?= $val('nome') ?>" required maxlength="150" autofocus></div>
      <div class="form-field"><label for="documento">CPF/CNPJ</label><input id="documento" name="documento" value="<?= e(documento_fmt($fo['documento'] ?? '')) ?>" data-mascara="documento" inputmode="numeric"></div>
    </div>
    <div class="form-row-3" style="margin-top:12px">
      <div class="form-field"><label for="contato">Pessoa de contato</label><input id="contato" name="contato" value="<?= $val('contato') ?>" maxlength="100"></div>
      <div class="form-field"><label for="telefone">Telefone</label><input id="telefone" name="telefone" value="<?= e(telefone_fmt($fo['telefone'] ?? '')) ?>" data-mascara="telefone"></div>
      <div class="form-field"><label for="email">E-mail</label><input id="email" name="email" type="email" value="<?= $val('email') ?>" maxlength="150"></div>
    </div>
  </div>
  <div class="form-field"><label for="observacoes">Observações (prazos, condições de pagamento…)</label><textarea id="observacoes" name="observacoes" rows="3"><?= $val('observacoes') ?></textarea></div>
  <div class="form-acoes">
    <a class="btn btn--ghost" href="<?= $id ? './fornecedor?id=' . (int) $id : './fornecedores' ?>">Cancelar</a>
    <button class="btn btn--primary">Salvar fornecedor</button>
  </div>
</form>
