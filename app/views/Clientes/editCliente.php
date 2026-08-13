<?php
/*
|--------------------------------------------------------------------------
| VIEW: editCliente
|--------------------------------------------------------------------------
| Variáveis esperadas:
|   $cliente  — array com os dados do cliente
|   $veiculos — array de veículos do cliente
|   $error    — string de erro (opcional)
|--------------------------------------------------------------------------
*/
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Editar Cliente</h1>
        <p class="page-header__subtitle">Atualize as informações do cliente cadastrado.</p>
    </div>

    <a href="./listClientes" class="btn btn--ghost">
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

<form action="./editCliente" method="POST">

    <input type="hidden" name="id_cliente" value="<?= (int)$cliente['id_cliente'] ?>">

    <div class="cliente-edit-grid">

        <!-- 1. IDENTIFICAÇÃO -->
        <div class="card">
            <div class="card__header">
                <div class="card__title">
                    <span class="ico-wrap ico" aria-hidden="true">
                        <i class="bi bi-person"></i>
                    </span>
                    1. Identificação
                </div>
            </div>

            <div class="card__body">
                <div class="form-grid">

                    <div class="form-field">
                        <label>Tipo de Pessoa <span style="color:var(--color-danger)">*</span></label>
                        <select name="tipo_pessoa" id="tipo_pessoa" required onchange="alternarTipoPessoa()">
                            <option value="">Selecione</option>
                            <option value="PF" <?= ($cliente['tipo_pessoa'] ?? '') === 'PF' ? 'selected' : '' ?>>Pessoa Física</option>
                            <option value="PJ" <?= ($cliente['tipo_pessoa'] ?? '') === 'PJ' ? 'selected' : '' ?>>Pessoa Jurídica</option>
                        </select>
                    </div>

                    <div class="form-field" style="grid-column: span 2;">
                        <label>Nome <span style="color:var(--color-danger)">*</span></label>
                        <input type="text" name="nome" class="js-capitalize-words" autocapitalize="words"
                            value="<?= htmlspecialchars($cliente['nome'] ?? '') ?>"
                            placeholder="Nome completo ou razão social" required>
                    </div>

                    <div class="form-field" id="campo_cpf" <?= ($cliente['tipo_pessoa'] ?? '') === 'PJ' ? 'style="display:none;"' : '' ?>>
                        <label>CPF</label>
                        <input type="text" name="cpf" id="cpf"
                            value="<?= htmlspecialchars($cliente['cpf'] ?? '') ?>"
                            placeholder="000.000.000-00" maxlength="14">
                    </div>

                    <div class="form-field" id="campo_cnpj" <?= ($cliente['tipo_pessoa'] ?? '') !== 'PJ' ? 'style="display:none;"' : '' ?>>
                        <label>CNPJ</label>
                        <input type="text" name="cnpj" id="cnpj"
                            value="<?= htmlspecialchars($cliente['cnpj'] ?? '') ?>"
                            placeholder="00.000.000/0000-00" maxlength="18">
                    </div>

                    <div class="form-field">
                        <label>E-mail</label>
                        <input type="email" name="email"
                            value="<?= htmlspecialchars($cliente['email'] ?? '') ?>"
                            placeholder="email@exemplo.com">
                    </div>

                    <div class="form-field">
                        <label>Telefone</label>
                        <input type="text" name="telefone" id="telefone"
                            value="<?= htmlspecialchars($cliente['telefone'] ?? '') ?>"
                            placeholder="(00) 00000-0000" maxlength="15">
                    </div>

                </div>
            </div>
        </div>

        <!-- 2. ENDEREÇO -->
        <div class="card">
            <div class="card__header">
                <div class="card__title">
                    <span class="ico-wrap ico" aria-hidden="true">
                        <i class="bi bi-geo-alt"></i>
                    </span>
                    2. Endereço
                </div>
            </div>

            <div class="card__body">
                <div class="form-grid">

                    <div class="form-field">
                        <label>CEP</label>
                        <div style="position:relative;">
                            <input type="text" name="cep" id="cep"
                                value="<?= htmlspecialchars($cliente['cep'] ?? '') ?>"
                                placeholder="00000-000" maxlength="9"
                                style="width:100%; padding-right:36px;" onblur="buscarCep()">
                            <span style="
                                position:absolute; right:10px; top:50%; transform:translateY(-50%);
                                color: var(--color-text-muted); cursor:pointer;
                            " onclick="buscarCep()" title="Buscar CEP">
                                <i class="bi bi-search"></i>
                            </span>
                        </div>
                    </div>

                    <div class="form-field" style="grid-column: span 2;">
                        <label>Endereço</label>
                        <input type="text" name="endereco" id="endereco"
                            value="<?= htmlspecialchars($cliente['endereco'] ?? '') ?>"
                            placeholder="Rua, número, complemento">
                    </div>

                    <div class="form-field">
                        <label>Bairro</label>
                        <input type="text" name="bairro" id="bairro"
                            value="<?= htmlspecialchars($cliente['bairro'] ?? '') ?>"
                            placeholder="Bairro">
                    </div>

                    <div class="form-field">
                        <label>Cidade</label>
                        <input type="text" name="cidade" id="cidade"
                            value="<?= htmlspecialchars($cliente['cidade'] ?? '') ?>"
                            placeholder="Cidade">
                    </div>

                    <div class="form-field">
                        <label>UF</label>
                        <select name="uf" id="uf">
                            <option value="">UF</option>
                            <?php
                            $estados = [
                                'AC',
                                'AL',
                                'AP',
                                'AM',
                                'BA',
                                'CE',
                                'DF',
                                'ES',
                                'GO',
                                'MA',
                                'MT',
                                'MS',
                                'MG',
                                'PA',
                                'PB',
                                'PR',
                                'PE',
                                'PI',
                                'RJ',
                                'RN',
                                'RS',
                                'RO',
                                'RR',
                                'SC',
                                'SP',
                                'SE',
                                'TO'
                            ];
                            foreach ($estados as $uf):
                            ?>
                                <option value="<?= $uf ?>" <?= ($cliente['uf'] ?? '') === $uf ? 'selected' : '' ?>>
                                    <?= $uf ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>
            </div>
        </div>

        <!-- 3. VEÍCULOS ASSOCIADOS -->
        <div class="card">
            <div class="card__header">
                <div class="card__title">
                    <span class="ico-wrap ico" aria-hidden="true">
                        <i class="bi bi-car-front"></i>
                    </span>
                    3. Veículos Associados
                </div>
                <a href="./saveVeiculo&id_cliente=<?= (int)$cliente['id_cliente'] ?>"
                    class="btn btn--primary btn--sm">
                    <i class="bi bi-plus-lg"></i>
                    Adicionar Veículo
                </a>
            </div>

            <div class="card__body">

                <?php if (!empty($veiculos)): ?>
                    <div class="vehicles-list">
                        <?php foreach ($veiculos as $veiculo): ?>
                            <div class="vehicle-card">
                                <div class="vehicle-card__icon">
                                    <i class="bi bi-car-front"></i>
                                </div>
                                <div class="vehicle-card__info">
                                    <div class="vehicle-card__name">
                                        <?= htmlspecialchars($veiculo['modelo']) ?>
                                    </div>
                                    <div class="vehicle-card__meta">
                                        <span><i class="bi bi-credit-card"></i> <?= htmlspecialchars($veiculo['placa']) ?></span>
                                        <span><i class="bi bi-calendar3"></i> <?= htmlspecialchars($veiculo['ano']) ?></span>
                                        <span><i class="bi bi-palette"></i> <?= htmlspecialchars($veiculo['cor']) ?></span>
                                    </div>
                                </div>
                                <a href="./editVeiculo&id=<?= (int)$veiculo['id_veiculo'] ?>"
                                    class="vehicle-card__action">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-vehicles">
                        <i class="bi bi-car-front"></i>
                        <span>Nenhum veículo cadastrado.</span>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- 4. CONFIGURAÇÕES -->
        <div class="card">
            <div class="card__header">
                <div class="card__title">
                    <span class="ico-wrap ico" aria-hidden="true">
                        <i class="bi bi-gear"></i>
                    </span>
                    4. Configurações
                </div>
            </div>

            <div class="card__body">
                <div class="form-grid">

                    <div class="form-field">
                        <label>Ativo</label>
                        <div class="radio-row">
                            <label>
                                <input type="radio" name="ativo" value="1"
                                    <?= ($cliente['ativo'] ?? 1) == 1 ? 'checked' : '' ?>> Sim
                            </label>
                            <label>
                                <input type="radio" name="ativo" value="0"
                                    <?= ($cliente['ativo'] ?? 1) == 0 ? 'checked' : '' ?>> Não
                            </label>
                        </div>
                    </div>

                    <div class="form-field" style="grid-column: span 2;">
                        <label>Observações</label>
                        <textarea name="observacoes" placeholder="Observações sobre o cliente..." rows="4"><?= htmlspecialchars($cliente['observacoes'] ?? '') ?></textarea>
                    </div>

                </div>
            </div>
        </div>

        <!-- 5. RESUMO DO CADASTRO -->
        <div class="card">
            <div class="card__header">
                <div class="card__title">
                    <span class="ico-wrap ico" aria-hidden="true">
                        <i class="bi bi-info-circle"></i>
                    </span>
                    5. Resumo do Cadastro
                </div>
            </div>

            <div class="card__body">
                <div class="veiculo-resumo-grid">

                    <div class="veiculo-resumo-item">
                        <div class="veiculo-resumo-item__label">ID do Cliente</div>
                        <div class="veiculo-resumo-item__value">#<?= (int)$cliente['id_cliente'] ?></div>
                    </div>

                    <div class="veiculo-resumo-item">
                        <div class="veiculo-resumo-item__label">Criado em</div>
                        <div class="veiculo-resumo-item__value"><?= htmlspecialchars($cliente['criado_em'] ?? date('d/m/Y')) ?></div>
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

        <?php if (($_SESSION['user_tipo'] ?? '') === 'ADMIN'): ?>
        <!--
          O botão fica aqui dentro pelo layout, mas pertence ao formulário
          "form-desativar-cliente", declarado depois do </form> desta tela — é
          para isso que serve o atributo form= do HTML5. Sem ele, a alternativa
          seria um <form> dentro de outro, que o navegador ignora.
          Desativar é POST porque muda dado: assim entra no controle de CSRF,
          que só vale para POST.
        -->
        <button type="submit"
            form="form-desativar-cliente"
            class="btn btn--danger-ghost">
            <i class="bi bi-person-x"></i>
            Desativar Cliente
        </button>
        <?php endif; ?>

        <div class="cliente-edit-footer__right">
            <a href="./listClientes" class="btn btn--ghost">
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

<?php if (($_SESSION['user_tipo'] ?? '') === 'ADMIN'): ?>
    <form method="post" action="./deleteCliente" id="form-desativar-cliente"
        onsubmit="return confirm('Deseja desativar este cliente?')">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $cliente['id_cliente'] ?>">
    </form>
<?php endif; ?>

<script>
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

    // Busca ViaCEP
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
</script>