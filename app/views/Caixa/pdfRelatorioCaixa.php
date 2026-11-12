<?php
/**
 * Template de PDF do relatório de caixa por período (via Dompdf).
 * Mesmo padrão dos PDFs de OS/Orçamento: cores em hexadecimal literal
 * (Dompdf não resolve var(--cor)) e layout em tabelas (sem flex/grid).
 * Espera: $periodo, $resumo, $lancamentos (de CaixaController::exportarRelatorioPdf).
 */
// Mapa único em MovimentacaoCaixa: a tela não repete a lista de formas.
require_once __DIR__ . '/../../models/MovimentacaoCaixa.php';
$formaLabel = MovimentacaoCaixa::FORMA_LABEL;
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

$docTipo      = "Relatório de Caixa";
$docNumero    = "";
$docSubtitulo = trim((string) ($periodo["rotulo"] ?? ""));
$docRodapeNota = date("d/m/Y", strtotime($periodo["inicio"])) . " a " . date("d/m/Y", strtotime($periodo["fim"]));
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Relatório de Caixa — Mecanize</title>
    <?php require __DIR__ . "/../layout/_docPdfEstilos.php"; ?>
    <style>
        /* Específico do relatório de caixa */
        table.cx-totais { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-bottom: 6px; page-break-inside: avoid; }
        table.cx-totais td { width: 33.3%; border: 1px solid #E2E8F0; border-radius: 8px; padding: 10px 12px; vertical-align: top; }
        .cx-rot { font-size: 8.5px; text-transform: uppercase; letter-spacing: .04em; color: #64748B; }
        .cx-val { font-size: 15px; font-weight: bold; margin-top: 3px; }
    </style>
</head>

<body>
    <?php require __DIR__ . "/../layout/_docPdfRodape.php"; ?>
    <?php require __DIR__ . "/../layout/_docPdfCabecalho.php"; ?>

    <!-- Totais -->
    <table class="cx-totais">
        <tr>
            <td>
                <div class="cx-rot">Total de entradas</div>
                <div class="cx-val verde"><?= $brl($totalEntradas) ?></div>
            </td>
            <td>
                <div class="cx-rot">Total de saídas</div>
                <div class="cx-val vermelho"><?= $brl($totalSaidas) ?></div>
            </td>
            <td>
                <div class="cx-rot">Resultado (entradas − saídas)</div>
                <div class="cx-val <?= $resultado >= 0 ? 'verde' : 'vermelho' ?>"><?= $brl($resultado) ?></div>
            </td>
        </tr>
    </table>

    <!-- Entradas por forma -->
    <div class="doc-secao">Entradas por forma de pagamento</div>
    <table class="doc-tabela">
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
            <tr class="doc-total-linha">
                <td>Total de entradas</td>
                <td class="num verde"><?= $brl($totalEntradas) ?></td>
                <td class="num">100%</td>
            </tr>
        </tbody>
    </table>

    <!-- Saídas por categoria -->
    <div class="doc-secao">Saídas por categoria</div>
    <table class="doc-tabela">
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
            <tr class="doc-total-linha">
                <td>Total de saídas</td>
                <td class="num vermelho"><?= $brl($totalSaidas) ?></td>
                <td class="num">100%</td>
            </tr>
        </tbody>
    </table>

    <!-- Lançamentos -->
    <div class="doc-secao">Lançamentos do período (<?= count($lancamentos ?? []) ?>)</div>
    <table class="doc-tabela">
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
</body>

</html>
