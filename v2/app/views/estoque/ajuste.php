<?php
/**
 * Ajuste de estoque pela contagem física, no padrão de janela (modal) simples: a peça, o saldo do sistema,
 * a quantidade contada, a diferença que será lançada e o motivo.
 * Os campos (produto_id, contado, justificativa), a validação e o destino são os mesmos de sempre.
 * Espera: $pecas, $d, $erro
 */
$titulo = 'Ajuste de estoque';
$qtd = fn($x) => rtrim(rtrim((string) $x, '0'), '.');
$sel = (int) ($d['produto_id'] ?? 0);
$atual = null;
foreach ($pecas as $p) { if ((int) $p['id'] === $sel) { $atual = $p; } }
?>
<form class="mf mf--simples" method="post" action="./estoque-ajuste" aria-labelledby="mf-titulo">
  <?= csrf_field() ?>

  <header class="mf__topo">
    <h2 class="mf__titulo" id="mf-titulo"><?= $titulo ?><span data-titulo-peca><?= $atual ? e($atual['nome']) : '' ?></span></h2>
    <a class="mf__fechar" href="./estoque" data-mf-fechar aria-label="Fechar" title="Fechar"><?= icone('close', 18, 1.75) ?></a>
  </header>

  <div class="mf__corpo">
    <div class="mf__conteudo">
      <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

      <section class="mf__secao" id="mf-contagem" aria-labelledby="mf-h-contagem">
        <h3 id="mf-h-contagem">Contagem física</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="produto_id">Peça</label></div>
          <div class="mf-linha__campo">
            <select id="produto_id" name="produto_id" required <?= $atual ? '' : 'autofocus' ?>>
              <option value="">Escolha a peça</option>
              <?php foreach ($pecas as $p): ?>
                <option value="<?= (int) $p['id'] ?>" data-saldo="<?= e($qtd($p['estoque_atual'])) ?>" data-codigo="<?= e($p['codigo_interno'] ?? '') ?>" <?= $sel === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?><?= $p['codigo_interno'] ? ' · ' . e($p['codigo_interno']) : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><span>No sistema</span><small>Saldo registrado hoje.</small></div>
          <div class="mf-linha__campo mf-linha__campo--fim"><span class="mf-est-leitura" data-previa-sistema><?= $atual ? e($qtd($atual['estoque_atual'])) : '' ?></span></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="contado">Quantidade contada</label><small>Quanto há na prateleira agora.</small></div>
          <div class="mf-linha__campo"><input id="contado" name="contado" value="<?= e($d['contado'] ?? '') ?>" inputmode="decimal" required placeholder="0" autocomplete="off" <?= $atual ? 'autofocus' : '' ?>></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><span>Diferença</span><small data-diferenca aria-live="polite">Informe a contagem para ver a diferença.</small></div>
          <div class="mf-linha__campo mf-linha__campo--fim"><span class="mf-est-leitura mf-est-leitura--forte" data-previa-diferenca></span></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="justificativa">Motivo do ajuste</label><small>Fica registrado no livro do estoque.</small></div>
          <div class="mf-linha__campo"><input id="justificativa" name="justificativa" value="<?= e($d['justificativa'] ?? '') ?>" maxlength="255" required placeholder="Ex.: inventário do mês, peça avariada"></div>
        </div>
      </section>
    </div>
  </div>

  <footer class="mf__rodape">
    <span class="mf__nota">O saldo passa a ser a quantidade contada.</span>
    <a class="btn btn--ghost" href="./estoque" data-mf-fechar>Cancelar</a>
    <button class="btn btn--primary" type="submit">Ajustar estoque</button>
  </footer>

  <script>
    (function () {
      // Só leitura: o saldo do sistema e a diferença acompanham a peça escolhida e o que foi contado.
      var raiz = document.currentScript.closest('.mf');
      var s = raiz.querySelector('#produto_id'), contado = raiz.querySelector('#contado');
      var tNome = raiz.querySelector('[data-titulo-peca]'), pSis = raiz.querySelector('[data-previa-sistema]');
      var pDif = raiz.querySelector('[data-previa-diferenca]'), texto = raiz.querySelector('[data-diferenca]');
      var numero = function (v) {
        v = String(v || '').trim(); if (!v) return null;
        if (v.indexOf(',') >= 0) v = v.replace(/\./g, '').replace(',', '.');
        var n = parseFloat(v); return isNaN(n) ? null : n;
      };
      var fmt = function (n) { return n.toLocaleString('pt-BR', { maximumFractionDigits: 3 }); };
      function sync() {
        var o = s.options[s.selectedIndex], tem = o && o.value;
        var saldo = tem ? numero(o.dataset.saldo) : null;
        tNome.textContent = tem ? o.textContent.split(' · ')[0] : '';
        pSis.textContent = tem ? fmt(saldo || 0) : '';
        var c = numero(contado.value);
        pDif.classList.remove('is-entra', 'is-sai');
        if (saldo !== null && c !== null) {
          var dif = c - saldo;
          pDif.textContent = (dif > 0 ? '+' : '') + fmt(dif);
          if (dif) pDif.classList.add(dif > 0 ? 'is-entra' : 'is-sai');
          texto.textContent = dif === 0 ? 'A contagem bate com o sistema: nada muda no saldo.'
            : (dif > 0 ? 'Entram ' + fmt(dif) : 'Saem ' + fmt(-dif)) + ' unidade(s). O saldo vai de ' + fmt(saldo) + ' para ' + fmt(c) + '.';
        } else {
          pDif.textContent = '';
          texto.textContent = tem ? 'Informe a contagem para ver a diferença.' : 'Escolha a peça e informe a contagem.';
        }
      }
      s.addEventListener('change', sync); contado.addEventListener('input', sync); sync();
    })();
  </script>
</form>
