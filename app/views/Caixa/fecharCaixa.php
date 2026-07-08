<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Variáveis esperadas:
// $caixa  array  — sessão de caixa atualmente aberta (obrigatório)
// $resumo array  — resumoSessao(): entradas, saidas, totais, caixa_atual
// $error  string — mensagem de erro (opcional)
// $old    array  — dados do POST anterior (opcional)

$valorAbertura = (float) ($caixa['valor_abertura'] ?? 0);

// Dinheiro esperado na gaveta (abertura + entradas em dinheiro − saídas).
$caixaAtual = (float) ($resumo['caixa_atual'] ?? $valorAbertura);

// Valor sugerido para fechamento: o dinheiro esperado, em formato cru.
$valorSugerido = $old['valor_fechamento'] ?? number_format($caixaAtual, 2, '.', '');

$nomeFechamento = $_SESSION['user_nome'] ?? 'Usuário';
$iniciaisFechamento = strtoupper(substr($nomeFechamento, 0, 1) . substr($nomeFechamento, -1));
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Fechar Caixa</h1>
        <p class="page-header__subtitle">Encerre o caixa do dia informando o valor final.</p>
    </div>
    <div class="page-header__actions">
        <a href="./home" class="btn btn--ghost">Cancelar</a>
    </div>
</div>

<div style="max-width:640px;margin:0 auto;">

    <?php if (!empty($error)): ?>
        <div style="background:var(--color-danger-soft);color:#991B1B;border:1px solid #FECACA;border-radius:var(--radius-md);padding:12px 16px;margin-bottom:20px;font-size:13px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- ── Quem está fechando + contexto da sessão ── -->
    <section class="card" style="margin-bottom:20px;">
        <div class="card__body" style="padding:20px 24px;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#3B82F6,#1E40AF);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">
                    <?= htmlspecialchars($iniciaisFechamento) ?>
                </div>
                <div>
                    <div style="font-weight:600;font-size:14px;color:var(--color-text);">
                        <?= htmlspecialchars($nomeFechamento) ?>
                    </div>
                    <div style="font-size:12px;color:var(--color-text-muted);">
                        Fechando o caixa em <?= date('d/m/Y \à\s H:i') ?>
                    </div>
                </div>
            </div>

            <div class="kv-row">
                <span>Aberto por</span>
                <b><?= htmlspecialchars($caixa['abertura_nome'] ?? '-') ?></b>
            </div>
            <div class="kv-row">
                <span>Aberto em</span>
                <b><?= !empty($caixa['data_abertura']) ? date('d/m/Y H:i', strtotime($caixa['data_abertura'])) : '-' ?></b>
            </div>
            <div class="kv-row">
                <span>Valor de abertura</span>
                <b>R$ <?= number_format($valorAbertura, 2, ',', '.') ?></b>
            </div>
        </div>
    </section>

    <!-- ── Formulário de fechamento ── -->
    <section class="card">
        <div class="card__header">
            <span class="card__title">
                <span class="ico-wrap">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="7" width="20" height="14" rx="2" />
                        <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" />
                    </svg>
                </span>
                Contagem final
            </span>
        </div>
        <div class="card__body" style="padding:20px 24px;">
            <form action="./fecharCaixa" method="POST" id="formFecharCaixa">

                <div class="form-field" style="margin-bottom:16px;">
                    <label>Valor de fechamento (dinheiro contado) <span class="required">*</span></label>
                    <input
                        type="text"
                        inputmode="numeric"
                        name="valor_fechamento"
                        id="valor_fechamento"
                        placeholder="0,00"
                        value="<?= htmlspecialchars($valorSugerido) ?>"
                        required>
                    <small style="display:block;margin-top:6px;font-size:12px;color:var(--color-text-muted);">
                        Sugerido com o dinheiro esperado na gaveta — ajuste com o valor realmente contado.
                    </small>
                </div>

                <!-- Diferença apurada (contagem − esperado) -->
                <div id="diferenca-wrap" style="display:flex;align-items:center;justify-content:space-between;border:1px dashed var(--color-border);border-radius:var(--radius-md);padding:12px 16px;margin-bottom:18px;">
                    <span style="font-size:13px;color:var(--color-text-muted);">Diferença (contado − esperado)</span>
                    <strong id="diferenca-valor" style="font-size:16px;">R$ 0,00</strong>
                </div>

                <div class="form-field" style="margin-bottom:20px;">
                    <label>Observação (opcional)</label>
                    <textarea
                        name="observacao"
                        rows="2"
                        placeholder="Ex.: fechamento do expediente"
                        style="resize:vertical;"><?= htmlspecialchars($old['observacao'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn--danger" style="width:100%;justify-content:center;"
                    onclick="return confirm('Confirma o fechamento do caixa?');">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="7" width="20" height="14" rx="2" />
                        <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" />
                        <path d="M9 14h6" />
                    </svg>
                    Fechar Caixa
                </button>
            </form>
        </div>
    </section>

</div>

<script>
    (function () {
        const input = document.getElementById('valor_fechamento');
        const difEl = document.getElementById('diferenca-valor');
        const wrap  = document.getElementById('diferenca-wrap');
        if (!input || typeof IMask === 'undefined') return;

        const esperado = <?= json_encode($caixaAtual) ?>;

        const m = IMask(input, {
            mask: Number,
            scale: 2,
            thousandsSeparator: '.',
            radix: ',',
            padFractionalZeros: true,
            normalizeZeros: true,
            min: 0
        });

        m.typedValue = parseFloat(String(input.value).replace(',', '.')) || 0;

        function formatBR(v) {
            return 'R$ ' + (v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function atualizarDiferenca() {
            const fechamento = m.typedValue || 0;
            const dif = +(fechamento - esperado).toFixed(2);
            difEl.textContent = (dif > 0 ? '+ ' : (dif < 0 ? '− ' : '')) + formatBR(Math.abs(dif));
            difEl.style.color = dif < 0 ? 'var(--color-danger)' : (dif > 0 ? 'var(--color-success)' : 'var(--color-text)');
        }

        m.on('accept', atualizarDiferenca);
        atualizarDiferenca();

        if (input.form) {
            input.form.addEventListener('submit', function () {
                input.value = String(m.typedValue || 0);
            });
        }
    })();
</script>
