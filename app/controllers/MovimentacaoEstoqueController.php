<?php

class MovimentacaoEstoqueController extends Controller
{
    private int $itensPorPagina = 8;

    // ── LIST ────────────────────────────────────────────────────────
    public function list()
    {
        $model       = $this->model('MovimentacaoEstoque');
        $filtros     = $_GET;
        $paginaAtual = max(1, (int)($_GET['pagina'] ?? 1));
        $offset      = ($paginaAtual - 1) * $this->itensPorPagina;

        $total        = $model->count($filtros);
        $totalPaginas = (int) ceil($total / $this->itensPorPagina);

        $this->view('MovimentacaoEstoque/listMovimentacaoEstoque', [
            'MovimentacaoEstoque'      => $model->list($this->itensPorPagina, $offset, $filtros),
            'totalmovimentacaoestoque' => $total,
            'paginaAtual'              => $paginaAtual,
            'totalPaginas'             => $totalPaginas,
        ]);
    }

    // ── SAVE: GET abre formulário / POST registra movimentação ───────
    public function save()
    {
        $this->requireRole(Permissao::PAPEIS_EQUIPE);

        // As constantes de forma de pagamento são lidas antes de qualquer
        // $this->model(), que é quem normalmente carrega a classe.
        require_once __DIR__ . '/../models/MovimentacaoCaixa.php';

        $model = $this->model('MovimentacaoEstoque');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $validator = (new Validator($_POST))
                ->required('produto_id', 'Produto')
                ->required('tipo', 'Tipo')
                ->in('tipo', ['ENTRADA', 'SAIDA', 'AJUSTE'], 'Tipo')
                ->required('quantidade', 'Quantidade')
                ->numeric('quantidade', 'Quantidade');

            if ($validator->fails()) {
                $this->view('MovimentacaoEstoque/saveMovimentacaoEstoque', [
                    'produtos' => $model->getProdutosAtivos(),
                    'error'    => $validator->firstError(),
                    'old'      => $_POST,
                ]);
                return;
            }

            $dados = $_POST;
            $dados['usuario_id'] = $_SESSION['user_id_usuario'] ?? null;
            $dados['origem']     = 'MANUAL';

            // Forma de pagamento só é guardada na compra (entrada), e só se
            // for uma das formas aceitas — o resto vira nulo em vez de erro.
            if (($dados['tipo'] ?? '') !== 'ENTRADA'
                || !in_array($dados['forma_pagamento'] ?? '', MovimentacaoCaixa::FORMAS, true)) {
                $dados['forma_pagamento'] = null;
            }

            $sucesso = $model->save($dados);

            if ($sucesso) {
                $this->lancarCompraNoCaixa($model->ultimoId(), $dados);

                header('Location: ./listMovimentacaoEstoque');
                exit;
            }

            $erroDetalhado = method_exists($model, 'getLastError')
                ? $model->getLastError()
                : '';

            $this->view('MovimentacaoEstoque/saveMovimentacaoEstoque', [
                'produtos' => $model->getProdutosAtivos(),
                'error'    => !empty($erroDetalhado)
                    ? 'Erro ao registrar movimentação: ' . $erroDetalhado
                    : 'Erro ao registrar movimentação.',
                'old'      => $_POST,
            ]);
            return;
        }

        $this->view('MovimentacaoEstoque/saveMovimentacaoEstoque', [
            'produtos' => $model->getProdutosAtivos(),
        ]);
    }

    /**
     * Compra de peça sai do caixa como SAÍDA na categoria Peças.
     *
     * Só acontece quando a pessoa deixou marcado "lançar no caixa" no
     * formulário — quem já lançou a compra na mão desmarca e não duplica.
     * O id do lançamento fica gravado na própria movimentação de estoque:
     * é o vínculo que permite ver de onde veio a saída e impede que a
     * mesma compra seja lançada duas vezes.
     *
     * Falha aqui não desfaz a entrada de estoque: a peça já está na
     * prateleira, e caixa fechado é motivo para lançar depois, não para
     * perder o registro do estoque.
     */
    private function lancarCompraNoCaixa(int $movimentacaoId, array $dados): void
    {
        if ($movimentacaoId <= 0
            || ($dados['tipo'] ?? '') !== 'ENTRADA'
            || empty($dados['lancar_no_caixa'])
            || empty($dados['forma_pagamento'])) {
            return;
        }

        try {
            $movCaixa = $this->model('MovimentacaoCaixa');
            $caixa    = $this->model('Caixa');

            $aberto = $caixa->getCaixaAberto();
            if (!$aberto) {
                return;
            }

            $model     = $this->model('MovimentacaoEstoque');
            $movimento = $model->findById($movimentacaoId);

            // O valor da compra é quantidade x custo unitário registrado.
            $valor = (float) ($movimento['quantidade'] ?? 0) * (float) ($movimento['custo_unitario'] ?? 0);
            if ($valor <= 0) {
                return;
            }

            $descricao = 'Compra de estoque: ' . ($movimento['produto_nome'] ?? 'peça');

            $idCaixa = $movCaixa->registrar(
                $aberto['id_caixa'],
                'SAIDA',
                $valor,
                null,
                'PECAS',
                $descricao,
                $_SESSION['user_id_usuario'] ?? null,
                'MANUAL',
                null
            );

            if ($idCaixa) {
                $model->vincularAoCaixa($movimentacaoId, (int) $idCaixa);
            }
        } catch (\Throwable $e) {
            error_log('[movimentação de estoque] Falha ao lançar compra no caixa: ' . $e->getMessage());
        }
    }

    // ── ESTORNO ─────────────────────────────────────────────────────
    // O histórico de estoque não se edita nem se apaga: o erro vira uma nova
    // movimentação que o desfaz, com motivo e autoria (ver MovimentacaoEstoque).
    public function estornar()
    {
        // POST em vez de GET: sem isso, um <img>/pré-carregamento em outra
        // página dispararia o estorno só de o ADMIN estar logado.
        $id = ($_SERVER['REQUEST_METHOD'] === 'POST') ? (int)($_POST['id'] ?? 0) : 0;

        if (!$id) {
            header('Location: ./listMovimentacaoEstoque');
            exit;
        }

        $r = $this->model('MovimentacaoEstoque')->estornar(
            $id,
            $_SESSION['user_id_usuario'] ?? null,
            (string) ($_POST['justificativa'] ?? '')
        );

        header('Location: ./listMovimentacaoEstoque?' . ($r['ok']
            ? 'ok=' . ($r['repetido'] ? 'jaestornada' : 'estornada')
            : 'erro=estorno&detalhe=' . urlencode((string) $r['erro'])));
        exit;
    }
}
