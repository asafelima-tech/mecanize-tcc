<?php
class Controller
{
    public function model($model)
    {
        require_once __DIR__ . "/../models/{$model}.php";
        return new $model();
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
        $noLayout = ['auth/login', 'user/login', 'Usuarios/login', 'Site/termos', 'Site/privacidade', 'Site/painel'];

        if (in_array($view, $noLayout)) {
            require_once __DIR__ . "/../views/{$view}.php";
            return;
        }

        require_once __DIR__ . "/../views/layout/header.php";
        require_once __DIR__ . "/../views/{$view}.php";
        require_once __DIR__ . "/../views/layout/footer.php";
    }
}
