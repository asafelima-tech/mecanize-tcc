<?php

require_once __DIR__ . '/../core/Transacao.php';
require_once __DIR__ . '/../core/Historico.php';

/**
 * Fila operacional: o que a oficina deve atacar agora, em que ordem.
 *
 * NÃO é uma fila cronológica nem um semáforo. Ela ORIENTA: ordena por prioridade (urgência
 * declarada e validada), tempo de espera, serviço rápido e disponibilidade das peças — e
 * mostra quem está livre. Mas não impede ninguém: um técnico livre pode pegar qualquer item,
 * inclusive um problema simples que chegou depois (o caso do cliente que "só precisa trocar
 * uma lâmpada"). Só a ordem MANUAL do administrador (com justificativa) sobrepõe a pontuação.
 *
 * Reúne duas fontes numa lista única:
 *   ocorrências abertas (ainda sem OS)   e   ordens de serviço em andamento.
 */
class FilaOperacional
{
    /** Quantos serviços um técnico acompanha ao mesmo tempo antes de ser considerado "ocupado". */
    public const CAPACIDADE_TECNICO = 2;

    private const PESO_PRIORIDADE = ['URGENTE' => 1000, 'ALTA' => 400, 'NORMAL' => 100, 'BAIXA' => 0];

    /** Etapas em que há algo para a equipe FAZER agora (as outras são acompanhamento ou espera externa). */
    public const ETAPAS_ACIONAVEIS = ['AVALIAR', 'ORCAR', 'EXECUTAR', 'CONFERIR', 'RETIRAR'];

    public const ETAPA_LABEL = [
        'AVALIAR'        => 'Avaliar',
        'EM_AVALIACAO'   => 'Em avaliação',
        'ORCAR'          => 'Fazer orçamento',
        'AGUARDAR_CLIENTE' => 'Aguardando o cliente',
        'EXECUTAR'       => 'Executar',
        'EM_EXECUCAO'    => 'Em execução',
        'AGUARDANDO_PECA' => 'Aguardando peças',
        'CONFERIR'       => 'Conferir (administrador)',
        'RETIRAR'        => 'Aguardando retirada',
        'ESPERA'         => 'Em espera',
    ];

    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    /**
     * Pontuação de um item (maior = mais urgente atacar). Pura — não toca no banco.
     *
     * @param array{prioridade:string, espera_min:int|float, duracao_estimada_min:?int, pecas_ok:bool, etapa:string, ordem_manual?:?int} $item
     */
    public static function pontuar(array $item): float
    {
        // Ordem manual do administrador vence qualquer cálculo: menor número = primeiro.
        if (isset($item['ordem_manual']) && $item['ordem_manual'] !== null) {
            return 1_000_000 - (int) $item['ordem_manual'];
        }

        $pontos = self::PESO_PRIORIDADE[$item['prioridade']] ?? 100;

        // Quem espera há mais tempo sobe, com teto (para não esmagar a prioridade).
        $pontos += min(((float) $item['espera_min']) / 2, 600);

        // Serviço rápido ganha um empurrão: resolve-se logo e libera o cliente.
        if ($item['duracao_estimada_min'] !== null && (int) $item['duracao_estimada_min'] <= 30) {
            $pontos += 80;
        }

        // Para EXECUTAR, peça faltando derruba o item (não dá para começar): ele vai
        // para "aguardando peças" na tela, mas a pontuação também reflete isso.
        if ($item['etapa'] === 'EXECUTAR' && !$item['pecas_ok']) {
            $pontos -= 300;
        }

        return $pontos;
    }

    /**
     * A fila completa, já ordenada. Cada item:
     *   tipo (OCORRENCIA|OS), id, numero, etapa, etapa_label, acionavel, cliente, placa, veiculo,
     *   prioridade, espera_min, duracao_estimada_min, pecas_ok, tecnico_nome, score, ordem_manual,
     *   bloqueio (texto, quando há algo externo travando), link.
     *
     * @return array<int,array<string,mixed>>
     */
    public function itens(): array
    {
        $itens = array_merge($this->itensDeOcorrencias(), $this->itensDeOs());

        foreach ($itens as &$i) {
            $i['etapa_label'] = self::ETAPA_LABEL[$i['etapa']] ?? $i['etapa'];
            $i['acionavel']   = in_array($i['etapa'], self::ETAPAS_ACIONAVEIS, true) && ($i['etapa'] !== 'EXECUTAR' || $i['pecas_ok']);
            $i['score']       = self::pontuar($i);
        }
        unset($i);

        usort($itens, fn($a, $b) => $b['score'] <=> $a['score'] ?: strcmp((string) $a['chegada'], (string) $b['chegada']));

        return $itens;
    }

    /** Técnicos ativos e quanto cada um tem em mãos. */
    public function tecnicos(): array
    {
        $stmt = $this->conn->query("
            SELECT u.id_usuario AS id, u.nome,
                   (SELECT COUNT(DISTINCT os.id_ordem_servico)
                      FROM ordem_servico_mao_obra m JOIN ordens_servico os ON os.id_ordem_servico = m.ordem_servico_id
                     WHERE m.tecnico_id = u.id_usuario AND os.status = 'EM_ANDAMENTO')
                 + (SELECT COUNT(*) FROM ocorrencias o WHERE o.tecnico_id = u.id_usuario AND o.status = 'EM_AVALIACAO') AS em_mao
            FROM usuarios u WHERE u.tipo = 'TECNICO' AND u.ativo = 1 ORDER BY u.nome
        ");

        return array_map(function ($t) {
            $t['em_mao'] = (int) $t['em_mao'];
            $t['livre']  = $t['em_mao'] < self::CAPACIDADE_TECNICO;

            return $t;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * O que sugerir a um técnico: o melhor item acionável que ele pode começar. NÃO exclui
     * itens de outros técnicos — só destaca os dele ou sem responsável. Devolve null se a
     * fila está vazia.
     */
    public function sugestaoPara(int $tecnicoId): ?array
    {
        foreach ($this->itens() as $i) {
            if (!$i['acionavel'] || !in_array($i['etapa'], ['AVALIAR', 'EXECUTAR'], true)) {
                continue;
            }

            if ($i['tecnico_id'] === null || $i['tecnico_id'] === $tecnicoId) {
                return $i;
            }
        }

        return null;
    }

    /* ── Reorganização (só o administrador; sempre com justificativa) ────── */

    /**
     * Põe a ocorrência no TOPO da fila (ordem manual), ou devolve ao cálculo automático
     * (`$topo = false`). A justificativa é obrigatória e fica na trilha da ocorrência.
     *
     * @return array{ok:bool, erro:?string}
     */
    public function reordenar(int $ocorrenciaId, bool $topo, string $justificativa, ?int $usuarioId): array
    {
        $justificativa = trim($justificativa);

        if (mb_strlen($justificativa) < 5) {
            return ['ok' => false, 'erro' => 'Explique por que a ordem da fila está sendo alterada.'];
        }

        try {
            return Transacao::executar($this->conn, function () use ($ocorrenciaId, $topo, $justificativa, $usuarioId) {
                $stmt = $this->conn->prepare('SELECT status, ordem_manual FROM ocorrencias WHERE id_ocorrencia = ? FOR UPDATE');
                $stmt->execute([$ocorrenciaId]);
                $oc = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$oc) {
                    throw new DomainException('Ocorrência não encontrada.');
                }

                if (in_array($oc['status'], ['ENCERRADA', 'CANCELADA'], true)) {
                    throw new DomainException('Esta ocorrência já foi encerrada.');
                }

                if ($topo) {
                    $min = $this->conn->query("SELECT MIN(ordem_manual) FROM ocorrencias WHERE ordem_manual IS NOT NULL")->fetchColumn();
                    $nova = $min === null || $min === false ? 1 : (int) $min - 1;
                } else {
                    $nova = null;
                }

                $this->conn->prepare('UPDATE ocorrencias SET ordem_manual = ? WHERE id_ocorrencia = ?')->execute([$nova, $ocorrenciaId]);

                Historico::registrar(
                    $this->conn, 'OCORRENCIA', $ocorrenciaId, (string) $oc['status'], (string) $oc['status'], $usuarioId,
                    ($topo ? 'Movida para o topo da fila: ' : 'Devolvida à ordem automática da fila: ') . $justificativa
                );

                return ['ok' => true, 'erro' => null];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    /* ── Fontes ──────────────────────────────────────────────────────────── */

    private function itensDeOcorrencias(): array
    {
        $stmt = $this->conn->query("
            SELECT o.id_ocorrencia AS id, o.numero, o.status, o.prioridade, o.chegada_em, o.duracao_estimada_min,
                   o.ordem_manual, o.tecnico_id, t.nome AS tecnico_nome, c.nome AS cliente, v.placa,
                   CONCAT(mv.nome, ' ', mo.nome) AS veiculo,
                   TIMESTAMPDIFF(MINUTE, o.chegada_em, NOW()) AS espera_min,
                   (SELECT a.id_avaliacao FROM avaliacoes_tecnicas a WHERE a.ocorrencia_id = o.id_ocorrencia ORDER BY a.versao DESC LIMIT 1) AS avaliacao_id
            FROM ocorrencias o
            JOIN clientes c ON c.id_cliente = o.cliente_id
            JOIN veiculos v ON v.id_veiculo = o.veiculo_id
            JOIN marcas_veiculos mv ON mv.id_marca = v.marca_id
            JOIN modelos_veiculos mo ON mo.id_modelo = v.modelo_id
            LEFT JOIN usuarios t ON t.id_usuario = o.tecnico_id
            WHERE o.status IN ('AGUARDANDO_AVALIACAO','EM_AVALIACAO','AGUARDANDO_ORCAMENTO','AGUARDANDO_APROVACAO','EM_ESPERA')
        ");

        $etapa = [
            'AGUARDANDO_AVALIACAO' => 'AVALIAR',
            'EM_AVALIACAO'         => 'EM_AVALIACAO',
            'AGUARDANDO_ORCAMENTO' => 'ORCAR',
            'AGUARDANDO_APROVACAO' => 'AGUARDAR_CLIENTE',
            'EM_ESPERA'            => 'ESPERA',
        ];

        $itens = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $itens[] = [
                'tipo'       => 'OCORRENCIA',
                'id'         => (int) $o['id'],
                'numero'     => $o['numero'],
                'etapa'      => $etapa[$o['status']],
                'cliente'    => $o['cliente'],
                'placa'      => $o['placa'],
                'veiculo'    => $o['veiculo'],
                'prioridade' => $o['prioridade'],
                'chegada'    => $o['chegada_em'],
                'espera_min' => max(0, (int) $o['espera_min']),
                'duracao_estimada_min' => $o['duracao_estimada_min'] !== null ? (int) $o['duracao_estimada_min'] : null,
                'pecas_ok'   => true,
                'tecnico_id' => $o['tecnico_id'] !== null ? (int) $o['tecnico_id'] : null,
                'tecnico_nome' => $o['tecnico_nome'],
                'ordem_manual' => $o['ordem_manual'] !== null ? (int) $o['ordem_manual'] : null,
                'bloqueio'   => $o['status'] === 'AGUARDANDO_APROVACAO' ? 'Orçamento com o cliente' : ($o['status'] === 'EM_ESPERA' ? 'Orçamento recusado/em espera' : null),
                'link'       => './viewOcorrencia?id=' . (int) $o['id'],
            ];
        }

        return $itens;
    }

    private function itensDeOs(): array
    {
        $stmt = $this->conn->query("
            SELECT os.id_ordem_servico AS id, os.numero, os.status, os.data_abertura, os.ocorrencia_id,
                   COALESCE(oc.prioridade, 'NORMAL') AS prioridade, oc.duracao_estimada_min, oc.ordem_manual,
                   c.nome AS cliente, v.placa, CONCAT(mv.nome, ' ', mo.nome) AS veiculo,
                   TIMESTAMPDIFF(MINUTE, os.data_abertura, NOW()) AS espera_min,
                   (SELECT m.tecnico_id FROM ordem_servico_mao_obra m WHERE m.ordem_servico_id = os.id_ordem_servico AND m.tecnico_id IS NOT NULL ORDER BY m.id_OS_mao LIMIT 1) AS tecnico_id,
                   (SELECT u.nome FROM ordem_servico_mao_obra m JOIN usuarios u ON u.id_usuario = m.tecnico_id WHERE m.ordem_servico_id = os.id_ordem_servico ORDER BY m.id_OS_mao LIMIT 1) AS tecnico_nome,
                   (SELECT COUNT(*) FROM ordem_servico_produtos p JOIN produtos pr ON pr.id_produto = p.produto_id
                     WHERE p.ordem_servico_id = os.id_ordem_servico AND p.status IN ('PLANEJADO','RESERVADO') AND pr.estoque_atual < p.quantidade) AS pecas_faltando
            FROM ordens_servico os
            JOIN clientes c ON c.id_cliente = os.cliente_id
            JOIN veiculos v ON v.id_veiculo = os.veiculo_id
            JOIN marcas_veiculos mv ON mv.id_marca = v.marca_id
            JOIN modelos_veiculos mo ON mo.id_modelo = v.modelo_id
            LEFT JOIN ocorrencias oc ON oc.id_ocorrencia = os.ocorrencia_id
            WHERE os.status IN ('ABERTA','EM_ANDAMENTO','AGUARDANDO_PECA','AGUARDANDO_CONFERENCIA','AGUARDANDO_RETIRADA')
        ");

        $etapa = [
            'ABERTA'                 => 'EXECUTAR',
            'EM_ANDAMENTO'           => 'EM_EXECUCAO',
            'AGUARDANDO_PECA'        => 'AGUARDANDO_PECA',
            'AGUARDANDO_CONFERENCIA' => 'CONFERIR',
            'AGUARDANDO_RETIRADA'    => 'RETIRAR',
        ];

        $itens = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $pecasOk = (int) $o['pecas_faltando'] === 0;

            $itens[] = [
                'tipo'       => 'OS',
                'id'         => (int) $o['id'],
                'numero'     => $o['numero'],
                'etapa'      => $etapa[$o['status']],
                'cliente'    => $o['cliente'],
                'placa'      => $o['placa'],
                'veiculo'    => $o['veiculo'],
                'prioridade' => $o['prioridade'],
                'chegada'    => $o['data_abertura'],
                'espera_min' => max(0, (int) $o['espera_min']),
                'duracao_estimada_min' => $o['duracao_estimada_min'] !== null ? (int) $o['duracao_estimada_min'] : null,
                'pecas_ok'   => $pecasOk,
                'tecnico_id' => $o['tecnico_id'] !== null ? (int) $o['tecnico_id'] : null,
                'tecnico_nome' => $o['tecnico_nome'],
                'ordem_manual' => $o['ordem_manual'] !== null ? (int) $o['ordem_manual'] : null,
                'bloqueio'   => $o['status'] === 'AGUARDANDO_PECA' || ($o['status'] === 'ABERTA' && !$pecasOk) ? 'Falta peça em estoque' : null,
                'link'       => './viewOrdemServico?id=' . (int) $o['id'],
            ];
        }

        return $itens;
    }
}
