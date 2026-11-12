<?php

require_once __DIR__ . '/HttpTransporte.php';
require_once __DIR__ . '/Integracoes.php';

/**
 * Adaptador da API oficial do WhatsApp Business (Meta Cloud API).
 *
 * Isto é a integração de verdade — e só funciona com as credenciais da conta (ver Integracoes). Sem
 * elas, `configurado()` é falso e NADA é enviado: o chamador registra "não configurado". Não existe
 * botão "abrir wa.me" fingindo ser integração.
 *
 * Regras da plataforma respeitadas:
 *   - mensagem de texto livre só dentro da janela de 24 h desde a última mensagem DO CLIENTE;
 *     fora dela, só template aprovado (quem chama decide, com `dentroDaJanela()`);
 *   - o webhook é autenticado pela assinatura `X-Hub-Signature-256` (HMAC-SHA256 do corpo bruto
 *     com o App Secret); assinatura ausente ou errada = a requisição é descartada;
 *   - verificação do webhook (GET) devolve o `hub.challenge` só se o `hub.verify_token` confere.
 */
final class WhatsAppCloud
{
    public const JANELA_HORAS = 24;

    private array $cfg;
    private HttpTransporte $http;

    public function __construct(?array $cfg = null, ?HttpTransporte $http = null)
    {
        $this->cfg  = $cfg ?? Integracoes::whatsapp();
        $this->http = $http ?? new CurlTransporte();
    }

    public function configurado(): bool
    {
        return Integracoes::whatsappConfigurado($this->cfg);
    }

    /** Template aprovado configurado para o evento, ou null. */
    public function templateDoEvento(string $evento): ?string
    {
        return $this->cfg['templates'][$evento] ?? null;
    }

    /** DDI+DDD+número só com dígitos (Brasil: 10/11 dígitos ganham o 55). Null se não parece um telefone. */
    public static function normalizarTelefone(?string $telefone): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $telefone);

        if ($d === '') {
            return null;
        }

        if (in_array(strlen($d), [10, 11], true)) {
            $d = '55' . $d;
        }

        return strlen($d) >= 12 && strlen($d) <= 15 ? $d : null;
    }

    /** A última mensagem do cliente foi há menos de 24 h? Só então o texto livre é permitido. */
    public static function dentroDaJanela(?string $ultimaEntradaEm, ?int $agora = null): bool
    {
        if (!$ultimaEntradaEm) {
            return false;
        }

        $ts = strtotime($ultimaEntradaEm);

        return $ts !== false && (($agora ?? time()) - $ts) < self::JANELA_HORAS * 3600;
    }

    /** @return array{ok:bool, id:?string, erro:?string, retentavel:bool} */
    public function enviarTexto(string $telefone, string $texto): array
    {
        return $this->enviar(['type' => 'text', 'text' => ['preview_url' => true, 'body' => mb_substr($texto, 0, 4000)]], $telefone);
    }

    /**
     * @param string[] $parametros valores de {{1}}, {{2}}… do corpo do template
     * @return array{ok:bool, id:?string, erro:?string, retentavel:bool}
     */
    public function enviarTemplate(string $telefone, string $template, array $parametros = []): array
    {
        $payload = ['type' => 'template', 'template' => ['name' => $template, 'language' => ['code' => $this->cfg['idioma']]]];

        if ($parametros) {
            $payload['template']['components'] = [[
                'type' => 'body',
                'parameters' => array_map(fn($p) => ['type' => 'text', 'text' => mb_substr((string) $p, 0, 1000)], array_values($parametros)),
            ]];
        }

        return $this->enviar($payload, $telefone);
    }

    private function enviar(array $conteudo, string $telefone): array
    {
        if (!$this->configurado()) {
            return ['ok' => false, 'id' => null, 'erro' => 'WhatsApp não configurado.', 'retentavel' => false];
        }

        $destino = self::normalizarTelefone($telefone);

        if ($destino === null) {
            return ['ok' => false, 'id' => null, 'erro' => 'Telefone do cliente inválido para o WhatsApp.', 'retentavel' => false];
        }

        $url = 'https://graph.facebook.com/' . $this->cfg['api_version'] . '/' . rawurlencode($this->cfg['phone_number_id']) . '/messages';
        $corpo = json_encode(['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $destino] + $conteudo, JSON_UNESCAPED_UNICODE);

        $r = $this->http->requisitar('POST', $url, [
            'Authorization: Bearer ' . $this->cfg['access_token'],
            'Content-Type: application/json',
        ], $corpo, 10);

        if ($r['status'] === 0) {
            return ['ok' => false, 'id' => null, 'erro' => 'Sem resposta do WhatsApp: ' . ($r['erro'] ?? 'falha de rede'), 'retentavel' => true];
        }

        $json = json_decode($r['corpo'], true);

        if ($r['status'] >= 200 && $r['status'] < 300 && !empty($json['messages'][0]['id'])) {
            return ['ok' => true, 'id' => (string) $json['messages'][0]['id'], 'erro' => null, 'retentavel' => false];
        }

        $erro = $json['error']['message'] ?? ('Resposta inesperada do WhatsApp (HTTP ' . $r['status'] . ').');
        $codigo = $json['error']['code'] ?? null;

        // 5xx e limite de taxa se repetem; token inválido, número inexistente, fora da janela não.
        $retentavel = $r['status'] >= 500 || $r['status'] === 429 || in_array($codigo, [4, 80007, 130429], true);

        return ['ok' => false, 'id' => null, 'erro' => mb_substr($erro . ($codigo !== null ? ' (código ' . $codigo . ')' : ''), 0, 250), 'retentavel' => $retentavel];
    }

    /* ── Webhook ─────────────────────────────────────────────────────────── */

    /** Confere `X-Hub-Signature-256: sha256=<hmac>` contra o corpo BRUTO. Sem App Secret, nada passa. */
    public function validarAssinatura(string $corpoBruto, ?string $cabecalho): bool
    {
        $segredo = (string) ($this->cfg['app_secret'] ?? '');

        if ($segredo === '' || $cabecalho === null || !str_starts_with($cabecalho, 'sha256=')) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $corpoBruto, $segredo), substr($cabecalho, 7));
    }

    /** Verificação inicial do webhook (GET): devolve o desafio só se o token confere. */
    public function desafioDaVerificacao(array $query): ?string
    {
        $esperado = (string) ($this->cfg['verify_token'] ?? '');

        if ($esperado === '' || ($query['hub_mode'] ?? ($query['hub.mode'] ?? '')) !== 'subscribe') {
            return null;
        }

        $token = (string) ($query['hub_verify_token'] ?? ($query['hub.verify_token'] ?? ''));
        $desafio = (string) ($query['hub_challenge'] ?? ($query['hub.challenge'] ?? ''));

        return hash_equals($esperado, $token) && $desafio !== '' ? $desafio : null;
    }

    /**
     * Normaliza o payload do webhook.
     *
     * @return array{mensagens:array<int,array{id:string,de:string,nome:?string,tipo:string,texto:string,em:int}>,
     *               status:array<int,array{id:string,status:string,erro:?string,em:int}>}
     */
    public function interpretar(array $payload): array
    {
        $saida = ['mensagens' => [], 'status' => []];

        foreach ((array) ($payload['entry'] ?? []) as $entrada) {
            foreach ((array) ($entrada['changes'] ?? []) as $mudanca) {
                $valor = $mudanca['value'] ?? [];

                $nomes = [];
                foreach ((array) ($valor['contacts'] ?? []) as $c) {
                    $nomes[(string) ($c['wa_id'] ?? '')] = $c['profile']['name'] ?? null;
                }

                foreach ((array) ($valor['messages'] ?? []) as $m) {
                    if (empty($m['id']) || empty($m['from'])) {
                        continue;
                    }

                    $tipo = (string) ($m['type'] ?? 'text');
                    $texto = match ($tipo) {
                        'text'        => (string) ($m['text']['body'] ?? ''),
                        'button'      => (string) ($m['button']['text'] ?? ''),
                        'interactive' => (string) ($m['interactive']['button_reply']['title'] ?? $m['interactive']['list_reply']['title'] ?? ''),
                        default       => '[' . $tipo . ' — o conteúdo deste tipo de mensagem não é exibido aqui; veja no aplicativo do WhatsApp]',
                    };

                    $saida['mensagens'][] = [
                        'id'    => (string) $m['id'],
                        'de'    => preg_replace('/\D+/', '', (string) $m['from']),
                        'nome'  => $nomes[(string) $m['from']] ?? null,
                        'tipo'  => $tipo,
                        'texto' => mb_substr($texto, 0, 4000),
                        'em'    => (int) ($m['timestamp'] ?? time()),
                    ];
                }

                foreach ((array) ($valor['statuses'] ?? []) as $s) {
                    if (empty($s['id']) || empty($s['status'])) {
                        continue;
                    }

                    $erro = null;
                    if (!empty($s['errors'][0])) {
                        $erro = trim(($s['errors'][0]['title'] ?? '') . ' ' . ($s['errors'][0]['message'] ?? ($s['errors'][0]['details'] ?? '')));
                    }

                    $saida['status'][] = ['id' => (string) $s['id'], 'status' => (string) $s['status'], 'erro' => $erro ?: null, 'em' => (int) ($s['timestamp'] ?? time())];
                }
            }
        }

        return $saida;
    }
}
