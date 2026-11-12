<?php
/**
 * Documento PDF da Ordem de Serviço (Dompdf).
 *
 * Estrutura, timbre e rodapé numerado vêm dos parciais compartilhados em
 * app/views/layout/_docPdf*.php — antes cada template PDF carregava sua
 * própria cópia do CSS e os documentos foram divergindo entre si.
 *
 * Não expõe comissão/lucro do técnico: é um documento para entregar ao
 * cliente, não um relatório interno.
 *
 * Espera: $os (array, de OrdemServico::getById), $itens (array de itens).
 */
$statusInfo = [
    'ABERTA'          => ['label' => OrdemServico::rotuloStatus('ABERTA'),          'bg' => '#DBEAFE', 'cor' => '#1D4ED8'],
    'EM_ANDAMENTO'    => ['label' => OrdemServico::rotuloStatus('EM_ANDAMENTO'),    'bg' => '#FEF3C7', 'cor' => '#92400E'],
    'AGUARDANDO_PECA' => ['label' => OrdemServico::rotuloStatus('AGUARDANDO_PECA'), 'bg' => '#EDE9FE', 'cor' => '#5B21B6'],
    'AGUARDANDO_CONFERENCIA' => ['label' => OrdemServico::rotuloStatus('AGUARDANDO_CONFERENCIA'), 'bg' => '#E2E8F0', 'cor' => '#334155'],
    'AGUARDANDO_RETIRADA'    => ['label' => OrdemServico::rotuloStatus('AGUARDANDO_RETIRADA'),    'bg' => '#CFFAFE', 'cor' => '#0E7490'],
    'CONCLUIDA'       => ['label' => OrdemServico::rotuloStatus('CONCLUIDA'),       'bg' => '#DCFCE7', 'cor' => '#16A34A'],
    'CANCELADA'       => ['label' => OrdemServico::rotuloStatus('CANCELADA'),       'bg' => '#FEE2E2', 'cor' => '#DC2626'],
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

$docTipo   = 'Ordem de Serviço';
$docNumero = $os['numero_os'] ?? ('#' . ($os['id_ordem_servico'] ?? ''));
$docStatus = $statusInfo;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>OS <?= htmlspecialchars($os['numero_os'] ?? '') ?> — <?= htmlspecialchars(EMPRESA_NOME) ?></title>
    <?php require __DIR__ . '/../layout/_docPdfEstilos.php'; ?>
</head>

<body>

    <?php require __DIR__ . '/../layout/_docPdfRodape.php'; ?>
    <?php require __DIR__ . '/../layout/_docPdfCabecalho.php'; ?>

    <table class="doc-meta">
        <tr>
            <td>
                <span class="rot">Aberta em</span>
                <span class="val"><?= !empty($os['data_abertura']) ? date('d/m/Y', strtotime($os['data_abertura'])) : '-' ?></span>
            </td>
            <td>
                <span class="rot">Concluída em</span>
                <span class="val"><?= !empty($os['data_conclusao']) ? date('d/m/Y', strtotime($os['data_conclusao'])) : '-' ?></span>
            </td>
            <td>
                <span class="rot">Responsável</span>
                <span class="val"><?= htmlspecialchars($os['nome_mecanico'] ?? '-') ?></span>
            </td>
        </tr>
    </table>

    <table class="doc-grid2">
        <tr>
            <td>
                <div class="doc-box">
                    <h3>Cliente</h3>
                    <p class="forte"><?= htmlspecialchars($os['nome_cliente'] ?? '-') ?></p>
                    <?php if ($docCliente): ?><p class="sub"><?= $docCliente ?></p><?php endif; ?>
                    <?php if (!empty($os['telefone_cliente'])): ?><p class="sub">Tel: <?= htmlspecialchars($os['telefone_cliente']) ?></p><?php endif; ?>
                    <?php if ($enderecoCompleto): ?><p class="sub"><?= $enderecoCompleto ?></p><?php endif; ?>
                </div>
            </td>
            <td class="dir">
                <div class="doc-box">
                    <h3>Veículo</h3>
                    <p class="forte"><?= htmlspecialchars($veiculoNome ?: ($os['modelo'] ?? '-')) ?></p>
                    <p class="sub"><?= htmlspecialchars(strtoupper($os['placa'] ?? '-')) ?></p>
                    <table class="doc-veiculo">
                        <tr>
                            <td><span class="rot">Ano</span><span class="val"><?= htmlspecialchars($os['ano_veiculo'] ?? '-') ?></span></td>
                            <td><span class="rot">Cor</span><span class="val"><?= htmlspecialchars($os['cor_veiculo'] ?? '-') ?></span></td>
                            <td><span class="rot">KM</span><span class="val"><?= !empty($os['km_veiculo']) ? number_format($os['km_veiculo'], 0, ',', '.') : '-' ?></span></td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <?php if (!empty($os['defeito_relatado']) || !empty($os['diagnostico'])): ?>
        <table class="doc-grid2">
            <tr>
                <?php if (!empty($os['defeito_relatado'])): ?>
                    <td>
                        <div class="doc-box">
                            <h3>Problema Relatado</h3>
                            <p><?= nl2br(htmlspecialchars($os['defeito_relatado'])) ?></p>
                        </div>
                    </td>
                <?php endif; ?>
                <?php if (!empty($os['diagnostico'])): ?>
                    <td class="dir">
                        <div class="doc-box">
                            <h3>Diagnóstico</h3>
                            <p><?= nl2br(htmlspecialchars($os['diagnostico'])) ?></p>
                        </div>
                    </td>
                <?php endif; ?>
            </tr>
        </table>
    <?php endif; ?>

    <?php if (!empty($itens)): ?>
        <div class="doc-secao">Peças &amp; Serviços</div>
        <table class="doc-tabela">
            <thead>
                <tr>
                    <th>Descrição</th>
                    <th class="num" style="width:9%;">Qtd</th>
                    <th class="num" style="width:16%;">Valor Unit.</th>
                    <th class="num" style="width:16%;">Desconto</th>
                    <th class="num" style="width:18%;">Total do Item</th>
                </tr>
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

    <!-- Totais nunca podem rachar entre páginas: meia apuração numa folha e
         o total geral na outra é o tipo de documento que ninguém aceita. -->
    <div class="doc-fechamento">
        <table class="doc-totais">
            <tr><td>Total Peças</td><td class="num">R$ <?= number_format($totalPecas, 2, ',', '.') ?></td></tr>
            <tr><td>Total Serviços</td><td class="num">R$ <?= number_format($totalServicos, 2, ',', '.') ?></td></tr>
            <tr><td>Subtotal</td><td class="num">R$ <?= number_format($subtotalOS, 2, ',', '.') ?></td></tr>
            <tr><td>Desconto</td><td class="num">- R$ <?= number_format($descontoOS, 2, ',', '.') ?></td></tr>
        </table>

        <div class="doc-total-final">
            <table>
                <tr>
                    <td>Total Geral</td>
                    <td class="num">R$ <?= number_format($totalGeralOS, 2, ',', '.') ?></td>
                </tr>
            </table>
        </div>
    </div>

    <?php if (!empty($os['observacoes'])): ?>
        <div class="doc-box" style="margin-top:14px;">
            <h3>Observações</h3>
            <p><?= nl2br(htmlspecialchars($os['observacoes'])) ?></p>
        </div>
    <?php endif; ?>

    <div class="doc-garantia">
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

    <table class="doc-assinaturas">
        <tr>
            <td>
                <?php
                $docAssinatura = $os['assinatura_cliente'] ?? '';
                require __DIR__ . '/../layout/_docPdfAssinatura.php';
                ?>
                Assinatura do Cliente
            </td>
            <td><?= htmlspecialchars($os['nome_mecanico'] ?? 'Responsável Técnico') ?></td>
        </tr>
    </table>

</body>

</html>
