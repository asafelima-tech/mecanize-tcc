<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$veiculo = $dados_veiculo ?? [];

$id       = (int)($veiculo['id_veiculo']    ?? 0);
$cliente = htmlspecialchars($veiculo['cliente_nome'] ?? '—');
$marca   = htmlspecialchars($veiculo['marca_nome']   ?? '—');
$modelo  = htmlspecialchars($veiculo['modelo_nome']  ?? '—');
$placa    = htmlspecialchars(strtoupper($veiculo['placa'] ?? '—'));
$ano      = htmlspecialchars($veiculo['ano']          ?? '—');
$cor      = htmlspecialchars($veiculo['cor']          ?? '—');
$combustivel = htmlspecialchars($veiculo['combustivel'] ?? '—');
$obs      = htmlspecialchars($veiculo['observacoes']  ?? '—');
$criado     = !empty($veiculo['criado_em'])     ? date('d/m/Y H:i', strtotime($veiculo['criado_em']))     : '—';
$atualizado = !empty($veiculo['atualizado_em']) ? date('d/m/Y H:i', strtotime($veiculo['atualizado_em'])) : '—';

// Iniciais da placa para o avatar
$avatarTexto = $placa !== '—' ? strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', $placa), 0, 3)) : '??';

$timeline = $timeline ?? [];
?>

<style>
    .timeline {
        position: relative;
        margin-left: 6px;
        padding-left: 22px;
        border-left: 2px solid var(--color-border);
    }

    .timeline__item {
        position: relative;
        padding-bottom: 20px;
    }

    .timeline__item:last-child {
        padding-bottom: 0;
    }

    .timeline__dot {
        position: absolute;
        left: -29px;
        top: 2px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: var(--color-primary-soft);
        color: var(--color-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 9px;
        border: 2px solid var(--color-surface);
    }

    .timeline__row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .timeline__texto {
        font-size: 13px;
        font-weight: 600;
        color: var(--color-text);
    }

    .timeline__texto a {
        color: inherit;
        text-decoration: none;
    }

    .timeline__texto a:hover {
        color: var(--color-primary);
    }

    .timeline__data {
        font-size: 11px;
        color: var(--color-text-muted);
        margin-top: 2px;
    }

    .timeline__valor {
        font-size: 12.5px;
        font-weight: 700;
        color: var(--color-text-secondary);
        white-space: nowrap;
    }
</style>

<div class="modal-overlay" id="modalViewVeiculo" style="align-items:flex-start;padding:32px 24px;overflow-y:auto;">
    <div class="modal modal--xl" style="max-height:none;">

        <!-- HEADER -->
        <div class="modal__header">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:40px;height:40px;background:var(--color-primary-soft);color:var(--color-primary);
                            border-radius:var(--radius-md);display:inline-flex;align-items:center;justify-content:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2-.001M13 16H9m4 0h2m2 0h1a1 1 0 001-1v-3.65a1 1 0 00-.22-.624l-2.34-2.81A1 1 0 0014.66 8H13" />
                    </svg>
                </div>
                <div>
                    <div class="modal__title">Veículo — <?= $marca ?> <?= $modelo ?></div>
                    <div class="modal__subtitle">Visualização detalhada do veículo</div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <!-- Badge da placa -->
                <span style="padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;letter-spacing:1px;
                             background:#F3F4F6;color:#374151;border:1px solid #E5E7EB;font-family:monospace;">
                    <?= $placa ?>
                </span>
                <a href="javascript:history.back()" class="modal__close" title="Fechar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </a>
            </div>
        </div>

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
                        'ID do Veículo'    => '#' . $id,
                        'Data de Cadastro' => $criado,
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

            <!-- ② DADOS DO VEÍCULO -->
            <div class="form-section">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);
                                border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:700;">②</span>
                    Dados do Veículo
                </div>

                <!-- Card destaque -->
                <div style="display:flex;align-items:center;gap:14px;padding:14px 16px;
                            background:var(--color-bg-alt);border:1px solid var(--color-border);
                            border-radius:var(--radius-md);margin-bottom:14px;">
                    <!-- Avatar placa -->
                    <div style="width:56px;height:56px;border-radius:var(--radius-md);background:var(--color-primary-soft);
                                color:var(--color-primary);display:flex;align-items:center;justify-content:center;
                                font-size:14px;font-weight:800;flex-shrink:0;letter-spacing:2px;font-family:monospace;">
                        <?= $avatarTexto ?>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:16px;font-weight:700;color:var(--color-text);">
                            <?= $marca ?> <?= $modelo ?>
                        </div>
                        <div style="font-size:12px;color:var(--color-text-muted);margin-top:3px;">
                            <?= $ano !== '—' ? $ano : '' ?>
                            <?= ($ano !== '—' && $cor !== '—') ? ' &nbsp;·&nbsp; ' : '' ?>
                            <?= $cor !== '—' ? $cor : '' ?>
                        </div>
                    </div>
                    <!-- Placa estilo brasileira -->
                    <div style="padding:6px 14px;border-radius:var(--radius-sm);background:#1D4ED8;color:#fff;
                                font-size:15px;font-weight:800;letter-spacing:2px;font-family:monospace;">
                        <?= $placa ?>
                    </div>
                </div>

                <!-- Grid de campos -->
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px 18px;">
                    <?php
                    $campos2 = [
                        'Marca'  => $marca,
                        'Modelo' => $modelo,
                        'Placa'  => $placa,
                        'Ano'    => $ano,
                        'Cor'    => $cor,
                        'Combustível' => $combustivel,
                    ];
                    foreach ($campos2 as $label => $val): ?>
                        <div class="form-field">
                            <label style="font-size:11px;color:var(--color-text-muted);font-weight:600;"><?= $label ?></label>
                            <div style="padding:9px 12px;background:var(--color-bg-alt);border:1px solid var(--color-border);
                                        border-radius:var(--radius-md);font-size:13px;font-weight:600;color:var(--color-text);
                                        <?= $label === 'Placa' ? 'font-family:monospace;letter-spacing:1px;' : '' ?>">
                                <?= $val ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ③ CLIENTE VINCULADO -->
            <div class="form-section">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);
                                border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:700;">③</span>
                    Cliente Vinculado
                </div>
                <?php if ($cliente !== '—'): ?>
                    <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--color-bg-alt);
                                border:1px solid var(--color-border);border-radius:var(--radius-md);">
                        <div style="width:36px;height:36px;border-radius:50%;background:var(--color-primary-soft);
                                    color:var(--color-primary);display:flex;align-items:center;justify-content:center;
                                    font-size:12px;font-weight:700;flex-shrink:0;">
                            <?= strtoupper(implode('', array_map(fn($p) => $p[0], array_slice(explode(' ', $cliente), 0, 2)))) ?>
                        </div>
                        <div style="flex:1;">
                            <div style="font-size:13px;font-weight:600;color:var(--color-text);"><?= $cliente ?></div>
                            <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px;">Proprietário do veículo</div>
                        </div>
                        <?php if (!empty($veiculo['id_cliente'])): ?>
                            <a href="./viewCliente?id=<?= (int)$veiculo['id_cliente'] ?>" class="btn btn--ghost btn--sm">
                                Ver cliente
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p style="font-size:13px;color:var(--color-text-muted);">Nenhum cliente vinculado.</p>
                <?php endif; ?>
            </div>

            <!-- ④ OBSERVAÇÕES -->
            <div class="form-section">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);
                                border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:700;">④</span>
                    Observações
                </div>
                <div style="padding:9px 12px;background:var(--color-bg-alt);border:1px solid var(--color-border);
                            border-radius:var(--radius-md);font-size:13px;color:var(--color-text);
                            min-height:60px;white-space:pre-wrap;line-height:1.5;">
                    <?= $obs ?>
                </div>
            </div>

            <!-- ⑤ HISTÓRICO -->
            <div class="form-section" style="margin-bottom:0;">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);
                                border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:700;">⑤</span>
                    Histórico do Veículo
                </div>
                <?php if (empty($timeline)): ?>
                    <p style="font-size:13px;color:var(--color-text-muted);">Nenhum evento registrado ainda.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($timeline as $evento): ?>
                            <div class="timeline__item">
                                <div class="timeline__dot"><i class="bi <?= htmlspecialchars($evento['icone']) ?>"></i></div>
                                <div class="timeline__row">
                                    <div>
                                        <div class="timeline__texto">
                                            <?php if ($evento['link']): ?>
                                                <a href="<?= htmlspecialchars($evento['link']) ?>"><?= htmlspecialchars($evento['texto']) ?></a>
                                            <?php else: ?>
                                                <?= htmlspecialchars($evento['texto']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="timeline__data">
                                            <?= !empty($evento['data']) ? date('d/m/Y \à\s H:i', strtotime($evento['data'])) : '-' ?>
                                        </div>
                                    </div>
                                    <?php if ($evento['valor'] !== null): ?>
                                        <div class="timeline__valor">R$ <?= number_format($evento['valor'], 2, ',', '.') ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div><!-- /modal__body -->

        <!-- FOOTER -->
        <div class="modal__footer">
            <div class="modal__footer-left">
                <span style="margin-right:16px;">Cadastro: <?= $criado ?></span>
                <span>Usuário: <?= htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário') ?> (<?= htmlspecialchars($_SESSION['user_tipo'] ?? 'Administrador') ?>)</span>
            </div>

            <a href="./editVeiculo&id=<?= $id ?>" class="btn btn--ghost btn--sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5
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