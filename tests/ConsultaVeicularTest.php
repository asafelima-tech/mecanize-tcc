<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/FakeTransporte.php';
require_once __DIR__ . '/../app/core/ConsultaVeicular.php';

/**
 * Consulta de dados do veículo pela placa: sem rede e SEM banco.
 *
 * Nenhum provedor é presumido — tudo vem da configuração. O que se prova: só passa o que foi configurado
 * e o serviço devolveu, dado de proprietário nunca atravessa, toda falha vira mensagem compreensível com
 * o cadastro manual como saída, e a ligação com marcas/modelos/cores do sistema só acontece quando é inequívoca.
 */
final class ConsultaVeicularTest extends TestCase
{
    private function cfg(array $extra = []): array
    {
        return $extra + [
            'url' => 'https://api.exemplo.test/v1/placa/{placa}', 'auth_tipo' => 'bearer', 'auth_nome' => '', 'token' => 'tok-secreto', 'sucesso' => '',
            'campos' => ['marca' => 'dados.marca', 'modelo' => 'dados.modelo', 'ano' => 'dados.ano', 'ano_modelo' => 'dados.anoModelo', 'cor' => 'dados.cor', 'combustivel' => 'dados.combustivel', 'versao' => 'dados.versao'],
        ];
    }

    private function resposta(array $dados = []): array
    {
        return ['dados' => $dados + ['marca' => 'VOLKSWAGEN', 'modelo' => 'GOL 1.0 MI', 'ano' => '2018', 'anoModelo' => '2019', 'cor' => 'PRATA', 'combustivel' => 'ALCOOL / GASOLINA'],
                'proprietario' => ['nome' => 'JOAO DA SILVA', 'cpf' => '123.456.789-09', 'endereco' => 'RUA X, 1']];
    }

    /* ── configuração ── */

    public function testSemConfiguracaoNaoChamaOServicoEDizOQueFalta(): void
    {
        $http = new FakeTransporte();
        $c = new ConsultaVeicular(['url' => '', 'auth_tipo' => 'bearer', 'auth_nome' => '', 'token' => '', 'sucesso' => '', 'campos' => []], $http);

        $r = $c->consultar('ABC1D23');

        $this->assertFalse($c->configurada());
        $this->assertSame('NAO_CONFIGURADO', $r['codigo']);
        $this->assertStringContainsString('manualmente', (string) $r['erro']);
        $this->assertSame([], $http->chamadas);
        $this->assertContains('CONSULTA_PLACA_TOKEN', $c->faltando());
        $this->assertNotEmpty(array_filter($c->faltando(), fn($f) => str_starts_with($f, 'CONSULTA_PLACA_URL')));
    }

    public function testUrlPrecisaSerHttpsComPlacaEExemploDoEnvNaoValeComoCredencial(): void
    {
        foreach (['http://api.exemplo.test/{placa}', 'https://api.exemplo.test/fixa', 'seu-endereco/{placa}'] as $url) {
            $this->assertFalse((new ConsultaVeicular($this->cfg(['url' => $url])))->configurada(), $url);
        }

        $this->assertFalse((new ConsultaVeicular($this->cfg(['token' => 'seu-token-aqui'])))->configurada());
        $this->assertTrue((new ConsultaVeicular($this->cfg(['auth_tipo' => 'nenhum', 'token' => ''])))->configurada(), 'serviço sem autenticação');
    }

    public function testHttpSoValeParaAPropriaMaquinaParaPoderTestarContraUmServicoLocal(): void
    {
        foreach (['http://127.0.0.1:8098/placa/{placa}', 'http://localhost/placa/{placa}'] as $url) {
            $this->assertTrue((new ConsultaVeicular($this->cfg(['url' => $url])))->configurada(), $url);
        }

        // um endereço que só COMEÇA parecido com a própria máquina não é a própria máquina
        foreach (['http://127.0.0.1.evil.test/{placa}', 'http://localhost.evil.test/{placa}', 'http://10.0.0.5/placa/{placa}'] as $url) {
            $this->assertFalse((new ConsultaVeicular($this->cfg(['url' => $url])))->configurada(), $url);
        }

        // o próprio transporte recusa antes de abrir qualquer conexão (nada sai pela rede neste teste)
        $r = (new CurlTransporte())->requisitar('GET', 'http://127.0.0.1.evil.test/x');
        $this->assertSame(0, $r['status']);
        $this->assertStringContainsString('HTTPS', (string) $r['erro']);
    }

    /* ── chamada ── */

    public function testPlacaInvalidaNemChegaAoServico(): void
    {
        $http = new FakeTransporte();

        foreach (['', 'AB12', 'ABC12D3', 'ZZZ'] as $p) {
            $this->assertSame('PLACA_INVALIDA', (new ConsultaVeicular($this->cfg(), $http))->consultar($p)['codigo'], "'{$p}'");
        }

        $this->assertSame([], $http->chamadas);
    }

    public function testAutenticacaoPorBearerCabecalhoOuParametro(): void
    {
        $casos = [
            [['auth_tipo' => 'bearer'], 'Authorization: Bearer tok-secreto', 'https://api.exemplo.test/v1/placa/ABC1D23'],
            [['auth_tipo' => 'cabecalho', 'auth_nome' => 'X-Chave'], 'X-Chave: tok-secreto', 'https://api.exemplo.test/v1/placa/ABC1D23'],
            [['auth_tipo' => 'consulta', 'auth_nome' => 'key'], null, 'https://api.exemplo.test/v1/placa/ABC1D23?key=tok-secreto'],
        ];

        foreach ($casos as [$extra, $cabecalho, $url]) {
            $http = (new FakeTransporte())->enfileirar(200, $this->resposta());
            (new ConsultaVeicular($this->cfg($extra), $http))->consultar('abc-1d23');

            $this->assertSame($url, $http->chamadas[0]['url']);
            $this->assertSame('GET', $http->chamadas[0]['metodo']);

            if ($cabecalho) {
                $this->assertContains($cabecalho, $http->chamadas[0]['cabecalhos']);
            } else {
                $this->assertNotContains('Authorization: Bearer tok-secreto', $http->chamadas[0]['cabecalhos']);
            }
        }
    }

    public function testSoOsCamposConfiguradosAtravessamEDadoDeProprietarioNuncaSai(): void
    {
        $http = (new FakeTransporte())->enfileirar(200, $this->resposta());

        $r = (new ConsultaVeicular($this->cfg(), $http))->consultar('ABC1D23');

        $this->assertTrue($r['ok']);
        $this->assertSame('VOLKSWAGEN', $r['dados']['marca']);
        $this->assertSame('GOL 1.0 MI', $r['dados']['modelo']);
        $this->assertSame('2018', $r['dados']['ano']);
        $this->assertSame('2019', $r['dados']['ano_modelo']);
        $this->assertNull($r['dados']['versao'], 'o que o serviço não mandou fica vazio — nada é inventado');
        $this->assertContains('versao', $r['ausentes']);

        $json = json_encode($r);
        foreach (['JOAO DA SILVA', '123.456.789-09', 'RUA X', 'proprietario', 'cpf'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $json, "'{$proibido}' não pode atravessar");
        }
        $this->assertStringNotContainsString('tok-secreto', $json);
    }

    public function testCampoCombinadoMarcaModeloEFlagDeSucesso(): void
    {
        $cfg = $this->cfg(['sucesso' => 'ok', 'campos' => ['marca_modelo' => 'veiculo.marca_modelo', 'ano' => 'veiculo.ano']]);

        $ok = (new ConsultaVeicular($cfg, (new FakeTransporte())->enfileirar(200, ['ok' => true, 'veiculo' => ['marca_modelo' => 'VW/GOL 1.0', 'ano' => 2017]])))->consultar('ABC1D23');
        $this->assertTrue($ok['ok']);
        $this->assertSame('VW/GOL 1.0', $ok['dados']['marca_modelo']);
        $this->assertSame('2017', $ok['dados']['ano'], 'número vira texto');

        $nao = (new ConsultaVeicular($cfg, (new FakeTransporte())->enfileirar(200, ['ok' => false, 'veiculo' => ['marca_modelo' => 'LIXO']])))->consultar('ABC1D23');
        $this->assertFalse($nao['ok']);
        $this->assertSame('NAO_ENCONTRADA', $nao['codigo']);
    }

    public function testFalhasViramMensagemCompreensivelSemVazarDetalhe(): void
    {
        $casos = [
            [404, '{"erro":"x"}', null, 'NAO_ENCONTRADA'],
            [429, 'plano zzz estourado', null, 'LIMITE'],
            [401, 'token zzz invalido', null, 'NAO_CONFIGURADO'],
            [500, 'stack interna db1.internal', null, 'INDISPONIVEL'],
            [200, 'isto não é json', null, 'INDISPONIVEL'],
            [200, '{"dados":{}}', null, 'NAO_ENCONTRADA'],
            [0, '', 'Operation timed out after 12001 milliseconds', 'TEMPO_ESGOTADO'],
            [0, '', 'Could not resolve host', 'INDISPONIVEL'],
        ];

        foreach ($casos as [$status, $corpo, $erroRede, $esperado]) {
            $r = (new ConsultaVeicular($this->cfg(), (new FakeTransporte())->enfileirar($status, $corpo, $erroRede)))->consultar('ABC1D23');

            $this->assertFalse($r['ok'], "HTTP {$status} {$corpo}");
            $this->assertSame($esperado, $r['codigo'], "HTTP {$status} {$corpo}");
            $this->assertStringContainsString('manualmente', (string) $r['erro'] . ' manualmente', 'sempre há saída');
            $this->assertStringNotContainsString('zzz', (string) $r['erro']);
            $this->assertStringNotContainsString('internal', (string) $r['erro']);
        }
    }

    /* ── leitura da resposta e ligação com o cadastro ── */

    public function testExtrairCaminhoComPontosEIndices(): void
    {
        $j = ['a' => ['b' => ['c' => 'ok']], 'lista' => [['cor' => 'AZUL'], ['cor' => 'VERDE']], 'zero' => 0, 'nulo' => null];

        $this->assertSame('ok', ConsultaVeicular::extrair($j, 'a.b.c'));
        $this->assertSame('VERDE', ConsultaVeicular::extrair($j, 'lista.1.cor'));
        $this->assertSame(0, ConsultaVeicular::extrair($j, 'zero'));
        $this->assertNull(ConsultaVeicular::extrair($j, 'a.x.c'));
        $this->assertNull(ConsultaVeicular::extrair($j, 'nulo.x'));
    }

    public function testMarcaSoCasaQuandoEInequivoca(): void
    {
        $marcas = [1 => 'Volkswagen', 2 => 'Fiat', 3 => 'Chevrolet', 4 => 'Land Rover', 5 => 'Mercedes-Benz'];

        $this->assertSame(1, ConsultaVeicular::escolherMarca($marcas, 'VOLKSWAGEN'));
        $this->assertSame(1, ConsultaVeicular::escolherMarca($marcas, 'vw'), 'abreviação conhecida');
        $this->assertSame(1, ConsultaVeicular::escolherMarca($marcas, 'VOLKSWAGEN/GOL'));
        $this->assertSame(3, ConsultaVeicular::escolherMarca($marcas, 'GM'));
        $this->assertSame(5, ConsultaVeicular::escolherMarca($marcas, 'MERCEDES BENZ'));
        $this->assertNull(ConsultaVeicular::escolherMarca($marcas, 'MARCAINEXISTENTE'));
        $this->assertNull(ConsultaVeicular::escolherMarca($marcas, ''));

        $this->assertSame([1, 'GOL 1.0 MI'], ConsultaVeicular::separarMarcaModelo($marcas, 'VW/GOL 1.0 MI'));
        $this->assertSame([2, 'UNO MILLE'], ConsultaVeicular::separarMarcaModelo($marcas, 'FIAT UNO MILLE'));
        $this->assertSame([4, 'DISCOVERY SPORT'], ConsultaVeicular::separarMarcaModelo($marcas, 'LAND ROVER DISCOVERY SPORT'));
        $this->assertSame([null, 'XPTO MODELO'], ConsultaVeicular::separarMarcaModelo($marcas, 'XPTO MODELO'), 'sem marca conhecida o texto inteiro é devolvido');
    }

    public function testModeloEscolheOMaisEspecificoPeloPrefixo(): void
    {
        $modelos = [10 => 'Uno', 11 => 'Uno Mille', 12 => 'Fiorino', 13 => 'Gol', 14 => 'Gol Rallye'];

        $this->assertSame(13, ConsultaVeicular::escolherModelo($modelos, 'GOL 1.0 MI'));
        $this->assertSame(14, ConsultaVeicular::escolherModelo($modelos, 'GOL RALLYE 1.6'));
        $this->assertSame(11, ConsultaVeicular::escolherModelo($modelos, 'UNO MILLE ECONOMY'));
        $this->assertSame(10, ConsultaVeicular::escolherModelo($modelos, 'UNO'));
        $this->assertSame(12, ConsultaVeicular::escolherModelo($modelos, 'FIORINO UNO WAY'));
        $this->assertNull(ConsultaVeicular::escolherModelo($modelos, 'PALIO'), 'modelo que não existe no cadastro não é chutado');
    }

    public function testCombustivelECorSoSaemSeExistiremNoSistema(): void
    {
        // As listas REAIS do cadastro (app/views/Veiculos/_combustiveis.php e _coresCarro.php).
        $lista = require __DIR__ . '/../app/views/Veiculos/_combustiveis.php';
        $cores = array_keys(require __DIR__ . '/../app/views/Veiculos/_coresCarro.php');

        $this->assertSame('Flex (Álcool/Gasolina)', ConsultaVeicular::combustivel('ALCOOL / GASOLINA', $lista));
        $this->assertSame('Flex (Álcool/Gasolina)', ConsultaVeicular::combustivel('Gasolina/Etanol', $lista));
        $this->assertSame('Flex (Álcool/Gasolina)', ConsultaVeicular::combustivel('FLEX', $lista));
        $this->assertSame('Gasolina', ConsultaVeicular::combustivel('GASOLINA', $lista));
        $this->assertSame('Etanol', ConsultaVeicular::combustivel('ALCOOL', $lista));
        $this->assertSame('Etanol', ConsultaVeicular::combustivel('ETANOL', $lista));
        $this->assertSame('Elétrico', ConsultaVeicular::combustivel('ELETRICO', $lista));
        $this->assertSame('Diesel', ConsultaVeicular::combustivel('DIESEL S10', $lista));
        $this->assertSame('GNV', ConsultaVeicular::combustivel('gás natural', $lista));
        $this->assertNull(ConsultaVeicular::combustivel('HIDROGENIO', $lista), 'o que o sistema não tem não é chutado');
        $this->assertNull(ConsultaVeicular::combustivel('', $lista));

        $this->assertSame('Prata', ConsultaVeicular::cor('PRATA', $cores));
        $this->assertSame('Branco Pérola', ConsultaVeicular::cor('branco perola', $cores));
        $this->assertNull(ConsultaVeicular::cor('CHUMBO METALICO', $cores));
    }

    public function testLimitePorSessao(): void
    {
        $h = [];

        for ($i = 0; $i < ConsultaVeicular::MAX_POR_JANELA; $i++) {
            $this->assertTrue(ConsultaVeicular::dentroDoLimite($h, 1000 + $i));
        }

        $this->assertFalse(ConsultaVeicular::dentroDoLimite($h, 1100));
    }
}
