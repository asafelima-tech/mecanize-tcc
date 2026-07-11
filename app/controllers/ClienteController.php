<?php

class ClienteController extends Controller
{
    public function list()
    {
        $cliente = $this->model('Cliente');

        $dados = $cliente->getResumo($_GET);

        $this->view('Clientes/listClientes', $dados);
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
            header('Location: ./listClientes');
            exit;
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
            header('Location: ./listClientes');
            exit;
        }

        $this->view('Clientes/viewCliente', $dados);
    }

    public function listDesativados()
    {
        $cliente = $this->model('Cliente');

        $busca = $_GET['busca'] ?? '';
        $pagina = (int)($_GET['pagina'] ?? 1);

        $dados = $cliente->getDesativados($busca, $pagina);

        $this->view('Clientes/listClientesDesativados', $dados);
    }

    public function reativar()
    {
        if (empty($_GET['id'])) {
            header('Location: ./listClientesDesativados');
            exit;
        }

        if (!ctype_digit((string) $_GET['id'])) {
            header('Location: ./listClientesDesativados');
            exit;
        }

        $cliente = $this->model('Cliente');

        $cliente->reativar((int) $_GET['id']);

        header('Location: ./listClientesDesativados');
        exit;
    }

    public function delete()
    {
        $this->requireRole(['ADMIN']);

        if (empty($_GET['id'])) {
            header('Location: ./listClientes');
            exit;
        }

        $cliente = $this->model('Cliente');

        $cliente->delete((int) $_GET['id']);

        header('Location: ./listClientes');
        exit;
    }
}
