/* =========================================================
   Select de cor do veículo (Tom Select com bolinha de cor)
   Inicializa o #corSelect das telas de cadastrar/editar veículo.
   Usa window.CORES_CARRO (nome => hex), emitido pela view.
   Seguro de rodar em qualquer página: sai cedo se não houver o select.
   ========================================================= */
(function () {
  function hexDaCor(cores, valor) {
    if (!valor) return null;
    if (cores[valor]) return cores[valor];
    const alvo = String(valor).toLowerCase();
    for (var nome in cores) {
      if (Object.prototype.hasOwnProperty.call(cores, nome) && nome.toLowerCase() === alvo) {
        return cores[nome];
      }
    }
    return null;
  }

  function bolinha(hex) {
    const cor = hex || '#CBD5E1';
    return '<span style="display:inline-block;width:12px;height:12px;border-radius:50%;'
      + 'background:' + cor + ';border:1px solid rgba(128,128,128,.45);'
      + 'margin-right:8px;flex-shrink:0;"></span>';
  }

  function initCorSelect() {
    if (typeof TomSelect === 'undefined') return;

    const el = document.getElementById('corSelect');
    if (!el || el.tomselect) return;

    const cores = window.CORES_CARRO || {};

    new TomSelect(el, {
      create: false,
      allowEmptyOption: true,
      render: {
        option: function (data, escape) {
          return '<div style="display:flex;align-items:center;">'
            + bolinha(hexDaCor(cores, data.value)) + escape(data.text) + '</div>';
        },
        item: function (data, escape) {
          if (!data.value) return '<div>' + escape(data.text) + '</div>';
          return '<div style="display:flex;align-items:center;">'
            + bolinha(hexDaCor(cores, data.value)) + escape(data.text) + '</div>';
        }
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCorSelect);
  } else {
    initCorSelect();
  }
})();
