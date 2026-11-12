<?php

require_once __DIR__ . '/OsTestCase.php';
require_once __DIR__ . '/../app/models/Financeiro.php';
require_once __DIR__ . '/../app/models/Despesa.php';
require_once __DIR__ . '/../app/models/ContaPagar.php';
require_once __DIR__ . '/../app/models/ContaReceber.php';

/**
 * Indicadores financeiros: cada número com uma definição, e os totais não contam o mesmo
 * dinheiro duas vezes. Receita faturada não é dinheiro recebido; saldo a receber não é lucro;
 * a compra no cartão, as parcelas da fatura e o pagamento da fatura são UMA despesa.
 *
 * Como a base de teste pode ter outras linhas, compara-se sempre o ANTES com o DEPOIS.
 */
final class FinanceiroTest extends OsTestCase
{
    private Financeiro $fin;
    private OrdemServicoPagamento $pag;
    private Despesa $despesas;
    private ContaPagar $pagar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fin      = new Financeiro($this->pdo);
        $this->pag      = new OrdemServicoPagamento($this->pdo);
        $this->despesas = new Despesa($this->pdo);
        $this->pagar    = new ContaPagar($this->pdo);
    }

    private function ind(): array
    {
        return $this->fin->indicadores('2000-01-01', '2100-12-31');
    }

    private function delta(array $antes, array $depois, string $chave, ?string $sub = null): float
    {
        $a = $sub ? $antes[$chave][$sub] : $antes[$chave];
        $d = $sub ? $depois[$chave][$sub] : $depois[$chave];

        return round((float) $d - (float) $a, 2);
    }

    /** OS padrão (2 peças a custo 60 + 1 serviço; total R$ 500) com plano e conferida; comissões de R$ 50 + R$ 30. */
    private function osComCustos(int $parcelas = 5, bool $concluir = true): int
    {
        $os = $this->novaOs();

        $this->pag->salvarPlano($os, ['pagamento_tipo' => $parcelas > 1 ? 'PARCELADO' : 'AVISTA', 'pagamento_forma' => 'CARTAO_CREDITO', 'parcelas' => $parcelas, 'primeiro_vencimento' => date('Y-m-d')]);
        $this->ateRetirada($os);

        // As comissões são calculadas pelo fluxo da OS (sem regra cadastrada, zero): fixa-se um valor conhecido.
        $this->pdo->exec("UPDATE ordem_servico_mao_obra SET valor_mao_obra = 50 WHERE ordem_servico_id = {$os} AND tecnico_id = {$this->tec1}");
        $this->pdo->exec("UPDATE ordem_servico_mao_obra SET valor_mao_obra = 30 WHERE ordem_servico_id = {$os} AND tecnico_id = {$this->tec2}");

        if ($concluir) {
            $this->pdo->exec("UPDATE ordens_servico SET status = 'CONCLUIDA', data_conclusao = NOW() WHERE id_ordem_servico = {$os}");
        }

        return $os;
    }

    private function receberParcelas(int $os, int $quantas): void
    {
        foreach (array_slice($this->pag->parcelas($os), 0, $quantas) as $p) {
            $this->assertNull($this->pag->pagarParcela((int) $p['id_parcela'], 'CARTAO_CREDITO', $this->adminId));
        }
    }

    /* ── receita faturada ≠ dinheiro recebido ≠ lucro ── */

    public function testFaturamentoBrutoContaOsEntreguesMasNaoEDinheiroRecebido(): void
    {
        $antes = $this->ind();
        $this->osComCustos(5, true);
        $depois = $this->ind();

        $this->assertSame(500.0, $this->delta($antes, $depois, 'faturamento_bruto'));
        $this->assertSame(1, $depois['os_entregues'] - $antes['os_entregues']);
        $this->assertSame(0.0, $this->delta($antes, $depois, 'recebido_bruto'), 'entregar/concluir não é receber');
        $this->assertSame(500.0, $this->delta($antes, $depois, 'a_receber', 'a_receber'), 'a cobrança está toda a receber');
    }

    public function testOsNaoEntregueNaoEntraNoFaturamento(): void
    {
        $antes = $this->ind();
        $this->osComCustos(5, false); // conferida, aguardando retirada
        $depois = $this->ind();

        $this->assertSame(0.0, $this->delta($antes, $depois, 'faturamento_bruto'));
        $this->assertSame(500.0, $this->delta($antes, $depois, 'a_receber', 'a_receber'), 'mas já é cobrança');
    }

    public function testRecebimentoParcialSomaSoOQueEntrouEMantemORestoAReceber(): void
    {
        $antes = $this->ind();
        $os = $this->osComCustos(5, true);
        $this->receberParcelas($os, 1);
        $depois = $this->ind();

        $this->assertSame(500.0, $this->delta($antes, $depois, 'faturamento_bruto'));
        $this->assertSame(100.0, $this->delta($antes, $depois, 'recebido_bruto'));
        $this->assertSame(400.0, $this->delta($antes, $depois, 'a_receber', 'a_receber'));
        $this->assertSame(4, $depois['a_receber']['parcelas_pendentes'] - $antes['a_receber']['parcelas_pendentes']);
    }

    public function testLucroEfetivoNaoTrataParceladoComoSeEstivesseDisponivel(): void
    {
        $antes = $this->ind();
        $os = $this->osComCustos(5, true);

        // custos da OS: 2 peças a R$ 60 = 120 + comissões 50 + 30 = 80  → R$ 200
        $this->receberParcelas($os, 1);
        $depois = $this->ind();

        $this->assertSame(120.0, $this->delta($antes, $depois, 'custos', 'pecas'));
        $this->assertSame(80.0, $this->delta($antes, $depois, 'custos', 'comissoes'));
        $this->assertSame(300.0, $this->delta($antes, $depois, 'resultado_operacional_estimado'), 'estimado: 500 − 120 − 80 (inclui o que ainda vai entrar)');
        $this->assertSame(-100.0, $this->delta($antes, $depois, 'lucro_efetivo'), 'efetivo: só entraram 100 e os custos de 200 já foram feitos');
    }

    public function testQuandoTudoEntraOLucroEfetivoFechaComOEstimado(): void
    {
        $antes = $this->ind();
        $os = $this->osComCustos(5, true);

        foreach ($this->pag->parcelas($os) as $p) {
            $this->pag->pagarParcela((int) $p['id_parcela'], 'CARTAO_CREDITO', $this->adminId);
        }

        $depois = $this->ind();
        $this->assertSame(500.0, $this->delta($antes, $depois, 'recebido_bruto'));
        $this->assertSame(300.0, $this->delta($antes, $depois, 'lucro_efetivo'));
        $this->assertSame(0.0, $this->delta($antes, $depois, 'a_receber', 'a_receber'));
    }

    public function testTaxasReduzemORecebidoLiquidoEOResultado(): void
    {
        $antes = $this->ind();
        $os = $this->osComCustos(1, true);
        $p = $this->pag->parcelas($os)[0];
        $this->pag->pagarParcela((int) $p['id_parcela'], 'CARTAO_CREDITO', $this->adminId, 15.0);
        $depois = $this->ind();

        $this->assertSame(500.0, $this->delta($antes, $depois, 'recebido_bruto'));
        $this->assertSame(15.0, $this->delta($antes, $depois, 'taxas'));
        $this->assertSame(485.0, $this->delta($antes, $depois, 'recebido_liquido'));
        $this->assertSame(285.0, $this->delta($antes, $depois, 'resultado_operacional_estimado'), '500 − 120 − 80 − 15');
    }

    public function testPeriodoSemMovimentoNaoTrazNada(): void
    {
        $this->osComCustos(1, true);

        $passado = $this->fin->indicadores('2000-01-01', '2000-01-31');

        $this->assertSame(0.0, $passado['faturamento_bruto']);
        $this->assertSame(0.0, $passado['recebido_bruto']);
        $this->assertSame(0.0, $passado['despesas_pagas']['total']);
    }

    /* ── despesas e o cartão ── */

    public function testDespesaDeEstoqueNaoAfetaOResultadoMasDeAluguelAfeta(): void
    {
        $antes = $this->ind();

        $base = ['data_compra' => date('Y-m-d'), 'meio_pagamento' => 'BOLETO', 'qtd_parcelas' => 1];
        $this->despesas->criar(['descricao' => 'Compra de pastilhas', 'categoria' => 'ESTOQUE', 'valor_total' => '1000,00'] + $base, $this->adminId);
        $this->despesas->criar(['descricao' => 'Aluguel do mês', 'categoria' => 'ALUGUEL', 'valor_total' => '400,00'] + $base, $this->adminId);

        $depois = $this->ind();

        $this->assertSame(400.0, $this->delta($antes, $depois, 'custos', 'despesas_operacionais'), 'estoque entra no resultado quando a peça é consumida, não na compra');
        $this->assertSame(-400.0, $this->delta($antes, $depois, 'resultado_operacional_estimado'));
        $this->assertSame(1400.0, $this->delta($antes, $depois, 'a_pagar', 'pendente'), 'mas as duas são obrigações a pagar');
        $this->assertSame(0.0, $this->delta($antes, $depois, 'despesas_pagas', 'total'), 'e nenhuma foi paga ainda');
    }

    public function testCompraNoCartaoPagaNaFaturaContaUmaDespesaSoEUmaSaidaSo(): void
    {
        $cartao = $this->fx->cartao(['dia_fechamento' => 10, 'dia_vencimento' => 20]);
        $antes = $this->ind();

        $r = $this->despesas->criar([
            'descricao' => 'Amortecedor', 'categoria' => 'ESTOQUE', 'valor_total' => '600,00', 'data_compra' => date('Y-m-d', strtotime('-4 months')),
            'meio_pagamento' => 'CARTAO_CREDITO', 'cartao_id' => $cartao, 'qtd_parcelas' => 3,
        ], $this->adminId);
        $parcelas = $this->despesas->parcelas($r['id']);

        $meio = $this->ind();
        $this->assertSame(600.0, $this->delta($antes, $meio, 'a_pagar', 'pendente'), 'a compra vira 3 obrigações de 200');
        $this->assertSame(0.0, $this->delta($antes, $meio, 'despesas_pagas', 'total'), 'comprar no cartão ainda não é dinheiro que saiu');

        $this->pagar->pagarFatura((int) $parcelas[0]['fatura_id'], ['forma' => 'PIX', 'chave' => bin2hex(random_bytes(8))], $this->adminId);

        $depois = $this->ind();
        $this->assertSame(400.0, $this->delta($antes, $depois, 'a_pagar', 'pendente'), 'pagar a primeira fatura tira 200 do a pagar');
        $this->assertSame(200.0, $this->delta($antes, $depois, 'despesas_pagas', 'contas_a_pagar'), 'e conta 200 como despesa paga — não 200 + 200 nem 600 + 200');
        $this->assertSame(-200.0, $this->delta($antes, $depois, 'fluxo_realizado'));
    }

    public function testSaidasDoCaixaLigadasAUmPagamentoNaoSaoContadasDuasVezes(): void
    {
        $caixa = $this->caixaId;
        $antes = $this->ind();

        (new MovimentacaoCaixa($this->pdo))->registrar($caixa, 'SAIDA', 30, 'DINHEIRO', 'OUTRO', 'Lanche da equipe', $this->adminId, 'MANUAL');

        $r = $this->despesas->criar(['descricao' => 'Material de limpeza', 'categoria' => 'OUTRAS', 'valor_total' => '100,00', 'data_compra' => date('Y-m-d'), 'meio_pagamento' => 'DINHEIRO', 'qtd_parcelas' => 1], $this->adminId);
        $parcela = (int) $this->despesas->parcelas($r['id'])[0]['id_conta_parcela'];
        $pg = $this->pagar->pagarParcela($parcela, ['forma' => 'DINHEIRO', 'chave' => bin2hex(random_bytes(8)), 'lancar_no_caixa' => true], $this->adminId);
        $this->assertTrue($pg['ok'], (string) $pg['erro']);

        $depois = $this->ind();
        $this->assertSame(30.0, $this->delta($antes, $depois, 'despesas_pagas', 'caixa_direto'), 'só a saída avulsa');
        $this->assertSame(100.0, $this->delta($antes, $depois, 'despesas_pagas', 'contas_a_pagar'), 'a paga pela conta a pagar aparece uma vez');
        $this->assertSame(130.0, $this->delta($antes, $depois, 'despesas_pagas', 'total'));
    }

    public function testPagamentoEstornadoSaiDasDespesasPagasESuaDevolucaoNaoViraReceita(): void
    {
        $antes = $this->ind();

        $r = $this->despesas->criar(['descricao' => 'Conta de internet', 'categoria' => 'INTERNET_TELEFONE', 'valor_total' => '90,00', 'data_compra' => date('Y-m-d'), 'meio_pagamento' => 'DINHEIRO', 'qtd_parcelas' => 1], $this->adminId);
        $parcela = (int) $this->despesas->parcelas($r['id'])[0]['id_conta_parcela'];
        $pg = $this->pagar->pagarParcela($parcela, ['forma' => 'DINHEIRO', 'chave' => bin2hex(random_bytes(8)), 'lancar_no_caixa' => true], $this->adminId)['pagamento_id'];
        $this->pagar->estornarPagamento($pg, 'Paguei a conta errada', $this->adminId);

        $depois = $this->ind();
        $this->assertSame(0.0, $this->delta($antes, $depois, 'despesas_pagas', 'total'));
        $this->assertSame(0.0, $this->delta($antes, $depois, 'outras_entradas'), 'a devolução ao caixa não é uma entrada de receita');
        $this->assertSame(0.0, $this->delta($antes, $depois, 'fluxo_realizado'));
        $this->assertSame(90.0, $this->delta($antes, $depois, 'a_pagar', 'pendente'), 'e a conta volta a ser devida');
    }

    public function testFolhaPagaSeparaComissaoDeSalarioFixo(): void
    {
        $os = $this->osComCustos(1, true);
        $antes = $this->ind();

        // fechamento de R$ 150: R$ 50 são a comissão desta OS; R$ 100 são salário fixo
        $this->pdo->prepare("INSERT INTO funcionario_fechamento_pagamento (tecnico_id, periodo_inicio, periodo_fim, valor_total, data_pagamento, forma_pagamento, criado_por) VALUES (?, CURDATE(), CURDATE(), 150, NOW(), 'PIX', ?)")
            ->execute([$this->tec1, $this->adminId]);
        $fechamento = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("UPDATE ordem_servico_mao_obra SET fechamento_pagamento_id = {$fechamento}, status_pagamento = 'PAGO' WHERE ordem_servico_id = {$os} AND tecnico_id = {$this->tec1}");

        $depois = $this->ind();
        $this->assertSame(150.0, $this->delta($antes, $depois, 'despesas_pagas', 'folha_comissoes'));
        $this->assertSame(100.0, $this->delta($antes, $depois, 'custos', 'salario_fixo'), 'só a parte fixa entra à parte; a comissão já é custo da OS');
        $this->assertSame(-100.0, $this->delta($antes, $depois, 'resultado_operacional_estimado'));
    }

    /* ── fluxo ── */

    public function testFluxoProjetadoDistribuiEntradasESaidasPorMesDeVencimento(): void
    {
        $mes = date('Y-m', strtotime('first day of next month'));
        $primeiroDoMes = $mes . '-10';

        $linhas = fn() => array_column($this->fin->fluxoProjetado(4), null, 'chave');
        $antes = $linhas();

        $os = $this->novaOs(['itens' => [['tipo' => 'servico', 'id' => 'custom_1', 'nome' => 'S', 'qty' => 1, 'preco' => 200, 'custom' => 1, 'categoria' => 'G']]]);
        $this->pag->salvarPlano($os, ['pagamento_tipo' => 'AVISTA', 'parcelas' => 1, 'primeiro_vencimento' => $primeiroDoMes]);
        $this->ateRetirada($os);

        $this->despesas->criar(['descricao' => 'Aluguel', 'categoria' => 'ALUGUEL', 'valor_total' => '150,00', 'data_compra' => date('Y-m-d'), 'meio_pagamento' => 'BOLETO', 'qtd_parcelas' => 1, 'primeiro_vencimento' => $primeiroDoMes], $this->adminId);

        $depois = $linhas();
        $this->assertArrayHasKey($mes, $depois);
        $this->assertSame(200.0, round($depois[$mes]['entradas'] - ($antes[$mes]['entradas'] ?? 0), 2));
        $this->assertSame(150.0, round($depois[$mes]['saidas'] - ($antes[$mes]['saidas'] ?? 0), 2));
        $this->assertSame(50.0, round($depois[$mes]['saldo'] - ($antes[$mes]['saldo'] ?? 0), 2));
    }

    public function testParcelasAtrasadasFicamEmBlocoProprioNoFluxo(): void
    {
        $this->despesas->criar(['descricao' => 'Conta atrasada', 'categoria' => 'OUTRAS', 'valor_total' => '75,00', 'data_compra' => date('Y-m-d', strtotime('-40 days')), 'meio_pagamento' => 'BOLETO', 'qtd_parcelas' => 1, 'primeiro_vencimento' => date('Y-m-d', strtotime('-10 days'))], $this->adminId);

        $linhas = array_column($this->fin->fluxoProjetado(3), null, 'chave');

        $this->assertArrayHasKey('ATRASADO', $linhas);
        $this->assertGreaterThanOrEqual(75.0, $linhas['ATRASADO']['saidas']);
    }
}
