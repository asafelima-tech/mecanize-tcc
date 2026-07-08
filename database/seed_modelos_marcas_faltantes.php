<?php
/*
|--------------------------------------------------------------------------
| Seed: modelos reais para marcas que estavam sem nenhum modelo
|--------------------------------------------------------------------------
| Popula `modelos_veiculos` (com `combustiveis`) para as marcas que não
| tinham modelos cadastrados (Abarth, Dodge, Ferrari, Mazda, Mini, etc.).
|
| - Mapeado por NOME da marca (os ids podem variar entre ambientes).
| - Idempotente: só insere um modelo se ele ainda não existir na marca.
| - `combustiveis` usa EXATAMENTE os rótulos de _combustiveis.php.
|   Em cada modelo, null = usa o 'default' da marca.
|
| Rodar:  php database/seed_modelos_marcas_faltantes.php
*/

$pdo = new PDO("mysql:host=localhost;dbname=mecanize_db;charset=utf8mb4", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

$FLEX = ['Flex (Álcool/Gasolina)', 'Gasolina', 'Etanol'];
$GAS  = ['Gasolina'];
$GASH = ['Gasolina', 'Híbrido'];
$ELE  = ['Elétrico'];
$DIE  = ['Diesel'];
$DIEG = ['Diesel', 'Gasolina'];

/*
 * Estrutura: 'Marca' => ['default' => [...combustiveis...], 'modelos' => ['Nome' => null|[...override...]]]
 */
$dados = [
    'Abarth' => ['default' => $GAS, 'modelos' => [
        '500' => null, '500C' => null, '595' => null, '695' => null,
        '124 Spider' => null, 'Grande Punto' => null, 'Punto Evo' => null,
        'Pulse' => $FLEX, 'Fastback' => $FLEX, '500e' => $ELE, '600e' => $ELE,
    ]],
    'Agrale' => ['default' => $DIE, 'modelos' => [
        'Marruá AM 200' => null, 'Marruá AM 300' => null, 'Marruá AM 350' => null,
    ]],
    'Alfa Romeo' => ['default' => $GAS, 'modelos' => [
        'Giulia' => null, 'Giulietta' => null, 'Stelvio' => null,
        'Tonale' => $GASH, '147' => null, '159' => null, 'MiTo' => null, '4C' => null,
    ]],
    'Aston Martin' => ['default' => $GAS, 'modelos' => [
        'DB11' => null, 'DB12' => null, 'DBX' => null, 'Vantage' => null,
        'DBS' => null, 'Vanquish' => null,
    ]],
    'Bentley' => ['default' => $GAS, 'modelos' => [
        'Continental GT' => null, 'Bentayga' => $GASH, 'Flying Spur' => null, 'Mulsanne' => null,
    ]],
    'Bugatti' => ['default' => $GAS, 'modelos' => [
        'Chiron' => null, 'Veyron' => null, 'Divo' => null, 'Mistral' => null, 'Tourbillon' => null,
    ]],
    'Cadillac' => ['default' => $GAS, 'modelos' => [
        'Escalade' => null, 'CT6' => null, 'XT5' => null, 'Lyriq' => $ELE, 'ATS' => null, 'CTS' => null,
    ]],
    'Chrysler' => ['default' => $GAS, 'modelos' => [
        '300C' => null, 'PT Cruiser' => null, 'Town & Country' => null,
        'Pacifica' => $GASH, 'Sebring' => null,
    ]],
    'Dodge' => ['default' => $GAS, 'modelos' => [
        'Journey' => null, 'Dart' => null, 'Durango' => null, 'Charger' => null,
        'Challenger' => null, 'Avenger' => null,
    ]],
    'DS Automobiles' => ['default' => $GAS, 'modelos' => [
        'DS3' => null, 'DS4' => null, 'DS5' => null, 'DS7' => $GASH, 'DS9' => null,
        'DS3 Crossback' => null,
    ]],
    'Effa' => ['default' => $GAS, 'modelos' => [
        'M100' => null, 'Start' => null, 'Plutus' => null, 'Hafei Towner' => null,
        'V21' => null, 'V25' => null,
    ]],
    'Ferrari' => ['default' => $GAS, 'modelos' => [
        '488 GTB' => null, 'F8 Tributo' => null, 'Roma' => null, 'Portofino' => null,
        'SF90 Stradale' => $GASH, '296 GTB' => $GASH, '812 Superfast' => null,
        'Purosangue' => null, 'California' => null,
    ]],
    'Foton' => ['default' => $DIE, 'modelos' => [
        'Tunland' => null, 'Aumark' => null, 'View' => null, 'Toano' => null, 'BJ40' => $GAS,
    ]],
    'Geely' => ['default' => $GAS, 'modelos' => [
        'Coolray' => null, 'Emgrand' => null, 'GX3 Pro' => null, 'Tugella' => null, 'EX5' => $ELE,
    ]],
    'GWM' => ['default' => $GAS, 'modelos' => [
        'Ora 03' => $ELE, 'Poer' => $DIE, 'Tank 300' => $GASH, 'Tank 500' => $GASH, 'Wingle' => $DIE,
    ]],
    'Haval' => ['default' => $GAS, 'modelos' => [
        'H6' => $GASH, 'H6 GT' => $GASH, 'Jolion' => null, 'H2' => null, 'H9' => $DIEG,
    ]],
    'Infiniti' => ['default' => $GAS, 'modelos' => [
        'Q50' => null, 'Q60' => null, 'QX50' => null, 'QX70' => null, 'QX80' => null, 'G37' => null,
    ]],
    'JAC Motors' => ['default' => $GAS, 'modelos' => [
        'J3' => null, 'J5' => null, 'J6' => null, 'T40' => null, 'T50' => null,
        'T6' => $DIEG, 'e-JS1' => $ELE, 'e-JS4' => $ELE,
    ]],
    'Jaguar' => ['default' => $GAS, 'modelos' => [
        'F-Pace' => null, 'E-Pace' => null, 'I-Pace' => $ELE, 'XE' => null, 'XF' => null,
        'XJ' => null, 'F-Type' => null,
    ]],
    'Jetour' => ['default' => $GAS, 'modelos' => [
        'T2' => null, 'Dashing' => null, 'X70' => null, 'X90' => null, 'T1' => $GASH,
    ]],
    'Koenigsegg' => ['default' => $GAS, 'modelos' => [
        'Jesko' => null, 'Regera' => $GASH, 'Agera' => null, 'Gemera' => $GASH, 'CC850' => null,
    ]],
    'Lamborghini' => ['default' => $GAS, 'modelos' => [
        'Aventador' => null, 'Huracán' => null, 'Urus' => null, 'Revuelto' => $GASH,
        'Gallardo' => null, 'Murciélago' => null,
    ]],
    'Land Rover' => ['default' => $GAS, 'modelos' => [
        'Range Rover' => $GASH, 'Range Rover Sport' => $GASH, 'Range Rover Evoque' => $GASH,
        'Range Rover Velar' => null, 'Discovery' => $DIEG, 'Discovery Sport' => null,
        'Defender' => $DIEG,
    ]],
    'Lexus' => ['default' => $GASH, 'modelos' => [
        'ES' => null, 'IS' => null, 'RX' => null, 'NX' => null, 'UX' => null,
        'LS' => null, 'LX' => $GAS, 'RZ' => $ELE,
    ]],
    'Lifan' => ['default' => $GAS, 'modelos' => [
        '320' => null, '530' => null, '620' => null, 'X60' => null, 'X80' => null,
    ]],
    'Lotus' => ['default' => $GAS, 'modelos' => [
        'Emira' => null, 'Evora' => null, 'Exige' => null, 'Elise' => null,
        'Eletre' => $ELE, 'Emeya' => $ELE, 'Evija' => $ELE,
    ]],
    'Lucid' => ['default' => $ELE, 'modelos' => [
        'Air' => null, 'Gravity' => null,
    ]],
    'Maserati' => ['default' => $GAS, 'modelos' => [
        'Ghibli' => null, 'Quattroporte' => null, 'Levante' => null, 'GranTurismo' => null,
        'Grecale' => null, 'MC20' => null,
    ]],
    'Mazda' => ['default' => $GAS, 'modelos' => [
        'Mazda2' => null, 'Mazda3' => null, 'Mazda6' => null, 'CX-3' => null, 'CX-30' => null,
        'CX-5' => null, 'CX-9' => null, 'MX-5' => null, 'CX-50' => null,
    ]],
    'McLaren' => ['default' => $GAS, 'modelos' => [
        '720S' => null, '750S' => null, 'Artura' => $GASH, 'GT' => null, '765LT' => null, '570S' => null,
    ]],
    'Mini' => ['default' => $GAS, 'modelos' => [
        'Cooper' => null, 'Cooper S' => null, 'Countryman' => null, 'Clubman' => null,
        'Cooper SE' => $ELE, 'Paceman' => null,
    ]],
    'NIO' => ['default' => $ELE, 'modelos' => [
        'ET5' => null, 'ET7' => null, 'ES6' => null, 'ES8' => null, 'EC6' => null, 'EL7' => null,
    ]],
    'Omoda' => ['default' => $GAS, 'modelos' => [
        'Omoda 5' => null, 'Omoda 5 GT' => null, 'Omoda 7' => null, 'Omoda E5' => $ELE,
    ]],
    'Pagani' => ['default' => $GAS, 'modelos' => [
        'Huayra' => null, 'Zonda' => null, 'Utopia' => null,
    ]],
    'Puma' => ['default' => $GAS, 'modelos' => [
        'GT' => null, 'GTS' => null, 'GTB' => null, 'P018' => null,
    ]],
    'Rivian' => ['default' => $ELE, 'modelos' => [
        'R1T' => null, 'R1S' => null, 'R2' => null, 'R3' => null,
    ]],
    'Rolls-Royce' => ['default' => $GAS, 'modelos' => [
        'Phantom' => null, 'Ghost' => null, 'Cullinan' => null, 'Wraith' => null,
        'Dawn' => null, 'Spectre' => $ELE,
    ]],
    'Seat' => ['default' => $GAS, 'modelos' => [
        'Ibiza' => null, 'Leon' => null, 'Ateca' => null, 'Arona' => null,
        'Córdoba' => null, 'Toledo' => null,
    ]],
    'Shineray' => ['default' => $GAS, 'modelos' => [
        'T20' => null, 'T30' => $DIEG, 'X30' => null, 'T40' => null,
    ]],
    'Skoda' => ['default' => $GAS, 'modelos' => [
        'Octavia' => null, 'Fabia' => null, 'Superb' => null, 'Kodiaq' => null,
        'Kamiq' => null, 'Scala' => null, 'Karoq' => null, 'Enyaq' => $ELE,
    ]],
    'Smart' => ['default' => $GAS, 'modelos' => [
        'Fortwo' => null, 'Forfour' => null, '#1' => $ELE, '#3' => $ELE,
    ]],
    'SsangYong' => ['default' => $DIE, 'modelos' => [
        'Korando' => $GAS, 'Tivoli' => $GAS, 'Rexton' => null, 'Actyon' => null,
        'Musso' => null, 'Kyron' => null,
    ]],
    'Troller' => ['default' => $DIE, 'modelos' => [
        'T4' => null, 'TX4' => null, 'Pantanal' => null,
    ]],
    'XPeng' => ['default' => $ELE, 'modelos' => [
        'P7' => null, 'G3' => null, 'G6' => null, 'G9' => null, 'P5' => null, 'X9' => null,
    ]],
    'Zeekr' => ['default' => $ELE, 'modelos' => [
        '001' => null, '007' => null, 'X' => null, '009' => null, '7X' => null,
    ]],
];

$findMarca = $pdo->prepare("SELECT id_marca FROM marcas_veiculos WHERE nome = ? LIMIT 1");
$exists    = $pdo->prepare("SELECT 1 FROM modelos_veiculos WHERE marca_id = ? AND LOWER(nome) = LOWER(?) LIMIT 1");
$insert    = $pdo->prepare("INSERT INTO modelos_veiculos (marca_id, nome, combustiveis) VALUES (?, ?, ?)");

$totalIns = 0;
$marcasNaoEncontradas = [];

foreach ($dados as $marcaNome => $cfg) {
    $findMarca->execute([$marcaNome]);
    $marcaId = $findMarca->fetchColumn();

    if (!$marcaId) {
        $marcasNaoEncontradas[] = $marcaNome;
        continue;
    }

    $insMarca = 0;
    foreach ($cfg['modelos'] as $nome => $override) {
        $exists->execute([$marcaId, $nome]);
        if ($exists->fetchColumn()) {
            continue; // já existe → idempotente
        }
        $combustiveis = implode(',', $override ?: $cfg['default']);
        $insert->execute([$marcaId, $nome, $combustiveis]);
        $insMarca++;
        $totalIns++;
    }
    echo str_pad($marcaNome, 22) . " +{$insMarca} modelo(s)\n";
}

echo "\nTotal de modelos inseridos: {$totalIns}\n";
if ($marcasNaoEncontradas) {
    echo "Marcas do seed que NÃO existem no banco (ignoradas): " . implode(', ', $marcasNaoEncontradas) . "\n";
}
echo "Pronto.\n";
