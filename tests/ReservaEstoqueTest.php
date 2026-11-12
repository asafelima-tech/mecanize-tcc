<?php

require_once __DIR__ . '/OsTestCase.php';

/**
 * Reserva de peça (RESERVADO): um compromisso, não uma baixa.
 *
 * O saldo físico não muda ao reservar, mas a quantidade sai do DISPONÍVEL das outras OS —
 * duas OS não prometem a mesma última peça. Consumir usa a própria reserva; liberar, cancelar a
 * OS ou mudar a quantidade a desfazem.
 */
final class ReservaEstoqueTest extends OsTestCase
{
    private function itemDaOs(int $osId): int
    {
        return (int) $this->pdo->query("SELECT id_OS_produto FROM ordem_servico_produtos WHERE ordem_servico_id = {$osId} AND status IN ('PLANEJADO','RESERVADO') ORDER BY id_OS_produto LIMIT 1")->fetchColumn();
    }

    private function statusItem(int $itemId): string
    {
        return (string) $this->pdo->query("SELECT status FROM ordem_servico_produtos WHERE id_OS_produto = {$itemId}")->fetchColumn();
    }

    /** Segunda OS que usa a mesma peça. */
    private function outraOsComAPeca(float $qtd): int
    {
        $outro = $this->fx->os($this->clienteId, $this->veiculoId);
        $this->fx->itemPeca($outro, $this->produtoId, $qtd, 100);

        return $outro;
    }

    public function testReservarNaoMexeNoEstoqueFisicoNemGravaMovimentacao(): void
    {
        $os = $this->novaOs();
        $item = $this->itemDaOs($os);

        $r = ConsumoEstoque::reservar($this->pdo, $item, $this->tec1);

        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->assertSame('RESERVADO', $this->statusItem($item));
        $this->assertSame(10.0, $this->fx->estoqueDe($this->produtoId), 'reservar não baixa o saldo físico');
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM movimentacoes_estoque WHERE produto_id = {$this->produtoId}"));
        $this->assertSame($this->tec1, $this->contar("SELECT reservado_por FROM ordem_servico_produtos WHERE id_OS_produto = {$item}"), 'registra quem reservou');
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM historico_estados WHERE entidade = 'OS' AND entidade_id = {$os} AND motivo LIKE 'Peça reservada%'"), 'a reserva fica na trilha da OS');
    }

    public function testReservarDeNovoNaoRepeteNada(): void
    {
        $os = $this->novaOs();
        $item = $this->itemDaOs($os);

        ConsumoEstoque::reservar($this->pdo, $item, $this->tec1);
        $r = ConsumoEstoque::reservar($this->pdo, $item, $this->tec1);

        $this->assertTrue($r['ok']);
        $this->assertTrue($r['repetido']);
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM historico_estados WHERE entidade = 'OS' AND entidade_id = {$os} AND motivo LIKE 'Peça reservada%'"));
    }

    public function testReservaDeUmaOsTiraOSaldoDisponivelDasOutras(): void
    {
        $os1 = $this->novaOs(); // usa 2
        $item1 = $this->itemDaOs($os1);

        // Outra OS que usaria 9: sobram 10 − 2 reservados = 8 disponíveis.
        $os2 = $this->outraOsComAPeca(9);
        $item2 = $this->itemDaOs($os2);

        $this->assertTrue(ConsumoEstoque::reservar($this->pdo, $item1, $this->tec1)['ok']);

        $r = ConsumoEstoque::reservar($this->pdo, $item2, $this->tec1);

        $this->assertFalse($r['ok'], 'não dá para reservar 9 quando 2 já estão prometidos e há 10');
        $this->assertStringContainsString('reservado', $r['erro']);
        $this->assertSame('PLANEJADO', $this->statusItem($item2));
    }

    public function testNaoReservaMaisDoQueOEstoque(): void
    {
        $os = $this->outraOsComAPeca(11);

        $r = ConsumoEstoque::reservar($this->pdo, $this->itemDaOs($os), $this->tec1);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Não há saldo disponível', $r['erro']);
    }

    public function testConsumirPecaSoPlanejadaRespeitaAReservaDasOutras(): void
    {
        $os1 = $this->novaOs();
        $this->iniciar($os1);
        $this->assertTrue(ConsumoEstoque::reservar($this->pdo, $this->itemDaOs($os1), $this->tec1)['ok']); // 2 reservados

        $os2 = $this->outraOsComAPeca(9);
        $this->pdo->exec("UPDATE ordens_servico SET status = 'EM_ANDAMENTO' WHERE id_ordem_servico = {$os2}");

        $r = ConsumoEstoque::consumir($this->pdo, $this->itemDaOs($os2), $this->tec2);

        $this->assertFalse($r['ok'], '9 > 10 − 2 reservados');
        $this->assertStringContainsString('reservado(s) para outras OS', $r['erro']);
        $this->assertSame(10.0, $this->fx->estoqueDe($this->produtoId));
    }

    public function testConsumirPecaReservadaUsaAPropriaReserva(): void
    {
        $os = $this->novaOs();
        $this->iniciar($os);
        $item = $this->itemDaOs($os);

        ConsumoEstoque::reservar($this->pdo, $item, $this->tec1);
        $r = ConsumoEstoque::consumir($this->pdo, $item, $this->tec1);

        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->assertSame('CONSUMIDO', $this->statusItem($item));
        $this->assertSame(8.0, $this->fx->estoqueDe($this->produtoId));
    }

    public function testLiberarDevolveODisponivelEPodeSerRepetido(): void
    {
        $os1 = $this->novaOs();
        $item1 = $this->itemDaOs($os1);
        ConsumoEstoque::reservar($this->pdo, $item1, $this->tec1);

        $os2 = $this->outraOsComAPeca(9);
        $this->assertFalse(ConsumoEstoque::reservar($this->pdo, $this->itemDaOs($os2), $this->tec1)['ok']);

        $this->assertTrue(ConsumoEstoque::liberarReserva($this->pdo, $item1, $this->tec1)['ok']);
        $this->assertSame('PLANEJADO', $this->statusItem($item1));
        $this->assertTrue(ConsumoEstoque::liberarReserva($this->pdo, $item1, $this->tec1)['repetido']);

        $this->assertTrue(ConsumoEstoque::reservar($this->pdo, $this->itemDaOs($os2), $this->tec1)['ok'], 'liberada, a outra OS consegue reservar');
    }

    public function testSoPecaPrevistaPodeSerReservadaESoReservadaPodeSerLiberada(): void
    {
        $os = $this->novaOs();
        $this->iniciar($os);
        $this->consumirTudo($os);
        $consumido = (int) $this->pdo->query("SELECT id_OS_produto FROM ordem_servico_produtos WHERE ordem_servico_id = {$os}")->fetchColumn();

        $this->assertFalse(ConsumoEstoque::reservar($this->pdo, $consumido, $this->tec1)['ok'], 'peça já consumida não se reserva');
        $this->assertFalse(ConsumoEstoque::liberarReserva($this->pdo, $consumido, $this->tec1)['ok']);
    }

    public function testNaoReservaEmOsEncerradaOuJaNaConferencia(): void
    {
        $os = $this->novaOs();
        $item = $this->itemDaOs($os);
        $this->pdo->exec("UPDATE ordens_servico SET status = 'AGUARDANDO_CONFERENCIA' WHERE id_ordem_servico = {$os}");

        $this->assertFalse(ConsumoEstoque::reservar($this->pdo, $item, $this->tec1)['ok']);
    }

    public function testCancelarAOsLiberaAsReservas(): void
    {
        $os = $this->novaOs();
        $item = $this->itemDaOs($os);
        ConsumoEstoque::reservar($this->pdo, $item, $this->tec1);

        $r = $this->mover($os, 'CANCELADA', 'ADMIN', ['motivo' => 'Cliente desistiu']);

        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->assertSame('CANCELADO', $this->statusItem($item), 'a reserva não fica pendurada depois do cancelamento');
        $this->assertSame(10.0, $this->fx->estoqueDe($this->produtoId));
    }

    public function testMudarAQuantidadeDeUmaPecaReservadaDesfazAReserva(): void
    {
        $os = $this->novaOs();
        $item = $this->itemDaOs($os);
        ConsumoEstoque::reservar($this->pdo, $item, $this->tec1);

        $itens = array_map(function ($i) {
            if ($i['tipo'] === 'produto') {
                $i['qty'] = 5;
            }
            return $i + ['custom' => $i['custom'] ?? 0];
        }, $this->os->getItensByOS($os));

        $ok = $this->os->update([
            'id_ordem_servico' => $os, 'numero' => 'OS-X', 'cliente_id' => $this->clienteId, 'veiculo_id' => $this->veiculoId, 'itens' => $itens,
        ]);

        $this->assertTrue($ok, $this->os->getLastError());
        $this->assertSame('PLANEJADO', $this->statusItem($item), 'a reserva era da quantidade antiga: some e precisa ser refeita');
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM ordem_servico_produtos WHERE id_OS_produto = {$item} AND reservado_em IS NOT NULL"));
    }

    public function testEditarSemMudarAQuantidadeMantemAReserva(): void
    {
        $os = $this->novaOs();
        $item = $this->itemDaOs($os);
        ConsumoEstoque::reservar($this->pdo, $item, $this->tec1);

        $itens = array_map(fn($i) => $i + ['custom' => $i['custom'] ?? 0], $this->os->getItensByOS($os));
        $this->assertTrue($this->os->update([
            'id_ordem_servico' => $os, 'numero' => 'OS-X', 'cliente_id' => $this->clienteId, 'veiculo_id' => $this->veiculoId, 'itens' => $itens,
        ]), $this->os->getLastError());

        $this->assertSame('RESERVADO', $this->statusItem($item));
    }
}
