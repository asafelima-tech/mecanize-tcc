</div>
<!-- CONTEÚDO DA PÁGINA TERMINA AQUI -->

</div><!-- fim .main -->
</div><!-- fim .app -->

<script>
  // ── Dark mode toggle ──
  (function() {
    var html = document.documentElement;
    var btn = document.getElementById('darkToggle');
    if (!btn) return;

    function applyTheme(dark) {
      html.setAttribute('data-theme', dark ? 'dark' : 'light');
      localStorage.setItem('mecanize-theme', dark ? 'dark' : 'light');

      // Redesenha os gráficos para refletir o novo tema
      if (typeof Chart !== 'undefined') {
        Object.values(Chart.instances).forEach(function(chart) {
          chart.update();
        });
      }
    }

    btn.addEventListener('click', function() {
      var isDark = html.getAttribute('data-theme') === 'dark';
      applyTheme(!isDark);
    });
  })();

  // ── Favicon dinâmico ──
  // Segue a preferência de cor do NAVEGADOR/SO (prefers-color-scheme),
  // não o tema do sistema (botão de alternar). Logo preta em navegador claro,
  // logo branca em navegador escuro — independente do tema escolhido no app.
  function atualizarFavicon() {
    const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const favicon = document.createElement('link');
    favicon.rel = 'icon';
    favicon.type = 'image/png';
    favicon.href = isDark ?
      './assets/img/m-logo-branco.png' :
      './assets/img/m-logo-escuro.png';
    document.querySelectorAll('link[rel*="icon"]').forEach(el => el.remove());
    document.head.appendChild(favicon);
  }

  document.addEventListener('DOMContentLoaded', atualizarFavicon);
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', atualizarFavicon);

  // ── Toggle sidebar ──
  const btn = document.getElementById('sidebarCollapseBtn');

  if (btn) {
    // Restaura estado salvo
    if (localStorage.getItem('sidebar-collapsed') === 'true') {
      document.body.classList.add('sidebar-collapsed');
    }

    btn.addEventListener('click', () => {
      const collapsed = document.body.classList.toggle('sidebar-collapsed');
      localStorage.setItem('sidebar-collapsed', collapsed);
    });
  }

  document.addEventListener('click', function(event) {
    const details = document.querySelector('.topbar-create');
    if (details && details.open && !details.contains(event.target)) {
      details.removeAttribute('open');
    }
  });

  // ── Gaveta mobile (menu hambúrguer) ──
  (function() {
    const hamb = document.getElementById('mobileNavToggle');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (!hamb) return;

    const fechar = () => document.body.classList.remove('mobile-nav-open');

    hamb.addEventListener('click', function(e) {
      e.stopPropagation();
      document.body.classList.toggle('mobile-nav-open');
    });

    if (backdrop) backdrop.addEventListener('click', fechar);

    // Toggle de tema da gaveta espelha o da topbar (reaproveita a lógica existente)
    const temaMobile = document.getElementById('darkToggleMobile');
    const temaTopbar = document.getElementById('darkToggle');
    if (temaMobile && temaTopbar) {
      temaMobile.addEventListener('click', function(e) {
        e.stopPropagation();
        temaTopbar.click();
      });
    }

    // Fecha ao tocar em qualquer item do menu
    document.querySelectorAll('.sidebar__nav a, .sidebar__footer a').forEach(function(a) {
      a.addEventListener('click', fechar);
    });

    // Fecha automaticamente se a tela voltar ao tamanho desktop
    window.addEventListener('resize', function() {
      if (window.innerWidth > 768) fechar();
    });
  })();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="./assets/js/masks.js?v=<?= time() ?>"></script>
<script src="./assets/js/text-format.js?v=<?= time() ?>"></script>
<script src="./assets/js/csrf.js?v=<?= time() ?>"></script>
</body>

</html>