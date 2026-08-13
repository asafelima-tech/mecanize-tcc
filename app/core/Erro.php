<?php

/**
 * Catálogo e renderização das telas de erro.
 *
 * Centraliza tudo o que define um erro para o usuário — o que aconteceu, por
 * que aconteceu, o que dá pra fazer em seguida — num lugar só, para que nenhum
 * ponto do sistema precise montar mensagem na mão (era assim que apareciam
 * respostas técnicas cruas, tipo o caminho absoluto do arquivo na tela).
 *
 * Duas formas de exibição:
 *   'app'      — dentro do layout normal, com menu lateral. Preserva o contexto
 *                de navegação: o usuário continua "dentro" do sistema.
 *   'autonomo' — página fechada em si mesma, sem header/footer/CSS externo.
 *                Usada quando a própria infraestrutura falhou (banco fora,
 *                configuração quebrada) e depender do layout seria arriscado.
 *
 * A escolha entre as duas é automática: erro de infraestrutura é sempre
 * autônomo; o resto usa o layout do app quando há sessão ativa e cai no
 * autônomo quando não há (numa tela de erro pré-login não existe menu).
 */
class Erro
{
    /**
     * Cada entrada descreve uma situação, não um número de status: é o que
     * permite ter telas distintas para casos que compartilham o mesmo código
     * HTTP (404 de rota inexistente x 404 de registro que não existe mais).
     *
     * arquetipo: define o layout base (bussola / acesso / sistema), para as
     * categorias não ficarem todas com a mesma cara.
     */
    public const CATALOGO = [
        'nao-encontrado' => [
            'http'      => 404,
            'arquetipo' => 'bussola',
            'tom'       => 'info',
            'titulo'    => 'Esta página não existe',
            'resumo'    => 'O endereço acessado não corresponde a nenhuma tela do sistema.',
            'porque'    => 'Normalmente é um link antigo, um endereço digitado com algum caractere a mais, ou uma página que mudou de lugar numa atualização.',
            'autonomo'  => false,
        ],

        'registro-inexistente' => [
            'http'      => 404,
            'arquetipo' => 'bussola',
            'tom'       => 'info',
            'titulo'    => 'Registro não encontrado',
            'resumo'    => 'O item que você tentou abrir não está mais no sistema.',
            'porque'    => 'Ele pode ter sido excluído ou arquivado por outra pessoa depois que sua tela foi carregada.',
            'autonomo'  => false,
        ],

        // Link de acompanhamento público (sem login) que não resolve. Quem vê
        // é o cliente da oficina, não um usuário do sistema — a linguagem e a
        // saída são outras: aqui não existe "ir para o início".
        'rastreio-invalido' => [
            'http'      => 404,
            'arquetipo' => 'bussola',
            'tom'       => 'info',
            'titulo'    => 'Link de acompanhamento inválido',
            'resumo'    => 'Este link não corresponde a nenhum serviço ou orçamento.',
            'porque'    => 'O endereço pode ter sido copiado pela metade, ou o serviço já foi encerrado e o acompanhamento saiu do ar.',
            'autonomo'  => true,
        ],

        'sem-permissao' => [
            'http'      => 403,
            'arquetipo' => 'acesso',
            'tom'       => 'danger',
            'titulo'    => 'Você não tem acesso a esta área',
            'resumo'    => 'Sua conta não tem permissão para abrir esta tela.',
            'porque'    => 'Cada perfil enxerga uma parte do sistema. Esta área é restrita a outro nível de acesso.',
            'autonomo'  => false,
        ],

        'nao-autenticado' => [
            'http'      => 401,
            'arquetipo' => 'acesso',
            'tom'       => 'info',
            'titulo'    => 'Você precisa entrar de novo',
            'resumo'    => 'Sua sessão não está mais ativa.',
            'porque'    => 'Por segurança, o acesso é encerrado depois de um tempo sem uso ou quando a conta é aberta em outro lugar.',
            'autonomo'  => true,
        ],

        'sessao-expirada' => [
            // 419 não é código HTTP registrado e este Apache derruba pra 500;
            // 403 é o código certo pra "requisição rejeitada por segurança".
            'http'      => 403,
            'arquetipo' => 'acesso',
            'tom'       => 'warning',
            'titulo'    => 'Esta página ficou aberta tempo demais',
            'resumo'    => 'O formulário expirou antes de ser enviado, então nada foi salvo.',
            'porque'    => 'Cada formulário carrega um selo de segurança com validade. Isso protege sua conta contra envios disparados por outros sites.',
            'autonomo'  => false,
        ],

        'falha-interna' => [
            'http'      => 500,
            'arquetipo' => 'sistema',
            'tom'       => 'danger',
            'titulo'    => 'Algo falhou do nosso lado',
            'resumo'    => 'A operação foi interrompida por uma falha do sistema.',
            'porque'    => 'O erro foi registrado automaticamente com um código de ocorrência. Não é preciso reportar manualmente.',
            'autonomo'  => true,
        ],

        'indisponivel' => [
            'http'      => 503,
            'arquetipo' => 'sistema',
            'tom'       => 'warning',
            'titulo'    => 'Sistema temporariamente indisponível',
            'resumo'    => 'O sistema está passando por uma manutenção rápida.',
            'porque'    => 'Atualizações são feitas fora do horário de pico e costumam durar poucos minutos.',
            'autonomo'  => true,
        ],

        'sem-conexao' => [
            'http'      => 503,
            'arquetipo' => 'sistema',
            'tom'       => 'warning',
            'titulo'    => 'Sem conexão com a internet',
            'resumo'    => 'Não foi possível falar com o servidor.',
            'porque'    => 'A conexão caiu ou está instável. Seus dados preenchidos continuam na tela.',
            'autonomo'  => true,
        ],

        'tempo-esgotado' => [
            'http'      => 504,
            'arquetipo' => 'sistema',
            'tom'       => 'warning',
            'titulo'    => 'A operação demorou demais',
            'resumo'    => 'O servidor não respondeu no tempo esperado.',
            'porque'    => 'Costuma acontecer com consultas pesadas ou quando a conexão está lenta. Na maioria das vezes, tentar de novo resolve.',
            'autonomo'  => true,
        ],
    ];

    /** Tipo usado quando pedem um que não existe no catálogo. */
    private const PADRAO = 'falha-interna';

    /**
     * Exibe a tela de erro e encerra a requisição.
     *
     * $contexto aceita:
     *   'detalhe'   → uma linha extra específica da situação
     *   'ocorrencia'→ código do incidente (gerado automaticamente se ausente)
     *   'voltar'    → URL do botão "voltar" (padrão: histórico do navegador)
     *   'entidade'  → nome do que não foi achado (ex.: "Ordem de serviço")
     */
    public static function exibir(string $tipo, array $contexto = []): void
    {
        $erro = self::descrever($tipo);

        if (!headers_sent()) {
            http_response_code($erro['http']);
        }

        $contexto['ocorrencia'] = $contexto['ocorrencia'] ?? self::gerarOcorrencia();

        self::renderizar($erro, $contexto);
        exit;
    }

    /**
     * Metadados de um tipo, já com o identificador embutido. Tipo desconhecido
     * cai em falha interna — uma tela de erro nunca pode, ela própria, quebrar
     * por causa de um nome errado.
     */
    public static function descrever(string $tipo): array
    {
        $dados = self::CATALOGO[$tipo] ?? self::CATALOGO[self::PADRAO];
        $dados['tipo'] = isset(self::CATALOGO[$tipo]) ? $tipo : self::PADRAO;

        return $dados;
    }

    /**
     * Código curto de ocorrência, para o usuário informar ao suporte e o
     * responsável localizar a linha exata no log. Sem ele, "deu erro às 15h"
     * é tudo o que se tem pra procurar.
     */
    public static function gerarOcorrencia(): string
    {
        return strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    /**
     * Registra a falha no log já com o código de ocorrência, para o que o
     * usuário vê na tela e o que está no log poderem ser cruzados.
     */
    public static function registrar(string $ocorrencia, string $mensagem): void
    {
        error_log("[MECANIZE:{$ocorrencia}] {$mensagem}");
    }

    private static function renderizar(array $erro, array $contexto): void
    {
        $logado = isset($_SESSION['logged']) && $_SESSION['logged'] === true;

        // Infraestrutura quebrada nunca tenta montar o layout do app.
        $autonomo = $erro['autonomo'] || !$logado;

        $view = __DIR__ . '/../views/Site/erros/' . $erro['arquetipo'] . '.php';

        if (!is_file($view)) {
            // Último recurso: se até o template sumiu, ainda assim entrega algo
            // legível em vez de página em branco ou aviso do PHP.
            echo '<!doctype html><meta charset="utf-8"><title>Erro</title>'
                . '<p style="font:16px system-ui;padding:40px">'
                . htmlspecialchars($erro['titulo']) . '</p>';
            return;
        }

        if ($autonomo) {
            require __DIR__ . '/../views/Site/erros/_autonomo.php';
            return;
        }

        require __DIR__ . '/../views/layout/header.php';
        require $view;
        require __DIR__ . '/../views/layout/footer.php';
    }

    /**
     * CSS das telas de erro, para embutir nas páginas autônomas (que não podem
     * depender de um <link> — se o servidor está com problema, o arquivo pode
     * nem ser servido). O mesmo arquivo é usado via <link> no layout normal.
     */
    public static function css(): string
    {
        return self::lerAsset('css/erros.css');
    }

    /** Mesma ideia do css(): o comportamento da tela não pode depender de rede. */
    public static function js(): string
    {
        return self::lerAsset('js/erros.js');
    }

    private static function lerAsset(string $relativo): string
    {
        $caminho = __DIR__ . '/../../public/assets/' . $relativo;

        return is_file($caminho) ? (string) file_get_contents($caminho) : '';
    }
}
