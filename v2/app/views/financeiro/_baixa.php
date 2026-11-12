<?php
/**
 * Diálogo de pagamento/recebimento de uma conta. Espera: $dlgId, $acao (URL do POST), $extras (campos ocultos),
 * $tipo ('RECEBER'|'PAGAR'), $caixa (aberto ou null). O valor e o saldo são preenchidos pelo botão que abre (data-*).
 * Na lista de contas, o botão de cada linha também traz data-acao (a conta) e data-descricao, e o diálogo
 * volta para a lista ($voltarLista).
 */
?>
<dialog id="<?= e($dlgId) ?>" class="dlg">
  <form method="post" action="<?= e($acao) ?>">
    <?= csrf_field() ?>
    <?php foreach ($extras as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
    <input type="hidden" name="conta_id">
    <?php if (!empty($voltarLista)): ?><input type="hidden" name="voltar_lista" value="<?= e($voltarLista) ?>"><?php endif; ?>
    <input type="hidden" name="chave" value="<?= bin2hex(random_bytes(16)) ?>">
    <h3><?= $tipo === 'PAGAR' ? 'Registrar pagamento' : 'Registrar recebimento' ?></h3>
    <p class="dlg__conta" data-baixa-conta hidden></p>
    <p class="form-dica" data-baixa-rotulo></p>
    <div class="form-row-2">
      <div class="form-field"><label>Valor <?= $tipo === 'PAGAR' ? 'pago' : 'recebido' ?> (R$)</label><input name="valor" inputmode="decimal" required></div>
      <div class="form-field"><label>Forma</label>
        <select name="forma" required><?php foreach (Lancamento::FORMAS as $k => $r): ?><option value="<?= $k ?>"><?= $r ?></option><?php endforeach; ?></select></div>
      <div class="form-field"><label>Juros / multa (R$)</label><input name="acrescimo" inputmode="decimal" placeholder="0,00"></div>
      <div class="form-field"><label>Desconto (R$)</label><input name="desconto" inputmode="decimal" placeholder="0,00"></div>
      <div class="form-field"><label>Data</label><input type="date" name="data" value="<?= date('Y-m-d') ?>"></div>
      <div class="form-field"><label>Taxa da maquininha (R$)</label><input name="taxa" inputmode="decimal" placeholder="0,00"></div>
    </div>
    <div class="form-field"><label>Observação</label><input name="observacao" maxlength="200"></div>
    <p class="form-dica" style="margin-top:8px"><?= $caixa ? 'Vai entrar no caixa aberto.' : '<strong>Não há caixa aberto:</strong> pagamento em dinheiro não será aceito.' ?> Valor menor que o saldo fica como pagamento parcial.</p>
    <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Cancelar</button><button class="btn btn--primary">Confirmar</button></div>
  </form>
</dialog>
<script>
  document.querySelectorAll('[data-baixar]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = document.getElementById(b.dataset.baixar), f = d.querySelector('form');
      if (b.dataset.acao) f.action = b.dataset.acao;
      f.conta_id.value = b.dataset.conta || '';
      var conta = d.querySelector('[data-baixa-conta]');
      if (conta) { conta.textContent = b.dataset.descricao || ''; conta.hidden = !b.dataset.descricao; }
      f.valor.value = b.dataset.saldo || '';
      if (b.dataset.forma) f.forma.value = b.dataset.forma;
      d.querySelector('[data-baixa-rotulo]').textContent = b.dataset.rotulo || '';
      d.showModal();
    });
  });
  document.querySelectorAll('dialog [data-fechar]').forEach(function (b) { b.onclick = function () { b.closest('dialog').close(); }; });
</script>
