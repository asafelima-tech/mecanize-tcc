<?php

require_once __DIR__ . '/../core/Transacao.php';
require_once __DIR__ . '/../core/Historico.php';
require_once __DIR__ . '/MovimentacaoEstoque.php';
require_once __DIR__ . '/SolicitacaoPeca.php';
require_once __DIR__ . '/Notificacao.php';

/**
 * Pedido de compra: o abastecimento da oficina junto aos fornecedores (só administrador).
 *
 *   RASCUNHO ──realizar──▶ REALIZADO ──receber──▶ PARCIALMENTE_RECEBIDO ──receber──▶ RECEBIDO
 *       │                     │
 *       └─────cancelar────────┴──(sem nenhum recebimento)──▶ CANCELADO
 *
 * REGRA CENTRAL: comprar não é ter. Montar, realizar ou cancelar um pedido NUNCA mexe no
 * estoque. O saldo sobe apenas quando um RECEBIMENTO é confirmado — e só pela quantidade
 * efetivamente recebida (recebimento parcial soma só o que chegou). O recebimento:
 *   - é idempotente (chave única por envio do formulário: clique repetido não soma duas vezes);
 *   - não aceita mais do que falta receber de cada item;
 *   - grava a entrada no livro de estoque (origem COMPRA, evento RECEBIMENTO) com o custo real;
 *   - recalcula o custo médio da peça;
 *   - reajusta as solicitações que o item atendia (e avisa quem pediu).
 * Um recebimento errado se desfaz por estorno (justificado), que retira as peças de volta —
 * só se elas ainda estiverem no estoque.
 *
 * Contas a pagar: o pedido já guarda condição/forma/vencimento previstos e `conta_pagar_id`;
 * o vínculo é ligado na Etapa 4, junto com o módulo financeiro.
 *
 * Ordem das travas (evita impasse): pedido → itens → produtos (por id) → solicitações.
 */
class PedidoCompra
{
    public const STATUS_LABEL = [
        'RASCUNHO'              => 'Rascunho',
        'REALIZADO'             => 'Pedido realizado',
        'PARCIALMENTE_RECEBIDO' => 'Parcialmente recebido',
        'RECEBIDO'              => 'Recebido',
        'CANCELADO'             => 'Cancelado',
    ];

    public const STATUS_BADGE = [
        'RASCUNHO'              => 'badge--neutral',
        'REALIZADO'             => 'badge--secondary',
        'PARCIALMENTE_RECEBIDO' => 'badge--info',
        'RECEBIDO'              => 'badge--success',
        'CANCELADO'             => 'badge--danger',
    ];

    public const FORMAS_PAGAMENTO = [
        'DINHEIRO' => 'Dinheiro', 'PIX' => 'PIX', 'CARTAO_DEBITO' => 'Cartão de débito', 'CARTAO_CREDITO' => 'Cartão de crédito',
        'BOLETO' => 'Boleto', 'TRANSFERENCIA' => 'Transferência', 'OUTRO' => 'Outro',
    ];

    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    public function conexao(): PDO
    {
        return $this->conn;
    }

    public static function rotulo(?string $s): string
    {
        return self::STATUS_LABEL[$s] ?? (string) $s;
    }

    public static function badge(?string $s): string
    {
        return self::STATUS_BADGE[$s] ?? 'badge--neutral';
    }

    /* ── Consulta ────────────────────────────────────────────────────────── */

    private const SELECT_PEDIDO = "
        SELECT pc.*, f.nome AS fornecedor_nome, f.site_url, f.condicoes_pagamento AS fornecedor_condicoes, f.ativo AS fornecedor_ativo,
               u.nome AS criado_por_nome,
               COALESCE((SELECT SUM(i.quantidade * i.preco_unitario) FROM pedido_compra_itens i WHERE i.pedido_id = pc.id_pedido), 0) AS subtotal,
               COALESCE((SELECT SUM(i.quantidade) FROM pedido_compra_itens i WHERE i.pedido_id = pc.id_pedido), 0) AS qtd_pedida,
               COALESCE((SELECT SUM(i.quantidade_recebida) FROM pedido_compra_itens i WHERE i.pedido_id = pc.id_pedido), 0) AS qtd_recebida,
               (SELECT COUNT(*) FROM pedido_compra_itens i WHERE i.pedido_id = pc.id_pedido) AS qtd_itens
        FROM pedidos_compra pc
        JOIN fornecedores f ON f.id_fornecedor = pc.fornecedor_id
        LEFT JOIN usuarios u ON u.id_usuario = pc.criado_por
    ";

    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare(self::SELECT_PEDIDO . ' WHERE pc.id_pedido = ?');
        $stmt->execute([$id]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($p) {
            $p['total'] = round((float) $p['subtotal'] + (float) $p['frete'], 2);
        }

        return $p ?: null;
    }

    public function listar(array $f = [], int $limite = 120): array
    {
        $where = [];
        $params = [];

        if (!empty($f['status']) && isset(self::STATUS_LABEL[$f['status']])) {
            $where[] = 'pc.status = ?';
            $params[] = $f['status'];
        } elseif (($f['grupo'] ?? 'abertos') === 'abertos') {
            $where[] = "pc.status IN ('RASCUNHO','REALIZADO','PARCIALMENTE_RECEBIDO')";
        }

        if (!empty($f['fornecedor_id'])) {
            $where[] = 'pc.fornecedor_id = ?';
            $params[] = (int) $f['fornecedor_id'];
        }

        if (!empty($f['busca'])) {
            $where[] = '(pc.numero LIKE ? OR f.nome LIKE ? OR pc.referencia_externa LIKE ?)';
            $t = '%' . trim($f['busca']) . '%';
            array_push($params, $t, $t, $t);
        }

        $stmt = $this->conn->prepare(self::SELECT_PEDIDO . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY pc.id_pedido DESC LIMIT ' . (int) $limite);
        $stmt->execute($params);

        return array_map(function ($p) {
            $p['total'] = round((float) $p['subtotal'] + (float) $p['frete'], 2);
            return $p;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function contagemPorStatus(): array
    {
        return array_map('intval', $this->conn->query('SELECT status, COUNT(*) FROM pedidos_compra GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    public function itens(int $pedidoId): array
    {
        $stmt = $this->conn->prepare("
            SELECT i.*, p.nome AS produto_nome, p.codigo_interno, p.estoque_atual,
                   s.numero AS solicitacao_numero,
                   (i.quantidade - i.quantidade_recebida) AS falta_receber
            FROM pedido_compra_itens i
            JOIN produtos p ON p.id_produto = i.produto_id
            LEFT JOIN solicitacoes_peca s ON s.id_solicitacao = i.solicitacao_id
            WHERE i.pedido_id = ? ORDER BY i.id_pedido_item
        ");
        $stmt->execute([$pedidoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Recebimentos do pedido, cada um com as linhas recebidas. */
    public function recebimentos(int $pedidoId): array
    {
        $stmt = $this->conn->prepare("
            SELECT r.*, u.nome AS recebido_por_nome, ue.nome AS estornado_por_nome
            FROM recebimentos_compra r
            LEFT JOIN usuarios u ON u.id_usuario = r.recebido_por
            LEFT JOIN usuarios ue ON ue.id_usuario = r.estornado_por
            WHERE r.pedido_id = ? ORDER BY r.id_recebimento DESC
        ");
        $stmt->execute([$pedidoId]);
        $recebimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $linhas = $this->conn->prepare("
            SELECT ri.*, p.nome AS produto_nome
            FROM recebimento_itens ri
            JOIN pedido_compra_itens i ON i.id_pedido_item = ri.pedido_item_id
            JOIN produtos p ON p.id_produto = i.produto_id
            WHERE ri.recebimento_id = ? ORDER BY ri.id_recebimento_item
        ");

        foreach ($recebimentos as &$r) {
            $linhas->execute([$r['id_recebimento']]);
            $r['linhas'] = $linhas->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($r);

        return $recebimentos;
    }

    public function linhaDoTempo(int $pedidoId): array
    {
        return Historico::da($this->conn, 'PEDIDO_COMPRA', $pedidoId);
    }

    /* ── Montagem do pedido (só RASCUNHO) ────────────────────────────────── */

    /** @return array{ok:bool, erro:?string, id:?int} */
    public function criarRascunho(int $fornecedorId, array $dados, ?int $usuarioId): array
    {
        try {
            return Transacao::executar($this->conn, function () use ($fornecedorId, $dados, $usuarioId) {
                $f = $this->conn->prepare('SELECT nome, ativo, condicoes_pagamento FROM fornecedores WHERE id_fornecedor = ?');
                $f->execute([$fornecedorId]);
                $fornecedor = $f->fetch(PDO::FETCH_ASSOC);

                if (!$fornecedor || !(int) $fornecedor['ativo']) {
                    throw new DomainException('Escolha um fornecedor ativo.');
                }

                $c = $this->camposDoPedido($dados + ['condicao_pagamento' => $fornecedor['condicoes_pagamento']]);

                $this->conn->prepare("
                    INSERT INTO pedidos_compra
                        (numero, fornecedor_id, condicao_pagamento, forma_pagamento, vencimento_previsto, frete, prazo_previsto, referencia_externa, observacoes, criado_por)
                    VALUES ('', :fornecedor, :condicao, :forma, :venc, :frete, :prazo, :ref, :obs, :criador)
                ")->execute($c + [':fornecedor' => $fornecedorId, ':criador' => $usuarioId]);

                $id = (int) $this->conn->lastInsertId();
                $this->conn->prepare('UPDATE pedidos_compra SET numero = ? WHERE id_pedido = ?')
                    ->execute(['PC-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT), $id]);

                Historico::registrar($this->conn, 'PEDIDO_COMPRA', $id, null, 'RASCUNHO', $usuarioId, 'Pedido criado para ' . $fornecedor['nome']);

                return ['ok' => true, 'erro' => null, 'id' => $id];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'id' => null];
        } catch (\Throwable $e) {
            error_log('[PedidoCompra criarRascunho] ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível criar o pedido.', 'id' => null];
        }
    }

    /** @return array<string,mixed> parâmetros nomeados do INSERT/UPDATE */
    private function camposDoPedido(array $d): array
    {
        $forma = $d['forma_pagamento'] ?? '';
        $venc  = trim((string) ($d['vencimento_previsto'] ?? ''));
        $prazo = trim((string) ($d['prazo_previsto'] ?? ''));
        $frete = round((float) str_replace(',', '.', (string) ($d['frete'] ?? 0)), 2);

        if ($frete < 0) {
            throw new DomainException('O frete não pode ser negativo.');
        }

        foreach ([['vencimento', $venc], ['prazo de entrega', $prazo]] as [$rotulo, $v]) {
            if ($v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                throw new DomainException('Data inválida em ' . $rotulo . '.');
            }
        }

        return [
            ':condicao' => trim((string) ($d['condicao_pagamento'] ?? '')) ?: null,
            ':forma'    => isset(self::FORMAS_PAGAMENTO[$forma]) ? $forma : null,
            ':venc'     => $venc !== '' ? $venc : null,
            ':frete'    => $frete,
            ':prazo'    => $prazo !== '' ? $prazo : null,
            ':ref'      => mb_substr(trim((string) ($d['referencia_externa'] ?? '')), 0, 80) ?: null,
            ':obs'      => trim((string) ($d['observacoes'] ?? '')) ?: null,
        ];
    }

    public function atualizarDados(int $pedidoId, array $dados, ?int $usuarioId): array
    {
        try {
            return Transacao::executar($this->conn, function () use ($pedidoId, $dados, $usuarioId) {
                $p = $this->travar($pedidoId);

                if ($p['status'] === 'CANCELADO') {
                    throw new DomainException('Pedido cancelado não pode ser alterado.');
                }

                if ($p['status'] !== 'RASCUNHO') {
                    // Depois de realizado, as condições comerciais estão fechadas; só o número do
                    // pedido no site do fornecedor e as observações podem ser anotados.
                    $this->conn->prepare('UPDATE pedidos_compra SET referencia_externa = ?, observacoes = ? WHERE id_pedido = ?')->execute([
                        mb_substr(trim((string) ($dados['referencia_externa'] ?? '')), 0, 80) ?: null,
                        trim((string) ($dados['observacoes'] ?? '')) ?: null,
                        $pedidoId,
                    ]);

                    return ['ok' => true, 'erro' => null];
                }

                $c = $this->camposDoPedido($dados);

                $this->conn->prepare("
                    UPDATE pedidos_compra SET condicao_pagamento = :condicao, forma_pagamento = :forma, vencimento_previsto = :venc,
                        frete = :frete, prazo_previsto = :prazo, referencia_externa = :ref, observacoes = :obs
                    WHERE id_pedido = :id
                ")->execute($c + [':id' => $pedidoId]);

                return ['ok' => true, 'erro' => null];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    /**
     * Inclui uma peça no rascunho. Se vier de uma solicitação, ela passa a APROVADA_COMPRA
     * (incluí-la no pedido é a aprovação). Mesma peça + mesma solicitação não duplica a linha:
     * soma-se à existente.
     *
     * @return array{ok:bool, erro:?string, item_id:?int}
     */
    public function adicionarItem(int $pedidoId, int $produtoId, float $quantidade, float $preco, ?int $solicitacaoId, ?int $usuarioId): array
    {
        try {
            return Transacao::executar($this->conn, function () use ($pedidoId, $produtoId, $quantidade, $preco, $solicitacaoId, $usuarioId) {
                $p = $this->travar($pedidoId);

                if ($p['status'] !== 'RASCUNHO') {
                    throw new DomainException('Só dá para mexer nos itens enquanto o pedido é rascunho.');
                }

                if ($quantidade <= 0) {
                    throw new DomainException('Informe uma quantidade maior que zero.');
                }

                if ($preco < 0) {
                    throw new DomainException('O preço não pode ser negativo.');
                }

                $prod = $this->conn->prepare('SELECT nome FROM produtos WHERE id_produto = ? AND ativo = 1');
                $prod->execute([$produtoId]);

                if ($prod->fetchColumn() === false) {
                    throw new DomainException('Peça não encontrada no cadastro. Cadastre o produto antes de comprá-lo.');
                }

                if ($solicitacaoId !== null) {
                    $s = $this->conn->prepare('SELECT status FROM solicitacoes_peca WHERE id_solicitacao = ? FOR UPDATE');
                    $s->execute([$solicitacaoId]);
                    $statusSolic = $s->fetchColumn();

                    if ($statusSolic === false) {
                        throw new DomainException('Solicitação não encontrada.');
                    }

                    if (!in_array($statusSolic, ['SOLICITADA', 'EM_ANALISE', 'APROVADA_COMPRA'], true)) {
                        throw new DomainException('Essa solicitação não está aguardando compra (' . mb_strtolower(SolicitacaoPeca::rotulo($statusSolic)) . ').');
                    }

                    $r = SolicitacaoPeca::transicionar($this->conn, $solicitacaoId, 'APROVADA_COMPRA', $usuarioId, 'Incluída no pedido ' . $p['numero']);
                    if (!$r['ok']) {
                        throw new DomainException($r['erro']);
                    }
                }

                $existente = $this->conn->prepare('SELECT id_pedido_item FROM pedido_compra_itens WHERE pedido_id = ? AND produto_id = ? AND solicitacao_id <=> ?');
                $existente->execute([$pedidoId, $produtoId, $solicitacaoId]);

                if ($itemId = $existente->fetchColumn()) {
                    $this->conn->prepare('UPDATE pedido_compra_itens SET quantidade = quantidade + ?, preco_unitario = ? WHERE id_pedido_item = ?')
                        ->execute([$quantidade, $preco, $itemId]);

                    return ['ok' => true, 'erro' => null, 'item_id' => (int) $itemId];
                }

                $this->conn->prepare('INSERT INTO pedido_compra_itens (pedido_id, produto_id, solicitacao_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$pedidoId, $produtoId, $solicitacaoId, $quantidade, $preco]);

                return ['ok' => true, 'erro' => null, 'item_id' => (int) $this->conn->lastInsertId()];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'item_id' => null];
        } catch (\Throwable $e) {
            error_log('[PedidoCompra adicionarItem] ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível incluir o item.', 'item_id' => null];
        }
    }

    public function atualizarItem(int $pedidoId, int $itemId, float $quantidade, float $preco): array
    {
        try {
            return Transacao::executar($this->conn, function () use ($pedidoId, $itemId, $quantidade, $preco) {
                $p = $this->travar($pedidoId);

                if ($p['status'] !== 'RASCUNHO') {
                    throw new DomainException('Só dá para mexer nos itens enquanto o pedido é rascunho.');
                }

                if ($quantidade <= 0 || $preco < 0) {
                    throw new DomainException('Quantidade e preço precisam ser válidos.');
                }

                $this->conn->prepare('UPDATE pedido_compra_itens SET quantidade = ?, preco_unitario = ? WHERE id_pedido_item = ? AND pedido_id = ?')
                    ->execute([$quantidade, $preco, $itemId, $pedidoId]);

                return ['ok' => true, 'erro' => null];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    public function removerItem(int $pedidoId, int $itemId): array
    {
        try {
            return Transacao::executar($this->conn, function () use ($pedidoId, $itemId) {
                $p = $this->travar($pedidoId);

                if ($p['status'] !== 'RASCUNHO') {
                    throw new DomainException('Só dá para mexer nos itens enquanto o pedido é rascunho.');
                }

                $this->conn->prepare('DELETE FROM pedido_compra_itens WHERE id_pedido_item = ? AND pedido_id = ?')->execute([$itemId, $pedidoId]);

                return ['ok' => true, 'erro' => null];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    /**
     * Monta/consolida um rascunho a partir de uma cotação escolhida: reaproveita o rascunho que
     * já existe para aquele fornecedor (é assim que as necessidades se consolidam num pedido só).
     *
     * @return array{ok:bool, erro:?string, pedido_id:?int}
     */
    public function incluirCotacao(int $cotacaoId, ?int $usuarioId): array
    {
        try {
            return Transacao::executar($this->conn, function () use ($cotacaoId, $usuarioId) {
                $c = $this->conn->prepare('SELECT * FROM cotacoes_compra WHERE id_cotacao = ?');
                $c->execute([$cotacaoId]);
                $cot = $c->fetch(PDO::FETCH_ASSOC);

                if (!$cot) {
                    throw new DomainException('Cotação não encontrada.');
                }

                $produtoId = $cot['produto_id'] ? (int) $cot['produto_id'] : null;

                if ($produtoId === null && $cot['solicitacao_id']) {
                    $p = $this->conn->prepare('SELECT produto_id FROM solicitacoes_peca WHERE id_solicitacao = ?');
                    $p->execute([$cot['solicitacao_id']]);
                    $produtoId = $p->fetchColumn() ?: null;
                }

                if ($produtoId === null) {
                    throw new DomainException('Esta peça ainda não está no cadastro de produtos. Cadastre-a para poder comprá-la e dar entrada no estoque.');
                }

                $rascunho = $this->conn->prepare("SELECT id_pedido FROM pedidos_compra WHERE fornecedor_id = ? AND status = 'RASCUNHO' ORDER BY id_pedido DESC LIMIT 1");
                $rascunho->execute([$cot['fornecedor_id']]);
                $pedidoId = $rascunho->fetchColumn();

                if (!$pedidoId) {
                    $novo = $this->criarRascunho((int) $cot['fornecedor_id'], [
                        'condicao_pagamento' => $cot['condicao_pagamento'],
                        'frete'              => $cot['frete'],
                        'prazo_previsto'     => $cot['prazo_dias'] ? date('Y-m-d', strtotime('+' . (int) $cot['prazo_dias'] . ' days')) : '',
                    ], $usuarioId);

                    if (!$novo['ok']) {
                        throw new DomainException($novo['erro']);
                    }

                    $pedidoId = $novo['id'];
                }

                $r = $this->adicionarItem(
                    (int) $pedidoId, $produtoId, (float) $cot['quantidade'], (float) $cot['preco_unitario'],
                    $cot['solicitacao_id'] ? (int) $cot['solicitacao_id'] : null, $usuarioId
                );

                if (!$r['ok']) {
                    throw new DomainException($r['erro']);
                }

                return ['ok' => true, 'erro' => null, 'pedido_id' => (int) $pedidoId];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'pedido_id' => null];
        } catch (\Throwable $e) {
            error_log('[PedidoCompra incluirCotacao] ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível montar o pedido.', 'pedido_id' => null];
        }
    }

    /* ── Realizar / cancelar ─────────────────────────────────────────────── */

    /**
     * Fecha o rascunho como pedido feito ao fornecedor. NÃO altera estoque. As solicitações
     * incluídas passam a PEDIDO_REALIZADO e quem pediu é avisado.
     */
    public function realizar(int $pedidoId, ?int $usuarioId): array
    {
        try {
            return Transacao::executar($this->conn, function () use ($pedidoId, $usuarioId) {
                $p = $this->travar($pedidoId);

                if ($p['status'] === 'REALIZADO') {
                    return ['ok' => true, 'erro' => null, 'repetido' => true];
                }

                if ($p['status'] !== 'RASCUNHO') {
                    throw new DomainException('Só um rascunho pode ser realizado (este pedido está "' . mb_strtolower(self::rotulo($p['status'])) . '").');
                }

                $ativo = $this->conn->prepare('SELECT ativo FROM fornecedores WHERE id_fornecedor = ?');
                $ativo->execute([$p['fornecedor_id']]);

                if (!(int) $ativo->fetchColumn()) {
                    throw new DomainException('O fornecedor está desativado. Reative-o ou escolha outro.');
                }

                $itens = $this->conn->prepare('SELECT * FROM pedido_compra_itens WHERE pedido_id = ? ORDER BY id_pedido_item FOR UPDATE');
                $itens->execute([$pedidoId]);
                $itens = $itens->fetchAll(PDO::FETCH_ASSOC);

                if (!$itens) {
                    throw new DomainException('O pedido não tem nenhum item.');
                }

                foreach ($itens as $i) {
                    if ((float) $i['preco_unitario'] <= 0) {
                        throw new DomainException('Informe o preço de todos os itens antes de realizar o pedido.');
                    }
                }

                $this->conn->prepare("UPDATE pedidos_compra SET status = 'REALIZADO', realizado_em = NOW() WHERE id_pedido = ?")->execute([$pedidoId]);
                Historico::registrar($this->conn, 'PEDIDO_COMPRA', $pedidoId, 'RASCUNHO', 'REALIZADO', $usuarioId, null, Historico::ORIGEM_USUARIO, ['itens' => count($itens)]);

                foreach (array_unique(array_filter(array_column($itens, 'solicitacao_id'))) as $solicitacaoId) {
                    $r = SolicitacaoPeca::transicionar($this->conn, (int) $solicitacaoId, 'PEDIDO_REALIZADO', $usuarioId, 'Pedido ' . $p['numero'] . ' realizado');

                    if ($r['ok'] && !$r['repetido']) {
                        $s = $this->conn->prepare('SELECT numero, solicitante_id, descricao FROM solicitacoes_peca WHERE id_solicitacao = ?');
                        $s->execute([$solicitacaoId]);
                        $s = $s->fetch(PDO::FETCH_ASSOC);

                        if ($s && (int) $s['solicitante_id'] !== $usuarioId) {
                            Notificacao::criar($this->conn, (int) $s['solicitante_id'], 'SOLICITACAO_PECA', 'Pedido feito · ' . $s['numero'],
                                $s['descricao'] . ' foi pedida ao fornecedor.', './viewSolicitacao?id=' . $solicitacaoId, 'SOLICITACAO:' . $solicitacaoId . ':PEDIDO_REALIZADO');
                        }
                    }
                }

                return ['ok' => true, 'erro' => null, 'repetido' => false];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'repetido' => false];
        } catch (\Throwable $e) {
            error_log('[PedidoCompra realizar] ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível realizar o pedido.', 'repetido' => false];
        }
    }

    /** Cancela um pedido que ainda não recebeu nada. Estoque não é tocado (nunca subiu). */
    public function cancelar(int $pedidoId, ?int $usuarioId, string $motivo): array
    {
        $motivo = trim($motivo);

        try {
            return Transacao::executar($this->conn, function () use ($pedidoId, $usuarioId, $motivo) {
                $p = $this->travar($pedidoId);

                if ($p['status'] === 'CANCELADO') {
                    return ['ok' => true, 'erro' => null, 'repetido' => true];
                }

                if (mb_strlen($motivo) < 3) {
                    throw new DomainException('Informe o motivo do cancelamento.');
                }

                if (!in_array($p['status'], ['RASCUNHO', 'REALIZADO'], true)) {
                    throw new DomainException(
                        $p['status'] === 'RECEBIDO'
                            ? 'Pedido já recebido não se cancela. Se algo veio errado, estorne o recebimento.'
                            : 'Este pedido já recebeu mercadoria. Estorne os recebimentos ou encerre o saldo.'
                    );
                }

                $recebidos = $this->conn->prepare('SELECT COUNT(*) FROM recebimentos_compra WHERE pedido_id = ? AND estornado_em IS NULL');
                $recebidos->execute([$pedidoId]);

                if ((int) $recebidos->fetchColumn() > 0) {
                    throw new DomainException('Este pedido tem recebimento registrado; estorne-o antes de cancelar.');
                }

                $this->conn->prepare("UPDATE pedidos_compra SET status = 'CANCELADO', motivo_encerramento = ? WHERE id_pedido = ?")->execute([mb_substr($motivo, 0, 255), $pedidoId]);
                Historico::registrar($this->conn, 'PEDIDO_COMPRA', $pedidoId, $p['status'], 'CANCELADO', $usuarioId, $motivo);

                // Quem estava esperando por este pedido volta a "aprovada para compra".
                $itens = $this->conn->prepare('SELECT DISTINCT solicitacao_id FROM pedido_compra_itens WHERE pedido_id = ? AND solicitacao_id IS NOT NULL');
                $itens->execute([$pedidoId]);

                foreach ($itens->fetchAll(PDO::FETCH_COLUMN) as $solicitacaoId) {
                    SolicitacaoPeca::transicionar($this->conn, (int) $solicitacaoId, 'APROVADA_COMPRA', $usuarioId, 'Pedido ' . $p['numero'] . ' cancelado', Historico::ORIGEM_SISTEMA);
                }

                return ['ok' => true, 'erro' => null, 'repetido' => false];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'repetido' => false];
        } catch (\Throwable $e) {
            error_log('[PedidoCompra cancelar] ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível cancelar o pedido.', 'repetido' => false];
        }
    }

    /** Pedido parcialmente recebido cujo saldo não virá: encerra sem cancelar o que já chegou. */
    public function encerrarComSaldo(int $pedidoId, ?int $usuarioId, string $motivo): array
    {
        $motivo = trim($motivo);

        try {
            return Transacao::executar($this->conn, function () use ($pedidoId, $usuarioId, $motivo) {
                $p = $this->travar($pedidoId);

                if ($p['status'] === 'RECEBIDO') {
                    return ['ok' => true, 'erro' => null, 'repetido' => true];
                }

                if ($p['status'] !== 'PARCIALMENTE_RECEBIDO') {
                    throw new DomainException('Só um pedido parcialmente recebido pode ser encerrado com saldo.');
                }

                if (mb_strlen($motivo) < 3) {
                    throw new DomainException('Informe por que o saldo não será entregue.');
                }

                $this->conn->prepare("UPDATE pedidos_compra SET status = 'RECEBIDO', recebido_em = NOW(), motivo_encerramento = ? WHERE id_pedido = ?")
                    ->execute([mb_substr($motivo, 0, 255), $pedidoId]);
                Historico::registrar($this->conn, 'PEDIDO_COMPRA', $pedidoId, 'PARCIALMENTE_RECEBIDO', 'RECEBIDO', $usuarioId, 'Encerrado com saldo não entregue: ' . $motivo);

                return ['ok' => true, 'erro' => null, 'repetido' => false];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'repetido' => false];
        }
    }

    /* ── Recebimento ─────────────────────────────────────────────────────── */

    /**
     * Confirma a chegada de mercadoria. É AQUI que o estoque sobe.
     *
     * @param array<int,array{item_id:int, quantidade:mixed, custo?:mixed}> $linhas só as com quantidade > 0 contam
     * @param array{chave:string, documento_fiscal?:string, observacao?:string} $dados
     * @return array{ok:bool, erro:?string, repetido:bool, id:?int}
     */
    public function receber(int $pedidoId, array $linhas, array $dados, ?int $usuarioId): array
    {
        $saida = ['ok' => false, 'erro' => null, 'repetido' => false, 'id' => null];
        $chave = trim((string) ($dados['chave'] ?? ''));

        try {
            return Transacao::executar($this->conn, function () use ($pedidoId, $linhas, $dados, $usuarioId, $saida, $chave) {
                if (mb_strlen($chave) < 8) {
                    throw new DomainException('Envio inválido. Recarregue a página e confirme o recebimento de novo.');
                }

                $p = $this->travar($pedidoId);

                // Mesmo envio repetido (duplo clique, reenvio do navegador): devolve o que já foi feito.
                $jaFeito = $this->conn->prepare('SELECT id_recebimento, pedido_id FROM recebimentos_compra WHERE chave_idempotencia = ?');
                $jaFeito->execute([$chave]);

                if ($r = $jaFeito->fetch(PDO::FETCH_ASSOC)) {
                    if ((int) $r['pedido_id'] !== $pedidoId) {
                        throw new DomainException('Esta chave de envio já foi usada em outro pedido.');
                    }

                    return array_merge($saida, ['ok' => true, 'repetido' => true, 'id' => (int) $r['id_recebimento']]);
                }

                if (!in_array($p['status'], ['REALIZADO', 'PARCIALMENTE_RECEBIDO'], true)) {
                    throw new DomainException(
                        $p['status'] === 'RASCUNHO'
                            ? 'Realize o pedido antes de registrar o recebimento.'
                            : 'Este pedido está "' . mb_strtolower(self::rotulo($p['status'])) . '" e não aceita novos recebimentos.'
                    );
                }

                // Itens travados em ordem fixa.
                $itensStmt = $this->conn->prepare('SELECT * FROM pedido_compra_itens WHERE pedido_id = ? ORDER BY id_pedido_item FOR UPDATE');
                $itensStmt->execute([$pedidoId]);
                $itens = [];
                foreach ($itensStmt->fetchAll(PDO::FETCH_ASSOC) as $i) {
                    $itens[(int) $i['id_pedido_item']] = $i;
                }

                $aReceber = [];
                foreach ($linhas as $l) {
                    $itemId = (int) ($l['item_id'] ?? 0);
                    $qtd    = round((float) str_replace(',', '.', (string) ($l['quantidade'] ?? 0)), 3);

                    if ($qtd <= 0) {
                        continue;
                    }

                    if (!isset($itens[$itemId])) {
                        throw new DomainException('Um dos itens informados não pertence a este pedido.');
                    }

                    $falta = round((float) $itens[$itemId]['quantidade'] - (float) $itens[$itemId]['quantidade_recebida'], 3);

                    if ($qtd > $falta + 0.0005) {
                        throw new DomainException(sprintf('Quantidade recebida maior que a que falta chegar (%s).', self::fmt($falta)));
                    }

                    $custo = isset($l['custo']) && $l['custo'] !== '' ? round((float) str_replace(',', '.', (string) $l['custo']), 2) : (float) $itens[$itemId]['preco_unitario'];

                    if ($custo < 0) {
                        throw new DomainException('Custo unitário inválido.');
                    }

                    $aReceber[$itemId] = ['qtd' => $qtd, 'custo' => $custo];
                }

                if (!$aReceber) {
                    throw new DomainException('Informe a quantidade recebida de pelo menos um item.');
                }

                $this->conn->prepare('INSERT INTO recebimentos_compra (pedido_id, chave_idempotencia, documento_fiscal, observacao, recebido_por) VALUES (?, ?, ?, ?, ?)')->execute([
                    $pedidoId, $chave,
                    mb_substr(trim((string) ($dados['documento_fiscal'] ?? '')), 0, 60) ?: null,
                    mb_substr(trim((string) ($dados['observacao'] ?? '')), 0, 255) ?: null,
                    $this->usuarioValido($usuarioId),
                ]);
                $recebimentoId = (int) $this->conn->lastInsertId();

                // Produtos em ordem de id: dois recebimentos cruzados não se travam.
                $porProduto = [];
                foreach ($aReceber as $itemId => $l) {
                    $porProduto[(int) $itens[$itemId]['produto_id']][] = $itemId;
                }
                ksort($porProduto);

                foreach ($porProduto as $produtoId => $itemIds) {
                    $prod = $this->conn->prepare('SELECT estoque_atual, preco_custo FROM produtos WHERE id_produto = ? FOR UPDATE');
                    $prod->execute([$produtoId]);
                    $prod = $prod->fetch(PDO::FETCH_ASSOC);

                    $estoque = (float) $prod['estoque_atual'];
                    $custoMedio = (float) $prod['preco_custo'];

                    foreach ($itemIds as $itemId) {
                        $qtd = $aReceber[$itemId]['qtd'];
                        $custo = $aReceber[$itemId]['custo'];

                        // Custo médio ponderado; sem saldo antes, o custo é o da compra.
                        $custoMedio = $estoque > 0 ? round((($estoque * $custoMedio) + ($qtd * $custo)) / ($estoque + $qtd), 2) : $custo;
                        $estoque += $qtd;

                        $movId = MovimentacaoEstoque::registrarSemAjustarEstoque($this->conn, [
                            'produto_id'     => $produtoId,
                            'tipo'           => 'ENTRADA',
                            'origem'         => 'COMPRA',
                            'evento'         => 'RECEBIMENTO',
                            'quantidade'     => $qtd,
                            'custo_unitario' => $custo,
                            'usuario_id'     => $usuarioId,
                            'observacao'     => 'Recebimento do pedido ' . $p['numero'],
                        ]);

                        $this->conn->prepare('INSERT INTO recebimento_itens (recebimento_id, pedido_item_id, quantidade, custo_unitario, movimentacao_id) VALUES (?, ?, ?, ?, ?)')
                            ->execute([$recebimentoId, $itemId, $qtd, $custo, $movId ?: null]);

                        $this->conn->prepare('UPDATE pedido_compra_itens SET quantidade_recebida = quantidade_recebida + ? WHERE id_pedido_item = ?')->execute([$qtd, $itemId]);
                    }

                    $this->conn->prepare('UPDATE produtos SET estoque_atual = ?, preco_custo = ?, atualizado_em = NOW() WHERE id_produto = ?')
                        ->execute([$estoque, $custoMedio, $produtoId]);
                }

                $novo = $this->recalcularStatus($pedidoId, $p['status']);
                Historico::registrar($this->conn, 'PEDIDO_COMPRA', $pedidoId, $p['status'], $novo, $usuarioId, 'Recebimento #' . $recebimentoId, Historico::ORIGEM_USUARIO, [
                    'recebimento_id' => $recebimentoId, 'linhas' => count($aReceber),
                ]);

                $this->reconciliarSolicitacoes($pedidoId, $usuarioId);

                return array_merge($saida, ['ok' => true, 'id' => $recebimentoId]);
            });
        } catch (DomainException $e) {
            return array_merge($saida, ['erro' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[PedidoCompra receber] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            return array_merge($saida, ['erro' => 'Não foi possível registrar o recebimento. Nada foi gravado.']);
        }
    }

    /**
     * Desfaz um recebimento lançado errado: tira as peças do estoque (só se ainda estiverem
     * lá), devolve as quantidades ao pedido e recalcula tudo que dependia dele. A entrada
     * original continua no livro; o estorno é uma nova linha, com justificativa e autoria.
     */
    public function estornarRecebimento(int $recebimentoId, ?int $usuarioId, string $justificativa): array
    {
        $justificativa = trim($justificativa);
        $saida = ['ok' => false, 'erro' => null, 'repetido' => false, 'pedido_id' => null];

        try {
            return Transacao::executar($this->conn, function () use ($recebimentoId, $usuarioId, $justificativa, $saida) {
                $r = $this->conn->prepare('SELECT * FROM recebimentos_compra WHERE id_recebimento = ?');
                $r->execute([$recebimentoId]);
                $rec = $r->fetch(PDO::FETCH_ASSOC);

                if (!$rec) {
                    throw new DomainException('Recebimento não encontrado.');
                }

                $pedidoId = (int) $rec['pedido_id'];
                $p = $this->travar($pedidoId);

                $r = $this->conn->prepare('SELECT estornado_em FROM recebimentos_compra WHERE id_recebimento = ? FOR UPDATE');
                $r->execute([$recebimentoId]);

                if ($r->fetchColumn() !== null) {
                    return array_merge($saida, ['ok' => true, 'repetido' => true, 'pedido_id' => $pedidoId]);
                }

                if (mb_strlen($justificativa) < 5) {
                    throw new DomainException('Informe o motivo do estorno do recebimento.');
                }

                $linhas = $this->conn->prepare("
                    SELECT ri.*, i.produto_id FROM recebimento_itens ri
                    JOIN pedido_compra_itens i ON i.id_pedido_item = ri.pedido_item_id
                    WHERE ri.recebimento_id = ? ORDER BY i.produto_id, ri.id_recebimento_item
                ");
                $linhas->execute([$recebimentoId]);
                $linhas = $linhas->fetchAll(PDO::FETCH_ASSOC);

                // Trava os itens do pedido e depois os produtos (por id), como no recebimento.
                $this->conn->prepare('SELECT id_pedido_item FROM pedido_compra_itens WHERE pedido_id = ? ORDER BY id_pedido_item FOR UPDATE')->execute([$pedidoId]);

                $saldo = [];
                foreach ($linhas as $l) {
                    $produtoId = (int) $l['produto_id'];

                    if (!isset($saldo[$produtoId])) {
                        $s = $this->conn->prepare('SELECT estoque_atual FROM produtos WHERE id_produto = ? FOR UPDATE');
                        $s->execute([$produtoId]);
                        $saldo[$produtoId] = (float) $s->fetchColumn();
                    }

                    $saldo[$produtoId] -= (float) $l['quantidade'];

                    if ($saldo[$produtoId] < -0.0005) {
                        throw new DomainException('Não dá para estornar: parte das peças recebidas já saiu do estoque (uso em OS ou saída). Corrija com um ajuste.');
                    }
                }

                foreach ($linhas as $l) {
                    MovimentacaoEstoque::registrarSemAjustarEstoque($this->conn, [
                        'produto_id'     => (int) $l['produto_id'],
                        'tipo'           => 'SAIDA',
                        'origem'         => 'ESTORNO',
                        'quantidade'     => (float) $l['quantidade'],
                        'delta'          => -(float) $l['quantidade'],
                        'custo_unitario' => (float) $l['custo_unitario'],
                        'usuario_id'     => $usuarioId,
                        'observacao'     => 'Estorno do recebimento #' . $recebimentoId . ' do pedido ' . $p['numero'],
                        'estorno_de_id'  => $l['movimentacao_id'] ?: null,
                        'justificativa'  => $justificativa,
                    ]);

                    $this->conn->prepare('UPDATE produtos SET estoque_atual = estoque_atual - ?, atualizado_em = NOW() WHERE id_produto = ?')
                        ->execute([(float) $l['quantidade'], (int) $l['produto_id']]);
                    $this->conn->prepare('UPDATE pedido_compra_itens SET quantidade_recebida = quantidade_recebida - ? WHERE id_pedido_item = ?')
                        ->execute([(float) $l['quantidade'], (int) $l['pedido_item_id']]);
                }

                $this->conn->prepare('UPDATE recebimentos_compra SET estornado_em = NOW(), estornado_por = ?, estorno_justificativa = ? WHERE id_recebimento = ?')
                    ->execute([$this->usuarioValido($usuarioId), mb_substr($justificativa, 0, 255), $recebimentoId]);

                $novo = $this->recalcularStatus($pedidoId, $p['status']);
                Historico::registrar($this->conn, 'PEDIDO_COMPRA', $pedidoId, $p['status'], $novo, $usuarioId, 'Recebimento #' . $recebimentoId . ' estornado: ' . $justificativa);

                $this->reconciliarSolicitacoes($pedidoId, $usuarioId);

                return array_merge($saida, ['ok' => true, 'pedido_id' => $pedidoId]);
            });
        } catch (DomainException $e) {
            return array_merge($saida, ['erro' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[PedidoCompra estornarRecebimento] ' . $e->getMessage());
            return array_merge($saida, ['erro' => 'Não foi possível estornar o recebimento.']);
        }
    }

    /* ── Internos ────────────────────────────────────────────────────────── */

    private function travar(int $pedidoId): array
    {
        $stmt = $this->conn->prepare('SELECT * FROM pedidos_compra WHERE id_pedido = ? FOR UPDATE');
        $stmt->execute([$pedidoId]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$p) {
            throw new DomainException('Pedido de compra não encontrado.');
        }

        return $p;
    }

    /** RECEBIDO se tudo chegou, PARCIALMENTE_RECEBIDO se chegou algo, REALIZADO se nada. Devolve o novo estado. */
    private function recalcularStatus(int $pedidoId, string $anterior): string
    {
        $stmt = $this->conn->prepare('SELECT COALESCE(SUM(quantidade), 0), COALESCE(SUM(quantidade_recebida), 0), COALESCE(SUM(quantidade_recebida >= quantidade), 0), COUNT(*) FROM pedido_compra_itens WHERE pedido_id = ?');
        $stmt->execute([$pedidoId]);
        [$pedida, $recebida, $completos, $n] = array_map('floatval', $stmt->fetch(PDO::FETCH_NUM));

        // Pedido encerrado com saldo (motivo registrado) continua RECEBIDO mesmo faltando mercadoria.
        $motivo = $this->conn->prepare('SELECT motivo_encerramento FROM pedidos_compra WHERE id_pedido = ?');
        $motivo->execute([$pedidoId]);
        $encerrado = $anterior === 'RECEBIDO' && $recebida > 0 && $motivo->fetchColumn() !== null;

        $novo = $recebida <= 0.0005 ? 'REALIZADO' : (($n > 0 && $completos >= $n) || $encerrado ? 'RECEBIDO' : 'PARCIALMENTE_RECEBIDO');

        $this->conn->prepare('UPDATE pedidos_compra SET status = ?, recebido_em = ? WHERE id_pedido = ?')
            ->execute([$novo, $novo === 'RECEBIDO' ? date('Y-m-d H:i:s') : null, $pedidoId]);

        return $novo;
    }

    private function reconciliarSolicitacoes(int $pedidoId, ?int $usuarioId): void
    {
        $ids = $this->conn->prepare('SELECT DISTINCT solicitacao_id FROM pedido_compra_itens WHERE pedido_id = ? AND solicitacao_id IS NOT NULL ORDER BY solicitacao_id');
        $ids->execute([$pedidoId]);

        foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $solicitacaoId) {
            SolicitacaoPeca::reconciliarComRecebimentos($this->conn, (int) $solicitacaoId, $usuarioId);
        }
    }

    private function usuarioValido(?int $id): ?int
    {
        if (!$id) {
            return null;
        }

        $stmt = $this->conn->prepare('SELECT 1 FROM usuarios WHERE id_usuario = ?');
        $stmt->execute([$id]);

        return $stmt->fetchColumn() ? $id : null;
    }

    private static function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, ',', '.'), '0'), ',');
    }
}
