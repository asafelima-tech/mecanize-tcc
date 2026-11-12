<?php
/**
 * Kanban das OS em aberto: uma coluna por situação, na ordem do fluxo. É só para enxergar o
 * andamento: a troca de situação é feita na tela da OS, que confere estoque, aprovação e comissão.
 * Espera: $colunas ([situação => [OS...]], de OrdemServico::kanban()).
 */
$noKanban = array_sum(array_map('count', $colunas));
?>
<?php if ($noKanban >= OrdemServico::KANBAN_LIMITE): ?>
  <p class="kanban__aviso">Mostrando as <?= OrdemServico::KANBAN_LIMITE ?> OS mais recentes. Use a busca ou o filtro de técnico para ver as outras.</p>
<?php endif; ?>
<div class="kanban" role="list">
  <?php foreach ($colunas as $status => $cartoes): [$rotulo, $cor] = os_status($status); ?>
    <section class="kanban__coluna" role="listitem" aria-label="<?= e($rotulo) ?>: <?= count($cartoes) ?>">
      <header class="kanban__topo">
        <span class="badge badge--<?= $cor ?>"><?= e($rotulo) ?></span>
        <span class="kanban__n"><?= count($cartoes) ?></span>
      </header>
      <?php if (!$cartoes): ?>
        <p class="kanban__vazio">Nenhuma OS</p>
      <?php endif; ?>
      <?php foreach ($cartoes as $o): ?>
        <a class="kanban__cartao" href="./os?id=<?= (int) $o['id'] ?>">
          <span class="kanban__linha">
            <strong class="tabular"><?= os_numero($o['numero']) ?></strong>
            <?php if (OrdemServico::atrasada($o)): ?><span class="badge badge--danger">Atrasada</span><?php endif; ?>
          </span>
          <span class="kanban__cliente"><?= e($o['cliente']) ?></span>
          <span class="kanban__veiculo"><?= e($o['modelo'] ?: 'Veículo') ?> · <span class="placa"><?= e($o['placa']) ?></span></span>
          <span class="kanban__linha kanban__rodape">
            <span><?= icone('mechanic', 14) ?> <?= e($o['tecnico'] ?? 'Sem técnico') ?></span>
            <strong class="tabular"><?= dinheiro($o['valor_total']) ?></strong>
          </span>
        </a>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>
</div>
