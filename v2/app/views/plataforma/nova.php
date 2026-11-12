<?php
/**
 * Cadastro de oficina (equipe do Mecanize), no formato de ficha das telas de cadastro: um cartão com três grupos
 * (dados da oficina, licença e primeiro administrador) e, ao lado, o resumo com as ações.
 * Os campos, nomes, validações e o destino do formulário são os mesmos de sempre.
 * Espera: $d, $erro
 */
$titulo = 'Nova oficina';
$val = fn($k) => e($d[$k] ?? '');
?>
<div class="page-header">
  <div><h1 class="page-header__title">Nova oficina</h1><p class="page-header__subtitle"><a href="./plataforma">Oficinas</a> · cadastro, licença e o primeiro acesso</p></div>
</div>

<form class="form-ficha" method="post" action="./plataforma-criar" autocomplete="off">
  <?= csrf_field() ?>

  <div class="form-ficha__corpo">
    <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

    <div class="card form-ficha__secao grupos">
      <section class="grupo" aria-labelledby="g-oficina">
        <header class="grupo__cabeca"><span class="grupo__n">1</span><h2 id="g-oficina">Oficina</h2></header>
        <div class="form-ficha__grade">
          <div class="form-field form-ficha__c8"><label for="nome">Nome *</label><input id="nome" name="nome" value="<?= $val('nome') ?>" required maxlength="150" autofocus></div>
          <div class="form-field form-ficha__c4"><label for="cnpj">CNPJ</label><input id="cnpj" name="cnpj" value="<?= $val('cnpj') ?>" data-mascara="cnpj" inputmode="numeric"></div>
          <div class="form-field form-ficha__c4"><label for="telefone">Telefone</label>
            <span class="campo-icone"><?= icone('phone') ?><input id="telefone" name="telefone" value="<?= $val('telefone') ?>" data-mascara="telefone" inputmode="tel"></span></div>
          <div class="form-field form-ficha__c5"><label for="email">E-mail</label>
            <span class="campo-icone"><?= icone('mail') ?><input id="email" name="email" type="email" value="<?= $val('email') ?>"></span></div>
          <div class="form-field form-ficha__c3"><label for="cidade">Cidade</label><input id="cidade" name="cidade" value="<?= $val('cidade') ?>"></div>
        </div>
      </section>

      <section class="grupo grupo--destaque" aria-labelledby="g-licenca">
        <header class="grupo__cabeca"><span class="grupo__n">2</span><h2 id="g-licenca">Licença</h2></header>
        <div class="plataforma-licenca"><?php require __DIR__ . '/_licenca.php'; ?></div>
      </section>

      <section class="grupo" aria-labelledby="g-admin">
        <header class="grupo__cabeca"><span class="grupo__n">3</span><h2 id="g-admin">Primeiro administrador</h2></header>
        <div class="form-ficha__grade">
          <div class="form-field form-ficha__c4"><label for="admin_nome">Nome *</label><input id="admin_nome" name="admin_nome" value="<?= $val('admin_nome') ?>" required maxlength="120"></div>
          <div class="form-field form-ficha__c4"><label for="admin_email">E-mail de acesso *</label>
            <span class="campo-icone"><?= icone('mail') ?><input id="admin_email" name="admin_email" type="email" value="<?= $val('admin_email') ?>" required></span></div>
          <div class="form-field form-ficha__c4"><label for="admin_senha">Senha provisória *</label>
            <span class="campo-icone"><?= icone('lock') ?><input id="admin_senha" name="admin_senha" type="text" minlength="8" required autocomplete="new-password"></span>
            <span class="form-dica">Mínimo 8 caracteres. Ele troca no perfil.</span></div>
        </div>
      </section>
    </div>
  </div>

  <aside class="form-ficha__lado" aria-label="Resumo">
    <div class="card form-ficha__resumo">
      <div class="form-ficha__perfil">
        <span class="form-ficha__avatar form-ficha__avatar--quadrado"><?= icone('dashboard', 22) ?></span>
        <div class="form-ficha__perfil-texto">
          <strong data-previa-nome><?= $val('nome') ?: 'Nova oficina' ?></strong>
          <span>Licença e primeiro acesso</span>
        </div>
      </div>
      <div class="form-ficha__acoes">
        <button class="btn btn--primary"><?= icone('check') ?> Cadastrar oficina</button>
        <a class="btn btn--ghost" href="./plataforma">Cancelar</a>
      </div>
      <p class="form-ficha__nota">O administrador entra com o e-mail e a senha provisória informados.</p>
    </div>
  </aside>
</form>
<script>
  (function () {
    var n = document.getElementById('nome'), p = document.querySelector('[data-previa-nome]');
    n.addEventListener('input', function () { p.textContent = n.value.trim() || 'Nova oficina'; });
  })();
</script>
