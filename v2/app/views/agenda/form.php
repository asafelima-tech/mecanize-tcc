<?php
/**
 * Agendar / editar agendamento: identificação (cliente e veículo), data e horário em destaque (atalhos dos
 * próximos dias e horários comuns, duração em botões), serviço com o técnico, observações e, ao lado, o
 * resumo do agendamento com as ações.
 * Os campos (cliente_id, veiculo_id, tecnico_id, data, hora, duracao_min, servico, observacoes), as
 * validações e a regra de conflito de horário são os mesmos de sempre: os atalhos só preenchem os campos.
 * Espera: $ag, $id, $erro, $clientes, $tecnicos
 */
$titulo = $id ? 'Editar agendamento' : 'Agendar';
$val = fn($k) => e($ag[$k] ?? '');
$duracoes = [30 => '30 min', 60 => '1 hora', 90 => '1h30', 120 => '2 horas', 180 => '3 horas', 240 => '4 horas', 480 => 'Dia todo (8h)'];
$durAtual = (int) ($ag['duracao_min'] ?? 60);
if (!isset($duracoes[$durAtual]) && $durAtual > 0) {
    $duracoes[$durAtual] = intdiv($durAtual, 60) ? intdiv($durAtual, 60) . 'h' . ($durAtual % 60 ? sprintf('%02d', $durAtual % 60) : '') : $durAtual . ' min';
    ksort($duracoes);
}
$semana = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
$hoje = new DateTimeImmutable('today');
$voltar = './agenda?periodo=dia&data=' . $val('data');
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= $titulo ?></h1>
    <p class="page-header__subtitle"><a href="<?= $voltar ?>">Agenda</a> · <?= $id ? 'altere o que for preciso e salve' : 'reserve um horário para o cliente' ?></p>
  </div>
</div>

<form class="form-ficha agd" method="post" action="./agenda-salvar<?= $id ? '?id=' . (int) $id : '' ?>">
  <?= csrf_field() ?>

  <div class="form-ficha__corpo">
    <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

    <!-- 1. Identificação -->
    <section class="card form-ficha__secao" aria-labelledby="agd-id">
      <header class="form-ficha__cabeca">
        <span class="form-ficha__icone"><?= icone('user', 18) ?></span>
        <div><h2 id="agd-id">Identificação</h2><p>Para quem é o horário.</p></div>
      </header>
      <div class="form-ficha__grade">
        <div class="form-field form-ficha__c6">
          <div class="form-ficha__rotulo-linha"><label for="cliente_id">Cliente *</label><a class="form-ficha__alternar" href="./cliente-form">+ Cadastrar cliente</a></div>
          <select id="cliente_id" name="cliente_id" required <?= $id ? '' : 'autofocus' ?>>
            <option value="">Escolha o cliente</option>
            <?php foreach ($clientes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) ($ag['cliente_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-field form-ficha__c6">
          <label for="veiculo_id">Veículo</label>
          <select id="veiculo_id" name="veiculo_id" data-atual="<?= (int) ($ag['veiculo_id'] ?? 0) ?>"><option value="">Escolha o cliente primeiro</option></select>
        </div>
      </div>
    </section>

    <!-- 2. Data e horário -->
    <section class="card form-ficha__secao agd-quando" aria-labelledby="agd-quando">
      <header class="form-ficha__cabeca">
        <span class="form-ficha__icone"><?= icone('calendar', 18) ?></span>
        <div><h2 id="agd-quando">Data e horário</h2><p>Escolha um dos próximos dias ou outra data no calendário.</p></div>
      </header>

      <div class="agd-dias" role="group" aria-label="Próximos dias">
        <?php for ($i = 0; $i < 7; $i++): $d = $hoje->modify("+{$i} days"); ?>
          <button type="button" class="agd-dia" data-dia="<?= $d->format('Y-m-d') ?>">
            <span><?= $i === 0 ? 'hoje' : ($i === 1 ? 'amanhã' : $semana[(int) $d->format('w')]) ?></span>
            <strong><?= $d->format('j') ?></strong>
          </button>
        <?php endfor; ?>
      </div>

      <div class="agd-quando__campos">
        <div class="form-field agd-quando__data"><label for="data">Data *</label><input id="data" name="data" type="date" value="<?= $val('data') ?>" required></div>
        <div class="form-field agd-quando__hora"><label for="hora">Hora *</label><input id="hora" name="hora" type="time" step="300" value="<?= $val('hora') ?>" required></div>
        <div class="agd-horas" role="group" aria-label="Horários comuns">
          <?php foreach (['08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00'] as $h): ?>
            <button type="button" class="agd-hora" data-hora="<?= $h ?>"><?= $h ?></button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="form-field">
        <span class="form-ficha__rotulo" id="rotulo-duracao">Duração</span>
        <div class="agd-duracoes" role="radiogroup" aria-labelledby="rotulo-duracao">
          <?php foreach ($duracoes as $m => $r): ?>
            <label class="agd-duracao"><input type="radio" name="duracao_min" value="<?= $m ?>" <?= $durAtual === $m ? 'checked' : '' ?>><span><?= e($r) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>

      <p class="agd-quando__resumo" data-quando aria-live="polite"></p>
    </section>

    <!-- 3. Serviço -->
    <section class="card form-ficha__secao" aria-labelledby="agd-servico">
      <header class="form-ficha__cabeca">
        <span class="form-ficha__icone"><?= icone('work-order', 18) ?></span>
        <div><h2 id="agd-servico">Serviço</h2><p>O que vai ser feito e quem atende.</p></div>
      </header>
      <div class="form-ficha__grade">
        <div class="form-field form-ficha__c8"><label for="servico">Serviço *</label>
          <input id="servico" name="servico" value="<?= $val('servico') ?>" maxlength="200" required placeholder="Ex.: revisão dos 30 mil km, barulho na suspensão"></div>
        <div class="form-field form-ficha__c4"><label for="tecnico_id">Técnico</label>
          <select id="tecnico_id" name="tecnico_id"><option value="">Qualquer um</option>
            <?php foreach ($tecnicos as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) ($ag['tecnico_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?></option><?php endforeach; ?>
          </select></div>
        <p class="acesso-senha__dica form-ficha__c12"><?= icone('info', 14) ?><span>Com técnico escolhido, ele não pode ter dois serviços no mesmo horário. Sem técnico, o horário não pode coincidir com outro agendamento.</span></p>
      </div>
    </section>

    <!-- 4. Informações adicionais -->
    <section class="card form-ficha__secao" aria-labelledby="agd-info">
      <header class="form-ficha__cabeca">
        <span class="form-ficha__icone"><?= icone('file', 18) ?></span>
        <div><h2 id="agd-info">Informações adicionais</h2><p>Recados para a equipe sobre este atendimento.</p></div>
      </header>
      <div class="form-field"><label for="observacoes" class="sr-only">Observações</label><textarea id="observacoes" name="observacoes" rows="3" placeholder="Ex.: cliente deixa o carro de manhã e busca no fim do dia"><?= $val('observacoes') ?></textarea></div>
    </section>
  </div>

  <aside class="form-ficha__lado" aria-label="Resumo do agendamento">
    <div class="card form-ficha__resumo agd-resumo">
      <div class="agd-resumo__data">
        <span class="agd-resumo__folha"><small data-r-mes>—</small><strong data-r-dia>—</strong></span>
        <div><strong data-r-semana>Escolha a data</strong><span data-r-horario>—</span></div>
      </div>
      <dl class="form-ficha__dados form-ficha__dados--lista">
        <div><dt><?= icone('user', 14) ?> Cliente</dt><dd data-r-cliente>—</dd></div>
        <div><dt><?= icone('car', 14) ?> Veículo</dt><dd data-r-veiculo>—</dd></div>
        <div><dt><?= icone('mechanic', 14) ?> Técnico</dt><dd data-r-tecnico>Qualquer um</dd></div>
      </dl>
      <p class="agd-resumo__servico" data-r-servico>Serviço ainda não descrito.</p>
      <div class="form-ficha__acoes">
        <button class="btn btn--primary"><?= icone('check') ?> <?= $id ? 'Salvar agendamento' : 'Agendar' ?></button>
        <a class="btn btn--ghost" href="<?= $voltar ?>">Cancelar</a>
      </div>
      <p class="form-ficha__nota">Campos com * são obrigatórios.</p>
    </div>
  </aside>
</form>

<script>
  (function () {
    var cli = document.getElementById('cliente_id'), vei = document.getElementById('veiculo_id');
    function carregar() {
      var atual = vei.dataset.atual;
      vei.innerHTML = '<option value="">' + (cli.value ? 'Carregando…' : 'Escolha o cliente primeiro') + '</option>';
      if (!cli.value) { resumo(); return; }
      fetch('./os-veiculos?cliente=' + encodeURIComponent(cli.value), { headers: { 'X-Requested-With': 'fetch' } })
        .then(function (r) { return r.json(); })
        .then(function (lista) {
          vei.innerHTML = '<option value="">' + (lista.length ? 'Sem veículo definido' : 'Este cliente não tem veículo') + '</option>';
          lista.forEach(function (v) {
            var o = document.createElement('option');
            o.value = v.id; o.textContent = v.rotulo; o.selected = String(v.id) === atual || (lista.length === 1 && !atual);
            vei.appendChild(o);
          });
          resumo();
        });
    }
    cli.addEventListener('change', function () { vei.dataset.atual = ''; carregar(); });

    // Atalhos de dia e horário: só preenchem os campos de data e hora.
    var data = document.getElementById('data'), hora = document.getElementById('hora');
    document.querySelectorAll('[data-dia]').forEach(function (b) {
      b.addEventListener('click', function () { data.value = b.dataset.dia; data.dispatchEvent(new Event('input', { bubbles: true })); data.dispatchEvent(new Event('change', { bubbles: true })); });
    });
    document.querySelectorAll('[data-hora]').forEach(function (b) {
      b.addEventListener('click', function () { hora.value = b.dataset.hora; hora.dispatchEvent(new Event('input', { bubbles: true })); hora.dispatchEvent(new Event('change', { bubbles: true })); });
    });

    // Resumo ao lado e linha "quando" (só leitura).
    var SEMANA = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
    var MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    var MESES_LONGOS = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    var $ = function (s) { return document.querySelector(s); };
    var texto = function (sel) { var o = sel.options[sel.selectedIndex]; return o && o.value ? o.textContent : ''; };
    function resumo() {
      var d = data.value, h = hora.value, dur = +(($('input[name=duracao_min]:checked') || {}).value || 60);
      document.querySelectorAll('[data-dia]').forEach(function (b) { b.classList.toggle('is-ativo', b.dataset.dia === d); });
      document.querySelectorAll('[data-hora]').forEach(function (b) { b.classList.toggle('is-ativo', b.dataset.hora === h); });
      var quando = '', horario = '—';
      if (/^\d{4}-\d{2}-\d{2}$/.test(d)) {
        var dt = new Date(d + 'T00:00:00');
        $('[data-r-mes]').textContent = MESES[dt.getMonth()];
        $('[data-r-dia]').textContent = dt.getDate();
        $('[data-r-semana]').textContent = SEMANA[dt.getDay()].replace(/^./, function (c) { return c.toUpperCase(); });
        quando = SEMANA[dt.getDay()] + ', ' + dt.getDate() + ' de ' + MESES_LONGOS[dt.getMonth()];
      } else {
        $('[data-r-mes]').textContent = '—'; $('[data-r-dia]').textContent = '—'; $('[data-r-semana]').textContent = 'Escolha a data';
      }
      if (/^\d{2}:\d{2}$/.test(h)) {
        var ini = +h.slice(0, 2) * 60 + +h.slice(3), fim = ini + dur;
        var hm = function (m) { return String(Math.floor(m / 60) % 24).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0'); };
        horario = 'das ' + h + ' às ' + hm(fim) + (fim >= 24 * 60 ? ' (dia seguinte)' : '');
      }
      $('[data-r-horario]').textContent = horario;
      $('[data-quando]').innerHTML = quando || horario !== '—'
        ? '<?= str_replace("'", "\\'", icone('clock', 14)) ?><span>' + [quando, horario !== '—' ? horario : ''].filter(Boolean).join(' · ') + '</span>' : '';
      $('[data-r-cliente]').textContent = texto(cli) || '—';
      $('[data-r-veiculo]').textContent = texto(vei) ? texto(vei).split(' — ')[0] : '—';
      $('[data-r-tecnico]').textContent = texto(document.getElementById('tecnico_id')) || 'Qualquer um';
      var s = document.getElementById('servico').value.trim();
      $('[data-r-servico]').textContent = s || 'Serviço ainda não descrito.';
      $('[data-r-servico]').classList.toggle('is-vazio', !s);
    }
    ['input', 'change'].forEach(function (ev) {
      [data, hora, document.getElementById('servico'), document.getElementById('tecnico_id'), vei].forEach(function (el) { el.addEventListener(ev, resumo); });
    });
    document.querySelectorAll('input[name=duracao_min]').forEach(function (r) { r.addEventListener('change', resumo); });
    carregar();
    resumo();
  })();
</script>
