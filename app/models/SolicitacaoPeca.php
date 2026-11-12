<?php

require_once __DIR__ . '/../core/Transacao.php';
require_once __DIR__ . '/../core/Historico.php';
require_once __DIR__ . '/Notificacao.php';

/**
 * Solicitação de peça: o pedido de reposição de quem está no balcão ou na bancada.
 *
 * É a NECESSIDADE, não a compra e muito menos a entrada no estoque. Quem decide o que
 * comprar é o administrador; quem confirma que a peça chegou é o recebimento do pedido
 * (PedidoCompra) — só ele mexe no saldo.
 *
 *   SOLICITADA → EM_ANALISE → APROVADA_COMPRA → PEDIDO_REALIZADO → PARCIALMENTE_RECEBIDA → RECEBIDA
 *        └──────────────────────────────┴───────────────→ CANCELADA (com motivo)
 *
 * Os estados PEDIDO_REALIZADO / PARCIALMENTE_RECEBIDA / RECEBIDA são movidos pelo pedido de
 * compra, na mesma transação do recebimento (e voltam se um recebimento é estornado).
 *
 * Idempotência: o mesmo solicitante não abre duas solicitações em aberto para a mesma peça
 * na mesma OS (índice único no banco) — o segundo clique devolve a que já existe.
 */
class SolicitacaoPeca
{
    public const STATUS_LABEL = [
        'SOLICITADA'            => 'Solicitada',
        'EM_ANALISE'            => 'Em análise',
        'APROVADA_COMPRA'       => 'Aprovada para compra',
        'PEDIDO_REALIZADO'      => 'Pedido realizado',
        'PARCIALMENTE_RECEBIDA' => 'Parcialmente recebida',
        'RECEBIDA'              => 'Recebida',
        'CANCELADA'             => 'Cancelada',
    ];

    public const STATUS_BADGE = [
        'SOLICITADA'            => 'badge--warning',
        'EM_ANALISE'            => 'badge--info',
        'APROVADA_COMPRA'       => 'badge--purple',
        'PEDIDO_REALIZADO'      => 'badge--secondary',
        'PARCIALMENTE_RECEBIDA' => 'badge--info',
        'RECEBIDA'              => 'badge--success',
        'CANCELADA'             => 'badge--danger',
    ];

    public const ABERTAS = ['SOLICITADA', 'EM_ANALISE', 'APROVADA_COMPRA', 'PEDIDO_REALIZADO', 'PARCIALMENTE_RECEBIDA'];

    public const URGENCIAS = ['BAIXA' => 'Baixa', 'NORMAL' => 'Normal', 'ALTA' => 'Alta', 'URGENTE' => 'Urgente'];

    private const PESO_URGENCIA = ['URGENTE' => 4, 'ALTA' => 3, 'NORMAL' => 2, 'BAIXA' => 1];

    /** RECEBIDA → anteriores só acontece quando um recebimento é estornado. */
    public const TRANSICOES = [
        'SOLICITADA'            => ['EM_ANALISE', 'APROVADA_COMPRA', 'CANCELADA'],
        'EM_ANALISE'            => ['APROVADA_COMPRA', 'CANCELADA'],
        'APROVADA_COMPRA'       => ['PEDIDO_REALIZADO', 'CANCELADA'],
        'PEDIDO_REALIZADO'      => ['PARCIALMENTE_RECEBIDA', 'RECEBIDA', 'APROVADA_COMPRA', 'CANCELADA'],
        'PARCIALMENTE_RECEBIDA' => ['RECEBIDA', 'PEDIDO_REALIZADO', 'CANCELADA'],
        'RECEBIDA'              => ['PARCIALMENTE_RECEBIDA', 'PEDIDO_REALIZADO'],
        'CANCELADA'             => [],
    ];

    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    public function conexao(): PDO
    {
        return $this->conn;
    }

    public static function rotulo(?string $status): string
    {
        return self::STATUS_LABEL[$status] ?? (string) $status;
    }

    public static function badge(?string $status): string
    {
        return self::STATUS_BADGE[$status] ?? 'badge--neutral';
    }

    /* ── Consulta ────────────────────────────────────────────────────────── */

    private const SELECT_BASE = "
        SELECT s.*,
               p.nome AS produto_nome, p.codigo_interno, p.estoque_atual AS estoque_agora, p.estoque_minimo,
               u.nome AS solicitante_nome, d.nome AS decidido_por_nome,
               os.numero AS os_numero, os.status AS os_status
        FROM solicitacoes_peca s
        LEFT JOIN produtos p        ON p.id_produto = s.produto_id
        LEFT JOIN usuarios u        ON u.id_usuario = s.solicitante_id
        LEFT JOIN usuarios d        ON d.id_usuario = s.decidido_por
        LEFT JOIN ordens_servico os ON os.id_ordem_servico = s.ordem_servico_id
    ";

    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare(self::SELECT_BASE . ' WHERE s.id_solicitacao = ?');
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @param array{grupo?:string,status?:string,urgencia?:string,busca?:string,solicitante_id?:int} $f */
    public function listar(array $f = [], int $limite = 150): array
    {
        $where  = [];
        $params = [];

        if (!empty($f['status']) && isset(self::STATUS_LABEL[$f['status']])) {
            $where[]  = 's.status = ?';
            $params[] = $f['status'];
        } elseif (($f['grupo'] ?? 'abertas') === 'abertas') {
            $where[] = "s.status IN ('" . implode("','", self::ABERTAS) . "')";
        }

        if (!empty($f['urgencia']) && isset(self::URGENCIAS[$f['urgencia']])) {
            $where[]  = 's.urgencia = ?';
            $params[] = $f['urgencia'];
        }

        if (!empty($f['ordem_servico_id'])) {
            $where[]  = 's.ordem_servico_id = ?';
            $params[] = (int) $f['ordem_servico_id'];
        }

        if (!empty($f['solicitante_id'])) {
            $where[]  = 's.solicitante_id = ?';
            $params[] = (int) $f['solicitante_id'];
        }

        if (!empty($f['busca'])) {
            $where[] = '(s.numero LIKE ? OR s.descricao LIKE ? OR p.nome LIKE ? OR os.numero LIKE ?)';
            $t = '%' . trim($f['busca']) . '%';
            array_push($params, $t, $t, $t, $t);
        }

        $sql = self::SELECT_BASE . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY FIELD(s.urgencia, 'URGENTE','ALTA','NORMAL','BAIXA'), s.criada_em DESC LIMIT " . (int) $limite;

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contagemPorStatus(): array
    {
        return array_map('intval', $this->conn->query('SELECT status, COUNT(*) FROM solicitacoes_peca GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    public function linhaDoTempo(int $id): array
    {
        return Historico::da($this->conn, 'SOLICITACAO_PECA', $id);
    }

    /** OS em andamento às quais uma solicitação pode ser ligada (peça que a OS está esperando). */
    public function osAbertas(): array
    {
        return $this->conn->query("
            SELECT os.id_ordem_servico AS id, os.numero, c.nome AS cliente, v.placa
            FROM ordens_servico os
            JOIN clientes c ON c.id_cliente = os.cliente_id
            JOIN veiculos v ON v.id_veiculo = os.veiculo_id
            WHERE os.status IN ('ABERTA','EM_ANDAMENTO','AGUARDANDO_PECA','AGUARDANDO_CONFERENCIA')
            ORDER BY os.id_ordem_servico DESC LIMIT 200
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
    /** Pedidos de compra em que a solicitação já foi incluída. */
    public function pedidosDaSolicitacao(int $id): array
    {
        $stmt = $this->conn->prepare("
            SELECT pc.id_pedido, pc.numero, pc.status, f.nome AS fornecedor_nome, i.quantidade, i.quantidade_recebida
            FROM pedido_compra_itens i
            JOIN pedidos_compra pc ON pc.id_pedido = i.pedido_id
            JOIN fornecedores f ON f.id_fornecedor = pc.fornecedor_id
            WHERE i.solicitacao_id = ?
            ORDER BY pc.id_pedido DESC
        ");
        $stmt->execute([$id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * O que a oficina precisa repor, consolidado por peça (administrador):
     *  - `solicitadas`: o que foi pedido e ainda não virou pedido de compra, somado por peça;
     *  - `abaixo_minimo`: peças cujo saldo disponível (estoque − reservas) está no mínimo ou
     *    abaixo e que ninguém solicitou ainda.
     *
     * @return array{solicitadas:array, abaixo_minimo:array}
     */
    public function necessidades(): array
    {
        $solicitadas = $this->conn->query("
            SELECT s.produto_id,
                   COALESCE(p.nome, MIN(s.descricao)) AS nome,
                   p.codigo_interno,
                   SUM(s.quantidade - s.quantidade_recebida) AS quantidade,
                   COUNT(*) AS qtd_solicitacoes,
                   GROUP_CONCAT(s.id_solicitacao ORDER BY s.id_solicitacao) AS ids,
                   GROUP_CONCAT(s.numero ORDER BY s.id_solicitacao SEPARATOR ', ') AS numeros,
                   MAX(FIELD(s.urgencia, 'BAIXA','NORMAL','ALTA','URGENTE')) AS urgencia_peso,
                   p.estoque_atual, p.estoque_minimo,
                   (SELECT COALESCE(SUM(r.quantidade), 0) FROM ordem_servico_produtos r WHERE r.produto_id = p.id_produto AND r.status = 'RESERVADO') AS reservado
            FROM solicitacoes_peca s
            LEFT JOIN produtos p ON p.id_produto = s.produto_id
            WHERE s.status IN ('SOLICITADA','EM_ANALISE','APROVADA_COMPRA')
            GROUP BY COALESCE(s.produto_id, CONCAT('d:', MD5(LOWER(s.descricao))))
            ORDER BY urgencia_peso DESC, nome
        ")->fetchAll(PDO::FETCH_ASSOC);

        $rotulos = [1 => 'BAIXA', 2 => 'NORMAL', 3 => 'ALTA', 4 => 'URGENTE'];
        foreach ($solicitadas as &$linha) {
            $linha['urgencia'] = $rotulos[(int) $linha['urgencia_peso']] ?? 'NORMAL';
        }
        unset($linha);

        $abaixo = $this->conn->query("
            SELECT p.id_produto AS produto_id, p.nome, p.codigo_interno, p.estoque_atual, p.estoque_minimo,
                   (SELECT COALESCE(SUM(r.quantidade), 0) FROM ordem_servico_produtos r WHERE r.produto_id = p.id_produto AND r.status = 'RESERVADO') AS reservado
            FROM produtos p
            WHERE p.ativo = 1 AND p.estoque_minimo > 0
              AND p.estoque_atual - (SELECT COALESCE(SUM(r.quantidade), 0) FROM ordem_servico_produtos r WHERE r.produto_id = p.id_produto AND r.status = 'RESERVADO') <= p.estoque_minimo
              AND NOT EXISTS (SELECT 1 FROM solicitacoes_peca s WHERE s.produto_id = p.id_produto
                              AND s.status IN ('SOLICITADA','EM_ANALISE','APROVADA_COMPRA','PEDIDO_REALIZADO','PARCIALMENTE_RECEBIDA'))
            ORDER BY p.nome
        ")->fetchAll(PDO::FETCH_ASSOC);

        return ['solicitadas' => $solicitadas, 'abaixo_minimo' => $abaixo];
    }

    /* ── Registro ────────────────────────────────────────────────────────── */

    /**
     * @param array{produto_id?:mixed, descricao?:string, quantidade:mixed, motivo:string,
     *              ordem_servico_id?:mixed, urgencia?:string, observacoes?:string} $d
     * @return array{ok:bool, erro:?string, id:?int, repetido:bool}
     */
    public function registrar(array $d, int $usuarioId): array
    {
        $saida = ['ok' => false, 'erro' => null, 'id' => null, 'repetido' => false];

        try {
            return Transacao::executar($this->conn, function () use ($d, $usuarioId, $saida) {
                $produtoId = !empty($d['produto_id']) ? (int) $d['produto_id'] : null;
                $descricao = trim((string) ($d['descricao'] ?? ''));
                $estoque   = null;

                if ($produtoId !== null) {
                    $p = $this->conn->prepare('SELECT nome, estoque_atual FROM produtos WHERE id_produto = ? AND ativo = 1');
                    $p->execute([$produtoId]);
                    $produto = $p->fetch(PDO::FETCH_ASSOC);

                    if (!$produto) {
                        throw new DomainException('A peça escolhida não existe mais no cadastro.');
                    }

                    // O que vale é o nome e o saldo do cadastro, não o que o formulário mandou.
                    $descricao = $produto['nome'];
                    $estoque   = (float) $produto['estoque_atual'];
                } elseif (mb_strlen($descricao) < 3) {
                    throw new DomainException('Escolha a peça no cadastro ou descreva a peça que precisa ser comprada.');
                }

                $quantidade = round((float) str_replace(',', '.', (string) ($d['quantidade'] ?? 0)), 3);
                if ($quantidade <= 0) {
                    throw new DomainException('Informe a quantidade necessária.');
                }

                $motivo = trim((string) ($d['motivo'] ?? ''));
                if (mb_strlen($motivo) < 3) {
                    throw new DomainException('Explique o motivo da solicitação (ex.: estoque acabando, peça para a OS).');
                }

                $urgencia = $d['urgencia'] ?? 'NORMAL';
                if (!isset(self::URGENCIAS[$urgencia])) {
                    $urgencia = 'NORMAL';
                }

                $osId = !empty($d['ordem_servico_id']) ? (int) $d['ordem_servico_id'] : null;
                if ($osId !== null) {
                    $o = $this->conn->prepare('SELECT status FROM ordens_servico WHERE id_ordem_servico = ?');
                    $o->execute([$osId]);
                    $osStatus = $o->fetchColumn();

                    if ($osStatus === false) {
                        throw new DomainException('A ordem de serviço informada não existe.');
                    }

                    if (in_array($osStatus, ['CONCLUIDA', 'CANCELADA'], true)) {
                        throw new DomainException('Essa ordem de serviço já foi encerrada.');
                    }
                }

                // Já existe uma em aberto igual, deste solicitante? Devolve a existente.
                $existente = $this->conn->prepare("
                    SELECT id_solicitacao FROM solicitacoes_peca
                    WHERE aberta_unica = CONCAT_WS('|', ?, ?, ?, MD5(LOWER(?)))
                ");
                $existente->execute([$usuarioId, $produtoId ?? 0, $osId ?? 0, $descricao]);

                if ($id = $existente->fetchColumn()) {
                    return array_merge($saida, ['ok' => true, 'id' => (int) $id, 'repetido' => true]);
                }

                $this->conn->prepare("
                    INSERT INTO solicitacoes_peca
                        (numero, produto_id, descricao, quantidade, estoque_conhecido, motivo, ordem_servico_id, urgencia, observacoes, solicitante_id)
                    VALUES ('', ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $produtoId, mb_substr($descricao, 0, 200), $quantidade, $estoque, mb_substr($motivo, 0, 255), $osId, $urgencia,
                    trim((string) ($d['observacoes'] ?? '')) ?: null, $usuarioId,
                ]);

                $id = (int) $this->conn->lastInsertId();
                $numero = 'SP-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
                $this->conn->prepare('UPDATE solicitacoes_peca SET numero = ? WHERE id_solicitacao = ?')->execute([$numero, $id]);

                Historico::registrar($this->conn, 'SOLICITACAO_PECA', $id, null, 'SOLICITADA', $usuarioId, $motivo, Historico::ORIGEM_USUARIO, [
                    'quantidade' => $quantidade, 'urgencia' => $urgencia, 'ordem_servico_id' => $osId,
                ]);

                $this->notificarNova($id, $numero, $descricao, $quantidade, $urgencia, $motivo, $osId, $usuarioId);

                return array_merge($saida, ['ok' => true, 'id' => $id]);
            });
        } catch (DomainException $e) {
            return array_merge($saida, ['erro' => $e->getMessage()]);
        } catch (PDOException $e) {
            // Duas requisições simultâneas passaram pela checagem: o índice único segurou a segunda.
            if (str_contains($e->getMessage(), 'uq_solicitacao_aberta')) {
                $existente = $this->conn->prepare("SELECT id_solicitacao FROM solicitacoes_peca WHERE aberta_unica = CONCAT_WS('|', ?, ?, ?, MD5(LOWER(?)))");
                $existente->execute([$usuarioId, !empty($d['produto_id']) ? (int) $d['produto_id'] : 0, !empty($d['ordem_servico_id']) ? (int) $d['ordem_servico_id'] : 0, trim((string) ($d['descricao'] ?? ''))]);

                if ($id = $existente->fetchColumn()) {
                    return array_merge($saida, ['ok' => true, 'id' => (int) $id, 'repetido' => true]);
                }
            }

            error_log('[SolicitacaoPeca registrar] ' . $e->getMessage());
            return array_merge($saida, ['erro' => 'Não foi possível registrar a solicitação.']);
        } catch (\Throwable $e) {
            error_log('[SolicitacaoPeca registrar] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            return array_merge($saida, ['erro' => 'Não foi possível registrar a solicitação.']);
        }
    }

    /**
     * Avisa os administradores e o(s) técnico(s) responsável(is) pela OS, quando há. Não avisa
     * quem fez a solicitação. A chave é a do fato: reprocessar não repete o aviso.
     */
    private function notificarNova(int $id, string $numero, string $peca, float $quantidade, string $urgencia, string $motivo, ?int $osId, int $solicitanteId): void
    {
        $destinos = Notificacao::idsDosAdmins($this->conn, $solicitanteId);

        if ($osId !== null) {
            $t = $this->conn->prepare("
                SELECT tecnico_id FROM ordem_servico_mao_obra WHERE ordem_servico_id = ? AND tecnico_id IS NOT NULL
                UNION
                SELECT oc.tecnico_id FROM ordens_servico os JOIN ocorrencias oc ON oc.id_ocorrencia = os.ocorrencia_id
                WHERE os.id_ordem_servico = ? AND oc.tecnico_id IS NOT NULL
            ");
            $t->execute([$osId, $osId]);

            foreach ($t->fetchAll(PDO::FETCH_COLUMN) as $tecnicoId) {
                if ((int) $tecnicoId !== $solicitanteId) {
                    $destinos[] = (int) $tecnicoId;
                }
            }
        }

        $qtd = rtrim(rtrim(number_format($quantidade, 3, ',', '.'), '0'), ',');

        Notificacao::paraUsuarios(
            $this->conn,
            $destinos,
            'SOLICITACAO_PECA',
            'Nova solicitação de peça ' . $numero . ($urgencia === 'URGENTE' || $urgencia === 'ALTA' ? ' · urgência ' . mb_strtolower(self::URGENCIAS[$urgencia]) : ''),
            $qtd . ' un. de ' . $peca . ' — ' . $motivo,
            './viewSolicitacao?id=' . $id,
            'SOLICITACAO:' . $id . ':CRIADA'
        );
    }

    /* ── Decisões do administrador ───────────────────────────────────────── */

    public function analisar(int $id, int $adminId): array
    {
        return $this->decidir($id, 'EM_ANALISE', $adminId, null);
    }

    public function aprovar(int $id, int $adminId): array
    {
        return $this->decidir($id, 'APROVADA_COMPRA', $adminId, null);
    }

    /**
     * Cancela. O solicitante só cancela a PRÓPRIA solicitação enquanto ninguém analisou
     * (SOLICITADA); o administrador cancela qualquer uma em aberto. Sempre com motivo.
     */
    public function cancelar(int $id, int $usuarioId, string $papel, string $motivo): array
    {
        $motivo = trim($motivo);

        if (mb_strlen($motivo) < 3) {
            return ['ok' => false, 'erro' => 'Informe o motivo do cancelamento.', 'repetido' => false, 'de' => null];
        }

        if ($papel !== 'ADMIN') {
            $s = $this->getById($id);

            if (!$s || (int) $s['solicitante_id'] !== $usuarioId) {
                return ['ok' => false, 'erro' => 'Só quem fez a solicitação (ou o administrador) pode cancelá-la.', 'repetido' => false, 'de' => null];
            }

            if ($s['status'] !== 'SOLICITADA' && $s['status'] !== 'CANCELADA') {
                return ['ok' => false, 'erro' => 'A solicitação já está em andamento; peça ao administrador para cancelar.', 'repetido' => false, 'de' => null];
            }
        }

        return $this->decidir($id, 'CANCELADA', $usuarioId, $motivo);
    }

    private function decidir(int $id, string $para, int $usuarioId, ?string $motivo): array
    {
        try {
            return Transacao::executar($this->conn, function () use ($id, $para, $usuarioId, $motivo) {
                $r = self::transicionar($this->conn, $id, $para, $usuarioId, $motivo);

                if ($r['ok'] && !$r['repetido']) {
                    $this->conn->prepare('UPDATE solicitacoes_peca SET decidido_por = ?, decidido_em = NOW(), motivo_decisao = COALESCE(?, motivo_decisao) WHERE id_solicitacao = ?')
                        ->execute([$usuarioId, $motivo, $id]);

                    $this->avisarSolicitante($id, $para, $motivo, $usuarioId);
                }

                return $r;
            });
        } catch (\Throwable $e) {
            error_log('[SolicitacaoPeca decidir] ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível atualizar a solicitação.', 'repetido' => false, 'de' => null];
        }
    }

    /**
     * Leva a solicitação para $para DENTRO da transação de quem chama. Recusa transição
     * inexistente; repetir é idempotente. Grava a trilha.
     *
     * @return array{ok:bool, erro:?string, repetido:bool, de:?string}
     */
    public static function transicionar(PDO $pdo, int $id, string $para, ?int $usuarioId, ?string $motivo = null, string $origem = Historico::ORIGEM_USUARIO): array
    {
        return Transacao::executar($pdo, function () use ($pdo, $id, $para, $usuarioId, $motivo, $origem) {
            $stmt = $pdo->prepare('SELECT status FROM solicitacoes_peca WHERE id_solicitacao = ? FOR UPDATE');
            $stmt->execute([$id]);
            $de = $stmt->fetchColumn();

            if ($de === false) {
                return ['ok' => false, 'erro' => 'Solicitação não encontrada.', 'repetido' => false, 'de' => null];
            }

            if ($de === $para) {
                return ['ok' => true, 'erro' => null, 'repetido' => true, 'de' => $de];
            }

            if (!in_array($para, self::TRANSICOES[$de] ?? [], true)) {
                return [
                    'ok' => false,
                    'erro' => sprintf('Uma solicitação "%s" não pode passar para "%s".', mb_strtolower(self::rotulo($de)), mb_strtolower(self::rotulo($para))),
                    'repetido' => false,
                    'de' => $de,
                ];
            }

            $pdo->prepare('UPDATE solicitacoes_peca SET status = ? WHERE id_solicitacao = ?')->execute([$para, $id]);
            Historico::registrar($pdo, 'SOLICITACAO_PECA', $id, $de, $para, $usuarioId, $motivo, $origem);

            return ['ok' => true, 'erro' => null, 'repetido' => false, 'de' => $de];
        });
    }

    private function avisarSolicitante(int $id, string $para, ?string $motivo, int $porUsuario): void
    {
        $textos = [
            'EM_ANALISE'      => ['em análise', 'O administrador está analisando.'],
            'APROVADA_COMPRA' => ['aprovada para compra', 'Vai entrar num pedido de compra.'],
            'CANCELADA'       => ['cancelada', $motivo ?? ''],
        ];

        if (!isset($textos[$para])) {
            return;
        }

        $stmt = $this->conn->prepare('SELECT numero, solicitante_id, descricao FROM solicitacoes_peca WHERE id_solicitacao = ?');
        $stmt->execute([$id]);
        $s = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($s && (int) $s['solicitante_id'] !== $porUsuario) {
            Notificacao::criar(
                $this->conn,
                (int) $s['solicitante_id'],
                'SOLICITACAO_PECA',
                'Solicitação ' . $s['numero'] . ' ' . $textos[$para][0],
                $s['descricao'] . ($textos[$para][1] !== '' ? ' — ' . $textos[$para][1] : ''),
                './viewSolicitacao?id=' . $id,
                'SOLICITACAO:' . $id . ':' . $para
            );
        }
    }

    /* ── Chamado pelo pedido de compra ───────────────────────────────────── */

    /**
     * Reajusta a solicitação ao que os pedidos já entregaram: soma o recebido dos itens que
     * a atendem e leva o estado a PEDIDO_REALIZADO / PARCIALMENTE_RECEBIDA / RECEBIDA.
     * Roda na transação do recebimento (ou do estorno dele). Só mexe em solicitação que já
     * está no ciclo do pedido — uma cancelada ou ainda não aprovada fica como está.
     */
    public static function reconciliarComRecebimentos(PDO $pdo, int $solicitacaoId, ?int $usuarioId): void
    {
        $stmt = $pdo->prepare('SELECT status, quantidade, numero, solicitante_id, descricao FROM solicitacoes_peca WHERE id_solicitacao = ? FOR UPDATE');
        $stmt->execute([$solicitacaoId]);
        $s = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$s || !in_array($s['status'], ['PEDIDO_REALIZADO', 'PARCIALMENTE_RECEBIDA', 'RECEBIDA'], true)) {
            return;
        }

        $soma = $pdo->prepare("
            SELECT COALESCE(SUM(i.quantidade_recebida), 0) FROM pedido_compra_itens i
            JOIN pedidos_compra pc ON pc.id_pedido = i.pedido_id
            WHERE i.solicitacao_id = ? AND pc.status <> 'CANCELADO'
        ");
        $soma->execute([$solicitacaoId]);
        $recebido = (float) $soma->fetchColumn();

        $pdo->prepare('UPDATE solicitacoes_peca SET quantidade_recebida = ? WHERE id_solicitacao = ?')->execute([$recebido, $solicitacaoId]);

        $alvo = $recebido + 0.0005 >= (float) $s['quantidade']
            ? 'RECEBIDA'
            : ($recebido > 0 ? 'PARCIALMENTE_RECEBIDA' : 'PEDIDO_REALIZADO');

        if ($alvo === $s['status']) {
            return;
        }

        $r = self::transicionar($pdo, $solicitacaoId, $alvo, $usuarioId, null, Historico::ORIGEM_SISTEMA);

        if ($r['ok'] && !$r['repetido'] && in_array($alvo, ['RECEBIDA', 'PARCIALMENTE_RECEBIDA'], true)) {
            Notificacao::criar(
                $pdo,
                (int) $s['solicitante_id'],
                'SOLICITACAO_PECA',
                $alvo === 'RECEBIDA' ? 'Peça chegou · ' . $s['numero'] : 'Chegou parte da peça · ' . $s['numero'],
                $s['descricao'] . ' — recebido ' . rtrim(rtrim(number_format($recebido, 3, ',', '.'), '0'), ',') . ' de ' . rtrim(rtrim(number_format((float) $s['quantidade'], 3, ',', '.'), '0'), ','),
                './viewSolicitacao?id=' . $solicitacaoId,
                'SOLICITACAO:' . $solicitacaoId . ':' . $alvo . ':' . number_format($recebido, 3, '.', '')
            );
        }
    }
}
