<?php

/**
 * Caixa da oficina: uma sessão aberta por vez (o banco garante). Todo recebimento e pagamento feito com
 * o caixa aberto fica ligado a ele; o dinheiro em espécie OBRIGATORIAMENTE passa por ele (o banco recusa
 * movimento em dinheiro sem caixa).
 *
 * Ninguém digita valor na abertura nem no fechamento: o sistema já registra cada entrada e saída.
 *   Saldo anterior = dinheiro que ficou na gaveta no fechamento anterior (só no primeiro caixa é informado).
 *   Saldo final    = saldo anterior + entradas − saídas (todas as formas de pagamento).
 *   Dinheiro       = saldo anterior + entradas em dinheiro − saídas em dinheiro (suprimento entra, sangria sai).
 * PIX, cartão, boleto e transferência caem na conta da oficina; só o dinheiro fica na gaveta e passa
 * para o caixa seguinte como saldo anterior. Colunas: valor_abertura = saldo anterior;
 * valor_esperado = valor_fechamento = dinheiro calculado no fechamento.
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

    /** Último caixa fechado: o dinheiro que ficou nele é o saldo anterior do próximo. */
    public function ultimoFechado(): ?array
    {
        return $this->linha("SELECT c.id, c.valor_fechamento, c.fechado_em, u.nome AS fechado_por_nome FROM caixas c
                             LEFT JOIN usuarios u ON u.id = c.fechado_por
                             WHERE c.oficina_id = :oficina AND c.status = 'FECHADO' ORDER BY c.fechado_em DESC, c.id DESC LIMIT 1");
    }

    /**
     * Abre o caixa com o saldo anterior vindo do último fechamento. O valor digitado só é usado no
     * primeiro caixa da oficina (quando não existe fechamento para servir de base).
     */
    public function abrir(string $saldoInicial, string $observacao): int
    {
        if ($this->aberto()) {
            throw new DomainException('Já existe um caixa aberto.');
        }
        $anterior = $this->ultimoFechado();
        if ($anterior) {
            $v = (float) $anterior['valor_fechamento'];
        } else {
            if (trim($saldoInicial) === '') {
                throw new DomainException('Informe quanto há em dinheiro na gaveta para começar.');
            }
            $v = Produto::valorDecimal($saldoInicial);
            if ($v < 0) {
                throw new DomainException('O saldo inicial não pode ser negativo.');
            }
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

    /** Fecha a sessão com o saldo calculado pelos lançamentos. Nada é digitado. */
    public function fechar(string $observacao): array
    {
        return Transacao::executar($this->db, function () use ($observacao) {
            $caixa = $this->exigirAberto(true);
            $f = $this->fechamento((int) $caixa['id']);
            $this->atualizar('caixas', (int) $caixa['id'], [
                'status' => 'FECHADO', 'fechado_por' => Contexto::usuarioId(), 'fechado_em' => date('Y-m-d H:i:s'),
                'valor_esperado' => $f['dinheiro'], 'valor_fechamento' => $f['dinheiro'], 'observacao_fechamento' => mb_substr(trim($observacao), 0, 255) ?: null,
            ]);

            return ['id' => (int) $caixa['id']] + $f;
        });
    }

    /**
     * Fechamento da sessão (o que já aconteceu, ou até agora se ainda está aberta):
     * saldo anterior, entradas, saídas e saldo final, no total e por forma de pagamento.
     * Devolve: anterior, entradas, saidas, saldo, dinheiro, outras, formas[forma => anterior/entradas/saidas/saldo]
     */
    public function fechamento(int $caixaId): array
    {
        $caixa = $this->buscar($caixaId) ?? throw new ErroHttp('registro-inexistente');
        $anterior = (float) $caixa['valor_abertura'];
        $formas = ['DINHEIRO' => ['anterior' => $anterior, 'entradas' => 0.0, 'saidas' => 0.0]];
        foreach ($this->lista(
            'SELECT forma_pagamento, direcao, SUM(valor) AS total FROM movimentacoes_financeiras
             WHERE oficina_id = :oficina AND caixa_id = :c GROUP BY forma_pagamento, direcao', ['c' => $caixaId]) as $l) {
            $formas[$l['forma_pagamento']] ??= ['anterior' => 0.0, 'entradas' => 0.0, 'saidas' => 0.0];
            $formas[$l['forma_pagamento']][$l['direcao'] === 'ENTRADA' ? 'entradas' : 'saidas'] += (float) $l['total'];
        }
        // Na ordem das formas de pagamento (dinheiro primeiro).
        $formas = array_intersect_key(array_merge(array_fill_keys(array_keys(Lancamento::FORMAS), null), $formas), $formas);

        $entradas = $saidas = 0.0;
        foreach ($formas as &$f) {
            $f['saldo'] = round($f['anterior'] + $f['entradas'] - $f['saidas'], 2);
            $entradas += $f['entradas'];
            $saidas += $f['saidas'];
        }
        unset($f);

        // Sessão antiga, fechada por contagem: vale o dinheiro que de fato ficou registrado.
        $dinheiro = $caixa['status'] === 'FECHADO' && $caixa['valor_fechamento'] !== null ? (float) $caixa['valor_fechamento'] : $formas['DINHEIRO']['saldo'];
        $outras = round(array_sum(array_column($formas, 'saldo')) - $formas['DINHEIRO']['saldo'], 2);

        return [
            'anterior' => round($anterior, 2), 'entradas' => round($entradas, 2), 'saidas' => round($saidas, 2),
            'dinheiro' => round($dinheiro, 2), 'outras' => $outras, 'saldo' => round($dinheiro + $outras, 2),
            'formas' => $formas,
        ];
    }

    /** Totais da sessão por forma e o dinheiro esperado na gaveta (usado pela sangria e pelas telas antigas). */
    public function resumo(int $caixaId): array
    {
        $f = $this->fechamento($caixaId);
        $porForma = [];
        foreach ($f['formas'] as $forma => $x) {
            if ($x['entradas'] > 0) {
                $porForma[$forma]['ENTRADA'] = $x['entradas'];
            }
            if ($x['saidas'] > 0) {
                $porForma[$forma]['SAIDA'] = $x['saidas'];
            }
        }

        return ['por_forma' => $porForma, 'esperado' => $f['formas']['DINHEIRO']['saldo']];
    }

    public function movimentos(int $caixaId): array
    {
        return $this->lista(
            "SELECT m.*, u.nome AS usuario, l.descricao AS lancamento, l.tipo AS lancamento_tipo, l.categoria AS lancamento_categoria,
                    l.ordem_servico_id, cl.nome AS cliente
             FROM movimentacoes_financeiras m
             LEFT JOIN usuarios u ON u.id = m.usuario_id
             LEFT JOIN lancamentos l ON l.oficina_id = m.oficina_id AND l.id = m.lancamento_id
             LEFT JOIN ordens_servico os ON os.oficina_id = l.oficina_id AND os.id = l.ordem_servico_id
             LEFT JOIN clientes cl ON cl.oficina_id = os.oficina_id AND cl.id = os.cliente_id
             WHERE m.oficina_id = :oficina AND m.caixa_id = :c ORDER BY m.data_movimento DESC, m.id DESC",
            ['c' => $caixaId]
        );
    }

    /** Sessões, da mais recente para a mais antiga, com entradas e saídas somadas (dinheiro e demais formas). */
    public function historico(Paginacao $p): array
    {
        $p->total = (int) $this->valor('SELECT COUNT(*) FROM caixas WHERE oficina_id = :oficina');

        $caixas = $this->lista(
            "SELECT c.*, ua.nome AS aberto_por_nome, uf.nome AS fechado_por_nome,
                    COALESCE(t.entradas, 0) AS entradas, COALESCE(t.saidas, 0) AS saidas,
                    COALESCE(t.entradas_dinheiro, 0) AS entradas_dinheiro, COALESCE(t.saidas_dinheiro, 0) AS saidas_dinheiro
             FROM caixas c
             LEFT JOIN usuarios ua ON ua.id = c.aberto_por LEFT JOIN usuarios uf ON uf.id = c.fechado_por
             LEFT JOIN (SELECT oficina_id, caixa_id,
                               SUM(CASE WHEN direcao = 'ENTRADA' THEN valor ELSE 0 END) AS entradas,
                               SUM(CASE WHEN direcao = 'SAIDA' THEN valor ELSE 0 END) AS saidas,
                               SUM(CASE WHEN direcao = 'ENTRADA' AND forma_pagamento = 'DINHEIRO' THEN valor ELSE 0 END) AS entradas_dinheiro,
                               SUM(CASE WHEN direcao = 'SAIDA' AND forma_pagamento = 'DINHEIRO' THEN valor ELSE 0 END) AS saidas_dinheiro
                        FROM movimentacoes_financeiras WHERE caixa_id IS NOT NULL GROUP BY oficina_id, caixa_id) t
                    ON t.oficina_id = c.oficina_id AND t.caixa_id = c.id
             WHERE c.oficina_id = :oficina ORDER BY c.aberto_em DESC, c.id DESC" . $p->sql()
        );
        foreach ($caixas as &$c) {
            $c += self::saldos($c);
        }

        return $caixas;
    }

    /** Saldo final e dinheiro de uma linha do histórico (mesma regra de fechamento()). */
    public static function saldos(array $c): array
    {
        $calculado = (float) $c['valor_abertura'] + (float) $c['entradas_dinheiro'] - (float) $c['saidas_dinheiro'];
        $dinheiro = $c['status'] === 'FECHADO' && $c['valor_fechamento'] !== null ? (float) $c['valor_fechamento'] : $calculado;
        $outras = ((float) $c['entradas'] - (float) $c['entradas_dinheiro']) - ((float) $c['saidas'] - (float) $c['saidas_dinheiro']);

        return ['dinheiro' => round($dinheiro, 2), 'saldo_final' => round($dinheiro + $outras, 2)];
    }

    public function exigirAberto(bool $travar = false): array
    {
        return $this->linha("SELECT * FROM caixas WHERE oficina_id = :oficina AND status = 'ABERTO'" . ($travar ? ' FOR UPDATE' : ''))
            ?? throw new DomainException('Abra o caixa antes de movimentar dinheiro.');
    }
}
