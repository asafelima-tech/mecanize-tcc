<?php

use PHPUnit\Framework\TestCase;

/**
 * Cobre a agenda de serviços, com foco na detecção de sobreposição de
 * horários (Agendamento::temConflito) — é a regra que impede marcar dois
 * serviços em cima do mesmo horário e a única com aritmética não trivial.
 *
 * Roda contra mecanize_db_test (ver tests/bootstrap.php) — cria e remove
 * seus próprios registros, nunca toca dado de produção.
 */
final class AgendamentoTest extends TestCase
{
    private PDO $pdo;
    private int $clienteId;
    private int $mecanicoA;
    private int $mecanicoB;
    private Agendamento $agenda;

    /** Data fixa no futuro, isolada de "hoje". */
    private const DIA = '2032-05-10';

    protected function setUp(): void
    {
        $this->pdo = new Database();

        $this->pdo->exec("
            INSERT INTO clientes (tipo_pessoa, nome, ativo)
            VALUES ('PF', 'Cliente Agenda PHPUnit', 1)
        ");
        $this->clienteId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("
            INSERT INTO usuarios (nome, email, senha, tipo)
            VALUES ('Mecanico A PHPUnit', 'phpunit.meca@teste.local', 'x', 'TECNICO')
        ");
        $this->mecanicoA = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("
            INSERT INTO usuarios (nome, email, senha, tipo)
            VALUES ('Mecanico B PHPUnit', 'phpunit.mecb@teste.local', 'x', 'TECNICO')
        ");
        $this->mecanicoB = (int) $this->pdo->lastInsertId();

        require_once __DIR__ . '/../app/models/Agendamento.php';
        $this->agenda = new Agendamento();
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("DELETE FROM agendamentos WHERE cliente_id = {$this->clienteId}");
        $this->pdo->exec("DELETE FROM clientes WHERE id_cliente = {$this->clienteId}");
        $this->pdo->exec("DELETE FROM usuarios WHERE id_usuario IN ({$this->mecanicoA}, {$this->mecanicoB})");
    }

    /** Cria um agendamento de 60 min às $hora, opcionalmente com mecânico. */
    private function marcar(string $hora, int $duracao = 60, ?int $mecanico = null, string $status = 'AGENDADO'): int
    {
        return (int) $this->agenda->criar([
            'cliente_id'  => $this->clienteId,
            'mecanico_id' => $mecanico,
            'data_hora'   => self::DIA . ' ' . $hora . ':00',
            'duracao_min' => $duracao,
            'servico'     => 'Serviço de teste',
            'status'      => $status,
        ]);
    }

    public function testCriaELeDeVolta(): void
    {
        $id = $this->marcar('09:00');

        $this->assertGreaterThan(0, $id);

        $registro = $this->agenda->getById($id);
        $this->assertSame('Serviço de teste', $registro['servico']);
        $this->assertSame('AGENDADO', $registro['status']);
        $this->assertSame('Cliente Agenda PHPUnit', $registro['cliente_nome']);
        $this->assertSame(60, (int) $registro['duracao_min']);
    }

    public function testHorarioIdenticoConflita(): void
    {
        $this->marcar('09:00');

        $this->assertTrue($this->agenda->temConflito(self::DIA . ' 09:00:00', 60));
    }

    public function testSobreposicaoParcialConflita(): void
    {
        $this->marcar('09:00', 60); // ocupa 09:00–10:00

        $this->assertTrue(
            $this->agenda->temConflito(self::DIA . ' 09:30:00', 60),
            '09:30–10:30 invade o fim do horário das 09:00'
        );
        $this->assertTrue(
            $this->agenda->temConflito(self::DIA . ' 08:30:00', 60),
            '08:30–09:30 invade o começo do horário das 09:00'
        );
    }

    public function testHorarioQueEngolistaOutroConflita(): void
    {
        $this->marcar('09:00', 30); // ocupa 09:00–09:30

        $this->assertTrue(
            $this->agenda->temConflito(self::DIA . ' 08:00:00', 240),
            '08:00–12:00 contém inteiramente o horário das 09:00'
        );
    }

    public function testHorariosQueApenasSeEncostamNaoConflitam(): void
    {
        $this->marcar('09:00', 60); // ocupa 09:00–10:00

        $this->assertFalse(
            $this->agenda->temConflito(self::DIA . ' 10:00:00', 60),
            'um serviço que começa exatamente quando o outro acaba não é conflito'
        );
        $this->assertFalse(
            $this->agenda->temConflito(self::DIA . ' 08:00:00', 60),
            'um serviço que acaba exatamente quando o outro começa não é conflito'
        );
    }

    public function testHorarioLivreNaoConflita(): void
    {
        $this->marcar('09:00', 60);

        $this->assertFalse($this->agenda->temConflito(self::DIA . ' 14:00:00', 60));
        $this->assertFalse($this->agenda->temConflito('2032-05-11 09:00:00', 60), 'outro dia');
    }

    public function testAgendamentoCanceladoNaoOcupaAgenda(): void
    {
        $this->marcar('09:00', 60, null, 'CANCELADO');

        $this->assertFalse(
            $this->agenda->temConflito(self::DIA . ' 09:00:00', 60),
            'horário cancelado tem que liberar a vaga'
        );
    }

    public function testConflitoPorMecanicoSoOlhaAAgendaDaquelePrestador(): void
    {
        $this->marcar('09:00', 60, $this->mecanicoA);

        $this->assertTrue(
            $this->agenda->temConflito(self::DIA . ' 09:00:00', 60, $this->mecanicoA),
            'o mecânico A já está ocupado nesse horário'
        );
        $this->assertFalse(
            $this->agenda->temConflito(self::DIA . ' 09:00:00', 60, $this->mecanicoB),
            'o mecânico B está livre — dois serviços em paralelo são válidos'
        );
    }

    public function testEdicaoNaoConflitaConsigoMesma(): void
    {
        $id = $this->marcar('09:00', 60);

        $this->assertTrue(
            $this->agenda->temConflito(self::DIA . ' 09:00:00', 60),
            'sem ignorar, o próprio registro aparece como conflito'
        );
        $this->assertFalse(
            $this->agenda->temConflito(self::DIA . ' 09:00:00', 60, null, $id),
            'ao editar, o agendamento não pode conflitar consigo mesmo'
        );
    }

    public function testAtualizarStatusRejeitaValorDesconhecido(): void
    {
        $id = $this->marcar('09:00');

        $this->assertFalse($this->agenda->atualizarStatus($id, 'QUALQUER_COISA'));
        $this->assertSame('AGENDADO', $this->agenda->getById($id)['status']);

        $this->assertTrue($this->agenda->atualizarStatus($id, 'CONFIRMADO'));
        $this->assertSame('CONFIRMADO', $this->agenda->getById($id)['status']);
    }

    public function testVincularOrdemServicoSoAconteceUmaVez(): void
    {
        $id = $this->marcar('09:00');

        // Uma OS de verdade, pra respeitar a foreign key. ordens_servico exige
        // veiculo_id, e veiculos exige marca/modelo — daí a cadeia de fixtures.
        $this->pdo->exec("INSERT INTO marcas_veiculos (nome) VALUES ('Marca PHPUnit')");
        $marcaId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO modelos_veiculos (marca_id, nome) VALUES ({$marcaId}, 'Modelo PHPUnit')");
        $modeloId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("
            INSERT INTO veiculos (cliente_id, marca_id, modelo_id, placa, placa_normalizada)
            VALUES ({$this->clienteId}, {$marcaId}, {$modeloId}, 'TST-0A11', 'TST0A11')
        ");
        $veiculoId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("
            INSERT INTO ordens_servico (numero, status, cliente_id, veiculo_id, data_abertura)
            VALUES ('OS-PHPUNIT-1', 'ABERTA', {$this->clienteId}, {$veiculoId}, NOW())
        ");
        $osId = (int) $this->pdo->lastInsertId();

        $this->assertTrue($this->agenda->vincularOrdemServico($id, $osId));

        $registro = $this->agenda->getById($id);
        $this->assertSame($osId, (int) $registro['ordem_servico_id']);
        $this->assertSame('CONCLUIDO', $registro['status'], 'converter em OS conclui o agendamento');

        // Segundo clique no mesmo botão não pode gerar outra conversão.
        $this->assertFalse(
            $this->agenda->vincularOrdemServico($id, $osId),
            'agendamento já convertido não pode ser vinculado de novo'
        );

        // Limpeza na ordem inversa das dependências (o tearDown genérico não
        // conhece esses registros extras).
        $this->pdo->exec("UPDATE agendamentos SET ordem_servico_id = NULL WHERE id_agendamento = {$id}");
        $this->pdo->exec("DELETE FROM ordens_servico WHERE id_ordem_servico = {$osId}");
        $this->pdo->exec("DELETE FROM veiculos WHERE id_veiculo = {$veiculoId}");
        $this->pdo->exec("DELETE FROM modelos_veiculos WHERE id_modelo = {$modeloId}");
        $this->pdo->exec("DELETE FROM marcas_veiculos WHERE id_marca = {$marcaId}");
    }

    public function testPorPeriodoRespeitaOIntervaloEOsFiltros(): void
    {
        $this->marcar('09:00', 60, $this->mecanicoA);
        $this->marcar('14:00', 60, $this->mecanicoB, 'CONFIRMADO');

        $doDia = $this->agenda->porPeriodo(self::DIA, self::DIA);
        $this->assertCount(2, $doDia);
        $this->assertSame(self::DIA . ' 09:00:00', $doDia[0]['data_hora'], 'ordenado por horário');

        $this->assertCount(
            1,
            $this->agenda->porPeriodo(self::DIA, self::DIA, ['status' => 'CONFIRMADO']),
            'filtro por status'
        );
        $this->assertCount(
            1,
            $this->agenda->porPeriodo(self::DIA, self::DIA, ['mecanico_id' => $this->mecanicoA]),
            'filtro por mecânico'
        );
        $this->assertCount(
            0,
            $this->agenda->porPeriodo('2032-06-01', '2032-06-30'),
            'mês sem agendamento'
        );
    }

    public function testResumoPeriodoContaPorStatus(): void
    {
        $this->marcar('09:00', 60, null, 'AGENDADO');
        $this->marcar('11:00', 60, null, 'CONFIRMADO');
        $this->marcar('14:00', 60, null, 'CANCELADO');

        $resumo = $this->agenda->resumoPeriodo(self::DIA, self::DIA);

        $this->assertSame(1, $resumo['AGENDADO']);
        $this->assertSame(1, $resumo['CONFIRMADO']);
        $this->assertSame(1, $resumo['CANCELADO']);
        $this->assertSame(0, $resumo['FALTOU']);
        $this->assertSame(3, $resumo['TOTAL']);
    }
}
