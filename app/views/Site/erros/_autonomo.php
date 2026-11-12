<?php
/**
 * Casca de TODAS as telas de erro: página inteira, fundo claro, logo oficial no
 * canto e o conteúdo centralizado (pagina.php).
 *
 * O CSS vai embutido e a logo vai como data URI: se o servidor está com
 * problema — que é justamente quando esta página aparece — um <link> ou uma
 * imagem podem simplesmente não ser servidos, e a tela ainda precisa ter
 * estilo e marca. Só a fonte é buscada por HTTP, e tem alternativa (a do
 * sistema operacional).
 *
 * Espera: $erro (metadados), $contexto, $view (caminho do conteúdo).
 */
$logo = Erro::logo();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mecanize · <?= htmlspecialchars($erro['titulo']) ?></title>
    <link rel="icon" type="image/png" href="./assets/img/logo-escuro.png">
    <link rel="stylesheet" href="./assets/vendor/inter.css">
    <style>
        <?= Erro::css() ?>
    </style>
</head>

<body class="erro-pagina">
    <?php if ($logo !== ''): ?>
        <img class="erro-logo" src="<?= $logo ?>" alt="Mecanize" width="300" height="78">
    <?php else: ?>
        <span class="erro-logo-texto">Mecanize</span>
    <?php endif; ?>

    <?php require $view; ?>

    <script>
        <?= Erro::js() ?>
    </script>
</body>

</html>
