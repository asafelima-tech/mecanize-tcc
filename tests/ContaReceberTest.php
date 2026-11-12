<?php

require_once __DIR__ . '/OsTestCase.php';
require_once __DIR__ . '/../app/models/ContaReceber.php';
require_once __DIR__ . '/../app/models/Financeiro.php';

/**
 * Contas a receber.
 *
 * O exemplo do pedido: serviço de R$ 1.000 pago em 5 parcelas de R$ 200. Depois que a primeira é
 * efetivamente liquidada restam 4 a receber (R$ 800). Concluir a OS, aprová-la ou entregá-la não é
 * dinheiro recebido; só a baixa da parcela é — e o estorno devolve a parcela à fila de cobrança.
 */
final class ContaReceberTest extends OsTestCase
{
    private OrdemServicoPagamento $pag;
    private ContaReceber $receber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pag     = new OrdemServicoPagamento($this->pdo);
        $this->receber = new ContaReceber($this->pdo);
    }

    /** OS de serviço único de $valor, com plano em $parcelas, conferida pelo administrador. */
    private function osCobrada(float $valor = 1000, int $parcelas = 5, string $forma = 'CARTAO_CREDITO'): int
    {
        $os = $this->novaOs(['itens' => [['tipo' => 'servico', 'id' => 'custom_1', 'nome' => 'Serviço', 'qty' => 1, 'preco' => $valor, 'custom' => 1, 'categoria' => 'Geral']]]);

        $this->assertTrue($this->pag->salvarPlano($os, [
            'pagamento_tipo' => $parcelas > 1 ? 'PARCELADO' : 'AVISTA', 'pagamento_forma' => $forma,
            'parcelas' => $parcelas, 'primeiro_vencimento' => date('Y-m-d'),
        ]));

        $this->ateRetirada($os);

        return $os;
    }

    private function parcelas(int $os): array
    {
        return $this->pag->parcelas($os);
    }

    public function testCincoParcelasDe200QuandoAPrimeiraPagaRestamQuatroAReceber(): void
    {
        $antes = $this->receber->totais();
        $os = $this->osCobrada(1000, 5);
        $parcelas = $this->parcelas($os);

        $this->assertCount(5, $parcelas);
        $this->assertSame([200.0, 200.0, 200.0, 200.0, 200.0], array_map(fn($p) => (float) $p['valor'], $parcelas));
        $this->assertSame(round($antes['a_receber'] + 1000, 2), $this->receber->totais()['a_receber'], 'a OS conferida entra em contas a receber');

        $this->assertNull($this->pag->pagarParcela((int) $parcelas[0]['id_parcela'], 'CARTAO_CREDITO', $this->adminId));

        $depois = $this->receber->totais();
        $this->assertSame(round($antes['a_receber'] + 800, 2), $depois['a_receber'], 'depois da primeira liquidada: R$ 800 a receber');
        $this->assertSame($antes['parcelas_pendentes'] + 4, $depois['parcelas_pendentes']);

        $abertas = $this->receber->listar(['situacao' => 'abertas', 'busca' => $this->pdo->query("SELECT numero FROM ordens_servico WHERE id_ordem_servico = {$os}")->fetchColumn()]);
        $this->assertCount(4, $abertas, 'a parcela recebida deixa de aparecer como pendente');
        $this->assertSame([2, 3, 4, 5], array_map(fn($p) => (int) $p['numero'], $abertas), 'as futuras continuam na lista');

        $this->assertCount(1, $this->receber->listar(['situacao' => 'recebidas', 'cliente_id' => $this->clienteId]));
    }

    public function testConferirAOsNaoEDinheiroRecebido(): void
    {
        $ind = new Financeiro($this->pdo);
        $antes = $ind->indicadores('2000-01-01', '2100-12-31');

        $this->osCobrada(1000, 5);

        $depois = $ind->indicadores('2000-01-01', '2100-12-31');
        $this->assertSame($antes['recebido_bruto'], $depois['recebido_bruto'], 'cobrança não é recebimento');
        $this->assertSame(0, $depois['parcelas_recebidas'] - $antes['parcelas_recebidas']);
    }

    public function testOsAindaEmExecucaoNaoEContaAReceber(): void
    {
        $antes = $this->receber->totais();

        $os = $this->novaOs();
        $this->pag->salvarPlano($os, ['pagamento_tipo' => 'PARCELADO', 'parcelas' => 3, 'primeiro_vencimento' => date('Y-m-d')]);
        $this->iniciar($os);

        $this->assertSame($antes, $this->receber->totais(), 'o trabalho ainda não foi entregue nem conferido: não é cobrança');
    }

    public function testParcelaVencidaEntraNoVencidoEMudaDeSituacao(): void
    {
        $os = $this->osCobrada(300, 3);
        $primeira = (int) $this->parcelas($os)[0]['id_parcela'];
        $antes = $this->receber->totais();

        $this->pdo->exec("UPDATE ordem_servico_parcelas SET vencimento = DATE_SUB(CURDATE(), INTERVAL 5 DAY) WHERE id_parcela = {$primeira}");

        $this->assertSame(round($antes['vencido'] + 100, 2), $this->receber->totais()['vencido']);
        $linha = array_values(array_filter($this->receber->listar(['situacao' => 'vencidas']), fn($p) => (int) $p['id_parcela'] === $primeira));
        $this->assertCount(1, $linha);
        $this->assertSame('VENCIDA', $linha[0]['situacao']);
        $this->assertSame('VENCIDA', ContaReceber::situacao($this->parcelas($os)[0]));
    }

    public function testFiltrosPorClienteFormaEPeriodo(): void
    {
        $os = $this->osCobrada(400, 2, 'PIX');
        $outroCliente = $this->fx->cliente();

        $this->assertNotEmpty($this->receber->listar(['cliente_id' => $this->clienteId]));
        $this->assertSame([], $this->receber->listar(['cliente_id' => $outroCliente]));

        $porForma = array_column($this->receber->listar(['forma' => 'PIX', 'cliente_id' => $this->clienteId]), 'id_ordem_servico');
        $this->assertContains((string) $os, array_map('strval', $porForma));
        $this->assertSame([], $this->receber->listar(['forma' => 'BOLETO', 'cliente_id' => $this->clienteId]));

        $this->assertNotEmpty($this->receber->listar(['de' => date('Y-m-d'), 'ate' => date('Y-m-d', strtotime('+40 days')), 'cliente_id' => $this->clienteId]));
        $this->assertSame([], $this->receber->listar(['de' => date('Y-m-d', strtotime('+100 days')), 'cliente_id' => $this->clienteId]));
    }

    public function testTaxaDoMeioDePagamentoFicaSeparadaDoValor(): void
    {
        $ind = new Financeiro($this->pdo);
        $antes = $ind->indicadores('2000-01-01', '2100-12-31');
        $os = $this->osCobrada(1000, 5);
        $parcela = (int) $this->parcelas($os)[0]['id_parcela'];

        $this->assertNull($this->pag->pagarParcela($parcela, 'CARTAO_CREDITO', $this->adminId, 6.0));

        $p = $this->parcelas($os)[0];
        $this->assertSame(200.0, (float) $p['valor'], 'a taxa não altera o valor da parcela');
        $this->assertSame(6.0, (float) $p['taxa_valor']);

        $depois = $ind->indicadores('2000-01-01', '2100-12-31');
        $this->assertSame(round($antes['recebido_bruto'] + 200, 2), $depois['recebido_bruto']);
        $this->assertSame(round($antes['taxas'] + 6, 2), $depois['taxas']);
        $this->assertSame(round($antes['recebido_liquido'] + 194, 2), $depois['recebido_liquido']);
    }

    public function testTaxaInvalidaERecusada(): void
    {
        $os = $this->osCobrada(100, 1, 'CARTAO_CREDITO');
        $parcela = (int) $this->parcelas($os)[0]['id_parcela'];

        $this->assertNotNull($this->pag->pagarParcela($parcela, 'CARTAO_CREDITO', $this->adminId, -1.0), 'taxa negativa');
        $this->assertNotNull($this->pag->pagarParcela($parcela, 'CARTAO_CREDITO', $this->adminId, 100.0), 'taxa maior ou igual ao valor');
        $this->assertSame(0, (int) $this->parcelas($os)[0]['pago'], 'nada foi baixado');
    }

    /* ── estorno do recebimento ── */

    public function testEstornoDevolveAParcelaACobrancaELancaASaidaNoCaixa(): void
    {
        $os = $this->osCobrada(1000, 5);
        $parcela = (int) $this->parcelas($os)[0]['id_parcela'];
        $this->pag->pagarParcela($parcela, 'CARTAO_CREDITO', $this->adminId, 6.0);
        $totalPago = $this->receber->totais()['a_receber'];

        $r = $this->pag->estornarParcela($parcela, 'Cobrança contestada pelo cliente', $this->adminId);

        $this->assertTrue($r['ok'], (string) $r['erro']);
        $p = $this->parcelas($os)[0];
        $this->assertSame(0, (int) $p['pago']);
        $this->assertNull($p['data_pagamento']);
        $this->assertSame(0.0, (float) $p['taxa_valor'], 'a taxa do recebimento estornado também sai');
        $this->assertNotNull($p['estornada_em']);
        $this->assertSame('Cobrança contestada pelo cliente', $p['estorno_justificativa']);
        $this->assertSame(round($totalPago + 200, 2), $this->receber->totais()['a_receber'], 'volta para o a receber');

        $mov = $this->pdo->query("SELECT tipo, valor, origem FROM movimentacoes_caixa WHERE chave_idempotencia = 'ESTORNO_PARCELA:{$parcela}:1'")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('SAIDA', $mov['tipo']);
        $this->assertSame(200.0, (float) $mov['valor']);
        $this->assertSame(2, $this->contar("SELECT COUNT(*) FROM movimentacoes_caixa WHERE ordem_servico_id = {$os}"), 'o recebimento original continua no livro, e o estorno é um lançamento novo');

        $estados = $this->pdo->query("SELECT estado_novo FROM historico_estados WHERE entidade = 'PARCELA_OS' AND entidade_id = {$parcela} ORDER BY id_historico")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['RECEBIDA', 'PENDENTE'], $estados);
    }

    public function testEstornarDeNovoNaoDevolveDuasVezes(): void
    {
        $os = $this->osCobrada(100, 1);
        $parcela = (int) $this->parcelas($os)[0]['id_parcela'];
        $this->pag->pagarParcela($parcela, 'PIX', $this->adminId);

        $this->pag->estornarParcela($parcela, 'Pagamento duplicado', $this->adminId);
        $r = $this->pag->estornarParcela($parcela, 'Pagamento duplicado', $this->adminId);

        $this->assertTrue($r['ok']);
        $this->assertTrue($r['repetido']);
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM movimentacoes_caixa WHERE chave_idempotencia LIKE 'ESTORNO_PARCELA:{$parcela}:%'"));
    }

    public function testEstornoExigeJustificativaCaixaAbertoEParcelaPaga(): void
    {
        $os = $this->osCobrada(100, 1);
        $parcela = (int) $this->parcelas($os)[0]['id_parcela'];

        $this->assertFalse($this->pag->estornarParcela($parcela, 'sem pagamento', $this->adminId)['ok'], 'parcela pendente não tem o que estornar');

        $this->pag->pagarParcela($parcela, 'PIX', $this->adminId);
        $this->assertFalse($this->pag->estornarParcela($parcela, '', $this->adminId)['ok'], 'sem justificativa');

        $this->pdo->exec("UPDATE caixa SET status = 'FECHADO', data_fechamento = NOW(), valor_fechamento = 0 WHERE status = 'ABERTO'");
        $r = $this->pag->estornarParcela($parcela, 'Estorno sem caixa', $this->adminId);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('caixa', $r['erro']);
        $this->assertSame(1, (int) $this->parcelas($os)[0]['pago'], 'sem caixa nada mudou');
    }

    public function testParcelaEstornadaPodeSerPagaDeNovoCadaPagamentoComSuaChave(): void
    {
        $os = $this->osCobrada(100, 1);
        $parcela = (int) $this->parcelas($os)[0]['id_parcela'];

        $this->assertNull($this->pag->pagarParcela($parcela, 'PIX', $this->adminId));
        $this->pag->estornarParcela($parcela, 'Pix devolvido pelo banco', $this->adminId);
        $this->assertNull($this->pag->pagarParcela($parcela, 'DINHEIRO', $this->adminId), 'o cliente pagou de novo');

        $p = $this->parcelas($os)[0];
        $this->assertSame(1, (int) $p['pago']);
        $this->assertSame('DINHEIRO', $p['forma_pagamento']);
        $this->assertSame(2, (int) $p['pagamentos_realizados']);

        $chaves = $this->pdo->query("SELECT chave_idempotencia FROM movimentacoes_caixa WHERE ordem_servico_id = {$os} ORDER BY id_movimentacao")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(["PARCELA:{$parcela}", "ESTORNO_PARCELA:{$parcela}:1", "PARCELA:{$parcela}:2"], $chaves);

        $this->assertNotNull($this->pag->pagarParcela($parcela, 'PIX', $this->adminId), 'e a de novo já paga continua recusada');
    }

    public function testRecebimentoEstornadoNaoContaComoRecebidoNemViraEntradaManual(): void
    {
        $ind = new Financeiro($this->pdo);
        $antes = $ind->indicadores('2000-01-01', '2100-12-31');
        $os = $this->osCobrada(100, 1);
        $parcela = (int) $this->parcelas($os)[0]['id_parcela'];

        $this->pag->pagarParcela($parcela, 'PIX', $this->adminId);
        $this->pag->estornarParcela($parcela, 'Estorno de teste', $this->adminId);

        $depois = $ind->indicadores('2000-01-01', '2100-12-31');
        $this->assertSame($antes['recebido_bruto'], $depois['recebido_bruto']);
        $this->assertSame($antes['outras_entradas'], $depois['outras_entradas']);
        $this->assertSame($antes['despesas_pagas']['caixa_direto'], $depois['despesas_pagas']['caixa_direto'], 'a saída do estorno é da OS, não uma despesa avulsa');
        $this->assertSame($antes['fluxo_realizado'], $depois['fluxo_realizado']);
    }
}
