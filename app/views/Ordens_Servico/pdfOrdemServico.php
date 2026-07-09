<?php
/**
 * Template dedicado para geração de PDF (via Dompdf) da Ordem de Serviço.
 * Segue o mesmo padrão de app/views/Orcamento/pdfOrcamento.php: cores em
 * hexadecimal literal (Dompdf não suporta var(--cor)) e layout em tabelas
 * (Dompdf não suporta flexbox/grid, por isso não reaproveita o CSS de
 * printOrcamento.php). Não expõe comissão/lucro do técnico — é um documento
 * para entregar ao cliente, não um relatório interno.
 * Espera: $os (array, de OrdemServico::getById), $itens (array de itens).
 */
$statusInfo = [
    'ABERTA'    => ['label' => 'Aberta',     'bg' => '#DBEAFE', 'cor' => '#1D4ED8'],
    'CONCLUIDA' => ['label' => 'Concluída',  'bg' => '#DCFCE7', 'cor' => '#16A34A'],
    'CANCELADA' => ['label' => 'Cancelada',  'bg' => '#FEE2E2', 'cor' => '#DC2626'],
][$os['status'] ?? ''] ?? ['label' => $os['status'] ?? '-', 'bg' => '#F1F5F9', 'cor' => '#475569'];

$docCliente = '';
if (!empty($os['cnpj_cliente'])) {
    $docCliente = 'CNPJ ' . htmlspecialchars($os['cnpj_cliente']);
} elseif (!empty($os['cpf_cliente'])) {
    $docCliente = 'CPF ' . htmlspecialchars($os['cpf_cliente']);
}

$enderecoPartes = array_filter([
    $os['endereco_cliente'] ?? null,
    $os['bairro_cliente']   ?? null,
    trim(($os['cidade_cliente'] ?? '') . (!empty($os['uf_cliente']) ? '/' . $os['uf_cliente'] : '')),
    !empty($os['cep_cliente']) ? 'CEP ' . $os['cep_cliente'] : null,
]);
$enderecoCompleto = implode(' · ', array_map('htmlspecialchars', $enderecoPartes));

$veiculoNome = trim(($os['marca_veiculo'] ?? '') . ' ' . ($os['modelo_veiculo'] ?? ''));

$totalPecas    = (float) ($os['valor_total_pecas']    ?? 0);
$totalServicos = (float) ($os['valor_total_servicos'] ?? 0);
$subtotalOS    = $totalPecas + $totalServicos;
$descontoOS    = (float) ($os['desconto_total'] ?? 0);
$totalGeralOS  = (float) ($os['valor_total_os']  ?? ($subtotalOS - $descontoOS));
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>OS <?= htmlspecialchars($os['numero_os'] ?? '') ?> — Mecanize</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        @page { margin: 26px 34px 30px; }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #111826;
        }

        .topbar {
            height: 5px;
            background: linear-gradient(135deg, #003BDD 0%, #0030C4 100%);
            margin: -26px -34px 18px;
        }

        /* ---------- Cabeçalho ---------- */
        table.doc-head { width: 100%; border-bottom: 2px solid #E2E8F0; padding-bottom: 16px; margin-bottom: 18px; }
        table.doc-head td { vertical-align: top; }

        table.brand-row td { vertical-align: middle; }
        .brand-mark {
            width: 36px; height: 36px; border-radius: 9px;
            background: linear-gradient(135deg, #003BDD 0%, #0030C4 100%);
            color: #FFFFFF; text-align: center; font-size: 16px; font-weight: bold;
            line-height: 36px;
        }
        .brand-name-cell { padding-left: 10px; }
        .brand-name { font-size: 15px; font-weight: bold; color: #111826; }
        .brand-meta { font-size: 9.5px; color: #64748B; margin-top: 2px; }

        .doc-head__ref { text-align: right; }
        .doc-head__ref .kicker { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 1.5px; color: #94A3B8; }
        .doc-head__ref .numero { font-size: 20px; font-weight: bold; color: #003BDD; margin: 3px 0 7px; }

        .badge {
            display: inline-block;
            padding: 3px 11px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: bold;
            background: <?= $statusInfo['bg'] ?>;
            color: <?= $statusInfo['cor'] ?>;
        }

        /* ---------- Faixa de meta-dados ---------- */
        table.meta-strip { width: 100%; margin-bottom: 18px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; }
        table.meta-strip td { padding: 10px 16px; text-align: left; border-right: 1px solid #E2E8F0; }
        table.meta-strip td:last-child { border-right: none; }
        table.meta-strip .m-label { display: block; font-size: 8.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #94A3B8; margin-bottom: 3px; }
        table.meta-strip .m-value { font-size: 12px; font-weight: bold; color: #1E293B; }

        /* ---------- Cards Cliente / Veículo ---------- */
        table.grid2 { width: 100%; margin-bottom: 16px; }
        table.grid2 td { width: 50%; vertical-align: top; }
        table.grid2 td.pad-left { padding-left: 12px; }

        .box { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px 14px; }
        .box h3 { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #94A3B8; margin-bottom: 6px; }
        .box p { font-size: 12px; color: #111826; line-height: 1.5; }
        .box p.strong { font-weight: bold; font-size: 13px; }
        .box p.sub { color: #64748B; font-size: 10.5px; margin-top: 2px; }

        table.vehicle-stats { width: 100%; margin-top: 8px; }
        table.vehicle-stats td { padding-top: 4px; border-top: 1px solid #E2E8F0; font-size: 11px; }
        table.vehicle-stats .vs-label { color: #94A3B8; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; display: block; }
        table.vehicle-stats .vs-value { font-weight: bold; color: #1E293B; }

        .section-title {
            font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;
            color: #003BDD; padding-bottom: 6px; margin: 4px 0 8px;
            border-bottom: 1.5px solid #E2E8F0;
        }

        /* ---------- Tabela de itens ---------- */
        table.itens { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.itens thead th {
            text-align: left; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px;
            color: #003BDD; padding: 7px 6px; background: #EEF2FF; border-bottom: 2px solid #DBEAFE;
        }
        table.itens tbody td { padding: 7px 6px; border-bottom: 1px solid #E2E8F0; font-size: 11px; }
        table.itens tbody tr:nth-child(even) td { background: #F8FAFC; }
        table.itens td.num, table.itens th.num { text-align: right; }

        /* ---------- Totais ---------- */
        table.totais { width: 270px; margin-left: auto; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 4px 14px; }
        table.totais td { padding: 5px 0; font-size: 11px; color: #1E293B; }
        table.totais td.valor { text-align: right; }
        .total-final { width: 270px; margin-left: auto; margin-top: 8px; background: linear-gradient(135deg, #003BDD 0%, #0030C4 100%); border-radius: 8px; padding: 10px 14px; }
        .total-final table { width: 100%; }
        .total-final td { color: #FFFFFF; font-size: 14px; font-weight: bold; }
        .total-final td.valor { text-align: right; font-size: 16px; }

        .obs-box { margin-top: 16px; padding: 12px 14px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; }
        .obs-box h3 { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #94A3B8; margin-bottom: 5px; }
        .obs-box p { font-size: 11px; line-height: 1.5; }

        .garantia-box { margin-top: 14px; padding: 12px 14px; border: 1px dashed #CBD5E1; border-radius: 8px; }
        .garantia-box h3 { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #94A3B8; margin-bottom: 5px; }
        .garantia-box p { font-size: 9.5px; line-height: 1.55; color: #64748B; text-align: justify; }

        table.assinaturas { width: 100%; margin-top: 46px; }
        table.assinaturas td { width: 50%; text-align: center; padding-top: 6px; border-top: 1.5px solid #CBD5E1; font-size: 10px; color: #94A3B8; }

        .doc-footer { margin-top: 18px; padding-top: 10px; border-top: 1px solid #E2E8F0; text-align: center; font-size: 9px; color: #94A3B8; }
    </style>
</head>

<body>

    <div class="topbar"></div>

    <table class="doc-head">
        <tr>
            <td style="width:55%;">
                <table class="brand-row">
                    <tr>
                        <td style="width:36px;"><div class="brand-mark">M</div></td>
                        <td class="brand-name-cell">
                            <div class="brand-name"><?= htmlspecialchars(defined('EMPRESA_NOME') ? EMPRESA_NOME : 'Mecanize') ?></div>
                            <div class="brand-meta">
                                <?= htmlspecialchars(defined('EMPRESA_ENDERECO') ? EMPRESA_ENDERECO : '') ?><br>
                                <?= htmlspecialchars(defined('EMPRESA_TELEFONE') ? EMPRESA_TELEFONE : '') ?>
                                <?= defined('EMPRESA_CNPJ') ? ' · CNPJ: ' . htmlspecialchars(EMPRESA_CNPJ) : '' ?>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="doc-head__ref" style="width:45%;">
                <div class="kicker">Ordem de Serviço</div>
                <div class="numero"><?= htmlspecialchars($os['numero_os'] ?? '#' . $os['id_ordem_servico']) ?></div>
                <span class="badge"><?= $statusInfo['label'] ?></span>
            </td>
        </tr>
    </table>

    <table class="meta-strip">
        <tr>
            <td>
                <span class="m-label">Aberta em</span>
                <span class="m-value"><?= !empty($os['data_abertura']) ? date('d/m/Y', strtotime($os['data_abertura'])) : '-' ?></span>
            </td>
            <td>
                <span class="m-label">Concluída em</span>
                <span class="m-value"><?= !empty($os['data_conclusao']) ? date('d/m/Y', strtotime($os['data_conclusao'])) : '-' ?></span>
            </td>
            <td>
                <span class="m-label">Responsável</span>
                <span class="m-value"><?= htmlspecialchars($os['nome_mecanico'] ?? '-') ?></span>
            </td>
        </tr>
    </table>

    <table class="grid2">
        <tr>
            <td>
                <div class="box">
                    <h3>Cliente</h3>
                    <p class="strong"><?= htmlspecialchars($os['nome_cliente'] ?? '-') ?></p>
                    <?php if ($docCliente): ?><p class="sub"><?= $docCliente ?></p><?php endif; ?>
                    <?php if (!empty($os['telefone_cliente'])): ?><p class="sub">Tel: <?= htmlspecialchars($os['telefone_cliente']) ?></p><?php endif; ?>
                    <?php if ($enderecoCompleto): ?><p class="sub"><?= $enderecoCompleto ?></p><?php endif; ?>
                </div>
            </td>
            <td class="pad-left">
                <div class="box">
                    <h3>Veículo</h3>
                    <p class="strong"><?= htmlspecialchars($veiculoNome ?: ($os['modelo'] ?? '-')) ?></p>
                    <p class="sub"><?= htmlspecialchars(strtoupper($os['placa'] ?? '-')) ?></p>
                    <table class="vehicle-stats">
                        <tr>
                            <td><span class="vs-label">Ano</span><span class="vs-value"><?= htmlspecialchars($os['ano_veiculo'] ?? '-') ?></span></td>
                            <td><span class="vs-label">Cor</span><span class="vs-value"><?= htmlspecialchars($os['cor_veiculo'] ?? '-') ?></span></td>
                            <td><span class="vs-label">KM</span><span class="vs-value"><?= !empty($os['km_veiculo']) ? number_format($os['km_veiculo'], 0, ',', '.') : '-' ?></span></td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <?php if (!empty($os['defeito_relatado']) || !empty($os['diagnostico'])): ?>
        <table class="grid2">
            <tr>
                <?php if (!empty($os['defeito_relatado'])): ?>
                    <td>
                        <div class="box">
                            <h3>Problema Relatado</h3>
                            <p><?= nl2br(htmlspecialchars($os['defeito_relatado'])) ?></p>
                        </div>
                    </td>
                <?php endif; ?>
                <?php if (!empty($os['diagnostico'])): ?>
                    <td class="pad-left">
                        <div class="box">
                            <h3>Diagnóstico</h3>
                            <p><?= nl2br(htmlspecialchars($os['diagnostico'])) ?></p>
                        </div>
                    </td>
                <?php endif; ?>
            </tr>
        </table>
    <?php endif; ?>

    <?php if (!empty($itens)): ?>
        <div class="section-title">Peças &amp; Serviços</div>
        <table class="itens">
            <thead>
                <tr><th>Descrição</th><th class="num">Qtd</th><th class="num">Valor Unit.</th><th class="num">Desconto Unit.</th><th class="num">Total do Item</th></tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $item):
                    $preco     = (float) ($item['preco_unitario'] ?? $item['preco'] ?? 0);
                    $qty       = (float) ($item['quantidade'] ?? $item['qty'] ?? 1);
                    $descItem  = (float) ($item['desconto'] ?? 0);
                    $totalItem = ($preco * $qty) - $descItem;
                ?>
                    <tr>
                        <td><?= htmlspecialchars($item['nome_produto'] ?? $item['nome'] ?? '-') ?></td>
                        <td class="num"><?= rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',') ?></td>
                        <td class="num">R$ <?= number_format($preco, 2, ',', '.') ?></td>
                        <td class="num">R$ <?= number_format($descItem, 2, ',', '.') ?></td>
                        <td class="num"><strong>R$ <?= number_format($totalItem, 2, ',', '.') ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <table class="totais">
        <tr><td>Total Peças</td><td class="valor">R$ <?= number_format($totalPecas, 2, ',', '.') ?></td></tr>
        <tr><td>Total Serviços</td><td class="valor">R$ <?= number_format($totalServicos, 2, ',', '.') ?></td></tr>
        <tr><td>Subtotal</td><td class="valor">R$ <?= number_format($subtotalOS, 2, ',', '.') ?></td></tr>
        <tr><td>Desconto</td><td class="valor">- R$ <?= number_format($descontoOS, 2, ',', '.') ?></td></tr>
    </table>
    <div class="total-final">
        <table><tr><td>Total Geral</td><td class="valor">R$ <?= number_format($totalGeralOS, 2, ',', '.') ?></td></tr></table>
    </div>

    <?php if (!empty($os['observacoes'])): ?>
        <div class="obs-box">
            <h3>Observações</h3>
            <p><?= nl2br(htmlspecialchars($os['observacoes'])) ?></p>
        </div>
    <?php endif; ?>

    <div class="garantia-box">
        <h3>Termos da Garantia</h3>
        <p>
            Os serviços e peças descritos nesta Ordem de Serviço possuem garantia de 90 (noventa) dias corridos,
            contados a partir da data de conclusão/retirada do veículo, restrita à mão de obra e às peças aqui
            listadas. A garantia perde a validade em caso de abertura, ajuste, reparo ou intervenção realizada por
            terceiros não autorizados após a entrega, uso indevido do veículo, acidentes, quedas, contato com
            líquidos ou substâncias que prejudiquem o funcionamento, picos de energia ou descarga atmosférica.
            Itens de desgaste natural não fornecidos por esta oficina não possuem cobertura de garantia.
        </p>
    </div>

    <table class="assinaturas">
        <tr>
            <td>
                <?php if (!empty($os['assinatura_cliente'])): ?>
                    <img src="<?= htmlspecialchars($os['assinatura_cliente']) ?>" style="max-height:60px;display:block;margin:0 auto 4px;"><br>
                <?php endif; ?>
                Assinatura do Cliente
            </td>
            <td><?= htmlspecialchars($os['nome_mecanico'] ?? 'Responsável Técnico') ?></td>
        </tr>
    </table>

    <div class="doc-footer">
        Documento gerado eletronicamente pelo Mecanize em <?= date('d/m/Y \à\s H:i') ?>
    </div>

</body>

</html>
