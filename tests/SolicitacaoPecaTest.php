<?php

require_once __DIR__ . '/ComprasTestCase.php';

/**
 * Solicitação de peça e notificações.
 *
 * A solicitação é a NECESSIDADE — não a compra nem a entrada no estoque. Estes testes
 * garantem: quem pede (técnico OU atendente) é registrado; admin e responsável pela OS são
 * avisados uma única vez; o saldo da peça é o do cadastro, não o do formulário; pedido
 * repetido não duplica; e cada passo do ciclo deixa rastro.
 */
final class SolicitacaoPecaTest extends ComprasTestCase
{
    public function testTecnicoRegistraESolicitacaoComSaldoDoCadastro(): void
    {
        $r = $this->pedirPeca(['quantidade' => '3,5', 'descricao' => 'texto que deve ser ignorado', 'urgencia' => 'ALTA']);

        $this->assertTrue($r['ok'], (string) $r['erro']);
        $s = $this->solic->getById($r['id']);

        $this->assertMatchesRegularExpression('/^SP-\d{4}/', $s['numero']);
        $this->assertSame('SOLICITADA', $s['status']);
        $this->assertSame(3.5, (float) $s['quantidade'], 'aceita vírgula e fração');
        $this->assertSame(10.0, (float) $s['estoque_conhecido'], 'o saldo conhecido vem do cadastro, não do formulário');
        $this->assertSame($s['produto_nome'], $s['descricao'], 'o nome vem do cadastro');
        $this->assertSame($this->tec1, (int) $s['solicitante_id']);
        $this->assertSame('ALTA', $s['urgencia']);
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM historico_estados WHERE entidade = 'SOLICITACAO_PECA' AND entidade_id = {$r['id']} AND estado_novo = 'SOLICITADA'"));
    }

    public function testAtendenteTambemSolicita(): void
    {
        $r = $this->pedirPeca([], $this->atendente);

        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->assertSame($this->atendente, (int) $this->solic->getById($r['id'])['solicitante_id']);
    }

    public function testPecaNaoCadastradaSoComDescricao(): void
    {
        $r = $this->solic->registrar(['descricao' => 'Coxim do motor Gol G5', 'quantidade' => 1, 'motivo' => 'Peça específica de uma OS'], $this->tec1);

        $this->assertTrue($r['ok'], (string) $r['erro']);
        $s = $this->solic->getById($r['id']);
        $this->assertNull($s['produto_id']);
        $this->assertNull($s['estoque_conhecido']);
        $this->assertSame('Coxim do motor Gol G5', $s['descricao']);
    }

    public function testValidacoes(): void
    {
        $this->assertFalse($this->solic->registrar(['quantidade' => 1, 'motivo' => 'sem peça'], $this->tec1)['ok'], 'sem peça nem descrição');
        $this->assertFalse($this->pedirPeca(['quantidade' => 0])['ok'], 'quantidade zero');
        $this->assertFalse($this->pedirPeca(['quantidade' => -2])['ok'], 'quantidade negativa');
        $this->assertFalse($this->pedirPeca(['motivo' => ''])['ok'], 'sem motivo');
        $this->assertFalse($this->pedirPeca(['produto_id' => 99999999])['ok'], 'peça inexistente');

        $r = $this->pedirPeca(['urgencia' => 'QUALQUER']);
        $this->assertSame('NORMAL', $this->solic->getById($r['id'])['urgencia'], 'urgência inválida cai em NORMAL');
    }

    public function testNaoSolicitaPecaParaOsEncerradaOuInexistente(): void
    {
        $cli = $this->fx->cliente();
        $os = $this->fx->os($cli, $this->fx->veiculo($cli), ['status' => 'CONCLUIDA']);

        $this->assertFalse($this->pedirPeca(['ordem_servico_id' => $os])['ok']);
        $this->assertFalse($this->pedirPeca(['ordem_servico_id' => 99999999])['ok']);
    }

    public function testMesmaSolicitacaoEmAbertoNaoDuplica(): void
    {
        $a = $this->pedirPeca();
        $b = $this->pedirPeca();

        $this->assertTrue($b['ok']);
        $this->assertTrue($b['repetido']);
        $this->assertSame($a['id'], $b['id']);
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM solicitacoes_peca WHERE produto_id = {$this->produtoId}"));
    }

    public function testOutroSolicitanteOuOutraOsAbreOutraSolicitacao(): void
    {
        $a = $this->pedirPeca();
        $outroUsuario = $this->pedirPeca([], $this->tec2);

        $cli = $this->fx->cliente();
        $os = $this->fx->os($cli, $this->fx->veiculo($cli));
        $outraOs = $this->pedirPeca(['ordem_servico_id' => $os]);

        $this->assertCount(3, array_unique([$a['id'], $outroUsuario['id'], $outraOs['id']]));
    }

    public function testDepoisDeCanceladaPodeSolicitarDeNovo(): void
    {
        $a = $this->pedirPeca();
        $this->assertTrue($this->solic->cancelar($a['id'], $this->tec1, 'TECNICO', 'Pedi sem querer')['ok']);

        $b = $this->pedirPeca();

        $this->assertTrue($b['ok']);
        $this->assertFalse($b['repetido']);
        $this->assertNotSame($a['id'], $b['id']);
    }

    public function testAvisaAdminsEOTecnicoDaOsMasNaoQuemPediuNemDuasVezes(): void
    {
        $cli = $this->fx->cliente();
        $os = $this->fx->os($cli, $this->fx->veiculo($cli));
        $this->pdo->prepare('INSERT INTO ordem_servico_mao_obra (ordem_servico_id, tecnico_id, valor_mao_obra, descricao) VALUES (?, ?, 0, ?)')
            ->execute([$os, $this->tec2, 'Comissão']);

        $r = $this->pedirPeca(['ordem_servico_id' => $os, 'urgencia' => 'URGENTE'], $this->atendente);
        $this->assertTrue($r['ok'], (string) $r['erro']);

        $n = fn(int $usuario) => $this->contar("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = {$usuario} AND tipo = 'SOLICITACAO_PECA'");

        $this->assertSame(1, $n($this->adminId), 'o administrador é avisado');
        $this->assertSame(1, $n($this->tec2), 'o técnico responsável pela OS é avisado');
        $this->assertSame(0, $n($this->atendente), 'quem pediu não é avisado do próprio pedido');
        $this->assertSame(0, $n($this->tec1), 'técnico que não é da OS não é avisado');

        $titulo = $this->pdo->query("SELECT titulo FROM notificacoes WHERE usuario_id = {$this->adminId} ORDER BY id_notificacao DESC LIMIT 1")->fetchColumn();
        $this->assertStringContainsString('urgência urgente', $titulo);

        // O mesmo pedido de novo (clique repetido) não avisa outra vez.
        $this->pedirPeca(['ordem_servico_id' => $os, 'urgencia' => 'URGENTE'], $this->atendente);
        $this->assertSame(1, $n($this->adminId));
    }

    public function testCicloDeDecisaoDoAdministrador(): void
    {
        $id = $this->pedirPeca()['id'];

        $this->assertTrue($this->solic->analisar($id, $this->adminId)['ok']);
        $this->assertSame('EM_ANALISE', $this->statusSolicitacao($id));

        $this->assertTrue($this->solic->aprovar($id, $this->adminId)['ok']);
        $this->assertSame('APROVADA_COMPRA', $this->statusSolicitacao($id));
        $this->assertTrue($this->solic->aprovar($id, $this->adminId)['repetido'], 'aprovar de novo é idempotente');

        $s = $this->solic->getById($id);
        $this->assertSame($this->adminId, (int) $s['decidido_por']);
        $this->assertNotNull($s['decidido_em']);

        $estados = $this->pdo->query("SELECT estado_novo FROM historico_estados WHERE entidade = 'SOLICITACAO_PECA' AND entidade_id = {$id} ORDER BY id_historico")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['SOLICITADA', 'EM_ANALISE', 'APROVADA_COMPRA'], $estados);
    }

    public function testQuemPediuSeAvisadoDaDecisao(): void
    {
        $id = $this->pedirPeca()['id'];
        $this->solic->aprovar($id, $this->adminId);
        $this->solic->aprovar($id, $this->adminId);

        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = {$this->tec1} AND chave_idempotencia = 'SOLICITACAO:{$id}:APROVADA_COMPRA'"));
    }

    public function testTransicaoInexistenteERecusada(): void
    {
        $id = $this->pedirPeca()['id'];

        $r = SolicitacaoPeca::transicionar($this->pdo, $id, 'RECEBIDA', $this->adminId);

        $this->assertFalse($r['ok'], 'ninguém pula de "solicitada" para "recebida"');
        $this->assertSame('SOLICITADA', $this->statusSolicitacao($id));

        $this->assertFalse(SolicitacaoPeca::transicionar($this->pdo, $id, 'PEDIDO_REALIZADO', $this->adminId)['ok']);
    }

    public function testCancelarExigeMotivoESolicitanteSoCancelaAPropriaAindaNaoAnalisada(): void
    {
        $id = $this->pedirPeca()['id'];

        $this->assertFalse($this->solic->cancelar($id, $this->tec1, 'TECNICO', '')['ok'], 'sem motivo');
        $this->assertFalse($this->solic->cancelar($id, $this->tec2, 'TECNICO', 'não é minha')['ok'], 'outro técnico não cancela');
        $this->assertSame('SOLICITADA', $this->statusSolicitacao($id));

        $this->solic->analisar($id, $this->adminId);
        $r = $this->solic->cancelar($id, $this->tec1, 'TECNICO', 'desisti');
        $this->assertFalse($r['ok'], 'depois que o administrador analisa, quem pediu não cancela sozinho');

        $r = $this->solic->cancelar($id, $this->adminId, 'ADMIN', 'Não vamos comprar');
        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->assertSame('CANCELADA', $this->statusSolicitacao($id));
        $this->assertSame('Não vamos comprar', $this->solic->getById($id)['motivo_decisao']);
        $this->assertTrue($this->solic->cancelar($id, $this->adminId, 'ADMIN', 'de novo')['repetido']);
    }

    public function testNecessidadesConsolidamPorPecaEMostramOQueEstaAbaixoDoMinimo(): void
    {
        $a = $this->pedirPeca(['quantidade' => 4], $this->tec1)['id'];
        $b = $this->pedirPeca(['quantidade' => 6, 'urgencia' => 'URGENTE'], $this->tec2)['id'];

        $minimo = $this->fx->produto(0, 100, 50); // estoque 0, mínimo 1, sem solicitação

        $n = $this->solic->necessidades();

        $linha = array_values(array_filter($n['solicitadas'], fn($l) => (int) $l['produto_id'] === $this->produtoId));
        $this->assertCount(1, $linha, 'duas solicitações da mesma peça viram uma linha');
        $this->assertSame(10.0, (float) $linha[0]['quantidade']);
        $this->assertSame(2, (int) $linha[0]['qtd_solicitacoes']);
        $this->assertSame('URGENTE', $linha[0]['urgencia'], 'vale a maior urgência');
        $this->assertSame([$a, $b], array_map('intval', explode(',', $linha[0]['ids'])));

        $abaixo = array_column($n['abaixo_minimo'], 'produto_id');
        $this->assertContains((string) $minimo, array_map('strval', $abaixo), 'peça no mínimo sem solicitação aparece');
        $this->assertNotContains((string) $this->produtoId, array_map('strval', $abaixo), 'peça já solicitada não é sugerida de novo');
    }
}
