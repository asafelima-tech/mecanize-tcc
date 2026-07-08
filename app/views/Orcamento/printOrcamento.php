<?php
$statusInfo = [
    'pendente'  => ['label' => 'Aguardando Aprovação', 'badge' => 'badge--warning'],
    'aprovado'  => ['label' => 'Aprovado',              'badge' => 'badge--success'],
    'reprovado' => ['label' => 'Recusado',              'badge' => 'badge--danger'],
][$orc['status'] ?? 'pendente'] ?? ['label' => 'Pendente', 'badge' => 'badge--neutral'];

$pecas    = array_filter($itens, fn($i) => ($i['tipo'] ?? '') !== 'servico');
$servicos = array_filter($itens, fn($i) => ($i['tipo'] ?? '') === 'servico');
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Orçamento <?= htmlspecialchars($orc['numero'] ?? '') ?> — Mecanize</title>
    <style>
        :root {
            --color-bg: #F1F5F9;
            --color-bg-alt: #F8FAFC;
            --color-surface: #FFFFFF;
            --color-border: #E2E8F0;
            --color-border-strong: #CBD5E1;

            --color-text: #111826;
            --color-text-secondary: #1E293B;
            --color-text-muted: #94A3B8;

            --color-primary: #003BDD;
            --color-primary-hover: #0030C4;
            --color-primary-soft: #DBEAFE;
            --color-primary-gradient: linear-gradient(135deg, #003BDD 0%, #0030C4 100%);

            --color-success: #003BDD;
            --color-success-soft: #EBF0FF;
            --color-warning: #F59E0B;
            --color-warning-soft: #FEF3C7;
            --color-danger: #EF4444;
            --color-danger-soft: #FEE2E2;

            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --radius-pill: 999px;

            --shadow-sm: 0 1px 3px rgba(17, 24, 38, 0.05), 0 1px 8px rgba(17, 24, 38, 0.04);
            --shadow-md: 0 4px 10px rgba(17, 24, 38, 0.07), 0 10px 28px rgba(17, 24, 38, 0.07);

            --font-sans: "Inter", "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-sans);
            font-size: 14px;
            color: var(--color-text);
            background: var(--color-bg);
            -webkit-font-smoothing: antialiased;
            padding: 32px 20px 60px;
        }

        /* ── Toolbar (some na impressão) ── */
        .print-toolbar {
            max-width: 800px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .print-toolbar__brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 15px;
            color: var(--color-text);
            letter-spacing: -0.02em;
        }

        .print-toolbar__brand .dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--color-primary-gradient);
        }

        .print-toolbar__actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            height: 38px;
            padding: 0 16px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            letter-spacing: -0.01em;
            cursor: pointer;
            border: none;
            transition: all .15s ease;
        }

        .btn--primary {
            background: var(--color-primary-gradient);
            color: #fff;
            box-shadow: 0 1px 2px rgba(37, 99, 235, .22), 0 3px 10px rgba(37, 99, 235, .18);
        }

        .btn--primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(37, 99, 235, .38);
        }

        .btn--ghost {
            background: #fff;
            color: var(--color-text-secondary);
            border: 1.5px solid var(--color-border);
        }

        .btn--ghost:hover {
            background: var(--color-bg-alt);
            border-color: var(--color-border-strong);
        }

        .btn--ghost.is-active {
            background: var(--color-primary-soft);
            color: var(--color-primary);
            border-color: var(--color-primary);
        }

        /* ── Aviso ao usuário do sistema (some na impressão) ── */
        .aviso-cliente {
            max-width: 800px;
            margin: 0 auto 12px;
            background: var(--color-primary-soft);
            border: 1px solid #BFDBFE;
            color: var(--color-primary);
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font-size: 12.5px;
            font-weight: 500;
            line-height: 1.5;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        .aviso-cliente svg {
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* ── Aviso de modo edição (some na impressão) ── */
        .aviso-edicao {
            max-width: 800px;
            margin: 0 auto 20px;
            background: var(--color-warning-soft);
            border: 1px solid #FDE68A;
            color: #92400E;
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font-size: 12.5px;
            font-weight: 500;
            line-height: 1.5;
            display: none;
            gap: 10px;
            align-items: flex-start;
        }

        .aviso-edicao svg {
            flex-shrink: 0;
            margin-top: 1px;
        }

        .doc.is-editing~.aviso-edicao,
        .is-editing .aviso-edicao {
            display: flex;
        }

        /* ── Documento ── */
        .doc {
            max-width: 800px;
            margin: 0 auto;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .doc-head {
            background: var(--color-bg-alt);
            padding: 28px 32px;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
        }

        .doc-head__brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .doc-head__logo {
            width: 42px;
            height: 42px;
            border-radius: var(--radius-md);
            background: var(--color-primary-gradient);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 800;
            font-size: 15px;
            flex-shrink: 0;
        }

        .doc-head__brand h1 {
            font-size: 17px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--color-text);
        }

        .doc-head__brand .empresa-info {
            font-size: 11.5px;
            color: var(--color-text-muted);
            margin-top: 2px;
            line-height: 1.5;
        }

        .doc-head__ref {
            text-align: right;
        }

        .doc-head__ref .kicker {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--color-text-muted);
        }

        .doc-head__ref .numero {
            font-size: 20px;
            font-weight: 800;
            color: var(--color-primary);
            letter-spacing: -0.02em;
            margin: 2px 0 6px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            height: 22px;
            padding: 0 10px;
            border-radius: var(--radius-pill);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        .badge--success {
            background: var(--color-success-soft);
            color: #002BA8;
        }

        .badge--warning {
            background: var(--color-warning-soft);
            color: #92400E;
        }

        .badge--danger {
            background: var(--color-danger-soft);
            color: #991B1B;
        }

        .badge--neutral {
            background: #F1F5F9;
            color: #475569;
        }

        .doc-body {
            padding: 28px 32px;
        }

        .grid2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        .box h3 {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--color-text-muted);
            margin-bottom: 6px;
        }

        .box p {
            font-size: 13.5px;
            color: var(--color-text);
            line-height: 1.5;
        }

        .box p.strong {
            font-weight: 700;
        }

        .box p.sub {
            color: var(--color-text-muted);
            font-size: 12.5px;
            margin-top: 1px;
        }

        .section-title {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--color-text-muted);
            padding-bottom: 8px;
            margin-bottom: 4px;
            border-bottom: 1px solid var(--color-border);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        thead th {
            text-align: left;
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--color-text-muted);
            padding: 10px 6px;
            background: var(--color-bg-alt);
            border-bottom: 2px solid var(--color-border);
        }

        tbody td {
            padding: 11px 6px;
            border-bottom: 1px solid var(--color-border);
            font-size: 13px;
            color: var(--color-text);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        td.num,
        th.num {
            text-align: right;
        }

        .totais {
            margin-left: auto;
            width: 300px;
            background: var(--color-bg-alt);
            border-radius: var(--radius-md);
            padding: 16px 18px;
        }

        .totais div {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 13px;
            color: var(--color-text-secondary);
        }

        .totais .final {
            border-top: 1.5px solid var(--color-border-strong);
            margin-top: 6px;
            padding-top: 10px;
            font-weight: 800;
            font-size: 17px;
            color: var(--color-primary);
        }

        .obs-box {
            margin-top: 20px;
            padding: 16px 18px;
            background: var(--color-bg-alt);
            border-radius: var(--radius-md);
            border: 1px solid var(--color-border);
        }

        .obs-box h3 {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--color-text-muted);
            margin-bottom: 6px;
        }

        .obs-box p {
            font-size: 13px;
            line-height: 1.6;
            color: var(--color-text);
        }

        .meta-line {
            margin-top: 18px;
            font-size: 12px;
            color: var(--color-text-muted);
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
        }

        .meta-line strong {
            color: var(--color-text-secondary);
        }

        .assinaturas {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 48px;
            margin-top: 64px;
        }

        .assinaturas .linha {
            border-top: 1.5px solid var(--color-border-strong);
            padding-top: 8px;
            text-align: center;
            font-size: 11.5px;
            color: var(--color-text-muted);
            font-weight: 500;
        }

        .doc-footer {
            padding: 16px 32px;
            background: var(--color-bg-alt);
            border-top: 1px solid var(--color-border);
            text-align: center;
            font-size: 10.5px;
            color: var(--color-text-muted);
        }

        /* ── Modo edição ── */
        [data-editable] {
            border-radius: 4px;
            transition: background .12s, box-shadow .12s;
            outline: none;
        }

        .doc.is-editing [data-editable] {
            cursor: text;
            box-shadow: 0 0 0 1px dashed var(--color-border-strong);
            padding: 2px 4px;
            margin: -2px -4px;
        }

        .doc.is-editing [data-editable]:hover {
            box-shadow: 0 0 0 1px var(--color-primary);
            background: var(--color-primary-soft);
        }

        .doc.is-editing [data-editable]:focus {
            box-shadow: 0 0 0 2px var(--color-primary);
            background: #fff;
        }

        /* ── Impressão ── */
        @media print {

            .print-toolbar,
            .aviso-cliente,
            .aviso-edicao {
                display: none !important;
            }

            body {
                background: #fff;
                padding: 0;
            }

            .doc {
                border: none;
                border-radius: 0;
                box-shadow: none;
                max-width: 100%;
            }

            .doc-head,
            .doc-footer {
                background: #fff;
            }

            .totais,
            .obs-box {
                background: #fafafa;
            }

            [data-editable] {
                box-shadow: none !important;
                background: transparent !important;
                padding: 0;
                margin: 0;
            }

            @page {
                margin: 14mm;
            }
        }
    </style>
</head>

<body>

    <div class="print-toolbar">
        <div class="print-toolbar__brand">
            <span class="dot"></span> Mecanize
        </div>
        <div class="print-toolbar__actions">
            <button type="button" class="btn btn--ghost" id="btn-editar" onclick="toggleEdicao()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                </svg>
                <span id="btn-editar-label">Editar</span>
            </button>
            <button type="button" class="btn btn--ghost" onclick="window.close()">Fechar</button>
            <button type="button" class="btn btn--primary" onclick="window.print()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9" />
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                    <rect x="6" y="14" width="12" height="8" />
                </svg>
                Imprimir / Salvar PDF
            </button>
        </div>
    </div>

    <?php if (($orc['status'] ?? '') === 'pendente'): ?>
        <div class="aviso-cliente">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <span>Este documento é o orçamento para aprovação do cliente. Assim que ele aprovar (verbalmente, por assinatura ou por escrito), retorne ao sistema e clique em <strong>"Aprovar e Gerar OS"</strong>.</span>
        </div>
    <?php endif; ?>

    <div class="aviso-edicao" id="aviso-edicao">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
        </svg>
        <span>Modo edição ativado — os campos com contorno tracejado podem ser corrigidos na hora. Essa alteração vale só para esta impressão/PDF, <strong>não é salva no sistema</strong>. Clique em "Concluir edição" quando terminar.</span>
    </div>

    <div class="doc" id="doc">

        <div class="doc-head">
            <div class="doc-head__brand">
                <div class="doc-head__logo">M</div>
                <div>
                    <h1 data-editable><?= htmlspecialchars(defined('EMPRESA_NOME') ? EMPRESA_NOME : 'Sua Oficina') ?></h1>
                    <div class="empresa-info" data-editable>
                        <?= htmlspecialchars(defined('EMPRESA_ENDERECO') ? EMPRESA_ENDERECO : '') ?><br>
                        <?= htmlspecialchars(defined('EMPRESA_TELEFONE') ? EMPRESA_TELEFONE : '') ?>
                        <?= defined('EMPRESA_CNPJ') ? ' · CNPJ: ' . htmlspecialchars(EMPRESA_CNPJ) : '' ?>
                    </div>
                </div>
            </div>
            <div class="doc-head__ref">
                <div class="kicker">Orçamento</div>
                <div class="numero"><?= htmlspecialchars($orc['numero'] ?? '#' . $orc['id_orcamento']) ?></div>
                <span class="badge <?= $statusInfo['badge'] ?>"><?= $statusInfo['label'] ?></span>
                <div style="font-size:11px;color:var(--color-text-muted);margin-top:8px;">
                    Emitido em <?= !empty($orc['data_criacao']) ? date('d/m/Y', strtotime($orc['data_criacao'])) : '-' ?>
                </div>
            </div>
        </div>

        <div class="doc-body">

            <div class="grid2">
                <div class="box">
                    <h3>Cliente</h3>
                    <p class="strong" data-editable><?= htmlspecialchars($orc['cliente_nome'] ?? '-') ?></p>
                </div>
                <div class="box">
                    <h3>Veículo</h3>
                    <p class="strong" data-editable><?= htmlspecialchars($orc['veiculo_modelo'] ?? '-') ?></p>
                    <p class="sub" data-editable><?= htmlspecialchars($orc['placa'] ?? '-') ?><?= !empty($orc['km_veiculo']) ? ' · ' . number_format($orc['km_veiculo'], 0, ',', '.') . ' km' : '' ?></p>
                </div>
            </div>

            <?php if (!empty($orc['defeito_relatado']) || !empty($orc['diagnostico'])): ?>
                <div class="grid2">
                    <?php if (!empty($orc['defeito_relatado'])): ?>
                        <div class="box">
                            <h3>Defeito Relatado</h3>
                            <p data-editable><?= nl2br(htmlspecialchars($orc['defeito_relatado'])) ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($orc['diagnostico'])): ?>
                        <div class="box">
                            <h3>Diagnóstico</h3>
                            <p data-editable><?= nl2br(htmlspecialchars($orc['diagnostico'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($pecas)): ?>
                <div class="section-title">Peças / Produtos</div>
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="num">Qtd</th>
                            <th class="num">Unitário</th>
                            <th class="num">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pecas as $item): ?>
                            <tr>
                                <td data-editable><?= htmlspecialchars($item['nome']) ?></td>
                                <td class="num"><?= (int) $item['qty'] ?></td>
                                <td class="num">R$ <?= number_format($item['preco'], 2, ',', '.') ?></td>
                                <td class="num" style="font-weight:600;">R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if (!empty($servicos)): ?>
                <div class="section-title">Mão de Obra / Serviços</div>
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="num">Qtd</th>
                            <th class="num">Unitário</th>
                            <th class="num">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($servicos as $item): ?>
                            <tr>
                                <td data-editable><?= htmlspecialchars($item['nome']) ?></td>
                                <td class="num"><?= (int) $item['qty'] ?></td>
                                <td class="num">R$ <?= number_format($item['preco'], 2, ',', '.') ?></td>
                                <td class="num" style="font-weight:600;">R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div class="totais">
                <div><span>Peças</span><span>R$ <?= number_format($orc['valor_total_pecas'] ?? 0, 2, ',', '.') ?></span></div>
                <div><span>Serviços</span><span>R$ <?= number_format($orc['valor_total_servicos'] ?? 0, 2, ',', '.') ?></span></div>
                <div><span>Mão de obra</span><span>R$ <?= number_format($orc['valor_mao_obra'] ?? 0, 2, ',', '.') ?></span></div>
                <div class="final"><span>Total</span><span>R$ <?= number_format($orc['valor_total_orcamento'] ?? 0, 2, ',', '.') ?></span></div>
            </div>

            <?php if (!empty($orc['observacoes'])): ?>
                <div class="obs-box">
                    <h3>Observações</h3>
                    <p data-editable><?= nl2br(htmlspecialchars($orc['observacoes'])) ?></p>
                </div>
            <?php endif; ?>

            <div class="meta-line">
                <?php if (!empty($orc['data_validade'])): ?>
                    <span>Válido até <strong><?= date('d/m/Y', strtotime($orc['data_validade'])) ?></strong></span>
                <?php endif; ?>
                <?php if (!empty($orc['prazo_execucao'])): ?>
                    <span>Prazo estimado: <strong data-editable><?= htmlspecialchars($orc['prazo_execucao']) ?></strong></span>
                <?php endif; ?>
            </div>

            <div class="assinaturas">
                <div class="linha">Assinatura do Cliente</div>
                <div class="linha"><?= htmlspecialchars($orc['mecanico_nome'] ?? 'Responsável Técnico') ?></div>
            </div>

        </div>

        <div class="doc-footer">
            Documento gerado eletronicamente pelo Mecanize em <?= date('d/m/Y \à\s H:i') ?>
        </div>
    </div>

    <script>
        // Garante que todo campo comece travado, evitando edição acidental
        document.querySelectorAll('[data-editable]').forEach(el => el.setAttribute('contenteditable', 'false'));

        function toggleEdicao() {
            const doc = document.getElementById('doc');
            const btn = document.getElementById('btn-editar');
            const label = document.getElementById('btn-editar-label');
            const ativo = doc.classList.toggle('is-editing');

            document.querySelectorAll('[data-editable]').forEach(el => {
                el.setAttribute('contenteditable', ativo ? 'true' : 'false');
            });

            btn.classList.toggle('is-active', ativo);
            label.textContent = ativo ? 'Concluir edição' : 'Editar';
            document.getElementById('aviso-edicao').style.display = ativo ? 'flex' : 'none';
        }
    </script>

</body>

</html>