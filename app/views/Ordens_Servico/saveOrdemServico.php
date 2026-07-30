<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$clientes        = $clientes        ?? [];
$veiculos        = $veiculos        ?? [];
$mecanicos       = $mecanicos       ?? [];
$produtos        = $produtos        ?? [];
$servicos        = $servicos        ?? [];
$proximoNumeroOS = $proximoNumeroOS ?? 'OS-0042';
$error           = $error           ?? '';

// Pré-preenchimento vindo da agenda ("Gerar OS" num agendamento). Só chega
// aqui pelo GET montado em AgendamentoController::gerarOrdemServico();
// aberto direto, o formulário continua em branco como sempre.
$preCliente     = $preCliente     ?? '';
$preVeiculo     = $preVeiculo     ?? '';
$preDefeito     = $preDefeito     ?? '';
$preAgendamento = $preAgendamento ?? '';
?>

<!-- ============================================================
     MODAL OVERLAY — Nova OS
     ============================================================ -->
<div class="modal-overlay" id="modalCriarOrdemServico">
    <div class="modal modal--xl">

        <!-- HEADER -->
        <div class="modal__header">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="
                    width:40px;height:40px;
                    background:var(--color-primary-soft);
                    color:var(--color-primary);
                    border-radius:var(--radius-md);
                    display:inline-flex;align-items:center;justify-content:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                               M9 5a2 2 0 002 2h2a2 2 0 002-2
                               M9 5a2 2 0 012-2h2a2 2 0 012 2
                               m-6 9l2 2 4-4" />
                    </svg>
                </div>
                <div>
                    <div class="modal__title">Nova Ordem de Serviço</div>
                    <div class="modal__subtitle">Preencha os dados abaixo para abrir uma nova OS.</div>
                </div>
            </div>
            <a href="./listOrdemServico" class="modal__close" title="Fechar">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </a>
        </div>

        <!-- FORM envolve BODY + FOOTER -->
        <form action="./saveOrdemServico" method="POST" id="formCriarOS" onsubmit="return prepararSubmit()"
            style="display:flex; flex-direction:column; flex:1; min-height:0; overflow:hidden;">

            <?php if ($preAgendamento !== ''): ?>
                <!-- Marca a OS como originada de um agendamento, pra agenda saber
                     que aquele horário virou serviço (ver AgendamentoController). -->
                <input type="hidden" name="agendamento_id" value="<?= htmlspecialchars($preAgendamento) ?>">
            <?php endif; ?>

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
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px 18px;">

                        <div class="form-field">
                            <label>Número da OS</label>
                            <input type="text" name="numero_os"
                                value="<?= htmlspecialchars($proximoNumeroOS) ?>"
                                readonly
                                style="background:var(--color-bg-alt);color:var(--color-text-muted);cursor:not-allowed;font-weight:600;">
                        </div>

                        <div class="form-field">
                            <label>Status <span style="color:var(--color-danger)">*</span></label>
                            <input type="hidden" name="status" value="ABERTA">
                            <input type="text" value="Aberta" readonly
                                style="background:var(--color-bg-alt);color:var(--color-text-muted);cursor:not-allowed;font-weight:600;">
                        </div>

                        <div class="form-field">
                            <label>Data de Abertura <span style="color:var(--color-danger)">*</span></label>
                            <input type="datetime-local" name="data_abertura"
                                value="<?= date('Y-m-d\TH:i') ?>" readonly>
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
                            <select name="cliente_id" id="cliente_id" required>
                                <option value="">Selecione o cliente</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?= htmlspecialchars($cliente['id_cliente']) ?>"
                                        <?= (string) $preCliente === (string) $cliente['id_cliente'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cliente['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label>Veículo <span style="color:var(--color-danger)">*</span></label>
                            <select name="veiculo_id" id="veiculo_id" required disabled>
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
                                <input type="number" name="km_veiculo" min="0" placeholder="Ex.: 85000"
                                    style="width:100%;padding-right:42px;">
                                <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:11px;font-weight:600;color:var(--color-text-muted);">km</span>
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

                        <!-- SELECT DO MECÂNICO -->
                        <div class="form-field">
                            <label>Mecânico</label>
                            <select name="mecanico_id" id="mecanico_id" onchange="atualizarMecanico()">
                                <option value="">Selecione o mecânico</option>
                                <?php foreach ($mecanicos as $m): ?>
                                    <option value="<?= htmlspecialchars($m['id']) ?>"
                                        data-nome="<?= htmlspecialchars($m['nome']) ?>">
                                        <?= htmlspecialchars($m['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- PORCENTAGEM DE COMISSÃO -->
                        <div class="form-field" id="mec-comissao-wrap" style="display:none;">
                            <label>
                                Comissão do Técnico
                                <span id="mec-comissao-sublabel" style="font-size:11px;font-weight:400;color:var(--color-text-muted);">(% sobre serviços)</span>
                            </label>
                            <div style="position:relative;">
                                <input
                                    type="number"
                                    id="mec-comissao-pct"
                                    min="0"
                                    max="100"
                                    step="0.1"
                                    placeholder="Ex.: 30"
                                    style="width:100%;box-sizing:border-box;padding-right:36px;"
                                    oninput="recalcularComissao()">
                                <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:12px;font-weight:700;color:var(--color-text-muted);">%</span>
                            </div>
                        </div>

                        <!-- BASE DE CÁLCULO DA COMISSÃO: Serviços / Peças / Ambos -->
                        <div class="form-field" id="comissao-base-wrap" style="display:none;grid-column:1/-1;">
                            <label>
                                Base de Cálculo
                                <span style="font-size:11px;font-weight:400;color:var(--color-text-muted);">(sobre o quê incide o percentual acima)</span>
                            </label>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <button type="button" id="base-servicos" onclick="setBaseComissao('servicos')"
                                    style="flex:1;min-width:140px;display:flex;align-items:center;justify-content:center;gap:6px;padding:9px 12px;border-radius:var(--radius-md);cursor:pointer;font-size:12px;font-weight:600;font-family:inherit;border:1.5px solid var(--color-primary);background:var(--color-primary);color:#fff;transition:all .12s;">
                                    <i class="bi bi-wrench" style="font-size:12px;"></i>
                                    Somente Serviços
                                </button>
                                <button type="button" id="base-pecas" onclick="setBaseComissao('pecas')"
                                    style="flex:1;min-width:140px;display:flex;align-items:center;justify-content:center;gap:6px;padding:9px 12px;border-radius:var(--radius-md);cursor:pointer;font-size:12px;font-weight:600;font-family:inherit;border:1.5px solid var(--color-border);background:#fff;color:var(--color-text-secondary);transition:all .12s;">
                                    <i class="bi bi-box-seam" style="font-size:12px;"></i>
                                    Somente Peças
                                </button>
                                <button type="button" id="base-ambos" onclick="setBaseComissao('ambos')"
                                    style="flex:1;min-width:140px;display:flex;align-items:center;justify-content:center;gap:6px;padding:9px 12px;border-radius:var(--radius-md);cursor:pointer;font-size:12px;font-weight:600;font-family:inherit;border:1.5px solid var(--color-border);background:#fff;color:var(--color-text-secondary);transition:all .12s;">
                                    <i class="bi bi-boxes" style="font-size:12px;"></i>
                                    Peças + Serviços
                                </button>
                            </div>
                        </div>

                        <!-- PREVIEW DO MECÂNICO + COMISSÃO -->
                        <div id="mec-preview" style="display:none;align-items:center;grid-column:1/-1;">
                            <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);width:100%;box-sizing:border-box;">

                                <div id="mec-avatar" style="width:36px;height:36px;border-radius:50%;background:var(--color-primary-soft);color:var(--color-primary);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;">--</div>

                                <div style="flex:1;">
                                    <div id="mec-nome" style="font-size:13px;font-weight:600;"></div>
                                    <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px;">
                                        Comissão:
                                        <strong id="mec-pct-label" style="color:var(--color-primary);">—</strong>
                                        &nbsp;→&nbsp;
                                        <strong id="mec-valor-calculado" style="color:var(--color-primary);">R$ 0,00</strong>
                                        <span id="mec-preview-sublabel" style="margin-left:4px;color:var(--color-text-muted);font-size:10px;">(sobre total de serviços)</span>
                                    </div>
                                </div>

                                <span class="badge badge--success">Incluído na OS</span>
                            </div>
                        </div>

                        <!-- Inputs hidden enviados ao backend -->
                        <input type="hidden" name="valor_mao_obra" id="valor_mao_obra" value="0">
                        <input type="hidden" name="comissao_pct" id="h-comissao-pct" value="0">
                        <input type="hidden" name="comissao_base" id="h-comissao-base" value="servicos">

                    </div>
                </div>

                <!-- ④ PRODUTOS & SERVIÇOS -->
                <div class="form-section">
                    <div class="form-section__title">
                        <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;">④</span>
                        Produtos &amp; Serviços
                    </div>

                    <div id="os-items-empty" style="text-align:center;padding:28px 20px;color:var(--color-text-muted);border:1px dashed var(--color-border);border-radius:var(--radius-md);margin-bottom:12px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto 8px;opacity:.4;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7H4a2 2 0 00-2 2v6a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2zM16 3H8l-1 4h10l-1-4z" />
                        </svg>
                        <p style="font-size:12px;">Nenhum item adicionado. Use os botões abaixo.</p>
                    </div>

                    <div id="os-items-table-wrap" style="display:none;margin-bottom:12px;">
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
                            <tbody id="os-items-tbody"></tbody>
                        </table>
                    </div>

                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <button type="button" class="btn btn--ghost btn--sm" onclick="abrirCatalogo('produto')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7H4a2 2 0 00-2 2v6a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2zM16 3H8l-1 4h10l-1-4z" />
                            </svg>
                            Adicionar Produto
                        </button>
                        <button type="button" class="btn btn--ghost btn--sm" onclick="abrirCatalogo('servico')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z" />
                            </svg>
                            Adicionar Serviço
                        </button>
                    </div>

                    <div id="hidden-items-container"></div>
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
                            <textarea name="defeito_relatado" rows="4" placeholder="Descreva o problema relatado pelo cliente..."><?= htmlspecialchars($preDefeito) ?></textarea>
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
                            <div id="resumo-pecas" style="font-size:18px;font-weight:700;color:var(--color-text);">R$ 0,00</div>
                        </div>

                        <div style="background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:12px 14px;">
                            <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:4px;">Total em Serviços</div>
                            <div id="resumo-servicos" style="font-size:18px;font-weight:700;color:var(--color-text);">R$ 0,00</div>
                        </div>

                        <div style="background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:12px 14px;">
                            <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:4px;">Comissão Técnico</div>
                            <div id="resumo-mao-obra" style="font-size:18px;font-weight:700;color:var(--color-text);">R$ 0,00</div>
                        </div>

                        <div style="background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:12px 14px;">
                            <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:4px;">Lucro Líquido</div>
                            <div id="resumo-lucro" style="font-size:18px;font-weight:700;color:#16A34A;">R$ 0,00</div>
                        </div>

                        <div style="background:var(--color-primary-soft);border:1px solid var(--color-primary);border-radius:var(--radius-md);padding:12px 14px;">
                            <div style="font-size:11px;color:var(--color-primary);font-weight:600;margin-bottom:4px;">Total da OS</div>
                            <div id="resumo-total" style="font-size:20px;font-weight:700;color:var(--color-primary);">R$ 0,00</div>
                        </div>

                    </div>
                    <input type="hidden" name="valor_total_pecas" id="h-total-pecas" value="0">
                    <input type="hidden" name="valor_total_servicos" id="h-total-servicos" value="0">
                    <input type="hidden" name="valor_total_os" id="h-total-os" value="0">
                </div>

                <!-- ⑦ CONCLUSÃO -->
                <div class="form-section" style="margin-bottom:0;">
                    <div class="form-section__title">
                        <span class="ico-wrap ico" style="width:22px;height:22px;background:var(--color-primary-soft);color:var(--color-primary);border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;">⑦</span>
                        Conclusão
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px 18px;">
                        <div class="form-field">
                            <label>Data de Conclusão</label>
                            <input type="datetime-local" name="data_conclusao">
                        </div>
                    </div>
                </div>

            </div><!-- fecha modal__body -->

            <!-- FOOTER -->
            <div class="modal__footer">
                <div class="modal__footer-left">
                    <span style="margin-right:16px;">Criado em: <?= date('d/m/Y') ?></span>
                    <span style="margin-right:16px;">Atualizado em: <?= date('d/m/Y') ?></span>
                    <span>Usuário: <?= htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário') ?> (<?= htmlspecialchars($_SESSION['user_tipo'] ?? 'Administrador') ?>)</span>
                </div>
                <button type="button" class="btn btn--ghost btn--sm" onclick="window.print()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 6 2 18 2 18 9" />
                        <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2" />
                        <rect x="6" y="14" width="12" height="8" />
                    </svg>
                    Imprimir OS
                </button>
                <button type="button" class="btn btn--danger btn--sm" onclick="limparFormOS()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" />
                        <path d="M10 11v6M14 11v6" />
                        <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" />
                    </svg>
                    Limpar
                </button>
                <a href="javascript:history.back()" class="btn btn--ghost btn--sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                    Cancelar
                </a>
                <button type="submit" class="btn btn--primary btn--sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                    </svg>
                    Gravar OS
                </button>
            </div>

        </form><!-- fecha form -->

    </div>
</div>


<!-- ============================================================
     MODAL CATÁLOGO (Produtos / Serviços)
     ============================================================ -->
<div id="modal-catalogo" style="
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
                <div id="cat-header-icon" style="width:36px;height:36px;background:var(--color-primary-soft);color:var(--color-primary);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;"></div>
                <div>
                    <div id="cat-header-title" style="font-size:14px;font-weight:700;"></div>
                    <div style="font-size:11px;color:var(--color-text-muted);">Selecione itens do catálogo ou crie um serviço personalizado</div>
                </div>
            </div>
            <button type="button" class="modal__close" onclick="fecharCatalogo()">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Busca -->
        <div id="cat-search-wrap" style="padding:10px 16px;border-bottom:1px solid var(--color-border);flex-shrink:0;">
            <div class="search-wrap">
                <input type="text" id="cat-search-input"
                    placeholder="Buscar por nome ou categoria..."
                    oninput="filtrarCatalogo()">
            </div>
        </div>

        <!-- Tabs -->
        <div id="cat-tabs" style="display:flex;border-bottom:1px solid var(--color-border);flex-shrink:0;overflow-x:auto;padding:0 12px;"></div>

        <!-- Lista do catálogo -->
        <div id="cat-lista" style="overflow-y:auto;flex:1;padding:8px 12px;"></div>

        <!-- Painel Personalizado -->
        <div id="cat-custom-panel" style="display:none;flex:1;overflow-y:auto;padding:20px 20px 8px;">

            <div style="background:var(--color-primary-soft);border:1px solid var(--color-primary);border-radius:var(--radius-md);padding:12px 14px;margin-bottom:18px;display:flex;align-items:flex-start;gap:10px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="var(--color-primary)" stroke-width="2" style="flex-shrink:0;margin-top:1px;">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                <p style="font-size:12px;color:var(--color-primary);line-height:1.5;margin:0;">
                    Use esta opção para registrar um serviço específico não cadastrado no catálogo.
                    Informe o nome e o valor — fixo em R$ ou como percentual sobre o total de peças da OS.
                </p>
            </div>

            <div class="form-field" style="margin-bottom:16px;">
                <label style="font-size:12px;font-weight:600;color:var(--color-text-secondary);display:block;margin-bottom:6px;">
                    Nome do Serviço <span style="color:var(--color-danger)">*</span>
                </label>
                <input type="text" id="custom-nome"
                    placeholder="Ex.: Regulagem de injeção eletrônica"
                    style="width:100%;box-sizing:border-box;">
            </div>

            <div class="form-field" style="margin-bottom:16px;">
                <label style="font-size:12px;font-weight:600;color:var(--color-text-secondary);display:block;margin-bottom:6px;">
                    Tipo de Custo <span style="color:var(--color-danger)">*</span>
                </label>
                <div style="display:flex;gap:10px;">
                    <label id="custom-tipo-fixo-label" style="flex:1;display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:var(--radius-md);cursor:pointer;border:1.5px solid var(--color-primary);background:var(--color-primary-soft);">
                        <input type="radio" name="custom_tipo_custo" id="custom-tipo-fixo" value="fixo" checked onchange="toggleTipoCusto()" style="accent-color:var(--color-primary);width:15px;height:15px;">
                        <span>
                            <span style="font-size:12px;font-weight:700;display:block;">Valor Fixo (R$)</span>
                            <span style="font-size:11px;color:var(--color-text-muted);">Valor exato em reais</span>
                        </span>
                    </label>
                    <label id="custom-tipo-pct-label" style="flex:1;display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:var(--radius-md);cursor:pointer;border:1.5px solid var(--color-border);background:#fff;">
                        <input type="radio" name="custom_tipo_custo" id="custom-tipo-pct" value="porcentagem" onchange="toggleTipoCusto()" style="accent-color:var(--color-primary);width:15px;height:15px;">
                        <span>
                            <span style="font-size:12px;font-weight:700;display:block;">Porcentagem (%)</span>
                            <span style="font-size:11px;color:var(--color-text-muted);">% sobre total de peças</span>
                        </span>
                    </label>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
                <div class="form-field" id="custom-campo-fixo">
                    <label style="font-size:12px;font-weight:600;color:var(--color-text-secondary);display:block;margin-bottom:6px;">
                        Valor (R$) <span style="color:var(--color-danger)">*</span>
                    </label>
                    <div style="position:relative;">
                        <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:12px;font-weight:700;color:var(--color-text-muted);">R$</span>
                        <input type="number" id="custom-valor" min="0" step="0.01" placeholder="0,00"
                            style="padding-left:32px;width:100%;box-sizing:border-box;"
                            oninput="previewCustom()">
                    </div>
                </div>
                <div class="form-field" id="custom-campo-pct" style="display:none;">
                    <label style="font-size:12px;font-weight:600;color:var(--color-text-secondary);display:block;margin-bottom:6px;">
                        Percentual (%) <span style="color:var(--color-danger)">*</span>
                    </label>
                    <div style="position:relative;">
                        <input type="number" id="custom-pct" min="0" max="100" step="0.1" placeholder="0,0"
                            style="padding-right:32px;width:100%;box-sizing:border-box;"
                            oninput="previewCustom()">
                        <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:12px;font-weight:700;color:var(--color-text-muted);">%</span>
                    </div>
                </div>
                <div id="custom-preview-wrap" style="display:none;align-self:end;">
                    <div style="padding:10px 14px;background:var(--color-bg-alt);border:1px solid var(--color-border);border-radius:var(--radius-md);">
                        <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:2px;">Valor calculado sobre peças</div>
                        <div id="custom-preview-valor" style="font-size:16px;font-weight:700;color:var(--color-primary);">R$ 0,00</div>
                    </div>
                </div>
            </div>

            <div class="form-field" style="margin-bottom:4px;">
                <label style="font-size:12px;font-weight:600;color:var(--color-text-secondary);display:block;margin-bottom:6px;">
                    Categoria <span style="font-size:11px;font-weight:400;color:var(--color-text-muted);">(opcional)</span>
                </label>
                <input type="text" id="custom-categoria"
                    placeholder="Ex.: Elétrica, Suspensão, Motor..."
                    style="width:100%;box-sizing:border-box;">
            </div>

        </div>

        <!-- Rodapé -->
        <div style="padding:12px 16px;border-top:1px solid var(--color-border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
            <span style="font-size:12px;color:var(--color-text-muted);">
                <span id="cat-sel-count" style="font-weight:700;color:var(--color-primary);">0</span>
                selecionado(s)
            </span>
            <div style="display:flex;gap:8px;">
                <button type="button" class="btn btn--ghost btn--sm" onclick="fecharCatalogo()">Cancelar</button>
                <button type="button" class="btn btn--primary btn--sm" id="cat-confirmar-btn" onclick="confirmarCatalogo()">
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
     JAVASCRIPT
     ============================================================ -->
<script>
    /* ----------------------------------------------------------------
   DADOS DE CATÁLOGO (PHP → JS)
---------------------------------------------------------------- */
    const CATALOGO = {
        produto: <?= json_encode(array_map(fn($p) => array_merge($p, ['id' => (string)($p['id'] ?? '')]), $produtos), JSON_UNESCAPED_UNICODE) ?>,
        servico: <?= json_encode(array_map(fn($s) => array_merge($s, ['id' => (string)($s['id'] ?? '')]), $servicos), JSON_UNESCAPED_UNICODE) ?>
    };

    const MECANICOS_DATA = <?= json_encode(array_map(fn($m) => [
                                'id'   => $m['id']   ?? null,
                                'nome' => $m['nome'] ?? '',
                            ], $mecanicos), JSON_UNESCAPED_UNICODE) ?>;

    /* ----------------------------------------------------------------
       ESTADO DA OS
    ---------------------------------------------------------------- */
    let osItens = [];
    let tipoAtual = 'produto';
    let selTemp = {};
    let catFiltro = null;
    let abaAtual = 'catalogo';
    let comissaoBase = 'servicos';

    /* ----------------------------------------------------------------
       UTILITÁRIOS
    ---------------------------------------------------------------- */
    function fmtBR(v) {
        return 'R$ ' + parseFloat(v || 0).toFixed(2)
            .replace('.', ',')
            .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function iniciaisMecanico(nome) {
        return nome.split(' ').slice(0, 2).map(p => p[0]).join('').toUpperCase();
    }

    function totalPecasOS() {
        return osItens.reduce((acc, it) => it.tipo === 'produto' ? acc + it.preco * it.qty : acc, 0);
    }

    function totalServicosOS() {
        return osItens.reduce((acc, it) => it.tipo === 'servico' ? acc + it.preco * it.qty : acc, 0);
    }

    /* ----------------------------------------------------------------
       BASE DE CÁLCULO DA COMISSÃO (serviços / peças / ambos)
    ---------------------------------------------------------------- */
    function baseComissaoValor() {
        if (comissaoBase === 'pecas') return totalPecasOS();
        if (comissaoBase === 'ambos') return totalPecasOS() + totalServicosOS();
        return totalServicosOS();
    }

    function baseComissaoTexto() {
        if (comissaoBase === 'pecas') return 'peças';
        if (comissaoBase === 'ambos') return 'peças e serviços';
        return 'serviços';
    }

    function setBaseComissao(base) {
        comissaoBase = base;
        document.getElementById('h-comissao-base').value = base;

        ['servicos', 'pecas', 'ambos'].forEach(b => {
            const btn = document.getElementById('base-' + b);
            if (!btn) return;
            const ativo = b === base;
            btn.style.background = ativo ? 'var(--color-primary)' : '#fff';
            btn.style.color = ativo ? '#fff' : 'var(--color-text-secondary)';
            btn.style.borderColor = ativo ? 'var(--color-primary)' : 'var(--color-border)';
        });

        const texto = baseComissaoTexto();
        document.getElementById('mec-comissao-sublabel').textContent = '(% sobre ' + texto + ')';
        document.getElementById('mec-preview-sublabel').textContent = '(sobre total de ' + texto + ')';

        recalcularComissao();
    }

    /* ----------------------------------------------------------------
       MECÂNICO — seleção + comissão
    ---------------------------------------------------------------- */
    function atualizarMecanico() {
        const sel = document.getElementById('mecanico_id');
        const opt = sel.options[sel.selectedIndex];
        const prev = document.getElementById('mec-preview');
        const comissaoWrap = document.getElementById('mec-comissao-wrap');
        const baseWrap = document.getElementById('comissao-base-wrap');

        if (!opt || !opt.value) {
            prev.style.display = 'none';
            comissaoWrap.style.display = 'none';
            baseWrap.style.display = 'none';
            document.getElementById('mec-comissao-pct').value = '';
            document.getElementById('valor_mao_obra').value = '0';
            document.getElementById('h-comissao-pct').value = '0';
            recalcular();
            return;
        }

        const nome = opt.dataset.nome;
        document.getElementById('mec-avatar').textContent = iniciaisMecanico(nome);
        document.getElementById('mec-nome').textContent = nome;

        // Mostra o campo de %, a base de cálculo e o preview
        comissaoWrap.style.display = 'block';
        baseWrap.style.display = 'block';
        prev.style.display = 'flex';

        recalcularComissao();
    }

    function recalcularComissao() {
        const pct = parseFloat(document.getElementById('mec-comissao-pct').value) || 0;
        const base = baseComissaoValor();
        const valorComiss = base * pct / 100;

        // Atualiza labels do preview
        document.getElementById('mec-pct-label').textContent = pct > 0 ? pct + '%' : '—';
        document.getElementById('mec-valor-calculado').textContent = fmtBR(valorComiss);

        // Atualiza hiddens
        document.getElementById('valor_mao_obra').value = valorComiss.toFixed(2);
        document.getElementById('h-comissao-pct').value = pct.toFixed(2);

        recalcular();
    }

    /* ----------------------------------------------------------------
       CATÁLOGO — abrir / fechar
    ---------------------------------------------------------------- */
    function abrirCatalogo(tipo) {
        tipoAtual = tipo;
        catFiltro = null;
        selTemp = {};
        abaAtual = 'catalogo';

        osItens.forEach(it => {
            if (it.tipo === tipo && !it.custom) selTemp[String(it.id)] = true;
        });

        const icons = {
            produto: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7H4a2 2 0 00-2 2v6a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2zM16 3H8l-1 4h10l-1-4z"/></svg>`,
            servico: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>`
        };
        document.getElementById('cat-header-icon').innerHTML = icons[tipo];
        document.getElementById('cat-header-title').textContent =
            tipo === 'produto' ? 'Adicionar Produto' : 'Adicionar Serviço';

        document.getElementById('cat-search-input').value = '';
        renderTabsCatalogo();
        ativarAbaCatalogo();

        document.getElementById('modal-catalogo').style.display = 'flex';
    }

    function fecharCatalogo() {
        document.getElementById('modal-catalogo').style.display = 'none';
    }

    /* ----------------------------------------------------------------
       TABS
    ---------------------------------------------------------------- */
    function getCategorias() {
        return [...new Set(CATALOGO[tipoAtual].map(i => i.categoria))];
    }

    function renderTabsCatalogo() {
        const cats = getCategorias();
        const tabs = document.getElementById('cat-tabs');

        const styleBase = `padding:10px 14px;font-size:12px;font-weight:500;border:none;background:none;cursor:pointer;border-bottom:2px solid transparent;color:var(--color-text-secondary);white-space:nowrap;font-family:inherit;`;
        const styleAtivo = `border-bottom-color:var(--color-primary);color:var(--color-primary);font-weight:600;`;

        let html = `<button type="button" onclick="setCatFiltro(null,this)" style="${styleBase}${styleAtivo}">Todos</button>`;
        html += cats.map(c =>
            `<button type="button" onclick="setCatFiltro('${c}',this)" style="${styleBase}">${c}</button>`
        ).join('');

        if (tipoAtual === 'servico') {
            html += `<button type="button" id="tab-personalizado" onclick="ativarAbaPersonalizado(this)" style="${styleBase}">
            <span style="display:inline-flex;align-items:center;gap:5px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Personalizado
            </span>
        </button>`;
        }

        tabs.innerHTML = html;
    }

    function setCatFiltro(cat, btn) {
        catFiltro = cat;
        abaAtual = 'catalogo';
        document.querySelectorAll('#cat-tabs button').forEach(b => {
            b.style.borderBottomColor = 'transparent';
            b.style.color = 'var(--color-text-secondary)';
            b.style.fontWeight = '500';
        });
        btn.style.borderBottomColor = 'var(--color-primary)';
        btn.style.color = 'var(--color-primary)';
        btn.style.fontWeight = '600';
        ativarAbaCatalogo();
    }

    function ativarAbaCatalogo() {
        abaAtual = 'catalogo';
        document.getElementById('cat-lista').style.display = 'block';
        document.getElementById('cat-custom-panel').style.display = 'none';
        document.getElementById('cat-search-wrap').style.display = 'block';
        document.getElementById('cat-sel-count').parentElement.style.display = '';
        document.getElementById('cat-confirmar-btn').textContent = 'Confirmar';
        renderListaCatalogo();
        atualizarContadorSel();
    }

    function ativarAbaPersonalizado(btn) {
        abaAtual = 'personalizado';
        document.querySelectorAll('#cat-tabs button').forEach(b => {
            b.style.borderBottomColor = 'transparent';
            b.style.color = 'var(--color-text-secondary)';
            b.style.fontWeight = '500';
        });
        btn.style.borderBottomColor = 'var(--color-primary)';
        btn.style.color = 'var(--color-primary)';
        btn.style.fontWeight = '600';

        document.getElementById('cat-lista').style.display = 'none';
        document.getElementById('cat-custom-panel').style.display = 'block';
        document.getElementById('cat-search-wrap').style.display = 'none';
        document.getElementById('cat-sel-count').parentElement.style.display = 'none';
        document.getElementById('cat-confirmar-btn').textContent = 'Adicionar Serviço';

        document.getElementById('custom-nome').value = '';
        document.getElementById('custom-valor').value = '';
        document.getElementById('custom-pct').value = '';
        document.getElementById('custom-categoria').value = '';
        document.getElementById('custom-preview-wrap').style.display = 'none';
        document.getElementById('custom-tipo-fixo').checked = true;
        toggleTipoCusto();
    }

    /* ----------------------------------------------------------------
       PAINEL PERSONALIZADO
    ---------------------------------------------------------------- */
    function toggleTipoCusto() {
        const isFixo = document.getElementById('custom-tipo-fixo').checked;
        const lblFixo = document.getElementById('custom-tipo-fixo-label');
        const lblPct = document.getElementById('custom-tipo-pct-label');
        lblFixo.style.border = isFixo ? '1.5px solid var(--color-primary)' : '1.5px solid var(--color-border)';
        lblFixo.style.background = isFixo ? 'var(--color-primary-soft)' : '#fff';
        lblPct.style.border = !isFixo ? '1.5px solid var(--color-primary)' : '1.5px solid var(--color-border)';
        lblPct.style.background = !isFixo ? 'var(--color-primary-soft)' : '#fff';
        document.getElementById('custom-campo-fixo').style.display = isFixo ? 'block' : 'none';
        document.getElementById('custom-campo-pct').style.display = !isFixo ? 'block' : 'none';
        if (isFixo) document.getElementById('custom-preview-wrap').style.display = 'none';
        previewCustom();
    }

    function previewCustom() {
        const isPct = document.getElementById('custom-tipo-pct').checked;
        if (!isPct) {
            document.getElementById('custom-preview-wrap').style.display = 'none';
            return;
        }
        const pct = parseFloat(document.getElementById('custom-pct').value) || 0;
        const basePeca = totalPecasOS();
        const calculado = basePeca * pct / 100;
        const wrap = document.getElementById('custom-preview-wrap');
        wrap.style.display = 'block';
        document.getElementById('custom-preview-valor').textContent = fmtBR(calculado);
        if (basePeca === 0) {
            wrap.querySelector('div').style.borderColor = '#FEF3C7';
            wrap.querySelector('div').style.background = '#FFFBEB';
            wrap.querySelector('[style*="font-size:11"]').textContent = '⚠ Nenhuma peça adicionada ainda';
        } else {
            wrap.querySelector('div').style.borderColor = 'var(--color-border)';
            wrap.querySelector('div').style.background = 'var(--color-bg-alt)';
            wrap.querySelector('[style*="font-size:11"]').textContent = 'Valor calculado sobre peças';
        }
    }

    /* ----------------------------------------------------------------
       CATÁLOGO — filtrar / renderizar
    ---------------------------------------------------------------- */
    function filtrarCatalogo() {
        renderListaCatalogo();
    }

    function renderListaCatalogo() {
        const q = (document.getElementById('cat-search-input').value || '').toLowerCase();
        const lista = CATALOGO[tipoAtual].filter(i => {
            const matchQ = i.nome.toLowerCase().includes(q) || i.categoria.toLowerCase().includes(q);
            const matchC = !catFiltro || i.categoria === catFiltro;
            return matchQ && matchC;
        });
        const el = document.getElementById('cat-lista');
        if (!lista.length) {
            el.innerHTML = `<div style="text-align:center;padding:32px;color:var(--color-text-muted);font-size:13px;">Nenhum item encontrado.</div>`;
            atualizarContadorSel();
            return;
        }
        el.innerHTML = lista.map(i => {
            const sel = !!selTemp[i.id];
            return `
        <div id="cat-item-${i.id}" onclick="toggleSelCatalogo('${i.id}')"
           style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin-bottom:4px;
       user-select:none;
                    border-radius:var(--radius-md);cursor:pointer;
                    border:1px solid ${sel ? 'var(--color-primary)' : 'var(--color-border)'};
                    background:${sel ? 'var(--color-primary-soft)' : '#fff'};transition:all .12s;">
            <div style="flex:1;">
                <div style="font-size:13px;font-weight:600;color:var(--color-text);">${i.nome}</div>
                <div style="font-size:11px;color:var(--color-text-muted);">${i.categoria}</div>
            </div>
            <span style="font-size:13px;font-weight:700;color:var(--color-primary);margin-right:8px;">${fmtBR(i.preco)}</span>
        <div class="cat-check" style="width:20px;height:20px;border-radius:50%;flex-shrink:0;
            border:1px solid ${sel ? 'var(--color-primary)' : 'var(--color-border-strong)'};
            background:${sel ? 'var(--color-primary)' : '#fff'};
            display:flex;align-items:center;justify-content:center;">
                ${sel ? `<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>` : ''}
            </div>
        </div>`;
        }).join('');
        atualizarContadorSel();
    }

    function toggleSelCatalogo(id) {
        id = String(id);
        if (selTemp[id]) delete selTemp[id];
        else selTemp[id] = true;
        const i = CATALOGO[tipoAtual].find(x => String(x.id) === id);
        if (!i) return;
        const sel = !!selTemp[id];
        const el = document.getElementById('cat-item-' + id);
        if (el) {
            el.style.border = `1px solid ${sel ? 'var(--color-primary)' : 'var(--color-border)'}`;
            el.style.background = sel ? 'var(--color-primary-soft)' : '#fff';
            const circle = el.querySelector('.cat-check');
            circle.style.border = `1px solid ${sel ? 'var(--color-primary)' : 'var(--color-border-strong)'}`;
            circle.style.background = sel ? 'var(--color-primary)' : '#fff';
            circle.innerHTML = sel ?
                `<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>` : '';
        }
        atualizarContadorSel();
    }

    function atualizarContadorSel() {
        document.getElementById('cat-sel-count').textContent = Object.keys(selTemp).length;
    }

    /* ----------------------------------------------------------------
       CONFIRMAR CATÁLOGO
    ---------------------------------------------------------------- */
    function confirmarCatalogo() {

        // Aba Personalizado
        if (abaAtual === 'personalizado') {
            const nome = document.getElementById('custom-nome').value.trim();
            const isPct = document.getElementById('custom-tipo-pct').checked;
            const categoria = document.getElementById('custom-categoria').value.trim() || 'Personalizado';

            if (!nome) {
                document.getElementById('custom-nome').focus();
                document.getElementById('custom-nome').style.borderColor = 'var(--color-danger)';
                return;
            }
            document.getElementById('custom-nome').style.borderColor = '';

            let preco = 0;
            let pct = null;

            if (isPct) {
                pct = parseFloat(document.getElementById('custom-pct').value) || 0;
                preco = totalPecasOS() * pct / 100;
            } else {
                preco = parseFloat(document.getElementById('custom-valor').value) || 0;
            }

            if (preco <= 0 && (!isPct || pct <= 0)) {
                const campo = isPct ? document.getElementById('custom-pct') : document.getElementById('custom-valor');
                campo.focus();
                campo.style.borderColor = 'var(--color-danger)';
                return;
            }

            osItens.push({
                id: 'custom_' + Date.now(),
                nome,
                tipo: 'servico',
                categoria,
                preco,
                qty: 1,
                custom: true,
                pct: isPct ? pct : null,
            });

            fecharCatalogo();
            renderTabelaItens();
            recalcular();
            return;
        }

        // Aba Catálogo
        osItens = osItens.filter(o => o.tipo !== tipoAtual || o.custom || selTemp[o.id]);
        Object.keys(selTemp).forEach(id => {
            if (!osItens.find(o => o.id === id)) {
                const item = CATALOGO[tipoAtual].find(i => String(i.id) === String(id));
                if (item) osItens.push({
                    ...item,
                    tipo: tipoAtual,
                    qty: 1,
                    preco: parseFloat(item.preco) || 0, // garante float
                    custom: false
                });
            }
        });

        fecharCatalogo();
        renderTabelaItens();
        recalcular();
    }

    function prepararSubmit() {
        // Valida veículo manualmente (estava disabled, required não funciona nele)
        const veiculoSel = document.getElementById('veiculo_id');
        veiculoSel.disabled = false;

        if (!veiculoSel.value) {
            veiculoSel.style.borderColor = 'var(--color-danger)';
            veiculoSel.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            veiculoSel.disabled = true; // mantém desabilitado se falhar
            return false; // bloqueia submit
        }
        veiculoSel.style.borderColor = '';

        const container = document.getElementById('hidden-items-container');
        container.innerHTML = '';

        osItens.forEach((it, i) => {
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
    }

    /* ----------------------------------------------------------------
       TABELA DE ITENS
    ---------------------------------------------------------------- */
    function renderTabelaItens() {
        const empty = document.getElementById('os-items-empty');
        const wrap = document.getElementById('os-items-table-wrap');
        const tbody = document.getElementById('os-items-tbody');

        if (!osItens.length) {
            empty.style.display = 'block';
            wrap.style.display = 'none';
            return;
        }

        empty.style.display = 'none';
        wrap.style.display = 'block';

        tbody.innerHTML = osItens.map((it, idx) => `
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
                    <button type="button" onclick="alterarQty(${idx},-1)" style="width:22px;height:22px;border-radius:4px;border:1px solid var(--color-border);background:var(--color-bg-alt);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;color:var(--color-text-secondary);">−</button>
                    <span style="min-width:24px;text-align:center;font-size:13px;font-weight:700;">${it.qty}</span>
                    <button type="button" onclick="alterarQty(${idx},1)" style="width:22px;height:22px;border-radius:4px;border:1px solid var(--color-border);background:var(--color-bg-alt);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;color:var(--color-text-secondary);">+</button>
                </div>`}
            </td>
            <td style="padding:9px 12px;text-align:right;font-size:12px;">${fmtBR(it.preco)}</td>
            <td style="padding:9px 12px;text-align:right;font-size:13px;font-weight:700;">${fmtBR(it.preco * it.qty)}</td>
            <td style="padding:9px 6px;text-align:center;">
                <button type="button" onclick="removerItem(${idx})" class="row-action-btn danger" title="Remover">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                </button>
            </td>
        </tr>`).join('');
    }

    function alterarQty(idx, delta) {
        if (osItens[idx].custom) return;
        osItens[idx].qty = Math.max(1, osItens[idx].qty + delta);
        renderTabelaItens();
        recalcular();
    }

    function removerItem(idx) {
        osItens.splice(idx, 1);
        renderTabelaItens();
        recalcular();
        recalcularCustomPct();
    }

    /* ----------------------------------------------------------------
       RECALCULAR % custom ao mudar peças
    ---------------------------------------------------------------- */
    function recalcularCustomPct() {
        const basePeca = totalPecasOS();
        let mudou = false;
        osItens.forEach(it => {
            if (it.custom && it.pct != null) {
                const novo = basePeca * it.pct / 100;
                if (novo !== it.preco) {
                    it.preco = novo;
                    mudou = true;
                }
            }
        });
        if (mudou) {
            renderTabelaItens();
            recalcular();
        }
    }

    /* ----------------------------------------------------------------
       RESUMO FINANCEIRO
    ---------------------------------------------------------------- */
    function recalcular() {
        let totalPecas = 0;
        let totalServicos = 0;

        osItens.forEach(it => {
            const sub = it.preco * it.qty;
            if (it.tipo === 'produto') totalPecas += sub;
            else totalServicos += sub;
        });

        // Recalcula comissão do mecânico sobre a base selecionada (serviços / peças / ambos)
        const pctComissao = parseFloat(document.getElementById('mec-comissao-pct').value) || 0;
        const baseComissao = baseComissaoValor();
        const comissao = baseComissao * pctComissao / 100;

        document.getElementById('valor_mao_obra').value = comissao.toFixed(2);
        document.getElementById('h-comissao-pct').value = pctComissao.toFixed(2);

        // Atualiza label do preview do mecânico se estiver visível
        document.getElementById('mec-pct-label').textContent = pctComissao > 0 ? pctComissao + '%' : '—';
        document.getElementById('mec-valor-calculado').textContent = fmtBR(comissao);

        const totalOS = totalPecas + totalServicos;
        const lucro = totalOS - comissao;

        document.getElementById('resumo-pecas').textContent = fmtBR(totalPecas);
        document.getElementById('resumo-servicos').textContent = fmtBR(totalServicos);
        document.getElementById('resumo-mao-obra').textContent = fmtBR(comissao);
        document.getElementById('resumo-lucro').textContent = fmtBR(lucro);
        document.getElementById('resumo-total').textContent = fmtBR(totalOS);

        document.getElementById('h-total-pecas').value = totalPecas.toFixed(2);
        document.getElementById('h-total-servicos').value = totalServicos.toFixed(2);
        document.getElementById('h-total-os').value = totalOS.toFixed(2);
    }

    /* ----------------------------------------------------------------
       PREPARAR SUBMIT
    ---------------------------------------------------------------- */
    function prepararSubmit() {
        const container = document.getElementById('hidden-items-container');
        container.innerHTML = '';

        osItens.forEach((it, i) => {
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
    }

    /* ----------------------------------------------------------------
       LIMPAR
    ---------------------------------------------------------------- */
    function limparFormOS() {
        if (!confirm('Deseja realmente limpar todos os dados?')) return;
        document.getElementById('formCriarOS').reset();
        osItens = [];
        selTemp = {};
        catFiltro = null;
        document.getElementById('mec-preview').style.display = 'none';
        document.getElementById('mec-comissao-wrap').style.display = 'none';
        document.getElementById('comissao-base-wrap').style.display = 'none';
        document.getElementById('valor_mao_obra').value = '0';
        document.getElementById('h-comissao-pct').value = '0';
        setBaseComissao('servicos');

        const tsCliente = getTS(clienteSelect);
        if (tsCliente) tsCliente.clear(true);

        const tsMecanico = getTS(document.getElementById('mecanico_id'));
        if (tsMecanico) tsMecanico.clear(true);

        renderTabelaItens();
        filtrarVeiculosPorCliente();
        recalcular();
    }

    /* ----------------------------------------------------------------
       FECHAR AO CLICAR FORA
    ---------------------------------------------------------------- */
    document.getElementById('modalCriarOrdemServico')
        .addEventListener('click', function(e) {
            if (e.target === this) window.location.href = './listOrdemServico';
        });

    document.getElementById('modal-catalogo')
        .addEventListener('click', function(e) {
            if (e.target === this) fecharCatalogo();
        });

    /* Init */
    const clienteSelect = document.getElementById('cliente_id');
    const veiculoSelect = document.getElementById('veiculo_id');

    const todasOptionsVeiculos = Array.from(veiculoSelect.options).map(option => {
        return option.cloneNode(true);
    });

    // Instância do Tom Select (se o campo tiver sido inicializado por essa lib em algum outro script)
    function getTS(select) {
        return select ? select.tomselect : null;
    }

    function resetarSelectVeiculos(mensagem = 'Selecione primeiro o cliente') {
        const ts = getTS(veiculoSelect);

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

        veiculoSelect.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = mensagem;
        veiculoSelect.appendChild(option);
        veiculoSelect.disabled = true;
    }

    function filtrarVeiculosPorCliente() {
        const clienteId = clienteSelect.value;
        const ts = getTS(veiculoSelect);

        if (!clienteId) {
            resetarSelectVeiculos('Selecione primeiro o cliente');
            return;
        }

        const veiculosFiltrados = todasOptionsVeiculos.filter(
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
        veiculoSelect.innerHTML = '';

        const optionInicial = document.createElement('option');
        optionInicial.value = '';
        optionInicial.textContent = 'Selecione o veículo';
        veiculoSelect.appendChild(optionInicial);

        if (veiculosFiltrados.length === 0) {
            resetarSelectVeiculos('Nenhum veículo cadastrado para este cliente');
            return;
        }

        veiculosFiltrados.forEach(opt => {
            veiculoSelect.appendChild(opt.cloneNode(true));
        });

        veiculoSelect.disabled = false;
    }

    if (clienteSelect && veiculoSelect) {
        clienteSelect.addEventListener('change', filtrarVeiculosPorCliente);
    }

    /* Init */
    filtrarVeiculosPorCliente();

    // Veículo vindo da agenda: só dá pra selecionar depois que
    // filtrarVeiculosPorCliente() repovoou a lista com os veículos do cliente.
    (function () {
        const preVeiculo = <?= json_encode((string) $preVeiculo) ?>;
        if (!preVeiculo || !veiculoSelect) return;

        const ts = getTS(veiculoSelect);
        if (ts) {
            ts.setValue(preVeiculo, true);
        } else {
            veiculoSelect.value = preVeiculo;
        }
    })();

    recalcular();
</script>