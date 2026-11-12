<?php
/**
 * Ficha da solicitação de peça.
 *
 * Espera: $solicitacao, $linhaDoTempo, $ehAdmin, $podeCancelar, $pedidos, $cotacoes, $fornecedores, $sucesso, $erro.
 * A equipe vê o andamento; fornecedor, cotações e pedido (com preços) são só do administrador.
 */
require_once __DIR__ . '/../../models/SolicitacaoPeca.php';
require_once __DIR__ . '/../../models/PedidoCompra.php';

$s = $solicitacao;
$id = (int) $s['id_solicitacao'];
$e = fn($v) => htmlspecialchars((string) $v);
$plain = fn($v) => str_replace('.', ',', rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.'));
$qtd = fn($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',') ?: '0';
$brl = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

$status = $s['status'];
$aberta = in_array($status, SolicitacaoPeca::ABERTAS, true);
$antesDoPedido = in_array($status, ['SOLICITADA', 'EM_ANALISE', 'APROVADA_COMPRA'], true);
$semCadastro = empty($s['produto_id']);

$mensagens = [
    'registrada' => 'Solicitação registrada. O administrador foi avisado.',
    'jaexistia'  => 'Você já tinha uma solicitação em aberto para esta peça — é esta aqui.',
    'analise'    => 'Solicitação em análise.',
    'aprovada'   => 'Solicitação aprovada para compra.',
    'cancelada'  => 'Solicitação cancelada.',
    'cotacao'    => 'Cotação registrada.',
    'cotacaoexcluida' => 'Cotação removida.',
];
$flashOk = $mensagens[$sucesso ?? ''] ?? null;

$badgeUrgencia = ['URGENTE' => 'badge--danger', 'ALTA' => 'badge--warning', 'NORMAL' => 'badge--neutral', 'BAIXA' => 'badge--neutral'];
$etapas = ['SOLICITADA', 'EM_ANALISE', 'APROVADA_COMPRA', 'PEDIDO_REALIZADO', 'PARCIALMENTE_RECEBIDA', 'RECEBIDA'];
$posicao = array_search($status, $etapas, true);
?>

<style>
    .sp-grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 16px; align-items: start; }
    .sp-col { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    .sp-dl { display: grid; grid-template-columns: 120px 1fr; gap: 6px 12px; font-size: 13px; margin: 0; }
    .sp-dl dt { color: var(--color-text-muted); }
    .sp-dl dd { margin: 0; min-width: 0; overflow-wrap: anywhere; }
    .sp-passos { display: flex; flex-wrap: wrap; gap: 6px; padding: 14px 20px 4px; }
    .sp-passo { padding: 4px 10px; border-radius: 999px; font-size: 11.5px; border: 1px solid var(--color-border); color: var(--color-text-muted); }
    .sp-passo.is-feito { background: var(--color-success-soft); color: var(--color-success, #16A34A); border-color: transparent; }
    .sp-passo.is-atual { background: var(--color-primary-soft); color: var(--color-primary); border-color: var(--color-primary); font-weight: 700; }
    @media (max-width: 960px) { .sp-grid { grid-template-columns: minmax(0, 1fr); } }
</style>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= $e($s['numero']) ?> · <?= $e($s['descricao']) ?></h1>
        <p class="page-header__subtitle">
            <span class="badge <?= SolicitacaoPeca::badge($status) ?>"><span class="badge__dot"></span><?= $e(SolicitacaoPeca::rotulo($status)) ?></span>
            <span class="badge <?= $badgeUrgencia[$s['urgencia']] ?? 'badge--neutral' ?>"><?= $e(SolicitacaoPeca::URGENCIAS[$s['urgencia']] ?? $s['urgencia']) ?></span>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="./listSolicitacoes" class="btn btn--ghost">Voltar</a>
        <?php if ($ehAdmin): ?><a href="./painelCompras" class="btn btn--ghost">Compras</a><?php endif; ?>
    </div>
</div>

<?php if ($flashOk): ?>
    <?php $alertaTipo = 'sucesso'; $alertaMensagem = $flashOk; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>
<?php if (!empty($erro)): ?>
    <?php $alertaTipo = 'erro'; $alertaMensagem = $erro; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<div class="sp-grid">
<div class="sp-col">

    <section class="card">
        <?php if ($status !== 'CANCELADA'): ?>
            <div class="sp-passos">
                <?php foreach ($etapas as $i => $etapa):
                    $classe = $posicao !== false && $i < $posicao ? 'is-feito' : ($etapa === $status ? 'is-atual' : ''); ?>
                    <span class="sp-passo <?= $classe ?>"><?= $e(SolicitacaoPeca::rotulo($etapa)) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="card__body" style="padding:16px 20px;">
            <dl class="sp-dl">
                <dt>Quantidade</dt>
                <dd><strong><?= $qtd($s['quantidade']) ?></strong>
                    <?php if ((float) $s['quantidade_recebida'] > 0): ?> · já chegou <strong><?= $qtd($s['quantidade_recebida']) ?></strong><?php endif; ?>
                </dd>
                <dt>Motivo</dt><dd><?= $e($s['motivo']) ?></dd>
                <dt>Estoque na hora</dt><dd><?= $s['estoque_conhecido'] !== null ? $qtd($s['estoque_conhecido']) : '—' ?>
                    <?php if ($s['estoque_agora'] !== null): ?><span class="text-muted">(agora: <?= $qtd($s['estoque_agora']) ?>)</span><?php endif; ?></dd>
                <?php if (!empty($s['os_numero'])): ?>
                    <dt>OS</dt><dd><a href="./viewOrdemServico?id=<?= (int) $s['ordem_servico_id'] ?>"><?= $e($s['os_numero']) ?></a></dd>
                <?php endif; ?>
                <?php if (!empty($s['observacoes'])): ?><dt>Observações</dt><dd><?= $e($s['observacoes']) ?></dd><?php endif; ?>
                <dt>Solicitante</dt><dd><?= $e($s['solicitante_nome']) ?> · <?= date('d/m/Y H:i', strtotime($s['criada_em'])) ?></dd>
                <?php if (!empty($s['decidido_por_nome'])): ?>
                    <dt>Decisão</dt><dd><?= $e($s['decidido_por_nome']) ?> · <?= date('d/m/Y H:i', strtotime($s['decidido_em'])) ?>
                        <?php if (!empty($s['motivo_decisao'])): ?><div><?= $e($s['motivo_decisao']) ?></div><?php endif; ?></dd>
                <?php endif; ?>
            </dl>

            <?php if ($semCadastro && $aberta): ?>
                <p class="text-muted" style="margin:12px 0 0;font-size:12.5px;">
                    Peça fora do cadastro de produtos.
                    <?php if ($ehAdmin): ?><a href="./saveProduto">Cadastre o produto</a> para poder comprá-lo e dar entrada no estoque.
                    <?php else: ?>O administrador cadastra o produto antes de comprar.<?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!empty($pedidos)): ?>
        <section class="card">
            <div class="card__header"><div class="card__title">Pedido de compra</div></div>
            <div class="card__body" style="padding:12px 20px 16px;">
                <?php foreach ($pedidos as $p): ?>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:space-between;align-items:center;padding:8px 0;font-size:13px;">
                        <div>
                            <?php if ($ehAdmin): ?><a href="./viewPedidoCompra?id=<?= (int) $p['id_pedido'] ?>"><strong><?= $e($p['numero']) ?></strong></a> · <?= $e($p['fornecedor_nome']) ?>
                            <?php else: ?><strong>Em pedido de compra</strong><?php endif; ?>
                            <div class="text-muted fs-12">pedido <?= $qtd($p['quantidade']) ?> · recebido <?= $qtd($p['quantidade_recebida']) ?></div>
                        </div>
                        <span class="badge <?= PedidoCompra::badge($p['status']) ?>"><?= $e(PedidoCompra::rotulo($p['status'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($ehAdmin && $antesDoPedido): ?>
        <section class="card">
            <div class="card__header"><div class="card__title">Cotações</div>
                <span class="text-muted fs-12">Registre o que cada fornecedor pediu e compare.</span></div>
            <div class="card__body" style="padding:12px 20px 18px;">
                <?php if (empty($cotacoes)): ?>
                    <p class="text-muted" style="font-size:13px;margin:0 0 12px;">Nenhuma cotação ainda. Pesquise no site ou por telefone e registre abaixo.</p>
                <?php else: ?>
                    <div class="table-wrap" style="margin-bottom:14px;">
                        <table class="table">
                            <thead><tr><th>Fornecedor</th><th style="text-align:right;">Unitário</th><th style="text-align:right;">Frete</th><th style="text-align:right;">Total</th><th>Prazo</th><th>Pagamento</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($cotacoes as $c): ?>
                                <tr style="<?= (int) $c['vencida'] ? 'opacity:.6;' : '' ?>">
                                    <td>
                                        <strong><?= $e($c['fornecedor_nome']) ?></strong>
                                        <?php if ($c['melhor_preco']): ?><span class="badge badge--success">Menor total</span><?php endif; ?>
                                        <?php if ($c['mais_rapida']): ?><span class="badge badge--info">Mais rápida</span><?php endif; ?>
                                        <?php if ((int) $c['vencida']): ?><span class="badge badge--neutral">Vencida</span><?php endif; ?>
                                        <?php if (!empty($c['observacao'])): ?><div class="text-muted fs-12"><?= $e($c['observacao']) ?></div><?php endif; ?>
                                    </td>
                                    <td style="text-align:right;"><?= $brl($c['preco_unitario']) ?></td>
                                    <td style="text-align:right;"><?= $brl($c['frete']) ?></td>
                                    <td style="text-align:right;"><strong><?= $brl($c['total']) ?></strong></td>
                                    <td><?= $c['prazo_dias'] !== null ? (int) $c['prazo_dias'] . ' dia(s)' : '—' ?></td>
                                    <td class="text-muted fs-12"><?= $e($c['condicao_pagamento'] ?? '—') ?></td>
                                    <td>
                                        <div style="display:flex;gap:6px;">
                                            <?php if (!$semCadastro): ?>
                                                <form action="./usarCotacao" method="POST">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id_cotacao" value="<?= (int) $c['id_cotacao'] ?>">
                                                    <input type="hidden" name="id_solicitacao" value="<?= $id ?>">
                                                    <button type="submit" class="btn btn--primary btn--sm">Usar no pedido</button>
                                                </form>
                                            <?php endif; ?>
                                            <form action="./excluirCotacao" method="POST" onsubmit="return confirm('Remover esta cotação?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id_cotacao" value="<?= (int) $c['id_cotacao'] ?>">
                                                <input type="hidden" name="id_solicitacao" value="<?= $id ?>">
                                                <button type="submit" class="btn btn--ghost btn--sm" aria-label="Remover cotação">×</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <details>
                    <summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--color-primary);">Registrar cotação</summary>
                    <form action="./registrarCotacao" method="POST" style="margin-top:10px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id_solicitacao" value="<?= $id ?>">
                        <div class="form-grid">
                            <div class="form-field">
                                <label>Fornecedor</label>
                                <select name="fornecedor_id" data-no-ts required>
                                    <option value="">Escolha...</option>
                                    <?php foreach ($fornecedores as $f): ?>
                                        <option value="<?= (int) $f['id_fornecedor'] ?>"><?= $e($f['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-field"><label>Quantidade</label><input type="text" inputmode="decimal" name="quantidade" value="<?= $e($plain($s['quantidade'])) ?>"></div>
                            <div class="form-field"><label>Preço unitário (R$)</label><input type="text" inputmode="decimal" name="preco_unitario" required></div>
                            <div class="form-field"><label>Frete (R$)</label><input type="text" inputmode="decimal" name="frete" placeholder="0,00"></div>
                            <div class="form-field"><label>Prazo (dias)</label><input type="number" min="0" name="prazo_dias"></div>
                            <div class="form-field"><label>Válida até</label><input type="date" name="validade_ate"></div>
                            <div class="form-field"><label>Condição de pagamento</label><input type="text" name="condicao_pagamento" maxlength="150" placeholder="Ex.: boleto 28 dias"></div>
                            <div class="form-field"><label>Observação</label><input type="text" name="observacao" maxlength="255"></div>
                        </div>
                        <button type="submit" class="btn btn--primary btn--sm" style="margin-top:10px;">Registrar</button>
                    </form>
                </details>

                <p class="text-muted fs-12" style="margin:12px 0 0;">
                    Para pesquisar:
                    <?php foreach ($fornecedores as $f): if (!empty($f['site_url'])): ?>
                        <a href="<?= $e($f['site_url']) ?>" target="_blank" rel="noopener noreferrer"><?= $e($f['nome']) ?></a> ·
                    <?php endif; endforeach; ?>
                    <a href="https://lista.mercadolivre.com.br/<?= rawurlencode($s['descricao']) ?>" target="_blank" rel="noopener noreferrer">buscar no Mercado Livre</a>
                    <span>(abre o site; não há compra automática — registre a cotação ou o pedido aqui)</span>
                </p>
            </div>
        </section>
    <?php endif; ?>
</div>

<div class="sp-col">
    <?php if ($ehAdmin || $podeCancelar): ?>
        <section class="card">
            <div class="card__header"><div class="card__title">Ações</div></div>
            <div class="card__body" style="padding:16px 20px;display:flex;flex-direction:column;gap:12px;">
                <?php if ($ehAdmin && $status === 'SOLICITADA'): ?>
                    <form action="./analisarSolicitacao" method="POST">
                        <?= csrf_field() ?><input type="hidden" name="id_solicitacao" value="<?= $id ?>">
                        <button type="submit" class="btn btn--ghost btn--sm">Marcar em análise</button>
                    </form>
                <?php endif; ?>
                <?php if ($ehAdmin && in_array($status, ['SOLICITADA', 'EM_ANALISE'], true)): ?>
                    <form action="./aprovarSolicitacao" method="POST">
                        <?= csrf_field() ?><input type="hidden" name="id_solicitacao" value="<?= $id ?>">
                        <button type="submit" class="btn btn--primary btn--sm">Aprovar para compra</button>
                    </form>
                <?php endif; ?>
                <?php if ($podeCancelar): ?>
                    <details>
                        <summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--color-danger);">Cancelar solicitação</summary>
                        <form action="./cancelarSolicitacao" method="POST" style="margin-top:8px;">
                            <?= csrf_field() ?><input type="hidden" name="id_solicitacao" value="<?= $id ?>">
                            <div class="form-field"><label>Motivo <span style="color:var(--color-danger)">*</span></label>
                                <input type="text" name="motivo" maxlength="255" required placeholder="Por que não é mais necessária?"></div>
                            <button type="submit" class="btn btn--danger btn--sm" style="margin-top:8px;" onclick="return confirm('Cancelar esta solicitação?')">Confirmar cancelamento</button>
                        </form>
                    </details>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="card">
        <div class="card__header"><div class="card__title">Linha do tempo</div></div>
        <div class="card__body" style="padding:14px 16px;">
            <ol style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px;">
                <?php foreach (array_reverse($linhaDoTempo) as $h): ?>
                    <li style="padding:10px 12px;background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);font-size:12.5px;">
                        <strong><?= $e(SolicitacaoPeca::rotulo($h['estado_novo'])) ?></strong>
                        <?php if (!empty($h['estado_anterior'])): ?><span class="text-muted">← <?= $e(SolicitacaoPeca::rotulo($h['estado_anterior'])) ?></span><?php endif; ?>
                        <div class="text-muted" style="font-size:11.5px;margin-top:2px;"><?= date('d/m/Y H:i', strtotime($h['criado_em'])) ?> · <?= $e($h['usuario_nome'] ?? ucfirst(strtolower((string) $h['origem']))) ?></div>
                        <?php if (!empty($h['motivo'])): ?><div style="margin-top:4px;"><?= $e($h['motivo']) ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>
</div>
</div>
