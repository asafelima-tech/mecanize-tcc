<?php

/**
 * Trilha de auditoria: só INSERT, nunca UPDATE/DELETE. Cada registro aponta para UM dono
 * (OS, lançamento, cliente, veículo, produto ou caixa) com chave estrangeira de verdade — o banco
 * impede registro órfão e impede apagar o dono enquanto houver histórico.
 */
final class Historico extends Modelo
{
    private const DONOS = ['ordem_servico_id', 'lancamento_id', 'cliente_id', 'veiculo_id', 'produto_id', 'caixa_id'];

    /**
     * @param array $dono ex.: ['ordem_servico_id' => 12]
     * @param array|null $dados antes/depois, cópia de itens etc. (vai como JSON)
     */
    public function registrar(array $dono, string $acao, ?string $anterior = null, ?string $novo = null, ?string $motivo = null, ?array $dados = null): void
    {
        $dono = array_intersect_key($dono, array_flip(self::DONOS));
        if (count($dono) > 1) {
            throw new LogicException('Histórico com mais de um dono.');
        }

        $this->inserir('historico', $dono + [
            'acao'            => $acao,
            'estado_anterior' => $anterior,
            'estado_novo'     => $novo,
            'motivo'          => $motivo !== null ? mb_substr($motivo, 0, 255) : null,
            'dados'           => $dados !== null ? json_encode($dados, JSON_UNESCAPED_UNICODE) : null,
            'autor_id'        => Contexto::usuarioId(),
        ]);
    }

    public function daOs(int $osId): array
    {
        return $this->lista(
            'SELECT h.*, u.nome AS autor FROM historico h LEFT JOIN usuarios u ON u.id = h.autor_id
             WHERE h.oficina_id = :oficina AND h.ordem_servico_id = :os ORDER BY h.criado_em DESC, h.id DESC',
            ['os' => $osId]
        );
    }

    public function daConta(int $lancamentoId): array
    {
        return $this->lista(
            'SELECT h.*, u.nome AS autor FROM historico h LEFT JOIN usuarios u ON u.id = h.autor_id
             WHERE h.oficina_id = :oficina AND h.lancamento_id = :l ORDER BY h.criado_em DESC, h.id DESC',
            ['l' => $lancamentoId]
        );
    }
}
