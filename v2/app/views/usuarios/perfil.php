<?php
/**
 * Meu perfil, no formato de ficha das telas de cadastro: um cartão com dois grupos (dados pessoais e troca de
 * senha, cada um com o seu formulário e botão, como antes) e, ao lado, o resumo da conta.
 * Os campos, nomes, ações (acao=dados / acao=senha) e validações são os mesmos de sempre.
 * Espera: $u (conta logada)
 */
$titulo = 'Meu perfil';
$nome = trim((string) ($u['nome'] ?? ''));
$partes = $nome !== '' ? preg_split('/\s+/', $nome) : [];
$iniciais = $partes ? mb_strtoupper(mb_substr($partes[0], 0, 1) . (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '')) : '';
$perfil = Usuario::PERFIS[$u['perfil']] ?? $u['perfil'];
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Meu perfil</h1>
    <p class="page-header__subtitle">Seus dados de contato e a senha de acesso</p>
  </div>
</div>

<div class="form-ficha">
  <div class="form-ficha__corpo">
    <div class="card form-ficha__secao grupos">
      <form class="grupo" method="post" action="./perfil-salvar" aria-labelledby="g-dados">
        <?= csrf_field() ?><input type="hidden" name="acao" value="dados">
        <header class="grupo__cabeca"><span class="grupo__n">1</span><h2 id="g-dados">Dados pessoais</h2></header>
        <div class="form-ficha__grade">
          <div class="form-field form-ficha__c7"><label for="nome">Nome</label><input id="nome" name="nome" value="<?= e($u['nome']) ?>" required maxlength="120" autocomplete="name"></div>
          <div class="form-field form-ficha__c5"><label for="telefone">Telefone</label>
            <span class="campo-icone"><?= icone('phone') ?><input id="telefone" name="telefone" value="<?= e(telefone_fmt($u['telefone'] ?? '')) ?>" data-mascara="telefone" inputmode="tel"></span></div>
          <p class="form-dica form-ficha__c12">O e-mail de acesso e o perfil são definidos pelo administrador em Usuários.</p>
        </div>
        <div class="grupo__acoes"><button class="btn btn--primary"><?= icone('check') ?> Salvar dados</button></div>
      </form>

      <form class="grupo" method="post" action="./perfil-salvar" aria-labelledby="g-senha">
        <?= csrf_field() ?><input type="hidden" name="acao" value="senha">
        <header class="grupo__cabeca"><span class="grupo__n">2</span><h2 id="g-senha">Trocar senha</h2></header>
        <div class="form-ficha__grade">
          <div class="form-field form-ficha__c4"><label for="senha_atual">Senha atual</label>
            <span class="campo-icone"><?= icone('lock') ?><input id="senha_atual" name="senha_atual" type="password" required autocomplete="current-password"></span></div>
          <div class="form-field form-ficha__c4"><label for="nova_senha">Nova senha</label>
            <input id="nova_senha" name="nova_senha" type="password" minlength="6" required autocomplete="new-password" placeholder="Mínimo de 6 caracteres"></div>
          <div class="form-field form-ficha__c4"><label for="confirmar_senha">Confirmar nova senha</label>
            <input id="confirmar_senha" name="confirmar_senha" type="password" minlength="6" required autocomplete="new-password"></div>
        </div>
        <div class="grupo__acoes"><button class="btn btn--primary"><?= icone('lock') ?> Trocar senha</button></div>
      </form>
    </div>
  </div>

  <aside class="form-ficha__lado" aria-label="Sua conta">
    <div class="card form-ficha__resumo">
      <div class="form-ficha__perfil">
        <span class="form-ficha__avatar"><?= $iniciais !== '' ? e($iniciais) : icone('user', 22) ?></span>
        <div class="form-ficha__perfil-texto">
          <strong><?= e($nome) ?></strong>
          <span><?= e($perfil) ?></span>
        </div>
      </div>
      <dl class="form-ficha__dados form-ficha__dados--lista perfil-conta">
        <div><dt><?= icone('mail', 14) ?> E-mail</dt><dd title="<?= e($u['email']) ?>"><?= e($u['email']) ?></dd></div>
        <div><dt><?= icone('lock', 14) ?> Perfil</dt><dd><?= e($perfil) ?></dd></div>
        <?php if (!empty($u['criado_em'])): ?><div><dt><?= icone('calendar', 14) ?> Na equipe desde</dt><dd><?= data_br($u['criado_em']) ?></dd></div><?php endif; ?>
      </dl>
    </div>
  </aside>
</div>
