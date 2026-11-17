<?php
/**
 * Consulta de uma sessão de caixa, no formato das outras fichas: identificação no topo (situação, quem abriu e
 * fechou), o fechamento calculado (saldo anterior, entradas, saídas e saldo final), os movimentos e, ao lado,
 * o mesmo fechamento por forma de pagamento e as observações.
 * Espera: $caixa, $fechamento, $movimentos
 */
$titulo = 'Caixa de ' . data_br($caixa['aberto_em']);
$fechado = $caixa['status'] === 'FECHADO';
// Sessões antigas eram fechadas por contagem da gaveta; se a contagem diferiu do calculado, fica registrado.
$dif = $fechado && $caixa['valor_esperado'] !== null ? (float) $caixa['valor_fechamento'] - (float) $caixa['valor_esperado'] : 0.0;
?>
<header class="ver-topo">
  <div class="ver-topo__id">
    <span class="ver-topo__avatar ver-topo__avatar--quadrado" aria-hidden="true"><?= icone('cash', 30, 1.5) ?></span>
    <div class="ver-topo__texto">
      <p class="ver-topo__voltar"><a href="./caixas"><?= icone('chevron-left', 14, 2) ?> Sessões de caixa</a></p>
      <h1>Caixa de <?= data_br($caixa['aberto_em']) ?></h1>
      <p class="ver-topo__meta">
        <span class="ver-chip<?= $fechado ? '' : ' ver-chip--escuro' ?>"><?= $fechado ? 'Fechado' : 'Aberto' ?></span>
        <span class="ver-chip"><?= icone('clock', 13) ?> Aberto por <?= e($caixa['aberto_por_nome'] ?? '—') ?> às <?= date('H:i', strtotime($caixa['aberto_em'])) ?></span>
        <?php if ($fechado): ?><span class="ver-chip"><?= icone('lock', 13) ?> Fechado por <?= e($caixa['fechado_por_nome'] ?? '—') ?> em <?= data_br($caixa['fechado_em'], true) ?></span><?php endif; ?>
      </p>
    </div>
  </div>
  <?php if (!$fechado): ?><div class="ver-topo__acoes"><a class="btn btn--primary btn--sm" href="./caixa"><?= icone('cash') ?> Ir para o caixa atual</a></div><?php endif; ?>
</header>

<div class="ver-numeros" aria-label="Fechamento da sessão">
  <div><span>Saldo anterior</span><strong><?= dinheiro($fechamento['anterior']) ?></strong><small>em dinheiro, do caixa anterior</small></div>
  <div><span>Entradas</span><strong><?= dinheiro($fechamento['entradas']) ?></strong><small>todas as formas</small></div>
  <div><span>Saídas</span><strong><?= dinheiro($fechamento['saidas']) ?></strong><small>todas as formas</small></div>
  <div><span>Saldo final<?= $fechado ? '' : ' até agora' ?></span><strong><?= ($fechamento['saldo'] < 0 ? '− ' : '') . dinheiro(abs($fechamento['saldo'])) ?></strong>
    <small><?= dinheiro($fechamento['dinheiro']) ?> em dinheiro na gaveta</small></div>
</div>

<div class="ver-grade">
  <div class="ver-principal">
    <section class="card card--tabela ver-historico" aria-labelledby="ver-movs">
      <header class="ver-historico__cabeca">
        <div><h2 id="ver-movs">Movimentos</h2><p><?= $movimentos ? count($movimentos) . ' movimento(s), do mais recente para o mais antigo.' : 'Recebimentos, pagamentos, suprimentos e sangrias da sessão.' ?></p></div>
      </header>
      <?php require __DIR__ . '/_movimentos.php'; ?>
    </section>
  </div>

  <aside class="card ver-lado" aria-labelledby="ver-formas">
    <header class="ver-lado__cabeca"><h2 id="ver-formas">Fechamento</h2></header>
    <?php $fechamentoTotais = false; $fechamentoAberto = !$fechado; require __DIR__ . '/_fechamento.php'; ?>
    <?php if (abs($dif) >= 0.005): ?>
      <p class="caixa-conferencia">
        <span class="badge badge--<?= $dif > 0 ? 'neutral' : 'danger' ?>"><?= $dif > 0 ? 'Sobra' : 'Falta' ?> de <?= dinheiro(abs($dif)) ?></span>
        <span>na contagem da gaveta feita no fechamento</span>
      </p>
    <?php endif; ?>
    <?php foreach (['observacao_abertura' => 'Na abertura', 'observacao_fechamento' => 'No fechamento'] as $k => $r): if ($caixa[$k]): ?>
      <div class="ver-lado__bloco">
        <h3 class="ver-grupo__titulo"><?= icone('file', 15) ?> Observação · <?= $r ?></h3>
        <p class="ver-lado__texto"><?= e($caixa[$k]) ?></p>
      </div>
    <?php endif; endforeach; ?>
  </aside>
</div>
