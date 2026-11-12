<?php
/**
 * Cadastro e edição de veículo, no mesmo formato da ficha de cliente: seções (identificação,
 * características, detalhes) e, ao lado, a prévia do veículo com a placa e as ações de salvar.
 * Os campos, nomes, validações e o destino do formulário são os mesmos de sempre.
 * Espera: $v, $id, $erro, $clientes, $marcas
 */
$titulo = $id ? 'Editar veículo' : 'Novo veículo';
$val = fn($k) => e($v[$k] ?? '');
$marcaAtual = $v['marca'] ?? ($_POST['marca'] ?? '');
$semModelo = empty($v['modelo_id']) && !empty($v['modelo_texto']);
$placa = trim((string) ($v['placa'] ?? ''));
$voltar = $id ? './veiculo?id=' . (int) $id : './veiculos';
$clienteNome = '';
foreach ($clientes as $c) {
    if ((int) ($v['cliente_id'] ?? 0) === (int) $c['id']) { $clienteNome = $c['nome']; break; }
}
$detalhes = array_filter([$v['ano'] ?? '', $v['cor'] ?? '', $v['combustivel'] ?? ''], fn($x) => (string) $x !== '');
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= $titulo ?></h1>
    <p class="page-header__subtitle"><a href="./veiculos">Veículos</a><?= $id ? ' · ' . $val('placa') : ' · vinculado a um cliente da oficina' ?></p>
  </div>
</div>

<form class="form-ficha" method="post" action="./veiculo-salvar<?= $id ? '?id=' . (int) $id : '' ?>">
  <?= csrf_field() ?>

  <div class="form-ficha__corpo">
    <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

    <section class="card form-ficha__secao" aria-labelledby="sec-identificacao">
      <header class="form-ficha__cabeca">
        <span class="form-ficha__icone"><?= icone('plate', 18) ?></span>
        <div><h2 id="sec-identificacao">Identificação</h2><p>De quem é o veículo e a placa que identifica ele nas OS e na agenda.</p></div>
      </header>
      <div class="form-ficha__grade">
        <div class="form-field form-ficha__c8">
          <label for="cliente_id">Cliente responsável *</label>
          <select id="cliente_id" name="cliente_id" required>
            <option value="">Escolha o cliente</option>
            <?php foreach ($clientes as $c): ?>
              <option value="<?= (int) $c['id'] ?>" data-nome="<?= e($c['nome']) ?>" <?= (int) ($v['cliente_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?><?= $c['documento'] ? ' — ' . e(documento_fmt($c['documento'])) : '' ?></option>
            <?php endforeach; ?>
          </select>
          <span class="form-dica">Não está na lista? <a href="./cliente-form">Cadastre o cliente</a> primeiro.</span>
        </div>
        <div class="form-field form-ficha__c4">
          <label for="placa">Placa *</label>
          <input id="placa" name="placa" class="campo-placa" value="<?= $val('placa') ?>" data-mascara="placa" required placeholder="ABC1D23" style="text-transform:uppercase">
          <span class="form-dica">Modelo antigo ou Mercosul.</span>
        </div>
      </div>
    </section>

    <section class="card form-ficha__secao" aria-labelledby="sec-caracteristicas">
      <header class="form-ficha__cabeca">
        <span class="form-ficha__icone"><?= icone('car', 18) ?></span>
        <div><h2 id="sec-caracteristicas">Características</h2><p>Marca, modelo e ano, usados para achar o veículo e nos relatórios.</p></div>
      </header>
      <div class="form-ficha__grade">
        <div class="form-field form-ficha__c4">
          <label for="marca">Marca</label>
          <select id="marca" name="marca">
            <option value="">Escolha a marca</option>
            <?php foreach ($marcas as $m): ?><option <?= $marcaAtual === $m ? 'selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-field form-ficha__c5">
          <div class="form-ficha__rotulo-linha">
            <label for="modelo_id">Modelo *</label>
            <a href="#" id="alternar-modelo" class="form-ficha__alternar"><?= $semModelo ? 'Escolher da lista' : 'Não está na lista?' ?></a>
          </div>
          <select id="modelo_id" name="modelo_id" data-atual="<?= (int) ($v['modelo_id'] ?? 0) ?>" class="<?= $semModelo ? 'oculto' : '' ?>">
            <option value="">Escolha a marca primeiro</option>
          </select>
          <input id="modelo_texto" name="modelo_texto" value="<?= $val('modelo_texto') ?>" maxlength="120" placeholder="Ex.: Fiat Uno Mille 1.0" class="<?= $semModelo ? '' : 'oculto' ?>">
        </div>
        <div class="form-field form-ficha__c3"><label for="ano">Ano</label><input id="ano" name="ano" type="number" min="1900" max="<?= (int) date('Y') + 1 ?>" value="<?= $val('ano') ?>" placeholder="<?= (int) date('Y') ?>"></div>
      </div>
    </section>

    <section class="card form-ficha__secao" aria-labelledby="sec-detalhes">
      <header class="form-ficha__cabeca">
        <span class="form-ficha__icone"><?= icone('checklist', 18) ?></span>
        <div><h2 id="sec-detalhes">Detalhes adicionais</h2><p>Ajudam a reconhecer o veículo no pátio e a escolher peças e fluidos.</p></div>
      </header>
      <div class="form-ficha__grade">
        <div class="form-field form-ficha__c6 form-ficha__m3">
          <label for="cor">Cor</label>
          <select id="cor" name="cor"><option value="">—</option>
            <?php foreach (Veiculo::CORES as $cor): ?><option <?= ($v['cor'] ?? '') === $cor ? 'selected' : '' ?>><?= e($cor) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-field form-ficha__c6 form-ficha__m3">
          <label for="combustivel">Combustível</label>
          <select id="combustivel" name="combustivel"><option value="">—</option>
            <?php foreach (Veiculo::COMBUSTIVEIS as $cb): ?><option <?= ($v['combustivel'] ?? '') === $cb ? 'selected' : '' ?>><?= e($cb) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-field form-ficha__c12"><label for="observacoes">Observações</label><textarea id="observacoes" name="observacoes" rows="3" placeholder="Avarias, acessórios, particularidades…"><?= $val('observacoes') ?></textarea></div>
      </div>
    </section>
  </div>

  <aside class="form-ficha__lado" aria-label="Resumo do veículo">
    <div class="card form-ficha__resumo">
      <div class="placa-previa<?= $placa === '' ? ' placa-previa--vazia' : '' ?>" aria-hidden="true">
        <span class="placa-previa__faixa">BRASIL</span>
        <span class="placa-previa__numero" data-previa-placa><?= $placa !== '' ? e(mb_strtoupper($placa)) : 'ABC1D23' ?></span>
      </div>
      <div class="form-ficha__veiculo">
        <strong data-previa-modelo><?= !empty($v['modelo']) ? e($v['modelo']) : ($id ? 'Modelo não informado' : 'Novo veículo') ?></strong>
        <span data-previa-detalhes><?= $detalhes ? e(implode(' · ', $detalhes)) : 'Ano, cor e combustível' ?></span>
      </div>
      <dl class="form-ficha__dados form-ficha__dados--lista">
        <div><dt><?= icone('user', 14) ?> Cliente</dt><dd data-previa-cliente><?= $clienteNome !== '' ? e($clienteNome) : '—' ?></dd></div>
        <?php if ($id && !empty($v['criado_em'])): ?><div><dt><?= icone('calendar', 14) ?> Cadastrado em</dt><dd><?= data_br($v['criado_em']) ?></dd></div><?php endif; ?>
      </dl>
      <div class="form-ficha__acoes">
        <button class="btn btn--primary"><?= icone('check') ?> Salvar veículo</button>
        <a class="btn btn--ghost" href="<?= $voltar ?>">Cancelar</a>
      </div>
      <p class="form-ficha__nota">Campos com * são obrigatórios.</p>
    </div>
  </aside>
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
          previa();
        });
    }
    marca.addEventListener('change', function () { modelo.dataset.atual = ''; carregar(); });
    alternar.addEventListener('click', function (e) {
      e.preventDefault();
      var usarTexto = texto.classList.contains('oculto');
      texto.classList.toggle('oculto', !usarTexto);
      modelo.classList.toggle('oculto', usarTexto);
      if (usarTexto) { modelo.value = ''; texto.focus(); } else { texto.value = ''; }
      alternar.textContent = usarTexto ? 'Escolher da lista' : 'Não está na lista?';
      previa();
    });

    // Prévia ao lado: acompanha o que é preenchido (não muda nada do que é enviado).
    var $ = function (s) { return document.getElementById(s); };
    var pPlaca = document.querySelector('[data-previa-placa]'), pModelo = document.querySelector('[data-previa-modelo]'),
        pDet = document.querySelector('[data-previa-detalhes]'), pCli = document.querySelector('[data-previa-cliente]');
    var modeloInicial = pModelo.textContent;
    function previa() {
      pPlaca.textContent = ($('placa').value.trim() || 'ABC1D23').toUpperCase();
      pPlaca.parentNode.classList.toggle('placa-previa--vazia', !$('placa').value.trim());
      var nomeModelo = !texto.classList.contains('oculto') ? texto.value.trim()
        : (modelo.value ? marca.value + ' ' + modelo.options[modelo.selectedIndex].text : '');
      pModelo.textContent = nomeModelo || (marca.value ? marca.value : modeloInicial);
      var det = [$('ano').value.trim(), $('cor').value, $('combustivel').value].filter(Boolean);
      pDet.textContent = det.length ? det.join(' · ') : 'Ano, cor e combustível';
      var c = $('cliente_id'); var o = c.options[c.selectedIndex];
      pCli.textContent = o && o.value ? o.dataset.nome : '—';
    }
    ['placa', 'ano', 'modelo_texto'].forEach(function (id) { $(id).addEventListener('input', previa); });
    ['cliente_id', 'modelo_id', 'cor', 'combustivel'].forEach(function (id) { $(id).addEventListener('change', previa); });
    carregar();
  })();
</script>
