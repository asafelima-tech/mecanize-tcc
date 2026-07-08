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
