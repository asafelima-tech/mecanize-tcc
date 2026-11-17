<?php
/**
 * Layout das telas de quem está logado: navegação global no topo (com painéis por grupo), barra da seção,
 * o conteúdo e um rodapé discreto.
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

// Navegação global (estilo barra do topo): os grupos do menu viram itens com um painel que abre abaixo,
// com as telas do grupo em letras grandes e, ao lado, os atalhos de criação do mesmo assunto. Tudo filtrado pela permissão.
$podeAbrir = fn(string $rota) => isset($rotas[$rota]) && Permissao::permite($perfil, $rotas[$rota][2]);
$criarPorGrupo = [
    'Operação'   => [['os-nova', '', 'Ordem de serviço'], ['agenda-form', '', 'Agendamento']],
    'Financeiro' => [['conta-form', 'tipo=RECEBER', 'Conta a receber'], ['conta-form', 'tipo=PAGAR', 'Conta a pagar']],
    'Cadastros'  => [['cliente-form', '', 'Cliente'], ['veiculo-form', '', 'Veículo'], ['produto-form', 'tipo=PECA', 'Peça'],
                     ['produto-form', 'tipo=SERVICO', 'Serviço'], ['fornecedor-form', '', 'Fornecedor'], ['estoque-entrada', '', 'Entrada de peças']],
    'Gestão'     => [['usuario-form', '', 'Usuário']],
    'Plataforma' => [['plataforma-nova', '', 'Oficina']],
];
$gruposNav = [];
foreach ($menu as $item) {
    if (!$podeAbrir($item['rota'])) continue;
    $item['ativo'] = $rotaAtual === $item['rota'] || in_array($rotaAtual, $item['rotas'] ?? [], true);
    $chave = $item['grupo'] === '' ? '_solto' . count($gruposNav) : $item['grupo'];
    if (!isset($gruposNav[$chave])) {
        $gruposNav[$chave] = ['nome' => $item['grupo'], 'itens' => [], 'ativo' => false,
            'criar' => array_values(array_filter($criarPorGrupo[$item['grupo']] ?? [], fn($c) => $podeAbrir($c[0])))];
    }
    $gruposNav[$chave]['itens'][] = $item;
    $gruposNav[$chave]['ativo'] = $gruposNav[$chave]['ativo'] || $item['ativo'];
}
$gruposNav = array_values($gruposNav);
// Barra da seção (abaixo da navegação): o nome da tela do menu e as telas irmãs do mesmo grupo.
$irmaos = $itemAtual ? array_values(array_filter($menu, fn($i) => $i['grupo'] !== '' && $i['grupo'] === $itemAtual['grupo'] && $podeAbrir($i['rota']))) : [];

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
  <link rel="stylesheet" href="./css/identidade.css?v=<?= (int) @filemtime(V2_RAIZ . '/public/css/identidade.css') ?>">
  <script>
    // Tema antes de desenhar a tela (sem "piscar" claro → escuro).
    (function () {
      try {
        var salvo = localStorage.getItem('mecanize-theme');
        if (salvo === 'dark' || (!salvo && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
          document.documentElement.setAttribute('data-theme', 'dark');
        }
      } catch (e) {}
    })();
  </script>
</head>

<body>
  <a class="pular" href="#conteudo">Pular para o conteúdo</a>
  <header class="gn" id="gn">
    <nav class="gn__barra" aria-label="Menu principal">
      <a class="gn__marca" href="./<?= e(Permissao::inicio($perfil)) ?>" aria-label="Mecanize — início">
        <img class="gn__logo gn__logo--claro" src="./img/logo.png" alt="Mecanize">
        <img class="gn__logo gn__logo--escuro" src="./img/logo-branco.png" alt="" aria-hidden="true">
      </a>

      <ul class="gn__itens" id="gn-itens">
        <?php foreach ($gruposNav as $g => $grupo): $temAtivo = $grupo['ativo']; ?>
          <?php if ($grupo['nome'] === ''): foreach ($grupo['itens'] as $item): $ativo = $item['ativo']; ?>
            <li class="gn__item gn__item--solto"><a class="gn__link<?= $ativo ? ' is-ativo' : '' ?>" href="./<?= e($item['rota']) ?>" <?= $ativo ? 'aria-current="page"' : '' ?>><?= e($item['rotulo']) ?></a></li>
          <?php endforeach; else: ?>
            <li class="gn__item" data-gn-item>
              <button type="button" class="gn__link<?= $temAtivo ? ' is-ativo' : '' ?>" aria-expanded="false" aria-controls="gn-fly-<?= $g ?>"><?= e($grupo['nome']) ?></button>
              <div class="gn__flyout" id="gn-fly-<?= $g ?>">
                <div class="gn__flyout-in">
                  <div class="gn__col gn__col--principal">
                    <h2 class="gn__col-titulo"><?= e($grupo['nome']) ?></h2>
                    <ul>
                      <?php foreach ($grupo['itens'] as $item): ?>
                        <li><a class="gn__grande<?= $item['ativo'] ? ' is-ativo' : '' ?>" href="./<?= e($item['rota']) ?>" <?= $item['ativo'] ? 'aria-current="page"' : '' ?>><?= e($item['rotulo']) ?></a></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                  <?php if ($grupo['criar']): ?>
                    <div class="gn__col">
                      <h2 class="gn__col-titulo">Criar</h2>
                      <ul>
                        <?php foreach ($grupo['criar'] as [$rota, $query, $rotulo]): ?>
                          <li><a class="gn__pequeno" href="./<?= e($rota) ?><?= $query !== '' ? '?' . e($query) : '' ?>"><?= e($rotulo) ?></a></li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ul>

      <div class="gn__direita">
        <?php require __DIR__ . '/_criar.php'; ?>
        <?php if ($oficina): ?>
          <a class="gn-caixa<?= $caixaAberto ? ' is-aberto' : '' ?>" href="./caixa" title="<?= $caixaAberto ? 'Caixa aberto desde ' . e(data_br($caixaAberto['aberto_em'], true)) : 'Caixa fechado' ?>">
            <span class="gn-caixa__ponto" aria-hidden="true"></span><span class="gn-caixa__texto"><?= $caixaAberto ? 'Caixa aberto' : 'Caixa fechado' ?></span>
          </a>
          <button type="button" class="gn-icone topbar-sino" data-abrir-avisos aria-haspopup="dialog" aria-controls="dlg-avisos" aria-label="Avisos<?= $avisosNaoLidos ? ' (' . $avisosNaoLidos . ' não lidos)' : '' ?>" title="Avisos">
            <?= icone('bell', 17) ?><?php if ($avisosNaoLidos): ?><span class="topbar-sino__n"><?= $avisosNaoLidos > 9 ? '9+' : $avisosNaoLidos ?></span><?php endif; ?>
          </button>
        <?php endif; ?>
        <div class="gn__item gn__item--conta" data-gn-item>
          <button type="button" class="gn-avatar" aria-expanded="false" aria-controls="gn-fly-conta" aria-label="Sua conta: <?= e($usuario['nome']) ?>" title="<?= e($usuario['nome']) ?>"><?= e($iniciais) ?></button>
          <div class="gn__flyout gn__flyout--conta" id="gn-fly-conta">
            <div class="gn-conta">
              <div class="gn-conta__quem">
                <span class="gn-conta__avatar" aria-hidden="true"><?= e($iniciais) ?></span>
                <div><strong><?= e($usuario['nome']) ?></strong><span><?= e($nomesPerfil[$perfil] ?? $perfil) ?><?= $oficina ? ' · ' . e($oficina['nome']) : '' ?></span></div>
              </div>
              <a class="gn-conta__opcao" href="<?= $oficina ? './perfil' : './plataforma' ?>"><?= icone('user', 16) ?> Meu perfil</a>
              <button type="button" class="gn-conta__opcao" id="darkToggle" aria-label="Alternar tema escuro"><?= icone('eye', 16) ?> <span>Tema escuro</span><span class="interruptor" aria-hidden="true"></span></button>
              <a class="gn-conta__opcao" href="./sair"><?= icone('logout', 16) ?> Sair</a>
            </div>
          </div>
        </div>
        <button type="button" class="gn-icone gn__hamburger" id="mobileNavToggle" aria-label="Abrir menu" aria-expanded="false" aria-controls="gn-itens"><?= icone('menu', 18) ?></button>
      </div>
    </nav>
  </header>
  <div class="gn-veu" id="gn-veu" aria-hidden="true"></div>

  <?php if ($itemAtual && $itemAtual['grupo'] !== ''): ?>
    <div class="ln">
      <div class="ln__in">
        <a class="ln__titulo" href="./<?= e($itemAtual['rota']) ?>"><?= e($itemAtual['rotulo']) ?></a>
        <?php if (count($irmaos) > 1): ?>
          <nav class="ln__links" aria-label="<?= e($itemAtual['grupo'] ?: 'Seção') ?>">
            <?php foreach ($irmaos as $ir): ?>
              <a href="./<?= e($ir['rota']) ?>"<?= $ir['rota'] === $itemAtual['rota'] ? ' class="is-ativo" aria-current="true"' : '' ?>><?= e($ir['rotulo']) ?></a>
            <?php endforeach; ?>
          </nav>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

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

  <footer class="rodape-app">
    <div class="rodape-app__in">
      <span>Mecanize<?= $oficina ? ' · ' . e($oficina['nome']) : '' ?></span>
      <span>Conectado como <?= e($usuario['nome']) ?> · <?= e($nomesPerfil[$perfil] ?? $perfil) ?></span>
      <span>© <?= date('Y') ?> Mecanize</span>
    </div>
  </footer>
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

      // Navegação global: cada grupo abre o seu painel ao passar o mouse (com uma pequena espera) ou ao clicar;
      // fecha ao sair da barra, com Esc ou clicando fora. No celular, o botão de menu abre a lista inteira.
      var gn = document.getElementById('gn'), veu = document.getElementById('gn-veu');
      var itens = Array.prototype.slice.call(document.querySelectorAll('[data-gn-item]'));
      var timer = null, aberto = null;
      var desktop = function () { return window.matchMedia('(min-width: 1025px)').matches; };
      function abrir(li) {
        if (aberto === li) return;
        if (aberto) fecharItem(aberto);
        aberto = li;
        li.classList.add('is-aberto');
        li.querySelector('[aria-expanded]').setAttribute('aria-expanded', 'true');
        document.body.classList.toggle('gn-aberto', !li.classList.contains('gn__item--conta'));
      }
      function fecharItem(li) {
        li.classList.remove('is-aberto');
        li.querySelector('[aria-expanded]').setAttribute('aria-expanded', 'false');
      }
      function fecharTudo() {
        clearTimeout(timer);
        if (aberto) fecharItem(aberto);
        aberto = null;
        document.body.classList.remove('gn-aberto');
      }
      itens.forEach(function (li) {
        var bt = li.querySelector('[aria-expanded]');
        bt.addEventListener('click', function (e) { e.stopPropagation(); if (aberto === li) fecharTudo(); else abrir(li); });
        if (!li.classList.contains('gn__item--conta')) {
          li.addEventListener('mouseenter', function () { if (!desktop()) return; clearTimeout(timer); timer = setTimeout(function () { abrir(li); }, aberto ? 0 : 140); });
        }
      });
      if (gn) gn.addEventListener('mouseleave', function () { if (!desktop() || !aberto || aberto.classList.contains('gn__item--conta')) return; clearTimeout(timer); timer = setTimeout(fecharTudo, 180); });
      if (gn) gn.addEventListener('mouseenter', function () { if (aberto && !aberto.classList.contains('gn__item--conta')) clearTimeout(timer); });
      document.addEventListener('click', function (e) { if (aberto && !aberto.contains(e.target)) fecharTudo(); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && aberto) { var b = aberto.querySelector('[aria-expanded]'); fecharTudo(); b.focus(); } });
      if (veu) veu.addEventListener('click', fecharTudo);

      var hamb = document.getElementById('mobileNavToggle');
      var fecharMenu = function () { document.body.classList.remove('mobile-nav-open'); if (hamb) hamb.setAttribute('aria-expanded', 'false'); };
      if (hamb) hamb.addEventListener('click', function (e) {
        e.stopPropagation();
        var a = document.body.classList.toggle('mobile-nav-open');
        hamb.setAttribute('aria-expanded', a ? 'true' : 'false');
        hamb.setAttribute('aria-label', a ? 'Fechar menu' : 'Abrir menu');
      });
      window.addEventListener('resize', function () { if (desktop()) fecharMenu(); });

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
