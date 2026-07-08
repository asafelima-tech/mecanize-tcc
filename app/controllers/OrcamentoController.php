<?php

class OrcamentoController extends Controller
{
    public function list()
    {
        $orcamento = $this->model('Orcamento');

        $dados = $orcamento->getResumo();

        $this->view('Orcamento/listOrcamentos', $dados);
    }

    // Exibe o formulário (GET)
    public function save()
    {
        $orcamento = $this->model('Orcamento');
        $dados = $orcamento->getDadosFormulario();
        $this->view('Orcamento/saveOrcamento', $dados);
    }

    // Processa o POST e salva (chamado pelo action="./saveOrcamento")
    public function saveOrcamento()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ./listOrcamentos');
            exit;
        }

        $orcamento = $this->model('Orcamento');

        $dados = [
            'numero_orcamento'      => $_POST['numero_orcamento']      ?? '',
            'status'                => $_POST['status']                ?? 'PENDENTE',
            'data_criacao'          => $_POST['data_criacao']          ?? '',
            'cliente_id'            => $_POST['cliente_id']            ?? '',
            'veiculo_id'            => $_POST['veiculo_id']            ?? '',
            'km_veiculo'            => $_POST['km_veiculo']            ?? null,
            'mecanico_id'           => $_POST['mecanico_id']           ?? null,
            'valor_mao_obra'        => $_POST['valor_mao_obra']        ?? 0,
            'comissao_pct'          => $_POST['comissao_pct']          ?? 0,
            'comissao_base'         => $_POST['comissao_base']         ?? 'servicos',
            'valor_total_pecas'     => $_POST['valor_total_pecas']     ?? 0,
            'valor_total_servicos'  => $_POST['valor_total_servicos']  ?? 0,
            'valor_total_orcamento' => $_POST['valor_total_orcamento'] ?? 0,
            'defeito_relatado'      => $_POST['defeito_relatado']      ?? null,
            'diagnostico'           => $_POST['diagnostico']           ?? null,
            'observacoes'           => $_POST['observacoes']           ?? null,
            'data_validade'         => $_POST['data_validade']         ?? null,
            'prazo_execucao'        => $_POST['prazo_execucao']        ?? null,
            'itens'                 => $_POST['itens']                 ?? [],
        ];

        $resultado = $orcamento->salvar($dados);

        if ($resultado) {
            header('Location: ./listOrcamentos?sucesso=1');
            exit;
        }

        $dadosForm               = $orcamento->getDadosFormulario();
        $dadosForm['error']      = 'Erro ao salvar o orçamento. Tente novamente.';
        $dadosForm['proximoNumero'] = $dados['numero_orcamento'];
        $this->view('Orcamento/saveOrcamento', $dadosForm);
        exit;
    }

    public function aprovarOrcamento()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ./listOrcamentos');
            exit;
        }

        $id = $_POST['id_orcamento'] ?? null;

        if (!$id) {
            header('Location: ./listOrcamentos');
            exit;
        }

        $orcamento = $this->model('Orcamento');
        $resultado = $orcamento->aprovar($id);

        if ($resultado) {
            $this->notificarClienteAprovacao($orcamento, $id);
            header('Location: ./listOrcamentos?sucesso=aprovado');
        } else {
            header('Location: ./listOrcamentos?erro=aprovacao');
        }
        exit;
    }

    /**
     * Avisa o cliente por e-mail que o orçamento foi aprovado. Falha de envio
     * não pode impedir o fluxo principal de aprovação (mesmo padrão usado na
     * recuperação de senha em UserController).
     */
    private function notificarClienteAprovacao($orcamento, $id): void
    {
        try {
            $orc = $orcamento->getById($id);
            if (!$orc || empty($orc['cliente_id'])) {
                return;
            }

            $cliente = $this->model('Cliente')->findById($orc['cliente_id']);
            if (!$cliente || empty($cliente['email'])) {
                return;
            }

            $valor = number_format((float) ($orc['valor_total_orcamento'] ?? 0), 2, ',', '.');

            $corpo = "<p>Olá, " . htmlspecialchars($cliente['nome']) . ".</p>"
                . "<p>Seu orçamento nº " . htmlspecialchars($orc['numero']) . " foi aprovado, no valor de R$ {$valor}.</p>"
                . "<p>Em breve entraremos em contato para dar andamento ao serviço.</p>";

            require_once __DIR__ . '/../core/Mailer.php';
            (new Mailer())->enviar($cliente['email'], 'Orçamento aprovado - Mecanize', $corpo);
        } catch (\Throwable $e) {
            error_log('[Orcamento aprovar] Falha ao enviar notificação: ' . $e->getMessage());
        }
    }

    public function recusarOrcamento()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ./listOrcamentos');
            exit;
        }

        $id     = $_POST['id_orcamento']  ?? null;
        $motivo = $_POST['motivo_recusa'] ?? null;

        if (!$id) {
            header('Location: ./listOrcamentos');
            exit;
        }

        $orcamento = $this->model('Orcamento');

        if ($orcamento->recusar($id, $motivo)) {
            header('Location: ./listOrcamentos?sucesso=recusado');
        } else {
            header('Location: ./listOrcamentos?erro=recusa');
        }
        exit;
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ./listOrcamentos');
            exit;
        }

        $orcamento = $this->model('Orcamento');

        if ($orcamento->criar($_POST)) {
            header('Location: ./listOrcamentos');
            exit;
        }

        $dados = $orcamento->getDadosFormulario();
        $dados['error'] = 'Erro ao salvar o orçamento.';

        $this->view('Orcamento/saveOrcamento', $dados);
    }

    public function visualizar()
    {
        if (empty($_GET['id'])) {
            header('Location: ./listOrcamentos');
            exit;
        }

        $orcamento = $this->model('Orcamento');

        $dados = [
            'orcamento' => $orcamento->getById($_GET['id']),
            'itens'     => $orcamento->getItensByOrcamento($_GET['id'])
        ];

        if (!$dados['orcamento']) {
            header('Location: ./listOrcamentos');
            exit;
        }

        $this->view('Orcamento/viewOrcamento', $dados);
    }

    public function edit()
    {
        $orcamento = $this->model('Orcamento');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id_orcamento'] ?? null;

            if (!$id) {
                header('Location: ./listOrcamentos');
                exit;
            }

            if ($orcamento->atualizar($id, $_POST)) {
                header('Location: ./listOrcamentos');
                exit;
            }

            $dados              = $orcamento->getDadosFormulario();
            $dados['orcamento'] = array_merge($orcamento->getById($id) ?: [], $_POST);
            $dados['itens']     = $orcamento->getItensByOrcamento($id);
            $dados['error']     = 'Erro ao atualizar o orçamento.';

            $this->view('Orcamento/editOrcamento', $dados);
            return;
        }

        if (empty($_GET['id'])) {
            header('Location: ./listOrcamentos');
            exit;
        }

        $dados              = $orcamento->getDadosFormulario();
        $dados['orcamento'] = $orcamento->getById($_GET['id']);
        $dados['itens']     = $orcamento->getItensByOrcamento($_GET['id']);

        if (!$dados['orcamento']) {
            header('Location: ./listOrcamentos');
            exit;
        }

        $this->view('Orcamento/editOrcamento', $dados);
    }

    public function finalizar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ./listOrcamentos');
            exit;
        }

        $id = $_POST['os_id'] ?? null;

        if (!$id) {
            header('Location: ./listOrcamentos');
            exit;
        }

        $orcamento = $this->model('Orcamento');

        if ($orcamento->finalizar($id)) {
            header('Location: ./listOrcamentos');
            exit;
        }

        $orcamento2 = $this->model('Orcamento');
        $dados = $orcamento2->getResumo();
        $dados['error'] = 'Erro ao finalizar o orçamento.';

        $this->view('Orcamento/listOrcamentos', $dados);
    }

    /**
     * Gera e baixa o orçamento em PDF de verdade (via Dompdf), ao lado do
     * botão "Imprimir" que hoje só abre a tela pronta para Ctrl+P.
     */
    public function exportarPdf()
    {
        if (empty($_GET['id'])) {
            header('Location: ./listOrcamentos');
            exit;
        }

        $orcamento = $this->model('Orcamento');

        $orc = $orcamento->getById($_GET['id']);
        if (!$orc) {
            header('Location: ./listOrcamentos');
            exit;
        }

        $itens = $orcamento->getItensByOrcamento($_GET['id']);

        require_once __DIR__ . '/../core/Pdf.php';

        ob_start();
        require __DIR__ . '/../views/Orcamento/pdfOrcamento.php';
        $html = ob_get_clean();

        $numero = preg_replace('/[^A-Za-z0-9_-]/', '_', $orc['numero'] ?? (string) $orc['id_orcamento']);

        Pdf::baixar($html, "orcamento_{$numero}.pdf");
    }

    public function imprimir()
    {
        if (empty($_GET['id'])) {
            header('Location: ./listOrcamentos');
            exit;
        }

        $orcamento = $this->model('Orcamento');

        $orc = $orcamento->getById($_GET['id']);
        if (!$orc) {
            header('Location: ./listOrcamentos');
            exit;
        }

        $itens = $orcamento->getItensByOrcamento($_GET['id']);

        // Renderizado SEM o layout principal (sem sidebar/topbar do sistema).
        // Ajuste o caminho do require conforme a estrutura real de pastas
        // do projeto (equivalente a onde ficam as demais views de Orcamento).
        require __DIR__ . '/../views/Orcamento/printOrcamento.php';
        exit;
    }
}
