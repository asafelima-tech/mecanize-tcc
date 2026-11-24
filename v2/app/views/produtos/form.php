<?php
/**
 * Cadastro e edição de peça ou serviço, no padrão de janela (modal) das telas de criar e editar.
 * Topo com título e fechar, seções à esquerda, linhas "rótulo à esquerda, campo à direita" e o rodapé
 * com as ações. Margem e situação do estoque são só leitura, calculadas na tela a partir dos campos.
 * Os campos, nomes, validações e o destino do formulário são os mesmos de sempre.
 * Espera: $pr, $id, $categorias, $erro
 */
$peca = ($pr['tipo'] ?? 'PECA') === 'PECA';
$titulo = $id ? ($peca ? 'Editar peça' : 'Editar serviço') : ($peca ? 'Nova peça' : 'Novo serviço');
$val = fn($k) => e($pr[$k] ?? '');
$num = fn($k) => isset($pr[$k]) && $pr[$k] !== '' ? e(is_numeric($pr[$k]) ? number_format((float) $pr[$k], 2, ',', '.') : $pr[$k]) : '';
// Tira só os zeros depois do ponto ("5.000" vira "5"); "10" vindo do formulário continua "10".
$qtd = fn($x) => str_contains((string) $x, '.') ? rtrim(rtrim((string) $x, '0'), '.') : (string) $x;
// Na volta de um erro de validação o POST não traz o estoque atual: a linha de leitura só aparece quando ele existe.
$temEstoque = $id && $peca && isset($pr['estoque_atual']);
$estoqueAtual = $qtd($pr['estoque_atual'] ?? '0') ?: '0';
$voltar = $id ? './produto?id=' . (int) $id : './produtos';
$nome = trim((string) ($pr['nome'] ?? ''));
$secoes = [['identificacao', $peca ? 'box' : 'work-order', 'Identificação'], ['preco', 'coins', 'Preço']];
if ($peca) { $secoes[] = ['estoque', 'part', 'Estoque']; }
$secoes[] = ['detalhes', 'file', 'Detalhes'];
?>
<form class="mf" method="post" action="./produto-salvar<?= $id ? '?id=' . (int) $id : '' ?>" aria-labelledby="mf-titulo">
  <?= csrf_field() ?>
  <input type="hidden" name="tipo" value="<?= $peca ? 'PECA' : 'SERVICO' ?>">

  <header class="mf__topo">
    <h2 class="mf__titulo" id="mf-titulo"><?= $titulo ?><?php if ($id && $nome !== ''): ?><span><?= e($nome) ?></span><?php endif; ?></h2>
    <a class="mf__fechar" href="<?= $voltar ?>" data-mf-fechar aria-label="Fechar" title="Fechar"><?= icone('close', 18, 1.75) ?></a>
  </header>

  <div class="mf__corpo">
    <nav class="mf__nav" aria-label="Seções do cadastro">
      <?php foreach ($secoes as $k => [$ancora, $ic, $rotulo]): ?>
        <a href="#mf-<?= $ancora ?>"<?= $k === 0 ? ' class="is-ativa" aria-current="true"' : '' ?>><?= icone($ic, 18) ?><span><?= $rotulo ?></span></a>
      <?php endforeach; ?>
      <?php if ($id): ?>
        <dl class="mf__nav-rodape">
          <div><dt>Tipo</dt><dd><?= $peca ? 'Peça' : 'Serviço' ?></dd></div>
          <?php if ($temEstoque): ?><div><dt>Em estoque</dt><dd><?= e($estoqueAtual) ?></dd></div><?php endif; ?>
          <?php if (isset($pr['ativo'])): ?><div><dt>Situação</dt><dd><?= $pr['ativo'] ? 'Ativo' : 'Inativo' ?></dd></div><?php endif; ?>
        </dl>
      <?php endif; ?>
    </nav>

    <div class="mf__conteudo">
      <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

      <section class="mf__secao" id="mf-identificacao" aria-labelledby="mf-h-identificacao">
        <h3 id="mf-h-identificacao">Identificação</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="nome">Nome</label><small><?= $peca ? 'Como a peça aparece no orçamento e na OS.' : 'Como o serviço aparece no orçamento e na OS.' ?></small></div>
          <div class="mf-linha__campo"><input id="nome" name="nome" value="<?= $val('nome') ?>" required maxlength="150" autocomplete="off" autofocus></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="categoria">Categoria</label><small>Escolha uma da lista ou digite uma nova.</small></div>
          <div class="mf-linha__campo">
            <input id="categoria" name="categoria" value="<?= $val('categoria') ?>" list="lista-categorias" maxlength="60" autocomplete="off">
            <datalist id="lista-categorias"><?php foreach ($categorias as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
          </div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="codigo_interno">Código interno</label><small>Opcional.</small></div>
          <div class="mf-linha__campo"><input id="codigo_interno" name="codigo_interno" value="<?= $val('codigo_interno') ?>" maxlength="40" autocomplete="off"></div>
        </div>
      </section>

      <section class="mf__secao" id="mf-preco" aria-labelledby="mf-h-preco">
        <h3 id="mf-h-preco">Preço</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="preco_venda">Preço de venda</label><small>Valor cobrado do cliente.</small></div>
          <div class="mf-linha__campo"><span class="mf-prefixo"><span>R$</span><input id="preco_venda" name="preco_venda" value="<?= $num('preco_venda') ?>" inputmode="decimal" required placeholder="0,00" autocomplete="off"></span></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="preco_custo"><?= $peca ? 'Custo' : 'Custo estimado' ?></label><small>Não aparece para o cliente.</small></div>
          <div class="mf-linha__campo"><span class="mf-prefixo"><span>R$</span><input id="preco_custo" name="preco_custo" value="<?= $num('preco_custo') ?>" inputmode="decimal" placeholder="0,00" autocomplete="off"></span></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><span>Margem</span><small data-margem>Informe o preço e o custo para ver a margem.</small></div>
          <div class="mf-linha__campo mf-linha__campo--fim"><output class="mf-valor" data-previa-margem aria-live="polite"></output></div>
        </div>
      </section>

      <?php if ($peca): ?>
        <section class="mf__secao" id="mf-estoque" aria-labelledby="mf-h-estoque">
          <h3 id="mf-h-estoque">Estoque</h3>
          <div class="mf-linha">
            <div class="mf-linha__rotulo"><label for="estoque_minimo">Estoque mínimo</label><small data-estoque>No mínimo ou abaixo, a peça entra em estoque baixo.</small></div>
            <div class="mf-linha__campo"><input id="estoque_minimo" name="estoque_minimo" value="<?= $val('estoque_minimo') !== '' ? e($qtd($pr['estoque_minimo'])) : '' ?>" inputmode="decimal" placeholder="0" autocomplete="off"></div>
          </div>
          <?php if (!$id): ?>
            <div class="mf-linha">
              <div class="mf-linha__rotulo"><label for="estoque_inicial">Estoque inicial</label><small>Entra no estoque ao salvar.</small></div>
              <div class="mf-linha__campo"><input id="estoque_inicial" name="estoque_inicial" value="<?= $val('estoque_inicial') ?>" inputmode="decimal" placeholder="0" autocomplete="off"></div>
            </div>
          <?php elseif ($temEstoque): ?>
            <div class="mf-linha">
              <div class="mf-linha__rotulo"><span>Estoque atual</span><small>Muda só por entrada, baixa ou ajuste de estoque.</small></div>
              <div class="mf-linha__campo mf-linha__campo--fim"><output class="mf-valor" data-estoque-atual="<?= e($estoqueAtual) ?>"><?= e($estoqueAtual) ?></output></div>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <section class="mf__secao" id="mf-detalhes" aria-labelledby="mf-h-detalhes">
        <h3 id="mf-h-detalhes">Detalhes<small>Opcional</small></h3>
        <?php if ($peca): ?>
          <div class="mf-linha">
            <div class="mf-linha__rotulo"><label for="marca">Marca</label></div>
            <div class="mf-linha__campo"><input id="marca" name="marca" value="<?= $val('marca') ?>" maxlength="60"></div>
          </div>
          <div class="mf-linha">
            <div class="mf-linha__rotulo"><label for="codigo_barras">Código de barras</label></div>
            <div class="mf-linha__campo"><input id="codigo_barras" name="codigo_barras" value="<?= $val('codigo_barras') ?>" maxlength="40" inputmode="numeric" autocomplete="off"></div>
          </div>
          <div class="mf-linha">
            <div class="mf-linha__rotulo"><label for="localizacao">Localização</label><small>Onde a peça fica guardada.</small></div>
            <div class="mf-linha__campo"><input id="localizacao" name="localizacao" value="<?= $val('localizacao') ?>" maxlength="60" placeholder="Ex.: Prateleira B3"></div>
          </div>
        <?php endif; ?>
        <div class="mf-linha mf-linha--bloco">
          <div class="mf-linha__rotulo"><label for="descricao">Descrição</label><small><?= $peca ? 'Aplicação, medidas e observações de uso.' : 'O que está incluído no serviço.' ?></small></div>
          <div class="mf-linha__campo"><textarea id="descricao" name="descricao" rows="3"><?= $val('descricao') ?></textarea></div>
        </div>
      </section>
    </div>
  </div>

  <footer class="mf__rodape">
    <span class="mf__nota">Nome e preço de venda são obrigatórios.</span>
    <a class="btn btn--ghost" href="<?= $voltar ?>" data-mf-fechar>Cancelar</a>
    <button class="btn btn--primary" type="submit"><?= $id ? 'Salvar alterações' : ($peca ? 'Cadastrar peça' : 'Cadastrar serviço') ?></button>
  </footer>

  <script>
    (function () {
      // Só leitura: a margem e a situação do estoque acompanham os campos.
      var raiz = document.currentScript.closest('.mf');
      var q = function (s) { return raiz.querySelector(s); };
      var numero = function (v) {
        v = String(v || '').trim(); if (!v) return null;
        if (v.indexOf(',') >= 0) v = v.replace(/\./g, '').replace(',', '.');
        var n = parseFloat(v); return isNaN(n) ? null : n;
      };
      var dinheiro = function (n) { return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
      var venda = q('#preco_venda'), custo = q('#preco_custo'), minimo = q('#estoque_minimo'), inicial = q('#estoque_inicial');
      var atualEl = q('[data-estoque-atual]'), pMargem = q('[data-margem]'), oMargem = q('[data-previa-margem]'), pEstoque = q('[data-estoque]');

      function atualizar() {
        var v = numero(venda.value), c = numero(custo.value);
        if (v && c !== null) {
          var lucro = v - c, pct = lucro / v * 100;
          oMargem.textContent = pct.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '%';
          pMargem.textContent = (lucro >= 0 ? 'Lucro de R$ ' : 'Prejuízo de R$ ') + dinheiro(Math.abs(lucro)) + ' por unidade.';
          oMargem.classList.toggle('is-alerta', lucro < 0);
          pMargem.classList.toggle('mf-texto-alerta', lucro < 0);
        } else {
          oMargem.textContent = '';
          pMargem.textContent = 'Informe o preço e o custo para ver a margem.';
          oMargem.classList.remove('is-alerta');
          pMargem.classList.remove('mf-texto-alerta');
        }

        if (pEstoque && minimo) {
          var min = numero(minimo.value);
          var atual = numero(atualEl ? atualEl.dataset.estoqueAtual : (inicial ? inicial.value : null)) || 0;
          var baixo = !!min && atual <= min;
          pEstoque.textContent = !min ? 'No mínimo ou abaixo, a peça entra em estoque baixo.'
            : (baixo ? 'Situação: no mínimo ou abaixo.' : 'Situação: acima do mínimo.');
          pEstoque.classList.toggle('mf-texto-alerta', baixo);
        }
      }
      [venda, custo, minimo, inicial].forEach(function (el) { if (el) el.addEventListener('input', atualizar); });
      atualizar();
    })();
  </script>
</form>
