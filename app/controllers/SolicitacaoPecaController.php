<?php

require_once __DIR__ . '/../models/SolicitacaoPeca.php';
require_once __DIR__ . '/../models/EstoqueConsulta.php';

/**
 * Solicitação de peça. Técnico e atendente criam e acompanham (mesmas permissões); só o
 * administrador analisa, aprova, cota e transforma em pedido de compra. O que envolve
 * fornecedor, preço e pedido NÃO aparece para a equipe.
 */
class SolicitacaoPecaController extends Controller
{
    private function usuarioId(): int
    {
        return (int) ($_SESSION['user_id_usuario'] ?? 0);
    }

    private function papel(): string
    {
        return (string) ($_SESSION['user_tipo'] ?? '');
    }

    private function exigirPost(string $voltar = './listSolicitacoes'): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ' . $voltar);
            exit;
        }
    }

    private function voltar(int $id, array $r, string $codigoOk): void
    {
        header('Location: ./viewSolicitacao?id=' . $id . '&' . (!empty($r['ok'])
            ? 'ok=' . $codigoOk
            : 'erro=fluxo&detalhe=' . urlencode((string) ($r['erro'] ?? 'Não foi possível concluir a ação.'))));
        exit;
    }

    public function list()
    {
        $model = $this->model('SolicitacaoPeca');

        $grupo = (string) ($_GET['aba'] ?? 'abertas');
        $f = ['busca' => $_GET['busca'] ?? ''];

        if (isset(SolicitacaoPeca::STATUS_LABEL[$grupo])) {
            $f['status'] = $grupo;
        } else {
            $f['grupo'] = $grupo === 'todas' ? 'todas' : 'abertas';
            $grupo = $f['grupo'];
        }

        if (!empty($_GET['urgencia'])) {
            $f['urgencia'] = $_GET['urgencia'];
        }

        if (!empty($_GET['minhas'])) {
            $f['solicitante_id'] = $this->usuarioId();
        }

        $this->view('Solicitacoes/listSolicitacoes', [
            'solicitacoes' => $model->listar($f),
            'contagem'     => $model->contagemPorStatus(),
            'aba'          => $grupo,
            'busca'        => $f['busca'],
            'urgencia'     => $f['urgencia'] ?? '',
            'minhas'       => !empty($_GET['minhas']),
            'ehAdmin'      => $this->papel() === 'ADMIN',
            'sucesso'      => $_GET['ok'] ?? null,
        ]);
    }

    public function save()
    {
        $model = $this->model('SolicitacaoPeca');
        $estoque = new EstoqueConsulta();

        $dados = [
            'produtos'    => $estoque->opcoesParaSolicitar(),
            'ordens'      => $model->osAbertas(),
            'urgencias'   => SolicitacaoPeca::URGENCIAS,
            'old'         => [],
            'error'       => null,
        ];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $dados['old'] = array_filter([
                'produto_id'       => $_GET['produto_id'] ?? null,
                'ordem_servico_id' => $_GET['ordem_servico_id'] ?? null,
                'quantidade'       => $_GET['quantidade'] ?? null,
            ]);
            $this->view('Solicitacoes/saveSolicitacao', $dados);
            return;
        }

        $r = $model->registrar($_POST, $this->usuarioId());

        if ($r['ok']) {
            header('Location: ./viewSolicitacao?id=' . $r['id'] . '&ok=' . ($r['repetido'] ? 'jaexistia' : 'registrada'));
            exit;
        }

        $dados['error'] = $r['erro'];
        $dados['old']   = $_POST;
        $this->view('Solicitacoes/saveSolicitacao', $dados);
    }

    public function visualizar()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $model = $this->model('SolicitacaoPeca');
        $s = $id > 0 ? $model->getById($id) : null;

        if (!$s) {
            $this->naoEncontrado('Solicitação de peça', './listSolicitacoes');
        }

        $ehAdmin = $this->papel() === 'ADMIN';
        $pedidos = $model->pedidosDaSolicitacao($id);

        $dados = [
            'solicitacao'  => $s,
            'linhaDoTempo' => $model->linhaDoTempo($id),
            'ehAdmin'      => $ehAdmin,
            'podeCancelar' => $ehAdmin
                ? in_array($s['status'], SolicitacaoPeca::ABERTAS, true)
                : ($s['status'] === 'SOLICITADA' && (int) $s['solicitante_id'] === $this->usuarioId()),
            // A equipe vê só ONDE a solicitação está (pedido feito / recebido), sem fornecedor nem preço.
            'pedidos'      => $ehAdmin ? $pedidos : array_map(fn($p) => ['numero' => $p['numero'], 'status' => $p['status'], 'quantidade' => $p['quantidade'], 'quantidade_recebida' => $p['quantidade_recebida']], $pedidos),
            'cotacoes'     => [],
            'fornecedores' => [],
            'sucesso'      => $_GET['ok'] ?? null,
            'erro'         => ($_GET['erro'] ?? '') === 'fluxo' ? ($_GET['detalhe'] ?? 'Não foi possível concluir a ação.') : null,
        ];

        if ($ehAdmin) {
            require_once __DIR__ . '/../models/CotacaoCompra.php';
            $dados['cotacoes'] = (new CotacaoCompra())->daSolicitacao($id);
            $dados['fornecedores'] = $this->model('Fornecedor')->listar();
        }

        $this->view('Solicitacoes/viewSolicitacao', $dados);
    }

    public function cancelar()
    {
        $this->exigirPost();

        $id = (int) ($_POST['id_solicitacao'] ?? 0);
        $this->voltar($id, $this->model('SolicitacaoPeca')->cancelar($id, $this->usuarioId(), $this->papel(), (string) ($_POST['motivo'] ?? '')), 'cancelada');
    }

    public function analisar()
    {
        $this->exigirPost();

        $id = (int) ($_POST['id_solicitacao'] ?? 0);
        $this->voltar($id, $this->model('SolicitacaoPeca')->analisar($id, $this->usuarioId()), 'analise');
    }

    public function aprovar()
    {
        $this->exigirPost();

        $id = (int) ($_POST['id_solicitacao'] ?? 0);
        $this->voltar($id, $this->model('SolicitacaoPeca')->aprovar($id, $this->usuarioId()), 'aprovada');
    }

    public function registrarCotacao()
    {
        $this->exigirPost();

        require_once __DIR__ . '/../models/CotacaoCompra.php';
        $id = (int) ($_POST['id_solicitacao'] ?? 0);
        $r = (new CotacaoCompra())->registrar(['solicitacao_id' => $id] + $_POST, $this->usuarioId());

        $this->voltar($id, $r, 'cotacao');
    }

    public function excluirCotacao()
    {
        $this->exigirPost();

        require_once __DIR__ . '/../models/CotacaoCompra.php';
        $id = (int) ($_POST['id_solicitacao'] ?? 0);
        (new CotacaoCompra())->excluir((int) ($_POST['id_cotacao'] ?? 0));

        $this->voltar($id, ['ok' => true], 'cotacaoexcluida');
    }

    /** Transforma a cotação escolhida em item do rascunho do fornecedor e abre o pedido. */
    public function usarCotacao()
    {
        $this->exigirPost();

        $r = $this->model('PedidoCompra')->incluirCotacao((int) ($_POST['id_cotacao'] ?? 0), $this->usuarioId());

        if ($r['ok']) {
            header('Location: ./viewPedidoCompra?id=' . $r['pedido_id'] . '&ok=itemincluido');
            exit;
        }

        $this->voltar((int) ($_POST['id_solicitacao'] ?? 0), $r, '');
    }
}
