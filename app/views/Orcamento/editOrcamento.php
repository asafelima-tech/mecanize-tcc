<?php
/*
|--------------------------------------------------------------------------
| VIEW: editOrcamento
|--------------------------------------------------------------------------
| Variáveis esperadas:
|
| $orcamento — dados atuais do orçamento
| $itens     — itens já cadastrados (somente leitura nesta tela)
| $clientes, $veiculos, $mecanicos — selects do formulário
| $error     — mensagem de erro opcional
|--------------------------------------------------------------------------
*/

$orcamento = $orcamento ?? [];
$itens     = $itens     ?? [];
$clientes  = $clientes  ?? [];
$veiculos  = $veiculos  ?? [];
$mecanicos = $mecanicos ?? [];
$error     = $error     ?? '';

$status = $orcamento['status'] ?? 'pendente';

$dataValidade = !empty($orcamento['data_validade'])
    ? date('Y-m-d', strtotime($orcamento['data_validade']))
    : '';

// prazo_execucao é texto livre (ex.: "2 dias úteis"), não uma data — mantém o valor cru.
$prazoExec = $orcamento['prazo_execucao'] ?? '';
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">
            Editar Orçamento
            <?php if (!empty($orcamento['numero'])): ?>
                <span style="color: var(--color-text-muted); font-weight: 500; font-size: 18px;">
                    · <?= htmlspecialchars($orcamento['numero']) ?>
                </span>
            <?php endif; ?>
        </h1>
        <p class="page-header__subtitle">Atualize os dados do orçamento. Os itens só podem ser alterados refazendo o orçamento.</p>
    </div>

    <div class="page-header__actions">
        <a href="./viewOrcamento?id=<?= (int)($orcamento['id_orcamento'] ?? 0) ?>" class="btn btn--ghost">
            <i class="bi bi-arrow-left"></i>
            Voltar
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div style="
        background:var(--color-danger-soft);color:#991B1B;
        border:1px solid #FECACA;border-radius:var(--radius-md);
        padding:12px 16px;margin-bottom:20px;font-size:13px;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form action="./editOrcamento" method="POST">

    <input type="hidden" name="id_orcamento" value="<?= (int)($orcamento['id_orcamento'] ?? 0) ?>">

    <div class="prod-edit-grid">

        <!-- 1. IDENTIFICAÇÃO -->
        <div class="card">
            <div class="card__header">
                <div class="card__title">
                    <span class="ico-wrap ico" aria-hidden="true">
                        <i class="bi bi-file-earmark-text"></i>
                    </span>
                    1. Identificação
                </div>
            </div>

            <div class="card__body">
                <div class="form-grid">

                    <div class="form-field">
                        <label>Número do Orçamento</label>
                        <input type="text" value="<?= htmlspecialchars($orcamento['numero'] ?? '-') ?>" readonly>
                    </div>

                    <div class="form-field">
                        <label>Status</label>
                        <select name="status">
                            <option value="pendente" <?= $status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                            <option value="aprovado" <?= $status === 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                            <option value="reprovado" <?= $status === 'reprovado' ? 'selected' : '' ?>>Recusado</option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label>Data de Validade</label>
                        <input type="date" name="data_validade" value="<?= htmlspecialchars($dataValidade) ?>">
                    </div>

                    <div class="form-field">
                        <label>Prazo de Execução</label>
                        <input type="text" name="prazo_execucao" value="<?= htmlspecialchars($prazoExec) ?>" placeholder="Ex.: 2 dias úteis">
                    </div>

                </div>
            </div>
        </div>

        <!-- 2. CLIENTE E VEÍCULO -->
        <div class="card">
            <div class="card__header">
                <div class="card__title">
                    <span class="ico-wrap ico" aria-hidden="true">
                        <i class="bi bi-person-vcard"></i>
                    </span>
                    2. Cliente e Veículo
                </div>
            </div>

            <div class="card__body">
                <div class="form-grid">

                    <div class="form-field">
                        <label>Cliente</label>
                        <select name="cliente_id">
                            <option value="">Selecione...</option>
                            <?php foreach ($clientes as $c): ?>
                                <option value="<?= (int)$c['id_cliente'] ?>" <?= (int)($orcamento['cliente_id'] ?? 0) === (int)$c['id_cliente'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label>Veículo</label>
                        <select name="veiculo_id">
                            <option value="">Selecione...</option>
                            <?php foreach ($veiculos as $v): ?>
                                <option value="<?= (int)$v['id'] ?>" <?= (int)($orcamento['veiculo_id'] ?? 0) === (int)$v['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(($v['placa'] ?? '') . ' · ' . ($v['modelo'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label>KM do Veículo</label>
                        <input type="number" name="km_veiculo" value="<?= htmlspecialchars($orcamento['km_veiculo'] ?? '') ?>" min="0">
                    </div>

                    <div class="form-field">
                        <label>Mecânico Responsável</label>
                        <select name="mecanico_id">
                            <option value="">Selecione...</option>
                            <?php foreach ($mecanicos as $m): ?>
                                <option value="<?= (int)$m['id'] ?>" <?= (int)($orcamento['mecanico_id'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                    3. Defeito, Diagnóstico e Observações
                </div>
            </div>

            <div class="card__body">
                <div class="form-field">
                    <label>Defeito Relatado</label>
                    <textarea name="defeito_relatado" rows="3"><?= htmlspecialchars($orcamento['defeito_relatado'] ?? '') ?></textarea>
                </div>
                <div class="form-field" style="margin-top:12px;">
                    <label>Diagnóstico</label>
                    <textarea name="diagnostico" rows="3"><?= htmlspecialchars($orcamento['diagnostico'] ?? '') ?></textarea>
                </div>
                <div class="form-field" style="margin-top:12px;">
                    <label>Observações</label>
                    <textarea name="observacoes" rows="3"><?= htmlspecialchars($orcamento['observacoes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- 4. ITENS (somente leitura) -->
        <div class="card">
            <div class="card__header">
                <div class="card__title">
                    <span class="ico-wrap ico" aria-hidden="true">
                        <i class="bi bi-list-check"></i>
                    </span>
                    4. Itens do Orçamento (somente leitura)
                </div>
            </div>

            <div class="card__body">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th>Categoria</th>
                                <th>Quantidade</th>
                                <th>Preço Unit.</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($itens)): ?>
                                <?php foreach ($itens as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['nome'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($item['tipo'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($item['categoria'] ?? '-') ?></td>
                                        <td><?= number_format((float)($item['qty'] ?? 0), 0, ',', '.') ?></td>
                                        <td>R$ <?= number_format((float)($item['preco'] ?? 0), 2, ',', '.') ?></td>
                                        <td>R$ <?= number_format((float)($item['subtotal'] ?? 0), 2, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:24px; color:var(--color-text-muted);">
                                        Nenhum item neste orçamento.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 5. VALORES -->
        <div class="card">
            <div class="card__header">
                <div class="card__title">
                    <span class="ico-wrap ico" aria-hidden="true">
                        <i class="bi bi-cash-coin"></i>
                    </span>
                    5. Valores
                </div>
            </div>

            <div class="card__body">
                <div class="form-grid">

                    <div class="form-field">
                        <label>Total em Peças</label>
                        <div class="os-money-input">
                            <span class="os-money-input__prefix">R$</span>
                            <input type="text" name="valor_total_pecas" value="<?= number_format((float)($orcamento['valor_total_pecas'] ?? 0), 2, '.', '') ?>">
                        </div>
                    </div>

                    <div class="form-field">
                        <label>Total em Serviços</label>
                        <div class="os-money-input">
                            <span class="os-money-input__prefix">R$</span>
                            <input type="text" name="valor_total_servicos" value="<?= number_format((float)($orcamento['valor_total_servicos'] ?? 0), 2, '.', '') ?>">
                        </div>
                    </div>

                    <div class="form-field">
                        <label>Mão de Obra</label>
                        <div class="os-money-input">
                            <span class="os-money-input__prefix">R$</span>
                            <input type="text" name="valor_mao_obra" value="<?= number_format((float)($orcamento['valor_mao_obra'] ?? 0), 2, '.', '') ?>" readonly>
                        </div>
                    </div>

                    <div class="form-field">
                        <label>Comissão (%)</label>
                        <div class="os-money-input os-money-input--total">
                            <span class="os-money-input__prefix" style="font-size:13px;">%</span>
                            <input type="text" name="comissao_pct" value="<?= number_format((float)($orcamento['comissao_pct'] ?? 0), 2, '.', '') ?>">
                        </div>
                    </div>

                    <div class="form-field" style="grid-column: 1 / -1;">
                        <label>Valor Total do Orçamento</label>
                        <div class="os-money-input os-money-input--total">
                            <span class="os-money-input__prefix">R$</span>
                            <input type="text" name="valor_total_orcamento" value="<?= number_format((float)($orcamento['valor_total_orcamento'] ?? 0), 2, '.', '') ?>" readonly>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <div class="os-edit-footer">
        <a href="./listOrcamentos" class="btn btn--ghost">
            <i class="bi bi-x-lg"></i>
            Cancelar
        </a>

        <div class="os-edit-footer__right">
            <button type="submit" class="btn btn--primary">
                <i class="bi bi-floppy"></i>
                Salvar Alterações
            </button>
        </div>
    </div>

</form>

<!-- ============================================================
     RECÁLCULO DOS VALORES (mesma lógica da tela de criação)
     Total do Orçamento = Total em Peças + Total em Serviços
     Mão de Obra        = Total em Serviços × Comissão% / 100
     ============================================================ -->
<script>
    (function () {
        const form = document.querySelector('form[action="./editOrcamento"]');
        if (!form) return;

        const elPecas    = form.querySelector('[name="valor_total_pecas"]');
        const elServicos = form.querySelector('[name="valor_total_servicos"]');
        const elComissao = form.querySelector('[name="comissao_pct"]');
        const elMaoObra  = form.querySelector('[name="valor_mao_obra"]');
        const elTotal    = form.querySelector('[name="valor_total_orcamento"]');

        // Mesmo parsing do back-end (str_replace(',', '.')): aceita vírgula ou ponto.
        function parseMoney(str) {
            const n = parseFloat(String(str == null ? '' : str).replace(/,/g, '.'));
            return isNaN(n) ? 0 : n;
        }

        function recalcular() {
            const pecas    = parseMoney(elPecas    && elPecas.value);
            const servicos = parseMoney(elServicos && elServicos.value);
            const pct      = parseMoney(elComissao && elComissao.value);

            const comissao = servicos * pct / 100;   // mão de obra = comissão sobre serviços
            const total    = pecas + servicos;        // total = peças + serviços

            if (elMaoObra) elMaoObra.value = comissao.toFixed(2);
            if (elTotal)   elTotal.value   = total.toFixed(2);
        }

        [elPecas, elServicos, elComissao].forEach(function (el) {
            if (el) el.addEventListener('input', recalcular);
        });

        // Recalcula ao abrir para corrigir valores carregados desatualizados.
        recalcular();
    })();
</script>
