<?php

final class Fornecedor extends Modelo
{
    protected array $mensagensUnicas = [
        'uq_fornecedores_documento' => 'Já existe um fornecedor com este CPF/CNPJ.',
    ];

    public function listar(array $f, Paginacao $p): array
    {
        $where  = 'oficina_id = :oficina';
        $params = [];
        if (($f['busca'] ?? '') !== '') {
            $where .= ' AND (nome LIKE :b1 OR contato LIKE :b2 OR documento LIKE :b3)';
            $b = '%' . $f['busca'] . '%';
            $params = ['b1' => $b, 'b2' => $b, 'b3' => '%' . (so_digitos($f['busca']) ?: $f['busca']) . '%'];
        }
        if (($f['status'] ?? 'ativos') !== 'todos') {
            $where .= ' AND ativo = :ativo';
            $params['ativo'] = ($f['status'] ?? 'ativos') === 'inativos' ? 0 : 1;
        }

        $p->total = (int) $this->valor("SELECT COUNT(*) FROM fornecedores WHERE {$where}", $params);

        return $this->lista("SELECT * FROM fornecedores WHERE {$where} ORDER BY nome" . $p->sql(), $params);
    }

    public function buscar(int $id): ?array
    {
        return $this->linha('SELECT * FROM fornecedores WHERE id = :id AND oficina_id = :oficina', ['id' => $id]);
    }

    public function opcoes(): array
    {
        return $this->lista('SELECT id, nome FROM fornecedores WHERE oficina_id = :oficina AND ativo = 1 ORDER BY nome');
    }

    public function salvar(array $d, ?int $id = null): int
    {
        $dados = [
            'nome'        => trim($d['nome']),
            'documento'   => so_digitos($d['documento'] ?? '') ?: null,
            'contato'     => trim((string) ($d['contato'] ?? '')) ?: null,
            'telefone'    => so_digitos($d['telefone'] ?? '') ?: null,
            'email'       => mb_strtolower(trim((string) ($d['email'] ?? ''))) ?: null,
            'observacoes' => trim((string) ($d['observacoes'] ?? '')) ?: null,
        ];

        if ($id !== null && !$this->buscar($id)) {
            throw new ErroHttp('registro-inexistente');
        }
        if ($id === null) {
            return $this->inserir('fornecedores', $dados);
        }
        $this->atualizar('fornecedores', $id, $dados);

        return $id;
    }

    public function definirAtivo(int $id, bool $ativo): void
    {
        $this->atualizar('fornecedores', $id, ['ativo' => $ativo ? 1 : 0]);
    }

    public function remover(int $id): bool
    {
        return $this->apagar('fornecedores', $id);
    }
}
