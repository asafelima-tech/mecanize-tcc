<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/models/OrdemServico.php';
require_once __DIR__ . '/../app/models/OrdemServicoFluxo.php';
require_once __DIR__ . '/../app/models/OrdemServicoPagamento.php';
require_once __DIR__ . '/../app/models/ConsumoEstoque.php';
require_once __DIR__ . '/../app/models/Caixa.php';
require_once __DIR__ . '/../app/models/MovimentacaoCaixa.php';

/**
 * Base dos testes que mexem numa OS de verdade: monta um mundo mínimo e coerente
 * (administrador, técnicos, cliente, veículo, peça com estoque, caixa aberto) e
 * oferece atalhos para levar a OS pelas etapas do fluxo real.
 *
 * Roda contra mecanize_db_test; tudo o que é criado é removido no tearDown.
 */
abstract class OsTestCase extends TestCase
{
    protected PDO $pdo;
    protected Fixtures $fx;
    protected OrdemServico $os;
    protected OrdemServicoFluxo $fluxo;

    protected int $adminId;
    protected int $tec1;
    protected int $tec2;
    protected int $clienteId;
    protected int $veiculoId;
    protected int $produtoId;
    protected int $caixaId;

    protected function setUp(): void
    {
        $this->pdo   = new Database();
        $this->fx    = new Fixtures($this->pdo);
        $this->os    = new OrdemServico();
        $this->fluxo = new OrdemServicoFluxo();

        $this->adminId   = $this->fx->usuario('ADMIN');
        $this->tec1      = $this->fx->usuario('TECNICO');
        $this->tec2      = $this->fx->usuario('TECNICO');
        $this->clienteId = $this->fx->cliente();
        $this->veiculoId = $this->fx->veiculo($this->clienteId);
        $this->produtoId = $this->fx->produto(10, 100, 60);
        $this->caixaId   = $this->fx->caixaAberto($this->adminId, 100);
    }

    protected function tearDown(): void
    {
        $this->fx->limpar();
    }

    /**
     * Cria uma OS pelo model (como a tela faz): 2 peças de R$ 100 + 1 serviço de
     * R$ 300 = R$ 500, com os dois técnicos atribuídos.
     */
    protected function novaOs(array $extra = []): int
    {
        $id = $this->os->create(array_merge([
            'cliente_id' => $this->clienteId,
            'veiculo_id' => $this->veiculoId,
            'tecnicos'   => [$this->tec1, $this->tec2],
            'itens'      => [
                ['tipo' => 'produto', 'id' => $this->produtoId, 'nome' => 'Peça', 'qty' => 2, 'preco' => 100],
                ['tipo' => 'servico', 'id' => 'custom_1', 'nome' => 'Serviço', 'qty' => 1, 'preco' => 300, 'custom' => 1, 'categoria' => 'Geral'],
            ],
        ], $extra));

        $this->assertNotFalse($id, 'a OS de teste deveria ser criada: ' . $this->os->getLastError());
        $this->fx->registrar('ordens_servico', (int) $id);

        return (int) $id;
    }

    protected function status(int $osId): string
    {
        return (string) $this->pdo->query("SELECT status FROM ordens_servico WHERE id_ordem_servico = {$osId}")->fetchColumn();
    }

    protected function mover(int $osId, string $para, string $papel = 'ADMIN', array $ctx = []): array
    {
        $usuario = $papel === 'ADMIN' ? $this->adminId : $this->tec1;

        return $this->fluxo->transicionar($osId, $para, $usuario, $papel, $ctx);
    }

    /** ABERTA → EM_ANDAMENTO (a partir daqui o consumo de peça é permitido). */
    protected function iniciar(int $osId): void
    {
        $this->assertTrue($this->mover($osId, 'EM_ANDAMENTO', 'TECNICO')['ok']);
    }

    /** Consome todas as peças previstas da OS. */
    protected function consumirTudo(int $osId): void
    {
        $itens = $this->pdo->query("SELECT id_OS_produto FROM ordem_servico_produtos WHERE ordem_servico_id = {$osId} AND status = 'PLANEJADO'")
            ->fetchAll(PDO::FETCH_COLUMN);

        foreach ($itens as $item) {
            $r = ConsumoEstoque::consumir($this->pdo, (int) $item, $this->tec1);
            $this->assertTrue($r['ok'], (string) $r['erro']);
        }
    }

    /** Leva a OS até AGUARDANDO_CONFERENCIA, consumindo as peças no caminho. */
    protected function ateConferencia(int $osId): void
    {
        $this->iniciar($osId);
        $this->consumirTudo($osId);
        $this->assertTrue($this->mover($osId, 'AGUARDANDO_CONFERENCIA', 'TECNICO')['ok']);
    }

    /** Leva a OS até AGUARDANDO_RETIRADA (conferida pelo administrador). */
    protected function ateRetirada(int $osId): void
    {
        $this->ateConferencia($osId);
        $r = $this->mover($osId, 'AGUARDANDO_RETIRADA', 'ADMIN');
        $this->assertTrue($r['ok'], (string) $r['erro']);
    }

    protected function contar(string $sql): int
    {
        return (int) $this->pdo->query($sql)->fetchColumn();
    }
}
