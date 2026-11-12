<?php
/**
 * Campos da regra de remuneração — usado por saveUsuario.php e
 * editUsuario.php dentro da seção "Regra de Remuneração" (só visível
 * quando o tipo do usuário é TÉCNICO).
 *
 * Espera (opcional): $regra — array de TecnicoRemuneracao::buscarRegraAtiva(),
 * pra pré-preencher na edição. Ausente/null = campos em branco (cadastro novo).
 */
$regra = $regra ?? null;
$tipoAtual = $regra['tipo'] ?? '';
?>
<div class="form-field">
    <label>Tipo de remuneração <span style="color:var(--color-danger)">*</span></label>
    <select name="remuneracao_tipo" id="campo-remuneracao-tipo" onchange="alternarCamposRegra()">
        <option value="">Selecione...</option>
        <?php foreach (TecnicoRemuneracao::TIPOS as $tipo): ?>
            <option value="<?= $tipo ?>" <?= $tipoAtual === $tipo ? 'selected' : '' ?>>
                <?= htmlspecialchars(TecnicoRemuneracao::rotuloTipo($tipo)) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="form-row-2" style="margin-top:14px;">
    <div class="form-field campo-regra" data-regra-tipos="PERCENTUAL_TOTAL,PERCENTUAL_SERVICOS,PERCENTUAL_PECAS,SALARIO_FIXO_MAIS_COMISSAO" style="display:none;">
        <label>Percentual (%) <span style="color:var(--color-danger)">*</span></label>
        <input type="number" name="remuneracao_percentual" step="0.01" min="0.01" max="100"
            value="<?= htmlspecialchars((string) ($regra['percentual'] ?? '')) ?>">
    </div>

    <div class="form-field campo-regra" data-regra-tipos="VALOR_FIXO_SERVICO,SALARIO_FIXO,SALARIO_FIXO_MAIS_COMISSAO" style="display:none;">
        <label id="rotulo-remuneracao-valor-fixo">Valor fixo (R$) <span style="color:var(--color-danger)">*</span></label>
        <input type="number" name="remuneracao_valor_fixo" step="0.01" min="0.01"
            value="<?= htmlspecialchars((string) ($regra['valor_fixo'] ?? '')) ?>">
    </div>

    <div class="form-field campo-regra" data-regra-tipos="SALARIO_FIXO_MAIS_COMISSAO" style="display:none;">
        <label>Comissão incide sobre <span style="color:var(--color-danger)">*</span></label>
        <select name="remuneracao_base_extra">
            <?php foreach (TecnicoRemuneracao::BASE_EXTRA_LABEL as $valor => $rotulo): ?>
                <option value="<?= $valor ?>" <?= ($regra['comissao_base_extra'] ?? '') === $valor ? 'selected' : '' ?>>
                    <?= htmlspecialchars(ucfirst($rotulo)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="campo-regra" data-regra-tipos="PERCENTUAL_TIPO_SERVICO" style="display:none;margin-top:14px;">
    <label class="fs-12" style="font-weight:600;display:block;margin-bottom:8px;">
        Percentual por categoria de serviço
        <span style="font-weight:400;color:var(--color-text-muted);">
            (a categoria precisa ser digitada igual à usada nos itens da OS)
        </span>
    </label>
    <div id="lista-categorias-regra">
        <?php foreach (($regra['categorias'] ?? [['categoria' => '', 'percentual' => '']]) as $c): ?>
            <div class="linha-categoria-regra" style="display:flex;gap:10px;margin-bottom:8px;">
                <input type="text" name="remuneracao_categoria[]" placeholder="Ex.: Elétrica"
                    value="<?= htmlspecialchars($c['categoria'] ?? '') ?>" style="flex:1 1 60%;height:40px;padding:0 12px;border:1.5px solid var(--color-border);border-radius:var(--radius-md);">
                <input type="number" name="remuneracao_categoria_pct[]" placeholder="%" step="0.01" min="0.01" max="100"
                    value="<?= htmlspecialchars((string) ($c['percentual'] ?? '')) ?>" style="flex:0 0 90px;height:40px;padding:0 12px;border:1.5px solid var(--color-border);border-radius:var(--radius-md);">
                <button type="button" class="btn btn--ghost btn--sm" onclick="this.closest('.linha-categoria-regra').remove()">✕</button>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn--ghost btn--sm" onclick="adicionarLinhaCategoriaRegra()">+ Adicionar categoria</button>
</div>

<div class="form-field" style="margin-top:14px;">
    <label>Observações</label>
    <textarea name="remuneracao_observacoes" rows="2" placeholder="Regras específicas, condições combinadas..."><?= htmlspecialchars($regra['observacoes'] ?? '') ?></textarea>
</div>

<template id="modelo-linha-categoria-regra">
    <div class="linha-categoria-regra" style="display:flex;gap:10px;margin-bottom:8px;">
        <input type="text" name="remuneracao_categoria[]" placeholder="Ex.: Elétrica" style="flex:1 1 60%;height:40px;padding:0 12px;border:1.5px solid var(--color-border);border-radius:var(--radius-md);">
        <input type="number" name="remuneracao_categoria_pct[]" placeholder="%" step="0.01" min="0.01" max="100" style="flex:0 0 90px;height:40px;padding:0 12px;border:1.5px solid var(--color-border);border-radius:var(--radius-md);">
        <button type="button" class="btn btn--ghost btn--sm" onclick="this.closest('.linha-categoria-regra').remove()">✕</button>
    </div>
</template>

<script>
    // Compartilhado por saveUsuario.php e editUsuario.php (cada um define
    // #campo-tipo com o próprio onchange="alternarSecaoRemuneracao()").
    function alternarSecaoRemuneracao() {
        var tipo = document.getElementById('campo-tipo')?.value;
        var secao = document.getElementById('secao-remuneracao');
        if (!secao) return;
        secao.style.display = (tipo === 'TECNICO') ? 'block' : 'none';
        if (tipo === 'TECNICO') alternarCamposRegra();
    }

    function alternarCamposRegra() {
        var tipo = document.getElementById('campo-remuneracao-tipo')?.value || '';
        document.querySelectorAll('.campo-regra').forEach(function (el) {
            var tipos = (el.dataset.regraTipos || '').split(',');
            el.style.display = tipos.includes(tipo) ? '' : 'none';
        });

        var rotuloFixo = document.getElementById('rotulo-remuneracao-valor-fixo');
        if (rotuloFixo) {
            rotuloFixo.innerHTML = (tipo === 'SALARIO_FIXO' || tipo === 'SALARIO_FIXO_MAIS_COMISSAO')
                ? 'Valor do salário fixo (R$) <span style="color:var(--color-danger)">*</span>'
                : 'Valor fixo por serviço (R$) <span style="color:var(--color-danger)">*</span>';
        }
    }

    function adicionarLinhaCategoriaRegra() {
        var modelo = document.getElementById('modelo-linha-categoria-regra');
        document.getElementById('lista-categorias-regra').appendChild(modelo.content.cloneNode(true));
    }

    // Estado inicial (cadastro novo com tipo já vindo selecionado como
    // TÉCNICO por erro de validação anterior, ou edição de um técnico).
    alternarSecaoRemuneracao();
</script>
