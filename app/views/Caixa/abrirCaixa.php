<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Variáveis esperadas:
// $error         string — mensagem de erro (opcional)
// $old           array  — dados do POST anterior (opcional)
// $saldoAnterior float  — fechamento da última sessão (saldo do caixa anterior)

$saldoAnterior = (float) ($saldoAnterior ?? 0);
// Valor sugerido para abertura: o saldo anterior, em formato cru (ponto decimal).
$valorSugerido = $old['valor_abertura'] ?? number_format($saldoAnterior, 2, '.', '');
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Abrir Caixa</h1>
        <p class="page-header__subtitle">É obrigatório abrir o caixa para começar a utilizar o sistema.</p>
    </div>
</div>

<section class="card" style="max-width:560px;margin:0 auto;">
    <div class="card__body" style="padding:24px;">

        <?php if (!empty($error)): ?>
            <div style="
                background: var(--color-danger-soft);
                color: #991B1B;
                border: 1px solid #FECACA;
                border-radius: var(--radius-md);
                padding: 12px 16px;
                margin-bottom: 20px;
                font-size: 13px;
            ">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Quem está abrindo -->
        <div style="
            display:flex; align-items:center; gap:12px;
            background: var(--color-primary-soft);
            border-radius: var(--radius-md);
            padding: 12px 16px; margin-bottom: 20px;
        ">
            <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#3B82F6,#1E40AF);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">
                <?= strtoupper(substr($_SESSION['user_nome'] ?? 'U', 0, 1) . substr($_SESSION['user_nome'] ?? 'U', -1)) ?>
            </div>
            <div>
                <div style="font-weight:600;font-size:14px;color:var(--color-text);">
                    <?= htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário') ?>
                </div>
                <div style="font-size:12px;color:var(--color-text-muted);">
                    Abrindo o caixa em <?= date('d/m/Y \à\s H:i') ?>
                </div>
            </div>
        </div>

        <!-- Saldo do caixa anterior -->
        <div style="
            display:flex; align-items:center; justify-content:space-between;
            border:1px dashed var(--color-border);
            border-radius: var(--radius-md);
            padding: 10px 14px; margin-bottom: 18px; font-size: 13px;
        ">
            <span style="color:var(--color-text-muted);">Saldo do caixa anterior (último fechamento)</span>
            <strong style="font-size:15px;color:var(--color-text);">R$ <?= number_format($saldoAnterior, 2, ',', '.') ?></strong>
        </div>

        <form action="./abrirCaixa" method="POST" id="formAbrirCaixa">

            <div class="form-field" style="margin-bottom:16px;">
                <label>Valor de abertura (troco inicial) <span style="color:var(--color-danger)">*</span></label>
                <input
                    type="text"
                    inputmode="numeric"
                    name="valor_abertura"
                    id="valor_abertura"
                    placeholder="0,00"
                    value="<?= htmlspecialchars($valorSugerido) ?>"
                    required>
                <small style="display:block;margin-top:6px;font-size:12px;color:var(--color-text-muted);">
                    Sugerido a partir do fechamento anterior — ajuste se o troco inicial for diferente.
                </small>
            </div>

            <div class="form-field" style="margin-bottom:20px;">
                <label>Observação (opcional)</label>
                <textarea
                    name="observacao"
                    rows="2"
                    placeholder="Ex.: caixa aberto no início do expediente"
                    style="resize:vertical;"><?= htmlspecialchars($old['observacao'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn--primary" style="width:100%;justify-content:center;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="7" width="20" height="14" rx="2" />
                    <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" />
                    <circle cx="12" cy="14" r="2" />
                </svg>
                Abrir Caixa
            </button>
        </form>
    </div>
</section>

<script>
    // Máscara de dinheiro (IMask): exibe 1.234,56 e envia o valor cru (1234.56) no submit
    (function () {
        const input = document.getElementById('valor_abertura');
        if (!input || typeof IMask === 'undefined') return;

        const m = IMask(input, {
            mask: Number,
            scale: 2,
            thousandsSeparator: '.',
            radix: ',',
            padFractionalZeros: true,
            normalizeZeros: true,
            min: 0
        });

        // Valor inicial chega com ponto decimal (ex.: "0.00")
        m.typedValue = parseFloat(String(input.value).replace(',', '.')) || 0;

        if (input.form) {
            input.form.addEventListener('submit', function () {
                input.value = String(m.typedValue || 0);
            });
        }
    })();
</script>
