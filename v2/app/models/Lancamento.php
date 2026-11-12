<?php

/**
 * Financeiro: contas a receber e a pagar.
 *
 *   lancamentos               = o que se deve (uma linha por parcela: valor, desconto, acréscimo, vencimento)
 *   movimentacoes_financeiras = o dinheiro que efetivamente entrou/saiu (pagamentos totais ou parciais, estornos)
 *
 * `valor_pago` e `status` do lançamento são SEMPRE recalculados a partir dos movimentos, na mesma
 * transação. Nada é apagado: pagamento errado é estornado (movimento contrário que aponta para o
 * original) e conta lançada errado é cancelada (só se não tiver pagamento).
 */
final class Lancamento extends Modelo
{
    public const FORMAS = [
        'DINHEIRO' => 'Dinheiro', 'PIX' => 'PIX', 'CARTAO_DEBITO' => 'Cartão de débito', 'CARTAO_CREDITO' => 'Cartão de crédito',
        'BOLETO' => 'Boleto', 'TRANSFERENCIA' => 'Transferência', 'OUTRO' => 'Outro',
    ];

    public const CATEGORIAS = [
        'RECEBER' => ['SERVICOS' => 'Serviços (OS)', 'OUTRAS_RECEITAS' => 'Outras receitas'],
        'PAGAR'   => ['PECAS' => 'Peças e mercadorias', 'COMISSAO' => 'Comissões', 'SALARIO' => 'Salários', 'ALUGUEL' => 'Aluguel',
                      'CONTAS_CONSUMO' => 'Água, luz, internet', 'IMPOSTOS' => 'Impostos e taxas', 'FERRAMENTAS' => 'Ferramentas e equipamentos',
                      'OUTRAS_DESPESAS' => 'Outras despesas'],
    ];

    public const SITUACOES = [
        'abertos'   => 'Em aberto', 'vencidos' => 'Vencidos', 'pagos' => 'Pagos', 'cancelados' => 'Cancelados', 'todos' => 'Todos',
    ];

    private Historico $hist;

    public function __construct(?PDO $db = null)
    {
        parent::__construct($db);
        $this->hist = new Historico($this->db);
    }

    public static function categoria(string $tipo, ?string $codigo): string
    {
        return self::CATEGORIAS[$tipo][$codigo] ?? (string) $codigo;
    }

    /* ── Consultas ───────────────────────────────────────────────────── */

    public function listar(array $f, Paginacao $p): array
    {
        $where  = 'l.oficina_id = :oficina AND l.tipo = :tipo';
        $params = ['tipo' => $f['tipo'] === 'PAGAR' ? 'PAGAR' : 'RECEBER'];

        switch ($f['situacao'] ?? 'abertos') {
            case 'vencidos':   $where .= " AND l.status IN ('ABERTO','PARCIAL') AND l.vencimento < CURDATE()"; break;
            case 'pagos':      $where .= " AND l.status = 'PAGO'"; break;
            case 'cancelados': $where .= " AND l.status = 'CANCELADO'"; break;
            case 'todos':      break;
            default:           $where .= " AND l.status IN ('ABERTO','PARCIAL')";
        }
        if (($f['busca'] ?? '') !== '') {
            $where .= ' AND (l.descricao LIKE :b1 OR c.nome LIKE :b2 OR fo.nome LIKE :b3 OR u.nome LIKE :b4 OR l.documento LIKE :b5)';
            $b = '%' . $f['busca'] . '%';
            $params += ['b1' => $b, 'b2' => $b, 'b3' => $b, 'b4' => $b, 'b5' => $b];
        }
        if (isset(self::CATEGORIAS[$params['tipo']][$f['categoria'] ?? ''])) {
            $where .= ' AND l.categoria = :categoria';
            $params['categoria'] = $f['categoria'];
        }
        if (!empty($f['de']) && strtotime($f['de'])) {
            $where .= ' AND l.vencimento >= :de';
            $params['de'] = date('Y-m-d', strtotime($f['de']));
        }
        if (!empty($f['ate']) && strtotime($f['ate'])) {
            $where .= ' AND l.vencimento <= :ate';
            $params['ate'] = date('Y-m-d', strtotime($f['ate']));
        }

        $from = 'FROM lancamentos l
                 LEFT JOIN clientes c ON c.oficina_id = l.oficina_id AND c.id = l.cliente_id
                 LEFT JOIN fornecedores fo ON fo.oficina_id = l.oficina_id AND fo.id = l.fornecedor_id
                 LEFT JOIN usuarios u ON u.id = l.funcionario_id
                 LEFT JOIN ordens_servico os ON os.oficina_id = l.oficina_id AND os.id = l.ordem_servico_id';
        $tot = $this->linha("SELECT COUNT(*) AS n, COALESCE(SUM(l.valor - l.desconto + l.acrescimo - l.valor_pago), 0) AS saldo {$from} WHERE {$where}", $params);
        $p->total = (int) $tot['n'];

        return [
            'linhas' => $this->lista(
                "SELECT l.*, (l.valor - l.desconto + l.acrescimo) AS devido, c.nome AS cliente, fo.nome AS fornecedor, u.nome AS funcionario, os.numero AS os_numero
                 {$from} WHERE {$where} ORDER BY l.status IN ('PAGO','CANCELADO'), l.vencimento, l.id" . $p->sql(), $params),
            'saldo'  => (float) $tot['saldo'],
        ];
    }

    /**
     * Números do topo de Contas a receber / a pagar (não dependem dos filtros da lista):
     * em aberto, em atraso, vencendo nos próximos 7 dias e o que foi recebido/pago no mês (pagamentos − estornos).
     */
    public function indicadores(string $tipo): array
    {
        $tipo = $tipo === 'PAGAR' ? 'PAGAR' : 'RECEBER';
        $saldo = '(valor - desconto + acrescimo - valor_pago)';
        $aberta = "status IN ('ABERTO','PARCIAL')";
        $l = $this->linha(
            "SELECT COALESCE(SUM(CASE WHEN {$aberta} THEN {$saldo} END), 0) AS aberto, SUM({$aberta}) AS n_aberto,
                    COALESCE(SUM(CASE WHEN {$aberta} AND vencimento < CURDATE() THEN {$saldo} END), 0) AS atrasado,
                    SUM({$aberta} AND vencimento < CURDATE()) AS n_atrasado,
                    COALESCE(SUM(CASE WHEN {$aberta} AND vencimento BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY THEN {$saldo} END), 0) AS proximos,
                    SUM({$aberta} AND vencimento BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY) AS n_proximos,
                    SUM(status = 'PAGO') AS pagos, SUM(status = 'CANCELADO') AS cancelados, COUNT(*) AS todos
             FROM lancamentos WHERE oficina_id = :oficina AND tipo = :tipo",
            ['tipo' => $tipo]
        );
        $m = $this->linha(
            "SELECT COALESCE(SUM(CASE WHEN m.natureza = 'BAIXA' THEN m.valor WHEN m.natureza = 'ESTORNO' THEN -m.valor END), 0) AS realizado,
                    SUM(m.natureza = 'BAIXA') AS n_realizado
             FROM movimentacoes_financeiras m JOIN lancamentos l ON l.oficina_id = m.oficina_id AND l.id = m.lancamento_id
             WHERE m.oficina_id = :oficina AND l.tipo = :tipo AND m.data_movimento >= DATE_FORMAT(CURDATE(), '%Y-%m-01')",
            ['tipo' => $tipo]
        );

        return array_map(fn($v) => $v === null ? 0 : $v + 0, $l + $m);
    }

    public function buscar(int $id): ?array
    {
        return $this->linha(
            'SELECT l.*, (l.valor - l.desconto + l.acrescimo) AS devido, c.nome AS cliente, fo.nome AS fornecedor, u.nome AS funcionario,
                    os.numero AS os_numero, cr.nome AS criado_por_nome
             FROM lancamentos l
             LEFT JOIN clientes c ON c.oficina_id = l.oficina_id AND c.id = l.cliente_id
             LEFT JOIN fornecedores fo ON fo.oficina_id = l.oficina_id AND fo.id = l.fornecedor_id
             LEFT JOIN usuarios u ON u.id = l.funcionario_id
             LEFT JOIN usuarios cr ON cr.id = l.criado_por
             LEFT JOIN ordens_servico os ON os.oficina_id = l.oficina_id AND os.id = l.ordem_servico_id
             WHERE l.id = :id AND l.oficina_id = :oficina',
            ['id' => $id]
        );
    }

    /** Todas as parcelas do mesmo parcelamento. */
    public function parcelas(array $l): array
    {
        $grupo = (int) ($l['grupo_id'] ?: $l['id']);

        return $this->lista('SELECT *, (valor - desconto + acrescimo) AS devido FROM lancamentos
                             WHERE oficina_id = :oficina AND (id = :g1 OR grupo_id = :g2) ORDER BY parcela_numero', ['g1' => $grupo, 'g2' => $grupo]);
    }

    public function movimentos(int $lancamentoId): array
    {
        return $this->lista(
            'SELECT m.*, u.nome AS usuario,
                    (SELECT COUNT(*) FROM movimentacoes_financeiras e WHERE e.oficina_id = m.oficina_id AND e.estorno_de_id = m.id) AS estornado
             FROM movimentacoes_financeiras m LEFT JOIN usuarios u ON u.id = m.usuario_id
             WHERE m.oficina_id = :oficina AND m.lancamento_id = :id ORDER BY m.data_movimento, m.id',
            ['id' => $lancamentoId]
        );
    }

    /** Contas a receber de uma OS e quanto ainda falta lançar. */
    public function daOs(int $osId): array
    {
        return $this->lista("SELECT *, (valor - desconto + acrescimo) AS devido FROM lancamentos
                             WHERE oficina_id = :oficina AND ordem_servico_id = :os AND tipo = 'RECEBER' ORDER BY vencimento, parcela_numero",
            ['os' => $osId]);
    }

    /* ── Lançar ──────────────────────────────────────────────────────── */

    /**
     * Lança uma conta (à vista ou parcelada). O valor é dividido em parcelas iguais; os centavos que
     * sobram vão na última. Vencimentos mensais a partir do primeiro.
     *
     * @return int id da primeira parcela
     */
    public function criar(array $d): int
    {
        $tipo = ($d['tipo'] ?? '') === 'PAGAR' ? 'PAGAR' : 'RECEBER';
        $valor = round(Produto::valorDecimal((string) ($d['valor'] ?? '0')), 2);
        $n = max(1, min(24, (int) ($d['parcelas'] ?? 1)));
        $venc = !empty($d['vencimento']) && strtotime($d['vencimento']) ? date('Y-m-d', strtotime($d['vencimento'])) : date('Y-m-d');
        $descricao = trim((string) ($d['descricao'] ?? ''));

        if ($valor <= 0) {
            throw new DomainException('Informe um valor maior que zero.');
        }
        if ($descricao === '') {
            throw new DomainException('Descreva a conta.');
        }
        if (!isset(self::CATEGORIAS[$tipo][$d['categoria'] ?? ''])) {
            throw new DomainException('Escolha a categoria.');
        }

        $base = floor($valor / $n * 100) / 100;

        return Transacao::executar($this->db, function () use ($d, $tipo, $valor, $n, $venc, $descricao, $base) {
            $primeiro = null;
            for ($i = 1; $i <= $n; $i++) {
                $parcela = $i < $n ? $base : round($valor - $base * ($n - 1), 2);
                $id = $this->inserir('lancamentos', [
                    'tipo' => $tipo, 'categoria' => $d['categoria'],
                    'descricao' => mb_substr($descricao, 0, 200), 'grupo_id' => $primeiro,
                    'parcela_numero' => $i, 'parcela_total' => $n, 'valor' => $parcela,
                    'competencia' => !empty($d['competencia']) && strtotime($d['competencia']) ? date('Y-m-d', strtotime($d['competencia'])) : date('Y-m-d'),
                    'vencimento' => date('Y-m-d', strtotime($venc . ' +' . ($i - 1) . ' month')),
                    'forma_prevista' => isset(self::FORMAS[$d['forma_prevista'] ?? '']) ? $d['forma_prevista'] : null,
                    'ordem_servico_id' => !empty($d['ordem_servico_id']) ? (int) $d['ordem_servico_id'] : null,
                    'cliente_id' => !empty($d['cliente_id']) ? (int) $d['cliente_id'] : null,
                    'fornecedor_id' => !empty($d['fornecedor_id']) ? (int) $d['fornecedor_id'] : null,
                    'funcionario_id' => !empty($d['funcionario_id']) ? (int) $d['funcionario_id'] : null,
                    'documento' => mb_substr(trim((string) ($d['documento'] ?? '')), 0, 60) ?: null,
                    'observacao' => mb_substr(trim((string) ($d['observacao'] ?? '')), 0, 255) ?: null,
                    'criado_por' => Contexto::usuarioId(),
                ]);
                $primeiro ??= $id;
            }
            $this->hist->registrar(['lancamento_id' => $primeiro], 'CRIACAO', null, 'ABERTO', null, ['valor' => $valor, 'parcelas' => $n]);

            return $primeiro;
        });
    }

    /** Cobrança de uma OS: lança o que falta receber (à vista ou parcelado). */
    public function cobrarOs(int $osId, array $d): int
    {
        return Transacao::executar($this->db, function () use ($osId, $d) {
            $os = $this->linha('SELECT id, numero, cliente_id, status, valor_total FROM ordens_servico WHERE id = :id AND oficina_id = :oficina FOR UPDATE', ['id' => $osId])
                ?? throw new ErroHttp('registro-inexistente');
            if (!in_array($os['status'], ['APROVADA', 'EM_EXECUCAO', 'AGUARDANDO_PECA', 'PRONTA', 'ENTREGUE'], true)) {
                throw new DomainException('Só é possível cobrar uma OS aprovada.');
            }
            $falta = $this->faltaLancar($os);
            if ($falta <= 0) {
                throw new DomainException('O valor desta OS já está todo lançado.');
            }

            return $this->criar([
                'tipo' => 'RECEBER', 'categoria' => 'SERVICOS', 'descricao' => os_numero($os['numero']),
                'valor' => number_format($falta, 2, '.', ''), 'parcelas' => $d['parcelas'] ?? 1, 'vencimento' => $d['vencimento'] ?? null,
                'forma_prevista' => $d['forma'] ?? null, 'ordem_servico_id' => $os['id'], 'cliente_id' => $os['cliente_id'],
            ]);
        });
    }

    /** Quanto do total da OS ainda não virou conta a receber (contas canceladas não contam). */
    public function faltaLancar(array $os): float
    {
        $lancado = (float) $this->valor("SELECT COALESCE(SUM(valor - desconto + acrescimo), 0) FROM lancamentos
                                         WHERE oficina_id = :oficina AND ordem_servico_id = :os AND tipo = 'RECEBER' AND status <> 'CANCELADO'",
            ['os' => $os['id']]);

        return round((float) $os['valor_total'] - $lancado, 2);
    }

    public function editar(int $id, array $d): void
    {
        Transacao::executar($this->db, function () use ($id, $d) {
            $l = $this->exigir($id, true);
            if ($l['status'] === 'CANCELADO' || $l['status'] === 'PAGO') {
                throw new DomainException('Conta paga ou cancelada não pode ser alterada.');
            }
            $novos = [
                'descricao'  => mb_substr(trim((string) ($d['descricao'] ?? '')) ?: $l['descricao'], 0, 200),
                'vencimento' => !empty($d['vencimento']) && strtotime($d['vencimento']) ? date('Y-m-d', strtotime($d['vencimento'])) : $l['vencimento'],
                'documento'  => mb_substr(trim((string) ($d['documento'] ?? '')), 0, 60) ?: null,
                'observacao' => mb_substr(trim((string) ($d['observacao'] ?? '')), 0, 255) ?: null,
            ];
            if (isset(self::CATEGORIAS[$l['tipo']][$d['categoria'] ?? ''])) {
                $novos['categoria'] = $d['categoria'];
            }
            if ($l['status'] === 'ABERTO' && ($d['valor'] ?? '') !== '' && !$l['ordem_servico_id']) {
                $v = round(Produto::valorDecimal((string) $d['valor']), 2);
                if ($v <= 0) {
                    throw new DomainException('Informe um valor maior que zero.');
                }
                $novos['valor'] = $v;
                $novos['desconto'] = min((float) $l['desconto'], $v);
            }
            $this->atualizar('lancamentos', $id, $novos);
            $this->hist->registrar(['lancamento_id' => $id], 'ALTERACAO', null, null, null,
                ['antes' => array_intersect_key($l, $novos), 'depois' => $novos]);
        });
    }

    /* ── Pagar / receber ─────────────────────────────────────────────── */

    /**
     * Registra um pagamento (total ou parcial). Juros/multa e desconto ajustam o devido antes.
     * Dinheiro exige caixa aberto; as outras formas entram no caixa aberto, se houver.
     */
    public function baixar(int $id, array $d): void
    {
        $valor    = round(Produto::valorDecimal((string) ($d['valor'] ?? '0')), 2);
        $juros    = round(Produto::valorDecimal((string) ($d['acrescimo'] ?? '0')), 2);
        $desconto = round(Produto::valorDecimal((string) ($d['desconto'] ?? '0')), 2);
        $taxa     = round(Produto::valorDecimal((string) ($d['taxa'] ?? '0')), 2);
        $forma    = $d['forma'] ?? '';

        if (!isset(self::FORMAS[$forma])) {
            throw new DomainException('Escolha a forma de pagamento.');
        }
        if ($valor <= 0) {
            throw new DomainException('Informe o valor pago.');
        }
        if ($juros < 0 || $desconto < 0 || $taxa < 0 || $taxa > $valor) {
            throw new DomainException('Juros, desconto e taxa precisam ser valores válidos.');
        }

        Transacao::executar($this->db, function () use ($id, $d, $valor, $juros, $desconto, $taxa, $forma) {
            $l = $this->exigir($id, true);
            if (!in_array($l['status'], ['ABERTO', 'PARCIAL'], true)) {
                throw new DomainException('Esta conta não está em aberto.');
            }

            $novoAcrescimo = (float) $l['acrescimo'] + $juros;
            $novoDesconto  = (float) $l['desconto'] + $desconto;
            $devido = round((float) $l['valor'] - $novoDesconto + $novoAcrescimo, 2);
            $saldo  = round($devido - (float) $l['valor_pago'], 2);
            if ($novoDesconto > (float) $l['valor'] + $novoAcrescimo) {
                throw new DomainException('O desconto é maior que a conta.');
            }
            if ($valor > $saldo + 0.004) {
                throw new DomainException('O valor pago (' . dinheiro($valor) . ') é maior que o saldo da conta (' . dinheiro($saldo) . ').');
            }

            $caixa = (new Caixa($this->db))->aberto();
            if ($forma === 'DINHEIRO' && !$caixa) {
                throw new DomainException('Pagamento em dinheiro precisa de caixa aberto. Abra o caixa e tente de novo.');
            }

            $this->atualizar('lancamentos', $id, ['acrescimo' => $novoAcrescimo, 'desconto' => $novoDesconto]);
            $this->inserir('movimentacoes_financeiras', [
                'natureza' => 'BAIXA', 'direcao' => $l['tipo'] === 'RECEBER' ? 'ENTRADA' : 'SAIDA', 'valor' => $valor, 'taxa' => $taxa,
                'forma_pagamento' => $forma, 'lancamento_id' => $id, 'caixa_id' => $caixa['id'] ?? null,
                'data_movimento' => $forma === 'DINHEIRO' || empty($d['data']) || !strtotime($d['data']) ? date('Y-m-d H:i:s') : date('Y-m-d 12:00:00', strtotime($d['data'])),
                'descricao' => mb_substr(trim((string) ($d['observacao'] ?? '')), 0, 200) ?: null,
                'usuario_id' => Contexto::usuarioId(), 'chave_idempotencia' => !empty($d['chave']) ? mb_substr((string) $d['chave'], 0, 64) : null,
            ]);
            $this->recalcular($id);
            $this->hist->registrar(['lancamento_id' => $id], 'PAGAMENTO', $l['status'], $this->exigir($id)['status'], null,
                ['valor' => $valor, 'forma' => $forma, 'juros' => $juros, 'desconto' => $desconto]);
        });
    }

    /** Recebimento feito pela tela da OS (equipe do balcão): só contas a receber DAQUELA OS. */
    public function receberDaOs(int $osId, int $lancamentoId, array $d): void
    {
        $l = $this->exigir($lancamentoId);
        if ((int) $l['ordem_servico_id'] !== $osId || $l['tipo'] !== 'RECEBER') {
            throw new ErroHttp('registro-inexistente');
        }
        $this->baixar($lancamentoId, $d);
    }

    /** Estorna um pagamento lançado errado. O original continua registrado. */
    public function estornar(int $movId, string $motivo): int
    {
        if (trim($motivo) === '') {
            throw new DomainException('Informe o motivo do estorno.');
        }

        return Transacao::executar($this->db, function () use ($movId, $motivo) {
            $m = $this->linha('SELECT * FROM movimentacoes_financeiras WHERE id = :id AND oficina_id = :oficina FOR UPDATE', ['id' => $movId])
                ?? throw new ErroHttp('registro-inexistente');
            if ($m['natureza'] !== 'BAIXA') {
                throw new DomainException('Só pagamentos podem ser estornados.');
            }
            if ($this->valor('SELECT COUNT(*) FROM movimentacoes_financeiras WHERE oficina_id = :oficina AND estorno_de_id = :id', ['id' => $movId])) {
                throw new DomainException('Este pagamento já foi estornado.');
            }
            $l = $this->exigir((int) $m['lancamento_id'], true);

            $caixa = (new Caixa($this->db))->aberto();
            if ($m['forma_pagamento'] === 'DINHEIRO' && !$caixa) {
                throw new DomainException('O estorno em dinheiro mexe na gaveta: abra o caixa antes.');
            }

            $this->inserir('movimentacoes_financeiras', [
                'natureza' => 'ESTORNO', 'direcao' => $m['direcao'] === 'ENTRADA' ? 'SAIDA' : 'ENTRADA', 'valor' => $m['valor'],
                'forma_pagamento' => $m['forma_pagamento'], 'lancamento_id' => $l['id'], 'estorno_de_id' => $movId,
                'caixa_id' => $caixa['id'] ?? null, 'descricao' => mb_substr(trim($motivo), 0, 200), 'usuario_id' => Contexto::usuarioId(),
            ]);
            $this->recalcular((int) $l['id']);
            $this->hist->registrar(['lancamento_id' => (int) $l['id']], 'ESTORNO', $l['status'], $this->exigir((int) $l['id'])['status'], $motivo, ['valor' => $m['valor']]);

            return (int) $l['id'];
        });
    }

    /** Cancela uma conta lançada errado (sem pagamento). Comissão cancelada volta a ficar pendente. */
    public function cancelar(int $id, string $motivo): void
    {
        if (trim($motivo) === '') {
            throw new DomainException('Informe o motivo do cancelamento.');
        }

        Transacao::executar($this->db, function () use ($id, $motivo) {
            $l = $this->exigir($id, true);
            if ($l['status'] !== 'ABERTO' || (float) $l['valor_pago'] > 0) {
                throw new DomainException('Só conta em aberto e sem pagamento pode ser cancelada. Estorne os pagamentos antes.');
            }
            $this->gravar('UPDATE ordem_servico_itens SET comissao_lancamento_id = NULL WHERE oficina_id = :oficina AND comissao_lancamento_id = :id', ['id' => $id]);
            $this->atualizar('lancamentos', $id, ['status' => 'CANCELADO', 'cancelado_em' => date('Y-m-d H:i:s'), 'cancelado_por' => Contexto::usuarioId(), 'motivo_cancelamento' => mb_substr(trim($motivo), 0, 255)]);
            $this->hist->registrar(['lancamento_id' => $id], 'CANCELAMENTO', $l['status'], 'CANCELADO', $motivo);
        });
    }

    /* ── Comissões ───────────────────────────────────────────────────── */

    /** Comissões a pagar por técnico: itens de OS concluídas/entregues ainda sem pagamento gerado. */
    public function comissoesPendentes(): array
    {
        return $this->lista(
            "SELECT u.id, u.nome, COUNT(DISTINCT i.ordem_servico_id) AS ordens, SUM(i.comissao_valor) AS total, MIN(os.concluido_em) AS desde
             FROM ordem_servico_itens i
             JOIN ordens_servico os ON os.oficina_id = i.oficina_id AND os.id = i.ordem_servico_id
             JOIN usuarios u ON u.id = i.tecnico_id
             WHERE i.oficina_id = :oficina AND i.comissao_valor > 0 AND i.comissao_lancamento_id IS NULL
               AND os.status IN ('PRONTA','ENTREGUE') AND i.estoque_status <> 'DEVOLVIDO'
             GROUP BY u.id, u.nome ORDER BY u.nome"
        );
    }

    public function itensComissao(int $tecnicoId): array
    {
        return $this->lista(
            "SELECT i.id, i.tipo, i.descricao, i.valor_total, i.comissao_percentual, i.comissao_valor, os.id AS os_id, os.numero, os.concluido_em
             FROM ordem_servico_itens i JOIN ordens_servico os ON os.oficina_id = i.oficina_id AND os.id = i.ordem_servico_id
             WHERE i.oficina_id = :oficina AND i.tecnico_id = :t AND i.comissao_valor > 0 AND i.comissao_lancamento_id IS NULL
               AND os.status IN ('PRONTA','ENTREGUE') AND i.estoque_status <> 'DEVOLVIDO' ORDER BY os.concluido_em, os.numero",
            ['t' => $tecnicoId]
        );
    }

    /**
     * Comissões por pessoa da equipe (tela Comissões): a regra atual do cadastro, o que está pendente de
     * lançamento (mesmos critérios de comissoesPendentes) e o saldo das contas de comissão ainda não pagas.
     * Entra quem tem regra de comissão, comissão pendente ou conta de comissão em aberto.
     */
    public function equipeComissoes(): array
    {
        return $this->lista(
            "SELECT u.id, u.nome, u.ativo, u.comissao_tipo, u.comissao_percentual,
                    COALESCE(p.ordens, 0) AS pend_os, p.desde AS pend_desde, COALESCE(p.base, 0) AS pend_base, COALESCE(p.total, 0) AS pend_total,
                    COALESCE(a.saldo, 0) AS aberto, COALESCE(a.n, 0) AS n_aberto
             FROM usuarios u
             LEFT JOIN (SELECT i.tecnico_id, COUNT(DISTINCT i.ordem_servico_id) AS ordens, MIN(os.concluido_em) AS desde,
                               SUM(i.valor_total) AS base, SUM(i.comissao_valor) AS total
                        FROM ordem_servico_itens i
                        JOIN ordens_servico os ON os.oficina_id = i.oficina_id AND os.id = i.ordem_servico_id
                        WHERE i.oficina_id = :o1 AND i.comissao_valor > 0 AND i.comissao_lancamento_id IS NULL
                          AND os.status IN ('PRONTA','ENTREGUE') AND i.estoque_status <> 'DEVOLVIDO'
                        GROUP BY i.tecnico_id) p ON p.tecnico_id = u.id
             LEFT JOIN (SELECT funcionario_id, SUM(valor - desconto + acrescimo - valor_pago) AS saldo, COUNT(*) AS n
                        FROM lancamentos
                        WHERE oficina_id = :o2 AND tipo = 'PAGAR' AND categoria = 'COMISSAO' AND status IN ('ABERTO','PARCIAL')
                        GROUP BY funcionario_id) a ON a.funcionario_id = u.id
             WHERE u.oficina_id = :oficina AND (u.comissao_tipo <> 'NENHUMA' OR p.total IS NOT NULL OR a.n IS NOT NULL)
             ORDER BY COALESCE(p.total, 0) DESC, u.nome",
            ['o1' => $this->oficinaId(), 'o2' => $this->oficinaId()]
        );
    }

    /** Números do topo da tela Comissões. Pendente e em aberto são o retrato de hoje; lançado e pago, do período. */
    public function resumoComissoes(string $de, string $ate, int $tecnico = 0): array
    {
        $fim = (new DateTimeImmutable($ate))->modify('+1 day')->format('Y-m-d');
        $porTec = $tecnico ? ' AND l.funcionario_id = :tec' : '';
        $pTec = $tecnico ? ['tec' => $tecnico] : [];
        $pend = $this->linha(
            "SELECT COALESCE(SUM(i.comissao_valor), 0) AS total, COUNT(DISTINCT i.ordem_servico_id) AS ordens, COUNT(DISTINCT i.tecnico_id) AS pessoas
             FROM ordem_servico_itens i JOIN ordens_servico os ON os.oficina_id = i.oficina_id AND os.id = i.ordem_servico_id
             WHERE i.oficina_id = :oficina AND i.comissao_valor > 0 AND i.comissao_lancamento_id IS NULL
               AND os.status IN ('PRONTA','ENTREGUE') AND i.estoque_status <> 'DEVOLVIDO'" . ($tecnico ? ' AND i.tecnico_id = :tec' : ''),
            $pTec
        );
        $contas = $this->linha(
            "SELECT COALESCE(SUM(CASE WHEN l.status IN ('ABERTO','PARCIAL') THEN l.valor - l.desconto + l.acrescimo - l.valor_pago END), 0) AS aberto,
                    SUM(l.status IN ('ABERTO','PARCIAL')) AS n_aberto,
                    COALESCE(SUM(CASE WHEN l.status <> 'CANCELADO' AND l.criado_em >= :de AND l.criado_em < :fim THEN l.valor - l.desconto + l.acrescimo END), 0) AS lancado,
                    SUM(l.status <> 'CANCELADO' AND l.criado_em >= :de2 AND l.criado_em < :fim2) AS n_lancado
             FROM lancamentos l WHERE l.oficina_id = :oficina AND l.tipo = 'PAGAR' AND l.categoria = 'COMISSAO'{$porTec}",
            ['de' => $de, 'fim' => $fim, 'de2' => $de, 'fim2' => $fim] + $pTec
        );
        $pago = $this->linha(
            "SELECT COALESCE(SUM(CASE WHEN m.natureza = 'BAIXA' THEN m.valor WHEN m.natureza = 'ESTORNO' THEN -m.valor END), 0) AS pago,
                    SUM(m.natureza = 'BAIXA') AS n_pago
             FROM movimentacoes_financeiras m JOIN lancamentos l ON l.oficina_id = m.oficina_id AND l.id = m.lancamento_id
             WHERE m.oficina_id = :oficina AND l.tipo = 'PAGAR' AND l.categoria = 'COMISSAO'
               AND m.data_movimento >= :de AND m.data_movimento < :fim{$porTec}",
            ['de' => $de, 'fim' => $fim] + $pTec
        );

        return [
            'pendente' => (float) $pend['total'], 'pend_os' => (int) $pend['ordens'], 'pend_pessoas' => (int) $pend['pessoas'],
            'aberto' => (float) $contas['aberto'], 'n_aberto' => (int) $contas['n_aberto'],
            'lancado' => (float) $contas['lancado'], 'n_lancado' => (int) $contas['n_lancado'],
            'pago' => (float) $pago['pago'], 'n_pago' => (int) $pago['n_pago'],
        ];
    }

    /** Contas de comissão já lançadas (histórico de pagamentos), com as OS que cada uma cobre. */
    public function historicoComissoes(array $f, Paginacao $p): array
    {
        $where = "l.oficina_id = :oficina AND l.tipo = 'PAGAR' AND l.categoria = 'COMISSAO' AND l.criado_em >= :de AND l.criado_em < :fim";
        $params = ['de' => $f['de'], 'fim' => (new DateTimeImmutable($f['ate']))->modify('+1 day')->format('Y-m-d')];
        if ($f['tecnico']) {
            $where .= ' AND l.funcionario_id = :tec';
            $params['tec'] = $f['tecnico'];
        }
        $where .= match ($f['situacao']) {
            'abertos' => " AND l.status IN ('ABERTO','PARCIAL')",
            'pagos' => " AND l.status = 'PAGO'",
            'cancelados' => " AND l.status = 'CANCELADO'",
            default => '',
        };
        $p->total = (int) $this->valor("SELECT COUNT(*) FROM lancamentos l WHERE {$where}", $params);

        return $this->lista(
            "SELECT l.*, (l.valor - l.desconto + l.acrescimo) AS devido, u.nome AS funcionario,
                    (SELECT GROUP_CONCAT(DISTINCT os.numero ORDER BY os.numero SEPARATOR ',')
                       FROM ordem_servico_itens i JOIN ordens_servico os ON os.oficina_id = i.oficina_id AND os.id = i.ordem_servico_id
                      WHERE i.oficina_id = l.oficina_id AND i.comissao_lancamento_id = l.id) AS ordens,
                    (SELECT MAX(m.data_movimento) FROM movimentacoes_financeiras m
                      WHERE m.oficina_id = l.oficina_id AND m.lancamento_id = l.id AND m.natureza = 'BAIXA') AS pago_em
             FROM lancamentos l LEFT JOIN usuarios u ON u.id = l.funcionario_id
             WHERE {$where} ORDER BY l.criado_em DESC, l.id DESC" . $p->sql(),
            $params
        );
    }

    /** Gera a conta a pagar das comissões pendentes de um técnico e marca os itens. */
    public function gerarComissao(int $tecnicoId, string $vencimento): int
    {
        return Transacao::executar($this->db, function () use ($tecnicoId, $vencimento) {
            $itens = $this->itensComissao($tecnicoId);
            if (!$itens) {
                throw new DomainException('Não há comissão pendente para este técnico.');
            }
            $nome  = $this->valor('SELECT nome FROM usuarios WHERE id = :id AND oficina_id = :oficina', ['id' => $tecnicoId]);
            $total = round(array_sum(array_column($itens, 'comissao_valor')), 2);
            $ordens = array_unique(array_map(fn($i) => os_numero($i['numero']), $itens));

            $id = $this->criar([
                'tipo' => 'PAGAR', 'categoria' => 'COMISSAO', 'descricao' => 'Comissão — ' . $nome,
                'valor' => number_format($total, 2, '.', ''), 'vencimento' => $vencimento, 'funcionario_id' => $tecnicoId,
                'observacao' => mb_substr('OS: ' . implode(', ', $ordens), 0, 255),
            ]);
            foreach ($itens as $i) {
                $this->atualizar('ordem_servico_itens', (int) $i['id'], ['comissao_lancamento_id' => $id]);
            }

            return $id;
        });
    }

    /* ── Apoio ───────────────────────────────────────────────────────── */

    /** valor_pago = pagamentos − estornos; status coerente (o banco também confere). */
    private function recalcular(int $id): void
    {
        $this->gravar(
            "UPDATE lancamentos l
             SET l.valor_pago = (SELECT COALESCE(SUM(IF(m.natureza = 'BAIXA', m.valor, -m.valor)), 0) FROM movimentacoes_financeiras m
                                 WHERE m.oficina_id = l.oficina_id AND m.lancamento_id = l.id),
                 l.status = CASE
                     WHEN l.valor_pago = 0 THEN 'ABERTO'
                     WHEN l.valor_pago >= l.valor - l.desconto + l.acrescimo THEN 'PAGO'
                     ELSE 'PARCIAL' END
             WHERE l.id = :id AND l.oficina_id = :oficina",
            ['id' => $id]
        );
    }

    private function exigir(int $id, bool $travar = false): array
    {
        return $this->linha('SELECT * FROM lancamentos WHERE id = :id AND oficina_id = :oficina' . ($travar ? ' FOR UPDATE' : ''), ['id' => $id])
            ?? throw new ErroHttp('registro-inexistente', ['voltar' => './financeiro']);
    }
}
