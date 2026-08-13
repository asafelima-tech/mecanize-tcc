<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$clientes           = $clientes           ?? [];
$veiculos           = $veiculos           ?? [];
$mecanicos       = $mecanicos       ?? [];
$produtos           = $produtos           ?? [];
$servicos           = $servicos           ?? [];
$proximoNumero      = $proximoNumero      ?? 'ORC-0001';
$error              = $error              ?? '';
?>

<!-- ============================================================
     MODAL OVERLAY — Novo Orçamento
     ============================================================ -->
<div class="modal-overlay" id="modalCriarOrcamento">
    <div class="modal modal--xl">

        <!-- HEADER -->
        <div class="modal__header">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="
                    width:40px;height:40px;
                    background:var(--color-primary-soft, #DBEAFE);
                    color:#1D49CB;
                    border-radius:var(--radius-md);
                    display:inline-flex;align-items:center;justify-content:center;">
                    <i class="bi bi-file-earmark-text" style="font-size:18px;"></i>
                </div>
                <div>
                    <div class="modal__title">Novo Orçamento</div>
                    <div class="modal__subtitle">Preencha os dados abaixo para criar um orçamento. Após aprovação, uma OS será gerada automaticamente.</div>
                </div>
            </div>
            <a href="#" onclick="history.back(); return false;" class="modal__close" title="Voltar">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </a>
        </div>

        <!-- FORM envolve BODY + FOOTER -->
        <form action="./saveOrcamento" method="POST" id="formCriarOrcamento"
            onsubmit="return prepararSubmitOrc()"
            style=" display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden;">

            <!-- BODY -->
            <div class="modal__body">

                <?php if (!empty($error)): ?>
                    <div style="
                        background:var(--color-danger-soft);color:#991B1B;
                        border:1px solid #FECACA;border-radius:var(--radius-md);
                        padding:12px 16px;margin-bottom:20px;font-size:13px;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <!-- ① IDENTIFICAÇÃO -->
                <div class="form-section">
                    <div class="form-section__title">
                        <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;">①</span>
                        Identificação
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px 18px;">

                        <div class="form-field">
                            <label>Número do Orçamento</label>
                            <input type="text" name="numero_orcamento"
                                value="<?= htmlspecialchars($proximoNumero) ?>"
                                readonly
                                style="background:var(--color-bg-alt);color:var(--color-text-muted);cursor:not-allowed;font-weight:600;">
                        </div>

                        <div class="form-field">
                            <label>Status</label>
                            <input type="hidden" name="status" value="PENDENTE">
                            <div style="display:inline-flex;align-items:center;gap:6px;padding:8px 12px;background:#DBEAFE;border:1px solid #1D49CB;border-radius:var(--radius-md);font-size:12px;font-weight:600;color:#1D49CB;width:100%;box-sizing:border-box;">
                                <i class="bi bi-clock" style="font-size:12px;"></i>
                                Pendente
                            </div>
                        </div>

                        <div class="form-field">
                            <label>Data de Criação <span style="color:var(--color-danger)">*</span></label>
                            <input type="datetime-local" name="data_criacao"
                                value="<?= date('Y-m-d\TH:i') ?>" readonly
                                style="background:var(--color-bg-alt);color:var(--color-text-muted);cursor:not-allowed;font-weight:600;">
                        </div>

                    </div>
                </div>

                <!-- ② CLIENTE E VEÍCULO -->
                <div class="form-section">
                    <div class="form-section__title">
                        <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;">②</span>
                        Cliente e Veículo
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px 18px;">

                        <div class="form-field">
                            <label>Cliente <span style="color:var(--color-danger)">*</span></label>
                            <select name="cliente_id" id="orc-cliente_id" required>
                                <option value="">Selecione o cliente</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?= htmlspecialchars($cliente['id_cliente']) ?>">
                                        <?= htmlspecialchars($cliente['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label>Veículo <span style="color:var(--color-danger)">*</span></label>
                            <select name="veiculo_id" id="orc-veiculo_id" disabled>
                                <option value="">Selecione primeiro o cliente</option>

                                <?php foreach ($veiculos as $v): ?>
                                    <option
                                        value="<?= htmlspecialchars($v['id']) ?>"
                                        data-cliente-id="<?= htmlspecialchars($v['cliente_id']) ?>">
                                        <?= htmlspecialchars(strtoupper($v['placa']) . ' — ' . $v['modelo']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>KM do Veículo</label>
                            <div style="position:relative;">
                                <input
                                    type="number"
                                    name="km_veiculo"
                                    min="0"
                                    placeholder="Ex.: 85000"
                                    style="width:100%;padding-right:42px;">

                                <span
                                    style="position:absolute;
                   right:10px;
                   top:50%;
                   transform:translateY(-50%);
                   font-size:11px;
                   font-weight:600;
                   color:var(--color-text-muted);">
                                    km
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ③ MECÂNICO -->
                <div class="form-section">
                    <div class="form-section__title">
                        <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;">③</span>
                        Mecânico Responsável
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px 18px;">

                        <div class="form-field">
                            <label>Mecânico</label>
                            <select name="mecanico_id" id="orc-mecanico_id">
                                <option value="">Selecione o mecânico</option>
                                <?php foreach ($mecanicos as $m): ?>
                                    <option value="<?= htmlspecialchars($m['id']) ?>"
                                        data-nome="<?= htmlspecialchars($m['nome']) ?>">
                                        <?= htmlspecialchars($m['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-field" id="orc-mec-comissao-wrap" style="display:none;">
                            <label>
                                Comissão do Técnico
                                <span id="orc-mec-comissao-sublabel" style="font-size:11px;font-weight:400;color:var(--color-text-muted);">(% sobre serviços)</span>
                            </label>
                            <div style="position:relative;">
                                <input
                                    type="number"
                                    id="orc-mec-comissao-pct"
                                    min="0" max="100" step="0.1"
                                    placeholder="Ex.: 30"
                                    style="width:100%;box-sizing:border-box;padding-right:36px;">
                                <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:12px;font-weight:700;color:var(--color-text-muted);">%</span>
                            </div>
                        </div>

                        <!-- Base de cálculo da comissão: Serviços / Peças / Ambos -->
                        <div class="form-field" id="orc-comissao-base-wrap" style="display:none;grid-column:1/-1;">
                            <label>
                                Base de Cálculo
                                <span style="font-size:11px;font-weight:400;color:var(--color-text-muted);">(sobre o quê incide o percentual acima)</span>
                            </label>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <button type="button" id="orc-base-servicos" onclick="orcSetBaseComissao('servicos')"
                                    style="flex:1;min-width:140px;display:flex;align-items:center;justify-content:center;gap:6px;padding:9px 12px;border-radius:var(--radius-md);cursor:pointer;font-size:12px;font-weight:600;font-family:inherit;border:1.5px solid var(--color-primary);background:var(--color-primary);color:#fff;transition:all .12s;">
                                    <i class="bi bi-wrench" style="font-size:12px;"></i>
                                    Somente Serviços
                                </button>
                                <button type="button" id="orc-base-pecas" onclick="orcSetBaseComissao('pecas')"
                                    style="flex:1;min-width:140px;display:flex;align-items:center;justify-content:center;gap:6px;padding:9px 12px;border-radius:var(--radius-md);cursor:pointer;font-size:12px;font-weight:600;font-family:inherit;border:1.5px solid var(--color-border);background:#fff;color:var(--color-text-secondary);transition:all .12s;">
                                    <i class="bi bi-box-seam" style="font-size:12px;"></i>
                                    Somente Peças
                                </button>
                                <button type="button" id="orc-base-ambos" onclick="orcSetBaseComissao('ambos')"
                                    style="flex:1;min-width:140px;display:flex;align-items:center;justify-content:center;gap:6px;padding:9px 12px;border-radius:var(--radius-md);cursor:pointer;font-size:12px;font-weight:600;font-family:inherit;border:1.5px solid var(--color-border);background:#fff;color:var(--color-text-secondary);transition:all .12s;">
                                    <i class="bi bi-boxes" style="font-size:12px;"></i>
                                    Peças + Serviços
                                </button>
                            </div>
                        </div>

                        <div id="orc-mec-preview" style="display:none;align-items:center;grid-column:1/-1;">
                            <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);width:100%;box-sizing:border-box;">
                                <div id="orc-mec-avatar" style="width:36px;height:36px;border-radius:50%;background:var(--color-primary-soft);color:var(--color-primary);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;">--</div>
                                <div style="flex:1;">
                                    <div id="orc-mec-nome" style="font-size:13px;font-weight:600;"></div>
                                    <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px;">
                                        Comissão:
                                        <strong id="orc-mec-pct-label" style="color:var(--color-primary);">—</strong>
                                        &nbsp;→&nbsp;
                                        <strong id="orc-mec-valor-calculado" style="color:var(--color-primary);">R$ 0,00</strong>
                                        <span id="orc-mec-preview-sublabel" style="margin-left:4px;color:var(--color-text-muted);font-size:10px;">(sobre total de serviços)</span>
                                    </div>
                                </div>
                                <span class="badge badge--success">Incluído</span>
                            </div>
                        </div>

                        <input type="hidden" name="valor_mao_obra" id="orc-valor_mao_obra" value="0">
                        <input type="hidden" name="comissao_pct" id="orc-h-comissao-pct" value="0">
                        <input type="hidden" name="comissao_base" id="orc-h-comissao-base" value="servicos">

                    </div>
                </div>

                <!-- ④ PRODUTOS & SERVIÇOS -->
                <div class="form-section">
                    <div class="form-section__title">
                        <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;">④</span>
                        Produtos &amp; Serviços
                    </div>

                    <div id="orc-items-empty" style="text-align:center;padding:28px 20px;color:var(--color-text-muted);border:1px dashed var(--color-border);border-radius:var(--radius-md);margin-bottom:12px;">
                        <i class="bi bi-box-seam" style="font-size:28px;opacity:.3;display:block;margin-bottom:8px;"></i>
                        <p style="font-size:12px;">Nenhum item adicionado. Use os botões abaixo.</p>
                    </div>

                    <div id="orc-items-table-wrap" style="display:none;margin-bottom:12px;">
                        <table style="width:100%;border-collapse:collapse;font-size:12px;">
                            <thead>
                                <tr style="background:var(--color-bg-alt);">
                                    <th style="padding:8px 12px;text-align:left;border-bottom:1px solid var(--color-border);font-weight:600;color:var(--color-text-secondary);">Item</th>
                                    <th style="padding:8px 12px;text-align:left;border-bottom:1px solid var(--color-border);font-weight:600;color:var(--color-text-secondary);">Tipo</th>
                                    <th style="padding:8px 12px;text-align:center;border-bottom:1px solid var(--color-border);font-weight:600;color:var(--color-text-secondary);">Qtd</th>
                                    <th style="padding:8px 12px;text-align:right;border-bottom:1px solid var(--color-border);font-weight:600;color:var(--color-text-secondary);">Unit.</th>
                                    <th style="padding:8px 12px;text-align:right;border-bottom:1px solid var(--color-border);font-weight:600;color:var(--color-text-secondary);">Subtotal</th>
                                    <th style="padding:8px 6px;border-bottom:1px solid var(--color-border);"></th>
                                </tr>
                            </thead>
                            <tbody id="orc-items-tbody"></tbody>
                        </table>
                    </div>

                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <button type="button" class="btn btn--ghost btn--sm" onclick="orcAbrirCatalogo('produto')">
                            <i class="bi bi-box-seam" style="font-size:12px;"></i>
                            Adicionar Produto
                        </button>
                        <button type="button" class="btn btn--ghost btn--sm" onclick="orcAbrirCatalogo('servico')">
                            <i class="bi bi-wrench" style="font-size:12px;"></i>
                            Adicionar Serviço
                        </button>
                    </div>

                    <div id="orc-hidden-items-container"></div>
                </div>

                <!-- ⑤ DIAGNÓSTICO -->
                <div class="form-section">
                    <div class="form-section__title">
                        <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;">⑤</span>
                        Diagnóstico
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px 18px;">
                        <div class="form-field">
                            <label>Defeito Relatado</label>
                            <textarea name="defeito_relatado" rows="4" placeholder="Descreva o problema relatado pelo cliente..."></textarea>
                        </div>
                        <div class="form-field">
                            <label>Diagnóstico Técnico</label>
                            <textarea name="diagnostico" rows="4" placeholder="Diagnóstico do técnico responsável..."></textarea>
                        </div>
                        <div class="form-field" style="grid-column:1/-1;">
                            <label>Observações</label>
                            <textarea name="observacoes" rows="3" placeholder="Informações adicionais, alertas ou instruções especiais..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- ⑥ RESUMO FINANCEIRO -->
                <div class="form-section">
                    <div class="form-section__title">
                        <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;">⑥</span>
                        Resumo Financeiro
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;gap:14px;">

                        <div style="background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:12px 14px;">
                            <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:4px;">Total em Peças</div>
                            <div id="orc-resumo-pecas" style="font-size:18px;font-weight:700;color:var(--color-text);">R$ 0,00</div>
                        </div>

                        <div style="background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:12px 14px;">
                            <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:4px;">Total em Serviços</div>
                            <div id="orc-resumo-servicos" style="font-size:18px;font-weight:700;color:var(--color-text);">R$ 0,00</div>
                        </div>

                        <div style="background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:12px 14px;">
                            <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:4px;">Comissão Técnico</div>
                            <div id="orc-resumo-mao-obra" style="font-size:18px;font-weight:700;color:var(--color-text);">R$ 0,00</div>
                        </div>

                        <div style="background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:12px 14px;">
                            <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:4px;">Lucro Estimado</div>
                            <div id="orc-resumo-lucro" style="font-size:18px;font-weight:700;color:#16A34A;">R$ 0,00</div>
                        </div>

                        <div style="background:#DBEAFE;border:1px solid #1D49CB;border-radius:var(--radius-md);padding:12px 14px;">
                            <div style="font-size:11px;color:#1D49CB;font-weight:600;margin-bottom:4px;">Total do Orçamento</div>
                            <div id="orc-resumo-total" style="font-size:20px;font-weight:700;color:#1D49CB;">R$ 0,00</div>
                        </div>

                    </div>
                    <input type="hidden" name="valor_total_pecas" id="orc-h-total-pecas" value="0">
                    <input type="hidden" name="valor_total_servicos" id="orc-h-total-servicos" value="0">
                    <input type="hidden" name="valor_total_orcamento" id="orc-h-total-orcamento" value="0">
                </div>

                <!-- ⑦ VALIDADE -->
                <div class="form-section" style="margin-bottom:0;">
                    <div class="form-section__title">
                        <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;">⑦</span>
                        Validade &amp; Prazo
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px 18px;">
                        <div class="form-field">
                            <label>Válido até</label>
                            <input type="date" name="data_validade"
                                value="<?= date('Y-m-d', strtotime('+14 days')) ?>">
                        </div>
                        <div class="form-field">
                            <label>Prazo de Execução Estimado</label>
                            <input type="text" name="prazo_execucao" placeholder="Ex.: 2 dias úteis">
                        </div>
                    </div>
                </div>

            </div><!-- fecha modal__body -->

            <!-- FOOTER -->
            <div class="modal__footer">
                <div class="modal__footer-left">
                    <span style="margin-right:16px;">Criado em: <?= date('d/m/Y') ?></span>
                    <span>Usuário: <?= htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário') ?> (<?= htmlspecialchars($_SESSION['user_tipo'] ?? 'Administrador') ?>)</span>
                </div>
                <button type="button" class="btn btn--ghost btn--sm" onclick="window.print()">
                    <i class="bi bi-printer" style="font-size:12px;"></i>
                    Imprimir
                </button>
                <button type="button" class="btn btn--danger btn--sm" onclick="limparFormOrc()">
                    <i class="bi bi-trash" style="font-size:12px;"></i>
                    Limpar
                </button>
                <a href="javascript:history.back()" class="btn btn--ghost btn--sm">
                    <i class="bi bi-x-lg" style="font-size:12px;"></i>
                    Cancelar
                </a>
                <button type="submit" class="btn btn--primary btn--sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                    </svg>
                    Salvar Orçamento
                </button>
            </div>

        </form>
    </div>
</div>


<!-- ============================================================
     MODAL CATÁLOGO (reutiliza estrutura da OS, escopo próprio)
     ============================================================ -->
<div id="orc-modal-catalogo" style="
    display:none;position:fixed;inset:0;z-index:1100;
    background:rgba(15,23,42,.45);backdrop-filter:blur(4px);
    align-items:center;justify-content:center;padding:24px;">

    <div style="
        background:#fff;border-radius:var(--radius-lg);
        border:1px solid var(--color-border);
        width:100%;max-width:560px;max-height:88vh;
        display:flex;flex-direction:column;overflow:hidden;
        box-shadow:var(--shadow-lg);
        animation:modal-in .22s cubic-bezier(.16,1,.3,1);">

        <!-- Cabeçalho -->
        <div style="padding:16px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div id="orc-cat-header-icon" style="width:36px;height:36px;background:var(--color-primary-soft);color:var(--color-primary);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;"></div>
                <div>
                    <div id="orc-cat-header-title" style="font-size:14px;font-weight:700;"></div>
                    <div style="font-size:11px;color:var(--color-text-muted);">Selecione itens do catálogo ou crie um serviço personalizado</div>
                </div>
            </div>
            <button type="button" class="modal__close" onclick="orcFecharCatalogo()">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Busca -->
        <div id="orc-cat-search-wrap" style="padding:10px 16px;border-bottom:1px solid var(--color-border);flex-shrink:0;">
            <div class="search-wrap">
                <input type="text" id="orc-cat-search-input"
                    placeholder="Buscar por nome ou categoria..."
                    oninput="orcFiltrarCatalogo()">
            </div>
        </div>

        <!-- Tabs -->
        <div id="orc-cat-tabs" style="display:flex;border-bottom:1px solid var(--color-border);flex-shrink:0;overflow-x:auto;padding:0 12px;"></div>

        <!-- Lista -->
        <div id="orc-cat-lista" style="overflow-y:auto;flex:1;padding:8px 12px;"></div>

        <!-- Painel Personalizado -->
        <div id="orc-cat-custom-panel" style="display:none;flex:1;overflow-y:auto;padding:20px 20px 8px;">

            <div style="background:var(--color-primary-soft);border:1px solid var(--color-primary);border-radius:var(--radius-md);padding:12px 14px;margin-bottom:18px;display:flex;align-items:flex-start;gap:10px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="var(--color-primary)" stroke-width="2" style="flex-shrink:0;margin-top:1px;">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                <p style="font-size:12px;color:var(--color-primary);line-height:1.5;margin:0;">
                    Use esta opção para registrar um serviço específico não cadastrado no catálogo.
                    Informe o nome e o valor — fixo em R$ ou como percentual sobre o total de peças do orçamento.
                </p>
            </div>

            <div class="form-field" style="margin-bottom:16px;">
                <label style="font-size:12px;font-weight:600;color:var(--color-text-secondary);display:block;margin-bottom:6px;">Nome do Serviço <span style="color:var(--color-danger)">*</span></label>
                <input type="text" id="orc-custom-nome" placeholder="Ex.: Regulagem de injeção eletrônica" style="width:100%;box-sizing:border-box;">
            </div>

            <div class="form-field" style="margin-bottom:16px;">
                <label style="font-size:12px;font-weight:600;color:var(--color-text-secondary);display:block;margin-bottom:6px;">Tipo de Custo <span style="color:var(--color-danger)">*</span></label>
                <div style="display:flex;gap:10px;">
                    <label id="orc-custom-tipo-fixo-label" style="flex:1;display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:var(--radius-md);cursor:pointer;border:1.5px solid var(--color-primary);background:var(--color-primary-soft);">
                        <input type="radio" name="orc_custom_tipo_custo" id="orc-custom-tipo-fixo" value="fixo" checked onchange="orcToggleTipoCusto()" style="accent-color:var(--color-primary);width:15px;height:15px;">
                        <span><span style="font-size:12px;font-weight:700;display:block;">Valor Fixo (R$)</span><span style="font-size:11px;color:var(--color-text-muted);">Valor exato em reais</span></span>
                    </label>
                    <label id="orc-custom-tipo-pct-label" style="flex:1;display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:var(--radius-md);cursor:pointer;border:1.5px solid var(--color-border);background:#fff;">
                        <input type="radio" name="orc_custom_tipo_custo" id="orc-custom-tipo-pct" value="porcentagem" onchange="orcToggleTipoCusto()" style="accent-color:var(--color-primary);width:15px;height:15px;">
                        <span><span style="font-size:12px;font-weight:700;display:block;">Porcentagem (%)</span><span style="font-size:11px;color:var(--color-text-muted);">% sobre total de peças</span></span>
                    </label>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
                <div class="form-field" id="orc-custom-campo-fixo">
                    <label style="font-size:12px;font-weight:600;color:var(--color-text-secondary);display:block;margin-bottom:6px;">Valor (R$) <span style="color:var(--color-danger)">*</span></label>
                    <div style="position:relative;">
                        <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:12px;font-weight:700;color:var(--color-text-muted);">R$</span>
                        <input type="number" id="orc-custom-valor" min="0" step="0.01" placeholder="0,00"
                            style="padding-left:32px;width:100%;box-sizing:border-box;"
                            oninput="orcPreviewCustom()">
                    </div>
                </div>
                <div class="form-field" id="orc-custom-campo-pct" style="display:none;">
                    <label style="font-size:12px;font-weight:600;color:var(--color-text-secondary);display:block;margin-bottom:6px;">Percentual (%) <span style="color:var(--color-danger)">*</span></label>
                    <div style="position:relative;">
                        <input type="number" id="orc-custom-pct" min="0" max="100" step="0.1" placeholder="0,0"
                            style="padding-right:32px;width:100%;box-sizing:border-box;"
                            oninput="orcPreviewCustom()">
                        <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:12px;font-weight:700;color:var(--color-text-muted);">%</span>
                    </div>
                </div>
                <div id="orc-custom-preview-wrap" style="display:none;align-self:end;">
                    <div style="padding:10px 14px;background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);">
                        <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:2px;">Valor calculado sobre peças</div>
                        <div id="orc-custom-preview-valor" style="font-size:16px;font-weight:700;color:var(--color-primary);">R$ 0,00</div>
                    </div>
                </div>
            </div>

            <div class="form-field" style="margin-bottom:4px;">
                <label style="font-size:12px;font-weight:600;color:var(--color-text-secondary);display:block;margin-bottom:6px;">Categoria <span style="font-size:11px;font-weight:400;color:var(--color-text-muted);">(opcional)</span></label>
                <input type="text" id="orc-custom-categoria" placeholder="Ex.: Elétrica, Suspensão, Motor..." style="width:100%;box-sizing:border-box;">
            </div>
        </div>

        <!-- Rodapé -->
        <div style="padding:12px 16px;border-top:1px solid var(--color-border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
            <span style="font-size:12px;color:var(--color-text-muted);">
                <span id="orc-cat-sel-count" style="font-weight:700;color:var(--color-primary);">0</span> selecionado(s)
            </span>
            <div style="display:flex;gap:8px;">
                <button type="button" class="btn btn--ghost btn--sm" onclick="orcFecharCatalogo()">Cancelar</button>
                <button type="button" class="btn btn--primary btn--sm" id="orc-cat-confirmar-btn" onclick="orcConfirmarCatalogo()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    Confirmar
                </button>
            </div>
        </div>
    </div>
</div>


<!-- ============================================================
     JAVASCRIPT — escopo isolado (prefixo orc)
     ============================================================ -->
<script>
    (function() {
        /* ---- DADOS PHP → JS ---- */
        const ORC_CATALOGO = {
            produto: <?= json_para_script(array_map(fn($p) => array_merge($p, ['id' => (string)($p['id'] ?? '')]), $produtos)) ?>,
            servico: <?= json_para_script(array_map(fn($s) => array_merge($s, ['id' => (string)($s['id'] ?? '')]), $servicos)) ?>
        };

        /* ---- ESTADO ---- */
        let orcItens = [];
        let orcTipo = 'produto';
        let orcSelTemp = {};
        let orcCatFiltro = null;
        let orcAbaAtual = 'catalogo';

        /* ---- UTILITÁRIOS ---- */
        function fmtBR(v) {
            return 'R$ ' + parseFloat(v || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function iniciaisOrc(nome) {
            return nome.split(' ').slice(0, 2).map(p => p[0]).join('').toUpperCase();
        }

        function orcTotalPecas() {
            return orcItens.reduce((a, it) => it.tipo === 'produto' ? a + it.preco * it.qty : a, 0);
        }

        function orcTotalServicos() {
            return orcItens.reduce((a, it) => it.tipo === 'servico' ? a + it.preco * it.qty : a, 0);
        }

        /* ---- MECÂNICO ---- */
        function atualizarMecanicoOrc() {
            const sel = document.getElementById('orc-mecanico_id');
            const opt = sel.options[sel.selectedIndex];
            const prev = document.getElementById('orc-mec-preview');
            const wrap = document.getElementById('orc-mec-comissao-wrap');
            const baseWrap = document.getElementById('orc-comissao-base-wrap');
            if (!opt || !opt.value) {
                prev.style.display = 'none';
                wrap.style.display = 'none';
                baseWrap.style.display = 'none';
                document.getElementById('orc-mec-comissao-pct').value = '';
                document.getElementById('orc-valor_mao_obra').value = '0';
                document.getElementById('orc-h-comissao-pct').value = '0';
                orcRecalcular();
                return;
            }
            const nome = opt.dataset.nome;
            document.getElementById('orc-mec-avatar').textContent = iniciaisOrc(nome);
            document.getElementById('orc-mec-nome').textContent = nome;
            wrap.style.display = 'block';
            baseWrap.style.display = 'block';
            prev.style.display = 'flex';
            recalcularComissaoOrc();
        };

        /* ---- BASE DE CÁLCULO DA COMISSÃO (serviços / peças / ambos) ---- */
        let orcComissaoBase = 'servicos';

        function orcBaseComissaoValor() {
            if (orcComissaoBase === 'pecas') return orcTotalPecas();
            if (orcComissaoBase === 'ambos') return orcTotalPecas() + orcTotalServicos();
            return orcTotalServicos();
        }

        function orcBaseComissaoTexto() {
            if (orcComissaoBase === 'pecas') return 'peças';
            if (orcComissaoBase === 'ambos') return 'peças e serviços';
            return 'serviços';
        }

        window.orcSetBaseComissao = function(base) {
            orcComissaoBase = base;
            document.getElementById('orc-h-comissao-base').value = base;

            ['servicos', 'pecas', 'ambos'].forEach(b => {
                const btn = document.getElementById('orc-base-' + b);
                if (!btn) return;
                const ativo = b === base;
                btn.style.background = ativo ? 'var(--color-primary)' : '#fff';
                btn.style.color = ativo ? '#fff' : 'var(--color-text-secondary)';
                btn.style.borderColor = ativo ? 'var(--color-primary)' : 'var(--color-border)';
            });

            const texto = orcBaseComissaoTexto();
            document.getElementById('orc-mec-comissao-sublabel').textContent = '(% sobre ' + texto + ')';
            document.getElementById('orc-mec-preview-sublabel').textContent = '(sobre total de ' + texto + ')';

            recalcularComissaoOrc();
        };

        function recalcularComissaoOrc() {
            const pct = parseFloat(document.getElementById('orc-mec-comissao-pct').value) || 0;
            const base = orcBaseComissaoValor();
            const val = base * pct / 100;
            document.getElementById('orc-mec-pct-label').textContent = pct > 0 ? pct + '%' : '—';
            document.getElementById('orc-mec-valor-calculado').textContent = fmtBR(val);
            document.getElementById('orc-valor_mao_obra').value = val.toFixed(2);
            document.getElementById('orc-h-comissao-pct').value = pct.toFixed(2);
            orcRecalcular();
        };

        /* ---- CATÁLOGO — abrir/fechar ---- */
        window.orcAbrirCatalogo = function(tipo) {
            orcTipo = tipo;
            orcCatFiltro = null;
            orcSelTemp = {};
            orcAbaAtual = 'catalogo';
            orcItens.forEach(it => {
                if (it.tipo === tipo && !it.custom) orcSelTemp[String(it.id)] = true;
            });

            const icons = {
                produto: `<i class="bi bi-box-seam" style="font-size:16px;"></i>`,
                servico: `<i class="bi bi-wrench" style="font-size:16px;"></i>`
            };
            document.getElementById('orc-cat-header-icon').innerHTML = icons[tipo];
            document.getElementById('orc-cat-header-title').textContent =
                tipo === 'produto' ? 'Adicionar Produto' : 'Adicionar Serviço';

            document.getElementById('orc-cat-search-input').value = '';
            orcRenderTabs();
            orcAtivarAbaCatalogo();
            document.getElementById('orc-modal-catalogo').style.display = 'flex';
        };

        window.orcFecharCatalogo = function() {
            document.getElementById('orc-modal-catalogo').style.display = 'none';
        };

        /* ---- TABS ---- */
        function orcGetCats() {
            return [...new Set(ORC_CATALOGO[orcTipo].map(i => i.categoria))];
        }

        function orcRenderTabs() {
            const cats = orcGetCats();
            const base = `padding:10px 14px;font-size:12px;font-weight:500;border:none;background:none;cursor:pointer;border-bottom:2px solid transparent;color:var(--color-text-secondary);white-space:nowrap;font-family:inherit;`;
            const ativo = `border-bottom-color:var(--color-primary);color:var(--color-primary);font-weight:600;`;
            let html = `<button type="button" onclick="orcSetFiltro(null,this)" style="${base}${ativo}">Todos</button>`;
            html += cats.map(c => `<button type="button" onclick="orcSetFiltro('${c}',this)" style="${base}">${c}</button>`).join('');
            if (orcTipo === 'servico') {
                html += `<button type="button" id="orc-tab-personalizado" onclick="orcAtivarPersonalizado(this)" style="${base}">
                <span style="display:inline-flex;align-items:center;gap:5px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Personalizado
                </span>
            </button>`;
            }
            document.getElementById('orc-cat-tabs').innerHTML = html;
        }

        window.orcSetFiltro = function(cat, btn) {
            orcCatFiltro = cat;
            orcAbaAtual = 'catalogo';
            document.querySelectorAll('#orc-cat-tabs button').forEach(b => {
                b.style.borderBottomColor = 'transparent';
                b.style.color = 'var(--color-text-secondary)';
                b.style.fontWeight = '500';
            });
            btn.style.borderBottomColor = 'var(--color-primary)';
            btn.style.color = 'var(--color-primary)';
            btn.style.fontWeight = '600';
            orcAtivarAbaCatalogo();
        };

        function orcAtivarAbaCatalogo() {
            orcAbaAtual = 'catalogo';
            document.getElementById('orc-cat-lista').style.display = 'block';
            document.getElementById('orc-cat-custom-panel').style.display = 'none';
            document.getElementById('orc-cat-search-wrap').style.display = 'block';
            document.getElementById('orc-cat-sel-count').parentElement.style.display = '';
            document.getElementById('orc-cat-confirmar-btn').textContent = 'Confirmar';
            orcRenderLista();
            orcAtualizarContador();
        }

        window.orcAtivarPersonalizado = function(btn) {
            orcAbaAtual = 'personalizado';
            document.querySelectorAll('#orc-cat-tabs button').forEach(b => {
                b.style.borderBottomColor = 'transparent';
                b.style.color = 'var(--color-text-secondary)';
                b.style.fontWeight = '500';
            });
            btn.style.borderBottomColor = 'var(--color-primary)';
            btn.style.color = 'var(--color-primary)';
            btn.style.fontWeight = '600';
            document.getElementById('orc-cat-lista').style.display = 'none';
            document.getElementById('orc-cat-custom-panel').style.display = 'block';
            document.getElementById('orc-cat-search-wrap').style.display = 'none';
            document.getElementById('orc-cat-sel-count').parentElement.style.display = 'none';
            document.getElementById('orc-cat-confirmar-btn').textContent = 'Adicionar Serviço';
            document.getElementById('orc-custom-nome').value = '';
            document.getElementById('orc-custom-valor').value = '';
            document.getElementById('orc-custom-pct').value = '';
            document.getElementById('orc-custom-categoria').value = '';
            document.getElementById('orc-custom-preview-wrap').style.display = 'none';
            document.getElementById('orc-custom-tipo-fixo').checked = true;
            orcToggleTipoCusto();
        };

        /* ---- PAINEL PERSONALIZADO ---- */
        window.orcToggleTipoCusto = function() {
            const isFixo = document.getElementById('orc-custom-tipo-fixo').checked;
            const lFixo = document.getElementById('orc-custom-tipo-fixo-label');
            const lPct = document.getElementById('orc-custom-tipo-pct-label');
            lFixo.style.border = isFixo ? '1.5px solid var(--color-primary)' : '1.5px solid var(--color-border)';
            lFixo.style.background = isFixo ? 'var(--color-primary-soft)' : '#fff';
            lPct.style.border = !isFixo ? '1.5px solid var(--color-primary)' : '1.5px solid var(--color-border)';
            lPct.style.background = !isFixo ? 'var(--color-primary-soft)' : '#fff';
            document.getElementById('orc-custom-campo-fixo').style.display = isFixo ? 'block' : 'none';
            document.getElementById('orc-custom-campo-pct').style.display = !isFixo ? 'block' : 'none';
            if (isFixo) document.getElementById('orc-custom-preview-wrap').style.display = 'none';
            orcPreviewCustom();
        };

        window.orcPreviewCustom = function() {
            const isPct = document.getElementById('orc-custom-tipo-pct').checked;
            if (!isPct) {
                document.getElementById('orc-custom-preview-wrap').style.display = 'none';
                return;
            }
            const pct = parseFloat(document.getElementById('orc-custom-pct').value) || 0;
            const base = orcTotalPecas();
            const calc = base * pct / 100;
            const wrap = document.getElementById('orc-custom-preview-wrap');
            wrap.style.display = 'block';
            document.getElementById('orc-custom-preview-valor').textContent = fmtBR(calc);
            const inner = wrap.querySelector('div');
            const label = wrap.querySelector('[style*="font-size:11"]');
            if (base === 0) {
                inner.style.borderColor = '#FEF3C7';
                inner.style.background = '#FFFBEB';
                label.textContent = '⚠ Nenhuma peça adicionada ainda';
            } else {
                inner.style.borderColor = 'var(--color-border)';
                inner.style.background = 'var(--color-bg-alt)';
                label.textContent = 'Valor calculado sobre peças';
            }
        };

        /* ---- CATÁLOGO — filtrar/renderizar ---- */
        window.orcFiltrarCatalogo = function() {
            orcRenderLista();
        };

        function orcRenderLista() {
            const q = (document.getElementById('orc-cat-search-input').value || '').toLowerCase();
            const lista = ORC_CATALOGO[orcTipo].filter(i => {
                const mQ = i.nome.toLowerCase().includes(q) || i.categoria.toLowerCase().includes(q);
                const mC = !orcCatFiltro || i.categoria === orcCatFiltro;
                return mQ && mC;
            });
            const el = document.getElementById('orc-cat-lista');
            if (!lista.length) {
                el.innerHTML = `<div style="text-align:center;padding:32px;color:var(--color-text-muted);font-size:13px;">Nenhum item encontrado.</div>`;
                orcAtualizarContador();
                return;
            }
            el.innerHTML = lista.map(i => {
                const sel = !!orcSelTemp[i.id];
                return `<div id="orc-cat-item-${i.id}" onclick="orcToggleSel('${i.id}')"
                style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin-bottom:4px;
                       border-radius:var(--radius-md);cursor:pointer;
                       border:1px solid ${sel ? 'var(--color-primary)' : 'var(--color-border)'};
                       background:${sel ? 'var(--color-primary-soft)' : '#fff'};transition:all .12s;">
                <div style="flex:1;">
                    <div style="font-size:13px;font-weight:600;color:var(--color-text);">${i.nome}</div>
                    <div style="font-size:11px;color:var(--color-text-muted);">${i.categoria}</div>
                </div>
                <span style="font-size:13px;font-weight:700;color:var(--color-primary);margin-right:8px;">${fmtBR(i.preco)}</span>
              <div class="orc-cat-check" style="width:20px;height:20px;border-radius:50%;flex-shrink:0;
            border:1px solid ${sel ? 'var(--color-primary)' : 'var(--color-border-strong)'};
            background:${sel ? 'var(--color-primary)' : '#fff'};
            display:flex;align-items:center;justify-content:center;">
                    ${sel ? `<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>` : ''}
                </div>
            </div>`;
            }).join('');
            orcAtualizarContador();
        }

        window.orcToggleSel = function(id) {
            id = String(id);
            if (orcSelTemp[id]) delete orcSelTemp[id];
            else orcSelTemp[id] = true;
            const item = ORC_CATALOGO[orcTipo].find(x => String(x.id) === id);
            if (!item) return;
            const sel = !!orcSelTemp[id];
            const el = document.getElementById('orc-cat-item-' + id);
            if (el) {
                el.style.border = `1px solid ${sel ? 'var(--color-primary)' : 'var(--color-border)'}`;
                el.style.background = sel ? 'var(--color-primary-soft)' : '#fff';
                const circle = el.querySelector('.orc-cat-check');
                circle.style.border = `1px solid ${sel ? 'var(--color-primary)' : 'var(--color-border-strong)'}`;
                circle.style.background = sel ? 'var(--color-primary)' : '#fff';
                circle.innerHTML = sel ?
                    `<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>` : '';
            }
            orcAtualizarContador();
        };

        function orcAtualizarContador() {
            document.getElementById('orc-cat-sel-count').textContent = Object.keys(orcSelTemp).length;
        }

        /* ---- CONFIRMAR CATÁLOGO ---- */
        window.orcConfirmarCatalogo = function() {
            if (orcAbaAtual === 'personalizado') {
                const nome = document.getElementById('orc-custom-nome').value.trim();
                const isPct = document.getElementById('orc-custom-tipo-pct').checked;
                const categoria = document.getElementById('orc-custom-categoria').value.trim() || 'Personalizado';
                if (!nome) {
                    document.getElementById('orc-custom-nome').focus();
                    document.getElementById('orc-custom-nome').style.borderColor = 'var(--color-danger)';
                    return;
                }
                document.getElementById('orc-custom-nome').style.borderColor = '';
                let preco = 0,
                    pct = null;
                if (isPct) {
                    pct = parseFloat(document.getElementById('orc-custom-pct').value) || 0;
                    preco = orcTotalPecas() * pct / 100;
                } else {
                    preco = parseFloat(document.getElementById('orc-custom-valor').value) || 0;
                }
                if (preco <= 0 && (!isPct || pct <= 0)) {
                    const campo = isPct ? document.getElementById('orc-custom-pct') : document.getElementById('orc-custom-valor');
                    campo.focus();
                    campo.style.borderColor = 'var(--color-danger)';
                    return;
                }
                orcItens.push({
                    id: 'custom_' + Date.now(),
                    nome,
                    tipo: 'servico',
                    categoria,
                    preco,
                    qty: 1,
                    custom: true,
                    pct: isPct ? pct : null
                });
                orcFecharCatalogo();
                orcRenderTabela();
                orcRecalcular();
                return;
            }

            orcItens = orcItens.filter(o => o.tipo !== orcTipo || o.custom || orcSelTemp[o.id]);
            Object.keys(orcSelTemp).forEach(id => {
                if (!orcItens.find(o => o.id === id)) {
                    const item = ORC_CATALOGO[orcTipo].find(i => String(i.id) === String(id));
                    if (item) orcItens.push({
                        ...item,
                        tipo: orcTipo,
                        qty: 1,
                        preco: parseFloat(item.preco) || 0,
                        custom: false
                    });
                }
            });
            orcFecharCatalogo();
            orcRenderTabela();
            orcRecalcular();
        };

        /* ---- TABELA DE ITENS ---- */
        function orcRenderTabela() {
            const empty = document.getElementById('orc-items-empty');
            const wrap = document.getElementById('orc-items-table-wrap');
            const tbody = document.getElementById('orc-items-tbody');
            if (!orcItens.length) {
                empty.style.display = 'block';
                wrap.style.display = 'none';
                return;
            }
            empty.style.display = 'none';
            wrap.style.display = 'block';
            tbody.innerHTML = orcItens.map((it, idx) => `
            <tr onmouseover="this.style.background='var(--color-bg-alt)'" onmouseout="this.style.background=''">
                <td style="padding:9px 12px;">
                    <div style="display:flex;align-items:center;gap:6px;">
                        <div style="font-size:13px;font-weight:600;color:var(--color-text);">${it.nome}</div>
                        ${it.custom ? `<span style="font-size:10px;font-weight:600;padding:2px 6px;border-radius:4px;background:#F0FDF4;color:#16A34A;border:1px solid #BBF7D0;">Personalizado</span>` : ''}
                        ${it.pct != null ? `<span style="font-size:10px;color:var(--color-text-muted);">(${it.pct}% s/ peças)</span>` : ''}
                    </div>
                    <div style="font-size:11px;color:var(--color-text-muted);">${it.categoria}</div>
                </td>
                <td style="padding:9px 12px;">
                    <span class="badge ${it.tipo === 'produto' ? 'badge--info' : 'badge--success'}">
                        ${it.tipo === 'produto' ? 'Peça' : 'Serviço'}
                    </span>
                </td>
                <td style="padding:9px 12px;text-align:center;">
                    ${it.custom ? `<span style="font-size:13px;color:var(--color-text-muted);">—</span>` : `
                    <div style="display:inline-flex;align-items:center;gap:4px;">
                        <button type="button" onclick="orcAlterarQty(${idx},-1)" style="width:22px;height:22px;border-radius:4px;border:1px solid var(--color-border);background:var(--color-bg-alt);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;color:var(--color-text-secondary);">−</button>
                        <span style="min-width:24px;text-align:center;font-size:13px;font-weight:700;">${it.qty}</span>
                        <button type="button" onclick="orcAlterarQty(${idx},1)" style="width:22px;height:22px;border-radius:4px;border:1px solid var(--color-border);background:var(--color-bg-alt);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;color:var(--color-text-secondary);">+</button>
                    </div>`}
                </td>
                <td style="padding:9px 12px;text-align:right;font-size:12px;">${fmtBR(it.preco)}</td>
                <td style="padding:9px 12px;text-align:right;font-size:13px;font-weight:700;">${fmtBR(it.preco * it.qty)}</td>
                <td style="padding:9px 6px;text-align:center;">
                    <button type="button" onclick="orcRemoverItem(${idx})" class="row-action-btn danger" title="Remover">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                    </button>
                </td>
            </tr>`).join('');
        }

        window.orcAlterarQty = function(idx, delta) {
            if (orcItens[idx].custom) return;
            orcItens[idx].qty = Math.max(1, orcItens[idx].qty + delta);
            orcRenderTabela();
            orcRecalcular();
        };

        window.orcRemoverItem = function(idx) {
            orcItens.splice(idx, 1);
            orcRenderTabela();
            orcRecalcular();
            orcRecalcularCustomPct();
        };

        function orcRecalcularCustomPct() {
            const base = orcTotalPecas();
            let mudou = false;
            orcItens.forEach(it => {
                if (it.custom && it.pct != null) {
                    const novo = base * it.pct / 100;
                    if (novo !== it.preco) {
                        it.preco = novo;
                        mudou = true;
                    }
                }
            });
            if (mudou) {
                orcRenderTabela();
                orcRecalcular();
            }
        }

        /* ---- RESUMO FINANCEIRO ---- */
        function orcRecalcular() {
            const totalPecas = orcTotalPecas();
            const totalServicos = orcTotalServicos();
            const pctComissao = parseFloat(document.getElementById('orc-mec-comissao-pct').value) || 0;
            const baseComissao = orcBaseComissaoValor();
            const comissao = baseComissao * pctComissao / 100;
            const totalOrc = totalPecas + totalServicos;
            const lucro = totalOrc - comissao;

            document.getElementById('orc-valor_mao_obra').value = comissao.toFixed(2);
            document.getElementById('orc-h-comissao-pct').value = pctComissao.toFixed(2);
            document.getElementById('orc-mec-pct-label').textContent = pctComissao > 0 ? pctComissao + '%' : '—';
            document.getElementById('orc-mec-valor-calculado').textContent = fmtBR(comissao);

            document.getElementById('orc-resumo-pecas').textContent = fmtBR(totalPecas);
            document.getElementById('orc-resumo-servicos').textContent = fmtBR(totalServicos);
            document.getElementById('orc-resumo-mao-obra').textContent = fmtBR(comissao);
            document.getElementById('orc-resumo-lucro').textContent = fmtBR(lucro);
            document.getElementById('orc-resumo-total').textContent = fmtBR(totalOrc);

            document.getElementById('orc-h-total-pecas').value = totalPecas.toFixed(2);
            document.getElementById('orc-h-total-servicos').value = totalServicos.toFixed(2);
            document.getElementById('orc-h-total-orcamento').value = totalOrc.toFixed(2);
        }

        /* ---- PREPARAR SUBMIT ---- */
        window.prepararSubmitOrc = function() {
            // Valida veículo manualmente (estava disabled, required não funciona nele)
            const veiculoSel = document.getElementById('orc-veiculo_id');
            veiculoSel.disabled = false;

            if (!veiculoSel.value) {
                veiculoSel.style.borderColor = 'var(--color-danger)';
                // Rola até o campo
                veiculoSel.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                veiculoSel.disabled = true; // mantém desabilitado se falhar
                return false; // bloqueia submit
            }
            veiculoSel.style.borderColor = '';

            // Monta os hidden dos itens
            const container = document.getElementById('orc-hidden-items-container');
            container.innerHTML = '';
            orcItens.forEach((it, i) => {
                const add = (name, val) => {
                    const inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = name;
                    inp.value = val;
                    container.appendChild(inp);
                };
                add(`itens[${i}][id]`, it.id);
                add(`itens[${i}][nome]`, it.nome);
                add(`itens[${i}][tipo]`, it.tipo);
                add(`itens[${i}][categoria]`, it.categoria);
                add(`itens[${i}][preco]`, it.preco.toFixed(2));
                add(`itens[${i}][qty]`, it.qty);
                add(`itens[${i}][subtotal]`, (it.preco * it.qty).toFixed(2));
                add(`itens[${i}][custom]`, it.custom ? '1' : '0');
                if (it.pct != null) add(`itens[${i}][pct]`, it.pct);
            });

            return true;
        };

        /* ---- FILTRAR VEÍCULOS PELO CLIENTE ---- */
        const orcClienteSelect = document.getElementById('orc-cliente_id');
        const orcVeiculoSelect = document.getElementById('orc-veiculo_id');

        const orcTodasOptionsVeiculos = Array.from(orcVeiculoSelect.options).map(option => {
            return option.cloneNode(true);
        });

        // Instância do Tom Select (se o campo tiver sido inicializado por essa lib em algum outro script)
        function orcGetTS(select) {
            return select ? select.tomselect : null;
        }

        function orcResetarSelectVeiculos(mensagem = 'Selecione primeiro o cliente') {
            const ts = orcGetTS(orcVeiculoSelect);

            if (ts) {
                ts.clear(true);
                ts.clearOptions();
                ts.addOption({
                    value: '',
                    text: mensagem
                });
                ts.refreshOptions(false);
                ts.disable();
                return;
            }

            orcVeiculoSelect.innerHTML = '';
            const option = document.createElement('option');
            option.value = '';
            option.textContent = mensagem;
            orcVeiculoSelect.appendChild(option);
            orcVeiculoSelect.disabled = true;
        }

        function orcFiltrarVeiculosPorCliente() {
            const clienteId = orcClienteSelect.value;
            const ts = orcGetTS(orcVeiculoSelect);

            if (!clienteId) {
                orcResetarSelectVeiculos('Selecione primeiro o cliente');
                return;
            }

            const veiculosFiltrados = orcTodasOptionsVeiculos.filter(
                opt => opt.value && opt.dataset.clienteId === clienteId
            );

            if (ts) {
                ts.clear(true);
                ts.clearOptions();

                if (veiculosFiltrados.length === 0) {
                    ts.addOption({
                        value: '',
                        text: 'Nenhum veículo cadastrado para este cliente'
                    });
                    ts.refreshOptions(false);
                    ts.disable();
                    return;
                }

                ts.addOption({
                    value: '',
                    text: 'Selecione o veículo'
                });
                veiculosFiltrados.forEach(opt => {
                    ts.addOption({
                        value: opt.value,
                        text: opt.textContent.trim()
                    });
                });
                ts.refreshOptions(false);
                ts.enable();
                return;
            }

            // Fallback para <select> nativo (sem Tom Select)
            orcVeiculoSelect.innerHTML = '';

            const optionInicial = document.createElement('option');
            optionInicial.value = '';
            optionInicial.textContent = 'Selecione o veículo';
            orcVeiculoSelect.appendChild(optionInicial);

            if (veiculosFiltrados.length === 0) {
                orcResetarSelectVeiculos('Nenhum veículo cadastrado para este cliente');
                return;
            }

            veiculosFiltrados.forEach(opt => {
                orcVeiculoSelect.appendChild(opt.cloneNode(true));
            });

            orcVeiculoSelect.disabled = false;
        }

        if (orcClienteSelect && orcVeiculoSelect) {
            orcClienteSelect.addEventListener('change', orcFiltrarVeiculosPorCliente);
        }
        /* ---- LIMPAR ---- */
        window.limparFormOrc = function() {
            if (!confirm('Deseja realmente limpar todos os dados?')) return;
            document.getElementById('formCriarOrcamento').reset();
            orcItens = [];
            orcSelTemp = {};
            orcCatFiltro = null;
            document.getElementById('orc-mec-preview').style.display = 'none';
            document.getElementById('orc-mec-comissao-wrap').style.display = 'none';
            document.getElementById('orc-comissao-base-wrap').style.display = 'none';
            document.getElementById('orc-valor_mao_obra').value = '0';
            document.getElementById('orc-h-comissao-pct').value = '0';
            orcSetBaseComissao('servicos');

            const tsCliente = orcGetTS(orcClienteSelect);
            if (tsCliente) tsCliente.clear(true);

            const tsMecanico = orcGetTS(document.getElementById('orc-mecanico_id'));
            if (tsMecanico) tsMecanico.clear(true);

            orcRenderTabela();
            orcFiltrarVeiculosPorCliente();
            orcRecalcular();
        };

        /* ---- FECHAR AO CLICAR FORA ---- */
        document.getElementById('modalCriarOrcamento').addEventListener('click', function(e) {
            if (e.target === this) history.back();
        });
        document.getElementById('orc-modal-catalogo').addEventListener('click', function(e) {
            if (e.target === this) orcFecharCatalogo();
        });

        /* Init */
        orcFiltrarVeiculosPorCliente();
        orcRecalcular();

        const orcMecanicoSelect = document.getElementById('orc-mecanico_id');
        if (orcMecanicoSelect) {
            orcMecanicoSelect.addEventListener('change', atualizarMecanicoOrc);
        }

        const orcComissaoPct = document.getElementById('orc-mec-comissao-pct');
        if (orcComissaoPct) {
            orcComissaoPct.addEventListener('input', recalcularComissaoOrc);
        }
    })();
</script>