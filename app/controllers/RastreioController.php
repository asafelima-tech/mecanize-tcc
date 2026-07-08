<?php

/**
 * Rastreamento público (sem login) de OS/Orçamento por token — pra o
 * cliente acompanhar o status sem precisar ligar pra oficina perguntando.
 * Rota liberada em $rotasPublicas (public/index.php).
 */
class RastreioController extends Controller
{
    public function ver()
    {
        $token = trim($_GET['token'] ?? '');

        if ($token === '') {
            $this->view('Site/rastreioNaoEncontrado');
            return;
        }

        $os = $this->model('OrdemServico')->getPorTokenRastreio($token);

        if ($os) {
            $this->view('Site/rastreio', ['tipo' => 'os', 'dados' => $os]);
            return;
        }

        $orc = $this->model('Orcamento')->getPorTokenRastreio($token);

        if ($orc) {
            $this->view('Site/rastreio', ['tipo' => 'orcamento', 'dados' => $orc]);
            return;
        }

        $this->view('Site/rastreioNaoEncontrado');
    }
}
