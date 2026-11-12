<?php /** Espera: $pendentes, $tecnico, $itens */ $titulo = 'Comissões'; ?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Comissões</h1>
    <p class="page-header__subtitle"><a href="./financeiro?tipo=PAGAR">Financeiro</a> · comissões de OS concluídas ainda não lançadas para pagamento</p>
  </div>
</div>
<div class="ficha">
  <div class="card card--tabela">
    <?php if (!$pendentes): ?><div class="vazio"><?= icone('percent', 32, 1.25) ?>Nenhuma comissão pendente.</div><?php else: ?>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Técnico</th><th class="num">OS</th><th>Desde</th><th class="num">A pagar</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($pendentes as $t): ?>
            <tr data-href="./comissoes?tecnico=<?= (int) $t['id'] ?>" class="<?= $tecnico === (int) $t['id'] ? 'selecionada' : '' ?>">
              <td><a href="./comissoes?tecnico=<?= (int) $t['id'] ?>"><?= e($t['nome']) ?></a></td><td class="num"><?= (int) $t['ordens'] ?></td><td><?= data_br($t['desde']) ?></td>
              <td class="num"><strong><?= dinheiro($t['total']) ?></strong></td>
              <td class="num">
                <form method="post" action="./comissao-gerar" style="display:inline-flex;gap:6px;align-items:center">
                  <?= csrf_field() ?><input type="hidden" name="tecnico_id" value="<?= (int) $t['id'] ?>">
                  <input type="date" name="vencimento" value="<?= date('Y-m-d') ?>" class="filtros__data" aria-label="Vencimento">
                  <button class="btn btn--primary btn--sm">Lançar pagamento</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
  <div class="card">
    <h2 class="card__titulo">Detalhe</h2>
    <?php if (!$itens): ?><p class="form-dica">Escolha um técnico para ver de onde vem a comissão.</p><?php endif; ?>
    <?php foreach ($itens as $i): ?>
      <div class="linha-os"><div class="linha-os__topo"><a href="./os?id=<?= (int) $i['os_id'] ?>"><?= os_numero($i['numero']) ?></a><strong><?= dinheiro($i['comissao_valor']) ?></strong></div>
        <div class="form-dica"><?= e($i['descricao']) ?> · <?= rtrim(rtrim((string) $i['comissao_percentual'], '0'), '.') ?>% de <?= dinheiro($i['valor_total']) ?></div></div>
    <?php endforeach; ?>
  </div>
</div>
