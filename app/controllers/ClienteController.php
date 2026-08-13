<?php

class ClienteController extends Controller
{
    public function list()
    {
        $cliente = $this->model('Cliente');

        $dados        = $cliente->getResumo($this->filtrosDaTela());
        $dados['aba'] = $this->abaAtual();

        // Troca de aba / paginação / busca chegam por fetch e só precisam do
        // miolo da tela. É a MESMA rota — o que muda é devolver só o pedaço
        // que vai ser trocado, em vez da página inteira com layout.
        if (!empty($_GET['fragmento'])) {
            $this->view('Clientes/_tabelaClientes', $dados);
            return;
        }

        $this->view('Clientes/listClientes', $dados);
    }

    /**
     * Aba visível da tela de clientes: 'ativos' (padrão) ou 'inativos'.
     * Qualquer outro valor cai em 'ativos' — a aba decide o recorte da
     * listagem, então não pode aceitar entrada arbitrária.
     */
    private function abaAtual(): string
    {
        return (($_GET['aba'] ?? '') === 'inativos') ? 'inativos' : 'ativos';
    }

    /**
     * Filtros da tela de clientes. O status NÃO vem mais do select da toolbar:
     * é a aba que decide se a listagem mostra ativos ou inativos.
     */
    private function filtrosDaTela(): array
    {
        return [
            'busca'  => trim($_GET['busca'] ?? ''),
            'pagina' => max(1, (int) ($_GET['pagina'] ?? 1)),
            'status' => $this->abaAtual() === 'inativos' ? 'inativo' : 'ativo',
        ];
    }

    public function novosMesList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $cliente = $this->model('Cliente');

        echo json_encode([
            'sucesso'  => true,
            'clientes' => $cliente->listarNovosEsteMes()
        ]);
        exit;
    }

    public function vipList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $cliente = $this->model('Cliente');

        echo json_encode([
            'sucesso'  => true,
            'clientes' => $cliente->listarVipRecorrentes()
        ]);
        exit;
    }

    public function vipPerfil()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'erro' => 'ID não informado.']);
            exit;
        }

        $cliente = $this->model('Cliente');
        $perfil  = $cliente->getPerfilCompleto((int) $_GET['id']);

        if (!$perfil) {
            http_response_code(404);
            echo json_encode(['sucesso' => false, 'erro' => 'Cliente não encontrado.']);
            exit;
        }

        echo json_encode(['sucesso' => true, 'cliente' => $perfil]);
        exit;
    }

    public function save()
    {
        $this->requireRole(['ADMIN', 'ATENDENTE']);

        $cliente = $this->model('Cliente');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $validator = new Validator($_POST);
            $validator
                ->required('tipo_pessoa', 'Tipo de pessoa')
                ->required('nome', 'Nome')
                ->email('email', 'E-mail')
                ->custom(
                    'email',
                    fn($v) => !$cliente->emailExisteAtivo($v ?? ''),
                    'Já existe um cliente ativo com este e-mail.'
                )
                ->celularBr('telefone', 'Telefone')
                ->custom(
                    'telefone',
                    fn($v) => !$cliente->telefoneExisteAtivo($v ?? ''),
                    'Já existe um cliente ativo com este telefone.'
                )
                ->cpfCnpj('cpf', 'CPF')
                ->custom(
                    'cpf',
                    fn($v) => !$cliente->cpfExisteAtivo($v ?? ''),
                    'Já existe um cliente ativo com este CPF.'
                )
                ->cpfCnpj('cnpj', 'CNPJ')
                ->custom(
                    'cnpj',
                    fn($v) => !$cliente->cnpjExisteAtivo($v ?? ''),
                    'Já existe um cliente ativo com este CNPJ.'
                );

            if ($validator->fails()) {
                $this->view('Clientes/saveCliente', [
                    'error' => $validator->firstError(),
                    'old'   => $_POST,
                ]);
                return;
            }

            $sucesso = $cliente->create($_POST);

            if ($sucesso) {
                header('Location: ./listClientes');
                exit;
            }

            $erroDetalhado = method_exists($cliente, 'getLastError')
                ? $cliente->getLastError()
                : '';

            $this->view('Clientes/saveCliente', [
                'error' => !empty($erroDetalhado)
                    ? 'Erro ao cadastrar cliente: ' . $erroDetalhado
                    : 'Erro ao cadastrar cliente.',
                'old' => $_POST
            ]);
            return;
        }

        $this->view('Clientes/saveCliente');
    }

    public function edit()
    {
        $this->requireRole(['ADMIN', 'ATENDENTE']);

        $cliente = $this->model('Cliente');
        $veiculo = $this->model('Veiculo');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id_cliente'] ?? null;

            if (!$id || !ctype_digit((string) $id)) {
                header('Location: ./listClientes');
                exit;
            }

            $id = (int) $id;

            $validator = new Validator($_POST);
            $validator
                ->required('tipo_pessoa', 'Tipo de pessoa')
                ->required('nome', 'Nome')
                ->email('email', 'E-mail')
                ->custom(
                    'email',
                    fn($v) => !$cliente->emailExisteAtivo($v ?? '', $id),
                    'Já existe outro cliente ativo com este e-mail.'
                )
                ->celularBr('telefone', 'Telefone')
                ->custom(
                    'telefone',
                    fn($v) => !$cliente->telefoneExisteAtivo($v ?? '', $id),
                    'Já existe outro cliente ativo com este telefone.'
                )
                ->cpfCnpj('cpf', 'CPF')
                ->custom(
                    'cpf',
                    fn($v) => !$cliente->cpfExisteAtivo($v ?? '', $id),
                    'Já existe outro cliente ativo com este CPF.'
                )
                ->cpfCnpj('cnpj', 'CNPJ')
                ->custom(
                    'cnpj',
                    fn($v) => !$cliente->cnpjExisteAtivo($v ?? '', $id),
                    'Já existe outro cliente ativo com este CNPJ.'
                );

            if ($validator->fails()) {
                $this->view('Clientes/editCliente', [
                    'cliente'  => array_merge($cliente->findById($id) ?: [], $_POST),
                    'veiculos' => $veiculo->getByCliente($id),
                    'error'    => $validator->firstError(),
                ]);
                return;
            }

            $sucesso = $cliente->update($id, $_POST);

            if ($sucesso) {
                header('Location: ./listClientes');
                exit;
            }

            $erroDetalhado = method_exists($cliente, 'getLastError')
                ? $cliente->getLastError()
                : '';

            $dados = [
                'cliente'  => array_merge(
                    $cliente->findById($id) ?: [],
                    $_POST
                ),
                'veiculos' => $veiculo->getByCliente($id),
                'error'    => !empty($erroDetalhado)
                    ? $erroDetalhado
                    : 'Erro ao atualizar cliente.'
            ];

            $this->view('Clientes/editCliente', $dados);
            return;
        }

        if (empty($_GET['id'])) {
            header('Location: ./listClientes');
            exit;
        }

        $dados = [
            'cliente'  => $cliente->findById($_GET['id']),
            'veiculos' => $veiculo->getByCliente($_GET['id']),
        ];

        if (!$dados['cliente']) {
            $this->naoEncontrado('Cliente', './listClientes');
        }

        $this->view('Clientes/editCliente', $dados);
    }

    public function visualizar()
    {
        if (empty($_GET['id'])) {
            header('Location: ./listClientes');
            exit;
        }

        $cliente = $this->model('Cliente');

        $dados = [
            'dados_cliente' => $cliente->findById($_GET['id'])
        ];

        if (!$dados['dados_cliente']) {
            $this->naoEncontrado('Cliente', './listClientes');
        }

        $this->view('Clientes/viewCliente', $dados);
    }

    /**
     * Os desativados deixaram de ter tela própria: viraram a aba "Inativos"
     * de listClientes. A rota continua existindo e redirecionando porque
     * links antigos e favoritos ainda apontam pra cá.
     */
    public function listDesativados()
    {
        header('Location: ' . $this->urlInativos());
        exit;
    }

    public function reativar()
    {
        // Mesma exigência de delete() (desativar): não fazia sentido quem não
        // pode desativar um cliente poder trazê-lo de volta.
        $this->requireRole(['ADMIN']);

        // POST em vez de GET: um link GET que muda dado pode ser disparado por
        // um <img>/pré-carregamento em outra página só de o ADMIN estar logado,
        // sem nenhum clique consciente. O gate de CSRF só cobre POST.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $this->urlInativos());
            exit;
        }

        if (!empty($_POST['id']) && ctype_digit((string) $_POST['id'])) {
            $cliente = $this->model('Cliente');
            $cliente->reativar((int) $_POST['id']);
        }

        header('Location: ' . $this->urlInativos());
        exit;
    }

    /** Volta pra aba de inativos preservando a busca que estava na tela. */
    private function urlInativos(): string
    {
        $query = ['aba' => 'inativos'];

        $busca = trim($_GET['busca'] ?? '');
        if ($busca !== '') {
            $query['busca'] = $busca;
        }

        return './listClientes?' . http_build_query($query);
    }

    public function delete()
    {
        $this->requireRole(['ADMIN']);

        // POST em vez de GET: um link GET que muda dado pode ser disparado por
        // um <img>/pré-carregamento em outra página só de o ADMIN estar logado,
        // sem nenhum clique consciente. O gate de CSRF só cobre POST.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
            header('Location: ./listClientes');
            exit;
        }

        $cliente = $this->model('Cliente');

        $cliente->delete((int) $_POST['id']);

        header('Location: ./listClientes');
        exit;
    }
}
