<?php

/**
 * Agenda de serviços: horários marcados antes de virarem Ordem de Serviço.
 *
 * Ciclo:  AGENDADO → CONFIRMADO → CONCLUIDO (ao gerar a OS)
 *                              ↘ CANCELADO / FALTOU
 *
 * A conversão em OS não é feita aqui: o agendamento só guarda o vínculo
 * (ordem_servico_id) depois que a OS já existe, para a agenda não duplicar
 * a lógica de criação de OS que já mora em OrdemServico.
 */
class Agendamento
{
    public $conn;

    /** Status aceitos (case do enum da coluna). */
    public const STATUS = ['AGENDADO', 'CONFIRMADO', 'CONCLUIDO', 'CANCELADO', 'FALTOU'];

    /** Status que ainda ocupam a agenda — os únicos que geram conflito de horário. */
    public const STATUS_ATIVOS = ['AGENDADO', 'CONFIRMADO'];

    /** Durações oferecidas no formulário, em minutos. */
    public const DURACOES = [30, 60, 90, 120, 180, 240, 480];

    public function __construct()
    {
        $this->conn = new Database();
    }

    /**
     * Agendamentos de um intervalo de datas, com cliente, veículo e mecânico.
     * $inicio/$fim: 'YYYY-MM-DD' (ambos inclusivos).
     */
    public function porPeriodo($inicio, $fim, array $filtros = [])
    {
        $sql = "SELECT a.*,
                       c.nome AS cliente_nome, c.telefone AS cliente_telefone, c.email AS cliente_email,
                       v.placa AS veiculo_placa,
                       mv.nome AS veiculo_marca, mo.nome AS veiculo_modelo,
                       u.nome AS mecanico_nome,
                       os.numero AS os_numero
                FROM agendamentos a
                INNER JOIN clientes c        ON c.id_cliente = a.cliente_id
                LEFT  JOIN veiculos v        ON v.id_veiculo = a.veiculo_id
                LEFT  JOIN marcas_veiculos mv  ON mv.id_marca  = v.marca_id
                LEFT  JOIN modelos_veiculos mo ON mo.id_modelo = v.modelo_id
                LEFT  JOIN usuarios u        ON u.id_usuario = a.mecanico_id
                LEFT  JOIN ordens_servico os ON os.id_ordem_servico = a.ordem_servico_id
                WHERE a.data_hora BETWEEN :inicio AND :fim";

        $params = [':inicio' => $inicio . ' 00:00:00', ':fim' => $fim . ' 23:59:59'];

        // Whitelist: o valor vai comparado por placeholder, mas só aceitamos
        // status conhecido pra um filtro inválido não devolver a agenda inteira.
        if (!empty($filtros['status']) && in_array($filtros['status'], self::STATUS, true)) {
            $sql .= " AND a.status = :status";
            $params[':status'] = $filtros['status'];
        }

        if (!empty($filtros['mecanico_id'])) {
            $sql .= " AND a.mecanico_id = :mecanico";
            $params[':mecanico'] = (int) $filtros['mecanico_id'];
        }

        $sql .= " ORDER BY a.data_hora ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $sql = "SELECT a.*,
                       c.nome AS cliente_nome, c.telefone AS cliente_telefone, c.email AS cliente_email,
                       v.placa AS veiculo_placa,
                       mv.nome AS veiculo_marca, mo.nome AS veiculo_modelo,
                       u.nome AS mecanico_nome,
                       os.numero AS os_numero
                FROM agendamentos a
                INNER JOIN clientes c        ON c.id_cliente = a.cliente_id
                LEFT  JOIN veiculos v        ON v.id_veiculo = a.veiculo_id
                LEFT  JOIN marcas_veiculos mv  ON mv.id_marca  = v.marca_id
                LEFT  JOIN modelos_veiculos mo ON mo.id_modelo = v.modelo_id
                LEFT  JOIN usuarios u        ON u.id_usuario = a.mecanico_id
                LEFT  JOIN ordens_servico os ON os.id_ordem_servico = a.ordem_servico_id
                WHERE a.id_agendamento = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => (int) $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Existe outro agendamento ativo que se sobrepõe a este horário?
     *
     * Dois intervalos [inicioA, fimA) e [inicioB, fimB) se sobrepõem quando
     * inicioA < fimB E inicioB < fimA. Encostar não conta como conflito: um
     * serviço que acaba 10:00 e outro que começa 10:00 convivem.
     *
     * $mecanicoId nulo = conflito de oficina (qualquer horário ocupado);
     * com mecânico, o conflito é só na agenda daquele mecânico.
     * $ignorarId serve para a edição não conflitar consigo mesma.
     */
    public function temConflito($dataHora, $duracaoMin, $mecanicoId = null, $ignorarId = null)
    {
        $sql = "SELECT COUNT(*)
                FROM agendamentos
                WHERE status IN ('" . implode("','", self::STATUS_ATIVOS) . "')
                  AND :inicio < DATE_ADD(data_hora, INTERVAL duracao_min MINUTE)
                  AND data_hora < DATE_ADD(:inicio2, INTERVAL :duracao MINUTE)";

        $params = [
            ':inicio'  => $dataHora,
            ':inicio2' => $dataHora,
            ':duracao' => (int) $duracaoMin,
        ];

        if ($mecanicoId) {
            $sql .= " AND mecanico_id = :mecanico";
            $params[':mecanico'] = (int) $mecanicoId;
        }

        if ($ignorarId) {
            $sql .= " AND id_agendamento <> :ignorar";
            $params[':ignorar'] = (int) $ignorarId;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function criar(array $dados)
    {
        $sql = "INSERT INTO agendamentos
                    (cliente_id, veiculo_id, mecanico_id, data_hora, duracao_min,
                     servico, observacoes, status, usuario_criacao_id)
                VALUES
                    (:cliente, :veiculo, :mecanico, :data_hora, :duracao,
                     :servico, :obs, :status, :usuario)";

        $stmt = $this->conn->prepare($sql);

        $ok = $stmt->execute([
            ':cliente'   => (int) $dados['cliente_id'],
            ':veiculo'   => !empty($dados['veiculo_id'])  ? (int) $dados['veiculo_id']  : null,
            ':mecanico'  => !empty($dados['mecanico_id']) ? (int) $dados['mecanico_id'] : null,
            ':data_hora' => $dados['data_hora'],
            ':duracao'   => (int) ($dados['duracao_min'] ?? 60),
            ':servico'   => trim($dados['servico']),
            ':obs'       => $this->ouNulo($dados['observacoes'] ?? null),
            ':status'    => in_array($dados['status'] ?? '', self::STATUS, true) ? $dados['status'] : 'AGENDADO',
            ':usuario'   => !empty($dados['usuario_criacao_id']) ? (int) $dados['usuario_criacao_id'] : null,
        ]);

        return $ok ? (int) $this->conn->lastInsertId() : false;
    }

    public function atualizar($id, array $dados)
    {
        $sql = "UPDATE agendamentos SET
                    cliente_id  = :cliente,
                    veiculo_id  = :veiculo,
                    mecanico_id = :mecanico,
                    data_hora   = :data_hora,
                    duracao_min = :duracao,
                    servico     = :servico,
                    observacoes = :obs,
                    status      = :status
                WHERE id_agendamento = :id";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ':cliente'   => (int) $dados['cliente_id'],
            ':veiculo'   => !empty($dados['veiculo_id'])  ? (int) $dados['veiculo_id']  : null,
            ':mecanico'  => !empty($dados['mecanico_id']) ? (int) $dados['mecanico_id'] : null,
            ':data_hora' => $dados['data_hora'],
            ':duracao'   => (int) ($dados['duracao_min'] ?? 60),
            ':servico'   => trim($dados['servico']),
            ':obs'       => $this->ouNulo($dados['observacoes'] ?? null),
            ':status'    => in_array($dados['status'] ?? '', self::STATUS, true) ? $dados['status'] : 'AGENDADO',
            ':id'        => (int) $id,
        ]);
    }

    /**
     * Troca só o status (usado pelos botões rápidos de confirmar/cancelar/faltou).
     * Rejeita status desconhecido em vez de gravar lixo no enum.
     */
    public function atualizarStatus($id, $status)
    {
        if (!in_array($status, self::STATUS, true)) {
            return false;
        }

        $stmt = $this->conn->prepare(
            "UPDATE agendamentos SET status = :status WHERE id_agendamento = :id"
        );

        return $stmt->execute([':status' => $status, ':id' => (int) $id]);
    }

    /**
     * Marca o agendamento como convertido, guardando a OS gerada.
     * Só converte quem ainda não foi convertido — a cláusula
     * `ordem_servico_id IS NULL` é o que impede duas OS pro mesmo horário
     * quando o botão é clicado duas vezes.
     */
    public function vincularOrdemServico($id, $ordemServicoId)
    {
        $stmt = $this->conn->prepare("
            UPDATE agendamentos
            SET ordem_servico_id = :os, status = 'CONCLUIDO'
            WHERE id_agendamento = :id AND ordem_servico_id IS NULL
        ");

        $stmt->execute([':os' => (int) $ordemServicoId, ':id' => (int) $id]);

        return $stmt->rowCount() > 0;
    }

    public function excluir($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM agendamentos WHERE id_agendamento = :id");

        return $stmt->execute([':id' => (int) $id]);
    }

    /**
     * Contadores do topo da agenda, para o período exibido.
     */
    public function resumoPeriodo($inicio, $fim)
    {
        $stmt = $this->conn->prepare("
            SELECT status, COUNT(*) AS qtd
            FROM agendamentos
            WHERE data_hora BETWEEN :inicio AND :fim
            GROUP BY status
        ");
        $stmt->execute([':inicio' => $inicio . ' 00:00:00', ':fim' => $fim . ' 23:59:59']);

        $resumo = array_fill_keys(self::STATUS, 0);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $resumo[$row['status']] = (int) $row['qtd'];
        }

        $resumo['TOTAL'] = array_sum($resumo);

        return $resumo;
    }

    /**
     * Próximos agendamentos ativos a partir de agora — usado no painel/dashboard.
     */
    public function proximos($limite = 5)
    {
        $limite = (int) $limite;

        $sql = "SELECT a.*, c.nome AS cliente_nome, v.placa AS veiculo_placa
                FROM agendamentos a
                INNER JOIN clientes c ON c.id_cliente = a.cliente_id
                LEFT  JOIN veiculos v ON v.id_veiculo = a.veiculo_id
                WHERE a.data_hora >= NOW()
                  AND a.status IN ('" . implode("','", self::STATUS_ATIVOS) . "')
                ORDER BY a.data_hora ASC
                LIMIT {$limite}";

        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Clientes/veículos/mecânicos para os selects do formulário.
     */
    public function getDadosFormulario()
    {
        return [
            'clientes' => $this->conn->query(
                "SELECT id_cliente, nome FROM clientes WHERE ativo = 1 ORDER BY nome"
            )->fetchAll(PDO::FETCH_ASSOC),

            'veiculos' => $this->conn->query(
                "SELECT v.id_veiculo, v.cliente_id, v.placa,
                        mv.nome AS marca, mo.nome AS modelo
                 FROM veiculos v
                 LEFT JOIN marcas_veiculos  mv ON mv.id_marca  = v.marca_id
                 LEFT JOIN modelos_veiculos mo ON mo.id_modelo = v.modelo_id
                 ORDER BY v.placa"
            )->fetchAll(PDO::FETCH_ASSOC),

            'mecanicos' => $this->conn->query(
                "SELECT id_usuario, nome FROM usuarios
                 WHERE ativo = 1 AND tipo IN ('TECNICO','ADMIN') ORDER BY nome"
            )->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    private function ouNulo($valor)
    {
        return ($valor !== null && trim((string) $valor) !== '') ? trim((string) $valor) : null;
    }
}
