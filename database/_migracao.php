<?php

/**
 * Apoio comum às migrações idempotentes (database/etapa*.php).
 *
 * Cada migração do projeto precisa fazer as mesmas perguntas ("essa coluna já
 * existe?", "essa FK já existe?") antes de alterar o banco, para poder rodar
 * quantas vezes for preciso. Em vez de repetir isso em cada script, fica aqui.
 *
 * Como apontar para outro banco (ex.: o de teste), sem editar o .env — uma
 * variável de ambiente de verdade vence o arquivo (ver Env::carregar):
 *
 *   DB_NAME=mecanize_db_test /c/xampp/php/php.exe database/etapa1_estabilizacao.php
 *
 * Por segurança, só roda contra mecanize_db e mecanize_db_test.
 */

require_once __DIR__ . '/../app/core/Database.php';

class Migracao
{
    public PDO $pdo;
    public string $banco;

    /** Quantas alterações esta execução fez de fato. */
    public int $feitas = 0;

    public function __construct()
    {
        $this->pdo = new Database();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->banco = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();

        if (!in_array($this->banco, ['mecanize_db', 'mecanize_db_test'], true)) {
            fwrite(STDERR, "ABORTADO: banco '{$this->banco}' não é o do Mecanize.\n");
            exit(1);
        }

        echo "Banco: {$this->banco}\n";
    }

    public function titulo(string $texto): void
    {
        echo "\n{$texto}\n";
    }

    public function tabelaExiste(string $tabela): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$tabela]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function colunaExiste(string $tabela, string $coluna): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$tabela, $coluna]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** Tipo completo da coluna (ex.: "enum('A','B')"), ou null se não existir. */
    public function tipoDaColuna(string $tabela, string $coluna): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$tabela, $coluna]);
        $tipo = $stmt->fetchColumn();

        return $tipo === false ? null : (string) $tipo;
    }

    public function indiceExiste(string $tabela, string $indice): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
        );
        $stmt->execute([$tabela, $indice]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function fkExiste(string $tabela, string $nome): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ?
               AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = ?"
        );
        $stmt->execute([$tabela, $nome]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** Executa o DDL/DML e registra no relatório. */
    public function executar(string $descricao, string $sql): void
    {
        $this->pdo->exec($sql);
        $this->feitas++;
        echo "  + {$descricao}\n";
    }

    public function jaTinha(string $descricao): void
    {
        echo "  = {$descricao} (já existe)\n";
    }

    public function criarTabela(string $tabela, string $ddl): void
    {
        if ($this->tabelaExiste($tabela)) {
            $this->jaTinha("tabela {$tabela}");
            return;
        }

        $this->executar("tabela {$tabela}", $ddl);
    }

    public function adicionarColuna(string $tabela, string $coluna, string $definicao): void
    {
        if ($this->colunaExiste($tabela, $coluna)) {
            $this->jaTinha("{$tabela}.{$coluna}");
            return;
        }

        $this->executar("{$tabela}.{$coluna}", "ALTER TABLE `{$tabela}` ADD COLUMN `{$coluna}` {$definicao}");
    }

    public function adicionarIndice(string $tabela, string $nome, string $colunas, bool $unico = false): void
    {
        if ($this->indiceExiste($tabela, $nome)) {
            $this->jaTinha("índice {$tabela}.{$nome}");
            return;
        }

        $tipo = $unico ? 'UNIQUE KEY' : 'KEY';
        $this->executar("índice {$tabela}.{$nome}", "ALTER TABLE `{$tabela}` ADD {$tipo} `{$nome}` ({$colunas})");
    }

    /**
     * Cria a FK se faltar. Antes confere órfãos: criar a FK com linha órfã
     * falha com errno 150 (sem dizer qual linha). Órfão é problema de dado,
     * não de migração — a execução PARA e lista, sem apagar nada sozinha.
     */
    public function adicionarFk(
        string $tabela,
        string $nome,
        string $coluna,
        string $tabelaRef,
        string $colunaRef,
        string $aoExcluir = 'RESTRICT'
    ): void {
        if ($this->fkExiste($tabela, $nome)) {
            $this->jaTinha("FK {$nome}");
            return;
        }

        $orfas = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM `{$tabela}` f
             LEFT JOIN `{$tabelaRef}` r ON r.`{$colunaRef}` = f.`{$coluna}`
             WHERE f.`{$coluna}` IS NOT NULL AND r.`{$colunaRef}` IS NULL"
        )->fetchColumn();

        if ($orfas > 0) {
            fwrite(STDERR, "ABORTADO: {$tabela}.{$coluna} tem {$orfas} linha(s) apontando para {$tabelaRef} inexistente. "
                . "Corrija os dados e rode de novo.\n");
            exit(1);
        }

        $this->executar(
            "FK {$nome} ({$tabela}.{$coluna} -> {$tabelaRef}.{$colunaRef}, ON DELETE {$aoExcluir})",
            "ALTER TABLE `{$tabela}` ADD CONSTRAINT `{$nome}` FOREIGN KEY (`{$coluna}`)
             REFERENCES `{$tabelaRef}` (`{$colunaRef}`) ON DELETE {$aoExcluir} ON UPDATE CASCADE"
        );
    }

    public function fim(): void
    {
        echo "\nMigração concluída — {$this->feitas} alteração(ões) nesta execução.\n";
    }
}
