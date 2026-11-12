<?php

class RelatorioController extends Controller
{
    public function listRelatorio()
    {
        $this->requireRole(Permissao::PAPEIS_ADMIN);

        $relatorio = $this->model('Relatorio');

        $dados = $relatorio->getResumo();

        $this->view('Relatorios/listRel', $dados);
    }
}
