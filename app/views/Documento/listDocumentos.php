<?php
// Variáveis esperadas do controller:
// $ordens          — array de OS finalizadas/concluídas
// $totalDocumentos — int
// $totalValor      — float
// $ticketMedio     — float
// $paginaAtual     — int

// Mock para visualização
$ordens = $ordens ?? [
    ['id' => 1, 'numero' => 'OS-0038', 'cliente' => 'João Silva',    'veiculo' => 'HB20 2021',  'placa' => 'ABC-1234', 'data_abertura' => '2025-05-10 09:00', 'data_conclusao' => '2025-05-12 17:30', 'valor_total_os' => 980.00,  'mecanico' => 'Roberto Oliveira', 'defeito_relatado' => 'Freios falhando',   'diagnostico' => 'Pastilhas e discos desgastados',  'itens' => [['nome' => 'Pastilha de Freio Dianteira', 'tipo' => 'produto', 'qty' => 2, 'preco' => 89.90], ['nome' => 'Revisão de Freios Completa', 'tipo' => 'servico', 'qty' => 1, 'preco' => 150.00]]],
    ['id' => 2, 'numero' => 'OS-0039', 'cliente' => 'Maria Souza',   'veiculo' => 'Civic 2019', 'placa' => 'XYZ-5678', 'data_abertura' => '2025-05-11 08:00', 'data_conclusao' => '2025-05-13 14:00', 'valor_total_os' => 1340.00, 'mecanico' => 'Ana Mendes',       'defeito_relatado' => 'Barulho no motor', 'diagnostico' => 'Correia dentada desgastada',      'itens' => [['nome' => 'Correia Dentada', 'tipo' => 'produto', 'qty' => 1, 'preco' => 185.00], ['nome' => 'Troca de Correia Dentada', 'tipo' => 'servico', 'qty' => 1, 'preco' => 200.00]]],
    ['id' => 3, 'numero' => 'OS-0040', 'cliente' => 'Carlos Pereira', 'veiculo' => 'Gol 2018',   'placa' => 'DEF-9012', 'data_abertura' => '2025-05-14 10:00', 'data_conclusao' => '2025-05-15 16:00', 'valor_total_os' => 430.00,  'mecanico' => 'Felipe Costa',     'defeito_relatado' => 'Bateria fraca',     'diagnostico' => 'Bateria sem carga',               'itens' => [['nome' => 'Bateria 60Ah', 'tipo' => 'produto', 'qty' => 1, 'preco' => 380.00]]],
    ['id' => 4, 'numero' => 'OS-0041', 'cliente' => 'Ana Lima',      'veiculo' => 'Onix 2022',  'placa' => 'GHI-3456', 'data_abertura' => '2025-05-15 09:00', 'data_conclusao' => '2025-05-16 11:00', 'valor_total_os' => 560.00,  'mecanico' => 'Roberto Oliveira', 'defeito_relatado' => 'Revisão periódica', 'diagnostico' => 'Troca de óleo e filtros',         'itens' => [['nome' => 'Filtro de Óleo', 'tipo' => 'produto', 'qty' => 1, 'preco' => 32.00], ['nome' => 'Troca de Óleo + Filtro', 'tipo' => 'servico', 'qty' => 1, 'preco' => 80.00]]],
];

$totalDocumentos = $totalDocumentos ?? count($ordens);
$totalValor      = $totalValor      ?? array_sum(array_column($ordens, 'valor_total_os'));
$ticketMedio     = $ticketMedio     ?? ($totalDocumentos ? $totalValor / $totalDocumentos : 0);
$paginaAtual     = $paginaAtual     ?? 1;
?>

<!-- ── Page Header ── -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Serviços Finalizados</h1>
        <p class="page-header__subtitle">Selecione uma OS concluída para gerar o documento.</p>
    </div>
</div>

<!-- ── Stat Cards ── -->
<section class="stat-grid" style="grid-template-columns:repeat(3,1fr);">

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">OS Disponíveis</div>
            <div class="stat-card__icon b1">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                    <line x1="16" y1="13" x2="8" y2="13" />
                    <line x1="16" y1="17" x2="8" y2="17" />
                    <polyline points="10 9 9 9 8 9" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $totalDocumentos ?></div>
        <div class="stat-card__delta">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
                <polyline points="17 6 23 6 23 12" />
            </svg>
            OS concluídas disponíveis
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Valor Total</div>
            <div class="stat-card__icon b3">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23" />
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value">R$ <?= number_format($totalValor, 2, ',', '.') ?></div>
        <div class="stat-card__delta">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
                <polyline points="17 6 23 6 23 12" />
            </svg>
            Soma das OS do período
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Ticket Médio</div>
            <div class="stat-card__icon b2">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="20" x2="18" y2="10" />
                    <line x1="12" y1="20" x2="12" y2="4" />
                    <line x1="6" y1="20" x2="6" y2="14" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value">R$ <?= number_format($ticketMedio, 2, ',', '.') ?></div>
        <div class="stat-card__delta neutral">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Por ordem concluída
        </div>
    </div>

</section>

<!-- ── Tabela de OS Concluídas ── -->
<section class="card">

    <!-- Toolbar -->
    <div class="table-toolbar">
        <div class="search-wrap">
            <input
                type="text"
                id="busca-os"
                placeholder="Buscar cliente, veículo ou OS..."
                oninput="filtrarTabela()">
        </div>

        <select id="filtro-mecanico" class="toolbar-select" onchange="filtrarTabela()">
            <option value="">Mecânico: Todos</option>
            <?php
            $mecanicosUnicos = array_unique(array_column($ordens, 'mecanico'));
            foreach ($mecanicosUnicos as $m):
            ?>
                <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
            <?php endforeach; ?>
        </select>

        <input type="date" id="filtro-data" class="toolbar-select" onchange="filtrarTabela()">

        <span style="margin-left:auto;font-size:12px;color:var(--color-text-muted);" id="contador-resultados">
            <?= count($ordens) ?> resultado(s)
        </span>
    </div>

    <!-- Banner informativo -->
    <div style="padding:10px 24px;background:var(--color-primary-soft);border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:8px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        <span style="font-size:12px;color:var(--color-primary);font-weight:500;">
            Clique em qualquer OS para gerar o documento final automaticamente.
        </span>
    </div>

    <!-- Tabela -->
    <div class="table-wrap">
        <table class="table" id="tabela-os">
            <thead>
                <tr>
                    <th>OS</th>
                    <th>Cliente</th>
                    <th>Veículo / Placa</th>
                    <th>Mecânico</th>
                    <th>Concluída em</th>
                    <th>Valor Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-os">
                <?php if (!empty($ordens)): ?>
                    <?php foreach ($ordens as $os):
                        $id = $os['id'] ?? 0;
                    ?>
                        <tr class="os-row"
                            data-id="<?= $id ?>"
                            data-os='<?= htmlspecialchars(json_encode($os, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'
                            data-cliente="<?= strtolower($os['cliente'] ?? '') ?>"
                            data-veiculo="<?= strtolower($os['veiculo'] ?? '') ?>"
                            data-numero="<?= strtolower($os['numero'] ?? '') ?>"
                            data-mecanico="<?= htmlspecialchars($os['mecanico'] ?? '') ?>"
                            data-data="<?= substr($os['data_conclusao'] ?? '', 0, 10) ?>"
                            style="cursor:pointer;"
                            onclick="selecionarOS(this)">

                            <td class="id-col" style="color:var(--color-primary);font-weight:700;">
                                <?= htmlspecialchars($os['numero'] ?? '#' . $id) ?>
                            </td>
                            <td style="font-weight:500;"><?= htmlspecialchars($os['cliente'] ?? '-') ?></td>
                            <td>
                                <div style="font-weight:500;"><?= htmlspecialchars($os['veiculo'] ?? '-') ?></div>
                                <?php if (!empty($os['placa'])): ?>
                                    <div class="text-muted fs-12"><?= htmlspecialchars($os['placa']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($os['mecanico'] ?? '-') ?></td>
                            <td>
                                <?= !empty($os['data_conclusao'])
                                    ? date('d/m/Y H:i', strtotime($os['data_conclusao']))
                                    : '-' ?>
                            </td>
                            <td>
                                <span style="font-size:15px;font-weight:700;color:var(--color-primary);">
                                    R$ <?= number_format($os['valor_total_os'] ?? 0, 2, ',', '.') ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <span class="badge badge--info" style="cursor:pointer;white-space:nowrap;">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                        <polyline points="14 2 14 8 20 8" />
                                        <line x1="16" y1="13" x2="8" y2="13" />
                                    </svg>
                                    Gerar documento
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 10px;display:block;opacity:.4;">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                <polyline points="14 2 14 8 20 8" />
                            </svg>
                            Nenhuma OS concluída disponível para documento.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginação -->
    <div class="pagination">
        <span id="pag-info"><?= count($ordens) ?> resultado(s)</span>
        <div class="pagination__pages">
            <button class="pagination__btn">‹</button>
            <button class="pagination__btn is-active"><?= $paginaAtual ?></button>
            <button class="pagination__btn">›</button>
        </div>
    </div>

</section>


<!-- ══════════════════════════════════════════════════════
     MODAL — CONFIRMAR ORÇAMENTO
     ══════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-documento" style="display:none;">
    <div class="modal modal--xl">

        <div class="modal__header">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:40px;height:40px;background:var(--color-primary-soft);color:var(--color-primary);border-radius:var(--radius-md);display:inline-flex;align-items:center;justify-content:center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                        <line x1="16" y1="13" x2="8" y2="13" />
                        <line x1="16" y1="17" x2="8" y2="17" />
                    </svg>
                </div>
                <div>
                    <div class="modal__title">Gerar Documento</div>
                    <div class="modal__subtitle" id="modal-os-ref">Confira os dados antes de finalizar.</div>
                </div>
            </div>
            <button type="button" class="modal__close" onclick="fecharModal()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
        </div>

        <div class="modal__body">

            <!-- ① Dados da OS -->
            <div class="form-section">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="font-size:11px;font-weight:700;">①</span>
                    Dados da OS
                </div>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px 18px;">
                    <div class="form-field">
                        <label>Número da OS</label>
                        <input type="text" id="orc-numero" readonly style="background:var(--color-bg-alt);color:var(--color-text-muted);font-weight:600;cursor:default;">
                    </div>
                    <div class="form-field">
                        <label>Cliente</label>
                        <input type="text" id="orc-cliente" readonly style="background:var(--color-bg-alt);cursor:default;">
                    </div>
                    <div class="form-field">
                        <label>Veículo / Placa</label>
                        <input type="text" id="orc-veiculo" readonly style="background:var(--color-bg-alt);cursor:default;">
                    </div>
                    <div class="form-field">
                        <label>Mecânico</label>
                        <input type="text" id="orc-mecanico" readonly style="background:var(--color-bg-alt);cursor:default;">
                    </div>
                    <div class="form-field">
                        <label>Data de Abertura</label>
                        <input type="text" id="orc-abertura" readonly style="background:var(--color-bg-alt);cursor:default;">
                    </div>
                    <div class="form-field">
                        <label>Data de Conclusão</label>
                        <input type="text" id="orc-conclusao" readonly style="background:var(--color-bg-alt);cursor:default;">
                    </div>
                </div>
            </div>

            <!-- ② Diagnóstico -->
            <div class="form-section">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="font-size:11px;font-weight:700;">②</span>
                    Diagnóstico
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px 18px;">
                    <div class="form-field">
                        <label>Defeito Relatado</label>
                        <textarea id="orc-defeito" rows="3" readonly style="background:var(--color-bg-alt);cursor:default;resize:none;"></textarea>
                    </div>
                    <div class="form-field">
                        <label>Diagnóstico Técnico</label>
                        <textarea id="orc-diagnostico" rows="3" readonly style="background:var(--color-bg-alt);cursor:default;resize:none;"></textarea>
                    </div>
                </div>
            </div>

            <!-- ③ Itens -->
            <div class="form-section">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="font-size:11px;font-weight:700;">③</span>
                    Produtos &amp; Serviços
                </div>
                <div class="table-wrap">
                    <table class="table" id="orc-itens-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Tipo</th>
                                <th style="text-align:center;">Qtd</th>
                                <th style="text-align:right;">Unit.</th>
                                <th style="text-align:right;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="orc-itens-tbody"></tbody>
                    </table>
                </div>
            </div>

            <!-- ④ Resumo Financeiro -->
            <div class="form-section" style="margin-bottom:0;">
                <div class="form-section__title">
                    <span class="ico-wrap ico" style="font-size:11px;font-weight:700;">④</span>
                    Resumo Financeiro
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
                    <div style="background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:12px 14px;">
                        <div class="text-muted fs-12" style="margin-bottom:4px;">Total em Peças</div>
                        <div id="orc-total-pecas" style="font-size:18px;font-weight:700;">R$ 0,00</div>
                    </div>
                    <div style="background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:12px 14px;">
                        <div class="text-muted fs-12" style="margin-bottom:4px;">Total em Serviços</div>
                        <div id="orc-total-servicos" style="font-size:18px;font-weight:700;">R$ 0,00</div>
                    </div>
                    <div style="background:var(--color-primary-soft);border:1px solid var(--color-primary);border-radius:var(--radius-md);padding:12px 14px;">
                        <div style="font-size:11px;color:var(--color-primary);font-weight:600;margin-bottom:4px;">Total da OS</div>
                        <div id="orc-total-os" style="font-size:20px;font-weight:700;color:var(--color-primary);">R$ 0,00</div>
                    </div>
                </div>
            </div>

        </div>

        <div class="modal__footer">
            <div class="modal__footer-left">Gerado em: <?= date('d/m/Y H:i') ?></div>
            <button type="button" class="btn btn--ghost btn--sm" onclick="fecharModal()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
                Cancelar
            </button>
            <button type="button" class="btn btn--primary btn--sm" onclick="finalizarDocumento()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12" />
                    <polyline points="12 5 19 12 12 19" />
                </svg>
                Finalizar
            </button>
        </div>

    </div>
</div>


<!-- ══════════════════════════════════════════════════════
     MODAL — TELA FINAL DO DOCUMENTO
     ══════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-final" style="display:none;">
    <div class="modal modal--xl">

        <div class="modal__header">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:40px;height:40px;background:var(--color-success-soft);color:var(--color-success);border-radius:var(--radius-md);display:inline-flex;align-items:center;justify-content:center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                </div>
                <div>
                    <div class="modal__title">Documento Gerado</div>
                    <div class="modal__subtitle" id="final-ref">Pronto para baixar, imprimir ou compartilhar.</div>
                </div>
            </div>
            <button type="button" class="modal__close" onclick="fecharFinal()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
        </div>

        <div class="modal__body">

            <!-- Ações de exportação -->
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px;">

                <button type="button" onclick="baixarPDF()" class="btn btn--ghost" style="flex-direction:column;height:auto;padding:22px 16px;gap:10px;border-radius:var(--radius-lg);">
                    <div style="width:48px;height:48px;border-radius:var(--radius-md);background:#FEE2E2;color:#DC2626;display:flex;align-items:center;justify-content:center;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                        </svg>
                    </div>
                    <div>
                        <div class="fw-700 fs-13">Baixar PDF</div>
                        <div class="text-muted fs-12" style="margin-top:2px;">Formato padrão</div>
                    </div>
                </button>

                <button type="button" onclick="baixarExcel()" class="btn btn--ghost" style="flex-direction:column;height:auto;padding:22px 16px;gap:10px;border-radius:var(--radius-lg);">
                    <div style="width:48px;height:48px;border-radius:var(--radius-md);background:var(--color-success-soft);color:var(--color-success);display:flex;align-items:center;justify-content:center;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="8" y1="13" x2="16" y2="13" />
                            <line x1="8" y1="17" x2="16" y2="17" />
                        </svg>
                    </div>
                    <div>
                        <div class="fw-700 fs-13">Baixar Excel</div>
                        <div class="text-muted fs-12" style="margin-top:2px;">Planilha detalhada</div>
                    </div>
                </button>

                <button type="button" onclick="imprimirDocumento()" class="btn btn--ghost" style="flex-direction:column;height:auto;padding:22px 16px;gap:10px;border-radius:var(--radius-lg);">
                    <div style="width:48px;height:48px;border-radius:var(--radius-md);background:var(--color-primary-soft);color:var(--color-primary);display:flex;align-items:center;justify-content:center;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 6 2 18 2 18 9" />
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                            <rect x="6" y="14" width="12" height="8" />
                        </svg>
                    </div>
                    <div>
                        <div class="fw-700 fs-13">Imprimir</div>
                        <div class="text-muted fs-12" style="margin-top:2px;">Direto na impressora</div>
                    </div>
                </button>

            </div>

            <!-- Prévia do documento — iframe com o PDF real (mesmo template de app/views/Ordens_Servico/pdfOrdemServico.php) -->
            <div style="border:1px solid var(--color-border);border-radius:var(--radius-lg);overflow:hidden;">

                <div style="padding:10px 16px;background:var(--color-bg-alt);border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:8px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10" />
                        <path d="M12 8v4M12 16h.01" />
                    </svg>
                    <span class="fw-600 fs-12 text-secondary">Prévia do Documento</span>
                </div>

                <iframe id="previa-documento-frame" title="Prévia do documento" style="width:100%;height:640px;border:0;display:block;background:#fff;"></iframe>

            </div>

        </div>

        <div class="modal__footer">
            <div class="modal__footer-left">
                <button type="button" class="btn btn--ghost btn--sm" onclick="voltarParaConferencia()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12" />
                        <polyline points="12 19 5 12 12 5" />
                    </svg>
                    Voltar
                </button>
            </div>
            <button type="button" class="btn btn--ghost btn--sm" onclick="fecharFinal()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
                Fechar
            </button>
            <button type="button" class="btn btn--ghost btn--sm" onclick="imprimirDocumento()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9" />
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                    <rect x="6" y="14" width="12" height="8" />
                </svg>
                Imprimir
            </button>
            <button type="button" class="btn btn--primary btn--sm" onclick="baixarPDF()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                    <polyline points="7 10 12 15 17 10" />
                    <line x1="12" y1="15" x2="12" y2="3" />
                </svg>
                Baixar PDF
            </button>
        </div>

    </div>
</div>


<!-- ── JavaScript (idêntico ao original) ── -->
<script>
    let osAtual = null;

    function fmtBR(v) {
        return 'R$ ' + parseFloat(v || 0).toFixed(2)
            .replace('.', ',')
            .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function fmtData(str) {
        if (!str) return '—';
        const d = new Date(str.replace(' ', 'T'));
        return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function filtrarTabela() {
        const q = document.getElementById('busca-os').value.toLowerCase();
        const mec = document.getElementById('filtro-mecanico').value;
        const dt = document.getElementById('filtro-data').value;
        const linhas = document.querySelectorAll('#tbody-os .os-row');
        let visiveis = 0;
        linhas.forEach(tr => {
            const ok = (!q || tr.dataset.cliente.includes(q) || tr.dataset.veiculo.includes(q) || tr.dataset.numero.includes(q)) &&
                (!mec || tr.dataset.mecanico === mec) &&
                (!dt || tr.dataset.data === dt);
            tr.style.display = ok ? '' : 'none';
            if (ok) visiveis++;
        });
        document.getElementById('contador-resultados').textContent = visiveis + ' resultado(s)';
        document.getElementById('pag-info').textContent = visiveis + ' resultado(s)';
    }

    function selecionarOS(tr) {
        try {
            osAtual = JSON.parse(tr.dataset.os);
        } catch (e) {
            alert('Erro ao carregar dados da OS.');
            return;
        }

        document.getElementById('modal-os-ref').textContent = 'Referência: ' + (osAtual.numero || '#' + osAtual.id);
        document.getElementById('orc-numero').value = osAtual.numero || '#' + osAtual.id;
        document.getElementById('orc-cliente').value = osAtual.cliente || '—';
        document.getElementById('orc-veiculo').value = (osAtual.veiculo || '—') + (osAtual.placa ? ' · ' + osAtual.placa : '');
        document.getElementById('orc-mecanico').value = osAtual.mecanico || '—';
        document.getElementById('orc-abertura').value = fmtData(osAtual.data_abertura);
        document.getElementById('orc-conclusao').value = fmtData(osAtual.data_conclusao);
        document.getElementById('orc-defeito').value = osAtual.defeito_relatado || '—';
        document.getElementById('orc-diagnostico').value = osAtual.diagnostico || '—';

        const itens = osAtual.itens || [];
        let totPecas = 0,
            totServicos = 0;
        document.getElementById('orc-itens-tbody').innerHTML = itens.map(it => {
            const sub = parseFloat(it.preco) * parseInt(it.qty || 1);
            it.tipo === 'produto' ? totPecas += sub : totServicos += sub;
            return `<tr>
            <td style="font-weight:500;">${it.nome}</td>
            <td><span class="badge ${it.tipo === 'produto' ? 'badge--info' : 'badge--success'}">${it.tipo === 'produto' ? 'Peça' : 'Serviço'}</span></td>
            <td style="text-align:center;">${it.qty || 1}</td>
            <td style="text-align:right;font-size:12px;">${fmtBR(it.preco)}</td>
            <td style="text-align:right;font-weight:700;">${fmtBR(sub)}</td>
        </tr>`;
        }).join('') || `<tr><td colspan="5" style="padding:20px;text-align:center;color:var(--color-text-muted);font-size:13px;">Nenhum item registrado.</td></tr>`;

        document.getElementById('orc-total-pecas').textContent = fmtBR(totPecas);
        document.getElementById('orc-total-servicos').textContent = fmtBR(totServicos);
        document.getElementById('orc-total-os').textContent = fmtBR(totPecas + totServicos);
        document.getElementById('modal-documento').style.display = 'flex';
    }

    function fecharModal() {
        document.getElementById('modal-documento').style.display = 'none';
    }

    function finalizarDocumento() {
        if (!osAtual) return;
        fecharModal();

        document.getElementById('final-ref').textContent = 'OS ' + (osAtual.numero || '#' + osAtual.id);
        document.getElementById('previa-documento-frame').src = './exportOrdemServicoPdf?id=' + encodeURIComponent(osAtual.id) + '&inline=1';
        document.getElementById('modal-final').style.display = 'flex';
    }

    function fecharFinal() {
        document.getElementById('modal-final').style.display = 'none';
    }

    function voltarParaConferencia() {
        fecharFinal();
        document.getElementById('modal-documento').style.display = 'flex';
    }

    function imprimirDocumento() {
        if (!osAtual) return;
        window.open('./exportOrdemServicoPdf?id=' + encodeURIComponent(osAtual.id) + '&inline=1', '_blank');
    }

    function baixarPDF() {
        if (!osAtual) return;
        window.location.href = './exportOrdemServicoPdf?id=' + encodeURIComponent(osAtual.id);
    }

    function baixarExcel() {
        if (!osAtual) return;
        const itens = osAtual.itens || [];
        let csv = 'Item;Tipo;Qtd;Preco Unit.;Subtotal\n';
        itens.forEach(it => {
            const sub = (parseFloat(it.preco) * parseInt(it.qty || 1)).toFixed(2);
            csv += `"${it.nome}";"${it.tipo === 'produto' ? 'Peça' : 'Serviço'}";${it.qty || 1};"${parseFloat(it.preco).toFixed(2)}";"${sub}"\n`;
        });
        csv += `\n;;;Total;${parseFloat(osAtual.valor_total_os || 0).toFixed(2)}`;
        const blob = new Blob(['\uFEFF' + csv], {
            type: 'text/csv;charset=utf-8;'
        });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'documento-' + (osAtual.numero || osAtual.id) + '.csv';
        a.click();
        URL.revokeObjectURL(url);
    }

    ['modal-documento', 'modal-final'].forEach(id => {
        document.getElementById(id).addEventListener('click', function(e) {
            if (e.target === this) this.style.display = 'none';
        });
    });
</script>