<?php
/**
 * Ícone da aba do navegador. Segue o tema do COMPUTADOR (prefers-color-scheme), não o tema escolhido
 * no sistema: a aba fica na barra do navegador, que acompanha o sistema operacional.
 * Computador no escuro → marca clara; no claro → marca escura. Troca na hora se o tema mudar.
 * A marca ocupa quase toda a largura do quadrado, com 1/16 de respiro em cada lado (nada encosta na borda, que o navegador corta ao redimensionar) e há um arquivo por tamanho
 * (16, 32 e 64 px), reduzido a partir do símbolo original para ficar nítido na aba.
 */
$favV = '?v=3';
?>
<?php foreach ([16, 32, 64] as $lado): $suf = $lado === 64 ? '' : '-' . $lado; ?>
<link rel="icon" type="image/png" sizes="<?= $lado ?>x<?= $lado ?>" href="./img/favicon-escuro<?= $suf ?>.png<?= $favV ?>" media="(prefers-color-scheme: light)">
<link rel="icon" type="image/png" sizes="<?= $lado ?>x<?= $lado ?>" href="./img/favicon-claro<?= $suf ?>.png<?= $favV ?>" media="(prefers-color-scheme: dark)">
<?php endforeach; ?>
<link rel="apple-touch-icon" href="./img/apple-touch-icon.png">
<script>
  (function () {
    var q = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)');
    if (!q) return;
    function aplicar() {
      // Alguns navegadores só leem o ícone uma vez e ignoram "media": troca pelos links do tema atual.
      document.querySelectorAll('link[rel="icon"]').forEach(function (l) { l.remove(); });
      var cor = q.matches ? 'claro' : 'escuro';
      [[16, '-16'], [32, '-32'], [64, '']].forEach(function (t) {
        var l = document.createElement('link');
        l.rel = 'icon'; l.type = 'image/png'; l.sizes = t[0] + 'x' + t[0];
        l.href = './img/favicon-' + cor + t[1] + '.png<?= $favV ?>';
        document.head.appendChild(l);
      });
    }
    aplicar();
    if (q.addEventListener) q.addEventListener('change', aplicar); else if (q.addListener) q.addListener(aplicar);
  })();
</script>
