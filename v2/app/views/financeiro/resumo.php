<?php
/**
 * Financeiro · visão geral (Design System, tela 16): dinheiro que entrou e saiu no período,
 * evolução, entradas por forma, saídas por categoria e os pagamentos e recebimentos.
 * Espera: $agrupar, $de, $ate, $busca, $caixa, $anterior, $sessoes, $fluxo, $maiorSaida, $movimentos, $p, $abertas
 */
$titulo = 'Financeiro';
$meses = ['', 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$base = ['agrupar' => $agrupar, 'de' => $de->format('Y-m-d'), 'ate' => $ate->format('Y-m-d')];
$variacao = function (float $atual, float $antes): ?string {
    if (abs($antes) < 0.005) {
        return null;
    }
    $v = ($atual - $antes) / abs($antes) * 100;

    return ($v >= 0 ? '+' : '−') . number_format(abs($v), 0, ',', '.') . '%';
};
$resultado = $caixa['recebido'] - $caixa['taxas'] - $caixa['pago'];
$resultadoAntes = $anterior['recebido'] - $anterior['taxas'] - $anterior['pago'];
$periodoCurto = $de->format('Y') === $ate->format('Y')
    ? $de->format('d/m') . ' – ' . $ate->format('d/m/Y')
    : $de->format('d/m/Y') . ' – ' . $ate->format('d/m/Y');

$maxFluxo = max(array_merge([0.0], array_column($fluxo, 'entradas'), array_column($fluxo, 'saidas')));
$passo = $maxFluxo > 0 ? (float) pow(10, floor(log10($maxFluxo / 4))) : 1.0;
foreach ([1, 2, 2.5, 5, 10] as $m) {
    if ($passo * $m * 4 >= $maxFluxo) { $passo *= $m; break; }
}
$topo = max($passo * 4, 1);
$escala = fn (float $v): string => $v >= 1000 ? number_format($v / 1000, fmod($v, 1000) ? 1 : 0, ',', '.') . ' mil' : number_format($v, 0, ',', '.');
$pct = fn (float $v, float $total): string => $total > 0 ? number_format($v / $total * 100, 0) . '%' : '—';

// Composição (entradas por forma, saídas por categoria): uma barra única dividida em partes, como a barra de
// armazenamento do iPhone, e a lista logo abaixo. Do maior para o menor; cada parte num tom da cor dos gráficos.
$composicao = function (array $itens, float $total, callable $nome) use ($pct): string {
    $itens = array_filter(array_map(fn ($v) => max(0.0, (float) $v), $itens), fn ($v) => $v > 0);
    arsort($itens);
    $n = count($itens);
    $tom = fn (int $i): int => (int) round(100 - $i * (82 / max(1, $n - 1)));
    $barra = '';
    $lista = '';
    $i = 0;
    foreach ($itens as $chave => $v) {
        $rotulo = e($nome($chave));
        $barra .= '<i style="flex-grow:' . round($v, 2) . ';--tom:' . $tom($i) . '%" title="' . $rotulo . ' · ' . e(dinheiro($v)) . '"></i>';
        $lista .= '<li><span class="fin-comp__ponto" style="--tom:' . $tom($i) . '%"></span><span class="fin-comp__nome">' . $rotulo . '</span>'
            . '<b>' . dinheiro($v) . '</b><span class="fin-comp__pct">' . $pct($v, $total) . '</span></li>';
        $i++;
    }

    return '<div class="fin-comp__barra" aria-hidden="true">' . $barra . '</div><ul class="fin-comp__lista">' . $lista . '</ul>';
};
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Financeiro</h1>
    <p class="page-header__subtitle">Entradas, saídas e resultado da oficina por período.</p>
  </div>
  <div class="page-header__actions">
    <a class="btn btn--contorno" href="./caixas"><?= icone('history') ?> Histórico de caixa</a>
    <a class="btn btn--ghost" href="./comissoes"><?= icone('percent') ?> Comissões</a>
    <details class="menu-mais"><summary class="btn btn--primary"><?= icone('plus') ?> Nova conta<?= icone('chevron-down', 14) ?></summary>
      <div class="menu-mais__lista">
        <a href="./conta-form?tipo=RECEBER">Conta a receber</a>
        <a href="./conta-form?tipo=PAGAR">Conta a pagar</a>
      </div>
    </details>
  </div>
</div>

<nav class="abas abas--contagem" aria-label="Financeiro">
  <a href="./financeiro" class="ativa" aria-current="page">Visão geral</a>
  <a href="./financeiro?tipo=RECEBER">A receber<span class="abas__n"><?= (int) $abertas['RECEBER']['n'] ?></span></a>
  <a href="./financeiro?tipo=PAGAR">A pagar<span class="abas__n"><?= (int) $abertas['PAGAR']['n'] ?></span></a>
</nav>

<form class="barra-periodo" method="get" action="./financeiro">
  <input type="hidden" name="agrupar" value="<?= e($agrupar) ?>">
  <div class="barra-periodo__nav">
    <nav class="abas abas--compactas" aria-label="Agrupar por">
      <?php foreach (['dia' => 'Diário', 'semana' => 'Semanal', 'mes' => 'Mensal', 'ano' => 'Anual'] as $k => $r): ?>
        <a href="./financeiro?agrupar=<?= $k ?>"<?= $agrupar === $k ? ' class="ativa" aria-current="page"' : '' ?>><?= $r ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
  <div class="barra-periodo__filtros barra-periodo__filtros--largo">
    <label class="campo-periodo"><?= icone('calendar') ?>
      <input type="date" name="de" value="<?= e($de->format('Y-m-d')) ?>" aria-label="De">
      <span>–</span>
      <input type="date" name="ate" value="<?= e($ate->format('Y-m-d')) ?>" aria-label="Até">
    </label>
    <button class="btn btn--ghost" type="submit">Aplicar</button>
  </div>
</form>

<div class="stat-grid stat-grid--icone">
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Total de entradas</span><?php if ($v = $variacao($caixa['recebido'], $anterior['recebido'])): ?><span class="stat-card__chip" title="Comparado ao período anterior de mesmo tamanho"><?= $v ?></span><?php endif; ?></div>
    <span class="stat-card__value"><?= dinheiro($caixa['recebido']) ?></span>
    <span class="stat-card__pe"><?= e($periodoCurto) ?><?= $caixa['taxas'] > 0 ? ' · taxas ' . dinheiro($caixa['taxas']) : '' ?></span>
  </div>
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Total de saídas</span><?php if ($v = $variacao($caixa['pago'], $anterior['pago'])): ?><span class="stat-card__chip" title="Comparado ao período anterior de mesmo tamanho"><?= $v ?></span><?php endif; ?></div>
    <span class="stat-card__value"><?= dinheiro($caixa['pago']) ?></span>
    <span class="stat-card__pe">contas pagas no período</span>
  </div>
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Resultado do período</span><?php if ($v = $variacao($resultado, $resultadoAntes)): ?><span class="stat-card__chip" title="Comparado ao período anterior de mesmo tamanho"><?= $v ?></span><?php endif; ?></div>
    <span class="stat-card__value"><?= $resultado < 0 ? '−' : '' ?><?= dinheiro(abs($resultado)) ?></span>
    <span class="stat-card__pe">entradas − taxas − saídas</span>
  </div>
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Sessões de caixa</span><?= icone('cash') ?></div>
    <span class="stat-card__value"><?= (int) $sessoes['n'] ?></span>
    <span class="stat-card__pe"><?= (int) $sessoes['diferenca'] ? (int) $sessoes['diferenca'] . ' fechada(s) com sobra ou falta' : 'nenhuma diferença na gaveta' ?></span>
  </div>
</div>

<section class="card quadro card--espaco fin-evolucao">
  <div class="quadro__topo">
    <h2 class="card__title">Evolução</h2>
    <div class="quadro__legenda"><span><span class="legenda-linha"></span>Entradas</span><span><span class="legenda-linha legenda-linha--saida"></span>Saídas</span></div>
  </div>
  <?php
    // Mesmo desenho do Faturamento do Início: muitos pontos ligados por segmentos retos (a linha "viva"), cada um
    // somando a janela anterior (1 dia no diário, 7 dias no semanal, 30 no mensal, 4 semanas no anual). Entradas na
    // linha azul com degradê; saídas numa linha cinza escura, mais fina e sem área.
    $mesesCurtos = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    $datasEvo = [];
    foreach (array_keys($linha) as $k) {
        $datasEvo[] = $baseLinha === 'dia' ? new DateTimeImmutable($k) : (new DateTimeImmutable())->setISODate((int) substr($k, 0, 4), (int) substr($k, 5));
    }
    $brutos = array_values($linha);
    $nEvo = count($brutos);
    $somaEnt = []; $somaSai = []; $se = 0.0; $ss = 0.0;
    foreach ($brutos as $i => $b) {
        $se += (float) $b['entradas']; $ss += (float) $b['saidas'];
        if ($i >= $janela) { $se -= (float) $brutos[$i - $janela]['entradas']; $ss -= (float) $brutos[$i - $janela]['saidas']; }
        $somaEnt[] = max(0.0, $se); $somaSai[] = max(0.0, $ss);
    }
    $maxEvo = max(array_merge([0.0], $somaEnt, $somaSai));
    $pEvo = $maxEvo > 0 ? (float) pow(10, floor(log10($maxEvo / 4))) : 1.0;
    foreach ([1, 2, 2.5, 5, 10] as $m) { if ($pEvo * $m * 4 >= $maxEvo) { $pEvo *= $m; break; } }
    $topoEvo = max($pEvo * 4, 1);
    $xEvo = fn (int $i): float => $nEvo > 1 ? round($i / ($nEvo - 1) * 100, 3) : 50.0;
    $linhaDe = function (array $vals) use ($xEvo, $topoEvo): string {
        $p = [];
        foreach ($vals as $i => $v) $p[] = $xEvo($i) . ' ' . round(100 - min(100, $v / $topoEvo * 100), 3);
        return $p ? 'M ' . implode(' L ', $p) : '';
    };
    $curvaEnt = $linhaDe($somaEnt);
    $curvaSai = $linhaDe($somaSai);
    $areaEnt = $curvaEnt !== '' ? $curvaEnt . ' L 100 100 L 0 100 Z' : '';
    $sufixo = $janela <= 1 ? '' : ($baseLinha === 'dia' ? $janela . ' dias até ' : $janela . ' semanas até ');
    $dadosEvo = [];
    foreach ($datasEvo as $i => $d) $dadosEvo[] = [$sufixo . $d->format('d/m') . ($d->format('Y') !== date('Y') ? '/' . $d->format('y') : ''), dinheiro($somaEnt[$i]), dinheiro($somaSai[$i])];
    // Rótulos: períodos longos, o mês onde ele começa (no anual, a cada trimestre); curtos, algumas datas espaçadas.
    $rotulosEvo = [];
    if ($baseLinha === 'semana') {
        foreach ($datasEvo as $i => $d) {
            $m = (int) $d->format('n');
            if (($i === 0 || (int) $datasEvo[$i - 1]->format('n') !== $m) && in_array($m, [1, 4, 7, 10], true)) $rotulosEvo[] = [$xEvo($i), $mesesCurtos[$m] . '/' . $d->format('y')];
        }
    } elseif ($nEvo > 45) {
        foreach ($datasEvo as $i => $d) { if ($d->format('d') === '01') $rotulosEvo[] = [$xEvo($i), $mesesCurtos[(int) $d->format('n')]]; }
    } else {
        $passoR = max(1, (int) ceil($nEvo / 7));
        foreach ($datasEvo as $i => $d) { if ($i % $passoR === 0) $rotulosEvo[] = [$xEvo($i), $d->format('d/m')]; }
    }
  ?>
  <div class="fat__quadro fin-linha" role="img" aria-label="Entradas e saídas no período: <?= e(implode('; ', array_map(fn ($b) => $b['rotulo'] . ' entradas ' . dinheiro($b['entradas']) . ', saídas ' . dinheiro($b['saidas']), array_values($fluxo)))) ?>">
    <div class="fat__escala" aria-hidden="true"><?php for ($i = 4; $i >= 0; $i--): ?><span><?= $escala($topoEvo / 4 * $i) ?></span><?php endfor; ?></div>
    <div class="fat__area-grafico">
      <?php for ($i = 0; $i < 5; $i++): ?><span class="fat__grade" style="bottom: calc(18px + (100% - 18px) * <?= $i / 4 ?>)"></span><?php endfor; ?>
      <div class="fat__grafico" data-pontos="<?= e(json_encode($dadosEvo, JSON_UNESCAPED_UNICODE)) ?>">
        <svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
          <defs>
            <linearGradient id="fin-degrade" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0" class="fat__degrade-azul"/><stop offset=".55" class="fat__degrade-meio"/><stop offset="1" class="fat__degrade-cinza"/>
            </linearGradient>
          </defs>
          <path class="fat__area" d="<?= $areaEnt ?>" fill="url(#fin-degrade)"/>
          <path class="fat__linha fat__linha--saida" d="<?= $curvaSai ?>"/>
          <path class="fat__linha" d="<?= $curvaEnt ?>"/>
        </svg>
        <span class="fat__ponto fat__ponto--saida" aria-hidden="true"></span>
        <span class="fat__ponto" aria-hidden="true"></span>
        <span class="fat__dica fin-linha__dica" aria-hidden="true"><small></small><span><i></i><b data-ent></b></span><span><i class="is-saida"></i><b data-sai></b></span></span>
      </div>
      <div class="fat__meses" aria-hidden="true">
        <?php foreach ($rotulosEvo as [$x, $rot]): ?>
          <span class="<?= $x < 4 ? 'is-inicio' : ($x > 96 ? 'is-fim' : '') ?>" style="left: <?= $x ?>%"><?= e($rot) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <script>
    // Os dois pontos deslizam sobre as linhas acompanhando o mouse (ou o dedo); o balão mostra o período mais
    // próximo com as entradas e as saídas dele.
    (function () {
      var area = document.currentScript.previousElementSibling.querySelector('.fat__grafico');
      var linhas = area ? area.querySelectorAll('.fat__linha') : [], svg = area && area.querySelector('svg');
      var saida = linhas[0], entrada = linhas[1];
      if (!entrada || !entrada.getTotalLength) return;
      var dados = []; try { dados = JSON.parse(area.dataset.pontos || '[]'); } catch (e) {}
      if (dados.length < 2) return;
      function amostrar(p) {
        var t = p.getTotalLength(), a = [];
        for (var k = 0; k <= 400; k++) { var q = p.getPointAtLength(t * k / 400); a.push([q.x, q.y]); }
        return a;
      }
      function alturaEm(a, x) {
        var i = 0, j = a.length - 1;
        while (j - i > 1) { var m = (i + j) >> 1; if (a[m][0] <= x) i = m; else j = m; }
        var t = a[j][0] === a[i][0] ? 0 : (x - a[i][0]) / (a[j][0] - a[i][0]);
        return a[i][1] + (a[j][1] - a[i][1]) * Math.max(0, Math.min(1, t));
      }
      var aEnt = amostrar(entrada), aSai = amostrar(saida);
      var pEnt = area.querySelector('.fat__ponto:not(.fat__ponto--saida)'), pSai = area.querySelector('.fat__ponto--saida'), dica = area.querySelector('.fat__dica');
      var alvo = null, quadro = 0;
      function desenhar() {
        quadro = 0;
        var x = alvo, yE = alturaEm(aEnt, x), yS = alturaEm(aSai, x), i = Math.max(0, Math.min(dados.length - 1, Math.round(x / 100 * (dados.length - 1))));
        pEnt.style.left = pSai.style.left = dica.style.left = x + '%';
        pEnt.style.top = yE + '%'; pSai.style.top = yS + '%';
        dica.style.top = Math.min(yE, yS) + '%';
        dica.querySelector('small').textContent = dados[i][0];
        dica.querySelector('[data-ent]').textContent = dados[i][1];
        dica.querySelector('[data-sai]').textContent = dados[i][2];
        dica.classList.toggle('is-a-esquerda', x > 80);
        dica.classList.toggle('is-a-direita', x < 20);
        area.classList.add('is-seguindo');
      }
      function mover(ev) {
        var r = svg.getBoundingClientRect();
        alvo = Math.max(0, Math.min(100, (ev.clientX - r.left) / r.width * 100));
        if (!quadro) quadro = requestAnimationFrame(desenhar);
      }
      area.addEventListener('pointermove', mover);
      area.addEventListener('pointerdown', mover);
      area.addEventListener('pointerleave', function () { area.classList.remove('is-seguindo'); });
    })();
  </script>
</section>

<div class="grade-2 grade-2--espaco grade-2--igual">
  <section class="card fin-comp">
    <header class="fin-comp__cabeca"><h2 class="card__title">Entradas por forma</h2><b><?= dinheiro($caixa['recebido']) ?></b></header>
    <?php if ($caixa['recebido'] <= 0): ?>
      <p class="fin-comp__vazio">Nenhum recebimento no período.</p>
    <?php else: ?>
      <?= $composicao($caixa['por_forma'], (float) $caixa['recebido'], fn ($k) => Lancamento::FORMAS[$k] ?? $k) ?>
      <dl class="fin-comp__rodape">
        <dt>Líquido após taxas<?php if ($caixa['taxas'] > 0): ?><small><?= dinheiro($caixa['taxas']) ?> em taxas</small><?php endif; ?></dt>
        <dd><?= dinheiro($caixa['recebido'] - $caixa['taxas']) ?></dd>
      </dl>
    <?php endif; ?>
  </section>
  <section class="card fin-comp">
    <header class="fin-comp__cabeca"><h2 class="card__title">Saídas por categoria</h2><b><?= dinheiro($caixa['pago']) ?></b></header>
    <?php if ($caixa['pago'] <= 0): ?>
      <p class="fin-comp__vazio">Nenhum pagamento no período.</p>
    <?php else: ?>
      <?= $composicao($caixa['despesas'], (float) $caixa['pago'], fn ($k) => Lancamento::categoria('PAGAR', $k)) ?>
      <?php if ($maiorSaida): ?>
        <dl class="fin-comp__rodape">
          <dt>Maior saída<small><?= e($maiorSaida['pessoa'] ?: $maiorSaida['descricao']) ?></small></dt>
          <dd><?= dinheiro($maiorSaida['valor']) ?></dd>
        </dl>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>

<div class="card card--tabela card--espaco">
  <div class="card__header">
    <span class="card__title">Lançamentos do período</span>
    <form method="get" action="./financeiro" class="filtros__busca filtros__busca--compacta">
      <?php foreach ($base as $k => $v): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endforeach; ?>
      <?= icone('search') ?><input type="search" name="busca" value="<?= e($busca) ?>" placeholder="Buscar lançamento" aria-label="Buscar lançamento">
    </form>
  </div>
  <?php if (!$movimentos): ?>
    <div class="vazio"><?= icone('wallet', 32, 1.25) ?>Nenhum pagamento ou recebimento <?= $busca !== '' ? 'com essa busca ' : '' ?>no período.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Data/hora</th><th>Tipo</th><th>Forma / categoria</th><th>Descrição</th><th>Registrado por</th><th class="num">Valor</th></tr></thead>
        <tbody>
          <?php foreach ($movimentos as $m): $entrada = $m['direcao'] === 'ENTRADA'; $estorno = $m['natureza'] === 'ESTORNO'; ?>
            <tr data-href="./conta?id=<?= (int) $m['lancamento_id'] ?>">
              <td class="secundario tabular"><?= date('d/m H:i', strtotime($m['data_movimento'])) ?></td>
              <td><span class="badge badge--plain badge--sinal"><?= $estorno ? icone('refresh', 12, 2) . 'Estorno' : ($entrada ? icone('plus', 12, 2) . 'Entrada' : icone('minus', 12, 2) . 'Saída') ?></span></td>
              <td><?= e($m['tipo'] === 'RECEBER' ? (Lancamento::FORMAS[$m['forma_pagamento']] ?? $m['forma_pagamento']) : Lancamento::categoria('PAGAR', $m['categoria'])) ?></td>
              <td><a href="./conta?id=<?= (int) $m['lancamento_id'] ?>"><?= e($m['descricao']) ?></a><?= $m['pessoa'] ? ' · ' . e($m['pessoa']) : '' ?></td>
              <td><?= e($m['usuario'] ?? '—') ?></td>
              <td class="num"><strong><?= $entrada ? '' : '− ' ?><?= dinheiro($m['valor']) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?php $rotuloItens = 'lançamentos'; require V2_RAIZ . '/app/views/layout/_rodape.php'; ?>
</div>
