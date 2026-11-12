<?php
/**
 * Central de conversas. Espera: $conversas, $contagem, $naoLidas, $aba, $busca, $responsavel, $soNaoLidas, $canais, $sucesso.
 */
require_once __DIR__ . '/../../models/Conversa.php';

$e = fn($v) => htmlspecialchars((string) $v);
$n = fn(string $s) => (int) ($contagem[$s] ?? 0);

$abas = [
    'abertas' => ['Em aberto', $n('ABERTA') + $n('AGUARDANDO_CLIENTE')],
    'ABERTA' => ['Abertas', $n('ABERTA')],
    'AGUARDANDO_CLIENTE' => ['Aguardando o cliente', $n('AGUARDANDO_CLIENTE')],
    'ENCERRADA' => ['Encerradas', $n('ENCERRADA')],
    'todas' => ['Todas', array_sum($contagem)],
];

$filtro = function (array $extra) use ($aba, $busca, $responsavel, $soNaoLidas) {
    $q = array_filter(['aba' => $aba, 'busca' => $busca, 'responsavel' => $responsavel, 'nao_lidas' => $soNaoLidas ? 1 : null], fn($v) => $v !== '' && $v !== null);

    return './listConversas?' . http_build_query(array_merge($q, $extra));
};

$mensagens = ['iniciada' => 'Conversa aberta.'];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Conversas</h1>
        <p class="page-header__subtitle">O que os clientes escrevem e o que o sistema e a equipe responderam — com o resultado real de cada envio.</p>
    </div>
</div>

<?php if (!$canais['whatsapp'] && !$canais['email']): ?>
    <?php $alertaTipo = 'aviso'; $alertaTitulo = 'Nenhum canal de envio configurado';
    $alertaMensagem = 'Os avisos aos clientes ficam registrados aqui, mas não saem. ' . ((($_SESSION['user_tipo'] ?? '') === 'ADMIN') ?'Veja em Integrações o que falta configurar.' : 'Peça ao administrador para configurar o WhatsApp ou o e-mail.');
    require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<?php if (!empty($mensagens[$sucesso ?? ''])): ?>
    <?php $alertaTipo = 'sucesso'; $alertaMensagem = $mensagens[$sucesso]; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<section class="card">
    <div class="tabs" style="overflow-x:auto;">
        <?php foreach ($abas as $chave => [$rotulo, $q]): ?>
            <a class="tabs__item <?= $aba === $chave ? 'is-active' : '' ?>" href="<?= $e($filtro(['aba' => $chave])) ?>" style="text-decoration:none;white-space:nowrap;">
                <?= $e($rotulo) ?><?php if ($q > 0): ?> <span class="text-muted fs-12">(<?= (int) $q ?>)</span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <form method="GET" action="./listConversas" class="table-toolbar">
        <input type="hidden" name="aba" value="<?= $e($aba) ?>">
        <div class="search-wrap">
            <input type="text" name="busca" value="<?= $e($busca) ?>" placeholder="Buscar por cliente, telefone, placa ou OS...">
        </div>
        <select name="responsavel" class="toolbar-select" onchange="this.form.submit()">
            <option value="">Responsável: todos</option>
            <option value="eu" <?= $responsavel === 'eu' ? 'selected' : '' ?>>Comigo</option>
            <option value="sem" <?= $responsavel === 'sem' ? 'selected' : '' ?>>Sem responsável</option>
        </select>
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
            <input type="checkbox" name="nao_lidas" value="1" <?= $soNaoLidas ? 'checked' : '' ?> onchange="this.form.submit()"> Só não lidas<?= $naoLidas ? ' (' . (int) $naoLidas . ')' : '' ?>
        </label>
        <span class="text-muted fs-12"><?= count($conversas) ?> resultado(s)</span>
    </form>

    <style>
        /* Celular: lista em cartões (a tabela larga rolava e escondia o botão "Abrir"). */
        .cv-cartoes { display: none; list-style: none; margin: 0; padding: 0 12px 12px; gap: 10px; }
        .cv-cartao { display: block; padding: 12px 14px; border: 1px solid var(--color-border); border-radius: var(--radius-lg); background: var(--color-surface); text-decoration: none; color: inherit; min-height: 44px; }
        .cv-cartao.nova { border-left: 4px solid var(--color-primary); }
        .cv-cartao__topo { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
        .cv-cartao__nome { font-weight: 700; font-size: 15px; overflow-wrap: anywhere; }
        .cv-cartao__hora { font-size: 12px; color: var(--color-text-muted); white-space: nowrap; }
        .cv-cartao__canal { font-size: 12px; color: var(--color-text-muted); overflow-wrap: anywhere; }
        .cv-cartao__texto { margin-top: 6px; font-size: 13px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .cv-cartao__rodape { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-top: 8px; font-size: 12px; color: var(--color-text-muted); }
        @media (max-width: 720px) {
            .cv-cartoes { display: grid; }
            .cv-tabela { display: none; }
        }
    </style>

    <?php if (!empty($conversas)): ?>
        <ul class="cv-cartoes">
            <?php foreach ($conversas as $c): ?>
                <li>
                    <a class="cv-cartao <?= (int) $c['nao_lidas'] > 0 ? 'nova' : '' ?>" href="./viewConversa?id=<?= (int) $c['id_conversa'] ?>">
                        <div class="cv-cartao__topo">
                            <span class="cv-cartao__nome"><?= $e($c['cliente_id'] ? $c['cliente_nome'] : ($c['nome_contato'] ?: 'Contato')) ?></span>
                            <span class="cv-cartao__hora"><?= date('d/m H:i', strtotime($c['ultima_mensagem_em'])) ?></span>
                        </div>
                        <div class="cv-cartao__canal"><?= $e(Conversa::CANAL_LABEL[$c['canal']] ?? $c['canal']) ?><?= $c['identificador'] ? ' · ' . $e($c['identificador']) : '' ?><?= $c['placa'] ? ' · ' . $e($c['placa']) : '' ?></div>
                        <div class="cv-cartao__texto"><?= $e(preg_replace('/\s+/', ' ', (string) $c['ultima_mensagem'])) ?></div>
                        <div class="cv-cartao__rodape">
                            <span class="badge <?= Conversa::STATUS_BADGE[$c['status']] ?? 'badge--neutral' ?>"><span class="badge__dot"></span><?= $e(Conversa::STATUS_LABEL[$c['status']] ?? $c['status']) ?></span>
                            <?php if ((int) $c['nao_lidas'] > 0): ?><span class="badge badge--danger"><?= (int) $c['nao_lidas'] ?> nova(s)</span><?php endif; ?>
                            <?php if (!$c['cliente_id']): ?><span class="badge badge--warning">Não identificado</span><?php endif; ?>
                            <?php if ((int) $c['problemas'] > 0): ?><span class="badge badge--danger"><?= (int) $c['problemas'] ?> sem envio</span><?php endif; ?>
                            <?= $c['responsavel_nome'] ? '<span>· ' . $e($c['responsavel_nome']) . '</span>' : '' ?>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="table-wrap cv-tabela">
        <table class="table">
            <thead>
                <tr><th>Contato</th><th>Última mensagem</th><th>Ligada a</th><th>Responsável</th><th>Quando</th><th>Situação</th><th></th></tr>
            </thead>
            <tbody>
                <?php if (empty($conversas)): ?>
                    <tr><td colspan="7" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">Nenhuma conversa neste filtro.</td></tr>
                <?php else: foreach ($conversas as $c): ?>
                    <tr<?= (int) $c['nao_lidas'] > 0 ? ' style="font-weight:600;"' : '' ?>>
                        <td>
                            <?php if ($c['cliente_id']): ?>
                                <?= $e($c['cliente_nome']) ?>
                                <?php if ($c['cliente_cadastro'] === 'PROVISORIO'): ?><span class="badge badge--warning" title="Cadastro provisório">Provisório</span><?php endif; ?>
                            <?php else: ?>
                                <?= $e($c['nome_contato'] ?: 'Contato') ?> <span class="badge badge--warning">Não identificado</span>
                            <?php endif; ?>
                            <div class="text-muted fs-12"><?= $e(Conversa::CANAL_LABEL[$c['canal']] ?? $c['canal']) ?><?= $c['identificador'] ? ' · ' . $e($c['identificador']) : '' ?></div>
                        </td>
                        <td style="max-width:300px;">
                            <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= $e(preg_replace('/\s+/', ' ', (string) $c['ultima_mensagem'])) ?></div>
                            <?php if ((int) $c['problemas'] > 0): ?><span class="badge badge--danger" title="Mensagens que não foram entregues ao cliente"><?= (int) $c['problemas'] ?> sem envio</span><?php endif; ?>
                        </td>
                        <td class="text-muted fs-12">
                            <?= $c['placa'] ? $e($c['placa']) : '' ?>
                            <?= $c['os_numero'] ? ' · OS ' . $e($c['os_numero']) : ($c['orcamento_numero'] ? ' · Orç. ' . $e($c['orcamento_numero']) : ($c['ocorrencia_numero'] ? ' · ' . $e($c['ocorrencia_numero']) : '')) ?>
                        </td>
                        <td class="text-muted"><?= $c['responsavel_nome'] ? $e($c['responsavel_nome']) : '—' ?></td>
                        <td class="text-muted"><?= date('d/m H:i', strtotime($c['ultima_mensagem_em'])) ?></td>
                        <td>
                            <span class="badge <?= Conversa::STATUS_BADGE[$c['status']] ?? 'badge--neutral' ?>"><span class="badge__dot"></span><?= $e(Conversa::STATUS_LABEL[$c['status']] ?? $c['status']) ?></span>
                            <?php if ((int) $c['nao_lidas'] > 0): ?><span class="badge badge--danger"><?= (int) $c['nao_lidas'] ?> nova(s)</span><?php endif; ?>
                        </td>
                        <td><a class="btn btn--ghost btn--sm" href="./viewConversa?id=<?= (int) $c['id_conversa'] ?>">Abrir</a></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
