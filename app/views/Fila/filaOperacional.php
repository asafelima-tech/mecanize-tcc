<?php
/**
 * Fila operacional: o que atacar agora e quem está livre.
 *
 * A fila ORIENTA — não trava ninguém. Um técnico livre pode abrir qualquer item. Só o
 * administrador muda a ordem, e sempre explicando por quê (fica na trilha da ocorrência).
 *
 * Espera: $acionaveis, $andamento, $esperando, $tecnicos, $capacidade, $ehAdmin, $sucesso, $erro.
 */
$e = fn($v) => htmlspecialchars((string) $v);

$badgePrioridade = ['URGENTE' => 'badge--danger', 'ALTA' => 'badge--warning', 'NORMAL' => 'badge--neutral', 'BAIXA' => 'badge--neutral'];
$rotuloPrioridade = ['URGENTE' => 'Urgente', 'ALTA' => 'Alta', 'NORMAL' => 'Normal', 'BAIXA' => 'Baixa'];

$acaoPorEtapa = [
    'AVALIAR'  => 'Avaliar',
    'ORCAR'    => 'Orçar',
    'EXECUTAR' => 'Abrir OS',
    'CONFERIR' => 'Conferir',
    'RETIRAR'  => 'Entrega',
];

$tempo = function (int $min): string {
    if ($min < 60) {
        return $min . ' min';
    }
    if ($min < 1440) {
        return intdiv($min, 60) . ' h ' . str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT) . ' min';
    }

    return intdiv($min, 1440) . ' d ' . intdiv($min % 1440, 60) . ' h';
};

$mensagemOk = ($sucesso ?? '') === 'reordenada' ? 'Ordem da fila atualizada.' : null;

$secoes = [
    ['Para fazer agora', 'Há algo que a equipe pode começar. A ordem considera prioridade, tempo de espera, serviço rápido e peças disponíveis.', $acionaveis, true],
    ['Em andamento', 'Avaliações e serviços sendo executados.', $andamento, false],
    ['Aguardando', 'Esperando o cliente, peças ou outra etapa externa.', $esperando, false],
];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Fila operacional</h1>
        <p class="page-header__subtitle">O que atacar agora. A fila orienta; quem está livre pode pegar qualquer item.</p>
    </div>
    <div class="page-header__actions">
        <a href="./listOcorrencias" class="btn btn--ghost">Atendimento</a>
        <a href="./saveOcorrencia" class="btn btn--primary">Registrar chegada</a>
    </div>
</div>

<?php if ($mensagemOk): ?>
    <?php $alertaTipo = 'sucesso'; $alertaMensagem = $mensagemOk; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>
<?php if (!empty($erro)): ?>
    <?php $alertaTipo = 'erro'; $alertaMensagem = $erro; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<section class="card" style="margin-bottom:16px;">
    <div class="card__header">
        <div class="card__title">Técnicos</div>
        <span class="text-muted fs-12">Ocupado a partir de <?= (int) $capacidade ?> serviços em mãos</span>
    </div>
    <div class="card__body" style="padding:14px 20px;display:flex;flex-wrap:wrap;gap:10px;">
        <?php if (empty($tecnicos)): ?>
            <span class="text-muted" style="font-size:13px;">Nenhum técnico ativo.</span>
        <?php endif; ?>
        <?php foreach ($tecnicos as $t): ?>
            <div style="padding:8px 14px;border:1px solid var(--color-border);border-radius:var(--radius-md);background:var(--color-bg-alt);font-size:13px;">
                <strong><?= $e($t['nome']) ?></strong>
                <span class="badge <?= $t['livre'] ? 'badge--success' : 'badge--warning' ?>" style="margin-left:6px;"><?= $t['livre'] ? 'Livre' : 'Ocupado' ?></span>
                <div class="text-muted fs-12"><?= (int) $t['em_mao'] ?> em mãos</div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php foreach ($secoes as [$titulo, $explica, $itens, $principal]): ?>
    <section class="card" style="margin-bottom:16px;">
        <div class="card__header">
            <div class="card__title"><?= $e($titulo) ?> <span class="text-muted fs-12">(<?= count($itens) ?>)</span></div>
        </div>
        <div class="card__body" style="padding:6px 20px 16px;">
            <p class="text-muted" style="font-size:12.5px;margin:10px 0;"><?= $e($explica) ?></p>

            <?php if (empty($itens)): ?>
                <p class="text-muted" style="font-size:13px;margin:0;padding:12px 0;">Nada aqui no momento.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <?php if ($principal): ?><th>#</th><?php endif; ?>
                                <th>Etapa</th>
                                <th>Item</th>
                                <th>Veículo</th>
                                <th>Prioridade</th>
                                <th>Espera</th>
                                <th>Responsável</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($itens as $pos => $i): ?>
                            <tr>
                                <?php if ($principal): ?><td class="id-col"><?= $pos + 1 ?></td><?php endif; ?>
                                <td>
                                    <?= $e($i['etapa_label']) ?>
                                    <?php if ($i['ordem_manual'] !== null): ?>
                                        <div><span class="badge badge--info" title="O administrador definiu a posição desta ocorrência">Ordem do administrador</span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($i['bloqueio'])): ?>
                                        <div class="text-muted fs-12"><?= $e($i['bloqueio']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= $e($i['link']) ?>"><?= $e($i['numero']) ?></a>
                                    <span class="text-muted fs-12">· <?= $i['tipo'] === 'OS' ? 'OS' : 'Ocorrência' ?></span>
                                    <div class="text-muted fs-12"><?= $e($i['cliente']) ?></div>
                                </td>
                                <td>
                                    <?= $e(strtoupper($i['placa'])) ?>
                                    <div class="text-muted fs-12"><?= $e($i['veiculo']) ?></div>
                                </td>
                                <td><span class="badge <?= $badgePrioridade[$i['prioridade']] ?? 'badge--neutral' ?>"><?= $e($rotuloPrioridade[$i['prioridade']] ?? $i['prioridade']) ?></span></td>
                                <td>
                                    <?= $e($tempo($i['espera_min'])) ?>
                                    <?php if ($i['duracao_estimada_min'] !== null): ?>
                                        <div class="text-muted fs-12">~<?= (int) $i['duracao_estimada_min'] ?> min de serviço</div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= $e($i['tecnico_nome'] ?? 'A definir') ?></td>
                                <td>
                                    <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
                                        <a class="btn <?= $principal ? 'btn--primary' : 'btn--ghost' ?> btn--sm" href="<?= $e($i['link']) ?>">
                                            <?= $e($acaoPorEtapa[$i['etapa']] ?? 'Abrir') ?>
                                        </a>

                                        <?php if ($ehAdmin && $i['tipo'] === 'OCORRENCIA'): ?>
                                            <details class="fila-ordem">
                                                <summary class="btn btn--ghost btn--sm" style="list-style:none;cursor:pointer;">Ordem</summary>
                                                <form action="./reordenarFila" method="POST" style="margin-top:8px;min-width:230px;">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id_ocorrencia" value="<?= (int) $i['id'] ?>">
                                                    <input type="text" name="justificativa" maxlength="200" required placeholder="Por quê? (obrigatório)" style="width:100%;">
                                                    <div style="display:flex;gap:6px;margin-top:6px;">
                                                        <button type="submit" name="acao" value="topo" class="btn btn--primary btn--sm">Pôr no topo</button>
                                                        <?php if ($i['ordem_manual'] !== null): ?>
                                                            <button type="submit" name="acao" value="automatica" class="btn btn--ghost btn--sm">Ordem automática</button>
                                                        <?php endif; ?>
                                                    </div>
                                                </form>
                                            </details>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endforeach; ?>
