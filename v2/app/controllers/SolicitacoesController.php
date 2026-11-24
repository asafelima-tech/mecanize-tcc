<?php

/** Pedidos de material dos técnicos, do lado do ADMIN: ver, aprovar, marcar como atendido ou recusar. */
final class SolicitacoesController extends Controller
{
    public function lista(): void
    {
        $m = new Solicitacao();
        $p = new Paginacao();
        $filtro = isset(Solicitacao::FILTROS[$_GET['situacao'] ?? '']) ? $_GET['situacao'] : 'abertas';

        $this->tela('solicitacoes/lista', [
            'filtro'    => $filtro,
            'pedidos'   => $m->listar($filtro, $p),
            'contagens' => $m->contagens(),
            'p'         => $p,
            'titulo'    => 'Pedidos dos técnicos',
        ]);
    }

    public function responder(): void
    {
        $id = $this->idDaUrl();
        $novo = $this->post('status');
        $mensagens = ['APROVADA' => 'Pedido aprovado.', 'ATENDIDA' => 'Pedido marcado como atendido.', 'RECUSADA' => 'Pedido recusado.'];
        if (!isset($mensagens[$novo])) {
            throw new ErroHttp('nao-encontrado');
        }
        try {
            (new Solicitacao())->responder($id, $novo, $this->post('resposta'));
            $this->avisar($mensagens[$novo] . ' O técnico foi avisado.');
        } catch (DomainException $e) {
            $this->avisar($e->getMessage(), 'erro');
        }
        $voltar = $this->post('voltar');
        if (preg_match('/^os:(\d+)$/', $voltar, $m)) {
            $this->redirecionar('os', ['id' => (int) $m[1]]);
        }
        $this->redirecionar('solicitacoes', array_filter(['situacao' => $this->post('situacao')]));
    }
}
