<?php
/*
|--------------------------------------------------------------------------
| VIEW: pagamentoOrdemServico — plano de pagamento e parcelas de uma OS
|--------------------------------------------------------------------------
| Variáveis esperadas:
|
| $os       — OrdemServico::getById()
| $plano    — OrdemServicoPagamento::plano()
| $parcelas — OrdemServicoPagamento::parcelas()
| $resumo   — OrdemServicoPagamento::resumo()
| $erro / $sucesso — mensagens vindas do redirecionamento
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../models/MovimentacaoCaixa.php';
require_once __DIR__ . '/../../models/OrdemServicoPagamento.php';

$brl = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

$idOS      = (int) ($os['id_ordem_servico'] ?? 0);
$base      = (float) ($os['valor_total_os'] ?? 0);
$tipoAtual = $plano['pagamento_tipo'] ?? 'AVISTA';
$parcelado = $tipoAtual === 'PARCELADO';
$total     = (float) ($plano['pagamento_total'] ?? 0) ?: $base;
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">
            Pagamento — OS <?= htmlspecialchars($os['numero_os'] ?? ('#' . $idOS)) ?>
        </h1>
        <p class="page-header__subtitle">
            <?= htmlspecialchars($os['nome_cliente'] ?? 'Cliente') ?> ·
            <?= htmlspecialchars(strtoupper($os['placa'] ?? '')) ?>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="./viewOrdemServico?id=<?= $idOS ?>" class="btn btn--ghost">
            <i class="bi bi-arrow-left"></i> Voltar para a OS
        </a>
    </div>
</div>

<?php if (!empty($erro)): ?>
    <?php $alertaTipo = 'erro'; $alertaMensagem = $erro === 'plano' ? 'Não foi possível salvar o plano de pagamento.' : $erro;
          require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<?php if (!empty($sucesso)): ?>
    <?php $alertaTipo = 'sucesso';
          $alertaMensagem = ['parcela' => 'Parcela recebida e lançada no caixa.', 'estorno' => 'Recebimento estornado: a parcela voltou a ser devida e o valor saiu do caixa aberto.'][$sucesso] ?? 'Plano de pagamento salvo.';
          require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<!-- ── Situação do pagamento ── -->
<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Valor da OS</div>
        </div>
        <div class="stat-card__value"><?= $brl($base) ?></div>
        <div class="stat-card__delta">Peças e serviços</div>
    </div>

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Total a receber</div>
        </div>
        <div class="stat-card__value"><?= $brl($total) ?></div>
        <div class="stat-card__delta">
            Com juros e taxa
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Já recebido</div>
        </div>
        <div class="stat-card__value"><?= $brl($resumo['valor_pago'] ?? 0) ?></div>
        <div class="stat-card__delta">
            <?= (int) ($resumo['qtd_pagas'] ?? 0) ?> de <?= (int) ($resumo['total'] ?? 0) ?> parcela(s)
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Em aberto</div>
        </div>
        <div class="stat-card__value"><?= $brl($resumo['valor_pendente'] ?? 0) ?></div>
        <div class="stat-card__delta<?= (int) ($resumo['qtd_atrasadas'] ?? 0) > 0 ? ' negative' : '' ?>">
            <?php if ((int) ($resumo['qtd_atrasadas'] ?? 0) > 0): ?>
                <?= (int) $resumo['qtd_atrasadas'] ?> parcela(s) em atraso
            <?php else: ?>
                <?= (int) ($resumo['qtd_pendentes'] ?? 0) ?> parcela(s) a vencer
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ── Plano de pagamento ── -->
<section class="card mt-24">
    <div class="card__header">
        <div class="card__title">Plano de pagamento</div>
    </div>
    <div class="card__body">
        <form method="POST" action="./salvarPagamentoOrdemServico" id="form-plano">
            <?= csrf_field() ?>
            <input type="hidden" name="id_ordem_servico" value="<?= $idOS ?>">

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px 18px;">

                <div class="form-field">
                    <label>Forma de recebimento</label>
                    <select name="pagamento_forma" id="pagamento_forma">
                        <option value="">Não informar</option>
                        <?php foreach (MovimentacaoCaixa::FORMAS as $forma): ?>
                            <option value="<?= $forma ?>"
                                <?= (($plano['pagamento_forma'] ?? '') === $forma) ? 'selected' : '' ?>>
                                <?= MovimentacaoCaixa::rotuloForma($forma) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label>Condição</label>
                    <select name="pagamento_tipo" id="pagamento_tipo">
                        <option value="AVISTA" <?= !$parcelado ? 'selected' : '' ?>>À vista</option>
                        <option value="PARCELADO" <?= $parcelado ? 'selected' : '' ?>>Parcelado</option>
                    </select>
                </div>

                <div class="form-field campo-parcelado">
                    <label>Parcelas</label>
                    <input type="number" name="parcelas" id="parcelas" min="1" max="48" step="1"
                        value="<?= max(1, (int) ($resumo['total'] ?? 1)) ?>">
                </div>

                <div class="form-field campo-parcelado">
                    <label>1º vencimento</label>
                    <input type="date" name="primeiro_vencimento"
                        value="<?= htmlspecialchars($parcelas[0]['vencimento'] ?? date('Y-m-d', strtotime('+30 days'))) ?>">
                </div>

                <div class="form-field">
                    <label>Juros (%)</label>
                    <input type="number" name="pagamento_juros" id="pagamento_juros" min="0" step="0.01"
                        value="<?= htmlspecialchars($plano['pagamento_juros'] ?? '0') ?>">
                </div>

                <div class="form-field">
                    <label>Taxa (R$)</label>
                    <input type="number" name="pagamento_taxa" id="pagamento_taxa" min="0" step="0.01"
                        value="<?= htmlspecialchars($plano['pagamento_taxa'] ?? '0') ?>">
                </div>
            </div>

            <!-- Prévia calculada na tela com a MESMA conta do model
                 (base + juros% + taxa), para não haver surpresa ao salvar. -->
            <div style="margin-top:16px;padding:14px 16px;background:var(--color-bg-alt);
                        border:1px solid var(--color-border);border-radius:var(--radius-md);
                        display:flex;flex-wrap:wrap;gap:20px;align-items:center;">
                <div>
                    <div style="font-size:11px;color:var(--color-text-muted);">Total com juros e taxa</div>
                    <div style="font-size:18px;font-weight:700;" id="previa-total"><?= $brl($total) ?></div>
                </div>
                <div class="campo-parcelado">
                    <div style="font-size:11px;color:var(--color-text-muted);">Valor de cada parcela</div>
                    <div style="font-size:18px;font-weight:700;" id="previa-parcela">—</div>
                </div>
                <button type="submit" class="btn btn--primary" style="margin-left:auto;">
                    <i class="bi bi-check2"></i> Salvar plano
                </button>
            </div>

            <small style="display:block;margin-top:10px;font-size:11px;color:var(--color-text-muted);">
                Numa OS parcelada o caixa recebe cada parcela quando ela é paga — concluir a OS não
                lança o valor cheio. Parcelas já pagas não são recalculadas.
            </small>
        </form>
    </div>
</section>

<!-- ── Parcelas ── -->
<section class="card mt-24">
    <div class="card__header">
        <div class="card__title">Parcelas</div>
    </div>

    <?php if (empty($parcelas)): ?>
        <div class="card__body">
            <p style="font-size:13px;color:var(--color-text-muted);text-align:center;padding:24px 0;">
                <?= $parcelado
                    ? 'Nenhuma parcela gerada ainda — salve o plano acima.'
                    : 'Esta OS está marcada como pagamento à vista.' ?>
            </p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Parcela</th>
                        <th>Valor</th>
                        <th>Vencimento</th>
                        <th>Situação</th>
                        <th>Recebimento</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parcelas as $p): ?>
                        <?php
                        $pago     = (int) $p['pago'] === 1;
                        $atrasada = !$pago && !empty($p['vencimento']) && $p['vencimento'] < date('Y-m-d');
                        ?>
                        <tr>
                            <td><strong><?= (int) $p['numero'] ?>/<?= count($parcelas) ?></strong></td>
                            <td><?= $brl($p['valor']) ?></td>
                            <td><?= !empty($p['vencimento']) ? date('d/m/Y', strtotime($p['vencimento'])) : '—' ?></td>
                            <td>
                                <?php if ($pago): ?>
                                    <span class="badge badge--success"><span class="badge__dot"></span>Paga</span>
                                <?php elseif ($atrasada): ?>
                                    <span class="badge badge--danger"><span class="badge__dot"></span>Em atraso</span>
                                <?php else: ?>
                                    <span class="badge badge--neutral"><span class="badge__dot"></span>Pendente</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted">
                                <?php if ($pago): ?>
                                    <?= date('d/m/Y', strtotime($p['data_pagamento'])) ?>
                                    · <?= htmlspecialchars(MovimentacaoCaixa::rotuloForma($p['forma_pagamento'])) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php if (!$pago): ?>
                                    <!-- POST com token: receber dinheiro é ação que altera caixa
                                         e não pode acontecer por uma URL carregada de fora. -->
                                    <form method="POST" action="./pagarParcelaOrdemServico"
                                        style="display:flex;gap:6px;justify-content:flex-end;align-items:center;"
                                        onsubmit="return confirm('Confirmar o recebimento desta parcela? O valor entra no caixa aberto.')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id_ordem_servico" value="<?= $idOS ?>">
                                        <input type="hidden" name="id_parcela" value="<?= (int) $p['id_parcela'] ?>">
                                        <select name="forma_pagamento" required style="max-width:170px;">
                                            <?php foreach (MovimentacaoCaixa::FORMAS as $forma): ?>
                                                <option value="<?= $forma ?>"
                                                    <?= (($plano['pagamento_forma'] ?? '') === $forma) ? 'selected' : '' ?>>
                                                    <?= MovimentacaoCaixa::rotuloForma($forma) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" inputmode="decimal" name="taxa" placeholder="Taxa R$ (opcional)" title="Taxa cobrada pela operadora/maquininha — registrada à parte, não altera o valor da parcela" style="max-width:130px;">
                                        <button type="submit" class="btn btn--primary btn--sm">Receber</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:12px;">Lançada no caixa<?= (float) ($p['taxa_valor'] ?? 0) > 0 ? ' · taxa R$ ' . number_format((float) $p['taxa_valor'], 2, ',', '.') : '' ?></span>
                                    <?php if (($_SESSION['user_tipo'] ?? '') === 'ADMIN'): ?>
                                        <details style="margin-top:4px;text-align:left;">
                                            <summary style="cursor:pointer;font-size:12px;color:var(--color-danger);">Estornar recebimento</summary>
                                            <form method="POST" action="./estornarParcelaOrdemServico" style="margin-top:6px;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id_ordem_servico" value="<?= $idOS ?>">
                                                <input type="hidden" name="id_parcela" value="<?= (int) $p['id_parcela'] ?>">
                                                <input type="text" name="justificativa" maxlength="255" required placeholder="Motivo do estorno" style="width:100%;">
                                                <p class="text-muted" style="font-size:11px;margin:4px 0;">A parcela volta a ser devida e o valor sai do caixa aberto.</p>
                                                <button type="submit" class="btn btn--danger btn--sm" onclick="return confirm('Estornar este recebimento?')">Confirmar estorno</button>
                                            </form>
                                        </details>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<script>
    // Prévia do total e da parcela — mesma conta do model
    // (base + juros% + taxa), para a tela não prometer um número e o
    // salvamento gravar outro.
    (function () {
        const base = <?= json_para_script((float) $base) ?>;

        const tipo     = document.getElementById('pagamento_tipo');
        const parcelas = document.getElementById('parcelas');
        const juros    = document.getElementById('pagamento_juros');
        const taxa     = document.getElementById('pagamento_taxa');

        const alvoTotal   = document.getElementById('previa-total');
        const alvoParcela = document.getElementById('previa-parcela');

        const dinheiro = valor => 'R$ ' + valor.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        function recalcular() {
            const parcelado = tipo.value === 'PARCELADO';

            // Campos que só valem no parcelado somem no à vista.
            document.querySelectorAll('.campo-parcelado').forEach(el => {
                el.style.display = parcelado ? '' : 'none';
            });

            const total = base
                + (base * (parseFloat(juros.value) || 0) / 100)
                + (parseFloat(taxa.value) || 0);

            alvoTotal.textContent = dinheiro(Math.round(total * 100) / 100);

            const qtd = Math.max(1, parseInt(parcelas.value, 10) || 1);
            alvoParcela.textContent = parcelado ? dinheiro(total / qtd) : '—';
        }

        [tipo, parcelas, juros, taxa].forEach(campo => {
            campo.addEventListener('input', recalcular);
            campo.addEventListener('change', recalcular);
        });

        recalcular();
    })();
</script>
