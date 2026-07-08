<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!-- MODAL OVERLAY -->
<div class="modal-overlay" id="modalCriarCliente">
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
                            d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 100-8 4 4 0 000 8z" />
                    </svg>
                </div>
                <div>
                    <div class="modal__title">Novo Cliente</div>
                    <div class="modal__subtitle">Preencha os dados abaixo para cadastrar um novo cliente.</div>
                </div>
            </div>

            <a href="./listClientes" class="modal__close" title="Fechar">
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

            <form action="./saveCliente" method="POST" id="formCriarCliente">

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
                            <label>Tipo de Pessoa <span style="color:var(--color-danger)">*</span></label>
                            <select name="tipo_pessoa" id="tipo_pessoa" required onchange="alternarTipoPessoa()">
                                <option value="">Selecione</option>
                                <option value="PF">Pessoa Física</option>
                                <option value="PJ">Pessoa Jurídica</option>
                            </select>
                        </div>

                        <div class="form-field" style="grid-column: span 2;">
                            <label>Nome <span style="color:var(--color-danger)">*</span></label>
                            <input type="text" name="nome" class="js-capitalize-words" autocapitalize="words" placeholder="Nome completo ou razão social" required>
                            <button type="button" onclick="preencherBalcao()" style="
                                margin-top:6px; padding:0; border:0; background:none;
                                color: var(--color-primary); font-size:12px; cursor:pointer;
                                display:inline-flex; align-items:center; gap:5px;
                            " title="Preenche um cliente genérico de balcão (quando não há nome do cliente)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4" />
                                </svg>
                                Não pegou o nome? Usar “Cliente Balcão”
                            </button>
                        </div>

                        <div class="form-field" id="campo_cpf">
                            <label>CPF</label>
                            <input type="text" name="cpf" id="cpf" placeholder="000.000.000-00" maxlength="14">
                        </div>

                        <div class="form-field" id="campo_cnpj" style="display:none;">
                            <label>CNPJ</label>
                            <input type="text" name="cnpj" id="cnpj" placeholder="00.000.000/0000-00" maxlength="18">
                        </div>

                        <div class="form-field">
                            <label>E-mail</label>
                            <input type="email" name="email" placeholder="email@exemplo.com">
                        </div>

                        <div class="form-field">
                            <label>Telefone</label>
                            <input type="text" name="telefone" id="telefone" placeholder="(00) 00000-0000" maxlength="15">
                        </div>

                    </div>
                </div>

                <!-- ② ENDEREÇO -->
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
                        Endereço
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap:14px 18px;">

                        <div class="form-field">
                            <label>CEP</label>
                            <div style="position:relative;">
                                <input type="text" name="cep" id="cep" placeholder="00000-000" maxlength="9"
                                    style="width:100%; padding-right:36px;" onblur="buscarCep()">
                                <span style="
                                    position:absolute; right:10px; top:50%; transform:translateY(-50%);
                                    color: var(--color-text-muted);
                                    cursor:pointer;
                                " onclick="buscarCep()" title="Buscar CEP">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </span>
                            </div>
                        </div>

                        <div class="form-field" style="grid-column: span 2;">
                            <label>Endereço</label>
                            <input type="text" name="endereco" id="endereco" placeholder="Rua, número, complemento">
                        </div>

                        <div class="form-field">
                            <label>Bairro</label>
                            <input type="text" name="bairro" id="bairro" placeholder="Bairro">
                        </div>

                        <div class="form-field">
                            <label>Cidade</label>
                            <input type="text" name="cidade" id="cidade" placeholder="Cidade">
                        </div>

                        <div class="form-field">
                            <label>UF</label>
                            <select name="uf" id="uf">
                                <option value="">UF</option>
                                <option value="AC">AC</option>
                                <option value="AL">AL</option>
                                <option value="AP">AP</option>
                                <option value="AM">AM</option>
                                <option value="BA">BA</option>
                                <option value="CE">CE</option>
                                <option value="DF">DF</option>
                                <option value="ES">ES</option>
                                <option value="GO">GO</option>
                                <option value="MA">MA</option>
                                <option value="MT">MT</option>
                                <option value="MS">MS</option>
                                <option value="MG">MG</option>
                                <option value="PA">PA</option>
                                <option value="PB">PB</option>
                                <option value="PR">PR</option>
                                <option value="PE">PE</option>
                                <option value="PI">PI</option>
                                <option value="RJ">RJ</option>
                                <option value="RN">RN</option>
                                <option value="RS">RS</option>
                                <option value="RO">RO</option>
                                <option value="RR">RR</option>
                                <option value="SC">SC</option>
                                <option value="SP" selected>SP</option>
                                <option value="SE">SE</option>
                                <option value="TO">TO</option>
                            </select>
                        </div>

                    </div>
                </div>

                <!-- ③ CONFIGURAÇÕES -->
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
                        Configurações
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 3fr; gap:14px 18px; align-items:start;">

                        <div class="form-field">
                            <label>Ativo</label>
                            <div class="radio-row">
                                <label>
                                    <input type="radio" name="ativo" value="1" checked> Sim
                                </label>
                                <label>
                                    <input type="radio" name="ativo" value="0"> Não
                                </label>
                            </div>
                        </div>

                        <div class="form-field">
                            <label>Observações</label>
                            <textarea name="observacoes" placeholder="Observações sobre o cliente..." rows="3"></textarea>
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

            <button type="button" class="btn btn--danger btn--sm"
                onclick="document.getElementById('formCriarCliente').reset(); alternarTipoPessoa();">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6" />
                    <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" />
                    <path d="M10 11v6M14 11v6" />
                    <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" />
                </svg>
                Limpar
            </button>

            <a href="./listClientes" class="btn btn--ghost btn--sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
                Cancelar
            </a>

            <button type="submit" form="formCriarCliente" class="btn btn--primary btn--sm">
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
    // Alterna visibilidade de CPF / CNPJ conforme tipo de pessoa
    function alternarTipoPessoa() {
        const tipo = document.getElementById('tipo_pessoa').value;
        const campoCpf = document.getElementById('campo_cpf');
        const campoCnpj = document.getElementById('campo_cnpj');

        if (tipo === 'PJ') {
            campoCpf.style.display = 'none';
            campoCnpj.style.display = '';
            document.getElementById('cpf').value = '';
        } else {
            campoCpf.style.display = '';
            campoCnpj.style.display = 'none';
            document.getElementById('cnpj').value = '';
        }
    }

    // Máscaras de CPF, CNPJ, telefone e CEP agora são aplicadas via IMask
    // (public/assets/js/masks.js)

    // Busca de CEP via ViaCEP
    function buscarCep() {
        const cep = document.getElementById('cep').value.replace(/\D/g, '');
        if (cep.length !== 8) return;

        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(r => r.json())
            .then(data => {
                if (data.erro) return;
                document.getElementById('endereco').value = data.logradouro || '';
                document.getElementById('bairro').value = data.bairro || '';
                document.getElementById('cidade').value = data.localidade || '';
                const ufSelect = document.getElementById('uf');
                for (let opt of ufSelect.options) {
                    if (opt.value === data.uf) {
                        ufSelect.value = data.uf;
                        break;
                    }
                }
            })
            .catch(() => {});
    }

    // Fechar modal clicando fora
    document.getElementById('modalCriarCliente').addEventListener('click', function(e) {
        if (e.target === this) window.location.href = './listClientes';
    });

    // Cliente Balcão: preenche um cliente genérico (atendimento sem nome do cliente).
    // A revisão/gravação continua manual — a Laura ainda pode complementar telefone/observações.
    function preencherBalcao() {
        const tipo = document.getElementById('tipo_pessoa');
        if (tipo) {
            tipo.value = 'PF';
            alternarTipoPessoa();
        }
        const nome = document.querySelector('#formCriarCliente input[name="nome"]');
        if (nome) {
            nome.value = 'Cliente Balcão';
            nome.focus();
            nome.select();
        }
    }

    // Aberto via botão "+ Cliente Balcão" (./saveCliente?balcao=1): já abre pré-preenchido
    if (new URLSearchParams(window.location.search).get('balcao') === '1') {
        const titulo = document.querySelector('#modalCriarCliente .modal__title');
        const sub = document.querySelector('#modalCriarCliente .modal__subtitle');
        if (titulo) titulo.textContent = 'Novo Cliente Balcão';
        if (sub) sub.textContent = 'Cliente genérico de balcão. Ajuste o nome se tiver mais detalhes e grave.';
        preencherBalcao();
    }
</script>