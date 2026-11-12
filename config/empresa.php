<?php

/**
 * Dados da oficina usados no papel timbrado de TODO documento impresso
 * (PDF de OS/orçamento, relatório de caixa, impressão de qualquer tela).
 *
 * Antes disso os templates já liam EMPRESA_NOME/EMPRESA_CNPJ/etc., mas as
 * constantes não eram definidas em lugar nenhum: todo documento saía com o
 * nome padrão e o endereço/telefone/CNPJ em branco — um documento entregue
 * ao cliente sem identificação de quem emitiu.
 *
 * Os valores vêm do .env (EMPRESA_*), então cada oficina preenche os seus
 * sem tocar no código. O que ficar em branco simplesmente não é impresso.
 */

if (!defined('EMPRESA_NOME')) {
    define('EMPRESA_NOME', env('EMPRESA_NOME', 'Mecanize'));
}

if (!defined('EMPRESA_CNPJ')) {
    define('EMPRESA_CNPJ', env('EMPRESA_CNPJ', ''));
}

if (!defined('EMPRESA_ENDERECO')) {
    define('EMPRESA_ENDERECO', env('EMPRESA_ENDERECO', ''));
}

if (!defined('EMPRESA_TELEFONE')) {
    define('EMPRESA_TELEFONE', env('EMPRESA_TELEFONE', ''));
}

if (!defined('EMPRESA_EMAIL')) {
    define('EMPRESA_EMAIL', env('EMPRESA_EMAIL', ''));
}

if (!defined('EMPRESA_HORARIO')) {
    define('EMPRESA_HORARIO', env('EMPRESA_HORARIO', ''));
}
