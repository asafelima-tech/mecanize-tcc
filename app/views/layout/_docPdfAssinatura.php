<?php
/**
 * Imagem da assinatura coletada na tela, para o bloco de assinaturas do PDF.
 *
 * Existe por causa de uma falha real: o Dompdf embute PNG através da extensão
 * GD e, quando ela não está habilitada no PHP, lança
 * "The PHP GD extension is required" — derrubando o documento INTEIRO. Uma
 * assinatura que não pôde ser desenhada não pode custar a ordem de serviço:
 * sem GD, sai a linha para assinar à mão e o documento continua sendo gerado.
 *
 * Variável esperada:
 *   $docAssinatura  string  data URI ou caminho da imagem (pode vir vazio)
 */

$docAssinatura = trim((string) ($docAssinatura ?? ''));

if ($docAssinatura === '') {
    return;
}

if (!extension_loaded('gd')) {
    error_log(
        'Assinatura não embutida no PDF: a extensão GD do PHP não está '
        . 'habilitada (o Dompdf precisa dela para PNG). Habilite extension=gd no php.ini.'
    );
    return;
}
?>
<img src="<?= htmlspecialchars($docAssinatura) ?>" style="max-height:60px;display:block;margin:0 auto 4px;"><br>
