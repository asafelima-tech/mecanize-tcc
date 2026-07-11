<?php
/*
|--------------------------------------------------------------------------
| VIEW: editCategoria
|--------------------------------------------------------------------------
| Variáveis esperadas:
| $categoria
|--------------------------------------------------------------------------
*/
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">
            Editar Categoria
            <span style="color: var(--color-text-muted); font-weight: 500; font-size: 18px;">
                · #<?= $categoria['id_categoria'] ?>
            </span>
        </h1>
        <p class="page-header__subtitle">Atualize as informações da categoria de produto.</p>
    </div>

    <div class="page-header__actions">
        <span class="status-pill" style="background: var(--color-success-soft); color: var(--color-success);">
            <span class="dot" style="background: var(--color-success);"></span>
            Ativa
        </span>

        <a href="./listCategoria" class="btn btn--ghost">
            <i class="bi bi-arrow-left"></i>
            Voltar
        </a>
    </div>
</div>

<form action="./editCategoria" method="POST" id="formEditCategoria">

    <input type="hidden" name="id_categoria" value="<?= $categoria['id_categoria'] ?>">

    <div class="cat-edit-grid">

        <!-- 1. INFORMAÇÕES DA CATEGORIA -->
        <div class="card">
            <div class="card__header">
                <div class="card__title">
                    <span class="ico-wrap ico" aria-hidden="true"><i class="bi bi-tags"></i></span>
                    1. Informações da Categoria
                </div>
            </div>
            <div class="card__body">
                <div class="form-grid">

                    <div class="form-field" style="grid-column: 1 / -1;">
                        <label>Nome da Categoria</label>
                        <input
                            type="text"
                            name="nome"
                            value="<?= htmlspecialchars($categoria['nome']) ?>"
                            placeholder="Ex.: Óleos, Pneus, Filtros..."
                            required>
                    </div>

                    <div class="form-field">
                        <label>Identificação</label>
                        <div style="
                            display: flex;
                            align-items: center;
                            gap: 8px;
                            background: var(--color-bg-alt);
                            border: 1px solid var(--color-border);
                            border-radius: var(--radius-md);
                            padding: 10px 14px;
                            font-size: 13px;
                            color: var(--color-text-muted);
                        ">
                            <i class="bi bi-hash"></i>
                            Categoria #<?= $categoria['id_categoria'] ?>
                        </div>
                    </div>

                    <div class="form-field">
                        <label>Status</label>
                        <div style="
                            display: flex;
                            align-items: center;
                            gap: 8px;
                            background: var(--color-bg-alt);
                            border: 1px solid var(--color-border);
                            border-radius: var(--radius-md);
                            padding: 10px 14px;
                        ">
                            <span class="badge badge--success">Ativa</span>
                            <span style="font-size: 13px; color: var(--color-text-muted);">
                                Disponível para uso nos produtos
                            </span>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- 2. INFORMAÇÕES DO SISTEMA -->
        <div class="card">
            <div class="card__header">
                <div class="card__title">
                    <span class="ico-wrap ico" aria-hidden="true"><i class="bi bi-info-circle"></i></span>
                    2. Informações do Sistema
                </div>
            </div>
            <div class="card__body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px;">

                    <div style="
                        border: 1px solid var(--color-border);
                        border-radius: var(--radius-md);
                        padding: 16px;
                        background: var(--color-bg-alt);
                    ">
                        <div style="
                            font-size: 11px;
                            color: var(--color-text-muted);
                            margin-bottom: 8px;
                            text-transform: uppercase;
                            letter-spacing: .04em;
                            font-weight: 600;
                        ">Criado em</div>
                        <div style="font-size: 15px; font-weight: 600; color: var(--color-text);">
                            <?= date('d/m/Y') ?>
                        </div>
                    </div>

                    <div style="
                        border: 1px solid var(--color-border);
                        border-radius: var(--radius-md);
                        padding: 16px;
                        background: var(--color-bg-alt);
                    ">
                        <div style="
                            font-size: 11px;
                            color: var(--color-text-muted);
                            margin-bottom: 8px;
                            text-transform: uppercase;
                            letter-spacing: .04em;
                            font-weight: 600;
                        ">Última atualização</div>
                        <div style="font-size: 15px; font-weight: 600; color: var(--color-text);">
                            <?= date('d/m/Y') ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <!-- FOOTER DE AÇÕES -->
    <div class="cat-edit-footer">

        <?php if (($_SESSION['user_tipo'] ?? '') === 'ADMIN'): ?>
        <form method="post" action="./deleteCategoria" style="display:inline;"
            onsubmit="return confirm('Deseja realmente excluir esta categoria?')">
            <input type="hidden" name="id" value="<?= $categoria['id_categoria'] ?>">
            <button type="submit" class="btn btn--danger-ghost">
                <i class="bi bi-trash"></i>
                Excluir Categoria
            </button>
        </form>
        <?php endif; ?>

        <div class="cat-edit-footer__right">
            <a href="./listCategoria" class="btn btn--ghost">
                <i class="bi bi-x-lg"></i>
                Cancelar
            </a>
            <button type="submit" class="btn btn--primary">
                <i class="bi bi-floppy"></i>
                Salvar Alterações
            </button>
        </div>

    </div>

</form>