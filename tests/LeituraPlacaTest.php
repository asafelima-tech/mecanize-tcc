<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/FakeTransporte.php';
require_once __DIR__ . '/../app/core/LeituraPlaca.php';

/**
 * Leitura de placa pela foto: sem rede (transporte falso) e SEM banco.
 *
 * O que se prova: a leitura é sempre uma sugestão para conferência (com confiança, formato e alternativas),
 * toda falha vira um código com mensagem compreensível, o detalhe do serviço não vaza e uma placa de
 * formato impossível nunca passa por "confiável".
 */
final class LeituraPlacaTest extends TestCase
{
    /** @var string[] */
    private array $temporarios = [];

    protected function tearDown(): void
    {
        foreach ($this->temporarios as $f) {
            @unlink($f);
        }
    }

    /** Foto mínima VÁLIDA (PNG 1×1) gravada em arquivo temporário. */
    private function foto(?string $conteudo = null): string
    {
        $f = tempnam(sys_get_temp_dir(), 'placa');
        file_put_contents($f, $conteudo ?? base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        $this->temporarios[] = $f;

        return $f;
    }

    private function leitor(FakeTransporte $http, ?string $token = 'token-de-teste'): LeituraPlaca
    {
        return new LeituraPlaca($http, $token);
    }

    private function resposta(array $resultado): array
    {
        return ['results' => [$resultado]];
    }

    /* ── texto e formato ── */

    public function testNormalizaEReconheceOsDoisFormatosBrasileiros(): void
    {
        $this->assertSame('ABC1D23', LeituraPlaca::normalizar(' abc-1d23 '));
        $this->assertSame('MERCOSUL', LeituraPlaca::formato('ABC1D23'));
        $this->assertSame('ANTIGO', LeituraPlaca::formato('ABC1234'));

        foreach (['AB12', 'ABC12D3', 'ABCD123', '1BC1D23', 'ABC1D2', 'ABC1D234', ''] as $invalida) {
            $this->assertNull(LeituraPlaca::formato($invalida), "'{$invalida}' não é placa brasileira");
        }
    }

    public function testLimitePorSessaoNaJanela(): void
    {
        $h = [];
        $agora = 1_000_000;

        for ($i = 0; $i < LeituraPlaca::MAX_POR_JANELA; $i++) {
            $this->assertTrue(LeituraPlaca::dentroDoLimite($h, $agora + $i));
        }

        $this->assertFalse(LeituraPlaca::dentroDoLimite($h, $agora + 20), 'estourou o teto');
        $this->assertCount(LeituraPlaca::MAX_POR_JANELA, $h, 'a tentativa recusada não entra no histórico');
        $this->assertTrue(LeituraPlaca::dentroDoLimite($h, $agora + LeituraPlaca::JANELA_SEGUNDOS + 1), 'a janela andou: libera');
    }

    /* ── configuração ── */

    public function testSemCredencialNemTentaChamarOServico(): void
    {
        foreach ([null, '', '   ', 'seu-token-do-platerecognizer.com'] as $token) {
            $http = new FakeTransporte();
            $r = $this->leitor($http, $token)->ler($this->foto());

            $this->assertFalse($r['ok']);
            $this->assertSame('NAO_CONFIGURADO', $r['codigo']);
            $this->assertStringContainsString('Digite a placa', (string) $r['erro']);
            $this->assertSame([], $http->chamadas, 'sem credencial não há chamada');
        }
    }

    /* ── resultado ── */

    public function testLeituraBoaVemComConfiancaFormatoEAlternativasMasPedeConferencia(): void
    {
        $http = (new FakeTransporte())->enfileirar(201, $this->resposta([
            'plate' => 'abc1d23', 'score' => 0.972,
            'candidates' => [['plate' => 'abc1d23', 'score' => 0.972], ['plate' => 'abc1023', 'score' => 0.55], ['plate' => 'abc1d23', 'score' => 0.3], ['plate' => 'xx', 'score' => 0.2]],
        ]));

        $r = $this->leitor($http)->ler($this->foto());

        $this->assertTrue($r['ok']);
        $this->assertSame('ABC1D23', $r['placa']);
        $this->assertSame('MERCOSUL', $r['formato']);
        $this->assertSame(0.972, $r['confianca']);
        $this->assertFalse($r['baixa_confianca']);
        $this->assertTrue($r['requer_conferencia'], 'mesmo a leitura boa é conferida pela pessoa');
        $this->assertSame([['placa' => 'ABC1023', 'confianca' => 0.55, 'formato' => 'ANTIGO']], $r['candidatos'], 'só alternativas DIFERENTES e com formato de placa');
        $this->assertNull($r['erro']);
    }

    public function testEnvioUsaCredencialRegiaoBrasilEOArquivoComTipoReal(): void
    {
        $http = (new FakeTransporte())->enfileirar(201, $this->resposta(['plate' => 'ABC1D23', 'score' => 0.9]));

        $this->leitor($http)->ler($this->foto());

        $c = $http->chamadas[0];
        $this->assertSame('POST', $c['metodo']);
        $this->assertSame(LeituraPlaca::URL, $c['url']);
        $this->assertContains('Authorization: Token token-de-teste', $c['cabecalhos']);
        $this->assertStringContainsString('name="regions"', (string) $c['corpo']);
        $this->assertStringContainsString("\r\n\r\nbr\r\n", (string) $c['corpo']);
        $this->assertStringContainsString('name="upload"; filename="placa.png"', (string) $c['corpo']);
        $this->assertStringContainsString('Content-Type: image/png', (string) $c['corpo']);
        $this->assertMatchesRegularExpression('/^Content-Type: multipart\/form-data; boundary=----mecanize[0-9a-f]+$/m', implode("\n", $c['cabecalhos']));
    }

    public function testLeituraIncertaEhMarcadaEPlacaDeFormatoImpossivelNuncaEhConfiavel(): void
    {
        $r = $this->leitor((new FakeTransporte())->enfileirar(201, $this->resposta(['plate' => 'ABC1D23', 'score' => 0.62])))->ler($this->foto());
        $this->assertTrue($r['ok']);
        $this->assertTrue($r['baixa_confianca'], 'nota 0,62 é incerta');

        $r = $this->leitor((new FakeTransporte())->enfileirar(201, $this->resposta(['plate' => 'ABC1D23'])))->ler($this->foto());
        $this->assertNull($r['confianca']);
        $this->assertTrue($r['baixa_confianca'], 'sem nota do serviço não se afirma confiança');

        // 99% de confiança num texto que não é placa: continua não confiável e a mensagem diz por quê.
        $r = $this->leitor((new FakeTransporte())->enfileirar(201, $this->resposta([
            'plate' => 'ABC12D3', 'score' => 0.99, 'candidates' => [['plate' => 'abc1203', 'score' => 0.5]],
        ])))->ler($this->foto());
        $this->assertTrue($r['ok']);
        $this->assertNull($r['formato']);
        $this->assertTrue($r['baixa_confianca']);
        $this->assertStringContainsString('não tem formato de placa brasileira', (string) $r['erro']);
        $this->assertSame('ABC1203', $r['candidatos'][0]['placa'], 'a alternativa válida é oferecida');
    }

    public function testFotoSemPlaca(): void
    {
        $r = $this->leitor((new FakeTransporte())->enfileirar(200, ['results' => []]))->ler($this->foto());

        $this->assertFalse($r['ok']);
        $this->assertSame('SEM_PLACA', $r['codigo']);
        $this->assertNull($r['placa']);

        $r = $this->leitor((new FakeTransporte())->enfileirar(200, 'isto não é json'))->ler($this->foto());
        $this->assertSame('SEM_PLACA', $r['codigo'], 'resposta ilegível não vira placa');
    }

    /* ── falhas do serviço ── */

    public function testFalhasDoServicoViramCodigosComMensagemCompreensivelSemVazarDetalhe(): void
    {
        $casos = [
            [429, 'quota exceeded for token zzz', null, 'LIMITE'],
            [401, 'Invalid token zzz', null, 'NAO_CONFIGURADO'],
            [403, 'forbidden', null, 'NAO_CONFIGURADO'],
            [500, 'Traceback secret internal host db1.internal', null, 'INDISPONIVEL'],
            [0, '', 'Operation timed out after 15001 milliseconds', 'TEMPO_ESGOTADO'],
            [0, '', 'Could not resolve host: api.platerecognizer.com', 'INDISPONIVEL'],
        ];

        foreach ($casos as [$status, $corpo, $erroRede, $esperado]) {
            $r = $this->leitor((new FakeTransporte())->enfileirar($status, $corpo, $erroRede))->ler($this->foto());

            $this->assertFalse($r['ok'], "HTTP {$status}");
            $this->assertSame($esperado, $r['codigo'], "HTTP {$status}");
            $this->assertNotEmpty($r['erro']);
            $this->assertStringNotContainsString('zzz', (string) $r['erro']);
            $this->assertStringNotContainsString('internal', (string) $r['erro']);
            $this->assertStringNotContainsString('platerecognizer', (string) $r['erro'], 'nome de host não vai para a tela');
        }
    }

    /* ── o arquivo ── */

    public function testArquivoQueNaoEFotoNemChegaAoServico(): void
    {
        $http = new FakeTransporte();
        $leitor = $this->leitor($http);

        $r = $leitor->ler($this->foto("<?php echo 'invadido'; ?>"));
        $this->assertSame('IMAGEM_INVALIDA', $r['codigo'], 'um script renomeado para .jpg não é foto');

        $r = $leitor->ler($this->foto(''));
        $this->assertSame('IMAGEM_INVALIDA', $r['codigo'], 'arquivo vazio');

        $r = $leitor->ler('/caminho/que/nao/existe.jpg');
        $this->assertSame('IMAGEM_INVALIDA', $r['codigo']);

        $gif = base64_decode('R0lGODlhAQABAAAAACwAAAAAAQABAAA=');
        $r = $leitor->ler($this->foto($gif));
        $this->assertSame('IMAGEM_INVALIDA', $r['codigo'], 'formato fora da lista (GIF)');

        $this->assertSame([], $http->chamadas);
    }

    public function testFotoGrandeDemaisEhRecusadaAntesDeEnviar(): void
    {
        $http = new FakeTransporte();
        $grande = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==') . str_repeat('0', LeituraPlaca::LIMITE_BYTES);

        $r = $this->leitor($http)->ler($this->foto($grande));

        $this->assertSame('TAMANHO', $r['codigo']);
        $this->assertSame([], $http->chamadas);
    }
}
