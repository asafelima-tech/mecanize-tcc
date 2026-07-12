    <?php

    class ProdutoController extends Controller
    {
        public function list()
        {
            $produto = $this->model('Produtos');

            $dados = $produto->getResumo($_GET);

            $this->view('Produtos/listProdutos', $dados);
        }

        public function save()
    {
        $produto = $this->model('Produtos');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $validator = (new Validator($_POST))
                ->required('categorias_produtos_id', 'Categoria')
                ->required('nome', 'Nome')
                ->required('localizacao', 'Localização');

            if ($validator->fails()) {
                $this->view('Produtos/saveProduto', [
                    'categorias' => $produto->getCategorias(),
                    'proximoCodigoInterno' => $produto->getProximoCodigoInterno(),
                    'error' => $validator->firstError(),
                    'old'   => $_POST,
                ]);
                return;
            }

            $sucesso = $produto->create($_POST);

            if ($sucesso) {
                header('Location: ./listProduto');
                exit;
            }

            $erroDetalhado = method_exists($produto, 'getLastError')
                ? $produto->getLastError()
                : '';

            $dados = [
                'categorias' => $produto->getCategorias(),
                'proximoCodigoInterno' => $produto->getProximoCodigoInterno(),
                'error' => !empty($erroDetalhado)
                    ? 'Erro ao cadastrar produto: ' . $erroDetalhado
                    : 'Erro ao cadastrar produto.',
                'old' => $_POST
            ];

            $this->view('Produtos/saveProduto', $dados);
            return;
        }

        $dados = [
            'categorias' => $produto->getCategorias(),
            'proximoCodigoInterno' => $produto->getProximoCodigoInterno()
        ];

        $this->view('Produtos/saveProduto', $dados);
    }

    public function edit()
    {
        $produto = $this->model('Produtos');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id_produto'] ?? null;

            if (!$id) {
                header('Location: ./listProduto');
                exit;
            }

            $validator = (new Validator($_POST))
                ->required('categorias_produtos_id', 'Categoria')
                ->required('nome', 'Nome')
                ->required('localizacao', 'Localização');

            if ($validator->fails()) {
                $this->view('Produtos/editProduto', [
                    'categorias' => $produto->getCategorias(),
                    'produto'    => array_merge($produto->getById($id) ?: [], $_POST),
                    'error'      => $validator->firstError(),
                ]);
                return;
            }

            $sucesso = $produto->update($id, $_POST);

            if ($sucesso) {
                header('Location: ./listProduto');
                exit;
            }

            $erroDetalhado = method_exists($produto, 'getLastError')
                ? $produto->getLastError()
                : '';

            $dados = [
                'categorias' => $produto->getCategorias(),
                'produto'    => array_merge(
                    $produto->getById($id) ?: [],
                    $_POST
                ),
                'error'      => !empty($erroDetalhado)
                    ? 'Erro ao atualizar produto: ' . $erroDetalhado
                    : 'Erro ao atualizar produto.',
                'old'        => $_POST
            ];

            $this->view('Produtos/editProduto', $dados);
            return;
        }

        $id = $_GET['id'] ?? null;

        if (!$id) {
            header('Location: ./listProduto');
            exit;
        }

        $dados = [
            'produto'    => $produto->getById($id),
            'categorias' => $produto->getCategorias()
        ];

        if (!$dados['produto']) {
            header('Location: ./listProduto');
            exit;
        }

        $this->view('Produtos/editProduto', $dados);
    }

        public function visualizar()
        {
            if (empty($_GET['id'])) {
                header('Location: ./listProduto');
                exit;
            }

            $produto = $this->model('Produtos');

            $dados = [
                'produto'    => $produto->getById($_GET['id']),
                'categorias' => $produto->getCategorias()
            ];

            if (!$dados['produto']) {
                header('Location: ./listProduto');
                exit;
            }

            $this->view('Produtos/viewProduto', $dados);
        }

        public function arquivar()
        {
            $this->requireRole(['ADMIN']);

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                header('Location: ./listProduto');
                exit;
            }

            $id = $_POST['id_produto'] ?? null;

            if (!$id) {
                header('Location: ./listProduto');
                exit;
            }

            $produto = $this->model('Produtos');

            $produto->arquiva($id);

            header('Location: ./listProduto');
            exit;
        }

        public function listRelatorio()
        {
            $produto = $this->model('Produtos');

            $dados = $produto->getResumo($_GET);

            $this->view('Produtos/relProduto', $dados);
        }

        public function gerarPDF()
        {
            echo 'PDF de produtos ainda não implementado.';
        }
    }