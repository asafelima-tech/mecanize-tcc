<?php
/**
 * Tela pública de rastreamento (sem login) — acessada via ./rastrear?token=...
 * Espera: $tipo ('os'|'orcamento'), $dados (array mínimo e público).
 */
$ehOS = $tipo === 'os';

$numero  = htmlspecialchars($ehOS ? ($dados['numero_os'] ?? '') : ($dados['numero'] ?? ''));
$cliente = htmlspecialchars(explode(' ', $dados['nome_cliente'] ?? $dados['cliente_nome'] ?? '')[0] ?: 'Cliente');
$veiculo = htmlspecialchars($dados['modelo'] ?? '-');
$placa   = htmlspecialchars(strtoupper($dados['placa'] ?? '-'));

if ($ehOS) {
    $etapas = ['ABERTA' => 'Aberta', 'EM_ANDAMENTO' => 'Em Andamento', 'AGUARDANDO_PECA' => 'Aguardando Peça', 'CONCLUIDA' => 'Concluída'];
    $statusAtual = $dados['status'] ?? 'ABERTA';
    $cancelado = $statusAtual === 'CANCELADA';
} else {
    $etapas = ['pendente' => 'Criado', '_aguardando' => 'Aguardando aprovação', '_decisao' => match ($dados['status'] ?? '') {
        'aprovado'  => 'Aprovado',
        'reprovado' => 'Recusado',
        default     => 'Aprovação',
    }];
    $statusAtual = $dados['status'] ?? 'pendente';
    $cancelado = $statusAtual === 'cancelado';
}

$indiceAtual = $ehOS
    ? array_search($statusAtual, array_keys($etapas), true)
    : (in_array($statusAtual, ['aprovado', 'reprovado'], true) ? 2 : ($statusAtual === 'pendente' ? 1 : 0));
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mecanize · Rastreamento <?= $numero ? '#' . $numero : '' ?></title>
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
        body {
            background: var(--color-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .rastreio-card {
            width: 100%;
            max-width: 420px;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            padding: 28px 24px;
        }

        .rastreio-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            font-weight: 800;
            color: var(--color-text);
            margin-bottom: 18px;
        }

        .rastreio-brand .dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--color-primary-gradient);
        }

        .rastreio-titulo {
            font-size: 13px;
            color: var(--color-text-muted);
        }

        .rastreio-veiculo {
            font-size: 19px;
            font-weight: 800;
            color: var(--color-text);
            margin: 2px 0 20px;
        }

        .rastreio-veiculo .placa {
            font-family: monospace;
            font-size: 13px;
            font-weight: 700;
            background: var(--color-bg-alt);
            border: 1px solid var(--color-border);
            padding: 2px 8px;
            border-radius: var(--radius-sm);
            margin-left: 6px;
            letter-spacing: 1px;
        }

        .os-stepper {
            display: flex;
            align-items: flex-start;
        }

        .os-stepper__step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            text-align: center;
        }

        .os-stepper__circle {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            border: 2px solid var(--color-border-strong);
            color: var(--color-text-muted);
            background: var(--color-surface);
        }

        .os-stepper__circle.is-done {
            background: var(--color-success);
            border-color: var(--color-success);
            color: #fff;
        }

        .os-stepper__circle.is-current {
            background: var(--color-primary-soft);
            border-color: var(--color-primary);
            color: var(--color-primary);
            box-shadow: 0 0 0 4px var(--color-primary-soft);
        }

        .os-stepper__label {
            font-size: 10.5px;
            font-weight: 600;
            color: var(--color-text-muted);
            margin-top: 6px;
        }

        .os-stepper__label.is-active {
            color: var(--color-text);
        }

        .os-stepper__line {
            flex: 1;
            height: 2px;
            background: var(--color-border-strong);
            margin-top: 14px;
        }

        .os-stepper__line.is-done {
            background: var(--color-success);
        }

        .rastreio-rodape {
            margin-top: 24px;
            font-size: 11px;
            color: var(--color-text-muted);
            text-align: center;
        }
    </style>
</head>

<body>

    <div class="rastreio-card">
        <div class="rastreio-brand"><span class="dot"></span> Mecanize</div>

        <div class="rastreio-titulo">
            Olá, <?= $cliente ?>. Acompanhe abaixo <?= $ehOS ? 'sua Ordem de Serviço' : 'seu orçamento' ?>
            <?= $numero ? '#' . $numero : '' ?>.
        </div>
        <div class="rastreio-veiculo">
            <?= $veiculo ?> <span class="placa"><?= $placa ?></span>
        </div>

        <?php if ($cancelado): ?>
            <div style="padding:12px 16px;background:var(--color-danger-soft);color:var(--color-danger);
                        border:1px solid var(--color-danger);border-radius:var(--radius-md);font-size:13px;font-weight:600;text-align:center;">
                <?= $ehOS ? 'Esta Ordem de Serviço foi cancelada.' : 'Este orçamento foi cancelado.' ?>
            </div>
        <?php else: ?>
            <div class="os-stepper">
                <?php $i = 0; foreach ($etapas as $label): ?>
                    <?php if ($i > 0): ?>
                        <div class="os-stepper__line <?= $i <= $indiceAtual ? 'is-done' : '' ?>"></div>
                    <?php endif; ?>
                    <div class="os-stepper__step">
                        <div class="os-stepper__circle <?= $i < $indiceAtual ? 'is-done' : ($i === $indiceAtual ? 'is-current' : '') ?>">
                            <?= $i < $indiceAtual ? '&check;' : ($i + 1) ?>
                        </div>
                        <div class="os-stepper__label <?= $i === $indiceAtual ? 'is-active' : '' ?>"><?= $label ?></div>
                    </div>
                <?php $i++; endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="rastreio-rodape">Esta página é atualizada automaticamente conforme o andamento avança.</div>
    </div>

</body>

</html>
