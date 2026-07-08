<?php

class SiteController extends Controller
{
    public function index()
    {
        require_once __DIR__ . '/../core/auth.php';

        $home = $this->model('Home');

        $dados = $home->getResumo();

        $this->view('site/home', $dados);
    }

    public function sobre()
    {
        $this->view('site/sobre');
    }

    public function contato()
    {
        $this->view('site/contato');
    }

    public function home()
    {
        require_once __DIR__ . '/../core/auth.php';

        $home = $this->model('Home');

        $dados = $home->getResumo();

        $this->view('site/home', $dados);
    }

    public function recuperarLogin()
    {
        $this->view('site/recuperarLogin');
    }

    public function erro404()
    {
        $this->view('site/404');
    }

    public function termos()
    {
        $this->view('Site/termos');
    }

    public function privacidade()
    {
        $this->view('Site/privacidade');
    }

    /**
     * Painel "modo TV" pra deixar num monitor da sala de espera — mostra só
     * placa/primeiro nome (sem dado sensível) das OS em andamento e das
     * concluídas recentemente. Atualiza sozinho (meta refresh).
     */
    public function painel()
    {
        $ordemServico = $this->model('OrdemServico');

        $dados = [
            'emAndamento' => $ordemServico->listarAtivas(),
            'prontas'     => $ordemServico->listarConcluidasRecentes(48),
        ];

        $this->view('Site/painel', $dados);
    }

    public function tabela()
    {
        require_once __DIR__ . '/../core/auth.php';

        $this->view('site/tabela');
    }
}