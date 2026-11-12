<?php

require_once __DIR__ . '/OsTestCase.php';
require_once __DIR__ . '/../app/models/Ocorrencia.php';
require_once __DIR__ . '/../app/models/Avaliacao.php';
require_once __DIR__ . '/../app/models/AutorizacaoCliente.php';
require_once __DIR__ . '/../app/models/Orcamento.php';
require_once __DIR__ . '/../app/models/Comunicacao.php';
require_once __DIR__ . '/../app/models/PortalToken.php';

/**
 * Mudança de estado REAL → mensagem ao cliente.
 *
 * O que se prova aqui: o aviso nasce na mesma transação da mudança, uma única vez por fato
 * (repetir a ação não duplica), nunca antes do fato, e o link do portal que vai no texto é de
 * verdade (resolve para a entidade certa). Sem canal configurado a mensagem fica registrada como
 * NAO_CONFIGURADO — nada é enviado nem fingido.
 */
final class HooksComunicacaoTest extends OsTestCase
{
    private Ocorrencia $oc;
    private Avaliacao $av;
    private Orcamento $orc;
    private AutorizacaoCliente $aut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oc  = new Ocorrencia();
        $this->av  = new Avaliacao();
        $this->orc = new Orcamento();
        $this->aut = new AutorizacaoCliente();
    }

    /** @return array<int,array<string,mixed>> mensagens do cliente de teste, da mais antiga à mais nova */
    private function mensagens(?string $evento = null): array
    {
        $sql = 'SELECT m.* FROM mensagens m JOIN conversas c ON c.id_conversa = m.conversa_id WHERE c.cliente_id = ' . $this->clienteId
             . ($evento ? " AND m.evento = '{$evento}'" : '') . ' ORDER BY m.id_mensagem';

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function chegada(): int
    {
        $r = $this->oc->registrar([
            'cliente' => ['id' => $this->clienteId],
            'veiculo' => ['id' => $this->veiculoId],
            'relato'  => 'Barulho na suspensão dianteira ao passar em lombadas',
        ], $this->adminId, 'ADMIN');
        $this->assertTrue($r['ok'], (string) $r['erro']);

        return (int) $r['id'];
    }

    private function avaliar(int $ocId): int
    {
        $i = $this->av->iniciar($ocId, $this->tec1);
        $this->assertTrue($i['ok'], (string) $i['erro']);
        $this->assertTrue($this->av->salvar($i['id'], [
            'defeitos' => 'Amortecedor dianteiro vazando e bucha da bandeja gasta',
            'itens'    => [
                ['tipo' => 'produto', 'produto_id' => $this->produtoId, 'quantidade' => 1, 'preco' => 100],
                ['tipo' => 'servico', 'nome' => 'Troca de amortecedor', 'categoria' => 'Suspensão', 'quantidade' => 1, 'preco' => 200],
            ],
        ])['ok']);
        $this->assertTrue($this->av->concluir($i['id'], $this->tec1)['ok']);

        return (int) $i['id'];
    }

    public function testChegadaAvisaOClienteUmaVezEComLinkDoPortalQueResolve(): void
    {
        $oc = $this->chegada();

        $m = $this->mensagens('VEICULO_RECEBIDO');
        $this->assertCount(1, $m);
        $this->assertSame('AUTOMATICA', $m[0]['tipo']);
        $this->assertSame('OCORRENCIA', $m[0]['contexto_tipo']);
        $this->assertSame($oc, (int) $m[0]['contexto_id']);

        // O texto leva um link do portal e esse link é de verdade: o token resolve para ESTA ocorrência.
        $this->assertSame(1, preg_match('~portal\?t=([A-Za-z0-9_-]+)~', $m[0]['corpo'], $achou), 'o texto traz o link seguro');
        $tok = PortalToken::resolver($this->pdo, $achou[1], false);
        $this->assertNotNull($tok);
        $this->assertSame('OCORRENCIA', $tok['escopo']);
        $this->assertSame($oc, (int) $tok['entidade_id']);
        $this->assertSame($this->clienteId, (int) $tok['cliente_id']);
    }

    public function testChegadaQueFalhaNaoDeixaMensagemSolta(): void
    {
        $antes = count($this->mensagens());

        $r = $this->oc->registrar(['cliente' => ['id' => $this->clienteId], 'veiculo' => ['id' => $this->veiculoId], 'relato' => ''], $this->adminId, 'ADMIN');

        $this->assertFalse($r['ok']);
        $this->assertCount($antes, $this->mensagens(), 'sem o fato, sem a mensagem');
    }

    public function testPrimeiraAvaliacaoAvisaMasReavaliarNao(): void
    {
        $oc = $this->chegada();
        $this->avaliar($oc);

        $this->assertCount(1, $this->mensagens('AVALIACAO_INICIADA'));

        // Reavaliar é ajuste interno: não manda outra mensagem ao cliente.
        $this->av->iniciar($oc, $this->tec1);
        $this->assertCount(1, $this->mensagens('AVALIACAO_INICIADA'));
    }

    public function testOrcamentoEnviadoAprovadoERecusadoCadaUmAvisaUmaVez(): void
    {
        $oc = $this->chegada();
        $orcId = $this->av->gerarOrcamento($this->avaliar($oc), $this->adminId)['id'];

        $this->assertCount(0, $this->mensagens('ORCAMENTO_DISPONIVEL'), 'orçamento ainda não enviado: nada a avisar');

        $this->assertTrue($this->orc->enviar($orcId)['ok']);
        $this->assertTrue($this->orc->enviar($orcId)['repetido']);
        $disp = $this->mensagens('ORCAMENTO_DISPONIVEL');
        $this->assertCount(1, $disp, 'reenviar o mesmo orçamento não duplica o aviso');
        $this->assertSame(1, preg_match('~portal\?t=([A-Za-z0-9_-]+)~', $disp[0]['corpo'], $achou));
        $tok = PortalToken::resolver($this->pdo, $achou[1], false);
        $this->assertSame(['ORCAMENTO', $orcId], [$tok['escopo'], (int) $tok['entidade_id']]);

        $this->assertTrue($this->orc->recusar($orcId, 'Achou caro')['ok']);
        $this->assertCount(1, $this->mensagens('ORCAMENTO_RECUSADO'));
        $this->assertCount(0, $this->mensagens('ORCAMENTO_APROVADO'), 'recusado não gera aviso de aprovação');

        $this->assertTrue($this->orc->reabrir($orcId)['ok']);
        $a = $this->orc->aprovar($orcId, null, ['usuario_id' => $this->adminId]);
        $this->assertTrue($a['ok'], (string) $a['erro']);
        $this->fx->registrar('ordens_servico', (int) $a['os_id']);
        $this->assertTrue($this->orc->aprovar($orcId)['repetido']);

        $this->assertCount(1, $this->mensagens('ORCAMENTO_APROVADO'), 'aprovar de novo não duplica o aviso');
    }

    public function testOrcamentoRecusadoDuasVezesAvisaDuasVezesPoisSaoDoisFatos(): void
    {
        $oc = $this->chegada();
        $orcId = $this->av->gerarOrcamento($this->avaliar($oc), $this->adminId)['id'];
        $this->orc->enviar($orcId);

        $this->orc->recusar($orcId, 'Achou caro');
        $this->orc->recusar($orcId, 'Achou caro'); // mesmo estado: repetido, sem novo fato
        $this->assertCount(1, $this->mensagens('ORCAMENTO_RECUSADO'));

        $this->orc->reabrir($orcId);
        $this->orc->enviar($orcId);
        $this->orc->recusar($orcId, 'Mudou de ideia de novo');
        $this->assertCount(2, $this->mensagens('ORCAMENTO_RECUSADO'), 'uma segunda recusa REAL é um fato novo');
    }

    public function testOsAvisaInicioProntoParaRetiradaEConclusaoSemDuplicar(): void
    {
        $osId = $this->novaOs();

        $this->assertCount(0, $this->mensagens('SERVICO_INICIADO'));
        $this->iniciar($osId);
        $this->assertCount(1, $this->mensagens('SERVICO_INICIADO'));
        $this->assertTrue($this->mover($osId, 'EM_ANDAMENTO', 'TECNICO')['repetido'] ?? false);
        $this->assertCount(1, $this->mensagens('SERVICO_INICIADO'), 'repetir a ação não avisa de novo');

        $this->consumirTudo($osId);
        $this->assertTrue($this->mover($osId, 'AGUARDANDO_CONFERENCIA', 'TECNICO')['ok']);
        $this->assertCount(0, $this->mensagens('PRONTO_RETIRADA'), 'ainda não passou pela conferência do administrador');

        $this->assertTrue($this->mover($osId, 'AGUARDANDO_RETIRADA', 'ADMIN')['ok']);
        $pronto = $this->mensagens('PRONTO_RETIRADA');
        $this->assertCount(1, $pronto);
        $this->assertSame(1, preg_match('~portal\?t=([A-Za-z0-9_-]+)~', $pronto[0]['corpo'], $achou));
        $tok = PortalToken::resolver($this->pdo, $achou[1], false);
        $this->assertSame(['OS', $osId], [$tok['escopo'], (int) $tok['entidade_id']]);

        $this->assertTrue($this->mover($osId, 'AGUARDANDO_RETIRADA', 'ADMIN')['repetido']);
        $this->assertCount(1, $this->mensagens('PRONTO_RETIRADA'));

        $this->assertTrue($this->mover($osId, 'CONCLUIDA', 'ADMIN')['ok']);
        $this->assertCount(1, $this->mensagens('OS_CONCLUIDA'));
    }

    public function testDevolverParaExecucaoERefazerAConferenciaAvisaDeNovoPoisEUmNovoFato(): void
    {
        $osId = $this->novaOs();
        $this->ateRetirada($osId);
        $this->assertCount(1, $this->mensagens('PRONTO_RETIRADA'));

        // O administrador devolve à execução (motivo obrigatório) e confere outra vez.
        $r = $this->mover($osId, 'EM_ANDAMENTO', 'ADMIN', ['motivo' => 'Faltou apertar a roda dianteira esquerda']);
        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->assertTrue($this->mover($osId, 'AGUARDANDO_CONFERENCIA', 'TECNICO')['ok']);
        $this->assertTrue($this->mover($osId, 'AGUARDANDO_RETIRADA', 'ADMIN')['ok']);

        $this->assertCount(2, $this->mensagens('PRONTO_RETIRADA'));
        $this->assertCount(1, $this->mensagens('SERVICO_INICIADO'), 'voltar à execução não é "iniciar" de novo');
    }

    public function testAutorizacaoAdicionalAvisaOClienteComLinkDeDecisao(): void
    {
        $oc = $this->chegada();
        $this->avaliar($oc);
        $this->aut->registrarValorAberto($oc, [
            'escopo' => 'Troca do par de amortecedores dianteiros e buchas da bandeja', 'canal' => 'PRESENCIAL',
            'evidencia' => 'Cliente confirmou ao balcão às 15h20', 'limite_valor' => '300',
        ], $this->adminId);
        $r = $this->oc->abrirOsDireta($oc, $this->adminId);
        $this->assertTrue($r['ok'], (string) $r['erro']);
        $osId = (int) $r['os_id'];
        $this->fx->registrar('ordens_servico', $osId);
        $this->iniciar($osId);

        $p = $this->aut->solicitarComplemento($osId, 150, 'Encontrado desalinhamento grave que não estava no escopo', $this->tec1);
        $this->assertTrue($p['ok'], (string) $p['erro']);

        $m = $this->mensagens('AUTORIZACAO_ADICIONAL');
        $this->assertCount(1, $m);
        $this->assertStringContainsString('150', $m[0]['corpo']);
        $this->assertSame(1, preg_match('~portal\?t=([A-Za-z0-9_-]+)~', $m[0]['corpo'], $achou));
        $this->assertSame(['OS', $osId], [PortalToken::resolver($this->pdo, $achou[1], false)['escopo'], $osId]);

        // Pedido que o sistema recusa não vira mensagem.
        $negado = $this->aut->solicitarComplemento($osId, 150, 'ruim', $this->tec1);
        $this->assertFalse($negado['ok']);
        $this->assertCount(1, $this->mensagens('AUTORIZACAO_ADICIONAL'));
    }

    public function testPagamentoNoBalcaoConfirmaUmaVezPorRecebimento(): void
    {
        $osId = $this->novaOs();
        $this->ateRetirada($osId);
        $pag = new OrdemServicoPagamento();
        $pag->salvarPlano($osId, ['pagamento_tipo' => 'AVISTA']);
        $parcela = (int) $pag->parcelas($osId)[0]['id_parcela'];

        $this->assertNull($pag->pagarParcela($parcela, 'PIX', $this->adminId), 'o recebimento passa');
        $this->assertCount(1, $this->mensagens('PAGAMENTO_CONFIRMADO'));

        $this->assertNotNull($pag->pagarParcela($parcela, 'PIX', $this->adminId), 'a mesma parcela não é recebida duas vezes');
        $this->assertCount(1, $this->mensagens('PAGAMENTO_CONFIRMADO'), 'e não avisa duas vezes');

        // Desfazer o recebimento e receber de novo é um fato novo (chave por tentativa).
        $est = $pag->estornarParcela($parcela, 'Recebido na parcela errada, refazendo', $this->adminId);
        $this->assertTrue($est['ok'], (string) $est['erro']);
        $this->assertNull($pag->pagarParcela($parcela, 'PIX', $this->adminId));
        $this->assertCount(2, $this->mensagens('PAGAMENTO_CONFIRMADO'));
    }

    public function testClienteSemContatoRegistraMasNaoFingeEnvio(): void
    {
        // O cadastro de teste não tem canal configurado no ambiente de teste: o histórico existe, honesto.
        $this->chegada();
        $m = $this->mensagens('VEICULO_RECEBIDO')[0];

        $this->assertContains($m['status'], ['NAO_CONFIGURADO', 'SEM_CONSENTIMENTO', 'SEM_CONTATO', 'PENDENTE']);
        $this->assertNotSame('ENVIADA', $m['status'], 'nada é marcado como enviado sem passar por um canal real');
    }
}
