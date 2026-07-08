<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!-- MODAL OVERLAY -->
<div class="modal-overlay" id="modalCriarUsuario">

    <div class="modal" style="max-width: 760px; width:100%;">

        <!-- HEADER -->
        <div class="modal__header">

            <div style="display:flex; align-items:center; gap:12px;">

                <div style="
                    width:40px;
                    height:40px;
                    border-radius: var(--radius-md);
                    background: var(--color-primary-soft);
                    color: var(--color-primary);
                    display:flex;
                    align-items:center;
                    justify-content:center;
                ">
                    <i class="bi bi-person-plus fs-4"></i>
                </div>

                <div>
                    <div class="modal__title">Novo Usuário</div>
                    <div class="modal__subtitle">
                        Cadastre um novo usuário no sistema.
                    </div>
                </div>

            </div>

            <a href="./listUsuarios" class="modal__close" title="Fechar">
                <i class="bi bi-x-lg"></i>
            </a>

        </div>

        <!-- FORM envolve BODY + FOOTER -->
        <form action="./saveUsuario" method="POST" id="formCriarUsuario">

            <!-- BODY -->
            <div class="modal__body">

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

                <!-- ① DADOS PRINCIPAIS -->
                <div class="form-section">

                    <div class="form-section__title">
                        <span class="ico-wrap ico" style="
                            width:22px;
                            height:22px;
                            background: var(--color-primary-soft);
                            color: var(--color-primary);
                            border-radius: var(--radius-sm);
                            display:inline-flex;
                            align-items:center;
                            justify-content:center;
                            font-size:11px;
                            font-weight:700;
                        ">
                            ①
                        </span>

                        Dados do Usuário
                    </div>

                    <div style="
                        display:grid;
                        grid-template-columns: repeat(2, 1fr);
                        gap:14px 18px;
                    ">

                        <!-- NOME -->
                        <div class="form-field">
                            <label>
                                Nome Completo
                                <span style="color:var(--color-danger)">*</span>
                            </label>

                            <input
                                type="text"
                                name="nome"
                                class="js-capitalize-words"
                                autocapitalize="words"
                                placeholder="Digite o nome completo"
                                required>
                        </div>

                        <!-- EMAIL -->
                        <div class="form-field">
                            <label>
                                Email
                                <span style="color:var(--color-danger)">*</span>
                            </label>

                            <input
                                type="email"
                                name="email"
                                placeholder="usuario@email.com"
                                required>
                        </div>

                        <!-- SENHA -->
                        <div class="form-field">
                            <label>
                                Senha
                                <span style="color:var(--color-danger)">*</span>
                            </label>

                            <div style="position:relative;">

                                <input
                                    type="password"
                                    name="senha"
                                    id="senha"
                                    placeholder="Senha de acesso"
                                    style="width:100%; padding-right:42px;"
                                    required>

                                <button
                                    type="button"
                                    id="togglePassword"
                                    style="
                                        position:absolute;
                                        right:10px;
                                        top:50%;
                                        transform:translateY(-50%);
                                        border:none;
                                        background:none;
                                        color: var(--color-text-muted);
                                        cursor:pointer;
                                    ">

                                    <i class="bi bi-eye" id="toggleIcon"></i>

                                </button>

                            </div>
                        </div>

                        <!-- TIPO -->
                        <div class="form-field">
                            <label>
                                Tipo de Usuário
                                <span style="color:var(--color-danger)">*</span>
                            </label>

                            <select name="tipo" required>
                                <option value="ADMIN">Administrador</option>
                                <option value="TECNICO">Técnico</option>
                                <option value="ATENDENTE" selected>Atendente</option>
                            </select>
                        </div>

                    </div>

                </div>

                <!-- ② STATUS -->
                <div class="form-section" style="margin-bottom:0;">

                    <div class="form-section__title">
                        <span class="ico-wrap ico" style="
                            width:22px;
                            height:22px;
                            background: var(--color-primary-soft);
                            color: var(--color-primary);
                            border-radius: var(--radius-sm);
                            display:inline-flex;
                            align-items:center;
                            justify-content:center;
                            font-size:11px;
                            font-weight:700;
                        ">
                            ②
                        </span>

                        Configurações
                    </div>

                    <div style="
                        display:grid;
                        grid-template-columns: 1fr;
                        gap:14px 18px;
                    ">

                        <div class="form-field">
                            <label>Status do Usuário</label>

                            <div class="radio-row">

                                <label>
                                    <input type="radio" name="ativo" value="1" checked>
                                    Ativo
                                </label>

                                <label>
                                    <input type="radio" name="ativo" value="0">
                                    Inativo
                                </label>

                            </div>

                        </div>

                    </div>

                </div>

            </div><!-- fecha modal__body -->

            <!-- FOOTER -->
            <div class="modal__footer">

                <div class="modal__footer-left">

                    <span style="margin-right:16px;">

                        <svg xmlns="http://www.w3.org/2000/svg"
                            width="13"
                            height="13"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                            style="vertical-align:-2px; margin-right:4px;">

                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                            <line x1="16" y1="2" x2="16" y2="6" />
                            <line x1="8" y1="2" x2="8" y2="6" />
                            <line x1="3" y1="10" x2="21" y2="10" />

                        </svg>

                        Criado em: <?= date('d/m/Y') ?>

                    </span>

                    <span>

                        <svg xmlns="http://www.w3.org/2000/svg"
                            width="13"
                            height="13"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                            style="vertical-align:-2px; margin-right:4px;">

                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" />

                            <circle cx="12" cy="7" r="4" />

                        </svg>

                        Usuário:
                        <?= htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário') ?>

                        (<?= htmlspecialchars($_SESSION['user_tipo'] ?? 'Administrador') ?>)

                    </span>

                </div>

                <!-- LIMPAR -->
                <button
                    type="button"
                    class="btn btn--danger btn--sm"
                    onclick="document.getElementById('formCriarUsuario').reset();">

                    <svg xmlns="http://www.w3.org/2000/svg"
                        width="14"
                        height="14"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2">

                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" />
                        <path d="M10 11v6M14 11v6" />
                        <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" />

                    </svg>

                    Limpar

                </button>

                <!-- CANCELAR -->
                <a href="./listUsuarios" class="btn btn--ghost btn--sm">

                    <svg xmlns="http://www.w3.org/2000/svg"
                        width="14"
                        height="14"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2">

                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />

                    </svg>

                    Cancelar

                </a>

                <!-- SALVAR -->
                <button
                    type="submit"
                    class="btn btn--primary btn--sm">

                    <svg xmlns="http://www.w3.org/2000/svg"
                        width="14"
                        height="14"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2">

                        <path stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M5 13l4 4L19 7" />

                    </svg>

                    Gravar

                </button>

            </div>

        </form>

    </div>

</div>

<script>
    // MOSTRAR / OCULTAR SENHA
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('senha');
    const toggleIcon = document.getElementById('toggleIcon');

    togglePassword.addEventListener('click', function() {

        const isPassword = passwordInput.type === 'password';

        passwordInput.type = isPassword ? 'text' : 'password';

        toggleIcon.classList.toggle('bi-eye');
        toggleIcon.classList.toggle('bi-eye-slash');

    });

    // FECHAR MODAL AO CLICAR FORA
    document.getElementById('modalCriarUsuario').addEventListener('click', function(e) {

        if (e.target === this) {
            window.location.href = './listUsuarios';
        }

    });
</script>