<?php

/**
 * Auxiliares de saída para as views.
 *
 * O escape do lado PHP já é rigoroso (htmlspecialchars em todo campo impresso),
 * mas ele parava na fronteira PHP → JavaScript: o que entra dentro de um bloco
 * <script> não passa pelo parser de HTML da mesma forma, e é ali que a proteção
 * abria um buraco.
 */

/**
 * Converte um valor PHP em JSON seguro para colar dentro de <script>.
 *
 * json_encode() sozinho não escapa "<", então um produto cadastrado como
 * "</script>" fecha o bloco e o resto do nome vira HTML executável na página —
 * XSS armazenado, que dispara na sessão de quem abrir a tela. As flags HEX
 * transformam < > & ' " em < e afins: o JavaScript lê exatamente o mesmo
 * valor, e o parser de HTML não enxerga tag nenhuma.
 *
 * JSON_UNESCAPED_UNICODE preserva o comportamento atual com acentos.
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
