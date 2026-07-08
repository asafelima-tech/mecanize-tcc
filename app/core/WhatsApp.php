<?php

/**
 * Gera links "wa.me" com mensagem pré-preenchida. Não envia nada sozinho —
 * quem clica no link é que confirma o envio pelo WhatsApp Web/app já logado.
 * Não depende de nenhuma conta/API paga (Meta Cloud API, Twilio, Z-API etc.),
 * que exigiriam cadastro comercial à parte para automatizar o envio de verdade.
 */
class WhatsApp
{
    public static function linkParaCliente(string $telefone, string $mensagem): string
    {
        $digitos = preg_replace('/\D/', '', $telefone);

        // Garante o DDI 55 (Brasil) sem duplicar quando o telefone já vem com ele.
        if ($digitos !== '' && substr($digitos, 0, 2) !== '55') {
            $digitos = '55' . $digitos;
        }

        return 'https://wa.me/' . $digitos . '?text=' . urlencode($mensagem);
    }
}
