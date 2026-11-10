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
                    c.nome AS cliente, v.placa
             FROM ordens_servico os
             JOIN clientes c ON c.oficina_id = os.oficina_id AND c.id = os.cliente_id
             JOIN veiculos v ON v.oficina_id = os.oficina_id AND v.id = os.veiculo_id
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
}
