<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Variáveis esperadas do controller:
// $caixa         array  — sessão de caixa aberta
// $resumo        array  — resumoSessao(): entradas, saidas, totais, caixa_atual
// $movimentacoes array  — lançamentos da sessão
// $ok            bool   — flash de sucesso (opcional)
// $error         string — mensagem de erro (opcional)

$formaLabel = [
    'DINHEIRO' => 'Dinheiro',
    'PIX'      => 'PIX',
    'CARTAO'   => 'Cartão',
    'BOLETO'   => 'Boleto',
];
$catLabel = [
    'PECAS'  => 'Peças',
    'BANCO'  => 'Retirada p/ banco',
    'OUTRO'  => 'Outro',
    'OS'     => 'Ordem de Serviço',
    'VENDA'  => 'Venda',
];

$brl = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Caixa — Lançamentos</h1>
        <p class="page-header__subtitle">Registre as entradas e saídas da sessão de caixa aberta.</p>
    </div>
    <div class="page-header__actions">
        <a href="./listCaixa" class="btn btn--ghost">Histórico</a>
        <a href="./fecharCaixa" class="btn btn--danger">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="7" width="20" height="14" rx="2" />
                <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" />
            </svg>
            Fechar Caixa
        </a>
    </div>
</div>

<?php if (!empty($ok)): ?>
    <div style="background:var(--color-success-soft);color:#166534;border:1px solid #BBF7D0;border-radius:var(--radius-md);padding:12px 16px;margin-bottom:16px;font-size:13px;">
        Movimentação registrada com sucesso.
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div style="background:var(--color-danger-soft);color:#991B1B;border:1px solid #FECACA;border-radius:var(--radius-md);padding:12px 16px;margin-bottom:16px;font-size:13px;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Sessão atual + Caixa atual -->
<section class="card" style="margin-bottom:20px;">
    <div class="card__body" style="padding:18px 20px;display:flex;flex-wrap:wrap;align-items:center;gap:20px;justify-content:space-between;">
        <div style="display:flex;align-items:center;gap:14px;">
            <span class="badge badge--success" style="font-size:13px;">
                <span class="badge__dot"></span> Caixa aberto
            </span>
            <span style="font-size:13px;color:var(--color-text-muted);">
                Aberto por <strong style="color:var(--color-text);"><?= htmlspecialchars($caixa['abertura_nome'] ?? '-') ?></strong>
                em <?= !empty($caixa['data_abertura']) ? date('d/m/Y H:i', strtotime($caixa['data_abertura'])) : '-' ?>
                — abertura <?= $brl($resumo['valor_abertura'] ?? 0) ?>
            </span>
        </div>
        <div style="text-align:right;">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--color-text-muted);">Caixa atual (dinheiro na gaveta)</div>
            <div style="font-size:26px;font-weight:700;color:var(--color-text);line-height:1.1;"><?= $brl($resumo['caixa_atual'] ?? 0) ?></div>
        </div>
    </div>
</section>

<div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.1fr);gap:20px;align-items:start;margin-bottom:20px;">

    <!-- Formulário de lançamento -->
    <section class="card">
        <div class="card__body" style="padding:20px;">
            <h2 style="font-size:15px;font-weight:700;margin:0 0 16px;color:var(--color-text);">Nova movimentação</h2>

            <form action="./registrarMovimentacaoCaixa" method="POST" id="formMov">

                <!-- Tipo (segmentado) -->
                <div class="form-field" style="margin-bottom:16px;">
                    <label>Tipo <span style="color:var(--color-danger)">*</span></label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <label class="mov-seg" style="margin:0;">
                            <input type="radio" name="tipo" value="ENTRADA" checked style="position:absolute;opacity:0;pointer-events:none;">
                            <span class="mov-seg__btn mov-seg__btn--entrada">↑ Entrada</span>
                        </label>
                        <label class="mov-seg" style="margin:0;">
                            <input type="radio" name="tipo" value="SAIDA" style="position:absolute;opacity:0;pointer-events:none;">
                            <span class="mov-seg__btn mov-seg__btn--saida">↓ Saída</span>
                        </label>
                    </div>
                </div>

                <!-- Forma de pagamento (entrada) -->
                <div class="form-field" style="margin-bottom:16px;" id="wrap-forma">
                    <label>Forma de pagamento <span style="color:var(--color-danger)">*</span></label>
                    <select name="forma_pagamento" id="forma_pagamento">
                        <?php foreach ($formaLabel as $valor => $rotulo): ?>
                            <option value="<?= $valor ?>"><?= $rotulo ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small style="display:block;margin-top:6px;font-size:12px;color:var(--color-text-muted);">
                        Só <strong>Dinheiro</strong> soma no caixa atual; PIX, cartão e boleto entram só no resumo/relatórios.
                    </small>
                </div>

                <!-- Categoria (saída) -->
                <div class="form-field" style="margin-bottom:16px;display:none;" id="wrap-categoria">
                    <label>Categoria da saída <span style="color:var(--color-danger)">*</span></label>
                    <select name="categoria" id="categoria" disabled>
                        <option value="PECAS">Peças</option>
                        <option value="BANCO">Retirada para banco</option>
                        <option value="OUTRO">Outro</option>
                    </select>
                </div>

                <div class="form-field" style="margin-bottom:16px;">
                    <label>Valor <span style="color:var(--color-danger)">*</span></label>
                    <input type="text" inputmode="numeric" name="valor" id="valor" placeholder="0,00" value="" required>
                </div>

                <div class="form-field" style="margin-bottom:20px;">
                    <label>Descrição (opcional)</label>
                    <input type="text" name="descricao" maxlength="255" placeholder="Ex.: troca de óleo, compra de pastilhas...">
                </div>

                <button type="submit" class="btn btn--primary" style="width:100%;justify-content:center;">
                    Registrar movimentação
                </button>
            </form>
        </div>
    </section>

    <!-- Resumo da sessão -->
    <section class="card">
        <div class="card__body" style="padding:20px;">
            <h2 style="font-size:15px;font-weight:700;margin:0 0 16px;color:var(--color-text);">Resumo da sessão</h2>

            <!-- Entradas -->
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--color-text-muted);margin-bottom:8px;">Entradas por forma de pagamento</div>
            <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:8px;">
                <?php foreach ($formaLabel as $valor => $rotulo): ?>
                    <div style="display:flex;justify-content:space-between;font-size:13px;">
                        <span style="color:var(--color-text-muted);"><?= $rotulo ?></span>
                        <strong style="color:var(--color-text);"><?= $brl($resumo['entradas'][$valor] ?? 0) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:13px;padding-top:8px;border-top:1px solid var(--color-border);margin-bottom:18px;">
                <span style="font-weight:600;color:var(--color-text);">Total de entradas</span>
                <strong style="color:var(--color-success);"><?= $brl($resumo['total_entradas'] ?? 0) ?></strong>
            </div>

            <!-- Saídas -->
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--color-text-muted);margin-bottom:8px;">Saídas por categoria</div>
            <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:8px;">
                <?php
                $catSaidaLabel = ['PECAS' => 'Peças', 'BANCO' => 'Retirada p/ banco', 'OUTRO' => 'Outro'];
                foreach ($catSaidaLabel as $valor => $rotulo): ?>
                    <div style="display:flex;justify-content:space-between;font-size:13px;">
                        <span style="color:var(--color-text-muted);"><?= $rotulo ?></span>
                        <strong style="color:var(--color-text);"><?= $brl($resumo['saidas'][$valor] ?? 0) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:13px;padding-top:8px;border-top:1px solid var(--color-border);">
                <span style="font-weight:600;color:var(--color-text);">Total de saídas</span>
                <strong style="color:var(--color-danger);"><?= $brl($resumo['total_saidas'] ?? 0) ?></strong>
            </div>
        </div>
    </section>
</div>

<!-- Lançamentos da sessão -->
<section class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Data/hora</th>
                    <th>Tipo</th>
                    <th>Forma / Categoria</th>
                    <th>Descrição</th>
                    <th>Registrado por</th>
                    <th style="text-align:right;">Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($movimentacoes)): ?>
                    <?php foreach ($movimentacoes as $m):
                        $isEntrada = ($m['tipo'] ?? '') === 'ENTRADA';
                        $detalhe   = $isEntrada
                            ? ($formaLabel[$m['forma_pagamento'] ?? ''] ?? '—')
                            : ($catLabel[$m['categoria'] ?? ''] ?? '—');
                        $valor = (float) ($m['valor'] ?? 0);
                    ?>
                        <tr>
                            <td class="text-muted">
                                <?= !empty($m['data_movimentacao']) ? date('d/m/Y H:i', strtotime($m['data_movimentacao'])) : '—' ?>
                            </td>
                            <td>
                                <?php if ($isEntrada): ?>
                                    <span class="badge badge--success"><span class="badge__dot"></span>Entrada</span>
                                <?php else: ?>
                                    <span class="badge badge--danger"><span class="badge__dot"></span>Saída</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($detalhe) ?>
                                <?php if (($m['origem'] ?? 'MANUAL') === 'OS'): ?>
                                    <span class="badge badge--secondary" style="margin-left:6px;">OS</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= !empty($m['descricao']) ? htmlspecialchars($m['descricao']) : '—' ?></td>
                            <td><?= htmlspecialchars($m['usuario_nome'] ?? '—') ?></td>
                            <td style="text-align:right;font-weight:600;color:<?= $isEntrada ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                                <?= $isEntrada ? '+ ' : '− ' ?>R$ <?= number_format($valor, 2, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">
                            Nenhuma movimentação registrada nesta sessão ainda.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<style>
    .mov-seg__btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        height: 40px;
        border: 1.5px solid var(--color-border);
        border-radius: var(--radius-md);
        font-size: 14px;
        font-weight: 600;
        color: var(--color-text-muted);
        cursor: pointer;
        transition: all .12s ease;
        user-select: none;
    }
    .mov-seg input:checked + .mov-seg__btn--entrada {
        border-color: var(--color-success, #16A34A);
        background: var(--color-success-soft);
        color: var(--color-success, #16A34A);
    }
    .mov-seg input:checked + .mov-seg__btn--saida {
        border-color: var(--color-danger, #DC2626);
        background: var(--color-danger-soft);
        color: var(--color-danger, #DC2626);
    }
    @media (max-width: 820px) {
        div[style*="grid-template-columns:minmax(0,1fr) minmax(0,1.1fr)"] {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<script>
    (function () {
        // ── Alterna forma de pagamento (entrada) × categoria (saída) ──
        const tipoInputs = document.querySelectorAll('input[name="tipo"]');
        const wrapForma  = document.getElementById('wrap-forma');
        const wrapCat    = document.getElementById('wrap-categoria');
        const selForma   = document.getElementById('forma_pagamento');
        const selCat     = document.getElementById('categoria');

        function aplicarTipo(tipo) {
            const entrada = tipo === 'ENTRADA';
            wrapForma.style.display = entrada ? '' : 'none';
            wrapCat.style.display   = entrada ? 'none' : '';
            // disabled => não é enviado/validado quando escondido
            selForma.disabled = !entrada;
            selForma.required = entrada;
            selCat.disabled   = entrada;
            selCat.required   = !entrada;
        }

        tipoInputs.forEach(r => r.addEventListener('change', () => aplicarTipo(r.value)));
        const marcado = document.querySelector('input[name="tipo"]:checked');
        aplicarTipo(marcado ? marcado.value : 'ENTRADA');

        // ── Máscara de dinheiro (IMask): exibe 1.234,56, envia o valor cru ──
        const input = document.getElementById('valor');
        if (input && typeof IMask !== 'undefined') {
            const m = IMask(input, {
                mask: Number,
                scale: 2,
                thousandsSeparator: '.',
                radix: ',',
                padFractionalZeros: true,
                normalizeZeros: true,
                min: 0
            });

            if (input.form) {
                input.form.addEventListener('submit', function () {
                    input.value = String(m.typedValue || 0);
                });
            }
        }
    })();
</script>
