<?php

/**
 * Envia as mensagens pendentes aos clientes (WhatsApp / e-mail).
 *
 * Rode a cada minuto pelo agendador do servidor:
 *   Windows (Agendador de Tarefas):  C:\xampp\php\php.exe C:\xampp\htdocs\mecanize\bin\enviar_mensagens.php
 *   Linux (cron):                    * * * * * php /caminho/mecanize/bin/enviar_mensagens.php
 *
 * É seguro rodar junto com o envio feito ao fim das requisições do sistema: cada mensagem é
 * reservada antes de ser enviada, então nunca sai duas vezes. Sem canal configurado, não faz nada
 * (as mensagens continuam registradas e visíveis nas conversas, como "não enviadas").
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../app/core/Env.php';
Env::carregar(__DIR__ . '/../.env');

require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Comunicacao.php';

try {
    $comunicacao = new Comunicacao();

    if (!(new WhatsAppCloud())->configurado() && !Integracoes::emailConfigurado()) {
        echo "Nenhum canal de envio configurado (WhatsApp/e-mail). Nada a fazer.\n";
        exit(0);
    }

    $total = ['enviadas' => 0, 'falhas' => 0, 'puladas' => 0];

    // Lotes pequenos até esvaziar a fila (ou até uns 60 s, para não sobrepor a execução do minuto seguinte).
    $limite = time() + 55;
    while (time() < $limite && $comunicacao->haPendentes()) {
        $r = $comunicacao->despacharPendentes(20);

        foreach ($total as $k => $_) {
            $total[$k] += $r[$k];
        }

        if (array_sum($r) === 0) {
            break;
        }
    }

    echo sprintf("[%s] Enviadas: %d · Falhas: %d · Puladas: %d\n", date('Y-m-d H:i:s'), $total['enviadas'], $total['falhas'], $total['puladas']);
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, '[enviar_mensagens] ' . $e->getMessage() . "\n");
    exit(1);
}
