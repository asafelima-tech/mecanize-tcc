<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cliente = $dados_cliente ?? [];

$id        = (int)($cliente['id_cliente']   ?? 0);
$nome      = htmlspecialchars($cliente['nome']         ?? '—');
$email     = htmlspecialchars($cliente['email']        ?? '—');
$telefone  = htmlspecialchars($cliente['telefone']     ?? '—');
$tipoPessoa = htmlspecialchars($cliente['tipo_pessoa']  ?? '—');
$cpf       = htmlspecialchars($cliente['cpf']          ?? '');
$cnpj      = htmlspecialchars($cliente['cnpj']         ?? '');
$doc       = $tipoPessoa === 'PJ' ? $cnpj : $cpf;
$docLabel  = $tipoPessoa === 'PJ' ? 'CNPJ' : 'CPF';

$cep       = htmlspecialchars($cliente['cep']          ?? '—');
$endereco  = htmlspecialchars($cliente['endereco']     ?? '—');
$bairro    = htmlspecialchars($cliente['bairro']       ?? '—');
$cidade    = htmlspecialchars($cliente['cidade']       ?? '—');
$uf        = htmlspecialchars($cliente['uf']           ?? '—');
$obs       = htmlspecialchars($cliente['observacoes']  ?? '—');
$ativo     = isset($cliente['ativo']) ? (int)$cliente['ativo'] : 1;
$criado    = !empty($cliente['criado_em'])     ? date('d/m/Y H:i', strtotime($cliente['criado_em']))     : '—';
$atualizado = !empty($cliente['atualizado_em']) ? date('d/m/Y H:i', strtotime($cliente['atualizado_em'])) : '—';

$tipoPessoaLabel = $tipoPessoa === 'PJ' ? 'Pessoa Jurídica' : 'Pessoa Física';
$tipoPessoaColors = [
    'PF' => ['bg' => '#DBEAFE', 'color' => '#1D4ED8', 'border' => '#BFDBFE'],
    'PJ' => ['bg' => '#EDE9FE', 'color' => '#6D28D9', 'border' => '#DDD6FE'],
];
$tc = $tipoPessoaColors[$cliente['tipo_pessoa'] ?? ''] ?? ['bg' => '#F3F4F6', 'color' => '#6B7280', 'border' => '#E5E7EB'];

$statusBg     = $ativo ? '#DCFCE7' : '#FEE2E2';
$statusColor  = $ativo ? '#16A34A' : '#DC2626';
$statusBorder = $ativo ? '#BBF7D0' : '#FECACA';
$statusLabel  = $ativo ? 'Ativo'   : 'Inativo';

$enderecoCompleto = implode(', ', array_filter([$endereco !== '—' ? $endereco : '', $bairro !== '—' ? $bairro : '', $cidade !== '—' ? $cidade : '', $uf !== '—' ? $uf : '']));
if (empty($enderecoCompleto)) $enderecoCompleto = '—';

// Iniciais para avatar
$iniciais = mb_strtoupper(implode('', array_map(
    fn($p) => mb_substr($p, 0, 1),
    array_slice(explode(' ', $nome), 0, 2)
)));
?>

<div class="modal-overlay" id="modalViewCliente" style="align-items:flex-start;padding:32px 24px;overflow-y:auto;">
    <div class="modal modal--xl" style="max-height:none;">

        <!-- HEADER -->
        <div class="modal__header">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:40px;height:40px;background:var(--color-primary-soft);color:var(--color-primary);
                            border-radius:var(--radius-md);display:inline-flex;align-items:center;justify-content:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 100-8 4 4 0 000 8z" />
                    </svg>
                </div>
                <div>
                    <div class="modal__title">Cliente — <?= $nome ?></div>
                    <div class="modal__subtitle">Visualização detalhada do cliente</div>
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
                        'ID do Cliente'       => '#' . $id,
                        'Data de Cadastro'    => $criado,
                        'Última Atualização'  => $atualizado,
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

            <!-- ② DADOS DO CLIENTE -->
            <div class="form-section">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);
                                border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:700;">②</span>
                    Dados do Cliente
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
                        <div style="font-size:12px;color:var(--color-text-muted);margin-top:3px;">
                            <?= $email !== '—' ? $email : '' ?>
                            <?= ($email !== '—' && $telefone !== '—') ? ' &nbsp;·&nbsp; ' : '' ?>
                            <?= $telefone !== '—' ? $telefone : '' ?>
                        </div>
                    </div>
                    <span style="padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;
                                 background:<?= $tc['bg'] ?>;color:<?= $tc['color'] ?>;border:1px solid <?= $tc['border'] ?>;">
                        <?= $tipoPessoaLabel ?>
                    </span>
                </div>

                <!-- Campos detalhados -->
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px 18px;">
                    <?php
                    $campos2 = [
                        'Nome'      => $nome,
                        'E-mail'    => $email,
                        'Telefone'  => $telefone,
                        $docLabel   => $doc ?: '—',
                        'Tipo de Pessoa' => $tipoPessoaLabel,
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

            <!-- ③ ENDEREÇO -->
            <div class="form-section">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);
                                border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:700;">③</span>
                    Endereço
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:14px 18px;">
                    <?php
                    $camposEnd = [
                        'CEP'      => $cep,
                        'Endereço' => $endereco,
                        'Bairro'   => $bairro,
                        'Cidade'   => $cidade,
                        'UF'       => $uf,
                    ];
                    $i = 0;
                    foreach ($camposEnd as $label => $val):
                        $span = ($label === 'Endereço') ? 'grid-column:span 2;' : '';
                    ?>
                        <div class="form-field" style="<?= $span ?>">
                            <label style="font-size:11px;color:var(--color-text-muted);font-weight:600;"><?= $label ?></label>
                            <div style="padding:9px 12px;background:var(--color-bg-alt);border:1px solid var(--color-border);
                                        border-radius:var(--radius-md);font-size:13px;font-weight:600;color:var(--color-text);">
                                <?= $val ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ④ CONFIGURAÇÕES -->
            <div class="form-section" style="margin-bottom:0;">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);
                                border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;
                                font-size:11px;font-weight:700;">④</span>
                    Configurações
                </div>
                <div style="display:grid;grid-template-columns:1fr 3fr;gap:14px 18px;align-items:start;">

                    <!-- Status -->
                    <div class="form-field">
                        <label style="font-size:11px;color:var(--color-text-muted);font-weight:600;">Status</label>
                        <div style="padding:9px 12px;background:var(--color-bg-alt);border:1px solid var(--color-border);
                                    border-radius:var(--radius-md);display:flex;align-items:center;gap:8px;">
                            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;
                                         background:<?= $statusColor ?>;flex-shrink:0;"></span>
                            <span style="font-size:13px;font-weight:600;color:<?= $statusColor ?>;"><?= $statusLabel ?></span>
                        </div>
                    </div>

                    <!-- Observações -->
                    <div class="form-field">
                        <label style="font-size:11px;color:var(--color-text-muted);font-weight:600;">Observações</label>
                        <div style="padding:9px 12px;background:var(--color-bg-alt);border:1px solid var(--color-border);
                                    border-radius:var(--radius-md);font-size:13px;color:var(--color-text);
                                    min-height:60px;white-space:pre-wrap;line-height:1.5;">
                            <?= $obs ?>
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

            <a href="./editCliente&id=<?= $id ?>" class="btn btn--ghost btn--sm">
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