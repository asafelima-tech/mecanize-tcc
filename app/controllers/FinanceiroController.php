<?php

require_once __DIR__ . '/../models/Financeiro.php';
require_once __DIR__ . '/../models/ContaReceber.php';
require_once __DIR__ . '/../models/ContaPagar.php';
require_once __DIR__ . '/../models/Despesa.php';
require_once __DIR__ . '/../models/CartaoCorporativo.php';
require_once __DIR__ . '/../models/FaturaCartao.php';
require_once __DIR__ . '/../models/ComprovanteFinanceiro.php';

/**
 * Financeiro (só administrador — a matriz de rotas barra os demais): painel de indicadores,
 * contas a receber e a pagar, despesas, cartões, faturas e comprovantes.
 *
 * A tela só traduz o formulário; as regras (parcelas, faturas, idempotência, estorno) estão nos
 * models e são testadas lá.
 */
class FinanceiroController extends Controller
{
    private function usuarioId(): int
    {
        return (int) ($_SESSION['user_id_usuario'] ?? 0);
    }

    private function exigirPost(string $voltar): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ' . $voltar);
            exit;
        }
    }

    private function voltarDespesa(int $id, array $r, string $codigoOk): void
    {
        header('Location: ./viewDespesa?id=' . $id . '&' . (!empty($r['ok'])
            ? 'ok=' . $codigoOk
            : 'erro=fluxo&detalhe=' . urlencode((string) ($r['erro'] ?? 'Não foi possível concluir a ação.'))));
        exit;
    }

    /* ── Painel ──────────────────────────────────────────────────────────── */

    /** @return array{0:string,1:string,2:string} início, fim e a opção escolhida */
    private function periodo(): array
    {
        $opcao = (string) ($_GET['periodo'] ?? 'mes');
        $hoje = date('Y-m-d');

        switch ($opcao) {
            case 'mes_passado':
                return [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month')), $opcao];
            case '30d':
                return [date('Y-m-d', strtotime('-29 days')), $hoje, $opcao];
            case 'ano':
                return [date('Y-01-01'), $hoje, $opcao];
            case 'custom':
                $de = $_GET['de'] ?? '';
                $ate = $_GET['ate'] ?? '';
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $de) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate) && $de <= $ate) {
                    return [$de, $ate, $opcao];
                }
                // intervalo inválido: cai no mês atual
            default:
                return [date('Y-m-01'), $hoje, 'mes'];
        }
    }

    public function painel()
    {
        [$inicio, $fim, $opcao] = $this->periodo();
        $fin = new Financeiro();

        $pagar = new ContaPagar();

        $this->view('Financeiro/painelFinanceiro', [
            'ind'         => $fin->indicadores($inicio, $fim),
            'fluxo'       => $fin->fluxoProjetado(6),
            'vencimentos' => $pagar->proximosVencimentos(8),
            'receberProx' => (new ContaReceber())->listar(['situacao' => 'abertas'], 8),
            'opcao'       => $opcao,
            'inicio'      => $inicio,
            'fim'         => $fim,
        ]);
    }

    /* ── Contas a receber ────────────────────────────────────────────────── */

    public function contasReceber()
    {
        $situacao = in_array($_GET['situacao'] ?? '', ['abertas', 'vencidas', 'recebidas', 'todas'], true) ? $_GET['situacao'] : 'abertas';

        $f = ['situacao' => $situacao];
        foreach (['de', 'ate', 'forma', 'busca'] as $campo) {
            if (!empty($_GET[$campo])) {
                $f[$campo] = trim((string) $_GET[$campo]);
            }
        }

        $model = new ContaReceber();

        $this->view('Financeiro/listContasReceber', [
            'parcelas' => $model->listar($f),
            'totais'   => $model->totais(),
            'filtros'  => $f + ['situacao' => $situacao],
        ]);
    }

    /* ── Contas a pagar e despesas ───────────────────────────────────────── */

    public function contasPagar()
    {
        $situacao = in_array($_GET['situacao'] ?? '', ['abertas', 'vencidas', 'pagas', 'todas'], true) ? $_GET['situacao'] : 'abertas';

        $f = ['situacao' => $situacao];
        foreach (['de', 'ate', 'busca'] as $campo) {
            if (!empty($_GET[$campo])) {
                $f[$campo] = trim((string) $_GET[$campo]);
            }
        }

        foreach (['fornecedor_id', 'cartao_id'] as $campo) {
            if (!empty($_GET[$campo])) {
                $f[$campo] = (int) $_GET[$campo];
            }
        }

        $pagar = new ContaPagar();

        $this->view('Financeiro/listContasPagar', [
            'parcelas'     => $pagar->listarParcelas($f),
            'totais'       => $pagar->totais(),
            'filtros'      => $f + ['situacao' => $situacao],
            'fornecedores' => $this->model('Fornecedor')->listar(),
            'cartoes'      => (new CartaoCorporativo())->listar(true),
            'chaveRapida'  => bin2hex(random_bytes(12)),
            'sucesso'      => $_GET['ok'] ?? null,
            'erro'         => $_GET['erro'] ?? null,
        ]);
    }

    public function salvarDespesa()
    {
        $dados = [
            'fornecedores' => $this->model('Fornecedor')->listar(),
            'cartoes'      => (new CartaoCorporativo())->listar(),
            'categorias'   => Despesa::CATEGORIAS,
            'meios'        => Despesa::MEIOS,
            'chave'        => bin2hex(random_bytes(12)),
            'old'          => [],
            'error'        => null,
        ];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $dados['old'] = array_filter(['fornecedor_id' => $_GET['fornecedor_id'] ?? null, 'categoria' => $_GET['categoria'] ?? null]);
            $this->view('Financeiro/saveDespesa', $dados);
            return;
        }

        $despesas = new Despesa();
        $r = $despesas->criar($_POST, $this->usuarioId());

        if (!$r['ok']) {
            $dados['error'] = $r['erro'];
            $dados['old']   = $_POST;
            $dados['chave'] = $_POST['chave'] ?? $dados['chave']; // reenvio do mesmo formulário mantém a chave
            $this->view('Financeiro/saveDespesa', $dados);
            return;
        }

        // Já paga na hora (à vista, pelo banco ou pelo caixa): é o pagamento da primeira parcela, à parte.
        if (!empty($_POST['pago_agora']) && ($_POST['meio_pagamento'] ?? '') !== 'CARTAO_CREDITO' && !$r['repetido']) {
            $primeira = $despesas->parcelas($r['id'])[0] ?? null;

            if ($primeira) {
                $pg = (new ContaPagar())->pagarParcela((int) $primeira['id_conta_parcela'], [
                    'forma' => $_POST['forma_pagamento'] ?? ($_POST['meio_pagamento'] ?? ''),
                    'chave' => 'NOVA:' . ($_POST['chave'] ?? bin2hex(random_bytes(8))),
                    'lancar_no_caixa' => !empty($_POST['lancar_no_caixa']),
                ], $this->usuarioId());

                if (!$pg['ok']) {
                    header('Location: ./viewDespesa?id=' . $r['id'] . '&ok=registrada&erro=fluxo&detalhe=' . urlencode('Despesa registrada, mas o pagamento não foi feito: ' . $pg['erro']));
                    exit;
                }
            }
        }

        header('Location: ./viewDespesa?id=' . $r['id'] . '&ok=' . ($r['repetido'] ? 'jaexistia' : 'registrada'));
        exit;
    }

    public function visualizarDespesa()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $despesas = new Despesa();
        $d = $id > 0 ? $despesas->getById($id) : null;

        if (!$d) {
            $this->naoEncontrado('Despesa', './listContasPagar');
        }

        $this->view('Financeiro/viewDespesa', [
            'despesa'      => $d,
            'parcelas'     => $despesas->parcelas($id),
            'pagamentos'   => $despesas->pagamentos($id),
            'comprovantes' => (new ComprovanteFinanceiro())->daDespesa($id),
            'linhaDoTempo' => $despesas->linhaDoTempo($id),
            'formas'       => ContaPagar::FORMAS,
            'sucesso'      => $_GET['ok'] ?? null,
            'erro'         => ($_GET['erro'] ?? '') === 'fluxo' ? ($_GET['detalhe'] ?? 'Não foi possível concluir a ação.') : null,
        ]);
    }

    public function pagarParcela()
    {
        $this->exigirPost('./listContasPagar');

        $despesaId = (int) ($_POST['id_despesa'] ?? 0);
        $r = (new ContaPagar())->pagarParcela((int) ($_POST['id_parcela'] ?? 0), [
            'forma'           => $_POST['forma'] ?? '',
            'chave'           => $_POST['chave'] ?? '',
            'data'            => $_POST['data'] ?? '',
            'lancar_no_caixa' => !empty($_POST['lancar_no_caixa']),
            'observacao'      => $_POST['observacao'] ?? '',
        ], $this->usuarioId());

        if ($despesaId > 0) {
            $this->voltarDespesa($despesaId, $r, !empty($r['repetido']) ? 'japago' : 'pago');
        }

        header('Location: ./listContasPagar?' . ($r['ok'] ? 'ok=pago' : 'erro=' . urlencode((string) $r['erro'])));
        exit;
    }

    public function estornarPagamento()
    {
        $this->exigirPost('./listContasPagar');

        $despesaId = (int) ($_POST['id_despesa'] ?? 0);
        $faturaId = (int) ($_POST['id_fatura'] ?? 0);
        $r = (new ContaPagar())->estornarPagamento((int) ($_POST['id_pagamento'] ?? 0), (string) ($_POST['justificativa'] ?? ''), $this->usuarioId());

        if ($faturaId > 0) {
            header('Location: ./viewFatura?id=' . $faturaId . '&' . ($r['ok'] ? 'ok=estornado' : 'erro=fluxo&detalhe=' . urlencode((string) $r['erro'])));
            exit;
        }

        $this->voltarDespesa($despesaId, $r, 'estornado');
    }

    public function cancelarDespesa()
    {
        $this->exigirPost('./listContasPagar');

        $id = (int) ($_POST['id_despesa'] ?? 0);
        $this->voltarDespesa($id, (new Despesa())->cancelar($id, $this->usuarioId(), (string) ($_POST['motivo'] ?? '')), 'cancelada');
    }

    /** Gera a conta a pagar de um pedido de compra (uma por pedido). */
    public function lancarContaPedido()
    {
        $this->exigirPost('./listPedidosCompra');

        $pedidoId = (int) ($_POST['id_pedido'] ?? 0);
        $r = (new Despesa())->criarDoPedido($pedidoId, $_POST, $this->usuarioId());

        if ($r['ok']) {
            header('Location: ./viewDespesa?id=' . $r['id'] . '&ok=' . ($r['repetido'] ? 'jaexistia' : 'registrada'));
            exit;
        }

        header('Location: ./viewPedidoCompra?id=' . $pedidoId . '&erro=fluxo&detalhe=' . urlencode((string) $r['erro']));
        exit;
    }

    /* ── Cartões e faturas ───────────────────────────────────────────────── */

    public function listarCartoes()
    {
        $this->view('Financeiro/listCartoes', [
            'cartoes' => (new CartaoCorporativo())->listar(true),
            'sucesso' => $_GET['ok'] ?? null,
        ]);
    }

    public function salvarCartao()
    {
        $model = new CartaoCorporativo();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->view('Financeiro/saveCartao', ['cartao' => [], 'error' => null, 'novo' => true, 'bandeiras' => CartaoCorporativo::BANDEIRAS]);
            return;
        }

        if ($model->create($_POST) !== false) {
            header('Location: ./listCartoes?ok=criado');
            exit;
        }

        $this->view('Financeiro/saveCartao', ['cartao' => $_POST, 'error' => $model->getLastError(), 'novo' => true, 'bandeiras' => CartaoCorporativo::BANDEIRAS]);
    }

    public function editarCartao()
    {
        $model = new CartaoCorporativo();
        $id = (int) ($_GET['id'] ?? $_POST['id_cartao'] ?? 0);
        $c = $id > 0 ? $model->getById($id) : null;

        if (!$c) {
            $this->naoEncontrado('Cartão', './listCartoes');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if ($model->update($id, $_POST)) {
                header('Location: ./listCartoes?ok=salvo');
                exit;
            }

            $this->view('Financeiro/saveCartao', ['cartao' => $_POST + $c, 'error' => $model->getLastError(), 'novo' => false, 'bandeiras' => CartaoCorporativo::BANDEIRAS]);
            return;
        }

        $this->view('Financeiro/saveCartao', ['cartao' => $c, 'error' => null, 'novo' => false, 'bandeiras' => CartaoCorporativo::BANDEIRAS]);
    }

    public function alternarCartao()
    {
        $this->exigirPost('./listCartoes');

        $model = new CartaoCorporativo();
        $id = (int) ($_POST['id_cartao'] ?? 0);
        $c = $model->getById($id);

        if ($c) {
            $model->definirAtivo($id, !(int) $c['ativo']);
        }

        header('Location: ./listCartoes?ok=' . ($c && (int) $c['ativo'] ? 'desativado' : 'reativado'));
        exit;
    }

    public function visualizarCartao()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $c = $id > 0 ? (new CartaoCorporativo())->getById($id) : null;

        if (!$c) {
            $this->naoEncontrado('Cartão', './listCartoes');
        }

        $this->view('Financeiro/viewCartao', ['cartao' => $c, 'faturas' => (new FaturaCartao())->doCartao($id)]);
    }

    public function visualizarFatura()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $faturas = new FaturaCartao();
        $f = $id > 0 ? $faturas->getById($id) : null;

        if (!$f) {
            $this->naoEncontrado('Fatura', './listCartoes');
        }

        $comprovantes = new ComprovanteFinanceiro();
        $pagamentos = $faturas->pagamentos($id);

        foreach ($pagamentos as &$pg) {
            $pg['comprovantes'] = $comprovantes->doPagamento((int) $pg['id_pagamento']);
        }
        unset($pg);

        $this->view('Financeiro/viewFatura', [
            'fatura'     => $f,
            'compras'    => $faturas->compras($id),
            'pagamentos' => $pagamentos,
            'formas'     => ContaPagar::FORMAS,
            'chave'      => bin2hex(random_bytes(12)),
            'sucesso'    => $_GET['ok'] ?? null,
            'erro'       => ($_GET['erro'] ?? '') === 'fluxo' ? ($_GET['detalhe'] ?? 'Não foi possível concluir a ação.') : null,
        ]);
    }

    public function pagarFatura()
    {
        $this->exigirPost('./listCartoes');

        $faturaId = (int) ($_POST['id_fatura'] ?? 0);
        $r = (new ContaPagar())->pagarFatura($faturaId, [
            'forma'           => $_POST['forma'] ?? '',
            'chave'           => $_POST['chave'] ?? '',
            'data'            => $_POST['data'] ?? '',
            'lancar_no_caixa' => !empty($_POST['lancar_no_caixa']),
            'observacao'      => $_POST['observacao'] ?? '',
        ], $this->usuarioId());

        header('Location: ./viewFatura?id=' . $faturaId . '&' . ($r['ok'] ? 'ok=' . (!empty($r['repetido']) ? 'japaga' : 'paga') : 'erro=fluxo&detalhe=' . urlencode((string) $r['erro'])));
        exit;
    }

    /* ── Comprovantes ────────────────────────────────────────────────────── */

    public function enviarComprovante()
    {
        $this->exigirPost('./listContasPagar');

        $despesaId = (int) ($_POST['id_despesa'] ?? 0);
        $pagamentoId = (int) ($_POST['id_pagamento'] ?? 0);
        $faturaId = (int) ($_POST['id_fatura'] ?? 0);

        $r = (new ComprovanteFinanceiro())->anexar(
            $pagamentoId > 0 ? ['pagamento_id' => $pagamentoId] : ['despesa_id' => $despesaId],
            $_FILES['arquivo'] ?? ['error' => UPLOAD_ERR_NO_FILE],
            $this->usuarioId()
        );

        if ($faturaId > 0) {
            header('Location: ./viewFatura?id=' . $faturaId . '&' . ($r['ok'] ? 'ok=comprovante' : 'erro=fluxo&detalhe=' . urlencode((string) $r['erro'])));
            exit;
        }

        $this->voltarDespesa($despesaId, $r, 'comprovante');
    }

    /** Entrega o comprovante só a quem tem acesso (administrador), com o tipo decidido no envio. */
    public function baixarComprovante()
    {
        $c = (new ComprovanteFinanceiro())->getById((int) ($_GET['id'] ?? 0));
        $arquivo = $c ? Armazenamento::caminho($c['caminho']) : null;

        if (!$c || !$arquivo) {
            $this->naoEncontrado('Comprovante', './listContasPagar');
        }

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: ' . $c['mime']);
        header('Content-Length: ' . filesize($arquivo));
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; sandbox');
        header('Cache-Control: private, no-store');
        header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._\- ]/', '_', $c['nome_original']) . '"');
        readfile($arquivo);
        exit;
    }
}
