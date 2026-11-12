<?php

/**
 * Catálogo e renderização das telas de erro.
 *
 * Centraliza tudo o que define um erro para o usuário — o que aconteceu e o
 * que dá pra fazer em seguida — num lugar só, para que nenhum ponto do sistema
 * precise montar mensagem na mão (era assim que apareciam respostas técnicas
 * cruas, tipo o caminho absoluto do arquivo na tela).
 *
 * TODAS as telas de erro são a mesma página (views/Site/erros/_autonomo.php +
 * pagina.php): logo, o código HTTP em destaque, o título, uma linha de
 * explicação e as saídas. Entre um erro e outro muda o texto e as saídas, nada
 * do visual. A página é fechada em si mesma — não monta o layout do sistema —
 * porque quando a própria infraestrutura falha (banco fora, configuração
 * quebrada) depender dele seria arriscado.
 */
class Erro
{
    /**
     * Cada entrada descreve uma situação, não um número de status: é o que
     * permite ter telas distintas para casos que compartilham o mesmo código
     * HTTP (404 de rota inexistente x 404 de registro que não existe mais).
     *
     * http:    o status com que a página é de fato servida (e o número em destaque).
     * titulo:  o título da tela.
     * resumo:  a única linha de explicação abaixo do título.
     * publico: quem vê é o cliente da oficina (link de acompanhamento/portal),
     *          não um usuário do sistema — sem "início" nem "login" nas saídas.
     */
    public const CATALOGO = [
        'nao-encontrado' => [
            'http'   => 404,
            'titulo' => 'Página não encontrada',
            'resumo' => 'O endereço que você acessou não existe ou foi movido.',
        ],

        'registro-inexistente' => [
            'http'   => 404,
            'titulo' => 'Registro não encontrado',
            'resumo' => 'O item que você tentou abrir não está mais no sistema.',
        ],

        // Link de acompanhamento público (sem login) que não resolve. Quem vê
        // é o cliente da oficina, não um usuário do sistema.
        'rastreio-invalido' => [
            'http'    => 404,
            'titulo'  => 'Link de acompanhamento inválido',
            'resumo'  => 'Este link não corresponde a nenhum serviço ou orçamento.',
            'publico' => true,
        ],

        // Link do portal do cliente: não diferencia "nunca existiu", "expirou" e "foi cancelado" de propósito.
        'portal-invalido' => [
            'http'    => 404,
            'titulo'  => 'Este link não está mais disponível',
            'resumo'  => 'O link expirou ou foi substituído. Peça um novo link à oficina.',
            'publico' => true,
        ],

        'sem-permissao' => [
            'http'   => 403,
            'titulo' => 'Acesso negado',
            'resumo' => 'Sua conta não tem permissão para abrir esta tela.',
        ],

        'nao-autenticado' => [
            'http'   => 401,
            'titulo' => 'Sessão encerrada',
            'resumo' => 'Entre novamente para continuar.',
        ],

        'sessao-expirada' => [
            // 419 não é código HTTP registrado e este Apache derruba pra 500;
            // 403 é o código certo pra "requisição rejeitada por segurança".
            'http'   => 403,
            'titulo' => 'Formulário expirado',
            'resumo' => 'O formulário ficou aberto tempo demais e nada foi salvo.',
        ],

        'falha-interna' => [
            'http'   => 500,
            'titulo' => 'Algo falhou do nosso lado',
            'resumo' => 'A operação foi interrompida por uma falha do sistema.',
        ],

        'indisponivel' => [
            'http'   => 503,
            'titulo' => 'Sistema em manutenção',
            'resumo' => 'Volte em alguns minutos: a atualização costuma ser rápida.',
        ],

        'sem-conexao' => [
            'http'   => 503,
            'titulo' => 'Sem conexão',
            'resumo' => 'Não foi possível falar com o servidor. Confira a sua internet.',
        ],

        'tempo-esgotado' => [
            'http'   => 504,
            'titulo' => 'A operação demorou demais',
            'resumo' => 'O servidor não respondeu no tempo esperado.',
        ],
    ];

    /** Tipo usado quando pedem um que não existe no catálogo. */
    private const PADRAO = 'falha-interna';

    /**
     * Exibe a tela de erro e encerra a requisição.
     *
     * $contexto aceita:
     *   'ocorrencia'→ código do incidente (gerado automaticamente se ausente)
     *   'voltar'    → URL do botão principal do "registro não encontrado"
     *   'entidade'  → nome do que não foi achado (ex.: "Ordem de serviço")
     *   'retorno'   → endereço para onde voltar depois de entrar de novo
     *   'publico'   → quem vê é o cliente da oficina (sem saídas para o sistema)
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
        $view = __DIR__ . '/../views/Site/erros/pagina.php';

        if (!is_file($view)) {
            // Último recurso: se até o template sumiu, ainda assim entrega algo
            // legível em vez de página em branco ou aviso do PHP.
            echo '<!doctype html><meta charset="utf-8"><title>Erro</title>'
                . '<p style="font:16px system-ui;padding:40px">'
                . htmlspecialchars($erro['titulo']) . '</p>';
            return;
        }

        require __DIR__ . '/../views/Site/erros/_autonomo.php';
    }

    /**
     * A logo oficial (logo-mecanize-doc.jpg) como data URI. A página não pode
     * depender de um arquivo de imagem sendo servido: se o servidor está com
     * problema, a marca ainda precisa aparecer.
     */
    public static function logo(): string
    {
        static $uri = null;

        if ($uri === null) {
            $caminho = __DIR__ . '/../../public/assets/img/logo-mecanize-doc.jpg';
            $uri = is_file($caminho) ? 'data:image/jpeg;base64,' . base64_encode((string) file_get_contents($caminho)) : '';
        }

        return $uri;
    }

    /**
     * CSS das telas de erro, para embutir na página (que não pode depender de
     * um <link> — se o servidor está com problema, o arquivo pode nem ser
     * servido). O mesmo arquivo é usado via <link> no layout normal, onde só
     * os alertas inline (.alerta*) existem.
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
