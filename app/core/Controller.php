<?php
class Controller
{
    public function model($model)
    {
        require_once __DIR__ . "/../models/{$model}.php";
        return new $model();
    }

    /**
     * Monta uma URL absoluta pro sistema (ex.: link de rastreio enviado por
     * e-mail/WhatsApp, que precisa funcionar fora do navegador logado).
     */
    protected function urlAbsoluta(string $caminhoRelativo): string
    {
        $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $base = $protocolo . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

        return $base . '/' . ltrim($caminhoRelativo, '/');
    }

    /**
     * Bloqueia o acesso ao método atual se o papel do usuário logado
     * (sessão já garantida pelo public/index.php) não estiver entre os
     * permitidos. Esconder o link no menu não é controle de acesso — isso
     * é o que de fato impede a execução da rota.
     */
    protected function requireRole(array $tiposPermitidos): void
    {
        $tipo = $_SESSION['user_tipo'] ?? null;

        if (!in_array($tipo, $tiposPermitidos, true)) {
            http_response_code(403);
            $this->view('Site/403');
            exit;
        }
    }

    public function view($view, $data = [])
    {
        extract($data);

        // Views que não usam o layout padrão (sem sidebar/topbar)
        // Comparação sensível a maiúsculas: inclui as variações usadas pelos controllers.
        // Views sem sidebar/topbar. Além das telas de tela cheia, entram aqui
        // os fragmentos carregados por fetch (prefixo _), que substituem só um
        // pedaço de uma página já renderizada.
        $noLayout = ['auth/login', 'user/login', 'Usuarios/login', 'Site/termos', 'Site/privacidade', 'Site/painel', 'Site/rastreio', 'Site/rastreioNaoEncontrado', 'Usuarios/_tabelaUsuarios'];

        if (in_array($view, $noLayout)) {
            require_once __DIR__ . "/../views/{$view}.php";
            return;
        }

        require_once __DIR__ . "/../views/layout/header.php";
        require_once __DIR__ . "/../views/{$view}.php";
        require_once __DIR__ . "/../views/layout/footer.php";
    }
}
