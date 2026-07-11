<?php

class MovimentacaoEstoqueController extends Controller
{
    private int $itensPorPagina = 8;

    // ── LIST ────────────────────────────────────────────────────────
    public function list()
    {
        $model       = $this->model('MovimentacaoEstoque');
        $filtros     = $_GET;
        $paginaAtual = max(1, (int)($_GET['pagina'] ?? 1));
        $offset      = ($paginaAtual - 1) * $this->itensPorPagina;

        $total        = $model->count($filtros);
        $totalPaginas = (int) ceil($total / $this->itensPorPagina);

        $this->view('MovimentacaoEstoque/listMovimentacaoEstoque', [
            'MovimentacaoEstoque'      => $model->list($this->itensPorPagina, $offset, $filtros),
            'totalmovimentacaoestoque' => $total,
            'paginaAtual'              => $paginaAtual,
            'totalPaginas'             => $totalPaginas,
        ]);
    }

    // ── SAVE: GET abre formulário / POST registra movimentação ───────
    public function save()
    {
        $model = $this->model('MovimentacaoEstoque');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $sucesso = $model->save($_POST);

            if ($sucesso) {
                header('Location: ./listMovimentacaoEstoque');
                exit;
            }

            $erroDetalhado = method_exists($model, 'getLastError')
                ? $model->getLastError()
                : '';

            $this->view('MovimentacaoEstoque/saveMovimentacaoEstoque', [
                'produtos' => $model->getProdutosAtivos(),
                'error'    => !empty($erroDetalhado)
                    ? 'Erro ao registrar movimentação: ' . $erroDetalhado
                    : 'Erro ao registrar movimentação.',
                'old'      => $_POST,
            ]);
            return;
        }

        $this->view('MovimentacaoEstoque/saveMovimentacaoEstoque', [
            'produtos' => $model->getProdutosAtivos(),
        ]);
    }

    // ── EDIT: exibe form de edição ──────────────────────────────────
    // ── EDIT: GET abre edição / POST atualiza ───────────────────────────
public function edit()
{
    $model = $this->model('MovimentacaoEstoque');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id_movimentacao'] ?? 0);

        if (!$id) {
            header('Location: ./listMovimentacaoEstoque');
            exit;
        }

        $sucesso = $model->update($id, $_POST);

        if ($sucesso) {
            header('Location: ./listMovimentacaoEstoque');
            exit;
        }

        $erroDetalhado = method_exists($model, 'getLastError')
            ? $model->getLastError()
            : '';

        $this->view('MovimentacaoEstoque/editMovimentacaoEstoque', [
            'movimentacao' => array_merge(
                $model->findById($id) ?: [],
                $_POST
            ),
            'produtos'     => $model->getProdutosAtivos(),
            'error'        => !empty($erroDetalhado)
                ? 'Erro ao atualizar movimentação: ' . $erroDetalhado
                : 'Erro ao atualizar movimentação.',
            'old'          => $_POST,
        ]);
        return;
    }

    $id = (int)($_GET['id'] ?? 0);

    if (!$id) {
        header('Location: ./listMovimentacaoEstoque');
        exit;
    }

    $mov = $model->findById($id);

    if (!$mov) {
        header('Location: ./listMovimentacaoEstoque');
        exit;
    }

    $this->view('MovimentacaoEstoque/editMovimentacaoEstoque', [
        'movimentacao' => $mov,
        'produtos'     => $model->getProdutosAtivos(),
    ]);
}

    // ── DELETE ──────────────────────────────────────────────────────
    public function delete()
    {
        $this->requireRole(['ADMIN']);

        // POST em vez de GET: sem isso, um <img>/pré-carregamento em outra
        // página dispara a exclusão (e a reversão de estoque) só de o ADMIN
        // estar logado, sem clique consciente. O gate de CSRF só cobre POST.
        $id = ($_SERVER['REQUEST_METHOD'] === 'POST') ? (int)($_POST['id'] ?? 0) : 0;

        if (!$id) {
            header('Location: ./listMovimentacaoEstoque');
            exit;
        }

        $model = $this->model('MovimentacaoEstoque');
        $model->delete($id);

        header('Location: ./listMovimentacaoEstoque');
        exit;
    }
}