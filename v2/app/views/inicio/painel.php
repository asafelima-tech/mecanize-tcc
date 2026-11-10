<?php
/** Espera: $resumo, $ultimas, $agenda, $alertas, $admin */
$titulo = 'Início';
$nomeStatus = [
    'ORCAMENTO' => ['Orçamento', 'neutral'], 'APROVADA' => ['Aprovada', 'info'], 'EM_EXECUCAO' => ['Em execução', 'info'],
    'AGUARDANDO_PECA' => ['Aguardando peça', 'warning'], 'PRONTA' => ['Pronta', 'success'], 'ENTREGUE' => ['Entregue', 'success'],
    'RECUSADA' => ['Recusada', 'danger'], 'CANCELADA' => ['Cancelada', 'danger'],
];
$cartoes = [
    ['Orçamentos abertos', $resumo['orcamentos'], 'bi-file-earmark-text', 'b1'],
    ['OS em andamento', $resumo['em_andamento'], 'bi-tools', 'b2'],
    ['Prontas p/ entrega', $resumo['prontas'], 'bi-check2-circle', 'b3'],
    ['Faturado no mês', dinheiro($resumo['faturado_mes']), 'bi-cash-coin', 'b4'],
];
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Olá, <?= e(explode(' ', Contexto::usuario()['nome'])[0]) ?></h1>
    <p class="page-header__subtitle"><?= e(Contexto::oficina()['nome']) ?> · <?= (int) $resumo['entregues_mes'] ?> OS entregue(s) neste mês · <?= (int) $resumo['clientes'] ?> cliente(s) ativo(s)</p>
  </div>
</div>

<div class="stat-grid">
  <?php foreach ($cartoes as [$rotulo, $valor, $icone, $cor]): ?>
    <div class="stat-card">
      <div class="stat-card__head">
        <span class="stat-card__icon <?= e($cor) ?>"><i class="bi <?= e($icone) ?>" aria-hidden="true"></i></span>
      </div>
      <div class="stat-card__label"><?= e($rotulo) ?></div>
      <div class="stat-card__value"><?= e($valor) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<?php
  $avisos = [];
  if ($alertas['aguardando']) $avisos[] = ['bi-hourglass-split', $alertas['aguardando'] . ' OS aguardando peça', './ordens?situacao=execucao', 'warning'];
  if ($alertas['orc_vencidos']) $avisos[] = ['bi-calendar-x', $alertas['orc_vencidos'] . ' orçamento(s) com validade vencida', './ordens?situacao=orcamento', 'neutral'];
  if ($admin && $alertas['receber_vencidas']) $avisos[] = ['bi-exclamation-circle', (int) $alertas['receber_vencidas'] . ' conta(s) a receber vencida(s) · ' . dinheiro($alertas['receber_valor']), './financeiro?tipo=RECEBER&situacao=vencidos', 'danger'];
  if ($admin && $alertas['pagar_vencidas']) $avisos[] = ['bi-exclamation-triangle', (int) $alertas['pagar_vencidas'] . ' conta(s) a pagar vencida(s) · ' . dinheiro($alertas['pagar_valor']), './financeiro?tipo=PAGAR&situacao=vencidos', 'danger'];
  if ($admin && $alertas['estoque_baixo']) $avisos[] = ['bi-box-seam', $alertas['estoque_baixo'] . ' peça(s) no estoque mínimo', './produtos?tipo=PECA&baixo=1', 'warning'];
?>
<?php if ($avisos): ?>
  <div class="avisos-painel">
    <?php foreach ($avisos as [$icone, $texto, $href, $cor]): ?>
      <a class="aviso-chip aviso-chip--<?= $cor ?>" href="<?= e($href) ?>"><i class="bi <?= $icone ?>" aria-hidden="true"></i> <?= e($texto) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="painel-grade">
<div class="card card--tabela">
  <div class="card__header"><span class="card__title">Últimas ordens de serviço</span><a class="form-dica" href="./ordens">Ver todas</a></div>
  <?php if (!$ultimas): ?>
    <div class="vazio"><i class="bi bi-clipboard"></i>Nenhuma ordem de serviço ainda.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>OS</th><th>Cliente</th><th>Placa</th><th>Situação</th><th style="text-align:right">Total</th></tr></thead>
        <tbody>
          <?php foreach ($ultimas as $os): [$rotuloStatus, $corStatus] = $nomeStatus[$os['status']] ?? [$os['status'], 'neutral']; ?>
            <tr data-href="./os?id=<?= (int) $os['id'] ?>">
              <td><a href="./os?id=<?= (int) $os['id'] ?>"><?= os_numero($os['numero']) ?></a></td>
              <td><?= e($os['cliente']) ?></td>
              <td><?= e($os['placa']) ?></td>
              <td><span class="badge badge--<?= e($corStatus) ?>"><?= e($rotuloStatus) ?></span></td>
              <td style="text-align:right"><?= dinheiro($os['valor_total']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div>
  <div class="card">
    <h2 class="card__titulo">Próximos agendamentos <a href="./agenda">Agenda</a></h2>
    <?php if (!$agenda): ?><p class="form-dica">Nada marcado. <a href="./agenda-form">Agendar</a></p><?php endif; ?>
    <?php foreach ($agenda as $a): $dia = substr($a['data_hora'], 0, 10); ?>
      <div class="linha-os"><div class="linha-os__topo">
        <span><strong><?= $dia === date('Y-m-d') ? 'Hoje' : data_br($dia) ?>, <?= substr($a['data_hora'], 11, 5) ?></strong> · <?= e($a['cliente']) ?><?= $a['placa'] ? ' · ' . e($a['placa']) : '' ?></span>
        <?php if ($a['status'] === 'CONFIRMADO'): ?><span class="badge badge--success">Confirmado</span><?php endif; ?></div>
        <div class="form-dica"><?= e($a['servico']) ?><?= $a['tecnico'] ? ' · ' . e($a['tecnico']) : '' ?></div></div>
    <?php endforeach; ?>
  </div>
  <div class="card">
    <h2 class="card__titulo">Caixa <a href="./caixa"><?= $alertas['caixa'] ? 'Abrir tela' : 'Abrir caixa' ?></a></h2>
    <?php if ($alertas['caixa']): ?>
      <p><span class="badge badge--success">Aberto</span> <span class="form-dica">desde <?= data_br($alertas['caixa']['aberto_em'], true) ?><?= $alertas['caixa']['aberto_por_nome'] ? ' · ' . e($alertas['caixa']['aberto_por_nome']) : '' ?></span></p>
    <?php else: ?>
      <p><span class="badge badge--neutral">Fechado</span> <span class="form-dica">recebimento em dinheiro exige caixa aberto</span></p>
    <?php endif; ?>
  </div>
</div>
</div>
