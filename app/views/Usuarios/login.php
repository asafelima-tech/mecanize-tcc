<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mecanize · Entrar</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400..800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./assets/css/global.css">

  <script>
    // ── Favicon dinâmico (login) ──
    // Segue a preferência de cor do NAVEGADOR/SO (prefers-color-scheme).
    // Logo escura em navegador claro, logo branca em navegador escuro.
    (function atualizarFavicon() {
      const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      const favicon = document.createElement('link');
      favicon.rel = 'icon';
      favicon.type = 'image/png';
      favicon.href = isDark ?
        './assets/img/logo-branco.ico' :
        './assets/img/logo-escuro.png';
      document.querySelectorAll('link[rel*="icon"]').forEach(el => el.remove());
      document.head.appendChild(favicon);
    })();

    // Atualiza em tempo real se o usuário mudar o tema do SO com a página aberta
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
      const favicon = document.querySelector('link[rel*="icon"]');
      const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      if (favicon) {
        favicon.href = isDark ?
          './assets/img/logo-branco.ico' :
          './assets/img/logo-escuro.png';
      }
    });
  </script>
</head>

<body>

  <main class="login">

    <!-- LADO ESQUERDO (IMAGEM) -->
    <section class="login__left">

      <!-- LOGO SOBRE A IMAGEM -->
      <img
        src="./assets/img/logo-mecanize.png"
        alt="Mecanize"
        class="login__logo-overlay">

      <img
        src="./assets/img/fundo.png"
        alt="Mecanize"
        class="login__image">

    </section>

    <!-- LADO DIREITO -->
    <section class="login__right">
      <div class="login__right-bg" aria-hidden="true">
        <div class="dot-pattern"></div>
        <div class="glow"></div>
        <div class="glow glow--secondary"></div>
      </div>

      <form class="login-card" method="POST" action="./login" novalidate>

        <div class="login-card__head">
          <h2 class="login-card__title">Entrar</h2>
          <p class="login-card__subtitle">Bem-vindo de volta ao Mecanize</p>
        </div>

        <?php if (!empty($error)): ?>
          <div style="background:var(--color-danger-soft);color:#991B1B;border-radius:var(--radius-md);padding:10px 14px;font-size:13px;font-weight:500;">
            <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($aviso)): ?>
          <div style="background:#FEF3C7;color:#92400E;border:1px solid #FDE68A;border-radius:var(--radius-md);padding:10px 14px;font-size:13px;font-weight:500;">
            <?= htmlspecialchars($aviso) ?>
          </div>
        <?php endif; ?>

        <div class="login-field">
          <label for="email">E-mail</label>
          <div class="login-input">
            <span class="ico-wrap">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                <circle cx="12" cy="7" r="4" />
              </svg>
            </span>
            <input type="email" id="email" name="email" placeholder="seu@email.com" autocomplete="username" required>
          </div>
        </div>

        <div class="login-field">
          <div class="login-field__top">
            <label for="senha">Senha</label>
          </div>
          <div class="login-input">
            <span class="ico-wrap">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
              </svg>
            </span>
            <input type="password" id="senha" name="senha" placeholder="Digite sua senha" autocomplete="current-password" required>
            <button type="button" class="login-input__toggle" id="togglesenha" aria-label="Mostrar senha">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
            </button>
          </div>
        </div>

        <div class="login-row">
          <a href="./recuperar-senha" class="login-link">Esqueceu a senha?</a>
        </div>

        <button type="submit" class="btn login-submit">
          Entrar
        </button>

      </form>

      <div class="login__right-footer">
        <span>© <?= date('Y') ?> Mecanize</span>
        <span class="dot"></span>
        <a href="./termos">Termos</a>
        <span class="dot"></span>
        <a href="./privacidade">Privacidade</a>
      </div>
    </section>

  </main>

  <script>
    const toggle = document.getElementById('togglesenha');
    const pwd = document.getElementById('senha');
    if (toggle && pwd) {
      toggle.addEventListener('click', () => {
        pwd.type = pwd.type === 'password' ? 'text' : 'password';
      });
    }
  </script>
</body>

</html>