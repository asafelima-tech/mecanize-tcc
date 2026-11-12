<?php

final class Veiculo extends Modelo
{
    protected array $mensagensUnicas = [
        'uq_veiculos_placa' => 'Já existe um veículo com esta placa nesta oficina.',
    ];

    public const COMBUSTIVEIS = ['Gasolina', 'Etanol', 'Flex (Álcool/Gasolina)', 'Diesel', 'GNV', 'Híbrido', 'Elétrico'];
    public const CORES = ['Branco', 'Branco Pérola', 'Preto', 'Prata', 'Cinza', 'Grafite', 'Chumbo', 'Vermelho', 'Vinho', 'Azul',
                          'Azul Marinho', 'Verde', 'Verde Escuro', 'Amarelo', 'Laranja', 'Marrom', 'Bege', 'Dourado', 'Bronze', 'Rosa', 'Roxo'];

    private const SELECT = "SELECT v.*, c.nome AS cliente, COALESCE(CONCAT(m.marca, ' ', m.nome), v.modelo_texto) AS modelo, m.marca,
                                   COALESCE(m.nome, v.modelo_texto) AS modelo_nome
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
        if (($f['marca'] ?? '') !== '') {
            $where .= ' AND m.marca = :marca';
            $params['marca'] = $f['marca'];
        }
        if (in_array($f['combustivel'] ?? '', self::COMBUSTIVEIS, true)) {
            $where .= ' AND v.combustivel = :combustivel';
            $params['combustivel'] = $f['combustivel'];
        }
        if (($f['novos'] ?? '') === 'mes') {
            $where .= " AND v.criado_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
        }

        $p->total = (int) $this->valor("SELECT COUNT(*) FROM veiculos v JOIN clientes c ON c.oficina_id = v.oficina_id AND c.id = v.cliente_id
                                         LEFT JOIN modelos_veiculos m ON m.id = v.modelo_id WHERE {$where}", $params);

        return $this->lista(self::SELECT . " WHERE {$where} ORDER BY v.placa_normalizada" . $p->sql(), $params);
    }

    /** Números do topo da lista: veículos ativos, novos no mês (e no mesmo trecho do mês anterior) e a marca mais comum. */
    public function resumo(): array
    {
        $l = $this->linha(
            "SELECT SUM(ativo = 1) AS ativos, SUM(ativo = 0) AS inativos,
                    SUM(criado_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS novos_mes,
                    SUM(criado_em >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01')
                        AND criado_em < DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01') + INTERVAL DAY(CURDATE()) DAY) AS novos_anterior
             FROM veiculos WHERE oficina_id = :oficina"
        );
        $marca = $this->linha(
            "SELECT m.marca, COUNT(*) AS n FROM veiculos v JOIN modelos_veiculos m ON m.id = v.modelo_id
             WHERE v.oficina_id = :oficina AND v.ativo = 1 GROUP BY m.marca ORDER BY n DESC, m.marca LIMIT 1"
        );

        return ['ativos' => (int) $l['ativos'], 'inativos' => (int) $l['inativos'], 'novos_mes' => (int) $l['novos_mes'],
                'novos_anterior' => (int) $l['novos_anterior'], 'marca' => $marca['marca'] ?? null, 'marca_n' => (int) ($marca['n'] ?? 0)];
    }

    /** Marcas (do catálogo) dos veículos da oficina, para o filtro. */
    public function marcas(): array
    {
        return array_column($this->lista(
            'SELECT DISTINCT m.marca FROM veiculos v JOIN modelos_veiculos m ON m.id = v.modelo_id WHERE v.oficina_id = :oficina ORDER BY m.marca'
        ), 'marca');
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
