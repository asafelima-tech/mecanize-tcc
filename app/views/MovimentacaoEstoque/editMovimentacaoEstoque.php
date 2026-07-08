<?php
/*
|--------------------------------------------------------------------------
| VIEW: editMovimentacaoEstoque
|--------------------------------------------------------------------------
| Variáveis esperadas:
|   $movimentacao — array com os dados da movimentação
|   $produtos     — array de produtos ativos
|   $error        — string de erro (opcional)
|--------------------------------------------------------------------------
*/
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Editar Movimentação</h1>
        <p class="page-header__subtitle">Atualize as informações da movimentação de estoque.</p>
    </div>

    <a href="./listMovimentacaoEstoque" class="btn btn--ghost">
        <i class="bi bi-arrow-left"></i>
        Voltar
    </a>
</div>

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

<form action="./editMovimentacaoEstoque" method="POST">

    <input type="hidden" name="id_movimentacao" value="<?= (int)$movimentacao['id_movimentacao'] ?>">

    <div class="cliente-edit-grid">

        <!-- 1. PRODUTO -->
        <div class="card">
            <div class="card__header">
                <div class="card__title">
                    <span class="ico-wrap ico" aria-hidden="true">
                        <i class="bi bi-box-seam"></i>
                    </span>
                    1. Produto
                </div>
            </div>

            <div class="card__body">
                <div class="form-grid">

                    <div class="form-field" style="grid-column: span 3;">
                        <label>Produto <span style="color:var(--color-danger)">*</span></label>
                        <select id="produto_id" name="produto_id" required onchange="atualizaBadge()">
                            <option value="">Selecione um produto...</option>
                            <?php foreach ($produtos as $p): ?>
                                <option
                                    value="<?= $p['id_produto'] ?>"
                                    data-estoque="<?= $p['estoque_atual'] ?>"
                                    <?= ($p['id_produto'] == $movimentacao['produto_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nome']) ?>
                                    (Estoque: <?= $p['estoque_atual'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field" style="grid-column: span 3;" id="estoque-badge-wrap">
                        <span id="estoque-badge" style="
                            display:inline-flex; align-items:center; gap:6px;
                            background: var(--color-primary-soft);
                            color: var(--color-primary);
                            border-radius: var(--radius-sm);
                            padding: 5px 12px;
                            font-size: 12px;
                            font-weight: 600;
                        ">
                            <i class="bi bi-layers"></i>
                            Estoque atual: <span id="estoque-valor">0</span> unidade(s)
                        </span>
                    </div>

                </div>
            </div>
        </div>

        <!-- 2. MOVIMENTAÇÃO -->
        <div class="card">
            <div class="card__header">
                <div class="card__title">
                    <span class="ico-wrap ico" aria-hidden="true">
                        <i class="bi bi-arrow-left-right"></i>
                    </span>
                    2. Movimentação
                </div>
            </div>

            <div class="card__body">
                <div class="form-grid">

                    <!-- Tipo -->
                    <div class="form-field" style="grid-column: span 2;">
                        <label>Tipo <span style="color:var(--color-danger)">*</span></label>
                        <div class="btn-group-toggle" style="display:flex; gap:0; margin-top:2px;">

                            <label class="toggle-option <?= $movimentacao['tipo'] === 'ENTRADA' ? 'is-active' : '' ?>"
                                style="flex:1; justify-content:center; border-radius:var(--radius-sm) 0 0 var(--radius-sm);">
                                <input
                                    type="radio"
                                    name="tipo"
                                    value="ENTRADA"
                                    <?= $movimentacao['tipo'] === 'ENTRADA' ? 'checked' : '' ?>
                                    required>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2.5"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="19" x2="12" y2="5" />
                                    <polyline points="5 12 12 5 19 12" />
                                </svg>
                                Entrada
                            </label>

                            <label class="toggle-option <?= $movimentacao['tipo'] === 'SAIDA' ? 'is-active' : '' ?>"
                                style="flex:1; justify-content:center; border-radius:0 var(--radius-sm) var(--radius-sm) 0; border-left:none;">
                                <input
                                    type="radio"
                                    name="tipo"
                                    value="SAIDA"
                                    <?= $movimentacao['tipo'] === 'SAIDA' ? 'checked' : '' ?>
                                    required>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2.5"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="5" x2="12" y2="19" />
                                    <polyline points="19 12 12 19 5 12" />
                                </svg>
                                Saída
                            </label>

                        </div>
                    </div>

                    <!-- Quantidade -->
                    <div class="form-field">
                        <label>Quantidade <span style="color:var(--color-danger)">*</span></label>
                        <input
                            type="number"
                            id="quantidade"
                            name="quantidade"
                            min="1"
                            value="<?= htmlspecialchars($movimentacao['quantidade']) ?>"
                            required>
                    </div>

                    <!-- Custo unitário (somente leitura) -->
                    <div class="form-field" style="grid-column: span 3;">
                        <label>Custo unitário registrado</label>
                        <input
                            type="text"
                            value="R$ <?= number_format((float)$movimentacao['custo_unitario'], 2, ',', '.') ?>"
                            disabled>
                        <span class="form-hint" style="font-size:12px; color:var(--color-text-muted); margin-top:4px; display:block;">
                            Atualizado automaticamente com o preço de custo atual do produto.
                        </span>
                    </div>

                </div>
            </div>
        </div>

        <!-- 3. RESUMO DO CADASTRO -->
        <div class="card">
            <div class="card__header">
                <div class="card__title">
                    <span class="ico-wrap ico" aria-hidden="true">
                        <i class="bi bi-info-circle"></i>
                    </span>
                    3. Resumo do Cadastro
                </div>
            </div>

            <div class="card__body">
                <div class="veiculo-resumo-grid">

                    <div class="veiculo-resumo-item">
                        <div class="veiculo-resumo-item__label">ID da Movimentação</div>
                        <div class="veiculo-resumo-item__value">#<?= (int)$movimentacao['id_movimentacao'] ?></div>
                    </div>

                    <div class="veiculo-resumo-item">
                        <div class="veiculo-resumo-item__label">Registrado em</div>
                        <div class="veiculo-resumo-item__value">
                            <?= htmlspecialchars($movimentacao['criado_em'] ?? date('d/m/Y')) ?>
                        </div>
                    </div>

                    <div class="veiculo-resumo-item">
                        <div class="veiculo-resumo-item__label">Última Atualização</div>
                        <div class="veiculo-resumo-item__value"><?= date('d/m/Y') ?></div>
                    </div>

                    <div class="veiculo-resumo-item">
                        <div class="veiculo-resumo-item__label">Usuário</div>
                        <div class="veiculo-resumo-item__value">
                            <?= htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário') ?>
                            (<?= htmlspecialchars($_SESSION['user_tipo'] ?? 'Administrador') ?>)
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div><!-- /.cliente-edit-grid -->

    <!-- FOOTER DE AÇÕES -->
    <div class="cliente-edit-footer">

        <a href="./deleteMovimentacaoEstoque&id=<?= (int)$movimentacao['id_movimentacao'] ?>"
            class="btn btn--danger-ghost"
            onclick="return confirm('Deseja excluir esta movimentação?')">
            <i class="bi bi-trash"></i>
            Excluir Movimentação
        </a>

        <div class="cliente-edit-footer__right">
            <a href="./listMovimentacaoEstoque" class="btn btn--ghost">
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

<script>
    const sel = document.getElementById('produto_id');
    const badge = document.getElementById('estoque-badge-wrap');
    const valor = document.getElementById('estoque-valor');

    function atualizaBadge() {
        const opt = sel.options[sel.selectedIndex];
        const estoque = opt?.dataset?.estoque;
        if (estoque !== undefined && opt.value) {
            valor.textContent = estoque;
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
    }

    atualizaBadge();

    document.querySelectorAll('.toggle-option input').forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('.toggle-option').forEach(l => l.classList.remove('is-active'));
            radio.closest('.toggle-option').classList.add('is-active');
        });
    });
</script>