<?php

return [
    'host'                  => env('MAIL_HOST', 'smtp.gmail.com'),
    'porta'                 => (int) env('MAIL_PORT', 587),
    'usuario'               => env('MAIL_USER'),
    'senha'                 => env('MAIL_PASS'), // sua Senha de App
    'remetente_nome'        => env('MAIL_FROM_NAME', 'Mecanize'),
    'remetente_email'       => env('MAIL_FROM_EMAIL'),
    'verificar_certificado' => filter_var(env('MAIL_VERIFY_CERT', true), FILTER_VALIDATE_BOOLEAN),
    'cafile'                => env('MAIL_CAFILE'),
];
