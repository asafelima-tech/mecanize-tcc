<?php

final class Cliente extends Modelo
{
    protected array $mensagensUnicas = [
        'uq_clientes_documento' => 'Já existe um cliente com este CPF/CNPJ nesta oficina.',
    ];

    public const CAMPOS = ['tipo_pessoa', 'nome', 'documento', 'email', 'telefone', 'endereco', 'bairro', 'cidade', 'uf', 'cep', 'aceita_contato', 'observacoes'];

    /** @return array<int,array> */
    public function listar(array $f, Paginacao $p): array
    {
        [$where, $params] = $this->filtros($f);
        $p->total = (int) $this->valor("SELECT COUNT(*) FROM clientes c WHERE {$where}", $params);

        return $this->lista(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM veiculos v WHERE v.oficina_id = c.oficina_id AND v.cliente_id = c.id) AS veiculos,
                    (SELECT MAX(os.criado_em) FROM ordens_servico os WHERE os.oficina_id = c.oficina_id AND os.cliente_id = c.id) AS ultima_os
             FROM clientes c WHERE {$where} ORDER BY c.nome" . $p->sql(),
            $params
        );
    }

    private function filtros(array $f): array
    {
        $where  = 'c.oficina_id = :oficina';
        $params = [];

        if (($f['busca'] ?? '') !== '') {
            $where .= ' AND (c.nome LIKE :b1 OR c.email LIKE :b2 OR c.telefone LIKE :b3 OR c.documento LIKE :b4)';
            $b = '%' . $f['busca'] . '%';
            $d = so_digitos($f['busca']);
            $params += ['b1' => $b, 'b2' => $b, 'b3' => '%' . ($d !== '' ? $d : $f['busca']) . '%', 'b4' => '%' . ($d !== '' ? $d : $f['busca']) . '%'];
        }
        if (($f['status'] ?? 'ativos') !== 'todos') {
            $where .= ' AND c.ativo = :ativo';
            $params['ativo'] = ($f['status'] ?? 'ativos') === 'inativos' ? 0 : 1;
        }

        return [$where, $params];
    }

    public function buscar(int $id): ?array
    {
        return $this->linha('SELECT * FROM clientes WHERE id = :id AND oficina_id = :oficina', ['id' => $id]);
    }

    /** Clientes ativos para os campos de escolha (veículo, OS, agenda). */
    public function opcoes(): array
    {
        return $this->lista('SELECT id, nome, documento FROM clientes WHERE oficina_id = :oficina AND ativo = 1 ORDER BY nome');
    }

    public function salvar(array $dados, ?int $id = null): int
    {
        $dados = array_intersect_key($dados, array_flip(self::CAMPOS));
        $dados['nome'] = trim($dados['nome']);
        foreach (['documento', 'telefone', 'cep'] as $c) {
            $dados[$c] = so_digitos($dados[$c] ?? '') ?: null;
        }
        foreach (['email', 'endereco', 'bairro', 'cidade', 'observacoes'] as $c) {
            $dados[$c] = trim((string) ($dados[$c] ?? '')) ?: null;
        }
        $dados['email'] = $dados['email'] ? mb_strtolower($dados['email']) : null;
        $dados['uf'] = strtoupper(trim((string) ($dados['uf'] ?? ''))) ?: null;
        $dados['aceita_contato'] = empty($dados['aceita_contato']) ? 0 : 1;

        if ($id !== null && !$this->buscar($id)) {
            throw new ErroHttp('registro-inexistente');
        }
        if ($id === null) {
            return $this->inserir('clientes', $dados);
        }
        $this->atualizar('clientes', $id, $dados);

        return $id;
    }

    public function definirAtivo(int $id, bool $ativo): void
    {
        $this->atualizar('clientes', $id, ['ativo' => $ativo ? 1 : 0]);
    }

    public function remover(int $id): bool
    {
        return $this->apagar('clientes', $id);
    }

    public function veiculos(int $id): array
    {
        return $this->lista(
            "SELECT v.*, COALESCE(CONCAT(m.marca, ' ', m.nome), v.modelo_texto) AS modelo
             FROM veiculos v LEFT JOIN modelos_veiculos m ON m.id = v.modelo_id
             WHERE v.oficina_id = :oficina AND v.cliente_id = :id ORDER BY v.ativo DESC, v.placa",
            ['id' => $id]
        );
    }

    /** Histórico de serviços do cliente (todas as OS, de todos os veículos). */
    public function historico(int $id): array
    {
        return $this->lista(
            'SELECT os.id, os.numero, os.status, os.valor_total, os.criado_em, os.entregue_em, v.placa
             FROM ordens_servico os JOIN veiculos v ON v.oficina_id = os.oficina_id AND v.id = os.veiculo_id
             WHERE os.oficina_id = :oficina AND os.cliente_id = :id ORDER BY os.criado_em DESC',
            ['id' => $id]
        );
    }
}
