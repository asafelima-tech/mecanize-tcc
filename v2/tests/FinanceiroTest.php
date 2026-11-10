<?php

/** Contas, parcelas, pagamentos parciais, estornos, caixa, cobrança de OS e comissões. */
final class FinanceiroTest extends Cenario
{
    private Lancamento $f;
    private Caixa $cx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->f  = new Lancamento();
        $this->cx = new Caixa();
    }

    private function conta(array $extra = []): int
    {
        return $this->f->criar($extra + ['tipo' => 'PAGAR', 'categoria' => 'ALUGUEL', 'descricao' => 'Aluguel', 'valor' => '1.000,00', 'vencimento' => '2026-12-05']);
    }

    private function conta1(int $id): array
    {
        return $this->db->query("SELECT * FROM lancamentos WHERE id = {$id}")->fetch();
    }

    public function testParcelasSomamOValorEVencemMesAMes(): void
    {
        $id = $this->conta(['valor' => '100', 'parcelas' => '3']);
        $p = $this->f->parcelas($this->conta1($id));

        $this->assertSame(['33.33', '33.33', '33.34'], array_column($p, 'valor'));
        $this->assertSame(['2026-12-05', '2027-01-05', '2027-02-05'], array_column($p, 'vencimento'));
        $this->assertSame([null, $id, $id], array_map(fn($x) => $x['grupo_id'] === null ? null : (int) $x['grupo_id'], $p));
    }

    public function testPagamentoParcialDepoisTotalComJurosEDesconto(): void
    {
        $id = $this->conta();
        $this->f->baixar($id, ['valor' => '400', 'forma' => 'PIX']);
        $this->assertSame('PARCIAL', $this->conta1($id)['status']);
        $this->assertSame('400.00', $this->conta1($id)['valor_pago']);

        try {
            $this->f->baixar($id, ['valor' => '700', 'forma' => 'PIX']);
            $this->fail('Pagamento maior que o saldo.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('maior que o saldo', $e->getMessage());
        }

        $this->f->baixar($id, ['valor' => '610', 'acrescimo' => '20', 'desconto' => '10', 'forma' => 'BOLETO']);
        $l = $this->conta1($id);
        $this->assertSame('PAGO', $l['status']);
        $this->assertSame('1010.00', $l['valor_pago']);
        $this->assertSame('20.00', $l['acrescimo']);
        $this->assertSame('10.00', $l['desconto']);
    }

    public function testDinheiroExigeCaixaAbertoEEntraNaGaveta(): void
    {
        $id = $this->conta(['tipo' => 'RECEBER', 'categoria' => 'OUTRAS_RECEITAS', 'descricao' => 'Venda', 'valor' => '50']);
        try {
            $this->f->baixar($id, ['valor' => '50', 'forma' => 'DINHEIRO']);
            $this->fail('Dinheiro sem caixa.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('caixa', $e->getMessage());
        }

        $cx = $this->cx->abrir('100,00', '');
        $this->f->baixar($id, ['valor' => '50', 'forma' => 'DINHEIRO']);
        $this->cx->movimentar('SANGRIA', '30', 'Depósito');
        $this->cx->movimentar('SUPRIMENTO', '5', 'Troco');

        $this->assertSame(125.0, $this->cx->resumo($cx)['esperado'], '100 + 50 − 30 + 5');
        $r = $this->cx->fechar('120', 'Faltou');
        $this->assertSame(-5.0, $r['diferenca']);
        $this->assertSame('FECHADO', $this->cx->buscar($cx)['status']);
        $this->assertSame('125.00', $this->cx->buscar($cx)['valor_esperado']);
    }

    public function testPixEntraNoCaixaAbertoMasNaoNoDinheiroEsperado(): void
    {
        $cx = $this->cx->abrir('0', '');
        $id = $this->conta(['tipo' => 'RECEBER', 'categoria' => 'OUTRAS_RECEITAS', 'descricao' => 'Venda', 'valor' => '80']);
        $this->f->baixar($id, ['valor' => '80', 'forma' => 'PIX']);

        $r = $this->cx->resumo($cx);
        $this->assertSame(80.0, $r['por_forma']['PIX']['ENTRADA']);
        $this->assertSame(0.0, $r['esperado']);
    }

    public function testUmCaixaAbertoPorVezESangriaMaiorQueAGaveta(): void
    {
        $this->cx->abrir('10', '');
        try {
            $this->cx->abrir('10', '');
            $this->fail('Dois caixas abertos.');
        } catch (DomainException $e) {
        }
        $this->expectExceptionMessage('maior que o dinheiro');
        $this->cx->movimentar('SANGRIA', '11', 'Retirada');
    }

    public function testEstornoReabreAContaENaoDuplica(): void
    {
        $id = $this->conta(['valor' => '200']);
        $this->f->baixar($id, ['valor' => '200', 'forma' => 'PIX']);
        $mov = (int) $this->db->query("SELECT id FROM movimentacoes_financeiras WHERE natureza = 'BAIXA'")->fetchColumn();

        $this->f->estornar($mov, 'Pago na conta errada');
        $l = $this->conta1($id);
        $this->assertSame('ABERTO', $l['status']);
        $this->assertSame('0.00', $l['valor_pago']);
        $this->assertSame(2, (int) $this->db->query('SELECT COUNT(*) FROM movimentacoes_financeiras')->fetchColumn(), 'o pagamento original continua registrado');

        $this->expectExceptionMessage('já foi estornado');
        $this->f->estornar($mov, 'De novo');
    }

    public function testCliqueDuploNaoPagaDuasVezes(): void
    {
        $id = $this->conta(['valor' => '200']);
        $this->f->baixar($id, ['valor' => '50', 'forma' => 'PIX', 'chave' => 'abc123']);
        try {
            $this->f->baixar($id, ['valor' => '50', 'forma' => 'PIX', 'chave' => 'abc123']);
            $this->fail('Mesmo formulário enviado duas vezes.');
        } catch (DomainException $e) {
        }
        $this->assertSame('50.00', $this->conta1($id)['valor_pago']);
    }

    public function testCancelarSoSemPagamento(): void
    {
        $id = $this->conta();
        $this->f->baixar($id, ['valor' => '10', 'forma' => 'PIX']);
        try {
            $this->f->cancelar($id, 'Errada');
            $this->fail('Conta com pagamento não pode ser cancelada.');
        } catch (DomainException $e) {
        }

        $outra = $this->conta();
        $this->f->cancelar($outra, 'Lançada em duplicidade');
        $this->assertSame('CANCELADO', $this->conta1($outra)['status']);
    }

    public function testCobrancaDaOsLancaOQueFaltaERecebePelaOs(): void
    {
        $os = new OrdemServico();
        $id = $os->criar(['cliente_id' => 101, 'veiculo_id' => 101]);
        $os->adicionarItem($id, ['descricao' => 'Serviço', 'valor_unitario' => '300']);

        try {
            $this->f->cobrarOs($id, []);
            $this->fail('Orçamento não aprovado não se cobra.');
        } catch (DomainException $e) {
        }

        $os->aprovar($id, '', null);
        $primeira = $this->f->cobrarOs($id, ['parcelas' => '2', 'forma' => 'CARTAO_CREDITO']);
        $this->assertSame(0.0, $this->f->faltaLancar($os->buscar($id)));
        $contas = $this->f->daOs($id);
        $this->assertCount(2, $contas);
        $this->assertSame(101, (int) $contas[0]['cliente_id']);

        $this->f->receberDaOs($id, $primeira, ['valor' => '150', 'forma' => 'CARTAO_CREDITO']);
        $this->assertSame('PAGO', $this->conta1($primeira)['status']);

        $alheia = $this->conta();
        $this->expectException(ErroHttp::class);
        $this->f->receberDaOs($id, $alheia, ['valor' => '1', 'forma' => 'PIX']);
    }

    public function testComissaoVaiParaContaAPagarEVoltaSeCancelar(): void
    {
        $this->db->exec("UPDATE usuarios SET comissao_tipo = 'PERCENTUAL_SERVICOS', comissao_percentual = 10 WHERE id = 12");
        $os = new OrdemServico();
        $id = $os->criar(['cliente_id' => 101, 'veiculo_id' => 101, 'tecnico_id' => 12]);
        $os->adicionarItem($id, ['descricao' => 'Revisão', 'valor_unitario' => '500']);
        $os->aprovar($id, '', null);
        $os->iniciar($id);
        $os->concluir($id);

        $pend = $this->f->comissoesPendentes();
        $this->assertSame('50.00', $pend[0]['total']);

        $conta = $this->f->gerarComissao(12, '2026-12-01');
        $this->assertSame([], $this->f->comissoesPendentes());
        $this->assertSame('COMISSAO', $this->conta1($conta)['categoria']);
        $this->assertSame(12, (int) $this->conta1($conta)['funcionario_id']);

        try {
            $os->retomar($id, '');
            $this->fail('OS com comissão já lançada não volta para execução.');
        } catch (DomainException $e) {
        }

        $this->f->cancelar($conta, 'Lançada antes da hora');
        $this->assertSame('50.00', $this->f->comissoesPendentes()[0]['total'], 'cancelada, a comissão volta a ficar pendente');
    }

    public function testIsolamento(): void
    {
        $id = $this->conta();
        $this->cx->abrir('0', '');
        $this->entrarComo(self::ADMIN_B);

        $this->assertNull((new Lancamento())->buscar($id));
        $this->assertNull((new Caixa())->aberto(), 'o caixa aberto é da oficina A');
        $this->expectException(ErroHttp::class);
        (new Lancamento())->baixar($id, ['valor' => '1', 'forma' => 'PIX']);
    }
}
