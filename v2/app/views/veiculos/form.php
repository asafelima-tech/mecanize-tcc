<?php
/** Espera: $v, $id, $erro, $clientes, $marcas */
$titulo = $id ? 'Editar veículo' : 'Novo veículo';
$val = fn($k) => e($v[$k] ?? '');
$marcaAtual = $v['marca'] ?? ($_POST['marca'] ?? '');
$semModelo = empty($v['modelo_id']) && !empty($v['modelo_texto']);
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= $titulo ?></h1>
    <p class="page-header__subtitle"><a href="./veiculos">Veículos</a><?= $id ? ' · ' . $val('placa') : '' ?></p>
  </div>
</div>

<form class="card form-card" method="post" action="./veiculo-salvar<?= $id ? '?id=' . (int) $id : '' ?>">
  <?= csrf_field() ?>
  <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

  <div class="form-section">
    <div class="form-row-3">
      <div class="form-field span-2">
        <label for="cliente_id">Cliente *</label>
        <select id="cliente_id" name="cliente_id" required>
          <option value="">Escolha o cliente</option>
          <?php foreach ($clientes as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) ($v['cliente_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?><?= $c['documento'] ? ' — ' . e(documento_fmt($c['documento'])) : '' ?></option>
          <?php endforeach; ?>
        </select>
        <span class="form-dica">Não está na lista? <a href="./cliente-form">Cadastre o cliente</a> primeiro.</span>
      </div>
      <div class="form-field">
        <label for="placa">Placa *</label>
        <input id="placa" name="placa" value="<?= $val('placa') ?>" data-mascara="placa" required placeholder="ABC-1234 ou ABC1D23" style="text-transform:uppercase">
      </div>
    </div>
  </div>

  <div class="form-section">
    <div class="form-row-3">
      <div class="form-field">
        <label for="marca">Marca</label>
        <select id="marca" name="marca">
          <option value="">Escolha a marca</option>
          <?php foreach ($marcas as $m): ?><option <?= $marcaAtual === $m ? 'selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-field span-2">
        <label for="modelo_id">Modelo *</label>
        <select id="modelo_id" name="modelo_id" data-atual="<?= (int) ($v['modelo_id'] ?? 0) ?>" class="<?= $semModelo ? 'oculto' : '' ?>">
          <option value="">Escolha a marca primeiro</option>
        </select>
        <input id="modelo_texto" name="modelo_texto" value="<?= $val('modelo_texto') ?>" maxlength="120" placeholder="Ex.: Fiat Uno Mille 1.0" class="<?= $semModelo ? '' : 'oculto' ?>">
        <a href="#" id="alternar-modelo" class="form-dica"><?= $semModelo ? 'Escolher da lista' : 'O modelo não está na lista' ?></a>
      </div>
    </div>
    <div class="form-row-3" style="margin-top:12px">
      <div class="form-field"><label for="ano">Ano</label><input id="ano" name="ano" type="number" min="1900" max="<?= (int) date('Y') + 1 ?>" value="<?= $val('ano') ?>"></div>
      <div class="form-field">
        <label for="cor">Cor</label>
        <select id="cor" name="cor"><option value="">—</option>
          <?php foreach (Veiculo::CORES as $cor): ?><option <?= ($v['cor'] ?? '') === $cor ? 'selected' : '' ?>><?= e($cor) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-field">
        <label for="combustivel">Combustível</label>
        <select id="combustivel" name="combustivel"><option value="">—</option>
          <?php foreach (Veiculo::COMBUSTIVEIS as $cb): ?><option <?= ($v['combustivel'] ?? '') === $cb ? 'selected' : '' ?>><?= e($cb) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <div class="form-field"><label for="observacoes">Observações</label><textarea id="observacoes" name="observacoes" rows="3"><?= $val('observacoes') ?></textarea></div>

  <div class="form-acoes">
    <a class="btn btn--ghost" href="<?= $id ? './veiculo?id=' . (int) $id : './veiculos' ?>">Cancelar</a>
    <button class="btn btn--primary">Salvar veículo</button>
  </div>
</form>

<script>
  (function () {
    var marca = document.getElementById('marca'), modelo = document.getElementById('modelo_id'),
        texto = document.getElementById('modelo_texto'), alternar = document.getElementById('alternar-modelo');

    function carregar() {
      var atual = modelo.dataset.atual;
      modelo.innerHTML = '<option value="">' + (marca.value ? 'Carregando…' : 'Escolha a marca primeiro') + '</option>';
      if (!marca.value) return;
      fetch('./veiculo-modelos?marca=' + encodeURIComponent(marca.value), { headers: { 'X-Requested-With': 'fetch' } })
        .then(function (r) { return r.json(); })
        .then(function (lista) {
          modelo.innerHTML = '<option value="">Escolha o modelo</option>';
          lista.forEach(function (m) {
            var o = document.createElement('option');
            o.value = m.id; o.textContent = m.nome; o.selected = String(m.id) === atual;
            modelo.appendChild(o);
          });
        });
    }
    marca.addEventListener('change', function () { modelo.dataset.atual = ''; carregar(); });
    alternar.addEventListener('click', function (e) {
      e.preventDefault();
      var usarTexto = texto.classList.contains('oculto');
      texto.classList.toggle('oculto', !usarTexto);
      modelo.classList.toggle('oculto', usarTexto);
      if (usarTexto) { modelo.value = ''; texto.focus(); } else { texto.value = ''; }
      alternar.textContent = usarTexto ? 'Escolher da lista' : 'O modelo não está na lista';
    });
    carregar();
  })();
</script>
