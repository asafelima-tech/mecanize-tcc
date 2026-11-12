<?php
/**
 * Template dedicado para geração de PDF (via Dompdf) do orçamento.
 * Não reaproveita printOrcamento.php porque o Dompdf não suporta variáveis
 * CSS (var(--cor)) nem flexbox/grid nem o JS de "modo edição" daquela tela
 * — aqui os valores de cor já vêm literais, o layout é em tabelas e não há
 * nenhum elemento interativo.
 * Espera as mesmas variáveis: $orc (array) e $itens (array).
 */
$statusInfo = [
    'pendente'  => ['label' => 'Aguardando Aprovação', 'bg' => '#FEF3C7', 'cor' => '#92400E'],
    'aprovado'  => ['label' => 'Aprovado',              'bg' => '#EBF0FF', 'cor' => '#002BA8'],
    'reprovado' => ['label' => 'Recusado',              'bg' => '#FEE2E2', 'cor' => '#991B1B'],
][$orc['status'] ?? 'pendente'] ?? ['label' => 'Pendente', 'bg' => '#F1F5F9', 'cor' => '#475569'];

$pecas    = array_filter($itens, fn($i) => ($i['tipo'] ?? '') !== 'servico');
$servicos = array_filter($itens, fn($i) => ($i['tipo'] ?? '') === 'servico');

$docCliente = '';
if (!empty($orc['cnpj_cliente'])) {
    $docCliente = 'CNPJ ' . htmlspecialchars($orc['cnpj_cliente']);
} elseif (!empty($orc['cpf_cliente'])) {
    $docCliente = 'CPF ' . htmlspecialchars($orc['cpf_cliente']);
}

$enderecoPartes = array_filter([
    $orc['endereco_cliente'] ?? null,
    $orc['bairro_cliente']   ?? null,
    trim(($orc['cidade_cliente'] ?? '') . (!empty($orc['uf_cliente']) ? '/' . $orc['uf_cliente'] : '')),
    !empty($orc['cep_cliente']) ? 'CEP ' . $orc['cep_cliente'] : null,
]);
$enderecoCompleto = implode(' · ', array_map('htmlspecialchars', $enderecoPartes));

$veiculoNome = trim(($orc['marca_veiculo'] ?? '') . ' ' . ($orc['modelo_veiculo'] ?? ''));

// Nota: valor_mao_obra é a comissão do técnico já carve-out de dentro de
// valor_total_pecas/valor_total_servicos (ver saveOrcamento.php/editOrcamento.php,
// onde "lucro = total - comissão") — não é um valor adicional cobrado do
// cliente. Por isso NÃO entra no subtotal nem aparece neste documento (que é
// entregue ao cliente), mesmo tratamento que pdfOrdemServico.php já dá.
$totalPecas    = (float) ($orc['valor_total_pecas']    ?? 0);
$totalServicos = (float) ($orc['valor_total_servicos'] ?? 0);
$subtotalOrc   = $totalPecas + $totalServicos;
$descontoOrc   = (float) ($orc['desconto_total'] ?? 0);
$totalGeralOrc = (float) ($orc['valor_total_orcamento'] ?? ($subtotalOrc - $descontoOrc));

$docTipo   = "Orçamento";
$docNumero = $orc["numero"] ?? ("#" . ($orc["id_orcamento"] ?? ""));
$docStatus = $statusInfo;
$docRodapeNota = !empty($orc["data_validade"])
    ? "válido até " . date("d/m/Y", strtotime($orc["data_validade"]))
    : "";
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Orçamento <?= htmlspecialchars($orc['numero'] ?? '') ?> — Mecanize</title>
    <?php require __DIR__ . "/../layout/_docPdfEstilos.php"; ?>
</head>

<body>

    <?php require __DIR__ . "/../layout/_docPdfRodape.php"; ?>
    <?php require __DIR__ . "/../layout/_docPdfCabecalho.php"; ?>

    <table class="doc-meta">
        <tr>
            <td>
                <span class="rot">Emitido em</span>
                <span class="val"><?= !empty($orc['data_criacao']) ? date('d/m/Y', strtotime($orc['data_criacao'])) : '-' ?></span>
            </td>
            <td>
                <span class="rot">Válido até</span>
                <span class="val"><?= !empty($orc['data_validade']) ? date('d/m/Y', strtotime($orc['data_validade'])) : '-' ?></span>
            </td>
            <td>
                <span class="rot">Responsável</span>
                <span class="val"><?= htmlspecialchars($orc['mecanico_nome'] ?? '-') ?></span>
            </td>
        </tr>
    </table>

    <table class="doc-grid2">
        <tr>
            <td>
                <div class="doc-box">
                    <h3>Cliente</h3>
                    <p class="forte"><?= htmlspecialchars($orc['cliente_nome'] ?? '-') ?></p>
                    <?php if ($docCliente): ?><p class="sub"><?= $docCliente ?></p><?php endif; ?>
                    <?php if (!empty($orc['telefone_cliente'])): ?><p class="sub">Tel: <?= htmlspecialchars($orc['telefone_cliente']) ?></p><?php endif; ?>
                    <?php if ($enderecoCompleto): ?><p class="sub"><?= $enderecoCompleto ?></p><?php endif; ?>
                </div>
            </td>
            <td class="dir">
                <div class="doc-box">
                    <h3>Veículo</h3>
                    <p class="forte"><?= htmlspecialchars($veiculoNome ?: ($orc['veiculo_modelo'] ?? '-')) ?></p>
                    <p class="sub"><?= htmlspecialchars(strtoupper($orc['placa'] ?? '-')) ?></p>
                    <table class="doc-veiculo">
                        <tr>
                            <td><span class="rot">Ano</span><span class="val"><?= htmlspecialchars($orc['ano_veiculo'] ?? '-') ?></span></td>
                            <td><span class="rot">Cor</span><span class="val"><?= htmlspecialchars($orc['cor_veiculo'] ?? '-') ?></span></td>
                            <td><span class="rot">KM</span><span class="val"><?= !empty($orc['km_veiculo']) ? number_format($orc['km_veiculo'], 0, ',', '.') : '-' ?></span></td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <?php if (!empty($orc['defeito_relatado']) || !empty($orc['diagnostico'])): ?>
        <table class="doc-grid2">
            <tr>
                <?php if (!empty($orc['defeito_relatado'])): ?>
                    <td>
                        <div class="doc-box">
                            <h3>Problema Relatado</h3>
                            <p><?= nl2br(htmlspecialchars($orc['defeito_relatado'])) ?></p>
                        </div>
                    </td>
                <?php endif; ?>
                <?php if (!empty($orc['diagnostico'])): ?>
                    <td class="dir">
                        <div class="doc-box">
                            <h3>Diagnóstico</h3>
                            <p><?= nl2br(htmlspecialchars($orc['diagnostico'])) ?></p>
                        </div>
                    </td>
                <?php endif; ?>
            </tr>
        </table>
    <?php endif; ?>

    <?php
    $renderTabelaItens = function ($lista) {
        foreach ($lista as $item):
            $preco     = (float) ($item['preco'] ?? 0);
            $qty       = (float) ($item['qty'] ?? 1);
            $descItem  = (float) ($item['desconto'] ?? 0);
            $totalItem = ($item['subtotal'] ?? ($preco * $qty)) - $descItem;
    ?>
            <tr>
                <td><?= htmlspecialchars($item['nome']) ?></td>
                <td class="num"><?= rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',') ?></td>
                <td class="num">R$ <?= number_format($preco, 2, ',', '.') ?></td>
                <td class="num">R$ <?= number_format($descItem, 2, ',', '.') ?></td>
                <td class="num"><strong>R$ <?= number_format($totalItem, 2, ',', '.') ?></strong></td>
            </tr>
    <?php
        endforeach;
    };
    ?>

    <?php if (!empty($pecas)): ?>
        <div class="doc-secao">Peças / Produtos</div>
        <table class="doc-tabela">
            <thead>
                <tr><th>Descrição</th><th class="num">Qtd</th><th class="num">Valor Unit.</th><th class="num">Desconto Unit.</th><th class="num">Total do Item</th></tr>
            </thead>
            <tbody><?php $renderTabelaItens($pecas); ?></tbody>
        </table>
    <?php endif; ?>

    <?php if (!empty($servicos)): ?>
        <div class="doc-secao">Mão de Obra / Serviços</div>
        <table class="doc-tabela">
            <thead>
                <tr><th>Descrição</th><th class="num">Qtd</th><th class="num">Valor Unit.</th><th class="num">Desconto Unit.</th><th class="num">Total do Item</th></tr>
            </thead>
            <tbody><?php $renderTabelaItens($servicos); ?></tbody>
        </table>
    <?php endif; ?>

    <!-- Totais e total geral sempre na mesma pagina -->
    <div class="doc-fechamento">
    <table class="doc-totais">
        <tr><td>Total Peças</td><td class="num">R$ <?= number_format($totalPecas, 2, ',', '.') ?></td></tr>
        <tr><td>Total Serviços</td><td class="num">R$ <?= number_format($totalServicos, 2, ',', '.') ?></td></tr>
        <tr><td>Subtotal</td><td class="num">R$ <?= number_format($subtotalOrc, 2, ',', '.') ?></td></tr>
        <tr><td>Desconto</td><td class="num">- R$ <?= number_format($descontoOrc, 2, ',', '.') ?></td></tr>
    </table>
    <div class="doc-total-final">
        <table><tr><td>Total Geral</td><td class="num">R$ <?= number_format($totalGeralOrc, 2, ',', '.') ?></td></tr></table>
    </div>
    </div>

    <?php if (!empty($orc['observacoes'])): ?>
        <div class="doc-box">
            <h3>Observações</h3>
            <p><?= nl2br(htmlspecialchars($orc['observacoes'])) ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($orc['prazo_execucao'])): ?>
        <div class="doc-box" style="margin-top:12px;">
            Prazo estimado de execução: <strong><?= htmlspecialchars($orc['prazo_execucao']) ?></strong>
        </div>
    <?php endif; ?>

    <div class="doc-garantia">
        <h3>Termos da Garantia</h3>
        <p>
            Este orçamento é válido até a data indicada acima. Após a aprovação e execução dos serviços aqui
            descritos, a Ordem de Serviço correspondente passa a ter garantia de 90 (noventa) dias corridos,
            contados a partir da data de conclusão/retirada do veículo, restrita à mão de obra e às peças
            listadas. A garantia perde a validade em caso de abertura, ajuste, reparo ou intervenção realizada
            por terceiros não autorizados após a entrega, uso indevido do veículo, acidentes, quedas, contato
            com líquidos ou substâncias que prejudiquem o funcionamento, picos de energia ou descarga
            atmosférica. Itens de desgaste natural não fornecidos por esta oficina não possuem cobertura de
            garantia.
        </p>
    </div>

    <table class="doc-assinaturas">
        <tr>
            <td>
                <?php
                $docAssinatura = $orc['assinatura_cliente'] ?? '';
                require __DIR__ . '/../layout/_docPdfAssinatura.php';
                ?>
                Assinatura do Cliente
            </td>
            <td><?= htmlspecialchars($orc['mecanico_nome'] ?? 'Responsável Técnico') ?></td>
        </tr>
    </table>

</body>

</html>
