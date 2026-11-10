<?php /** Espera: $p (Paginacao). Rodapé da tabela: total e páginas. */ ?>
<div class="paginas">
  <span><?= (int) $p->total ?> registro(s)</span>
  <?php if ($p->paginas() > 1): ?>
    <span>
      <?php if ($p->pagina > 1): ?><a href="<?= e(url_com(['pagina' => $p->pagina - 1])) ?>" aria-label="Página anterior">‹</a><?php endif; ?>
      <?php for ($i = max(1, $p->pagina - 2); $i <= min($p->paginas(), $p->pagina + 2); $i++): ?>
        <?php if ($i === $p->pagina): ?><span class="atual"><?= $i ?></span><?php else: ?><a href="<?= e(url_com(['pagina' => $i])) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
      <?php if ($p->pagina < $p->paginas()): ?><a href="<?= e(url_com(['pagina' => $p->pagina + 1])) ?>" aria-label="Próxima página">›</a><?php endif; ?>
    </span>
  <?php endif; ?>
</div>
