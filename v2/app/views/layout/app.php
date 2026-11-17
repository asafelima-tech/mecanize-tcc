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

// Situação do caixa no topo: só para quem opera o caixa (o técnico não vê).
$veCaixa = $oficina && Permissao::permite($perfil, Permissao::BALCAO);
$caixaAberto = $veCaixa ? (new Caixa())->aberto() : null;
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
  <link rel="stylesheet" href="./css/identidade.css?v=<?= (int) @filemtime(V2_RAIZ . '/public/css/identidade.css') ?>">
  <link rel="stylesheet" href="./css/responsivo.css?v=<?= (int) @filemtime(V2_RAIZ . '/public/css/responsivo.css') ?>">
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
  <a class="pular" href="#conteudo">Pular para o conteúdo</a>
  <div class="app">
    <aside class="sidebar" id="menu-principal" aria-label="Menu principal">
      <div class="sidebar__brand">
        <a href="./<?= e(Permissao::inicio($perfil)) ?>" aria-label="Mecanize — início">
          <img class="sidebar__logo-full logo-p-claro" src="./img/logo.png" alt="Mecanize">
          <img class="sidebar__logo-full logo-p-escuro" src="./img/logo-branco.png" alt="" aria-hidden="true">
          <img class="sidebar__logo-icon logo-p-claro" src="./img/simbolo.png" alt="Mecanize">
          <img class="sidebar__logo-icon logo-p-escuro" src="./img/simbolo-branco-recorte.png" alt="" aria-hidden="true">
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
        <?php if ($caminho): ?>
          <nav class="topbar__caminho" aria-label="Você está em">
            <?php foreach ($caminho as $k => [$rotulo, $link]): ?>
              <?php if ($k > 0): ?><span class="topbar__sep" aria-hidden="true"><?= icone('chevron-right', 12, 2) ?></span><?php endif; ?>
              <?php if ($link): ?><a href="<?= e($link) ?>"><?= e($rotulo) ?></a><?php elseif ($k === count($caminho) - 1): ?><b aria-current="page"><?= e($rotulo) ?></b><?php else: ?><span><?= e($rotulo) ?></span><?php endif; ?>
            <?php endforeach; ?>
          </nav>
        <?php endif; ?>
        <div class="topbar__right">
          <?php require __DIR__ . '/_criar.php'; ?>
          <?php if ($oficina): ?>
            <?php if ($veCaixa): ?>
            <a class="topbar-caixa<?= $caixaAberto ? ' is-aberto' : '' ?>" href="./caixa" title="<?= $caixaAberto ? 'Caixa aberto desde ' . e(data_br($caixaAberto['aberto_em'], true)) : 'Caixa fechado' ?>">
              <span class="topbar-caixa__ponto" aria-hidden="true"></span><?= $caixaAberto ? 'Caixa aberto · ' . e(strtok((string) $caixaAberto['aberto_por_nome'], ' ')) : 'Caixa fechado' ?>
            </a>
            <?php endif; ?>
            <button type="button" class="topbar-sino" data-abrir-avisos aria-haspopup="dialog" aria-controls="dlg-avisos" aria-label="Avisos<?= $avisosNaoLidos ? ' (' . $avisosNaoLidos . ' não lidos)' : '' ?>" title="Avisos">
              <?= icone('bell', 18) ?><?php if ($avisosNaoLidos): ?><span class="topbar-sino__n"><?= $avisosNaoLidos > 9 ? '9+' : $avisosNaoLidos ?></span><?php endif; ?>
            </button>
          <?php endif; ?>
        </div>
      </header>

      <main class="content" id="conteudo">
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
  <script>
    // Tabelas no celular: cada linha vira um cartão (ver responsivo.css, seção 7). Aqui cada célula recebe o nome
    // da sua coluna (data-rotulo) e são marcadas a coluna principal (título do cartão) e a de ações.
    (function () {
      var PRINCIPAL = /^(os|nome|cliente|descri|fornecedor|produto|pe[cç]a|servi[cç]o|usu[aá]rio|item|sess[aã]o|oficina|t[eé]cnico|conta|ve[ií]culo)/i;
      function rotular(tabela) {
        if (tabela.dataset.semCartao !== undefined || tabela.dataset.rotulada) return;
        var linhaCab = tabela.tHead && tabela.tHead.rows[tabela.tHead.rows.length - 1];
        if (!linhaCab) return;
        var cab = [];
        [].forEach.call(linhaCab.cells, function (th) {
          var t = (th.textContent || '').replace(/\s+/g, ' ').trim();
          for (var k = 0; k < (th.colSpan || 1); k++) cab.push(t);
        });
        var principal = 0;
        if (!PRINCIPAL.test(cab[0] || '')) { for (var i = 1; i < cab.length; i++) if (PRINCIPAL.test(cab[i])) { principal = i; break; } }
        var ultimo = cab.length - 1, acoes = /^(a[cç][oõ]es)?$/i.test(cab[ultimo] || '') ? ultimo : -1;
        [].forEach.call(tabela.tBodies, function (tb) {
          [].forEach.call(tb.rows, function (tr) {
            var col = 0;
            [].forEach.call(tr.cells, function (td) {
              if (td.colSpan > 1 && tr.cells.length === 1) { col += td.colSpan; return; }
              if (col === principal) td.classList.add('td-principal');
              else if (col === acoes) td.classList.add('td-acoes');
              else if (cab[col]) td.setAttribute('data-rotulo', cab[col]);
              else td.classList.add('td-vazio');
              col += td.colSpan || 1;
            });
          });
        });
        tabela.classList.add('table--cartoes');
        tabela.dataset.rotulada = '1';
      }
      function rotularTudo(raiz) { (raiz || document).querySelectorAll('table.table').forEach(rotular); }
      window.rotularTabelas = rotularTudo;
      // Menus "⋯" fora de tabelas: se a lista abrir além da borda esquerda, ela passa a abrir para a direita.
      document.addEventListener('toggle', function (e) {
        var d = e.target;
        if (!d.matches || !d.matches('details.menu-mais') || !d.open || d.closest('.table-wrap') || window.innerWidth < 600) return;
        var l = d.querySelector('.menu-mais__lista');
        if (!l) return;
        l.classList.remove('is-a-direita');
        if (l.getBoundingClientRect().left < 8) l.classList.add('is-a-direita');
      }, true);
      rotularTudo();
      if (window.MutationObserver) {
        new MutationObserver(function (ms) {
          ms.forEach(function (m) { [].forEach.call(m.addedNodes, function (n) {
            if (n.nodeType !== 1) return;
            var t = n.closest && n.closest('table.table');
            if (t) { delete t.dataset.rotulada; rotular(t); } else if (n.querySelectorAll) rotularTudo(n);
          }); });
        }).observe(document.querySelector('.content') || document.body, { childList: true, subtree: true });
      }
    })();
  </script>
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
      if (recolher) (function () {
        // Recolher/expandir com movimento contínuo: a coluna anima pelo CSS e os itens que mudam de lugar
        // (ícones, títulos de grupo, logo, perfil) deslizam da posição antiga para a nova (técnica FLIP).
        var corpo = document.body, barra = document.querySelector('.sidebar'), ocupado = false;
        var ALVOS = '.sidebar__brand > a, .sidebar__retrair, .sidebar__rotulo, .sidebar__item, .sidebar__tema, .sidebar__perfil';
        var TEXTOS = '.sidebar__item .label, .sidebar__rotulo, .sidebar__logo-full, .sidebar__tema > span:not(.interruptor), .sidebar__perfil > a:first-child > div, .sidebar__sair';
        var CURVA = 'cubic-bezier(.32, .72, 0, 1)';
        var semAnimacao = function () {
          return !barra || !barra.animate || window.innerWidth <= 900 || window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        };
        var lista = function () { return [].slice.call(barra.querySelectorAll(ALVOS)); };
        var medir = function (els) { return els.map(function (el) { return el.getBoundingClientRect(); }); };
        var deslizar = function (els, antes, depois, dur, soVertical) {
          els.forEach(function (el, i) {
            var a = antes[i], b = depois[i];
            if (!a.width || !b.width) return;
            var dx = soVertical && soVertical(el) ? 0 : a.left - b.left, dy = a.top - b.top;
            if (Math.abs(dx) < .5 && Math.abs(dy) < .5) return;
            el.animate([{ transform: 'translate(' + dx + 'px,' + dy + 'px)' }, { transform: 'none' }], { duration: dur, easing: CURVA });
          });
        };
        var surgir = function (sel, atraso, dur) {
          [].forEach.call(barra.querySelectorAll(sel), function (el) {
            el.animate([{ opacity: 0 }, { opacity: 1 }], { duration: dur, delay: atraso, easing: 'ease-out', fill: 'backwards' });
          });
        };
        var recolherBarra = function () {
          corpo.classList.add('sidebar-fechando');               // a coluna encolhe e os textos esmaecem
          setTimeout(function () {
            var els = lista(), antes = medir(els);
            corpo.classList.add('sidebar-collapsed');
            corpo.classList.remove('sidebar-fechando');
            deslizar(els, antes, medir(els), 280);                // os ícones se acomodam no centro
            surgir('.sidebar__logo-icon, .sidebar__rotulo', 40, 240);
            setTimeout(function () { ocupado = false; }, 290);
          }, 330);
        };
        var expandirBarra = function () {
          var els = lista(), antes = medir(els);
          corpo.classList.add('sidebar-medindo');                 // mede a posição final sem transição
          corpo.classList.remove('sidebar-collapsed');
          var depois = medir(els);
          corpo.classList.add('sidebar-collapsed');
          void barra.offsetWidth;
          corpo.classList.remove('sidebar-medindo');
          void barra.offsetWidth;
          corpo.classList.remove('sidebar-collapsed');            // agora a coluna cresce com transição
          deslizar(els, antes, depois, 340, function (el) { return el.classList.contains('sidebar__retrair'); });
          surgir(TEXTOS, 90, 280);
          setTimeout(function () { ocupado = false; }, 350);
        };
        recolher.addEventListener('click', function () {
          if (ocupado) return;
          var r = !corpo.classList.contains('sidebar-collapsed');
          recolher.setAttribute('aria-label', r ? 'Expandir barra lateral' : 'Retrair barra lateral');
          try { localStorage.setItem('sidebar-collapsed', r); } catch (e) {}
          if (semAnimacao()) { corpo.classList.toggle('sidebar-collapsed', r); return; }
          ocupado = true;
          if (r) recolherBarra(); else expandirBarra();
        });
      })();

      var hamb = document.getElementById('mobileNavToggle');
      var fechar = function () {
        if (!document.body.classList.contains('mobile-nav-open')) return;
        document.body.classList.remove('mobile-nav-open');
        if (hamb) { hamb.setAttribute('aria-expanded', 'false'); hamb.focus({ preventScroll: true }); }
      };
      if (hamb) {
        hamb.setAttribute('aria-expanded', 'false');
        hamb.setAttribute('aria-controls', 'menu-principal');
        hamb.addEventListener('click', function (e) {
          e.stopPropagation();
          var aberto = document.body.classList.toggle('mobile-nav-open');
          hamb.setAttribute('aria-expanded', aberto ? 'true' : 'false');
          if (aberto) { var ativo = document.querySelector('.sidebar__item.is-active') || document.querySelector('.sidebar__item'); if (ativo) ativo.focus({ preventScroll: true }); }
        });
      }
      // Menu sobreposto: fecha com Esc e ao escolher um destino.
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fechar(); });
      document.querySelectorAll('.sidebar a').forEach(function (a) { a.addEventListener('click', function () { if (window.innerWidth <= 899) fechar(); }); });
      var fundo = document.getElementById('sidebarBackdrop');
      if (fundo) fundo.addEventListener('click', fechar);
      window.addEventListener('resize', function () { if (window.innerWidth > 900) fechar(); });

      // Criar: painel logo abaixo do botão. Abre e fecha no botão; fecha no Esc, clicando fora ou ao escolher.
      var btCriar = document.querySelector('[data-abrir-criar]'), pnCriar = document.getElementById('painel-criar');
      if (btCriar && pnCriar) {
        var abrirCriar = function (abrir) {
          pnCriar.hidden = !abrir;
          btCriar.setAttribute('aria-expanded', abrir ? 'true' : 'false');
          if (abrir) { var p = pnCriar.querySelector('[role=menuitem]'); if (p) p.focus({ preventScroll: true }); }
        };
        btCriar.addEventListener('click', function (e) { e.stopPropagation(); abrirCriar(pnCriar.hidden); });
        document.addEventListener('click', function (e) { if (!pnCriar.hidden && !pnCriar.contains(e.target)) abrirCriar(false); });
        document.addEventListener('keydown', function (e) {
          if (pnCriar.hidden) return;
          if (e.key === 'Escape') { abrirCriar(false); btCriar.focus(); }
          // Setas para cima/baixo andam entre as opções.
          if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            var itens = Array.prototype.slice.call(pnCriar.querySelectorAll('[role=menuitem]')), k = itens.indexOf(document.activeElement);
            k = e.key === 'ArrowDown' ? (k + 1) % itens.length : (k - 1 + itens.length) % itens.length;
            itens[k].focus(); e.preventDefault();
          }
        });
      }

      // Avisos: painel simples ancorado no sininho (não é janela modal: sem véu, sem travar a página).
      // O sininho abre e fecha; também fecha com Esc, com o X ou clicando fora.
      var dlgAvisos = document.getElementById('dlg-avisos');
      var sino = document.querySelector('[data-abrir-avisos]');
      if (dlgAvisos && sino) {
        sino.setAttribute('aria-haspopup', 'true');
        sino.setAttribute('aria-expanded', 'false');
        var abrirAvisos = function () { if (!dlgAvisos.open) { dlgAvisos.show(); sino.setAttribute('aria-expanded', 'true'); } };
        var fecharAvisos = function () { if (dlgAvisos.open) dlgAvisos.close(); };
        sino.addEventListener('click', function (e) { e.stopPropagation(); dlgAvisos.open ? fecharAvisos() : abrirAvisos(); });
        dlgAvisos.addEventListener('close', function () { sino.setAttribute('aria-expanded', 'false'); });
        document.addEventListener('click', function (e) { if (dlgAvisos.open && !dlgAvisos.contains(e.target)) fecharAvisos(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && dlgAvisos.open) { fecharAvisos(); sino.focus(); } });
        dlgAvisos.querySelector('[data-fechar-avisos]').addEventListener('click', fecharAvisos);
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
