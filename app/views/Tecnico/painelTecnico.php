<?php
/**
 * Painel de trabalho — pensado para o celular, no balcão ou debaixo do carro.
 *
 * Responde, de cima para baixo: o que preciso fazer AGORA (em ordem de prioridade), o que está parado
 * esperando outra pessoa ou outra coisa (peça, conferência do administrador, retirada) e o histórico.
 * Sem nada financeiro nem administrativo. Técnico e atendente veem os mesmos recursos (mesma permissão);
 * o que muda é o foco: o técnico entra aqui, o atendente entra no Início.
 *
 * Espera: $minhas (['ABERTA'=>[], 'EM_ANDAMENTO'=>[], 'AGUARDANDO_PECA'=>[]]), $prontas, $historico,
 *         $avaliacoes (ocorrências aguardando avaliação), $sugestao, $escopoTodas.
 */
require_once __DIR__ . '/../../models/OcorrenciaFluxo.php';

$ehAdmin     = ($_SESSION['user_tipo'] ?? '') === 'ADMIN';
$avaliacoes  = $avaliacoes ?? [];
$sugestao    = $sugestao ?? null;
$escopoTodas = !empty($escopoTodas);

$aExecutar   = $minhas['ABERTA']          ?? [];
$emExecucao  = $minhas['EM_ANDAMENTO']    ?? [];
$aguardPeca  = $minhas['AGUARDANDO_PECA'] ?? [];
$prontas     = $prontas ?? [];
$historico   = $historico ?? [];

$e = fn($v) => htmlspecialchars((string) $v);

$idade = function (?string $data): string {
    if (!$data) {
        return '';
    }

    $dias = max(0, (int) floor((time() - strtotime($data)) / 86400));

    if ($dias === 0) {
        $h = max(0, (int) floor((time() - strtotime($data)) / 3600));

        return $h < 1 ? 'há menos de 1 h' : 'há ' . $h . ' h';
    }

    return 'há ' . $dias . ($dias === 1 ? ' dia' : ' dias');
};

// A fila "fazer agora": urgentes primeiro, depois o que já está na bancada, depois o que falta iniciar, depois as demais avaliações.
$tarefas = [];

foreach ($avaliacoes as $o) {
    $urgente = in_array($o['prioridade'], ['URGENTE', 'ALTA'], true);
    $tarefas[] = [
        'ordem'  => $urgente ? 0 : ($o['status'] === 'EM_AVALIACAO' ? 1 : 3),
        'tempo'  => strtotime((string) $o['chegada_em']),
        'tipo'   => 'avaliacao',
        'link'   => './viewOcorrencia?id=' . (int) $o['id_ocorrencia'],
        'numero' => $o['numero'],
        'cliente' => $o['cliente_nome'],
        'veiculo' => trim(($o['veiculo_modelo'] ?? '') . ' · ' . strtoupper((string) $o['placa']), ' ·'),
        'texto'  => $o['relato'],
        'quando' => 'Chegou ' . $idade($o['chegada_em']),
        'badge'  => OcorrenciaFluxo::badge($o['status']),
        'status' => OcorrenciaFluxo::rotulo($o['status']),
        'urgencia' => $o['prioridade'] === 'URGENTE' ? 'Urgente' : ($o['prioridade'] === 'ALTA' ? 'Urgência declarada' : null),
        'acao'   => $o['status'] === 'EM_AVALIACAO' ? 'Continuar avaliação' : 'Avaliar',
        'rotulo' => 'Avaliação',
    ];
}

foreach ($emExecucao as $os) {
    $tarefas[] = ['ordem' => 1, 'tempo' => strtotime((string) ($os['data_abertura'] ?? 'now')), 'tipo' => 'os', 'os' => $os, 'acao' => 'Continuar', 'rotulo' => 'Em execução'];
}

foreach ($aExecutar as $os) {
    $tarefas[] = ['ordem' => 2, 'tempo' => strtotime((string) ($os['data_abertura'] ?? 'now')), 'tipo' => 'os', 'os' => $os, 'acao' => 'Iniciar', 'rotulo' => 'A iniciar'];
}

usort($tarefas, fn($a, $b) => [$a['ordem'], $a['tempo']] <=> [$b['ordem'], $b['tempo']]);

$parados = [];

foreach ($aguardPeca as $os) {
    $parados[] = ['os' => $os, 'depende' => 'Depende da chegada de uma peça'];
}

foreach ($prontas as $os) {
    $parados[] = ['os' => $os, 'depende' => ($os['status'] ?? '') === 'AGUARDANDO_RETIRADA' ? 'Pronta: aguardando o cliente retirar' : 'Depende da conferência do administrador'];
}

/** Cartão de uma OS. $acao = texto do botão principal (null = só consulta). */
function tpCartaoOs(array $os, ?string $acao, ?string $depende = null, ?string $rotulo = null): void
{
    $id = (int) ($os['id_ordem_servico'] ?? $os['id'] ?? 0);
    $status = $os['status'] ?? 'ABERTA';
    $dias = !empty($os['data_abertura']) ? max(0, (int) floor((time() - strtotime($os['data_abertura'])) / 86400)) : null;
    ?>
    <a href="./viewOSTecnico?id=<?= $id ?>" class="tp-cartao">
        <div class="tp-cartao__topo">
            <span class="tp-cartao__numero">#<?= htmlspecialchars((string) ($os['numero'] ?? $id)) ?></span>
            <span class="badge <?= OrdemServico::classeBadgeStatus($status) ?>"><span class="badge__dot"></span><?= htmlspecialchars(OrdemServico::rotuloStatus($status)) ?></span>
        </div>
        <div class="tp-cartao__cliente"><?= htmlspecialchars((string) ($os['cliente'] ?? '-')) ?></div>
        <div class="tp-cartao__veiculo"><?= htmlspecialchars((string) ($os['veiculo'] ?? '-')) ?><?php if (!empty($os['placa'])): ?> · <strong><?= htmlspecialchars(strtoupper($os['placa'])) ?></strong><?php endif; ?></div>
        <?php if (!empty($os['defeito_relatado'])): ?><div class="tp-cartao__texto"><?= htmlspecialchars($os['defeito_relatado']) ?></div><?php endif; ?>
        <?php if ($depende): ?><div class="tp-cartao__depende"><?= htmlspecialchars($depende) ?></div><?php endif; ?>
        <div class="tp-cartao__rodape">
            <span class="tp-cartao__idade"><?= $dias === null ? '' : ($dias === 0 ? 'Aberta hoje' : 'Aberta há ' . $dias . ($dias === 1 ? ' dia' : ' dias')) ?></span>
            <?php if ($acao): ?><span class="btn btn--primary tp-cartao__acao"><?= htmlspecialchars($acao) ?></span><?php endif; ?>
        </div>
    </a>
    <?php
}
?>

<style>
    /* Mobile primeiro: uma coluna, alvos de toque de pelo menos 44 px, nada que role na horizontal. */
    .tp-resumo { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 14px; padding: 0; list-style: none; }
    .tp-resumo li { display: flex; align-items: baseline; gap: 6px; padding: 8px 12px; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); font-size: 12px; color: var(--color-text-muted); }
    .tp-resumo strong { font-size: 18px; color: var(--color-text); line-height: 1; }
    .tp-resumo li.is-alerta { border-color: var(--color-danger); background: var(--color-danger-soft); }
    .tp-resumo li.is-alerta strong { color: var(--color-danger); }

    .tp-secao { margin: 0 0 20px; }
    .tp-secao__titulo { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; margin: 0 0 8px; font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: var(--color-text); }
    .tp-secao__titulo small { font-weight: 500; text-transform: none; letter-spacing: 0; color: var(--color-text-muted); font-size: 12px; }
    .tp-lista { display: grid; grid-template-columns: minmax(0, 1fr); gap: 10px; }

    .tp-cartao { display: block; min-width: 0; padding: 14px; text-decoration: none; color: inherit; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); }
    .tp-cartao:active { background: var(--color-bg-alt); }
    .tp-cartao--urgente { border-left: 4px solid var(--color-danger); }
    .tp-cartao__topo { display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap; }
    .tp-cartao__numero { font-weight: 800; font-size: 14px; }
    .tp-cartao__cliente { margin-top: 4px; font-size: 15px; font-weight: 600; color: var(--color-text); overflow-wrap: anywhere; }
    .tp-cartao__veiculo { font-size: 13px; color: var(--color-text-secondary); overflow-wrap: anywhere; }
    .tp-cartao__texto { margin-top: 8px; font-size: 13px; color: var(--color-text); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .tp-cartao__depende { margin-top: 8px; padding: 6px 10px; font-size: 12px; font-weight: 600; background: var(--color-warning-soft); border-radius: var(--radius-md); color: var(--color-text); }
    .tp-cartao__rodape { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-top: 10px; }
    .tp-cartao__idade { font-size: 12px; color: var(--color-text-muted); }
    .tp-cartao__acao { min-height: 44px; min-width: 44px; padding: 0 18px; display: inline-flex; align-items: center; justify-content: center; pointer-events: none; }

    .tp-parados .tp-cartao { background: var(--color-bg-alt); box-shadow: none; }
    .tp-vazio { padding: 28px 16px; text-align: center; color: var(--color-text-muted); background: var(--color-surface); border: 1px dashed var(--color-border-strong); border-radius: var(--radius-lg); font-size: 14px; }
    .tp-historico summary { cursor: pointer; min-height: 44px; display: flex; align-items: center; font-weight: 700; font-size: 13px; }
    .tp-escopo { margin: 0 0 12px; font-size: 13px; }
    .tp-escopo a { display: inline-flex; align-items: center; min-height: 36px; }

    @media (min-width: 720px) {
        .tp-lista { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (min-width: 1100px) {
        .tp-lista { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= $escopoTodas || $ehAdmin ? 'Painel de trabalho' : 'Meu painel' ?></h1>
        <p class="page-header__subtitle"><?= $escopoTodas || $ehAdmin ? 'O trabalho de todos, em ordem de prioridade.' : 'O que fazer agora, em ordem de prioridade.' ?> · <?= date('d/m') ?></p>
    </div>
    <div class="page-header__actions">
        <a href="./saveOcorrencia" class="btn btn--primary">Registrar chegada</a>
    </div>
</div>

<ul class="tp-resumo" aria-label="Resumo">
    <?php $urgentes = count(array_filter($avaliacoes, fn($o) => $o['prioridade'] === 'URGENTE')); ?>
    <?php if ($urgentes): ?><li class="is-alerta"><strong><?= $urgentes ?></strong> urgente<?= $urgentes > 1 ? 's' : '' ?></li><?php endif; ?>
    <li><strong><?= count($avaliacoes) ?></strong> avaliaç<?= count($avaliacoes) === 1 ? 'ão' : 'ões' ?></li>
    <li><strong><?= count($emExecucao) ?></strong> em execução</li>
    <li><strong><?= count($aExecutar) ?></strong> a iniciar</li>
    <li><strong><?= count($parados) ?></strong> esperando</li>
</ul>

<?php if (!$ehAdmin): ?>
    <p class="tp-escopo">
        <?php if ($escopoTodas): ?>
            Mostrando o trabalho de todos. <a href="./painelTecnico">Ver só o meu</a>
        <?php else: ?>
            Mostrando o que está com você. <a href="./painelTecnico?escopo=todas">Ver o trabalho de todos</a>
        <?php endif; ?>
    </p>
<?php endif; ?>

<section class="tp-secao" aria-labelledby="tp-agora">
    <h2 class="tp-secao__titulo" id="tp-agora">Fazer agora <small><?= count($tarefas) ?> tarefa<?= count($tarefas) === 1 ? '' : 's' ?></small></h2>

    <?php if (empty($tarefas)): ?>
        <div class="tp-vazio">Nada pendente com você agora. <?php if (!$escopoTodas && !$ehAdmin): ?><br><a href="./painelTecnico?escopo=todas">Ver o trabalho de todos</a><?php endif; ?></div>
    <?php else: ?>
        <div class="tp-lista">
            <?php foreach ($tarefas as $t): ?>
                <?php if ($t['tipo'] === 'os'): ?>
                    <?php tpCartaoOs($t['os'], $t['acao'], null, $t['rotulo']); ?>
                <?php else: ?>
                    <a href="<?= $e($t['link']) ?>" class="tp-cartao <?= $t['urgencia'] === 'Urgente' ? 'tp-cartao--urgente' : '' ?>">
                        <div class="tp-cartao__topo">
                            <span class="tp-cartao__numero"><?= $e($t['numero']) ?> · avaliação</span>
                            <span style="display:flex;gap:6px;flex-wrap:wrap;">
                                <?php if ($t['urgencia']): ?><span class="badge <?= $t['urgencia'] === 'Urgente' ? 'badge--danger' : 'badge--warning' ?>"><?= $e($t['urgencia']) ?></span><?php endif; ?>
                                <span class="badge <?= $e($t['badge']) ?>"><span class="badge__dot"></span><?= $e($t['status']) ?></span>
                            </span>
                        </div>
                        <div class="tp-cartao__cliente"><?= $e($t['cliente']) ?></div>
                        <div class="tp-cartao__veiculo"><?= $e($t['veiculo']) ?></div>
                        <div class="tp-cartao__texto"><?= $e($t['texto']) ?></div>
                        <div class="tp-cartao__rodape">
                            <span class="tp-cartao__idade"><?= $e($t['quando']) ?></span>
                            <span class="btn btn--primary tp-cartao__acao"><?= $e($t['acao']) ?></span>
                        </div>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if (!empty($sugestao) && !empty($tarefas)): ?>
    <p class="tp-escopo" style="margin-top:-8px;">A fila do administrador sugere começar por <a href="<?= $e($sugestao['link']) ?>"><strong><?= $e($sugestao['etapa_label']) ?> · <?= $e($sugestao['numero']) ?></strong></a> (<?= $e(strtoupper((string) $sugestao['placa'])) ?>).</p>
<?php endif; ?>

<?php if ($parados): ?>
    <section class="tp-secao tp-parados" aria-labelledby="tp-parados">
        <h2 class="tp-secao__titulo" id="tp-parados">Esperando outra pessoa ou outra coisa <small><?= count($parados) ?></small></h2>
        <div class="tp-lista">
            <?php foreach ($parados as $p): ?>
                <?php tpCartaoOs($p['os'], null, $p['depende']); ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="tp-secao tp-historico">
    <details>
        <summary>Histórico recente (<?= count($historico) ?>)</summary>
        <?php if (empty($historico)): ?>
            <div class="tp-vazio">Nenhuma OS no histórico ainda.</div>
        <?php else: ?>
            <div class="tp-lista" style="margin-top:8px;">
                <?php foreach (array_slice($historico, 0, 30) as $os): ?>
                    <?php tpCartaoOs($os, null); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </details>
</section>
