<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$usuario = $dados_usuario ?? [];

$id     = (int)($usuario['id_usuario']    ?? 0);
$nome   = htmlspecialchars($usuario['nome']          ?? '—');
$email  = htmlspecialchars($usuario['email']         ?? '—');
$tipo   = htmlspecialchars($usuario['tipo']          ?? '—');
$ativo  = isset($usuario['ativo']) ? (int)$usuario['ativo'] : 1;
$criado = !empty($usuario['criado_em']) ? date('d/m/Y H:i', strtotime($usuario['criado_em'])) : '—';
$atualizado = !empty($usuario['atualizado_em']) ? date('d/m/Y H:i', strtotime($usuario['atualizado_em'])) : '—';

$tipoLabels = [
    'ADMIN'     => 'Administrador',
    'TECNICO'   => 'Técnico',
    'ATENDENTE' => 'Atendente',
];
$tipoLabel = $tipoLabels[$usuario['tipo'] ?? ''] ?? $tipo;

$tipoColors = [
    'ADMIN'     => ['bg' => '#EDE9FE', 'color' => '#6D28D9', 'border' => '#DDD6FE'],
    'TECNICO'   => ['bg' => '#DBEAFE', 'color' => '#1D4ED8', 'border' => '#BFDBFE'],
    'ATENDENTE' => ['bg' => '#FEF9C3', 'color' => '#A16207', 'border' => '#FEF08A'],
];
$tc = $tipoColors[$usuario['tipo'] ?? ''] ?? ['bg' => '#F3F4F6', 'color' => '#6B7280', 'border' => '#E5E7EB'];

$statusBg     = $ativo ? '#DCFCE7' : '#FEE2E2';
$statusColor  = $ativo ? '#16A34A' : '#DC2626';
$statusBorder = $ativo ? '#BBF7D0' : '#FECACA';
$statusLabel  = $ativo ? 'Ativo'   : 'Inativo';

// Iniciais para o avatar
$iniciais = strtoupper(implode('', array_map(
    fn($p) => $p[0],
    array_slice(explode(' ', $nome), 0, 2)
)));
?>

<div class="modal-overlay" id="modalViewUsuario" style="align-items:flex-start;padding:32px 24px;overflow-y:auto;">
    <div class="modal modal--xl" style="max-height:none;">

        <!-- HEADER -->
        <div class="modal__header">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:40px;height:40px;background:var(--color-primary-soft);color:var(--color-primary);
                            border-radius:var(--radius-md);display:inline-flex;align-items:center;justify-content:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" />
                        <circle cx="12" cy="7" r="4" />
                    </svg>
                </div>
                <div>
                    <div class="modal__title">Usuário — <?= $nome ?></div>
                    <div class="modal__subtitle">Visualização detalhada do usuário</div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;
                             background:<?= $statusBg ?>;color:<?= $statusColor ?>;border:1px solid <?= $statusBorder ?>;">
                    <?= $statusLabel ?>
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
                        'ID do Usuário'    => '#' . $id,
                        'Data de Cadastro' => $criado,
                        'Última Atualização' => $atualizado,
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

            <!-- ② DADOS DO USUÁRIO -->
            <div class="form-section">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);
                                border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:700;">②</span>
                    Dados do Usuário
                </div>

                <!-- Avatar + info principal -->
                <div style="display:flex;align-items:center;gap:14px;padding:14px 16px;
                            background:var(--color-bg-alt);border:1px solid var(--color-border);
                            border-radius:var(--radius-md);margin-bottom:14px;">
                    <div style="width:52px;height:52px;border-radius:50%;background:var(--color-primary-soft);
                                color:var(--color-primary);display:flex;align-items:center;justify-content:center;
                                font-size:17px;font-weight:700;flex-shrink:0;letter-spacing:1px;">
                        <?= $iniciais ?>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:16px;font-weight:700;color:var(--color-text);"><?= $nome ?></div>
                        <div style="font-size:12px;color:var(--color-text-muted);margin-top:3px;"><?= $email ?></div>
                    </div>
                    <span style="padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;
                                 background:<?= $tc['bg'] ?>;color:<?= $tc['color'] ?>;border:1px solid <?= $tc['border'] ?>;">
                        <?= $tipoLabel ?>
                    </span>
                </div>

                <!-- Campos detalhados -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px 18px;">
                    <?php
                    $campos2 = [
                        'Nome Completo' => $nome,
                        'Email'         => $email,
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

            <!-- ③ CONFIGURAÇÕES -->
            <div class="form-section" style="margin-bottom:0;">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);
                                border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:700;">③</span>
                    Configurações
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px 18px;">

                    <!-- Tipo de Usuário -->
                    <div class="form-field">
                        <label style="font-size:11px;color:var(--color-text-muted);font-weight:600;">Tipo de Usuário</label>
                        <div style="padding:9px 12px;background:var(--color-bg-alt);border:1px solid var(--color-border);
                                    border-radius:var(--radius-md);display:flex;align-items:center;gap:8px;">
                            <span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;
                                         background:<?= $tc['bg'] ?>;color:<?= $tc['color'] ?>;border:1px solid <?= $tc['border'] ?>;">
                                <?= $tipoLabel ?>
                            </span>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="form-field">
                        <label style="font-size:11px;color:var(--color-text-muted);font-weight:600;">Status do Usuário</label>
                        <div style="padding:9px 12px;background:var(--color-bg-alt);border:1px solid var(--color-border);
                                    border-radius:var(--radius-md);display:flex;align-items:center;gap:8px;">
                            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;
                                         background:<?= $statusColor ?>;flex-shrink:0;"></span>
                            <span style="font-size:13px;font-weight:600;color:<?= $statusColor ?>;"><?= $statusLabel ?></span>
                        </div>
                    </div>

                </div>
            </div>

        </div><!-- /modal__body -->

        <!-- FOOTER -->
        <div class="modal__footer">
            <div class="modal__footer-left">
                <span style="margin-right:16px;">Cadastro: <?= $criado ?></span>
                <span>Usuário: <?= htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário') ?> (<?= htmlspecialchars($_SESSION['user_tipo'] ?? 'Administrador') ?>)</span>
            </div>

            <a href="./editUsuario&id=<?= $id ?>" class="btn btn--ghost btn--sm">
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