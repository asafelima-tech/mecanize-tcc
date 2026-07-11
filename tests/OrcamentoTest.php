<?php

use PHPUnit\Framework\TestCase;

/**
 * Cobre duas correções de 2026-07-10 em Orcamento.php:
 *  - aprovar() chamado duas vezes pro mesmo orçamento não pode criar duas OS
 *    (guarda de "já existe OS" + índice único uq_ordens_servico_orcamento_id).
 *  - recusar() só pode sair de 'pendente' — um orçamento já aprovado não pode
 *    virar 'reprovado' (duplo clique / reenvio de formulário).
 *
 * Não há FK enforçada no schema (confirmado na auditoria), então o fixture
 * pode usar cliente_id/veiculo_id fictícios sem precisar de linhas reais em
 * clientes/veiculos — o que importa aqui é o controle de status/duplicidade,
 * não a integridade referencial.
 */
final class OrcamentoTest extends TestCase
{
    private PDO $pdo;
    private int $clienteId;
    private int $veiculoId;
    private int $orcamentoId;

    protected function setUp(): void
    {
        $this->pdo = new Database();

        // orcamentos.cliente_id/veiculo_id têm FK de verdade (diferente de
        // clientes/produtos, que não têm) — precisa de linhas reais.
        $this->pdo->exec("INSERT INTO clientes (tipo_pessoa, nome, ativo) VALUES ('FISICA', 'Cliente Teste PHPUnit', 1)");
        $this->clienteId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("
            INSERT INTO veiculos (cliente_id, marca_id, modelo_id, placa, placa_normalizada)
            VALUES ({$this->clienteId}, 1, 1, 'PHU-0001', 'PHU0001')
        ");
        $this->veiculoId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("
            INSERT INTO orcamentos (
                numero, cliente_id, veiculo_id, status,
                valor_total_pecas, valor_total_servicos, valor_total_orcamento
            ) VALUES (
                'ORC-PHPUNIT', {$this->clienteId}, {$this->veiculoId}, 'pendente',
                100.00, 200.00, 300.00
            )
        ");
        $this->orcamentoId = (int) $this->pdo->lastInsertId();

        require_once __DIR__ . '/../app/models/Orcamento.php';
        require_once __DIR__ . '/../app/models/OrdemServico.php';
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("DELETE FROM ordens_servico WHERE orcamento_id = {$this->orcamentoId}");
        $this->pdo->exec("DELETE FROM orcamentos WHERE id_orcamento = {$this->orcamentoId}");
        $this->pdo->exec("DELETE FROM veiculos WHERE id_veiculo = {$this->veiculoId}");
        $this->pdo->exec("DELETE FROM clientes WHERE id_cliente = {$this->clienteId}");
    }

    public function testAprovarDuasVezesNaoCriaDuasOS(): void
    {
        $orcamento = new Orcamento();

        $ok1 = $orcamento->aprovar($this->orcamentoId);
        $this->assertTrue($ok1, 'primeira aprovação deveria funcionar');

        $ok2 = $orcamento->aprovar($this->orcamentoId);
        $this->assertTrue($ok2, 'segunda chamada não deveria falhar, só não criar OS nova');

        $total = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM ordens_servico WHERE orcamento_id = {$this->orcamentoId}"
        )->fetchColumn();

        $this->assertSame(1, $total, 'aprovar() duas vezes não pode criar duas Ordens de Serviço (bug corrigido em 2026-07-10)');
    }

    public function testRecusarFuncionaQuandoPendente(): void
    {
        $orcamento = new Orcamento();

        $ok = $orcamento->recusar($this->orcamentoId, 'Cliente desistiu');
        $this->assertTrue($ok);

        $status = $this->pdo->query(
            "SELECT status FROM orcamentos WHERE id_orcamento = {$this->orcamentoId}"
        )->fetchColumn();
        $this->assertSame('reprovado', $status);
    }

    public function testRecusarFalhaSeJaAprovado(): void
    {
        $orcamento = new Orcamento();

        $orcamento->aprovar($this->orcamentoId);

        $ok = $orcamento->recusar($this->orcamentoId, 'Tentativa tardia de recusar');

        $this->assertFalse($ok, 'não pode recusar um orçamento já aprovado (bug corrigido em 2026-07-10)');

        $status = $this->pdo->query(
            "SELECT status FROM orcamentos WHERE id_orcamento = {$this->orcamentoId}"
        )->fetchColumn();
        $this->assertSame('aprovado', $status, 'status deveria continuar aprovado, não virar reprovado');
    }
}
