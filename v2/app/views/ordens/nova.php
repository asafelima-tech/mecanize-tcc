<?php
/**
 * Novo orçamento: identificação (cliente, veículo, técnico e relato), a montagem dos itens — serviços e
 * peças, cada um com sua área de inclusão e a lista do que já entrou —, informações adicionais e, ao lado,
 * o resumo financeiro com a ação de abrir o orçamento.
 * Os itens são montados na tela e enviados junto com a OS: o servidor inclui cada um pela mesma rotina da
 * tela da OS (mesmas validações, mesmo cálculo dos totais), tudo numa transação. Também dá para abrir sem
 * itens e incluir depois, como antes.
 * Espera: $os, $clientes, $tecnicos, $catalogo, $erro
 */
$titulo = 'Novo orçamento';
$val = fn($k) => e($os[$k] ?? '');
$qtdFmt = fn($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
$cat = array_map(fn($c) => [
    'id' => (int) $c['id'], 'tipo' => $c['tipo'], 'nome' => $c['nome'], 'codigo' => (string) ($c['codigo_interno'] ?? ''),
    'preco' => number_format((float) $c['preco_venda'], 2, ',', '.'), 'estoque' => $c['tipo'] === 'PECA' ? $qtdFmt($c['estoque_atual']) : null,
], $catalogo);
$itensIniciais = array_values(array_filter((array) ($os['itens'] ?? []), 'is_array'));
$nomesTecnicos = array_column($tecnicos, 'nome', 'id');
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Novo orçamento</h1>
    <p class="page-header__subtitle"><a href="./ordens">Ordens de serviço</a><?= !empty($os['agendamento_id']) ? ' · a partir de um <a href="./agenda">agendamento</a>' : '' ?> · monte os itens agora ou depois de abrir</p>
  </div>
</div>

<form class="orc" method="post" action="./os-criar" id="form-orcamento">
  <?= csrf_field() ?>
  <?php if (!empty($os['agendamento_id'])): ?><input type="hidden" name="agendamento_id" value="<?= (int) $os['agendamento_id'] ?>"><?php endif; ?>
  <div id="itens-enviados" hidden></div>

  <div class="orc__corpo">
    <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

    <!-- 1. Identificação -->
    <section class="card form-ficha__secao" aria-labelledby="orc-id">
      <header class="form-ficha__cabeca">
        <span class="form-ficha__icone"><?= icone('car', 18) ?></span>
        <div><h2 id="orc-id">Identificação</h2><p>Cliente, veículo e o que motivou a visita.</p></div>
      </header>
      <div class="form-ficha__grade">
        <div class="form-field form-ficha__c5">
          <div class="form-ficha__rotulo-linha"><label for="cliente_id">Cliente *</label><a class="form-ficha__alternar" href="./cliente-form">+ Cadastrar cliente</a></div>
          <select id="cliente_id" name="cliente_id" required autofocus>
            <option value="">Escolha o cliente</option>
            <?php foreach ($clientes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) ($os['cliente_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-field form-ficha__c4">
          <div class="form-ficha__rotulo-linha"><label for="veiculo_id">Veículo *</label><span id="link-veiculo"></span></div>
          <select id="veiculo_id" name="veiculo_id" required data-atual="<?= (int) ($os['veiculo_id'] ?? 0) ?>"><option value="">Escolha o cliente primeiro</option></select>
        </div>
        <div class="form-field form-ficha__c3"><label for="km_veiculo">Quilometragem</label>
          <span class="campo-sufixo"><input id="km_veiculo" name="km_veiculo" value="<?= $val('km_veiculo') ?>" inputmode="numeric" placeholder="0"><span>km</span></span></div>
        <div class="form-field form-ficha__c8"><label for="defeito_relatado">O que o cliente relatou</label>
          <textarea id="defeito_relatado" name="defeito_relatado" rows="2" placeholder="Ex.: barulho ao frear, luz da injeção acesa…"><?= $val('defeito_relatado') ?></textarea></div>
        <div class="form-field form-ficha__c4"><label for="tecnico_id">Técnico responsável</label>
          <select id="tecnico_id" name="tecnico_id"><option value="">—</option>
            <?php foreach ($tecnicos as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) ($os['tecnico_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?></option><?php endforeach; ?>
          </select></div>
      </div>
    </section>

    <!-- 2 e 3. Composição: serviços e peças -->
    <section class="card orc-comp" aria-label="Itens do orçamento">
      <?php foreach (['SERVICO' => ['work-order', 'Serviços e mão de obra', 'Do catálogo ou avulso, com o técnico que executa.'], 'PECA' => ['box', 'Peças e produtos', 'Sempre do catálogo: é ele que controla o estoque.']] as $tipo => [$ic, $nomeGrupo, $dica]): $pc = $tipo === 'PECA'; ?>
        <div class="orc-grupo" data-grupo="<?= $tipo ?>">
          <header class="orc-grupo__cabeca">
            <span class="form-ficha__icone"><?= icone($ic, 18) ?></span>
            <div><h2><?= $nomeGrupo ?> <span class="orc-grupo__n" data-contagem>0</span></h2><p><?= $dica ?></p></div>
            <strong class="orc-grupo__soma" data-soma>R$ 0,00</strong>
          </header>

          <div class="orc-adicionar" data-adicionar>
            <div class="form-field orc-adicionar__item">
              <label for="cat-<?= $tipo ?>"><?= $pc ? 'Peça' : 'Serviço' ?></label>
              <select id="cat-<?= $tipo ?>" data-campo="produto_id">
                <option value=""><?= $pc ? 'Escolha a peça' : 'Serviço avulso (descreva ao lado)' ?></option>
                <?php foreach ($cat as $c): if ($c['tipo'] !== $tipo) continue; ?>
                  <option value="<?= $c['id'] ?>"><?= e($c['nome']) ?> — R$ <?= $c['preco'] ?><?= $pc ? ' · estoque ' . $c['estoque'] : '' ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if (!$pc): ?>
              <div class="form-field orc-adicionar__desc"><label for="desc-<?= $tipo ?>">Descrição</label><input id="desc-<?= $tipo ?>" data-campo="descricao" maxlength="200" placeholder="Ex.: Troca de óleo e filtro"></div>
            <?php endif; ?>
            <div class="form-field orc-adicionar__qtd"><label for="qtd-<?= $tipo ?>">Qtd</label><input id="qtd-<?= $tipo ?>" data-campo="quantidade" value="1" inputmode="decimal"></div>
            <div class="form-field orc-adicionar__valor"><label for="vu-<?= $tipo ?>">Unitário</label><span class="campo-prefixo"><span>R$</span><input id="vu-<?= $tipo ?>" data-campo="valor_unitario" inputmode="decimal" placeholder="0,00"></span></div>
            <div class="form-field orc-adicionar__valor"><label for="dc-<?= $tipo ?>">Desconto</label><span class="campo-prefixo"><span>R$</span><input id="dc-<?= $tipo ?>" data-campo="desconto" inputmode="decimal" placeholder="0,00"></span></div>
            <?php if (!$pc): ?>
              <div class="form-field orc-adicionar__tec"><label for="tec-<?= $tipo ?>">Técnico</label>
                <select id="tec-<?= $tipo ?>" data-campo="tecnico_id"><option value="">Responsável da OS</option>
                  <?php foreach ($tecnicos as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['nome']) ?></option><?php endforeach; ?>
                </select></div>
            <?php endif; ?>
            <div class="orc-adicionar__acao">
              <button type="button" class="btn btn--primary" data-incluir><?= icone('plus') ?> <span>Adicionar</span></button>
              <button type="button" class="btn btn--ghost btn--icone" data-cancelar-edicao hidden aria-label="Cancelar alteração" title="Cancelar alteração"><?= icone('close') ?></button>
            </div>
            <p class="orc-adicionar__erro" data-erro role="alert" hidden></p>
          </div>

          <div class="orc-lista" data-lista>
            <p class="orc-lista__vazia" data-vazia><?= $pc ? 'Nenhuma peça no orçamento.' : 'Nenhum serviço no orçamento.' ?></p>
            <table class="table orc-tabela" hidden>
              <thead><tr><th><?= $pc ? 'Peça' : 'Serviço' ?></th><th class="num">Qtd</th><th class="num">Unitário</th><th class="num">Desconto</th><th class="num">Total</th><th class="num"><span class="sr-only">Ações</span></th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      <?php endforeach; ?>
    </section>

    <!-- 5. Informações adicionais -->
    <section class="card form-ficha__secao" aria-labelledby="orc-info">
      <header class="form-ficha__cabeca">
        <span class="form-ficha__icone"><?= icone('file', 18) ?></span>
        <div><h2 id="orc-info">Informações adicionais</h2><p>Prazos e observações do orçamento.</p></div>
      </header>
      <div class="form-ficha__grade">
        <div class="form-field form-ficha__c6"><label for="validade_orcamento">Orçamento válido até</label><input id="validade_orcamento" name="validade_orcamento" type="date" value="<?= $val('validade_orcamento') ?>"></div>
        <div class="form-field form-ficha__c6"><label for="previsao_conclusao">Previsão de entrega</label><input id="previsao_conclusao" name="previsao_conclusao" type="datetime-local" value="<?= $val('previsao_conclusao') ?>"></div>
        <div class="form-field form-ficha__c6"><label for="observacoes">Observações <span class="orc-rotulo-dica">saem na impressão</span></label><textarea id="observacoes" name="observacoes" rows="3" placeholder="Condições, garantia, forma de pagamento combinada…"><?= $val('observacoes') ?></textarea></div>
        <div class="form-field form-ficha__c6"><label for="observacoes_internas">Anotações internas <span class="orc-rotulo-dica">só a equipe vê</span></label><textarea id="observacoes_internas" name="observacoes_internas" rows="3"><?= $val('observacoes_internas') ?></textarea></div>
      </div>
    </section>
  </div>

  <!-- 4. Resumo financeiro -->
  <aside class="orc__lado" aria-label="Resumo do orçamento">
    <div class="card orc-resumo">
      <h2>Resumo</h2>
      <dl class="orc-resumo__linhas">
        <div><dt>Serviços <small data-res-n="SERVICO">0 itens</small></dt><dd data-res="SERVICO">R$ 0,00</dd></div>
        <div><dt>Peças <small data-res-n="PECA">0 itens</small></dt><dd data-res="PECA">R$ 0,00</dd></div>
        <div><dt>Descontos</dt><dd data-res="desconto">− R$ 0,00</dd></div>
      </dl>
      <div class="orc-resumo__total"><span>Total do orçamento</span><strong data-res="total">R$ 0,00</strong></div>
      <p class="orc-resumo__nota" data-res-validade></p>
      <div class="form-ficha__acoes">
        <button class="btn btn--primary"><?= icone('check') ?> Abrir orçamento</button>
        <a class="btn btn--ghost" href="./ordens">Cancelar</a>
      </div>
      <p class="form-ficha__nota">Depois de aberto: imprimir, enviar o link ao cliente e registrar a aprovação.</p>
    </div>
  </aside>
</form>

<script>
  (function () {
    // Veículos do cliente escolhido (igual a antes).
    var cli = document.getElementById('cliente_id'), vei = document.getElementById('veiculo_id'), link = document.getElementById('link-veiculo');
    function carregar() {
      var atual = vei.dataset.atual;
      link.innerHTML = cli.value ? '<a class="form-ficha__alternar" href="./veiculo-form?cliente=' + encodeURIComponent(cli.value) + '">+ Cadastrar veículo</a>' : '';
      vei.innerHTML = '<option value="">' + (cli.value ? 'Carregando…' : 'Escolha o cliente primeiro') + '</option>';
      if (!cli.value) return;
      fetch('./os-veiculos?cliente=' + encodeURIComponent(cli.value), { headers: { 'X-Requested-With': 'fetch' } })
        .then(function (r) { return r.json(); })
        .then(function (lista) {
          vei.innerHTML = '<option value="">' + (lista.length ? 'Escolha o veículo' : 'Este cliente não tem veículo') + '</option>';
          lista.forEach(function (v) {
            var o = document.createElement('option');
            o.value = v.id; o.textContent = v.rotulo; o.selected = String(v.id) === atual || lista.length === 1;
            vei.appendChild(o);
          });
        });
    }
    cli.addEventListener('change', function () { vei.dataset.atual = ''; carregar(); });
    carregar();

    // ── Montagem dos itens ──────────────────────────────────────────────
    var catalogo = {}; <?= json_encode($cat, JSON_UNESCAPED_UNICODE) ?>.forEach(function (c) { catalogo[c.id] = c; });
    var tecnicos = <?= json_encode((object) $nomesTecnicos, JSON_UNESCAPED_UNICODE) ?>;
    var itens = <?= json_encode($itensIniciais, JSON_UNESCAPED_UNICODE) ?>.map(function (i) {
      var c = catalogo[i.produto_id]; return Object.assign({}, i, { tipo: c ? c.tipo : (i.tipo === 'PECA' ? 'PECA' : 'SERVICO') });
    });
    var num = function (v) { v = String(v == null ? '' : v).trim(); if (!v) return null; if (v.indexOf(',') >= 0) v = v.replace(/\./g, '').replace(',', '.'); var n = parseFloat(v); return isNaN(n) ? null : n; };
    var brl = function (n) { return 'R$ ' + (n || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    var qtd = function (n) { return n.toLocaleString('pt-BR', { maximumFractionDigits: 3 }); };
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
    var valores = function (i) {
      var q = num(i.quantidade) || 0, c = catalogo[i.produto_id], u = num(i.valor_unitario);
      if (u === null) u = c ? num(c.preco) : 0;
      var bruto = Math.round(q * u * 100) / 100, d = num(i.desconto) || 0;
      return { q: q, u: u, bruto: bruto, d: d, total: bruto - d, nome: (i.descricao || '').trim() || (c ? c.nome : '') };
    };

    document.querySelectorAll('[data-grupo]').forEach(function (g) {
      var tipo = g.dataset.grupo, box = g.querySelector('[data-adicionar]'), campo = function (n) { return box.querySelector('[data-campo="' + n + '"]'); };
      var sel = campo('produto_id'), desc = campo('descricao'), erro = box.querySelector('[data-erro]');
      var botao = box.querySelector('[data-incluir]'), cancelar = box.querySelector('[data-cancelar-edicao]');
      g._editando = null;

      sel.addEventListener('change', function () {
        var c = catalogo[sel.value];
        if (c) campo('valor_unitario').value = c.preco;
        if (desc) desc.placeholder = sel.value ? 'Opcional — usa o nome do catálogo' : 'Ex.: Troca de óleo e filtro';
      });

      function limpar() {
        sel.value = ''; if (desc) { desc.value = ''; desc.placeholder = 'Ex.: Troca de óleo e filtro'; }
        campo('quantidade').value = '1'; campo('valor_unitario').value = ''; campo('desconto').value = '';
        if (campo('tecnico_id')) campo('tecnico_id').value = '';
        g._editando = null; botao.querySelector('span').textContent = 'Adicionar'; cancelar.hidden = true; box.classList.remove('is-editando');
        erro.hidden = true;
      }
      function falha(msg) { erro.textContent = msg; erro.hidden = false; }

      botao.addEventListener('click', function () {
        var i = { tipo: tipo, produto_id: sel.value, descricao: desc ? desc.value.trim() : '', quantidade: campo('quantidade').value.trim() || '1',
                  valor_unitario: campo('valor_unitario').value.trim(), desconto: campo('desconto').value.trim(), tecnico_id: campo('tecnico_id') ? campo('tecnico_id').value : '' };
        // As mesmas regras da inclusão na OS (o servidor confere de novo ao salvar).
        if (tipo === 'PECA' && !i.produto_id) return falha('Escolha a peça do catálogo: é ela que controla o estoque.');
        if (tipo === 'SERVICO' && !i.produto_id && !i.descricao) return falha('Descreva o serviço.');
        var v = valores(i);
        if (!(v.q > 0)) return falha('A quantidade precisa ser maior que zero.');
        if (v.d > v.bruto) return falha('O desconto não pode ser maior que o valor do item.');
        if (g._editando !== null) itens[g._editando] = i; else itens.push(i);
        limpar(); desenhar(); sel.focus();
      });
      cancelar.addEventListener('click', limpar);
      box.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.tagName === 'INPUT') { e.preventDefault(); botao.click(); } });

      g._editar = function (idx) {
        var i = itens[idx]; g._editando = idx;
        sel.value = i.produto_id || ''; if (desc) desc.value = i.descricao || '';
        campo('quantidade').value = i.quantidade; campo('valor_unitario').value = i.valor_unitario; campo('desconto').value = i.desconto;
        if (campo('tecnico_id')) campo('tecnico_id').value = i.tecnico_id || '';
        botao.querySelector('span').textContent = 'Salvar alteração'; cancelar.hidden = false; box.classList.add('is-editando'); erro.hidden = true;
        sel.focus();
      };
      g._limpar = limpar;
    });

    function desenhar() {
      var soma = { SERVICO: 0, PECA: 0 }, cont = { SERVICO: 0, PECA: 0 }, desconto = 0, total = 0;
      document.querySelectorAll('[data-grupo]').forEach(function (g) {
        var tipo = g.dataset.grupo, tb = g.querySelector('tbody'); tb.innerHTML = '';
        itens.forEach(function (i, idx) {
          if (i.tipo !== tipo) return;
          var v = valores(i), c = catalogo[i.produto_id];
          soma[tipo] += v.bruto; cont[tipo]++; desconto += v.d; total += v.total;
          var sub = [];
          if (c && c.codigo) sub.push(esc(c.codigo));
          if (!c) sub.push('avulso');
          if (tipo === 'SERVICO') sub.push(i.tecnico_id && tecnicos[i.tecnico_id] ? esc(tecnicos[i.tecnico_id]) : 'responsável da OS');
          if (tipo === 'PECA' && c) sub.push('estoque ' + esc(c.estoque));
          var tr = document.createElement('tr'); if (g._editando === idx) tr.className = 'is-editando';
          tr.innerHTML = '<td><span class="orc-item"><strong>' + esc(v.nome) + '</strong><small>' + sub.join(' · ') + '</small></span></td>'
            + '<td class="num">' + qtd(v.q) + '</td><td class="num">' + brl(v.u) + '</td>'
            + '<td class="num">' + (v.d ? '− ' + brl(v.d) : '<span class="orc-traco">—</span>') + '</td>'
            + '<td class="num"><strong>' + brl(v.total) + '</strong></td>'
            + '<td class="num"><span class="acoes-linha">'
            + '<button type="button" class="btn btn--fantasma btn--icone" data-editar="' + idx + '" aria-label="Alterar ' + esc(v.nome) + '" title="Alterar"><?= str_replace("'", "\\'", icone('edit')) ?></button>'
            + '<button type="button" class="btn btn--fantasma btn--icone" data-remover="' + idx + '" aria-label="Remover ' + esc(v.nome) + '" title="Remover"><?= str_replace("'", "\\'", icone('trash')) ?></button>'
            + '</span></td>';
          tb.appendChild(tr);
        });
        g.querySelector('table').hidden = cont[tipo] === 0;
        g.querySelector('[data-vazia]').hidden = cont[tipo] > 0;
        g.querySelector('[data-contagem]').textContent = cont[tipo];
        g.querySelector('[data-soma]').textContent = brl(soma[tipo]);
      });
      ['SERVICO', 'PECA'].forEach(function (t) {
        document.querySelector('[data-res="' + t + '"]').textContent = brl(soma[t]);
        document.querySelector('[data-res-n="' + t + '"]').textContent = cont[t] + (cont[t] === 1 ? ' item' : ' itens');
      });
      document.querySelector('[data-res="desconto"]').textContent = '− ' + brl(desconto);
      document.querySelector('[data-res="total"]').textContent = brl(total);
    }

    document.querySelector('.orc-comp').addEventListener('click', function (e) {
      var b = e.target.closest('[data-editar], [data-remover]'); if (!b) return;
      var idx = +(b.dataset.editar || b.dataset.remover), g = document.querySelector('[data-grupo="' + itens[idx].tipo + '"]');
      if (b.dataset.editar !== undefined) { g._editar(idx); desenhar(); return; }
      itens.splice(idx, 1);
      document.querySelectorAll('[data-grupo]').forEach(function (x) { if (x._editando !== null) x._limpar(); });
      desenhar();
    });

    // Validade no resumo
    var val = document.getElementById('validade_orcamento'), nota = document.querySelector('[data-res-validade]');
    function validade() { var p = val.value.split('-'); nota.innerHTML = val.value ? '<?= str_replace("'", "\\'", icone('calendar', 13)) ?> Válido até ' + p[2] + '/' + p[1] + '/' + p[0] : ''; }
    val.addEventListener('change', validade); validade();

    // Envio: os itens viram campos itens[n][...] do formulário.
    document.getElementById('form-orcamento').addEventListener('submit', function (e) {
      var pendente = Array.prototype.some.call(document.querySelectorAll('[data-adicionar]'), function (b) {
        var s = b.querySelector('[data-campo="produto_id"]'), d = b.querySelector('[data-campo="descricao"]');
        return s.value || (d && d.value.trim());
      });
      var form = e.target;
      if (pendente && !form._semPendente) {
        e.preventDefault();
        Mecanize.confirmar('Há um item preenchido que ainda não foi adicionado. Abrir o orçamento sem ele?', { titulo: 'Item não adicionado', sim: 'Abrir sem ele' })
          .then(function (ok) { if (ok) { form._semPendente = true; form.requestSubmit(); } });
        return;
      }
      var box = document.getElementById('itens-enviados'); box.innerHTML = '';
      itens.forEach(function (i, n) {
        ['produto_id', 'tipo', 'descricao', 'quantidade', 'valor_unitario', 'desconto', 'tecnico_id'].forEach(function (k) {
          var h = document.createElement('input'); h.type = 'hidden'; h.name = 'itens[' + n + '][' + k + ']'; h.value = i[k] == null ? '' : i[k]; box.appendChild(h);
        });
      });
    });
    desenhar();
  })();
</script>
