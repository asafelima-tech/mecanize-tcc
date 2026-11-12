<?php
/**
 * Portal do cliente (sem login) — ./portal?t=...
 * Espera: $v (PortalCaso::visao), $tokenBruto, $flash (?array), $dias (validade restante do link).
 */
$h  = fn($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$rs = fn($valor) => 'R$ ' . number_format((float) $valor, 2, ',', '.');
$dt = fn($valor, $fmt = 'd/m/Y') => $valor ? date($fmt, strtotime($valor)) : '';

$orcamentosParaDecidir = array_filter($v['orcamentos'], fn($o) => $o['pode_decidir']);
$orcamentosDecididos   = array_filter($v['orcamentos'], fn($o) => !$o['pode_decidir'] && $o['enviado'] && $o['status'] !== 'pendente');
$osPendentesPagar      = [];

foreach ($v['ordens'] as $o) {
    if ($o['cobrada'] && $o['saldo'] > 0) {
        $osPendentesPagar[] = $o;
    }
}

$situacao = $v['situacao'];
$classeSituacao = match ($situacao['codigo']) {
    'AGUARDANDO_RETIRADA', 'CONCLUIDA' => 'is-ok',
    'CANCELADA', 'EM_ESPERA'           => 'is-aviso',
    default                            => 'is-info',
};
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $h($v['oficina']['nome']) ?> · Acompanhamento do serviço</title>
    <link rel="stylesheet" href="./assets/vendor/inter.css">
    <link rel="stylesheet" href="./assets/css/global.css">

    <script>
        (function () {
            if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>

    <style>
        body { background: var(--color-bg); min-height: 100vh; padding: 16px; }
        .pt { max-width: 560px; margin: 0 auto; display: flex; flex-direction: column; gap: 14px; }
        .pt-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-xl); box-shadow: var(--shadow-md); padding: 20px 18px; }
        .pt-marca { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 800; color: var(--color-text); }
        .pt-marca .dot { width: 9px; height: 9px; border-radius: 50%; background: var(--color-primary-gradient); }
        .pt-ola { font-size: 13px; color: var(--color-text-muted); margin-top: 14px; }
        .pt-veiculo { font-size: 19px; font-weight: 800; color: var(--color-text); margin: 2px 0 0; }
        .pt-placa { font-family: monospace; font-size: 13px; font-weight: 700; background: var(--color-bg-alt); border: 1px solid var(--color-border); padding: 2px 8px; border-radius: var(--radius-sm); margin-left: 6px; letter-spacing: 1px; }
        .pt-situacao { border-radius: var(--radius-lg); padding: 14px 16px; margin-top: 16px; border: 1px solid transparent; }
        .pt-situacao.is-info { background: var(--color-info-soft); border-color: var(--color-info); }
        .pt-situacao.is-ok { background: var(--color-success-soft); border-color: var(--color-success); }
        .pt-situacao.is-aviso { background: var(--color-warning-soft); border-color: var(--color-warning); }
        .pt-situacao strong { display: block; font-size: 16px; color: var(--color-text); }
        .pt-situacao span { font-size: 13px; color: var(--color-text-secondary); }
        .pt-previsao { margin-top: 10px; font-size: 13px; color: var(--color-text-secondary); }
        .pt-titulo { font-size: 14px; font-weight: 800; color: var(--color-text); margin: 0 0 10px; }
        .pt-sub { font-size: 12px; color: var(--color-text-muted); margin: 0 0 10px; }
        .pt-flash { border-radius: var(--radius-lg); padding: 12px 14px; font-size: 13px; font-weight: 600; border: 1px solid; }
        .pt-flash.ok { background: var(--color-success-soft); border-color: var(--color-success); color: var(--color-text); }
        .pt-flash.pt-erro { background: var(--color-danger-soft); border-color: var(--color-danger); color: var(--color-text); }
        .pt-flash.info { background: var(--color-info-soft); border-color: var(--color-info); color: var(--color-text); }
        .pt-tabela { width: 100%; border-collapse: collapse; font-size: 13px; }
        .pt-tabela th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: var(--color-text-muted); padding: 6px 4px; border-bottom: 1px solid var(--color-border); }
        .pt-tabela td { padding: 8px 4px; border-bottom: 1px solid var(--color-border); color: var(--color-text); vertical-align: top; }
        .pt-tabela .num { text-align: right; white-space: nowrap; }
        .pt-total { display: flex; justify-content: space-between; font-weight: 800; font-size: 16px; padding: 12px 4px 0; color: var(--color-text); }
        .pt-aceite { display: flex; gap: 8px; align-items: flex-start; font-size: 13px; margin: 14px 0 10px; color: var(--color-text-secondary); }
        .pt-aceite input, .pt-parcela input[type=checkbox] { width: 22px; height: 22px; flex-shrink: 0; }
        .pt-aceite { padding: 6px 0; }
        .pt-parcela label { flex: 1; min-height: 32px; display: flex; align-items: center; cursor: pointer; }
        .pt-acoes { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
        .pt-acoes .btn { flex: 1 1 140px; justify-content: center; }
        .pt-linha { list-style: none; margin: 0; padding: 0; }
        .pt-linha li { position: relative; padding: 0 0 14px 22px; font-size: 13px; color: var(--color-text); }
        .pt-linha li::before { content: ""; position: absolute; left: 3px; top: 5px; width: 9px; height: 9px; border-radius: 50%; background: var(--color-success); }
        .pt-linha li::after { content: ""; position: absolute; left: 7px; top: 15px; bottom: -3px; width: 1px; background: var(--color-border-strong); }
        .pt-linha li:last-child::after { display: none; }
        .pt-linha li:last-child { padding-bottom: 0; }
        .pt-linha time { display: block; font-size: 11px; color: var(--color-text-muted); }
        .pt-parcela { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid var(--color-border); font-size: 13px; color: var(--color-text); }
        .pt-parcela:last-of-type { border-bottom: 0; }
        .pt-parcela .valor { margin-left: auto; font-weight: 700; white-space: nowrap; }
        .pt-pago { color: var(--color-success); font-weight: 700; font-size: 12px; }
        .pt-nota { font-size: 12px; color: var(--color-text-muted); margin-top: 10px; }
        .pt-retirada p { margin: 4px 0; font-size: 13px; color: var(--color-text-secondary); }
        .pt-pref { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 9px 0; border-bottom: 1px solid var(--color-border); font-size: 13px; color: var(--color-text); }
        .pt-pref:last-of-type { border-bottom: 0; }
        .pt-rodape { font-size: 11px; color: var(--color-text-muted); text-align: center; padding: 6px 0 18px; }
        .pt-rodape a { color: var(--color-text-muted); display: inline-block; padding: 10px 8px; }
    </style>
</head>

<body>
    <main class="pt">

        <section class="pt-card">
            <div class="pt-marca"><span class="dot"></span> <?= $h($v['oficina']['nome']) ?></div>
            <div class="pt-ola">Olá, <?= $h($v['primeiro_nome']) ?>. Acompanhe aqui o seu atendimento.</div>
            <div class="pt-veiculo">
                <?= $h($v['veiculo']['descricao'] ?: 'Seu veículo') ?>
                <?php if ($v['veiculo']['placa']): ?><span class="pt-placa"><?= $h(strtoupper($v['veiculo']['placa'])) ?></span><?php endif; ?>
            </div>

            <div class="pt-situacao <?= $classeSituacao ?>">
                <strong><?= $h($situacao['rotulo']) ?></strong>
                <span><?= $h($situacao['descricao']) ?></span>
            </div>

            <?php foreach ($v['ordens'] as $os): ?>
                <?php if ($os['previsao'] && !$os['finalizada']): ?>
                    <div class="pt-previsao">Previsão de conclusão (OS <?= $h($os['numero']) ?>): <strong><?= $h($dt($os['previsao'], 'd/m/Y \à\s H:i')) ?></strong>. É uma estimativa e pode mudar; avisaremos se mudar.</div>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>

        <?php if ($flash): ?>
            <div class="pt-flash <?= $flash['tipo'] === 'erro' ? 'pt-erro' : $h($flash['tipo']) ?>" role="status"><?= $h($flash['texto']) ?></div>
        <?php endif; ?>

        <?php foreach ($orcamentosParaDecidir as $o): ?>
            <section class="pt-card" id="orcamento-<?= $o['id'] ?>">
                <h2 class="pt-titulo">Orçamento <?= $h($o['numero']) ?> — aguardando a sua resposta</h2>
                <?php if ($o['diagnostico']): ?><p class="pt-sub"><strong>Diagnóstico:</strong> <?= $h($o['diagnostico']) ?></p><?php endif; ?>

                <table class="pt-tabela">
                    <thead><tr><th>Item</th><th class="num">Qtd</th><th class="num">Valor</th></tr></thead>
                    <tbody>
                        <?php foreach ($o['itens'] as $i): ?>
                            <tr>
                                <td><?= $h($i['nome']) ?><br><small style="color:var(--color-text-muted)"><?= $i['tipo'] === 'produto' ? 'Peça' : 'Serviço' ?></small></td>
                                <td class="num"><?= $h(rtrim(rtrim(number_format((float) $i['quantidade'], 2, ',', '.'), '0'), ',')) ?></td>
                                <td class="num"><?= $h($rs($i['subtotal'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="pt-total"><span>Total</span><span><?= $h($rs($o['valor'])) ?></span></div>
                <?php if ($o['validade']): ?><p class="pt-nota">Válido até <?= $h($dt($o['validade'])) ?>.<?= $o['prazo'] ? ' Prazo de execução: ' . $h($o['prazo']) . '.' : '' ?></p><?php endif; ?>

                <form method="post" action="./portalOrcamento">
                    <?= csrf_field() ?>
                    <input type="hidden" name="t" value="<?= $h($tokenBruto) ?>">
                    <input type="hidden" name="id_orcamento" value="<?= $o['id'] ?>">

                    <label class="pt-aceite">
                        <input type="checkbox" name="aceite" value="1">
                        <span>Li os itens e o valor acima e autorizo a execução do serviço.</span>
                    </label>
                    <div class="form-field" style="margin-top:10px;"><textarea name="motivo" rows="2" maxlength="255" aria-label="Motivo, se for recusar" placeholder="Se for recusar, conte o motivo (opcional)"></textarea></div>

                    <div class="pt-acoes">
                        <button class="btn btn--primary btn--lg" type="submit" name="decisao" value="aprovar">Aprovar orçamento</button>
                        <button class="btn btn--ghost btn--lg" type="submit" name="decisao" value="recusar">Recusar</button>
                    </div>
                </form>
            </section>
        <?php endforeach; ?>

        <?php foreach ($v['ordens'] as $os): ?>
            <?php foreach ($os['autorizacoes_pendentes'] as $a): ?>
                <section class="pt-card" id="autorizacao-<?= $a['id'] ?>">
                    <h2 class="pt-titulo">Precisamos da sua autorização — OS <?= $h($os['numero']) ?></h2>
                    <p class="pt-sub">Durante o serviço surgiu algo além do que foi autorizado. Nada será feito a mais sem a sua resposta.</p>
                    <p style="font-size:13px;color:var(--color-text)"><?= $h($a['motivo']) ?></p>
                    <div class="pt-total"><span>Valor adicional</span><span><?= $h($rs($a['valor'])) ?></span></div>

                    <form method="post" action="./portalAutorizacao">
                        <?= csrf_field() ?>
                        <input type="hidden" name="t" value="<?= $h($tokenBruto) ?>">
                        <input type="hidden" name="id_autorizacao" value="<?= $a['id'] ?>">
                        <div class="pt-acoes">
                            <button class="btn btn--primary btn--lg" type="submit" name="decisao" value="autorizar">Autorizar</button>
                            <button class="btn btn--ghost btn--lg" type="submit" name="decisao" value="recusar">Não autorizar</button>
                        </div>
                    </form>
                </section>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <?php foreach ($orcamentosDecididos as $o): ?>
            <section class="pt-card">
                <h2 class="pt-titulo">Orçamento <?= $h($o['numero']) ?></h2>
                <p style="font-size:13px;color:var(--color-text)">
                    <?= match ($o['status']) {
                        'aprovado'  => 'Aprovado' . ($o['decisao'] ? ' por você em ' . $dt($o['decisao']['criada_em'], 'd/m/Y \à\s H:i') : '') . '.',
                        'reprovado' => 'Recusado' . ($o['decisao'] ? ' por você em ' . $dt($o['decisao']['criada_em'], 'd/m/Y \à\s H:i') : '') . '. Se mudou de ideia, fale com a oficina.',
                        default     => 'Este orçamento foi cancelado pela oficina.',
                    } ?>
                    Total: <strong><?= $h($rs($o['valor'])) ?></strong>.
                </p>
            </section>
        <?php endforeach; ?>

        <?php foreach ($v['ordens'] as $os): ?>
            <?php if ($os['pronta_para_retirar']): ?>
                <section class="pt-card pt-retirada">
                    <h2 class="pt-titulo">Retirada do veículo</h2>
                    <p>Seu veículo está pronto na oficina.</p>
                    <?php if ($v['oficina']['endereco']): ?><p><strong>Endereço:</strong> <?= $h($v['oficina']['endereco']) ?></p><?php endif; ?>
                    <?php if ($v['oficina']['horario']): ?><p><strong>Horário:</strong> <?= $h($v['oficina']['horario']) ?></p><?php endif; ?>
                    <?php if ($v['oficina']['telefone']): ?><p><strong>Telefone:</strong> <?= $h($v['oficina']['telefone']) ?></p><?php endif; ?>
                    <?php if ($os['saldo'] > 0): ?><p><strong>Saldo a pagar:</strong> <?= $h($rs($os['saldo'])) ?><?= $v['pagamento_online'] ? ' (pode pagar abaixo ou na retirada)' : ' (na retirada)' ?></p><?php endif; ?>
                </section>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php foreach ($v['ordens'] as $os): ?>
            <?php if ($os['cobrada'] && $os['parcelas']): ?>
                <section class="pt-card" id="pagamento-<?= $os['id'] ?>">
                    <h2 class="pt-titulo">Pagamento — OS <?= $h($os['numero']) ?></h2>

                    <?php $pagaveis = 0; ?>
                    <form method="post" action="./portalPagar">
                        <?= csrf_field() ?>
                        <input type="hidden" name="t" value="<?= $h($tokenBruto) ?>">
                        <input type="hidden" name="id_os" value="<?= $os['id'] ?>">

                        <?php foreach ($os['parcelas'] as $p): ?>
                            <?php $escolhivel = !$p['pago'] && $v['pagamento_online'] && !$p['em_andamento']; $pagaveis += $escolhivel ? 1 : 0; ?>
                            <div class="pt-parcela">
                                <?php if ($escolhivel): ?>
                                    <input type="checkbox" name="parcelas[]" value="<?= $p['id'] ?>" id="parc-<?= $p['id'] ?>" checked>
                                <?php endif; ?>
                                <label <?= $escolhivel ? 'for="parc-' . $p['id'] . '"' : '' ?>>
                                    Parcela <?= $p['numero'] ?><?= $p['vencimento'] ? ' · vence ' . $h($dt($p['vencimento'])) : '' ?>
                                </label>
                                <span class="valor">
                                    <?php if ($p['pago']): ?>
                                        <span class="pt-pago">Pago<?= $p['pago_em'] ? ' em ' . $h($dt($p['pago_em'])) : '' ?></span>
                                    <?php elseif ($p['em_andamento']): ?>
                                        <span class="pt-nota" style="margin:0">Pagamento em andamento</span>
                                    <?php endif; ?>
                                    <?= $h($rs($p['valor'])) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($pagaveis > 0): ?>
                            <div class="pt-acoes"><button class="btn btn--primary btn--lg" type="submit">Pagar online</button></div>
                            <p class="pt-nota">Você será levado à página segura do provedor de pagamento. Não guardamos dados de cartão. A baixa só acontece quando o provedor confirma.</p>
                        <?php elseif ($os['saldo'] > 0 && !$v['pagamento_online']): ?>
                            <p class="pt-nota">O pagamento é feito na oficina.</p>
                        <?php endif; ?>
                    </form>
                </section>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($v['linha_do_tempo']): ?>
            <section class="pt-card">
                <h2 class="pt-titulo">Andamento</h2>
                <ul class="pt-linha">
                    <?php foreach ($v['linha_do_tempo'] as $e): ?>
                        <li><?= $h($e['titulo']) ?><time datetime="<?= $h($e['quando']) ?>"><?= $h($dt($e['quando'], 'd/m/Y \à\s H:i')) ?></time></li>
                    <?php endforeach; ?>
                </ul>
                <p class="pt-nota">Cada etapa só aparece depois de registrada pela oficina.</p>
            </section>
        <?php endif; ?>

        <section class="pt-card">
            <h2 class="pt-titulo">Como prefere receber os avisos?</h2>
            <p class="pt-sub">Mandamos só avisos do seu atendimento (veículo recebido, orçamento, serviço pronto, pagamento). Você pode mudar quando quiser.</p>

            <?php
            $c = $v['consentimento'];
            $canais = [];
            if ($c['tem_telefone']) {
                $canais[] = ['WHATSAPP', 'WhatsApp', $c['whatsapp'] === true ? 'Você aceitou receber.' : ($c['whatsapp'] === false ? 'Você pediu para parar.' : 'Ainda não autorizado.'), $c['whatsapp'] === true];
            }
            if ($c['tem_email']) {
                $canais[] = ['EMAIL', 'E-mail', $c['email'] === false ? 'Você pediu para parar.' : 'Você recebe os avisos.', $c['email'] !== false];
            }
            ?>
            <?php foreach ($canais as [$cod, $nome, $estado, $ativo]): ?>
                <form method="post" action="./portalConsentimento" class="pt-pref">
                    <?= csrf_field() ?>
                    <input type="hidden" name="t" value="<?= $h($tokenBruto) ?>">
                    <input type="hidden" name="canal" value="<?= $cod ?>">
                    <input type="hidden" name="concedido" value="<?= $ativo ? '0' : '1' ?>">
                    <span><strong><?= $nome ?></strong><br><small style="color:var(--color-text-muted)"><?= $h($estado) ?></small></span>
                    <button class="btn btn--sm <?= $ativo ? 'btn--ghost' : 'btn--primary' ?>" type="submit"><?= $ativo ? 'Parar de receber' : 'Quero receber' ?></button>
                </form>
            <?php endforeach; ?>
            <?php if (!$canais): ?><p class="pt-nota">Não há telefone nem e-mail no seu cadastro. Informe à oficina.</p><?php endif; ?>
        </section>

        <p class="pt-rodape">
            <?= $h($v['oficina']['nome']) ?><?= $v['oficina']['telefone'] ? ' · ' . $h($v['oficina']['telefone']) : '' ?><br>
            Este link é pessoal e vale por mais <?= (int) $dias ?> dia<?= $dias === 1 ? '' : 's' ?>. Não o compartilhe.
            · <a href="./privacidade">Privacidade</a>
        </p>
    </main>

    <script src="./assets/js/csrf.js"></script>
</body>

</html>
