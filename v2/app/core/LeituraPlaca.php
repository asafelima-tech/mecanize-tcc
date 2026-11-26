<?php

require_once __DIR__ . '/HttpTransporte.php';

/**
 * Leitura de placa por foto (Plate Recognizer). Trazida da v1 (app/core/LeituraPlaca.php).
 *
 * A leitura é uma SUGESTÃO para conferência, nunca um dado confirmado: o resultado sempre diz o quanto
 * o serviço confia (`confianca`), se o texto tem formato de placa brasileira (`formato`) e quais foram
 * as alternativas (`candidatos`). A tela só preenche o campo depois que a pessoa escolhe. Nada é gravado aqui.
 *
 * Falhas viram códigos estáveis (NAO_CONFIGURADO, SEM_PLACA, LIMITE, TEMPO_ESGOTADO, INDISPONIVEL,
 * IMAGEM_INVALIDA, TAMANHO) com mensagem compreensível; o detalhe técnico do serviço fica no log e
 * nunca vai para a tela. O envio usa o HttpTransporte, então os testes não tocam a rede.
 * Credencial: PLATE_RECOGNIZER_TOKEN no .env.
 */
final class LeituraPlaca
{
    public const URL = 'https://api.platerecognizer.com/v1/plate-reader/';

    /** A tela reduz a foto antes de enviar; isto é só o teto do que o servidor aceita. */
    public const LIMITE_BYTES = 4 * 1024 * 1024;

    /** Abaixo disso a leitura é marcada como incerta (a conferência continua obrigatória em qualquer caso). */
    public const CONFIANCA_ALTA = 0.85;

    public const TIMEOUT_SEGUNDOS = 15;

    /** Teto de leituras por sessão numa janela: cada leitura tem custo no plano contratado. */
    public const MAX_POR_JANELA = 15;
    public const JANELA_SEGUNDOS = 600;

    private HttpTransporte $http;
    private ?string $token;

    public function __construct(?HttpTransporte $http = null, ?string $token = null)
    {
        $this->http = $http ?? new CurlTransporte();
        $token = trim($token ?? (string) env('PLATE_RECOGNIZER_TOKEN', ''));

        // Texto de exemplo do .env.example não é credencial.
        $this->token = ($token !== '' && !preg_match('/^(seu-|sua-|troque|exemplo|xxx|\.\.\.)/i', $token)) ? $token : null;
    }

    public function configurada(): bool
    {
        return $this->token !== null;
    }

    public static function normalizar(?string $texto): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $texto));
    }

    /** @return 'MERCOSUL'|'ANTIGO'|null  AAA0A00 (Mercosul) · AAA0000 (modelo anterior) · senão, não é placa brasileira */
    public static function formato(string $normalizada): ?string
    {
        if (preg_match('/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/', $normalizada)) {
            return 'MERCOSUL';
        }

        return preg_match('/^[A-Z]{3}[0-9]{4}$/', $normalizada) ? 'ANTIGO' : null;
    }

    /**
     * Cada leitura tem custo: controla quantas a mesma sessão pode pedir numa janela.
     *
     * @param array<int,int> $historico instantes (timestamp) das leituras recentes; alterado
     */
    public static function dentroDoLimite(array &$historico, int $agora, int $max = self::MAX_POR_JANELA, int $janela = self::JANELA_SEGUNDOS): bool
    {
        $historico = array_values(array_filter($historico, fn($t) => $t > $agora - $janela));

        if (count($historico) >= $max) {
            return false;
        }

        $historico[] = $agora;

        return true;
    }

    /**
     * @return array{ok:bool, codigo:string, erro:?string, placa:?string, formato:?string, confianca:?float,
     *               baixa_confianca:bool, candidatos:array<int,array{placa:string,confianca:?float,formato:?string}>, requer_conferencia:bool}
     */
    public function ler(string $caminhoImagem): array
    {
        if (!$this->configurada()) {
            return $this->falha('NAO_CONFIGURADO', 'A leitura automática de placa não está configurada neste sistema. Digite a placa.');
        }

        $tamanho = @filesize($caminhoImagem);

        if ($tamanho === false || $tamanho <= 0) {
            return $this->falha('IMAGEM_INVALIDA', 'Nenhuma imagem foi recebida. Tire a foto novamente.');
        }

        if ($tamanho > self::LIMITE_BYTES) {
            return $this->falha('TAMANHO', 'A foto é grande demais (mais de 4 MB). Tire de novo, mais perto da placa.');
        }

        // getimagesize lê o CONTEÚDO: é o que distingue uma foto de um .txt renomeado para .jpg.
        $info = @getimagesize($caminhoImagem);
        $tiposAceitos = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

        if ($info === false || !isset($tiposAceitos[$info[2]])) {
            return $this->falha('IMAGEM_INVALIDA', 'O arquivo enviado não é uma foto válida (use JPG, PNG ou WEBP).');
        }

        $conteudo = (string) file_get_contents($caminhoImagem);
        $fronteira = '----mecanize' . bin2hex(random_bytes(8));
        $corpo = "--{$fronteira}\r\nContent-Disposition: form-data; name=\"regions\"\r\n\r\nbr\r\n"
               . "--{$fronteira}\r\nContent-Disposition: form-data; name=\"upload\"; filename=\"placa.{$tiposAceitos[$info[2]]}\"\r\n"
               . "Content-Type: {$info['mime']}\r\n\r\n{$conteudo}\r\n--{$fronteira}--\r\n";

        $r = $this->http->requisitar('POST', self::URL, [
            'Authorization: Token ' . $this->token,
            'Content-Type: multipart/form-data; boundary=' . $fronteira,
        ], $corpo, self::TIMEOUT_SEGUNDOS);

        if ($r['status'] === 0) {
            error_log('[leitura de placa] sem resposta: ' . ($r['erro'] ?? '?'));
            $tempo = stripos((string) $r['erro'], 'time') !== false;

            return $this->falha(
                $tempo ? 'TEMPO_ESGOTADO' : 'INDISPONIVEL',
                $tempo ? 'O serviço de leitura demorou demais para responder. Tente de novo ou digite a placa.' : 'Não foi possível falar com o serviço de leitura de placa agora. Digite a placa.'
            );
        }

        if ($r['status'] === 429) {
            error_log('[leitura de placa] limite do plano atingido (HTTP 429)');

            return $this->falha('LIMITE', 'O limite de leituras automáticas do plano foi atingido. Digite a placa.');
        }

        if ($r['status'] === 401 || $r['status'] === 403) {
            error_log("[leitura de placa] credencial recusada (HTTP {$r['status']})");

            return $this->falha('NAO_CONFIGURADO', 'O serviço de leitura recusou a credencial configurada. Avise o administrador e digite a placa.');
        }

        if ($r['status'] < 200 || $r['status'] >= 300) {
            error_log("[leitura de placa] HTTP {$r['status']}: " . substr($r['corpo'], 0, 300));

            return $this->falha('INDISPONIVEL', 'O serviço de leitura de placa não respondeu. Tente novamente ou digite a placa.');
        }

        $dados = json_decode($r['corpo'], true);
        $resultado = is_array($dados) ? ($dados['results'][0] ?? null) : null;

        if (!is_array($resultado) || empty($resultado['plate'])) {
            return $this->falha('SEM_PLACA', 'Nenhuma placa foi encontrada na foto. Aproxime-se, evite reflexo e tente de novo, ou digite a placa.');
        }

        $placa = self::normalizar((string) $resultado['plate']);
        $confianca = isset($resultado['score']) && is_numeric($resultado['score']) ? round((float) $resultado['score'], 4) : null;
        $formato = self::formato($placa);

        $candidatos = [];
        foreach ((array) ($resultado['candidates'] ?? []) as $c) {
            $p = self::normalizar((string) ($c['plate'] ?? ''));

            if ($p === '' || $p === $placa || isset($candidatos[$p]) || self::formato($p) === null) {
                continue;
            }

            $candidatos[$p] = ['placa' => $p, 'confianca' => isset($c['score']) && is_numeric($c['score']) ? round((float) $c['score'], 4) : null, 'formato' => self::formato($p)];
        }

        return [
            'ok' => true,
            'codigo' => 'OK',
            'erro' => $formato === null ? "O texto lido (\"{$placa}\") não tem formato de placa brasileira. Confira com cuidado." : null,
            'placa' => $placa,
            'formato' => $formato,
            'confianca' => $confianca,
            // Sem nota do serviço não há como afirmar confiança: trata como incerta.
            'baixa_confianca' => $confianca === null || $confianca < self::CONFIANCA_ALTA || $formato === null,
            'candidatos' => array_slice(array_values($candidatos), 0, 3),
            'requer_conferencia' => true,
        ];
    }

    private function falha(string $codigo, string $mensagem): array
    {
        return ['ok' => false, 'codigo' => $codigo, 'erro' => $mensagem, 'placa' => null, 'formato' => null, 'confianca' => null,
                'baixa_confianca' => true, 'candidatos' => [], 'requer_conferencia' => true];
    }
}
