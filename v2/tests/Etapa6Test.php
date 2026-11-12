<?php

/** Agenda, anexos, avisos, relatórios, configurações da oficina e licenças (plataforma). */
final class Etapa6Test extends Cenario
{
    private array $arquivosCriados = [];

    protected function tearDown(): void
    {
        foreach ($this->arquivosCriados as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    /* ── Agenda ──────────────────────────────────────────────────────── */

    private function agendar(array $extra = []): int
    {
        return (new Agendamento())->salvar($extra + ['cliente_id' => 101, 'veiculo_id' => 101, 'data' => '2030-05-10', 'hora' => '09:00', 'duracao_min' => 60, 'servico' => 'Revisão']);
    }

    public function testConflitoDeHorarioPorTecnicoESemTecnico(): void
    {
        $this->agendar(['tecnico_id' => 12]);

        // Mesmo técnico, horário sobreposto: recusa.
        try {
            $this->agendar(['tecnico_id' => 12, 'hora' => '09:30']);
            $this->fail('Conflito do técnico.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('técnico', $e->getMessage());
        }
        // Outro técnico no mesmo horário: pode.
        $this->assertGreaterThan(0, $this->agendar(['tecnico_id' => 11, 'hora' => '09:30']));
        // Encostado (começa quando o outro termina): pode.
        $this->assertGreaterThan(0, $this->agendar(['tecnico_id' => 12, 'hora' => '10:00']));
        // Sem técnico: não pode sobrepor nenhum agendamento ativo.
        $this->expectException(DomainException::class);
        $this->agendar(['hora' => '09:15']);
    }

    public function testCanceladoLiberaOHorarioEReativarConfereDeNovo(): void
    {
        $m = new Agendamento();
        $a = $this->agendar(['tecnico_id' => 12]);
        $m->marcar($a, 'CANCELADO');
        $b = $this->agendar(['tecnico_id' => 12]);
        $this->assertGreaterThan($a, $b);

        $this->expectExceptionMessage('ocupado');
        $m->marcar($a, 'AGENDADO');
    }

    public function testVeiculoPrecisaSerDoClienteEOutraOficinaNaoEnxerga(): void
    {
        try {
            $this->agendar(['veiculo_id' => 201]);
            $this->fail('Veículo de outra oficina.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('veículo', $e->getMessage());
        }
        $id = $this->agendar();
        $this->entrarComo(self::ADMIN_B);
        $this->assertNull((new Agendamento())->buscar($id));
        $this->assertSame([], (new Agendamento())->porPeriodo('2030-05-01', '2030-06-01'));
    }

    public function testGerarOsVinculaUmaVezSo(): void
    {
        $m = new Agendamento();
        $a = $this->agendar();
        $this->assertTrue($m->vincularOs($a, 101, 101));
        $ag = $m->buscar($a);
        $this->assertSame('CONCLUIDO', $ag['status']);
        $this->assertSame(101, (int) $ag['ordem_servico_id']);
        // Segundo clique: não vincula outra OS.
        $this->assertFalse($m->vincularOs($a, 101, 101));
        // Agendamento que virou OS não é excluído nem muda de situação.
        $this->expectException(DomainException::class);
        $m->remover($a);
    }

    /* ── Anexos ──────────────────────────────────────────────────────── */

    private function arquivoTemporario(string $conteudo, string $nome): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'anx');
        file_put_contents($tmp, $conteudo);
        $this->arquivosCriados[] = $tmp;

        return ['name' => $nome, 'tmp_name' => $tmp, 'size' => strlen($conteudo), 'error' => UPLOAD_ERR_OK];
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    public function testFotoNaOsGravaArquivoDaOficinaEHistorico(): void
    {
        $m = new Anexo();
        $id = $m->anexarNaOs(101, 'FOTO_ANTES', 'Para-choque', $this->arquivoTemporario($this->png(), 'frente.png'), false);
        $a = $m->buscar($id);
        $caminho = Armazenamento::caminho($a['caminho']);
        $this->arquivosCriados[] = $caminho;

        $this->assertNotNull($caminho);
        $this->assertStringContainsString(DIRECTORY_SEPARATOR . 'oficinas' . DIRECTORY_SEPARATOR . '1' . DIRECTORY_SEPARATOR, $caminho);
        $this->assertSame('image/png', $a['mime']);
        $this->assertSame('frente.png', $a['nome_original']);
        $this->assertSame('ANEXO', $this->db->query('SELECT acao FROM historico WHERE ordem_servico_id = 101 ORDER BY id DESC LIMIT 1')->fetchColumn());

        // A outra oficina não acha o anexo nem o arquivo.
        $this->entrarComo(self::ADMIN_B);
        $this->assertNull((new Anexo())->buscar($id));
        $this->assertNull(Armazenamento::caminho($a['caminho']));
    }

    public function testFotoRecusaPdfEOsDeOutraOficina(): void
    {
        $m = new Anexo();
        try {
            $m->anexarNaOs(101, 'FOTO_DEPOIS', null, $this->arquivoTemporario("%PDF-1.4\n%âãÏÓ\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF", 'nota.pdf'), false);
            $this->fail('PDF como foto.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('foto', $e->getMessage());
        }
        $this->expectException(ErroHttp::class);
        $m->anexarNaOs(201, 'FOTO_ANTES', null, $this->arquivoTemporario($this->png(), 'x.png'), false);
    }

    public function testArquivoDisfarcadoERecusado(): void
    {
        $this->expectExceptionMessage('Tipo de arquivo não aceito');
        (new Anexo())->anexarNaOs(101, 'DOCUMENTO', null, $this->arquivoTemporario('<?php echo 1;', 'foto.png'), false);
    }

    /* ── Avisos ──────────────────────────────────────────────────────── */

    public function testOsSemPecaAvisaOsAdminsUmaVez(): void
    {
        $this->db->exec("INSERT INTO produtos (id, oficina_id, tipo, nome, preco_venda) VALUES (501, 1, 'PECA', 'Filtro', 30)");
        $os = new OrdemServico();
        $os->adicionarItem(101, ['produto_id' => 501, 'quantidade' => '1']);
        $os->aprovar(101, 'Cliente', null);
        $this->entrarComo(12); // o técnico inicia; a ADMIN é avisada
        $os->iniciar(101);
        $os->baixarPendentes(101);

        $this->entrarComo(self::ADMIN_A);
        $n = new Notificacao();
        $this->assertSame(1, $n->naoLidas());
        $aviso = $n->listar()[0];
        $this->assertSame('./os?id=101', $n->ler((int) $aviso['id']));
        $this->assertSame(0, $n->naoLidas());

        // Ninguém lê aviso de outra pessoa.
        $this->entrarComo(12);
        $this->assertNull((new Notificacao())->ler((int) $aviso['id']));
    }

    /* ── Relatórios ──────────────────────────────────────────────────── */

    public function testRelatorioUsaOsEntreguesEDinheiroEfetivo(): void
    {
        $this->db->exec("UPDATE ordens_servico SET status = 'ENTREGUE', aprovado_em = NOW(), entregue_em = NOW(), valor_total = 300, valor_servicos = 300 WHERE id IN (101, 201)");
        $f = new Lancamento();
        $conta = $f->criar(['tipo' => 'RECEBER', 'categoria' => 'SERVICOS', 'descricao' => 'OS', 'valor' => '300', 'vencimento' => date('Y-m-d')]);
        $f->baixar($conta, ['valor' => '200', 'forma' => 'PIX', 'taxa' => '2']);
        $movId = (int) $this->db->query("SELECT id FROM movimentacoes_financeiras WHERE lancamento_id = {$conta}")->fetchColumn();
        $f->baixar($conta, ['valor' => '100', 'forma' => 'CARTAO_CREDITO', 'taxa' => '5']);
        $f->estornar($movId, 'Lançado errado');

        $r = new Relatorio(date('Y-m-01'), date('Y-m-d'));
        $fat = $r->faturamento();
        $this->assertSame(1, $fat['os']);           // a OS da outra oficina não entra
        $this->assertSame(300.0, $fat['total']);

        $cx = $r->caixa();
        $this->assertEqualsWithDelta(100.0, $cx['recebido'], 0.001);   // 200 + 100 − 200 estornado
        $this->assertEqualsWithDelta(5.0, $cx['taxas'], 0.001);        // a taxa do PIX estornado volta
        $this->assertSame(['CARTAO_CREDITO'], array_keys(array_filter($cx['por_forma'], fn($v) => abs($v) > 0.001)));

        $contas = $r->contasEmAberto();
        $this->assertEqualsWithDelta(200.0, $contas['RECEBER']['saldo'], 0.001);
    }

    /* ── Configurações e plataforma ──────────────────────────────────── */

    public function testAdminEditaCadastroComHistoricoMasNaoALicenca(): void
    {
        $o = new Oficina();
        $o->salvarConfiguracoes(['nome' => 'Oficina A Ltda', 'cnpj' => '11.222.333/0001-81', 'uf' => 'sp', 'cep' => '01001-000',
                                 'status' => 'ATIVA', 'licenca_valida_ate' => '2099-01-01', 'limite_usuarios' => 99]);
        $a = $o->atual();
        $this->assertSame('Oficina A Ltda', $a['nome']);
        $this->assertSame('SP', $a['uf']);
        $this->assertSame('01001000', $a['cep']);
        $this->assertNotSame('2099-01-01', $a['licenca_valida_ate']);   // licença ignorada
        $this->assertNotSame(99, (int) $a['limite_usuarios']);
        $this->assertSame('CONFIGURACOES', $o->historico()[0]['acao']);

        $this->expectException(ErroHttp::class);
        $o->alterarLicenca(1, ['plano' => 'PREMIUM', 'limite_usuarios' => 99, 'status' => 'ATIVA', 'licenca_valida_ate' => '2099-01-01']);
    }

    public function testPlataformaCadastraOficinaEAlteraLicencaComHistorico(): void
    {
        $this->entrarComo(99);
        $o = new Oficina();
        $id = $o->criar(['nome' => 'Oficina C', 'plano' => 'BASICO', 'limite_usuarios' => 3, 'status' => 'TESTE', 'licenca_valida_ate' => '2030-01-31',
                         'admin_nome' => 'Carla', 'admin_email' => 'Carla@C.test', 'admin_senha' => 'provisoria1']);

        $adm = $this->db->query("SELECT * FROM usuarios WHERE oficina_id = {$id}")->fetch();
        $this->assertSame('ADMIN', $adm['perfil']);
        $this->assertSame('carla@c.test', $adm['email']);
        $this->assertTrue(password_verify('provisoria1', $adm['senha_hash']));

        $mudou = $o->alterarLicenca($id, ['plano' => 'PROFISSIONAL', 'limite_usuarios' => 10, 'status' => 'ATIVA', 'licenca_valida_ate' => '2031-01-31', 'motivo' => 'Pago']);
        $this->assertSame(['de' => 'TESTE', 'para' => 'ATIVA'], $mudou['status']);
        $h = $o->historicoLicenca($id);
        $this->assertSame(['LICENCA', 'OFICINA_CRIADA'], array_column($h, 'acao'));
        $this->assertNull($h[0]['autor_id']);

        try {
            $o->alterarLicenca($id, ['plano' => 'PROFISSIONAL', 'limite_usuarios' => 10, 'status' => 'CANCELADA', 'licenca_valida_ate' => '2031-01-31']);
            $this->fail('Cancelar sem motivo.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('motivo', $e->getMessage());
        }

        // E-mail repetido (de qualquer oficina) é recusado com mensagem clara.
        $this->expectExceptionMessage('e-mail');
        $o->criar(['nome' => 'Oficina D', 'plano' => 'BASICO', 'limite_usuarios' => 3, 'status' => 'TESTE', 'licenca_valida_ate' => '2030-01-31',
                   'admin_nome' => 'X', 'admin_email' => 'ana@a.test', 'admin_senha' => 'provisoria1']);
    }

    public function testUsuarioDeOficinaNaoUsaAsTelasDaPlataforma(): void
    {
        $this->expectException(ErroHttp::class);
        (new Oficina())->listarTodas();
    }

    /* ── Telas do Design System ──────────────────────────────────────── */

    public function testCalendarioDaAgendaMarcaSoDiasComHorarioAtivo(): void
    {
        $a = $this->agendar(['tecnico_id' => 12]);
        $this->agendar(['tecnico_id' => 12, 'hora' => '14:00']);
        $c = $this->agendar(['data' => '2030-05-12']);
        (new Agendamento())->marcar($c, 'CANCELADO');

        $dias = (new Agendamento())->diasComAgendamento('2030-05-01', '2030-06-01');
        $this->assertSame(['2030-05-10' => 2], $dias, 'cancelado não marca o dia');

        $this->entrarComo(self::ADMIN_B);
        $this->assertSame([], (new Agendamento())->diasComAgendamento('2030-05-01', '2030-06-01'), 'a oficina B não vê a agenda da A');
    }

    public function testGraficosDaTelaInicialContamSoAOficinaLogada(): void
    {
        $g = (new Painel())->graficos();
        $this->assertSame(1, $g['status_mes']['abertas'], 'a OS de teste da oficina A está em orçamento');
        $this->assertCount(7, $g['fluxo']);
        $this->assertCount(6, $g['meses']);
        $this->assertSame(array_sum(array_column($g['fluxo'], 'os')), $g['abertas_hoje'], 'as OS de hoje entram no último dia do fluxo');

        $this->entrarComo(self::ADMIN_B);
        $this->assertSame(1, (new Painel())->graficos()['status_mes']['abertas'], 'a oficina B conta só a própria OS');
    }

    public function testRelatorioPorTecnicoESituacaoDasAbertasEResumoDaEquipe(): void
    {
        $os = new OrdemServico();
        $id = $os->criar(['cliente_id' => 101, 'veiculo_id' => 101, 'tecnico_id' => 12, 'previsao_conclusao' => date('Y-m-d', strtotime('+3 days'))]);
        $os->adicionarItem($id, ['descricao' => 'Revisão', 'valor_unitario' => '200']);
        $os->aprovar($id, 'Cliente', null);
        $os->iniciar($id);
        $os->concluir($id);
        $os->entregar($id, '');

        $r = new Relatorio(date('Y-m-01'), date('Y-m-d'));
        $d = array_column($r->desempenho(), null, 'tecnico');
        $this->assertSame(1, (int) $d['Tiago Técnico']['os']);
        $this->assertEqualsWithDelta(200.0, (float) $d['Tiago Técnico']['total'], 0.001);
        $this->assertSame(1, $d['Tiago Técnico']['no_prazo'], 'ficou pronta antes da previsão');

        $s = $r->situacaoDasAbertas();
        $this->assertSame(1, $s['Concluídas']);
        $this->assertSame(1, $s['Orçamento'], 'a OS do cenário');

        $equipe = (new Usuario())->resumo();
        $this->assertSame(2, $equipe['ativos']);
        $this->assertSame(1, $equipe['admins']);

        $this->entrarComo(self::ADMIN_B);
        $this->assertSame([], (new Relatorio(date('Y-m-01'), date('Y-m-d')))->desempenho(), 'a oficina B não vê o desempenho da A');
        $this->assertSame(1, (new Usuario())->resumo()['total']);
    }
}
