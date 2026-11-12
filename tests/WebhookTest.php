<?php

require_once __DIR__ . '/OsTestCase.php';
require_once __DIR__ . '/FakeTransporte.php';
require_once __DIR__ . '/../app/core/ProvedorPagamento.php';
require_once __DIR__ . '/../app/core/WhatsAppCloud.php';
require_once __DIR__ . '/../app/models/CobrancaOnline.php';
require_once __DIR__ . '/../app/models/Comunicacao.php';
require_once __DIR__ . '/../app/models/Consentimento.php';

/**
 * Entrada dos webhooks: a ASSINATURA do corpo bruto decide tudo.
 *
 * Sem assinatura válida nada é lido nem gravado; com ela, o mesmo evento reenviado não repete efeito.
 */
final class WebhookTest extends OsTestCase
{
    private const SEGREDO_PG = 'segredo-simulado-de-teste';
    private const APP_SECRET = 'app-secret-do-whatsapp-de-teste';

    private function cfgWa(): array
    {
        return ['ativo' => true, 'phone_number_id' => '111', 'access_token' => 'tok', 'app_secret' => self::APP_SECRET, 'verify_token' => 'ver123', 'api_version' => 'v20.0', 'idioma' => 'pt_BR', 'templates' => []];
    }

    private function comunicacao(): Comunicacao
    {
        return new Comunicacao($this->pdo, new WhatsAppCloud($this->cfgWa(), new FakeTransporte()), fn() => null, false);
    }

    private function assinar(string $corpo, string $segredo = self::APP_SECRET): string
    {
        return 'sha256=' . hash_hmac('sha256', $corpo, $segredo);
    }

    private function telefoneDoCliente(): string
    {
        $tel = sprintf('(99) 9%04d-%04d', random_int(1000, 9999), random_int(1000, 9999));
        $this->pdo->prepare('UPDATE clientes SET telefone = ? WHERE id_cliente = ?')->execute([$tel, $this->clienteId]);

        return '55' . preg_replace('/\D+/', '', $tel);
    }

    private function payloadMensagem(string $de, string $texto, string $id): string
    {
        return json_encode(['entry' => [['changes' => [['value' => [
            'contacts' => [['wa_id' => $de, 'profile' => ['name' => 'Cliente Teste']]],
            'messages' => [['id' => $id, 'from' => $de, 'type' => 'text', 'timestamp' => (string) time(), 'text' => ['body' => $texto]]],
        ]]]]]]);
    }

    /* ── WhatsApp ── */

    public function testVerificacaoDaUrlSoDevolveODesafioComOTokenCerto(): void
    {
        $wa = new WhatsAppCloud($this->cfgWa(), new FakeTransporte());

        $this->assertSame('abc', $wa->desafioDaVerificacao(['hub_mode' => 'subscribe', 'hub_verify_token' => 'ver123', 'hub_challenge' => 'abc']));
        $this->assertNull($wa->desafioDaVerificacao(['hub_mode' => 'subscribe', 'hub_verify_token' => 'errado', 'hub_challenge' => 'abc']));
        $this->assertNull($wa->desafioDaVerificacao(['hub_mode' => 'subscribe', 'hub_challenge' => 'abc']));
    }

    public function testMensagemComAssinaturaInvalidaOuAusenteNaoGravaNada(): void
    {
        $de = $this->telefoneDoCliente();
        $corpo = $this->payloadMensagem($de, 'Oi, tudo bem?', 'wamid.INVALIDA1');
        $com = $this->comunicacao();

        foreach ([null, '', 'sha256=' . str_repeat('0', 64), $this->assinar($corpo, 'outro-segredo'), $this->assinar($corpo . ' ')] as $assinatura) {
            $r = $com->receberWebhookWhatsApp($corpo, $assinatura);
            $this->assertSame(401, $r['http']);
        }

        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM mensagens WHERE provider_message_id = 'wamid.INVALIDA1'"));
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM conversas WHERE cliente_id = {$this->clienteId}"));
    }

    public function testMensagemDoClienteAbreConversaIdentificadaEAvisaAEquipe(): void
    {
        $de = $this->telefoneDoCliente();
        $corpo = $this->payloadMensagem($de, 'Meu carro já está pronto?', 'wamid.ABC123');

        $r = $this->comunicacao()->receberWebhookWhatsApp($corpo, $this->assinar($corpo));

        $this->assertSame(200, $r['http']);
        $this->assertSame(1, $r['mensagens']);
        $conversa = $this->pdo->query("SELECT * FROM conversas WHERE cliente_id = {$this->clienteId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($conversa, 'o telefone foi reconhecido como o do cliente cadastrado');
        $this->assertSame(1, (int) $conversa['nao_lidas']);

        $m = $this->pdo->query("SELECT * FROM mensagens WHERE provider_message_id = 'wamid.ABC123'")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(['ENTRADA', 'RECEBIDA', 'Meu carro já está pronto?'], [$m['direcao'], $m['status'], $m['corpo']]);
        $this->assertGreaterThan(0, $this->contar("SELECT COUNT(*) FROM notificacoes WHERE tipo = 'CONVERSA' AND usuario_id = {$this->adminId}"), 'a equipe é avisada');
    }

    public function testMesmaMensagemReenviadaPelaMetaNaoDuplica(): void
    {
        $de = $this->telefoneDoCliente();
        $corpo = $this->payloadMensagem($de, 'Oi', 'wamid.DUP1');
        $com = $this->comunicacao();

        $a = $com->receberWebhookWhatsApp($corpo, $this->assinar($corpo));
        $b = $com->receberWebhookWhatsApp($corpo, $this->assinar($corpo));

        $this->assertSame([1, 0], [$a['mensagens'], $b['mensagens']]);
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM mensagens WHERE provider_message_id = 'wamid.DUP1'"));
        $this->assertSame(1, (int) $this->pdo->query("SELECT nao_lidas FROM conversas WHERE cliente_id = {$this->clienteId}")->fetchColumn());
    }

    public function testNumeroDesconhecidoViraConversaNaoIdentificada(): void
    {
        $corpo = $this->payloadMensagem('5599900000001', 'Quanto custa um alinhamento?', 'wamid.DESCONHECIDO1');

        $this->assertSame(200, $this->comunicacao()->receberWebhookWhatsApp($corpo, $this->assinar($corpo))['http']);

        $c = $this->pdo->query("SELECT c.cliente_id, c.identificador FROM conversas c JOIN mensagens m ON m.conversa_id = c.id_conversa WHERE m.provider_message_id = 'wamid.DESCONHECIDO1'")->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($c['cliente_id'], 'não se inventa um cliente');
        $this->assertSame('5599900000001', $c['identificador']);
        $this->pdo->exec("DELETE FROM conversas WHERE identificador = '5599900000001'");
    }

    public function testRecibosDeEntregaAtualizamSemRegredir(): void
    {
        $de = $this->telefoneDoCliente();
        $com = $this->comunicacao();
        $conversa = Conversa::abertaOuNova($this->pdo, $this->clienteId, 'WHATSAPP', $de, 'Cliente', 'teste');
        $this->pdo->prepare("INSERT INTO mensagens (conversa_id, direcao, tipo, canal, destino, corpo, status, provider_message_id) VALUES (?, 'SAIDA', 'TEXTO', 'WHATSAPP', ?, 'oi', 'ENVIADA', 'wamid.SAIDA1')")->execute([$conversa, $de]);

        $recibo = fn(string $status) => json_encode(['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.SAIDA1', 'status' => $status, 'timestamp' => (string) time()]]]]]]]]);
        $enviar = function (string $status) use ($com, $recibo) {
            $corpo = $recibo($status);

            return $com->receberWebhookWhatsApp($corpo, $this->assinar($corpo));
        };
        $status = fn() => $this->pdo->query("SELECT status FROM mensagens WHERE provider_message_id = 'wamid.SAIDA1'")->fetchColumn();

        $this->assertSame(1, $enviar('delivered')['recibos']);
        $this->assertSame('ENTREGUE', $status());
        $this->assertSame(1, $enviar('read')['recibos']);
        $this->assertSame('LIDA', $status());

        $this->assertSame(0, $enviar('delivered')['recibos'], 'recibo atrasado não rebaixa LIDA');
        $this->assertSame('LIDA', $status());
        $this->assertSame(0, $enviar('failed')['recibos'], 'falha depois de entregue é ignorada');
        $this->assertSame('LIDA', $status());
    }

    public function testPedidoParaPararViraDescadastroRegistrado(): void
    {
        $de = $this->telefoneDoCliente();
        Consentimento::registrar($this->pdo, $this->clienteId, 'WHATSAPP', true, 'BALCAO', $this->adminId);
        $this->assertTrue(Consentimento::permite($this->pdo, $this->clienteId, 'WHATSAPP'));

        $corpo = $this->payloadMensagem($de, ' Parar. ', 'wamid.PARAR1');
        $this->comunicacao()->receberWebhookWhatsApp($corpo, $this->assinar($corpo));

        $this->assertFalse(Consentimento::permite($this->pdo, $this->clienteId, 'WHATSAPP'));
        $h = Consentimento::historico($this->pdo, $this->clienteId);
        $this->assertSame('WHATSAPP', $h[0]['origem']);

        // Uma frase que só CONTÉM a palavra não é descadastro.
        Consentimento::registrar($this->pdo, $this->clienteId, 'WHATSAPP', true, 'BALCAO', $this->adminId);
        $corpo = $this->payloadMensagem($de, 'Posso parar o carro aí na frente?', 'wamid.PARAR2');
        $this->comunicacao()->receberWebhookWhatsApp($corpo, $this->assinar($corpo));
        $this->assertTrue(Consentimento::permite($this->pdo, $this->clienteId, 'WHATSAPP'));
    }

    public function testCorpoInvalidoOuGrandeDemais(): void
    {
        $com = $this->comunicacao();
        $lixo = 'isto não é json';
        $this->assertSame(400, $com->receberWebhookWhatsApp($lixo, $this->assinar($lixo))['http'], 'assinado, mas não é JSON');

        $grande = str_repeat('a', 1048577);
        $this->assertSame(413, $com->receberWebhookWhatsApp($grande, $this->assinar($grande))['http']);
    }

    public function testSemSegredoConfiguradoTudoERecusado(): void
    {
        $cfg = $this->cfgWa();
        $cfg['app_secret'] = '';
        $com = new Comunicacao($this->pdo, new WhatsAppCloud($cfg, new FakeTransporte()), fn() => null, false);
        $corpo = $this->payloadMensagem($this->telefoneDoCliente(), 'Oi', 'wamid.SEMSEGREDO');

        // Com segredo vazio, "assinar com vazio" não pode ser um jeito de entrar.
        $this->assertSame(401, $com->receberWebhookWhatsApp($corpo, $this->assinar($corpo, ''))['http']);
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM mensagens WHERE provider_message_id = 'wamid.SEMSEGREDO'"));
    }

    /* ── Pagamento ── */

    private function cfgPg(): array
    {
        return ['provedor' => 'simulado', 'mp_access_token' => '', 'mp_webhook_secret' => '', 'max_parcelas' => 1, 'simulado_segredo' => self::SEGREDO_PG, 'permitir_simulado' => true];
    }

    private function cobrancaPendente(): array
    {
        $os = $this->novaOs(['itens' => [['tipo' => 'servico', 'id' => 'custom_1', 'nome' => 'Serviço', 'qty' => 1, 'preco' => 300, 'custom' => 1, 'categoria' => 'Geral']]]);
        $pag = new OrdemServicoPagamento($this->pdo);
        $pag->salvarPlano($os, ['pagamento_tipo' => 'AVISTA']);
        $this->ateRetirada($os);
        $parcela = (int) $pag->parcelas($os)[0]['id_parcela'];

        $prov = new ProvedorSimulado($this->cfgPg());
        $cob = new CobrancaOnline($this->pdo, $prov, $this->cfgPg());
        $r = $cob->criar($os, [$parcela], null, 'https://x.test/portal?t=1', 'https://x.test/webhookPagamento');
        $this->assertTrue($r['ok'], (string) $r['erro']);

        return [$os, $parcela, $cob->getById($r['cobranca_id']), $cob, $prov];
    }

    private function eventoPago(array $c, string $tipo = 'PAGO', ?string $id = null): string
    {
        return json_encode([
            'evento_id' => $id ?? 'WH-' . bin2hex(random_bytes(6)), 'tipo' => $tipo, 'referencia' => $c['referencia'], 'cobranca_id' => $c['provedor_cobranca_id'],
            'valor' => (float) $c['valor'], 'forma' => 'PIX', 'parcelas' => 1, 'taxa' => 0,
        ]);
    }

    public function testPagamentoSemAssinaturaOuComAssinaturaErradaNaoBaixaNada(): void
    {
        [$os, $parcela, $c, $cob, $prov] = $this->cobrancaPendente();
        $corpo = $this->eventoPago($c);

        foreach ([[], ['x-simulado-assinatura' => ''], ['x-simulado-assinatura' => 'sha256=' . str_repeat('a', 64)], ['x-simulado-assinatura' => $prov->assinarCorpo($corpo . ' ')]] as $cab) {
            $r = $cob->receberWebhook($prov, $corpo, $cab);
            $this->assertSame(401, $r['http']);
            $this->assertSame([], $r['resultados']);
        }

        $this->assertSame(0, (int) $this->pdo->query("SELECT pago FROM ordem_servico_parcelas WHERE id_parcela = {$parcela}")->fetchColumn());
        $this->assertSame('PENDENTE', $cob->getById((int) $c['id_cobranca'])['status']);
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM webhook_eventos WHERE provedor = 'SIMULADO' AND evento_id LIKE 'WH-%'"), 'evento rejeitado nem é registrado');
    }

    public function testPagamentoAssinadoBaixaUmaVezEOReenvioNaoRepete(): void
    {
        [$os, $parcela, $c, $cob, $prov] = $this->cobrancaPendente();
        $corpo = $this->eventoPago($c, 'PAGO', 'WH-FIXO-1');
        $cab = ['x-simulado-assinatura' => $prov->assinarCorpo($corpo)];

        $a = $cob->receberWebhook($prov, $corpo, $cab);
        $b = $cob->receberWebhook($prov, $corpo, $cab);

        $this->assertSame([200, ['paga']], [$a['http'], $a['resultados']]);
        $this->assertSame([200, ['duplicado']], [$b['http'], $b['resultados']]);
        $p = $this->pdo->query("SELECT pago, origem_recebimento, movimentacao_caixa_id FROM ordem_servico_parcelas WHERE id_parcela = {$parcela}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame([1, 'ONLINE', null], [(int) $p['pago'], $p['origem_recebimento'], $p['movimentacao_caixa_id']]);
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM webhook_eventos WHERE provedor = 'SIMULADO' AND evento_id = 'WH-FIXO-1'"));
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM mensagens m JOIN conversas cv ON cv.id_conversa = m.conversa_id WHERE cv.cliente_id = {$this->clienteId} AND m.evento = 'PAGAMENTO_CONFIRMADO'"), 'um aviso só');
    }

    public function testEstornoPeloProvedorDevolveAParcelaAoDevedor(): void
    {
        [$os, $parcela, $c, $cob, $prov] = $this->cobrancaPendente();
        $pago = $this->eventoPago($c);
        $cob->receberWebhook($prov, $pago, ['x-simulado-assinatura' => $prov->assinarCorpo($pago)]);

        $est = $this->eventoPago($c, 'ESTORNADO');
        $r = $cob->receberWebhook($prov, $est, ['x-simulado-assinatura' => $prov->assinarCorpo($est)]);

        $this->assertSame(['estornada'], $r['resultados']);
        $this->assertSame(0, (int) $this->pdo->query("SELECT pago FROM ordem_servico_parcelas WHERE id_parcela = {$parcela}")->fetchColumn());
    }

    public function testProvedorNaoConfiguradoRecusaSempre(): void
    {
        [$os, $parcela, $c, $cob, $prov] = $this->cobrancaPendente();
        $semSegredo = new ProvedorSimulado(['provedor' => 'simulado', 'mp_access_token' => '', 'mp_webhook_secret' => '', 'max_parcelas' => 1, 'simulado_segredo' => '', 'permitir_simulado' => true]);
        $corpo = $this->eventoPago($c);

        $r = $cob->receberWebhook($semSegredo, $corpo, ['x-simulado-assinatura' => $semSegredo->assinarCorpo($corpo)]);

        $this->assertSame(401, $r['http'], 'sem segredo configurado a assinatura "vazia" não vale');
        $this->assertSame(0, (int) $this->pdo->query("SELECT pago FROM ordem_servico_parcelas WHERE id_parcela = {$parcela}")->fetchColumn());
    }

    public function testCorpoGrandeDemaisEhRecusadoAntesDeQualquerLeitura(): void
    {
        [$os, $parcela, $c, $cob, $prov] = $this->cobrancaPendente();
        $grande = str_repeat('x', 262145);

        $this->assertSame(413, $cob->receberWebhook($prov, $grande, ['x-simulado-assinatura' => $prov->assinarCorpo($grande)])['http']);
    }
}
