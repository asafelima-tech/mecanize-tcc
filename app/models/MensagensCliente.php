<?php

/**
 * Textos das mensagens automáticas ao cliente — uma por mudança REAL de estado.
 *
 * Curtos, sem jargão, sem dado sensível além do necessário (valor só nos eventos financeiros) e
 * sempre com o caminho para agir (o link do portal) quando há algo a decidir ou pagar.
 *
 * Para o WhatsApp fora da janela de 24 h só vale template aprovado pela Meta. Os parâmetros do
 * template, nesta ordem, são os de `parametros()`: {{1}} primeiro nome · {{2}} placa · {{3}} link
 * (ou o número da OS/orçamento quando não há link). O texto do template é escrito pela oficina na
 * Meta; este arquivo só entrega os valores.
 */
final class MensagensCliente
{
    public const EVENTOS = [
        'VEICULO_RECEBIDO'      => 'Veículo recebido',
        'AVALIACAO_INICIADA'    => 'Avaliação iniciada',
        'ORCAMENTO_DISPONIVEL'  => 'Orçamento disponível para aprovação',
        'ORCAMENTO_APROVADO'    => 'Orçamento aprovado',
        'ORCAMENTO_RECUSADO'    => 'Orçamento recusado',
        'SERVICO_INICIADO'      => 'Serviço iniciado',
        'AUTORIZACAO_ADICIONAL' => 'Autorização adicional necessária',
        'PRONTO_RETIRADA'       => 'Veículo liberado para retirada',
        'OS_CONCLUIDA'          => 'Ordem de serviço concluída',
        'PAGAMENTO_CONFIRMADO'  => 'Pagamento confirmado',
        'PAGAMENTO_ESTORNADO'   => 'Pagamento estornado',
        // Não nasce de mudança de estado: é o atendente reenviando o link de acompanhamento.
        'LINK_ACOMPANHAMENTO'   => 'Link de acompanhamento',
    ];

    public static function primeiroNome(?string $nome): string
    {
        $p = preg_split('/\s+/', trim((string) $nome));

        return $p[0] !== '' ? mb_convert_case(mb_strtolower($p[0]), MB_CASE_TITLE) : 'cliente';
    }

    private static function brl($v): string
    {
        return 'R$ ' . number_format((float) $v, 2, ',', '.');
    }

    /**
     * @param array{nome?:string, placa?:string, veiculo?:string, numero?:string, valor?:float, link?:string, empresa?:string, saldo?:float, previsao?:string, motivo?:string} $d
     * @return array{assunto:string, texto:string, parametros:string[]}
     */
    public static function montar(string $evento, array $d): array
    {
        $nome = self::primeiroNome($d['nome'] ?? '');
        $emp = $d['empresa'] ?? 'Mecanize';
        $placa = strtoupper((string) ($d['placa'] ?? ''));
        $veic = trim((string) ($d['veiculo'] ?? '')) . ($placa !== '' ? ' (' . $placa . ')' : '');
        $veic = trim($veic) !== '' ? trim($veic) : 'seu veículo';
        $num = (string) ($d['numero'] ?? '');
        $link = (string) ($d['link'] ?? '');
        $verLink = $link !== '' ? "\n\nAcompanhe e decida por aqui: " . $link : '';

        $texto = match ($evento) {
            'VEICULO_RECEBIDO'     => "Olá, {$nome}! Recebemos {$veic} na {$emp}. Vamos avaliar e avisar você sobre os próximos passos." . ($link !== '' ? "\n\nAcompanhe: " . $link : ''),
            'AVALIACAO_INICIADA'   => "Olá, {$nome}! A avaliação técnica de {$veic} começou. Assim que terminar, você recebe o orçamento por aqui." . ($link !== '' ? "\n\nAcompanhe: " . $link : ''),
            'ORCAMENTO_DISPONIVEL' => "Olá, {$nome}! O orçamento " . ($num !== '' ? $num . ' ' : '') . "de {$veic} está pronto: " . (isset($d['valor']) ? self::brl($d['valor']) . '. ' : '') . "Veja os itens e aprove ou recuse com segurança pelo link:\n" . $link,
            'ORCAMENTO_APROVADO'   => "Olá, {$nome}! Registramos a aprovação do orçamento " . ($num !== '' ? $num . ' ' : '') . "de {$veic}. Vamos iniciar o serviço e avisar cada etapa." . ($link !== '' ? "\n\nAcompanhe: " . $link : ''),
            'ORCAMENTO_RECUSADO'   => "Olá, {$nome}! Registramos que o orçamento " . ($num !== '' ? $num . ' ' : '') . "de {$veic} não foi aprovado. Se mudar de ideia ou quiser ajustar algo, é só responder esta mensagem.",
            'SERVICO_INICIADO'     => "Olá, {$nome}! O serviço em {$veic} foi iniciado" . ($num !== '' ? " (OS {$num})" : '') . '.' . (!empty($d['previsao']) ? " Previsão de conclusão: {$d['previsao']}." : '') . ($link !== '' ? "\n\nAcompanhe: " . $link : ''),
            'AUTORIZACAO_ADICIONAL' => "Olá, {$nome}! Durante o serviço em {$veic} identificamos algo que precisa da sua autorização" . (isset($d['valor']) ? ' (' . self::brl($d['valor']) . ' adicionais)' : '') . '.' . (!empty($d['motivo']) ? " Motivo: {$d['motivo']}." : '') . "\n\nDecida pelo link:\n" . $link,
            'PRONTO_RETIRADA'      => "Olá, {$nome}! {$veic} está pronto para retirada" . ($num !== '' ? " (OS {$num})" : '') . '.' . (!empty($d['saldo']) ? ' Valor a pagar: ' . self::brl($d['saldo']) . '.' : '') . ($link !== '' ? "\n\nVeja detalhes" . (!empty($d['saldo']) ? ' e pague online' : '') . ': ' . $link : ''),
            'OS_CONCLUIDA'         => "Olá, {$nome}! A ordem de serviço" . ($num !== '' ? " {$num}" : '') . " de {$veic} foi concluída. Obrigado por escolher a {$emp}!",
            'PAGAMENTO_CONFIRMADO' => "Olá, {$nome}! Confirmamos o pagamento" . (isset($d['valor']) ? ' de ' . self::brl($d['valor']) : '') . ($num !== '' ? " da OS {$num}" : '') . '. Obrigado!',
            'PAGAMENTO_ESTORNADO'  => "Olá, {$nome}! O pagamento" . (isset($d['valor']) ? ' de ' . self::brl($d['valor']) : '') . ($num !== '' ? " da OS {$num}" : '') . ' foi estornado. Em caso de dúvida, responda esta mensagem.',
            'LINK_ACOMPANHAMENTO'  => "Olá, {$nome}! Aqui está o link para acompanhar o atendimento de {$veic} na {$emp}:\n" . $link,
            default                => "Olá, {$nome}! Há uma atualização sobre {$veic}." . ($link !== '' ? "\n\n" . $link : ''),
        };

        return [
            'assunto'    => (self::EVENTOS[$evento] ?? 'Atualização') . ' — ' . $emp,
            'texto'      => $texto,
            'parametros' => [$nome, $placa !== '' ? $placa : $veic, $link !== '' ? $link : $num],
        ];
    }
}
