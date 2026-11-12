<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/models/MovimentacaoEstoque.php';

/**
 * O livro de estoque é imutável: movimentação gravada não se edita nem se apaga.
 * A correção é um ESTORNO (nova movimentação oposta, com motivo e autoria), e o
 * AJUSTE passou a ter sentido explícito (antes todo ajuste subtraía).
 *
 * Propriedade central: o saldo de um produto é a soma dos `delta` das suas
 * movimentações — o histórico explica o saldo.
 */
final class LivroEstoqueTest extends TestCase
{
    private PDO $pdo;
    private Fixtures $fx;
    private MovimentacaoEstoque $mov;
    private int $produtoId;
    private int $usuarioId;

    protected function setUp(): void
    {
        $this->pdo       = new Database();
        $this->fx        = new Fixtures($this->pdo);
        $this->mov       = new MovimentacaoEstoque();
        $this->produtoId = $this->fx->produto(10, 100, 60);
        $this->usuarioId = $this->fx->usuario('ADMIN');
    }

    protected function tearDown(): void
    {
        $this->fx->limpar();
    }

    private function lancar(array $dados): bool
    {
        return $this->mov->save($dados + ['produto_id' => $this->produtoId, 'usuario_id' => $this->usuarioId]);
    }

    private function saldo(): float
    {
        return $this->fx->estoqueDe($this->produtoId);
    }

    private function somaDeltas(): float
    {
        return (float) $this->pdo->query("SELECT COALESCE(SUM(delta),0) FROM movimentacoes_estoque WHERE produto_id = {$this->produtoId}")->fetchColumn();
    }

    public function testAjustePositivoAumentaEAjusteNegativoDiminui(): void
    {
        $this->assertTrue($this->lancar(['tipo' => 'AJUSTE', 'quantidade' => 3, 'sentido' => '+', 'justificativa' => 'Contagem de inventário']));
        $this->assertSame(13.0, $this->saldo(), 'antes não existia ajuste positivo: todo AJUSTE subtraía');

        $this->assertTrue($this->lancar(['tipo' => 'AJUSTE', 'quantidade' => 5, 'sentido' => '-', 'justificativa' => 'Caixa avariada']));
        $this->assertSame(8.0, $this->saldo());

        $deltas = $this->pdo->query("SELECT delta FROM movimentacoes_estoque WHERE produto_id = {$this->produtoId} ORDER BY id_movimentacao")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame([3.0, -5.0], array_map('floatval', $deltas));
    }

    public function testAjusteExigeSentidoEMotivo(): void
    {
        $this->assertFalse($this->lancar(['tipo' => 'AJUSTE', 'quantidade' => 1, 'justificativa' => 'sem sentido informado']));
        $this->assertStringContainsString('aumenta ou diminui', $this->mov->getLastError());

        $this->assertFalse($this->lancar(['tipo' => 'AJUSTE', 'quantidade' => 1, 'sentido' => '+']));
        $this->assertStringContainsString('motivo', $this->mov->getLastError());

        $this->assertFalse($this->lancar(['tipo' => 'AJUSTE', 'quantidade' => 1, 'sentido' => '+', 'justificativa' => 'ok']), 'motivo de 2 letras não é motivo');

        $this->assertSame(10.0, $this->saldo());
        $this->assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM movimentacoes_estoque WHERE produto_id = {$this->produtoId}")->fetchColumn());
    }

    public function testNaoDeixaOEstoqueFicarNegativoNemPorSaidaNemPorAjuste(): void
    {
        $this->assertFalse($this->lancar(['tipo' => 'SAIDA', 'quantidade' => 11]));
        $this->assertFalse($this->lancar(['tipo' => 'AJUSTE', 'quantidade' => 11, 'sentido' => '-', 'justificativa' => 'Perda total']));
        $this->assertSame(10.0, $this->saldo());
    }

    public function testSaldoEhASomaDosDeltas(): void
    {
        $this->lancar(['tipo' => 'ENTRADA', 'quantidade' => 4]);
        $this->lancar(['tipo' => 'SAIDA', 'quantidade' => '2,5']);
        $this->lancar(['tipo' => 'AJUSTE', 'quantidade' => 1, 'sentido' => '+', 'justificativa' => 'Achei uma']);
        $this->lancar(['tipo' => 'AJUSTE', 'quantidade' => 3, 'sentido' => '-', 'justificativa' => 'Perda']);

        // 10 inicial + 4 − 2,5 + 1 − 3
        $this->assertSame(9.5, $this->saldo());
        $this->assertSame(9.5 - 10.0, $this->somaDeltas(), 'o histórico explica exatamente o saldo');
    }

    public function testEstornarDesfazOEfeitoSemApagarOHistorico(): void
    {
        $this->lancar(['tipo' => 'ENTRADA', 'quantidade' => 5]);
        $orig = (int) $this->pdo->query("SELECT MAX(id_movimentacao) FROM movimentacoes_estoque WHERE produto_id = {$this->produtoId}")->fetchColumn();
        $this->assertSame(15.0, $this->saldo());

        $r = $this->mov->estornar($orig, $this->usuarioId, 'Lançada no produto errado');

        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->assertSame(10.0, $this->saldo());
        $this->assertSame(2, (int) $this->pdo->query("SELECT COUNT(*) FROM movimentacoes_estoque WHERE produto_id = {$this->produtoId}")->fetchColumn(), 'a original continua; o estorno é uma linha nova');

        $est = $this->pdo->query("SELECT tipo, origem, delta, estorno_de_id, justificativa, usuario_id FROM movimentacoes_estoque WHERE estorno_de_id = {$orig}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('SAIDA', $est['tipo']);
        $this->assertSame('ESTORNO', $est['origem']);
        $this->assertSame(-5.0, (float) $est['delta']);
        $this->assertSame('Lançada no produto errado', $est['justificativa']);
        $this->assertSame($this->usuarioId, (int) $est['usuario_id']);
        $this->assertSame(0.0, $this->somaDeltas() - ($this->saldo() - 10.0), 'o saldo continua igual à soma dos deltas');
    }

    public function testEstornoExigeMotivoEEUnicoEIdempotente(): void
    {
        $this->lancar(['tipo' => 'ENTRADA', 'quantidade' => 2]);
        $orig = (int) $this->pdo->query("SELECT MAX(id_movimentacao) FROM movimentacoes_estoque WHERE produto_id = {$this->produtoId}")->fetchColumn();

        $this->assertFalse($this->mov->estornar($orig, $this->usuarioId, '  ')['ok']);
        $this->assertSame(12.0, $this->saldo());

        $this->assertTrue($this->mov->estornar($orig, $this->usuarioId, 'Compra devolvida')['ok']);
        $segundo = $this->mov->estornar($orig, $this->usuarioId, 'Compra devolvida de novo');

        $this->assertTrue($segundo['ok'] && $segundo['repetido'], 'estornar duas vezes não estorna duas vezes');
        $this->assertSame(10.0, $this->saldo());
    }

    public function testUmEstornoNaoSeEstorna(): void
    {
        $this->lancar(['tipo' => 'ENTRADA', 'quantidade' => 2]);
        $orig = (int) $this->pdo->query("SELECT MAX(id_movimentacao) FROM movimentacoes_estoque WHERE produto_id = {$this->produtoId}")->fetchColumn();
        $this->mov->estornar($orig, $this->usuarioId, 'Engano de lançamento');
        $estorno = (int) $this->pdo->query("SELECT id_movimentacao FROM movimentacoes_estoque WHERE estorno_de_id = {$orig}")->fetchColumn();

        $r = $this->mov->estornar($estorno, $this->usuarioId, 'Desfazer o estorno');

        $this->assertFalse($r['ok']);
        $this->assertSame(10.0, $this->saldo());
    }

    public function testEstornoDeEntradaQueJaFoiConsumidaERecusado(): void
    {
        $this->lancar(['tipo' => 'ENTRADA', 'quantidade' => 3]);
        $orig = (int) $this->pdo->query("SELECT MAX(id_movimentacao) FROM movimentacoes_estoque WHERE produto_id = {$this->produtoId}")->fetchColumn();
        $this->pdo->exec("UPDATE produtos SET estoque_atual = 1 WHERE id_produto = {$this->produtoId}"); // as peças já saíram

        $r = $this->mov->estornar($orig, $this->usuarioId, 'Compra devolvida');

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('apenas 1', (string) $r['erro']);
        $this->assertSame(1.0, $this->saldo());
    }

    public function testMovimentacaoDeOsNaoSeEstornaPelaTelaDeEstoque(): void
    {
        $cliente = $this->fx->cliente();
        $os      = $this->fx->os($cliente, $this->fx->veiculo($cliente));
        $item    = $this->fx->itemPeca($os, $this->produtoId, 2, 100);
        $this->pdo->exec("UPDATE ordens_servico SET status = 'EM_ANDAMENTO' WHERE id_ordem_servico = {$os}");
        ConsumoEstoque::consumir($this->pdo, $item, $this->usuarioId);
        $mov = (int) $this->pdo->query("SELECT id_movimentacao FROM movimentacoes_estoque WHERE os_produto_id = {$item}")->fetchColumn();

        $r = $this->mov->estornar($mov, $this->usuarioId, 'Quero a peça de volta');

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('ordem de serviço', (string) $r['erro']);
        $this->assertSame(8.0, $this->saldo());
    }

    public function testEstornoDeAjusteDeCadastroDeProdutoFunciona(): void
    {
        // Produtos::update grava o ajuste com o sinal e a autoria.
        require_once __DIR__ . '/../app/models/Produtos.php';
        $_SESSION['user_id_usuario'] = $this->usuarioId;
        $ok = (new Produtos())->update($this->produtoId, [
            'nome' => 'Peça PHPUnit', 'categorias_produtos_id' => $this->fx->categoriaProduto(), 'localizacao' => 'T1',
            'estoque_atual' => '7', 'estoque_minimo' => 1, 'preco_custo' => 60, 'preco_venda' => 100, 'ativo' => 1,
        ]);
        unset($_SESSION['user_id_usuario']);
        $this->assertTrue($ok);
        $this->assertSame(7.0, $this->saldo());

        $aj = $this->pdo->query("SELECT id_movimentacao, delta, usuario_id FROM movimentacoes_estoque WHERE produto_id = {$this->produtoId} AND tipo = 'AJUSTE'")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(-3.0, (float) $aj['delta']);
        $this->assertSame($this->usuarioId, (int) $aj['usuario_id'], 'o ajuste pelo cadastro agora registra quem fez');

        $this->assertTrue($this->mov->estornar((int) $aj['id_movimentacao'], $this->usuarioId, 'Ajuste indevido')['ok']);
        $this->assertSame(10.0, $this->saldo());
    }
}
