<?php
/** Espera: $periodo, $ini, $fim, $ref, $f, $agendamentos, $resumo, $tecnicos */
$titulo = 'Agenda';
$dias = ['', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado', 'domingo'];
$passo = $periodo === 'dia' ? '1 day' : '7 days';
$base = ['periodo' => $periodo, 'data' => $ref->format('Y-m-d'), 'status' => $f['status'], 'tecnico' => $f['tecnico'] ?: ''];
$link = fn(array $troca) => './agenda?' . http_build_query(array_filter($troca + $base, fn($v) => $v !== '' && $v !== null));
$fimInclusivo = (new DateTimeImmutable($fim))->modify('-1 day');
$porDia = [];
foreach ($agendamentos as $a) {
    $porDia[substr($a['data_hora'], 0, 10)][] = $a;
}
$destaque = (int) ($_GET['destaque'] ?? 0);
$ehAdmin = Contexto::perfil() === 'ADMIN';
$hoje = date('Y-m-d');
$ocultos = $base;
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Agenda</h1>
    <p class="page-header__subtitle">
      <?= $periodo === 'dia' ? e(ucfirst($dias[(int) $ref->format('N')])) . ', ' . $ref->format('d/m/Y') : 'Semana de ' . (new DateTimeImmutable($ini))->format('d/m') . ' a ' . $fimInclusivo->format('d/m/Y') ?>
      · <?= (int) ($resumo['AGENDADO'] + $resumo['CONFIRMADO']) ?> marcado(s) · <?= (int) $resumo['CONCLUIDO'] ?> atendido(s)<?= $resumo['FALTOU'] ? ' · ' . (int) $resumo['FALTOU'] . ' falta(s)' : '' ?>
    </p>
  </div>
  <a class="btn btn--primary" href="./agenda-form?data=<?= e($periodo === 'dia' ? $ref->format('Y-m-d') : max($hoje, $ini)) ?>"><i class="bi bi-plus-lg"></i> Agendar</a>
</div>

<div class="card card--tabela">
  <form class="filtros" method="get" action="./agenda">
    <input type="hidden" name="periodo" value="<?= e($periodo) ?>">
    <div class="acoes">
      <a class="btn btn--ghost btn--sm" href="<?= e($link(['data' => $ref->modify('-' . $passo)->format('Y-m-d')])) ?>" aria-label="Anterior"><i class="bi bi-chevron-left"></i></a>
      <a class="btn btn--ghost btn--sm" href="<?= e($link(['data' => $hoje])) ?>">Hoje</a>
      <a class="btn btn--ghost btn--sm" href="<?= e($link(['data' => $ref->modify('+' . $passo)->format('Y-m-d')])) ?>" aria-label="Próximo"><i class="bi bi-chevron-right"></i></a>
    </div>
    <input class="filtros__data" type="date" name="data" value="<?= e($ref->format('Y-m-d')) ?>" onchange="this.form.submit()" aria-label="Data">
    <div class="abas abas--compactas">
      <a href="<?= e($link(['periodo' => 'dia'])) ?>" class="<?= $periodo === 'dia' ? 'ativa' : '' ?>">Dia</a>
      <a href="<?= e($link(['periodo' => 'semana'])) ?>" class="<?= $periodo === 'semana' ? 'ativa' : '' ?>">Semana</a>
    </div>
    <select name="status" aria-label="Situação">
      <option value="">Todas as situações</option>
      <?php foreach (Agendamento::SITUACOES as $k => [$r]): ?><option value="<?= $k ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
    </select>
    <select name="tecnico" aria-label="Técnico">
      <option value="">Todos os técnicos</option>
      <?php foreach ($tecnicos as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) $f['tecnico'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?></option><?php endforeach; ?>
    </select>
  </form>

  <?php if (!$agendamentos): ?>
    <div class="vazio"><i class="bi bi-calendar3"></i>Nada marcado <?= $periodo === 'dia' ? 'neste dia' : 'nesta semana' ?><?= $f['status'] || $f['tecnico'] ? ' com esses filtros' : '' ?>.</div>
  <?php else: ?>
    <?php foreach ($porDia as $dia => $lista): $d = new DateTimeImmutable($dia); ?>
      <?php if ($periodo === 'semana'): ?>
        <div class="agenda-dia<?= $dia === $hoje ? ' agenda-dia--hoje' : '' ?>">
          <a href="<?= e($link(['periodo' => 'dia', 'data' => $dia])) ?>"><?= e(ucfirst($dias[(int) $d->format('N')])) ?>, <?= $d->format('d/m') ?></a><?= $dia === $hoje ? ' · hoje' : '' ?>
        </div>
      <?php endif; ?>
      <div class="table-wrap"><table class="table">
        <tbody>
          <?php foreach ($lista as $a): [$rotulo, $cor] = Agendamento::SITUACOES[$a['status']]; $ativo = in_array($a['status'], Agendamento::ATIVOS, true);
                $fimServico = (new DateTimeImmutable($a['data_hora']))->modify('+' . (int) $a['duracao_min'] . ' minutes'); ?>
            <tr class="<?= $destaque === (int) $a['id'] ? 'linha-destaque' : '' ?><?= $ativo ? '' : ' linha-apagada' ?>">
              <td style="width:110px;white-space:nowrap"><strong><?= substr($a['data_hora'], 11, 5) ?></strong><span class="sub">até <?= $fimServico->format('H:i') ?></span></td>
              <td><?= e($a['servico']) ?>
                <span class="sub"><a href="./cliente?id=<?= (int) $a['cliente_id'] ?>"><?= e($a['cliente']) ?></a><?= $a['placa'] ? ' · ' . e($a['placa']) : '' ?><?= $a['cliente_telefone'] ? ' · ' . e(telefone_fmt($a['cliente_telefone'])) : '' ?></span>
                <?php if ($a['observacoes']): ?><span class="sub"><?= e($a['observacoes']) ?></span><?php endif; ?></td>
              <td><?= e($a['tecnico'] ?? '—') ?></td>
              <td><span class="badge badge--<?= $cor ?>"><?= e($rotulo) ?></span><?php if ($a['os_numero']): ?><span class="sub"><a href="./os?id=<?= (int) $a['ordem_servico_id'] ?>">OS <?= os_numero($a['os_numero']) ?></a></span><?php endif; ?></td>
              <td class="num">
                <div class="acoes" style="justify-content:flex-end">
                  <?php if ($ativo): ?>
                    <a class="btn btn--primary btn--sm" href="./agenda-os?id=<?= (int) $a['id'] ?>">Gerar OS</a>
                    <?php if ($a['status'] === 'AGENDADO'): ?>
                      <form method="post" action="./agenda-marcar?id=<?= (int) $a['id'] ?>"><?= csrf_field() ?><?php foreach ($ocultos as $k => $v): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endforeach; ?><button class="btn btn--ghost btn--sm" name="status" value="CONFIRMADO">Confirmar</button></form>
                    <?php endif; ?>
                    <details class="menu-mais"><summary class="btn btn--ghost btn--sm" aria-label="Mais ações"><i class="bi bi-three-dots"></i></summary>
                      <div class="menu-mais__lista">
                        <a href="./agenda-form?id=<?= (int) $a['id'] ?>">Editar</a>
                        <form method="post" action="./agenda-marcar?id=<?= (int) $a['id'] ?>"><?= csrf_field() ?><?php foreach ($ocultos as $k => $v): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endforeach; ?><button name="status" value="FALTOU">Não compareceu</button></form>
                        <form method="post" action="./agenda-marcar?id=<?= (int) $a['id'] ?>" data-confirmar="Cancelar este agendamento?"><?= csrf_field() ?><?php foreach ($ocultos as $k => $v): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endforeach; ?><button name="status" value="CANCELADO">Cancelar</button></form>
                      </div>
                    </details>
                  <?php elseif (!$a['ordem_servico_id']): ?>
                    <form method="post" action="./agenda-marcar?id=<?= (int) $a['id'] ?>"><?= csrf_field() ?><?php foreach ($ocultos as $k => $v): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endforeach; ?><button class="btn btn--ghost btn--sm" name="status" value="AGENDADO">Reativar</button></form>
                    <?php if ($ehAdmin): ?>
                      <form method="post" action="./agenda-excluir?id=<?= (int) $a['id'] ?>" data-confirmar="Excluir este agendamento? Não dá para desfazer."><?= csrf_field() ?><?php foreach ($ocultos as $k => $v): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endforeach; ?><button class="btn btn--ghost btn--sm" aria-label="Excluir"><i class="bi bi-trash"></i></button></form>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
