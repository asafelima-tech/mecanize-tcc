<?php

require_once __DIR__ . '/../core/Integracoes.php';
require_once __DIR__ . '/../models/Conversa.php';
require_once __DIR__ . '/../models/Comunicacao.php';
require_once __DIR__ . '/../models/Consentimento.php';

/**
 * Central de conversas: tudo o que o cliente disse e tudo o que o sistema e a equipe disseram a ele,
 * num lugar só. Técnico e atendente têm as mesmas permissões aqui (EQUIPE); a tela de Integrações,
 * que mostra o estado das credenciais, é só do administrador.
 *
 * Nada é fingido: o resultado de cada mensagem (enviada, entregue, lida, falha, sem consentimento,
 * canal não configurado) aparece na própria conversa. Responder por WhatsApp fora da janela de 24 h é
 * recusado com a explicação — a plataforma só aceita template aprovado fora dela.
 */
class ConversaController extends Controller
{
    // (as ações de consentimento e de abrir conversa nascem na ficha do cliente)

    private function usuarioId(): int
    {
        return (int) ($_SESSION['user_id_usuario'] ?? 0);
    }

    private function exigirPost(string $voltar = './listConversas'): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ' . $voltar);
            exit;
        }
    }

    private function voltar(int $id, array $r, string $codigoOk): void
    {
        header('Location: ./viewConversa?id=' . $id . '&' . (!empty($r['ok'])
            ? 'ok=' . $codigoOk
            : 'erro=fluxo&detalhe=' . urlencode((string) ($r['erro'] ?? 'Não foi possível concluir a ação.'))));
        exit;
    }

    public function list()
    {
        $model = new Conversa();
        $aba = (string) ($_GET['aba'] ?? 'abertas');
        $f = ['busca' => trim((string) ($_GET['busca'] ?? ''))];

        if (isset(Conversa::STATUS_LABEL[$aba])) {
            $f['status'] = $aba;
        } else {
            $f['grupo'] = $aba === 'todas' ? 'todas' : 'abertas';
            $aba = $f['grupo'];
        }

        $resp = (string) ($_GET['responsavel'] ?? '');
        if ($resp === 'eu') {
            $f['responsavel_id'] = $this->usuarioId();
        } elseif ($resp === 'sem') {
            $f['sem_responsavel'] = true;
        }

        if (!empty($_GET['nao_lidas'])) {
            $f['nao_lidas'] = true;
        }

        $this->view('Conversas/listConversas', [
            'conversas'   => $model->listar($f),
            'contagem'    => $model->contagemPorStatus(),
            'naoLidas'    => $model->totalNaoLidas(),
            'aba'         => $aba,
            'busca'       => $f['busca'],
            'responsavel' => $resp,
            'soNaoLidas'  => !empty($_GET['nao_lidas']),
            'canais'      => ['whatsapp' => (new WhatsAppCloud())->configurado(), 'email' => Integracoes::emailConfigurado()],
            'sucesso'     => $_GET['ok'] ?? null,
        ]);
    }

    public function visualizar()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $model = new Conversa();
        $conversa = $id ? $model->getById($id) : null;

        if (!$conversa) {
            $this->naoEncontrado('Conversa', './listConversas');
        }

        $model->marcarLida($id);

        $busca = trim((string) ($_GET['q_cliente'] ?? ''));
        $candidatos = [];

        if (!$conversa['cliente_id'] && $busca !== '') {
            $stmt = (new Database())->prepare("SELECT id_cliente, nome, telefone FROM clientes WHERE ativo = 1 AND (nome LIKE ? OR telefone LIKE ?) ORDER BY nome LIMIT 15");
            $stmt->execute(['%' . $busca . '%', '%' . $busca . '%']);
            $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $pdo = new Database();
        $consent = $conversa['cliente_id']
            ? ['whatsapp' => Consentimento::vigente($pdo, (int) $conversa['cliente_id'], 'WHATSAPP'), 'email' => Consentimento::vigente($pdo, (int) $conversa['cliente_id'], 'EMAIL')]
            : null;

        $this->view('Conversas/viewConversa', [
            'conversa'   => $conversa,
            'mensagens'  => $model->mensagens($id),
            'contexto'   => $conversa['cliente_id'] ? $model->contextoDoCliente((int) $conversa['cliente_id']) : null,
            'consent'    => $consent,
            'candidatos' => $candidatos,
            'busca'      => $busca,
            'equipe'     => $pdo->query("SELECT id_usuario, nome, tipo FROM usuarios WHERE ativo = 1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC),
            'janelaAberta' => WhatsAppCloud::dentroDaJanela($conversa['ultima_entrada_em']),
            'whatsappPronto' => (new WhatsAppCloud())->configurado(),
            'emailPronto'  => Integracoes::emailConfigurado(),
            'sucesso'    => $_GET['ok'] ?? null,
            'erro'       => ($_GET['erro'] ?? '') === 'fluxo' ? ($_GET['detalhe'] ?? 'Não foi possível concluir a ação.') : null,
        ]);
    }

    public function responder()
    {
        $this->exigirPost();
        $id = (int) ($_POST['id_conversa'] ?? 0);

        $r = (new Comunicacao())->responder($id, (string) ($_POST['texto'] ?? ''), $this->usuarioId());

        // O envio já foi tentado; o resultado verdadeiro (enviada, falha, não configurado) está na conversa.
        $this->voltar($id, $r, 'respondida');
    }

    public function registrarContato()
    {
        $this->exigirPost();
        $id = (int) ($_POST['id_conversa'] ?? 0);

        $r = (new Comunicacao())->registrarManual($id, (string) ($_POST['direcao'] ?? ''), (string) ($_POST['texto'] ?? ''), $this->usuarioId());

        $this->voltar($id, $r, 'registrado');
    }

    public function atribuir()
    {
        $this->exigirPost();
        $id = (int) ($_POST['id_conversa'] ?? 0);
        $resp = (int) ($_POST['responsavel_id'] ?? 0);

        $this->voltar($id, (new Conversa())->atribuir($id, $resp > 0 ? $resp : null), 'atribuida');
    }

    public function mudarStatus()
    {
        $this->exigirPost();
        $id = (int) ($_POST['id_conversa'] ?? 0);

        $this->voltar($id, (new Conversa())->mudarStatus($id, (string) ($_POST['status'] ?? '')), 'status');
    }

    public function vincular()
    {
        $this->exigirPost();
        $id = (int) ($_POST['id_conversa'] ?? 0);

        $v = [];
        foreach (['veiculo_id', 'ocorrencia_id', 'orcamento_id', 'ordem_servico_id'] as $campo) {
            if (array_key_exists($campo, $_POST)) {
                $v[$campo] = (int) $_POST[$campo] ?: null;
            }
        }

        $this->voltar($id, (new Conversa())->vincular($id, $v), 'vinculada');
    }

    public function associarCliente()
    {
        $this->exigirPost();
        $id = (int) ($_POST['id_conversa'] ?? 0);

        $r = (new Conversa())->associarCliente($id, (int) ($_POST['cliente_id'] ?? 0));

        $this->voltar((int) ($r['conversa_id'] ?? $id), $r, 'associada');
    }

    /** Abre (ou retoma) a conversa de um cliente cadastrado — o botão "Conversar" da ficha do cliente. */
    public function iniciar()
    {
        $this->exigirPost('./listClientes');
        $clienteId = (int) ($_POST['cliente_id'] ?? 0);

        $stmt = (new Database())->prepare('SELECT id_cliente, nome, telefone, email FROM clientes WHERE id_cliente = ? AND ativo = 1');
        $stmt->execute([$clienteId]);
        $cli = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cli) {
            $this->naoEncontrado('Cliente', './listClientes');
        }

        $pdo = new Database();
        $aberta = $pdo->prepare("SELECT id_conversa FROM conversas WHERE cliente_id = ? AND status <> 'ENCERRADA' ORDER BY ultima_mensagem_em DESC LIMIT 1");
        $aberta->execute([$clienteId]);

        if (!$id = (int) $aberta->fetchColumn()) {
            $tel = WhatsAppCloud::normalizarTelefone($cli['telefone']);
            $canal = $tel !== null ? 'WHATSAPP' : (!empty($cli['email']) ? 'EMAIL' : 'MANUAL');
            $id = Conversa::abertaOuNova($pdo, $clienteId, $canal, $canal === 'WHATSAPP' ? $tel : ($canal === 'EMAIL' ? $cli['email'] : null), $cli['nome'], 'Conversa iniciada pela equipe');
        }

        header('Location: ./viewConversa?id=' . $id);
        exit;
    }

    /** Consentimento do cliente (balcão, telefone…): vira linha nova no livro, nunca edição. */
    public function consentimento()
    {
        $this->exigirPost('./listClientes');

        $clienteId = (int) ($_POST['cliente_id'] ?? 0);
        $canal = (string) ($_POST['canal'] ?? '');
        $origem = (string) ($_POST['origem'] ?? 'BALCAO');
        $obs = trim((string) ($_POST['observacao'] ?? ''));

        $r = Consentimento::registrar(new Database(), $clienteId, $canal, ($_POST['concedido'] ?? '') === '1', $origem, $this->usuarioId(), $obs !== '' ? $obs : null);

        header('Location: ./viewCliente?id=' . $clienteId . '&' . ($r['ok']
            ? 'ok=consentimento'
            : 'erro=' . urlencode((string) $r['erro'])));
        exit;
    }
}
