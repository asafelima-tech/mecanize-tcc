<?php

require_once __DIR__ . '/OsTestCase.php';
require_once __DIR__ . '/../app/models/Ocorrencia.php';
require_once __DIR__ . '/../app/models/Avaliacao.php';
require_once __DIR__ . '/../app/models/AutorizacaoCliente.php';
require_once __DIR__ . '/../app/models/Orcamento.php';
require_once __DIR__ . '/../app/models/PortalToken.php';
require_once __DIR__ . '/../app/models/PortalCaso.php';
require_once __DIR__ . '/../app/models/Consentimento.php';

/**
 * Portal do cliente: o que o link alcança, o que mostra e o que o cliente consegue decidir.
 *
 * O ponto central é a FRONTEIRA: um link só enxerga e decide sobre o caso do próprio cliente, uma
 * decisão vale uma vez, e nada interno (custo, telefone, motivo do histórico, nome de técnico) vaza.
 */
final class PortalCasoTest extends OsTestCase
{
    private Ocorrencia $oc;
    private Avaliacao $av;
    private Orcamento $orc;
    private AutorizacaoCliente $aut;
    private PortalCaso $portal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oc = new Ocorrencia();
        $this->av = new Avaliacao();
        $this->orc = new Orcamento();
        $this->aut = new AutorizacaoCliente();
        $this->portal = new PortalCaso($this->pdo);
    }

    private function chegada(?int $clienteId = null, ?int $veiculoId = null): int
    {
        $r = $this->oc->registrar([
            'cliente' => ['id' => $clienteId ?? $this->clienteId],
            'veiculo' => ['id' => $veiculoId ?? $this->veiculoId],
            'relato'  => 'Barulho na suspensão dianteira ao passar em lombadas',
        ], $this->adminId, 'ADMIN');
        $this->assertTrue($r['ok'], (string) $r['erro']);

        return (int) $r['id'];
    }

    private function avaliar(int $ocId): int
    {
        $i = $this->av->iniciar($ocId, $this->tec1);
        $this->av->salvar($i['id'], [
            'defeitos' => 'Amortecedor dianteiro vazando e bucha da bandeja gasta',
            'itens'    => [
                ['tipo' => 'produto', 'produto_id' => $this->produtoId, 'quantidade' => 1, 'preco' => 100],
                ['tipo' => 'servico', 'nome' => 'Troca de amortecedor', 'categoria' => 'Suspensão', 'quantidade' => 1, 'preco' => 200],
            ],
        ]);
        $this->assertTrue($this->av->concluir($i['id'], $this->tec1)['ok']);

        return (int) $i['id'];
    }

    /** Ocorrência com orçamento criado (ainda NÃO enviado). */
    private function casoComOrcamento(): array
    {
        $oc = $this->chegada();
        $orcId = (int) $this->av->gerarOrcamento($this->avaliar($oc), $this->adminId)['id'];

        return [$oc, $orcId];
    }

    private function token(string $escopo, int $entidade, ?int $cliente = null): array
    {
        $bruto = PortalToken::emitir($this->pdo, $cliente ?? $this->clienteId, $escopo, $entidade, $this->adminId);
        $t = PortalToken::resolver($this->pdo, $bruto, false);
        $this->assertNotNull($t);
        $t['_bruto'] = $bruto;

        return $t;
    }

    private function contarDecisoes(): int
    {
        return $this->contar("SELECT COUNT(*) FROM decisoes_portal WHERE cliente_id = {$this->clienteId}");
    }

    /* ── alcance ── */

    public function testOTokenDaOcorrenciaAlcancaOCasoTodo(): void
    {
        [$oc, $orcId] = $this->casoComOrcamento();
        $this->orc->enviar($orcId);
        $osId = (int) $this->orc->aprovar($orcId, null, ['usuario_id' => $this->adminId])['os_id'];
        $this->fx->registrar('ordens_servico', $osId);

        $alc = $this->portal->alcance($this->token('OCORRENCIA', $oc));

        $this->assertSame($oc, $alc['ocorrencia_id']);
        $this->assertSame([$orcId], $alc['orcamento_ids']);
        $this->assertSame([$osId], $alc['os_ids']);

        // Do orçamento e da OS também se chega ao mesmo caso.
        $this->assertSame([$osId], $this->portal->alcance($this->token('ORCAMENTO', $orcId))['os_ids']);
        $this->assertSame($oc, $this->portal->alcance($this->token('OS', $osId))['ocorrencia_id']);
    }

    public function testTokenDeUmClienteNaoEnxergaEntidadeDeOutro(): void
    {
        [$oc, $orcId] = $this->casoComOrcamento();
        $outro = $this->fx->cliente();

        $this->assertNull($this->portal->alcance($this->token('OCORRENCIA', $oc, $outro)), 'ocorrência alheia');
        $this->assertNull($this->portal->alcance($this->token('ORCAMENTO', $orcId, $outro)), 'orçamento alheio');
        $this->assertNull($this->portal->visao($this->token('ORCAMENTO', $orcId, $outro)));
    }

    public function testTokenDeUmaOcorrenciaNaoAlcancaAOutraDoMesmoCliente(): void
    {
        [$ocA, $orcA] = $this->casoComOrcamento();
        $this->orc->enviar($orcA);
        [$ocB, $orcB] = $this->casoComOrcamento();
        $this->orc->enviar($orcB);

        $alc = $this->portal->alcance($this->token('OCORRENCIA', $ocA));
        $this->assertSame([$orcA], $alc['orcamento_ids']);

        $r = $this->portal->decidirOrcamento($this->token('OCORRENCIA', $ocA), $orcB, true, null, '10.0.0.1', 'UA');
        $this->assertFalse($r['ok'], 'o link do atendimento A não decide o orçamento do atendimento B');
        $this->assertSame('pendente', $this->pdo->query("SELECT status FROM orcamentos WHERE id_orcamento = {$orcB}")->fetchColumn());
        $this->assertSame(0, $this->contarDecisoes());
    }

    /* ── visão ── */

    public function testVisaoNaoVazaDadoInterno(): void
    {
        $this->pdo->prepare('UPDATE clientes SET email = ?, telefone = ? WHERE id_cliente = ?')->execute(['segredo@exemplo.test', '(11) 98888-7777', $this->clienteId]);
        [$oc, $orcId] = $this->casoComOrcamento();
        $this->orc->enviar($orcId);
        $this->orc->recusar($orcId, 'MOTIVO-INTERNO-NAO-PODE-VAZAR');

        $json = json_encode($this->portal->visao($this->token('OCORRENCIA', $oc)), JSON_UNESCAPED_UNICODE);

        foreach (['segredo@exemplo.test', '98888-7777', 'MOTIVO-INTERNO-NAO-PODE-VAZAR', 'comissao', 'preco_custo', 'custo'] as $proibido) {
            $this->assertStringNotContainsString($proibido, (string) $json, "'{$proibido}' não pode sair no portal");
        }

        $nomeTecnico = (string) $this->pdo->query("SELECT nome FROM usuarios WHERE id_usuario = {$this->tec1}")->fetchColumn();
        $this->assertStringNotContainsString($nomeTecnico, (string) $json, 'o nome do técnico não vai para o cliente');
    }

    public function testLinhaDoTempoUsaLinguagemDoClienteESoOQueFoiRegistrado(): void
    {
        [$oc, $orcId] = $this->casoComOrcamento();
        $v = $this->portal->visao($this->token('OCORRENCIA', $oc));
        $titulos = array_column($v['linha_do_tempo'], 'titulo');

        $this->assertContains('Veículo recebido na oficina', $titulos);
        $this->assertContains('Avaliação técnica iniciada', $titulos);
        $this->assertNotContains('Serviço em execução', $titulos, 'nada de etapa que ainda não aconteceu');
        $this->assertSame('Preparando o orçamento', $v['situacao']['rotulo']);

        $this->orc->enviar($orcId);
        $a = $this->orc->aprovar($orcId, null, ['usuario_id' => $this->adminId]);
        $this->fx->registrar('ordens_servico', (int) $a['os_id']);
        $this->iniciar((int) $a['os_id']);

        $v = $this->portal->visao($this->token('OCORRENCIA', $oc));
        $titulos = array_column($v['linha_do_tempo'], 'titulo');
        $this->assertContains('Orçamento aprovado', $titulos);
        $this->assertContains('Serviço em execução', $titulos);
        $this->assertSame('Em execução', $v['situacao']['rotulo']);
        $this->assertSame('Em execução', $this->portal->visao($this->token('OS', (int) $a['os_id']))['situacao']['rotulo']);
    }

    public function testOrcamentoSoPodeSerDecididoDepoisDeEnviadoEEnquantoValer(): void
    {
        [$oc, $orcId] = $this->casoComOrcamento();
        $tok = $this->token('OCORRENCIA', $oc);

        $this->assertFalse($this->portal->visao($tok)['orcamentos'][0]['pode_decidir'], 'rascunho não é decidível');
        $r = $this->portal->decidirOrcamento($tok, $orcId, true, null, '10.0.0.1', 'UA');
        $this->assertFalse($r['ok']);
        $this->assertSame('pendente', $this->pdo->query("SELECT status FROM orcamentos WHERE id_orcamento = {$orcId}")->fetchColumn());

        $this->orc->enviar($orcId);
        $this->assertTrue($this->portal->visao($tok)['orcamentos'][0]['pode_decidir']);

        $this->pdo->exec("UPDATE orcamentos SET data_validade = '2020-01-01' WHERE id_orcamento = {$orcId}");
        $this->assertFalse($this->portal->visao($tok)['orcamentos'][0]['pode_decidir'], 'vencido não é decidível');
        $this->assertFalse($this->portal->decidirOrcamento($tok, $orcId, true, null, '10.0.0.1', 'UA')['ok']);
    }

    /* ── decisão do orçamento ── */

    public function testAprovarPeloPortalCriaAOsRegistraEvidenciaEAvisaAEquipe(): void
    {
        [$oc, $orcId] = $this->casoComOrcamento();
        $this->orc->enviar($orcId);
        $tok = $this->token('ORCAMENTO', $orcId);

        $r = $this->portal->decidirOrcamento($tok, $orcId, true, null, '203.0.113.9', 'Mozilla/5.0 (teste)');
        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->assertFalse($r['repetido']);

        $osId = (int) $this->pdo->query("SELECT id_ordem_servico FROM ordens_servico WHERE orcamento_id = {$orcId}")->fetchColumn();
        $this->assertGreaterThan(0, $osId, 'a aprovação abre a OS');
        $this->fx->registrar('ordens_servico', $osId);

        $aut = $this->pdo->query("SELECT canal, evidencia, status FROM autorizacoes_cliente WHERE orcamento_id = {$orcId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('PORTAL', $aut['canal']);
        $this->assertSame('APROVADA', $aut['status']);
        $this->assertStringContainsString('203.0.113.9', $aut['evidencia']);

        $d = $this->pdo->query("SELECT * FROM decisoes_portal WHERE tipo = 'ORCAMENTO' AND entidade_id = {$orcId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(['APROVADO', '203.0.113.9', (int) $tok['id_token']], [$d['decisao'], $d['ip'], (int) $d['token_id']]);
        $this->assertStringContainsString('Mozilla', $d['user_agent']);

        $h = $this->pdo->query("SELECT origem FROM historico_estados WHERE entidade = 'ORCAMENTO' AND entidade_id = {$orcId} AND estado_novo = 'aprovado'")->fetchColumn();
        $this->assertSame('PORTAL', $h, 'a trilha de auditoria diz que veio do portal');

        $avisos = $this->contar("SELECT COUNT(*) FROM notificacoes WHERE tipo = 'PORTAL' AND usuario_id = {$this->adminId} AND titulo LIKE '%aprovado pelo cliente%'");
        $this->assertSame(1, $avisos, 'a equipe é avisada');
    }

    public function testAprovarDeNovoNaoDuplicaOsNemDecisaoNemAviso(): void
    {
        [$oc, $orcId] = $this->casoComOrcamento();
        $this->orc->enviar($orcId);
        $tok = $this->token('ORCAMENTO', $orcId);

        $this->assertTrue($this->portal->decidirOrcamento($tok, $orcId, true, null, '203.0.113.9', 'UA')['ok']);
        $segunda = $this->portal->decidirOrcamento($tok, $orcId, true, null, '203.0.113.9', 'UA');

        $this->assertTrue($segunda['ok']);
        $this->assertTrue($segunda['repetido']);
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM ordens_servico WHERE orcamento_id = {$orcId}"));
        $this->assertSame(1, $this->contarDecisoes());
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM notificacoes WHERE tipo = 'PORTAL' AND usuario_id = {$this->adminId}"));
        $this->fx->registrar('ordens_servico', (int) $this->pdo->query("SELECT id_ordem_servico FROM ordens_servico WHERE orcamento_id = {$orcId}")->fetchColumn());
    }

    public function testRecusarPeloPortalNaoCriaOsEPoeAOcorrenciaEmEspera(): void
    {
        [$oc, $orcId] = $this->casoComOrcamento();
        $this->orc->enviar($orcId);
        $tok = $this->token('OCORRENCIA', $oc);

        $r = $this->portal->decidirOrcamento($tok, $orcId, false, 'Achei caro, vou pesquisar', '203.0.113.9', 'UA');
        $this->assertTrue($r['ok'], (string) $r['erro']);

        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM ordens_servico WHERE orcamento_id = {$orcId}"));
        $this->assertSame('EM_ESPERA', $this->pdo->query("SELECT status FROM ocorrencias WHERE id_ocorrencia = {$oc}")->fetchColumn());
        $d = $this->pdo->query("SELECT decisao, motivo FROM decisoes_portal WHERE entidade_id = {$orcId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(['RECUSADO', 'Achei caro, vou pesquisar'], [$d['decisao'], $d['motivo']]);

        // Já recusado: aprovar pelo mesmo link não vale (a oficina precisa reabrir).
        $volta = $this->portal->decidirOrcamento($tok, $orcId, true, null, '203.0.113.9', 'UA');
        $this->assertFalse($volta['ok']);
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM ordens_servico WHERE orcamento_id = {$orcId}"));
    }

    public function testFalhaNaAprovacaoNaoDeixaDecisaoRegistrada(): void
    {
        [$oc, $orcId] = $this->casoComOrcamento();
        $this->orc->enviar($orcId);
        // Itens que não somam o total: a aprovação falha por regra e NADA pode ficar gravado.
        $this->pdo->exec("UPDATE orcamentos SET valor_total_orcamento = valor_total_orcamento + 50 WHERE id_orcamento = {$orcId}");

        $r = $this->portal->decidirOrcamento($this->token('ORCAMENTO', $orcId), $orcId, true, null, '203.0.113.9', 'UA');

        $this->assertFalse($r['ok']);
        $this->assertSame(0, $this->contarDecisoes(), 'sem decisão fantasma');
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM ordens_servico WHERE orcamento_id = {$orcId}"));
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM notificacoes WHERE tipo = 'PORTAL' AND usuario_id = {$this->adminId}"));
    }

    /* ── autorização adicional ── */

    private function osComPedidoDeAutorizacao(): array
    {
        $oc = $this->chegada();
        $this->avaliar($oc);
        $this->aut->registrarValorAberto($oc, [
            'escopo' => 'Troca do par de amortecedores dianteiros e buchas da bandeja', 'canal' => 'PRESENCIAL',
            'evidencia' => 'Cliente confirmou ao balcão às 15h20', 'limite_valor' => '300',
        ], $this->adminId);
        $r = $this->oc->abrirOsDireta($oc, $this->adminId);
        $osId = (int) $r['os_id'];
        $this->fx->registrar('ordens_servico', $osId);
        $this->iniciar($osId);
        $p = $this->aut->solicitarComplemento($osId, 150, 'Encontrado desalinhamento grave que não estava no escopo', $this->tec1);
        $this->assertTrue($p['ok'], (string) $p['erro']);

        return [$oc, $osId, (int) $p['id']];
    }

    public function testAutorizarPeloPortalAumentaOTetoUmaVezSo(): void
    {
        [$oc, $osId, $autId] = $this->osComPedidoDeAutorizacao();
        $tok = $this->token('OS', $osId);

        $v = $this->portal->visao($tok);
        $this->assertCount(1, $v['ordens'][0]['autorizacoes_pendentes']);

        $r = $this->portal->decidirAutorizacao($tok, $autId, true, '203.0.113.9', 'UA');
        $this->assertTrue($r['ok'], (string) $r['erro']);
        $again = $this->portal->decidirAutorizacao($tok, $autId, true, '203.0.113.9', 'UA');
        $this->assertTrue($again['repetido']);

        $this->assertSame(450.0, (float) $this->pdo->query("SELECT valor_autorizado FROM ordens_servico WHERE id_ordem_servico = {$osId}")->fetchColumn(), '300 + 150, uma vez só');
        $a = $this->pdo->query("SELECT status, canal FROM autorizacoes_cliente WHERE id_autorizacao = {$autId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(['APROVADA', 'PORTAL'], [$a['status'], $a['canal']]);
        $this->assertSame(1, $this->contarDecisoes());
        $this->assertCount(0, $this->portal->visao($tok)['ordens'][0]['autorizacoes_pendentes'], 'respondido, deixa de ser pergunta');
    }

    public function testRecusarAutorizacaoNaoMudaOTetoEAvisaATecnicoEAdmin(): void
    {
        [$oc, $osId, $autId] = $this->osComPedidoDeAutorizacao();
        $this->pdo->prepare('INSERT INTO ordem_servico_mao_obra (ordem_servico_id, tecnico_id, descricao, valor_mao_obra) VALUES (?, ?, ?, 0)')->execute([$osId, $this->tec1, 'x']);

        $r = $this->portal->decidirAutorizacao($this->token('OCORRENCIA', $oc), $autId, false, '203.0.113.9', 'UA');
        $this->assertTrue($r['ok'], (string) $r['erro']);

        $this->assertSame(300.0, (float) $this->pdo->query("SELECT valor_autorizado FROM ordens_servico WHERE id_ordem_servico = {$osId}")->fetchColumn());
        $this->assertSame('RECUSADA', $this->pdo->query("SELECT status FROM autorizacoes_cliente WHERE id_autorizacao = {$autId}")->fetchColumn());
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM notificacoes WHERE tipo = 'PORTAL' AND usuario_id = {$this->tec1}"), 'quem executa o serviço fica sabendo');
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM notificacoes WHERE tipo = 'PORTAL' AND usuario_id = {$this->adminId}"));
        $h = $this->pdo->query("SELECT origem FROM historico_estados WHERE entidade = 'OS' AND entidade_id = {$osId} ORDER BY id_historico DESC LIMIT 1")->fetchColumn();
        $this->assertSame('PORTAL', $h);
    }

    public function testAutorizacaoDeOutraOsNaoEDecididaPorEsteLink(): void
    {
        [$ocA, $osA, $autA] = $this->osComPedidoDeAutorizacao();
        [$ocB, $osB, $autB] = $this->osComPedidoDeAutorizacao();

        $r = $this->portal->decidirAutorizacao($this->token('OS', $osA), $autB, true, '203.0.113.9', 'UA');

        $this->assertFalse($r['ok']);
        $this->assertSame('SOLICITADA', $this->pdo->query("SELECT status FROM autorizacoes_cliente WHERE id_autorizacao = {$autB}")->fetchColumn());
    }

    /* ── pagamento e consentimento ── */

    public function testSemProvedorConfiguradoOPortalNaoOfereceNemIniciaPagamento(): void
    {
        $osId = $this->novaOs();
        $this->ateRetirada($osId);
        (new OrdemServicoPagamento())->salvarPlano($osId, ['pagamento_tipo' => 'AVISTA']);
        $parcela = (int) (new OrdemServicoPagamento())->parcelas($osId)[0]['id_parcela'];
        $tok = $this->token('OS', $osId);

        $v = $this->portal->visao($tok);
        $this->assertFalse($v['pagamento_online'], 'sem provedor configurado, nada de botão de pagar online');
        $this->assertTrue($v['ordens'][0]['cobrada']);
        $this->assertSame(500.0, $v['ordens'][0]['saldo']);
        $this->assertTrue($v['ordens'][0]['pronta_para_retirar']);

        $r = $this->portal->iniciarPagamento($tok, $osId, [$parcela]);
        $this->assertFalse($r['ok']);
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM cobrancas_online WHERE ordem_servico_id = {$osId}"));
    }

    public function testPagarOsDeOutroLinkEBarrado(): void
    {
        $osA = $this->novaOs();
        $this->ateRetirada($osA);
        (new OrdemServicoPagamento())->salvarPlano($osA, ['pagamento_tipo' => 'AVISTA']);
        $outro = $this->fx->cliente();

        $r = $this->portal->iniciarPagamento($this->token('OS', $osA, $outro), $osA, [1]);
        $this->assertFalse($r['ok']);
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM cobrancas_online WHERE ordem_servico_id = {$osA}"));
    }

    public function testConsentimentoPeloPortalEntraNoLivro(): void
    {
        $tok = $this->token('OCORRENCIA', $this->chegada());

        $this->assertNull(Consentimento::vigente($this->pdo, $this->clienteId, 'WHATSAPP'));
        $this->assertTrue($this->portal->registrarConsentimento($tok, 'WHATSAPP', true)['ok']);
        $this->assertTrue(Consentimento::vigente($this->pdo, $this->clienteId, 'WHATSAPP'));
        $this->assertTrue($this->portal->registrarConsentimento($tok, 'WHATSAPP', false)['ok']);
        $this->assertFalse(Consentimento::vigente($this->pdo, $this->clienteId, 'WHATSAPP'));

        $h = Consentimento::historico($this->pdo, $this->clienteId);
        $this->assertCount(2, $h, 'o livro guarda as duas decisões');
        $this->assertSame('PORTAL', $h[0]['origem']);

        $this->assertFalse($this->portal->registrarConsentimento($tok, 'SMS', true)['ok'], 'canal desconhecido');
    }

    public function testTokenRevogadoOuExpiradoNaoResolve(): void
    {
        $oc = $this->chegada();
        $bruto = PortalToken::emitir($this->pdo, $this->clienteId, 'OCORRENCIA', $oc, $this->adminId);
        $this->assertNotNull(PortalToken::resolver($this->pdo, $bruto, false));

        $this->assertSame(1, PortalToken::revogarDaEntidade($this->pdo, 'OCORRENCIA', $oc) >= 1 ? 1 : 0);
        $this->assertNull(PortalToken::resolver($this->pdo, $bruto, false), 'revogado');

        $bruto2 = PortalToken::emitir($this->pdo, $this->clienteId, 'OCORRENCIA', $oc, $this->adminId, 1);
        $this->pdo->exec("UPDATE portal_tokens SET expira_em = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE token_hash = '" . hash('sha256', $bruto2) . "'");
        $this->assertNull(PortalToken::resolver($this->pdo, $bruto2, false), 'expirado');

        $this->assertNull(PortalToken::resolver($this->pdo, 'curto', false), 'formato inválido nem chega ao banco');
        $this->assertNull(PortalToken::resolver($this->pdo, str_repeat('a', 43), false), 'inexistente');
    }
}
