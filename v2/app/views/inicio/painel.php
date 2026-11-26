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
// Tons da cor dos gráficos, do mais forte ao mais leve, todos próximos do escuro do sistema. O azul do sistema
// marca só o que está acontecendo agora (aqui, as OS em andamento), como o dia de hoje e o mês atual nos outros gráficos.
$tom = fn (int $p): string => 'color-mix(in srgb, var(--grafico-1) ' . $p . '%, var(--card))';
$fatias = [
    'concluidas' => ['Concluídas', $tom(100)], 'andamento' => ['Em andamento', 'var(--acento)'], 'peca' => ['Aguardando peça', $tom(58)],
    'abertas' => ['Abertas', $tom(42)], 'canceladas' => ['Canceladas', $tom(28)],
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
if (!empty($alertas['validar'])) $avisos[] = ['checklist', $plural((int) $alertas['validar'], 'serviço para validar', 'serviços para validar'), './ordens?situacao=validacao', ''];
if (!empty($alertas['pedidos'])) $avisos[] = ['box', $plural((int) $alertas['pedidos'], 'pedido de técnico', 'pedidos de técnicos'), './solicitacoes', ''];
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
  <div class="stat-card">
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
    <div class="quadro__topo"><h2 class="card__title">Fluxo de OS × Receita</h2><span class="etiqueta" data-pm-periodo>Últimos <?= $pmN ?> dias</span></div>
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
               data-os="<?= $pmOs[$i] ?>" data-rec="<?= e(dinheiro($pmRec[$i])) ?>" data-rec-v="<?= round($pmRec[$i], 2) ?>">
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
        var gr = grade.getBoundingClientRect(), i = Math.floor((ev.clientX - gr.left) / gr.width * visiveis.length);
        return visiveis[Math.max(0, Math.min(visiveis.length - 1, i))];
      }

      // Quantos dias cabem: colunas de ~17 px com 1 px entre elas, ocupando a largura toda do cartão. Os dias mais
      // antigos que não cabem saem; a escala das bolinhas e os totais do topo passam a ser os do período que aparece.
      var COL = 17, GAP = 1, visiveis = Array.prototype.slice.call(cols);
      var eixo = g.querySelector('.pm__eixo'), rotulos = eixo ? eixo.children : [], periodo = document.querySelector('[data-pm-periodo]');
      var moeda = window.Intl ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }) : null;
      function ajustar() {
        var w = grade.clientWidth;
        if (!w) return;
        var n = Math.max(7, Math.min(cols.length, Math.floor((w + GAP) / (COL + GAP)))), ini = cols.length - n;
        cols.forEach(function (c, i) { c.classList.toggle('is-fora', i < ini); });
        Array.prototype.forEach.call(rotulos, function (r, i) { r.classList.toggle('is-fora', i < ini); });
        grade.style.setProperty('--colunas', n);
        if (eixo) eixo.style.setProperty('--colunas', n);
        visiveis = Array.prototype.slice.call(cols, ini);
        var maxO = 1, maxR = 1, totO = 0, totR = 0;
        visiveis.forEach(function (c) { var o = +c.dataset.os || 0, r = +c.dataset.recV || 0; maxO = Math.max(maxO, o); maxR = Math.max(maxR, r); totO += o; totR += r; });
        visiveis.forEach(function (c) {
          var o = +c.dataset.os || 0, r = +c.dataset.recV || 0;
          var po = o <= 0 ? 0 : Math.max(1, Math.round(o / maxO * 10)), pr = r <= 0 ? 0 : Math.max(1, Math.round(r / maxR * 10));
          c.querySelectorAll('i').forEach(function (el, k) { el.classList.toggle('o', k < po); el.classList.toggle('r', k < pr); });
        });
        total.dataset.os = totO;
        if (moeda) total.dataset.rec = moeda.format(totR);
        if (periodo) periodo.textContent = 'Últimos ' + n + ' dias';
        if (atual && atual.classList.contains('is-fora')) atual = null;
        resumo();
      }
      ajustar();
      if (window.ResizeObserver) new ResizeObserver(ajustar).observe(grade); else window.addEventListener('resize', ajustar);
      grade.addEventListener('pointermove', function (ev) { var c = colunaEm(ev); if (c !== atual) mostrar(c); });
      grade.addEventListener('pointerdown', function (ev) { mostrar(colunaEm(ev)); });
      grade.addEventListener('pointerleave', function (ev) { if (ev.pointerType === 'mouse') esconder(); });
      document.addEventListener('pointerdown', function (ev) { if (atual && !grade.contains(ev.target)) esconder(); });
    })();
  </script>

  <section class="card quadro">
    <div class="quadro__topo"><h2 class="card__title">Status das OS</h2><span class="etiqueta">Mês</span></div>
    <div class="rosca">
      <?php
        // Anel de tracinhos: 60 marcas em volta, divididas entre as situações na proporção de cada uma
        // (a partir do topo, no sentido do relógio). Sem OS no mês, todas ficam no cinza vazio.
        $marcas = [];
        if ($totalMes) {
            $acum = 0;
            foreach ($fatias as $k => [, $cor]) {
                $v = (int) $g['status_mes'][$k];
                if (!$v) continue;
                $de = (int) round($acum / $totalMes * 60);
                $acum += $v;
                for ($m = $de, $ate = (int) round($acum / $totalMes * 60); $m < $ate; $m++) $marcas[$m] = $cor;
            }
        }
      ?>
      <div class="rosca__desenho rosca__desenho--tracos">
        <svg width="140" height="140" viewBox="0 0 140 140" aria-hidden="true">
          <?php for ($m = 0; $m < 60; $m++): ?><line x1="70" y1="5" x2="70" y2="17" transform="rotate(<?= $m * 6 ?> 70 70)" stroke="<?= $marcas[$m] ?? 'var(--grafico-vazio)' ?>" style="--m: <?= $m ?>"/><?php endfor; ?>
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

  <section class="card quadro fat">
    <div class="quadro__topo"><h2 class="card__title">Faturamento por mês</h2><span class="etiqueta">Últimos 6 meses</span></div>
    <?php
      // Cada ponto é o faturamento dos 30 dias anteriores àquele dia, ao longo de 6 meses: muitos pontos dão a
      // linha fina com pequenas ondas. Escala de zero ao topo arredondado, grade discreta e os meses embaixo, onde
      // cada mês começa. Linha no azul do sistema; o degradê desce do azul para o cinza escuro do sistema.
      $diario = array_map('floatval', $g['diario'] ?? []);
      $datas = array_keys($diario);
      $valoresDia = array_values($diario);
      $serie = [];
      $soma = 0.0;
      foreach ($valoresDia as $i => $v) {
          $soma += $v;
          if ($i >= 30) $soma -= $valoresDia[$i - 30];
          if ($i >= 29) $serie[] = [$datas[$i], max(0.0, $soma)];
      }
      $serie = array_slice($serie, -182);
      $fN = count($serie);
      $fVals = array_column($serie, 1);
      $fMax = $fN ? max($fVals) : 0.0;
      $fPasso = $fMax > 0 ? (float) pow(10, floor(log10($fMax / 4))) : 1.0;
      foreach ([1, 2, 2.5, 5, 10] as $m) { if ($fPasso * $m * 4 >= $fMax) { $fPasso *= $m; break; } }
      $fTopo = max($fPasso * 4, 1);
      $fX = fn (int $i): float => $fN > 1 ? round($i / ($fN - 1) * 100, 3) : 50.0;
      $fPts = [];
      foreach ($fVals as $i => $v) $fPts[] = $fX($i) . ' ' . round(100 - min(100, $v / $fTopo * 100), 3);
      $fLinha = $fPts ? 'M ' . implode(' L ', $fPts) : '';
      $fArea = $fLinha ? $fLinha . ' L 100 100 L 0 100 Z' : '';
      $fDados = array_map(fn ($p) => [date('d/m', strtotime($p[0])), dinheiro($p[1])], $serie);
      // Meses no eixo: um rótulo onde cada mês começa.
      $fMeses = [];
      foreach ($serie as $i => [$d]) { if (substr($d, 8, 2) === '01') $fMeses[] = [$fX($i), $mesesCurto[(int) substr($d, 5, 2)]]; }
    ?>
    <div class="fat__quadro">
      <div class="fat__escala" aria-hidden="true"><?php for ($i = 4; $i >= 0; $i--): ?><span><?= $escala($fTopo / 4 * $i) ?></span><?php endfor; ?></div>
      <div class="fat__area-grafico">
        <?php for ($i = 0; $i < 5; $i++): ?><span class="fat__grade" style="bottom: calc(18px + (100% - 18px) * <?= $i / 4 ?>)"></span><?php endfor; ?>
        <div class="fat__grafico" role="img" aria-label="Faturamento dos 30 dias anteriores, dia a dia, nos últimos 6 meses: de <?= e(dinheiro($fVals[0] ?? 0)) ?> a <?= e(dinheiro($fN ? end($fVals) : 0)) ?>" data-pontos="<?= e(json_encode($fDados, JSON_UNESCAPED_UNICODE)) ?>">
          <svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
            <defs>
              <linearGradient id="fat-degrade" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" class="fat__degrade-azul"/><stop offset=".55" class="fat__degrade-meio"/><stop offset="1" class="fat__degrade-cinza"/>
              </linearGradient>
            </defs>
            <path class="fat__area" d="<?= $fArea ?>" fill="url(#fat-degrade)"/>
            <path class="fat__linha" d="<?= $fLinha ?>"/>
          </svg>
          <span class="fat__ponto" aria-hidden="true"></span>
          <span class="fat__dica" aria-hidden="true"><b></b><small></small></span>
        </div>
        <div class="fat__meses" aria-hidden="true">
          <?php foreach ($fMeses as [$x, $rot]): ?><span class="<?= $x < 4 ? 'is-inicio' : ($x > 96 ? 'is-fim' : '') ?>" style="left: <?= $x ?>%"><?= $rot ?></span><?php endforeach; ?>
        </div>
      </div>
    </div>
    <script>
      // O ponto desliza sobre a linha acompanhando o mouse (ou o dedo); o balão mostra o faturamento dos 30 dias
      // até o dia sob o cursor.
      (function () {
        var area = document.currentScript.previousElementSibling.querySelector('.fat__grafico');
        var path = area && area.querySelector('.fat__linha'), svg = area && area.querySelector('svg');
        if (!path || !path.getTotalLength) return;
        var total = path.getTotalLength();
        if (!total) return;
        var ponto = area.querySelector('.fat__ponto'), dica = area.querySelector('.fat__dica');
        var dados = []; try { dados = JSON.parse(area.dataset.pontos || '[]'); } catch (e) {}
        if (!dados.length) return;
        var amostras = [], N = 600, alvo = null, quadro = 0;
        for (var k = 0; k <= N; k++) { var p = path.getPointAtLength(total * k / N); amostras.push([p.x, p.y]); }
        var xMin = amostras[0][0], xMax = amostras[N][0];
        function alturaEm(x) {
          var a = 0, b = N;
          while (b - a > 1) { var m = (a + b) >> 1; if (amostras[m][0] <= x) a = m; else b = m; }
          var p0 = amostras[a], p1 = amostras[b], t = p1[0] === p0[0] ? 0 : (x - p0[0]) / (p1[0] - p0[0]);
          return p0[1] + (p1[1] - p0[1]) * Math.max(0, Math.min(1, t));
        }
        function desenhar() {
          quadro = 0;
          var x = alvo, y = alturaEm(x);
          ponto.style.left = dica.style.left = x + '%';
          ponto.style.top = dica.style.top = y + '%';
          var i = Math.max(0, Math.min(dados.length - 1, Math.round(x / 100 * (dados.length - 1))));
          dica.querySelector('b').textContent = dados[i][1];
          dica.querySelector('small').textContent = '30 dias até ' + dados[i][0];
          dica.classList.toggle('is-a-esquerda', x > 80);
          dica.classList.toggle('is-a-direita', x < 20);
          area.classList.add('is-seguindo');
        }
        function mover(ev) {
          var r = svg.getBoundingClientRect();
          alvo = Math.max(xMin, Math.min(xMax, (ev.clientX - r.left) / r.width * 100));
          if (!quadro) quadro = requestAnimationFrame(desenhar);
        }
        area.addEventListener('pointermove', mover);
        area.addEventListener('pointerdown', mover);
        area.addEventListener('pointerleave', function () { area.classList.remove('is-seguindo'); });
      })();
    </script>
  </section>

  <section class="card quadro">
    <div class="quadro__topo"><h2 class="card__title">Ticket médio &amp; peças mais vendidas</h2></div>
    <div class="mini-numeros">
      <div><span>Ticket médio (mês)</span><b><?= $g['ticket_mes'] === null ? '—' : dinheiro($g['ticket_mes']) ?></b></div>
      <div><span>Ticket médio (geral)</span><b><?= $g['ticket_geral'] === null ? '—' : dinheiro($g['ticket_geral']) ?></b></div>
    </div>
    <?php if (!$g['pecas']): ?>
      <p class="form-dica">Nenhuma peça vendida nos últimos 6 meses.</p>
    <?php else:
      // Mesmo padrão das entradas e saídas do Financeiro: uma barra única dividida entre as peças (do mais vendido
      // ao menos vendido, cada uma num tom) e a lista com ponto do mesmo tom, nome, quantidade e porcentagem.
      $qtdPecas = array_map(fn ($p) => max(0.0, (float) $p['quantidade']), $g['pecas']);
      $totPecas = array_sum($qtdPecas);
      $nPecas = count($qtdPecas);
      $tomPeca = fn (int $i): int => (int) round(100 - $i * (82 / max(1, $nPecas - 1)));
      $un = fn (float $q): string => number_format($q, fmod($q, 1) ? 1 : 0, ',', '.') . ' un.';
    ?>
      <div class="fin-comp__barra" aria-hidden="true">
        <?php foreach ($g['pecas'] as $i => $pc): ?><i style="flex-grow: <?= round($qtdPecas[$i], 2) ?>; --tom: <?= $tomPeca($i) ?>%" title="<?= e($pc['descricao'] . ' · ' . $un($qtdPecas[$i])) ?>"></i><?php endforeach; ?>
      </div>
      <ul class="fin-comp__lista">
        <?php foreach ($g['pecas'] as $i => $pc): ?>
          <li><span class="fin-comp__ponto" style="--tom: <?= $tomPeca($i) ?>%"></span><span class="fin-comp__nome"><?= e($pc['descricao']) ?></span>
            <b><?= $un($qtdPecas[$i]) ?></b><span class="fin-comp__pct"><?= $totPecas > 0 ? $pct($qtdPecas[$i] / $totPecas) : '0%' ?></span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
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
