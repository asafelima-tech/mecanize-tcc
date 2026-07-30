<?php

use PHPUnit\Framework\TestCase;

/**
 * Cobre os agregados por período do livro-caixa (Fase 3), que respondem ao
 * pedido registrado em ERROS.MD: caixa de entrada/saída diário, semanal,
 * mensal e anual, com as entradas quebradas por forma de pagamento e as
 * saídas por categoria.
 *
 * Roda contra mecanize_db_test (ver tests/bootstrap.php) — cria e remove sua
 * própria sessão de caixa e seus lançamentos, nunca toca dado de produção.
 */
final class RelatorioCaixaTest extends TestCase
{
    private PDO $pdo;
    private int $usuarioId;
    private int $caixaId;
    private MovimentacaoCaixa $mov;

    /** Datas fixas dentro de um mês isolado, pra não colidir com "hoje". */
    private const DIA_A = '2031-03-10';
    private const DIA_B = '2031-03-11';
    private const OUTRO_MES = '2031-04-05';

    protected function setUp(): void
    {
        $this->pdo = new Database();

        $this->pdo->exec("
            INSERT INTO usuarios (nome, email, senha, tipo)
            VALUES ('Caixa Teste PHPUnit', 'phpunit.caixa@teste.local', 'x', 'ADMIN')
        ");
        $this->usuarioId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("
            INSERT INTO caixa (usuario_abertura_id, valor_abertura, status)
            VALUES ({$this->usuarioId}, 100.00, 'ABERTO')
        ");
        $this->caixaId = (int) $this->pdo->lastInsertId();

        require_once __DIR__ . '/../app/models/MovimentacaoCaixa.php';
        $this->mov = new MovimentacaoCaixa();

        // Entradas: uma de cada forma, em dois dias distintos do mesmo mês.
        $this->lancar('ENTRADA', 'DINHEIRO', null, 200.00, self::DIA_A);
        $this->lancar('ENTRADA', 'PIX',      null, 150.00, self::DIA_A);
        $this->lancar('ENTRADA', 'CARTAO',   null,  80.00, self::DIA_B);
        $this->lancar('ENTRADA', 'BOLETO',   null,  70.00, self::DIA_B);

        // Saídas por categoria.
        $this->lancar('SAIDA', null, 'PECAS', 120.00, self::DIA_A);
        $this->lancar('SAIDA', null, 'BANCO',  50.00, self::DIA_B);

        // Fora do período: não pode entrar em nenhum total de março.
        $this->lancar('ENTRADA', 'DINHEIRO', null, 999.00, self::OUTRO_MES);
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("DELETE FROM movimentacoes_caixa WHERE caixa_id = {$this->caixaId}");
        $this->pdo->exec("DELETE FROM caixa WHERE id_caixa = {$this->caixaId}");
        $this->pdo->exec("DELETE FROM usuarios WHERE id_usuario = {$this->usuarioId}");
    }

    /** Insere direto no banco pra poder datar o lançamento no passado. */
    private function lancar(string $tipo, ?string $forma, ?string $cat, float $valor, string $dia): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO movimentacoes_caixa
                (caixa_id, tipo, forma_pagamento, categoria, valor, usuario_id, origem, data_movimentacao)
            VALUES (:caixa, :tipo, :forma, :cat, :valor, :usuario, 'MANUAL', :quando)
        ");
        $stmt->execute([
            ':caixa'   => $this->caixaId,
            ':tipo'    => $tipo,
            ':forma'   => $forma,
            ':cat'     => $cat,
            ':valor'   => $valor,
            ':usuario' => $this->usuarioId,
            ':quando'  => $dia . ' 10:00:00',
        ]);
    }

    public function testEntradasSaoQuebradasPorFormaDePagamento(): void
    {
        $r = $this->mov->resumoPeriodo('2031-03-01', '2031-03-31');

        $this->assertSame(200.0, $r['entradas']['DINHEIRO']);
        $this->assertSame(150.0, $r['entradas']['PIX']);
        $this->assertSame(80.0,  $r['entradas']['CARTAO']);
        $this->assertSame(70.0,  $r['entradas']['BOLETO']);
        $this->assertSame(500.0, $r['total_entradas']);
    }

    public function testSaidasSaoQuebradasPorCategoria(): void
    {
        $r = $this->mov->resumoPeriodo('2031-03-01', '2031-03-31');

        $this->assertSame(120.0, $r['saidas']['PECAS']);
        $this->assertSame(50.0,  $r['saidas']['BANCO']);
        $this->assertSame(0.0,   $r['saidas']['OUTRO']);
        $this->assertSame(170.0, $r['total_saidas']);
    }

    public function testResultadoEEntradasMenosSaidas(): void
    {
        $r = $this->mov->resumoPeriodo('2031-03-01', '2031-03-31');

        $this->assertSame(330.0, $r['resultado'], '500 de entradas − 170 de saídas');
        $this->assertSame(6, $r['qtd_lancamentos'], 'o lançamento de abril não pode ser contado');
        $this->assertSame(1, $r['qtd_sessoes']);
    }

    public function testPeriodoDeUmDicaSoPegaOsLancamentosDaqueleDia(): void
    {
        $r = $this->mov->resumoPeriodo(self::DIA_A, self::DIA_A);

        $this->assertSame(350.0, $r['total_entradas'], 'só dinheiro (200) + PIX (150) do dia A');
        $this->assertSame(120.0, $r['total_saidas'], 'só a saída de peças do dia A');
        $this->assertSame(0.0, $r['entradas']['CARTAO'], 'cartão foi lançado no dia B');
    }

    public function testLimitesDoIntervaloSaoInclusivos(): void
    {
        // O lançamento das 10:00 do DIA_B tem que entrar mesmo quando ele é o
        // último dia do intervalo (a query fecha em 23:59:59, não em 00:00:00).
        $r = $this->mov->resumoPeriodo(self::DIA_A, self::DIA_B);

        $this->assertSame(500.0, $r['total_entradas']);
        $this->assertSame(6, $r['qtd_lancamentos']);
    }

    public function testPeriodoSemMovimentacaoVoltaZeradoENaoQuebra(): void
    {
        $r = $this->mov->resumoPeriodo('2031-01-01', '2031-01-31');

        $this->assertSame(0.0, $r['total_entradas']);
        $this->assertSame(0.0, $r['total_saidas']);
        $this->assertSame(0.0, $r['resultado']);
        $this->assertSame(0, $r['qtd_lancamentos']);
        // As chaves precisam existir zeradas — a view itera sobre elas.
        $this->assertSame(0.0, $r['entradas']['PIX']);
        $this->assertSame(0.0, $r['saidas']['PECAS']);
    }

    public function testCategoriaAutomaticaDeOsCaiEmOutro(): void
    {
        // O enum da coluna aceita 'OS'/'VENDA' (origem automática), que não são
        // categorias de saída manual — precisam ser somadas em OUTRO em vez de
        // sumirem do relatório.
        $this->lancar('SAIDA', null, 'OS', 30.00, self::DIA_A);

        $r = $this->mov->resumoPeriodo('2031-03-01', '2031-03-31');

        $this->assertSame(30.0, $r['saidas']['OUTRO'], "saída com categoria 'OS' deve cair em OUTRO");
        $this->assertSame(200.0, $r['total_saidas'], '120 + 50 + 30');
    }

    public function testSerieDiariaAgrupaPorDia(): void
    {
        $serie = $this->mov->seriePeriodo('2031-03-01', '2031-03-31', 'dia');

        $this->assertCount(2, $serie, 'dois dias com movimentação em março');
        $this->assertSame(self::DIA_A, $serie[0]['rotulo'], 'a série precisa vir ordenada');
        $this->assertSame(350.0, $serie[0]['entradas']);
        $this->assertSame(120.0, $serie[0]['saidas']);
        $this->assertSame(self::DIA_B, $serie[1]['rotulo']);
        $this->assertSame(150.0, $serie[1]['entradas']);
    }

    public function testSerieMensalAgrupaPorMes(): void
    {
        $serie = $this->mov->seriePeriodo('2031-01-01', '2031-12-31', 'mes');

        $this->assertCount(2, $serie, 'março e abril');
        $this->assertSame('2031-03', $serie[0]['rotulo']);
        $this->assertSame(500.0, $serie[0]['entradas']);
        $this->assertSame('2031-04', $serie[1]['rotulo']);
        $this->assertSame(999.0, $serie[1]['entradas']);
    }

    public function testLancamentosDoPeriodoVemDoMaisRecenteParaOMaisAntigo(): void
    {
        $lancamentos = $this->mov->lancamentosPeriodo('2031-03-01', '2031-03-31');

        $this->assertCount(6, $lancamentos);
        $this->assertSame(
            self::DIA_B . ' 10:00:00',
            $lancamentos[0]['data_movimentacao'],
            'o mais recente precisa vir primeiro'
        );
        $this->assertSame('Caixa Teste PHPUnit', $lancamentos[0]['usuario_nome']);
    }

    public function testLancamentosRespeitamOLimite(): void
    {
        $this->assertCount(3, $this->mov->lancamentosPeriodo('2031-03-01', '2031-03-31', 3));
    }
}
