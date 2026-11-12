<?php
/**
 * Registrar a chegada de um veículo.
 *
 * Dois caminhos, o mesmo resultado (uma ocorrência):
 *   cliente já cadastrado → busca por nome, telefone ou placa e escolhe o veículo;
 *   atendimento rápido    → só primeiro nome + telefone (cadastro PROVISÓRIO) e a placa.
 *
 * Espera: $tecnicos, $marcas, $prioridades, $modo ('existente'|'rapido'), $old, $error,
 *         $duplicidade, $clientePreSelecionado, $veiculosDoCliente.
 */
$old  = $old ?? [];
$modo = $modo ?? 'existente';
$v = fn($campo, $padrao = '') => htmlspecialchars((string) ($old[$campo] ?? $padrao));

$clienteSel   = $clientePreSelecionado ?? null;
$veiculosSel  = $veiculosDoCliente ?? [];
$veiculoSelId = (string) ($old['veiculo_id'] ?? '');
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Registrar chegada</h1>
        <p class="page-header__subtitle">Cliente, veículo e o problema relatado — a avaliação técnica começa daqui.</p>
    </div>
    <div class="page-header__actions">
        <a href="./listOcorrencias" class="btn btn--ghost">Voltar</a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <?php $alertaTipo = 'erro'; $alertaMensagem = $error; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<?php if (!empty($duplicidade)): ?>
    <section class="card" style="margin-bottom:16px;border-color:var(--color-warning);">
        <div class="card__body" style="padding:16px 20px;display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;">
            <div style="font-size:13px;">
                <strong>Cadastro existente encontrado:</strong>
                <?= htmlspecialchars($duplicidade['nome'] ?? '') ?>
                <?php if (!empty($duplicidade['telefone'])): ?> · <?= htmlspecialchars($duplicidade['telefone']) ?><?php endif; ?>
            </div>
            <a class="btn btn--primary btn--sm"
               href="./saveOcorrencia?cliente_id=<?= (int) $duplicidade['id'] ?><?= !empty($duplicidade['veiculo_id']) ? '&veiculo_id=' . (int) $duplicidade['veiculo_id'] : '' ?>&relato=<?= urlencode((string) ($old['relato'] ?? '')) ?>">
                Usar este cadastro
            </a>
        </div>
    </section>
<?php endif; ?>

<style>
    /* Celular: caixas de marcar com tamanho de toque (o rótulo inteiro já marca, mas a caixa precisa ser visível). */
    #formChegada input[type="checkbox"] { width: 22px; height: 22px; flex-shrink: 0; }
    #formChegada label:has(> input[type="checkbox"]) { min-height: 44px; }
</style>

<form action="./saveOcorrencia" method="POST" id="formChegada">
    <?= csrf_field() ?>
    <input type="hidden" name="modo_cliente" id="modo_cliente" value="<?= $modo === 'rapido' ? 'rapido' : 'existente' ?>">

    <section class="card" style="margin-bottom:16px;">
        <div class="card__header">
            <div class="card__title">1. Cliente</div>
            <div style="display:flex;gap:8px;">
                <button type="button" class="btn btn--sm <?= $modo === 'rapido' ? 'btn--ghost' : 'btn--primary' ?>" id="btnExistente" onclick="trocarModo('existente')">Já cadastrado</button>
                <button type="button" class="btn btn--sm <?= $modo === 'rapido' ? 'btn--primary' : 'btn--ghost' ?>" id="btnRapido" onclick="trocarModo('rapido')"><i class="bi bi-lightning-charge"></i> Atendimento rápido</button>
            </div>
        </div>
        <div class="card__body" style="padding:20px;">

            <!-- Cliente já cadastrado: busca por nome, telefone ou placa -->
            <div id="blocoExistente" style="<?= $modo === 'rapido' ? 'display:none;' : '' ?>">
                <input type="hidden" name="cliente_id" id="cliente_id" value="<?= $clienteSel ? (int) $clienteSel['id_cliente'] : $v('cliente_id') ?>">

                <div class="form-field">
                    <label for="busca_cliente">Buscar cliente</label>
                    <input type="text" id="busca_cliente" autocomplete="off" placeholder="Nome, telefone ou placa do veículo..." <?= $clienteSel ? 'style="display:none;"' : '' ?>>
                    <div id="resultadosBusca" style="margin-top:6px;"></div>
                </div>

                <div id="clienteEscolhido" style="display:<?= $clienteSel ? 'flex' : 'none' ?>;padding:12px 16px;background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);justify-content:space-between;align-items:center;gap:12px;">
                    <div>
                        <strong id="clienteEscolhidoNome"><?= htmlspecialchars($clienteSel['nome'] ?? '') ?></strong>
                        <div class="text-muted fs-12" id="clienteEscolhidoTel"><?= htmlspecialchars($clienteSel['telefone'] ?? '') ?></div>
                    </div>
                    <button type="button" class="btn btn--ghost btn--sm" onclick="limparCliente()">Trocar</button>
                </div>

                <div class="form-field" id="blocoVeiculoExistente" style="margin-top:16px;<?= $clienteSel ? '' : 'display:none;' ?>">
                    <label for="veiculo_id">Veículo</label>
                    <select name="veiculo_id" id="veiculo_id" data-no-ts onchange="alternarVeiculoNovo()">
                        <?php foreach ($veiculosSel as $ve): ?>
                            <option value="<?= (int) $ve['id_veiculo'] ?>" <?= $veiculoSelId === (string) $ve['id_veiculo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(strtoupper($ve['placa']) . ' — ' . trim(($ve['marca_nome'] ?? '') . ' ' . ($ve['modelo_nome'] ?? ''))) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="__novo" <?= $veiculoSelId === '__novo' ? 'selected' : '' ?>>+ Cadastrar outro veículo deste cliente</option>
                    </select>
                    <input type="hidden" name="modo_veiculo" id="modo_veiculo" value="existente">
                </div>
            </div>

            <!-- Atendimento rápido: só o essencial -->
            <div id="blocoRapido" style="<?= $modo === 'rapido' ? '' : 'display:none;' ?>">
                <p class="text-muted" style="font-size:12px;margin:0 0 12px;">
                    Para quem precisa deixar o carro sem preencher o cadastro completo. O cadastro fica marcado como
                    <strong>provisório</strong> e pode ser completado (ou unificado com um existente) depois, sem perder o histórico.
                </p>
                <div class="form-grid">
                    <div class="form-field">
                        <label for="cliente_nome">Primeiro nome <span style="color:var(--color-danger)">*</span></label>
                        <input type="text" name="cliente_nome" id="cliente_nome" maxlength="150" value="<?= $v('cliente_nome') ?>">
                    </div>
                    <div class="form-field">
                        <label for="cliente_telefone">Telefone para contato <span style="color:var(--color-danger)">*</span></label>
                        <input type="text" name="cliente_telefone" id="cliente_telefone" maxlength="20" placeholder="(18) 99999-8888" value="<?= $v('cliente_telefone') ?>">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Veículo novo (cliente rápido OU outro veículo de cliente existente) -->
    <section class="card" id="cardVeiculoNovo" style="margin-bottom:16px;<?= $modo === 'rapido' ? '' : 'display:none;' ?>">
        <div class="card__header"><div class="card__title">2. Veículo</div></div>
        <div class="card__body" style="padding:20px;">
            <div class="form-grid">
                <div class="form-field">
                    <label for="placa">Placa <span style="color:var(--color-danger)">*</span></label>
                    <input type="text" name="placa" id="placa" maxlength="8" placeholder="ABC1D23" style="text-transform:uppercase;" value="<?= $v('placa') ?>">
                    <label for="fotoPlacaOc" class="btn btn--ghost btn--sm" style="margin-top:6px;min-height:40px;cursor:pointer;">
                        Fotografar a placa
                        <input type="file" id="fotoPlacaOc" accept="image/*" capture="environment" style="display:none;">
                    </label>
                    <div id="painelLeituraPlacaOc" style="margin-top:8px;"></div>
                    <small class="text-muted fs-12">Marca e modelo são opcionais agora — complete depois. A placa lida na foto só entra no campo depois que você confirmar.</small>
                </div>
                <div class="form-field">
                    <label for="marca_id">Marca</label>
                    <select name="marca_id" id="marca_id" data-no-ts>
                        <option value="">Não informada</option>
                        <?php foreach ($marcas as $m): ?>
                            <option value="<?= (int) $m['id_marca'] ?>" <?= (string) ($old['marca_id'] ?? '') === (string) $m['id_marca'] ? 'selected' : '' ?>><?= htmlspecialchars($m['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label for="modelo_id">Modelo</label>
                    <select name="modelo_id" id="modelo_id" data-no-ts><option value="">Não informado</option></select>
                </div>
                <div class="form-field">
                    <label for="ano">Ano</label>
                    <input type="number" name="ano" id="ano" min="1950" max="<?= (int) date('Y') + 1 ?>" value="<?= $v('ano') ?>">
                </div>
                <div class="form-field">
                    <label for="cor">Cor</label>
                    <input type="text" name="cor" id="cor" maxlength="40" value="<?= $v('cor') ?>">
                </div>
            </div>
        </div>
    </section>

    <section class="card" style="margin-bottom:16px;">
        <div class="card__header"><div class="card__title">3. Atendimento</div></div>
        <div class="card__body" style="padding:20px;">
            <div class="form-field">
                <label for="relato">Problema relatado <span style="color:var(--color-danger)">*</span></label>
                <textarea name="relato" id="relato" rows="3" required placeholder="O que o cliente relatou? Quando acontece? Há quanto tempo?"><?= $v('relato') ?></textarea>
            </div>
            <div class="form-grid" style="margin-top:12px;">
                <div class="form-field">
                    <label for="tecnico_id">Técnico responsável</label>
                    <select name="tecnico_id" id="tecnico_id" data-no-ts>
                        <option value="">A definir</option>
                        <?php foreach ($tecnicos as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" <?= (string) ($old['tecnico_id'] ?? '') === (string) $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label for="observacoes">Observações iniciais</label>
                    <input type="text" name="observacoes" id="observacoes" maxlength="500" placeholder="Opcional (estado do veículo, pertences, avarias visíveis...)" value="<?= $v('observacoes') ?>">
                </div>
            </div>

            <div style="margin-top:14px;padding:12px 16px;background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);">
                <label style="display:flex;gap:8px;align-items:center;font-size:13px;font-weight:600;">
                    <input type="checkbox" name="consentimento_whatsapp" value="1" <?= !empty($old['consentimento_whatsapp']) ? 'checked' : '' ?>>
                    O cliente autorizou receber avisos do atendimento por WhatsApp
                </label>
                <small class="text-muted fs-12">Pergunte ao cliente. Fica registrado (data e quem registrou); ele pode parar quando quiser. Sem isso, o aviso de chegada não sai por WhatsApp.</small>
            </div>

            <div style="margin-top:14px;padding:12px 16px;background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);">
                <label style="display:flex;gap:8px;align-items:center;font-size:13px;font-weight:600;">
                    <input type="checkbox" name="urgencia" value="1" id="urgencia" <?= !empty($old['urgencia']) ? 'checked' : '' ?> onchange="document.getElementById('blocoUrgencia').style.display = this.checked ? 'block' : 'none'">
                    Urgência
                </label>
                <div id="blocoUrgencia" style="<?= !empty($old['urgencia']) ? '' : 'display:none;' ?>margin-top:10px;">
                    <input type="text" name="urgencia_motivo" maxlength="255" placeholder="Por que é urgente? (o administrador valida)" value="<?= $v('urgencia_motivo') ?>">
                    <small class="text-muted fs-12">A urgência declarada sobe na fila como "alta"; a validação do administrador a torna "urgente".</small>
                </div>
            </div>
        </div>
    </section>

    <div style="display:flex;gap:10px;justify-content:flex-end;">
        <a href="./listOcorrencias" class="btn btn--ghost">Cancelar</a>
        <button type="submit" class="btn btn--primary">Registrar chegada</button>
    </div>
</form>

<script src="./assets/js/foto-upload.js"></script>
<script src="./assets/js/placa-ocr.js"></script>
<script>
    var veiculosPorCliente = {};
    var modelosCarregados = {};

    function el(id) { return document.getElementById(id); }

    // Placa por foto: só vira valor do campo depois da confirmação (placa-ocr.js).
    PlacaOcr.iniciar({
        inputFoto: '#fotoPlacaOc',
        painel: '#painelLeituraPlacaOc',
        rota: './lerPlaca',
        aoConfirmar: function (placa) { el('placa').value = PlacaOcr.formatar(placa); },
        aoDigitar: function () { el('placa').focus(); }
    });

    function trocarModo(modo) {
        el('modo_cliente').value = modo;
        el('blocoExistente').style.display = modo === 'existente' ? '' : 'none';
        el('blocoRapido').style.display = modo === 'rapido' ? '' : 'none';
        el('btnExistente').className = 'btn btn--sm ' + (modo === 'existente' ? 'btn--primary' : 'btn--ghost');
        el('btnRapido').className = 'btn btn--sm ' + (modo === 'rapido' ? 'btn--primary' : 'btn--ghost');
        atualizarCardVeiculo();
    }

    function atualizarCardVeiculo() {
        var rapido = el('modo_cliente').value === 'rapido';
        var novo = !rapido && el('veiculo_id') && el('veiculo_id').value === '__novo';
        el('cardVeiculoNovo').style.display = (rapido || novo) ? '' : 'none';
        el('modo_veiculo').value = (rapido || novo) ? 'novo' : 'existente';
    }

    function alternarVeiculoNovo() { atualizarCardVeiculo(); }

    function escolherCliente(c) {
        el('cliente_id').value = c.id;
        el('clienteEscolhidoNome').textContent = c.nome;
        el('clienteEscolhidoTel').textContent = c.telefone || '';
        el('clienteEscolhido').style.display = 'flex';
        el('busca_cliente').style.display = 'none';
        el('resultadosBusca').innerHTML = '';

        var sel = el('veiculo_id');
        sel.innerHTML = '';
        (c.veiculos || []).forEach(function (v) {
            var o = document.createElement('option');
            o.value = v.id;
            o.textContent = String(v.placa).toUpperCase() + ' — ' + v.modelo;
            sel.appendChild(o);
        });
        var novo = document.createElement('option');
        novo.value = '__novo';
        novo.textContent = '+ Cadastrar outro veículo deste cliente';
        sel.appendChild(novo);
        if (!(c.veiculos || []).length) sel.value = '__novo';

        el('blocoVeiculoExistente').style.display = '';
        atualizarCardVeiculo();
    }

    function limparCliente() {
        el('cliente_id').value = '';
        el('clienteEscolhido').style.display = 'none';
        el('blocoVeiculoExistente').style.display = 'none';
        el('busca_cliente').style.display = '';
        el('busca_cliente').value = '';
        el('busca_cliente').focus();
        atualizarCardVeiculo();
    }

    (function () {
        var campo = el('busca_cliente');
        var caixa = el('resultadosBusca');
        var espera = null;

        campo.addEventListener('input', function () {
            clearTimeout(espera);
            var termo = campo.value.trim();

            if (termo.length < 2) { caixa.innerHTML = ''; return; }

            espera = setTimeout(function () {
                fetch('./buscarClientes?termo=' + encodeURIComponent(termo))
                    .then(function (r) { return r.json(); })
                    .then(function (resp) {
                        caixa.innerHTML = '';
                        var lista = resp.clientes || [];

                        if (!lista.length) {
                            caixa.innerHTML = '<div class="text-muted fs-12">Nenhum cliente encontrado. Use o <strong>atendimento rápido</strong> (primeiro nome + telefone) ou <a href="./saveCliente">cadastre o cliente completo</a>.</div>';
                            return;
                        }

                        lista.forEach(function (c) {
                            var b = document.createElement('button');
                            b.type = 'button';
                            b.className = 'btn btn--ghost';
                            b.style.cssText = 'display:flex;justify-content:space-between;width:100%;text-align:left;margin-bottom:6px;';
                            var placas = (c.veiculos || []).map(function (v) { return String(v.placa).toUpperCase(); }).join(', ');
                            b.innerHTML = '<span></span><span class="text-muted fs-12"></span>';
                            b.children[0].textContent = c.nome + (c.cadastro_status === 'PROVISORIO' ? ' (provisório)' : '');
                            b.children[1].textContent = (c.telefone || '') + (placas ? ' · ' + placas : '');
                            b.addEventListener('click', function () { escolherCliente(c); });
                            caixa.appendChild(b);
                        });
                    });
            }, 250);
        });
    })();

    // Modelos da marca escolhida (rota já existente do cadastro de veículos).
    el('marca_id').addEventListener('change', function () {
        var marca = this.value, sel = el('modelo_id');
        sel.innerHTML = '<option value="">Não informado</option>';
        if (!marca) return;
        fetch('./modelosPorMarca?marca_id=' + encodeURIComponent(marca))
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                (Array.isArray(resp) ? resp : []).forEach(function (m) {
                    var o = document.createElement('option');
                    o.value = m.id_modelo;
                    o.textContent = m.nome;
                    sel.appendChild(o);
                });
            });
    });

    atualizarCardVeiculo();
</script>
