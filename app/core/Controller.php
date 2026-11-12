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
            require_once __DIR__ . '/Erro.php';
            Erro::exibir('sem-permissao');
        }
    }

    /**
     * Encerra a requisição com a tela de "registro não encontrado".
     *
     * Existe porque o padrão anterior era um redirecionamento mudo pra
     * listagem: quem clicava num item já excluído voltava pra lista sem
     * entender se tinha clicado errado, se o sistema tinha travado ou se o
     * registro sumiu. Agora a tela diz o que houve e leva de volta.
     *
     * @param string $entidade Nome do que não foi achado ("Cliente", "Ordem de serviço")
     * @param string $voltar   Rota da listagem de origem
     */
    protected function naoEncontrado(string $entidade, string $voltar): void
    {
        require_once __DIR__ . '/Erro.php';

        Erro::exibir('registro-inexistente', [
            'entidade' => $entidade,
            'voltar'   => $voltar,
        ]);
    }

    public function view($view, $data = [])
    {
        // EXTR_SKIP: sem isso, uma chave chamada "view" ou "data" dentro dos
        // dados sobrescreveria a variável do próprio método e mudaria qual
        // arquivo é incluído. Comportamento idêntico no uso atual.
        extract($data, EXTR_SKIP);

        // Views que não usam o layout padrão (sem sidebar/topbar).
        // Comparação sensível a maiúsculas: inclui as variações usadas pelos controllers.
        // As telas de senha entram aqui pelo mesmo motivo do login: atendem quem
        // está FORA do sistema (inclusive por link de e-mail), então não podem
        // aparecer dentro da sidebar/topbar como se a pessoa já estivesse logada.
        $noLayout = [
            'auth/login',
            'user/login',
            'Usuarios/login',
            'Usuarios/recuperarSenha',
            'Usuarios/redefinirSenha',
            'Site/termos',
            'Site/privacidade',
            'Site/painel',
            'Site/rastreio',
            // Portal do cliente: página própria, fora do sistema (o cliente não é usuário).
            'Portal/caso',
            'Portal/pagamentoSimulado',
        ];

        // Fragmentos carregados por fetch (prefixo _) substituem só um pedaço
        // de uma página já renderizada, então nunca levam layout junto.
        // É regra por prefixo, e não uma lista: com lista, criar um fragmento
        // novo e esquecer de registrá-lo aqui injeta header+footer inteiros
        // dentro da tabela — falha silenciosa e confusa de diagnosticar.
        $ehFragmento = str_starts_with(basename($view), '_');

        if ($ehFragmento || in_array($view, $noLayout)) {
            require_once __DIR__ . "/../views/{$view}.php";
            return;
        }

        require_once __DIR__ . "/../views/layout/header.php";
        require_once __DIR__ . "/../views/{$view}.php";
        require_once __DIR__ . "/../views/layout/footer.php";
    }
}
