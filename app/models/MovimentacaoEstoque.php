<?php

class MovimentacaoEstoque {
    private $conn;
    private $lastError = '';

    public function __construct() {
        $this->conn = new Database();
    }

    public function getLastError(): string {
        return $this->lastError;
    }

    // Estoque atual de um produto (coluna é DECIMAL(10,3): preserva fração — litros/kg).
    // Fora de transação — usado só pra exibição, nunca pra decidir se uma
    // saída pode ser feita (isso tem que ler a linha travada, ver abaixo).
    private function estoqueAtual(int $produtoId): float {
        $stmt = $this->conn->prepare("SELECT estoque_atual FROM produtos WHERE id_produto = ? LIMIT 1");
        $stmt->execute([$produtoId]);
        return (float) $stmt->fetchColumn();
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

        $sql = "SELECT m.id_movimentacao, m.produto_id, p.nome AS produto_nome,
                       m.tipo, m.quantidade, m.custo_unitario, m.criado_em
                FROM movimentacoes_estoque m
                LEFT JOIN produtos p ON p.id_produto = m.produto_id
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
        $sql = "SELECT m.id_movimentacao, m.produto_id, p.nome AS produto_nome,
                       m.tipo, m.quantidade, m.custo_unitario, m.criado_em
                FROM movimentacoes_estoque m
                LEFT JOIN produtos p ON p.id_produto = m.produto_id
                WHERE m.id_movimentacao = :id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── SAVE ────────────────────────────────────────────────────────
    // Insere a movimentação e ajusta estoque_atual do produto
    public function save(array $dados): bool {
        $this->lastError = '';

        $tipo      = $dados['tipo'] ?? '';
        $produtoId = (int) ($dados['produto_id'] ?? 0);
        // (float) preserva fração — a coluna é DECIMAL(10,3) (produtos vendidos
        // por litro/kg); um (int) aqui truncava 2.5L pra 2L silenciosamente.
        $qtd       = abs((float) ($dados['quantidade'] ?? 0));

        if ($produtoId <= 0 || $qtd <= 0) {
            $this->lastError = 'Informe o produto e uma quantidade válida.';
            return false;
        }

        try {
            $this->conn->beginTransaction();

            // 1) Trava a linha do produto ANTES de checar se a saída cabe no
            // estoque — sem isso, duas saídas simultâneas podiam ler o mesmo
            // estoque, as duas passarem na validação, e o estoque terminar
            // negativo (a única coisa atômica antes era o UPDATE em si).
            $estoqueTravado = $this->estoqueAtualTravado($produtoId);

            if ($tipo === 'SAIDA' && $qtd > $estoqueTravado) {
                $this->conn->rollBack();
                $this->lastError = "Saída de {$qtd} unidade(s) maior que o estoque atual ({$estoqueTravado}).";
                return false;
            }

            // 2) Busca custo_unitario atual do produto
            $sqlCusto = "SELECT preco_custo FROM produtos WHERE id_produto = :pid LIMIT 1";
            $stmtC    = $this->conn->prepare($sqlCusto);
            $stmtC->bindValue(':pid', $produtoId, PDO::PARAM_INT);
            $stmtC->execute();
            $custo = $stmtC->fetchColumn() ?? 0;

            // 3) Insere movimentação
            $sqlIns = "INSERT INTO movimentacoes_estoque
                            (produto_id, tipo, quantidade, custo_unitario)
                       VALUES
                            (:produto_id, :tipo, :quantidade, :custo_unitario)";

            $stmtI = $this->conn->prepare($sqlIns);
            $stmtI->execute([
                ':produto_id'    => $produtoId,
                ':tipo'          => $tipo,          // ENTRADA | SAIDA
                ':quantidade'    => $qtd,
                ':custo_unitario'=> $custo,
            ]);

            // 4) Atualiza estoque do produto (linha já travada acima)
            $op     = ($tipo === 'ENTRADA') ? '+' : '-';
            $sqlEst = "UPDATE produtos
                       SET estoque_atual  = estoque_atual $op :qtd,
                           atualizado_em  = NOW()
                       WHERE id_produto   = :pid";

            $stmtE = $this->conn->prepare($sqlEst);
            $stmtE->execute([
                ':qtd' => $qtd,
                ':pid' => $produtoId,
            ]);

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    // ── UPDATE ──────────────────────────────────────────────────────
    // Reverte o efeito antigo no estoque e aplica o novo
    public function update(int $id, array $dados): bool {
        $this->lastError = '';

        $novoTipo    = $dados['tipo'] ?? '';
        $novoProduto = (int) ($dados['produto_id'] ?? 0);
        $novaQtd     = abs((float) ($dados['quantidade'] ?? 0));

        if ($novoProduto <= 0 || $novaQtd <= 0) {
            $this->lastError = 'Informe o produto e uma quantidade válida.';
            return false;
        }

        try {
            $this->conn->beginTransaction();

            // 1) Busca movimentação original
            $sqlOld = "SELECT produto_id, tipo, quantidade
                       FROM movimentacoes_estoque
                       WHERE id_movimentacao = :id LIMIT 1";
            $stmtO  = $this->conn->prepare($sqlOld);
            $stmtO->bindValue(':id', $id, PDO::PARAM_INT);
            $stmtO->execute();
            $old = $stmtO->fetch(PDO::FETCH_ASSOC);

            if (!$old) {
                $this->conn->rollBack();
                return false;
            }

            // Trava a linha do produto de destino ANTES de validar a saída —
            // mesmo raciocínio do save(): a checagem "cabe no estoque?" e o
            // ajuste têm que enxergar um estoque que não muda por baixo dela
            // no meio do caminho (edição concorrente da mesma movimentação
            // ou outra saída do mesmo produto rodando ao mesmo tempo).
            $estoqueTravado = $this->estoqueAtualTravado($novoProduto);

            // Se o produto mudou nesta edição, a linha do produto ANTIGO
            // também precisa ser travada (ela também vai ser escrita abaixo,
            // ao reverter o efeito antigo).
            if ((int) $old['produto_id'] !== $novoProduto) {
                $this->estoqueAtualTravado((int) $old['produto_id']);
            }

            // Valida saída: estoque-base = estoque atual sem o efeito da movimentação antiga
            if ($novoTipo === 'SAIDA') {
                $base = $estoqueTravado;
                if ((int) $old['produto_id'] === $novoProduto) {
                    $base += ($old['tipo'] === 'ENTRADA') ? -(float) $old['quantidade'] : (float) $old['quantidade'];
                }
                if ($novaQtd > $base) {
                    $this->conn->rollBack();
                    $this->lastError = "Saída de {$novaQtd} unidade(s) maior que o estoque disponível ({$base}).";
                    return false;
                }
            }

            // 2) Reverte efeito antigo no estoque
            $opRev  = ($old['tipo'] === 'ENTRADA') ? '-' : '+';
            $sqlRev = "UPDATE produtos
                       SET estoque_atual = estoque_atual $opRev :qtd,
                           atualizado_em = NOW()
                       WHERE id_produto  = :pid";
            $stmtR  = $this->conn->prepare($sqlRev);
            $stmtR->execute([':qtd' => $old['quantidade'], ':pid' => $old['produto_id']]);

            // 3) Busca custo atual do produto (pode ter mudado)
            $sqlCusto = "SELECT preco_custo FROM produtos WHERE id_produto = :pid LIMIT 1";
            $stmtC    = $this->conn->prepare($sqlCusto);
            $stmtC->bindValue(':pid', $novoProduto, PDO::PARAM_INT);
            $stmtC->execute();
            $custo = $stmtC->fetchColumn() ?? 0;

            // 4) Atualiza a movimentação
            $sqlUpd = "UPDATE movimentacoes_estoque
                       SET produto_id     = :produto_id,
                           tipo           = :tipo,
                           quantidade     = :quantidade,
                           custo_unitario = :custo_unitario
                       WHERE id_movimentacao = :id";
            $stmtU  = $this->conn->prepare($sqlUpd);
            $stmtU->execute([
                ':produto_id'     => $novoProduto,
                ':tipo'           => $novoTipo,
                ':quantidade'     => $novaQtd,
                ':custo_unitario' => $custo,
                ':id'             => $id,
            ]);

            // 5) Aplica novo efeito no estoque
            $opNovo  = ($novoTipo === 'ENTRADA') ? '+' : '-';
            $sqlNovo = "UPDATE produtos
                        SET estoque_atual = estoque_atual $opNovo :qtd,
                            atualizado_em = NOW()
                        WHERE id_produto  = :pid";
            $stmtN   = $this->conn->prepare($sqlNovo);
            $stmtN->execute([
                ':qtd' => $novaQtd,
                ':pid' => $novoProduto,
            ]);

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    // ── DELETE ──────────────────────────────────────────────────────
    // Reverte o efeito no estoque antes de excluir
    public function delete(int $id): bool {
        try {
            $this->conn->beginTransaction();

            // 1) Busca movimentação
            $sqlOld = "SELECT produto_id, tipo, quantidade
                       FROM movimentacoes_estoque
                       WHERE id_movimentacao = :id LIMIT 1";
            $stmtO  = $this->conn->prepare($sqlOld);
            $stmtO->bindValue(':id', $id, PDO::PARAM_INT);
            $stmtO->execute();
            $mov = $stmtO->fetch(PDO::FETCH_ASSOC);

            if (!$mov) {
                $this->conn->rollBack();
                return false;
            }

            // 2) Reverte estoque
            $opRev  = ($mov['tipo'] === 'ENTRADA') ? '-' : '+';
            $sqlRev = "UPDATE produtos
                       SET estoque_atual = estoque_atual $opRev :qtd,
                           atualizado_em = NOW()
                       WHERE id_produto  = :pid";
            $stmtR  = $this->conn->prepare($sqlRev);
            $stmtR->execute([':qtd' => $mov['quantidade'], ':pid' => $mov['produto_id']]);

            // 3) Exclui
            $sqlDel = "DELETE FROM movimentacoes_estoque WHERE id_movimentacao = :id";
            $stmtD  = $this->conn->prepare($sqlDel);
            $stmtD->bindValue(':id', $id, PDO::PARAM_INT);
            $stmtD->execute();

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
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