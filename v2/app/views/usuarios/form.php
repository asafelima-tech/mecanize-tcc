<?php
/** Espera: $u, $id, $erro */
$titulo = $id ? 'Editar usuário' : 'Novo usuário';
$val = fn($k) => e($u[$k] ?? '');
$eu = $id !== null && $id === Contexto::usuarioId();
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= $titulo ?></h1>
    <p class="page-header__subtitle"><a href="./usuarios">Usuários</a><?= $id ? ' · ' . $val('nome') : '' ?></p>
  </div>
  <?php if ($id && !$eu): ?>
    <div class="page-header__actions">
      <form method="post" action="./usuario-excluir?id=<?= (int) $id ?>" data-confirmar="Excluir este usuário? Só é possível se ele não tiver nada registrado no sistema; caso contrário, desative.">
        <?= csrf_field() ?><button class="btn btn--danger-ghost btn--sm">Excluir</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<form class="card form-card" method="post" action="./usuario-salvar<?= $id ? '?id=' . (int) $id : '' ?>" autocomplete="off">
  <?= csrf_field() ?>
  <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

  <div class="form-section">
    <div class="form-row-3">
      <div class="form-field span-2"><label for="nome">Nome *</label><input id="nome" name="nome" value="<?= $val('nome') ?>" required maxlength="120" autofocus></div>
      <div class="form-field"><label for="telefone">Telefone</label><input id="telefone" name="telefone" value="<?= e(telefone_fmt($u['telefone'] ?? '')) ?>" data-mascara="telefone"></div>
    </div>
    <div class="form-row-3" style="margin-top:12px">
      <div class="form-field span-2"><label for="email">E-mail de acesso *</label><input id="email" name="email" type="email" value="<?= $val('email') ?>" required maxlength="150" autocomplete="off"></div>
      <div class="form-field">
        <label for="senha"><?= $id ? 'Nova senha' : 'Senha *' ?></label>
        <input id="senha" name="senha" type="password" minlength="6" <?= $id ? '' : 'required' ?> autocomplete="new-password" placeholder="<?= $id ? 'Deixe em branco para manter' : 'Mínimo de 6 caracteres' ?>">
      </div>
    </div>
  </div>

  <div class="form-section">
    <div class="form-row-3">
      <div class="form-field">
        <label for="perfil">Perfil *</label>
        <select id="perfil" name="perfil" <?= $eu ? 'disabled' : '' ?>>
          <?php foreach (Usuario::PERFIS as $k => $r): ?><option value="<?= $k ?>" <?= ($u['perfil'] ?? '') === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?>
        </select>
        <?php if ($eu): ?><input type="hidden" name="perfil" value="<?= $val('perfil') ?>"><span class="form-dica">Você não pode mudar o próprio perfil.</span><?php endif; ?>
      </div>
      <div class="form-field">
        <label for="comissao_tipo">Comissão</label>
        <select id="comissao_tipo" name="comissao_tipo">
          <?php foreach (Usuario::COMISSOES as $k => $r): ?><option value="<?= $k ?>" <?= ($u['comissao_tipo'] ?? 'NENHUMA') === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-field">
        <label for="comissao_percentual">Percentual (%)</label>
        <input id="comissao_percentual" name="comissao_percentual" value="<?= e(rtrim(rtrim((string) ($u['comissao_percentual'] ?? ''), '0'), '.')) ?>" inputmode="decimal">
      </div>
    </div>
    <p class="form-dica" style="margin-top:8px">Administrador: tudo da oficina. Atendente e técnico: a operação do dia a dia (clientes, veículos, OS, agenda, caixa).</p>
  </div>

  <div class="form-acoes">
    <a class="btn btn--ghost" href="./usuarios">Cancelar</a>
    <button class="btn btn--primary">Salvar usuário</button>
  </div>
</form>
<script>
  (function () {
    var t = document.getElementById('comissao_tipo'), p = document.getElementById('comissao_percentual');
    function sync() { p.disabled = t.value === 'NENHUMA'; }
    t.addEventListener('change', sync); sync();
  })();
</script>
