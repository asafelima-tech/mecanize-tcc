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
 * O sistema não mandava nenhum deles. Aqui é a rede de proteção para as falhas
 * que ainda não conhecemos: mesmo que um campo escape do tratamento, o
 * navegador limita o estrago.
 *
 * X-Frame-Options / frame-ancestors: ninguém consegue embutir o sistema num
 * iframe invisível e roubar cliques do usuário logado (clickjacking).
 * X-Content-Type-Options: o navegador para de "adivinhar" o tipo de um arquivo
 * enviado — um .jpg que na verdade é script deixa de ser executado como script.
 * Referrer-Policy: o endereço interno (com id de cliente, número de OS) para de
 * vazar para sites externos que a pessoa abrir a partir daqui.
 *
 * 'unsafe-inline' continua necessário porque as telas têm <script> e style=
 * escritos direto no HTML — tirar isso significaria reescrever ~50 blocos.
 * Mesmo assim a política vale a pena: sem origem externa em script-src, um
 * script injetado apontando para outro domínio simplesmente não carrega, e foi
 * por isso que as bibliotecas saíram do CDN e vieram para assets/vendor.
 * img-src data: é obrigatório para a assinatura do cliente, que é desenhada
 * num canvas e trafega como data URI.
 */
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header(
    "Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' 'unsafe-inline'; "
    . "style-src 'self' 'unsafe-inline'; "
    . "img-src 'self' data:; "
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
require_once __DIR__ . '/../app/core/Sessao.php';
require_once __DIR__ . '/../app/core/Html.php';
require_once __DIR__ . '/../app/core/Permissao.php';
require_once __DIR__ . '/../config/empresa.php';
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

// Webhook (servidor externo): sem sessão nem cookie — quem autentica é a assinatura do corpo.
$ehWebhook = ($routes[$uri]['csrf'] ?? true) === false && ($routes[$uri]['acesso'] ?? null) === Permissao::PUBLICO;

if ($ehWebhook) {
    $_SESSION = [];
} elseif (session_status() === PHP_SESSION_NONE) {
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
    // O relógio de limpeza do PHP precisa acompanhar o de inatividade do
    // sistema — ver Sessao::configurarArmazenamento().
    Sessao::configurarArmazenamento();
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
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !$ehWebhook) {
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

// Rotas sem login = as que declaram acesso PUBLICO em config/routes.php. A lista
// deixou de ser escrita à mão aqui: uma rota só vira pública por decisão explícita
// na tabela de rotas (e o PermissaoTest confere).
$rotasPublicas = array_keys(array_filter(
    $routes,
    fn($rota) => ($rota['acesso'] ?? null) === Permissao::PUBLICO
));

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
 * ── Validade da sessão ─────────────────────────────────────────────────────
 * Duas regras que resolvem três problemas, usando o que já existe no banco.
 *
 * 1) Inatividade: a sessão só valia enquanto o navegador ficasse aberto, sem
 *    prazo nenhum. Numa oficina o computador é compartilhado e fica ligado o
 *    dia inteiro — quem sentar depois herdava a sessão de quem saiu. Passados
 *    30 minutos sem nenhuma requisição, a sessão cai.
 *
 * 2) Revalidação: `usuarios.atualizado_em` é comparado com o valor carimbado
 *    no login. Se mudou, a conta foi alterada por alguém e a sessão cai na
 *    hora. Como User::update() e User::toggleStatus() já gravam
 *    `atualizado_em = NOW()`, isso faz com que TROCAR A SENHA, MUDAR O PAPEL
 *    ou DESATIVAR um usuário derrubem a sessão dele imediatamente — antes, um
 *    usuário demitido continuava dentro do sistema até fechar o navegador.
 *    registrarTentativaFalha()/resetarTentativas() não tocam nessa coluna de
 *    propósito, então tentativa de login de terceiro não expulsa ninguém.
 *
 * Cada regra tem o SEU motivo (ver app/core/Sessao.php): o caminho de saída é
 * Sessao::encerrar(), que guarda o motivo e passa pelo ./logout (fecha o caixa
 * e leva ao login com a mensagem daquele motivo — nunca a de outra regra). Rotas públicas e de erro ficam de fora, como nas
 * demais regras deste arquivo.
 */
if ($logado && !in_array($uri, array_merge(['login', 'logout'], $rotasPublicas), true)) {
    // Regra 1 — inatividade. Vale para TODOS os perfis, ADMIN inclusive.
    if (Sessao::inativa()) {
        Sessao::encerrar(Sessao::INATIVIDADE);
    }

    Sessao::registrarAtividade();

    // Conta alterada durante a sessão: derruba. Falha de banco aqui não pode
    // desconectar todo mundo, então o erro é ignorado e a regra volta a valer
    // na requisição seguinte.
    try {
        require_once __DIR__ . '/../app/models/User.php';

        $atual = (new User())->findById((int) ($_SESSION['user_id_usuario'] ?? 0));

        // Regra 2 — conta: desativada/apagada ou alterada (motivos distintos).
        $motivoConta = Sessao::motivoDaConta($atual);
    } catch (\Throwable $e) {
        $motivoConta = null;
        // Segue o baile: indisponibilidade do banco já tem tratamento próprio.
    }

    if ($motivoConta !== null) {
        Sessao::encerrar($motivoConta);
    }
}

/*
 * ── Janela de atendimento (ATENDENTE) ──────────────────────────────────────
 * Atendentes só podem usar o sistema de seg–sex 07:00–18:00 e sáb 07:00–13:00.
 * Bloquear apenas o login não basta: se o expediente terminar com a sessão
 * ainda ativa (ex.: passou das 18:00), o usuário é desconectado na hora.
 * É a mesma regra de horário usada em UserController::login() (Sessao::foraDoHorario).
 * ADMIN e TÉCNICO nunca são tocados por ela. O fluxo passa pelo ./logout para que
 * o caixa também seja fechado automaticamente (o login e o próprio logout ficam
 * de fora para não cair em loop).
 */
if (
    $logado
    && !in_array($uri, ['login', 'logout'], true)
    && Sessao::foraDoHorario($_SESSION['user_tipo'] ?? null)  // só o ATENDENTE tem horário
) {
    Sessao::encerrar(Sessao::HORARIO);
}

/*
 * ── Autorização por nível de rota ──────────────────────────────────────────
 * Cada rota declara em config/routes.php quem pode chamá-la (ver Permissao).
 * A checagem é feita AQUI, antes do controller: o que impede a execução é o
 * servidor, nunca o menu escondido nem a "URL inerte" abaixo (um fetch() do
 * console passa por ela como same-origin). Rota sem nível declarado é negada
 * para todos menos o ADMIN — esquecer de classificar uma rota nova fecha a
 * porta em vez de abri-la.
 */
if ($logado && isset($routes[$uri]) && !in_array($uri, $rotasPublicas, true)) {
    if (!Permissao::podeAcessarRota($_SESSION['user_tipo'] ?? null, $routes, $uri)) {
        if (Permissao::nivelDaRota($routes, $uri) === null) {
            error_log("[acesso] Rota '{$uri}' sem nível de acesso em config/routes.php — negada por padrão.");
        }

        Erro::exibir('sem-permissao');
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
    'consultarPlacaVeiculo',
    'modelosPorMarca',
    'criarModelo',
    'recuperar-senha',
    'redefinir-senha',
    'painel',
    'painelTecnico',
], $rotasDeErro, $rotasPublicas);

if (
    $logado
    && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && !in_array($uri, $rotasNavegacaoDireta, true)
    && !navegacaoInterna()
) {
    // Técnico não usa o dashboard financeiro — cai direto na própria
    // central de trabalho, mesmo critério de UserController::destinoAposLogin().
    $destinoInerte = (($_SESSION['user_tipo'] ?? '') === 'TECNICO') ? './painelTecnico' : './home';
    header('Location: ' . $destinoInerte);
    exit;
}

/*
 * ── Caixa obrigatório ──────────────────────────────────────────────────────
 * Enquanto não houver um caixa ABERTO, usuário do balcão (ADMIN/ATENDENTE) é
 * redirecionado para a tela de abertura. As rotas abaixo continuam acessíveis
 * para permitir fazer login/logout, gerenciar o próprio caixa
 * (abrir/fechar/histórico) e usar os endpoints de recuperação de senha.
 *
 * (Havia aqui um "fechamento automático por aba fechada" que consultava uma
 * coluna que nunca existiu no banco e falhava em silêncio a cada requisição.
 * Removido: o caixa fecha no logout/expiração do dono dele — ver UserController.)
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
    'consultarPlacaVeiculo',
    'modelosPorMarca',
    'criarModelo',
    // Painel modo TV — tela passiva, não faz sentido exigir caixa aberto.
    'painel',
], $rotasPublicas);

/*
 * O TÉCNICO fica de fora desta regra: com a matriz de permissões, ele não pode
 * mais abrir caixa — sem esta condição ele seria mandado para ./abrirCaixa e
 * levaria um 403 lá, num redirecionamento sem saída. Caixa é controle de
 * recebimento de dinheiro, não de presença: quem não opera o caixa não tem por
 * que ser barrado por ele.
 */
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

/*
 * ── Envio das mensagens pendentes ao cliente ───────────────────────────────
 * As mensagens nascem na MESMA transação da mudança de estado (nunca há aviso de algo que não aconteceu) e
 * são enviadas FORA dela. Quem envia é o agendador (bin/enviar_mensagens.php); este gancho só garante que,
 * enquanto a equipe usa o sistema, o que está na fila saia logo — depois de a página já ter sido entregue
 * e com a sessão liberada, para não atrasar nem travar o próximo clique de ninguém.
 * Sem canal configurado não faz nada. Cada mensagem é reservada antes de enviada: sem envio em duplicidade.
 */
if ($logado && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !ehRequisicaoDeScript()) {
    register_shutdown_function(function () {
        try {
            require_once __DIR__ . '/../app/models/Comunicacao.php';

            if (!(new WhatsAppCloud())->configurado() && !Integracoes::emailConfigurado()) {
                return;
            }

            $comunicacao = new Comunicacao();

            if (!$comunicacao->haPendentes()) {
                return;
            }

            ignore_user_abort(true);
            @session_write_close();

            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } else {
                while (ob_get_level() > 0) {
                    @ob_end_flush();
                }
                @flush();
            }

            $comunicacao->despacharPendentes(3);
        } catch (\Throwable $e) {
            error_log('[envio de mensagens] ' . $e->getMessage());
        }
    });
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
