<?php /** Espera: $d, $erro */ $titulo = 'Nova oficina'; $val = fn($k) => e($d[$k] ?? ''); ?>
<div class="page-header">
  <div><h1 class="page-header__title">Nova oficina</h1><p class="page-header__subtitle"><a href="./plataforma">Oficinas</a></p></div>
</div>
<form class="card form-card" method="post" action="./plataforma-criar" autocomplete="off">
  <?= csrf_field() ?>
  <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>
  <h2 class="card__titulo">Oficina</h2>
  <div class="form-row-3">
    <div class="form-field span-2"><label for="nome">Nome *</label><input id="nome" name="nome" value="<?= $val('nome') ?>" required maxlength="150" autofocus></div>
    <div class="form-field"><label for="cnpj">CNPJ</label><input id="cnpj" name="cnpj" value="<?= $val('cnpj') ?>" data-mascara="cnpj" inputmode="numeric"></div>
  </div>
  <div class="form-row-3" style="margin-top:12px">
    <div class="form-field"><label for="telefone">Telefone</label><input id="telefone" name="telefone" value="<?= $val('telefone') ?>" data-mascara="telefone"></div>
    <div class="form-field"><label for="email">E-mail</label><input id="email" name="email" type="email" value="<?= $val('email') ?>"></div>
    <div class="form-field"><label for="cidade">Cidade</label><input id="cidade" name="cidade" value="<?= $val('cidade') ?>"></div>
  </div>

  <h2 class="card__titulo" style="margin-top:20px">Licença</h2>
  <?php require __DIR__ . '/_licenca.php'; ?>

  <h2 class="card__titulo" style="margin-top:20px">Primeiro administrador</h2>
  <div class="form-row-3">
    <div class="form-field"><label for="admin_nome">Nome *</label><input id="admin_nome" name="admin_nome" value="<?= $val('admin_nome') ?>" required maxlength="120"></div>
    <div class="form-field"><label for="admin_email">E-mail de acesso *</label><input id="admin_email" name="admin_email" type="email" value="<?= $val('admin_email') ?>" required></div>
    <div class="form-field"><label for="admin_senha">Senha provisória *</label><input id="admin_senha" name="admin_senha" type="text" minlength="8" required autocomplete="new-password"><span class="form-dica">Mínimo 8 caracteres. Ele troca no perfil.</span></div>
  </div>
  <div class="form-acoes"><a class="btn btn--ghost" href="./plataforma">Cancelar</a><button class="btn btn--primary">Cadastrar oficina</button></div>
</form>
