<?php

require_once __DIR__ . '/../core/Transacao.php';
require_once __DIR__ . '/Comunicacao.php';
require_once __DIR__ . '/../core/Historico.php';
require_once __DIR__ . '/OcorrenciaFluxo.php';

/**
 * Avaliação técnica de uma ocorrência: o que o técnico constatou e recomenda.
 *
 * É a BASE do orçamento — o atendente não reconstrói à mão o que o técnico já
 * registrou: gerarOrcamento() copia os itens recomendados. Cada reavaliação cria
 * uma nova VERSÃO (a anterior fica como estava), e a ocorrência acompanha:
 *
 *   iniciar()  → ocorrência EM_AVALIACAO, abre a versão N+1 (parte dos itens da anterior)
 *   salvar()   → grava defeitos, observações e itens (só enquanto EM_ANDAMENTO)
 *   concluir() → com itens: ocorrência AGUARDANDO_ORCAMENTO; sem itens exige "sem reparo"
 *                e a ocorrência é ENCERRADA (nada a orçar)
 */
class Avaliacao
{
    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    /* ── Consulta ────────────────────────────────────────────────────────── */

    /** Versão mais recente da avaliação da ocorrência, ou null. */
    public function ultima(int $ocorrenciaId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT a.*, u.nome AS tecnico_nome
            FROM avaliacoes_tecnicas a
            LEFT JOIN usuarios u ON u.id_usuario = a.tecnico_id
            WHERE a.ocorrencia_id = ? ORDER BY a.versao DESC LIMIT 1
        ");
        $stmt->execute([$ocorrenciaId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function porId(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM avaliacoes_tecnicas WHERE id_avaliacao = ?');
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function versoes(int $ocorrenciaId): array
    {
        $stmt = $this->conn->prepare('SELECT id_avaliacao, versao, status, iniciada_em, concluida_em FROM avaliacoes_tecnicas WHERE ocorrencia_id = ? ORDER BY versao');
        $stmt->execute([$ocorrenciaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function itens(int $avaliacaoId): array
    {
        $stmt = $this->conn->prepare("
            SELECT i.*, p.estoque_atual
            FROM avaliacao_itens i
            LEFT JOIN produtos p ON p.id_produto = i.produto_id
            WHERE i.avaliacao_id = ? ORDER BY i.id_avaliacao_item
        ");
        $stmt->execute([$avaliacaoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Soma dos itens (quantidade × preço). */
    public function total(int $avaliacaoId): float
    {
        $stmt = $this->conn->prepare('SELECT COALESCE(SUM(quantidade * preco), 0) FROM avaliacao_itens WHERE avaliacao_id = ?');
        $stmt->execute([$avaliacaoId]);

        return round((float) $stmt->fetchColumn(), 2);
    }

    /** Todas as peças da avaliação têm saldo em estoque? (insumo da fila: peça faltando = não dá para executar já) */
    public function pecasDisponiveis(int $avaliacaoId): bool
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM avaliacao_itens i
            JOIN produtos p ON p.id_produto = i.produto_id
            WHERE i.avaliacao_id = ? AND i.tipo = 'produto' AND p.estoque_atual < i.quantidade
        ");
        $stmt->execute([$avaliacaoId]);

        return (int) $stmt->fetchColumn() === 0;
    }

    /* ── Fluxo ───────────────────────────────────────────────────────────── */

    /**
     * Abre uma avaliação (nova versão) e põe a ocorrência EM_AVALIACAO.
     * Idempotente: se já há uma versão em andamento, devolve essa.
     *
     * @return array{ok:bool, erro:?string, id:?int, repetido:bool}
     */
    public function iniciar(int $ocorrenciaId, int $tecnicoId): array
    {
        try {
            return Transacao::executar($this->conn, function () use ($ocorrenciaId, $tecnicoId) {
                $stmt = $this->conn->prepare('SELECT status, tecnico_id FROM ocorrencias WHERE id_ocorrencia = ? FOR UPDATE');
                $stmt->execute([$ocorrenciaId]);
                $oc = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$oc) {
                    throw new DomainException('Ocorrência não encontrada.');
                }

                $anterior = $this->ultima($ocorrenciaId);

                if ($anterior && $anterior['status'] === 'EM_ANDAMENTO') {
                    return ['ok' => true, 'erro' => null, 'id' => (int) $anterior['id_avaliacao'], 'repetido' => true];
                }

                $r = OcorrenciaFluxo::transicionar($this->conn, $ocorrenciaId, 'EM_AVALIACAO', $tecnicoId, $anterior ? 'Reavaliação' : 'Avaliação iniciada');

                if (!$r['ok']) {
                    throw new DomainException($r['erro']);
                }

                $versao = $anterior ? (int) $anterior['versao'] + 1 : 1;

                $this->conn->prepare('INSERT INTO avaliacoes_tecnicas (ocorrencia_id, tecnico_id, versao) VALUES (?, ?, ?)')
                    ->execute([$ocorrenciaId, $tecnicoId, $versao]);

                $id = (int) $this->conn->lastInsertId();

                // Quem avalia passa a ser o responsável, se ainda não havia um.
                if (empty($oc['tecnico_id'])) {
                    $this->conn->prepare('UPDATE ocorrencias SET tecnico_id = ? WHERE id_ocorrencia = ?')->execute([$tecnicoId, $ocorrenciaId]);
                }

                // Reavaliar parte do que já havia sido recomendado.
                if ($anterior) {
                    $this->conn->prepare("
                        INSERT INTO avaliacao_itens (avaliacao_id, tipo, produto_id, nome, categoria, quantidade, preco, observacao)
                        SELECT ?, tipo, produto_id, nome, categoria, quantidade, preco, observacao
                        FROM avaliacao_itens WHERE avaliacao_id = ?
                    ")->execute([$id, $anterior['id_avaliacao']]);
                }

                // Só a PRIMEIRA avaliação avisa o cliente (reavaliar é um ajuste interno).
                if (!$anterior) {
                    $cli = $this->conn->prepare('SELECT cliente_id, veiculo_id FROM ocorrencias WHERE id_ocorrencia = ?');
                    $cli->execute([$ocorrenciaId]);

                    if ($o = $cli->fetch(PDO::FETCH_ASSOC)) {
                        Comunicacao::evento(
                            $this->conn, 'AVALIACAO_INICIADA', (int) $o['cliente_id'], Comunicacao::dadosDoVeiculo($this->conn, (int) $o['veiculo_id']),
                            'AVALIACAO:' . $id . ':INICIADA', 'OCORRENCIA', $ocorrenciaId, ['escopo' => 'OCORRENCIA', 'entidade' => $ocorrenciaId]
                        );
                    }
                }

                return ['ok' => true, 'erro' => null, 'id' => $id, 'repetido' => false];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'id' => null, 'repetido' => false];
        } catch (\Throwable $e) {
            error_log('[Avaliacao iniciar] ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível iniciar a avaliação.', 'id' => null, 'repetido' => false];
        }
    }

    /**
     * Grava defeitos, observações e itens. $dados['itens'] é a lista COMPLETA desejada
     * (tipo, produto_id|nome, categoria, quantidade, preco, observacao): substitui a
     * anterior — a avaliação ainda é rascunho.
     *
     * @return array{ok:bool, erro:?string}
     */
    public function salvar(int $avaliacaoId, array $dados): array
    {
        try {
            Transacao::executar($this->conn, function () use ($avaliacaoId, $dados) {
                $stmt = $this->conn->prepare('SELECT status FROM avaliacoes_tecnicas WHERE id_avaliacao = ? FOR UPDATE');
                $stmt->execute([$avaliacaoId]);
                $status = $stmt->fetchColumn();

                if ($status === false) {
                    throw new DomainException('Avaliação não encontrada.');
                }

                if ($status !== 'EM_ANDAMENTO') {
                    throw new DomainException('Esta avaliação já foi concluída. Inicie uma reavaliação para alterá-la.');
                }

                $itens = $this->normalizarItens((array) ($dados['itens'] ?? []));

                $this->conn->prepare("
                    UPDATE avaliacoes_tecnicas
                    SET defeitos = ?, observacoes = ?, duracao_estimada_min = ?
                    WHERE id_avaliacao = ?
                ")->execute([
                    trim((string) ($dados['defeitos'] ?? '')) ?: null,
                    trim((string) ($dados['observacoes'] ?? '')) ?: null,
                    !empty($dados['duracao_estimada_min']) ? max(1, (int) $dados['duracao_estimada_min']) : null,
                    $avaliacaoId,
                ]);

                $this->conn->prepare('DELETE FROM avaliacao_itens WHERE avaliacao_id = ?')->execute([$avaliacaoId]);

                $ins = $this->conn->prepare("
                    INSERT INTO avaliacao_itens (avaliacao_id, tipo, produto_id, nome, categoria, quantidade, preco, observacao)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($itens as $i) {
                    $ins->execute([$avaliacaoId, $i['tipo'], $i['produto_id'], $i['nome'], $i['categoria'], $i['quantidade'], $i['preco'], $i['observacao']]);
                }
            });

            return ['ok' => true, 'erro' => null];
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        } catch (\Throwable $e) {
            error_log('[Avaliacao salvar] ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível salvar a avaliação.'];
        }
    }

    /**
     * Conclui a avaliação.
     *  - exige o que foi constatado (defeitos) — sem isso o orçamento não tem base;
     *  - com itens: ocorrência → AGUARDANDO_ORCAMENTO;
     *  - sem itens: só com $semReparo (o veículo foi avaliado e não precisa de nada) e
     *    a ocorrência é ENCERRADA.
     *
     * @return array{ok:bool, erro:?string, repetido:bool, proximo:?string}
     */
    public function concluir(int $avaliacaoId, ?int $usuarioId, bool $semReparo = false): array
    {
        $saida = ['ok' => false, 'erro' => null, 'repetido' => false, 'proximo' => null];

        try {
            return Transacao::executar($this->conn, function () use ($avaliacaoId, $usuarioId, $semReparo, $saida) {
                $stmt = $this->conn->prepare('SELECT * FROM avaliacoes_tecnicas WHERE id_avaliacao = ? FOR UPDATE');
                $stmt->execute([$avaliacaoId]);
                $av = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$av) {
                    throw new DomainException('Avaliação não encontrada.');
                }

                if ($av['status'] === 'CONCLUIDA') {
                    return array_merge($saida, ['ok' => true, 'repetido' => true]);
                }

                if (mb_strlen(trim((string) $av['defeitos'])) < 5) {
                    throw new DomainException('Registre os defeitos constatados antes de concluir a avaliação.');
                }

                $qtdItens = $this->conn->prepare('SELECT COUNT(*) FROM avaliacao_itens WHERE avaliacao_id = ?');
                $qtdItens->execute([$avaliacaoId]);
                $temItens = (int) $qtdItens->fetchColumn() > 0;

                if (!$temItens && !$semReparo) {
                    throw new DomainException(
                        'A avaliação não tem nenhum serviço ou peça recomendado. Se o veículo não precisa de reparo, marque "Sem reparo necessário".'
                    );
                }

                $this->conn->prepare("UPDATE avaliacoes_tecnicas SET status = 'CONCLUIDA', concluida_em = NOW() WHERE id_avaliacao = ?")
                    ->execute([$avaliacaoId]);

                // A duração estimada do atendimento acompanha a avaliação (alimenta a fila).
                $this->conn->prepare('UPDATE ocorrencias SET duracao_estimada_min = COALESCE(?, duracao_estimada_min) WHERE id_ocorrencia = ?')
                    ->execute([$av['duracao_estimada_min'], $av['ocorrencia_id']]);

                $destino = $temItens ? 'AGUARDANDO_ORCAMENTO' : 'ENCERRADA';
                $r = OcorrenciaFluxo::transicionar(
                    $this->conn,
                    (int) $av['ocorrencia_id'],
                    $destino,
                    $usuarioId,
                    $temItens ? 'Avaliação concluída (v' . $av['versao'] . ')' : 'Avaliação sem necessidade de reparo'
                );

                if (!$r['ok']) {
                    throw new DomainException($r['erro']);
                }

                return array_merge($saida, ['ok' => true, 'proximo' => $destino]);
            });
        } catch (DomainException $e) {
            return array_merge($saida, ['erro' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[Avaliacao concluir] ' . $e->getMessage());
            return array_merge($saida, ['erro' => 'Não foi possível concluir a avaliação.']);
        }
    }

    /**
     * Gera o orçamento a partir da avaliação concluída: serviços e peças recomendados pelo
     * técnico viram os itens, o diagnóstico vem junto — o atendente só confere os preços.
     * Idempotente: se já existe orçamento (pendente ou aprovado) desta avaliação, devolve o
     * mesmo em vez de criar outro.
     *
     * @return array{ok:bool, erro:?string, id:?int, repetido:bool}
     */
    public function gerarOrcamento(int $avaliacaoId, ?int $usuarioId): array
    {
        try {
            return Transacao::executar($this->conn, function () use ($avaliacaoId, $usuarioId) {
                $stmt = $this->conn->prepare('SELECT * FROM avaliacoes_tecnicas WHERE id_avaliacao = ? FOR UPDATE');
                $stmt->execute([$avaliacaoId]);
                $av = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$av || $av['status'] !== 'CONCLUIDA') {
                    throw new DomainException('Conclua a avaliação técnica antes de gerar o orçamento.');
                }

                $oc = $this->conn->prepare('SELECT * FROM ocorrencias WHERE id_ocorrencia = ? FOR UPDATE');
                $oc->execute([$av['ocorrencia_id']]);
                $oc = $oc->fetch(PDO::FETCH_ASSOC);

                $existente = $this->conn->prepare("SELECT id_orcamento FROM orcamentos WHERE avaliacao_id = ? AND status IN ('pendente','aprovado') ORDER BY id_orcamento DESC LIMIT 1");
                $existente->execute([$avaliacaoId]);

                if ($id = $existente->fetchColumn()) {
                    return ['ok' => true, 'erro' => null, 'id' => (int) $id, 'repetido' => true];
                }

                if ($oc['status'] !== 'AGUARDANDO_ORCAMENTO') {
                    throw new DomainException('A ocorrência não está aguardando orçamento (' . mb_strtolower(OcorrenciaFluxo::rotulo($oc['status'])) . ').');
                }

                $itens = $this->itensParaDocumento($avaliacaoId);

                if (empty($itens)) {
                    throw new DomainException('A avaliação não tem itens para orçar.');
                }

                require_once __DIR__ . '/Orcamento.php';
                $orcamento = new Orcamento($this->conn);

                $id = $orcamento->salvar([
                    'cliente_id'       => $oc['cliente_id'],
                    'veiculo_id'       => $oc['veiculo_id'],
                    'mecanico_id'      => $av['tecnico_id'],
                    'ocorrencia_id'    => $oc['id_ocorrencia'],
                    'avaliacao_id'     => $avaliacaoId,
                    'defeito_relatado' => $oc['relato'],
                    'diagnostico'      => $av['defeitos'],
                    'observacoes'      => $av['observacoes'],
                    'data_validade'    => date('Y-m-d', strtotime('+15 days')),
                    'itens'            => $itens,
                ], ['usuario_id' => $usuarioId]);

                if (!$id) {
                    throw new DomainException('Não foi possível gerar o orçamento: ' . $orcamento->getLastError());
                }

                return ['ok' => true, 'erro' => null, 'id' => (int) $id, 'repetido' => false];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'id' => null, 'repetido' => false];
        } catch (\Throwable $e) {
            error_log('[Avaliacao gerarOrcamento] ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível gerar o orçamento.', 'id' => null, 'repetido' => false];
        }
    }

    /**
     * Itens da avaliação no formato que Orcamento::salvar() e OrdemServico::create() usam.
     * Peça entra pelo preço de venda do cadastro quando a avaliação não informou um.
     */
    public function itensParaDocumento(int $avaliacaoId): array
    {
        $saida = [];

        foreach ($this->itens($avaliacaoId) as $i) {
            $preco = (float) $i['preco'];

            if ($preco <= 0 && $i['tipo'] === 'produto' && $i['produto_id']) {
                $p = $this->conn->prepare('SELECT preco_venda FROM produtos WHERE id_produto = ?');
                $p->execute([$i['produto_id']]);
                $preco = (float) $p->fetchColumn();
            }

            $saida[] = [
                'tipo'      => $i['tipo'],
                'id'        => $i['tipo'] === 'produto' && $i['produto_id'] ? (int) $i['produto_id'] : 'custom_' . $i['id_avaliacao_item'],
                'nome'      => $i['nome'],
                'categoria' => $i['categoria'] ?: ($i['tipo'] === 'servico' ? 'Serviço' : 'Peça'),
                'qty'       => (float) $i['quantidade'],
                'preco'     => $preco,
                'custom'    => $i['tipo'] === 'servico' || !$i['produto_id'] ? 1 : 0,
            ];
        }

        return $saida;
    }

    /* ── Interno ─────────────────────────────────────────────────────────── */

    /** @return array<int,array<string,mixed>> */
    private function normalizarItens(array $itens): array
    {
        $saida = [];

        foreach ($itens as $i) {
            $tipo = ($i['tipo'] ?? '') === 'produto' ? 'produto' : 'servico';
            $nome = trim((string) ($i['nome'] ?? ''));
            $produtoId = null;

            if ($tipo === 'produto' && !empty($i['produto_id'])) {
                $p = $this->conn->prepare('SELECT nome FROM produtos WHERE id_produto = ? AND ativo = 1');
                $p->execute([(int) $i['produto_id']]);
                $nomeProduto = $p->fetchColumn();

                if ($nomeProduto === false) {
                    throw new DomainException('Uma das peças escolhidas não existe mais no cadastro.');
                }

                $produtoId = (int) $i['produto_id'];
                $nome = $nome !== '' ? $nome : (string) $nomeProduto;
            }

            if ($nome === '') {
                continue; // linha em branco do formulário
            }

            $quantidade = round((float) str_replace(',', '.', (string) ($i['quantidade'] ?? 1)), 3);
            $preco      = round((float) str_replace(',', '.', (string) ($i['preco'] ?? 0)), 2);

            if ($quantidade <= 0) {
                throw new DomainException('Quantidade inválida em "' . $nome . '".');
            }

            if ($preco < 0) {
                throw new DomainException('Valor inválido em "' . $nome . '".');
            }

            $saida[] = [
                'tipo'       => $tipo,
                'produto_id' => $produtoId,
                'nome'       => mb_substr($nome, 0, 255),
                'categoria'  => trim((string) ($i['categoria'] ?? '')) ?: null,
                'quantidade' => $quantidade,
                'preco'      => $preco,
                'observacao' => trim((string) ($i['observacao'] ?? '')) ?: null,
            ];
        }

        return $saida;
    }
}
