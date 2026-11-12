<?php

require_once __DIR__ . '/OsTestCase.php';
require_once __DIR__ . '/../app/models/Ocorrencia.php';
require_once __DIR__ . '/../app/models/FilaOperacional.php';

/**
 * Fila operacional: prioriza e ORIENTA, mas não bloqueia quem está livre.
 */
final class FilaOperacionalTest extends OsTestCase
{
    private FilaOperacional $fila;
    private Ocorrencia $oc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fila = new FilaOperacional();
        $this->oc   = new Ocorrencia();
    }

    private function chegada(array $extra = []): int
    {
        $r = $this->oc->registrar(array_merge([
            'cliente' => ['id' => $this->clienteId],
            'veiculo' => ['id' => $this->veiculoId],
            'relato'  => 'Problema de teste na fila',
        ], $extra), $this->adminId, 'ADMIN');
        $this->assertTrue($r['ok'], (string) $r['erro']);

        return (int) $r['id'];
    }

    /** Posição (0-based) de uma ocorrência na fila, ou null. */
    private function posicao(int $ocId): ?int
    {
        foreach ($this->fila->itens() as $i => $item) {
            if ($item['tipo'] === 'OCORRENCIA' && $item['id'] === $ocId) {
                return $i;
            }
        }

        return null;
    }

    /* ── Pontuação (pura) ──────────────────────────────────────────────── */

    private function item(array $x = []): array
    {
        return array_merge(['prioridade' => 'NORMAL', 'espera_min' => 0, 'duracao_estimada_min' => null, 'pecas_ok' => true, 'etapa' => 'AVALIAR', 'ordem_manual' => null], $x);
    }

    public function testPrioridadeDominaOTempoDeEspera(): void
    {
        $urgenteRecem = FilaOperacional::pontuar($this->item(['prioridade' => 'URGENTE', 'espera_min' => 0]));
        $normalAntigo = FilaOperacional::pontuar($this->item(['prioridade' => 'NORMAL', 'espera_min' => 600]));
        $baixa        = FilaOperacional::pontuar($this->item(['prioridade' => 'BAIXA']));

        $this->assertGreaterThan($normalAntigo, $urgenteRecem, 'urgência validada passa à frente de quem só espera mais');
        $this->assertGreaterThan($baixa, $normalAntigo);
    }

    public function testQuemEsperaMaisSobeMasComTeto(): void
    {
        $this->assertGreaterThan(
            FilaOperacional::pontuar($this->item(['espera_min' => 10])),
            FilaOperacional::pontuar($this->item(['espera_min' => 300]))
        );

        // Esperar um mês não faz um item "normal" ultrapassar um urgente.
        $this->assertLessThan(
            FilaOperacional::pontuar($this->item(['prioridade' => 'URGENTE'])),
            FilaOperacional::pontuar($this->item(['espera_min' => 60 * 24 * 30]))
        );
    }

    public function testServicoRapidoGanhaUmEmpurrao(): void
    {
        $this->assertGreaterThan(
            FilaOperacional::pontuar($this->item(['duracao_estimada_min' => 240])),
            FilaOperacional::pontuar($this->item(['duracao_estimada_min' => 20]))
        );
    }

    public function testPecaFaltandoDerrubaOExecutarMasNaoAAvaliacao(): void
    {
        $this->assertLessThan(
            FilaOperacional::pontuar($this->item(['etapa' => 'EXECUTAR', 'pecas_ok' => true])),
            FilaOperacional::pontuar($this->item(['etapa' => 'EXECUTAR', 'pecas_ok' => false]))
        );
        $this->assertSame(
            FilaOperacional::pontuar($this->item(['etapa' => 'AVALIAR', 'pecas_ok' => true])),
            FilaOperacional::pontuar($this->item(['etapa' => 'AVALIAR', 'pecas_ok' => false])),
            'avaliar não depende de peça'
        );
    }

    public function testOrdemManualSobrepoeATudo(): void
    {
        $manual = FilaOperacional::pontuar($this->item(['prioridade' => 'BAIXA', 'ordem_manual' => 1]));
        $urgente = FilaOperacional::pontuar($this->item(['prioridade' => 'URGENTE', 'espera_min' => 9999]));

        $this->assertGreaterThan($urgente, $manual);
        $this->assertGreaterThan(
            FilaOperacional::pontuar($this->item(['ordem_manual' => 5])),
            FilaOperacional::pontuar($this->item(['ordem_manual' => 2])),
            'menor número = mais perto do topo'
        );
    }

    /* ── Fila de verdade ───────────────────────────────────────────────── */

    public function testUrgenteValidadaVemAntesDeNormal(): void
    {
        $normal  = $this->chegada();
        $urgente = $this->chegada(['urgencia' => true, 'urgencia_motivo' => 'Frota parada']); // ADMIN → validada

        $this->assertLessThan($this->posicao($normal), $this->posicao($urgente));
    }

    public function testChegadaMaisRecenteDeServicoRapidoPodePassarNaFrente(): void
    {
        $demorado = $this->chegada(['duracao_estimada_min' => 300]);
        $rapido   = $this->chegada(['duracao_estimada_min' => 15]);
        $this->pdo->exec("UPDATE ocorrencias SET chegada_em = NOW() - INTERVAL 5 MINUTE WHERE id_ocorrencia = {$demorado}");

        $this->assertLessThan($this->posicao($demorado), $this->posicao($rapido), 'a fila não é puramente cronológica');
    }

    public function testTecnicoLivrePodeComecarAQualquerItemSemBloqueioArtificial(): void
    {
        $primeiro = $this->chegada(['urgencia' => true, 'urgencia_motivo' => 'Cliente esperando']);
        $simples  = $this->chegada(['duracao_estimada_min' => 10]);

        // O primeiro está atribuído a OUTRO técnico (ocupado). O livre ainda recebe sugestão.
        $this->oc->atribuirTecnico($primeiro, $this->tec2, $this->adminId);

        $sugestao = $this->fila->sugestaoPara($this->tec1);

        $this->assertNotNull($sugestao);
        $this->assertSame($simples, $sugestao['id'], 'o técnico livre recebe o melhor item que NÃO é de outro técnico');
        $this->assertTrue($sugestao['acionavel']);

        // E a ocorrência de outro técnico continua acionável por qualquer um (não há trava de dono).
        $this->assertTrue(OcorrenciaFluxo::transicionar($this->pdo, $primeiro, 'EM_AVALIACAO', $this->tec1)['ok']);
    }

    public function testDisponibilidadeDosTecnicosVemDoQueTemEmMao(): void
    {
        $antes = array_column($this->fila->tecnicos(), null, 'id');
        $this->assertTrue($antes[$this->tec1]['livre']);

        // tec1 pega duas OS em execução → atinge a capacidade
        foreach ([1, 2] as $_) {
            $os = $this->novaOs(['tecnicos' => [$this->tec1]]);
            $this->iniciar($os);
        }

        $depois = array_column($this->fila->tecnicos(), null, 'id');
        $this->assertSame(2, $depois[$this->tec1]['em_mao']);
        $this->assertFalse($depois[$this->tec1]['livre']);
        $this->assertTrue($depois[$this->tec2]['livre']);
    }

    public function testOsComPecaFaltandoVaiParaEsperaEOutraAcionavelNaFrente(): void
    {
        $semPeca = $this->novaOs(['itens' => [['tipo' => 'produto', 'id' => $this->produtoId, 'nome' => 'P', 'qty' => 50, 'preco' => 10]]]);
        $comPeca = $this->novaOs();

        $porId = [];
        foreach ($this->fila->itens() as $i) {
            if ($i['tipo'] === 'OS') {
                $porId[$i['id']] = $i;
            }
        }

        $this->assertFalse($porId[$semPeca]['acionavel'], 'sem saldo de peça não dá para começar');
        $this->assertSame('Falta peça em estoque', $porId[$semPeca]['bloqueio']);
        $this->assertTrue($porId[$comPeca]['acionavel']);
        $this->assertGreaterThan($porId[$semPeca]['score'], $porId[$comPeca]['score']);
    }

    /* ── Reorganização manual ──────────────────────────────────────────── */

    public function testReordenarExigeJustificativaEPoeNoTopoEFicaNaTrilha(): void
    {
        $a = $this->chegada(['urgencia' => true, 'urgencia_motivo' => 'Frota parada']);
        $b = $this->chegada(['prioridade' => 'BAIXA']);
        $this->assertLessThan($this->posicao($b), $this->posicao($a));

        $semMotivo = $this->fila->reordenar($b, true, '  ', $this->adminId);
        $this->assertFalse($semMotivo['ok']);
        $this->assertLessThan($this->posicao($b), $this->posicao($a), 'sem justificativa nada muda');

        $r = $this->fila->reordenar($b, true, 'Cliente é parceiro e combinou retirada às 17h', $this->adminId);
        $this->assertTrue($r['ok'], (string) $r['erro']);
        $this->assertLessThan($this->posicao($a), $this->posicao($b), 'a ordem manual vence a pontuação');

        $trilha = array_column($this->oc->linhaDoTempo($b), 'motivo');
        $this->assertStringContainsString('topo da fila', implode(' ', $trilha));
        $this->assertStringContainsString('parceiro', implode(' ', $trilha));

        $this->assertTrue($this->fila->reordenar($b, false, 'Voltando ao cálculo automático', $this->adminId)['ok']);
        $this->assertLessThan($this->posicao($b), $this->posicao($a), 'devolvida à ordem automática');
    }

    public function testNaoReordenaOcorrenciaEncerrada(): void
    {
        $oc = $this->chegada();
        $this->oc->cancelar($oc, $this->adminId, 'Engano de cadastro');

        $this->assertFalse($this->fila->reordenar($oc, true, 'Tentando mover', $this->adminId)['ok']);
    }
}
