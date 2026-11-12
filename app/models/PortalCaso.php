<?php

require_once __DIR__ . '/../core/Transacao.php';
require_once __DIR__ . '/../core/Integracoes.php';
require_once __DIR__ . '/../core/Historico.php';
require_once __DIR__ . '/MensagensCliente.php';
require_once __DIR__ . '/PortalToken.php';
require_once __DIR__ . '/Consentimento.php';
require_once __DIR__ . '/Notificacao.php';
require_once __DIR__ . '/Orcamento.php';
require_once __DIR__ . '/AutorizacaoCliente.php';
require_once __DIR__ . '/CobrancaOnline.php';
require_once __DIR__ . '/ContaReceber.php';

/**
 * O que o cliente vê e faz no portal (link seguro, sem login).
 *
 * UM link vale para o "caso" dele: o token aponta para uma ocorrência, um orçamento ou uma OS, e
 * daí se alcança o resto da MESMA jornada (ocorrência ↔ orçamento ↔ OS) — sempre do mesmo cliente.
 * Nada fora desse alcance é lido nem alterado, e toda ação confere o alcance de novo no servidor
 * (esconder o botão não é controle).
 *
 * LGPD: só o primeiro nome, o veículo e os dados do próprio serviço. Nunca CPF, endereço, telefone,
 * custo, margem, comissão, nome de técnico nem os motivos internos do histórico.
 *
 * O rastreamento é o ANDAMENTO do serviço (etapas reais, vindas do histórico de estados). Não há
 * localização nem GPS: o veículo está na oficina e o sistema só afirma o que registrou.
 */
final class PortalCaso
{
    /** Etapas do histórico, ditas na linguagem do cliente. [entidade][estado] => texto */
    private const ETAPAS = [
        'OCORRENCIA' => [
            'AGUARDANDO_AVALIACAO' => 'Veículo recebido na oficina',
            'EM_AVALIACAO'         => 'Avaliação técnica iniciada',
            'AGUARDANDO_ORCAMENTO' => 'Avaliação concluída — preparando o orçamento',
            'AGUARDANDO_APROVACAO' => 'Orçamento enviado — aguardando a sua aprovação',
            'EM_ESPERA'            => 'Atendimento em espera',
            'EM_EXECUCAO'          => 'Serviço autorizado',
            'ENCERRADA'            => 'Atendimento encerrado',
            'CANCELADA'            => 'Atendimento cancelado',
        ],
        'ORCAMENTO' => [
            'aprovado'  => 'Orçamento aprovado',
            'reprovado' => 'Orçamento recusado',
            'cancelado' => 'Orçamento cancelado',
        ],
        'OS' => [
            'ABERTA'                 => 'Ordem de serviço aberta',
            'EM_ANDAMENTO'           => 'Serviço em execução',
            'AGUARDANDO_PECA'        => 'Aguardando a chegada de uma peça',
            'AGUARDANDO_CONFERENCIA' => 'Conferência final do serviço',
            'AGUARDANDO_RETIRADA'    => 'Veículo pronto para retirada',
            'CONCLUIDA'              => 'Serviço entregue',
            'CANCELADA'              => 'Ordem de serviço cancelada',
        ],
    ];

    /** Onde o cliente está agora, em uma frase. */
    private const SITUACAO_OS = [
        'ABERTA'                 => ['Recebido', 'Seu serviço está registrado e entra na fila de execução.'],
        'EM_ANDAMENTO'           => ['Em execução', 'Nossa equipe está trabalhando no seu veículo.'],
        'AGUARDANDO_PECA'        => ['Aguardando peça', 'O serviço está pausado até a chegada de uma peça.'],
        'AGUARDANDO_CONFERENCIA' => ['Conferência final', 'O serviço foi feito e está passando pela conferência final.'],
        'AGUARDANDO_RETIRADA'    => ['Pronto para retirada', 'Seu veículo está pronto. Pode vir buscá-lo.'],
        'CONCLUIDA'              => ['Entregue', 'Serviço entregue. Obrigado pela confiança!'],
        'CANCELADA'              => ['Cancelada', 'Esta ordem de serviço foi cancelada.'],
    ];

    private const SITUACAO_OCORRENCIA = [
        'AGUARDANDO_AVALIACAO' => ['Recebido', 'Seu veículo foi recebido e aguarda a avaliação técnica.'],
        'EM_AVALIACAO'         => ['Em avaliação', 'Nosso técnico está avaliando o seu veículo.'],
        'AGUARDANDO_ORCAMENTO' => ['Preparando o orçamento', 'A avaliação terminou e o orçamento está sendo preparado.'],
        'AGUARDANDO_APROVACAO' => ['Aguardando a sua aprovação', 'O orçamento está pronto. Veja os itens e responda abaixo.'],
        'EM_ESPERA'            => ['Em espera', 'O atendimento está em espera. Fale com a oficina para retomar.'],
        'EM_EXECUCAO'          => ['Serviço autorizado', 'O serviço foi autorizado e vai para execução.'],
        'ENCERRADA'            => ['Encerrado', 'Atendimento encerrado.'],
        'CANCELADA'            => ['Cancelado', 'Este atendimento foi cancelado.'],
    ];

    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    /* ── Alcance ─────────────────────────────────────────────────────────── */

    /**
     * O que este token enxerga e pode decidir. null = a entidade sumiu ou não é do cliente do token.
     *
     * @return array{cliente_id:int, ocorrencia_id:?int, orcamento_ids:int[], os_ids:int[]}|null
     */
    public function alcance(array $token): ?array
    {
        $cliente = (int) $token['cliente_id'];
        $id = (int) $token['entidade_id'];
        $oc = null;
        $orcs = [];
        $oss = [];

        switch ($token['escopo']) {
            case 'OCORRENCIA':
                $o = $this->um('SELECT id_ocorrencia FROM ocorrencias WHERE id_ocorrencia = ? AND cliente_id = ?', [$id, $cliente]);
                if (!$o) {
                    return null;
                }
                $oc = $id;
                break;

            case 'ORCAMENTO':
                $o = $this->um('SELECT ocorrencia_id FROM orcamentos WHERE id_orcamento = ? AND cliente_id = ?', [$id, $cliente]);
                if (!$o) {
                    return null;
                }
                $orcs[] = $id;
                $oc = $o['ocorrencia_id'] ? (int) $o['ocorrencia_id'] : null;
                break;

            case 'OS':
                $o = $this->um('SELECT ocorrencia_id, orcamento_id FROM ordens_servico WHERE id_ordem_servico = ? AND cliente_id = ?', [$id, $cliente]);
                if (!$o) {
                    return null;
                }
                $oss[] = $id;
                $oc = $o['ocorrencia_id'] ? (int) $o['ocorrencia_id'] : null;
                if ($o['orcamento_id']) {
                    $orcs[] = (int) $o['orcamento_id'];
                }
                break;

            default:
                return null;
        }

        if ($oc !== null) {
            foreach ($this->todos("SELECT id_orcamento FROM orcamentos WHERE ocorrencia_id = ? AND cliente_id = ? AND status <> 'cancelado'", [$oc, $cliente]) as $r) {
                $orcs[] = (int) $r['id_orcamento'];
            }
            foreach ($this->todos('SELECT id_ordem_servico FROM ordens_servico WHERE ocorrencia_id = ? AND cliente_id = ?', [$oc, $cliente]) as $r) {
                $oss[] = (int) $r['id_ordem_servico'];
            }
        }

        foreach ($orcs as $o) {
            foreach ($this->todos('SELECT id_ordem_servico FROM ordens_servico WHERE orcamento_id = ? AND cliente_id = ?', [$o, $cliente]) as $r) {
                $oss[] = (int) $r['id_ordem_servico'];
            }
        }

        return [
            'cliente_id'    => $cliente,
            'ocorrencia_id' => $oc,
            'orcamento_ids' => array_values(array_unique($orcs)),
            'os_ids'        => array_values(array_unique($oss)),
        ];
    }

    /* ── Visão ───────────────────────────────────────────────────────────── */

    /** @return array<string,mixed>|null */
    public function visao(array $token): ?array
    {
        $alc = $this->alcance($token);

        if ($alc === null) {
            return null;
        }

        $cliente = $this->um('SELECT nome, email, telefone FROM clientes WHERE id_cliente = ?', [$alc['cliente_id']]);

        $orcamentos = array_map(fn($id) => $this->orcamento($id), $alc['orcamento_ids']);
        $ordens = array_map(fn($id) => $this->ordem($id), $alc['os_ids']);
        $ordens = array_values(array_filter($ordens));
        $orcamentos = array_values(array_filter($orcamentos));

        // O veículo e a situação principal vêm da entidade do token, ou da mais avançada do caso.
        $veiculoId = $this->veiculoDoCaso($token, $alc);
        $veiculo = $veiculoId ? $this->um(
            "SELECT v.placa, CONCAT(mv.nome, ' ', mo.nome) AS descricao, v.cor, v.ano FROM veiculos v
             LEFT JOIN marcas_veiculos mv ON mv.id_marca = v.marca_id LEFT JOIN modelos_veiculos mo ON mo.id_modelo = v.modelo_id WHERE v.id_veiculo = ?",
            [$veiculoId]
        ) : null;

        if ($veiculo && str_contains((string) $veiculo['descricao'], 'Não informado')) {
            $veiculo['descricao'] = '';
        }

        return [
            'primeiro_nome' => MensagensCliente::primeiroNome($cliente['nome'] ?? ''),
            'veiculo'       => $veiculo ?: ['placa' => '', 'descricao' => '', 'cor' => null, 'ano' => null],
            'situacao'      => $this->situacao($alc, $ordens),
            'orcamentos'    => $orcamentos,
            'ordens'        => $ordens,
            'linha_do_tempo' => $this->linhaDoTempo($alc),
            'pagamento_online' => (new CobrancaOnline($this->conn))->disponivel(),
            'consentimento' => [
                'whatsapp'      => Consentimento::vigente($this->conn, $alc['cliente_id'], 'WHATSAPP'),
                'email'         => Consentimento::vigente($this->conn, $alc['cliente_id'], 'EMAIL'),
                'tem_email'     => !empty($cliente['email']),
                'tem_telefone'  => !empty($cliente['telefone']),
                'whatsapp_ativo' => Integracoes::whatsapp()['ativo'] ?? false,
            ],
            'oficina' => [
                'nome'     => self::empresa('NOME', 'Mecanize'),
                'endereco' => self::empresa('ENDERECO'),
                'telefone' => self::empresa('TELEFONE'),
                'email'    => self::empresa('EMAIL'),
                'horario'  => self::empresa('HORARIO'),
            ],
        ];
    }

    /** Dado da oficina (config/empresa.php, vindo do .env); em branco se não foi preenchido. */
    private static function empresa(string $campo, string $padrao = ''): string
    {
        $nome = 'EMPRESA_' . $campo;

        return defined($nome) ? (string) constant($nome) : (string) env($nome, $padrao);
    }

    private function veiculoDoCaso(array $token, array $alc): ?int
    {
        $consultas = [
            ['OS', 'SELECT veiculo_id FROM ordens_servico WHERE id_ordem_servico = ?'],
            ['ORCAMENTO', 'SELECT veiculo_id FROM orcamentos WHERE id_orcamento = ?'],
            ['OCORRENCIA', 'SELECT veiculo_id FROM ocorrencias WHERE id_ocorrencia = ?'],
        ];

        foreach ($consultas as [$escopo, $sql]) {
            if ($token['escopo'] === $escopo) {
                $r = $this->um($sql, [(int) $token['entidade_id']]);

                return $r && $r['veiculo_id'] ? (int) $r['veiculo_id'] : null;
            }
        }

        return null;
    }

    /** @return array{codigo:string, rotulo:string, descricao:string} */
    private function situacao(array $alc, array $ordens): array
    {
        // A OS mais recente manda; sem OS, vale a ocorrência.
        if ($ordens) {
            $os = end($ordens);
            [$rotulo, $desc] = self::SITUACAO_OS[$os['status']] ?? [$os['status'], ''];

            return ['codigo' => $os['status'], 'rotulo' => $rotulo, 'descricao' => $desc];
        }

        if ($alc['ocorrencia_id']) {
            $o = $this->um('SELECT status FROM ocorrencias WHERE id_ocorrencia = ?', [$alc['ocorrencia_id']]);
            [$rotulo, $desc] = self::SITUACAO_OCORRENCIA[$o['status'] ?? ''] ?? ['Em andamento', ''];

            return ['codigo' => (string) ($o['status'] ?? ''), 'rotulo' => $rotulo, 'descricao' => $desc];
        }

        return ['codigo' => '', 'rotulo' => 'Em andamento', 'descricao' => ''];
    }

    /** @return array<string,mixed>|null */
    private function orcamento(int $id): ?array
    {
        $o = $this->um('SELECT id_orcamento, numero, status, enviado_em, data_validade, prazo_execucao, diagnostico, valor_total_orcamento FROM orcamentos WHERE id_orcamento = ?', [$id]);

        if (!$o) {
            return null;
        }

        $itens = $this->todos('SELECT nome, tipo, quantidade, preco_unitario, subtotal FROM orcamento_itens WHERE orcamento_id = ? ORDER BY id_orcamento_item', [$id]);
        $vencido = !empty($o['data_validade']) && $o['data_validade'] < date('Y-m-d');
        $enviado = $o['enviado_em'] !== null;

        return [
            'id'        => (int) $o['id_orcamento'],
            'numero'    => $o['numero'],
            'status'    => $o['status'],
            'valor'     => (float) $o['valor_total_orcamento'],
            'validade'  => $o['data_validade'],
            'prazo'     => $o['prazo_execucao'],
            'diagnostico' => $o['diagnostico'],
            'itens'     => $itens,
            'enviado'   => $enviado,
            'vencido'   => $vencido,
            // Só se decide o que a oficina já ENVIOU (rascunho ou versão em revisão ficam de fora) e que ainda vale.
            'pode_decidir' => $enviado && $o['status'] === 'pendente' && !$vencido,
            'decisao'   => $this->decisaoRegistrada('ORCAMENTO', $id),
        ];
    }

    /** @return array<string,mixed>|null */
    private function ordem(int $id): ?array
    {
        $os = $this->um('SELECT id_ordem_servico, numero, status, valor_total_os, valor_autorizado, previsao_conclusao FROM ordens_servico WHERE id_ordem_servico = ?', [$id]);

        if (!$os) {
            return null;
        }

        $parcelas = (new CobrancaOnline($this->conn))->parcelasDaOs($id);
        $cobrada = in_array($os['status'], ContaReceber::OS_COBRADAS, true);
        $saldo = 0.0;

        foreach ($parcelas as $p) {
            if ((int) $p['pago'] === 0) {
                $saldo += (float) $p['valor'];
            }
        }

        $pendentes = $this->todos(
            "SELECT id_autorizacao, valor_adicional, escopo, registrada_em FROM autorizacoes_cliente
             WHERE ordem_servico_id = ? AND tipo = 'COMPLEMENTAR' AND status = 'SOLICITADA' ORDER BY id_autorizacao",
            [$id]
        );

        return [
            'id'          => (int) $os['id_ordem_servico'],
            'numero'      => $os['numero'],
            'status'      => $os['status'],
            'valor'       => (float) $os['valor_total_os'],
            'previsao'    => $os['previsao_conclusao'],
            'saldo'       => round($saldo, 2),
            'cobrada'     => $cobrada,
            'parcelas'    => array_map(fn($p) => [
                'id'        => (int) $p['id_parcela'],
                'numero'    => (int) $p['numero'],
                'valor'     => (float) $p['valor'],
                'vencimento' => $p['vencimento'],
                'pago'      => (int) $p['pago'] === 1,
                'pago_em'   => $p['data_pagamento'],
                'em_andamento' => !empty($p['cobranca_ativa_id']),
                'checkout'  => $p['checkout_ativo'] ?: null,
            ], $parcelas),
            // Só autorizações em aberto aparecem como pergunta; o histórico delas é da oficina.
            'autorizacoes_pendentes' => array_map(fn($a) => [
                'id' => (int) $a['id_autorizacao'], 'valor' => (float) $a['valor_adicional'], 'motivo' => $a['escopo'], 'em' => $a['registrada_em'],
            ], $pendentes),
            'pronta_para_retirar' => $os['status'] === 'AGUARDANDO_RETIRADA',
            'finalizada' => in_array($os['status'], ['CONCLUIDA', 'CANCELADA'], true),
        ];
    }

    private function decisaoRegistrada(string $tipo, int $id): ?array
    {
        return $this->um('SELECT decisao, criada_em FROM decisoes_portal WHERE tipo = ? AND entidade_id = ? ORDER BY id_decisao DESC LIMIT 1', [$tipo, $id]);
    }

    /**
     * Andamento real, em ordem. Só entram os estados mapeados (linguagem do cliente); motivos e
     * autores do histórico interno NUNCA saem daqui.
     *
     * @return array<int,array{quando:string, titulo:string}>
     */
    private function linhaDoTempo(array $alc): array
    {
        $fontes = [];

        if ($alc['ocorrencia_id']) {
            $fontes[] = ['OCORRENCIA', $alc['ocorrencia_id']];
        }
        foreach ($alc['orcamento_ids'] as $id) {
            $fontes[] = ['ORCAMENTO', $id];
        }
        foreach ($alc['os_ids'] as $id) {
            $fontes[] = ['OS', $id];
        }

        $eventos = [];

        foreach ($fontes as [$entidade, $id]) {
            foreach ($this->todos('SELECT estado_anterior, estado_novo, criado_em, id_historico FROM historico_estados WHERE entidade = ? AND entidade_id = ? ORDER BY id_historico', [$entidade, $id]) as $h) {
                // Mesmo estado → mesmo estado é anotação interna (ex.: pedido de autorização), não etapa.
                if ($h['estado_anterior'] === $h['estado_novo']) {
                    continue;
                }

                $titulo = self::ETAPAS[$entidade][$h['estado_novo']] ?? null;

                // Orçamento voltando a "pendente" (reabertura) não é novidade para o cliente.
                if ($titulo === null) {
                    continue;
                }

                if ($entidade === 'OS' && $h['estado_novo'] === 'EM_ANDAMENTO' && $h['estado_anterior'] !== null && $h['estado_anterior'] !== 'ABERTA') {
                    $titulo = $h['estado_anterior'] === 'AGUARDANDO_PECA' ? 'Serviço retomado' : 'Ajustes finais em execução';
                }

                $eventos[] = ['quando' => $h['criado_em'], 'titulo' => $titulo, 'ordem' => (int) $h['id_historico']];
            }
        }

        usort($eventos, fn($a, $b) => [$a['quando'], $a['ordem']] <=> [$b['quando'], $b['ordem']]);

        return array_map(fn($e) => ['quando' => $e['quando'], 'titulo' => $e['titulo']], $eventos);
    }

    /* ── Ações do cliente ────────────────────────────────────────────────── */

    /**
     * @return array{ok:bool, erro:?string, repetido:bool}
     */
    public function decidirOrcamento(array $token, int $orcamentoId, bool $aprovar, ?string $motivo, ?string $ip, ?string $userAgent): array
    {
        $alc = $this->alcance($token);

        if ($alc === null || !in_array($orcamentoId, $alc['orcamento_ids'], true)) {
            return ['ok' => false, 'erro' => 'Este orçamento não está disponível neste link.', 'repetido' => false];
        }

        $orc = $this->um('SELECT status, enviado_em, numero, ocorrencia_id FROM orcamentos WHERE id_orcamento = ?', [$orcamentoId]);

        if (!$orc || $orc['enviado_em'] === null) {
            return ['ok' => false, 'erro' => 'Este orçamento ainda não foi liberado para a sua resposta. Fale com a oficina.', 'repetido' => false];
        }

        $motivo = $motivo !== null ? mb_substr(trim($motivo), 0, 255) : null;
        $quando = date('d/m/Y H:i');
        $orcamento = new Orcamento($this->conn);

        try {
            return Transacao::executar($this->conn, function () use ($token, $alc, $orcamentoId, $aprovar, $motivo, $ip, $userAgent, $orc, $quando, $orcamento) {
                $ctx = ['origem' => Historico::ORIGEM_PORTAL, 'dados' => ['token_id' => (int) $token['id_token'], 'ip' => $ip]];

                if ($aprovar) {
                    $r = $orcamento->aprovar($orcamentoId, null, $ctx + [
                        'canal'     => 'PORTAL',
                        'evidencia' => "Aprovado pelo cliente no portal em {$quando} (link #{$token['id_token']}, IP " . ($ip ?: 'n/d') . ')',
                    ]);
                } else {
                    $r = $orcamento->recusar($orcamentoId, $motivo ?: 'Recusado pelo cliente no portal', $ctx);
                }

                if (!$r['ok']) {
                    throw new DomainException((string) $r['erro']);
                }

                if (!($r['repetido'] ?? false)) {
                    $this->registrarDecisao($token, 'ORCAMENTO', $orcamentoId, $aprovar, $motivo, $ip, $userAgent);
                    $this->avisarEquipe(
                        $aprovar ? 'Orçamento ' . $orc['numero'] . ' aprovado pelo cliente' : 'Orçamento ' . $orc['numero'] . ' recusado pelo cliente',
                        ($aprovar ? 'O cliente aprovou o orçamento pelo portal.' : 'O cliente recusou o orçamento pelo portal.') . ($motivo ? ' Motivo: ' . $motivo : ''),
                        $aprovar && !empty($r['os_id']) ? './viewOrdemServico?id=' . $r['os_id'] : './viewOrcamento?id=' . $orcamentoId,
                        'PORTAL:ORCAMENTO:' . $orcamentoId . ':' . ($aprovar ? 'APROVADO' : 'RECUSADO') . ':' . $token['id_token'],
                        null
                    );
                }

                return ['ok' => true, 'erro' => null, 'repetido' => (bool) ($r['repetido'] ?? false)];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'repetido' => false];
        } catch (\Throwable $e) {
            error_log('[Portal orçamento] ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível registrar a sua resposta. Tente novamente.', 'repetido' => false];
        }
    }

    /**
     * @return array{ok:bool, erro:?string, repetido:bool}
     */
    public function decidirAutorizacao(array $token, int $autorizacaoId, bool $aprovar, ?string $ip, ?string $userAgent): array
    {
        $alc = $this->alcance($token);
        $a = $this->um("SELECT ordem_servico_id, valor_adicional, tipo FROM autorizacoes_cliente WHERE id_autorizacao = ?", [$autorizacaoId]);

        if ($alc === null || !$a || $a['tipo'] !== 'COMPLEMENTAR' || !in_array((int) $a['ordem_servico_id'], $alc['os_ids'], true)) {
            return ['ok' => false, 'erro' => 'Este pedido não está disponível neste link.', 'repetido' => false];
        }

        $quando = date('d/m/Y H:i');

        try {
            return Transacao::executar($this->conn, function () use ($token, $autorizacaoId, $aprovar, $ip, $userAgent, $a, $quando) {
                $r = (new AutorizacaoCliente($this->conn))->decidir(
                    $autorizacaoId, $aprovar, 'PORTAL',
                    "Resposta do cliente no portal em {$quando} (link #{$token['id_token']}, IP " . ($ip ?: 'n/d') . ')',
                    null, Historico::ORIGEM_PORTAL
                );

                if (!$r['ok']) {
                    throw new DomainException((string) $r['erro']);
                }

                if (!$r['repetido']) {
                    $this->registrarDecisao($token, 'AUTORIZACAO', $autorizacaoId, $aprovar, null, $ip, $userAgent);

                    $os = $this->um('SELECT numero FROM ordens_servico WHERE id_ordem_servico = ?', [$a['ordem_servico_id']]);
                    $this->avisarEquipe(
                        $aprovar ? 'Valor adicional autorizado pelo cliente' : 'Valor adicional recusado pelo cliente',
                        'OS ' . ($os['numero'] ?? '') . ': o cliente ' . ($aprovar ? 'autorizou' : 'recusou') . ' R$ ' . number_format((float) $a['valor_adicional'], 2, ',', '.') . ' pelo portal.',
                        './viewOrdemServico?id=' . $a['ordem_servico_id'],
                        'PORTAL:AUTORIZACAO:' . $autorizacaoId . ':' . ($aprovar ? 'SIM' : 'NAO'),
                        (int) $a['ordem_servico_id']
                    );
                }

                return ['ok' => true, 'erro' => null, 'repetido' => $r['repetido']];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'repetido' => false];
        } catch (\Throwable $e) {
            error_log('[Portal autorização] ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível registrar a sua resposta. Tente novamente.', 'repetido' => false];
        }
    }

    /**
     * Inicia o pagamento online das parcelas escolhidas (da OS que o link alcança).
     *
     * @param int[] $parcelaIds
     * @return array{ok:bool, erro:?string, url:?string}
     */
    public function iniciarPagamento(array $token, int $osId, array $parcelaIds): array
    {
        $alc = $this->alcance($token);

        if ($alc === null || !in_array($osId, $alc['os_ids'], true)) {
            return ['ok' => false, 'erro' => 'Esta ordem de serviço não está disponível neste link.', 'url' => null];
        }

        $base = Integracoes::appUrl();
        $retorno = $base . '/portal?t=' . rawurlencode(self::tokenBruto($token)) . '&pg=retorno';

        $r = (new CobrancaOnline($this->conn))->criar($osId, $parcelaIds, (int) $token['id_token'], $retorno, $base . '/webhookPagamento');

        return ['ok' => $r['ok'], 'erro' => $r['erro'], 'url' => $r['url']];
    }

    /** O token bruto só existe na requisição; o chamador o repassa em `_bruto`. */
    private static function tokenBruto(array $token): string
    {
        return (string) ($token['_bruto'] ?? '');
    }

    /**
     * @return array{ok:bool, erro:?string}
     */
    public function registrarConsentimento(array $token, string $canal, bool $concedido): array
    {
        $alc = $this->alcance($token);

        if ($alc === null) {
            return ['ok' => false, 'erro' => 'Link indisponível.'];
        }

        $r = Consentimento::registrar(
            $this->conn, $alc['cliente_id'], $canal, $concedido, 'PORTAL', null,
            $concedido ? 'Aceitou receber mensagens pelo portal' : 'Pediu para parar de receber mensagens pelo portal'
        );

        return ['ok' => $r['ok'], 'erro' => $r['erro']];
    }

    /* ── Apoio ───────────────────────────────────────────────────────────── */

    private function registrarDecisao(array $token, string $tipo, int $entidadeId, bool $aprovou, ?string $motivo, ?string $ip, ?string $userAgent): void
    {
        $this->conn->prepare('INSERT INTO decisoes_portal (token_id, cliente_id, tipo, entidade_id, decisao, motivo, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                (int) $token['id_token'], (int) $token['cliente_id'], $tipo, $entidadeId, $aprovou ? 'APROVADO' : 'RECUSADO',
                $motivo, $ip ? mb_substr($ip, 0, 45) : null, $userAgent ? mb_substr($userAgent, 0, 200) : null,
            ]);
    }

    /** A decisão do cliente chega à equipe: administradores, atendentes e os técnicos da OS (se houver). */
    private function avisarEquipe(string $titulo, string $mensagem, string $link, string $chave, ?int $osId): void
    {
        $ids = array_map('intval', $this->conn->query("SELECT id_usuario FROM usuarios WHERE ativo = 1 AND tipo IN ('ADMIN','ATENDENTE')")->fetchAll(PDO::FETCH_COLUMN));

        if ($osId) {
            $t = $this->conn->prepare('SELECT DISTINCT tecnico_id FROM ordem_servico_mao_obra WHERE ordem_servico_id = ? AND tecnico_id IS NOT NULL');
            $t->execute([$osId]);
            $ids = array_merge($ids, array_map('intval', $t->fetchAll(PDO::FETCH_COLUMN)));
        }

        Notificacao::paraUsuarios($this->conn, $ids, 'PORTAL', $titulo, $mensagem, $link, $chave);
    }

    private function um(string $sql, array $params): ?array
    {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function todos(string $sql, array $params): array
    {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
