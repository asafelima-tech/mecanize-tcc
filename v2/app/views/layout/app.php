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
  <link rel="stylesheet" href="./css/tecnico.css?v=<?= (int) @filemtime(V2_RAIZ . '/public/css/tecnico.css') ?>">
  <script>
    // Tema antes de desenhar a tela (sem "piscar" claro → escuro).
    (function () {
      try {
        var salvo = localStorage.getItem('mecanize-theme');
        if (salvo === 'dark' || (!salvo && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
          document.documentElement.setAttribute('data-theme', 'dark');
        }
      } catch (e) {}
      // Barra lateral (computador): "aberta" (com a largura escolhida), "trilho" (só ícones) ou "oculta".
      // Aplicada antes de desenhar, sem animação, para a tela já abrir como a pessoa deixou.
      var h = document.documentElement, estado = 'aberta', largura = 248;
      try {
        var s = JSON.parse(localStorage.getItem('mecanize-lateral') || 'null');
        if (s && /^(aberta|trilho|oculta)$/.test(s.estado)) estado = s.estado;
        if (s && s.largura >= 208 && s.largura <= 248) largura = Math.round(s.largura);
        if (!s && localStorage.getItem('sidebar-collapsed') === 'true') estado = 'trilho';
      } catch (e) {}
      h.setAttribute('data-lateral', estado);
      h.style.setProperty('--lat-aberta', largura + 'px');
      h.style.setProperty('--lat', (estado === 'aberta' ? largura : estado === 'trilho' ? 68 : 0) + 'px');
      h.classList.add('lat-carregando');
    })();
  </script>
</head>

<body<?= $perfil === 'TECNICO' ? ' class="perfil-tecnico"' : '' ?>>
  <a class="pular" href="#conteudo">Pular para o conteúdo</a>
  <div class="app">
    <aside class="sidebar" id="menu-principal" aria-label="Menu principal">
      <div class="sidebar__brand">
        <a href="./<?= e(Permissao::inicio($perfil)) ?>" aria-label="Mecanize, início" title="Início">
          <img class="sidebar__logo-full logo-p-claro" src="./img/logo.png<?= $logoV ?? '' ?>" data-recorte="logo" alt="Mecanize">
          <img class="sidebar__logo-full logo-p-escuro" src="./img/logo-branco.png<?= $logoV ?? '' ?>" data-recorte="logo" alt="" aria-hidden="true">
          <img class="sidebar__logo-icon logo-p-claro" src="./img/logo.png<?= $logoV ?? '' ?>" data-recorte="simbolo" alt="Mecanize">
          <img class="sidebar__logo-icon logo-p-escuro" src="./img/logo-branco.png<?= $logoV ?? '' ?>" data-recorte="simbolo" alt="" aria-hidden="true">
        </a>
        <button type="button" class="sidebar__retrair" id="sidebarCollapseBtn" aria-controls="menu-principal" aria-expanded="true" aria-label="Recolher menu" title="Recolher menu (Ctrl+B)"><?= icone('sidebar-collapse', 20) ?></button>
        <button type="button" class="sidebar__abrir" id="sidebarExpandBtn" aria-controls="menu-principal" aria-expanded="false" aria-label="Expandir menu" title="Expandir menu (Ctrl+B)"><?= icone('sidebar-expand', 20) ?></button>
      </div>

      <?php $grupoAtual = null; foreach ($menu as $item):
        if (!isset($rotas[$item['rota']]) || !Permissao::permite($perfil, $rotas[$item['rota']][2]) || in_array($perfil, $item['esconder'] ?? [], true)) continue;
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
        <button type="button" class="sidebar__tema" id="darkToggle" aria-label="Ativar tema escuro" title="Ativar tema escuro"><i class="tema-icone" aria-hidden="true"><?= icone('moon', 20, 1.5, 'tema-icone__lua') ?><?= icone('sun', 20, 1.5, 'tema-icone__sol') ?></i><span class="interruptor" aria-hidden="true"></span><span class="tema-rotulo"><span class="tema-rotulo__escuro">Tema escuro</span><span class="tema-rotulo__claro">Tema claro</span></span></button>
        <div class="sidebar__perfil">
          <a href="<?= $oficina ? './perfil' : './plataforma' ?>" title="Meu perfil<?= $oficina ? ' · ' . e($oficina['nome']) : '' ?>">
            <span class="sidebar__avatar"><?= e($iniciais) ?></span>
            <div style="min-width:0"><div class="sidebar__nome"><?= e($usuario['nome']) ?></div><div class="sidebar__papel"><?= e($nomesPerfil[$perfil] ?? $perfil) ?></div></div>
          </a>
          <a href="./sair" class="sidebar__sair" aria-label="Sair" title="Sair"><?= icone('logout', 16) ?></a>
        </div>
      </div>
    </aside>
    <div class="lateral-alca" id="lateralAlca" role="separator" tabindex="0" aria-orientation="vertical" aria-controls="menu-principal"
         aria-label="Largura da barra lateral" aria-valuemin="0" aria-valuemax="248" title="Arraste para ajustar · clique duplo restaura"></div>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="main">
      <header class="topbar">
        <button type="button" class="topbar__hamburger" id="mobileNavToggle" aria-label="Abrir menu"><?= icone('menu', 20) ?></button>
        <button type="button" class="topbar__lateral" id="lateralMostrar" aria-controls="menu-principal" aria-expanded="false" aria-label="Mostrar menu" title="Mostrar menu (Ctrl+B)"><?= icone('sidebar-expand', 20) ?></button>
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
  <?php if ($perfil === 'TECNICO'): ?>
    <!-- Técnico no celular: as três telas dele numa barra embaixo, ao alcance do polegar (some no computador). -->
    <nav class="tec-barra" aria-label="Navegação do técnico">
      <?php foreach ([['servicos', ['servico'], 'mechanic', 'Meus serviços'], ['pedidos', [], 'send', 'Pedidos de material'], ['perfil', [], 'user', 'Perfil']] as [$r, $filhas, $ic, $rot]):
        $na = $rotaAtual === $r || in_array($rotaAtual, $filhas, true); ?>
        <a href="./<?= $r ?>"<?= $na ? ' class="ativa" aria-current="page"' : '' ?>><?= icone($ic, 22, $na ? 2 : 1.5) ?><span><?= $rot ?></span></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>
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
      // O ícone mostra o destino do clique: lua no claro (vai para o escuro), sol no escuro (volta para o claro).
      function rotuloTema() {
        if (!tema) return;
        var dica = html.getAttribute('data-theme') === 'dark' ? 'Ativar tema claro' : 'Ativar tema escuro';
        tema.setAttribute('aria-label', dica); tema.title = dica;
      }
      rotuloTema();
      if (tema) tema.addEventListener('click', function () {
        var escuro = html.getAttribute('data-theme') !== 'dark';
        html.setAttribute('data-theme', escuro ? 'dark' : 'light');
        try { localStorage.setItem('mecanize-theme', escuro ? 'dark' : 'light'); } catch (e) {}
        rotuloTema();
      });

      // Barra lateral (computador): três estados — "aberta" (largura ajustável, de 208 a 248 px), "trilho" (68 px, só
      // ícones) e "oculta". Tudo sai da variável --lat (registrada no CSS com @property): coluna, barra e alça se movem
      // juntas numa única transição, sem saltos. Arrastar a alça (borda direita) ajusta a largura; ao soltar perto do
      // trilho ou da borda, a barra se encaixa no estado mais próximo. Teclado: Ctrl+B alterna; na alça, setas, Home e End.
      (function () {
        var barra = document.getElementById('menu-principal'), alca = document.getElementById('lateralAlca');
        var btRecolher = document.getElementById('sidebarCollapseBtn'), btExpandir = document.getElementById('sidebarExpandBtn');
        var btMostrar = document.getElementById('lateralMostrar');
        if (!barra || !alca) return;
        var PADRAO = 248, MIN = 208, MAX = 248, TRILHO = 68, TEXTO = 150;
        var computador = window.matchMedia('(min-width: 900px)');
        var estado = html.getAttribute('data-lateral') || 'aberta';
        var largura = parseFloat(html.style.getPropertyValue('--lat-aberta')) || PADRAO;
        var limitar = function (v) { return Math.round(Math.min(MAX, Math.max(MIN, v))); };
        var alvo = function () { return estado === 'aberta' ? largura : estado === 'trilho' ? TRILHO : 0; };
        var atual = function () { var v = parseFloat(getComputedStyle(html).getPropertyValue('--lat')); return isNaN(v) ? alvo() : v; };
        // Último estado visível ("aberta" ou "trilho"): é para ele que a barra volta quando estava oculta.
        var ultimo = estado === 'oculta' ? 'aberta' : estado;
        try { var salvo = JSON.parse(localStorage.getItem('mecanize-lateral') || 'null'); if (salvo && /^(aberta|trilho)$/.test(salvo.ultimo)) ultimo = salvo.ultimo; } catch (e) {}

        var descrever = function () {
          alca.setAttribute('aria-valuenow', alvo());
          alca.setAttribute('aria-valuetext', estado === 'aberta' ? 'Aberta, ' + largura + ' pixels' : estado === 'trilho' ? 'Só ícones' : 'Oculta');
          if (btRecolher) btRecolher.setAttribute('aria-expanded', estado === 'aberta' ? 'true' : 'false');
          if (btExpandir) btExpandir.setAttribute('aria-expanded', estado === 'aberta' ? 'true' : 'false');
          if (btMostrar) btMostrar.setAttribute('aria-expanded', estado === 'oculta' ? 'false' : 'true');
        };
        // O botão que fica visível no novo estado (para onde o foco vai quando o anterior some).
        var botaoDe = function (e) { return e === 'aberta' ? btRecolher : e === 'trilho' ? btExpandir : btMostrar; };
        var aplicar = function (novo, novaLargura, teclado) {
          if (novaLargura != null) largura = limitar(novaLargura);
          var ativo = document.activeElement;
          var focoNoBotao = ativo && (ativo === btRecolher || ativo === btExpandir || ativo === btMostrar);
          var focoDentro = barra.contains(ativo);
          estado = novo;
          if (estado !== 'oculta') ultimo = estado;
          html.setAttribute('data-lateral', estado);
          html.style.setProperty('--lat-aberta', largura + 'px');
          html.style.setProperty('--lat', alvo() + 'px');
          try { localStorage.setItem('mecanize-lateral', JSON.stringify({ estado: estado, largura: largura, ultimo: ultimo })); localStorage.removeItem('sidebar-collapsed'); } catch (e) {}
          descrever();
          // Pelo teclado, o foco segue para o botão que continua visível; com o mouse, o botão só perde o foco (sem anel).
          var destino = botaoDe(estado);
          if (focoNoBotao && ativo !== destino) { if (teclado && destino) destino.focus({ preventScroll: true }); else ativo.blur(); }
          else if (estado === 'oculta' && focoDentro && btMostrar) btMostrar.focus({ preventScroll: true });
        };
        var mostrar = function (teclado) { aplicar(ultimo === 'trilho' ? 'trilho' : 'aberta', null, teclado); };
        var alternar = function (teclado) { if (estado === 'oculta') mostrar(teclado); else aplicar(estado === 'aberta' ? 'trilho' : 'aberta', null, teclado); };

        // detail 0 = clique vindo do teclado (Enter/Espaço).
        if (btRecolher) btRecolher.addEventListener('click', function (e) { aplicar('trilho', null, e.detail === 0); });
        if (btExpandir) btExpandir.addEventListener('click', function (e) { aplicar('aberta', null, e.detail === 0); });
        if (btMostrar) btMostrar.addEventListener('click', function (e) { mostrar(e.detail === 0); });
        document.addEventListener('keydown', function (e) {
          if (!computador.matches || e.altKey || e.shiftKey || !(e.ctrlKey || e.metaKey) || String(e.key).toLowerCase() !== 'b') return;
          var t = e.target;
          if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
          e.preventDefault();
          alternar(true);
        });

        // Arrastar: a barra segue o cursor sem transição; ao soltar, encaixa com transição.
        var arraste = null, quadro = 0;
        var desenhar = function () {
          quadro = 0;
          if (!arraste) return;
          var x = arraste.x;
          if (x > MAX) x = MAX + Math.min(32, (x - MAX) * .2);        // além do máximo, com resistência
          x = Math.max(0, x);
          html.style.setProperty('--lat', x + 'px');
          html.setAttribute('data-lateral', x >= TEXTO ? 'aberta' : 'trilho');
        };
        alca.addEventListener('pointerdown', function (e) {
          if (e.button !== 0 || !computador.matches) return;
          e.preventDefault();
          var lat = atual();                                           // onde estiver, mesmo no meio de uma transição
          arraste = { id: e.pointerId, dx: e.clientX - lat, x0: e.clientX, x: lat, moveu: false };
          try { alca.setPointerCapture(e.pointerId); } catch (er) {}
          html.classList.add('lat-arrastando');
          html.style.setProperty('--lat', lat + 'px');
        });
        alca.addEventListener('pointermove', function (e) {
          if (!arraste || e.pointerId !== arraste.id) return;
          if (!arraste.moveu && Math.abs(e.clientX - arraste.x0) < 3) return;
          arraste.moveu = true;
          arraste.x = e.clientX - arraste.dx;
          if (!quadro) quadro = requestAnimationFrame(desenhar);
        });
        var soltar = function (e) {
          if (!arraste || (e && e.pointerId !== arraste.id)) return;
          var a = arraste;
          if (quadro) { cancelAnimationFrame(quadro); desenhar(); }
          arraste = null;
          html.classList.remove('lat-arrastando');
          void getComputedStyle(html).getPropertyValue('--lat');       // a transição parte de onde a barra está
          if (!a.moveu) { aplicar(estado); return; }
          if (a.x < TRILHO / 2) aplicar('oculta');
          else if (a.x < (TRILHO + MIN) / 2) aplicar('trilho');
          else aplicar('aberta', a.x);
        };
        alca.addEventListener('pointerup', soltar);
        alca.addEventListener('pointercancel', soltar);
        alca.addEventListener('lostpointercapture', soltar);
        alca.addEventListener('dblclick', function () { aplicar('aberta', PADRAO); });
        alca.addEventListener('keydown', function (e) {
          var passo = e.shiftKey ? 48 : 16;
          if (e.key === 'ArrowRight') {
            if (estado === 'oculta') aplicar('trilho');
            else if (estado === 'trilho') aplicar('aberta');
            else aplicar('aberta', largura + passo);
          } else if (e.key === 'ArrowLeft') {
            if (estado === 'aberta') { if (largura - passo < MIN) aplicar('trilho'); else aplicar('aberta', largura - passo); }
            else if (estado === 'trilho') aplicar('oculta');
          } else if (e.key === 'Home') aplicar('trilho');
          else if (e.key === 'End') aplicar('aberta', MAX);
          else if (e.key === 'Enter' || e.key === ' ') alternar(true);
          else return;
          e.preventDefault();
        });

        descrever();
        requestAnimationFrame(function () { requestAnimationFrame(function () { html.classList.remove('lat-carregando'); }); });
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
      document.querySelectorAll('.sidebar a').forEach(function (a) { a.addEventListener('click', function () { if (window.innerWidth < 900) fechar(); }); });
      var fundo = document.getElementById('sidebarBackdrop');
      if (fundo) fundo.addEventListener('click', fechar);
      window.addEventListener('resize', function () { if (window.innerWidth >= 900) fechar(); });

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

      // Avisos. No computador: painel simples ancorado no sininho (não é janela modal: sem véu, sem travar a página);
      // o sininho abre e fecha; também fecha com Esc, com o X ou clicando fora. No celular (até 900 px): folha modal
      // que sobe de baixo (#dlg-avisos-celular), fecha com o X, com Esc ou tocando no fundo escurecido.
      var dlgAvisos = document.getElementById('dlg-avisos');
      var dlgCelular = document.getElementById('dlg-avisos-celular');
      var sino = document.querySelector('[data-abrir-avisos]');
      var celular = window.matchMedia('(max-width: 899.98px)');
      if (dlgAvisos && sino) {
        sino.setAttribute('aria-haspopup', 'true');
        sino.setAttribute('aria-expanded', 'false');
        var abrirAvisos = function () { if (!dlgAvisos.open) { dlgAvisos.show(); sino.setAttribute('aria-expanded', 'true'); } };
        var fecharAvisos = function () { if (dlgAvisos.open) dlgAvisos.close(); };
        var abrirCelular = function () { if (dlgCelular && !dlgCelular.open) { dlgCelular.showModal(); sino.setAttribute('aria-expanded', 'true'); } };
        var fecharCelular = function () {
          if (!dlgCelular || !dlgCelular.open || dlgCelular.classList.contains('is-saindo')) return;
          dlgCelular.classList.add('is-saindo');
          setTimeout(function () { dlgCelular.classList.remove('is-saindo'); dlgCelular.close(); }, 180);
        };
        sino.addEventListener('click', function (e) {
          e.stopPropagation();
          if (celular.matches && dlgCelular) { abrirCelular(); return; }
          dlgAvisos.open ? fecharAvisos() : abrirAvisos();
        });
        dlgAvisos.addEventListener('close', function () { sino.setAttribute('aria-expanded', 'false'); });
        document.addEventListener('click', function (e) { if (dlgAvisos.open && !dlgAvisos.contains(e.target)) fecharAvisos(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && dlgAvisos.open) { fecharAvisos(); sino.focus(); } });
        dlgAvisos.querySelector('[data-fechar-avisos]').addEventListener('click', fecharAvisos);
        if (dlgCelular) {
          dlgCelular.addEventListener('close', function () { sino.setAttribute('aria-expanded', 'false'); sino.focus({ preventScroll: true }); });
          dlgCelular.addEventListener('cancel', function (e) { e.preventDefault(); fecharCelular(); });
          dlgCelular.addEventListener('click', function (e) { if (e.target === dlgCelular) fecharCelular(); }); // toque no fundo
          dlgCelular.querySelector('[data-fechar-avisos-celular]').addEventListener('click', fecharCelular);
        }
        if (location.hash === '#avisos') { history.replaceState(null, '', location.pathname + location.search); celular.matches && dlgCelular ? abrirCelular() : abrirAvisos(); }

        // "Marcar todos como lidos" sem sair da página (sem JS, o formulário volta para cá). Vale para as duas janelas.
        var marcarLidos = function () {
          dlgAvisos.querySelectorAll('.aviso--novo').forEach(function (a) { a.classList.remove('aviso--novo'); var p = a.querySelector('.ponto-novo'); if (p) p.remove(); });
          var r1 = dlgAvisos.querySelector('[data-avisos-resumo]'); if (r1) r1.textContent = 'Tudo lido';
          var f1 = dlgAvisos.querySelector('[data-avisos-ler-todas]'); if (f1) f1.remove();
          if (dlgCelular) {
            dlgCelular.querySelectorAll('.aviso2--novo').forEach(function (a) { a.classList.remove('aviso2--novo'); var p = a.querySelector('.aviso2__ponto'); if (p) p.remove(); });
            var g = dlgCelular.querySelector('[data-avisos-grupo="novos"] h4'); if (g) g.textContent = 'Recentes';
            var r2 = dlgCelular.querySelector('[data-avisos-resumo-celular]'); if (r2) r2.textContent = 'Tudo em dia';
            var f2 = dlgCelular.querySelector('[data-avisos-ler-todas-celular]'); if (f2) f2.remove();
          }
          var n = sino.querySelector('.topbar-sino__n'); if (n) n.remove();
          sino.setAttribute('aria-label', 'Avisos');
        };
        [dlgAvisos.querySelector('[data-avisos-ler-todas]'), dlgCelular && dlgCelular.querySelector('[data-avisos-ler-todas-celular]')].forEach(function (form) {
          if (form && window.fetch) form.addEventListener('submit', function (e) {
            e.preventDefault();
            fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
              .then(function (r) { if (!r.ok) throw r; marcarLidos(); })
              .catch(function () { form.submit(); });
          });
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
      if (window.Mecanize && window.Mecanize.abrirSeJanela && window.Mecanize.abrirSeJanela(tr.dataset.href)) return;
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

    // Máscaras: data-mascara="telefone|cep|placa|documento|cpf|cnpj". aplicarMascaras(raiz) também vale para o
    // conteúdo que chega depois (as janelas de criar e editar, js/modal.js).
    if (window.IMask) {
      var mascaras = {
        telefone: { mask: [{ mask: '(00) 0000-0000' }, { mask: '(00) 00000-0000' }] },
        cep: { mask: '00000-000' },
        cpf: { mask: '000.000.000-00' },
        cnpj: { mask: '00.000.000/0000-00' },
        documento: { mask: [{ mask: '000.000.000-00' }, { mask: '00.000.000/0000-00' }] },
        placa: { mask: [{ mask: 'aaa-0000' }, { mask: 'aaa0a00' }], prepare: function (s) { return s.toUpperCase(); } }
      };
      window.aplicarMascaras = function (raiz) {
        (raiz || document).querySelectorAll('[data-mascara]').forEach(function (el) {
          var m = mascaras[el.dataset.mascara];
          if (m && !el._mascara) el._mascara = IMask(el, m);
        });
      };
      window.aplicarMascaras(document);
    }
  </script>
  <script src="<?= e(asset('js/csrf.js')) ?>"></script>
  <script src="./js/modal.js?v=<?= (int) @filemtime(V2_RAIZ . '/public/js/modal.js') ?>"></script>
</body>

</html>
