<?php

require_once __DIR__ . '/../core/Transacao.php';
require_once __DIR__ . '/../core/Historico.php';
require_once __DIR__ . '/MovimentacaoEstoque.php';

/**
 * Ciclo de vida do estoque de uma peça lançada numa OS.
 *
 *   PLANEJADO ⇄ RESERVADO ──consumir──▶ CONSUMIDO ──devolver──▶ DEVOLVIDO
 *       │            │
 *       └────────────┴──cancelar──▶ CANCELADO
 *
 * RESERVADO é um compromisso, não uma baixa: o saldo físico (`estoque_atual`) não muda, mas a
 * quantidade reservada deixa de estar DISPONÍVEL para as outras OS (disponível = estoque −
 * reservado). Assim duas OS não prometem a mesma última peça. Consumir uma peça RESERVADA usa a
 * própria reserva; consumir uma só PLANEJADA respeita as reservas das demais.
 *
 * Abrir ou editar uma OS NÃO mexe no estoque: o item nasce PLANEJADO (é só uma
 * previsão de uso e de cobrança). O saldo só diminui quando o consumo é
 * CONFIRMADO — pelo técnico ao usar a peça, ou pelo administrador — e só aumenta
 * quando a peça é devolvida ou a OS é cancelada.
 *
 * Garantias:
 *  - Idempotência: consumir/devolver o mesmo item de novo não baixa/estorna duas
 *    vezes. A segunda chamada devolve ok com `repetido`; e mesmo que a checagem
 *    de status falhasse, a chave única (os_produto_id, evento) em
 *    movimentacoes_estoque recusaria a segunda movimentação.
 *  - Saldo: consumir mais do que há em estoque é recusado, com a quantidade
 *    disponível na mensagem — nunca deixa o produto negativo.
 *  - Atomicidade: item, saldo e movimentação mudam numa transação só.
 *  - Ordem das travas: OS → item → produto, em todos os caminhos. Dois fluxos
 *    que travassem em ordens diferentes poderiam se travar mutuamente.
 *
 * Todos os métodos recebem o PDO: trabalham na conexão de quem chamou, inclusive
 * dentro da transação dele (ver Transacao).
 */
final class ConsumoEstoque
{
    /** Situações da OS em que dá para confirmar o consumo de uma peça. */
    public const OS_PERMITE_CONSUMO = ['EM_ANDAMENTO', 'AGUARDANDO_PECA', 'AGUARDANDO_CONFERENCIA'];

    /** Situações da OS em que dá para reservar (ou liberar) uma peça. */
    public const OS_PERMITE_RESERVA = ['ABERTA', 'EM_ANDAMENTO', 'AGUARDANDO_PECA'];

    /** Situações do item que ainda contam na cobrança da OS. */
    public const STATUS_COBRADOS = ['PLANEJADO', 'RESERVADO', 'CONSUMIDO'];

    /**
     * Confirma o consumo da peça: baixa o estoque e registra a saída.
     *
     * @return array{ok:bool, erro:?string, repetido:bool}
     */
    public static function consumir(PDO $pdo, int $itemId, ?int $usuarioId): array
    {
        return Transacao::executar($pdo, function () use ($pdo, $itemId, $usuarioId) {
            $osId = self::osDoItem($pdo, $itemId);

            if ($osId === null) {
                return self::falha('Peça não encontrada na ordem de serviço.');
            }

            $os = self::travarOs($pdo, $osId);
            $item = self::travarItem($pdo, $itemId);

            if ($item['status'] === 'CONSUMIDO') {
                return ['ok' => true, 'erro' => null, 'repetido' => true];
            }

            if (!in_array($item['status'], ['PLANEJADO', 'RESERVADO'], true)) {
                return self::falha('Esta peça foi ' . self::rotuloStatus($item['status']) . ' e não pode mais ser consumida.');
            }

            if (!in_array($os['status'], self::OS_PERMITE_CONSUMO, true)) {
                return self::falha(
                    'O consumo só pode ser confirmado com a OS em execução. Inicie a execução da OS antes.'
                );
            }

            $quantidade = (float) $item['quantidade'];

            $produto = $pdo->prepare('SELECT nome, estoque_atual, preco_custo FROM produtos WHERE id_produto = ? FOR UPDATE');
            $produto->execute([(int) $item['produto_id']]);
            $produto = $produto->fetch(PDO::FETCH_ASSOC);

            if (!$produto) {
                return self::falha('Produto não encontrado.');
            }

            // Peça RESERVADA usa a própria reserva; uma só PLANEJADA respeita as das outras OS.
            $reservadoOutros = self::reservadoPorOutros($pdo, (int) $item['produto_id'], $itemId);

            if ($quantidade > (float) $produto['estoque_atual'] - $reservadoOutros + 0.0005) {
                if ($reservadoOutros > 0.0005 && $quantidade <= (float) $produto['estoque_atual']) {
                    return self::falha(sprintf(
                        'Saldo disponível insuficiente de "%s": há %s em estoque, %s reservado(s) para outras OS e esta usa %s.',
                        $produto['nome'],
                        self::fmt((float) $produto['estoque_atual']),
                        self::fmt($reservadoOutros),
                        self::fmt($quantidade)
                    ));
                }

                return self::falha(sprintf(
                    'Saldo insuficiente de "%s": há %s em estoque e a OS usa %s.',
                    $produto['nome'],
                    self::fmt((float) $produto['estoque_atual']),
                    self::fmt($quantidade)
                ));
            }

            $pdo->prepare('UPDATE produtos SET estoque_atual = estoque_atual - ?, atualizado_em = NOW() WHERE id_produto = ?')
                ->execute([$quantidade, (int) $item['produto_id']]);

            MovimentacaoEstoque::registrarSemAjustarEstoque($pdo, [
                'produto_id'       => (int) $item['produto_id'],
                'tipo'             => 'SAIDA',
                'origem'           => 'OS',
                'ordem_servico_id' => $osId,
                'os_produto_id'    => $itemId,
                'evento'           => 'CONSUMO',
                'quantidade'       => $quantidade,
                'custo_unitario'   => (float) $produto['preco_custo'],
                'usuario_id'       => $usuarioId,
                'observacao'       => 'Consumo confirmado na ordem de serviço',
            ]);

            $pdo->prepare(
                "UPDATE ordem_servico_produtos
                 SET status = 'CONSUMIDO', consumido_em = NOW(), consumido_por = ?
                 WHERE id_OS_produto = ?"
            )->execute([$usuarioId && self::usuarioExiste($pdo, $usuarioId) ? $usuarioId : null, $itemId]);

            return ['ok' => true, 'erro' => null, 'repetido' => false];
        });
    }

    /**
     * Devolve ao estoque uma peça já consumida (peça que não foi usada, erro de
     * lançamento, cancelamento). A justificativa fica registrada na movimentação.
     *
     * @return array{ok:bool, erro:?string, repetido:bool}
     */
    public static function devolver(PDO $pdo, int $itemId, ?int $usuarioId, string $justificativa): array
    {
        $justificativa = trim($justificativa);

        if ($justificativa === '') {
            return self::falha('Informe o motivo da devolução ao estoque.');
        }

        return Transacao::executar($pdo, function () use ($pdo, $itemId, $usuarioId, $justificativa) {
            $osId = self::osDoItem($pdo, $itemId);

            if ($osId === null) {
                return self::falha('Peça não encontrada na ordem de serviço.');
            }

            self::travarOs($pdo, $osId);
            $item = self::travarItem($pdo, $itemId);

            if ($item['status'] === 'DEVOLVIDO') {
                return ['ok' => true, 'erro' => null, 'repetido' => true];
            }

            if ($item['status'] !== 'CONSUMIDO') {
                return self::falha('Só uma peça já consumida pode ser devolvida ao estoque.');
            }

            self::estornarConsumo($pdo, $item, $osId, $usuarioId, $justificativa);

            return ['ok' => true, 'erro' => null, 'repetido' => false];
        });
    }

    /**
     * Cancelamento da OS: devolve tudo o que foi consumido e cancela o que
     * estava só previsto. Chamado de dentro da transação do cancelamento (a OS
     * já está travada por quem chamou).
     *
     * @return int quantas peças voltaram ao estoque
     */
    public static function desfazerTudoDaOs(PDO $pdo, int $osId, ?int $usuarioId, string $motivo): int
    {
        $itens = $pdo->prepare('SELECT id_OS_produto FROM ordem_servico_produtos WHERE ordem_servico_id = ? ORDER BY id_OS_produto FOR UPDATE');
        $itens->execute([$osId]);

        $devolvidas = 0;

        foreach ($itens->fetchAll(PDO::FETCH_COLUMN) as $itemId) {
            $item = self::travarItem($pdo, (int) $itemId);

            if ($item['status'] === 'CONSUMIDO') {
                self::estornarConsumo($pdo, $item, $osId, $usuarioId, $motivo);
                $devolvidas++;
            } elseif (in_array($item['status'], ['PLANEJADO', 'RESERVADO'], true)) {
                $pdo->prepare("UPDATE ordem_servico_produtos SET status = 'CANCELADO' WHERE id_OS_produto = ?")
                    ->execute([(int) $itemId]);
            }
        }

        return $devolvidas;
    }

    /**
     * Reserva a peça para esta OS: o saldo físico não muda, mas a quantidade sai do DISPONÍVEL
     * das outras OS. Recusa se o disponível (estoque − reservas das outras OS) não cobre.
     *
     * @return array{ok:bool, erro:?string, repetido:bool}
     */
    public static function reservar(PDO $pdo, int $itemId, ?int $usuarioId): array
    {
        return Transacao::executar($pdo, function () use ($pdo, $itemId, $usuarioId) {
            $osId = self::osDoItem($pdo, $itemId);

            if ($osId === null) {
                return self::falha('Peça não encontrada na ordem de serviço.');
            }

            $os = self::travarOs($pdo, $osId);
            $item = self::travarItem($pdo, $itemId);

            if ($item['status'] === 'RESERVADO') {
                return ['ok' => true, 'erro' => null, 'repetido' => true];
            }

            if ($item['status'] !== 'PLANEJADO') {
                return self::falha('Só uma peça prevista pode ser reservada (esta foi ' . self::rotuloStatus($item['status']) . ').');
            }

            if (!in_array($os['status'], self::OS_PERMITE_RESERVA, true)) {
                return self::falha('Esta OS não aceita mais reservas de peça.');
            }

            // Trava o produto ANTES de somar as reservas: duas reservas simultâneas da última
            // peça se enfileiram aqui, e a segunda enxerga a primeira.
            $produto = $pdo->prepare('SELECT nome, estoque_atual FROM produtos WHERE id_produto = ? FOR UPDATE');
            $produto->execute([(int) $item['produto_id']]);
            $produto = $produto->fetch(PDO::FETCH_ASSOC);

            if (!$produto) {
                return self::falha('Produto não encontrado.');
            }

            $quantidade = (float) $item['quantidade'];
            $outros = self::reservadoPorOutros($pdo, (int) $item['produto_id'], $itemId);

            if ($quantidade > (float) $produto['estoque_atual'] - $outros + 0.0005) {
                return self::falha(sprintf(
                    'Não há saldo disponível de "%s" para reservar: há %s em estoque, %s já reservado(s) para outras OS e esta usa %s.',
                    $produto['nome'],
                    self::fmt((float) $produto['estoque_atual']),
                    self::fmt($outros),
                    self::fmt($quantidade)
                ));
            }

            $pdo->prepare("UPDATE ordem_servico_produtos SET status = 'RESERVADO', reservado_em = NOW(), reservado_por = ? WHERE id_OS_produto = ?")
                ->execute([$usuarioId && self::usuarioExiste($pdo, $usuarioId) ? $usuarioId : null, $itemId]);

            Historico::registrar($pdo, 'OS', $osId, $os['status'], $os['status'], $usuarioId, 'Peça reservada: ' . $produto['nome'] . ' (' . self::fmt($quantidade) . ')');

            return ['ok' => true, 'erro' => null, 'repetido' => false];
        });
    }

    /**
     * Desfaz a reserva (a peça volta a PLANEJADO e o saldo reservado fica disponível para
     * outras OS). Não toca no estoque físico.
     *
     * @return array{ok:bool, erro:?string, repetido:bool}
     */
    public static function liberarReserva(PDO $pdo, int $itemId, ?int $usuarioId): array
    {
        return Transacao::executar($pdo, function () use ($pdo, $itemId, $usuarioId) {
            $osId = self::osDoItem($pdo, $itemId);

            if ($osId === null) {
                return self::falha('Peça não encontrada na ordem de serviço.');
            }

            $os = self::travarOs($pdo, $osId);
            $item = self::travarItem($pdo, $itemId);

            if ($item['status'] === 'PLANEJADO') {
                return ['ok' => true, 'erro' => null, 'repetido' => true];
            }

            if ($item['status'] !== 'RESERVADO') {
                return self::falha('Só uma peça reservada pode ter a reserva liberada.');
            }

            $pdo->prepare("UPDATE ordem_servico_produtos SET status = 'PLANEJADO', reservado_em = NULL, reservado_por = NULL WHERE id_OS_produto = ?")
                ->execute([$itemId]);

            Historico::registrar($pdo, 'OS', $osId, $os['status'], $os['status'], $usuarioId, 'Reserva de peça liberada (item #' . $itemId . ')');

            return ['ok' => true, 'erro' => null, 'repetido' => false];
        });
    }

    /** Quanto do produto está reservado para outros itens (todos os RESERVADO menos o próprio). */
    public static function reservadoPorOutros(PDO $pdo, int $produtoId, int $exceptoItemId = 0): float
    {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantidade), 0) FROM ordem_servico_produtos WHERE produto_id = ? AND status = 'RESERVADO' AND id_OS_produto <> ?");
        $stmt->execute([$produtoId, $exceptoItemId]);

        return (float) $stmt->fetchColumn();
    }

    /** Peças da OS que ainda não foram nem consumidas nem descartadas. */
    public static function pendentesDaOs(PDO $pdo, int $osId): array
    {
        $stmt = $pdo->prepare(
            "SELECT p.id_OS_produto, p.produto_id, p.quantidade, pr.nome
             FROM ordem_servico_produtos p
             JOIN produtos pr ON pr.id_produto = p.produto_id
             WHERE p.ordem_servico_id = ? AND p.status IN ('PLANEJADO','RESERVADO')
             ORDER BY p.id_OS_produto"
        );
        $stmt->execute([$osId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ── internos ──────────────────────────────────────────────────────── */

    private static function estornarConsumo(PDO $pdo, array $item, int $osId, ?int $usuarioId, string $justificativa): void
    {
        $quantidade = (float) $item['quantidade'];

        $custo = $pdo->prepare('SELECT preco_custo FROM produtos WHERE id_produto = ? FOR UPDATE');
        $custo->execute([(int) $item['produto_id']]);

        $pdo->prepare('UPDATE produtos SET estoque_atual = estoque_atual + ?, atualizado_em = NOW() WHERE id_produto = ?')
            ->execute([$quantidade, (int) $item['produto_id']]);

        MovimentacaoEstoque::registrarSemAjustarEstoque($pdo, [
            'produto_id'       => (int) $item['produto_id'],
            'tipo'             => 'ENTRADA',
            'origem'           => 'OS',
            'ordem_servico_id' => $osId,
            'os_produto_id'    => (int) $item['id_OS_produto'],
            'evento'           => 'DEVOLUCAO',
            'quantidade'       => $quantidade,
            'custo_unitario'   => (float) ($custo->fetchColumn() ?: 0),
            'usuario_id'       => $usuarioId,
            'observacao'       => 'Devolução ao estoque',
            'justificativa'    => $justificativa,
        ]);

        $pdo->prepare("UPDATE ordem_servico_produtos SET status = 'DEVOLVIDO', devolvido_em = NOW() WHERE id_OS_produto = ?")
            ->execute([(int) $item['id_OS_produto']]);
    }

    private static function osDoItem(PDO $pdo, int $itemId): ?int
    {
        $stmt = $pdo->prepare('SELECT ordem_servico_id FROM ordem_servico_produtos WHERE id_OS_produto = ?');
        $stmt->execute([$itemId]);
        $osId = $stmt->fetchColumn();

        return $osId === false ? null : (int) $osId;
    }

    private static function travarOs(PDO $pdo, int $osId): array
    {
        $stmt = $pdo->prepare('SELECT id_ordem_servico, status FROM ordens_servico WHERE id_ordem_servico = ? FOR UPDATE');
        $stmt->execute([$osId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['id_ordem_servico' => $osId, 'status' => null];
    }

    private static function travarItem(PDO $pdo, int $itemId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM ordem_servico_produtos WHERE id_OS_produto = ? FOR UPDATE');
        $stmt->execute([$itemId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['status' => null, 'id_OS_produto' => $itemId];
    }

    private static function usuarioExiste(PDO $pdo, int $usuarioId): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $stmt->execute([$usuarioId]);

        return (bool) $stmt->fetchColumn();
    }

    /** @return array{ok:bool, erro:string, repetido:bool} */
    private static function falha(string $erro): array
    {
        return ['ok' => false, 'erro' => $erro, 'repetido' => false];
    }

    private static function rotuloStatus(?string $status): string
    {
        return [
            'DEVOLVIDO' => 'devolvida',
            'CANCELADO' => 'cancelada',
            'CONSUMIDO' => 'consumida',
        ][$status] ?? 'encerrada';
    }

    private static function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, ',', '.'), '0'), ',');
    }
}
