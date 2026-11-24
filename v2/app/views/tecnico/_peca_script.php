<?php
/**
 * Comportamento dos campos de peça (tecnico/_peca.php), dos botões − e + de quantidade e dos
 * formulários que exigem peça. Espera: $catalogo (id, nome, codigo_interno, marca).
 */
$lista = array_map(fn($p) => ['i' => (int) $p['id'], 'n' => $p['nome'], 'c' => (string) ($p['codigo_interno'] ?? ''), 'm' => (string) ($p['marca'] ?? '')], $catalogo);
?>
<script>
  (function () {
    var PECAS = <?= json_encode($lista, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var sem = function (t) { return String(t || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
    PECAS.forEach(function (p) { p.b = sem(p.n + ' ' + p.c + ' ' + p.m); });

    document.querySelectorAll('[data-tec-peca]').forEach(function (caixa) {
      var oculto = caixa.querySelector('input[type=hidden]'), busca = caixa.querySelector('input[type=search]');
      var lista = caixa.querySelector('.tec-peca__lista'), escolhida = caixa.querySelector('.tec-peca__escolhida');
      var mostrar = function (abrir) { lista.hidden = !abrir; busca.setAttribute('aria-expanded', abrir ? 'true' : 'false'); };
      var escolher = function (p) {
        oculto.value = p.i;
        escolhida.querySelector('span').textContent = p.n + (p.c ? ' · ' + p.c : '');
        escolhida.hidden = false; busca.parentNode.hidden = true; mostrar(false);
        oculto.dispatchEvent(new Event('change', { bubbles: true }));
      };
      busca.addEventListener('input', function () {
        var q = sem(busca.value).trim(), achou = [];
        if (q.length) {
          var termos = q.split(/\s+/);
          for (var i = 0; i < PECAS.length && achou.length < 8; i++) {
            if (termos.every(function (t) { return PECAS[i].b.indexOf(t) !== -1; })) achou.push(PECAS[i]);
          }
        }
        lista.innerHTML = '';
        achou.forEach(function (p) {
          var li = document.createElement('li'), bt = document.createElement('button');
          bt.type = 'button'; bt.setAttribute('role', 'option');
          bt.innerHTML = '<strong></strong><small></small>';
          bt.querySelector('strong').textContent = p.n;
          bt.querySelector('small').textContent = [p.m, p.c].filter(Boolean).join(' · ');
          bt.addEventListener('click', function () { escolher(p); });
          li.appendChild(bt); lista.appendChild(li);
        });
        if (q.length && !achou.length) {
          var li = document.createElement('li'); li.className = 'tec-peca__nada';
          li.textContent = 'Nenhuma peça com esse nome. Se ela não é cadastrada, use "Pedir ao administrador".';
          lista.appendChild(li);
        }
        mostrar(q.length > 0);
      });
      escolhida.querySelector('[data-trocar]').addEventListener('click', function () {
        oculto.value = ''; escolhida.hidden = true; busca.parentNode.hidden = false; busca.value = ''; busca.focus();
      });
    });

    // Quantidade: botões − e + grandes (o teclado numérico continua valendo).
    document.querySelectorAll('[data-tec-qtd]').forEach(function (caixa) {
      var campo = caixa.querySelector('input');
      caixa.querySelectorAll('[data-passo]').forEach(function (bt) {
        bt.addEventListener('click', function () {
          var v = parseFloat(String(campo.value).replace(',', '.')) || 0;
          v = Math.max(1, Math.round((v + Number(bt.dataset.passo)) * 1000) / 1000);
          campo.value = String(v).replace('.', ',');
        });
      });
    });

    // Formulário que exige peça escolhida da lista.
    document.querySelectorAll('form').forEach(function (f) {
      f.addEventListener('submit', function (e) {
        var o = f.querySelector('[data-tec-peca] input[data-obrigatorio]');
        if (o && !o.value) { e.preventDefault(); var b = f.querySelector('[data-tec-peca] input[type=search]'); b.focus(); b.setCustomValidity('Escolha a peça na lista.'); b.reportValidity(); setTimeout(function () { b.setCustomValidity(''); }, 2000); }
      });
    });
  })();
</script>
