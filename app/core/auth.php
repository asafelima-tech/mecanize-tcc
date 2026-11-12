<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
 * Segunda camada: o front-controller (public/index.php) já barra antes, mas uma
 * checagem redundante que funciona custa menos que remover as inclusões.
 *
 * O caminho estava apontando para /mecanize/public/login — outro projeto. Numa
 * instalação chamada mecanize-tcc, o redirecionamento levava a um 404, não ao
 * login: a "camada de segurança" mandava o visitante para lugar nenhum.
 * Caminho relativo funciona em qualquer pasta onde o sistema seja instalado.
 */
if (!isset($_SESSION['logged']) || $_SESSION['logged'] !== true) {
    header('Location: ./login');
    exit;
}