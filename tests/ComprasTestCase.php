<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/OsTestCase.php';
require_once __DIR__ . '/../app/models/SolicitacaoPeca.php';
require_once __DIR__ . '/../app/models/PedidoCompra.php';
require_once __DIR__ . '/../app/models/CotacaoCompra.php';
require_once __DIR__ . '/../app/models/Fornecedor.php';
require_once __DIR__ . '/../app/models/Notificacao.php';
require_once __DIR__ . '/../app/models/MovimentacaoEstoque.php';

/**
 * Base dos testes de solicitação de peça, compras e notificações: um administrador, dois
 * técnicos, um atendente, um fornecedor e uma peça com estoque conhecido.
 */
abstract class ComprasTestCase extends TestCase
{
    protected PDO $pdo;
    protected Fixtures $fx;
    protected SolicitacaoPeca $solic;
    protected PedidoCompra $compras;

    protected int $adminId;
    protected int $tec1;
    protected int $tec2;
    protected int $atendente;
    protected int $fornecedorId;
    protected int $produtoId;

    protected function setUp(): void
    {
        $this->pdo     = new Database();
        $this->fx      = new Fixtures($this->pdo);
        $this->solic   = new SolicitacaoPeca($this->pdo);
        $this->compras = new PedidoCompra($this->pdo);

        $this->adminId      = $this->fx->usuario('ADMIN');
        $this->tec1         = $this->fx->usuario('TECNICO');
        $this->tec2         = $this->fx->usuario('TECNICO');
        $this->atendente    = $this->fx->usuario('ATENDENTE');
        $this->fornecedorId = $this->fx->fornecedor();
        $this->produtoId    = $this->fx->produto(10, 150, 60);
    }

    protected function tearDown(): void
    {
        $this->fx->limpar();
    }

    protected function pedirPeca(array $extra = [], ?int $solicitante = null): array
    {
        return $this->solic->registrar($extra + [
            'produto_id' => $this->produtoId,
            'quantidade' => 4,
            'motivo'     => 'Estoque acabando',
            'urgencia'   => 'NORMAL',
        ], $solicitante ?? $this->tec1);
    }

    protected function statusSolicitacao(int $id): string
    {
        return (string) $this->pdo->query("SELECT status FROM solicitacoes_peca WHERE id_solicitacao = {$id}")->fetchColumn();
    }

    protected function statusPedido(int $id): string
    {
        return (string) $this->pdo->query("SELECT status FROM pedidos_compra WHERE id_pedido = {$id}")->fetchColumn();
    }

    protected function contar(string $sql): int
    {
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    protected function chave(): string
    {
        return bin2hex(random_bytes(8));
    }

    /** Rascunho com um item de $qtd × $preco (e, se informada, ligado a uma solicitação). */
    protected function rascunhoComItem(float $qtd = 10, float $preco = 80, ?int $solicitacaoId = null): array
    {
        $p = $this->compras->criarRascunho($this->fornecedorId, [], $this->adminId);
        $this->assertTrue($p['ok'], (string) $p['erro']);

        $i = $this->compras->adicionarItem($p['id'], $this->produtoId, $qtd, $preco, $solicitacaoId, $this->adminId);
        $this->assertTrue($i['ok'], (string) $i['erro']);

        return [$p['id'], $i['item_id']];
    }

    /** Pedido já REALIZADO. */
    protected function pedidoRealizado(float $qtd = 10, float $preco = 80, ?int $solicitacaoId = null): array
    {
        [$pedido, $item] = $this->rascunhoComItem($qtd, $preco, $solicitacaoId);
        $r = $this->compras->realizar($pedido, $this->adminId);
        $this->assertTrue($r['ok'], (string) $r['erro']);

        return [$pedido, $item];
    }

    protected function receber(int $pedido, int $item, float $qtd, ?string $chave = null, ?float $custo = null): array
    {
        return $this->compras->receber(
            $pedido,
            [['item_id' => $item, 'quantidade' => $qtd] + ($custo !== null ? ['custo' => $custo] : [])],
            ['chave' => $chave ?? $this->chave()],
            $this->adminId
        );
    }
}
