<?php

/**
 * Movimentação de estoque. Toda mudança de saldo passa por aqui e deixa uma linha no livro
 * (movimentacoes_estoque), na MESMA transação — o saldo da peça sempre fecha com a soma do livro.
 *
 * O saldo nunca fica negativo: a peça é travada (SELECT … FOR UPDATE) e a baixa só acontece se houver.
 */
final class Estoque extends Modelo
{
    /** Baixa a peça de um item de OS. Devolve false (sem mexer em nada) se não houver saldo. */
    public function baixarItemOs(array $item): bool
    {
        if ($item['tipo'] !== 'PECA' || $item['estoque_status'] !== 'PENDENTE' || !$item['produto_id']) {
            return $item['estoque_status'] !== 'PENDENTE';
        }

        return Transacao::executar($this->db, function () use ($item) {
            $saldo = (float) $this->valor('SELECT estoque_atual FROM produtos WHERE id = :id AND oficina_id = :oficina FOR UPDATE', ['id' => $item['produto_id']]);
            if ($saldo + 1e-9 < (float) $item['quantidade']) {
                return false;
            }

            $this->gravar('UPDATE produtos SET estoque_atual = estoque_atual - :q WHERE id = :id AND oficina_id = :oficina',
                ['q' => $item['quantidade'], 'id' => $item['produto_id']]);
            $this->inserir('movimentacoes_estoque', [
                'produto_id' => $item['produto_id'], 'motivo' => 'SAIDA_OS', 'delta' => -1 * (float) $item['quantidade'],
                'custo_unitario' => $item['custo_unitario'], 'ordem_servico_item_id' => $item['id'],
                'usuario_id' => Contexto::usuarioId(), 'observacao' => 'Baixa na OS ' . os_numero($item['os_numero'] ?? ''),
            ]);
            $this->atualizar('ordem_servico_itens', (int) $item['id'], ['estoque_status' => 'BAIXADO']);

            return true;
        });
    }

    /** Devolve ao estoque a peça de um item já baixado (item removido ou OS cancelada). */
    public function devolverItemOs(array $item, string $motivo): void
    {
        if ($item['tipo'] !== 'PECA' || $item['estoque_status'] !== 'BAIXADO') {
            return;
        }

        Transacao::executar($this->db, function () use ($item, $motivo) {
            $this->valor('SELECT id FROM produtos WHERE id = :id AND oficina_id = :oficina FOR UPDATE', ['id' => $item['produto_id']]);
            $this->gravar('UPDATE produtos SET estoque_atual = estoque_atual + :q WHERE id = :id AND oficina_id = :oficina',
                ['q' => $item['quantidade'], 'id' => $item['produto_id']]);
            $this->inserir('movimentacoes_estoque', [
                'produto_id' => $item['produto_id'], 'motivo' => 'DEVOLUCAO_OS', 'delta' => (float) $item['quantidade'],
                'custo_unitario' => $item['custo_unitario'], 'ordem_servico_item_id' => $item['id'],
                'usuario_id' => Contexto::usuarioId(), 'observacao' => mb_substr($motivo, 0, 255),
            ]);
            $this->atualizar('ordem_servico_itens', (int) $item['id'], ['estoque_status' => 'DEVOLVIDO']);
        });
    }

    /* ── Entrada de mercadoria, ajuste e estorno (tela Estoque) ────────── */

    public const MOTIVOS = [
        'COMPRA' => ['Entrada', 'success'], 'SAIDA_OS' => ['Saída por OS', 'info'], 'DEVOLUCAO_OS' => ['Devolução de OS', 'neutral'],
        'AJUSTE' => ['Ajuste', 'warning'], 'ESTORNO' => ['Estorno', 'danger'],
    ];

    /**
     * Entrada de mercadoria (substitui o módulo de compras da v1): várias peças de uma vez, com
     * fornecedor e nota. Opcionalmente atualiza o custo das peças e lança a conta a pagar.
     *
     * @param array $d fornecedor_id, documento, observacao, atualizar_custo, gerar_conta, vencimento, forma,
     *                 itens: [[produto_id, quantidade, custo_unitario], ...]
     * @return int quantidade de itens lançados
     */
    public function entrada(array $d): int
    {
        $itens = [];
        foreach ($d['itens'] ?? [] as $l) {
            if (empty($l['produto_id'])) {
                continue;
            }
            $q = Produto::valorDecimal((string) ($l['quantidade'] ?? '0'));
            $c = Produto::valorDecimal((string) ($l['custo_unitario'] ?? '0'));
            if ($q <= 0) {
                throw new DomainException('Informe a quantidade de cada peça.');
            }
            $itens[] = ['produto_id' => (int) $l['produto_id'], 'quantidade' => $q, 'custo' => $c];
        }
        if (!$itens) {
            throw new DomainException('Inclua pelo menos uma peça na entrada.');
        }

        $fornecedor = !empty($d['fornecedor_id']) ? (int) $d['fornecedor_id'] : null;
        $documento  = mb_substr(trim((string) ($d['documento'] ?? '')), 0, 60) ?: null;

        if ($fornecedor && !$this->valor('SELECT id FROM fornecedores WHERE id = :id AND oficina_id = :oficina', ['id' => $fornecedor])) {
            throw new DomainException('Fornecedor não encontrado.');
        }

        return Transacao::executar($this->db, function () use ($itens, $fornecedor, $documento, $d) {
            $lancamento = null;
            if (!empty($d['gerar_conta'])) {
                $total = round(array_sum(array_map(fn($i) => $i['quantidade'] * $i['custo'], $itens)), 2);
                if ($total <= 0) {
                    throw new DomainException('Para lançar a conta a pagar, informe o custo das peças.');
                }
                if (empty($d['vencimento']) || !strtotime($d['vencimento'])) {
                    throw new DomainException('Informe o vencimento da conta a pagar.');
                }
                $nomeForn = $fornecedor ? $this->valor('SELECT nome FROM fornecedores WHERE id = :id AND oficina_id = :oficina', ['id' => $fornecedor]) : null;
                $lancamento = $this->inserir('lancamentos', [
                    'tipo' => 'PAGAR', 'categoria' => 'PECAS',
                    'descricao' => mb_substr('Compra de peças' . ($documento ? " — NF {$documento}" : '') . ($nomeForn ? " — {$nomeForn}" : ''), 0, 200),
                    'valor' => $total, 'competencia' => date('Y-m-d'), 'vencimento' => date('Y-m-d', strtotime($d['vencimento'])),
                    'forma_prevista' => in_array($d['forma'] ?? '', ['DINHEIRO', 'PIX', 'CARTAO_DEBITO', 'CARTAO_CREDITO', 'BOLETO', 'TRANSFERENCIA', 'OUTRO'], true) ? $d['forma'] : null,
                    'fornecedor_id' => $fornecedor, 'documento' => $documento, 'criado_por' => Contexto::usuarioId(),
                ]);
            }

            foreach ($itens as $i) {
                $p = $this->linha("SELECT id, tipo FROM produtos WHERE id = :id AND oficina_id = :oficina FOR UPDATE", ['id' => $i['produto_id']]);
                if (!$p || $p['tipo'] !== 'PECA') {
                    throw new DomainException('Só peças do catálogo desta oficina entram no estoque.');
                }
                $this->gravar('UPDATE produtos SET estoque_atual = estoque_atual + :q' . (!empty($d['atualizar_custo']) && $i['custo'] > 0 ? ', preco_custo = :c' : '')
                    . ' WHERE id = :id AND oficina_id = :oficina',
                    ['q' => $i['quantidade'], 'id' => $i['produto_id']] + (!empty($d['atualizar_custo']) && $i['custo'] > 0 ? ['c' => $i['custo']] : []));
                $this->inserir('movimentacoes_estoque', [
                    'produto_id' => $i['produto_id'], 'motivo' => 'COMPRA', 'delta' => $i['quantidade'], 'custo_unitario' => $i['custo'],
                    'fornecedor_id' => $fornecedor, 'documento' => $documento, 'lancamento_id' => $lancamento,
                    'observacao' => mb_substr(trim((string) ($d['observacao'] ?? '')), 0, 255) ?: null, 'usuario_id' => Contexto::usuarioId(),
                ]);
            }

            return count($itens);
        });
    }

    /** Acerta o saldo pela contagem física. A justificativa é obrigatória. */
    public function ajustar(int $produtoId, string $contado, string $justificativa): float
    {
        if (trim($justificativa) === '') {
            throw new DomainException('Explique o motivo do ajuste (ex.: contagem, avaria, perda).');
        }
        $novo = Produto::valorDecimal($contado);
        if ($novo < 0) {
            throw new DomainException('A quantidade contada não pode ser negativa.');
        }

        return Transacao::executar($this->db, function () use ($produtoId, $novo, $justificativa) {
            $p = $this->linha('SELECT id, tipo, estoque_atual, preco_custo FROM produtos WHERE id = :id AND oficina_id = :oficina FOR UPDATE', ['id' => $produtoId]);
            if (!$p || $p['tipo'] !== 'PECA') {
                throw new DomainException('Peça não encontrada.');
            }
            $delta = round($novo - (float) $p['estoque_atual'], 3);
            if (abs($delta) < 0.0005) {
                throw new DomainException('A quantidade contada é igual ao saldo atual: não há o que ajustar.');
            }

            $this->gravar('UPDATE produtos SET estoque_atual = :n WHERE id = :id AND oficina_id = :oficina', ['n' => $novo, 'id' => $produtoId]);
            $this->inserir('movimentacoes_estoque', [
                'produto_id' => $produtoId, 'motivo' => 'AJUSTE', 'delta' => $delta, 'custo_unitario' => $p['preco_custo'],
                'observacao' => mb_substr(trim($justificativa), 0, 255), 'usuario_id' => Contexto::usuarioId(),
            ]);

            return $delta;
        });
    }

    /**
     * Desfaz uma entrada ou um ajuste lançado errado: cria um ESTORNO com o movimento contrário
     * (o original continua no livro). Movimentos de OS são desfeitos pela própria OS.
     */
    public function estornar(int $movId, string $motivo): void
    {
        if (trim($motivo) === '') {
            throw new DomainException('Informe o motivo do estorno.');
        }

        Transacao::executar($this->db, function () use ($movId, $motivo) {
            $m = $this->linha('SELECT * FROM movimentacoes_estoque WHERE id = :id AND oficina_id = :oficina FOR UPDATE', ['id' => $movId])
                ?? throw new ErroHttp('registro-inexistente');
            if (!in_array($m['motivo'], ['COMPRA', 'AJUSTE'], true)) {
                throw new DomainException('Só entradas e ajustes podem ser estornados aqui. Movimentos de OS são desfeitos na própria OS.');
            }
            if ($this->valor('SELECT COUNT(*) FROM movimentacoes_estoque WHERE oficina_id = :oficina AND estorno_de_id = :id', ['id' => $movId])) {
                throw new DomainException('Este movimento já foi estornado.');
            }

            $saldo = (float) $this->valor('SELECT estoque_atual FROM produtos WHERE id = :id AND oficina_id = :oficina FOR UPDATE', ['id' => $m['produto_id']]);
            if ($saldo - (float) $m['delta'] < -0.0005) {
                throw new DomainException('O estorno deixaria o estoque negativo (parte destas peças já foi usada).');
            }

            if ($m['lancamento_id']) {
                $l = $this->linha('SELECT * FROM lancamentos WHERE id = :id AND oficina_id = :oficina FOR UPDATE', ['id' => $m['lancamento_id']]);
                if ($l && $l['status'] !== 'CANCELADO') {
                    if ($l['status'] !== 'ABERTO') {
                        throw new DomainException('A conta a pagar desta entrada já tem pagamento. Estorne o pagamento no financeiro antes.');
                    }
                    $restante = round((float) $l['valor'] - (float) $m['delta'] * (float) $m['custo_unitario'], 2);
                    $this->atualizar('lancamentos', (int) $l['id'], $restante > 0.004
                        ? ['valor' => $restante, 'desconto' => min((float) $l['desconto'], $restante)]
                        : ['status' => 'CANCELADO', 'cancelado_em' => date('Y-m-d H:i:s'), 'cancelado_por' => Contexto::usuarioId(), 'motivo_cancelamento' => 'Entrada de estoque estornada']);
                }
            }

            $this->gravar('UPDATE produtos SET estoque_atual = estoque_atual - :d WHERE id = :id AND oficina_id = :oficina', ['d' => $m['delta'], 'id' => $m['produto_id']]);
            $this->inserir('movimentacoes_estoque', [
                'produto_id' => $m['produto_id'], 'motivo' => 'ESTORNO', 'delta' => -1 * (float) $m['delta'], 'custo_unitario' => $m['custo_unitario'],
                'estorno_de_id' => $movId, 'fornecedor_id' => $m['fornecedor_id'], 'documento' => $m['documento'],
                'observacao' => mb_substr(trim($motivo), 0, 255), 'usuario_id' => Contexto::usuarioId(),
            ]);
        });
    }

    /** Livro de movimentações com filtros. */
    public function livro(array $f, Paginacao $p): array
    {
        $where  = 'm.oficina_id = :oficina';
        $params = [];
        if (($f['busca'] ?? '') !== '') {
            $where .= ' AND (pr.nome LIKE :b1 OR pr.codigo_interno LIKE :b2 OR m.documento LIKE :b3)';
            $params += ['b1' => '%' . $f['busca'] . '%', 'b2' => '%' . $f['busca'] . '%', 'b3' => '%' . $f['busca'] . '%'];
        }
        if (isset(self::MOTIVOS[$f['motivo'] ?? ''])) {
            $where .= ' AND m.motivo = :motivo';
            $params['motivo'] = $f['motivo'];
        }
        if (!empty($f['produto'])) {
            $where .= ' AND m.produto_id = :produto';
            $params['produto'] = (int) $f['produto'];
        }
        if (!empty($f['de']) && strtotime($f['de'])) {
            $where .= ' AND m.criado_em >= :de';
            $params['de'] = date('Y-m-d 00:00:00', strtotime($f['de']));
        }
        if (!empty($f['ate']) && strtotime($f['ate'])) {
            $where .= ' AND m.criado_em <= :ate';
            $params['ate'] = date('Y-m-d 23:59:59', strtotime($f['ate']));
        }

        $from = 'FROM movimentacoes_estoque m JOIN produtos pr ON pr.oficina_id = m.oficina_id AND pr.id = m.produto_id';
        $p->total = (int) $this->valor("SELECT COUNT(*) {$from} WHERE {$where}", $params);

        return $this->lista(
            "SELECT m.*, pr.nome AS produto, u.nome AS usuario, fo.nome AS fornecedor, os.id AS os_id, os.numero AS os_numero,
                    (SELECT COUNT(*) FROM movimentacoes_estoque e WHERE e.oficina_id = m.oficina_id AND e.estorno_de_id = m.id) AS estornado
             {$from}
             LEFT JOIN usuarios u ON u.id = m.usuario_id
             LEFT JOIN fornecedores fo ON fo.oficina_id = m.oficina_id AND fo.id = m.fornecedor_id
             LEFT JOIN ordem_servico_itens i ON i.oficina_id = m.oficina_id AND i.id = m.ordem_servico_item_id
             LEFT JOIN ordens_servico os ON os.oficina_id = i.oficina_id AND os.id = i.ordem_servico_id
             WHERE {$where} ORDER BY m.criado_em DESC, m.id DESC" . $p->sql(),
            $params
        );
    }

    /** Números do topo da tela de estoque. */
    public function resumo(): array
    {
        return $this->linha(
            "SELECT COUNT(*) AS pecas,
                    COALESCE(SUM(estoque_minimo > 0 AND estoque_atual <= estoque_minimo), 0) AS abaixo_minimo,
                    COALESCE(SUM(estoque_atual * preco_custo), 0) AS valor_custo
             FROM produtos WHERE oficina_id = :oficina AND tipo = 'PECA' AND ativo = 1"
        );
    }

    /** Peças ativas para os campos de escolha (entrada e ajuste). */
    public function pecas(): array
    {
        return $this->lista("SELECT id, nome, codigo_interno, estoque_atual, preco_custo FROM produtos
                             WHERE oficina_id = :oficina AND tipo = 'PECA' AND ativo = 1 ORDER BY nome");
    }
}
