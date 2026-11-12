<?php

require_once __DIR__ . '/ContaReceber.php';
require_once __DIR__ . '/ContaPagar.php';

/**
 * Indicadores financeiros, com UMA definição para cada número — todos derivados dos registros
 * reais (parcelas, pagamentos, livro de estoque, caixa), nunca de somas soltas.
 *
 *   Faturamento bruto  valor das OS ENTREGUES (concluídas) no período, pela data da entrega.
 *                      Não é dinheiro recebido.
 *   Recebido           parcelas de OS cuja BAIXA aconteceu no período (valor bruto), com as
 *                      TAXAS do meio de pagamento separadas; recebido líquido = bruto − taxas.
 *   A receber          parcelas pendentes de OS já cobradas (hoje), separando o vencido.
 *   A pagar            parcelas pendentes das despesas ativas (hoje). A fatura do cartão é só o
 *                      agrupamento das parcelas: cada parcela conta UMA vez.
 *   Despesas pagas     parcelas quitadas no período (direto ou pela fatura) + fechamentos de
 *                      comissão/salário + saídas do caixa lançadas à mão que não pertencem a uma
 *                      conta a pagar. A compra no cartão, a parcela e o pagamento da fatura são
 *                      UM gasto: o dinheiro só "sai" quando a fatura é paga.
 *   Fluxo realizado    recebido líquido + outras entradas do caixa − despesas pagas.
 *   Fluxo projetado    o que vence nos próximos meses: parcelas a receber − parcelas a pagar.
 *   Resultado operacional estimado (competência)
 *                      faturamento bruto − custo das peças consumidas − comissões − despesas
 *                      operacionais (categorias que afetam o resultado) − salário fixo − taxas.
 *                      Inclui valores ainda a receber: é uma ESTIMATIVA.
 *   Lucro efetivo      só o que de fato entrou das OS do período: recebido líquido dessas OS −
 *                      os custos delas (peças e comissões) − despesas operacionais PAGAS no
 *                      período − salário fixo pago. Parcelamento a receber NÃO conta como lucro.
 *
 * Compra de estoque (categoria ESTOQUE) não é despesa de resultado: o custo da peça entra quando
 * ela é consumida numa OS (livro de estoque). Contar nas duas pontas dobraria o custo.
 */
class Financeiro
{
    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    private function valor(string $sql, array $params): float
    {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return round((float) $stmt->fetchColumn(), 2);
    }

    /** @return array<string,mixed> */
    public function indicadores(string $inicio, string $fim): array
    {
        $p = [$inicio, $fim];

        /* ── competência ── */
        $faturamento = $this->valor("SELECT COALESCE(SUM(valor_total_os), 0) FROM ordens_servico WHERE status = 'CONCLUIDA' AND DATE(data_conclusao) BETWEEN ? AND ?", $p);
        $qtdOs = (int) $this->valor("SELECT COUNT(*) FROM ordens_servico WHERE status = 'CONCLUIDA' AND DATE(data_conclusao) BETWEEN ? AND ?", $p);
        $acrescimos = $this->valor("SELECT COALESCE(SUM(pagamento_total - valor_total_os), 0) FROM ordens_servico WHERE status = 'CONCLUIDA' AND pagamento_total IS NOT NULL AND DATE(data_conclusao) BETWEEN ? AND ?", $p);

        $custoPecas = $this->valor("
            SELECT COALESCE(SUM(-m.delta * COALESCE(m.custo_unitario, 0)), 0)
            FROM movimentacoes_estoque m JOIN ordens_servico os ON os.id_ordem_servico = m.ordem_servico_id
            WHERE m.origem = 'OS' AND m.evento IN ('CONSUMO','DEVOLUCAO') AND os.status = 'CONCLUIDA' AND DATE(os.data_conclusao) BETWEEN ? AND ?
        ", $p);

        $comissoes = $this->valor("
            SELECT COALESCE(SUM(mob.valor_mao_obra), 0)
            FROM ordem_servico_mao_obra mob JOIN ordens_servico os ON os.id_ordem_servico = mob.ordem_servico_id
            WHERE mob.tecnico_id IS NOT NULL AND os.status = 'CONCLUIDA' AND DATE(os.data_conclusao) BETWEEN ? AND ?
        ", $p);

        $despesasCompetencia = $this->valor("SELECT COALESCE(SUM(valor_total), 0) FROM despesas WHERE status = 'ATIVA' AND afeta_resultado = 1 AND data_compra BETWEEN ? AND ?", $p);

        /* ── caixa: o que entrou ── */
        $rec = $this->conn->prepare("SELECT COALESCE(SUM(valor), 0) AS bruto, COALESCE(SUM(taxa_valor), 0) AS taxas, COUNT(*) AS qtd FROM ordem_servico_parcelas WHERE pago = 1 AND DATE(data_pagamento) BETWEEN ? AND ?");
        $rec->execute($p);
        $rec = $rec->fetch(PDO::FETCH_ASSOC);
        $recebidoBruto = round((float) $rec['bruto'], 2);
        $taxas = round((float) $rec['taxas'], 2);
        $recebidoLiquido = round($recebidoBruto - $taxas, 2);

        $outrasEntradas = $this->valor("
            SELECT COALESCE(SUM(m.valor), 0) FROM movimentacoes_caixa m
            WHERE m.tipo = 'ENTRADA' AND m.origem = 'MANUAL' AND DATE(m.data_movimentacao) BETWEEN ? AND ?
              AND NOT EXISTS (SELECT 1 FROM pagamentos_pagar pg WHERE pg.estorno_caixa_id = m.id_movimentacao)
        ", $p);

        /* ── caixa: o que saiu ── */
        $contasPagas = $this->valor("
            SELECT COALESCE(SUM(p.valor), 0) FROM contas_pagar_parcelas p JOIN despesas d ON d.id_despesa = p.despesa_id
            WHERE p.status = 'PAGA' AND d.status = 'ATIVA' AND DATE(p.pago_em) BETWEEN ? AND ?
        ", $p);

        $contasPagasOperacionais = $this->valor("
            SELECT COALESCE(SUM(p.valor), 0) FROM contas_pagar_parcelas p JOIN despesas d ON d.id_despesa = p.despesa_id
            WHERE p.status = 'PAGA' AND d.status = 'ATIVA' AND d.afeta_resultado = 1 AND DATE(p.pago_em) BETWEEN ? AND ?
        ", $p);

        $folhaPaga = $this->valor('SELECT COALESCE(SUM(valor_total), 0) FROM funcionario_fechamento_pagamento WHERE DATE(data_pagamento) BETWEEN ? AND ?', $p);

        // Parte fixa do fechamento: o total pago menos as comissões de OS que ele quitou (essas já
        // entram em "comissões", pela OS). Sem isto o salário fixo ficaria fora do resultado.
        $salarioFixoPago = $this->valor("
            SELECT COALESCE(SUM(f.valor_total - COALESCE((SELECT SUM(mob.valor_mao_obra) FROM ordem_servico_mao_obra mob WHERE mob.fechamento_pagamento_id = f.id), 0)), 0)
            FROM funcionario_fechamento_pagamento f WHERE DATE(f.data_pagamento) BETWEEN ? AND ?
        ", $p);

        $caixaDireto = $this->valor("
            SELECT COALESCE(SUM(m.valor), 0) FROM movimentacoes_caixa m
            WHERE m.tipo = 'SAIDA' AND m.origem = 'MANUAL' AND DATE(m.data_movimentacao) BETWEEN ? AND ?
              AND NOT EXISTS (SELECT 1 FROM pagamentos_pagar pg WHERE pg.movimentacao_caixa_id = m.id_movimentacao)
        ", $p);

        $despesasPagas = round($contasPagas + $folhaPaga + $caixaDireto, 2);

        /* ── lucro efetivo: só o que entrou das OS do período ── */
        $recebidoDasOsDoPeriodo = $this->valor("
            SELECT COALESCE(SUM(pa.valor - pa.taxa_valor), 0)
            FROM ordem_servico_parcelas pa JOIN ordens_servico os ON os.id_ordem_servico = pa.ordem_servico_id
            WHERE pa.pago = 1 AND os.status = 'CONCLUIDA' AND DATE(os.data_conclusao) BETWEEN ? AND ?
        ", $p);

        $resultadoEstimado = round($faturamento - $custoPecas - $comissoes - $despesasCompetencia - $salarioFixoPago - $taxas, 2);
        $lucroEfetivo = round($recebidoDasOsDoPeriodo - $custoPecas - $comissoes - $contasPagasOperacionais - $salarioFixoPago, 2);

        $receber = (new ContaReceber($this->conn))->totais();
        $pagar = (new ContaPagar($this->conn))->totais();

        return [
            'periodo'            => ['inicio' => $inicio, 'fim' => $fim],
            'faturamento_bruto'  => $faturamento,
            'os_entregues'       => $qtdOs,
            'acrescimos_planos'  => $acrescimos,
            'recebido_bruto'     => $recebidoBruto,
            'taxas'              => $taxas,
            'recebido_liquido'   => $recebidoLiquido,
            'parcelas_recebidas' => (int) $rec['qtd'],
            'a_receber'          => $receber,
            'a_pagar'            => $pagar,
            'despesas_pagas'     => [
                'total'           => $despesasPagas,
                'contas_a_pagar'  => $contasPagas,
                'folha_comissoes' => $folhaPaga,
                'caixa_direto'    => $caixaDireto,
            ],
            'outras_entradas'    => $outrasEntradas,
            'fluxo_realizado'    => round($recebidoLiquido + $outrasEntradas - $despesasPagas, 2),
            'custos'             => [
                'pecas'            => $custoPecas,
                'comissoes'        => $comissoes,
                'despesas_operacionais' => $despesasCompetencia,
                'salario_fixo'     => $salarioFixoPago,
            ],
            'resultado_operacional_estimado' => $resultadoEstimado,
            'lucro_efetivo'      => $lucroEfetivo,
            'recebido_das_os_do_periodo' => $recebidoDasOsDoPeriodo,
        ];
    }

    /**
     * Fluxo projetado: o que está para vencer, mês a mês (a partir de hoje), mais o que já venceu.
     * Entradas = parcelas a receber; saídas = parcelas a pagar. Saldo acumulado parte de zero —
     * é a VARIAÇÃO projetada, não o saldo do caixa.
     *
     * @return array<int,array{chave:string, rotulo:string, entradas:float, saidas:float, saldo:float, acumulado:float}>
     */
    public function fluxoProjetado(int $meses = 6): array
    {
        $meses = max(1, min(24, $meses));
        $hoje = date('Y-m-d');
        $limite = date('Y-m-t', strtotime($hoje . ' +' . ($meses - 1) . ' months'));

        $entradas = $this->conn->prepare("
            SELECT CASE WHEN p.vencimento < ? THEN 'ATRASADO' ELSE DATE_FORMAT(p.vencimento, '%Y-%m') END AS chave, SUM(p.valor)
            FROM ordem_servico_parcelas p JOIN ordens_servico os ON os.id_ordem_servico = p.ordem_servico_id
            WHERE p.pago = 0 AND os.status IN ('AGUARDANDO_RETIRADA','CONCLUIDA') AND p.vencimento <= ?
            GROUP BY chave
        ");
        $entradas->execute([$hoje, $limite]);
        $entradas = $entradas->fetchAll(PDO::FETCH_KEY_PAIR);

        $saidas = $this->conn->prepare("
            SELECT CASE WHEN p.vencimento < ? THEN 'ATRASADO' ELSE DATE_FORMAT(p.vencimento, '%Y-%m') END AS chave, SUM(p.valor)
            FROM contas_pagar_parcelas p JOIN despesas d ON d.id_despesa = p.despesa_id
            WHERE p.status = 'PENDENTE' AND d.status = 'ATIVA' AND p.vencimento <= ?
            GROUP BY chave
        ");
        $saidas->execute([$hoje, $limite]);
        $saidas = $saidas->fetchAll(PDO::FETCH_KEY_PAIR);

        $chaves = ['ATRASADO' => 'Atrasados'];
        for ($i = 0; $i < $meses; $i++) {
            $mes = date('Y-m', strtotime(date('Y-m-01') . " +{$i} months"));
            $chaves[$mes] = date('m/Y', strtotime($mes . '-01'));
        }

        $linhas = [];
        $acumulado = 0.0;

        foreach ($chaves as $chave => $rotulo) {
            $e = round((float) ($entradas[$chave] ?? 0), 2);
            $s = round((float) ($saidas[$chave] ?? 0), 2);

            if ($chave === 'ATRASADO' && $e == 0 && $s == 0) {
                continue;
            }

            $acumulado = round($acumulado + $e - $s, 2);
            $linhas[] = ['chave' => $chave, 'rotulo' => $rotulo, 'entradas' => $e, 'saidas' => $s, 'saldo' => round($e - $s, 2), 'acumulado' => $acumulado];
        }

        return $linhas;
    }
}
