<?php

/** OS com orçamento integrado: fluxo completo, valores históricos, estoque, comissão e isolamento. */
final class OrdemServicoTest extends Cenario
{
    private OrdemServico $os;
    private int $peca;
    private int $servico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->os = new OrdemServico();
        $prod = new Produto();
        $this->peca    = $prod->salvar(['tipo' => 'PECA', 'nome' => 'Filtro de óleo', 'preco_venda' => '40', 'preco_custo' => '20', 'estoque_inicial' => '2']);
        $this->servico = $prod->salvar(['tipo' => 'SERVICO', 'nome' => 'Troca de óleo', 'preco_venda' => '80']);
        $this->db->exec("UPDATE usuarios SET comissao_tipo = 'PERCENTUAL_SERVICOS', comissao_percentual = 10 WHERE id = 12");
    }

    private function novaOs(): int
    {
        return $this->os->criar(['cliente_id' => 101, 'veiculo_id' => 101, 'tecnico_id' => 12, 'defeito_relatado' => 'Barulho']);
    }

    private function livroFecha(): void
    {
        $this->assertSame(
            $this->db->query("SELECT estoque_atual FROM produtos WHERE id = {$this->peca}")->fetchColumn(),
            $this->db->query("SELECT CAST(SUM(delta) AS DECIMAL(10,3)) FROM movimentacoes_estoque WHERE produto_id = {$this->peca}")->fetchColumn(),
            'o saldo da peça precisa fechar com o livro de estoque'
        );
    }

    private function estoque(): string
    {
        return (string) $this->db->query("SELECT estoque_atual FROM produtos WHERE id = {$this->peca}")->fetchColumn();
    }

    public function testNumeracaoPropriaDeCadaOficina(): void
    {
        $this->db->exec('UPDATE oficinas SET proximo_numero_os = 7 WHERE id = 1');
        $a = $this->novaOs();
        $this->assertSame(7, (int) $this->os->buscar($a)['numero']);
        $this->assertSame(8, (int) $this->os->buscar($this->novaOs())['numero']);

        $this->entrarComo(self::ADMIN_B);
        $b = (new OrdemServico())->criar(['cliente_id' => 201, 'veiculo_id' => 201]);
        $this->assertSame(2, (int) (new OrdemServico())->buscar($b)['numero'], 'a oficina B segue a própria sequência (já tinha a nº 1), sem pular para 9');
    }

    public function testAbreComoOrcamentoComLinkEHistorico(): void
    {
        $id = $this->novaOs();
        $os = $this->os->buscar($id);

        $this->assertSame('ORCAMENTO', $os['status']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $os['token_rastreio']);
        $this->assertSame('CRIACAO', $this->os->historico($id)[0]['acao']);
    }

    public function testVeiculoPrecisaSerDoCliente(): void
    {
        $this->db->exec("INSERT INTO clientes (id, oficina_id, tipo_pessoa, nome) VALUES (102, 1, 'PF', 'Outro')");
        $this->expectExceptionMessage('veículo deste cliente');
        $this->os->criar(['cliente_id' => 102, 'veiculo_id' => 101]);
    }

    public function testItensComPrecoDoCatalogoEAvulsoETotais(): void
    {
        $id = $this->novaOs();
        $this->os->adicionarItem($id, ['produto_id' => $this->peca, 'quantidade' => '2']);
        $this->os->adicionarItem($id, ['descricao' => 'Diagnóstico', 'valor_unitario' => '50,00', 'desconto' => '5']);

        $os = $this->os->buscar($id);
        $this->assertSame('80.00', $os['valor_pecas']);
        $this->assertSame('50.00', $os['valor_servicos']);
        $this->assertSame('5.00', $os['valor_desconto']);
        $this->assertSame('125.00', $os['valor_total']);
        $this->assertSame('2.000', $this->estoque(), 'no orçamento a peça ainda não sai do estoque');
    }

    public function testRegrasDeItem(): void
    {
        $id = $this->novaOs();
        foreach ([
            [['tipo' => 'PECA', 'descricao' => 'Peça solta', 'valor_unitario' => '10'], 'catálogo'],
            [['descricao' => 'X', 'valor_unitario' => '10', 'desconto' => '11'], 'desconto'],
            [['descricao' => 'X', 'valor_unitario' => '10', 'quantidade' => '0'], 'quantidade'],
        ] as [$dados, $trecho]) {
            try {
                $this->os->adicionarItem($id, $dados);
                $this->fail("Deveria recusar ({$trecho}).");
            } catch (DomainException $e) {
                $this->assertStringContainsStringIgnoringCase($trecho, $e->getMessage());
            }
        }
    }

    public function testAprovacaoCongelaValorEItensDepoisSaoAdicionais(): void
    {
        $id = $this->novaOs();
        try {
            $this->os->aprovar($id, 'Cliente', null);
            $this->fail('Orçamento sem itens não pode ser aprovado.');
        } catch (DomainException $e) {
        }

        $this->os->adicionarItem($id, ['produto_id' => $this->servico]);
        $this->os->aprovar($id, 'Cliente da A', 'data:image/png;base64,AAAA');
        $this->os->adicionarItem($id, ['descricao' => 'Lavagem', 'valor_unitario' => '30']);

        $os = $this->os->buscar($id);
        $this->assertSame('APROVADA', $os['status']);
        $this->assertSame('80.00', $os['valor_aprovado'], 'o valor aprovado não muda');
        $this->assertSame('110.00', $os['valor_total']);

        $itens = $this->os->itens($id);
        $this->assertSame(['ORCAMENTO', 'ADICIONAL'], array_column($itens, 'origem'));

        $aprovacao = array_values(array_filter($this->os->historico($id), fn($h) => $h['acao'] === 'APROVACAO'))[0];
        $copia = json_decode($aprovacao['dados'], true);
        $this->assertSame('80.00', $copia['valor_aprovado']);
        $this->assertCount(1, $copia['itens'], 'a cópia aprovada não inclui o adicional');

        // Depois da aprovação, alterar não mexe no preço.
        $this->os->alterarItem($id, (int) $itens[0]['id'], ['valor_unitario' => '1', 'quantidade' => '9']);
        $this->assertSame('80.00', $this->os->itens($id)[0]['valor_unitario']);
    }

    public function testMudancaNoCatalogoNaoAlteraOsAntiga(): void
    {
        $id = $this->novaOs();
        $this->os->adicionarItem($id, ['produto_id' => $this->servico]);
        (new Produto())->salvar(['nome' => 'Troca de óleo premium', 'preco_venda' => '200'], $this->servico);

        $item = $this->os->itens($id)[0];
        $this->assertSame('Troca de óleo', $item['descricao']);
        $this->assertSame('80.00', $item['valor_unitario']);
    }

    public function testFluxoCompletoComEstoqueEComissao(): void
    {
        $id = $this->novaOs();
        $this->os->adicionarItem($id, ['produto_id' => $this->peca, 'quantidade' => '3']); // só há 2
        $this->os->adicionarItem($id, ['produto_id' => $this->servico]);
        $this->os->aprovar($id, '', null);

        $faltam = $this->os->iniciar($id);
        $this->assertSame(['Filtro de óleo'], $faltam);
        $this->assertSame('AGUARDANDO_PECA', $this->os->buscar($id)['status']);
        $this->assertSame('2.000', $this->estoque(), 'sem saldo suficiente, nada é baixado');

        try {
            $this->os->concluir($id);
            $this->fail('Não conclui com peça pendente.');
        } catch (DomainException $e) {
        }

        // Chegou mercadoria (a entrada de estoque é da etapa 4; aqui direto no livro).
        $this->db->exec("UPDATE produtos SET estoque_atual = estoque_atual + 5 WHERE id = {$this->peca}");
        $this->db->exec("INSERT INTO movimentacoes_estoque (oficina_id, produto_id, motivo, delta) VALUES (1, {$this->peca}, 'COMPRA', 5)");

        $this->assertSame([], $this->os->baixarPendentes($id));
        $this->assertSame('EM_EXECUCAO', $this->os->buscar($id)['status']);
        $this->assertSame('4.000', $this->estoque());

        $this->os->concluir($id);
        $os = $this->os->buscar($id);
        $this->assertSame('PRONTA', $os['status']);
        $comissoes = array_column($this->os->itens($id), 'comissao_valor', 'tipo');
        $this->assertSame('8.00', $comissoes['SERVICO'], '10% sobre o serviço de R$ 80');
        $this->assertSame('0.00', $comissoes['PECA'], 'a regra do técnico é só sobre serviços');

        $this->os->entregar($id, 'Lavado');
        $this->assertSame('ENTREGUE', $this->os->buscar($id)['status']);
        $this->livroFecha();
    }

    public function testRemoverItemDepoisDaAprovacaoDevolvePecaEFicaNoHistorico(): void
    {
        $id = $this->novaOs();
        $this->os->adicionarItem($id, ['produto_id' => $this->peca]);
        $this->os->adicionarItem($id, ['produto_id' => $this->servico]);
        $this->os->aprovar($id, '', null);
        $this->os->iniciar($id);
        $this->assertSame('1.000', $this->estoque());

        $peca = $this->os->itens($id)[1];
        $this->assertSame('PECA', $peca['tipo']);
        try {
            $this->os->removerItem($id, (int) $peca['id'], '');
            $this->fail('Depois da aprovação a remoção precisa de motivo.');
        } catch (DomainException $e) {
        }

        $this->os->removerItem($id, (int) $peca['id'], 'Cliente trouxe a peça');
        $this->assertSame('2.000', $this->estoque());
        $this->assertSame('DEVOLVIDO', $this->os->itens($id)[1]['estoque_status']);
        $this->assertSame('80.00', $this->os->buscar($id)['valor_total'], 'item devolvido sai do total');
        $this->assertSame('120.00', $this->os->buscar($id)['valor_aprovado']);
        $this->livroFecha();
    }

    public function testCancelarDevolveEstoque(): void
    {
        $id = $this->novaOs();
        $this->os->adicionarItem($id, ['produto_id' => $this->peca, 'quantidade' => '2']);
        $this->os->aprovar($id, '', null);
        $this->os->iniciar($id);
        $this->assertSame('0.000', $this->estoque());

        try {
            $this->os->cancelar($id, '');
            $this->fail('Cancelar exige motivo.');
        } catch (DomainException $e) {
        }
        $this->os->cancelar($id, 'Cliente desistiu');

        $this->assertSame('CANCELADA', $this->os->buscar($id)['status']);
        $this->assertSame('2.000', $this->estoque());
        $this->livroFecha();
    }

    public function testRecusaEReabertura(): void
    {
        $id = $this->novaOs();
        $this->os->recusar($id, 'Achou caro');
        $this->assertSame('RECUSADA', $this->os->buscar($id)['status']);
        $this->assertSame('Achou caro', $this->os->buscar($id)['motivo_recusa']);

        $this->os->reabrir($id);
        $this->assertSame('ORCAMENTO', $this->os->buscar($id)['status']);
    }

    public function testTransicaoInvalidaERecusada(): void
    {
        $id = $this->novaOs();
        $this->expectException(DomainException::class);
        $this->os->entregar($id, '');
    }

    public function testNaoMexeEmOsDeOutraOficina(): void
    {
        $this->assertNull($this->os->buscar(201));
        $this->expectException(ErroHttp::class);
        $this->os->adicionarItem(201, ['descricao' => 'Invasão', 'valor_unitario' => '1']);
    }

    public function testPecaDeOutraOficinaNaoEntraNaOs(): void
    {
        $id = $this->novaOs();
        $this->entrarComo(self::ADMIN_B);
        $pecaB = (new Produto())->salvar(['tipo' => 'PECA', 'nome' => 'Peça da B', 'preco_venda' => '1']);
        $this->entrarComo(self::ADMIN_A);

        $this->expectExceptionMessage('não encontrado');
        $this->os->adicionarItem($id, ['produto_id' => $pecaB]);
    }

    public function testAcompanhamentoPublicoPeloToken(): void
    {
        $id = $this->novaOs();
        $this->os->adicionarItem($id, ['produto_id' => $this->servico]);
        $token = $this->os->buscar($id)['token_rastreio'];

        Contexto::limpar(); // visitante sem login
        $pub = (new OrdemServico())->porToken($token);
        $this->assertSame('Oficina A', $pub['oficina']);
        $this->assertCount(1, $pub['itens']);
        $this->assertArrayNotHasKey('custo_unitario', $pub['itens'][0]);
        $this->assertNull((new OrdemServico())->porToken(str_repeat('0', 32)));
        $this->assertNull((new OrdemServico())->porToken("' OR 1=1"));
    }

    public function testAbasContamPorSituacaoEKanbanAgrupaAsAbertas(): void
    {
        $antes = $this->os->contagens([]);
        $id    = $this->novaOs();
        $depois = $this->os->contagens([]);

        $this->assertSame($antes['orcamento'] + 1, $depois['orcamento']);
        $this->assertSame($antes['abertas'] + 1, $depois['abertas']);
        $this->assertSame($antes['todas'] + 1, $depois['todas']);
        $this->assertSame($antes['execucao'], $depois['execucao'], 'orçamento não conta como em andamento');

        $ids = fn (array $colunas) => array_map('intval', array_column(array_merge(...array_values($colunas)), 'id'));
        $kanban = $this->os->kanban([]);
        $this->assertSame(OrdemServico::KANBAN, array_keys($kanban), 'todas as colunas, na ordem do fluxo');
        $this->assertContains($id, array_map('intval', array_column($kanban['ORCAMENTO'], 'id')));
        $this->assertNotContains($id, $ids($this->os->kanban(['tecnico' => 999999])), 'o filtro de técnico vale no Kanban');

        $this->entrarComo(self::ADMIN_B);
        $this->assertNotContains($id, $ids((new OrdemServico())->kanban([])), 'a oficina B não enxerga a OS da oficina A');
    }
}
