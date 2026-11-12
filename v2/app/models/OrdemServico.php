<?php

/**
 * Ordem de serviço com o orçamento integrado.
 *
 *   ORCAMENTO ──aprovar──▶ APROVADA ──iniciar──▶ EM_EXECUCAO ⇄ AGUARDANDO_PECA ──concluir──▶ PRONTA ──entregar──▶ ENTREGUE
 *       │  ▲                                          ▲                                          │
 *    recusar│ reabrir                                 └──────────────── reabrir execução ────────┘
 *       ▼  │
 *   RECUSADA                     cancelar: de qualquer situação antes de ENTREGUE (peças baixadas voltam ao estoque)
 *
 * Valores: cada item guarda descrição, quantidade, preço e desconto da época (o catálogo pode mudar
 * depois sem afetar a OS). Na aprovação, o total fica congelado em `valor_aprovado` e uma cópia
 * completa dos itens vai para o histórico. Depois da aprovação, item novo entra como ADICIONAL e
 * item removido fica registrado no histórico (peça baixada volta ao estoque e o item fica DEVOLVIDO).
 */
final class OrdemServico extends Modelo
{
    public const TRANSICOES = [
        'aprovar'   => [['ORCAMENTO'], 'APROVADA'],
        'recusar'   => [['ORCAMENTO'], 'RECUSADA'],
        'reabrir'   => [['RECUSADA'], 'ORCAMENTO'],
        'iniciar'   => [['APROVADA'], 'EM_EXECUCAO'],
        'concluir'  => [['EM_EXECUCAO'], 'PRONTA'],
        'retomar'   => [['PRONTA'], 'EM_EXECUCAO'],
        'entregar'  => [['PRONTA'], 'ENTREGUE'],
        'cancelar'  => [['ORCAMENTO', 'APROVADA', 'EM_EXECUCAO', 'AGUARDANDO_PECA', 'PRONTA'], 'CANCELADA'],
    ];

    /** Situações em que dá para incluir/remover itens. */
    public const EDITAVEL = ['ORCAMENTO', 'APROVADA', 'EM_EXECUCAO', 'AGUARDANDO_PECA'];

    public const FILTROS = [
        'abertas'    => ['Em aberto', ['ORCAMENTO', 'APROVADA', 'EM_EXECUCAO', 'AGUARDANDO_PECA', 'PRONTA']],
        'orcamento'  => ['Orçamentos', ['ORCAMENTO']],
        'execucao'   => ['Em andamento', ['APROVADA', 'EM_EXECUCAO', 'AGUARDANDO_PECA']],
        'prontas'    => ['Prontas', ['PRONTA']],
        'entregues'  => ['Entregues', ['ENTREGUE']],
        'encerradas' => ['Recusadas e canceladas', ['RECUSADA', 'CANCELADA']],
        'todas'      => ['Todas', []],
    ];

    /** Colunas do Kanban: as situações em aberto, na ordem do fluxo. */
    public const KANBAN = ['ORCAMENTO', 'APROVADA', 'EM_EXECUCAO', 'AGUARDANDO_PECA', 'PRONTA'];
    public const KANBAN_LIMITE = 200;

    private Historico $hist;

    public function __construct(?PDO $db = null)
    {
        parent::__construct($db);
        $this->hist = new Historico($this->db);
    }

    /* ── Consultas ───────────────────────────────────────────────────── */

    public function listar(array $f, Paginacao $p): array
    {
        [$where, $params, $from] = $this->filtro($f);
        $p->total = (int) $this->valor("SELECT COUNT(*) {$from} WHERE {$where}", $params);

        return $this->lista(
            self::CAMPOS_LISTA . " {$from} WHERE {$where}
             ORDER BY FIELD(os.status, 'PRONTA', 'AGUARDANDO_PECA', 'EM_EXECUCAO', 'APROVADA', 'ORCAMENTO') DESC, os.numero DESC" . $p->sql(),
            $params
        );
    }

    /**
     * Quantas OS há em cada aba da lista. Respeita a busca e o técnico, não a aba escolhida.
     * Uma consulta só, agrupada por situação; cada aba soma as situações que cobre.
     */
    public function contagens(array $f): array
    {
        [$where, $params, $from] = $this->filtro($f, false);
        $porStatus = [];
        foreach ($this->lista("SELECT os.status, COUNT(*) AS n {$from} WHERE {$where} GROUP BY os.status", $params) as $l) {
            $porStatus[$l['status']] = (int) $l['n'];
        }

        $r = [];
        foreach (self::FILTROS as $k => [, $status]) {
            $r[$k] = $status ? array_sum(array_intersect_key($porStatus, array_flip($status))) : array_sum($porStatus);
        }

        return $r;
    }

    /**
     * OS em aberto agrupadas por situação, para o Kanban. É só consulta: a troca de situação
     * continua na tela da OS, onde ficam as regras de estoque, aprovação e comissão.
     * Devolve [situação => [OS...]] com todas as colunas (mesmo vazias) e no máximo $limite OS.
     */
    public function kanban(array $f, int $limite = self::KANBAN_LIMITE): array
    {
        [$where, $params, $from] = $this->filtro(['situacao' => 'abertas'] + $f);
        $colunas = array_fill_keys(self::KANBAN, []);
        $linhas  = $this->lista(self::CAMPOS_LISTA . " {$from} WHERE {$where} ORDER BY os.numero DESC LIMIT " . max(1, $limite), $params);
        foreach ($linhas as $o) {
            $colunas[$o['status']][] = $o;
        }

        return $colunas;
    }

    /** Passou da previsão de conclusão e ainda não ficou pronta. */
    public static function atrasada(array $os): bool
    {
        return !empty($os['previsao_conclusao'])
            && !in_array($os['status'], ['PRONTA', 'ENTREGUE', 'RECUSADA', 'CANCELADA'], true)
            && strtotime($os['previsao_conclusao']) < time();
    }

    private const CAMPOS_LISTA = "SELECT os.id, os.numero, os.status, os.valor_total, os.criado_em, os.previsao_conclusao, os.entregue_em,
                    c.nome AS cliente, c.telefone AS cliente_telefone, v.placa,
                    COALESCE(CONCAT(m.marca, ' ', m.nome), v.modelo_texto) AS modelo, t.nome AS tecnico";

    /** WHERE da lista (aba, busca e técnico) e o FROM com cliente, veículo, modelo e técnico. */
    private function filtro(array $f, bool $comSituacao = true): array
    {
        $where  = 'os.oficina_id = :oficina';
        $params = [];
        $status = $comSituacao ? (self::FILTROS[$f['situacao'] ?? 'abertas'][1] ?? self::FILTROS['abertas'][1]) : [];
        if ($status) {
            $marcadores = [];
            foreach ($status as $i => $s) {
                $marcadores[] = ":s{$i}";
                $params["s{$i}"] = $s;
            }
            $where .= ' AND os.status IN (' . implode(',', $marcadores) . ')';
        }
        if (($f['busca'] ?? '') !== '') {
            $num = (int) preg_replace('/\D/', '', $f['busca']);
            $where .= ' AND (c.nome LIKE :b1 OR v.placa_normalizada LIKE :b2' . ($num > 0 ? ' OR os.numero = :num' : '') . ')';
            $params += ['b1' => '%' . $f['busca'] . '%', 'b2' => '%' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $f['busca'])) . '%'];
            if ($num > 0) {
                $params['num'] = $num;
            }
        }
        if (!empty($f['tecnico'])) {
            $where .= ' AND os.tecnico_id = :tec';
            $params['tec'] = (int) $f['tecnico'];
        }

        $from = 'FROM ordens_servico os
                 JOIN clientes c ON c.oficina_id = os.oficina_id AND c.id = os.cliente_id
                 JOIN veiculos v ON v.oficina_id = os.oficina_id AND v.id = os.veiculo_id
                 LEFT JOIN modelos_veiculos m ON m.id = v.modelo_id
                 LEFT JOIN usuarios t ON t.id = os.tecnico_id';

        return [$where, $params, $from];
    }

    public function buscar(int $id): ?array
    {
        return $this->linha(
            "SELECT os.*, c.nome AS cliente, c.telefone AS cliente_telefone, c.email AS cliente_email, c.documento AS cliente_documento,
                    c.aceita_contato, v.placa, v.ano, v.cor, COALESCE(CONCAT(m.marca, ' ', m.nome), v.modelo_texto) AS modelo,
                    t.nome AS tecnico, cr.nome AS criado_por_nome
             FROM ordens_servico os
             JOIN clientes c ON c.oficina_id = os.oficina_id AND c.id = os.cliente_id
             JOIN veiculos v ON v.oficina_id = os.oficina_id AND v.id = os.veiculo_id
             LEFT JOIN modelos_veiculos m ON m.id = v.modelo_id
             LEFT JOIN usuarios t ON t.id = os.tecnico_id
             LEFT JOIN usuarios cr ON cr.id = os.criado_por
             WHERE os.id = :id AND os.oficina_id = :oficina",
            ['id' => $id]
        );
    }

    public function itens(int $osId): array
    {
        return $this->lista(
            'SELECT i.*, t.nome AS tecnico, p.estoque_atual FROM ordem_servico_itens i
             LEFT JOIN usuarios t ON t.id = i.tecnico_id
             LEFT JOIN produtos p ON p.oficina_id = i.oficina_id AND p.id = i.produto_id
             WHERE i.oficina_id = :oficina AND i.ordem_servico_id = :os ORDER BY i.tipo DESC, i.id',
            ['os' => $osId]
        );
    }

    public function historico(int $osId): array
    {
        return $this->hist->daOs($osId);
    }

    /** Página pública de acompanhamento (sem login): só o necessário, achado pelo token. */
    public function porToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32,64}$/', $token)) {
            return null;
        }

        $os = $this->linhaGlobal(
            "SELECT os.id, os.oficina_id, os.numero, os.status, os.valor_total, os.previsao_conclusao, os.criado_em, os.aprovado_em,
                    os.entregue_em, os.defeito_relatado, v.placa, COALESCE(CONCAT(m.marca, ' ', m.nome), v.modelo_texto) AS modelo,
                    o.nome AS oficina, o.telefone AS oficina_telefone, o.endereco AS oficina_endereco
             FROM ordens_servico os
             JOIN oficinas o ON o.id = os.oficina_id
             JOIN veiculos v ON v.oficina_id = os.oficina_id AND v.id = os.veiculo_id
             LEFT JOIN modelos_veiculos m ON m.id = v.modelo_id
             WHERE os.token_rastreio = :t AND os.status <> 'CANCELADA'",
            ['t' => $token]
        );
        if ($os) {
            $os['itens'] = $this->listaGlobal(
                "SELECT tipo, descricao, quantidade, valor_unitario, desconto, valor_total FROM ordem_servico_itens
                 WHERE oficina_id = :o AND ordem_servico_id = :id AND estoque_status <> 'DEVOLVIDO' ORDER BY tipo DESC, id",
                ['o' => $os['oficina_id'], 'id' => $os['id']]
            );
        }

        return $os;
    }

    /* ── Abertura e dados ────────────────────────────────────────────── */

    public function criar(array $d): int
    {
        return Transacao::executar($this->db, function () use ($d) {
            $veiculo = $this->linha('SELECT id, cliente_id, ativo FROM veiculos WHERE id = :id AND oficina_id = :oficina', ['id' => (int) $d['veiculo_id']]);
            if (!$veiculo || (int) $veiculo['cliente_id'] !== (int) $d['cliente_id']) {
                throw new DomainException('Escolha um veículo deste cliente.');
            }

            // Número sequencial por oficina, sem corrida: a linha da oficina fica travada até o fim da transação.
            $numero = (int) $this->valor('SELECT proximo_numero_os FROM oficinas WHERE id = :oficina FOR UPDATE');
            $this->gravar('UPDATE oficinas SET proximo_numero_os = proximo_numero_os + 1 WHERE id = :oficina');

            $id = $this->inserir('ordens_servico', $this->dadosEditaveis($d) + [
                'numero'         => $numero,
                'cliente_id'     => (int) $d['cliente_id'],
                'veiculo_id'     => (int) $d['veiculo_id'],
                'status'         => 'ORCAMENTO',
                'token_rastreio' => bin2hex(random_bytes(16)),
                'criado_por'     => Contexto::usuarioId(),
            ]);
            $this->hist->registrar(['ordem_servico_id' => $id], 'CRIACAO', null, 'ORCAMENTO');

            return $id;
        });
    }

    public function atualizarDados(int $id, array $d): void
    {
        $os = $this->exigir($id);
        if (in_array($os['status'], ['ENTREGUE', 'CANCELADA'], true)) {
            throw new DomainException('Ordem de serviço encerrada: os dados não podem mais ser alterados.');
        }

        $novos = $this->dadosEditaveis($d);
        $mudou = [];
        foreach ($novos as $k => $v) {
            if ((string) ($os[$k] ?? '') !== (string) ($v ?? '')) {
                $mudou[$k] = ['de' => $os[$k], 'para' => $v];
            }
        }
        if (!$mudou) {
            return;
        }

        Transacao::executar($this->db, function () use ($id, $novos, $mudou) {
            $this->atualizar('ordens_servico', $id, $novos);
            $this->hist->registrar(['ordem_servico_id' => $id], 'DADOS', null, null, null, $mudou);
        });
    }

    private function dadosEditaveis(array $d): array
    {
        $txt = fn($k) => trim((string) ($d[$k] ?? '')) ?: null;
        $tecnico = !empty($d['tecnico_id']) ? (int) $d['tecnico_id'] : null;

        return [
            'km_veiculo'           => ($d['km_veiculo'] ?? '') !== '' ? (int) so_digitos((string) $d['km_veiculo']) : null,
            'defeito_relatado'     => $txt('defeito_relatado'),
            'diagnostico'          => $txt('diagnostico'),
            'observacoes'          => $txt('observacoes'),
            'observacoes_internas' => $txt('observacoes_internas'),
            'tecnico_id'           => $tecnico,
            'previsao_conclusao'   => $txt('previsao_conclusao') ? date('Y-m-d H:i:s', strtotime($d['previsao_conclusao'])) : null,
            'validade_orcamento'   => $txt('validade_orcamento') ? date('Y-m-d', strtotime($d['validade_orcamento'])) : null,
        ];
    }

    /* ── Itens ───────────────────────────────────────────────────────── */

    public function adicionarItem(int $osId, array $d): void
    {
        Transacao::executar($this->db, function () use ($osId, $d) {
            $os = $this->exigir($osId, true);
            if (!in_array($os['status'], self::EDITAVEL, true)) {
                throw new DomainException('Nesta situação a OS não aceita novos itens.');
            }

            $produto = null;
            if (!empty($d['produto_id'])) {
                $produto = $this->linha('SELECT * FROM produtos WHERE id = :id AND oficina_id = :oficina AND ativo = 1', ['id' => (int) $d['produto_id']])
                    ?? throw new DomainException('Item do catálogo não encontrado.');
            }
            $tipo = $produto['tipo'] ?? (($d['tipo'] ?? '') === 'PECA' ? 'PECA' : 'SERVICO');
            if ($tipo === 'PECA' && !$produto) {
                throw new DomainException('Peça precisa ser escolhida do catálogo (é ela que controla o estoque).');
            }

            $qtd   = Produto::valorDecimal((string) ($d['quantidade'] ?? '1'));
            $valor = ($d['valor_unitario'] ?? '') !== '' ? Produto::valorDecimal((string) $d['valor_unitario']) : (float) ($produto['preco_venda'] ?? 0);
            $desc  = Produto::valorDecimal((string) ($d['desconto'] ?? '0'));
            $descricao = trim((string) ($d['descricao'] ?? '')) ?: ($produto['nome'] ?? '');

            if ($descricao === '') {
                throw new DomainException('Descreva o serviço.');
            }
            if ($qtd <= 0) {
                throw new DomainException('A quantidade precisa ser maior que zero.');
            }
            if ($desc > round($qtd * $valor, 2)) {
                throw new DomainException('O desconto não pode ser maior que o valor do item.');
            }

            $itemId = $this->inserir('ordem_servico_itens', [
                'ordem_servico_id' => $osId,
                'tipo'             => $tipo,
                'origem'           => $os['status'] === 'ORCAMENTO' ? 'ORCAMENTO' : 'ADICIONAL',
                'produto_id'       => $produto['id'] ?? null,
                'descricao'        => mb_substr($descricao, 0, 200),
                'categoria'        => $produto['categoria'] ?? null,
                'quantidade'       => $qtd,
                'valor_unitario'   => $valor,
                'desconto'         => $desc,
                'custo_unitario'   => $produto ? $produto['preco_custo'] : null,
                'tecnico_id'       => $tipo === 'SERVICO' && !empty($d['tecnico_id']) ? (int) $d['tecnico_id'] : null,
                'estoque_status'   => $tipo === 'PECA' ? 'PENDENTE' : 'NAO_SE_APLICA',
            ]);

            $item = $this->item($osId, $itemId);
            $this->hist->registrar(['ordem_servico_id' => $osId], $item['origem'] === 'ADICIONAL' ? 'ITEM_ADICIONAL' : 'ITEM_INCLUIDO', null, null, null, $this->resumoItem($item));

            // Durante a execução a peça sai do estoque na hora; sem saldo, a OS passa a aguardar peça.
            if ($tipo === 'PECA' && in_array($os['status'], ['EM_EXECUCAO', 'AGUARDANDO_PECA'], true)) {
                if (!(new Estoque($this->db))->baixarItemOs($item + ['os_numero' => $os['numero']]) && $os['status'] === 'EM_EXECUCAO') {
                    $this->mudarStatus($os, 'AGUARDANDO_PECA', 'Peça sem estoque: ' . $item['descricao']);
                }
            }
            $this->recalcular($osId);
        });
    }

    /** No orçamento, o item pode ser corrigido; depois da aprovação, só o técnico do serviço muda. */
    public function alterarItem(int $osId, int $itemId, array $d): void
    {
        Transacao::executar($this->db, function () use ($osId, $itemId, $d) {
            $os = $this->exigir($osId, true);
            $item = $this->item($osId, $itemId) ?? throw new ErroHttp('registro-inexistente');
            if (!in_array($os['status'], self::EDITAVEL, true) || $item['estoque_status'] === 'DEVOLVIDO') {
                throw new DomainException('Este item não pode mais ser alterado.');
            }

            $novos = ['tecnico_id' => $item['tipo'] === 'SERVICO' && !empty($d['tecnico_id']) ? (int) $d['tecnico_id'] : null];
            if ($os['status'] === 'ORCAMENTO') {
                $novos += [
                    'descricao'      => mb_substr(trim((string) ($d['descricao'] ?? '')) ?: $item['descricao'], 0, 200),
                    'quantidade'     => Produto::valorDecimal((string) ($d['quantidade'] ?? $item['quantidade'])),
                    'valor_unitario' => Produto::valorDecimal((string) ($d['valor_unitario'] ?? $item['valor_unitario'])),
                    'desconto'       => Produto::valorDecimal((string) ($d['desconto'] ?? '0')),
                ];
                if ($novos['quantidade'] <= 0 || $novos['desconto'] > round($novos['quantidade'] * $novos['valor_unitario'], 2)) {
                    throw new DomainException('Quantidade ou desconto inválido.');
                }
            }

            $this->atualizar('ordem_servico_itens', $itemId, $novos);
            $this->hist->registrar(['ordem_servico_id' => $osId], 'ITEM_ALTERADO', null, null, null,
                ['antes' => $this->resumoItem($item), 'depois' => $this->resumoItem($this->item($osId, $itemId))]);
            $this->recalcular($osId);
        });
    }

    public function removerItem(int $osId, int $itemId, string $motivo = ''): void
    {
        Transacao::executar($this->db, function () use ($osId, $itemId, $motivo) {
            $os = $this->exigir($osId, true);
            $item = $this->item($osId, $itemId) ?? throw new ErroHttp('registro-inexistente');
            if (!in_array($os['status'], self::EDITAVEL, true) || $item['estoque_status'] === 'DEVOLVIDO') {
                throw new DomainException('Este item não pode mais ser removido.');
            }
            if ($os['status'] !== 'ORCAMENTO' && trim($motivo) === '') {
                throw new DomainException('Informe o motivo da remoção (o cliente já aprovou este orçamento).');
            }

            $this->hist->registrar(['ordem_servico_id' => $osId], 'ITEM_REMOVIDO', null, null, $motivo ?: null, $this->resumoItem($item));
            if ($item['estoque_status'] === 'BAIXADO') {
                (new Estoque($this->db))->devolverItemOs($item, 'Removido da OS ' . os_numero($os['numero']));
            } else {
                $this->apagar('ordem_servico_itens', $itemId);
            }
            $this->recalcular($osId);
            $this->conferirPecas($osId);
        });
    }

    /* ── Situação ────────────────────────────────────────────────────── */

    public function aprovar(int $id, string $aprovadoPor, ?string $assinatura): void
    {
        Transacao::executar($this->db, function () use ($id, $aprovadoPor, $assinatura) {
            $os = $this->exigirTransicao($id, 'aprovar');
            $itens = array_values(array_filter($this->itens($id), fn($i) => $i['estoque_status'] !== 'DEVOLVIDO'));
            if (!$itens) {
                throw new DomainException('Inclua pelo menos um item antes de aprovar o orçamento.');
            }

            $this->atualizar('ordens_servico', $id, [
                'status' => 'APROVADA', 'aprovado_em' => date('Y-m-d H:i:s'), 'aprovado_por_nome' => mb_substr(trim($aprovadoPor), 0, 120) ?: null,
                'assinatura_cliente' => $assinatura, 'valor_aprovado' => $os['valor_total'], 'recusado_em' => null, 'motivo_recusa' => null,
            ]);
            // Cópia fiel do que o cliente aprovou: mesmo que os itens mudem depois, isto não muda.
            $this->hist->registrar(['ordem_servico_id' => $id], 'APROVACAO', 'ORCAMENTO', 'APROVADA', $aprovadoPor ?: null, [
                'valor_aprovado' => $os['valor_total'],
                'itens' => array_map([$this, 'resumoItem'], $itens),
            ]);
        });
    }

    public function recusar(int $id, string $motivo): void
    {
        Transacao::executar($this->db, function () use ($id, $motivo) {
            $os = $this->exigirTransicao($id, 'recusar');
            $this->atualizar('ordens_servico', $id, ['status' => 'RECUSADA', 'recusado_em' => date('Y-m-d H:i:s'), 'motivo_recusa' => mb_substr(trim($motivo), 0, 255) ?: null]);
            $this->hist->registrar(['ordem_servico_id' => $id], 'STATUS', $os['status'], 'RECUSADA', $motivo ?: null);
        });
    }

    public function reabrir(int $id): void
    {
        Transacao::executar($this->db, function () use ($id) {
            $os = $this->exigirTransicao($id, 'reabrir');
            $this->atualizar('ordens_servico', $id, ['status' => 'ORCAMENTO', 'recusado_em' => null]);
            $this->hist->registrar(['ordem_servico_id' => $id], 'STATUS', $os['status'], 'ORCAMENTO', 'Orçamento reaberto');
        });
    }

    /** Inicia a execução e baixa as peças do estoque. Peça sem saldo deixa a OS aguardando peça. */
    public function iniciar(int $id): array
    {
        return Transacao::executar($this->db, function () use ($id) {
            $os = $this->exigirTransicao($id, 'iniciar');
            $this->mudarStatus($os, 'EM_EXECUCAO');

            return $this->conferirPecas($id);
        });
    }

    /** Tenta baixar as peças pendentes (chegou mercadoria). Devolve as que ainda faltam. */
    public function baixarPendentes(int $id): array
    {
        return Transacao::executar($this->db, function () use ($id) {
            $os = $this->exigir($id, true);
            if (!in_array($os['status'], ['EM_EXECUCAO', 'AGUARDANDO_PECA'], true)) {
                throw new DomainException('A OS não está em execução.');
            }

            return $this->conferirPecas($id);
        });
    }

    public function concluir(int $id): void
    {
        Transacao::executar($this->db, function () use ($id) {
            $os = $this->exigirTransicao($id, 'concluir');
            $pendentes = array_filter($this->itens($id), fn($i) => $i['estoque_status'] === 'PENDENTE');
            if ($pendentes) {
                throw new DomainException('Ainda há peça sem baixa no estoque: ' . implode(', ', array_column($pendentes, 'descricao')) . '.');
            }

            $this->calcularComissoes($os);
            $this->atualizar('ordens_servico', $id, ['status' => 'PRONTA', 'concluido_em' => date('Y-m-d H:i:s')]);
            $this->hist->registrar(['ordem_servico_id' => $id], 'STATUS', $os['status'], 'PRONTA');
        });
    }

    public function retomar(int $id, string $motivo): void
    {
        Transacao::executar($this->db, function () use ($id, $motivo) {
            $os = $this->exigirTransicao($id, 'retomar');
            $pagas = (int) $this->valor('SELECT COUNT(*) FROM ordem_servico_itens WHERE oficina_id = :oficina AND ordem_servico_id = :id AND comissao_lancamento_id IS NOT NULL', ['id' => $id]);
            if ($pagas > 0) {
                throw new DomainException('A comissão desta OS já foi lançada para pagamento. Para voltar a OS à execução, cancele antes a conta da comissão no financeiro.');
            }
            $this->atualizar('ordens_servico', $id, ['status' => 'EM_EXECUCAO', 'concluido_em' => null]);
            $this->hist->registrar(['ordem_servico_id' => $id], 'STATUS', 'PRONTA', 'EM_EXECUCAO', $motivo ?: null);
        });
    }

    public function entregar(int $id, string $checklist): void
    {
        Transacao::executar($this->db, function () use ($id, $checklist) {
            $os = $this->exigirTransicao($id, 'entregar');
            $this->atualizar('ordens_servico', $id, ['status' => 'ENTREGUE', 'entregue_em' => date('Y-m-d H:i:s'), 'checklist_entrega' => trim($checklist) ?: $os['checklist_entrega']]);
            $this->hist->registrar(['ordem_servico_id' => $id], 'STATUS', 'PRONTA', 'ENTREGUE');
        });
    }

    public function cancelar(int $id, string $motivo): void
    {
        if (trim($motivo) === '') {
            throw new DomainException('Informe o motivo do cancelamento.');
        }

        Transacao::executar($this->db, function () use ($id, $motivo) {
            $os = $this->exigirTransicao($id, 'cancelar');
            $estoque = new Estoque($this->db);
            foreach ($this->itens($id) as $item) {
                $estoque->devolverItemOs($item, 'OS ' . os_numero($os['numero']) . ' cancelada');
            }
            $this->atualizar('ordens_servico', $id, ['status' => 'CANCELADA', 'cancelado_em' => date('Y-m-d H:i:s'), 'motivo_cancelamento' => mb_substr(trim($motivo), 0, 255)]);
            $this->hist->registrar(['ordem_servico_id' => $id], 'STATUS', $os['status'], 'CANCELADA', $motivo);
        });
    }

    /* ── Apoio ───────────────────────────────────────────────────────── */

    /** Baixa as peças pendentes e acerta EM_EXECUCAO ⇄ AGUARDANDO_PECA. Devolve as descrições que faltam. */
    private function conferirPecas(int $id): array
    {
        $os = $this->exigir($id);
        if (!in_array($os['status'], ['EM_EXECUCAO', 'AGUARDANDO_PECA'], true)) {
            return [];
        }

        $estoque = new Estoque($this->db);
        $faltam  = [];
        foreach ($this->itens($id) as $item) {
            if ($item['estoque_status'] === 'PENDENTE' && !$estoque->baixarItemOs($item + ['os_numero' => $os['numero']])) {
                $faltam[] = $item['descricao'];
            }
        }

        $deveria = $faltam ? 'AGUARDANDO_PECA' : 'EM_EXECUCAO';
        if ($os['status'] !== $deveria) {
            $this->mudarStatus($os, $deveria, $faltam ? 'Sem estoque: ' . implode(', ', $faltam) : 'Peças disponíveis');
            if ($faltam) {
                // Alternativa simples à "solicitação de peça" da v1: o ADMIN fica sabendo o que comprar.
                (new Notificacao($this->db))->paraAdmins('PECA_FALTANDO', 'OS ' . os_numero($os['numero']) . ' aguardando peça',
                    'Sem estoque: ' . implode(', ', $faltam), './os?id=' . $id, 'PECA:' . $id . ':' . md5(implode('|', $faltam)));
            }
        }

        return $faltam;
    }

    /** Comissão de cada item pela regra do técnico (do item ou, se não houver, o responsável pela OS). */
    private function calcularComissoes(array $os): void
    {
        $regras = [];
        foreach ($this->lista('SELECT id, comissao_tipo, comissao_percentual FROM usuarios WHERE oficina_id = :oficina') as $u) {
            $regras[(int) $u['id']] = $u;
        }

        foreach ($this->itens((int) $os['id']) as $item) {
            if ($item['estoque_status'] === 'DEVOLVIDO') {
                continue;
            }
            $tec   = $item['tecnico_id'] ?: $os['tecnico_id'];
            $regra = $tec ? ($regras[(int) $tec] ?? null) : null;
            $vale  = $regra && (
                $regra['comissao_tipo'] === 'PERCENTUAL_TOTAL'
                || ($regra['comissao_tipo'] === 'PERCENTUAL_SERVICOS' && $item['tipo'] === 'SERVICO')
                || ($regra['comissao_tipo'] === 'PERCENTUAL_PECAS' && $item['tipo'] === 'PECA'));

            $this->atualizar('ordem_servico_itens', (int) $item['id'], [
                'tecnico_id'          => $vale || $item['tipo'] === 'SERVICO' ? ($tec ?: null) : $item['tecnico_id'],
                'comissao_percentual' => $vale ? $regra['comissao_percentual'] : 0,
                'comissao_valor'      => $vale ? round((float) $item['valor_total'] * (float) $regra['comissao_percentual'] / 100, 2) : 0,
            ]);
        }
    }

    private function recalcular(int $id): void
    {
        $this->gravar(
            "UPDATE ordens_servico os
             LEFT JOIN (SELECT ordem_servico_id,
                               SUM(IF(tipo = 'PECA', ROUND(quantidade * valor_unitario, 2), 0)) AS pecas,
                               SUM(IF(tipo = 'SERVICO', ROUND(quantidade * valor_unitario, 2), 0)) AS servicos,
                               SUM(desconto) AS descontos, SUM(valor_total) AS total
                        FROM ordem_servico_itens
                        WHERE oficina_id = :oficina AND ordem_servico_id = :id1 AND estoque_status <> 'DEVOLVIDO'
                        GROUP BY ordem_servico_id) t ON t.ordem_servico_id = os.id
             SET os.valor_pecas = COALESCE(t.pecas, 0), os.valor_servicos = COALESCE(t.servicos, 0),
                 os.valor_desconto = COALESCE(t.descontos, 0), os.valor_total = COALESCE(t.total, 0)
             WHERE os.id = :id2 AND os.oficina_id = :oficina",
            ['id1' => $id, 'id2' => $id]
        );
    }

    private function mudarStatus(array $os, string $novo, ?string $motivo = null): void
    {
        $this->atualizar('ordens_servico', (int) $os['id'], ['status' => $novo]);
        $this->hist->registrar(['ordem_servico_id' => (int) $os['id']], 'STATUS', $os['status'], $novo, $motivo);
    }

    private function exigir(int $id, bool $travar = false): array
    {
        return $this->linha('SELECT * FROM ordens_servico WHERE id = :id AND oficina_id = :oficina' . ($travar ? ' FOR UPDATE' : ''), ['id' => $id])
            ?? throw new ErroHttp('registro-inexistente', ['voltar' => './ordens']);
    }

    private function exigirTransicao(int $id, string $acao): array
    {
        $os = $this->exigir($id, true);
        [$de, $para] = self::TRANSICOES[$acao];
        if (!in_array($os['status'], $de, true)) {
            throw new DomainException('Esta ação não é possível na situação atual da OS (' . os_status($os['status'])[0] . ').');
        }

        return $os;
    }

    private function item(int $osId, int $itemId): ?array
    {
        return $this->linha('SELECT * FROM ordem_servico_itens WHERE id = :id AND ordem_servico_id = :os AND oficina_id = :oficina', ['id' => $itemId, 'os' => $osId]);
    }

    private function resumoItem(array $i): array
    {
        return array_intersect_key($i, array_flip(['id', 'tipo', 'origem', 'produto_id', 'descricao', 'quantidade', 'valor_unitario', 'desconto', 'valor_total', 'tecnico_id']));
    }
}
