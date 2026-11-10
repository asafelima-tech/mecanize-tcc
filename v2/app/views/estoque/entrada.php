<?php
/** Espera: $d, $pecas, $fornecedores, $erro */
$titulo = 'Entrada de mercadoria';
$linhas = array_values($d['itens'] ?? []) ?: [[]];
$formas = ['BOLETO' => 'Boleto', 'PIX' => 'PIX', 'TRANSFERENCIA' => 'Transferência', 'DINHEIRO' => 'Dinheiro', 'CARTAO_CREDITO' => 'Cartão de crédito', 'CARTAO_DEBITO' => 'Cartão de débito', 'OUTRO' => 'Outro'];
$opcoes = function ($sel) use ($pecas) {
    $h = '<option value="">Escolha a peça</option>';
    foreach ($pecas as $p) {
        $h .= '<option value="' . (int) $p['id'] . '" data-custo="' . e(number_format((float) $p['preco_custo'], 2, ',', '.')) . '"' . ((int) $sel === (int) $p['id'] ? ' selected' : '') . '>'
            . e($p['nome']) . ($p['codigo_interno'] ? ' · ' . e($p['codigo_interno']) : '') . ' (saldo ' . e(rtrim(rtrim((string) $p['estoque_atual'], '0'), '.')) . ')</option>';
    }
    return $h;
};
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Entrada de mercadoria</h1>
    <p class="page-header__subtitle"><a href="./estoque">Estoque</a> · peças que chegaram do fornecedor</p>
  </div>
</div>

<form class="card form-card" method="post" action="./estoque-entrada" id="form-entrada" style="max-width:960px">
  <?= csrf_field() ?>
  <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

  <div class="form-section">
    <div class="form-row-3">
      <div class="form-field span-2">
        <label for="fornecedor_id">Fornecedor</label>
        <select id="fornecedor_id" name="fornecedor_id"><option value="">—</option>
          <?php foreach ($fornecedores as $fo): ?><option value="<?= (int) $fo['id'] ?>" <?= (int) ($d['fornecedor_id'] ?? 0) === (int) $fo['id'] ? 'selected' : '' ?>><?= e($fo['nome']) ?></option><?php endforeach; ?>
        </select>
        <span class="form-dica"><a href="./fornecedor-form">Cadastrar fornecedor</a></span>
      </div>
      <div class="form-field"><label for="documento">Nº da nota</label><input id="documento" name="documento" value="<?= e($d['documento'] ?? '') ?>" maxlength="60"></div>
    </div>
  </div>

  <div class="form-section">
    <table class="table" id="linhas">
      <thead><tr><th style="width:55%">Peça</th><th>Quantidade</th><th>Custo unitário (R$)</th><th class="num">Subtotal</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($linhas as $n => $l): ?>
          <tr>
            <td><div class="form-field"><select name="itens[<?= $n ?>][produto_id]" class="ent-produto"><?= $opcoes($l['produto_id'] ?? 0) ?></select></div></td>
            <td><div class="form-field"><input name="itens[<?= $n ?>][quantidade]" class="ent-qtd" value="<?= e($l['quantidade'] ?? '1') ?>" inputmode="decimal"></div></td>
            <td><div class="form-field"><input name="itens[<?= $n ?>][custo_unitario]" class="ent-custo" value="<?= e($l['custo_unitario'] ?? '') ?>" inputmode="decimal"></div></td>
            <td class="num ent-sub">—</td>
            <td><button type="button" class="btn btn--ghost btn--sm ent-remover" aria-label="Remover linha"><i class="bi bi-x-lg"></i></button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td colspan="3"><button type="button" class="btn btn--ghost btn--sm" id="ent-mais"><i class="bi bi-plus-lg"></i> Outra peça</button></td><td class="num"><strong id="ent-total">R$ 0,00</strong></td><td></td></tr></tfoot>
    </table>
    <p class="form-dica">A peça não está na lista? <a href="./produto-form">Cadastre no catálogo</a> antes.</p>
  </div>

  <div class="form-section">
    <label class="check"><input type="checkbox" name="atualizar_custo" value="1" <?= !empty($d['atualizar_custo']) ? 'checked' : '' ?>> Atualizar o custo das peças com o valor desta nota</label>
    <label class="check" style="margin-top:8px"><input type="checkbox" name="gerar_conta" value="1" id="gerar_conta" <?= !empty($d['gerar_conta']) ? 'checked' : '' ?>> Lançar a conta a pagar ao fornecedor</label>
    <div class="form-row-3" id="campos-conta" style="margin-top:12px">
      <div class="form-field"><label for="vencimento">Vencimento</label><input type="date" id="vencimento" name="vencimento" value="<?= e($d['vencimento'] ?? '') ?>"></div>
      <div class="form-field"><label for="forma">Forma prevista</label><select id="forma" name="forma"><?php foreach ($formas as $k => $r): ?><option value="<?= $k ?>" <?= ($d['forma'] ?? 'BOLETO') === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></div>
    </div>
  </div>

  <div class="form-field"><label for="observacao">Observação</label><input id="observacao" name="observacao" value="<?= e($d['observacao'] ?? '') ?>" maxlength="255"></div>

  <div class="form-acoes">
    <a class="btn btn--ghost" href="./estoque">Cancelar</a>
    <button class="btn btn--primary">Registrar entrada</button>
  </div>
</form>

<script>
  (function () {
    var corpo = document.querySelector('#linhas tbody'), modelo = corpo.rows[0].cloneNode(true), seq = corpo.rows.length;
    var num = function (v) { v = String(v || '').trim(); if (v.indexOf(',') >= 0) v = v.replace(/\./g, '').replace(',', '.'); return parseFloat(v) || 0; };
    var brl = function (n) { return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); };
    function total() {
      var t = 0;
      corpo.querySelectorAll('tr').forEach(function (tr) {
        var s = num(tr.querySelector('.ent-qtd').value) * num(tr.querySelector('.ent-custo').value);
        tr.querySelector('.ent-sub').textContent = s ? brl(s) : '—'; t += s;
      });
      document.getElementById('ent-total').textContent = brl(t);
    }
    corpo.addEventListener('input', total);
    corpo.addEventListener('change', function (e) {
      if (e.target.classList.contains('ent-produto')) {
        var o = e.target.options[e.target.selectedIndex], c = e.target.closest('tr').querySelector('.ent-custo');
        if (o && o.dataset.custo && !c.value) c.value = o.dataset.custo;
      }
      total();
    });
    corpo.addEventListener('click', function (e) {
      var b = e.target.closest('.ent-remover');
      if (b && corpo.rows.length > 1) { b.closest('tr').remove(); total(); }
    });
    document.getElementById('ent-mais').addEventListener('click', function () {
      var tr = modelo.cloneNode(true);
      tr.querySelectorAll('[name]').forEach(function (el) { el.name = el.name.replace(/itens\[\d+\]/, 'itens[' + seq + ']'); el.value = el.classList.contains('ent-qtd') ? '1' : ''; });
      seq++; corpo.appendChild(tr); total();
    });
    var gc = document.getElementById('gerar_conta'), cc = document.getElementById('campos-conta');
    var sync = function () { cc.style.display = gc.checked ? '' : 'none'; };
    gc.addEventListener('change', sync); sync(); total();
  })();
</script>
