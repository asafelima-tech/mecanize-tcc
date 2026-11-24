<?php
/**
 * Campo "Peça do catálogo" do técnico: digita parte do nome ou do código e toca na peça.
 * Só nome, marca e código (sem saldo nem preço). Grava o id num campo escondido.
 * Espera: $pecaId (id único do campo), $catalogo. Opcional: $pecaObrigatoria (bool), $pecaRotulo.
 * O script fica em tecnico/_peca_script.php (uma vez por página).
 */
$pecaObrigatoria = $pecaObrigatoria ?? false;
?>
<div class="tec-peca" data-tec-peca>
  <label class="tec-campo__rotulo" for="<?= e($pecaId) ?>-busca"><?= e($pecaRotulo ?? 'Peça ou material') ?></label>
  <input type="hidden" name="produto_id" value="" <?= $pecaObrigatoria ? 'data-obrigatorio' : '' ?>>
  <div class="tec-peca__busca">
    <?= icone('search', 18) ?>
    <input id="<?= e($pecaId) ?>-busca" type="search" autocomplete="off" enterkeyhint="search" placeholder="Digite o nome ou o código"
           aria-describedby="<?= e($pecaId) ?>-ajuda" aria-controls="<?= e($pecaId) ?>-lista" aria-expanded="false">
  </div>
  <ul class="tec-peca__lista" id="<?= e($pecaId) ?>-lista" role="listbox" hidden></ul>
  <p class="tec-peca__escolhida" hidden><span></span><button type="button" class="tec-link" data-trocar>Trocar</button></p>
  <p class="tec-campo__dica" id="<?= e($pecaId) ?>-ajuda"><?= count($catalogo) ?> peças no catálogo.</p>
</div>
<?php unset($pecaObrigatoria, $pecaRotulo);
