<?php

require_once __DIR__ . '/OsTestCase.php';
require_once __DIR__ . '/FakeTransporte.php';
require_once __DIR__ . '/../app/core/ProvedorPagamento.php';
require_once __DIR__ . '/../app/models/CobrancaOnline.php';
require_once __DIR__ . '/../app/models/Financeiro.php';

/**
 * Pagamento online pelo portal.
 *
 * O provedor SIMULADO exercita o caminho inteiro (cobrança → webhook assinado → baixa) sem mover
 * dinheiro; o adaptador do Mercado Pago é testado com um transporte falso que devolve as respostas
 * descritas na documentação. As garantias: sem cobrança duplicada, webhook repetido não repete
 * efeito, valor conferido, recusado/cancelado/estornado atualizam os registros, e nenhum dado de
 * cartão existe em lugar nenhum.
 */
final class PagamentoOnlineTest extends OsTestCase
{
    private const SEGREDO = 'segredo-simulado-de-teste';

    private OrdemServicoPagamento $pag;
    private ProvedorSimulado $sim;
    private CobrancaOnline $cob;

    protected function setUp(): void
    {
        parent::setUp();

        $cfg = $this->cfgPagamentos();
        $this->pag = new OrdemServicoPagamento($this->pdo);
        $this->sim = new ProvedorSimulado($cfg);
        $this->cob = new CobrancaOnline($this->pdo, $this->sim, $cfg);
    }

    private function cfgPagamentos(array $extra = []): array
    {
        return $extra + ['provedor' => 'simulado', 'mp_access_token' => '', 'mp_webhook_secret' => '', 'max_parcelas' => 1, 'simulado_segredo' => self::SEGREDO, 'permitir_simulado' => true];
    }

    private function osCobrada(float $valor = 600, int $parcelas = 3): int
    {
        $os = $this->novaOs(['itens' => [['tipo' => 'servico', 'id' => 'custom_1', 'nome' => 'Serviço', 'qty' => 1, 'preco' => $valor, 'custom' => 1, 'categoria' => 'Geral']]]);
        $this->assertTrue($this->pag->salvarPlano($os, ['pagamento_tipo' => $parcelas > 1 ? 'PARCELADO' : 'AVISTA', 'parcelas' => $parcelas, 'primeiro_vencimento' => date('Y-m-d')]));
        $this->ateRetirada($os);

        return $os;
    }

    private function parcelaIds(int $os): array
    {
        return array_map('intval', array_column($this->pag->parcelas($os), 'id_parcela'));
    }

    private function criar(int $os, array $parcelas): array
    {
        return $this->cob->criar($os, $parcelas, null, 'https://x.test/portal?t=1', 'https://x.test/webhookPagamento');
    }

    private function evento(string $tipo, array $cobranca, array $extra = []): array
    {
        return $extra + [
            'evento_id' => 'phpunit-' . bin2hex(random_bytes(6)), 'referencia' => $cobranca['referencia'], 'provedor_cobranca_id' => $cobranca['provedor_cobranca_id'],
            'tipo' => $tipo, 'valor' => (float) $cobranca['valor'], 'forma' => 'PIX', 'parcelas' => 1, 'taxa' => 0.0, 'detalhe' => null,
        ];
    }

    private function dispara(string $tipo, int $cobrancaId, array $extra = []): string
    {
        return $this->cob->processarEvento('SIMULADO', $this->evento($tipo, $this->cob->getById($cobrancaId), $extra));
    }

    private function p(int $parcelaId): array
    {
        return $this->pdo->query("SELECT * FROM ordem_servico_parcelas WHERE id_parcela = {$parcelaId}")->fetch(PDO::FETCH_ASSOC);
    }

    /* ── criar a cobrança ── */

    public function testCriaCobrancaPendenteParaAParcelaSemBaixarNada(): void
    {
        $os = $this->osCobrada();
        [$p1] = $this->parcelaIds($os);

        $r = $this->criar($os, [$p1]);

        $this->assertTrue($r['ok'], (string) $r['erro']);
        $c = $this->cob->getById($r['cobranca_id']);
        $this->assertSame('PENDENTE', $c['status']);
        $this->assertSame(200.0, (float) $c['valor']);
        $this->assertSame('SIMULADO', $c['provedor']);
        $this->assertMatchesRegularExpression('/^MECANIZE-\d{6}$/', $c['referencia']);
        $this->assertStringContainsString('portalPagamentoSimulado?ref=' . $c['referencia'], $c['checkout_url']);
        $this->assertSame(0, (int) $this->p($p1)['pago'], 'criar a cobrança não é receber');
        $this->assertSame($r['url'], $c['checkout_url']);
    }

    public function testPagarDeNovoDevolveAMesmaCobrancaSemDuplicar(): void
    {
        $os = $this->osCobrada();
        [$p1] = $this->parcelaIds($os);

        $a = $this->criar($os, [$p1]);
        $b = $this->criar($os, [$p1]);

        $this->assertTrue($b['repetido']);
        $this->assertSame($a['cobranca_id'], $b['cobranca_id']);
        $this->assertSame($a['url'], $b['url']);
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM cobrancas_online WHERE ordem_servico_id = {$os}"));
    }

    public function testNaoSobrepoeCobrancasNaMesmaParcela(): void
    {
        $os = $this->osCobrada();
        [$p1, $p2] = $this->parcelaIds($os);
        $this->criar($os, [$p1]);

        $r = $this->criar($os, [$p1, $p2]);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('em andamento', $r['erro']);
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM cobrancas_online WHERE ordem_servico_id = {$os}"));
        $this->assertTrue($this->criar($os, [$p2])['ok'], 'mas outra parcela pode ter a sua');
    }

    public function testValidacoesDaCriacao(): void
    {
        $os = $this->osCobrada();
        [$p1] = $this->parcelaIds($os);
        $outra = $this->osCobrada(100, 1);
        [$alheia] = $this->parcelaIds($outra);

        $this->assertFalse($this->criar($os, [])['ok'], 'nada escolhido');
        $this->assertFalse($this->criar($os, [$alheia])['ok'], 'parcela de outra OS');
        $this->assertFalse($this->criar($os, [99999999])['ok'], 'parcela inexistente');

        $this->assertNull($this->pag->pagarParcela($p1, 'PIX', $this->adminId));
        $r = $this->criar($os, [$p1]);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('já está paga', $r['erro']);

        $emExecucao = $this->novaOs();
        $this->pag->salvarPlano($emExecucao, ['pagamento_tipo' => 'AVISTA', 'parcelas' => 1]);
        $r = $this->criar($emExecucao, $this->parcelaIds($emExecucao));
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('ainda não tem cobrança', $r['erro']);

        $semProvedor = new CobrancaOnline($this->pdo, null, $this->cfgPagamentos(['provedor' => '']));
        $r = $semProvedor->criar($os, [$this->parcelaIds($os)[1]], null, 'x', 'y');
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('não está disponível', $r['erro']);
        $this->assertFalse($semProvedor->disponivel());
    }

    public function testFalhaDoProvedorLiberaAParcelaParaNovaTentativa(): void
    {
        $os = $this->osCobrada();
        [$p1] = $this->parcelaIds($os);
        $quebrado = new class implements ProvedorPagamento {
            public function nome(): string { return 'SIMULADO'; }
            public function configurado(): bool { return true; }
            public function criarCobranca(array $dados): array { return ['ok' => false, 'id' => null, 'url' => null, 'erro' => 'provedor fora do ar']; }
            public function validarWebhook(string $c, array $h, array $q): bool { return false; }
            public function interpretarWebhook(string $c, array $h, array $q): array { return []; }
        };

        $r = (new CobrancaOnline($this->pdo, $quebrado, $this->cfgPagamentos()))->criar($os, [$p1], null, 'x', 'y');

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Tente novamente', $r['erro']);
        $this->assertStringNotContainsString('fora do ar', $r['erro'], 'o detalhe técnico não vai para o cliente');
        $this->assertSame('CANCELADA', $this->pdo->query("SELECT status FROM cobrancas_online WHERE ordem_servico_id = {$os}")->fetchColumn());
        $this->assertTrue($this->criar($os, [$p1])['ok'], 'a parcela ficou livre para tentar de novo');
    }

    public function testCobrancaPresaSemLinkEAbandonadaNaProximaTentativa(): void
    {
        $os = $this->osCobrada();
        [$p1] = $this->parcelaIds($os);
        $a = $this->criar($os, [$p1]);
        $this->pdo->exec("UPDATE cobrancas_online SET checkout_url = NULL, criada_em = DATE_SUB(NOW(), INTERVAL 10 MINUTE) WHERE id_cobranca = {$a['cobranca_id']}");

        $b = $this->criar($os, [$p1]);

        $this->assertTrue($b['ok'], (string) $b['erro']);
        $this->assertNotSame($a['cobranca_id'], $b['cobranca_id']);
        $this->assertSame('CANCELADA', $this->cob->getById($a['cobranca_id'])['status']);
    }

    /* ── plano refeito depois de existir cobrança online ── */

    public function testRefazerOPlanoNaoQuebraComCobrancaRecusadaNoHistorico(): void
    {
        $os = $this->osCobrada(600, 3);
        [$p1] = $this->parcelaIds($os);
        $c = $this->criar($os, [$p1]);
        $this->assertSame('cobranca_recusada', $this->dispara('RECUSADO', $c['cobranca_id']));

        // A conferência refaz as parcelas devidas: o vínculo da cobrança recusada não pode travar isso.
        $this->assertTrue($this->pag->salvarPlano($os, ['pagamento_tipo' => 'PARCELADO', 'parcelas' => 3, 'primeiro_vencimento' => date('Y-m-d')]), 'o plano foi refeito');
        $this->assertCount(3, $this->pag->parcelas($os));
        $this->assertSame('RECUSADA', $this->cob->getById($c['cobranca_id'])['status'], 'a cobrança continua no histórico');
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM cobranca_parcelas WHERE cobranca_id = {$c['cobranca_id']}"));
    }

    public function testRefazerOPlanoCancelaACobrancaEmAndamentoEPagamentoTardioNaoBaixaAsCegas(): void
    {
        $os = $this->osCobrada(600, 3);
        [$p1] = $this->parcelaIds($os);
        $c = $this->criar($os, [$p1]);

        $this->assertTrue($this->pag->salvarPlano($os, ['pagamento_tipo' => 'AVISTA']));

        $this->assertSame('CANCELADA', $this->cob->getById($c['cobranca_id'])['status'], 'a cobrança do plano antigo foi cancelada');
        $novas = $this->parcelaIds($os);
        $this->assertCount(1, $novas);
        $this->assertTrue($this->criar($os, $novas)['ok'], 'o cliente pode pagar o plano novo');

        // O cliente pagou a cobrança antiga mesmo assim, no provedor: nada é baixado, o administrador é avisado.
        $res = $this->dispara('PAGO', $c['cobranca_id']);

        $this->assertSame('paga_sem_parcela', $res);
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM ordem_servico_parcelas WHERE ordem_servico_id = {$os} AND pago = 1"));
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM movimentacoes_caixa WHERE ordem_servico_id = {$os}"));
        $this->assertSame('PAGA', $this->cob->getById($c['cobranca_id'])['status']);
        $this->assertStringContainsString('não existem mais', (string) $this->cob->getById($c['cobranca_id'])['detalhe_status']);
        $this->assertGreaterThan(0, $this->contar("SELECT COUNT(*) FROM notificacoes WHERE chave_idempotencia = 'COBRANCA:{$c['cobranca_id']}:SEM_PARCELA' AND usuario_id = {$this->adminId}"));
    }

    /* ── confirmação ── */

    public function testPagamentoConfirmadoBaixaAParcelaOnlineSemMexerNoCaixa(): void
    {
        $os = $this->osCobrada();
        [$p1] = $this->parcelaIds($os);
        $c = $this->criar($os, [$p1]);
        $movAntes = $this->contar("SELECT COUNT(*) FROM movimentacoes_caixa WHERE ordem_servico_id = {$os}");

        $res = $this->dispara('PAGO', $c['cobranca_id'], ['taxa' => 7.5, 'forma' => 'CARTAO_CREDITO', 'parcelas' => 1]);

        $this->assertSame('paga', $res);
        $p = $this->p($p1);
        $this->assertSame(1, (int) $p['pago']);
        $this->assertSame('ONLINE', $p['origem_recebimento']);
        $this->assertSame($c['cobranca_id'], (int) $p['cobranca_online_id']);
        $this->assertNull($p['movimentacao_caixa_id'], 'recebimento online não passa pelo caixa físico');
        $this->assertSame(7.5, (float) $p['taxa_valor']);
        $this->assertSame('CARTAO_CREDITO', $p['forma_pagamento']);
        $this->assertSame($movAntes, $this->contar("SELECT COUNT(*) FROM movimentacoes_caixa WHERE ordem_servico_id = {$os}"));

        $cobranca = $this->cob->getById($c['cobranca_id']);
        $this->assertSame('PAGA', $cobranca['status']);
        $this->assertNotNull($cobranca['paga_em']);
        $this->assertSame('RECEBIDA', $this->pdo->query("SELECT estado_novo FROM historico_estados WHERE entidade = 'PARCELA_OS' AND entidade_id = {$p1}")->fetchColumn());
    }

    public function testConfirmacaoAtualizaContasAReceberEOsIndicadores(): void
    {
        $fin = new Financeiro($this->pdo);
        $os = $this->osCobrada(600, 3);
        $antes = $fin->indicadores('2000-01-01', '2100-12-31');
        [$p1] = $this->parcelaIds($os);
        $c = $this->criar($os, [$p1]);

        $this->dispara('PAGO', $c['cobranca_id'], ['taxa' => 4.0]);

        $depois = $fin->indicadores('2000-01-01', '2100-12-31');
        $this->assertSame(round($antes['recebido_bruto'] + 200, 2), $depois['recebido_bruto']);
        $this->assertSame(round($antes['taxas'] + 4, 2), $depois['taxas']);
        $this->assertSame(round($antes['a_receber']['a_receber'] - 200, 2), $depois['a_receber']['a_receber'], 'a parcela paga deixa de ser pendente');
        $this->assertSame($antes['outras_entradas'], $depois['outras_entradas']);
    }

    public function testMesmoWebhookDuasVezesNaoRepeteEfeito(): void
    {
        $os = $this->osCobrada();
        [$p1] = $this->parcelaIds($os);
        $c = $this->criar($os, [$p1]);
        $ev = $this->evento('PAGO', $this->cob->getById($c['cobranca_id']), ['taxa' => 3.0]);

        $a = $this->cob->processarEvento('SIMULADO', $ev);
        $b = $this->cob->processarEvento('SIMULADO', $ev);
        $outroIdMesmoFato = $this->cob->processarEvento('SIMULADO', ['evento_id' => 'phpunit-outro-id'] + $ev);

        $this->assertSame('paga', $a);
        $this->assertSame('duplicado', $b);
        $this->assertSame('ja_paga', $outroIdMesmoFato, 'mesmo fato com id novo: o estado impede a segunda baixa');
        $this->assertSame(1, (int) $this->p($p1)['pagamentos_realizados']);
        $this->assertSame(3.0, (float) $this->p($p1)['taxa_valor'], 'a taxa não é somada de novo');
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM mensagens WHERE chave_idempotencia = 'EV:PAGAMENTO_CONFIRMADO:COBRANCA:{$c['cobranca_id']}:PAGA'"), 'o cliente é avisado uma vez');
    }

    public function testValorDivergenteNaoBaixaEAvisaOAdministrador(): void
    {
        $os = $this->osCobrada();
        [$p1] = $this->parcelaIds($os);
        $c = $this->criar($os, [$p1]);

        $res = $this->dispara('PAGO', $c['cobranca_id'], ['valor' => 50.0]);

        $this->assertSame('valor_divergente', $res);
        $this->assertSame(0, (int) $this->p($p1)['pago']);
        $this->assertSame('PENDENTE', $this->cob->getById($c['cobranca_id'])['status']);
        $this->assertStringContainsString('diferente', $this->cob->getById($c['cobranca_id'])['detalhe_status']);
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = {$this->adminId} AND chave_idempotencia = 'COBRANCA:{$c['cobranca_id']}:DIVERGENTE'"));
    }

    public function testCobrancaComVariasParcelasBaixaTodasEDistribuiATaxaProporcionalmente(): void
    {
        $os = $this->osCobrada(600, 3); // 3 × 200
        $ids = $this->parcelaIds($os);
        $this->pdo->exec("UPDATE ordem_servico_parcelas SET valor = 100 WHERE id_parcela = {$ids[0]}");
        $this->pdo->exec("UPDATE ordem_servico_parcelas SET valor = 300 WHERE id_parcela = {$ids[1]}");
        $c = $this->criar($os, [$ids[0], $ids[1]]);
        $this->assertSame(400.0, (float) $this->cob->getById($c['cobranca_id'])['valor']);

        $this->dispara('PAGO', $c['cobranca_id'], ['taxa' => 10.0, 'forma' => 'CARTAO_CREDITO', 'parcelas' => 3]);

        $this->assertSame(2.5, (float) $this->p($ids[0])['taxa_valor']);
        $this->assertSame(7.5, (float) $this->p($ids[1])['taxa_valor']);
        $this->assertSame(0, (int) $this->p($ids[2])['pago'], 'a que não foi cobrada continua devida');
        $this->assertSame(3, (int) $this->cob->getById($c['cobranca_id'])['parcelas_cartao']);
    }

    public function testRecusadoCanceladoEExpiradoLiberamAsParcelasParaNovaTentativa(): void
    {
        $os = $this->osCobrada();
        [$p1, $p2, $p3] = $this->parcelaIds($os);

        foreach ([[$p1, 'RECUSADO', 'RECUSADA', 'cobranca_recusada'], [$p2, 'CANCELADO', 'CANCELADA', 'cobranca_cancelada'], [$p3, 'EXPIRADO', 'EXPIRADA', 'cobranca_expirada']] as [$parcela, $tipo, $status, $esperado]) {
            $c = $this->criar($os, [$parcela]);
            $this->assertSame($esperado, $this->dispara($tipo, $c['cobranca_id']));
            $this->assertSame($status, $this->cob->getById($c['cobranca_id'])['status']);
            $this->assertSame(0, (int) $this->p($parcela)['pago'], 'cobrança que não foi paga não baixa parcela');
            $nova = $this->criar($os, [$parcela]);
            $this->assertTrue($nova['ok'], 'libera para tentar de novo');
            $this->assertNotSame($c['cobranca_id'], $nova['cobranca_id']);
        }
    }

    public function testPagamentoQueChegaDepoisDeRecusadoEAceitoPoisODinheiroEntrou(): void
    {
        $os = $this->osCobrada();
        [$p1] = $this->parcelaIds($os);
        $c = $this->criar($os, [$p1]);
        $this->dispara('RECUSADO', $c['cobranca_id']);

        $this->assertSame('paga', $this->dispara('PAGO', $c['cobranca_id']));
        $this->assertSame(1, (int) $this->p($p1)['pago']);
    }

    public function testEventoPendenteSoAnotaODetalhe(): void
    {
        $os = $this->osCobrada();
        $c = $this->criar($os, [$this->parcelaIds($os)[0]]);

        $this->assertSame('pendente', $this->dispara('PENDENTE', $c['cobranca_id'], ['detalhe' => 'aguardando o Pix']));
        $this->assertSame('aguardando o Pix', $this->cob->getById($c['cobranca_id'])['detalhe_status']);
        $this->assertSame('PENDENTE', $this->cob->getById($c['cobranca_id'])['status']);
    }

    public function testEstornoDevolveAParcelaAoAReceberAvisaOAdminEOCliente(): void
    {
        $os = $this->osCobrada();
        [$p1] = $this->parcelaIds($os);
        $c = $this->criar($os, [$p1]);
        $this->dispara('PAGO', $c['cobranca_id'], ['taxa' => 5.0]);

        $res = $this->dispara('ESTORNADO', $c['cobranca_id'], ['detalhe' => 'chargeback']);

        $this->assertSame('estornada', $res);
        $p = $this->p($p1);
        $this->assertSame(0, (int) $p['pago']);
        $this->assertSame(0.0, (float) $p['taxa_valor']);
        $this->assertSame('BALCAO', $p['origem_recebimento']);
        $this->assertNull($p['cobranca_online_id']);
        $this->assertSame('ESTORNADA', $this->cob->getById($c['cobranca_id'])['status']);
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = {$this->adminId} AND chave_idempotencia = 'COBRANCA:{$c['cobranca_id']}:ESTORNADO'"));
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM mensagens WHERE chave_idempotencia = 'EV:PAGAMENTO_ESTORNADO:COBRANCA:{$c['cobranca_id']}:ESTORNADA'"));
        $this->assertSame(['RECEBIDA', 'PENDENTE'], $this->pdo->query("SELECT estado_novo FROM historico_estados WHERE entidade = 'PARCELA_OS' AND entidade_id = {$p1} ORDER BY id_historico")->fetchAll(PDO::FETCH_COLUMN));

        $this->assertSame('ja_estornada', $this->dispara('ESTORNADO', $c['cobranca_id']), 'repetir não faz nada');
        $this->assertTrue($this->criar($os, [$p1])['ok'], 'a parcela volta a poder ser paga online');
    }

    public function testEstornoDeCobrancaNaoPagaEIgnorado(): void
    {
        $os = $this->osCobrada();
        $c = $this->criar($os, [$this->parcelaIds($os)[0]]);

        $this->assertSame('estorno_sem_pagamento', $this->dispara('ESTORNADO', $c['cobranca_id']));
        $this->assertSame('PENDENTE', $this->cob->getById($c['cobranca_id'])['status']);
    }

    public function testRecebimentoOnlineNaoPodeSerEstornadoNoBalcao(): void
    {
        $os = $this->osCobrada();
        [$p1] = $this->parcelaIds($os);
        $c = $this->criar($os, [$p1]);
        $this->dispara('PAGO', $c['cobranca_id']);

        $r = $this->pag->estornarParcela($p1, 'tentando estornar no balcão', $this->adminId);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('online', $r['erro']);
        $this->assertSame(1, (int) $this->p($p1)['pago']);
    }

    public function testPagamentoOnlineDeParcelaJaPagaNoBalcaoNaoContaDuasVezesEAvisa(): void
    {
        $os = $this->osCobrada(400, 2);
        [$p1, $p2] = $this->parcelaIds($os);
        $c = $this->criar($os, [$p1, $p2]);
        $this->assertNull($this->pag->pagarParcela($p1, 'DINHEIRO', $this->adminId), 'a parcela 1 foi paga no balcão enquanto o cliente pagava online');

        $res = $this->dispara('PAGO', $c['cobranca_id']);

        $this->assertSame('paga_com_duplicidade', $res);
        $this->assertSame('BALCAO', $this->p($p1)['origem_recebimento'], 'a 1ª continua sendo do balcão');
        $this->assertSame('ONLINE', $this->p($p2)['origem_recebimento']);
        $this->assertStringContainsString('devolver', $this->cob->getById($c['cobranca_id'])['detalhe_status']);
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = {$this->adminId} AND chave_idempotencia = 'COBRANCA:{$c['cobranca_id']}:DUPLICADO'"));
    }

    public function testEventoDeCobrancaDesconhecidaNaoQuebraEFicaRegistrado(): void
    {
        $r = $this->cob->processarEvento('SIMULADO', ['evento_id' => 'phpunit-desconhecida', 'referencia' => 'MECANIZE-999999', 'provedor_cobranca_id' => 'X', 'tipo' => 'PAGO', 'valor' => 10.0]);

        $this->assertSame('cobranca_desconhecida', $r);
        $this->assertSame('cobranca_desconhecida', $this->pdo->query("SELECT resultado FROM webhook_eventos WHERE evento_id = 'phpunit-desconhecida'")->fetchColumn());
    }

    public function testNaoExisteCampoDeCartaoEmNenhumaTabelaDeCobranca(): void
    {
        foreach (['cobrancas_online', 'cobranca_parcelas'] as $tabela) {
            $colunas = $this->pdo->query("SHOW COLUMNS FROM {$tabela}")->fetchAll(PDO::FETCH_COLUMN);
            $this->assertSame([], array_values(array_filter($colunas, fn($c) => preg_match('/numero_cartao|cvv|cvc|validade|titular|pan\b/i', $c))), "{$tabela} não guarda dado de cartão");
        }
    }

    /* ── provedor simulado ── */

    public function testSimuladoSoValeComSegredoEPermissao(): void
    {
        $this->assertTrue($this->sim->configurado());
        $this->assertFalse((new ProvedorSimulado($this->cfgPagamentos(['permitir_simulado' => false])))->configurado(), 'produção sem a permissão explícita');
        $this->assertFalse((new ProvedorSimulado($this->cfgPagamentos(['simulado_segredo' => ''])))->configurado());
        $this->assertNull(Provedores::atual($this->cfgPagamentos(['permitir_simulado' => false])));
        $this->assertInstanceOf(ProvedorSimulado::class, Provedores::atual($this->cfgPagamentos()));
        $this->assertNull(Provedores::atual($this->cfgPagamentos(['provedor' => ''])));
    }

    public function testWebhookSimuladoExigeAssinaturaValida(): void
    {
        $corpo = json_encode(['evento_id' => 'phpunit-a', 'referencia' => 'R', 'tipo' => 'PAGO', 'valor' => 10]);

        $this->assertTrue($this->sim->validarWebhook($corpo, ['x-simulado-assinatura' => $this->sim->assinarCorpo($corpo)], []));
        $this->assertFalse($this->sim->validarWebhook($corpo . ' ', ['x-simulado-assinatura' => $this->sim->assinarCorpo($corpo)], []));
        $this->assertFalse($this->sim->validarWebhook($corpo, [], []));
        $this->assertFalse($this->sim->validarWebhook($corpo, ['x-simulado-assinatura' => 'sha256=00'], []));
        $this->assertFalse((new ProvedorSimulado($this->cfgPagamentos(['simulado_segredo' => 'outro-segredo-aqui'])))->validarWebhook($corpo, ['x-simulado-assinatura' => $this->sim->assinarCorpo($corpo)], []));

        $ev = $this->sim->interpretarWebhook($corpo, [], []);
        $this->assertSame('PAGO', $ev[0]['tipo']);
        $this->assertSame([], $this->sim->interpretarWebhook('lixo', [], []));
        $this->assertTrue($this->sim->referenciaAssinadaValida('MECANIZE-000001', $this->sim->assinarReferencia('MECANIZE-000001')));
        $this->assertFalse($this->sim->referenciaAssinadaValida('MECANIZE-000002', $this->sim->assinarReferencia('MECANIZE-000001')));
    }

    /* ── Mercado Pago (transporte falso) ── */

    private function cfgMp(array $extra = []): array
    {
        return $extra + ['provedor' => 'mercadopago', 'mp_access_token' => 'APP_USR-token-de-teste', 'mp_webhook_secret' => 'segredo-webhook-mp', 'max_parcelas' => 3, 'simulado_segredo' => '', 'permitir_simulado' => false];
    }

    public function testMercadoPagoCriaPreferenciaComReferenciaNotificacaoEParcelas(): void
    {
        $http = (new FakeTransporte())->enfileirar(201, ['id' => '987-abc', 'init_point' => 'https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=987-abc']);
        $mp = new MercadoPago($this->cfgMp(), $http);

        $r = $mp->criarCobranca(['referencia' => 'MECANIZE-000007', 'valor' => 250.5, 'descricao' => 'Ordem de serviço OS-7', 'max_parcelas' => 3,
            'url_retorno' => 'https://oficina.example/portal?t=x', 'url_notificacao' => 'https://oficina.example/webhookPagamento']);

        $this->assertTrue($r['ok']);
        $this->assertSame('987-abc', $r['id']);
        $this->assertStringContainsString('mercadopago', $r['url']);
        $c = $http->chamadas[0];
        $this->assertSame('https://api.mercadopago.com/checkout/preferences', $c['url']);
        $this->assertContains('Authorization: Bearer APP_USR-token-de-teste', $c['cabecalhos']);
        $corpo = $http->ultimoCorpo();
        $this->assertSame('MECANIZE-000007', $corpo['external_reference']);
        $this->assertSame('https://oficina.example/webhookPagamento', $corpo['notification_url']);
        $this->assertSame(250.5, $corpo['items'][0]['unit_price']);
        $this->assertSame(3, $corpo['payment_methods']['installments']);
        $this->assertSame('BRL', $corpo['items'][0]['currency_id']);
    }

    public function testMercadoPagoSemCredenciaisNaoChamaARede(): void
    {
        $http = new FakeTransporte();
        $mp = new MercadoPago($this->cfgMp(['mp_access_token' => '']), $http);

        $this->assertFalse($mp->configurado());
        $this->assertFalse($mp->criarCobranca(['referencia' => 'R', 'valor' => 1.0, 'descricao' => 'x', 'max_parcelas' => 1, 'url_retorno' => 'a', 'url_notificacao' => 'b'])['ok']);
        $this->assertSame([], $http->chamadas);
        $this->assertNull(Provedores::porNome('MERCADOPAGO', $this->cfgMp(['mp_access_token' => ''])));
    }

    public function testMercadoPagoErroNaCriacaoVirraMensagemSemVazarOCorpo(): void
    {
        $http = (new FakeTransporte())->enfileirar(400, ['message' => 'invalid unit_price', 'secret' => 'APP_USR-token-de-teste']);
        $r = (new MercadoPago($this->cfgMp(), $http))->criarCobranca(['referencia' => 'R', 'valor' => 1.0, 'descricao' => 'x', 'max_parcelas' => 1, 'url_retorno' => 'a', 'url_notificacao' => 'b']);

        $this->assertFalse($r['ok']);
        $this->assertStringNotContainsString('APP_USR', (string) $r['erro']);
    }

    private function assinaturaMp(string $id, string $requestId, ?int $tsMs = null, string $segredo = 'segredo-webhook-mp'): string
    {
        $ts = (string) ($tsMs ?? (time() * 1000));
        $manifest = 'id:' . strtolower($id) . ';request-id:' . $requestId . ';ts:' . $ts . ';';

        return 'ts=' . $ts . ',v1=' . hash_hmac('sha256', $manifest, $segredo);
    }

    public function testMercadoPagoValidaAssinaturaDoWebhook(): void
    {
        $mp = new MercadoPago($this->cfgMp(), new FakeTransporte());
        $corpo = json_encode(['type' => 'payment', 'data' => ['id' => '1234567']]);
        $q = ['data_id' => '1234567', 'type' => 'payment'];

        $this->assertTrue($mp->validarWebhook($corpo, ['x-signature' => $this->assinaturaMp('1234567', 'req-1'), 'x-request-id' => 'req-1'], $q));
        $this->assertFalse($mp->validarWebhook($corpo, ['x-signature' => $this->assinaturaMp('1234567', 'req-1', null, 'outro-segredo'), 'x-request-id' => 'req-1'], $q), 'segredo errado');
        $this->assertFalse($mp->validarWebhook($corpo, ['x-signature' => $this->assinaturaMp('1234567', 'req-1'), 'x-request-id' => 'req-2'], $q), 'request-id diferente');
        $this->assertFalse($mp->validarWebhook($corpo, ['x-signature' => $this->assinaturaMp('7654321', 'req-1'), 'x-request-id' => 'req-1'], $q), 'id diferente');
        $this->assertFalse($mp->validarWebhook($corpo, ['x-signature' => $this->assinaturaMp('1234567', 'req-1', (time() - 7200) * 1000), 'x-request-id' => 'req-1'], $q), 'carimbo antigo (replay)');
        $this->assertFalse($mp->validarWebhook($corpo, [], $q), 'sem assinatura');
        $this->assertFalse($mp->validarWebhook($corpo, ['x-signature' => 'lixo', 'x-request-id' => 'req-1'], $q));
        $this->assertFalse((new MercadoPago($this->cfgMp(['mp_webhook_secret' => '']), new FakeTransporte()))->validarWebhook($corpo, ['x-signature' => $this->assinaturaMp('1234567', 'req-1'), 'x-request-id' => 'req-1'], $q));
        $this->assertTrue($mp->validarWebhook($corpo, ['x-signature' => $this->assinaturaMp('1234567', 'req-1'), 'x-request-id' => 'req-1'], []), 'data.id também vem do corpo');
    }

    public function testMercadoPagoInterpretaOEstadoConsultadoNaApi(): void
    {
        $casos = [
            ['approved', 'accredited', 'PAGO'], ['pending', 'pending_waiting_transfer', 'PENDENTE'], ['in_process', 'x', 'PENDENTE'], ['rejected', 'cc_rejected_other_reason', 'RECUSADO'],
            ['cancelled', 'expired', 'EXPIRADO'], ['cancelled', 'by_collector', 'CANCELADO'], ['refunded', 'refunded', 'ESTORNADO'], ['charged_back', 'x', 'ESTORNADO'],
        ];

        foreach ($casos as [$status, $detalhe, $esperado]) {
            $http = (new FakeTransporte())->enfileirar(200, [
                'id' => 1234567, 'status' => $status, 'status_detail' => $detalhe, 'external_reference' => 'MECANIZE-000009', 'transaction_amount' => 200.0,
                'installments' => 3, 'payment_type_id' => 'credit_card', 'date_last_updated' => '2026-10-09T10:00:00.000-03:00',
                'fee_details' => [['type' => 'mercadopago_fee', 'amount' => 7.99], ['type' => 'financing_fee', 'amount' => 2.01]],
            ]);
            $ev = (new MercadoPago($this->cfgMp(), $http))->interpretarWebhook(json_encode(['type' => 'payment', 'data' => ['id' => '1234567']]), [], ['type' => 'payment', 'data_id' => '1234567']);

            $this->assertSame($esperado, $ev[0]['tipo'], "{$status}/{$detalhe}");
            $this->assertSame('MECANIZE-000009', $ev[0]['referencia']);
            $this->assertSame(200.0, $ev[0]['valor']);
            $this->assertSame(10.0, $ev[0]['taxa'], 'as taxas somadas');
            $this->assertSame('CARTAO_CREDITO', $ev[0]['forma']);
            $this->assertSame(3, $ev[0]['parcelas']);
            $this->assertSame('https://api.mercadopago.com/v1/payments/1234567', $http->chamadas[0]['url']);
            $this->assertSame('GET', $http->chamadas[0]['metodo']);
        }
    }

    public function testMercadoPagoIgnoraOutrosTopicosETrataFalhaNaConsulta(): void
    {
        $http = new FakeTransporte();
        $mp = new MercadoPago($this->cfgMp(), $http);

        $this->assertSame([], $mp->interpretarWebhook('{}', [], ['type' => 'merchant_order', 'data_id' => '1']));
        $this->assertSame([], $mp->interpretarWebhook('{}', [], ['type' => 'payment', 'data_id' => 'nao-numerico']), 'id estranho não vira chamada à API');
        $this->assertSame([], $http->chamadas);

        $this->expectException(RuntimeException::class);
        $mp->interpretarWebhook('{}', [], ['type' => 'payment', 'data_id' => '99']); // sem resposta combinada → falha de rede
    }

    public function testFluxoCompletoComMercadoPagoEntraPeloMesmoCaminhoDoSimulado(): void
    {
        $os = $this->osCobrada();
        [$p1] = $this->parcelaIds($os);
        $http = (new FakeTransporte())
            ->enfileirar(201, ['id' => 'pref-1', 'init_point' => 'https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=pref-1']);
        $cob = new CobrancaOnline($this->pdo, new MercadoPago($this->cfgMp(), $http), $this->cfgMp());

        $c = $cob->criar($os, [$p1], null, 'https://x/portal', 'https://x/webhookPagamento');
        $this->assertTrue($c['ok'], (string) $c['erro']);
        $linha = $cob->getById($c['cobranca_id']);
        $this->assertSame('MERCADOPAGO', $linha['provedor']);
        $this->assertSame('pref-1', $linha['provedor_cobranca_id']);

        $http->enfileirar(200, ['id' => 555, 'status' => 'approved', 'status_detail' => 'accredited', 'external_reference' => $linha['referencia'], 'transaction_amount' => 200.0,
            'installments' => 1, 'payment_type_id' => 'bank_transfer', 'date_last_updated' => '2026-10-09T10:00:00.000-03:00', 'fee_details' => [['type' => 'mercadopago_fee', 'amount' => 1.0]]]);
        $eventos = $cob->provedor()->interpretarWebhook('{}', [], ['type' => 'payment', 'data_id' => '555']);

        $this->assertSame(['paga'], $cob->processarEventos('MERCADOPAGO', $eventos));
        $this->assertSame('PIX', $this->p($p1)['forma_pagamento']);
        $this->assertSame(1.0, (float) $this->p($p1)['taxa_valor']);
        $this->assertSame(['duplicado'], $cob->processarEventos('MERCADOPAGO', $eventos), 'o provedor reenviou a notificação');
    }
}
