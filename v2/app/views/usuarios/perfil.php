<?php /** Espera: $u (conta logada) */ $titulo = 'Meu perfil'; ?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Meu perfil</h1>
    <p class="page-header__subtitle"><?= e($u['email']) ?> · <?= e(Usuario::PERFIS[$u['perfil']] ?? $u['perfil']) ?></p>
  </div>
</div>

<div class="ficha" style="max-width:880px">
  <form class="card" method="post" action="./perfil-salvar">
    <?= csrf_field() ?><input type="hidden" name="acao" value="dados">
    <h2 class="card__titulo">Dados</h2>
    <div class="form-field"><label for="nome">Nome</label><input id="nome" name="nome" value="<?= e($u['nome']) ?>" required maxlength="120"></div>
    <div class="form-field" style="margin-top:12px"><label for="telefone">Telefone</label><input id="telefone" name="telefone" value="<?= e(telefone_fmt($u['telefone'] ?? '')) ?>" data-mascara="telefone"></div>
    <div class="form-acoes"><button class="btn btn--primary">Salvar</button></div>
  </form>

  <form class="card" method="post" action="./perfil-salvar" style="margin-top:0">
    <?= csrf_field() ?><input type="hidden" name="acao" value="senha">
    <h2 class="card__titulo">Trocar senha</h2>
    <div class="form-field"><label for="senha_atual">Senha atual</label><input id="senha_atual" name="senha_atual" type="password" required autocomplete="current-password"></div>
    <div class="form-field" style="margin-top:12px"><label for="nova_senha">Nova senha</label><input id="nova_senha" name="nova_senha" type="password" minlength="6" required autocomplete="new-password"></div>
    <div class="form-field" style="margin-top:12px"><label for="confirmar_senha">Confirmar</label><input id="confirmar_senha" name="confirmar_senha" type="password" minlength="6" required autocomplete="new-password"></div>
    <div class="form-acoes"><button class="btn btn--primary">Trocar senha</button></div>
  </form>
</div>
