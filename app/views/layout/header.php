<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$rotaAtual = $_GET['url'] ?? 'home';

function menuAtivo($rota, $rotaAtual)
{
  return $rota === $rotaAtual ? 'is-active' : '';
}

// Quem opera o caixa: o indicador da topbar e o item "Caixa" no menu só
// aparecem para esses papéis. O técnico não mexe com dinheiro, então mostrar
// a ele um botão que leva a uma tela sem permissão seria só um beco sem saída.
$operaCaixa = in_array($_SESSION['user_tipo'] ?? '', ['ADMIN', 'ATENDENTE'], true);

// Estado atual do caixa (para o indicador da topbar / sidebar)
$caixaStatus = null;
if (isset($_SESSION['logged']) && $_SESSION['logged'] === true && $operaCaixa) {
  require_once __DIR__ . '/../../models/Caixa.php';
  try {
    $caixaStatus = (new Caixa())->getCaixaAberto();
  } catch (\Throwable $e) {
    $caixaStatus = null;
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
  <title>Mecanize</title>
  <link rel="icon" type="image/ico" href="./assets/img/logo-branco.ico">
  <?php $v = '?v=' . time(); ?>
  <!--
    Bibliotecas servidas pelo próprio projeto (public/assets/vendor/), não mais
    por CDN. Duas razões: um <script> apontando pra domínio de terceiro executa
    na página com acesso total à sessão do usuário, e quem publica lá pode
    trocar o conteúdo do arquivo a qualquer momento sem ninguém aqui saber
    (o antigo "chart.js" vinha sem número de versão, então qualquer release
    nova entrava sozinha no sistema). De quebra, o Mecanize passa a funcionar
    sem internet.

    Duas bibliotecas foram removidas em vez de baixadas, por não terem nenhum
    uso no código: Font Awesome (nenhuma classe fa-* nas telas, os ícones são
    SVG inline e Bootstrap Icons) e o bundle JS do Bootstrap (nenhum
    data-bs-* e nenhum componente instanciado).
  -->
  <link rel="stylesheet" href="./assets/vendor/inter.css<?= $v ?>">
  <link rel="stylesheet" href="./assets/css/global.css<?= $v ?>">
  <link rel="stylesheet" href="./assets/css/sidebar.css<?= $v ?>">
  <link rel="stylesheet" href="./assets/css/erros.css<?= $v ?>">
  <link rel="stylesheet" href="./assets/vendor/bootstrap-icons/bootstrap-icons.css<?= $v ?>">
  <link rel="stylesheet" href="./assets/vendor/tom-select.css<?= $v ?>">
  <script src="./assets/vendor/chart.umd.min.js<?= $v ?>"></script>
  <script src="./assets/vendor/imask.min.js<?= $v ?>"></script>
  <script src="./assets/vendor/tom-select.complete.min.js<?= $v ?>"></script>
  <script src="./assets/js/escape.js<?= $v ?>"></script>
  <script src="./assets/js/cor-select.js<?= $v ?>"></script>
  <script src="./assets/js/select-enhance.js<?= $v ?>"></script>
  <!-- Aplica o tema ANTES de renderizar — evita flash de tela branca -->
  <script>
    (function() {
      var saved = localStorage.getItem('mecanize-theme');
      var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      if (saved === 'dark' || (!saved && prefersDark)) {
        document.documentElement.setAttribute('data-theme', 'dark');
      }
    })();
  </script>
</head>

<body>

  <div class="app">
    <!-- ══ SIDEBAR ══ -->
    <aside class="sidebar">
      <div class="sidebar__brand">
        <a href="./home">
          <img class="sidebar__logo-full" src="./assets/img/mecanize.ico" alt="Mecanize">
          <img class="sidebar__logo-icon" src="./assets/img/m-logo-branco.png" alt="M">
        </a>
      </div>

      <ul class="sidebar__nav">
        <li class="mob-nav <?= menuAtivo('home', $rotaAtual) ?>">
          <a href="./home">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
              <polyline points="9 22 9 12 15 12 15 22" />
            </svg>
            <span class="label">Início</span>
          </a>
        </li>

        <li class="<?= menuAtivo('listClientes', $rotaAtual) ?>">
          <a href="./listClientes">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
            <span class="label">Clientes</span>
          </a>
        </li>
        <li class="<?= menuAtivo('listVeiculos', $rotaAtual) ?>">
          <a href="./listVeiculos">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
              <path d="M7 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0-4 0" />
              <path d="M17 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0-4 0" />
              <path d="M5 17H3V13L6 7h9l3 5h1a1 1 0 0 1 1 1v4h-2" />
              <path d="M6 13h12" />
            </svg>
            <span class="label">Veículos</span>
          </a>
        </li>

        <li class="<?= menuAtivo('listOrcamentos', $rotaAtual) ?>">
          <a href="./listOrcamentos">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 2h10l4 4v14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z" />
              <text x="9" y="14" font-size="7" font-weight="700" text-anchor="middle" stroke="none" fill="currentColor" font-family="sans-serif">R$</text>
              <line x1="5" y1="18" x2="13" y2="18" />
              <line x1="5" y1="21" x2="10" y2="21" />
            </svg>
            <span class="label">Orçamentos</span>
          </a>
        </li>
        <li class="<?= menuAtivo('listOrdemServico', $rotaAtual) ?>">
          <a href="./listOrdemServico">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="2" width="14" height="18" rx="2" />
              <path d="M7 2v2a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1V2" />
              <path d="M6 12l3 3 5-6" />
            </svg>
            <span class="label">Ordens de Serviço</span>
          </a>
        </li>

        <li class="<?= menuAtivo('listProduto', $rotaAtual) ?>">
          <a href="./listProduto">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
              <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
              <line x1="12" y1="22.08" x2="12" y2="12" />
            </svg>
            <span class="label">Produtos</span>
          </a>
        </li>
        <li class="<?= menuAtivo('listMovimentacaoEstoque', $rotaAtual) ?>">
          <a href="./listMovimentacaoEstoque">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="5" width="15" height="13" rx="1" />
              <line x1="2" y1="10" x2="17" y2="10" />
              <line x1="2" y1="14" x2="17" y2="14" />
              <path d="M19 2l3 3-3 3" />
              <line x1="22" y1="5" x2="17" y2="5" />
            </svg>
            <span class="label">Movimentações</span>
          </a>
        </li>

        <li class="<?= menuAtivo('listAgendamentos', $rotaAtual) ?>">
          <a href="./listAgendamentos">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="4" width="18" height="18" rx="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
            <span class="label">Agenda</span>
          </a>
        </li>

        <?php if ($operaCaixa): ?>
          <li class="<?= menuAtivo('listCaixa', $rotaAtual) ?>">
            <a href="./listCaixa">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="7" width="20" height="14" rx="2" />
                <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" />
                <circle cx="12" cy="14" r="2" />
              </svg>
              <span class="label">Caixa</span>
            </a>
          </li>
        <?php endif; ?>

        <li>
          <a href="./painel" target="_blank" rel="noopener">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="3" width="20" height="14" rx="2" />
              <line x1="8" y1="21" x2="16" y2="21" />
              <line x1="12" y1="17" x2="12" y2="21" />
            </svg>
            <span class="label">Painel (TV)</span>
          </a>
        </li>

        <?php if (isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'ADMIN'): ?>
          <li class="mob-nav <?= menuAtivo('listRel', $rotaAtual) ?>">
            <a href="./listRel">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="20" x2="21" y2="20" />
                <rect x="5" y="14" width="3" height="6" rx="1" />
                <rect x="10" y="9" width="3" height="11" rx="1" />
                <rect x="15" y="12" width="3" height="8" rx="1" />
                <path d="M5 15 Q10 6 18 10" stroke-dasharray="2 2" fill="none" />
              </svg>
              <span class="label">Relatórios</span>
            </a>
          </li>

          <li class="mob-nav <?= menuAtivo('relatorioCaixa', $rotaAtual) ?>">
            <a href="./relatorioCaixa">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 1v22" />
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
              </svg>
              <span class="label">Relatório de Caixa</span>
            </a>
          </li>

        <?php endif; ?>


        <?php if (isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'ADMIN'): ?>
          <li class="<?= menuAtivo('listUsuarios', $rotaAtual) ?>">
            <a href="./listUsuarios">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="8" cy="7" r="4" />
                <path d="M2 21c0-4 2.7-7 6-7" />
                <circle cx="17" cy="17" r="3" />
                <line x1="17" y1="13" x2="17" y2="14" />
                <line x1="17" y1="20" x2="17" y2="21" />
                <line x1="13" y1="17" x2="14" y2="17" />
                <line x1="20" y1="17" x2="21" y2="17" />
              </svg>
              <span class="label">Usuários</span>
            </a>
          </li>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'ADMIN'): ?>
          <li class="mob-nav <?= menuAtivo('listDocumentos', $rotaAtual) ?>">
            <a href="./listDocumentos">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 3h6" />
                <path d="M9 3a2 2 0 0 0-2 2v1h10V5a2 2 0 0 0-2-2" />
                <rect x="5" y="6" width="14" height="15" rx="2" />
                <path d="M8 11h8" />
                <path d="M8 15h5" />
                <path d="M16.5 14.5c0-.8-.7-1.5-1.5-1.5s-1.5.7-1.5 1.5.7 1.5 1.5 1.5 1.5.7 1.5 1.5-.7 1.5-1.5 1.5-1.5-.7-1.5-1.5" />
              </svg>
              <span class="label">Documentos</span>
            </a>
          </li>
        <?php endif; ?>
      </ul>

      <button class="sidebar__toggle-floating" id="sidebarCollapseBtn" aria-label="Recolher menu">
        <svg class="sidebar__collapse-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m11 17-5-5 5-5" />
          <path d="m18 17-5-5 5-5" />
        </svg>
      </button>

      <div class="sidebar__footer">
        <!-- Toggle de tema dentro da gaveta (somente mobile) -->
        <button class="sidebar__theme-toggle" id="darkToggleMobile" type="button" aria-label="Alternar tema escuro">
          <span class="sidebar__theme-toggle-label">
            <span class="dark-toggle__icon-light">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="5" />
                <line x1="12" y1="1" x2="12" y2="3" />
                <line x1="12" y1="21" x2="12" y2="23" />
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
                <line x1="1" y1="12" x2="3" y2="12" />
                <line x1="21" y1="12" x2="23" y2="12" />
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
              </svg>
            </span>
            <span class="dark-toggle__icon-dark">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
              </svg>
            </span>
            Tema escuro
          </span>
          <span class="dark-toggle__track" aria-hidden="true"></span>
        </button>

        <a href="./perfil" class="sidebar__profile">
          <div class="sidebar__profile-avatar">
            <?= strtoupper(
              substr($_SESSION['user_nome'] ?? 'U', 0, 1) .
                substr($_SESSION['user_nome'] ?? 'U', -1)
            ) ?>
          </div>
          <div class="sidebar__profile-info">
            <div class="sidebar__footer-title"><?= htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário') ?></div>
            <div class="sidebar__footer-sub"><?= htmlspecialchars($_SESSION['user_tipo'] ?? '') ?></div>
          </div>
        </a>

        <a href="./logout" class="sidebar__logout">
          <span class="sidebar__logout-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path class="door" d="M5 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" />
              <polyline class="arrow" points="15 17 20 12 15 7" />
              <line class="arrow-line" x1="20" y1="12" x2="10" y2="12" />
            </svg>
          </span>
          <span class="sidebar__logout-label">Sair</span>
        </a>
      </div>
    </aside>

    <!-- Fundo escuro da gaveta (somente mobile) -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- ══ MAIN ══ -->
    <div class="main">

      <!-- TOPBAR -->
      <header class="topbar">

        <!-- Botão de menu (somente mobile) -->
        <button class="topbar__hamburger" id="mobileNavToggle" aria-label="Abrir menu">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="6" x2="21" y2="6" />
            <line x1="3" y1="12" x2="21" y2="12" />
            <line x1="3" y1="18" x2="21" y2="18" />
          </svg>
        </button>

        <details class="topbar-create">
          <summary>
            <span class="topbar-create__icon">+</span>
            Criar
          </summary>

          <div class="topbar-create__menu">
            <a href="./novoOrcamento">
              <span>Orçamento</span>
              <small>Criar novo orçamento</small>
            </a>

            <a href="./saveOrdemServico">
              <span>Ordem de Serviço</span>
              <small>Abrir nova OS</small>
            </a>

            <a href="./saveCliente">
              <span>Cliente</span>
              <small>Cadastrar cliente</small>
            </a>

            <a href="./saveVeiculo">
              <span>Veículo</span>
              <small>Cadastrar veículo</small>
            </a>

            <a href="./saveProduto">
              <span>Produto</span>
              <small>Cadastrar item no estoque</small>
            </a>
          </div>
        </details>

        <button class="topbar-dark-toggle" id="darkToggle" aria-label="Alternar tema escuro">
          <span class="dark-toggle__icon-light">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="5" />
              <line x1="12" y1="1" x2="12" y2="3" />
              <line x1="12" y1="21" x2="12" y2="23" />
              <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
              <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
              <line x1="1" y1="12" x2="3" y2="12" />
              <line x1="21" y1="12" x2="23" y2="12" />
              <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
              <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
            </svg>
          </span>
          <span class="dark-toggle__track" aria-hidden="true"></span>
          <span class="dark-toggle__icon-dark">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
            </svg>
          </span>
        </button>

        <div class="topbar__right">
          <?php if (!$operaCaixa): ?>
            <!-- Técnico não opera caixa: sem indicador e sem atalho. -->
          <?php elseif (!empty($caixaStatus)): ?>
            <a href="./caixaMovimentacoes" title="Caixa aberto por <?= htmlspecialchars($caixaStatus['abertura_nome'] ?? '') ?> — clique para ver os lançamentos"
              style="display:inline-flex;align-items:center;gap:8px;height:34px;padding:0 12px;border-radius:var(--radius-sm);border:1.5px solid var(--color-success,#16A34A);background:var(--color-success-soft);color:var(--color-success,#16A34A);text-decoration:none;font-size:12px;font-weight:600;white-space:nowrap;">
              <span style="width:8px;height:8px;border-radius:50%;background:currentColor;flex-shrink:0;"></span>
              <span>Caixa aberto · <?= htmlspecialchars($caixaStatus['abertura_nome'] ?? '') ?></span>
            </a>
          <?php else: ?>
            <a href="./abrirCaixa" title="Abrir caixa"
              style="display:inline-flex;align-items:center;gap:8px;height:34px;padding:0 12px;border-radius:var(--radius-sm);border:1.5px solid var(--color-border);background:transparent;color:var(--color-text-muted);text-decoration:none;font-size:12px;font-weight:600;white-space:nowrap;">
              <span style="width:8px;height:8px;border-radius:50%;background:currentColor;flex-shrink:0;"></span>
              <span>Caixa fechado</span>
            </a>
          <?php endif; ?>
          <a href="./perfil" class="topbar__user">
            <div class="topbar__avatar">
              <?= strtoupper(
                substr($_SESSION['user_nome'] ?? 'U', 0, 1) .
                  substr($_SESSION['user_nome'] ?? 'U', -1)
              ) ?>
            </div>
            <div class="topbar__user-info">
              <div class="topbar__user-name">
                <?= htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário') ?>
              </div>
              <div class="topbar__user-role">
                <?= htmlspecialchars($_SESSION['user_tipo'] ?? '') ?>
              </div>
            </div>
          </a>
        </div>
      </header>

      <!-- CONTEÚDO DA PÁGINA -->
      <div class="content">