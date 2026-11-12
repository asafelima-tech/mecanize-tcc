<?php

require_once __DIR__ . '/../models/SolicitacaoPeca.php';
require_once __DIR__ . '/../models/PedidoCompra.php';
require_once __DIR__ . '/../models/Fornecedor.php';

/**
 * Compras e fornecedores — só administrador (a matriz de rotas barra os demais).
 *
 * A tela só traduz o formulário: quem decide o que pode (estado do pedido, quantidades,
 * saldo) são os models. Comprar não mexe no estoque; só o recebimento confirmado.
 */
class CompraController extends Controller
{
    private function usuarioId(): int
    {
        return (int) ($_SESSION['user_id_usuario'] ?? 0);
    }

    private function exigirPost(string $voltar = './painelCompras'): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ' . $voltar);
            exit;
        }
    }

    private function voltarAoPedido(int $id, array $r, string $codigoOk): void
    {
        header('Location: ./viewPedidoCompra?id=' . $id . '&' . (!empty($r['ok'])
            ? 'ok=' . $codigoOk
            : 'erro=fluxo&detalhe=' . urlencode((string) ($r['erro'] ?? 'Não foi possível concluir a ação.'))));
        exit;
    }

    private function numero($v): float
    {
        return round((float) str_replace(',', '.', (string) $v), 3);
    }

    /* ── Painel ──────────────────────────────────────────────────────────── */

    /** O que precisa ser reposto (consolidado) e os pedidos em andamento. */
    public function painel()
    {
        $solic   = $this->model('SolicitacaoPeca');
        $compras = $this->model('PedidoCompra');

        $this->view('Compras/painelCompras', [
            'necessidades' => $solic->necessidades(),
            'pedidos'      => $compras->listar(['grupo' => 'abertos'], 40),
            'fornecedores' => $this->model('Fornecedor')->listar(),
            'contagem'     => $compras->contagemPorStatus(),
            'pendentes'    => $solic->contagemPorStatus(),
            'sucesso'      => $_GET['ok'] ?? null,
            'erro'         => $_GET['erro'] ?? null,
        ]);
    }

    /**
     * Reúne as necessidades marcadas num rascunho do fornecedor escolhido. Cada solicitação vira
     * uma linha própria (rastreável) com a quantidade que falta; qualquer excedente digitado vira
     * linha sem solicitação. O preço inicial é o último custo — o administrador ajusta no rascunho.
     */
    public function criarPedidoDeNecessidades()
    {
        $this->exigirPost();

        $compras = $this->model('PedidoCompra');
        $solic   = $this->model('SolicitacaoPeca');
        $selecionadas = (array) ($_POST['sel'] ?? []);

        if (!$selecionadas) {
            header('Location: ./painelCompras?erro=' . urlencode('Marque pelo menos uma necessidade.'));
            exit;
        }

        $r = $compras->criarRascunho((int) ($_POST['fornecedor_id'] ?? 0), [], $this->usuarioId());

        if (!$r['ok']) {
            header('Location: ./painelCompras?erro=' . urlencode((string) $r['erro']));
            exit;
        }

        $pedidoId = (int) $r['id'];
        $erros = [];

        foreach ($selecionadas as $i) {
            $linha = $_POST['linha'][$i] ?? [];
            $produtoId = (int) ($linha['produto_id'] ?? 0);

            if ($produtoId <= 0) {
                $erros[] = 'Uma peça sem cadastro não entra no pedido: cadastre o produto primeiro.';
                continue;
            }

            $custo = $this->conn_custo($produtoId);
            $preco = isset($linha['preco']) && $linha['preco'] !== '' ? $this->numero($linha['preco']) : $custo;
            $total = $this->numero($linha['quantidade'] ?? 0);
            $ids   = array_filter(array_map('intval', explode(',', (string) ($linha['solicitacoes'] ?? ''))));
            $coberto = 0.0;

            foreach ($ids as $solicitacaoId) {
                $s = $solic->getById($solicitacaoId);

                if (!$s || (int) $s['produto_id'] !== $produtoId) {
                    continue;
                }

                $falta = (float) $s['quantidade'] - (float) $s['quantidade_recebida'];
                $a = $compras->adicionarItem($pedidoId, $produtoId, $falta, $preco, $solicitacaoId, $this->usuarioId());

                if ($a['ok']) {
                    $coberto += $falta;
                } else {
                    $erros[] = $a['erro'];
                }
            }

            $extra = round($total - $coberto, 3);

            if ($extra > 0.0005) {
                $a = $compras->adicionarItem($pedidoId, $produtoId, $extra, $preco, null, $this->usuarioId());

                if (!$a['ok']) {
                    $erros[] = $a['erro'];
                }
            }
        }

        header('Location: ./viewPedidoCompra?id=' . $pedidoId . '&ok=rascunhocriado' . ($erros ? '&erro=fluxo&detalhe=' . urlencode(implode(' ', array_unique($erros))) : ''));
        exit;
    }

    /** Último custo do cadastro (sugestão de preço no rascunho). */
    private function conn_custo(int $produtoId): float
    {
        $stmt = $this->model('PedidoCompra')->conexao()->prepare('SELECT preco_custo FROM produtos WHERE id_produto = ?');
        $stmt->execute([$produtoId]);

        return (float) $stmt->fetchColumn();
    }

    /* ── Pedidos ─────────────────────────────────────────────────────────── */

    public function listarPedidos()
    {
        $compras = $this->model('PedidoCompra');

        $aba = (string) ($_GET['aba'] ?? 'abertos');
        $f = ['busca' => $_GET['busca'] ?? ''];

        if (isset(PedidoCompra::STATUS_LABEL[$aba])) {
            $f['status'] = $aba;
        } else {
            $f['grupo'] = $aba === 'todos' ? 'todos' : 'abertos';
            $aba = $f['grupo'];
        }

        $this->view('Compras/listPedidosCompra', [
            'pedidos'      => $compras->listar($f),
            'contagem'     => $compras->contagemPorStatus(),
            'aba'          => $aba,
            'busca'        => $f['busca'],
            'fornecedores' => $this->model('Fornecedor')->listar(),
            'sucesso'      => $_GET['ok'] ?? null,
        ]);
    }

    public function criarPedido()
    {
        $this->exigirPost('./listPedidosCompra');

        $r = $this->model('PedidoCompra')->criarRascunho((int) ($_POST['fornecedor_id'] ?? 0), $_POST, $this->usuarioId());

        if ($r['ok']) {
            header('Location: ./viewPedidoCompra?id=' . $r['id'] . '&ok=rascunhocriado');
            exit;
        }

        header('Location: ./listPedidosCompra?erro=' . urlencode((string) $r['erro']));
        exit;
    }

    public function visualizarPedido()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $compras = $this->model('PedidoCompra');
        $pedido = $id > 0 ? $compras->getById($id) : null;

        if (!$pedido) {
            $this->naoEncontrado('Pedido de compra', './listPedidosCompra');
        }

        $this->view('Compras/viewPedidoCompra', [
            'pedido'        => $pedido,
            'itens'         => $compras->itens($id),
            'recebimentos'  => $compras->recebimentos($id),
            'linhaDoTempo'  => $compras->linhaDoTempo($id),
            'produtos'      => $this->produtosAtivos(),
            'formas'        => PedidoCompra::FORMAS_PAGAMENTO,
            'cartoes'       => $this->cartoesAtivos(),
            'chaveRecebimento' => bin2hex(random_bytes(12)),
            'sucesso'       => $_GET['ok'] ?? null,
            'erro'          => ($_GET['erro'] ?? '') === 'fluxo' ? ($_GET['detalhe'] ?? 'Não foi possível concluir a ação.') : null,
        ]);
    }

    private function cartoesAtivos(): array
    {
        require_once __DIR__ . '/../models/CartaoCorporativo.php';
        require_once __DIR__ . '/../models/Despesa.php';

        return (new CartaoCorporativo())->listar();
    }

    private function produtosAtivos(): array
    {
        return $this->model('PedidoCompra')->conexao()
            ->query('SELECT id_produto, nome, codigo_interno, estoque_atual, preco_custo FROM produtos WHERE ativo = 1 ORDER BY nome')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function atualizarPedido()
    {
        $this->exigirPost();

        $id = (int) ($_POST['id_pedido'] ?? 0);
        $this->voltarAoPedido($id, $this->model('PedidoCompra')->atualizarDados($id, $_POST, $this->usuarioId()), 'salvo');
    }

    public function adicionarItem()
    {
        $this->exigirPost();

        $id = (int) ($_POST['id_pedido'] ?? 0);
        $r = $this->model('PedidoCompra')->adicionarItem(
            $id, (int) ($_POST['produto_id'] ?? 0), $this->numero($_POST['quantidade'] ?? 0), $this->numero($_POST['preco'] ?? 0), null, $this->usuarioId()
        );

        $this->voltarAoPedido($id, $r, 'itemincluido');
    }

    public function atualizarItem()
    {
        $this->exigirPost();

        $id = (int) ($_POST['id_pedido'] ?? 0);
        $r = $this->model('PedidoCompra')->atualizarItem($id, (int) ($_POST['id_item'] ?? 0), $this->numero($_POST['quantidade'] ?? 0), $this->numero($_POST['preco'] ?? 0));

        $this->voltarAoPedido($id, $r, 'salvo');
    }

    public function removerItem()
    {
        $this->exigirPost();

        $id = (int) ($_POST['id_pedido'] ?? 0);
        $this->voltarAoPedido($id, $this->model('PedidoCompra')->removerItem($id, (int) ($_POST['id_item'] ?? 0)), 'itemremovido');
    }

    public function realizar()
    {
        $this->exigirPost();

        $id = (int) ($_POST['id_pedido'] ?? 0);
        $r = $this->model('PedidoCompra')->realizar($id, $this->usuarioId());

        $this->voltarAoPedido($id, $r, !empty($r['repetido']) ? 'jarealizado' : 'realizado');
    }

    public function cancelar()
    {
        $this->exigirPost();

        $id = (int) ($_POST['id_pedido'] ?? 0);
        $this->voltarAoPedido($id, $this->model('PedidoCompra')->cancelar($id, $this->usuarioId(), (string) ($_POST['motivo'] ?? '')), 'cancelado');
    }

    public function encerrar()
    {
        $this->exigirPost();

        $id = (int) ($_POST['id_pedido'] ?? 0);
        $this->voltarAoPedido($id, $this->model('PedidoCompra')->encerrarComSaldo($id, $this->usuarioId(), (string) ($_POST['motivo'] ?? '')), 'encerrado');
    }

    public function receber()
    {
        $this->exigirPost();

        $id = (int) ($_POST['id_pedido'] ?? 0);
        $linhas = [];

        foreach ((array) ($_POST['recebido'] ?? []) as $itemId => $l) {
            $linhas[] = ['item_id' => (int) $itemId, 'quantidade' => $l['quantidade'] ?? 0, 'custo' => $l['custo'] ?? ''];
        }

        $r = $this->model('PedidoCompra')->receber($id, $linhas, [
            'chave'            => (string) ($_POST['chave'] ?? ''),
            'documento_fiscal' => $_POST['documento_fiscal'] ?? '',
            'observacao'       => $_POST['observacao'] ?? '',
        ], $this->usuarioId());

        $this->voltarAoPedido($id, $r, !empty($r['repetido']) ? 'recebimentorepetido' : 'recebido');
    }

    public function estornarRecebimento()
    {
        $this->exigirPost();

        $compras = $this->model('PedidoCompra');
        $r = $compras->estornarRecebimento((int) ($_POST['id_recebimento'] ?? 0), $this->usuarioId(), (string) ($_POST['justificativa'] ?? ''));

        $this->voltarAoPedido((int) ($r['pedido_id'] ?? $_POST['id_pedido'] ?? 0), $r, 'estornado');
    }

    /* ── Fornecedores ────────────────────────────────────────────────────── */

    public function listarFornecedores()
    {
        $this->view('Compras/listFornecedores', [
            'fornecedores' => $this->model('Fornecedor')->listar(true, (string) ($_GET['busca'] ?? '')),
            'busca'        => (string) ($_GET['busca'] ?? ''),
            'sucesso'      => $_GET['ok'] ?? null,
        ]);
    }

    public function salvarFornecedor()
    {
        $model = $this->model('Fornecedor');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->view('Compras/saveFornecedor', ['fornecedor' => [], 'error' => null, 'novo' => true]);
            return;
        }

        $id = $model->create($_POST);

        if ($id !== false) {
            header('Location: ./listFornecedores?ok=criado');
            exit;
        }

        $this->view('Compras/saveFornecedor', ['fornecedor' => $_POST, 'error' => $model->getLastError(), 'novo' => true]);
    }

    public function editarFornecedor()
    {
        $model = $this->model('Fornecedor');
        $id = (int) ($_GET['id'] ?? $_POST['id_fornecedor'] ?? 0);
        $f = $id > 0 ? $model->getById($id) : null;

        if (!$f) {
            $this->naoEncontrado('Fornecedor', './listFornecedores');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if ($model->update($id, $_POST)) {
                header('Location: ./listFornecedores?ok=salvo');
                exit;
            }

            $this->view('Compras/saveFornecedor', ['fornecedor' => $_POST + $f, 'error' => $model->getLastError(), 'novo' => false]);
            return;
        }

        $this->view('Compras/saveFornecedor', ['fornecedor' => $f, 'error' => null, 'novo' => false]);
    }

    public function alternarFornecedor()
    {
        $this->exigirPost('./listFornecedores');

        $model = $this->model('Fornecedor');
        $id = (int) ($_POST['id_fornecedor'] ?? 0);
        $f = $model->getById($id);

        if ($f) {
            $model->definirAtivo($id, !(int) $f['ativo']);
        }

        header('Location: ./listFornecedores?ok=' . ($f && (int) $f['ativo'] ? 'desativado' : 'reativado'));
        exit;
    }
}
