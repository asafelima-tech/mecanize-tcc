<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$os    = $os ?? [];
$itens = $os['itens'] ?? [];

$totPecas    = 0;
$totServicos = 0;
foreach ($itens as $item) {
    $sub = (float) $item['preco'] * (int) ($item['qty'] ?? 1);
    if ($item['tipo'] === 'produto') $totPecas    += $sub;
    else                             $totServicos += $sub;
}
$totGeral = $totPecas + $totServicos;
?>

<!-- MODAL OVERLAY — abre automaticamente via JS no fim do arquivo -->
<div class="modal-overlay" id="modalDocumento">
    <div class="modal modal--xl">

        <!-- HEADER -->
        <div class="modal__header">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="
                    width:40px; height:40px;
                    background: var(--color-primary-soft);
                    color: var(--color-primary);
                    border-radius: var(--radius-md);
                    display:inline-flex; align-items:center; justify-content:center;
                ">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586
                               a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div>
                    <div class="modal__title">Gerar Orçamento</div>
                    <div class="modal__subtitle">
                        Referência: <?= htmlspecialchars($os['numero'] ?? '—') ?>
                    </div>
                </div>
            </div>

            <a href="./listDocumentos" class="modal__close" title="Fechar">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </a>
        </div>

        <!-- BODY -->
        <div class="modal__body">

            <?php if (!empty($error)): ?>
                <div style="
                    background: var(--color-danger-soft); color:#991B1B;
                    border:1px solid #FECACA; border-radius:var(--radius-md);
                    padding:12px 16px; margin-bottom:20px; font-size:13px;
                ">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="./finalizarDocumento" method="POST" id="formDocumento">
                <input type="hidden" name="os_id" value="<?= (int) ($os['id'] ?? 0) ?>">

                <!-- ① DADOS DA OS -->
                <div class="form-section">
                    <div class="form-section__title">
                        <span class="ico-wrap ico" style="
                            width:22px; height:22px;
                            background:var(--color-primary-soft); color:var(--color-primary);
                            border-radius:var(--radius-sm); display:inline-flex;
                            align-items:center; justify-content:center;
                            font-size:11px; font-weight:700;">①</span>
                        Dados da OS
                    </div>

                    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:14px 18px;">

                        <div class="form-field">
                            <label>Número da OS</label>
                            <input type="text" value="<?= htmlspecialchars($os['numero'] ?? '—') ?>"
                                readonly style="background:var(--color-bg-alt); font-weight:600; cursor:not-allowed;">
                        </div>

                        <div class="form-field">
                            <label>Cliente</label>
                            <input type="text" value="<?= htmlspecialchars($os['cliente'] ?? '—') ?>"
                                readonly style="background:var(--color-bg-alt); cursor:not-allowed;">
                        </div>

                        <div class="form-field">
                            <label>Veículo / Placa</label>
                            <input type="text"
                                value="<?= htmlspecialchars(($os['veiculo'] ?? '—') . (!empty($os['placa']) ? ' · ' . $os['placa'] : '')) ?>"
                                readonly style="background:var(--color-bg-alt); cursor:not-allowed;">
                        </div>

                        <div class="form-field">
                            <label>Data de Abertura</label>
                            <input type="text"
                                value="<?= !empty($os['data_abertura']) ? date('d/m/Y H:i', strtotime($os['data_abertura'])) : '—' ?>"
                                readonly style="background:var(--color-bg-alt); cursor:not-allowed;">
                        </div>

                        <div class="form-field">
                            <label>Data de Conclusão</label>
                            <input type="text"
                                value="<?= !empty($os['data_conclusao']) ? date('d/m/Y H:i', strtotime($os['data_conclusao'])) : '—' ?>"
                                readonly style="background:var(--color-bg-alt); cursor:not-allowed;">
                        </div>

                    </div>
                </div>

                <!-- ② DIAGNÓSTICO -->
                <div class="form-section">
                    <div class="form-section__title">
                        <span class="ico-wrap ico" style="
                            width:22px; height:22px;
                            background:var(--color-primary-soft); color:var(--color-primary);
                            border-radius:var(--radius-sm); display:inline-flex;
                            align-items:center; justify-content:center;
                            font-size:11px; font-weight:700;">②</span>
                        Diagnóstico
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px 18px;">
                        <div class="form-field">
                            <label>Defeito Relatado</label>
                            <textarea rows="3" readonly
                                style="background:var(--color-bg-alt); cursor:not-allowed; resize:none;"><?= htmlspecialchars($os['defeito_relatado'] ?? '—') ?></textarea>
                        </div>
                        <div class="form-field">
                            <label>Diagnóstico Técnico</label>
                            <textarea rows="3" readonly
                                style="background:var(--color-bg-alt); cursor:not-allowed; resize:none;"><?= htmlspecialchars($os['diagnostico'] ?? '—') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- ③ PRODUTOS & SERVIÇOS -->
                <div class="form-section">
                    <div class="form-section__title">
                        <span class="ico-wrap ico" style="
                            width:22px; height:22px;
                            background:var(--color-primary-soft); color:var(--color-primary);
                            border-radius:var(--radius-sm); display:inline-flex;
                            align-items:center; justify-content:center;
                            font-size:11px; font-weight:700;">③</span>
                        Produtos &amp; Serviços
                    </div>

                    <table style="width:100%; border-collapse:collapse; font-size:13px;">
                        <thead>
                            <tr style="background:var(--color-bg-alt);">
                                <th style="padding:8px 12px; text-align:left; border-bottom:1px solid var(--color-border);
                                    font-size:11px; font-weight:600; color:var(--color-text-secondary);">Item</th>
                                <th style="padding:8px 12px; text-align:left; border-bottom:1px solid var(--color-border);
                                    font-size:11px; font-weight:600; color:var(--color-text-secondary);">Tipo</th>
                                <th style="padding:8px 12px; text-align:center; border-bottom:1px solid var(--color-border);
                                    font-size:11px; font-weight:600; color:var(--color-text-secondary);">Qtd</th>
                                <th style="padding:8px 12px; text-align:right; border-bottom:1px solid var(--color-border);
                                    font-size:11px; font-weight:600; color:var(--color-text-secondary);">Unit.</th>
                                <th style="padding:8px 12px; text-align:right; border-bottom:1px solid var(--color-border);
                                    font-size:11px; font-weight:600; color:var(--color-text-secondary);">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($itens)): ?>
                                <?php foreach ($itens as $item):
                                    $qty = (int)   ($item['qty']   ?? 1);
                                    $sub = (float) ($item['preco'] ?? 0) * $qty;
                                ?>
                                    <tr style="border-bottom:1px solid var(--color-border);">
                                        <td style="padding:9px 12px; font-weight:500;">
                                            <?= htmlspecialchars($item['nome']) ?>
                                        </td>
                                        <td style="padding:9px 12px;">
                                            <?php if ($item['tipo'] === 'produto'): ?>
                                                <span class="badge badge--info">Peça</span>
                                            <?php else: ?>
                                                <span class="badge badge--success">Serviço</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:9px 12px; text-align:center;"><?= $qty ?></td>
                                        <td style="padding:9px 12px; text-align:right; font-size:12px;">
                                            R$ <?= number_format($item['preco'] ?? 0, 2, ',', '.') ?>
                                        </td>
                                        <td style="padding:9px 12px; text-align:right; font-weight:700;">
                                            R$ <?= number_format($sub, 2, ',', '.') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="padding:20px; text-align:center;
                                        color:var(--color-text-muted); font-size:13px;">
                                        Nenhum item registrado nesta OS.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- ④ RESUMO FINANCEIRO -->
                <div class="form-section" style="margin-bottom:0;">
                    <div class="form-section__title">
                        <span class="ico-wrap ico" style="
                            width:22px; height:22px;
                            background:var(--color-primary-soft); color:var(--color-primary);
                            border-radius:var(--radius-sm); display:inline-flex;
                            align-items:center; justify-content:center;
                            font-size:11px; font-weight:700;">④</span>
                        Resumo Financeiro
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px;">

                        <div style="background:var(--color-bg-alt); border:1px solid var(--color-border);
                            border-radius:var(--radius-md); padding:12px 14px;">
                            <div style="font-size:11px; color:var(--color-text-muted); margin-bottom:4px;">Total em Peças</div>
                            <div style="font-size:18px; font-weight:700;">
                                R$ <?= number_format($totPecas, 2, ',', '.') ?>
                            </div>
                        </div>

                        <div style="background:var(--color-bg-alt); border:1px solid var(--color-border);
                            border-radius:var(--radius-md); padding:12px 14px;">
                            <div style="font-size:11px; color:var(--color-text-muted); margin-bottom:4px;">Total em Serviços</div>
                            <div style="font-size:18px; font-weight:700;">
                                R$ <?= number_format($totServicos, 2, ',', '.') ?>
                            </div>
                        </div>

                        <div style="background:var(--color-primary-soft); border:1px solid var(--color-primary);
                            border-radius:var(--radius-md); padding:12px 14px;">
                            <div style="font-size:11px; color:var(--color-primary); font-weight:600; margin-bottom:4px;">Total da OS</div>
                            <div style="font-size:20px; font-weight:700; color:var(--color-primary);">
                                R$ <?= number_format($totGeral, 2, ',', '.') ?>
                            </div>
                        </div>

                    </div>
                </div>

            </form>
        </div><!-- /modal__body -->

        <!-- FOOTER -->
        <div class="modal__footer">
            <div class="modal__footer-left">
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        style="vertical-align:-2px; margin-right:4px;">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                        <line x1="16" y1="2" x2="16" y2="6" />
                        <line x1="8" y1="2" x2="8" y2="6" />
                        <line x1="3" y1="10" x2="21" y2="10" />
                    </svg>
                    Gerado em: <?= date('d/m/Y H:i') ?>
                </span>
                &nbsp;&nbsp;
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        style="vertical-align:-2px; margin-right:4px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" />
                        <circle cx="12" cy="7" r="4" />
                    </svg>
                    <?= htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário') ?>
                    (<?= htmlspecialchars($_SESSION['user_tipo'] ?? 'Administrador') ?>)
                </span>
            </div>

            <a href="./listDocumentos" class="btn btn--ghost btn--sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5m0 0l5-5m-5 5h12" />
                </svg>
                Voltar
            </a>

            <button type="submit" form="formDocumento" class="btn btn--primary btn--sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
                Finalizar Documento
            </button>
        </div>

    </div>
</div>

<script>
    // Fecha clicando fora do modal
    document.getElementById('modalDocumento').addEventListener('click', function(e) {
        if (e.target === this) window.location.href = './listDocumentos';
    });
</script>