<?php

require_once __DIR__ . '/../core/Transacao.php';
require_once __DIR__ . '/../core/Historico.php';
require_once __DIR__ . '/ConsumoEstoque.php';

class OrdemServico
{
    public $conn;

    /** Colunas do quadro Kanban, na ordem do fluxo. CANCELADA fica fora (não é uma etapa). */
    public const STATUS_KANBAN = ['ABERTA', 'EM_ANDAMENTO', 'AGUARDANDO_PECA', 'AGUARDANDO_CONFERENCIA', 'AGUARDANDO_RETIRADA', 'CONCLUIDA'];

    /** Status de execução, que a equipe alterna por botão simples (sem formulário). */
    public const STATUS_TECNICO = ['ABERTA', 'EM_ANDAMENTO', 'AGUARDANDO_PECA'];

    /** Status em que a OS ainda está nas mãos da execução (peças e serviços editáveis). */
    public const STATUS_EM_EXECUCAO = ['ABERTA', 'EM_ANDAMENTO', 'AGUARDANDO_PECA'];

    /** OS encerradas: não aceitam mais nenhuma mudança de valor ou de item. */
    public const STATUS_ENCERRADOS = ['CONCLUIDA', 'CANCELADA'];

    /**
     * Fluxo: execução → técnico termina → administrador confere → cliente retira.
     * Ver OrdemServicoFluxo para as transições permitidas.
     */
    public const STATUS_LABEL = [
        'ABERTA'                 => 'Aguardando execução',
        'EM_ANDAMENTO'           => 'Em execução',
        'AGUARDANDO_PECA'        => 'Aguardando peças',
        'AGUARDANDO_CONFERENCIA' => 'Aguardando conferência',
        'AGUARDANDO_RETIRADA'    => 'Aguardando retirada',
        'CONCLUIDA'              => 'Entregue',
        'CANCELADA'              => 'Cancelada',
    ];

    /**
     * Itens fixos do checklist de entrega, preenchido pelo técnico ao
     * finalizar o serviço (transição para AGUARDANDO_CONFERENCIA). Fixo em código
     * (não configurável por OS) porque é sempre a mesma checagem de saída de
     * oficina — uma tabela à parte seria estrutura nova para um dado que não
     * varia.
     */
    public const CHECKLIST_ENTREGA = [
        'oleo_fluidos'   => 'Nível de óleo e fluidos verificado',
        'pneus'          => 'Pneus calibrados',
        'freios'         => 'Freios testados',
        'luzes_painel'   => 'Luzes e painel testados',
        'ferramentas'    => 'Ferramentas e peças retiradas do veículo',
        'teste_rodagem'  => 'Teste de rodagem realizado',
        'limpeza'        => 'Veículo limpo internamente',
    ];

    public static function rotuloStatus(?string $status): string
    {
        return self::STATUS_LABEL[$status] ?? (string) $status;
    }

    /** Classe .badge--* usada em todo lugar que mostra o status (kanban, listagem, painel do técnico, rastreio). */
    public const STATUS_BADGE = [
        'ABERTA'                 => 'badge--info',
        'EM_ANDAMENTO'           => 'badge--warning',
        'AGUARDANDO_PECA'        => 'badge--purple',
        'AGUARDANDO_CONFERENCIA' => 'badge--secondary',
        'AGUARDANDO_RETIRADA'    => 'badge--info',
        'CONCLUIDA'              => 'badge--success',
        'CANCELADA'              => 'badge--danger',
    ];

    public static function classeBadgeStatus(?string $status): string
    {
        return self::STATUS_BADGE[$status] ?? 'badge--neutral';
    }

    /**
     * @param PDO|null $conn conexão a reaproveitar — usada pelo OrdemServicoFluxo
     *                       para o model trabalhar DENTRO da transação dele
     *                       (outra conexão esperaria as travas dela até o timeout).
     */
    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    /**
     * OS concluídas nas últimas N horas (usado no painel modo TV — não há
     * flag de "retirado", então limitamos por tempo pra não acumular pra
     * sempre no quadro de "pronto pra retirada").
     */
    public function listarConcluidasRecentes(int $horas = 48): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                os.id_ordem_servico, os.numero, os.data_conclusao,
                c.nome AS cliente, v.placa
            FROM ordens_servico os
            INNER JOIN clientes c ON c.id_cliente = os.cliente_id
            INNER JOIN veiculos v ON v.id_veiculo  = os.veiculo_id
            WHERE os.status = 'CONCLUIDA'
              AND os.data_conclusao >= (NOW() - INTERVAL :horas HOUR)
            ORDER BY os.data_conclusao DESC
        ");
        $stmt->execute([':horas' => $horas]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna o token de rastreio público da OS, gerando um na hora se ainda
     * não existir (cobre OS's criadas antes desta feature).
     */
    public function getOuCriarToken(int $id): string
    {
        $stmt = $this->conn->prepare("SELECT token_rastreio FROM ordens_servico WHERE id_ordem_servico = ?");
        $stmt->execute([$id]);
        $token = $stmt->fetchColumn();

        if ($token) {
            return $token;
        }

        $token = bin2hex(random_bytes(24));
        $this->conn->prepare("UPDATE ordens_servico SET token_rastreio = ? WHERE id_ordem_servico = ?")
            ->execute([$token, $id]);

        return $token;
    }

    /**
     * Dados mínimos e públicos de uma OS a partir do token de rastreio — sem
     * valores financeiros nem dados do cliente além do primeiro nome, pra
     * ficar seguro mesmo se o link for encaminhado adiante.
     */
    public function getPorTokenRastreio(string $token): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT
                os.numero          AS numero_os,
                os.status,
                os.data_abertura,
                os.data_conclusao,
                v.placa,
                CONCAT(mv.nome, ' ', mo.nome) AS modelo,
                c.nome             AS nome_cliente
            FROM ordens_servico os
            INNER JOIN veiculos          v  ON v.id_veiculo = os.veiculo_id
            INNER JOIN marcas_veiculos   mv ON mv.id_marca  = v.marca_id
            INNER JOIN modelos_veiculos  mo ON mo.id_modelo = v.modelo_id
            INNER JOIN clientes          c  ON c.id_cliente = os.cliente_id
            WHERE os.token_rastreio = ?
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        return $linha ?: null;
    }

    /** Histórico de OS de um veículo (usado na linha do tempo do veículo). */
    public function listarPorVeiculo(int $veiculoId): array
    {
        $stmt = $this->conn->prepare("
            SELECT id_ordem_servico, numero, status, data_abertura, data_conclusao, valor_total_os
            FROM ordens_servico
            WHERE veiculo_id = ?
            ORDER BY data_abertura DESC
        ");
        $stmt->execute([$veiculoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarEmAndamento($filtros = [])
    {
        return $this->listarPorStatus(['EM_ANDAMENTO'], $filtros);
    }

    public function listarAguardandoPeca($filtros = [])
    {
        return $this->listarPorStatus(['AGUARDANDO_PECA'], $filtros);
    }

    public function statusAtual(int $id): ?string
    {
        $stmt = $this->conn->prepare('SELECT status FROM ordens_servico WHERE id_ordem_servico = ?');
        $stmt->execute([$id]);
        $status = $stmt->fetchColumn();

        return $status === false ? null : (string) $status;
    }

    /**
     * O técnico está atribuído a esta OS? Serve para destacar "minhas" tarefas na
     * tela — NÃO é uma regra de autorização: técnico e atendente têm as mesmas
     * permissões sobre qualquer OS (ver Permissao). Uma OS pode ter vários
     * técnicos, cada um com a sua linha em ordem_servico_mao_obra.
     */
    public function atribuidaA(int $ordemId, int $tecnicoId): bool
    {
        $stmt = $this->conn->prepare("
            SELECT 1 FROM ordem_servico_mao_obra
            WHERE ordem_servico_id = ? AND tecnico_id = ?
            LIMIT 1
        ");
        $stmt->execute([$ordemId, $tecnicoId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Itens do checklist de entrega marcados pelo técnico ao terminar o serviço,
     * já no formato gravado na OS (JSON). Fica aqui, junto da constante que
     * define os itens, e não no controller.
     */
    public static function montarChecklist(array $post): string
    {
        $checklist = [];

        foreach (self::CHECKLIST_ENTREGA as $chave => $rotulo) {
            $checklist[] = [
                'item' => $rotulo,
                'ok'   => !empty($post['checklist'][$chave]),
            ];
        }

        return json_encode($checklist, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Peça usada na execução, lançada pela equipe. O preço vem SEMPRE do
     * cadastro do produto (preco_venda) — quem lança só escolhe o quê e quanto.
     *
     * Entra como PLANEJADO (previsão + cobrança): o estoque só baixa quando o
     * consumo é confirmado. Com $consumirAgora a confirmação acontece logo em
     * seguida; se ela for recusada (sem saldo, OS ainda não iniciada) a peça
     * continua lançada como planejada e a mensagem explica o motivo — nada se
     * perde e nada fica pela metade.
     *
     * @return array{ok:bool, erro:?string, aviso:?string, item_id:?int}
     */
    public function adicionarPeca(int $ordemId, int $produtoId, float $qty, ?int $usuarioId, bool $consumirAgora = false): array
    {
        if ($qty <= 0) {
            return ['ok' => false, 'erro' => 'Quantidade inválida.', 'aviso' => null, 'item_id' => null];
        }

        try {
            $itemId = Transacao::executar($this->conn, function () use ($ordemId, $produtoId, $qty) {
                $os = $this->conn->prepare('SELECT status FROM ordens_servico WHERE id_ordem_servico = ? FOR UPDATE');
                $os->execute([$ordemId]);
                $status = $os->fetchColumn();

                if ($status === false) {
                    throw new DomainException('Ordem de serviço não encontrada.');
                }

                if (!in_array($status, self::STATUS_EM_EXECUCAO, true)) {
                    throw new DomainException('Esta OS já saiu da execução — não é possível lançar mais peças.');
                }

                $produto = $this->conn->prepare('SELECT preco_venda FROM produtos WHERE id_produto = ? AND ativo = 1');
                $produto->execute([$produtoId]);
                $preco = $produto->fetchColumn();

                if ($preco === false) {
                    throw new DomainException('Produto não encontrado.');
                }

                $this->conn->prepare("
                    INSERT INTO ordem_servico_produtos (ordem_servico_id, produto_id, quantidade, valor_unitario, status)
                    VALUES (?, ?, ?, ?, 'PLANEJADO')
                ")->execute([$ordemId, $produtoId, $qty, $preco]);

                $id = (int) $this->conn->lastInsertId();
                $this->recalcularTotais($ordemId);

                return $id;
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'aviso' => null, 'item_id' => null];
        } catch (\Throwable $e) {
            error_log('[OS adicionarPeca] ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível registrar a peça.', 'aviso' => null, 'item_id' => null];
        }

        if (!$consumirAgora) {
            return ['ok' => true, 'erro' => null, 'aviso' => null, 'item_id' => $itemId];
        }

        $consumo = ConsumoEstoque::consumir($this->conn, $itemId, $usuarioId);

        return [
            'ok'      => true,
            'erro'    => null,
            'aviso'   => $consumo['ok'] ? null : 'Peça lançada, mas o consumo não foi confirmado: ' . $consumo['erro'],
            'item_id' => $itemId,
        ];
    }

    /**
     * Totais da OS, SEMPRE recalculados a partir dos itens gravados — nunca do
     * que o navegador mandou. Peças devolvidas ou canceladas não entram na
     * cobrança. Serviços avulsos antigos (guardados em mão de obra sem técnico)
     * continuam contando.
     *
     * valor_total_os = peças + serviços (não há desconto nem acréscimo na OS;
     * juros e taxa do pagamento ficam no plano de pagamento, à parte).
     *
     * @return array{pecas:float, servicos:float, total:float}
     */
    public function recalcularTotais(int $ordemId): array
    {
        $pecas = $this->conn->prepare("
            SELECT COALESCE(SUM(quantidade * valor_unitario), 0)
            FROM ordem_servico_produtos
            WHERE ordem_servico_id = ? AND status IN ('PLANEJADO','RESERVADO','CONSUMIDO')
        ");
        $pecas->execute([$ordemId]);
        $totalPecas = round((float) $pecas->fetchColumn(), 2);

        $servicos = $this->conn->prepare("
            SELECT
                COALESCE((SELECT SUM(quantidade * valor_unitario) FROM ordem_servico_servicos WHERE ordem_servico_id = :os1), 0)
              + COALESCE((SELECT SUM(valor_mao_obra) FROM ordem_servico_mao_obra WHERE ordem_servico_id = :os2 AND tecnico_id IS NULL), 0)
        ");
        $servicos->execute([':os1' => $ordemId, ':os2' => $ordemId]);
        $totalServicos = round((float) $servicos->fetchColumn(), 2);

        $total = round($totalPecas + $totalServicos, 2);

        $this->conn->prepare("
            UPDATE ordens_servico
            SET valor_total_pecas = ?, valor_total_servicos = ?, valor_total_os = ?
            WHERE id_ordem_servico = ?
        ")->execute([$totalPecas, $totalServicos, $total, $ordemId]);

        return ['pecas' => $totalPecas, 'servicos' => $totalServicos, 'total' => $total];
    }

    /** A OS ainda está na execução (peças e serviços podem mudar)? */
    private function statusEmExecucao(int $ordemId): bool
    {
        return in_array($this->statusAtual($ordemId), self::STATUS_EM_EXECUCAO, true);
    }

    /** Confirma (ou desmarca) que um serviço planejado foi executado. */
    public function alternarServicoExecutado(int $servicoRowId, int $ordemId): bool
    {
        if (!$this->statusEmExecucao($ordemId)) {
            return false;
        }

        return $this->conn->prepare("
            UPDATE ordem_servico_servicos
            SET executado = NOT executado
            WHERE id = ? AND ordem_servico_id = ?
        ")->execute([$servicoRowId, $ordemId]);
    }

    /**
     * Salvamento avulso das observações técnicas — dá para ir anotando durante o
     * serviço, não só no momento de terminar.
     */
    public function salvarObservacoesTecnicas(int $id, string $texto): bool
    {
        if (!$this->statusEmExecucao($id)) {
            return false;
        }

        return $this->conn->prepare("
            UPDATE ordens_servico SET observacoes_tecnicas = ? WHERE id_ordem_servico = ?
        ")->execute([trim($texto) ?: null, $id]);
    }

    /** Mesma listagem/JOIN dos métodos administrativos, filtrada pelo técnico atribuído. */
    public function listarPorTecnico(int $tecnicoId, array $statusList, array $filtros = []): array
    {
        return $this->listarPorStatus($statusList, $filtros, $tecnicoId);
    }

    public function listarAguardandoConferencia($filtros = [])
    {
        return $this->listarPorStatus(['AGUARDANDO_CONFERENCIA'], $filtros);
    }

    public function listarAguardandoRetirada($filtros = [])
    {
        return $this->listarPorStatus(['AGUARDANDO_RETIRADA'], $filtros);
    }

    public function getClientes()
    {
        // "Cliente Balcão" (cliente genérico de balcão) fica sempre no topo da lista
        $sql = "SELECT id_cliente, nome
                FROM clientes
                WHERE ativo = 1
                ORDER BY (nome = 'Cliente Balcão') DESC, nome ASC";

        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVeiculos()
    {
        $stmt = $this->conn->prepare("
        SELECT
            v.id_veiculo AS id,
            v.cliente_id,
            v.placa,
            CONCAT(mv.nome, ' ', mo.nome) AS modelo,
            c.nome AS cliente
        FROM veiculos v
        INNER JOIN marcas_veiculos mv ON mv.id_marca = v.marca_id
        INNER JOIN modelos_veiculos mo ON mo.id_modelo = v.modelo_id
        INNER JOIN clientes c ON c.id_cliente = v.cliente_id
        ORDER BY v.placa
        ");

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Técnicos ativos + a regra de remuneração de cada um (se tiver) — é o
     * que alimenta a prévia de comissão calculada em JS na tela de OS
     * (ver MECANICOS_DATA em saveOrdemServico.php/editOrdemServico.php).
     * O cálculo que de fato vale é sempre o do servidor, na finalização
     * (calcularComissoesNaConclusao()) — isto aqui é só pra quem está
     * montando a OS ter uma estimativa.
     */
    public function getMecanicos()
    {
        $sql = "SELECT
                    u.id_usuario AS id,
                    u.nome,
                    0 AS valor_hora,
                    r.tipo AS regra_tipo,
                    r.percentual AS regra_percentual,
                    r.valor_fixo AS regra_valor_fixo,
                    r.comissao_base_extra AS regra_base_extra
                FROM usuarios u
                LEFT JOIN tecnico_remuneracao_regra r ON r.tecnico_id = u.id_usuario AND r.ativo = 1
                WHERE u.tipo  = 'TECNICO'
                  AND u.ativo = 1
                ORDER BY u.nome ASC";

        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProdutos()
    {
        $sql = "SELECT
                p.id_produto AS id,
                p.nome,
                COALESCE(c.nome, 'Produto') AS categoria,
                p.preco_venda AS preco
            FROM produtos p
            LEFT JOIN categorias_produtos c ON c.id_categoria = p.categorias_produtos_id
            WHERE p.ativo = 1
            ORDER BY p.nome ASC";

        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getServicos()
    {
        return [];
    }

    public function gerarNumeroOS()
    {
        $sql    = "SELECT MAX(id_ordem_servico) AS max_id FROM ordens_servico";
        $stmt   = $this->conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $proximoId = (int)($result['max_id'] ?? 0) + 1;

        return 'OS-' . str_pad($proximoId, 4, '0', STR_PAD_LEFT);
    }

    public function getProximoNumero()
    {
        return $this->gerarNumeroOS();
    }

    public function getResumo($filtros = [])
    {
        return [
            'statusAbertas'        => $this->contarPorStatus('ABERTA'),
            'statusEmAndamento'    => $this->contarPorStatus('EM_ANDAMENTO'),
            'statusAguardandoPeca' => $this->contarPorStatus('AGUARDANDO_PECA'),
            'statusAguardandoConferencia' => $this->contarPorStatus('AGUARDANDO_CONFERENCIA'),
            'statusAguardandoRetirada'    => $this->contarPorStatus('AGUARDANDO_RETIRADA'),
            'statusConcluidas'     => $this->contarPorStatus('CONCLUIDA'),
            'ordens'               => $this->listarAtivas($filtros),
            'ordensAbertas'        => $this->listarAbertas($filtros),
            'ordensProntas'        => array_merge($this->listarAguardandoConferencia($filtros), $this->listarAguardandoRetirada($filtros)),
            'ordensConcluidas'     => $this->listarConcluidas($filtros),
            'ordensArquivadas'     => $this->listarArquivadas($filtros),
            'paginaAtual'          => 1,
        ];
    }

    public function listarAbertas($filtros = [])
    {
        return $this->listarPorStatus(['ABERTA'], $filtros);
    }

    public function listarAtivas($filtros = [])
    {
        return $this->listarPorStatus(['EM_ANDAMENTO', 'AGUARDANDO_PECA'], $filtros);
    }

    public function listarConcluidas($filtros = [])
    {
        return $this->listarPorStatus(['CONCLUIDA'], $filtros);
    }

    public function listarArquivadas($filtros = [])
    {
        return $this->listarPorStatus(['CANCELADA'], $filtros);
    }

    private function listarPorStatus(array $statusList, $filtros = [], ?int $tecnicoId = null)
    {
        $placeholders = implode(',', array_fill(0, count($statusList), '?'));

        $sql = "
        SELECT
            os.id_ordem_servico,
            os.numero,
            os.status,
            os.data_abertura,
            os.data_conclusao,
            os.valor_total_os,
            os.valor_total_pecas,
            os.valor_total_servicos,
            os.defeito_relatado,
            os.diagnostico,
            os.km_veiculo,
            c.nome  AS cliente,
            v.placa,
            CONCAT(
                mv.nome,
                CASE WHEN mo.nome IS NOT NULL AND mo.nome <> ''
                     THEN CONCAT(' ', mo.nome) ELSE '' END,
                CASE WHEN v.ano IS NOT NULL
                     THEN CONCAT(' ', v.ano) ELSE '' END
            ) AS veiculo,
            u.nome  AS mecanico,
            u.id_usuario AS mecanico_id,
            mob.valor_mao_obra,
            mob.descricao AS mao_obra_descricao
        FROM ordens_servico os
        INNER JOIN clientes         c   ON c.id_cliente  = os.cliente_id
        INNER JOIN veiculos         v   ON v.id_veiculo  = os.veiculo_id
        INNER JOIN marcas_veiculos  mv  ON mv.id_marca   = v.marca_id
        INNER JOIN modelos_veiculos mo  ON mo.id_modelo  = v.modelo_id
        LEFT  JOIN ordem_servico_mao_obra mob
            ON mob.ordem_servico_id = os.id_ordem_servico
            AND mob.id_OS_mao = (
                SELECT MIN(id_OS_mao)
                FROM ordem_servico_mao_obra
                WHERE ordem_servico_id = os.id_ordem_servico
                AND tecnico_id IS NOT NULL
            )
        LEFT  JOIN usuarios         u   ON u.id_usuario  = mob.tecnico_id
        WHERE os.status IN ($placeholders)
        ";

        $params = $statusList;

        if ($tecnicoId !== null) {
            // EXISTS, e não "mob.tecnico_id = ?": o JOIN acima só traz a
            // PRIMEIRA linha de técnico da OS (para exibir o responsável), então
            // o 2º e o 3º técnico atribuídos nunca a encontravam.
            $sql     .= " AND EXISTS (
                SELECT 1 FROM ordem_servico_mao_obra tmob
                WHERE tmob.ordem_servico_id = os.id_ordem_servico AND tmob.tecnico_id = ?
            )";
            $params[] = $tecnicoId;
        }

        if (!empty($filtros['busca'])) {
            $sql .= " AND (os.numero LIKE ? OR c.nome LIKE ? OR v.placa LIKE ?)";
            $busca    = '%' . trim($filtros['busca']) . '%';
            $params[] = $busca;
            $params[] = $busca;
            $params[] = $busca;
        }

        $sql .= " ORDER BY os.data_abertura DESC LIMIT 50";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $stmt = $this->conn->prepare("
        SELECT
            os.id_ordem_servico,
            os.numero          AS numero_os,
            os.status,
            os.data_abertura,
            os.data_conclusao,
            os.km_veiculo,
            os.defeito_relatado,
            os.diagnostico,
            os.observacoes,
            os.observacoes_tecnicas,
            os.checklist_entrega,
            os.assinatura_cliente,
            os.assinatura_tecnico,
            os.tecnico_finalizacao_id,
            os.data_finalizacao_tecnica,
            os.valor_total_pecas,
            os.valor_total_servicos,
            os.valor_total_os,
            os.pagamento_tipo,
            os.pagamento_forma,
            os.pagamento_juros,
            os.pagamento_taxa,
            os.pagamento_total,
            os.cliente_id,
            os.veiculo_id,
            os.ocorrencia_id,
            os.valor_autorizado,
            os.previsao_conclusao,
            c.nome             AS nome_cliente,
            c.tipo_pessoa      AS tipo_pessoa_cliente,
            c.cpf              AS cpf_cliente,
            c.cnpj             AS cnpj_cliente,
            c.telefone         AS telefone_cliente,
            c.endereco         AS endereco_cliente,
            c.bairro           AS bairro_cliente,
            c.cidade           AS cidade_cliente,
            c.uf               AS uf_cliente,
            c.cep              AS cep_cliente,
            v.placa,
            v.ano              AS ano_veiculo,
            v.cor              AS cor_veiculo,
            mv.nome            AS marca_veiculo,
            mo.nome            AS modelo_veiculo,
            CONCAT(
                mv.nome,
                CASE WHEN mo.nome IS NOT NULL AND mo.nome <> ''
                     THEN CONCAT(' ', mo.nome) ELSE '' END,
                CASE WHEN v.ano IS NOT NULL
                     THEN CONCAT(' ', v.ano) ELSE '' END
            )                  AS modelo,
            u.nome             AS nome_mecanico,
            mob.tecnico_id     AS mecanico_id,
            mob.valor_mao_obra,
            mob.descricao      AS mao_obra_descricao
        FROM ordens_servico os
        INNER JOIN clientes          c   ON c.id_cliente  = os.cliente_id
        INNER JOIN veiculos          v   ON v.id_veiculo  = os.veiculo_id
        INNER JOIN marcas_veiculos   mv  ON mv.id_marca   = v.marca_id
        INNER JOIN modelos_veiculos  mo  ON mo.id_modelo  = v.modelo_id
        LEFT  JOIN ordem_servico_mao_obra mob
            ON mob.ordem_servico_id = os.id_ordem_servico
            AND mob.id_OS_mao = (
                SELECT MIN(id_OS_mao)
                FROM ordem_servico_mao_obra
                WHERE ordem_servico_id = os.id_ordem_servico
                AND tecnico_id IS NOT NULL
            )
        LEFT  JOIN usuarios          u   ON u.id_usuario  = mob.tecnico_id
        WHERE os.id_ordem_servico = ?
        LIMIT 1
    ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['mao_obra_descricao'])) {
            preg_match('/\((\d+(?:\.\d+)?)%\)/', $row['mao_obra_descricao'], $matches);
            $row['comissao_pct'] = $matches[1] ?? 0;
        } else {
            $row['comissao_pct'] = 0;
        }

        return $row;
    }

    private function contarPorStatus($status)
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM ordens_servico WHERE status = ?"
        );
        $stmt->execute([$status]);
        return (int) $stmt->fetchColumn();
    }

    public function listar($filtros = [])
    {
        $sql = "
            SELECT
                os.id_ordem_servico,
                os.numero,
                os.status,
                os.data_abertura,
                os.data_conclusao,
                os.valor_total_os,
                c.nome  AS cliente,
                v.placa,
                CONCAT(
                    mv.nome,
                    CASE WHEN mo.nome IS NOT NULL AND mo.nome <> ''
                         THEN CONCAT(' ', mo.nome) ELSE '' END,
                    CASE WHEN v.ano IS NOT NULL
                         THEN CONCAT(' ', v.ano) ELSE '' END
                ) AS veiculo
            FROM ordens_servico os
            INNER JOIN clientes          c  ON c.id_cliente = os.cliente_id
            INNER JOIN veiculos          v  ON v.id_veiculo = os.veiculo_id
            INNER JOIN marcas_veiculos   mv ON mv.id_marca  = v.marca_id
            INNER JOIN modelos_veiculos  mo ON mo.id_modelo = v.modelo_id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filtros['busca'])) {
            $sql .= " AND (
                os.numero LIKE ? OR c.nome LIKE ?
                OR v.placa LIKE ? OR mv.nome LIKE ? OR mo.nome LIKE ?
            )";
            $busca    = '%' . trim($filtros['busca']) . '%';
            $params[] = $busca;
            $params[] = $busca;
            $params[] = $busca;
            $params[] = $busca;
            $params[] = $busca;
        }

        if (!empty($filtros['status'])) {
            $sql     .= " AND os.status = ?";
            $params[] = $filtros['status'];
        }

        if (!empty($filtros['data'])) {
            $sql     .= " AND DATE(os.data_abertura) = ?";
            $params[] = $filtros['data'];
        }

        $sql .= " ORDER BY os.data_abertura DESC LIMIT 20";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Última mensagem de recusa de create()/update() — para a tela explicar o motivo. */
    private string $ultimoErro = '';

    public function getLastError(): string
    {
        return $this->ultimoErro;
    }

    /** Usuário que está operando (sessão), para trilha e autoria. */
    private function usuarioLogado(array $dados = []): ?int
    {
        $id = $dados['_usuario_id'] ?? ($_SESSION['user_id_usuario'] ?? null);

        return $id ? (int) $id : null;
    }

    /**
     * Cria a OS e devolve o id (ou false).
     *
     *  - Nasce ABERTA (ou EM_ANDAMENTO quando o serviço já começa na hora).
     *    Qualquer outro status que venha do formulário é ignorado: conferência,
     *    retirada, entrega e cancelamento só se alcançam pelas transições do
     *    OrdemServicoFluxo, que validam e deixam rastro.
     *  - As peças entram PLANEJADO: abrir uma OS não baixa estoque nenhum.
     *  - Os totais são recalculados dos itens. O que o navegador mandou em
     *    valor_total_* é descartado — não é fonte de verdade para dinheiro.
     *
     * O id devolvido é o que permite à agenda e ao orçamento amarrarem a OS criada.
     */
    public function create($data)
    {
        $this->ultimoErro = '';

        $status = in_array($data['status'] ?? 'ABERTA', ['ABERTA', 'EM_ANDAMENTO'], true)
            ? ($data['status'] ?? 'ABERTA')
            : 'ABERTA';

        // Compatibilidade: a aprovação de orçamento ainda informa UM mecânico.
        $tecnicos = $data['tecnicos'] ?? [];
        if (empty($tecnicos) && !empty($data['mecanico_id'])) {
            $tecnicos = [(int) $data['mecanico_id']];
        }

        try {
            return Transacao::executar($this->conn, function () use ($data, $status, $tecnicos) {
                $this->conn->prepare("
                    INSERT INTO ordens_servico (
                        numero, orcamento_id, ocorrencia_id, valor_autorizado, status, data_abertura,
                        cliente_id, veiculo_id, km_veiculo,
                        defeito_relatado, diagnostico, observacoes,
                        valor_total_pecas, valor_total_servicos, valor_total_os
                    ) VALUES (
                        '', :orcamento_id, :ocorrencia_id, :valor_autorizado, :status, :data_abertura,
                        :cliente_id, :veiculo_id, :km_veiculo,
                        :defeito_relatado, :diagnostico, :observacoes,
                        0, 0, 0
                    )
                ")->execute([
                    ':orcamento_id'     => !empty($data['orcamento_id']) ? (int) $data['orcamento_id'] : null,
                    ':ocorrencia_id'    => !empty($data['ocorrencia_id']) ? (int) $data['ocorrencia_id'] : null,
                    // Teto autorizado pelo cliente (orçamento aprovado ou autorização com valor
                    // em aberto). NULL = OS aberta direto no balcão, sem controle de autorização.
                    ':valor_autorizado' => isset($data['valor_autorizado']) && $data['valor_autorizado'] !== '' ? round((float) $data['valor_autorizado'], 2) : null,
                    ':status'           => $status,
                    ':data_abertura'    => !empty($data['data_abertura'])
                        ? str_replace('T', ' ', $data['data_abertura'])
                        : date('Y-m-d H:i:s'),
                    ':cliente_id'       => $data['cliente_id']       ?? null,
                    ':veiculo_id'       => $data['veiculo_id']       ?? null,
                    ':km_veiculo'       => !empty($data['km_veiculo']) ? $data['km_veiculo'] : null,
                    ':defeito_relatado' => $data['defeito_relatado'] ?? null,
                    ':diagnostico'      => $data['diagnostico']      ?? null,
                    ':observacoes'      => $data['observacoes']      ?? null,
                ]);

                $id = (int) $this->conn->lastInsertId();

                $this->conn->prepare("UPDATE ordens_servico SET numero = ? WHERE id_ordem_servico = ?")
                    ->execute(['OS-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT), $id]);

                foreach ((array) ($data['itens'] ?? []) as $item) {
                    $tipo = $item['tipo'] ?? '';

                    if ($tipo === 'produto') {
                        $this->inserirItemProduto($id, $item);
                    } elseif ($tipo === 'servico') {
                        $this->salvarItemServico($id, $item);
                    }
                }

                $this->salvarTecnicosResponsaveis($id, (array) $tecnicos);
                $this->recalcularTotais($id);

                Historico::registrar(
                    $this->conn,
                    'OS',
                    $id,
                    null,
                    $status,
                    $this->usuarioLogado($data),
                    !empty($data['orcamento_id']) ? 'Criada a partir do orçamento aprovado' : 'OS criada'
                );

                return $id;
            });
        } catch (DomainException $e) {
            // Dado inválido enviado pelo usuário: erro esperado, a tela explica.
            $this->ultimoErro = $e->getMessage();
            return false;
        } catch (Throwable $e) {
            error_log('[OS create] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            $this->ultimoErro = 'Não foi possível salvar a ordem de serviço.';
            return false;
        }
    }

    /**
     * Itens da OS (peças + serviços) no formato que o formulário, a visualização
     * e o PDF usam.
     *
     * Por padrão só os itens que ainda contam na cobrança. Peças DEVOLVIDAS ou
     * CANCELADAS ficam de fora — se voltassem para o formulário, a próxima edição
     * as lançaria de novo. `$incluirInativos` serve às telas de histórico.
     *
     * `row_id` identifica a linha no banco: o formulário o devolve ao salvar, e é
     * assim que update() sabe qual linha é qual (preservando, por exemplo, o
     * "executado" do serviço e a peça já consumida).
     */
    public function getItensByOS($id, bool $incluirInativos = false)
    {
        $filtroStatus = $incluirInativos ? '' : " AND oi.status IN ('PLANEJADO','RESERVADO','CONSUMIDO')";

        $stmt = $this->conn->prepare("
        SELECT
            p.id_produto        AS id,
            oi.id_OS_produto    AS row_id,
            oi.status           AS status_item,
            p.estoque_atual     AS estoque_atual,
            (SELECT COALESCE(SUM(r.quantidade), 0) FROM ordem_servico_produtos r
              WHERE r.produto_id = p.id_produto AND r.status = 'RESERVADO' AND r.id_OS_produto <> oi.id_OS_produto) AS reservado_outros,
            p.nome,
            COALESCE(c.nome, 'Produto') AS categoria,
            'produto'           AS tipo,
            oi.quantidade       AS qty,
            oi.valor_unitario   AS preco,
            0                   AS custom
        FROM ordem_servico_produtos oi
        INNER JOIN produtos p ON p.id_produto = oi.produto_id
        LEFT JOIN categorias_produtos c ON c.id_categoria = p.categorias_produtos_id
        WHERE oi.ordem_servico_id = ?{$filtroStatus}
        ORDER BY oi.id_OS_produto
    ");
        $stmt->execute([$id]);
        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->conn->prepare("
        SELECT
            COALESCE(servico_id, CONCAT('custom_', id)) AS id,
            id              AS row_id,
            nome,
            COALESCE(categoria, 'Serviço') AS categoria,
            'servico'       AS tipo,
            quantidade      AS qty,
            valor_unitario  AS preco,
            personalizado   AS custom,
            percentual_base AS pct,
            executado
        FROM ordem_servico_servicos
        WHERE ordem_servico_id = ?
        ORDER BY id
    ");
        $stmt->execute([$id]);
        $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Serviços avulsos salvos via mão de obra (tecnico_id NULL = serviço de item, não comissão)
        $stmt = $this->conn->prepare("
        SELECT
            id_OS_mao           AS id,
            descricao           AS nome,
            'Serviço'           AS categoria,
            'servico'           AS tipo,
            1                   AS qty,
            valor_mao_obra      AS preco,
            1                   AS custom
        FROM ordem_servico_mao_obra
        WHERE ordem_servico_id = ?
          AND tecnico_id IS NULL
    ");
        $stmt->execute([$id]);
        $servicosMaoObra = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_merge($produtos, $servicos, $servicosMaoObra);
    }

    public function getMaoObraByOS($id)
    {
        $stmt = $this->conn->prepare("
            SELECT tecnico_id, valor_mao_obra
            FROM ordem_servico_mao_obra
            WHERE ordem_servico_id = ?
            AND tecnico_id IS NOT NULL
            LIMIT 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Todos os técnicos atribuídos à OS (uma OS pode ter mais de um, cada
     * um com sua própria comissão) — substitui getMaoObraByOS() nos lugares
     * que precisam ver todos, não só o primeiro.
     */
    public function getTecnicosDaOS(int $ordemId): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                mob.id_OS_mao, mob.tecnico_id, u.nome AS tecnico_nome,
                mob.valor_mao_obra, mob.regra_tipo, mob.regra_percentual, mob.base_valor,
                mob.status_pagamento, mob.ajustado_manualmente, mob.observacao_ajuste,
                mob.calculado_em, mob.fechamento_pagamento_id
            FROM ordem_servico_mao_obra mob
            INNER JOIN usuarios u ON u.id_usuario = mob.tecnico_id
            WHERE mob.ordem_servico_id = ? AND mob.tecnico_id IS NOT NULL
            ORDER BY mob.id_OS_mao ASC
        ");
        $stmt->execute([$ordemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Atribui técnicos à OS — só ATRIBUI (grava tecnico_id, sem valor
     * nenhum ainda); o cálculo da comissão só acontece na finalização (ver
     * calcularComissoesNaConclusao()). Idempotente: técnico que já tem
     * linha pra essa OS (calculada ou não) não ganha uma segunda.
     */
    private function salvarTecnicosResponsaveis(int $ordemId, array $tecnicoIds): void
    {
        $tecnicoIds = array_unique(array_filter(array_map('intval', $tecnicoIds)));

        if (empty($tecnicoIds)) {
            return;
        }

        $existentes = $this->conn->prepare("
            SELECT tecnico_id FROM ordem_servico_mao_obra
            WHERE ordem_servico_id = ? AND tecnico_id IS NOT NULL
        ");
        $existentes->execute([$ordemId]);
        $jaAtribuidos = array_map('intval', $existentes->fetchAll(PDO::FETCH_COLUMN));

        $stmt = $this->conn->prepare("
            INSERT INTO ordem_servico_mao_obra (ordem_servico_id, tecnico_id, descricao, valor_mao_obra)
            VALUES (?, ?, 'Técnico responsável', 0)
        ");

        foreach ($tecnicoIds as $tecnicoId) {
            if (!in_array($tecnicoId, $jaAtribuidos, true)) {
                $stmt->execute([$ordemId, $tecnicoId]);
            }
        }
    }

    /**
     * Calcula e trava a comissão de cada técnico atribuído à OS, a partir
     * da regra ATUAL de cada um e dos valores FINAIS da OS — chamado uma
     * vez, na finalização (OrdemServicoController::concluir()).
     *
     * calculado_em é a guarda contra duplicidade: só processa quem ainda
     * não tem esse carimbo. Reprocessar uma OS já concluída (reabrir e
     * concluir de novo, por exemplo) não recalcula nada — e como o
     * resultado é gravado (não uma referência à regra), mudar a regra do
     * técnico depois nunca reabre o cálculo de uma OS antiga.
     */
    public function calcularComissoesNaConclusao(int $ordemId): void
    {
        $pendentesStmt = $this->conn->prepare("
            SELECT id_OS_mao, tecnico_id FROM ordem_servico_mao_obra
            WHERE ordem_servico_id = ? AND tecnico_id IS NOT NULL AND calculado_em IS NULL
        ");
        $pendentesStmt->execute([$ordemId]);
        $pendentes = $pendentesStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($pendentes)) {
            return;
        }

        $os = $this->getById($ordemId);

        $itensStmt = $this->conn->prepare("
            SELECT categoria, quantidade, valor_unitario, executado
            FROM ordem_servico_servicos WHERE ordem_servico_id = ?
        ");
        $itensStmt->execute([$ordemId]);
        $itens = $itensStmt->fetchAll(PDO::FETCH_ASSOC);

        require_once __DIR__ . '/TecnicoRemuneracao.php';
        $remuneracao = new TecnicoRemuneracao($this->conn);

        $update = $this->conn->prepare("
            UPDATE ordem_servico_mao_obra
            SET valor_mao_obra = :valor, regra_tipo = :tipo, regra_percentual = :pct,
                base_valor = :base, status_pagamento = 'PENDENTE', calculado_em = NOW()
            WHERE id_OS_mao = :id
        ");

        foreach ($pendentes as $p) {
            $regra = $remuneracao->buscarRegraAtiva((int) $p['tecnico_id']);

            if (!$regra) {
                // Sem regra cadastrada: registra R$0, mas carimba calculado_em
                // do mesmo jeito — senão ficaria pendente de cálculo pra
                // sempre, reaparecendo em toda finalização futura.
                $update->execute([':valor' => 0, ':tipo' => null, ':pct' => null, ':base' => null, ':id' => $p['id_OS_mao']]);
                continue;
            }

            $resultado = $remuneracao->calcularComissao($regra, $os, $itens);

            $update->execute([
                ':valor' => $resultado['valor'],
                ':tipo'  => $resultado['regra_tipo'],
                ':pct'   => $resultado['regra_percentual'],
                ':base'  => $resultado['base_valor'],
                ':id'    => $p['id_OS_mao'],
            ]);
        }
    }

    /** Alguma comissão desta OS já entrou num fechamento de pagamento? */
    public function temComissaoPaga(int $ordemId): bool
    {
        $stmt = $this->conn->prepare("
            SELECT 1 FROM ordem_servico_mao_obra
            WHERE ordem_servico_id = ? AND fechamento_pagamento_id IS NOT NULL
            LIMIT 1
        ");
        $stmt->execute([$ordemId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * A OS voltou para a execução (conferência reprovada): as comissões já
     * calculadas e ainda NÃO pagas são zeradas e voltam a "não calculadas", para
     * serem refeitas sobre os valores finais na próxima conferência. Comissão paga
     * não se mexe — quem chama confere temComissaoPaga() antes.
     */
    public function reabrirComissoesNaoPagas(int $ordemId): int
    {
        $stmt = $this->conn->prepare("
            UPDATE ordem_servico_mao_obra
            SET valor_mao_obra = 0, regra_tipo = NULL, regra_percentual = NULL, base_valor = NULL,
                status_pagamento = NULL, calculado_em = NULL
            WHERE ordem_servico_id = ? AND tecnico_id IS NOT NULL
              AND calculado_em IS NOT NULL AND fechamento_pagamento_id IS NULL
        ");
        $stmt->execute([$ordemId]);

        return $stmt->rowCount();
    }

    /**
     * OS cancelada: a comissão não é devida. Zera as não pagas e mantém o carimbo
     * calculado_em, para o fechamento mensal não tentar recalculá-las.
     */
    public function anularComissoesNaoPagas(int $ordemId): int
    {
        $stmt = $this->conn->prepare("
            UPDATE ordem_servico_mao_obra
            SET valor_mao_obra = 0, base_valor = 0, ajustado_manualmente = 0,
                observacao_ajuste = 'OS cancelada', status_pagamento = 'PENDENTE',
                calculado_em = COALESCE(calculado_em, NOW())
            WHERE ordem_servico_id = ? AND tecnico_id IS NOT NULL AND fechamento_pagamento_id IS NULL
        ");
        $stmt->execute([$ordemId]);

        return $stmt->rowCount();
    }

    /**
     * Situações em que ainda dá para alterar dados, itens e valores da OS.
     * Depois da conferência (AGUARDANDO_RETIRADA) o valor já virou cobrança.
     */
    private const STATUS_EDITAVEIS = ['ABERTA', 'EM_ANDAMENTO', 'AGUARDANDO_PECA', 'AGUARDANDO_CONFERENCIA'];

    /**
     * Salva as alterações do formulário de edição.
     *
     *  - NÃO altera o status: mudar de etapa é uma transição (OrdemServicoFluxo),
     *    com regras, papéis e trilha. O formulário só pede a transição depois.
     *  - NÃO confia nos totais enviados: recalcula dos itens.
     *  - Peça já CONSUMIDA é linha travada — o estoque dela já saiu. Para mudar,
     *    a peça precisa ser devolvida antes (ConsumoEstoque::devolver); tentar
     *    trocar a quantidade ou removê-la aqui é recusado com a explicação.
     *  - Peças planejadas e serviços são reconciliados LINHA A LINHA pelo row_id
     *    que o formulário devolve: o que mudou é atualizado, o que sumiu é
     *    removido, o que é novo é inserido. (Antes tudo era apagado e recriado,
     *    o que zerava o "executado" dos serviços e o percentual.)
     */
    public function update($data)
    {
        $this->ultimoErro = '';
        $id = (int) ($data['id_ordem_servico'] ?? 0);

        try {
            Transacao::executar($this->conn, function () use ($data, $id) {
                $os = $this->conn->prepare('SELECT status FROM ordens_servico WHERE id_ordem_servico = ? FOR UPDATE');
                $os->execute([$id]);
                $status = $os->fetchColumn();

                if ($status === false) {
                    throw new DomainException('Ordem de serviço não encontrada.');
                }

                if (!in_array($status, self::STATUS_EDITAVEIS, true)) {
                    throw new DomainException(
                        'Esta OS está "' . self::rotuloStatus($status) . '" e não aceita mais alterações de itens ou valores.'
                    );
                }

                $this->conn->prepare("
                    UPDATE ordens_servico SET
                        numero           = COALESCE(NULLIF(:numero, ''), numero),
                        data_abertura    = COALESCE(:data_abertura, data_abertura),
                        cliente_id       = :cliente_id,
                        veiculo_id       = :veiculo_id,
                        km_veiculo       = :km_veiculo,
                        defeito_relatado = :defeito_relatado,
                        diagnostico      = :diagnostico,
                        observacoes      = :observacoes
                    WHERE id_ordem_servico = :id
                ")->execute([
                    ':id'               => $id,
                    ':numero'           => trim((string) ($data['numero'] ?? '')),
                    ':data_abertura'    => !empty($data['data_abertura']) ? str_replace('T', ' ', $data['data_abertura']) : null,
                    ':cliente_id'       => $data['cliente_id']       ?? null,
                    ':veiculo_id'       => $data['veiculo_id']       ?? null,
                    ':km_veiculo'       => !empty($data['km_veiculo']) ? $data['km_veiculo'] : null,
                    ':defeito_relatado' => $data['defeito_relatado'] ?? null,
                    ':diagnostico'      => $data['diagnostico']      ?? null,
                    ':observacoes'      => $data['observacoes']      ?? null,
                ]);

                $itens = (array) ($data['itens'] ?? []);

                $this->reconciliarProdutos($id, array_filter($itens, fn($i) => ($i['tipo'] ?? '') === 'produto'));
                $this->reconciliarServicos($id, array_filter($itens, fn($i) => ($i['tipo'] ?? '') === 'servico'));

                // Só apaga linha de técnico ainda NÃO calculada — comissão calculada
                // (calculado_em) é histórico e nunca é recriada por edição.
                $this->conn->prepare("DELETE FROM ordem_servico_mao_obra WHERE ordem_servico_id = ? AND tecnico_id IS NOT NULL AND calculado_em IS NULL")->execute([$id]);
                $this->salvarTecnicosResponsaveis($id, (array) ($data['tecnicos'] ?? []));

                $this->recalcularTotais($id);
            });

            return true;
        } catch (DomainException $e) {
            $this->ultimoErro = $e->getMessage();
            return false;
        } catch (Throwable $e) {
            error_log('[OS update] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            $this->ultimoErro = 'Não foi possível salvar a ordem de serviço.';
            return false;
        }
    }

    /**
     * Peças: confronta o que o formulário enviou com o que está gravado.
     * Roda dentro da transação de update().
     *
     * @param array<int,array> $postados linhas de peça enviadas
     */
    private function reconciliarProdutos(int $ordemId, array $postados): void
    {
        $existentes = $this->conn->prepare("
            SELECT pr.id_OS_produto, pr.status, pr.quantidade, pr.valor_unitario, p.nome
            FROM ordem_servico_produtos pr
            JOIN produtos p ON p.id_produto = pr.produto_id
            WHERE pr.ordem_servico_id = ? AND pr.status IN ('PLANEJADO','RESERVADO','CONSUMIDO')
            FOR UPDATE
        ");
        $existentes->execute([$ordemId]);

        $porLinha = [];
        foreach ($existentes->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $porLinha[(int) $linha['id_OS_produto']] = $linha;
        }

        $mantidas = [];

        foreach ($postados as $item) {
            $rowId = (int) ($item['row_id'] ?? 0);

            if ($rowId > 0 && isset($porLinha[$rowId])) {
                $atual = $porLinha[$rowId];
                $mantidas[$rowId] = true;

                $qtd   = $this->quantidade($item['qty'] ?? $atual['quantidade']);
                $preco = $this->money($item['preco'] ?? $atual['valor_unitario']);

                if ($atual['status'] === 'CONSUMIDO') {
                    if (abs($qtd - (float) $atual['quantidade']) > 0.0005 || abs($preco - (float) $atual['valor_unitario']) > 0.005) {
                        throw new DomainException(
                            'A peça "' . $atual['nome'] . '" já foi consumida e não pode ser alterada aqui. '
                            . 'Devolva a peça ao estoque primeiro (na tela da OS) e lance a correção.'
                        );
                    }
                    continue;
                }

                $this->conn->prepare("UPDATE ordem_servico_produtos SET quantidade = ?, valor_unitario = ? WHERE id_OS_produto = ?")
                    ->execute([$qtd, $preco, $rowId]);

                // Mudou a quantidade de uma peça reservada: a reserva era da quantidade antiga e
                // some (o saldo reservado não pode ficar maior que o que o estoque cobre).
                if ($atual['status'] === 'RESERVADO' && abs($qtd - (float) $atual['quantidade']) > 0.0005) {
                    $this->conn->prepare("UPDATE ordem_servico_produtos SET status = 'PLANEJADO', reservado_em = NULL, reservado_por = NULL WHERE id_OS_produto = ?")
                        ->execute([$rowId]);
                }

                continue;
            }

            $this->inserirItemProduto($ordemId, $item);
        }

        foreach ($porLinha as $rowId => $atual) {
            if (isset($mantidas[$rowId])) {
                continue;
            }

            if ($atual['status'] === 'CONSUMIDO') {
                throw new DomainException(
                    'A peça "' . $atual['nome'] . '" já foi consumida e não pode ser removida da OS. '
                    . 'Devolva-a ao estoque primeiro (na tela da OS).'
                );
            }

            // Peça só planejada nunca mexeu em estoque: sair da OS é só apagar a linha.
            $this->conn->prepare("DELETE FROM ordem_servico_produtos WHERE id_OS_produto = ?")->execute([$rowId]);
        }
    }

    /**
     * Serviços: mesma ideia das peças — atualiza a linha que tem row_id (mantendo
     * o "executado"), insere a nova, remove a que sumiu. Os serviços avulsos
     * antigos (guardados em mão de obra sem técnico) são migrados para
     * ordem_servico_servicos na primeira edição.
     */
    private function reconciliarServicos(int $ordemId, array $postados): void
    {
        $existentes = $this->conn->prepare("SELECT id FROM ordem_servico_servicos WHERE ordem_servico_id = ? FOR UPDATE");
        $existentes->execute([$ordemId]);
        $ids = array_map('intval', $existentes->fetchAll(PDO::FETCH_COLUMN));

        $this->conn->prepare("DELETE FROM ordem_servico_mao_obra WHERE ordem_servico_id = ? AND tecnico_id IS NULL")->execute([$ordemId]);

        $mantidos = [];

        foreach ($postados as $item) {
            $rowId = (int) ($item['row_id'] ?? 0);

            if ($rowId > 0 && in_array($rowId, $ids, true)) {
                $mantidos[$rowId] = true;
                $this->atualizarItemServico($rowId, $item);
                continue;
            }

            $this->salvarItemServico($ordemId, $item);
        }

        foreach ($ids as $rowId) {
            if (!isset($mantidos[$rowId])) {
                $this->conn->prepare("DELETE FROM ordem_servico_servicos WHERE id = ?")->execute([$rowId]);
            }
        }
    }

    /** Lança a peça como PLANEJADO. NÃO toca no estoque. Devolve o id da linha. */
    private function inserirItemProduto($ordemId, $item): int
    {
        $produtoId = $item['id'] ?? null;

        if (empty($produtoId) || str_starts_with((string) $produtoId, 'custom_')) {
            return 0;
        }

        $qtd   = $this->quantidade($item['qty'] ?? 1);
        $preco = $this->money($item['preco'] ?? 0);

        if ($qtd <= 0) {
            throw new DomainException('Quantidade inválida em uma das peças.');
        }

        if ($preco < 0) {
            throw new DomainException('Valor inválido em uma das peças.');
        }

        $this->conn->prepare("
            INSERT INTO ordem_servico_produtos (ordem_servico_id, produto_id, quantidade, valor_unitario, status)
            VALUES (?, ?, ?, ?, 'PLANEJADO')
        ")->execute([(int) $ordemId, (int) $produtoId, $qtd, $preco]);

        return (int) $this->conn->lastInsertId();
    }

    private function atualizarItemServico(int $rowId, array $item): void
    {
        $percentual = isset($item['pct']) && $item['pct'] !== '' ? (float) $item['pct'] : null;

        $this->conn->prepare("
            UPDATE ordem_servico_servicos
            SET nome = ?, categoria = ?, quantidade = ?, valor_unitario = ?, personalizado = ?, percentual_base = ?
            WHERE id = ?
        ")->execute([
            $item['nome'] ?? 'Serviço',
            $item['categoria'] ?? null,
            $this->quantidade($item['qty'] ?? 1) ?: 1,
            $this->money($item['preco'] ?? 0),
            $this->ehCustom($item) ? 1 : 0,
            $percentual,
            $rowId,
        ]);
    }

    /** O formulário manda '1'/'0'; o orçamento aprovado chega com inteiro. */
    private function ehCustom(array $item): bool
    {
        return in_array($item['custom'] ?? 0, [1, '1', true], true);
    }

    private function quantidade($valor): float
    {
        return round((float) str_replace(',', '.', (string) $valor), 3);
    }

    private function salvarItemServico($ordemId, $item)
    {
        $isCustom  = $this->ehCustom($item);
        $servicoId = null;

        if (!$isCustom && !str_starts_with((string)($item['id'] ?? ''), 'custom_')) {
            $servicoId = (int) ($item['id'] ?? 0) ?: null;
        }

        $percentual = isset($item['pct']) && $item['pct'] !== ''
            ? (float) $item['pct']
            : null;

        $this->conn->prepare("
            INSERT INTO ordem_servico_servicos (
                ordem_servico_id,
                servico_id,
                nome,
                categoria,
                quantidade,
                valor_unitario,
                personalizado,
                percentual_base
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $ordemId,
            $servicoId,
            $item['nome']      ?? 'Serviço',
            $item['categoria'] ?? null,
            $this->quantidade($item['qty'] ?? 1) ?: 1,
            $this->money($item['preco'] ?? 0),
            $isCustom ? 1 : 0,
            $percentual,
        ]);
    }

    /**
     * Registros de OUTROS fluxos que apontam para a OS. Enquanto existirem, a
     * exclusão é barrada: eles têm vida própria e apagá-los junto destruiria
     * informação que não é da OS.
     *
     * `agendamentos` tem foreign key RESTRICT no banco, então sem esta
     * checagem a exclusão estourava uma PDOException não tratada e o usuário
     * recebia uma tela de erro 500 sem entender o que impediu.
     *
     * Movimentações de estoque e lançamentos de caixa entram aqui pelo mesmo
     * motivo — e porque são HISTÓRICO: uma OS que já baixou peça ou recebeu
     * dinheiro não "deixa de existir"; ela é cancelada, e o rastro fica.
     */
    private const VINCULOS = [
        ['tabela' => 'agendamentos',           'coluna' => 'ordem_servico_id', 'rotulo' => 'agendamentos'],
        ['tabela' => 'movimentacoes_estoque',  'coluna' => 'ordem_servico_id', 'rotulo' => 'movimentações de estoque'],
        ['tabela' => 'movimentacoes_caixa',    'coluna' => 'ordem_servico_id', 'rotulo' => 'lançamentos de caixa'],
        // Foto de evidência é registro do que foi encontrado/feito: não some junto com a OS.
        ['tabela' => 'evidencias_os',          'coluna' => 'ordem_servico_id', 'rotulo' => 'foto(s) de evidência'],
    ];

    /**
     * Itens que SÃO a OS e saem junto com ela.
     *
     * Nenhuma dessas tabelas tem foreign key para ordens_servico, então o
     * banco não removia nada sozinho: excluir uma OS deixava produtos,
     * serviços, mão de obra e anexos para trás, apontando para um id que não
     * existe mais — sem erro e sem aviso.
     */
    private const ITENS = [
        'ordem_servico_produtos',
        'ordem_servico_servicos',
        'ordem_servico_mao_obra',
        'anexos_ordem_servico',
    ];

    /**
     * Descreve o que impede a exclusão da OS, por extenso ("1 agendamentos").
     * Array vazio = pode excluir.
     *
     * Os nomes de tabela/coluna vêm só da constante acima, nunca da requisição.
     */
    public function vinculos($id): array
    {
        $id      = (int) $id;
        $achados = [];

        foreach (self::VINCULOS as $vinculo) {
            $sql = "SELECT COUNT(*) FROM `{$vinculo['tabela']}` WHERE `{$vinculo['coluna']}` = :id";

            try {
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([':id' => $id]);
                $qtd = (int) $stmt->fetchColumn();
            } catch (\PDOException $e) {
                // Tabela ainda não criada nesta instalação (ex.: agendamentos
                // antes da migração) não pode derrubar a checagem.
                continue;
            }

            if ($qtd > 0) {
                $achados[] = $qtd . ' ' . $vinculo['rotulo'];
            }
        }

        // Recebimento já registrado é dinheiro que entrou: não some com a OS.
        $stmtRecebida = $this->conn->prepare("
            SELECT COUNT(*) FROM ordem_servico_parcelas WHERE ordem_servico_id = ? AND pago = 1
        ");
        $stmtRecebida->execute([$id]);
        $qtdRecebida = (int) $stmtRecebida->fetchColumn();

        if ($qtdRecebida > 0) {
            $achados[] = $qtdRecebida . ' parcela(s) já recebida(s)';
        }

        // Comissão já paga é histórico financeiro fechado — não dá pra
        // excluir a OS que a gerou, senão o pagamento fica sem explicação
        // no extrato do técnico. Fora do loop de VINCULOS porque não é uma
        // contagem simples de FK, é "tem alguma linha PAGA".
        $stmtPaga = $this->conn->prepare("
            SELECT COUNT(*) FROM ordem_servico_mao_obra
            WHERE ordem_servico_id = ? AND fechamento_pagamento_id IS NOT NULL
        ");
        $stmtPaga->execute([$id]);
        $qtdPaga = (int) $stmtPaga->fetchColumn();

        if ($qtdPaga > 0) {
            $achados[] = $qtdPaga . ' comissão(ões) de técnico já paga(s)';
        }

        return $achados;
    }

    /**
     * Exclui a OS e os itens que pertencem a ela, tudo numa transação: ou some
     * o conjunto inteiro, ou não some nada. Sem isso, uma falha no meio deixava
     * a OS apagada e os itens vivos (ou o contrário).
     *
     * Só é possível enquanto a OS não tem história: nenhuma peça consumida,
     * nenhum lançamento de caixa, nenhuma parcela recebida (ver vinculos()). Uma
     * OS que já movimentou estoque ou dinheiro se CANCELA — o cancelamento
     * devolve as peças e deixa o rastro. As peças apenas planejadas nunca
     * tocaram o estoque, então apagar a OS não tem o que estornar.
     *
     * A checagem roda de novo AQUI, com a OS travada, porque entre a tela e este
     * clique outra pessoa pode ter registrado um recebimento.
     *
     * Retorna false — em vez de estourar PDOException, que virava tela 500 —
     * quando algo impede a exclusão.
     */
    public function delete($id)
    {
        $id = (int) $id;

        try {
            return Transacao::executar($this->conn, function () use ($id) {
                $os = $this->conn->prepare('SELECT status FROM ordens_servico WHERE id_ordem_servico = ? FOR UPDATE');
                $os->execute([$id]);
                $status = $os->fetchColumn();

                if ($status === false) {
                    return false;
                }

                if ($this->vinculos($id)) {
                    return false;
                }

                foreach (self::ITENS as $tabela) {
                    $this->conn->prepare("DELETE FROM `{$tabela}` WHERE ordem_servico_id = ?")->execute([$id]);
                }

                $this->conn->prepare('DELETE FROM ordem_servico_parcelas WHERE ordem_servico_id = ?')->execute([$id]);

                $stmt = $this->conn->prepare("DELETE FROM ordens_servico WHERE id_ordem_servico = ?");
                $stmt->execute([$id]);
                $removidas = $stmt->rowCount();

                if ($removidas > 0) {
                    Historico::registrar($this->conn, 'OS', $id, (string) $status, 'EXCLUIDA', $this->usuarioLogado(), 'OS excluída');
                }

                return $removidas > 0;
            });
        } catch (\PDOException $e) {
            error_log('[OrdemServico delete] ' . $e->getMessage());

            return false;
        }
    }

    private function money($valor)
    {
        if ($valor === null || $valor === '') {
            return 0;
        }
        return (float) str_replace(',', '.', $valor);
    }
}
