<?php

use PHPUnit\Framework\TestCase;

/**
 * Ciclo de vida do orçamento (Orcamento): salvar → aprovar/recusar/reabrir.
 *
 * O que estes testes travam, e por quê:
 *  - aprovar duas vezes NÃO cria duas OS (idempotência, 10/07);
 *  - só "pendente" aprova ou recusa: orçamento recusado/cancelado não vira OS
 *    por engano, e orçamento aprovado não vira "recusado" com uma OS viva atrás;
 *  - a OS criada nasce ABERTA, com o técnico do orçamento, e NÃO baixa estoque;
 *  - os valores são calculados no servidor a partir dos itens, nunca do que o
 *    navegador mandou;
 *  - editar grava os itens (antes eles eram ignorados) e só vale enquanto pendente.
 *
 * Roda contra mecanize_db_test (ver tests/bootstrap.php); tudo o que cria é removido.
 */
final class OrcamentoTest extends TestCase
{
    private PDO $pdo;
    private Fixtures $fx;
    private Orcamento $orcamento;
    private int $clienteId;
    private int $veiculoId;
    private int $produtoId;
    private int $tecnicoId;

    protected function setUp(): void
    {
        $this->pdo = new Database();
        $this->fx  = new Fixtures($this->pdo);

        require_once __DIR__ . '/../app/models/Orcamento.php';
        require_once __DIR__ . '/../app/models/OrdemServico.php';

        $this->clienteId = $this->fx->cliente();
        $this->veiculoId = $this->fx->veiculo($this->clienteId);
        $this->produtoId = $this->fx->produto(10, 100);
        $this->tecnicoId = $this->fx->usuario('TECNICO');
        $this->orcamento = new Orcamento();
    }

    protected function tearDown(): void
    {
        // Registra o que o model criou (orçamentos e OS) para a limpeza ordenada.
        foreach ($this->pdo->query("SELECT id_orcamento FROM orcamentos WHERE cliente_id = {$this->clienteId}")->fetchAll(PDO::FETCH_COLUMN) as $id) {
            foreach ($this->pdo->query("SELECT id_ordem_servico FROM ordens_servico WHERE orcamento_id = {$id}")->fetchAll(PDO::FETCH_COLUMN) as $os) {
                $this->fx->registrar('ordens_servico', (int) $os);
            }
            $this->pdo->exec("DELETE FROM historico_estados WHERE entidade = 'ORCAMENTO' AND entidade_id = {$id}");
            $this->fx->registrar('orcamentos', (int) $id);
        }

        foreach ($this->pdo->query("SELECT id_ordem_servico FROM ordens_servico WHERE cliente_id = {$this->clienteId}")->fetchAll(PDO::FETCH_COLUMN) as $os) {
            $this->fx->registrar('ordens_servico', (int) $os);
        }

        $this->pdo->exec("DELETE oi FROM orcamento_itens oi JOIN orcamentos o ON o.id_orcamento = oi.orcamento_id WHERE o.cliente_id = {$this->clienteId}");
        $this->fx->limpar();
    }

    /** Orçamento de R$ 300: 1 peça de R$ 100 + 1 serviço de R$ 200. */
    private function novoOrcamento(array $extra = []): int
    {
        $id = $this->orcamento->salvar(array_merge([
            'cliente_id'  => $this->clienteId,
            'veiculo_id'  => $this->veiculoId,
            'mecanico_id' => $this->tecnicoId,
            'itens'       => [
                ['tipo' => 'produto', 'id' => $this->produtoId, 'nome' => 'Peça', 'qty' => 1, 'preco' => 100, 'categoria' => 'x'],
                ['tipo' => 'servico', 'id' => 'custom_1', 'nome' => 'Mão de obra', 'qty' => 1, 'preco' => 200, 'custom' => 1, 'categoria' => 'Geral'],
            ],
        ], $extra));

        $this->assertNotFalse($id, 'o orçamento de teste deveria ser salvo: ' . $this->orcamento->getLastError());

        return (int) $id;
    }

    private function status(int $id): string
    {
        return (string) $this->pdo->query("SELECT status FROM orcamentos WHERE id_orcamento = {$id}")->fetchColumn();
    }

    private function osDe(int $orcamentoId): array
    {
        return $this->pdo->query("SELECT * FROM ordens_servico WHERE orcamento_id = {$orcamentoId}")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function testAprovarDuasVezesNaoCriaDuasOS(): void
    {
        $id = $this->novoOrcamento();

        $r1 = $this->orcamento->aprovar($id);
        $this->assertTrue($r1['ok'], 'primeira aprovação deveria funcionar: ' . ($r1['erro'] ?? ''));
        $this->assertFalse($r1['repetido']);

        $r2 = $this->orcamento->aprovar($id);
        $this->assertTrue($r2['ok'], 'a segunda chamada não falha, só não cria OS nova');
        $this->assertTrue($r2['repetido'], 'a segunda chamada tem que se declarar repetida');
        $this->assertSame($r1['os_id'], $r2['os_id']);

        $this->assertCount(1, $this->osDe($id), 'aprovar() duas vezes não pode criar duas Ordens de Serviço');
    }

    public function testOsNasceAbertaComTecnicoSemBaixarEstoque(): void
    {
        $id = $this->novoOrcamento();

        $r = $this->orcamento->aprovar($id);
        $this->assertTrue($r['ok'], (string) $r['erro']);

        $os = $this->pdo->query("SELECT * FROM ordens_servico WHERE id_ordem_servico = {$r['os_id']}")->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('ABERTA', $os['status'], 'a OS nasce "aguardando execução", não "em andamento"');
        $this->assertSame(300.0, (float) $os['valor_total_os'], 'o valor da OS é o que o cliente aprovou');

        $tecnicos = $this->pdo->query("SELECT tecnico_id FROM ordem_servico_mao_obra WHERE ordem_servico_id = {$r['os_id']} AND tecnico_id IS NOT NULL")
            ->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame([$this->tecnicoId], array_map('intval', $tecnicos), 'o técnico do orçamento tem que ir para a OS (a OS perdia o técnico)');

        $this->assertSame(10.0, $this->fx->estoqueDe($this->produtoId), 'aprovar um orçamento NÃO baixa estoque');

        $statusPeca = $this->pdo->query("SELECT status FROM ordem_servico_produtos WHERE ordem_servico_id = {$r['os_id']}")->fetchColumn();
        $this->assertSame('PLANEJADO', $statusPeca, 'a peça entra prevista; só baixa quando o consumo é confirmado');
    }

    public function testRecusarFuncionaQuandoPendente(): void
    {
        $id = $this->novoOrcamento();

        $r = $this->orcamento->recusar($id, 'Cliente desistiu');

        $this->assertTrue($r['ok']);
        $this->assertSame('reprovado', $this->status($id));
        $this->assertCount(0, $this->osDe($id), 'recusar não gera OS');
    }

    public function testRecusarFalhaSeJaAprovado(): void
    {
        $id = $this->novoOrcamento();
        $this->orcamento->aprovar($id);

        $r = $this->orcamento->recusar($id, 'Tentativa tardia de recusar');

        $this->assertFalse($r['ok'], 'não pode recusar um orçamento já aprovado');
        $this->assertSame('aprovado', $this->status($id), 'continua aprovado, não vira reprovado com uma OS viva atrás');
    }

    public function testOrcamentoRecusadoNaoPodeSerAprovadoMasPodeSerReaberto(): void
    {
        $id = $this->novoOrcamento();
        $this->orcamento->recusar($id, 'Achou caro');

        $r = $this->orcamento->aprovar($id);

        $this->assertFalse($r['ok'], 'um orçamento recusado não vira OS por engano');
        $this->assertStringContainsString('Reabra', (string) $r['erro'], 'a mensagem diz o caminho: reabrir');
        $this->assertCount(0, $this->osDe($id));
        $this->assertSame('reprovado', $this->status($id));

        $this->assertTrue($this->orcamento->reabrir($id)['ok']);
        $this->assertSame('pendente', $this->status($id));

        $this->assertTrue($this->orcamento->aprovar($id)['ok'], 'reaberto, pode ser aprovado');
        $this->assertCount(1, $this->osDe($id));
    }

    public function testNaoAprovaOrcamentoVencido(): void
    {
        $id = $this->novoOrcamento(['data_validade' => date('Y-m-d', strtotime('-3 days'))]);

        $r = $this->orcamento->aprovar($id);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('venceu', (string) $r['erro']);
        $this->assertCount(0, $this->osDe($id));
        $this->assertSame('pendente', $this->status($id), 'a recusa não muda o estado do orçamento');
    }

    public function testNaoAprovaOrcamentoSemItens(): void
    {
        $id = $this->novoOrcamento(['itens' => []]);

        $r = $this->orcamento->aprovar($id);

        $this->assertFalse($r['ok']);
        $this->assertCount(0, $this->osDe($id));
    }

    public function testTotaisSaoCalculadosNoServidorEIgnoramOQueOFormularioMandou(): void
    {
        $id = $this->novoOrcamento([
            // O navegador (ou quem adultera a requisição) tenta impor valores.
            'valor_total_pecas'     => '1',
            'valor_total_servicos'  => '1',
            'valor_total_orcamento' => '1',
        ]);

        $o = $this->pdo->query("SELECT * FROM orcamentos WHERE id_orcamento = {$id}")->fetch(PDO::FETCH_ASSOC);

        $this->assertSame(100.0, (float) $o['valor_total_pecas']);
        $this->assertSame(200.0, (float) $o['valor_total_servicos']);
        $this->assertSame(300.0, (float) $o['valor_total_orcamento']);
        $this->assertSame('ORC-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT), $o['numero'], 'a numeração é do servidor');
    }

    public function testSalvarComItemInvalidoNaoDeixaOrcamentoPelaMetade(): void
    {
        $antes = (int) $this->pdo->query("SELECT COUNT(*) FROM orcamentos WHERE cliente_id = {$this->clienteId}")->fetchColumn();

        $r = $this->orcamento->salvar([
            'cliente_id' => $this->clienteId,
            'veiculo_id' => $this->veiculoId,
            'itens'      => [['tipo' => 'servico', 'nome' => 'Ok', 'qty' => 1, 'preco' => 50], ['tipo' => 'servico', 'nome' => '', 'qty' => 1, 'preco' => 10]],
        ]);

        $this->assertFalse($r);
        $this->assertNotSame('', $this->orcamento->getLastError());

        $depois = (int) $this->pdo->query("SELECT COUNT(*) FROM orcamentos WHERE cliente_id = {$this->clienteId}")->fetchColumn();
        $this->assertSame($antes, $depois, 'cabeçalho e itens vão numa transação: falhou, não grava nada');
    }

    public function testEditarGravaOsItensERecalculaOsTotais(): void
    {
        $id = $this->novoOrcamento();

        $ok = $this->orcamento->atualizar($id, [
            'cliente_id' => $this->clienteId,
            'veiculo_id' => $this->veiculoId,
            'itens'      => [['tipo' => 'servico', 'id' => 'custom_9', 'nome' => 'Só um serviço', 'qty' => 3, 'preco' => 10, 'custom' => 1]],
        ]);

        $this->assertTrue($ok, $this->orcamento->getLastError());

        $itens = $this->pdo->query("SELECT nome, quantidade FROM orcamento_itens WHERE orcamento_id = {$id}")->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $itens, 'os itens enviados na edição são gravados (antes eram ignorados)');
        $this->assertSame('Só um serviço', $itens[0]['nome']);

        $total = (float) $this->pdo->query("SELECT valor_total_orcamento FROM orcamentos WHERE id_orcamento = {$id}")->fetchColumn();
        $this->assertSame(30.0, $total);
    }

    public function testEditarNaoAlteraOStatusEBloqueiaOrcamentoAprovado(): void
    {
        $id = $this->novoOrcamento();

        $this->orcamento->atualizar($id, [
            'cliente_id' => $this->clienteId, 'veiculo_id' => $this->veiculoId,
            'status' => 'aprovado', // tentativa de aprovar "na mão", sem criar OS
            'itens' => [['tipo' => 'servico', 'id' => 'custom_1', 'nome' => 'S', 'qty' => 1, 'preco' => 5, 'custom' => 1]],
        ]);
        $this->assertSame('pendente', $this->status($id), 'a edição não muda o estado do orçamento');

        $this->orcamento->aprovar($id);

        $ok = $this->orcamento->atualizar($id, [
            'cliente_id' => $this->clienteId, 'veiculo_id' => $this->veiculoId,
            'itens' => [['tipo' => 'servico', 'id' => 'custom_1', 'nome' => 'Mudou', 'qty' => 1, 'preco' => 9999, 'custom' => 1]],
        ]);

        $this->assertFalse($ok, 'orçamento aprovado já virou OS e não se edita');
        $this->assertStringContainsString('não pode mais ser editado', $this->orcamento->getLastError());
    }

    public function testTodaTransicaoFicaNoHistorico(): void
    {
        $id = $this->novoOrcamento();
        $this->orcamento->recusar($id, 'Sem verba');
        $this->orcamento->reabrir($id);

        $trilha = $this->pdo->query("SELECT estado_anterior, estado_novo, motivo FROM historico_estados WHERE entidade='ORCAMENTO' AND entidade_id={$id} ORDER BY id_historico")
            ->fetchAll(PDO::FETCH_ASSOC);

        $this->assertSame(
            [['', 'pendente'], ['pendente', 'reprovado'], ['reprovado', 'pendente']],
            array_map(fn($l) => [(string) $l['estado_anterior'], $l['estado_novo']], $trilha)
        );
        $this->assertSame('Sem verba', $trilha[1]['motivo']);
    }
}
