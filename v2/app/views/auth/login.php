<?php /** Espera (opcional): $erro, $aviso, $email, $retorno */ ?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
  <title>Mecanize · Entrar</title>
  <?php require V2_RAIZ . '/app/views/layout/_favicon.php'; ?>
  <link rel="stylesheet" href="./css/mecanize.css?v=<?= (int) @filemtime(V2_RAIZ . '/public/css/mecanize.css') ?>">
  <script>try { if (localStorage.getItem('mecanize-theme') === 'dark' || (!localStorage.getItem('mecanize-theme') && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.setAttribute('data-theme', 'dark'); } catch (e) {}</script>
</head>

<body>
  <main class="login-pagina">
    <div class="login-cartao">
      <?php require __DIR__ . '/_vitrine.php'; ?>

      <section class="login-painel" aria-label="Entrar">
        <form class="login-form" method="POST" action="./login" novalidate>
          <img class="login-logo-movel logo-claro" src="./img/logo.png" alt="Mecanize">

          <?php if (!empty($retorno)): ?>
            <!-- Tela que a pessoa tentou abrir antes de entrar (validada no controller). -->
            <input type="hidden" name="retorno" value="<?= e($retorno) ?>">
          <?php endif; ?>
          <?= csrf_field() ?>

          <div class="login-form__cabeca">
            <h1 class="login-form__titulo">Bem-vindo de volta</h1>
            <span class="login-form__sub">Entre para continuar na Mecanize.</span>
          </div>

          <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>
          <?php if (!empty($aviso)) { $alertaTipo = 'aviso'; $alertaMensagem = $aviso; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

          <div class="login-form__campos">
            <div class="login-campo">
              <label for="email">E-mail</label>
              <span class="login-entrada">
                <input type="email" id="email" name="email" placeholder="seu@email.com" autocomplete="username" value="<?= e($email ?? '') ?>" required autofocus>
              </span>
            </div>
            <div class="login-campo">
              <label for="senha">Senha</label>
              <span class="login-entrada">
                <input type="password" id="senha" name="senha" placeholder="Digite sua senha" autocomplete="current-password" required>
                <button type="button" class="login-olho" id="togglesenha" aria-label="Mostrar senha" title="Mostrar senha"><?= icone('eye', 20) ?></button>
              </span>
            </div>
            <div class="login-linha">
              <a href="./recuperar-senha">Esqueceu a senha?</a>
            </div>
          </div>

          <button type="submit" class="login-entrar">Entrar</button>
        </form>
        <span class="login-rodape">© <?= date('Y') ?> Mecanize</span>
      </section>
    </div>
  </main>

  <script>
    const toggle = document.getElementById('togglesenha');
    const pwd = document.getElementById('senha');
    if (toggle && pwd) {
      toggle.addEventListener('click', () => {
        const mostrar = pwd.type === 'password';
        pwd.type = mostrar ? 'text' : 'password';
        toggle.setAttribute('aria-label', mostrar ? 'Ocultar senha' : 'Mostrar senha');
        toggle.title = toggle.getAttribute('aria-label');
        toggle.innerHTML = mostrar ? <?= json_para_script(icone('eye-off', 20)) ?> : <?= json_para_script(icone('eye', 20)) ?>;
      });
    }
  </script>
  <script src="./js/campos.js?v=<?= (int) @filemtime(V2_RAIZ . '/public/js/campos.js') ?>"></script>
  <script src="<?= e(asset('js/csrf.js')) ?>"></script>
</body>

</html>
