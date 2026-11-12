<?php
/**
 * Pedido de compra: montagem (rascunho), realização, recebimento (parcial ou total) e estorno.
 *
 * Espera: $pedido, $itens, $recebimentos, $linhaDoTempo, $produtos, $formas, $chaveRecebimento, $sucesso, $erro.
 */
require_once __DIR__ . '/../../models/PedidoCompra.php';

$p = $pedido;
$id = (int) $p['id_pedido'];
$status = $p['status'];
$e = fn($v) => htmlspecialchars((string) $v);
$qtd = fn($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',') ?: '0';
$brl = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$num = fn($v) => str_replace('.', ',', (string) $v);
$plain = fn($v) => str_replace('.', ',', rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.'));

$rascunho = $status === 'RASCUNHO';
$recebivel = in_array($status, ['REALIZADO', 'PARCIALMENTE_RECEBIDO'], true);
$cancelavel = in_array($status, ['RASCUNHO', 'REALIZADO'], true);
$haRecebimentoAtivo = (bool) array_filter($recebimentos, fn($r) => $r['estornado_em'] === null);
$itensAReceber = array_values(array_filter($itens, fn($i) => (float) $i['falta_receber'] > 0.0005));

$mensagens = [
    'rascunhocriado' => 'Rascunho criado.', 'salvo' => 'Alterações salvas.', 'itemincluido' => 'Item incluído no pedido.',
    'itemremovido' => 'Item removido.', 'realizado' => 'Pedido realizado. O estoque só sobe quando a mercadoria chegar e o recebimento for registrado.',
    'jarealizado' => 'Este pedido já estava realizado.', 'cancelado' => 'Pedido cancelado.',
    'recebido' => 'Recebimento registrado: o estoque foi atualizado só com o que chegou.',
    'recebimentorepetido' => 'Este recebimento já tinha sido registrado — o estoque não foi somado de novo.',
    'estornado' => 'Recebimento estornado: as peças saíram do estoque e as quantidades voltaram ao pedido.',
    'encerrado' => 'Pedido encerrado com o saldo não entregue.',
];
$flashOk = $mensagens[$sucesso ?? ''] ?? null;
?>

<style>
    .pc-grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 16px; align-items: start; }
    .pc-col { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    .pc-inline { display: flex; gap: 6px; align-items: center; }
    .pc-inline input { width: 90px; }
    @media (max-width: 960px) { .pc-grid { grid-template-columns: minmax(0, 1fr); } }
</style>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= $e($p['numero']) ?> · <?= $e($p['fornecedor_nome']) ?></h1>
        <p class="page-header__subtitle">
            <span class="badge <?= PedidoCompra::badge($status) ?>"><span class="badge__dot"></span><?= $e(PedidoCompra::rotulo($status)) ?></span>
            Total <strong><?= $brl($p['total']) ?></strong> · recebido <?= $qtd($p['qtd_recebida']) ?> de <?= $qtd($p['qtd_pedida']) ?>
        </p>
    </div>
    <div class="page-header__actions">
        <?php if (!empty($p['site_url'])): ?>
            <a href="<?= $e($p['site_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn--ghost">Site do fornecedor</a>
        <?php endif; ?>
        <a href="./listPedidosCompra" class="btn btn--ghost">Voltar</a>
    </div>
</div>

<?php if ($flashOk): ?>
    <?php $alertaTipo = 'sucesso'; $alertaMensagem = $flashOk; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>
<?php if (!empty($erro)): ?>
    <?php $alertaTipo = 'erro'; $alertaMensagem = $erro; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<div class="pc-grid">
<div class="pc-col">

    <!-- Itens -->
    <section class="card">
        <div class="card__header"><div class="card__title">Itens</div></div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Peça</th><th style="text-align:right;">Quantidade</th><th style="text-align:right;">Preço un.</th><th style="text-align:right;">Subtotal</th><th style="text-align:right;">Recebido</th><?php if ($rascunho): ?><th></th><?php endif; ?></tr>
                </thead>
                <tbody>
                <?php if (empty($itens)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:36px 20px;color:var(--color-text-muted);">Nenhum item ainda.</td></tr>
                <?php else: foreach ($itens as $i): ?>
                    <tr>
                        <td>
                            <strong><?= $e($i['produto_nome']) ?></strong>
                            <div class="text-muted fs-12"><?= $e($i['codigo_interno']) ?> · em estoque <?= $qtd($i['estoque_atual']) ?></div>
                            <?php if (!empty($i['solicitacao_numero'])): ?>
                                <div class="fs-12"><a href="./viewSolicitacao?id=<?= (int) $i['solicitacao_id'] ?>"><?= $e($i['solicitacao_numero']) ?></a></div>
                            <?php endif; ?>
                        </td>
                        <?php if ($rascunho): ?>
                            <td colspan="2" style="text-align:right;">
                                <form action="./atualizarItemPedidoCompra" method="POST" class="pc-inline" style="justify-content:flex-end;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id_pedido" value="<?= $id ?>"><input type="hidden" name="id_item" value="<?= (int) $i['id_pedido_item'] ?>">
                                    <input type="text" inputmode="decimal" name="quantidade" value="<?= $e($plain($i['quantidade'])) ?>" aria-label="Quantidade">
                                    <input type="text" inputmode="decimal" name="preco" value="<?= $e($num(number_format((float) $i['preco_unitario'], 2, '.', ''))) ?>" aria-label="Preço unitário">
                                    <button type="submit" class="btn btn--ghost btn--sm">Salvar</button>
                                </form>
                            </td>
                        <?php else: ?>
                            <td style="text-align:right;"><?= $qtd($i['quantidade']) ?></td>
                            <td style="text-align:right;"><?= $brl($i['preco_unitario']) ?></td>
                        <?php endif; ?>
                        <td style="text-align:right;"><?= $brl($i['quantidade'] * $i['preco_unitario']) ?></td>
                        <td style="text-align:right;"><?= $qtd($i['quantidade_recebida']) ?></td>
                        <?php if ($rascunho): ?>
                            <td>
                                <form action="./removerItemPedidoCompra" method="POST">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id_pedido" value="<?= $id ?>"><input type="hidden" name="id_item" value="<?= (int) $i['id_pedido_item'] ?>">
                                    <button type="submit" class="btn btn--ghost btn--sm" aria-label="Remover item">×</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="3" style="text-align:right;">Frete</td><td style="text-align:right;"><?= $brl($p['frete']) ?></td><td colspan="2"></td></tr>
                    <tr><td colspan="3" style="text-align:right;"><strong>Total do pedido</strong></td><td style="text-align:right;"><strong><?= $brl($p['total']) ?></strong></td><td colspan="2"></td></tr>
                </tfoot>
            </table>
        </div>

        <?php if ($rascunho): ?>
            <div class="card__body" style="padding:14px 20px;border-top:1px solid var(--color-border);">
                <form action="./adicionarItemPedidoCompra" method="POST" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_pedido" value="<?= $id ?>">
                    <div class="form-field" style="flex:1 1 240px;margin:0;">
                        <label>Incluir peça</label>
                        <select name="produto_id" required>
                            <option value="">Escolha...</option>
                            <?php foreach ($produtos as $pr): ?>
                                <option value="<?= (int) $pr['id_produto'] ?>"><?= $e($pr['nome']) ?> (<?= $e($pr['codigo_interno']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field" style="width:110px;margin:0;"><label>Quantidade</label><input type="text" inputmode="decimal" name="quantidade" value="1" required></div>
                    <div class="form-field" style="width:130px;margin:0;"><label>Preço un. (R$)</label><input type="text" inputmode="decimal" name="preco" required></div>
                    <button type="submit" class="btn btn--ghost">Incluir</button>
                </form>
            </div>
        <?php endif; ?>
    </section>

    <!-- Recebimento -->
    <?php if ($recebivel): ?>
        <section class="card" style="border-color:var(--color-primary);">
            <div class="card__header"><div class="card__title">Registrar recebimento</div>
                <span class="text-muted fs-12">Informe só o que chegou agora. O estoque soma exatamente isso.</span></div>
            <div class="card__body" style="padding:16px 20px;">
                <?php if (!$itensAReceber): ?>
                    <p class="text-muted" style="margin:0;font-size:13px;">Tudo o que foi pedido já chegou.</p>
                <?php else: ?>
                    <form action="./receberPedidoCompra" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id_pedido" value="<?= $id ?>">
                        <input type="hidden" name="chave" value="<?= $e($chaveRecebimento) ?>">

                        <div class="table-wrap">
                            <table class="table">
                                <thead><tr><th>Peça</th><th style="text-align:right;">Falta chegar</th><th style="width:130px;">Chegou agora</th><th style="width:140px;">Custo un. da nota (R$)</th></tr></thead>
                                <tbody>
                                <?php foreach ($itensAReceber as $i): ?>
                                    <tr>
                                        <td><?= $e($i['produto_nome']) ?></td>
                                        <td style="text-align:right;"><?= $qtd($i['falta_receber']) ?></td>
                                        <td><input type="text" inputmode="decimal" name="recebido[<?= (int) $i['id_pedido_item'] ?>][quantidade]" placeholder="0" style="width:100%;" aria-label="Quantidade recebida de <?= $e($i['produto_nome']) ?>"></td>
                                        <td><input type="text" inputmode="decimal" name="recebido[<?= (int) $i['id_pedido_item'] ?>][custo]" value="<?= $e($num(number_format((float) $i['preco_unitario'], 2, '.', ''))) ?>" style="width:100%;"></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="form-grid" style="margin-top:12px;">
                            <div class="form-field"><label>Nota fiscal / documento</label><input type="text" name="documento_fiscal" maxlength="60"></div>
                            <div class="form-field"><label>Observação</label><input type="text" name="observacao" maxlength="255" placeholder="Ex.: caixa avariada, faltou uma unidade"></div>
                        </div>
                        <button type="submit" class="btn btn--primary" style="margin-top:12px;" onclick="return confirm('Confirmar o recebimento? O estoque será atualizado agora.')">Confirmar recebimento</button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Recebimentos já feitos -->
    <?php if (!empty($recebimentos)): ?>
        <section class="card">
            <div class="card__header"><div class="card__title">Recebimentos</div></div>
            <div class="card__body" style="padding:8px 20px 16px;">
                <?php foreach ($recebimentos as $r): $estornado = $r['estornado_em'] !== null; ?>
                    <div style="padding:12px 0;border-bottom:1px solid var(--color-border);<?= $estornado ? 'opacity:.65;' : '' ?>">
                        <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;align-items:center;">
                            <div>
                                <strong>Recebimento #<?= (int) $r['id_recebimento'] ?></strong>
                                <span class="text-muted fs-12">· <?= date('d/m/Y H:i', strtotime($r['recebido_em'])) ?> · <?= $e($r['recebido_por_nome'] ?? '—') ?></span>
                                <?php if ($estornado): ?><span class="badge badge--danger">Estornado</span><?php endif; ?>
                                <?php if (!empty($r['documento_fiscal'])): ?><div class="fs-12 text-muted">Documento: <?= $e($r['documento_fiscal']) ?></div><?php endif; ?>
                                <?php if (!empty($r['observacao'])): ?><div class="fs-12 text-muted"><?= $e($r['observacao']) ?></div><?php endif; ?>
                            </div>
                        </div>
                        <ul style="margin:6px 0 0;padding-left:18px;font-size:13px;">
                            <?php foreach ($r['linhas'] as $l): ?>
                                <li><?= $qtd($l['quantidade']) ?> × <?= $e($l['produto_nome']) ?> a <?= $brl($l['custo_unitario']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($estornado): ?>
                            <div class="fs-12 text-muted" style="margin-top:4px;">Estornado em <?= date('d/m/Y H:i', strtotime($r['estornado_em'])) ?> por <?= $e($r['estornado_por_nome'] ?? '—') ?>: <?= $e($r['estorno_justificativa']) ?></div>
                        <?php else: ?>
                            <details style="margin-top:6px;">
                                <summary style="cursor:pointer;font-size:12.5px;font-weight:600;color:var(--color-danger);">Estornar este recebimento</summary>
                                <form action="./estornarRecebimentoCompra" method="POST" style="margin-top:8px;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id_recebimento" value="<?= (int) $r['id_recebimento'] ?>"><input type="hidden" name="id_pedido" value="<?= $id ?>">
                                    <div class="form-field"><label>Por quê? <span style="color:var(--color-danger)">*</span></label>
                                        <input type="text" name="justificativa" maxlength="255" required placeholder="Ex.: peça errada, lançado no pedido errado"></div>
                                    <p class="text-muted fs-12" style="margin:6px 0;">As peças saem do estoque (só é possível se ainda estiverem lá) e voltam a faltar no pedido.</p>
                                    <button type="submit" class="btn btn--danger btn--sm" onclick="return confirm('Estornar este recebimento?')">Confirmar estorno</button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<div class="pc-col">
    <section class="card">
        <div class="card__header"><div class="card__title">Condições</div></div>
        <div class="card__body" style="padding:16px 20px;">
            <?php if ($status === 'CANCELADO'): ?>
                <p style="margin:0;font-size:13px;"><strong>Cancelado.</strong> <?= $e($p['motivo_encerramento'] ?? '') ?></p>
            <?php else: ?>
            <form action="./atualizarPedidoCompra" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id_pedido" value="<?= $id ?>">
                <?php if ($rascunho): ?>
                    <div class="form-field"><label>Condição de pagamento</label><input type="text" name="condicao_pagamento" maxlength="150" value="<?= $e($p['condicao_pagamento']) ?>" placeholder="Ex.: boleto 28 dias"></div>
                    <div class="form-field" style="margin-top:8px;"><label>Forma de pagamento</label>
                        <select name="forma_pagamento" data-no-ts>
                            <option value="">—</option>
                            <?php foreach ($formas as $chave => $rotulo): ?><option value="<?= $e($chave) ?>" <?= $p['forma_pagamento'] === $chave ? 'selected' : '' ?>><?= $e($rotulo) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="form-grid" style="margin-top:8px;">
                        <div class="form-field"><label>Vencimento previsto</label><input type="date" name="vencimento_previsto" value="<?= $e($p['vencimento_previsto']) ?>"></div>
                        <div class="form-field"><label>Entrega prevista</label><input type="date" name="prazo_previsto" value="<?= $e($p['prazo_previsto']) ?>"></div>
                    </div>
                    <div class="form-field" style="margin-top:8px;"><label>Frete (R$)</label><input type="text" inputmode="decimal" name="frete" value="<?= $e($num(number_format((float) $p['frete'], 2, '.', ''))) ?>"></div>
                <?php else: ?>
                    <dl style="display:grid;grid-template-columns:110px 1fr;gap:6px 10px;font-size:13px;margin:0 0 10px;">
                        <dt class="text-muted">Pagamento</dt><dd style="margin:0;"><?= $e($p['condicao_pagamento'] ?: '—') ?> <?= $p['forma_pagamento'] ? '· ' . $e($formas[$p['forma_pagamento']] ?? $p['forma_pagamento']) : '' ?></dd>
                        <dt class="text-muted">Vencimento</dt><dd style="margin:0;"><?= $p['vencimento_previsto'] ? date('d/m/Y', strtotime($p['vencimento_previsto'])) : '—' ?></dd>
                        <dt class="text-muted">Entrega</dt><dd style="margin:0;"><?= $p['prazo_previsto'] ? date('d/m/Y', strtotime($p['prazo_previsto'])) : '—' ?></dd>
                    </dl>
                <?php endif; ?>
                <div class="form-field" style="margin-top:8px;"><label>Nº do pedido no site do fornecedor</label>
                    <input type="text" name="referencia_externa" maxlength="80" value="<?= $e($p['referencia_externa']) ?>" placeholder="Anote o número do pedido feito no site"></div>
                <div class="form-field" style="margin-top:8px;"><label>Observações</label><textarea name="observacoes" rows="2"><?= $e($p['observacoes']) ?></textarea></div>
                <button type="submit" class="btn btn--ghost btn--sm" style="margin-top:10px;">Salvar</button>
            </form>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!$rascunho && $status !== 'CANCELADO'): ?>
    <section class="card">
        <div class="card__header"><div class="card__title">Conta a pagar</div></div>
        <div class="card__body" style="padding:16px 20px;font-size:13px;">
            <?php if (!empty($p['conta_pagar_id'])): ?>
                <p style="margin:0;">Este pedido já tem conta a pagar:
                    <a href="./viewDespesa?id=<?= (int) $p['conta_pagar_id'] ?>"><strong>DP-<?= str_pad((string) (int) $p['conta_pagar_id'], 4, '0', STR_PAD_LEFT) ?></strong></a>.</p>
            <?php else: ?>
                <p class="text-muted" style="margin:0 0 10px;">O pedido ainda não foi lançado em contas a pagar. Lance quando souber como e quando vai pagar (o valor padrão é o total do pedido; ajuste ao da nota).</p>
                <details>
                    <summary class="btn btn--primary btn--sm" style="list-style:none;cursor:pointer;">Lançar conta a pagar</summary>
                    <form action="./lancarContaPedido" method="POST" style="margin-top:10px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id_pedido" value="<?= $id ?>">
                        <input type="hidden" name="chave" value="<?= $e($chaveRecebimento) ?>">
                        <div class="form-field"><label>Valor (R$)</label><input type="text" inputmode="decimal" name="valor_total" value="<?= $e(number_format((float) $p['total'], 2, ',', '')) ?>"></div>
                        <div class="form-field" style="margin-top:8px;"><label>Pagamento</label>
                            <select name="meio_pagamento" data-no-ts required>
                                <?php foreach (Despesa::MEIOS as $chaveMeio => $rotulo): ?>
                                    <option value="<?= $e($chaveMeio) ?>" <?= $p['forma_pagamento'] === $chaveMeio ? 'selected' : '' ?>><?= $e($rotulo) ?></option>
                                <?php endforeach; ?>
                            </select></div>
                        <?php if (!empty($cartoes)): ?>
                        <div class="form-field" style="margin-top:8px;"><label>Cartão (se for no cartão)</label>
                            <select name="cartao_id" data-no-ts><option value="">—</option>
                                <?php foreach ($cartoes as $cartao): ?><option value="<?= (int) $cartao['id_cartao'] ?>"><?= $e($cartao['apelido']) ?></option><?php endforeach; ?>
                            </select></div>
                        <?php endif; ?>
                        <div class="form-grid" style="margin-top:8px;">
                            <div class="form-field"><label>Parcelas</label><input type="number" min="1" max="48" name="qtd_parcelas" value="1"></div>
                            <div class="form-field"><label>1º vencimento</label><input type="date" name="primeiro_vencimento" value="<?= $e($p['vencimento_previsto']) ?>"></div>
                        </div>
                        <button type="submit" class="btn btn--primary btn--sm" style="margin-top:10px;">Lançar</button>
                    </form>
                </details>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="card">
        <div class="card__header"><div class="card__title">Ações</div></div>
        <div class="card__body" style="padding:16px 20px;display:flex;flex-direction:column;gap:12px;">
            <?php if ($rascunho): ?>
                <form action="./realizarPedidoCompra" method="POST">
                    <?= csrf_field() ?><input type="hidden" name="id_pedido" value="<?= $id ?>">
                    <button type="submit" class="btn btn--primary" onclick="return confirm('Realizar o pedido? Os itens e preços ficam travados. O estoque NÃO muda agora.')">Realizar pedido</button>
                    <p class="text-muted fs-12" style="margin:6px 0 0;">Marca que o pedido foi feito ao fornecedor. Faça-o no site ou por telefone e anote o número ao lado.</p>
                </form>
            <?php endif; ?>

            <?php if ($status === 'PARCIALMENTE_RECEBIDO'): ?>
                <details>
                    <summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--color-primary);">Encerrar sem esperar o saldo</summary>
                    <form action="./encerrarPedidoCompra" method="POST" style="margin-top:8px;">
                        <?= csrf_field() ?><input type="hidden" name="id_pedido" value="<?= $id ?>">
                        <div class="form-field"><label>Motivo <span style="color:var(--color-danger)">*</span></label>
                            <input type="text" name="motivo" maxlength="255" required placeholder="Ex.: fornecedor não tem mais o restante"></div>
                        <button type="submit" class="btn btn--ghost btn--sm" style="margin-top:8px;" onclick="return confirm('Encerrar o pedido? O que já chegou continua no estoque.')">Encerrar pedido</button>
                    </form>
                </details>
            <?php endif; ?>

            <?php if ($cancelavel && !$haRecebimentoAtivo): ?>
                <details>
                    <summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--color-danger);">Cancelar pedido</summary>
                    <form action="./cancelarPedidoCompra" method="POST" style="margin-top:8px;">
                        <?= csrf_field() ?><input type="hidden" name="id_pedido" value="<?= $id ?>">
                        <div class="form-field"><label>Motivo <span style="color:var(--color-danger)">*</span></label>
                            <input type="text" name="motivo" maxlength="255" required></div>
                        <button type="submit" class="btn btn--danger btn--sm" style="margin-top:8px;" onclick="return confirm('Cancelar este pedido?')">Confirmar cancelamento</button>
                    </form>
                </details>
            <?php endif; ?>

            <?php if (!$rascunho && !$recebivel && $status !== 'CANCELADO'): ?>
                <p class="text-muted" style="margin:0;font-size:13px;">Pedido concluído.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="card">
        <div class="card__header"><div class="card__title">Linha do tempo</div></div>
        <div class="card__body" style="padding:14px 16px;">
            <ol style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px;">
                <?php foreach (array_reverse($linhaDoTempo) as $h): ?>
                    <li style="padding:10px 12px;background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);font-size:12.5px;">
                        <strong><?= $e(PedidoCompra::rotulo($h['estado_novo'])) ?></strong>
                        <?php if (!empty($h['estado_anterior']) && $h['estado_anterior'] !== $h['estado_novo']): ?><span class="text-muted">← <?= $e(PedidoCompra::rotulo($h['estado_anterior'])) ?></span><?php endif; ?>
                        <div class="text-muted" style="font-size:11.5px;margin-top:2px;"><?= date('d/m/Y H:i', strtotime($h['criado_em'])) ?> · <?= $e($h['usuario_nome'] ?? ucfirst(strtolower((string) $h['origem']))) ?></div>
                        <?php if (!empty($h['motivo'])): ?><div style="margin-top:4px;"><?= $e($h['motivo']) ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>
</div>
</div>
