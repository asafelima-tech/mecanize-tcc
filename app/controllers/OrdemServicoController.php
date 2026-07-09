<?php

class OrdemServicoController extends Controller
{
    public function listRelatorio()
    {
        $ordemServico = $this->model('OrdemServico');

        $dados = [
            'ordens' => method_exists($ordemServico, 'listRelatorio') ? $ordemServico->listRelatorio() : []
        ];

        $this->view('Ordens_Servico/relatorio', $dados);
    }

    public function list()
    {
        $ordem = $this->model('OrdemServico');

        // getResumo() já devolve 'ordens', 'ordensConcluidas' e 'ordensArquivadas'
        $dados = $ordem->getResumo($_GET);

        $this->view('Ordens_Servico/listOrdemServico', $dados);
    }

    /**
     * Quadro Kanban (Aberta / Em Andamento / Aguardando Peça / Concluída),
     * alternativa visual à listagem em tabela de ./listOrdemServico.
     */
    public function kanban()
    {
        $ordem = $this->model('OrdemServico');

        $dados = [
            'colunas' => [
                'ABERTA'          => ['label' => 'Aberta',          'ordens' => $ordem->listarAbertas($_GET)],
                'EM_ANDAMENTO'    => ['label' => 'Em Andamento',    'ordens' => $ordem->listarEmAndamento($_GET)],
                'AGUARDANDO_PECA' => ['label' => 'Aguardando Peça', 'ordens' => $ordem->listarAguardandoPeca($_GET)],
                'CONCLUIDA'       => ['label' => 'Concluída',       'ordens' => $ordem->listarConcluidas($_GET)],
            ],
        ];

        $this->view('Ordens_Servico/kanbanOrdemServico', $dados);
    }

    /**
     * Move a OS pra outra coluna do Kanban (drag-and-drop). Chamado via
     * fetch() — responde JSON, não redireciona.
     */
    public function moverStatus()
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
            exit;
        }

        $id     = (int) ($_POST['id_ordem_servico'] ?? 0);
        $status = strtoupper(trim($_POST['status'] ?? ''));

        $ordemServico = $this->model('OrdemServico');

        if (!$id || !in_array($status, OrdemServico::STATUS_KANBAN, true)) {
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos.']);
            exit;
        }

        $sucesso = $ordemServico->atualizarStatus($id, $status);

        if ($sucesso && $status === 'CONCLUIDA') {
            $this->notificarClienteVeiculoPronto($ordemServico, $id);
        }

        echo json_encode(['sucesso' => $sucesso]);
        exit;
    }

    public function visualizar()
    {
        $id = $_GET['id'] ?? null;

        if (!$id) {
            header('Location: ./listOrdemServico');
            exit;
        }

        $ordemServico = $this->model('OrdemServico');
        $ordem        = $ordemServico->getById($id);

        if (!$ordem) {
            header('Location: ./listOrdemServico');
            exit;
        }

        $tokenRastreio = $ordemServico->getOuCriarToken((int) $id);

        $dados = [
            'ordem_servico'   => $ordem,
            'itens_os'        => $ordemServico->getItensByOS($id),
            'mao_obra_os'     => $ordemServico->getMaoObraByOS($id),
            'linkRastreio'    => $this->urlAbsoluta('rastrear?token=' . $tokenRastreio),
        ];

        $this->view('Ordens_Servico/viewOrdemServico', $dados);
    }

    /**
     * Gera e baixa a Ordem de Serviço em PDF de verdade (via Dompdf).
     */
    public function exportarPdf()
    {
        $id = $_GET['id'] ?? null;

        if (!$id) {
            header('Location: ./listOrdemServico');
            exit;
        }

        $ordemServico = $this->model('OrdemServico');
        $os           = $ordemServico->getById($id);

        if (!$os) {
            header('Location: ./listOrdemServico');
            exit;
        }

        $itens = $ordemServico->getItensByOS($id);

        require_once __DIR__ . '/../core/Pdf.php';

        ob_start();
        require __DIR__ . '/../views/Ordens_Servico/pdfOrdemServico.php';
        $html = ob_get_clean();

        $numero = preg_replace('/[^A-Za-z0-9_-]/', '_', $os['numero_os'] ?? (string) $os['id_ordem_servico']);

        Pdf::baixar($html, "os_{$numero}.pdf", empty($_GET['inline']));
    }

    public function save()
    {
        $ordemServico = $this->model('OrdemServico');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $sucesso = $ordemServico->create($_POST);
            } catch (\Throwable $e) {
                $sucesso = false;
                $erroCreate = $e->getMessage();
            }

            if ($sucesso) {
                header('Location: ./listOrdemServico');
                exit;
            }

            $dados          = $this->dadosFormularioOS($ordemServico);
            $dados['error'] = !empty($erroCreate)
                ? 'Erro ao cadastrar ordem de serviço: ' . $erroCreate
                : 'Erro ao cadastrar ordem de serviço.';
            $dados['old']   = $_POST;

            $this->view('Ordens_Servico/saveOrdemServico', $dados);
            return;
        }

        $dados = $this->dadosFormularioOS($ordemServico);
        $this->view('Ordens_Servico/saveOrdemServico', $dados);
    }

   
    public function edit()
{
    $ordemServico = $this->model('OrdemServico');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id_ordem_servico'] ?? null;

        if (!$id) {
            header('Location: ./listOrdemServico');
            exit;
        }

        $sucesso = $ordemServico->update($_POST);

        if ($sucesso) {
            header('Location: ./listOrdemServico');
            exit;
        }

        $erroDetalhado = method_exists($ordemServico, 'getLastError')
            ? $ordemServico->getLastError()
            : '';

        $dados = $this->dadosFormularioOS($ordemServico);

        $dados['ordem_servico'] = array_merge(
            $ordemServico->getById($id) ?: [],
            $_POST
        );

        $dados['itens_os'] = $ordemServico->getItensByOS($id);
        $dados['mao_obra_os'] = $ordemServico->getMaoObraByOS($id);

        $dados['error'] = !empty($erroDetalhado)
            ? 'Erro ao salvar alterações: ' . $erroDetalhado
            : 'Erro ao salvar alterações.';

        $dados['old'] = $_POST;

        $this->view('Ordens_Servico/editOrdemServico', $dados);
        return;
    }

    $id = $_GET['id'] ?? null;

    if (!$id) {
        header('Location: ./listOrdemServico');
        exit;
    }

    $ordem = $ordemServico->getById($id);

    if (!$ordem) {
        header('Location: ./listOrdemServico');
        exit;
    }

    $dados = $this->dadosFormularioOS($ordemServico);

    $dados['ordem_servico'] = $ordem;
    $dados['itens_os']      = $ordemServico->getItensByOS($id);
    $dados['mao_obra_os']   = $ordemServico->getMaoObraByOS($id);

    $this->view('Ordens_Servico/editOrdemServico', $dados);
}

    private function dadosFormularioOS($ordemServico)
    {
        return [
            'clientes'        => $ordemServico->getClientes(),
            'veiculos'        => $ordemServico->getVeiculos(),
            'mecanicos'       => $ordemServico->getMecanicos(),
            'produtos'        => $ordemServico->getProdutos(),
            'servicos'        => $ordemServico->getServicos(),
            'proximoNumeroOS' => $ordemServico->gerarNumeroOS(),
        ];
    }

    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ./listOrdemServico');
            exit;
        }

        $id = $_POST['id_ordem_servico'] ?? null;

        if (!$id) {
            header('Location: ./listOrdemServico');
            exit;
        }

        $ordemServico = $this->model('OrdemServico');
        $ordemServico->delete($id);

        header('Location: ./listOrdemServico');
        exit;
    }

    public function concluir()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ./listOrdemServico');
            exit;
        }

        $id = $_POST['id_ordem_servico'] ?? null;

        if (!$id) {
            header('Location: ./listOrdemServico');
            exit;
        }

        // Assinatura capturada no canvas do modal de conclusão (base64 PNG).
        $assinatura = trim($_POST['assinatura_cliente'] ?? '');
        $assinatura = ($assinatura !== '' && str_starts_with($assinatura, 'data:image/')) ? $assinatura : null;

        $ordemServico = $this->model('OrdemServico');
        $ordemServico->concluir($id, $assinatura);

        $this->notificarClienteVeiculoPronto($ordemServico, $id);

        header('Location: ./listOrdemServico');
        exit;
    }

    /**
     * Avisa o cliente por e-mail que o veículo está pronto para retirada.
     * Falha de envio não pode impedir o fluxo principal de conclusão da OS
     * (mesmo padrão usado na recuperação de senha em UserController).
     */
    private function notificarClienteVeiculoPronto($ordemServico, $id): void
    {
        try {
            $ordem = $ordemServico->getById($id);
            if (!$ordem || empty($ordem['cliente_id'])) {
                return;
            }

            $cliente = $this->model('Cliente')->findById($ordem['cliente_id']);
            if (!$cliente || empty($cliente['email'])) {
                return;
            }

            $corpo = "<p>Olá, " . htmlspecialchars($cliente['nome']) . ".</p>"
                . "<p>Seu veículo (placa " . htmlspecialchars($ordem['placa'] ?? '') . "), referente à OS nº "
                . htmlspecialchars($ordem['numero_os'] ?? '') . ", está pronto para retirada.</p>";

            require_once __DIR__ . '/../core/Mailer.php';
            (new Mailer())->enviar($cliente['email'], 'Seu veículo está pronto - Mecanize', $corpo);
        } catch (\Throwable $e) {
            error_log('[OS concluir] Falha ao enviar notificação: ' . $e->getMessage());
        }
    }
}
