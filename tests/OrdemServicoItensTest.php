<?php

require_once __DIR__ . '/OsTestCase.php';

/**
 * Criação e edição da OS: totais calculados no servidor, edição que respeita o
 * que já foi consumido, exclusão só enquanto a OS não tem história, e a visão
 * de "minhas OS" que esquecia o 2º técnico.
 */
final class OrdemServicoItensTest extends OsTestCase
{
    private function linhasDaOs(int $os): array
    {
        return $this->os->getItensByOS($os, true);
    }

    public function testTotaisSaoDosItensENaoDoQueONavegadorMandou(): void
    {
        $id = $this->novaOs([
            'valor_total_pecas'    => '1',
            'valor_total_servicos' => '1',
            'valor_total_os'       => '1',
        ]);

        $o = $this->pdo->query("SELECT valor_total_pecas, valor_total_servicos, valor_total_os FROM ordens_servico WHERE id_ordem_servico = {$id}")->fetch(PDO::FETCH_ASSOC);

        $this->assertSame(200.0, (float) $o['valor_total_pecas']);
        $this->assertSame(300.0, (float) $o['valor_total_servicos']);
        $this->assertSame(500.0, (float) $o['valor_total_os']);
    }

    public function testStatusPedidoNoFormularioNaoPulaEtapas(): void
    {
        foreach (['CONCLUIDA', 'CANCELADA', 'AGUARDANDO_RETIRADA', 'PRONTA_ENTREGA', 'xyz'] as $tentativa) {
            $id = $this->novaOs(['status' => $tentativa]);
            $this->assertSame('ABERTA', $this->status($id), "criar com status {$tentativa} cai em ABERTA");
        }

        $this->assertSame('EM_ANDAMENTO', $this->status($this->novaOs(['status' => 'EM_ANDAMENTO'])), 'começar já em execução é permitido');
    }

    public function testEditarNaoMudaOStatusNemMexeNoEstoque(): void
    {
        $id = $this->novaOs();
        $itens = $this->os->getItensByOS($id);

        $ok = $this->os->update([
            'id_ordem_servico' => $id, 'numero' => 'OS-X', 'status' => 'CONCLUIDA',
            'cliente_id' => $this->clienteId, 'veiculo_id' => $this->veiculoId,
            'itens' => array_map(fn($i) => $i + ['custom' => $i['custom'] ?? 0], $itens),
        ]);

        $this->assertTrue($ok, $this->os->getLastError());
        $this->assertSame('ABERTA', $this->status($id), 'o status vindo do formulário é ignorado pelo update');
        $this->assertSame(10.0, $this->fx->estoqueDe($this->produtoId), 'editar a OS não mexe no estoque (antes devolvia e baixava de novo)');
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM movimentacoes_estoque WHERE produto_id = {$this->produtoId}"));
    }

    public function testEditarPreservaOExecutadoDoServicoEOPercentual(): void
    {
        $id = $this->novaOs();
        $servico = array_values(array_filter($this->os->getItensByOS($id), fn($i) => $i['tipo'] === 'servico'))[0];
        $this->pdo->exec("UPDATE ordem_servico_servicos SET executado = 1, percentual_base = 12.5 WHERE id = {$servico['row_id']}");

        $itens = $this->os->getItensByOS($id);
        $ok = $this->os->update([
            'id_ordem_servico' => $id, 'numero' => 'OS-X',
            'cliente_id' => $this->clienteId, 'veiculo_id' => $this->veiculoId,
            'itens' => $itens, // o formulário devolve row_id e pct
        ]);

        $this->assertTrue($ok, $this->os->getLastError());
        $linha = $this->pdo->query("SELECT executado, percentual_base FROM ordem_servico_servicos WHERE ordem_servico_id = {$id}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $linha['executado'], 'o "executado" confirmado pelo técnico sobrevive à edição');
        $this->assertSame(12.5, (float) $linha['percentual_base'], 'o percentual também');
    }

    public function testPecaConsumidaFicaTravadaNaEdicao(): void
    {
        $id = $this->novaOs();
        $this->iniciar($id);
        $this->consumirTudo($id);
        $itens = $this->os->getItensByOS($id);

        // Tentar mudar a quantidade da peça já consumida:
        $alterados = array_map(function ($i) {
            if ($i['tipo'] === 'produto') { $i['qty'] = 1; }
            return $i;
        }, $itens);

        $this->assertFalse($this->os->update([
            'id_ordem_servico' => $id, 'numero' => 'OS-X', 'cliente_id' => $this->clienteId,
            'veiculo_id' => $this->veiculoId, 'itens' => $alterados,
        ]));
        $this->assertStringContainsString('já foi consumida', $this->os->getLastError());

        // Tentar removê-la:
        $semPeca = array_values(array_filter($itens, fn($i) => $i['tipo'] !== 'produto'));
        $this->assertFalse($this->os->update([
            'id_ordem_servico' => $id, 'numero' => 'OS-X', 'cliente_id' => $this->clienteId,
            'veiculo_id' => $this->veiculoId, 'itens' => $semPeca,
        ]));
        $this->assertStringContainsString('Devolva', $this->os->getLastError());

        $this->assertSame(8.0, $this->fx->estoqueDe($this->produtoId));
        $this->assertSame(500.0, (float) $this->pdo->query("SELECT valor_total_os FROM ordens_servico WHERE id_ordem_servico = {$id}")->fetchColumn(), 'a recusa não deixou nada pela metade');
    }

    public function testPecaApenasPlanejadaPodeSerTrocadaERemovidaLivremente(): void
    {
        $id = $this->novaOs();
        $itens = array_values(array_filter($this->os->getItensByOS($id), fn($i) => $i['tipo'] === 'servico'));

        $ok = $this->os->update([
            'id_ordem_servico' => $id, 'numero' => 'OS-X', 'cliente_id' => $this->clienteId,
            'veiculo_id' => $this->veiculoId, 'itens' => $itens,
        ]);

        $this->assertTrue($ok, $this->os->getLastError());
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM ordem_servico_produtos WHERE ordem_servico_id = {$id}"));
        $this->assertSame(300.0, (float) $this->pdo->query("SELECT valor_total_os FROM ordens_servico WHERE id_ordem_servico = {$id}")->fetchColumn());
        $this->assertSame(10.0, $this->fx->estoqueDe($this->produtoId));
    }

    public function testOsConferidaOuEncerradaNaoAceitaEdicaoDeItens(): void
    {
        $id = $this->novaOs();
        $this->ateRetirada($id);

        $ok = $this->os->update([
            'id_ordem_servico' => $id, 'numero' => 'OS-X', 'cliente_id' => $this->clienteId,
            'veiculo_id' => $this->veiculoId,
            'itens' => [['tipo' => 'servico', 'id' => 'custom_1', 'nome' => 'Barato', 'qty' => 1, 'preco' => 1, 'custom' => 1]],
        ]);

        $this->assertFalse($ok);
        $this->assertStringContainsString('não aceita mais alterações', $this->os->getLastError());
        $this->assertSame(500.0, (float) $this->pdo->query("SELECT valor_total_os FROM ordens_servico WHERE id_ordem_servico = {$id}")->fetchColumn(), 'depois da conferência o valor virou cobrança e não muda');
    }

    public function testQuantidadeInvalidaOuValorNegativoSaoRecusados(): void
    {
        $antes = $this->contar("SELECT COUNT(*) FROM ordens_servico WHERE cliente_id = {$this->clienteId}");

        foreach ([
            ['tipo' => 'produto', 'id' => $this->produtoId, 'nome' => 'P', 'qty' => 0, 'preco' => 10],
            ['tipo' => 'produto', 'id' => $this->produtoId, 'nome' => 'P', 'qty' => 1, 'preco' => -5],
        ] as $itemRuim) {
            $this->assertFalse($this->os->create([
                'cliente_id' => $this->clienteId, 'veiculo_id' => $this->veiculoId, 'itens' => [$itemRuim],
            ]));
        }

        $this->assertSame($antes, $this->contar("SELECT COUNT(*) FROM ordens_servico WHERE cliente_id = {$this->clienteId}"), 'create é transacional: item inválido não deixa OS pela metade');
    }

    public function testSegundoTecnicoEnxergaAOsNoSeuPainel(): void
    {
        $id = $this->novaOs(); // tec1 e tec2 atribuídos

        $doTec1 = array_column($this->os->listarPorTecnico($this->tec1, ['ABERTA']), 'id_ordem_servico');
        $doTec2 = array_column($this->os->listarPorTecnico($this->tec2, ['ABERTA']), 'id_ordem_servico');

        $this->assertContains($id, array_map('intval', $doTec1));
        $this->assertContains($id, array_map('intval', $doTec2), 'o 2º técnico atribuído não enxergava a OS (recebia 404)');
        $this->assertTrue($this->os->atribuidaA($id, $this->tec2));
    }

    public function testExclusaoSoEnquantoNaoHaHistoria(): void
    {
        $limpa = $this->novaOs();
        $this->assertSame([], $this->os->vinculos($limpa));
        $this->assertTrue($this->os->delete($limpa), 'OS só com peças previstas pode ser excluída');
        $this->assertSame(10.0, $this->fx->estoqueDe($this->produtoId));

        $comHistoria = $this->novaOs();
        $this->iniciar($comHistoria);
        $this->consumirTudo($comHistoria);

        $this->assertNotEmpty($this->os->vinculos($comHistoria));
        $this->assertStringContainsString('movimentações de estoque', implode(', ', $this->os->vinculos($comHistoria)));
        $this->assertFalse($this->os->delete($comHistoria), 'OS que movimentou estoque se cancela, não se apaga');
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM ordens_servico WHERE id_ordem_servico = {$comHistoria}"));
        $this->assertSame(8.0, $this->fx->estoqueDe($this->produtoId));
    }

    public function testAdicionarPecaPeloTecnicoUsaOPrecoDoCadastroEPodeConsumirNaHora(): void
    {
        $id = $this->novaOs(['itens' => []]);
        $this->iniciar($id);

        $r = $this->os->adicionarPeca($id, $this->produtoId, 3, $this->tec1, true);

        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->assertNull($r['aviso']);
        $this->assertSame(7.0, $this->fx->estoqueDe($this->produtoId));
        $this->assertSame(300.0, (float) $this->pdo->query("SELECT valor_total_os FROM ordens_servico WHERE id_ordem_servico = {$id}")->fetchColumn(), 'preço de R$ 100 vem do cadastro, não de quem lança');
    }

    public function testAdicionarPecaSemSaldoFicaPlanejadaEAvisa(): void
    {
        $id = $this->novaOs(['itens' => []]);
        $this->iniciar($id);

        $r = $this->os->adicionarPeca($id, $this->produtoId, 99, $this->tec1, true);

        $this->assertTrue($r['ok'], 'a peça foi lançada');
        $this->assertNotNull($r['aviso']);
        $this->assertStringContainsString('Saldo insuficiente', $r['aviso']);
        $this->assertSame(10.0, $this->fx->estoqueDe($this->produtoId));
        $this->assertSame('PLANEJADO', $this->pdo->query("SELECT status FROM ordem_servico_produtos WHERE id_OS_produto = {$r['item_id']}")->fetchColumn());
    }

    public function testNaoAdicionaPecaDepoisQueAOsSaiuDaExecucao(): void
    {
        $id = $this->novaOs();
        $this->ateConferencia($id);

        $r = $this->os->adicionarPeca($id, $this->produtoId, 1, $this->tec1);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('saiu da execução', (string) $r['erro']);
    }
}
