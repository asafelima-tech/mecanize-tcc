<?php
// Renderiza uma view do Mecanize COM o layout, sem banco: sessão simulada + dados sintéticos. Serve para conferir
// layout/JS no Chrome quando só a estrutura da tela importa. Consultas ao banco dentro do layout já são capturadas
// pelo próprio header (try/catch) — o que não for capturado aparece aqui como erro e é um defeito real.
// uso: php tests/browser/renderizar_view.php <view> <rota> <saida.html> <perfil: ADMIN|TECNICO|ATENDENTE> [arquivo-de-dados.php]
[$_, $view, $rota, $saida, $tipo] = $argv + [null, 'Veiculos/saveVeiculo', 'saveVeiculo', 'saida.html', 'TECNICO'];
$arquivoDados = $argv[5] ?? null;
$BASE = getenv('AUD_BASE') ?: 'http://127.0.0.1:8099/';
$app = str_replace('\\', '/', dirname(__DIR__, 2)) . '/';

date_default_timezone_set('America/Sao_Paulo');
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '0');

require_once $app . 'app/core/Env.php';
Env::carregar($app . '.env');
foreach (['Controller', 'Database', 'Csrf', 'Html', 'Permissao'] as $c) { require_once $app . "app/core/$c.php"; }
require_once $app . 'config/empresa.php';

session_id('render' . bin2hex(random_bytes(4)));
session_start();
$_SESSION = ['logged' => true, 'user_tipo' => $tipo, 'user_nome' => 'Usuário de Teste', 'user_id_usuario' => 1, 'user_email' => 'teste@test.local'];
$_GET['url'] = $rota;
$_SERVER['REQUEST_METHOD'] = 'GET';

$dados = $arquivoDados ? (require $arquivoDados) : [];

// Páginas fora do layout do sistema (portal do cliente, telas públicas).
$semLayout = (bool) preg_match('~^(Portal|Site/(termos|privacidade|rastreio))/?~', $view);

ob_start();
extract($dados, EXTR_SKIP);
if (!$semLayout) require $app . 'app/views/layout/header.php';
require $app . "app/views/$view.php";
if (!$semLayout) require $app . 'app/views/layout/footer.php';
$html = ob_get_clean();

$html = preg_replace('~<head>~i', '<head><base href="' . $BASE . '">', $html, 1);
file_put_contents($saida, $html);
echo strlen($html) . " bytes\n";
