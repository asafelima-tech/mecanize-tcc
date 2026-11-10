<?php
/**
 * Botões "Desativar/Reativar" e "Excluir" de uma ficha. Espera: $rotaAtivo, $rotaExcluir (ou null),
 * $registro (com id e ativo), $nome ("o cliente").
 */
?>
<form method="post" action="./<?= e($rotaAtivo) ?>?id=<?= (int) $registro['id'] ?>"
      <?= (int) $registro['ativo'] ? 'data-confirmar="Desativar ' . e($nome) . '? Ele deixa de aparecer nas listas e nas escolhas, mas o histórico continua."' : '' ?>>
  <?= csrf_field() ?>
  <input type="hidden" name="ativo" value="<?= (int) $registro['ativo'] ? 0 : 1 ?>">
  <button class="btn btn--ghost btn--sm"><?= (int) $registro['ativo'] ? 'Desativar' : 'Reativar' ?></button>
</form>
<?php if ($rotaExcluir && Permissao::permite(Contexto::perfil(), Permissao::ADMIN)): ?>
  <form method="post" action="./<?= e($rotaExcluir) ?>?id=<?= (int) $registro['id'] ?>"
        data-confirmar="Excluir <?= e($nome) ?> definitivamente? Só é possível se não houver nada ligado a ele.">
    <?= csrf_field() ?>
    <button class="btn btn--danger-ghost btn--sm">Excluir</button>
  </form>
<?php endif; ?>
