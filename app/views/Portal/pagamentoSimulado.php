<?php
/**
 * Página de pagamento do provedor SIMULADO (ambiente de teste) — ./portalPagamentoSimulado?ref=...
 * Espera: $cobranca (array), $ref, $sig, $retorno (string), $resultado (?array do receberWebhook).
 */
$h = fn($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$valor = 'R$ ' . number_format((float) $cobranca['valor'], 2, ',', '.');
$aberta = $cobranca['status'] === 'PENDENTE';
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Pagamento (ambiente de teste)</title>
    <link rel="stylesheet" href="./assets/vendor/inter.css">
    <link rel="stylesheet" href="./assets/css/global.css">
    <style>
        body { background: var(--color-bg); min-height: 100vh; padding: 16px; display: flex; justify-content: center; align-items: flex-start; }
        .sim { width: 100%; max-width: 440px; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-xl); box-shadow: var(--shadow-md); padding: 22px 20px; margin-top: 20px; }
        .sim-aviso { background: var(--color-warning-soft); border: 1px solid var(--color-warning); border-radius: var(--radius-lg); padding: 10px 12px; font-size: 12.5px; font-weight: 700; color: var(--color-text); margin-bottom: 16px; }
        .sim h1 { font-size: 17px; margin: 0 0 4px; color: var(--color-text); }
        .sim .valor { font-size: 28px; font-weight: 800; color: var(--color-text); margin: 6px 0 14px; }
        .sim p { font-size: 13px; color: var(--color-text-secondary); }
        .sim label { display: block; font-size: 13px; margin: 6px 0; color: var(--color-text); }
        .sim .acoes { display: flex; gap: 8px; margin-top: 16px; flex-wrap: wrap; }
        .sim .acoes .btn { flex: 1 1 140px; justify-content: center; }
        .sim .sim-erro { color: var(--color-danger); font-weight: 700; font-size: 13px; margin-top: 10px; }
    </style>
</head>

<body>
    <main class="sim">
        <div class="sim-aviso">AMBIENTE DE TESTE — nenhum dinheiro é movimentado. Esta página só existe porque o pagamento simulado foi habilitado de propósito.</div>

        <h1>Cobrança <?= $h($cobranca['referencia']) ?></h1>
        <div class="valor"><?= $h($valor) ?></div>

        <?php if ($resultado && $resultado['http'] !== 200): ?>
            <p class="sim-erro">O sistema não aceitou o evento (<?= $h($resultado['motivo']) ?>).</p>
        <?php endif; ?>

        <?php if (!$aberta): ?>
            <p>Esta cobrança já está <strong><?= $h(strtolower($cobranca['status'])) ?></strong>.</p>
            <?php if ($retorno): ?><div class="acoes"><a class="btn btn--primary" href="<?= $h($retorno) ?>">Voltar</a></div><?php endif; ?>
        <?php else: ?>
            <form method="post" action="./portalPagamentoSimulado">
                <?= csrf_field() ?>
                <input type="hidden" name="ref" value="<?= $h($ref) ?>">
                <input type="hidden" name="s" value="<?= $h($sig) ?>">
                <input type="hidden" name="r" value="<?= $h($retorno) ?>">

                <label><input type="radio" name="forma" value="PIX" checked> Pix</label>
                <label><input type="radio" name="forma" value="CARTAO_CREDITO"> Cartão de crédito (taxa fictícia de 3,99%)</label>

                <div class="acoes">
                    <button class="btn btn--primary btn--lg" type="submit" name="acao" value="pagar">Simular pagamento aprovado</button>
                    <button class="btn btn--ghost btn--lg" type="submit" name="acao" value="recusar">Simular pagamento recusado</button>
                </div>
            </form>
        <?php endif; ?>
    </main>
    <script src="./assets/js/csrf.js"></script>
</body>

</html>
