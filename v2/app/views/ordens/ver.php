<?php
/** Espera: $os, $itens, $historico, $tecnicos, $catalogo, $contas, $faltaLancar, $caixa, $anexos, $linkAcompanhamento */
$titulo   = os_numero($os['numero']);
[$rotuloStatus, $corStatus] = os_status($os['status']);
$st       = $os['status'];
$editavel = in_array($st, OrdemServico::EDITAVEL, true);
$noOrcamento = $st === 'ORCAMENTO';
$cancelavel  = in_array($st, OrdemServico::TRANSICOES['cancelar'][0], true);
$qtd = fn($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
$ativos = array_filter($itens, fn($i) => $i['estoque_status'] !== 'DEVOLVIDO');
$acaoUrl = './os-acao?id=' . (int) $os['id'];
$acoesHistorico = [
    'CRIACAO' => 'Orçamento aberto', 'DADOS' => 'Dados alterados', 'ITEM_INCLUIDO' => 'Item incluído', 'ITEM_ADICIONAL' => 'Item adicional',
    'ITEM_ALTERADO' => 'Item alterado', 'ITEM_REMOVIDO' => 'Item removido', 'APROVACAO' => 'Orçamento aprovado', 'STATUS' => 'Situação',
    'MIGRACAO_V1' => 'Migrado da versão anterior', 'ORCAMENTO_VERSAO' => 'Orçamento original preservado', 'ANEXO' => 'Arquivo anexado',
];
$botao = fn(string $acao, string $rotulo, string $classe = 'btn--primary') =>
    '<form method="post" action="' . $acaoUrl . '">' . csrf_field() . '<input type="hidden" name="acao" value="' . $acao . '"><button class="btn ' . $classe . ' btn--sm">' . $rotulo . '</button></form>';
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= os_numero($os['numero']) ?> <span class="badge badge--<?= $corStatus ?>"><?= $rotuloStatus ?></span></h1>
    <p class="page-header__subtitle"><a href="./ordens">Ordens de serviço</a> · <?= e($os['cliente']) ?> · <?= e($os['placa']) ?> <?= e($os['modelo']) ?> · aberta em <?= data_br($os['criado_em'], true) ?></p>
  </div>
  <div class="page-header__actions acoes">
    <a class="btn btn--ghost btn--sm" href="./os-imprimir?id=<?= (int) $os['id'] ?>" target="_blank"><i class="bi bi-printer"></i> Imprimir</a>
    <button type="button" class="btn btn--ghost btn--sm" data-copiar="<?= e($linkAcompanhamento) ?>"><i class="bi bi-link-45deg"></i> Link do cliente</button>
    <?php if ($cancelavel): ?><button type="button" class="btn btn--danger-ghost btn--sm" data-abrir="dlg-cancelar">Cancelar OS</button><?php endif; ?>
    <?php if ($st === 'ORCAMENTO'): ?>
      <button type="button" class="btn btn--ghost btn--sm" data-abrir="dlg-recusar">Recusado</button>
      <button type="button" class="btn btn--primary btn--sm" data-abrir="dlg-aprovar"><i class="bi bi-check2"></i> Aprovar orçamento</button>
    <?php elseif ($st === 'RECUSADA'): ?>
      <?= $botao('reabrir', 'Reabrir orçamento') ?>
    <?php elseif ($st === 'APROVADA'): ?>
      <?= $botao('iniciar', '<i class="bi bi-play-fill"></i> Iniciar execução') ?>
    <?php elseif ($st === 'AGUARDANDO_PECA'): ?>
      <?= $botao('baixar', '<i class="bi bi-box-arrow-in-down"></i> Peças chegaram') ?>
    <?php elseif ($st === 'EM_EXECUCAO'): ?>
      <?= $botao('concluir', '<i class="bi bi-check2-circle"></i> Concluir serviço') ?>
    <?php elseif ($st === 'PRONTA'): ?>
      <button type="button" class="btn btn--ghost btn--sm" data-abrir="dlg-retomar">Voltar para execução</button>
      <button type="button" class="btn btn--primary btn--sm" data-abrir="dlg-entregar"><i class="bi bi-key"></i> Entregar veículo</button>
    <?php endif; ?>
  </div>
</div>

<?php if ($st === 'RECUSADA' && $os['motivo_recusa']): ?>
  <?php $alertaTipo = 'aviso'; $alertaTitulo = 'Orçamento recusado'; $alertaMensagem = $os['motivo_recusa']; require V2_RAIZ . '/app/views/layout/_alerta.php'; ?>
<?php elseif ($st === 'CANCELADA'): ?>
  <?php $alertaTipo = 'erro'; $alertaTitulo = 'OS cancelada em ' . data_br($os['cancelado_em'], true); $alertaMensagem = $os['motivo_cancelamento'] ?? ''; require V2_RAIZ . '/app/views/layout/_alerta.php'; ?>
<?php endif; ?>

<div class="ficha">
  <div>
    <!-- Itens -->
    <div class="card card--tabela">
      <div class="card__header"><span class="card__title">Serviços e peças</span>
        <?php if ($os['valor_aprovado'] !== null && (float) $os['valor_aprovado'] !== (float) $os['valor_total']): ?>
          <span class="form-dica">Aprovado: <?= dinheiro($os['valor_aprovado']) ?> · diferença <?= dinheiro((float) $os['valor_total'] - (float) $os['valor_aprovado']) ?></span>
        <?php endif; ?>
      </div>
      <?php if (!$itens): ?>
        <div class="vazio"><i class="bi bi-list-check"></i>Nenhum item ainda. Inclua os serviços e as peças abaixo.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Descrição</th><th class="num">Qtd</th><th class="num">Unitário</th><th class="num">Desconto</th><th class="num">Total</th><?php if ($editavel): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
              <?php foreach ($itens as $i): $devolvido = $i['estoque_status'] === 'DEVOLVIDO'; ?>
                <tr class="<?= $devolvido ? 'inativo' : '' ?>">
                  <td>
                    <?= $devolvido ? '<s>' . e($i['descricao']) . '</s>' : e($i['descricao']) ?>
                    <span class="sub">
                      <?= $i['tipo'] === 'PECA' ? 'Peça' : 'Serviço' ?>
                      <?= $i['origem'] === 'ADICIONAL' ? ' · <strong>adicional</strong>' : '' ?>
                      <?= $i['tecnico'] ? ' · ' . e($i['tecnico']) : '' ?>
                      <?php if ($i['tipo'] === 'PECA'): ?> · <?= ['PENDENTE' => 'a baixar do estoque', 'BAIXADO' => 'baixada do estoque', 'DEVOLVIDO' => 'removida (devolvida ao estoque)'][$i['estoque_status']] ?? '' ?><?php endif; ?>
                      <?= (float) $i['comissao_valor'] > 0 ? ' · comissão ' . dinheiro($i['comissao_valor']) : '' ?>
                    </span>
                  </td>
                  <td class="num"><?= $qtd($i['quantidade']) ?></td>
                  <td class="num"><?= dinheiro($i['valor_unitario']) ?></td>
                  <td class="num"><?= (float) $i['desconto'] > 0 ? '−' . dinheiro($i['desconto']) : '—' ?></td>
                  <td class="num"><?= dinheiro($i['valor_total']) ?></td>
                  <?php if ($editavel): ?>
                    <td class="num" style="white-space:nowrap">
                      <?php if (!$devolvido): ?>
                        <button type="button" class="btn btn--ghost btn--sm" title="Alterar" aria-label="Alterar item" data-editar-item='<?= e(json_encode(['id' => (int) $i['id'], 'tipo' => $i['tipo'], 'descricao' => $i['descricao'], 'quantidade' => $qtd($i['quantidade']), 'valor_unitario' => number_format((float) $i['valor_unitario'], 2, ',', '.'), 'desconto' => number_format((float) $i['desconto'], 2, ',', '.'), 'tecnico_id' => (int) $i['tecnico_id']], JSON_UNESCAPED_UNICODE)) ?>'><i class="bi bi-pencil"></i></button>
                        <?php if ($noOrcamento): ?>
                          <form method="post" action="./os-item?id=<?= (int) $os['id'] ?>" style="display:inline" data-confirmar="Remover este item do orçamento?">
                            <?= csrf_field() ?><input type="hidden" name="operacao" value="remover"><input type="hidden" name="item_id" value="<?= (int) $i['id'] ?>">
                            <button class="btn btn--ghost btn--sm" title="Remover" aria-label="Remover item"><i class="bi bi-trash"></i></button>
                          </form>
                        <?php else: ?>
                          <button type="button" class="btn btn--ghost btn--sm" title="Remover" aria-label="Remover item" data-remover-item="<?= (int) $i['id'] ?>"><i class="bi bi-trash"></i></button>
                        <?php endif; ?>
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr><td colspan="4" class="num">Peças</td><td class="num"><?= dinheiro($os['valor_pecas']) ?></td><?php if ($editavel): ?><td></td><?php endif; ?></tr>
              <tr><td colspan="4" class="num">Serviços</td><td class="num"><?= dinheiro($os['valor_servicos']) ?></td><?php if ($editavel): ?><td></td><?php endif; ?></tr>
              <?php if ((float) $os['valor_desconto'] > 0): ?><tr><td colspan="4" class="num">Descontos</td><td class="num">−<?= dinheiro($os['valor_desconto']) ?></td><?php if ($editavel): ?><td></td><?php endif; ?></tr><?php endif; ?>
              <tr><td colspan="4" class="num"><strong>Total</strong></td><td class="num"><strong><?= dinheiro($os['valor_total']) ?></strong></td><?php if ($editavel): ?><td></td><?php endif; ?></tr>
            </tfoot>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($editavel): ?>
        <form method="post" action="./os-item?id=<?= (int) $os['id'] ?>" class="os-novo-item" id="form-item">
          <?= csrf_field() ?><input type="hidden" name="operacao" value="incluir">
          <div class="form-field span-3">
            <label for="produto_id">Do catálogo</label>
            <select id="produto_id" name="produto_id">
              <option value="">Serviço avulso (descrever ao lado)</option>
              <?php foreach (['SERVICO' => 'Serviços', 'PECA' => 'Peças'] as $tipo => $grupo): ?>
                <optgroup label="<?= $grupo ?>">
                  <?php foreach ($catalogo as $c): if ($c['tipo'] !== $tipo) continue; ?>
                    <option value="<?= (int) $c['id'] ?>" data-tipo="<?= $tipo ?>" data-preco="<?= e(number_format((float) $c['preco_venda'], 2, ',', '.')) ?>">
                      <?= e($c['nome']) ?> — <?= dinheiro($c['preco_venda']) ?><?= $tipo === 'PECA' ? ' (estoque ' . $qtd($c['estoque_atual']) . ')' : '' ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field span-3"><label for="descricao">Descrição</label><input id="descricao" name="descricao" maxlength="200" placeholder="Ex.: Troca de óleo e filtro"></div>
          <div class="form-field"><label for="quantidade">Qtd</label><input id="quantidade" name="quantidade" value="1" inputmode="decimal"></div>
          <div class="form-field"><label for="valor_unitario">Unitário (R$)</label><input id="valor_unitario" name="valor_unitario" inputmode="decimal"></div>
          <div class="form-field"><label for="desconto">Desconto (R$)</label><input id="desconto" name="desconto" inputmode="decimal" placeholder="0,00"></div>
          <div class="form-field span-2" id="campo-tecnico">
            <label for="tecnico_item">Técnico</label>
            <select id="tecnico_item" name="tecnico_id"><option value="">Responsável da OS</option>
              <?php foreach ($tecnicos as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['nome']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-field" style="justify-content:flex-end"><button class="btn btn--primary"><i class="bi bi-plus-lg"></i> Incluir<?= $noOrcamento ? '' : ' adicional' ?></button></div>
        </form>
        <?php if (!$noOrcamento): ?><p class="form-dica" style="padding:0 24px 16px">O orçamento já foi aprovado: o que for incluído agora aparece como <strong>adicional</strong>, separado do valor aprovado.</p><?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- Fotos e documentos -->
    <div class="card" id="arquivos">
      <h2 class="card__titulo">Fotos e documentos <span class="form-dica"><?= count($anexos) ?> de <?= Anexo::MAX_POR_DONO ?></span></h2>
      <?php if ($anexos): ?>
        <div class="anexos">
          <?php foreach ($anexos as $a): $img = Anexo::ehImagem($a); ?>
            <a class="anexo" href="./anexo?id=<?= (int) $a['id'] ?>" target="_blank" rel="noopener" title="<?= e($a['nome_original']) ?>">
              <?php if ($img): ?><img src="./anexo?id=<?= (int) $a['id'] ?>" alt="<?= e($a['descricao'] ?: $a['nome_original']) ?>" loading="lazy"><?php else: ?><span class="anexo__doc"><i class="bi bi-file-earmark-pdf"></i></span><?php endif; ?>
              <span class="anexo__legenda"><strong><?= e(Anexo::CATEGORIAS_OS[$a['categoria']] ?? $a['categoria']) ?></strong><?= $a['descricao'] ? ' · ' . e($a['descricao']) : '' ?><br><span class="form-dica"><?= data_br($a['enviado_em'], true) ?><?= $a['autor'] ? ' · ' . e($a['autor']) : '' ?></span></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="form-dica">Nenhum arquivo. Fotos do antes e depois ajudam a mostrar ao cliente o que foi feito.</p>
      <?php endif; ?>
      <?php if ($st !== 'CANCELADA' && count($anexos) < Anexo::MAX_POR_DONO): ?>
        <form method="post" action="./os-anexar?os=<?= (int) $os['id'] ?>" enctype="multipart/form-data" class="form-row-3" style="margin-top:12px;align-items:end">
          <?= csrf_field() ?>
          <div class="form-field"><label for="anexo-categoria">Tipo</label>
            <select id="anexo-categoria" name="categoria"><?php foreach (Anexo::CATEGORIAS_OS as $k => $r): ?><option value="<?= $k ?>"><?= e($r) ?></option><?php endforeach; ?></select></div>
          <div class="form-field"><label for="anexo-descricao">Descrição (opcional)</label><input id="anexo-descricao" name="descricao" maxlength="200"></div>
          <div class="form-field"><label for="anexo-arquivos">Arquivos (até 10, 5 MB cada)</label><input id="anexo-arquivos" type="file" name="arquivos[]" multiple required accept="image/jpeg,image/png,image/webp,application/pdf"></div>
          <div class="form-field span-3"><span class="form-dica">Fotos: JPG, PNG ou WEBP. PDF só no tipo "Documento". Arquivos anexados não são apagados: ficam como registro da OS.</span>
            <div><button class="btn btn--ghost btn--sm"><i class="bi bi-paperclip"></i> Anexar</button></div></div>
        </form>
      <?php endif; ?>
    </div>

    <!-- Histórico -->
    <div class="card">
      <h2 class="card__titulo">Histórico</h2>
      <?php foreach ($historico as $h): ?>
        <div class="linha-os">
          <div class="linha-os__topo">
            <span><strong><?= e($acoesHistorico[$h['acao']] ?? $h['acao']) ?></strong>
              <?php if ($h['estado_novo']): ?> · <?= $h['estado_anterior'] ? e(os_status($h['estado_anterior'])[0]) . ' → ' : '' ?><?= e(os_status($h['estado_novo'])[0]) ?><?php endif; ?>
              <?php if (in_array($h['acao'], ['ITEM_INCLUIDO', 'ITEM_ADICIONAL', 'ITEM_REMOVIDO'], true) && $h['dados']): $d = json_decode($h['dados'], true); ?>
                · <?= e($d['descricao'] ?? '') ?> (<?= dinheiro($d['valor_total'] ?? 0) ?>)
              <?php endif; ?>
            </span>
            <span class="form-dica"><?= data_br($h['criado_em'], true) ?><?= $h['autor'] ? ' · ' . e($h['autor']) : '' ?></span>
          </div>
          <?php if ($h['motivo']): ?><div class="form-dica"><?= e($h['motivo']) ?></div><?php endif; ?>
          <?php if ($h['acao'] === 'APROVACAO' && $h['dados']): $d = json_decode($h['dados'], true); ?>
            <details><summary class="form-dica">Ver o que foi aprovado (<?= dinheiro($d['valor_aprovado'] ?? 0) ?>)</summary>
              <ul><?php foreach ($d['itens'] ?? [] as $it): ?><li><?= e($it['descricao']) ?> — <?= $qtd($it['quantidade']) ?> × <?= dinheiro($it['valor_unitario']) ?><?= (float) $it['desconto'] > 0 ? ' − ' . dinheiro($it['desconto']) : '' ?> = <?= dinheiro($it['valor_total']) ?></li><?php endforeach; ?></ul>
            </details>
          <?php endif; ?>
          <?php if ($h['acao'] === 'ORCAMENTO_VERSAO' && $h['dados']): $d = json_decode($h['dados'], true); ?>
            <details><summary class="form-dica">Ver o orçamento original<?= isset($d['orcamento']['numero']) ? ' (' . e($d['orcamento']['numero']) . ')' : '' ?></summary>
              <ul><?php foreach ($d['itens'] ?? [] as $it): ?><li><?= e($it['nome'] ?? $it['descricao'] ?? '') ?> — <?= $qtd($it['quantidade'] ?? 1) ?> × <?= dinheiro($it['preco_unitario'] ?? $it['valor_unitario'] ?? 0) ?></li><?php endforeach; ?></ul>
              <?php if (isset($d['orcamento']['valor_total_orcamento'])): ?><span class="form-dica">Total do orçamento: <?= dinheiro($d['orcamento']['valor_total_orcamento']) ?></span><?php endif; ?>
            </details>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div>
  <!-- Pagamento -->
  <?php
    $cobravel = in_array($st, ['APROVADA', 'EM_EXECUCAO', 'AGUARDANDO_PECA', 'PRONTA', 'ENTREGUE'], true);
    $recebido = array_sum(array_map(fn($c) => $c['status'] === 'CANCELADO' ? 0 : (float) $c['valor_pago'], $contas));
    $aReceber = round((float) $os['valor_total'] - $recebido, 2);
    $coresConta = ['ABERTO' => 'info', 'PARCIAL' => 'warning', 'PAGO' => 'success', 'CANCELADO' => 'neutral'];
    $nomesConta = ['ABERTO' => 'Em aberto', 'PARCIAL' => 'Parcial', 'PAGO' => 'Recebido', 'CANCELADO' => 'Cancelado'];
  ?>
  <?php if ($cobravel || $contas): ?>
    <div class="card" style="margin-bottom:16px">
      <h2 class="card__titulo">Pagamento <?php if ($st !== 'CANCELADA'): ?><span class="badge badge--<?= $aReceber <= 0.004 ? 'success' : ($recebido > 0 ? 'warning' : 'neutral') ?>"><?= $aReceber <= 0.004 ? 'Quitada' : 'Falta ' . dinheiro($aReceber) ?></span><?php endif; ?></h2>
      <?php foreach ($contas as $c): $saldoC = round((float) $c['devido'] - (float) $c['valor_pago'], 2); ?>
        <div class="linha-os"><div class="linha-os__topo">
          <span><?= $c['parcela_total'] > 1 ? (int) $c['parcela_numero'] . '/' . (int) $c['parcela_total'] . ' · ' : '' ?><?= data_br($c['vencimento']) ?> · <?= dinheiro($c['devido']) ?>
            <span class="badge badge--<?= $coresConta[$c['status']] ?>"><?= $nomesConta[$c['status']] ?></span></span>
          <?php if (in_array($c['status'], ['ABERTO', 'PARCIAL'], true)): ?>
            <button type="button" class="btn btn--primary btn--sm" data-baixar="dlg-receber" data-conta="<?= (int) $c['id'] ?>" data-saldo="<?= e(number_format($saldoC, 2, ',', '.')) ?>" data-forma="<?= e($c['forma_prevista'] ?? '') ?>" data-rotulo="Saldo desta parcela: <?= e(dinheiro($saldoC)) ?>">Receber</button>
          <?php endif; ?>
        </div><?php if ((float) $c['valor_pago'] > 0 && $c['status'] !== 'PAGO'): ?><div class="form-dica">Recebido <?= dinheiro($c['valor_pago']) ?></div><?php endif; ?></div>
      <?php endforeach; ?>
      <?php if ($cobravel && $faltaLancar > 0.004): ?>
        <form method="post" action="<?= $acaoUrl ?>" class="form-row-3" style="margin-top:12px;align-items:end">
          <?= csrf_field() ?><input type="hidden" name="acao" value="cobrar">
          <div class="form-field"><label>Parcelas</label><select name="parcelas"><?php for ($i = 1; $i <= 12; $i++): ?><option value="<?= $i ?>"><?= $i === 1 ? 'À vista' : $i . 'x de ' . dinheiro($faltaLancar / $i) ?></option><?php endfor; ?></select></div>
          <div class="form-field"><label>1º vencimento</label><input type="date" name="vencimento" value="<?= date('Y-m-d') ?>"></div>
          <div class="form-field"><label>Forma</label><select name="forma"><?php foreach (Lancamento::FORMAS as $k => $r): ?><option value="<?= $k ?>" <?= $k === 'PIX' ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></div>
          <div class="form-field span-3"><button class="btn btn--ghost btn--sm"><i class="bi bi-receipt"></i> Lançar cobrança de <?= dinheiro($faltaLancar) ?></button></div>
        </form>
      <?php elseif ($faltaLancar < -0.004): ?>
        <p class="form-dica" style="margin-top:8px">O lançado é maior que o total atual da OS (<?= dinheiro(-$faltaLancar) ?> a mais). Ajuste no financeiro.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Dados -->
  <div class="card">
    <h2 class="card__titulo">Dados da OS <?php if (!in_array($st, ['ENTREGUE', 'CANCELADA'], true)): ?><a href="#" data-abrir="dlg-dados">Editar</a><?php endif; ?></h2>
    <dl class="dados" style="grid-template-columns:1fr">
      <div><dt>Cliente</dt><dd><a href="./cliente?id=<?= (int) $os['cliente_id'] ?>"><?= e($os['cliente']) ?></a>
        <?php if ($os['cliente_telefone']): ?><br><span class="form-dica"><?= e(telefone_fmt($os['cliente_telefone'])) ?>
          <?php if ($os['aceita_contato']): ?> · <a href="https://wa.me/55<?= e(so_digitos($os['cliente_telefone'])) ?>?text=<?= rawurlencode('Olá! Acompanhe o serviço do seu veículo ' . $os['placa'] . ': ' . $linkAcompanhamento) ?>" target="_blank" rel="noopener">enviar link pelo WhatsApp</a><?php endif; ?></span><?php endif; ?></dd></div>
      <div><dt>Veículo</dt><dd><a href="./veiculo?id=<?= (int) $os['veiculo_id'] ?>"><?= e($os['placa']) ?></a> · <?= e($os['modelo']) ?><?= $os['km_veiculo'] ? ' · ' . number_format((int) $os['km_veiculo'], 0, ',', '.') . ' km' : '' ?></dd></div>
      <div><dt>Técnico responsável</dt><dd><?= e($os['tecnico'] ?? '—') ?></dd></div>
      <div><dt>Previsão de entrega</dt><dd><?= data_br($os['previsao_conclusao'], true) ?></dd></div>
      <?php if ($st === 'ORCAMENTO'): ?><div><dt>Orçamento válido até</dt><dd><?= data_br($os['validade_orcamento']) ?></dd></div><?php endif; ?>
      <?php if ($os['aprovado_em']): ?><div><dt>Aprovação</dt><dd><?= data_br($os['aprovado_em'], true) ?><?= $os['aprovado_por_nome'] ? ' · ' . e($os['aprovado_por_nome']) : '' ?>
        <?php if ($os['assinatura_cliente']): ?><br><img src="<?= e($os['assinatura_cliente']) ?>" alt="Assinatura do cliente" style="max-width:100%;height:60px;object-fit:contain;background:#fff;border-radius:6px"><?php endif; ?></dd></div><?php endif; ?>
      <?php if ($os['entregue_em']): ?><div><dt>Entregue em</dt><dd><?= data_br($os['entregue_em'], true) ?></dd></div><?php endif; ?>
      <div><dt>Relato do cliente</dt><dd style="white-space:pre-line"><?= e($os['defeito_relatado'] ?: '—') ?></dd></div>
      <div><dt>Diagnóstico</dt><dd style="white-space:pre-line"><?= e($os['diagnostico'] ?: '—') ?></dd></div>
      <?php if ($os['observacoes']): ?><div><dt>Observações (saem na impressão)</dt><dd style="white-space:pre-line"><?= e($os['observacoes']) ?></dd></div><?php endif; ?>
      <?php if ($os['observacoes_internas']): ?><div><dt>Anotações internas</dt><dd style="white-space:pre-line"><?= e($os['observacoes_internas']) ?></dd></div><?php endif; ?>
      <?php if ($os['checklist_entrega']): ?><div><dt>Checklist de entrega</dt><dd style="white-space:pre-line"><?= e($os['checklist_entrega']) ?></dd></div><?php endif; ?>
    </dl>
  </div>
  </div>
</div>

<?php $dlgId = 'dlg-receber'; $acao = $acaoUrl; $extras = ['acao' => 'receber']; $tipo = 'RECEBER'; require V2_RAIZ . '/app/views/financeiro/_baixa.php'; ?>

<!-- Diálogos -->
<dialog id="dlg-dados" class="dlg">
  <form method="post" action="./os-dados?id=<?= (int) $os['id'] ?>">
    <?= csrf_field() ?>
    <h3>Dados da OS</h3>
    <div class="form-row-2">
      <div class="form-field"><label>Quilometragem</label><input name="km_veiculo" value="<?= e($os['km_veiculo']) ?>" inputmode="numeric"></div>
      <div class="form-field"><label>Técnico responsável</label>
        <select name="tecnico_id"><option value="">—</option><?php foreach ($tecnicos as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) $os['tecnico_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?></option><?php endforeach; ?></select></div>
      <div class="form-field"><label>Previsão de entrega</label><input type="datetime-local" name="previsao_conclusao" value="<?= $os['previsao_conclusao'] ? date('Y-m-d\TH:i', strtotime($os['previsao_conclusao'])) : '' ?>"></div>
      <div class="form-field"><label>Orçamento válido até</label><input type="date" name="validade_orcamento" value="<?= e($os['validade_orcamento']) ?>"></div>
    </div>
    <div class="form-field"><label>Relato do cliente</label><textarea name="defeito_relatado" rows="2"><?= e($os['defeito_relatado']) ?></textarea></div>
    <div class="form-field"><label>Diagnóstico</label><textarea name="diagnostico" rows="2"><?= e($os['diagnostico']) ?></textarea></div>
    <div class="form-field"><label>Observações (saem na impressão)</label><textarea name="observacoes" rows="2"><?= e($os['observacoes']) ?></textarea></div>
    <div class="form-field"><label>Anotações internas</label><textarea name="observacoes_internas" rows="2"><?= e($os['observacoes_internas']) ?></textarea></div>
    <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Cancelar</button><button class="btn btn--primary">Salvar</button></div>
  </form>
</dialog>

<dialog id="dlg-aprovar" class="dlg">
  <form method="post" action="<?= $acaoUrl ?>" id="form-aprovar">
    <?= csrf_field() ?><input type="hidden" name="acao" value="aprovar"><input type="hidden" name="assinatura" id="assinatura">
    <h3>Aprovar orçamento de <?= dinheiro($os['valor_total']) ?></h3>
    <p class="form-dica">Os itens e o valor de agora ficam guardados como o que o cliente aprovou.</p>
    <div class="form-field"><label>Aprovado por</label><input name="aprovado_por" value="<?= e($os['cliente']) ?>" maxlength="120"></div>
    <div class="form-field"><label>Assinatura do cliente (opcional)</label><canvas id="canvas-assinatura" style="width:100%;height:120px;border:1.5px dashed var(--color-border);border-radius:8px;background:#fff;touch-action:none"></canvas>
      <a href="#" id="limpar-assinatura" class="form-dica">Limpar</a></div>
    <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Cancelar</button><button class="btn btn--primary">Aprovar</button></div>
  </form>
</dialog>

<?php foreach (['recusar' => ['Orçamento recusado', 'Motivo (opcional)', false, 'Registrar recusa'],
                'cancelar' => ['Cancelar OS', 'Motivo do cancelamento', true, 'Cancelar OS'],
                'retomar' => ['Voltar para execução', 'O que precisa ser refeito?', false, 'Voltar para execução']] as $acao => [$tit, $rot, $obrig, $btn]): ?>
  <dialog id="dlg-<?= $acao ?>" class="dlg">
    <form method="post" action="<?= $acaoUrl ?>">
      <?= csrf_field() ?><input type="hidden" name="acao" value="<?= $acao ?>">
      <h3><?= $tit ?></h3>
      <?php if ($acao === 'cancelar'): ?><p class="form-dica">Peças já baixadas voltam para o estoque.</p><?php endif; ?>
      <div class="form-field"><label><?= $rot ?></label><textarea name="motivo" rows="3" <?= $obrig ? 'required' : '' ?>></textarea></div>
      <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Voltar</button><button class="btn <?= $acao === 'cancelar' ? 'btn--danger' : 'btn--primary' ?>"><?= $btn ?></button></div>
    </form>
  </dialog>
<?php endforeach; ?>

<dialog id="dlg-entregar" class="dlg">
  <form method="post" action="<?= $acaoUrl ?>">
    <?= csrf_field() ?><input type="hidden" name="acao" value="entregar">
    <h3>Entregar veículo</h3>
    <?php if ($aReceber > 0.004): ?><p class="alerta alerta--aviso" style="margin:8px 0">Ainda falta receber <?= dinheiro($aReceber) ?> desta OS.</p><?php endif; ?>
    <div class="form-field"><label>Checklist / observações da entrega</label><textarea name="checklist_entrega" rows="4" placeholder="Ex.: veículo lavado, peças antigas entregues ao cliente"><?= e($os['checklist_entrega']) ?></textarea></div>
    <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Voltar</button><button class="btn btn--primary">Confirmar entrega</button></div>
  </form>
</dialog>

<dialog id="dlg-item" class="dlg">
  <form method="post" action="./os-item?id=<?= (int) $os['id'] ?>">
    <?= csrf_field() ?><input type="hidden" name="operacao" value="alterar"><input type="hidden" name="item_id">
    <h3>Alterar item</h3>
    <?php if ($noOrcamento): ?>
      <div class="form-field"><label>Descrição</label><input name="descricao" maxlength="200"></div>
      <div class="form-row-3">
        <div class="form-field"><label>Qtd</label><input name="quantidade" inputmode="decimal"></div>
        <div class="form-field"><label>Unitário (R$)</label><input name="valor_unitario" inputmode="decimal"></div>
        <div class="form-field"><label>Desconto (R$)</label><input name="desconto" inputmode="decimal"></div>
      </div>
    <?php else: ?>
      <p class="form-dica">Depois da aprovação, valores e quantidades ficam como o cliente aprovou. Dá para trocar o técnico do serviço ou remover o item.</p>
    <?php endif; ?>
    <div class="form-field" data-so-servico><label>Técnico</label>
      <select name="tecnico_id"><option value="">Responsável da OS</option><?php foreach ($tecnicos as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['nome']) ?></option><?php endforeach; ?></select></div>
    <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Cancelar</button><button class="btn btn--primary">Salvar</button></div>
  </form>
</dialog>

<dialog id="dlg-remover" class="dlg">
  <form method="post" action="./os-item?id=<?= (int) $os['id'] ?>">
    <?= csrf_field() ?><input type="hidden" name="operacao" value="remover"><input type="hidden" name="item_id">
    <h3>Remover item</h3>
    <p class="form-dica">O cliente já aprovou este orçamento: a remoção fica registrada no histórico. Peça já baixada volta ao estoque.</p>
    <div class="form-field"><label>Motivo</label><textarea name="motivo" rows="2" required></textarea></div>
    <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Voltar</button><button class="btn btn--danger">Remover</button></div>
  </form>
</dialog>

<script src="<?= e(asset('js/assinatura.js')) ?>"></script>
<script>
  (function () {
    var abrir = function (id) { var d = document.getElementById(id); if (d) d.showModal(); return d; };
    document.querySelectorAll('[data-abrir]').forEach(function (b) {
      b.addEventListener('click', function (e) { e.preventDefault(); var d = abrir(b.dataset.abrir); if (b.dataset.abrir === 'dlg-aprovar' && pad) pad.redimensionar(); });
    });
    document.querySelectorAll('dialog [data-fechar]').forEach(function (b) { b.addEventListener('click', function () { b.closest('dialog').close(); }); });

    // Assinatura
    var pad = window.initAssinatura ? initAssinatura('canvas-assinatura') : null;
    var limpar = document.getElementById('limpar-assinatura');
    if (limpar && pad) limpar.addEventListener('click', function (e) { e.preventDefault(); pad.limpar(); });
    var fa = document.getElementById('form-aprovar');
    if (fa) fa.addEventListener('submit', function () { document.getElementById('assinatura').value = pad ? pad.dataUrl() : ''; });

    // Copiar link de acompanhamento
    document.querySelectorAll('[data-copiar]').forEach(function (b) {
      b.addEventListener('click', function () {
        navigator.clipboard.writeText(b.dataset.copiar).then(function () { var t = b.innerHTML; b.textContent = 'Link copiado'; setTimeout(function () { b.innerHTML = t; }, 1800); });
      });
    });

    // Novo item: preço do catálogo e campos conforme o tipo
    var sel = document.getElementById('produto_id');
    if (sel) {
      var valor = document.getElementById('valor_unitario'), desc = document.getElementById('descricao'), tec = document.getElementById('campo-tecnico');
      var sync = function () {
        var o = sel.options[sel.selectedIndex];
        var peca = o && o.dataset.tipo === 'PECA';
        if (o && o.dataset.preco) valor.value = o.dataset.preco;
        desc.placeholder = sel.value ? 'Opcional — usa o nome do catálogo' : 'Ex.: Troca de óleo e filtro';
        desc.required = !sel.value;
        tec.style.visibility = peca ? 'hidden' : 'visible';
      };
      sel.addEventListener('change', sync); sync();
    }

    // Alterar / remover item
    document.querySelectorAll('[data-editar-item]').forEach(function (b) {
      b.addEventListener('click', function () {
        var it = JSON.parse(b.dataset.editarItem), d = document.getElementById('dlg-item'), f = d.querySelector('form');
        ['item_id', 'descricao', 'quantidade', 'valor_unitario', 'desconto'].forEach(function (k) { if (f[k]) f[k].value = k === 'item_id' ? it.id : it[k]; });
        f.tecnico_id.value = it.tecnico_id || '';
        d.querySelector('[data-so-servico]').style.display = it.tipo === 'SERVICO' ? '' : 'none';
        d.showModal();
      });
    });
    document.querySelectorAll('[data-remover-item]').forEach(function (b) {
      b.addEventListener('click', function () { var d = document.getElementById('dlg-remover'); d.querySelector('[name=item_id]').value = b.dataset.removerItem; d.showModal(); });
    });
  })();
</script>
