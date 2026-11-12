<?php

final class Fornecedor extends Modelo
{
    protected array $mensagensUnicas = [
        'uq_fornecedores_documento' => 'Já existe um fornecedor com este CPF/CNPJ.',
    ];

    public function listar(array $f, Paginacao $p): array
    {
        $where  = 'f.oficina_id = :oficina';
        $params = [];
        if (($f['busca'] ?? '') !== '') {
            $where .= ' AND (f.nome LIKE :b1 OR f.contato LIKE :b2 OR f.documento LIKE :b3)';
            $b = '%' . $f['busca'] . '%';
            $params = ['b1' => $b, 'b2' => $b, 'b3' => '%' . (so_digitos($f['busca']) ?: $f['busca']) . '%'];
        }
        if (($f['status'] ?? 'ativos') !== 'todos') {
            $where .= ' AND f.ativo = :ativo';
            $params['ativo'] = ($f['status'] ?? 'ativos') === 'inativos' ? 0 : 1;
        }

        $p->total = (int) $this->valor("SELECT COUNT(*) FROM fornecedores f WHERE {$where}", $params);

        return $this->lista(
            "SELECT f.*, (SELECT MAX(m.criado_em) FROM movimentacoes_estoque m
                           WHERE m.oficina_id = f.oficina_id AND m.fornecedor_id = f.id AND m.motivo = 'COMPRA') AS ultima_compra
             FROM fornecedores f WHERE {$where} ORDER BY f.nome" . $p->sql(),
            $params
        );
    }

    /**
     * Números do topo: fornecedores ativos e inativos, o que entrou de mercadoria no mês (pelo custo
     * da nota, comparado ao mesmo trecho do mês anterior) e as contas a pagar a fornecedores em aberto.
     */
    public function resumo(): array
    {
        $l = $this->linha('SELECT SUM(ativo = 1) AS ativos, SUM(ativo = 0) AS inativos FROM fornecedores WHERE oficina_id = :oficina');
        $compras = $this->linha(
            "SELECT COALESCE(SUM(CASE WHEN criado_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN delta * COALESCE(custo_unitario, 0) END), 0) AS mes,
                    COUNT(DISTINCT CASE WHEN criado_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN CONCAT(COALESCE(fornecedor_id, 0), '|', COALESCE(documento, id)) END) AS notas,
                    COALESCE(SUM(CASE WHEN criado_em >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01')
                                       AND criado_em < DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01') + INTERVAL DAY(CURDATE()) DAY
                                      THEN delta * COALESCE(custo_unitario, 0) END), 0) AS anterior
             FROM movimentacoes_estoque m
             WHERE oficina_id = :oficina AND motivo = 'COMPRA' AND criado_em >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01')
               AND NOT EXISTS (SELECT 1 FROM movimentacoes_estoque e WHERE e.oficina_id = m.oficina_id AND e.estorno_de_id = m.id)"
        );
        $pagar = $this->linha(
            "SELECT COUNT(*) AS n, COALESCE(SUM(valor - desconto + acrescimo - valor_pago), 0) AS saldo
             FROM lancamentos WHERE oficina_id = :oficina AND tipo = 'PAGAR' AND fornecedor_id IS NOT NULL AND status IN ('ABERTO','PARCIAL')"
        );

        return ['ativos' => (int) $l['ativos'], 'inativos' => (int) $l['inativos'], 'compras_mes' => (float) $compras['mes'],
                'notas_mes' => (int) $compras['notas'], 'compras_anterior' => (float) $compras['anterior'],
                'pagar_n' => (int) $pagar['n'], 'pagar_saldo' => (float) $pagar['saldo']];
    }

    /** Últimas entradas de mercadoria (uma por nota), com o fornecedor, a quantidade de itens e o total. */
    public function ultimasEntradas(int $limite = 3): array
    {
        $limite = max(1, min(20, $limite));

        return $this->lista(
            "SELECT m.fornecedor_id, f.nome AS fornecedor, m.documento, MAX(m.criado_em) AS criado_em, COUNT(*) AS itens,
                    SUM(m.delta * COALESCE(m.custo_unitario, 0)) AS total, MAX(u.nome) AS usuario
             FROM movimentacoes_estoque m
             LEFT JOIN fornecedores f ON f.oficina_id = m.oficina_id AND f.id = m.fornecedor_id
             LEFT JOIN usuarios u ON u.id = m.usuario_id
             WHERE m.oficina_id = :oficina AND m.motivo = 'COMPRA'
             GROUP BY m.fornecedor_id, f.nome, m.documento, DATE(m.criado_em)
             ORDER BY criado_em DESC LIMIT {$limite}"
        );
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
