<?php
/**
 * Uma conversa. Espera: $conversa, $mensagens, $contexto (ou null), $consent (ou null), $candidatos, $busca, $equipe,
 * $janelaAberta, $whatsappPronto, $emailPronto, $sucesso, $erro.
 */
require_once __DIR__ . '/../../models/Conversa.php';
require_once __DIR__ . '/../../models/MensagensCliente.php';

$c = $conversa;
$id = (int) $c['id_conversa'];
$e = fn($v) => htmlspecialchars((string) $v);
$brl = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$temCliente = !empty($c['cliente_id']);
$encerrada = $c['status'] === 'ENCERRADA';
$nomeContato = $temCliente ? $c['cliente_nome'] : ($c['nome_contato'] ?: 'Contato não identificado');

$rotuloStatusMsg = [
    'PENDENTE' => ['Na fila de envio', 'badge--info'], 'ENVIADA' => ['Enviada', 'badge--info'], 'ENTREGUE' => ['Entregue', 'badge--success'],
    'LIDA' => ['Lida', 'badge--success'], 'FALHA' => ['Falhou', 'badge--danger'], 'NAO_CONFIGURADO' => ['Não enviada — canal não configurado', 'badge--danger'],
    'SEM_CONSENTIMENTO' => ['Não enviada — sem consentimento', 'badge--warning'], 'SEM_CONTATO' => ['Não enviada — cliente sem contato', 'badge--danger'],
    'CANCELADA' => ['Cancelada', 'badge--neutral'], 'RECEBIDA' => ['Recebida', 'badge--neutral'], 'REGISTRADA' => ['Registro manual', 'badge--neutral'],
];
$statusConsent = fn($v) => $v === true ? 'Autorizou' : ($v === false ? 'Pediu para parar' : 'Nunca perguntado');

$msgOk = [
    'respondida' => 'Mensagem enviada — veja o resultado do envio na conversa.', 'registrado' => 'Registro salvo na conversa.',
    'atribuida' => 'Responsável atualizado.', 'status' => 'Situação atualizada.', 'vinculada' => 'Ligações atualizadas.', 'associada' => 'Cliente associado à conversa.',
];
$flashOk = $msgOk[$sucesso ?? ''] ?? null;
?>

<style>
    .cv-grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 16px; align-items: start; }
    .cv-col { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    .cv-fio { display: flex; flex-direction: column; gap: 10px; padding: 16px 20px; max-height: 560px; overflow-y: auto; }
    .cv-msg { max-width: 82%; padding: 9px 12px; border-radius: 12px; font-size: 13px; line-height: 1.45; border: 1px solid var(--color-border); background: var(--color-bg-alt); overflow-wrap: anywhere; }
    .cv-msg.saida { align-self: flex-end; background: var(--color-primary-soft); border-color: transparent; }
    .cv-msg.nota { align-self: center; max-width: 94%; background: var(--color-warning-soft); border-style: dashed; }
    .cv-msg .meta { display: block; margin-top: 5px; font-size: 11px; color: var(--color-text-muted); }
    .cv-msg .cv-erro { display: block; margin-top: 4px; font-size: 11.5px; color: var(--color-danger); font-weight: 600; }
    .cv-dl { display: grid; grid-template-columns: 96px 1fr; gap: 6px 10px; font-size: 13px; margin: 0; }
    .cv-dl dt { color: var(--color-text-muted); } .cv-dl dd { margin: 0; min-width: 0; overflow-wrap: anywhere; }
    .cv-form .btn { min-height: 44px; }
    .cv-form .form-field + .form-field { margin-top: 10px; }
    @media (max-width: 960px) { .cv-grid { grid-template-columns: minmax(0, 1fr); } .cv-msg { max-width: 94%; } }
</style>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= $e($nomeContato) ?></h1>
        <p class="page-header__subtitle">
            <span class="badge <?= Conversa::STATUS_BADGE[$c['status']] ?? 'badge--neutral' ?>"><span class="badge__dot"></span><?= $e(Conversa::STATUS_LABEL[$c['status']] ?? $c['status']) ?></span>
            <span class="text-muted"><?= $e(Conversa::CANAL_LABEL[$c['canal']] ?? $c['canal']) ?><?= $c['identificador'] ? ' · ' . $e($c['identificador']) : '' ?></span>
            <?php if (!$temCliente): ?><span class="badge badge--warning">Número não identificado</span><?php endif; ?>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="./listConversas" class="btn btn--ghost">Voltar</a>
        <?php if ($temCliente): ?><a href="./viewCliente?id=<?= (int) $c['cliente_id'] ?>" class="btn btn--ghost">Ficha do cliente</a><?php endif; ?>
    </div>
</div>

<?php if ($flashOk): ?><?php $alertaTipo = 'sucesso'; $alertaMensagem = $flashOk; require __DIR__ . '/../layout/_alerta.php'; ?><?php endif; ?>
<?php if (!empty($erro)): ?><?php $alertaTipo = 'erro'; $alertaMensagem = $erro; require __DIR__ . '/../layout/_alerta.php'; ?><?php endif; ?>

<div class="cv-grid">
<div class="cv-col">

    <section class="card">
        <div class="cv-fio" id="cv-fio" tabindex="0" aria-label="Mensagens da conversa">
            <?php if (empty($mensagens)): ?>
                <p class="text-muted" style="text-align:center;margin:20px 0;">Ainda não há mensagens nesta conversa.</p>
            <?php endif; ?>
            <?php foreach ($mensagens as $m):
                $classe = $m['direcao'] === 'SAIDA' ? 'saida' : ($m['direcao'] === 'NOTA' ? 'nota' : 'entrada');
                [$rotSt, $badgeSt] = $rotuloStatusMsg[$m['status']] ?? [$m['status'], 'badge--neutral'];
                $autor = $m['direcao'] === 'ENTRADA' ? $nomeContato : ($m['autor_nome'] ?: ($m['tipo'] === 'AUTOMATICA' ? 'Sistema' : 'Equipe'));
                ?>
                <div class="cv-msg <?= $classe ?>">
                    <?php if ($m['evento']): ?><strong class="fs-12"><?= $e(MensagensCliente::EVENTOS[$m['evento']] ?? $m['evento']) ?></strong><br><?php endif; ?>
                    <?= nl2br($e($m['corpo'])) ?>
                    <?php if (!empty($m['erro'])): ?><span class="cv-erro"><?= $e($m['erro']) ?></span><?php endif; ?>
                    <span class="meta">
                        <?= $m['direcao'] === 'NOTA' ? 'Nota interna' : ($m['canal'] === 'MANUAL' ? 'Registro manual' : $e(Conversa::CANAL_LABEL[$m['canal']] ?? $m['canal'])) ?>
                        · <?= $e($autor) ?> · <?= date('d/m H:i', strtotime($m['criada_em'])) ?>
                        <?php if ($m['direcao'] !== 'NOTA'): ?> · <span class="badge <?= $badgeSt ?>"><?= $e($rotSt) ?></span><?php endif; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if (!$encerrada): ?>
        <section class="card">
            <div class="card__header"><div class="card__title">Responder</div></div>
            <div class="card__body cv-form" style="padding:12px 20px 18px;">
                <?php if ($c['canal'] === 'MANUAL'): ?>
                    <p class="text-muted fs-12" style="margin:0 0 10px;">Esta conversa não tem canal de envio (o cliente não tem telefone nem e-mail cadastrados). Use "Registrar contato" para anotar o que foi conversado por fora.</p>
                <?php elseif ($c['canal'] === 'WHATSAPP' && !$whatsappPronto): ?>
                    <p class="text-muted fs-12" style="margin:0 0 10px;">O WhatsApp não está configurado neste sistema, então daqui não dá para enviar. Registre o contato manualmente.</p>
                <?php elseif ($c['canal'] === 'WHATSAPP' && !$janelaAberta): ?>
                    <p class="text-muted fs-12" style="margin:0 0 10px;">Já passaram mais de 24 h desde a última mensagem do cliente. Nessa situação o WhatsApp só aceita mensagens de template aprovado (as automáticas do sistema usam). Para conversar livremente, registre o contato ou aguarde o cliente escrever.</p>
                <?php elseif ($c['canal'] === 'EMAIL' && !$emailPronto): ?>
                    <p class="text-muted fs-12" style="margin:0 0 10px;">O e-mail não está configurado neste sistema. Registre o contato manualmente.</p>
                <?php else: ?>
                    <form method="post" action="./responderConversa">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id_conversa" value="<?= $id ?>">
                        <div class="form-field">
                            <label for="cv-resposta">Mensagem para o cliente</label>
                            <textarea id="cv-resposta" name="texto" rows="3" maxlength="4000" required placeholder="Escreva a resposta ao cliente..."></textarea>
                        </div>
                        <div style="display:flex;justify-content:flex-end;margin-top:8px;"><button class="btn btn--primary" type="submit">Enviar pelo <?= $e(Conversa::CANAL_LABEL[$c['canal']] ?? $c['canal']) ?></button></div>
                    </form>
                    <hr style="border:0;border-top:1px solid var(--color-border);margin:14px 0;">
                <?php endif; ?>

                <form method="post" action="./registrarContatoConversa">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_conversa" value="<?= $id ?>">
                    <div class="form-field">
                        <label for="cv-contato">Registrar contato feito por fora do sistema, ou nota interna</label>
                        <textarea id="cv-contato" name="texto" rows="2" maxlength="4000" required placeholder="Ex.: Liguei às 15h; o cliente aprovou por telefone..."></textarea>
                    </div>
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px;align-items:center;flex-wrap:wrap;">
                        <select name="direcao" class="toolbar-select">
                            <option value="SAIDA">Eu falei com o cliente</option>
                            <option value="ENTRADA">O cliente falou comigo</option>
                            <option value="NOTA">Nota interna (o cliente não vê)</option>
                        </select>
                        <button class="btn btn--ghost" type="submit">Registrar</button>
                    </div>
                </form>
            </div>
        </section>
    <?php endif; ?>

</div>
<div class="cv-col">

    <section class="card">
        <div class="card__header"><div class="card__title">Conversa</div></div>
        <div class="card__body" style="padding:12px 20px 16px;">
            <dl class="cv-dl">
                <dt>Responsável</dt><dd><?= $c['responsavel_nome'] ? $e($c['responsavel_nome']) : '<span class="text-muted">ninguém</span>' ?></dd>
                <?php if ($c['assunto']): ?><dt>Assunto</dt><dd><?= $e($c['assunto']) ?></dd><?php endif; ?>
                <dt>Aberta em</dt><dd><?= date('d/m/Y H:i', strtotime($c['criada_em'])) ?></dd>
                <?php if ($c['canal'] === 'WHATSAPP'): ?><dt>Janela 24 h</dt><dd><?= $janelaAberta ? 'Aberta (pode responder livremente)' : 'Fechada (só template aprovado)' ?></dd><?php endif; ?>
            </dl>

            <form method="post" action="./atribuirConversa" style="display:flex;gap:6px;margin-top:12px;">
                <?= csrf_field() ?>
                <input type="hidden" name="id_conversa" value="<?= $id ?>">
                <select name="responsavel_id" class="toolbar-select" style="flex:1;min-width:0;">
                    <option value="0">Sem responsável</option>
                    <?php foreach ($equipe as $u): ?>
                        <option value="<?= (int) $u['id_usuario'] ?>" <?= (int) $c['responsavel_id'] === (int) $u['id_usuario'] ? 'selected' : '' ?>><?= $e($u['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn--ghost btn--sm" type="submit">Atribuir</button>
            </form>

            <form method="post" action="./statusConversa" style="margin-top:8px;">
                <?= csrf_field() ?>
                <input type="hidden" name="id_conversa" value="<?= $id ?>">
                <?php if ($encerrada): ?>
                    <button class="btn btn--ghost btn--sm" name="status" value="ABERTA" type="submit">Reabrir conversa</button>
                <?php else: ?>
                    <button class="btn btn--ghost btn--sm" name="status" value="ENCERRADA" type="submit">Encerrar conversa</button>
                <?php endif; ?>
            </form>
        </div>
    </section>

    <?php if (!$temCliente): ?>
        <section class="card">
            <div class="card__header"><div class="card__title">Quem é este contato?</div></div>
            <div class="card__body" style="padding:12px 20px 16px;">
                <p class="text-muted fs-12" style="margin:0 0 10px;">O número não bate com nenhum cadastro. Procure o cliente e associe — o histórico da conversa vai junto. Nada é cadastrado automaticamente.</p>
                <form method="get" action="./viewConversa" style="display:flex;gap:6px;">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <div class="form-field" style="flex:1;min-width:0;"><input type="text" name="q_cliente" value="<?= $e($busca) ?>" placeholder="Nome ou telefone" aria-label="Buscar cliente"></div>
                    <button class="btn btn--ghost btn--sm" type="submit">Buscar</button>
                </form>
                <?php foreach ($candidatos as $cand): ?>
                    <form method="post" action="./associarConversa" style="display:flex;justify-content:space-between;gap:8px;align-items:center;padding:7px 0;font-size:13px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id_conversa" value="<?= $id ?>">
                        <input type="hidden" name="cliente_id" value="<?= (int) $cand['id_cliente'] ?>">
                        <span><?= $e($cand['nome']) ?><br><small class="text-muted"><?= $e($cand['telefone']) ?></small></span>
                        <button class="btn btn--primary btn--sm" type="submit">Associar</button>
                    </form>
                <?php endforeach; ?>
                <?php if ($busca !== '' && !$candidatos): ?>
                    <p class="text-muted fs-12" style="margin:10px 0 0;">Nenhum cliente encontrado. <a href="./saveCliente">Cadastre o cliente</a> e volte aqui.</p>
                <?php endif; ?>
            </div>
        </section>
    <?php else: ?>
        <section class="card">
            <div class="card__header"><div class="card__title">Ligar a um atendimento</div></div>
            <div class="card__body" style="padding:12px 20px 16px;">
                <form method="post" action="./vincularConversa">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_conversa" value="<?= $id ?>">
                    <?php
                    $seletores = [
                        'veiculo_id' => ['Veículo', $contexto['veiculos'], 'id_veiculo', fn($r) => $r['placa'] . ' · ' . $r['modelo'], $c['veiculo_id']],
                        'ocorrencia_id' => ['Atendimento', $contexto['ocorrencias'], 'id_ocorrencia', fn($r) => $r['numero'], $c['ocorrencia_id']],
                        'orcamento_id' => ['Orçamento', $contexto['orcamentos'], 'id_orcamento', fn($r) => $r['numero'] . ' · ' . $brl($r['valor_total_orcamento']), $c['orcamento_id']],
                        'ordem_servico_id' => ['Ordem de serviço', $contexto['ordens'], 'id_ordem_servico', fn($r) => $r['numero'] . ' · ' . $brl($r['valor_total_os']), $c['ordem_servico_id']],
                    ];
                    foreach ($seletores as $campo => [$rotulo, $linhas, $pk, $texto, $atual]): ?>
                        <div class="form-field">
                            <label for="vinc-<?= $campo ?>"><?= $e($rotulo) ?></label>
                            <select id="vinc-<?= $campo ?>" name="<?= $campo ?>">
                                <option value="0">—</option>
                                <?php foreach ($linhas as $r): ?>
                                    <option value="<?= (int) $r[$pk] ?>" <?= (int) $atual === (int) $r[$pk] ? 'selected' : '' ?>><?= $e($texto($r)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                    <div style="margin-top:10px;"><button class="btn btn--ghost btn--sm" type="submit">Salvar ligações</button></div>
                </form>

                <div style="margin-top:12px;font-size:13px;display:flex;flex-direction:column;gap:4px;">
                    <?php if ($c['ocorrencia_id']): ?><a href="./viewOcorrencia?id=<?= (int) $c['ocorrencia_id'] ?>">Abrir atendimento <?= $e($c['ocorrencia_numero']) ?></a><?php endif; ?>
                    <?php if ($c['orcamento_id']): ?><a href="./viewOrcamento?id=<?= (int) $c['orcamento_id'] ?>">Abrir orçamento <?= $e($c['orcamento_numero']) ?></a><?php endif; ?>
                    <?php if ($c['ordem_servico_id']): ?><a href="./viewOrdemServico?id=<?= (int) $c['ordem_servico_id'] ?>">Abrir OS <?= $e($c['os_numero']) ?></a><?php endif; ?>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card__header"><div class="card__title">Avisos ao cliente</div></div>
            <div class="card__body" style="padding:12px 20px 16px;">
                <dl class="cv-dl">
                    <dt>WhatsApp</dt><dd><?= $e($statusConsent($consent['whatsapp'])) ?></dd>
                    <dt>E-mail</dt><dd><?= $e($statusConsent($consent['email'])) ?></dd>
                </dl>
                <p class="text-muted fs-12" style="margin:10px 0 0;">Para registrar a autorização do cliente, use a <a href="./viewCliente?id=<?= (int) $c['cliente_id'] ?>">ficha dele</a>.</p>
            </div>
        </section>
    <?php endif; ?>

</div>
</div>

<script>
    // Abre na mensagem mais recente (a conversa cresce para baixo).
    (function () { var fio = document.getElementById('cv-fio'); if (fio) fio.scrollTop = fio.scrollHeight; })();
</script>
