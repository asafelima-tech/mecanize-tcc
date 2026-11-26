<?php
/**
 * Agenda (Design System, tela 13): navegação por dia, semana ou mês, números do período, os
 * horários agrupados por dia e o calendário do mês numa tira horizontal acima (coluna única).
 * Espera: $periodo, $ini, $fim, $ref, $mes, $f, $p (Paginacao), $agendamentos, $resumo, $diasMarcados, $tecnicos
 */
$titulo = 'Agenda';
$diasNome = ['', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
$mesesNome = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$passo = ['dia' => '1 day', 'semana' => '7 days', 'mes' => '1 month'][$periodo];
$base = ['periodo' => $periodo, 'data' => $ref->format('Y-m-d'), 'status' => $f['status'], 'tecnico' => $f['tecnico'] ?: ''];
$link = fn(array $troca) => './agenda?' . http_build_query(array_filter($troca + $base, fn($v) => $v !== '' && $v !== null));
$dIni = new DateTimeImmutable($ini);
$dFim = (new DateTimeImmutable($fim))->modify('-1 day');
$porDia = [];
foreach ($agendamentos as $a) {
    $porDia[substr($a['data_hora'], 0, 10)][] = $a;
}
$destaque = (int) ($_GET['destaque'] ?? 0);
$ehAdmin = Contexto::perfil() === 'ADMIN';
$hoje = date('Y-m-d');
$ocultos = $base;
// Depois de confirmar, cancelar ou reativar, a agenda volta para a mesma página.
if ($p->pagina > 1) $ocultos['pagina'] = $p->pagina;
$dataLonga = fn(DateTimeImmutable $d) => (int) $d->format('j') . ' de ' . $mesesNome[(int) $d->format('n')];

// Título do período: "12 de novembro de 2026", "9 a 15 de novembro de 2026" ou "Novembro de 2026".
if ($periodo === 'dia') {
    $tituloPeriodo = $diasNome[(int) $ref->format('N')] . ', ' . $dataLonga($ref) . ' de ' . $ref->format('Y');
} elseif ($periodo === 'mes') {
    $tituloPeriodo = ucfirst($mesesNome[(int) $dIni->format('n')]) . ' de ' . $dIni->format('Y');
} elseif ($dIni->format('n') === $dFim->format('n')) {
    $tituloPeriodo = (int) $dIni->format('j') . ' a ' . $dataLonga($dFim) . ' de ' . $dFim->format('Y');
} else {
    $tituloPeriodo = $dataLonga($dIni) . ' a ' . $dataLonga($dFim) . ' de ' . $dFim->format('Y');
}

$total = array_sum($resumo);
$naoComparecidos = (int) $resumo['FALTOU'];
$campos = function () use ($ocultos): string {
    $h = csrf_field();
    foreach ($ocultos as $k => $v) {
        $h .= '<input type="hidden" name="' . e($k) . '" value="' . e((string) $v) . '">';
    }
    return $h;
};
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Agenda</h1>
    <p class="page-header__subtitle">Agendamentos da oficina por dia, semana ou mês.</p>
  </div>
  <div class="page-header__actions">
    <a class="btn btn--primary" href="./agenda-form?data=<?= e($periodo === 'dia' ? $ref->format('Y-m-d') : max($hoje, $ini)) ?>"><?= icone('plus') ?> Novo agendamento</a>
  </div>
</div>

<?php
$noPeriodoAtual = $hoje >= $ini && $hoje < $fim;
// Versão curta do título para o celular: "16–22 nov. 2026", "28 nov. – 4 dez. 2026", "Ter, 17 nov. 2026", "Nov. 2026".
$mesCurto = fn(DateTimeImmutable $d) => ['', 'jan.', 'fev.', 'mar.', 'abr.', 'maio', 'jun.', 'jul.', 'ago.', 'set.', 'out.', 'nov.', 'dez.'][(int) $d->format('n')];
if ($periodo === 'dia') {
    $tituloCurto = mb_substr($diasNome[(int) $ref->format('N')], 0, 3) . ', ' . (int) $ref->format('j') . ' ' . $mesCurto($ref) . ' ' . $ref->format('Y');
} elseif ($periodo === 'mes') {
    $tituloCurto = ucfirst($mesesNome[(int) $dIni->format('n')]) . ' ' . $dIni->format('Y');
} elseif ($dIni->format('n') === $dFim->format('n')) {
    $tituloCurto = (int) $dIni->format('j') . '–' . (int) $dFim->format('j') . ' ' . $mesCurto($dFim) . ' ' . $dFim->format('Y');
} else {
    $tituloCurto = (int) $dIni->format('j') . ' ' . $mesCurto($dIni) . ' – ' . (int) $dFim->format('j') . ' ' . $mesCurto($dFim) . ' ' . $dFim->format('Y');
}
$filtrosAtivos = ($f['status'] ? 1 : 0) + ($f['tecnico'] ? 1 : 0);
?>
<div class="agenda-barra">
  <div class="agenda-barra__periodo">
    <h2 class="agenda-barra__titulo"><span class="agenda-barra__longo"><?= e($tituloPeriodo) ?></span><span class="agenda-barra__curto" aria-hidden="true"><?= e($tituloCurto) ?></span></h2>
    <span class="agenda-barra__setas">
      <a class="agenda-barra__seta" href="<?= e($link(['data' => $ref->modify('-' . $passo)->format('Y-m-d')])) ?>" aria-label="Período anterior" title="Anterior"><?= icone('chevron-left', 18, 2) ?></a>
      <a class="agenda-barra__seta" href="<?= e($link(['data' => $ref->modify('+' . $passo)->format('Y-m-d')])) ?>" aria-label="Próximo período" title="Próximo"><?= icone('chevron-right', 18, 2) ?></a>
    </span>
    <?php if (!$noPeriodoAtual): ?><a class="agenda-barra__hoje" href="<?= e($link(['data' => $hoje])) ?>">Hoje</a><?php endif; ?>
  </div>
  <div class="agenda-barra__acoes">
    <nav class="agenda-barra__vista" aria-label="Visualizar por">
      <?php foreach (['dia' => 'Dia', 'semana' => 'Semana', 'mes' => 'Mês'] as $k => $r): ?>
        <a href="<?= e($link(['periodo' => $k])) ?>"<?= $periodo === $k ? ' class="ativa" aria-current="page"' : '' ?>><?= $r ?></a>
      <?php endforeach; ?>
    </nav>
    <details class="agenda-filtros">
      <summary class="agenda-barra__filtro<?= $filtrosAtivos ? ' is-ativo' : '' ?>" aria-label="Filtros<?= $filtrosAtivos ? ' (' . $filtrosAtivos . ' ativo' . ($filtrosAtivos > 1 ? 's' : '') . ')' : '' ?>">
        <?= icone('filter', 16) ?><span>Filtros</span><?php if ($filtrosAtivos): ?><b class="agenda-barra__n"><?= $filtrosAtivos ?></b><?php endif; ?>
      </summary>
      <form class="agenda-filtros__painel" method="get" action="./agenda" data-aplicar-ao-mudar>
        <input type="hidden" name="periodo" value="<?= e($periodo) ?>">
        <input type="hidden" name="data" value="<?= e($ref->format('Y-m-d')) ?>">
        <label class="agenda-filtros__campo"><span>Situação</span>
          <select name="status">
            <option value="">Todas</option>
            <?php foreach (Agendamento::SITUACOES as $k => [$r]): ?><option value="<?= $k ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="agenda-filtros__campo"><span>Técnico</span>
          <select name="tecnico">
            <option value="">Todos</option>
            <?php foreach ($tecnicos as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) $f['tecnico'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?></option><?php endforeach; ?>
          </select>
        </label>
        <?php if ($filtrosAtivos): ?><a class="agenda-filtros__limpar" href="<?= e($link(['status' => '', 'tecnico' => ''])) ?>">Limpar filtros</a><?php endif; ?>
        <noscript><button class="btn btn--ghost btn--sm" type="submit">Aplicar</button></noscript>
      </form>
    </details>
  </div>
</div>
<script>
  // Filtros da agenda: aplicam ao escolher; o painel fecha com Esc ou tocando fora.
  (function () {
    var d = document.querySelector('.agenda-filtros');
    if (!d) return;
    d.querySelectorAll('[data-aplicar-ao-mudar] select').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });
    document.addEventListener('click', function (e) { if (d.open && !d.contains(e.target)) d.open = false; });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && d.open) { d.open = false; d.querySelector('summary').focus(); } });
  })();
</script>

<?php
// Calendário do mês numa tira horizontal de largura cheia, logo abaixo da barra do período (no lugar do calendário lateral).
// As setas trocam o mês da tira (?mes=); cada dia abre a visão do dia; o ponto marca os dias com agendamento.
$antes = $mes->modify('-1 month');
$depois = $mes->modify('+1 month');
$letrasSemana = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
$diasComAgenda = count($diasMarcados);
$refDia = $ref->format('Y-m-d');
$mesIni = $mes->format('Y-m-d');
$mesFim = $depois->format('Y-m-d');
$foco = $refDia >= $mesIni && $refDia < $mesFim ? $refDia : ($hoje >= $mesIni && $hoje < $mesFim ? $hoje : '');
?>
<nav class="card cu-tira" aria-label="Calendário do mês">
  <div class="cu-tira__topo">
    <strong class="cu-tira__mes"><?= e(ucfirst($mesesNome[(int) $mes->format('n')])) ?> de <?= $mes->format('Y') ?></strong>
    <span class="cu-tira__legenda"><i aria-hidden="true"></i><?= $diasComAgenda ? $diasComAgenda . ' dia' . ($diasComAgenda === 1 ? '' : 's') . ' com agendamento' : 'Nenhum dia com agendamento' ?></span>
    <span class="cu-tira__setas">
      <a class="agenda-barra__seta" href="<?= e($link(['mes' => $antes->format('Y-m')])) ?>" aria-label="Mês anterior" title="Mês anterior"><?= icone('chevron-left', 18, 2) ?></a>
      <a class="agenda-barra__seta" href="<?= e($link(['mes' => $depois->format('Y-m')])) ?>" aria-label="Próximo mês" title="Próximo mês"><?= icone('chevron-right', 18, 2) ?></a>
    </span>
  </div>
  <div class="cu-tira__dias" data-tira>
    <?php for ($d = $mes; $d < $depois; $d = $d->modify('+1 day')): $ds = $d->format('Y-m-d'); $sem = (int) $d->format('w');
          $classes = ($ds === $hoje ? ' is-hoje' : '') . ($ds >= $ini && $ds < $fim ? ' is-periodo' : '') . (isset($diasMarcados[$ds]) ? ' tem-agenda' : '') . ($sem === 0 || $sem === 6 ? ' is-fds' : ''); ?>
      <a class="cu-tira__dia<?= $classes ?>" href="<?= e($link(['periodo' => 'dia', 'data' => $ds, 'mes' => null])) ?>"<?= $ds === $foco ? ' data-foco' : '' ?><?= $ds === $hoje ? ' aria-current="date"' : '' ?>
         aria-label="<?= e($diasNome[(int) $d->format('N')] . ', ' . $dataLonga($d)) ?><?= isset($diasMarcados[$ds]) ? ', ' . $diasMarcados[$ds] . ' agendamento(s)' : '' ?>">
        <span class="cu-tira__sem" aria-hidden="true"><?= $letrasSemana[$sem] ?></span><b><?= (int) $d->format('j') ?></b><i aria-hidden="true"></i>
      </a>
    <?php endfor; ?>
  </div>
</nav>
<script>
  // A tira abre centralizada no dia escolhido (ou em hoje), sem rolar a página.
  (function () {
    var t = document.querySelector('[data-tira]');
    var f = t && t.querySelector('[data-foco]');
    if (!f || t.scrollWidth <= t.clientWidth) return;
    t.scrollLeft = f.offsetLeft - (t.clientWidth - f.offsetWidth) / 2;
  })();
</script>

<div class="stat-grid stat-grid--icone">
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Total no período</span><?= icone('calendar') ?></div>
    <span class="stat-card__value"><?= $total ?></span>
    <span class="stat-card__pe">agendamento<?= $total === 1 ? '' : 's' ?></span>
  </div>
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Confirmados</span><?= icone('check') ?></div>
    <span class="stat-card__value"><?= (int) $resumo['CONFIRMADO'] ?></span>
    <span class="stat-card__pe"><?= $total ? number_format($resumo['CONFIRMADO'] / $total * 100, 0) . '% do período' : 'nada no período' ?></span>
  </div>
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Aguardando confirmação</span><?= icone('clock') ?></div>
    <span class="stat-card__value"><?= (int) $resumo['AGENDADO'] ?></span>
    <span class="stat-card__pe"><?= (int) $resumo['CONCLUIDO'] ?> já atendido<?= (int) $resumo['CONCLUIDO'] === 1 ? '' : 's' ?></span>
  </div>
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Cancelados / faltas</span><?= icone('close') ?></div>
    <span class="stat-card__value"><?= (int) $resumo['CANCELADO'] + $naoComparecidos ?></span>
    <span class="stat-card__pe"><?= $naoComparecidos ?> não compareceu</span>
  </div>
</div>

<div class="agenda-dias cu-agenda-lista">
    <?php if (!$agendamentos): ?>
      <div class="card"><div class="vazio"><?= icone('calendar', 32, 1.25) ?>Nada marcado <?= ['dia' => 'neste dia', 'semana' => 'nesta semana', 'mes' => 'neste mês'][$periodo] ?><?= $f['status'] || $f['tecnico'] ? ' com esses filtros' : '' ?>.</div></div>
    <?php endif; ?>
    <?php foreach ($porDia as $dia => $lista): $d = new DateTimeImmutable($dia); $n = count($lista); ?>
      <section class="card agenda-dia-card<?= $dia === $hoje ? ' is-hoje' : '' ?>" aria-label="<?= e($diasNome[(int) $d->format('N')] . ', ' . $dataLonga($d)) ?>">
        <header class="agenda-dia-card__topo">
          <a href="<?= e($link(['periodo' => 'dia', 'data' => $dia])) ?>"><?= e($diasNome[(int) $d->format('N')]) ?>, <?= e($dataLonga($d)) ?></a>
          <span><?= $dia === $hoje ? 'Hoje · ' : '' ?><?= $n ?> agendamento<?= $n === 1 ? '' : 's' ?></span>
        </header>
        <?php foreach ($lista as $a): [$rotulo, $cor] = Agendamento::SITUACOES[$a['status']]; $ativo = in_array($a['status'], Agendamento::ATIVOS, true);
              $fimServico = (new DateTimeImmutable($a['data_hora']))->modify('+' . (int) $a['duracao_min'] . ' minutes'); ?>
          <div class="agenda-item<?= $destaque === (int) $a['id'] ? ' is-destaque' : '' ?><?= $ativo ? '' : ' is-apagado' ?>">
            <div class="agenda-item__hora"><strong><?= substr($a['data_hora'], 11, 5) ?></strong><span>até <?= $fimServico->format('H:i') ?></span></div>
            <div class="agenda-item__corpo">
              <div class="agenda-item__titulo">
                <strong><?= e($a['servico']) ?></strong>
                <span class="badge badge--<?= $cor ?>"><?= e($rotulo) ?></span>
                <?php if ($a['os_numero']): ?><a class="agenda-item__os" href="./os?id=<?= (int) $a['ordem_servico_id'] ?>"><?= os_numero($a['os_numero']) ?></a><?php endif; ?>
              </div>
              <div class="agenda-item__linha">
                <a href="./cliente?id=<?= (int) $a['cliente_id'] ?>"><?= e($a['cliente']) ?></a><?php if ($a['placa']): ?> · <span class="placa"><?= e($a['placa']) ?></span><?php endif; ?><?= $a['tecnico'] ? ' · ' . e($a['tecnico']) : '' ?><?= $a['cliente_telefone'] ? ' · ' . e(telefone_fmt($a['cliente_telefone'])) : '' ?>
              </div>
              <?php if ($a['observacoes']): ?><div class="agenda-item__linha"><?= e($a['observacoes']) ?></div><?php endif; ?>
            </div>
            <div class="agenda-item__acoes acoes">
              <?php if ($ativo): ?>
                <?php if ($a['status'] === 'AGENDADO'): ?>
                  <form method="post" action="./agenda-marcar?id=<?= (int) $a['id'] ?>"><?= $campos() ?><button class="btn btn--primary btn--sm" name="status" value="CONFIRMADO">Confirmar</button></form>
                <?php endif; ?>
                <a class="btn btn--ghost btn--sm" href="./agenda-os?id=<?= (int) $a['id'] ?>">Gerar OS</a>
                <details class="menu-mais"><summary class="btn btn--fantasma btn--icone" aria-label="Mais ações"><?= icone('more-h') ?></summary>
                  <div class="menu-mais__lista">
                    <a href="./agenda-form?id=<?= (int) $a['id'] ?>">Editar</a>
                    <form method="post" action="./agenda-marcar?id=<?= (int) $a['id'] ?>"><?= $campos() ?><button name="status" value="FALTOU">Não compareceu</button></form>
                    <form method="post" action="./agenda-marcar?id=<?= (int) $a['id'] ?>" data-confirmar="Cancelar este agendamento?"><?= $campos() ?><button name="status" value="CANCELADO">Cancelar</button></form>
                  </div>
                </details>
              <?php elseif (!$a['ordem_servico_id']): ?>
                <form method="post" action="./agenda-marcar?id=<?= (int) $a['id'] ?>"><?= $campos() ?><button class="btn btn--ghost btn--sm" name="status" value="AGENDADO">Reativar</button></form>
                <?php if ($ehAdmin): ?>
                  <form method="post" action="./agenda-excluir?id=<?= (int) $a['id'] ?>" data-confirmar="Excluir este agendamento? Não dá para desfazer."><?= $campos() ?><button class="btn btn--ghost btn--sm btn--icone btn--excluir" aria-label="Excluir agendamento" title="Excluir"><?= icone('trash', 17, 2.1) ?></button></form>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
    <?php if ($p->paginas() > 1): ?>
      <div class="card agenda-paginas"><?php $rotuloItens = 'agendamentos'; require V2_RAIZ . '/app/views/layout/_rodape.php'; ?></div>
    <?php endif; ?>
</div>
