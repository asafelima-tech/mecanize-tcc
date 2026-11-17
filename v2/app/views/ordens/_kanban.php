<?php
/**
 * Kanban das OS em aberto: uma coluna por situação, na ordem do fluxo. Arrastar um cartão para
 * outra coluna muda a situação pelas mesmas operações da tela da OS (OrdemServico::MOVIMENTOS_KANBAN):
 * as colunas que aceitam a OS se destacam, as outras ficam apagadas. Aprovar e voltar para
 * execução pedem confirmação. Clicar no cartão continua abrindo a OS.
 * Espera: $colunas ([situação => [OS...]], de OrdemServico::kanban()).
 */
$noKanban = array_sum(array_map('count', $colunas));
?>
<?php if ($noKanban >= OrdemServico::KANBAN_LIMITE): ?>
  <p class="kanban__aviso">Mostrando as <?= OrdemServico::KANBAN_LIMITE ?> OS mais recentes. Use a busca ou o filtro de técnico para ver as outras.</p>
<?php endif; ?>
<div class="kanban" role="list" data-mover="./os-mover" data-token="<?= e(Csrf::token()) ?>"
     data-movimentos="<?= e(json_encode(OrdemServico::MOVIMENTOS_KANBAN)) ?>">
  <?php foreach ($colunas as $status => $cartoes): [$rotulo, $cor] = os_status($status); ?>
    <section class="kanban__coluna" role="listitem" data-status="<?= e($status) ?>" data-rotulo="<?= e($rotulo) ?>" aria-label="<?= e($rotulo) ?>: <?= count($cartoes) ?>">
      <header class="kanban__topo">
        <span class="badge badge--<?= $cor ?>"><?= e($rotulo) ?></span>
        <span class="kanban__n"><?= count($cartoes) ?></span>
      </header>
      <p class="kanban__vazio"<?= $cartoes ? ' hidden' : '' ?>>Nenhuma OS</p>
      <?php foreach ($cartoes as $o): ?>
        <a class="kanban__cartao" href="./os?id=<?= (int) $o['id'] ?>" draggable="false"
           data-id="<?= (int) $o['id'] ?>" data-status="<?= e($o['status']) ?>" data-numero="<?= (int) $o['numero'] ?>"
           data-os="<?= e(os_numero($o['numero'])) ?>" data-cliente="<?= e($o['cliente']) ?>" data-total="<?= e(dinheiro($o['valor_total'])) ?>">
          <span class="kanban__linha">
            <strong class="tabular"><?= os_numero($o['numero']) ?></strong>
            <?php if (OrdemServico::atrasada($o)): ?><span class="badge badge--danger">Atrasada</span><?php endif; ?>
          </span>
          <span class="kanban__cliente"><?= e($o['cliente']) ?></span>
          <span class="kanban__veiculo"><?= e($o['modelo'] ?: 'Veículo') ?> · <span class="placa"><?= e($o['placa']) ?></span></span>
          <span class="kanban__linha kanban__rodape">
            <span><?= icone('mechanic', 14) ?> <?= e($o['tecnico'] ?? 'Sem técnico') ?></span>
            <strong class="tabular"><?= dinheiro($o['valor_total']) ?></strong>
          </span>
        </a>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>
</div>

<!-- Confirmações ao soltar o cartão (aprovar e voltar para execução) -->
<dialog id="dlg-kb-aprovar" class="dlg">
  <form method="dialog">
    <h3>Aprovar orçamento</h3>
    <p><strong data-kb-os></strong><br>Os itens e o valor de agora ficam guardados como o que o cliente aprovou.</p>
    <div class="form-field"><label for="kb-aprovado-por">Aprovado por</label><input id="kb-aprovado-por" name="aprovado_por" maxlength="120" autocomplete="off"></div>
    <p class="form-dica">Para colher a assinatura do cliente, aprove pela tela da OS.</p>
    <div class="form-acoes"><button type="button" class="btn btn--ghost" data-kb-cancelar>Cancelar</button><button class="btn btn--primary" value="ok">Aprovar</button></div>
  </form>
</dialog>
<dialog id="dlg-kb-retomar" class="dlg">
  <form method="dialog">
    <h3>Voltar para execução</h3>
    <p><strong data-kb-os></strong></p>
    <div class="form-field"><label for="kb-motivo">O que precisa ser refeito? (opcional)</label><textarea id="kb-motivo" name="motivo" rows="3"></textarea></div>
    <div class="form-acoes"><button type="button" class="btn btn--ghost" data-kb-cancelar>Cancelar</button><button class="btn btn--primary" value="ok">Voltar para execução</button></div>
  </form>
</dialog>

<div class="kb-aviso" role="status" aria-live="polite"><span></span></div>

<script>
  // Kanban: arrastar o cartão de uma coluna para outra (mouse: segurar e arrastar; toque: segurar um instante e arrastar).
  (function () {
    var quadro = document.querySelector('.kanban[data-mover]');
    if (!quadro || !window.fetch) return;
    var MOV = JSON.parse(quadro.dataset.movimentos || '{}');
    var colunas = [].slice.call(quadro.querySelectorAll('.kanban__coluna'));
    var semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var CURVA = 'cubic-bezier(.2, .8, .2, 1)';
    var s = null;            // arrasto em andamento
    var bloquearCliqueAte = 0;

    var colunaDe = function (st) { return quadro.querySelector('.kanban__coluna[data-status="' + st + '"]'); };
    var aceita = function (de, para) { return de !== para && !!MOV[de + '>' + para]; };
    var rotuloDe = function (st) { var c = colunaDe(st); return c ? c.dataset.rotulo : st; };

    /* ── Coluna sob o ponteiro: a mais próxima na horizontal (sem "buracos" entre colunas) ── */
    function colunaSob(x, y) {
      var r = quadro.getBoundingClientRect();
      if (x < r.left - 24 || x > r.right + 24 || y < r.top - 48) return null;
      var melhor = null, dist = Infinity;
      colunas.forEach(function (c) {
        var cr = c.getBoundingClientRect();
        if (cr.right < r.left || cr.left > r.right) return; // fora da área visível (rolagem lateral)
        var d = x < cr.left ? cr.left - x : (x > cr.right ? x - cr.right : 0);
        if (d < dist) { dist = d; melhor = c; }
      });
      return melhor;
    }

    /* ── Animação FLIP: os cartões deslizam da posição antiga para a nova ── */
    function comFlip(cols, mudanca) {
      var els = [];
      cols.forEach(function (c) { if (c) els = els.concat([].slice.call(c.querySelectorAll('.kanban__cartao:not(.is-chegando), .kanban__vaga'))); });
      var antes = els.map(function (el) { return el.getBoundingClientRect(); });
      mudanca();
      if (semMovimento) return;
      els.forEach(function (el, i) {
        if (!el.isConnected) return;
        var d = el.getBoundingClientRect(), dx = antes[i].left - d.left, dy = antes[i].top - d.top;
        if (Math.abs(dx) < .5 && Math.abs(dy) < .5) return;
        el.animate([{ transform: 'translate(' + dx + 'px,' + dy + 'px)' }, { transform: 'translate(0,0)' }], { duration: 280, easing: CURVA });
      });
    }

    // Posição na coluna: a mesma ordem da tela (número da OS, do maior para o menor).
    function inserir(col, el, numero) {
      var cs = col.querySelectorAll('.kanban__cartao');
      for (var i = 0; i < cs.length; i++) if (cs[i] !== el && +cs[i].dataset.numero < numero) return col.insertBefore(el, cs[i]);
      col.appendChild(el);
    }

    function contar() {
      colunas.forEach(function (c) {
        var n = c.querySelectorAll('.kanban__cartao').length;
        c.querySelector('.kanban__n').textContent = n;
        c.setAttribute('aria-label', c.dataset.rotulo + ': ' + n);
        var v = c.querySelector('.kanban__vazio');
        if (v) v.hidden = n > 0 || !!c.querySelector('.kanban__vaga');
      });
    }

    function marcarAtraso(cartao, sim) {
      var b = cartao.querySelector('.kanban__linha .badge--danger');
      if (sim && !b) { b = document.createElement('span'); b.className = 'badge badge--danger'; b.textContent = 'Atrasada'; cartao.querySelector('.kanban__linha').appendChild(b); }
      else if (!sim && b) b.remove();
    }

    /* ── Aviso rápido no pé da tela ── */
    var aviso = document.querySelector('.kb-aviso'), avisoTempo;
    function avisar(texto, tipo) {
      if (!aviso || !texto) return;
      aviso.className = 'kb-aviso kb-aviso--' + (tipo || 'sucesso');
      aviso.firstChild.textContent = texto;
      void aviso.offsetWidth;
      aviso.classList.add('is-visivel');
      clearTimeout(avisoTempo);
      avisoTempo = setTimeout(function () { aviso.classList.remove('is-visivel'); }, tipo === 'sucesso' ? 3200 : 6000);
    }

    /* ── Pegar ── */
    quadro.addEventListener('pointerdown', function (e) {
      if (e.button !== 0 || !e.isPrimary || s) return;
      var cartao = e.target.closest('.kanban__cartao');
      if (!cartao || cartao.classList.contains('is-salvando')) return;
      s = { cartao: cartao, id: e.pointerId, x0: e.clientX, y0: e.clientY, x: e.clientX, y: e.clientY, toque: e.pointerType !== 'mouse', ativo: false };
      // No toque, segurar um instante antes de arrastar: assim deslizar o dedo continua rolando a tela.
      if (s.toque) s.espera = setTimeout(function () { if (s && !s.ativo) comecar(); }, 280);
    });
    quadro.addEventListener('dragstart', function (e) { if (e.target.closest && e.target.closest('.kanban__cartao')) e.preventDefault(); });
    quadro.addEventListener('contextmenu', function (e) { if (s) e.preventDefault(); });
    quadro.addEventListener('click', function (e) { if (Date.now() < bloquearCliqueAte) { e.preventDefault(); e.stopPropagation(); } }, true);
    document.addEventListener('touchmove', function (e) { if (s && s.ativo) e.preventDefault(); }, { passive: false });

    document.addEventListener('pointermove', function (e) {
      if (!s || e.pointerId !== s.id) return;
      s.x = e.clientX; s.y = e.clientY;
      if (!s.ativo) {
        var d = Math.hypot(s.x - s.x0, s.y - s.y0);
        if (s.toque) { if (d > 10) { clearTimeout(s.espera); s = null; } } // o dedo está rolando a tela
        else if (d > 5) comecar();
        return;
      }
      e.preventDefault();
      acompanhar();
    }, { passive: false });
    document.addEventListener('pointerup', function (e) { if (s && e.pointerId === s.id) soltar(false); });
    document.addEventListener('pointercancel', function (e) { if (s && e.pointerId === s.id) soltar(true); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && s && s.ativo) { e.preventDefault(); soltar(true); } });
    window.addEventListener('blur', function () { if (s && s.ativo) soltar(true); });

    function rolagemVertical() {
      for (var el = quadro.parentElement; el && el !== document.body; el = el.parentElement) {
        var o = getComputedStyle(el).overflowY;
        if ((o === 'auto' || o === 'scroll') && el.scrollHeight > el.clientHeight) return el;
      }
      return document.scrollingElement || document.documentElement;
    }

    function comecar() {
      clearTimeout(s.espera);
      var c = s.cartao, r = c.getBoundingClientRect();
      s.ativo = true;
      s.origem = c.parentElement;
      s.de = c.dataset.status;
      s.numero = +c.dataset.numero;
      s.ox = s.x - r.left; s.oy = s.y - r.top;
      s.rolagem = rolagemVertical();

      var f = c.cloneNode(true);
      f.removeAttribute('href'); f.setAttribute('aria-hidden', 'true');
      f.className = 'kanban__cartao kanban__fantasma';
      f.style.width = r.width + 'px'; f.style.height = r.height + 'px';
      f.style.translate = r.left + 'px ' + r.top + 'px';
      document.body.appendChild(f);
      s.fantasma = f;

      s.vaga = document.createElement('div');
      s.vaga.className = 'kanban__vaga';
      s.vaga.style.height = r.height + 'px';
      s.vaga.style.borderRadius = getComputedStyle(c).borderRadius;

      c.classList.add('is-origem');
      quadro.classList.add('is-arrastando');
      document.documentElement.classList.add('kb-arrastando');
      colunas.forEach(function (col) {
        var st = col.dataset.status;
        col.classList.toggle('is-aceita', aceita(s.de, st));
        col.classList.toggle('is-bloqueada', st !== s.de && !aceita(s.de, st));
      });
      requestAnimationFrame(function () { f.classList.add('is-erguido'); });
      if (s.toque && navigator.vibrate) navigator.vibrate(10);
      acompanhar();
      requestAnimationFrame(rolarNasBordas);
    }

    function acompanhar() {
      s.fantasma.style.translate = (s.x - s.ox) + 'px ' + (s.y - s.oy) + 'px';
      var col = colunaSob(s.x, s.y);
      var alvo = col && col.classList.contains('is-aceita') ? col : null;
      if (alvo === s.alvo) return;
      if (s.alvo) s.alvo.classList.remove('is-alvo');
      var antiga = s.vaga.parentElement;
      s.alvo = alvo;
      if (alvo) {
        alvo.classList.add('is-alvo');
        comFlip([antiga, alvo], function () { inserir(alvo, s.vaga, s.numero); contar(); });
        if (!semMovimento) s.vaga.animate([{ opacity: 0, transform: 'scale(.97)' }, { opacity: 1, transform: 'none' }], { duration: 180, easing: CURVA });
      } else if (antiga) {
        comFlip([antiga], function () { s.vaga.remove(); contar(); });
      }
    }

    // Perto das bordas, a tela rola sozinha (para os lados no Kanban, para cima e para baixo na página).
    function rolarNasBordas() {
      if (!s || !s.ativo) return;
      var r = quadro.getBoundingClientRect(), borda = 56, vx = 0, vy = 0;
      if (quadro.scrollWidth > quadro.clientWidth) {
        if (s.x < r.left + borda) vx = -Math.ceil((r.left + borda - s.x) / 4);
        else if (s.x > r.right - borda) vx = Math.ceil((s.x - (r.right - borda)) / 4);
      }
      if (s.y < 72) vy = -Math.ceil((72 - s.y) / 5);
      else if (s.y > window.innerHeight - 72) vy = Math.ceil((s.y - (window.innerHeight - 72)) / 5);
      if (vx) quadro.scrollLeft += Math.max(-24, Math.min(24, vx));
      if (vy) s.rolagem.scrollTop += Math.max(-20, Math.min(20, vy));
      if (vx || vy) acompanhar();
      requestAnimationFrame(rolarNasBordas);
    }

    /* ── Soltar ── */
    function soltar(cancelado) {
      var a = s; s = null;
      clearTimeout(a.espera);
      if (!a.ativo) return; // foi só um clique: o link abre a OS
      bloquearCliqueAte = Date.now() + 400;
      quadro.classList.remove('is-arrastando');
      document.documentElement.classList.remove('kb-arrastando');
      colunas.forEach(function (c) { c.classList.remove('is-aceita', 'is-bloqueada', 'is-alvo'); });

      var alvo = cancelado ? null : a.alvo;
      if (!alvo) {
        var sob = cancelado ? null : colunaSob(a.x, a.y);
        if (a.vaga.parentElement) comFlip([a.vaga.parentElement], function () { a.vaga.remove(); contar(); });
        voltar(a);
        if (sob && sob !== a.origem) {
          var destinos = Object.keys(MOV).filter(function (k) { return k.indexOf(a.de + '>') === 0; }).map(function (k) { return rotuloDe(k.split('>')[1]); });
          avisar('Não dá para mover de ' + rotuloDe(a.de) + ' para ' + sob.dataset.rotulo + '.' + (destinos.length ? ' De ' + rotuloDe(a.de) + ', a OS vai para ' + destinos.join(' ou ') + '.' : ''), 'aviso');
        }
        return;
      }

      var op = MOV[a.de + '>' + alvo.dataset.status];
      pousar(a, alvo);
      if (op === 'aprovar' || op === 'retomar') {
        confirmar(op, a.cartao).then(function (dados) { if (dados) salvar(a, alvo, dados); else devolver(a); });
      } else {
        salvar(a, alvo, {});
      }
    }

    // O cartão "fantasma" desce até o lugar e some; o cartão de verdade aparece no lugar dele.
    function aterrissar(f, destino, fim) {
      if (semMovimento) { f.remove(); fim(); return; }
      var r = destino.getBoundingClientRect();
      var anim = f.animate([
        { translate: f.style.translate, transform: getComputedStyle(f).transform },
        { translate: r.left + 'px ' + r.top + 'px', transform: 'none' }
      ], { duration: 260, easing: CURVA, fill: 'forwards' });
      anim.onfinish = function () { f.remove(); fim(); };
    }

    function voltar(a) {
      aterrissar(a.fantasma, a.cartao, function () { a.cartao.classList.remove('is-origem'); });
    }

    function pousar(a, alvo) {
      var c = a.cartao;
      c.classList.add('is-chegando');
      comFlip([a.origem, alvo], function () { a.vaga.replaceWith(c); c.classList.remove('is-origem'); contar(); });
      c.classList.add('is-salvando');
      aterrissar(a.fantasma, c, function () { c.classList.remove('is-chegando'); });
    }

    // Desfaz: o cartão volta para a coluna de onde saiu.
    function devolver(a) {
      var c = a.cartao;
      c.classList.remove('is-salvando');
      comFlip([c.parentElement, a.origem], function () { inserir(a.origem, c, a.numero); contar(); });
    }

    function confirmar(op, cartao) {
      var dlg = document.getElementById(op === 'aprovar' ? 'dlg-kb-aprovar' : 'dlg-kb-retomar');
      if (!dlg) return Promise.resolve({});
      var form = dlg.querySelector('form');
      form.reset();
      dlg.querySelector('[data-kb-os]').textContent = cartao.dataset.os + ' · ' + cartao.dataset.cliente + (op === 'aprovar' ? ' · ' + cartao.dataset.total : '');
      if (form.elements.aprovado_por) form.elements.aprovado_por.value = cartao.dataset.cliente;
      return new Promise(function (resolver) {
        dlg.returnValue = '';
        dlg.addEventListener('close', function fim() {
          dlg.removeEventListener('close', fim);
          var dados = {};
          new FormData(form).forEach(function (v, k) { dados[k] = v; });
          resolver(dlg.returnValue === 'ok' ? dados : null);
        });
        dlg.showModal();
      });
    }
    document.querySelectorAll('[data-kb-cancelar]').forEach(function (b) { b.addEventListener('click', function () { b.closest('dialog').close(''); }); });

    function salvar(a, alvo, extras) {
      var c = a.cartao, fd = new FormData();
      fd.append('csrf_token', quadro.dataset.token);
      fd.append('de', a.de);
      fd.append('para', alvo.dataset.status);
      Object.keys(extras).forEach(function (k) { fd.append(k, extras[k]); });
      c.classList.add('is-salvando');
      c.setAttribute('aria-busy', 'true');

      fetch(quadro.dataset.mover + '?id=' + encodeURIComponent(c.dataset.id), {
        method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' }
      })
        .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { j.httpOk = r.ok; return j; }); })
        .then(function (j) {
          c.classList.remove('is-salvando');
          c.removeAttribute('aria-busy');
          if (!(j.httpOk && j.ok)) {
            devolver(a);
            avisar(j.mensagem || 'Não foi possível mover a OS. Tente de novo.', 'erro');
            return;
          }
          c.dataset.status = j.status;
          marcarAtraso(c, !!j.atrasada);
          var final = colunaDe(j.status);
          if (final && final !== c.parentElement) comFlip([c.parentElement, final], function () { inserir(final, c, a.numero); contar(); });
          else if (!final) { c.remove(); contar(); }
          c.classList.remove('is-confirmado'); void c.offsetWidth; c.classList.add('is-confirmado');
          avisar(j.mensagem, j.tipo === 'aviso' ? 'aviso' : 'sucesso');
        })
        .catch(function () {
          c.classList.remove('is-salvando');
          c.removeAttribute('aria-busy');
          devolver(a);
          avisar('Sem conexão com o servidor. A OS não foi movida.', 'erro');
        });
    }
  })();
</script>
