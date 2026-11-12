<?php

/**
 * Números da tela inicial: ordens de serviço, agenda, caixa e — para o ADMIN — os alertas de
 * contas vencidas e estoque abaixo do mínimo. Tudo da oficina logada, dados reais.
 */
final class Painel extends Modelo
{
    public function resumo(): array
    {
        $porStatus = [];
        foreach ($this->lista('SELECT status, COUNT(*) AS total FROM ordens_servico WHERE oficina_id = :oficina GROUP BY status') as $l) {
            $porStatus[$l['status']] = (int) $l['total'];
        }

        $mes = $this->linha(
            "SELECT COUNT(*) AS entregues, COALESCE(SUM(valor_total), 0) AS faturado
             FROM ordens_servico
             WHERE oficina_id = :oficina AND status = 'ENTREGUE'
               AND entregue_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
        );

        return [
            'orcamentos'   => $porStatus['ORCAMENTO'] ?? 0,
            'em_andamento' => ($porStatus['APROVADA'] ?? 0) + ($porStatus['EM_EXECUCAO'] ?? 0) + ($porStatus['AGUARDANDO_PECA'] ?? 0),
            'aprovadas'    => $porStatus['APROVADA'] ?? 0,
            'em_execucao'  => $porStatus['EM_EXECUCAO'] ?? 0,
            'aguardando'   => $porStatus['AGUARDANDO_PECA'] ?? 0,
            'prontas'      => $porStatus['PRONTA'] ?? 0,
            'entregues_mes'=> (int) $mes['entregues'],
            'faturado_mes' => (float) $mes['faturado'],
            'clientes'     => (int) $this->valor('SELECT COUNT(*) FROM clientes WHERE oficina_id = :oficina AND ativo = 1'),
        ];
    }

    /** Últimas OS movimentadas. */
    public function ultimasOs(int $limite = 8): array
    {
        $limite = max(1, min(50, $limite));

        return $this->lista(
            "SELECT os.id, os.numero, os.status, os.valor_total, COALESCE(os.atualizado_em, os.criado_em) AS movimentada_em,
                    c.nome AS cliente, v.placa, COALESCE(CONCAT(m.marca, ' ', m.nome), v.modelo_texto) AS modelo
             FROM ordens_servico os
             JOIN clientes c ON c.oficina_id = os.oficina_id AND c.id = os.cliente_id
             JOIN veiculos v ON v.oficina_id = os.oficina_id AND v.id = os.veiculo_id
             LEFT JOIN modelos_veiculos m ON m.id = v.modelo_id
             WHERE os.oficina_id = :oficina
             ORDER BY movimentada_em DESC, os.id DESC
             LIMIT {$limite}"
        );
    }

    /** Alertas do dia. Contas e estoque só para o ADMIN (envolvem valores e custo). */
    public function alertas(bool $admin): array
    {
        $a = [
            'caixa'        => (new Caixa($this->db))->aberto(),
            'aguardando'   => (int) $this->valor("SELECT COUNT(*) FROM ordens_servico WHERE oficina_id = :oficina AND status = 'AGUARDANDO_PECA'"),
            'orc_vencidos' => (int) $this->valor("SELECT COUNT(*) FROM ordens_servico WHERE oficina_id = :oficina AND status = 'ORCAMENTO' AND validade_orcamento < CURDATE()"),
            'agenda_hoje'  => (int) $this->valor("SELECT COUNT(*) FROM agendamentos WHERE oficina_id = :oficina AND status IN ('AGENDADO','CONFIRMADO') AND DATE(data_hora) = CURDATE()"),
        ];
        if ($admin) {
            $a += $this->linha(
                "SELECT SUM(tipo = 'RECEBER') AS receber_vencidas, COALESCE(SUM(CASE WHEN tipo = 'RECEBER' THEN valor - desconto + acrescimo - valor_pago END), 0) AS receber_valor,
                        SUM(tipo = 'PAGAR') AS pagar_vencidas, COALESCE(SUM(CASE WHEN tipo = 'PAGAR' THEN valor - desconto + acrescimo - valor_pago END), 0) AS pagar_valor
                 FROM lancamentos WHERE oficina_id = :oficina AND status IN ('ABERTO','PARCIAL') AND vencimento < CURDATE()"
            );
            $a['estoque_baixo'] = (int) $this->valor("SELECT COUNT(*) FROM produtos WHERE oficina_id = :oficina AND tipo = 'PECA' AND ativo = 1 AND estoque_minimo > 0 AND estoque_atual <= estoque_minimo");
        }

        return $a;
    }

    /** OS em andamento com a etapa (1 aprovada · 2 em execução/aguardando peça · 3 pronta), as mais adiantadas primeiro. */
    public function emAndamento(int $limite = 5): array
    {
        $limite = max(1, min(30, $limite));

        return $this->lista(
            "SELECT os.id, os.numero, os.status, c.nome AS cliente, v.placa,
                    (SELECT GROUP_CONCAT(i.descricao ORDER BY i.id SEPARATOR ' + ') FROM ordem_servico_itens i
                      WHERE i.oficina_id = os.oficina_id AND i.ordem_servico_id = os.id AND i.tipo = 'SERVICO' AND i.estoque_status <> 'DEVOLVIDO') AS servicos
             FROM ordens_servico os
             JOIN clientes c ON c.oficina_id = os.oficina_id AND c.id = os.cliente_id
             JOIN veiculos v ON v.oficina_id = os.oficina_id AND v.id = os.veiculo_id
             WHERE os.oficina_id = :oficina AND os.status IN ('APROVADA','EM_EXECUCAO','AGUARDANDO_PECA','PRONTA')
             ORDER BY FIELD(os.status, 'PRONTA', 'EM_EXECUCAO', 'AGUARDANDO_PECA', 'APROVADA'), os.numero
             LIMIT {$limite}"
        );
    }

    /** Faturamento (OS entregues) de cada um dos últimos $dias dias, do mais antigo para hoje. */
    public function faturamentoDias(int $dias = 7): array
    {
        $dias = max(1, min(31, $dias));
        $valores = [];
        foreach ($this->lista("SELECT DATE(entregue_em) AS dia, SUM(valor_total) AS total FROM ordens_servico
                               WHERE oficina_id = :oficina AND status = 'ENTREGUE' AND entregue_em >= CURDATE() - INTERVAL " . ($dias - 1) . " DAY
                               GROUP BY DATE(entregue_em)") as $l) {
            $valores[$l['dia']] = (float) $l['total'];
        }
        $serie = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $serie[$d] = $valores[$d] ?? 0.0;
        }

        return $serie;
    }

    /**
     * Números dos gráficos da tela inicial (Design System, tela 12). Tudo de OS, por consultas
     * agrupadas — nada é guardado à parte.
     */
    public function graficos(): array
    {
        // Cartões: OS abertas hoje e faturamento do mês contra o mesmo trecho do mês anterior.
        $abertasHoje = (int) $this->valor('SELECT COUNT(*) FROM ordens_servico WHERE oficina_id = :oficina AND criado_em >= CURDATE()');
        $mesAnterior = (float) $this->valor(
            "SELECT COALESCE(SUM(valor_total), 0) FROM ordens_servico
             WHERE oficina_id = :oficina AND status = 'ENTREGUE'
               AND entregue_em >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01')
               AND entregue_em < DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01') + INTERVAL DAY(CURDATE()) DAY"
        );

        // Fluxo dos últimos 7 dias: OS abertas por dia e receita (OS entregues) por dia.
        $abertas = [];
        foreach ($this->lista("SELECT DATE(criado_em) AS dia, COUNT(*) AS n FROM ordens_servico
                               WHERE oficina_id = :oficina AND criado_em >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(criado_em)") as $l) {
            $abertas[$l['dia']] = (int) $l['n'];
        }
        $receita = $this->faturamentoDias(7);
        $fluxo = [];
        foreach ($receita as $dia => $valor) {
            $fluxo[$dia] = ['os' => $abertas[$dia] ?? 0, 'receita' => $valor];
        }

        // Situação das OS abertas neste mês.
        $grupos = ['concluidas' => ['PRONTA', 'ENTREGUE'], 'andamento' => ['APROVADA', 'EM_EXECUCAO'], 'peca' => ['AGUARDANDO_PECA'],
                   'abertas' => ['ORCAMENTO'], 'canceladas' => ['RECUSADA', 'CANCELADA']];
        $porStatus = [];
        foreach ($this->lista("SELECT status, COUNT(*) AS n FROM ordens_servico
                               WHERE oficina_id = :oficina AND criado_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01') GROUP BY status") as $l) {
            $porStatus[$l['status']] = (int) $l['n'];
        }
        $statusMes = [];
        foreach ($grupos as $k => $lista) {
            $statusMes[$k] = array_sum(array_intersect_key($porStatus, array_flip($lista)));
        }

        // Concluídas no mês: tempo médio da abertura até ficar pronta, e quantas ficaram prontas até a previsão.
        $prazo = $this->linha(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, criado_em, concluido_em)) / 1440 AS dias,
                    SUM(previsao_conclusao IS NOT NULL) AS com_previsao,
                    SUM(previsao_conclusao IS NOT NULL AND DATE(concluido_em) <= previsao_conclusao) AS no_prazo
             FROM ordens_servico
             WHERE oficina_id = :oficina AND concluido_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND status IN ('PRONTA','ENTREGUE')"
        );

        // Faturamento (OS entregues) dos últimos 6 meses, do mais antigo para o atual.
        $porMes = [];
        foreach ($this->lista("SELECT DATE_FORMAT(entregue_em, '%Y-%m') AS mes, SUM(valor_total) AS total, COUNT(*) AS n FROM ordens_servico
                               WHERE oficina_id = :oficina AND status = 'ENTREGUE' AND entregue_em >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m-01')
                               GROUP BY mes") as $l) {
            $porMes[$l['mes']] = ['total' => (float) $l['total'], 'n' => (int) $l['n']];
        }
        $meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = date('Y-m', strtotime(date('Y-m-01') . " -{$i} months"));
            $meses[$m] = $porMes[$m]['total'] ?? 0.0;
        }
        $atual = $porMes[date('Y-m')] ?? ['total' => 0.0, 'n' => 0];

        $geral = $this->linha("SELECT COUNT(*) AS n, COALESCE(SUM(valor_total), 0) AS total FROM ordens_servico WHERE oficina_id = :oficina AND status = 'ENTREGUE'");

        // Peças mais vendidas (quantidade em OS entregues nos últimos 6 meses).
        $pecas = $this->lista(
            "SELECT i.descricao, SUM(i.quantidade) AS quantidade
             FROM ordem_servico_itens i
             JOIN ordens_servico os ON os.oficina_id = i.oficina_id AND os.id = i.ordem_servico_id
             WHERE i.oficina_id = :oficina AND i.tipo = 'PECA' AND i.estoque_status <> 'DEVOLVIDO'
               AND os.status = 'ENTREGUE' AND os.entregue_em >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m-01')
             GROUP BY i.descricao ORDER BY quantidade DESC, i.descricao LIMIT 4"
        );

        return [
            'abertas_hoje'  => $abertasHoje,
            'mes_anterior'  => $mesAnterior,
            'fluxo'         => $fluxo,
            'status_mes'    => $statusMes,
            'tempo_medio'   => $prazo['dias'] === null ? null : (float) $prazo['dias'],
            'no_prazo'      => (int) $prazo['com_previsao'] > 0 ? (int) $prazo['no_prazo'] / (int) $prazo['com_previsao'] : null,
            'meses'         => $meses,
            'ticket_mes'    => $atual['n'] > 0 ? $atual['total'] / $atual['n'] : null,
            'ticket_geral'  => (int) $geral['n'] > 0 ? (float) $geral['total'] / (int) $geral['n'] : null,
            'pecas'         => $pecas,
        ];
    }
}
