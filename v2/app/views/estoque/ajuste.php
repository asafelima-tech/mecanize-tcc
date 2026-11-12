<?php
/**
 * Ajuste de estoque pela contagem física, no formato de ficha das outras telas de cadastro: um cartão com a peça
 * e a contagem e, ao lado, o resumo com o saldo do sistema, o contado e a diferença que será lançada.
 * Os campos (produto_id, contado, justificativa), a validação e o destino são os mesmos de sempre.
 * Espera: $pecas, $d, $erro
 */
$titulo = 'Ajuste de estoque';
$qtd = fn($x) => rtrim(rtrim((string) $x, '0'), '.');
$sel = (int) ($d['produto_id'] ?? 0);
$atual = null;
foreach ($pecas as $p) { if ((int) $p['id'] === $sel) { $atual = $p; } }
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Ajuste de estoque</h1>
    <p class="page-header__subtitle"><a href="./estoque">Estoque</a> · acertar o saldo pela contagem física</p>
  </div>
</div>

<form class="form-ficha" method="post" action="./estoque-ajuste">
  <?= csrf_field() ?>

  <div class="form-ficha__corpo">
    <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

    <div class="card form-ficha__secao grupos">
      <section class="grupo" aria-labelledby="g-peca">
        <header class="grupo__cabeca"><span class="grupo__n">1</span><h2 id="g-peca">Peça contada</h2></header>
        <div class="form-ficha__grade">
          <div class="form-field form-ficha__c12">
            <label for="produto_id">Peça *</label>
            <select id="produto_id" name="produto_id" required>
              <option value="">Escolha a peça</option>
              <?php foreach ($pecas as $p): ?>
                <option value="<?= (int) $p['id'] ?>" data-saldo="<?= e($qtd($p['estoque_atual'])) ?>" data-codigo="<?= e($p['codigo_interno'] ?? '') ?>" <?= $sel === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?><?= $p['codigo_interno'] ? ' · ' . e($p['codigo_interno']) : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </section>

      <section class="grupo grupo--destaque" aria-labelledby="g-contagem">
        <header class="grupo__cabeca"><span class="grupo__n">2</span><h2 id="g-contagem">Contagem</h2></header>
        <div class="painel-numeros painel-numeros--um">
          <div class="painel-numeros__bloco">
            <span class="painel-numeros__titulo"><?= icone('part', 15) ?> Na prateleira</span>
            <div class="painel-numeros__campos">
              <div class="form-field"><label for="contado">Quantidade contada *</label>
                <input id="contado" name="contado" value="<?= e($d['contado'] ?? '') ?>" inputmode="decimal" required placeholder="0"></div>
              <div class="form-field"><label for="saldo-sistema">No sistema</label>
                <span class="campo-trava"><input id="saldo-sistema" value="<?= $atual ? e($qtd($atual['estoque_atual'])) : '' ?>" disabled placeholder="—"><?= icone('lock', 14) ?></span></div>
            </div>
            <p class="painel-numeros__rodape" data-diferenca>Informe quanto há na prateleira para ver a diferença.</p>
          </div>
        </div>
      </section>

      <section class="grupo" aria-labelledby="g-motivo">
        <header class="grupo__cabeca"><span class="grupo__n">3</span><h2 id="g-motivo">Motivo</h2></header>
        <div class="form-field"><label for="justificativa">Motivo do ajuste *</label>
          <input id="justificativa" name="justificativa" value="<?= e($d['justificativa'] ?? '') ?>" maxlength="255" required placeholder="Ex.: inventário do mês, peça avariada"></div>
      </section>
    </div>
  </div>

  <aside class="form-ficha__lado" aria-label="Resumo do ajuste">
    <div class="card form-ficha__resumo">
      <div class="form-ficha__perfil">
        <span class="form-ficha__avatar form-ficha__avatar--quadrado"><?= icone('settings', 22) ?></span>
        <div class="form-ficha__perfil-texto">
          <strong data-previa-nome><?= $atual ? e($atual['nome']) : 'Escolha a peça' ?></strong>
          <span data-previa-codigo><?= $atual && $atual['codigo_interno'] ? e($atual['codigo_interno']) : 'Ajuste por contagem' ?></span>
        </div>
      </div>
      <div class="resumo-preco">
        <span>Diferença lançada</span>
        <strong data-previa-diferenca>—</strong>
      </div>
      <dl class="form-ficha__dados form-ficha__dados--lista">
        <div><dt><?= icone('history', 14) ?> No sistema</dt><dd data-previa-sistema><?= $atual ? e($qtd($atual['estoque_atual'])) : '—' ?></dd></div>
        <div><dt><?= icone('check', 14) ?> Contado</dt><dd data-previa-contado>—</dd></div>
      </dl>
      <div class="form-ficha__acoes">
        <button class="btn btn--primary"><?= icone('check') ?> Ajustar estoque</button>
        <a class="btn btn--ghost" href="./estoque">Cancelar</a>
      </div>
      <p class="form-ficha__nota">O saldo passa a ser a quantidade contada; a diferença fica no livro do estoque.</p>
    </div>
  </aside>
</form>

<script>
  (function () {
    // Só leitura: o saldo do sistema e a diferença acompanham a peça escolhida e o que foi contado.
    var s = document.getElementById('produto_id'), contado = document.getElementById('contado');
    var sistema = document.getElementById('saldo-sistema');
    var pNome = document.querySelector('[data-previa-nome]'), pCod = document.querySelector('[data-previa-codigo]');
    var pSis = document.querySelector('[data-previa-sistema]'), pCont = document.querySelector('[data-previa-contado]');
    var pDif = document.querySelector('[data-previa-diferenca]'), rodape = document.querySelector('[data-diferenca]');
    var numero = function (v) {
      v = String(v || '').trim(); if (!v) return null;
      if (v.indexOf(',') >= 0) v = v.replace(/\./g, '').replace(',', '.');
      var n = parseFloat(v); return isNaN(n) ? null : n;
    };
    var fmt = function (n) { return n.toLocaleString('pt-BR', { maximumFractionDigits: 3 }); };
    function sync() {
      var o = s.options[s.selectedIndex], tem = o && o.value;
      var saldo = tem ? numero(o.dataset.saldo) : null;
      sistema.value = tem ? o.dataset.saldo : '';
      pNome.textContent = tem ? o.textContent.split(' · ')[0] : 'Escolha a peça';
      pCod.textContent = tem && o.dataset.codigo ? o.dataset.codigo : 'Ajuste por contagem';
      pSis.textContent = tem ? o.dataset.saldo : '—';
      var c = numero(contado.value);
      pCont.textContent = c !== null ? fmt(c) : '—';
      if (saldo !== null && c !== null) {
        var dif = c - saldo;
        pDif.textContent = (dif > 0 ? '+' : '') + fmt(dif);
        rodape.innerHTML = dif === 0 ? 'A contagem bate com o sistema: nada muda no saldo.'
          : (dif > 0 ? 'Entram <strong>' + fmt(dif) + '</strong>' : 'Saem <strong>' + fmt(-dif) + '</strong>') + ' unidade(s): o saldo vai de ' + fmt(saldo) + ' para <strong>' + fmt(c) + '</strong>.';
      } else {
        pDif.textContent = '—';
        rodape.textContent = 'Informe quanto há na prateleira para ver a diferença.';
      }
    }
    s.addEventListener('change', sync); contado.addEventListener('input', sync); sync();
  })();
</script>
