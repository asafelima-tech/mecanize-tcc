<?php

require_once __DIR__ . '/HttpTransporte.php';
require_once __DIR__ . '/Integracoes.php';

/**
 * Provedor de pagamento online.
 *
 * O Mecanize NÃO processa cartão: o cliente paga na página do provedor e o provedor avisa por
 * webhook assinado. O sistema guarda só a referência da cobrança, o valor e o resultado — nunca
 * número, validade ou código de cartão.
 *
 * Eventos normalizados que a interface devolve (`tipo`):
 *   PAGO · PENDENTE · RECUSADO · CANCELADO · EXPIRADO · ESTORNADO
 */
interface ProvedorPagamento
{
    public function nome(): string;

    public function configurado(): bool;

    /**
     * @param array{referencia:string, valor:float, descricao:string, max_parcelas:int, cliente_nome?:string, url_retorno:string, url_notificacao:string} $dados
     * @return array{ok:bool, id:?string, url:?string, erro:?string}
     */
    public function criarCobranca(array $dados): array;

    /** A notificação veio mesmo do provedor? (assinatura) */
    public function validarWebhook(string $corpoBruto, array $cabecalhos, array $query): bool;

    /**
     * @return array<int,array{evento_id:string, referencia:?string, provedor_cobranca_id:?string, tipo:string, valor:?float, forma:?string, parcelas:?int, taxa:float, detalhe:?string}>
     */
    public function interpretarWebhook(string $corpoBruto, array $cabecalhos, array $query): array;
}

/**
 * Provedor SIMULADO — só para teste e demonstração. Não move dinheiro nenhum.
 *
 * Só funciona se PAGAMENTOS_PERMITIR_SIMULADO=true (ou APP_DEBUG=true); a página de pagamento
 * mostra o aviso "ambiente de teste". Passa pelo MESMO caminho do provedor real: a cobrança é
 * criada, a "confirmação" chega como webhook ASSINADO (HMAC-SHA256 com o segredo) e é processada
 * por CobrancaOnline::processarWebhook — a prova de que o fluxo e as proteções funcionam.
 */
final class ProvedorSimulado implements ProvedorPagamento
{
    private array $cfg;

    public function __construct(?array $cfg = null)
    {
        $this->cfg = $cfg ?? Integracoes::pagamentos();
    }

    public function nome(): string
    {
        return 'SIMULADO';
    }

    public function configurado(): bool
    {
        return Integracoes::pagamentosConfigurado($this->cfg) && $this->cfg['provedor'] === 'simulado';
    }

    public function criarCobranca(array $dados): array
    {
        if (!$this->configurado()) {
            return ['ok' => false, 'id' => null, 'url' => null, 'erro' => 'Provedor simulado não está habilitado.'];
        }

        return [
            'ok' => true,
            'id' => 'SIM-' . bin2hex(random_bytes(6)),
            'url' => Integracoes::appUrl() . '/portalPagamentoSimulado?ref=' . rawurlencode($dados['referencia']) . '&s=' . $this->assinarReferencia($dados['referencia'])
                // Para onde voltar depois (o controller só segue se for um endereço do próprio portal).
                . (!empty($dados['url_retorno']) ? '&r=' . rawurlencode($dados['url_retorno']) : ''),
            'erro' => null,
        ];
    }

    public function assinarReferencia(string $referencia): string
    {
        return hash_hmac('sha256', 'checkout|' . $referencia, (string) $this->cfg['simulado_segredo']);
    }

    public function referenciaAssinadaValida(string $referencia, string $assinatura): bool
    {
        return (string) $this->cfg['simulado_segredo'] !== '' && hash_equals($this->assinarReferencia($referencia), $assinatura);
    }

    /** Assinatura que o "provedor" põe no cabeçalho X-Simulado-Assinatura. */
    public function assinarCorpo(string $corpo): string
    {
        return 'sha256=' . hash_hmac('sha256', $corpo, (string) $this->cfg['simulado_segredo']);
    }

    public function validarWebhook(string $corpoBruto, array $cabecalhos, array $query): bool
    {
        $segredo = (string) $this->cfg['simulado_segredo'];
        $recebida = $cabecalhos['x-simulado-assinatura'] ?? '';

        return $this->configurado() && $segredo !== '' && is_string($recebida) && hash_equals($this->assinarCorpo($corpoBruto), $recebida);
    }

    public function interpretarWebhook(string $corpoBruto, array $cabecalhos, array $query): array
    {
        $j = json_decode($corpoBruto, true);

        if (!is_array($j) || empty($j['evento_id']) || empty($j['tipo'])) {
            return [];
        }

        return [[
            'evento_id'            => (string) $j['evento_id'],
            'referencia'           => isset($j['referencia']) ? (string) $j['referencia'] : null,
            'provedor_cobranca_id' => isset($j['cobranca_id']) ? (string) $j['cobranca_id'] : null,
            'tipo'                 => (string) $j['tipo'],
            'valor'                => isset($j['valor']) ? (float) $j['valor'] : null,
            'forma'                => $j['forma'] ?? null,
            'parcelas'             => isset($j['parcelas']) ? (int) $j['parcelas'] : null,
            'taxa'                 => (float) ($j['taxa'] ?? 0),
            'detalhe'              => $j['detalhe'] ?? null,
        ]];
    }
}

/**
 * Mercado Pago — Checkout Pro (o cliente é levado à página do Mercado Pago para pagar).
 *
 * ATENÇÃO: implementado conforme a documentação pública (criação de preferência em
 * POST /checkout/preferences; notificação `payment` + consulta GET /v1/payments/{id}; assinatura
 * do webhook em `x-signature` com o manifest `id:<data.id>;request-id:<x-request-id>;ts:<ts>;`).
 * NÃO foi exercitado contra uma conta real — isso depende das credenciais da oficina. Os testes
 * automatizados usam um transporte falso com as respostas descritas na documentação.
 */
final class MercadoPago implements ProvedorPagamento
{
    private const API = 'https://api.mercadopago.com';
    private const TOLERANCIA_SEGUNDOS = 900;

    private array $cfg;
    private HttpTransporte $http;

    public function __construct(?array $cfg = null, ?HttpTransporte $http = null)
    {
        $this->cfg = $cfg ?? Integracoes::pagamentos();
        $this->http = $http ?? new CurlTransporte();
    }

    public function nome(): string
    {
        return 'MERCADOPAGO';
    }

    public function configurado(): bool
    {
        return $this->cfg['provedor'] === 'mercadopago' && Integracoes::pagamentosConfigurado($this->cfg);
    }

    private function cabecalhos(): array
    {
        return ['Authorization: Bearer ' . $this->cfg['mp_access_token'], 'Content-Type: application/json', 'Accept: application/json'];
    }

    public function criarCobranca(array $dados): array
    {
        if (!$this->configurado()) {
            return ['ok' => false, 'id' => null, 'url' => null, 'erro' => 'Mercado Pago não está configurado.'];
        }

        $corpo = [
            'items'              => [['title' => mb_substr($dados['descricao'], 0, 250), 'quantity' => 1, 'currency_id' => 'BRL', 'unit_price' => round($dados['valor'], 2)]],
            'external_reference' => $dados['referencia'],
            'notification_url'   => $dados['url_notificacao'],
            'back_urls'          => ['success' => $dados['url_retorno'], 'failure' => $dados['url_retorno'], 'pending' => $dados['url_retorno']],
            'auto_return'        => 'approved',
            'payment_methods'    => ['installments' => max(1, (int) $dados['max_parcelas'])],
            'statement_descriptor' => 'MECANIZE',
        ];

        $r = $this->http->requisitar('POST', self::API . '/checkout/preferences', $this->cabecalhos(), json_encode($corpo, JSON_UNESCAPED_UNICODE), 15);
        $j = json_decode($r['corpo'], true);

        if ($r['status'] >= 200 && $r['status'] < 300 && !empty($j['id']) && !empty($j['init_point'])) {
            return ['ok' => true, 'id' => (string) $j['id'], 'url' => (string) $j['init_point'], 'erro' => null];
        }

        return ['ok' => false, 'id' => null, 'url' => null, 'erro' => $r['status'] === 0
            ? 'Sem resposta do Mercado Pago: ' . ($r['erro'] ?? 'falha de rede')
            : 'O Mercado Pago recusou a criação da cobrança (HTTP ' . $r['status'] . ').'];
    }

    /** `data.id` da notificação: query (?data.id= / ?data_id=) ou corpo JSON. */
    private function idDoDado(string $corpoBruto, array $query): ?string
    {
        $id = $query['data_id'] ?? ($query['data.id'] ?? null);

        if ($id === null) {
            $j = json_decode($corpoBruto, true);
            $id = is_array($j) ? ($j['data']['id'] ?? null) : null;
        }

        return $id === null || $id === '' ? null : (string) $id;
    }

    public function validarWebhook(string $corpoBruto, array $cabecalhos, array $query): bool
    {
        $segredo = (string) $this->cfg['mp_webhook_secret'];
        $assinatura = (string) ($cabecalhos['x-signature'] ?? '');

        if ($segredo === '' || $assinatura === '') {
            return false;
        }

        $ts = $v1 = null;
        foreach (explode(',', $assinatura) as $parte) {
            [$k, $v] = array_pad(explode('=', trim($parte), 2), 2, null);
            if ($k === 'ts') {
                $ts = $v;
            } elseif ($k === 'v1') {
                $v1 = $v;
            }
        }

        if ($ts === null || $v1 === null || !ctype_digit($ts)) {
            return false;
        }

        // Replay: o carimbo (em milissegundos) precisa ser recente.
        if (abs(time() - intdiv((int) $ts, 1000)) > self::TOLERANCIA_SEGUNDOS) {
            return false;
        }

        $id = $this->idDoDado($corpoBruto, $query);
        $requestId = (string) ($cabecalhos['x-request-id'] ?? '');

        $manifest = ($id !== null ? 'id:' . strtolower($id) . ';' : '') . ($requestId !== '' ? 'request-id:' . $requestId . ';' : '') . 'ts:' . $ts . ';';

        return hash_equals(hash_hmac('sha256', $manifest, $segredo), $v1);
    }

    public function interpretarWebhook(string $corpoBruto, array $cabecalhos, array $query): array
    {
        $j = json_decode($corpoBruto, true);
        $tipo = (string) ($query['type'] ?? ($query['topic'] ?? (is_array($j) ? ($j['type'] ?? '') : '')));
        $id = $this->idDoDado($corpoBruto, $query);

        if ($tipo !== 'payment' || $id === null || !ctype_digit($id)) {
            return []; // outros tópicos (merchant_order etc.) não interessam
        }

        // A notificação traz só o id: o estado verdadeiro vem da consulta autenticada à API.
        $r = $this->http->requisitar('GET', self::API . '/v1/payments/' . $id, $this->cabecalhos(), null, 15);
        $p = json_decode($r['corpo'], true);

        if ($r['status'] !== 200 || !is_array($p) || empty($p['status'])) {
            throw new RuntimeException('Não foi possível consultar o pagamento no Mercado Pago (HTTP ' . $r['status'] . ').');
        }

        $status = (string) $p['status'];
        $detalhe = (string) ($p['status_detail'] ?? '');
        $tipoNormal = match ($status) {
            'approved'                                 => 'PAGO',
            'pending', 'in_process', 'authorized', 'in_mediation' => 'PENDENTE',
            'rejected'                                 => 'RECUSADO',
            'cancelled'                                => str_contains($detalhe, 'expired') ? 'EXPIRADO' : 'CANCELADO',
            'refunded', 'charged_back'                 => 'ESTORNADO',
            default                                    => 'PENDENTE',
        };

        $taxa = 0.0;
        foreach ((array) ($p['fee_details'] ?? []) as $f) {
            $taxa += (float) ($f['amount'] ?? 0);
        }

        $forma = match ($p['payment_type_id'] ?? '') {
            'credit_card' => 'CARTAO_CREDITO', 'debit_card' => 'CARTAO_DEBITO', 'bank_transfer' => 'PIX', 'ticket' => 'BOLETO', default => null,
        };

        return [[
            'evento_id'            => 'pay-' . $id . '-' . $status . '-' . ($p['date_last_updated'] ?? ''),
            'referencia'           => isset($p['external_reference']) ? (string) $p['external_reference'] : null,
            'provedor_cobranca_id' => null,
            'tipo'                 => $tipoNormal,
            'valor'                => isset($p['transaction_amount']) ? (float) $p['transaction_amount'] : null,
            'forma'                => $forma,
            'parcelas'             => isset($p['installments']) ? (int) $p['installments'] : null,
            'taxa'                 => round($taxa, 2),
            'detalhe'              => $detalhe !== '' ? $detalhe : null,
        ]];
    }
}

/** Escolhe o provedor pela configuração. */
final class Provedores
{
    public static function atual(?array $cfg = null, ?HttpTransporte $http = null): ?ProvedorPagamento
    {
        $cfg = $cfg ?? Integracoes::pagamentos();

        $p = match ($cfg['provedor']) {
            'simulado'    => new ProvedorSimulado($cfg),
            'mercadopago' => new MercadoPago($cfg, $http),
            default       => null,
        };

        return $p && $p->configurado() ? $p : null;
    }

    public static function porNome(string $nome, ?array $cfg = null, ?HttpTransporte $http = null): ?ProvedorPagamento
    {
        $cfg = $cfg ?? Integracoes::pagamentos();

        $p = match (strtoupper($nome)) {
            'SIMULADO'    => new ProvedorSimulado($cfg),
            'MERCADOPAGO' => new MercadoPago($cfg, $http),
            default       => null,
        };

        return $p && $p->configurado() ? $p : null;
    }
}
