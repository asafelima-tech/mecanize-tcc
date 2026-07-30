<?php
/**
 * Template de PDF do relatório de caixa por período (via Dompdf).
 * Mesmo padrão dos PDFs de OS/Orçamento: cores em hexadecimal literal
 * (Dompdf não resolve var(--cor)) e layout em tabelas (sem flex/grid).
 * Espera: $periodo, $resumo, $lancamentos (de CaixaController::exportarRelatorioPdf).
 */
$formaLabel = [
    'DINHEIRO' => 'Dinheiro',
    'PIX'      => 'PIX',
    'CARTAO'   => 'Cartão',
    'BOLETO'   => 'Boleto',
];
$catSaidaLabel = [
    'PECAS' => 'Peças',
    'BANCO' => 'Retirada para banco',
    'OUTRO' => 'Outro',
];
$catLabel = $catSaidaLabel + ['OS' => 'Ordem de Serviço', 'VENDA' => 'Venda'];

$brl = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

$totalEntradas = (float) ($resumo['total_entradas'] ?? 0);
$totalSaidas   = (float) ($resumo['total_saidas'] ?? 0);
$resultado     = (float) ($resumo['resultado'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Relatório de Caixa — Mecanize</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        @page { margin: 26px 34px 30px; }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #111826;
        }

        .topbar {
            height: 5px;
            background: #003BDD;
            margin: -26px -34px 18px;
        }

        table.doc-head { width: 100%; border-bottom: 2px solid #E2E8F0; padding-bottom: 14px; margin-bottom: 18px; }
        table.doc-head td { vertical-align: top; }

        .brand-mark {
            width: 36px; height: 36px; border-radius: 9px;
            background: #003BDD; color: #FFFFFF;
            text-align: center; font-size: 16px; font-weight: bold; line-height: 36px;
        }
        .brand-name { font-size: 15px; font-weight: bold; }
        .brand-sub  { font-size: 10px; color: #64748B; }

        .doc-title { font-size: 16px; font-weight: bold; text-align: right; }
        .doc-period { font-size: 11px; color: #475569; text-align: right; margin-top: 3px; }
        .doc-emit { font-size: 9px; color: #94A3B8; text-align: right; margin-top: 2px; }

        h2 {
            font-size: 11px; text-transform: uppercase; letter-spacing: .04em;
            color: #64748B; margin: 18px 0 8px;
        }

        table.totais { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-bottom: 4px; }
        table.totais td {
            width: 33.3%; border: 1px solid #E2E8F0; border-radius: 8px;
            padding: 10px 12px; vertical-align: top;
        }
        .tot-label { font-size: 9px; text-transform: uppercase; letter-spacing: .04em; color: #64748B; }
        .tot-valor { font-size: 15px; font-weight: bold; margin-top: 3px; }

        table.dados { width: 100%; border-collapse: collapse; }
        table.dados th {
            background: #F8FAFC; border-bottom: 1px solid #E2E8F0;
            font-size: 9px; text-transform: uppercase; letter-spacing: .04em;
            color: #64748B; text-align: left; padding: 7px 8px;
        }
        table.dados td { border-bottom: 1px solid #F1F5F9; padding: 6px 8px; }
        table.dados tr.total-row td {
            border-top: 1.5px solid #E2E8F0; border-bottom: none;
            font-weight: bold; background: #F8FAFC;
        }

        .num { text-align: right; }
        .verde { color: #16A34A; }
        .vermelho { color: #DC2626; }
        .muted { color: #64748B; }

        .rodape {
            margin-top: 22px; padding-top: 10px; border-top: 1px solid #E2E8F0;
            font-size: 9px; color: #94A3B8; text-align: center;
        }
    </style>
</head>

<body>
    <div class="topbar"></div>

    <table class="doc-head">
        <tr>
            <td width="55%">
                <table>
                    <tr>
                        <td><div class="brand-mark">M</div></td>
                        <td style="padding-left:10px;vertical-align:middle;">
                            <div class="brand-name">Mecanize</div>
                            <div class="brand-sub">Relatório financeiro de caixa</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td width="45%">
                <div class="doc-title">Relatório de Caixa</div>
                <div class="doc-period"><?= htmlspecialchars($periodo['rotulo'] ?? '') ?></div>
                <div class="doc-emit">
                    <?= date('d/m/Y', strtotime($periodo['inicio'])) ?> a <?= date('d/m/Y', strtotime($periodo['fim'])) ?>
                    · emitido em <?= date('d/m/Y H:i') ?>
                </div>
            </td>
        </tr>
    </table>

    <!-- Totais -->
    <table class="totais">
        <tr>
            <td>
                <div class="tot-label">Total de entradas</div>
                <div class="tot-valor verde"><?= $brl($totalEntradas) ?></div>
            </td>
            <td>
                <div class="tot-label">Total de saídas</div>
                <div class="tot-valor vermelho"><?= $brl($totalSaidas) ?></div>
            </td>
            <td>
                <div class="tot-label">Resultado (entradas − saídas)</div>
                <div class="tot-valor <?= $resultado >= 0 ? 'verde' : 'vermelho' ?>"><?= $brl($resultado) ?></div>
            </td>
        </tr>
    </table>

    <!-- Entradas por forma -->
    <h2>Entradas por forma de pagamento</h2>
    <table class="dados">
        <thead>
            <tr>
                <th>Forma de pagamento</th>
                <th class="num" width="20%">Valor</th>
                <th class="num" width="15%">% do total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($formaLabel as $chave => $rotulo):
                $val = (float) ($resumo['entradas'][$chave] ?? 0);
                $pct = $totalEntradas > 0 ? round(($val / $totalEntradas) * 100) : 0; ?>
                <tr>
                    <td><?= $rotulo ?></td>
                    <td class="num"><?= $brl($val) ?></td>
                    <td class="num muted"><?= $pct ?>%</td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td>Total de entradas</td>
                <td class="num verde"><?= $brl($totalEntradas) ?></td>
                <td class="num">100%</td>
            </tr>
        </tbody>
    </table>

    <!-- Saídas por categoria -->
    <h2>Saídas por categoria</h2>
    <table class="dados">
        <thead>
            <tr>
                <th>Categoria</th>
                <th class="num" width="20%">Valor</th>
                <th class="num" width="15%">% do total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($catSaidaLabel as $chave => $rotulo):
                $val = (float) ($resumo['saidas'][$chave] ?? 0);
                $pct = $totalSaidas > 0 ? round(($val / $totalSaidas) * 100) : 0; ?>
                <tr>
                    <td><?= $rotulo ?></td>
                    <td class="num"><?= $brl($val) ?></td>
                    <td class="num muted"><?= $pct ?>%</td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td>Total de saídas</td>
                <td class="num vermelho"><?= $brl($totalSaidas) ?></td>
                <td class="num">100%</td>
            </tr>
        </tbody>
    </table>

    <!-- Lançamentos -->
    <h2>Lançamentos do período (<?= count($lancamentos ?? []) ?>)</h2>
    <table class="dados">
        <thead>
            <tr>
                <th width="16%">Data/hora</th>
                <th width="10%">Tipo</th>
                <th width="20%">Forma / Categoria</th>
                <th>Descrição</th>
                <th class="num" width="16%">Valor</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($lancamentos)): ?>
                <?php foreach ($lancamentos as $m):
                    $isEntrada = ($m['tipo'] ?? '') === 'ENTRADA';
                    $detalhe   = $isEntrada
                        ? ($formaLabel[$m['forma_pagamento'] ?? ''] ?? '-')
                        : ($catLabel[$m['categoria'] ?? ''] ?? '-');
                    $descricao = trim((string) ($m['descricao'] ?? ''));
                    if ($descricao === '' && !empty($m['os_numero'])) {
                        $descricao = 'OS ' . $m['os_numero'];
                    }
                ?>
                    <tr>
                        <td class="muted"><?= !empty($m['data_movimentacao']) ? date('d/m/Y H:i', strtotime($m['data_movimentacao'])) : '-' ?></td>
                        <td class="<?= $isEntrada ? 'verde' : 'vermelho' ?>"><?= $isEntrada ? 'Entrada' : 'Saída' ?></td>
                        <td><?= htmlspecialchars($detalhe) ?></td>
                        <td class="muted"><?= $descricao !== '' ? htmlspecialchars($descricao) : '-' ?></td>
                        <td class="num <?= $isEntrada ? 'verde' : 'vermelho' ?>">
                            <?= $isEntrada ? '+ ' : '- ' ?><?= $brl($m['valor'] ?? 0) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="muted" style="text-align:center;padding:24px 0;">
                        Nenhuma movimentação registrada neste período.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="rodape">
        Mecanize · relatório gerado automaticamente pelo sistema em <?= date('d/m/Y \à\s H:i') ?>
    </div>
</body>

</html>
