<?php
/**
 * Nova despesa. Espera: $fornecedores, $cartoes, $categorias, $meios, $chave, $old, $error.
 */
$e = fn($v) => htmlspecialchars((string) $v);
$old = $old ?? [];
$v = fn($k, $d = '') => $e($old[$k] ?? $d);
$meio = $old['meio_pagamento'] ?? 'BOLETO';
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Nova despesa</h1>
        <p class="page-header__subtitle">Registre a compra ou o gasto uma vez: as parcelas, as faturas e os pagamentos saem dela.</p>
    </div>
    <div class="page-header__actions"><a href="./listContasPagar" class="btn btn--ghost">Voltar</a></div>
</div>

<?php if (!empty($error)): ?>
    <?php $alertaTipo = 'erro'; $alertaMensagem = $error; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<form action="./saveDespesa" method="POST" id="formDespesa">
    <?= csrf_field() ?>
    <input type="hidden" name="chave" value="<?= $e($chave) ?>">

    <section class="card" style="margin-bottom:16px;">
        <div class="card__header"><div class="card__title">A despesa</div></div>
        <div class="card__body" style="padding:20px;">
            <div class="form-grid">
                <div class="form-field" style="grid-column:1 / -1;"><label for="descricao">Descrição <span style="color:var(--color-danger)">*</span></label>
                    <input type="text" name="descricao" id="descricao" maxlength="200" required placeholder="Ex.: Amortecedor dianteiro — fornecedor X" value="<?= $v('descricao') ?>"></div>
                <div class="form-field"><label for="categoria">Categoria <span style="color:var(--color-danger)">*</span></label>
                    <select name="categoria" id="categoria" data-no-ts required>
                        <option value="">Escolha...</option>
                        <?php foreach ($categorias as $chaveCat => [$rotulo]): ?>
                            <option value="<?= $e($chaveCat) ?>" <?= ($old['categoria'] ?? '') === $chaveCat ? 'selected' : '' ?>><?= $e($rotulo) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted fs-12">Peças para estoque só viram custo quando são usadas numa OS.</small></div>
                <div class="form-field"><label for="fornecedor_id">Fornecedor</label>
                    <select name="fornecedor_id" id="fornecedor_id">
                        <option value="">—</option>
                        <?php foreach ($fornecedores as $f): if (!(int) $f['ativo']) continue; ?>
                            <option value="<?= (int) $f['id_fornecedor'] ?>" <?= (string) ($old['fornecedor_id'] ?? '') === (string) $f['id_fornecedor'] ? 'selected' : '' ?>><?= $e($f['nome']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-field"><label for="valor_total">Valor total (R$) <span style="color:var(--color-danger)">*</span></label>
                    <input type="text" inputmode="decimal" name="valor_total" id="valor_total" required value="<?= $v('valor_total') ?>"></div>
                <div class="form-field"><label for="data_compra">Data da compra <span style="color:var(--color-danger)">*</span></label>
                    <input type="date" name="data_compra" id="data_compra" required value="<?= $v('data_compra', date('Y-m-d')) ?>"></div>
            </div>
        </div>
    </section>

    <section class="card" style="margin-bottom:16px;">
        <div class="card__header"><div class="card__title">Como será pago</div></div>
        <div class="card__body" style="padding:20px;">
            <div class="form-grid">
                <div class="form-field"><label for="meio_pagamento">Meio de pagamento <span style="color:var(--color-danger)">*</span></label>
                    <select name="meio_pagamento" id="meio_pagamento" data-no-ts required onchange="atualizarMeio()">
                        <?php foreach ($meios as $chaveMeio => $rotulo): ?>
                            <option value="<?= $e($chaveMeio) ?>" <?= $meio === $chaveMeio ? 'selected' : '' ?>><?= $e($rotulo) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-field" id="blocoCartao" style="display:none;"><label for="cartao_id">Cartão <span style="color:var(--color-danger)">*</span></label>
                    <select name="cartao_id" id="cartao_id" data-no-ts>
                        <option value="">Escolha...</option>
                        <?php foreach ($cartoes as $c): ?>
                            <option value="<?= (int) $c['id_cartao'] ?>" <?= (string) ($old['cartao_id'] ?? '') === (string) $c['id_cartao'] ? 'selected' : '' ?>>
                                <?= $e($c['apelido']) ?> — fecha dia <?= (int) $c['dia_fechamento'] ?>, vence dia <?= (int) $c['dia_vencimento'] ?><?= $c['limite_disponivel'] !== null ? ' · disponível R$ ' . number_format($c['limite_disponivel'], 2, ',', '.') : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($cartoes)): ?><small class="text-muted fs-12">Nenhum cartão cadastrado: <a href="./saveCartao">cadastrar cartão</a>.</small><?php endif; ?></div>
                <div class="form-field"><label for="qtd_parcelas">Parcelas</label>
                    <input type="number" min="1" max="48" name="qtd_parcelas" id="qtd_parcelas" value="<?= $v('qtd_parcelas', '1') ?>" oninput="previa()"></div>
                <div class="form-field" id="blocoVencimento"><label for="primeiro_vencimento">Primeiro vencimento</label>
                    <input type="date" name="primeiro_vencimento" id="primeiro_vencimento" value="<?= $v('primeiro_vencimento') ?>">
                    <small class="text-muted fs-12">Em branco: a data da compra (à vista) ou +30 dias (parcelado).</small></div>
            </div>

            <p id="previaParcelas" class="text-muted" style="font-size:12.5px;margin:12px 0 0;"></p>
            <p id="avisoCartao" class="text-muted" style="font-size:12.5px;margin:6px 0 0;display:none;">
                No cartão, cada parcela cai na fatura do seu mês e é quitada quando a <strong>fatura</strong> é paga — a compra, as parcelas e o pagamento da fatura são uma despesa só.
            </p>

            <div id="blocoPagoAgora" style="margin-top:14px;padding:12px 16px;background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);">
                <label style="display:flex;gap:8px;align-items:center;font-size:13px;font-weight:600;">
                    <input type="checkbox" name="pago_agora" value="1" id="pago_agora" <?= !empty($old['pago_agora']) ? 'checked' : '' ?> onchange="document.getElementById('detalhePago').style.display = this.checked ? 'block' : 'none'">
                    Já foi paga (registrar o pagamento da 1ª parcela agora)
                </label>
                <div id="detalhePago" style="display:<?= !empty($old['pago_agora']) ? 'block' : 'none' ?>;margin-top:10px;">
                    <div class="form-grid">
                        <div class="form-field"><label>Forma</label>
                            <select name="forma_pagamento" data-no-ts>
                                <?php foreach (ContaPagar::FORMAS as $chaveForma => $nome): ?><option value="<?= $e($chaveForma) ?>"><?= $e($nome) ?></option><?php endforeach; ?>
                            </select></div>
                    </div>
                    <label style="display:flex;gap:6px;align-items:center;font-size:12.5px;margin-top:8px;"><input type="checkbox" name="lancar_no_caixa" value="1"> Lançar a saída no caixa aberto</label>
                </div>
            </div>

            <div class="form-field" style="margin-top:14px;"><label for="observacoes">Observações</label><textarea name="observacoes" id="observacoes" rows="2"><?= $v('observacoes') ?></textarea></div>
        </div>
    </section>

    <div style="display:flex;gap:10px;justify-content:flex-end;">
        <a href="./listContasPagar" class="btn btn--ghost">Cancelar</a>
        <button type="submit" class="btn btn--primary">Registrar despesa</button>
    </div>
</form>

<script>
    function parseBR(v) { v = String(v || '').trim(); if (v.indexOf(',') >= 0) v = v.replace(/\./g, '').replace(',', '.'); var n = parseFloat(v); return isNaN(n) ? 0 : n; }

    function atualizarMeio() {
        var meio = document.getElementById('meio_pagamento').value;
        var cartao = meio === 'CARTAO_CREDITO';
        document.getElementById('blocoCartao').style.display = cartao ? '' : 'none';
        document.getElementById('blocoVencimento').style.display = cartao ? 'none' : '';
        document.getElementById('blocoPagoAgora').style.display = cartao ? 'none' : '';
        document.getElementById('avisoCartao').style.display = cartao ? 'block' : 'none';
        previa();
    }

    function previa() {
        var total = parseBR(document.getElementById('valor_total').value);
        var n = Math.max(1, Math.min(48, parseInt(document.getElementById('qtd_parcelas').value, 10) || 1));
        var alvo = document.getElementById('previaParcelas');
        if (total <= 0) { alvo.textContent = ''; return; }
        var base = Math.floor(total * 100 / n) / 100, ultima = Math.round((total - base * (n - 1)) * 100) / 100;
        var f = function (x) { return x.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); };
        alvo.textContent = n === 1 ? 'Uma parcela de ' + f(total) + '.' : (n - 1) + ' parcela(s) de ' + f(base) + ' + 1 de ' + f(ultima) + ' (a sobra de centavos vai na última).';
    }

    document.getElementById('valor_total').addEventListener('input', previa);
    atualizarMeio();
</script>
