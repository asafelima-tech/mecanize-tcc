<?php

final class Veiculo extends Modelo
{
    protected array $mensagensUnicas = [
        'uq_veiculos_placa' => 'Já existe um veículo com esta placa nesta oficina.',
    ];

    public const COMBUSTIVEIS = ['Gasolina', 'Etanol', 'Flex (Álcool/Gasolina)', 'Diesel', 'GNV', 'Híbrido', 'Elétrico'];
    public const CORES = ['Branco', 'Branco Pérola', 'Preto', 'Prata', 'Cinza', 'Grafite', 'Chumbo', 'Vermelho', 'Vinho', 'Azul',
                          'Azul Marinho', 'Verde', 'Verde Escuro', 'Amarelo', 'Laranja', 'Marrom', 'Bege', 'Dourado', 'Bronze', 'Rosa', 'Roxo'];

    private const SELECT = "SELECT v.*, c.nome AS cliente, COALESCE(CONCAT(m.marca, ' ', m.nome), v.modelo_texto) AS modelo, m.marca
                            FROM veiculos v
                            JOIN clientes c ON c.oficina_id = v.oficina_id AND c.id = v.cliente_id
                            LEFT JOIN modelos_veiculos m ON m.id = v.modelo_id";

    /** Placa só com letras e números, maiúscula. Aceita a antiga (ABC1234) e a Mercosul (ABC1D23). */
    public static function normalizarPlaca(string $placa): ?string
    {
        $p = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $placa));

        return preg_match('/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/', $p) ? $p : null;
    }

    public static function placaFmt(string $normalizada): string
    {
        return ctype_digit($normalizada[4] ?? '') ? substr($normalizada, 0, 3) . '-' . substr($normalizada, 3) : $normalizada;
    }

    public function listar(array $f, Paginacao $p): array
    {
        $where  = 'v.oficina_id = :oficina';
        $params = [];
        if (($f['busca'] ?? '') !== '') {
            $where .= " AND (v.placa_normalizada LIKE :b1 OR c.nome LIKE :b2 OR m.nome LIKE :b3 OR m.marca LIKE :b4 OR v.modelo_texto LIKE :b5)";
            $b = '%' . $f['busca'] . '%';
            $params = ['b1' => '%' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $f['busca'])) . '%', 'b2' => $b, 'b3' => $b, 'b4' => $b, 'b5' => $b];
        }
        if (($f['status'] ?? 'ativos') !== 'todos') {
            $where .= ' AND v.ativo = :ativo';
            $params['ativo'] = ($f['status'] ?? 'ativos') === 'inativos' ? 0 : 1;
        }

        $p->total = (int) $this->valor("SELECT COUNT(*) FROM veiculos v JOIN clientes c ON c.oficina_id = v.oficina_id AND c.id = v.cliente_id
                                         LEFT JOIN modelos_veiculos m ON m.id = v.modelo_id WHERE {$where}", $params);

        return $this->lista(self::SELECT . " WHERE {$where} ORDER BY v.placa_normalizada" . $p->sql(), $params);
    }

    public function buscar(int $id): ?array
    {
        return $this->linha(self::SELECT . ' WHERE v.id = :id AND v.oficina_id = :oficina', ['id' => $id]);
    }

    public function salvar(array $d, ?int $id = null): int
    {
        $placa = self::normalizarPlaca((string) $d['placa']);
        $dados = [
            'cliente_id'        => (int) $d['cliente_id'],
            'modelo_id'         => !empty($d['modelo_id']) ? (int) $d['modelo_id'] : null,
            'modelo_texto'      => empty($d['modelo_id']) ? (trim((string) ($d['modelo_texto'] ?? '')) ?: null) : null,
            'placa'             => self::placaFmt($placa),
            'placa_normalizada' => $placa,
            'ano'               => !empty($d['ano']) ? (int) $d['ano'] : null,
            'cor'               => trim((string) ($d['cor'] ?? '')) ?: null,
            'combustivel'       => trim((string) ($d['combustivel'] ?? '')) ?: null,
            'observacoes'       => trim((string) ($d['observacoes'] ?? '')) ?: null,
        ];

        if ($id !== null && !$this->buscar($id)) {
            throw new ErroHttp('registro-inexistente');
        }
        if ($id === null) {
            return $this->inserir('veiculos', $dados);
        }
        $this->atualizar('veiculos', $id, $dados);

        return $id;
    }

    public function definirAtivo(int $id, bool $ativo): void
    {
        $this->atualizar('veiculos', $id, ['ativo' => $ativo ? 1 : 0]);
    }

    public function remover(int $id): bool
    {
        return $this->apagar('veiculos', $id);
    }

    /** Histórico do veículo: cada OS com o que foi feito. */
    public function historico(int $id): array
    {
        $os = $this->lista(
            'SELECT id, numero, status, km_veiculo, defeito_relatado, valor_total, criado_em, entregue_em
             FROM ordens_servico WHERE oficina_id = :oficina AND veiculo_id = :id ORDER BY criado_em DESC',
            ['id' => $id]
        );
        if (!$os) {
            return [];
        }

        $itens = [];
        foreach ($this->lista(
            "SELECT i.ordem_servico_id, i.tipo, i.descricao, i.quantidade FROM ordem_servico_itens i
             JOIN ordens_servico os ON os.oficina_id = i.oficina_id AND os.id = i.ordem_servico_id
             WHERE i.oficina_id = :oficina AND os.veiculo_id = :id AND i.estoque_status <> 'DEVOLVIDO' ORDER BY i.tipo DESC, i.id",
            ['id' => $id]
        ) as $i) {
            $itens[$i['ordem_servico_id']][] = $i;
        }
        foreach ($os as &$o) {
            $o['itens'] = $itens[$o['id']] ?? [];
        }

        return $os;
    }
}
