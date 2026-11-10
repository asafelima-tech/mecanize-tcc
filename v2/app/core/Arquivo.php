<?php

/**
 * Envia um arquivo guardado (anexo, logotipo) para o navegador. O tipo vem do CONTEÚDO (conferido no
 * envio: só PDF e imagem) e o navegador é proibido de "adivinhar" outro (nosniff).
 */
final class Arquivo
{
    public static function enviar(string $caminho, string $mime, string $nome, bool $baixar = false): never
    {
        $nome = preg_replace('/[^\w.\- ]+/u', '_', $nome) ?: 'arquivo';
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($caminho));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        header('Content-Disposition: ' . ($baixar ? 'attachment' : 'inline') . '; filename="' . $nome . '"; filename*=UTF-8\'\'' . rawurlencode($nome));
        readfile($caminho);
        exit;
    }
}
