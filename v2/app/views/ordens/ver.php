<?php
/**
 * Ficha da OS no padrão das fichas de consulta (.ver-*): identidade e ações no topo, o andamento em etapas,
 * os números (total, aprovado, recebido, previsão), e o trabalho no centro (relato e diagnóstico, serviços e
 * peças com a inclusão logo abaixo, fotos e documentos, histórico). Ao lado: cliente e veículo, pagamento e dados.
 * Formulários, rotas, campos e diálogos são os mesmos de antes; só a apresentação mudou.
 * Execução do técnico: o que ele informou (início, materiais usados, pedidos, relato) e, aguardando validação, os
 * botões do ADMIN para validar (aprovando ou recusando cada material) ou devolver ao técnico.
 * Espera: $os, $itens, $historico, $tecnicos, $catalogo, $contas, $faltaLancar, $caixa, $anexos, $linkAcompanhamento,
 *         $materiais, $pedidos
 */
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
    'EXECUCAO_INICIADA' => 'Técnico começou o serviço', 'EXECUCAO_ENVIADA' => 'Técnico informou que terminou', 'EXECUCAO_VALIDADA' => 'Execução validada',
    'EXECUCAO_DEVOLVIDA' => 'Devolvido ao técnico', 'MATERIAL_INFORMADO' => 'Material informado pelo técnico', 'MATERIAL_CANCELADO' => 'Material retirado pelo técnico',
    'MATERIAIS_VALIDADOS' => 'Materiais avaliados', 'PEDIDO_MATERIAL' => 'Pedido de material',
];
$ehAdmin = Contexto::perfil() === 'ADMIN';
$pendentesTec = array_values(array_filter($materiais, fn($m) => $m['status'] === 'PENDENTE'));
// Peças já previstas na OS (para avisar, na validação, quando o técnico informa a mesma peça: pode ser a mesma, já baixada).
$previstas = [];
foreach ($ativos as $i) { if ($i['tipo'] === 'PECA' && $i['produto_id']) { $previstas[(int) $i['produto_id']] = ($previstas[(int) $i['produto_id']] ?? 0) + (float) $i['quantidade']; } }
$temExecucao = $os['execucao_iniciada_em'] || $os['execucao_enviada_em'] || $materiais || $pedidos;
$rotMaterial = ['PENDENTE' => ['A avaliar', 'warning'], 'APROVADO' => ['Aprovado', 'success'], 'RECUSADO' => ['Recusado', 'muted'], 'CANCELADO' => ['Retirado pelo técnico', 'muted']];
$rotPedido = ['RECEBIDA' => 'Recebida', 'APROVADA' => 'Aprovada', 'ATENDIDA' => 'Atendida', 'RECUSADA' => 'Recusada', 'PENDENTE' => 'A avaliar', 'CANCELADO' => 'Retirado'];
$botao = fn(string $acao, string $rotulo, string $classe = 'btn--primary') =>
    '<form method="post" action="' . $acaoUrl . '">' . csrf_field() . '<input type="hidden" name="acao" value="' . $acao . '"><button class="btn ' . $classe . ' btn--sm">' . $rotulo . '</button></form>';

// Pagamento (mesmas contas de antes, calculadas aqui em cima porque aparecem nos números e na entrega).
$cobravel = in_array($st, ['APROVADA', 'EM_EXECUCAO', 'AGUARDANDO_PECA', 'AGUARDANDO_VALIDACAO', 'PRONTA', 'ENTREGUE'], true);
$recebido = array_sum(array_map(fn($c) => $c['status'] === 'CANCELADO' ? 0 : (float) $c['valor_pago'], $contas));
$aReceber = round((float) $os['valor_total'] - $recebido, 2);
$coresConta = ['ABERTO' => 'neutral', 'PARCIAL' => 'info', 'PAGO' => 'success', 'CANCELADO' => 'muted'];
$nomesConta = ['ABERTO' => 'Em aberto', 'PARCIAL' => 'Parcial', 'PAGO' => 'Recebido', 'CANCELADO' => 'Cancelado'];

// Andamento: só a leitura da situação atual, em etapas (a troca continua nos botões de sempre).
$etapas = ['ORCAMENTO' => 'Orçamento', 'APROVADA' => 'Aprovada', 'EM_EXECUCAO' => 'Em execução', 'PRONTA' => 'Pronta', 'ENTREGUE' => 'Entregue'];
$posicao = array_search(in_array($st, ['AGUARDANDO_PECA', 'AGUARDANDO_VALIDACAO'], true) ? 'EM_EXECUCAO' : $st, array_keys($etapas), true);
$diferenca = $os['valor_aprovado'] !== null ? round((float) $os['valor_total'] - (float) $os['valor_aprovado'], 2) : 0.0;
$iniciais = function (string $nome): string { $p = preg_split('/\s+/', trim($nome)); return mb_strtoupper(mb_substr($p[0], 0, 1) . (count($p) > 1 ? mb_substr(end($p), 0, 1) : '')); };
?>
<header class="ver-topo">
  <div class="ver-topo__id">
    <span class="ver-topo__avatar ver-topo__avatar--quadrado" aria-hidden="true"><?= icone('work-order', 30, 1.5) ?></span>
    <div class="ver-topo__texto">
      <p class="ver-topo__voltar"><a href="./ordens"><?= icone('chevron-left', 14, 2) ?> Ordens de serviço</a></p>
      <h1><?= os_numero($os['numero']) ?></h1>
      <p class="ver-topo__meta">
        <span class="badge badge--<?= $corStatus ?>"><?= $rotuloStatus ?></span>
        <a class="ver-chip" href="./cliente?id=<?= (int) $os['cliente_id'] ?>"><?= icone('user', 13) ?> <?= e($os['cliente']) ?></a>
        <a class="ver-chip ver-chip--mono" href="./veiculo?id=<?= (int) $os['veiculo_id'] ?>"><?= icone('car', 13) ?> <?= e($os['placa']) ?><?= $os['modelo'] ? ' · ' . e($os['modelo']) : '' ?></a>
        <span class="ver-chip ver-chip--mono"><?= icone('calendar', 13) ?> Aberta em <?= data_br($os['criado_em'], true) ?></span>
      </p>
    </div>
  </div>
  <div class="ver-topo__acoes">
    <a class="btn btn--ghost btn--sm" href="./os-imprimir?id=<?= (int) $os['id'] ?>" target="_blank"><?= icone('print') ?> Imprimir</a>
    <?php if ($st === 'ORCAMENTO'): ?>
      <button type="button" class="btn btn--ghost btn--sm" data-abrir="dlg-recusar">Recusado</button>
      <button type="button" class="btn btn--primary btn--sm" data-abrir="dlg-aprovar"><?= icone('check') ?> Aprovar orçamento</button>
    <?php elseif ($st === 'RECUSADA'): ?>
      <?= $botao('reabrir', 'Reabrir orçamento') ?>
    <?php elseif ($st === 'APROVADA'): ?>
      <?= $botao('iniciar', icone('arrow-right') . ' Iniciar execução') ?>
    <?php elseif ($st === 'AGUARDANDO_PECA'): ?>
      <?= $botao('baixar', icone('download') . ' Peças chegaram') ?>
    <?php elseif ($st === 'EM_EXECUCAO'): ?>
      <?= $botao('concluir', icone('success') . ' Concluir serviço') ?>
    <?php elseif ($st === 'AGUARDANDO_VALIDACAO' && $ehAdmin): ?>
      <button type="button" class="btn btn--ghost btn--sm" data-abrir="dlg-devolver">Devolver ao técnico</button>
      <button type="button" class="btn btn--primary btn--sm" data-abrir="dlg-validar"><?= icone('checklist') ?> Validar execução</button>
    <?php elseif ($st === 'PRONTA'): ?>
      <button type="button" class="btn btn--ghost btn--sm" data-abrir="dlg-retomar">Voltar para execução</button>
      <button type="button" class="btn btn--primary btn--sm" data-abrir="dlg-entregar"><?= icone('key') ?> Entregar veículo</button>
    <?php endif; ?>
    <details class="menu-mais">
      <summary class="btn btn--ghost btn--sm btn--icone" aria-label="Mais ações"><?= icone('more-h') ?></summary>
      <div class="menu-mais__lista">
        <button type="button" data-copiar="<?= e($linkAcompanhamento) ?>"><?= icone('link') ?> Copiar link do cliente</button>
        <?php if (!in_array($st, ['ENTREGUE', 'CANCELADA'], true)): ?><button type="button" data-abrir="dlg-dados"><?= icone('edit') ?> Editar dados da OS</button><?php endif; ?>
        <?php if ($cancelavel): ?><button type="button" data-abrir="dlg-cancelar"><?= icone('cancel') ?> Cancelar OS</button><?php endif; ?>
      </div>
    </details>
  </div>
</header>

<?php if ($st === 'RECUSADA' && $os['motivo_recusa']): ?>
  <?php $alertaTipo = 'aviso'; $alertaTitulo = 'Orçamento recusado'; $alertaMensagem = $os['motivo_recusa']; require V2_RAIZ . '/app/views/layout/_alerta.php'; ?>
<?php elseif ($st === 'CANCELADA'): ?>
  <?php $alertaTipo = 'erro'; $alertaTitulo = 'OS cancelada em ' . data_br($os['cancelado_em'], true); $alertaMensagem = $os['motivo_cancelamento'] ?? ''; require V2_RAIZ . '/app/views/layout/_alerta.php'; ?>
<?php endif; ?>

<?php if ($st === 'AGUARDANDO_VALIDACAO'): ?>
  <?php $alertaTipo = 'info'; $alertaTitulo = ($os['tecnico'] ?: 'O técnico') . ' informou que terminou em ' . data_br($os['execucao_enviada_em'], true);
        $alertaMensagem = $ehAdmin ? 'Confira o relato e os materiais em "Execução do técnico" e valide ou devolva.' : 'Aguardando a validação do administrador.';
        require V2_RAIZ . '/app/views/layout/_alerta.php'; ?>
<?php elseif (in_array($st, ['EM_EXECUCAO', 'AGUARDANDO_PECA'], true) && $os['execucao_devolvida_em'] && $os['execucao_devolvida_em'] > ($os['execucao_enviada_em'] ?? '')): ?>
  <?php $alertaTipo = 'aviso'; $alertaTitulo = 'Devolvida ao técnico em ' . data_br($os['execucao_devolvida_em'], true); $alertaMensagem = (string) $os['execucao_devolucao_motivo'];
        require V2_RAIZ . '/app/views/layout/_alerta.php'; ?>
<?php endif; ?>

<?php if ($posicao !== false): ?>
  <ol class="os-etapas" aria-label="Andamento da OS">
    <?php $n = 0; foreach ($etapas as $chave => $rotulo): $estado = $n < $posicao ? 'is-feita' : ($n === $posicao ? 'is-atual' : ''); ?>
      <li class="os-etapas__item <?= $estado ?>" <?= $n === $posicao ? 'aria-current="step"' : '' ?>>
        <span class="os-etapas__marca" aria-hidden="true"><?= $n < $posicao ? icone('check', 12, 2.5) : '' ?></span>
        <span class="os-etapas__rotulo"><?= $n === $posicao && $st === 'AGUARDANDO_PECA' ? 'Aguardando peça' : ($n === $posicao && $st === 'AGUARDANDO_VALIDACAO' ? 'Em validação' : $rotulo) ?></span>
      </li>
    <?php $n++; endforeach; ?>
  </ol>
<?php endif; ?>

<div class="ver-numeros" aria-label="Valores da OS">
  <div>
    <span>Total da OS</span><strong><?= dinheiro($os['valor_total']) ?></strong>
    <small>Peças <?= dinheiro($os['valor_pecas']) ?> · serviços <?= dinheiro($os['valor_servicos']) ?></small>
  </div>
  <div>
    <span>Aprovado pelo cliente</span><strong><?= $os['valor_aprovado'] !== null ? dinheiro($os['valor_aprovado']) : '—' ?></strong>
    <small><?php if ($os['valor_aprovado'] === null): ?><?= $os['aprovado_em'] ? 'aprovado em ' . data_br($os['aprovado_em']) : ($noOrcamento ? 'aguardando aprovação' : 'sem aprovação registrada') ?><?php elseif (abs($diferenca) > 0.004): ?>adicional <?= $diferenca > 0 ? '+' : '−' ?><?= dinheiro(abs($diferenca)) ?><?php else: ?>em <?= data_br($os['aprovado_em']) ?><?php endif; ?></small>
  </div>
  <div class="<?= $cobravel && $aReceber > 0.004 && $st === 'ENTREGUE' ? 'ver-numeros__alerta' : '' ?>">
    <span>Recebido</span><strong><?= dinheiro($recebido) ?></strong>
    <small><?php if (!$cobravel && !$contas): ?>cobrança depois da aprovação<?php elseif ($aReceber > 0.004): ?>falta <?= dinheiro($aReceber) ?><?php else: ?>quitada<?php endif; ?></small>
  </div>
  <div>
    <span>Previsão de entrega</span><strong class="ver-numeros__data"><?= data_br($os['previsao_conclusao']) ?></strong>
    <small><?php if ($os['entregue_em']): ?>entregue em <?= data_br($os['entregue_em']) ?><?php elseif ($noOrcamento && $os['validade_orcamento']): ?>orçamento válido até <?= data_br($os['validade_orcamento']) ?><?php elseif ($os['previsao_conclusao']): ?>às <?= date('H:i', strtotime($os['previsao_conclusao'])) ?><?php else: ?>não definida<?php endif; ?></small>
  </div>
</div>

<div class="ver-grade">
  <div class="ver-principal">
    <!-- Relato e diagnóstico -->
    <section class="card ver-dados" aria-label="Relato e diagnóstico">
      <div class="ver-grupo">
        <h2 class="ver-grupo__titulo"><?= icone('message', 15) ?> Relato do cliente</h2>
        <?php if ($os['defeito_relatado']): ?><p class="ver-texto"><?= e($os['defeito_relatado']) ?></p><?php else: ?><p class="secundario ver-vazio">Não informado.</p><?php endif; ?>
      </div>
      <div class="ver-grupo">
        <h2 class="ver-grupo__titulo"><?= icone('mechanic', 15) ?> Diagnóstico</h2>
        <?php if ($os['diagnostico']): ?><p class="ver-texto"><?= e($os['diagnostico']) ?></p><?php else: ?><p class="secundario ver-vazio">Ainda sem diagnóstico.</p><?php endif; ?>
      </div>
      <?php if ($os['observacoes'] || $os['observacoes_internas'] || $os['checklist_entrega']): ?>
        <div class="ver-obs os-notas">
          <?php if ($os['observacoes']): ?><div><h3 class="ver-grupo__titulo"><?= icone('print', 15) ?> Observações <span class="os-notas__dica">saem na impressão</span></h3><p><?= e($os['observacoes']) ?></p></div><?php endif; ?>
          <?php if ($os['observacoes_internas']): ?><div><h3 class="ver-grupo__titulo"><?= icone('lock', 15) ?> Anotações internas</h3><p><?= e($os['observacoes_internas']) ?></p></div><?php endif; ?>
          <?php if ($os['checklist_entrega']): ?><div><h3 class="ver-grupo__titulo"><?= icone('checklist', 15) ?> Checklist de entrega</h3><p><?= e($os['checklist_entrega']) ?></p></div><?php endif; ?>
        </div>
      <?php endif; ?>
    </section>

    <!-- Serviços e peças -->
    <section class="card card--tabela ver-historico os-itens" aria-labelledby="os-itens-titulo">
      <header class="ver-historico__cabeca">
        <div>
          <h2 id="os-itens-titulo">Serviços e peças</h2>
          <p><?= $ativos ? count($ativos) . ' ' . (count($ativos) === 1 ? 'item' : 'itens') . ($noOrcamento ? ' no orçamento.' : '.') : 'Nenhum item ainda.' ?>
            <?php if (abs($diferenca) > 0.004): ?> Aprovado <?= dinheiro($os['valor_aprovado']) ?> · diferença <?= dinheiro($diferenca) ?>.<?php endif; ?></p>
        </div>
      </header>
      <?php if (!$itens): ?>
        <div class="vazio"><?= icone('checklist', 28, 1.25) ?><?= $editavel ? 'Inclua os serviços e as peças logo abaixo.' : 'Nenhum serviço ou peça nesta OS.' ?></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table os-itens__tabela">
            <thead><tr><th>Descrição</th><th class="num">Qtd</th><th class="num">Unitário</th><th class="num">Desconto</th><th class="num">Total</th><?php if ($editavel): ?><th><span class="sr-only">Ações</span></th><?php endif; ?></tr></thead>
            <tbody>
              <?php foreach ($itens as $i): $devolvido = $i['estoque_status'] === 'DEVOLVIDO'; ?>
                <tr class="<?= $devolvido ? 'inativo' : '' ?>">
                  <td>
                    <span class="os-itens__desc">
                      <span class="os-itens__tipo" title="<?= $i['tipo'] === 'PECA' ? 'Peça' : 'Serviço' ?>" aria-hidden="true"><?= icone($i['tipo'] === 'PECA' ? 'part' : 'mechanic', 15) ?></span>
                      <span>
                        <?= $devolvido ? '<s>' . e($i['descricao']) . '</s>' : '<strong>' . e($i['descricao']) . '</strong>' ?>
                        <span class="sub">
                          <?= $i['tipo'] === 'PECA' ? 'Peça' : 'Serviço' ?>
                          <?= $i['origem'] === 'ADICIONAL' ? ' · <b>adicional</b>' : '' ?>
                          <?= $i['tecnico'] ? ' · ' . e($i['tecnico']) : '' ?>
                          <?php if ($i['tipo'] === 'PECA'): ?> · <?= ['PENDENTE' => 'a baixar do estoque', 'BAIXADO' => 'baixada do estoque', 'DEVOLVIDO' => 'removida (devolvida ao estoque)'][$i['estoque_status']] ?? '' ?><?php endif; ?>
                          <?= (float) $i['comissao_valor'] > 0 ? ' · comissão ' . dinheiro($i['comissao_valor']) : '' ?>
                        </span>
                      </span>
                    </span>
                  </td>
                  <td class="num"><?= $qtd($i['quantidade']) ?></td>
                  <td class="num"><?= dinheiro($i['valor_unitario']) ?></td>
                  <td class="num secundario"><?= (float) $i['desconto'] > 0 ? '−' . dinheiro($i['desconto']) : '—' ?></td>
                  <td class="num"><strong><?= dinheiro($i['valor_total']) ?></strong></td>
                  <?php if ($editavel): ?>
                    <td class="num os-itens__acoes">
                      <?php if (!$devolvido): ?>
                        <button type="button" class="btn btn--ghost btn--sm btn--icone" title="Alterar" aria-label="Alterar item" data-editar-item='<?= e(json_encode(['id' => (int) $i['id'], 'tipo' => $i['tipo'], 'descricao' => $i['descricao'], 'quantidade' => $qtd($i['quantidade']), 'valor_unitario' => number_format((float) $i['valor_unitario'], 2, ',', '.'), 'desconto' => number_format((float) $i['desconto'], 2, ',', '.'), 'tecnico_id' => (int) $i['tecnico_id']], JSON_UNESCAPED_UNICODE)) ?>'><?= icone('edit') ?></button>
                        <?php if ($noOrcamento): ?>
                          <form method="post" action="./os-item?id=<?= (int) $os['id'] ?>" data-confirmar="Remover este item do orçamento?">
                            <?= csrf_field() ?><input type="hidden" name="operacao" value="remover"><input type="hidden" name="item_id" value="<?= (int) $i['id'] ?>">
                            <button class="btn btn--ghost btn--sm btn--icone" title="Remover" aria-label="Remover item"><?= icone('trash') ?></button>
                          </form>
                        <?php else: ?>
                          <button type="button" class="btn btn--ghost btn--sm btn--icone" title="Remover" aria-label="Remover item" data-remover-item="<?= (int) $i['id'] ?>"><?= icone('trash') ?></button>
                        <?php endif; ?>
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <dl class="os-totais" aria-label="Totais">
          <div><dt>Peças</dt><dd><?= dinheiro($os['valor_pecas']) ?></dd></div>
          <div><dt>Serviços</dt><dd><?= dinheiro($os['valor_servicos']) ?></dd></div>
          <?php if ((float) $os['valor_desconto'] > 0): ?><div><dt>Descontos</dt><dd>−<?= dinheiro($os['valor_desconto']) ?></dd></div><?php endif; ?>
          <div class="os-totais__total"><dt>Total</dt><dd><?= dinheiro($os['valor_total']) ?></dd></div>
        </dl>
      <?php endif; ?>

      <?php if ($editavel): ?>
        <div class="os-incluir<?= $itens ? '' : ' is-aberto' ?>" id="os-incluir">
          <button type="button" class="os-incluir__abrir" aria-expanded="<?= $itens ? 'false' : 'true' ?>" aria-controls="form-item">
            <span class="os-incluir__mais" aria-hidden="true"><?= icone('plus', 14, 2.2) ?></span><?= $noOrcamento ? 'Incluir serviço ou peça' : 'Incluir adicional' ?>
          </button>
          <form method="post" action="./os-item?id=<?= (int) $os['id'] ?>" class="os-incluir__form" id="form-item">
            <?= csrf_field() ?><input type="hidden" name="operacao" value="incluir">
            <div class="os-incluir__topo">
              <div class="os-incluir__tipo" role="radiogroup" aria-label="Tipo do item">
                <button type="button" role="radio" aria-checked="true" data-tipo-item="SERVICO"><?= icone('mechanic', 15) ?>Serviço</button>
                <button type="button" role="radio" aria-checked="false" data-tipo-item="PECA"><?= icone('part', 15) ?>Peça</button>
              </div>
              <?php if (!$noOrcamento): ?><p class="os-incluir__nota">Entra como <strong>adicional</strong>, separado do valor aprovado.</p><?php endif; ?>
              <?php if ($itens): ?><button type="button" class="os-incluir__fechar" aria-label="Fechar"><?= icone('close', 15) ?></button><?php endif; ?>
            </div>

            <div class="os-incluir__item">
              <label class="os-campo os-campo--item"><span data-rotulo-item>Serviço</span>
                <select id="produto_id" name="produto_id">
                  <option value="" data-tipo="SERVICO">Avulso (descrever)</option>
                  <?php foreach ($catalogo as $c): if ($c['tipo'] !== 'SERVICO') continue; ?>
                    <option value="<?= (int) $c['id'] ?>" data-tipo="SERVICO" data-preco="<?= e(number_format((float) $c['preco_venda'], 2, ',', '.')) ?>"><?= e($c['nome']) ?> · <?= dinheiro($c['preco_venda']) ?></option>
                  <?php endforeach; ?>
                  <option value="" data-tipo="PECA">Escolha a peça</option>
                  <?php foreach ($catalogo as $c): if ($c['tipo'] !== 'PECA') continue; $saldo = (float) $c['estoque_atual']; ?>
                    <option value="<?= (int) $c['id'] ?>" data-tipo="PECA" data-preco="<?= e(number_format((float) $c['preco_venda'], 2, ',', '.')) ?>"><?= e($c['nome']) ?> · <?= dinheiro($c['preco_venda']) ?> · <?= $saldo > 0 ? $qtd($c['estoque_atual']) . ' em estoque' : 'sem estoque' ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="os-campo os-campo--desc" id="campo-descricao"><span>Descrição</span>
                <input id="descricao" name="descricao" maxlength="200" placeholder="Ex.: Troca de óleo e filtro">
              </label>
            </div>

            <div class="os-incluir__valores">
              <label class="os-campo os-campo--qtd"><span>Qtd</span><input id="quantidade" name="quantidade" value="1" inputmode="decimal" autocomplete="off"></label>
              <label class="os-campo os-campo--din"><span>Unitário</span><span class="os-campo__moeda"><i>R$</i><input id="valor_unitario" name="valor_unitario" inputmode="decimal" placeholder="0,00" autocomplete="off"></span></label>
              <label class="os-campo os-campo--din"><span>Desconto</span><span class="os-campo__moeda"><i>R$</i><input id="desconto" name="desconto" inputmode="decimal" placeholder="0,00" autocomplete="off"></span></label>
              <label class="os-campo os-campo--tec" id="campo-tecnico"><span>Técnico</span>
                <select id="tecnico_item" name="tecnico_id"><option value="">Responsável da OS</option>
                  <?php foreach ($tecnicos as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['nome']) ?></option><?php endforeach; ?>
                </select>
              </label>
              <div class="os-incluir__fim">
                <span class="os-incluir__total" aria-live="polite"><small>Total do item</small><b data-total-item>R$ 0,00</b></span>
                <button class="btn btn--primary"><?= icone('plus') ?> Incluir</button>
              </div>
            </div>
          </form>
        </div>
      <?php endif; ?>
    </section>

    <?php if ($temExecucao): ?>
    <!-- Execução do técnico -->
    <section class="card card--tabela ver-historico" id="execucao" aria-labelledby="os-exec-titulo">
      <header class="ver-historico__cabeca">
        <div><h2 id="os-exec-titulo">Execução do técnico</h2>
          <p><?= e($os['tecnico'] ?: 'Técnico') ?><?= $os['execucao_iniciada_em'] ? ' · começou em ' . data_br($os['execucao_iniciada_em'], true) : '' ?><?= $os['execucao_enviada_em'] ? ' · informou que terminou em ' . data_br($os['execucao_enviada_em'], true) : '' ?><?= $os['conferido_em'] ? ' · validada em ' . data_br($os['conferido_em'], true) : '' ?></p></div>
      </header>
      <div class="exec-admin">
        <?php if ($os['execucao_observacao']): ?>
          <div class="exec-admin__relato"><h3 class="ver-grupo__titulo"><?= icone('message', 15) ?> Relato do técnico</h3><p class="ver-texto"><?= e($os['execucao_observacao']) ?></p></div>
        <?php endif; ?>
        <h3 class="ver-grupo__titulo"><?= icone('part', 15) ?> Materiais que o técnico informou</h3>
        <?php if ($materiais): ?>
          <ul class="exec-admin__lista">
            <?php foreach ($materiais as $m): [$rm, $cm] = $rotMaterial[$m['status']]; ?>
              <li>
                <span><strong><?= e($m['nome']) ?></strong> · <?= $qtd($m['quantidade']) ?> un.<?= isset($previstas[(int) $m['produto_id']]) && $m['status'] === 'PENDENTE' ? ' <span class="exec-admin__nota">já prevista na OS (' . $qtd($previstas[(int) $m['produto_id']]) . ')</span>' : '' ?>
                  <small><?= e($m['tecnico_nome']) ?> · <?= data_br($m['criado_em'], true) ?><?= $m['observacao'] ? ' · “' . e($m['observacao']) . '”' : '' ?><?= $m['motivo_decisao'] && $m['status'] === 'RECUSADO' ? ' · recusado: ' . e($m['motivo_decisao']) : '' ?></small></span>
                <span class="badge badge--<?= $cm ?>"><?= $rm ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php if ($pendentesTec): ?><p class="form-dica">Os materiais a avaliar ainda não saíram do estoque. Os aprovados na validação entram na OS como adicionais e são baixados uma única vez.</p><?php endif; ?>
        <?php else: ?>
          <p class="secundario ver-vazio">Nenhum material informado.</p>
        <?php endif; ?>
        <?php if ($pedidos): ?>
          <h3 class="ver-grupo__titulo"><?= icone('box', 15) ?> Pedidos do técnico ligados a esta OS</h3>
          <ul class="exec-admin__lista">
            <?php foreach ($pedidos as $pd): ?>
              <li>
                <span><strong><?= e(Solicitacao::TIPOS[$pd['tipo']][0]) ?><?= ($pd['produto'] ?? $pd['item_descricao']) ? ': ' . e($pd['produto'] ?? $pd['item_descricao']) : '' ?><?= $pd['quantidade'] ? ' · ' . $qtd($pd['quantidade']) . ' un.' : '' ?></strong>
                  <small><?= e($pd['tecnico']) ?> · <?= data_br($pd['criado_em'], true) ?> · “<?= e($pd['justificativa']) ?>”<?= $pd['resposta'] ? ' · resposta: ' . e($pd['resposta']) : '' ?></small></span>
                <a class="badge badge--<?= Solicitacao::SITUACOES[$pd['status']][1] ?>" href="./solicitacoes?situacao=todas"><?= $rotPedido[$pd['status']] ?></a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- Fotos e documentos -->
    <section class="card card--tabela ver-historico" id="arquivos" aria-labelledby="os-arquivos-titulo">
      <header class="ver-historico__cabeca">
        <div><h2 id="os-arquivos-titulo">Fotos e documentos</h2><p><?= count($anexos) ?> de <?= Anexo::MAX_POR_DONO ?> arquivos. Ficam como registro da OS e não são apagados.</p></div>
      </header>
      <div class="os-arquivos">
        <?php if ($anexos): ?>
          <div class="anexos">
            <?php foreach ($anexos as $a): $img = Anexo::ehImagem($a); ?>
              <a class="anexo" href="./anexo?id=<?= (int) $a['id'] ?>" target="_blank" rel="noopener" title="<?= e($a['nome_original']) ?>">
                <?php if ($img): ?><img src="./anexo?id=<?= (int) $a['id'] ?>" alt="<?= e($a['descricao'] ?: $a['nome_original']) ?>" loading="lazy"><?php else: ?><span class="anexo__doc"><?= icone('file') ?></span><?php endif; ?>
                <span class="anexo__legenda"><strong><?= e(Anexo::CATEGORIAS_OS[$a['categoria']] ?? $a['categoria']) ?></strong><?= $a['descricao'] ? ' · ' . e($a['descricao']) : '' ?><br><span class="form-dica"><?= data_br($a['enviado_em'], true) ?><?= $a['autor'] ? ' · ' . e($a['autor']) : '' ?></span></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="ver-lado__vazio"><?= icone('image', 24, 1.25) ?>Nenhum arquivo. Fotos do antes e depois ajudam a mostrar ao cliente o que foi feito.</div>
        <?php endif; ?>
        <?php if ($st !== 'CANCELADA' && count($anexos) < Anexo::MAX_POR_DONO): ?>
          <form method="post" action="./os-anexar?os=<?= (int) $os['id'] ?>" enctype="multipart/form-data" class="os-anexar">
            <?= csrf_field() ?>
            <div class="form-field"><label for="anexo-categoria">Tipo</label>
              <select id="anexo-categoria" name="categoria"><?php foreach (Anexo::CATEGORIAS_OS as $k => $r): ?><option value="<?= $k ?>"><?= e($r) ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label for="anexo-descricao">Descrição (opcional)</label><input id="anexo-descricao" name="descricao" maxlength="200"></div>
            <div class="form-field"><label for="anexo-arquivos">Arquivos (até 10, 5 MB cada)</label><input id="anexo-arquivos" type="file" name="arquivos[]" multiple required accept="image/jpeg,image/png,image/webp,application/pdf"></div>
            <div class="os-anexar__pe"><span class="form-dica">Fotos: JPG, PNG ou WEBP. PDF só no tipo "Documento".</span>
              <button class="btn btn--ghost btn--sm"><?= icone('attach') ?> Anexar</button></div>
          </form>
        <?php endif; ?>
      </div>
    </section>

    <!-- Histórico -->
    <section class="card card--tabela ver-historico" aria-labelledby="os-hist-titulo">
      <header class="ver-historico__cabeca">
        <div><h2 id="os-hist-titulo">Histórico</h2><p>Tudo o que aconteceu com esta OS, do mais recente para o mais antigo.</p></div>
      </header>
      <ol class="conta-linha-tempo os-linha-tempo">
        <?php foreach ($historico as $h): ?>
          <li>
            <strong><?= e($acoesHistorico[$h['acao']] ?? $h['acao']) ?><?php if ($h['acao'] === 'PEDIDO_MATERIAL' || str_starts_with($h['acao'], 'MATERIAL')): ?><?= $h['estado_novo'] && isset($rotPedido[$h['estado_novo']]) ? ' · ' . $rotPedido[$h['estado_novo']] : '' ?><?php elseif ($h['estado_novo']): ?> · <?= $h['estado_anterior'] ? e(os_status($h['estado_anterior'])[0]) . ' → ' : '' ?><?= e(os_status($h['estado_novo'])[0]) ?><?php endif; ?></strong>
            <?php if (in_array($h['acao'], ['ITEM_INCLUIDO', 'ITEM_ADICIONAL', 'ITEM_REMOVIDO'], true) && $h['dados']): $d = json_decode($h['dados'], true); ?>
              <span class="os-linha-tempo__texto"><?= e($d['descricao'] ?? '') ?> · <?= dinheiro($d['valor_total'] ?? 0) ?></span>
            <?php endif; ?>
            <?php if (in_array($h['acao'], ['MATERIAL_INFORMADO', 'MATERIAL_CANCELADO'], true) && $h['dados']): $d = json_decode($h['dados'], true); ?>
              <span class="os-linha-tempo__texto"><?= e($d['produto'] ?? '') ?> · <?= $qtd($d['quantidade'] ?? 0) ?> un.</span>
            <?php endif; ?>
            <?php if ($h['acao'] === 'MATERIAIS_VALIDADOS' && $h['dados']): $d = json_decode($h['dados'], true); ?>
              <span class="os-linha-tempo__texto"><?= e(implode(' · ', array_map(fn($x) => $x['material'] . ' (' . $qtd($x['quantidade']) . '): ' . ($x['decisao'] === 'APROVADO' ? 'aprovado' : 'recusado'), $d['materiais'] ?? []))) ?></span>
            <?php endif; ?>
            <?php if ($h['acao'] === 'PEDIDO_MATERIAL' && $h['dados'] && !$h['estado_anterior']): $d = json_decode($h['dados'], true); ?>
              <span class="os-linha-tempo__texto"><?= e(Solicitacao::TIPOS[$d['tipo'] ?? '']['0'] ?? '') ?><?= !empty($d['item']) ? ': ' . e($d['item']) : '' ?></span>
            <?php endif; ?>
            <?php if ($h['motivo']): ?><span class="os-linha-tempo__texto">“<?= e($h['motivo']) ?>”</span><?php endif; ?>
            <small><?= data_br($h['criado_em'], true) ?><?= $h['autor'] ? ' · ' . e($h['autor']) : '' ?></small>
            <?php if ($h['acao'] === 'APROVACAO' && $h['dados']): $d = json_decode($h['dados'], true); ?>
              <details class="os-linha-tempo__detalhe"><summary>Ver o que foi aprovado (<?= dinheiro($d['valor_aprovado'] ?? 0) ?>)</summary>
                <ul><?php foreach ($d['itens'] ?? [] as $it): ?><li><?= e($it['descricao']) ?> — <?= $qtd($it['quantidade']) ?> × <?= dinheiro($it['valor_unitario']) ?><?= (float) $it['desconto'] > 0 ? ' − ' . dinheiro($it['desconto']) : '' ?> = <?= dinheiro($it['valor_total']) ?></li><?php endforeach; ?></ul>
              </details>
            <?php endif; ?>
            <?php if ($h['acao'] === 'ORCAMENTO_VERSAO' && $h['dados']): $d = json_decode($h['dados'], true); ?>
              <details class="os-linha-tempo__detalhe"><summary>Ver o orçamento original<?= isset($d['orcamento']['numero']) ? ' (' . e($d['orcamento']['numero']) . ')' : '' ?></summary>
                <ul><?php foreach ($d['itens'] ?? [] as $it): ?><li><?= e($it['nome'] ?? $it['descricao'] ?? '') ?> — <?= $qtd($it['quantidade'] ?? 1) ?> × <?= dinheiro($it['preco_unitario'] ?? $it['valor_unitario'] ?? 0) ?></li><?php endforeach; ?></ul>
                <?php if (isset($d['orcamento']['valor_total_orcamento'])): ?><span class="form-dica">Total do orçamento: <?= dinheiro($d['orcamento']['valor_total_orcamento']) ?></span><?php endif; ?>
              </details>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  </div>

  <div class="ver-coluna">
    <!-- Cliente e veículo -->
    <aside class="card ver-lado" aria-label="Cliente e veículo">
      <header class="ver-lado__cabeca"><h2>Cliente e veículo</h2></header>
      <a class="ver-veiculo" href="./cliente?id=<?= (int) $os['cliente_id'] ?>">
        <span class="avatar avatar--g" aria-hidden="true"><?= e($iniciais($os['cliente'])) ?></span>
        <span class="ver-veiculo__texto"><strong><?= e($os['cliente']) ?></strong><small><?= $os['cliente_telefone'] ? e(telefone_fmt($os['cliente_telefone'])) : 'Ver ficha do cliente' ?></small></span>
        <?= icone('chevron-right', 14, 2) ?>
      </a>
      <a class="ver-veiculo" href="./veiculo?id=<?= (int) $os['veiculo_id'] ?>">
        <?= placa_caixa($os['placa'], false) ?>
        <span class="ver-veiculo__texto"><strong><?= e($os['modelo'] ?: 'Modelo não informado') ?></strong><small><?= $os['km_veiculo'] ? number_format((int) $os['km_veiculo'], 0, ',', '.') . ' km na entrada' : 'Km não informado' ?></small></span>
        <?= icone('chevron-right', 14, 2) ?>
      </a>
      <?php if ($os['cliente_telefone'] && $os['aceita_contato']): ?>
        <a class="btn btn--ghost btn--sm os-whats" href="https://wa.me/55<?= e(so_digitos($os['cliente_telefone'])) ?>?text=<?= rawurlencode('Olá! Acompanhe o serviço do seu veículo ' . $os['placa'] . ': ' . $linkAcompanhamento) ?>" target="_blank" rel="noopener"><?= icone('message') ?> Enviar link pelo WhatsApp</a>
      <?php endif; ?>
    </aside>

    <!-- Pagamento (só o balcão: o técnico não mexe em dinheiro) -->
    <?php $veDinheiro = Permissao::permite(Contexto::perfil(), Permissao::BALCAO); ?>
    <?php if ($veDinheiro && ($cobravel || $contas)): ?>
      <aside class="card ver-lado" aria-label="Pagamento">
        <header class="ver-lado__cabeca"><h2>Pagamento</h2>
          <?php if ($st !== 'CANCELADA'): ?><span class="badge badge--<?= $aReceber <= 0.004 ? 'success' : ($recebido > 0 ? 'info' : 'neutral') ?>"><?= $aReceber <= 0.004 ? 'Quitada' : 'Falta ' . dinheiro($aReceber) ?></span><?php endif; ?>
        </header>
        <?php if ($contas): ?>
          <ul class="conta-lista">
            <?php foreach ($contas as $c): $saldoC = round((float) $c['devido'] - (float) $c['valor_pago'], 2); ?>
              <li class="conta-lista__item os-conta">
                <span class="conta-lista__n"><?= $c['parcela_total'] > 1 ? (int) $c['parcela_numero'] . '/' . (int) $c['parcela_total'] : '1x' ?></span>
                <span class="conta-lista__texto">
                  <strong><?= dinheiro($c['devido']) ?></strong>
                  <small>vence <?= data_br($c['vencimento']) ?> · <?= $nomesConta[$c['status']] ?><?php if ((float) $c['valor_pago'] > 0 && $c['status'] !== 'PAGO'): ?> · recebido <?= dinheiro($c['valor_pago']) ?><?php endif; ?></small>
                </span>
                <?php if (in_array($c['status'], ['ABERTO', 'PARCIAL'], true)): ?>
                  <button type="button" class="btn btn--primary btn--sm" data-baixar="dlg-receber" data-conta="<?= (int) $c['id'] ?>" data-saldo="<?= e(number_format($saldoC, 2, ',', '.')) ?>" data-forma="<?= e($c['forma_prevista'] ?? '') ?>" data-rotulo="Saldo desta parcela: <?= e(dinheiro($saldoC)) ?>">Receber</button>
                <?php else: ?>
                  <span class="badge badge--<?= $coresConta[$c['status']] ?>"><?= $nomesConta[$c['status']] ?></span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php if ($cobravel && $faltaLancar > 0.004): ?>
          <form method="post" action="<?= $acaoUrl ?>" class="os-cobrar">
            <?= csrf_field() ?><input type="hidden" name="acao" value="cobrar">
            <p class="os-cobrar__titulo">Lançar cobrança de <strong><?= dinheiro($faltaLancar) ?></strong></p>
            <div class="form-field"><label for="cobrar-parcelas">Parcelas</label><select id="cobrar-parcelas" name="parcelas"><?php for ($i = 1; $i <= 12; $i++): ?><option value="<?= $i ?>"><?= $i === 1 ? 'À vista' : $i . 'x de ' . dinheiro($faltaLancar / $i) ?></option><?php endfor; ?></select></div>
            <div class="os-cobrar__linha">
              <div class="form-field"><label for="cobrar-vencimento">1º vencimento</label><input id="cobrar-vencimento" type="date" name="vencimento" value="<?= date('Y-m-d') ?>"></div>
              <div class="form-field"><label for="cobrar-forma">Forma</label><select id="cobrar-forma" name="forma"><?php foreach (Lancamento::FORMAS as $k => $r): ?><option value="<?= $k ?>" <?= $k === 'PIX' ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></div>
            </div>
            <button class="btn btn--ghost btn--sm"><?= icone('receipt') ?> Lançar cobrança</button>
          </form>
        <?php elseif ($faltaLancar < -0.004): ?>
          <p class="form-dica">O lançado é maior que o total atual da OS (<?= dinheiro(-$faltaLancar) ?> a mais). Ajuste no financeiro.</p>
        <?php elseif (!$contas): ?>
          <div class="ver-lado__vazio"><?= icone('wallet', 22, 1.25) ?>Nenhuma cobrança lançada.</div>
        <?php endif; ?>
      </aside>
    <?php endif; ?>

    <!-- Dados -->
    <aside class="card ver-lado" aria-label="Dados da OS">
      <header class="ver-lado__cabeca"><h2>Dados da OS</h2>
        <?php if (!in_array($st, ['ENTREGUE', 'CANCELADA'], true)): ?><button type="button" class="btn btn--ghost btn--sm" data-abrir="dlg-dados"><?= icone('edit') ?> Editar</button><?php endif; ?>
      </header>
      <dl class="ver-lista">
        <div><dt>Técnico responsável</dt><dd><?= e($os['tecnico'] ?? '—') ?></dd></div>
        <div><dt>Previsão de entrega</dt><dd class="tabular"><?= data_br($os['previsao_conclusao'], true) ?></dd></div>
        <?php if ($st === 'ORCAMENTO'): ?><div><dt>Orçamento válido até</dt><dd class="tabular"><?= data_br($os['validade_orcamento']) ?></dd></div><?php endif; ?>
        <div><dt>Quilometragem</dt><dd class="tabular"><?= $os['km_veiculo'] ? number_format((int) $os['km_veiculo'], 0, ',', '.') . ' km' : '—' ?></dd></div>
        <?php if ($os['aprovado_em']): ?><div><dt>Aprovação</dt><dd class="tabular"><?= data_br($os['aprovado_em'], true) ?><?= $os['aprovado_por_nome'] ? ' · ' . e($os['aprovado_por_nome']) : '' ?></dd></div><?php endif; ?>
        <?php if ($os['entregue_em']): ?><div><dt>Entregue em</dt><dd class="tabular"><?= data_br($os['entregue_em'], true) ?></dd></div><?php endif; ?>
      </dl>
      <?php if ($os['aprovado_em'] && $os['assinatura_cliente']): ?>
        <div class="ver-lado__bloco">
          <h3 class="ver-grupo__titulo"><?= icone('edit', 15) ?> Assinatura do cliente</h3>
          <img class="os-assinatura" src="<?= e($os['assinatura_cliente']) ?>" alt="Assinatura do cliente">
        </div>
      <?php endif; ?>
    </aside>
  </div>
</div>

<?php if ($veDinheiro): $dlgId = 'dlg-receber'; $acao = $acaoUrl; $extras = ['acao' => 'receber']; $tipo = 'RECEBER'; require V2_RAIZ . '/app/views/financeiro/_baixa.php'; endif; ?>

<!-- Diálogos -->
<dialog id="dlg-dados" class="dlg dlg--largo">
  <form method="post" action="./os-dados?id=<?= (int) $os['id'] ?>">
    <?= csrf_field() ?>
    <h3>Dados da OS</h3>
    <p>Quilometragem, responsável, prazos e anotações. Os itens e valores continuam na tela da OS.</p>
    <div class="form-row-2">
      <div class="form-field"><label for="dados-km">Quilometragem</label><input id="dados-km" name="km_veiculo" value="<?= e($os['km_veiculo']) ?>" inputmode="numeric"></div>
      <div class="form-field"><label for="dados-tecnico">Técnico responsável</label>
        <select id="dados-tecnico" name="tecnico_id"><option value="">—</option><?php foreach ($tecnicos as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) $os['tecnico_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?></option><?php endforeach; ?></select></div>
      <div class="form-field"><label for="dados-previsao">Previsão de entrega</label><input id="dados-previsao" type="datetime-local" name="previsao_conclusao" value="<?= $os['previsao_conclusao'] ? date('Y-m-d\TH:i', strtotime($os['previsao_conclusao'])) : '' ?>"></div>
      <div class="form-field"><label for="dados-validade">Orçamento válido até</label><input id="dados-validade" type="date" name="validade_orcamento" value="<?= e($os['validade_orcamento']) ?>"></div>
    </div>
    <div class="form-field"><label for="dados-relato">Relato do cliente</label><textarea id="dados-relato" name="defeito_relatado" rows="2"><?= e($os['defeito_relatado']) ?></textarea></div>
    <div class="form-field"><label for="dados-diagnostico">Diagnóstico</label><textarea id="dados-diagnostico" name="diagnostico" rows="2"><?= e($os['diagnostico']) ?></textarea></div>
    <div class="form-field"><label for="dados-obs">Observações (saem na impressão)</label><textarea id="dados-obs" name="observacoes" rows="2"><?= e($os['observacoes']) ?></textarea></div>
    <div class="form-field"><label for="dados-internas">Anotações internas</label><textarea id="dados-internas" name="observacoes_internas" rows="2"><?= e($os['observacoes_internas']) ?></textarea></div>
    <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Cancelar</button><button class="btn btn--primary">Salvar</button></div>
  </form>
</dialog>

<dialog id="dlg-aprovar" class="dlg">
  <form method="post" action="<?= $acaoUrl ?>" id="form-aprovar">
    <?= csrf_field() ?><input type="hidden" name="acao" value="aprovar"><input type="hidden" name="assinatura" id="assinatura">
    <h3>Aprovar orçamento de <?= dinheiro($os['valor_total']) ?></h3>
    <p>Os itens e o valor de agora ficam guardados como o que o cliente aprovou.</p>
    <div class="form-field"><label for="aprovado-por">Aprovado por</label><input id="aprovado-por" name="aprovado_por" value="<?= e($os['cliente']) ?>" maxlength="120"></div>
    <div class="form-field"><label for="canvas-assinatura">Assinatura do cliente (opcional)</label><canvas id="canvas-assinatura" class="os-assinar"></canvas>
      <a href="#" id="limpar-assinatura" class="form-dica">Limpar</a></div>
    <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Cancelar</button><button class="btn btn--primary">Aprovar</button></div>
  </form>
</dialog>

<?php foreach (['recusar' => ['Orçamento recusado', 'Motivo (opcional)', false, 'Registrar recusa'],
                'cancelar' => ['Cancelar OS', 'Motivo do cancelamento', true, 'Cancelar OS'],
                'retomar' => ['Voltar para execução', 'O que precisa ser refeito?', false, 'Voltar para execução'],
                'devolver' => ['Devolver ao técnico', 'O que o técnico precisa corrigir ou completar?', true, 'Devolver ao técnico']] as $acao => [$tit, $rot, $obrig, $btn]): ?>
  <dialog id="dlg-<?= $acao ?>" class="dlg">
    <form method="post" action="<?= $acaoUrl ?>">
      <?= csrf_field() ?><input type="hidden" name="acao" value="<?= $acao ?>">
      <h3><?= $tit ?></h3>
      <?php if ($acao === 'cancelar'): ?><p>Peças já baixadas voltam para o estoque.</p><?php endif; ?>
      <div class="form-field"><label for="motivo-<?= $acao ?>"><?= $rot ?></label><textarea id="motivo-<?= $acao ?>" name="motivo" rows="3" <?= $obrig ? 'required' : '' ?>></textarea></div>
      <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Voltar</button><button class="btn <?= $acao === 'cancelar' ? 'btn--danger' : 'btn--primary' ?>"><?= $btn ?></button></div>
    </form>
  </dialog>
<?php endforeach; ?>

<?php if ($st === 'AGUARDANDO_VALIDACAO' && $ehAdmin): ?>
<dialog id="dlg-validar" class="dlg dlg--largo">
  <form method="post" action="<?= $acaoUrl ?>">
    <?= csrf_field() ?><input type="hidden" name="acao" value="validar">
    <h3>Validar a execução</h3>
    <p><?= e($os['tecnico'] ?: 'O técnico') ?> informou que terminou em <?= data_br($os['execucao_enviada_em'], true) ?>.<?= $os['execucao_observacao'] ? ' Relato: “' . e($os['execucao_observacao']) . '”' : '' ?></p>
    <?php if ($pendentesTec): ?>
      <fieldset class="exec-validar">
        <legend>Materiais informados — marque os que foram mesmo usados</legend>
        <?php foreach ($pendentesTec as $m): $prev = $previstas[(int) $m['produto_id']] ?? null; ?>
          <label class="exec-validar__linha">
            <input type="checkbox" name="aprovar[]" value="<?= (int) $m['id'] ?>" <?= $prev ? '' : 'checked' ?>>
            <span><strong><?= e($m['nome']) ?> · <?= $qtd($m['quantidade']) ?> un.</strong>
              <small><?= $prev ? 'Esta peça já está prevista na OS (' . $qtd($prev) . ' un., já separada do estoque na execução). Marque só se foi usada além disso. ' : '' ?><?= $m['observacao'] ? '“' . e($m['observacao']) . '”' : '' ?></small></span>
          </label>
        <?php endforeach; ?>
      </fieldset>
      <p class="form-dica">Os marcados entram na OS como adicionais (preço de venda do catálogo) e saem do estoque agora, uma única vez. Os desmarcados ficam recusados.</p>
      <div class="form-field"><label for="motivo-recusa">Motivo, se recusar algum material (o técnico vê)</label><input id="motivo-recusa" name="motivo_recusa" maxlength="255"></div>
    <?php else: ?>
      <p class="form-dica">Nenhum material a avaliar.</p>
    <?php endif; ?>
    <p>Ao validar, a comissão é calculada e a OS fica <strong>pronta para entrega</strong>.</p>
    <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Voltar</button><button class="btn btn--primary">Validar e concluir</button></div>
  </form>
</dialog>
<?php endif; ?>

<dialog id="dlg-entregar" class="dlg">
  <form method="post" action="<?= $acaoUrl ?>">
    <?= csrf_field() ?><input type="hidden" name="acao" value="entregar">
    <h3>Entregar veículo</h3>
    <?php if ($aReceber > 0.004): ?><p class="alerta alerta--aviso os-dlg-alerta">Ainda falta receber <?= dinheiro($aReceber) ?> desta OS.</p><?php endif; ?>
    <div class="form-field"><label for="checklist-entrega">Checklist / observações da entrega</label><textarea id="checklist-entrega" name="checklist_entrega" rows="4" placeholder="Ex.: veículo lavado, peças antigas entregues ao cliente"><?= e($os['checklist_entrega']) ?></textarea></div>
    <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Voltar</button><button class="btn btn--primary">Confirmar entrega</button></div>
  </form>
</dialog>

<dialog id="dlg-item" class="dlg">
  <form method="post" action="./os-item?id=<?= (int) $os['id'] ?>">
    <?= csrf_field() ?><input type="hidden" name="operacao" value="alterar"><input type="hidden" name="item_id">
    <h3>Alterar item</h3>
    <?php if ($noOrcamento): ?>
      <div class="form-field"><label for="item-descricao">Descrição</label><input id="item-descricao" name="descricao" maxlength="200"></div>
      <div class="form-row-3">
        <div class="form-field"><label for="item-qtd">Qtd</label><input id="item-qtd" name="quantidade" inputmode="decimal"></div>
        <div class="form-field"><label for="item-valor">Unitário (R$)</label><input id="item-valor" name="valor_unitario" inputmode="decimal"></div>
        <div class="form-field"><label for="item-desconto">Desconto (R$)</label><input id="item-desconto" name="desconto" inputmode="decimal"></div>
      </div>
    <?php else: ?>
      <p>Depois da aprovação, valores e quantidades ficam como o cliente aprovou. Dá para trocar o técnico do serviço ou remover o item.</p>
    <?php endif; ?>
    <div class="form-field" data-so-servico><label for="item-tecnico">Técnico</label>
      <select id="item-tecnico" name="tecnico_id"><option value="">Responsável da OS</option><?php foreach ($tecnicos as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['nome']) ?></option><?php endforeach; ?></select></div>
    <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Cancelar</button><button class="btn btn--primary">Salvar</button></div>
  </form>
</dialog>

<dialog id="dlg-remover" class="dlg">
  <form method="post" action="./os-item?id=<?= (int) $os['id'] ?>">
    <?= csrf_field() ?><input type="hidden" name="operacao" value="remover"><input type="hidden" name="item_id">
    <h3>Remover item</h3>
    <p>O cliente já aprovou este orçamento: a remoção fica registrada no histórico. Peça já baixada volta ao estoque.</p>
    <div class="form-field"><label for="remover-motivo">Motivo</label><textarea id="remover-motivo" name="motivo" rows="2" required></textarea></div>
    <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Voltar</button><button class="btn btn--danger">Remover</button></div>
  </form>
</dialog>

<script src="<?= e(asset('js/assinatura.js')) ?>"></script>
<script>
  (function () {
    var abrir = function (id) { var d = document.getElementById(id); if (d) d.showModal(); return d; };
    document.querySelectorAll('[data-abrir]').forEach(function (b) {
      b.addEventListener('click', function (e) {
        e.preventDefault();
        var menu = b.closest('details.menu-mais'); if (menu) menu.open = false;
        abrir(b.dataset.abrir); if (b.dataset.abrir === 'dlg-aprovar' && pad) pad.redimensionar();
      });
    });
    // Menu ⋯: quando as ações descem para a esquerda (telas menores), a lista abre para a direita.
    document.querySelectorAll('.ver-topo details.menu-mais').forEach(function (m) {
      m.addEventListener('toggle', function () {
        var l = m.querySelector('.menu-mais__lista'); if (!m.open || !l) return;
        l.classList.remove('menu-mais__lista--direita');
        var area = document.querySelector('.content'), limite = area ? area.getBoundingClientRect().left : 0;
        if (l.getBoundingClientRect().left < limite) l.classList.add('menu-mais__lista--direita');
      });
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
        navigator.clipboard.writeText(b.dataset.copiar).then(function () {
          var t = b.innerHTML; b.textContent = 'Link copiado';
          setTimeout(function () { b.innerHTML = t; var m = b.closest('details.menu-mais'); if (m) m.open = false; }, 1400);
        });
      });
    });

    // Incluir item: abre e fecha a área, Serviço/Peça filtra o catálogo, campos aparecem só quando fazem sentido
    // e o total do item é calculado enquanto se digita.
    var caixa = document.getElementById('os-incluir'), sel = document.getElementById('produto_id');
    if (caixa && sel) {
      var form = document.getElementById('form-item'), botaoAbrir = caixa.querySelector('.os-incluir__abrir'), fecharB = caixa.querySelector('.os-incluir__fechar');
      var valor = document.getElementById('valor_unitario'), desc = document.getElementById('descricao'), qtdI = document.getElementById('quantidade'), descI = document.getElementById('desconto');
      var campoDesc = document.getElementById('campo-descricao'), campoTec = document.getElementById('campo-tecnico'), rotulo = caixa.querySelector('[data-rotulo-item]');
      var totalEl = caixa.querySelector('[data-total-item]'), botoesTipo = caixa.querySelectorAll('[data-tipo-item]');
      var opcoes = { SERVICO: [], PECA: [] };
      [].slice.call(sel.options).forEach(function (o) { opcoes[o.dataset.tipo].push(o); });
      var num = function (v) { v = String(v || '').replace(/[^\d,.-]/g, ''); if (v.indexOf(',') >= 0) v = v.replace(/\./g, '').replace(',', '.'); var n = parseFloat(v); return isFinite(n) ? n : 0; };
      var brl = function (n) { return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); };
      var total = function () { totalEl.textContent = brl(Math.max(0, Math.round(num(qtdI.value) * num(valor.value) * 100) / 100 - num(descI.value))); };
      var tipo = 'SERVICO';
      var sync = function () {
        var o = sel.options[sel.selectedIndex], avulso = tipo === 'SERVICO' && !sel.value;
        if (o && o.dataset.preco) valor.value = o.dataset.preco;
        campoDesc.hidden = !avulso;
        if (!avulso) desc.value = '';
        desc.required = avulso;
        campoTec.hidden = tipo === 'PECA';
        sel.required = tipo === 'PECA';
        total();
      };
      var mudarTipo = function (t) {
        tipo = t;
        botoesTipo.forEach(function (b) { b.setAttribute('aria-checked', b.dataset.tipoItem === t ? 'true' : 'false'); });
        while (sel.options.length) sel.remove(0);
        opcoes[t].forEach(function (o) { sel.add(o); });
        sel.selectedIndex = 0;
        rotulo.textContent = t === 'PECA' ? 'Peça do estoque' : 'Serviço';
        valor.value = ''; desc.value = ''; descI.value = ''; qtdI.value = '1';
        sync();
      };
      var abrirArea = function (sim) {
        caixa.classList.toggle('is-aberto', sim);
        botaoAbrir.setAttribute('aria-expanded', sim ? 'true' : 'false');
        if (sim) setTimeout(function () { sel.focus({ preventScroll: true }); caixa.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }, 60);
        else botaoAbrir.focus({ preventScroll: true });
      };
      botoesTipo.forEach(function (b) { b.addEventListener('click', function () { mudarTipo(b.dataset.tipoItem); sel.focus(); }); });
      botaoAbrir.addEventListener('click', function () { abrirArea(true); });
      if (fecharB) fecharB.addEventListener('click', function () { abrirArea(false); });
      form.addEventListener('keydown', function (e) { if (e.key === 'Escape' && fecharB) abrirArea(false); });
      sel.addEventListener('change', sync);
      [qtdI, valor, descI].forEach(function (i) { i.addEventListener('input', total); });
      mudarTipo('SERVICO');
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
