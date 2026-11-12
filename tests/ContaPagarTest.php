<?php

require_once __DIR__ . '/ComprasTestCase.php';
require_once __DIR__ . '/../app/models/Despesa.php';
require_once __DIR__ . '/../app/models/ContaPagar.php';
require_once __DIR__ . '/../app/models/CartaoCorporativo.php';
require_once __DIR__ . '/../app/models/FaturaCartao.php';
require_once __DIR__ . '/../app/models/Financeiro.php';

/**
 * Contas a pagar, cartões e faturas.
 *
 * O exemplo do pedido: um amortecedor de R$ 600 no cartão corporativo em 3x de R$ 200. Compra,
 * parcelas e pagamento da fatura são etapas de UMA despesa — o dinheiro sai uma vez só e nenhum
 * total conta a mesma compra duas vezes.
 */
final class ContaPagarTest extends ComprasTestCase
{
    private Despesa $despesas;
    private ContaPagar $pagar;
    private FaturaCartao $faturas;
    private int $cartaoId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->despesas = new Despesa($this->pdo);
        $this->pagar    = new ContaPagar($this->pdo);
        $this->faturas  = new FaturaCartao($this->pdo);
        $this->cartaoId = $this->fx->cartao(['dia_fechamento' => 10, 'dia_vencimento' => 20, 'limite_total' => 1000]);
    }

    private function compraNoCartao(float $valor = 600, int $parcelas = 3, ?string $data = null): int
    {
        $r = $this->despesas->criar([
            'descricao' => 'Amortecedor dianteiro', 'categoria' => 'ESTOQUE', 'valor_total' => number_format($valor, 2, ',', ''),
            'data_compra' => $data ?? date('Y-m-d', strtotime('-4 months')), 'meio_pagamento' => 'CARTAO_CREDITO',
            'cartao_id' => $this->cartaoId, 'qtd_parcelas' => $parcelas, 'fornecedor_id' => $this->fornecedorId,
        ], $this->adminId);

        $this->assertTrue($r['ok'], (string) $r['erro']);

        return $r['id'];
    }

    private function boleto(float $valor = 300, int $parcelas = 3, string $categoria = 'ALUGUEL', ?string $primeiro = null): int
    {
        $r = $this->despesas->criar([
            'descricao' => 'Despesa de teste', 'categoria' => $categoria, 'valor_total' => number_format($valor, 2, ',', ''),
            'data_compra' => date('Y-m-d'), 'meio_pagamento' => 'BOLETO', 'qtd_parcelas' => $parcelas,
            'primeiro_vencimento' => $primeiro ?? date('Y-m-d', strtotime('+10 days')),
        ], $this->adminId);

        $this->assertTrue($r['ok'], (string) $r['erro']);

        return $r['id'];
    }

    private function parcelas(int $despesaId): array
    {
        return $this->despesas->parcelas($despesaId);
    }

    private function pagto(string $forma = 'PIX', array $extra = []): array
    {
        return ['forma' => $forma, 'chave' => $this->chave()] + $extra;
    }

    /* ── o exemplo: R$ 600 no cartão em 3 parcelas de R$ 200 ── */

    public function testCompraParceladaNoCartaoGeraTresObrigacoesDe200EmFaturasDiferentes(): void
    {
        $id = $this->compraNoCartao(600, 3);
        $parcelas = $this->parcelas($id);

        $this->assertCount(3, $parcelas);
        $this->assertSame([200.0, 200.0, 200.0], array_map(fn($p) => (float) $p['valor'], $parcelas));
        $this->assertSame(['PENDENTE', 'PENDENTE', 'PENDENTE'], array_column($parcelas, 'status'));
        $this->assertCount(3, array_unique(array_column($parcelas, 'fatura_id')), 'cada parcela cai numa fatura (mês) diferente');
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM despesas WHERE cartao_id = {$this->cartaoId}"), 'uma compra = uma despesa');
        $this->assertSame(600.0, (float) $this->despesas->getById($id)['valor_pendente']);
    }

    public function testVencimentoDaParcelaEODaFaturaEDeMesEmMes(): void
    {
        $id = $this->compraNoCartao(600, 3);
        $parcelas = $this->parcelas($id);

        foreach ($parcelas as $i => $p) {
            $this->assertSame($p['fatura_vence_em'], $p['vencimento'], 'a parcela vence com a fatura');
            $this->assertSame('20', date('d', strtotime($p['vencimento'])), 'dia de vencimento do cartão');

            if ($i > 0) {
                $meses = (int) ((strtotime($p['vencimento']) - strtotime($parcelas[$i - 1]['vencimento'])) / 86400 / 28);
                $this->assertGreaterThanOrEqual(1, $meses);
            }
        }
    }

    public function testCalendarioDaFaturaDependeDoDiaDeFechamento(): void
    {
        // fecha dia 10, vence dia 20
        $this->assertSame(['referencia' => '2026-03-01', 'fecha_em' => '2026-03-10', 'vence_em' => '2026-03-20'], CartaoCorporativo::datasDaFatura(10, 20, '2026-03-05'));
        $this->assertSame(['referencia' => '2026-03-01', 'fecha_em' => '2026-03-10', 'vence_em' => '2026-03-20'], CartaoCorporativo::datasDaFatura(10, 20, '2026-03-10'), 'no dia do fechamento ainda entra');
        $this->assertSame(['referencia' => '2026-04-01', 'fecha_em' => '2026-04-10', 'vence_em' => '2026-04-20'], CartaoCorporativo::datasDaFatura(10, 20, '2026-03-11'), 'depois do fechamento vai para a seguinte');
        // fecha dia 25, vence dia 5 do mês seguinte
        $this->assertSame(['referencia' => '2026-03-01', 'fecha_em' => '2026-03-25', 'vence_em' => '2026-04-05'], CartaoCorporativo::datasDaFatura(25, 5, '2026-03-03'));
        // virada de ano e deslocamento (parcelas)
        $this->assertSame(['referencia' => '2027-01-01', 'fecha_em' => '2027-01-25', 'vence_em' => '2027-02-05'], CartaoCorporativo::datasDaFatura(25, 5, '2026-12-03', 1));
        $this->assertSame(['referencia' => '2027-02-01', 'fecha_em' => '2027-02-25', 'vence_em' => '2027-03-05'], CartaoCorporativo::datasDaFatura(25, 5, '2026-12-30', 1));
    }

    public function testPagarAFaturaQuitaAParcelaDelaSemDuplicarADespesa(): void
    {
        $id = $this->compraNoCartao(600, 3);
        $p = $this->parcelas($id);
        $faturaId = (int) $p[0]['fatura_id'];

        $totalAntes = $this->pagar->totais()['pendente'];

        $r = $this->pagar->pagarFatura($faturaId, $this->pagto('PIX'), $this->adminId);

        $this->assertTrue($r['ok'], (string) $r['erro']);

        $depois = $this->parcelas($id);
        $this->assertSame(['PAGA', 'PENDENTE', 'PENDENTE'], array_column($depois, 'status'));
        $this->assertSame(200.0, (float) $this->pdo->query("SELECT valor FROM pagamentos_pagar WHERE id_pagamento = {$r['pagamento_id']}")->fetchColumn());
        $this->assertSame(round($totalAntes - 200, 2), $this->pagar->totais()['pendente'], 'o pendente cai 200: restam 400 desta compra');
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM despesas WHERE cartao_id = {$this->cartaoId}"), 'pagar a fatura não cria outra despesa');
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM pagamentos_pagar WHERE fatura_id = {$faturaId}"));
        $this->assertNotNull($this->faturas->getById($faturaId)['paga_em']);
        $this->assertSame('PAGA', $this->faturas->getById($faturaId)['situacao']);
        $this->assertSame(400.0, (float) $this->despesas->getById($id)['valor_pendente']);
    }

    public function testFaturaReuneAsParcelasDeVariasComprasEUmPagamentoQuitaTodas(): void
    {
        $data = date('Y-m-d', strtotime('-4 months'));
        $a = $this->compraNoCartao(600, 3, $data);
        $b = $this->compraNoCartao(90, 1, $data);

        $faturaId = (int) $this->parcelas($a)[0]['fatura_id'];
        $this->assertSame($faturaId, (int) $this->parcelas($b)[0]['fatura_id'], 'duas compras do mesmo mês, mesma fatura');
        $this->assertSame(290.0, $this->faturas->getById($faturaId)['total'], '200 + 90');
        $this->assertCount(2, $this->faturas->compras($faturaId));

        $r = $this->pagar->pagarFatura($faturaId, $this->pagto(), $this->adminId);

        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->assertSame(290.0, (float) $this->pdo->query("SELECT valor FROM pagamentos_pagar WHERE id_pagamento = {$r['pagamento_id']}")->fetchColumn(), 'um pagamento só, pela soma');
        $this->assertSame('PAGA', $this->parcelas($b)[0]['status']);
    }

    public function testParcelaDeCartaoNaoSePagaIsoladamente(): void
    {
        $id = $this->compraNoCartao(600, 3);

        $r = $this->pagar->pagarParcela((int) $this->parcelas($id)[0]['id_conta_parcela'], $this->pagto(), $this->adminId);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('FATURA', $r['erro']);
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM pagamentos_pagar WHERE parcela_id IS NOT NULL AND usuario_id = {$this->adminId}"));
    }

    public function testFaturaAindaAbertaNaoSePaga(): void
    {
        $id = $this->compraNoCartao(100, 1, date('Y-m-d')); // fecha nesta virada de mês
        $faturaId = (int) $this->parcelas($id)[0]['fatura_id'];
        $this->pdo->exec("UPDATE faturas_cartao SET fecha_em = DATE_ADD(CURDATE(), INTERVAL 5 DAY) WHERE id_fatura = {$faturaId}");

        $r = $this->pagar->pagarFatura($faturaId, $this->pagto(), $this->adminId);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('aberta', $r['erro']);
        $this->assertSame('PENDENTE', $this->parcelas($id)[0]['status']);
    }

    public function testPagarFaturaDeNovoERepetidoOuRecusado(): void
    {
        $id = $this->compraNoCartao(600, 3);
        $faturaId = (int) $this->parcelas($id)[0]['fatura_id'];
        $chave = $this->chave();

        $a = $this->pagar->pagarFatura($faturaId, ['forma' => 'PIX', 'chave' => $chave], $this->adminId);
        $b = $this->pagar->pagarFatura($faturaId, ['forma' => 'PIX', 'chave' => $chave], $this->adminId);
        $c = $this->pagar->pagarFatura($faturaId, $this->pagto(), $this->adminId);

        $this->assertTrue($b['repetido'], 'mesma chave = mesmo pagamento');
        $this->assertSame($a['pagamento_id'], $b['pagamento_id']);
        $this->assertFalse($c['ok'], 'outra tentativa, com chave nova, não paga uma fatura já paga');
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM pagamentos_pagar WHERE fatura_id = {$faturaId}"));
    }

    public function testEstornoDoPagamentoDaFaturaVoltaTudoAoEstadoAnterior(): void
    {
        $id = $this->compraNoCartao(600, 3);
        $faturaId = (int) $this->parcelas($id)[0]['fatura_id'];
        $pg = $this->pagar->pagarFatura($faturaId, $this->pagto(), $this->adminId)['pagamento_id'];

        $this->assertFalse($this->pagar->estornarPagamento($pg, '', $this->adminId)['ok'], 'exige justificativa');

        $r = $this->pagar->estornarPagamento($pg, 'Pagamento lançado na fatura errada', $this->adminId);

        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->assertSame(['PENDENTE', 'PENDENTE', 'PENDENTE'], array_column($this->parcelas($id), 'status'));
        $this->assertNull($this->faturas->getById($faturaId)['paga_em']);
        $this->assertTrue($this->pagar->estornarPagamento($pg, 'de novo', $this->adminId)['repetido']);

        // depois do estorno a fatura pode ser paga de novo (o pagamento antigo fica no histórico)
        $novo = $this->pagar->pagarFatura($faturaId, $this->pagto(), $this->adminId);
        $this->assertTrue($novo['ok'], (string) $novo['erro']);
        $this->assertSame(2, $this->contar("SELECT COUNT(*) FROM pagamentos_pagar WHERE fatura_id = {$faturaId}"));
    }

    public function testLimiteComprometidoEODisponivelAcompanhamAsParcelasNaoPagas(): void
    {
        $cartoes = new CartaoCorporativo($this->pdo);
        $id = $this->compraNoCartao(600, 3);

        $c = $cartoes->getById($this->cartaoId);
        $this->assertSame(600.0, $c['comprometido']);
        $this->assertSame(400.0, $c['limite_disponivel']);
        $this->assertFalse($c['limite_excedido']);

        $this->pagar->pagarFatura((int) $this->parcelas($id)[0]['fatura_id'], $this->pagto(), $this->adminId);
        $c = $cartoes->getById($this->cartaoId);
        $this->assertSame(400.0, $c['comprometido'], 'a fatura paga libera o limite');
        $this->assertSame(600.0, $c['limite_disponivel']);

        $this->compraNoCartao(900, 1); // passa do limite de 1000: registra, mas sinaliza
        $this->assertTrue($cartoes->getById($this->cartaoId)['limite_excedido'], 'compra já feita não é barrada, mas o excesso fica visível');
    }

    public function testCompraNovaNaoEntraEmFaturaJaPaga(): void
    {
        $data = date('Y-m-d', strtotime('-4 months'));
        $a = $this->compraNoCartao(100, 1, $data);
        $faturaPaga = (int) $this->parcelas($a)[0]['fatura_id'];
        $this->pagar->pagarFatura($faturaPaga, $this->pagto(), $this->adminId);

        $b = $this->compraNoCartao(50, 1, $data);

        $this->assertNotSame($faturaPaga, (int) $this->parcelas($b)[0]['fatura_id'], 'vai para a fatura seguinte em aberto');
        $this->assertSame(100.0, $this->faturas->getById($faturaPaga)['total'], 'a fatura paga não muda');
    }

    /* ── fora do cartão ── */

    public function testBoletoParceladoGeraParcelasMensaisQueFechamComOTotal(): void
    {
        $id = $this->boleto(100, 3, 'ALUGUEL', '2026-01-31');
        $p = $this->parcelas($id);

        $this->assertSame([33.33, 33.33, 33.34], array_map(fn($x) => (float) $x['valor'], $p), 'a sobra de centavos vai na última');
        $this->assertSame(100.0, round(array_sum(array_map(fn($x) => (float) $x['valor'], $p)), 2));
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], array_column($p, 'vencimento'), 'fim de mês não estoura (31/01 + 1 mês = 28/02)');
    }

    public function testPagarParcelaAtualizaOSaldoPendenteEAsProximas(): void
    {
        $id = $this->boleto(300, 3);
        $p = $this->parcelas($id);

        $r = $this->pagar->pagarParcela((int) $p[0]['id_conta_parcela'], $this->pagto('PIX'), $this->adminId);

        $this->assertTrue($r['ok'], (string) $r['erro']);
        $d = $this->despesas->getById($id);
        $this->assertSame(100.0, (float) $d['valor_pago']);
        $this->assertSame(200.0, (float) $d['valor_pendente']);
        $this->assertSame(2, (int) $d['parcelas_pendentes']);
        $this->assertSame($p[1]['vencimento'], $d['proximo_vencimento'], 'a parcela atual passa a ser a seguinte');
    }

    public function testPagarDuasVezesAMesmaParcelaERecusado(): void
    {
        $id = $this->boleto(300, 3);
        $parcela = (int) $this->parcelas($id)[0]['id_conta_parcela'];
        $chave = $this->chave();

        $a = $this->pagar->pagarParcela($parcela, ['forma' => 'PIX', 'chave' => $chave], $this->adminId);
        $b = $this->pagar->pagarParcela($parcela, ['forma' => 'PIX', 'chave' => $chave], $this->adminId);
        $c = $this->pagar->pagarParcela($parcela, $this->pagto('PIX'), $this->adminId);

        $this->assertTrue($b['repetido']);
        $this->assertSame($a['pagamento_id'], $b['pagamento_id']);
        $this->assertFalse($c['ok']);
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM pagamentos_pagar WHERE parcela_id = {$parcela}"));
    }

    public function testPagamentoComSaidaNoCaixaAmarraOsDoisELevaACategoriaDoEstoque(): void
    {
        $caixa = $this->fx->caixaAberto($this->adminId, 500);
        $id = $this->boleto(120, 1, 'ESTOQUE');
        $parcela = (int) $this->parcelas($id)[0]['id_conta_parcela'];

        $r = $this->pagar->pagarParcela($parcela, $this->pagto('DINHEIRO', ['lancar_no_caixa' => true]), $this->adminId);

        $this->assertTrue($r['ok'], (string) $r['erro']);
        $mov = $this->pdo->query("SELECT m.tipo, m.valor, m.categoria, m.caixa_id FROM movimentacoes_caixa m JOIN pagamentos_pagar pg ON pg.movimentacao_caixa_id = m.id_movimentacao WHERE pg.id_pagamento = {$r['pagamento_id']}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('SAIDA', $mov['tipo']);
        $this->assertSame(120.0, (float) $mov['valor']);
        $this->assertSame('PECAS', $mov['categoria']);
        $this->assertSame($caixa, (int) $mov['caixa_id']);
    }

    public function testSemCaixaAbertoOPagamentoComSaidaNoCaixaNaoGravaNada(): void
    {
        $this->pdo->exec("UPDATE caixa SET status = 'FECHADO', data_fechamento = NOW(), valor_fechamento = 0 WHERE status = 'ABERTO'");
        $id = $this->boleto(120, 1);
        $parcela = (int) $this->parcelas($id)[0]['id_conta_parcela'];

        $r = $this->pagar->pagarParcela($parcela, $this->pagto('DINHEIRO', ['lancar_no_caixa' => true]), $this->adminId);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('caixa', $r['erro']);
        $this->assertSame('PENDENTE', $this->parcelas($id)[0]['status'], 'o pagamento inteiro foi desfeito');
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM pagamentos_pagar WHERE parcela_id = {$parcela}"));

        // sem lançar no caixa (pago pelo banco) funciona
        $this->assertTrue($this->pagar->pagarParcela($parcela, $this->pagto('TRANSFERENCIA'), $this->adminId)['ok']);
    }

    public function testEstornoDePagamentoComCaixaDevolveONaoCaixaAberto(): void
    {
        $this->fx->caixaAberto($this->adminId, 500);
        $id = $this->boleto(120, 1);
        $parcela = (int) $this->parcelas($id)[0]['id_conta_parcela'];
        $pg = $this->pagar->pagarParcela($parcela, $this->pagto('DINHEIRO', ['lancar_no_caixa' => true]), $this->adminId)['pagamento_id'];

        $r = $this->pagar->estornarPagamento($pg, 'Paguei o boleto errado', $this->adminId);

        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->assertSame('PENDENTE', $this->parcelas($id)[0]['status']);
        $this->assertNotNull($this->pdo->query("SELECT estorno_caixa_id FROM pagamentos_pagar WHERE id_pagamento = {$pg}")->fetchColumn(), 'a devolução entra no caixa como lançamento novo');
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM movimentacoes_caixa WHERE chave_idempotencia = 'ESTORNO_PAGAR:{$pg}'"));
    }

    public function testDataDePagamentoNaoPodeSerFutura(): void
    {
        $id = $this->boleto(100, 1);
        $parcela = (int) $this->parcelas($id)[0]['id_conta_parcela'];

        $this->assertFalse($this->pagar->pagarParcela($parcela, $this->pagto('PIX', ['data' => date('Y-m-d', strtotime('+3 days'))]), $this->adminId)['ok']);
        $this->assertTrue($this->pagar->pagarParcela($parcela, $this->pagto('PIX', ['data' => date('Y-m-d', strtotime('-3 days'))]), $this->adminId)['ok'], 'pagamento retroativo é permitido');
    }

    /* ── cancelamento ── */

    public function testCancelarDespesaSemPagamentoLiberaOPendenteEOLimite(): void
    {
        $id = $this->compraNoCartao(600, 3);
        $this->assertSame(600.0, (new CartaoCorporativo($this->pdo))->getById($this->cartaoId)['comprometido']);

        $this->assertFalse($this->despesas->cancelar($id, $this->adminId, '')['ok'], 'exige motivo');
        $r = $this->despesas->cancelar($id, $this->adminId, 'Compra feita por engano');

        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->assertSame(['CANCELADA', 'CANCELADA', 'CANCELADA'], array_column($this->parcelas($id), 'status'));
        $this->assertSame(0.0, (new CartaoCorporativo($this->pdo))->getById($this->cartaoId)['comprometido']);
        $this->assertTrue($this->despesas->cancelar($id, $this->adminId, 'de novo')['repetido']);
    }

    public function testDespesaComParcelaPagaSoSeCancelaDepoisDeEstornar(): void
    {
        $id = $this->boleto(300, 3);
        $pg = $this->pagar->pagarParcela((int) $this->parcelas($id)[0]['id_conta_parcela'], $this->pagto(), $this->adminId)['pagamento_id'];

        $r = $this->despesas->cancelar($id, $this->adminId, 'cancelar tudo');
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Estorne', $r['erro']);

        $this->pagar->estornarPagamento($pg, 'Pagamento indevido', $this->adminId);
        $this->assertTrue($this->despesas->cancelar($id, $this->adminId, 'cancelar tudo')['ok']);
    }

    public function testParcelaCanceladaNaoPodeSerPaga(): void
    {
        $id = $this->boleto(100, 1);
        $parcela = (int) $this->parcelas($id)[0]['id_conta_parcela'];
        $this->despesas->cancelar($id, $this->adminId, 'cancelada');

        $this->assertFalse($this->pagar->pagarParcela($parcela, $this->pagto(), $this->adminId)['ok']);
    }

    /* ── validações ── */

    public function testValidacoesDaDespesa(): void
    {
        $base = ['descricao' => 'Teste', 'categoria' => 'ALUGUEL', 'valor_total' => '100,00', 'data_compra' => date('Y-m-d'), 'meio_pagamento' => 'BOLETO', 'qtd_parcelas' => 1];

        $this->assertTrue($this->despesas->criar($base, $this->adminId)['ok']);
        $this->assertFalse($this->despesas->criar(['valor_total' => '0'] + $base, $this->adminId)['ok'], 'valor zero');
        $this->assertFalse($this->despesas->criar(['descricao' => 'x'] + $base, $this->adminId)['ok'], 'sem descrição');
        $this->assertFalse($this->despesas->criar(['categoria' => 'NADA'] + $base, $this->adminId)['ok'], 'categoria inválida');
        $this->assertFalse($this->despesas->criar(['meio_pagamento' => 'CHEQUE'] + $base, $this->adminId)['ok'], 'meio inválido');
        $this->assertFalse($this->despesas->criar(['qtd_parcelas' => 49] + $base, $this->adminId)['ok'], 'parcelas demais');
        $this->assertFalse($this->despesas->criar(['meio_pagamento' => 'CARTAO_CREDITO'] + $base, $this->adminId)['ok'], 'cartão sem cartão');
        $this->assertFalse($this->despesas->criar(['meio_pagamento' => 'PIX', 'qtd_parcelas' => 3] + $base, $this->adminId)['ok'], 'PIX não parcela');
        $this->assertFalse($this->despesas->criar(['data_compra' => '31/12/2026'] + $base, $this->adminId)['ok'], 'data em formato inválido');
        $this->assertFalse($this->despesas->criar(['fornecedor_id' => 99999999] + $base, $this->adminId)['ok'], 'fornecedor inexistente');
    }

    public function testMesmoEnvioDoFormularioNaoDuplicaADespesa(): void
    {
        $base = ['descricao' => 'Conta de energia', 'categoria' => 'ENERGIA_AGUA', 'valor_total' => '250,00', 'data_compra' => date('Y-m-d'), 'meio_pagamento' => 'BOLETO', 'qtd_parcelas' => 1, 'chave' => $this->chave()];

        $a = $this->despesas->criar($base, $this->adminId);
        $b = $this->despesas->criar($base, $this->adminId);

        $this->assertTrue($b['repetido']);
        $this->assertSame($a['id'], $b['id']);
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM despesas WHERE chave_idempotencia = '{$base['chave']}'"));
    }

    public function testCartaoValidaDiasLimiteEDuplicidade(): void
    {
        $c = new CartaoCorporativo($this->pdo);

        $id = $c->create(['apelido' => 'Cartão Teste ' . uniqid(), 'instituicao' => 'Banco X', 'dia_fechamento' => 5, 'dia_vencimento' => 15, 'limite_total' => '12.500,50', 'final_cartao' => '1234']);
        $this->assertNotFalse($id, $c->getLastError());
        $this->fx->registrar('cartoes_corporativos', $id);
        $this->assertSame(12500.5, (float) $c->getById($id)['limite_total'], 'limite em formato brasileiro');

        $this->assertFalse($c->create(['apelido' => 'X', 'instituicao' => 'B', 'dia_fechamento' => 5, 'dia_vencimento' => 15]), 'apelido curto');
        $this->assertFalse($c->create(['apelido' => 'Cartão ok', 'instituicao' => 'B', 'dia_fechamento' => 31, 'dia_vencimento' => 15]), 'dia 31 não existe em todo mês');
        $this->assertFalse($c->create(['apelido' => 'Cartão ok', 'instituicao' => 'B', 'dia_fechamento' => 5, 'dia_vencimento' => 0]));
        $this->assertFalse($c->create(['apelido' => 'Cartão ok', 'instituicao' => 'B', 'dia_fechamento' => 5, 'dia_vencimento' => 15, 'final_cartao' => '12']), 'só os 4 últimos dígitos');
        $this->assertFalse($c->create(['apelido' => $c->getById($id)['apelido'], 'instituicao' => 'B', 'dia_fechamento' => 5, 'dia_vencimento' => 15]), 'apelido repetido');
    }

    /* ── pedido de compra → conta a pagar ── */

    public function testPedidoDeCompraGeraUmaContaAPagarQueNaoDuplica(): void
    {
        [$pedido] = $this->pedidoRealizado(10, 80);
        $this->pdo->exec("UPDATE pedidos_compra SET frete = 50 WHERE id_pedido = {$pedido}");

        $r = $this->despesas->criarDoPedido($pedido, ['meio_pagamento' => 'BOLETO', 'qtd_parcelas' => 2, 'primeiro_vencimento' => date('Y-m-d', strtotime('+15 days'))], $this->adminId);

        $this->assertTrue($r['ok'], (string) $r['erro']);
        $d = $this->despesas->getById($r['id']);
        $this->assertSame(850.0, (float) $d['valor_total'], '10 × R$ 80 + frete de R$ 50');
        $this->assertSame('ESTOQUE', $d['categoria']);
        $this->assertSame('PEDIDO_COMPRA', $d['origem']);
        $this->assertSame($this->fornecedorId, (int) $d['fornecedor_id']);
        $this->assertSame($r['id'], (int) $this->compras->getById($pedido)['conta_pagar_id']);
        $this->assertCount(2, $this->parcelas($r['id']));

        $de_novo = $this->despesas->criarDoPedido($pedido, ['meio_pagamento' => 'BOLETO'], $this->adminId);
        $this->assertTrue($de_novo['repetido']);
        $this->assertSame($r['id'], $de_novo['id']);
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM despesas WHERE origem = 'PEDIDO_COMPRA' AND origem_id = {$pedido}"));
    }

    public function testPedidoRascunhoOuCanceladoNaoGeraContaAPagar(): void
    {
        [$rascunho] = $this->rascunhoComItem();
        $this->assertFalse($this->despesas->criarDoPedido($rascunho, ['meio_pagamento' => 'BOLETO'], $this->adminId)['ok']);

        [$cancelado] = $this->pedidoRealizado();
        $this->compras->cancelar($cancelado, $this->adminId, 'desistência');
        $this->assertFalse($this->despesas->criarDoPedido($cancelado, ['meio_pagamento' => 'BOLETO'], $this->adminId)['ok']);
    }

    public function testCancelarAContaDoPedidoLiberaOPedidoParaLancarOutra(): void
    {
        [$pedido] = $this->pedidoRealizado(10, 80);
        $r = $this->despesas->criarDoPedido($pedido, ['meio_pagamento' => 'BOLETO'], $this->adminId);

        $this->despesas->cancelar($r['id'], $this->adminId, 'Lancei com o valor errado');

        $this->assertNull($this->compras->getById($pedido)['conta_pagar_id']);

        $outra = $this->despesas->criarDoPedido($pedido, ['meio_pagamento' => 'PIX', 'valor_total' => '780,00'], $this->adminId);
        $this->assertTrue($outra['ok'], (string) $outra['erro']);
        $this->assertNotSame($r['id'], $outra['id']);
        $this->assertSame(780.0, (float) $this->despesas->getById($outra['id'])['valor_total']);
    }

    /* ── totais e vencimentos ── */

    public function testTotaisSeparamVencidoEAVencerEContamCadaParcelaUmaVez(): void
    {
        $antes = $this->pagar->totais();

        $this->boleto(100, 1, 'ALUGUEL', date('Y-m-d', strtotime('-5 days')));   // vencida
        $this->boleto(60, 1, 'ALUGUEL', date('Y-m-d', strtotime('+3 days')));     // próxima
        $this->boleto(40, 1, 'ALUGUEL', date('Y-m-d', strtotime('+60 days')));    // futura
        $this->compraNoCartao(600, 3); // 3 parcelas de 200 (em faturas diferentes), vencidas ou não

        $depois = $this->pagar->totais();

        $this->assertSame(round($antes['pendente'] + 800, 2), $depois['pendente'], '100 + 60 + 40 + 600: o cartão conta as parcelas, não a fatura nem a compra');
        $this->assertGreaterThanOrEqual($antes['vencido'] + 100, $depois['vencido']);
        $this->assertSame(round($depois['pendente'] - $depois['vencido'], 2), $depois['a_vencer']);
        $this->assertGreaterThanOrEqual($antes['proximos_7_dias'] + 60, $depois['proximos_7_dias']);
    }

    public function testListagemDeParcelasFiltraPorSituacao(): void
    {
        $vencida = $this->boleto(100, 1, 'ALUGUEL', date('Y-m-d', strtotime('-5 days')));
        $futura = $this->boleto(50, 1, 'ALUGUEL', date('Y-m-d', strtotime('+20 days')));
        $paga = $this->boleto(30, 1);
        $this->pagar->pagarParcela((int) $this->parcelas($paga)[0]['id_conta_parcela'], $this->pagto(), $this->adminId);

        $ids = fn(string $situacao) => array_map('intval', array_column($this->pagar->listarParcelas(['situacao' => $situacao]), 'id_despesa'));

        $this->assertContains($vencida, $ids('vencidas'));
        $this->assertNotContains($futura, $ids('vencidas'));
        $this->assertContains($futura, $ids('abertas'));
        $this->assertNotContains($paga, $ids('abertas'));
        $this->assertContains($paga, $ids('pagas'));
        $this->assertSame('VENCIDA', array_values(array_filter($this->pagar->listarParcelas(['situacao' => 'abertas']), fn($p) => (int) $p['id_despesa'] === $vencida))[0]['situacao']);
    }

    public function testDinheiroHelperDividEFormata(): void
    {
        $this->assertSame(1500.0, Dinheiro::parse('1.500,00'));
        $this->assertSame(1500.5, Dinheiro::parse('1500,5'));
        $this->assertSame(1500.5, Dinheiro::parse('1500.50'));
        $this->assertSame(0.0, Dinheiro::parse('abc'));
        $this->assertSame([33.33, 33.33, 33.34], Dinheiro::dividir(100, 3));
        $this->assertSame([0.01, 0.01, 0.01], Dinheiro::dividir(0.03, 3));
        $this->assertSame('2026-02-28', Dinheiro::somarMeses('2026-01-31', 1));
        $this->assertSame('2024-02-29', Dinheiro::somarMeses('2024-01-31', 1), 'ano bissexto');
        $this->assertSame('R$ 1.234,50', Dinheiro::formatar(1234.5));
    }
}
