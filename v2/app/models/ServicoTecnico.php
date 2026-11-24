<?php

/**
 * A área do TÉCNICO: só as OS em que ele está — responsável pela OS ou técnico de algum serviço dela.
 *
 * Toda consulta daqui leva o id do técnico logado (Contexto), nunca um id vindo da tela. Uma OS de
 * outro técnico (ou de outra oficina) simplesmente "não existe" para ele: mesmo erro de registro
 * inexistente, sem dizer que ela existe. Valores em dinheiro não saem daqui.
 *
 * Os materiais que o técnico informa (os_materiais) não mexem no estoque: ficam pendentes até a
 * validação do ADMIN (OrdemServico::validar), que é quem baixa, uma única vez.
 */
final class ServicoTecnico extends Modelo
{
    /** Situações em que a OS aparece para o técnico (orçamento, recusa e cancelamento não são trabalho dele). */
    public const VISIVEIS = ['APROVADA', 'EM_EXECUCAO', 'AGUARDANDO_PECA', 'AGUARDANDO_VALIDACAO', 'PRONTA', 'ENTREGUE'];

    /** Abas do painel: o que ele precisa fazer primeiro. */
    public const ABAS = [
        'fazer'     => ['A fazer', ['APROVADA', 'EM_EXECUCAO', 'AGUARDANDO_PECA']],
        'validacao' => ['Em validação', ['AGUARDANDO_VALIDACAO']],
        'feitos'    => ['Concluídos', ['PRONTA', 'ENTREGUE']],
    ];

    private const VINCULO = '(os.tecnico_id = :tec1 OR EXISTS (SELECT 1 FROM ordem_servico_itens vi
                               WHERE vi.oficina_id = os.oficina_id AND vi.ordem_servico_id = os.id AND vi.tecnico_id = :tec2))';

    private function tec(): array
    {
        $id = (int) Contexto::usuarioId();

        return ['tec1' => $id, 'tec2' => $id];
    }

    /** Serviços do técnico numa aba. Concluídos: só os últimos 30 dias. */
    public function servicos(string $aba): array
    {
        $status = self::ABAS[$aba][1] ?? self::ABAS['fazer'][1];
        $params = $this->tec();
        $marcas = [];
        foreach ($status as $i => $s) {
            $marcas[] = ":s{$i}";
            $params["s{$i}"] = $s;
        }
        $recentes = $aba === 'feitos' ? ' AND COALESCE(os.concluido_em, os.atualizado_em, os.criado_em) >= NOW() - INTERVAL 30 DAY' : '';

        return $this->lista(
            "SELECT os.id, os.numero, os.status, os.defeito_relatado, os.diagnostico, os.observacoes_internas, os.previsao_conclusao,
                    os.execucao_iniciada_em, os.execucao_enviada_em, os.execucao_devolvida_em, os.execucao_devolucao_motivo,
                    os.concluido_em, os.criado_em, os.aprovado_em, c.nome AS cliente, v.placa, v.cor, v.ano,
                    COALESCE(CONCAT(m.marca, ' ', m.nome), v.modelo_texto) AS modelo,
                    (SELECT GROUP_CONCAT(i.descricao ORDER BY i.id SEPARATOR ' · ') FROM ordem_servico_itens i
                      WHERE i.oficina_id = os.oficina_id AND i.ordem_servico_id = os.id AND i.tipo = 'SERVICO' AND i.estoque_status <> 'DEVOLVIDO') AS servicos,
                    (SELECT COUNT(*) FROM os_materiais om WHERE om.oficina_id = os.oficina_id AND om.ordem_servico_id = os.id AND om.status = 'PENDENTE') AS materiais_pendentes
             FROM ordens_servico os
             JOIN clientes c ON c.oficina_id = os.oficina_id AND c.id = os.cliente_id
             JOIN veiculos v ON v.oficina_id = os.oficina_id AND v.id = os.veiculo_id
             LEFT JOIN modelos_veiculos m ON m.id = v.modelo_id
             WHERE os.oficina_id = :oficina AND " . self::VINCULO . ' AND os.status IN (' . implode(',', $marcas) . "){$recentes}
             ORDER BY (os.execucao_devolvida_em > COALESCE(os.execucao_enviada_em, '1000-01-01')) DESC,
                      FIELD(os.status, 'APROVADA', 'AGUARDANDO_PECA', 'EM_EXECUCAO') DESC,
                      os.previsao_conclusao IS NULL, os.previsao_conclusao, os.numero DESC
             LIMIT 100",
            $params
        );
    }

    /** Quantos serviços há em cada aba (para os números das abas). */
    public function contagens(): array
    {
        $porStatus = [];
        foreach ($this->lista('SELECT os.status, COUNT(*) AS n FROM ordens_servico os WHERE os.oficina_id = :oficina AND ' . self::VINCULO
            . " AND (os.status NOT IN ('PRONTA','ENTREGUE') OR COALESCE(os.concluido_em, os.atualizado_em, os.criado_em) >= NOW() - INTERVAL 30 DAY)
              GROUP BY os.status", $this->tec()) as $l) {
            $porStatus[$l['status']] = (int) $l['n'];
        }
        $r = [];
        foreach (self::ABAS as $k => [, $status]) {
            $r[$k] = array_sum(array_intersect_key($porStatus, array_flip($status)));
        }

        return $r;
    }

    /**
     * A OS, se o técnico logado estiver nela. Senão, "registro inexistente" — o mesmo de um id que não existe.
     * Com $travar, trava a linha (para gravar em seguida, na mesma transação).
     */
    public function exigir(int $osId, bool $travar = false): array
    {
        $os = $this->linha(
            "SELECT os.id, os.numero, os.status, os.tecnico_id, os.defeito_relatado, os.diagnostico, os.observacoes, os.observacoes_internas,
                    os.km_veiculo, os.previsao_conclusao, os.aprovado_em, os.criado_em, os.concluido_em, os.entregue_em,
                    os.execucao_iniciada_em, os.execucao_enviada_em, os.execucao_observacao, os.execucao_devolvida_em, os.execucao_devolucao_motivo,
                    c.nome AS cliente, v.placa, v.cor, v.ano, v.combustivel, COALESCE(CONCAT(m.marca, ' ', m.nome), v.modelo_texto) AS modelo,
                    t.nome AS tecnico
             FROM ordens_servico os
             JOIN clientes c ON c.oficina_id = os.oficina_id AND c.id = os.cliente_id
             JOIN veiculos v ON v.oficina_id = os.oficina_id AND v.id = os.veiculo_id
             LEFT JOIN modelos_veiculos m ON m.id = v.modelo_id
             LEFT JOIN usuarios t ON t.id = os.tecnico_id
             WHERE os.id = :id AND os.oficina_id = :oficina AND " . self::VINCULO . "
               AND os.status IN ('" . implode("','", self::VISIVEIS) . "')" . ($travar ? ' FOR UPDATE' : ''),
            ['id' => $osId] + $this->tec()
        );

        return $os ?? throw new ErroHttp('registro-inexistente', ['voltar' => './servicos']);
    }

    /** OS em aberto do técnico, para ligar um pedido de material a uma delas. */
    public function abertas(): array
    {
        return $this->lista(
            "SELECT os.id, os.numero, v.placa, COALESCE(CONCAT(m.marca, ' ', m.nome), v.modelo_texto) AS modelo
             FROM ordens_servico os
             JOIN veiculos v ON v.oficina_id = os.oficina_id AND v.id = os.veiculo_id
             LEFT JOIN modelos_veiculos m ON m.id = v.modelo_id
             WHERE os.oficina_id = :oficina AND " . self::VINCULO . " AND os.status IN ('APROVADA','EM_EXECUCAO','AGUARDANDO_PECA','AGUARDANDO_VALIDACAO')
             ORDER BY os.numero DESC",
            $this->tec()
        );
    }

    /** Serviços e peças previstos na OS — sem valores. `meu` marca os serviços dele. */
    public function itens(int $osId): array
    {
        $this->exigir($osId);

        return $this->lista(
            "SELECT i.id, i.tipo, i.origem, i.descricao, i.quantidade, i.estoque_status, i.executado, i.tecnico_id = :eu AS meu, t.nome AS tecnico
             FROM ordem_servico_itens i LEFT JOIN usuarios t ON t.id = i.tecnico_id
             WHERE i.oficina_id = :oficina AND i.ordem_servico_id = :os AND i.estoque_status <> 'DEVOLVIDO'
             ORDER BY i.tipo DESC, i.id",
            ['os' => $osId, 'eu' => (int) Contexto::usuarioId()]
        );
    }

    /* ── Materiais usados (os_materiais) ─────────────────────────────── */

    public function materiais(int $osId): array
    {
        return $this->lista(
            "SELECT m.*, p.nome, p.codigo_interno, t.nome AS tecnico_nome, d.nome AS decidido_por_nome
             FROM os_materiais m
             JOIN produtos p ON p.oficina_id = m.oficina_id AND p.id = m.produto_id
             JOIN usuarios t ON t.id = m.tecnico_id
             LEFT JOIN usuarios d ON d.id = m.decidido_por
             WHERE m.oficina_id = :oficina AND m.ordem_servico_id = :os
             ORDER BY m.status = 'CANCELADO', m.criado_em, m.id",
            ['os' => $osId]
        );
    }

    /** O técnico informa um material usado. Só registra: o estoque só muda na validação do ADMIN. */
    public function registrarMaterial(int $osId, array $d): void
    {
        Transacao::executar($this->db, function () use ($osId, $d) {
            $os = $this->exigir($osId, true);
            if (!in_array($os['status'], ['EM_EXECUCAO', 'AGUARDANDO_PECA'], true)) {
                throw new DomainException($os['status'] === 'APROVADA'
                    ? 'Toque em "Começar serviço" antes de registrar materiais.'
                    : 'Este serviço não aceita mais materiais (' . os_status($os['status'])[0] . ').');
            }
            $produto = $this->linha("SELECT id, nome FROM produtos WHERE id = :id AND oficina_id = :oficina AND ativo = 1 AND tipo = 'PECA'",
                ['id' => (int) ($d['produto_id'] ?? 0)]) ?? throw new DomainException('Escolha a peça na lista. Se ela não estiver cadastrada, use "Pedir ao administrador".');
            $qtd = Produto::valorDecimal((string) ($d['quantidade'] ?? '1'));
            if ($qtd <= 0 || $qtd > 9999) {
                throw new DomainException('Informe a quantidade usada.');
            }
            $obs = mb_substr(trim((string) ($d['observacao'] ?? '')), 0, 255) ?: null;

            $id = $this->inserir('os_materiais', ['ordem_servico_id' => $osId, 'tecnico_id' => (int) Contexto::usuarioId(),
                'produto_id' => (int) $produto['id'], 'quantidade' => $qtd, 'observacao' => $obs]);
            (new Historico($this->db))->registrar(['ordem_servico_id' => $osId], 'MATERIAL_INFORMADO', null, 'PENDENTE', $obs,
                ['material_id' => $id, 'produto_id' => (int) $produto['id'], 'produto' => $produto['nome'], 'quantidade' => $qtd]);
        });
    }

    /** O técnico desfaz um material que ele mesmo informou e que ainda não foi avaliado (fica registrado). */
    public function cancelarMaterial(int $osId, int $materialId): void
    {
        Transacao::executar($this->db, function () use ($osId, $materialId) {
            $os = $this->exigir($osId, true);
            if (!in_array($os['status'], ['EM_EXECUCAO', 'AGUARDANDO_PECA'], true)) {
                throw new DomainException('Com o serviço em validação ou concluído, os materiais não podem mais ser alterados.');
            }
            $m = $this->linha("SELECT m.*, p.nome FROM os_materiais m JOIN produtos p ON p.oficina_id = m.oficina_id AND p.id = m.produto_id
                               WHERE m.id = :id AND m.oficina_id = :oficina AND m.ordem_servico_id = :os AND m.tecnico_id = :eu FOR UPDATE",
                ['id' => $materialId, 'os' => $osId, 'eu' => (int) Contexto::usuarioId()]) ?? throw new ErroHttp('registro-inexistente', ['voltar' => './servicos']);
            if ($m['status'] !== 'PENDENTE') {
                throw new DomainException('Este material já foi avaliado pelo administrador.');
            }
            $this->atualizar('os_materiais', $materialId, ['status' => 'CANCELADO', 'decidido_por' => Contexto::usuarioId(), 'decidido_em' => date('Y-m-d H:i:s'),
                'motivo_decisao' => 'Desfeito pelo técnico']);
            (new Historico($this->db))->registrar(['ordem_servico_id' => $osId], 'MATERIAL_CANCELADO', 'PENDENTE', 'CANCELADO', null,
                ['material_id' => $materialId, 'produto' => $m['nome'], 'quantidade' => $m['quantidade']]);
        });
    }

    /** Peças ativas do catálogo para escolher (nome e código; sem saldo nem preço). */
    public function catalogoPecas(): array
    {
        return $this->lista("SELECT id, nome, codigo_interno, marca FROM produtos WHERE oficina_id = :oficina AND ativo = 1 AND tipo = 'PECA' ORDER BY nome LIMIT 2000");
    }

    /** O que aconteceu na OS, só os passos que interessam ao técnico (sem valores). */
    public function andamento(int $osId): array
    {
        $acoes = "'STATUS','EXECUCAO_INICIADA','EXECUCAO_ENVIADA','EXECUCAO_VALIDADA','EXECUCAO_DEVOLVIDA','MATERIAL_INFORMADO','MATERIAL_CANCELADO','MATERIAIS_VALIDADOS','PEDIDO_MATERIAL','ANEXO'";

        return $this->lista(
            "SELECT h.acao, h.estado_anterior, h.estado_novo, h.motivo, h.dados, h.criado_em, u.nome AS autor
             FROM historico h LEFT JOIN usuarios u ON u.id = h.autor_id
             WHERE h.oficina_id = :oficina AND h.ordem_servico_id = :os AND h.acao IN ({$acoes})
             ORDER BY h.criado_em DESC, h.id DESC LIMIT 20",
            ['os' => $osId]
        );
    }
}
