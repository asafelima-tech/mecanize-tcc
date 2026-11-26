<?php
/**
 * Relatórios: simples de ler, do resumo ao detalhe.
 *   Topo: período em texto, seletor de período e imprimir, numa linha.
 *   Quatro números do período: faturamento, OS entregues, ticket médio e saldo (comparados ao período anterior).
 *   Detalhe em abas (uma visível por vez): Vendas, Dinheiro, Equipe e clientes, Estoque.
 *   Na impressão/PDF saem todas as abas, uma depois da outra.
 * Espera: $ini, $fim, $atalho, $faturamento, $tempo, $ordens, $situacao, $caixa, $contas, $itens, $tecnicos, $clientes, $estoque, $agenda
 */
$titulo = 'Relatórios';
$fat = $faturamento;
$serie = $tempo['serie'];
$max = max(array_merge([0.0], array_column($serie, 'total')));
$qtd = fn($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
$pct = fn(?float $v) => $v === null ? '—' : number_format($v, 1, ',', '.') . '%';
$plural = fn(int $n, string $um, string $varios): string => $n . ' ' . ($n === 1 ? $um : $varios);
$abertasHoje = array_sum($ordens['abertas_hoje']);
$variacao = function (float $atual, float $antes): ?array {
    if ($antes <= 0) return null;
    $v = ($atual - $antes) / $antes * 100;
    return [($v >= 0 ? '+' : '−') . number_format(abs($v), 0, ',', '.') . '%', $v >= 0];
};
$delta = function (?array $v): string {
    if (!$v) return '';
    return '<span class="rl-delta' . ($v[1] ? '' : ' rl-delta--baixa') . '">' . icone($v[1] ? 'trend-up' : 'trend-down', 12, 2) . e($v[0]) . '</span>';
};
$iniciais = function (string $nome): string {
    $p = preg_split('/\s+/', trim($nome));
    return mb_strtoupper(mb_substr($p[0], 0, 1) . (count($p) > 1 ? mb_substr(end($p), 0, 1) : ''));
};
$passo = $max > 0 ? (float) pow(10, floor(log10($max / 4))) : 1.0;
foreach ([1, 2, 2.5, 5, 10] as $m) {
    if ($passo * $m * 4 >= $max) { $passo *= $m; break; }
}
$topo = max($passo * 4, 1);
$escala = fn (float $v): string => $v >= 1000 ? number_format($v / 1000, fmod($v, 1000) ? 1 : 0, ',', '.') . ' mil' : number_format($v, 0, ',', '.');
$maxSituacao = max(array_merge([1], array_values($situacao)));
$maxCliente = max(array_merge([1.0], array_map('floatval', array_column($clientes, 'total'))));
$maxItem = max(array_merge([1.0], array_map('floatval', array_column($itens, 'total'))));
$porForma = $caixa['por_forma'];

// Composição (mesmo padrão do Financeiro e do Início): uma barra única dividida entre as partes, do maior para o
// menor, cada uma num tom da cor dos gráficos, e a lista com ponto do mesmo tom, nome, valor e porcentagem.
// $linhas: [[nome, detalhe ou null, valor, valor escrito], ...]
$composicao = function (array $linhas): string {
    $linhas = array_values(array_filter($linhas, fn ($l) => $l[2] > 0));
    usort($linhas, fn ($a, $b) => $b[2] <=> $a[2]);
    $n = count($linhas);
    $total = array_sum(array_column($linhas, 2));
    $tom = fn (int $i): int => (int) round(100 - $i * (82 / max(1, $n - 1)));
    $barra = '';
    $lista = '';
    foreach ($linhas as $i => [$nome, $detalhe, $v, $texto]) {
        $porc = $total > 0 ? number_format($v / $total * 100, 0, ',', '.') . '%' : '0%';
        $barra .= '<i style="flex-grow:' . round($v, 2) . ';--tom:' . $tom($i) . '%" title="' . e($nome . ' · ' . $texto) . '"></i>';
        $lista .= '<li><span class="fin-comp__ponto" style="--tom:' . $tom($i) . '%"></span><span class="fin-comp__nome">' . e($nome)
            . ($detalhe !== null ? '<small>' . e($detalhe) . '</small>' : '') . '</span><b>' . e($texto) . '</b><span class="fin-comp__pct">' . $porc . '</span></li>';
    }

    return '<div class="fin-comp__barra" aria-hidden="true">' . $barra . '</div><ul class="fin-comp__lista">' . $lista . '</ul>';
};

// Faturamento em linha (o mesmo desenho do Início): pontos de ponta a ponta, curva suave, escala de zero ao topo.
$nSerie = count($serie);
$xSerie = fn (int $i): float => $nSerie > 1 ? $i / ($nSerie - 1) * 100 : 50.0;
$ptsSerie = [];
foreach ($serie as $i => $s) $ptsSerie[] = [$xSerie($i), 100 - min(100, (float) $s['total'] / $topo * 100)];
$curvaSerie = curva_suave($ptsSerie);
$areaSerie = $ptsSerie ? $curvaSerie . ' L ' . round(end($ptsSerie)[0], 3) . ' 100 L ' . round($ptsSerie[0][0], 3) . ' 100 Z' : '';
$dadosSerie = array_map(fn ($s) => [$s['rotulo'], dinheiro($s['total']), (int) $s['os']], $serie);

// Período em texto: "1 a 17 de novembro de 2026" e quantos dias tem (para "comparado aos N dias anteriores").
$meses = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$dI = new DateTimeImmutable($ini); $dF = new DateTimeImmutable($fim);
$diasPeriodo = $dI->diff($dF)->days + 1;
$longa = fn(DateTimeImmutable $d, bool $ano = true) => (int) $d->format('j') . ' de ' . $meses[(int) $d->format('n')] . ($ano ? ' de ' . $d->format('Y') : '');
if ($ini === $fim) $periodoTexto = $longa($dI);
elseif ($dI->format('Y-m') === $dF->format('Y-m')) $periodoTexto = (int) $dI->format('j') . ' a ' . $longa($dF);
elseif ($dI->format('Y') === $dF->format('Y')) $periodoTexto = $longa($dI, false) . ' a ' . $longa($dF);
else $periodoTexto = $longa($dI) . ' a ' . $longa($dF);
$hoje = date('d/m/Y');
$abas = ['vendas' => 'Vendas', 'dinheiro' => 'Dinheiro', 'equipe' => 'Equipe<span class="rl-abas__extra"> e clientes</span>', 'estoque' => 'Estoque'];
?>
<div class="rl">
  <header class="rl-topo">
    <div class="rl-topo__texto">
      <h1 class="rl-topo__titulo">Relatórios</h1>
      <p class="rl-topo__periodo"><?= e($periodoTexto) ?> <span>· comparado aos <?= $diasPeriodo ?> dias anteriores</span></p>
    </div>
    <div class="rl-topo__acoes nao-imprimir">
      <nav class="rl-periodo" aria-label="Período">
        <?php $curtos = ['mes' => 'Mês', 'mes-passado' => 'Mês passado', '30' => '30 dias', 'ano' => 'Ano']; foreach (RelatoriosController::ATALHOS as $k => $r): ?><a data-curto="<?= e($curtos[$k] ?? $r) ?>" href="./relatorios?p=<?= e($k) ?>"<?= $atalho === (string) $k ? ' class="ativa" aria-current="page"' : '' ?>><span><?= e($k === '30' ? '30 dias' : $r) ?></span></a><?php endforeach; ?>
        <details class="rl-datas"<?= $atalho === '' ? ' data-ativo' : '' ?>>
          <summary aria-label="Período personalizado"<?= $atalho === '' ? ' class="ativa"' : '' ?>><?= icone('calendar', 14) ?><span class="rl-datas__texto">Personalizado</span></summary>
          <form class="rl-datas__painel" method="get" action="./relatorios">
            <label><span>De</span><input type="date" name="ini" value="<?= e($ini) ?>" required></label>
            <label><span>Até</span><input type="date" name="fim" value="<?= e($fim) ?>" required></label>
            <button class="btn btn--primary btn--sm">Aplicar</button>
          </form>
        </details>
      </nav>
      <button type="button" class="rl-imprimir" onclick="window.print()" title="Imprimir ou salvar PDF" aria-label="Imprimir ou salvar PDF"><?= icone('print', 18) ?></button>
    </div>
  </header>
  <p class="rel-impresso">Período: <?= e($periodoTexto) ?> · emitido em <?= $hoje ?></p>

  <!-- Os quatro números do período -->
  <section class="rl-kpis" aria-label="Resumo do período">
    <div class="rl-kpi">
      <span class="rl-kpi__rotulo">Faturamento <?= $delta($variacao($fat['total'], $fat['anterior'])) ?></span>
      <strong class="rl-kpi__valor"><?= dinheiro($fat['total']) ?></strong>
      <span class="rl-kpi__pe">Serviços <?= dinheiro($fat['servicos']) ?> · Peças <?= dinheiro($fat['pecas']) ?></span>
    </div>
    <div class="rl-kpi">
      <span class="rl-kpi__rotulo">OS entregues <?= $delta($variacao($fat['os'], $fat['os_anterior'])) ?></span>
      <strong class="rl-kpi__valor"><?= (int) $fat['os'] ?></strong>
      <span class="rl-kpi__pe"><?= (int) $fat['os_anterior'] ?> no período anterior</span>
    </div>
    <div class="rl-kpi">
      <span class="rl-kpi__rotulo">Ticket médio <?= $delta($variacao($fat['ticket'], $fat['ticket_anterior'])) ?></span>
      <strong class="rl-kpi__valor"><?= dinheiro($fat['ticket']) ?></strong>
      <span class="rl-kpi__pe"><?= dinheiro($fat['ticket_anterior']) ?> no período anterior</span>
    </div>
    <div class="rl-kpi<?= $caixa['saldo'] < 0 ? ' rl-kpi--negativo' : '' ?>">
      <span class="rl-kpi__rotulo">Saldo do período</span>
      <strong class="rl-kpi__valor"><?= dinheiro($caixa['saldo']) ?></strong>
      <span class="rl-kpi__pe">Recebido <?= dinheiro($caixa['recebido']) ?> · Pago <?= dinheiro($caixa['pago']) ?></span>
    </div>
  </section>

  <!-- Abas -->
  <div class="rl-abas nao-imprimir" role="tablist" aria-label="Detalhes do relatório">
    <?php $primeira = true; foreach ($abas as $k => $r): ?>
      <button type="button" role="tab" id="aba-<?= $k ?>" aria-controls="painel-<?= $k ?>" aria-selected="<?= $primeira ? 'true' : 'false' ?>" tabindex="<?= $primeira ? '0' : '-1' ?>" data-aba="<?= $k ?>"><?= $r ?></button>
    <?php $primeira = false; endforeach; ?>
  </div>

  <!-- Vendas -->
  <section class="rl-painel" id="painel-vendas" role="tabpanel" aria-labelledby="aba-vendas" data-painel="vendas">
    <h2 class="rl-painel__titulo">Vendas</h2>
    <div class="card rl-cartao">
      <header class="rl-cartao__cabeca"><h3>Faturamento <?= $tempo['por_mes'] ? 'por mês' : 'por dia' ?></h3><span>OS entregues, pelo valor total</span></header>
      <?php if ($max <= 0): ?>
        <div class="rl-vazio"><?= icone('chart', 28, 1.25) ?>Nenhuma OS entregue no período.</div>
      <?php else: ?>
        <div class="fat__quadro rl-linha">
          <div class="fat__escala" aria-hidden="true"><?php for ($i = 4; $i >= 0; $i--): ?><span><?= $escala($topo / 4 * $i) ?></span><?php endfor; ?></div>
          <div class="fat__area-grafico">
            <?php for ($i = 0; $i < 5; $i++): ?><span class="fat__grade" style="bottom: calc(18px + (100% - 18px) * <?= $i / 4 ?>)"></span><?php endfor; ?>
            <div class="fat__grafico" role="img" aria-label="Faturamento <?= $tempo['por_mes'] ? 'por mês' : 'por dia' ?>, máximo de <?= e(dinheiro($max)) ?>" data-pontos="<?= e(json_encode($dadosSerie, JSON_UNESCAPED_UNICODE)) ?>">
              <svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                <defs>
                  <linearGradient id="rl-degrade" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" class="fat__degrade-azul"/><stop offset=".55" class="fat__degrade-meio"/><stop offset="1" class="fat__degrade-cinza"/>
                  </linearGradient>
                </defs>
                <path class="fat__area" d="<?= $areaSerie ?>" fill="url(#rl-degrade)"/>
                <path class="fat__linha" d="<?= $curvaSerie ?>"/>
              </svg>
              <span class="fat__ponto" aria-hidden="true"></span>
              <span class="fat__dica" aria-hidden="true"><b></b><small></small></span>
            </div>
            <div class="fat__meses" aria-hidden="true">
              <?php $rotular = max(1, (int) ceil($nSerie / 10)); foreach ($serie as $i => $s): if ($i % $rotular !== 0 && $i !== $nSerie - 1) continue; if ($i !== $nSerie - 1 && $nSerie - 1 - $i < $rotular / 2) continue; $x = round($xSerie($i), 3); ?>
                <span class="<?= $x < 4 ? 'is-inicio' : ($x > 96 ? 'is-fim' : '') ?>" style="left: <?= $x ?>%"><?= e($s['rotulo']) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <script>
          // O ponto desliza sobre a linha acompanhando o mouse (ou o dedo); o balão mostra o valor do período mais
          // próximo e quantas OS foram entregues nele.
          (function () {
            var area = document.currentScript.previousElementSibling.querySelector('.fat__grafico');
            var path = area && area.querySelector('.fat__linha'), svg = area && area.querySelector('svg');
            if (!path || !path.getTotalLength) return;
            var total = path.getTotalLength();
            if (!total) return;
            var ponto = area.querySelector('.fat__ponto'), dica = area.querySelector('.fat__dica');
            var dados = []; try { dados = JSON.parse(area.dataset.pontos || '[]'); } catch (e) {}
            if (!dados.length) return;
            var amostras = [], N = 500, alvo = null, quadro = 0;
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
              var x = alvo, y = alturaEm(x), i = Math.max(0, Math.min(dados.length - 1, Math.round(x / 100 * (dados.length - 1))));
              ponto.style.left = dica.style.left = x + '%';
              ponto.style.top = dica.style.top = y + '%';
              dica.querySelector('b').textContent = dados[i][1];
              dica.querySelector('small').textContent = dados[i][0] + ' · ' + dados[i][2] + (dados[i][2] === 1 ? ' OS' : ' OS');
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
      <?php endif; ?>
    </div>

    <div class="rl-grade">
      <div class="card rl-cartao">
        <header class="rl-cartao__cabeca"><h3>Ordens de serviço</h3><span>Abertas no período</span></header>
        <div class="rl-destaque">
          <strong><?= $pct($ordens['aprovacao']) ?></strong>
          <span>dos orçamentos aprovados<small><?= $plural((int) $ordens['aprovadas'], 'aprovado', 'aprovados') ?> · <?= $plural((int) $ordens['recusadas'], 'recusado', 'recusados') ?></small></span>
        </div>
        <?php if (array_sum($situacao) > 0): ?>
          <?= $composicao(array_map(fn ($r, $n) => [$r, null, (float) $n, (string) (int) $n], array_keys($situacao), array_values($situacao))) ?>
        <?php else: ?>
          <div class="rl-vazio">Nenhuma OS aberta no período.</div>
        <?php endif; ?>
        <dl class="rl-rodape">
          <div><dt>Em aberto agora</dt><dd><a href="./ordens"><?= (int) $abertasHoje ?></a></dd></div>
          <div><dt>Atendimentos da agenda</dt><dd><?= (int) $agenda['CONCLUIDO'] ?></dd></div>
          <div><dt>Faltas</dt><dd><?= (int) $agenda['FALTOU'] ?></dd></div>
        </dl>
      </div>

      <div class="card rl-cartao">
        <header class="rl-cartao__cabeca"><h3>Mais vendidos</h3><span>Peças e serviços das OS entregues</span></header>
        <?php if (!$itens): ?><div class="rl-vazio">Nenhuma OS entregue no período.</div><?php else: ?>
          <?= $composicao(array_map(fn ($it) => [$it['descricao'],
                ($it['tipo'] === 'PECA' ? 'Peça' : 'Serviço') . ' · ' . $qtd($it['quantidade']) . ' un.' . ($it['margem'] !== null ? ' · margem ' . dinheiro($it['margem']) : ''),
                (float) $it['total'], dinheiro($it['total'])], array_slice($itens, 0, 8))) ?>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- Dinheiro -->
  <section class="rl-painel" id="painel-dinheiro" role="tabpanel" aria-labelledby="aba-dinheiro" data-painel="dinheiro" hidden>
    <h2 class="rl-painel__titulo">Dinheiro</h2>
    <div class="card rl-faixa">
      <div><span>Recebido</span><strong><?= dinheiro($caixa['recebido']) ?></strong></div>
      <div><span>Taxas</span><strong>− <?= dinheiro($caixa['taxas']) ?></strong></div>
      <div><span>Pago</span><strong>− <?= dinheiro($caixa['pago']) ?></strong></div>
      <div class="rl-faixa__total<?= $caixa['saldo'] < 0 ? ' is-negativo' : '' ?>"><span>Saldo</span><strong><?= dinheiro($caixa['saldo']) ?></strong></div>
    </div>

    <div class="rl-grade">
      <div class="card rl-cartao">
        <header class="rl-cartao__cabeca"><h3>Entradas por forma</h3><b><?= dinheiro($caixa['recebido']) ?></b></header>
        <?php if (!$porForma): ?><div class="rl-vazio">Nada recebido no período.</div><?php else: ?>
          <?= $composicao(array_map(fn ($forma, $v) => [Lancamento::FORMAS[$forma] ?? $forma, null, (float) $v, dinheiro($v)], array_keys($porForma), array_values($porForma))) ?>
        <?php endif; ?>
      </div>
      <div class="card rl-cartao">
        <header class="rl-cartao__cabeca"><h3>Saídas por categoria</h3><b><?= dinheiro($caixa['pago']) ?></b></header>
        <?php if (!$caixa['despesas']): ?><div class="rl-vazio">Nada pago no período.</div><?php else: ?>
          <?= $composicao(array_map(fn ($cat, $v) => [Lancamento::categoria('PAGAR', $cat), null, (float) $v, dinheiro($v)], array_keys($caixa['despesas']), array_values($caixa['despesas']))) ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="card rl-cartao">
      <header class="rl-cartao__cabeca"><h3>Contas em aberto <span class="rl-selo">hoje</span></h3><a class="rl-link nao-imprimir" href="./financeiro?situacao=vencidos">Ver vencidas<?= icone('chevron-right', 12, 2) ?></a></header>
      <div class="rl-numeros">
        <div><span>A receber</span><strong><?= dinheiro($contas['RECEBER']['saldo']) ?></strong><small><?= $plural((int) $contas['RECEBER']['n'], 'conta', 'contas') ?></small></div>
        <div class="<?= $contas['RECEBER']['vencido'] > 0 ? 'is-alerta' : '' ?>"><span>Vencido a receber</span><strong><?= dinheiro($contas['RECEBER']['vencido']) ?></strong><small><?= $plural((int) $contas['RECEBER']['n_vencidas'], 'conta', 'contas') ?></small></div>
        <div><span>A pagar</span><strong><?= dinheiro($contas['PAGAR']['saldo']) ?></strong><small><?= $plural((int) $contas['PAGAR']['n'], 'conta', 'contas') ?></small></div>
        <div class="<?= $contas['PAGAR']['vencido'] > 0 ? 'is-alerta' : '' ?>"><span>Vencido a pagar</span><strong><?= dinheiro($contas['PAGAR']['vencido']) ?></strong><small><?= $plural((int) $contas['PAGAR']['n_vencidas'], 'conta', 'contas') ?></small></div>
      </div>
    </div>
  </section>

  <!-- Equipe e clientes -->
  <section class="rl-painel" id="painel-equipe" role="tabpanel" aria-labelledby="aba-equipe" data-painel="equipe" hidden>
    <h2 class="rl-painel__titulo">Equipe e clientes</h2>
    <div class="card card--tabela rl-cartao rl-cartao--tabela">
      <header class="rl-cartao__cabeca"><h3>Desempenho por técnico</h3><span>OS entregues por responsável</span></header>
      <?php if (!$tecnicos): ?><div class="rl-vazio">Nada no período.</div><?php else: ?>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Técnico</th><th class="num">OS</th><th class="num">Vendido</th><th class="num">Comissão</th><th class="num">Tempo médio</th><th class="num">No prazo</th></tr></thead>
          <tbody><?php foreach ($tecnicos as $t): $prazo = $t['com_previsao'] > 0 ? $t['no_prazo'] / $t['com_previsao'] * 100 : null; ?>
            <tr>
              <td><span class="celula-pessoa"><span class="avatar"><?= e($iniciais($t['tecnico'])) ?></span><?= e($t['tecnico']) ?></span></td>
              <td class="num"><?= (int) $t['os'] ?></td>
              <td class="num"><strong><?= dinheiro($t['total']) ?></strong></td>
              <td class="num secundario"><?= dinheiro($t['comissao']) ?></td>
              <td class="num"><?= $t['dias'] === null ? '—' : number_format($t['dias'], 1, ',', '.') . ' dias' ?></td>
              <td class="num"><?= $prazo === null ? '<span class="secundario">sem previsão</span>' : number_format($prazo, 0) . '%' ?></td>
            </tr>
          <?php endforeach; ?></tbody>
        </table></div>
      <?php endif; ?>
    </div>

    <div class="card rl-cartao">
      <header class="rl-cartao__cabeca"><h3>Clientes que mais compraram</h3><a class="rl-link nao-imprimir" href="./clientes">Ver clientes<?= icone('chevron-right', 12, 2) ?></a></header>
      <?php if (!$clientes): ?><div class="rl-vazio">Nada no período.</div><?php else: ?>
        <ol class="rl-lista rl-lista--clientes">
          <?php foreach (array_slice($clientes, 0, 6) as $pos => $c): ?>
            <li><span class="rl-posicao"><?= $pos + 1 ?></span>
              <a class="rl-lista__nome" href="./cliente?id=<?= (int) $c['id'] ?>"><?= e($c['nome']) ?><small><?= $plural((int) $c['os'], 'OS', 'OS') ?></small></a>
              <b><?= dinheiro($c['total']) ?></b></li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </div>
  </section>

  <!-- Estoque -->
  <section class="rl-painel" id="painel-estoque" role="tabpanel" aria-labelledby="aba-estoque" data-painel="estoque" hidden>
    <h2 class="rl-painel__titulo">Estoque</h2>
    <div class="card rl-faixa">
      <div><span>Peças cadastradas</span><strong><?= (int) $estoque['pecas'] ?></strong></div>
      <div><span>Valor pelo custo</span><strong><?= dinheiro($estoque['valor_custo']) ?></strong></div>
      <div><span>Valor pelo preço de venda</span><strong><?= dinheiro($estoque['valor_venda']) ?></strong></div>
      <div class="<?= $estoque['abaixo'] ? 'is-alerta' : '' ?>"><span>No mínimo ou abaixo</span><strong><?= count($estoque['abaixo']) ?></strong></div>
    </div>
    <div class="card card--tabela rl-cartao rl-cartao--tabela">
      <header class="rl-cartao__cabeca"><h3>Peças para repor <span class="rl-selo">hoje</span></h3><a class="rl-link nao-imprimir" href="./produtos?tipo=PECA&amp;baixo=1">Ver no estoque<?= icone('chevron-right', 12, 2) ?></a></header>
      <?php if (!$estoque['abaixo']): ?><div class="rl-vazio">Nenhuma peça no mínimo ou abaixo dele.</div><?php else: ?>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Peça</th><th class="num">Saldo</th><th class="num">Mínimo</th></tr></thead>
          <tbody><?php foreach ($estoque['abaixo'] as $p): ?><tr data-href="./produto?id=<?= (int) $p['id'] ?>"><td><a href="./produto?id=<?= (int) $p['id'] ?>"><?= e($p['nome']) ?></a></td><td class="num"><strong><?= $qtd($p['estoque_atual']) ?></strong></td><td class="num secundario"><?= $qtd($p['estoque_minimo']) ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
      <?php endif; ?>
    </div>
  </section>
</div>

<script>
  // Abas do relatório: uma visível por vez; lembra a aba no endereço (#dinheiro) e aceita as setas do teclado.
  (function () {
    var abas = [].slice.call(document.querySelectorAll('.rl-abas [role=tab]'));
    if (!abas.length) return;
    var paineis = document.querySelectorAll('.rl-painel');
    function ativar(nome, foco) {
      if (!document.querySelector('[data-painel="' + nome + '"]')) nome = abas[0].dataset.aba;
      abas.forEach(function (a) { var s = a.dataset.aba === nome; a.setAttribute('aria-selected', s); a.tabIndex = s ? 0 : -1; if (s && foco) a.focus(); });
      paineis.forEach(function (p) { p.hidden = p.dataset.painel !== nome; });
      if (history.replaceState) history.replaceState(null, '', nome === abas[0].dataset.aba ? location.pathname + location.search : '#' + nome);
    }
    abas.forEach(function (a, i) {
      a.addEventListener('click', function () { ativar(a.dataset.aba); });
      a.addEventListener('keydown', function (e) {
        var d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
        if (d) { e.preventDefault(); ativar(abas[(i + d + abas.length) % abas.length].dataset.aba, true); }
      });
    });
    if (location.hash) ativar(location.hash.slice(1));
    // Os links de período mantêm a aba aberta.
    document.querySelectorAll('.rl-periodo a').forEach(function (l) { l.addEventListener('click', function () { if (location.hash) l.href = l.href.split('#')[0] + location.hash; }); });
    var form = document.querySelector('.rl-datas__painel');
    if (form) form.addEventListener('submit', function () { if (location.hash) form.action = './relatorios' + location.hash; });
    // Painel de datas fecha ao clicar fora ou com Esc.
    var datas = document.querySelector('.rl-datas');
    // O calendário dos campos de data abre fora do painel (.mec-pop): clicar nele não fecha o painel, e o Esc fecha primeiro o calendário.
    document.addEventListener('click', function (e) { if (datas && datas.open && !datas.contains(e.target) && !e.target.closest('.mec-pop')) datas.open = false; });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && datas && datas.open && !document.querySelector('.mec-pop')) datas.open = false; });
  })();
</script>
