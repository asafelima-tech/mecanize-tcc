<?php

/**
 * Relatórios do ADMIN, sempre de um período [início, fim] (datas inclusivas).
 *
 * De onde vem cada número — para não haver duas versões da mesma verdade:
 *  - Faturamento: OS ENTREGUES no período, pelo valor_total da OS (o que foi vendido).
 *  - Recebido / pago: dinheiro que de fato entrou ou saiu (movimentacoes_financeiras), estornos
 *    descontados. É o "regime de caixa"; difere do faturamento quando há venda a prazo.
 *  - Contas em aberto e estoque: posição de HOJE, não do período.
 *  - Itens, técnicos e clientes: itens das OS entregues no período (valores congelados no item).
 */
final class Relatorio extends Modelo
{
    private string $ini;
    private string $fim;

    public function __construct(string $inicio, string $fim, ?PDO $db = null)
    {
        parent::__construct($db);
        $this->ini = $inicio . ' 00:00:00';
        $this->fim = (new DateTimeImmutable($fim))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    }

    private function p(array $extra = []): array
    {
        return ['ini' => $this->ini, 'fim' => $this->fim] + $extra;
    }

    /** Faturamento do período e do período anterior de mesmo tamanho (comparação). */
    public function faturamento(): array
    {
        $sql = "SELECT COUNT(*) AS os, COALESCE(SUM(valor_total), 0) AS total, COALESCE(SUM(valor_servicos), 0) AS servicos,
                       COALESCE(SUM(valor_pecas), 0) AS pecas, COALESCE(SUM(valor_desconto), 0) AS descontos
                FROM ordens_servico WHERE oficina_id = :oficina AND status = 'ENTREGUE' AND entregue_em >= :ini AND entregue_em < :fim";
        $atual = $this->linha($sql, $this->p());

        $dias = (int) (new DateTimeImmutable($this->ini))->diff(new DateTimeImmutable($this->fim))->days;
        $antIni = (new DateTimeImmutable($this->ini))->modify("-{$dias} days")->format('Y-m-d H:i:s');
        $anterior = $this->linha($sql, ['ini' => $antIni, 'fim' => $this->ini]);

        $total = (float) $atual['total'];
        $totalAnt = (float) $anterior['total'];

        return [
            'os'        => (int) $atual['os'],
            'total'     => $total,
            'servicos'  => (float) $atual['servicos'],
            'pecas'     => (float) $atual['pecas'],
            'descontos' => (float) $atual['descontos'],
            'ticket'    => (int) $atual['os'] > 0 ? $total / (int) $atual['os'] : 0.0,
            'anterior'  => $totalAnt,
            'variacao'  => $totalAnt > 0 ? ($total - $totalAnt) / $totalAnt * 100 : null,
        ];
    }

    /** Faturamento por dia (períodos até 62 dias) ou por mês. */
    public function faturamentoNoTempo(): array
    {
        $dias = (int) (new DateTimeImmutable($this->ini))->diff(new DateTimeImmutable($this->fim))->days;
        $porMes = $dias > 62;
        $fmt = $porMes ? '%Y-%m' : '%Y-%m-%d';

        $valores = [];
        foreach ($this->lista("SELECT DATE_FORMAT(entregue_em, '{$fmt}') AS chave, SUM(valor_total) AS total, COUNT(*) AS os
                               FROM ordens_servico WHERE oficina_id = :oficina AND status = 'ENTREGUE' AND entregue_em >= :ini AND entregue_em < :fim
                               GROUP BY chave", $this->p()) as $l) {
            $valores[$l['chave']] = ['total' => (float) $l['total'], 'os' => (int) $l['os']];
        }

        // Preenche os dias/meses sem movimento com zero (o gráfico não "pula" datas).
        $serie = [];
        $d = new DateTimeImmutable($porMes ? substr($this->ini, 0, 7) . '-01' : substr($this->ini, 0, 10));
        $limite = new DateTimeImmutable($this->fim);
        while ($d < $limite) {
            $k = $d->format($porMes ? 'Y-m' : 'Y-m-d');
            $serie[] = ['chave' => $k, 'rotulo' => $porMes ? $d->format('m/Y') : $d->format('d/m')] + ($valores[$k] ?? ['total' => 0.0, 'os' => 0]);
            $d = $d->modify($porMes ? '+1 month' : '+1 day');
        }

        return ['por_mes' => $porMes, 'serie' => $serie];
    }

    /** Situação das OS: abertas hoje e o que aconteceu no período. */
    public function ordens(): array
    {
        $abertas = [];
        foreach ($this->lista("SELECT status, COUNT(*) AS n FROM ordens_servico WHERE oficina_id = :oficina
                               AND status IN ('ORCAMENTO','APROVADA','EM_EXECUCAO','AGUARDANDO_PECA','PRONTA') GROUP BY status") as $l) {
            $abertas[$l['status']] = (int) $l['n'];
        }

        $periodo = $this->linha(
            "SELECT SUM(criado_em >= :ini AND criado_em < :fim) AS abertas,
                    SUM(aprovado_em >= :ini AND aprovado_em < :fim) AS aprovadas,
                    SUM(status = 'RECUSADA' AND recusado_em >= :ini AND recusado_em < :fim) AS recusadas,
                    SUM(status = 'CANCELADA' AND cancelado_em >= :ini AND cancelado_em < :fim) AS canceladas
             FROM ordens_servico WHERE oficina_id = :oficina", $this->p());

        $decididos = (int) $periodo['aprovadas'] + (int) $periodo['recusadas'];

        return [
            'abertas_hoje' => $abertas,
            'abertas'      => (int) $periodo['abertas'],
            'aprovadas'    => (int) $periodo['aprovadas'],
            'recusadas'    => (int) $periodo['recusadas'],
            'canceladas'   => (int) $periodo['canceladas'],
            'aprovacao'    => $decididos > 0 ? (int) $periodo['aprovadas'] / $decididos * 100 : null,
        ];
    }

    /** Dinheiro que entrou e saiu no período, estornos descontados. */
    public function caixa(): array
    {
        $linhas = $this->lista(
            "SELECT l.tipo, mf.forma_pagamento AS forma, l.categoria,
                    SUM(CASE WHEN mf.natureza = 'BAIXA' THEN mf.valor ELSE -mf.valor END) AS valor,
                    SUM(CASE WHEN mf.natureza = 'BAIXA' THEN mf.taxa ELSE -COALESCE(orig.taxa, 0) END) AS taxa
             FROM movimentacoes_financeiras mf
             JOIN lancamentos l ON l.oficina_id = mf.oficina_id AND l.id = mf.lancamento_id
             LEFT JOIN movimentacoes_financeiras orig ON orig.oficina_id = mf.oficina_id AND orig.id = mf.estorno_de_id
             WHERE mf.oficina_id = :oficina AND mf.natureza IN ('BAIXA','ESTORNO') AND mf.data_movimento >= :ini AND mf.data_movimento < :fim
             GROUP BY l.tipo, mf.forma_pagamento, l.categoria", $this->p());

        // O estorno não repete a taxa; ela é devolvida pelo valor do movimento original.
        $r = ['recebido' => 0.0, 'pago' => 0.0, 'taxas' => 0.0, 'por_forma' => [], 'despesas' => [], 'receitas' => []];
        foreach ($linhas as $l) {
            $v = (float) $l['valor'];
            if ($l['tipo'] === 'RECEBER') {
                $r['recebido'] += $v;
                $r['taxas'] += (float) $l['taxa'];
                $r['por_forma'][$l['forma']] = ($r['por_forma'][$l['forma']] ?? 0) + $v;
                $r['receitas'][$l['categoria']] = ($r['receitas'][$l['categoria']] ?? 0) + $v;
            } else {
                $r['pago'] += $v;
                $r['despesas'][$l['categoria']] = ($r['despesas'][$l['categoria']] ?? 0) + $v;
            }
        }
        // Pago e estornado no mesmo período soma zero: não aparece.
        foreach (['por_forma', 'despesas', 'receitas'] as $k) {
            $r[$k] = array_filter($r[$k], fn($v) => abs($v) >= 0.005);
        }
        arsort($r['por_forma']);
        arsort($r['despesas']);
        arsort($r['receitas']);
        $r['saldo'] = $r['recebido'] - $r['taxas'] - $r['pago'];

        return $r;
    }

    /** Posição de hoje das contas em aberto. */
    public function contasEmAberto(): array
    {
        $r = [];
        foreach ($this->lista(
            "SELECT tipo, COUNT(*) AS n, SUM(valor - desconto + acrescimo - valor_pago) AS saldo,
                    SUM(CASE WHEN vencimento < CURDATE() THEN valor - desconto + acrescimo - valor_pago ELSE 0 END) AS vencido,
                    SUM(vencimento < CURDATE()) AS n_vencidas
             FROM lancamentos WHERE oficina_id = :oficina AND status IN ('ABERTO','PARCIAL') GROUP BY tipo") as $l) {
            $r[$l['tipo']] = ['n' => (int) $l['n'], 'saldo' => (float) $l['saldo'], 'vencido' => (float) $l['vencido'], 'n_vencidas' => (int) $l['n_vencidas']];
        }

        return $r + ['RECEBER' => ['n' => 0, 'saldo' => 0.0, 'vencido' => 0.0, 'n_vencidas' => 0],
                     'PAGAR'   => ['n' => 0, 'saldo' => 0.0, 'vencido' => 0.0, 'n_vencidas' => 0]];
    }

    /** Peças e serviços mais vendidos (itens das OS entregues), com margem das peças quando há custo. */
    public function itensMaisVendidos(int $limite = 10): array
    {
        $limite = max(1, min(50, $limite));

        return $this->lista(
            "SELECT i.tipo, i.descricao, SUM(i.quantidade) AS quantidade, SUM(i.valor_total) AS total,
                    SUM(CASE WHEN i.tipo = 'PECA' AND i.custo_unitario IS NOT NULL THEN i.valor_total - ROUND(i.custo_unitario * i.quantidade, 2) END) AS margem
             FROM ordem_servico_itens i
             JOIN ordens_servico os ON os.oficina_id = i.oficina_id AND os.id = i.ordem_servico_id
             WHERE i.oficina_id = :oficina AND os.status = 'ENTREGUE' AND os.entregue_em >= :ini AND os.entregue_em < :fim
               AND i.estoque_status <> 'DEVOLVIDO'
             GROUP BY i.tipo, i.descricao
             ORDER BY total DESC LIMIT {$limite}", $this->p());
    }

    /** Produção por técnico: o técnico do item ou, sem ele, o responsável pela OS. */
    public function tecnicos(): array
    {
        return $this->lista(
            "SELECT COALESCE(u.nome, 'Sem técnico') AS tecnico, COUNT(DISTINCT os.id) AS os,
                    SUM(CASE WHEN i.tipo = 'SERVICO' THEN i.valor_total ELSE 0 END) AS servicos,
                    SUM(i.valor_total) AS total, SUM(i.comissao_valor) AS comissao
             FROM ordem_servico_itens i
             JOIN ordens_servico os ON os.oficina_id = i.oficina_id AND os.id = i.ordem_servico_id
             LEFT JOIN usuarios u ON u.oficina_id = i.oficina_id AND u.id = COALESCE(i.tecnico_id, os.tecnico_id)
             WHERE i.oficina_id = :oficina AND os.status = 'ENTREGUE' AND os.entregue_em >= :ini AND os.entregue_em < :fim
               AND i.estoque_status <> 'DEVOLVIDO'
             GROUP BY u.id, u.nome
             ORDER BY total DESC", $this->p());
    }

    public function clientes(int $limite = 10): array
    {
        $limite = max(1, min(50, $limite));

        return $this->lista(
            "SELECT c.id, c.nome, COUNT(*) AS os, SUM(os.valor_total) AS total
             FROM ordens_servico os JOIN clientes c ON c.oficina_id = os.oficina_id AND c.id = os.cliente_id
             WHERE os.oficina_id = :oficina AND os.status = 'ENTREGUE' AND os.entregue_em >= :ini AND os.entregue_em < :fim
             GROUP BY c.id, c.nome ORDER BY total DESC LIMIT {$limite}", $this->p());
    }

    /** Posição de hoje do estoque. */
    public function estoque(): array
    {
        $r = $this->linha("SELECT COUNT(*) AS pecas, COALESCE(SUM(estoque_atual * preco_custo), 0) AS valor_custo,
                                  COALESCE(SUM(estoque_atual * preco_venda), 0) AS valor_venda
                           FROM produtos WHERE oficina_id = :oficina AND tipo = 'PECA' AND ativo = 1");
        $baixo = $this->lista("SELECT id, nome, estoque_atual, estoque_minimo FROM produtos
                               WHERE oficina_id = :oficina AND tipo = 'PECA' AND ativo = 1 AND estoque_minimo > 0 AND estoque_atual <= estoque_minimo
                               ORDER BY (estoque_atual / estoque_minimo), nome LIMIT 15");

        return ['pecas' => (int) $r['pecas'], 'valor_custo' => (float) $r['valor_custo'], 'valor_venda' => (float) $r['valor_venda'], 'abaixo' => $baixo];
    }

    /** Agenda do período. */
    public function agenda(): array
    {
        $r = array_fill_keys(array_keys(Agendamento::SITUACOES), 0);
        foreach ($this->lista('SELECT status, COUNT(*) AS n FROM agendamentos WHERE oficina_id = :oficina AND data_hora >= :ini AND data_hora < :fim GROUP BY status', $this->p()) as $l) {
            $r[$l['status']] = (int) $l['n'];
        }

        return $r;
    }
}
