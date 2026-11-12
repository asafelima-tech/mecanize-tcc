<?php

require_once __DIR__ . '/../core/Transacao.php';
require_once __DIR__ . '/../core/Historico.php';
require_once __DIR__ . '/../core/Dinheiro.php';
require_once __DIR__ . '/MovimentacaoCaixa.php';
require_once __DIR__ . '/Caixa.php';

/**
 * Contas a pagar: as obrigações pendentes e o dinheiro que sai para quitá-las.
 *
 *   PARCELA (fora do cartão)  →  pagamento tipo PARCELA: quita aquela parcela.
 *   FATURA  (cartão)          →  pagamento tipo FATURA: quita, de uma vez, TODAS as parcelas
 *                                pendentes da fatura. As parcelas de cartão não se pagam uma a uma.
 *
 * Garantias:
 *  - o mesmo alvo (parcela ou fatura) não tem dois pagamentos ativos — índice único no banco —, e
 *    cada pagamento leva uma chave de idempotência (clique repetido devolve o que já existe);
 *  - pagar e lançar a saída no caixa (opcional) acontecem na mesma transação;
 *  - um pagamento errado se desfaz por ESTORNO (justificado): as parcelas voltam a pendentes, a
 *    fatura volta a em aberto e, se havia saída no caixa, entra um lançamento de devolução no
 *    caixa aberto — o lançamento original nunca é apagado;
 *  - a fatura só é paga depois de fechada (comprar de novo nela depois do pagamento é impossível).
 */
class ContaPagar
{
    public const FORMAS = ['DINHEIRO' => 'Dinheiro', 'PIX' => 'PIX', 'TRANSFERENCIA' => 'Transferência', 'BOLETO' => 'Boleto', 'CARTAO_DEBITO' => 'Cartão de débito', 'OUTRO' => 'Outro'];

    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    public function conexao(): PDO
    {
        return $this->conn;
    }

    /* ── Consulta ────────────────────────────────────────────────────────── */

    /**
     * Parcelas a pagar com a situação calculada: PENDENTE, VENCIDA (pendente com vencimento
     * passado), PAGA ou CANCELADA. `via_fatura` marca as de cartão (quitadas pela fatura).
     *
     * @param array{situacao?:string, de?:string, ate?:string, fornecedor_id?:int, cartao_id?:int, busca?:string} $f
     */
    public function listarParcelas(array $f = [], int $limite = 300): array
    {
        $where = ["d.status = 'ATIVA'"];
        $params = [];

        switch ($f['situacao'] ?? 'abertas') {
            case 'abertas':
                $where[] = "p.status = 'PENDENTE'";
                break;
            case 'vencidas':
                $where[] = "p.status = 'PENDENTE' AND p.vencimento < CURDATE()";
                break;
            case 'pagas':
                $where[] = "p.status = 'PAGA'";
                break;
            case 'todas':
                $where[] = "p.status <> 'CANCELADA'";
                break;
        }

        if (!empty($f['de'])) {
            $where[] = 'p.vencimento >= ?';
            $params[] = $f['de'];
        }

        if (!empty($f['ate'])) {
            $where[] = 'p.vencimento <= ?';
            $params[] = $f['ate'];
        }

        foreach (['fornecedor_id' => 'd.fornecedor_id', 'cartao_id' => 'd.cartao_id'] as $chave => $coluna) {
            if (!empty($f[$chave])) {
                $where[] = "{$coluna} = ?";
                $params[] = (int) $f[$chave];
            }
        }

        if (!empty($f['busca'])) {
            $where[] = '(d.descricao LIKE ? OR d.numero LIKE ? OR fo.nome LIKE ?)';
            $t = '%' . trim($f['busca']) . '%';
            array_push($params, $t, $t, $t);
        }

        $stmt = $this->conn->prepare("
            SELECT p.*, d.id_despesa, d.numero AS despesa_numero, d.descricao, d.categoria, d.meio_pagamento, d.qtd_parcelas,
                   fo.nome AS fornecedor_nome, ca.apelido AS cartao_apelido,
                   (p.fatura_id IS NOT NULL) AS via_fatura,
                   CASE WHEN p.status = 'PENDENTE' AND p.vencimento < CURDATE() THEN 'VENCIDA' ELSE p.status END AS situacao
            FROM contas_pagar_parcelas p
            JOIN despesas d ON d.id_despesa = p.despesa_id
            LEFT JOIN fornecedores fo ON fo.id_fornecedor = d.fornecedor_id
            LEFT JOIN cartoes_corporativos ca ON ca.id_cartao = d.cartao_id
            WHERE " . implode(' AND ', $where) . '
            ORDER BY p.vencimento, p.id_conta_parcela LIMIT ' . (int) $limite
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Totais de contas a pagar. Cada parcela é contada UMA vez: a fatura do cartão é só a
     * agregação das parcelas dela, então não entra como outra obrigação.
     *
     * @return array{pendente:float, vencido:float, a_vencer:float, proximos_7_dias:float, parcelas_pendentes:int, parcelas_vencidas:int}
     */
    public function totais(): array
    {
        $r = $this->conn->query("
            SELECT COALESCE(SUM(p.valor), 0) AS pendente,
                   COALESCE(SUM(CASE WHEN p.vencimento < CURDATE() THEN p.valor END), 0) AS vencido,
                   COALESCE(SUM(CASE WHEN p.vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN p.valor END), 0) AS proximos,
                   COUNT(*) AS qtd, COALESCE(SUM(p.vencimento < CURDATE()), 0) AS qtd_vencidas
            FROM contas_pagar_parcelas p JOIN despesas d ON d.id_despesa = p.despesa_id
            WHERE p.status = 'PENDENTE' AND d.status = 'ATIVA'
        ")->fetch(PDO::FETCH_ASSOC);

        return [
            'pendente'           => round((float) $r['pendente'], 2),
            'vencido'            => round((float) $r['vencido'], 2),
            'a_vencer'           => round((float) $r['pendente'] - (float) $r['vencido'], 2),
            'proximos_7_dias'    => round((float) $r['proximos'], 2),
            'parcelas_pendentes' => (int) $r['qtd'],
            'parcelas_vencidas'  => (int) $r['qtd_vencidas'],
        ];
    }

    /** Próximos vencimentos (parcelas diretas e faturas), do mais próximo ao mais distante. */
    public function proximosVencimentos(int $limite = 8): array
    {
        $stmt = $this->conn->query("
            SELECT 'PARCELA' AS tipo, p.id_conta_parcela AS id, d.id_despesa, d.descricao AS titulo, p.vencimento, p.valor
            FROM contas_pagar_parcelas p JOIN despesas d ON d.id_despesa = p.despesa_id
            WHERE p.status = 'PENDENTE' AND d.status = 'ATIVA' AND p.fatura_id IS NULL
            UNION ALL
            SELECT 'FATURA', f.id_fatura, NULL, CONCAT('Fatura ', c.apelido, ' · ', DATE_FORMAT(f.vence_em, '%m/%Y')), f.vence_em,
                   (SELECT COALESCE(SUM(p.valor), 0) FROM contas_pagar_parcelas p JOIN despesas d ON d.id_despesa = p.despesa_id
                     WHERE p.fatura_id = f.id_fatura AND p.status = 'PENDENTE' AND d.status = 'ATIVA')
            FROM faturas_cartao f JOIN cartoes_corporativos c ON c.id_cartao = f.cartao_id
            WHERE f.paga_em IS NULL
              AND EXISTS (SELECT 1 FROM contas_pagar_parcelas p JOIN despesas d ON d.id_despesa = p.despesa_id
                           WHERE p.fatura_id = f.id_fatura AND p.status = 'PENDENTE' AND d.status = 'ATIVA')
            ORDER BY vencimento ASC LIMIT " . (int) $limite
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ── Pagamento ───────────────────────────────────────────────────────── */

    /**
     * Paga uma parcela que NÃO é de cartão.
     *
     * @param array{forma:string, chave:string, data?:string, lancar_no_caixa?:bool, observacao?:string} $d
     * @return array{ok:bool, erro:?string, repetido:bool, pagamento_id:?int}
     */
    public function pagarParcela(int $parcelaId, array $d, ?int $usuarioId): array
    {
        $saida = ['ok' => false, 'erro' => null, 'repetido' => false, 'pagamento_id' => null];

        try {
            return Transacao::executar($this->conn, function () use ($parcelaId, $d, $usuarioId, $saida) {
                $chave = $this->chaveValida($d['chave'] ?? '');

                if ($ja = $this->pagamentoPorChave($chave)) {
                    return array_merge($saida, ['ok' => true, 'repetido' => true, 'pagamento_id' => $ja]);
                }

                $stmt = $this->conn->prepare("
                    SELECT p.*, d.numero AS despesa_numero, d.descricao, d.categoria, d.status AS despesa_status, d.id_despesa
                    FROM contas_pagar_parcelas p JOIN despesas d ON d.id_despesa = p.despesa_id
                    WHERE p.id_conta_parcela = ? FOR UPDATE
                ");
                $stmt->execute([$parcelaId]);
                $p = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$p) {
                    throw new DomainException('Parcela não encontrada.');
                }

                if ($p['fatura_id'] !== null) {
                    throw new DomainException('Esta parcela é de cartão: ela é quitada quando a FATURA é paga.');
                }

                if ($p['despesa_status'] !== 'ATIVA' || $p['status'] === 'CANCELADA') {
                    throw new DomainException('Esta parcela foi cancelada.');
                }

                if ($p['status'] === 'PAGA') {
                    throw new DomainException('Esta parcela já foi paga.');
                }

                $forma = $this->formaValida($d['forma'] ?? '');
                $pagoEm = $this->dataPagamento($d['data'] ?? null);
                $valor = (float) $p['valor'];

                $pagamentoId = $this->inserirPagamento('PARCELA', $parcelaId, null, $valor, $forma, $pagoEm, $usuarioId, $chave, $d['observacao'] ?? null);

                $this->conn->prepare("UPDATE contas_pagar_parcelas SET status = 'PAGA', pago_em = ?, pagamento_id = ? WHERE id_conta_parcela = ?")
                    ->execute([$pagoEm, $pagamentoId, $parcelaId]);

                if (!empty($d['lancar_no_caixa'])) {
                    $this->lancarSaidaNoCaixa($pagamentoId, $valor, $forma, $p['categoria'], 'Pagamento ' . $p['despesa_numero'] . ' — parcela ' . $p['numero'] . ' · ' . $p['descricao'], $usuarioId, $chave);
                }

                Historico::registrar($this->conn, 'DESPESA', (int) $p['id_despesa'], null, 'PARCELA_PAGA', $usuarioId, 'Parcela ' . $p['numero'] . ' paga (' . self::FORMAS[$forma] . ')', Historico::ORIGEM_USUARIO, [
                    'pagamento_id' => $pagamentoId, 'valor' => $valor,
                ]);

                return array_merge($saida, ['ok' => true, 'pagamento_id' => $pagamentoId]);
            });
        } catch (DomainException $e) {
            return array_merge($saida, ['erro' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[ContaPagar pagarParcela] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            return array_merge($saida, ['erro' => 'Não foi possível registrar o pagamento. Nada foi gravado.']);
        }
    }

    /**
     * Paga a fatura do cartão: UM pagamento que quita todas as parcelas pendentes dela. As
     * parcelas não geram, cada uma, outra despesa nem outra saída: a compra, as parcelas e o
     * pagamento da fatura são etapas de uma despesa só.
     *
     * @param array{forma:string, chave:string, data?:string, lancar_no_caixa?:bool, observacao?:string} $d
     * @return array{ok:bool, erro:?string, repetido:bool, pagamento_id:?int}
     */
    public function pagarFatura(int $faturaId, array $d, ?int $usuarioId): array
    {
        $saida = ['ok' => false, 'erro' => null, 'repetido' => false, 'pagamento_id' => null];

        try {
            return Transacao::executar($this->conn, function () use ($faturaId, $d, $usuarioId, $saida) {
                $chave = $this->chaveValida($d['chave'] ?? '');

                if ($ja = $this->pagamentoPorChave($chave)) {
                    return array_merge($saida, ['ok' => true, 'repetido' => true, 'pagamento_id' => $ja]);
                }

                $stmt = $this->conn->prepare("
                    SELECT f.*, c.apelido FROM faturas_cartao f JOIN cartoes_corporativos c ON c.id_cartao = f.cartao_id
                    WHERE f.id_fatura = ? FOR UPDATE
                ");
                $stmt->execute([$faturaId]);
                $f = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$f) {
                    throw new DomainException('Fatura não encontrada.');
                }

                if ($f['paga_em'] !== null) {
                    throw new DomainException('Esta fatura já foi paga.');
                }

                if ($f['fecha_em'] > date('Y-m-d')) {
                    throw new DomainException('A fatura ainda está aberta (fecha em ' . date('d/m/Y', strtotime($f['fecha_em'])) . '): novas compras ainda podem entrar nela.');
                }

                $parcelas = $this->conn->prepare("
                    SELECT p.id_conta_parcela, p.valor, d.categoria FROM contas_pagar_parcelas p
                    JOIN despesas d ON d.id_despesa = p.despesa_id
                    WHERE p.fatura_id = ? AND p.status = 'PENDENTE' AND d.status = 'ATIVA'
                    ORDER BY p.id_conta_parcela FOR UPDATE
                ");
                $parcelas->execute([$faturaId]);
                $parcelas = $parcelas->fetchAll(PDO::FETCH_ASSOC);

                if (!$parcelas) {
                    throw new DomainException('Esta fatura não tem nada pendente.');
                }

                $forma = $this->formaValida($d['forma'] ?? '');
                $pagoEm = $this->dataPagamento($d['data'] ?? null);
                $valor = round(array_sum(array_map(fn($p) => (float) $p['valor'], $parcelas)), 2);

                $pagamentoId = $this->inserirPagamento('FATURA', null, $faturaId, $valor, $forma, $pagoEm, $usuarioId, $chave, $d['observacao'] ?? null);

                $this->conn->prepare("UPDATE contas_pagar_parcelas SET status = 'PAGA', pago_em = ?, pagamento_id = ? WHERE fatura_id = ? AND status = 'PENDENTE'")
                    ->execute([$pagoEm, $pagamentoId, $faturaId]);
                $this->conn->prepare('UPDATE faturas_cartao SET paga_em = ? WHERE id_fatura = ?')->execute([$pagoEm, $faturaId]);

                if (!empty($d['lancar_no_caixa'])) {
                    $this->lancarSaidaNoCaixa($pagamentoId, $valor, $forma, 'OUTRO', 'Pagamento da fatura ' . $f['apelido'] . ' · ' . date('m/Y', strtotime($f['vence_em'])), $usuarioId, $chave);
                }

                return array_merge($saida, ['ok' => true, 'pagamento_id' => $pagamentoId]);
            });
        } catch (DomainException $e) {
            return array_merge($saida, ['erro' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[ContaPagar pagarFatura] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            return array_merge($saida, ['erro' => 'Não foi possível pagar a fatura. Nada foi gravado.']);
        }
    }

    /**
     * Desfaz um pagamento lançado errado. Parcelas voltam a pendentes, a fatura volta a em
     * aberto, e a saída do caixa (se houve) é devolvida com um lançamento novo no caixa ABERTO.
     *
     * @return array{ok:bool, erro:?string, repetido:bool}
     */
    public function estornarPagamento(int $pagamentoId, string $justificativa, ?int $usuarioId): array
    {
        $justificativa = trim($justificativa);
        $saida = ['ok' => false, 'erro' => null, 'repetido' => false];

        try {
            return Transacao::executar($this->conn, function () use ($pagamentoId, $justificativa, $usuarioId, $saida) {
                $stmt = $this->conn->prepare('SELECT * FROM pagamentos_pagar WHERE id_pagamento = ? FOR UPDATE');
                $stmt->execute([$pagamentoId]);
                $pg = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$pg) {
                    throw new DomainException('Pagamento não encontrado.');
                }

                if ($pg['estornado_em'] !== null) {
                    return array_merge($saida, ['ok' => true, 'repetido' => true]);
                }

                if (mb_strlen($justificativa) < 5) {
                    throw new DomainException('Informe o motivo do estorno do pagamento.');
                }

                $estornoCaixaId = null;

                if ($pg['movimentacao_caixa_id']) {
                    $caixa = (new Caixa($this->conn))->getCaixaAberto();

                    if (!$caixa) {
                        throw new DomainException('Abra o caixa para registrar a devolução do valor ao caixa.');
                    }

                    $estornoCaixaId = (new MovimentacaoCaixa($this->conn))->registrar(
                        $caixa['id_caixa'], 'ENTRADA', (float) $pg['valor'], $pg['forma_pagamento'] === 'OUTRO' ? null : $pg['forma_pagamento'], 'OUTRO',
                        'Estorno do pagamento #' . $pagamentoId . ': ' . mb_substr($justificativa, 0, 120), $usuarioId, 'MANUAL', null, 'ESTORNO_PAGAR:' . $pagamentoId
                    );

                    if (!$estornoCaixaId) {
                        throw new DomainException('Não foi possível lançar o estorno no caixa.');
                    }
                }

                $this->conn->prepare("UPDATE contas_pagar_parcelas SET status = 'PENDENTE', pago_em = NULL, pagamento_id = NULL WHERE pagamento_id = ?")->execute([$pagamentoId]);

                if ($pg['fatura_id']) {
                    $this->conn->prepare('UPDATE faturas_cartao SET paga_em = NULL WHERE id_fatura = ?')->execute([$pg['fatura_id']]);
                }

                $this->conn->prepare('UPDATE pagamentos_pagar SET estornado_em = NOW(), estornado_por = ?, estorno_justificativa = ?, estorno_caixa_id = ? WHERE id_pagamento = ?')
                    ->execute([$usuarioId, mb_substr($justificativa, 0, 255), $estornoCaixaId, $pagamentoId]);

                Historico::registrar($this->conn, 'PAGAMENTO_PAGAR', $pagamentoId, 'PAGO', 'ESTORNADO', $usuarioId, $justificativa);

                return array_merge($saida, ['ok' => true]);
            });
        } catch (DomainException $e) {
            return array_merge($saida, ['erro' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[ContaPagar estornarPagamento] ' . $e->getMessage());
            return array_merge($saida, ['erro' => 'Não foi possível estornar o pagamento.']);
        }
    }

    /* ── Internos ────────────────────────────────────────────────────────── */

    private function chaveValida($chave): string
    {
        $chave = trim((string) $chave);

        if (mb_strlen($chave) < 8) {
            throw new DomainException('Envio inválido. Recarregue a página e confirme de novo.');
        }

        return mb_substr($chave, 0, 80);
    }

    private function formaValida($forma): string
    {
        if (!isset(self::FORMAS[$forma])) {
            throw new DomainException('Escolha a forma de pagamento.');
        }

        return (string) $forma;
    }

    /** Data do pagamento: a informada (não futura) ou agora. */
    private function dataPagamento(?string $data): string
    {
        $data = trim((string) $data);

        if ($data === '') {
            return date('Y-m-d H:i:s');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || strtotime($data) === false) {
            throw new DomainException('Data do pagamento inválida.');
        }

        if ($data > date('Y-m-d')) {
            throw new DomainException('A data do pagamento não pode ser futura: registre quando o pagamento for feito.');
        }

        return $data === date('Y-m-d') ? date('Y-m-d H:i:s') : $data . ' 12:00:00';
    }

    private function pagamentoPorChave(string $chave): ?int
    {
        $stmt = $this->conn->prepare('SELECT id_pagamento FROM pagamentos_pagar WHERE chave_idempotencia = ?');
        $stmt->execute([$chave]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function inserirPagamento(string $tipo, ?int $parcelaId, ?int $faturaId, float $valor, string $forma, string $pagoEm, ?int $usuarioId, string $chave, ?string $observacao): int
    {
        $this->conn->prepare("
            INSERT INTO pagamentos_pagar (tipo, parcela_id, fatura_id, valor, forma_pagamento, pago_em, usuario_id, observacao, chave_idempotencia)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$tipo, $parcelaId, $faturaId, $valor, $forma, $pagoEm, $this->usuarioValido($usuarioId), mb_substr(trim((string) $observacao), 0, 255) ?: null, $chave]);

        return (int) $this->conn->lastInsertId();
    }

    private function lancarSaidaNoCaixa(int $pagamentoId, float $valor, string $forma, string $categoria, string $descricao, ?int $usuarioId, string $chave): void
    {
        if ($forma === 'OUTRO') {
            throw new DomainException('Para lançar no caixa, escolha dinheiro, PIX, transferência, boleto ou débito.');
        }

        $caixa = (new Caixa($this->conn))->getCaixaAberto();

        if (!$caixa) {
            throw new DomainException('Não há caixa aberto para lançar a saída. Abra o caixa ou desmarque "lançar no caixa".');
        }

        $categoriaCaixa = $categoria === 'ESTOQUE' ? 'PECAS' : 'OUTRO';

        $idCaixa = (new MovimentacaoCaixa($this->conn))->registrar(
            $caixa['id_caixa'], 'SAIDA', $valor, $forma, $categoriaCaixa, $descricao, $usuarioId, 'MANUAL', null, 'PAGAR:' . $chave
        );

        if (!$idCaixa) {
            throw new DomainException('Não foi possível lançar a saída no caixa.');
        }

        $this->conn->prepare('UPDATE pagamentos_pagar SET movimentacao_caixa_id = ? WHERE id_pagamento = ?')->execute([$idCaixa, $pagamentoId]);
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
}
