<?php
/**
 * Cadastro/edição de cartão. Espera: $cartao, $error, $novo, $bandeiras.
 * Só 4 últimos dígitos (opcionais): o sistema nunca guarda número, validade nem código do cartão.
 */
$e = fn($v) => htmlspecialchars((string) $v);
$c = $cartao ?? [];
$v = fn($k) => $e($c[$k] ?? '');
$limite = isset($c['limite_total']) && $c['limite_total'] !== null && $c['limite_total'] !== '' ? $e(is_numeric($c['limite_total']) ? number_format((float) $c['limite_total'], 2, ',', '') : $c['limite_total']) : '';
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= $novo ? 'Novo cartão' : 'Editar cartão' ?></h1>
        <p class="page-header__subtitle">Informe só o que o controle precisa: apelido, emissor, limite e as datas da fatura.</p>
    </div>
    <div class="page-header__actions"><a href="./listCartoes" class="btn btn--ghost">Voltar</a></div>
</div>

<?php if (!empty($error)): ?>
    <?php $alertaTipo = 'erro'; $alertaMensagem = $error; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<form action="<?= $novo ? './saveCartao' : './editCartao' ?>" method="POST">
    <?= csrf_field() ?>
    <?php if (!$novo): ?><input type="hidden" name="id_cartao" value="<?= (int) $c['id_cartao'] ?>"><?php endif; ?>

    <section class="card" style="margin-bottom:16px;">
        <div class="card__body" style="padding:20px;">
            <div class="form-grid">
                <div class="form-field"><label for="apelido">Apelido <span style="color:var(--color-danger)">*</span></label><input type="text" name="apelido" id="apelido" maxlength="80" required placeholder="Ex.: Cartão da oficina" value="<?= $v('apelido') ?>"></div>
                <div class="form-field"><label for="instituicao">Banco / emissor <span style="color:var(--color-danger)">*</span></label><input type="text" name="instituicao" id="instituicao" maxlength="80" required value="<?= $v('instituicao') ?>"></div>
                <div class="form-field"><label for="bandeira">Bandeira</label>
                    <select name="bandeira" id="bandeira" data-no-ts><option value="">—</option>
                        <?php foreach ($bandeiras as $b): ?><option value="<?= $e($b) ?>" <?= ($c['bandeira'] ?? '') === $b ? 'selected' : '' ?>><?= $e($b) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-field"><label for="final_cartao">Últimos 4 dígitos</label><input type="text" name="final_cartao" id="final_cartao" maxlength="4" inputmode="numeric" pattern="\d{4}" placeholder="opcional" value="<?= $v('final_cartao') ?>"></div>
                <div class="form-field"><label for="limite_total">Limite total (R$)</label><input type="text" inputmode="decimal" name="limite_total" id="limite_total" placeholder="se souber" value="<?= $limite ?>"></div>
                <div class="form-field"><label for="dia_fechamento">Dia de fechamento <span style="color:var(--color-danger)">*</span></label><input type="number" min="1" max="28" name="dia_fechamento" id="dia_fechamento" required value="<?= $v('dia_fechamento') ?>"></div>
                <div class="form-field"><label for="dia_vencimento">Dia de vencimento <span style="color:var(--color-danger)">*</span></label><input type="number" min="1" max="28" name="dia_vencimento" id="dia_vencimento" required value="<?= $v('dia_vencimento') ?>"></div>
            </div>
            <p class="text-muted fs-12" style="margin:12px 0 0;">
                Compras feitas até o dia do fechamento entram na fatura que fecha naquele mês; depois dele, na do mês seguinte. Dias de 1 a 28, para existirem em todo mês.
                Não informe número, validade nem código do cartão — o sistema não precisa e não guarda.
            </p>
        </div>
    </section>

    <div style="display:flex;gap:10px;justify-content:flex-end;">
        <a href="./listCartoes" class="btn btn--ghost">Cancelar</a>
        <button type="submit" class="btn btn--primary"><?= $novo ? 'Cadastrar' : 'Salvar' ?></button>
    </div>
</form>
