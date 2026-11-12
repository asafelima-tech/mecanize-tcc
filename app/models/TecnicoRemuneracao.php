<?php

/**
 * Regra de remuneração do técnico + cálculo de comissão.
 *
 * Uma regra ATIVA por técnico (UNIQUE em tecnico_remuneracao_regra.tecnico_id)
 * — editar sobrescreve. O histórico de quanto foi de fato pago não mora
 * aqui: mora na própria OS (ordem_servico_mao_obra), como um retrato do que
 * essa regra dizia no momento em que a OS foi finalizada. Mudar a regra
 * aqui nunca reabre o cálculo de uma OS já concluída — ver
 * OrdemServico::calcularComissoesNaConclusao().
 */
class TecnicoRemuneracao
{
    public $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    public const TIPOS = [
        'PERCENTUAL_TOTAL',
        'PERCENTUAL_SERVICOS',
        'PERCENTUAL_PECAS',
        'PERCENTUAL_TIPO_SERVICO',
        'VALOR_FIXO_SERVICO',
        'SALARIO_FIXO',
        'SALARIO_FIXO_MAIS_COMISSAO',
    ];

    public const TIPO_LABEL = [
        'PERCENTUAL_TOTAL'           => 'Percentual sobre o valor total da OS',
        'PERCENTUAL_SERVICOS'        => 'Percentual sobre mão de obra / serviços',
        'PERCENTUAL_PECAS'           => 'Percentual sobre peças/produtos utilizados',
        'PERCENTUAL_TIPO_SERVICO'    => 'Percentual por tipo de serviço',
        'VALOR_FIXO_SERVICO'         => 'Valor fixo por serviço realizado',
        'SALARIO_FIXO'               => 'Salário fixo mensal',
        'SALARIO_FIXO_MAIS_COMISSAO' => 'Salário fixo + comissão',
    ];

    public const BASE_EXTRA_LABEL = [
        'TOTAL'    => 'valor total da OS',
        'SERVICOS' => 'mão de obra / serviços',
        'PECAS'    => 'peças/produtos',
    ];

    public static function rotuloTipo(?string $tipo): string
    {
        return self::TIPO_LABEL[$tipo] ?? (string) $tipo;
    }

    /** Regra ativa do técnico, com as categorias (se o tipo for PERCENTUAL_TIPO_SERVICO). Null = sem regra cadastrada. */
    public function buscarRegraAtiva(int $tecnicoId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM tecnico_remuneracao_regra WHERE tecnico_id = ? AND ativo = 1
        ");
        $stmt->execute([$tecnicoId]);
        $regra = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$regra) {
            return null;
        }

        $regra['categorias'] = $regra['tipo'] === 'PERCENTUAL_TIPO_SERVICO'
            ? $this->buscarCategorias((int) $regra['id'])
            : [];

        return $regra;
    }

    public function buscarCategorias(int $regraId): array
    {
        $stmt = $this->conn->prepare("
            SELECT categoria, percentual FROM tecnico_remuneracao_regra_categoria
            WHERE regra_id = ? ORDER BY categoria ASC
        ");
        $stmt->execute([$regraId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cria ou substitui a regra ativa do técnico (UPSERT — a UNIQUE em
     * tecnico_id garante uma regra por técnico). Retorna true, ou uma
     * string com o motivo da recusa quando os dados não batem com o tipo
     * escolhido (ex.: PERCENTUAL_TOTAL sem percentual).
     */
    public function salvar(int $tecnicoId, array $dados)
    {
        $tipo = $dados['tipo'] ?? '';

        if (!in_array($tipo, self::TIPOS, true)) {
            return 'Tipo de remuneração inválido.';
        }

        $percentual  = null;
        $valorFixo   = null;
        $baseExtra   = null;
        $categorias  = [];

        switch ($tipo) {
            case 'PERCENTUAL_TOTAL':
            case 'PERCENTUAL_SERVICOS':
            case 'PERCENTUAL_PECAS':
                $percentual = $this->numero($dados['percentual'] ?? null);
                if ($percentual === null || $percentual <= 0) {
                    return 'Informe o percentual.';
                }
                break;

            case 'PERCENTUAL_TIPO_SERVICO':
                foreach ((array) ($dados['categorias'] ?? []) as $linha) {
                    $categoria = trim((string) ($linha['categoria'] ?? ''));
                    $pct       = $this->numero($linha['percentual'] ?? null);
                    if ($categoria !== '' && $pct !== null && $pct > 0) {
                        $categorias[] = ['categoria' => $categoria, 'percentual' => $pct];
                    }
                }
                if (empty($categorias)) {
                    return 'Informe ao menos uma categoria com percentual.';
                }
                break;

            case 'VALOR_FIXO_SERVICO':
                $valorFixo = $this->numero($dados['valor_fixo'] ?? null);
                if ($valorFixo === null || $valorFixo <= 0) {
                    return 'Informe o valor fixo por serviço.';
                }
                break;

            case 'SALARIO_FIXO':
                $valorFixo = $this->numero($dados['valor_fixo'] ?? null);
                if ($valorFixo === null || $valorFixo <= 0) {
                    return 'Informe o valor do salário fixo.';
                }
                break;

            case 'SALARIO_FIXO_MAIS_COMISSAO':
                $valorFixo  = $this->numero($dados['valor_fixo'] ?? null);
                $percentual = $this->numero($dados['percentual'] ?? null);
                $baseExtra  = $dados['comissao_base_extra'] ?? null;
                if ($valorFixo === null || $valorFixo <= 0) {
                    return 'Informe o valor do salário fixo.';
                }
                if ($percentual === null || $percentual <= 0) {
                    return 'Informe o percentual da comissão.';
                }
                if (!in_array($baseExtra, ['TOTAL', 'SERVICOS', 'PECAS'], true)) {
                    return 'Selecione sobre o que a comissão incide.';
                }
                break;
        }

        try {
            $this->conn->beginTransaction();

            $this->conn->prepare("
                INSERT INTO tecnico_remuneracao_regra
                    (tecnico_id, tipo, percentual, valor_fixo, comissao_base_extra, observacoes, ativo)
                VALUES (?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE
                    tipo = VALUES(tipo),
                    percentual = VALUES(percentual),
                    valor_fixo = VALUES(valor_fixo),
                    comissao_base_extra = VALUES(comissao_base_extra),
                    observacoes = VALUES(observacoes),
                    ativo = 1
            ")->execute([
                $tecnicoId,
                $tipo,
                $percentual,
                $valorFixo,
                $baseExtra,
                trim((string) ($dados['observacoes'] ?? '')) ?: null,
            ]);

            $regraId = (int) $this->conn->query(
                'SELECT id FROM tecnico_remuneracao_regra WHERE tecnico_id = ' . (int) $tecnicoId
            )->fetchColumn();

            // Categorias sempre recriadas do zero — não têm histórico próprio
            // (o histórico do que foi usado mora na OS, snapshot).
            $this->conn->prepare('DELETE FROM tecnico_remuneracao_regra_categoria WHERE regra_id = ?')
                ->execute([$regraId]);

            if ($tipo === 'PERCENTUAL_TIPO_SERVICO') {
                $stmt = $this->conn->prepare('
                    INSERT INTO tecnico_remuneracao_regra_categoria (regra_id, categoria, percentual)
                    VALUES (?, ?, ?)
                ');
                foreach ($categorias as $c) {
                    $stmt->execute([$regraId, $c['categoria'], $c['percentual']]);
                }
            }

            $this->conn->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('[TecnicoRemuneracao salvar] ' . $e->getMessage());
            return 'Não foi possível salvar a regra de remuneração.';
        }
    }

    /**
     * Calcula a comissão de UM técnico numa OS, a partir da regra dele e
     * dos valores finais da OS. Não grava nada — quem chama decide onde
     * persistir (ver OrdemServico::calcularComissoesNaConclusao()).
     *
     * $os precisa de: valor_total_os, valor_total_pecas, valor_total_servicos.
     * $itensServico: linhas de ordem_servico_servicos (quantidade,
     * valor_unitario, categoria, executado) — só usado por
     * PERCENTUAL_TIPO_SERVICO e VALOR_FIXO_SERVICO.
     *
     * Retorna ['valor' => float, 'base_valor' => float,
     *          'regra_tipo' => string, 'regra_percentual' => ?float].
     */
    public function calcularComissao(array $regra, array $os, array $itensServico = []): array
    {
        $tipo = $regra['tipo'];

        $totalOS       = (float) ($os['valor_total_os'] ?? 0);
        $totalServicos = (float) ($os['valor_total_servicos'] ?? 0);
        $totalPecas    = (float) ($os['valor_total_pecas'] ?? 0);

        $resultado = [
            'valor'            => 0.0,
            'base_valor'       => 0.0,
            'regra_tipo'       => $tipo,
            'regra_percentual' => isset($regra['percentual']) ? (float) $regra['percentual'] : null,
        ];

        switch ($tipo) {
            case 'PERCENTUAL_TOTAL':
                $resultado['base_valor'] = $totalOS;
                $resultado['valor']      = round($totalOS * (float) $regra['percentual'] / 100, 2);
                break;

            case 'PERCENTUAL_SERVICOS':
                $resultado['base_valor'] = $totalServicos;
                $resultado['valor']      = round($totalServicos * (float) $regra['percentual'] / 100, 2);
                break;

            case 'PERCENTUAL_PECAS':
                $resultado['base_valor'] = $totalPecas;
                $resultado['valor']      = round($totalPecas * (float) $regra['percentual'] / 100, 2);
                break;

            case 'PERCENTUAL_TIPO_SERVICO':
                $base  = 0.0;
                $valor = 0.0;
                $mapa  = [];
                foreach (($regra['categorias'] ?? []) as $c) {
                    // Correspondência por texto (não existe catálogo formal de
                    // serviços com categoria fixa neste sistema) — sem diferenciar
                    // maiúsculas/acentuação exatos, senão "Elétrica" e "elétrica"
                    // cadastrados de formas diferentes nunca bateriam.
                    $mapa[$this->chaveCategoria($c['categoria'])] = (float) $c['percentual'];
                }
                foreach ($itensServico as $item) {
                    $chave = $this->chaveCategoria($item['categoria'] ?? '');
                    if (!isset($mapa[$chave])) {
                        continue;
                    }
                    $valorItem = (float) ($item['quantidade'] ?? 1) * (float) ($item['valor_unitario'] ?? 0);
                    $base     += $valorItem;
                    $valor    += $valorItem * $mapa[$chave] / 100;
                }
                $resultado['base_valor']       = round($base, 2);
                $resultado['valor']            = round($valor, 2);
                $resultado['regra_percentual'] = null; // não é um % único — varia por categoria
                break;

            case 'VALOR_FIXO_SERVICO':
                $qtd = 0;
                foreach ($itensServico as $item) {
                    if (!empty($item['executado'])) {
                        $qtd++;
                    }
                }
                $resultado['base_valor'] = $qtd;
                $resultado['valor']      = round((float) $regra['valor_fixo'] * $qtd, 2);
                break;

            case 'SALARIO_FIXO':
                // Sem comissão por OS — o salário entra só no fechamento
                // mensal (ver TecnicoRemuneracao::resumoPeriodo()).
                break;

            case 'SALARIO_FIXO_MAIS_COMISSAO':
                $baseValor = match ($regra['comissao_base_extra']) {
                    'TOTAL'    => $totalOS,
                    'SERVICOS' => $totalServicos,
                    'PECAS'    => $totalPecas,
                    default    => 0.0,
                };
                $resultado['base_valor'] = $baseValor;
                $resultado['valor']      = round($baseValor * (float) $regra['percentual'] / 100, 2);
                break;
        }

        return $resultado;
    }

    /** Normaliza pra comparar categorias digitadas de formas ligeiramente diferentes. */
    private function chaveCategoria(string $categoria): string
    {
        $chave = mb_strtolower(trim($categoria));
        $chave = strtr($chave, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']);
        return $chave;
    }

    private function numero($valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        return (float) str_replace(',', '.', (string) $valor);
    }

    // ────────────────────────────────────────────────────────────────
    // Relatório de Financeiro/Comissões e fechamento de pagamento
    // ────────────────────────────────────────────────────────────────

    /** Todo técnico ativo, com a regra (se tiver) — base do seletor da tela de comissões. */
    public function listarTecnicos(): array
    {
        $stmt = $this->conn->query("
            SELECT u.id_usuario, u.nome, r.tipo
            FROM usuarios u
            LEFT JOIN tecnico_remuneracao_regra r ON r.tecnico_id = u.id_usuario AND r.ativo = 1
            WHERE u.tipo = 'TECNICO' AND u.ativo = 1
            ORDER BY u.nome ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Linhas de comissão de um técnico já CALCULADAS (OS finalizada) num
     * período — uma linha por OS. calculado_em é o carimbo de quando a
     * finalização gerou aquele valor, não a data de abertura da OS.
     */
    public function comissoesPorTecnico(int $tecnicoId, string $inicio, string $fim): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                mob.id_OS_mao, mob.ordem_servico_id, mob.valor_mao_obra, mob.regra_tipo,
                mob.regra_percentual, mob.base_valor, mob.status_pagamento,
                mob.ajustado_manualmente, mob.calculado_em, mob.fechamento_pagamento_id,
                os.numero AS os_numero, os.valor_total_os, os.data_conclusao,
                c.nome AS cliente
            FROM ordem_servico_mao_obra mob
            INNER JOIN ordens_servico os ON os.id_ordem_servico = mob.ordem_servico_id
            INNER JOIN clientes c ON c.id_cliente = os.cliente_id
            WHERE mob.tecnico_id = ?
              AND mob.calculado_em IS NOT NULL
              AND mob.calculado_em BETWEEN ? AND ?
            ORDER BY mob.calculado_em ASC
        ");
        $stmt->execute([$tecnicoId, $inicio . ' 00:00:00', $fim . ' 23:59:59']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resumo do período pra um técnico: quantidade de OS, total calculado,
     * quanto já foi pago e quanto está pendente. Pra SALARIO_FIXO e
     * SALARIO_FIXO_MAIS_COMISSAO, o salário fixo do período (não vinculado
     * a nenhuma OS) entra somado em "pendente" enquanto não houver um
     * fechamento do período que já cubra esse técnico.
     */
    public function resumoTecnico(int $tecnicoId, string $inicio, string $fim): array
    {
        $linhas = $this->comissoesPorTecnico($tecnicoId, $inicio, $fim);

        $pago     = 0.0;
        $pendente = 0.0;
        foreach ($linhas as $l) {
            if ($l['status_pagamento'] === 'PAGO') {
                $pago += (float) $l['valor_mao_obra'];
            } else {
                $pendente += (float) $l['valor_mao_obra'];
            }
        }

        $regra = $this->buscarRegraAtiva($tecnicoId);
        $salarioFixo = 0.0;
        if ($regra && in_array($regra['tipo'], ['SALARIO_FIXO', 'SALARIO_FIXO_MAIS_COMISSAO'], true)) {
            $salarioFixo = (float) $regra['valor_fixo'];

            // Já existe fechamento cobrindo esse período pra esse técnico?
            // Sem essa checagem o salário fixo apareceria de novo em todo
            // fechamento futuro, mesmo já pago.
            $jaFechado = $this->conn->prepare("
                SELECT 1 FROM funcionario_fechamento_pagamento
                WHERE tecnico_id = ? AND periodo_inicio = ? AND periodo_fim = ?
                LIMIT 1
            ");
            $jaFechado->execute([$tecnicoId, $inicio, $fim]);

            if ($jaFechado->fetchColumn()) {
                $pago += $salarioFixo;
            } else {
                $pendente += $salarioFixo;
            }
        }

        return [
            'qtd_os'       => count($linhas),
            'linhas'       => $linhas,
            'salario_fixo' => $salarioFixo,
            'valor_pago'   => round($pago, 2),
            'valor_pendente' => round($pendente, 2),
            'total'        => round($pago + $pendente, 2),
        ];
    }

    /**
     * Fecha o pagamento de um técnico num período: soma tudo que ainda
     * está pendente (comissões calculadas + salário fixo, se for o caso),
     * cria o registro de fechamento e carimba as linhas incluídas — é
     * esse carimbo (fechamento_pagamento_id) que impede a mesma OS entrar
     * de novo num fechamento seguinte.
     */
    public function pagar(int $tecnicoId, string $inicio, string $fim, string $formaPagamento, ?string $observacoes, int $criadoPor)
    {
        $resumo = $this->resumoTecnico($tecnicoId, $inicio, $fim);

        if ($resumo['valor_pendente'] <= 0) {
            return 'Não há valor pendente para este técnico neste período.';
        }

        try {
            $this->conn->beginTransaction();

            $this->conn->prepare("
                INSERT INTO funcionario_fechamento_pagamento
                    (tecnico_id, periodo_inicio, periodo_fim, valor_total, data_pagamento, forma_pagamento, observacoes, criado_por)
                VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)
            ")->execute([
                $tecnicoId, $inicio, $fim, $resumo['valor_pendente'], $formaPagamento,
                trim((string) $observacoes) ?: null, $criadoPor,
            ]);

            $fechamentoId = (int) $this->conn->lastInsertId();

            $this->conn->prepare("
                UPDATE ordem_servico_mao_obra
                SET status_pagamento = 'PAGO', fechamento_pagamento_id = ?
                WHERE tecnico_id = ?
                  AND status_pagamento = 'PENDENTE'
                  AND calculado_em BETWEEN ? AND ?
            ")->execute([$fechamentoId, $tecnicoId, $inicio . ' 00:00:00', $fim . ' 23:59:59']);

            $this->conn->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('[TecnicoRemuneracao pagar] ' . $e->getMessage());
            return 'Não foi possível registrar o pagamento.';
        }
    }

    public function listarFechamentos(int $tecnicoId): array
    {
        $stmt = $this->conn->prepare("
            SELECT f.*, u.nome AS criado_por_nome
            FROM funcionario_fechamento_pagamento f
            LEFT JOIN usuarios u ON u.id_usuario = f.criado_por
            WHERE f.tecnico_id = ?
            ORDER BY f.data_pagamento DESC
        ");
        $stmt->execute([$tecnicoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajuste manual pontual do valor de uma comissão já calculada — só
     * permitido enquanto ainda não entrou num fechamento (linha paga é
     * histórico fechado, não se mexe mais nela).
     */
    public function ajustarValor(int $maoObraId, float $novoValor, string $observacao)
    {
        $atual = $this->conn->prepare("SELECT status_pagamento FROM ordem_servico_mao_obra WHERE id_OS_mao = ?");
        $atual->execute([$maoObraId]);
        $status = $atual->fetchColumn();

        if ($status === false) {
            return 'Comissão não encontrada.';
        }
        if ($status === 'PAGO') {
            return 'Esta comissão já foi paga — não é possível ajustar.';
        }
        if ($novoValor < 0) {
            return 'Valor inválido.';
        }

        $ok = $this->conn->prepare("
            UPDATE ordem_servico_mao_obra
            SET valor_mao_obra = ?, ajustado_manualmente = 1, observacao_ajuste = ?
            WHERE id_OS_mao = ?
        ")->execute([$novoValor, trim($observacao) ?: null, $maoObraId]);

        return $ok ?: 'Não foi possível ajustar o valor.';
    }
}
