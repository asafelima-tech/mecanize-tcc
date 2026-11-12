<?php
/**
 * Integrações. Espera: $resumo, $urls, $https, $templates, $situacoes, $pendentes, $validade, $simulado, $resultado.
 */
$e = fn($v) => htmlspecialchars((string) $v);

$rotuloSituacao = [
    'PENDENTE' => 'Na fila de envio', 'ENVIADA' => 'Enviadas', 'ENTREGUE' => 'Entregues', 'LIDA' => 'Lidas', 'FALHA' => 'Falharam',
    'NAO_CONFIGURADO' => 'Não enviadas — canal não configurado', 'SEM_CONSENTIMENTO' => 'Não enviadas — sem consentimento',
    'SEM_CONTATO' => 'Não enviadas — cliente sem contato', 'REGISTRADA' => 'Registros manuais', 'CANCELADA' => 'Canceladas',
];
?>

<style>
    .ig-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; align-items: start; }
    .ig-var { font-family: monospace; font-size: 12px; background: var(--color-bg-alt); border: 1px solid var(--color-border); border-radius: 6px; padding: 1px 6px; margin: 0 4px 4px 0; display: inline-block; }
</style>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Integrações</h1>
        <p class="page-header__subtitle">O que já está conectado e o que ainda depende de contratação ou configuração. Nenhum valor secreto aparece aqui.</p>
    </div>
</div>

<?php if ($simulado): ?>
    <?php $alertaTipo = 'aviso'; $alertaTitulo = 'Pagamento SIMULADO habilitado'; $alertaMensagem = 'O provedor simulado serve para testar o fluxo sem mover dinheiro. Não use em produção: troque PAGAMENTOS_PROVEDOR por um provedor real.'; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>
<?php if (!$https): ?>
    <?php $alertaTipo = 'aviso'; $alertaTitulo = 'Endereço público sem HTTPS'; $alertaMensagem = 'WhatsApp e provedores de pagamento só chamam webhooks em HTTPS público. Defina APP_URL com o endereço real (https://...) quando publicar o sistema.'; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>
<?php if ($resultado): ?>
    <?php $alertaTipo = 'sucesso'; $alertaMensagem = 'Fila processada: ' . (int) $resultado['enviadas'] . ' enviada(s), ' . (int) $resultado['falhas'] . ' com falha, ' . (int) $resultado['puladas'] . ' ignorada(s).'; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<div class="ig-grid">
    <?php foreach ($resumo as $chave => $r): ?>
        <section class="card">
            <div class="card__header">
                <div class="card__title"><?= $e($r['nome']) ?></div>
                <span class="badge <?= $r['configurado'] ? 'badge--success' : 'badge--warning' ?>"><span class="badge__dot"></span><?= $r['configurado'] ? 'Configurado' : 'Pendente' ?></span>
            </div>
            <div class="card__body" style="padding:12px 20px 16px;font-size:13px;">
                <p style="margin:0 0 10px;color:var(--color-text-muted);"><?= $e($r['observacao']) ?></p>
                <?php if ($r['faltando']): ?>
                    <div class="fs-12" style="font-weight:700;margin-bottom:6px;">Falta definir no arquivo .env:</div>
                    <?php foreach ($r['faltando'] as $v): ?><span class="ig-var"><?= $e($v) ?></span><?php endforeach; ?>
                <?php else: ?>
                    <div class="fs-12" style="font-weight:600;">Todas as variáveis necessárias estão definidas.</div>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<section class="card" style="margin-top:16px;">
    <div class="card__header"><div class="card__title">Endereços que o provedor precisa chamar (webhooks)</div></div>
    <div class="card__body" style="padding:12px 20px 16px;font-size:13px;">
        <dl style="display:grid;grid-template-columns:150px 1fr;gap:8px 12px;margin:0;">
            <dt class="text-muted">WhatsApp</dt><dd style="margin:0;"><span class="ig-var"><?= $e($urls['whatsapp']) ?></span><div class="text-muted fs-12">Cadastre na Meta (Webhooks do app) com o mesmo WHATSAPP_VERIFY_TOKEN; assine "messages".</div></dd>
            <dt class="text-muted">Pagamento</dt><dd style="margin:0;"><span class="ig-var"><?= $e($urls['pagamento']) ?></span><div class="text-muted fs-12">Cadastre no painel do provedor. Toda notificação é conferida pela assinatura antes de qualquer efeito.</div></dd>
        </dl>
        <p class="text-muted fs-12" style="margin:12px 0 0;">Os links enviados ao cliente valem por <?= (int) $validade ?> dias (PORTAL_VALIDADE_DIAS) e podem ser revogados em cada atendimento, orçamento ou OS.</p>
    </div>
</section>

<section class="card" style="margin-top:16px;">
    <div class="card__header">
        <div class="card__title">Mensagens ao cliente — últimos 30 dias</div>
        <form method="post" action="./despacharMensagens" style="margin:0;">
            <?= csrf_field() ?>
            <button class="btn btn--ghost btn--sm" type="submit" <?= $pendentes ? '' : 'disabled' ?>>Enviar a fila agora</button>
        </form>
    </div>
    <div class="card__body" style="padding:12px 20px 16px;font-size:13px;">
        <?php if (!$situacoes): ?>
            <p class="text-muted" style="margin:0;">Nenhuma mensagem no período.</p>
        <?php else: ?>
            <table class="table"><tbody>
                <?php foreach ($rotuloSituacao as $cod => $rot): if (empty($situacoes[$cod])) continue; ?>
                    <tr><td><?= $e($rot) ?></td><td style="text-align:right;font-weight:700;"><?= (int) $situacoes[$cod] ?></td></tr>
                <?php endforeach; ?>
            </tbody></table>
        <?php endif; ?>
        <p class="text-muted fs-12" style="margin:10px 0 0;">A fila é enviada por um agendador (<span class="ig-var">php bin/enviar_mensagens.php</span> a cada minuto) e também ao fim de cada requisição quando há algo pendente. "Não enviadas" ficam visíveis na conversa do cliente — nada é marcado como enviado sem passar por um canal real.</p>
    </div>
</section>

<section class="card" style="margin-top:16px;">
    <div class="card__header"><div class="card__title">Templates do WhatsApp (mensagens fora da janela de 24 h)</div></div>
    <div class="card__body" style="padding:12px 20px 16px;font-size:13px;">
        <p class="text-muted fs-12" style="margin:0 0 10px;">A Meta só aceita mensagens iniciadas pela empresa se forem templates aprovados. Crie e aprove cada template na Meta e informe o nome na variável indicada. Parâmetros, nesta ordem: <strong>{{1}}</strong> primeiro nome · <strong>{{2}}</strong> placa · <strong>{{3}}</strong> link (ou número do documento). Sem template, a mensagem fora da janela fica registrada como "não enviada" com o motivo.</p>
        <table class="table">
            <thead><tr><th>Evento</th><th>Variável</th><th>Situação</th></tr></thead>
            <tbody>
                <?php foreach ($templates as $t): ?>
                    <tr>
                        <td><?= $e($t['rotulo']) ?></td>
                        <td><span class="ig-var"><?= $e($t['variavel']) ?></span></td>
                        <td><span class="badge <?= $t['configurado'] ? 'badge--success' : 'badge--neutral' ?>"><?= $t['configurado'] ? 'Definido' : 'Não definido' ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
