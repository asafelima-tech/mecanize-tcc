<?php

/** Nenhuma oficina enxerga ou altera dado de outra — nem pelo código, nem pelo banco. */
final class IsolamentoTest extends Cenario
{
    public function testConsultaSemFiltroDeOficinaNemExecuta(): void
    {
        $this->expectException(LogicException::class);
        $this->modelo()->lista('SELECT * FROM clientes');
    }

    public function testCadaOficinaSoVeOsProprioClientes(): void
    {
        $sql = 'SELECT nome FROM clientes WHERE oficina_id = :oficina';
        $this->assertSame(['Cliente da A'], array_column($this->modelo()->lista($sql), 'nome'));

        $this->entrarComo(self::ADMIN_B);
        $this->assertSame(['Cliente da B'], array_column($this->modelo()->lista($sql), 'nome'));
    }

    public function testParametroOficinaVindoDeForaEhIgnorado(): void
    {
        $linhas = $this->modelo()->lista('SELECT nome FROM clientes WHERE oficina_id = :oficina', ['oficina' => self::B]);
        $this->assertSame(['Cliente da A'], array_column($linhas, 'nome'));
    }

    public function testNaoAtualizaRegistroDeOutraOficina(): void
    {
        $alteradas = $this->modelo()->atualizar('clientes', 201, ['nome' => 'Invadido']);

        $this->assertSame(0, $alteradas);
        $this->assertSame('Cliente da B', $this->db->query('SELECT nome FROM clientes WHERE id = 201')->fetchColumn());
    }

    public function testInserirUsaAOficinaDaSessaoMesmoSeInformarOutra(): void
    {
        $id = $this->modelo()->inserir('clientes', ['tipo_pessoa' => 'PF', 'nome' => 'Novo', 'oficina_id' => self::B]);

        $this->assertSame(self::A, (int) $this->db->query("SELECT oficina_id FROM clientes WHERE id = {$id}")->fetchColumn());
    }

    public function testBancoRecusaOsDaOficinaAComClienteDaOficinaB(): void
    {
        $this->expectException(PDOException::class);
        $this->db->exec("INSERT INTO ordens_servico (oficina_id, numero, cliente_id, veiculo_id, criado_por) VALUES (1, 2, 201, 101, 11)");
    }

    public function testBancoRecusaTecnicoDeOutraOficina(): void
    {
        $this->expectException(PDOException::class);
        $this->db->exec('UPDATE ordens_servico SET tecnico_id = 21 WHERE id = 101');
    }

    public function testMesmaPlacaEDocumentoPodemExistirEmOficinasDiferentes(): void
    {
        $this->assertSame(2, (int) $this->db->query("SELECT COUNT(*) FROM veiculos WHERE placa_normalizada = 'ABC1234'")->fetchColumn());
        $this->assertSame(2, (int) $this->db->query("SELECT COUNT(*) FROM clientes WHERE documento = '11111111111'")->fetchColumn());
    }

    public function testPainelContaSoAOficinaLogada(): void
    {
        $this->assertSame(1, (new Painel())->resumo()['orcamentos']);
        $this->assertSame([101], array_map('intval', array_column((new Painel())->ultimasOs(), 'id')));
    }

    public function testPlataformaNaoTemOficinaEConsultaDeOficinaFalha(): void
    {
        $this->entrarComo(99);

        $this->assertNull(Contexto::oficina());
        $this->expectException(LogicException::class);
        (new Painel())->resumo();
    }

    public function testListaDeOficinasSoParaPlataforma(): void
    {
        try {
            (new Oficina())->listarTodas();
            $this->fail('Administrador de oficina não pode listar todas as oficinas.');
        } catch (ErroHttp $e) {
            $this->assertSame('sem-permissao', $e->tipo);
        }

        $this->entrarComo(99);
        $this->assertCount(2, (new Oficina())->listarTodas());
    }

    public function testArquivosFicamNaPastaDaOficinaENaoSaiDela(): void
    {
        $raizA = Armazenamento::raiz();
        $this->assertStringEndsWith(DIRECTORY_SEPARATOR . 'oficinas' . DIRECTORY_SEPARATOR . self::A, $raizA);

        $pastaB = PROJETO_RAIZ . '/storage/oficinas/' . self::B . '/teste';
        @mkdir($pastaB, 0777, true);
        file_put_contents($pastaB . '/arquivo.txt', 'da B');

        try {
            $this->assertNull(Armazenamento::caminho('../' . self::B . '/teste/arquivo.txt'));

            $this->entrarComo(self::ADMIN_B);
            $this->assertNotNull(Armazenamento::caminho('teste/arquivo.txt'));
        } finally {
            @unlink($pastaB . '/arquivo.txt');
            @rmdir($pastaB);
        }
    }
}
