<?php

/**
 * Catálogo de peças e serviços.
 *
 * O estoque NÃO é editado no cadastro: ele só muda por movimentação (entrada, baixa por OS, ajuste),
 * para o livro de estoque sempre fechar com o saldo. A única exceção é o estoque inicial de uma
 * peça nova, que já nasce registrado como movimentação.
 */
final class Produto extends Modelo
{
    protected array $mensagensUnicas = [
        'uq_produtos_codigo' => 'Já existe um item com este código interno.',
    ];

    /** Observação do movimento que registra o estoque inicial de uma peça nova (é um ajuste, mas não é correção de inventário). */
    public const OBS_ESTOQUE_INICIAL = 'Estoque inicial no cadastro';

    public function listar(array $f, Paginacao $p): array
    {
        $where  = 'p.oficina_id = :oficina';
        $params = [];

        if (($f['busca'] ?? '') !== '') {
            $where .= ' AND (p.nome LIKE :b1 OR p.codigo_interno LIKE :b2 OR p.codigo_barras LIKE :b3 OR p.marca LIKE :b4)';
            $b = '%' . $f['busca'] . '%';
            $params += ['b1' => $b, 'b2' => $b, 'b3' => $b, 'b4' => $b];
        }
        if (in_array($f['tipo'] ?? '', ['PECA', 'SERVICO'], true)) {
            $where .= ' AND p.tipo = :tipo';
            $params['tipo'] = $f['tipo'];
        }
        if (($f['categoria'] ?? '') !== '') {
            $where .= ' AND p.categoria = :categoria';
            $params['categoria'] = $f['categoria'];
        }
        if (!empty($f['baixo'])) {
            $where .= " AND p.tipo = 'PECA' AND p.estoque_minimo > 0 AND p.estoque_atual <= p.estoque_minimo";
        }
        if (!empty($f['esgotado'])) {
            $where .= " AND p.tipo = 'PECA' AND p.estoque_atual <= 0";
        }
        if (($f['status'] ?? 'ativos') !== 'todos') {
            $where .= ' AND p.ativo = :ativo';
            $params['ativo'] = ($f['status'] ?? 'ativos') === 'inativos' ? 0 : 1;
        }

        $p->total = (int) $this->valor("SELECT COUNT(*) FROM produtos p WHERE {$where}", $params);

        return $this->lista("SELECT p.* FROM produtos p WHERE {$where} ORDER BY p.tipo, p.nome" . $p->sql(), $params);
    }

    /** Números do topo do catálogo (só itens ativos). O valor em inventário é pelo custo: a tela só mostra ao ADMIN. */
    public function resumo(): array
    {
        $l = $this->linha(
            "SELECT SUM(tipo = 'PECA') AS pecas, SUM(tipo = 'SERVICO') AS servicos,
                    SUM(tipo = 'PECA' AND estoque_minimo > 0 AND estoque_atual <= estoque_minimo AND estoque_atual > 0) AS baixo,
                    SUM(tipo = 'PECA' AND estoque_atual <= 0) AS esgotados,
                    COALESCE(SUM(CASE WHEN tipo = 'PECA' AND estoque_atual > 0 THEN estoque_atual * COALESCE(preco_custo, 0) END), 0) AS inventario
             FROM produtos WHERE oficina_id = :oficina AND ativo = 1"
        );

        return ['pecas' => (int) $l['pecas'], 'servicos' => (int) $l['servicos'], 'baixo' => (int) $l['baixo'],
                'esgotados' => (int) $l['esgotados'], 'inventario' => (float) $l['inventario']];
    }

    /** Itens ativos para escolher numa OS (id, tipo, nome, preço, estoque). */
    public function listarParaEscolha(): array
    {
        return $this->lista(
            'SELECT id, tipo, nome, codigo_interno, preco_venda, estoque_atual FROM produtos
             WHERE oficina_id = :oficina AND ativo = 1 ORDER BY tipo DESC, nome'
        );
    }

    public function buscar(int $id): ?array
    {
        return $this->linha('SELECT * FROM produtos WHERE id = :id AND oficina_id = :oficina', ['id' => $id]);
    }

    public function categorias(): array
    {
        return array_column($this->lista(
            "SELECT DISTINCT categoria FROM produtos WHERE oficina_id = :oficina AND categoria IS NOT NULL AND categoria <> '' ORDER BY categoria"
        ), 'categoria');
    }

    public function salvar(array $d, ?int $id = null): int
    {
        $tipo  = $id === null
            ? (($d['tipo'] ?? '') === 'SERVICO' ? 'SERVICO' : 'PECA')
            : ($this->buscar($id) ?? throw new ErroHttp('registro-inexistente'))['tipo'];
        $dados = [
            'nome'           => trim($d['nome']),
            'categoria'      => trim((string) ($d['categoria'] ?? '')) ?: null,
            'codigo_interno' => trim((string) ($d['codigo_interno'] ?? '')) ?: null,
            'descricao'      => trim((string) ($d['descricao'] ?? '')) ?: null,
            'preco_venda'    => self::valorDecimal($d['preco_venda'] ?? '0'),
            'preco_custo'    => self::valorDecimal($d['preco_custo'] ?? '0'),
        ];
        if ($tipo === 'PECA') {
            $dados += [
                'codigo_barras'  => trim((string) ($d['codigo_barras'] ?? '')) ?: null,
                'marca'          => trim((string) ($d['marca'] ?? '')) ?: null,
                'localizacao'    => trim((string) ($d['localizacao'] ?? '')) ?: null,
                'estoque_minimo' => self::valorDecimal($d['estoque_minimo'] ?? '0'),
            ];
        }

        if ($id !== null) {
            $this->atualizar('produtos', $id, $dados);
            return $id;
        }

        return Transacao::executar($this->db, function () use ($dados, $tipo, $d) {
            $inicial = $tipo === 'PECA' ? self::valorDecimal($d['estoque_inicial'] ?? '0') : 0.0;
            $id = $this->inserir('produtos', $dados + ['tipo' => $tipo, 'estoque_atual' => $inicial]);

            if ($inicial != 0) {
                $this->inserir('movimentacoes_estoque', [
                    'produto_id' => $id, 'motivo' => 'AJUSTE', 'delta' => $inicial, 'custo_unitario' => $dados['preco_custo'],
                    'usuario_id' => Contexto::usuarioId(), 'observacao' => self::OBS_ESTOQUE_INICIAL,
                ]);
            }

            return $id;
        });
    }

    public function definirAtivo(int $id, bool $ativo): void
    {
        $this->atualizar('produtos', $id, ['ativo' => $ativo ? 1 : 0]);
    }

    public function remover(int $id): bool
    {
        return $this->apagar('produtos', $id);
    }

    /** Últimas movimentações de estoque da peça (consulta). */
    public function movimentacoes(int $id, int $limite = 15): array
    {
        $limite = max(1, min(100, $limite));

        return $this->lista(
            "SELECT m.*, u.nome AS usuario FROM movimentacoes_estoque m
             LEFT JOIN usuarios u ON u.id = m.usuario_id
             WHERE m.oficina_id = :oficina AND m.produto_id = :id ORDER BY m.criado_em DESC, m.id DESC LIMIT {$limite}",
            ['id' => $id]
        );
    }

    /** "1.234,56" ou "1234.56" → 1234.56 */
    public static function valorDecimal(string $v): float
    {
        $v = trim(str_replace(['R$', ' '], '', $v));
        if ($v === '') {
            return 0.0;
        }
        if (str_contains($v, ',')) {
            $v = str_replace(['.', ','], ['', '.'], $v);
        }

        return round((float) $v, 3);
    }
}
