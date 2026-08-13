<?php
// Variáveis esperadas do controller:
// $statusAbertas / $osAbertas, $statusEmAndamento, $statusAguardandoPeca / $aguardandoPecas
// $faturamentoMensal, $statusConcluidas, $statusCanceladas
// $percentualEmAndamento, $percentualAguardandoPeca, $percentualCanceladas, $percentualConcluidas, $percentualAbertas
// $graficoOS, $graficoReceita, $graficoStatus
// $orcamentos
?>

<!-- Page header -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Dashboard</h1>
        <p class="page-header__subtitle">Visão geral da oficina e das ordens de serviço.</p>
    </div>
    <div class="page-header__actions">
        <a href="./saveOrdemServico" class="btn btn--primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Nova OS
        </a>
    </div>
</div>

<!-- Stat cards -->
<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">OS Abertas</div>
            <div class="stat-card__icon b1">
                <i class="bi bi-folder2-open"></i>
            </div>
        </div>
        <div class="stat-card__value"><?= $statusAbertas ?? $osAbertas ?? 0 ?></div>
        <div class="stat-card__delta"><i data-icon="trending-up" data-size="12"></i> Aguardando início</div>
    </div>

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Em Andamento</div>
            <div class="stat-card__icon b2">
                <i class="bi bi-tools"></i>
            </div>
        </div>
        <div class="stat-card__value"><?= $statusEmAndamento ?? 0 ?></div>
        <div class="stat-card__delta"><i data-icon="trending-up" data-size="12"></i> Serviços em execução</div>
    </div>

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Aguardando Peça</div>
            <div class="stat-card__icon b3">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
        </div>
        <div class="stat-card__value"><?= $statusAguardandoPeca ?? $aguardandoPecas ?? 0 ?></div>
        <div class="stat-card__delta negative"><i data-icon="trending-down" data-size="12"></i> OS paradas</div>
    </div>
    
    <?php if (isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'ADMIN'): ?>

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Faturamento Mensal</div>
            <div class="stat-card__icon b4">
                <i class="bi bi-currency-dollar"></i>
            </div>
        </div>
        <div class="stat-card__value">R$ <?= number_format($faturamentoMensal ?? 0, 2, ',', '.') ?></div>
        <div class="stat-card__delta"><i data-icon="trending-up" data-size="12"></i> OS concluídas no mês</div>
    </div>

    <?php endif; ?>
</section>

<!-- Charts -->
<section class="grid-2">
    <!-- Fluxo de OS × Receita -->
    <div class="card">
        <div class="card__header">
            <div class="card__title">Fluxo de OS × Receita</div>
            <span style="
    display: inline-flex;
    align-items: center;
    height: 32px;
    padding: 0 10px;
    border: 1.5px solid var(--color-border);
    border-radius: var(--radius-sm);
    font-size: 12px;
    font-family: var(--font-sans);
    color: var(--color-text-muted);
    white-space: nowrap;
">Últimos 7 dias</span>
        </div>
        <div class="card__body">
            <div class="chart-legend">
                <span><i style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#003BDD;vertical-align:middle;"></i> Ordens de Serviço</span>
                <?php if (isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'ADMIN'): ?>
                <span><i style="display:inline-block;width:16px;height:2.5px;border-radius:2px;background:none;border-top:2.5px dashed #93C5FD;vertical-align:middle;"></i> Receita (R$)</span>
                <?php endif; ?>
            </div>
            <div class="chart-wrap-line">
                <canvas id="graficoFluxo"></canvas>
            </div>
        </div>
    </div>

    <!-- Status das OS -->
    <div class="card">
        <div class="card__header">
            <div class="card__title">Status das OS</div>
        </div>
        <div class="card__body donut-wrap">
            <div class="donut-canvas-wrap">
                <canvas id="graficoStatus"></canvas>
            </div>
            <ul class="donut-legend">
                <?php
                $legendaStatus = [
                    ['cor' => '#003BDD', 'label' => 'Concluídas',      'val' => $statusConcluidas ?? 0,     'pct' => $percentualConcluidas ?? 0],
                    ['cor' => '#60A5FA', 'label' => 'Em andamento',    'val' => $statusEmAndamento ?? 0,    'pct' => $percentualEmAndamento ?? 0],
                    ['cor' => '#BFDBFE', 'label' => 'Pendentes',       'val' => $statusAguardandoPeca ?? 0, 'pct' => $percentualAguardandoPeca ?? 0],
                    ['cor' => '#EF4444', 'label' => 'Canceladas',      'val' => $statusCanceladas ?? 0,     'pct' => $percentualCanceladas ?? 0],
                    ['cor' => '#3B82F6', 'label' => 'Abertas',         'val' => $statusAbertas ?? 0,        'pct' => $percentualAbertas ?? 0],
                ];
                foreach ($legendaStatus as $item):
                ?>
                    <li>
                        <span class="dot" style="background:<?= $item['cor'] ?>"></span>
                        <?= $item['label'] ?>
                        <b><?= $item['val'] ?> (<?= $item['pct'] ?>%)</b>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>

<?php if (isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'ADMIN'): ?>
<!-- Dashboard financeiro -->
<section class="grid-2 mt-24">
    <!-- Faturamento por mês -->
    <div class="card">
        <div class="card__header">
            <div class="card__title">Faturamento por Mês</div>
            <span style="display:inline-flex;align-items:center;height:32px;padding:0 10px;border:1.5px solid var(--color-border);
                         border-radius:var(--radius-sm);font-size:12px;color:var(--color-text-muted);white-space:nowrap;">
                Últimos 6 meses
            </span>
        </div>
        <div class="card__body">
            <div class="chart-wrap-line">
                <canvas id="graficoFaturamentoMes"></canvas>
            </div>
        </div>
    </div>

    <!-- Ticket médio + produtos mais vendidos -->
    <div class="card">
        <div class="card__header">
            <div class="card__title">Ticket Médio &amp; Peças Mais Vendidas</div>
        </div>
        <div class="card__body">
            <div style="display:flex;gap:16px;margin-bottom:20px;">
                <div style="flex:1;background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:14px 16px;">
                    <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:4px;">Ticket médio (mês)</div>
                    <div style="font-size:20px;font-weight:700;color:var(--color-primary);">
                        R$ <?= number_format($ticketMedio['mesAtual'] ?? 0, 2, ',', '.') ?>
                    </div>
                </div>
                <div style="flex:1;background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:14px 16px;">
                    <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:4px;">Ticket médio (geral)</div>
                    <div style="font-size:20px;font-weight:700;color:var(--color-text);">
                        R$ <?= number_format($ticketMedio['geral'] ?? 0, 2, ',', '.') ?>
                    </div>
                </div>
            </div>

            <?php
            $maxQtd = 1;
            foreach ($produtosMaisVendidos ?? [] as $p) {
                $maxQtd = max($maxQtd, (int) $p['quantidade']);
            }
            ?>
            <?php if (empty($produtosMaisVendidos)): ?>
                <p style="font-size:13px;color:var(--color-text-muted);text-align:center;padding:12px 0;">Nenhuma peça vendida ainda.</p>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php foreach ($produtosMaisVendidos as $p): $pct = round(((int) $p['quantidade'] / $maxQtd) * 100); ?>
                        <div>
                            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;">
                                <span style="color:var(--color-text);font-weight:600;"><?= htmlspecialchars($p['nome']) ?></span>
                                <span style="color:var(--color-text-muted);"><?= (int) $p['quantidade'] ?> un.</span>
                            </div>
                            <div style="background:var(--color-bg-alt);border-radius:var(--radius-pill);height:8px;overflow:hidden;">
                                <div style="background:var(--color-primary-gradient);height:100%;width:<?= $pct ?>%;border-radius:var(--radius-pill);"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Últimas Ordens de Serviço -->
<section class="card mt-24">
    <div class="card__header">
        <div class="card__title">Últimas Ordens de Serviço</div>
        <a href="./listOrdemServico" class="text-secondary fs-12 link-icon">
            Ver todas <i class="bi bi-arrow-right"></i>
        </a>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Veículo</th>
                    <th>Valor Total</th>
                    <th>Status</th>
                    <th>Data</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orcamentos)): ?>
                    <?php foreach ($orcamentos as $orcamento):
                        $status = $orcamento['status'] ?? '';
                        $badgeClass = match ($status) {
                            'CONCLUIDA'       => 'badge--success',
                            'EM_ANDAMENTO'    => 'badge--info',
                            'AGUARDANDO_PECA' => 'badge--warning',
                            'CANCELADA'       => 'badge--danger',
                            default           => 'badge--secondary'
                        };
                        $statusTexto = match ($status) {
                            'CONCLUIDA'       => 'Concluída',
                            'EM_ANDAMENTO'    => 'Em andamento',
                            'AGUARDANDO_PECA' => 'Aguardando peça',
                            'CANCELADA'       => 'Cancelada',
                            'ABERTA'          => 'Aberta',
                            default           => $status
                        };
                    ?>
                        <tr>
                            <td class="id-col">#<?= htmlspecialchars($orcamento['id']) ?></td>
                            <td><?= htmlspecialchars($orcamento['cliente']) ?></td>
                            <td>
                                <?= htmlspecialchars($orcamento['veiculo']) ?>
                                <?php if (!empty($orcamento['placa'])): ?>
                                    <div class="text-muted fs-12"><?= htmlspecialchars($orcamento['placa']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>R$ <?= number_format($orcamento['valor'] ?? 0, 2, ',', '.') ?></td>
                            <td>
                                <span class="badge <?= $badgeClass ?>">
                                    <span class="badge__dot"></span>
                                    <?= $statusTexto ?>
                                </span>
                            </td>
                            <td><?= !empty($orcamento['data']) ? date('d/m/Y', strtotime($orcamento['data'])) : '-' ?></td>
                            <td>
                                <div class="row-actions">
                                    <a href="./viewOrdemServico&id=<?= $orcamento['id'] ?>" class="row-action-btn primary" title="Visualizar">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </a>
                                    <a href="./editOrdemServico&id=<?= $orcamento['id'] ?>" class="row-action-btn primary" title="Editar">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding:40px;">
                            Nenhuma ordem de serviço encontrada.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
    const fontFamily = "Inter, system-ui, sans-serif";

    // Lê CSS variables em tempo real — garante cor correta em qualquer tema
    function getCSSVar(name) {
        return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    }

    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const colorGrid = isDark ? '#21262D' : '#F1F5F9';

    Chart.defaults.font.family = fontFamily;
    Chart.defaults.font.size = 11;
    Chart.defaults.color = getCSSVar('--color-text-muted') || (isDark ? '#6E7B8E' : '#64748B');

    // Plugin: texto central do donut
    // Lê as cores no momento do desenho, respondendo à troca de tema em tempo real
    const centerTextPlugin = {
        id: "centerText",
        afterDraw(chart) {
            const opts = chart.config.options.plugins?.centerText;
            if (!opts) return;
            const {
                ctx,
                chartArea: {
                    left,
                    top,
                    right,
                    bottom
                }
            } = chart;
            const cx = (left + right) / 2;
            const cy = (top + bottom) / 2;

            const currentText = getCSSVar('--color-text') || '#0F172A';
            const currentMuted = getCSSVar('--color-text-muted') || '#64748B';

            ctx.save();
            ctx.textAlign = "center";
            ctx.fillStyle = currentText;
            ctx.font = `800 30px ${fontFamily}`;
            ctx.fillText(opts.text, cx, cy + 4);
            if (opts.subtext) {
                ctx.fillStyle = currentMuted;
                ctx.font = `500 11px ${fontFamily}`;
                ctx.fillText(opts.subtext, cx, cy + 22);
            }
            ctx.restore();
        }
    };

    <?php
    // Dados financeiros só vão pro HTML se o usuário for ADMIN — senão, mesmo
    // com o card visual escondido, os valores apareceriam no código-fonte da
    // página pra qualquer usuário logado. Vale pro "Faturamento Mensal" E pra
    // série de Receita do gráfico "Fluxo de OS × Receita" (essa ficava de
    // fora dessa mesma checagem antes, vazando receita diária pra quem não é ADMIN).
    $ehAdminDashboard = isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'ADMIN';
    ?>
    const dadosOS = <?= json_para_script($graficoOS     ?? [0, 0, 0, 0, 0, 0, 0]) ?>;
    const dadosReceita = <?= json_para_script($ehAdminDashboard ? ($graficoReceita ?? [0, 0, 0, 0, 0, 0, 0]) : []) ?>;
    const dadosStatus = <?= json_para_script($graficoStatus  ?? [0, 0, 0, 0, 0]) ?>;
    const totalOS = dadosStatus.reduce((a, b) => a + b, 0);
    const labelsFaturamentoMes = <?= json_para_script($ehAdminDashboard ? ($faturamentoPorMes['labels']  ?? []) : []) ?>;
    const dadosFaturamentoMes  = <?= json_para_script($ehAdminDashboard ? ($faturamentoPorMes['valores'] ?? []) : []) ?>;

    function initCharts() {
        // ── Area chart: Fluxo OS × Receita ──────────────────────────────────────
        const flowEl = document.getElementById("graficoFluxo");
        if (flowEl) {
            const ctx = flowEl.getContext("2d");

            const gradBlue = ctx.createLinearGradient(0, 0, 0, 280);
            gradBlue.addColorStop(0, "rgba(0,59,221,0.22)");
            gradBlue.addColorStop(1, "rgba(0,59,221,0.01)");

            const gradLight = ctx.createLinearGradient(0, 0, 0, 280);
            gradLight.addColorStop(0, "rgba(147,197,253,0.30)");
            gradLight.addColorStop(1, "rgba(147,197,253,0.01)");

            const datasetsFluxo = [{
                label: "Ordens de Serviço",
                data: dadosOS,
                borderColor: "#003BDD",
                backgroundColor: gradBlue,
                fill: true,
                tension: 0.42,
                borderWidth: 2.5,
                pointRadius: 4,
                pointBackgroundColor: "#fff",
                pointBorderColor: "#003BDD",
                pointBorderWidth: 2,
                pointHoverRadius: 7,
                pointHoverBackgroundColor: "#003BDD",
                pointHoverBorderColor: "#fff",
                pointHoverBorderWidth: 2,
            }];

            // Série de Receita só existe pra ADMIN — dadosReceita já vem vazio
            // do PHP pra outros papéis, mas nem monta o dataset nesse caso
            // (evita uma linha "achatada" em zero no lugar do card escondido).
            <?php if ($ehAdminDashboard): ?>
            datasetsFluxo.push({
                label: "Receita (R$)",
                data: dadosReceita,
                borderColor: "#93C5FD",
                backgroundColor: gradLight,
                fill: true,
                tension: 0.42,
                borderWidth: 2,
                borderDash: [5, 4],
                pointRadius: 4,
                pointBackgroundColor: "#fff",
                pointBorderColor: "#93C5FD",
                pointBorderWidth: 2,
                pointHoverRadius: 7,
                pointHoverBackgroundColor: "#93C5FD",
                pointHoverBorderColor: "#fff",
                pointHoverBorderWidth: 2,
            });
            <?php endif; ?>

            new Chart(ctx, {
                type: "line",
                data: {
                    labels: ["Seg", "Ter", "Qua", "Qui", "Sex", "Sáb", "Dom"],
                    datasets: datasetsFluxo,
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
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 10
                            },
                            grid: {
                                color: colorGrid
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

        // ── Donut: Status das OS ─────────────────────────────────────────────────
        const donutEl = document.getElementById("graficoStatus");
        if (donutEl) {
            new Chart(donutEl, {
                type: "doughnut",
                data: {
                    labels: ["Concluídas", "Em andamento", "Pendentes", "Canceladas", "Abertas"],
                    datasets: [{
                        data: dadosStatus,
                        backgroundColor: ["#003BDD", "#60A5FA", "#BFDBFE", "#EF4444", "#3B82F6"],
                        borderWidth: 0,
                        spacing: 4,
                        hoverOffset: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: "72%",
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
                            callbacks: {
                                label: (ctx) => {
                                    const pct = totalOS ? Math.round((ctx.parsed / totalOS) * 100) : 0;
                                    return ` ${ctx.label}: ${ctx.parsed} (${pct}%)`;
                                },
                            },
                        },
                        centerText: {
                            text: String(totalOS),
                            subtext: "Total de OS"
                        },
                    },
                },
                plugins: [centerTextPlugin],
            });
        }

        // ── Barras: Faturamento por Mês ──────────────────────────────────────────
        const faturamentoMesEl = document.getElementById("graficoFaturamentoMes");
        if (faturamentoMesEl) {
            const ctxFaturamentoMes = faturamentoMesEl.getContext("2d");
            const gradFaturamentoMes = ctxFaturamentoMes.createLinearGradient(0, 0, 0, 320);
            gradFaturamentoMes.addColorStop(0, "#60A5FA");
            gradFaturamentoMes.addColorStop(1, "#003BDD");

            new Chart(faturamentoMesEl, {
                type: "bar",
                data: {
                    labels: labelsFaturamentoMes,
                    datasets: [{
                        label: "Faturamento",
                        data: dadosFaturamentoMes,
                        backgroundColor: gradFaturamentoMes,
                        borderRadius: 6,
                        borderSkipped: false,
                        maxBarThickness: 40,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: "#0F172A",
                            titleColor: "rgba(255,255,255,0.65)",
                            bodyColor: "#fff",
                            padding: { top: 10, bottom: 10, left: 14, right: 14 },
                            cornerRadius: 10,
                            callbacks: {
                                label: (ctx) => ' R$ ' + ctx.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 2 }),
                            },
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: colorGrid },
                            border: { display: false },
                            ticks: {
                                callback: (v) => 'R$ ' + v.toLocaleString('pt-BR'),
                            },
                        },
                        x: {
                            grid: { display: false },
                            border: { display: false },
                        },
                    },
                },
            });
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        setTimeout(initCharts, 50);
    });
</script>