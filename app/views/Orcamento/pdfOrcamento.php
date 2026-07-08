<?php
/**
 * Template dedicado para geração de PDF (via Dompdf) do orçamento.
 * Não reaproveita printOrcamento.php porque o Dompdf não suporta variáveis
 * CSS (var(--cor)) nem o JS de "modo edição" daquela tela — aqui os valores
 * de cor já vêm literais e não há nenhum elemento interativo.
 * Espera as mesmas variáveis: $orc (array) e $itens (array).
 */
$statusInfo = [
    'pendente'  => ['label' => 'Aguardando Aprovação', 'bg' => '#FEF3C7', 'cor' => '#92400E'],
    'aprovado'  => ['label' => 'Aprovado',              'bg' => '#EBF0FF', 'cor' => '#002BA8'],
    'reprovado' => ['label' => 'Recusado',              'bg' => '#FEE2E2', 'cor' => '#991B1B'],
][$orc['status'] ?? 'pendente'] ?? ['label' => 'Pendente', 'bg' => '#F1F5F9', 'cor' => '#475569'];

$pecas    = array_filter($itens, fn($i) => ($i['tipo'] ?? '') !== 'servico');
$servicos = array_filter($itens, fn($i) => ($i['tipo'] ?? '') === 'servico');
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Orçamento <?= htmlspecialchars($orc['numero'] ?? '') ?> — Mecanize</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #111826;
        }

        .doc-head {
            padding: 20px 0;
            border-bottom: 2px solid #E2E8F0;
            display: table;
            width: 100%;
        }

        .doc-head__brand { display: table-cell; vertical-align: top; }
        .doc-head__brand h1 { font-size: 16px; font-weight: bold; }
        .doc-head__brand .empresa-info { font-size: 10px; color: #64748B; margin-top: 2px; }

        .doc-head__ref { display: table-cell; text-align: right; vertical-align: top; }
        .doc-head__ref .kicker { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #94A3B8; }
        .doc-head__ref .numero { font-size: 18px; font-weight: bold; color: #003BDD; margin: 2px 0 6px; }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: bold;
            background: <?= $statusInfo['bg'] ?>;
            color: <?= $statusInfo['cor'] ?>;
        }

        .doc-body { padding: 20px 0; }

        table.grid2 { width: 100%; margin-bottom: 16px; }
        table.grid2 td { width: 50%; vertical-align: top; padding-right: 12px; }

        .box h3 { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #94A3B8; margin-bottom: 4px; }
        .box p { font-size: 12px; color: #111826; }
        .box p.strong { font-weight: bold; }
        .box p.sub { color: #94A3B8; font-size: 10.5px; margin-top: 1px; }

        .section-title {
            font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;
            color: #94A3B8; padding-bottom: 6px; margin: 10px 0 4px;
            border-bottom: 1px solid #E2E8F0;
        }

        table.itens { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.itens thead th {
            text-align: left; font-size: 9px; font-weight: bold; text-transform: uppercase;
            color: #94A3B8; padding: 6px; background: #F8FAFC; border-bottom: 2px solid #E2E8F0;
        }
        table.itens tbody td { padding: 6px; border-bottom: 1px solid #E2E8F0; font-size: 11px; }
        table.itens td.num, table.itens th.num { text-align: right; }

        table.totais { width: 260px; margin-left: auto; background: #F8FAFC; padding: 10px 14px; }
        table.totais td { padding: 3px 0; font-size: 11px; color: #1E293B; }
        table.totais td.valor { text-align: right; }
        table.totais tr.final td { border-top: 1.5px solid #CBD5E1; padding-top: 8px; font-weight: bold; font-size: 14px; color: #003BDD; }

        .obs-box { margin-top: 16px; padding: 10px 14px; background: #F8FAFC; border: 1px solid #E2E8F0; }
        .obs-box h3 { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #94A3B8; margin-bottom: 4px; }
        .obs-box p { font-size: 11px; }

        .meta-line { margin-top: 14px; font-size: 10px; color: #94A3B8; }
        .meta-line strong { color: #1E293B; }

        table.assinaturas { width: 100%; margin-top: 50px; }
        table.assinaturas td { width: 50%; text-align: center; padding-top: 6px; border-top: 1.5px solid #CBD5E1; font-size: 10px; color: #94A3B8; }

        .doc-footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #E2E8F0; text-align: center; font-size: 9px; color: #94A3B8; }
    </style>
</head>

<body>

    <div class="doc-head">
        <div class="doc-head__brand">
            <h1><?= htmlspecialchars(defined('EMPRESA_NOME') ? EMPRESA_NOME : 'Mecanize') ?></h1>
            <div class="empresa-info">
                <?= htmlspecialchars(defined('EMPRESA_ENDERECO') ? EMPRESA_ENDERECO : '') ?><br>
                <?= htmlspecialchars(defined('EMPRESA_TELEFONE') ? EMPRESA_TELEFONE : '') ?>
                <?= defined('EMPRESA_CNPJ') ? ' · CNPJ: ' . htmlspecialchars(EMPRESA_CNPJ) : '' ?>
            </div>
        </div>
        <div class="doc-head__ref">
            <div class="kicker">Orçamento</div>
            <div class="numero"><?= htmlspecialchars($orc['numero'] ?? '#' . $orc['id_orcamento']) ?></div>
            <span class="badge"><?= $statusInfo['label'] ?></span>
            <div style="font-size:9px;color:#94A3B8;margin-top:6px;">
                Emitido em <?= !empty($orc['data_criacao']) ? date('d/m/Y', strtotime($orc['data_criacao'])) : '-' ?>
            </div>
        </div>
    </div>

    <div class="doc-body">

        <table class="grid2">
            <tr>
                <td class="box">
                    <h3>Cliente</h3>
                    <p class="strong"><?= htmlspecialchars($orc['cliente_nome'] ?? '-') ?></p>
                </td>
                <td class="box">
                    <h3>Veículo</h3>
                    <p class="strong"><?= htmlspecialchars($orc['veiculo_modelo'] ?? '-') ?></p>
                    <p class="sub"><?= htmlspecialchars($orc['placa'] ?? '-') ?><?= !empty($orc['km_veiculo']) ? ' · ' . number_format($orc['km_veiculo'], 0, ',', '.') . ' km' : '' ?></p>
                </td>
            </tr>
        </table>

        <?php if (!empty($orc['defeito_relatado']) || !empty($orc['diagnostico'])): ?>
            <table class="grid2">
                <tr>
                    <?php if (!empty($orc['defeito_relatado'])): ?>
                        <td class="box">
                            <h3>Defeito Relatado</h3>
                            <p><?= nl2br(htmlspecialchars($orc['defeito_relatado'])) ?></p>
                        </td>
                    <?php endif; ?>
                    <?php if (!empty($orc['diagnostico'])): ?>
                        <td class="box">
                            <h3>Diagnóstico</h3>
                            <p><?= nl2br(htmlspecialchars($orc['diagnostico'])) ?></p>
                        </td>
                    <?php endif; ?>
                </tr>
            </table>
        <?php endif; ?>

        <?php if (!empty($pecas)): ?>
            <div class="section-title">Peças / Produtos</div>
            <table class="itens">
                <thead>
                    <tr><th>Item</th><th class="num">Qtd</th><th class="num">Unitário</th><th class="num">Subtotal</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($pecas as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['nome']) ?></td>
                            <td class="num"><?= (int) $item['qty'] ?></td>
                            <td class="num">R$ <?= number_format($item['preco'], 2, ',', '.') ?></td>
                            <td class="num"><strong>R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($servicos)): ?>
            <div class="section-title">Mão de Obra / Serviços</div>
            <table class="itens">
                <thead>
                    <tr><th>Item</th><th class="num">Qtd</th><th class="num">Unitário</th><th class="num">Subtotal</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($servicos as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['nome']) ?></td>
                            <td class="num"><?= (int) $item['qty'] ?></td>
                            <td class="num">R$ <?= number_format($item['preco'], 2, ',', '.') ?></td>
                            <td class="num"><strong>R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <table class="totais">
            <tr><td>Peças</td><td class="valor">R$ <?= number_format($orc['valor_total_pecas'] ?? 0, 2, ',', '.') ?></td></tr>
            <tr><td>Serviços</td><td class="valor">R$ <?= number_format($orc['valor_total_servicos'] ?? 0, 2, ',', '.') ?></td></tr>
            <tr><td>Mão de obra</td><td class="valor">R$ <?= number_format($orc['valor_mao_obra'] ?? 0, 2, ',', '.') ?></td></tr>
            <tr class="final"><td>Total</td><td class="valor">R$ <?= number_format($orc['valor_total_orcamento'] ?? 0, 2, ',', '.') ?></td></tr>
        </table>

        <?php if (!empty($orc['observacoes'])): ?>
            <div class="obs-box">
                <h3>Observações</h3>
                <p><?= nl2br(htmlspecialchars($orc['observacoes'])) ?></p>
            </div>
        <?php endif; ?>

        <div class="meta-line">
            <?php if (!empty($orc['data_validade'])): ?>
                Válido até <strong><?= date('d/m/Y', strtotime($orc['data_validade'])) ?></strong>
            <?php endif; ?>
            <?php if (!empty($orc['prazo_execucao'])): ?>
                &nbsp;&nbsp;·&nbsp;&nbsp; Prazo estimado: <strong><?= htmlspecialchars($orc['prazo_execucao']) ?></strong>
            <?php endif; ?>
        </div>

        <table class="assinaturas">
            <tr>
                <td>
                    <?php if (!empty($orc['assinatura_cliente'])): ?>
                        <img src="<?= htmlspecialchars($orc['assinatura_cliente']) ?>" style="max-height:60px;display:block;margin:0 auto 4px;"><br>
                    <?php endif; ?>
                    Assinatura do Cliente
                </td>
                <td><?= htmlspecialchars($orc['mecanico_nome'] ?? 'Responsável Técnico') ?></td>
            </tr>
        </table>

    </div>

    <div class="doc-footer">
        Documento gerado eletronicamente pelo Mecanize em <?= date('d/m/Y \à\s H:i') ?>
    </div>

</body>

</html>
