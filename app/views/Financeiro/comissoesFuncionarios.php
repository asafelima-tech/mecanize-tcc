<?php
require_once __DIR__ . '/../../models/TecnicoRemuneracao.php';
require_once __DIR__ . '/../../models/MovimentacaoCaixa.php';

$meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
    7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
];

$anoAtual = (int) date('Y');
$tecnicoAtual = null;
foreach ($tecnicos as $t) {
    if ((int) $t['id_usuario'] === (int) $tecnicoSelecionado) {
        $tecnicoAtual = $t;
        break;
    }
}

$alertaTipo = $sucesso ? 'sucesso' : ($erro ? 'erro' : null);
$alertaMensagem = $sucesso
    ? ['pago' => 'Pagamento registrado.', 'ajustado' => 'Valor ajustado.'][$sucesso] ?? 'Feito.'
    : $erro;

if ($alertaMensagem):
    require __DIR__ . '/../layout/_alerta.php';
endif;
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Financeiro · Comissões dos Funcionários</h1>
        <p class="page-header__subtitle">Comissão calculada automaticamente na finalização de cada OS, por técnico e por período.</p>
    </div>
</div>

<!-- ── Filtro: técnico + período ── -->
<section class="card" style="margin-bottom:20px;">
    <div class="card__body" style="padding:16px 20px;">
        <form action="./comissoesFuncionarios" method="GET" style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-field" style="flex:1 1 220px;margin:0;">
                <label>Funcionário</label>
                <select name="tecnico_id" onchange="this.form.submit()">
                    <?php if (empty($tecnicos)): ?>
                        <option value="">Nenhum técnico cadastrado</option>
                    <?php endif; ?>
                    <?php foreach ($tecnicos as $t): ?>
                        <option value="<?= (int) $t['id_usuario'] ?>" <?= (int) $t['id_usuario'] === (int) $tecnicoSelecionado ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['nome']) ?>
                            <?= empty($t['tipo']) ? ' (sem regra)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field" style="flex:0 0 160px;margin:0;">
                <label>Mês</label>
                <select name="mes" onchange="this.form.submit()">
                    <?php foreach ($meses as $num => $nome): ?>
                        <option value="<?= $num ?>" <?= $num === $mes ? 'selected' : '' ?>><?= $nome ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field" style="flex:0 0 110px;margin:0;">
                <label>Ano</label>
                <select name="ano" onchange="this.form.submit()">
                    <?php for ($a = $anoAtual + 1; $a >= $anoAtual - 3; $a--): ?>
                        <option value="<?= $a ?>" <?= $a === $ano ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </form>
    </div>
</section>

<?php if (!$tecnicoAtual): ?>
    <section class="card">
        <div class="card__body" style="text-align:center;padding:60px 20px;color:var(--color-text-muted);">
            <p>Nenhum técnico cadastrado ainda.</p>
            <a href="./saveUsuario" class="btn btn--primary" style="margin-top:12px;">Cadastrar técnico</a>
        </div>
    </section>
<?php else: ?>

    <!-- ── Resumo do período ── -->
    <div class="stat-grid" style="margin-bottom:20px;">
        <div class="stat-card">
            <div class="stat-card__head">
                <div class="stat-card__label">OS realizadas</div>
                <div class="stat-card__icon b1">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="2" width="14" height="18" rx="2" /><path d="M6 12l3 3 5-6" /></svg>
                </div>
            </div>
            <div class="stat-card__value"><?= $resumo['qtd_os'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card__head">
                <div class="stat-card__label">Total do período</div>
                <div class="stat-card__icon b2">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" /></svg>
                </div>
            </div>
            <div class="stat-card__value">R$ <?= number_format($resumo['total'], 2, ',', '.') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card__head">
                <div class="stat-card__label">Já pago</div>
                <div class="stat-card__icon b3">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" /></svg>
                </div>
            </div>
            <div class="stat-card__value">R$ <?= number_format($resumo['valor_pago'], 2, ',', '.') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card__head">
                <div class="stat-card__label">Pendente</div>
                <div class="stat-card__icon b4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 3" /></svg>
                </div>
            </div>
            <div class="stat-card__value">R$ <?= number_format($resumo['valor_pendente'], 2, ',', '.') ?></div>
        </div>
    </div>

    <!-- ── Tabela de OS do período ── -->
    <section class="card" style="margin-bottom:20px;">
        <div class="card__header">
            <div class="card__title"><?= htmlspecialchars($tecnicoAtual['nome']) ?> — <?= $meses[$mes] ?>/<?= $ano ?></div>
            <?php if ($resumo['valor_pendente'] > 0): ?>
                <button type="button" class="btn btn--primary btn--sm" onclick="document.getElementById('modal-pagar').style.display='flex'">
                    Pagar R$ <?= number_format($resumo['valor_pendente'], 2, ',', '.') ?>
                </button>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>OS</th>
                        <th>Cliente</th>
                        <th>Valor da OS</th>
                        <th>Base da comissão</th>
                        <th>Regra</th>
                        <th>Comissão</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($resumo['salario_fixo'])): ?>
                        <tr>
                            <td colspan="2" class="text-muted">Salário fixo do período</td>
                            <td>—</td>
                            <td>—</td>
                            <td><?= htmlspecialchars(TecnicoRemuneracao::rotuloTipo($tecnicoAtual['tipo'])) ?></td>
                            <td>R$ <?= number_format($resumo['salario_fixo'], 2, ',', '.') ?></td>
                            <td colspan="2">—</td>
                        </tr>
                    <?php endif; ?>
                    <?php if (empty($resumo['linhas']) && empty($resumo['salario_fixo'])): ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:40px 20px;color:var(--color-text-muted);">Nenhuma OS finalizada neste período.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($resumo['linhas'] as $l): ?>
                        <tr>
                            <td class="id-col">#<?= htmlspecialchars($l['os_numero']) ?></td>
                            <td><?= htmlspecialchars($l['cliente']) ?></td>
                            <td>R$ <?= number_format((float) $l['valor_total_os'], 2, ',', '.') ?></td>
                            <td>
                                <?= $l['base_valor'] !== null
                                    ? ($l['regra_tipo'] === 'VALOR_FIXO_SERVICO'
                                        ? (int) $l['base_valor'] . ' serviço(s)'
                                        : 'R$ ' . number_format((float) $l['base_valor'], 2, ',', '.'))
                                    : '—' ?>
                            </td>
                            <td class="fs-12 text-secondary">
                                <?= htmlspecialchars(TecnicoRemuneracao::rotuloTipo($l['regra_tipo'])) ?>
                                <?= $l['regra_percentual'] !== null ? ' (' . rtrim(rtrim(number_format((float) $l['regra_percentual'], 2, ',', '.'), '0'), ',') . '%)' : '' ?>
                            </td>
                            <td>
                                <strong>R$ <?= number_format((float) $l['valor_mao_obra'], 2, ',', '.') ?></strong>
                                <?php if (!empty($l['ajustado_manualmente'])): ?>
                                    <span class="fs-12 text-secondary" title="Ajustado manualmente">·ajustado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $l['status_pagamento'] === 'PAGO' ? 'badge--success' : 'badge--warning' ?>">
                                    <span class="badge__dot"></span><?= $l['status_pagamento'] === 'PAGO' ? 'Pago' : 'Pendente' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($l['status_pagamento'] !== 'PAGO'): ?>
                                    <button type="button" class="btn btn--ghost btn--sm"
                                        onclick="abrirAjuste(<?= (int) $l['id_OS_mao'] ?>, <?= (float) $l['valor_mao_obra'] ?>)">
                                        Ajustar
                                    </button>
                                <?php else: ?>
                                    <a href="./viewOrdemServico?id=<?= (int) $l['ordem_servico_id'] ?>" class="fs-12 text-secondary">Ver OS</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ── Histórico de pagamentos ── -->
    <?php if (!empty($fechamentos)): ?>
        <section class="card">
            <div class="card__header">
                <div class="card__title">Histórico de pagamentos</div>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Período</th>
                            <th>Valor pago</th>
                            <th>Data</th>
                            <th>Forma</th>
                            <th>Registrado por</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fechamentos as $f): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($f['periodo_inicio'])) ?> a <?= date('d/m/Y', strtotime($f['periodo_fim'])) ?></td>
                                <td>R$ <?= number_format((float) $f['valor_total'], 2, ',', '.') ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($f['data_pagamento'])) ?></td>
                                <td><?= htmlspecialchars(MovimentacaoCaixa::rotuloForma($f['forma_pagamento'])) ?></td>
                                <td class="fs-12 text-secondary"><?= htmlspecialchars($f['criado_por_nome'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <!-- ── Modal: pagar ── -->
    <div class="modal-overlay" id="modal-pagar" style="display:none;">
        <div class="modal" style="max-width:440px;">
            <div class="modal__header">
                <div>
                    <div class="modal__title">Pagar comissão</div>
                    <div class="modal__subtitle"><?= htmlspecialchars($tecnicoAtual['nome']) ?> — <?= $meses[$mes] ?>/<?= $ano ?></div>
                </div>
                <button type="button" class="modal__close" onclick="document.getElementById('modal-pagar').style.display='none'">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
                </button>
            </div>
            <form action="./pagarComissoesFuncionario" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="tecnico_id" value="<?= (int) $tecnicoSelecionado ?>">
                <input type="hidden" name="mes" value="<?= $mes ?>">
                <input type="hidden" name="ano" value="<?= $ano ?>">
                <div class="modal__body">
                    <p style="font-size:13px;margin-bottom:14px;">
                        Valor a pagar: <strong style="color:var(--color-primary);">R$ <?= number_format($resumo['valor_pendente'], 2, ',', '.') ?></strong>
                    </p>
                    <div class="form-field">
                        <label>Forma de pagamento <span style="color:var(--color-danger)">*</span></label>
                        <select name="forma_pagamento" required>
                            <?php foreach (MovimentacaoCaixa::FORMAS as $forma): ?>
                                <option value="<?= $forma ?>"><?= MovimentacaoCaixa::rotuloForma($forma) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field" style="margin-top:14px;">
                        <label>Observações</label>
                        <textarea name="observacoes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal__footer">
                    <button type="button" class="btn btn--ghost btn--sm" onclick="document.getElementById('modal-pagar').style.display='none'">Cancelar</button>
                    <button type="submit" class="btn btn--primary btn--sm">Confirmar pagamento</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Modal: ajuste manual ── -->
    <div class="modal-overlay" id="modal-ajuste" style="display:none;">
        <div class="modal" style="max-width:400px;">
            <div class="modal__header">
                <div class="modal__title">Ajustar valor da comissão</div>
                <button type="button" class="modal__close" onclick="document.getElementById('modal-ajuste').style.display='none'">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
                </button>
            </div>
            <form action="./ajustarComissaoFuncionario" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id_mao_obra" id="ajuste-id">
                <input type="hidden" name="tecnico_id" value="<?= (int) $tecnicoSelecionado ?>">
                <input type="hidden" name="mes" value="<?= $mes ?>">
                <input type="hidden" name="ano" value="<?= $ano ?>">
                <div class="modal__body">
                    <div class="form-field">
                        <label>Novo valor (R$) <span style="color:var(--color-danger)">*</span></label>
                        <input type="number" name="novo_valor" id="ajuste-valor" step="0.01" min="0" required>
                    </div>
                    <div class="form-field" style="margin-top:14px;">
                        <label>Motivo do ajuste</label>
                        <textarea name="observacao_ajuste" rows="2" placeholder="Ex.: desconto combinado, correção de regra..."></textarea>
                    </div>
                </div>
                <div class="modal__footer">
                    <button type="button" class="btn btn--ghost btn--sm" onclick="document.getElementById('modal-ajuste').style.display='none'">Cancelar</button>
                    <button type="submit" class="btn btn--primary btn--sm">Salvar ajuste</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirAjuste(id, valorAtual) {
            document.getElementById('ajuste-id').value = id;
            document.getElementById('ajuste-valor').value = valorAtual.toFixed(2);
            document.getElementById('modal-ajuste').style.display = 'flex';
        }
    </script>

<?php endif; ?>
