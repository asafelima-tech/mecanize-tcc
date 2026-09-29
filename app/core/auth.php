<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged']) || $_SESSION['logged'] !== true) {
    // Caminho relativo à pasta public/, como no resto do sistema. Estava
    // apontando para "/mecanize/public/login", pasta de outra versão do
    // projeto que não existe neste servidor: se esta verificação chegasse a
    // disparar, o usuário caía numa página inexistente em vez do login.
    header('Location: ./login');
    exit;
}
