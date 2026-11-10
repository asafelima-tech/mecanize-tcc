<?php

/** Estoque: livro de movimentações, entrada de mercadoria, ajuste por contagem e estorno. Só ADMIN. */
final class EstoqueController extends Controller
{
    public function livro(): void
    {
        $p = new Paginacao();
        $f = [];
        foreach (['busca', 'motivo', 'produto', 'de', 'ate'] as $k) {
            $f[$k] = trim((string) ($_GET[$k] ?? ''));
        }
        $m = new Estoque();
        $this->tela('estoque/livro', ['movs' => $m->livro($f, $p), 'f' => $f, 'p' => $p, 'resumo' => $m->resumo()]);
    }

    public function entrada(): void
    {
        if (!$this->ehPost()) {
            $this->telaEntrada(['itens' => [['produto_id' => (int) ($_GET['produto'] ?? 0)]], 'atualizar_custo' => 1]);
            return;
        }

        try {
            $n = (new Estoque())->entrada($_POST);
        } catch (DomainException $e) {
            $this->telaEntrada($_POST, $e->getMessage());
            return;
        }
        $this->avisar("Entrada registrada: {$n} peça(s) no estoque." . (!empty($_POST['gerar_conta']) ? ' A conta a pagar foi lançada.' : ''));
        $this->redirecionar('estoque');
    }

    public function ajuste(): void
    {
        $produto = (int) ($_GET['produto'] ?? $_POST['produto_id'] ?? 0);
        if (!$this->ehPost()) {
            $this->tela('estoque/ajuste', ['pecas' => (new Estoque())->pecas(), 'd' => ['produto_id' => $produto]]);
            return;
        }

        try {
            $delta = (new Estoque())->ajustar($produto, $this->post('contado'), $this->post('justificativa'));
        } catch (DomainException $e) {
            $this->tela('estoque/ajuste', ['pecas' => (new Estoque())->pecas(), 'd' => $_POST, 'erro' => $e->getMessage()]);
            return;
        }
        $this->avisar('Estoque ajustado (' . ($delta > 0 ? '+' : '') . rtrim(rtrim(number_format($delta, 3, ',', '.'), '0'), ',') . ').');
        $this->redirecionar('produto', ['id' => $produto]);
    }

    public function estornar(): void
    {
        try {
            (new Estoque())->estornar($this->idDaUrl(), $this->post('motivo'));
            $this->avisar('Movimento estornado.');
        } catch (DomainException $e) {
            $this->avisar($e->getMessage(), 'erro');
        }
        $this->redirecionar('estoque');
    }

    private function telaEntrada(array $d, ?string $erro = null): void
    {
        $this->tela('estoque/entrada', [
            'd' => $d + ['vencimento' => date('Y-m-d', strtotime('+30 days'))], 'erro' => $erro,
            'pecas' => (new Estoque())->pecas(), 'fornecedores' => (new Fornecedor())->opcoes(),
        ]);
    }
}
