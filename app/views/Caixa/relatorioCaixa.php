<?php
// Variáveis esperadas do controller (CaixaController::relatorio):
// $periodo     array — tipo, inicio, fim, rotulo, granularidade
// $resumo      array — resumoPeriodo(): entradas, saidas, totais, resultado
// $serie       array — seriePeriodo(): [rotulo, entradas, saidas]
// $lancamentos array — lancamentosPeriodo()

$formaLabel = [
    'DINHEIRO' => 'Dinheiro',
    'PIX'      => 'PIX',
    'CARTAO'   => 'Cartão',
    'BOLETO'   => 'Boleto',
];
$catSaidaLabel = [
    'PECAS' => 'Peças',
    'BANCO' => 'Retirada para banco',
    'OUTRO' => 'Outro',
];
// Rótulos das saídas na tabela de lançamentos (inclui origens automáticas)
$catLabel = $catSaidaLabel + ['OS' => 'Ordem de Serviço', 'VENDA' => 'Venda'];

$brl = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

$totalEntradas = (float) ($resumo['total_entradas'] ?? 0);
$totalSaidas   = (float) ($resumo['total_saidas'] ?? 0);
$resultado     = (float) ($resumo['resultado'] ?? 0);

// Mantém o intervalo escolhido nos links de período/PDF
$qsPeriodo = ['periodo' => $periodo['tipo'] ?? 'mes'];
if (($periodo['tipo'] ?? '') === 'custom') {
    $qsPeriodo['inicio'] = $periodo['inicio'];
    $qsPeriodo['fim']    = $periodo['fim'];
}

$abas = [
    'dia'    => 'Diário',
    'semana' => 'Semanal',
    'mes'    => 'Mensal',
    'ano'    => 'Anual',
];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Relatório de Caixa</h1>
        <p class="page-header__subtitle">
            Entradas por forma de pagamento e saídas por categoria — <?= htmlspecialchars($periodo['rotulo'] ?? '') ?>.
        </p>
    </div>
    <div class="page-header__actions">
        <a href="./listCaixa" class="btn btn--ghost">Histórico</a>
        <a href="./relatorioCaixaPdf?<?= htmlspecialchars(http_build_query($qsPeriodo)) ?>" class="btn btn--primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
            Baixar PDF
        </a>
    </div>
</div>

<!-- ── Seletor de período ── -->
<section class="card" style="margin-bottom:20px;">
    <div class="card__body" style="padding:16px 20px;display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:space-between;">

        <div class="periodo-tabs">
            <?php foreach ($abas as $chave => $rotulo):
                $ativa = ($periodo['tipo'] ?? 'mes') === $chave; ?>
                <a href="./relatorioCaixa?periodo=<?= $chave ?>"
                   class="periodo-tab<?= $ativa ? ' periodo-tab--ativa' : '' ?>"><?= $rotulo ?></a>
            <?php endforeach; ?>
        </div>

        <form method="GET" action="./relatorioCaixa" class="periodo-custom">
            <input type="hidden" name="periodo" value="custom">
            <label for="inicio">De</label>
            <input type="date" id="inicio" name="inicio" value="<?= htmlspecialchars($periodo['inicio'] ?? '') ?>" required>
            <label for="fim">até</label>
            <input type="date" id="fim" name="fim" value="<?= htmlspecialchars($periodo['fim'] ?? '') ?>" required>
            <button type="submit" class="btn btn--ghost btn--sm">Aplicar</button>
        </form>
    </div>
</section>

<!-- ── Totais do período ── -->
<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Total de entradas</div>
            <div class="stat-card__ico b1">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="19" x2="12" y2="5" />
                    <polyline points="5 12 12 5 19 12" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $brl($totalEntradas) ?></div>
        <div class="stat-card__delta"><?= (int) ($resumo['qtd_lancamentos'] ?? 0) ?> lançamentos no período</div>
    </div>

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Total de saídas</div>
            <div class="stat-card__ico b2">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <polyline points="19 12 12 19 5 12" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $brl($totalSaidas) ?></div>
        <div class="stat-card__delta">Peças, banco e outras retiradas</div>
    </div>

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Resultado do período</div>
            <div class="stat-card__ico b3">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23" />
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value" style="color:<?= $resultado >= 0 ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
            <?= $brl($resultado) ?>
        </div>
        <div class="stat-card__delta">Entradas − saídas</div>
    </div>

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Sessões de caixa</div>
            <div class="stat-card__ico b4">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="7" width="20" height="14" rx="2" />
                    <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= (int) ($resumo['qtd_sessoes'] ?? 0) ?></div>
        <div class="stat-card__delta">Com movimentação no período</div>
    </div>
</section>

<!-- ── Evolução ── -->
<section class="card mt-24">
    <div class="card__header">
        <div class="card__title">
            Evolução <?= ($periodo['granularidade'] ?? 'dia') === 'mes' ? 'mensal' : 'diária' ?>
        </div>
        <div class="chart-legend">
            <span><i style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#16A34A;vertical-align:middle;"></i> Entradas</span>
            <span style="margin-left:12px;"><i style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#DC2626;vertical-align:middle;"></i> Saídas</span>
        </div>
    </div>
    <div class="card__body">
        <?php if (!empty($serie)): ?>
            <div class="chart-wrap">
                <canvas id="caixaChart"></canvas>
            </div>
        <?php else: ?>
            <p style="font-size:13px;color:var(--color-text-muted);text-align:center;padding:40px 0;">
                Nenhuma movimentação registrada neste período.
            </p>
        <?php endif; ?>
    </div>
</section>

<!-- ── Quebra por forma de pagamento e por categoria ── -->
<section class="grid-2 mt-24">

    <div class="card">
        <div class="card__header">
            <div class="card__title">Entradas por forma de pagamento</div>
        </div>
        <div class="card__body">
            <div class="bar-list">
                <?php foreach ($formaLabel as $chave => $rotulo):
                    $val = (float) ($resumo['entradas'][$chave] ?? 0);
                    $pct = $totalEntradas > 0 ? round(($val / $totalEntradas) * 100) : 0; ?>
                    <div class="bar-item">
                        <div class="bar-item__label">
                            <span><?= $rotulo ?></span>
                            <span style="font-weight:600;color:var(--color-text);">
                                <?= $brl($val) ?>
                                <span style="color:var(--color-text-muted);font-weight:400;">(<?= $pct ?>%)</span>
                            </span>
                        </div>
                        <div class="bar">
                            <span style="width:<?= $pct ?>%;background:var(--color-success, #16A34A);"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:13px;padding-top:12px;margin-top:4px;border-top:1px solid var(--color-border);">
                <span style="font-weight:600;color:var(--color-text);">Total</span>
                <strong style="color:var(--color-success);"><?= $brl($totalEntradas) ?></strong>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <div class="card__title">Saídas por categoria</div>
        </div>
        <div class="card__body">
            <div class="bar-list">
                <?php foreach ($catSaidaLabel as $chave => $rotulo):
                    $val = (float) ($resumo['saidas'][$chave] ?? 0);
                    $pct = $totalSaidas > 0 ? round(($val / $totalSaidas) * 100) : 0; ?>
                    <div class="bar-item">
                        <div class="bar-item__label">
                            <span><?= $rotulo ?></span>
                            <span style="font-weight:600;color:var(--color-text);">
                                <?= $brl($val) ?>
                                <span style="color:var(--color-text-muted);font-weight:400;">(<?= $pct ?>%)</span>
                            </span>
                        </div>
                        <div class="bar">
                            <span style="width:<?= $pct ?>%;background:var(--color-danger, #DC2626);"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:13px;padding-top:12px;margin-top:4px;border-top:1px solid var(--color-border);">
                <span style="font-weight:600;color:var(--color-text);">Total</span>
                <strong style="color:var(--color-danger);"><?= $brl($totalSaidas) ?></strong>
            </div>
        </div>
    </div>

</section>

<!-- ── Lançamentos do período ── -->
<section class="card mt-24">
    <div class="card__header">
        <div class="card__title">Lançamentos do período</div>
        <span class="fs-12 text-secondary"><?= count($lancamentos ?? []) ?> registro(s)</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Data/hora</th>
                    <th>Tipo</th>
                    <th>Forma / Categoria</th>
                    <th>Descrição</th>
                    <th>Registrado por</th>
                    <th style="text-align:right;">Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($lancamentos)): ?>
                    <?php foreach ($lancamentos as $m):
                        $isEntrada = ($m['tipo'] ?? '') === 'ENTRADA';
                        $detalhe   = $isEntrada
                            ? ($formaLabel[$m['forma_pagamento'] ?? ''] ?? '—')
                            : ($catLabel[$m['categoria'] ?? ''] ?? '—');
                    ?>
                        <tr>
                            <td class="text-muted">
                                <?= !empty($m['data_movimentacao']) ? date('d/m/Y H:i', strtotime($m['data_movimentacao'])) : '—' ?>
                            </td>
                            <td>
                                <?php if ($isEntrada): ?>
                                    <span class="badge badge--success"><span class="badge__dot"></span>Entrada</span>
                                <?php else: ?>
                                    <span class="badge badge--danger"><span class="badge__dot"></span>Saída</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($detalhe) ?>
                                <?php if (($m['origem'] ?? 'MANUAL') === 'OS' && !empty($m['os_numero'])): ?>
                                    <span class="badge badge--secondary" style="margin-left:6px;">OS <?= htmlspecialchars($m['os_numero']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= !empty($m['descricao']) ? htmlspecialchars($m['descricao']) : '—' ?></td>
                            <td><?= htmlspecialchars($m['usuario_nome'] ?? '—') ?></td>
                            <td style="text-align:right;font-weight:600;color:<?= $isEntrada ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                                <?= $isEntrada ? '+ ' : '− ' ?>R$ <?= number_format((float) ($m['valor'] ?? 0), 2, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">
                            Nenhuma movimentação registrada neste período.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<style>
    .periodo-tabs {
        display: inline-flex;
        gap: 4px;
        background: var(--color-bg-subtle, #F1F5F9);
        border-radius: var(--radius-md);
        padding: 4px;
    }

    .periodo-tab {
        padding: 7px 16px;
        border-radius: calc(var(--radius-md) - 2px);
        font-size: 13px;
        font-weight: 600;
        color: var(--color-text-muted);
        text-decoration: none;
        transition: background .12s ease, color .12s ease;
    }

    .periodo-tab:hover { color: var(--color-text); }

    .periodo-tab--ativa {
        background: var(--color-surface, #FFF);
        color: var(--color-primary, #094FE4);
        box-shadow: 0 1px 2px rgba(0, 0, 0, .08);
    }

    .periodo-custom {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: var(--color-text-muted);
    }

    .periodo-custom input[type="date"] {
        height: 36px;
        padding: 0 10px;
        border: 1.5px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-surface, #FFF);
        color: var(--color-text);
        font-size: 13px;
        font-family: inherit;
    }

    @media (max-width: 760px) {
        .periodo-tabs { width: 100%; justify-content: space-between; }
        .periodo-custom { width: 100%; flex-wrap: wrap; }
    }
</style>

<?php if (!empty($serie)): ?>
<script>
    (function () {
        const serie = <?= json_encode($serie, JSON_UNESCAPED_UNICODE) ?>;
        const granularidade = <?= json_encode($periodo['granularidade'] ?? 'dia') ?>;

        // 'YYYY-MM-DD' → '05/03' | 'YYYY-MM' → 'mar'
        const meses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
        const rotulos = serie.map(function (p) {
            const partes = p.rotulo.split('-');
            return granularidade === 'mes'
                ? meses[parseInt(partes[1], 10) - 1]
                : partes[2] + '/' + partes[1];
        });

        const ctx = document.getElementById('caixaChart');
        if (!ctx || typeof Chart === 'undefined') return;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: rotulos,
                datasets: [
                    {
                        label: 'Entradas',
                        data: serie.map(p => p.entradas),
                        backgroundColor: '#16A34A',
                        borderRadius: 4,
                    },
                    {
                        label: 'Saídas',
                        data: serie.map(p => p.saidas),
                        backgroundColor: '#DC2626',
                        borderRadius: 4,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                const v = ctx.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                                return ctx.dataset.label + ': R$ ' + v;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: v => 'R$ ' + v.toLocaleString('pt-BR')
                        }
                    }
                }
            }
        });
    })();
</script>
<?php endif; ?>
