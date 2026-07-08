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

    // Estoque atual de um produto
    private function estoqueAtual(int $produtoId): int {
        $stmt = $this->conn->prepare("SELECT estoque_atual FROM produtos WHERE id_produto = ? LIMIT 1");
        $stmt->execute([$produtoId]);
        return (int) $stmt->fetchColumn();
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
        $qtd       = abs((int) ($dados['quantidade'] ?? 0));

        if ($produtoId <= 0 || $qtd <= 0) {
            $this->lastError = 'Informe o produto e uma quantidade válida.';
            return false;
        }

        // Não permite saída maior que o estoque atual
        if ($tipo === 'SAIDA') {
            $estoque = $this->estoqueAtual($produtoId);
            if ($qtd > $estoque) {
                $this->lastError = "Saída de {$qtd} unidade(s) maior que o estoque atual ({$estoque}).";
                return false;
            }
        }

        try {
            $this->conn->beginTransaction();

            // 1) Busca custo_unitario atual do produto
            $sqlCusto = "SELECT preco_custo FROM produtos WHERE id_produto = :pid LIMIT 1";
            $stmtC    = $this->conn->prepare($sqlCusto);
            $stmtC->bindValue(':pid', $dados['produto_id'], PDO::PARAM_INT);
            $stmtC->execute();
            $custo = $stmtC->fetchColumn() ?? 0;

            // 2) Insere movimentação
            $sqlIns = "INSERT INTO movimentacoes_estoque
                            (produto_id, tipo, quantidade, custo_unitario)
                       VALUES
                            (:produto_id, :tipo, :quantidade, :custo_unitario)";

            $stmtI = $this->conn->prepare($sqlIns);
            $stmtI->execute([
                ':produto_id'    => $dados['produto_id'],
                ':tipo'          => $dados['tipo'],          // ENTRADA | SAIDA
                ':quantidade'    => abs((int)$dados['quantidade']),
                ':custo_unitario'=> $custo,
            ]);

            // 3) Atualiza estoque do produto
            $op     = ($dados['tipo'] === 'ENTRADA') ? '+' : '-';
            $sqlEst = "UPDATE produtos
                       SET estoque_atual  = estoque_atual $op :qtd,
                           atualizado_em  = NOW()
                       WHERE id_produto   = :pid";

            $stmtE = $this->conn->prepare($sqlEst);
            $stmtE->execute([
                ':qtd' => abs((int)$dados['quantidade']),
                ':pid' => $dados['produto_id'],
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
        $novaQtd     = abs((int) ($dados['quantidade'] ?? 0));

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

            // Valida saída: estoque-base = estoque atual sem o efeito da movimentação antiga
            if ($novoTipo === 'SAIDA') {
                $base = $this->estoqueAtual($novoProduto);
                if ((int) $old['produto_id'] === $novoProduto) {
                    $base += ($old['tipo'] === 'ENTRADA') ? -(int) $old['quantidade'] : (int) $old['quantidade'];
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
            $stmtC->bindValue(':pid', $dados['produto_id'], PDO::PARAM_INT);
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
                ':produto_id'     => $dados['produto_id'],
                ':tipo'           => $dados['tipo'],
                ':quantidade'     => abs((int)$dados['quantidade']),
                ':custo_unitario' => $custo,
                ':id'             => $id,
            ]);

            // 5) Aplica novo efeito no estoque
            $opNovo  = ($dados['tipo'] === 'ENTRADA') ? '+' : '-';
            $sqlNovo = "UPDATE produtos
                        SET estoque_atual = estoque_atual $opNovo :qtd,
                            atualizado_em = NOW()
                        WHERE id_produto  = :pid";
            $stmtN   = $this->conn->prepare($sqlNovo);
            $stmtN->execute([
                ':qtd' => abs((int)$dados['quantidade']),
                ':pid' => $dados['produto_id'],
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