<?php
/**
 * Rodapé fixo dos documentos em PDF: repete em TODAS as páginas (é o que o
 * position:fixed faz no Dompdf).
 *
 * A numeração "Página X de Y" NÃO sai daqui: o counter(pages) do Dompdf
 * devolve zero, porque o total só existe depois da paginação. Ela é
 * carimbada pelo Pdf::numerarPaginas() no canto direito desta mesma linha.
 *
 * Precisa ficar logo no início do <body>, antes do conteúdo — o Dompdf
 * posiciona o bloco fixo a partir dali para todas as páginas.
 *
 * Variável opcional:
 *   $docRodapeNota  string  linha extra à esquerda (ex.: validade do orçamento)
 */

$docRodapeNota = $docRodapeNota ?? '';
?>

<div class="doc-rodape">
    <table>
        <tr>
            <td>
                <?= htmlspecialchars(EMPRESA_NOME) ?> ·
                documento gerado pelo sistema Mecanize
                <?= $docRodapeNota !== '' ? ' · ' . htmlspecialchars($docRodapeNota) : '' ?>
            </td>
            <!-- Coluna reservada para o carimbo de numeração (Pdf.php) -->
            <td class="dir">&nbsp;</td>
        </tr>
    </table>
</div>
