<?php

require_once __DIR__ . '/../models/Notificacao.php';

/**
 * O sino. Cada pessoa vê e marca só as próprias notificações (o filtro por usuário está no model).
 */
class NotificacaoController extends Controller
{
    private function usuarioId(): int
    {
        return (int) ($_SESSION['user_id_usuario'] ?? 0);
    }

    public function list()
    {
        $model = new Notificacao();

        $this->view('Notificacoes/listNotificacoes', [
            'notificacoes' => $model->listar($this->usuarioId(), !empty($_GET['nao_lidas'])),
            'soNaoLidas'   => !empty($_GET['nao_lidas']),
            'naoLidas'     => $model->contarNaoLidas($this->usuarioId()),
        ]);
    }

    /** Marca como lida e leva ao assunto. O link vem do banco (gerado pelo sistema), nunca do pedido. */
    public function ler()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ./listNotificacoes');
            exit;
        }

        $n = (new Notificacao())->marcarLida((int) ($_POST['id_notificacao'] ?? 0), $this->usuarioId());

        $destino = $n['link'] ?? '';

        // Só rotas internas relativas ("./rota?..."): nada de ir para fora do sistema.
        header('Location: ' . (preg_match('#^\./[A-Za-z0-9_]+(\?[A-Za-z0-9_=&%.\-]*)?$#', $destino) ? $destino : './listNotificacoes'));
        exit;
    }

    public function lerTodas()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            (new Notificacao())->marcarTodas($this->usuarioId());
        }

        header('Location: ./listNotificacoes');
        exit;
    }
}
