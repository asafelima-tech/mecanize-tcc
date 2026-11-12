<?php
// Variáveis do controller (AgendamentoController::list):
// $periodo      array — tipo, ref, inicio, fim, rotulo, anterior, proximo
// $agendamentos array — já ordenados por data_hora
// $resumo       array — contagem por status + TOTAL
// $mecanicos    array — para o filtro
// $filtros      array — status/mecanico_id aplicados
// $ok / $erro   string — flash da última ação

$statusInfo = [
    'AGENDADO'   => ['label' => 'Agendado',        'classe' => 'badge--info'],
    'CONFIRMADO' => ['label' => 'Confirmado',      'classe' => 'badge--success'],
    'CONCLUIDO'  => ['label' => 'Concluído',       'classe' => 'badge--secondary'],
    'CANCELADO'  => ['label' => 'Cancelado',       'classe' => 'badge--danger'],
    'FALTOU'     => ['label' => 'Não compareceu',  'classe' => 'badge--warning'],
];

$flashOk = [
    'criado'   => 'Agendamento criado com sucesso.',
    'editado'  => 'Agendamento atualizado.',
    'status'   => 'Status atualizado.',
    'excluido' => 'Agendamento excluído.',
];
$flashErro = [
    'status'   => 'Não foi possível atualizar o status.',
    'excluido' => 'Não foi possível excluir o agendamento.',
    'os'       => 'Agendamento não encontrado.',
];

// Agrupa por dia para a agenda ficar legível em qualquer período.
$porDia = [];
foreach ($agendamentos as $a) {
    $porDia[date('Y-m-d', strtotime($a['data_hora']))][] = $a;
}

$diasSemana = [1 => 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
$hoje       = date('Y-m-d');

$linkPeriodo = fn($tipo, $data) => './listAgendamentos?' . http_build_query(
    array_filter([
        'periodo'     => $tipo,
        'data'        => $data,
        'status'      => $filtros['status'] ?? null,
        'mecanico_id' => $filtros['mecanico_id'] ?? null,
    ])
);

$ehAdmin = ($_SESSION['user_tipo'] ?? null) === 'ADMIN';
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Agenda</h1>
        <p class="page-header__subtitle">Horários marcados — <?= htmlspecialchars($periodo['rotulo']) ?>.</p>
    </div>
    <div class="page-header__actions">
        <a href="./saveAgendamento?data=<?= htmlspecialchars($periodo['inicio']) ?>" class="btn btn--primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Novo agendamento
        </a>
    </div>
</div>

<?php if (!empty($ok) && isset($flashOk[$ok])): ?>
    <?php $alertaTipo = 'sucesso'; $alertaMensagem = $flashOk[$ok];
                  require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>
<?php if (!empty($erro) && isset($flashErro[$erro])): ?>
    <?php $alertaTipo = 'erro'; $alertaMensagem = $flashErro[$erro];
                  require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<!-- ── Navegação e filtros ── -->
<section class="card" style="margin-bottom:20px;">
    <div class="card__body" style="padding:16px 20px;display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:space-between;">

        <div style="display:flex;align-items:center;gap:10px;">
            <a href="<?= $linkPeriodo($periodo['tipo'], $periodo['anterior']) ?>" class="btn btn--ghost btn--sm" title="Anterior">‹</a>
            <a href="<?= $linkPeriodo($periodo['tipo'], $hoje) ?>" class="btn btn--ghost btn--sm">Hoje</a>
            <a href="<?= $linkPeriodo($periodo['tipo'], $periodo['proximo']) ?>" class="btn btn--ghost btn--sm" title="Próximo">›</a>

            <div class="periodo-tabs" style="margin-left:8px;">
                <?php foreach (['dia' => 'Dia', 'semana' => 'Semana', 'mes' => 'Mês'] as $chave => $rotulo): ?>
                    <a href="<?= $linkPeriodo($chave, $periodo['ref']) ?>"
                       class="periodo-tab<?= $periodo['tipo'] === $chave ? ' periodo-tab--ativa' : '' ?>"><?= $rotulo ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <form method="GET" action="./listAgendamentos" style="display:flex;align-items:center;gap:8px;">
            <input type="hidden" name="periodo" value="<?= htmlspecialchars($periodo['tipo']) ?>">
            <input type="hidden" name="data" value="<?= htmlspecialchars($periodo['ref']) ?>">

            <select name="status" class="toolbar-select toolbar-select--sm" data-no-ts>
                <option value="">Todos os status</option>
                <?php foreach ($statusInfo as $chave => $info): ?>
                    <option value="<?= $chave ?>" <?= ($filtros['status'] ?? '') === $chave ? 'selected' : '' ?>>
                        <?= $info['label'] ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="mecanico_id" class="toolbar-select toolbar-select--sm" data-no-ts>
                <option value="">Todos os mecânicos</option>
                <?php foreach ($mecanicos as $m): ?>
                    <option value="<?= (int) $m['id_usuario'] ?>"
                        <?= (string) ($filtros['mecanico_id'] ?? '') === (string) $m['id_usuario'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn--ghost btn--sm">Filtrar</button>
        </form>
    </div>
</section>

<!-- ── Contadores do período ── -->
<section class="stat-grid" style="margin-bottom:24px;">
    <?php
    // Um glifo por card, como no resto do sistema: os tons b1–b4 são pastéis
    // muito próximos e não distinguem nada sozinhos — os quatro cards ficavam
    // com o mesmo calendário e só o fundo mudava de matiz.
    $cards = [
        [
            'label' => 'Total no período',
            'valor' => $resumo['TOTAL'],
            'ico'   => 'b1',
            'svg'   => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>'
                     . '<line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        ],
        [
            'label' => 'Confirmados',
            'valor' => $resumo['CONFIRMADO'],
            'ico'   => 'b2',
            'svg'   => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        ],
        [
            'label' => 'Aguardando confirmação',
            'valor' => $resumo['AGENDADO'],
            'ico'   => 'b3',
            'svg'   => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        ],
        [
            'label' => 'Cancelados / faltas',
            'valor' => $resumo['CANCELADO'] + $resumo['FALTOU'],
            'ico'   => 'b4',
            'svg'   => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/>'
                     . '<line x1="9" y1="9" x2="15" y2="15"/>',
        ],
    ];
    foreach ($cards as $card): ?>
        <div class="stat-card">
            <div class="stat-card__head">
                <div class="stat-card__label"><?= $card['label'] ?></div>
                <div class="stat-card__icon <?= $card['ico'] ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $card['svg'] ?></svg>
                </div>
            </div>
            <div class="stat-card__value"><?= (int) $card['valor'] ?></div>
        </div>
    <?php endforeach; ?>
</section>

<!-- ── Agenda ── -->
<?php if (empty($porDia)): ?>
    <section class="card">
        <div class="card__body" style="text-align:center;padding:60px 20px;color:var(--color-text-muted);">
            <p style="margin-bottom:16px;">Nenhum agendamento neste período.</p>
            <a href="./saveAgendamento?data=<?= htmlspecialchars($periodo['inicio']) ?>" class="btn btn--primary">Criar o primeiro</a>
        </div>
    </section>
<?php else: ?>
    <?php foreach ($porDia as $dia => $doDia): ?>
        <section class="card" style="margin-bottom:16px;">
            <div class="card__header">
                <div class="card__title">
                    <?= $diasSemana[(int) date('N', strtotime($dia))] ?>, <?= date('d/m/Y', strtotime($dia)) ?>
                    <?php if ($dia === $hoje): ?>
                        <span class="badge badge--success" style="margin-left:8px;"><span class="badge__dot"></span>Hoje</span>
                    <?php endif; ?>
                </div>
                <span class="fs-12 text-secondary"><?= count($doDia) ?> agendamento(s)</span>
            </div>

            <div class="card__body" style="padding:0;">
                <?php foreach ($doDia as $a):
                    $info    = $statusInfo[$a['status']] ?? ['label' => $a['status'], 'classe' => 'badge--secondary'];
                    $inicio  = strtotime($a['data_hora']);
                    $fim     = $inicio + ((int) $a['duracao_min'] * 60);
                    $veiculo = trim(($a['veiculo_marca'] ?? '') . ' ' . ($a['veiculo_modelo'] ?? ''));
                    $ativo   = in_array($a['status'], ['AGENDADO', 'CONFIRMADO'], true);
                ?>
                    <div class="agenda-item">
                        <div class="agenda-item__hora">
                            <strong><?= date('H:i', $inicio) ?></strong>
                            <span>até <?= date('H:i', $fim) ?></span>
                        </div>

                        <div class="agenda-item__info">
                            <div class="agenda-item__servico"><?= htmlspecialchars($a['servico']) ?></div>
                            <div class="agenda-item__meta">
                                <?= htmlspecialchars($a['cliente_nome']) ?>
                                <?php if (!empty($a['veiculo_placa'])): ?>
                                    · <?= htmlspecialchars(strtoupper($a['veiculo_placa'])) ?>
                                    <?= $veiculo !== '' ? '(' . htmlspecialchars($veiculo) . ')' : '' ?>
                                <?php endif; ?>
                                <?php if (!empty($a['mecanico_nome'])): ?>
                                    · <?= htmlspecialchars($a['mecanico_nome']) ?>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($a['observacoes'])): ?>
                                <div class="agenda-item__obs"><?= htmlspecialchars($a['observacoes']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="agenda-item__status">
                            <span class="badge <?= $info['classe'] ?>"><span class="badge__dot"></span><?= $info['label'] ?></span>
                            <?php if (!empty($a['ordem_servico_id'])): ?>
                                <a href="./viewOrdemServico?id=<?= (int) $a['ordem_servico_id'] ?>" class="fs-12 text-secondary" style="display:block;margin-top:4px;">
                                    OS <?= htmlspecialchars($a['os_numero'] ?? '') ?>
                                </a>
                            <?php endif; ?>
                        </div>

                        <div class="agenda-item__acoes">
                            <?php if ($ativo): ?>
                                <?php if ($a['status'] === 'AGENDADO'): ?>
                                    <form action="./statusAgendamento" method="POST" class="agenda-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id_agendamento" value="<?= (int) $a['id_agendamento'] ?>">
                                        <input type="hidden" name="status" value="CONFIRMADO">
                                        <input type="hidden" name="periodo" value="<?= htmlspecialchars($periodo['tipo']) ?>">
                                        <input type="hidden" name="data_ref" value="<?= htmlspecialchars($periodo['ref']) ?>">
                                        <button type="submit" class="btn btn--success btn--sm">Confirmar</button>
                                    </form>
                                <?php endif; ?>

                                <?php if (empty($a['ordem_servico_id'])): ?>
                                    <form action="./gerarOsAgendamento" method="POST" class="agenda-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id_agendamento" value="<?= (int) $a['id_agendamento'] ?>">
                                        <button type="submit" class="btn btn--ghost btn--sm">Gerar OS</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>

                            <a href="./editAgendamento?id=<?= (int) $a['id_agendamento'] ?>" class="btn btn--ghost btn--sm">Editar</a>

                            <?php if ($ativo): ?>
                                <form action="./statusAgendamento" method="POST" class="agenda-form"
                                      onsubmit="return confirm('Cancelar este agendamento?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id_agendamento" value="<?= (int) $a['id_agendamento'] ?>">
                                    <input type="hidden" name="status" value="CANCELADO">
                                    <input type="hidden" name="periodo" value="<?= htmlspecialchars($periodo['tipo']) ?>">
                                    <input type="hidden" name="data_ref" value="<?= htmlspecialchars($periodo['ref']) ?>">
                                    <button type="submit" class="btn btn--danger btn--sm">Cancelar</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($ehAdmin): ?>
                                <form action="./deleteAgendamento" method="POST" class="agenda-form"
                                      onsubmit="return confirm('Excluir este agendamento em definitivo?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id_agendamento" value="<?= (int) $a['id_agendamento'] ?>">
                                    <input type="hidden" name="periodo" value="<?= htmlspecialchars($periodo['tipo']) ?>">
                                    <input type="hidden" name="data_ref" value="<?= htmlspecialchars($periodo['ref']) ?>">
                                    <button type="submit" class="btn btn--ghost btn--sm" title="Excluir">✕</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<style>
    .periodo-tabs {
        display: inline-flex;
        gap: 4px;
        background: var(--color-bg-subtle);
        border-radius: var(--radius-md);
        padding: 4px;
    }

    .periodo-tab {
        padding: 6px 14px;
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

    .agenda-item {
        display: grid;
        grid-template-columns: 92px minmax(0, 1fr) auto auto;
        gap: 16px;
        align-items: center;
        padding: 14px 20px;
        border-bottom: 1px solid var(--color-border);
    }

    .agenda-item:last-child { border-bottom: none; }

    .agenda-item__hora {
        display: flex;
        flex-direction: column;
        line-height: 1.3;
    }

    .agenda-item__hora strong { font-size: 15px; color: var(--color-text); }
    .agenda-item__hora span   { font-size: 11px; color: var(--color-text-muted); }

    .agenda-item__servico {
        font-size: 14px;
        font-weight: 600;
        color: var(--color-text);
        margin-bottom: 2px;
    }

    .agenda-item__meta { font-size: 12px; color: var(--color-text-muted); }

    .agenda-item__obs {
        font-size: 12px;
        color: var(--color-text-muted);
        font-style: italic;
        margin-top: 3px;
    }

    .agenda-item__acoes {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    /* os botões de ação são forms independentes; sem isso cada um vira bloco */
    .agenda-form { display: inline; margin: 0; }

    @media (max-width: 860px) {
        .agenda-item {
            grid-template-columns: 80px minmax(0, 1fr);
            row-gap: 10px;
        }

        .agenda-item__status,
        .agenda-item__acoes {
            grid-column: 1 / -1;
            justify-content: flex-start;
        }
    }
</style>
