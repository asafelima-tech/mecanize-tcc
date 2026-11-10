<?php /** Espera: $os, $clientes, $tecnicos, $erro */ $titulo = 'Novo orçamento'; $val = fn($k) => e($os[$k] ?? ''); ?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Novo orçamento</h1>
    <p class="page-header__subtitle"><a href="./ordens">Ordens de serviço</a> · <?= !empty($os['agendamento_id']) ? 'a partir de um <a href="./agenda">agendamento</a> · ' : '' ?>os itens são incluídos na próxima tela</p>
  </div>
</div>

<form class="card form-card" method="post" action="./os-criar">
  <?= csrf_field() ?>
  <?php if (!empty($os['agendamento_id'])): ?><input type="hidden" name="agendamento_id" value="<?= (int) $os['agendamento_id'] ?>"><?php endif; ?>
  <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

  <div class="form-section">
    <div class="form-row-3">
      <div class="form-field">
        <label for="cliente_id">Cliente *</label>
        <select id="cliente_id" name="cliente_id" required autofocus>
          <option value="">Escolha o cliente</option>
          <?php foreach ($clientes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) ($os['cliente_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?>
        </select>
        <span class="form-dica"><a href="./cliente-form">Cadastrar cliente</a></span>
      </div>
      <div class="form-field">
        <label for="veiculo_id">Veículo *</label>
        <select id="veiculo_id" name="veiculo_id" required data-atual="<?= (int) ($os['veiculo_id'] ?? 0) ?>"><option value="">Escolha o cliente primeiro</option></select>
        <span class="form-dica" id="link-veiculo"></span>
      </div>
      <div class="form-field"><label for="km_veiculo">Quilometragem</label><input id="km_veiculo" name="km_veiculo" value="<?= $val('km_veiculo') ?>" inputmode="numeric"></div>
    </div>
  </div>

  <div class="form-field"><label for="defeito_relatado">O que o cliente relatou</label><textarea id="defeito_relatado" name="defeito_relatado" rows="3"><?= $val('defeito_relatado') ?></textarea></div>

  <div class="form-section" style="margin-top:16px">
    <div class="form-row-3">
      <div class="form-field">
        <label for="tecnico_id">Técnico responsável</label>
        <select id="tecnico_id" name="tecnico_id"><option value="">—</option>
          <?php foreach ($tecnicos as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) ($os['tecnico_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-field"><label for="previsao_conclusao">Previsão de entrega</label><input id="previsao_conclusao" name="previsao_conclusao" type="datetime-local" value="<?= $val('previsao_conclusao') ?>"></div>
      <div class="form-field"><label for="validade_orcamento">Orçamento válido até</label><input id="validade_orcamento" name="validade_orcamento" type="date" value="<?= $val('validade_orcamento') ?>"></div>
    </div>
  </div>

  <div class="form-acoes">
    <a class="btn btn--ghost" href="./ordens">Cancelar</a>
    <button class="btn btn--primary">Abrir orçamento</button>
  </div>
</form>

<script>
  (function () {
    var cli = document.getElementById('cliente_id'), vei = document.getElementById('veiculo_id'), link = document.getElementById('link-veiculo');
    function carregar() {
      var atual = vei.dataset.atual;
      link.innerHTML = cli.value ? '<a href="./veiculo-form?cliente=' + encodeURIComponent(cli.value) + '">Cadastrar veículo deste cliente</a>' : '';
      vei.innerHTML = '<option value="">' + (cli.value ? 'Carregando…' : 'Escolha o cliente primeiro') + '</option>';
      if (!cli.value) return;
      fetch('./os-veiculos?cliente=' + encodeURIComponent(cli.value), { headers: { 'X-Requested-With': 'fetch' } })
        .then(function (r) { return r.json(); })
        .then(function (lista) {
          vei.innerHTML = '<option value="">' + (lista.length ? 'Escolha o veículo' : 'Este cliente não tem veículo') + '</option>';
          lista.forEach(function (v) {
            var o = document.createElement('option');
            o.value = v.id; o.textContent = v.rotulo; o.selected = String(v.id) === atual || lista.length === 1;
            vei.appendChild(o);
          });
        });
    }
    cli.addEventListener('change', function () { vei.dataset.atual = ''; carregar(); });
    carregar();
  })();
</script>
