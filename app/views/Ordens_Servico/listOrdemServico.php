<?php
// Variáveis esperadas do controller:
// $ordensAbertas, $ordens, $ordensConcluidas, $ordensArquivadas

// Excluir uma OS é DELETE definitivo, não desativação — restrito a ADMIN no
// OrdemServicoController::delete(). Esconder o botão aqui é só coerência
// visual; o que de fato impede a ação é o requireRole() lá.
$podeExcluir = ($_SESSION['user_tipo'] ?? '') === 'ADMIN';

// Retorno das ações de exclusão (OrdemServicoController::delete)
$flashErro = [
    'vinculos' => 'Esta ordem de serviço não pode ser excluída porque está vinculada a outros registros'
        . (!empty($_GET['detalhe']) ? ' (' . $_GET['detalhe'] . ')' : '')
        . '. Remova o vínculo antes de excluí-la.',
    'exclusao' => 'Não foi possível excluir esta ordem de serviço.',
][$_GET['erro'] ?? ''] ?? null;

$flashOk = [
    'excluido'    => 'Ordem de serviço excluída.',
    'conferida'   => 'OS conferida e liberada para retirada. A cobrança foi gerada.',
    'jaconferida' => 'Esta OS já tinha sido conferida.',
    'devolvida'   => 'OS devolvida à execução.',
    'entregue'    => 'Veículo entregue ao cliente.',
    'jaentregue'  => 'Esta OS já tinha sido entregue.',
    'cancelada'   => 'OS cancelada.',
][$_GET['ok'] ?? ''] ?? null;

// Erro vindo do fluxo (OrdemServicoFluxo): o texto explica o que impediu a etapa.
if (($_GET['erro'] ?? '') === 'fluxo') {
    $flashErro = $_GET['detalhe'] ?? 'Não foi possível concluir a etapa.';
}
?>

<?php if ($flashErro !== null): ?>
    <?php $alertaTipo = 'erro'; $alertaMensagem = $flashErro;
          require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>
<?php if ($flashOk !== null): ?>
    <?php $alertaTipo = 'sucesso'; $alertaMensagem = $flashOk;
          require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<!-- ── Page Header ─────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Ordens de Serviço</h1>
        <p class="page-header__subtitle">Gerencie todas as ordens de serviço da oficina.</p>
    </div>
    <div class="page-header__actions">
        <a href="./kanbanOrdemServico" class="btn btn--ghost">
            <i class="bi bi-kanban"></i>
            Ver como Kanban
        </a>
        <a href="./saveOrdemServico" class="btn btn--primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Nova Ordem de Serviço
        </a>
    </div>
</div>

<!-- ── Main Card ──────────────────────────────────────────── -->
<section class="card">

    <div class="tabs">
        <button class="tabs__item is-active" data-tab="abertas">OS Abertas</button>
        <button class="tabs__item" data-tab="ativas">OS Em Andamento</button>
        <button class="tabs__item" data-tab="prontas">Conferência e Retirada</button>
        <button class="tabs__item" data-tab="concluidas">OS Concluídas</button>
        <button class="tabs__item" data-tab="arquivadas">OS Arquivadas</button>
    </div>

    <!-- ════ PANEL: OS ABERTAS ════ -->
    <div class="tab-panel is-active" id="panel-abertas">
        <div class="table-toolbar">
            <div class="search-wrap">
                <input type="text" id="busca-abertas"
                    placeholder="Buscar por cliente, OS ou placa..."
                    oninput="filtrarTabela('abertas')">
            </div>
            <input type="date" id="filtro-data-abertas" class="toolbar-select" onchange="filtrarTabela('abertas')">
            <span class="text-muted fs-12" id="contador-abertas"><?= count($ordensAbertas ?? []) ?> resultado(s)</span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID da OS</th>
                        <th>Cliente</th>
                        <th>Veículo</th>
                        <th>Data de Entrada</th>
                        <th>Valor</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="tbody-abertas">
                    <?php if (!empty($ordensAbertas)): ?>
                        <?php foreach ($ordensAbertas as $os):
                            $id = $os['id_ordem_servico'] ?? $os['id'];
                        ?>
                            <tr>
                                <td class="id-col">#<?= htmlspecialchars($os['numero'] ?? $id) ?></td>
                                <td><?= htmlspecialchars($os['cliente'] ?? '-') ?></td>
                                <td>
                                    <?= htmlspecialchars($os['veiculo'] ?? '-') ?>
                                    <?php if (!empty($os['placa'])): ?>
                                        <div class="text-muted fs-12"><?= htmlspecialchars($os['placa']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= !empty($os['data_abertura']) ? date('d/m/Y · H:i', strtotime($os['data_abertura'])) : '-' ?></td>
                                <td>R$ <?= number_format($os['valor_total_os'] ?? 0, 2, ',', '.') ?></td>
                                <td>
                                    <div class="row-actions">
                                        <a href="./viewOrdemServico&id=<?= $id ?>" class="row-action-btn primary" title="Visualizar">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        </a>
                                        <a href="./editOrdemServico&id=<?= $id ?>" class="row-action-btn" title="Editar">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                            </svg>
                                        </a>
                                        <?php if ($podeExcluir): ?>
                                        <form method="post" action="./deleteOrdemServico" style="display:inline;"
                                            onsubmit="return confirm('Excluir esta Ordem de Serviço? Os produtos, serviços e mão de obra lançados nela são apagados junto, em definitivo.')">
                                            <input type="hidden" name="id_ordem_servico" value="<?= $id ?>">
                                            <button type="submit" class="row-action-btn danger" title="Excluir">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="3 6 5 6 21 6" />
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                                </svg>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">Nenhuma ordem aberta encontrada.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- fim panel-abertas -->

    <!-- ════ PANEL: EM ANDAMENTO ════ -->
    <div class="tab-panel" id="panel-ativas">
        <div class="table-toolbar">
            <div class="search-wrap">
                <input type="text" id="busca-ativas"
                    placeholder="Buscar por cliente, OS ou placa..."
                    oninput="filtrarTabela('ativas')">
            </div>
            <select id="filtro-status-ativas" class="toolbar-select" onchange="filtrarTabela('ativas')">
                <option value="">Status: Todos</option>
                <option value="em andamento">Em andamento</option>
                <option value="aguardando peça">Aguardando peça</option>
            </select>
            <input type="date" id="filtro-data-ativas" class="toolbar-select" onchange="filtrarTabela('ativas')">
            <span class="text-muted fs-12" id="contador-ativas"><?= count($ordens ?? []) ?> resultado(s)</span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID da OS</th>
                        <th>Cliente</th>
                        <th>Veículo</th>
                        <th>Status</th>
                        <th>Data de Entrada</th>
                        <th>Valor</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="tbody-ativas">
                    <?php if (!empty($ordens)): ?>
                        <?php foreach ($ordens as $os):
                            $status = $os['status'] ?? 'EM_ANDAMENTO';
                            $badgeClass  = $status === 'AGUARDANDO_PECA' ? 'badge--warning' : 'badge--info';
                            $statusTexto = $status === 'AGUARDANDO_PECA' ? 'Aguardando peça' : 'Em andamento';
                            $id = $os['id_ordem_servico'] ?? $os['id'];
                        ?>
                            <tr>
                                <td class="id-col">#<?= htmlspecialchars($os['numero'] ?? $id) ?></td>
                                <td><?= htmlspecialchars($os['cliente'] ?? '-') ?></td>
                                <td>
                                    <?= htmlspecialchars($os['veiculo'] ?? '-') ?>
                                    <?php if (!empty($os['placa'])): ?>
                                        <div class="text-muted fs-12"><?= htmlspecialchars($os['placa']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $badgeClass ?>">
                                        <span class="badge__dot"></span><?= $statusTexto ?>
                                    </span>
                                </td>
                                <td><?= !empty($os['data_abertura']) ? date('d/m/Y · H:i', strtotime($os['data_abertura'])) : '-' ?></td>
                                <td>R$ <?= number_format($os['valor_total_os'] ?? 0, 2, ',', '.') ?></td>
                                <td>
                                    <div class="row-actions">
                                        <a href="./viewOrdemServico&id=<?= $id ?>" class="row-action-btn primary" title="Visualizar">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        </a>
                                        <a href="./editOrdemServico&id=<?= $id ?>" class="row-action-btn" title="Editar">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                            </svg>
                                        </a>
                                        <?php if ($podeExcluir): ?>
                                        <form method="post" action="./deleteOrdemServico" style="display:inline;"
                                            onsubmit="return confirm('Excluir esta Ordem de Serviço? Os produtos, serviços e mão de obra lançados nela são apagados junto, em definitivo.')">
                                            <input type="hidden" name="id_ordem_servico" value="<?= $id ?>">
                                            <button type="submit" class="row-action-btn danger" title="Excluir">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="3 6 5 6 21 6" />
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                                </svg>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">Nenhuma ordem em andamento encontrada.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- fim panel-ativas -->

    <!-- ════ PANEL: OS PRONTAS PARA ENTREGA ════ -->
    <div class="tab-panel" id="panel-prontas">
        <div class="table-toolbar">
            <div class="search-wrap">
                <input type="text" id="busca-prontas"
                    placeholder="Buscar por cliente, OS ou placa..."
                    oninput="filtrarTabela('prontas')">
            </div>
            <input type="date" id="filtro-data-prontas" class="toolbar-select" onchange="filtrarTabela('prontas')">
            <span class="text-muted fs-12" id="contador-prontas"><?= count($ordensProntas ?? []) ?> resultado(s)</span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID da OS</th>
                        <th>Cliente</th>
                        <th>Veículo</th>
                        <th>Status</th>
                        <th>Data de Entrada</th>
                        <th>Valor</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="tbody-prontas">
                    <?php if (!empty($ordensProntas)): ?>
                        <?php foreach ($ordensProntas as $os):
                            $id = $os['id_ordem_servico'] ?? $os['id'];
                        ?>
                            <tr>
                                <td class="id-col">#<?= htmlspecialchars($os['numero'] ?? $id) ?></td>
                                <td><?= htmlspecialchars($os['cliente'] ?? '-') ?></td>
                                <td>
                                    <?= htmlspecialchars($os['veiculo'] ?? '-') ?>
                                    <?php if (!empty($os['placa'])): ?>
                                        <div class="text-muted fs-12"><?= htmlspecialchars($os['placa']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= OrdemServico::classeBadgeStatus($os['status'] ?? '') ?>">
                                        <span class="badge__dot"></span><?= OrdemServico::rotuloStatus($os['status'] ?? '') ?>
                                    </span>
                                </td>
                                <td><?= !empty($os['data_abertura']) ? date('d/m/Y · H:i', strtotime($os['data_abertura'])) : '-' ?></td>
                                <td>R$ <?= number_format($os['valor_total_os'] ?? 0, 2, ',', '.') ?></td>
                                <td>
                                    <div class="row-actions">
                                        <a href="./viewOrdemServico&id=<?= $id ?>" class="row-action-btn primary" title="Visualizar">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        </a>
                                        <?php if (($os['status'] ?? '') === 'AGUARDANDO_CONFERENCIA' && $podeExcluir): ?>
                                            <form action="./conferirOrdemServico" method="POST" style="display:inline;"
                                                  onsubmit="return confirm('Conferir esta OS e liberar o veículo para retirada? Isso calcula as comissões e gera a cobrança.')">
                                                <input type="hidden" name="id_ordem_servico" value="<?= (int) $id ?>">
                                                <button type="submit" class="row-action-btn success" title="Conferir e liberar para retirada">
                                                    <i class="bi bi-patch-check"></i>
                                                </button>
                                            </form>
                                            <button type="button" class="row-action-btn" title="Devolver à execução"
                                                data-id="<?= (int) $id ?>" data-numero="<?= htmlspecialchars($os['numero'] ?? $id) ?>"
                                                onclick="abrirModalMotivo('devolver', this.dataset.id, this.dataset.numero)">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        <?php elseif (($os['status'] ?? '') === 'AGUARDANDO_RETIRADA'): ?>
                                            <button type="button" class="row-action-btn success" title="Entregar ao cliente"
                                                data-id="<?= (int) $id ?>" data-numero="<?= htmlspecialchars($os['numero'] ?? $id) ?>"
                                                onclick="abrirModalEntrega(this.dataset.id, this.dataset.numero)">
                                                <i class="bi bi-box-arrow-right"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted fs-12" title="Só o administrador confere e libera">Aguarda o administrador</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">Nenhuma ordem aguardando conferência ou retirada.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- fim panel-prontas -->

    <!-- ════ PANEL: CONCLUÍDAS ════ -->
    <div class="tab-panel" id="panel-concluidas">
        <div class="table-toolbar">
            <div class="search-wrap">
                <input type="text" id="busca-concluidas"
                    placeholder="Buscar por cliente, OS ou placa..."
                    oninput="filtrarTabela('concluidas')">
            </div>
            <input type="date" id="filtro-data-concluidas" class="toolbar-select" onchange="filtrarTabela('concluidas')">
            <span class="text-muted fs-12" id="contador-concluidas"><?= count($ordensConcluidas ?? []) ?> resultado(s)</span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID da OS</th>
                        <th>Cliente</th>
                        <th>Veículo</th>
                        <th>Data de Entrada</th>
                        <th>Data de Conclusão</th>
                        <th>Valor</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="tbody-concluidas">
                    <?php if (!empty($ordensConcluidas)): ?>
                        <?php foreach ($ordensConcluidas as $os):
                            $id = $os['id_ordem_servico'] ?? $os['id'];
                        ?>
                            <tr>
                                <td class="id-col">#<?= htmlspecialchars($os['numero'] ?? $id) ?></td>
                                <td><?= htmlspecialchars($os['cliente'] ?? '-') ?></td>
                                <td>
                                    <?= htmlspecialchars($os['veiculo'] ?? '-') ?>
                                    <?php if (!empty($os['placa'])): ?>
                                        <div class="text-muted fs-12"><?= htmlspecialchars($os['placa']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= !empty($os['data_abertura'])  ? date('d/m/Y', strtotime($os['data_abertura']))  : '-' ?></td>
                                <td><?= !empty($os['data_conclusao']) ? date('d/m/Y', strtotime($os['data_conclusao'])) : '-' ?></td>
                                <td>R$ <?= number_format($os['valor_total_os'] ?? 0, 2, ',', '.') ?></td>
                                <td>
                                    <div class="row-actions">
                                        <a href="./viewOrdemServico&id=<?= $id ?>" class="row-action-btn primary" title="Visualizar">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        </a>
                                        <a href="./editOrdemServico&id=<?= $id ?>" class="row-action-btn" title="Editar">
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
                            <td colspan="7" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">Nenhuma ordem concluída encontrada.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- fim panel-concluidas -->

    <!-- ════ PANEL: ARQUIVADAS ════ -->
    <div class="tab-panel" id="panel-arquivadas">
        <div class="table-toolbar">
            <div class="search-wrap">
                <input type="text" id="busca-arquivadas"
                    placeholder="Buscar por cliente, OS ou placa..."
                    oninput="filtrarTabela('arquivadas')">
            </div>
            <input type="date" id="filtro-data-arquivadas" class="toolbar-select" onchange="filtrarTabela('arquivadas')">
            <span class="text-muted fs-12" id="contador-arquivadas"><?= count($ordensArquivadas ?? []) ?> resultado(s)</span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID da OS</th>
                        <th>Cliente</th>
                        <th>Veículo</th>
                        <th>Data de Entrada</th>
                        <th>Data de Conclusão</th>
                        <th>Valor</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="tbody-arquivadas">
                    <?php if (!empty($ordensArquivadas)): ?>
                        <?php foreach ($ordensArquivadas as $os):
                            $id = $os['id_ordem_servico'] ?? $os['id'];
                        ?>
                            <tr>
                                <td class="id-col">#<?= htmlspecialchars($os['numero'] ?? $id) ?></td>
                                <td><?= htmlspecialchars($os['cliente'] ?? '-') ?></td>
                                <td>
                                    <?= htmlspecialchars($os['veiculo'] ?? '-') ?>
                                    <?php if (!empty($os['placa'])): ?>
                                        <div class="text-muted fs-12"><?= htmlspecialchars($os['placa']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= !empty($os['data_abertura'])  ? date('d/m/Y', strtotime($os['data_abertura']))  : '-' ?></td>
                                <td><?= !empty($os['data_conclusao']) ? date('d/m/Y', strtotime($os['data_conclusao'])) : '-' ?></td>
                                <td>R$ <?= number_format($os['valor_total_os'] ?? 0, 2, ',', '.') ?></td>
                                <td>
                                    <div class="row-actions">
                                        <a href="./viewOrdemServico&id=<?= $id ?>" class="row-action-btn primary" title="Visualizar">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">Nenhuma ordem arquivada encontrada.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- fim panel-arquivadas -->

</section>

<?php
$fluxoVoltar = 'lista';
$fluxoOs = ['id' => 0, 'numero' => ''];
require __DIR__ . '/_modaisFluxo.php';
?>

<script>

    document.querySelectorAll('.tabs__item[data-tab]').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.tabs__item[data-tab]').forEach(t => t.classList.remove('is-active'));
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('is-active'));
            tab.classList.add('is-active');
            document.getElementById('panel-' + tab.dataset.tab).classList.add('is-active');
        });
    });

    function filtrarTabela(painel) {
        const busca = (document.getElementById('busca-' + painel)?.value ?? '').toLowerCase();
        const status = (document.getElementById('filtro-status-' + painel)?.value ?? '').toLowerCase();
        const data = document.getElementById('filtro-data-' + painel)?.value ?? '';

        const linhas = document.querySelectorAll('#tbody-' + painel + ' tr');
        let visiveis = 0;

        linhas.forEach(tr => {
            const texto = tr.innerText.toLowerCase();
            const tdStatus = tr.querySelector('td:nth-child(4)')?.innerText.toLowerCase() ?? '';
            const tdData = tr.querySelector('td:nth-child(5)')?.innerText ?? '';

            const partes = tdData.trim().split(/[\s·]+/)[0].split('/');
            const dataLinha = partes.length === 3 ? `${partes[2]}-${partes[1]}-${partes[0]}` : '';

            const ok = (!busca || texto.includes(busca)) &&
                (!status || tdStatus.includes(status)) &&
                (!data || dataLinha === data);

            tr.style.display = ok ? '' : 'none';
            if (ok) visiveis++;
        });

        const el = document.getElementById('contador-' + painel);
        if (el) el.textContent = visiveis + ' resultado(s)';
    }
</script>