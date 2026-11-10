<?php

final class NotificacoesController extends Controller
{
    public function lista(): void
    {
        $this->tela('notificacoes/lista', ['notificacoes' => (new Notificacao())->listar()]);
    }

    /** Marca como lida e abre o link do aviso. */
    public function abrir(): void
    {
        $link = (new Notificacao())->ler($this->idDaUrl()) ?? './notificacoes';
        // Só links internos relativos (./rota?...): nada de redirecionar para fora.
        header('Location: ' . (preg_match('#^\./[a-z0-9\-]+(\?[^\s]*)?$#i', $link) ? $link : './notificacoes'));
        exit;
    }

    public function lerTodas(): void
    {
        (new Notificacao())->lerTodas();
        $this->redirecionar('notificacoes');
    }
}
