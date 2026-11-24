<?php
/**
 * Cadastro de oficina (equipe do Mecanize), no padrão de janela (modal) das telas de criar e editar.
 * Topo com título e fechar, seções à esquerda (oficina, licença e primeiro administrador), linhas
 * "rótulo à esquerda, campo à direita" e o rodapé com as ações.
 * Os campos de licença são os mesmos de _licenca.php (nomes, limites e atalhos de renovar), aqui em linhas.
 * Os campos, nomes, validações e o destino do formulário são os mesmos de sempre.
 * Espera: $d, $erro
 */
$val = fn($k) => e($d[$k] ?? '');
$secoes = [['oficina', 'home', 'Oficina'], ['licenca', 'key', 'Licença'], ['administrador', 'user', 'Administrador']];
?>
<form class="mf" method="post" action="./plataforma-criar" autocomplete="off" aria-labelledby="mf-titulo">
  <?= csrf_field() ?>

  <header class="mf__topo">
    <h2 class="mf__titulo" id="mf-titulo">Nova oficina</h2>
    <a class="mf__fechar" href="./plataforma" data-mf-fechar aria-label="Fechar" title="Fechar"><?= icone('close', 18, 1.75) ?></a>
  </header>

  <div class="mf__corpo">
    <nav class="mf__nav" aria-label="Seções do cadastro">
      <?php foreach ($secoes as $k => [$ancora, $ic, $rotulo]): ?>
        <a href="#mf-<?= $ancora ?>"<?= $k === 0 ? ' class="is-ativa" aria-current="true"' : '' ?>><?= icone($ic, 18) ?><span><?= $rotulo ?></span></a>
      <?php endforeach; ?>
    </nav>

    <div class="mf__conteudo">
      <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

      <section class="mf__secao" id="mf-oficina" aria-labelledby="mf-h-oficina">
        <h3 id="mf-h-oficina">Oficina</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="nome">Nome</label></div>
          <div class="mf-linha__campo"><input id="nome" name="nome" value="<?= $val('nome') ?>" required maxlength="150" autofocus></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="cnpj">CNPJ</label><small>Opcional.</small></div>
          <div class="mf-linha__campo"><input id="cnpj" name="cnpj" value="<?= $val('cnpj') ?>" data-mascara="cnpj" inputmode="numeric"></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="telefone">Telefone</label></div>
          <div class="mf-linha__campo"><input id="telefone" name="telefone" value="<?= $val('telefone') ?>" data-mascara="telefone" inputmode="tel" placeholder="(00) 00000-0000"></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="email">E-mail</label></div>
          <div class="mf-linha__campo"><input id="email" name="email" type="email" value="<?= $val('email') ?>" placeholder="contato@oficina.com.br"></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="cidade">Cidade</label></div>
          <div class="mf-linha__campo"><input id="cidade" name="cidade" value="<?= $val('cidade') ?>"></div>
        </div>
      </section>

      <section class="mf__secao" id="mf-licenca" aria-labelledby="mf-h-licenca">
        <h3 id="mf-h-licenca">Licença</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="plano">Plano</label><small>Trocar o plano ajusta o limite de usuários.</small></div>
          <div class="mf-linha__campo">
            <select id="plano" name="plano">
              <?php foreach (Oficina::PLANOS as $k => [$r, $lim]): ?><option value="<?= $k ?>" data-limite="<?= $lim ?>" <?= ($d['plano'] ?? '') === $k ? 'selected' : '' ?>><?= e($r) ?> (até <?= $lim ?>)</option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="limite_usuarios">Limite de usuários</label></div>
          <div class="mf-linha__campo"><input id="limite_usuarios" name="limite_usuarios" type="number" min="1" max="500" value="<?= (int) ($d['limite_usuarios'] ?? 3) ?>"></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="status">Situação</label></div>
          <div class="mf-linha__campo">
            <select id="status" name="status"><?php foreach (Oficina::SITUACOES as $k => $r): ?><option value="<?= $k ?>" <?= ($d['status'] ?? '') === $k ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?></select>
          </div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="licenca_valida_ate">Válida até</label><small>Renovar soma a partir da validade ou de hoje, o que for depois.</small></div>
          <div class="mf-linha__campo">
            <input id="licenca_valida_ate" name="licenca_valida_ate" type="date" value="<?= e($d['licenca_valida_ate'] ?? '') ?>" required>
            <span class="mf-atalhos"><a href="#" data-somar="1">+1 mês</a><a href="#" data-somar="12">+1 ano</a></span>
          </div>
        </div>
      </section>

      <section class="mf__secao" id="mf-administrador" aria-labelledby="mf-h-administrador">
        <h3 id="mf-h-administrador">Primeiro administrador</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="admin_nome">Nome</label></div>
          <div class="mf-linha__campo"><input id="admin_nome" name="admin_nome" value="<?= $val('admin_nome') ?>" required maxlength="120"></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="admin_email">E-mail de acesso</label><small>Usado para entrar no sistema.</small></div>
          <div class="mf-linha__campo"><input id="admin_email" name="admin_email" type="email" value="<?= $val('admin_email') ?>" required></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="admin_senha">Senha provisória</label><small>Mínimo de 8 caracteres. Ele troca no perfil.</small></div>
          <div class="mf-linha__campo"><input id="admin_senha" name="admin_senha" type="text" minlength="8" required autocomplete="new-password"></div>
        </div>
      </section>
    </div>
  </div>

  <footer class="mf__rodape">
    <span class="mf__nota">O administrador entra com o e-mail e a senha provisória.</span>
    <a class="btn btn--ghost" href="./plataforma" data-mf-fechar>Cancelar</a>
    <button class="btn btn--primary" type="submit">Cadastrar oficina</button>
  </footer>

  <script>
    (function () {
      // Plano define o limite de usuários; os atalhos somam meses à validade da licença.
      var raiz = document.currentScript.closest('.mf');
      var plano = raiz.querySelector('#plano'), limite = raiz.querySelector('#limite_usuarios'), validade = raiz.querySelector('#licenca_valida_ate');
      plano.addEventListener('change', function () { limite.value = plano.selectedOptions[0].dataset.limite; });
      raiz.querySelectorAll('[data-somar]').forEach(function (a) {
        a.addEventListener('click', function (e) {
          e.preventDefault();
          // Soma a partir da validade atual ou de hoje, o que for depois (renovar não "perde" dias).
          var hoje = new Date(); hoje.setHours(0, 0, 0, 0);
          var base = validade.value ? new Date(validade.value + 'T00:00:00') : hoje;
          if (isNaN(base) || base < hoje) base = hoje;
          base.setMonth(base.getMonth() + parseInt(a.dataset.somar, 10));
          validade.value = base.getFullYear() + '-' + String(base.getMonth() + 1).padStart(2, '0') + '-' + String(base.getDate()).padStart(2, '0');
          validade.dispatchEvent(new Event('change', { bubbles: true }));
        });
      });
    })();
  </script>
</form>
