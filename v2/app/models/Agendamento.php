<?php

/**
 * Agenda da oficina: horários marcados com cliente, veículo e técnico.
 *
 * Regras herdadas da v1:
 *  - conflito de horário: com técnico, ele não pode ter dois serviços sobrepostos; sem técnico, o
 *    horário não pode se sobrepor a nenhum outro agendamento ativo (agendado ou confirmado);
 *  - "Gerar OS" leva à tela de novo orçamento já preenchida; quando a OS é criada, o agendamento
 *    fica CONCLUIDO e guarda a OS (uma vez só — o segundo clique abre a OS que já existe);
 *  - excluir é só do ADMIN; a equipe cancela.
 */
final class Agendamento extends Modelo
{
    public const SITUACOES = [
        'AGENDADO'   => ['Agendado', 'info'],
        'CONFIRMADO' => ['Confirmado', 'success'],
        'CONCLUIDO'  => ['Atendido', 'neutral'],
        'CANCELADO'  => ['Cancelado', 'danger'],
        'FALTOU'     => ['Não compareceu', 'warning'],
    ];

    public const ATIVOS = ['AGENDADO', 'CONFIRMADO'];

    /** Situações que a equipe pode marcar pelos botões rápidos. */
    public const MARCAVEIS = ['AGENDADO', 'CONFIRMADO', 'CANCELADO', 'FALTOU'];

    public function porPeriodo(string $inicio, string $fim, array $f = []): array
    {
        $where = ['a.oficina_id = :oficina', 'a.data_hora >= :ini', 'a.data_hora < :fim'];
        $params = ['ini' => $inicio . ' 00:00:00', 'fim' => $fim . ' 00:00:00'];

        if (!empty($f['status']) && isset(self::SITUACOES[$f['status']])) {
            $where[] = 'a.status = :status';
            $params['status'] = $f['status'];
        }
        if (!empty($f['tecnico'])) {
            $where[] = 'a.tecnico_id = :tecnico';
            $params['tecnico'] = (int) $f['tecnico'];
        }

        return $this->lista(
            'SELECT a.*, c.nome AS cliente, c.telefone AS cliente_telefone, v.placa, u.nome AS tecnico, os.numero AS os_numero
             FROM agendamentos a
             JOIN clientes c ON c.oficina_id = a.oficina_id AND c.id = a.cliente_id
             LEFT JOIN veiculos v ON v.oficina_id = a.oficina_id AND v.id = a.veiculo_id
             LEFT JOIN usuarios u ON u.oficina_id = a.oficina_id AND u.id = a.tecnico_id
             LEFT JOIN ordens_servico os ON os.oficina_id = a.oficina_id AND os.id = a.ordem_servico_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY a.data_hora, a.id',
            $params
        );
    }

    /** Contagem por situação no período (para o topo da agenda). */
    public function resumo(string $inicio, string $fim): array
    {
        $r = array_fill_keys(array_keys(self::SITUACOES), 0);
        foreach ($this->lista('SELECT status, COUNT(*) AS n FROM agendamentos
                               WHERE oficina_id = :oficina AND data_hora >= :ini AND data_hora < :fim GROUP BY status',
                     ['ini' => $inicio . ' 00:00:00', 'fim' => $fim . ' 00:00:00']) as $l) {
            $r[$l['status']] = (int) $l['n'];
        }

        return $r;
    }

    /** Próximos horários ativos a partir de agora (tela inicial). */
    public function proximos(int $limite = 6): array
    {
        $limite = max(1, min(30, $limite));

        return $this->lista(
            "SELECT a.id, a.data_hora, a.servico, a.status, c.nome AS cliente, v.placa, u.nome AS tecnico
             FROM agendamentos a
             JOIN clientes c ON c.oficina_id = a.oficina_id AND c.id = a.cliente_id
             LEFT JOIN veiculos v ON v.oficina_id = a.oficina_id AND v.id = a.veiculo_id
             LEFT JOIN usuarios u ON u.oficina_id = a.oficina_id AND u.id = a.tecnico_id
             WHERE a.oficina_id = :oficina AND a.status IN ('AGENDADO','CONFIRMADO') AND a.data_hora >= NOW() - INTERVAL 1 HOUR
             ORDER BY a.data_hora LIMIT {$limite}"
        );
    }

    public function buscar(int $id): ?array
    {
        return $this->linha('SELECT * FROM agendamentos WHERE id = :id AND oficina_id = :oficina', ['id' => $id]);
    }

    /** Cria ou edita. Devolve o id. */
    public function salvar(array $d, ?int $id = null): int
    {
        $dados = $this->validar($d);

        return Transacao::executar($this->db, function () use ($dados, $id) {
            if ($id !== null) {
                $atual = $this->buscar($id) ?? throw new ErroHttp('registro-inexistente', ['voltar' => './agenda']);
                if (!in_array($atual['status'], self::ATIVOS, true)) {
                    throw new DomainException('Só dá para editar um agendamento que ainda está agendado ou confirmado.');
                }
            }

            if ($this->temConflito($dados['data_hora'], $dados['duracao_min'], $dados['tecnico_id'], $id)) {
                throw new DomainException($dados['tecnico_id']
                    ? 'Esse técnico já tem outro serviço marcado nesse horário.'
                    : 'Já existe outro agendamento nesse horário.');
            }

            if ($id === null) {
                return $this->inserir('agendamentos', $dados + ['status' => 'AGENDADO', 'criado_por' => Contexto::usuarioId()]);
            }

            $this->atualizar('agendamentos', $id, $dados);

            return $id;
        });
    }

    public function temConflito(string $dataHora, int $duracao, ?int $tecnicoId, ?int $ignorarId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM agendamentos
                WHERE oficina_id = :oficina AND status IN ('AGENDADO','CONFIRMADO')
                  AND :inicio < DATE_ADD(data_hora, INTERVAL duracao_min MINUTE)
                  AND data_hora < DATE_ADD(:inicio, INTERVAL :duracao MINUTE)";
        $params = ['inicio' => $dataHora, 'duracao' => $duracao];

        if ($tecnicoId) {
            $sql .= ' AND tecnico_id = :tecnico';
            $params['tecnico'] = $tecnicoId;
        }
        if ($ignorarId) {
            $sql .= ' AND id <> :ignorar';
            $params['ignorar'] = $ignorarId;
        }

        return (int) $this->valor($sql, $params) > 0;
    }

    public function marcar(int $id, string $situacao): void
    {
        if (!in_array($situacao, self::MARCAVEIS, true)) {
            throw new DomainException('Situação inválida.');
        }
        $a = $this->buscar($id) ?? throw new ErroHttp('registro-inexistente', ['voltar' => './agenda']);
        if ($a['ordem_servico_id']) {
            throw new DomainException('Este agendamento já virou ordem de serviço.');
        }
        if ($a['status'] === $situacao) {
            return;
        }
        // Voltar a valer (cancelado/faltou → agendado) só se o horário ainda estiver livre.
        if (in_array($situacao, self::ATIVOS, true) && !in_array($a['status'], self::ATIVOS, true)
            && $this->temConflito($a['data_hora'], (int) $a['duracao_min'], $a['tecnico_id'] ? (int) $a['tecnico_id'] : null, $id)) {
            throw new DomainException('O horário já foi ocupado por outro agendamento.');
        }

        $this->atualizar('agendamentos', $id, ['status' => $situacao]);
    }

    public function remover(int $id): bool
    {
        $a = $this->buscar($id);
        if ($a && $a['ordem_servico_id']) {
            throw new DomainException('Este agendamento já virou ordem de serviço e fica no histórico. Use "cancelar" se for o caso.');
        }

        return $this->apagar('agendamentos', $id);
    }

    /**
     * Liga a OS recém-criada ao agendamento. "ordem_servico_id IS NULL" garante uma OS só por
     * agendamento mesmo com clique duplo. Devolve false se o agendamento não podia ser vinculado.
     */
    public function vincularOs(int $id, int $osId, int $clienteId): bool
    {
        return $this->gravar(
            "UPDATE agendamentos SET ordem_servico_id = :os, status = 'CONCLUIDO'
             WHERE id = :id AND oficina_id = :oficina AND ordem_servico_id IS NULL AND cliente_id = :cliente
               AND status IN ('AGENDADO','CONFIRMADO')",
            ['os' => $osId, 'id' => $id, 'cliente' => $clienteId]
        ) > 0;
    }

    private function validar(array $d): array
    {
        $clienteId = (int) ($d['cliente_id'] ?? 0);
        $veiculoId = (int) ($d['veiculo_id'] ?? 0) ?: null;
        $tecnicoId = (int) ($d['tecnico_id'] ?? 0) ?: null;
        $data = (string) ($d['data'] ?? '');
        $hora = (string) ($d['hora'] ?? '');
        $servico = trim((string) ($d['servico'] ?? ''));
        $duracao = (int) ($d['duracao_min'] ?? 60);

        if (!$clienteId || !$this->valor('SELECT COUNT(*) FROM clientes WHERE id = :id AND oficina_id = :oficina', ['id' => $clienteId])) {
            throw new DomainException('Escolha o cliente.');
        }
        if ($veiculoId && !$this->valor('SELECT COUNT(*) FROM veiculos WHERE id = :id AND cliente_id = :c AND oficina_id = :oficina', ['id' => $veiculoId, 'c' => $clienteId])) {
            throw new DomainException('Escolha um veículo deste cliente.');
        }
        if ($tecnicoId && !$this->valor("SELECT COUNT(*) FROM usuarios WHERE id = :id AND oficina_id = :oficina AND ativo = 1", ['id' => $tecnicoId])) {
            throw new DomainException('Técnico inválido.');
        }
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $data . ' ' . $hora);
        if (!$dt || $dt->format('Y-m-d H:i') !== $data . ' ' . $hora) {
            throw new DomainException('Informe uma data e hora válidas.');
        }
        if ($servico === '') {
            throw new DomainException('Descreva o serviço.');
        }
        if ($duracao < 15 || $duracao > 600) {
            throw new DomainException('A duração deve ficar entre 15 minutos e 10 horas.');
        }

        return [
            'cliente_id'  => $clienteId,
            'veiculo_id'  => $veiculoId,
            'tecnico_id'  => $tecnicoId,
            'data_hora'   => $dt->format('Y-m-d H:i:s'),
            'duracao_min' => $duracao,
            'servico'     => mb_substr($servico, 0, 200),
            'observacoes' => trim((string) ($d['observacoes'] ?? '')) ?: null,
        ];
    }
}
