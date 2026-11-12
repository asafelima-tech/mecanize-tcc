<?php

/**
 * Consulta de estoque para a EQUIPE (técnico, atendente e administrador).
 *
 * Mostra o que a bancada e o balcão precisam saber — existe a peça? onde fica? quanto está
 * prometido para outras OS? — e NADA de custo, margem ou valor de inventário (isso é do
 * cadastro de produtos, só do administrador).
 *
 *   estoque    saldo físico (o que está na prateleira)
 *   reservado  parte dele já prometida a OS abertas (peças RESERVADO)
 *   disponivel estoque − reservado (o que ainda dá para prometer)
 *   situacao   SEM_ESTOQUE (disponível ≤ 0) · BAIXO (no mínimo ou abaixo) · OK
 */
class EstoqueConsulta
{
    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    /**
     * @param array{busca?:string, situacao?:string, categoria_id?:int} $filtros
     * @return array<int,array<string,mixed>>
     */
    public function listar(array $filtros = [], int $limite = 300): array
    {
        $where  = ['p.ativo = 1'];
        $params = [];

        if (!empty($filtros['busca'])) {
            $where[] = '(p.nome LIKE ? OR p.codigo_interno LIKE ? OR p.codigo_barras LIKE ? OR p.marca LIKE ? OR p.aplicacao LIKE ? OR p.localizacao LIKE ?)';
            $t = '%' . trim($filtros['busca']) . '%';
            array_push($params, $t, $t, $t, $t, $t, $t);
        }

        if (!empty($filtros['categoria_id'])) {
            $where[] = 'p.categorias_produtos_id = ?';
            $params[] = (int) $filtros['categoria_id'];
        }

        $sql = "
            SELECT p.id_produto, p.codigo_interno, p.nome, p.marca, p.aplicacao, p.localizacao,
                   c.nome AS categoria, p.estoque_atual AS estoque, p.estoque_minimo,
                   COALESCE(r.reservado, 0) AS reservado,
                   p.estoque_atual - COALESCE(r.reservado, 0) AS disponivel,
                   (SELECT COUNT(*) FROM solicitacoes_peca s WHERE s.produto_id = p.id_produto
                       AND s.status IN ('SOLICITADA','EM_ANALISE','APROVADA_COMPRA','PEDIDO_REALIZADO','PARCIALMENTE_RECEBIDA')) AS solicitacoes_abertas
            FROM produtos p
            LEFT JOIN categorias_produtos c ON c.id_categoria = p.categorias_produtos_id
            LEFT JOIN (
                SELECT produto_id, SUM(quantidade) AS reservado FROM ordem_servico_produtos WHERE status = 'RESERVADO' GROUP BY produto_id
            ) r ON r.produto_id = p.id_produto
            WHERE " . implode(' AND ', $where) . '
            ORDER BY p.nome
        ';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        $linhas = array_map(function ($l) {
            $l['situacao'] = self::situacao((float) $l['disponivel'], (float) $l['estoque_minimo']);
            return $l;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));

        if (!empty($filtros['situacao']) && in_array($filtros['situacao'], ['SEM_ESTOQUE', 'BAIXO', 'OK'], true)) {
            $linhas = array_values(array_filter($linhas, fn($l) => $l['situacao'] === $filtros['situacao']));
        }

        return array_slice($linhas, 0, $limite);
    }

    public static function situacao(float $disponivel, float $minimo): string
    {
        if ($disponivel <= 0.0005) {
            return 'SEM_ESTOQUE';
        }

        return $minimo > 0 && $disponivel <= $minimo + 0.0005 ? 'BAIXO' : 'OK';
    }

    /** Peças para escolher numa solicitação: sem custo, com o saldo à vista. */
    public function opcoesParaSolicitar(): array
    {
        return array_map(
            fn($l) => ['id' => (int) $l['id_produto'], 'nome' => $l['nome'], 'codigo' => $l['codigo_interno'], 'estoque' => (float) $l['estoque'], 'disponivel' => (float) $l['disponivel'], 'localizacao' => $l['localizacao']],
            $this->listar([], 2000)
        );
    }

    public function categorias(): array
    {
        return $this->conn->query('SELECT id_categoria, nome FROM categorias_produtos ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
    }
}
