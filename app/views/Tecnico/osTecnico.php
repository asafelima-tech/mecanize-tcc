<?php
/**
 * Tela de trabalho do técnico numa OS: status, peças, serviços, observações,
 * checklist e finalização — tudo integrado ao módulo de OS existente (mesmas
 * tabelas, sem duplicar estrutura). Fica só-leitura a partir de
 * AGUARDANDO_CONFERENCIA: terminar o serviço NÃO encerra a OS, só a envia para
 * a conferência do administrador.
 *
 * Mesmas permissões do atendente (ver Permissao): é uma visão, não um privilégio.
 *
 * Espera: $ordem_servico (OrdemServico::getById), $itens_os, $produtos,
 *         $checklist (array decodificado), $erro, $sucesso.
 */
require_once __DIR__ . '/../../models/ConsumoEstoque.php';
require_once __DIR__ . '/../../models/SolicitacaoPeca.php';
require_once __DIR__ . '/../../models/EvidenciaOs.php';

$evidencias = $evidencias ?? [];

$bloqueios        = $bloqueios ?? [];
$solicitacoes     = $solicitacoes ?? [];
$historico_veiculo = $historico_veiculo ?? [];

$os     = $ordem_servico ?? [];
$id     = (int) ($os['id_ordem_servico'] ?? 0);
$status = $os['status'] ?? 'ABERTA';

$emExecucao = in_array($status, OrdemServico::STATUS_TECNICO, true);
$podeFinalizar = in_array($status, ['EM_ANDAMENTO', 'AGUARDANDO_PECA'], true);

$pecas    = array_values(array_filter($itens_os ?? [], fn($i) => ($i['tipo'] ?? '') === 'produto'));
$servicos = array_values(array_filter($itens_os ?? [], fn($i) => ($i['tipo'] ?? '') === 'servico'));

// O consumo (que baixa o estoque) só pode ser confirmado com a OS em execução.
$podeConsumir = in_array($status, ConsumoEstoque::OS_PERMITE_CONSUMO, true) && $status !== 'AGUARDANDO_CONFERENCIA';
// Reservar separa a peça para esta OS (as outras OS deixam de contar com ela) sem baixar o estoque.
$podeReservar = in_array($status, ConsumoEstoque::OS_PERMITE_RESERVA, true);

$alertaTipo = $sucesso ? 'sucesso' : ($erro ? 'erro' : null);
$alertaMensagem = $sucesso
    ? [
        'status' => 'Status atualizado.', 'finalizado' => 'Serviço finalizado — enviado para a conferência do administrador.', 'peca' => 'Peça registrada.', 'obs' => 'Observações salvas.',
        'pecaconsumida' => 'Peça usada: o estoque foi baixado.', 'pecajaconsumida' => 'Esta peça já estava como usada.',
        'pecareservada' => 'Peça reservada para esta OS.', 'pecajareservada' => 'Esta peça já estava reservada.', 'reservaliberada' => 'Reserva liberada: a peça voltou ao disponível.',
        'evidencia' => 'Foto anexada à OS.',
        'complementosolicitado' => 'Pedido enviado. A OS só segue depois da resposta do cliente.',
    ][$sucesso] ?? 'Feito.'
    : $erro;

if ($alertaMensagem):
    require __DIR__ . '/../layout/_alerta.php';
endif;
?>

<style>
    .os-tec-secao { margin-bottom: 20px; }
    .os-tec-linha { display: flex; flex-wrap: wrap; gap: 8px 20px; font-size: 14px; color: var(--color-text); }
    .os-tec-linha strong { color: var(--color-text-muted); font-weight: 600; font-size: 12px; display: block; text-transform: uppercase; letter-spacing: .03em; }
    .os-tec-voltar { display: inline-flex; align-items: center; min-height: 44px; text-decoration: none; }
    .os-tec-aviso { border: 1.5px solid var(--color-warning); background: var(--color-warning-soft); border-radius: var(--radius-lg); padding: 12px 14px; font-size: 13px; line-height: 1.45; }
    .os-tec-aviso strong { display: block; margin-bottom: 4px; }
    .os-tec-aviso ul { margin: 6px 0 0; padding-left: 18px; }
    /* Peça: nome e situação em cima, quantidade e ações embaixo — cabe em 360 px sem apertar os botões. */
    .os-tec-peca { padding: 12px 0; border-bottom: 1px solid var(--color-border); }
    .os-tec-peca:last-child { border-bottom: none; }
    .os-tec-peca__nome { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-size: 14px; font-weight: 600; }
    .os-tec-peca__linha { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 8px; }
    .os-tec-peca__qtd { font-size: 12px; color: var(--color-text-muted); margin-right: auto; }
    .os-tec-peca__linha .btn { min-height: 40px; }
    .os-tec-peca__linha form { margin: 0; }
    .os-tec-hist { display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; padding: 10px 0; border-bottom: 1px solid var(--color-border); font-size: 13px; }
    .os-tec-hist:last-child { border-bottom: none; }
    .os-tec-checklist label { min-height: 44px; }
    /* Evidências: tipo em botões grandes, foto em um toque, miniaturas em grade. */
    .os-tec-evid-tipos { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
    .os-tec-evid-tipos label { position: relative; }
    .os-tec-evid-tipos input { position: absolute; opacity: 0; inset: 0; }
    .os-tec-evid-tipos span { display: inline-flex; align-items: center; min-height: 44px; padding: 0 14px; border: 1.5px solid var(--color-border-strong); border-radius: 999px; font-size: 13px; font-weight: 600; cursor: pointer; background: var(--color-surface); }
    .os-tec-evid-tipos input:checked + span { background: var(--color-primary-soft); border-color: var(--color-primary); color: var(--color-primary); }
    .os-tec-evid-tipos input:focus-visible + span { outline: 2px solid var(--color-primary); outline-offset: 2px; }
    .os-tec-evid-foto { width: 100%; min-height: 56px; justify-content: center; cursor: pointer; font-weight: 700; }
    .os-tec-evid-previa { display: none; width: 100%; max-height: 220px; object-fit: contain; margin-top: 10px; border-radius: var(--radius-md); border: 1px solid var(--color-border); background: #000; }
    .os-tec-evid-grade { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-top: 16px; }
    .os-tec-evid-item { min-width: 0; font-size: 12px; }
    .os-tec-evid-item img { display: block; width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: var(--radius-md); border: 1px solid var(--color-border); background: var(--color-bg-alt); }
    .os-tec-evid-item .legenda { margin-top: 4px; color: var(--color-text-muted); overflow-wrap: anywhere; }
    @media (min-width: 720px) { .os-tec-evid-grade { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .os-tec-checklist input[type="checkbox"] { width: 22px; height: 22px; }
    .os-tec-status-botoes { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
    .os-tec-status-botoes form { flex: 1 1 150px; }
    .os-tec-status-botoes .btn { width: 100%; justify-content: center; padding: 12px; font-size: 13px; }
    .os-tec-item-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--color-border); }
    .os-tec-item-row:last-child { border-bottom: none; }
    .os-tec-checklist label { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--color-border); font-size: 13px; }
    .os-tec-checklist label:last-child { border-bottom: none; }
    .os-tec-checklist input[type="checkbox"] { width: 18px; height: 18px; flex-shrink: 0; }
    #canvas-assinatura-tecnico { width: 100%; height: 130px; margin-top: 6px; background: #fff; border: 1.5px dashed var(--color-border-strong); border-radius: var(--radius-md); cursor: crosshair; touch-action: none; }

    .os-tec-finalizar-bar {
        position: sticky;
        bottom: 0;
        display: flex;
        justify-content: center;
        margin-top: 24px;
        /* padding-bottom com env(safe-area-inset-bottom): folga extra em
           celulares com barra de gestos/notch (iPhone), senão o botão fica
           colado na borda física da tela. */
        padding: 14px 0 calc(14px + env(safe-area-inset-bottom, 0px));
        background: linear-gradient(to top, var(--color-bg) 60%, transparent);
    }

    .os-tec-finalizar-bar .btn {
        padding: 14px 28px;
        font-size: 14px;
    }

    @media (max-width: 480px) {
        .os-tec-status-botoes form {
            flex-basis: 100%;
        }

        .os-tec-finalizar-bar .btn {
            width: 100%;
        }

        .os-tec-add-peca .form-field {
            flex-basis: 100% !important;
        }

        .os-tec-add-peca .btn {
            width: 100%;
        }
    }
</style>

<div class="page-header">
    <div>
        <a href="./painelTecnico" class="fs-12 text-secondary os-tec-voltar">&larr; Painel de trabalho</a>
        <h1 class="page-header__title" style="margin-top:6px;">#<?= htmlspecialchars($os['numero_os'] ?? $id) ?></h1>
        <p class="page-header__subtitle">
            <span class="badge <?= OrdemServico::classeBadgeStatus($status) ?>">
                <span class="badge__dot"></span><?= OrdemServico::rotuloStatus($status) ?>
            </span>
        </p>
    </div>
</div>

<!-- ── Dados da OS (só leitura) ── -->
<section class="card os-tec-secao">
    <div class="card__header">
        <div class="card__title">Cliente e veículo</div>
    </div>
    <div class="card__body">
        <div class="os-tec-linha">
            <span><strong>Cliente:</strong> <?= htmlspecialchars($os['nome_cliente'] ?? '-') ?></span>
            <span><strong>Veículo:</strong> <?= htmlspecialchars($os['modelo'] ?? '-') ?></span>
            <span><strong>Placa:</strong> <?= htmlspecialchars(strtoupper($os['placa'] ?? '-')) ?></span>
            <span><strong>Km:</strong> <?= htmlspecialchars($os['km_veiculo'] ?? '-') ?></span>
        </div>
        <?php if (!empty($os['defeito_relatado'])): ?>
            <div style="margin-top:12px;">
                <div class="fs-12 text-secondary" style="font-weight:600;">Defeito relatado</div>
                <div style="font-size:13px;margin-top:2px;"><?= nl2br(htmlspecialchars($os['defeito_relatado'])) ?></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($os['diagnostico'])): ?>
            <div style="margin-top:12px;">
                <div class="fs-12 text-secondary" style="font-weight:600;">Diagnóstico</div>
                <div style="font-size:13px;margin-top:2px;"><?= nl2br(htmlspecialchars($os['diagnostico'])) ?></div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ── O que está travando o serviço ── -->
<?php if ($bloqueios): ?>
<section class="os-tec-secao os-tec-aviso" role="alert">
    <strong>Esta OS não pode ir para a conferência ainda</strong>
    <ul><?php foreach ($bloqueios as $b): ?><li><?= htmlspecialchars($b) ?></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>
<?php if ($emExecucao): ?>
<section class="card os-tec-secao">
    <div class="card__body" style="padding:6px 16px;">
        <details>
            <summary style="cursor:pointer;min-height:44px;display:flex;align-items:center;font-size:13px;font-weight:700;color:var(--color-primary);">Encontrei algo a mais: pedir autorização ao cliente</summary>
            <form action="./solicitarComplementoOrdemServico" method="POST" style="padding:4px 0 14px;">
                <?= csrf_field() ?>
                <input type="hidden" name="id_ordem_servico" value="<?= $id ?>">
                <input type="hidden" name="voltar" value="tecnico">
                <div class="form-field"><label>Valor adicional (R$)</label><input type="text" inputmode="decimal" name="valor_adicional" required placeholder="0,00"></div>
                <div class="form-field"><label>O que mudou?</label><input type="text" name="motivo" maxlength="255" required placeholder="Ex.: disco empenado, precisa trocar também"></div>
                <button type="submit" class="btn btn--primary" style="min-height:44px;width:100%;">Enviar pedido ao cliente</button>
                <p class="fs-12 text-muted" style="margin:8px 0 0;">O cliente recebe o pedido e responde pelo link seguro (ou a equipe registra a resposta). Nada além do autorizado é cobrado sem isso.</p>
            </form>
        </details>
    </div>
</section>
<?php endif; ?>

<!-- ── Status de execução ── -->
<?php if ($emExecucao): ?>
<section class="card os-tec-secao">
    <div class="card__header">
        <div class="card__title">Status da execução</div>
    </div>
    <div class="card__body">
        <div class="os-tec-status-botoes">
            <?php foreach (OrdemServico::STATUS_TECNICO as $opcao):
                if ($opcao === $status) continue;
            ?>
                <form action="./atualizarStatusOSTecnico" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_ordem_servico" value="<?= $id ?>">
                    <input type="hidden" name="status" value="<?= $opcao ?>">
                    <button type="submit" class="btn btn--ghost">
                        Marcar: <?= OrdemServico::rotuloStatus($opcao) ?>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php elseif ($status === 'AGUARDANDO_CONFERENCIA'): ?>
<section class="card os-tec-secao">
    <div class="card__body" style="text-align:center;padding:24px 20px;">
        <p style="color:var(--color-text-muted);font-size:13px;">
            Serviço finalizado <?= !empty($os['data_finalizacao_tecnica']) ? 'em ' . date('d/m/Y \à\s H:i', strtotime($os['data_finalizacao_tecnica'])) : '' ?>.
            Aguardando a conferência do administrador — se algo precisar de ajuste, a OS volta para a execução com o motivo.
        </p>
    </div>
</section>
<?php elseif ($status === 'AGUARDANDO_RETIRADA'): ?>
<section class="card os-tec-secao">
    <div class="card__body" style="text-align:center;padding:24px 20px;">
        <p style="color:var(--color-text-muted);font-size:13px;">
            Serviço conferido e liberado. Aguardando a retirada pelo cliente.
        </p>
    </div>
</section>
<?php elseif ($status === 'CONCLUIDA'): ?>
<section class="card os-tec-secao">
    <div class="card__body" style="text-align:center;padding:24px 20px;">
        <p style="color:var(--color-text-muted);font-size:13px;">
            Entregue <?= !empty($os['data_conclusao']) ? 'em ' . date('d/m/Y \à\s H:i', strtotime($os['data_conclusao'])) : '' ?>.
        </p>
    </div>
</section>
<?php endif; ?>

<!-- ── Serviços planejados ── -->
<?php if (!empty($servicos)): ?>
<section class="card os-tec-secao">
    <div class="card__header">
        <div class="card__title">Serviços</div>
    </div>
    <div class="card__body os-tec-checklist">
        <?php foreach ($servicos as $s): ?>
            <?php if (!empty($s['row_id'])): ?>
                <form action="./confirmarServicoOSTecnico" method="POST" onchange="this.submit()">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_ordem_servico" value="<?= $id ?>">
                    <input type="hidden" name="servico_row_id" value="<?= (int) $s['row_id'] ?>">
                    <label>
                        <input type="checkbox" name="executado" value="1" <?= !empty($s['executado']) ? 'checked' : '' ?>
                            <?= $emExecucao ? '' : 'disabled' ?>>
                        <span><?= htmlspecialchars($s['nome']) ?></span>
                    </label>
                </form>
            <?php else: ?>
                <label style="opacity:.7;">
                    <input type="checkbox" checked disabled>
                    <span><?= htmlspecialchars($s['nome']) ?></span>
                </label>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ── Peças usadas ── -->
<section class="card os-tec-secao">
    <div class="card__header">
        <div class="card__title">Peças usadas</div>
    </div>
    <div class="card__body">
        <?php if (empty($pecas)): ?>
            <p class="fs-12 text-secondary">Nenhuma peça registrada nesta OS ainda.</p>
        <?php else: ?>
            <?php foreach ($pecas as $p):
                $stPeca = $p['status_item'] ?? null;
                $rotuloPeca = [
                    'PLANEJADO' => ['Prevista', 'badge--neutral'],
                    'RESERVADO' => ['Reservada', 'badge--info'],
                    'CONSUMIDO' => ['Consumida', 'badge--success'],
                ][$stPeca] ?? null;
                $aberta = in_array($stPeca, ['PLANEJADO', 'RESERVADO'], true);
                $saldoPeca = (float) ($p['estoque_atual'] ?? 0) - (float) ($p['reservado_outros'] ?? 0);
                $semSaldo = $aberta && isset($p['estoque_atual']) && $saldoPeca + 0.0005 < (float) $p['qty'];
                $idItem = (int) ($p['row_id'] ?? 0);
            ?>
                <div class="os-tec-peca">
                    <div class="os-tec-peca__nome">
                        <?= htmlspecialchars($p['nome']) ?>
                        <?php if ($rotuloPeca): ?><span class="badge <?= $rotuloPeca[1] ?>"><?= $rotuloPeca[0] ?></span><?php endif; ?>
                        <?php if ($semSaldo): ?><span class="badge badge--warning" title="Em estoque: <?= rtrim(rtrim(number_format((float) $p['estoque_atual'], 3, ',', '.'), '0'), ',') ?>">Sem saldo</span><?php endif; ?>
                    </div>
                    <div class="os-tec-peca__linha">
                        <span class="os-tec-peca__qtd"><?= rtrim(rtrim(number_format((float) $p['qty'], 3, ',', '.'), '0'), ',') ?> un.</span>
                        <?php if ($semSaldo): ?>
                            <a class="btn btn--ghost btn--sm" href="./saveSolicitacao?produto_id=<?= (int) $p['id'] ?>&ordem_servico_id=<?= $id ?>&quantidade=<?= urlencode((string) $p['qty']) ?>">Solicitar peça</a>
                        <?php endif; ?>
                        <?php if ($stPeca === 'PLANEJADO' && $podeReservar && !$semSaldo): ?>
                            <form action="./reservarPecaOrdemServico" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id_item" value="<?= $idItem ?>">
                                <input type="hidden" name="id_ordem_servico" value="<?= $id ?>">
                                <input type="hidden" name="voltar" value="tecnico">
                                <button type="submit" class="btn btn--ghost btn--sm" title="Separa a peça para esta OS, sem baixar o estoque">Reservar</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($stPeca === 'RESERVADO' && $podeReservar): ?>
                            <form action="./liberarReservaOrdemServico" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id_item" value="<?= $idItem ?>">
                                <input type="hidden" name="id_ordem_servico" value="<?= $id ?>">
                                <input type="hidden" name="voltar" value="tecnico">
                                <button type="submit" class="btn btn--ghost btn--sm">Liberar reserva</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($aberta && $podeConsumir && !$semSaldo): ?>
                            <form action="./consumirPecaOrdemServico" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id_item" value="<?= $idItem ?>">
                                <input type="hidden" name="id_ordem_servico" value="<?= $id ?>">
                                <input type="hidden" name="voltar" value="tecnico">
                                <button type="submit" class="btn btn--primary btn--sm" title="Confirma que a peça foi usada e baixa o estoque">Usei</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if ($emExecucao): ?>
        <form action="./adicionarPecaOSTecnico" method="POST" class="os-tec-add-peca" style="display:flex;gap:10px;align-items:flex-end;margin-top:16px;flex-wrap:wrap;">
            <?= csrf_field() ?>
            <input type="hidden" name="id_ordem_servico" value="<?= $id ?>">
            <div class="form-field" style="flex:1 1 200px;margin:0;">
                <label>Peça</label>
                <select name="produto_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach (($produtos ?? []) as $prod): ?>
                        <option value="<?= (int) $prod['id'] ?>"><?= htmlspecialchars($prod['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field" style="flex:0 0 100px;margin:0;">
                <label>Quantidade</label>
                <input type="number" name="quantidade" min="0.01" step="0.01" value="1" required>
            </div>
            <label style="flex:1 1 100%;display:flex;gap:8px;align-items:center;font-size:12px;">
                <input type="checkbox" name="consumir" value="1" style="width:18px;height:18px;">
                Já utilizei esta peça (baixar do estoque agora)
            </label>
            <button type="submit" class="btn btn--primary">Adicionar</button>
        </form>
        <?php endif; ?>
    </div>
</section>

<!-- ── Fotos de evidência ── -->
<section class="card os-tec-secao" id="evidencias">
    <div class="card__header">
        <div class="card__title">Fotos (evidências)</div>
        <span class="text-muted fs-12"><?= count($evidencias) ?> foto<?= count($evidencias) === 1 ? '' : 's' ?></span>
    </div>
    <div class="card__body">
        <?php if ($status !== 'CANCELADA'): ?>
            <form action="./enviarEvidenciaOS" method="POST" enctype="multipart/form-data" id="form-evidencia">
                <?= csrf_field() ?>
                <input type="hidden" name="id_ordem_servico" value="<?= $id ?>">

                <div class="os-tec-evid-tipos" role="radiogroup" aria-label="Tipo da foto">
                    <?php foreach (EvidenciaOs::TIPOS as $chave => $rotulo): ?>
                        <label><input type="radio" name="tipo" value="<?= $chave ?>" <?= $chave === 'DURANTE' ? 'checked' : '' ?>><span><?= htmlspecialchars($rotulo) ?></span></label>
                    <?php endforeach; ?>
                </div>

                <label class="btn btn--ghost os-tec-evid-foto" for="foto-evidencia">Tirar foto ou escolher da galeria</label>
                <input type="file" id="foto-evidencia" name="foto" accept="image/*" capture="environment" style="position:absolute;left:-9999px;" required>
                <img id="previa-evidencia" class="os-tec-evid-previa" alt="Prévia da foto">
                <div id="status-evidencia" class="fs-12 text-muted" style="min-height:18px;margin-top:6px;" aria-live="polite"></div>

                <div class="form-field" style="margin-top:8px;">
                    <input type="text" name="descricao" maxlength="255" placeholder="O que a foto mostra? (opcional)">
                </div>
                <button type="submit" class="btn btn--primary" id="enviar-evidencia" style="min-height:48px;width:100%;" disabled>Enviar foto</button>
            </form>
        <?php endif; ?>

        <?php if ($evidencias): ?>
            <div class="os-tec-evid-grade">
                <?php foreach ($evidencias as $ev): ?>
                    <div class="os-tec-evid-item">
                        <a href="./verEvidenciaOS?id=<?= (int) $ev['id_evidencia'] ?>" target="_blank" rel="noopener">
                            <img src="./verEvidenciaOS?id=<?= (int) $ev['id_evidencia'] ?>" loading="lazy" alt="<?= htmlspecialchars((string) ($ev['descricao'] ?: EvidenciaOs::TIPOS[$ev['tipo']])) ?>">
                        </a>
                        <div class="legenda">
                            <strong><?= htmlspecialchars(EvidenciaOs::TIPOS[$ev['tipo']] ?? $ev['tipo']) ?></strong><?= $ev['descricao'] ? ' · ' . htmlspecialchars($ev['descricao']) : '' ?><br>
                            <?= htmlspecialchars((string) $ev['enviado_por_nome']) ?> · <?= date('d/m H:i', strtotime($ev['enviado_em'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($status !== 'CANCELADA'): ?>
            <p class="fs-12 text-muted" style="margin:12px 0 0;">Nenhuma foto ainda. Registre o estado do veículo (antes), o problema encontrado e o serviço pronto (depois) — a foto fica ligada à OS, com quem tirou e quando.</p>
        <?php endif; ?>
    </div>
</section>

<script src="./assets/js/foto-upload.js"></script>
<script>
    (function () {
        var input = document.getElementById('foto-evidencia');
        if (!input) return;

        var botao = document.getElementById('enviar-evidencia');
        var status = document.getElementById('status-evidencia');
        var previa = document.getElementById('previa-evidencia');

        status.textContent = '';

        FotoUpload.prepararInput(input, function (foto) {
            // a foto já saiu reduzida do aparelho (o envio fica leve até no 4G)
            previa.src = URL.createObjectURL(foto);
            previa.style.display = 'block';
            status.textContent = 'Foto pronta (' + Math.max(1, Math.round(foto.size / 1024)) + ' KB). Confira a prévia e toque em Enviar.';
            botao.disabled = false;
        }, function (erro) {
            previa.style.display = 'none';
            status.textContent = erro.message;
            status.style.color = 'var(--color-danger)';
            botao.disabled = true;
        });

        input.addEventListener('change', function () { botao.disabled = true; status.style.color = ''; status.textContent = 'Preparando a foto…'; });

        // evita envio duplicado por toque repetido
        document.getElementById('form-evidencia').addEventListener('submit', function () { botao.disabled = true; botao.textContent = 'Enviando…'; });
    })();
</script>

<!-- ── Observações técnicas ── -->
<section class="card os-tec-secao">
    <div class="card__header">
        <div class="card__title">Observações técnicas</div>
    </div>
    <div class="card__body">
        <?php if ($emExecucao): ?>
            <form action="./salvarObservacoesOSTecnico" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id_ordem_servico" value="<?= $id ?>">
                <div class="form-field">
                    <textarea name="observacoes_tecnicas" rows="4"
                        placeholder="Problemas encontrados, recomendações para o cliente..."
                    ><?= htmlspecialchars($os['observacoes_tecnicas'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn--ghost btn--sm">Salvar observações</button>
            </form>
        <?php else: ?>
            <p style="font-size:13px;"><?= nl2br(htmlspecialchars($os['observacoes_tecnicas'] ?? 'Nenhuma observação registrada.')) ?></p>
        <?php endif; ?>
    </div>
</section>

<!-- ── Peças pedidas para esta OS ── -->
<?php if ($solicitacoes): ?>
<section class="card os-tec-secao">
    <div class="card__header"><div class="card__title">Peças pedidas para esta OS</div></div>
    <div class="card__body">
        <?php foreach ($solicitacoes as $sol): ?>
            <a href="./viewSolicitacao?id=<?= (int) $sol['id_solicitacao'] ?>" class="os-tec-hist" style="text-decoration:none;color:inherit;min-height:44px;align-items:center;">
                <span><strong><?= htmlspecialchars($sol['numero']) ?></strong> · <?= htmlspecialchars($sol['descricao']) ?></span>
                <span class="badge <?= SolicitacaoPeca::badge($sol['status']) ?>"><?= htmlspecialchars(SolicitacaoPeca::rotulo($sol['status'])) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ── Histórico do veículo ── -->
<?php if ($historico_veiculo): ?>
<section class="card os-tec-secao">
    <div class="card__body" style="padding:6px 16px;">
        <details>
            <summary style="cursor:pointer;min-height:44px;display:flex;align-items:center;font-size:13px;font-weight:700;">Histórico deste veículo (<?= count($historico_veiculo) ?>)</summary>
            <?php foreach ($historico_veiculo as $h): ?>
                <div class="os-tec-hist">
                    <span><strong><?= htmlspecialchars($h['numero']) ?></strong> · <?= date('d/m/Y', strtotime($h['data_abertura'])) ?></span>
                    <span class="badge <?= OrdemServico::classeBadgeStatus($h['status']) ?>"><?= htmlspecialchars(OrdemServico::rotuloStatus($h['status'])) ?></span>
                </div>
            <?php endforeach; ?>
        </details>
    </div>
</section>
<?php endif; ?>

<!-- ── Checklist + assinatura já registrados (pós-finalização) ── -->
<?php if (!empty($checklist)): ?>
<section class="card os-tec-secao">
    <div class="card__header">
        <div class="card__title">Checklist de entrega</div>
    </div>
    <div class="card__body os-tec-checklist">
        <?php foreach ($checklist as $item): ?>
            <label style="opacity:.85;">
                <input type="checkbox" <?= !empty($item['ok']) ? 'checked' : '' ?> disabled>
                <span><?= htmlspecialchars($item['item'] ?? '') ?></span>
            </label>
        <?php endforeach; ?>
        <?php if (!empty($os['assinatura_tecnico'])): ?>
            <div style="margin-top:14px;">
                <div class="fs-12 text-secondary" style="font-weight:600;margin-bottom:6px;">Assinatura do técnico</div>
                <img src="<?= htmlspecialchars($os['assinatura_tecnico']) ?>" alt="Assinatura do técnico"
                    style="max-width:260px;border:1px solid var(--color-border);border-radius:var(--radius-md);background:#fff;">
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ── Finalizar serviço ── -->
<?php if ($podeFinalizar): ?>
<div class="os-tec-finalizar-bar">
    <button type="button" class="btn btn--primary" onclick="abrirModalFinalizar()">
        Finalizar serviço
    </button>
</div>

<div class="modal-overlay" id="modal-finalizar" style="display:none;">
    <div class="modal" style="max-width:480px;max-height:90vh;overflow-y:auto;">
        <div class="modal__header">
            <div>
                <div class="modal__title">Finalizar serviço</div>
                <div class="modal__subtitle">Confirma que a OS <?= htmlspecialchars($os['numero_os'] ?? $id) ?> está pronta para entrega?</div>
            </div>
            <button type="button" class="modal__close" onclick="fecharModalFinalizar()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
        </div>
        <form id="form-finalizar" action="./finalizarOSTecnico" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="id_ordem_servico" value="<?= $id ?>">
            <div class="modal__body">
                <label class="fs-12" style="font-weight:600;">Checklist de entrega</label>
                <div class="os-tec-checklist">
                    <?php foreach (OrdemServico::CHECKLIST_ENTREGA as $chave => $rotulo): ?>
                        <label>
                            <input type="checkbox" name="checklist[<?= htmlspecialchars($chave) ?>]" value="1">
                            <span><?= htmlspecialchars($rotulo) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="form-field" style="margin-top:14px;">
                    <label>Observações técnicas</label>
                    <textarea name="observacoes_tecnicas" rows="3"><?= htmlspecialchars($os['observacoes_tecnicas'] ?? '') ?></textarea>
                </div>

                <div style="margin-top:14px;">
                    <label class="fs-12" style="font-weight:600;">Assinatura do técnico</label>
                    <canvas id="canvas-assinatura-tecnico" width="380" height="130"></canvas>
                    <button type="button" class="btn btn--ghost btn--sm" style="margin-top:8px;"
                        onclick="assinaturaTecnico && assinaturaTecnico.limpar()">Limpar assinatura</button>
                </div>
                <input type="hidden" name="assinatura_tecnico" id="h-assinatura-tecnico">
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--ghost btn--sm" onclick="fecharModalFinalizar()">Cancelar</button>
                <button type="submit" class="btn btn--sm" style="background:var(--color-success);color:#fff;border-color:var(--color-success);">
                    Confirmar finalização
                </button>
            </div>
        </form>
    </div>
</div>

<script src="./assets/js/assinatura.js?v=<?= time() ?>"></script>
<script>
    var assinaturaTecnico = initAssinatura('canvas-assinatura-tecnico');

    function abrirModalFinalizar() {
        document.getElementById('modal-finalizar').style.display = 'flex';
        // Canvas começa display:none dentro do modal — só dá pra saber o
        // tamanho real na tela (e ajustar a resolução do canvas) depois dele
        // ficar visível.
        if (assinaturaTecnico) assinaturaTecnico.redimensionar();
    }

    function fecharModalFinalizar() {
        document.getElementById('modal-finalizar').style.display = 'none';
    }

    document.getElementById('form-finalizar').addEventListener('submit', function () {
        document.getElementById('h-assinatura-tecnico').value = assinaturaTecnico ? assinaturaTecnico.dataUrl() : '';
    });
</script>
<?php endif; ?>
