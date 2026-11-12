<?php
/**
 * Solicitar reposição de peça (técnico, atendente ou administrador).
 *
 * Espera: $produtos (id, nome, codigo, estoque, disponivel, localizacao), $ordens, $urgencias, $old, $error.
 */
$e = fn($v) => htmlspecialchars((string) $v);
$old = $old ?? [];
$v = fn($k, $d = '') => $e($old[$k] ?? $d);
$qtd = fn($x) => rtrim(rtrim(number_format((float) $x, 3, ',', '.'), '0'), ',') ?: '0';
$produtoSel = (string) ($old['produto_id'] ?? '');
$semCadastro = $produtoSel === '' && !empty($old['descricao']);
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Solicitar peça</h1>
        <p class="page-header__subtitle">Avise o administrador que uma peça está acabando ou faltando. Isto não é a compra.</p>
    </div>
    <div class="page-header__actions">
        <a href="./listSolicitacoes" class="btn btn--ghost">Voltar</a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <?php $alertaTipo = 'erro'; $alertaMensagem = $error; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<form action="./saveSolicitacao" method="POST">
    <?= csrf_field() ?>

    <section class="card" style="margin-bottom:16px;">
        <div class="card__header"><div class="card__title">Peça</div></div>
        <div class="card__body" style="padding:20px;">
            <div class="form-field">
                <label for="produto_id">Peça do estoque</label>
                <select name="produto_id" id="produto_id" onchange="atualizarPeca()">
                    <option value="">— peça que não está no cadastro —</option>
                    <?php foreach ($produtos as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" data-estoque="<?= $e($qtd($p['estoque'])) ?>" data-disponivel="<?= $e($qtd($p['disponivel'])) ?>" data-local="<?= $e($p['localizacao']) ?>"
                            <?= $produtoSel === (string) $p['id'] ? 'selected' : '' ?>>
                            <?= $e($p['nome']) ?> (<?= $e($p['codigo']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted fs-12" id="infoEstoque"></small>
            </div>

            <div class="form-field" id="blocoDescricao" style="margin-top:12px;<?= $produtoSel !== '' ? 'display:none;' : '' ?>">
                <label for="descricao">Descreva a peça <span style="color:var(--color-danger)">*</span></label>
                <input type="text" name="descricao" id="descricao" maxlength="200" placeholder="Ex.: Coxim do motor Gol G5 1.6" value="<?= $v('descricao') ?>">
                <small class="text-muted fs-12">Peça fora do cadastro: o administrador cadastra o produto antes de comprar.</small>
            </div>

            <div class="form-grid" style="margin-top:12px;">
                <div class="form-field">
                    <label for="quantidade">Quantidade necessária <span style="color:var(--color-danger)">*</span></label>
                    <input type="text" inputmode="decimal" name="quantidade" id="quantidade" required value="<?= $v('quantidade', '1') ?>">
                </div>
                <div class="form-field">
                    <label for="urgencia">Urgência</label>
                    <select name="urgencia" id="urgencia" data-no-ts>
                        <?php foreach ($urgencias as $chave => $rotulo): ?>
                            <option value="<?= $e($chave) ?>" <?= ($old['urgencia'] ?? 'NORMAL') === $chave ? 'selected' : '' ?>><?= $e($rotulo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </section>

    <section class="card" style="margin-bottom:16px;">
        <div class="card__header"><div class="card__title">Motivo</div></div>
        <div class="card__body" style="padding:20px;">
            <div class="form-field">
                <label for="motivo">Por que precisa? <span style="color:var(--color-danger)">*</span></label>
                <input type="text" name="motivo" id="motivo" maxlength="255" required placeholder="Ex.: estoque acabando, peça para a OS do cliente, item danificado..." value="<?= $v('motivo') ?>">
            </div>
            <div class="form-field" style="margin-top:12px;">
                <label for="ordem_servico_id">Ordem de serviço relacionada (se houver)</label>
                <select name="ordem_servico_id" id="ordem_servico_id">
                    <option value="">Nenhuma</option>
                    <?php foreach ($ordens as $o): ?>
                        <option value="<?= (int) $o['id'] ?>" <?= (string) ($old['ordem_servico_id'] ?? '') === (string) $o['id'] ? 'selected' : '' ?>>
                            <?= $e($o['numero']) ?> — <?= $e($o['cliente']) ?> (<?= $e(strtoupper($o['placa'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted fs-12">Se a OS tem técnico responsável, ele também é avisado.</small>
            </div>
            <div class="form-field" style="margin-top:12px;">
                <label for="observacoes">Observações</label>
                <textarea name="observacoes" id="observacoes" rows="2" placeholder="Marca preferida, aplicação, código do fornecedor..."><?= $v('observacoes') ?></textarea>
            </div>
        </div>
    </section>

    <div style="display:flex;gap:10px;justify-content:flex-end;">
        <a href="./listSolicitacoes" class="btn btn--ghost">Cancelar</a>
        <button type="submit" class="btn btn--primary">Enviar solicitação</button>
    </div>
</form>

<script>
    function atualizarPeca() {
        var sel = document.getElementById('produto_id');
        var opt = sel.options[sel.selectedIndex];
        var info = document.getElementById('infoEstoque');
        var bloco = document.getElementById('blocoDescricao');

        if (!sel.value) {
            info.textContent = '';
            bloco.style.display = '';
            return;
        }

        bloco.style.display = 'none';
        document.getElementById('descricao').value = '';
        info.textContent = 'Em estoque: ' + opt.dataset.estoque + ' · disponível (sem as reservas): ' + opt.dataset.disponivel + (opt.dataset.local ? ' · local: ' + opt.dataset.local : '');
    }
    atualizarPeca();
</script>
