<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../models/TecnicoRemuneracao.php';
require_once __DIR__ . '/../../models/MovimentacaoCaixa.php';
require_once __DIR__ . '/../../models/ConsumoEstoque.php';

$os        = $ordem_servico ?? [];
$itens     = $itens_os      ?? [];
$tecnicos  = $tecnicos_os   ?? []; // OrdemServico::getTecnicosDaOS() — pode ter mais de um
$historico = $historico     ?? [];
$parcelas  = $parcelas      ?? [];
$resumoCobranca = $resumoCobranca ?? [];

$idOS      = (int) ($os['id_ordem_servico'] ?? 0);
$ehAdmin   = ($_SESSION['user_tipo'] ?? '') === 'ADMIN';

$numero    = htmlspecialchars($os['numero_os']       ?? '—');
$status    = htmlspecialchars(OrdemServico::rotuloStatus($os['status'] ?? '') ?: '—');
$cliente   = htmlspecialchars($os['nome_cliente']    ?? '—');
$veiculo   = htmlspecialchars($os['modelo']          ?? '—');
$placa     = htmlspecialchars(strtoupper($os['placa'] ?? ''));
$km        = htmlspecialchars($os['km_veiculo']      ?? '—');
$defeito   = htmlspecialchars($os['defeito_relatado'] ?? '—');
$diag      = htmlspecialchars($os['diagnostico']     ?? '—');
$obs       = htmlspecialchars($os['observacoes']     ?? '—');
$abertura  = !empty($os['data_abertura'])  ? date('d/m/Y H:i', strtotime($os['data_abertura']))  : '—';
$conclusao = !empty($os['data_conclusao']) ? date('d/m/Y H:i', strtotime($os['data_conclusao'])) : '—';

$totalPecas    = (float)($os['valor_total_pecas']    ?? 0);
$totalServicos = (float)($os['valor_total_servicos'] ?? 0);
// Soma de todos os técnicos — cada um pode ter uma regra/comissão diferente.
$comissao      = array_sum(array_map(fn($t) => (float) $t['valor_mao_obra'], $tecnicos));
$totalOS       = (float)($os['valor_total_os']       ?? 0);
$lucro         = $totalOS - $comissao;

$statusColors = [
    'ABERTA'                 => ['bg' => '#DBEAFE', 'color' => '#1D4ED8', 'border' => '#BFDBFE'],
    'EM_ANDAMENTO'           => ['bg' => '#FEF3C7', 'color' => '#B45309', 'border' => '#FDE68A'],
    'AGUARDANDO_PECA'        => ['bg' => '#EDE9FE', 'color' => '#6D28D9', 'border' => '#DDD6FE'],
    'AGUARDANDO_CONFERENCIA' => ['bg' => '#E2E8F0', 'color' => '#334155', 'border' => '#CBD5E1'],
    'AGUARDANDO_RETIRADA'    => ['bg' => '#CFFAFE', 'color' => '#0E7490', 'border' => '#A5F3FC'],
    'CONCLUIDA'              => ['bg' => '#DCFCE7', 'color' => '#16A34A', 'border' => '#BBF7D0'],
    'CANCELADA'              => ['bg' => '#FEE2E2', 'color' => '#DC2626', 'border' => '#FECACA'],
];
$sc = $statusColors[$os['status'] ?? ''] ?? ['bg' => '#F3F4F6', 'color' => '#6B7280', 'border' => '#E5E7EB'];

function fmtBR(float $v): string
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

/** Quantidade sem zeros inúteis: 2 → "2", 2.5 → "2,5", 0.750 → "0,75". */
function fmtQtd($v): string
{
    return rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
}

$linkRastreio = $linkRastreio ?? '';

// Link "wa.me" com mensagem pronta — só faz sentido quando o veículo está
// liberado para retirada e o cliente tem telefone cadastrado.
$whatsappLink = null;
if (in_array($os['status'] ?? '', ['AGUARDANDO_RETIRADA', 'CONCLUIDA'], true) && !empty($os['telefone_cliente'])) {
    $mensagem = "Olá, {$os['nome_cliente']}. Seu veículo (placa {$placa}), referente à OS nº {$numero}, está pronto para retirada.";
    if ($linkRastreio) {
        $mensagem .= " Acompanhe pelo link: {$linkRastreio}";
    }
    $whatsappLink = WhatsApp::linkParaCliente($os['telefone_cliente'], $mensagem);
}

// Stepper de acompanhamento (estilo rastreio) — a progressão do fluxo. "Aguardando
// peças" é uma pausa da execução e acende a mesma etapa de "Em execução".
// Cancelada fica fora da progressão linear (mostrada à parte).
$etapasStepper = array_intersect_key(
    OrdemServico::STATUS_LABEL,
    array_flip(['ABERTA', 'EM_ANDAMENTO', 'AGUARDANDO_CONFERENCIA', 'AGUARDANDO_RETIRADA', 'CONCLUIDA'])
);
$statusAtualOS = $os['status'] ?? 'ABERTA';
$statusNoStepper = $statusAtualOS === 'AGUARDANDO_PECA' ? 'EM_ANDAMENTO' : $statusAtualOS;
$indiceAtual   = array_search($statusNoStepper, array_keys($etapasStepper), true);

$encerrada    = in_array($statusAtualOS, OrdemServico::STATUS_ENCERRADOS, true);
$podeConsumir = in_array($statusAtualOS, ConsumoEstoque::OS_PERMITE_CONSUMO, true);
$podeReservar = in_array($statusAtualOS, ConsumoEstoque::OS_PERMITE_RESERVA, true);

// Mensagens de retorno das ações (ok=<codigo> | erro=fluxo&detalhe=<texto>)
$mensagensOk = [
    'conferida'      => 'OS conferida e liberada para retirada. A cobrança foi gerada.',
    'jaconferida'    => 'Esta OS já tinha sido conferida.',
    'devolvida'      => 'OS devolvida à execução.',
    'entregue'       => 'Veículo entregue ao cliente.',
    'jaentregue'     => 'Esta OS já tinha sido entregue.',
    'cancelada'      => 'OS cancelada.',
    'pecaconsumida'  => 'Consumo confirmado: o estoque foi baixado.',
    'pecajaconsumida' => 'Esta peça já tinha o consumo confirmado.',
    'pecadevolvida'  => 'Peça devolvida ao estoque.',
    'pecareservada'  => 'Peça reservada: o saldo disponível das outras OS diminuiu, o estoque físico continua igual.',
    'pecajareservada' => 'Esta peça já estava reservada.',
    'reservaliberada' => 'Reserva liberada.',
    'complementosolicitado' => 'Pedido de autorização complementar registrado. A OS só segue para conferência depois da resposta do cliente.',
    'complementodecidido'   => 'Resposta do cliente registrada.',
];
$flashOk   = $mensagensOk[$sucesso ?? ''] ?? null;

// Autorização do cliente: a OS aberta pelo atendimento carrega um teto (valor_autorizado).
$autorizacoes         = $autorizacoes ?? [];
$canaisAutorizacao    = $canaisAutorizacao ?? [];
$bloqueiosAutorizacao = $bloqueiosAutorizacao ?? [];
$valorAutorizado      = isset($os['valor_autorizado']) ? (float) $os['valor_autorizado'] : null;
$flashErro = ($erro ?? '') === 'fluxo' ? ($detalhe ?? 'Não foi possível concluir a etapa.') : null;
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

        <?php if ($flashOk !== null): ?>
            <div style="padding:12px 24px 0;">
                <?php $alertaTipo = 'sucesso'; $alertaMensagem = $flashOk;
                      require __DIR__ . '/../layout/_alerta.php'; ?>
            </div>
        <?php endif; ?>
        <?php if ($flashErro !== null): ?>
            <div style="padding:12px 24px 0;">
                <?php $alertaTipo = 'erro'; $alertaMensagem = $flashErro;
                      require __DIR__ . '/../layout/_alerta.php'; ?>
            </div>
        <?php endif; ?>

        <!-- AÇÕES DA ETAPA — cada botão é uma transição validada no servidor
             (OrdemServicoFluxo): o que aparece aqui é só o que o seu papel pode fazer agora. -->
        <?php
        $acoesEtapa = [];
        if ($statusAtualOS === 'AGUARDANDO_CONFERENCIA' && $ehAdmin) {
            $acoesEtapa[] = 'conferir';
            $acoesEtapa[] = 'devolver';
        } elseif ($statusAtualOS === 'AGUARDANDO_RETIRADA') {
            $acoesEtapa[] = 'entregar';
            if ($ehAdmin) {
                $acoesEtapa[] = 'devolver';
            }
        }
        if ($ehAdmin && !$encerrada) {
            $acoesEtapa[] = 'cancelar';
        }
        ?>
        <?php if ($statusAtualOS === 'AGUARDANDO_CONFERENCIA' && !$ehAdmin): ?>
            <div style="margin:16px 24px 0;padding:12px 16px;background:var(--color-bg-alt);border:1px solid var(--color-border);
                        border-radius:var(--radius-md);font-size:13px;color:var(--color-text-secondary);">
                <i class="bi bi-hourglass-split"></i>
                O serviço foi finalizado e aguarda a conferência do administrador para ser liberado ao cliente.
            </div>
        <?php endif; ?>
        <?php if (!empty($acoesEtapa)): ?>
            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding:14px 24px 0;">
                <?php if (in_array('conferir', $acoesEtapa, true)): ?>
                    <form action="./conferirOrdemServico" method="POST" style="display:inline;"
                          onsubmit="return confirm('Conferir esta OS e liberar o veículo para retirada? Isso calcula as comissões e gera a cobrança.')">
                        <input type="hidden" name="id_ordem_servico" value="<?= $idOS ?>">
                        <input type="hidden" name="voltar" value="view">
                        <button type="submit" class="btn btn--primary btn--sm">
                            <i class="bi bi-patch-check"></i> Conferir e liberar para retirada
                        </button>
                    </form>
                <?php endif; ?>
                <?php if (in_array('entregar', $acoesEtapa, true)): ?>
                    <button type="button" class="btn btn--primary btn--sm" onclick="abrirModalEntrega()">
                        <i class="bi bi-box-arrow-right"></i> Entregar ao cliente
                    </button>
                <?php endif; ?>
                <?php if (in_array('devolver', $acoesEtapa, true)): ?>
                    <button type="button" class="btn btn--ghost btn--sm" onclick="abrirModalMotivo('devolver')">
                        <i class="bi bi-arrow-counterclockwise"></i> Devolver à execução
                    </button>
                <?php endif; ?>
                <?php if (in_array('cancelar', $acoesEtapa, true)): ?>
                    <button type="button" class="btn btn--ghost btn--sm" style="color:var(--color-danger);" onclick="abrirModalMotivo('cancelar')">
                        <i class="bi bi-x-circle"></i> Cancelar OS
                    </button>
                <?php endif; ?>
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
                <div class="form-row-3">
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
                <div class="form-row-3">
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

            <!-- ③ TÉCNICOS RESPONSÁVEIS -->
            <div class="form-section">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);
                                border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:700;">③</span>
                    Técnicos Responsáveis
                </div>
                <?php if (!empty($tecnicos)): ?>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <?php foreach ($tecnicos as $t):
                            $nomeT = htmlspecialchars($t['tecnico_nome']);
                            $iniciais = strtoupper(implode('', array_map(fn($p) => $p[0], array_slice(explode(' ', $nomeT), 0, 2))));
                            $calculado = !empty($t['calculado_em']);
                        ?>
                            <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--color-bg-alt);
                                        border:1px solid var(--color-border);border-radius:var(--radius-md);">
                                <div style="width:36px;height:36px;border-radius:50%;background:var(--color-primary-soft);
                                            color:var(--color-primary);display:flex;align-items:center;justify-content:center;
                                            font-size:12px;font-weight:700;flex-shrink:0;">
                                    <?= $iniciais ?>
                                </div>
                                <div style="flex:1;">
                                    <div style="font-size:13px;font-weight:600;"><?= $nomeT ?></div>
                                    <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px;">
                                        <?php if ($calculado): ?>
                                            <?= htmlspecialchars(TecnicoRemuneracao::rotuloTipo($t['regra_tipo'])) ?>
                                            &nbsp;→&nbsp;
                                            <strong style="color:var(--color-primary);"><?= fmtBR((float) $t['valor_mao_obra']) ?></strong>
                                        <?php else: ?>
                                            Comissão calculada na finalização da OS
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($calculado): ?>
                                    <span class="badge <?= $t['status_pagamento'] === 'PAGO' ? 'badge--success' : 'badge--warning' ?>">
                                        <?= $t['status_pagamento'] === 'PAGO' ? 'Pago' : 'Pendente' ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge--secondary">Atribuído</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="font-size:13px;color:var(--color-text-muted);">Nenhum técnico atribuído.</p>
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
                                <th style="padding:8px 12px;text-align:left;border-bottom:1px solid var(--color-border);font-weight:600;color:var(--color-text-secondary);">Estoque</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itens as $it):
                                $tipo = $it['tipo'] ?? 'produto';
                                $preco = (float)($it['preco_unitario'] ?? $it['preco'] ?? 0);
                                $qty   = (float)($it['quantidade'] ?? $it['qty'] ?? 1);
                                $statusItem = $it['status_item'] ?? null;
                                $itemInativo = in_array($statusItem, ['DEVOLVIDO', 'CANCELADO'], true);
                                $rotuloItem = [
                                    'PLANEJADO' => ['Prevista', 'badge--neutral'],
                                    'RESERVADO' => ['Reservada', 'badge--info'],
                                    'CONSUMIDO' => ['Consumida', 'badge--success'],
                                    'DEVOLVIDO' => ['Devolvida', 'badge--warning'],
                                    'CANCELADO' => ['Cancelada', 'badge--danger'],
                                ][$statusItem] ?? null;
                            ?>
                                <tr style="border-bottom:1px solid var(--color-border);<?= $itemInativo ? 'opacity:.55;text-decoration:line-through;' : '' ?>">
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
                                    <td style="padding:9px 12px;text-align:center;font-size:13px;font-weight:700;"><?= fmtQtd($qty) ?></td>
                                    <td style="padding:9px 12px;text-align:right;font-size:12px;"><?= fmtBR($preco) ?></td>
                                    <td style="padding:9px 12px;text-align:right;font-size:13px;font-weight:700;"><?= fmtBR($preco * $qty) ?></td>
                                    <td style="padding:9px 12px;white-space:nowrap;text-decoration:none;">
                                        <?php if ($tipo === 'produto' && $rotuloItem): ?>
                                            <span class="badge <?= $rotuloItem[1] ?>"><?= $rotuloItem[0] ?></span>
                                            <?php
                                            $pendenteDeEstoque = in_array($statusItem, ['PLANEJADO', 'RESERVADO'], true) && isset($it['estoque_atual']);
                                            $saldoParaOItem = (float) ($it['estoque_atual'] ?? 0) - (float) ($it['reservado_outros'] ?? 0);
                                            ?>
                                            <?php if ($pendenteDeEstoque && $saldoParaOItem + 0.0005 < $qty): ?>
                                                <span class="badge badge--warning" title="Em estoque: <?= fmtQtd($it['estoque_atual']) ?> · reservado para outras OS: <?= fmtQtd($it['reservado_outros']) ?>">Sem saldo</span>
                                                <a class="btn btn--ghost btn--sm" href="./saveSolicitacao?produto_id=<?= (int) $it['id'] ?>&ordem_servico_id=<?= $idOS ?>&quantidade=<?= urlencode((string) $qty) ?>">Solicitar peça</a>
                                            <?php endif; ?>
                                            <?php if ($statusItem === 'PLANEJADO' && $podeReservar): ?>
                                                <form action="./reservarPecaOrdemServico" method="POST" style="display:inline;">
                                                    <input type="hidden" name="id_item" value="<?= (int) ($it['row_id'] ?? 0) ?>">
                                                    <input type="hidden" name="id_ordem_servico" value="<?= $idOS ?>">
                                                    <input type="hidden" name="voltar" value="view">
                                                    <button type="submit" class="btn btn--ghost btn--sm" title="Separa a peça para esta OS sem baixar o estoque">Reservar</button>
                                                </form>
                                            <?php elseif ($statusItem === 'RESERVADO' && $podeReservar): ?>
                                                <form action="./liberarReservaOrdemServico" method="POST" style="display:inline;">
                                                    <input type="hidden" name="id_item" value="<?= (int) ($it['row_id'] ?? 0) ?>">
                                                    <input type="hidden" name="id_ordem_servico" value="<?= $idOS ?>">
                                                    <input type="hidden" name="voltar" value="view">
                                                    <button type="submit" class="btn btn--ghost btn--sm">Liberar reserva</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (in_array($statusItem, ['PLANEJADO', 'RESERVADO'], true) && $podeConsumir): ?>
                                                <form action="./consumirPecaOrdemServico" method="POST" style="display:inline;"
                                                      onsubmit="return confirm('Confirmar que esta peça foi usada? O estoque será baixado agora.')">
                                                    <input type="hidden" name="id_item" value="<?= (int) ($it['row_id'] ?? 0) ?>">
                                                    <input type="hidden" name="id_ordem_servico" value="<?= $idOS ?>">
                                                    <input type="hidden" name="voltar" value="view">
                                                    <button type="submit" class="btn btn--ghost btn--sm" title="Confirma o uso e baixa o estoque">Confirmar consumo</button>
                                                </form>
                                            <?php elseif ($statusItem === 'CONSUMIDO' && !$encerrada): ?>
                                                <button type="button" class="btn btn--ghost btn--sm"
                                                        data-item="<?= (int) ($it['row_id'] ?? 0) ?>"
                                                        data-nome="<?= htmlspecialchars($it['nome'] ?? '') ?>"
                                                        onclick="abrirModalDevolverPeca(this)">Devolver</button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:var(--color-text-muted);font-size:11px;">—</span>
                                        <?php endif; ?>
                                    </td>
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
                <div class="form-row-2">
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
                <div class="form-row-5">
                    <?php
                    $cards = [
                        ['Total em Peças',    $totalPecas,    false],
                        ['Total em Serviços', $totalServicos, false],
                    ];
                    // Comissão dos técnicos e resultado são da gestão: ficam só para o administrador.
                    if ($ehAdmin) {
                        $cards[] = ['Comissão Técnico', $comissao, false];
                        $cards[] = ['Lucro Líquido',    $lucro,    false];
                    }
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

            <!-- ⑦ COBRANÇA — o que o cliente deve e o que já foi recebido. Concluir ou
                 entregar a OS não dá baixa em nada: cada parcela só vira "paga" quando
                 o recebimento é registrado. -->
            <div class="form-section">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);
                                border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:700;">⑦</span>
                    Cobrança
                </div>
                <?php if (empty($parcelas)): ?>
                    <p style="font-size:13px;color:var(--color-text-muted);">
                        A cobrança é gerada quando o administrador confere a OS. Até lá, nada é devido nem recebido.
                    </p>
                <?php else: ?>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:12px;">
                        <div style="background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:10px 14px;">
                            <div style="font-size:11px;color:var(--color-text-muted);">Recebido</div>
                            <div style="font-size:16px;font-weight:700;color:#16A34A;"><?= fmtBR((float) ($resumoCobranca['valor_pago'] ?? 0)) ?></div>
                        </div>
                        <div style="background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:10px 14px;">
                            <div style="font-size:11px;color:var(--color-text-muted);">A receber</div>
                            <div style="font-size:16px;font-weight:700;"><?= fmtBR((float) ($resumoCobranca['valor_pendente'] ?? 0)) ?></div>
                        </div>
                        <?php if (($resumoCobranca['qtd_atrasadas'] ?? 0) > 0): ?>
                        <div style="background:var(--color-danger-soft);border:1px solid var(--color-danger);border-radius:var(--radius-md);padding:10px 14px;">
                            <div style="font-size:11px;color:var(--color-danger);">Vencidas</div>
                            <div style="font-size:16px;font-weight:700;color:var(--color-danger);"><?= (int) $resumoCobranca['qtd_atrasadas'] ?> parcela(s)</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <table style="width:100%;border-collapse:collapse;font-size:12px;">
                        <thead>
                            <tr style="background:var(--color-bg-alt);">
                                <th style="padding:8px 12px;text-align:left;">Parcela</th>
                                <th style="padding:8px 12px;text-align:left;">Vencimento</th>
                                <th style="padding:8px 12px;text-align:right;">Valor</th>
                                <th style="padding:8px 12px;text-align:left;">Situação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parcelas as $p):
                                $paga = (int) $p['pago'] === 1;
                                $vencida = !$paga && !empty($p['vencimento']) && $p['vencimento'] < date('Y-m-d');
                            ?>
                                <tr style="border-bottom:1px solid var(--color-border);">
                                    <td style="padding:8px 12px;"><?= (int) $p['numero'] ?>/<?= count($parcelas) ?></td>
                                    <td style="padding:8px 12px;"><?= !empty($p['vencimento']) ? date('d/m/Y', strtotime($p['vencimento'])) : '—' ?></td>
                                    <td style="padding:8px 12px;text-align:right;font-weight:700;"><?= fmtBR((float) $p['valor']) ?></td>
                                    <td style="padding:8px 12px;">
                                        <?php if ($paga): ?>
                                            <span class="badge badge--success">Recebida · <?= htmlspecialchars(MovimentacaoCaixa::rotuloForma($p['forma_pagamento'])) ?></span>
                                        <?php elseif ($vencida): ?>
                                            <span class="badge badge--danger">Vencida</span>
                                        <?php else: ?>
                                            <span class="badge badge--warning">A receber</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <?php if ($valorAutorizado !== null || !empty($autorizacoes)): ?>
            <!-- AUTORIZAÇÃO DO CLIENTE — o teto do que a oficina pode executar -->
            <div class="form-section">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);
                                border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:700;"><i class="bi bi-shield-check"></i></span>
                    Autorização do cliente
                </div>

                <?php if ($valorAutorizado !== null): ?>
                    <p style="font-size:13px;margin:0 0 10px;">
                        Autorizado até <strong><?= fmtBR($valorAutorizado) ?></strong> · valor atual da OS <strong><?= fmtBR($totalOS) ?></strong>
                        <?php if ($totalOS > $valorAutorizado + 0.005): ?>
                            <span class="badge badge--warning">Acima do autorizado</span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <?php foreach ($bloqueiosAutorizacao as $bloqueio): ?>
                    <?php $alertaTipo = 'aviso'; $alertaMensagem = $bloqueio; require __DIR__ . '/../layout/_alerta.php'; ?>
                <?php endforeach; ?>

                <?php if (!empty($autorizacoes)): ?>
                    <ol style="list-style:none;margin:0 0 12px;padding:0;display:flex;flex-direction:column;gap:8px;">
                        <?php foreach ($autorizacoes as $a): ?>
                            <li style="padding:10px 14px;background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);font-size:12.5px;">
                                <strong><?= ['ORCAMENTO' => 'Aprovação do orçamento', 'VALOR_ABERTO' => 'Valor em aberto', 'COMPLEMENTAR' => 'Complemento'][$a['tipo']] ?? htmlspecialchars($a['tipo']) ?></strong>
                                · <?= htmlspecialchars(AutorizacaoCliente::STATUS_LABEL[$a['status']] ?? $a['status']) ?>
                                <?php if ($a['tipo'] === 'COMPLEMENTAR'): ?> · + <?= fmtBR((float) $a['valor_adicional']) ?>
                                <?php elseif ($a['limite_valor'] !== null): ?> · até <?= fmtBR((float) $a['limite_valor']) ?><?php endif; ?>
                                <div style="margin-top:2px;"><?= htmlspecialchars($a['escopo']) ?></div>
                                <div style="color:var(--color-text-muted);font-size:11.5px;margin-top:2px;">
                                    <?= htmlspecialchars($canaisAutorizacao[$a['canal']] ?? $a['canal']) ?>
                                    · <?= date('d/m/Y H:i', strtotime($a['registrada_em'])) ?> · <?= htmlspecialchars($a['registrada_por_nome'] ?? '—') ?>
                                    <?php if (!empty($a['evidencia'])): ?> · <?= htmlspecialchars($a['evidencia']) ?><?php endif; ?>
                                </div>

                                <?php if ($a['status'] === 'SOLICITADA' && !$encerrada): ?>
                                    <details style="margin-top:8px;">
                                        <summary style="cursor:pointer;font-weight:600;color:var(--color-primary);">Registrar a resposta do cliente</summary>
                                        <form action="./decidirAutorizacaoOrdemServico" method="POST" style="margin-top:8px;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id_ordem_servico" value="<?= $idOS ?>">
                                            <input type="hidden" name="id_autorizacao" value="<?= (int) $a['id_autorizacao'] ?>">
                                            <input type="hidden" name="voltar" value="view">
                                            <div class="form-grid">
                                                <div class="form-field">
                                                    <label>Canal</label>
                                                    <select name="canal" data-no-ts required>
                                                        <?php foreach ($canaisAutorizacao as $chave => $rotulo): if ($chave === 'PORTAL') continue; ?>
                                                            <option value="<?= htmlspecialchars($chave) ?>"><?= htmlspecialchars($rotulo) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="form-field">
                                                    <label>Evidência</label>
                                                    <input type="text" name="evidencia" maxlength="500" required placeholder="Como o cliente respondeu?">
                                                </div>
                                            </div>
                                            <div style="display:flex;gap:8px;margin-top:8px;">
                                                <button type="submit" name="decisao" value="aprovar" class="btn btn--sm" style="background:var(--color-success);color:#fff;border-color:var(--color-success);">Cliente autorizou</button>
                                                <button type="submit" name="decisao" value="recusar" class="btn btn--danger btn--sm">Cliente recusou</button>
                                            </div>
                                        </form>
                                    </details>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>

                <?php if ($valorAutorizado !== null && !$encerrada): ?>
                    <details>
                        <summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--color-primary);">Solicitar autorização complementar</summary>
                        <form action="./solicitarComplementoOrdemServico" method="POST" style="margin-top:8px;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id_ordem_servico" value="<?= $idOS ?>">
                            <input type="hidden" name="voltar" value="view">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label>Valor adicional (R$)</label>
                                    <input type="text" inputmode="decimal" name="valor_adicional" required placeholder="0,00">
                                </div>
                                <div class="form-field">
                                    <label>O que mudou?</label>
                                    <input type="text" name="motivo" maxlength="255" required placeholder="Ex.: encontrado desgaste no disco, precisa trocar também">
                                </div>
                            </div>
                            <button type="submit" class="btn btn--ghost btn--sm" style="margin-top:8px;">Registrar pedido</button>
                        </form>
                    </details>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <!-- ⑧ HISTÓRICO — quem levou a OS para cada etapa, quando e por quê -->
            <div class="form-section">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);
                                border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:700;">⑧</span>
                    Histórico da OS
                </div>
                <?php if (empty($historico)): ?>
                    <p style="font-size:13px;color:var(--color-text-muted);">Sem registros de etapa para esta OS.</p>
                <?php else: ?>
                    <ol style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px;">
                        <?php foreach ($historico as $h): ?>
                            <li style="padding:10px 14px;background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);font-size:12.5px;">
                                <strong><?= htmlspecialchars(OrdemServico::rotuloStatus($h['estado_novo']) ?: $h['estado_novo']) ?></strong>
                                <?php if (!empty($h['estado_anterior'])): ?>
                                    <span style="color:var(--color-text-muted);">← <?= htmlspecialchars(OrdemServico::rotuloStatus($h['estado_anterior'])) ?></span>
                                <?php endif; ?>
                                <div style="color:var(--color-text-muted);font-size:11.5px;margin-top:2px;">
                                    <?= date('d/m/Y H:i', strtotime($h['criado_em'])) ?>
                                    · <?= htmlspecialchars($h['usuario_nome'] ?? ucfirst(strtolower($h['origem']))) ?>
                                </div>
                                <?php if (!empty($h['motivo'])): ?>
                                    <div style="margin-top:4px;"><?= htmlspecialchars($h['motivo']) ?></div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>

            <?php if (!in_array($os['status'], ['CONCLUIDA', 'CANCELADA'], true)): ?>
            <!-- PREVISÃO DE CONCLUSÃO — é o que o cliente vê no portal (uma estimativa, não uma promessa) -->
            <div class="form-section">
                <div class="form-section__title">Previsão de conclusão</div>
                <form method="post" action="./salvarPrevisaoOS" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_ordem_servico" value="<?= (int) $os['id_ordem_servico'] ?>">
                    <input type="datetime-local" name="previsao" value="<?= !empty($os['previsao_conclusao']) ? date('Y-m-d\TH:i', strtotime($os['previsao_conclusao'])) : '' ?>">
                    <button type="submit" class="btn btn--ghost btn--sm">Salvar previsão</button>
                    <small class="text-muted">Deixe em branco para não informar ao cliente.</small>
                </form>
            </div>
            <?php endif; ?>

            <div style="margin-bottom:16px;">
                <?php $linkEscopo = 'OS'; $linkEntidadeId = (int) $os['id_ordem_servico']; require __DIR__ . '/../Portal/_linkPortal.php'; ?>
            </div>

            <?php if (!empty($os['assinatura_cliente'])): ?>
            <!-- ⑨ ASSINATURA DO CLIENTE -->
            <div class="form-section" style="margin-bottom:0;">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);
                                border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:700;">⑨</span>
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
            <a href="./pagamentoOrdemServico?id=<?= (int) ($os['id_ordem_servico'] ?? 0) ?>" class="btn btn--ghost btn--sm">
                <i class="bi bi-cash-coin"></i> Pagamento
            </a>
            <?php if ($linkRastreio): ?>
            <button type="button" class="btn btn--ghost btn--sm" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($linkRastreio, ENT_QUOTES) ?>'); this.querySelector('span').textContent = 'Copiado!'; setTimeout(() => this.querySelector('span').textContent = 'Link de Rastreio', 2000);">
                <i class="bi bi-link-45deg"></i> <span>Link de Rastreio</span>
            </button>
            <?php endif; ?>
            <!-- Imprimir abre o MESMO documento do "Baixar PDF", só que na aba
                 (inline). Antes era window.print() na tela: saía a interface
                 do sistema impressa em vez da ordem de serviço. O PDF é o
                 documento de verdade — timbre, A4, cabeçalho de tabela
                 repetido e "Página X de Y". -->
            <a href="./exportOrdemServicoPdf?id=<?= (int) ($os['id_ordem_servico'] ?? 0) ?>&inline=1"
               target="_blank" rel="noopener" class="btn btn--ghost btn--sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9" />
                    <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2" />
                    <rect x="6" y="14" width="12" height="8" />
                </svg>
                Imprimir
            </a>
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
<?php
$fluxoVoltar = 'view';
$fluxoOs     = ['id' => $idOS, 'numero' => (string) ($os['numero_os'] ?? '')];
require __DIR__ . '/_modaisFluxo.php';
