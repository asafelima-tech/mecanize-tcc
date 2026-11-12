<?php
/**
 * Fatura do cartão: as compras que a compõem, o pagamento e o histórico.
 *
 * Espera: $fatura, $compras, $pagamentos (cada um com 'comprovantes'), $formas, $chave, $sucesso, $erro.
 */
require_once __DIR__ . '/../../models/FaturaCartao.php';

$f = $fatura;
$id = (int) $f['id_fatura'];
$e = fn($v) => htmlspecialchars((string) $v);
$brl = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$data = fn($x) => $x ? date('d/m/Y', strtotime($x)) : '—';
$situacao = $f['situacao'];
$podePagar = $f['pendente'] > 0 && $f['paga_em'] === null && $f['fecha_em'] <= date('Y-m-d');

$mensagens = ['paga' => 'Fatura paga: as parcelas dela foram quitadas.', 'japaga' => 'Este pagamento já tinha sido registrado.', 'estornado' => 'Pagamento estornado: a fatura voltou a ficar em aberto.', 'comprovante' => 'Comprovante anexado.'];
$flashOk = $mensagens[$sucesso ?? ''] ?? null;
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Fatura <?= date('m/Y', strtotime($f['vence_em'])) ?> · <?= $e($f['cartao_apelido']) ?></h1>
        <p class="page-header__subtitle">
            <span class="badge <?= FaturaCartao::SITUACAO_BADGE[$situacao] ?>"><span class="badge__dot"></span><?= $e(FaturaCartao::SITUACAO_LABEL[$situacao]) ?></span>
            fecha em <?= $data($f['fecha_em']) ?> · vence em <?= $data($f['vence_em']) ?>
        </p>
    </div>
    <div class="page-header__actions"><a href="./viewCartao?id=<?= (int) $f['cartao_id'] ?>" class="btn btn--ghost">Voltar ao cartão</a></div>
</div>

<?php if ($flashOk): ?>
    <?php $alertaTipo = 'sucesso'; $alertaMensagem = $flashOk; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>
<?php if (!empty($erro)): ?>
    <?php $alertaTipo = 'erro'; $alertaMensagem = $erro; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<section class="stat-grid">
    <div class="stat-card"><div class="stat-card__head"><div class="stat-card__label">Total da fatura</div></div><div class="stat-card__value"><?= $brl($f['total']) ?></div><div class="stat-card__delta"><?= (int) $f['qtd_compras'] ?> parcela(s) de compras</div></div>
    <div class="stat-card"><div class="stat-card__head"><div class="stat-card__label">Pendente</div></div><div class="stat-card__value"><?= $brl($f['pendente']) ?></div></div>
</section>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px;margin-top:16px;">
    <section class="card" style="grid-column:1 / -1;">
        <div class="card__header"><div class="card__title">Compras que compõem esta fatura</div></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Compra</th><th>Data</th><th>Parcela</th><th style="text-align:right;">Valor na fatura</th><th>Situação</th></tr></thead>
                <tbody>
                <?php if (empty($compras)): ?>
                    <tr><td colspan="5" style="text-align:center;padding:30px 20px;color:var(--color-text-muted);">Nenhuma compra.</td></tr>
                <?php else: foreach ($compras as $c): ?>
                    <tr>
                        <td><a href="./viewDespesa?id=<?= (int) $c['id_despesa'] ?>"><?= $e($c['descricao']) ?></a><div class="text-muted fs-12"><?= $e($c['despesa_numero']) ?><?= $c['fornecedor_nome'] ? ' · ' . $e($c['fornecedor_nome']) : '' ?> · total <?= $brl($c['valor_total']) ?></div></td>
                        <td><?= $data($c['data_compra']) ?></td>
                        <td><?= (int) $c['numero'] ?> de <?= (int) $c['qtd_parcelas'] ?></td>
                        <td style="text-align:right;"><strong><?= $brl($c['valor']) ?></strong></td>
                        <td><span class="badge <?= $c['status'] === 'PAGA' ? 'badge--success' : 'badge--neutral' ?>"><?= $c['status'] === 'PAGA' ? 'Paga' : 'Pendente' ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card__header"><div class="card__title">Pagar a fatura</div></div>
        <div class="card__body" style="padding:16px 20px;">
            <?php if ($f['paga_em'] !== null): ?>
                <p style="margin:0;font-size:13px;">Fatura paga em <?= $data($f['paga_em']) ?>.</p>
            <?php elseif (!$podePagar): ?>
                <p class="text-muted" style="margin:0;font-size:13px;"><?= $f['pendente'] <= 0 ? 'Nada pendente nesta fatura.' : 'A fatura ainda está aberta: novas compras podem entrar até ' . $data($f['fecha_em']) . '. Ela poderá ser paga depois que fechar.' ?></p>
            <?php else: ?>
                <form action="./pagarFaturaCartao" method="POST" onsubmit="return confirm('Pagar a fatura de <?= $e($brl($f['pendente'])) ?>? As parcelas dela serão quitadas.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_fatura" value="<?= $id ?>"><input type="hidden" name="chave" value="<?= $e($chave) ?>">
                    <p style="margin:0 0 10px;font-size:13px;">Valor: <strong><?= $brl($f['pendente']) ?></strong> — uma saída só, que quita todas as parcelas desta fatura. Não cria outra despesa.</p>
                    <div class="form-grid">
                        <div class="form-field"><label>Forma</label><select name="forma" data-no-ts required><?php foreach ($formas as $chaveForma => $nome): ?><option value="<?= $e($chaveForma) ?>"><?= $e($nome) ?></option><?php endforeach; ?></select></div>
                        <div class="form-field"><label>Data do pagamento</label><input type="date" name="data" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>"></div>
                    </div>
                    <label class="fs-12" style="display:flex;gap:6px;align-items:center;margin-top:8px;"><input type="checkbox" name="lancar_no_caixa" value="1"> Lançar a saída no caixa aberto</label>
                    <button type="submit" class="btn btn--primary" style="margin-top:12px;">Pagar fatura</button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <section class="card">
        <div class="card__header"><div class="card__title">Pagamentos e comprovantes</div></div>
        <div class="card__body" style="padding:8px 20px 16px;">
            <?php if (empty($pagamentos)): ?>
                <p class="text-muted" style="font-size:13px;margin:8px 0;">Nenhum pagamento.</p>
            <?php else: foreach ($pagamentos as $pg): $est = $pg['estornado_em'] !== null; ?>
                <div style="padding:10px 0;border-bottom:1px solid var(--color-border);<?= $est ? 'opacity:.65;' : '' ?>">
                    <strong><?= $brl($pg['valor']) ?></strong>
                    <span class="text-muted fs-12">· <?= $e(ContaPagar::FORMAS[$pg['forma_pagamento']] ?? $pg['forma_pagamento']) ?> · <?= date('d/m/Y H:i', strtotime($pg['pago_em'])) ?> · <?= $e($pg['usuario_nome'] ?? '—') ?></span>
                    <?php if ($est): ?><span class="badge badge--danger">Estornado</span><div class="fs-12 text-muted"><?= $e($pg['estorno_justificativa']) ?></div><?php endif; ?>
                    <?php foreach ($pg['comprovantes'] as $c): ?>
                        <div class="fs-12"><a href="./baixarComprovante?id=<?= (int) $c['id_comprovante'] ?>" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> <?= $e($c['nome_original']) ?></a></div>
                    <?php endforeach; ?>
                    <?php if (!$est): ?>
                        <form action="./enviarComprovante" method="POST" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-top:6px;">
                            <?= csrf_field() ?><input type="hidden" name="id_pagamento" value="<?= (int) $pg['id_pagamento'] ?>"><input type="hidden" name="id_fatura" value="<?= $id ?>">
                            <input type="file" name="arquivo" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*" required>
                            <button type="submit" class="btn btn--ghost btn--sm">Anexar comprovante</button>
                        </form>
                        <details style="margin-top:6px;">
                            <summary style="cursor:pointer;font-size:12.5px;font-weight:600;color:var(--color-danger);">Estornar</summary>
                            <form action="./estornarPagamentoConta" method="POST" style="margin-top:6px;">
                                <?= csrf_field() ?><input type="hidden" name="id_pagamento" value="<?= (int) $pg['id_pagamento'] ?>"><input type="hidden" name="id_fatura" value="<?= $id ?>">
                                <input type="text" name="justificativa" maxlength="255" required placeholder="Motivo do estorno" style="width:100%;">
                                <button type="submit" class="btn btn--danger btn--sm" style="margin-top:6px;" onclick="return confirm('Estornar o pagamento desta fatura?')">Confirmar estorno</button>
                            </form>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </section>
</div>
