<?php

/**
 * Cotações de compra: o que cada fornecedor pediu por uma peça (preço, frete, prazo,
 * condição de pagamento), registrado à mão a partir do que a oficina consultou.
 *
 * Não há consulta automática a fornecedores: onde eles não oferecem API de compra (ver
 * MELHORIAS_2026-10-08.md, "Dependências externas"), a equipe pesquisa no site/telefone e
 * registra aqui, e o sistema só ajuda a COMPARAR e a transformar a melhor em pedido.
 */
class CotacaoCompra
{
    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    /**
     * @param array{solicitacao_id?:mixed, produto_id?:mixed, descricao?:string, fornecedor_id:mixed, quantidade:mixed,
     *              preco_unitario:mixed, frete?:mixed, prazo_dias?:mixed, condicao_pagamento?:string, validade_ate?:string, observacao?:string} $d
     * @return array{ok:bool, erro:?string, id:?int}
     */
    public function registrar(array $d, ?int $usuarioId): array
    {
        try {
            $solicitacaoId = !empty($d['solicitacao_id']) ? (int) $d['solicitacao_id'] : null;
            $produtoId     = !empty($d['produto_id']) ? (int) $d['produto_id'] : null;
            $descricao     = trim((string) ($d['descricao'] ?? ''));

            if ($solicitacaoId !== null) {
                $s = $this->conn->prepare('SELECT produto_id, descricao, quantidade FROM solicitacoes_peca WHERE id_solicitacao = ?');
                $s->execute([$solicitacaoId]);
                $solic = $s->fetch(PDO::FETCH_ASSOC);

                if (!$solic) {
                    throw new DomainException('Solicitação não encontrada.');
                }

                $produtoId = $solic['produto_id'] ? (int) $solic['produto_id'] : $produtoId;
                $descricao = $solic['descricao'];
                $d['quantidade'] = ($d['quantidade'] ?? '') !== '' ? $d['quantidade'] : $solic['quantidade'];
            } elseif ($produtoId !== null) {
                $p = $this->conn->prepare('SELECT nome FROM produtos WHERE id_produto = ?');
                $p->execute([$produtoId]);
                $descricao = (string) $p->fetchColumn();
            }

            if ($descricao === '') {
                throw new DomainException('Informe a peça cotada.');
            }

            $fornecedorId = (int) ($d['fornecedor_id'] ?? 0);
            $f = $this->conn->prepare('SELECT ativo FROM fornecedores WHERE id_fornecedor = ?');
            $f->execute([$fornecedorId]);

            if (!(int) $f->fetchColumn()) {
                throw new DomainException('Escolha um fornecedor ativo.');
            }

            $numero = fn($v) => round((float) str_replace(',', '.', (string) $v), 2);
            $quantidade = round((float) str_replace(',', '.', (string) ($d['quantidade'] ?? 0)), 3);
            $preco = $numero($d['preco_unitario'] ?? 0);
            $frete = $numero($d['frete'] ?? 0);

            if ($quantidade <= 0 || $preco <= 0) {
                throw new DomainException('Informe quantidade e preço unitário maiores que zero.');
            }

            if ($frete < 0) {
                throw new DomainException('O frete não pode ser negativo.');
            }

            $prazo = trim((string) ($d['prazo_dias'] ?? ''));
            $validade = trim((string) ($d['validade_ate'] ?? ''));

            if ($validade !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validade)) {
                throw new DomainException('Data de validade inválida.');
            }

            $this->conn->prepare("
                INSERT INTO cotacoes_compra
                    (solicitacao_id, produto_id, descricao, fornecedor_id, quantidade, preco_unitario, frete, prazo_dias, condicao_pagamento, validade_ate, observacao, registrada_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $solicitacaoId, $produtoId, mb_substr($descricao, 0, 200), $fornecedorId, $quantidade, $preco, $frete,
                $prazo !== '' && ctype_digit($prazo) ? (int) $prazo : null,
                trim((string) ($d['condicao_pagamento'] ?? '')) ?: null,
                $validade !== '' ? $validade : null,
                mb_substr(trim((string) ($d['observacao'] ?? '')), 0, 255) ?: null,
                $usuarioId,
            ]);

            return ['ok' => true, 'erro' => null, 'id' => (int) $this->conn->lastInsertId()];
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'id' => null];
        } catch (\Throwable $e) {
            error_log('[CotacaoCompra registrar] ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível registrar a cotação.', 'id' => null];
        }
    }

    /**
     * Cotações da solicitação já comparadas: `total` (itens + frete) e as marcas
     * `melhor_preco` (menor total) e `mais_rapida` (menor prazo informado). Cotação vencida
     * continua listada, mas sinalizada.
     */
    public function daSolicitacao(int $solicitacaoId): array
    {
        $stmt = $this->conn->prepare("
            SELECT c.*, f.nome AS fornecedor_nome, f.site_url, u.nome AS registrada_por_nome,
                   ROUND(c.quantidade * c.preco_unitario + c.frete, 2) AS total,
                   (c.validade_ate IS NOT NULL AND c.validade_ate < CURDATE()) AS vencida
            FROM cotacoes_compra c
            JOIN fornecedores f ON f.id_fornecedor = c.fornecedor_id
            LEFT JOIN usuarios u ON u.id_usuario = c.registrada_por
            WHERE c.solicitacao_id = ?
            ORDER BY total ASC, c.id_cotacao
        ");
        $stmt->execute([$solicitacaoId]);

        return self::marcar($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @param array<int,array<string,mixed>> $linhas */
    public static function marcar(array $linhas): array
    {
        $validas = array_filter($linhas, fn($c) => !(int) $c['vencida']);

        $menorTotal = $validas ? min(array_column($validas, 'total')) : null;
        $prazos = array_filter(array_column($validas, 'prazo_dias'), fn($p) => $p !== null);
        $menorPrazo = $prazos ? min($prazos) : null;

        foreach ($linhas as &$c) {
            $c['melhor_preco'] = !(int) $c['vencida'] && $menorTotal !== null && (float) $c['total'] === (float) $menorTotal;
            $c['mais_rapida']  = !(int) $c['vencida'] && $menorPrazo !== null && $c['prazo_dias'] !== null && (int) $c['prazo_dias'] === (int) $menorPrazo;
        }

        return $linhas;
    }

    public function excluir(int $cotacaoId): bool
    {
        return $this->conn->prepare('DELETE FROM cotacoes_compra WHERE id_cotacao = ?')->execute([$cotacaoId]);
    }
}
