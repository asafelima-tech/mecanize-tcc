<?php

require_once __DIR__ . '/OsTestCase.php';

/**
 * Máquina de estados da OS (OrdemServicoFluxo). Cada teste é um defeito da
 * auditoria de 08/10/2026 que passou a ser impossível:
 *
 *  - Kanban/edição levavam a OS de qualquer etapa para qualquer etapa;
 *  - concluir duas vezes lançava duas vezes no caixa;
 *  - técnico (ou qualquer um) liberava o veículo sem a conferência do administrador;
 *  - concluir não era idempotente nem deixava rastro.
 */
final class OrdemServicoFluxoTest extends OsTestCase
{
    public function testTransicoesInexistentesSaoRecusadasSemMudarNada(): void
    {
        $id = $this->novaOs();

        foreach ([
            'CONCLUIDA'              => 'pular direto para entregue',
            'AGUARDANDO_RETIRADA'    => 'liberar sem conferência',
            'AGUARDANDO_CONFERENCIA' => 'terminar sem ter começado',
        ] as $destino => $descricao) {
            $r = $this->mover($id, $destino, 'ADMIN');
            $this->assertFalse($r['ok'], "ABERTA não pode: {$descricao}");
        }

        $this->assertSame('ABERTA', $this->status($id));
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM historico_estados WHERE entidade='OS' AND entidade_id={$id}"), 'só o registro de criação');
    }

    public function testOsEncerradaNaoVolta(): void
    {
        $id = $this->novaOs();
        $this->assertTrue($this->mover($id, 'CANCELADA', 'ADMIN', ['motivo' => 'Cliente desistiu'])['ok']);

        foreach (['ABERTA', 'EM_ANDAMENTO', 'CONCLUIDA'] as $destino) {
            $this->assertFalse($this->mover($id, $destino, 'ADMIN')['ok'], "CANCELADA → {$destino}");
        }

        $this->assertSame('CANCELADA', $this->status($id));
    }

    public function testTecnicoTerminaMasNaoEncerraNemConfereNemCancela(): void
    {
        $id = $this->novaOs();
        $this->iniciar($id);
        $this->consumirTudo($id);

        $r = $this->mover($id, 'AGUARDANDO_CONFERENCIA', 'TECNICO');
        $this->assertTrue($r['ok'], 'o técnico termina o serviço e envia para conferência');

        foreach (['TECNICO', 'ATENDENTE'] as $papel) {
            $r = $this->mover($id, 'AGUARDANDO_RETIRADA', $papel);
            $this->assertFalse($r['ok'], "{$papel} não confere a OS");
            $this->assertStringContainsString('administrador', (string) $r['erro']);

            $this->assertFalse($this->mover($id, 'EM_ANDAMENTO', $papel, ['motivo' => 'x'])['ok'], "{$papel} não devolve a OS");
            $this->assertFalse($this->mover($id, 'CANCELADA', $papel, ['motivo' => 'x'])['ok'], "{$papel} não cancela a OS");
        }

        $this->assertSame('AGUARDANDO_CONFERENCIA', $this->status($id));
    }

    public function testConferirGeraACobrancaEEIdempotente(): void
    {
        $id = $this->novaOs();
        $this->ateConferencia($id);

        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM ordem_servico_parcelas WHERE ordem_servico_id={$id}"), 'antes da conferência não há cobrança');

        $r1 = $this->mover($id, 'AGUARDANDO_RETIRADA', 'ADMIN');
        $this->assertTrue($r1['ok'], (string) $r1['erro']);
        $this->assertFalse($r1['repetido']);

        $parcelas = $this->pdo->query("SELECT valor, pago FROM ordem_servico_parcelas WHERE ordem_servico_id={$id}")->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $parcelas, 'OS à vista = uma parcela');
        $this->assertSame(500.0, (float) $parcelas[0]['valor']);
        $this->assertSame(0, (int) $parcelas[0]['pago'], 'conferir NÃO é receber: a parcela continua em aberto');
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM movimentacoes_caixa WHERE ordem_servico_id={$id}"), 'nenhum dinheiro entrou no caixa');

        $conferido = $this->pdo->query("SELECT conferido_por, data_conferencia FROM ordens_servico WHERE id_ordem_servico={$id}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame($this->adminId, (int) $conferido['conferido_por']);
        $this->assertNotNull($conferido['data_conferencia']);

        // Clique duplo: nada se repete.
        $r2 = $this->mover($id, 'AGUARDANDO_RETIRADA', 'ADMIN');
        $this->assertTrue($r2['ok']);
        $this->assertTrue($r2['repetido'], 'a segunda conferência se declara repetida');
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM ordem_servico_parcelas WHERE ordem_servico_id={$id}"));
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM historico_estados WHERE entidade='OS' AND entidade_id={$id} AND estado_novo='AGUARDANDO_RETIRADA'"));
    }

    public function testNaoConfereComPecaPrevistaQueNaoFoiUsada(): void
    {
        $id = $this->novaOs();
        $this->iniciar($id);
        // Termina SEM consumir a peça prevista.
        $this->assertTrue($this->mover($id, 'AGUARDANDO_CONFERENCIA', 'TECNICO')['ok']);

        $r = $this->mover($id, 'AGUARDANDO_RETIRADA', 'ADMIN');

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('não consumida', (string) $r['erro']);
        $this->assertSame('AGUARDANDO_CONFERENCIA', $this->status($id), 'a transação desfaz tudo');
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM ordem_servico_parcelas WHERE ordem_servico_id={$id}"));
    }

    public function testReprovarExigeMotivoEDesfazAhCobrancaNaoRecebida(): void
    {
        $id = $this->novaOs();
        $this->ateRetirada($id);
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM ordem_servico_parcelas WHERE ordem_servico_id={$id}"));

        $semMotivo = $this->mover($id, 'EM_ANDAMENTO', 'ADMIN');
        $this->assertFalse($semMotivo['ok']);
        $this->assertStringContainsString('motivo', (string) $semMotivo['erro']);
        $this->assertSame('AGUARDANDO_RETIRADA', $this->status($id));

        $r = $this->mover($id, 'EM_ANDAMENTO', 'ADMIN', ['motivo' => 'Faltou alinhar']);
        $this->assertTrue($r['ok'], (string) $r['erro']);

        $this->assertSame('EM_ANDAMENTO', $this->status($id));
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM ordem_servico_parcelas WHERE ordem_servico_id={$id}"), 'a cobrança será refeita na próxima conferência');
        $this->assertNull($this->pdo->query("SELECT conferido_por FROM ordens_servico WHERE id_ordem_servico={$id}")->fetchColumn());

        $motivo = $this->pdo->query("SELECT motivo FROM historico_estados WHERE entidade='OS' AND entidade_id={$id} AND estado_novo='EM_ANDAMENTO' ORDER BY id_historico DESC")->fetchColumn();
        $this->assertSame('Faltou alinhar', $motivo, 'o motivo fica na trilha');
    }

    public function testEntregarComRecebimentoLancaNoCaixaUmaSoVez(): void
    {
        $id = $this->novaOs();
        $this->ateRetirada($id);

        $r1 = $this->mover($id, 'CONCLUIDA', 'TECNICO', ['receber_forma' => 'PIX']);
        $this->assertTrue($r1['ok'], (string) $r1['erro']);

        $this->assertSame('CONCLUIDA', $this->status($id));
        $this->assertNotNull($this->pdo->query("SELECT data_conclusao FROM ordens_servico WHERE id_ordem_servico={$id}")->fetchColumn());
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM movimentacoes_caixa WHERE ordem_servico_id={$id} AND origem='OS'"));
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM ordem_servico_parcelas WHERE ordem_servico_id={$id} AND pago=1"));

        // Clique duplo / aba antiga: antes lançava a entrada de novo.
        $r2 = $this->mover($id, 'CONCLUIDA', 'TECNICO', ['receber_forma' => 'PIX']);
        $this->assertTrue($r2['ok']);
        $this->assertTrue($r2['repetido']);
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM movimentacoes_caixa WHERE ordem_servico_id={$id} AND origem='OS'"), 'entregar duas vezes NÃO pode lançar duas vezes no caixa');
    }

    public function testEntregarSemReceberNaoDaBaixaNaCobranca(): void
    {
        $id = $this->novaOs();
        $this->ateRetirada($id);

        $this->assertTrue($this->mover($id, 'CONCLUIDA', 'TECNICO')['ok']);

        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM movimentacoes_caixa WHERE ordem_servico_id={$id}"));
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM ordem_servico_parcelas WHERE ordem_servico_id={$id} AND pago=0"), 'entregue ≠ recebido');
    }

    public function testRecebimentoRecusadoDesfazAEntrega(): void
    {
        $id = $this->novaOs();
        $this->ateRetirada($id);
        $this->pdo->exec("UPDATE caixa SET status='FECHADO', data_fechamento=NOW(), valor_fechamento=0 WHERE id_caixa={$this->caixaId}");

        $r = $this->mover($id, 'CONCLUIDA', 'TECNICO', ['receber_forma' => 'DINHEIRO']);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('caixa', strtolower((string) $r['erro']));
        $this->assertSame('AGUARDANDO_RETIRADA', $this->status($id), 'sem caixa o recebimento falha e a entrega não acontece "pela metade"');
    }

    public function testCancelarDevolveOEstoqueERemoveACobranca(): void
    {
        $id = $this->novaOs();
        $this->ateRetirada($id);
        $this->assertSame(8.0, $this->fx->estoqueDe($this->produtoId), 'as 2 peças foram consumidas');

        $semMotivo = $this->mover($id, 'CANCELADA', 'ADMIN');
        $this->assertFalse($semMotivo['ok']);

        $r = $this->mover($id, 'CANCELADA', 'ADMIN', ['motivo' => 'Cliente desistiu']);
        $this->assertTrue($r['ok'], (string) $r['erro']);

        $this->assertSame('CANCELADA', $this->status($id));
        $this->assertSame(10.0, $this->fx->estoqueDe($this->produtoId), 'cancelar devolve as peças consumidas');
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM ordem_servico_parcelas WHERE ordem_servico_id={$id}"));
        $this->assertSame('DEVOLVIDO', $this->pdo->query("SELECT status FROM ordem_servico_produtos WHERE ordem_servico_id={$id}")->fetchColumn());
        $this->assertSame(2, $this->contar("SELECT COUNT(*) FROM movimentacoes_estoque WHERE ordem_servico_id={$id}"), 'consumo + devolução: o histórico guarda os dois');
    }

    public function testNaoCancelaOsQueJaRecebeuPagamento(): void
    {
        $id = $this->novaOs();
        $this->ateRetirada($id);
        $this->assertTrue($this->mover($id, 'CONCLUIDA', 'ADMIN', ['receber_forma' => 'PIX'])['ok']);
        // CONCLUIDA é terminal; usa uma segunda OS para o caso "recebeu e ainda não foi entregue".
        $id2 = $this->novaOs();
        $this->ateRetirada($id2);
        $parcela = (int) $this->pdo->query("SELECT id_parcela FROM ordem_servico_parcelas WHERE ordem_servico_id={$id2}")->fetchColumn();
        $this->assertNull((new OrdemServicoPagamento())->pagarParcela($parcela, 'PIX', $this->adminId));

        $r = $this->mover($id2, 'CANCELADA', 'ADMIN', ['motivo' => 'x']);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('recebimento', strtolower((string) $r['erro']));
        $this->assertSame('AGUARDANDO_RETIRADA', $this->status($id2));
        // As duas OS consumiram 2 peças cada (10 − 4 = 6). A recusa não pode ter devolvido
        // as da segunda: a transação do cancelamento desfaz tudo.
        $this->assertSame(6.0, $this->fx->estoqueDe($this->produtoId), 'a recusa não devolveu estoque (transação desfeita)');
    }

    public function testCadaTransicaoFicaNaTrilhaComAutor(): void
    {
        $id = $this->novaOs();
        $this->ateRetirada($id);

        $trilha = $this->pdo->query("SELECT estado_novo, usuario_id FROM historico_estados WHERE entidade='OS' AND entidade_id={$id} ORDER BY id_historico")
            ->fetchAll(PDO::FETCH_ASSOC);

        $this->assertSame(
            ['ABERTA', 'EM_ANDAMENTO', 'AGUARDANDO_CONFERENCIA', 'AGUARDANDO_RETIRADA'],
            array_column($trilha, 'estado_novo')
        );
        $this->assertSame($this->adminId, (int) end($trilha)['usuario_id'], 'a trilha registra quem conferiu');
    }

    public function testDestinosPermitidosRefletemOPapel(): void
    {
        $this->assertSame([], OrdemServicoFluxo::destinosPermitidos('CONCLUIDA', 'ADMIN'));
        $this->assertSame([], OrdemServicoFluxo::destinosPermitidos('AGUARDANDO_CONFERENCIA', 'TECNICO'));
        $this->assertSame([], OrdemServicoFluxo::destinosPermitidos('AGUARDANDO_CONFERENCIA', 'ATENDENTE'));
        $this->assertEqualsCanonicalizing(
            ['AGUARDANDO_RETIRADA', 'EM_ANDAMENTO', 'CANCELADA'],
            OrdemServicoFluxo::destinosPermitidos('AGUARDANDO_CONFERENCIA', 'ADMIN')
        );
    }
}
