<?php
/*
|--------------------------------------------------------------------------
| VIEW: viewOrcamento
|--------------------------------------------------------------------------
| Variáveis esperadas:
|
| $orcamento — array com dados do orçamento
| $itens     — array de itens (produtos e serviços)
|--------------------------------------------------------------------------
*/

$orcamento = $orcamento ?? [];
$itens     = $itens     ?? [];

$status        = $orcamento['status']     ?? 'pendente';
$dataCriacao   = !empty($orcamento['data_criacao'])   ? date('d/m/Y H:i', strtotime($orcamento['data_criacao']))   : '-';
$dataValidade  = !empty($orcamento['data_validade'])  ? date('d/m/Y',     strtotime($orcamento['data_validade']))  : '-';
// prazo_execucao é texto livre (ex.: "2 dias úteis"), não uma data.
$prazoExec     = !empty($orcamento['prazo_execucao']) ? $orcamento['prazo_execucao'] : '-';

$badgeClass = match ($status) {
    'pendente'  => 'badge--warning',
    'aprovado'  => 'badge--success',
    'reprovado' => 'badge--danger',
    'cancelado' => 'badge--secondary',
    default     => 'badge--secondary'
};
$statusTexto = match ($status) {
    'pendente'  => 'Pendente',
    'aprovado'  => 'Aprovado',
    'reprovado' => 'Recusado',
    'cancelado' => 'Cancelado',
    default     => $status
};

$linkRastreio = $linkRastreio ?? '';

// Link "wa.me" com mensagem pronta — só faz sentido quando o orçamento foi
// aprovado e o cliente tem telefone cadastrado.
$whatsappLink = null;
if ($status === 'aprovado' && !empty($orcamento['telefone_cliente'])) {
    $mensagem = "Olá, {$orcamento['cliente_nome']}. Seu orçamento nº {$orcamento['numero']} foi aprovado. Em breve entraremos em contato para dar andamento ao serviço.";
    if ($linkRastreio) {
        $mensagem .= " Acompanhe pelo link: {$linkRastreio}";
    }
    $whatsappLink = WhatsApp::linkParaCliente($orcamento['telefone_cliente'], $mensagem);
}

// Stepper de acompanhamento: Criado -> Aguardando aprovação -> decisão final.
// "cancelado" fica fora da progressão (mostrado como banner à parte).
$decisaoLabel = match ($status) {
    'aprovado'  => 'Aprovado',
    'reprovado' => 'Recusado',
    default     => 'Aprovação',
};
$decisaoOk = in_array($status, ['aprovado', 'reprovado'], true);
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">
            Visualizar Orçamento
            <?php if (!empty($orcamento['numero'])): ?>
                <span style="color: var(--color-text-muted); font-weight: 500; font-size: 18px;">
                    · <?= htmlspecialchars($orcamento['numero']) ?>
                </span>
            <?php endif; ?>
        </h1>
        <p class="page-header__subtitle">Detalhes do orçamento e seus itens.</p>
    </div>

    <div class="page-header__actions">
        <span class="badge <?= $badgeClass ?>">
            <span class="badge__dot"></span>
            <?= $statusTexto ?>
        </span>

        <a href="./listOrcamentos" class="btn btn--ghost">
            <i class="bi bi-arrow-left"></i>
            Voltar
        </a>

        <a href="./exportOrcamentoPdf?id=<?= (int) ($orcamento['id_orcamento'] ?? 0) ?>" class="btn btn--ghost">
            <i class="bi bi-file-earmark-pdf"></i>
            Baixar PDF
        </a>

        <?php if ($linkRastreio): ?>
        <button type="button" class="btn btn--ghost" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($linkRastreio, ENT_QUOTES) ?>'); this.querySelector('span').textContent = 'Copiado!'; setTimeout(() => this.querySelector('span').textContent = 'Link de Rastreio', 2000);">
            <i class="bi bi-link-45deg"></i> <span>Link de Rastreio</span>
        </button>
        <?php endif; ?>

        <?php if ($whatsappLink): ?>
        <a href="<?= htmlspecialchars($whatsappLink) ?>" target="_blank" rel="noopener" class="btn btn--ghost">
            <i class="bi bi-whatsapp"></i>
            Enviar WhatsApp
        </a>
        <?php endif; ?>

        <a href="./editOrcamento?id=<?= (int) ($orcamento['id_orcamento'] ?? 0) ?>" class="btn btn--primary">
            <i class="bi bi-pencil"></i>
            Editar
        </a>
    </div>
</div>

<style>
    .os-stepper {
        display: flex;
        align-items: flex-start;
        padding: 4px 4px 22px;
    }

    .os-stepper__step {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        text-align: center;
    }

    .os-stepper__circle {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        font-weight: 700;
        border: 2px solid var(--color-border-strong);
        color: var(--color-text-muted);
        background: var(--color-surface);
    }

    .os-stepper__circle.is-done {
        background: var(--color-success);
        border-color: var(--color-success);
        color: #fff;
    }

    .os-stepper__circle.is-current {
        background: var(--color-primary-soft);
        border-color: var(--color-primary);
        color: var(--color-primary);
        box-shadow: 0 0 0 4px var(--color-primary-soft);
    }

    .os-stepper__circle.is-danger {
        background: var(--color-danger);
        border-color: var(--color-danger);
        color: #fff;
    }

    .os-stepper__label {
        font-size: 11.5px;
        font-weight: 600;
        color: var(--color-text-muted);
        margin-top: 6px;
    }

    .os-stepper__label.is-active {
        color: var(--color-text);
    }

    .os-stepper__line {
        flex: 1;
        height: 2px;
        background: var(--color-border-strong);
        margin-top: 15px;
    }

    .os-stepper__line.is-done {
        background: var(--color-success);
    }
</style>

<?php if ($status === 'cancelado'): ?>
    <div style="margin-bottom:20px;padding:12px 16px;background:var(--color-danger-soft);color:var(--color-danger);
                border:1px solid var(--color-danger);border-radius:var(--radius-md);font-size:13px;font-weight:600;text-align:center;">
        <i class="bi bi-x-circle"></i> Este orçamento foi cancelado.
    </div>
<?php else: ?>
    <div class="os-stepper">
        <div class="os-stepper__step">
            <div class="os-stepper__circle is-done"><i class="bi bi-check-lg"></i></div>
            <div class="os-stepper__label is-active">Criado</div>
        </div>
        <div class="os-stepper__line is-done"></div>
        <div class="os-stepper__step">
            <div class="os-stepper__circle <?= $decisaoOk ? 'is-done' : 'is-current' ?>">
                <?= $decisaoOk ? '<i class="bi bi-check-lg"></i>' : '2' ?>
            </div>
            <div class="os-stepper__label <?= !$decisaoOk ? 'is-active' : '' ?>">Aguardando aprovação</div>
        </div>
        <div class="os-stepper__line <?= $decisaoOk ? 'is-done' : '' ?>"></div>
        <div class="os-stepper__step">
            <div class="os-stepper__circle <?= $status === 'aprovado' ? 'is-done' : ($status === 'reprovado' ? 'is-danger' : '') ?>">
                <?= $decisaoOk ? '<i class="bi ' . ($status === 'aprovado' ? 'bi-check-lg' : 'bi-x-lg') . '"></i>' : '3' ?>
            </div>
            <div class="os-stepper__label <?= $decisaoOk ? 'is-active' : '' ?>"><?= $decisaoLabel ?></div>
        </div>
    </div>
<?php endif; ?>

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
                    <input type="text" value="<?= htmlspecialchars($statusTexto) ?>" readonly>
                </div>

                <div class="form-field">
                    <label>Data de Criação</label>
                    <input type="text" value="<?= htmlspecialchars($dataCriacao) ?>" readonly>
                </div>

                <div class="form-field">
                    <label>Data de Validade</label>
                    <input type="text" value="<?= htmlspecialchars($dataValidade) ?>" readonly>
                </div>

                <div class="form-field">
                    <label>Prazo de Execução</label>
                    <input type="text" value="<?= htmlspecialchars($prazoExec) ?>" readonly>
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
                    <input type="text" value="<?= htmlspecialchars($orcamento['cliente_nome'] ?? '-') ?>" readonly>
                </div>

                <div class="form-field">
                    <label>Veículo</label>
                    <input type="text" value="<?= htmlspecialchars(trim(($orcamento['veiculo_modelo'] ?? '-') . ' · ' . ($orcamento['placa'] ?? ''), ' ·')) ?>" readonly>
                </div>

                <div class="form-field">
                    <label>KM do Veículo</label>
                    <input type="text" value="<?= htmlspecialchars($orcamento['km_veiculo'] ?? '-') ?>" readonly>
                </div>

                <div class="form-field">
                    <label>Mecânico Responsável</label>
                    <input type="text" value="<?= htmlspecialchars($orcamento['mecanico_nome'] ?? '-') ?>" readonly>
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
                <textarea rows="3" readonly><?= htmlspecialchars($orcamento['defeito_relatado'] ?? '') ?></textarea>
            </div>
            <div class="form-field" style="margin-top:12px;">
                <label>Diagnóstico</label>
                <textarea rows="3" readonly><?= htmlspecialchars($orcamento['diagnostico'] ?? '') ?></textarea>
            </div>
            <div class="form-field" style="margin-top:12px;">
                <label>Observações</label>
                <textarea rows="3" readonly><?= htmlspecialchars($orcamento['observacoes'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- 4. ITENS DO ORÇAMENTO -->
    <div class="card">
        <div class="card__header">
            <div class="card__title">
                <span class="ico-wrap ico" aria-hidden="true">
                    <i class="bi bi-list-check"></i>
                </span>
                4. Itens do Orçamento
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
                        <input type="text" value="<?= number_format((float)($orcamento['valor_total_pecas'] ?? 0), 2, ',', '.') ?>" readonly>
                    </div>
                </div>

                <div class="form-field">
                    <label>Total em Serviços</label>
                    <div class="os-money-input">
                        <span class="os-money-input__prefix">R$</span>
                        <input type="text" value="<?= number_format((float)($orcamento['valor_total_servicos'] ?? 0), 2, ',', '.') ?>" readonly>
                    </div>
                </div>

                <div class="form-field">
                    <label>Mão de Obra</label>
                    <div class="os-money-input">
                        <span class="os-money-input__prefix">R$</span>
                        <input type="text" value="<?= number_format((float)($orcamento['valor_mao_obra'] ?? 0), 2, ',', '.') ?>" readonly>
                    </div>
                </div>

                <div class="form-field">
                    <label>Comissão (%)</label>
                    <div class="os-money-input">
                        <span class="os-money-input__prefix" style="font-size:13px;">%</span>
                        <input type="text" value="<?= number_format((float)($orcamento['comissao_pct'] ?? 0), 2, ',', '.') ?>" readonly>
                    </div>
                </div>

                <div class="form-field" style="grid-column: 1 / -1;">
                    <label>Valor Total do Orçamento</label>
                    <div class="os-money-input os-money-input--total">
                        <span class="os-money-input__prefix">R$</span>
                        <input type="text" value="<?= number_format((float)($orcamento['valor_total_orcamento'] ?? 0), 2, ',', '.') ?>" readonly>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php if (!empty($orcamento['assinatura_cliente'])): ?>
    <!-- 6. ASSINATURA DO CLIENTE -->
    <div class="card">
        <div class="card__header">
            <div class="card__title">
                <span class="ico-wrap ico" aria-hidden="true">
                    <i class="bi bi-pen"></i>
                </span>
                6. Assinatura do Cliente
            </div>
        </div>
        <div class="card__body" style="text-align:center;">
            <img src="<?= htmlspecialchars($orcamento['assinatura_cliente']) ?>" alt="Assinatura do cliente"
                style="max-width:320px;width:100%;background:#fff;border:1px solid var(--color-border);border-radius:var(--radius-md);padding:8px;">
        </div>
    </div>
    <?php endif; ?>

</div>
