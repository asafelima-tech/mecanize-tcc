<?php
/**
 * Nova ordem de serviço (abre como orçamento), no padrão de janela (modal) das telas de criar e editar.
 * Seções: cliente e veículo, relato, itens (serviços e peças montados na tela) e prazo e observações.
 * Os itens são montados aqui e enviados junto com a OS (campos itens[n][...]): o servidor inclui cada um
 * pela mesma rotina da tela da OS (mesmas validações, mesmo cálculo dos totais), tudo numa transação.
 * Também dá para abrir sem itens e incluir depois. Aberta pelo js/modal.js; acessada direto, ocupa a página.
 * Espera: $os, $clientes, $tecnicos, $catalogo, $erro (opcional)
 */
$titulo = 'Nova ordem de serviço';
$val = fn($k) => e($os[$k] ?? '');
$qtdFmt = fn($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
$cat = array_map(fn($c) => [
    'id' => (int) $c['id'], 'tipo' => $c['tipo'], 'nome' => $c['nome'], 'codigo' => (string) ($c['codigo_interno'] ?? ''),
    'preco' => number_format((float) $c['preco_venda'], 2, ',', '.'), 'estoque' => $c['tipo'] === 'PECA' ? $qtdFmt($c['estoque_atual']) : null,
], $catalogo);
$itensIniciais = array_values(array_filter((array) ($os['itens'] ?? []), 'is_array'));
$nomesTecnicos = array_column($tecnicos, 'nome', 'id');
$doAgendamento = !empty($os['agendamento_id']);
$json = fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$secoes = [['cliente', 'car', 'Cliente e veículo'], ['relato', 'message', 'Relato'], ['itens', 'work-order', 'Itens'], ['prazo', 'calendar', 'Prazo e observações']];
$grupos = [
    'SERVICO' => ['Serviços e mão de obra', 'Do catálogo ou avulso, com o técnico que executa.', 'Adicionar serviço', 'Nenhum serviço ainda.'],
    'PECA'    => ['Peças e produtos', 'Sempre do catálogo: é ele que controla o estoque.', 'Adicionar peça', 'Nenhuma peça ainda.'],
];
?>
<form class="mf" method="post" action="./os-criar" aria-labelledby="mf-titulo">
  <?= csrf_field() ?>
  <?php if ($doAgendamento): ?><input type="hidden" name="agendamento_id" value="<?= (int) $os['agendamento_id'] ?>"><?php endif; ?>
  <div data-itens-enviados hidden></div>

  <header class="mf__topo">
    <h2 class="mf__titulo" id="mf-titulo"><?= $titulo ?><?php if ($doAgendamento): ?><span>A partir do agendamento</span><?php endif; ?></h2>
    <a class="mf__fechar" href="./ordens" data-mf-fechar aria-label="Fechar" title="Fechar"><?= icone('close', 18, 1.75) ?></a>
  </header>

  <div class="mf__corpo">
    <nav class="mf__nav" aria-label="Seções da ordem de serviço">
      <?php foreach ($secoes as $k => [$ancora, $ic, $rotulo]): ?>
        <a href="#mf-<?= $ancora ?>"<?= $k === 0 ? ' class="is-ativa" aria-current="true"' : '' ?>><?= icone($ic, 18) ?><span><?= $rotulo ?></span></a>
      <?php endforeach; ?>
    </nav>

    <div class="mf__conteudo">
      <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

      <section class="mf__secao" id="mf-cliente" aria-labelledby="mf-h-cliente">
        <h3 id="mf-h-cliente">Cliente e veículo</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="cliente_id">Cliente</label><small>Não está na lista? <a href="./cliente-form">Cadastrar cliente</a></small></div>
          <div class="mf-linha__campo">
            <select id="cliente_id" name="cliente_id" required autofocus>
              <option value="">Escolha o cliente</option>
              <?php foreach ($clientes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) ($os['cliente_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="veiculo_id">Veículo</label><small data-dica-veiculo>Escolha o cliente primeiro.</small></div>
          <div class="mf-linha__campo">
            <select id="veiculo_id" name="veiculo_id" required data-atual="<?= (int) ($os['veiculo_id'] ?? 0) ?>"><option value="">Escolha o cliente primeiro</option></select>
          </div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="km_veiculo">Quilometragem</label><small>Em km, como está no painel. Opcional.</small></div>
          <div class="mf-linha__campo"><input id="km_veiculo" name="km_veiculo" value="<?= $val('km_veiculo') ?>" inputmode="numeric" placeholder="0"></div>
        </div>
      </section>

      <section class="mf__secao" id="mf-relato" aria-labelledby="mf-h-relato">
        <h3 id="mf-h-relato">Relato</h3>
        <div class="mf-linha mf-linha--bloco">
          <div class="mf-linha__rotulo"><label for="defeito_relatado">O que o cliente relatou</label><small>Sintomas, barulhos, luzes no painel. Ajuda no diagnóstico.</small></div>
          <div class="mf-linha__campo"><textarea id="defeito_relatado" name="defeito_relatado" rows="3" placeholder="Ex.: barulho ao frear, luz da injeção acesa"><?= $val('defeito_relatado') ?></textarea></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="tecnico_id">Técnico responsável</label><small>Quem cuida da OS. Cada serviço pode ter outro técnico.</small></div>
          <div class="mf-linha__campo">
            <select id="tecnico_id" name="tecnico_id"><option value="">Definir depois</option>
              <?php foreach ($tecnicos as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) ($os['tecnico_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
      </section>

      <section class="mf__secao" id="mf-itens" aria-labelledby="mf-h-itens">
        <h3 id="mf-h-itens">Itens<small>Opcional, dá para incluir depois de abrir</small></h3>

        <?php foreach ($grupos as $tipo => [$nomeGrupo, $dica, $rotuloAdicionar, $vazio]): $pc = $tipo === 'PECA'; $t = strtolower($tipo); ?>
          <div class="mf-linha mf-linha--bloco" data-grupo="<?= $tipo ?>">
            <div class="mf-linha__rotulo"><span id="orc-g-<?= $t ?>"><?= $nomeGrupo ?></span><small><?= $dica ?></small></div>
            <div class="mf-linha__campo">
              <ul class="mf-orc-lista" data-lista aria-labelledby="orc-g-<?= $t ?>" hidden></ul>
              <p class="mf-orc-vazia" data-vazia><?= $vazio ?></p>

              <div class="mf-orc-editor<?= $pc ? ' mf-orc-editor--peca' : '' ?>" data-editor role="group" aria-label="<?= $rotuloAdicionar ?>" hidden>
                <div class="mf-orc-campo mf-orc-campo--item">
                  <label for="orc-cat-<?= $t ?>"><?= $pc ? 'Peça' : 'Serviço' ?></label>
                  <select id="orc-cat-<?= $t ?>" data-campo="produto_id">
                    <option value=""><?= $pc ? 'Escolha a peça' : 'Serviço avulso (descreva ao lado)' ?></option>
                    <?php foreach ($cat as $c): if ($c['tipo'] !== $tipo) continue; ?>
                      <option value="<?= $c['id'] ?>"><?= e($c['nome']) ?> · R$ <?= $c['preco'] ?><?= $pc ? ' · estoque ' . $c['estoque'] : '' ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <?php if (!$pc): ?>
                  <div class="mf-orc-campo mf-orc-campo--desc"><label for="orc-desc-<?= $t ?>">Descrição</label><input id="orc-desc-<?= $t ?>" data-campo="descricao" maxlength="200" placeholder="Ex.: Troca de óleo e filtro"></div>
                <?php endif; ?>
                <div class="mf-orc-campo mf-orc-campo--qtd"><label for="orc-qtd-<?= $t ?>">Qtd</label><input id="orc-qtd-<?= $t ?>" data-campo="quantidade" value="1" inputmode="decimal"></div>
                <div class="mf-orc-campo mf-orc-campo--valor"><label for="orc-vu-<?= $t ?>">Unitário (R$)</label><input id="orc-vu-<?= $t ?>" data-campo="valor_unitario" inputmode="decimal" placeholder="0,00"></div>
                <div class="mf-orc-campo mf-orc-campo--valor"><label for="orc-dc-<?= $t ?>">Desconto (R$)</label><input id="orc-dc-<?= $t ?>" data-campo="desconto" inputmode="decimal" placeholder="0,00"></div>
                <?php if (!$pc): ?>
                  <div class="mf-orc-campo mf-orc-campo--tec"><label for="orc-tec-<?= $t ?>">Técnico</label>
                    <select id="orc-tec-<?= $t ?>" data-campo="tecnico_id"><option value="">Responsável da OS</option>
                      <?php foreach ($tecnicos as $tc): ?><option value="<?= (int) $tc['id'] ?>"><?= e($tc['nome']) ?></option><?php endforeach; ?>
                    </select></div>
                <?php endif; ?>
                <p class="mf-orc-erro" data-erro role="alert" hidden></p>
                <div class="mf-orc-editor__acoes">
                  <button type="button" class="btn btn--ghost btn--sm" data-cancelar>Cancelar</button>
                  <button type="button" class="btn btn--primary btn--sm" data-incluir>Adicionar</button>
                </div>
              </div>

              <button type="button" class="mf-orc-mais" data-abrir><?= icone('plus', 16, 1.75) ?><span><?= $rotuloAdicionar ?></span></button>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="mf-linha mf-orc-total">
          <div class="mf-linha__rotulo"><span>Total do orçamento</span><small data-total-detalhe>Serviços R$ 0,00 · Peças R$ 0,00</small></div>
          <div class="mf-linha__campo mf-linha__campo--fim"><strong data-total>R$ 0,00</strong></div>
        </div>
      </section>

      <section class="mf__secao" id="mf-prazo" aria-labelledby="mf-h-prazo">
        <h3 id="mf-h-prazo">Prazo e observações</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="validade_orcamento">Orçamento válido até</label><small>Depois dessa data, os valores precisam ser revistos.</small></div>
          <div class="mf-linha__campo"><input id="validade_orcamento" name="validade_orcamento" type="date" value="<?= $val('validade_orcamento') ?>"></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="previsao_conclusao">Previsão de entrega</label><small>Dia e hora combinados com o cliente.</small></div>
          <div class="mf-linha__campo"><input id="previsao_conclusao" name="previsao_conclusao" type="datetime-local" value="<?= $val('previsao_conclusao') ?>"></div>
        </div>
        <div class="mf-linha mf-linha--bloco">
          <div class="mf-linha__rotulo"><label for="observacoes">Observações</label><small>Saem na impressão: condições, garantia, forma de pagamento combinada.</small></div>
          <div class="mf-linha__campo"><textarea id="observacoes" name="observacoes" rows="3"><?= $val('observacoes') ?></textarea></div>
        </div>
        <div class="mf-linha mf-linha--bloco">
          <div class="mf-linha__rotulo"><label for="observacoes_internas">Anotações internas</label><small>Só a equipe vê.</small></div>
          <div class="mf-linha__campo"><textarea id="observacoes_internas" name="observacoes_internas" rows="3"><?= $val('observacoes_internas') ?></textarea></div>
        </div>
      </section>
    </div>
  </div>

  <footer class="mf__rodape">
    <span class="mf__nota" data-nota>Cliente e veículo são obrigatórios.</span>
    <a class="btn btn--ghost" href="./ordens" data-mf-fechar>Cancelar</a>
    <button class="btn btn--primary" type="submit">Abrir OS</button>
  </footer>

  <script>
    (function () {
      var raiz = document.currentScript.closest('.mf');
      var form = raiz.matches('form') ? raiz : raiz.querySelector('form');
      var $ = function (s, b) { return (b || raiz).querySelector(s); };
      var $$ = function (s, b) { return (b || raiz).querySelectorAll(s); };

      // ── Veículos do cliente escolhido ─────────────────────────────────────
      // O rótulo vem do servidor como "placa (traço) modelo"; aqui o separador vira "·".
      var TRACO = new RegExp('\\s+[' + String.fromCharCode(8212, 8211) + ']\\s+', 'g');
      var cli = $('#cliente_id'), vei = $('#veiculo_id'), dicaVei = $('[data-dica-veiculo]');
      function carregar() {
        var atual = vei.dataset.atual, pedido = cli.value;
        dicaVei.innerHTML = pedido ? 'Não está na lista? <a href="./veiculo-form?cliente=' + encodeURIComponent(pedido) + '">Cadastrar veículo</a>' : 'Escolha o cliente primeiro.';
        vei.innerHTML = '<option value="">' + (pedido ? 'Carregando…' : 'Escolha o cliente primeiro') + '</option>';
        if (!pedido) return;
        fetch('./os-veiculos?cliente=' + encodeURIComponent(pedido), { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (lista) {
            if (cli.value !== pedido) return; // trocou de cliente enquanto carregava
            vei.innerHTML = '<option value="">' + (lista.length ? 'Escolha o veículo' : 'Este cliente não tem veículo') + '</option>';
            lista.forEach(function (v) {
              var o = document.createElement('option');
              o.value = v.id; o.textContent = String(v.rotulo).replace(TRACO, ' · ');
              o.selected = String(v.id) === atual || lista.length === 1;
              vei.appendChild(o);
            });
          })
          .catch(function () { if (cli.value === pedido) vei.innerHTML = '<option value="">Não foi possível carregar os veículos</option>'; });
      }
      cli.addEventListener('change', function () { vei.dataset.atual = ''; carregar(); });
      carregar();

      // ── Montagem dos itens ────────────────────────────────────────────────
      var catalogo = {}; <?= $json($cat) ?>.forEach(function (c) { catalogo[c.id] = c; });
      var tecnicos = <?= $json((object) $nomesTecnicos) ?>;
      var itens = <?= $json($itensIniciais) ?>.map(function (i) {
        var c = catalogo[i.produto_id]; return Object.assign({}, i, { tipo: c ? c.tipo : (i.tipo === 'PECA' ? 'PECA' : 'SERVICO') });
      });
      var ICONE = { editar: <?= $json(icone('edit', 16)) ?>, remover: <?= $json(icone('trash', 16)) ?> };
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
      var grupos = $$('[data-grupo]');

      grupos.forEach(function (g) {
        var tipo = g.dataset.grupo, ed = $('[data-editor]', g), campo = function (n) { return $('[data-campo="' + n + '"]', ed); };
        var sel = campo('produto_id'), desc = campo('descricao'), erro = $('[data-erro]', ed);
        var botao = $('[data-incluir]', ed), cancelar = $('[data-cancelar]', ed), abrir = $('[data-abrir]', g);
        var DICA_DESC = 'Ex.: Troca de óleo e filtro';
        g._editando = null;

        function mostrar() { ed.hidden = false; abrir.hidden = true; sel.focus(); }
        function limpar(fechar) {
          sel.value = ''; if (desc) { desc.value = ''; desc.placeholder = DICA_DESC; }
          campo('quantidade').value = '1'; campo('valor_unitario').value = ''; campo('desconto').value = '';
          if (campo('tecnico_id')) campo('tecnico_id').value = '';
          g._editando = null; botao.textContent = 'Adicionar'; erro.hidden = true;
          if (fechar) { ed.hidden = true; abrir.hidden = false; }
        }
        function falha(msg) { erro.textContent = msg; erro.hidden = false; }

        sel.addEventListener('change', function () {
          var c = catalogo[sel.value];
          if (c) campo('valor_unitario').value = c.preco;
          if (desc) desc.placeholder = sel.value ? 'Opcional, usa o nome do catálogo' : DICA_DESC;
        });
        abrir.addEventListener('click', mostrar);

        botao.addEventListener('click', function () {
          var i = { tipo: tipo, produto_id: sel.value, descricao: desc ? desc.value.trim() : '', quantidade: campo('quantidade').value.trim() || '1',
                    valor_unitario: campo('valor_unitario').value.trim(), desconto: campo('desconto').value.trim(), tecnico_id: campo('tecnico_id') ? campo('tecnico_id').value : '' };
          // As mesmas regras da inclusão na OS (o servidor confere de novo ao salvar).
          if (tipo === 'PECA' && !i.produto_id) return falha('Escolha a peça do catálogo: é ela que controla o estoque.');
          if (tipo === 'SERVICO' && !i.produto_id && !i.descricao) return falha('Descreva o serviço.');
          var v = valores(i);
          if (!(v.q > 0)) return falha('A quantidade precisa ser maior que zero.');
          if (v.d > v.bruto) return falha('O desconto não pode ser maior que o valor do item.');
          var alterando = g._editando !== null;
          if (alterando) itens[g._editando] = i; else itens.push(i);
          // Item novo: o editor continua aberto para o próximo. Alteração: fecha.
          limpar(alterando); desenhar();
          if (alterando) abrir.focus(); else sel.focus();
        });
        cancelar.addEventListener('click', function () { limpar(true); desenhar(); abrir.focus(); });
        ed.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.tagName === 'INPUT') { e.preventDefault(); botao.click(); } });

        g._editar = function (idx) {
          var i = itens[idx]; g._editando = idx;
          sel.value = i.produto_id || ''; if (desc) { desc.value = i.descricao || ''; desc.placeholder = sel.value ? 'Opcional, usa o nome do catálogo' : DICA_DESC; }
          campo('quantidade').value = i.quantidade; campo('valor_unitario').value = i.valor_unitario; campo('desconto').value = i.desconto;
          if (campo('tecnico_id')) campo('tecnico_id').value = i.tecnico_id || '';
          botao.textContent = 'Salvar alteração'; erro.hidden = true; ed.hidden = false; abrir.hidden = true;
          sel.focus();
        };
        g._limpar = limpar;
      });

      // Validade (para a nota do rodapé): o campo de data devolve aaaa-mm-dd em .value.
      var validade = $('#validade_orcamento');
      function textoValidade() { var p = (validade.value || '').slice(0, 10).split('-'); return p.length === 3 ? 'válido até ' + p[2] + '/' + p[1] + '/' + p[0] : ''; }

      function desenhar() {
        var soma = { SERVICO: 0, PECA: 0 }, cont = { SERVICO: 0, PECA: 0 }, desconto = 0, total = 0;
        grupos.forEach(function (g) {
          var tipo = g.dataset.grupo, ul = $('[data-lista]', g); ul.innerHTML = '';
          itens.forEach(function (i, idx) {
            if (i.tipo !== tipo) return;
            var v = valores(i), c = catalogo[i.produto_id];
            soma[tipo] += v.bruto; cont[tipo]++; desconto += v.d; total += v.total;
            var sub = [];
            if (c && c.codigo) sub.push(esc(c.codigo));
            if (!c) sub.push('avulso');
            if (tipo === 'SERVICO') sub.push(i.tecnico_id && tecnicos[i.tecnico_id] ? esc(tecnicos[i.tecnico_id]) : 'responsável da OS');
            if (tipo === 'PECA' && c) sub.push('estoque ' + esc(c.estoque));
            var li = document.createElement('li');
            li.className = 'mf-orc-item' + (g._editando === idx ? ' is-editando' : '');
            li.innerHTML = '<div class="mf-orc-item__nome"><strong>' + esc(v.nome) + '</strong><small>' + sub.join(' · ') + '</small></div>'
              + '<div class="mf-orc-item__conta">' + qtd(v.q) + ' × ' + brl(v.u) + (v.d ? '<small>desconto ' + brl(v.d) + '</small>' : '') + '</div>'
              + '<strong class="mf-orc-item__total">' + brl(v.total) + '</strong>'
              + '<div class="mf-orc-item__acoes">'
              + '<button type="button" class="mf-orc-acao" data-editar="' + idx + '" aria-label="Alterar ' + esc(v.nome) + '" title="Alterar">' + ICONE.editar + '</button>'
              + '<button type="button" class="mf-orc-acao mf-orc-acao--perigo" data-remover="' + idx + '" aria-label="Remover ' + esc(v.nome) + '" title="Remover">' + ICONE.remover + '</button>'
              + '</div>';
            ul.appendChild(li);
          });
          ul.hidden = cont[tipo] === 0;
          $('[data-vazia]', g).hidden = cont[tipo] > 0;
        });
        var n = cont.SERVICO + cont.PECA;
        $('[data-total]').textContent = brl(total);
        $('[data-total-detalhe]').textContent = 'Serviços ' + brl(soma.SERVICO) + ' · Peças ' + brl(soma.PECA) + (desconto ? ' · descontos ' + brl(desconto) : '');
        var nota = n ? 'Total ' + brl(total) + ' · ' + n + (n === 1 ? ' item' : ' itens') : 'Cliente e veículo são obrigatórios.';
        var vt = textoValidade();
        $('[data-nota]').textContent = n && vt ? nota + ' · ' + vt : nota;
      }
      validade.addEventListener('change', desenhar);

      raiz.addEventListener('click', function (e) {
        var b = e.target.closest('[data-editar], [data-remover]'); if (!b || !raiz.contains(b)) return;
        var idx = +(b.dataset.editar !== undefined ? b.dataset.editar : b.dataset.remover);
        var g = $('[data-grupo="' + itens[idx].tipo + '"]');
        if (b.dataset.editar !== undefined) { g._editar(idx); desenhar(); return; }
        itens.splice(idx, 1);
        grupos.forEach(function (x) { if (x._editando !== null) x._limpar(true); });
        desenhar();
      });

      // Envio: os itens viram campos itens[n][...] do formulário. Roda antes do envio da janela (js/modal.js).
      form.addEventListener('submit', function (e) {
        var pendente = Array.prototype.some.call(grupos, function (g) {
          var s = $('[data-campo="produto_id"]', g), d = $('[data-campo="descricao"]', g);
          return s.value || (d && d.value.trim());
        });
        if (pendente && !form._semPendente) {
          e.preventDefault();
          Mecanize.confirmar('Há um item preenchido que ainda não foi adicionado. Abrir a OS sem ele?', { titulo: 'Item não adicionado', sim: 'Abrir sem ele' })
            .then(function (ok) { if (ok) { form._semPendente = true; form.requestSubmit(); } });
          return;
        }
        var box = $('[data-itens-enviados]'); box.innerHTML = '';
        itens.forEach(function (i, n) {
          ['produto_id', 'tipo', 'descricao', 'quantidade', 'valor_unitario', 'desconto', 'tecnico_id'].forEach(function (k) {
            var h = document.createElement('input'); h.type = 'hidden'; h.name = 'itens[' + n + '][' + k + ']'; h.value = i[k] == null ? '' : i[k]; box.appendChild(h);
          });
        });
      });
      desenhar();
    })();
  </script>
</form>
