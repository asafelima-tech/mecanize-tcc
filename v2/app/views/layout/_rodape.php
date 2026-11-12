<?php
/**
 * Rodapé da tabela (tela 14 do Design System): "Mostrando 20 de 45 ordens" e os botões Anterior/Próxima.
 * Espera: $p (Paginacao). Opcional: $rotuloItens (ex.: 'ordens em andamento'; padrão 'registros').
 */
$rotuloItens = $rotuloItens ?? 'registros';
$naPagina    = max(0, min($p->limite(), (int) $p->total - $p->deslocamento()));
?>
<div class="rodape-tabela">
  <span>Mostrando <?= $naPagina ?> de <?= (int) $p->total ?> <?= e($rotuloItens) ?><?php if ($p->paginas() > 1): ?> · página <?= $p->pagina ?> de <?= $p->paginas() ?><?php endif; ?></span>
  <?php if ($p->paginas() > 1): ?>
    <nav class="rodape-tabela__nav" aria-label="Páginas">
      <?php if ($p->pagina > 1): ?>
        <a class="btn btn--ghost btn--sm" href="<?= e(url_com(['pagina' => $p->pagina - 1 > 1 ? $p->pagina - 1 : null])) ?>" rel="prev">Anterior</a>
      <?php else: ?>
        <span class="btn btn--ghost btn--sm" aria-disabled="true">Anterior</span>
      <?php endif; ?>
      <?php if ($p->pagina < $p->paginas()): ?>
        <a class="btn btn--ghost btn--sm" href="<?= e(url_com(['pagina' => $p->pagina + 1])) ?>" rel="next">Próxima</a>
      <?php else: ?>
        <span class="btn btn--ghost btn--sm" aria-disabled="true">Próxima</span>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</div>
