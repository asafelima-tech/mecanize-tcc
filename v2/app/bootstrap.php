<?php

/**
 * Carga comum da v2 — usada pelo site (public/index.php), pelos testes e pelos scripts de bin/.
 *
 * Um lugar só para: ler o .env (o mesmo da raiz do projeto), fuso horário e carregar as classes.
 * Controllers e models são carregados sob demanda pelo nome da classe (autoload), então não existe
 * mais a lista de require_once espalhada pelos arquivos.
 */

declare(strict_types=1);

define('V2_RAIZ', dirname(__DIR__));            // .../mecanize-tcc/v2
define('PROJETO_RAIZ', dirname(V2_RAIZ));        // .../mecanize-tcc

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/core/Env.php';
Env::carregar(PROJETO_RAIZ . '/.env');

spl_autoload_register(static function (string $classe): void {
    foreach (['core', 'models', 'controllers'] as $pasta) {
        $arquivo = __DIR__ . "/{$pasta}/{$classe}.php";
        if (is_file($arquivo)) {
            require_once $arquivo;
            return;
        }
    }
});

require_once __DIR__ . '/core/Html.php';
require_once __DIR__ . '/core/Erro.php';      // também declara ErroHttp
require_once __DIR__ . '/core/Contexto.php';  // também declara LicencaBloqueada
