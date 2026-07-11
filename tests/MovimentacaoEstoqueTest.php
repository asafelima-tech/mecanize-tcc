<?php

use PHPUnit\Framework\TestCase;

/**
 * Cobre a correção crítica de 2026-07-10: MovimentacaoEstoque::save()/update()
 * agora travam a linha do produto (SELECT ... FOR UPDATE) antes de validar
 * uma saída, pra estoque não conseguir ficar negativo. Também cobre a
 * preservação de quantidade fracionada (antes truncada com (int)).
 *
 * Roda contra mecanize_db_test (ver tests/bootstrap.php) — cria e remove seu
 * próprio produto de teste a cada teste, nunca toca dado de produção.
 */
final class MovimentacaoEstoqueTest extends TestCase
{
    private PDO $pdo;
    private int $categoriaId;
    private int $produtoId;

    protected function setUp(): void
    {
        $this->pdo = new Database();

        $this->pdo->exec("INSERT INTO categorias_produtos (nome) VALUES ('Categoria Teste PHPUnit')");
        $this->categoriaId = (int) $this->pdo->lastInsertId();

        require_once __DIR__ . '/../app/models/Produtos.php';
        $produtos = new Produtos();
        $produtos->create([
            'nome'                    => 'Produto Teste PHPUnit',
            'categorias_produtos_id'  => $this->categoriaId,
            'localizacao'             => 'Teste',
            'preco_custo'             => 10,
            'preco_venda'             => 20,
            'estoque_atual'           => 5,
            'estoque_minimo'          => 1,
            'ativo'                   => 1,
        ]);

        $this->produtoId = (int) $this->pdo->query(
            "SELECT id_produto FROM produtos WHERE nome = 'Produto Teste PHPUnit'"
        )->fetchColumn();
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("DELETE FROM movimentacoes_estoque WHERE produto_id = {$this->produtoId}");
        $this->pdo->exec("DELETE FROM produtos WHERE id_produto = {$this->produtoId}");
        $this->pdo->exec("DELETE FROM categorias_produtos WHERE id_categoria = {$this->categoriaId}");
    }

    private function estoqueAtual(): float
    {
        return (float) $this->pdo->query(
            "SELECT estoque_atual FROM produtos WHERE id_produto = {$this->produtoId}"
        )->fetchColumn();
    }

    public function testSaidaMaiorQueOEstoqueEBloqueada(): void
    {
        require_once __DIR__ . '/../app/models/MovimentacaoEstoque.php';
        $mov = new MovimentacaoEstoque();

        $ok = $mov->save(['produto_id' => $this->produtoId, 'tipo' => 'SAIDA', 'quantidade' => 10]);

        $this->assertFalse($ok, 'saída maior que o estoque (5) deveria ser bloqueada');
        $this->assertSame(5.0, $this->estoqueAtual(), 'estoque não deveria ter mudado');
    }

    public function testSaidaDentroDoEstoqueFunciona(): void
    {
        require_once __DIR__ . '/../app/models/MovimentacaoEstoque.php';
        $mov = new MovimentacaoEstoque();

        $ok = $mov->save(['produto_id' => $this->produtoId, 'tipo' => 'SAIDA', 'quantidade' => 3]);

        $this->assertTrue($ok);
        $this->assertSame(2.0, $this->estoqueAtual());
    }

    public function testQuantidadeFracionadaEPreservada(): void
    {
        require_once __DIR__ . '/../app/models/MovimentacaoEstoque.php';
        $mov = new MovimentacaoEstoque();

        $ok = $mov->save(['produto_id' => $this->produtoId, 'tipo' => 'SAIDA', 'quantidade' => '2.5']);

        $this->assertTrue($ok);
        $this->assertSame(2.5, $this->estoqueAtual(), 'quantidade fracionada não pode ser truncada (bug corrigido em 2026-07-10)');
    }

    public function testEntradaSomaNormalmente(): void
    {
        require_once __DIR__ . '/../app/models/MovimentacaoEstoque.php';
        $mov = new MovimentacaoEstoque();

        $ok = $mov->save(['produto_id' => $this->produtoId, 'tipo' => 'ENTRADA', 'quantidade' => 10]);

        $this->assertTrue($ok);
        $this->assertSame(15.0, $this->estoqueAtual());
    }

    public function testAjusteManualNoEditarProdutoGeraAuditoria(): void
    {
        require_once __DIR__ . '/../app/models/Produtos.php';
        $produtos = new Produtos();

        $ok = $produtos->update($this->produtoId, [
            'nome'                   => 'Produto Teste PHPUnit',
            'categorias_produtos_id' => $this->categoriaId,
            'localizacao'            => 'Teste',
            'estoque_atual'          => '8',
            'estoque_minimo'         => 1,
            'preco_custo'            => 10,
            'preco_venda'            => 20,
            'ativo'                  => 1,
        ]);

        $this->assertTrue($ok);
        $this->assertSame(8.0, $this->estoqueAtual());

        $mov = $this->pdo->query(
            "SELECT tipo, quantidade FROM movimentacoes_estoque WHERE produto_id = {$this->produtoId}"
        )->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($mov, 'correção manual de estoque deveria gerar uma movimentação de auditoria (bug corrigido em 2026-07-10)');
        $this->assertSame('AJUSTE', $mov['tipo']);
        $this->assertSame(3.0, (float) $mov['quantidade'], 'ajuste de 5 para 8 deveria registrar +3');
    }
}
