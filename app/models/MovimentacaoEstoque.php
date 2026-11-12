<?php

require_once __DIR__ . '/../core/Transacao.php';

/**
 * Livro de estoque.
 *
 * REGRA CENTRAL: uma movimentação, depois de gravada, NUNCA é editada nem
 * apagada. O saldo de um produto é a soma dos `delta` das suas movimentações; se
 * algo foi lançado errado, a correção é uma NOVA movimentação que o desfaz
 * (estorno, com justificativa e autoria) — o erro e a correção ficam no histórico.
 * Antes, editar ou excluir uma movimentação reescrevia o passado: o saldo mudava
 * e nada dizia por quê.
 *
 * `quantidade` é sempre a grandeza (positiva) do que se moveu; `delta` é o efeito
 * no saldo, COM sinal. AJUSTE não tem sinal implícito: quem lança informa se foi
 * para mais ou para menos (antes, todo AJUSTE subtraía — não existia ajuste positivo).
 */
class MovimentacaoEstoque {
    private $conn;
    private $lastError = '';

    /** Origens cuja movimentação o administrador pode estornar pela tela de estoque. */
    private const ORIGENS_ESTORNAVEIS = ['MANUAL', 'COMPRA'];

    public function __construct(?PDO $conn = null) {
        $this->conn = $conn ?? new Database();
    }

    public function getLastError(): string {
        return $this->lastError;
    }

    /** Id da última movimentação inserida por save(). */
    private $ultimoId = 0;

    public function ultimoId(): int {
        return $this->ultimoId;
    }

    /**
     * Grava a movimentação SEM tocar em produtos.estoque_atual.
     *
     * É o ÚNICO ponto que escreve em movimentacoes_estoque. Existe para quem já
     * ajustou o saldo dentro da própria transação:
     *   - o consumo/devolução de peça da OS (ConsumoEstoque);
     *   - o cadastro de produto, que já nasce com o estoque inicial na linha;
     *   - o estorno e o lançamento manual deste model.
     *
     * Recebe a conexão ($pdo) para entrar na MESMA transação de quem chamou: se
     * aquela transação falhar, a movimentação também some, e saldo e histórico
     * nunca contam coisas diferentes.
     *
     * @param PDO   $pdo   conexão já dentro de uma transação
     * @param array $dados produto_id, tipo, quantidade, [delta], custo_unitario,
     *                     origem, ordem_servico_id, os_produto_id, evento,
     *                     usuario_id, observacao, estorno_de_id, justificativa
     */
    public static function registrarSemAjustarEstoque(PDO $pdo, array $dados): int {
        $qtd = abs((float) ($dados['quantidade'] ?? 0));

        $dados['usuario_id'] = self::usuarioValido($pdo, $dados['usuario_id'] ?? null);

        if ((int) ($dados['produto_id'] ?? 0) <= 0 || $qtd <= 0) {
            return 0;
        }

        $tipo = $dados['tipo'] ?? 'SAIDA';

        // delta = o efeito real no saldo, COM SINAL. É a verdade do livro de
        // estoque. AJUSTE não tem sinal implícito, então quem chama informa.
        $delta = array_key_exists('delta', $dados)
            ? (float) $dados['delta']
            : ($tipo === 'ENTRADA' ? $qtd : -$qtd);

        $sql = "INSERT INTO movimentacoes_estoque
                    (produto_id, tipo, origem, ordem_servico_id, os_produto_id, evento, quantidade, delta,
                     custo_unitario, forma_pagamento, usuario_id, observacao, estorno_de_id, justificativa)
                VALUES
                    (:produto_id, :tipo, :origem, :os_id, :os_produto_id, :evento, :quantidade, :delta,
                     :custo_unitario, :forma, :usuario_id, :observacao, :estorno_de, :justificativa)";

        $pdo->prepare($sql)->execute([
            ':produto_id'     => (int) $dados['produto_id'],
            ':tipo'           => $tipo,
            ':origem'         => $dados['origem'] ?? 'MANUAL',
            ':os_id'          => $dados['ordem_servico_id'] ?? null,
            ':os_produto_id'  => $dados['os_produto_id'] ?? null,
            ':evento'         => $dados['evento'] ?? null,
            ':quantidade'     => $qtd,
            ':delta'          => $delta,
            ':custo_unitario' => $dados['custo_unitario'] ?? 0,
            ':forma'          => $dados['forma_pagamento'] ?? null,
            ':usuario_id'     => $dados['usuario_id'] ?? null,
            ':observacao'     => $dados['observacao'] ?? null,
            ':estorno_de'     => $dados['estorno_de_id'] ?? null,
            ':justificativa'  => $dados['justificativa'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Devolve o id do usuário se ele ainda existir; senão, null.
     *
     * A movimentação é gravada dentro da transação de quem chamou (OS, cadastro de
     * produto). Um id de usuário que não existe mais faria a chave estrangeira
     * estourar e levaria a transação inteira junto — a OS não seria salva por
     * causa de um campo de autoria.
     */
    private static function usuarioValido(PDO $pdo, $usuarioId): ?int {
        $id = (int) $usuarioId;

        if ($id <= 0) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT 1 FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $stmt->execute([$id]);

        return $stmt->fetchColumn() ? $id : null;
    }

    /**
     * Amarra a movimentação de estoque ao lançamento do caixa que ela gerou.
     * É esse vínculo que mostra na tela de onde veio a saída de dinheiro e
     * que evita a mesma compra ser lançada duas vezes.
     */
    public function vincularAoCaixa(int $movimentacaoId, int $movimentacaoCaixaId): void {
        $this->conn->prepare(
            "UPDATE movimentacoes_estoque SET movimentacao_caixa_id = ? WHERE id_movimentacao = ?"
        )->execute([$movimentacaoCaixaId, $movimentacaoId]);
    }

    /**
     * Lê o estoque atual TRAVANDO a linha (SELECT ... FOR UPDATE) — só pode
     * ser chamado dentro de uma transação já aberta. Sem isso, duas saídas
     * simultâneas do mesmo produto podiam ambas ler o mesmo estoque antes de
     * qualquer uma decrementar, passar na validação e derrubar o estoque
     * abaixo de zero (a única coisa atômica antes era o UPDATE em si, não a
     * checagem "a saída cabe no estoque?").
     */
    private function estoqueAtualTravado(int $produtoId): float {
        $stmt = $this->conn->prepare("SELECT estoque_atual FROM produtos WHERE id_produto = ? FOR UPDATE");
        $stmt->execute([$produtoId]);
        return (float) $stmt->fetchColumn();
    }

    private const COLUNAS_LISTAGEM = "m.id_movimentacao, m.produto_id, p.nome AS produto_nome,
                       m.tipo, m.quantidade, m.delta, m.custo_unitario, m.criado_em,
                       m.origem, m.evento, m.ordem_servico_id, m.forma_pagamento,
                       m.observacao, m.justificativa, m.estorno_de_id, m.movimentacao_caixa_id,
                       os.numero AS os_numero, pc.id_pedido AS pedido_id, pc.numero AS pedido_numero, u.nome AS usuario_nome,
                       EXISTS (SELECT 1 FROM movimentacoes_estoque e WHERE e.estorno_de_id = m.id_movimentacao) AS estornada";

    // ── LIST ────────────────────────────────────────────────────────
    public function list(int $limite = 8, int $offset = 0, array $filtros = []) {
        $where  = [];
        $params = [];

        if (!empty($filtros['busca'])) {
            $where[]          = "p.nome LIKE :busca";
            $params[':busca'] = '%' . $filtros['busca'] . '%';
        }

        if (!empty($filtros['tipo'])) {
            $where[]         = "m.tipo = :tipo";
            $params[':tipo'] = $filtros['tipo'];
        }

        $clausula = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT " . self::COLUNAS_LISTAGEM . "
                FROM movimentacoes_estoque m
                LEFT JOIN produtos p ON p.id_produto = m.produto_id
                LEFT JOIN ordens_servico os ON os.id_ordem_servico = m.ordem_servico_id
                LEFT JOIN recebimento_itens ri ON ri.movimentacao_id = m.id_movimentacao
                LEFT JOIN pedido_compra_itens pci ON pci.id_pedido_item = ri.pedido_item_id
                LEFT JOIN pedidos_compra pc ON pc.id_pedido = pci.pedido_id
                LEFT JOIN usuarios u ON u.id_usuario = m.usuario_id
                $clausula
                ORDER BY m.id_movimentacao DESC
                LIMIT :limite OFFSET :offset";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $val) $stmt->bindValue($key, $val);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── COUNT ───────────────────────────────────────────────────────
    public function count(array $filtros = []): int {
        $where  = [];
        $params = [];

        if (!empty($filtros['busca'])) {
            $where[]          = "p.nome LIKE :busca";
            $params[':busca'] = '%' . $filtros['busca'] . '%';
        }

        if (!empty($filtros['tipo'])) {
            $where[]         = "m.tipo = :tipo";
            $params[':tipo'] = $filtros['tipo'];
        }

        $clausula = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT COUNT(*)
                FROM movimentacoes_estoque m
                LEFT JOIN produtos p ON p.id_produto = m.produto_id
                $clausula";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $val) $stmt->bindValue($key, $val);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    // ── FIND BY ID ──────────────────────────────────────────────────
    public function findById(int $id) {
        $sql = "SELECT " . self::COLUNAS_LISTAGEM . "
                FROM movimentacoes_estoque m
                LEFT JOIN produtos p ON p.id_produto = m.produto_id
                LEFT JOIN ordens_servico os ON os.id_ordem_servico = m.ordem_servico_id
                LEFT JOIN recebimento_itens ri ON ri.movimentacao_id = m.id_movimentacao
                LEFT JOIN pedido_compra_itens pci ON pci.id_pedido_item = ri.pedido_item_id
                LEFT JOIN pedidos_compra pc ON pc.id_pedido = pci.pedido_id
                LEFT JOIN usuarios u ON u.id_usuario = m.usuario_id
                WHERE m.id_movimentacao = :id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── SAVE ────────────────────────────────────────────────────────
    /**
     * Lançamento manual: ENTRADA, SAIDA ou AJUSTE.
     *
     *  - Qualquer movimento que reduza o saldo (SAIDA, AJUSTE "para menos") só passa
     *    se couber no estoque — a checagem lê o saldo com a linha travada, então duas
     *    saídas simultâneas não derrubam o estoque abaixo de zero.
     *  - AJUSTE exige o sentido ('+' ou '-') e uma justificativa: é correção de
     *    contagem, e quem confere depois precisa saber por quê.
     */
    public function save(array $dados): bool {
        $this->lastError = '';

        $tipo      = $dados['tipo'] ?? '';
        $produtoId = (int) ($dados['produto_id'] ?? 0);
        // (float) preserva fração — a coluna é DECIMAL(10,3) (produtos vendidos
        // por litro/kg); um (int) aqui truncava 2.5L pra 2L silenciosamente.
        $qtd       = abs((float) str_replace(',', '.', (string) ($dados['quantidade'] ?? 0)));

        if ($produtoId <= 0 || $qtd <= 0) {
            $this->lastError = 'Informe o produto e uma quantidade válida.';
            return false;
        }

        if (!in_array($tipo, ['ENTRADA', 'SAIDA', 'AJUSTE'], true)) {
            $this->lastError = 'Tipo de movimentação inválido.';
            return false;
        }

        $justificativa = trim((string) ($dados['justificativa'] ?? ''));

        if ($tipo === 'AJUSTE') {
            if (!in_array($dados['sentido'] ?? '', ['+', '-'], true)) {
                $this->lastError = 'No ajuste, informe se o estoque aumenta ou diminui.';
                return false;
            }

            if (mb_strlen($justificativa) < 5) {
                $this->lastError = 'Informe o motivo do ajuste (ex.: contagem de inventário, perda, avaria).';
                return false;
            }
        }

        $sinal = match ($tipo) {
            'ENTRADA' => 1,
            'SAIDA'   => -1,
            default   => ($dados['sentido'] === '+') ? 1 : -1,
        };
        $delta = $sinal * $qtd;

        try {
            Transacao::executar($this->conn, function () use ($dados, $tipo, $produtoId, $qtd, $delta, $justificativa) {
                // Trava a linha do produto ANTES de checar o saldo.
                $estoque = $this->estoqueAtualTravado($produtoId);

                if ($delta < 0 && $qtd > $estoque) {
                    throw new DomainException(
                        'Retirada de ' . $this->fmt($qtd) . ' unidade(s) maior que o estoque atual (' . $this->fmt($estoque) . ').'
                    );
                }

                $custo = $this->conn->prepare("SELECT preco_custo FROM produtos WHERE id_produto = ? LIMIT 1");
                $custo->execute([$produtoId]);

                $this->ultimoId = self::registrarSemAjustarEstoque($this->conn, [
                    'produto_id'      => $produtoId,
                    'tipo'            => $tipo,
                    'origem'          => $dados['origem'] ?? 'MANUAL',
                    'quantidade'      => $qtd,
                    'delta'           => $delta,
                    'custo_unitario'  => (float) ($custo->fetchColumn() ?: 0),
                    // Forma de pagamento só faz sentido na compra (entrada).
                    'forma_pagamento' => $tipo === 'ENTRADA' ? ($dados['forma_pagamento'] ?? null) : null,
                    'usuario_id'      => $dados['usuario_id'] ?? null,
                    'observacao'      => $dados['observacao'] ?? null,
                    'justificativa'   => $justificativa !== '' ? $justificativa : null,
                ]);

                $this->conn->prepare("UPDATE produtos SET estoque_atual = estoque_atual + ?, atualizado_em = NOW() WHERE id_produto = ?")
                    ->execute([$delta, $produtoId]);
            });

            return true;
        } catch (DomainException $e) {
            $this->lastError = $e->getMessage();
            return false;
        } catch (\Throwable $e) {
            error_log('[MovimentacaoEstoque save] ' . $e->getMessage());
            $this->lastError = 'Não foi possível registrar a movimentação.';
            return false;
        }
    }

    // ── ESTORNO ─────────────────────────────────────────────────────
    /**
     * Desfaz uma movimentação lançando a oposta — NUNCA apagando a original.
     *
     *  - Só lançamento manual (ou compra) pode ser estornado aqui. Movimentação de
     *    OS se desfaz devolvendo a peça na própria OS (ConsumoEstoque::devolver), e
     *    o estoque inicial do cadastro se corrige por ajuste.
     *  - Uma movimentação é estornada UMA vez (chave única em estorno_de_id) e um
     *    estorno não se estorna.
     *  - Estornar uma entrada tira peças do estoque: só vale se elas ainda estão lá.
     *  - O lançamento de caixa de uma compra NÃO é alterado: quanto dinheiro voltou
     *    do fornecedor é decisão financeira, registrada à parte.
     *
     * @return array{ok:bool, erro:?string, repetido:bool}
     */
    public function estornar(int $id, ?int $usuarioId, string $justificativa): array {
        $justificativa = trim($justificativa);
        $saida = ['ok' => false, 'erro' => null, 'repetido' => false];

        if (mb_strlen($justificativa) < 5) {
            $saida['erro'] = 'Informe o motivo do estorno.';
            return $saida;
        }

        try {
            return Transacao::executar($this->conn, function () use ($id, $usuarioId, $justificativa, $saida) {
                $stmt = $this->conn->prepare('SELECT * FROM movimentacoes_estoque WHERE id_movimentacao = ? FOR UPDATE');
                $stmt->execute([$id]);
                $orig = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$orig) {
                    throw new DomainException('Movimentação não encontrada.');
                }

                $jaEstornada = $this->conn->prepare('SELECT 1 FROM movimentacoes_estoque WHERE estorno_de_id = ?');
                $jaEstornada->execute([$id]);

                if ($jaEstornada->fetchColumn()) {
                    return array_merge($saida, ['ok' => true, 'repetido' => true]);
                }

                if (!empty($orig['estorno_de_id']) || $orig['origem'] === 'ESTORNO') {
                    throw new DomainException('Um estorno não pode ser estornado. Se foi um engano, lance a movimentação de novo.');
                }

                if (!in_array($orig['origem'], self::ORIGENS_ESTORNAVEIS, true) || !empty($orig['evento'])) {
                    throw new DomainException(
                        $orig['origem'] === 'OS'
                            ? 'Esta movimentação veio de uma ordem de serviço. Para desfazê-la, devolva a peça na própria OS.'
                            : 'Este tipo de movimentação não pode ser estornado. Corrija o saldo com um ajuste.'
                    );
                }

                $produtoId = (int) $orig['produto_id'];
                $deltaOrig = $orig['delta'] !== null
                    ? (float) $orig['delta']
                    : ($orig['tipo'] === 'ENTRADA' ? (float) $orig['quantidade'] : -(float) $orig['quantidade']);
                $deltaNovo = -$deltaOrig;

                $estoque = $this->estoqueAtualTravado($produtoId);

                if ($deltaNovo < 0 && abs($deltaNovo) > $estoque) {
                    throw new DomainException(
                        'Não dá para estornar: o estorno retiraria ' . $this->fmt(abs($deltaNovo))
                        . ' unidade(s) e há apenas ' . $this->fmt($estoque) . ' em estoque.'
                    );
                }

                self::registrarSemAjustarEstoque($this->conn, [
                    'produto_id'     => $produtoId,
                    'tipo'           => $deltaNovo > 0 ? 'ENTRADA' : 'SAIDA',
                    'origem'         => 'ESTORNO',
                    'quantidade'     => abs($deltaNovo),
                    'delta'          => $deltaNovo,
                    'custo_unitario' => $orig['custo_unitario'] ?? 0,
                    'usuario_id'     => $usuarioId,
                    'observacao'     => 'Estorno da movimentação #' . $id,
                    'estorno_de_id'  => $id,
                    'justificativa'  => $justificativa,
                ]);

                $this->conn->prepare("UPDATE produtos SET estoque_atual = estoque_atual + ?, atualizado_em = NOW() WHERE id_produto = ?")
                    ->execute([$deltaNovo, $produtoId]);

                return array_merge($saida, ['ok' => true]);
            });
        } catch (DomainException $e) {
            return array_merge($saida, ['erro' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[MovimentacaoEstoque estornar] ' . $e->getMessage());
            return array_merge($saida, ['erro' => 'Não foi possível estornar a movimentação.']);
        }
    }

    private function fmt(float $n): string {
        return rtrim(rtrim(number_format($n, 3, ',', '.'), '0'), ',');
    }

    // ── LISTA PRODUTOS ATIVOS (para o <select> do form) ─────────────
    public function getProdutosAtivos(): array {
        $sql = "SELECT id_produto, nome, estoque_atual, preco_custo
                FROM produtos
                WHERE ativo = 1
                ORDER BY nome ASC";

        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
