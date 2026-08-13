<?php
/**
 * Casca das telas de erro que não podem depender do resto do sistema.
 *
 * Nada aqui busca arquivo por HTTP: o CSS vai embutido e a fonte cai na do
 * sistema operacional. Se o servidor está com problema — que é justamente
 * quando esta página aparece — um <link> pode simplesmente não ser servido, e
 * o usuário veria texto cru sem estilo nenhum.
 *
 * Espera: $erro (metadados), $contexto, $view (caminho do arquétipo).
 */
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mecanize — <?= htmlspecialchars($erro['titulo']) ?></title>
    <link rel="icon" type="image/ico" href="./assets/img/mecanize.ico">
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: #F1F5F9;
            font-family: "Inter", "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        /* Marca no topo: mesmo sem o layout do app, a tela continua sendo
           reconhecidamente do Mecanize, e não uma página de erro genérica. */
        .erro-marca {
            position: absolute;
            top: 26px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: -.02em;
            color: #111826;
        }

        .erro-marca__sinal {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            background: linear-gradient(135deg, #003BDD 0%, #0030C4 100%);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 800;
        }

        @media (max-width: 700px) {
            .erro-marca { top: 18px; }
        }

        <?= Erro::css() ?>
    </style>
</head>

<body>
    <div class="erro-marca">
        <span class="erro-marca__sinal">M</span>
        Mecanize
    </div>

    <?php require $view; ?>

    <script>
        <?= Erro::js() ?>
    </script>
</body>

</html>
