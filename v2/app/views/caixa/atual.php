<?php
/**
 * Caixa (Design System, tela 15): situação da sessão, dinheiro esperado na gaveta, entradas e saídas
 * da sessão, últimos lançamentos e o histórico de sessões (ADMIN).
 * Espera: $caixa, $resumo, $movimentos, $sugestao, $sessoes
 */
$titulo = 'Caixa';
$admin = Contexto::perfil() === 'ADMIN';
$iconeForma = ['DINHEIRO' => 'cash', 'PIX' => 'qr-code', 'CARTAO_DEBITO' => 'credit-card', 'CARTAO_CREDITO' => 'credit-card',
               'BOLETO' => 'barcode', 'TRANSFERENCIA' => 'bank', 'OUTRO' => 'more-h'];

if ($caixa) {
    // Entradas por forma e saídas por destino, somadas dos movimentos da sessão.
    $entradas = $saidas = 0.0;
    $nEntradas = $nSaidas = 0;
    $porForma = [];
    $porDestino = [];
    foreach ($movimentos as $m) {
        $v = (float) $m['valor'];
        if ($m['direcao'] === 'ENTRADA') {
            $entradas += $v;
            $nEntradas++;
            $porForma[$m['forma_pagamento']] = ($porForma[$m['forma_pagamento']] ?? 0) + $v;
        } else {
            $saidas += $v;
            $nSaidas++;
            $destino = match (true) {
                $m['natureza'] === 'SANGRIA' => ['Retirada (sangria)', 'bank'],
                $m['natureza'] === 'ESTORNO' => ['Estornos', 'refresh'],
                default => [Lancamento::CATEGORIAS['PAGAR'][$m['lancamento_categoria']] ?? 'Pagamentos', $m['lancamento_categoria'] === 'PECAS' ? 'part' : 'receipt'],
            };
            $porDestino[$destino[0]] = [$destino[1], ($porDestino[$destino[0]][1] ?? 0) + $v];
        }
    }
    arsort($porForma);
    uasort($porDestino, fn ($a, $b) => $b[1] <=> $a[1]);
    $aberturaHoje = substr($caixa['aberto_em'], 0, 10) === date('Y-m-d');
    $horaAbertura = date('H:i', strtotime($caixa['aberto_em']));
}
$descricaoMov = function (array $m): array {
    $rotulo = ['BAIXA' => $m['lancamento_tipo'] === 'PAGAR' ? 'Pagamento' : 'Recebimento', 'ESTORNO' => 'Estorno', 'SUPRIMENTO' => 'Suprimento', 'SANGRIA' => 'Sangria'][$m['natureza']];
    $titulo = $m['lancamento'] ? $m['lancamento'] . ($m['cliente'] ? ' · ' . $m['cliente'] : '') : ($m['descricao'] ?: $rotulo);

    return [$titulo, ($m['direcao'] === 'ENTRADA' ? 'Entrada' : 'Saída') . ' · ' . (Lancamento::FORMAS[$m['forma_pagamento']] ?? $m['forma_pagamento']) . ' · ' . $rotulo];
};
$linkMov = fn (array $m): ?string => $m['lancamento_id'] ? './' . ($admin ? 'conta?id=' . (int) $m['lancamento_id'] : 'os?id=' . (int) $m['ordem_servico_id']) : null;
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Caixa</h1>
    <p class="page-header__subtitle"><?= $caixa
        ? 'Sessão aberta por ' . e($caixa['aberto_por_nome']) . ($aberturaHoje ? ' hoje às ' . $horaAbertura : ' em ' . data_br($caixa['aberto_em'], true)) . '.'
        : 'Abra o caixa para receber e pagar em dinheiro.' ?></p>
  </div>
  <div class="page-header__actions">
    <?php if ($admin): ?><a class="btn btn--contorno" href="./relatorios"><?= icone('chart') ?> Relatório de caixa</a><?php endif; ?>
    <?php if ($caixa): ?>
      <button type="button" class="btn btn--ghost" data-abrir="dlg-mov" data-natureza="SUPRIMENTO"><?= icone('download') ?> Suprimento</button>
      <button type="button" class="btn btn--ghost" data-abrir="dlg-mov" data-natureza="SANGRIA"><?= icone('upload') ?> Sangria</button>
      <button type="button" class="btn btn--primary" data-abrir="dlg-fechar"><?= icone('lock') ?> Fechar caixa</button>
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
  <div class="stat-grid stat-grid--icone">
    <div class="stat-card stat-card--destaque">
      <div class="stat-card__topo"><span class="stat-card__label">Status</span></div>
      <span class="stat-card__value stat-card__value--status"><span class="ponto-status" aria-hidden="true"></span>Aberto</span>
      <span class="stat-card__pe">desde <?= $aberturaHoje ? $horaAbertura : data_br($caixa['aberto_em'], true) ?> · troco <?= dinheiro($caixa['valor_abertura']) ?></span>
    </div>
    <div class="stat-card">
      <div class="stat-card__topo"><span class="stat-card__label">Caixa atual (dinheiro)</span><?= icone('cash') ?></div>
      <span class="stat-card__value"><?= dinheiro($resumo['esperado']) ?></span>
      <span class="stat-card__pe">esperado na gaveta</span>
    </div>
    <div class="stat-card">
      <div class="stat-card__topo"><span class="stat-card__label">Entradas</span><?= icone('download') ?></div>
      <span class="stat-card__value"><?= dinheiro($entradas) ?></span>
      <span class="stat-card__pe"><?= $nEntradas ?> lançamento<?= $nEntradas === 1 ? '' : 's' ?></span>
    </div>
    <div class="stat-card">
      <div class="stat-card__topo"><span class="stat-card__label">Saídas</span><?= icone('upload') ?></div>
      <span class="stat-card__value"><?= dinheiro($saidas) ?></span>
      <span class="stat-card__pe"><?= $nSaidas ?> lançamento<?= $nSaidas === 1 ? '' : 's' ?></span>
    </div>
  </div>

  <div class="grade-3">
    <section class="card">
      <h2 class="card__titulo">Entradas por forma</h2>
      <?php if (!$porForma): ?><p class="form-dica">Nenhuma entrada nesta sessão.</p><?php endif; ?>
      <ul class="ranking ranking--icone">
        <?php foreach ($porForma as $forma => $v): ?>
          <li><span class="ranking__nome"><?= icone($iconeForma[$forma] ?? 'coins') ?><?= e(Lancamento::FORMAS[$forma] ?? $forma) ?></span><b><?= dinheiro($v) ?></b>
            <span class="ranking__trilho"><span style="width:<?= $entradas > 0 ? round($v / $entradas * 100, 1) : 0 ?>%"></span></span></li>
        <?php endforeach; ?>
      </ul>
      <p class="form-dica">Só o dinheiro soma no caixa atual. PIX, cartão e boleto ficam registrados no financeiro.</p>
    </section>

    <section class="card">
      <h2 class="card__titulo">Saídas por destino</h2>
      <?php if (!$porDestino): ?><p class="form-dica">Nenhuma saída nesta sessão.</p><?php endif; ?>
      <ul class="ranking ranking--icone">
        <?php foreach ($porDestino as $nome => [$ic, $v]): ?>
          <li><span class="ranking__nome"><?= icone($ic) ?><?= e($nome) ?></span><b><?= dinheiro($v) ?></b>
            <span class="ranking__trilho"><span style="width:<?= $saidas > 0 ? round($v / $saidas * 100, 1) : 0 ?>%"></span></span></li>
        <?php endforeach; ?>
      </ul>
      <p class="form-dica">Saídas em dinheiro descontam do caixa atual.</p>
      <div class="resultado"><span>Resultado da sessão</span><b><?= $entradas - $saidas < 0 ? '−' : '' ?><?= dinheiro(abs($entradas - $saidas)) ?></b></div>
    </section>

    <section class="card">
      <h2 class="card__titulo">Últimos lançamentos <?php if (count($movimentos) > 5): ?><a href="#movimentos">Ver todos<?= icone('chevron-right', 12, 2) ?></a><?php endif; ?></h2>
      <?php if (!$movimentos): ?><p class="form-dica">Nenhum movimento nesta sessão.</p><?php endif; ?>
      <ul class="lancamentos">
        <?php foreach (array_slice($movimentos, 0, 5) as $m): [$t, $sub] = $descricaoMov($m); $l = $linkMov($m); ?>
          <li>
            <span class="lancamentos__hora"><?= date('H:i', strtotime($m['data_movimento'])) ?></span>
            <span class="lancamentos__texto"><?php if ($l): ?><a href="<?= e($l) ?>"><?= e($t) ?></a><?php else: ?><?= e($t) ?><?php endif; ?><small><?= e($sub) ?></small></span>
            <b class="lancamentos__valor"><?= $m['direcao'] === 'ENTRADA' ? '' : '− ' ?><?= dinheiro($m['valor']) ?></b>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>

  <?php if (count($movimentos) > 5): ?>
    <div class="card card--tabela card--espaco" id="movimentos">
      <div class="card__header"><span class="card__title">Todos os movimentos da sessão</span><span class="form-dica"><?= count($movimentos) ?> movimentos</span></div>
      <?php require __DIR__ . '/_movimentos.php'; ?>
    </div>
  <?php endif; ?>

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
      <p class="form-dica">Conte o dinheiro da gaveta e informe o valor. O sistema compara com o esperado (<?= dinheiro($resumo['esperado']) ?>) e registra sobra ou falta.</p>
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

<?php if ($sessoes): ?>
  <div class="card card--tabela card--espaco">
    <div class="card__header"><span class="card__title">Histórico de sessões</span><a href="./caixas">Ver todas<?= icone('chevron-right', 12, 2) ?></a></div>
    <?php $caixas = $sessoes; require __DIR__ . '/_sessoes.php'; ?>
  </div>
<?php endif; ?>
