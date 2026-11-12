<?php
/**
 * Cadastro e edição de peça ou serviço, no formato de ficha das outras telas de cadastro:
 * um cartão com três grupos (identificação, preço e estoque em destaque, complementares) e, ao lado,
 * o resumo com o preço e as ações de salvar. Margem e situação do estoque são só leitura, calculadas
 * na tela a partir do que está nos campos.
 * Os campos, nomes, validações e o destino do formulário são os mesmos de sempre.
 * Espera: $pr, $id, $categorias, $erro
 */
$peca = ($pr['tipo'] ?? 'PECA') === 'PECA';
$titulo = ($id ? 'Editar ' : 'Nova ') . ($peca ? 'peça' : 'serviço');
if (!$id && !$peca) { $titulo = 'Novo serviço'; }
$val = fn($k) => e($pr[$k] ?? '');
$num = fn($k) => isset($pr[$k]) && $pr[$k] !== '' ? e(is_numeric($pr[$k]) ? number_format((float) $pr[$k], 2, ',', '.') : $pr[$k]) : '';
$qtd = fn($x) => rtrim(rtrim((string) $x, '0'), '.');
$estoqueAtual = $qtd($pr['estoque_atual'] ?? '0') ?: '0';
$voltar = $id ? './produto?id=' . (int) $id : './produtos';
$nome = trim((string) ($pr['nome'] ?? ''));
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= $titulo ?></h1>
    <p class="page-header__subtitle"><a href="./produtos">Peças e serviços</a><?= $id ? ' · ' . $val('nome') : ($peca ? ' · item do estoque usado nas OS' : ' · mão de obra cobrada nas OS') ?></p>
  </div>
</div>

<form class="form-ficha" method="post" action="./produto-salvar<?= $id ? '?id=' . (int) $id : '' ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="tipo" value="<?= $peca ? 'PECA' : 'SERVICO' ?>">

  <div class="form-ficha__corpo">
    <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

    <div class="card form-ficha__secao grupos">
      <!-- 1. Identificação -->
      <section class="grupo" aria-labelledby="g-identificacao">
        <header class="grupo__cabeca">
          <span class="grupo__n">1</span>
          <h2 id="g-identificacao">Identificação <?= $peca ? 'da peça' : 'do serviço' ?></h2>
        </header>
        <div class="form-ficha__grade">
          <div class="form-field form-ficha__c6"><label for="nome">Nome *</label><input id="nome" name="nome" value="<?= $val('nome') ?>" required maxlength="150" autofocus></div>
          <div class="form-field form-ficha__c3">
            <label for="categoria">Categoria</label>
            <input id="categoria" name="categoria" value="<?= $val('categoria') ?>" list="lista-categorias" maxlength="60" placeholder="Escolha ou digite">
            <datalist id="lista-categorias"><?php foreach ($categorias as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
          </div>
          <div class="form-field form-ficha__c3"><label for="codigo_interno">Código interno</label><input id="codigo_interno" name="codigo_interno" value="<?= $val('codigo_interno') ?>" maxlength="40" class="campo-codigo"></div>
        </div>
      </section>

      <!-- 2. Preço e estoque (em destaque) -->
      <section class="grupo grupo--destaque" aria-labelledby="g-preco">
        <header class="grupo__cabeca">
          <span class="grupo__n">2</span>
          <h2 id="g-preco"><?= $peca ? 'Precificação e estoque' : 'Precificação' ?></h2>
        </header>
        <div class="painel-numeros<?= $peca ? '' : ' painel-numeros--um' ?>">
          <div class="painel-numeros__bloco">
            <span class="painel-numeros__titulo"><?= icone('coins', 15) ?> Preço</span>
            <div class="painel-numeros__campos">
              <div class="form-field"><label for="preco_venda">Preço de venda *</label>
                <span class="campo-prefixo"><span>R$</span><input id="preco_venda" name="preco_venda" value="<?= $num('preco_venda') ?>" inputmode="decimal" required placeholder="0,00"></span></div>
              <div class="form-field"><label for="preco_custo"><?= $peca ? 'Custo' : 'Custo estimado' ?></label>
                <span class="campo-prefixo"><span>R$</span><input id="preco_custo" name="preco_custo" value="<?= $num('preco_custo') ?>" inputmode="decimal" placeholder="0,00"></span></div>
            </div>
            <p class="painel-numeros__rodape" data-margem>Informe o preço e o custo para ver a margem.</p>
          </div>

          <?php if ($peca): ?>
            <div class="painel-numeros__bloco">
              <span class="painel-numeros__titulo"><?= icone('part', 15) ?> Estoque</span>
              <div class="painel-numeros__campos">
                <div class="form-field"><label for="estoque_minimo">Estoque mínimo</label>
                  <input id="estoque_minimo" name="estoque_minimo" value="<?= $val('estoque_minimo') !== '' ? e($qtd($pr['estoque_minimo'])) : '' ?>" inputmode="decimal" placeholder="0"></div>
                <?php if (!$id): ?>
                  <div class="form-field"><label for="estoque_inicial">Estoque inicial</label>
                    <input id="estoque_inicial" name="estoque_inicial" value="<?= $val('estoque_inicial') ?>" inputmode="decimal" placeholder="0"></div>
                <?php else: ?>
                  <div class="form-field"><label for="estoque_atual_ver">Estoque atual</label>
                    <span class="campo-trava"><input id="estoque_atual_ver" value="<?= e($estoqueAtual) ?>" disabled data-estoque-atual="<?= e($estoqueAtual) ?>"><?= icone('lock', 14) ?></span></div>
                <?php endif; ?>
              </div>
              <p class="painel-numeros__rodape" data-estoque><?= $id ? 'O estoque atual muda só por entrada, baixa ou ajuste de estoque.' : 'A quantidade inicial entra no estoque ao salvar.' ?></p>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- 3. Complementares -->
      <section class="grupo" aria-labelledby="g-complementar">
        <header class="grupo__cabeca">
          <span class="grupo__n">3</span>
          <h2 id="g-complementar">Informações complementares</h2>
        </header>
        <div class="form-ficha__grade">
          <?php if ($peca): ?>
            <div class="form-field form-ficha__c4"><label for="marca">Marca</label><input id="marca" name="marca" value="<?= $val('marca') ?>" maxlength="60"></div>
            <div class="form-field form-ficha__c4"><label for="codigo_barras">Código de barras</label>
              <span class="campo-icone"><?= icone('barcode') ?><input id="codigo_barras" name="codigo_barras" value="<?= $val('codigo_barras') ?>" maxlength="40" inputmode="numeric" class="campo-codigo"></span></div>
            <div class="form-field form-ficha__c4"><label for="localizacao">Localização</label>
              <span class="campo-icone"><?= icone('location') ?><input id="localizacao" name="localizacao" value="<?= $val('localizacao') ?>" maxlength="60" placeholder="Ex.: Prateleira B3"></span></div>
          <?php endif; ?>
          <div class="form-field form-ficha__c12"><label for="descricao">Descrição</label><textarea id="descricao" name="descricao" rows="3" placeholder="<?= $peca ? 'Aplicação, medidas, observações de uso…' : 'O que está incluído no serviço…' ?>"><?= $val('descricao') ?></textarea></div>
        </div>
      </section>
    </div>
  </div>

  <aside class="form-ficha__lado" aria-label="Resumo">
    <div class="card form-ficha__resumo">
      <div class="form-ficha__perfil">
        <span class="form-ficha__avatar form-ficha__avatar--quadrado"><?= icone($peca ? 'box' : 'work-order', 22) ?></span>
        <div class="form-ficha__perfil-texto">
          <strong data-previa-nome><?= $nome !== '' ? e($nome) : ($peca ? 'Nova peça' : 'Novo serviço') ?></strong>
          <span data-previa-sub><?= e(implode(' · ', array_filter([$peca ? 'Peça' : 'Serviço', $pr['categoria'] ?? '', $pr['codigo_interno'] ?? ''], fn($x) => (string) $x !== ''))) ?></span>
        </div>
      </div>
      <div class="resumo-preco">
        <span>Preço de venda</span>
        <strong data-previa-preco>R$ <?= $num('preco_venda') !== '' ? $num('preco_venda') : '0,00' ?></strong>
      </div>
      <dl class="form-ficha__dados form-ficha__dados--lista">
        <div><dt><?= icone('percent', 14) ?> Margem</dt><dd data-previa-margem>—</dd></div>
        <?php if ($peca && $id): ?><div><dt><?= icone('part', 14) ?> Em estoque</dt><dd data-previa-estoque><?= e($estoqueAtual) ?></dd></div><?php endif; ?>
      </dl>
      <div class="form-ficha__acoes">
        <button class="btn btn--primary"><?= icone('check') ?> Salvar <?= $peca ? 'peça' : 'serviço' ?></button>
        <a class="btn btn--ghost" href="<?= $voltar ?>">Cancelar</a>
      </div>
      <p class="form-ficha__nota">Campos com * são obrigatórios.</p>
    </div>
  </aside>
</form>

<script>
  (function () {
    // Só leitura: margem, situação do estoque e o resumo ao lado acompanham os campos.
    var $ = function (s) { return document.getElementById(s); };
    var numero = function (v) {
      v = String(v || '').trim(); if (!v) return null;
      if (v.indexOf(',') >= 0) v = v.replace(/\./g, '').replace(',', '.');
      var n = parseFloat(v); return isNaN(n) ? null : n;
    };
    var dinheiro = function (n) { return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    var qtd = function (n) { return n.toLocaleString('pt-BR', { maximumFractionDigits: 3 }); };
    var ehPeca = <?= $peca ? 'true' : 'false' ?>, temId = <?= $id ? 'true' : 'false' ?>;
    var pMargem = document.querySelector('[data-margem]'), pEstoque = document.querySelector('[data-estoque]');
    var tipoRotulo = ehPeca ? 'Peça' : 'Serviço';

    function atualizar() {
      var venda = numero($('preco_venda').value), custo = numero($('preco_custo').value);
      document.querySelector('[data-previa-preco]').textContent = 'R$ ' + dinheiro(venda || 0);
      var margemTxt = '—';
      if (venda && custo !== null) {
        var lucro = venda - custo, pct = lucro / venda * 100;
        margemTxt = pct.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '%';
        pMargem.innerHTML = 'Margem de <strong>' + margemTxt + '</strong> · ' + (lucro >= 0 ? 'lucro de' : 'prejuízo de') + ' <strong>R$ ' + dinheiro(Math.abs(lucro)) + '</strong> por unidade';
        pMargem.classList.toggle('is-alerta', lucro < 0);
      } else {
        pMargem.textContent = 'Informe o preço e o custo para ver a margem.';
        pMargem.classList.remove('is-alerta');
      }
      document.querySelector('[data-previa-margem]').textContent = margemTxt;

      if (ehPeca && pEstoque) {
        var min = numero($('estoque_minimo').value);
        var atualEl = temId ? $('estoque_atual_ver') : $('estoque_inicial');
        var atual = numero(atualEl ? (temId ? atualEl.dataset.estoqueAtual : atualEl.value) : null) || 0;
        var base = temId ? 'O estoque atual muda só por entrada, baixa ou ajuste de estoque.' : 'A quantidade inicial entra no estoque ao salvar.';
        if (min) {
          var baixo = atual <= min;
          pEstoque.innerHTML = '<span class="situacao' + (baixo ? ' situacao--baixo' : '') + '">' + (baixo ? 'No mínimo ou abaixo' : 'Acima do mínimo') + '</span> ' + base;
        } else {
          pEstoque.textContent = base;
        }
      }

      var nome = $('nome').value.trim();
      document.querySelector('[data-previa-nome]').textContent = nome || (ehPeca ? 'Nova peça' : 'Novo serviço');
      document.querySelector('[data-previa-sub]').textContent = [tipoRotulo, $('categoria').value.trim(), $('codigo_interno').value.trim()].filter(Boolean).join(' · ');
    }
    ['preco_venda', 'preco_custo', 'estoque_minimo', 'estoque_inicial', 'nome', 'categoria', 'codigo_interno'].forEach(function (id) {
      var el = $(id); if (el) el.addEventListener('input', atualizar);
    });
    atualizar();
  })();
</script>
