<?php

use PHPUnit\Framework\TestCase;

/**
 * Cobre o recorte por aba da tela de Usuários (Ativos / Inativos).
 *
 * O ponto sensível é que a aba deixou de ser um filtro opcional e passou a
 * ser o que define o `status` da consulta: se `abaAtual()` deixar passar um
 * valor inesperado, ou `filtrosDaTela()` devolver status nulo, a aba
 * "Inativos" volta a mostrar usuário ativo (e vice-versa) — que é justamente
 * o que a separação existe pra impedir.
 *
 * Testa os dois métodos privados por reflection de propósito: é a regra de
 * decisão em si, não o caminho HTTP, que precisa ficar travada.
 */
final class UsuariosAbasTest extends TestCase
{
    private UserController $controller;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../app/core/Controller.php';
        require_once __DIR__ . '/../app/controllers/UserController.php';

        $this->controller = new UserController();
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }

    private function chamar(string $metodo)
    {
        $ref = new ReflectionMethod($this->controller, $metodo);
        $ref->setAccessible(true);

        return $ref->invoke($this->controller);
    }

    public function testSemParametroAAbaPadraoEAtivos(): void
    {
        $this->assertSame('ativos', $this->chamar('abaAtual'));
        $this->assertSame(1, $this->chamar('filtrosDaTela')['status']);
    }

    public function testAbaInativosSelecionaStatusZero(): void
    {
        $_GET['aba'] = 'inativos';

        $this->assertSame('inativos', $this->chamar('abaAtual'));
        $this->assertSame(0, $this->chamar('filtrosDaTela')['status']);
    }

    /**
     * @dataProvider valoresInesperados
     */
    public function testValorInesperadoNaAbaCaiEmAtivos($valor): void
    {
        $_GET['aba'] = $valor;

        $this->assertSame('ativos', $this->chamar('abaAtual'));
        $this->assertSame(
            1,
            $this->chamar('filtrosDaTela')['status'],
            'aba desconhecida nunca pode virar "sem filtro de status" — misturaria ativos e inativos'
        );
    }

    public function valoresInesperados(): array
    {
        return [
            'string arbitrária' => ['qualquer-coisa'],
            'vazio'             => [''],
            'numérico'          => ['1'],
            'caixa diferente'   => ['INATIVOS'],
            'sql'               => ["' OR 1=1 --"],
            'array'             => [['inativos']],
        ];
    }

    public function testStatusNuncaEnulo(): void
    {
        // Um status nulo faria buildFiltro() omitir o WHERE de `ativo` e a
        // aba mostraria os dois grupos juntos.
        foreach (['ativos', 'inativos', 'lixo', ''] as $aba) {
            $_GET['aba'] = $aba;
            $filtros = $this->chamar('filtrosDaTela');

            $this->assertNotNull($filtros['status'], "aba '{$aba}' deixou o status nulo");
            $this->assertContains($filtros['status'], [0, 1]);
        }
    }

    public function testBuscaETipoContinuamSendoLidosEAparados(): void
    {
        $_GET['aba']   = 'inativos';
        $_GET['busca'] = '  maria  ';
        $_GET['tipo']  = ' ADMIN ';

        $filtros = $this->chamar('filtrosDaTela');

        $this->assertSame('maria', $filtros['busca']);
        $this->assertSame('ADMIN', $filtros['tipo']);
        $this->assertSame(0, $filtros['status'], 'busca não pode atropelar o recorte da aba');
    }
}
