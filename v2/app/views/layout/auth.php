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
  <?php require V2_RAIZ . '/app/views/layout/_favicon.php'; ?>
  <link rel="stylesheet" href="./css/mecanize.css?v=<?= (int) @filemtime(V2_RAIZ . '/public/css/mecanize.css') ?>">
  <script>try { if (localStorage.getItem('mecanize-theme') === 'dark' || (!localStorage.getItem('mecanize-theme') && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.setAttribute('data-theme', 'dark'); } catch (e) {}</script>
</head>

<body>
  <main class="login-pagina">
    <div class="login-cartao">
      <?php require V2_RAIZ . '/app/views/auth/_vitrine.php'; ?>

      <section class="login-painel">
        <div class="login-form">
          <img class="login-logo-movel logo-claro" src="./img/logo.png" alt="Mecanize">
          <?= $conteudo ?>
        </div>
        <span class="login-rodape">© <?= date('Y') ?> Mecanize</span>
      </section>
    </div>
  </main>

  <script src="./js/campos.js?v=<?= (int) @filemtime(V2_RAIZ . '/public/js/campos.js') ?>"></script>
  <script src="<?= e(asset('js/csrf.js')) ?>"></script>
</body>

</html>
