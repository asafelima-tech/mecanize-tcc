<?php

class CategoriaController extends Controller
{
    public function list()
    {
        $categoria = $this->model('Categoria');

        $dados = [
            'categorias' => $categoria->list()
        ];

        $this->view('categoria/listCategoria', $dados);
    }

    public function save()
    {
        $categoria = $this->model('Categoria');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($categoria->create($_POST)) {
                header('Location: ./listCategoria');
                exit;
            }

            $this->view('categoria/saveCategoria', [
                'error' => 'Erro ao cadastrar categoria.',
                'old' => $_POST
            ]);
            return;
        }

        $this->view('categoria/saveCategoria');
    }

   public function edit()
{
    $categoria = $this->model('Categoria');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST['id_categoria'])) {
            header('Location: ./listCategoria');
            exit;
        }

        if ($categoria->update($_POST)) {
            header('Location: ./listCategoria');
            exit;
        }

        $this->view('categoria/editCategoria', [
            'categoria' => $_POST,
            'error' => 'Erro ao atualizar categoria.'
        ]);
        return;
    }

    if (empty($_GET['id'])) {
        header('Location: ./listCategoria');
        exit;
    }

    $dados = [
        'categoria' => $categoria->findById($_GET['id'])
    ];

    if (!$dados['categoria']) {
        header('Location: ./listCategoria');
        exit;
    }

    $this->view('categoria/editCategoria', $dados);
}
    public function delete()
    {
        if (empty($_GET['id'])) {
            header('Location: ./listCategoria');
            exit;
        }

        $categoria = $this->model('Categoria');
        $categoria->delete($_GET['id']);

        header('Location: ./listCategoria');
        exit;
    }
}