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

// Linha suave (curva de Catmull-Rom) para o gráfico de fluxo, em coordenadas do viewBox 520×190.
$curva = function (array $valores, float $max): array {
    $n = count($valores);
    $pts = [];
    foreach (array_values($valores) as $i => $v) {
        $pts[] = [$n > 1 ? $i * 520 / ($n - 1) : 260, 170 - ($max > 0 ? $v / $max : 0) * 150];
    }
    $d = sprintf('M%.1f %.1f', $pts[0][0], $pts[0][1]);
    for ($i = 0; $i < $n - 1; $i++) {
        $p0 = $pts[max(0, $i - 1)]; $p1 = $pts[$i]; $p2 = $pts[$i + 1]; $p3 = $pts[min($n - 1, $i + 2)];
        // Pontos de controle presos entre os dois pontos na vertical: a curva não passa abaixo do zero.
        $y1 = min(max($p1[1] + ($p2[1] - $p0[1]) / 6, min($p1[1], $p2[1])), max($p1[1], $p2[1]));
        $y2 = min(max($p2[1] - ($p3[1] - $p1[1]) / 6, min($p1[1], $p2[1])), max($p1[1], $p2[1]));
        $d .= sprintf('C%.1f %.1f %.1f %.1f %.1f %.1f', $p1[0] + ($p2[0] - $p0[0]) / 6, $y1, $p2[0] - ($p3[0] - $p1[0]) / 6, $y2, $p2[0], $p2[1]);
    }

    return [$d, end($pts)];
};
$osDia = array_column($g['fluxo'], 'os');
$recDia = array_column($g['fluxo'], 'receita');
[$linhaOs, $fimOs] = $curva($osDia, (float) max(array_merge([1], $osDia)) * 1.1);
[$linhaRec] = $curva($recDia, (float) max(array_merge([1], $recDia)) * 1.1);

// Rosca da situação das OS no mês.
$fatias = [
    'concluidas' => ['Concluídas', 'var(--grafico-1)'], 'andamento' => ['Em andamento', 'var(--grafico-2)'], 'peca' => ['Aguardando peça', 'var(--grafico-3)'],
    'abertas' => ['Abertas', 'var(--grafico-4)'], 'canceladas' => ['Canceladas', 'var(--grafico-5)'],
];
$totalMes = array_sum($g['status_mes']);
$circ = 2 * M_PI * 62;

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
if ($alertas['orc_vencidos']) $avisos[] = ['calendar', $alertas['orc_vencidos'] . ' orçamento(s) com validade vencida', './ordens?situacao=orcamento', ''];
if ($admin && $alertas['receber_vencidas']) $avisos[] = ['error', (int) $alertas['receber_vencidas'] . ' a receber vencida(s) · ' . dinheiro($alertas['receber_valor']), './financeiro?tipo=RECEBER&situacao=vencidos', 'danger'];
if ($admin && $alertas['pagar_vencidas']) $avisos[] = ['warning', (int) $alertas['pagar_vencidas'] . ' a pagar vencida(s) · ' . dinheiro($alertas['pagar_valor']), './financeiro?tipo=PAGAR&situacao=vencidos', 'danger'];
if ($admin && $alertas['estoque_baixo']) $avisos[] = ['part', $alertas['estoque_baixo'] . ' peça(s) no estoque mínimo', './produtos?tipo=PECA&baixo=1', ''];
if ($alertas['agenda_hoje']) $avisos[] = ['calendar', $alertas['agenda_hoje'] . ' agendamento(s) hoje', './agenda?periodo=dia', ''];
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Olá, <?= e(explode(' ', Contexto::usuario()['nome'])[0]) ?>!</h1>
    <p class="page-header__subtitle">Visão geral da oficina e das ordens de serviço · <?= e($hoje) ?></p>
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
  <div class="avisos-painel">
    <?php foreach ($avisos as [$icone, $texto, $href, $tom]): ?>
      <a class="aviso-chip<?= $tom ? ' aviso-chip--' . $tom : '' ?>" href="<?= e($href) ?>"><?= icone($icone, 14) ?><?= e($texto) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="painel-graficos">
  <section class="card quadro">
    <div class="quadro__topo"><h2 class="card__title">Fluxo de OS × Receita</h2><span class="etiqueta">Últimos 7 dias</span></div>
    <svg class="quadro__linha" viewBox="0 0 520 190" preserveAspectRatio="none" role="img"
         aria-label="OS abertas e receita dos últimos 7 dias: <?= e(implode('; ', array_map(fn ($d, $v) => data_br($d) . ' ' . $v['os'] . ' OS, ' . dinheiro($v['receita']), array_keys($g['fluxo']), $g['fluxo']))) ?>">
      <defs>
        <linearGradient id="degrade-os" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="currentColor" stop-opacity=".16"/><stop offset="1" stop-color="currentColor" stop-opacity="0"/></linearGradient>
        <linearGradient id="degrade-receita" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="currentColor" stop-opacity=".08"/><stop offset="1" stop-color="currentColor" stop-opacity="0"/></linearGradient>
      </defs>
      <path d="M0 20H520M0 70H520M0 120H520" class="quadro__grade"/>
      <path d="M0 170H520" class="quadro__base"/>
      <path d="<?= $linhaRec ?>L520 170L0 170Z" class="quadro__area quadro__area--2"/>
      <path d="<?= $linhaRec ?>" class="quadro__traco quadro__traco--2"/>
      <path d="<?= $linhaOs ?>L520 170L0 170Z" class="quadro__area"/>
      <path d="<?= $linhaOs ?>" class="quadro__traco"/>
      <circle cx="<?= round($fimOs[0] - 3, 1) ?>" cy="<?= round($fimOs[1], 1) ?>" r="4" class="quadro__ponto"/>
    </svg>
    <div class="quadro__eixo">
      <?php $i = 0; foreach (array_keys($g['fluxo']) as $dia): $i++; ?><span><?= $i === count($g['fluxo']) ? 'Hoje' : $curtos[(int) date('N', strtotime($dia))] ?></span><?php endforeach; ?>
    </div>
    <div class="quadro__legenda">
      <span><span class="legenda-cor"></span>Ordens de serviço abertas</span>
      <span><span class="legenda-cor legenda-cor--tracejada"></span>Receita (R$)</span>
    </div>
  </section>

  <section class="card quadro">
    <div class="quadro__topo"><h2 class="card__title">Status das OS</h2><span class="etiqueta">Mês</span></div>
    <div class="rosca">
      <div class="rosca__desenho">
        <svg width="140" height="140" viewBox="0 0 140 140" aria-hidden="true">
          <circle cx="70" cy="70" r="62" fill="none" stroke="var(--grafico-vazio)" stroke-width="16"/>
          <?php $acumulado = 0.0; foreach ($fatias as $k => [, $cor]): $v = $g['status_mes'][$k]; if (!$v) continue; $tam = $v / $totalMes * $circ; ?>
            <circle cx="70" cy="70" r="62" fill="none" stroke="<?= $cor ?>" stroke-width="16" stroke-dasharray="<?= round(max(0, $tam - 2), 1) ?> <?= round($circ, 1) ?>"
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
    <div class="barras" role="img" aria-label="Faturamento por mês: <?= e(implode('; ', array_map(fn ($m, $v) => $mesesCurto[(int) substr($m, 5)] . ' ' . dinheiro($v), array_keys($g['meses']), $g['meses']))) ?>">
      <div class="barras__escala"><?php for ($i = 4; $i >= 0; $i--): ?><span><?= $escala($topo / 4 * $i) ?></span><?php endfor; ?></div>
      <div class="barras__area">
        <?php for ($i = 0; $i < 5; $i++): ?><span class="barras__grade" style="bottom:calc(18px + (100% - 18px) * <?= $i / 4 ?>)"></span><?php endfor; ?>
        <?php foreach ($g['meses'] as $m => $v): ?>
          <div class="barras__coluna" title="<?= e($mesesCurto[(int) substr($m, 5)] . ' · ' . dinheiro($v)) ?>">
            <span class="barras__barra" style="height:<?= round($v / $topo * 100, 1) ?>%"></span>
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
