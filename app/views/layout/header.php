<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$rotaAtual = $_GET['url'] ?? 'home';

// Técnico usa uma sidebar própria, bem mais curta — a área dele não tem
// nada administrativo/financeiro (ver app/controllers/TecnicoController.php).
$ehTecnico = ($_SESSION['user_tipo'] ?? '') === 'TECNICO';

function menuAtivo($rota, $rotaAtual)
{
  return $rota === $rotaAtual ? 'is-active' : '';
}

// Estado atual do caixa (para o indicador da topbar / sidebar)
$caixaStatus = null;
if (isset($_SESSION['logged']) && $_SESSION['logged'] === true) {
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
  <!-- Bibliotecas servidas pelo próprio projeto (public/assets/vendor).
       Sem CDN por dois motivos: a CSP abaixo só aceita origem própria, e um
       script de terceiro carregado de fora é código que ninguém aqui revisou
       rodando dentro da sessão de quem está logado. De quebra, o sistema
       funciona sem internet — a demonstração não depende do Wi-Fi da escola. -->
  <link rel="stylesheet" href="./assets/vendor/inter.css">
  <link rel="icon" type="image/ico" href="./assets/img/logo-branco.ico">
  <?php $v = '?v=' . time(); ?>
  <link rel="stylesheet" href="./assets/css/global.css<?= $v ?>">
  <link rel="stylesheet" href="./assets/css/sidebar.css<?= $v ?>">
  <link rel="stylesheet" href="./assets/css/erros.css<?= $v ?>">
  <!-- Só no papel: desmonta a casca do app (sidebar/topbar/rolagem interna)
       e transforma a tela num documento A4. Ver public/assets/css/print.css. -->
  <link rel="stylesheet" media="print" href="./assets/css/print.css<?= $v ?>">
  <!-- Font Awesome saiu daqui: a busca não achou uma única classe fa-/fas/far
       em app/views. O projeto usa SVG inline e Bootstrap Icons. -->
  <link rel="stylesheet" href="./assets/vendor/bootstrap-icons.css">
  <link rel="stylesheet" href="./assets/vendor/tom-select.css">
  <script src="./assets/vendor/chart.umd.min.js"></script>
  <script src="./assets/vendor/imask.min.js"></script>
  <script src="./assets/vendor/tom-select.complete.min.js"></script>
  <!-- Antes dos scripts inline das telas: é lá que ele é usado, ao montar
       linhas de tabela e painéis com innerHTML. -->
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

    /**
     * Cor de série de gráfico a partir do token do design system
     * (--chart-1, --chart-ordinal-3...). Os gráficos são desenhados em
     * canvas, então não herdam CSS: sem ler o token aqui, cada tela
     * acabava com um hexadecimal solto no meio do JavaScript — foi assim
     * que verde e vermelho entraram numa paleta que é toda azul.
     * Definida no <head> porque as telas montam os gráficos antes do rodapé.
     */
    window.corDoTema = function (token) {
      return getComputedStyle(document.documentElement).getPropertyValue(token).trim();
    };

    /** Mistura a cor com branco — a ponta clara do gradiente. */
    function clarearCor(hex, fator) {
      var m = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex.trim());
      if (!m) return hex;

      var canais = [1, 2, 3].map(function (i) {
        var c = parseInt(m[i], 16);
        return Math.round(c + (255 - c) * fator);
      });

      return 'rgb(' + canais.join(',') + ')';
    }

    /**
     * Gradiente vertical de uma série, do tom claro ao tom cheio — o mesmo
     * desenho do gráfico de faturamento, agora usado por todos. Precisa ser
     * um CanvasGradient (o canvas não entende linear-gradient do CSS), então
     * é montado aqui a partir do mesmo token que o CSS usa.
     */
    window.gradienteDoTema = function (ctx, token, altura) {
      var base = window.corDoTema(token);
      var gradiente = ctx.createLinearGradient(0, 0, 0, altura || 320);

      gradiente.addColorStop(0, clarearCor(base, 0.45));
      gradiente.addColorStop(1, base);

      return gradiente;
    };
  </script>
</head>

<body>

  <div class="app">
    <!-- ══ SIDEBAR ══ -->
    <aside class="sidebar">
      <div class="sidebar__brand">
        <a href="<?= $ehTecnico ? './painelTecnico' : './home' ?>">
          <img class="sidebar__logo-full" src="./assets/img/mecanize.ico" alt="Mecanize">
          <img class="sidebar__logo-icon" src="./assets/img/m-logo-branco.png" alt="M">
        </a>
      </div>

      <?php require __DIR__ . '/_sidebarMenu.php'; ?>

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

            <?php if (($_SESSION['user_tipo'] ?? '') === 'ADMIN'): ?>
            <a href="./saveProduto">
              <span>Produto</span>
              <small>Cadastrar item no estoque</small>
            </a>
            <?php endif; ?>
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
          <?php
          // O sino: avisos do próprio usuário (solicitação de peça, peça que chegou…).
          $naoLidas = 0;
          try {
              require_once __DIR__ . '/../../models/Notificacao.php';
              $naoLidas = (new Notificacao())->contarNaoLidas((int) ($_SESSION['user_id_usuario'] ?? 0));
          } catch (\Throwable $e) {
              error_log('[sino] ' . $e->getMessage());
          }
          ?>
          <a href="./listNotificacoes" class="topbar-bell" title="<?= $naoLidas ? $naoLidas . ' aviso(s) não lido(s)' : 'Avisos' ?>" aria-label="Avisos"
            style="position:relative;display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:var(--radius-sm);border:1.5px solid var(--color-border);color:var(--color-text-muted);text-decoration:none;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
              <path d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
            <?php if ($naoLidas > 0): ?>
              <span style="position:absolute;top:-6px;right:-6px;min-width:18px;height:18px;padding:0 5px;border-radius:9px;background:var(--color-danger,#DC2626);color:#fff;font-size:10px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;"><?= $naoLidas > 99 ? '99+' : (int) $naoLidas ?></span>
            <?php endif; ?>
          </a>
          <?php if ($operaCaixa && !empty($caixaStatus)): ?>
            <a href="./caixaMovimentacoes" title="Caixa aberto por <?= htmlspecialchars($caixaStatus['abertura_nome'] ?? '') ?> — clique para ver os lançamentos"
              style="display:inline-flex;align-items:center;gap:8px;height:34px;padding:0 12px;border-radius:var(--radius-sm);border:1.5px solid var(--color-success,#16A34A);background:var(--color-success-soft);color:var(--color-success,#16A34A);text-decoration:none;font-size:12px;font-weight:600;white-space:nowrap;">
              <span style="width:8px;height:8px;border-radius:50%;background:currentColor;flex-shrink:0;"></span>
              <span>Caixa aberto · <?= htmlspecialchars($caixaStatus['abertura_nome'] ?? '') ?></span>
            </a>
          <?php elseif ($operaCaixa): ?>
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
        <?php require __DIR__ . '/_docImpressoCabecalho.php'; ?>