<?php
/*
|--------------------------------------------------------------------------
| VIEW: viewProduto
|--------------------------------------------------------------------------
| Variáveis esperadas:
|
| $produto    — array com os dados do produto
| $categorias — array de categorias (para mostrar o nome da categoria)
|--------------------------------------------------------------------------
*/

$produto      = $produto ?? [];
$categoriaNome = '';

if (!empty($produto['categorias_produtos_id']) && !empty($categorias)) {
    foreach ($categorias as $cat) {
        if ((int)$cat['id_categoria'] === (int)$produto['categorias_produtos_id']) {
            $categoriaNome = $cat['nome'];
            break;
        }
    }
}

$precoCusto = (float)($produto['preco_custo'] ?? 0);
$precoVenda = (float)($produto['preco_venda'] ?? 0);
$margem     = $precoCusto > 0 ? (($precoVenda - $precoCusto) / $precoCusto) * 100 : 0;
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">
            Visualizar Produto
            <?php if (!empty($produto['codigo_interno'])): ?>
                <span style="color: var(--color-text-muted); font-weight: 500; font-size: 18px;">
                    · <?= htmlspecialchars($produto['codigo_interno']) ?>
                </span>
            <?php endif; ?>
        </h1>
        <p class="page-header__subtitle">Detalhes do produto cadastrado.</p>
    </div>

    <div class="page-header__actions">
        <?php
        $ativo     = $produto['ativo'] ?? 1;
        $pillLabel = $ativo ? 'Ativo' : 'Inativo';
        $pillColor = $ativo ? 'var(--color-success)' : 'var(--color-danger)';
        $pillBg    = $ativo ? 'var(--color-success-soft)' : 'var(--color-danger-soft)';
        ?>
        <span class="status-pill" style="background: <?= $pillBg ?>; color: <?= $pillColor ?>;">
            <span class="dot" style="background: <?= $pillColor ?>;"></span>
            <?= $pillLabel ?>
        </span>

        <a href="./listProduto" class="btn btn--ghost">
            <i class="bi bi-arrow-left"></i>
            Voltar
        </a>

        <a href="./editProduto?id=<?= (int)($produto['id_produto'] ?? 0) ?>" class="btn btn--primary">
            <i class="bi bi-pencil"></i>
            Editar
        </a>
    </div>
</div>

<div class="prod-edit-grid">

    <!-- 1. IDENTIFICAÇÃO -->
    <div class="card">
        <div class="card__header">
            <div class="card__title">
                <span class="ico-wrap ico" aria-hidden="true">
                    <i class="bi bi-upc-scan"></i>
                </span>
                1. Identificação
            </div>
        </div>

        <div class="card__body">
            <div class="form-grid">

                <div class="form-field">
                    <label>Código Interno</label>
                    <input type="text" value="<?= htmlspecialchars($produto['codigo_interno'] ?? '-') ?>" readonly>
                </div>

                <div class="form-field">
                    <label>Código de Barras</label>
                    <input type="text" value="<?= htmlspecialchars($produto['codigo_barras'] ?? '-') ?>" readonly>
                </div>

                <div class="form-field" style="grid-column: 1 / -1;">
                    <label>Nome do Produto</label>
                    <input type="text" value="<?= htmlspecialchars($produto['nome'] ?? '-') ?>" readonly>
                </div>

                <div class="form-field">
                    <label>Categoria</label>
                    <input type="text" value="<?= htmlspecialchars($categoriaNome ?: 'Sem categoria') ?>" readonly>
                </div>

                <div class="form-field">
                    <label>Marca</label>
                    <input type="text" value="<?= htmlspecialchars($produto['marca'] ?? '-') ?>" readonly>
                </div>

            </div>
        </div>
    </div>

    <!-- 2. ESPECIFICAÇÕES -->
    <div class="card">
        <div class="card__header">
            <div class="card__title">
                <span class="ico-wrap ico" aria-hidden="true">
                    <i class="bi bi-tags"></i>
                </span>
                2. Especificações
            </div>
        </div>

        <div class="card__body">
            <div class="form-grid">

                <div class="form-field">
                    <label>Volume / Embalagem</label>
                    <input type="text" value="<?= htmlspecialchars($produto['volume_embalagem'] ?? '-') ?>" readonly>
                </div>

                <div class="form-field" style="grid-column: 1 / -1;">
                    <label>Aplicação</label>
                    <input type="text" value="<?= htmlspecialchars($produto['aplicacao'] ?? '-') ?>" readonly>
                </div>

                <div class="form-field" style="grid-column: 1 / -1;">
                    <label>Localização no Estoque</label>
                    <input type="text" value="<?= htmlspecialchars($produto['localizacao'] ?? '-') ?>" readonly>
                </div>

            </div>
        </div>
    </div>

    <!-- 3. DESCRIÇÃO -->
    <div class="card">
        <div class="card__header">
            <div class="card__title">
                <span class="ico-wrap ico" aria-hidden="true">
                    <i class="bi bi-card-text"></i>
                </span>
                3. Descrição
            </div>
        </div>

        <div class="card__body">
            <div class="form-field">
                <label>Descrição</label>
                <textarea rows="5" readonly><?= htmlspecialchars($produto['descricao'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- 4. ESTOQUE E FINANCEIRO -->
    <div class="card">
        <div class="card__header">
            <div class="card__title">
                <span class="ico-wrap ico" aria-hidden="true">
                    <i class="bi bi-boxes"></i>
                </span>
                4. Estoque e Financeiro
            </div>
        </div>

        <div class="card__body">
            <div class="form-grid">

                <div class="form-field">
                    <label>Estoque Atual</label>
                    <input type="text" value="<?= number_format((float)($produto['estoque_atual'] ?? 0), 0, ',', '.') ?>" readonly>
                </div>

                <div class="form-field">
                    <label>Estoque Mínimo</label>
                    <input type="text" value="<?= number_format((float)($produto['estoque_minimo'] ?? 0), 0, ',', '.') ?>" readonly>
                </div>

                <?php if (($_SESSION['user_tipo'] ?? '') === 'ADMIN'): ?>
                <div class="form-field">
                    <label>Preço de Custo</label>
                    <div class="os-money-input">
                        <span class="os-money-input__prefix">R$</span>
                        <input type="text" value="<?= number_format($precoCusto, 2, ',', '.') ?>" readonly>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-field">
                    <label>Preço de Venda</label>
                    <div class="os-money-input">
                        <span class="os-money-input__prefix">R$</span>
                        <input type="text" value="<?= number_format($precoVenda, 2, ',', '.') ?>" readonly>
                    </div>
                </div>

                <?php if (($_SESSION['user_tipo'] ?? '') === 'ADMIN'): ?>
                <div class="form-field" style="grid-column: 1 / -1;">
                    <label>Margem de Lucro</label>
                    <div class="os-money-input os-money-input--total">
                        <span class="os-money-input__prefix" style="font-size:13px;">%</span>
                        <input type="text" value="<?= number_format($margem, 2, ',', '.') ?>" readonly>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

</div>
