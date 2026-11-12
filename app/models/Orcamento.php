<?php

require_once __DIR__ . '/../core/Transacao.php';
require_once __DIR__ . '/Comunicacao.php';
require_once __DIR__ . '/../core/Historico.php';
require_once __DIR__ . '/OrdemServico.php';
require_once __DIR__ . '/OcorrenciaFluxo.php';
require_once __DIR__ . '/AutorizacaoCliente.php';

class Orcamento
{
    private $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    /**
     * Retorna o token de rastreio público do orçamento, gerando um na hora
     * se ainda não existir (cobre orçamentos criados antes desta feature).
     */
    public function getOuCriarToken(int $id): string
    {
        $stmt = $this->conn->prepare("SELECT token_rastreio FROM orcamentos WHERE id_orcamento = ?");
        $stmt->execute([$id]);
        $token = $stmt->fetchColumn();

        if ($token) {
            return $token;
        }

        $token = bin2hex(random_bytes(24));
        $this->conn->prepare("UPDATE orcamentos SET token_rastreio = ? WHERE id_orcamento = ?")
            ->execute([$token, $id]);

        return $token;
    }

    /**
     * Dados mínimos e públicos de um orçamento a partir do token de rastreio
     * — sem valores financeiros nem dados do cliente além do primeiro nome.
     */
    public function getPorTokenRastreio(string $token): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT
                o.numero,
                o.status,
                o.data_criacao,
                v.placa,
                CONCAT(mv.nome, ' ', mo.nome) AS modelo,
                c.nome AS cliente_nome
            FROM orcamentos o
            INNER JOIN veiculos          v  ON v.id_veiculo = o.veiculo_id
            INNER JOIN marcas_veiculos   mv ON mv.id_marca  = v.marca_id
            INNER JOIN modelos_veiculos  mo ON mo.id_modelo = v.modelo_id
            INNER JOIN clientes          c  ON c.id_cliente = o.cliente_id
            WHERE o.token_rastreio = ?
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        return $linha ?: null;
    }

    /** Histórico de orçamentos de um veículo (usado na linha do tempo do veículo). */
    public function listarPorVeiculo(int $veiculoId): array
    {
        $stmt = $this->conn->prepare("
            SELECT id_orcamento, numero, status, data_criacao, valor_total_orcamento
            FROM orcamentos
            WHERE veiculo_id = ?
            ORDER BY data_criacao DESC
        ");
        $stmt->execute([$veiculoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDadosFormulario()
    {
        return [
            'clientes'       => $this->getClientes(),
            'veiculos'       => $this->getVeiculos(),
            'mecanicos'      => $this->getMecanicos(),
            'produtos'       => $this->getProdutos(),
            'servicos'       => $this->getServicos(),
            'proximoNumero'  => $this->getProximoNumero(),
        ];
    }

    /**
     * Máquina de estados do orçamento: estado atual => estados de destino.
     *
     *   pendente ──aprovar──▶ aprovado   (cria a OS, uma única vez; fim da linha aqui)
     *      │   ╰──recusar──▶ reprovado ──reabrir──▶ pendente   (negociação retomada)
     *      ╰──cancelar──▶ cancelado
     *
     * Aprovar só vale a partir de "pendente": um orçamento recusado ou cancelado
     * NÃO vira OS por engano (aba antiga, clique repetido). Para vender depois de
     * uma recusa, o orçamento é reaberto — uma decisão explícita, com histórico.
     */
    public const TRANSICOES = [
        'pendente'  => ['aprovado', 'reprovado', 'cancelado'],
        'reprovado' => ['pendente'],
        'aprovado'  => [],
        'cancelado' => [],
    ];

    public const ROTULOS = [
        'pendente'  => 'Pendente',
        'aprovado'  => 'Aprovado',
        'reprovado' => 'Recusado',
        'cancelado' => 'Cancelado',
    ];

    private string $ultimoErro = '';

    public function getLastError(): string
    {
        return $this->ultimoErro;
    }

    private function num($valor): float
    {
        return (float) str_replace(',', '.', trim((string) $valor));
    }

    private function usuarioDe(array $ctx): ?int
    {
        $id = $ctx['usuario_id'] ?? ($_SESSION['user_id_usuario'] ?? null);

        return $id ? (int) $id : null;
    }

    /**
     * Valida e normaliza as linhas do formulário e calcula os totais NO SERVIDOR.
     * O subtotal e os totais que o navegador mandou são descartados: dinheiro não
     * pode depender de um campo oculto que qualquer um edita.
     *
     * @return array{itens: array<int,array>, pecas: float, servicos: float, total: float}
     */
    private function normalizarItens(array $itens): array
    {
        $saida = [];
        $pecas = 0.0;
        $servicos = 0.0;

        foreach ($itens as $item) {
            $tipo = ($item['tipo'] ?? 'produto') === 'servico' ? 'servico' : 'produto';
            $nome = trim((string) ($item['nome'] ?? ''));
            $qtd  = round($this->num($item['qty'] ?? 1), 3);
            $preco = round($this->num($item['preco'] ?? 0), 2);

            if ($nome === '') {
                throw new DomainException('Há um item sem descrição no orçamento.');
            }

            if ($qtd <= 0) {
                throw new DomainException('Quantidade inválida no item "' . $nome . '".');
            }

            if ($preco < 0) {
                throw new DomainException('Valor inválido no item "' . $nome . '".');
            }

            $custom = in_array($item['custom'] ?? 0, [1, '1', true], true);
            $itemId = $custom || empty($item['id']) || !ctype_digit((string) $item['id']) ? null : (int) $item['id'];
            $subtotal = round($qtd * $preco, 2);

            if ($tipo === 'produto') {
                $pecas += $subtotal;
            } else {
                $servicos += $subtotal;
            }

            $saida[] = [
                'item_id'   => $itemId,
                'nome'      => $nome,
                'tipo'      => $tipo,
                'categoria' => $item['categoria'] ?? null,
                'preco'     => $preco,
                'qty'       => $qtd,
                'subtotal'  => $subtotal,
                'custom'    => $custom ? 1 : 0,
                'pct'       => isset($item['pct']) && $item['pct'] !== '' ? (float) $item['pct'] : null,
            ];
        }

        $pecas = round($pecas, 2);
        $servicos = round($servicos, 2);

        return ['itens' => $saida, 'pecas' => $pecas, 'servicos' => $servicos, 'total' => round($pecas + $servicos, 2)];
    }

    /**
     * Cria o orçamento (sempre "pendente") e devolve o id, ou false.
     *
     * A numeração é do servidor (ORC-<id>): o número que vinha do formulário era
     * calculado pelo navegador de quem abriu a tela primeiro, e dois orçamentos
     * abertos ao mesmo tempo recebiam o mesmo. Cabeçalho, itens e histórico vão
     * numa transação só — antes uma falha nos itens deixava o orçamento sem eles.
     */
    public function salvar(array $post, array $ctx = [])
    {
        $this->ultimoErro = '';

        try {
            return Transacao::executar($this->conn, function () use ($post, $ctx) {
                $n = $this->normalizarItens((array) ($post['itens'] ?? []));

                $this->conn->prepare("
                    INSERT INTO orcamentos (
                        numero, cliente_id, veiculo_id, mecanico_id, ocorrencia_id, avaliacao_id,
                        km_veiculo, defeito_relatado, diagnostico, observacoes,
                        valor_total_pecas, valor_total_servicos, valor_total_orcamento,
                        valor_mao_obra, comissao_pct, comissao_base,
                        data_criacao, data_validade, prazo_execucao, status
                    ) VALUES (
                        '', :cliente_id, :veiculo_id, :mecanico_id, :ocorrencia_id, :avaliacao_id,
                        :km_veiculo, :defeito_relatado, :diagnostico, :observacoes,
                        :pecas, :servicos, :total,
                        :valor_mao_obra, :comissao_pct, :comissao_base,
                        :data_criacao, :data_validade, :prazo_execucao, 'pendente'
                    )
                ")->execute([
                    ':cliente_id'       => $post['cliente_id']       ?? null,
                    ':veiculo_id'       => $post['veiculo_id']       ?? null,
                    ':mecanico_id'      => !empty($post['mecanico_id']) ? $post['mecanico_id'] : null,
                    ':ocorrencia_id'    => !empty($post['ocorrencia_id']) ? (int) $post['ocorrencia_id'] : null,
                    ':avaliacao_id'     => !empty($post['avaliacao_id']) ? (int) $post['avaliacao_id'] : null,
                    ':km_veiculo'       => !empty($post['km_veiculo'])  ? $post['km_veiculo']  : null,
                    ':defeito_relatado' => $post['defeito_relatado'] ?? null,
                    ':diagnostico'      => $post['diagnostico']      ?? null,
                    ':observacoes'      => $post['observacoes']      ?? null,
                    ':pecas'            => $n['pecas'],
                    ':servicos'         => $n['servicos'],
                    ':total'            => $n['total'],
                    ':valor_mao_obra'   => $this->num($post['valor_mao_obra'] ?? 0),
                    ':comissao_pct'     => $this->num($post['comissao_pct'] ?? 0),
                    ':comissao_base'    => $this->normalizarComissaoBase($post['comissao_base'] ?? null),
                    ':data_criacao'     => str_replace('T', ' ', !empty($post['data_criacao']) ? $post['data_criacao'] : date('Y-m-d H:i:s')),
                    ':data_validade'    => !empty($post['data_validade']) ? $post['data_validade'] : null,
                    ':prazo_execucao'   => $post['prazo_execucao']   ?? null,
                ]);

                $id = (int) $this->conn->lastInsertId();

                $this->conn->prepare("UPDATE orcamentos SET numero = ? WHERE id_orcamento = ?")
                    ->execute(['ORC-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT), $id]);

                $this->gravarItens($id, $n['itens']);

                Historico::registrar($this->conn, 'ORCAMENTO', $id, null, 'pendente', $this->usuarioDe($ctx), 'Orçamento criado');

                return $id;
            });
        } catch (DomainException $e) {
            $this->ultimoErro = $e->getMessage();
            return false;
        } catch (\Throwable $e) {
            error_log('[Orcamento salvar] ' . $e->getMessage());
            $this->ultimoErro = 'Não foi possível salvar o orçamento.';
            return false;
        }
    }

    /**
     * Garante que só um dos 3 valores aceitos pelo ENUM do banco
     * (servicos | pecas | ambos) seja gravado, evitando erro de SQL
     * caso o POST venha vazio, ausente ou adulterado.
     */
    private function normalizarComissaoBase($valor)
    {
        $permitidos = ['servicos', 'pecas', 'ambos'];
        return in_array($valor, $permitidos, true) ? $valor : 'servicos';
    }

    /**
     * Aprova o orçamento e cria a OS correspondente — UMA vez.
     *
     *  - Só a partir de "pendente". Recusado/cancelado é recusado aqui, com o
     *    motivo; aprovado de novo devolve sucesso SEM criar nada (idempotente).
     *  - Validade vencida bloqueia: os preços podem ter mudado.
     *  - O orçamento é travado (FOR UPDATE) e a OS é criada na MESMA conexão e
     *    transação: ou existem a aprovação e a OS, ou nenhuma das duas. (Antes a OS
     *    era criada fora da transação por causa de um deadlock entre duas
     *    conexões; com uma conexão só o problema não existe.) O índice único
     *    uq_ordens_servico_orcamento_id continua como rede de proteção do banco.
     *  - A OS nasce ABERTA ("aguardando execução"), com o técnico do orçamento
     *    atribuído, e NÃO baixa estoque: as peças entram previstas.
     *
     * $ctx: usuario_id, origem (USUARIO | PORTAL), dados (contexto da decisão).
     *
     * @return array{ok:bool, erro:?string, repetido:bool, os_id:?int}
     */
    public function aprovar($id, ?string $assinatura = null, array $ctx = []): array
    {
        $id = (int) $id;
        $resultado = ['ok' => false, 'erro' => null, 'repetido' => false, 'os_id' => null];

        try {
            return Transacao::executar($this->conn, function () use ($id, $assinatura, $ctx, $resultado) {
                $stmt = $this->conn->prepare('SELECT * FROM orcamentos WHERE id_orcamento = ? FOR UPDATE');
                $stmt->execute([$id]);
                $orc = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$orc) {
                    throw new DomainException('Orçamento não encontrado.');
                }

                $osExistente = $this->conn->prepare('SELECT id_ordem_servico FROM ordens_servico WHERE orcamento_id = ? LIMIT 1');
                $osExistente->execute([$id]);
                $osId = $osExistente->fetchColumn();

                if ($orc['status'] === 'aprovado' && $osId !== false) {
                    return array_merge($resultado, ['ok' => true, 'repetido' => true, 'os_id' => (int) $osId]);
                }

                if ($orc['status'] !== 'pendente') {
                    throw new DomainException(
                        'Este orçamento está "' . (self::ROTULOS[$orc['status']] ?? $orc['status'])
                        . '" e não pode ser aprovado.'
                        . ($orc['status'] === 'reprovado' ? ' Reabra o orçamento antes, se o cliente mudou de ideia.' : '')
                    );
                }

                if (!empty($orc['data_validade']) && $orc['data_validade'] < date('Y-m-d')) {
                    throw new DomainException(
                        'Este orçamento venceu em ' . date('d/m/Y', strtotime($orc['data_validade']))
                        . '. Atualize a validade (e confira os valores) antes de aprovar.'
                    );
                }

                $itens = $this->getItensByOrcamento($id);

                if (empty($itens)) {
                    throw new DomainException('O orçamento não tem itens.');
                }

                $os = new OrdemServico($this->conn);

                $osId = $os->create([
                    'orcamento_id'     => $id,
                    'status'           => 'ABERTA',
                    'data_abertura'    => date('Y-m-d H:i:s'),
                    'cliente_id'       => $orc['cliente_id'],
                    'veiculo_id'       => $orc['veiculo_id'],
                    'km_veiculo'       => $orc['km_veiculo'],
                    'defeito_relatado' => $orc['defeito_relatado'],
                    'diagnostico'      => $orc['diagnostico'],
                    'observacoes'      => $orc['observacoes'],
                    'tecnicos'         => $orc['mecanico_id'] ? [(int) $orc['mecanico_id']] : [],
                    'itens'            => $itens,
                    'ocorrencia_id'    => $orc['ocorrencia_id'],
                    // O cliente aprovou ESTE valor: é o teto até uma autorização complementar.
                    'valor_autorizado' => $orc['valor_total_orcamento'],
                    '_usuario_id'      => $this->usuarioDe($ctx),
                ]);

                if (!$osId) {
                    throw new DomainException('Não foi possível criar a OS a partir do orçamento. ' . $os->getLastError());
                }

                // O valor que o cliente aprovou tem que ser o valor da OS. Se os
                // itens não somam o total do orçamento (orçamento antigo, gravado
                // com o total do navegador), não se inventa um valor: pede-se para
                // reabrir e salvar de novo, o que recalcula tudo dos itens.
                $total = $this->conn->prepare('SELECT valor_total_os FROM ordens_servico WHERE id_ordem_servico = ?');
                $total->execute([$osId]);

                if (abs((float) $total->fetchColumn() - (float) $orc['valor_total_orcamento']) > 0.01) {
                    throw new DomainException(
                        'Os itens do orçamento não somam o valor total dele. Abra o orçamento em "Editar" e salve de novo '
                        . 'para recalcular antes de aprovar.'
                    );
                }

                $this->conn->prepare("
                    UPDATE orcamentos
                    SET status = 'aprovado', data_aprovacao = NOW(),
                        assinatura_cliente = COALESCE(?, assinatura_cliente)
                    WHERE id_orcamento = ?
                ")->execute([$assinatura, $id]);

                // A aprovação é uma autorização do cliente: fica registrada com canal e evidência.
                $origem = $ctx['origem'] ?? Historico::ORIGEM_USUARIO;
                (new AutorizacaoCliente($this->conn))->registrarDoOrcamento(
                    $id,
                    (int) $osId,
                    $orc['ocorrencia_id'] ? (int) $orc['ocorrencia_id'] : null,
                    (int) $orc['cliente_id'],
                    (float) $orc['valor_total_orcamento'],
                    $ctx['canal'] ?? ($origem === Historico::ORIGEM_PORTAL ? 'PORTAL' : 'PRESENCIAL'),
                    $this->usuarioDe($ctx),
                    $ctx['evidencia'] ?? ($origem === Historico::ORIGEM_PORTAL
                        ? 'Aprovado pelo cliente no portal'
                        : 'Aprovação registrada no sistema pelo atendimento')
                );

                $this->sincronizarOcorrencia($orc['ocorrencia_id'], 'EM_EXECUCAO', $this->usuarioDe($ctx), 'Orçamento aprovado — OS ' . $this->numeroDaOs((int) $osId) . ' aberta', $origem);

                Historico::registrar(
                    $this->conn,
                    'ORCAMENTO',
                    $id,
                    'pendente',
                    'aprovado',
                    $this->usuarioDe($ctx),
                    'OS ' . $this->numeroDaOs((int) $osId) . ' criada',
                    $ctx['origem'] ?? Historico::ORIGEM_USUARIO,
                    $ctx['dados'] ?? null
                );

                Comunicacao::evento(
                    $this->conn, 'ORCAMENTO_APROVADO', (int) $orc['cliente_id'],
                    Comunicacao::dadosDoVeiculo($this->conn, (int) $orc['veiculo_id']) + ['numero' => $orc['numero']],
                    'ORCAMENTO:' . $id . ':APROVADO', 'ORCAMENTO', $id, ['escopo' => 'OS', 'entidade' => (int) $osId]
                );

                return array_merge($resultado, ['ok' => true, 'os_id' => (int) $osId]);
            });
        } catch (DomainException $e) {
            return array_merge($resultado, ['erro' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[Orcamento aprovar] ' . $e->getMessage());
            return array_merge($resultado, ['erro' => 'Não foi possível aprovar o orçamento. Nada foi alterado.']);
        }
    }

    private function numeroDaOs(int $osId): string
    {
        $stmt = $this->conn->prepare('SELECT numero FROM ordens_servico WHERE id_ordem_servico = ?');
        $stmt->execute([$osId]);

        return (string) $stmt->fetchColumn();
    }

    /**
     * Muda o estado de um orçamento por uma transição simples (recusar, reabrir,
     * cancelar). Aprovar tem método próprio porque cria a OS.
     *
     * Idempotente: pedir o estado em que ele já está devolve sucesso sem repetir
     * nada. Uma transição que não existe (recusar um aprovado, por exemplo — que
     * deixaria um orçamento "recusado" com uma OS viva por trás) é recusada.
     *
     * @return array{ok:bool, erro:?string, repetido:bool}
     */
    private function transicionar(int $id, string $para, array $ctx, ?string $motivo): array
    {
        $resultado = ['ok' => false, 'erro' => null, 'repetido' => false];

        try {
            return Transacao::executar($this->conn, function () use ($id, $para, $ctx, $motivo, $resultado) {
                $stmt = $this->conn->prepare('SELECT status, cliente_id, veiculo_id, numero FROM orcamentos WHERE id_orcamento = ? FOR UPDATE');
                $stmt->execute([$id]);
                $orcAtual = $stmt->fetch(PDO::FETCH_ASSOC);
                $atual = $orcAtual ? $orcAtual['status'] : false;

                if ($atual === false) {
                    throw new DomainException('Orçamento não encontrado.');
                }

                if ($atual === $para) {
                    return array_merge($resultado, ['ok' => true, 'repetido' => true]);
                }

                if (!in_array($para, self::TRANSICOES[$atual] ?? [], true)) {
                    throw new DomainException(sprintf(
                        'Um orçamento "%s" não pode passar para "%s".',
                        strtolower(self::ROTULOS[$atual] ?? $atual),
                        strtolower(self::ROTULOS[$para] ?? $para)
                    ));
                }

                $extra = match ($para) {
                    'reprovado' => ', data_recusa = NOW(), motivo_recusa = :motivo',
                    'pendente'  => ', data_recusa = NULL, motivo_recusa = NULL',
                    default     => '',
                };

                $params = [':status' => $para, ':id' => $id];
                if ($para === 'reprovado') {
                    $params[':motivo'] = $motivo;
                }

                $this->conn->prepare("UPDATE orcamentos SET status = :status{$extra} WHERE id_orcamento = :id")->execute($params);

                // Recusar põe a ocorrência em espera; reabrir volta a orçar; cancelar também
                // libera para um novo orçamento. A ocorrência nunca fica contando outra história.
                $ocorr = $this->conn->prepare('SELECT ocorrencia_id FROM orcamentos WHERE id_orcamento = ?');
                $ocorr->execute([$id]);
                $alvoOcorrencia = ['reprovado' => 'EM_ESPERA', 'pendente' => 'AGUARDANDO_ORCAMENTO', 'cancelado' => 'AGUARDANDO_ORCAMENTO'][$para] ?? null;

                if ($alvoOcorrencia !== null) {
                    $this->sincronizarOcorrencia($ocorr->fetchColumn() ?: null, $alvoOcorrencia, $this->usuarioDe($ctx), $motivo ?: ('Orçamento ' . (self::ROTULOS[$para] ?? $para)), $ctx['origem'] ?? Historico::ORIGEM_USUARIO);
                }

                $historicoId = Historico::registrar(
                    $this->conn,
                    'ORCAMENTO',
                    $id,
                    $atual,
                    $para,
                    $this->usuarioDe($ctx),
                    $motivo,
                    $ctx['origem'] ?? Historico::ORIGEM_USUARIO,
                    $ctx['dados'] ?? null
                );

                if ($para === 'reprovado') {
                    Comunicacao::evento(
                        $this->conn, 'ORCAMENTO_RECUSADO', (int) $orcAtual['cliente_id'],
                        Comunicacao::dadosDoVeiculo($this->conn, (int) $orcAtual['veiculo_id']) + ['numero' => $orcAtual['numero']],
                        'ORCAMENTO:' . $id . ':RECUSADO:' . $historicoId, 'ORCAMENTO', $id
                    );
                }

                return array_merge($resultado, ['ok' => true]);
            });
        } catch (DomainException $e) {
            return array_merge($resultado, ['erro' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[Orcamento transicionar] ' . $e->getMessage());
            return array_merge($resultado, ['erro' => 'Não foi possível concluir a ação.']);
        }
    }

    /**
     * Recusa o orçamento (só enquanto pendente). Não gera OS: o processo fica em
     * espera até alguém reabrir ou cancelar.
     */
    public function recusar($id, $motivo = null, array $ctx = []): array
    {
        return $this->transicionar((int) $id, 'reprovado', $ctx, $motivo !== null ? trim((string) $motivo) : null);
    }

    public function reabrir($id, array $ctx = []): array
    {
        return $this->transicionar((int) $id, 'pendente', $ctx, 'Orçamento reaberto');
    }

    public function cancelar($id, ?string $motivo = null, array $ctx = []): array
    {
        return $this->transicionar((int) $id, 'cancelado', $ctx, $motivo !== null ? trim($motivo) : null);
    }

    /**
     * Registra que o orçamento foi ENVIADO ao cliente (e, com isso, a ocorrência passa a
     * aguardar a aprovação). O envio em si (WhatsApp/e-mail/portal) é outro passo — aqui é
     * o marco no sistema. Idempotente: reenviar não muda a data do primeiro envio.
     *
     * @return array{ok:bool, erro:?string, repetido:bool}
     */
    public function enviar($id, array $ctx = []): array
    {
        $id = (int) $id;

        try {
            return Transacao::executar($this->conn, function () use ($id, $ctx) {
                $stmt = $this->conn->prepare('SELECT status, enviado_em, ocorrencia_id, valor_total_orcamento, cliente_id, veiculo_id, numero FROM orcamentos WHERE id_orcamento = ? FOR UPDATE');
                $stmt->execute([$id]);
                $orc = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$orc) {
                    throw new DomainException('Orçamento não encontrado.');
                }

                if ($orc['status'] !== 'pendente') {
                    throw new DomainException('Só um orçamento pendente pode ser enviado ao cliente.');
                }

                if ((float) $orc['valor_total_orcamento'] <= 0) {
                    throw new DomainException('O orçamento está sem valor. Informe os preços antes de enviar ao cliente.');
                }

                if ($orc['enviado_em'] !== null) {
                    return ['ok' => true, 'erro' => null, 'repetido' => true];
                }

                $this->conn->prepare('UPDATE orcamentos SET enviado_em = NOW() WHERE id_orcamento = ?')->execute([$id]);

                Historico::registrar($this->conn, 'ORCAMENTO', $id, 'pendente', 'pendente', $this->usuarioDe($ctx), 'Enviado ao cliente', $ctx['origem'] ?? Historico::ORIGEM_USUARIO);

                $this->sincronizarOcorrencia($orc['ocorrencia_id'], 'AGUARDANDO_APROVACAO', $this->usuarioDe($ctx), 'Orçamento enviado ao cliente', $ctx['origem'] ?? Historico::ORIGEM_USUARIO);

                // O cliente recebe o link seguro para ver os itens e aprovar ou recusar.
                Comunicacao::evento(
                    $this->conn, 'ORCAMENTO_DISPONIVEL', (int) $orc['cliente_id'],
                    Comunicacao::dadosDoVeiculo($this->conn, (int) $orc['veiculo_id']) + ['numero' => $orc['numero'], 'valor' => (float) $orc['valor_total_orcamento']],
                    'ORCAMENTO:' . $id . ':ENVIADO', 'ORCAMENTO', $id, ['escopo' => 'ORCAMENTO', 'entidade' => $id]
                );

                return ['ok' => true, 'erro' => null, 'repetido' => false];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'repetido' => false];
        } catch (\Throwable $e) {
            error_log('[Orcamento enviar] ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível registrar o envio.', 'repetido' => false];
        }
    }

    /**
     * Leva a ocorrência ligada ao orçamento para $alvo (se houver ocorrência). Dentro da
     * transação de quem chama; recusa de transição desfaz tudo — a ocorrência e o
     * orçamento não podem contar histórias diferentes.
     */
    private function sincronizarOcorrencia($ocorrenciaId, string $alvo, ?int $usuarioId, ?string $motivo, string $origem): void
    {
        if (!$ocorrenciaId) {
            return;
        }

        $r = OcorrenciaFluxo::transicionar($this->conn, (int) $ocorrenciaId, $alvo, $usuarioId, $motivo, $origem);

        if (!$r['ok']) {
            throw new DomainException($r['erro']);
        }
    }

    /** Versões anteriores do orçamento (cada edição guarda o estado de ANTES). */
    public function versoes($id): array
    {
        $stmt = $this->conn->prepare("
            SELECT v.versao, v.criado_em, u.nome AS criado_por_nome, v.dados
            FROM orcamento_versoes v LEFT JOIN usuarios u ON u.id_usuario = v.criado_por
            WHERE v.orcamento_id = ? ORDER BY v.versao DESC
        ");
        $stmt->execute([(int) $id]);

        return array_map(function ($l) {
            $l['dados'] = json_decode($l['dados'], true);
            return $l;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Guarda o estado atual (cabeçalho + itens) como uma versão, antes de uma edição. */
    private function guardarVersao(int $id, ?int $usuarioId): void
    {
        $cab = $this->conn->prepare('SELECT * FROM orcamentos WHERE id_orcamento = ?');
        $cab->execute([$id]);
        $orc = $cab->fetch(PDO::FETCH_ASSOC);

        $itens = $this->conn->prepare('SELECT * FROM orcamento_itens WHERE orcamento_id = ? ORDER BY id_orcamento_item');
        $itens->execute([$id]);

        unset($orc['assinatura_cliente']); // imagem grande e sem relevância para comparar versões

        $this->conn->prepare('INSERT INTO orcamento_versoes (orcamento_id, versao, dados, criado_por) VALUES (?, ?, ?, ?)')
            ->execute([$id, (int) $orc['versao'], json_encode(['orcamento' => $orc, 'itens' => $itens->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE), $usuarioId]);

        $this->conn->prepare('UPDATE orcamentos SET versao = versao + 1, enviado_em = NULL WHERE id_orcamento = ?')->execute([$id]);
    }

    /** Grava as linhas já normalizadas (ver normalizarItens). */
    private function gravarItens(int $orcamentoId, array $itens): void
    {
        if (empty($itens)) {
            return;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO orcamento_itens (
                orcamento_id, item_id, nome, tipo, categoria,
                preco_unitario, quantidade, subtotal, custom, pct
            ) VALUES (
                :orcamento_id, :item_id, :nome, :tipo, :categoria,
                :preco_unitario, :quantidade, :subtotal, :custom, :pct
            )
        ");

        foreach ($itens as $item) {
            $stmt->execute([
                ':orcamento_id'   => $orcamentoId,
                ':item_id'        => $item['item_id'],
                ':nome'           => $item['nome'],
                ':tipo'           => $item['tipo'],
                ':categoria'      => $item['categoria'],
                ':preco_unitario' => $item['preco'],
                ':quantidade'     => $item['qty'],
                ':subtotal'       => $item['subtotal'],
                ':custom'         => $item['custom'],
                ':pct'            => $item['pct'],
            ]);
        }
    }

    /**
     * Edita um orçamento AINDA PENDENTE: dados, itens e totais (recalculados dos
     * itens). O estado não muda por aqui — aprovar, recusar, reabrir e cancelar
     * têm ações próprias. Um orçamento aprovado já virou OS e não se edita:
     * editar o orçamento deixaria dois documentos contando histórias diferentes.
     */
    public function atualizar($id, array $post, array $ctx = []): bool
    {
        $this->ultimoErro = '';
        $id = (int) $id;

        try {
            Transacao::executar($this->conn, function () use ($id, $post, $ctx) {
                $stmt = $this->conn->prepare('SELECT status FROM orcamentos WHERE id_orcamento = ? FOR UPDATE');
                $stmt->execute([$id]);
                $status = $stmt->fetchColumn();

                if ($status === false) {
                    throw new DomainException('Orçamento não encontrado.');
                }

                if ($status !== 'pendente') {
                    throw new DomainException(
                        'Este orçamento está "' . strtolower(self::ROTULOS[$status] ?? $status)
                        . '" e não pode mais ser editado.' . ($status === 'reprovado' ? ' Reabra-o para negociar de novo.' : '')
                    );
                }

                $n = $this->normalizarItens((array) ($post['itens'] ?? []));

                // Quem já viu a versão anterior (enviada ao cliente) não pode ter o documento
                // trocado por baixo: o estado de antes fica guardado e o envio é refeito.
                $this->guardarVersao($id, $this->usuarioDe($ctx));

                $this->conn->prepare("
                    UPDATE orcamentos SET
                        cliente_id            = :cliente_id,
                        veiculo_id            = :veiculo_id,
                        mecanico_id           = :mecanico_id,
                        km_veiculo            = :km_veiculo,
                        defeito_relatado      = :defeito_relatado,
                        diagnostico           = :diagnostico,
                        observacoes           = :observacoes,
                        valor_total_pecas     = :pecas,
                        valor_total_servicos  = :servicos,
                        valor_total_orcamento = :total,
                        valor_mao_obra        = :valor_mao_obra,
                        comissao_pct          = :comissao_pct,
                        comissao_base         = :comissao_base,
                        data_validade         = :data_validade,
                        prazo_execucao        = :prazo_execucao
                    WHERE id_orcamento = :id
                ")->execute([
                    ':id'               => $id,
                    ':cliente_id'       => $post['cliente_id']       ?? null,
                    ':veiculo_id'       => $post['veiculo_id']       ?? null,
                    ':mecanico_id'      => !empty($post['mecanico_id']) ? $post['mecanico_id'] : null,
                    ':km_veiculo'       => !empty($post['km_veiculo']) ? $post['km_veiculo'] : null,
                    ':defeito_relatado' => $post['defeito_relatado'] ?? null,
                    ':diagnostico'      => $post['diagnostico']      ?? null,
                    ':observacoes'      => $post['observacoes']      ?? null,
                    ':pecas'            => $n['pecas'],
                    ':servicos'         => $n['servicos'],
                    ':total'            => $n['total'],
                    ':valor_mao_obra'   => $this->num($post['valor_mao_obra'] ?? 0),
                    ':comissao_pct'     => $this->num($post['comissao_pct'] ?? 0),
                    ':comissao_base'    => $this->normalizarComissaoBase($post['comissao_base'] ?? null),
                    ':data_validade'    => !empty($post['data_validade']) ? $post['data_validade'] : null,
                    ':prazo_execucao'   => $post['prazo_execucao']   ?? null,
                ]);

                $this->conn->prepare('DELETE FROM orcamento_itens WHERE orcamento_id = ?')->execute([$id]);
                $this->gravarItens($id, $n['itens']);

                // Já tinha sido enviado ao cliente? Agora é outro documento: a ocorrência volta a
                // aguardar o orçamento até ele ser reenviado.
                $oc = $this->conn->prepare("
                    SELECT o.id_ocorrencia FROM orcamentos q JOIN ocorrencias o ON o.id_ocorrencia = q.ocorrencia_id
                    WHERE q.id_orcamento = ? AND o.status = 'AGUARDANDO_APROVACAO'
                ");
                $oc->execute([$id]);

                if ($ocorrenciaId = $oc->fetchColumn()) {
                    $this->sincronizarOcorrencia($ocorrenciaId, 'AGUARDANDO_ORCAMENTO', $this->usuarioDe($ctx), 'Orçamento alterado — reenvie ao cliente', Historico::ORIGEM_USUARIO);
                }
            });

            return true;
        } catch (DomainException $e) {
            $this->ultimoErro = $e->getMessage();
            return false;
        } catch (\Throwable $e) {
            error_log('[Orcamento atualizar] ' . $e->getMessage());
            $this->ultimoErro = 'Não foi possível salvar o orçamento.';
            return false;
        }
    }

    /**
     * Só para EXIBIR no formulário. O número de verdade é atribuído pelo servidor
     * ao salvar (ORC-<id>) — dois orçamentos abertos ao mesmo tempo mostram o mesmo
     * "próximo número" e cada um recebe o seu ao ser gravado.
     */
    private function getProximoNumero()
    {
        $max = (int) $this->conn->query("SELECT COALESCE(MAX(id_orcamento), 0) FROM orcamentos")->fetchColumn();

        return 'ORC-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    private function getClientes()
    {
        // "Cliente Balcão" (cliente genérico de balcão) fica sempre no topo da lista
        $stmt = $this->conn->prepare("SELECT id_cliente, nome FROM clientes ORDER BY (nome = 'Cliente Balcão') DESC, nome");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getVeiculos()
    {
        $stmt = $this->conn->prepare("
            SELECT
                v.id_veiculo  AS id,
                v.cliente_id,
                v.placa,
                CONCAT(mv.nome, ' ', mo.nome) AS modelo,
                c.nome        AS cliente
            FROM veiculos v
            INNER JOIN marcas_veiculos mv ON mv.id_marca  = v.marca_id
            INNER JOIN modelos_veiculos mo ON mo.id_modelo = v.modelo_id
            INNER JOIN clientes c          ON c.id_cliente = v.cliente_id
            ORDER BY v.placa
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getMecanicos()
    {
        $stmt = $this->conn->prepare("
            SELECT id_usuario AS id, nome
            FROM usuarios
            WHERE tipo = 'TECNICO'
            ORDER BY nome
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getProdutos()
    {
        $stmt = $this->conn->prepare("
            SELECT
                p.id_produto  AS id,
                p.nome,
                p.preco_venda AS preco,
                cp.nome       AS categoria
            FROM produtos p
            LEFT JOIN categorias_produtos cp ON cp.id_categoria = p.categorias_produtos_id
            WHERE p.ativo = 1
            ORDER BY p.nome
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getServicos()
    {
        return [];
    }

    public function getById($id)
    {
        $stmt = $this->conn->prepare("
            SELECT
                o.id_orcamento,
                o.numero,
                o.cliente_id,
                o.veiculo_id,
                o.mecanico_id,
                o.km_veiculo,
                o.defeito_relatado,
                o.diagnostico,
                o.observacoes,
                o.assinatura_cliente,
                o.valor_total_pecas,
                o.valor_total_servicos,
                o.valor_total_orcamento,
                o.valor_mao_obra,
                o.comissao_pct,
                o.comissao_base,
                o.data_criacao,
                o.data_validade,
                o.prazo_execucao,
                o.status,
                c.nome AS cliente_nome,
                c.tipo_pessoa AS tipo_pessoa_cliente,
                c.cpf AS cpf_cliente,
                c.cnpj AS cnpj_cliente,
                c.telefone AS telefone_cliente,
                c.endereco AS endereco_cliente,
                c.bairro AS bairro_cliente,
                c.cidade AS cidade_cliente,
                c.uf AS uf_cliente,
                c.cep AS cep_cliente,
                v.placa,
                v.ano AS ano_veiculo,
                v.cor AS cor_veiculo,
                mv.nome AS marca_veiculo,
                mo.nome AS modelo_veiculo,
                CONCAT(mv.nome, ' ', mo.nome) AS veiculo_modelo,
                u.nome AS mecanico_nome
            FROM orcamentos o
            LEFT JOIN clientes          c   ON c.id_cliente  = o.cliente_id
            LEFT JOIN veiculos          v   ON v.id_veiculo  = o.veiculo_id
            LEFT JOIN marcas_veiculos   mv  ON mv.id_marca   = v.marca_id
            LEFT JOIN modelos_veiculos  mo  ON mo.id_modelo  = v.modelo_id
            LEFT JOIN usuarios          u   ON u.id_usuario  = o.mecanico_id
            WHERE o.id_orcamento = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getItensByOrcamento($orcamentoId)
    {
        $stmt = $this->conn->prepare("
            SELECT
                item_id        AS id,
                nome,
                tipo,
                categoria,
                preco_unitario AS preco,
                quantidade     AS qty,
                subtotal,
                custom,
                pct
            FROM orcamento_itens
            WHERE orcamento_id = ?
            ORDER BY id_orcamento_item ASC
        ");
        $stmt->execute([$orcamentoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getResumo()
    {
        return [
            'orcamentosPendentes'  => $this->listarPorStatus('pendente'),
            'orcamentosAprovados'  => $this->listarPorStatus('aprovado'),
            'orcamentosRecusados'  => $this->listarPorStatus('reprovado'),
            'totalPendentes'       => $this->contarPorStatus('pendente'),
            'totalAprovados'       => $this->contarPorStatus('aprovado'),
            'totalRecusados'       => $this->contarPorStatus('reprovado'),
            'valorTotalPendente'   => $this->somarPorStatus('pendente'),
            'ticketMedio'          => $this->calcularTicketMedio(),
        ];
    }

    private function listarPorStatus(string $status): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                o.id_orcamento,
                o.numero,
                o.status,
                o.data_criacao,
                o.data_aprovacao,
                o.data_recusa,
                o.data_validade,
                o.valor_total_orcamento AS valor_total,
                c.nome   AS cliente,
                v.placa,
                CONCAT(mv.nome, ' ', mo.nome) AS veiculo,
                u.nome   AS mecanico,
                os.id_ordem_servico AS os_id,
                os.numero           AS os_numero
            FROM orcamentos o
            LEFT JOIN clientes         c  ON c.id_cliente  = o.cliente_id
            LEFT JOIN veiculos         v  ON v.id_veiculo  = o.veiculo_id
            LEFT JOIN marcas_veiculos  mv ON mv.id_marca   = v.marca_id
            LEFT JOIN modelos_veiculos mo ON mo.id_modelo  = v.modelo_id
            LEFT JOIN usuarios         u  ON u.id_usuario  = o.mecanico_id
            LEFT JOIN ordens_servico   os ON os.orcamento_id = o.id_orcamento
            WHERE o.status = ?
            ORDER BY o.data_criacao DESC
        ");
        $stmt->execute([$status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function contarPorStatus(string $status): int
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM orcamentos WHERE status = ?"
        );
        $stmt->execute([$status]);
        return (int) $stmt->fetchColumn();
    }

    private function somarPorStatus(string $status): float
    {
        $stmt = $this->conn->prepare(
            "SELECT COALESCE(SUM(valor_total_orcamento), 0) FROM orcamentos WHERE status = ?"
        );
        $stmt->execute([$status]);
        return (float) $stmt->fetchColumn();
    }

    private function calcularTicketMedio(): float
    {
        $stmt = $this->conn->query(
            "SELECT COALESCE(AVG(valor_total_orcamento), 0)
             FROM orcamentos
             WHERE status IN ('pendente','aprovado')"
        );
        return (float) $stmt->fetchColumn();
    }
}
