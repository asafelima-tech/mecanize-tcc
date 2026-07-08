<?php

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../app/core/Env.php';
Env::carregar(__DIR__ . '/../.env');

require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Validator.php';
require_once __DIR__ . '/../app/core/WhatsApp.php';
require_once __DIR__ . '/../config/routes.php';

/**
 * Indica se a requisição partiu de DENTRO do próprio sistema (clique em
 * link/botão ou envio de formulário) e não de alguém digitando/colando o
 * endereço na barra ou abrindo um favorito.
 *
 * Sinal primário: Fetch Metadata (Sec-Fetch-Site), que o navegador define
 * sozinho e a barra de endereço não consegue forjar. Fallback: host do
 * Referer (cobre redirecionamentos internos e navegadores antigos).
 */
function navegacaoInterna(): bool
{
    // Cliques/submits no próprio site → "same-origin".
    if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? null) === 'same-origin') {
        return true;
    }

    // Referer do mesmo host: cobre redirecionamentos internos pós-POST e
    // navegadores antigos sem Sec-Fetch-*. Digitar/colar/favoritar não envia Referer.
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer !== '') {
        $refHost  = parse_url($referer, PHP_URL_HOST);
        $thisHost = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($refHost !== null && strcasecmp($refHost, $thisHost) === 0) {
            return true;
        }
    }

    return false;
}

$uri = $_GET['url'] ?? 'home';
$uri = trim($uri, '/');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$logado = isset($_SESSION['logged']) && $_SESSION['logged'] === true;

/*
 * ── Autenticação obrigatória ───────────────────────────────────────────────
 * Toda rota exige login, exceto a própria tela de login, o logout, o erro 404
 * e o fluxo de recuperação de senha (que existe justamente para quem não
 * consegue logar). Centralizar aqui garante que nenhuma rota (página ou
 * endpoint AJAX) fique acessível sem sessão — não dependemos mais de cada
 * controller incluir auth.php.
 */
$rotasPublicas = ['login', 'logout', 'erro404', 'recuperar-senha', 'redefinir-senha', 'termos', 'privacidade', 'rastrear'];

if (!$logado && !in_array($uri, $rotasPublicas, true)) {
    header('Location: ./login');
    exit;
}

/*
 * ── Janela de atendimento (ATENDENTE) ──────────────────────────────────────
 * Atendentes só podem usar o sistema de seg–sex 07:00–18:00 e sáb 07:00–13:00.
 * Bloquear apenas o login não basta: se o expediente terminar com a sessão
 * ainda ativa (ex.: passou das 18:00), o usuário é desconectado na hora.
 * É a mesma regra de horário usada em UserController::login(). O fluxo passa
 * pelo ./logout para que o caixa também seja fechado automaticamente (o login
 * e o próprio logout ficam de fora para não cair em loop).
 */
if (
    $logado
    && ($_SESSION['user_tipo'] ?? '') === 'ATENDENTE'
    && !in_array($uri, ['login', 'logout'], true)
) {
    $diaSemana = (int) date('N');
    $horaAtual = date('H:i');

    $dentroDaJanela =
        ($diaSemana >= 1 && $diaSemana <= 5 && $horaAtual >= '07:00' && $horaAtual < '18:00') ||
        ($diaSemana === 6 && $horaAtual >= '07:00' && $horaAtual < '13:00');

    if (!$dentroDaJanela) {
        header('Location: ./logout?expirado=1');
        exit;
    }
}

/*
 * ── URL inerte: navegação só por dentro do sistema ─────────────────────────
 * Digitar/colar/editar o endereço na barra (ou abrir um favorito) não leva a
 * lugar nenhum — cai no Início. Só links/botões clicados dentro do sistema
 * navegam. Vale para qualquer tipo de usuário. Aplica-se apenas a GET (todo
 * POST parte de um formulário interno) e preserva os redirecionamentos do
 * próprio sistema via allowlist (Início, login/logout, fluxo de caixa, o
 * fluxo de recuperação de senha — que chega por link de e-mail, portanto
 * nunca "same-origin" — e os endpoints AJAX usados dentro das telas).
 */
$rotasNavegacaoDireta = [
    'home',
    'login',
    'logout',
    'abrirCaixa',
    'fecharCaixa',
    'lerPlaca',
    'modelosPorMarca',
    'criarModelo',
    'recuperar-senha',
    'redefinir-senha',
    'painel',
];

if (
    $logado
    && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && !in_array($uri, $rotasNavegacaoDireta, true)
    && !navegacaoInterna()
) {
    header('Location: ./home');
    exit;
}

/*
 * ── Fechamento automático de caixa por aba fechada ─────────────────────────
 * Verificação oportunista: se a sessão de caixa aberta está marcada como
 * pendente de fechamento (aba fechada, ver caixa-guard.js) há mais tempo que
 * o limite e ninguém cancelou (nenhum reload/navegação da mesma aba), fecha
 * automaticamente. Executado a cada requisição — não precisa de cron.
 */
if ($logado) {
    require_once __DIR__ . '/../app/models/Caixa.php';
    try {
        (new Caixa())->fecharsePendenteExpirado();
    } catch (\Throwable $e) {
        // Não pode derrubar o sistema.
    }
}

/*
 * ── Caixa obrigatório ──────────────────────────────────────────────────────
 * Enquanto não houver um caixa ABERTO, qualquer usuário logado é redirecionado
 * para a tela de abertura. As rotas abaixo continuam acessíveis para permitir
 * fazer login/logout, gerenciar o próprio caixa (abrir/fechar/histórico) e
 * usar os endpoints de recuperação de senha e de controle de fechamento
 * automático da aba.
 */
$rotasSemCaixa = [
    'login',
    'logout',
    'abrirCaixa',
    'fecharCaixa',
    'listCaixa',
    'recuperar-senha',
    'redefinir-senha',
    // Endpoints AJAX/JSON usados dentro das telas — não devem ser redirecionados
    'lerPlaca',
    'modelosPorMarca',
    'criarModelo',
    'marcarFechamentoAuto',
    'cancelarFechamentoAuto',
    // Painel modo TV — tela passiva, não faz sentido exigir caixa aberto.
    'painel',
];

if ($logado && !in_array($uri, $rotasSemCaixa, true)) {
    require_once __DIR__ . '/../app/models/Caixa.php';

    try {
        $caixaModel = new Caixa();
        if (!$caixaModel->getCaixaAberto()) {
            header('Location: ./abrirCaixa');
            exit;
        }
    } catch (\Throwable $e) {
        // Falha ao consultar o caixa não deve derrubar o sistema inteiro
    }
}

if (array_key_exists($uri, $routes)) {
    $controllerName = $routes[$uri]['controller'];
    $method = $routes[$uri]['method'];

    $controllerFile = __DIR__ . "/../app/controllers/{$controllerName}.php";

    if (!file_exists($controllerFile)) {
        die("Controller não encontrado: " . $controllerFile);
    }

    require_once $controllerFile;

    if (!class_exists($controllerName)) {
        die("Classe não encontrada: " . $controllerName);
    }

    $controller = new $controllerName();

    if (!method_exists($controller, $method)) {
        die("Método não encontrado: {$controllerName}::{$method}");
    }

    $controller->$method();
} else {
    require_once __DIR__ . '/../app/controllers/ErrorController.php';
    $controller = new ErrorController();
    $controller->erro404();
}
