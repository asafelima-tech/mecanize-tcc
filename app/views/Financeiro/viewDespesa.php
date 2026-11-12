<?php
/**
 * Ficha da despesa: parcelas, pagamentos, comprovantes, estorno e cancelamento.
 *
 * Espera: $despesa, $parcelas, $pagamentos, $comprovantes, $linhaDoTempo, $formas, $sucesso, $erro.
 */
require_once __DIR__ . '/../../models/Despesa.php';

$d = $despesa;
$id = (int) $d['id_despesa'];
$e = fn($v) => htmlspecialchars((string) $v);
$brl = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$data = fn($x) => $x ? date('d/m/Y', strtotime($x)) : '—';
$cancelada = $d['status'] === 'CANCELADA';
$noCartao = $d['meio_pagamento'] === 'CARTAO_CREDITO';
$chave = bin2hex(random_bytes(12));

$mensagens = [
    'registrada' => 'Despesa registrada.', 'jaexistia' => 'Esta despesa já estava registrada.', 'pago' => 'Pagamento registrado.', 'japago' => 'Este pagamento já tinha sido registrado.',
    'estornado' => 'Pagamento estornado: a parcela voltou a ser devida.', 'cancelada' => 'Despesa cancelada.', 'comprovante' => 'Comprovante anexado.',
];
$flashOk = $mensagens[$sucesso ?? ''] ?? null;
?>

<style>
    .dp-grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 16px; align-items: start; }
    .dp-col { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    @media (max-width: 960px) { .dp-grid { grid-template-columns: minmax(0, 1fr); } }
</style>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= $e($d['numero']) ?> · <?= $e($d['descricao']) ?></h1>
        <p class="page-header__subtitle">
            <span class="badge <?= $cancelada ? 'badge--danger' : ((float) $d['valor_pendente'] > 0 ? 'badge--warning' : 'badge--success') ?>"><?= $cancelada ? 'Cancelada' : ((float) $d['valor_pendente'] > 0 ? 'Em aberto' : 'Quitada') ?></span>
            <?= $e(Despesa::rotuloCategoria($d['categoria'])) ?> · <?= $e(Despesa::MEIOS[$d['meio_pagamento']] ?? $d['meio_pagamento']) ?>
        </p>
    </div>
    <div class="page-header__actions"><a href="./listContasPagar" class="btn btn--ghost">Voltar</a></div>
</div>

<?php if ($flashOk): ?>
    <?php $alertaTipo = 'sucesso'; $alertaMensagem = $flashOk; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>
<?php if (!empty($erro)): ?>
    <?php $alertaTipo = 'erro'; $alertaMensagem = $erro; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<section class="stat-grid">
    <div class="stat-card"><div class="stat-card__head"><div class="stat-card__label">Valor total</div></div><div class="stat-card__value"><?= $brl($d['valor_total']) ?></div><div class="stat-card__delta"><?= (int) $d['qtd_parcelas'] ?> parcela(s)</div></div>
    <div class="stat-card"><div class="stat-card__head"><div class="stat-card__label">Já pago</div></div><div class="stat-card__value"><?= $brl($d['valor_pago']) ?></div></div>
    <div class="stat-card"><div class="stat-card__head"><div class="stat-card__label">Ainda pendente</div></div><div class="stat-card__value"><?= $brl($d['valor_pendente']) ?></div><div class="stat-card__delta"><?= $d['proximo_vencimento'] ? 'próximo vencimento ' . $data($d['proximo_vencimento']) : 'sem vencimentos pendentes' ?></div></div>
</section>

<div class="dp-grid" style="margin-top:16px;">
<div class="dp-col">

    <section class="card">
        <div class="card__header"><div class="card__title">Parcelas</div></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Parcela</th><th>Vencimento</th><th style="text-align:right;">Valor</th><th>Situação</th><th>Ação</th></tr></thead>
                <tbody>
                <?php foreach ($parcelas as $p):
                    $atrasada = $p['status'] === 'PENDENTE' && $p['vencimento'] < date('Y-m-d'); ?>
                    <tr style="<?= $p['status'] === 'CANCELADA' ? 'opacity:.55;' : '' ?>">
                        <td><?= (int) $p['numero'] ?> de <?= (int) $d['qtd_parcelas'] ?>
                            <?php if ($p['fatura_id']): ?><div class="fs-12"><a href="./viewFatura?id=<?= (int) $p['fatura_id'] ?>">fatura <?= date('m/Y', strtotime($p['fatura_vence_em'])) ?></a></div><?php endif; ?></td>
                        <td><?= $data($p['vencimento']) ?></td>
                        <td style="text-align:right;"><?= $brl($p['valor']) ?></td>
                        <td>
                            <?php if ($p['status'] === 'PAGA'): ?><span class="badge badge--success">Paga</span><div class="text-muted fs-12">em <?= $data($p['pago_em']) ?></div>
                            <?php elseif ($p['status'] === 'CANCELADA'): ?><span class="badge badge--neutral">Cancelada</span>
                            <?php elseif ($atrasada): ?><span class="badge badge--danger">Vencida</span>
                            <?php else: ?><span class="badge badge--neutral">A vencer</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p['status'] === 'PENDENTE' && $p['fatura_id']): ?>
                                <a class="btn btn--ghost btn--sm" href="./viewFatura?id=<?= (int) $p['fatura_id'] ?>">Pagar pela fatura</a>
                            <?php elseif ($p['status'] === 'PENDENTE' && !$cancelada): ?>
                                <details>
                                    <summary class="btn btn--primary btn--sm" style="list-style:none;cursor:pointer;">Pagar</summary>
                                    <form action="./pagarParcelaConta" method="POST" style="margin-top:8px;min-width:240px;" onsubmit="return confirm('Registrar o pagamento desta parcela?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id_despesa" value="<?= $id ?>"><input type="hidden" name="id_parcela" value="<?= (int) $p['id_conta_parcela'] ?>">
                                        <input type="hidden" name="chave" value="<?= $e($chave . '-' . $p['id_conta_parcela']) ?>">
                                        <div class="form-field"><label>Forma</label>
                                            <select name="forma" data-no-ts required><?php foreach ($formas as $chaveForma => $nome): ?><option value="<?= $e($chaveForma) ?>"><?= $e($nome) ?></option><?php endforeach; ?></select></div>
                                        <div class="form-field" style="margin-top:6px;"><label>Data do pagamento</label><input type="date" name="data" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>"></div>
                                        <label class="fs-12" style="display:flex;gap:6px;align-items:center;margin-top:6px;"><input type="checkbox" name="lancar_no_caixa" value="1"> Lançar a saída no caixa aberto</label>
                                        <button type="submit" class="btn btn--primary btn--sm" style="margin-top:6px;">Confirmar pagamento</button>
                                    </form>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card__header"><div class="card__title">Pagamentos</div></div>
        <div class="card__body" style="padding:8px 20px 16px;">
            <?php if (empty($pagamentos)): ?>
                <p class="text-muted" style="font-size:13px;margin:8px 0;">Nenhum pagamento ainda.</p>
            <?php else: foreach ($pagamentos as $pg): $est = $pg['estornado_em'] !== null; ?>
                <div style="padding:10px 0;border-bottom:1px solid var(--color-border);<?= $est ? 'opacity:.65;' : '' ?>">
                    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                        <div>
                            <strong><?= $brl($pg['valor']) ?></strong>
                            <span class="text-muted fs-12">· <?= $pg['tipo'] === 'FATURA' ? 'pagamento da fatura' : 'parcela' ?> · <?= $e(ContaPagar::FORMAS[$pg['forma_pagamento']] ?? $pg['forma_pagamento']) ?> · <?= date('d/m/Y H:i', strtotime($pg['pago_em'])) ?> · <?= $e($pg['usuario_nome'] ?? '—') ?></span>
                            <?php if ($est): ?><span class="badge badge--danger">Estornado</span><div class="fs-12 text-muted">por <?= $e($pg['estornado_por_nome'] ?? '—') ?>: <?= $e($pg['estorno_justificativa']) ?></div><?php endif; ?>
                            <?php if ($pg['movimentacao_caixa_id']): ?><div class="fs-12 text-muted"><i class="bi bi-cash-coin"></i> saída lançada no caixa</div><?php endif; ?>
                        </div>
                        <?php if (!$est): ?>
                            <details>
                                <summary style="cursor:pointer;font-size:12.5px;font-weight:600;color:var(--color-danger);">Estornar</summary>
                                <form action="./estornarPagamentoConta" method="POST" style="margin-top:6px;min-width:240px;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id_pagamento" value="<?= (int) $pg['id_pagamento'] ?>"><input type="hidden" name="id_despesa" value="<?= $id ?>">
                                    <input type="text" name="justificativa" maxlength="255" required placeholder="Motivo do estorno" style="width:100%;">
                                    <button type="submit" class="btn btn--danger btn--sm" style="margin-top:6px;" onclick="return confirm('Estornar este pagamento?')">Confirmar estorno</button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </section>

    <section class="card">
        <div class="card__header"><div class="card__title">Comprovantes</div><span class="text-muted fs-12">PDF, JPG, PNG ou WEBP · até 5 MB</span></div>
        <div class="card__body" style="padding:12px 20px 18px;">
            <?php if (empty($comprovantes)): ?>
                <p class="text-muted" style="font-size:13px;margin:0 0 12px;">Nenhum comprovante anexado.</p>
            <?php else: ?>
                <ul style="list-style:none;margin:0 0 12px;padding:0;display:flex;flex-direction:column;gap:6px;">
                    <?php foreach ($comprovantes as $c): ?>
                        <li style="font-size:13px;display:flex;justify-content:space-between;gap:10px;">
                            <a href="./baixarComprovante?id=<?= (int) $c['id_comprovante'] ?>" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> <?= $e($c['nome_original']) ?></a>
                            <span class="text-muted fs-12"><?= number_format($c['tamanho'] / 1024, 0, ',', '.') ?> KB · <?= date('d/m/Y', strtotime($c['enviado_em'])) ?> · <?= $e($c['enviado_por_nome'] ?? '—') ?><?= $c['pagamento_id'] ? ' · de um pagamento' : '' ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <form action="./enviarComprovante" method="POST" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                <?= csrf_field() ?>
                <input type="hidden" name="id_despesa" value="<?= $id ?>">
                <input type="file" name="arquivo" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*" required>
                <button type="submit" class="btn btn--ghost btn--sm">Anexar</button>
            </form>
        </div>
    </section>
</div>

<div class="dp-col">
    <section class="card">
        <div class="card__header"><div class="card__title">Origem</div></div>
        <div class="card__body" style="padding:16px 20px;font-size:13px;">
            <dl style="display:grid;grid-template-columns:100px 1fr;gap:6px 10px;margin:0;">
                <dt class="text-muted">Compra em</dt><dd style="margin:0;"><?= $data($d['data_compra']) ?></dd>
                <dt class="text-muted">Fornecedor</dt><dd style="margin:0;"><?= $e($d['fornecedor_nome'] ?? '—') ?></dd>
                <?php if ($d['cartao_id']): ?><dt class="text-muted">Cartão</dt><dd style="margin:0;"><a href="./viewCartao?id=<?= (int) $d['cartao_id'] ?>"><?= $e($d['cartao_apelido']) ?></a></dd><?php endif; ?>
                <?php if ($d['origem'] === 'PEDIDO_COMPRA'): ?><dt class="text-muted">Pedido</dt><dd style="margin:0;"><a href="./viewPedidoCompra?id=<?= (int) $d['origem_id'] ?>">pedido de compra #<?= (int) $d['origem_id'] ?></a></dd><?php endif; ?>
                <dt class="text-muted">Registrada por</dt><dd style="margin:0;"><?= $e($d['criado_por_nome'] ?? '—') ?> · <?= date('d/m/Y H:i', strtotime($d['criada_em'])) ?></dd>
                <?php if (!empty($d['observacoes'])): ?><dt class="text-muted">Observações</dt><dd style="margin:0;"><?= $e($d['observacoes']) ?></dd><?php endif; ?>
                <?php if ($cancelada): ?><dt class="text-muted">Cancelamento</dt><dd style="margin:0;"><?= $e($d['cancelamento_motivo']) ?></dd><?php endif; ?>
            </dl>
            <?php if ($noCartao): ?>
                <p class="text-muted fs-12" style="margin:10px 0 0;">Compra no cartão: o dinheiro só sai quando a <strong>fatura</strong> é paga; as parcelas acima são as partes dela em cada fatura.</p>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!$cancelada): ?>
        <section class="card">
            <div class="card__header"><div class="card__title">Ações</div></div>
            <div class="card__body" style="padding:16px 20px;">
                <details>
                    <summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--color-danger);">Cancelar despesa</summary>
                    <form action="./cancelarDespesa" method="POST" style="margin-top:8px;">
                        <?= csrf_field() ?><input type="hidden" name="id_despesa" value="<?= $id ?>">
                        <div class="form-field"><label>Motivo <span style="color:var(--color-danger)">*</span></label><input type="text" name="motivo" maxlength="255" required></div>
                        <p class="text-muted fs-12" style="margin:6px 0;">Só é possível se nenhuma parcela foi paga (estorne o pagamento antes).</p>
                        <button type="submit" class="btn btn--danger btn--sm" onclick="return confirm('Cancelar esta despesa?')">Confirmar cancelamento</button>
                    </form>
                </details>
            </div>
        </section>
    <?php endif; ?>

    <section class="card">
        <div class="card__header"><div class="card__title">Histórico</div></div>
        <div class="card__body" style="padding:14px 16px;">
            <ol style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px;">
                <?php foreach (array_reverse($linhaDoTempo) as $h): ?>
                    <li style="padding:10px 12px;background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);font-size:12.5px;">
                        <strong><?= $e(str_replace('_', ' ', ucfirst(strtolower($h['estado_novo'])))) ?></strong>
                        <div class="text-muted" style="font-size:11.5px;"><?= date('d/m/Y H:i', strtotime($h['criado_em'])) ?> · <?= $e($h['usuario_nome'] ?? 'Sistema') ?></div>
                        <?php if (!empty($h['motivo'])): ?><div style="margin-top:3px;"><?= $e($h['motivo']) ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>
</div>
</div>
