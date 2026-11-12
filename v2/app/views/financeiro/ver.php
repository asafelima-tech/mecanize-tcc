<?php
/** Espera: $l, $parcelas, $movimentos, $historico, $caixa */
$receber = $l['tipo'] === 'RECEBER';
$titulo = $l['descricao'];
$saldo = round((float) $l['devido'] - (float) $l['valor_pago'], 2);
$cores = ['ABERTO' => 'neutral', 'PARCIAL' => 'info', 'PAGO' => 'success', 'CANCELADO' => 'muted'];
$nomes = ['ABERTO' => 'Em aberto', 'PARCIAL' => 'Parcial', 'PAGO' => $receber ? 'Recebido' : 'Pago', 'CANCELADO' => 'Cancelado'];
$aberta = in_array($l['status'], ['ABERTO', 'PARCIAL'], true);
$acoesHist = ['CRIACAO' => 'Lançada', 'ALTERACAO' => 'Alterada', 'PAGAMENTO' => $receber ? 'Recebimento' : 'Pagamento', 'ESTORNO' => 'Estorno', 'CANCELAMENTO' => 'Cancelada', 'ANEXO' => 'Comprovante anexado'];
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= e($l['descricao']) ?> <span class="badge badge--<?= $cores[$l['status']] ?>"><?= $nomes[$l['status']] ?></span></h1>
    <p class="page-header__subtitle"><a href="./financeiro?tipo=<?= e($l['tipo']) ?>"><?= $receber ? 'Contas a receber' : 'Contas a pagar' ?></a> · <?= e(Lancamento::categoria($l['tipo'], $l['categoria'])) ?>
      <?= $l['parcela_total'] > 1 ? ' · parcela ' . (int) $l['parcela_numero'] . ' de ' . (int) $l['parcela_total'] : '' ?></p>
  </div>
  <div class="page-header__actions acoes">
    <?php if ($l['status'] === 'ABERTO' && (float) $l['valor_pago'] == 0): ?><button type="button" class="btn btn--danger-ghost btn--sm" data-abrir="dlg-cancelar">Cancelar conta</button><?php endif; ?>
    <?php if ($aberta): ?>
      <button type="button" class="btn btn--ghost btn--sm" data-abrir="dlg-editar">Editar</button>
      <button type="button" class="btn btn--primary btn--sm" data-baixar="dlg-baixa" data-conta="<?= (int) $l['id'] ?>" data-saldo="<?= e(number_format($saldo, 2, ',', '.')) ?>" data-forma="<?= e($l['forma_prevista'] ?? '') ?>" data-rotulo="Saldo: <?= e(dinheiro($saldo)) ?>">
        <?= icone('cash') ?> <?= $receber ? 'Receber' : 'Pagar' ?></button>
    <?php endif; ?>
  </div>
</div>

<?php if ($l['status'] === 'CANCELADO'): ?>
  <?php $alertaTipo = 'aviso'; $alertaTitulo = 'Cancelada em ' . data_br($l['cancelado_em'], true); $alertaMensagem = $l['motivo_cancelamento'] ?? ''; require V2_RAIZ . '/app/views/layout/_alerta.php'; ?>
<?php endif; ?>

<div class="ficha">
  <div>
    <div class="card">
      <dl class="dados">
        <div><dt>Vencimento</dt><dd><?= data_br($l['vencimento']) ?></dd></div>
        <div><dt>Valor</dt><dd><?= dinheiro($l['valor']) ?></dd></div>
        <?php if ((float) $l['acrescimo'] > 0): ?><div><dt>Juros / multa</dt><dd>+<?= dinheiro($l['acrescimo']) ?></dd></div><?php endif; ?>
        <?php if ((float) $l['desconto'] > 0): ?><div><dt>Desconto</dt><dd>−<?= dinheiro($l['desconto']) ?></dd></div><?php endif; ?>
        <div><dt><?= $receber ? 'Recebido' : 'Pago' ?></dt><dd><?= dinheiro($l['valor_pago']) ?></dd></div>
        <div><dt>Saldo</dt><dd><strong><?= $l['status'] === 'CANCELADO' ? '—' : dinheiro($saldo) ?></strong></dd></div>
        <?php if ($l['cliente'] || $l['fornecedor'] || $l['funcionario']): ?><div><dt><?= $receber ? 'Cliente' : 'Favorecido' ?></dt><dd><?= e($l['cliente'] ?? $l['fornecedor'] ?? $l['funcionario']) ?></dd></div><?php endif; ?>
        <?php if ($l['os_numero']): ?><div><dt>Ordem de serviço</dt><dd><a href="./os?id=<?= (int) $l['ordem_servico_id'] ?>"><?= os_numero($l['os_numero']) ?></a></dd></div><?php endif; ?>
        <div><dt>Competência</dt><dd><?= date('m/Y', strtotime($l['competencia'])) ?></dd></div>
        <?php if ($l['forma_prevista']): ?><div><dt>Forma prevista</dt><dd><?= e(Lancamento::FORMAS[$l['forma_prevista']] ?? $l['forma_prevista']) ?></dd></div><?php endif; ?>
        <?php if ($l['documento']): ?><div><dt>Documento</dt><dd><?= e($l['documento']) ?></dd></div><?php endif; ?>
      </dl>
      <?php if ($l['observacao']): ?><p class="form-dica" style="margin-top:12px"><?= e($l['observacao']) ?></p><?php endif; ?>
    </div>

    <div class="card card--tabela">
      <div class="card__header"><span class="card__title"><?= $receber ? 'Recebimentos' : 'Pagamentos' ?></span></div>
      <?php if (!$movimentos): ?><div class="vazio">Nenhum <?= $receber ? 'recebimento' : 'pagamento' ?> ainda.</div><?php else: ?>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Data</th><th>Forma</th><th>Por</th><th class="num">Valor</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($movimentos as $m): $estorno = $m['natureza'] === 'ESTORNO'; ?>
              <tr class="<?= $m['estornado'] ? 'inativo' : '' ?>">
                <td><?= data_br($m['data_movimento'], true) ?><?= $estorno ? '<span class="sub">estorno: ' . e($m['descricao']) . '</span>' : ($m['descricao'] ? '<span class="sub">' . e($m['descricao']) . '</span>' : '') ?></td>
                <td><?= e(Lancamento::FORMAS[$m['forma_pagamento']] ?? $m['forma_pagamento']) ?><?= (float) $m['taxa'] > 0 ? '<span class="sub">taxa ' . dinheiro($m['taxa']) . '</span>' : '' ?></td>
                <td><?= e($m['usuario'] ?? '—') ?></td>
                <td class="num"><?= $estorno ? '−' : '' ?><?= dinheiro($m['valor']) ?></td>
                <td class="num"><?php if (!$estorno && !$m['estornado']): ?><button type="button" class="btn btn--ghost btn--sm" data-estornar="<?= (int) $m['id'] ?>">Estornar</button><?php elseif ($m['estornado']): ?><span class="form-dica">estornado</span><?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <?php if (count($parcelas) > 1): ?>
      <div class="card">
        <h2 class="card__titulo">Parcelas</h2>
        <?php foreach ($parcelas as $pc): ?>
          <div class="linha-os"><div class="linha-os__topo">
            <a href="./conta?id=<?= (int) $pc['id'] ?>"><?= (int) $pc['parcela_numero'] ?>/<?= (int) $pc['parcela_total'] ?> · <?= data_br($pc['vencimento']) ?></a>
            <span><?= dinheiro($pc['devido']) ?> <span class="badge badge--<?= $cores[$pc['status']] ?>"><?= $nomes[$pc['status']] ?></span></span>
          </div></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="card">
      <h2 class="card__titulo">Comprovantes</h2>
      <?php if (!$anexos): ?><p class="form-dica">Nenhum comprovante anexado.</p><?php endif; ?>
      <?php foreach ($anexos as $a): ?>
        <div class="linha-os"><div class="linha-os__topo">
          <a href="./anexo?id=<?= (int) $a['id'] ?>" target="_blank" rel="noopener"><?= icone(Anexo::ehImagem($a) ? 'image' : 'file') ?> <?= e($a['descricao'] ?: $a['nome_original']) ?></a>
          <span class="form-dica"><?= data_br($a['enviado_em']) ?></span></div></div>
      <?php endforeach; ?>
      <?php if (count($anexos) < Anexo::MAX_POR_DONO): ?>
        <form method="post" action="./conta-anexar?conta=<?= (int) $l['id'] ?>" enctype="multipart/form-data" style="margin-top:12px">
          <?= csrf_field() ?>
          <div class="form-field"><input type="file" name="arquivo" required accept="image/jpeg,image/png,image/webp,application/pdf" aria-label="Comprovante"></div>
          <div class="form-field" style="margin-top:8px"><input name="descricao" maxlength="200" placeholder="Descrição (opcional)" aria-label="Descrição"></div>
          <div style="margin-top:8px"><button class="btn btn--ghost btn--sm"><?= icone('attach') ?> Anexar comprovante</button> <span class="form-dica">PDF ou imagem, até 5 MB</span></div>
        </form>
      <?php endif; ?>
    </div>
    <div class="card">
      <h2 class="card__titulo">Histórico</h2>
      <?php foreach ($historico as $h): ?>
        <div class="linha-os"><div class="linha-os__topo"><strong><?= e($acoesHist[$h['acao']] ?? $h['acao']) ?></strong><span class="form-dica"><?= data_br($h['criado_em'], true) ?></span></div>
          <div class="form-dica"><?= e($h['autor'] ?? '') ?><?= $h['motivo'] ? ' · ' . e($h['motivo']) : '' ?>
            <?php if ($h['dados'] && ($d = json_decode($h['dados'], true)) && isset($d['valor'])): ?> · <?= dinheiro($d['valor']) ?><?php endif; ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php $dlgId = 'dlg-baixa'; $acao = './conta-baixar?id=' . (int) $l['id']; $extras = []; $tipo = $l['tipo']; require __DIR__ . '/_baixa.php'; ?>

<dialog id="dlg-estornar" class="dlg">
  <form method="post" action="./conta-estornar">
    <?= csrf_field() ?><input type="hidden" name="conta_id" value="<?= (int) $l['id'] ?>">
    <h3>Estornar <?= $receber ? 'recebimento' : 'pagamento' ?></h3>
    <p class="form-dica">O registro original continua; o estorno lança o valor contrário e a conta volta a ficar em aberto. Em dinheiro, o valor sai/volta pelo caixa aberto.</p>
    <div class="form-field"><label>Motivo</label><textarea name="motivo" rows="2" required></textarea></div>
    <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Voltar</button><button class="btn btn--danger">Estornar</button></div>
  </form>
</dialog>

<dialog id="dlg-cancelar" class="dlg">
  <form method="post" action="./conta-cancelar?id=<?= (int) $l['id'] ?>">
    <?= csrf_field() ?>
    <h3>Cancelar conta</h3>
    <div class="form-field"><label>Motivo</label><textarea name="motivo" rows="2" required></textarea></div>
    <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Voltar</button><button class="btn btn--danger">Cancelar conta</button></div>
  </form>
</dialog>

<dialog id="dlg-editar" class="dlg">
  <form method="post" action="./conta-editar?id=<?= (int) $l['id'] ?>">
    <?= csrf_field() ?>
    <h3>Editar conta</h3>
    <div class="form-field"><label>Descrição</label><input name="descricao" value="<?= e($l['descricao']) ?>" maxlength="200"></div>
    <div class="form-row-2">
      <div class="form-field"><label>Categoria</label><select name="categoria"><?php foreach (Lancamento::CATEGORIAS[$l['tipo']] as $k => $r): ?><option value="<?= $k ?>" <?= $l['categoria'] === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></div>
      <div class="form-field"><label>Vencimento</label><input type="date" name="vencimento" value="<?= e($l['vencimento']) ?>"></div>
      <?php if ($l['status'] === 'ABERTO' && !$l['ordem_servico_id']): ?><div class="form-field"><label>Valor (R$)</label><input name="valor" value="<?= e(number_format((float) $l['valor'], 2, ',', '.')) ?>" inputmode="decimal"></div><?php endif; ?>
      <div class="form-field"><label>Documento</label><input name="documento" value="<?= e($l['documento']) ?>" maxlength="60"></div>
    </div>
    <div class="form-field"><label>Observação</label><input name="observacao" value="<?= e($l['observacao']) ?>" maxlength="255"></div>
    <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Cancelar</button><button class="btn btn--primary">Salvar</button></div>
  </form>
</dialog>
<script>
  document.querySelectorAll('[data-abrir]').forEach(function (b) { b.addEventListener('click', function () { document.getElementById(b.dataset.abrir).showModal(); }); });
  document.querySelectorAll('[data-estornar]').forEach(function (b) {
    b.addEventListener('click', function () { var d = document.getElementById('dlg-estornar'); d.querySelector('form').action = './conta-estornar?id=' + b.dataset.estornar; d.showModal(); });
  });
  document.querySelectorAll('dialog [data-fechar]').forEach(function (b) { b.onclick = function () { b.closest('dialog').close(); }; });
</script>
