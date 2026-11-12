<?php
/**
 * Ocorrências: o relato do cliente sobre um veículo, da chegada à OS.
 *
 * Espera: $ocorrencias, $contagem (status => n), $aba, $busca, $prioridade.
 */
require_once __DIR__ . '/../../models/OcorrenciaFluxo.php';
require_once __DIR__ . '/../../models/Ocorrencia.php';

$contagem = $contagem ?? [];
$n = fn(string $status) => (int) ($contagem[$status] ?? 0);
$abertas = array_sum(array_map($n, OcorrenciaFluxo::ABERTAS));

$abas = [
    'abertas'              => ['Abertas', $abertas],
    'AGUARDANDO_AVALIACAO' => ['Aguardando avaliação', $n('AGUARDANDO_AVALIACAO')],
    'EM_AVALIACAO'         => ['Em avaliação', $n('EM_AVALIACAO')],
    'AGUARDANDO_ORCAMENTO' => ['Aguardando orçamento', $n('AGUARDANDO_ORCAMENTO')],
    'AGUARDANDO_APROVACAO' => ['Aguardando aprovação', $n('AGUARDANDO_APROVACAO')],
    'EM_ESPERA'            => ['Em espera', $n('EM_ESPERA')],
    'EM_EXECUCAO'          => ['Em execução', $n('EM_EXECUCAO')],
    'ENCERRADA'            => ['Encerradas', $n('ENCERRADA')],
    'todas'                => ['Todas', array_sum($contagem)],
];

$badgePrioridade = [
    'URGENTE' => 'badge--danger',
    'ALTA'    => 'badge--warning',
    'NORMAL'  => 'badge--neutral',
    'BAIXA'   => 'badge--neutral',
];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Atendimento</h1>
        <p class="page-header__subtitle">Do veículo que chega à ordem de serviço: avaliação, orçamento e autorização.</p>
    </div>
    <div class="page-header__actions">
        <a href="./filaOperacional" class="btn btn--ghost"><i class="bi bi-list-ol"></i> Fila</a>
        <a href="./saveOcorrencia?modo=rapido" class="btn btn--ghost"><i class="bi bi-lightning-charge"></i> Atendimento rápido</a>
        <a href="./saveOcorrencia" class="btn btn--primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
            Registrar chegada
        </a>
    </div>
</div>

<section class="stat-grid">
    <?php foreach ([
        ['Aguardando avaliação', $n('AGUARDANDO_AVALIACAO'), 'b1'],
        ['Em avaliação', $n('EM_AVALIACAO'), 'b2'],
        ['Aguardando orçamento', $n('AGUARDANDO_ORCAMENTO'), 'b3'],
        ['Aguardando aprovação', $n('AGUARDANDO_APROVACAO'), 'b4'],
    ] as [$rotulo, $valor, $cor]): ?>
        <div class="stat-card">
            <div class="stat-card__head">
                <div class="stat-card__label"><?= $rotulo ?></div>
                <div class="stat-card__icon <?= $cor ?>"><i class="bi bi-clipboard2-pulse"></i></div>
            </div>
            <div class="stat-card__value"><?= $valor ?></div>
        </div>
    <?php endforeach; ?>
</section>

<section class="card">
    <div class="tabs">
        <?php foreach ($abas as $chave => [$rotulo, $qtd]): ?>
            <a class="tabs__item <?= $aba === $chave ? 'is-active' : '' ?>" href="./listOcorrencias?aba=<?= urlencode($chave) ?>" style="text-decoration:none;">
                <?= htmlspecialchars($rotulo) ?>
                <?php if ($qtd > 0): ?><span class="text-muted fs-12">(<?= (int) $qtd ?>)</span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <form method="GET" action="./listOcorrencias" class="table-toolbar">
        <input type="hidden" name="aba" value="<?= htmlspecialchars($aba) ?>">
        <div class="search-wrap">
            <input type="text" name="busca" value="<?= htmlspecialchars($busca ?? '') ?>" placeholder="Buscar por cliente, telefone, placa, número ou relato...">
        </div>
        <select name="prioridade" class="toolbar-select" onchange="this.form.submit()">
            <option value="">Prioridade: todas</option>
            <?php foreach (Ocorrencia::PRIORIDADES as $chave => $rotulo): ?>
                <option value="<?= $chave ?>" <?= ($prioridade ?? '') === $chave ? 'selected' : '' ?>><?= htmlspecialchars($rotulo) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="text-muted fs-12"><?= count($ocorrencias) ?> resultado(s)</span>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Cliente</th>
                    <th>Veículo</th>
                    <th>Relato</th>
                    <th>Prioridade</th>
                    <th>Etapa</th>
                    <th>Responsável</th>
                    <th>Chegada</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ocorrencias)): ?>
                    <tr><td colspan="9" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">Nenhuma ocorrência neste filtro.</td></tr>
                <?php else: foreach ($ocorrencias as $o): ?>
                    <tr>
                        <td class="id-col"><?= htmlspecialchars($o['numero']) ?></td>
                        <td>
                            <?= htmlspecialchars($o['cliente_nome']) ?>
                            <?php if ($o['cliente_cadastro'] === 'PROVISORIO'): ?>
                                <span class="badge badge--warning" title="Cadastro mínimo (atendimento rápido): falta completar">Provisório</span>
                            <?php endif; ?>
                            <div class="text-muted fs-12"><?= htmlspecialchars($o['cliente_telefone'] ?? '') ?></div>
                        </td>
                        <td>
                            <?= htmlspecialchars(strtoupper($o['placa'])) ?>
                            <div class="text-muted fs-12"><?= htmlspecialchars($o['veiculo_modelo']) ?></div>
                        </td>
                        <td style="max-width:260px;">
                            <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($o['relato']) ?>"><?= htmlspecialchars($o['relato']) ?></div>
                        </td>
                        <td><span class="badge <?= $badgePrioridade[$o['prioridade']] ?? 'badge--neutral' ?>"><?= htmlspecialchars(explode(' (', Ocorrencia::PRIORIDADES[$o['prioridade']])[0]) ?></span></td>
                        <td><span class="badge <?= OcorrenciaFluxo::badge($o['status']) ?>"><span class="badge__dot"></span><?= htmlspecialchars(OcorrenciaFluxo::rotulo($o['status'])) ?></span></td>
                        <td class="text-muted"><?= htmlspecialchars($o['tecnico_nome'] ?? '—') ?></td>
                        <td class="text-muted"><?= date('d/m H:i', strtotime($o['chegada_em'])) ?></td>
                        <td>
                            <div class="row-actions">
                                <a href="./viewOcorrencia?id=<?= (int) $o['id_ocorrencia'] ?>" class="row-action-btn primary" title="Abrir">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
