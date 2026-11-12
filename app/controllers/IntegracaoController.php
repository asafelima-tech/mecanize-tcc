<?php

require_once __DIR__ . '/../core/Integracoes.php';
require_once __DIR__ . '/../core/WhatsAppCloud.php';
require_once __DIR__ . '/../models/Comunicacao.php';
require_once __DIR__ . '/../models/MensagensCliente.php';

/**
 * Estado das integrações externas (WhatsApp, e-mail, pagamento online) — só o administrador.
 *
 * A tela diz a verdade: o que está pronto, o que falta (só o NOME da variável, nunca o valor), as
 * URLs que o provedor precisa chamar e o que aconteceu com as mensagens. Nada aqui configura nem
 * "conecta" nada por conta própria: as credenciais vêm do .env, entregues pela Meta/provedor.
 */
class IntegracaoController extends Controller
{
    public function index()
    {
        $pdo = new Database();
        $wa = new WhatsAppCloud();
        $base = Integracoes::appUrl();

        $templates = [];
        foreach (MensagensCliente::EVENTOS as $evento => $rotulo) {
            $templates[$evento] = ['rotulo' => $rotulo, 'configurado' => $wa->templateDoEvento($evento) !== null, 'variavel' => 'WHATSAPP_TEMPLATE_' . $evento];
        }

        $situacoes = $pdo->query("SELECT status, COUNT(*) FROM mensagens WHERE direcao = 'SAIDA' AND criada_em >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->view('Integracoes/integracoes', [
            'resumo'     => Integracoes::resumo(),
            'urls'       => ['whatsapp' => $base . '/webhookWhatsApp', 'pagamento' => $base . '/webhookPagamento'],
            'https'      => str_starts_with($base, 'https://'),
            'templates'  => $templates,
            'situacoes'  => array_map('intval', $situacoes),
            'pendentes'  => (new Comunicacao($pdo))->haPendentes(),
            'validade'   => Integracoes::validadePortalDias(),
            'simulado'   => Integracoes::pagamentos()['provedor'] === 'simulado',
            'resultado'  => $_SESSION['despacho_resultado'] ?? null,
        ]);

        unset($_SESSION['despacho_resultado']);
    }

    /** Envia agora o que está na fila (o mesmo que o agendador faz). */
    public function despachar()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ./integracoes');
            exit;
        }

        $_SESSION['despacho_resultado'] = (new Comunicacao())->despacharPendentes(30);
        header('Location: ./integracoes');
        exit;
    }
}
