<?php
/**
 * Rodapé das telas impressas — par do _docImpressoCabecalho.php.
 *
 * Fecha o documento dizendo de onde ele veio e quando foi gerado, que é o
 * mínimo para uma folha que vai ser arquivada ou entregue. A numeração de
 * página fica por conta do navegador (Chrome/Edge imprimem "1/3" no rodapé
 * do papel); nos documentos gerados em PDF pelo servidor a numeração é
 * própria, com "Página X de Y".
 */
?>

<div class="doc-print doc-print__rodape" style="display:none;" aria-hidden="true">
    <?= htmlspecialchars(EMPRESA_NOME) ?> ·
    documento gerado pelo sistema Mecanize em <?= date('d/m/Y \à\s H:i') ?>
</div>
