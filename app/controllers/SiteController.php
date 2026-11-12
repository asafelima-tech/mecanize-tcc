<?php

class SiteController extends Controller
{
    public function index()
    {
        require_once __DIR__ . '/../core/auth.php';

        $dados = $this->model('Home')->getResumo();

        // A tela inicial começa pelo que exige ação de quem entrou (perfil), não por números de vitrine.
        // Falha na contagem não pode impedir o início de abrir.
        try {
            $dados['pendencias'] = $this->model('Pendencias')->paraPerfil((string) ($_SESSION['user_tipo'] ?? ''));
        } catch (\Throwable $e) {
            error_log('[início] pendências: ' . $e->getMessage());
            $dados['pendencias'] = [];
        }

        $this->view('Site/home', $dados);
    }

    public function home()
    {
        $this->index();
    }
    public function erro404()
    {
        Erro::exibir('nao-encontrado');
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
     * placa/primeiro nome (sem dado sensível) das OS em andamento e das que
     * o técnico já finalizou e o administrador liberou (AGUARDANDO_RETIRADA). Atualiza sozinho (meta refresh).
     *
     * Antes da área do técnico, "concluída" era o único status de "pronto";
     * mostrava as concluídas nas últimas 48h como aproximação de "ainda não
     * retirado". Agora CONCLUIDA passou a significar "já entregue" (ver
     * OrdemServico::STATUS_LABEL) — um carro nesse status já foi embora, então
     * mostrar isso na tela de "pronto para retirada" ficaria ao contrário.
     * AGUARDANDO_RETIRADA é o status certo: conferida pelo administrador, ainda não saiu.
     */
    public function painel()
    {
        $ordemServico = $this->model('OrdemServico');

        $dados = [
            'emAndamento' => $ordemServico->listarAtivas(),
            'prontas'     => $ordemServico->listarAguardandoRetirada(),
        ];

        $this->view('Site/painel', $dados);
    }

}