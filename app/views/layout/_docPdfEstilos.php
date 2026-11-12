<?php
/**
 * Base de estilo compartilhada pelos documentos gerados em PDF (Dompdf):
 * OS, orçamento e relatório de caixa. Antes cada template repetia o mesmo
 * CSS colado, e cada um foi divergindo — margens, tamanhos de fonte e
 * regras de quebra diferentes em documentos que deveriam parecer da mesma
 * empresa.
 *
 * Restrições do Dompdf que ditam o formato daqui:
 *   - não resolve var(--cor)  → cores em hexadecimal literal;
 *   - não faz flexbox/grid    → colunas em <table>;
 *   - repete <thead> a cada quebra de página (é o que dá continuidade às
 *     tabelas longas) e respeita page-break-inside: avoid.
 *
 * A margem inferior do @page é o que reserva espaço para o rodapé fixo com
 * a numeração — ver _docPdfRodape.php. O espaçamento geral é enxuto de
 * propósito: documento de uma OS curta tem que caber numa folha só, em vez
 * de jogar a garantia e as assinaturas sozinhas numa segunda página.
 */
?>
<style>
    /* ATENÇÃO: o reset não pode encostar em html/body nem usar o seletor
       universal. No Dompdf a margem do @page é aplicada na caixa raiz, então
       "* { margin: 0 }" ou "body { margin: 0 }" zera a margem do papel — era
       exatamente por isso que os PDFs saíam com o conteúdo colado na borda,
       sem margem para furador e por cima da faixa do rodapé.
       O reset aqui é elemento a elemento, de propósito. */
    h1, h2, h3, h4, p, div, span,
    table, thead, tbody, tr, th, td, img {
        margin: 0;
        padding: 0;
    }

    div, table, th, td { box-sizing: border-box; }

    /* Margens do papel: a inferior reserva a faixa do rodapé fixo e da
       numeração de página (ver .doc-rodape, mais abaixo). */
    @page { margin: 28px 34px 54px 34px; }

    body {
        font-family: Helvetica, Arial, sans-serif;
        font-size: 11px;
        line-height: 1.4;
        color: #111826;
    }

    /* Faixa da marca sangrando até a borda do papel */
    .doc-faixa {
        height: 5px;
        background: #003BDD;
        margin: -28px -34px 14px;
    }

    /* ── Papel timbrado ── */
    table.doc-head { width: 100%; border-bottom: 2px solid #E2E8F0; padding-bottom: 11px; margin-bottom: 12px; }
    table.doc-head td { vertical-align: top; }
    table.doc-head table td { vertical-align: middle; }

    .doc-selo {
        width: 34px; height: 34px; border-radius: 8px;
        background: #003BDD; color: #FFFFFF;
        font-size: 15px; font-weight: bold; line-height: 34px; text-align: center;
    }

    .doc-empresa { font-size: 14px; font-weight: bold; color: #111826; }
    .doc-contato { font-size: 9px; color: #64748B; line-height: 1.45; margin-top: 2px; }

    .doc-ref { text-align: right; }
    .doc-ref .doc-tipo { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 1.5px; color: #94A3B8; }
    .doc-ref .doc-numero { font-size: 19px; font-weight: bold; color: #003BDD; margin: 2px 0 6px; }
    .doc-ref .doc-sub { font-size: 10px; color: #475569; }
    .doc-ref .doc-emissao { font-size: 8.5px; color: #94A3B8; margin-top: 3px; }

    .doc-status {
        display: inline-block;
        padding: 3px 11px;
        border-radius: 999px;
        font-size: 9.5px;
        font-weight: bold;
    }

    /* ── Títulos de seção ──
       page-break-after: avoid impede o clássico título órfão no pé da
       página com a tabela começando só na página seguinte. */
    .doc-secao {
        font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;
        color: #003BDD; border-bottom: 1.5px solid #E2E8F0;
        padding-bottom: 4px; margin: 12px 0 6px;
        page-break-after: avoid;
    }

    /* ── Caixas de dados ── */
    .doc-box {
        background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px;
        padding: 9px 12px;
        page-break-inside: avoid;
    }
    .doc-box h3 { font-size: 8.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #94A3B8; margin-bottom: 5px; }
    .doc-box p { font-size: 11px; line-height: 1.42; }
    .doc-box p.forte { font-weight: bold; font-size: 12.5px; }
    .doc-box p.sub { color: #64748B; font-size: 10px; margin-top: 2px; }

    table.doc-grid2 { width: 100%; margin-bottom: 9px; }
    table.doc-grid2 > tbody > tr > td { width: 50%; vertical-align: top; }
    table.doc-grid2 > tbody > tr > td.dir { padding-left: 10px; }

    /* ── Faixa de metadados (3 colunas: datas + responsável) ── */
    table.doc-meta {
        width: 100%; margin-bottom: 11px;
        background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px;
        page-break-inside: avoid;
    }
    table.doc-meta td { padding: 7px 14px; border-right: 1px solid #E2E8F0; }
    table.doc-meta td:last-child { border-right: none; }
    table.doc-meta .rot { display: block; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #94A3B8; margin-bottom: 2px; }
    table.doc-meta .val { font-size: 11.5px; font-weight: bold; color: #1E293B; }

    /* ── Mini-ficha do veículo dentro da caixa ── */
    table.doc-veiculo { width: 100%; margin-top: 7px; }
    table.doc-veiculo td { padding-top: 4px; border-top: 1px solid #E2E8F0; font-size: 10.5px; }
    table.doc-veiculo .rot { display: block; font-size: 8px; text-transform: uppercase; letter-spacing: .5px; color: #94A3B8; }
    table.doc-veiculo .val { font-weight: bold; color: #1E293B; }

    /* ── Totais ──
       Ficam sempre juntos: meia apuração numa folha e o total geral na
       outra é o tipo de documento que ninguém aceita. */
    .doc-fechamento { page-break-inside: avoid; margin-top: 9px; }

    table.doc-totais {
        width: 260px; margin-left: auto;
        background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px;
        padding: 4px 13px;
    }
    table.doc-totais td { padding: 3px 0; font-size: 10.5px; color: #1E293B; }

    .doc-total-final {
        width: 260px; margin-left: auto; margin-top: 7px;
        background: #003BDD; border-radius: 8px; padding: 8px 13px;
    }
    .doc-total-final table { width: 100%; }
    .doc-total-final td { color: #FFFFFF; font-size: 13px; font-weight: bold; }
    .doc-total-final td.num { font-size: 15px; }

    /* ── Garantia e assinaturas ── */
    .doc-garantia {
        margin-top: 10px; padding: 9px 12px;
        border: 1px dashed #CBD5E1; border-radius: 8px;
        page-break-inside: avoid;
    }
    .doc-garantia h3 { font-size: 8.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #94A3B8; margin-bottom: 4px; }
    .doc-garantia p { font-size: 9px; line-height: 1.45; color: #64748B; text-align: justify; }

    table.doc-assinaturas { width: 100%; margin-top: 16px; page-break-inside: avoid; }
    table.doc-assinaturas td { width: 50%; text-align: center; padding-top: 6px; border-top: 1.5px solid #CBD5E1; font-size: 10px; color: #94A3B8; }

    /* ── Tabelas de dados ──
       O <thead> é repetido pelo Dompdf a cada página nova; sem ele a
       segunda página vira uma lista de números sem nome de coluna. */
    table.doc-tabela { width: 100%; border-collapse: collapse; }

    table.doc-tabela thead th {
        text-align: left;
        font-size: 8.5px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px;
        color: #003BDD; background: #EEF2FF;
        border-bottom: 2px solid #DBEAFE;
        padding: 6px;
    }

    table.doc-tabela tbody td {
        padding: 5px 6px;
        border-bottom: 1px solid #E2E8F0;
        font-size: 10.5px;
        vertical-align: top;
    }

    table.doc-tabela tbody tr { page-break-inside: avoid; }
    table.doc-tabela tbody tr:nth-child(even) td { background: #F8FAFC; }
    table.doc-tabela tr.doc-total-linha td { background: #F1F5F9; font-weight: bold; border-top: 1.5px solid #CBD5E1; }

    .num { text-align: right; }
    .muted { color: #64748B; }
    .verde { color: #16A34A; }
    .vermelho { color: #DC2626; }

    /* Blocos que não podem ser cortados no meio */
    .doc-nao-quebrar { page-break-inside: avoid; }

    /* ── Rodapé fixo (repete em todas as páginas) ── */
    .doc-rodape {
        position: fixed;
        bottom: -38px;
        left: 0;
        right: 0;
        height: 40px;
        padding-top: 6px;
        border-top: 1px solid #E2E8F0;
        font-size: 8.5px;
        color: #94A3B8;
    }
    .doc-rodape table { width: 100%; }
    .doc-rodape td.dir { text-align: right; }
</style>
