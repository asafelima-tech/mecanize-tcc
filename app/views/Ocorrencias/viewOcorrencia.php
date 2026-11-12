<?php
/**
 * Ficha da ocorrência: o ponto onde atendente e técnico (mesmas permissões) conduzem
 * o atendimento — avaliação técnica → orçamento (ou autorização + OS direta) → OS.
 *
 * Espera: $ocorrencia, $linhaDoTempo, $avaliacao, $avaliacaoItens, $avaliacaoTotal, $versoes,
 *         $orcamentos, $autorizacao, $autorizacoes, $produtos, $tecnicos, $canais, $ehAdmin,
 *         $sucesso, $erro.
 */
require_once __DIR__ . '/../../models/OcorrenciaFluxo.php';
require_once __DIR__ . '/../../models/Ocorrencia.php';
require_once __DIR__ . '/../../models/AutorizacaoCliente.php';

$o       = $ocorrencia;
$id      = (int) $o['id_ocorrencia'];
$status  = $o['status'];
$fechada = in_array($status, ['ENCERRADA', 'CANCELADA'], true);
$temOs   = !empty($o['os_id']);

$brl = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$qtd = fn($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
$e   = fn($v) => htmlspecialchars((string) $v);

$avConcluida   = $avaliacao && $avaliacao['status'] === 'CONCLUIDA';
$avEmAndamento = $avaliacao && $avaliacao['status'] === 'EM_ANDAMENTO';

$mensagensOk = [
    'registrada'            => 'Chegada registrada. A ocorrência aguarda a avaliação técnica.',
    'avaliacaoiniciada'     => 'Avaliação iniciada.',
    'avaliacaoemandamento'  => 'Esta avaliação já estava em andamento.',
    'avaliacaosalva'        => 'Avaliação salva.',
    'avaliacaoconcluida'    => 'Avaliação concluída. Agora é possível orçar ou, com autorização do cliente, abrir a OS.',
    'semreparo'             => 'Avaliação concluída sem necessidade de reparo. Ocorrência encerrada.',
    'autorizacaoregistrada' => 'Autorização do cliente registrada.',
    'urgenciavalidada'      => 'Urgência validada: a ocorrência sobe para o topo da fila.',
    'tecnicoatribuido'      => 'Responsável atualizado.',
    'cancelada'             => 'Ocorrência cancelada.',
    'orcamentoenviado'      => 'Orçamento marcado como enviado ao cliente. Aguardando a aprovação.',
    'jaenviado'             => 'Este orçamento já tinha sido enviado.',
    'orcamentoaprovado'     => 'Aprovação registrada. A ordem de serviço foi aberta.',
    'jaaprovado'            => 'Este orçamento já estava aprovado.',
    'orcamentorecusado'     => 'Recusa registrada.',
    'jarecusado'            => 'Este orçamento já estava recusado.',
];
$flashOk = $mensagensOk[$sucesso ?? ''] ?? null;

$badgePrioridade = ['URGENTE' => 'badge--danger', 'ALTA' => 'badge--warning', 'NORMAL' => 'badge--neutral', 'BAIXA' => 'badge--neutral'];
$rotuloPrioridade = explode(' (', Ocorrencia::PRIORIDADES[$o['prioridade']] ?? $o['prioridade'])[0];

$produtosJs = array_map(fn($p) => [
    'id' => (int) $p['id'], 'nome' => $p['nome'], 'categoria' => $p['categoria'], 'preco' => (float) $p['preco'],
], $produtos ?? []);

$itensJs = array_map(fn($i) => [
    'tipo' => $i['tipo'], 'produto_id' => $i['produto_id'] ? (int) $i['produto_id'] : null, 'nome' => $i['nome'],
    'categoria' => $i['categoria'], 'quantidade' => (float) $i['quantidade'], 'preco' => (float) $i['preco'],
    'observacao' => $i['observacao'],
], $avaliacaoItens ?? []);

$flagsJson = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;

$badgeOrcamento = ['pendente' => 'badge--warning', 'aprovado' => 'badge--success', 'reprovado' => 'badge--danger', 'cancelado' => 'badge--neutral'];
?>

<style>
    .oc-grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 16px; align-items: start; }
    .oc-col { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    .oc-dl { display: grid; grid-template-columns: 110px 1fr; gap: 6px 12px; font-size: 13px; margin: 0; }
    .oc-dl dt { color: var(--color-text-muted); }
    .oc-dl dd { margin: 0; min-width: 0; overflow-wrap: anywhere; }
    .oc-linha { display: grid; grid-template-columns: 110px 1fr 90px 90px 36px; gap: 8px; align-items: end; padding: 12px; background: var(--color-bg-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); }
    .oc-linha .form-field { margin: 0; }
    .oc-linha__obs { grid-column: 1 / -1; }
    .oc-passo { padding: 14px 16px; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-bg-alt); }
    .oc-passo + .oc-passo { margin-top: 12px; }
    .oc-details > summary { cursor: pointer; font-size: 13px; font-weight: 600; color: var(--color-primary); }
    .oc-details[open] > summary { margin-bottom: 10px; }
    @media (max-width: 960px) {
        .oc-grid { grid-template-columns: minmax(0, 1fr); }
        .oc-linha { grid-template-columns: 1fr 1fr; }
        .oc-linha .oc-item { grid-column: 1 / -1; }
        .oc-dl { grid-template-columns: 95px 1fr; }
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= $e($o['numero']) ?> · <?= $e($o['cliente_nome']) ?></h1>
        <p class="page-header__subtitle">
            <?= $e(strtoupper($o['placa'])) ?> · <?= $e($o['veiculo_modelo']) ?>
            <span class="badge <?= OcorrenciaFluxo::badge($status) ?>" style="margin-left:6px;"><span class="badge__dot"></span><?= $e(OcorrenciaFluxo::rotulo($status)) ?></span>
            <span class="badge <?= $badgePrioridade[$o['prioridade']] ?? 'badge--neutral' ?>"><?= $e($rotuloPrioridade) ?></span>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="./filaOperacional" class="btn btn--ghost"><i class="bi bi-list-ol"></i> Fila</a>
        <a href="./listOcorrencias" class="btn btn--ghost">Voltar</a>
    </div>
</div>

<?php if ($flashOk): ?>
    <?php $alertaTipo = 'sucesso'; $alertaMensagem = $flashOk; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>
<?php if (!empty($erro)): ?>
    <?php $alertaTipo = 'erro'; $alertaMensagem = $erro; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<div class="oc-grid">
<div class="oc-col">

    <!-- ① Relato -->
    <section class="card">
        <div class="card__header"><div class="card__title">Problema relatado</div></div>
        <div class="card__body" style="padding:16px 20px;font-size:14px;">
            <p style="margin:0;white-space:pre-wrap;"><?= $e($o['relato']) ?></p>
            <?php if (!empty($o['observacoes'])): ?>
                <p class="text-muted" style="margin:10px 0 0;font-size:12.5px;"><strong>Observações:</strong> <?= $e($o['observacoes']) ?></p>
            <?php endif; ?>
        </div>
    </section>

    <!-- ② Ordem de serviço, quando existe -->
    <?php if ($temOs): ?>
        <section class="card" style="border-color:var(--color-success);">
            <div class="card__body" style="padding:16px 20px;display:flex;flex-wrap:wrap;gap:12px;justify-content:space-between;align-items:center;">
                <div>
                    <strong>OS <?= $e($o['os_numero']) ?></strong>
                    <span class="badge <?= OrdemServico::classeBadgeStatus($o['os_status']) ?>" style="margin-left:6px;"><span class="badge__dot"></span><?= $e(OrdemServico::rotuloStatus($o['os_status'])) ?></span>
                    <div class="text-muted fs-12">A execução, as peças, a conferência e a entrega seguem na ordem de serviço.</div>
                </div>
                <a class="btn btn--primary" href="./viewOrdemServico?id=<?= (int) $o['os_id'] ?>">Abrir a OS</a>
            </div>
        </section>
    <?php endif; ?>

    <!-- ③ Avaliação técnica -->
    <section class="card">
        <div class="card__header">
            <div class="card__title">Avaliação técnica<?= $avaliacao ? ' · v' . (int) $avaliacao['versao'] : '' ?></div>
            <?php if (count($versoes) > 1): ?>
                <span class="text-muted fs-12"><?= count($versoes) ?> versões (a anterior fica registrada)</span>
            <?php endif; ?>
        </div>
        <div class="card__body" style="padding:16px 20px;">

            <?php if ($status === 'AGUARDANDO_AVALIACAO'): ?>
                <p style="margin:0 0 12px;font-size:13.5px;">O veículo chegou e ainda não foi avaliado. Ao iniciar, a ocorrência passa a <strong>avaliação em andamento</strong>.</p>
                <form action="./iniciarAvaliacaoOcorrencia" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_ocorrencia" value="<?= $id ?>">
                    <button type="submit" class="btn btn--primary"><i class="bi bi-clipboard2-pulse"></i> Iniciar avaliação técnica</button>
                </form>

            <?php elseif ($avEmAndamento): ?>
                <form action="./salvarAvaliacaoOcorrencia" method="POST" id="formAvaliacao">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_ocorrencia" value="<?= $id ?>">
                    <input type="hidden" name="id_avaliacao" value="<?= (int) $avaliacao['id_avaliacao'] ?>">

                    <div class="form-field">
                        <label for="defeitos">Defeitos constatados <span style="color:var(--color-danger)">*</span></label>
                        <textarea name="defeitos" id="defeitos" rows="3" placeholder="O que foi verificado e o que está errado?"><?= $e($avaliacao['defeitos'] ?? '') ?></textarea>
                    </div>

                    <div style="margin-top:14px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                            <strong style="font-size:13px;">Serviços e peças recomendados</strong>
                            <div style="display:flex;gap:6px;">
                                <button type="button" class="btn btn--ghost btn--sm" onclick="adicionarLinha({tipo:'servico'})">+ Serviço</button>
                                <button type="button" class="btn btn--ghost btn--sm" onclick="adicionarLinha({tipo:'produto'})">+ Peça</button>
                            </div>
                        </div>
                        <div id="linhasItens" style="display:flex;flex-direction:column;gap:8px;"></div>
                        <div style="text-align:right;margin-top:8px;font-size:13px;">Total estimado: <strong id="totalItens">R$ 0,00</strong></div>
                    </div>

                    <div class="form-grid" style="margin-top:12px;">
                        <div class="form-field">
                            <label for="duracao_estimada_min">Tempo estimado (minutos)</label>
                            <input type="number" min="1" max="9999" name="duracao_estimada_min" id="duracao_estimada_min" value="<?= $e($avaliacao['duracao_estimada_min'] ?? '') ?>">
                            <small class="text-muted fs-12">Ajuda a fila a priorizar serviços rápidos.</small>
                        </div>
                        <div class="form-field">
                            <label for="observacoes_av">Observações</label>
                            <input type="text" name="observacoes" id="observacoes_av" value="<?= $e($avaliacao['observacoes'] ?? '') ?>">
                        </div>
                    </div>

                    <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end;margin-top:14px;">
                        <button type="submit" name="acao" value="salvar" class="btn btn--ghost">Salvar rascunho</button>
                        <button type="submit" name="acao" value="concluir_sem_reparo" class="btn btn--ghost"
                                onclick="return confirm('Concluir SEM reparo? A ocorrência será encerrada (nada a orçar).')">Sem reparo necessário</button>
                        <button type="submit" name="acao" value="concluir" class="btn btn--primary">Concluir avaliação</button>
                    </div>
                </form>

            <?php elseif ($avConcluida): ?>
                <div style="font-size:13px;margin-bottom:10px;">
                    <strong>Defeitos constatados:</strong>
                    <p style="margin:4px 0 0;white-space:pre-wrap;"><?= $e($avaliacao['defeitos']) ?></p>
                    <?php if (!empty($avaliacao['observacoes'])): ?>
                        <p class="text-muted" style="margin:6px 0 0;"><?= $e($avaliacao['observacoes']) ?></p>
                    <?php endif; ?>
                    <p class="text-muted fs-12" style="margin:6px 0 0;">
                        Por <?= $e($avaliacao['tecnico_nome'] ?? '—') ?> · concluída em <?= $avaliacao['concluida_em'] ? date('d/m/Y H:i', strtotime($avaliacao['concluida_em'])) : '—' ?>
                        <?php if (!empty($avaliacao['duracao_estimada_min'])): ?> · estimativa <?= (int) $avaliacao['duracao_estimada_min'] ?> min<?php endif; ?>
                    </p>
                </div>

                <?php if (empty($avaliacaoItens)): ?>
                    <p class="text-muted" style="font-size:13px;margin:0;">Nenhum serviço ou peça recomendado (sem reparo necessário).</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Item</th><th>Qtd</th><th>Valor</th><th>Subtotal</th><th>Estoque</th></tr></thead>
                            <tbody>
                            <?php foreach ($avaliacaoItens as $i): ?>
                                <tr>
                                    <td>
                                        <?= $e($i['nome']) ?>
                                        <span class="text-muted fs-12">· <?= $i['tipo'] === 'produto' ? 'Peça' : 'Serviço' ?></span>
                                        <?php if (!empty($i['observacao'])): ?><div class="text-muted fs-12"><?= $e($i['observacao']) ?></div><?php endif; ?>
                                    </td>
                                    <td><?= $qtd($i['quantidade']) ?></td>
                                    <td><?= $brl($i['preco']) ?></td>
                                    <td><?= $brl($i['quantidade'] * $i['preco']) ?></td>
                                    <td>
                                        <?php if ($i['tipo'] === 'produto' && $i['produto_id']): ?>
                                            <?php if ((float) $i['estoque_atual'] >= (float) $i['quantidade']): ?>
                                                <span class="badge badge--success">Disponível</span>
                                            <?php else: ?>
                                                <span class="badge badge--warning" title="Em estoque: <?= $qtd($i['estoque_atual']) ?>">Falta peça</span>
                                            <?php endif; ?>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot><tr><td colspan="3" style="text-align:right;"><strong>Total avaliado</strong></td><td colspan="2"><strong><?= $brl($avaliacaoTotal) ?></strong></td></tr></tfoot>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($status === 'AGUARDANDO_ORCAMENTO'): ?>
                    <form action="./iniciarAvaliacaoOcorrencia" method="POST" style="margin-top:12px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id_ocorrencia" value="<?= $id ?>">
                        <button type="submit" class="btn btn--ghost btn--sm" onclick="return confirm('Abrir uma reavaliação? A versão atual fica registrada.')">Reavaliar (nova versão)</button>
                    </form>
                <?php endif; ?>

            <?php else: ?>
                <p class="text-muted" style="margin:0;font-size:13px;">Sem avaliação registrada.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- ④ Orçamento ou autorização + OS direta -->
    <?php if (!$fechada && !$temOs && $avConcluida && !empty($avaliacaoItens) && $status === 'AGUARDANDO_ORCAMENTO'): ?>
        <section class="card">
            <div class="card__header"><div class="card__title">Como o cliente vai autorizar?</div></div>
            <div class="card__body" style="padding:16px 20px;">

                <div class="oc-passo">
                    <strong style="font-size:13.5px;">Caminho 1 · Orçamento</strong>
                    <p class="text-muted" style="font-size:12.5px;margin:4px 0 10px;">Os serviços e peças da avaliação viram um orçamento. Você confere os preços, envia, e o cliente aprova.</p>
                    <form action="./gerarOrcamentoOcorrencia" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id_ocorrencia" value="<?= $id ?>">
                        <input type="hidden" name="id_avaliacao" value="<?= (int) $avaliacao['id_avaliacao'] ?>">
                        <button type="submit" class="btn btn--primary btn--sm">Gerar orçamento</button>
                    </form>
                </div>

                <div class="oc-passo">
                    <strong style="font-size:13.5px;">Caminho 2 · Autorização do cliente (sem orçamento)</strong>
                    <p class="text-muted" style="font-size:12.5px;margin:4px 0 10px;">
                        Para o cliente que deixou o carro e aceitou o serviço. A autorização fica registrada com
                        <strong>escopo, canal e evidência</strong>, e a OS tem um teto: crescer além dele exige nova autorização.
                    </p>

                    <?php if ($autorizacao): ?>
                        <dl class="oc-dl" style="margin-bottom:12px;">
                            <dt>Escopo</dt><dd><?= $e($autorizacao['escopo']) ?></dd>
                            <dt>Limite</dt><dd><?= $autorizacao['limite_valor'] !== null ? $brl($autorizacao['limite_valor']) : 'Sem limite acordado — o teto será o total avaliado (' . $brl($avaliacaoTotal) . ')' ?></dd>
                            <dt>Canal</dt><dd><?= $e($canais[$autorizacao['canal']] ?? $autorizacao['canal']) ?></dd>
                            <dt>Evidência</dt><dd><?= $e($autorizacao['evidencia'] ?: '—') ?></dd>
                            <dt>Registrada</dt><dd><?= date('d/m/Y H:i', strtotime($autorizacao['registrada_em'])) ?> · <?= $e($autorizacao['registrada_por_nome'] ?? '—') ?></dd>
                        </dl>
                        <form action="./abrirOsOcorrencia" method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id_ocorrencia" value="<?= $id ?>">
                            <button type="submit" class="btn btn--primary btn--sm">Abrir OS direto</button>
                        </form>
                    <?php else: ?>
                        <form action="./registrarAutorizacaoOcorrencia" method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id_ocorrencia" value="<?= $id ?>">
                            <div class="form-field">
                                <label for="escopo">Escopo autorizado <span style="color:var(--color-danger)">*</span></label>
                                <textarea name="escopo" id="escopo" rows="2" required placeholder="O que o cliente autorizou fazer? (ex.: trocar pastilhas e discos dianteiros conforme avaliação)"></textarea>
                            </div>
                            <div class="form-grid" style="margin-top:8px;">
                                <div class="form-field">
                                    <label for="canal">Canal <span style="color:var(--color-danger)">*</span></label>
                                    <select name="canal" id="canal" data-no-ts required>
                                        <?php foreach ($canais as $chave => $rotulo): ?>
                                            <?php if ($chave === 'PORTAL') continue; // o portal registra a própria autorização ?>
                                            <option value="<?= $e($chave) ?>"><?= $e($rotulo) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label for="limite_valor">Limite de valor (R$)</label>
                                    <input type="text" inputmode="decimal" name="limite_valor" id="limite_valor" placeholder="Em branco = sem limite">
                                    <small class="text-muted fs-12">Sem limite, o teto será o total avaliado.</small>
                                </div>
                            </div>
                            <div class="form-field" style="margin-top:8px;">
                                <label for="evidencia">Evidência <span style="color:var(--color-danger)">*</span></label>
                                <textarea name="evidencia" id="evidencia" rows="2" required placeholder="Como foi dada a autorização? (ex.: confirmou por telefone às 14h20, falou com o Sr. João)"></textarea>
                            </div>
                            <button type="submit" class="btn btn--primary btn--sm" style="margin-top:10px;">Registrar autorização</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- ⑤ Orçamentos desta ocorrência -->
    <?php if (!empty($orcamentos)): ?>
        <section class="card">
            <div class="card__header"><div class="card__title">Orçamentos</div></div>
            <div class="card__body" style="padding:8px 20px 16px;">
                <?php foreach ($orcamentos as $orc): ?>
                    <?php $oid = (int) $orc['id_orcamento']; $pendente = $orc['status'] === 'pendente'; ?>
                    <div class="oc-passo">
                        <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:space-between;align-items:center;">
                            <div>
                                <strong><?= $e($orc['numero']) ?></strong>
                                <?php if ((int) $orc['versao'] > 1): ?><span class="text-muted fs-12">· v<?= (int) $orc['versao'] ?></span><?php endif; ?>
                                <span class="badge <?= $badgeOrcamento[$orc['status']] ?? 'badge--neutral' ?>" style="margin-left:6px;"><?= $e(Orcamento::ROTULOS[$orc['status']] ?? $orc['status']) ?></span>
                                <div class="text-muted fs-12">
                                    <?= $brl($orc['valor_total_orcamento']) ?>
                                    <?php if (!empty($orc['enviado_em'])): ?> · enviado em <?= date('d/m H:i', strtotime($orc['enviado_em'])) ?><?php else: ?> · ainda não enviado<?php endif; ?>
                                    <?php if (!empty($orc['data_validade'])): ?> · válido até <?= date('d/m/Y', strtotime($orc['data_validade'])) ?><?php endif; ?>
                                </div>
                            </div>
                            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                                <a class="btn btn--ghost btn--sm" href="./viewOrcamento?id=<?= $oid ?>">Abrir</a>
                                <?php if ($pendente): ?>
                                    <a class="btn btn--ghost btn--sm" href="./editOrcamento?id=<?= $oid ?>">Ajustar preços</a>
                                    <?php if (empty($orc['enviado_em'])): ?>
                                        <form action="./enviarOrcamento" method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id_orcamento" value="<?= $oid ?>">
                                            <input type="hidden" name="ocorrencia_id" value="<?= $id ?>">
                                            <button type="submit" class="btn btn--primary btn--sm">Marcar como enviado</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($pendente): ?>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;margin-top:12px;">
                                <details class="oc-details">
                                    <summary>Registrar aprovação do cliente</summary>
                                    <form action="./aprovarOrcamento" method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id_orcamento" value="<?= $oid ?>">
                                        <input type="hidden" name="ocorrencia_id" value="<?= $id ?>">
                                        <div class="form-field">
                                            <label>Canal</label>
                                            <select name="canal" data-no-ts>
                                                <?php foreach ($canais as $chave => $rotulo): ?>
                                                    <?php if ($chave === 'PORTAL') continue; ?>
                                                    <option value="<?= $e($chave) ?>"><?= $e($rotulo) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-field" style="margin-top:6px;">
                                            <label>Evidência (opcional)</label>
                                            <input type="text" name="evidencia" maxlength="500" placeholder="Ex.: aprovou por telefone às 15h">
                                        </div>
                                        <p class="text-muted fs-12" style="margin:6px 0;">Aprovar abre a ordem de serviço.</p>
                                        <button type="submit" class="btn btn--sm" style="background:var(--color-success);color:#fff;border-color:var(--color-success);">Confirmar aprovação</button>
                                    </form>
                                </details>
                                <details class="oc-details">
                                    <summary>Registrar recusa</summary>
                                    <form action="./recusarOrcamento" method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id_orcamento" value="<?= $oid ?>">
                                        <input type="hidden" name="ocorrencia_id" value="<?= $id ?>">
                                        <div class="form-field">
                                            <label>Motivo</label>
                                            <input type="text" name="motivo_recusa" maxlength="255" placeholder="Por que o cliente recusou?">
                                        </div>
                                        <button type="submit" class="btn btn--danger btn--sm" style="margin-top:8px;">Confirmar recusa</button>
                                    </form>
                                </details>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- ⑥ Autorizações registradas -->
    <?php if (!empty($autorizacoes)): ?>
        <section class="card">
            <div class="card__header"><div class="card__title">Autorizações do cliente</div></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Tipo</th><th>Situação</th><th>Valor</th><th>Canal</th><th>Registro</th></tr></thead>
                    <tbody>
                    <?php foreach ($autorizacoes as $a): ?>
                        <tr>
                            <td>
                                <?= ['ORCAMENTO' => 'Aprovação de orçamento', 'VALOR_ABERTO' => 'Valor em aberto', 'COMPLEMENTAR' => 'Complementar'][$a['tipo']] ?? $e($a['tipo']) ?>
                                <div class="text-muted fs-12" style="max-width:260px;"><?= $e($a['escopo']) ?></div>
                            </td>
                            <td><?= $e(AutorizacaoCliente::STATUS_LABEL[$a['status']] ?? $a['status']) ?></td>
                            <td>
                                <?php if ($a['tipo'] === 'COMPLEMENTAR'): ?>+ <?= $brl($a['valor_adicional']) ?>
                                <?php elseif ($a['limite_valor'] !== null): ?>até <?= $brl($a['limite_valor']) ?>
                                <?php else: ?>sem limite<?php endif; ?>
                            </td>
                            <td><?= $e($canais[$a['canal']] ?? $a['canal']) ?></td>
                            <td class="text-muted fs-12">
                                <?= date('d/m/Y H:i', strtotime($a['registrada_em'])) ?> · <?= $e($a['registrada_por_nome'] ?? '—') ?>
                                <?php if (!empty($a['evidencia'])): ?><div><?= $e($a['evidencia']) ?></div><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>

<!-- Coluna lateral -->
<div class="oc-col">
    <section class="card">
        <div class="card__header"><div class="card__title">Resumo</div></div>
        <div class="card__body" style="padding:16px 20px;">
            <dl class="oc-dl">
                <dt>Cliente</dt>
                <dd>
                    <a href="./viewCliente?id=<?= (int) $o['cliente_id'] ?>"><?= $e($o['cliente_nome']) ?></a>
                    <?php if ($o['cliente_cadastro'] === 'PROVISORIO'): ?>
                        <span class="badge badge--warning" title="Cadastro mínimo, feito no atendimento rápido">Provisório</span>
                        <div><a class="fs-12" href="./editCliente?id=<?= (int) $o['cliente_id'] ?>">Completar cadastro</a></div>
                    <?php endif; ?>
                </dd>
                <dt>Telefone</dt><dd><?= $e($o['cliente_telefone'] ?: '—') ?></dd>
                <dt>Veículo</dt>
                <dd>
                    <?= $e(strtoupper($o['placa'])) ?> · <?= $e($o['veiculo_modelo']) ?>
                    <?php if (!empty($o['veiculo_incompleto'])): ?>
                        <span class="badge badge--warning" title="Marca/modelo ainda não informados">Dados incompletos</span>
                        <div><a class="fs-12" href="./editVeiculo?id=<?= (int) $o['veiculo_id'] ?>">Completar veículo</a></div>
                    <?php endif; ?>
                </dd>
                <dt>Origem</dt><dd><?= $e(Ocorrencia::ORIGENS[$o['origem']] ?? $o['origem']) ?></dd>
                <dt>Chegada</dt><dd><?= date('d/m/Y H:i', strtotime($o['chegada_em'])) ?></dd>
                <dt>Registrada por</dt><dd><?= $e($o['criador_nome'] ?? '—') ?></dd>
                <dt>Responsável</dt><dd><?= $e($o['tecnico_nome'] ?? 'A definir') ?></dd>
                <?php if (!empty($o['urgencia_motivo'])): ?>
                    <dt>Urgência</dt>
                    <dd>
                        <?= $e($o['urgencia_motivo']) ?>
                        <div class="text-muted fs-12">
                            <?= $o['prioridade'] === 'URGENTE' ? 'Validada pelo administrador' : 'Declarada — aguardando validação do administrador' ?>
                        </div>
                    </dd>
                <?php endif; ?>
            </dl>
        </div>
    </section>

    <?php if (!$fechada): ?>
        <section class="card">
            <div class="card__header"><div class="card__title">Ajustes</div></div>
            <div class="card__body" style="padding:16px 20px;display:flex;flex-direction:column;gap:14px;">

                <?php if ($ehAdmin && $o['prioridade'] === 'ALTA'): ?>
                    <form action="./validarUrgenciaOcorrencia" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id_ocorrencia" value="<?= $id ?>">
                        <button type="submit" class="btn btn--primary btn--sm">Validar urgência</button>
                    </form>
                <?php endif; ?>

                <form action="./atribuirTecnicoOcorrencia" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_ocorrencia" value="<?= $id ?>">
                    <div class="form-field">
                        <label for="tecnico_id">Técnico responsável</label>
                        <select name="tecnico_id" id="tecnico_id" data-no-ts>
                            <option value="">A definir</option>
                            <?php foreach ($tecnicos as $t): ?>
                                <option value="<?= (int) $t['id'] ?>" <?= (int) $o['tecnico_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= $e($t['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn--ghost btn--sm" style="margin-top:8px;">Atualizar responsável</button>
                </form>

                <?php if (!$temOs): ?>
                    <details class="oc-details">
                        <summary style="color:var(--color-danger);">Cancelar ocorrência</summary>
                        <form action="./cancelarOcorrencia" method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id_ocorrencia" value="<?= $id ?>">
                            <div class="form-field">
                                <label>Motivo <span style="color:var(--color-danger)">*</span></label>
                                <input type="text" name="motivo" maxlength="255" required placeholder="Desistência do cliente, cadastro por engano...">
                            </div>
                            <button type="submit" class="btn btn--danger btn--sm" style="margin-top:8px;" onclick="return confirm('Cancelar esta ocorrência?')">Confirmar cancelamento</button>
                        </form>
                    </details>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php $linkEscopo = 'OCORRENCIA'; $linkEntidadeId = (int) $ocorrencia['id_ocorrencia']; require __DIR__ . '/../Portal/_linkPortal.php'; ?>

    <section class="card">
        <div class="card__header"><div class="card__title">Linha do tempo</div></div>
        <div class="card__body" style="padding:14px 16px;">
            <?php if (empty($linhaDoTempo)): ?>
                <p class="text-muted" style="margin:0;font-size:13px;">Sem registros.</p>
            <?php else: ?>
                <ol style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px;">
                    <?php foreach (array_reverse($linhaDoTempo) as $h): ?>
                        <li style="padding:10px 12px;background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);font-size:12.5px;">
                            <strong><?= $e(OcorrenciaFluxo::rotulo($h['estado_novo'])) ?></strong>
                            <?php if (!empty($h['estado_anterior'])): ?>
                                <span class="text-muted">← <?= $e(OcorrenciaFluxo::rotulo($h['estado_anterior'])) ?></span>
                            <?php endif; ?>
                            <div class="text-muted" style="font-size:11.5px;margin-top:2px;">
                                <?= date('d/m/Y H:i', strtotime($h['criado_em'])) ?> · <?= $e($h['usuario_nome'] ?? ucfirst(strtolower((string) $h['origem']))) ?>
                            </div>
                            <?php if (!empty($h['motivo'])): ?><div style="margin-top:4px;"><?= $e($h['motivo']) ?></div><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    </section>
</div>
</div>

<?php if ($avEmAndamento): ?>
<script>
    var PRODUTOS = <?= json_encode($produtosJs, $flagsJson) ?>;
    var ITENS_INICIAIS = <?= json_encode($itensJs, $flagsJson) ?>;
    var contadorLinhas = 0;

    function el(tag, attrs, filhos) {
        var n = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            if (k === 'text') n.textContent = attrs[k]; else n.setAttribute(k, attrs[k]);
        });
        (filhos || []).forEach(function (f) { n.appendChild(f); });
        return n;
    }

    function campo(rotulo, controle, classe) {
        var c = el('div', { 'class': 'form-field' + (classe ? ' ' + classe : '') }, [el('label', { text: rotulo }), controle]);
        return c;
    }

    function formatarBR(v) {
        return 'R$ ' + (Math.round(v * 100) / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalcular() {
        var total = 0;
        document.querySelectorAll('#linhasItens .oc-linha').forEach(function (l) {
            var q = parseFloat(String(l.querySelector('.f-qtd').value).replace(',', '.')) || 0;
            var p = parseFloat(String(l.querySelector('.f-preco').value).replace(',', '.')) || 0;
            total += q * p;
        });
        document.getElementById('totalItens').textContent = formatarBR(total);
    }

    function adicionarLinha(item) {
        item = item || {};
        var i = contadorLinhas++;
        var ehPeca = item.tipo === 'produto';
        var pre = 'itens[' + i + ']';

        var linha = el('div', { 'class': 'oc-linha' });

        linha.appendChild(el('input', { type: 'hidden', name: pre + '[tipo]', value: ehPeca ? 'produto' : 'servico' }));
        linha.appendChild(el('input', { type: 'hidden', name: pre + '[categoria]', 'class': 'f-cat', value: item.categoria || '' }));

        var rotuloTipo = el('div', { 'class': 'form-field' }, [el('label', { text: 'Tipo' }), el('div', { text: ehPeca ? 'Peça' : 'Serviço', style: 'font-size:13px;font-weight:600;padding:9px 0;' })]);
        linha.appendChild(rotuloTipo);

        var controleItem;
        if (ehPeca) {
            controleItem = el('select', { name: pre + '[produto_id]', 'data-no-ts': '', 'class': 'f-prod' });
            controleItem.appendChild(el('option', { value: '', text: 'Escolha a peça...' }));
            PRODUTOS.forEach(function (p) {
                var o = el('option', { value: p.id, text: p.nome });
                if (item.produto_id && Number(item.produto_id) === p.id) o.selected = true;
                controleItem.appendChild(o);
            });
            linha.appendChild(el('input', { type: 'hidden', name: pre + '[nome]', 'class': 'f-nome', value: item.nome || '' }));
        } else {
            controleItem = el('input', { type: 'text', name: pre + '[nome]', maxlength: '255', placeholder: 'Ex.: Troca de pastilhas', value: item.nome || '', 'class': 'f-nome' });
        }
        linha.appendChild(campo('Item', controleItem, 'oc-item'));

        var qtd = el('input', { type: 'text', inputmode: 'decimal', name: pre + '[quantidade]', 'class': 'f-qtd', value: String(item.quantidade != null ? item.quantidade : 1).replace('.', ',') });
        var preco = el('input', { type: 'text', inputmode: 'decimal', name: pre + '[preco]', 'class': 'f-preco', value: item.preco != null ? Number(item.preco).toFixed(2).replace('.', ',') : '' });
        linha.appendChild(campo('Qtd', qtd));
        linha.appendChild(campo('Valor un.', preco));

        var remover = el('button', { type: 'button', 'class': 'btn btn--ghost btn--sm', title: 'Remover', 'aria-label': 'Remover item', text: '×' });
        remover.addEventListener('click', function () { linha.remove(); recalcular(); });
        linha.appendChild(el('div', {}, [remover]));

        var obs = campo('Observação', el('input', { type: 'text', name: pre + '[observacao]', maxlength: '255', value: item.observacao || '' }), 'oc-linha__obs');
        linha.appendChild(obs);

        if (ehPeca) {
            controleItem.addEventListener('change', function () {
                var p = PRODUTOS.filter(function (x) { return String(x.id) === controleItem.value; })[0];
                if (p) {
                    linha.querySelector('.f-nome').value = p.nome;
                    linha.querySelector('.f-cat').value = p.categoria || '';
                    preco.value = Number(p.preco).toFixed(2).replace('.', ',');
                } else {
                    linha.querySelector('.f-nome').value = '';
                }
                recalcular();
            });
        }

        qtd.addEventListener('input', recalcular);
        preco.addEventListener('input', recalcular);

        document.getElementById('linhasItens').appendChild(linha);
        recalcular();
    }

    ITENS_INICIAIS.forEach(adicionarLinha);
    if (!ITENS_INICIAIS.length) adicionarLinha({ tipo: 'servico' });
</script>
<?php endif; ?>
