<?php
/** Espera: $contas, $saldo, $f, $p */
$receber = $f['tipo'] === 'RECEBER';
$titulo = $receber ? 'Contas a receber' : 'Contas a pagar';
$cores = ['ABERTO' => 'neutral', 'PARCIAL' => 'info', 'PAGO' => 'success', 'CANCELADO' => 'muted'];
$nomes = ['ABERTO' => 'Em aberto', 'PARCIAL' => 'Parcial', 'PAGO' => $receber ? 'Recebido' : 'Pago', 'CANCELADO' => 'Cancelado'];
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Financeiro</h1>
    <p class="page-header__subtitle"><?= e(Lancamento::SITUACOES[$f['situacao']]) ?>: <?= dinheiro($saldo) ?> <?= $receber ? 'a receber' : 'a pagar' ?></p>
  </div>
  <div class="page-header__actions">
    <a class="btn btn--contorno" href="./caixas"><?= icone('history') ?> Histórico de caixa</a>
    <a class="btn btn--ghost" href="./comissoes"><?= icone('percent') ?> Comissões</a>
    <a class="btn btn--primary" href="./conta-form?tipo=<?= e($f['tipo']) ?>"><?= icone('plus') ?> <?= $receber ? 'Conta a receber' : 'Conta a pagar' ?></a>
  </div>
</div>

<nav class="abas abas--contagem" aria-label="Financeiro">
  <a href="./financeiro">Visão geral</a>
  <a href="./financeiro?tipo=RECEBER"<?= $receber ? ' class="ativa" aria-current="page"' : '' ?>>A receber</a>
  <a href="./financeiro?tipo=PAGAR"<?= $receber ? '' : ' class="ativa" aria-current="page"' ?>>A pagar</a>
</nav>

<div class="card card--tabela">
  <form class="filtros" method="get" action="./financeiro">
    <input type="hidden" name="tipo" value="<?= e($f['tipo']) ?>">
    <div class="filtros__busca"><?= icone('search') ?><input type="search" name="busca" value="<?= e($f['busca']) ?>" placeholder="Descrição, <?= $receber ? 'cliente' : 'fornecedor' ?> ou documento" aria-label="Buscar"></div>
    <select name="situacao" aria-label="Situação"><?php foreach (Lancamento::SITUACOES as $k => $r): ?><option value="<?= $k ?>" <?= $f['situacao'] === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select>
    <input type="date" name="de" value="<?= e($f['de']) ?>" class="filtros__data" aria-label="Vencimento de" onchange="this.form.submit()">
    <input type="date" name="ate" value="<?= e($f['ate']) ?>" class="filtros__data" aria-label="Vencimento até" onchange="this.form.submit()">
  </form>
  <?php if (!$contas): ?>
    <div class="vazio"><?= icone('wallet', 32, 1.25) ?>Nenhuma conta nesta situação.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Vencimento</th><th>Descrição</th><th><?= $receber ? 'Cliente' : 'Favorecido' ?></th><th>Situação</th><th class="num">Valor</th><th class="num">Saldo</th></tr></thead>
        <tbody>
          <?php foreach ($contas as $c): $saldoConta = (float) $c['devido'] - (float) $c['valor_pago']; $vencida = in_array($c['status'], ['ABERTO', 'PARCIAL'], true) && $c['vencimento'] < date('Y-m-d'); ?>
            <tr data-href="./conta?id=<?= (int) $c['id'] ?>" class="<?= $c['status'] === 'CANCELADO' ? 'inativo' : '' ?>">
              <td><?= data_br($c['vencimento']) ?><?= $vencida ? '<span class="sub"><strong>Vencida</strong></span>' : '' ?></td>
              <td><a href="./conta?id=<?= (int) $c['id'] ?>"><?= e($c['descricao']) ?></a>
                <span class="sub"><?= e(Lancamento::categoria($c['tipo'], $c['categoria'])) ?><?= $c['parcela_total'] > 1 ? ' · parcela ' . (int) $c['parcela_numero'] . '/' . (int) $c['parcela_total'] : '' ?></span></td>
              <td><?= e($c['cliente'] ?? $c['fornecedor'] ?? $c['funcionario'] ?? '—') ?></td>
              <td><span class="badge badge--<?= $cores[$c['status']] ?>"><?= $nomes[$c['status']] ?></span></td>
              <td class="num"><?= dinheiro($c['devido']) ?></td>
              <td class="num"><?= $c['status'] === 'CANCELADO' ? '—' : dinheiro($saldoConta) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?php $rotuloItens = 'contas'; require V2_RAIZ . '/app/views/layout/_rodape.php'; ?>
</div>
