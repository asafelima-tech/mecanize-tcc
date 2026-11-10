<?php
/** Espera: $c (dados), $id (null = novo), $erro (opcional) */
$titulo = $id ? 'Editar cliente' : 'Novo cliente';
$v = fn($k) => e($c[$k] ?? '');
$pj = ($c['tipo_pessoa'] ?? 'PF') === 'PJ';
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= $titulo ?></h1>
    <p class="page-header__subtitle"><a href="./clientes">Clientes</a><?= $id ? ' · ' . $v('nome') : '' ?></p>
  </div>
</div>

<form class="card form-card" method="post" action="./cliente-salvar<?= $id ? '?id=' . (int) $id : '' ?>">
  <?= csrf_field() ?>
  <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

  <div class="form-section">
    <div class="form-row-4">
      <div class="form-field">
        <label for="tipo_pessoa">Tipo</label>
        <select id="tipo_pessoa" name="tipo_pessoa">
          <option value="PF" <?= $pj ? '' : 'selected' ?>>Pessoa física</option>
          <option value="PJ" <?= $pj ? 'selected' : '' ?>>Pessoa jurídica</option>
        </select>
      </div>
      <div class="form-field span-2">
        <label for="nome"><?= $pj ? 'Razão social / nome' : 'Nome completo' ?> *</label>
        <input id="nome" name="nome" value="<?= $v('nome') ?>" required maxlength="150" autofocus>
      </div>
      <div class="form-field">
        <label for="documento" id="rotulo-doc"><?= $pj ? 'CNPJ' : 'CPF' ?></label>
        <input id="documento" name="documento" value="<?= e(documento_fmt($c['documento'] ?? '')) ?>" data-mascara="documento" inputmode="numeric">
      </div>
    </div>
  </div>

  <div class="form-section">
    <div class="form-row-3">
      <div class="form-field"><label for="telefone">Telefone / WhatsApp</label><input id="telefone" name="telefone" value="<?= e(telefone_fmt($c['telefone'] ?? '')) ?>" data-mascara="telefone" inputmode="tel"></div>
      <div class="form-field span-2"><label for="email">E-mail</label><input id="email" name="email" type="email" value="<?= $v('email') ?>" maxlength="150"></div>
    </div>
    <label class="check" style="margin-top:10px"><input type="checkbox" name="aceita_contato" value="1" <?= !empty($c['aceita_contato']) ? 'checked' : '' ?>> O cliente aceita receber contato da oficina (WhatsApp, e-mail)</label>
  </div>

  <div class="form-section">
    <div class="form-row-4">
      <div class="form-field"><label for="cep">CEP</label><input id="cep" name="cep" value="<?= $v('cep') ?>" data-mascara="cep" inputmode="numeric"></div>
      <div class="form-field span-3"><label for="endereco">Endereço</label><input id="endereco" name="endereco" value="<?= $v('endereco') ?>" maxlength="255"></div>
    </div>
    <div class="form-row-3" style="margin-top:12px">
      <div class="form-field"><label for="bairro">Bairro</label><input id="bairro" name="bairro" value="<?= $v('bairro') ?>" maxlength="100"></div>
      <div class="form-field"><label for="cidade">Cidade</label><input id="cidade" name="cidade" value="<?= $v('cidade') ?>" maxlength="100"></div>
      <div class="form-field"><label for="uf">UF</label><input id="uf" name="uf" value="<?= $v('uf') ?>" maxlength="2" style="text-transform:uppercase"></div>
    </div>
  </div>

  <div class="form-field"><label for="observacoes">Observações</label><textarea id="observacoes" name="observacoes" rows="3"><?= $v('observacoes') ?></textarea></div>

  <div class="form-acoes">
    <a class="btn btn--ghost" href="<?= $id ? './cliente?id=' . (int) $id : './clientes' ?>">Cancelar</a>
    <button class="btn btn--primary">Salvar cliente</button>
  </div>
</form>

<script>
  document.getElementById('tipo_pessoa').addEventListener('change', function () {
    var pj = this.value === 'PJ';
    document.getElementById('rotulo-doc').textContent = pj ? 'CNPJ' : 'CPF';
    document.querySelector('label[for=nome]').textContent = (pj ? 'Razão social / nome' : 'Nome completo') + ' *';
  });
</script>
