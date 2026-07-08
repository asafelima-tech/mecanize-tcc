<?php

class RelatorioController extends Controller
{
    public function listRelatorio()
    {
        $this->requireRole(['ADMIN']);

        $relatorio = $this->model('Relatorio');

        $dados = $relatorio->getResumo();

        $this->view('Relatorios/listRel', $dados);
    }
}
