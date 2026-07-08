<?php
// Variáveis esperadas do controller:
// $usuario['nome'], $usuario['email'], $usuario['tipo'], $usuario['telefone']
// $usuario['notificacoes']['ordens'], ['atualizacoes'], ['orcamentos']
// $mensagemSucesso, $mensagemErro

$iniciais = strtoupper(
    substr($usuario['nome'] ?? 'U', 0, 1) .
        substr($usuario['nome'] ?? 'U', -1)
);
?>

<!-- ── Page Header ── -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Meu Perfil</h1>
        <p class="page-header__subtitle">Gerencie suas informações pessoais, senha e preferências.</p>
    </div>
</div>

<!-- ── Alertas ── -->
<?php if (!empty($mensagemSucesso)): ?>
    <div class="profile-alert profile-alert--success">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12" />
        </svg>
        <?= htmlspecialchars($mensagemSucesso) ?>
    </div>
<?php endif; ?>

<?php if (!empty($mensagemErro)): ?>
    <div class="profile-alert profile-alert--danger">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        <?= htmlspecialchars($mensagemErro) ?>
    </div>
<?php endif; ?>

<div id="senhaAlertPlaceholder"></div>

<!-- ── Cabeçalho de Perfil ── -->
<section class="card profile-header">
    <div class="profile-header__avatar"><?= $iniciais ?></div>
    <div class="profile-header__info">
        <h2 class="profile-header__name"><?= htmlspecialchars($usuario['nome'] ?? 'Usuário') ?></h2>
        <span class="profile-header__email"><?= htmlspecialchars($usuario['email'] ?? '') ?></span>
    </div>
    <span class="badge-tipo"><?= htmlspecialchars($usuario['tipo'] ?? '') ?></span>
</section>

<!-- ── Dados Pessoais ── -->
<section class="card mt-24">
    <div class="card__header">
        <div class="card__title">
            <span class="ico-wrap ico">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                </svg>
            </span>
            Dados Pessoais
        </div>
        <span class="text-secondary fs-12">Atualize suas informações de contato</span>
    </div>

    <div class="card__body">
        <form method="POST">

            <div class="form-row">
                <div class="form-field">
                    <label>Nome Completo</label>
                    <input
                        type="text"
                        name="nome"
                        value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>"
                        placeholder="Seu nome completo"
                        required>
                </div>
                <div class="form-field">
                    <label>E-mail</label>
                    <input
                        type="email"
                        name="email"
                        value="<?= htmlspecialchars($usuario['email'] ?? '') ?>"
                        placeholder="seu@email.com"
                        required>
                </div>
            </div>

            <div class="form-row mt-12">
                <div class="form-field">
                    <label>Telefone</label>
                    <input
                        type="text"
                        name="telefone"
                        id="campoTelefone"
                        value="<?= htmlspecialchars($usuario['telefone'] ?? '') ?>"
                        placeholder="(00) 00000-0000">
                </div>
                <div class="form-field">
                    <label>Tipo de Usuário</label>
                    <div class="profile-readonly-row">
                        <span class="badge-tipo badge-tipo--inline"><?= htmlspecialchars($usuario['tipo'] ?? '') ?></span>
                        <span class="text-secondary fs-12">Definido pelo administrador</span>
                    </div>
                </div>
            </div>

            <div class="text-right mt-16">
                <button type="submit" class="btn btn--primary">Salvar Alterações</button>
            </div>

        </form>
    </div>
</section>

<!-- ── Segurança ── -->
<section class="card mt-24">
    <div class="card__header">
        <div class="card__title">
            <span class="ico-wrap ico">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
            </span>
            Segurança
        </div>
        <span class="text-secondary fs-12">Altere sua senha de acesso</span>
    </div>

    <div class="card__body">
        <form method="POST" action="./updateSenha" id="formSenha">
            <div class="form-row">
                <div class="form-field">
                    <label>Senha Atual</label>
                    <div class="password-field">
                        <input type="password" name="senha_atual" id="senhaAtual" placeholder="Digite sua senha atual" required>
                        <button type="button" class="password-field__toggle" data-target="senhaAtual" aria-label="Mostrar senha">
                            <svg class="icon-eye" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="form-field">
                    <label>Nova Senha</label>
                    <div class="password-field">
                        <input type="password" name="nova_senha" id="novaSenha" placeholder="Digite sua nova senha" minlength="6" required>
                        <button type="button" class="password-field__toggle" data-target="novaSenha" aria-label="Mostrar senha">
                            <svg class="icon-eye" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="form-field">
                    <label>Confirmar Nova Senha</label>
                    <div class="password-field">
                        <input type="password" name="confirmar_senha" id="confirmarSenha" placeholder="Confirme sua nova senha" minlength="6" required>
                        <button type="button" class="password-field__toggle" data-target="confirmarSenha" aria-label="Mostrar senha">
                            <svg class="icon-eye" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <div class="text-right mt-16">
                <button type="submit" class="btn btn--primary">Atualizar Senha</button>
            </div>
        </form>
    </div>
</section>

<!-- ── Notificações ── -->
<section class="card mt-24">
    <div class="card__header">
        <div class="card__title">
            <span class="ico-wrap ico">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                    <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                </svg>
            </span>
            Notificações
        </div>
        <span class="text-secondary fs-12">Escolha o que você quer ser avisado</span>
    </div>

    <div class="card__body">
        <form method="POST" action="./updateNotificacoes">

            <label class="notif-row">
                <div>
                    <div class="notif-row__title">Ordens de Serviço</div>
                    <div class="notif-row__desc">Avisos de abertura, aprovação e conclusão de OS</div>
                </div>
                <span class="toggle-switch">
                    <input type="checkbox" name="notif_ordens" <?= !empty($usuario['notificacoes']['ordens']) ? 'checked' : '' ?>>
                    <span class="toggle-switch__track"></span>
                </span>
            </label>

            <label class="notif-row">
                <div>
                    <div class="notif-row__title">Atualizações do Sistema</div>
                    <div class="notif-row__desc">Novidades, correções e manutenções programadas</div>
                </div>
                <span class="toggle-switch">
                    <input type="checkbox" name="notif_atualizacoes" <?= !empty($usuario['notificacoes']['atualizacoes']) ? 'checked' : '' ?>>
                    <span class="toggle-switch__track"></span>
                </span>
            </label>

            <label class="notif-row notif-row--last">
                <div>
                    <div class="notif-row__title">Orçamentos</div>
                    <div class="notif-row__desc">Avisos de aprovação, recusa ou expiração</div>
                </div>
                <span class="toggle-switch">
                    <input type="checkbox" name="notif_orcamentos" <?= !empty($usuario['notificacoes']['orcamentos']) ? 'checked' : '' ?>>
                    <span class="toggle-switch__track"></span>
                </span>
            </label>

            <div class="text-right mt-16">
                <button type="submit" class="btn btn--primary">Salvar Preferências</button>
            </div>
        </form>
    </div>
</section>

<!-- ── Estilos específicos desta tela ──
     Se preferir, mova este bloco para global.css junto dos outros
     estilos de tela — deixei aqui isolado pra facilitar a revisão. -->
<style>
    /* Cabeçalho de perfil */
    .profile-header {
        display: flex;
        align-items: center;
        gap: 18px;
        padding: 20px 24px;
    }

    .profile-header__avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3B82F6, #1E40AF);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 20px;
        flex-shrink: 0;
    }

    .profile-header__info {
        flex: 1;
        min-width: 0;
    }

    .profile-header__name {
        font-size: 17px;
        font-weight: 700;
        color: var(--color-text);
        margin: 0 0 2px;
    }

    .profile-header__email {
        font-size: 13px;
        color: var(--color-text-muted);
    }

    /* Badge de tipo de usuário */
    .badge-tipo {
        display: inline-flex;
        align-items: center;
        padding: 5px 12px;
        border-radius: 999px;
        background: var(--color-primary-soft);
        color: var(--color-primary);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .02em;
        text-transform: uppercase;
        flex-shrink: 0;
    }

    .profile-readonly-row {
        display: flex;
        align-items: center;
        gap: 10px;
        height: 38px;
    }

    .badge-tipo--inline {
        text-transform: none;
        letter-spacing: 0;
    }

    /* Campo de senha com botão de olho embutido */
    .password-field {
        position: relative;
    }

    .password-field input {
        width: 100%;
        padding-right: 40px;
    }

    .password-field__toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: var(--color-text-muted);
        display: flex;
        align-items: center;
        transition: color .15s ease;
    }

    .password-field__toggle:hover {
        color: var(--color-primary);
    }

    /* Linha de notificação */
    .notif-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 14px 4px;
        border-bottom: 1px solid var(--color-border);
        cursor: pointer;
    }

    .notif-row--last {
        border-bottom: none;
    }

    .notif-row__title {
        font-size: 13.5px;
        font-weight: 600;
        color: var(--color-text);
    }

    .notif-row__desc {
        font-size: 12px;
        color: var(--color-text-muted);
        margin-top: 2px;
    }

    /* Toggle switch */
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 40px;
        height: 24px;
        flex-shrink: 0;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-switch__track {
        position: absolute;
        inset: 0;
        background: var(--color-border);
        border-radius: 999px;
        transition: background .2s ease;
    }

    .toggle-switch__track::before {
        content: "";
        position: absolute;
        width: 18px;
        height: 18px;
        left: 3px;
        top: 3px;
        background: #fff;
        border-radius: 50%;
        transition: transform .2s ease;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .25);
    }

    .toggle-switch input:checked+.toggle-switch__track {
        background: var(--color-primary);
    }

    .toggle-switch input:checked+.toggle-switch__track::before {
        transform: translateX(16px);
    }

    /* Alertas */
    .profile-alert {
        display: flex;
        align-items: center;
        gap: 8px;
        border-radius: var(--radius-md);
        padding: 12px 16px;
        font-size: 13px;
        font-weight: 500;
        margin-bottom: 20px;
    }

    .profile-alert--success {
        background: #F0FDF4;
        border: 1px solid #BBF7D0;
        color: #16A34A;
    }

    .profile-alert--danger {
        background: var(--color-danger-soft);
        border: 1px solid #FECACA;
        color: #DC2626;
    }
</style>

<!-- ── Scripts ── -->
<script>
    // Mostrar/ocultar senha em qualquer campo marcado com .password-field
    document.querySelectorAll('.password-field__toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const input = document.getElementById(btn.dataset.target);
            const visivel = input.type === 'text';
            input.type = visivel ? 'password' : 'text';
            btn.querySelector('.icon-eye').innerHTML = visivel ?
                '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>' :
                '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
        });
    });

    // Máscara de telefone (usa a lib imask já carregada no header)
    if (window.IMask) {
        IMask(document.getElementById('campoTelefone'), {
            mask: [{
                    mask: '(00) 0000-0000'
                },
                {
                    mask: '(00) 00000-0000'
                }
            ]
        });
    }

    // Confirma que nova senha e confirmação coincidem antes de enviar
    document.getElementById('formSenha').addEventListener('submit', function(e) {
        const nova = document.getElementById('novaSenha').value;
        const confirmar = document.getElementById('confirmarSenha').value;
        if (nova !== confirmar) {
            e.preventDefault();
            document.getElementById('senhaAlertPlaceholder').innerHTML =
                '<div class="profile-alert profile-alert--danger">' +
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' +
                'A nova senha e a confirmação não coincidem.' +
                '</div>';
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
    });
</script>