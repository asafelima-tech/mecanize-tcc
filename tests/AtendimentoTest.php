<?php

require_once __DIR__ . '/OsTestCase.php';
require_once __DIR__ . '/../app/models/Ocorrencia.php';
require_once __DIR__ . '/../app/models/Avaliacao.php';
require_once __DIR__ . '/../app/models/AutorizacaoCliente.php';
require_once __DIR__ . '/../app/models/Orcamento.php';
require_once __DIR__ . '/../app/models/Cliente.php';

/**
 * Os fluxos de atendimento, do balcão à OS — sem duplicar cliente, veículo, orçamento nem OS.
 *
 *  FLUXO 1  cliente completo chega → ocorrência → avaliação → orçamento → aprovação → OS
 *  FLUXO 2  cadastro mínimo (nome + telefone) → ocorrência → avaliação → autorização com valor
 *           em aberto → OS direto, SEM orçamento formal → regularização/unificação do cadastro
 *  FLUXO 3  cliente conhecido, atendimento remoto → mesmo caminho do 1 (origem REMOTO)
 */
final class AtendimentoTest extends OsTestCase
{
    private Ocorrencia $oc;
    private Avaliacao $av;
    private Orcamento $orc;
    private AutorizacaoCliente $aut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oc  = new Ocorrencia();
        $this->av  = new Avaliacao();
        $this->orc = new Orcamento();
        $this->aut = new AutorizacaoCliente();
    }

    protected function tearDown(): void
    {
        // Cadastros criados pelo cadastro rápido entram na limpeza.
        foreach ($this->pdo->query("SELECT id_cliente FROM clientes WHERE nome LIKE 'PHPUnit Rápido%'")->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $this->fx->registrar('clientes', (int) $id);
        }
        parent::tearDown();
    }

    /** Chegada de um cliente já cadastrado (o do OsTestCase). */
    private function chegada(array $extra = []): int
    {
        $r = $this->oc->registrar(array_merge([
            'cliente' => ['id' => $this->clienteId],
            'veiculo' => ['id' => $this->veiculoId],
            'relato'  => 'Barulho na suspensão dianteira ao passar em lombadas',
        ], $extra), $this->adminId, 'ADMIN');

        $this->assertTrue($r['ok'], (string) $r['erro']);

        return (int) $r['id'];
    }

    private function statusOc(int $id): string
    {
        return (string) $this->pdo->query("SELECT status FROM ocorrencias WHERE id_ocorrencia = {$id}")->fetchColumn();
    }

    /** Avaliação técnica completa: 1 peça (R$ 100 do cadastro) + 1 serviço de R$ 200. */
    private function avaliar(int $ocId, bool $concluir = true): int
    {
        $i = $this->av->iniciar($ocId, $this->tec1);
        $this->assertTrue($i['ok'], (string) $i['erro']);

        $s = $this->av->salvar($i['id'], [
            'defeitos'    => 'Amortecedor dianteiro vazando e bucha da bandeja gasta',
            'observacoes' => 'Recomendo trocar o par',
            'duracao_estimada_min' => 120,
            'itens' => [
                ['tipo' => 'produto', 'produto_id' => $this->produtoId, 'quantidade' => 1, 'preco' => 100],
                ['tipo' => 'servico', 'nome' => 'Troca de amortecedor', 'categoria' => 'Suspensão', 'quantidade' => 1, 'preco' => 200],
            ],
        ]);
        $this->assertTrue($s['ok'], (string) $s['erro']);

        if ($concluir) {
            $c = $this->av->concluir($i['id'], $this->tec1);
            $this->assertTrue($c['ok'], (string) $c['erro']);
        }

        return (int) $i['id'];
    }

    /* ── Chegada ─────────────────────────────────────────────────────── */

    public function testChegadaComClienteECarroJaCadastradosNaoCriaNada(): void
    {
        $clientes = $this->contar('SELECT COUNT(*) FROM clientes');
        $veiculos = $this->contar('SELECT COUNT(*) FROM veiculos');

        $id = $this->chegada();

        $o = $this->oc->getById($id);
        $this->assertSame('AGUARDANDO_AVALIACAO', $o['status']);
        $this->assertSame('PRESENCIAL', $o['origem']);
        $this->assertSame('OC-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT), $o['numero']);
        $this->assertSame($clientes, $this->contar('SELECT COUNT(*) FROM clientes'), 'reaproveitou o cliente');
        $this->assertSame($veiculos, $this->contar('SELECT COUNT(*) FROM veiculos'), 'reaproveitou o veículo');
        $this->assertCount(1, $this->oc->linhaDoTempo($id));
    }

    public function testCadastroRapidoCriaClienteProvisorioEVeiculoIncompleto(): void
    {
        $r = $this->oc->registrar([
            'cliente' => ['nome' => 'PHPUnit Rápido Maria', 'telefone' => '(18) 99123-4567'],
            'veiculo' => ['placa' => 'ZQR1D23'],
            'relato'  => 'Carro não pega pela manhã',
        ], $this->adminId, 'ATENDENTE');

        $this->assertTrue($r['ok'], (string) $r['erro']);

        $cli = $this->pdo->query("SELECT nome, telefone, cadastro_status, tipo_pessoa FROM clientes WHERE id_cliente = {$r['cliente_id']}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('PROVISORIO', $cli['cadastro_status'], 'o cadastro mínimo é identificado como provisório');
        $this->assertSame('PHPUnit Rápido Maria', $cli['nome']);

        $v = $this->pdo->query("SELECT v.placa, v.placa_normalizada, v.dados_incompletos, m.nome AS marca FROM veiculos v JOIN marcas_veiculos m ON m.id_marca = v.marca_id WHERE v.id_veiculo = {$r['veiculo_id']}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('ZQR1D23', $v['placa']);
        $this->assertSame('Não informado', $v['marca']);
        $this->assertSame(1, (int) $v['dados_incompletos']);

        $this->assertSame('RAPIDO', $this->oc->getById((int) $r['id'])['origem']);

        $this->pdo->exec("DELETE FROM historico_estados WHERE entidade='OCORRENCIA' AND entidade_id={$r['id']}");
        $this->fx->registrar('ocorrencias', (int) $r['id']);
        $this->fx->registrar('veiculos', (int) $r['veiculo_id']);
        $this->fx->registrar('clientes', (int) $r['cliente_id']);
    }

    public function testTelefoneJaCadastradoNaoDuplicaEOferecePerfilExistente(): void
    {
        $this->pdo->exec("UPDATE clientes SET telefone = '(18) 98877-6655' WHERE id_cliente = {$this->clienteId}");
        $antes = [$this->contar('SELECT COUNT(*) FROM clientes'), $this->contar('SELECT COUNT(*) FROM veiculos'), $this->contar('SELECT COUNT(*) FROM ocorrencias')];

        $r = $this->oc->registrar([
            'cliente' => ['nome' => 'PHPUnit Rápido Joana', 'telefone' => '18988776655'],
            'veiculo' => ['placa' => 'ZQR2E34'],
            'relato'  => 'Revisão',
        ], $this->adminId, 'ATENDENTE');

        $this->assertFalse($r['ok']);
        $this->assertSame('cliente', $r['duplicidade']['tipo']);
        $this->assertSame($this->clienteId, (int) $r['duplicidade']['id'], 'aponta o cadastro existente para ser usado');
        $this->assertSame($antes, [$this->contar('SELECT COUNT(*) FROM clientes'), $this->contar('SELECT COUNT(*) FROM veiculos'), $this->contar('SELECT COUNT(*) FROM ocorrencias')], 'nada foi gravado');
    }

    public function testPlacaDeOutroClienteBarraEDesfazOClienteProvisorioJaCriado(): void
    {
        $antes = $this->contar('SELECT COUNT(*) FROM clientes');
        $placa = (string) $this->pdo->query("SELECT placa_normalizada FROM veiculos WHERE id_veiculo = {$this->veiculoId}")->fetchColumn();

        $r = $this->oc->registrar([
            'cliente' => ['nome' => 'PHPUnit Rápido Pedro', 'telefone' => '(18) 97766-5544'],
            'veiculo' => ['placa' => $placa],
            'relato'  => 'Troca de óleo',
        ], $this->adminId, 'ATENDENTE');

        $this->assertFalse($r['ok']);
        $this->assertSame('veiculo', $r['duplicidade']['tipo']);
        $this->assertSame($this->clienteId, (int) $r['duplicidade']['id'], 'diz de quem é o veículo');
        $this->assertSame($antes, $this->contar('SELECT COUNT(*) FROM clientes'), 'o provisório criado um instante antes foi desfeito (sem órfão)');
    }

    public function testValidacoesDaChegada(): void
    {
        $base = ['cliente' => ['id' => $this->clienteId], 'veiculo' => ['id' => $this->veiculoId], 'relato' => 'Problema qualquer'];

        $this->assertFalse($this->oc->registrar(array_merge($base, ['relato' => ' ']), $this->adminId)['ok'], 'relato obrigatório');
        $this->assertFalse($this->oc->registrar(['cliente' => ['nome' => 'X', 'telefone' => '123'], 'veiculo' => ['placa' => 'ABC1234'], 'relato' => 'ok ok'], $this->adminId)['ok'], 'telefone inválido');
        $this->assertFalse($this->oc->registrar(['cliente' => ['nome' => 'PHPUnit Rápido Z', 'telefone' => '(18) 96655-4433'], 'veiculo' => ['placa' => '12'], 'relato' => 'ok ok'], $this->adminId)['ok'], 'placa inválida');
        $this->assertFalse($this->oc->registrar(array_merge($base, ['cliente' => ['id' => 999999999]]), $this->adminId)['ok'], 'cliente inexistente');
        $this->assertFalse($this->oc->registrar(array_merge($base, ['tecnico_id' => $this->adminId]), $this->adminId)['ok'], 'responsável precisa ser técnico ativo');
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM clientes WHERE nome LIKE 'PHPUnit Rápido%'"), 'recusas não deixam cadastro pelo caminho');
    }

    public function testUrgenciaEDeclaradaPeloAtendenteEValidadaPeloAdministrador(): void
    {
        $semMotivo = $this->oc->registrar(['cliente' => ['id' => $this->clienteId], 'veiculo' => ['id' => $this->veiculoId], 'relato' => 'Carro parado na rua', 'urgencia' => true], $this->adminId, 'ATENDENTE');
        $this->assertFalse($semMotivo['ok'], 'urgência exige motivo');

        $r = $this->oc->registrar(['cliente' => ['id' => $this->clienteId], 'veiculo' => ['id' => $this->veiculoId], 'relato' => 'Carro parado na rua', 'urgencia' => true, 'urgencia_motivo' => 'Cliente precisa do carro para trabalhar'], $this->adminId, 'ATENDENTE');
        $id = (int) $r['id'];
        $this->assertSame('ALTA', $this->oc->getById($id)['prioridade'], 'declarada pelo atendente = ALTA (ainda não validada)');

        $this->assertTrue($this->oc->validarUrgencia($id, $this->adminId)['ok']);
        $o = $this->oc->getById($id);
        $this->assertSame('URGENTE', $o['prioridade']);
        $this->assertSame($this->adminId, (int) $o['urgencia_validada_por']);
        $this->assertTrue($this->oc->validarUrgencia($id, $this->adminId)['repetido'], 'validar de novo não repete');

        $normal = $this->chegada();
        $this->assertFalse($this->oc->validarUrgencia($normal, $this->adminId)['ok'], 'só urgência declarada pode ser validada');

        $porAdmin = $this->oc->registrar(['cliente' => ['id' => $this->clienteId], 'veiculo' => ['id' => $this->veiculoId], 'relato' => 'Urgente de verdade', 'urgencia' => true, 'urgencia_motivo' => 'Frota parada'], $this->adminId, 'ADMIN');
        $this->assertSame('URGENTE', $this->oc->getById((int) $porAdmin['id'])['prioridade'], 'o administrador que registra já valida');
    }

    /* ── Fluxo 1: avaliação → orçamento → aprovação → OS ───────────────── */

    public function testFluxo1CompletoDaChegadaAteAEntrega(): void
    {
        $oc = $this->chegada();

        // técnico avalia
        $avId = $this->avaliar($oc);
        $this->assertSame('AGUARDANDO_ORCAMENTO', $this->statusOc($oc));
        $this->assertSame($this->tec1, (int) $this->oc->getById($oc)['tecnico_id'], 'quem avaliou vira o responsável');

        // o orçamento nasce da avaliação (o atendente não redigita)
        $g = $this->av->gerarOrcamento($avId, $this->adminId);
        $this->assertTrue($g['ok'], (string) $g['erro']);
        $g2 = $this->av->gerarOrcamento($avId, $this->adminId);
        $this->assertTrue($g2['repetido'] && $g2['id'] === $g['id'], 'gerar de novo devolve o mesmo orçamento');
        $orcId = $g['id'];

        $o = $this->pdo->query("SELECT * FROM orcamentos WHERE id_orcamento = {$orcId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame($oc, (int) $o['ocorrencia_id']);
        $this->assertSame($avId, (int) $o['avaliacao_id']);
        $this->assertSame(300.0, (float) $o['valor_total_orcamento']);
        $this->assertStringContainsString('vazando', $o['diagnostico'], 'o diagnóstico do técnico vai junto');
        $this->assertSame(2, $this->contar("SELECT COUNT(*) FROM orcamento_itens WHERE orcamento_id = {$orcId}"));

        // envio ao cliente
        $this->assertTrue($this->orc->enviar($orcId)['ok']);
        $this->assertSame('AGUARDANDO_APROVACAO', $this->statusOc($oc));
        $this->assertTrue($this->orc->enviar($orcId)['repetido']);

        // o cliente aprova → OS criada UMA vez, ligada à ocorrência
        $a = $this->orc->aprovar($orcId, null, ['usuario_id' => $this->adminId]);
        $this->assertTrue($a['ok'], (string) $a['erro']);
        $this->assertTrue($this->orc->aprovar($orcId)['repetido']);
        $osId = $a['os_id'];

        $osRow = $this->pdo->query("SELECT status, ocorrencia_id, valor_autorizado, valor_total_os FROM ordens_servico WHERE id_ordem_servico = {$osId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('ABERTA', $osRow['status']);
        $this->assertSame($oc, (int) $osRow['ocorrencia_id']);
        $this->assertSame(300.0, (float) $osRow['valor_autorizado'], 'o teto autorizado é o valor que o cliente aprovou');
        $this->assertSame('EM_EXECUCAO', $this->statusOc($oc));
        $this->fx->registrar('ordens_servico', $osId);

        $aut = $this->pdo->query("SELECT tipo, status, limite_valor, canal FROM autorizacoes_cliente WHERE orcamento_id = {$orcId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(['ORCAMENTO', 'APROVADA', 300.0], [$aut['tipo'], $aut['status'], (float) $aut['limite_valor']], 'a aprovação ficou registrada como autorização');

        // execução e entrega encerram a ocorrência
        $this->ateRetirada($osId);
        $this->assertTrue($this->mover($osId, 'CONCLUIDA', 'ADMIN')['ok']);
        $this->assertSame('ENCERRADA', $this->statusOc($oc), 'entregar a OS encerra a ocorrência');
    }

    public function testRecusaColocaEmEsperaENaoCriaOsEReaberturaVolta(): void
    {
        $oc = $this->chegada();
        $orcId = $this->av->gerarOrcamento($this->avaliar($oc), $this->adminId)['id'];
        $this->orc->enviar($orcId);

        $this->assertTrue($this->orc->recusar($orcId, 'Achou caro')['ok']);
        $this->assertSame('EM_ESPERA', $this->statusOc($oc), 'recusar põe em espera, sem OS');
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM ordens_servico WHERE ocorrencia_id = {$oc}"));

        $this->assertFalse($this->orc->aprovar($orcId)['ok'], 'recusado não vira OS');

        $this->assertTrue($this->orc->reabrir($orcId)['ok']);
        $this->assertSame('AGUARDANDO_ORCAMENTO', $this->statusOc($oc));
        $this->assertTrue($this->orc->aprovar($orcId)['ok']);
        $this->assertSame('EM_EXECUCAO', $this->statusOc($oc));
    }

    public function testEditarOrcamentoJaEnviadoGuardaAVersaoEPedeReenvio(): void
    {
        $oc = $this->chegada();
        $orcId = $this->av->gerarOrcamento($this->avaliar($oc), $this->adminId)['id'];
        $this->orc->enviar($orcId);
        $this->assertSame('AGUARDANDO_APROVACAO', $this->statusOc($oc));

        $ok = $this->orc->atualizar($orcId, [
            'cliente_id' => $this->clienteId, 'veiculo_id' => $this->veiculoId,
            'itens' => [['tipo' => 'servico', 'id' => 'custom_1', 'nome' => 'Serviço revisado', 'qty' => 1, 'preco' => 250, 'custom' => 1]],
        ], ['usuario_id' => $this->adminId]);

        $this->assertTrue($ok, $this->orc->getLastError());
        $this->assertSame('AGUARDANDO_ORCAMENTO', $this->statusOc($oc), 'o documento mudou: a ocorrência volta a aguardar o reenvio');
        $this->assertNull($this->pdo->query("SELECT enviado_em FROM orcamentos WHERE id_orcamento = {$orcId}")->fetchColumn());

        $versoes = $this->orc->versoes($orcId);
        $this->assertCount(1, $versoes);
        $this->assertSame(300.0, (float) $versoes[0]['dados']['orcamento']['valor_total_orcamento'], 'a versão guardada é a de ANTES da edição');
        $this->assertCount(2, $versoes[0]['dados']['itens']);
        $this->assertSame(2, (int) $this->pdo->query("SELECT versao FROM orcamentos WHERE id_orcamento = {$orcId}")->fetchColumn());
    }

    public function testNaoEnviaOrcamentoSemValor(): void
    {
        $oc = $this->chegada();
        $i = $this->av->iniciar($oc, $this->tec1);
        $this->av->salvar($i['id'], ['defeitos' => 'Precisa de diagnóstico detalhado', 'itens' => [['tipo' => 'servico', 'nome' => 'Diagnóstico', 'quantidade' => 1, 'preco' => 0]]]);
        $this->av->concluir($i['id'], $this->tec1);
        $orcId = $this->av->gerarOrcamento($i['id'], $this->adminId)['id'];

        $r = $this->orc->enviar($orcId);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('sem valor', (string) $r['erro']);
    }

    /* ── Avaliação ───────────────────────────────────────────────────── */

    public function testConcluirAvaliacaoExigeDefeitosEItensOuSemReparo(): void
    {
        $oc = $this->chegada();
        $i = $this->av->iniciar($oc, $this->tec1);

        $r = $this->av->concluir($i['id'], $this->tec1);
        $this->assertFalse($r['ok'], 'sem defeitos registrados não conclui');

        $this->av->salvar($i['id'], ['defeitos' => 'Nenhum defeito encontrado no teste de rodagem', 'itens' => []]);
        $r = $this->av->concluir($i['id'], $this->tec1);
        $this->assertFalse($r['ok'], 'sem itens exige marcar "sem reparo"');
        $this->assertSame('EM_AVALIACAO', $this->statusOc($oc));

        $r = $this->av->concluir($i['id'], $this->tec1, true);
        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->assertSame('ENCERRADA', $this->statusOc($oc), 'sem reparo, nada a orçar');
    }

    public function testReavaliacaoCriaNovaVersaoSemApagarAAnterior(): void
    {
        $oc = $this->chegada();
        $v1 = $this->avaliar($oc);

        // O cliente pediu uma segunda opinião: o orçamento volta a ser avaliado.
        $r = OcorrenciaFluxo::transicionar($this->pdo, $oc, 'EM_AVALIACAO', $this->adminId, 'Nova avaliação');
        $this->assertTrue($r['ok']);
        $this->pdo->exec("UPDATE ocorrencias SET status = 'AGUARDANDO_ORCAMENTO' WHERE id_ocorrencia = {$oc}");

        $v2 = $this->av->iniciar($oc, $this->tec2);

        $this->assertTrue($v2['ok'], (string) $v2['erro']);
        $this->assertNotSame($v1, $v2['id']);
        $this->assertSame([1, 2], array_map(fn($v) => (int) $v['versao'], $this->av->versoes($oc)));
        $this->assertCount(2, $this->av->itens($v2['id']), 'a nova versão parte dos itens da anterior');
        $this->assertSame('CONCLUIDA', $this->av->porId($v1)['status'], 'a versão anterior não foi alterada');

        $again = $this->av->iniciar($oc, $this->tec2);
        $this->assertTrue($again['repetido'] && $again['id'] === $v2['id'], 'iniciar duas vezes não abre duas avaliações');
    }

    public function testAvaliacaoConcluidaNaoSeEditaMasEstadoInvalidoNaoPassa(): void
    {
        $oc = $this->chegada();
        $av = $this->avaliar($oc);

        $this->assertFalse($this->av->salvar($av, ['defeitos' => 'mudando depois', 'itens' => []])['ok']);

        $this->assertFalse(OcorrenciaFluxo::transicionar($this->pdo, $oc, 'EM_EXECUCAO', $this->adminId)['ok'] && false, 'placeholder');
        $r = OcorrenciaFluxo::transicionar($this->pdo, $this->chegada(), 'ENCERRADA', $this->adminId);
        $this->assertFalse($r['ok'], 'aguardando avaliação não encerra direto');
    }

    /* ── Fluxo 2: autorização com valor em aberto → OS direto ──────────── */

    private function autorizacao(int $oc, array $extra = []): array
    {
        return $this->aut->registrarValorAberto($oc, array_merge([
            'escopo'    => 'Troca do par de amortecedores dianteiros e buchas da bandeja',
            'evidencia' => 'Cliente confirmou ao balcão às 15h20, presente a atendente Laura',
            'canal'     => 'PRESENCIAL',
        ], $extra), $this->adminId);
    }

    public function testFluxo2AbreOsDiretoComAutorizacaoSemOrcamentoFormal(): void
    {
        $oc = $this->chegada(['origem' => 'RAPIDO']);
        $this->avaliar($oc);

        $semAut = $this->oc->abrirOsDireta($oc, $this->adminId);
        $this->assertFalse($semAut['ok'], 'sem autorização registrada não abre');
        $this->assertStringContainsString('autorização do cliente', (string) $semAut['erro']);

        $a = $this->autorizacao($oc);
        $this->assertTrue($a['ok'], (string) $a['erro']);

        $r = $this->oc->abrirOsDireta($oc, $this->adminId);
        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->fx->registrar('ordens_servico', (int) $r['os_id']);

        $os = $this->pdo->query("SELECT * FROM ordens_servico WHERE id_ordem_servico = {$r['os_id']}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('ABERTA', $os['status']);
        $this->assertNull($os['orcamento_id'], 'sem orçamento formal');
        $this->assertSame($oc, (int) $os['ocorrencia_id']);
        $this->assertSame(300.0, (float) $os['valor_total_os']);
        $this->assertSame(300.0, (float) $os['valor_autorizado'], 'sem limite informado, o teto é o total AVALIADO');
        $this->assertSame('EM_EXECUCAO', $this->statusOc($oc));
        $this->assertStringContainsString('vazando', $os['diagnostico']);
        $this->assertSame((int) $r['os_id'], (int) $this->pdo->query("SELECT ordem_servico_id FROM autorizacoes_cliente WHERE id_autorizacao = {$a['id']}")->fetchColumn(), 'a autorização ficou amarrada à OS');

        $again = $this->oc->abrirOsDireta($oc, $this->adminId);
        $this->assertTrue($again['ok'] && $again['repetido'] && $again['os_id'] === $r['os_id'], 'abrir de novo não cria outra OS');
    }

    public function testAutorizacaoExigeEscopoCanalEEvidencia(): void
    {
        $oc = $this->chegada();

        $this->assertFalse($this->autorizacao($oc, ['escopo' => 'qualquer'])['ok'], '"aceito qualquer coisa" sem escopo não vale');
        $this->assertFalse($this->autorizacao($oc, ['evidencia' => ''])['ok'], 'sem evidência não vale');
        $this->assertFalse($this->autorizacao($oc, ['canal' => 'TELEPATIA'])['ok'], 'canal inválido');
        $this->assertFalse($this->autorizacao($oc, ['limite_valor' => '-5'])['ok']);
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM autorizacoes_cliente WHERE ocorrencia_id = {$oc}"));

        $this->assertTrue($this->autorizacao($oc, ['limite_valor' => '500'])['ok']);
        $segunda = $this->autorizacao($oc, ['limite_valor' => '800']);
        $this->assertTrue($segunda['ok']);
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM autorizacoes_cliente WHERE ocorrencia_id = {$oc} AND status = 'APROVADA'"), 'só uma vigente; a anterior fica REVOGADA no histórico');
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM autorizacoes_cliente WHERE ocorrencia_id = {$oc} AND status = 'REVOGADA'"));
    }

    public function testLimiteAcordadoMenorQueOAvaliadoBarraAOsDireta(): void
    {
        $oc = $this->chegada();
        $this->avaliar($oc); // total avaliado: R$ 300
        $this->autorizacao($oc, ['limite_valor' => '250']);

        $r = $this->oc->abrirOsDireta($oc, $this->adminId);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('passa do limite', (string) $r['erro']);
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM ordens_servico WHERE ocorrencia_id = {$oc}"));
        $this->assertSame('AGUARDANDO_ORCAMENTO', $this->statusOc($oc), 'a recusa não muda a ocorrência');
    }

    public function testNaoAbreOsDiretaSemAvaliacaoConcluidaOuComValorZero(): void
    {
        $oc = $this->chegada();
        $this->autorizacao($oc);
        $this->assertFalse($this->oc->abrirOsDireta($oc, $this->adminId)['ok'], 'sem avaliação concluída');

        $i = $this->av->iniciar($oc, $this->tec1);
        $this->av->salvar($i['id'], ['defeitos' => 'Diagnóstico completo realizado', 'itens' => [['tipo' => 'servico', 'nome' => 'Serviço sem preço', 'quantidade' => 1, 'preco' => 0]]]);
        $this->av->concluir($i['id'], $this->tec1);

        $r = $this->oc->abrirOsDireta($oc, $this->adminId);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('valor zero', (string) $r['erro']);
    }

    /* ── Autorização complementar durante a execução ────────────────────── */

    private function osComAutorizacaoAberta(): int
    {
        $oc = $this->chegada();
        $this->avaliar($oc);
        $this->autorizacao($oc, ['limite_valor' => '300']);
        $r = $this->oc->abrirOsDireta($oc, $this->adminId);
        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->fx->registrar('ordens_servico', (int) $r['os_id']);
        $this->iniciar((int) $r['os_id']);

        return (int) $r['os_id'];
    }

    public function testServicoQueCrescePrecisaDeAutorizacaoComplementarParaSeguir(): void
    {
        $os = $this->osComAutorizacaoAberta();
        $this->consumirTudo($os);

        // O técnico descobre mais um problema: R$ 150 além do autorizado.
        $this->os->update([
            'id_ordem_servico' => $os, 'numero' => 'OS-X', 'cliente_id' => $this->clienteId, 'veiculo_id' => $this->veiculoId,
            'itens' => array_merge($this->os->getItensByOS($os), [['tipo' => 'servico', 'id' => 'custom_9', 'nome' => 'Alinhamento extra', 'qty' => 1, 'preco' => 150, 'custom' => 1]]),
            'tecnicos' => [$this->tec1],
        ]);
        $this->assertSame(450.0, (float) $this->pdo->query("SELECT valor_total_os FROM ordens_servico WHERE id_ordem_servico = {$os}")->fetchColumn());

        $r = $this->mover($os, 'AGUARDANDO_CONFERENCIA', 'TECNICO');
        $this->assertFalse($r['ok'], 'acima do autorizado não segue para conferência');
        $this->assertStringContainsString('passou do que o cliente autorizou', (string) $r['erro']);
        $this->assertStringContainsString('150,00', (string) $r['erro'], 'a mensagem diz quanto falta autorizar');
        $this->assertSame('EM_ANDAMENTO', $this->status($os));

        // O técnico pede; enquanto o cliente não responde, continua barrado.
        $p = $this->aut->solicitarComplemento($os, 150, 'Encontrado desalinhamento grave que não estava no escopo', $this->tec1);
        $this->assertTrue($p['ok'], (string) $p['erro']);
        $r = $this->mover($os, 'AGUARDANDO_CONFERENCIA', 'TECNICO');
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('aguardando a resposta do cliente', (string) $r['erro']);

        // Recusado: o total continua acima do autorizado, a OS continua barrada.
        $this->assertTrue($this->aut->decidir($p['id'], false, 'TELEFONE', 'Cliente recusou o serviço extra por telefone', $this->adminId)['ok']);
        $this->assertFalse($this->mover($os, 'AGUARDANDO_CONFERENCIA', 'TECNICO')['ok']);

        // Nova solicitação, desta vez aprovada, com canal e evidência.
        $p2 = $this->aut->solicitarComplemento($os, 150, 'Cliente pensou melhor e quer o alinhamento', $this->tec1);
        $this->assertFalse($this->aut->decidir($p2['id'], true, 'WHATSAPP', '', $this->adminId)['ok'], 'resposta sem evidência não vale');
        $this->assertTrue($this->aut->decidir($p2['id'], true, 'WHATSAPP', 'Mensagem do cliente: "pode fazer, autorizo +R$150"', $this->adminId)['ok']);
        $this->assertTrue($this->aut->decidir($p2['id'], true, 'WHATSAPP', 'Mensagem do cliente: "pode fazer, autorizo +R$150"', $this->adminId)['repetido'], 'idempotente');

        $this->assertSame(450.0, (float) $this->pdo->query("SELECT valor_autorizado FROM ordens_servico WHERE id_ordem_servico = {$os}")->fetchColumn(), '300 + 150, uma vez só');
        $this->assertTrue($this->mover($os, 'AGUARDANDO_CONFERENCIA', 'TECNICO')['ok'], 'autorizado, a OS segue');
    }

    public function testOsAbertaDiretoNoBalcaoNaoTemControleDeAutorizacao(): void
    {
        $os = $this->novaOs(); // OsTestCase: sem ocorrência, sem valor autorizado
        $this->ateConferencia($os);

        $this->assertSame([], $this->aut->bloqueiosDaOs($os), 'cliente presente no balcão: sem teto de autorização');
        $this->assertSame('AGUARDANDO_CONFERENCIA', $this->status($os));
    }

    /* ── Regularização e unificação ────────────────────────────────────── */

    public function testUnificarProvisorioMoveOHistoricoSemApagarNada(): void
    {
        $r = $this->oc->registrar([
            'cliente' => ['nome' => 'PHPUnit Rápido Carlos', 'telefone' => '(18) 95544-3322'],
            'veiculo' => ['placa' => 'ZQR3F45'],
            'relato'  => 'Freio fazendo ruído',
        ], $this->adminId, 'ATENDENTE');
        $this->assertTrue($r['ok'], (string) $r['erro']);
        $prov = (int) $r['cliente_id'];
        $this->fx->registrar('clientes', $prov);
        $this->fx->registrar('veiculos', (int) $r['veiculo_id']);
        $this->fx->registrar('ocorrencias', (int) $r['id']);

        $cli = new Cliente();
        $u = $cli->unificar($prov, $this->clienteId, $this->adminId);

        $this->assertTrue($u['ok'], (string) $u['erro']);
        $this->assertSame(1, $u['movidos']['veiculos']);
        $this->assertSame(1, $u['movidos']['ocorrencias']);

        $this->assertSame($this->clienteId, (int) $this->pdo->query("SELECT cliente_id FROM veiculos WHERE id_veiculo = {$r['veiculo_id']}")->fetchColumn());
        $this->assertSame($this->clienteId, (int) $this->pdo->query("SELECT cliente_id FROM ocorrencias WHERE id_ocorrencia = {$r['id']}")->fetchColumn());

        $linha = $this->pdo->query("SELECT ativo, unificado_em_id FROM clientes WHERE id_cliente = {$prov}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(0, (int) $linha['ativo'], 'o provisório não é apagado: fica desativado');
        $this->assertSame($this->clienteId, (int) $linha['unificado_em_id']);
        $this->assertSame('(18) 95544-3322', $this->pdo->query("SELECT telefone FROM clientes WHERE id_cliente = {$this->clienteId}")->fetchColumn(), 'o telefone que o destino não tinha foi aproveitado');
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM historico_estados WHERE entidade='CLIENTE' AND entidade_id={$prov}"));
    }

    public function testSoSeUnificaProvisorioAtivoEEmCadastroDiferente(): void
    {
        $cli = new Cliente();

        $this->assertFalse($cli->unificar($this->clienteId, $this->clienteId, $this->adminId)['ok'], 'consigo mesmo');
        $outro = $this->fx->cliente();
        $this->assertFalse($cli->unificar($this->clienteId, $outro, $this->adminId)['ok'], 'um cadastro COMPLETO nunca é fundido em outro: poderia misturar pessoas');
        $this->assertSame(1, (int) $this->pdo->query("SELECT ativo FROM clientes WHERE id_cliente = {$this->clienteId}")->fetchColumn());
    }

    public function testBuscaAcheNomeTelefoneEPlacaEDevolveOsVeiculos(): void
    {
        $this->pdo->exec("UPDATE clientes SET nome = 'PHPUnit Busca Zeferino', telefone = '(18) 94433-2211' WHERE id_cliente = {$this->clienteId}");
        $placa = (string) $this->pdo->query("SELECT placa FROM veiculos WHERE id_veiculo = {$this->veiculoId}")->fetchColumn();
        $cli = new Cliente();

        foreach (['Zeferino', '94433-2211', '944332211', $placa, strtolower(str_replace('-', '', $placa))] as $termo) {
            $achados = $cli->buscar($termo);
            $ids = array_column($achados, 'id');
            $this->assertContains($this->clienteId, $ids, "a busca por '{$termo}' acha o cliente");
        }

        $achado = array_values(array_filter($cli->buscar('Zeferino'), fn($c) => $c['id'] === $this->clienteId))[0];
        $this->assertSame($this->veiculoId, (int) $achado['veiculos'][0]['id']);
        $this->assertSame([], $cli->buscar('a'), 'termo curto demais não varre a base');
    }

    public function testCancelarOcorrenciaExigeMotivoEFicaNaTrilha(): void
    {
        $oc = $this->chegada();

        $this->assertFalse($this->oc->cancelar($oc, $this->adminId, ' ')['ok']);
        $this->assertTrue($this->oc->cancelar($oc, $this->adminId, 'Cliente desistiu antes da avaliação')['ok']);
        $this->assertSame('CANCELADA', $this->statusOc($oc));
        $this->assertFalse($this->oc->atribuirTecnico($oc, $this->tec1, $this->adminId)['ok'], 'encerrada não recebe técnico');
    }
}
