<?php

/** Cadastros da etapa 2: regras de negócio e isolamento entre oficinas. */
final class CadastrosTest extends Cenario
{
    private function cliente(array $extra = []): array
    {
        return $extra + ['tipo_pessoa' => 'PF', 'nome' => 'Maria', 'documento' => '529.982.247-25', 'telefone' => '(18) 99999-8888',
                         'email' => 'Maria@Email.com', 'cep' => '19000-000', 'uf' => 'sp', 'aceita_contato' => '1'];
    }

    public function testClienteGuardaSoDigitosENormaliza(): void
    {
        $id = (new Cliente())->salvar($this->cliente());
        $c  = (new Cliente())->buscar($id);

        $this->assertSame('52998224725', $c['documento']);
        $this->assertSame('18999998888', $c['telefone']);
        $this->assertSame('maria@email.com', $c['email']);
        $this->assertSame('SP', $c['uf']);
        $this->assertSame(self::A, (int) $c['oficina_id']);
    }

    public function testDocumentoRepetidoNaMesmaOficinaDaMensagemClara(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('CPF/CNPJ');
        (new Cliente())->salvar($this->cliente(['documento' => '11111111111']));
    }

    public function testNaoEnxergaNemEditaClienteDeOutraOficina(): void
    {
        $this->assertNull((new Cliente())->buscar(201));

        $this->expectException(ErroHttp::class);
        (new Cliente())->salvar($this->cliente(['documento' => '']), 201);
    }

    public function testBuscaEPaginacao(): void
    {
        $m = new Cliente();
        for ($i = 1; $i <= 25; $i++) {
            $m->salvar($this->cliente(['nome' => "Pessoa {$i}", 'documento' => '']));
        }
        $p = new Paginacao(2);
        $lista = $m->listar(['busca' => 'Pessoa', 'status' => 'ativos'], $p);

        $this->assertSame(25, $p->total);
        $this->assertCount(5, $lista);
        $this->assertSame(0, (new Paginacao())->deslocamento());
    }

    public function testClienteComOsNaoPodeSerExcluidoSoDesativado(): void
    {
        try {
            (new Cliente())->remover(101);
            $this->fail('Cliente com OS não pode ser excluído.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Desative', $e->getMessage());
        }

        (new Cliente())->definirAtivo(101, false);
        $this->assertSame(0, (int) (new Cliente())->buscar(101)['ativo']);
        $this->assertNotContains(101, array_map('intval', array_column((new Cliente())->opcoes(), 'id')));
    }

    public function testPlaca(): void
    {
        $this->assertSame('ABC1234', Veiculo::normalizarPlaca('abc-1234'));
        $this->assertSame('ABC1D23', Veiculo::normalizarPlaca('ABC1D23'));
        $this->assertNull(Veiculo::normalizarPlaca('AB-12345'));
        $this->assertSame('ABC-1234', Veiculo::placaFmt('ABC1234'));
        $this->assertSame('ABC1D23', Veiculo::placaFmt('ABC1D23'));
    }

    public function testVeiculoNaoPodeSerDeClienteDeOutraOficina(): void
    {
        $this->expectException(DomainException::class);
        (new Veiculo())->salvar(['cliente_id' => 201, 'placa' => 'XYZ1A23', 'modelo_texto' => 'Gol']);
    }

    public function testPlacaRepetidaNaMesmaOficina(): void
    {
        $this->expectExceptionMessage('placa');
        (new Veiculo())->salvar(['cliente_id' => 101, 'placa' => 'abc1234', 'modelo_texto' => 'Gol']);
    }

    public function testHistoricoDoVeiculo(): void
    {
        $this->db->exec("INSERT INTO ordem_servico_itens (oficina_id, ordem_servico_id, tipo, descricao, quantidade, valor_unitario)
                         VALUES (1, 101, 'SERVICO', 'Troca de óleo', 1, 100)");
        $h = (new Veiculo())->historico(101);

        $this->assertCount(1, $h);
        $this->assertSame('Troca de óleo', $h[0]['itens'][0]['descricao']);
        $this->assertSame([], (new Veiculo())->historico(201), 'veículo de outra oficina não traz nada');
    }

    public function testPecaNovaComEstoqueInicialRegistraMovimentacao(): void
    {
        $id = (new Produto())->salvar(['tipo' => 'PECA', 'nome' => 'Filtro', 'preco_venda' => '34,90', 'preco_custo' => '1.018,50', 'estoque_inicial' => '7']);
        $p  = (new Produto())->buscar($id);

        $this->assertSame('34.90', $p['preco_venda']);
        $this->assertSame('1018.50', $p['preco_custo']);
        $this->assertSame('7.000', $p['estoque_atual']);
        $this->assertSame('7.000', $this->db->query("SELECT SUM(delta) FROM movimentacoes_estoque WHERE produto_id = {$id}")->fetchColumn());
    }

    public function testEditarNaoMexeNoEstoqueNemNoTipo(): void
    {
        $m  = new Produto();
        $id = $m->salvar(['tipo' => 'PECA', 'nome' => 'Vela', 'preco_venda' => '10', 'estoque_inicial' => '4']);
        $m->salvar(['tipo' => 'SERVICO', 'nome' => 'Vela NGK', 'preco_venda' => '12', 'estoque_inicial' => '99'], $id);
        $p = $m->buscar($id);

        $this->assertSame('PECA', $p['tipo']);
        $this->assertSame('4.000', $p['estoque_atual']);
        $this->assertSame('Vela NGK', $p['nome']);
    }

    public function testServicoNaoTemEstoque(): void
    {
        $id = (new Produto())->salvar(['tipo' => 'SERVICO', 'nome' => 'Alinhamento', 'preco_venda' => '80', 'estoque_inicial' => '5']);

        $this->assertSame('0.000', (new Produto())->buscar($id)['estoque_atual']);
    }

    public function testLimiteDeUsuariosDoPlano(): void
    {
        $this->db->exec('UPDATE oficinas SET limite_usuarios = 2 WHERE id = 1'); // A já tem 2 ativos
        $this->entrarComo(self::ADMIN_A);

        $this->expectExceptionMessage('permite 2');
        (new Usuario())->salvarDaOficina(['nome' => 'Novo', 'email' => 'novo@a.test', 'perfil' => 'ATENDENTE', 'senha' => '123456']);
    }

    public function testEmailUnicoNaPlataformaInteira(): void
    {
        $this->expectExceptionMessage('e-mail já está em uso');
        (new Usuario())->salvarDaOficina(['nome' => 'Copia', 'email' => 'bruno@b.test', 'perfil' => 'ATENDENTE', 'senha' => '123456']);
    }

    public function testAdminNaoTiraOProprioAcessoNemDeixaOficinaSemAdmin(): void
    {
        $u = new Usuario();
        try {
            $u->definirAtivo(self::ADMIN_A, false);
            $this->fail('Não pode se desativar.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('própria', $e->getMessage());
        }
        try {
            $u->salvarDaOficina(['nome' => 'Ana', 'email' => 'ana@a.test', 'perfil' => 'ATENDENTE'], self::ADMIN_A);
            $this->fail('Não pode tirar o próprio perfil de administrador.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('própria', $e->getMessage());
        }

        $u->definirAtivo(12, false); // técnico pode ser desativado
        $this->assertSame(0, (int) $u->buscarDaOficina(12)['ativo']);
    }

    public function testNaoGerenciaUsuarioDeOutraOficina(): void
    {
        $this->assertNull((new Usuario())->buscarDaOficina(self::ADMIN_B));
        $this->expectException(ErroHttp::class);
        (new Usuario())->definirAtivo(self::ADMIN_B, false);
    }

    public function testTrocaDaPropriaSenhaExigeASenhaAtual(): void
    {
        $u = new Usuario();
        try {
            $u->trocarPropriaSenha('errada', 'nova123');
            $this->fail('Senha atual errada não pode trocar.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('não confere', $e->getMessage());
        }

        $u->trocarPropriaSenha(self::SENHA, 'nova123');
        $this->assertTrue(password_verify('nova123', $u->porEmail('ana@a.test')['senha_hash']));
    }

    public function testCatalogoDeModelosEhGlobal(): void
    {
        $this->db->exec("INSERT INTO modelos_veiculos (marca, nome) VALUES ('Fiat', 'Uno'), ('Fiat', 'Palio')");
        $m = new ModeloVeiculo();

        $this->assertContains('Fiat', $m->marcas());
        $this->entrarComo(self::ADMIN_B);
        $this->assertCount(2, $m->daMarca('Fiat'), 'as duas oficinas veem o mesmo catálogo');
    }

    public function testNumerosEFiltrosDasListasDeClientesEVeiculos(): void
    {
        $pj = (new Cliente())->salvar($this->cliente(['tipo_pessoa' => 'PJ', 'nome' => 'Frota Ltda', 'documento' => '']));
        (new Veiculo())->salvar(['cliente_id' => $pj, 'placa' => 'XYZ1A23', 'modelo_texto' => 'Van', 'combustivel' => 'Diesel']);

        $r = (new Cliente())->resumo();
        $this->assertSame(2, $r['ativos'], 'o cliente do cenário e o novo');
        $this->assertSame(2, $r['novos_mes']);

        $p = new Paginacao();
        $pjs = (new Cliente())->listar(['tipo' => 'PJ', 'status' => 'ativos'], $p);
        $this->assertSame([$pj], array_map('intval', array_column($pjs, 'id')));
        $this->assertSame('XYZ1A23', $pjs[0]['placas']);

        $diesel = (new Veiculo())->listar(['combustivel' => 'Diesel', 'status' => 'ativos'], new Paginacao());
        $this->assertSame(['XYZ1A23'], array_column($diesel, 'placa_normalizada'));
        $this->assertSame(2, (new Veiculo())->resumo()['ativos']);

        // Recorrente: 4 OS em 12 meses (o cenário já tem uma).
        for ($i = 0; $i < 3; $i++) {
            (new OrdemServico())->criar(['cliente_id' => 101, 'veiculo_id' => 101]);
        }
        $this->assertSame(1, (new Cliente())->resumo()['recorrentes']);
        $this->assertSame(101, (int) (new Cliente())->recorrentes()[0]['id']);

        $this->entrarComo(self::ADMIN_B);
        $this->assertSame(0, (new Cliente())->resumo()['recorrentes'], 'a oficina B não conta os clientes da A');
        $this->assertSame([], (new Cliente())->listar(['tipo' => 'PJ', 'status' => 'ativos'], new Paginacao()));
    }
}
