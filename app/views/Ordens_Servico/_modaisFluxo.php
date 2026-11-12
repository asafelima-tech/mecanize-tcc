<?php
/**
 * Diálogos das etapas do ciclo de vida da OS — usados pela lista e pelo detalhe.
 *
 *   Entrega ........ ./entregarOrdemServico            (assinatura + receber agora)
 *   Motivo ......... ./reprovarConferenciaOrdemServico (devolver à execução)
 *                    ./cancelarOrdemServico            (cancelar a OS)
 *   Devolver peça .. ./devolverPecaOrdemServico        (volta ao estoque, com justificativa)
 *
 * Quem inclui define, antes do require:
 *   $fluxoVoltar  'lista' | 'view'   para onde a ação devolve o usuário
 *   $fluxoOs      ['id' => int, 'numero' => string]   (opcional; na lista, vem do clique)
 *
 * As regras (quem pode, de qual etapa, motivo obrigatório) são do servidor
 * (OrdemServicoFluxo); estes diálogos só coletam os dados.
 */
require_once __DIR__ . '/../../models/MovimentacaoCaixa.php';

$fluxoVoltar = $fluxoVoltar ?? 'lista';
$fluxoOs     = $fluxoOs ?? ['id' => 0, 'numero' => ''];
?>

<!-- ══ ENTREGA AO CLIENTE ══════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-entrega" style="display:none;">
    <div class="modal" style="max-width:460px;">
        <div class="modal__header">
            <div>
                <div class="modal__title">Entregar ao cliente</div>
                <div class="modal__subtitle" id="entrega-ref"></div>
            </div>
            <button type="button" class="modal__close" onclick="fecharModalFluxo('modal-entrega')" aria-label="Fechar">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
            </button>
        </div>
        <form action="./entregarOrdemServico" method="POST"
              onsubmit="document.getElementById('entrega-assinatura').value = (assinaturaEntrega ? assinaturaEntrega.dataUrl() : '');">
            <div class="modal__body" style="padding:20px 24px;">
                <input type="hidden" name="id_ordem_servico" id="entrega-id">
                <input type="hidden" name="voltar" value="<?= htmlspecialchars($fluxoVoltar) ?>">
                <input type="hidden" name="assinatura_cliente" id="entrega-assinatura">

                <p style="font-size:12px;color:var(--color-text-muted);margin:0 0 12px;">
                    Entregar encerra a OS. A entrega <strong>não</strong> dá baixa na cobrança: o recebimento é
                    registrado à parte — aqui, se o cliente estiver pagando agora, ou depois, na tela de pagamento.
                </p>

                <label style="display:flex;gap:8px;align-items:center;font-size:13px;font-weight:600;">
                    <input type="checkbox" name="receber_agora" value="1" id="entrega-receber"
                           onchange="document.getElementById('entrega-forma-wrap').style.display = this.checked ? 'block' : 'none'">
                    O cliente está pagando agora
                </label>

                <div class="form-field" id="entrega-forma-wrap" style="display:none;margin-top:10px;">
                    <label>Forma de pagamento</label>
                    <select name="forma_pagamento" data-no-ts>
                        <?php foreach (MovimentacaoCaixa::FORMAS as $forma): ?>
                            <option value="<?= $forma ?>"><?= htmlspecialchars(MovimentacaoCaixa::rotuloForma($forma)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color:var(--color-text-muted);font-size:11px;">
                        Registra o recebimento da próxima parcela e lança no caixa aberto.
                    </small>
                </div>

                <div style="margin-top:14px;">
                    <label style="font-size:11px;color:var(--color-text-muted);font-weight:600;">
                        Assinatura do cliente <span style="font-weight:400;">(retirada do veículo, opcional)</span>
                    </label>
                    <canvas id="canvas-assinatura-entrega" width="380" height="130"
                        style="width:100%;height:130px;margin-top:6px;background:#fff;border:1.5px dashed var(--color-border-strong);
                               border-radius:var(--radius-md);cursor:crosshair;touch-action:none;"></canvas>
                    <button type="button" class="btn btn--ghost btn--sm" style="margin-top:8px;"
                            onclick="assinaturaEntrega && assinaturaEntrega.limpar()">Limpar assinatura</button>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--ghost btn--sm" onclick="fecharModalFluxo('modal-entrega')">Cancelar</button>
                <button type="submit" class="btn btn--primary btn--sm">Confirmar entrega</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ MOTIVO (devolver à execução / cancelar) ═════════════════════════ -->
<div class="modal-overlay" id="modal-motivo" style="display:none;">
    <div class="modal" style="max-width:460px;">
        <div class="modal__header">
            <div>
                <div class="modal__title" id="motivo-titulo"></div>
                <div class="modal__subtitle" id="motivo-ref"></div>
            </div>
            <button type="button" class="modal__close" onclick="fecharModalFluxo('modal-motivo')" aria-label="Fechar">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
            </button>
        </div>
        <form id="form-motivo" method="POST" action="">
            <div class="modal__body" style="padding:20px 24px;">
                <input type="hidden" name="id_ordem_servico" id="motivo-id">
                <input type="hidden" name="voltar" value="<?= htmlspecialchars($fluxoVoltar) ?>">
                <p id="motivo-explica" style="font-size:12px;color:var(--color-text-muted);margin:0 0 10px;"></p>
                <div class="form-field">
                    <label>Motivo <span style="color:var(--color-danger)">*</span></label>
                    <textarea name="motivo" id="motivo-texto" rows="3" maxlength="500" required
                              placeholder="Explique em poucas palavras — fica registrado no histórico da OS."></textarea>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--ghost btn--sm" onclick="fecharModalFluxo('modal-motivo')">Voltar</button>
                <button type="submit" class="btn btn--primary btn--sm" id="motivo-confirmar"></button>
            </div>
        </form>
    </div>
</div>

<!-- ══ DEVOLVER PEÇA AO ESTOQUE ════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-devolver-peca" style="display:none;">
    <div class="modal" style="max-width:460px;">
        <div class="modal__header">
            <div>
                <div class="modal__title">Devolver peça ao estoque</div>
                <div class="modal__subtitle" id="devolver-peca-ref"></div>
            </div>
            <button type="button" class="modal__close" onclick="fecharModalFluxo('modal-devolver-peca')" aria-label="Fechar">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
            </button>
        </div>
        <form action="./devolverPecaOrdemServico" method="POST">
            <div class="modal__body" style="padding:20px 24px;">
                <input type="hidden" name="id_item" id="devolver-peca-item">
                <input type="hidden" name="id_ordem_servico" id="devolver-peca-os">
                <input type="hidden" name="voltar" value="<?= htmlspecialchars($fluxoVoltar) ?>">
                <p style="font-size:12px;color:var(--color-text-muted);margin:0 0 10px;">
                    A peça volta ao estoque e deixa de ser cobrada nesta OS. O consumo original continua no histórico de estoque.
                </p>
                <div class="form-field">
                    <label>Motivo da devolução <span style="color:var(--color-danger)">*</span></label>
                    <textarea name="justificativa" rows="3" maxlength="255" required
                              placeholder="Ex.: peça não foi usada; lançada por engano."></textarea>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--ghost btn--sm" onclick="fecharModalFluxo('modal-devolver-peca')">Voltar</button>
                <button type="submit" class="btn btn--primary btn--sm">Devolver ao estoque</button>
            </div>
        </form>
    </div>
</div>

<script src="./assets/js/assinatura.js?v=<?= time() ?>"></script>
<script>
    var FLUXO_CTX = <?= json_para_script($fluxoOs) ?>;
    var assinaturaEntrega = initAssinatura('canvas-assinatura-entrega');

    function fecharModalFluxo(id) {
        document.getElementById(id).style.display = 'none';
    }

    function abrirModalEntrega(id, numero) {
        id = id || FLUXO_CTX.id;
        numero = numero || FLUXO_CTX.numero;
        document.getElementById('entrega-id').value = id;
        document.getElementById('entrega-ref').textContent = 'OS #' + numero;
        document.getElementById('modal-entrega').style.display = 'flex';
        // Só depois do modal ficar visível (o canvas começa escondido).
        if (assinaturaEntrega) assinaturaEntrega.redimensionar();
    }

    var MOTIVO_ACOES = {
        devolver: {
            action: './reprovarConferenciaOrdemServico',
            titulo: 'Devolver à execução',
            explica: 'A OS volta para "Em execução" e o técnico é avisado pelo motivo. Comissões e cobrança ainda não recebidas são refeitas na próxima conferência.',
            botao: 'Devolver à execução'
        },
        cancelar: {
            action: './cancelarOrdemServico',
            titulo: 'Cancelar ordem de serviço',
            explica: 'As peças já consumidas voltam ao estoque, as comissões não pagas são anuladas e a cobrança em aberto é removida. Não dá para cancelar uma OS que já recebeu pagamento.',
            botao: 'Cancelar a OS'
        }
    };

    function abrirModalMotivo(acao, id, numero) {
        var cfg = MOTIVO_ACOES[acao];
        id = id || FLUXO_CTX.id;
        numero = numero || FLUXO_CTX.numero;
        document.getElementById('form-motivo').action = cfg.action;
        document.getElementById('motivo-titulo').textContent = cfg.titulo;
        document.getElementById('motivo-ref').textContent = 'OS #' + numero;
        document.getElementById('motivo-explica').textContent = cfg.explica;
        document.getElementById('motivo-confirmar').textContent = cfg.botao;
        document.getElementById('motivo-id').value = id;
        document.getElementById('motivo-texto').value = '';
        document.getElementById('modal-motivo').style.display = 'flex';
        document.getElementById('motivo-texto').focus();
    }

    function abrirModalDevolverPeca(botao) {
        document.getElementById('devolver-peca-item').value = botao.dataset.item;
        document.getElementById('devolver-peca-os').value = FLUXO_CTX.id;
        document.getElementById('devolver-peca-ref').textContent = botao.dataset.nome;
        document.getElementById('modal-devolver-peca').style.display = 'flex';
    }
</script>
