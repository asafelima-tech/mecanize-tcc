<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!-- MODAL OVERLAY -->
<div class="modal-overlay" id="modalCriarProduto">
    <div class="modal modal--xl">

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
                    <i class="bi bi-box fs-3"></i>
                </div>
                <div>
                    <div class="modal__title">Novo Produto</div>
                    <div class="modal__subtitle">Preencha os dados abaixo para cadastrar um novo produto.</div>
                </div>
            </div>

            <a href="./listProduto" class="modal__close" title="Fechar">
                <i class="bi bi-x-lg"></i>
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

            <form action="./saveProduto" method="POST" id="formCriarProduto">

                <!-- ① IDENTIFICAÇÃO -->
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
                        Identificação
                    </div>

                    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:14px 18px;">

                        <div class="form-field">
                            <label>Código Interno</label>
                            <input
                                type="text"
                                name="codigo_interno"
                                value="<?= htmlspecialchars($proximoCodigoInterno ?? '') ?>"
                                readonly
                                style="background:var(--color-bg-alt); color:var(--color-text-secondary); cursor:not-allowed;">
                        </div>

                        <div class="form-field">
                            <label>Categoria <span style="color:var(--color-danger)">*</span></label>
                            <select name="categorias_produtos_id" required>
                                <option value="">Selecione a categoria</option>

                                <?php if (!empty($categorias)): ?>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <option
                                            value="<?= htmlspecialchars($categoria['id_categoria']) ?>"
                                            <?= (($old['categorias_produtos_id'] ?? '') == $categoria['id_categoria']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($categoria['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label>Código de Barras</label>
                            <input
                                type="text"
                                name="codigo_barras"
                                value="<?= htmlspecialchars($old['codigo_barras'] ?? '') ?>"
                                placeholder="Ex.: 7891234567890">
                        </div>

                        <div class="form-field">
                            <label>Nome do Produto <span style="color:var(--color-danger)">*</span></label>
                            <input
                                type="text"
                                name="nome"
                                value="<?= htmlspecialchars($old['nome'] ?? '') ?>"
                                placeholder="Nome do produto"
                                required>
                        </div>

                        <div class="form-field">
                            <label>Marca</label>
                            <input
                                type="text"
                                name="marca"
                                value="<?= htmlspecialchars($old['marca'] ?? '') ?>"
                                placeholder="Marca do produto">
                        </div>

                    </div>
                </div>

                <!-- ② DETALHES TÉCNICOS -->
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
                        Detalhes Técnicos
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:14px 18px;">
                        <div class="form-field">
                            <label>Descrição</label>
                            <textarea
                                name="descricao"
                                placeholder="Descrição detalhada do produto..."
                                rows="4"><?= htmlspecialchars($old['descricao'] ?? '') ?></textarea>
                        </div>

                        <div class="form-field">
                            <label>Aplicação</label>
                            <textarea
                                name="aplicacao"
                                placeholder="Onde e como o produto é utilizado..."
                                rows="4"><?= htmlspecialchars($old['aplicacao'] ?? '') ?></textarea>
                        </div>

                        <div class="form-field">
                            <label>Volume / Embalagem</label>
                            <input
                                type="text"
                                name="volume_embalagem"
                                value="<?= htmlspecialchars($old['volume_embalagem'] ?? '') ?>"
                                placeholder="Ex.: 500ml, 1L, 20kg">
                        </div>
                    </div>
                </div>

                <!-- ③ GESTÃO DE ESTOQUE -->
                <div class="form-section">
                    <div class="form-section__title">
                        <span class="ico-wrap ico" style="
                            width:22px; height:22px;
                            background: var(--color-primary-soft);
                            color: var(--color-primary);
                            border-radius: var(--radius-sm);
                            display:inline-flex; align-items:center; justify-content:center;
                            font-size:11px; font-weight:700;
                        ">③</span>
                        Gestão de Estoque
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr 1.5fr 1fr 1fr; gap:14px 18px; align-items:start;">
                        <div class="form-field">
                            <label>Estoque Atual</label>
                            <input
                                type="number"
                                name="estoque_atual"
                                value="<?= htmlspecialchars($old['estoque_atual'] ?? '0') ?>">
                        </div>

                        <div class="form-field">
                            <label>Estoque Mínimo</label>
                            <input
                                type="number"
                                name="estoque_minimo"
                                value="<?= htmlspecialchars($old['estoque_minimo'] ?? '0') ?>">
                        </div>

                        <div class="form-field">
                            <label>Localização no Estoque <span style="color:var(--color-danger)">*</span></label>
                            <div style="position:relative;">
                                <input
                                    type="text"
                                    name="localizacao"
                                    value="<?= htmlspecialchars($old['localizacao'] ?? '') ?>"
                                    placeholder="Ex.: Prateleira A1"
                                    style="width:100%; padding-right:36px;"
                                    required>
                                <span style="
                                    position:absolute; right:10px; top:50%; transform:translateY(-50%);
                                    color: var(--color-text-muted);
                                ">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </span>
                            </div>
                        </div>

                        <div class="form-field">
                            <label>Ativo</label>
                            <div class="radio-row">
                                <label>
                                    <input
                                        type="radio"
                                        name="ativo"
                                        value="1"
                                        <?= (($old['ativo'] ?? '1') == '1') ? 'checked' : '' ?>>
                                    Sim
                                </label>
                                <label>
                                    <input
                                        type="radio"
                                        name="ativo"
                                        value="0"
                                        <?= (($old['ativo'] ?? '1') == '0') ? 'checked' : '' ?>>
                                    Não
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ④ FINANCEIRO -->
                <div class="form-section" style="margin-bottom:0;">
                    <div class="form-section__title">
                        <span class="ico-wrap ico" style="
                            width:22px; height:22px;
                            background: var(--color-primary-soft);
                            color: var(--color-primary);
                            border-radius: var(--radius-sm);
                            display:inline-flex; align-items:center; justify-content:center;
                            font-size:11px; font-weight:700;
                        ">④</span>
                        Financeiro
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:14px 18px;">
                        <div class="form-field">
                            <label>Preço de Custo (R$)</label>
                            <input
                                type="text"
                                inputmode="numeric"
                                name="preco_custo"
                                id="preco_custo"
                                value="<?= htmlspecialchars($old['preco_custo'] ?? '0.00') ?>">
                        </div>

                        <div class="form-field">
                            <label>Preço de Venda (R$)</label>
                            <input
                                type="text"
                                inputmode="numeric"
                                name="preco_venda"
                                id="preco_venda"
                                value="<?= htmlspecialchars($old['preco_venda'] ?? '0.00') ?>">
                        </div>

                        <div class="form-field">
                            <label>Margem de Lucro (%)</label>
                            <div style="position:relative;">
                                <input
                                    type="text"
                                    inputmode="numeric"
                                    id="margem_lucro"
                                    value="0"
                                    placeholder="Ex.: 30"
                                    style="width:100%; padding-right:32px;">
                                <span style="
                                    position:absolute; right:12px; top:50%; transform:translateY(-50%);
                                    font-size:13px; font-weight:600; color:var(--color-text-muted);
                                ">%</span>
                            </div>
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
                    Criado em: <?= date('d/m/Y') ?>
                </span>

                <span style="margin-right:16px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        style="vertical-align:-2px; margin-right:4px;">
                        <circle cx="12" cy="12" r="10" />
                        <polyline points="12 6 12 12 16 14" />
                    </svg>
                    Atualizado em: <?= date('d/m/Y') ?>
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

            <button type="button" class="btn btn--ghost btn--sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9" />
                    <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2" />
                    <rect x="6" y="14" width="12" height="8" />
                </svg>
                Imprimir Etiqueta
            </button>

            <button
                type="button"
                class="btn btn--danger btn--sm"
                onclick="document.getElementById('formCriarProduto').reset(); calcularMargem();">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6" />
                    <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" />
                    <path d="M10 11v6M14 11v6" />
                    <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" />
                </svg>
                Excluir
            </button>

            <a href="./listProduto" class="btn btn--ghost btn--sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
                Cancelar
            </a>

            <button type="submit" form="formCriarProduto" class="btn btn--primary btn--sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                </svg>
                Gravar
            </button>
        </div>

    </div>
</div>

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

    // Fechar modal clicando fora
    document.getElementById('modalCriarProduto').addEventListener('click', function(e) {
        if (e.target === this) window.location.href = './listProduto';
    });
</script>