<?php
// Variáveis esperadas do controller:
// $faturamentoMensal, $faturamentoPorcentagem
// $statusConcluidas, $ticketMedio, $estoqueBaixo
// $ordensConcluidas
// $labelsGrafico, $graficoOS, $graficoReceita, $graficoDespesas, $graficoStatus
// $mecanicos => array com: nome, foto, os_concluidas, faturamento, avaliacao, eficiencia
// $graficoMecanicosLabels, $graficoMecanicosData
?>

<!-- ── Page Header ── -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Relatórios e Insights</h1>
        <p class="page-header__subtitle">Acompanhe o desempenho financeiro, operacional e de estoque.</p>
    </div>
    <div class="page-header__actions">
        <button class="btn btn--ghost">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="16" rx="2" />
                <line x1="16" y1="2" x2="16" y2="6" />
                <line x1="8" y1="2" x2="8" y2="6" />
                <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
            Últimos 30 dias
        </button>
        <button type="button" onclick="window.print()" class="btn btn--primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
            Imprimir / Salvar PDF
        </button>
    </div>
</div>

<!-- ── Stat Cards ── -->
<section class="stat-grid">

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Faturamento Total</div>
            <div class="stat-card__icon b1">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23" />
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value">R$ <?= number_format($faturamentoMensal ?? 0, 2, ',', '.') ?></div>
        <?php $pctFat = (float) ($faturamentoPorcentagem ?? 0); ?>
        <div class="stat-card__delta <?= $pctFat < 0 ? 'negative' : '' ?>">
            <?php if ($pctFat < 0): ?>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="23 18 13.5 8.5 8.5 13.5 1 6" />
                <polyline points="17 18 23 18 23 12" />
            </svg>
            <?php else: ?>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
                <polyline points="17 6 23 6 23 12" />
            </svg>
            <?php endif; ?>
            <?= ($pctFat > 0 ? '+' : '') . number_format($pctFat, 1, ',', '.') ?>% vs. mês anterior
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">OS Concluídas</div>
            <div class="stat-card__icon b2">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $statusConcluidas ?? 0 ?></div>
        <div class="stat-card__delta">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
                <polyline points="17 6 23 6 23 12" />
            </svg>
            Serviços finalizados
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Ticket Médio</div>
            <div class="stat-card__icon b3">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="16" rx="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value">R$ <?= number_format($ticketMedio ?? 0, 2, ',', '.') ?></div>
        <div class="stat-card__delta neutral">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Por ordem concluída
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Produtos em Baixa</div>
            <div class="stat-card__icon b4">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                    <line x1="12" y1="9" x2="12" y2="13" />
                    <line x1="12" y1="17" x2="12.01" y2="17" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $estoqueBaixo ?? 0 ?></div>
        <div class="stat-card__delta negative">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="23 18 13.5 8.5 8.5 13.5 1 6" />
                <polyline points="17 18 23 18 23 12" />
            </svg>
            Atenção ao estoque
        </div>
    </div>

</section>

<!-- ── Gráfico Receita vs Despesas ── -->
<section class="card">
    <div class="card__header">
        <div class="card__title">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-wrap ico">
                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
                <polyline points="17 6 23 6 23 12" />
            </svg>
            Receita Mensal
        </div>
        <select class="toolbar-select toolbar-select--sm">
            <option>Mensal</option>
            <option>Semanal</option>
        </select>
    </div>
    <div class="card__body">
        <!-- "vs Despesas" removido: o sistema não tem nenhum controle de
             despesas ainda (nenhuma tabela rastreia isso) — mostrar uma
             série sempre zerada ao lado da receita real dava a impressão
             de que a oficina não tem despesa nenhuma, o que é enganoso. -->
        <div class="chart-legend">
            <span><i style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#003BDD;vertical-align:middle;"></i> Receita (R$)</span>
        </div>
        <div class="chart-wrap">
            <canvas id="barChart"></canvas>
        </div>
    </div>
</section>

<!-- ── OS por Status + Clientes ── -->
<section class="grid-2 mt-24">

    <!-- OS por Status -->
    <div class="card">
        <div class="card__header">
            <div class="card__title">OS por Status (Mês)</div>
        </div>
        <div class="card__body">
            <div class="bar-list">
                <?php
                $statusItems = [
                    ['label' => 'Concluídas',       'key' => 'concluidas',        'cor' => 'var(--color-primary)',       'default' => 0],
                    ['label' => 'Em Manutenção',     'key' => 'em_manutencao',     'cor' => 'var(--color-info)',          'default' => 0],
                    ['label' => 'Aguardando Peça',   'key' => 'aguardando_peca',   'cor' => 'var(--color-warning)',       'default' => 0],
                    ['label' => 'Diagnóstico',       'key' => 'diagnostico',       'cor' => '#7C3AED',                    'default' => 0],
                    ['label' => 'Arquivadas',        'key' => 'arquivadas',        'cor' => 'var(--color-border-strong)', 'default' => 0],
                ];
                $maxOS = max(array_map(fn($s) => (int)($graficoStatus[$s['key']] ?? $s['default']), $statusItems)) ?: 1;
                foreach ($statusItems as $item):
                    $val = (int)($graficoStatus[$item['key']] ?? $item['default']);
                    $pct = round(($val / $maxOS) * 100);
                ?>
                    <div class="bar-item">
                        <div class="bar-item__label">
                            <span><?= $item['label'] ?></span>
                            <span style="color:<?= $item['cor'] ?>;font-weight:600;"><?= $val ?></span>
                        </div>
                        <div class="bar">
                            <span style="width:<?= $pct ?>%;background:<?= $item['cor'] ?>;"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Clientes Atendidos -->
    <div class="card">
        <div class="card__header">
            <div class="card__title">Clientes Atendidos</div>
            <a href="./listClientes" class="fs-12 text-secondary" style="display:inline-flex;align-items:center;gap:4px;">
                Ver clientes
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="5" y1="12" x2="19" y2="12" />
                    <polyline points="12 5 19 12 12 19" />
                </svg>
            </a>
        </div>
        <div class="card__body">
            <div class="prod-list">
                <?php if (!empty($clientesAtendidos)):
                    $maxOS = max(array_map(fn($c) => (int)($c['total_os'] ?? 0), $clientesAtendidos)) ?: 1;
                    foreach ($clientesAtendidos as $cliente):
                        $iniciais = strtoupper(implode('', array_map(fn($p) => $p[0], array_slice(explode(' ', $cliente['nome'] ?? ''), 0, 2))));
                        $pct = round(((int)($cliente['total_os'] ?? 0) / $maxOS) * 100);
                ?>
                        <div class="prod-item">
                            <div class="user-cell">
                                <div class="avatar avatar--sm"><?= htmlspecialchars($iniciais) ?></div>
                                <div>
                                    <div class="user-cell__name"><?= htmlspecialchars($cliente['nome'] ?? '-') ?></div>
                                    <div class="user-cell__sub"><?= htmlspecialchars($cliente['veiculo'] ?? '') ?> · <?= (int)($cliente['total_os'] ?? 0) ?> OS</div>
                                </div>
                            </div>
                            <div class="prod-item__bar">
                                <div class="bar"><span style="width:<?= $pct ?>%;background:var(--color-primary);"></span></div>
                            </div>
                        </div>
                    <?php endforeach;
                else: ?>
                    <p style="font-size:13px;color:var(--color-text-muted);text-align:center;padding:24px 0;">Nenhum cliente encontrado.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

</section>

<!-- ── Scripts Chart.js ── -->
<script>
    Chart.defaults.font.family = "Inter, system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = "#64748B";

    const labelsGrafico = <?= json_encode($labelsGrafico          ?? ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul']) ?>;
    const dadosReceita = <?= json_encode($graficoReceita         ?? [0, 0, 0, 0, 0, 0, 0]) ?>;

    function initCharts() {
        const el = document.getElementById("barChart");
        if (!el) return;
        const ctx = el.getContext("2d");

        const gradReceita = ctx.createLinearGradient(0, 0, 0, 320);
        gradReceita.addColorStop(0, "#60A5FA");
        gradReceita.addColorStop(1, "#003BDD");

        new Chart(ctx, {
            type: "bar",
            data: {
                labels: labelsGrafico,
                datasets: [{
                        label: "Receita (R$)",
                        data: dadosReceita,
                        backgroundColor: gradReceita,
                        borderRadius: 6,
                        borderSkipped: false,
                        maxBarThickness: 36,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: "index",
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: "#0F172A",
                        titleColor: "rgba(255,255,255,0.65)",
                        bodyColor: "#fff",
                        padding: {
                            top: 10,
                            bottom: 10,
                            left: 14,
                            right: 14
                        },
                        cornerRadius: 10,
                        boxPadding: 5,
                        callbacks: {
                            label: (ctx) => {
                                const v = ctx.parsed.y;
                                const fmt = v >= 1000 ?
                                    `R$ ${(v / 1000).toFixed(1)}k` :
                                    `R$ ${Number(v).toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
                                return ` ${ctx.dataset.label}: ${fmt}`;
                            },
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (v) => v >= 1000 ?
                                `R$ ${(v / 1000).toFixed(1)}k` : `R$ ${Number(v).toLocaleString('pt-BR', {minimumFractionDigits: 2})}`,
                        },
                        grid: {
                            color: "#F1F5F9"
                        },
                        border: {
                            display: false
                        },
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        border: {
                            display: false
                        },
                    },
                },
            },
        });
    }

    document.addEventListener("DOMContentLoaded", () => setTimeout(initCharts, 50));
</script>