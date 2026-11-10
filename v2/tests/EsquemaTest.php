<?php

use PHPUnit\Framework\TestCase;

/** Confere, no banco de verdade, as regras de estrutura que garantem o isolamento. */
final class EsquemaTest extends TestCase
{
    private function db(): PDO
    {
        return Banco::conexao();
    }

    private function tabelasDeOficina(): array
    {
        return $this->db()->query("SELECT table_name FROM information_schema.columns
            WHERE table_schema = DATABASE() AND column_name = 'oficina_id' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function testSaoDezesseteTabelas(): void
    {
        $this->assertSame(17, (int) $this->db()->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn());
    }

    public function testTodaTabelaDeOficinaTemChaveUnicaComposta(): void
    {
        foreach ($this->tabelasDeOficina() as $tabela) {
            if ($tabela === 'historico' || $tabela === 'notificacoes') {
                continue; // ninguém aponta para elas
            }
            $tem = $this->db()->query("SELECT COUNT(*) FROM information_schema.statistics s1
                JOIN information_schema.statistics s2 ON s2.table_schema = s1.table_schema AND s2.table_name = s1.table_name AND s2.index_name = s1.index_name
                WHERE s1.table_schema = DATABASE() AND s1.table_name = '{$tabela}' AND s1.non_unique = 0
                  AND s1.column_name = 'oficina_id' AND s1.seq_in_index = 1 AND s2.column_name = 'id' AND s2.seq_in_index = 2")->fetchColumn();
            $this->assertGreaterThan(0, (int) $tem, "{$tabela} precisa de UNIQUE (oficina_id, id)");
        }
    }

    public function testTodaLigacaoEntreTabelasDeOficinaIncluiAOficina(): void
    {
        $deOficina = $this->tabelasDeOficina();
        $fks = $this->db()->query("SELECT k.table_name, k.constraint_name, k.referenced_table_name,
                                          GROUP_CONCAT(k.column_name ORDER BY k.ordinal_position) AS colunas
                                   FROM information_schema.key_column_usage k
                                   WHERE k.table_schema = DATABASE() AND k.referenced_table_name IS NOT NULL
                                   GROUP BY k.table_name, k.constraint_name, k.referenced_table_name")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($fks as $fk) {
            $ref = $fk['referenced_table_name'] ?? $fk['REFERENCED_TABLE_NAME'];
            $tab = $fk['table_name'] ?? $fk['TABLE_NAME'];
            if (!in_array($ref, $deOficina, true) || !in_array($tab, $deOficina, true)) {
                continue;
            }
            $this->assertStringStartsWith('oficina_id,', $fk['colunas'] ?? $fk['COLUNAS'], "{$tab} → {$ref} precisa ser (oficina_id, ...)");
        }
    }
}
