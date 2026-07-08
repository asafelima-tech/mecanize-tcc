<?php
/*
|--------------------------------------------------------------------------
| Migration + seed: combustíveis permitidos por modelo
|--------------------------------------------------------------------------
| - Cria a coluna `combustiveis` em `modelos_veiculos` (se não existir).
| - Popula cada modelo com os combustíveis plausíveis (regras heurísticas).
|
| O valor é uma lista separada por vírgula, usando EXATAMENTE os rótulos de
| app/views/Veiculos/_combustiveis.php (a filtragem na listagem usa o valor
| exato). Modelos criados "na hora" ficam com NULL → o front-end mostra a
| lista completa (sem restrição).
|
| Como rodar:  php database/seed_combustiveis_modelos.php
| É idempotente: pode rodar quantas vezes quiser.
*/

$pdo = new PDO("mysql:host=localhost;dbname=mecanize_db;charset=utf8mb4", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

/* 1) Garante a coluna */
$col = $pdo->query("SHOW COLUMNS FROM modelos_veiculos LIKE 'combustiveis'")->fetch();
if (!$col) {
    $pdo->exec("ALTER TABLE modelos_veiculos ADD COLUMN combustiveis VARCHAR(255) NULL AFTER nome");
    echo "Coluna `combustiveis` criada.\n";
} else {
    echo "Coluna `combustiveis` já existe.\n";
}

/* 2) Regras de combustível por (marca, modelo) */
function fuelsFor(string $marca, string $modelo): array
{
    $FLEX      = ['Flex (Álcool/Gasolina)', 'Gasolina', 'Etanol'];
    $FLEX_H    = ['Flex (Álcool/Gasolina)', 'Gasolina', 'Etanol', 'Híbrido'];
    $GAS       = ['Gasolina'];
    $GAS_H     = ['Gasolina', 'Híbrido'];
    $ELE       = ['Elétrico'];
    $DIES_FLEX = ['Diesel', 'Flex (Álcool/Gasolina)', 'Gasolina'];
    $DIES_GAS  = ['Diesel', 'Gasolina'];
    $DIES      = ['Diesel'];

    // 100% elétricos / híbridos plug-in
    if ($marca === 'Tesla') return $ELE;
    if ($marca === 'BYD') {
        return $modelo === 'Song Plus' ? ['Híbrido', 'Elétrico'] : $ELE;
    }
    if ($marca === 'Porsche') {
        if ($modelo === 'Taycan') return $ELE;
        if ($modelo === 'Macan')  return ['Gasolina', 'Elétrico'];
        if (in_array($modelo, ['Cayenne', 'Panamera'])) return $GAS_H;
        return $GAS; // 911
    }
    if ($marca === 'Volvo') {
        if ($modelo === 'EX30') return $ELE;
        if ($modelo === 'XC40') return ['Gasolina', 'Híbrido', 'Elétrico'];
        return $GAS_H; // S60, XC60, XC90
    }

    // Marcas mistas (picape/SUV com diesel)
    if ($marca === 'Jeep') {
        if (in_array($modelo, ['Compass', 'Commander', 'Renegade'])) return $DIES_FLEX;
        return $GAS; // Cherokee, Wrangler
    }
    if ($marca === 'Ram') {
        if ($modelo === 'Rampage') return $DIES_FLEX;
        if ($modelo === '1500')    return $GAS;
        return $DIES; // 2500, 3500
    }
    if ($marca === 'Mitsubishi') {
        if (in_array($modelo, ['L200', 'Pajero'])) return $DIES;
        if ($modelo === 'Eclipse Cross') return $GAS_H;
        return $GAS; // ASX, Outlander
    }

    // Premium importadas — gasolina (+ híbrido em algumas)
    if ($marca === 'Audi') return $GAS;
    if (in_array($marca, ['BMW', 'Mercedes-Benz'])) return $GAS_H;
    if (in_array($marca, ['Kia', 'Subaru', 'Suzuki'])) {
        return $modelo === 'Sorento' ? $DIES_GAS : $GAS;
    }

    // Diesel por modelo (antes dos blocos de híbrido p/ pegar picapes)
    if (in_array($modelo, ['S10', 'Toro', 'Hilux'])) return $DIES_FLEX;
    if (in_array($modelo, ['Ranger', 'Frontier']))   return $DIES_GAS;
    if (in_array($modelo, ['SW4', 'Amarok', 'Master'])) return $DIES;

    // Híbridos mainstream / casos pontuais
    if ($marca === 'Toyota') {
        if (in_array($modelo, ['Corolla', 'Corolla Cross'])) return $FLEX_H;
        if (in_array($modelo, ['Camry', 'RAV4']))            return $GAS_H;
        return $FLEX; // Etios, Yaris
    }
    if ($marca === 'Ford' && $modelo === 'Fusion') return $GAS_H;
    if ($marca === 'Hyundai') {
        if ($modelo === 'Santa Fé') return $GAS_H;
        if ($modelo === 'i30')      return $GAS;
        return $FLEX; // HB20, HB20S, Creta, Tucson
    }
    if ($marca === 'Honda' && $modelo === 'Civic') return $FLEX_H;

    // Padrão: carro brasileiro flex
    return $FLEX;
}

/* 3) Aplica nas linhas existentes */
$sel = $pdo->query("
    SELECT mo.id_modelo, mo.nome AS modelo, ma.nome AS marca
    FROM modelos_veiculos mo
    JOIN marcas_veiculos ma ON ma.id_marca = mo.marca_id
");
$upd = $pdo->prepare("UPDATE modelos_veiculos SET combustiveis = ? WHERE id_modelo = ?");

$n = 0;
foreach ($sel as $r) {
    $valor = implode(',', fuelsFor($r['marca'], $r['modelo']));
    $upd->execute([$valor, $r['id_modelo']]);
    $n++;
}

echo "Modelos atualizados: {$n}\n";
echo "Pronto.\n";
