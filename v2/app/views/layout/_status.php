<?php
/**
 * Filtro de situação (ativos/inativos/todos) para a barra de filtros. Espera: $f.
 */
?>
<select name="status" aria-label="Situação">
  <?php foreach (['ativos' => 'Ativos', 'inativos' => 'Inativos', 'todos' => 'Todos'] as $v => $r): ?>
    <option value="<?= $v ?>" <?= ($f['status'] ?? 'ativos') === $v ? 'selected' : '' ?>><?= $r ?></option>
  <?php endforeach; ?>
</select>
