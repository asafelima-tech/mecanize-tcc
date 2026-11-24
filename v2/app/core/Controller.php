<?php

/** Base dos controllers: desenhar tela, redirecionar, responder JSON e ler o que veio do formulário. */
abstract class Controller
{
    /**
     * Desenha uma view. Com $layout = 'app' a tela entra no layout do sistema (menu + topo);
     * 'auth' é a casca das telas de quem não está logado; null desenha a view sozinha.
     */
    protected function tela(string $view, array $dados = [], ?string $layout = 'app'): void
    {
        $arquivo = V2_RAIZ . "/app/views/{$view}.php";
        if (!is_file($arquivo)) {
            throw new LogicException("View inexistente: {$view}");
        }

        // Pedida pela janela (modal) do sistema: só o conteúdo da tela, sem menu nem topo.
        if ($layout === 'app' && self::ehModal()) {
            $layout = null;
        }

        extract($dados, EXTR_SKIP);
        ob_start();
        require $arquivo;
        $conteudo = ob_get_clean();

        if ($layout === null) {
            // Na janela, o aviso pendente (ex.: "Perfil atualizado.") vai num cabeçalho e o modal.js mostra no topo.
            if (self::ehModal() && ($aviso = self::tirarAviso()) && !headers_sent()) {
                header('X-Mecanize-Aviso: ' . rawurlencode((string) json_encode($aviso, JSON_UNESCAPED_UNICODE)));
            }
            echo $conteudo;
            return;
        }

        require V2_RAIZ . "/app/views/layout/{$layout}.php";
    }

    protected function redirecionar(string $rota, array $params = []): never
    {
        $destino = './' . $rota . ($params ? '?' . http_build_query($params) : '');
        // Na janela (modal), quem navega é o script: ele fecha a janela e abre o destino.
        if (self::ehModal()) {
            $this->json(['ir' => $destino]);
        }
        header('Location: ' . $destino);
        exit;
    }

    /** A requisição veio da janela (modal) de criar/editar (public/js/modal.js). */
    public static function ehModal(): bool
    {
        return ($_SERVER['HTTP_X_MECANIZE_MODAL'] ?? '') === '1';
    }

    protected function json($dados, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function ehPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    protected function post(string $campo, string $padrao = ''): string
    {
        $v = $_POST[$campo] ?? $padrao;

        return is_string($v) ? trim($v) : $padrao;
    }

    protected function idDaUrl(string $campo = 'id'): int
    {
        $id = filter_var($_GET[$campo] ?? $_POST[$campo] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || $id === null) {
            throw new ErroHttp('registro-inexistente');
        }

        return (int) $id;
    }

    /** Mensagem de uma tela para a próxima (depois de redirecionar). */
    protected function avisar(string $mensagem, string $tipo = 'sucesso'): void
    {
        $_SESSION['aviso'] = ['tipo' => $tipo, 'mensagem' => $mensagem];
    }

    public static function tirarAviso(): ?array
    {
        $a = $_SESSION['aviso'] ?? null;
        unset($_SESSION['aviso']);

        return $a;
    }
}
