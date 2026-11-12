<?php

require_once __DIR__ . '/../core/Transacao.php';
require_once __DIR__ . '/Comunicacao.php';
require_once __DIR__ . '/Consentimento.php';
require_once __DIR__ . '/../core/Historico.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/OcorrenciaFluxo.php';
require_once __DIR__ . '/Avaliacao.php';
require_once __DIR__ . '/AutorizacaoCliente.php';
require_once __DIR__ . '/OrdemServico.php';

/**
 * Cadastro que já existe (telefone ou placa). Carrega a descrição do existente para a tela
 * oferecer "usar este". É uma DomainException: lançada no meio da transação, desfaz o que
 * já tinha sido gravado (ex.: o cliente provisório criado um instante antes).
 */
class DuplicidadeCadastro extends DomainException
{
    /** @var array<string,mixed> */
    public array $existente;

    public function __construct(string $mensagem, array $existente)
    {
        parent::__construct($mensagem);
        $this->existente = $existente;
    }
}

/**
 * Ocorrência: o relato do cliente sobre UM veículo, do balcão até a OS.
 *
 * É o fio que costura os três fluxos de atendimento sem duplicar nada:
 *   PRESENCIAL  cliente (cadastro completo) chega com o veículo;
 *   RAPIDO      cliente deixa o carro com cadastro mínimo (primeiro nome + telefone);
 *   REMOTO      cliente conhecido pede atendimento por mensagem.
 * Nos três, cliente e veículo são reaproveitados quando já existem — o sistema
 * oferece o cadastro existente em vez de criar outro.
 */
class Ocorrencia
{
    public const PRIORIDADES = [
        'BAIXA'   => 'Baixa',
        'NORMAL'  => 'Normal',
        'ALTA'    => 'Alta (urgência declarada)',
        'URGENTE' => 'Urgente (validada)',
    ];

    public const ORIGENS = [
        'PRESENCIAL' => 'Atendimento presencial',
        'RAPIDO'     => 'Atendimento rápido (cadastro mínimo)',
        'REMOTO'     => 'Atendimento remoto',
    ];

    /** Placa antiga (ABC1234) ou Mercosul (ABC1D23). */
    private const REGEX_PLACA = '/^[A-Z]{3}[0-9][0-9A-Z][0-9]{2}$/';

    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    public function conexao(): PDO
    {
        return $this->conn;
    }

    /* ── Registro da chegada ─────────────────────────────────────────────── */

    /**
     * Registra a chegada: cliente, veículo e ocorrência, numa transação só.
     *
     * $dados:
     *   cliente   ['id' => int]                     cliente já cadastrado
     *             ['nome' => str, 'telefone' => str] cadastro rápido (provisório)
     *   veiculo   ['id' => int]                      veículo já cadastrado
     *             ['placa' => str, marca_id?, modelo_id?, ano?, cor?, combustivel?]
     *   relato, observacoes, prioridade, urgencia (bool), urgencia_motivo,
     *   tecnico_id, origem (PRESENCIAL | RAPIDO | REMOTO), duracao_estimada_min
     *
     * Duplicidade nunca é resolvida em silêncio: se o telefone ou a placa já
     * existem, devolve `duplicidade` descrevendo o cadastro existente e NÃO grava
     * nada — a tela oferece usar o que já existe.
     *
     * @return array{ok:bool, erro:?string, id:?int, cliente_id:?int, veiculo_id:?int, duplicidade:?array}
     */
    public function registrar(array $dados, ?int $usuarioId, ?string $papel = null): array
    {
        $saida = ['ok' => false, 'erro' => null, 'id' => null, 'cliente_id' => null, 'veiculo_id' => null, 'duplicidade' => null];

        try {
            return Transacao::executar($this->conn, function () use ($dados, $usuarioId, $papel, $saida) {
                $relato = trim((string) ($dados['relato'] ?? ''));

                if (mb_strlen($relato) < 3) {
                    throw new DomainException('Descreva o problema relatado pelo cliente.');
                }

                $prioridade = $dados['prioridade'] ?? 'NORMAL';
                if (!isset(self::PRIORIDADES[$prioridade])) {
                    $prioridade = 'NORMAL';
                }

                // Urgência é DECLARADA por quem atende (nível ALTA) e VALIDADA pelo
                // administrador (nível URGENTE). O administrador que registra já valida.
                $urgenciaMotivo = trim((string) ($dados['urgencia_motivo'] ?? ''));
                $validadaPor = null;

                if (!empty($dados['urgencia']) || $prioridade === 'URGENTE' || $prioridade === 'ALTA') {
                    if ($urgenciaMotivo === '') {
                        throw new DomainException('Informe o motivo da urgência.');
                    }

                    if ($papel === 'ADMIN') {
                        $prioridade  = 'URGENTE';
                        $validadaPor = $usuarioId;
                    } else {
                        $prioridade = 'ALTA'; // declarada; o administrador valida depois
                    }
                } else {
                    $urgenciaMotivo = '';
                }

                [$clienteId, $clienteNovo] = $this->resolverCliente((array) ($dados['cliente'] ?? []));

                $veiculoId = $this->resolverVeiculo((array) ($dados['veiculo'] ?? []), $clienteId);

                $tecnicoId = !empty($dados['tecnico_id']) ? (int) $dados['tecnico_id'] : null;
                if ($tecnicoId !== null) {
                    $t = $this->conn->prepare("SELECT 1 FROM usuarios WHERE id_usuario = ? AND tipo = 'TECNICO' AND ativo = 1");
                    $t->execute([$tecnicoId]);

                    if (!$t->fetchColumn()) {
                        throw new DomainException('O técnico escolhido não está disponível.');
                    }
                }

                $origem = $dados['origem'] ?? ($clienteNovo ? 'RAPIDO' : 'PRESENCIAL');
                if (!isset(self::ORIGENS[$origem])) {
                    $origem = 'PRESENCIAL';
                }

                $this->conn->prepare("
                    INSERT INTO ocorrencias
                        (numero, origem, cliente_id, veiculo_id, relato, observacoes, prioridade,
                         urgencia_motivo, urgencia_validada_por, urgencia_validada_em, tecnico_id,
                         criado_por, duracao_estimada_min)
                    VALUES ('', :origem, :cliente, :veiculo, :relato, :obs, :prioridade,
                            :urg, :urg_por, :urg_em, :tecnico, :criador, :duracao)
                ")->execute([
                    ':origem'     => $origem,
                    ':cliente'    => $clienteId,
                    ':veiculo'    => $veiculoId,
                    ':relato'     => $relato,
                    ':obs'        => trim((string) ($dados['observacoes'] ?? '')) ?: null,
                    ':prioridade' => $prioridade,
                    ':urg'        => $urgenciaMotivo !== '' ? $urgenciaMotivo : null,
                    ':urg_por'    => $validadaPor,
                    ':urg_em'     => $validadaPor ? date('Y-m-d H:i:s') : null,
                    ':tecnico'    => $tecnicoId,
                    ':criador'    => $usuarioId,
                    ':duracao'    => !empty($dados['duracao_estimada_min']) ? (int) $dados['duracao_estimada_min'] : null,
                ]);

                $id = (int) $this->conn->lastInsertId();

                $this->conn->prepare('UPDATE ocorrencias SET numero = ? WHERE id_ocorrencia = ?')
                    ->execute(['OC-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT), $id]);

                Historico::registrar(
                    $this->conn,
                    'OCORRENCIA',
                    $id,
                    null,
                    'AGUARDANDO_AVALIACAO',
                    $usuarioId,
                    'Chegada registrada' . ($clienteNovo ? ' (cadastro provisório do cliente)' : ''),
                    Historico::ORIGEM_USUARIO,
                    ['origem' => $origem, 'prioridade' => $prioridade]
                );

                // O cliente que acabou de autorizar os avisos no balcão já recebe o primeiro (a decisão fica no livro).
                if (!empty($dados['consentimento_whatsapp']) && Consentimento::vigente($this->conn, $clienteId, 'WHATSAPP') === null) {
                    Consentimento::registrar($this->conn, $clienteId, 'WHATSAPP', true, 'BALCAO', $usuarioId, 'Autorizou os avisos por WhatsApp ao registrar a chegada');
                }

                Comunicacao::evento(
                    $this->conn, 'VEICULO_RECEBIDO', $clienteId, Comunicacao::dadosDoVeiculo($this->conn, $veiculoId) + ['numero' => 'OC-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT)],
                    'OCORRENCIA:' . $id . ':RECEBIDO', 'OCORRENCIA', $id, ['escopo' => 'OCORRENCIA', 'entidade' => $id]
                );

                return array_merge($saida, ['ok' => true, 'id' => $id, 'cliente_id' => $clienteId, 'veiculo_id' => $veiculoId]);
            });
        } catch (DuplicidadeCadastro $e) {
            return array_merge($saida, ['erro' => $e->getMessage(), 'duplicidade' => $e->existente]);
        } catch (DomainException $e) {
            return array_merge($saida, ['erro' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[Ocorrencia registrar] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            return array_merge($saida, ['erro' => 'Não foi possível registrar a chegada. Nada foi gravado.']);
        }
    }

    /**
     * @return array{0:int, 1:bool} [cliente_id, cadastro novo?]
     * @throws DuplicidadeCadastro se o telefone já pertence a um cliente ativo
     */
    private function resolverCliente(array $c): array
    {
        if (!empty($c['id'])) {
            $stmt = $this->conn->prepare('SELECT id_cliente FROM clientes WHERE id_cliente = ? AND ativo = 1');
            $stmt->execute([(int) $c['id']]);

            if (!$stmt->fetchColumn()) {
                throw new DomainException('Cliente não encontrado ou desativado.');
            }

            return [(int) $c['id'], false];
        }

        $nome     = trim((string) ($c['nome'] ?? ''));
        $telefone = trim((string) ($c['telefone'] ?? ''));

        if (mb_strlen($nome) < 2) {
            throw new DomainException('Informe o nome do cliente (basta o primeiro nome).');
        }

        $validacao = (new Validator(['telefone' => $telefone]))
            ->required('telefone', 'Telefone')
            ->celularBr('telefone', 'Telefone');

        if ($validacao->fails()) {
            throw new DomainException($validacao->firstError());
        }

        $existente = $this->clientePorTelefone($telefone);

        if ($existente !== null) {
            throw new DuplicidadeCadastro(
                'Já existe um cliente ativo com este telefone: ' . $existente['nome'] . '. Use o cadastro dele para não duplicar.',
                ['tipo' => 'cliente'] + $existente
            );
        }

        $this->conn->prepare("
            INSERT INTO clientes (tipo_pessoa, nome, telefone, cadastro_status, ativo)
            VALUES ('PF', ?, ?, 'PROVISORIO', 1)
        ")->execute([mb_substr($nome, 0, 150), $telefone]);

        return [(int) $this->conn->lastInsertId(), true];
    }

    /** Cliente ativo com este telefone (comparado pelos dígitos), ou null. */
    public function clientePorTelefone(string $telefone): ?array
    {
        $digitos = preg_replace('/\D+/', '', $telefone);

        if ($digitos === '') {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT id_cliente AS id, nome, telefone, cadastro_status
            FROM clientes WHERE ativo = 1 AND uniq_telefone = ? LIMIT 1
        ");
        $stmt->execute([$digitos]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @throws DuplicidadeCadastro se a placa já pertence a outro cliente */
    private function resolverVeiculo(array $v, int $clienteId): int
    {
        if (!empty($v['id'])) {
            $stmt = $this->conn->prepare('SELECT id_veiculo, cliente_id FROM veiculos WHERE id_veiculo = ?');
            $stmt->execute([(int) $v['id']]);
            $linha = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$linha) {
                throw new DomainException('Veículo não encontrado.');
            }

            if ((int) $linha['cliente_id'] !== $clienteId) {
                throw new DomainException('Este veículo pertence a outro cliente.');
            }

            return (int) $linha['id_veiculo'];
        }

        $placaBruta = strtoupper(trim((string) ($v['placa'] ?? '')));
        $normalizada = preg_replace('/[^A-Z0-9]/', '', $placaBruta);

        if (!preg_match(self::REGEX_PLACA, $normalizada)) {
            throw new DomainException('Informe uma placa válida (ex.: ABC-1234 ou ABC1D23).');
        }

        $existente = $this->conn->prepare("
            SELECT v.id_veiculo, v.cliente_id, c.nome AS cliente_nome, c.telefone AS cliente_telefone
            FROM veiculos v JOIN clientes c ON c.id_cliente = v.cliente_id
            WHERE v.placa_normalizada = ?
        ");
        $existente->execute([$normalizada]);
        $achado = $existente->fetch(PDO::FETCH_ASSOC);

        if ($achado) {
            if ((int) $achado['cliente_id'] === $clienteId) {
                return (int) $achado['id_veiculo']; // o mesmo veículo do mesmo cliente: reaproveita
            }

            throw new DuplicidadeCadastro(
                'A placa ' . $placaBruta . ' já está cadastrada para ' . $achado['cliente_nome']
                    . '. Se é a mesma pessoa, use o cadastro dela; se o veículo mudou de dono, ajuste o cadastro do veículo.',
                [
                    'tipo' => 'veiculo', 'veiculo_id' => (int) $achado['id_veiculo'],
                    'id' => (int) $achado['cliente_id'], 'nome' => $achado['cliente_nome'], 'telefone' => $achado['cliente_telefone'],
                ]
            );
        }

        [$marcaId, $modeloId, $incompleto] = $this->marcaModelo((int) ($v['marca_id'] ?? 0), (int) ($v['modelo_id'] ?? 0));

        $placaFormatada = preg_match('/^[A-Z]{3}[0-9]{4}$/', $normalizada)
            ? substr($normalizada, 0, 3) . '-' . substr($normalizada, 3)
            : $normalizada;

        $this->conn->prepare("
            INSERT INTO veiculos (cliente_id, marca_id, modelo_id, placa, placa_normalizada, ano, cor, combustivel, dados_incompletos)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $clienteId, $marcaId, $modeloId, $placaFormatada, $normalizada,
            !empty($v['ano']) ? (int) $v['ano'] : null,
            trim((string) ($v['cor'] ?? '')) ?: null,
            trim((string) ($v['combustivel'] ?? '')) ?: null,
            $incompleto ? 1 : 0,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    /** @return array{0:int,1:int,2:bool} [marca, modelo, dados incompletos?] */
    private function marcaModelo(int $marcaId, int $modeloId): array
    {
        if ($marcaId > 0 && $modeloId > 0) {
            $ok = $this->conn->prepare('SELECT 1 FROM modelos_veiculos WHERE id_modelo = ? AND marca_id = ?');
            $ok->execute([$modeloId, $marcaId]);

            if (!$ok->fetchColumn()) {
                throw new DomainException('O modelo informado não pertence à marca escolhida.');
            }

            return [$marcaId, $modeloId, false];
        }

        $marca = $this->conn->query("SELECT id_marca FROM marcas_veiculos WHERE nome = 'Não informado' LIMIT 1")->fetchColumn();
        $modelo = $marca ? $this->conn->query("SELECT id_modelo FROM modelos_veiculos WHERE marca_id = {$marca} AND nome = 'Não informado' LIMIT 1")->fetchColumn() : false;

        if (!$marca || !$modelo) {
            throw new DomainException('Informe a marca e o modelo do veículo.');
        }

        return [(int) $marca, (int) $modelo, true];
    }

    /* ── Consulta ────────────────────────────────────────────────────────── */

    private const SELECT_BASE = "
        SELECT o.*,
               c.nome AS cliente_nome, c.telefone AS cliente_telefone, c.cadastro_status AS cliente_cadastro,
               v.placa, v.dados_incompletos AS veiculo_incompleto,
               CONCAT(mv.nome, ' ', mo.nome) AS veiculo_modelo,
               t.nome AS tecnico_nome, cr.nome AS criador_nome,
               os.id_ordem_servico AS os_id, os.numero AS os_numero, os.status AS os_status,
               orc.id_orcamento AS orcamento_id, orc.numero AS orcamento_numero, orc.status AS orcamento_status
        FROM ocorrencias o
        JOIN clientes c ON c.id_cliente = o.cliente_id
        JOIN veiculos v ON v.id_veiculo = o.veiculo_id
        JOIN marcas_veiculos mv ON mv.id_marca = v.marca_id
        JOIN modelos_veiculos mo ON mo.id_modelo = v.modelo_id
        LEFT JOIN usuarios t  ON t.id_usuario  = o.tecnico_id
        LEFT JOIN usuarios cr ON cr.id_usuario = o.criado_por
        LEFT JOIN ordens_servico os ON os.ocorrencia_id = o.id_ocorrencia
        LEFT JOIN orcamentos orc ON orc.id_orcamento = (
            SELECT MAX(o2.id_orcamento) FROM orcamentos o2 WHERE o2.ocorrencia_id = o.id_ocorrencia
        )
    ";

    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare(self::SELECT_BASE . ' WHERE o.id_ocorrencia = ?');
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * @param array{status?:string, busca?:string, tecnico_id?:int, prioridade?:string, abertas?:bool} $filtros
     */
    public function listar(array $filtros = [], int $limite = 100): array
    {
        $where  = [];
        $params = [];

        if (!empty($filtros['status'])) {
            $where[]  = 'o.status = ?';
            $params[] = $filtros['status'];
        } elseif (!empty($filtros['abertas'])) {
            $where[] = "o.status IN ('" . implode("','", OcorrenciaFluxo::ABERTAS) . "')";
        }

        if (!empty($filtros['tecnico_id'])) {
            $where[]  = 'o.tecnico_id = ?';
            $params[] = (int) $filtros['tecnico_id'];
        }

        if (!empty($filtros['prioridade'])) {
            $where[]  = 'o.prioridade = ?';
            $params[] = $filtros['prioridade'];
        }

        if (!empty($filtros['busca'])) {
            $where[] = '(o.numero LIKE ? OR c.nome LIKE ? OR c.telefone LIKE ? OR v.placa LIKE ? OR o.relato LIKE ?)';
            $termo = '%' . trim($filtros['busca']) . '%';
            array_push($params, $termo, $termo, $termo, $termo, $termo);
        }

        $sql = self::SELECT_BASE . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY o.chegada_em DESC, o.id_ocorrencia DESC LIMIT ' . (int) $limite;

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Contagem por estado, para os cartões da tela. */
    public function contagemPorStatus(): array
    {
        $linhas = $this->conn->query('SELECT status, COUNT(*) AS n FROM ocorrencias GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);

        return array_map('intval', $linhas);
    }

    public function linhaDoTempo(int $id): array
    {
        return Historico::da($this->conn, 'OCORRENCIA', $id);
    }

    /* ── Alterações simples ──────────────────────────────────────────────── */

    /** Administrador valida a urgência declarada por quem atendeu (ALTA → URGENTE). */
    public function validarUrgencia(int $id, int $adminId): array
    {
        try {
            return Transacao::executar($this->conn, function () use ($id, $adminId) {
                $stmt = $this->conn->prepare('SELECT prioridade, status FROM ocorrencias WHERE id_ocorrencia = ? FOR UPDATE');
                $stmt->execute([$id]);
                $o = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$o) {
                    throw new DomainException('Ocorrência não encontrada.');
                }

                if ($o['prioridade'] === 'URGENTE') {
                    return ['ok' => true, 'erro' => null, 'repetido' => true];
                }

                if ($o['prioridade'] !== 'ALTA') {
                    throw new DomainException('Só uma urgência declarada pode ser validada.');
                }

                $this->conn->prepare("
                    UPDATE ocorrencias SET prioridade = 'URGENTE', urgencia_validada_por = ?, urgencia_validada_em = NOW()
                    WHERE id_ocorrencia = ?
                ")->execute([$adminId, $id]);

                Historico::registrar($this->conn, 'OCORRENCIA', $id, 'ALTA', 'URGENTE', $adminId, 'Urgência validada pelo administrador');

                return ['ok' => true, 'erro' => null, 'repetido' => false];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'repetido' => false];
        }
    }

    /** Atribui (ou troca) o técnico responsável. */
    public function atribuirTecnico(int $id, ?int $tecnicoId, ?int $usuarioId): array
    {
        try {
            return Transacao::executar($this->conn, function () use ($id, $tecnicoId, $usuarioId) {
                if ($tecnicoId !== null) {
                    $t = $this->conn->prepare("SELECT nome FROM usuarios WHERE id_usuario = ? AND tipo = 'TECNICO' AND ativo = 1");
                    $t->execute([$tecnicoId]);
                    $nome = $t->fetchColumn();

                    if ($nome === false) {
                        throw new DomainException('O técnico escolhido não está disponível.');
                    }
                }

                $stmt = $this->conn->prepare('SELECT status FROM ocorrencias WHERE id_ocorrencia = ? FOR UPDATE');
                $stmt->execute([$id]);
                $status = $stmt->fetchColumn();

                if ($status === false) {
                    throw new DomainException('Ocorrência não encontrada.');
                }

                if (in_array($status, ['ENCERRADA', 'CANCELADA'], true)) {
                    throw new DomainException('Esta ocorrência já foi encerrada.');
                }

                $this->conn->prepare('UPDATE ocorrencias SET tecnico_id = ? WHERE id_ocorrencia = ?')->execute([$tecnicoId, $id]);

                Historico::registrar(
                    $this->conn, 'OCORRENCIA', $id, (string) $status, (string) $status, $usuarioId,
                    $tecnicoId ? 'Técnico responsável: ' . $nome : 'Técnico responsável removido'
                );

                return ['ok' => true, 'erro' => null];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    /**
     * Fluxo rápido: abre a OS DIRETO, sem orçamento formal, quando há
     *   (1) avaliação técnica concluída (os dados técnicos estão registrados) e
     *   (2) autorização do cliente com valor em aberto, registrada com canal e evidência.
     *
     * A autorização não vira cheque em branco: o teto da OS (`valor_autorizado`) é o limite
     * que o cliente acordou — ou, se não houve limite, o total do que foi AVALIADO. Se o
     * total avaliado já passa do limite acordado, a OS não abre (é preciso nova autorização
     * ou um orçamento comum). Qualquer crescimento depois disso exige autorização
     * complementar (ver AutorizacaoCliente).
     *
     * @return array{ok:bool, erro:?string, os_id:?int, repetido:bool}
     */
    public function abrirOsDireta(int $ocorrenciaId, ?int $usuarioId): array
    {
        $vazio = ['ok' => false, 'erro' => null, 'os_id' => null, 'repetido' => false];

        try {
            return Transacao::executar($this->conn, function () use ($ocorrenciaId, $usuarioId, $vazio) {
                $stmt = $this->conn->prepare('SELECT * FROM ocorrencias WHERE id_ocorrencia = ? FOR UPDATE');
                $stmt->execute([$ocorrenciaId]);
                $oc = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$oc) {
                    throw new DomainException('Ocorrência não encontrada.');
                }

                $jaTem = $this->conn->prepare('SELECT id_ordem_servico FROM ordens_servico WHERE ocorrencia_id = ?');
                $jaTem->execute([$ocorrenciaId]);

                if ($osId = $jaTem->fetchColumn()) {
                    return array_merge($vazio, ['ok' => true, 'os_id' => (int) $osId, 'repetido' => true]);
                }

                if ($oc['status'] !== 'AGUARDANDO_ORCAMENTO') {
                    throw new DomainException('Só dá para abrir a OS depois da avaliação técnica concluída (a ocorrência está "' . mb_strtolower(OcorrenciaFluxo::rotulo($oc['status'])) . '").');
                }

                $avaliacoes = new Avaliacao($this->conn);
                $av = $avaliacoes->ultima($ocorrenciaId);

                if (!$av || $av['status'] !== 'CONCLUIDA') {
                    throw new DomainException('Conclua a avaliação técnica antes de abrir a OS.');
                }

                $autorizacao = (new AutorizacaoCliente($this->conn))->ativaDaOcorrencia($ocorrenciaId);

                if (!$autorizacao) {
                    throw new DomainException('Registre a autorização do cliente (canal e evidência) antes de abrir a OS sem orçamento.');
                }

                $itens = $avaliacoes->itensParaDocumento((int) $av['id_avaliacao']);

                if (empty($itens)) {
                    throw new DomainException('A avaliação não tem serviços ou peças para executar.');
                }

                $total = 0.0;
                foreach ($itens as $i) {
                    $total += $i['qty'] * $i['preco'];
                }
                $total = round($total, 2);

                if ($total <= 0) {
                    throw new DomainException('Informe os valores dos serviços na avaliação: a OS não pode abrir com valor zero.');
                }

                $limite = $autorizacao['limite_valor'] !== null ? (float) $autorizacao['limite_valor'] : null;

                if ($limite !== null && $total > $limite + 0.005) {
                    throw new DomainException(sprintf(
                        'O total avaliado (R$ %s) passa do limite que o cliente autorizou (R$ %s). Registre nova autorização ou envie um orçamento.',
                        number_format($total, 2, ',', '.'),
                        number_format($limite, 2, ',', '.')
                    ));
                }

                $os = new OrdemServico($this->conn);
                $osId = $os->create([
                    'status'           => 'ABERTA',
                    'cliente_id'       => $oc['cliente_id'],
                    'veiculo_id'       => $oc['veiculo_id'],
                    'defeito_relatado' => $oc['relato'],
                    'diagnostico'      => $av['defeitos'],
                    'observacoes'      => $av['observacoes'],
                    'tecnicos'         => $oc['tecnico_id'] ? [(int) $oc['tecnico_id']] : [],
                    'itens'            => $itens,
                    'ocorrencia_id'    => $ocorrenciaId,
                    // Sem limite acordado, o teto é o que foi avaliado: o resto exige complemento.
                    'valor_autorizado' => $limite ?? $total,
                    '_usuario_id'      => $usuarioId,
                ]);

                if (!$osId) {
                    throw new DomainException('Não foi possível abrir a OS: ' . $os->getLastError());
                }

                $this->conn->prepare('UPDATE autorizacoes_cliente SET ordem_servico_id = ? WHERE id_autorizacao = ?')
                    ->execute([$osId, $autorizacao['id_autorizacao']]);

                $r = OcorrenciaFluxo::transicionar($this->conn, $ocorrenciaId, 'EM_EXECUCAO', $usuarioId, 'OS aberta direto, com autorização do cliente');

                if (!$r['ok']) {
                    throw new DomainException($r['erro']);
                }

                return array_merge($vazio, ['ok' => true, 'os_id' => (int) $osId]);
            });
        } catch (DomainException $e) {
            return array_merge($vazio, ['erro' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[Ocorrencia abrirOsDireta] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            return array_merge($vazio, ['erro' => 'Não foi possível abrir a OS. Nada foi alterado.']);
        }
    }

    /** Cancela a ocorrência (desistência do cliente, cadastro por engano…). Exige motivo. */
    public function cancelar(int $id, ?int $usuarioId, string $motivo): array
    {
        $motivo = trim($motivo);

        if (mb_strlen($motivo) < 3) {
            return ['ok' => false, 'erro' => 'Informe o motivo do cancelamento.', 'repetido' => false, 'de' => null];
        }

        return OcorrenciaFluxo::transicionar($this->conn, $id, 'CANCELADA', $usuarioId, $motivo);
    }

    /** Técnicos ativos, para o seletor de responsável. */
    public function tecnicos(): array
    {
        return $this->conn->query("SELECT id_usuario AS id, nome FROM usuarios WHERE tipo = 'TECNICO' AND ativo = 1 ORDER BY nome")
            ->fetchAll(PDO::FETCH_ASSOC);
    }
}
