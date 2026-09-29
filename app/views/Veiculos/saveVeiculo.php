<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$coresCarro   = require __DIR__ . '/_coresCarro.php';
$combustiveis = require __DIR__ . '/_combustiveis.php';
?>

<!-- MODAL OVERLAY -->
<div class="modal-overlay" id="modalCriarVeiculo">
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
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2-.001M13 16H9m4 0h2m2 0h1a1 1 0 001-1v-3.65a1 1 0 00-.22-.624l-2.34-2.81A1 1 0 0014.66 8H13" />
                    </svg>
                </div>

                <div>
                    <div class="modal__title">Novo Veículo</div>
                    <div class="modal__subtitle">Preencha os dados abaixo para cadastrar um novo veículo.</div>
                </div>
            </div>

            <a href="./listVeiculos" class="modal__close" title="Fechar">
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

            <form action="./saveVeiculo" method="POST" id="formCriarVeiculo">

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

                    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:14px 18px;">

                        <div class="form-field">
                            <label>Cliente <span style="color:var(--color-danger)">*</span></label>
                            <select name="cliente_id" required>
                                <option value="">Selecione o cliente</option>

                                <?php if (!empty($clientes)): ?>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <option value="<?= htmlspecialchars($cliente['id_cliente']) ?>">
                                            <?= htmlspecialchars($cliente['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label>Marca <span style="color:var(--color-danger)">*</span></label>
                            <select name="marca_id" id="marca_id" required>
                                <option value="">Selecione a marca</option>

                                <?php if (!empty($marcas)): ?>
                                    <?php foreach ($marcas as $marca): ?>
                                        <option value="<?= htmlspecialchars($marca['id_marca']) ?>">
                                            <?= htmlspecialchars($marca['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label>Modelo <span style="color:var(--color-danger)">*</span></label>
                            <select name="modelo_id" id="modelo_id" required>
                                <option value="">Selecione primeiro a marca</option>
                            </select>
                        </div>

                    </div>
                </div>

                <!-- ② DADOS DO VEÍCULO -->
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
                        Dados do Veículo
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap:14px 18px;">

                        <!-- PLACA (agora começa com upload) -->
                        <div class="form-field">
                            <label>Placa <span style="color:var(--color-danger)">*</span></label>

                            <!-- Área de upload (estado inicial) -->
                            <div id="areaUploadPlaca">
                                <label id="labelFotoPlaca" for="fotoPlaca" style="
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    gap: 6px;
                    border: 2px dashed var(--color-border, #D1D5DB);
                    border-radius: var(--radius-md);
                    padding: 14px 10px;
                    cursor: pointer;
                    background: var(--color-bg-soft);
                    transition: border-color .2s, background .2s;
                    text-align: center;
                    min-height: 80px;
                    position: relative;
                "
                                    onmouseenter="this.style.borderColor='var(--color-primary)'; this.style.background='var(--color-primary-soft)'"
                                    onmouseleave="this.style.borderColor='var(--color-border, #D1D5DB)'; this.style.background='var(--color-bg-soft)'">

                                    <svg id="iconeFotoPlaca" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                        viewBox="0 0 24 24" stroke="var(--color-primary)" stroke-width="1.6">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <circle cx="12" cy="13" r="3" stroke="var(--color-primary)" stroke-width="1.6" />
                                    </svg>

                                    <span id="textoFotoPlaca" style="font-size:11px; color:var(--color-text-muted); line-height:1.4;">
                                        Foto da placa<br>
                                        <span style="font-size:10px; opacity:.7;">arraste a imagem, clique para anexar ou fotografe</span>
                                    </span>

                                    <input
                                        type="file"
                                        id="fotoPlaca"
                                        accept="image/*"
                                        capture="environment"
                                        style="display:none;"
                                        onchange="aoSelecionarFotoPlaca(this)">
                                </label>

                                <!-- Preview da imagem (oculto inicialmente) -->
                                <div id="previewPlacaWrap" style="display:none; position:relative; margin-top:0;">
                                    <img id="previewPlaca" src="" alt="Preview da placa" style="
                        width:100%;
                        height:80px;
                        object-fit:cover;
                        border-radius: var(--radius-md);
                        border: 1px solid var(--color-border, #E5E7EB);
                        display:block;
                    ">
                                    <!-- Botão X para remover -->
                                    <button type="button" onclick="removerFotoPlaca()" title="Remover foto" style="
                        position:absolute; top:5px; right:5px;
                        width:22px; height:22px;
                        background: rgba(0,0,0,.55);
                        border: none;
                        border-radius: 50%;
                        cursor: pointer;
                        display:flex; align-items:center; justify-content:center;
                        color:#fff;
                        padding:0;
                        line-height:1;
                        transition: background .15s;
                    " onmouseenter="this.style.background='rgba(220,38,38,.85)'"
                                        onmouseleave="this.style.background='rgba(0,0,0,.55)'">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <!-- Botão ler placa (aparece após foto) -->
                                <button
                                    type="button"
                                    id="btnLerPlaca"
                                    onclick="lerPlaca()"
                                    style="display:none; margin-top:6px; width:100%;"
                                    class="btn btn--primary btn--sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18" />
                                    </svg>
                                    Ler placa pela foto
                                </button>

                                <div id="statusLeituraPlaca" style="font-size:11px; color:var(--color-text-muted); margin-top:4px;"></div>
                            </div>

                            <!-- Entrada manual (toggle) -->
                            <div style="margin-top:8px; text-align:center;">
                                <button type="button" onclick="toggleEntradaManualPlaca()" id="btnToggleManual" style="
                    background:none; border:none; cursor:pointer;
                    font-size:11px; color:var(--color-text-muted);
                    text-decoration:underline; padding:0;
                ">Inserir placa manualmente</button>
                            </div>

                            <div id="entradaManualPlaca" style="display:none; margin-top:8px;">
                                <div style="position:relative;">
                                    <input
                                        type="text"
                                        name="placa"
                                        id="placa"
                                        class="form-control"
                                        placeholder="ABC1D23 ou ABC-1234"
                                        maxlength="8"
                                        required>
                                    <span style="
                        position:absolute; right:10px; top:50%; transform:translateY(-50%);
                        color: var(--color-text-muted);
                    ">
                                    </span>
                                </div>
                            </div>

                            <input type="hidden" name="placa_normalizada" id="placa_normalizada">
                        </div>

                        <div class="form-field">
                            <label>Ano</label>
                            <input
                                type="number"
                                name="ano"
                                id="ano"
                                placeholder="Ex.: 2021"
                                min="1950"
                                max="<?= date('Y') + 1 ?>"
                                value="">
                        </div>

                        <div class="form-field">
                            <label>Cor</label>
                            <select name="cor" id="corSelect" placeholder="Selecione a cor">
                                <option value="">Selecione a cor</option>
                                <?php foreach ($coresCarro as $nomeCor => $hexCor): ?>
                                    <option value="<?= htmlspecialchars($nomeCor) ?>"><?= htmlspecialchars($nomeCor) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label>Combustível</label>
                            <select name="combustivel" id="combustivelSelect">
                                <option value="">Selecione o combustível</option>
                                <?php foreach ($combustiveis as $tipoCombustivel): ?>
                                    <option value="<?= htmlspecialchars($tipoCombustivel) ?>"><?= htmlspecialchars($tipoCombustivel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>
                </div>

                <!-- ③ OBSERVAÇÕES -->
                <div class="form-section" style="margin-bottom:0;">
                    <div class="form-section__title">
                        <span class="ico-wrap ico" style="
                            width:22px; height:22px;
                            background: var(--color-primary-soft);
                            color: var(--color-primary);
                            border-radius: var(--radius-sm);
                            display:inline-flex; align-items:center; justify-content:center;
                            font-size:11px; font-weight:700;
                        ">③</span>
                        Observações
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr; gap:14px 18px;">
                        <div class="form-field">
                            <label>Observações</label>
                            <textarea name="observacoes" placeholder="Informações adicionais sobre o veículo..." rows="3"></textarea>
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

            <!-- {
                    "sucesso": true,
                    "placa": "SJC7J83",
                    "dados": {
                        "marca": "JEEP",
                        "modelo": "COMPASS SERIE S TF",
                        "anoFabricacao": "2023",
                        "anoModelo": "2024",
                        "cor": "BRANCA",
                        "combustivel": "GASOLINA",
                        "chassi": "*******59364",
                        "uf": "MG",
                        "municipio": "BELO HORIZONTE",
                        "importado": "NAO"
                    }
                    } -->

            <button
                type="button"
                class="btn btn--danger btn--sm"
                onclick="limparFormularioVeiculo();">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6" />
                    <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" />
                    <path d="M10 11v6M14 11v6" />
                    <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" />
                </svg>
                Limpar
            </button>

            <a href="./listVeiculos" class="btn btn--ghost btn--sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
                Cancelar
            </a>

            <button type="submit" form="formCriarVeiculo" class="btn btn--primary btn--sm">
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
    window.CORES_CARRO = <?= json_para_script($coresCarro) ?>;
    window.COMBUSTIVEIS = <?= json_para_script($combustiveis) ?>;

    // Reconstrói o <select> de combustível mostrando só os tipos permitidos
    // para o modelo escolhido. Sem restrição (modelo novo/sem regra) → lista completa.
    function aplicarCombustiveis(permitidos, manter) {
        const sel = document.getElementById('combustivelSelect');
        if (!sel) return;

        const atual = (manter !== undefined && manter !== null) ? manter : sel.value;
        const lista = (permitidos && permitidos.length) ? permitidos : (window.COMBUSTIVEIS || []);

        sel.innerHTML = '<option value="">Selecione o combustível</option>';

        let achou = false;
        lista.forEach(function (c) {
            const o = document.createElement('option');
            o.value = c;
            o.textContent = c;
            if (c === atual) {
                o.selected = true;
                achou = true;
            }
            sel.appendChild(o);
        });

        // Preserva valor legado fora da lista para não perder o dado já gravado
        if (atual && !achou) {
            const o = document.createElement('option');
            o.value = atual;
            o.textContent = atual;
            o.selected = true;
            sel.appendChild(o);
        }
    }

    function normalizarPlaca(valor) {
        return String(valor || '')
            .toUpperCase()
            .replace(/[^A-Z0-9]/g, '')
            .substring(0, 7);
    }

    function formatarPlaca(valor) {
        const raw = normalizarPlaca(valor);

        // Placa antiga: ABC1234 -> ABC-1234
        if (/^[A-Z]{3}[0-9]{4}$/.test(raw)) {
            return raw.substring(0, 3) + '-' + raw.substring(3);
        }

        // Mercosul: ABC1D23 fica sem traço
        return raw;
    }

    function atualizarPlaca(valor) {
        const placaInput = document.getElementById('placa');
        const placaNormalizada = document.getElementById('placa_normalizada');

        const raw = normalizarPlaca(valor);

        // A digitação manual é controlada pela máscara IMask (masks.js).
        // Aqui só tratamos o preenchimento programático (OCR): repassa o valor
        // pela máscara quando ela existir, com fallback para o formatador local.
        if (window.placaMask) {
            window.placaMask.value = formatarPlaca(raw);
        } else {
            placaInput.value = formatarPlaca(raw);
        }
        if (placaNormalizada) placaNormalizada.value = raw;
    }

    document.getElementById('marca_id').addEventListener('change', function() {
        const marcaId = this.value;
        const selectModelo = document.getElementById('modelo_id');

        // Marca mudou: sem modelo escolhido ainda → combustível volta à lista completa
        aplicarCombustiveis(null, '');

        selectModelo.innerHTML = '<option value="">Carregando...</option>';

        if (!marcaId) {
            selectModelo.innerHTML = '<option value="">Selecione primeiro a marca</option>';
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

                if (Array.isArray(data) && data.length > 0) {
                    data.forEach(function(modelo) {
                        const opt = document.createElement('option');
                        opt.value = modelo.id_modelo;
                        opt.textContent = modelo.nome;
                        opt.dataset.combustiveis = modelo.combustiveis || '';
                        selectModelo.appendChild(opt);
                    });
                } else {
                    selectModelo.innerHTML = '<option value="">Nenhum modelo encontrado</option>';
                }
            })
            .catch(error => {
                console.error('Erro ao buscar modelos:', error);
                selectModelo.innerHTML = '<option value="">Erro ao carregar modelos</option>';
            });
    });

    // Ao escolher o modelo, restringe o combustível aos tipos válidos dele
    document.getElementById('modelo_id').addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const csv = opt ? (opt.dataset.combustiveis || '') : '';
        aplicarCombustiveis(csv ? csv.split(',') : null, '');
    });

    function limparModelo() {
        document.getElementById('modelo_id').innerHTML = '<option value="">Selecione primeiro a marca</option>';
    }

    function limparFormularioVeiculo() {
        document.getElementById('formCriarVeiculo').reset();
        document.getElementById('placa_normalizada').value = '';
        document.getElementById('statusLeituraPlaca').textContent = '';
        limparModelo();
    }

    document.getElementById('modalCriarVeiculo').addEventListener('click', function(e) {
        if (e.target === this) {
            window.location.href = './listVeiculos';
        }
    });

    function lerPlaca() {
        const input = document.getElementById('fotoPlaca');
        const campoPlaca = document.getElementById('placa');
        const campoStatus = document.getElementById('statusLeituraPlaca');
        const botao = document.getElementById('btnLerPlaca');

        if (!input) {
            alert('Campo de imagem não encontrado.');
            return;
        }

        if (!campoPlaca) {
            alert('Campo de placa não encontrado.');
            return;
        }

        if (!input.files || !input.files[0]) {
            alert('Selecione ou tire uma foto da placa.');
            return;
        }

        const formData = new FormData();
        formData.append('imagem', input.files[0]);

        campoStatus.textContent = 'Enviando imagem e lendo placa...';
        botao.disabled = true;
        botao.textContent = 'Lendo...';

        fetch('./lerPlaca', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const texto = await response.text();

                console.log('Resposta bruta do PHP:', texto);

                let data;

                try {
                    data = JSON.parse(texto);
                } catch (e) {
                    campoStatus.textContent = 'Erro: o PHP não retornou JSON válido.';
                    alert('O PHP não retornou JSON válido. Veja o console do navegador.');
                    console.error('Resposta recebida:', texto);
                    return;
                }

                if (data.sucesso) {
                    atualizarPlaca(data.placa);

                    campoStatus.textContent = 'Placa reconhecida: ' + normalizarPlaca(data.placa);
                    alert('Placa reconhecida: ' + normalizarPlaca(data.placa));
                } else {
                    campoStatus.textContent = data.erro || 'Não foi possível reconhecer a placa.';
                    alert(data.erro || 'Não foi possível reconhecer a placa.');
                    console.log(data);
                }
            })
            .catch(error => {
                campoStatus.textContent = 'Erro ao conectar com a rota ./lerPlaca.';
                alert('Erro ao conectar com a rota ./lerPlaca.');
                console.error(error);
            })
            .finally(() => {
                botao.disabled = false;
                botao.textContent = 'Ler placa pela foto';
            });
    }

    function atualizarPreviewPlaca(input) {
        const preview = document.getElementById('previewPlaca');
        const icone = document.getElementById('iconeFotoPlaca');
        const texto = document.getElementById('textoFotoPlaca');

        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                icone.style.display = 'none';
                texto.textContent = input.files[0].name;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function aoSelecionarFotoPlaca(input) {
        if (!input.files || !input.files[0]) return;

        const reader = new FileReader();
        reader.onload = function(e) {
            // Esconde o label de upload, mostra o preview
            document.getElementById('labelFotoPlaca').style.display = 'none';
            const wrap = document.getElementById('previewPlacaWrap');
            document.getElementById('previewPlaca').src = e.target.result;
            wrap.style.display = 'block';
            document.getElementById('btnLerPlaca').style.display = 'block';
            document.getElementById('statusLeituraPlaca').textContent = '';
        };
        reader.readAsDataURL(input.files[0]);
    }

    // Arrastar e soltar a foto da placa direto na área tracejada, como
    // alternativa a clicar (que já abria a galeria/câmera nativamente).
    (function () {
        const dropArea = document.getElementById('labelFotoPlaca');
        const inputFoto = document.getElementById('fotoPlaca');
        if (!dropArea || !inputFoto) return;

        function estiloArrastando() {
            dropArea.style.borderColor = 'var(--color-primary)';
            dropArea.style.background = 'var(--color-primary-soft)';
        }

        function estiloParado() {
            dropArea.style.borderColor = 'var(--color-border, #D1D5DB)';
            dropArea.style.background = 'var(--color-bg-soft)';
        }

        ['dragenter', 'dragover'].forEach(function (evento) {
            dropArea.addEventListener(evento, function (e) {
                e.preventDefault();
                e.stopPropagation();
                estiloArrastando();
            });
        });

        ['dragleave', 'dragend'].forEach(function (evento) {
            dropArea.addEventListener(evento, function (e) {
                e.preventDefault();
                e.stopPropagation();
                estiloParado();
            });
        });

        dropArea.addEventListener('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            estiloParado();

            const arquivos = e.dataTransfer.files;
            if (!arquivos || !arquivos[0]) return;

            if (!arquivos[0].type.startsWith('image/')) {
                alert('Solte um arquivo de imagem (foto da placa).');
                return;
            }

            inputFoto.files = arquivos;
            aoSelecionarFotoPlaca(inputFoto);
        });

        // Se o usuário errar o alvo e soltar fora da área tracejada, evita
        // que o navegador abra a imagem numa aba nova em vez de ignorar.
        ['dragover', 'drop'].forEach(function (evento) {
            document.addEventListener(evento, function (e) {
                if (!dropArea.contains(e.target)) e.preventDefault();
            });
        });
    })();

    function removerFotoPlaca() {
        document.getElementById('fotoPlaca').value = '';
        document.getElementById('previewPlaca').src = '';
        document.getElementById('previewPlacaWrap').style.display = 'none';
        document.getElementById('labelFotoPlaca').style.display = 'flex';
        document.getElementById('btnLerPlaca').style.display = 'none';
        document.getElementById('statusLeituraPlaca').textContent = '';
    }

    function toggleEntradaManualPlaca() {
        const area = document.getElementById('entradaManualPlaca');
        const btn = document.getElementById('btnToggleManual');
        const visivel = area.style.display !== 'none';
        area.style.display = visivel ? 'none' : 'block';
        btn.textContent = visivel ? 'Inserir placa manualmente' : 'Ocultar entrada manual';
    }

    // Atualiza limparFormularioVeiculo para resetar o estado visual
    function limparFormularioVeiculo() {
        document.getElementById('formCriarVeiculo').reset();
        document.getElementById('placa_normalizada').value = '';
        document.getElementById('statusLeituraPlaca').textContent = '';
        // form.reset() não sincroniza a máscara IMask sozinho
        if (window.placaMask) window.placaMask.value = '';
        limparModelo();
        removerFotoPlaca();
        document.getElementById('entradaManualPlaca').style.display = 'none';
        document.getElementById('btnToggleManual').textContent = 'Inserir placa manualmente';
        aplicarCombustiveis(null, '');

        // form.reset() não sincroniza o Tom Select sozinho
        var corEl = document.getElementById('corSelect');
        if (corEl && corEl.tomselect) corEl.tomselect.clear(true);
    }
</script>