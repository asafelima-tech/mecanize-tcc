<?php

require_once __DIR__ . '/ComprasTestCase.php';

/**
 * Compras e recebimentos.
 *
 * Os critérios de aceite do pedido: a compra NÃO altera o estoque antes do recebimento, e o
 * recebimento parcial soma apenas o que de fato chegou. Daí em diante: idempotência do
 * recebimento, custo médio, estorno, e o reflexo no ciclo das solicitações.
 */
final class PedidoCompraTest extends ComprasTestCase
{
    public function testMontarERealizarUmPedidoNaoMexeNoEstoque(): void
    {
        [$pedido] = $this->rascunhoComItem(10, 80);
        $this->assertSame(10.0, $this->fx->estoqueDe($this->produtoId), 'rascunho não mexe no estoque');

        $this->assertTrue($this->compras->realizar($pedido, $this->adminId)['ok']);

        $this->assertSame('REALIZADO', $this->statusPedido($pedido));
        $this->assertSame(10.0, $this->fx->estoqueDe($this->produtoId), 'comprar não é ter: pedido realizado não altera o estoque');
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM movimentacoes_estoque WHERE produto_id = {$this->produtoId}"));
    }

    public function testRecebimentoParcialSomaSoOQueChegou(): void
    {
        [$pedido, $item] = $this->pedidoRealizado(10, 80);

        $r = $this->receber($pedido, $item, 4);

        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->assertSame(14.0, $this->fx->estoqueDe($this->produtoId), '10 que havia + 4 que chegaram');
        $this->assertSame('PARCIALMENTE_RECEBIDO', $this->statusPedido($pedido));
        $this->assertSame(4.0, (float) $this->pdo->query("SELECT quantidade_recebida FROM pedido_compra_itens WHERE id_pedido_item = {$item}")->fetchColumn());

        $mov = $this->pdo->query("SELECT tipo, origem, evento, quantidade, delta, custo_unitario, usuario_id FROM movimentacoes_estoque WHERE produto_id = {$this->produtoId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('ENTRADA', $mov['tipo']);
        $this->assertSame('COMPRA', $mov['origem']);
        $this->assertSame('RECEBIMENTO', $mov['evento']);
        $this->assertSame(4.0, (float) $mov['delta']);
        $this->assertSame(80.0, (float) $mov['custo_unitario']);
        $this->assertSame($this->adminId, (int) $mov['usuario_id'], 'quem confirmou o recebimento fica registrado');

        $r2 = $this->receber($pedido, $item, 6);
        $this->assertTrue($r2['ok'], (string) $r2['erro']);
        $this->assertSame(20.0, $this->fx->estoqueDe($this->produtoId));
        $this->assertSame('RECEBIDO', $this->statusPedido($pedido));
        $this->assertNotNull($this->pdo->query("SELECT recebido_em FROM pedidos_compra WHERE id_pedido = {$pedido}")->fetchColumn());
    }

    public function testMesmoEnvioDoRecebimentoNaoSomaDuasVezes(): void
    {
        [$pedido, $item] = $this->pedidoRealizado(10, 80);
        $chave = $this->chave();

        $a = $this->receber($pedido, $item, 4, $chave);
        $b = $this->receber($pedido, $item, 4, $chave);

        $this->assertTrue($b['ok']);
        $this->assertTrue($b['repetido']);
        $this->assertSame($a['id'], $b['id']);
        $this->assertSame(14.0, $this->fx->estoqueDe($this->produtoId), 'reenvio do formulário não soma de novo');
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM recebimentos_compra WHERE pedido_id = {$pedido}"));
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM movimentacoes_estoque WHERE produto_id = {$this->produtoId}"));
    }

    public function testChaveCurtaOuDeOutroPedidoERecusada(): void
    {
        [$p1, $i1] = $this->pedidoRealizado(10, 80);
        [$p2, $i2] = $this->pedidoRealizado(10, 80);

        $this->assertFalse($this->receber($p1, $i1, 1, 'abc')['ok'], 'chave curta demais');

        $chave = $this->chave();
        $this->assertTrue($this->receber($p1, $i1, 1, $chave)['ok']);
        $this->assertFalse($this->receber($p2, $i2, 1, $chave)['ok'], 'a chave de um pedido não vale em outro');
    }

    public function testNaoRecebeMaisDoQueFalta(): void
    {
        [$pedido, $item] = $this->pedidoRealizado(10, 80);
        $this->receber($pedido, $item, 8);

        $r = $this->receber($pedido, $item, 3);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('maior que a que falta', $r['erro']);
        $this->assertSame(18.0, $this->fx->estoqueDe($this->produtoId), 'nada entrou');
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM recebimentos_compra WHERE pedido_id = {$pedido}"), 'a tentativa não deixou recebimento vazio');
    }

    public function testRecebimentoSemQuantidadeEItemDeOutroPedidoSaoRecusados(): void
    {
        [$pedido, $item] = $this->pedidoRealizado(10, 80);
        [, $itemAlheio] = $this->pedidoRealizado(5, 10);

        $this->assertFalse($this->receber($pedido, $item, 0)['ok'], 'nenhuma quantidade informada');
        $this->assertFalse($this->receber($pedido, $itemAlheio, 1)['ok'], 'item de outro pedido');
        $this->assertSame(10.0, $this->fx->estoqueDe($this->produtoId));
    }

    public function testNaoRecebeRascunhoNemPedidoCancelado(): void
    {
        [$rascunho, $item] = $this->rascunhoComItem();
        $this->assertFalse($this->receber($rascunho, $item, 1)['ok'], 'rascunho ainda não foi pedido');

        [$pedido, $item2] = $this->pedidoRealizado();
        $this->compras->cancelar($pedido, $this->adminId, 'Fornecedor sem estoque');
        $this->assertFalse($this->receber($pedido, $item2, 1)['ok']);

        $this->assertSame(10.0, $this->fx->estoqueDe($this->produtoId));
    }

    public function testCustoMedioPonderado(): void
    {
        // 10 em estoque a R$ 60 + 10 que chegam a R$ 100 → R$ 80.
        [$pedido, $item] = $this->pedidoRealizado(10, 100);

        $this->receber($pedido, $item, 10);

        $this->assertSame(80.0, (float) $this->pdo->query("SELECT preco_custo FROM produtos WHERE id_produto = {$this->produtoId}")->fetchColumn());
    }

    public function testCustoRealDaNotaPodeDiferirDoPrevisto(): void
    {
        [$pedido, $item] = $this->pedidoRealizado(10, 100);

        $this->receber($pedido, $item, 10, null, 140.0); // 10 @60 + 10 @140 → 100

        $this->assertSame(100.0, (float) $this->pdo->query("SELECT preco_custo FROM produtos WHERE id_produto = {$this->produtoId}")->fetchColumn());
        $this->assertSame(140.0, (float) $this->pdo->query("SELECT custo_unitario FROM recebimento_itens")->fetchColumn());
    }

    public function testRealizarExigeItensPrecoEFornecedorAtivo(): void
    {
        $vazio = $this->compras->criarRascunho($this->fornecedorId, [], $this->adminId);
        $this->assertFalse($this->compras->realizar($vazio['id'], $this->adminId)['ok'], 'sem itens');

        [$semPreco] = $this->rascunhoComItem(5, 0);
        $r = $this->compras->realizar($semPreco, $this->adminId);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('preço', $r['erro']);

        [$pedido] = $this->rascunhoComItem(5, 10);
        $this->pdo->exec("UPDATE fornecedores SET ativo = 0 WHERE id_fornecedor = {$this->fornecedorId}");
        $this->assertFalse($this->compras->realizar($pedido, $this->adminId)['ok'], 'fornecedor desativado');
        $this->assertFalse($this->compras->criarRascunho($this->fornecedorId, [], $this->adminId)['ok']);
    }

    public function testRealizarDeNovoERepetidoSemEfeito(): void
    {
        [$pedido] = $this->pedidoRealizado();

        $r = $this->compras->realizar($pedido, $this->adminId);

        $this->assertTrue($r['ok']);
        $this->assertTrue($r['repetido']);
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM historico_estados WHERE entidade = 'PEDIDO_COMPRA' AND entidade_id = {$pedido} AND estado_novo = 'REALIZADO'"));
    }

    public function testItensSoMudamEnquantoRascunho(): void
    {
        [$pedido, $item] = $this->pedidoRealizado();

        $this->assertFalse($this->compras->adicionarItem($pedido, $this->produtoId, 1, 10, null, $this->adminId)['ok']);
        $this->assertFalse($this->compras->atualizarItem($pedido, $item, 2, 10)['ok']);
        $this->assertFalse($this->compras->removerItem($pedido, $item)['ok']);
        $this->assertFalse($this->compras->adicionarItem($this->compras->criarRascunho($this->fornecedorId, [], $this->adminId)['id'], 999999999, 1, 10, null, $this->adminId)['ok'], 'peça inexistente');
    }

    public function testMesmaPecaNoRascunhoSomaNaLinhaExistente(): void
    {
        [$pedido, $item] = $this->rascunhoComItem(5, 50);

        $r = $this->compras->adicionarItem($pedido, $this->produtoId, 3, 55, null, $this->adminId);

        $this->assertSame($item, $r['item_id']);
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM pedido_compra_itens WHERE pedido_id = {$pedido}"));
        $this->assertSame(8.0, (float) $this->pdo->query("SELECT quantidade FROM pedido_compra_itens WHERE id_pedido_item = {$item}")->fetchColumn());
    }

    public function testTotalSomaItensEFrete(): void
    {
        $p = $this->compras->criarRascunho($this->fornecedorId, ['frete' => '25,50'], $this->adminId);
        $this->compras->adicionarItem($p['id'], $this->produtoId, 2, 100, null, $this->adminId);

        $this->assertSame(225.5, $this->compras->getById($p['id'])['total']);
    }

    public function testCancelarPedidoSemRecebimentoNaoMexeNoEstoque(): void
    {
        [$pedido] = $this->pedidoRealizado();

        $this->assertFalse($this->compras->cancelar($pedido, $this->adminId, '')['ok'], 'exige motivo');
        $r = $this->compras->cancelar($pedido, $this->adminId, 'Preço subiu');

        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->assertSame('CANCELADO', $this->statusPedido($pedido));
        $this->assertSame(10.0, $this->fx->estoqueDe($this->produtoId));
        $this->assertTrue($this->compras->cancelar($pedido, $this->adminId, 'de novo')['repetido']);
    }

    public function testPedidoQueJaRecebeuNaoSeCancela(): void
    {
        [$pedido, $item] = $this->pedidoRealizado();
        $this->receber($pedido, $item, 2);

        $r = $this->compras->cancelar($pedido, $this->adminId, 'quero cancelar');

        $this->assertFalse($r['ok']);
        $this->assertSame('PARCIALMENTE_RECEBIDO', $this->statusPedido($pedido));
        $this->assertSame(12.0, $this->fx->estoqueDe($this->produtoId));
    }

    public function testEstornarRecebimentoDevolveEstoqueEQuantidadesERepeteSemEfeito(): void
    {
        [$pedido, $item] = $this->pedidoRealizado(10, 80);
        $rec = $this->receber($pedido, $item, 10);
        $this->assertSame(20.0, $this->fx->estoqueDe($this->produtoId));
        $this->assertSame('RECEBIDO', $this->statusPedido($pedido));

        $this->assertFalse($this->compras->estornarRecebimento($rec['id'], $this->adminId, '')['ok'], 'exige justificativa');

        $r = $this->compras->estornarRecebimento($rec['id'], $this->adminId, 'Veio a peça errada');
        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->assertSame(10.0, $this->fx->estoqueDe($this->produtoId));
        $this->assertSame('REALIZADO', $this->statusPedido($pedido), 'o pedido volta a aguardar a mercadoria');
        $this->assertSame(0.0, (float) $this->pdo->query("SELECT quantidade_recebida FROM pedido_compra_itens WHERE id_pedido_item = {$item}")->fetchColumn());

        $this->assertTrue($this->compras->estornarRecebimento($rec['id'], $this->adminId, 'de novo')['repetido']);
        $this->assertSame(10.0, $this->fx->estoqueDe($this->produtoId), 'estornar duas vezes não tira duas vezes');

        // O livro mantém a entrada E o estorno, ligados.
        $this->assertSame(2, $this->contar("SELECT COUNT(*) FROM movimentacoes_estoque WHERE produto_id = {$this->produtoId}"));
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM movimentacoes_estoque WHERE produto_id = {$this->produtoId} AND origem = 'ESTORNO' AND estorno_de_id IS NOT NULL"));
    }

    public function testNaoEstornaSeAsPecasJaSairamDoEstoque(): void
    {
        [$pedido, $item] = $this->pedidoRealizado(10, 80);
        $rec = $this->receber($pedido, $item, 10);

        // 15 das 20 peças foram usadas: o estorno retiraria 10 e só há 5.
        $this->pdo->exec("UPDATE produtos SET estoque_atual = 5 WHERE id_produto = {$this->produtoId}");

        $r = $this->compras->estornarRecebimento($rec['id'], $this->adminId, 'Engano no recebimento');

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('já saiu do estoque', $r['erro']);
        $this->assertSame(5.0, $this->fx->estoqueDe($this->produtoId));
        $this->assertSame('RECEBIDO', $this->statusPedido($pedido));
    }

    public function testEstornoGenericoDoLivroNaoDesfazRecebimento(): void
    {
        [$pedido, $item] = $this->pedidoRealizado(10, 80);
        $this->receber($pedido, $item, 10);
        $movId = (int) $this->pdo->query("SELECT id_movimentacao FROM movimentacoes_estoque WHERE produto_id = {$this->produtoId}")->fetchColumn();

        $r = (new MovimentacaoEstoque($this->pdo))->estornar($movId, $this->adminId, 'quero desfazer por aqui');

        $this->assertFalse($r['ok'], 'um recebimento só se desfaz pelo recebimento (que atualiza o pedido junto)');
        $this->assertSame(20.0, $this->fx->estoqueDe($this->produtoId));
    }

    public function testEncerrarComSaldoFechaOPedidoSemApagarOQueChegou(): void
    {
        [$pedido, $item] = $this->pedidoRealizado(10, 80);

        $this->assertFalse($this->compras->encerrarComSaldo($pedido, $this->adminId, 'sem recebimento')['ok'], 'só quem recebeu parte pode encerrar com saldo');

        $this->receber($pedido, $item, 6);
        $this->assertFalse($this->compras->encerrarComSaldo($pedido, $this->adminId, '')['ok'], 'exige motivo');

        $r = $this->compras->encerrarComSaldo($pedido, $this->adminId, 'Fornecedor descontinuou o restante');
        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->assertSame('RECEBIDO', $this->statusPedido($pedido));
        $this->assertSame(16.0, $this->fx->estoqueDe($this->produtoId));
        $this->assertFalse($this->receber($pedido, $item, 1)['ok'], 'pedido encerrado não recebe mais');
    }

    /* ── reflexo nas solicitações ── */

    public function testIncluirSolicitacaoNoPedidoAprovaERealizarAvisaQuemPediu(): void
    {
        $sol = $this->pedirPeca(['quantidade' => 4])['id'];
        [$pedido] = $this->rascunhoComItem(4, 80, $sol);

        $this->assertSame('APROVADA_COMPRA', $this->statusSolicitacao($sol), 'incluir no pedido é a aprovação');

        $this->compras->realizar($pedido, $this->adminId);
        $this->assertSame('PEDIDO_REALIZADO', $this->statusSolicitacao($sol));
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = {$this->tec1} AND chave_idempotencia = 'SOLICITACAO:{$sol}:PEDIDO_REALIZADO'"));
    }

    public function testRecebimentoParcialEIntegralMoveASolicitacaoEAvisaQuemPediu(): void
    {
        $sol = $this->pedirPeca(['quantidade' => 4])['id'];
        [$pedido, $item] = $this->pedidoRealizado(4, 80, $sol);

        $this->receber($pedido, $item, 1);
        $s = $this->solic->getById($sol);
        $this->assertSame('PARCIALMENTE_RECEBIDA', $s['status']);
        $this->assertSame(1.0, (float) $s['quantidade_recebida']);

        $this->receber($pedido, $item, 3);
        $s = $this->solic->getById($sol);
        $this->assertSame('RECEBIDA', $s['status']);
        $this->assertSame(4.0, (float) $s['quantidade_recebida']);

        $this->assertSame(2, $this->contar("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = {$this->tec1} AND titulo LIKE '%chegou%'"), 'avisa quando chega parte e quando completa');
    }

    public function testEstornoDeRecebimentoFazASolicitacaoVoltar(): void
    {
        $sol = $this->pedirPeca(['quantidade' => 4])['id'];
        [$pedido, $item] = $this->pedidoRealizado(4, 80, $sol);
        $rec = $this->receber($pedido, $item, 4);
        $this->assertSame('RECEBIDA', $this->statusSolicitacao($sol));

        $this->compras->estornarRecebimento($rec['id'], $this->adminId, 'Peça veio com defeito');

        $this->assertSame('PEDIDO_REALIZADO', $this->statusSolicitacao($sol));
        $this->assertSame(0.0, (float) $this->solic->getById($sol)['quantidade_recebida']);
    }

    public function testCancelarOPedidoDevolveASolicitacaoParaAprovada(): void
    {
        $sol = $this->pedirPeca(['quantidade' => 4])['id'];
        [$pedido] = $this->pedidoRealizado(4, 80, $sol);
        $this->assertSame('PEDIDO_REALIZADO', $this->statusSolicitacao($sol));

        $this->compras->cancelar($pedido, $this->adminId, 'Trocar de fornecedor');

        $this->assertSame('APROVADA_COMPRA', $this->statusSolicitacao($sol));
    }

    public function testSolicitacaoCanceladaNaoEntraNoPedido(): void
    {
        $sol = $this->pedirPeca()['id'];
        $this->solic->cancelar($sol, $this->adminId, 'ADMIN', 'não precisa mais');
        $p = $this->compras->criarRascunho($this->fornecedorId, [], $this->adminId);

        $r = $this->compras->adicionarItem($p['id'], $this->produtoId, 4, 80, $sol, $this->adminId);

        $this->assertFalse($r['ok']);
        $this->assertSame('CANCELADA', $this->statusSolicitacao($sol));
    }

    /* ── cotações ── */

    public function testCotacoesSaoComparadasPorTotalEPrazo(): void
    {
        $sol = $this->pedirPeca(['quantidade' => 4])['id'];
        $f2 = $this->fx->fornecedor();
        $f3 = $this->fx->fornecedor();
        $cot = new CotacaoCompra($this->pdo);

        $a = $cot->registrar(['solicitacao_id' => $sol, 'fornecedor_id' => $this->fornecedorId, 'preco_unitario' => '100', 'frete' => '0', 'prazo_dias' => 5], $this->adminId);
        $b = $cot->registrar(['solicitacao_id' => $sol, 'fornecedor_id' => $f2, 'preco_unitario' => '90', 'frete' => '60', 'prazo_dias' => 2], $this->adminId); // 420
        $c = $cot->registrar(['solicitacao_id' => $sol, 'fornecedor_id' => $f3, 'preco_unitario' => '95', 'frete' => '0', 'prazo_dias' => 9], $this->adminId); // 380

        foreach ([$a, $b, $c] as $r) {
            $this->assertTrue($r['ok'], (string) $r['erro']);
        }

        $lista = $cot->daSolicitacao($sol);
        $this->assertCount(3, $lista);
        $this->assertSame([380.0, 400.0, 420.0], array_map(fn($l) => (float) $l['total'], $lista), 'ordenadas pelo total com frete');

        $melhor = array_values(array_filter($lista, fn($l) => $l['melhor_preco']));
        $rapida = array_values(array_filter($lista, fn($l) => $l['mais_rapida']));
        $this->assertCount(1, $melhor);
        $this->assertSame($c['id'], (int) $melhor[0]['id_cotacao'], 'menor total = 95 × 4, sem frete');
        $this->assertSame($b['id'], (int) $rapida[0]['id_cotacao'], 'menor prazo = 2 dias');
    }

    public function testCotacaoVencidaNaoConcorreAoMelhorPreco(): void
    {
        $sol = $this->pedirPeca(['quantidade' => 1])['id'];
        $cot = new CotacaoCompra($this->pdo);

        $barata = $cot->registrar(['solicitacao_id' => $sol, 'fornecedor_id' => $this->fornecedorId, 'preco_unitario' => '10', 'validade_ate' => date('Y-m-d', strtotime('-3 days'))], $this->adminId);
        $valida = $cot->registrar(['solicitacao_id' => $sol, 'fornecedor_id' => $this->fx->fornecedor(), 'preco_unitario' => '50'], $this->adminId);

        $lista = $cot->daSolicitacao($sol);
        $porId = array_column($lista, null, 'id_cotacao');

        $this->assertTrue((bool) $porId[$barata['id']]['vencida']);
        $this->assertFalse($porId[$barata['id']]['melhor_preco']);
        $this->assertTrue($porId[$valida['id']]['melhor_preco']);
    }

    public function testCotacaoValidaDados(): void
    {
        $cot = new CotacaoCompra($this->pdo);
        $base = ['produto_id' => $this->produtoId, 'fornecedor_id' => $this->fornecedorId, 'quantidade' => 2, 'preco_unitario' => 10];

        $this->assertFalse($cot->registrar(['preco_unitario' => 0] + $base, $this->adminId)['ok'], 'preço zero');
        $this->assertFalse($cot->registrar(['quantidade' => 0] + $base, $this->adminId)['ok'], 'quantidade zero');
        $this->assertFalse($cot->registrar(['frete' => -1] + $base, $this->adminId)['ok'], 'frete negativo');
        $this->assertFalse($cot->registrar(['fornecedor_id' => 99999999] + $base, $this->adminId)['ok'], 'fornecedor inexistente');
        $this->assertTrue($cot->registrar($base, $this->adminId)['ok']);
    }

    public function testIncluirCotacaoReaproveitaORascunhoDoFornecedor(): void
    {
        $s1 = $this->pedirPeca(['quantidade' => 4], $this->tec1)['id'];
        $outraPeca = $this->fx->produto(2, 80, 40);
        $s2 = $this->solic->registrar(['produto_id' => $outraPeca, 'quantidade' => 2, 'motivo' => 'Acabando'], $this->tec2)['id'];
        $cot = new CotacaoCompra($this->pdo);

        $c1 = $cot->registrar(['solicitacao_id' => $s1, 'fornecedor_id' => $this->fornecedorId, 'preco_unitario' => '70', 'frete' => '20', 'prazo_dias' => 3, 'condicao_pagamento' => '28 dias'], $this->adminId);
        $c2 = $cot->registrar(['solicitacao_id' => $s2, 'fornecedor_id' => $this->fornecedorId, 'preco_unitario' => '40'], $this->adminId);

        $r1 = $this->compras->incluirCotacao($c1['id'], $this->adminId);
        $r2 = $this->compras->incluirCotacao($c2['id'], $this->adminId);

        $this->assertTrue($r1['ok'], (string) $r1['erro']);
        $this->assertTrue($r2['ok'], (string) $r2['erro']);
        $this->assertSame($r1['pedido_id'], $r2['pedido_id'], 'as duas necessidades se consolidam num pedido só');
        $this->assertCount(2, $this->compras->itens($r1['pedido_id']));

        $p = $this->compras->getById($r1['pedido_id']);
        $this->assertSame('28 dias', $p['condicao_pagamento'], 'o rascunho novo herda as condições da cotação');
        $this->assertSame(20.0, (float) $p['frete']);
        $this->assertSame('APROVADA_COMPRA', $this->statusSolicitacao($s1));
        $this->assertSame('APROVADA_COMPRA', $this->statusSolicitacao($s2));
    }

    public function testCotacaoDePecaNaoCadastradaNaoViraPedido(): void
    {
        $sol = $this->solic->registrar(['descricao' => 'Peça sem cadastro', 'quantidade' => 1, 'motivo' => 'Teste de cadastro'], $this->tec1)['id'];
        $c = (new CotacaoCompra($this->pdo))->registrar(['solicitacao_id' => $sol, 'fornecedor_id' => $this->fornecedorId, 'preco_unitario' => '10'], $this->adminId);

        $r = $this->compras->incluirCotacao($c['id'], $this->adminId);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('cadastro', $r['erro']);
    }

    /* ── fornecedores ── */

    public function testFornecedorValidaDocumentoDuplicidadeESite(): void
    {
        $f = new Fornecedor($this->pdo);

        $id = $f->create(['nome' => 'Auto Peças Teste PHPUnit', 'documento' => '11.222.333/0001-81', 'site_url' => 'pecas.example.com', 'email' => 'v@teste.com']);
        $this->assertNotFalse($id, $f->getLastError());
        $this->fx->registrar('fornecedores', $id);

        $this->assertSame('https://pecas.example.com', $f->getById($id)['site_url'], 'só http(s): sem protocolo vira https');
        $this->assertFalse($f->create(['nome' => 'Outro', 'documento' => '11.222.333/0001-81']), 'documento repetido');
        $this->assertFalse($f->create(['nome' => 'X', 'documento' => '123']), 'documento inválido');
        $this->assertFalse($f->create(['nome' => 'Y', 'site_url' => 'javascript:alert(1)']), 'esquema perigoso');
        $this->assertFalse($f->create(['nome' => 'Z', 'email' => 'nao-e-email']));
        $this->assertFalse($f->create(['nome' => '']));

        $this->assertTrue($f->update($id, ['nome' => 'Auto Peças Teste PHPUnit 2', 'documento' => '11.222.333/0001-81']), 'atualizar mantendo o próprio documento');
        $this->assertTrue($f->definirAtivo($id, false));
        $this->assertSame(0, (int) $f->getById($id)['ativo']);
    }
}
