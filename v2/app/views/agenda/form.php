<?php /** Espera: $ag, $id, $erro, $clientes, $tecnicos */ $titulo = $id ? 'Editar agendamento' : 'Agendar'; $val = fn($k) => e($ag[$k] ?? ''); ?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= $titulo ?></h1>
    <p class="page-header__subtitle"><a href="./agenda?periodo=dia&data=<?= $val('data') ?>">Agenda</a></p>
  </div>
</div>

<form class="card form-card" method="post" action="./agenda-salvar<?= $id ? '?id=' . (int) $id : '' ?>">
  <?= csrf_field() ?>
  <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>
  <div class="form-section">
    <div class="form-row-3">
      <div class="form-field">
        <label for="cliente_id">Cliente *</label>
        <select id="cliente_id" name="cliente_id" required <?= $id ? '' : 'autofocus' ?>>
          <option value="">Escolha o cliente</option>
          <?php foreach ($clientes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) ($ag['cliente_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?>
        </select>
        <span class="form-dica"><a href="./cliente-form">Cadastrar cliente</a></span>
      </div>
      <div class="form-field">
        <label for="veiculo_id">Veículo</label>
        <select id="veiculo_id" name="veiculo_id" data-atual="<?= (int) ($ag['veiculo_id'] ?? 0) ?>"><option value="">Escolha o cliente primeiro</option></select>
      </div>
      <div class="form-field">
        <label for="tecnico_id">Técnico</label>
        <select id="tecnico_id" name="tecnico_id"><option value="">Qualquer um</option>
          <?php foreach ($tecnicos as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) ($ag['tecnico_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row-3" style="margin-top:12px">
      <div class="form-field"><label for="data">Data *</label><input id="data" name="data" type="date" value="<?= $val('data') ?>" required></div>
      <div class="form-field"><label for="hora">Hora *</label><input id="hora" name="hora" type="time" step="300" value="<?= $val('hora') ?>" required></div>
      <div class="form-field"><label for="duracao_min">Duração</label>
        <select id="duracao_min" name="duracao_min">
          <?php foreach ([30 => '30 min', 60 => '1 hora', 90 => '1h30', 120 => '2 horas', 180 => '3 horas', 240 => '4 horas', 480 => 'Dia todo (8h)'] as $m => $r): ?>
            <option value="<?= $m ?>" <?= (int) ($ag['duracao_min'] ?? 60) === $m ? 'selected' : '' ?>><?= $r ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>
  <div class="form-field"><label for="servico">Serviço *</label><input id="servico" name="servico" value="<?= $val('servico') ?>" maxlength="200" required placeholder="Ex.: revisão dos 30 mil km, barulho na suspensão"></div>
  <div class="form-field" style="margin-top:12px"><label for="observacoes">Observações</label><textarea id="observacoes" name="observacoes" rows="2"><?= $val('observacoes') ?></textarea></div>
  <p class="form-dica" style="margin-top:8px">Com técnico escolhido, ele não pode ter dois serviços no mesmo horário. Sem técnico, o horário não pode coincidir com outro agendamento.</p>
  <div class="form-acoes">
    <a class="btn btn--ghost" href="./agenda?periodo=dia&data=<?= $val('data') ?>">Cancelar</a>
    <button class="btn btn--primary"><?= $id ? 'Salvar' : 'Agendar' ?></button>
  </div>
</form>

<script>
  (function () {
    var cli = document.getElementById('cliente_id'), vei = document.getElementById('veiculo_id');
    function carregar() {
      var atual = vei.dataset.atual;
      vei.innerHTML = '<option value="">' + (cli.value ? 'Carregando…' : 'Escolha o cliente primeiro') + '</option>';
      if (!cli.value) return;
      fetch('./os-veiculos?cliente=' + encodeURIComponent(cli.value), { headers: { 'X-Requested-With': 'fetch' } })
        .then(function (r) { return r.json(); })
        .then(function (lista) {
          vei.innerHTML = '<option value="">' + (lista.length ? 'Sem veículo definido' : 'Este cliente não tem veículo') + '</option>';
          lista.forEach(function (v) {
            var o = document.createElement('option');
            o.value = v.id; o.textContent = v.rotulo; o.selected = String(v.id) === atual || (lista.length === 1 && !atual);
            vei.appendChild(o);
          });
        });
    }
    cli.addEventListener('change', function () { vei.dataset.atual = ''; carregar(); });
    carregar();
  })();
</script>
