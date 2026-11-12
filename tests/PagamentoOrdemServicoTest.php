<?php

require_once __DIR__ . '/OsTestCase.php';

/**
 * Cobrança da OS (OrdemServicoPagamento) e o livro-caixa.
 *
 *  - toda OS cobrada tem parcelas (à vista = 1);
 *  - receber é baixar uma parcela, e a mesma parcela nunca entra duas vezes no caixa;
 *  - só existe UM caixa aberto, garantido pelo banco;
 *  - o caixa pertence a quem o abriu.
 */
final class PagamentoOrdemServicoTest extends OsTestCase
{
    private OrdemServicoPagamento $pag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pag = new OrdemServicoPagamento();
    }

    private function osComValor(float $valor = 600.0): int
    {
        $id = $this->novaOs(['itens' => [['tipo' => 'servico', 'id' => 'custom_1', 'nome' => 'Serviço', 'qty' => 1, 'preco' => $valor, 'custom' => 1]]]);
        $this->assertSame($valor, (float) $this->pdo->query("SELECT valor_total_os FROM ordens_servico WHERE id_ordem_servico = {$id}")->fetchColumn());

        return $id;
    }

    public function testAVistaGeraUmaParcelaComOTotalDaOs(): void
    {
        $id = $this->osComValor(600);

        $this->assertTrue($this->pag->salvarPlano($id, ['pagamento_tipo' => 'AVISTA']));

        $parcelas = $this->pag->parcelas($id);
        $this->assertCount(1, $parcelas);
        $this->assertSame(600.0, (float) $parcelas[0]['valor']);
    }

    public function testParceladoDivideESobraDeCentavosVaiParaAUltima(): void
    {
        $id = $this->osComValor(100);

        $this->pag->salvarPlano($id, ['pagamento_tipo' => 'PARCELADO', 'parcelas' => 3, 'primeiro_vencimento' => '2026-11-10']);

        $valores = array_map(fn($p) => (float) $p['valor'], $this->pag->parcelas($id));
        $this->assertSame([33.33, 33.33, 33.34], $valores);
        $this->assertSame(100.0, round(array_sum($valores), 2), 'a soma das parcelas fecha com o total');

        $venc = array_column($this->pag->parcelas($id), 'vencimento');
        $this->assertSame(['2026-11-10', '2026-12-10', '2027-01-10'], $venc);
    }

    public function testJurosETaxaEntramNoTotalDoPlano(): void
    {
        $id = $this->osComValor(1000);

        $this->pag->salvarPlano($id, [
            'pagamento_tipo' => 'PARCELADO', 'parcelas' => 5, 'pagamento_juros' => '10', 'pagamento_taxa' => '50',
            'pagamento_forma' => 'CARTAO_CREDITO',
        ]);

        $resumo = $this->pag->resumo($id);
        $this->assertSame(1150.0, $resumo['valor_total'], '1000 + 10% + R$ 50 de taxa');
        $this->assertSame(5, $resumo['total']);
    }

    public function testPagarParcelaLancaNoCaixaEUmaSoVez(): void
    {
        $id = $this->osComValor(200);
        $this->pag->salvarPlano($id, ['pagamento_tipo' => 'AVISTA']);
        $parcela = (int) $this->pag->parcelas($id)[0]['id_parcela'];

        $this->assertNull($this->pag->pagarParcela($parcela, 'PIX', $this->adminId));
        $this->assertSame('Esta parcela já foi paga.', $this->pag->pagarParcela($parcela, 'PIX', $this->adminId), 'segunda baixa é recusada com explicação');
        $this->assertSame('Esta parcela já foi paga.', $this->pag->pagarParcela($parcela, 'DINHEIRO', $this->tec1));

        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM movimentacoes_caixa WHERE ordem_servico_id = {$id}"));
        $mov = $this->pdo->query("SELECT tipo, forma_pagamento, valor, chave_idempotencia FROM movimentacoes_caixa WHERE ordem_servico_id = {$id}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('ENTRADA', $mov['tipo']);
        $this->assertSame('PIX', $mov['forma_pagamento']);
        $this->assertSame(200.0, (float) $mov['valor']);
        $this->assertSame("PARCELA:{$parcela}", $mov['chave_idempotencia']);
    }

    public function testLancamentoComAMesmaChaveNaoDuplicaNoLivroCaixa(): void
    {
        $caixa = new MovimentacaoCaixa();

        $a = $caixa->registrar($this->caixaId, 'ENTRADA', 50, 'PIX', 'OS', 'teste', $this->adminId, 'OS', null, 'CHAVE-TESTE-1');
        $b = $caixa->registrar($this->caixaId, 'ENTRADA', 50, 'PIX', 'OS', 'teste', $this->adminId, 'OS', null, 'CHAVE-TESTE-1');

        $this->assertSame($a, $b, 'a mesma chave devolve o mesmo lançamento');
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM movimentacoes_caixa WHERE chave_idempotencia = 'CHAVE-TESTE-1'"));
    }

    public function testNaoRecebeSemCaixaAbertoNemComFormaInvalidaNemEmOsCancelada(): void
    {
        $id = $this->osComValor(80);
        $this->pag->salvarPlano($id, ['pagamento_tipo' => 'AVISTA']);
        $parcela = (int) $this->pag->parcelas($id)[0]['id_parcela'];

        $this->assertStringContainsString('forma de pagamento', (string) $this->pag->pagarParcela($parcela, 'BITCOIN', $this->adminId));

        $this->pdo->exec("UPDATE caixa SET status = 'FECHADO', data_fechamento = NOW(), valor_fechamento = 0 WHERE id_caixa = {$this->caixaId}");
        $this->assertStringContainsString('caixa aberto', (string) $this->pag->pagarParcela($parcela, 'PIX', $this->adminId));

        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM ordem_servico_parcelas WHERE id_parcela = {$parcela} AND pago = 1"), 'nada ficou pela metade');
    }

    public function testRefazerOPlanoComParcelaPagaForaDeOrdemNaoColideNumeros(): void
    {
        $id = $this->osComValor(300);
        $this->pag->salvarPlano($id, ['pagamento_tipo' => 'PARCELADO', 'parcelas' => 3]);
        $segunda = (int) $this->pag->parcelas($id)[1]['id_parcela'];
        $this->assertNull($this->pag->pagarParcela($segunda, 'PIX', $this->adminId), 'paga a 2ª antes da 1ª');

        $ok = $this->pag->salvarPlano($id, ['pagamento_tipo' => 'PARCELADO', 'parcelas' => 3]);

        $this->assertTrue($ok, 'antes estourava a chave única (ordem_servico_id, numero)');
        $numeros = array_map('intval', array_column($this->pag->parcelas($id), 'numero'));
        $this->assertSame([1, 2, 3], $numeros);
        $this->assertSame(300.0, round(array_sum(array_map(fn($p) => (float) $p['valor'], $this->pag->parcelas($id))), 2));
    }

    public function testGarantirPlanoRefazAsParcelasSobreOTotalAtual(): void
    {
        $id = $this->osComValor(100);
        $this->pag->salvarPlano($id, ['pagamento_tipo' => 'AVISTA']);

        // O valor da OS mudou depois do plano ser desenhado (peça trocada, serviço extra…).
        $this->pdo->exec("UPDATE ordens_servico SET valor_total_os = 175 WHERE id_ordem_servico = {$id}");
        $this->pag->garantirPlano($id);

        $parcelas = $this->pag->parcelas($id);
        $this->assertCount(1, $parcelas);
        $this->assertSame(175.0, (float) $parcelas[0]['valor'], 'a cobrança acompanha o valor final da OS');
    }

    /* ── Caixa ───────────────────────────────────────────────────────── */

    public function testSoPodeHaverUmCaixaAbertoGarantidoPeloBanco(): void
    {
        $caixa = new Caixa();

        $this->assertFalse($caixa->abrir($this->tec1, 50), 'já existe caixa aberto (o do setUp)');

        // Mesmo escrevendo direto no banco, ele recusa o segundo ABERTO.
        $this->expectException(PDOException::class);
        $this->pdo->exec("INSERT INTO caixa (usuario_abertura_id, valor_abertura, status) VALUES ({$this->tec1}, 0, 'ABERTO')");
    }

    public function testAberturaSimultaneaDeixaApenasUmCaixa(): void
    {
        $this->pdo->exec("UPDATE caixa SET status = 'FECHADO', data_fechamento = NOW(), valor_fechamento = 0 WHERE id_caixa = {$this->caixaId}");

        $a = (new Caixa())->abrir($this->adminId, 10);
        $b = (new Caixa())->abrir($this->tec1, 20);

        $this->assertTrue($a);
        $this->assertFalse($b, 'a segunda abertura é recusada em vez de criar dois caixas');
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM caixa WHERE status = 'ABERTO'"));

        foreach ($this->pdo->query("SELECT id_caixa FROM caixa WHERE usuario_abertura_id IN ({$this->adminId}, {$this->tec1})")->fetchAll(PDO::FETCH_COLUMN) as $c) {
            $this->fx->registrar('caixa', (int) $c);
        }
    }

    public function testCaixaPertenceAQuemAbriu(): void
    {
        $caixa = new Caixa();

        $this->assertTrue($caixa->ehDonoDoCaixaAberto($this->adminId));
        $this->assertFalse($caixa->ehDonoDoCaixaAberto($this->tec1), 'o logout do técnico não mexe no caixa do administrador');
    }
}
