<?php

use PHPUnit\Framework\TestCase;

/**
 * Cobre a exclusão de ordem de serviço, que tinha dois defeitos:
 *
 *   1. As tabelas de itens (produtos, serviços, mão de obra, anexos) não têm
 *      foreign key para `ordens_servico`. O DELETE apagava só a OS e deixava
 *      os itens para trás, apontando para um id inexistente — sem erro e sem
 *      aviso.
 *   2. `agendamentos` TEM foreign key RESTRICT. Excluir uma OS agendada
 *      estourava PDOException não tratada e o usuário via uma tela 500.
 *
 * Roda contra mecanize_db_test (ver tests/bootstrap.php).
 */
final class OrdemServicoExclusaoTest extends TestCase
{
    private PDO $pdo;
    private OrdemServico $model;

    /** @var int[] ids criados no teste, removidos no tearDown */
    private array $ordens       = [];
    private array $agendamentos = [];
    private array $clientes     = [];
    private array $veiculos     = [];
    private array $modelos      = [];
    private array $marcas       = [];

    protected function setUp(): void
    {
        $this->pdo = new Database();

        require_once __DIR__ . '/../app/models/OrdemServico.php';
        $this->model = new OrdemServico();
    }

    protected function tearDown(): void
    {
        foreach ($this->agendamentos as $id) {
            $this->pdo->exec("DELETE FROM agendamentos WHERE id_agendamento = {$id}");
        }

        foreach ($this->ordens as $id) {
            foreach (['ordem_servico_produtos', 'ordem_servico_servicos', 'ordem_servico_mao_obra', 'anexos_ordem_servico'] as $t) {
                $this->pdo->exec("DELETE FROM {$t} WHERE ordem_servico_id = {$id}");
            }
            $this->pdo->exec("DELETE FROM ordens_servico WHERE id_ordem_servico = {$id}");
        }

        // Ordem inversa da criação: veículo depende de marca/modelo/cliente, e
        // a OS depende do veículo — apagar fora de ordem esbarra nas FKs.
        foreach ($this->veiculos as $id) {
            $this->pdo->exec("DELETE FROM veiculos WHERE id_veiculo = {$id}");
        }

        foreach ($this->modelos as $id) {
            $this->pdo->exec("DELETE FROM modelos_veiculos WHERE id_modelo = {$id}");
        }

        foreach ($this->marcas as $id) {
            $this->pdo->exec("DELETE FROM marcas_veiculos WHERE id_marca = {$id}");
        }

        foreach ($this->clientes as $id) {
            $this->pdo->exec("DELETE FROM clientes WHERE id_cliente = {$id}");
        }
    }

    private function criarCliente(): int
    {
        $this->pdo->exec("
            INSERT INTO clientes (tipo_pessoa, nome, ativo)
            VALUES ('PF', 'Cliente OS PHPUnit', 1)
        ");
        $id = (int) $this->pdo->lastInsertId();
        $this->clientes[] = $id;

        return $id;
    }

    /**
     * ordens_servico exige veiculo_id, e veiculos exige marca/modelo — daí a
     * cadeia de fixtures (mesmo encadeamento usado em AgendamentoTest).
     */
    private function criarVeiculo(int $clienteId): int
    {
        $this->pdo->exec("INSERT INTO marcas_veiculos (nome) VALUES ('Marca OS PHPUnit')");
        $marcaId = (int) $this->pdo->lastInsertId();
        $this->marcas[] = $marcaId;

        $this->pdo->exec("INSERT INTO modelos_veiculos (marca_id, nome) VALUES ({$marcaId}, 'Modelo OS PHPUnit')");
        $modeloId = (int) $this->pdo->lastInsertId();
        $this->modelos[] = $modeloId;

        // Placa única por veículo criado: placa_normalizada tem índice único.
        $sufixo = str_pad((string) count($this->veiculos), 3, '0', STR_PAD_LEFT);

        $this->pdo->exec("
            INSERT INTO veiculos (cliente_id, marca_id, modelo_id, placa, placa_normalizada)
            VALUES ({$clienteId}, {$marcaId}, {$modeloId}, 'OS-{$sufixo}A', 'OS{$sufixo}A')
        ");
        $id = (int) $this->pdo->lastInsertId();
        $this->veiculos[] = $id;

        return $id;
    }

    private function criarOs(int $clienteId): int
    {
        $numero    = 'PHPUNIT-' . uniqid();
        $veiculoId = $this->criarVeiculo($clienteId);

        $this->pdo->exec("
            INSERT INTO ordens_servico (numero, cliente_id, veiculo_id, status, valor_total_os)
            VALUES ('{$numero}', {$clienteId}, {$veiculoId}, 'ABERTA', 250.00)
        ");
        $id = (int) $this->pdo->lastInsertId();
        $this->ordens[] = $id;

        return $id;
    }

    private function contarItens(int $osId): int
    {
        $total = 0;

        foreach (['ordem_servico_produtos', 'ordem_servico_servicos', 'ordem_servico_mao_obra'] as $t) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$t} WHERE ordem_servico_id = ?");
            $stmt->execute([$osId]);
            $total += (int) $stmt->fetchColumn();
        }

        return $total;
    }

    public function testExcluirOsRemoveOsItensJuntoEmVezDeDeixarOrfaos(): void
    {
        $cliente = $this->criarCliente();
        $os      = $this->criarOs($cliente);

        $this->pdo->exec("INSERT INTO ordem_servico_produtos (ordem_servico_id, produto_id, quantidade, valor_unitario) VALUES ({$os}, 1, 2, 50.00)");
        $this->pdo->exec("INSERT INTO ordem_servico_servicos (ordem_servico_id, nome, quantidade, valor_unitario) VALUES ({$os}, 'Alinhamento', 1, 80.00)");

        $this->assertSame(2, $this->contarItens($os), 'pré-condição: a OS tem itens lançados');

        $this->assertTrue($this->model->delete($os));

        $this->assertSame(0, $this->contarItens($os), 'os itens da OS não podem sobreviver à exclusão dela');

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM ordens_servico WHERE id_ordem_servico = ?');
        $stmt->execute([$os]);
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    public function testOsComAgendamentoEhBarradaPorVinculosEmVezDeEstourarErro(): void
    {
        $cliente = $this->criarCliente();
        $os      = $this->criarOs($cliente);

        $this->pdo->exec("
            INSERT INTO agendamentos (cliente_id, data_hora, servico, ordem_servico_id, status)
            VALUES ({$cliente}, NOW(), 'Revisão', {$os}, 'AGENDADO')
        ");
        $this->agendamentos[] = (int) $this->pdo->lastInsertId();

        $vinculos = $this->model->vinculos($os);

        $this->assertNotEmpty($vinculos, 'o agendamento vinculado tem que aparecer como impedimento');
        $this->assertStringContainsString('agendamento', implode(', ', $vinculos));
    }

    /**
     * Se a checagem de vínculos for ignorada, delete() ainda assim não pode
     * propagar PDOException — é isso que virava tela 500.
     */
    public function testDeleteNaoPropagaExcecaoQuandoAForeignKeyBarra(): void
    {
        $cliente = $this->criarCliente();
        $os      = $this->criarOs($cliente);

        $this->pdo->exec("
            INSERT INTO agendamentos (cliente_id, data_hora, servico, ordem_servico_id, status)
            VALUES ({$cliente}, NOW(), 'Revisão', {$os}, 'AGENDADO')
        ");
        $this->agendamentos[] = (int) $this->pdo->lastInsertId();

        $this->assertFalse($this->model->delete($os), 'delete() devolve false em vez de estourar');
    }

    /**
     * O mesmo, uma camada abaixo: um DELETE cru — phpMyAdmin, script de
     * importação, qualquer coisa que não passe pelo model — também não pode
     * deixar itens órfãos. Quem garante isso são as foreign keys ON DELETE
     * CASCADE criadas por database/add_fk_itens_ordem_servico.php.
     *
     * Este teste falha numa instalação onde a migração não foi rodada, e é
     * essa a intenção: o schema faz parte da correção.
     */
    public function testDeleteCruNoBancoTambemRemoveOsItensEmCascata(): void
    {
        $cliente = $this->criarCliente();
        $os      = $this->criarOs($cliente);

        $this->pdo->exec("INSERT INTO ordem_servico_produtos (ordem_servico_id, produto_id, quantidade, valor_unitario) VALUES ({$os}, 1, 1, 25.00)");
        $this->pdo->exec("INSERT INTO ordem_servico_mao_obra (ordem_servico_id, tecnico_id, descricao, valor_mao_obra) VALUES ({$os}, NULL, 'Mão de obra PHPUnit', 40.00)");

        $this->assertSame(2, $this->contarItens($os), 'pré-condição: a OS tem itens');

        // Sem passar pelo model: é o banco que tem que limpar.
        $this->pdo->exec("DELETE FROM ordens_servico WHERE id_ordem_servico = {$os}");

        $this->assertSame(
            0,
            $this->contarItens($os),
            'as foreign keys ON DELETE CASCADE não estão no banco — rode database/add_fk_itens_ordem_servico.php'
        );
    }

    /**
     * A transação não pode deixar a OS apagada e os itens vivos (nem o
     * contrário) quando a exclusão é barrada no meio do caminho.
     */
    public function testExclusaoBarradaNaoApagaOsItensPelaMetade(): void
    {
        $cliente = $this->criarCliente();
        $os      = $this->criarOs($cliente);

        $this->pdo->exec("INSERT INTO ordem_servico_produtos (ordem_servico_id, produto_id, quantidade, valor_unitario) VALUES ({$os}, 1, 1, 30.00)");
        $this->pdo->exec("
            INSERT INTO agendamentos (cliente_id, data_hora, servico, ordem_servico_id, status)
            VALUES ({$cliente}, NOW(), 'Revisão', {$os}, 'AGENDADO')
        ");
        $this->agendamentos[] = (int) $this->pdo->lastInsertId();

        $this->assertFalse($this->model->delete($os));

        $this->assertSame(1, $this->contarItens($os), 'rollback: o item continua lá');

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM ordens_servico WHERE id_ordem_servico = ?');
        $stmt->execute([$os]);
        $this->assertSame(1, (int) $stmt->fetchColumn(), 'rollback: a OS continua lá');
    }
}
