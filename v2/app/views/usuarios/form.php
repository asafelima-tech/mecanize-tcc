<?php
/**
 * Cadastro e edição de usuário, no padrão de janela (modal) das telas de criar e editar.
 * Topo com título e fechar, seções à esquerda, linhas "rótulo à esquerda, campo à direita" e o rodapé
 * com as ações. Aberta pelo js/modal.js sobre a tela de onde veio; acessada direto, ocupa a página.
 * São dois formulários (salvar e excluir), por isso a raiz é um div e o botão do rodapé usa form="".
 * Os campos, nomes, validações e permissões são os mesmos de sempre: ninguém muda o próprio perfil
 * e o próprio usuário não pode ser excluído por aqui.
 * Espera: $u, $id, $erro
 */
$titulo = $id ? 'Editar usuário' : 'Novo usuário';
$val = fn($k) => e($u[$k] ?? '');
$eu = $id !== null && $id === Contexto::usuarioId();
$perfilAtual = $u['perfil'] ?? 'ATENDENTE';
$nome = trim((string) ($u['nome'] ?? ''));
$descPerfil = [
    'ADMIN'     => 'Tudo da oficina, inclusive financeiro, relatórios, configurações e usuários.',
    'ATENDENTE' => 'Dia a dia: clientes, veículos, agenda, OS e caixa.',
    'TECNICO'   => 'Só os próprios serviços, pelo celular. A conclusão passa pelo administrador.',
];
$bloqueado = !empty($u['bloqueado_ate']) && strtotime($u['bloqueado_ate']) > time();
$situacao = !isset($u['ativo']) ? null : ($u['ativo'] ? ($bloqueado ? 'Bloqueado por tentativas' : 'Ativo') : 'Inativo');
$voltar = './usuarios';
$secoes = [['identificacao', 'user', 'Identificação'], ['acesso', 'lock', 'Acesso'], ['comissao', 'percent', 'Contato e comissão']];
?>
<div class="mf" role="group" aria-labelledby="mf-titulo">
  <header class="mf__topo">
    <h2 class="mf__titulo" id="mf-titulo"><?= $titulo ?><?php if ($id && $nome !== ''): ?><span><?= e($nome) ?></span><?php endif; ?></h2>
    <a class="mf__fechar" href="<?= $voltar ?>" data-mf-fechar aria-label="Fechar" title="Fechar"><?= icone('close', 18, 1.75) ?></a>
  </header>

  <form class="mf__corpo" id="mf-usuario" method="post" action="./usuario-salvar<?= $id ? '?id=' . (int) $id : '' ?>" autocomplete="off">
    <?= csrf_field() ?>

    <nav class="mf__nav" aria-label="Seções do cadastro">
      <?php foreach ($secoes as $k => [$ancora, $ic, $rotulo]): ?>
        <a href="#mf-<?= $ancora ?>"<?= $k === 0 ? ' class="is-ativa" aria-current="true"' : '' ?>><?= icone($ic, 18) ?><span><?= $rotulo ?></span></a>
      <?php endforeach; ?>
      <?php if ($situacao || ($id && !empty($u['criado_em']))): ?>
        <dl class="mf__nav-rodape">
          <?php if ($situacao): ?><div><dt>Situação</dt><dd><?= e($situacao) ?></dd></div><?php endif; ?>
          <?php if ($id && !empty($u['criado_em'])): ?><div><dt>Criado em</dt><dd><?= data_br($u['criado_em']) ?></dd></div><?php endif; ?>
        </dl>
      <?php endif; ?>
    </nav>

    <div class="mf__conteudo">
      <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

      <section class="mf__secao" id="mf-identificacao" aria-labelledby="mf-h-identificacao">
        <h3 id="mf-h-identificacao">Identificação</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="nome">Nome</label><small>Aparece nas OS e nos relatórios.</small></div>
          <div class="mf-linha__campo"><input id="nome" name="nome" value="<?= $val('nome') ?>" required maxlength="120" autocomplete="off" autofocus></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="email">E-mail de acesso</label><small>Usado para entrar no sistema.</small></div>
          <div class="mf-linha__campo"><input id="email" name="email" type="email" value="<?= $val('email') ?>" required maxlength="150" autocomplete="off" placeholder="nome@exemplo.com"></div>
        </div>
      </section>

      <section class="mf__secao" id="mf-acesso" aria-labelledby="mf-h-acesso">
        <h3 id="mf-h-acesso">Acesso</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo">
            <span id="rotulo-perfil">Perfil</span>
            <small id="perfil-desc"><?= $eu ? 'Você não pode mudar o próprio perfil.' : e($descPerfil[$perfilAtual] ?? '') ?></small>
          </div>
          <?php if ($eu): ?>
            <?php // Na própria conta o perfil não muda: aparece só o valor (como em "Meu perfil"), sem um seletor apagado. ?>
            <div class="mf-linha__campo mf-linha__campo--fim">
              <span class="mf-leitura"><?= e(Usuario::PERFIS[$perfilAtual] ?? $perfilAtual) ?></span>
              <input type="hidden" name="perfil" value="<?= $val('perfil') ?>">
            </div>
          <?php else: ?>
            <div class="mf-linha__campo">
              <div class="segmento" role="radiogroup" aria-labelledby="rotulo-perfil" aria-describedby="perfil-desc">
                <?php foreach (Usuario::PERFIS as $k => $r): ?>
                  <label><input type="radio" name="perfil" value="<?= $k ?>" data-desc="<?= e($descPerfil[$k] ?? '') ?>" <?= $perfilAtual === $k ? 'checked' : '' ?>><span><?= e($r) ?></span></label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="senha"><?= $id ? 'Nova senha' : 'Senha' ?></label><small><?= $id ? 'Preencha só para trocar a senha.' : 'A pessoa entra com o e-mail e esta senha.' ?></small></div>
          <div class="mf-linha__campo"><input id="senha" name="senha" type="password" minlength="6" <?= $id ? '' : 'required' ?> autocomplete="new-password" placeholder="<?= $id ? 'Deixe em branco para manter' : 'Mínimo de 6 caracteres' ?>"></div>
        </div>
      </section>

      <section class="mf__secao" id="mf-comissao" aria-labelledby="mf-h-comissao">
        <h3 id="mf-h-comissao">Contato e comissão</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="telefone">Telefone</label></div>
          <div class="mf-linha__campo"><input id="telefone" name="telefone" value="<?= e(telefone_fmt($u['telefone'] ?? '')) ?>" data-mascara="telefone" inputmode="tel" placeholder="(00) 00000-0000"></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="comissao_tipo">Comissão</label><small>Calculada nas OS em que a pessoa é responsável.</small></div>
          <div class="mf-linha__campo mf-linha__campo--par">
            <select id="comissao_tipo" name="comissao_tipo">
              <?php foreach (Usuario::COMISSOES as $k => $r): ?><option value="<?= $k ?>" <?= ($u['comissao_tipo'] ?? 'NENHUMA') === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?>
            </select>
            <span class="mf-sufixo"><input id="comissao_percentual" name="comissao_percentual" value="<?= e(($pc = (string) ($u['comissao_percentual'] ?? '')) !== '' && str_contains($pc, '.') ? rtrim(rtrim($pc, '0'), '.') : $pc) ?>" inputmode="decimal" placeholder="0" aria-label="Percentual da comissão"><span aria-hidden="true">%</span></span>
          </div>
        </div>
      </section>

      <?php if ($id && !$eu): ?>
        <section class="mf__secao" id="mf-excluir" aria-labelledby="mf-h-excluir">
          <h3 id="mf-h-excluir">Excluir</h3>
          <div class="mf-linha">
            <div class="mf-linha__rotulo"><span>Excluir usuário</span><small>Só é possível sem nada registrado no sistema. Se houver, desative.</small></div>
            <div class="mf-linha__campo mf-linha__campo--fim">
              <button class="btn btn--danger-ghost btn--sm" type="submit" form="mf-usuario-excluir"><?= icone('trash') ?> Excluir</button>
            </div>
          </div>
        </section>
      <?php endif; ?>
    </div>
  </form>

  <footer class="mf__rodape">
    <span class="mf__nota"><?= $id ? 'Nome e e-mail são obrigatórios.' : 'Nome, e-mail e senha são obrigatórios.' ?></span>
    <a class="btn btn--ghost" href="<?= $voltar ?>" data-mf-fechar>Cancelar</a>
    <button class="btn btn--primary" type="submit" form="mf-usuario"><?= $id ? 'Salvar alterações' : 'Criar usuário' ?></button>
  </footer>

  <?php if ($id && !$eu): ?>
    <form id="mf-usuario-excluir" method="post" action="./usuario-excluir?id=<?= (int) $id ?>" hidden data-mf-ignorar-alteracao
          data-confirmar="Excluir este usuário? Só é possível se ele não tiver nada registrado no sistema; caso contrário, desative.">
      <?= csrf_field() ?>
    </form>
  <?php endif; ?>

  <script>
    (function () {
      var raiz = document.currentScript.closest('.mf');
      // Comissão: sem comissão, o percentual não se aplica.
      var t = raiz.querySelector('#comissao_tipo'), p = raiz.querySelector('#comissao_percentual');
      function sync() { p.disabled = t.value === 'NENHUMA'; }
      t.addEventListener('change', sync); sync();
      // Perfil: a dica embaixo do rótulo explica o perfil escolhido.
      var desc = raiz.querySelector('#perfil-desc');
      raiz.querySelectorAll('input[name=perfil][type=radio]').forEach(function (r) {
        r.addEventListener('change', function () { if (r.checked) desc.textContent = r.dataset.desc; });
      });
    })();
  </script>
</div>
