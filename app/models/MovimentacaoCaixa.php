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

    /** Formas de pagamento aceitas em ENTRADA (case do enum). */
    public const FORMAS = ['DINHEIRO', 'PIX', 'CARTAO', 'BOLETO'];

    /** Categorias aceitas em SAÍDA manual (case do enum). */
    public const CATEGORIAS_SAIDA = ['PECAS', 'BANCO', 'OUTRO'];

    public function __construct()
    {
        $this->conn = new Database();
    }

    /**
     * Registra uma movimentação. Retorna false se o valor for <= 0
     * (a validação dos enums fica a cargo do controller).
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
        $osId = null
    ) {
        $valorNum = $this->parseMoney($valor);

        if ($valorNum <= 0) {
            return false;
        }

        $sql = "INSERT INTO movimentacoes_caixa (
                    caixa_id, tipo, forma_pagamento, categoria, valor,
                    descricao, usuario_id, origem, ordem_servico_id
                ) VALUES (
                    :caixa_id, :tipo, :forma, :categoria, :valor,
                    :descricao, :usuario_id, :origem, :os_id
                )";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ':caixa_id'   => $caixaId,
            ':tipo'       => $tipo,
            ':forma'      => ($formaPagamento !== null && $formaPagamento !== '') ? $formaPagamento : null,
            ':categoria'  => ($categoria !== null && $categoria !== '') ? $categoria : null,
            ':valor'      => $valorNum,
            ':descricao'  => ($descricao !== null && trim($descricao) !== '') ? trim($descricao) : null,
            ':usuario_id' => $usuarioId,
            ':origem'     => $origem,
            ':os_id'      => $osId,
        ]);
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
        $entradas = array_fill_keys(self::FORMAS, 0.0);

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
        $entradas = array_fill_keys(self::FORMAS, 0.0);

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
