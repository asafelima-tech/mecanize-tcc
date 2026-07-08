<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Variáveis esperadas do controller:
// $caixas       array — histórico de sessões de caixa (já filtrado por papel
//               em CaixaController::list — atendente só vê as próprias sessões)
// $caixaAberto  array|false — sessão atualmente aberta (se houver)
// $resumoAberto array|null  — resumoSessao() da sessão aberta (caixa atual etc.)

// Rótulos legíveis para as chaves de forma de pagamento / categoria do resumo.
$labelForma = [
    'DINHEIRO' => 'Dinheiro',
    'PIX'      => 'Pix',
    'CARTAO'   => 'Cartão',
    'BOLETO'   => 'Boleto',
];
$labelCategoria = [
    'PECAS' => 'Peças',
    'BANCO' => 'Banco',
    'OUTRO' => 'Outro',
];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Caixa</h1>
        <p class="page-header__subtitle">Histórico de aberturas e fechamentos de caixa.</p>
    </div>
    <div class="page-header__actions">
        <?php if (!empty($caixaAberto)): ?>
            <a href="./caixaMovimentacoes" class="btn btn--primary">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                Lançamentos
            </a>
            <a href="./fecharCaixa" class="btn btn--danger">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="7" width="20" height="14" rx="2" />
                    <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" />
                </svg>
                Fechar Caixa
            </a>
        <?php else: ?>
            <a href="./abrirCaixa" class="btn btn--primary">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                Abrir Caixa
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($caixaAberto)): ?>

    <?php
    $caixaAtualValor = (float) ($resumoAberto['caixa_atual'] ?? 0);
    ?>

    <!-- ── Cards de status da sessão aberta ── -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-card__head">
                <span class="stat-card__label">Status</span>
                <div class="stat-card__icon b1">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0 1 9.9-1" />
                    </svg>
                </div>
            </div>
            <div class="stat-card__value" style="font-size:22px;">Aberto</div>
            <span class="stat-card__delta neutral">
                Por <strong style="color:var(--color-text);font-weight:600;"><?= htmlspecialchars($caixaAberto['abertura_nome'] ?? '-') ?></strong>
                às <?= !empty($caixaAberto['data_abertura']) ? date('H:i', strtotime($caixaAberto['data_abertura'])) : '--' ?>
            </span>
        </div>

        <div class="stat-card">
            <div class="stat-card__head">
                <span class="stat-card__label">Caixa atual (dinheiro)</span>
                <div class="stat-card__icon b2">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="6" width="20" height="12" rx="2" />
                        <circle cx="12" cy="12" r="2" />
                        <path d="M6 12h.01M18 12h.01" />
                    </svg>
                </div>
            </div>
            <div class="stat-card__value" style="<?= $caixaAtualValor < 0 ? 'color:var(--color-danger);' : '' ?>">
                R$ <?= number_format($caixaAtualValor, 2, ',', '.') ?>
            </div>
            <span class="stat-card__delta neutral">
                Abertura R$ <?= number_format((float) ($resumoAberto['valor_abertura'] ?? $caixaAberto['valor_abertura'] ?? 0), 2, ',', '.') ?>
            </span>
        </div>

        <div class="stat-card">
            <div class="stat-card__head">
                <span class="stat-card__label">Entradas</span>
                <div class="stat-card__icon b3">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
                        <polyline points="17 6 23 6 23 12" />
                    </svg>
                </div>
            </div>
            <div class="stat-card__value">R$ <?= number_format((float) ($resumoAberto['total_entradas'] ?? 0), 2, ',', '.') ?></div>
            <span class="stat-card__delta">Nesta sessão</span>
        </div>

        <div class="stat-card">
            <div class="stat-card__head">
                <span class="stat-card__label">Saídas</span>
                <div class="stat-card__icon b4">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 18 13.5 8.5 8.5 13.5 1 6" />
                        <polyline points="17 18 23 18 23 12" />
                    </svg>
                </div>
            </div>
            <div class="stat-card__value">R$ <?= number_format((float) ($resumoAberto['total_saidas'] ?? 0), 2, ',', '.') ?></div>
            <span class="stat-card__delta neutral">Nesta sessão</span>
        </div>
    </div>

    <!-- ── Composição da sessão: entradas por forma / saídas por categoria ── -->
    <section class="card" style="margin-bottom:20px;">
        <div class="card__header">
            <span class="card__title">
                <span class="ico-wrap">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 3v18h18" />
                        <path d="M18 17V9M13 17V5M8 17v-3" />
                    </svg>
                </span>
                Composição da sessão
            </span>
            <span style="font-size:12px;color:var(--color-text-muted);">
                Aberta em <?= !empty($caixaAberto['data_abertura']) ? date('d/m/Y H:i', strtotime($caixaAberto['data_abertura'])) : '--' ?>
            </span>
        </div>
        <div class="card__body">
            <div class="grid-2">
                <div>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-muted);margin-bottom:4px;">
                        Entradas por forma
                    </div>
                    <?php foreach ($labelForma as $chave => $rotulo): ?>
                        <div class="kv-row">
                            <span><?= $rotulo ?></span>
                            <b>R$ <?= number_format((float) ($resumoAberto['entradas'][$chave] ?? 0), 2, ',', '.') ?></b>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-muted);margin-bottom:4px;">
                        Saídas por categoria
                    </div>
                    <?php foreach ($labelCategoria as $chave => $rotulo): ?>
                        <div class="kv-row">
                            <span><?= $rotulo ?></span>
                            <b>R$ <?= number_format((float) ($resumoAberto['saidas'][$chave] ?? 0), 2, ',', '.') ?></b>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

<?php else: ?>

    <!-- ── Nenhum caixa aberto ── -->
    <section class="card" style="margin-bottom:20px;">
        <div class="card__body" style="text-align:center;padding:48px 20px;">
            <div style="width:56px;height:56px;border-radius:50%;background:var(--color-bg);color:var(--color-text-muted);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
            </div>
            <div style="font-weight:700;font-size:15px;color:var(--color-text);margin-bottom:4px;">
                Nenhum caixa aberto
            </div>
            <p style="font-size:13px;color:var(--color-text-muted);margin-bottom:20px;">
                Abra o caixa para começar a registrar entradas e saídas do dia.
            </p>
            <a href="./abrirCaixa" class="btn btn--primary btn--sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                Abrir Caixa
            </a>
        </div>
    </section>

<?php endif; ?>

<!-- ── Histórico ── -->
<section class="card">
    <div class="card__header">
        <span class="card__title">
            <span class="ico-wrap">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 3v5h5" />
                    <path d="M3.05 13A9 9 0 1 0 6 5.3L3 8" />
                    <path d="M12 7v5l4 2" />
                </svg>
            </span>
            Histórico de sessões
        </span>
        <span class="badge badge--neutral"><?= count($caixas ?? []) ?> sessõe(s)</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Status</th>
                    <th>Aberto por</th>
                    <th>Abertura</th>
                    <th>Valor abertura</th>
                    <th>Fechado por</th>
                    <th>Fechamento</th>
                    <th>Valor fechamento</th>
                    <th>Diferença</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($caixas)): ?>
                    <?php foreach ($caixas as $c):
                        $aberto   = ($c['status'] ?? '') === 'ABERTO';
                        $vAbert   = (float) ($c['valor_abertura'] ?? 0);
                        $vFech    = $c['valor_fechamento'];
                        $temFech  = $vFech !== null;
                        $dif      = $temFech ? ((float) $vFech - $vAbert) : null;
                    ?>
                        <tr>
                            <td class="id-col">#<?= htmlspecialchars($c['id_caixa']) ?></td>
                            <td>
                                <?php if ($aberto): ?>
                                    <span class="badge badge--success"><span class="badge__dot"></span>Aberto</span>
                                <?php else: ?>
                                    <span class="badge badge--secondary"><span class="badge__dot"></span>Fechado</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($c['abertura_nome'] ?? '-') ?></td>
                            <td class="text-muted">
                                <?= !empty($c['data_abertura']) ? date('d/m/Y H:i', strtotime($c['data_abertura'])) : '--' ?>
                            </td>
                            <td style="font-weight:500;">R$ <?= number_format($vAbert, 2, ',', '.') ?></td>
                            <td><?= !empty($c['fechamento_nome']) ? htmlspecialchars($c['fechamento_nome']) : '—' ?></td>
                            <td class="text-muted">
                                <?= !empty($c['data_fechamento']) ? date('d/m/Y H:i', strtotime($c['data_fechamento'])) : '—' ?>
                            </td>
                            <td style="font-weight:500;">
                                <?= $temFech ? 'R$ ' . number_format((float) $vFech, 2, ',', '.') : '—' ?>
                            </td>
                            <td style="font-weight:600;color:<?= $dif === null ? 'var(--color-text-muted)' : ($dif < 0 ? 'var(--color-danger)' : ($dif > 0 ? 'var(--color-success)' : 'var(--color-text)')) ?>;">
                                <?php if ($dif === null): ?>
                                    —
                                <?php else: ?>
                                    <?= ($dif > 0 ? '+ ' : ($dif < 0 ? '− ' : '')) ?>R$ <?= number_format(abs($dif), 2, ',', '.') ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">
                            Nenhuma sessão de caixa registrada ainda.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
