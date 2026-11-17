<?php
/**
 * Tela inicial (Design System, tela 12): cartões, fluxo de OS × receita, situação das OS no mês,
 * faturamento por mês, ticket médio e peças mais vendidas, e as últimas OS.
 * Espera: $resumo, $graficos, $ultimas, $alertas, $admin.
 */
$titulo = 'Início';
$g = $graficos;
$diasSemana = ['', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado', 'domingo'];
$curtos = ['', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
$mesesNome = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$mesesCurto = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
$hoje = $diasSemana[(int) date('N')] . ', ' . (int) date('j') . ' de ' . $mesesNome[(int) date('n')] . ' de ' . date('Y');
$iniciais = function (string $nome): string {
    $p = preg_split('/\s+/', trim($nome));
    return mb_strtoupper(mb_substr($p[0], 0, 1) . (count($p) > 1 ? mb_substr(end($p), 0, 1) : ''));
};
$pct = fn (float $v): string => number_format($v * 100, 0, ',', '.') . '%';

// Matriz de pontos do fluxo: uma coluna por dia (últimos 30), 10 pontos por coluna. Os pontos cheios mostram o
// valor do dia em relação ao maior do período; dia com movimento tem pelo menos 1 ponto.
$pmLinhas = 10;
$pmOs = array_map('intval', array_column($g['fluxo'], 'os'));
$pmRec = array_map('floatval', array_column($g['fluxo'], 'receita'));
$pmMaxOs = max(array_merge([1], $pmOs));
$pmMaxRec = max(array_merge([1.0], $pmRec));
$pmPontos = fn (float $v, float $max): int => $v <= 0 ? 0 : max(1, (int) round($v / $max * $pmLinhas));
$pmDias = array_keys($g['fluxo']);
$pmN = count($pmDias);
$pmTotOs = array_sum($pmOs);
$pmTotRec = array_sum($pmRec);

// Rosca da situação das OS no mês.
$fatias = [
    'concluidas' => ['Concluídas', 'var(--grafico-1)'], 'andamento' => ['Em andamento', 'var(--grafico-2)'], 'peca' => ['Aguardando peça', 'var(--grafico-3)'],
    'abertas' => ['Abertas', 'var(--grafico-4)'], 'canceladas' => ['Canceladas', 'var(--grafico-5)'],
];
$totalMes = array_sum($g['status_mes']);
$circ = 2 * M_PI * 58;

// Barras do faturamento por mês, com a escala arredondada para cima.
$maxMes = max(array_merge([0.0], array_values($g['meses'])));
$passo = $maxMes > 0 ? (float) pow(10, floor(log10($maxMes / 4))) : 1.0;
foreach ([1, 2, 2.5, 5, 10] as $m) {
    if ($passo * $m * 4 >= $maxMes) { $passo *= $m; break; }
}
$topo = max($passo * 4, 1);
$escala = fn (float $v): string => $v >= 1000 ? number_format($v / 1000, $v % 1000 ? 1 : 0, ',', '.') . ' mil' : number_format($v, 0, ',', '.');
$maxPeca = max(array_merge([1], array_map('floatval', array_column($g['pecas'], 'quantidade'))));

// Variação do faturamento contra o mesmo trecho do mês anterior.
$variacao = $g['mes_anterior'] > 0 ? ($resumo['faturado_mes'] - $g['mes_anterior']) / $g['mes_anterior'] : null;

$avisos = [];
$plural = fn (int $n, string $um, string $varios): string => $n . ' ' . ($n === 1 ? $um : $varios);
if ($alertas['orc_vencidos']) $avisos[] = ['calendar', $plural((int) $alertas['orc_vencidos'], 'orçamento vencido', 'orçamentos vencidos'), './ordens?situacao=orcamento', ''];
if ($admin && $alertas['receber_vencidas']) $avisos[] = ['error', $plural((int) $alertas['receber_vencidas'], 'a receber vencida', 'a receber vencidas') . ' · ' . dinheiro($alertas['receber_valor']), './financeiro?tipo=RECEBER&situacao=vencidos', 'danger'];
if ($admin && $alertas['pagar_vencidas']) $avisos[] = ['warning', $plural((int) $alertas['pagar_vencidas'], 'a pagar vencida', 'a pagar vencidas') . ' · ' . dinheiro($alertas['pagar_valor']), './financeiro?tipo=PAGAR&situacao=vencidos', 'danger'];
if ($admin && $alertas['estoque_baixo']) $avisos[] = ['part', $plural((int) $alertas['estoque_baixo'], 'peça no mínimo', 'peças no mínimo'), './produtos?tipo=PECA&baixo=1', ''];
if ($alertas['agenda_hoje']) $avisos[] = ['calendar', $plural((int) $alertas['agenda_hoje'], 'agendamento hoje', 'agendamentos hoje'), './agenda?periodo=dia', ''];
?>
<div class="page-header painel-hero">
  <div>
    <p class="painel-hero__data"><?= e(mb_strtoupper(mb_substr($hoje, 0, 1)) . mb_substr($hoje, 1)) ?></p>
    <h1 class="page-header__title">Olá, <?= e(explode(' ', Contexto::usuario()['nome'])[0]) ?>.</h1>
    <p class="page-header__subtitle">Visão geral da oficina e das ordens de serviço.</p>
  </div>
  <div class="page-header__actions">
    <a class="btn btn--primary" href="./os-nova"><?= icone('plus') ?> Nova OS</a>
  </div>
</div>

<div class="stat-grid">
  <a class="stat-card" href="./ordens?situacao=orcamento">
    <div class="stat-card__topo"><span class="stat-card__label">OS abertas</span><?php if ($g['abertas_hoje']): ?><span class="stat-card__chip">+<?= (int) $g['abertas_hoje'] ?> hoje</span><?php endif; ?></div>
    <span class="stat-card__value"><?= (int) $resumo['orcamentos'] + (int) $resumo['aprovadas'] ?></span>
    <span class="stat-card__pe">Aguardando início</span>
  </a>
  <a class="stat-card" href="./ordens?situacao=execucao">
    <div class="stat-card__topo"><span class="stat-card__label">Em andamento</span><?php if ($resumo['prontas']): ?><span class="stat-card__chip"><?= (int) $resumo['prontas'] ?> pronta(s)</span><?php endif; ?></div>
    <span class="stat-card__value"><?= (int) $resumo['em_execucao'] ?></span>
    <span class="stat-card__pe">Serviços em execução</span>
  </a>
  <a class="stat-card" href="./ordens?modo=kanban">
    <div class="stat-card__topo"><span class="stat-card__label">Aguardando peça</span></div>
    <span class="stat-card__value"><?= (int) $resumo['aguardando'] ?></span>
    <span class="stat-card__pe">OS paradas</span>
  </a>
  <div class="stat-card stat-card--destaque">
    <div class="stat-card__topo"><span class="stat-card__label">Faturamento mensal</span>
      <?php if ($variacao !== null): ?><span class="stat-card__chip" title="Comparado ao mesmo período do mês anterior"><?= $variacao >= 0 ? '+' : '−' ?><?= $pct(abs($variacao)) ?></span><?php endif; ?></div>
    <span class="stat-card__value"><?= dinheiro($resumo['faturado_mes']) ?></span>
    <span class="stat-card__pe"><?= (int) $resumo['entregues_mes'] ?> OS entregue(s) no mês</span>
  </div>
</div>

<?php if ($avisos): ?>
  <div class="avisos-painel" id="avisos-painel">
    <?php foreach ($avisos as [$icone, $texto, $href, $tom]): ?>
      <a class="aviso-chip<?= $tom ? ' aviso-chip--' . $tom : '' ?>" href="<?= e($href) ?>"><?= icone($icone, 14) ?><?= e($texto) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<script>
  // Avisos do painel: esmaece a borda direita só quando há mais avisos para deslizar (e some ao chegar ao fim).
  (function () {
    var l = document.getElementById('avisos-painel');
    if (!l) return;
    var ver = function () { l.classList.toggle('is-cortado', l.scrollLeft + l.clientWidth < l.scrollWidth - 2); };
    l.addEventListener('scroll', ver, { passive: true }); window.addEventListener('resize', ver); ver();
  })();
</script>

<div class="painel-graficos">
  <section class="card quadro">
    <div class="quadro__topo"><h2 class="card__title">Fluxo de OS × Receita</h2><span class="etiqueta">Últimos <?= $pmN ?> dias</span></div>
    <div class="pm" id="grafico-fluxo" data-metrica="os">
      <div class="pm__cabeca">
        <p class="pm__resumo" aria-live="polite">
          <b data-pm-total data-os="<?= $pmTotOs ?>" data-rec="<?= e(dinheiro($pmTotRec)) ?>"><?= $pmTotOs ?></b>
          <span data-pm-rotulo><?= $pmTotOs === 1 ? 'OS aberta' : 'OS abertas' ?> no período</span>
        </p>
        <div class="pm__alternar" role="radiogroup" aria-label="Mostrar">
          <button type="button" role="radio" aria-checked="true" data-pm="os">OS abertas</button>
          <button type="button" role="radio" aria-checked="false" data-pm="rec">Receita</button>
        </div>
      </div>
      <div class="pm__grade" style="--colunas: <?= $pmN ?>" role="img"
           aria-label="OS abertas e receita por dia nos últimos <?= $pmN ?> dias: <?= e(implode('; ', array_map(fn ($d, $o, $r) => date('d/m', strtotime($d)) . ' ' . $o . ' OS, ' . dinheiro($r), $pmDias, $pmOs, $pmRec))) ?>">
        <?php foreach ($pmDias as $i => $dia): $po = $pmPontos($pmOs[$i], $pmMaxOs); $pr = $pmPontos($pmRec[$i], $pmMaxRec); $ehHoje = $i === $pmN - 1; ?>
          <div class="pm__col<?= $ehHoje ? ' is-hoje' : '' ?>" style="--c: <?= $i ?>"
               data-dia="<?= e(($ehHoje ? 'Hoje' : $curtos[(int) date('N', strtotime($dia))]) . ', ' . date('d/m', strtotime($dia))) ?>"
               data-os="<?= $pmOs[$i] ?>" data-rec="<?= e(dinheiro($pmRec[$i])) ?>">
            <?php for ($k = 0; $k < $pmLinhas; $k++): ?><i class="<?= trim(($k < $po ? 'o ' : '') . ($k < $pr ? 'r' : '')) ?>" style="--k: <?= $k ?>"></i><?php endfor; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="pm__eixo" style="--colunas: <?= $pmN ?>" aria-hidden="true">
        <?php foreach ($pmDias as $i => $dia): ?><span><?= $i === $pmN - 1 ? 'Hoje' : ($i % 7 === 0 && $i < $pmN - 5 ? date('d/m', strtotime($dia)) : '') ?></span><?php endforeach; ?>
      </div>
    </div>
  </section>
  <script>
    // Matriz de pontos: alterna entre OS abertas e receita. Passar o mouse (ou tocar) numa coluna destaca o dia
    // e o número grande do topo passa a mostrar o valor daquele dia; ao sair, volta ao total do período.
    (function () {
      var g = document.getElementById('grafico-fluxo');
      if (!g) return;
      var grade = g.querySelector('.pm__grade'), cols = grade.querySelectorAll('.pm__col');
      var total = g.querySelector('[data-pm-total]'), rotulo = g.querySelector('[data-pm-rotulo]'), atual = null;
      var nomeOs = function (n) { return +n === 1 ? 'OS aberta' : 'OS abertas'; };
      function resumo() {
        var os = g.dataset.metrica === 'os';
        if (atual) {
          total.textContent = os ? atual.dataset.os : atual.dataset.rec;
          rotulo.textContent = (os ? nomeOs(atual.dataset.os) + ' em ' : 'em ') + atual.dataset.dia;
        } else {
          total.textContent = os ? total.dataset.os : total.dataset.rec;
          rotulo.textContent = os ? nomeOs(total.dataset.os) + ' no período' : 'de receita no período';
        }
        g.classList.toggle('is-dia', !!atual);
      }
      g.querySelectorAll('[data-pm]').forEach(function (b) {
        b.addEventListener('click', function () {
          g.dataset.metrica = b.dataset.pm;
          g.querySelectorAll('[data-pm]').forEach(function (x) { x.setAttribute('aria-checked', x === b ? 'true' : 'false'); });
          resumo();
        });
      });
      function mostrar(col) { atual = col; cols.forEach(function (c) { c.classList.toggle('is-ativa', c === col); }); grade.classList.add('is-focada'); resumo(); }
      function esconder() { atual = null; grade.classList.remove('is-focada'); cols.forEach(function (c) { c.classList.remove('is-ativa'); }); resumo(); }
      function colunaEm(ev) {
        var gr = grade.getBoundingClientRect(), i = Math.floor((ev.clientX - gr.left) / gr.width * cols.length);
        return cols[Math.max(0, Math.min(cols.length - 1, i))];
      }
      grade.addEventListener('pointermove', function (ev) { var c = colunaEm(ev); if (c !== atual) mostrar(c); });
      grade.addEventListener('pointerdown', function (ev) { mostrar(colunaEm(ev)); });
      grade.addEventListener('pointerleave', function (ev) { if (ev.pointerType === 'mouse') esconder(); });
      document.addEventListener('pointerdown', function (ev) { if (atual && !grade.contains(ev.target)) esconder(); });
    })();
  </script>

  <section class="card quadro">
    <div class="quadro__topo"><h2 class="card__title">Status das OS</h2><span class="etiqueta">Mês</span></div>
    <div class="rosca">
      <div class="rosca__desenho">
        <svg width="140" height="140" viewBox="0 0 140 140" aria-hidden="true">
          <circle cx="70" cy="70" r="58" fill="none" stroke="var(--grafico-vazio)" stroke-width="24"/>
          <?php $acumulado = 0.0; foreach ($fatias as $k => [, $cor]): $v = $g['status_mes'][$k]; if (!$v) continue; $tam = $v / $totalMes * $circ; ?>
            <circle cx="70" cy="70" r="58" fill="none" stroke="<?= $cor ?>" stroke-width="24" stroke-dasharray="<?= round(max(0, $tam - 2), 1) ?> <?= round($circ, 1) ?>"
                    stroke-dashoffset="<?= round(-$acumulado, 1) ?>" transform="rotate(-90 70 70)"/>
          <?php $acumulado += $tam; endforeach; ?>
        </svg>
        <span class="rosca__centro"><b><?= $totalMes ?></b><span>OS no mês</span></span>
      </div>
      <ul class="rosca__legenda">
        <?php foreach ($fatias as $k => [$rotulo, $cor]): $v = $g['status_mes'][$k]; ?>
          <li><span class="legenda-cor" style="background:<?= $cor ?>"></span><span><?= e($rotulo) ?></span><b><?= $v ?></b><span class="secundario"><?= $totalMes ? $pct($v / $totalMes) : '—' ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="mini-numeros">
      <div><span>Tempo médio até concluir</span><b><?= $g['tempo_medio'] === null ? '—' : number_format($g['tempo_medio'], 1, ',', '.') . ' dias' ?></b></div>
      <div><span>Concluídas no prazo</span><b><?= $g['no_prazo'] === null ? '—' : $pct($g['no_prazo']) ?></b></div>
    </div>
  </section>

  <section class="card quadro">
    <div class="quadro__topo"><h2 class="card__title">Faturamento por mês</h2><span class="etiqueta">Últimos 6 meses</span></div>
    <?php $picoMes = $maxMes > 0 ? array_search($maxMes, array_values($g['meses']), true) : null; ?>
    <div class="barras barras--pico" role="img" aria-label="Faturamento por mês: <?= e(implode('; ', array_map(fn ($m, $v) => $mesesCurto[(int) substr($m, 5)] . ' ' . dinheiro($v), array_keys($g['meses']), $g['meses']))) ?>">
      <div class="barras__escala"><?php for ($i = 4; $i >= 0; $i--): ?><span><?= $escala($topo / 4 * $i) ?></span><?php endfor; ?></div>
      <div class="barras__area">
        <?php for ($i = 0; $i < 5; $i++): ?><span class="barras__grade" style="bottom:calc(18px + (100% - 18px) * <?= $i / 4 ?>)"></span><?php endfor; ?>
        <?php $iMes = 0; foreach ($g['meses'] as $m => $v): $ehPico = $iMes++ === $picoMes; ?>
          <div class="barras__coluna<?= $ehPico ? ' is-pico' : '' ?>" title="<?= e($mesesCurto[(int) substr($m, 5)] . ' · ' . dinheiro($v)) ?>">
            <span class="barras__barra" style="height:<?= round($v / $topo * 100, 1) ?>%"><?php if ($ehPico): ?><span class="rel-pico"><?= e(dinheiro($v)) ?></span><?php endif; ?></span>
            <span class="barras__rotulo"><?= $mesesCurto[(int) substr($m, 5)] ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="card quadro">
    <div class="quadro__topo"><h2 class="card__title">Ticket médio &amp; peças mais vendidas</h2></div>
    <div class="mini-numeros">
      <div><span>Ticket médio (mês)</span><b><?= $g['ticket_mes'] === null ? '—' : dinheiro($g['ticket_mes']) ?></b></div>
      <div><span>Ticket médio (geral)</span><b><?= $g['ticket_geral'] === null ? '—' : dinheiro($g['ticket_geral']) ?></b></div>
    </div>
    <?php if (!$g['pecas']): ?>
      <p class="form-dica">Nenhuma peça vendida nos últimos 6 meses.</p>
    <?php endif; ?>
    <ul class="ranking">
      <?php foreach ($g['pecas'] as $pc): $q = (float) $pc['quantidade']; ?>
        <li><span class="ranking__nome"><?= e($pc['descricao']) ?></span><b><?= number_format($q, fmod($q, 1) ? 1 : 0, ',', '.') ?> un.</b>
          <span class="ranking__trilho"><span style="width:<?= round($q / $maxPeca * 100, 1) ?>%"></span></span></li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>

<div class="card card--tabela">
  <div class="card__header"><span class="card__title">Últimas ordens de serviço</span><a href="./ordens?situacao=todas">Ver todas<?= icone('chevron-right', 12, 2) ?></a></div>
  <?php if (!$ultimas): ?>
    <div class="vazio"><?= icone('work-order', 32, 1.25) ?><strong>Nenhuma ordem de serviço ainda.</strong>O primeiro orçamento aparece aqui.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>OS</th><th>Cliente</th><th>Veículo</th><th>Situação</th><th class="num">Valor total</th><th>Data</th><th class="num">Ações</th></tr></thead>
        <tbody>
          <?php foreach ($ultimas as $os): [$rotuloStatus, $corStatus] = os_status($os['status']); $link = './os?id=' . (int) $os['id']; ?>
            <tr data-href="<?= e($link) ?>">
              <td><a href="<?= e($link) ?>"><?= os_numero($os['numero']) ?></a></td>
              <td><span class="celula-pessoa"><span class="avatar"><?= e($iniciais($os['cliente'])) ?></span><?= e($os['cliente']) ?></span></td>
              <td><?= e($os['modelo'] ?: 'Veículo') ?><span class="sub placa"><?= e($os['placa']) ?></span></td>
              <td><span class="badge badge--<?= e($corStatus) ?>"><?= e($rotuloStatus) ?></span></td>
              <td class="num"><strong><?= dinheiro($os['valor_total']) ?></strong></td>
              <td class="secundario tabular"><?= date('d/m', strtotime($os['movimentada_em'])) ?></td>
              <td class="num"><span class="acoes-linha">
                <a class="btn btn--fantasma btn--icone" href="<?= e($link) ?>" aria-label="Abrir <?= os_numero($os['numero']) ?>" title="Abrir"><?= icone('eye') ?></a>
                <a class="btn btn--fantasma btn--icone" href="./os-imprimir?id=<?= (int) $os['id'] ?>" target="_blank" rel="noopener" aria-label="Imprimir <?= os_numero($os['numero']) ?>" title="Imprimir"><?= icone('print') ?></a>
              </span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
