<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/FakeTransporte.php';
require_once __DIR__ . '/../app/core/WhatsAppCloud.php';

/**
 * Adaptador do WhatsApp Business (API oficial). Sem rede: um transporte falso devolve as respostas.
 * O que se confere é o que SERIA enviado, a regra da janela de 24 h, e a autenticação do webhook.
 */
final class WhatsAppCloudTest extends TestCase
{
    private const SEGREDO = 'segredo-do-app-de-teste';

    private function cfg(array $extra = []): array
    {
        return $extra + [
            'ativo' => true, 'phone_number_id' => '1234567890', 'access_token' => 'token-de-teste', 'app_secret' => self::SEGREDO,
            'verify_token' => 'verifica-123', 'api_version' => 'v20.0', 'idioma' => 'pt_BR', 'templates' => ['ORCAMENTO_DISPONIVEL' => 'orcamento_pronto'],
        ];
    }

    public function testSemCredenciaisNaoEstaConfiguradoENadaEEnviado(): void
    {
        $http = new FakeTransporte();
        $wa = new WhatsAppCloud($this->cfg(['access_token' => '', 'ativo' => false]), $http);

        $this->assertFalse($wa->configurado());
        $r = $wa->enviarTexto('18999998888', 'oi');

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('não configurado', $r['erro']);
        $this->assertSame([], $http->chamadas, 'sem configuração nenhuma requisição sai');
    }

    public function testTextoVaiParaOEndpointCertoComTokenENumeroNormalizado(): void
    {
        $http = (new FakeTransporte())->enfileirar(200, ['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.ABC']]]);
        $wa = new WhatsAppCloud($this->cfg(), $http);

        $r = $wa->enviarTexto('(18) 99999-8888', 'Olá, tudo bem?');

        $this->assertTrue($r['ok']);
        $this->assertSame('wamid.ABC', $r['id']);
        $c = $http->chamadas[0];
        $this->assertSame('POST', $c['metodo']);
        $this->assertSame('https://graph.facebook.com/v20.0/1234567890/messages', $c['url']);
        $this->assertContains('Authorization: Bearer token-de-teste', $c['cabecalhos']);
        $corpo = $http->ultimoCorpo();
        $this->assertSame('whatsapp', $corpo['messaging_product']);
        $this->assertSame('5518999998888', $corpo['to'], 'DDI 55 acrescentado e só dígitos');
        $this->assertSame('text', $corpo['type']);
        $this->assertSame('Olá, tudo bem?', $corpo['text']['body']);
    }

    public function testTemplateLevaNomeIdiomaEParametrosNaOrdem(): void
    {
        $http = (new FakeTransporte())->enfileirar(200, ['messages' => [['id' => 'wamid.T1']]]);
        $wa = new WhatsAppCloud($this->cfg(), $http);

        $r = $wa->enviarTemplate('5518999998888', 'orcamento_pronto', ['Maria', 'ABC1D23', 'https://x/p?t=1']);

        $this->assertTrue($r['ok']);
        $t = $http->ultimoCorpo()['template'];
        $this->assertSame('orcamento_pronto', $t['name']);
        $this->assertSame('pt_BR', $t['language']['code']);
        $this->assertSame(['Maria', 'ABC1D23', 'https://x/p?t=1'], array_column($t['components'][0]['parameters'], 'text'));
        $this->assertSame('orcamento_pronto', $wa->templateDoEvento('ORCAMENTO_DISPONIVEL'));
        $this->assertNull($wa->templateDoEvento('OS_CONCLUIDA'));
    }

    public function testErrosDaApiSaoClassificadosEmRetentavelOuDefinitivo(): void
    {
        $http = (new FakeTransporte())
            ->enfileirar(401, ['error' => ['message' => 'Invalid OAuth access token.', 'code' => 190]])
            ->enfileirar(503, 'indisponível')
            ->enfileirar(0, '', 'timeout')
            ->enfileirar(200, ['sem' => 'id']);
        $wa = new WhatsAppCloud($this->cfg(), $http);

        $token = $wa->enviarTexto('5518999998888', 'a');
        $this->assertFalse($token['ok']);
        $this->assertFalse($token['retentavel'], 'token inválido não adianta repetir');
        $this->assertStringContainsString('190', $token['erro']);

        $this->assertTrue($wa->enviarTexto('5518999998888', 'b')['retentavel'], '5xx se repete');
        $rede = $wa->enviarTexto('5518999998888', 'c');
        $this->assertTrue($rede['retentavel']);
        $this->assertStringContainsString('Sem resposta', $rede['erro']);
        $this->assertFalse($wa->enviarTexto('5518999998888', 'd')['ok'], '200 sem id de mensagem não conta como enviado');
    }

    public function testTelefoneInvalidoNaoChamaAApi(): void
    {
        $http = new FakeTransporte();
        $wa = new WhatsAppCloud($this->cfg(), $http);

        $this->assertFalse($wa->enviarTexto('123', 'oi')['ok']);
        $this->assertSame([], $http->chamadas);
    }

    public function testNormalizacaoDeTelefone(): void
    {
        $this->assertSame('5518999998888', WhatsAppCloud::normalizarTelefone('(18) 99999-8888'));
        $this->assertSame('551833334444', WhatsAppCloud::normalizarTelefone('18 3333-4444'));
        $this->assertSame('5518999998888', WhatsAppCloud::normalizarTelefone('+55 18 99999-8888'));
        $this->assertNull(WhatsAppCloud::normalizarTelefone('12345'));
        $this->assertNull(WhatsAppCloud::normalizarTelefone(''));
        $this->assertNull(WhatsAppCloud::normalizarTelefone(null));
    }

    public function testJanelaDe24Horas(): void
    {
        $agora = strtotime('2026-10-09 12:00:00');

        $this->assertTrue(WhatsAppCloud::dentroDaJanela('2026-10-09 09:00:00', $agora));
        $this->assertTrue(WhatsAppCloud::dentroDaJanela('2026-10-08 12:00:01', $agora), 'por um segundo ainda vale');
        $this->assertFalse(WhatsAppCloud::dentroDaJanela('2026-10-08 12:00:00', $agora), '24 h exatas já é fora');
        $this->assertFalse(WhatsAppCloud::dentroDaJanela('2026-10-01 12:00:00', $agora));
        $this->assertFalse(WhatsAppCloud::dentroDaJanela(null, $agora), 'cliente nunca escreveu: sem janela');
    }

    /* ── webhook ── */

    public function testAssinaturaDoWebhook(): void
    {
        $wa = new WhatsAppCloud($this->cfg(), new FakeTransporte());
        $corpo = '{"object":"whatsapp_business_account","entry":[]}';
        $certa = 'sha256=' . hash_hmac('sha256', $corpo, self::SEGREDO);

        $this->assertTrue($wa->validarAssinatura($corpo, $certa));
        $this->assertFalse($wa->validarAssinatura($corpo . ' ', $certa), 'corpo alterado');
        $this->assertFalse($wa->validarAssinatura($corpo, 'sha256=' . hash_hmac('sha256', $corpo, 'outro-segredo')), 'segredo errado');
        $this->assertFalse($wa->validarAssinatura($corpo, null), 'sem cabeçalho');
        $this->assertFalse($wa->validarAssinatura($corpo, hash_hmac('sha256', $corpo, self::SEGREDO)), 'sem o prefixo sha256=');
        $this->assertFalse((new WhatsAppCloud($this->cfg(['app_secret' => '']), new FakeTransporte()))->validarAssinatura($corpo, $certa), 'sem App Secret nada passa');
    }

    public function testVerificacaoInicialDoWebhook(): void
    {
        $wa = new WhatsAppCloud($this->cfg(), new FakeTransporte());

        $this->assertSame('12345', $wa->desafioDaVerificacao(['hub_mode' => 'subscribe', 'hub_verify_token' => 'verifica-123', 'hub_challenge' => '12345']));
        $this->assertSame('99', $wa->desafioDaVerificacao(['hub.mode' => 'subscribe', 'hub.verify_token' => 'verifica-123', 'hub.challenge' => '99']), 'aceita os dois formatos de nome');
        $this->assertNull($wa->desafioDaVerificacao(['hub_mode' => 'subscribe', 'hub_verify_token' => 'errado', 'hub_challenge' => '12345']));
        $this->assertNull($wa->desafioDaVerificacao(['hub_mode' => 'unsubscribe', 'hub_verify_token' => 'verifica-123', 'hub_challenge' => '12345']));
        $this->assertNull($wa->desafioDaVerificacao([]));
        $this->assertNull((new WhatsAppCloud($this->cfg(['verify_token' => '']), new FakeTransporte()))->desafioDaVerificacao(['hub_mode' => 'subscribe', 'hub_verify_token' => '', 'hub_challenge' => 'x']));
    }

    public function testInterpretaMensagensERecibosDoWebhook(): void
    {
        $wa = new WhatsAppCloud($this->cfg(), new FakeTransporte());

        $r = $wa->interpretar(['entry' => [['changes' => [['value' => [
            'contacts' => [['wa_id' => '5518999998888', 'profile' => ['name' => 'Maria Silva']]],
            'messages' => [
                ['from' => '5518999998888', 'id' => 'wamid.IN1', 'timestamp' => '1790000000', 'type' => 'text', 'text' => ['body' => 'Meu carro está pronto?']],
                ['from' => '5518999998888', 'id' => 'wamid.IN2', 'timestamp' => '1790000100', 'type' => 'image', 'image' => ['id' => 'x']],
            ],
            'statuses' => [
                ['id' => 'wamid.OUT1', 'status' => 'delivered', 'timestamp' => '1790000200'],
                ['id' => 'wamid.OUT2', 'status' => 'failed', 'timestamp' => '1790000300', 'errors' => [['title' => 'Re-engagement message', 'message' => 'fora da janela']]],
            ],
        ]]]]]]);

        $this->assertCount(2, $r['mensagens']);
        $this->assertSame('wamid.IN1', $r['mensagens'][0]['id']);
        $this->assertSame('5518999998888', $r['mensagens'][0]['de']);
        $this->assertSame('Maria Silva', $r['mensagens'][0]['nome']);
        $this->assertSame('Meu carro está pronto?', $r['mensagens'][0]['texto']);
        $this->assertStringContainsString('image', $r['mensagens'][1]['texto'], 'tipo sem texto vira um aviso, não some');

        $this->assertCount(2, $r['status']);
        $this->assertSame('delivered', $r['status'][0]['status']);
        $this->assertStringContainsString('fora da janela', $r['status'][1]['erro']);
        $this->assertSame(['mensagens' => [], 'status' => []], $wa->interpretar([]), 'payload vazio ou estranho não quebra');
        $this->assertSame(['mensagens' => [], 'status' => []], $wa->interpretar(['entry' => 'lixo']));
    }
}
