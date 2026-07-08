<?php

/**
 * Envio de e-mail via SMTP simples (sem bibliotecas externas). Suporta
 * autenticação com STARTTLS, compatível com a maioria dos provedores
 * (Gmail, Outlook, SMTP de hospedagem, SendGrid, etc.).
 * Configuração em config/mail.php.
 */
class Mailer
{
    private $host;
    private $porta;
    private $usuario;
    private $senha;
    private $remetenteNome;
    private $remetenteEmail;
    private $verificarCertificado;
    private $arquivoCertificado;

    public function __construct()
    {
        require_once __DIR__ . '/Env.php';
        Env::carregar(__DIR__ . '/../../.env');

        $config = require __DIR__ . '/../../config/mail.php';

        $this->host                 = $config['host'];
        $this->porta                = $config['porta'];
        $this->usuario              = $config['usuario'];
        $this->senha                = $config['senha'];
        $this->remetenteNome        = $config['remetente_nome'];
        $this->remetenteEmail       = $config['remetente_email'];
        // Em produção, deixe true (o padrão). Só desative em ambiente local
        // sem certificado configurado — nesse caso a conexão fica vulnerável
        // a interceptação, use apenas para testes.
        $this->verificarCertificado = $config['verificar_certificado'] ?? true;
        // Caminho explícito do bundle de certificados CA, para não depender
        // do php.ini estar configurado corretamente no ambiente (comum em
        // instalações XAMPP onde o certificado embutido está desatualizado).
        $this->arquivoCertificado   = $config['cafile'] ?? null;
    }

    /**
     * @param string      $destinatario
     * @param string      $assunto
     * @param string      $corpoHtml
     * @param string|null $corpoTexto Versão em texto puro (opcional). Se omitida,
     *                                é derivada automaticamente do HTML — mensagens
     *                                só-HTML são um sinal de spam para vários filtros.
     */
    public function enviar($destinatario, $assunto, $corpoHtml, $corpoTexto = null)
    {
        $conexao = @fsockopen($this->host, $this->porta, $errno, $errstr, 10);
        if (!$conexao) {
            throw new \RuntimeException("Falha ao conectar no SMTP: {$errstr}");
        }

        $this->ler($conexao);
        $this->comando($conexao, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $this->comando($conexao, "STARTTLS");

        stream_context_set_option($conexao, 'ssl', 'verify_peer', $this->verificarCertificado);
        stream_context_set_option($conexao, 'ssl', 'verify_peer_name', $this->verificarCertificado);
        stream_context_set_option($conexao, 'ssl', 'allow_self_signed', !$this->verificarCertificado);

        if ($this->verificarCertificado && $this->arquivoCertificado && is_file($this->arquivoCertificado)) {
            stream_context_set_option($conexao, 'ssl', 'cafile', $this->arquivoCertificado);
        }

        if (!stream_socket_enable_crypto($conexao, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new \RuntimeException("Falha ao iniciar TLS com o servidor SMTP.");
        }

        $this->comando($conexao, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $this->comando($conexao, "AUTH LOGIN");
        $this->comando($conexao, base64_encode($this->usuario));
        $this->comando($conexao, base64_encode($this->senha));

        $this->comando($conexao, "MAIL FROM:<{$this->remetenteEmail}>");
        $this->comando($conexao, "RCPT TO:<{$destinatario}>");
        $this->comando($conexao, "DATA");

        $corpoTexto = $corpoTexto ?? $this->textoPlano($corpoHtml);
        $dominio    = substr(strrchr($this->remetenteEmail, '@'), 1) ?: ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $boundary   = 'mecanize_' . bin2hex(random_bytes(12));

        // Date + Message-ID: sem eles, boa parte dos filtros de spam já
        // penaliza a mensagem de cara, independente do conteúdo.
        $cabecalhos =
            "From: {$this->remetenteNome} <{$this->remetenteEmail}>\r\n" .
            "To: <{$destinatario}>\r\n" .
            "Reply-To: {$this->remetenteEmail}\r\n" .
            "Date: " . date('r') . "\r\n" .
            "Message-ID: <" . bin2hex(random_bytes(16)) . "@{$dominio}>\r\n" .
            "Subject: =?UTF-8?B?" . base64_encode($assunto) . "?=\r\n" .
            "MIME-Version: 1.0\r\n" .
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

        // multipart/alternative com texto puro + HTML: e-mails só-HTML, sem
        // fallback em texto, são um dos sinais mais comuns usados por filtros
        // de spam (indica envio automatizado/em massa mal formatado).
        $corpo =
            "--{$boundary}\r\n" .
            "Content-Type: text/plain; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: 8bit\r\n\r\n" .
            $corpoTexto . "\r\n\r\n" .
            "--{$boundary}\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: 8bit\r\n\r\n" .
            $corpoHtml . "\r\n\r\n" .
            "--{$boundary}--";

        // Dot-stuffing (RFC 5321): uma linha que começa com "." dentro do
        // corpo encerraria a seção DATA prematuramente se não for escapada.
        $corpo = preg_replace('/^\./m', '..', $corpo);

        fwrite($conexao, $cabecalhos . "\r\n" . $corpo . "\r\n.\r\n");
        $this->ler($conexao);

        $this->comando($conexao, "QUIT");
        fclose($conexao);

        return true;
    }

    /**
     * Deriva uma versão em texto puro a partir do HTML (quebras de parágrafo/
     * linha viram \n antes do strip_tags, senão o texto sai todo grudado).
     */
    private function textoPlano(string $html): string
    {
        $texto = preg_replace('/<(br|\/p|\/div|\/tr)\s*\/?>/i', "\n", $html);
        $texto = strip_tags($texto);
        $texto = html_entity_decode($texto, ENT_QUOTES, 'UTF-8');
        $texto = preg_replace('/[ \t]+/', ' ', $texto);
        $texto = preg_replace('/\n{3,}/', "\n\n", $texto);

        return trim($texto);
    }

    private function comando($conexao, $comando)
    {
        fwrite($conexao, $comando . "\r\n");
        return $this->ler($conexao);
    }

    private function ler($conexao)
    {
        $resposta = '';
        while ($linha = fgets($conexao, 515)) {
            $resposta .= $linha;
            if (isset($linha[3]) && $linha[3] === ' ') {
                break;
            }
        }

        $codigo = (int) substr($resposta, 0, 3);
        if ($codigo >= 400) {
            throw new \RuntimeException("Erro SMTP: {$resposta}");
        }

        return $resposta;
    }
}
