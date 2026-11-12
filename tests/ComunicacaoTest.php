<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures.php';
require_once __DIR__ . '/FakeTransporte.php';
require_once __DIR__ . '/../app/models/Comunicacao.php';
require_once __DIR__ . '/../app/models/Conversa.php';
require_once __DIR__ . '/../app/models/Consentimento.php';
require_once __DIR__ . '/../app/models/PortalToken.php';

/**
 * Central de conversas e comunicação com o cliente.
 *
 * Sem rede (transporte falso) e sem SMTP (função falsa): o que se confere é a DECISÃO — canal,
 * consentimento, janela de 24 h, template — e o REGISTRO honesto do resultado de cada mensagem.
 */
final class ComunicacaoTest extends TestCase
{
    private PDO $pdo;
    private Fixtures $fx;
    private int $adminId;
    private int $atendenteId;
    private array $emails = [];

    protected function setUp(): void
    {
        $this->pdo = new Database();
        $this->fx = new Fixtures($this->pdo);
        $this->adminId = $this->fx->usuario('ADMIN');
        $this->atendenteId = $this->fx->usuario('ATENDENTE');
        $this->emails = [];
    }

    protected function tearDown(): void
    {
        $this->fx->limpar();
    }

    private function telefone(): string
    {
        return sprintf('(99) 9%04d-%04d', random_int(1000, 9999), random_int(1000, 9999));
    }

    private function cliente(array $extra = []): int
    {
        return $this->fx->cliente($extra + ['telefone' => $this->telefone(), 'nome' => 'Maria da Silva Teste']);
    }

    private function cfg(array $extra = []): array
    {
        return $extra + [
            'ativo' => true, 'phone_number_id' => '111', 'access_token' => 'tok', 'app_secret' => 'seg', 'verify_token' => 'ver',
            'api_version' => 'v20.0', 'idioma' => 'pt_BR', 'templates' => [],
        ];
    }

    private function servico(?FakeTransporte $http = null, array $cfgWa = [], bool $email = false, ?callable $enviarEmail = null): Comunicacao
    {
        $http = $http ?? new FakeTransporte();
        $wa = new WhatsAppCloud($cfgWa === ['semwa'] ? $this->cfg(['ativo' => false]) : $this->cfg($cfgWa), $http);

        return new Comunicacao($this->pdo, $wa, $enviarEmail ?? function (string $para, string $assunto, string $html, string $texto): void {
            $this->emails[] = compact('para', 'assunto', 'html', 'texto');
        }, $email);
    }

    private function msg(int $id): array
    {
        return $this->pdo->query("SELECT * FROM mensagens WHERE id_mensagem = {$id}")->fetch(PDO::FETCH_ASSOC);
    }

    private function dado(array $extra = []): array
    {
        return $extra + ['placa' => 'ABC1D23', 'veiculo' => 'Fiat Uno', 'numero' => 'OS-1'];
    }

    /* ── decisão do canal e registro honesto ── */

    public function testSemNenhumCanalConfiguradoARegistraEMasNaoFingeEnvio(): void
    {
        $cli = $this->cliente();
        $s = $this->servico(null, ['semwa'], false);

        $id = $s->registrarEvento('VEICULO_RECEBIDO', $cli, $this->dado(), 'OCORRENCIA:1:RECEBIDO');

        $m = $this->msg($id);
        $this->assertSame('NAO_CONFIGURADO', $m['status']);
        $this->assertStringContainsString('Nenhum canal', $m['erro']);
        $this->assertSame('SAIDA', $m['direcao']);
        $this->assertSame('AUTOMATICA', $m['tipo']);
        $this->assertStringContainsString('Maria', $m['corpo']);
        $this->assertStringContainsString('ABC1D23', $m['corpo']);
        $this->assertFalse($s->haPendentes(), 'nada vai para a fila de envio');
        $this->assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM conversas WHERE cliente_id = {$cli}")->fetchColumn(), 'mas a conversa e o histórico existem');
    }

    public function testOMesmoFatoNuncaGeraDuasMensagens(): void
    {
        $cli = $this->cliente();
        $s = $this->servico(null, ['semwa']);

        $a = $s->registrarEvento('ORCAMENTO_APROVADO', $cli, $this->dado(), 'ORCAMENTO:7:APROVADO');
        $b = $s->registrarEvento('ORCAMENTO_APROVADO', $cli, $this->dado(), 'ORCAMENTO:7:APROVADO');
        $c = $s->registrarEvento('ORCAMENTO_APROVADO', $cli, $this->dado(), 'ORCAMENTO:8:APROVADO');

        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
        $this->assertSame(2, (int) $this->pdo->query("SELECT COUNT(*) FROM mensagens m JOIN conversas c ON c.id_conversa = m.conversa_id WHERE c.cliente_id = {$cli}")->fetchColumn());
    }

    public function testWhatsAppSoComConsentimentoRegistrado(): void
    {
        $cli = $this->cliente();
        $s = $this->servico();

        $semConsent = $s->registrarEvento('VEICULO_RECEBIDO', $cli, $this->dado(), 'A');
        $this->assertSame('SEM_CONSENTIMENTO', $this->msg($semConsent)['status']);
        $this->assertStringContainsString('consentimento', $this->msg($semConsent)['erro']);

        Consentimento::registrar($this->pdo, $cli, 'WHATSAPP', true, 'BALCAO', $this->adminId, 'Autorizou no balcão');
        $comConsent = $s->registrarEvento('AVALIACAO_INICIADA', $cli, $this->dado(), 'B');
        $m = $this->msg($comConsent);

        $this->assertSame('PENDENTE', $m['status']);
        $this->assertSame('WHATSAPP', $m['canal']);
        $this->assertStringStartsWith('5599', $m['destino'], 'destino normalizado com o 55');
    }

    public function testQuemPediuParaPararNaoRecebe(): void
    {
        $cli = $this->cliente();
        Consentimento::registrar($this->pdo, $cli, 'WHATSAPP', true, 'BALCAO', $this->adminId);
        Consentimento::registrar($this->pdo, $cli, 'WHATSAPP', false, 'WHATSAPP', null, 'Mandou PARAR');

        $id = $this->servico()->registrarEvento('VEICULO_RECEBIDO', $cli, $this->dado(), 'C');

        $this->assertSame('SEM_CONSENTIMENTO', $this->msg($id)['status']);
        $this->assertFalse(Consentimento::permite($this->pdo, $cli, 'WHATSAPP'));
    }

    public function testSemConsentimentoNoWhatsAppUsaEmailSeEstiverConfigurado(): void
    {
        $cli = $this->cliente(['email' => 'maria@teste.example']);
        $id = $this->servico(null, [], true)->registrarEvento('VEICULO_RECEBIDO', $cli, $this->dado(), 'D');

        $m = $this->msg($id);
        $this->assertSame('PENDENTE', $m['status']);
        $this->assertSame('EMAIL', $m['canal'], 'um canal só por mensagem: não duplica por WhatsApp e e-mail');
        $this->assertSame('maria@teste.example', $m['destino']);
    }

    public function testClienteQuePediuParaNaoReceberEmailNaoRecebe(): void
    {
        $cli = $this->cliente(['email' => 'maria@teste.example']);
        Consentimento::registrar($this->pdo, $cli, 'EMAIL', false, 'TELEFONE', $this->adminId);

        $id = $this->servico(null, ['semwa'], true)->registrarEvento('VEICULO_RECEBIDO', $cli, $this->dado(), 'E');

        $this->assertSame('SEM_CONSENTIMENTO', $this->msg($id)['status']);
    }

    public function testClienteSemTelefoneNemEmailFicaSemContato(): void
    {
        $cli = $this->fx->cliente(['nome' => 'Sem Contato', 'telefone' => null]);
        $id = $this->servico()->registrarEvento('VEICULO_RECEBIDO', $cli, $this->dado(), 'F');

        $this->assertSame('SEM_CONTATO', $this->msg($id)['status']);
        $this->assertSame('MANUAL', $this->msg($id)['canal']);
    }

    public function testEventoParaClienteInexistenteNaoQuebra(): void
    {
        $this->assertNull($this->servico()->registrarEvento('VEICULO_RECEBIDO', 99999999, $this->dado(), 'G'));
    }

    public function testLinkDoPortalVaiNaMensagemEOBancoGuardaSoOHash(): void
    {
        $cli = $this->cliente();
        $id = $this->servico(null, ['semwa'])->registrarEvento('ORCAMENTO_DISPONIVEL', $cli, $this->dado(['valor' => 1500]), 'ORCAMENTO:9:ENVIADO', 'ORCAMENTO', 9, ['escopo' => 'ORCAMENTO', 'entidade' => 9]);

        $m = $this->msg($id);
        $this->assertSame(1, preg_match('#portal\?t=([A-Za-z0-9_-]{43})#', $m['corpo'], $achou), 'link com token de 43 caracteres');
        $token = $achou[1];
        $this->assertStringContainsString('R$ 1.500,00', $m['corpo']);

        $linha = $this->pdo->query("SELECT * FROM portal_tokens WHERE cliente_id = {$cli}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(hash('sha256', $token), $linha['token_hash']);
        $this->assertStringNotContainsString($token, json_encode($linha), 'o token bruto não está no banco');
        $this->assertSame('ORCAMENTO', $linha['escopo']);
        $this->assertSame(9, (int) $linha['entidade_id']);
    }

    /* ── envio ── */

    public function testDentroDaJanelaVaiTextoLivreEOStatusFicaEnviadaComOIdDoProvedor(): void
    {
        $cli = $this->cliente();
        Consentimento::registrar($this->pdo, $cli, 'WHATSAPP', true, 'BALCAO', $this->adminId);
        $http = (new FakeTransporte())->enfileirar(200, ['messages' => [['id' => 'wamid.OK1']]]);
        $s = $this->servico($http);

        $id = $s->registrarEvento('VEICULO_RECEBIDO', $cli, $this->dado(), 'H');
        // o cliente escreveu há pouco: a janela de 24 h está aberta
        $this->pdo->exec("UPDATE conversas SET ultima_entrada_em = NOW() WHERE cliente_id = {$cli}");

        $this->assertSame('ENVIADA', $s->enviarMensagem($id));

        $m = $this->msg($id);
        $this->assertSame('wamid.OK1', $m['provider_message_id']);
        $this->assertNotNull($m['enviada_em']);
        $this->assertSame('text', $http->ultimoCorpo()['type']);
        $this->assertNull($s->enviarMensagem($id), 'enviar de novo não faz nada');
        $this->assertCount(1, $http->chamadas);
    }

    public function testForaDaJanelaSemTemplateNaoEnviaEDizPor_que(): void
    {
        $cli = $this->cliente();
        Consentimento::registrar($this->pdo, $cli, 'WHATSAPP', true, 'BALCAO', $this->adminId);
        $http = new FakeTransporte();
        $s = $this->servico($http);

        $id = $s->registrarEvento('VEICULO_RECEBIDO', $cli, $this->dado(), 'I');

        $this->assertSame('NAO_CONFIGURADO', $s->enviarMensagem($id));
        $this->assertStringContainsString('24 h', $this->msg($id)['erro']);
        $this->assertSame([], $http->chamadas, 'nenhuma tentativa fora da regra da plataforma');
    }

    public function testForaDaJanelaComTemplateAprovadoEnviaOTemplateComOsParametros(): void
    {
        $cli = $this->cliente();
        Consentimento::registrar($this->pdo, $cli, 'WHATSAPP', true, 'BALCAO', $this->adminId);
        $http = (new FakeTransporte())->enfileirar(200, ['messages' => [['id' => 'wamid.TPL']]]);
        $s = $this->servico($http, ['templates' => ['ORCAMENTO_DISPONIVEL' => 'orcamento_pronto']]);

        $id = $s->registrarEvento('ORCAMENTO_DISPONIVEL', $cli, $this->dado(['valor' => 800]), 'J', null, null, ['escopo' => 'ORCAMENTO', 'entidade' => 1]);

        $this->assertSame('ENVIADA', $s->enviarMensagem($id));
        $t = $http->ultimoCorpo()['template'];
        $this->assertSame('orcamento_pronto', $t['name']);
        $parametros = array_column($t['components'][0]['parameters'], 'text');
        $this->assertSame('Maria', $parametros[0]);
        $this->assertSame('ABC1D23', $parametros[1]);
        $this->assertStringContainsString('portal?t=', $parametros[2]);
    }

    public function testFalhaDeRedeTentaDeNovoAteOLimiteEDepoisFicaComoFalha(): void
    {
        $cli = $this->cliente();
        Consentimento::registrar($this->pdo, $cli, 'WHATSAPP', true, 'BALCAO', $this->adminId);
        $http = (new FakeTransporte())->enfileirar(503, 'x')->enfileirar(503, 'x')->enfileirar(503, 'x');
        $s = $this->servico($http);

        $id = $s->registrarEvento('VEICULO_RECEBIDO', $cli, $this->dado(), 'K');
        $this->pdo->exec("UPDATE conversas SET ultima_entrada_em = NOW() WHERE cliente_id = {$cli}");

        $this->assertSame('PENDENTE', $s->enviarMensagem($id), '1ª falha: volta para a fila');
        $this->assertSame('PENDENTE', $s->enviarMensagem($id), '2ª falha: volta para a fila');
        $this->assertSame('FALHA', $s->enviarMensagem($id), '3ª falha: desiste e registra');
        $this->assertSame(3, (int) $this->msg($id)['tentativas']);
        $this->assertNotEmpty($this->msg($id)['erro']);
        $this->assertNull($s->enviarMensagem($id), 'FALHA não é reenviada sozinha');
    }

    public function testErroDefinitivoDoProvedorNaoEReenviado(): void
    {
        $cli = $this->cliente();
        Consentimento::registrar($this->pdo, $cli, 'WHATSAPP', true, 'BALCAO', $this->adminId);
        $http = (new FakeTransporte())->enfileirar(401, ['error' => ['message' => 'token inválido', 'code' => 190]]);
        $s = $this->servico($http);

        $id = $s->registrarEvento('VEICULO_RECEBIDO', $cli, $this->dado(), 'L');
        $this->pdo->exec("UPDATE conversas SET ultima_entrada_em = NOW() WHERE cliente_id = {$cli}");

        $this->assertSame('FALHA', $s->enviarMensagem($id));
        $this->assertStringContainsString('190', $this->msg($id)['erro']);
    }

    public function testEmailEnviaTextoEscapadoComLinkClicavel(): void
    {
        $cli = $this->cliente(['email' => 'maria@teste.example', 'nome' => 'Maria <b>x</b>']);
        $s = $this->servico(null, ['semwa'], true);

        $id = $s->registrarEvento('ORCAMENTO_DISPONIVEL', $cli, $this->dado(['valor' => 100]), 'M', null, null, ['escopo' => 'ORCAMENTO', 'entidade' => 5]);

        $this->assertSame('ENVIADA', $s->enviarMensagem($id));
        $this->assertCount(1, $this->emails);
        $e = $this->emails[0];
        $this->assertSame('maria@teste.example', $e['para']);
        $this->assertStringContainsString('Orçamento disponível', $e['assunto']);
        $this->assertStringNotContainsString('<b>', $e['html'], 'HTML do cadastro é escapado');
        $this->assertMatchesRegularExpression('#<a href="http[^"]+portal\?t=#', $e['html']);
    }

    public function testExcecaoNoEmailVirraFalhaDepoisDasTentativas(): void
    {
        $cli = $this->cliente(['email' => 'maria@teste.example']);
        $s = $this->servico(null, ['semwa'], true, function () { throw new RuntimeException('SMTP caiu'); });

        $id = $s->registrarEvento('VEICULO_RECEBIDO', $cli, $this->dado(), 'N');

        $this->assertSame('PENDENTE', $s->enviarMensagem($id));
        $this->assertSame('PENDENTE', $s->enviarMensagem($id));
        $this->assertSame('FALHA', $s->enviarMensagem($id));
        $this->assertStringContainsString('SMTP caiu', $this->msg($id)['erro']);
    }

    public function testDespacharPendentesProcessaAFilaECondensaOResultado(): void
    {
        $cli = $this->cliente(['email' => 'maria@teste.example']);
        $s = $this->servico(null, ['semwa'], true);

        $s->registrarEvento('VEICULO_RECEBIDO', $cli, $this->dado(), 'O1');
        $s->registrarEvento('AVALIACAO_INICIADA', $cli, $this->dado(), 'O2');
        $this->assertTrue($s->haPendentes());

        $r = $s->despacharPendentes(10);

        $this->assertSame(2, $r['enviadas']);
        $this->assertFalse($s->haPendentes());
        $this->assertCount(2, $this->emails);
    }

    /* ── recepção ── */

    public function testMensagemDeClienteConhecidoCaiNaConversaDeleEAbreAJanela(): void
    {
        $cli = $this->cliente();
        $numero = '55' . preg_replace('/\D/', '', $this->pdo->query("SELECT telefone FROM clientes WHERE id_cliente = {$cli}")->fetchColumn());
        $s = $this->servico();

        $r = $s->receber('WHATSAPP', $numero, 'Maria no Zap', 'Meu carro já está pronto?', 'wamid.IN-' . uniqid(), time());

        $this->assertTrue($r['ok']);
        $this->assertFalse($r['duplicada']);
        $this->assertSame($cli, $r['cliente_id'], 'o telefone identificou o cadastro');
        $c = $this->pdo->query("SELECT * FROM conversas WHERE id_conversa = {$r['conversa_id']}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $c['nao_lidas']);
        $this->assertTrue(WhatsAppCloud::dentroDaJanela($c['ultima_entrada_em']), 'a mensagem do cliente abre a janela de 24 h');
    }

    public function testNumeroDesconhecidoViraConversaSemClienteParaIdentificar(): void
    {
        $r = $this->servico()->receber('WHATSAPP', '5599900001111', 'Fulano', 'Oi, vocês fazem alinhamento?', 'wamid.IN-' . uniqid(), time());

        $this->assertNull($r['cliente_id']);
        $c = $this->pdo->query("SELECT * FROM conversas WHERE id_conversa = {$r['conversa_id']}")->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($c['cliente_id']);
        $this->assertSame('Fulano', $c['nome_contato']);
        $this->assertSame('Contato não identificado', $c['assunto']);
    }

    public function testMesmaMensagemDoProvedorNaoDuplica(): void
    {
        $s = $this->servico();
        $id = 'wamid.DUP-' . uniqid();

        $a = $s->receber('WHATSAPP', '5599900002222', 'X', 'texto', $id, time());
        $b = $s->receber('WHATSAPP', '5599900002222', 'X', 'texto', $id, time());

        $this->assertTrue($b['duplicada']);
        $this->assertSame($a['conversa_id'], $b['conversa_id']);
        $this->assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM mensagens WHERE provider_message_id = '{$id}'")->fetchColumn());
        $this->assertSame(1, (int) $this->pdo->query("SELECT nao_lidas FROM conversas WHERE id_conversa = {$a['conversa_id']}")->fetchColumn(), 'o reenvio do webhook não conta como nova mensagem');
    }

    public function testVariasMensagensDoMesmoContatoUsamUmaConversaAberta(): void
    {
        $s = $this->servico();

        $a = $s->receber('WHATSAPP', '5599900003333', 'Y', 'primeira', 'wamid.A-' . uniqid(), time());
        $b = $s->receber('WHATSAPP', '5599900003333', 'Y', 'segunda', 'wamid.B-' . uniqid(), time());

        $this->assertSame($a['conversa_id'], $b['conversa_id']);
        $this->assertSame(2, (int) $this->pdo->query("SELECT nao_lidas FROM conversas WHERE id_conversa = {$a['conversa_id']}")->fetchColumn());

        // encerrada, a próxima abre uma nova
        (new Conversa($this->pdo))->mudarStatus($a['conversa_id'], 'ENCERRADA');
        $c = $s->receber('WHATSAPP', '5599900003333', 'Y', 'terceira', 'wamid.C-' . uniqid(), time());
        $this->assertNotSame($a['conversa_id'], $c['conversa_id']);
    }

    public function testEquipeEAvisadaDaMensagemNovaUmaVezSo(): void
    {
        $cli = $this->cliente();
        $numero = '55' . preg_replace('/\D/', '', $this->pdo->query("SELECT telefone FROM clientes WHERE id_cliente = {$cli}")->fetchColumn());
        $s = $this->servico();
        $id = 'wamid.AV-' . uniqid();

        $s->receber('WHATSAPP', $numero, 'M', 'preciso de uma resposta', $id, time());
        $s->receber('WHATSAPP', $numero, 'M', 'preciso de uma resposta', $id, time());

        $n = fn(int $u) => (int) $this->pdo->query("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = {$u} AND tipo = 'CONVERSA' AND chave_idempotencia = 'MSG:{$id}'")->fetchColumn();
        $this->assertSame(1, $n($this->adminId));
        $this->assertSame(1, $n($this->atendenteId));
    }

    public function testComResponsavelSoELeEAvisado(): void
    {
        $cli = $this->cliente();
        $numero = '55' . preg_replace('/\D/', '', $this->pdo->query("SELECT telefone FROM clientes WHERE id_cliente = {$cli}")->fetchColumn());
        $s = $this->servico();
        $primeira = $s->receber('WHATSAPP', $numero, 'M', 'oi', 'wamid.R1-' . uniqid(), time());
        (new Conversa($this->pdo))->atribuir($primeira['conversa_id'], $this->atendenteId);

        $id = 'wamid.R2-' . uniqid();
        $s->receber('WHATSAPP', $numero, 'M', 'e então?', $id, time());

        $n = fn(int $u) => (int) $this->pdo->query("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = {$u} AND chave_idempotencia = 'MSG:{$id}'")->fetchColumn();
        $this->assertSame(1, $n($this->atendenteId));
        $this->assertSame(0, $n($this->adminId));
    }

    /* ── recibos de entrega ── */

    public function testReciboNuncaRegride(): void
    {
        $cli = $this->cliente();
        Consentimento::registrar($this->pdo, $cli, 'WHATSAPP', true, 'BALCAO', $this->adminId);
        $http = (new FakeTransporte())->enfileirar(200, ['messages' => [['id' => 'wamid.REC-1']]]);
        $s = $this->servico($http);

        $id = $s->registrarEvento('VEICULO_RECEBIDO', $cli, $this->dado(), 'P');
        $this->pdo->exec("UPDATE conversas SET ultima_entrada_em = NOW() WHERE cliente_id = {$cli}");
        $s->enviarMensagem($id);

        $this->assertTrue($s->atualizarEntrega('wamid.REC-1', 'delivered'));
        $this->assertSame('ENTREGUE', $this->msg($id)['status']);
        $this->assertTrue($s->atualizarEntrega('wamid.REC-1', 'read'));
        $this->assertSame('LIDA', $this->msg($id)['status']);
        $this->assertFalse($s->atualizarEntrega('wamid.REC-1', 'delivered'), 'recibo atrasado não rebaixa LIDA');
        $this->assertFalse($s->atualizarEntrega('wamid.REC-1', 'failed'), 'falha depois de entregue é ignorada');
        $this->assertSame('LIDA', $this->msg($id)['status']);
        $this->assertFalse($s->atualizarEntrega('wamid.NAO-EXISTE', 'read'));
        $this->assertFalse($s->atualizarEntrega('wamid.REC-1', 'coisa-estranha'));
    }

    public function testFalhaReportadaPeloWhatsAppFicaRegistradaComOMotivo(): void
    {
        $cli = $this->cliente();
        Consentimento::registrar($this->pdo, $cli, 'WHATSAPP', true, 'BALCAO', $this->adminId);
        $http = (new FakeTransporte())->enfileirar(200, ['messages' => [['id' => 'wamid.FAIL-1']]]);
        $s = $this->servico($http);
        $id = $s->registrarEvento('VEICULO_RECEBIDO', $cli, $this->dado(), 'Q');
        $this->pdo->exec("UPDATE conversas SET ultima_entrada_em = NOW() WHERE cliente_id = {$cli}");
        $s->enviarMensagem($id);

        $this->assertTrue($s->atualizarEntrega('wamid.FAIL-1', 'failed', 'Número sem WhatsApp'));

        $this->assertSame('FALHA', $this->msg($id)['status']);
        $this->assertStringContainsString('Número sem WhatsApp', $this->msg($id)['erro']);
    }

    /* ── resposta da equipe ── */

    public function testRespostaManualNaJanelaVaiPeloWhatsAppEForaDelaERecusada(): void
    {
        $http = (new FakeTransporte())->enfileirar(200, ['messages' => [['id' => 'wamid.RESP-1']]]);
        $s = $this->servico($http);
        $r = $s->receber('WHATSAPP', '5599900004444', 'Zé', 'Oi', 'wamid.IN-' . uniqid(), time());

        $ok = $s->responder($r['conversa_id'], 'Olá, Zé! Já vamos te atender.', $this->atendenteId);
        $this->assertTrue($ok['ok'], (string) $ok['erro']);
        $this->assertSame('ENVIADA', $ok['status']);
        $this->assertSame('Olá, Zé! Já vamos te atender.', $http->ultimoCorpo()['text']['body']);
        $this->assertSame($this->atendenteId, (int) $this->msg($ok['id'])['autor_id']);
        $this->assertSame('AGUARDANDO_CLIENTE', $this->pdo->query("SELECT status FROM conversas WHERE id_conversa = {$r['conversa_id']}")->fetchColumn());
        $this->assertSame(0, (int) $this->pdo->query("SELECT nao_lidas FROM conversas WHERE id_conversa = {$r['conversa_id']}")->fetchColumn(), 'responder zera o não lido');

        $this->pdo->exec("UPDATE conversas SET ultima_entrada_em = DATE_SUB(NOW(), INTERVAL 30 HOUR) WHERE id_conversa = {$r['conversa_id']}");
        $fora = $s->responder($r['conversa_id'], 'E agora?', $this->atendenteId);
        $this->assertFalse($fora['ok']);
        $this->assertStringContainsString('24 h', $fora['erro']);
        $this->assertCount(1, $http->chamadas, 'a segunda não foi enviada');
    }

    public function testResponderValidaTextoConversaEncerradaCanalManualEWhatsAppNaoConfigurado(): void
    {
        $s = $this->servico();
        $r = $s->receber('WHATSAPP', '5599900005555', 'W', 'oi', 'wamid.IN-' . uniqid(), time());

        $this->assertFalse($s->responder($r['conversa_id'], '   ', $this->adminId)['ok'], 'vazio');
        $this->assertFalse($s->responder(99999999, 'oi', $this->adminId)['ok'], 'conversa inexistente');
        $this->assertFalse($this->servico(null, ['semwa'])->responder($r['conversa_id'], 'oi', $this->adminId)['ok'], 'WhatsApp não configurado');

        (new Conversa($this->pdo))->mudarStatus($r['conversa_id'], 'ENCERRADA');
        $this->assertFalse($s->responder($r['conversa_id'], 'oi', $this->adminId)['ok'], 'encerrada');

        $manual = Conversa::abertaOuNova($this->pdo, null, 'MANUAL', 'registro-teste-5599');
        $x = $s->responder($manual, 'oi', $this->adminId);
        $this->assertFalse($x['ok']);
        $this->assertStringContainsString('registrar contato', $x['erro']);
    }

    public function testRegistroManualFicaMarcadoComoTalENaoComoEnvio(): void
    {
        $s = $this->servico();
        $conversa = Conversa::abertaOuNova($this->pdo, null, 'MANUAL', 'registro-teste-5599');

        $entrada = $s->registrarManual($conversa, 'ENTRADA', 'Cliente ligou: quer saber do orçamento.', $this->atendenteId);
        $saida = $s->registrarManual($conversa, 'SAIDA', 'Retornei: orçamento sai amanhã.', $this->atendenteId);
        $nota = $s->registrarManual($conversa, 'NOTA', 'Cliente prefere contato à tarde.', $this->atendenteId);

        foreach ([$entrada, $saida, $nota] as $r) {
            $this->assertTrue($r['ok']);
            $m = $this->msg($r['id']);
            $this->assertSame('REGISTRADA', $m['status']);
            $this->assertSame('MANUAL', $m['canal']);
        }
        $this->assertFalse($s->registrarManual($conversa, 'ENTRADA', '', $this->adminId)['ok']);
        $this->assertFalse($s->registrarManual($conversa, 'QUALQUER', 'x', $this->adminId)['ok']);
        $this->assertFalse($s->registrarManual(99999999, 'NOTA', 'x', $this->adminId)['ok']);
    }

    /* ── conversas ── */

    public function testSoExisteUmaConversaAbertaPorContatoEVincularExigeMesmoCliente(): void
    {
        $c = new Conversa($this->pdo);
        $cliA = $this->cliente();
        $cliB = $this->cliente();
        $veiA = $this->fx->veiculo($cliA);
        $veiB = $this->fx->veiculo($cliB);

        $a1 = Conversa::abertaOuNova($this->pdo, $cliA, 'WHATSAPP', '5599900006666');
        $a2 = Conversa::abertaOuNova($this->pdo, $cliA, 'WHATSAPP', '5599900006666');
        $this->assertSame($a1, $a2);

        $this->assertTrue($c->vincular($a1, ['veiculo_id' => $veiA])['ok']);
        $r = $c->vincular($a1, ['veiculo_id' => $veiB]);
        $this->assertFalse($r['ok'], 'veículo de outro cliente não se liga');
        $this->assertStringContainsString('não pertence', $r['erro']);
        $this->assertSame($veiA, (int) $c->getById($a1)['veiculo_id']);
        $this->assertTrue($c->vincular($a1, ['veiculo_id' => null])['ok'], 'desligar é permitido');

        $semCliente = Conversa::abertaOuNova($this->pdo, null, 'WHATSAPP', '5599900007777');
        $this->assertFalse($c->vincular($semCliente, ['veiculo_id' => $veiA])['ok'], 'sem cliente identificado não liga nada');
    }

    public function testAssociarClienteJuntaAConversaSeJaHouverUmaAbertaDele(): void
    {
        $c = new Conversa($this->pdo);
        $s = $this->servico();
        $cli = $this->cliente();
        $numero = '5599900008888';

        $conhecida = Conversa::abertaOuNova($this->pdo, $cli, 'WHATSAPP', $numero);
        $anonima = $s->receber('WHATSAPP', $numero, 'Fulano', 'sou eu de novo', 'wamid.AS-' . uniqid(), time())['conversa_id'];

        // Cliente sem o telefone no cadastro: a mensagem chegou como "não identificada"
        $this->assertNotSame($conhecida, $anonima);

        $r = $c->associarCliente($anonima, $cli);

        $this->assertTrue($r['ok'], (string) ($r['erro'] ?? ''));
        $this->assertSame($conhecida, $r['conversa_id'], 'o histórico foi para a conversa que já existia');
        $this->assertNull($c->getById($anonima), 'a duplicata some');
        $this->assertGreaterThanOrEqual(1, count($c->mensagens($conhecida)));
        $this->assertFalse($c->associarCliente($conhecida, 99999999)['ok']);
    }

    public function testAtribuirEFiltrarConversas(): void
    {
        $c = new Conversa($this->pdo);
        $cli = $this->cliente();
        $id = Conversa::abertaOuNova($this->pdo, $cli, 'WHATSAPP', '5599900009999', 'Teste Filtro Único');

        $this->assertFalse($c->atribuir($id, 99999999)['ok']);
        $this->assertTrue($c->atribuir($id, $this->atendenteId)['ok']);

        $ids = fn(array $f) => array_map('intval', array_column($c->listar($f), 'id_conversa'));
        $this->assertContains($id, $ids(['responsavel_id' => $this->atendenteId]));
        $this->assertNotContains($id, $ids(['responsavel_id' => $this->adminId]));
        $this->assertContains($id, $ids(['busca' => 'Maria da Silva']));
        $this->assertContains($id, $ids(['cliente_id' => $cli]));

        $c->mudarStatus($id, 'ENCERRADA');
        $this->assertNotContains($id, $ids(['grupo' => 'abertas']));
        $this->assertContains($id, $ids(['status' => 'ENCERRADA']));
        $this->assertFalse($c->mudarStatus($id, 'INVENTADA')['ok']);
    }

    public function testNaoReabreConversaSeJaHouverOutraAbertaComOMesmoContato(): void
    {
        $c = new Conversa($this->pdo);
        $cli = $this->cliente();
        $velha = Conversa::abertaOuNova($this->pdo, $cli, 'WHATSAPP', '5599900001234');
        $c->mudarStatus($velha, 'ENCERRADA');
        $nova = Conversa::abertaOuNova($this->pdo, $cli, 'WHATSAPP', '5599900001234');

        $r = $c->mudarStatus($velha, 'ABERTA');

        $this->assertNotSame($velha, $nova);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('outra conversa aberta', $r['erro']);
    }

    /* ── consentimento ── */

    public function testConsentimentoEUmLivroQueSoAcrescentaERepetirNaoSuja(): void
    {
        $cli = $this->cliente();

        $this->assertNull(Consentimento::vigente($this->pdo, $cli, 'WHATSAPP'), 'nunca perguntado');
        $this->assertFalse(Consentimento::permite($this->pdo, $cli, 'WHATSAPP'), 'WhatsApp exige consentimento registrado');
        $this->assertTrue(Consentimento::permite($this->pdo, $cli, 'EMAIL'), 'e-mail operacional vale até pedirem para parar');

        $this->assertFalse(Consentimento::registrar($this->pdo, $cli, 'WHATSAPP', true, 'BALCAO', $this->adminId)['repetido']);
        $this->assertTrue(Consentimento::registrar($this->pdo, $cli, 'WHATSAPP', true, 'TELEFONE', $this->adminId)['repetido']);
        Consentimento::registrar($this->pdo, $cli, 'WHATSAPP', false, 'WHATSAPP', null, 'PARAR');

        $h = Consentimento::historico($this->pdo, $cli);
        $this->assertCount(2, $h);
        $this->assertSame([0, 1], array_map(fn($x) => (int) $x['concedido'], $h), 'mais recente primeiro: parou, depois aceitou');
        $this->assertFalse(Consentimento::registrar($this->pdo, $cli, 'SMS', true, 'BALCAO', null)['ok']);
        $this->assertFalse(Consentimento::registrar($this->pdo, 99999999, 'EMAIL', true, 'BALCAO', null)['ok']);
    }

    /* ── portal: tokens ── */

    public function testTokenDoPortalTemValidadeRevogacaoEContagemDeAcesso(): void
    {
        $cli = $this->cliente();
        $token = PortalToken::emitir($this->pdo, $cli, 'OS', 123);

        $this->assertTrue(PortalToken::formatoValido($token));
        $t = PortalToken::resolver($this->pdo, $token);
        $this->assertSame('OS', $t['escopo']);
        $this->assertSame(123, (int) $t['entidade_id']);

        PortalToken::resolver($this->pdo, $token);
        $lista = PortalToken::listarDaEntidade($this->pdo, 'OS', 123);
        $this->assertSame(2, (int) $lista[0]['acessos']);
        $this->assertSame('ATIVO', $lista[0]['situacao']);
        $this->assertArrayNotHasKey('token', $lista[0]);

        $this->assertSame(1, PortalToken::revogarDaEntidade($this->pdo, 'OS', 123));
        $this->assertNull(PortalToken::resolver($this->pdo, $token), 'revogado deixa de valer');
        $this->assertSame('REVOGADO', PortalToken::listarDaEntidade($this->pdo, 'OS', 123)[0]['situacao']);
    }

    public function testTokenExpiradoMalformadoOuInexistenteNaoValem(): void
    {
        $cli = $this->cliente();
        $token = PortalToken::emitir($this->pdo, $cli, 'ORCAMENTO', 1, null, 1);
        $this->pdo->exec("UPDATE portal_tokens SET expira_em = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE cliente_id = {$cli}");

        $this->assertNull(PortalToken::resolver($this->pdo, $token), 'expirado');
        $this->assertNull(PortalToken::resolver($this->pdo, 'curto'), 'malformado');
        $this->assertNull(PortalToken::resolver($this->pdo, str_repeat('A', 43)), 'inexistente');
        $this->assertNull(PortalToken::resolver($this->pdo, "' OR 1=1 --"), 'injeção');
        $this->assertSame('EXPIRADO', PortalToken::listarDaEntidade($this->pdo, 'ORCAMENTO', 1)[0]['situacao']);
        $this->expectException(InvalidArgumentException::class);
        PortalToken::emitir($this->pdo, $cli, 'QUALQUER', 1);
    }
}
