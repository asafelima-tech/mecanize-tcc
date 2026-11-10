<?php

/**
 * Base de todos os models — é aqui que o isolamento entre oficinas acontece no código.
 *
 * Regras:
 *  1. Toda consulta em tabela de oficina passa por consultar()/linha()/lista()/valor(), e o SQL
 *     PRECISA conter o marcador :oficina (ex.: "WHERE oficina_id = :oficina"). Sem ele a consulta
 *     nem é executada: lança LogicException. Esquecer o filtro vira erro na hora, não vazamento.
 *  2. O valor de :oficina é sempre o da sessão (Contexto::oficinaId()). Se alguém mandar 'oficina'
 *     nos parâmetros, é sobrescrito.
 *  3. inserir() e atualizar() preenchem/filtram oficina_id sozinhos e respeitam a licença
 *     (somente leitura quando vencida/suspensa).
 *  4. Tabelas sem oficina (catálogo de modelos de veículo, login por e-mail) usam os métodos
 *     *Global — o nome deixa explícito, na revisão, que ali não há filtro.
 *
 * O banco é a segunda barreira: as chaves estrangeiras compostas (oficina_id, id) recusam qualquer
 * ligação entre registros de oficinas diferentes, mesmo que o código erre.
 */
abstract class Modelo
{
    protected PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Banco::conexao();
    }

    protected function oficinaId(): int
    {
        return Contexto::oficinaId();
    }

    /* ── Leitura e escrita filtradas pela oficina ─────────────────────── */

    protected function consultar(string $sql, array $params = []): PDOStatement
    {
        if (!preg_match('/:oficina\b/', $sql)) {
            throw new LogicException('Consulta sem filtro de oficina (:oficina): ' . preg_replace('/\s+/', ' ', trim($sql)));
        }

        $params['oficina'] = $this->oficinaId();

        return $this->executar($sql, $params);
    }

    protected function linha(string $sql, array $params = []): ?array
    {
        $r = $this->consultar($sql, $params)->fetch();

        return $r === false ? null : $r;
    }

    protected function lista(string $sql, array $params = []): array
    {
        return $this->consultar($sql, $params)->fetchAll();
    }

    protected function valor(string $sql, array $params = [])
    {
        return $this->consultar($sql, $params)->fetchColumn();
    }

    /** INSERT com oficina_id da sessão. Devolve o id criado. */
    protected function inserir(string $tabela, array $dados): int
    {
        Contexto::exigirEscrita();
        $dados['oficina_id'] = $this->oficinaId();

        $colunas = array_keys($dados);
        $sql = 'INSERT INTO ' . self::identificador($tabela)
            . ' (' . implode(', ', array_map([self::class, 'identificador'], $colunas)) . ')'
            . ' VALUES (' . implode(', ', array_map(fn($c) => ':' . $c, $colunas)) . ')';

        $this->executar($sql, $dados);

        return (int) $this->db->lastInsertId();
    }

    /** UPDATE de um registro DA OFICINA. Devolve quantas linhas mudaram (0 = não existe nesta oficina ou nada mudou). */
    protected function atualizar(string $tabela, int $id, array $dados): int
    {
        Contexto::exigirEscrita();
        unset($dados['id'], $dados['oficina_id']);

        if ($dados === []) {
            return 0;
        }

        $sets = implode(', ', array_map(fn($c) => self::identificador($c) . ' = :' . $c, array_keys($dados)));
        $dados['id'] = $id;

        return $this->consultar('UPDATE ' . self::identificador($tabela) . " SET {$sets} WHERE id = :id AND oficina_id = :oficina", $dados)
            ->rowCount();
    }

    /** Executa uma gravação escrita à mão (UPDATE com cálculo, por exemplo), já com licença e filtro. */
    protected function gravar(string $sql, array $params = []): int
    {
        Contexto::exigirEscrita();

        return $this->consultar($sql, $params)->rowCount();
    }

    /* ── Sem filtro de oficina: só para tabelas globais e autenticação ─── */

    protected function linhaGlobal(string $sql, array $params = []): ?array
    {
        $r = $this->executar($sql, $params)->fetch();

        return $r === false ? null : $r;
    }

    protected function listaGlobal(string $sql, array $params = []): array
    {
        return $this->executar($sql, $params)->fetchAll();
    }

    protected function executarGlobal(string $sql, array $params = []): int
    {
        return $this->executar($sql, $params)->rowCount();
    }

    /* ── Apoio ─────────────────────────────────────────────────────────── */

    /** Exclui um registro DA OFICINA. Registro com vínculos (OS, histórico…) não sai: o banco recusa. */
    protected function apagar(string $tabela, int $id): bool
    {
        Contexto::exigirEscrita();

        return $this->consultar('DELETE FROM ' . self::identificador($tabela) . ' WHERE id = :id AND oficina_id = :oficina', ['id' => $id])
            ->rowCount() > 0;
    }

    /**
     * Mensagens para chaves únicas violadas, por nome da chave (ex.: 'uq_clientes_documento' => 'Já existe…').
     * Cada model preenche as suas.
     */
    protected array $mensagensUnicas = [];

    private function executar(string $sql, array $params): PDOStatement
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt;
        } catch (PDOException $e) {
            throw $this->traduzir($e);
        }
    }

    /** Erros de integridade viram mensagem para a tela (DomainException); o resto segue como falha interna. */
    private function traduzir(PDOException $e): Throwable
    {
        $codigo = (int) ($e->errorInfo[1] ?? 0);
        $msg    = (string) ($e->errorInfo[2] ?? $e->getMessage());

        if ($codigo === 1062) {
            foreach ($this->mensagensUnicas as $chave => $texto) {
                if (str_contains($msg, $chave)) {
                    return new DomainException($texto, 0, $e);
                }
            }
            return new DomainException('Já existe um cadastro com estes dados.', 0, $e);
        }
        if ($codigo === 1451) {
            return new DomainException('Este cadastro tem registros ligados a ele (ordens de serviço, movimentações…) e não pode ser excluído. Desative-o.', 0, $e);
        }
        if ($codigo === 1452) {
            return new DomainException('Um dos itens escolhidos não existe ou não pertence a esta oficina.', 0, $e);
        }
        if ($codigo === 3819) {
            return new DomainException('Os dados informados não são válidos para gravação.', 0, $e);
        }

        return $e;
    }

    private static function identificador(string $nome): string
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $nome)) {
            throw new InvalidArgumentException("Nome de tabela/coluna inválido: {$nome}");
        }

        return '`' . $nome . '`';
    }
}
