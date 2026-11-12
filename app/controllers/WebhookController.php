<?php

require_once __DIR__ . '/../core/Integracoes.php';
require_once __DIR__ . '/../core/ProvedorPagamento.php';
require_once __DIR__ . '/../core/WhatsAppCloud.php';
require_once __DIR__ . '/../models/Comunicacao.php';
require_once __DIR__ . '/../models/CobrancaOnline.php';

/**
 * Entrada dos webhooks (WhatsApp e provedor de pagamento).
 *
 * Quem chama é um SERVIDOR externo, sem sessão nem CSRF — por isso estas rotas são PUBLICO e
 * marcadas `csrf => false` em config/routes.php. A autenticação é a ASSINATURA do corpo bruto
 * (HMAC com o segredo combinado com o provedor): assinatura ausente/errada = nada é lido nem gravado.
 * A resposta é sempre curta e sem detalhes internos.
 */
class WebhookController extends Controller
{
    private function responder(int $http, string $texto): void
    {
        http_response_code($http);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo $texto;
        exit;
    }

    /** Cabeçalhos da requisição, com o nome em minúsculas ("x-hub-signature-256"). */
    private function cabecalhos(): array
    {
        $saida = [];

        foreach ($_SERVER as $chave => $valor) {
            if (str_starts_with($chave, 'HTTP_') && is_string($valor)) {
                $saida[strtolower(str_replace('_', '-', substr($chave, 5)))] = $valor;
            }
        }

        return $saida;
    }

    /** WhatsApp Cloud API: GET = verificação da URL; POST = mensagens recebidas e recibos de entrega. */
    public function whatsapp()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $desafio = (new WhatsAppCloud())->desafioDaVerificacao($_GET);

            $desafio !== null ? $this->responder(200, $desafio) : $this->responder(403, 'forbidden');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->responder(405, 'method not allowed');
        }

        $r = (new Comunicacao())->receberWebhookWhatsApp((string) file_get_contents('php://input'), $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? null);

        $this->responder($r['http'], $r['motivo']);
    }

    /** Provedor de pagamento: confirmação, recusa, estorno. */
    public function pagamento()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->responder(405, 'method not allowed');
        }

        $nome = (string) ($_GET['p'] ?? '');
        $prov = $nome !== '' ? Provedores::porNome($nome) : Provedores::atual();

        if ($prov === null) {
            $this->responder(404, 'provider not configured');
        }

        $r = (new CobrancaOnline(null, $prov))->receberWebhook($prov, (string) file_get_contents('php://input'), $this->cabecalhos(), $_GET);

        $this->responder($r['http'], $r['motivo']);
    }
}
