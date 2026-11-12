<?php

require_once __DIR__ . '/../core/Transacao.php';
require_once __DIR__ . '/../core/WhatsAppCloud.php';

/**
 * Conversas com clientes (a central de atendimento).
 *
 * Existe no máximo UMA conversa ABERTA por canal + cliente + contato (índice único no banco): uma
 * mensagem nova cai na conversa que já está aberta em vez de abrir outra. Conversa encerrada não
 * recebe mais mensagens — a próxima abre uma nova.
 *
 * Mensagem de um número que não é de nenhum cliente cadastrado cria a conversa SEM cliente
 * ("número não identificado"); o atendente associa ao cadastro certo (ou cria um provisório).
 */
final class Conversa
{
    public const STATUS_LABEL = ['ABERTA' => 'Aberta', 'AGUARDANDO_CLIENTE' => 'Aguardando o cliente', 'ENCERRADA' => 'Encerrada'];
    public const STATUS_BADGE = ['ABERTA' => 'badge--warning', 'AGUARDANDO_CLIENTE' => 'badge--info', 'ENCERRADA' => 'badge--neutral'];
    public const CANAL_LABEL = ['WHATSAPP' => 'WhatsApp', 'EMAIL' => 'E-mail', 'MANUAL' => 'Registro manual'];

    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    /** Cliente ativo cujo telefone (só dígitos, com ou sem 55) bate com o do contato. */
    public static function clientePorTelefone(PDO $pdo, string $telefoneDigitos): ?int
    {
        $d = preg_replace('/\D+/', '', $telefoneDigitos);
        $sem55 = str_starts_with($d, '55') && strlen($d) > 11 ? substr($d, 2) : $d;

        $stmt = $pdo->prepare('SELECT id_cliente FROM clientes WHERE ativo = 1 AND uniq_telefone IN (?, ?) ORDER BY id_cliente LIMIT 1');
        $stmt->execute([$d, $sem55]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * A conversa aberta do canal+cliente+contato, criando se não houver.
     *
     * @return int id da conversa
     */
    public static function abertaOuNova(PDO $pdo, ?int $clienteId, string $canal, ?string $identificador, ?string $nomeContato = null, ?string $assunto = null): int
    {
        $busca = $pdo->prepare("SELECT id_conversa FROM conversas WHERE aberta_unica = CONCAT(?, '|', ?, '|', ?)");
        $busca->execute([$canal, $clienteId ?? 0, $identificador ?? '']);

        if ($id = $busca->fetchColumn()) {
            return (int) $id;
        }

        try {
            $pdo->prepare('INSERT INTO conversas (cliente_id, canal, identificador, nome_contato, assunto, ultima_mensagem_em) VALUES (?, ?, ?, ?, ?, NOW())')
                ->execute([$clienteId, $canal, $identificador, $nomeContato !== null ? mb_substr($nomeContato, 0, 150) : null, $assunto !== null ? mb_substr($assunto, 0, 160) : null]);
        } catch (PDOException $e) {
            // Duas mensagens simultâneas do mesmo contato: o índice único segurou a segunda.
            if (str_contains($e->getMessage(), 'uq_conversa_aberta')) {
                $busca->execute([$canal, $clienteId ?? 0, $identificador ?? '']);

                if ($id = $busca->fetchColumn()) {
                    return (int) $id;
                }
            }

            throw $e;
        }

        return (int) $pdo->lastInsertId();
    }

    /* ── Consulta (a central) ────────────────────────────────────────────── */

    private const SELECT_BASE = "
        SELECT c.*, cl.nome AS cliente_nome, cl.telefone AS cliente_telefone, cl.cadastro_status AS cliente_cadastro,
               v.placa, CONCAT(mv.nome, ' ', mo.nome) AS veiculo_modelo,
               u.nome AS responsavel_nome,
               oc.numero AS ocorrencia_numero, orc.numero AS orcamento_numero, os.numero AS os_numero,
               (SELECT m.corpo FROM mensagens m WHERE m.conversa_id = c.id_conversa ORDER BY m.id_mensagem DESC LIMIT 1) AS ultima_mensagem,
               (SELECT COUNT(*) FROM mensagens m WHERE m.conversa_id = c.id_conversa AND m.status IN ('FALHA','NAO_CONFIGURADO','SEM_CONSENTIMENTO','SEM_CONTATO')) AS problemas
        FROM conversas c
        LEFT JOIN clientes cl ON cl.id_cliente = c.cliente_id
        LEFT JOIN veiculos v ON v.id_veiculo = c.veiculo_id
        LEFT JOIN marcas_veiculos mv ON mv.id_marca = v.marca_id
        LEFT JOIN modelos_veiculos mo ON mo.id_modelo = v.modelo_id
        LEFT JOIN usuarios u ON u.id_usuario = c.responsavel_id
        LEFT JOIN ocorrencias oc ON oc.id_ocorrencia = c.ocorrencia_id
        LEFT JOIN orcamentos orc ON orc.id_orcamento = c.orcamento_id
        LEFT JOIN ordens_servico os ON os.id_ordem_servico = c.ordem_servico_id
    ";

    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare(self::SELECT_BASE . ' WHERE c.id_conversa = ?');
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @param array{status?:string, grupo?:string, responsavel_id?:int, sem_responsavel?:bool, busca?:string, cliente_id?:int, nao_lidas?:bool} $f */
    public function listar(array $f = [], int $limite = 150): array
    {
        $where = [];
        $params = [];

        if (!empty($f['status']) && isset(self::STATUS_LABEL[$f['status']])) {
            $where[] = 'c.status = ?';
            $params[] = $f['status'];
        } elseif (($f['grupo'] ?? 'abertas') === 'abertas') {
            $where[] = "c.status <> 'ENCERRADA'";
        }

        if (!empty($f['responsavel_id'])) {
            $where[] = 'c.responsavel_id = ?';
            $params[] = (int) $f['responsavel_id'];
        } elseif (!empty($f['sem_responsavel'])) {
            $where[] = 'c.responsavel_id IS NULL';
        }

        if (!empty($f['cliente_id'])) {
            $where[] = 'c.cliente_id = ?';
            $params[] = (int) $f['cliente_id'];
        }

        if (!empty($f['nao_lidas'])) {
            $where[] = 'c.nao_lidas > 0';
        }

        if (!empty($f['busca'])) {
            $where[] = '(cl.nome LIKE ? OR c.nome_contato LIKE ? OR c.identificador LIKE ? OR v.placa LIKE ? OR c.assunto LIKE ? OR os.numero LIKE ?)';
            $t = '%' . trim($f['busca']) . '%';
            array_push($params, $t, $t, $t, $t, $t, $t);
        }

        $stmt = $this->conn->prepare(self::SELECT_BASE . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY c.nao_lidas > 0 DESC, c.ultima_mensagem_em DESC LIMIT ' . (int) $limite);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contagemPorStatus(): array
    {
        return array_map('intval', $this->conn->query('SELECT status, COUNT(*) FROM conversas GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    public function totalNaoLidas(): int
    {
        return (int) $this->conn->query("SELECT COALESCE(SUM(nao_lidas), 0) FROM conversas WHERE status <> 'ENCERRADA'")->fetchColumn();
    }

    public function mensagens(int $conversaId): array
    {
        $stmt = $this->conn->prepare("
            SELECT m.*, u.nome AS autor_nome FROM mensagens m LEFT JOIN usuarios u ON u.id_usuario = m.autor_id
            WHERE m.conversa_id = ? ORDER BY m.id_mensagem ASC
        ");
        $stmt->execute([$conversaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Contexto do cliente para o atendente entender o que está em jogo sem sair da conversa. */
    public function contextoDoCliente(int $clienteId): array
    {
        $q = fn(string $sql) => (function () use ($sql, $clienteId) {
            $s = $this->conn->prepare($sql);
            $s->execute([$clienteId]);
            return $s->fetchAll(PDO::FETCH_ASSOC);
        })();

        return [
            'veiculos' => $q("SELECT v.id_veiculo, v.placa, CONCAT(mv.nome, ' ', mo.nome) AS modelo FROM veiculos v JOIN marcas_veiculos mv ON mv.id_marca = v.marca_id JOIN modelos_veiculos mo ON mo.id_modelo = v.modelo_id WHERE v.cliente_id = ? ORDER BY v.placa"),
            'ocorrencias' => $q("SELECT id_ocorrencia, numero, status, relato FROM ocorrencias WHERE cliente_id = ? ORDER BY id_ocorrencia DESC LIMIT 5"),
            'orcamentos' => $q("SELECT id_orcamento, numero, status, valor_total_orcamento FROM orcamentos WHERE cliente_id = ? ORDER BY id_orcamento DESC LIMIT 5"),
            'ordens' => $q("SELECT id_ordem_servico, numero, status, valor_total_os FROM ordens_servico WHERE cliente_id = ? ORDER BY id_ordem_servico DESC LIMIT 5"),
        ];
    }

    /* ── Ações ───────────────────────────────────────────────────────────── */

    public function marcarLida(int $id): void
    {
        $this->conn->prepare('UPDATE conversas SET nao_lidas = 0 WHERE id_conversa = ?')->execute([$id]);
    }

    /** @return array{ok:bool, erro:?string} */
    public function atribuir(int $id, ?int $responsavelId): array
    {
        if ($responsavelId !== null) {
            $u = $this->conn->prepare("SELECT 1 FROM usuarios WHERE id_usuario = ? AND ativo = 1");
            $u->execute([$responsavelId]);

            if (!$u->fetchColumn()) {
                return ['ok' => false, 'erro' => 'Responsável não encontrado.'];
            }
        }

        $this->conn->prepare('UPDATE conversas SET responsavel_id = ? WHERE id_conversa = ?')->execute([$responsavelId, $id]);

        return ['ok' => true, 'erro' => null];
    }

    /**
     * ENCERRADA libera o "slot" da conversa aberta; reabrir só é possível se não houver outra
     * aberta com o mesmo canal+cliente+contato (o índice único recusa — a mensagem diz isso).
     *
     * @return array{ok:bool, erro:?string}
     */
    public function mudarStatus(int $id, string $status): array
    {
        if (!isset(self::STATUS_LABEL[$status])) {
            return ['ok' => false, 'erro' => 'Situação inválida.'];
        }

        try {
            $this->conn->prepare('UPDATE conversas SET status = ? WHERE id_conversa = ?')->execute([$status, $id]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'uq_conversa_aberta')) {
                return ['ok' => false, 'erro' => 'Já existe outra conversa aberta com este contato. Use aquela.'];
            }

            throw $e;
        }

        return ['ok' => true, 'erro' => null];
    }

    /**
     * Liga a conversa a ocorrência/orçamento/OS/veículo — todos precisam ser do MESMO cliente da
     * conversa (ligar o caso de outro cliente vazaria dado de um para o outro).
     *
     * @param array{veiculo_id?:?int, ocorrencia_id?:?int, orcamento_id?:?int, ordem_servico_id?:?int} $vinculos
     * @return array{ok:bool, erro:?string}
     */
    public function vincular(int $id, array $vinculos): array
    {
        $c = $this->conn->prepare('SELECT cliente_id FROM conversas WHERE id_conversa = ?');
        $c->execute([$id]);
        $clienteId = $c->fetchColumn();

        if ($clienteId === false) {
            return ['ok' => false, 'erro' => 'Conversa não encontrada.'];
        }

        if ($clienteId === null) {
            return ['ok' => false, 'erro' => 'Identifique o cliente desta conversa antes de ligá-la a um atendimento.'];
        }

        $tabelas = ['veiculo_id' => ['veiculos', 'id_veiculo'], 'ocorrencia_id' => ['ocorrencias', 'id_ocorrencia'],
                    'orcamento_id' => ['orcamentos', 'id_orcamento'], 'ordem_servico_id' => ['ordens_servico', 'id_ordem_servico']];
        $sets = [];
        $params = [];

        foreach ($tabelas as $campo => [$tabela, $pk]) {
            if (!array_key_exists($campo, $vinculos)) {
                continue;
            }

            $valor = $vinculos[$campo] ? (int) $vinculos[$campo] : null;

            if ($valor !== null) {
                $ok = $this->conn->prepare("SELECT 1 FROM {$tabela} WHERE {$pk} = ? AND cliente_id = ?");
                $ok->execute([$valor, $clienteId]);

                if (!$ok->fetchColumn()) {
                    return ['ok' => false, 'erro' => 'Esse registro não pertence ao cliente desta conversa.'];
                }
            }

            $sets[] = "{$campo} = ?";
            $params[] = $valor;
        }

        if ($sets) {
            $params[] = $id;
            $this->conn->prepare('UPDATE conversas SET ' . implode(', ', $sets) . ' WHERE id_conversa = ?')->execute($params);
        }

        return ['ok' => true, 'erro' => null];
    }

    /** Associa uma conversa "número não identificado" ao cadastro certo (o histórico vai junto). */
    public function associarCliente(int $id, int $clienteId): array
    {
        return Transacao::executar($this->conn, function () use ($id, $clienteId) {
            $c = $this->conn->prepare('SELECT * FROM conversas WHERE id_conversa = ? FOR UPDATE');
            $c->execute([$id]);
            $conversa = $c->fetch(PDO::FETCH_ASSOC);

            if (!$conversa) {
                return ['ok' => false, 'erro' => 'Conversa não encontrada.'];
            }

            $cli = $this->conn->prepare('SELECT 1 FROM clientes WHERE id_cliente = ? AND ativo = 1');
            $cli->execute([$clienteId]);

            if (!$cli->fetchColumn()) {
                return ['ok' => false, 'erro' => 'Cliente não encontrado ou desativado.'];
            }

            // Já existe uma conversa aberta deste cliente neste contato? Então esta se junta a ela.
            $destino = $this->conn->prepare("SELECT * FROM conversas WHERE aberta_unica = CONCAT(?, '|', ?, '|', ?) AND id_conversa <> ? FOR UPDATE");
            $destino->execute([$conversa['canal'], $clienteId, $conversa['identificador'] ?? '', $id]);

            if ($alvo = $destino->fetch(PDO::FETCH_ASSOC)) {
                $maisRecente = function (?string $a, ?string $b): ?string {
                    $ts = array_filter([$a ? strtotime($a) : null, $b ? strtotime($b) : null]);
                    return $ts ? date('Y-m-d H:i:s', max($ts)) : null;
                };

                $this->conn->prepare('UPDATE mensagens SET conversa_id = ? WHERE conversa_id = ?')->execute([$alvo['id_conversa'], $id]);
                $this->conn->prepare('UPDATE conversas SET nao_lidas = ?, ultima_entrada_em = ?, ultima_mensagem_em = ? WHERE id_conversa = ?')->execute([
                    (int) $alvo['nao_lidas'] + (int) $conversa['nao_lidas'],
                    $maisRecente($alvo['ultima_entrada_em'], $conversa['ultima_entrada_em']),
                    $maisRecente($alvo['ultima_mensagem_em'], $conversa['ultima_mensagem_em']),
                    $alvo['id_conversa'],
                ]);
                $this->conn->prepare('DELETE FROM conversas WHERE id_conversa = ?')->execute([$id]);

                return ['ok' => true, 'erro' => null, 'conversa_id' => (int) $alvo['id_conversa']];
            }
            $this->conn->prepare('UPDATE conversas SET cliente_id = ? WHERE id_conversa = ?')->execute([$clienteId, $id]);

            return ['ok' => true, 'erro' => null, 'conversa_id' => $id];
        });
    }
}
