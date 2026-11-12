<?php
/**
 * Casca das telas de senha (recuperar / redefinir).
 *
 * Tela própria, com endereço próprio: card no meio de um fundo único.
 * Não é o layout do app — quem chega aqui não está logado (muitas vezes
 * vem de um link de e-mail), e mostrar sidebar/topbar sugeria o contrário.
 * Também não é a tela de login: visual e CSS (assets/css/auth.css) são só
 * destas telas, então mexer numa não desconfigura a outra.
 *
 * Uso (o conteúdo do card é montado com buffer e passado como string):
 *
 *   <?php ob_start(); ?>
 *       ... markup de dentro do card ...
 *   <?php
 *   $authConteudo = ob_get_clean();
 *   $authTitulo   = 'Recuperar senha';
 *   require __DIR__ . '/../layout/auth.php';
 *
 * Variáveis:
 *   $authTitulo   string — vai para o <title> da aba (prefixado com "Mecanize · ")
 *   $authConteudo string — HTML já renderizado do miolo do card
 */

$authTitulo   = $authTitulo   ?? 'Acesso';
$authConteudo = $authConteudo ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
  <title>Mecanize · <?= htmlspecialchars($authTitulo) ?></title>
  <link rel="icon" type="image/png" href="./assets/img/logo-escuro.png">
  <link rel="stylesheet" href="./assets/vendor/inter.css">
  <!-- global.css entra só pelo reset, pela fonte e pelos tokens de cor;
       todo o visual desta tela mora no auth.css. -->
  <link rel="stylesheet" href="./assets/css/global.css">
  <link rel="stylesheet" href="./assets/css/auth.css">
</head>

<body class="auth-body">

  <main class="auth">

    <div class="auth-card">
      <img src="./assets/img/logo-escuro.png" alt="Mecanize" class="auth-marca">
      <?= $authConteudo ?>
    </div>

    <div class="auth-rodape">
      <span>© <?= date('Y') ?> Mecanize</span>
      <span class="ponto"></span>
      <a href="./termos">Termos</a>
      <span class="ponto"></span>
      <a href="./privacidade">Privacidade</a>
    </div>

  </main>

  <script src="./assets/js/csrf.js?v=<?= time() ?>"></script>
</body>

</html>
