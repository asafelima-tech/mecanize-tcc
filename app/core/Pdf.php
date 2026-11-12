<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Geração de PDF a partir de HTML, via Dompdf (instalado com Composer).
 * Usado pelos documentos do sistema: OS, orçamento e relatório de caixa.
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
        $dompdf = self::montar($html);

        $dompdf->stream($nomeArquivo, ['Attachment' => $attachment]);
    }

    /**
     * Mesmo documento, devolvido como string (para testes e para quem
     * precisa do binário sem mandar direto pro navegador).
     */
    public static function gerar(string $html): string
    {
        return self::montar($html)->output();
    }

    private static function montar(string $html): Dompdf
    {
        $options = new Options();

        // Acesso remoto DESLIGADO. Com ele ligado, uma URL que aparecesse no
        // HTML do documento faria o servidor buscá-la sozinho — inclusive
        // endereços internos da rede, com o PDF servindo de intermediário
        // (SSRF). Testado: assinatura do cliente (data: URI) e logotipo
        // (arquivo local) continuam saindo idênticos, porque nenhum dos dois
        // depende de rede.
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        // Por segurança o Dompdf só lê arquivos de dentro do próprio diretório
        // (chroot). Sem liberar a pasta pública, a logomarca do timbre era
        // silenciosamente descartada — o documento saía sem marca nenhuma e
        // sem erro. Só a pasta de imagens é liberada, nada mais do projeto.
        $options->setChroot([realpath(__DIR__ . '/../../public/assets/img')]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        self::numerarPaginas($dompdf);

        return $dompdf;
    }

    /**
     * Carimba "Página X de Y" no rodapé de todas as páginas.
     *
     * Não dá para fazer isso só com CSS: o counter(pages) do Dompdf devolve
     * zero ("Página 1 de 0"), porque o total de páginas só existe depois que
     * o documento inteiro foi paginado. O carimbo pelo Canvas acontece
     * justamente nesse momento — e, morando aqui, vale para todo documento
     * que passar pelo Pdf, sem cada template ter que lembrar.
     *
     * As coordenadas casam com o rodapé em HTML de _docPdfRodape.php (mesma
     * linha de base, alinhado à direita, dentro da margem inferior de 64px
     * reservada em _docPdfEstilos.php).
     */
    private static function numerarPaginas(Dompdf $dompdf): void
    {
        $canvas = $dompdf->getCanvas();
        $fonte  = $dompdf->getFontMetrics()->getFont('Helvetica');

        if (!$fonte) {
            return;
        }

        $tamanho = 8.5;
        $texto   = 'Página {PAGE_NUM} de {PAGE_COUNT}';

        // Largura estimada com os placeholders já preenchidos: sem isso o
        // texto sai deslocado para fora da margem direita.
        $largura = $dompdf->getFontMetrics()->getTextWidth(
            str_replace(['{PAGE_NUM}', '{PAGE_COUNT}'], ['00', '00'], $texto),
            $fonte,
            $tamanho
        );

        $margemDireita = 25.5;  // 34px do @page convertidos para pontos
        $alturaRodape  = 30;    // distância da base do papel até a linha do rodapé

        $canvas->page_text(
            $canvas->get_width() - $margemDireita - $largura,
            $canvas->get_height() - $alturaRodape,
            $texto,
            $fonte,
            $tamanho,
            [0.58, 0.64, 0.72]
        );
    }
}
