<?php

/**
 * Front controller da v2 — toda requisição passa por aqui, nesta ordem:
 *
 *   1. rota existe?                         não → 404
 *   2. POST com token CSRF válido?          não → "formulário expirado"
 *   3. sessão: inatividade e conta revalidada no banco (desativada, alterada, oficina cancelada)
 *   4. login exigido pela rota?             não logado → tela de login
 *   5. nível de acesso da rota (Permissao)  sem permissão → 403
 *   6. licença: gravação com licença vencida/suspensa → "somente leitura"
 *   7. controller
 *
 * Ficaram de fora, de propósito, três regras da v1: o horário fixo do atendente, o "caixa
 * obrigatório" (abrir caixa antes de qualquer tela) e a "URL inerte" (só navegar por cliques). O
 * caixa continua obrigatório onde importa: o banco recusa receber em dinheiro sem caixa aberto.
 */

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

error_clear_last();
$debug = filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'self'");

set_exception_handler(static function (Throwable $e) use ($debug): void {
    if ($e instanceof ErroHttp) {
        Erro::exibir($e->tipo, $e->contexto);
    }
    if ($e instanceof LicencaBloqueada) {
        Erro::exibir('licenca-bloqueada', ['mensagem' => $e->getMessage()]);
    }

    $ocorrencia = Erro::gerarOcorrencia();
    Erro::registrar($ocorrencia, (string) $e);

    if ($debug && !Erro::ehScript()) {
        http_response_code(500);
        echo '<pre style="white-space:pre-wrap">' . e((string) $e) . '</pre>';
        exit;
    }

    Erro::exibir('falha-interna', ['ocorrencia' => $ocorrencia]);
});

$rotas = require __DIR__ . '/../config/rotas.php';
$rota  = trim((string) ($_GET['url'] ?? ''), '/');
$rota  = $rota === '' ? 'inicio' : $rota;
$post  = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

// 1. Rota
if (!isset($rotas[$rota])) {
    Sessao::iniciar();
    Erro::exibir('nao-encontrado');
}
[$controller, $metodo, $nivel] = $rotas[$rota];

Sessao::iniciar();

// 2. CSRF em todo POST
if ($post && !Csrf::valido($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) {
    Erro::exibir('sessao-expirada');
}

// 3. Sessão: relê a conta e a oficina NO BANCO a cada requisição. É daqui (e só daqui) que sai a
//    oficina usada em todas as consultas.
if (($usuarioId = Sessao::usuarioId()) !== null) {
    $motivo = Sessao::inativa() ? Sessao::INATIVIDADE : null;
    $conta  = null;

    if ($motivo === null) {
        $conta  = (new Usuario())->paraSessao($usuarioId);
        $motivo = Sessao::motivoParaEncerrar($conta);
    }

    if ($motivo !== null) {
        Sessao::sair();
        if ($rota !== 'login') {
            Erro::ehScript() ? Erro::exibir('nao-autenticado') : header('Location: ./login?motivo=' . urlencode($motivo));
            exit;
        }
    } else {
        Sessao::registrarAtividade();
        $oficina = $conta['oficina_id'] === null ? null : [
            'id'                 => (int) $conta['oficina_id'],
            'nome'               => $conta['oficina_nome'],
            'status'             => $conta['oficina_status'],
            'licenca_valida_ate' => $conta['licenca_valida_ate'],
            'plano'              => $conta['plano'],
        ];
        unset($conta['senha_hash']);
        Contexto::definir($conta, $oficina);
    }
}

// 4. Login
if ($nivel !== Permissao::PUBLICO && !Contexto::logado()) {
    if (Erro::ehScript()) {
        Erro::exibir('nao-autenticado');
    }
    $retorno = (!$post && $rota !== 'inicio') ? ['retorno' => $rota . (($q = array_diff_key($_GET, ['url' => 1])) ? '?' . http_build_query($q) : '')] : [];
    header('Location: ./login' . ($retorno ? '?' . http_build_query($retorno) : ''));
    exit;
}

// 5. Permissão da rota
if (!Permissao::permite(Contexto::perfil(), $nivel)) {
    Erro::exibir('sem-permissao');
}

// 6. Licença: com a licença vencida/suspensa só sair e telas sem login aceitam POST.
//    (Os models também conferem — Contexto::exigirEscrita() — então é uma trava dupla.)
if ($post && $nivel !== Permissao::PUBLICO && Contexto::oficina() !== null && !Licenca::permiteEscrita(Contexto::oficina())) {
    Erro::exibir('licenca-bloqueada', ['mensagem' => Licenca::mensagem(Contexto::oficina())]);
}

// 7. Controller
(new $controller())->$metodo();
