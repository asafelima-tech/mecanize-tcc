<?php

/**
 * Pedidos e avisos do técnico ao ADMIN (solicitacoes_material): peça que acabou, reposição, peça
 * indisponível, compra, material usado que não está no catálogo e problemas encontrados no serviço.
 *
 *   RECEBIDA ──▶ APROVADA ──▶ ATENDIDA          (o ADMIN pode atender direto ou recusar a qualquer momento antes de atender)
 *       └──────────────────▶ RECUSADA
 *
 * A linha guarda a situação atual; cada mudança vai para o histórico (com a OS, quando houver),
 * com quem fez e quando — nada é sobrescrito sem registro. O técnico só vê os pedidos dele.
 */
final class Solicitacao extends Modelo
{
    public const TIPOS = [
        'ACABOU'         => ['Acabou no estoque', 'Uma peça ou material acabou na prateleira.'],
        'REPOSICAO'      => ['Repor material', 'Está acabando e precisa ser reposto.'],
        'INDISPONIVEL'   => ['Peça indisponível', 'Uma peça necessária para o serviço não está disponível.'],
        'COMPRA'         => ['Comprar peça', 'Pedir a compra de uma peça específica.'],
        'NAO_CADASTRADO' => ['Usei material sem cadastro', 'Usei um material que não está no catálogo.'],
        'PROBLEMA'       => ['Problema no serviço', 'Algo encontrado no carro ou no serviço que o administrador precisa saber.'],
    ];

    public const SITUACOES = [
        'RECEBIDA' => ['Recebida', 'neutral'], 'APROVADA' => ['Aprovada', 'info'],
        'ATENDIDA' => ['Atendida', 'success'], 'RECUSADA' => ['Recusada', 'muted'],
    ];

    /** Para onde cada situação pode ir. */
    private const PASSOS = ['RECEBIDA' => ['APROVADA', 'ATENDIDA', 'RECUSADA'], 'APROVADA' => ['ATENDIDA', 'RECUSADA']];

    public const FILTROS = [
        'abertas'  => ['Em aberto', ['RECEBIDA', 'APROVADA']],
        'atendidas'=> ['Atendidas', ['ATENDIDA']],
        'recusadas'=> ['Recusadas', ['RECUSADA']],
        'todas'    => ['Todas', []],
    ];

    private const CAMPOS = "SELECT s.*, t.nome AS tecnico, r.nome AS respondido_por_nome, p.nome AS produto, p.codigo_interno,
                                   os.numero AS os_numero, v.placa
                            FROM solicitacoes_material s
                            JOIN usuarios t ON t.id = s.tecnico_id
                            LEFT JOIN usuarios r ON r.id = s.respondido_por
                            LEFT JOIN produtos p ON p.oficina_id = s.oficina_id AND p.id = s.produto_id
                            LEFT JOIN ordens_servico os ON os.oficina_id = s.oficina_id AND os.id = s.ordem_servico_id
                            LEFT JOIN veiculos v ON v.oficina_id = os.oficina_id AND v.id = os.veiculo_id";

    /** O técnico cria um pedido. A OS, se vier, precisa ser dele; a peça, do catálogo desta oficina. */
    public function criar(array $d): int
    {
        $tipo = (string) ($d['tipo'] ?? '');
        if (!isset(self::TIPOS[$tipo])) {
            throw new DomainException('Escolha o tipo do pedido.');
        }
        $justificativa = mb_substr(trim((string) ($d['justificativa'] ?? '')), 0, 500);
        if (mb_strlen($justificativa) < 3) {
            throw new DomainException('Conte em poucas palavras o que aconteceu ou o que você precisa.');
        }

        $osId = !empty($d['ordem_servico_id']) ? (int) $d['ordem_servico_id'] : null;
        if ($osId) {
            (new ServicoTecnico($this->db))->exigir($osId); // só OS em que ele está
        }
        $produtoId = !empty($d['produto_id']) ? (int) $d['produto_id'] : null;
        $produto = $produtoId ? ($this->linha('SELECT id, nome FROM produtos WHERE id = :id AND oficina_id = :oficina', ['id' => $produtoId])
            ?? throw new DomainException('Peça não encontrada no catálogo.')) : null;
        $descricao = mb_substr(trim((string) ($d['item_descricao'] ?? '')), 0, 150) ?: null;
        if ($tipo !== 'PROBLEMA' && !$produto && !$descricao) {
            throw new DomainException('Diga qual é a peça ou o material: escolha na lista ou escreva o nome.');
        }
        $qtd = ($d['quantidade'] ?? '') !== '' ? Produto::valorDecimal((string) $d['quantidade']) : null;
        if ($qtd !== null && ($qtd <= 0 || $qtd > 99999)) {
            throw new DomainException('A quantidade precisa ser maior que zero.');
        }

        return Transacao::executar($this->db, function () use ($tipo, $osId, $produto, $descricao, $qtd, $justificativa) {
            $id = $this->inserir('solicitacoes_material', [
                'tecnico_id' => (int) Contexto::usuarioId(), 'ordem_servico_id' => $osId, 'tipo' => $tipo,
                'produto_id' => $produto['id'] ?? null, 'item_descricao' => $produto ? null : $descricao,
                'quantidade' => $qtd, 'justificativa' => $justificativa,
            ]);
            $item = $produto['nome'] ?? $descricao;
            (new Historico($this->db))->registrar($osId ? ['ordem_servico_id' => $osId] : [], 'PEDIDO_MATERIAL', null, 'RECEBIDA', $justificativa,
                ['solicitacao_id' => $id, 'tipo' => $tipo, 'item' => $item, 'quantidade' => $qtd]);

            $numero = $osId ? $this->valor('SELECT numero FROM ordens_servico WHERE id = :id AND oficina_id = :oficina', ['id' => $osId]) : null;
            (new Notificacao($this->db))->paraAdmins('PEDIDO_MATERIAL', self::TIPOS[$tipo][0] . ($item ? ': ' . $item : ''),
                Contexto::usuario()['nome'] . ($numero ? ' · ' . os_numero($numero) : '') . ' — ' . $justificativa,
                './solicitacoes', 'PEDIDO:' . $id);

            return $id;
        });
    }

    /** Pedidos do técnico logado, os em aberto primeiro. */
    public function doTecnico(int $limite = 50): array
    {
        return $this->lista(self::CAMPOS . " WHERE s.oficina_id = :oficina AND s.tecnico_id = :eu
            ORDER BY s.status IN ('RECEBIDA','APROVADA') DESC, s.criado_em DESC, s.id DESC LIMIT " . max(1, min(200, $limite)),
            ['eu' => (int) Contexto::usuarioId()]);
    }

    /** Pedidos ligados a uma OS (para a validação e a ficha da OS). */
    public function daOs(int $osId): array
    {
        return $this->lista(self::CAMPOS . ' WHERE s.oficina_id = :oficina AND s.ordem_servico_id = :os ORDER BY s.criado_em DESC, s.id DESC', ['os' => $osId]);
    }

    /** Lista do ADMIN, por situação. */
    public function listar(string $filtro, Paginacao $p): array
    {
        [$where, $params] = $this->filtro($filtro);
        $p->total = (int) $this->valor("SELECT COUNT(*) FROM solicitacoes_material s WHERE {$where}", $params);

        return $this->lista(self::CAMPOS . " WHERE {$where} ORDER BY s.status = 'RECEBIDA' DESC, s.criado_em DESC, s.id DESC" . $p->sql(), $params);
    }

    public function contagens(): array
    {
        $por = [];
        foreach ($this->lista('SELECT status, COUNT(*) AS n FROM solicitacoes_material WHERE oficina_id = :oficina GROUP BY status') as $l) {
            $por[$l['status']] = (int) $l['n'];
        }
        $r = [];
        foreach (self::FILTROS as $k => [, $st]) {
            $r[$k] = $st ? array_sum(array_intersect_key($por, array_flip($st))) : array_sum($por);
        }

        return $r;
    }

    /** Quantos pedidos esperam resposta (para o aviso do ADMIN) ou, do técnico, quantos dele estão em aberto. */
    public function emAberto(?int $tecnicoId = null): int
    {
        return (int) $this->valor("SELECT COUNT(*) FROM solicitacoes_material WHERE oficina_id = :oficina AND status IN ('RECEBIDA','APROVADA')"
            . ($tecnicoId ? ' AND tecnico_id = :t' : ''), $tecnicoId ? ['t' => $tecnicoId] : []);
    }

    /** O ADMIN responde: aprova, marca como atendida ou recusa. Recusar pede o motivo. */
    public function responder(int $id, string $novo, string $resposta): void
    {
        $resposta = mb_substr(trim($resposta), 0, 255);
        if ($novo === 'RECUSADA' && $resposta === '') {
            throw new DomainException('Diga ao técnico por que o pedido foi recusado.');
        }

        Transacao::executar($this->db, function () use ($id, $novo, $resposta) {
            $s = $this->linha('SELECT * FROM solicitacoes_material WHERE id = :id AND oficina_id = :oficina FOR UPDATE', ['id' => $id])
                ?? throw new ErroHttp('registro-inexistente', ['voltar' => './solicitacoes']);
            if (!in_array($novo, self::PASSOS[$s['status']] ?? [], true)) {
                throw new DomainException('Este pedido já está ' . mb_strtolower(self::SITUACOES[$s['status']][0]) . '.');
            }

            $this->atualizar('solicitacoes_material', $id, ['status' => $novo, 'resposta' => $resposta !== '' ? $resposta : $s['resposta'],
                'respondido_por' => Contexto::usuarioId(), 'respondido_em' => date('Y-m-d H:i:s')]);
            (new Historico($this->db))->registrar($s['ordem_servico_id'] ? ['ordem_servico_id' => (int) $s['ordem_servico_id']] : [],
                'PEDIDO_MATERIAL', $s['status'], $novo, $resposta !== '' ? $resposta : null, ['solicitacao_id' => $id]);

            $titulos = ['APROVADA' => 'Pedido aprovado', 'ATENDIDA' => 'Pedido atendido', 'RECUSADA' => 'Pedido recusado'];
            (new Notificacao($this->db))->paraUsuario((int) $s['tecnico_id'], 'PEDIDO_' . $novo, $titulos[$novo] . ': ' . self::TIPOS[$s['tipo']][0],
                $resposta !== '' ? $resposta : null, './pedidos', 'PEDIDO:' . $id . ':' . $novo);
        });
    }

    private function filtro(string $filtro): array
    {
        $st = self::FILTROS[$filtro][1] ?? self::FILTROS['abertas'][1];
        $where = 's.oficina_id = :oficina';
        $params = [];
        if ($st) {
            $m = [];
            foreach ($st as $i => $s) {
                $m[] = ":s{$i}";
                $params["s{$i}"] = $s;
            }
            $where .= ' AND s.status IN (' . implode(',', $m) . ')';
        }

        return [$where, $params];
    }
}
