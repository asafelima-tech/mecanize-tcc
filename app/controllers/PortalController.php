<?php

require_once __DIR__ . '/../core/Erro.php';
require_once __DIR__ . '/../core/Integracoes.php';
require_once __DIR__ . '/../core/ProvedorPagamento.php';
require_once __DIR__ . '/../models/PortalToken.php';
require_once __DIR__ . '/../models/PortalCaso.php';
require_once __DIR__ . '/../models/CobrancaOnline.php';

/**
 * Portal do cliente (sem login): acompanhar o serviço, aprovar/recusar orçamento, autorizar valor
 * adicional, pagar online e escolher como quer ser avisado.
 *
 * Todas as rotas daqui são PUBLICO (config/routes.php): quem entra é o cliente, por um link com
 * token de 256 bits. A proteção está nas regras, e não no endereço:
 *  - o token só existe na requisição (no banco fica o hash), expira e pode ser revogado;
 *  - link inválido/expirado/revogado dão a MESMA tela genérica;
 *  - toda ação confere de novo no servidor o que o token alcança (PortalCaso::alcance);
 *  - todo POST passa pelo CSRF global (a página do portal abre uma sessão anônima só para isso);
 *  - cada decisão grava IP e navegador (decisoes_portal) e entra na trilha de auditoria.
 */
class PortalController extends Controller
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = new Database();
    }

    /** O portal não pode ser guardado em cache nem indexado: a URL é, na prática, uma senha. */
    private function cabecalhosSeguros(): void
    {
        header('Cache-Control: no-store, max-age=0');
        header('X-Robots-Tag: noindex, nofollow');
        header('Referrer-Policy: no-referrer');
    }

    /** @return array<string,mixed> o token (com `_bruto`) ou encerra com a tela genérica */
    private function token(bool $post, bool $contarAcesso = false): array
    {
        $this->cabecalhosSeguros();

        $bruto = trim((string) ($post ? ($_POST['t'] ?? '') : ($_GET['t'] ?? '')));
        $token = $bruto !== '' ? PortalToken::resolver($this->pdo, $bruto, $contarAcesso) : null;

        if ($token === null) {
            Erro::exibir('portal-invalido');
        }

        $token['_bruto'] = $bruto;

        return $token;
    }

    private function exigirPost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit;
        }
    }

    private function voltar(array $token, string $tipo, string $texto): void
    {
        $_SESSION['portal_flash'] = ['tipo' => $tipo, 'texto' => $texto];
        header('Location: ./portal?t=' . rawurlencode($token['_bruto']));
        exit;
    }

    private function ip(): ?string
    {
        // Só REMOTE_ADDR: X-Forwarded-For é escrito por quem faz a requisição e não prova nada.
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    private function navegador(): ?string
    {
        return isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 200) : null;
    }

    /* ── Página ──────────────────────────────────────────────────────────── */

    public function ver()
    {
        $token = $this->token(false, true);
        $visao = (new PortalCaso($this->pdo))->visao($token);

        if ($visao === null) {
            Erro::exibir('portal-invalido');
        }

        $flash = $_SESSION['portal_flash'] ?? null;
        unset($_SESSION['portal_flash']);

        // De volta do provedor: a tela NÃO afirma pagamento — mostra o que o sistema já registrou.
        if (($_GET['pg'] ?? '') === 'retorno' && !$flash) {
            $flash = ['tipo' => 'info', 'texto' => 'Obrigado! A confirmação do pagamento chega do provedor em instantes. Esta página mostra o que já foi registrado; atualize para ver a confirmação.'];
        }

        $this->view('Portal/caso', [
            'v'          => $visao,
            'tokenBruto' => $token['_bruto'],
            'flash'      => $flash,
            'dias'       => max(0, (int) ceil((strtotime($token['expira_em']) - time()) / 86400)),
        ]);
    }

    /* ── Ações ───────────────────────────────────────────────────────────── */

    public function decidirOrcamento()
    {
        $this->exigirPost();
        $token = $this->token(true);

        $decisao = (string) ($_POST['decisao'] ?? '');

        if (!in_array($decisao, ['aprovar', 'recusar'], true)) {
            $this->voltar($token, 'erro', 'Escolha aprovar ou recusar.');
        }

        if ($decisao === 'aprovar' && empty($_POST['aceite'])) {
            $this->voltar($token, 'erro', 'Para aprovar, marque a confirmação de que leu os itens e o valor.');
        }

        $r = (new PortalCaso($this->pdo))->decidirOrcamento(
            $token, (int) ($_POST['id_orcamento'] ?? 0), $decisao === 'aprovar', $_POST['motivo'] ?? null, $this->ip(), $this->navegador()
        );

        if (!$r['ok']) {
            $this->voltar($token, 'erro', (string) $r['erro']);
        }

        $this->voltar($token, 'ok', $r['repetido']
            ? 'Sua resposta já estava registrada. Obrigado!'
            : ($decisao === 'aprovar' ? 'Orçamento aprovado! A oficina já foi avisada e vai dar andamento ao serviço.' : 'Orçamento recusado. A oficina foi avisada e entrará em contato, se precisar.'));
    }

    public function decidirAutorizacao()
    {
        $this->exigirPost();
        $token = $this->token(true);

        $decisao = (string) ($_POST['decisao'] ?? '');

        if (!in_array($decisao, ['autorizar', 'recusar'], true)) {
            $this->voltar($token, 'erro', 'Escolha autorizar ou não autorizar.');
        }

        $r = (new PortalCaso($this->pdo))->decidirAutorizacao(
            $token, (int) ($_POST['id_autorizacao'] ?? 0), $decisao === 'autorizar', $this->ip(), $this->navegador()
        );

        if (!$r['ok']) {
            $this->voltar($token, 'erro', (string) $r['erro']);
        }

        $this->voltar($token, 'ok', $r['repetido']
            ? 'Sua resposta já estava registrada. Obrigado!'
            : ($decisao === 'autorizar' ? 'Valor adicional autorizado. A equipe já foi avisada.' : 'Valor adicional não autorizado. A equipe foi avisada.'));
    }

    public function pagar()
    {
        $this->exigirPost();
        $token = $this->token(true);

        $r = (new PortalCaso($this->pdo))->iniciarPagamento(
            $token, (int) ($_POST['id_os'] ?? 0), array_map('intval', (array) ($_POST['parcelas'] ?? []))
        );

        // O endereço de pagamento vem do provedor (HTTPS) ou, no modo de teste, do próprio sistema.
        if ($r['ok'] && $r['url'] && (str_starts_with($r['url'], 'https://') || str_starts_with($r['url'], Integracoes::appUrl() . '/'))) {
            header('Location: ' . $r['url']);
            exit;
        }

        $this->voltar($token, 'erro', $r['ok'] ? 'Não foi possível abrir o pagamento agora. Tente novamente.' : (string) $r['erro']);
    }

    public function consentimento()
    {
        $this->exigirPost();
        $token = $this->token(true);

        $canal = (string) ($_POST['canal'] ?? '');
        $r = (new PortalCaso($this->pdo))->registrarConsentimento($token, $canal, ($_POST['concedido'] ?? '') === '1');

        $this->voltar($token, $r['ok'] ? 'ok' : 'erro', $r['ok'] ? 'Preferência de contato salva.' : (string) $r['erro']);
    }

    /* ── Pagamento SIMULADO (só quando habilitado de propósito) ──────────── */

    /**
     * Página de pagamento do provedor simulado. Existe para exercitar o fluxo inteiro sem conta em
     * provedor real: o "pagamento" vira um webhook ASSINADO que entra pelo MESMO caminho do real
     * (CobrancaOnline::receberWebhook). Fora do modo de teste, esta rota responde 404.
     */
    public function pagamentoSimulado()
    {
        $this->cabecalhosSeguros();

        $prov = Provedores::porNome('simulado');
        $ref = (string) ($_GET['ref'] ?? $_POST['ref'] ?? '');
        $sig = (string) ($_GET['s'] ?? $_POST['s'] ?? '');

        if (!$prov instanceof ProvedorSimulado || !$prov->configurado() || !$prov->referenciaAssinadaValida($ref, $sig)) {
            Erro::exibir('nao-encontrado', ['publico' => true]);
        }

        $cobrancas = new CobrancaOnline($this->pdo, $prov);
        $cobranca = $cobrancas->porReferencia($ref);

        if (!$cobranca || $cobranca['provedor'] !== 'SIMULADO') {
            Erro::exibir('nao-encontrado', ['publico' => true]);
        }

        $retorno = (string) ($_GET['r'] ?? $_POST['r'] ?? '');
        // Só volta para o próprio portal (sem isto o parâmetro serviria de redirecionamento aberto).
        $retornoSeguro = str_starts_with($retorno, Integracoes::appUrl() . '/portal?t=') ? $retorno : '';
        $resultado = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $acao = (string) ($_POST['acao'] ?? '');
            $forma = ($_POST['forma'] ?? '') === 'CARTAO_CREDITO' ? 'CARTAO_CREDITO' : 'PIX';

            if (!in_array($acao, ['pagar', 'recusar'], true)) {
                Erro::exibir('nao-encontrado', ['publico' => true]);
            }

            $valor = (float) $cobranca['valor'];
            $corpo = json_encode([
                'evento_id'   => 'SIM-' . bin2hex(random_bytes(8)),
                'tipo'        => $acao === 'pagar' ? 'PAGO' : 'RECUSADO',
                'referencia'  => $ref,
                'cobranca_id' => $cobranca['provedor_cobranca_id'],
                'valor'       => $valor,
                'forma'       => $forma,
                'parcelas'    => 1,
                // Taxa FICTÍCIA do ambiente de teste (cartão 3,99%); no provedor real vem dele.
                'taxa'        => $acao === 'pagar' && $forma === 'CARTAO_CREDITO' ? round($valor * 0.0399, 2) : 0,
                'detalhe'     => $acao === 'pagar' ? null : 'Recusado no ambiente de teste',
            ], JSON_UNESCAPED_UNICODE);

            $resultado = $cobrancas->receberWebhook($prov, $corpo, ['x-simulado-assinatura' => $prov->assinarCorpo($corpo)], []);

            if ($resultado['http'] === 200 && $retornoSeguro !== '') {
                header('Location: ' . $retornoSeguro);
                exit;
            }
        }

        $this->view('Portal/pagamentoSimulado', [
            'cobranca'  => $cobranca,
            'ref'       => $ref,
            'sig'       => $sig,
            'retorno'   => $retornoSeguro,
            'resultado' => $resultado,
        ]);
    }
}
