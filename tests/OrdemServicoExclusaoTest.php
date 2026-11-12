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
 * Roda contra mecanize_db_test (ver tests/bootstrap.php). Os dados vêm de
 * Fixtures (cliente → veículo → peça → OS coerentes): as foreign keys da
 * Etapa 1 recusam as fixtures "soltas" que este teste usava antes.
 */
final class OrdemServicoExclusaoTest extends TestCase
{
    private PDO $pdo;
    private OrdemServico $model;
    private Fixtures $fx;

    protected function setUp(): void
    {
        $this->pdo = new Database();
        $this->fx  = new Fixtures($this->pdo);

        require_once __DIR__ . '/../app/models/OrdemServico.php';
        $this->model = new OrdemServico();
    }

    protected function tearDown(): void
    {
        $this->fx->limpar();
    }

    /** @return array{0:int,1:int,2:int} [cliente, veículo, OS] */
    private function novaOs(): array
    {
        $cliente = $this->fx->cliente();
        $veiculo = $this->fx->veiculo($cliente);
        $os      = $this->fx->os($cliente, $veiculo, ['valor_total_os' => 250.00]);

        return [$cliente, $veiculo, $os];
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

    private function criarAgendamento(int $cliente, int $os): void
    {
        $this->pdo->exec("
            INSERT INTO agendamentos (cliente_id, data_hora, servico, ordem_servico_id, status)
            VALUES ({$cliente}, NOW(), 'Revisão', {$os}, 'AGENDADO')
        ");
        $this->fx->registrar('agendamentos', (int) $this->pdo->lastInsertId());
    }

    public function testExcluirOsRemoveOsItensJuntoEmVezDeDeixarOrfaos(): void
    {
        [, , $os] = $this->novaOs();
        $peca     = $this->fx->produto();

        $this->fx->itemPeca($os, $peca, 2, 50.00);
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
        [$cliente, , $os] = $this->novaOs();

        $this->criarAgendamento($cliente, $os);

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
        [$cliente, , $os] = $this->novaOs();

        $this->criarAgendamento($cliente, $os);

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
        [, , $os] = $this->novaOs();
        $peca     = $this->fx->produto();

        $this->fx->itemPeca($os, $peca, 1, 25.00);
        $this->pdo->exec("INSERT INTO ordem_servico_mao_obra (ordem_servico_id, tecnico_id, descricao, valor_mao_obra) VALUES ({$os}, NULL, 'Mão de obra', 40.00)");

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
        [$cliente, , $os] = $this->novaOs();
        $peca             = $this->fx->produto();

        $this->fx->itemPeca($os, $peca, 1, 30.00);
        $this->criarAgendamento($cliente, $os);

        $this->assertFalse($this->model->delete($os));

        $this->assertSame(1, $this->contarItens($os), 'rollback: o item continua lá');

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM ordens_servico WHERE id_ordem_servico = ?');
        $stmt->execute([$os]);
        $this->assertSame(1, (int) $stmt->fetchColumn(), 'rollback: a OS continua lá');
    }
}
