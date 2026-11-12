<?php

require_once __DIR__ . '/OsTestCase.php';

/**
 * Ciclo de vida do estoque de uma peça da OS (ConsumoEstoque).
 *
 * A auditoria achou o contrário do pedido: o estoque baixava ao SALVAR a OS (sem
 * checar saldo — chegou a −42) e editar a OS apagava o histórico. Agora:
 * abrir a OS não mexe em estoque; só o consumo CONFIRMADO baixa; reprocessar não
 * baixa de novo; sem saldo, recusa; e o histórico só cresce.
 */
final class ConsumoEstoqueTest extends OsTestCase
{
    private function itemDaOs(int $osId): int
    {
        return (int) $this->pdo->query("SELECT id_OS_produto FROM ordem_servico_produtos WHERE ordem_servico_id = {$osId}")->fetchColumn();
    }

    public function testAbrirOuEditarAOsNaoMexeNoEstoque(): void
    {
        $this->novaOs();

        $this->assertSame(10.0, $this->fx->estoqueDe($this->produtoId), 'abrir a OS não baixa estoque (antes baixava 2)');
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM movimentacoes_estoque WHERE produto_id = {$this->produtoId}"));
    }

    public function testConsumoConfirmadoBaixaOEstoqueEGravaAMovimentacao(): void
    {
        $os = $this->novaOs();
        $this->iniciar($os);

        $r = ConsumoEstoque::consumir($this->pdo, $this->itemDaOs($os), $this->tec1);

        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->assertSame(8.0, $this->fx->estoqueDe($this->produtoId));

        $mov = $this->pdo->query("SELECT tipo, origem, evento, quantidade, delta, usuario_id, ordem_servico_id FROM movimentacoes_estoque WHERE produto_id = {$this->produtoId}")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('SAIDA', $mov['tipo']);
        $this->assertSame('OS', $mov['origem']);
        $this->assertSame('CONSUMO', $mov['evento']);
        $this->assertSame(-2.0, (float) $mov['delta']);
        $this->assertSame($this->tec1, (int) $mov['usuario_id'], 'a movimentação registra quem confirmou');
        $this->assertSame($os, (int) $mov['ordem_servico_id']);

        $item = $this->pdo->query("SELECT status, consumido_por FROM ordem_servico_produtos WHERE id_OS_produto = " . $this->itemDaOs($os))->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('CONSUMIDO', $item['status']);
        $this->assertSame($this->tec1, (int) $item['consumido_por']);
    }

    public function testConsumirDeNovoNaoBaixaDuasVezes(): void
    {
        $os = $this->novaOs();
        $this->iniciar($os);
        $item = $this->itemDaOs($os);

        $r1 = ConsumoEstoque::consumir($this->pdo, $item, $this->tec1);
        $r2 = ConsumoEstoque::consumir($this->pdo, $item, $this->tec1);
        $r3 = ConsumoEstoque::consumir($this->pdo, $item, $this->adminId);

        $this->assertTrue($r1['ok'] && !$r1['repetido']);
        $this->assertTrue($r2['ok'] && $r2['repetido'], 'reprocessar devolve ok, declarado repetido');
        $this->assertTrue($r3['ok'] && $r3['repetido']);
        $this->assertSame(8.0, $this->fx->estoqueDe($this->produtoId), 'reprocessar uma OS não duplica a baixa');
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM movimentacoes_estoque WHERE os_produto_id = {$item}"));
    }

    public function testBancoRecusaASegundaBaixaDoMesmoItemMesmoSemPassarPelaChecagemDeStatus(): void
    {
        // A garantia final é do banco: chave única (item, evento).
        $os = $this->novaOs();
        $this->iniciar($os);
        $item = $this->itemDaOs($os);
        ConsumoEstoque::consumir($this->pdo, $item, $this->tec1);

        $this->expectException(PDOException::class);
        MovimentacaoEstoque::registrarSemAjustarEstoque($this->pdo, [
            'produto_id' => $this->produtoId, 'tipo' => 'SAIDA', 'origem' => 'OS', 'quantidade' => 2,
            'ordem_servico_id' => $os, 'os_produto_id' => $item, 'evento' => 'CONSUMO',
        ]);
    }

    public function testSemSaldoOConsumoERecusadoComAMensagem(): void
    {
        $os = $this->novaOs([
            'itens' => [['tipo' => 'produto', 'id' => $this->produtoId, 'nome' => 'Peça', 'qty' => 50, 'preco' => 100]],
        ]);
        $this->iniciar($os);

        $r = ConsumoEstoque::consumir($this->pdo, $this->itemDaOs($os), $this->tec1);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Saldo insuficiente', (string) $r['erro']);
        $this->assertStringContainsString('10', (string) $r['erro'], 'a mensagem diz quanto há em estoque');
        $this->assertSame(10.0, $this->fx->estoqueDe($this->produtoId), 'estoque nunca fica negativo (chegou a −42)');
        $this->assertSame('PLANEJADO', $this->pdo->query("SELECT status FROM ordem_servico_produtos WHERE id_OS_produto = " . $this->itemDaOs($os))->fetchColumn());
    }

    public function testNaoConsomeComAOsAindaNaoIniciada(): void
    {
        $os = $this->novaOs();

        $r = ConsumoEstoque::consumir($this->pdo, $this->itemDaOs($os), $this->tec1);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('execução', (string) $r['erro']);
        $this->assertSame(10.0, $this->fx->estoqueDe($this->produtoId));
    }

    public function testDevolverRestauraOEstoqueEExigeMotivo(): void
    {
        $os = $this->novaOs();
        $this->iniciar($os);
        $item = $this->itemDaOs($os);
        ConsumoEstoque::consumir($this->pdo, $item, $this->tec1);

        $semMotivo = ConsumoEstoque::devolver($this->pdo, $item, $this->tec1, '  ');
        $this->assertFalse($semMotivo['ok']);
        $this->assertSame(8.0, $this->fx->estoqueDe($this->produtoId));

        $r = ConsumoEstoque::devolver($this->pdo, $item, $this->tec1, 'Peça não foi usada');
        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->assertSame(10.0, $this->fx->estoqueDe($this->produtoId));

        $again = ConsumoEstoque::devolver($this->pdo, $item, $this->tec1, 'de novo');
        $this->assertTrue($again['ok'] && $again['repetido']);
        $this->assertSame(10.0, $this->fx->estoqueDe($this->produtoId), 'devolver duas vezes não soma duas vezes');

        $this->assertSame(2, $this->contar("SELECT COUNT(*) FROM movimentacoes_estoque WHERE os_produto_id = {$item}"), 'consumo e devolução ficam, ambos');
        $this->assertSame('DEVOLVIDO', $this->pdo->query("SELECT status FROM ordem_servico_produtos WHERE id_OS_produto = {$item}")->fetchColumn());
    }

    public function testPecaDevolvidaNaoEhMaisCobrada(): void
    {
        $os = $this->novaOs();
        $this->iniciar($os);
        $item = $this->itemDaOs($os);
        ConsumoEstoque::consumir($this->pdo, $item, $this->tec1);
        $this->assertSame(500.0, (float) $this->pdo->query("SELECT valor_total_os FROM ordens_servico WHERE id_ordem_servico = {$os}")->fetchColumn());

        ConsumoEstoque::devolver($this->pdo, $item, $this->tec1, 'Erro de lançamento');
        $totais = $this->os->recalcularTotais($os);

        $this->assertSame(300.0, $totais['total'], 'sem a peça devolvida, só o serviço é cobrado');
    }

    public function testPecaDevolvidaNaoVoltaParaOFormularioDeEdicao(): void
    {
        $os = $this->novaOs();
        $this->iniciar($os);
        $item = $this->itemDaOs($os);
        ConsumoEstoque::consumir($this->pdo, $item, $this->tec1);
        ConsumoEstoque::devolver($this->pdo, $item, $this->tec1, 'Erro');

        $ativos = array_column($this->os->getItensByOS($os), 'tipo');
        $this->assertSame(['servico'], $ativos, 'item devolvido não é re-enviado como ativo pelo formulário');
        $this->assertCount(2, $this->os->getItensByOS($os, true), 'mas continua visível no histórico da OS');
    }
}
