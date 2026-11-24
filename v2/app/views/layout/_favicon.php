<?php
/**
 * Marca do Mecanize: logos recortadas e ícone da aba, a partir de img/logo.png (preta) e img/logo-branco.png.
 * Os arquivos vêm com margem larga em volta da palavra; o navegador recorta na hora:
 *   <img data-recorte="logo">    só a palavra, sem a margem (a altura continua vindo do CSS de cada tela);
 *   <img data-recorte="simbolo"> só o "m" da marca (barra lateral recolhida, marca-d'água do login).
 * O ícone da aba é o "m" num quadrado e segue o tema do COMPUTADOR (prefers-color-scheme), não o do
 * sistema: computador no escuro → marca clara; no claro → marca escura. Troca na hora se o tema mudar.
 * $logoV entra no endereço das logos para o navegador não mostrar uma versão antiga guardada.
 */
$logoV = '?v=' . max((int) @filemtime(V2_RAIZ . '/public/img/logo.png'), (int) @filemtime(V2_RAIZ . '/public/img/logo-branco.png'));
?>
<link rel="apple-touch-icon" href="./img/logo.png<?= $logoV ?>">
<script>
  (function () {
    var V = '<?= $logoV ?>';
    // Até o recorte ficar pronto a logo não aparece (sem "piscar" pequena). Sem JavaScript, nada é escondido.
    var estilo = document.createElement('style');
    estilo.textContent = 'img[data-recorte]:not(.is-recortada){visibility:hidden}';
    document.head.appendChild(estilo);

    // Lê a imagem uma vez e acha onde está a tinta: a palavra inteira e o primeiro desenho (o "m").
    var lidas = {};
    function medir(src) {
      if (lidas[src]) return lidas[src];
      lidas[src] = new Promise(function (ok, falha) {
        var img = new Image();
        img.onload = function () {
          try {
            var w = img.naturalWidth, h = img.naturalHeight, c = document.createElement('canvas');
            c.width = w; c.height = h;
            var ctx = c.getContext('2d');
            ctx.drawImage(img, 0, 0);
            var d = ctx.getImageData(0, 0, w, h).data;
            var f = [d[0], d[1], d[2], d[3]];                     // fundo: o canto de cima à esquerda
            var tinta = function (x, y) {
              var i = (y * w + x) * 4;
              if (d[i + 3] < 64) return false;
              if (f[3] < 128) return true;                       // fundo transparente
              return Math.abs(d[i] - f[0]) + Math.abs(d[i + 1] - f[1]) + Math.abs(d[i + 2] - f[2]) > 120;
            };
            var col = new Uint8Array(w), x0 = w, x1 = -1, y0 = h, y1 = -1, x, y;
            for (y = 0; y < h; y++) for (x = 0; x < w; x++) {
              if (!tinta(x, y)) continue;
              col[x] = 1;
              if (x < x0) x0 = x; if (x > x1) x1 = x; if (y < y0) y0 = y; if (y > y1) y1 = y;
            }
            if (x1 < 0) throw new Error('sem marca');
            // O "m" vai do começo da palavra até a primeira coluna vazia (o espaço antes do "e").
            var mx1 = x1;
            for (x = x0 + Math.round((y1 - y0) * .5); x <= x1; x++) if (!col[x]) { mx1 = x - 1; break; }
            var my0 = h, my1 = -1;
            for (y = y0; y <= y1; y++) for (x = x0; x <= mx1; x++) if (tinta(x, y)) { if (y < my0) my0 = y; my1 = y; break; }
            var folga = 1;
            var caixa = function (a, b, c2, d2) {
              a = Math.max(0, a - folga); b = Math.max(0, b - folga);
              return [a, b, Math.min(w, c2 + folga + 1) - a, Math.min(h, d2 + folga + 1) - b];
            };
            ok({ img: img, logo: caixa(x0, y0, x1, y1), simbolo: caixa(x0, my0, mx1, my1) });
          } catch (e) { falha(e); }
        };
        img.onerror = falha;
        img.src = src;
      });
      return lidas[src];
    }

    // Recorte como imagem: do tamanho exato (lado vazio) ou centralizado num quadrado com respiro.
    function recortar(m, r, lado, fundo) {
      var c = document.createElement('canvas'), ctx;
      if (!lado) {
        c.width = r[2]; c.height = r[3];
        c.getContext('2d').drawImage(m.img, r[0], r[1], r[2], r[3], 0, 0, r[2], r[3]);
        return c.toDataURL('image/png');
      }
      c.width = c.height = lado;
      ctx = c.getContext('2d');
      if (fundo) { ctx.fillStyle = fundo; ctx.fillRect(0, 0, lado, lado); }
      var respiro = lado / 16 * (fundo ? 3 : 1), esc = (lado - 2 * respiro) / Math.max(r[2], r[3]);
      ctx.imageSmoothingEnabled = true;
      ctx.imageSmoothingQuality = 'high';
      ctx.drawImage(m.img, r[0], r[1], r[2], r[3], (lado - r[2] * esc) / 2, (lado - r[3] * esc) / 2, r[2] * esc, r[3] * esc);
      return c.toDataURL('image/png');
    }

    // Logos da página.
    function logos() {
      document.querySelectorAll('img[data-recorte]').forEach(function (el) {
        var tipo = el.getAttribute('data-recorte') === 'simbolo' ? 'simbolo' : 'logo';
        medir(el.getAttribute('src')).then(function (m) {
          el.src = recortar(m, m[tipo]);
          el.classList.add('is-recortada');
        }, function () { el.classList.add('is-recortada'); });
      });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', logos); else logos();

    // Ícone da aba (16, 32 e 64 px) e da tela inicial do iPhone (fundo branco, 180 px).
    var q = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)');
    function icone() {
      medir('./img/' + (q && q.matches ? 'logo-branco' : 'logo') + '.png' + V).then(function (m) {
        document.querySelectorAll('link[rel="icon"]').forEach(function (l) { l.remove(); });
        [16, 32, 64].forEach(function (lado) {
          var l = document.createElement('link');
          l.rel = 'icon'; l.type = 'image/png'; l.sizes = lado + 'x' + lado;
          l.href = recortar(m, m.simbolo, lado);
          document.head.appendChild(l);
        });
      }, function () {});
    }
    icone();
    if (q) { if (q.addEventListener) q.addEventListener('change', icone); else if (q.addListener) q.addListener(icone); }
    medir('./img/logo.png' + V).then(function (m) {
      var l = document.querySelector('link[rel="apple-touch-icon"]');
      if (l) l.href = recortar(m, m.simbolo, 180, '#FFFFFF');
    }, function () {});
  })();
</script>
