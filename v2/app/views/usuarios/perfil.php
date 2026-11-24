<?php
/**
 * Meu perfil, no padrão de janela (modal) das telas de criar e editar.
 * Duas seções com o seu formulário cada (dados pessoais e troca de senha, como antes): o "Salvar" do rodapé
 * envia os dados; a senha tem o seu botão na própria seção. Aberta pelo js/modal.js sobre a tela de onde
 * veio; acessada direto, ocupa a página (também no celular do técnico).
 * Os campos, nomes, ações (acao=dados / acao=senha) e validações são os mesmos de sempre.
 * Espera: $u (conta logada)
 */
$titulo = 'Meu perfil';
$nome = trim((string) ($u['nome'] ?? ''));
$perfil = Usuario::PERFIS[$u['perfil']] ?? $u['perfil'];
$inicio = './' . e(Permissao::inicio(Contexto::perfil()));
$secoes = [['dados', 'user', 'Dados pessoais'], ['senha', 'lock', 'Senha']];
?>
<div class="mf" role="group" aria-labelledby="mf-titulo">
  <header class="mf__topo">
    <h2 class="mf__titulo" id="mf-titulo">Meu perfil<?php if ($nome !== ''): ?><span><?= e($nome) ?></span><?php endif; ?></h2>
    <a class="mf__fechar" href="<?= $inicio ?>" data-mf-fechar aria-label="Fechar" title="Fechar"><?= icone('close', 18, 1.75) ?></a>
  </header>

  <div class="mf__corpo">
    <nav class="mf__nav" aria-label="Seções do perfil">
      <?php foreach ($secoes as $k => [$ancora, $ic, $rotulo]): ?>
        <a href="#mf-<?= $ancora ?>"<?= $k === 0 ? ' class="is-ativa" aria-current="true"' : '' ?>><?= icone($ic, 18) ?><span><?= $rotulo ?></span></a>
      <?php endforeach; ?>
      <?php if (!empty($u['criado_em'])): ?>
        <dl class="mf__nav-rodape">
          <div><dt>Na equipe desde</dt><dd><?= data_br($u['criado_em']) ?></dd></div>
        </dl>
      <?php endif; ?>
    </nav>

    <div class="mf__conteudo">
      <form id="perfil-dados" method="post" action="./perfil-salvar">
        <?= csrf_field() ?><input type="hidden" name="acao" value="dados">
        <section class="mf__secao" id="mf-dados" aria-labelledby="mf-h-dados">
          <h3 id="mf-h-dados">Dados pessoais</h3>
          <div class="mf-linha">
            <div class="mf-linha__rotulo"><label for="nome">Nome</label></div>
            <div class="mf-linha__campo"><input id="nome" name="nome" value="<?= e($u['nome']) ?>" required maxlength="120" autocomplete="name"></div>
          </div>
          <div class="mf-linha">
            <div class="mf-linha__rotulo"><label for="telefone">Telefone</label><small>Celular com DDD.</small></div>
            <div class="mf-linha__campo"><input id="telefone" name="telefone" value="<?= e(telefone_fmt($u['telefone'] ?? '')) ?>" data-mascara="telefone" inputmode="tel" placeholder="(00) 00000-0000" autocomplete="tel"></div>
          </div>
          <div class="mf-linha">
            <div class="mf-linha__rotulo"><span>E-mail de acesso</span><small>O e-mail e o perfil são definidos pelo administrador em Usuários.</small></div>
            <div class="mf-linha__campo mf-linha__campo--fim"><span class="mf-leitura" title="<?= e($u['email']) ?>"><?= e($u['email']) ?></span></div>
          </div>
          <div class="mf-linha">
            <div class="mf-linha__rotulo"><span>Perfil</span></div>
            <div class="mf-linha__campo mf-linha__campo--fim"><span class="mf-leitura"><?= e($perfil) ?></span></div>
          </div>
        </section>
      </form>

      <form id="perfil-senha" method="post" action="./perfil-salvar">
        <?= csrf_field() ?><input type="hidden" name="acao" value="senha">
        <section class="mf__secao" id="mf-senha" aria-labelledby="mf-h-senha">
          <h3 id="mf-h-senha">Trocar senha</h3>
          <div class="mf-linha">
            <div class="mf-linha__rotulo"><label for="senha_atual">Senha atual</label></div>
            <div class="mf-linha__campo"><input id="senha_atual" name="senha_atual" type="password" required autocomplete="current-password"></div>
          </div>
          <div class="mf-linha">
            <div class="mf-linha__rotulo"><label for="nova_senha">Nova senha</label><small>Mínimo de 6 caracteres.</small></div>
            <div class="mf-linha__campo"><input id="nova_senha" name="nova_senha" type="password" minlength="6" required autocomplete="new-password"></div>
          </div>
          <div class="mf-linha">
            <div class="mf-linha__rotulo"><label for="confirmar_senha">Confirmar nova senha</label></div>
            <div class="mf-linha__campo"><input id="confirmar_senha" name="confirmar_senha" type="password" minlength="6" required autocomplete="new-password"></div>
          </div>
          <div class="mf-acoes-secao">
            <button class="btn btn--ghost btn--sm" type="submit">Trocar senha</button>
          </div>
        </section>
      </form>
    </div>
  </div>

  <footer class="mf__rodape">
    <span class="mf__nota">A senha é trocada à parte, na sua seção.</span>
    <a class="btn btn--ghost" href="<?= $inicio ?>" data-mf-fechar>Cancelar</a>
    <button class="btn btn--primary" type="submit" form="perfil-dados">Salvar</button>
  </footer>
</div>
