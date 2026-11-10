<?php
/**
 * Layout das telas de quem está logado: menu lateral, topo e o conteúdo.
 * Espera: $conteudo (HTML da tela) e, opcional, $titulo.
 */
$rotas     = require V2_RAIZ . '/config/rotas.php';
$menu      = require V2_RAIZ . '/config/menu.php';
$rotaAtual = trim((string) ($_GET['url'] ?? 'inicio'), '/') ?: 'inicio';
$usuario   = Contexto::usuario();
$oficina   = Contexto::oficina();
$perfil    = Contexto::perfil();
$avisoLicenca = $oficina ? Licenca::mensagem($oficina) : null;
$somenteLeitura = $oficina && !Licenca::permiteEscrita($oficina);
$aviso     = Controller::tirarAviso();
$iniciais  = mb_strtoupper(mb_substr($usuario['nome'], 0, 1) . mb_substr($usuario['nome'], -1));
$avisosNaoLidos = $oficina ? (new Notificacao())->naoLidas() : 0;
$nomesPerfil = ['ADMIN' => 'Administrador', 'ATENDENTE' => 'Atendente', 'TECNICO' => 'Técnico', 'PLATAFORMA' => 'Plataforma'];
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
  <title>Mecanize<?= isset($titulo) ? ' · ' . e($titulo) : '' ?></title>
  <link rel="icon" type="image/png" href="<?= e(asset('img/m-logo-escuro.png')) ?>">
  <link rel="stylesheet" href="<?= e(asset('vendor/inter.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/global.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/sidebar.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/erros.css')) ?>">
  <link rel="stylesheet" href="./css/v2.css">
  <script>
    // Tema antes de desenhar a tela (sem "piscar" claro → escuro).
    (function () {
      var salvo = localStorage.getItem('mecanize-theme');
      if (salvo === 'dark' || (!salvo && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.setAttribute('data-theme', 'dark');
      }
      if (localStorage.getItem('sidebar-collapsed') === 'true') {
        document.documentElement.classList.add('pre-collapsed');
      }
    })();
  </script>
</head>

<body>
  <div class="app">
    <aside class="sidebar">
      <div class="sidebar__brand">
        <a href="./<?= e(Permissao::inicio($perfil)) ?>">
          <img class="sidebar__logo-full" src="<?= e(asset('img/mecanize.ico')) ?>" alt="Mecanize">
          <img class="sidebar__logo-icon" src="<?= e(asset('img/m-logo-branco.png')) ?>" alt="M">
        </a>
      </div>

      <ul class="sidebar__nav">
        <?php foreach ($menu as $item):
          if (!isset($rotas[$item['rota']]) || !Permissao::permite($perfil, $rotas[$item['rota']][2])) continue;
          $ativo = $rotaAtual === $item['rota'] || in_array($rotaAtual, $item['rotas'] ?? [], true); ?>
          <li class="<?= $ativo ? 'is-active' : '' ?>">
            <a href="./<?= e($item['rota']) ?>" data-tip="<?= e($item['rotulo']) ?>" <?= $ativo ? 'aria-current="page"' : '' ?>>
              <i class="bi <?= e($item['icone']) ?> icon" aria-hidden="true"></i>
              <span class="label"><?= e($item['rotulo']) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>

      <button class="sidebar__toggle-floating" id="sidebarCollapseBtn" aria-label="Recolher menu">
        <svg class="sidebar__collapse-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m11 17-5-5 5-5" /><path d="m18 17-5-5 5-5" /></svg>
      </button>

      <div class="sidebar__footer">
        <a href="<?= $oficina ? './perfil' : '#' ?>" class="sidebar__profile">
          <div class="sidebar__profile-avatar"><?= e($iniciais) ?></div>
          <div class="sidebar__profile-info">
            <div class="sidebar__footer-title"><?= e($usuario['nome']) ?></div>
            <div class="sidebar__footer-sub"><?= e($nomesPerfil[$perfil] ?? $perfil) ?></div>
          </div>
        </a>
        <a href="./sair" class="sidebar__logout">
          <span class="sidebar__logout-icon"><i class="bi bi-box-arrow-right" aria-hidden="true"></i></span>
          <span class="sidebar__logout-label">Sair</span>
        </a>
      </div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="main">
      <header class="topbar">
        <button class="topbar__hamburger" id="mobileNavToggle" aria-label="Abrir menu"><i class="bi bi-list" aria-hidden="true"></i></button>
        <strong class="topbar__oficina"><?= e($oficina['nome'] ?? 'Mecanize — Plataforma') ?></strong>
        <div class="topbar__right">
          <?php if ($oficina): ?>
            <a class="topbar-sino" href="./notificacoes" aria-label="Avisos<?= $avisosNaoLidos ? ' (' . $avisosNaoLidos . ' não lidos)' : '' ?>">
              <i class="bi bi-bell" aria-hidden="true"></i><?php if ($avisosNaoLidos): ?><span class="topbar-sino__n"><?= $avisosNaoLidos > 9 ? '9+' : $avisosNaoLidos ?></span><?php endif; ?>
            </a>
          <?php endif; ?>
          <button class="topbar-dark-toggle" id="darkToggle" aria-label="Alternar tema escuro">
            <span class="dark-toggle__icon-light"><i class="bi bi-sun" aria-hidden="true"></i></span>
            <span class="dark-toggle__track" aria-hidden="true"></span>
            <span class="dark-toggle__icon-dark"><i class="bi bi-moon" aria-hidden="true"></i></span>
          </button>
        </div>
      </header>

      <div class="content">
        <?php if ($avisoLicenca): ?>
          <?php $alertaTipo = $somenteLeitura ? 'erro' : 'aviso'; $alertaTitulo = $somenteLeitura ? 'Somente leitura' : 'Licença';
                $alertaMensagem = $avisoLicenca; require __DIR__ . '/_alerta.php'; ?>
        <?php endif; ?>
        <?php if ($aviso): ?>
          <?php $alertaTipo = $aviso['tipo']; $alertaMensagem = $aviso['mensagem']; require __DIR__ . '/_alerta.php'; ?>
        <?php endif; ?>

        <?= $conteudo ?>
      </div>
    </div>
  </div>

  <script>
    (function () {
      var html = document.documentElement;
      var tema = document.getElementById('darkToggle');
      if (tema) tema.addEventListener('click', function () {
        var escuro = html.getAttribute('data-theme') !== 'dark';
        html.setAttribute('data-theme', escuro ? 'dark' : 'light');
        localStorage.setItem('mecanize-theme', escuro ? 'dark' : 'light');
      });

      if (html.classList.contains('pre-collapsed')) document.body.classList.add('sidebar-collapsed');
      var recolher = document.getElementById('sidebarCollapseBtn');
      if (recolher) recolher.addEventListener('click', function () {
        localStorage.setItem('sidebar-collapsed', document.body.classList.toggle('sidebar-collapsed'));
      });

      var fechar = function () { document.body.classList.remove('mobile-nav-open'); };
      var hamb = document.getElementById('mobileNavToggle');
      if (hamb) hamb.addEventListener('click', function (e) { e.stopPropagation(); document.body.classList.toggle('mobile-nav-open'); });
      var fundo = document.getElementById('sidebarBackdrop');
      if (fundo) fundo.addEventListener('click', fechar);
      window.addEventListener('resize', function () { if (window.innerWidth > 768) fechar(); });
    })();
  </script>
  <script src="<?= e(asset('vendor/imask.min.js')) ?>"></script>
  <script>
    // Linhas de tabela clicáveis (o primeiro campo continua sendo um link de verdade, para teclado e nova aba).
    document.addEventListener('click', function (e) {
      var tr = e.target.closest('tr[data-href]');
      if (!tr || e.target.closest('a, button, input, select, form')) return;
      window.location = tr.dataset.href;
    });

    // Filtros que se aplicam ao escolher (selects dentro de form.filtros).
    document.querySelectorAll('form.filtros select').forEach(function (s) {
      s.addEventListener('change', function () { s.form.submit(); });
    });

    // Confirmação antes de ações destrutivas: <form data-confirmar="texto">.
    document.querySelectorAll('form[data-confirmar]').forEach(function (f) {
      f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirmar)) e.preventDefault(); });
    });

    // Máscaras: data-mascara="telefone|cep|placa|documento|cpf|cnpj".
    if (window.IMask) {
      var mascaras = {
        telefone: { mask: [{ mask: '(00) 0000-0000' }, { mask: '(00) 00000-0000' }] },
        cep: { mask: '00000-000' },
        cpf: { mask: '000.000.000-00' },
        cnpj: { mask: '00.000.000/0000-00' },
        documento: { mask: [{ mask: '000.000.000-00' }, { mask: '00.000.000/0000-00' }] },
        placa: { mask: [{ mask: 'aaa-0000' }, { mask: 'aaa0a00' }], prepare: function (s) { return s.toUpperCase(); } }
      };
      document.querySelectorAll('[data-mascara]').forEach(function (el) {
        var m = mascaras[el.dataset.mascara];
        if (m) el._mascara = IMask(el, m);
      });
    }
  </script>
  <script src="<?= e(asset('js/csrf.js')) ?>"></script>
</body>

</html>
