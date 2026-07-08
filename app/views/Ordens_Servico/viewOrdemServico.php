<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$os      = $ordem_servico ?? [];
$itens   = $itens_os      ?? [];
$maoObra = $mao_obra_os   ?? [];

$numero    = htmlspecialchars($os['numero_os']       ?? '—');
$status    = htmlspecialchars($os['status']          ?? '—');
$cliente   = htmlspecialchars($os['nome_cliente']    ?? '—');
$veiculo   = htmlspecialchars($os['modelo']          ?? '—');
$placa     = htmlspecialchars(strtoupper($os['placa'] ?? ''));
$km        = htmlspecialchars($os['km_veiculo']      ?? '—');
$mecanico  = htmlspecialchars($os['nome_mecanico']   ?? '—');
$defeito   = htmlspecialchars($os['defeito_relatado'] ?? '—');
$diag      = htmlspecialchars($os['diagnostico']     ?? '—');
$obs       = htmlspecialchars($os['observacoes']     ?? '—');
$abertura  = !empty($os['data_abertura'])  ? date('d/m/Y H:i', strtotime($os['data_abertura']))  : '—';
$conclusao = !empty($os['data_conclusao']) ? date('d/m/Y H:i', strtotime($os['data_conclusao'])) : '—';

$totalPecas    = (float)($os['valor_total_pecas']    ?? 0);
$totalServicos = (float)($os['valor_total_servicos'] ?? 0);
$comissao      = (float)($os['valor_mao_obra']       ?? 0);
$totalOS       = (float)($os['valor_total_os']       ?? 0);
$lucro         = $totalOS - $comissao;

$statusColors = [
    'ABERTA'     => ['bg' => '#DBEAFE', 'color' => '#1D4ED8', 'border' => '#BFDBFE'],
    'CONCLUIDA'  => ['bg' => '#DCFCE7', 'color' => '#16A34A', 'border' => '#BBF7D0'],
    'CANCELADA'  => ['bg' => '#FEE2E2', 'color' => '#DC2626', 'border' => '#FECACA'],
];
$sc = $statusColors[$os['status'] ?? ''] ?? ['bg' => '#F3F4F6', 'color' => '#6B7280', 'border' => '#E5E7EB'];

function fmtBR(float $v): string
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

$linkRastreio = $linkRastreio ?? '';

// Link "wa.me" com mensagem pronta — só faz sentido quando a OS está
// concluída e o cliente tem telefone cadastrado.
$whatsappLink = null;
if (($os['status'] ?? '') === 'CONCLUIDA' && !empty($os['telefone_cliente'])) {
    $mensagem = "Olá, {$os['nome_cliente']}. Seu veículo (placa {$placa}), referente à OS nº {$numero}, está pronto para retirada.";
    if ($linkRastreio) {
        $mensagem .= " Acompanhe pelo link: {$linkRastreio}";
    }
    $whatsappLink = WhatsApp::linkParaCliente($os['telefone_cliente'], $mensagem);
}

// Stepper de acompanhamento (estilo rastreio) — mesma ordem das colunas do
// Kanban. Cancelada fica fora da progressão linear (mostrada à parte).
$etapasStepper = ['ABERTA' => 'Aberta', 'EM_ANDAMENTO' => 'Em Andamento', 'AGUARDANDO_PECA' => 'Aguardando Peça', 'CONCLUIDA' => 'Concluída'];
$statusAtualOS = $os['status'] ?? 'ABERTA';
$indiceAtual   = array_search($statusAtualOS, array_keys($etapasStepper), true);
?>

<style>
    .os-stepper {
        display: flex;
        align-items: flex-start;
        padding: 18px 24px 6px;
    }

    .os-stepper__step {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        text-align: center;
    }

    .os-stepper__circle {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        font-weight: 700;
        border: 2px solid var(--color-border-strong);
        color: var(--color-text-muted);
        background: var(--color-surface);
    }

    .os-stepper__circle.is-done {
        background: var(--color-success);
        border-color: var(--color-success);
        color: #fff;
    }

    .os-stepper__circle.is-current {
        background: var(--color-primary-soft);
        border-color: var(--color-primary);
        color: var(--color-primary);
        box-shadow: 0 0 0 4px var(--color-primary-soft);
    }

    .os-stepper__label {
        font-size: 11.5px;
        font-weight: 600;
        color: var(--color-text-muted);
        margin-top: 6px;
    }

    .os-stepper__label.is-active {
        color: var(--color-text);
    }

    .os-stepper__line {
        flex: 1;
        height: 2px;
        background: var(--color-border-strong);
        margin-top: 15px;
    }

    .os-stepper__line.is-done {
        background: var(--color-success);
    }
</style>

<div class="modal-overlay" id="modalViewOrdemServico" style="align-items:flex-start;padding:32px 24px;overflow-y:auto;">
    <div class="modal modal--xl" style="max-height:none;">

        <!-- HEADER -->
        <div class="modal__header">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:40px;height:40px;background:var(--color-primary-soft);color:var(--color-primary);
                            border-radius:var(--radius-md);display:inline-flex;align-items:center;justify-content:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                                 M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <div>
                    <div class="modal__title">Ordem de Serviço — <?= $numero ?></div>
                    <div class="modal__subtitle">Visualização detalhada da OS</div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;
                             background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;border:1px solid <?= $sc['border'] ?>;">
                    <?= $status ?>
                </span>
                <a href="javascript:history.back()" class="modal__close" title="Fechar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </a>
            </div>
        </div>

        <!-- STEPPER DE STATUS -->
        <?php if ($statusAtualOS === 'CANCELADA'): ?>
            <div style="margin:16px 24px;padding:12px 16px;background:var(--color-danger-soft);color:var(--color-danger);
                        border:1px solid var(--color-danger);border-radius:var(--radius-md);font-size:13px;font-weight:600;text-align:center;">
                <i class="bi bi-x-circle"></i> Esta Ordem de Serviço foi cancelada.
            </div>
        <?php else: ?>
            <div class="os-stepper">
                <?php $i = 0; foreach ($etapasStepper as $label): ?>
                    <?php if ($i > 0): ?>
                        <div class="os-stepper__line <?= $i <= $indiceAtual ? 'is-done' : '' ?>"></div>
                    <?php endif; ?>
                    <div class="os-stepper__step">
                        <div class="os-stepper__circle <?= $i < $indiceAtual ? 'is-done' : ($i === $indiceAtual ? 'is-current' : '') ?>">
                            <?= $i < $indiceAtual ? '<i class="bi bi-check-lg"></i>' : ($i + 1) ?>
                        </div>
                        <div class="os-stepper__label <?= $i === $indiceAtual ? 'is-active' : '' ?>"><?= $label ?></div>
                    </div>
                <?php $i++; endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- BODY -->
        <div class="modal__body">

            <!-- ① IDENTIFICAÇÃO -->
            <div class="form-section">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);
                                border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:700;">①</span>
                    Identificação
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px 18px;">
                    <?php
                    $campos = [
                        'Número da OS'    => $numero,
                        'Status'          => $status,
                        'Data de Abertura' => $abertura,
                        'Data Conclusão'  => $conclusao,
                    ];
                    foreach ($campos as $label => $val): ?>
                        <div class="form-field">
                            <label style="font-size:11px;color:var(--color-text-muted);font-weight:600;"><?= $label ?></label>
                            <div style="padding:9px 12px;background:var(--color-bg-alt);border:1px solid var(--color-border);
                                        border-radius:var(--radius-md);font-size:13px;font-weight:600;color:var(--color-text);">
                                <?= $val ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ② CLIENTE E VEÍCULO -->
            <div class="form-section">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);
                                border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:700;">②</span>
                    Cliente e Veículo
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px 18px;">
                    <?php
                    $campos2 = [
                        'Cliente' => $cliente,
                        'Veículo' => $veiculo . ($placa ? " — {$placa}" : ''),
                        'KM'      => $km !== '—' ? number_format((int)$km, 0, ',', '.') . ' km' : '—',
                    ];
                    foreach ($campos2 as $label => $val): ?>
                        <div class="form-field">
                            <label style="font-size:11px;color:var(--color-text-muted);font-weight:600;"><?= $label ?></label>
                            <div style="padding:9px 12px;background:var(--color-bg-alt);border:1px solid var(--color-border);
                                        border-radius:var(--radius-md);font-size:13px;font-weight:600;color:var(--color-text);">
                                <?= $val ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ③ MECÂNICO -->
            <div class="form-section">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);
                                border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:700;">③</span>
                    Mecânico Responsável
                </div>
                <?php if ($mecanico !== '—'): ?>
                    <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--color-bg-alt);
                                border:1px solid var(--color-border);border-radius:var(--radius-md);">
                        <div style="width:36px;height:36px;border-radius:50%;background:var(--color-primary-soft);
                                    color:var(--color-primary);display:flex;align-items:center;justify-content:center;
                                    font-size:12px;font-weight:700;flex-shrink:0;">
                            <?= strtoupper(implode('', array_map(fn($p) => $p[0], array_slice(explode(' ', $mecanico), 0, 2)))) ?>
                        </div>
                        <div style="flex:1;">
                            <div style="font-size:13px;font-weight:600;"><?= $mecanico ?></div>
                            <?php if (!empty($os['comissao_pct'])): ?>
                                <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px;">
                                    Comissão: <strong style="color:var(--color-primary);"><?= $os['comissao_pct'] ?>%</strong>
                                    &nbsp;→&nbsp;
                                    <strong style="color:var(--color-primary);"><?= fmtBR($comissao) ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                        <span class="badge badge--success">Responsável</span>
                    </div>
                <?php else: ?>
                    <p style="font-size:13px;color:var(--color-text-muted);">Nenhum mecânico atribuído.</p>
                <?php endif; ?>
            </div>

            <!-- ④ PRODUTOS & SERVIÇOS -->
            <div class="form-section">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);
                                border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:700;">④</span>
                    Produtos &amp; Serviços
                </div>
                <?php if (empty($itens)): ?>
                    <div style="text-align:center;padding:28px 20px;color:var(--color-text-muted);
                                border:1px dashed var(--color-border);border-radius:var(--radius-md);">
                        <p style="font-size:12px;">Nenhum item registrado nesta OS.</p>
                    </div>
                <?php else: ?>
                    <table style="width:100%;border-collapse:collapse;font-size:12px;">
                        <thead>
                            <tr style="background:var(--color-bg-alt);">
                                <th style="padding:8px 12px;text-align:left;border-bottom:1px solid var(--color-border);font-weight:600;color:var(--color-text-secondary);">Item</th>
                                <th style="padding:8px 12px;text-align:left;border-bottom:1px solid var(--color-border);font-weight:600;color:var(--color-text-secondary);">Tipo</th>
                                <th style="padding:8px 12px;text-align:center;border-bottom:1px solid var(--color-border);font-weight:600;color:var(--color-text-secondary);">Qtd</th>
                                <th style="padding:8px 12px;text-align:right;border-bottom:1px solid var(--color-border);font-weight:600;color:var(--color-text-secondary);">Unit.</th>
                                <th style="padding:8px 12px;text-align:right;border-bottom:1px solid var(--color-border);font-weight:600;color:var(--color-text-secondary);">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itens as $it):
                                $tipo = $it['tipo'] ?? 'produto';
                                $preco = (float)($it['preco_unitario'] ?? $it['preco'] ?? 0);
                                $qty   = (int)($it['quantidade'] ?? $it['qty'] ?? 1);
                            ?>
                                <tr style="border-bottom:1px solid var(--color-border);">
                                    <td style="padding:9px 12px;">
                                        <div style="font-size:13px;font-weight:600;color:var(--color-text);">
                                            <?= htmlspecialchars($it['nome_produto'] ?? $it['nome'] ?? '—') ?>
                                        </div>
                                        <div style="font-size:11px;color:var(--color-text-muted);">
                                            <?= htmlspecialchars($it['categoria'] ?? '') ?>
                                        </div>
                                    </td>
                                    <td style="padding:9px 12px;">
                                        <span class="badge <?= $tipo === 'produto' ? 'badge--info' : 'badge--success' ?>">
                                            <?= $tipo === 'produto' ? 'Peça' : 'Serviço' ?>
                                        </span>
                                    </td>
                                    <td style="padding:9px 12px;text-align:center;font-size:13px;font-weight:700;"><?= $qty ?></td>
                                    <td style="padding:9px 12px;text-align:right;font-size:12px;"><?= fmtBR($preco) ?></td>
                                    <td style="padding:9px 12px;text-align:right;font-size:13px;font-weight:700;"><?= fmtBR($preco * $qty) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- ⑤ DIAGNÓSTICO -->
            <div class="form-section">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);
                                border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:700;">⑤</span>
                    Diagnóstico
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px 18px;">
                    <?php
                    $textos = [
                        'Defeito Relatado'    => $defeito,
                        'Diagnóstico Técnico' => $diag,
                    ];
                    foreach ($textos as $label => $val): ?>
                        <div class="form-field">
                            <label style="font-size:11px;color:var(--color-text-muted);font-weight:600;"><?= $label ?></label>
                            <div style="padding:9px 12px;background:var(--color-bg-alt);border:1px solid var(--color-border);
                                        border-radius:var(--radius-md);font-size:13px;color:var(--color-text);
                                        min-height:72px;white-space:pre-wrap;line-height:1.5;">
                                <?= $val ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="form-field" style="grid-column:1/-1;">
                        <label style="font-size:11px;color:var(--color-text-muted);font-weight:600;">Observações</label>
                        <div style="padding:9px 12px;background:var(--color-bg-alt);border:1px solid var(--color-border);
                                    border-radius:var(--radius-md);font-size:13px;color:var(--color-text);
                                    min-height:52px;white-space:pre-wrap;line-height:1.5;">
                            <?= $obs ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ⑥ RESUMO FINANCEIRO -->
            <div class="form-section">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);
                                border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:700;">⑥</span>
                    Resumo Financeiro
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;gap:14px;">
                    <?php
                    $cards = [
                        ['Total em Peças',    $totalPecas,    false],
                        ['Total em Serviços', $totalServicos, false],
                        ['Comissão Técnico',  $comissao,      false],
                        ['Lucro Líquido',     $lucro,         false],
                    ];
                    foreach ($cards as [$label, $val, $primary]): ?>
                        <div style="background:var(--color-bg-alt);border:1px solid var(--color-border);
                                    border-radius:var(--radius-md);padding:12px 14px;">
                            <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:4px;"><?= $label ?></div>
                            <div style="font-size:18px;font-weight:700;color:<?= $label === 'Lucro Líquido' ? '#16A34A' : 'var(--color-text)' ?>;">
                                <?= fmtBR($val) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div style="background:var(--color-primary-soft);border:1px solid var(--color-primary);
                                border-radius:var(--radius-md);padding:12px 14px;">
                        <div style="font-size:11px;color:var(--color-primary);font-weight:600;margin-bottom:4px;">Total da OS</div>
                        <div style="font-size:20px;font-weight:700;color:var(--color-primary);"><?= fmtBR($totalOS) ?></div>
                    </div>
                </div>
            </div>

            <?php if (!empty($os['assinatura_cliente'])): ?>
            <!-- ⑦ ASSINATURA DO CLIENTE -->
            <div class="form-section" style="margin-bottom:0;">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);
                                border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:700;">⑦</span>
                    Assinatura do Cliente
                </div>
                <div style="text-align:center;">
                    <img src="<?= htmlspecialchars($os['assinatura_cliente']) ?>" alt="Assinatura do cliente"
                        style="max-width:320px;width:100%;background:#fff;border:1px solid var(--color-border);border-radius:var(--radius-md);padding:8px;">
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /modal__body -->

        <!-- FOOTER -->
        <div class="modal__footer">
            <div class="modal__footer-left">
                <span style="margin-right:16px;">Abertura: <?= $abertura ?></span>
                <span>Usuário: <?= htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário') ?> (<?= htmlspecialchars($_SESSION['user_tipo'] ?? 'Administrador') ?>)</span>
            </div>
            <?php if ($whatsappLink): ?>
            <a href="<?= htmlspecialchars($whatsappLink) ?>" target="_blank" rel="noopener" class="btn btn--ghost btn--sm">
                <i class="bi bi-whatsapp"></i> Enviar WhatsApp
            </a>
            <?php endif; ?>
            <a href="./exportOrdemServicoPdf?id=<?= (int) ($os['id_ordem_servico'] ?? 0) ?>" class="btn btn--ghost btn--sm">
                <i class="bi bi-file-earmark-pdf"></i> Baixar PDF
            </a>
            <?php if ($linkRastreio): ?>
            <button type="button" class="btn btn--ghost btn--sm" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($linkRastreio, ENT_QUOTES) ?>'); this.querySelector('span').textContent = 'Copiado!'; setTimeout(() => this.querySelector('span').textContent = 'Link de Rastreio', 2000);">
                <i class="bi bi-link-45deg"></i> <span>Link de Rastreio</span>
            </button>
            <?php endif; ?>
            <button type="button" class="btn btn--ghost btn--sm" onclick="window.print()">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9" />
                    <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2" />
                    <rect x="6" y="14" width="12" height="8" />
                </svg>
                Imprimir
            </button>
            <a href="./editOrdemServico?id=<?= (int)($os['id_ordem_servico'] ?? 0) ?>" class="btn btn--ghost btn--sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5
                                                                              m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Editar
            </a>
            <a href="javascript:history.back()" class="btn btn--primary btn--sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Voltar
            </a>
        </div>

    </div>
</div>