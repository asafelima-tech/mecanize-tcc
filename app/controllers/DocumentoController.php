<?php

class DocumentoController extends Controller
{
    public function list()
    {
        $this->requireRole(['ADMIN']);

        $documento = $this->model('Documento');

        $dados = $documento->getResumo();

        $this->view('Documento/listDocumentos', $dados);
    }

    public function save()
    {
        $this->requireRole(['ADMIN']);

        $os_id = $_GET['os_id'] ?? null;

        if (!$os_id) {
            header('Location: ./listDocumentos');
            exit;
        }

        $documento = $this->model('Documento');

        $dados = [
            'os' => $documento->getOSParaOrcamento($os_id)
        ];

        if (!$dados['os']) {
            header('Location: ./listDocumentos');
            exit;
        }

        $this->view('Documento/saveDocumento', $dados);
    }

    public function finalizar()
    {
        $this->requireRole(['ADMIN']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ./listDocumentos');
            exit;
        }

        $id = $_POST['os_id'] ?? null;

        if (!$id) {
            header('Location: ./listDocumentos');
            exit;
        }
        
        $documento = $this->model('Documento');

        if ($documento->finalizar($id)) {
            header('Location: ./listDocumentos');
            exit;
        }

        $documento2 = $this->model('Documento');
        $dados = $documento2->getResumo();
        $dados['error'] = 'Erro ao finalizar o orçamento.';

        $this->view('Documento/listDocumentos', $dados);
    }
}
