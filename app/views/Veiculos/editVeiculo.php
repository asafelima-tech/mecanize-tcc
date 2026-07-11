<?php
/*
|--------------------------------------------------------------------------
| VIEW: editVeiculo
|--------------------------------------------------------------------------
| Variáveis esperadas:
| $veiculo
| $clientes
| $marcas
| $modelos
|--------------------------------------------------------------------------
*/

$coresCarro   = require __DIR__ . '/_coresCarro.php';
$combustiveis = require __DIR__ . '/_combustiveis.php';

$corAtual = $veiculo['cor'] ?? '';
$corConhecida = false;
foreach ($coresCarro as $nomeCor => $hexCor) {
    if (strtolower($corAtual) === strtolower($nomeCor)) {
        $corConhecida = true;
        break;
    }
}

$combustivelAtual = $veiculo['combustivel'] ?? '';
$combustivelConhecido = false;
foreach ($combustiveis as $tipoCombustivel) {
    if (strtolower($combustivelAtual) === strtolower($tipoCombustivel)) {
        $combustivelConhecido = true;
        break;
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Editar Veículo</h1>
        <p class="page-header__subtitle">Atualize as informações do veículo cadastrado.</p>
    </div>

    <a href="./listVeiculos" class="btn btn--ghost">
        <i class="bi bi-arrow-left"></i>
        Voltar
    </a>
</div>

<form action="./editVeiculo" method="POST">

    <input type="hidden" name="id_veiculo" value="<?= $veiculo['id_veiculo'] ?>">

    <div class="veiculo-edit-grid">

        <!-- 1. INFORMAÇÕES DO VEÍCULO -->
        <div class="card">
            <div class="card__header">
                <div class="card__title">
                    <span class="ico-wrap ico" aria-hidden="true">
                        <i class="bi bi-car-front"></i>
                    </span>
                    1. Informações do Veículo
                </div>
            </div>

            <div class="card__body">
                <div class="form-grid">

                    <div class="form-field" style="grid-column: 1 / -1;">
                        <label>Cliente</label>
                        <select name="cliente_id" required>
                            <option value="" disabled>Selecione o cliente</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option
                                    value="<?= $cliente['id_cliente'] ?>"
                                    <?= $cliente['id_cliente'] == $veiculo['cliente_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cliente['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label>Marca</label>
                      <select name="marca_id" id="marca_id" required>
                            <option value="" disabled>Selecione a marca</option>
                            <?php foreach ($marcas as $marca): ?>
                                <option
                                    value="<?= $marca['id_marca'] ?>"
                                    <?= $marca['id_marca'] == $veiculo['marca_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($marca['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                   <div class="form-field">
    <label>Modelo</label>
    <select
        name="modelo_id"
        id="modelo_id"
        required
        data-modelo-atual="<?= htmlspecialchars($veiculo['modelo_id'] ?? '') ?>">
        <option value="">Carregando modelos...</option>
    </select>
</div>

                </div>
            </div>
        </div>

        <!-- 2. IDENTIFICAÇÃO -->
        <div class="card">
            <div class="card__header">
                <div class="card__title">
                    <span class="ico-wrap ico" aria-hidden="true">
                        <i class="bi bi-upc-scan"></i>
                    </span>
                    2. Identificação
                </div>
            </div>

            <div class="card__body">
                <div class="form-grid">

                    <div class="form-field">
                        <label>Placa</label>
                        <input
                            type="text"
                            name="placa"
                            id="placa"
                            maxlength="8"
                            value="<?= htmlspecialchars($veiculo['placa']) ?>"
                            placeholder="ABC1D23 ou ABC-1234"
                            required>
                    </div>

                    <div class="form-field">
                        <label>Ano</label>
                        <input
                            type="number"
                            name="ano"
                            min="1900"
                            max="<?= date('Y') + 1 ?>"
                            value="<?= htmlspecialchars($veiculo['ano'] ?? '') ?>"
                            placeholder="<?= date('Y') ?>">
                    </div>

                    <div class="form-field">
                        <label>Cor</label>
                        <select name="cor" id="corSelect" placeholder="Selecione a cor">
                            <option value="">Selecione a cor</option>
                            <?php if ($corAtual !== '' && !$corConhecida): ?>
                                <option value="<?= htmlspecialchars($corAtual) ?>" selected><?= htmlspecialchars($corAtual) ?></option>
                            <?php endif; ?>
                            <?php foreach ($coresCarro as $nomeCor => $hexCor): ?>
                                <option
                                    value="<?= htmlspecialchars($nomeCor) ?>"
                                    <?= strtolower($corAtual) === strtolower($nomeCor) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($nomeCor) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label>Combustível</label>
                        <select name="combustivel" id="combustivelSelect">
                            <option value="">Selecione o combustível</option>
                            <?php if ($combustivelAtual !== '' && !$combustivelConhecido): ?>
                                <option value="<?= htmlspecialchars($combustivelAtual) ?>" selected><?= htmlspecialchars($combustivelAtual) ?></option>
                            <?php endif; ?>
                            <?php foreach ($combustiveis as $tipoCombustivel): ?>
                                <option
                                    value="<?= htmlspecialchars($tipoCombustivel) ?>"
                                    <?= strtolower($combustivelAtual) === strtolower($tipoCombustivel) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tipoCombustivel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>
            </div>
        </div>

        <!-- 3. OBSERVAÇÕES -->
        <div class="card">
            <div class="card__header">
                <div class="card__title">
                    <span class="ico-wrap ico" aria-hidden="true">
                        <i class="bi bi-chat-left-text"></i>
                    </span>
                    3. Observações
                </div>
            </div>

            <div class="card__body">
                <div class="form-field">
                    <label>Detalhes adicionais</label>
                    <textarea
                        name="observacoes"
                        rows="6"
                        placeholder="Informações adicionais sobre o veículo..."><?= htmlspecialchars($veiculo['observacoes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- 4. RESUMO DO CADASTRO -->
        <div class="card">
            <div class="card__header">
                <div class="card__title">
                    <span class="ico-wrap ico" aria-hidden="true">
                        <i class="bi bi-info-circle"></i>
                    </span>
                    4. Resumo do Cadastro
                </div>
            </div>

            <div class="card__body">
                <div class="veiculo-resumo-grid">

                    <div class="veiculo-resumo-item">
                        <div class="veiculo-resumo-item__label">ID do Veículo</div>
                        <div class="veiculo-resumo-item__value">#<?= $veiculo['id_veiculo'] ?></div>
                    </div>

                    <div class="veiculo-resumo-item">
                        <div class="veiculo-resumo-item__label">Última Atualização</div>
                        <div class="veiculo-resumo-item__value"><?= date('d/m/Y') ?></div>
                    </div>

                </div>
            </div>
        </div>

    </div><!-- /.veiculo-edit-grid -->

    <!-- FOOTER DE AÇÕES -->
    <div class="veiculo-edit-footer">

        <?php if (($_SESSION['user_tipo'] ?? '') === 'ADMIN'): ?>
        <a href="./deleteVeiculo&id=<?= $veiculo['id_veiculo'] ?>"
            class="btn btn--danger-ghost"
            onclick="return confirm('Excluir este veículo? Todos os orçamentos e ordens de serviço vinculados a ele também serão APAGADOS. Esta ação é irreversível.')">
            <i class="bi bi-trash"></i>
            Excluir Veículo
        </a>
        <?php endif; ?>

        <div class="veiculo-edit-footer__right">
            <a href="./listVeiculos" class="btn btn--ghost">
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
    window.CORES_CARRO = <?= json_encode($coresCarro, JSON_UNESCAPED_UNICODE) ?>;
    window.COMBUSTIVEIS = <?= json_encode($combustiveis, JSON_UNESCAPED_UNICODE) ?>;
    window.COMBUSTIVEL_ATUAL = <?= json_encode($combustivelAtual, JSON_UNESCAPED_UNICODE) ?>;

    // Reconstrói o <select> de combustível mostrando só os tipos permitidos
    // para o modelo escolhido. Sem restrição → lista completa. Preserva o valor atual.
    function aplicarCombustiveis(permitidos, manter) {
        const sel = document.getElementById('combustivelSelect');
        if (!sel) return;

        const atual = (manter !== undefined && manter !== null) ? manter : sel.value;
        const lista = (permitidos && permitidos.length) ? permitidos : (window.COMBUSTIVEIS || []);

        sel.innerHTML = '<option value="">Selecione o combustível</option>';

        let achou = false;
        lista.forEach(function(c) {
            const o = document.createElement('option');
            o.value = c;
            o.textContent = c;
            if (c === atual) {
                o.selected = true;
                achou = true;
            }
            sel.appendChild(o);
        });

        if (atual && !achou) {
            const o = document.createElement('option');
            o.value = atual;
            o.textContent = atual;
            o.selected = true;
            sel.appendChild(o);
        }
    }

    // A máscara de placa (antiga + Mercosul) é aplicada globalmente em masks.js.

    const selectMarca = document.getElementById('marca_id');
    const selectModelo = document.getElementById('modelo_id');
    const modeloAtualId = selectModelo.dataset.modeloAtual || '';

    function carregarModelosPorMarca(marcaId, modeloSelecionado = '') {
        selectModelo.innerHTML = '<option value="">Carregando...</option>';

        if (!marcaId) {
            selectModelo.innerHTML = '<option value="">Selecione primeiro a marca</option>';
            aplicarCombustiveis(null, '');
            return;
        }

        fetch('/mecanize/public/?url=modelosPorMarca&marca_id=' + encodeURIComponent(marcaId))
            .then(response => response.text())
            .then(texto => {
                let data;

                try {
                    data = JSON.parse(texto);
                } catch (e) {
                    console.error('Resposta inválida ao carregar modelos:', texto);
                    selectModelo.innerHTML = '<option value="">Erro ao carregar modelos</option>';
                    return;
                }

                selectModelo.innerHTML = '<option value="">Selecione o modelo</option>';

                let combustiveisSel = '';

                if (Array.isArray(data) && data.length > 0) {
                    data.forEach(function(modelo) {
                        const opt = document.createElement('option');
                        opt.value = modelo.id_modelo;
                        opt.textContent = modelo.nome;
                        opt.dataset.combustiveis = modelo.combustiveis || '';

                        if (String(modelo.id_modelo) === String(modeloSelecionado)) {
                            opt.selected = true;
                            combustiveisSel = modelo.combustiveis || '';
                        }

                        selectModelo.appendChild(opt);
                    });
                } else {
                    selectModelo.innerHTML = '<option value="">Nenhum modelo encontrado</option>';
                }

                // Modelo já selecionado (abertura): filtra combustível mantendo o valor atual.
                // Caso contrário (troca de marca): lista completa, sem seleção.
                if (modeloSelecionado) {
                    aplicarCombustiveis(combustiveisSel ? combustiveisSel.split(',') : null, window.COMBUSTIVEL_ATUAL);
                } else {
                    aplicarCombustiveis(null, '');
                }
            })
            .catch(error => {
                console.error('Erro ao buscar modelos:', error);
                selectModelo.innerHTML = '<option value="">Erro ao carregar modelos</option>';
            });
    }

    selectMarca.addEventListener('change', function() {
        carregarModelosPorMarca(this.value);
    });

    // Ao escolher outro modelo, restringe o combustível aos tipos válidos dele
    selectModelo.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const csv = opt ? (opt.dataset.combustiveis || '') : '';
        aplicarCombustiveis(csv ? csv.split(',') : null, '');
    });

    // Ao abrir a tela de edição, carrega somente os modelos da marca atual
    carregarModelosPorMarca(selectMarca.value, modeloAtualId);
</script>