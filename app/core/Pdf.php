<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Geração de PDF a partir de HTML, via Dompdf (instalado com Composer).
 * Usado pelos botões "Baixar PDF" de Orçamento e Ordem de Serviço.
 */
class Pdf
{
    public static function baixar(string $html, string $nomeArquivo): void
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream($nomeArquivo, ['Attachment' => true]);
    }
}
