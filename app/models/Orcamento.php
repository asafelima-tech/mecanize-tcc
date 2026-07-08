<?php

require_once __DIR__ . '/OrdemServico.php';

class Orcamento
{
    private $conn;

    public function __construct()
    {
        $this->conn = new Database();
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

    public function salvar(array $post)
    {
        $stmt = $this->conn->prepare("
            INSERT INTO orcamentos (
                numero, cliente_id, veiculo_id, mecanico_id,
                km_veiculo, defeito_relatado, diagnostico, observacoes,
                valor_total_pecas, valor_total_servicos, valor_total_orcamento,
                valor_mao_obra, comissao_pct, comissao_base,
                data_criacao, data_validade, prazo_execucao, status
            ) VALUES (
                :numero, :cliente_id, :veiculo_id, :mecanico_id,
                :km_veiculo, :defeito_relatado, :diagnostico, :observacoes,
                :valor_total_pecas, :valor_total_servicos, :valor_total_orcamento,
                :valor_mao_obra, :comissao_pct, :comissao_base,
                :data_criacao, :data_validade, :prazo_execucao, 'pendente'
            )
        ");

        $ok = $stmt->execute([
            ':numero'                => $post['numero_orcamento']      ?? null,
            ':cliente_id'            => $post['cliente_id']            ?? null,
            ':veiculo_id'            => $post['veiculo_id']            ?? null,
            ':mecanico_id'           => !empty($post['mecanico_id']) ? $post['mecanico_id'] : null,
            ':km_veiculo'            => !empty($post['km_veiculo'])  ? $post['km_veiculo']  : null,
            ':defeito_relatado'      => $post['defeito_relatado']      ?? null,
            ':diagnostico'           => $post['diagnostico']           ?? null,
            ':observacoes'           => $post['observacoes']           ?? null,
            ':valor_total_pecas'     => $post['valor_total_pecas']     ?? 0,
            ':valor_total_servicos'  => $post['valor_total_servicos']  ?? 0,
            ':valor_total_orcamento' => $post['valor_total_orcamento'] ?? 0,
            ':valor_mao_obra'        => $post['valor_mao_obra']        ?? 0,
            ':comissao_pct'          => $post['comissao_pct']          ?? 0,
            ':comissao_base'         => $this->normalizarComissaoBase($post['comissao_base'] ?? null),
            ':data_criacao'          => str_replace('T', ' ', $post['data_criacao'] ?? date('Y-m-d H:i:s')),
            ':data_validade'         => !empty($post['data_validade']) ? $post['data_validade'] : null,
            ':prazo_execucao'        => $post['prazo_execucao']        ?? null,
        ]);

        if (!$ok) return false;

        $orcamentoId = $this->conn->lastInsertId();

        return $this->salvarItens($orcamentoId, $post['itens'] ?? []);
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

    public function aprovar($id, ?string $assinatura = null)
    {
        $orc = $this->getById($id);

        if (!$orc) {
            error_log('[aprovar] Orçamento não encontrado: ' . $id);
            return false;
        }

        $stmtOs = $this->conn->prepare("
            SELECT id_ordem_servico
            FROM ordens_servico
            WHERE orcamento_id = ?
            LIMIT 1
        ");
        $stmtOs->execute([$id]);
        $osExistente = $stmtOs->fetch(PDO::FETCH_ASSOC);

        if ($osExistente) {
            $stmt = $this->conn->prepare("
                UPDATE orcamentos
                SET status             = 'aprovado',
                    data_aprovacao     = NOW(),
                    assinatura_cliente = COALESCE(?, assinatura_cliente)
                WHERE id_orcamento = ?
            ");
            return $stmt->execute([$assinatura, $id]);
        }

        $itens   = $this->getItensByOrcamento($id);

        error_log('[aprovar] itens do orçamento ' . $id . ': ' . json_encode($itens));

        $osModel = new OrdemServico();

        $resultado = $osModel->create([
            'orcamento_id'         => $id,
            'status'               => 'EM_ANDAMENTO',
            'data_abertura'        => date('Y-m-d H:i:s'),
            'cliente_id'           => $orc['cliente_id'],
            'veiculo_id'           => $orc['veiculo_id'],
            'km_veiculo'           => $orc['km_veiculo'],
            'defeito_relatado'     => $orc['defeito_relatado'],
            'diagnostico'          => $orc['diagnostico'],
            'observacoes'          => $orc['observacoes'],
            'valor_total_pecas'    => $orc['valor_total_pecas'],
            'valor_total_servicos' => $orc['valor_total_servicos'],
            'valor_total_os'       => $orc['valor_total_orcamento'],
            'mecanico_id'          => $orc['mecanico_id'],
            'valor_mao_obra'       => $orc['valor_mao_obra'],
            'comissao_pct'         => $orc['comissao_pct'],
            'comissao_base'        => $orc['comissao_base'] ?? 'servicos',
            'itens'                => $itens,
        ]);

        if (!$resultado) {
            error_log('[aprovar] Falha ao criar OS a partir do orçamento: ' . $id);
            return false;
        }

        $stmt = $this->conn->prepare("
            UPDATE orcamentos
            SET status             = 'aprovado',
                data_aprovacao     = NOW(),
                assinatura_cliente = COALESCE(?, assinatura_cliente)
            WHERE id_orcamento = ?
        ");
        return $stmt->execute([$assinatura, $id]);
    }

    public function recusar($id, $motivo = null)
    {
        $stmt = $this->conn->prepare("
            UPDATE orcamentos
            SET status        = 'reprovado',
                data_recusa   = NOW(),
                motivo_recusa = ?
            WHERE id_orcamento = ?
        ");
        return $stmt->execute([$motivo, $id]);
    }

    private function salvarItens($orcamentoId, array $itens)
    {
        if (empty($itens)) return true;

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
            $ok = $stmt->execute([
                ':orcamento_id'   => $orcamentoId,
                ':item_id'        => !empty($item['custom']) ? null : ($item['id'] ?? null),
                ':nome'           => $item['nome']      ?? '',
                ':tipo'           => $item['tipo']      ?? 'produto',
                ':categoria'      => $item['categoria'] ?? null,
                ':preco_unitario' => $item['preco']     ?? 0,
                ':quantidade'     => $item['qty']       ?? 1,
                ':subtotal'       => $item['subtotal']  ?? 0,
                ':custom'         => $item['custom']    ?? 0,
                ':pct'            => !empty($item['pct']) ? $item['pct'] : null,
            ]);
            if (!$ok) return false;
        }

        return true;
    }

    public function atualizar($id, array $post)
    {
        $stmt = $this->conn->prepare("
            UPDATE orcamentos SET
                cliente_id            = :cliente_id,
                veiculo_id            = :veiculo_id,
                mecanico_id           = :mecanico_id,
                km_veiculo            = :km_veiculo,
                defeito_relatado      = :defeito_relatado,
                diagnostico           = :diagnostico,
                observacoes           = :observacoes,
                valor_total_pecas     = :valor_total_pecas,
                valor_total_servicos  = :valor_total_servicos,
                valor_total_orcamento = :valor_total_orcamento,
                valor_mao_obra        = :valor_mao_obra,
                comissao_pct          = :comissao_pct,
                comissao_base         = :comissao_base,
                data_validade         = :data_validade,
                prazo_execucao        = :prazo_execucao,
                status                = :status
            WHERE id_orcamento = :id
        ");

        return $stmt->execute([
            ':id'                    => $id,
            ':cliente_id'            => $post['cliente_id']            ?? null,
            ':veiculo_id'            => $post['veiculo_id']            ?? null,
            ':mecanico_id'           => $post['mecanico_id']           ?? null,
            ':km_veiculo'            => $post['km_veiculo']            ?? null,
            ':defeito_relatado'      => $post['defeito_relatado']      ?? null,
            ':diagnostico'           => $post['diagnostico']           ?? null,
            ':observacoes'           => $post['observacoes']           ?? null,
            ':valor_total_pecas'     => $post['valor_total_pecas']     ?? 0,
            ':valor_total_servicos'  => $post['valor_total_servicos']  ?? 0,
            ':valor_total_orcamento' => $post['valor_total_orcamento'] ?? 0,
            ':valor_mao_obra'        => $post['valor_mao_obra']        ?? 0,
            ':comissao_pct'          => $post['comissao_pct']          ?? 0,
            ':comissao_base'         => $this->normalizarComissaoBase($post['comissao_base'] ?? null),
            ':data_validade'         => $post['data_validade']         ?? null,
            ':prazo_execucao'        => $post['prazo_execucao']        ?? null,
            ':status'                => $post['status']                ?? 'pendente',
        ]);
    }

    private function getProximoNumero()
    {
        $stmt = $this->conn->prepare("
            SELECT numero FROM orcamentos ORDER BY id_orcamento DESC LIMIT 1
        ");
        $stmt->execute();
        $last = $stmt->fetchColumn();

        if (!$last) return 'ORC-0001';

        $num = (int) preg_replace('/\D/', '', $last);
        return 'ORC-' . str_pad($num + 1, 4, '0', STR_PAD_LEFT);
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
                c.telefone AS telefone_cliente,
                v.placa,
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

    public function listar()
    {
        $stmt = $this->conn->prepare("
            SELECT
                os.id_ordem_servico AS id,
                os.numero,
                os.data_abertura,
                os.data_conclusao,
                os.valor_total_os,
                os.defeito_relatado,
                os.diagnostico,
                c.nome AS cliente,
                CONCAT(mv.nome, ' ', mo.nome, ' ', COALESCE(v.ano, '')) AS veiculo,
                v.placa,
                u.nome AS mecanico
            FROM ordens_servico os
            INNER JOIN clientes c ON c.id_cliente = os.cliente_id
            INNER JOIN veiculos v ON v.id_veiculo = os.veiculo_id
            INNER JOIN marcas_veiculos mv ON mv.id_marca = v.marca_id
            INNER JOIN modelos_veiculos mo ON mo.id_modelo = v.modelo_id
            LEFT JOIN ordem_servico_mao_obra mob
                ON mob.ordem_servico_id = os.id_ordem_servico
                AND mob.id_OS_mao = (
                    SELECT MIN(id_OS_mao)
                    FROM ordem_servico_mao_obra
                    WHERE ordem_servico_id = os.id_ordem_servico
                    AND tecnico_id IS NOT NULL
                )
            LEFT JOIN usuarios u ON u.id_usuario = mob.tecnico_id
            WHERE os.status = 'CONCLUIDA'
            ORDER BY os.data_conclusao DESC
            LIMIT 20
        ");
        $stmt->execute();
        $ordens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ordens as &$os) {
            $os['itens'] = $this->buscarItens((int) $os['id']);
        }
        return $ordens;
    }

    private function buscarItens($osId)
    {
        $stmtProd = $this->conn->prepare("
            SELECT p.nome, 'produto' AS tipo, oi.quantidade AS qty, oi.valor_unitario AS preco
            FROM ordem_servico_produtos oi
            INNER JOIN produtos p ON p.id_produto = oi.produto_id
            WHERE oi.ordem_servico_id = ?
        ");
        $stmtProd->execute([$osId]);
        $produtos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

        $stmtMao = $this->conn->prepare("
            SELECT descricao AS nome, 'servico' AS tipo, 1 AS qty, valor_mao_obra AS preco
            FROM ordem_servico_mao_obra
            WHERE ordem_servico_id = ?
        ");
        $stmtMao->execute([$osId]);
        $maoObra = $stmtMao->fetchAll(PDO::FETCH_ASSOC);

        return array_merge($produtos, $maoObra);
    }

    public function finalizar($osId)
    {
        $stmt = $this->conn->prepare("SELECT status FROM ordens_servico WHERE id_ordem_servico = ?");
        $stmt->execute([$osId]);
        $os = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$os || $os['status'] !== 'CONCLUIDA') {
            return false;
        }

        return true;
    }
}
