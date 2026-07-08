<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!-- MODAL OVERLAY -->
<div class="modal-overlay" id="modalCriarCategoria">

    <div class="modal" style="max-width: 750px;">

        <!-- HEADER -->
        <div class="modal__header">

            <div style="display:flex; align-items:center; gap:12px;">

                <div style="
                    width:40px;
                    height:40px;
                    background: var(--color-primary-soft);
                    color: var(--color-primary);
                    border-radius: var(--radius-md);
                    display:flex;
                    align-items:center;
                    justify-content:center;
                ">
                    <svg xmlns="http://www.w3.org/2000/svg"
                        width="20"
                        height="20"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2">
                        <path stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M7 7h.01M7 3h5.586a2 2 0 011.414.586l6.414 6.414a2 2 0 010 2.828l-7.172 7.172a2 2 0 01-2.828 0L3.586 13.172a2 2 0 010-2.828L10.414 3.586A2 2 0 0111.828 3H17" />
                    </svg>
                </div>

                <div>
                    <div class="modal__title">Nova Categoria</div>
                    <div class="modal__subtitle">
                        Cadastre uma nova categoria de produto.
                    </div>
                </div>

            </div>

            <a href="./listCategoria" class="modal__close" title="Fechar">
                <svg xmlns="http://www.w3.org/2000/svg"
                    width="18"
                    height="18"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2.5">
                    <path stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M6 18L18 6M6 6l12 12" />
                </svg>
            </a>

        </div>

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

           <form action="./saveCategoria" method="POST" id="formCriarCategoria">

                <!-- SEÇÃO -->
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
                            ①
                        </span>

                        Informações da Categoria
                    </div>

                    <div style="
                        display:grid;
                        grid-template-columns:1fr;
                        gap:16px;
                    ">

                        <div class="form-field">
                            <label>
                                Nome da Categoria
                                <span style="color:var(--color-danger)">*</span>
                            </label>

                            <input
                                type="text"
                                name="nome"
                                placeholder="Ex.: Óleos, Filtros, Pneus..."
                                required>
                        </div>

                    </div>

                </div>

            </form>

        </div>

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
                        <path stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" />
                        <circle cx="12" cy="7" r="4" />
                    </svg>

                    Usuário:
                    <?= htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário') ?>
                    (<?= htmlspecialchars($_SESSION['user_tipo'] ?? 'Administrador') ?>)
                </span>

            </div>

            <!-- BOTÕES -->
            <button
                type="button"
                class="btn btn--danger btn--sm"
                onclick="document.getElementById('formCriarCategoria').reset();">

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

            <a href="./listCategoria" class="btn btn--ghost btn--sm">

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

            <button
                type="submit"
                form="formCriarCategoria"
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
                        d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                </svg>

                Salvar
            </button>

        </div>

    </div>

</div>

<script>
    // Fecha modal clicando fora
    document.getElementById('modalCriarCategoria').addEventListener('click', function(e) {
        if (e.target === this) {
            window.location.href = './listCategoria';
        }
    });
</script>