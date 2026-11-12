<?php
/**
 * Cadastro e edição de usuário, no formato de ficha das outras telas de cadastro: identificação,
 * acesso e permissões (perfil em cartões, senha) e complementares (telefone e comissão); ao lado,
 * o resumo da conta e as ações de salvar.
 * Os campos, nomes, validações e permissões são os mesmos de sempre: ninguém muda o próprio perfil
 * e o próprio usuário não pode ser excluído por aqui.
 * Espera: $u, $id, $erro
 */
$titulo = $id ? 'Editar usuário' : 'Novo usuário';
$val = fn($k) => e($u[$k] ?? '');
$eu = $id !== null && $id === Contexto::usuarioId();
$perfilAtual = $u['perfil'] ?? 'ATENDENTE';
$nome = trim((string) ($u['nome'] ?? ''));
$partes = $nome !== '' ? preg_split('/\s+/', $nome) : [];
$iniciais = $partes ? mb_strtoupper(mb_substr($partes[0], 0, 1) . (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '')) : '';
$descPerfil = [
    'ADMIN'     => ['lock', 'Tudo da oficina, inclusive financeiro, relatórios, configurações e usuários.'],
    'ATENDENTE' => ['users', 'Operação do dia a dia: clientes, veículos, agenda, OS e caixa.'],
    'TECNICO'   => ['mechanic', 'As mesmas permissões do atendente; responde pelas OS e pode ter comissão.'],
];
$bloqueado = !empty($u['bloqueado_ate']) && strtotime($u['bloqueado_ate']) > time();
$situacao = !isset($u['ativo']) ? null : ($u['ativo'] ? ($bloqueado ? 'Bloqueado por tentativas' : 'Ativo') : 'Inativo');
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= $titulo ?></h1>
    <p class="page-header__subtitle"><a href="./usuarios">Usuários</a><?= $id ? ' · ' . $val('nome') : ' · acesso de uma pessoa da equipe ao sistema' ?></p>
  </div>
  <?php if ($id && !$eu): ?>
    <div class="page-header__actions">
      <form method="post" action="./usuario-excluir?id=<?= (int) $id ?>" data-confirmar="Excluir este usuário? Só é possível se ele não tiver nada registrado no sistema; caso contrário, desative.">
        <?= csrf_field() ?><button class="btn btn--danger-ghost btn--sm"><?= icone('trash') ?> Excluir</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<form class="form-ficha" method="post" action="./usuario-salvar<?= $id ? '?id=' . (int) $id : '' ?>" autocomplete="off">
  <?= csrf_field() ?>

  <div class="form-ficha__corpo">
    <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

    <section class="card form-ficha__secao" aria-labelledby="sec-identificacao">
      <header class="form-ficha__cabeca">
        <span class="form-ficha__icone"><?= icone('user', 18) ?></span>
        <div><h2 id="sec-identificacao">Identificação</h2><p>Quem é a pessoa e o e-mail que ela usa para entrar.</p></div>
      </header>
      <div class="identidade">
        <span class="identidade__avatar" aria-hidden="true" data-previa-iniciais><?= $iniciais !== '' ? e($iniciais) : icone('user', 24) ?></span>
        <div class="form-ficha__grade identidade__campos">
          <div class="form-field form-ficha__c6"><label for="nome">Nome *</label><input id="nome" name="nome" value="<?= $val('nome') ?>" required maxlength="120" autofocus></div>
          <div class="form-field form-ficha__c6"><label for="email">E-mail de acesso *</label>
            <span class="campo-icone"><?= icone('mail') ?><input id="email" name="email" type="email" value="<?= $val('email') ?>" required maxlength="150" autocomplete="off"></span></div>
          <p class="form-dica form-ficha__c12 identidade__dica">As iniciais do nome identificam a pessoa na barra lateral, nas OS e nos relatórios.</p>
        </div>
      </div>
    </section>

    <section class="card form-ficha__secao" aria-labelledby="sec-acesso">
      <header class="form-ficha__cabeca">
        <span class="form-ficha__icone"><?= icone('lock', 18) ?></span>
        <div><h2 id="sec-acesso">Acesso e permissões</h2><p>O perfil define o que a pessoa vê e pode fazer no sistema.</p></div>
      </header>
      <div class="form-field">
        <span class="form-ficha__rotulo" id="rotulo-perfil">Perfil *</span>
        <div class="perfis-opcoes" role="radiogroup" aria-labelledby="rotulo-perfil">
          <?php foreach (Usuario::PERFIS as $k => $r): [$ic, $txt] = $descPerfil[$k] ?? ['user', '']; ?>
            <label class="perfil-opcao<?= $eu ? ' is-travado' : '' ?>">
              <input type="radio" name="perfil" value="<?= $k ?>" <?= $perfilAtual === $k ? 'checked' : '' ?> <?= $eu ? 'disabled' : '' ?>>
              <span class="perfil-opcao__corpo">
                <span class="perfil-opcao__topo"><span class="perfil-opcao__icone"><?= icone($ic, 16) ?></span><strong><?= e($r) ?></strong><span class="perfil-opcao__marca" aria-hidden="true"><?= icone('check', 12, 2.5) ?></span></span>
                <small><?= e($txt) ?></small>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
        <?php if ($eu): ?><input type="hidden" name="perfil" value="<?= $val('perfil') ?>"><span class="form-dica"><?= icone('info', 13) ?> Você não pode mudar o próprio perfil.</span><?php endif; ?>
      </div>

      <div class="form-ficha__grade acesso-senha">
        <div class="form-field form-ficha__c6">
          <label for="senha"><?= $id ? 'Nova senha' : 'Senha *' ?></label>
          <span class="campo-icone"><?= icone('key') ?><input id="senha" name="senha" type="password" minlength="6" <?= $id ? '' : 'required' ?> autocomplete="new-password" placeholder="<?= $id ? 'Deixe em branco para manter' : 'Mínimo de 6 caracteres' ?>"></span>
        </div>
        <p class="form-ficha__c6 acesso-senha__dica"><?= icone('info', 14) ?><span><?= $id ? 'Preencha só para trocar a senha desta pessoa. Mínimo de 6 caracteres.' : 'A pessoa entra com este e-mail e esta senha. Mínimo de 6 caracteres.' ?></span></p>
      </div>
    </section>

    <section class="card form-ficha__secao" aria-labelledby="sec-complementar">
      <header class="form-ficha__cabeca">
        <span class="form-ficha__icone"><?= icone('percent', 18) ?></span>
        <div><h2 id="sec-complementar">Informações complementares</h2><p>Contato e a comissão calculada nas OS em que a pessoa é responsável.</p></div>
      </header>
      <div class="form-ficha__grade">
        <div class="form-field form-ficha__c4"><label for="telefone">Telefone</label>
          <span class="campo-icone"><?= icone('phone') ?><input id="telefone" name="telefone" value="<?= e(telefone_fmt($u['telefone'] ?? '')) ?>" data-mascara="telefone"></span></div>
        <div class="form-field form-ficha__c5">
          <label for="comissao_tipo">Comissão</label>
          <select id="comissao_tipo" name="comissao_tipo">
            <?php foreach (Usuario::COMISSOES as $k => $r): ?><option value="<?= $k ?>" <?= ($u['comissao_tipo'] ?? 'NENHUMA') === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-field form-ficha__c3">
          <label for="comissao_percentual">Percentual</label>
          <span class="campo-sufixo"><input id="comissao_percentual" name="comissao_percentual" value="<?= e(rtrim(rtrim((string) ($u['comissao_percentual'] ?? ''), '0'), '.')) ?>" inputmode="decimal" placeholder="0"><span>%</span></span>
        </div>
      </div>
    </section>
  </div>

  <aside class="form-ficha__lado" aria-label="Resumo do usuário">
    <div class="card form-ficha__resumo">
      <div class="form-ficha__perfil">
        <span class="form-ficha__avatar" data-previa-iniciais><?= $iniciais !== '' ? e($iniciais) : icone('user', 22) ?></span>
        <div class="form-ficha__perfil-texto">
          <strong data-previa-nome><?= $nome !== '' ? e($nome) : 'Novo usuário' ?></strong>
          <span data-previa-email><?= $val('email') !== '' ? $val('email') : 'e-mail de acesso' ?></span>
        </div>
      </div>
      <dl class="form-ficha__dados form-ficha__dados--lista">
        <div><dt><?= icone('lock', 14) ?> Perfil</dt><dd data-previa-perfil><?= e(Usuario::PERFIS[$perfilAtual] ?? $perfilAtual) ?></dd></div>
        <div><dt><?= icone('percent', 14) ?> Comissão</dt><dd data-previa-comissao>—</dd></div>
        <?php if ($situacao): ?><div><dt><?= icone('check', 14) ?> Situação</dt><dd><?= e($situacao) ?></dd></div><?php endif; ?>
        <?php if ($id && !empty($u['criado_em'])): ?><div><dt><?= icone('calendar', 14) ?> Criado em</dt><dd><?= data_br($u['criado_em']) ?></dd></div><?php endif; ?>
      </dl>
      <?php if ($eu): ?><p class="form-ficha__aviso"><?= icone('info', 14) ?> Esta é a sua conta.</p><?php endif; ?>
      <div class="form-ficha__acoes">
        <button class="btn btn--primary"><?= icone('check') ?> Salvar usuário</button>
        <a class="btn btn--ghost" href="./usuarios">Cancelar</a>
      </div>
      <p class="form-ficha__nota">Campos com * são obrigatórios.</p>
    </div>
  </aside>
</form>
<script>
  (function () {
    var t = document.getElementById('comissao_tipo'), p = document.getElementById('comissao_percentual');
    function sync() { p.disabled = t.value === 'NENHUMA'; p.closest('.form-field').classList.toggle('is-inativo', p.disabled); previa(); }

    // Resumo ao lado acompanha o preenchimento (não muda nada do que é enviado).
    var nome = document.getElementById('nome'), email = document.getElementById('email');
    var iconeVazio = <?= json_encode(icone('user', 22)) ?>;
    function previa() {
      var n = nome.value.trim(), partes = n ? n.split(/\s+/) : [];
      var ini = partes.length ? (partes[0].charAt(0) + (partes.length > 1 ? partes[partes.length - 1].charAt(0) : '')).toUpperCase() : '';
      document.querySelectorAll('[data-previa-iniciais]').forEach(function (el) { if (ini) el.textContent = ini; else el.innerHTML = iconeVazio; });
      document.querySelector('[data-previa-nome]').textContent = n || 'Novo usuário';
      document.querySelector('[data-previa-email]').textContent = email.value.trim() || 'e-mail de acesso';
      var r = document.querySelector('input[name=perfil]:checked');
      if (r) document.querySelector('[data-previa-perfil]').textContent = r.closest('label').querySelector('strong').textContent;
      document.querySelector('[data-previa-comissao]').textContent = t.value === 'NENHUMA' ? 'Sem comissão'
        : (p.value.trim() || '0') + t.options[t.selectedIndex].text.replace(/^%/, '%');
    }
    [nome, email, p].forEach(function (el) { el.addEventListener('input', previa); });
    document.querySelectorAll('input[name=perfil]').forEach(function (r) { r.addEventListener('change', previa); });
    t.addEventListener('change', sync); sync();
  })();
</script>
