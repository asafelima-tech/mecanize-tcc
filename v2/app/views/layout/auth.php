<?php
/**
 * Casca das telas de senha (recuperar / redefinir). Espera: $conteudo (HTML do card), $authTitulo.
 */
$authTitulo = $authTitulo ?? 'Acesso';
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
  <title>Mecanize · <?= e($authTitulo) ?></title>
  <link rel="icon" type="image/png" href="<?= e(asset('img/logo-escuro.png')) ?>">
  <link rel="stylesheet" href="<?= e(asset('vendor/inter.css')) ?>">
  <!-- global.css entra só pelo reset, pela fonte e pelos tokens de cor;
       todo o visual desta tela mora no auth.css. -->
  <link rel="stylesheet" href="<?= e(asset('css/global.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/auth.css')) ?>">
</head>

<body class="auth-body">

  <main class="auth">

    <div class="auth-card">
      <img src="<?= e(asset('img/logo-escuro.png')) ?>" alt="Mecanize" class="auth-marca">
      <?= $conteudo ?>
    </div>

    <div class="auth-rodape">
      <span>© <?= date('Y') ?> Mecanize</span>
    </div>

  </main>

  <script src="<?= e(asset('js/csrf.js')) ?>"></script>
</body>

</html>
