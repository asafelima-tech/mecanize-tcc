<?php

/**
 * Caixa (gaveta) da oficina: uma sessão aberta por vez (o banco garante). Todo recebimento e
 * pagamento feito com o caixa aberto fica ligado a ele; o dinheiro em espécie OBRIGATORIAMENTE passa
 * por ele (o banco recusa movimento em dinheiro sem caixa).
 *
 * Dinheiro esperado na gaveta = abertura + entradas em dinheiro − saídas em dinheiro
 * (suprimento é entrada; sangria é saída).
 */
final class Caixa extends Modelo
{
    public function aberto(): ?array
    {
        return $this->linha("SELECT c.*, u.nome AS aberto_por_nome FROM caixas c LEFT JOIN usuarios u ON u.id = c.aberto_por
                             WHERE c.oficina_id = :oficina AND c.status = 'ABERTO'");
    }

    public function buscar(int $id): ?array
    {
        return $this->linha('SELECT c.*, ua.nome AS aberto_por_nome, uf.nome AS fechado_por_nome FROM caixas c
                             LEFT JOIN usuarios ua ON ua.id = c.aberto_por LEFT JOIN usuarios uf ON uf.id = c.fechado_por
                             WHERE c.id = :id AND c.oficina_id = :oficina', ['id' => $id]);
    }

    /** Valor contado no último fechamento: sugestão para a abertura seguinte. */
    public function ultimoFechamento(): ?float
    {
        $v = $this->valor("SELECT valor_fechamento FROM caixas WHERE oficina_id = :oficina AND status = 'FECHADO' ORDER BY fechado_em DESC, id DESC LIMIT 1");

        return $v === false || $v === null ? null : (float) $v;
    }

    public function abrir(string $valor, string $observacao): int
    {
        $v = Produto::valorDecimal($valor);
        if ($v < 0) {
            throw new DomainException('O valor de abertura não pode ser negativo.');
        }
        if ($this->aberto()) {
            throw new DomainException('Já existe um caixa aberto.');
        }
        try {
            return $this->inserir('caixas', ['aberto_por' => Contexto::usuarioId(), 'valor_abertura' => $v, 'observacao_abertura' => trim($observacao) ?: null]);
        } catch (DomainException $e) {
            throw new DomainException('Já existe um caixa aberto.'); // dois cliques ao mesmo tempo: o banco barra o segundo
        }
    }

    /** Suprimento (entrada de troco) ou sangria (retirada) de dinheiro. */
    public function movimentar(string $natureza, string $valor, string $descricao, ?string $chave = null): void
    {
        if (!in_array($natureza, ['SUPRIMENTO', 'SANGRIA'], true)) {
            throw new DomainException('Movimento de caixa inválido.');
        }
        $v = Produto::valorDecimal($valor);
        if ($v <= 0) {
            throw new DomainException('Informe o valor.');
        }
        if (trim($descricao) === '') {
            throw new DomainException('Descreva o motivo (ex.: troco inicial, depósito no banco).');
        }

        Transacao::executar($this->db, function () use ($natureza, $v, $descricao, $chave) {
            $caixa = $this->exigirAberto(true);
            if ($natureza === 'SANGRIA' && $v > $this->resumo((int) $caixa['id'])['esperado'] + 0.004) {
                throw new DomainException('A sangria é maior que o dinheiro que deveria estar na gaveta.');
            }
            $this->inserir('movimentacoes_financeiras', [
                'natureza' => $natureza, 'direcao' => $natureza === 'SUPRIMENTO' ? 'ENTRADA' : 'SAIDA', 'valor' => $v,
                'forma_pagamento' => 'DINHEIRO', 'caixa_id' => $caixa['id'], 'descricao' => mb_substr(trim($descricao), 0, 200),
                'usuario_id' => Contexto::usuarioId(), 'chave_idempotencia' => $chave ?: null,
            ]);
        });
    }

    public function fechar(string $contado, string $observacao): array
    {
        $v = Produto::valorDecimal($contado);
        if ($v < 0) {
            throw new DomainException('O valor contado não pode ser negativo.');
        }

        return Transacao::executar($this->db, function () use ($v, $observacao) {
            $caixa = $this->exigirAberto(true);
            $esperado = $this->resumo((int) $caixa['id'])['esperado'];
            $this->atualizar('caixas', (int) $caixa['id'], [
                'status' => 'FECHADO', 'fechado_por' => Contexto::usuarioId(), 'fechado_em' => date('Y-m-d H:i:s'),
                'valor_esperado' => $esperado, 'valor_fechamento' => $v, 'observacao_fechamento' => trim($observacao) ?: null,
            ]);

            return ['id' => (int) $caixa['id'], 'esperado' => $esperado, 'diferenca' => round($v - $esperado, 2)];
        });
    }

    /** Totais da sessão: por forma de pagamento e o dinheiro esperado na gaveta. */
    public function resumo(int $caixaId): array
    {
        $caixa = $this->buscar($caixaId) ?? throw new ErroHttp('registro-inexistente');
        $porForma = [];
        $esperado = (float) $caixa['valor_abertura'];
        foreach ($this->lista(
            'SELECT forma_pagamento, direcao, SUM(valor) AS total FROM movimentacoes_financeiras
             WHERE oficina_id = :oficina AND caixa_id = :c GROUP BY forma_pagamento, direcao', ['c' => $caixaId]) as $l) {
            $porForma[$l['forma_pagamento']][$l['direcao']] = (float) $l['total'];
            if ($l['forma_pagamento'] === 'DINHEIRO') {
                $esperado += $l['direcao'] === 'ENTRADA' ? (float) $l['total'] : -(float) $l['total'];
            }
        }

        return ['por_forma' => $porForma, 'esperado' => round($esperado, 2)];
    }

    public function movimentos(int $caixaId): array
    {
        return $this->lista(
            "SELECT m.*, u.nome AS usuario, l.descricao AS lancamento, l.tipo AS lancamento_tipo, l.ordem_servico_id
             FROM movimentacoes_financeiras m
             LEFT JOIN usuarios u ON u.id = m.usuario_id
             LEFT JOIN lancamentos l ON l.oficina_id = m.oficina_id AND l.id = m.lancamento_id
             WHERE m.oficina_id = :oficina AND m.caixa_id = :c ORDER BY m.data_movimento DESC, m.id DESC",
            ['c' => $caixaId]
        );
    }

    public function historico(Paginacao $p): array
    {
        $p->total = (int) $this->valor('SELECT COUNT(*) FROM caixas WHERE oficina_id = :oficina');

        return $this->lista(
            'SELECT c.*, ua.nome AS aberto_por_nome, uf.nome AS fechado_por_nome FROM caixas c
             LEFT JOIN usuarios ua ON ua.id = c.aberto_por LEFT JOIN usuarios uf ON uf.id = c.fechado_por
             WHERE c.oficina_id = :oficina ORDER BY c.aberto_em DESC, c.id DESC' . $p->sql()
        );
    }

    public function exigirAberto(bool $travar = false): array
    {
        return $this->linha("SELECT * FROM caixas WHERE oficina_id = :oficina AND status = 'ABERTO'" . ($travar ? ' FOR UPDATE' : ''))
            ?? throw new DomainException('Abra o caixa antes de movimentar dinheiro.');
    }
}
