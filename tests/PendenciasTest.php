<?php

require_once __DIR__ . '/OsTestCase.php';
require_once __DIR__ . '/../app/models/Pendencias.php';
require_once __DIR__ . '/../app/models/Comunicacao.php';
require_once __DIR__ . '/../app/models/Orcamento.php';

/**
 * "O que precisa de você agora" (tela inicial por perfil).
 *
 * As contagens são do banco inteiro: os testes comparam ANTES e DEPOIS de criar o cenário (delta),
 * nunca um valor absoluto.
 */
final class PendenciasTest extends OsTestCase
{
    private Pendencias $pend;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pend = new Pendencias($this->pdo);
    }

    private function qtd(string $tipo, string $chave): int
    {
        foreach ($this->pend->paraPerfil($tipo) as $i) {
            if ($i['chave'] === $chave) {
                return $i['qtd'];
            }
        }

        return 0;
    }

    public function testAtendenteNaoVeDecisoesNemDinheiroEOTecnicoVeOMesmoQueOAtendente(): void
    {
        $atendente = array_column($this->pend->paraPerfil('ATENDENTE'), 'chave');
        $tecnico = array_column($this->pend->paraPerfil('TECNICO'), 'chave');

        $this->assertSame($atendente, $tecnico, 'técnico = atendente (mesmas permissões; só o painel de entrada muda)');

        foreach (['conferir', 'urgencia', 'pecas', 'receber_vencido', 'pagar_vencido', 'pagar_semana'] as $soAdmin) {
            $this->assertNotContains($soAdmin, $atendente, "'{$soAdmin}' é do administrador");
        }

        $grupos = array_unique(array_column($this->pend->paraPerfil('ATENDENTE'), 'grupo'));
        $this->assertEmpty(array_intersect($grupos, ['Decisões', 'Dinheiro']));
    }

    public function testServicoParaConferirApareceSoParaOAdministrador(): void
    {
        $antesAdmin = $this->qtd('ADMIN', 'conferir');
        $antesAtend = $this->qtd('ATENDENTE', 'conferir');
        $os = $this->novaOs();
        $this->ateConferencia($os);

        $this->assertSame($antesAdmin + 1, $this->qtd('ADMIN', 'conferir'));
        $this->assertSame($antesAtend, $this->qtd('ATENDENTE', 'conferir'), 'zero para a equipe');

        $this->assertTrue($this->mover($os, 'AGUARDANDO_RETIRADA', 'ADMIN')['ok']);
        $this->assertSame($antesAdmin, $this->qtd('ADMIN', 'conferir'), 'conferido, sai da lista');
    }

    public function testOrcamentoSemRespostaHaMaisDeDoisDiasEPendenteDeEnvio(): void
    {
        $semResp = $this->qtd('ATENDENTE', 'sem_resposta');
        $enviar = $this->qtd('ATENDENTE', 'orcamento_enviar');

        $this->pdo->prepare("INSERT INTO orcamentos (numero, cliente_id, veiculo_id, status, valor_total_orcamento) VALUES ('ORC-PEND1', ?, ?, 'pendente', 100)")->execute([$this->clienteId, $this->veiculoId]);
        $naoEnviado = (int) $this->pdo->lastInsertId();
        $this->fx->registrar('orcamentos', $naoEnviado);
        $this->assertSame($enviar + 1, $this->qtd('ATENDENTE', 'orcamento_enviar'), 'criado e ainda não enviado');

        $this->pdo->exec("UPDATE orcamentos SET enviado_em = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id_orcamento = {$naoEnviado}");
        $this->assertSame($enviar, $this->qtd('ATENDENTE', 'orcamento_enviar'), 'enviado: sai da lista de envio');
        $this->assertSame($semResp, $this->qtd('ATENDENTE', 'sem_resposta'), 'enviado ontem ainda não é "sem resposta"');

        $this->pdo->exec("UPDATE orcamentos SET enviado_em = DATE_SUB(NOW(), INTERVAL 3 DAY) WHERE id_orcamento = {$naoEnviado}");
        $this->assertSame($semResp + 1, $this->qtd('ATENDENTE', 'sem_resposta'));

        $this->pdo->exec("UPDATE orcamentos SET status = 'aprovado' WHERE id_orcamento = {$naoEnviado}");
        $this->assertSame($semResp, $this->qtd('ATENDENTE', 'sem_resposta'), 'respondido, sai da lista');
    }

    public function testAvisoQueNaoChegouContaMasCanalNaoConfiguradoNao(): void
    {
        $antes = $this->qtd('ATENDENTE', 'sem_envio');
        $conversa = Conversa::abertaOuNova($this->pdo, $this->clienteId, 'MANUAL', null, 'Teste', 'pendencias');
        $ins = $this->pdo->prepare("INSERT INTO mensagens (conversa_id, direcao, tipo, canal, corpo, status) VALUES (?, 'SAIDA', 'AUTOMATICA', 'MANUAL', 'x', ?)");

        $ins->execute([$conversa, 'NAO_CONFIGURADO']);
        $this->assertSame($antes, $this->qtd('ATENDENTE', 'sem_envio'), 'canal não configurado é assunto de Integrações, não da equipe');

        $ins->execute([$conversa, 'FALHA']);
        $ins->execute([$conversa, 'SEM_CONSENTIMENTO']);
        $this->assertSame($antes + 2, $this->qtd('ATENDENTE', 'sem_envio'));
    }

    public function testConversaNaoLidaApareceEEncerradaNao(): void
    {
        $antes = $this->qtd('ATENDENTE', 'conversas');
        $conversa = Conversa::abertaOuNova($this->pdo, $this->clienteId, 'MANUAL', null, 'Teste', 'pendencias');
        $this->pdo->exec("UPDATE conversas SET nao_lidas = 2 WHERE id_conversa = {$conversa}");

        $this->assertSame($antes + 1, $this->qtd('ATENDENTE', 'conversas'));

        $this->pdo->exec("UPDATE conversas SET status = 'ENCERRADA' WHERE id_conversa = {$conversa}");
        $this->assertSame($antes, $this->qtd('ATENDENTE', 'conversas'));
    }

    public function testAlertasVemAntesDoQueEApenasInformacao(): void
    {
        $peso = ['alerta' => 0, 'acao' => 1, 'info' => 2];
        $tons = array_map(fn($i) => $peso[$i['tom']], $this->pend->paraPerfil('ADMIN'));

        $ordenado = $tons;
        sort($ordenado);
        $this->assertSame($ordenado, $tons, 'alerta, depois ação, depois informação');
    }
}
