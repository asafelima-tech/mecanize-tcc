<?php

/**
 * Área do técnico (24/11/2026): ele só enxerga as OS em que está; informar material não mexe no estoque;
 * a baixa acontece só na validação do ADMIN, uma única vez; devolução, cancelamento e pedidos de material.
 */
final class TecnicoTest extends Cenario
{
    private const TEC = 12;      // Tiago Técnico (oficina A)
    private const OUTRO = 13;    // Otávio, outro técnico da oficina A

    private OrdemServico $os;
    private int $peca;
    private int $servico;

    protected function setUp(): void
    {
        parent::setUp();
        $hash = password_hash(self::SENHA, PASSWORD_DEFAULT);
        $this->db->exec("INSERT INTO usuarios (id, oficina_id, nome, email, senha_hash, perfil, atualizado_em)
                         VALUES (13, 1, 'Otávio Técnico', 'otavio@a.test', '{$hash}', 'TECNICO', '2026-01-01 10:00:00')");
        $this->os = new OrdemServico();
        $prod = new Produto();
        $this->peca    = $prod->salvar(['tipo' => 'PECA', 'nome' => 'Pastilha de freio', 'preco_venda' => '120', 'preco_custo' => '60', 'estoque_inicial' => '5']);
        $this->servico = $prod->salvar(['tipo' => 'SERVICO', 'nome' => 'Troca de pastilhas', 'preco_venda' => '90']);
        $this->db->exec("UPDATE usuarios SET comissao_tipo = 'PERCENTUAL_SERVICOS', comissao_percentual = 10 WHERE id = 12");
    }

    /** OS aprovada, com o técnico responsável. */
    private function osAprovada(int $tecnico = self::TEC): int
    {
        $this->entrarComo(self::ADMIN_A);
        $id = $this->os->criar(['cliente_id' => 101, 'veiculo_id' => 101, 'tecnico_id' => $tecnico, 'defeito_relatado' => 'Freio chiando']);
        $this->os->adicionarItem($id, ['produto_id' => $this->servico]);
        $this->os->aprovar($id, 'Cliente', null);

        return $id;
    }

    private function estoque(): string
    {
        return (string) $this->db->query("SELECT estoque_atual FROM produtos WHERE id = {$this->peca}")->fetchColumn();
    }

    private function saidas(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM movimentacoes_estoque WHERE produto_id = {$this->peca} AND motivo = 'SAIDA_OS'")->fetchColumn();
    }

    private function status(int $id): string
    {
        return (string) $this->db->query("SELECT status FROM ordens_servico WHERE id = {$id}")->fetchColumn();
    }

    public function testTecnicoSoVeAsOsEmQueEsta(): void
    {
        $minha = $this->osAprovada(self::TEC);
        $deOutro = $this->osAprovada(self::OUTRO);

        $this->entrarComo(self::TEC);
        $m = new ServicoTecnico();
        $this->assertSame([$minha], array_map('intval', array_column($m->servicos('fazer'), 'id')));
        $this->assertSame($minha, (int) $m->exigir($minha)['id']);

        try {
            $m->exigir($deOutro);
            $this->fail('o técnico abriu a OS de outro técnico');
        } catch (ErroHttp $e) {
            $this->assertSame('registro-inexistente', $e->tipo);
        }
        // Nem informando material, nem pedindo peça para a OS de outro
        $this->expectException(ErroHttp::class);
        $m->registrarMaterial($deOutro, ['produto_id' => $this->peca, 'quantidade' => '1']);
    }

    public function testTecnicoDoServicoTambemEnxergaAOs(): void
    {
        $id = $this->osAprovada(self::OUTRO);
        $this->entrarComo(self::ADMIN_A);
        $item = (int) $this->db->query("SELECT id FROM ordem_servico_itens WHERE ordem_servico_id = {$id} AND tipo = 'SERVICO'")->fetchColumn();
        $this->os->alterarItem($id, $item, ['tecnico_id' => self::TEC]);

        $this->entrarComo(self::TEC);
        $this->assertSame($id, (int) (new ServicoTecnico())->exigir($id)['id']);
    }

    public function testOrcamentoNaoApareceParaOTecnico(): void
    {
        $this->entrarComo(self::ADMIN_A);
        $id = $this->os->criar(['cliente_id' => 101, 'veiculo_id' => 101, 'tecnico_id' => self::TEC]);
        $this->entrarComo(self::TEC);
        $this->expectException(ErroHttp::class);
        (new ServicoTecnico())->exigir($id);
    }

    public function testMaterialInformadoSoBaixaNaValidacaoEUmaVezSo(): void
    {
        $id = $this->osAprovada();
        $this->entrarComo(self::TEC);
        $this->os->comecarExecucao($id);
        $m = new ServicoTecnico();
        $m->registrarMaterial($id, ['produto_id' => $this->peca, 'quantidade' => '2', 'observacao' => 'par dianteiro']);
        $this->assertSame('5.000', $this->estoque(), 'informar o material não mexe no estoque');

        $this->os->enviarParaValidacao($id, 'Troquei as pastilhas');
        $this->assertSame('AGUARDANDO_VALIDACAO', $this->status($id));
        $this->assertSame('5.000', $this->estoque(), 'terminar o serviço também não');

        $this->entrarComo(self::ADMIN_A);
        $mat = (int) $this->db->query("SELECT id FROM os_materiais WHERE ordem_servico_id = {$id}")->fetchColumn();
        $this->os->validar($id, [$mat]);

        $this->assertSame('PRONTA', $this->status($id));
        $this->assertSame('3.000', $this->estoque(), 'a validação baixou as 2 peças');
        $this->assertSame(1, $this->saidas());
        $os = $this->os->buscar($id);
        $this->assertSame(self::ADMIN_A, (int) $os['conferido_por']);
        $this->assertSame('330.00', $os['valor_total'], 'o material aprovado entra na OS como adicional (90 + 2 × 120)');
        $this->assertSame('9.00', (string) $this->db->query("SELECT comissao_valor FROM ordem_servico_itens WHERE ordem_servico_id = {$id} AND tipo = 'SERVICO'")->fetchColumn());

        // Validar de novo (clique duplo, outra aba): não passa e não baixa outra vez.
        try {
            $this->os->validar($id, [$mat]);
            $this->fail('validou duas vezes');
        } catch (DomainException) {
        }
        $this->assertSame('3.000', $this->estoque());
        $this->assertSame(1, $this->saidas());
        $this->assertSame('APROVADO', (string) $this->db->query("SELECT status FROM os_materiais WHERE id = {$mat}")->fetchColumn());
    }

    public function testMaterialRecusadoNaoSaiDoEstoque(): void
    {
        $id = $this->osAprovada();
        $this->entrarComo(self::TEC);
        $this->os->comecarExecucao($id);
        (new ServicoTecnico())->registrarMaterial($id, ['produto_id' => $this->peca, 'quantidade' => '1']);
        $this->os->enviarParaValidacao($id, '');

        $this->entrarComo(self::ADMIN_A);
        $this->os->validar($id, [], 'Era a peça prevista');
        $this->assertSame('5.000', $this->estoque());
        $this->assertSame('RECUSADO', (string) $this->db->query("SELECT status FROM os_materiais WHERE ordem_servico_id = {$id}")->fetchColumn());
        $this->assertSame('PRONTA', $this->status($id));
    }

    public function testSemSaldoAValidacaoNaoGravaNada(): void
    {
        $id = $this->osAprovada();
        $this->entrarComo(self::TEC);
        $this->os->comecarExecucao($id);
        (new ServicoTecnico())->registrarMaterial($id, ['produto_id' => $this->peca, 'quantidade' => '9']);
        $this->os->enviarParaValidacao($id, '');

        $this->entrarComo(self::ADMIN_A);
        $mat = (int) $this->db->query("SELECT id FROM os_materiais WHERE ordem_servico_id = {$id}")->fetchColumn();
        $this->db->exec('SAVEPOINT antes');
        try {
            $this->os->validar($id, [$mat]);
            $this->fail('validou sem saldo');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Sem estoque', $e->getMessage());
        }
        $this->assertSame('5.000', $this->estoque());
    }

    public function testDevolverVoltaAoTecnicoComOMotivo(): void
    {
        $id = $this->osAprovada();
        $this->entrarComo(self::TEC);
        $this->os->comecarExecucao($id);
        $this->os->enviarParaValidacao($id, 'feito');

        $this->entrarComo(self::ADMIN_A);
        try {
            $this->os->devolver($id, '');
            $this->fail('devolveu sem motivo');
        } catch (DomainException) {
        }
        $this->os->devolver($id, 'Faltou regular o freio de mão');
        $this->assertSame('EM_EXECUCAO', $this->status($id));

        $acoes = array_column($this->os->historico($id), 'acao');
        foreach (['EXECUCAO_INICIADA', 'EXECUCAO_ENVIADA', 'EXECUCAO_DEVOLVIDA'] as $a) {
            $this->assertContains($a, $acoes);
        }
        $aviso = $this->db->query("SELECT titulo FROM notificacoes WHERE usuario_id = 12 AND tipo = 'EXECUCAO_DEVOLVIDA'")->fetchColumn();
        $this->assertNotFalse($aviso, 'o técnico é avisado da devolução');
    }

    public function testTecnicoNaoConcluiNemValidaAOs(): void
    {
        $id = $this->osAprovada();
        $this->entrarComo(self::TEC);
        $this->os->comecarExecucao($id);
        // O técnico só "envia"; o concluir do escritório exige validação quando há material pendente.
        (new ServicoTecnico())->registrarMaterial($id, ['produto_id' => $this->peca, 'quantidade' => '1']);
        $this->entrarComo(self::ADMIN_A);
        $this->expectExceptionMessage('materiais que ainda não foram avaliados');
        $this->os->concluir($id);
    }

    public function testCancelarDesfazMaterialPendente(): void
    {
        $id = $this->osAprovada();
        $this->entrarComo(self::TEC);
        $this->os->comecarExecucao($id);
        $m = new ServicoTecnico();
        $m->registrarMaterial($id, ['produto_id' => $this->peca, 'quantidade' => '1']);
        $mat = (int) $this->db->query("SELECT id FROM os_materiais WHERE ordem_servico_id = {$id}")->fetchColumn();
        $m->cancelarMaterial($id, $mat);
        $this->assertSame('CANCELADO', (string) $this->db->query("SELECT status FROM os_materiais WHERE id = {$mat}")->fetchColumn(), 'fica registrado, não é apagado');

        $this->os->enviarParaValidacao($id, '');
        $this->expectException(DomainException::class);
        $m->registrarMaterial($id, ['produto_id' => $this->peca, 'quantidade' => '1']); // em validação, não aceita mais
    }

    public function testPedidoDeMaterialEResposta(): void
    {
        $id = $this->osAprovada();
        $this->entrarComo(self::TEC);
        $s = new Solicitacao();
        $pedido = $s->criar(['tipo' => 'INDISPONIVEL', 'ordem_servico_id' => $id, 'item_descricao' => 'Disco de freio', 'quantidade' => '2', 'justificativa' => 'Disco no limite']);
        $this->assertSame(1, $s->emAberto(self::TEC));
        $this->assertNotFalse($this->db->query("SELECT id FROM notificacoes WHERE usuario_id = 11 AND tipo = 'PEDIDO_MATERIAL'")->fetchColumn(), 'o ADMIN é avisado');

        // Pedido para OS de outro técnico: negado.
        $deOutro = $this->osAprovada(self::OUTRO);
        $this->entrarComo(self::TEC);
        try {
            $s->criar(['tipo' => 'COMPRA', 'ordem_servico_id' => $deOutro, 'item_descricao' => 'X', 'justificativa' => 'teste']);
            $this->fail('pediu para a OS de outro técnico');
        } catch (ErroHttp) {
        }

        $this->entrarComo(self::ADMIN_A);
        try {
            $s->responder($pedido, 'RECUSADA', '');
            $this->fail('recusou sem motivo');
        } catch (DomainException) {
        }
        $s->responder($pedido, 'APROVADA', 'Comprando hoje');
        $s->responder($pedido, 'ATENDIDA', 'Chegou');
        $this->assertSame('ATENDIDA', (string) $this->db->query("SELECT status FROM solicitacoes_material WHERE id = {$pedido}")->fetchColumn());
        $this->assertSame(3, (int) $this->db->query("SELECT COUNT(*) FROM historico WHERE acao = 'PEDIDO_MATERIAL' AND ordem_servico_id = {$id}")->fetchColumn(),
            'criação, aprovação e atendimento ficam no histórico');

        $this->entrarComo(self::TEC);
        $this->assertSame('ATENDIDA', $s->doTecnico()[0]['status']);
        $this->assertSame(0, $s->emAberto(self::TEC));
    }
}
