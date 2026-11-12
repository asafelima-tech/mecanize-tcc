<?php
/**
 * Agenda (Design System, tela 13): navegação por dia, semana ou mês, números do período, os
 * horários agrupados por dia e o calendário do mês ao lado.
 * Espera: $periodo, $ini, $fim, $ref, $mes, $f, $agendamentos, $resumo, $diasMarcados, $tecnicos
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

<form class="barra-periodo" method="get" action="./agenda">
  <input type="hidden" name="periodo" value="<?= e($periodo) ?>">
  <input type="hidden" name="data" value="<?= e($ref->format('Y-m-d')) ?>">
  <div class="barra-periodo__nav">
    <a class="btn btn--contorno btn--icone" href="<?= e($link(['data' => $ref->modify('-' . $passo)->format('Y-m-d')])) ?>" aria-label="Período anterior"><?= icone('chevron-left') ?></a>
    <a class="btn btn--contorno" href="<?= e($link(['data' => $hoje])) ?>">Hoje</a>
    <a class="btn btn--contorno btn--icone" href="<?= e($link(['data' => $ref->modify('+' . $passo)->format('Y-m-d')])) ?>" aria-label="Próximo período"><?= icone('chevron-right') ?></a>
    <h2 class="barra-periodo__titulo"><?= e($tituloPeriodo) ?></h2>
  </div>
  <div class="barra-periodo__filtros">
    <nav class="abas abas--compactas" aria-label="Período">
      <?php foreach (['dia' => 'Dia', 'semana' => 'Semana', 'mes' => 'Mês'] as $k => $r): ?>
        <a href="<?= e($link(['periodo' => $k])) ?>"<?= $periodo === $k ? ' class="ativa" aria-current="page"' : '' ?>><?= $r ?></a>
      <?php endforeach; ?>
    </nav>
    <select name="status" aria-label="Situação">
      <option value="">Todas as situações</option>
      <?php foreach (Agendamento::SITUACOES as $k => [$r]): ?><option value="<?= $k ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
    </select>
    <select name="tecnico" aria-label="Técnico">
      <option value="">Todos os técnicos</option>
      <?php foreach ($tecnicos as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) $f['tecnico'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn--ghost" type="submit"><?= icone('filter') ?> Filtrar</button>
  </div>
</form>

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

<div class="agenda-grade">
  <div class="agenda-dias">
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
                  <form method="post" action="./agenda-excluir?id=<?= (int) $a['id'] ?>" data-confirmar="Excluir este agendamento? Não dá para desfazer."><?= $campos() ?><button class="btn btn--fantasma btn--icone" aria-label="Excluir" title="Excluir"><?= icone('trash') ?></button></form>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
  </div>

  <aside class="card mini-cal" aria-label="Calendário">
    <?php $antes = $mes->modify('-1 month'); $depois = $mes->modify('+1 month'); ?>
    <div class="mini-cal__topo">
      <strong><?= e(ucfirst($mesesNome[(int) $mes->format('n')])) ?> <?= $mes->format('Y') ?></strong>
      <span>
        <a class="btn btn--fantasma btn--icone" href="<?= e($link(['mes' => $antes->format('Y-m')])) ?>" aria-label="Mês anterior"><?= icone('chevron-left') ?></a>
        <a class="btn btn--fantasma btn--icone" href="<?= e($link(['mes' => $depois->format('Y-m')])) ?>" aria-label="Próximo mês"><?= icone('chevron-right') ?></a>
      </span>
    </div>
    <div class="mini-cal__grade" role="grid">
      <?php foreach (['D', 'S', 'T', 'Q', 'Q', 'S', 'S'] as $sem): ?><span class="mini-cal__sem" aria-hidden="true"><?= $sem ?></span><?php endforeach; ?>
      <?php for ($i = 0; $i < (int) $mes->format('w'); $i++): ?><span></span><?php endfor; ?>
      <?php for ($d = $mes; $d < $depois; $d = $d->modify('+1 day')): $ds = $d->format('Y-m-d');
            $classes = ($ds === $hoje ? ' is-hoje' : '') . ($ds >= $ini && $ds < $fim ? ' is-periodo' : '') . (isset($diasMarcados[$ds]) ? ' tem-agenda' : ''); ?>
        <a class="mini-cal__dia<?= $classes ?>" href="<?= e($link(['periodo' => 'dia', 'data' => $ds, 'mes' => null])) ?>"
           aria-label="<?= e($dataLonga($d)) ?><?= isset($diasMarcados[$ds]) ? ', ' . $diasMarcados[$ds] . ' agendamento(s)' : '' ?>"><?= (int) $d->format('j') ?></a>
      <?php endfor; ?>
    </div>
  </aside>
</div>
