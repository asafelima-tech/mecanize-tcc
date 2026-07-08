<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Variáveis esperadas:
// $produtos  array  — lista de produtos ativos
// $error     string — mensagem de erro (opcional)
// $old       array  — dados do POST anterior (opcional)

// Mock para visualização
if (!isset($produtos)) {
    $produtos = [
        ['id_produto' => 1, 'nome' => 'Produto Exemplo A', 'estoque_atual' => 42],
        ['id_produto' => 2, 'nome' => 'Produto Exemplo B', 'estoque_atual' => 7],
    ];
}
?>

<!-- MODAL OVERLAY -->
<div class="modal-overlay" id="modalNovaMovimentacao">
    <div class="modal modal--md">

        <!-- HEADER -->
        <div class="modal__header">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="
                    width:40px; height:40px;
                    background: var(--color-primary-soft);
                    color: var(--color-primary);
                    border-radius: var(--radius-md);
                    display:inline-flex; align-items:center; justify-content:center;
                ">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 7h18M3 12h18M3 17h18" />
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17 3l4 4-4 4M7 21l-4-4 4-4" />
                    </svg>
                </div>
                <div>
                    <div class="modal__title">Nova Movimentação</div>
                    <div class="modal__subtitle">Registre uma entrada ou saída de estoque.</div>
                </div>
            </div>

            <a href="./listMovimentacaoEstoque" class="modal__close" title="Fechar">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
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

            <form action="./saveMovimentacaoEstoque" method="POST" id="formNovaMovimentacao">

                <!-- ① PRODUTO -->
                <div class="form-section">
                    <div class="form-section__title">
                        <span class="ico-wrap ico" style="
                            width:22px; height:22px;
                            background: var(--color-primary-soft);
                            color: var(--color-primary);
                            border-radius: var(--radius-sm);
                            display:inline-flex; align-items:center; justify-content:center;
                            font-size:11px; font-weight:700;
                        ">①</span>
                        Produto
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr; gap:14px 18px;">

                        <div class="form-field">
                            <label>Produto <span style="color:var(--color-danger)">*</span></label>
                            <select
                                id="produto_id"
                                name="produto_id"
                                required>
                                <option value="">Selecione um produto...</option>
                                <?php foreach ($produtos as $p): ?>
                                    <option
                                        value="<?= $p['id_produto'] ?>"
                                        data-estoque="<?= $p['estoque_atual'] ?>"
                                        <?= (($old['produto_id'] ?? '') == $p['id_produto']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['nome']) ?>
                                        (Estoque: <?= $p['estoque_atual'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Estoque hint -->
                        <div id="estoque-badge-wrap" style="display:none; margin-top:-8px;">
                            <span id="estoque-badge" style="
                                display:inline-flex; align-items:center; gap:6px;
                                background: var(--color-primary-soft);
                                color: var(--color-primary);
                                border-radius: var(--radius-sm);
                                padding: 5px 12px;
                                font-size: 12px;
                                font-weight: 600;
                            ">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M20 7H4a2 2 0 00-2 2v6a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2z" />
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16 3H8v4h8V3z" />
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M8 17v4M16 17v4" />
                                </svg>
                                Estoque atual: <span id="estoque-valor">0</span> unidade(s)
                            </span>
                        </div>

                    </div>
                </div>

                <!-- ② MOVIMENTAÇÃO -->
                <div class="form-section">
                    <div class="form-section__title">
                        <span class="ico-wrap ico" style="
                            width:22px; height:22px;
                            background: var(--color-primary-soft);
                            color: var(--color-primary);
                            border-radius: var(--radius-sm);
                            display:inline-flex; align-items:center; justify-content:center;
                            font-size:11px; font-weight:700;
                        ">②</span>
                        Movimentação
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px 18px;">

                        <!-- Tipo -->
                        <div class="form-field">
                            <label>Tipo <span style="color:var(--color-danger)">*</span></label>
                            <div class="btn-group-toggle" style="display:flex; gap:0; margin-top:2px;">

                                <label class="toggle-option <?= (($old['tipo'] ?? 'ENTRADA') === 'ENTRADA') ? 'is-active' : '' ?>"
                                    style="flex:1; justify-content:center; border-radius:var(--radius-sm) 0 0 var(--radius-sm);">
                                    <input
                                        type="radio"
                                        name="tipo"
                                        value="ENTRADA"
                                        <?= (($old['tipo'] ?? 'ENTRADA') === 'ENTRADA') ? 'checked' : '' ?>
                                        required>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2.5"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="19" x2="12" y2="5" />
                                        <polyline points="5 12 12 5 19 12" />
                                    </svg>
                                    Entrada
                                </label>

                                <label class="toggle-option <?= (($old['tipo'] ?? '') === 'SAIDA') ? 'is-active' : '' ?>"
                                    style="flex:1; justify-content:center; border-radius:0 var(--radius-sm) var(--radius-sm) 0; border-left:none;">
                                    <input
                                        type="radio"
                                        name="tipo"
                                        value="SAIDA"
                                        <?= (($old['tipo'] ?? '') === 'SAIDA') ? 'checked' : '' ?>
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
                                value="<?= htmlspecialchars($old['quantidade'] ?? '') ?>"
                                placeholder="0"
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
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        style="vertical-align:-2px; margin-right:4px;">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                        <line x1="16" y1="2" x2="16" y2="6" />
                        <line x1="8" y1="2" x2="8" y2="6" />
                        <line x1="3" y1="10" x2="21" y2="10" />
                    </svg>
                    Registrado em: <?= date('d/m/Y') ?>
                </span>
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        style="vertical-align:-2px; margin-right:4px;">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" />
                        <circle cx="12" cy="7" r="4" />
                    </svg>
                    Usuário: <?= htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário') ?>
                    (<?= htmlspecialchars($_SESSION['user_tipo'] ?? 'Administrador') ?>)
                </span>
            </div>

            <button type="button" class="btn btn--danger btn--sm"
                onclick="document.getElementById('formNovaMovimentacao').reset(); atualizaBadge();">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6" />
                    <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" />
                    <path d="M10 11v6M14 11v6" />
                    <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" />
                </svg>
                Limpar
            </button>

            <a href="./listMovimentacaoEstoque" class="btn btn--ghost btn--sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
                Cancelar
            </a>

            <button type="submit" form="formNovaMovimentacao" class="btn btn--primary btn--sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                </svg>
                Registrar
            </button>
        </div>

    </div>
</div>

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

    sel.addEventListener('change', atualizaBadge);
    atualizaBadge();

    // Impede registrar SAÍDA maior que o estoque atual (validação também no servidor)
    function estoqueSelecionado() {
        const opt = sel.options[sel.selectedIndex];
        const e = opt?.dataset?.estoque;
        return (e !== undefined && opt.value) ? parseInt(e, 10) : null;
    }

    document.getElementById('formNovaMovimentacao').addEventListener('submit', function(e) {
        const tipo = document.querySelector('input[name="tipo"]:checked')?.value;
        const qtd = parseInt(document.getElementById('quantidade').value, 10) || 0;
        const estoque = estoqueSelecionado();

        if (tipo === 'SAIDA' && estoque !== null && qtd > estoque) {
            e.preventDefault();
            alert('Não é possível registrar uma saída de ' + qtd +
                ' unidade(s): o estoque atual é de apenas ' + estoque + '.');
        }
    });

    // Highlight visual nos botões de toggle
    document.querySelectorAll('.toggle-option input').forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('.toggle-option').forEach(l => l.classList.remove('is-active'));
            radio.closest('.toggle-option').classList.add('is-active');
        });
    });

    // Fechar modal clicando fora
    document.getElementById('modalNovaMovimentacao').addEventListener('click', function(e) {
        if (e.target === this) window.location.href = './listMovimentacaoEstoque';
    });
</script>