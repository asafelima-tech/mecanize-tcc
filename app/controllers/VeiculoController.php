<?php

class VeiculoController extends Controller
{
    public function list()
    {
        $veiculo = $this->model('Veiculo');
        $dados = $veiculo->getResumo($_GET);

        $this->view('Veiculos/listVeiculos', $dados);
    }

    public function save()
    {
        $veiculo = $this->model('Veiculo');
        $cliente = $this->model('Cliente');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $sucesso = $veiculo->create($_POST);

            if ($sucesso) {
                header('Location: ./listVeiculos');
                exit;
            }

            $erroDetalhado = method_exists($veiculo, 'getLastError')
                ? $veiculo->getLastError()
                : '';

            $dados = [
                'clientes' => $cliente->listarAtivos(),
                'marcas'   => $veiculo->listarMarcas(),
                'modelos'  => $veiculo->listarModelos(),
                'error'    => !empty($erroDetalhado)
                    ? 'Erro ao cadastrar veículo: ' . $erroDetalhado
                    : 'Erro ao cadastrar veículo.',
                'old'      => $_POST
            ];

            $this->view('Veiculos/saveVeiculo', $dados);
            return;
        }

        $dados = [
            'clientes' => $cliente->listarAtivos(),
            'marcas'   => $veiculo->listarMarcas(),
            'modelos'  => $veiculo->listarModelos(),
        ];

        $this->view('Veiculos/saveVeiculo', $dados);
    }

    public function novosMesList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $veiculo = $this->model('Veiculo');

        echo json_encode([
            'sucesso'  => true,
            'veiculos' => $veiculo->listarNovosEsteMes()
        ]);
        exit;
    }

    public function perfilJson()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'erro' => 'ID não informado.']);
            exit;
        }

        $veiculo = $this->model('Veiculo');
        $dados   = $veiculo->getById((int) $_GET['id']);

        if (!$dados) {
            http_response_code(404);
            echo json_encode(['sucesso' => false, 'erro' => 'Veículo não encontrado.']);
            exit;
        }

        echo json_encode(['sucesso' => true, 'veiculo' => $dados]);
        exit;
    }

    public function visualizar()
    {
        if (empty($_GET['id'])) {
            header('Location: ./listVeiculos');
            exit;
        }

        $veiculo = $this->model('Veiculo');

        $dadosVeiculo = $veiculo->getById($_GET['id']);

        if (!$dadosVeiculo) {
            header('Location: ./listVeiculos');
            exit;
        }

        $dados = [
            'dados_veiculo' => $dadosVeiculo,
            'timeline'      => $this->montarTimeline((int) $_GET['id'], $dadosVeiculo),
        ];

        $this->view('Veiculos/viewVeiculo', $dados);
    }

    /**
     * Junta orçamentos e OS's do veículo numa única linha do tempo, mais
     * recente primeiro, encerrando com o cadastro do veículo.
     */
    private function montarTimeline(int $veiculoId, array $dadosVeiculo): array
    {
        $eventos = [];

        foreach ($this->model('Orcamento')->listarPorVeiculo($veiculoId) as $orc) {
            $eventos[] = [
                'data'  => $orc['data_criacao'],
                'icone' => 'bi-file-earmark-text',
                'texto' => 'Orçamento #' . ($orc['numero'] ?? $orc['id_orcamento']) . ' — ' . ucfirst($orc['status'] ?? ''),
                'valor' => (float) ($orc['valor_total_orcamento'] ?? 0),
                'link'  => './viewOrcamento?id=' . (int) $orc['id_orcamento'],
            ];
        }

        foreach ($this->model('OrdemServico')->listarPorVeiculo($veiculoId) as $os) {
            $dataRef = $os['data_conclusao'] ?: $os['data_abertura'];
            $statusLabel = match ($os['status'] ?? '') {
                'CONCLUIDA' => 'Concluída',
                'CANCELADA' => 'Cancelada',
                default     => 'Aberta',
            };
            $eventos[] = [
                'data'  => $dataRef,
                'icone' => 'bi-wrench-adjustable',
                'texto' => 'OS #' . ($os['numero'] ?? $os['id_ordem_servico']) . ' — ' . $statusLabel,
                'valor' => (float) ($os['valor_total_os'] ?? 0),
                'link'  => './viewOrdemServico?id=' . (int) $os['id_ordem_servico'],
            ];
        }

        usort($eventos, fn($a, $b) => strtotime($b['data'] ?? 'now') <=> strtotime($a['data'] ?? 'now'));

        if (!empty($dadosVeiculo['criado_em'])) {
            $eventos[] = [
                'data'  => $dadosVeiculo['criado_em'],
                'icone' => 'bi-car-front',
                'texto' => 'Veículo cadastrado',
                'valor' => null,
                'link'  => null,
            ];
        }

        return $eventos;
    }

    public function edit()
    {
        $veiculo = $this->model('Veiculo');
        $cliente = $this->model('Cliente');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id_veiculo'] ?? null;

            if (!$id) {
                header('Location: ./listVeiculos');
                exit;
            }

            $sucesso = $veiculo->update($id, $_POST);

            if ($sucesso) {
                header('Location: ./listVeiculos');
                exit;
            }

            $erroDetalhado = method_exists($veiculo, 'getLastError')
                ? $veiculo->getLastError()
                : '';

            $dados = [
                'veiculo'  => array_merge(
                    $veiculo->getById($id) ?: [],
                    $_POST
                ),
                'clientes' => $cliente->listarAtivos(),
                'marcas'   => $veiculo->listarMarcas(),
                'modelos'  => $veiculo->listarModelos(),
                'error'    => !empty($erroDetalhado)
                    ? 'Erro ao atualizar veículo: ' . $erroDetalhado
                    : 'Erro ao atualizar veículo.',
                'old'      => $_POST
            ];

            $this->view('Veiculos/editVeiculo', $dados);
            return;
        }

        $id = $_GET['id'] ?? null;

        if (!$id) {
            header('Location: ./listVeiculos');
            exit;
        }

        $dados = [
            'veiculo'  => $veiculo->getById($id),
            'clientes' => $cliente->listarAtivos(),
            'marcas'   => $veiculo->listarMarcas(),
            'modelos'  => $veiculo->listarModelos(),
        ];

        if (!$dados['veiculo']) {
            header('Location: ./listVeiculos');
            exit;
        }

        $this->view('Veiculos/editVeiculo', $dados);
    }

    public function delete()
    {
        $id = $_GET['id'] ?? null;

        if (!$id) {
            header('Location: ./listVeiculos');
            exit;
        }

        $veiculo = $this->model('Veiculo');
        $veiculo->delete($id);

        header('Location: ./listVeiculos');
        exit;
    }
}
