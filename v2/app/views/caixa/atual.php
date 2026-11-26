<?php
/**
 * Caixa (Design System, tela 15): situação da sessão, dinheiro esperado na gaveta, entradas e saídas
 * da sessão, últimos lançamentos e o histórico de sessões (ADMIN).
 * Espera: $caixa, $resumo, $fechamento, $movimentos, $anterior (último fechamento, com o caixa fechado), $sessoes
 */
$titulo = 'Caixa';
$admin = Contexto::perfil() === 'ADMIN';

/**
 * Composição de um total (entradas por forma, saídas por destino): uma barra única dividida em tons de uma mesma
 * cor e, abaixo, a legenda com nome, participação e valor. Sem ícones nem trilhos: a barra já mostra a proporção.
 */
$composicao = function (array $partes, float $total, string $vazio): string {
    if (!$partes || $total <= 0) {
        return '<p class="composicao__vazio">' . e($vazio) . '</p>';
    }
    $pct = fn (float $v): string => ($p = $v / $total * 100) > 0 && $p < 1 ? '<1%' : round($p) . '%';
    $barra = $lista = '';
    $i = 0;
    foreach ($partes as $nome => $v) {
        $tom = min($i++, 4);
        $barra .= '<span class="composicao__fatia" data-tom="' . $tom . '" style="flex-grow:' . round($v, 2) . '"></span>';
        $lista .= '<li><span class="composicao__ponto" data-tom="' . $tom . '" aria-hidden="true"></span><span class="composicao__nome">' . e($nome)
            . '</span><span class="composicao__pct">' . $pct($v) . '</span><b>' . dinheiro($v) . '</b></li>';
    }
    $rotulo = implode('; ', array_map(fn ($n, $v) => $n . ' ' . $pct($v), array_keys($partes), $partes));

    return '<div class="composicao__barra" role="img" aria-label="' . e($rotulo) . '">' . $barra . '</div><ul class="composicao__lista">' . $lista . '</ul>';
};

if ($caixa) {
    // Entradas por forma e saídas por destino, somadas dos movimentos da sessão.
    $entradas = $saidas = 0.0;
    $nEntradas = $nSaidas = 0;
    $entradasDinheiro = $saidasDinheiro = 0.0;
    $porForma = [];
    $porDestino = [];
    foreach ($movimentos as $m) {
        $v = (float) $m['valor'];
        $emDinheiro = $m['forma_pagamento'] === 'DINHEIRO';
        if ($m['direcao'] === 'ENTRADA') {
            $entradas += $v;
            $nEntradas++;
            $entradasDinheiro += $emDinheiro ? $v : 0;
            $nome = Lancamento::FORMAS[$m['forma_pagamento']] ?? $m['forma_pagamento'];
            $porForma[$nome] = ($porForma[$nome] ?? 0) + $v;
        } else {
            $saidas += $v;
            $nSaidas++;
            $saidasDinheiro += $emDinheiro ? $v : 0;
            $nome = match (true) {
                $m['natureza'] === 'SANGRIA' => 'Retirada (sangria)',
                $m['natureza'] === 'ESTORNO' => 'Estornos',
                default => Lancamento::CATEGORIAS['PAGAR'][$m['lancamento_categoria']] ?? 'Pagamentos',
            };
            $porDestino[$nome] = ($porDestino[$nome] ?? 0) + $v;
        }
    }
    arsort($porForma);
    arsort($porDestino);
    $resultado = $entradas - $saidas;
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
    <?php if ($admin): ?><a class="btn btn--ghost" href="./relatorios"><?= icone('chart') ?> Relatório de caixa</a><?php endif; ?>
    <?php if ($caixa): ?>
      <button type="button" class="btn btn--ghost" data-abrir="dlg-mov" data-natureza="SUPRIMENTO"><?= icone('download') ?> Suprimento</button>
      <button type="button" class="btn btn--ghost" data-abrir="dlg-mov" data-natureza="SANGRIA"><?= icone('upload') ?> Sangria</button>
      <button type="button" class="btn btn--primary" data-abrir="dlg-fechar"><?= icone('lock') ?> Fechar caixa</button>
    <?php endif; ?>
  </div>
</div>

<?php if (!$caixa): ?>
  <form class="card form-card caixa-abrir" method="post" action="./caixa-abrir">
    <?= csrf_field() ?>
    <h2 class="card__titulo">Abrir caixa</h2>
    <?php if ($anterior): ?>
      <div class="caixa-abrir__base">
        <span>Saldo anterior</span>
        <strong><?= dinheiro($anterior['valor_fechamento']) ?></strong>
        <small>Dinheiro que ficou na gaveta no fechamento de <?= data_br($anterior['fechado_em'], true) ?><?= $anterior['fechado_por_nome'] ? ', por ' . e(strtok((string) $anterior['fechado_por_nome'], ' ')) : '' ?>.</small>
      </div>
    <?php else: ?>
      <div class="form-field"><label for="valor">Dinheiro na gaveta para começar (R$)</label><input id="valor" name="valor" inputmode="decimal" required>
        <span class="form-dica">Só no primeiro caixa. Depois o saldo de cada fechamento vira o saldo anterior do caixa seguinte.</span></div>
    <?php endif; ?>
    <div class="form-field" style="margin-top:12px"><label for="observacao">Observação</label><input id="observacao" name="observacao" maxlength="255"></div>
    <div class="form-acoes"><button class="btn btn--primary">Abrir caixa</button></div>
  </form>
<?php else: ?>
  <div class="stat-grid stat-grid--icone">
    <div class="stat-card">
      <div class="stat-card__topo"><span class="stat-card__label">Status</span></div>
      <span class="stat-card__value stat-card__value--status"><span class="ponto-status" aria-hidden="true"></span>Aberto</span>
      <span class="stat-card__pe">desde <?= $aberturaHoje ? $horaAbertura : data_br($caixa['aberto_em'], true) ?> · saldo anterior <?= dinheiro($caixa['valor_abertura']) ?></span>
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
    <section class="card composicao" aria-labelledby="comp-entradas">
      <header class="composicao__cabeca">
        <h2 class="card__titulo" id="comp-entradas">Entradas</h2>
        <span><?= count($porForma) ?> forma<?= count($porForma) === 1 ? '' : 's' ?> de pagamento</span>
      </header>
      <?= $composicao($porForma, $entradas, 'Nenhuma entrada nesta sessão.') ?>
      <?php if ($entradas > 0): ?>
        <p class="composicao__nota"><?= $entradasDinheiro > 0
            ? '<b>' . dinheiro($entradasDinheiro) . '</b> em dinheiro foi para a gaveta. O restante fica no financeiro.'
            : 'Nada em dinheiro: tudo fica registrado no financeiro.' ?></p>
      <?php endif; ?>
    </section>

    <section class="card composicao" aria-labelledby="comp-saidas">
      <header class="composicao__cabeca">
        <h2 class="card__titulo" id="comp-saidas">Saídas</h2>
        <span><?= count($porDestino) ?> destino<?= count($porDestino) === 1 ? '' : 's' ?></span>
      </header>
      <?= $composicao($porDestino, $saidas, 'Nenhuma saída nesta sessão.') ?>
      <?php if ($saidas > 0): ?>
        <p class="composicao__nota"><?= $saidasDinheiro > 0
            ? '<b>' . dinheiro($saidasDinheiro) . '</b> saiu da gaveta em dinheiro.'
            : 'Nada saiu da gaveta: as saídas foram por outras formas.' ?></p>
      <?php endif; ?>
      <div class="composicao__resultado">
        <span>Resultado da sessão<small>entradas menos saídas</small></span>
        <b><?= $resultado < 0 ? '-' : '' ?><?= dinheiro(abs($resultado)) ?></b>
      </div>
    </section>

    <section class="card">
      <h2 class="card__titulo">Últimos lançamentos <?php if (count($movimentos) > 5): ?><a href="#movimentos">Ver todos<?= icone('chevron-right', 12, 2) ?></a><?php endif; ?></h2>
      <?php if (!$movimentos): ?><p class="form-dica">Nenhum movimento nesta sessão.</p><?php endif; ?>
      <ul class="lancamentos">
        <?php foreach (array_slice($movimentos, 0, 5) as $m): [$t, $sub] = $descricaoMov($m); $l = $linkMov($m); ?>
          <li>
            <span class="lancamentos__hora"><?= date('H:i', strtotime($m['data_movimento'])) ?></span>
            <span class="lancamentos__texto"><?php if ($l): ?><a href="<?= e($l) ?>"><?= e($t) ?></a><?php else: ?><?= e($t) ?><?php endif; ?><small><?= e($sub) ?></small></span>
            <b class="lancamentos__valor"><?= $m['direcao'] === 'ENTRADA' ? '' : '-' ?><?= dinheiro($m['valor']) ?></b>
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
      <div class="form-field"><label for="atual-valor-189">Valor (R$)</label><input id="atual-valor-189" name="valor" inputmode="decimal" required></div>
      <div class="form-field"><label for="atual-descricao-190">Motivo</label><input id="atual-descricao-190" name="descricao" maxlength="200" required placeholder="Ex.: troco, depósito no banco"></div>
      <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Cancelar</button><button class="btn btn--primary">Registrar</button></div>
    </form>
  </dialog>
  <dialog id="dlg-fechar" class="dlg dlg--fechamento" aria-labelledby="dlg-fechar-titulo">
    <form method="post" action="./caixa-fechar">
      <?= csrf_field() ?>
      <h3 id="dlg-fechar-titulo" tabindex="-1" autofocus>Fechar caixa</h3>
      <p class="form-dica">Calculado pelos lançamentos desde <?= $aberturaHoje ? 'hoje às ' . $horaAbertura : data_br($caixa['aberto_em'], true) ?>.</p>
      <?php require __DIR__ . '/_fechamento.php'; ?>
      <div class="form-field"><label for="obs-fechar">Observação <span class="opcional">(opcional)</span></label><input id="obs-fechar" name="observacao" maxlength="255"></div>
      <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Cancelar</button><button class="btn btn--primary">Confirmar fechamento</button></div>
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
