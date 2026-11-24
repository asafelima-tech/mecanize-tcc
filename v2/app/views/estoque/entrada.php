<?php
/**
 * Entrada de mercadoria, no padrão de janela (modal) das telas de criar e editar.
 * Seções: a nota (fornecedor e número), as peças recebidas (busca por nome ou código, quantidade e custo
 * editáveis na própria linha, total no fim), a conta a pagar opcional e a observação.
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
$secoes = [['nota', 'receipt', 'Nota'], ['pecas', 'part', 'Peças'], ['conta', 'wallet', 'Conta a pagar'], ['observacao', 'file', 'Observação']];
?>
<form class="mf" method="post" action="./estoque-entrada" id="form-entrada" aria-labelledby="mf-titulo">
  <?= csrf_field() ?>

  <header class="mf__topo">
    <h2 class="mf__titulo" id="mf-titulo"><?= $titulo ?><span>Peças que chegaram do fornecedor</span></h2>
    <a class="mf__fechar" href="./estoque" data-mf-fechar aria-label="Fechar" title="Fechar"><?= icone('close', 18, 1.75) ?></a>
  </header>

  <div class="mf__corpo">
    <nav class="mf__nav" aria-label="Seções da entrada">
      <?php foreach ($secoes as $k => [$ancora, $ic, $rotulo]): ?>
        <a href="#mf-<?= $ancora ?>"<?= $k === 0 ? ' class="is-ativa" aria-current="true"' : '' ?>><?= icone($ic, 18) ?><span><?= $rotulo ?></span></a>
      <?php endforeach; ?>
    </nav>

    <div class="mf__conteudo">
      <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

      <section class="mf__secao" id="mf-nota" aria-labelledby="mf-h-nota">
        <h3 id="mf-h-nota">Nota</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="fornecedor_id">Fornecedor</label><small>Opcional. <a href="./fornecedor-form">Cadastrar fornecedor</a></small></div>
          <div class="mf-linha__campo">
            <select id="fornecedor_id" name="fornecedor_id">
              <option value="">Sem fornecedor</option>
              <?php foreach ($fornecedores as $fo): ?><option value="<?= (int) $fo['id'] ?>" <?= (int) ($d['fornecedor_id'] ?? 0) === (int) $fo['id'] ? 'selected' : '' ?>><?= e($fo['nome']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="documento">Número da nota</label><small>Opcional.</small></div>
          <div class="mf-linha__campo"><input id="documento" name="documento" value="<?= e($d['documento'] ?? '') ?>" maxlength="60" placeholder="Ex.: 4587" autocomplete="off"></div>
        </div>
      </section>

      <section class="mf__secao" id="mf-pecas" aria-labelledby="mf-h-pecas">
        <h3 id="mf-h-pecas">Peças recebidas</h3>
        <div class="mf-linha mf-linha--bloco">
          <div class="mf-linha__rotulo"><label for="entrada-busca">Peças</label><small>Busque pelo nome ou código e tecle Enter para incluir. A peça não está no catálogo? <a href="./produto-form?tipo=PECA">Cadastrar peça</a></small></div>
          <div class="mf-linha__campo mf-est-lista">
            <div class="mf-est-cab" aria-hidden="true"<?= $linhas ? '' : ' hidden' ?>><span>Peça</span><span>Quantidade</span><span>Custo unitário</span><span>Subtotal</span><span></span></div>
            <ul class="mf-est-itens" id="linhas">
              <?php foreach ($linhas as $n => $l): $pc = $porId[(int) $l['produto_id']]; ?>
                <li class="mf-est-item" data-produto="<?= (int) $pc['id'] ?>" data-saldo="<?= e($qtd($pc['estoque_atual'])) ?>">
                  <div class="mf-est-item__peca">
                    <input type="hidden" name="itens[<?= $n ?>][produto_id]" value="<?= (int) $pc['id'] ?>">
                    <strong><?= e($pc['nome']) ?></strong>
                    <small><?= $pc['codigo_interno'] ? e($pc['codigo_interno']) . ' · ' : '' ?>saldo <?= e($qtd($pc['estoque_atual'])) ?> <span class="mf-est-depois"></span></small>
                  </div>
                  <span class="mf-est-passos">
                    <button type="button" class="mf-est-passo" data-passo="-1" aria-label="Diminuir">−</button>
                    <input name="itens[<?= $n ?>][quantidade]" class="ent-qtd" value="<?= e($l['quantidade'] ?? '1') ?>" inputmode="decimal" aria-label="Quantidade">
                    <button type="button" class="mf-est-passo" data-passo="1" aria-label="Aumentar">+</button>
                  </span>
                  <span class="mf-est-moeda"><span aria-hidden="true">R$</span><input name="itens[<?= $n ?>][custo_unitario]" class="ent-custo" value="<?= e($l['custo_unitario'] ?? '') ?>" inputmode="decimal" placeholder="0,00" aria-label="Custo unitário"></span>
                  <span class="mf-est-sub ent-sub">R$ 0,00</span>
                  <button type="button" class="mf-est-remover ent-remover" aria-label="Remover <?= e($pc['nome']) ?>" title="Remover"><?= icone('close', 16, 1.75) ?></button>
                </li>
              <?php endforeach; ?>
            </ul>
            <p class="mf-est-vazio" id="entrada-vazio"<?= $linhas ? ' hidden' : '' ?>>Nenhuma peça incluída ainda.</p>

            <div class="mf-est-adicionar">
              <button type="button" class="mf-est-adicionar__botao" data-adicionar<?= $linhas ? '' : ' hidden' ?>><?= icone('plus', 16, 1.75) ?><span>Adicionar peça</span></button>
              <div class="mf-est-busca" role="combobox" aria-expanded="false" aria-haspopup="listbox" aria-owns="entrada-sugestoes"<?= $linhas ? ' hidden' : '' ?>>
                <input type="search" id="entrada-busca" placeholder="Nome ou código da peça" autocomplete="off" aria-controls="entrada-sugestoes" enterkeyhint="done">
                <ul class="mf-est-sugestoes" id="entrada-sugestoes" role="listbox" hidden></ul>
              </div>
            </div>

            <div class="mf-est-total">
              <span>Total da nota<small id="ent-resumo">Nenhuma peça</small></span>
              <strong id="ent-total">R$ 0,00</strong>
            </div>
          </div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="atualizar_custo">Atualizar o custo das peças</label><small>O custo do catálogo passa a ser o custo unitário desta nota, só nas linhas com custo.</small></div>
          <div class="mf-linha__campo mf-linha__campo--fim">
            <input class="mf-chave" type="checkbox" role="switch" id="atualizar_custo" name="atualizar_custo" value="1" data-nativo <?= !empty($d['atualizar_custo']) ? 'checked' : '' ?>>
          </div>
        </div>
      </section>

      <section class="mf__secao" id="mf-conta" aria-labelledby="mf-h-conta">
        <h3 id="mf-h-conta">Conta a pagar<small>Opcional</small></h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="gerar_conta">Lançar a conta a pagar</label><small>No valor total da nota, para o fornecedor.</small></div>
          <div class="mf-linha__campo mf-linha__campo--fim">
            <input class="mf-chave" type="checkbox" role="switch" id="gerar_conta" name="gerar_conta" value="1" data-nativo aria-controls="campos-conta" <?= !empty($d['gerar_conta']) ? 'checked' : '' ?>>
          </div>
        </div>
        <div class="mf-est-conta" id="campos-conta">
          <div class="mf-linha">
            <div class="mf-linha__rotulo"><label for="vencimento">Vencimento</label></div>
            <div class="mf-linha__campo"><input type="date" id="vencimento" name="vencimento" value="<?= e($d['vencimento'] ?? '') ?>"></div>
          </div>
          <div class="mf-linha">
            <div class="mf-linha__rotulo"><label for="forma">Forma prevista</label></div>
            <div class="mf-linha__campo">
              <select id="forma" name="forma"><?php foreach ($formas as $k => $r): ?><option value="<?= $k ?>" <?= ($d['forma'] ?? 'BOLETO') === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select>
            </div>
          </div>
        </div>
      </section>

      <section class="mf__secao" id="mf-observacao" aria-labelledby="mf-h-observacao">
        <h3 id="mf-h-observacao">Observação</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="observacao">Observação</label><small>Opcional. Aparece no livro do estoque.</small></div>
          <div class="mf-linha__campo"><input id="observacao" name="observacao" value="<?= e($d['observacao'] ?? '') ?>" maxlength="255"></div>
        </div>
      </section>
    </div>
  </div>

  <footer class="mf__rodape">
    <span class="mf__nota">As quantidades entram no estoque ao registrar.</span>
    <a class="btn btn--ghost" href="./estoque" data-mf-fechar>Cancelar</a>
    <button class="btn btn--primary" type="submit" id="ent-registrar">Registrar entrada</button>
  </footer>

  <template id="entrada-linha">
    <li class="mf-est-item">
      <div class="mf-est-item__peca">
        <input type="hidden" data-campo="produto_id">
        <strong data-nome></strong>
        <small><span data-codigo></span>saldo <span data-saldo></span> <span class="mf-est-depois"></span></small>
      </div>
      <span class="mf-est-passos">
        <button type="button" class="mf-est-passo" data-passo="-1" aria-label="Diminuir">−</button>
        <input data-campo="quantidade" class="ent-qtd" value="1" inputmode="decimal" aria-label="Quantidade">
        <button type="button" class="mf-est-passo" data-passo="1" aria-label="Aumentar">+</button>
      </span>
      <span class="mf-est-moeda"><span aria-hidden="true">R$</span><input data-campo="custo_unitario" class="ent-custo" inputmode="decimal" placeholder="0,00" aria-label="Custo unitário"></span>
      <span class="mf-est-sub ent-sub">R$ 0,00</span>
      <button type="button" class="mf-est-remover ent-remover" aria-label="Remover" title="Remover"><?= icone('close', 16, 1.75) ?></button>
    </li>
  </template>

  <script>
    (function () {
      var raiz = document.currentScript.closest('.mf');
      var $ = function (s) { return raiz.querySelector(s); };
      var catalogo = <?= json_para_script($catalogo) ?>;
      var corpo = $('#linhas'), modelo = $('#entrada-linha');
      var busca = $('#entrada-busca'), caixaBusca = $('.mf-est-busca'), lista = $('#entrada-sugestoes');
      var adicionar = $('[data-adicionar]'), vazio = $('#entrada-vazio'), cab = $('.mf-est-cab');
      var seq = corpo.children.length, ativo = -1, achados = [];
      var num = function (v) { v = String(v || '').trim(); if (v.indexOf(',') >= 0) v = v.replace(/\./g, '').replace(',', '.'); return parseFloat(v) || 0; };
      var brl = function (n) { return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); };
      var qtdTxt = function (n) { return (Math.round(n * 1000) / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 3 }); };
      var sem = function (s) { return String(s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };
      var itens = function () { return [].slice.call(corpo.querySelectorAll('.mf-est-item')); };

      function total() {
        var t = 0, un = 0, n = 0;
        itens().forEach(function (li) {
          var q = num(li.querySelector('.ent-qtd').value), s = q * num(li.querySelector('.ent-custo').value);
          li.querySelector('.ent-sub').textContent = brl(s);
          li.querySelector('.mf-est-depois').textContent = q > 0 ? '→ ' + qtdTxt(num(li.dataset.saldo) + q) : '';
          t += s; un += q; n++;
        });
        $('#ent-total').textContent = brl(t);
        $('#ent-resumo').textContent = n ? n + (n === 1 ? ' peça' : ' peças') + ' · ' + qtdTxt(un) + (un === 1 ? ' unidade' : ' unidades') : 'Nenhuma peça';
        vazio.hidden = n > 0;
        cab.hidden = n === 0;
      }

      // A busca fica aberta enquanto não há peças; depois, "Adicionar peça" abre de novo.
      function abrirBusca() { caixaBusca.hidden = false; adicionar.hidden = true; busca.focus(); }
      function recolherBusca() {
        if (busca.value.trim() || !itens().length) return;
        caixaBusca.hidden = true; adicionar.hidden = false;
      }
      adicionar.addEventListener('click', abrirBusca);

      function incluir(p) {
        var existente = corpo.querySelector('.mf-est-item[data-produto="' + p.id + '"]');
        if (existente) {
          var q = existente.querySelector('.ent-qtd'); q.value = qtdTxt(num(q.value) + 1); total(); q.focus(); q.select(); return;
        }
        var li = modelo.content.firstElementChild.cloneNode(true);
        li.dataset.produto = p.id; li.dataset.saldo = p.saldo;
        li.querySelectorAll('[data-campo]').forEach(function (el) { el.name = 'itens[' + seq + '][' + el.dataset.campo + ']'; });
        li.querySelector('[data-campo=produto_id]').value = p.id;
        li.querySelector('[data-nome]').textContent = p.nome;
        li.querySelector('[data-codigo]').textContent = p.codigo ? p.codigo + ' · ' : '';
        li.querySelector('[data-saldo]').textContent = p.saldo;
        if (p.custo !== '0,00') li.querySelector('.ent-custo').value = p.custo;
        li.querySelector('.ent-remover').setAttribute('aria-label', 'Remover ' + p.nome);
        seq++; corpo.appendChild(li); total();
        var q2 = li.querySelector('.ent-qtd'); q2.focus(); q2.select();
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
        if (termo && !achados.length) lista.innerHTML = '<li class="mf-est-sugestoes__nada">Nenhuma peça com esse nome ou código.</li>';
        lista.hidden = !termo;
        caixaBusca.setAttribute('aria-expanded', termo ? 'true' : 'false');
        marcar();
      }
      function marcar() { lista.querySelectorAll('[role=option]').forEach(function (li, i) { li.classList.toggle('is-ativo', i === ativo); li.setAttribute('aria-selected', i === ativo ? 'true' : 'false'); }); }
      function escolher(i) { if (achados[i]) { incluir(achados[i]); busca.value = ''; sugerir(); } }

      busca.addEventListener('input', sugerir);
      busca.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown' && achados.length) { ativo = (ativo + 1) % achados.length; marcar(); e.preventDefault(); }
        else if (e.key === 'ArrowUp' && achados.length) { ativo = (ativo - 1 + achados.length) % achados.length; marcar(); e.preventDefault(); }
        else if (e.key === 'Enter') { e.preventDefault(); escolher(ativo); }
        // Esc com texto limpa a busca sem fechar a janela; vazio, deixa o Esc fechar a janela.
        else if (e.key === 'Escape' && busca.value) { e.preventDefault(); e.stopPropagation(); busca.value = ''; sugerir(); }
      });
      lista.addEventListener('mousedown', function (e) { var li = e.target.closest('[role=option]'); if (li) { e.preventDefault(); escolher(+li.dataset.i); } });
      busca.addEventListener('blur', function () { setTimeout(function () { lista.hidden = true; caixaBusca.setAttribute('aria-expanded', 'false'); recolherBusca(); }, 120); });
      busca.addEventListener('focus', function () { if (busca.value.trim()) sugerir(); });

      corpo.addEventListener('input', total);
      corpo.addEventListener('click', function (e) {
        var passo = e.target.closest('.mf-est-passo');
        if (passo) { var q = passo.parentNode.querySelector('.ent-qtd'); q.value = qtdTxt(Math.max(0, num(q.value) + (+passo.dataset.passo))); total(); return; }
        var b = e.target.closest('.ent-remover');
        if (b) { b.closest('.mf-est-item').remove(); total(); abrirBusca(); }
      });
      // Enter dentro de uma linha não envia o formulário: volta para a busca.
      corpo.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); abrirBusca(); } });

      var gc = $('#gerar_conta'), cc = $('#campos-conta');
      var sync = function () { cc.hidden = !gc.checked; };
      gc.addEventListener('change', sync); sync(); total();
    })();
  </script>
</form>
