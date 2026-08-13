<?php

/**
 * Converte um valor PHP em JSON seguro para colar DENTRO de uma tag <script>.
 *
 * json_encode() sozinho não escapa "<". Como as telas de OS, orçamento e
 * veículo entregam listas do banco pro JavaScript assim:
 *
 *     const CATALOGO = <?= json_para_script($produtos) ?>;
 *
 * um produto cadastrado com "</script>" no nome fechava a tag ali mesmo, e o
 * resto do nome passava a valer como HTML da página — era só cadastrar
 * "</script><script>...</script>" pra rodar script na tela de quem abrisse o
 * formulário.
 *
 * As flags JSON_HEX_* trocam < > & ' " pelas versões escapadas (< e
 * companhia). O JavaScript lê exatamente o mesmo texto de antes; o navegador,
 * ao procurar onde a tag <script> termina, não enxerga tag nenhuma.
 *
 * JSON_UNESCAPED_UNICODE mantém acentuação legível, como já era antes.
 */
function json_para_script($valor): string
{
    return json_encode(
        $valor,
        JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
    );
}
