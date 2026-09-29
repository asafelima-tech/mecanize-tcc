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
    /**
     * $attachment = true força o download ("Baixar PDF"); false abre o PDF
     * inline no visualizador do navegador (usado pra "Imprimir" e pra prévia
     * embutida em iframe, já que o próprio visualizador de PDF do navegador
     * tem botão de imprimir/salvar).
     */
    public static function baixar(string $html, string $nomeArquivo, bool $attachment = true): void
    {
        $options = new Options();

        /*
         * Só imagem embutida no próprio HTML (data:), que é o formato da
         * assinatura desenhada pelo cliente na tela de conclusão da OS.
         *
         * Por padrão o Dompdf também aceita file://, http:// e https:// numa
         * tag <img>. Isso significa que qualquer texto que chegasse ao HTML do
         * documento sem escape poderia mandar o SERVIDOR abrir um arquivo do
         * disco dele (o .env, por exemplo) e desenhá-lo dentro do PDF, ou
         * fazer requisições para endereços internos da rede. Hoje as views de
         * PDF escapam tudo, mas limitar o protocolo aqui faz a proteção deixar
         * de depender disso.
         *
         * A opção 'isRemoteEnabled' que existia aqui não fazia mais efeito: no
         * Dompdf 3.x ela virou legado e nada no pacote a consulta — quem manda
         * é esta lista de protocolos.
         */
        $options->setAllowedProtocols(['data://' => ['rules' => []]]);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream($nomeArquivo, ['Attachment' => $attachment]);
    }
}
