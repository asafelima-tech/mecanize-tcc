<?php

/**
 * Configuração das integrações externas, lida do ambiente (.env) — nunca do banco, nunca exibida.
 *
 * O sistema nunca finge uma integração: cada canal/provedor só fica "configurado" quando TODAS as
 * variáveis que ele exige estão preenchidas (e não são o texto de exemplo). Enquanto não estiver, o
 * canal responde "não configurado" e a tela lista o que falta — sem revelar valor algum.
 *
 *   WHATSAPP_ENABLED, WHATSAPP_PHONE_NUMBER_ID, WHATSAPP_ACCESS_TOKEN, WHATSAPP_APP_SECRET,
 *   WHATSAPP_VERIFY_TOKEN, [WHATSAPP_API_VERSION], [WHATSAPP_TEMPLATE_LANG],
 *   [WHATSAPP_TEMPLATE_<EVENTO>]
 *   PAGAMENTOS_PROVEDOR = mercadopago | simulado, MERCADOPAGO_ACCESS_TOKEN, MERCADOPAGO_WEBHOOK_SECRET,
 *   [PAGAMENTOS_MAX_PARCELAS_CARTAO], [PAGAMENTOS_PERMITIR_SIMULADO]
 *   APP_URL (endereço público do sistema: links das mensagens e URL dos webhooks)
 *   [PORTAL_VALIDADE_DIAS]
 */
final class Integracoes
{
    /** Eventos que podem ter template aprovado no WhatsApp (nome do template em WHATSAPP_TEMPLATE_<EVENTO>). */
    public const EVENTOS_TEMPLATE = [
        'VEICULO_RECEBIDO', 'AVALIACAO_INICIADA', 'ORCAMENTO_DISPONIVEL', 'ORCAMENTO_APROVADO', 'ORCAMENTO_RECUSADO',
        'SERVICO_INICIADO', 'AUTORIZACAO_ADICIONAL', 'PRONTO_RETIRADA', 'OS_CONCLUIDA', 'PAGAMENTO_CONFIRMADO',
    ];

    private static function v(string $chave, $padrao = null)
    {
        $valor = function_exists('env') ? env($chave, $padrao) : $padrao;

        return is_string($valor) ? trim($valor) : $valor;
    }

    private static function preenchido($valor): bool
    {
        if (!is_string($valor) || $valor === '') {
            return false;
        }

        // texto de exemplo do .env.example
        return !preg_match('/^(seu-|sua-|troque|exemplo|xxx|\.\.\.)/i', $valor);
    }

    /** @return array<string,mixed> */
    public static function whatsapp(): array
    {
        $templates = [];
        foreach (self::EVENTOS_TEMPLATE as $evento) {
            $nome = self::v('WHATSAPP_TEMPLATE_' . $evento);
            if (self::preenchido($nome)) {
                $templates[$evento] = $nome;
            }
        }

        return [
            'ativo'           => filter_var(self::v('WHATSAPP_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'phone_number_id' => (string) self::v('WHATSAPP_PHONE_NUMBER_ID', ''),
            'access_token'    => (string) self::v('WHATSAPP_ACCESS_TOKEN', ''),
            'app_secret'      => (string) self::v('WHATSAPP_APP_SECRET', ''),
            'verify_token'    => (string) self::v('WHATSAPP_VERIFY_TOKEN', ''),
            'api_version'     => (string) self::v('WHATSAPP_API_VERSION', 'v20.0'),
            'idioma'          => (string) self::v('WHATSAPP_TEMPLATE_LANG', 'pt_BR'),
            'templates'       => $templates,
        ];
    }

    /** @return string[] variáveis que faltam para o WhatsApp funcionar (vazio = configurado) */
    public static function whatsappFaltando(?array $cfg = null): array
    {
        $cfg = $cfg ?? self::whatsapp();
        $faltando = [];

        if (empty($cfg['ativo'])) {
            $faltando[] = 'WHATSAPP_ENABLED';
        }

        foreach (['WHATSAPP_PHONE_NUMBER_ID' => 'phone_number_id', 'WHATSAPP_ACCESS_TOKEN' => 'access_token',
                  'WHATSAPP_APP_SECRET' => 'app_secret', 'WHATSAPP_VERIFY_TOKEN' => 'verify_token'] as $variavel => $chave) {
            if (!self::preenchido($cfg[$chave] ?? '')) {
                $faltando[] = $variavel;
            }
        }

        return $faltando;
    }
    public static function whatsappConfigurado(?array $cfg = null): bool
    {
        return self::whatsappFaltando($cfg) === [];
    }

    public static function emailConfigurado(): bool
    {
        return self::preenchido((string) self::v('MAIL_HOST', ''))
            && self::preenchido((string) self::v('MAIL_USER', ''))
            && self::preenchido((string) self::v('MAIL_PASS', ''))
            && self::preenchido((string) self::v('MAIL_FROM_EMAIL', ''));
    }

    /** @return array<string,mixed> */
    public static function pagamentos(): array
    {
        $provedor = strtolower((string) self::v('PAGAMENTOS_PROVEDOR', ''));

        return [
            'provedor'         => in_array($provedor, ['mercadopago', 'simulado'], true) ? $provedor : '',
            'mp_access_token'  => (string) self::v('MERCADOPAGO_ACCESS_TOKEN', ''),
            'mp_webhook_secret' => (string) self::v('MERCADOPAGO_WEBHOOK_SECRET', ''),
            'max_parcelas'     => max(1, min(12, (int) self::v('PAGAMENTOS_MAX_PARCELAS_CARTAO', 1))),
            'simulado_segredo' => (string) self::v('PAGAMENTOS_SIMULADO_SEGREDO', ''),
            'permitir_simulado' => filter_var(self::v('PAGAMENTOS_PERMITIR_SIMULADO', false), FILTER_VALIDATE_BOOLEAN)
                || filter_var(self::v('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /** @return string[] */
    public static function pagamentosFaltando(?array $cfg = null): array
    {
        $cfg = $cfg ?? self::pagamentos();

        if ($cfg['provedor'] === '') {
            return ['PAGAMENTOS_PROVEDOR'];
        }

        if ($cfg['provedor'] === 'simulado') {
            return array_merge(
                $cfg['permitir_simulado'] ? [] : ['PAGAMENTOS_PERMITIR_SIMULADO (ou APP_DEBUG=true)'],
                self::preenchido($cfg['simulado_segredo']) ? [] : ['PAGAMENTOS_SIMULADO_SEGREDO']
            );
        }

        return array_keys(array_filter(
            ['MERCADOPAGO_ACCESS_TOKEN' => $cfg['mp_access_token'], 'MERCADOPAGO_WEBHOOK_SECRET' => $cfg['mp_webhook_secret']],
            fn($v) => !self::preenchido($v)
        ));
    }

    public static function pagamentosConfigurado(?array $cfg = null): bool
    {
        return self::pagamentosFaltando($cfg) === [];
    }

    public static function validadePortalDias(): int
    {
        return max(1, min(365, (int) self::v('PORTAL_VALIDADE_DIAS', 30)));
    }

    /**
     * Endereço público do sistema (sem barra final). APP_URL tem prioridade — links de mensagem e
     * URL de webhook precisam ser os de produção, não o que a requisição de quem disparou viu.
     */
    public static function appUrl(): string
    {
        $url = (string) self::v('APP_URL', '');

        if (preg_match('#^https?://#i', $url)) {
            return rtrim($url, '/');
        }

        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $base = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')), '/\\');

        return ($https ? 'https://' : 'http://') . $host . $base;
    }

    /** @return string[] */
    private static function consultaPlacaFaltando(): array
    {
        require_once __DIR__ . '/ConsultaVeicular.php';

        return (new ConsultaVeicular())->faltando();
    }

    /**
     * Resumo para a tela do administrador: o que está pronto e o que falta — SEM nenhum valor.
     *
     * @return array<string,array{nome:string, configurado:bool, faltando:string[], observacao:string}>
     */
    public static function resumo(): array
    {
        $wa = self::whatsappFaltando();
        $pg = self::pagamentosFaltando();
        $cfgPg = self::pagamentos();

        return [
            'whatsapp' => [
                'nome' => 'WhatsApp Business (API oficial da Meta)', 'configurado' => $wa === [], 'faltando' => $wa,
                'observacao' => 'Exige conta Business, número, token, webhook público em HTTPS e templates aprovados para mensagens fora da janela de 24 h.',
            ],
            'email' => [
                'nome' => 'E-mail (SMTP)', 'configurado' => self::emailConfigurado(),
                'faltando' => self::emailConfigurado() ? [] : ['MAIL_HOST', 'MAIL_USER', 'MAIL_PASS', 'MAIL_FROM_EMAIL'],
                'observacao' => 'Usado para avisar o cliente quando o WhatsApp não está disponível para ele.',
            ],
            'pagamentos' => [
                'nome' => 'Pagamento online (' . ($cfgPg['provedor'] === 'simulado' ? 'provedor SIMULADO — só para teste' : ($cfgPg['provedor'] === 'mercadopago' ? 'Mercado Pago' : 'nenhum provedor escolhido')) . ')',
                'configurado' => $pg === [], 'faltando' => $pg,
                'observacao' => 'Exige contratar um provedor e uma URL pública HTTPS para o webhook. Nenhum dado de cartão passa pelo Mecanize.',
            ],
            'leitura_placa' => [
                'nome' => 'Leitura de placa pela foto (Plate Recognizer)', 'configurado' => self::preenchido((string) self::v('PLATE_RECOGNIZER_TOKEN', '')),
                'faltando' => self::preenchido((string) self::v('PLATE_RECOGNIZER_TOKEN', '')) ? [] : ['PLATE_RECOGNIZER_TOKEN'],
                'observacao' => 'A leitura é só uma sugestão: a pessoa confere antes de a placa entrar no cadastro. Cada leitura tem custo no plano contratado (há teto por sessão).',
            ],
            'consulta_placa' => [
                'nome' => 'Consulta de dados do veículo pela placa', 'configurado' => self::consultaPlacaFaltando() === [],
                'faltando' => self::consultaPlacaFaltando(),
                'observacao' => 'Depende de um serviço contratado: o endereço, a credencial e o caminho de cada campo na resposta saem da documentação dele (nenhum provedor é presumido). Só dados do VEÍCULO atravessam; sem isto, o cadastro é manual.',
            ],
            'app_url' => [
                'nome' => 'Endereço público do sistema (APP_URL)', 'configurado' => preg_match('#^https?://#i', (string) self::v('APP_URL', '')) === 1,
                'faltando' => preg_match('#^https?://#i', (string) self::v('APP_URL', '')) === 1 ? [] : ['APP_URL'],
                'observacao' => 'Os links enviados ao cliente e as URLs dos webhooks usam este endereço.',
            ],
        ];
    }
}
