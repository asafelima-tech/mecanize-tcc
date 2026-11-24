<?php
/**
 * Agendar e editar agendamento, no padrão de janela (modal) das telas de criar e editar.
 * Seções: cliente e veículo, data e horário (atalhos dos próximos dias, horários comuns e duração),
 * serviço com o técnico e observações. Aberta pelo js/modal.js; acessada direto, ocupa a página.
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
$clienteNome = '';
foreach ($clientes as $c) {
    if ((int) ($ag['cliente_id'] ?? 0) === (int) $c['id']) { $clienteNome = (string) $c['nome']; }
}
$situacao = $id && isset($ag['status']) ? (Agendamento::SITUACOES[$ag['status']][0] ?? null) : null;
$secoes = [['cliente', 'user', 'Cliente'], ['quando', 'calendar', 'Data e horário'], ['servico', 'work-order', 'Serviço'], ['observacoes', 'file', 'Observações']];
?>
<form class="mf" method="post" action="./agenda-salvar<?= $id ? '?id=' . (int) $id : '' ?>" aria-labelledby="mf-titulo">
  <?= csrf_field() ?>

  <header class="mf__topo">
    <h2 class="mf__titulo" id="mf-titulo"><?= $titulo ?><?php if ($id && $clienteNome !== ''): ?><span><?= e($clienteNome) ?></span><?php endif; ?></h2>
    <a class="mf__fechar" href="<?= $voltar ?>" data-mf-fechar aria-label="Fechar" title="Fechar"><?= icone('close', 18, 1.75) ?></a>
  </header>

  <div class="mf__corpo">
    <nav class="mf__nav" aria-label="Seções do agendamento">
      <?php foreach ($secoes as $k => [$ancora, $ic, $rotulo]): ?>
        <a href="#mf-<?= $ancora ?>"<?= $k === 0 ? ' class="is-ativa" aria-current="true"' : '' ?>><?= icone($ic, 18) ?><span><?= $rotulo ?></span></a>
      <?php endforeach; ?>
      <?php if ($situacao): ?>
        <dl class="mf__nav-rodape">
          <div><dt>Situação</dt><dd><?= e($situacao) ?></dd></div>
        </dl>
      <?php endif; ?>
    </nav>

    <div class="mf__conteudo">
      <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

      <section class="mf__secao" id="mf-cliente" aria-labelledby="mf-h-cliente">
        <h3 id="mf-h-cliente">Cliente</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="cliente_id">Cliente</label><small>Não está na lista? <a href="./cliente-form">Cadastrar cliente</a></small></div>
          <div class="mf-linha__campo">
            <select id="cliente_id" name="cliente_id" required <?= $id ? '' : 'autofocus' ?>>
              <option value="">Escolha o cliente</option>
              <?php foreach ($clientes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) ($ag['cliente_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="veiculo_id">Veículo</label><small>Opcional. Lista os veículos do cliente escolhido.</small></div>
          <div class="mf-linha__campo">
            <select id="veiculo_id" name="veiculo_id" data-atual="<?= (int) ($ag['veiculo_id'] ?? 0) ?>"><option value="">Escolha o cliente primeiro</option></select>
          </div>
        </div>
      </section>

      <section class="mf__secao" id="mf-quando" aria-labelledby="mf-h-quando">
        <h3 id="mf-h-quando">Data e horário</h3>
        <div class="mf-linha mf-linha--bloco">
          <div class="mf-linha__rotulo"><span id="rotulo-dia">Dia</span><small>Um dos próximos dias ou outra data no calendário.</small></div>
          <div class="mf-linha__campo">
            <div class="mf-agd-dias" role="group" aria-labelledby="rotulo-dia">
              <?php for ($i = 0; $i < 7; $i++): $d = $hoje->modify("+{$i} days"); ?>
                <button type="button" class="mf-agd-dia" data-dia="<?= $d->format('Y-m-d') ?>"><span><?= $i === 0 ? 'Hoje' : ($i === 1 ? 'Amanhã' : ucfirst($semana[(int) $d->format('w')])) ?></span><strong><?= $d->format('j') ?></strong></button>
              <?php endfor; ?>
              <span class="mf-agd-outra mf-agd-outra--dia">
                <button type="button" class="mf-agd-dia mf-agd-dia--outra" data-outra-data><span>Outra data</span></button>
                <input id="data" name="data" type="date" value="<?= $val('data') ?>" required class="mf-agd-oculto" tabindex="-1" aria-label="Data">
              </span>
            </div>
          </div>
        </div>
        <div class="mf-linha mf-linha--bloco">
          <div class="mf-linha__rotulo"><span id="rotulo-hora">Horário</span></div>
          <div class="mf-linha__campo">
            <div class="mf-agd-chips" role="group" aria-labelledby="rotulo-hora">
              <?php foreach (['08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00'] as $h): ?>
                <button type="button" class="mf-agd-chip" data-hora="<?= $h ?>"><?= $h ?></button>
              <?php endforeach; ?>
              <span class="mf-agd-outra">
                <button type="button" class="mf-agd-chip" data-outra-hora>Outro</button>
                <input id="hora" name="hora" type="time" step="300" value="<?= $val('hora') ?>" required class="mf-agd-oculto" aria-label="Outro horário">
              </span>
            </div>
          </div>
        </div>
        <div class="mf-linha mf-linha--bloco">
          <div class="mf-linha__rotulo"><span id="rotulo-duracao">Duração</span></div>
          <div class="mf-linha__campo">
            <div class="mf-agd-chips" role="radiogroup" aria-labelledby="rotulo-duracao">
              <?php foreach ($duracoes as $m => $r): ?>
                <label class="mf-agd-radio"><input type="radio" name="duracao_min" value="<?= $m ?>" <?= $durAtual === $m ? 'checked' : '' ?>><span><?= e($r) ?></span></label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><span>Reserva</span></div>
          <div class="mf-linha__campo"><span class="mf-agd-quando" data-quando aria-live="polite">Escolha o dia e o horário</span></div>
        </div>
      </section>

      <section class="mf__secao" id="mf-servico" aria-labelledby="mf-h-servico">
        <h3 id="mf-h-servico">Serviço</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="servico">Serviço</label><small>O que vai ser feito.</small></div>
          <div class="mf-linha__campo"><input id="servico" name="servico" value="<?= $val('servico') ?>" maxlength="200" required placeholder="Ex.: revisão dos 30 mil km"></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="tecnico_id">Técnico</label><small>Com técnico escolhido, ele não pode ter dois serviços no mesmo horário. Sem técnico, o horário não pode coincidir com outro agendamento.</small></div>
          <div class="mf-linha__campo">
            <select id="tecnico_id" name="tecnico_id"><option value="">Qualquer um</option>
              <?php foreach ($tecnicos as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) ($ag['tecnico_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
      </section>

      <section class="mf__secao" id="mf-observacoes" aria-labelledby="mf-h-observacoes">
        <h3 id="mf-h-observacoes">Observações</h3>
        <div class="mf-linha mf-linha--bloco">
          <div class="mf-linha__rotulo"><label for="observacoes">Recados para a equipe</label><small>O que precisa ser lembrado neste atendimento.</small></div>
          <div class="mf-linha__campo"><textarea id="observacoes" name="observacoes" rows="3" placeholder="Ex.: cliente deixa o carro de manhã e busca no fim do dia"><?= $val('observacoes') ?></textarea></div>
        </div>
      </section>
    </div>
  </div>

  <footer class="mf__rodape">
    <span class="mf__nota">Cliente, dia, horário e serviço são obrigatórios.</span>
    <a class="btn btn--ghost" href="<?= $voltar ?>" data-mf-fechar>Cancelar</a>
    <button class="btn btn--primary" type="submit"><?= $id ? 'Salvar agendamento' : 'Agendar' ?></button>
  </footer>

  <script>
    (function () {
      var raiz = document.currentScript.closest('.mf');
      var $ = function (s) { return raiz.querySelector(s); };
      var $$ = function (s) { return [].slice.call(raiz.querySelectorAll(s)); };

      // Veículos do cliente escolhido.
      var cli = $('#cliente_id'), vei = $('#veiculo_id');
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
          })
          .catch(function () { vei.innerHTML = '<option value="">Não foi possível carregar os veículos</option>'; });
      }
      cli.addEventListener('change', function () { vei.dataset.atual = ''; carregar(); });

      // Atalhos de dia e horário: só preenchem os campos de data e hora.
      var data = $('#data'), hora = $('#hora');
      function preencher(campo, v) {
        campo.value = v;
        campo.dispatchEvent(new Event('input', { bubbles: true }));
        campo.dispatchEvent(new Event('change', { bubbles: true }));
      }
      $$('.mf-agd-dia[data-dia]').forEach(function (b) { b.addEventListener('click', function () { preencher(data, b.dataset.dia); }); });
      $$('.mf-agd-chip[data-hora]').forEach(function (b) { b.addEventListener('click', function () { preencher(hora, b.dataset.hora); }); });

      // "Outra data" abre o calendário do sistema (js/campos.js) logo abaixo do botão; "Outro" mostra o campo de hora.
      var outraData = $('[data-outra-data]'), outraHora = $('[data-outra-hora]');
      outraData.addEventListener('click', function () {
        var cal = data.closest('.campo-data-caixa'), bt = cal && cal.querySelector('.campo-data-botao');
        if (bt) { bt.click(); return; }
        try { data.showPicker(); } catch (e) { data.classList.add('is-visivel'); data.focus(); }
      });
      function mostrarHora() { hora.classList.add('is-visivel'); hora.removeAttribute('tabindex'); outraHora.hidden = true; }
      outraHora.addEventListener('click', function () { mostrarHora(); hora.focus(); try { hora.showPicker(); } catch (e) {} });
      hora.addEventListener('invalid', mostrarHora);

      // Marcação dos atalhos e a linha "Reserva" (só leitura).
      var SEMANA = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
      var MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
      var MESES_LONGOS = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
      function resumo() {
        var d = data.value, h = hora.value, dur = +((raiz.querySelector('input[name=duracao_min]:checked') || {}).value || 60);
        var dias = $$('.mf-agd-dia[data-dia]'), horas = $$('.mf-agd-chip[data-hora]');
        dias.forEach(function (b) { b.classList.toggle('is-ativo', b.dataset.dia === d); });
        horas.forEach(function (b) { b.classList.toggle('is-ativo', b.dataset.hora === h); });
        var dataOk = /^\d{4}-\d{2}-\d{2}$/.test(d), horaOk = /^\d{2}:\d{2}$/.test(h);
        var outra = dataOk && !dias.some(function (b) { return b.dataset.dia === d; });
        var dt = dataOk ? new Date(d + 'T00:00:00') : null;
        outraData.classList.toggle('is-ativo', outra);
        outraData.innerHTML = '';
        if (outra) {
          var s = document.createElement('span'), n = document.createElement('strong');
          s.textContent = MESES[dt.getMonth()]; n.textContent = dt.getDate();
          outraData.appendChild(s); outraData.appendChild(n);
          outraData.setAttribute('aria-label', 'Outra data: ' + dt.getDate() + ' de ' + MESES_LONGOS[dt.getMonth()]);
        } else {
          var t = document.createElement('span'); t.textContent = 'Outra data'; outraData.appendChild(t);
          outraData.removeAttribute('aria-label');
        }
        if (horaOk && !horas.some(function (b) { return b.dataset.hora === h; })) mostrarHora();
        var partes = [];
        if (dataOk) partes.push(SEMANA[dt.getDay()] + ', ' + dt.getDate() + ' de ' + MESES_LONGOS[dt.getMonth()]);
        if (horaOk) {
          var ini = +h.slice(0, 2) * 60 + +h.slice(3), fim = ini + dur;
          var hm = function (m) { return String(Math.floor(m / 60) % 24).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0'); };
          partes.push('das ' + h + ' às ' + hm(fim) + (fim >= 24 * 60 ? ' (dia seguinte)' : ''));
        }
        var txt = partes.join(' · ');
        $('[data-quando]').textContent = txt ? txt.charAt(0).toUpperCase() + txt.slice(1) : 'Escolha o dia e o horário';
      }
      ['input', 'change'].forEach(function (ev) { [data, hora].forEach(function (el) { el.addEventListener(ev, resumo); }); });
      $$('input[name=duracao_min]').forEach(function (r) { r.addEventListener('change', resumo); });
      carregar();
      resumo();
    })();
  </script>
</form>
