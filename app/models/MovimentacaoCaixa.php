<?php

/**
 * Livro-caixa: lançamentos de ENTRADA/SAÍDA dentro de uma sessão de caixa.
 *
 * Cada linha pertence a uma sessão da tabela `caixa` (caixa_id). Entradas
 * guardam a forma de pagamento; saídas guardam a categoria.
 *
 * O "caixa atual" (dinheiro físico) é calculado em resumoSessao():
 *   valor_abertura + Σ(entradas em DINHEIRO) − Σ(todas as saídas)
 * PIX/cartão/boleto são registrados e somam nos totais, mas NÃO entram no
 * dinheiro da gaveta.
 */
class MovimentacaoCaixa
{
    public $conn;

    /**
     * Formas de pagamento oferecidas nas telas (case do enum).
     *
     * Débito e crédito são separados porque só o crédito costuma ter
     * parcelamento e taxa de maquininha — somar os dois num "CARTAO" escondia
     * exatamente o que o financeiro precisa enxergar.
     */
    public const FORMAS = ['DINHEIRO', 'PIX', 'CARTAO_DEBITO', 'CARTAO_CREDITO', 'BOLETO', 'TRANSFERENCIA'];

    /**
     * Valor que existe no banco mas não é mais oferecido. Fica aqui para que
     * lançamento antigo continue somando nos relatórios em vez de sumir — o
     * enum da tabela também o mantém.
     */
    public const FORMAS_LEGADAS = ['CARTAO'];

    /** Rótulo de cada forma, para o mapa não ser repetido em cada tela. */
    public const FORMA_LABEL = [
        'DINHEIRO'       => 'Dinheiro',
        'PIX'            => 'PIX',
        'CARTAO_DEBITO'  => 'Cartão de débito',
        'CARTAO_CREDITO' => 'Cartão de crédito',
        'BOLETO'         => 'Boleto',
        'TRANSFERENCIA'  => 'Transferência',
        'CARTAO'         => 'Cartão (registro antigo)',
    ];

    /** Formas oferecidas + as que só existem no histórico. */
    public static function formasTodas(): array
    {
        return array_merge(self::FORMAS, self::FORMAS_LEGADAS);
    }

    /** Rótulo legível de uma forma; devolve a própria chave se não conhecer. */
    public static function rotuloForma(?string $forma): string
    {
        return self::FORMA_LABEL[$forma] ?? ($forma ?: '-');
    }

    /** Categorias aceitas em SAÍDA manual (case do enum). */
    public const CATEGORIAS_SAIDA = ['PECAS', 'BANCO', 'OUTRO'];

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    /**
     * Registra uma movimentação e devolve o ID do lançamento criado.
     * Retorna false se o valor for <= 0 (a validação dos enums fica a cargo
     * do controller).
     *
     * O ID é o que permite amarrar o lançamento à sua origem — a compra de
     * peça no estoque, a parcela da OS — e, com isso, saber que aquilo já
     * foi lançado e não deve ser lançado de novo.
     *
     * $chave torna o lançamento IDEMPOTENTE: com a mesma chave (ex.: "PARCELA:7"),
     * repetir a chamada não cria outra linha — devolve o id da que já existe. É o
     * que protege o livro-caixa de clique duplo, aba antiga e reenvio de webhook.
     * A unicidade é do banco (uq_mov_caixa_chave), não só deste if.
     */
    public function registrar(
        $caixaId,
        $tipo,
        $valor,
        $formaPagamento,
        $categoria,
        $descricao,
        $usuarioId,
        $origem = 'MANUAL',
        $osId = null,
        ?string $chave = null
    ) {
        $valorNum = $this->parseMoney($valor);

        if ($valorNum <= 0) {
            return false;
        }

        if ($chave !== null) {
            $existente = $this->idPorChave($chave);

            if ($existente !== null) {
                return $existente;
            }
        }

        $sql = "INSERT INTO movimentacoes_caixa (
                    caixa_id, tipo, forma_pagamento, categoria, valor,
                    descricao, usuario_id, origem, ordem_servico_id, chave_idempotencia
                ) VALUES (
                    :caixa_id, :tipo, :forma, :categoria, :valor,
                    :descricao, :usuario_id, :origem, :os_id, :chave
                )";

        $stmt = $this->conn->prepare($sql);

        try {
            $ok = $stmt->execute([
                ':caixa_id'   => $caixaId,
                ':tipo'       => $tipo,
                ':forma'      => ($formaPagamento !== null && $formaPagamento !== '') ? $formaPagamento : null,
                ':categoria'  => ($categoria !== null && $categoria !== '') ? $categoria : null,
                ':valor'      => $valorNum,
                ':descricao'  => ($descricao !== null && trim($descricao) !== '') ? trim($descricao) : null,
                ':usuario_id' => $usuarioId,
                ':origem'     => $origem,
                ':os_id'      => $osId,
                ':chave'      => $chave,
            ]);
        } catch (\PDOException $e) {
            // Outra requisição gravou a mesma chave entre o SELECT e o INSERT.
            if ($chave !== null && $e->getCode() === '23000') {
                $existente = $this->idPorChave($chave);

                if ($existente !== null) {
                    return $existente;
                }
            }

            throw $e;
        }

        return $ok ? (int) $this->conn->lastInsertId() : false;
    }

    private function idPorChave(string $chave): ?int
    {
        $stmt = $this->conn->prepare('SELECT id_movimentacao FROM movimentacoes_caixa WHERE chave_idempotencia = ?');
        $stmt->execute([$chave]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * Lançamentos de uma sessão de caixa, com o nome de quem registrou.
     */
    public function porSessao($caixaId)
    {
        $sql = "SELECT m.*, u.nome AS usuario_nome
                FROM movimentacoes_caixa m
                INNER JOIN usuarios u ON u.id_usuario = m.usuario_id
                WHERE m.caixa_id = :id
                ORDER BY m.id_movimentacao DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $caixaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resumo da sessão: entradas por forma de pagamento, saídas por categoria,
     * totais e o caixa atual (dinheiro físico na gaveta).
     */
    public function resumoSessao($caixaId)
    {
        $caixaId = (int) $caixaId;

        // Valor de abertura da própria sessão
        $stmt = $this->conn->prepare("SELECT valor_abertura FROM caixa WHERE id_caixa = :id");
        $stmt->execute([':id' => $caixaId]);
        $valorAbertura = (float) ($stmt->fetchColumn() ?: 0);

        // Entradas por forma de pagamento (zeradas por padrão)
        $entradas = array_fill_keys(self::formasTodas(), 0.0);

        $stmt = $this->conn->prepare("
            SELECT forma_pagamento, COALESCE(SUM(valor), 0) AS total
            FROM movimentacoes_caixa
            WHERE caixa_id = :id AND tipo = 'ENTRADA'
            GROUP BY forma_pagamento
        ");
        $stmt->execute([':id' => $caixaId]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $forma = $row['forma_pagamento'];
            if ($forma !== null && array_key_exists($forma, $entradas)) {
                $entradas[$forma] = (float) $row['total'];
            }
        }

        // Saídas por categoria (zeradas por padrão; categoria nula vira OUTRO)
        $saidas = array_fill_keys(self::CATEGORIAS_SAIDA, 0.0);

        $stmt = $this->conn->prepare("
            SELECT categoria, COALESCE(SUM(valor), 0) AS total
            FROM movimentacoes_caixa
            WHERE caixa_id = :id AND tipo = 'SAIDA'
            GROUP BY categoria
        ");
        $stmt->execute([':id' => $caixaId]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cat = $row['categoria'];
            if ($cat === null || !array_key_exists($cat, $saidas)) {
                $cat = 'OUTRO';
            }
            $saidas[$cat] += (float) $row['total'];
        }

        $totalEntradas = array_sum($entradas);
        $totalSaidas   = array_sum($saidas);

        // Dinheiro na gaveta: só DINHEIRO entra; toda saída sai em dinheiro.
        $caixaAtual = $valorAbertura + $entradas['DINHEIRO'] - $totalSaidas;

        return [
            'valor_abertura' => $valorAbertura,
            'entradas'       => $entradas,
            'saidas'         => $saidas,
            'total_entradas' => $totalEntradas,
            'total_saidas'   => $totalSaidas,
            'caixa_atual'    => $caixaAtual,
        ];
    }

    /**
     * Resumo agregado de um intervalo de datas, atravessando todas as sessões
     * de caixa do período (diferente de resumoSessao(), que olha uma sessão só).
     *
     * Base do relatório diário/semanal/mensal/anual pedido pelo cliente:
     * entradas quebradas por forma de pagamento e saídas por categoria.
     *
     * Aqui NÃO existe "caixa atual" — o dinheiro na gaveta é um conceito de
     * sessão (depende do valor de abertura de cada uma). O que o período tem é
     * o *resultado*: total de entradas − total de saídas.
     *
     * $inicio/$fim: 'YYYY-MM-DD' (ambos inclusivos).
     */
    public function resumoPeriodo($inicio, $fim)
    {
        $params = [':inicio' => $inicio . ' 00:00:00', ':fim' => $fim . ' 23:59:59'];

        // Entradas por forma de pagamento (zeradas por padrão)
        $entradas = array_fill_keys(self::formasTodas(), 0.0);

        $stmt = $this->conn->prepare("
            SELECT forma_pagamento, COALESCE(SUM(valor), 0) AS total
            FROM movimentacoes_caixa
            WHERE tipo = 'ENTRADA' AND data_movimentacao BETWEEN :inicio AND :fim
            GROUP BY forma_pagamento
        ");
        $stmt->execute($params);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $forma = $row['forma_pagamento'];
            if ($forma !== null && array_key_exists($forma, $entradas)) {
                $entradas[$forma] = (float) $row['total'];
            }
        }

        // Saídas por categoria (categoria nula/desconhecida cai em OUTRO)
        $saidas = array_fill_keys(self::CATEGORIAS_SAIDA, 0.0);

        $stmt = $this->conn->prepare("
            SELECT categoria, COALESCE(SUM(valor), 0) AS total
            FROM movimentacoes_caixa
            WHERE tipo = 'SAIDA' AND data_movimentacao BETWEEN :inicio AND :fim
            GROUP BY categoria
        ");
        $stmt->execute($params);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cat = $row['categoria'];
            if ($cat === null || !array_key_exists($cat, $saidas)) {
                $cat = 'OUTRO';
            }
            $saidas[$cat] += (float) $row['total'];
        }

        // Quantidade de lançamentos e sessões de caixa tocadas no período
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) AS qtd, COUNT(DISTINCT caixa_id) AS sessoes
            FROM movimentacoes_caixa
            WHERE data_movimentacao BETWEEN :inicio AND :fim
        ");
        $stmt->execute($params);
        $contagem = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['qtd' => 0, 'sessoes' => 0];

        $totalEntradas = array_sum($entradas);
        $totalSaidas   = array_sum($saidas);

        return [
            'inicio'         => $inicio,
            'fim'            => $fim,
            'entradas'       => $entradas,
            'saidas'         => $saidas,
            'total_entradas' => $totalEntradas,
            'total_saidas'   => $totalSaidas,
            'resultado'      => $totalEntradas - $totalSaidas,
            'qtd_lancamentos' => (int) $contagem['qtd'],
            'qtd_sessoes'    => (int) $contagem['sessoes'],
        ];
    }

    /**
     * Série temporal do período, para o gráfico de evolução.
     * $granularidade: 'dia' (relatório diário/semanal/mensal) ou 'mes' (anual).
     * Devolve linhas [rotulo, entradas, saidas] já ordenadas.
     */
    public function seriePeriodo($inicio, $fim, $granularidade = 'dia')
    {
        // Whitelist: a expressão vai concatenada no SQL, então não pode vir do usuário.
        $formato = $granularidade === 'mes' ? '%Y-%m' : '%Y-%m-%d';

        $sql = "SELECT DATE_FORMAT(data_movimentacao, '{$formato}') AS rotulo,
                       COALESCE(SUM(CASE WHEN tipo = 'ENTRADA' THEN valor END), 0) AS entradas,
                       COALESCE(SUM(CASE WHEN tipo = 'SAIDA'   THEN valor END), 0) AS saidas
                FROM movimentacoes_caixa
                WHERE data_movimentacao BETWEEN :inicio AND :fim
                GROUP BY rotulo
                ORDER BY rotulo";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':inicio' => $inicio . ' 00:00:00', ':fim' => $fim . ' 23:59:59']);

        return array_map(function ($row) {
            return [
                'rotulo'   => $row['rotulo'],
                'entradas' => (float) $row['entradas'],
                'saidas'   => (float) $row['saidas'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Lançamentos detalhados do período (todas as sessões), do mais recente
     * para o mais antigo, com quem registrou e o número da OS quando a entrada
     * veio da conclusão de uma ordem de serviço.
     */
    public function lancamentosPeriodo($inicio, $fim, $limite = 500)
    {
        $limite = (int) $limite;

        $sql = "SELECT m.*, u.nome AS usuario_nome, os.numero AS os_numero
                FROM movimentacoes_caixa m
                INNER JOIN usuarios u ON u.id_usuario = m.usuario_id
                LEFT  JOIN ordens_servico os ON os.id_ordem_servico = m.ordem_servico_id
                WHERE m.data_movimentacao BETWEEN :inicio AND :fim
                ORDER BY m.data_movimentacao DESC, m.id_movimentacao DESC
                LIMIT {$limite}";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':inicio' => $inicio . ' 00:00:00', ':fim' => $fim . ' 23:59:59']);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Normaliza o valor monetário recebido do formulário para float.
     * O front envia o valor cru (ex.: "1234.56") via IMask; este parser
     * também tolera o formato pt-BR ("1.234,56") caso o JS esteja desativado.
     * (Mesma lógica de Caixa::parseMoney.)
     */
    private function parseMoney($valor)
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return 0.0;
        }

        $valor = preg_replace('/[^\d,.\-]/', '', $valor);

        if (strpos($valor, ',') !== false && strpos($valor, '.') !== false) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        } else {
            $valor = str_replace(',', '.', $valor);
        }

        return (float) $valor;
    }
}
