<?php

/**
 * Telas de erro. Cada situação tem título, explicação e saídas; nenhuma mostra detalhe técnico
 * (esse vai só para o log, com o código de ocorrência que aparece na tela).
 *
 * Controllers e models não montam tela de erro: lançam ErroHttp('tipo') e o front controller exibe.
 */
final class Erro
{
    public const CATALOGO = [
        'nao-encontrado'       => [404, 'Página não encontrada', 'O endereço que você acessou não existe ou foi movido.'],
        'registro-inexistente' => [404, 'Registro não encontrado', 'O item que você tentou abrir não existe ou não pertence à sua oficina.'],
        'rastreio-invalido'    => [404, 'Link de acompanhamento inválido', 'Este link não corresponde a nenhum serviço.'],
        'sem-permissao'        => [403, 'Acesso negado', 'Sua conta não tem permissão para abrir esta tela.'],
        'nao-autenticado'      => [401, 'Sessão encerrada', 'Entre novamente para continuar.'],
        'sessao-expirada'      => [403, 'Formulário expirado', 'O formulário ficou aberto tempo demais e nada foi salvo.'],
        'licenca-bloqueada'    => [403, 'Modo somente leitura', 'A licença da oficina não permite gravar alterações agora.'],
        'falha-interna'        => [500, 'Algo falhou do nosso lado', 'A operação foi interrompida por uma falha do sistema.'],
    ];

    public static function exibir(string $tipo, array $contexto = []): never
    {
        [$http, $titulo, $resumo] = self::CATALOGO[$tipo] ?? self::CATALOGO['falha-interna'];
        $tipo = isset(self::CATALOGO[$tipo]) ? $tipo : 'falha-interna';

        if (!headers_sent()) {
            http_response_code($http);
        }

        // Requisição de script (fetch): responde JSON, não uma página.
        if (self::ehScript()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['erro' => $tipo, 'mensagem' => $contexto['mensagem'] ?? $resumo], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $resumo     = $contexto['mensagem'] ?? $resumo;
        $ocorrencia = $contexto['ocorrencia'] ?? null;
        $voltar     = $contexto['voltar'] ?? null;
        require V2_RAIZ . '/app/views/erros/pagina.php';
        exit;
    }

    public static function gerarOcorrencia(): string
    {
        return strtoupper(bin2hex(random_bytes(4)));
    }

    public static function registrar(string $ocorrencia, string $mensagem): void
    {
        error_log("[MECANIZE-V2:{$ocorrencia}] {$mensagem}");
    }

    public static function ehScript(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'empty');
    }

    /** CSS da página de erro embutido (a página não pode depender de um arquivo sendo servido). */
    public static function css(): string
    {
        $arquivo = PROJETO_RAIZ . '/public/assets/css/erros.css';

        return is_file($arquivo) ? (string) file_get_contents($arquivo) : '';
    }
}

/** Interrompe a requisição com uma das telas de Erro::CATALOGO. */
final class ErroHttp extends RuntimeException
{
    public function __construct(public readonly string $tipo, public readonly array $contexto = [])
    {
        parent::__construct($tipo);
    }
}
