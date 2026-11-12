<?php
/**
 * Entrada de mercadoria, no formato de ficha das outras telas: identificação da nota, as peças recebidas
 * (o centro da operação: busca por nome ou código, quantidade e custo editáveis na própria linha) e as
 * informações adicionais; ao lado, o resumo com o total, a conta a pagar opcional e as ações.
 * Os campos enviados e as regras são os mesmos de antes (Estoque::entrada): itens[n][produto_id|quantidade|custo_unitario],
 * fornecedor_id, documento, atualizar_custo, gerar_conta (+ vencimento e forma) e observacao.
 * Espera: $d, $pecas, $fornecedores, $erro
 */
$titulo = 'Entrada de mercadoria';
$formas = ['BOLETO' => 'Boleto', 'PIX' => 'PIX', 'TRANSFERENCIA' => 'Transferência', 'DINHEIRO' => 'Dinheiro', 'CARTAO_CREDITO' => 'Cartão de crédito', 'CARTAO_DEBITO' => 'Cartão de débito', 'OUTRO' => 'Outro'];
$qtd = fn($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
$porId = [];
foreach ($pecas as $p) {
    $porId[(int) $p['id']] = $p;
}
// Linhas já escolhidas (volta de erro, ou ?produto= vindo da ficha da peça).
$linhas = array_values(array_filter($d['itens'] ?? [], fn($l) => !empty($l['produto_id']) && isset($porId[(int) $l['produto_id']])));
$catalogo = array_map(fn($p) => [
    'id' => (int) $p['id'], 'nome' => $p['nome'], 'codigo' => (string) ($p['codigo_interno'] ?? ''),
    'saldo' => $qtd($p['estoque_atual']), 'custo' => number_format((float) $p['preco_custo'], 2, ',', '.'),
], array_values($pecas));
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= $titulo ?></h1>
    <p class="page-header__subtitle"><a href="./estoque">Estoque</a> · peças que chegaram do fornecedor</p>
  </div>
</div>

<form class="form-ficha entrada" method="post" action="./estoque-entrada" id="form-entrada">
  <?= csrf_field() ?>

  <div class="form-ficha__corpo">
    <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

    <div class="card form-ficha__secao grupos">
      <!-- 1. Identificação -->
      <section class="grupo" aria-labelledby="g-ident">
        <header class="grupo__cabeca"><span class="grupo__n">1</span><h2 id="g-ident">Identificação da entrada</h2></header>
        <div class="form-ficha__grade">
          <div class="form-field form-ficha__c8">
            <label for="fornecedor_id">Fornecedor</label>
            <select id="fornecedor_id" name="fornecedor_id">
              <option value="">Sem fornecedor</option>
              <?php foreach ($fornecedores as $fo): ?><option value="<?= (int) $fo['id'] ?>" <?= (int) ($d['fornecedor_id'] ?? 0) === (int) $fo['id'] ? 'selected' : '' ?>><?= e($fo['nome']) ?></option><?php endforeach; ?>
            </select>
            <span class="form-dica"><a href="./fornecedor-form">Cadastrar fornecedor</a></span>
          </div>
          <div class="form-field form-ficha__c4">
            <label for="documento">Nº da nota</label>
            <input id="documento" name="documento" value="<?= e($d['documento'] ?? '') ?>" maxlength="60" class="campo-codigo" placeholder="Ex.: 4587">
          </div>
        </div>
      </section>

      <!-- 2. Peças recebidas (o centro da operação) -->
      <section class="grupo grupo--destaque" aria-labelledby="g-pecas">
        <header class="grupo__cabeca"><span class="grupo__n">2</span><h2 id="g-pecas">Peças recebidas</h2></header>
        <div class="entrada-pecas">
          <div class="entrada-busca" role="combobox" aria-expanded="false" aria-haspopup="listbox" aria-owns="entrada-sugestoes">
            <?= icone('search') ?>
            <input type="search" id="entrada-busca" placeholder="Buscar peça por nome ou código e Enter para incluir" autocomplete="off" aria-label="Buscar peça" aria-controls="entrada-sugestoes">
            <ul class="entrada-sugestoes" id="entrada-sugestoes" role="listbox" hidden></ul>
          </div>

          <div class="entrada-tabela">
            <table class="table" id="linhas">
              <thead><tr><th>Peça</th><th class="num">Quantidade</th><th class="num">Custo unitário</th><th class="num">Subtotal</th><th><span class="sr-only">Remover</span></th></tr></thead>
              <tbody>
                <?php foreach ($linhas as $n => $l): $pc = $porId[(int) $l['produto_id']]; ?>
                  <tr data-produto="<?= (int) $pc['id'] ?>" data-saldo="<?= e($qtd($pc['estoque_atual'])) ?>">
                    <td class="entrada-peca">
                      <input type="hidden" name="itens[<?= $n ?>][produto_id]" value="<?= (int) $pc['id'] ?>">
                      <strong><?= e($pc['nome']) ?></strong>
                      <span class="sub"><?= $pc['codigo_interno'] ? e($pc['codigo_interno']) . ' · ' : '' ?>saldo <?= e($qtd($pc['estoque_atual'])) ?> <span class="entrada-depois"></span></span>
                    </td>
                    <td class="num"><span class="entrada-qtd">
                      <button type="button" class="entrada-passo" data-passo="-1" aria-label="Diminuir">−</button>
                      <input name="itens[<?= $n ?>][quantidade]" class="ent-qtd" value="<?= e($l['quantidade'] ?? '1') ?>" inputmode="decimal" aria-label="Quantidade">
                      <button type="button" class="entrada-passo" data-passo="1" aria-label="Aumentar">+</button>
                    </span></td>
                    <td class="num"><span class="campo-prefixo entrada-custo"><span>R$</span><input name="itens[<?= $n ?>][custo_unitario]" class="ent-custo" value="<?= e($l['custo_unitario'] ?? '') ?>" inputmode="decimal" placeholder="0,00" aria-label="Custo unitário"></span></td>
                    <td class="num ent-sub">—</td>
                    <td class="num"><button type="button" class="btn btn--fantasma btn--icone ent-remover" aria-label="Remover <?= e($pc['nome']) ?>"><?= icone('close') ?></button></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <div class="entrada-vazio" id="entrada-vazio"<?= $linhas ? ' hidden' : '' ?>><?= icone('part', 22, 1.4) ?><span>Busque uma peça acima para incluir na entrada.</span></div>
          </div>
          <p class="entrada-rodape">A peça não está no catálogo? <a href="./produto-form?tipo=PECA">Cadastrar peça</a></p>
        </div>
      </section>

      <!-- 3. Informações adicionais -->
      <section class="grupo" aria-labelledby="g-adicional">
        <header class="grupo__cabeca"><span class="grupo__n">3</span><h2 id="g-adicional">Informações adicionais</h2></header>
        <div class="form-ficha__grade">
          <div class="form-field form-ficha__c12"><label for="observacao">Observação</label><input id="observacao" name="observacao" value="<?= e($d['observacao'] ?? '') ?>" maxlength="255" placeholder="Opcional · aparece no livro do estoque"></div>
          <label class="opcao form-ficha__c12">
            <input type="checkbox" name="atualizar_custo" value="1" <?= !empty($d['atualizar_custo']) ? 'checked' : '' ?>>
            <span class="opcao__texto"><strong>Atualizar o custo das peças com o valor desta nota</strong><small>O custo do catálogo passa a ser o custo unitário informado (só nas linhas com custo).</small></span>
          </label>
        </div>
      </section>
    </div>
  </div>

  <aside class="form-ficha__lado" aria-label="Resumo da entrada">
    <div class="card form-ficha__resumo">
      <div class="form-ficha__perfil">
        <span class="form-ficha__avatar form-ficha__avatar--quadrado"><?= icone('download', 22) ?></span>
        <div class="form-ficha__perfil-texto">
          <strong data-previa-forn>Entrada de mercadoria</strong>
          <span data-previa-nota>Sem nota informada</span>
        </div>
      </div>
      <div class="resumo-preco">
        <span>Total da nota</span>
        <strong id="ent-total">R$ 0,00</strong>
      </div>
      <dl class="form-ficha__dados form-ficha__dados--lista">
        <div><dt><?= icone('part', 14) ?> Peças</dt><dd id="ent-pecas">0</dd></div>
        <div><dt><?= icone('box', 14) ?> Unidades</dt><dd id="ent-unidades">0</dd></div>
        <div><dt><?= icone('calendar', 14) ?> Entrada</dt><dd>hoje, ao registrar</dd></div>
      </dl>

      <div class="entrada-conta">
        <label class="opcao">
          <input type="checkbox" name="gerar_conta" value="1" id="gerar_conta" <?= !empty($d['gerar_conta']) ? 'checked' : '' ?>>
          <span class="opcao__texto"><strong>Lançar a conta a pagar</strong><small>No valor total, para o fornecedor.</small></span>
        </label>
        <div class="entrada-conta__campos" id="campos-conta">
          <div class="form-field"><label for="vencimento">Vencimento</label><input type="date" id="vencimento" name="vencimento" value="<?= e($d['vencimento'] ?? '') ?>"></div>
          <div class="form-field"><label for="forma">Forma prevista</label><select id="forma" name="forma"><?php foreach ($formas as $k => $r): ?><option value="<?= $k ?>" <?= ($d['forma'] ?? 'BOLETO') === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></div>
        </div>
      </div>

      <div class="form-ficha__acoes">
        <button class="btn btn--primary" id="ent-registrar"><?= icone('check') ?> Registrar entrada</button>
        <a class="btn btn--ghost" href="./estoque">Cancelar</a>
      </div>
      <p class="form-ficha__nota">As quantidades entram no estoque ao registrar.</p>
    </div>
  </aside>
</form>

<template id="entrada-linha">
  <tr>
    <td class="entrada-peca">
      <input type="hidden" data-campo="produto_id">
      <strong data-nome></strong>
      <span class="sub"><span data-codigo></span>saldo <span data-saldo></span> <span class="entrada-depois"></span></span>
    </td>
    <td class="num"><span class="entrada-qtd">
      <button type="button" class="entrada-passo" data-passo="-1" aria-label="Diminuir">−</button>
      <input data-campo="quantidade" class="ent-qtd" value="1" inputmode="decimal" aria-label="Quantidade">
      <button type="button" class="entrada-passo" data-passo="1" aria-label="Aumentar">+</button>
    </span></td>
    <td class="num"><span class="campo-prefixo entrada-custo"><span>R$</span><input data-campo="custo_unitario" class="ent-custo" inputmode="decimal" placeholder="0,00" aria-label="Custo unitário"></span></td>
    <td class="num ent-sub">—</td>
    <td class="num"><button type="button" class="btn btn--fantasma btn--icone ent-remover" aria-label="Remover"><?= icone('close') ?></button></td>
  </tr>
</template>

<script>
  (function () {
    var catalogo = <?= json_para_script($catalogo) ?>;
    var f = document.getElementById('form-entrada'), corpo = document.querySelector('#linhas tbody'), modelo = document.getElementById('entrada-linha');
    var busca = document.getElementById('entrada-busca'), lista = document.getElementById('entrada-sugestoes'), vazio = document.getElementById('entrada-vazio');
    var seq = corpo.rows.length, ativo = -1, achados = [];
    var num = function (v) { v = String(v || '').trim(); if (v.indexOf(',') >= 0) v = v.replace(/\./g, '').replace(',', '.'); return parseFloat(v) || 0; };
    var brl = function (n) { return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); };
    var qtdTxt = function (n) { return (Math.round(n * 1000) / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 3 }); };
    var sem = function (s) { return String(s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };

    function total() {
      var t = 0, un = 0, n = 0;
      corpo.querySelectorAll('tr').forEach(function (tr) {
        var q = num(tr.querySelector('.ent-qtd').value), s = q * num(tr.querySelector('.ent-custo').value);
        tr.querySelector('.ent-sub').textContent = s ? brl(s) : '—';
        tr.querySelector('.entrada-depois').textContent = q > 0 ? '→ ' + qtdTxt(num(tr.dataset.saldo) + q) : '';
        t += s; un += q; n++;
      });
      document.getElementById('ent-total').textContent = brl(t);
      document.getElementById('ent-pecas').textContent = n;
      document.getElementById('ent-unidades').textContent = qtdTxt(un);
      vazio.hidden = n > 0;
    }

    function incluir(p) {
      var existente = corpo.querySelector('tr[data-produto="' + p.id + '"]');
      if (existente) {
        var q = existente.querySelector('.ent-qtd'); q.value = qtdTxt(num(q.value) + 1); total(); q.focus(); q.select(); return;
      }
      var tr = modelo.content.firstElementChild.cloneNode(true);
      tr.dataset.produto = p.id; tr.dataset.saldo = p.saldo;
      tr.querySelectorAll('[data-campo]').forEach(function (el) { el.name = 'itens[' + seq + '][' + el.dataset.campo + ']'; });
      tr.querySelector('[data-campo=produto_id]').value = p.id;
      tr.querySelector('[data-nome]').textContent = p.nome;
      tr.querySelector('[data-codigo]').textContent = p.codigo ? p.codigo + ' · ' : '';
      tr.querySelector('[data-saldo]').textContent = p.saldo;
      if (p.custo !== '0,00') tr.querySelector('.ent-custo').value = p.custo;
      tr.querySelector('.ent-remover').setAttribute('aria-label', 'Remover ' + p.nome);
      seq++; corpo.appendChild(tr); total();
      var q2 = tr.querySelector('.ent-qtd'); q2.focus(); q2.select();
    }

    function sugerir() {
      var termo = sem(busca.value.trim());
      achados = termo ? catalogo.filter(function (p) { return sem(p.nome + ' ' + p.codigo).indexOf(termo) >= 0; }).slice(0, 8) : [];
      ativo = achados.length ? 0 : -1;
      lista.innerHTML = '';
      achados.forEach(function (p, i) {
        var li = document.createElement('li');
        li.setAttribute('role', 'option'); li.dataset.i = i;
        li.innerHTML = '<span><strong></strong><small></small></span><b></b>';
        li.querySelector('strong').textContent = p.nome;
        li.querySelector('small').textContent = (p.codigo ? p.codigo + ' · ' : '') + 'saldo ' + p.saldo;
        li.querySelector('b').textContent = p.custo !== '0,00' ? 'R$ ' + p.custo : '';
        lista.appendChild(li);
      });
      if (termo && !achados.length) lista.innerHTML = '<li class="entrada-sugestoes__nada">Nenhuma peça com esse nome ou código.</li>';
      lista.hidden = !termo;
      busca.parentNode.setAttribute('aria-expanded', termo ? 'true' : 'false');
      marcar();
    }
    function marcar() { lista.querySelectorAll('[role=option]').forEach(function (li, i) { li.classList.toggle('is-ativo', i === ativo); li.setAttribute('aria-selected', i === ativo ? 'true' : 'false'); }); }
    function escolher(i) { if (achados[i]) { incluir(achados[i]); busca.value = ''; sugerir(); } }

    busca.addEventListener('input', sugerir);
    busca.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' && achados.length) { ativo = (ativo + 1) % achados.length; marcar(); e.preventDefault(); }
      else if (e.key === 'ArrowUp' && achados.length) { ativo = (ativo - 1 + achados.length) % achados.length; marcar(); e.preventDefault(); }
      else if (e.key === 'Enter') { e.preventDefault(); escolher(ativo); }
      else if (e.key === 'Escape') { busca.value = ''; sugerir(); }
    });
    lista.addEventListener('mousedown', function (e) { var li = e.target.closest('[role=option]'); if (li) { e.preventDefault(); escolher(+li.dataset.i); busca.focus(); } });
    busca.addEventListener('blur', function () { setTimeout(function () { lista.hidden = true; }, 120); });
    busca.addEventListener('focus', function () { if (busca.value.trim()) sugerir(); });

    corpo.addEventListener('input', total);
    corpo.addEventListener('click', function (e) {
      var passo = e.target.closest('.entrada-passo');
      if (passo) { var q = passo.parentNode.querySelector('.ent-qtd'); q.value = qtdTxt(Math.max(0, num(q.value) + (+passo.dataset.passo))); total(); return; }
      var b = e.target.closest('.ent-remover');
      if (b) { b.closest('tr').remove(); total(); busca.focus(); }
    });
    // Enter dentro de uma linha não envia o formulário: volta para a busca.
    corpo.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); busca.focus(); } });

    // Resumo ao lado: fornecedor e nota.
    var forn = document.getElementById('fornecedor_id'), doc = document.getElementById('documento');
    var resumo = function () {
      document.querySelector('[data-previa-forn]').textContent = forn.value ? forn.options[forn.selectedIndex].text : 'Entrada de mercadoria';
      document.querySelector('[data-previa-nota]').textContent = doc.value.trim() ? 'Nota ' + doc.value.trim() : 'Sem nota informada';
    };
    forn.addEventListener('change', resumo); doc.addEventListener('input', resumo); resumo();

    var gc = document.getElementById('gerar_conta'), cc = document.getElementById('campos-conta');
    var sync = function () { cc.hidden = !gc.checked; };
    gc.addEventListener('change', sync); sync(); total();
  })();
</script>
