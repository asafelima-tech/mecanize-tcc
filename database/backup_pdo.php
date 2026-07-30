<?php

/**
 * Backup do banco em SQL, feito inteiramente via PDO.
 *
 * Existe porque o `database/backup.ps1` não roda nesta máquina: tanto o
 * mysql.exe quanto o mysqldump.exe do XAMPP falham com
 * `ERROR 1156: Plugin caching_sha2_password could not be loaded`. Enquanto o
 * cliente MySQL estiver quebrado, este é o caminho que funciona.
 *
 * Gera CREATE TABLE + INSERTs, com FOREIGN_KEY_CHECKS desligado no arquivo
 * (as tabelas são restauradas em ordem alfabética, não em ordem de
 * dependência) e DROP TABLE antes de cada CREATE, para o arquivo poder ser
 * reimportado por cima sem conflito.
 *
 * Uso:
 *   php database/backup_pdo.php                      → banco inteiro
 *   php database/backup_pdo.php caixa ordens_servico → só essas tabelas
 *
 * Salva em database/backups/ (pasta já ignorada pelo git).
 */

require_once __DIR__ . '/../app/core/Database.php';

$pdo = new Database();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$banco = $pdo->query('SELECT DATABASE()')->fetchColumn();

// Tabelas pedidas na linha de comando; sem argumento, o banco inteiro.
$pedidas = array_slice($argv, 1);

$todas = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")
    ->fetchAll(PDO::FETCH_COLUMN);

if ($pedidas) {
    $desconhecidas = array_diff($pedidas, $todas);

    if ($desconhecidas) {
        fwrite(STDERR, "Tabela(s) inexistente(s): " . implode(', ', $desconhecidas) . "\n");
        exit(1);
    }

    $tabelas = $pedidas;
} else {
    $tabelas = $todas;
}

$pasta = __DIR__ . '/backups';

if (!is_dir($pasta)) {
    mkdir($pasta, 0777, true);
}

$arquivo = $pasta . '/' . $banco . '_' . date('Y-m-d_His') . '.sql';
$saida   = fopen($arquivo, 'w');

fwrite($saida, "-- Backup de {$banco} — " . date('d/m/Y H:i:s') . "\n");
fwrite($saida, "-- Gerado por database/backup_pdo.php\n");
fwrite($saida, "-- Tabelas: " . implode(', ', $tabelas) . "\n\n");
fwrite($saida, "SET NAMES utf8mb4;\n");
fwrite($saida, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

$totalLinhas = 0;

/**
 * Colunas geradas (VIRTUAL/STORED) da tabela. O MySQL recusa INSERT que traga
 * valor pra elas ("The value specified for generated column ... is not
 * allowed"), então ficam de fora do dump — o banco recalcula ao restaurar.
 *
 * O filtro é por GENERATION_EXPRESSION, não por `EXTRA LIKE '%GENERATED%'`:
 * uma coluna comum com DEFAULT CURRENT_TIMESTAMP tem EXTRA
 * `DEFAULT_GENERATED` e casaria no LIKE. Pulá-la não daria erro nenhum —
 * a restauração simplesmente gravaria NOW() no lugar da data original,
 * perdendo o dado sem avisar.
 */
$colunasGeradas = function (string $tabela) use ($pdo): array {
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :tabela
          AND GENERATION_EXPRESSION <> ''
    ");
    $stmt->execute([':tabela' => $tabela]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
};

foreach ($tabelas as $tabela) {
    $ddl     = $pdo->query("SHOW CREATE TABLE `{$tabela}`")->fetch(PDO::FETCH_ASSOC)['Create Table'];
    $geradas = $colunasGeradas($tabela);

    fwrite($saida, "-- ----------------------------\n");
    fwrite($saida, "-- {$tabela}\n");
    fwrite($saida, "-- ----------------------------\n");
    fwrite($saida, "DROP TABLE IF EXISTS `{$tabela}`;\n");
    fwrite($saida, $ddl . ";\n\n");

    $stmt  = $pdo->query("SELECT * FROM `{$tabela}`");
    $linhas = 0;

    while ($linha = $stmt->fetch(PDO::FETCH_ASSOC)) {
        foreach ($geradas as $coluna) {
            unset($linha[$coluna]);
        }

        $colunas = '`' . implode('`, `', array_keys($linha)) . '`';

        $valores = array_map(function ($valor) use ($pdo) {
            return $valor === null ? 'NULL' : $pdo->quote((string) $valor);
        }, array_values($linha));

        fwrite($saida, "INSERT INTO `{$tabela}` ({$colunas}) VALUES (" . implode(', ', $valores) . ");\n");
        $linhas++;
    }

    if ($linhas) {
        fwrite($saida, "\n");
    }

    printf(
        "  %-28s %d linha(s)%s\n",
        $tabela,
        $linhas,
        $geradas ? '  (coluna gerada ignorada: ' . implode(', ', $geradas) . ')' : ''
    );
    $totalLinhas += $linhas;
}

fwrite($saida, "SET FOREIGN_KEY_CHECKS = 1;\n");
fclose($saida);

echo "\nBackup salvo em: " . str_replace('\\', '/', $arquivo) . "\n";
echo count($tabelas) . " tabela(s), {$totalLinhas} linha(s), " . number_format(filesize($arquivo)) . " bytes.\n";
