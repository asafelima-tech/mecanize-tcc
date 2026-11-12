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
$partesNome = preg_split('/\s+/', trim($usuario['nome']));
$iniciais  = mb_strtoupper(mb_substr($partesNome[0], 0, 1) . (count($partesNome) > 1 ? mb_substr(end($partesNome), 0, 1) : ''));
$avisosNaoLidos = $oficina ? (new Notificacao())->naoLidas() : 0;
$avisos = $oficina ? (new Notificacao())->listar(30) : [];
$nomesPerfil = ['ADMIN' => 'Administrador', 'ATENDENTE' => 'Atendente', 'TECNICO' => 'Técnico', 'PLATAFORMA' => 'Plataforma'];

// Caminho no topo (Design System): "Grupo / Item do menu" e, numa tela filha, o título dela por último.
$itemAtual = null;
foreach ($menu as $item) {
    if ($rotaAtual === $item['rota'] || in_array($rotaAtual, $item['rotas'] ?? [], true)) {
        $itemAtual = $item;
        break;
    }
}
$caminho = [];
if ($itemAtual) {
    if ($itemAtual['grupo'] !== '' && $itemAtual['grupo'] !== $itemAtual['rotulo']) {
        $caminho[] = [$itemAtual['grupo'], null];
    }
    $caminho[] = [$itemAtual['rotulo'], $rotaAtual === $itemAtual['rota'] ? null : './' . $itemAtual['rota']];
    if ($rotaAtual !== $itemAtual['rota'] && isset($titulo) && $titulo !== $itemAtual['rotulo']) {
        $caminho[] = [$titulo, null];
    }
} elseif (isset($titulo)) {
    $caminho[] = [$titulo, null];
}

// Situação do caixa no topo: a gaveta é da oficina, todo mundo da equipe vê.
$caixaAberto = $oficina ? (new Caixa())->aberto() : null;
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
  <title>Mecanize<?= isset($titulo) ? ' · ' . e($titulo) : '' ?></title>
  <?php require V2_RAIZ . '/app/views/layout/_favicon.php'; ?>
  <link rel="stylesheet" href="./css/mecanize.css?v=<?= (int) @filemtime(V2_RAIZ . '/public/css/mecanize.css') ?>">
  <script>
    // Tema antes de desenhar a tela (sem "piscar" claro → escuro).
    (function () {
      try {
        var salvo = localStorage.getItem('mecanize-theme');
        if (salvo === 'dark' || (!salvo && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
          document.documentElement.setAttribute('data-theme', 'dark');
        }
        if (localStorage.getItem('sidebar-collapsed') === 'true') document.documentElement.classList.add('pre-collapsed');
      } catch (e) {}
    })();
  </script>
</head>

<body>
  <div class="app">
    <aside class="sidebar" aria-label="Menu principal">
      <div class="sidebar__brand">
        <a href="./<?= e(Permissao::inicio($perfil)) ?>" aria-label="Mecanize — início">
          <img class="sidebar__logo-full" src="./img/logo-branco.png" alt="Mecanize">
          <img class="sidebar__logo-icon" src="./img/simbolo-branco-recorte.png" alt="Mecanize">
        </a>
        <button type="button" class="sidebar__retrair" id="sidebarCollapseBtn" aria-label="Retrair barra lateral" title="Retrair barra lateral"><?= icone('sidebar-collapse', 20) ?></button>
      </div>

      <?php $grupoAtual = null; foreach ($menu as $item):
        if (!isset($rotas[$item['rota']]) || !Permissao::permite($perfil, $rotas[$item['rota']][2])) continue;
        $ativo = $rotaAtual === $item['rota'] || in_array($rotaAtual, $item['rotas'] ?? [], true);
        if ($item['grupo'] !== $grupoAtual):
          if ($grupoAtual !== null) echo '</nav>';
          $grupoAtual = $item['grupo']; ?>
        <nav class="sidebar__grupo" aria-label="<?= e($grupoAtual !== '' ? $grupoAtual : 'Início') ?>"><?php if ($grupoAtual !== ''): ?><span class="sidebar__rotulo"><?= e($grupoAtual) ?></span><?php endif; ?>
        <?php endif; ?>
          <a class="sidebar__item<?= $ativo ? ' is-active' : '' ?>" href="./<?= e($item['rota']) ?>" title="<?= e($item['rotulo']) ?>" <?= $ativo ? 'aria-current="page"' : '' ?>>
            <?= icone($item['icone'], 20, $ativo ? 2 : 1.5) ?><span class="label"><?= e($item['rotulo']) ?></span>
          </a>
      <?php endforeach; if ($grupoAtual !== null) echo '</nav>'; ?>

      <div class="sidebar__rodape">
        <button type="button" class="sidebar__tema" id="darkToggle" aria-label="Alternar tema escuro"><span class="interruptor" aria-hidden="true"></span><span>Tema escuro</span></button>
        <div class="sidebar__perfil">
          <a href="<?= $oficina ? './perfil' : './plataforma' ?>" title="Meu perfil<?= $oficina ? ' · ' . e($oficina['nome']) : '' ?>">
            <span class="sidebar__avatar"><?= e($iniciais) ?></span>
            <div style="min-width:0"><div class="sidebar__nome"><?= e($usuario['nome']) ?></div><div class="sidebar__papel"><?= e($nomesPerfil[$perfil] ?? $perfil) ?></div></div>
          </a>
          <a href="./sair" class="sidebar__sair" aria-label="Sair" title="Sair"><?= icone('logout', 16) ?></a>
        </div>
      </div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="main">
      <header class="topbar">
        <button type="button" class="topbar__hamburger" id="mobileNavToggle" aria-label="Abrir menu"><?= icone('menu', 20) ?></button>
        <nav class="topbar__caminho" aria-label="Você está em">
          <?php foreach ($caminho as $i => [$nome, $link]): $ultimo = $i === count($caminho) - 1; ?>
            <?php if ($i > 0): ?><span class="topbar__sep" aria-hidden="true">/</span><?php endif; ?>
            <?php if ($link): ?><a href="<?= e($link) ?>"><?= e($nome) ?></a><?php elseif ($ultimo): ?><b aria-current="page"><?= e($nome) ?></b><?php else: ?><span><?= e($nome) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <div class="topbar__right">
          <?php if ($oficina): ?>
            <a class="topbar-caixa<?= $caixaAberto ? ' is-aberto' : '' ?>" href="./caixa" title="<?= $caixaAberto ? 'Caixa aberto desde ' . e(data_br($caixaAberto['aberto_em'], true)) : 'Caixa fechado' ?>">
              <span class="topbar-caixa__ponto" aria-hidden="true"></span><?= $caixaAberto ? 'Caixa aberto · ' . e(strtok((string) $caixaAberto['aberto_por_nome'], ' ')) : 'Caixa fechado' ?>
            </a>
            <button type="button" class="topbar-sino" data-abrir-avisos aria-haspopup="dialog" aria-controls="dlg-avisos" aria-label="Avisos<?= $avisosNaoLidos ? ' (' . $avisosNaoLidos . ' não lidos)' : '' ?>" title="Avisos">
              <?= icone('bell', 18) ?><?php if ($avisosNaoLidos): ?><span class="topbar-sino__n"><?= $avisosNaoLidos > 9 ? '9+' : $avisosNaoLidos ?></span><?php endif; ?>
            </button>
          <?php endif; ?>
        </div>
      </header>

      <main class="content">
        <?php if ($avisoLicenca): ?>
          <?php $alertaTipo = $somenteLeitura ? 'erro' : 'aviso'; $alertaTitulo = $somenteLeitura ? 'Somente leitura' : 'Licença';
                $alertaMensagem = $avisoLicenca; require __DIR__ . '/_alerta.php'; ?>
        <?php endif; ?>
        <?php if ($aviso): ?>
          <?php $alertaTipo = $aviso['tipo']; $alertaMensagem = $aviso['mensagem']; require __DIR__ . '/_alerta.php'; ?>
        <?php endif; ?>

        <?= $conteudo ?>
      </main>
    </div>
  </div>
  <?php if ($oficina) require __DIR__ . '/_avisos.php'; ?>
  <script src="./js/campos.js?v=<?= (int) @filemtime(V2_RAIZ . '/public/js/campos.js') ?>"></script>

  <script>
    (function () {
      var html = document.documentElement;
      var tema = document.getElementById('darkToggle');
      if (tema) tema.addEventListener('click', function () {
        var escuro = html.getAttribute('data-theme') !== 'dark';
        html.setAttribute('data-theme', escuro ? 'dark' : 'light');
        try { localStorage.setItem('mecanize-theme', escuro ? 'dark' : 'light'); } catch (e) {}
      });

      if (html.classList.contains('pre-collapsed')) document.body.classList.add('sidebar-collapsed');
      var recolher = document.getElementById('sidebarCollapseBtn');
      if (recolher) recolher.addEventListener('click', function () {
        var r = document.body.classList.toggle('sidebar-collapsed');
        recolher.setAttribute('aria-label', r ? 'Expandir barra lateral' : 'Retrair barra lateral');
        try { localStorage.setItem('sidebar-collapsed', r); } catch (e) {}
      });

      var fechar = function () { document.body.classList.remove('mobile-nav-open'); };
      var hamb = document.getElementById('mobileNavToggle');
      if (hamb) hamb.addEventListener('click', function (e) { e.stopPropagation(); document.body.classList.toggle('mobile-nav-open'); });
      var fundo = document.getElementById('sidebarBackdrop');
      if (fundo) fundo.addEventListener('click', fechar);
      window.addEventListener('resize', function () { if (window.innerWidth > 900) fechar(); });

      // Avisos: janela aberta pelo sininho. Fecha com Esc, com o X ou clicando fora dela.
      var dlgAvisos = document.getElementById('dlg-avisos');
      var sino = document.querySelector('[data-abrir-avisos]');
      if (dlgAvisos && sino) {
        var abrirAvisos = function () { if (!dlgAvisos.open) { dlgAvisos.showModal(); sino.setAttribute('aria-expanded', 'true'); } };
        sino.addEventListener('click', abrirAvisos);
        dlgAvisos.addEventListener('close', function () { sino.setAttribute('aria-expanded', 'false'); });
        dlgAvisos.addEventListener('click', function (e) {
          var r = dlgAvisos.getBoundingClientRect();
          if (e.target === dlgAvisos && (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom)) dlgAvisos.close();
        });
        dlgAvisos.querySelector('[data-fechar-avisos]').addEventListener('click', function () { dlgAvisos.close(); });
        if (location.hash === '#avisos') { history.replaceState(null, '', location.pathname + location.search); abrirAvisos(); }

        // "Marcar todos como lidos" sem sair da página (sem JS, o formulário volta para cá).
        var lerTodas = dlgAvisos.querySelector('[data-avisos-ler-todas]');
        if (lerTodas && window.fetch) lerTodas.addEventListener('submit', function (e) {
          e.preventDefault();
          fetch(lerTodas.action, { method: 'POST', body: new FormData(lerTodas), credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) {
              if (!r.ok) throw r;
              dlgAvisos.querySelectorAll('.aviso--novo').forEach(function (a) { a.classList.remove('aviso--novo'); var p = a.querySelector('.ponto-novo'); if (p) p.remove(); });
              dlgAvisos.querySelector('[data-avisos-resumo]').textContent = 'Tudo lido';
              lerTodas.remove();
              var n = sino.querySelector('.topbar-sino__n'); if (n) n.remove();
              sino.setAttribute('aria-label', 'Avisos');
            })
            .catch(function () { lerTodas.submit(); });
        });
      }
    })();
  </script>
  <script src="<?= e(asset('vendor/imask.min.js')) ?>"></script>
  <script>
    // Linhas de tabela clicáveis (o primeiro campo continua sendo um link de verdade, para teclado e nova aba).
    document.addEventListener('click', function (e) {
      var tr = e.target.closest('tr[data-href]');
      if (!tr || e.target.closest('a, button, input, select, form, details, label')) return;
      window.location = tr.dataset.href;
    });

    // Menus "⋯": fecha os outros ao abrir um e fecha todos ao clicar fora.
    document.addEventListener('click', function (e) {
      document.querySelectorAll('details.menu-mais[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
    });
    // Dentro de uma tabela com rolagem, o menu "flutua" sobre a tela (senão a tabela o cortaria).
    function posicionarMenu(d) {
      var lista = d.querySelector('.menu-mais__lista');
      if (!d.open) { lista.removeAttribute('style'); return; }
      var r = d.querySelector('summary').getBoundingClientRect();
      lista.style.position = 'fixed';
      lista.style.left = 'auto';
      lista.style.right = (window.innerWidth - r.right) + 'px';
      var acima = r.bottom + lista.offsetHeight + 12 > window.innerHeight;
      lista.style.top = acima ? 'auto' : (r.bottom + 6) + 'px';
      lista.style.bottom = acima ? (window.innerHeight - r.top + 6) + 'px' : 'auto';
    }
    document.addEventListener('toggle', function (e) {
      if (e.target.matches && e.target.matches('details.menu-mais') && e.target.closest('.table-wrap')) posicionarMenu(e.target);
    }, true);
    ['scroll', 'resize'].forEach(function (ev) {
      window.addEventListener(ev, function () { document.querySelectorAll('.table-wrap details.menu-mais[open]').forEach(posicionarMenu); }, true);
    });

    // Filtros que se aplicam ao escolher (selects dentro de form.filtros).
    document.querySelectorAll('form.filtros select').forEach(function (s) {
      s.addEventListener('change', function () { s.form.submit(); });
    });

    // Confirmação antes de ações destrutivas (<form data-confirmar="texto">): janela do sistema, em js/campos.js.

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
