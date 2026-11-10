<?php

/** Entrada de mercadoria, ajuste e estorno: o saldo sempre fecha com o livro e nunca fica negativo. */
final class EstoqueTest extends Cenario
{
    private Estoque $e;
    private int $peca;

    protected function setUp(): void
    {
        parent::setUp();
        $this->e = new Estoque();
        $this->peca = (new Produto())->salvar(['tipo' => 'PECA', 'nome' => 'Pastilha', 'preco_venda' => '120', 'preco_custo' => '60', 'estoque_inicial' => '1']);
        $this->db->exec("INSERT INTO fornecedores (id, oficina_id, nome) VALUES (101, 1, 'Distribuidora A'), (201, 2, 'Distribuidora B')");
    }

    private function produto(): array
    {
        return (new Produto())->buscar($this->peca);
    }

    private function livroFecha(): void
    {
        $soma = $this->db->query("SELECT CAST(SUM(delta) AS DECIMAL(10,3)) FROM movimentacoes_estoque WHERE produto_id = {$this->peca}")->fetchColumn();
        $this->assertSame($this->produto()['estoque_atual'], $soma);
    }

    private function entrada(array $extra = []): void
    {
        $this->e->entrada($extra + ['fornecedor_id' => 101, 'documento' => '123', 'atualizar_custo' => '1',
            'itens' => [['produto_id' => $this->peca, 'quantidade' => '4', 'custo_unitario' => '55,50']]]);
    }

    public function testEntradaSomaAtualizaCustoERegistraFornecedor(): void
    {
        $this->entrada();
        $p = $this->produto();

        $this->assertSame('5.000', $p['estoque_atual']);
        $this->assertSame('55.50', $p['preco_custo']);
        $mov = $this->db->query("SELECT * FROM movimentacoes_estoque WHERE motivo = 'COMPRA'")->fetch();
        $this->assertSame('123', $mov['documento']);
        $this->assertSame(101, (int) $mov['fornecedor_id']);
        $this->livroFecha();
    }

    public function testEntradaPodeLancarContaAPagar(): void
    {
        $this->entrada(['gerar_conta' => '1', 'vencimento' => '2026-12-10', 'forma' => 'BOLETO']);
        $l = $this->db->query('SELECT * FROM lancamentos')->fetch();

        $this->assertSame('PAGAR', $l['tipo']);
        $this->assertSame('222.00', $l['valor']);
        $this->assertSame('ABERTO', $l['status']);
        $this->assertSame(101, (int) $l['fornecedor_id']);
        $this->assertStringContainsString('NF 123', $l['descricao']);
    }

    public function testEntradaRecusaServicoPecaDeOutraOficinaEFornecedorDeOutraOficina(): void
    {
        $servico = (new Produto())->salvar(['tipo' => 'SERVICO', 'nome' => 'Alinhamento', 'preco_venda' => '80']);
        foreach ([
            ['itens' => [['produto_id' => $servico, 'quantidade' => '1']]],
            ['itens' => []],
            ['fornecedor_id' => 201, 'itens' => [['produto_id' => $this->peca, 'quantidade' => '1']]],
        ] as $dados) {
            try {
                $this->e->entrada($dados);
                $this->fail('Entrada inválida aceita.');
            } catch (DomainException $ex) {
                $this->assertNotSame('', $ex->getMessage());
            }
        }
        $this->assertSame('1.000', $this->produto()['estoque_atual']);
    }

    public function testAjustePelaContagem(): void
    {
        $delta = $this->e->ajustar($this->peca, '3', 'Inventário');
        $this->assertSame(2.0, $delta);
        $this->assertSame('3.000', $this->produto()['estoque_atual']);

        $this->e->ajustar($this->peca, '0', 'Avaria');
        $this->assertSame('0.000', $this->produto()['estoque_atual']);
        $this->livroFecha();

        foreach ([['0', 'Igual'], ['2', ''], ['-1', 'Negativo']] as [$contado, $motivo]) {
            try {
                $this->e->ajustar($this->peca, $contado, $motivo);
                $this->fail('Ajuste inválido aceito.');
            } catch (DomainException $ex) {
            }
        }
    }

    public function testEstornoDeEntradaReduzOuCancelaAContaEmAberto(): void
    {
        $this->entrada(['gerar_conta' => '1', 'vencimento' => '2026-12-10']);
        $mov = (int) $this->db->query("SELECT id FROM movimentacoes_estoque WHERE motivo = 'COMPRA'")->fetchColumn();

        $this->e->estornar($mov, 'Nota lançada em duplicidade');

        $this->assertSame('1.000', $this->produto()['estoque_atual']);
        $this->assertSame('CANCELADO', $this->db->query('SELECT status FROM lancamentos')->fetchColumn());
        $this->livroFecha();

        $this->expectExceptionMessage('já foi estornado');
        $this->e->estornar($mov, 'De novo');
    }

    public function testEstornoNaoDeixaEstoqueNegativoNemMexeEmContaPaga(): void
    {
        $this->entrada(['gerar_conta' => '1', 'vencimento' => '2026-12-10']);
        $mov = (int) $this->db->query("SELECT id FROM movimentacoes_estoque WHERE motivo = 'COMPRA'")->fetchColumn();

        $this->e->ajustar($this->peca, '2', 'Usei três peças');
        try {
            $this->e->estornar($mov, 'Erro');
            $this->fail('Estorno deixaria o saldo negativo.');
        } catch (DomainException $ex) {
            $this->assertStringContainsString('negativo', $ex->getMessage());
        }

        $this->e->ajustar($this->peca, '10', 'Recontagem');
        $this->db->exec("UPDATE lancamentos SET valor_pago = 100, status = 'PARCIAL'");
        $this->expectExceptionMessage('já tem pagamento');
        $this->e->estornar($mov, 'Erro');
    }

    public function testMovimentoDeOsNaoSeEstornaAqui(): void
    {
        $os = new OrdemServico();
        $id = $os->criar(['cliente_id' => 101, 'veiculo_id' => 101]);
        $os->adicionarItem($id, ['produto_id' => $this->peca]);
        $os->aprovar($id, '', null);
        $os->iniciar($id);
        $mov = (int) $this->db->query("SELECT id FROM movimentacoes_estoque WHERE motivo = 'SAIDA_OS'")->fetchColumn();

        $this->expectExceptionMessage('própria OS');
        $this->e->estornar($mov, 'Tentativa');
    }

    public function testLivroEResumoSaoDaOficina(): void
    {
        $this->entrada();
        $p = new Paginacao();
        $this->assertCount(2, $this->e->livro([], $p), 'estoque inicial + entrada');
        $this->assertSame(1, (int) $this->e->resumo()['pecas']);
        $this->assertSame('277.50', number_format((float) $this->e->resumo()['valor_custo'], 2, '.', ''), '5 peças × R$ 55,50');

        $this->entrarComo(self::ADMIN_B);
        $this->assertCount(0, (new Estoque())->livro([], new Paginacao()));
        $this->expectException(ErroHttp::class);
        (new Estoque())->estornar((int) $this->db->query('SELECT MAX(id) FROM movimentacoes_estoque')->fetchColumn(), 'Invasão');
    }
}
