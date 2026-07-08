/* =========================================================
   Realça TODO <select> de formulário com Tom Select, dando o
   mesmo painel/dropdown bonito do select de cor (cantos
   arredondados, itens com hover, busca).

   - Pula #corSelect (tem init próprio com bolinha em cor-select.js),
     os filtros compactos (.toolbar-select) e quem optar por sair
     com data-no-ts.
   - Um MutationObserver mantém o Tom Select sincronizado quando as
     <option> do <select> nativo são trocadas via innerHTML (cascata
     marca→modelo→combustível e quaisquer outros selects dinâmicos),
     sem precisar tocar no JS de cada tela. O observer é desligado
     durante a reconstrução para evitar laço infinito.
   ========================================================= */
(function () {
  function reconstruir(ts, el) {
    var valor = el.value;

    ts.clear(true);        // limpa seleção (silencioso)
    ts.clearOptions();     // remove todas as opções atuais

    var opcoes = [];
    for (var i = 0; i < el.options.length; i++) {
      var o = el.options[i];
      opcoes.push({ value: o.value, text: o.text });
    }
    ts.addOptions(opcoes);
    ts.refreshOptions(false);
    ts.setValue(valor, true); // restaura o valor sem disparar change
  }

  function enhance(el) {
    if (!el || el.tomselect) return;
    if (el.id === 'corSelect') return;                  // init próprio (bolinha de cor)
    if (el.classList.contains('toolbar-select')) return; // filtros compactos: ficam nativos
    if (el.hasAttribute('data-no-ts')) return;
    if (el.multiple) return;

    var ts = new TomSelect(el, {
      create: false,
      allowEmptyOption: true
    });

    var obs = new MutationObserver(function () {
      obs.disconnect();
      reconstruir(ts, el);
      obs.observe(el, { childList: true });
    });
    obs.observe(el, { childList: true });
  }

  function enhanceAll() {
    if (typeof TomSelect === 'undefined') return;
    var selects = document.querySelectorAll('select');
    for (var i = 0; i < selects.length; i++) enhance(selects[i]);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', enhanceAll);
  } else {
    enhanceAll();
  }
})();
