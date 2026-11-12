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
        $this->requireRole(Permissao::PAPEIS_ADMIN);

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
        $this->requireRole(Permissao::PAPEIS_ADMIN);

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
            $this->naoEncontrado('Produto', './listProduto');
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
                $this->naoEncontrado('Produto', './listProduto');
            }

            $this->view('Produtos/viewProduto', $dados);
        }

        /**
         * Folha de etiquetas de prateleira de um produto já cadastrado.
         *
         * Só existe para produto salvo: a etiqueta carrega o código do
         * produto, e antes de gravar esse código ainda é só o "próximo da
         * fila" — pode acabar em outro produto se o cadastro não for
         * concluído, e aí a etiqueta na prateleira aponta para a peça errada.
         *
         * O código de barras impresso segue o que estiver cadastrado:
         * 12/13 dígitos viram EAN-13 (o dígito verificador é calculado pela
         * biblioteca); qualquer outro formato vira Code 128, que aceita
         * letras. Sem código de barras cadastrado, o código interno (PRD-0001)
         * é impresso em Code 128 — assim toda etiqueta sai escaneável.
         */
        public function imprimirEtiqueta()
        {
            if (empty($_GET['id'])) {
                header('Location: ./listProduto');
                exit;
            }

            $produtoModel = $this->model('Produtos');
            $produto      = $produtoModel->getById($_GET['id']);

            if (!$produto) {
                $this->naoEncontrado('Produto', './listProduto');
            }

            $categoria = '';
            foreach ($produtoModel->getCategorias() as $c) {
                if ((int) $c['id_categoria'] === (int) ($produto['categorias_produtos_id'] ?? 0)) {
                    $categoria = $c['nome'];
                    break;
                }
            }

            $codigoBarras = trim((string) ($produto['codigo_barras'] ?? ''));
            $codigoImpresso = $codigoBarras !== ''
                ? $codigoBarras
                : trim((string) ($produto['codigo_interno'] ?? ''));

            $barras = $this->gerarCodigoDeBarras($codigoImpresso);

            $barcodeSvg = $barras['svg'];
            // O que sai embaixo da barra é o que a barra realmente carrega:
            // num EAN-13 cadastrado com 12 dígitos, a biblioteca acrescenta o
            // dígito verificador, e imprimir o número original deixaria a
            // etiqueta divergindo do que o leitor devolve no caixa.
            $codigoImpresso = $barras['codigo'];

            require __DIR__ . '/../views/Produtos/etiquetaProduto.php';
            exit;
        }

        /**
         * ['svg' => código de barras renderizado, 'codigo' => valor que a barra
         * de fato carrega]. Falha de geração não pode derrubar a impressão:
         * sem a barra, a etiqueta ainda sai com o código legível em texto.
         */
        private function gerarCodigoDeBarras(string $codigo): array
        {
            if ($codigo === '') {
                return ['svg' => '', 'codigo' => ''];
            }

            require_once __DIR__ . '/../../vendor/autoload.php';

            $gerador = new \Picqer\Barcode\BarcodeGeneratorSVG();

            // EAN-13 primeiro quando o código tem cara de EAN, com Code 128
            // como rede de segurança: um cadastro com 13 dígitos e dígito
            // verificador errado faz o EAN-13 estourar, e aí a etiqueta sairia
            // sem barra nenhuma em vez de sair escaneável em outro padrão.
            $tentativas = preg_match('/^\d{12,13}$/', $codigo)
                ? [$gerador::TYPE_EAN_13, $gerador::TYPE_CODE_128]
                : [$gerador::TYPE_CODE_128];

            foreach ($tentativas as $tipo) {
                try {
                    $svg = $gerador->getBarcode($codigo, $tipo, 2, 44);

                    // O gerador devolve um documento SVG completo; dentro do
                    // HTML, o prólogo XML/DOCTYPE apareceria como texto solto
                    // em cima da etiqueta.
                    $inicio = strpos($svg, '<svg');

                    // <desc> guarda o valor codificado — com o dígito
                    // verificador, quando a biblioteca calculou um.
                    $codificado = preg_match('/<desc>([^<]*)<\/desc>/', $svg, $m)
                        ? trim($m[1])
                        : $codigo;

                    return [
                        'svg'    => $inicio === false ? $svg : substr($svg, $inicio),
                        'codigo' => $codificado !== '' ? $codificado : $codigo,
                    ];
                } catch (\Throwable $e) {
                    error_log("Código de barras ({$tipo}) do produto: " . $e->getMessage());
                }
            }

            return ['svg' => '', 'codigo' => $codigo];
        }

        public function arquivar()
        {
            $this->requireRole(Permissao::PAPEIS_ADMIN);

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

        public function gerarPDF()
        {
            echo 'PDF de produtos ainda não implementado.';
        }
    }