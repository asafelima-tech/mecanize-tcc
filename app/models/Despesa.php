<?php

require_once __DIR__ . '/../core/Transacao.php';
require_once __DIR__ . '/../core/Historico.php';
require_once __DIR__ . '/../core/Dinheiro.php';
require_once __DIR__ . '/CartaoCorporativo.php';

/**
 * Despesa: a COMPRA ou o gasto, registrada uma vez — o evento econômico.
 *
 * Ela gera as OBRIGAÇÕES (contas_pagar_parcelas) e, quando alguém paga, o DINHEIRO que saiu
 * (pagamentos_pagar, ver ContaPagar). São três etapas da mesma despesa, nunca três despesas:
 *
 *   compra de R$ 600 no cartão em 3x  → 1 despesa · 3 parcelas de R$ 200 (uma por fatura)
 *                                       · 1 pagamento por FATURA paga (que quita as parcelas dela)
 *
 * Por isso o total de "despesas pagas" soma os PAGAMENTOS, e o de "a pagar" soma as PARCELAS
 * pendentes: a mesma compra não aparece duas vezes em nenhum dos dois.
 *
 * Categoria ESTOQUE é compra de peça para revender nas OS: não é resultado até a peça ser
 * consumida (o custo entra pelo livro de estoque). Se contasse nas duas pontas, o custo apareceria
 * em dobro no resultado.
 */
class Despesa
{
    /** categoria => [rótulo, afeta o resultado?] */
    public const CATEGORIAS = [
        'ESTOQUE'     => ['Peças e materiais para estoque', false],
        'ALUGUEL'     => ['Aluguel', true],
        'ENERGIA_AGUA' => ['Energia e água', true],
        'INTERNET_TELEFONE' => ['Internet e telefone', true],
        'FOLHA'       => ['Folha e encargos', true],
        'IMPOSTOS'    => ['Impostos e taxas', true],
        'MANUTENCAO'  => ['Manutenção e ferramentas', true],
        'MARKETING'   => ['Marketing', true],
        'TARIFAS'     => ['Tarifas bancárias e financeiras', true],
        'OUTRAS'      => ['Outras despesas', true],
    ];

    public const MEIOS = [
        'CARTAO_CREDITO' => 'Cartão corporativo',
        'BOLETO'         => 'Boleto',
        'PIX'            => 'PIX',
        'TRANSFERENCIA'  => 'Transferência',
        'DINHEIRO'       => 'Dinheiro',
        'CARTAO_DEBITO'  => 'Cartão de débito',
        'OUTRO'          => 'Outro',
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

    public static function rotuloCategoria(?string $c): string
    {
        return self::CATEGORIAS[$c][0] ?? (string) $c;
    }

    /* ── Consulta ────────────────────────────────────────────────────────── */

    private const SELECT_BASE = "
        SELECT d.*, f.nome AS fornecedor_nome, c.apelido AS cartao_apelido, u.nome AS criado_por_nome,
               COALESCE((SELECT SUM(p.valor) FROM contas_pagar_parcelas p WHERE p.despesa_id = d.id_despesa AND p.status = 'PAGA'), 0) AS valor_pago,
               COALESCE((SELECT SUM(p.valor) FROM contas_pagar_parcelas p WHERE p.despesa_id = d.id_despesa AND p.status = 'PENDENTE'), 0) AS valor_pendente,
               (SELECT COUNT(*) FROM contas_pagar_parcelas p WHERE p.despesa_id = d.id_despesa AND p.status = 'PENDENTE') AS parcelas_pendentes,
               (SELECT MIN(p.vencimento) FROM contas_pagar_parcelas p WHERE p.despesa_id = d.id_despesa AND p.status = 'PENDENTE') AS proximo_vencimento
        FROM despesas d
        LEFT JOIN fornecedores f ON f.id_fornecedor = d.fornecedor_id
        LEFT JOIN cartoes_corporativos c ON c.id_cartao = d.cartao_id
        LEFT JOIN usuarios u ON u.id_usuario = d.criado_por
    ";

    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare(self::SELECT_BASE . ' WHERE d.id_despesa = ?');
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @param array{situacao?:string, categoria?:string, fornecedor_id?:int, cartao_id?:int, busca?:string, de?:string, ate?:string} $f */
    public function listar(array $f = [], int $limite = 200): array
    {
        $where = [];
        $params = [];

        if (($f['situacao'] ?? '') === 'CANCELADA') {
            $where[] = "d.status = 'CANCELADA'";
        } else {
            $where[] = "d.status = 'ATIVA'";

            if (($f['situacao'] ?? '') === 'PENDENTE') {
                $where[] = "EXISTS (SELECT 1 FROM contas_pagar_parcelas p WHERE p.despesa_id = d.id_despesa AND p.status = 'PENDENTE')";
            } elseif (($f['situacao'] ?? '') === 'QUITADA') {
                $where[] = "NOT EXISTS (SELECT 1 FROM contas_pagar_parcelas p WHERE p.despesa_id = d.id_despesa AND p.status = 'PENDENTE')";
            }
        }

        foreach (['categoria' => 'd.categoria', 'fornecedor_id' => 'd.fornecedor_id', 'cartao_id' => 'd.cartao_id'] as $chave => $coluna) {
            if (!empty($f[$chave])) {
                $where[] = "{$coluna} = ?";
                $params[] = $f[$chave];
            }
        }

        if (!empty($f['de'])) {
            $where[] = 'd.data_compra >= ?';
            $params[] = $f['de'];
        }

        if (!empty($f['ate'])) {
            $where[] = 'd.data_compra <= ?';
            $params[] = $f['ate'];
        }

        if (!empty($f['busca'])) {
            $where[] = '(d.descricao LIKE ? OR d.numero LIKE ? OR f.nome LIKE ?)';
            $t = '%' . trim($f['busca']) . '%';
            array_push($params, $t, $t, $t);
        }

        $stmt = $this->conn->prepare(self::SELECT_BASE . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY d.data_compra DESC, d.id_despesa DESC LIMIT ' . (int) $limite);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function parcelas(int $despesaId): array
    {
        $stmt = $this->conn->prepare("
            SELECT p.*, f.referencia AS fatura_referencia, f.fecha_em AS fatura_fecha_em, f.vence_em AS fatura_vence_em, f.paga_em AS fatura_paga_em
            FROM contas_pagar_parcelas p
            LEFT JOIN faturas_cartao f ON f.id_fatura = p.fatura_id
            WHERE p.despesa_id = ? ORDER BY p.numero
        ");
        $stmt->execute([$despesaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Pagamentos que quitaram parcelas desta despesa (direto ou pela fatura do cartão). */
    public function pagamentos(int $despesaId): array
    {
        $stmt = $this->conn->prepare("
            SELECT DISTINCT pg.*, u.nome AS usuario_nome, ue.nome AS estornado_por_nome
            FROM pagamentos_pagar pg
            LEFT JOIN usuarios u ON u.id_usuario = pg.usuario_id
            LEFT JOIN usuarios ue ON ue.id_usuario = pg.estornado_por
            WHERE pg.id_pagamento IN (SELECT pagamento_id FROM contas_pagar_parcelas WHERE despesa_id = :d AND pagamento_id IS NOT NULL)
               OR pg.parcela_id IN (SELECT id_conta_parcela FROM contas_pagar_parcelas WHERE despesa_id = :d2)
               OR pg.fatura_id IN (SELECT fatura_id FROM contas_pagar_parcelas WHERE despesa_id = :d3 AND fatura_id IS NOT NULL)
            ORDER BY pg.id_pagamento DESC
        ");
        $stmt->execute([':d' => $despesaId, ':d2' => $despesaId, ':d3' => $despesaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function linhaDoTempo(int $id): array
    {
        return Historico::da($this->conn, 'DESPESA', $id);
    }

    /* ── Registro ────────────────────────────────────────────────────────── */

    /**
     * Registra a despesa e gera as parcelas.
     *
     * $d: descricao, categoria, valor_total, data_compra, meio_pagamento, qtd_parcelas,
     *     [fornecedor_id], [cartao_id] (obrigatório no cartão), [primeiro_vencimento] (fora do cartão),
     *     [observacoes], [chave] (idempotência do formulário), [origem, origem_id] (uso interno).
     *
     * Cartão: cada parcela cai na fatura do seu mês e vence com ela. Fora do cartão: parcelas
     * mensais a partir do primeiro vencimento (padrão: a própria data da compra, ou +30 dias se
     * parcelado). A despesa nunca nasce "paga": pagar é um passo à parte (ContaPagar).
     *
     * @return array{ok:bool, erro:?string, id:?int, repetido:bool}
     */
    public function criar(array $d, ?int $usuarioId): array
    {
        $saida = ['ok' => false, 'erro' => null, 'id' => null, 'repetido' => false];

        try {
            return Transacao::executar($this->conn, function () use ($d, $usuarioId, $saida) {
                $chave = trim((string) ($d['chave'] ?? '')) ?: null;

                if ($chave !== null) {
                    $ja = $this->conn->prepare('SELECT id_despesa FROM despesas WHERE chave_idempotencia = ?');
                    $ja->execute([$chave]);

                    if ($id = $ja->fetchColumn()) {
                        return array_merge($saida, ['ok' => true, 'id' => (int) $id, 'repetido' => true]);
                    }
                }

                $descricao = trim((string) ($d['descricao'] ?? ''));
                if (mb_strlen($descricao) < 3) {
                    throw new DomainException('Descreva a despesa.');
                }

                $categoria = (string) ($d['categoria'] ?? '');
                if (!isset(self::CATEGORIAS[$categoria])) {
                    throw new DomainException('Escolha a categoria da despesa.');
                }

                $valor = Dinheiro::parse($d['valor_total'] ?? 0);
                if ($valor <= 0) {
                    throw new DomainException('Informe um valor maior que zero.');
                }

                $dataCompra = (string) ($d['data_compra'] ?? date('Y-m-d'));
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataCompra) || strtotime($dataCompra) === false) {
                    throw new DomainException('Data da compra inválida.');
                }

                $meio = (string) ($d['meio_pagamento'] ?? '');
                if (!isset(self::MEIOS[$meio])) {
                    throw new DomainException('Escolha o meio de pagamento.');
                }

                $n = (int) ($d['qtd_parcelas'] ?? 1);
                if ($n < 1 || $n > 48) {
                    throw new DomainException('O número de parcelas vai de 1 a 48.');
                }

                $cartao = null;
                if ($meio === 'CARTAO_CREDITO') {
                    $c = $this->conn->prepare('SELECT * FROM cartoes_corporativos WHERE id_cartao = ? AND ativo = 1');
                    $c->execute([(int) ($d['cartao_id'] ?? 0)]);
                    $cartao = $c->fetch(PDO::FETCH_ASSOC);

                    if (!$cartao) {
                        throw new DomainException('Escolha o cartão corporativo da compra.');
                    }
                } elseif ($n > 1 && in_array($meio, ['DINHEIRO', 'PIX', 'CARTAO_DEBITO'], true)) {
                    throw new DomainException('Parcelamento só se aplica a cartão de crédito, boleto ou transferência.');
                }

                $fornecedorId = !empty($d['fornecedor_id']) ? (int) $d['fornecedor_id'] : null;
                if ($fornecedorId !== null) {
                    $f = $this->conn->prepare('SELECT 1 FROM fornecedores WHERE id_fornecedor = ?');
                    $f->execute([$fornecedorId]);

                    if (!$f->fetchColumn()) {
                        throw new DomainException('Fornecedor não encontrado.');
                    }
                }

                $origem = ($d['origem'] ?? 'MANUAL') === 'PEDIDO_COMPRA' ? 'PEDIDO_COMPRA' : 'MANUAL';

                $this->conn->prepare("
                    INSERT INTO despesas
                        (numero, descricao, categoria, afeta_resultado, fornecedor_id, origem, origem_id, valor_total, data_compra,
                         meio_pagamento, cartao_id, qtd_parcelas, observacoes, chave_idempotencia, criado_por)
                    VALUES ('', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    mb_substr($descricao, 0, 200), $categoria, self::CATEGORIAS[$categoria][1] ? 1 : 0, $fornecedorId,
                    $origem, $origem === 'PEDIDO_COMPRA' ? (int) ($d['origem_id'] ?? 0) : null,
                    $valor, $dataCompra, $meio, $cartao ? (int) $cartao['id_cartao'] : null, $n,
                    trim((string) ($d['observacoes'] ?? '')) ?: null, $chave, $usuarioId,
                ]);

                $id = (int) $this->conn->lastInsertId();
                $this->conn->prepare('UPDATE despesas SET numero = ? WHERE id_despesa = ?')->execute(['DP-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT), $id]);

                $this->gerarParcelas($id, $valor, $n, $dataCompra, $cartao, (string) ($d['primeiro_vencimento'] ?? ''));

                Historico::registrar($this->conn, 'DESPESA', $id, null, 'ATIVA', $usuarioId, mb_substr($descricao, 0, 200), Historico::ORIGEM_USUARIO, [
                    'valor' => $valor, 'parcelas' => $n, 'meio' => $meio, 'origem' => $origem,
                ]);

                return array_merge($saida, ['ok' => true, 'id' => $id]);
            });
        } catch (DomainException $e) {
            return array_merge($saida, ['erro' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[Despesa criar] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            return array_merge($saida, ['erro' => 'Não foi possível registrar a despesa. Nada foi gravado.']);
        }
    }

    private function gerarParcelas(int $despesaId, float $valor, int $n, string $dataCompra, ?array $cartao, string $primeiroVencimento): void
    {
        $valores = Dinheiro::dividir($valor, $n);
        $insert = $this->conn->prepare('INSERT INTO contas_pagar_parcelas (despesa_id, numero, valor, vencimento, fatura_id) VALUES (?, ?, ?, ?, ?)');

        if ($primeiroVencimento !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $primeiroVencimento)) {
            throw new DomainException('Data do primeiro vencimento inválida.');
        }

        $primeiro = $primeiroVencimento !== '' ? $primeiroVencimento : ($n > 1 ? Dinheiro::somarMeses($dataCompra, 1) : $dataCompra);

        foreach ($valores as $i => $v) {
            if ($cartao) {
                $fatura = CartaoCorporativo::faturaParaParcela($this->conn, $cartao, $dataCompra, $i);
                $insert->execute([$despesaId, $i + 1, $v, $fatura['vence_em'], (int) $fatura['id_fatura']]);
            } else {
                $insert->execute([$despesaId, $i + 1, $v, Dinheiro::somarMeses($primeiro, $i), null]);
            }
        }
    }

    /**
     * Cancela a despesa — só se nada dela foi pago (parcela quitada, direta ou pela fatura). Quem
     * já pagou estorna o pagamento primeiro: assim o dinheiro que saiu nunca fica órfão. As
     * parcelas pendentes são canceladas (saem de "a pagar" e liberam o limite do cartão) e a
     * ligação com o pedido de compra é desfeita, para ele poder gerar outra conta.
     */
    public function cancelar(int $id, ?int $usuarioId, string $motivo): array
    {
        $motivo = trim($motivo);

        try {
            return Transacao::executar($this->conn, function () use ($id, $usuarioId, $motivo) {
                $stmt = $this->conn->prepare('SELECT * FROM despesas WHERE id_despesa = ? FOR UPDATE');
                $stmt->execute([$id]);
                $d = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$d) {
                    throw new DomainException('Despesa não encontrada.');
                }

                if ($d['status'] === 'CANCELADA') {
                    return ['ok' => true, 'erro' => null, 'repetido' => true];
                }

                if (mb_strlen($motivo) < 3) {
                    throw new DomainException('Informe o motivo do cancelamento.');
                }

                $pagas = $this->conn->prepare("SELECT COUNT(*) FROM contas_pagar_parcelas WHERE despesa_id = ? AND status = 'PAGA'");
                $pagas->execute([$id]);

                if ((int) $pagas->fetchColumn() > 0) {
                    throw new DomainException('Esta despesa já tem parcela paga. Estorne o pagamento antes de cancelar.');
                }

                $this->conn->prepare("UPDATE contas_pagar_parcelas SET status = 'CANCELADA' WHERE despesa_id = ? AND status = 'PENDENTE'")->execute([$id]);
                $this->conn->prepare("UPDATE despesas SET status = 'CANCELADA', cancelada_em = NOW(), cancelada_por = ?, cancelamento_motivo = ? WHERE id_despesa = ?")
                    ->execute([$usuarioId, mb_substr($motivo, 0, 255), $id]);
                $this->conn->prepare('UPDATE pedidos_compra SET conta_pagar_id = NULL WHERE conta_pagar_id = ?')->execute([$id]);

                Historico::registrar($this->conn, 'DESPESA', $id, 'ATIVA', 'CANCELADA', $usuarioId, $motivo);

                return ['ok' => true, 'erro' => null, 'repetido' => false];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'repetido' => false];
        } catch (\Throwable $e) {
            error_log('[Despesa cancelar] ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível cancelar a despesa.', 'repetido' => false];
        }
    }

    /**
     * Gera a conta a pagar de um pedido de compra (uma só por pedido ativo — o índice único do
     * banco garante). Valor padrão: o total do pedido (itens + frete); o administrador ajusta ao
     * valor da nota. Categoria ESTOQUE.
     *
     * @return array{ok:bool, erro:?string, id:?int, repetido:bool}
     */
    public function criarDoPedido(int $pedidoId, array $d, ?int $usuarioId): array
    {
        $saida = ['ok' => false, 'erro' => null, 'id' => null, 'repetido' => false];

        try {
            return Transacao::executar($this->conn, function () use ($pedidoId, $d, $usuarioId, $saida) {
                $stmt = $this->conn->prepare('SELECT * FROM pedidos_compra WHERE id_pedido = ? FOR UPDATE');
                $stmt->execute([$pedidoId]);
                $p = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$p) {
                    throw new DomainException('Pedido de compra não encontrado.');
                }

                if ($p['conta_pagar_id']) {
                    return array_merge($saida, ['ok' => true, 'id' => (int) $p['conta_pagar_id'], 'repetido' => true]);
                }

                if (in_array($p['status'], ['RASCUNHO', 'CANCELADO'], true)) {
                    throw new DomainException($p['status'] === 'RASCUNHO'
                        ? 'Realize o pedido antes de lançar a conta a pagar.'
                        : 'Pedido cancelado não gera conta a pagar.');
                }

                $total = $this->conn->prepare('SELECT COALESCE(SUM(quantidade * preco_unitario), 0) + ? FROM pedido_compra_itens WHERE pedido_id = ?');
                $total->execute([(float) $p['frete'], $pedidoId]);
                $totalPedido = round((float) $total->fetchColumn(), 2);

                $r = $this->criar([
                    'descricao'           => 'Pedido de compra ' . $p['numero'],
                    'categoria'           => 'ESTOQUE',
                    'valor_total'         => ($d['valor_total'] ?? '') !== '' ? $d['valor_total'] : number_format($totalPedido, 2, ',', ''),
                    'data_compra'         => $d['data_compra'] ?? date('Y-m-d'),
                    'meio_pagamento'      => $d['meio_pagamento'] ?? ($p['forma_pagamento'] && isset(self::MEIOS[$p['forma_pagamento']]) ? $p['forma_pagamento'] : ''),
                    'cartao_id'           => $d['cartao_id'] ?? null,
                    'qtd_parcelas'        => $d['qtd_parcelas'] ?? 1,
                    'primeiro_vencimento' => $d['primeiro_vencimento'] ?? ($p['vencimento_previsto'] ?? ''),
                    'fornecedor_id'       => $p['fornecedor_id'],
                    'observacoes'         => $d['observacoes'] ?? null,
                    'origem'              => 'PEDIDO_COMPRA',
                    'origem_id'           => $pedidoId,
                    'chave'               => $d['chave'] ?? null,
                ], $usuarioId);

                if (!$r['ok']) {
                    throw new DomainException($r['erro']);
                }

                $this->conn->prepare('UPDATE pedidos_compra SET conta_pagar_id = ? WHERE id_pedido = ?')->execute([$r['id'], $pedidoId]);
                Historico::registrar($this->conn, 'PEDIDO_COMPRA', $pedidoId, $p['status'], $p['status'], $usuarioId, 'Conta a pagar lançada: DP-' . str_pad((string) $r['id'], 4, '0', STR_PAD_LEFT));

                return $r;
            });
        } catch (DomainException $e) {
            return array_merge($saida, ['erro' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[Despesa criarDoPedido] ' . $e->getMessage());
            return array_merge($saida, ['erro' => 'Não foi possível lançar a conta a pagar do pedido.']);
        }
    }
}
