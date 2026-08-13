<?php

// Descarta qualquer erro fatal de uma requisição ANTERIOR atendida pelo mesmo
// worker do Apache/mod_php — sem isso, error_get_last() no shutdown handler
// abaixo podia "herdar" um erro de outro request antigo e marcar como 500
// uma página que renderizou perfeitamente.
error_clear_last();

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../app/core/Env.php';
Env::carregar(__DIR__ . '/../.env');

/*
 * ── Tratamento global de erro ───────────────────────────────────────────────
 * Sem isso, uma falha de banco/config imprevista mostrava um stack trace cru
 * (caminho absoluto dos arquivos, DSN) pra qualquer visitante — display_errors
 * fica ligado por padrão no php.ini. Com APP_DEBUG=false (produção), o detalhe
 * vai só pro log do PHP e a tela mostra uma página genérica.
 */
$appDebug = filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('log_errors', '1');

/*
 * ── Cabeçalhos de segurança ────────────────────────────────────────────────
 * Enviados aqui, antes de qualquer saída, para valerem em toda resposta do
 * sistema (página, JSON, PDF e CSV) sem depender de cada controller lembrar.
 *
 * X-Frame-Options / frame-ancestors: impedem que outro site abra o Mecanize
 *   dentro de um <iframe> invisível e engane o usuário a clicar em botões que
 *   ele não está vendo. 'self' continua permitindo a prévia de PDF em iframe,
 *   que é do próprio sistema.
 * X-Content-Type-Options: o navegador para de "adivinhar" o tipo do arquivo e
 *   passa a respeitar o Content-Type que mandamos — sem isso, um upload
 *   devolvido como texto pode acabar interpretado como HTML.
 * Referrer-Policy: o endereço interno (com id de cliente, número de OS) deixa
 *   de ser enviado ao clicar num link para fora do sistema.
 * Content-Security-Policy: lista de onde a página PODE carregar coisa. Como
 *   nada mais vem de CDN, tudo é 'self'; form-action impede que um formulário
 *   injetado poste os dados para fora, e base-uri impede que uma tag <base>
 *   injetada redirecione todos os caminhos relativos da página.
 *
 * 'unsafe-inline' segue necessário porque as telas têm <script> e style=
 * escritos direto no HTML — tirar isso significaria reescrever dezenas de
 * blocos. Mesmo assim a política já vale: ela barra o carregamento de script
 * vindo de qualquer domínio externo. img-src precisa de data: por causa da
 * assinatura do cliente, que o canvas gera nesse formato.
 */
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header(
    "Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'unsafe-inline'; "
        . "style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data:; "
        . "font-src 'self'; "
        . "connect-src 'self'; "
        . "form-action 'self'; "
        . "base-uri 'self'; "
        . "frame-ancestors 'self'"
);

require_once __DIR__ . '/../app/core/Erro.php';

/*
 * Toda falha não tratada vira uma tela de erro do sistema, nunca um stack
 * trace ou uma mensagem do PHP. O detalhe técnico vai pro log associado a um
 * código de ocorrência, que é o mesmo mostrado na tela — é assim que dá pra
 * cruzar "deu erro, o código era X" com a linha exata do log.
 */
set_exception_handler(function (\Throwable $e) use ($appDebug) {
    $ocorrencia = Erro::gerarOcorrencia();
    Erro::registrar($ocorrencia, 'Exceção não tratada: ' . $e);

    if ($appDebug) {
        http_response_code(500);
        echo '<pre style="white-space:pre-wrap">' . htmlspecialchars((string) $e) . '</pre>';
        exit;
    }

    Erro::exibir('falha-interna', ['ocorrencia' => $ocorrencia]);
});

register_shutdown_function(function () use ($appDebug) {
    $fatal = error_get_last();

    if (!$fatal || !in_array($fatal['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    $ocorrencia = Erro::gerarOcorrencia();
    Erro::registrar($ocorrencia, "Erro fatal: {$fatal['message']} em {$fatal['file']}:{$fatal['line']}");

    // headers_sent() = a página já começou a sair; sobrescrever agora só
    // produziria uma tela meio renderizada com um erro colado no meio.
    if (!$appDebug && !headers_sent()) {
        Erro::exibir('falha-interna', ['ocorrencia' => $ocorrencia]);
    }
});

require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Validator.php';
require_once __DIR__ . '/../app/core/WhatsApp.php';
require_once __DIR__ . '/../app/core/Csrf.php';
require_once __DIR__ . '/../app/core/Html.php';
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
    // HttpOnly (JS não lê o cookie) + SameSite=Lax (bloqueia o cookie em
    // requisições cross-site, mitigando CSRF, sem quebrar navegação normal
    // por link) + Secure só quando o acesso já é HTTPS (em HTTP, como no
    // ambiente local via XAMPP, exigir Secure faria o navegador descartar
    // o cookie e ninguém conseguiria logar).
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$logado = isset($_SESSION['logged']) && $_SESSION['logged'] === true;

/*
 * ── Proteção CSRF ───────────────────────────────────────────────────────────
 * Nenhum POST era protegido antes disso — bastava a sessão ativa pra qualquer
 * ação mudar dado (criar/editar/excluir cliente, veículo, produto, OS, caixa
 * etc.), o que abre brecha pra um site externo disparar essas ações em nome
 * de um usuário logado (CSRF). Centralizado aqui, cobre toda rota de uma vez.
 * O token vem do campo oculto csrf_token (forms) ou do header X-CSRF-Token
 * (fetch/AJAX) — ambos preenchidos automaticamente por
 * public/assets/js/csrf.js, além do helper csrf_field() usado nos forms.
 */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $tokenRecebido = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

    if (!Csrf::valido($tokenRecebido)) {
        // Quase sempre é sessão vencida ou aba parada há horas, não ataque —
        // por isso a tela explica e devolve o formulário preenchido, em vez
        // de acusar o usuário de alguma coisa.
        Erro::exibir('sessao-expirada');
    }
}

/*
 * ── Autenticação obrigatória ───────────────────────────────────────────────
 * Toda rota exige login, exceto a própria tela de login, o logout, o erro 404
 * e o fluxo de recuperação de senha (que existe justamente para quem não
 * consegue logar). Centralizar aqui garante que nenhuma rota (página ou
 * endpoint AJAX) fique acessível sem sessão — não dependemos mais de cada
 * controller incluir auth.php.
 */
/*
 * As telas de erro entram em todas as listas de exceção abaixo: uma tela que
 * explica um problema não pode, ela mesma, ser barrada por falta de login, de
 * caixa aberto ou por não ter vindo de um clique interno — seria justamente
 * nesses casos que ela precisaria aparecer.
 */
$rotasDeErro = ['erro404', 'erroPermissao', 'erroIndisponivel', 'erroConexao', 'erroTempo'];

$rotasPublicas = array_merge(
    ['login', 'logout', 'recuperar-senha', 'redefinir-senha', 'termos', 'privacidade', 'rastrear'],
    $rotasDeErro
);

/**
 * A requisição veio de fetch/XHR (e não de uma navegação do navegador)?
 * Importa porque redirecionar um fetch não leva ninguém ao login: o script
 * recebe o HTML da tela de login e o injeta no meio da página.
 */
function ehRequisicaoDeScript(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        || (($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'empty');
}

if (!$logado && !in_array($uri, $rotasPublicas, true)) {
    // Script: devolve a tela de 401 com o status certo, pra quem chamou saber
    // que caiu a sessão em vez de receber o HTML do login como se fosse dado.
    if (ehRequisicaoDeScript()) {
        Erro::exibir('nao-autenticado', ['retorno' => $_SERVER['REQUEST_URI'] ?? '']);
    }

    // Navegação normal: manda pro login guardando onde a pessoa queria chegar,
    // pra ela voltar direto pra lá depois de entrar.
    $destino = './login';

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $uri !== 'home') {
        // 'url' é o parâmetro interno do rewrite (.htaccess), não faz parte do
        // endereço que o usuário pediu — incluí-lo duplicaria a rota.
        $parametros = $_GET;
        unset($parametros['url']);

        $destino .= '?retorno=' . urlencode($uri . ($parametros ? '&' . http_build_query($parametros) : ''));
    }

    header('Location: ' . $destino);
    exit;
}

/*
 * ── Sessão parada tempo demais ─────────────────────────────────────────────
 * O login valia até fechar o navegador. Numa oficina onde o computador do
 * balcão fica ligado o dia inteiro e é usado por mais de uma pessoa, isso
 * significa que quem senta na cadeira depois herda a sessão de quem estava
 * antes. Passados TEMPO_MAXIMO_INATIVO sem nenhuma requisição, a sessão cai.
 *
 * A saída é pelo ./logout (e não um session_destroy() aqui) porque é lá que
 * o caixa aberto é fechado automaticamente; ?expirado=1 é o mesmo aviso que a
 * tela de login já sabe mostrar para o atendente fora de horário.
 */
const TEMPO_MAXIMO_INATIVO = 30 * 60; // 30 minutos

if ($logado && !in_array($uri, ['login', 'logout'], true)) {
    $ultimoAcesso = $_SESSION['ultimo_acesso'] ?? time();

    if ((time() - $ultimoAcesso) > TEMPO_MAXIMO_INATIVO) {
        header('Location: ./logout?expirado=1');
        exit;
    }

    $_SESSION['ultimo_acesso'] = time();
}

/*
 * ── Conta alterada durante a sessão ────────────────────────────────────────
 * O papel e a situação do usuário eram lidos uma única vez, no login, e nunca
 * mais conferidos. Na prática: desativar alguém, rebaixá-lo de ADMIN ou trocar
 * a senha dele não tinha efeito nenhum enquanto a sessão continuasse aberta —
 * a pessoa seguia trabalhando com as permissões antigas até fechar o navegador.
 *
 * A conferência usa `usuarios.atualizado_em`, que User::update() e
 * User::toggleStatus() já mantêm: se o valor no banco não é mais o mesmo
 * anotado no login, alguma coisa mudou naquela conta (nome, e-mail, papel,
 * situação ou senha) e a sessão não vale mais. Uma comparação de texto resolve
 * os três casos de uma vez.
 *
 * As tentativas de login erradas de propósito NÃO tocam em atualizado_em
 * (ver User::registrarTentativaFalha), então ninguém consegue derrubar a
 * sessão alheia só errando a senha da conta.
 */
if ($logado && !in_array($uri, array_merge(['login', 'logout'], $rotasDeErro), true)) {
    try {
        $usuarioAtual = (new Database())->prepare(
            "SELECT ativo, tipo, atualizado_em FROM usuarios WHERE id_usuario = ?"
        );
        $usuarioAtual->execute([$_SESSION['user_id_usuario'] ?? 0]);
        $contaNoBanco = $usuarioAtual->fetch(PDO::FETCH_ASSOC);

        $contaSumiu    = !$contaNoBanco;
        $contaInativa  = $contaNoBanco && (int) $contaNoBanco['ativo'] !== 1;
        $contaMudou    = $contaNoBanco
            && (string) ($contaNoBanco['atualizado_em'] ?? '') !== (string) ($_SESSION['user_atualizado_em'] ?? '');

        if ($contaSumiu || $contaInativa || $contaMudou) {
            header('Location: ./logout?expirado=1');
            exit;
        }
    } catch (\Throwable $e) {
        // Banco fora do ar não pode expulsar quem já está trabalhando —
        // a tela de erro apropriada aparece adiante, no fluxo normal.
    }
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
$rotasNavegacaoDireta = array_merge([
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
], $rotasDeErro);

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
 * Enquanto não houver um caixa ABERTO, o usuário logado é redirecionado para a
 * tela de abertura. As rotas abaixo continuam acessíveis para permitir fazer
 * login/logout, gerenciar o próprio caixa (abrir/fechar/histórico) e usar os
 * endpoints de recuperação de senha e de controle de fechamento automático
 * da aba.
 *
 * O TÉCNICO fica de fora desta regra. Caixa é controle do dinheiro que entra e
 * sai, não controle de presença: quem não recebe pagamento não tem por que ser
 * barrado por ele. Sem essa exceção o mecânico ficaria preso — a regra o
 * mandaria para ./abrirCaixa, e abrir caixa é justamente uma das telas que ele
 * não tem permissão para usar.
 */
$rotasSemCaixa = array_merge($rotasDeErro, [
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
]);

if (
    $logado
    && ($_SESSION['user_tipo'] ?? '') !== 'TECNICO'
    && !in_array($uri, $rotasSemCaixa, true)
) {
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

    /*
     * Rota registrada apontando pra código que não existe é defeito de
     * programação, não erro do usuário. Antes isso saía como die() com o
     * caminho absoluto do arquivo na tela — entregava a estrutura de pastas
     * do servidor pra qualquer visitante. Agora vira ocorrência no log e a
     * tela genérica de falha interna.
     */
    $problema = null;

    if (!file_exists($controllerFile)) {
        $problema = "Controller inexistente: {$controllerName} (rota '{$uri}')";
    } else {
        require_once $controllerFile;

        if (!class_exists($controllerName)) {
            $problema = "Classe inexistente: {$controllerName} (rota '{$uri}')";
        } elseif (!method_exists($controllerName, $method)) {
            $problema = "Método inexistente: {$controllerName}::{$method} (rota '{$uri}')";
        }
    }

    if ($problema !== null) {
        $ocorrencia = Erro::gerarOcorrencia();
        Erro::registrar($ocorrencia, $problema);
        Erro::exibir('falha-interna', ['ocorrencia' => $ocorrencia]);
    }

    $controller = new $controllerName();
    $controller->$method();
} else {
    Erro::exibir('nao-encontrado');
}
