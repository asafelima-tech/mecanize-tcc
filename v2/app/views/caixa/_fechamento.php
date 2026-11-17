<?php
/**
 * Fechamento calculado de uma sessão de caixa: saldo anterior + entradas − saídas = saldo final,
 * e o mesmo por forma de pagamento. Usado na janela de fechar caixa e na consulta da sessão.
 * Espera: $fechamento (Caixa::fechamento) e, opcional, $fechamentoTotais (mostra o quadro de totais; padrão true)
 *         e $fechamentoAberto (sessão ainda aberta: o texto da gaveta fala no futuro; padrão true).
 */
$fx = $fechamento;
$comTotais = $fechamentoTotais ?? true;
$emAberto = $fechamentoAberto ?? true;
$iconeForma = ['DINHEIRO' => 'cash', 'PIX' => 'qr-code', 'CARTAO_DEBITO' => 'credit-card', 'CARTAO_CREDITO' => 'credit-card',
               'BOLETO' => 'barcode', 'TRANSFERENCIA' => 'bank', 'OUTRO' => 'more-h'];
$sinal = fn (float $v): string => ($v < 0 ? '− ' : '') . dinheiro(abs($v));
?>
<div class="fechamento">
  <?php if ($comTotais): ?>
    <dl class="fechamento__conta">
      <div><dt>Saldo anterior</dt><dd><?= dinheiro($fx['anterior']) ?></dd></div>
      <div><dt>Entradas</dt><dd>+ <?= dinheiro($fx['entradas']) ?></dd></div>
      <div><dt>Saídas</dt><dd>− <?= dinheiro($fx['saidas']) ?></dd></div>
      <div class="fechamento__total"><dt>Saldo final</dt><dd><?= $sinal($fx['saldo']) ?></dd></div>
    </dl>
  <?php endif; ?>

  <h4 class="fechamento__titulo">Por forma de pagamento</h4>
  <ul class="fechamento__formas">
    <?php foreach ($fx['formas'] as $forma => $f):
      $partes = [];
      if ($forma === 'DINHEIRO') {
          $partes[] = 'anterior ' . dinheiro($f['anterior']);
      }
      if ($f['entradas'] > 0 || $forma === 'DINHEIRO') {
          $partes[] = '+ ' . dinheiro($f['entradas']);
      }
      if ($f['saidas'] > 0 || $forma === 'DINHEIRO') {
          $partes[] = '− ' . dinheiro($f['saidas']);
      } ?>
      <li>
        <span class="fechamento__icone" aria-hidden="true"><?= icone($iconeForma[$forma] ?? 'coins', 17) ?></span>
        <span class="fechamento__forma"><?= e(Lancamento::FORMAS[$forma] ?? $forma) ?><small><?= e(implode(' · ', str_replace(' ', "\u{00A0}", $partes))) ?></small></span>
        <b class="fechamento__valor"><?= $sinal($forma === 'DINHEIRO' ? $fx['dinheiro'] : $f['saldo']) ?></b>
      </li>
    <?php endforeach; ?>
  </ul>

  <p class="fechamento__gaveta">
    <?= icone('cash', 16) ?>
    <span><b><?= dinheiro($fx['dinheiro']) ?> em dinheiro</b> <?= $emAberto ? 'ficam na gaveta e viram o saldo anterior do próximo caixa.' : 'ficaram na gaveta para o caixa seguinte.' ?>
      PIX, cartão, boleto e transferência vão para a conta da oficina.</span>
  </p>
</div>
