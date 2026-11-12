<?php
/**
 * Ficha da conta, no formato das outras fichas de consulta: topo com a identificação e as ações
 * (Receber/Pagar, Editar e, no menu, Cancelar), os números da conta (saldo, valor, pago, vencimento),
 * os dados em blocos, os pagamentos com estorno e, ao lado, parcelas, comprovantes e histórico.
 * Os diálogos e os destinos dos formulários são os mesmos de antes.
 * Espera: $l, $parcelas, $movimentos, $historico, $caixa, $anexos
 */
$receber = $l['tipo'] === 'RECEBER';
$titulo = $l['descricao'];
$saldo = round((float) $l['devido'] - (float) $l['valor_pago'], 2);
$cores = ['ABERTO' => 'neutral', 'PARCIAL' => 'info', 'PAGO' => 'success', 'CANCELADO' => 'muted'];
$nomes = ['ABERTO' => 'Em aberto', 'PARCIAL' => 'Parcial', 'PAGO' => $receber ? 'Recebido' : 'Pago', 'CANCELADO' => 'Cancelado'];
$aberta = in_array($l['status'], ['ABERTO', 'PARCIAL'], true);
$acoesHist = ['CRIACAO' => 'Lançada', 'ALTERACAO' => 'Alterada', 'PAGAMENTO' => $receber ? 'Recebimento' : 'Pagamento', 'ESTORNO' => 'Estorno', 'CANCELAMENTO' => 'Cancelada', 'ANEXO' => 'Comprovante anexado'];
?>
<?php
$quem = $l['cliente'] ?? $l['fornecedor'] ?? $l['funcionario'] ?? null;
$hoje = date('Y-m-d');
$atrasada = $aberta && $l['vencimento'] < $hoje;
[$rotulo, $cor] = $atrasada ? ['Atrasada', 'danger'] : [$nomes[$l['status']], $cores[$l['status']]];
$dias = (int) (new DateTimeImmutable($hoje))->diff(new DateTimeImmutable($l['vencimento']))->format('%r%a');
$prazo = !$aberta ? '' : match (true) {
    $dias === 0 => 'vence hoje', $dias === 1 => 'vence amanhã', $dias > 1 => 'vence em ' . $dias . ' dias',
    $dias === -1 => 'atrasada há 1 dia', default => 'atrasada há ' . abs($dias) . ' dias',
};
$feitos = array_filter($movimentos, fn($m) => $m['natureza'] !== 'ESTORNO' && !$m['estornado']);
$ultimo = $feitos ? max(array_column($feitos, 'data_movimento')) : null;
?>
<header class="ver-topo">
  <div class="ver-topo__id">
    <span class="ver-topo__avatar ver-topo__avatar--quadrado" aria-hidden="true"><?= icone($receber ? 'download' : 'upload', 30, 1.6) ?></span>
    <div class="ver-topo__texto">
      <p class="ver-topo__voltar"><a href="./financeiro?tipo=<?= e($l['tipo']) ?>"><?= icone('chevron-left', 14, 2) ?> <?= $receber ? 'Contas a receber' : 'Contas a pagar' ?></a></p>
      <h1><?= e($l['descricao']) ?></h1>
      <p class="ver-topo__meta">
        <span class="badge badge--<?= $cor ?>"><?= $rotulo ?></span>
        <span class="ver-chip"><?= icone('folder', 13) ?> <?= e(Lancamento::categoria($l['tipo'], $l['categoria'])) ?></span>
        <?php if ($l['parcela_total'] > 1): ?><span class="ver-chip ver-chip--mono">Parcela <?= (int) $l['parcela_numero'] ?> de <?= (int) $l['parcela_total'] ?></span><?php endif; ?>
        <?php if ($l['os_numero']): ?><a class="ver-chip ver-chip--mono" href="./os?id=<?= (int) $l['ordem_servico_id'] ?>"><?= icone('work-order', 13) ?> <?= os_numero($l['os_numero']) ?></a><?php endif; ?>
        <?php if ($l['documento']): ?><span class="ver-chip ver-chip--mono">Doc. <?= e($l['documento']) ?></span><?php endif; ?>
      </p>
    </div>
  </div>
  <div class="ver-topo__acoes">
    <?php if ($aberta): ?>
      <button type="button" class="btn btn--ghost btn--sm" data-abrir="dlg-editar"><?= icone('edit') ?> Editar</button>
      <button type="button" class="btn btn--primary btn--sm" data-baixar="dlg-baixa" data-conta="<?= (int) $l['id'] ?>" data-saldo="<?= e(number_format($saldo, 2, ',', '.')) ?>" data-forma="<?= e($l['forma_prevista'] ?? '') ?>" data-rotulo="Saldo: <?= e(dinheiro($saldo)) ?>">
        <?= icone('cash') ?> <?= $receber ? 'Receber' : 'Pagar' ?></button>
    <?php endif; ?>
    <?php if ($l['status'] === 'ABERTO' && (float) $l['valor_pago'] == 0): ?>
      <details class="menu-mais">
        <summary class="btn btn--ghost btn--sm btn--icone" aria-label="Mais ações"><?= icone('more-h') ?></summary>
        <div class="menu-mais__lista">
          <button type="button" data-abrir="dlg-cancelar"><?= icone('cancel') ?>Cancelar conta</button>
        </div>
      </details>
    <?php endif; ?>
  </div>
</header>

<?php if ($l['status'] === 'CANCELADO'): ?>
  <?php $alertaTipo = 'aviso'; $alertaTitulo = 'Cancelada em ' . data_br($l['cancelado_em'], true); $alertaMensagem = $l['motivo_cancelamento'] ?? ''; require V2_RAIZ . '/app/views/layout/_alerta.php'; ?>
<?php endif; ?>

<div class="ver-numeros" aria-label="Valores">
  <div><span>Saldo</span><strong><?= $l['status'] === 'CANCELADO' ? '—' : dinheiro(max(0, $saldo)) ?></strong><small><?= $l['status'] === 'PAGO' ? 'quitada' : ($l['status'] === 'CANCELADO' ? 'conta cancelada' : ($receber ? 'falta receber' : 'falta pagar')) ?></small></div>
  <div><span>Valor</span><strong><?= dinheiro($l['devido']) ?></strong><small><?= (float) $l['acrescimo'] > 0 || (float) $l['desconto'] > 0 ? dinheiro($l['valor']) . ((float) $l['acrescimo'] > 0 ? ' + ' . dinheiro($l['acrescimo']) . ' de juros' : '') . ((float) $l['desconto'] > 0 ? ' − ' . dinheiro($l['desconto']) . ' de desconto' : '') : 'valor da conta' ?></small></div>
  <div><span><?= $receber ? 'Recebido' : 'Pago' ?></span><strong><?= dinheiro($l['valor_pago']) ?></strong><small><?= $ultimo ? 'último em ' . data_br($ultimo) : 'nenhum ' . ($receber ? 'recebimento' : 'pagamento') ?></small></div>
  <div class="<?= $atrasada ? 'ver-numeros__alerta' : '' ?>"><span>Vencimento</span><strong><?= data_br($l['vencimento']) ?></strong><small><?= $prazo ?: 'competência ' . date('m/Y', strtotime($l['competencia'])) ?></small></div>
</div>

<div class="ver-grade">
  <div class="ver-principal">
    <section class="card ver-dados" aria-label="Dados da conta">
      <div class="ver-grupo">
        <h2 class="ver-grupo__titulo"><?= icone($receber ? 'user' : 'transfer', 15) ?> <?= $receber ? 'Cliente' : 'Favorecido' ?></h2>
        <dl class="ver-lista">
          <div><dt><?= $receber ? 'Cliente' : ($l['funcionario'] && !$l['fornecedor'] ? 'Funcionário' : 'Fornecedor') ?></dt><dd><?= $quem ? e($quem) : '<span class="secundario">Não informado</span>' ?></dd></div>
          <?php if ($l['os_numero']): ?><div><dt>Ordem de serviço</dt><dd><a class="ver-link" href="./os?id=<?= (int) $l['ordem_servico_id'] ?>"><?= os_numero($l['os_numero']) ?></a></dd></div><?php endif; ?>
          <div><dt>Documento</dt><dd><?= $l['documento'] ? e($l['documento']) : '<span class="secundario">Não informado</span>' ?></dd></div>
        </dl>
      </div>
      <div class="ver-grupo">
        <h2 class="ver-grupo__titulo"><?= icone('calendar', 15) ?> Datas e forma</h2>
        <dl class="ver-lista">
          <div><dt>Competência</dt><dd><?= date('m/Y', strtotime($l['competencia'])) ?></dd></div>
          <div><dt>Forma prevista</dt><dd><?= $l['forma_prevista'] ? e(Lancamento::FORMAS[$l['forma_prevista']] ?? $l['forma_prevista']) : '<span class="secundario">A definir</span>' ?></dd></div>
          <div><dt>Lançada em</dt><dd><?= data_br($l['criado_em']) ?><?= $l['criado_por_nome'] ? ' <span class="secundario">· ' . e($l['criado_por_nome']) . '</span>' : '' ?></dd></div>
        </dl>
      </div>
      <div class="ver-obs">
        <h2 class="ver-grupo__titulo"><?= icone('file', 15) ?> Observação</h2>
        <?php if ($l['observacao']): ?><p><?= e($l['observacao']) ?></p><?php else: ?><p class="secundario">Nenhuma observação.</p><?php endif; ?>
      </div>
    </section>

    <section class="card card--tabela ver-historico" aria-labelledby="ver-movs">
      <header class="ver-historico__cabeca">
        <div><h2 id="ver-movs"><?= $receber ? 'Recebimentos' : 'Pagamentos' ?></h2><p><?= $movimentos ? 'Um estorno lança o valor contrário; o registro original continua.' : 'Cada ' . ($receber ? 'recebimento' : 'pagamento') . ', total ou parcial, aparece aqui.' ?></p></div>
        <?php if ($aberta): ?><button type="button" class="btn btn--ghost btn--sm" data-baixar="dlg-baixa" data-conta="<?= (int) $l['id'] ?>" data-saldo="<?= e(number_format($saldo, 2, ',', '.')) ?>" data-forma="<?= e($l['forma_prevista'] ?? '') ?>" data-rotulo="Saldo: <?= e(dinheiro($saldo)) ?>"><?= icone('plus') ?> Registrar</button><?php endif; ?>
      </header>
      <?php if (!$movimentos): ?>
        <div class="vazio"><?= icone('cash', 28, 1.25) ?>Nenhum <?= $receber ? 'recebimento' : 'pagamento' ?> ainda.</div>
      <?php else: ?>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Data</th><th>Forma</th><th>Por</th><th class="num">Valor</th><th class="num"><span class="sr-only">Ações</span></th></tr></thead>
          <tbody>
            <?php foreach ($movimentos as $m): $estorno = $m['natureza'] === 'ESTORNO'; ?>
              <tr class="<?= $m['estornado'] ? 'inativo' : '' ?>">
                <td class="tabular"><?= data_br($m['data_movimento'], true) ?><?= $estorno ? '<span class="sub">estorno: ' . e($m['descricao']) . '</span>' : ($m['descricao'] ? '<span class="sub">' . e($m['descricao']) . '</span>' : '') ?></td>
                <td><?= e(Lancamento::FORMAS[$m['forma_pagamento']] ?? $m['forma_pagamento']) ?><?= (float) $m['taxa'] > 0 ? '<span class="sub">taxa ' . dinheiro($m['taxa']) . '</span>' : '' ?></td>
                <td><?= e($m['usuario'] ?? '—') ?></td>
                <td class="num"><strong><?= $estorno ? '− ' : '' ?><?= dinheiro($m['valor']) ?></strong></td>
                <td class="num"><?php if (!$estorno && !$m['estornado']): ?><button type="button" class="btn btn--ghost btn--sm" data-estornar="<?= (int) $m['id'] ?>">Estornar</button><?php elseif ($m['estornado']): ?><span class="form-dica">estornado</span><?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </section>
  </div>

  <div class="ver-coluna">
    <?php if (count($parcelas) > 1): ?>
      <aside class="card ver-lado" aria-labelledby="ver-parcelas">
        <header class="ver-lado__cabeca"><h2 id="ver-parcelas">Parcelas</h2><span class="secundario"><?= count($parcelas) ?>×</span></header>
        <ul class="conta-lista">
          <?php foreach ($parcelas as $pc): $atual = (int) $pc['id'] === (int) $l['id']; ?>
            <li><a href="./conta?id=<?= (int) $pc['id'] ?>" class="conta-lista__item<?= $atual ? ' is-atual' : '' ?>">
              <span class="conta-lista__n"><?= (int) $pc['parcela_numero'] ?></span>
              <span class="conta-lista__texto"><strong><?= data_br($pc['vencimento']) ?></strong><small><?= $nomes[$pc['status']] ?><?= $atual ? ' · esta' : '' ?></small></span>
              <b><?= dinheiro($pc['devido']) ?></b>
            </a></li>
          <?php endforeach; ?>
        </ul>
      </aside>
    <?php endif; ?>

    <aside class="card ver-lado" aria-labelledby="ver-comprovantes">
      <header class="ver-lado__cabeca"><h2 id="ver-comprovantes">Comprovantes</h2><?php if ($anexos): ?><span class="secundario"><?= count($anexos) ?></span><?php endif; ?></header>
      <?php if (!$anexos): ?><div class="ver-lado__vazio"><?= icone('attach', 22, 1.4) ?><span>Nenhum comprovante anexado.</span></div><?php endif; ?>
      <?php if ($anexos): ?>
        <ul class="conta-lista">
          <?php foreach ($anexos as $a): ?>
            <li><a href="./anexo?id=<?= (int) $a['id'] ?>" target="_blank" rel="noopener" class="conta-lista__item">
              <span class="conta-lista__n"><?= icone(Anexo::ehImagem($a) ? 'image' : 'file', 15) ?></span>
              <span class="conta-lista__texto"><strong><?= e($a['descricao'] ?: $a['nome_original']) ?></strong><small><?= data_br($a['enviado_em']) ?></small></span>
              <?= icone('external', 14) ?>
            </a></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if (count($anexos) < Anexo::MAX_POR_DONO): ?>
        <form class="conta-anexar" method="post" action="./conta-anexar?conta=<?= (int) $l['id'] ?>" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="form-field"><input type="file" name="arquivo" required accept="image/jpeg,image/png,image/webp,application/pdf" aria-label="Comprovante"></div>
          <div class="form-field"><input name="descricao" maxlength="200" placeholder="Descrição (opcional)" aria-label="Descrição"></div>
          <button class="btn btn--ghost btn--sm"><?= icone('attach') ?> Anexar comprovante</button>
          <span class="form-dica">PDF ou imagem, até 5 MB.</span>
        </form>
      <?php endif; ?>
    </aside>

    <aside class="card ver-lado" aria-labelledby="ver-hist">
      <header class="ver-lado__cabeca"><h2 id="ver-hist">Histórico</h2></header>
      <ol class="conta-linha-tempo">
        <?php foreach ($historico as $h): ?>
          <li>
            <strong><?= e($acoesHist[$h['acao']] ?? $h['acao']) ?><?php if ($h['dados'] && ($d = json_decode($h['dados'], true)) && isset($d['valor'])): ?> · <?= dinheiro($d['valor']) ?><?php endif; ?></strong>
            <small><?= data_br($h['criado_em'], true) ?><?= $h['autor'] ? ' · ' . e($h['autor']) : '' ?></small>
            <?php if ($h['motivo']): ?><span><?= e($h['motivo']) ?></span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </aside>
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
  document.querySelectorAll('[data-abrir]').forEach(function (b) { b.addEventListener('click', function () { var m = b.closest('details'); if (m) m.open = false; document.getElementById(b.dataset.abrir).showModal(); }); });
  // Vindo do menu da lista de contas (#editar / #cancelar): abre direto o diálogo, se a ação existir nesta conta.
  (function () { var d = { '#editar': 'dlg-editar', '#cancelar': 'dlg-cancelar' }[location.hash]; if (d && document.querySelector('[data-abrir="' + d + '"]')) document.getElementById(d).showModal(); })();
  document.querySelectorAll('[data-estornar]').forEach(function (b) {
    b.addEventListener('click', function () { var d = document.getElementById('dlg-estornar'); d.querySelector('form').action = './conta-estornar?id=' + b.dataset.estornar; d.showModal(); });
  });
  document.querySelectorAll('dialog [data-fechar]').forEach(function (b) { b.onclick = function () { b.closest('dialog').close(); }; });
</script>
