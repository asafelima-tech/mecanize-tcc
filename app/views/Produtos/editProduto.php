<?php
/*
|--------------------------------------------------------------------------
| VIEW: editProduto
|--------------------------------------------------------------------------
| Variáveis esperadas:
|
| $produto              — array com os dados do produto
| $categorias_produtos  — array de categorias
|--------------------------------------------------------------------------
*/
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">
            Editar Produto
            <?php if (!empty($produto['codigo_interno'])): ?>
                <span style="color: var(--color-text-muted); font-weight: 500; font-size: 18px;">
                    · <?= htmlspecialchars($produto['codigo_interno']) ?>
                </span>
            <?php endif; ?>
        </h1>
        <p class="page-header__subtitle">Atualize as informações do produto.</p>
    </div>

    <div class="page-header__actions">
        <?php
        $ativo = $produto['ativo'] ?? 1;
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
    </div>
</div>

<form action="./editProduto" method="POST">

    <input type="hidden" name="id_produto" value="<?= $produto['id_produto'] ?>">

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
                        <input
                            type="text"
                            name="codigo_interno"
                            value="<?= htmlspecialchars($produto['codigo_interno'] ?? '') ?>"
                            placeholder="Ex: PROD-0001"
                            readonly>
                    </div>

                    <div class="form-field">
                        <label>Código de Barras</label>
                        <input
                            type="text"
                            name="codigo_barras"
                            value="<?= htmlspecialchars($produto['codigo_barras'] ?? '') ?>"
                            placeholder="Ex: 7891234567890">
                    </div>

                    <div class="form-field" style="grid-column: 1 / -1;">
                        <label>Nome do Produto <span class="required">*</span></label>
                        <input
                            type="text"
                            name="nome"
                            value="<?= htmlspecialchars($produto['nome'] ?? '') ?>"
                            placeholder="Ex: Óleo Motor 5W30 Sintético"
                            required>
                    </div>

                  <div class="form-field">
    <label>Categoria</label>
    <select name="categorias_produtos_id" required>
        <option value="" disabled <?= empty($produto['categorias_produtos_id']) ? 'selected' : '' ?>>
            Selecione uma categoria
        </option>

        <?php foreach ($categorias as $cat): ?>
            <option
                value="<?= htmlspecialchars($cat['id_categoria']) ?>"
                <?= $cat['id_categoria'] == ($produto['categorias_produtos_id'] ?? null) ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['nome']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

                    <div class="form-field">
                        <label>Marca</label>
                        <input
                            type="text"
                            name="marca"
                            value="<?= htmlspecialchars($produto['marca'] ?? '') ?>"
                            placeholder="Ex: Castrol, Mobil, Shell...">
                    </div>

                </div>
            </div>
        </div>

        <!-- 2.ESPECIFICAÇÕES -->
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
                        <input
                            type="text"
                            name="volume_embalagem"
                            value="<?= htmlspecialchars($produto['volume_embalagem'] ?? '') ?>"
                            placeholder="Ex: 1L, 4L, 200ml">
                    </div>

                    <div class="form-field" style="grid-column: 1 / -1;">
                        <label>Aplicação</label>
                        <input
                            type="text"
                            name="aplicacao"
                            value="<?= htmlspecialchars($produto['aplicacao'] ?? '') ?>"
                            placeholder="Ex: Motores a gasolina e flex, carros de passeio">
                    </div>

                    <div class="form-field" style="grid-column: 1 / -1;">
                        <label>Localização no Estoque</label>
                        <input
                            type="text"
                            name="localizacao"
                            value="<?= htmlspecialchars($produto['localizacao'] ?? '') ?>"
                            placeholder="Ex: Prateleira A2, Corredor 3"
                            required>
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
                    <textarea
                        name="descricao"
                        rows="5"
                        placeholder="Descreva o produto, características técnicas, especificações..."><?= htmlspecialchars($produto['descricao'] ?? '') ?></textarea>
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
                        <input
                            type="number"
                            step="0.001"
                            min="0"
                            name="estoque_atual"
                            value="<?= htmlspecialchars($produto['estoque_atual'] ?? '0') ?>"
                            placeholder="0">
                    </div>

                    <div class="form-field">
                        <label>Estoque Mínimo</label>
                        <input
                            type="number"
                            step="0.001"
                            min="0"
                            name="estoque_minimo"
                            value="<?= htmlspecialchars($produto['estoque_minimo'] ?? '0') ?>"
                            placeholder="0">
                    </div>

                    <div class="form-field">
                        <label>Preço de Custo</label>
                        <div class="os-money-input">
                            <span class="os-money-input__prefix">R$</span>
                          <input
    type="text"
    inputmode="numeric"
    id="preco_custo"
    name="preco_custo"
    value="<?= htmlspecialchars(number_format((float)($produto['preco_custo'] ?? 0), 2, '.', '')) ?>"
    placeholder="0.00">
                        </div>
                    </div>

                    <div class="form-field">
                        <label>Preço de Venda</label>
                        <div class="os-money-input">
                            <span class="os-money-input__prefix">R$</span>
                          <input
    type="text"
    inputmode="numeric"
    id="preco_venda"
    name="preco_venda"
    value="<?= htmlspecialchars(number_format((float)($produto['preco_venda'] ?? 0), 2, '.', '')) ?>"
    placeholder="0.00">
                        </div>
                    </div>

                    <div class="form-field" style="grid-column: 1 / -1;">
                        <label>Margem de Lucro</label>
                        <div class="os-money-input os-money-input--total">
                            <span class="os-money-input__prefix" style="font-size:13px;">%</span>
                           <input
    type="text"
    inputmode="numeric"
    id="margem_lucro"
    name="margem_lucro"
    value="0"
    placeholder="Ex.: 30">
                        </div>
                    </div>

                </div>

                <!-- Status ativo -->
                <div class="prod-ativo-wrap">
                    <label class="prod-toggle">
                     <input type="hidden" name="ativo" value="0">

<input
    type="checkbox"
    name="ativo"
    value="1"
    <?= !empty($produto['ativo']) ? 'checked' : '' ?>>
                        <span class="prod-toggle__track"></span>
                        <span class="prod-toggle__label">Produto ativo</span>
                    </label>
                </div>

            </div>
        </div>

    </div><!-- /.prod-edit-grid -->

    <!-- FOOTER DE AÇÕES -->
    <div class="os-edit-footer">

        <button type="submit"
            formaction="./deleteProduto"
            formmethod="post"
            formnovalidate
            class="btn btn--danger-ghost"
            onclick="return confirm('Deseja excluir este produto? Esta ação não pode ser desfeita.')">
            <i class="bi bi-trash"></i>
            Excluir Produto
        </button>

        <div class="os-edit-footer__right">
            <a href="./listProduto" class="btn btn--ghost">
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
    const inputCusto = document.getElementById('preco_custo');
    const inputVenda = document.getElementById('preco_venda');
    const inputMargem = document.getElementById('margem_lucro');

    // Máscara de dinheiro (IMask): exibe 1.234,56 e envia o valor cru no submit
    const moneyOpts = {
        mask: Number,
        scale: 2,
        thousandsSeparator: '.',
        radix: ',',
        mapToRadix: ['.'],
        padFractionalZeros: true,
        normalizeZeros: true,
        min: 0
    };

    const mCusto = IMask(inputCusto, moneyOpts);
    const mVenda = IMask(inputVenda, moneyOpts);

    // Valores iniciais chegam com ponto decimal (ex.: "1234.56")
    mCusto.typedValue = parseFloat(String(inputCusto.value).replace(',', '.')) || 0;
    mVenda.typedValue = parseFloat(String(inputVenda.value).replace(',', '.')) || 0;

    let ultimoCampoEditado = null;
    let travaVenda = false;

    const somenteNumeros = valor => String(valor || '').replace(/\D/g, '');

    function calcularMargem() {
        const custo = mCusto.typedValue || 0;
        const venda = mVenda.typedValue || 0;
        inputMargem.value = custo > 0 ? Math.round(((venda - custo) / custo) * 100) : 0;
    }

    function calcularVendaPelaMargem() {
        const custo = mCusto.typedValue || 0;
        const margem = parseInt(somenteNumeros(inputMargem.value), 10) || 0;
        const venda = custo > 0 ? +(custo + (custo * margem / 100)).toFixed(2) : 0;
        travaVenda = true;          // evita que o 'accept' do venda recalcule a margem
        mVenda.typedValue = venda;
        travaVenda = false;
    }

    mCusto.on('accept', function () {
        if (ultimoCampoEditado === 'margem') calcularVendaPelaMargem();
        else calcularMargem();
    });

    mVenda.on('accept', function () {
        if (travaVenda) return;
        ultimoCampoEditado = 'venda';
        calcularMargem();
    });

    inputMargem.addEventListener('input', function () {
        ultimoCampoEditado = 'margem';
        this.value = somenteNumeros(this.value);
        calcularVendaPelaMargem();
    });

    inputMargem.addEventListener('blur', function () {
        if (this.value.trim() === '') {
            this.value = '0';
            calcularVendaPelaMargem();
        }
    });

    [inputCusto, inputVenda, inputMargem].forEach(input => {
        input.addEventListener('focus', function () { setTimeout(() => this.select(), 0); });
        input.addEventListener('click', function () { setTimeout(() => this.select(), 0); });
    });

    calcularMargem();

    // Desmascara no envio: manda o valor cru (1234.56) pro back-end, sem alterá-lo
    if (inputCusto.form) {
        inputCusto.form.addEventListener('submit', function () {
            inputCusto.value = String(mCusto.typedValue || 0);
            inputVenda.value = String(mVenda.typedValue || 0);
        });
    }
</script>