<?php
/**
 * Cadastro/edição de fornecedor. Espera: $fornecedor (array), $error, $novo.
 */
$e = fn($v) => htmlspecialchars((string) $v);
$f = $fornecedor ?? [];
$v = fn($k) => $e($f[$k] ?? '');
$acao = $novo ? './saveFornecedor' : './editFornecedor';
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= $novo ? 'Novo fornecedor' : 'Editar fornecedor' ?></h1>
        <p class="page-header__subtitle">Quem fornece peças e materiais para a oficina.</p>
    </div>
    <div class="page-header__actions"><a href="./listFornecedores" class="btn btn--ghost">Voltar</a></div>
</div>

<?php if (!empty($error)): ?>
    <?php $alertaTipo = 'erro'; $alertaMensagem = $error; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<form action="<?= $acao ?>" method="POST">
    <?= csrf_field() ?>
    <?php if (!$novo): ?><input type="hidden" name="id_fornecedor" value="<?= (int) $f['id_fornecedor'] ?>"><?php endif; ?>

    <section class="card" style="margin-bottom:16px;">
        <div class="card__body" style="padding:20px;">
            <div class="form-grid">
                <div class="form-field"><label for="nome">Nome <span style="color:var(--color-danger)">*</span></label><input type="text" name="nome" id="nome" maxlength="150" required value="<?= $v('nome') ?>"></div>
                <div class="form-field"><label for="documento">CNPJ/CPF</label><input type="text" name="documento" id="documento" maxlength="20" value="<?= $v('documento') ?>"></div>
                <div class="form-field"><label for="contato">Contato</label><input type="text" name="contato" id="contato" maxlength="120" value="<?= $v('contato') ?>"></div>
                <div class="form-field"><label for="telefone">Telefone</label><input type="text" name="telefone" id="telefone" maxlength="30" value="<?= $v('telefone') ?>"></div>
                <div class="form-field"><label for="email">E-mail</label><input type="email" name="email" id="email" maxlength="150" value="<?= $v('email') ?>"></div>
                <div class="form-field"><label for="site_url">Site</label><input type="text" name="site_url" id="site_url" maxlength="255" placeholder="www.fornecedor.com.br" value="<?= $v('site_url') ?>"></div>
                <div class="form-field"><label for="condicoes_pagamento">Condições de pagamento</label><input type="text" name="condicoes_pagamento" id="condicoes_pagamento" maxlength="150" placeholder="Ex.: boleto 28 dias" value="<?= $v('condicoes_pagamento') ?>"></div>
                <div class="form-field"><label for="prazo_medio_dias">Prazo médio de entrega (dias)</label><input type="number" min="0" name="prazo_medio_dias" id="prazo_medio_dias" value="<?= $v('prazo_medio_dias') ?>"></div>
            </div>
            <div class="form-field" style="margin-top:12px;"><label for="observacoes">Observações</label><textarea name="observacoes" id="observacoes" rows="2"><?= $v('observacoes') ?></textarea></div>
            <p class="text-muted fs-12" style="margin:12px 0 0;">O site é só um atalho para abrir a página do fornecedor: o pedido é feito lá e registrado aqui. Não há compra automática.</p>
        </div>
    </section>

    <div style="display:flex;gap:10px;justify-content:flex-end;">
        <a href="./listFornecedores" class="btn btn--ghost">Cancelar</a>
        <button type="submit" class="btn btn--primary"><?= $novo ? 'Cadastrar' : 'Salvar' ?></button>
    </div>
</form>
