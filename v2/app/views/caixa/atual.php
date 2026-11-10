<?php /** Espera: $caixa, $resumo, $movimentos, $sugestao */ $titulo = 'Caixa'; ?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Caixa <?= $caixa ? '<span class="badge badge--success">Aberto</span>' : '<span class="badge badge--neutral">Fechado</span>' ?></h1>
    <p class="page-header__subtitle"><?= $caixa ? 'Aberto por ' . e($caixa['aberto_por_nome']) . ' em ' . data_br($caixa['aberto_em'], true) : 'Abra o caixa para receber e pagar em dinheiro.' ?></p>
  </div>
  <div class="page-header__actions acoes">
    <?php if (Contexto::perfil() === 'ADMIN'): ?><a class="btn btn--ghost" href="./caixas"><i class="bi bi-clock-history"></i> Sessões anteriores</a><?php endif; ?>
    <?php if ($caixa): ?>
      <button type="button" class="btn btn--ghost" data-abrir="dlg-mov" data-natureza="SUPRIMENTO">Suprimento</button>
      <button type="button" class="btn btn--ghost" data-abrir="dlg-mov" data-natureza="SANGRIA">Sangria</button>
      <button type="button" class="btn btn--primary" data-abrir="dlg-fechar"><i class="bi bi-lock"></i> Fechar caixa</button>
    <?php endif; ?>
  </div>
</div>

<?php if (!$caixa): ?>
  <form class="card form-card" method="post" action="./caixa-abrir" style="max-width:520px">
    <?= csrf_field() ?>
    <h2 class="card__titulo">Abrir caixa</h2>
    <div class="form-field"><label for="valor">Dinheiro na gaveta agora (R$)</label><input id="valor" name="valor" inputmode="decimal" value="<?= $sugestao !== null ? e(number_format($sugestao, 2, ',', '.')) : '' ?>" required>
      <?php if ($sugestao !== null): ?><span class="form-dica">Sugestão: o valor contado no último fechamento.</span><?php endif; ?></div>
    <div class="form-field" style="margin-top:12px"><label for="observacao">Observação</label><input id="observacao" name="observacao" maxlength="255"></div>
    <div class="form-acoes"><button class="btn btn--primary">Abrir caixa</button></div>
  </form>
<?php else: ?>
  <div class="ficha">
    <div class="card card--tabela">
      <div class="card__header"><span class="card__title">Movimentos da sessão</span></div>
      <?php require __DIR__ . '/_movimentos.php'; ?>
    </div>
    <div class="card"><?php require __DIR__ . '/_resumo.php'; ?></div>
  </div>

  <dialog id="dlg-mov" class="dlg">
    <form method="post" action="./caixa-movimento">
      <?= csrf_field() ?><input type="hidden" name="natureza"><input type="hidden" name="chave" value="<?= bin2hex(random_bytes(16)) ?>">
      <h3 data-titulo></h3>
      <div class="form-field"><label>Valor (R$)</label><input name="valor" inputmode="decimal" required></div>
      <div class="form-field"><label>Motivo</label><input name="descricao" maxlength="200" required placeholder="Ex.: troco, depósito no banco"></div>
      <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Cancelar</button><button class="btn btn--primary">Registrar</button></div>
    </form>
  </dialog>
  <dialog id="dlg-fechar" class="dlg">
    <form method="post" action="./caixa-fechar">
      <?= csrf_field() ?>
      <h3>Fechar caixa</h3>
      <p class="form-dica">Conte o dinheiro da gaveta e informe o valor. O sistema compara com o esperado e registra sobra ou falta.</p>
      <div class="form-field"><label>Dinheiro contado (R$)</label><input name="contado" inputmode="decimal" required></div>
      <div class="form-field"><label>Observação</label><input name="observacao" maxlength="255"></div>
      <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Cancelar</button><button class="btn btn--primary">Fechar caixa</button></div>
    </form>
  </dialog>
  <script>
    document.querySelectorAll('[data-abrir]').forEach(function (b) {
      b.addEventListener('click', function () {
        var d = document.getElementById(b.dataset.abrir);
        if (b.dataset.natureza) { d.querySelector('[name=natureza]').value = b.dataset.natureza; d.querySelector('[data-titulo]').textContent = b.dataset.natureza === 'SANGRIA' ? 'Sangria (retirada de dinheiro)' : 'Suprimento (entrada de troco)'; }
        d.showModal();
      });
    });
    document.querySelectorAll('dialog [data-fechar]').forEach(function (b) { b.onclick = function () { b.closest('dialog').close(); }; });
  </script>
<?php endif; ?>
