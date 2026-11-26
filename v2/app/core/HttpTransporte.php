<?php

/**
 * Como o sistema fala com serviços externos (hoje, a leitura de placa por foto).
 *
 * É uma interface para que os adaptadores sejam testados SEM rede: os testes entregam um transporte falso
 * que devolve a resposta combinada; em produção entra o CurlTransporte. Nenhum adaptador chama curl direto.
 * (Trazido da v1, app/core/HttpTransporte.php, sem mudanças de comportamento.)
 */
interface HttpTransporte
{
    /**
     * @param array<int,string> $cabecalhos linhas "Nome: valor"
     * @return array{status:int, corpo:string, erro:?string} status 0 = falha de rede (sem resposta)
     */
    public function requisitar(string $metodo, string $url, array $cabecalhos = [], ?string $corpo = null, int $timeout = 10): array;
}

final class CurlTransporte implements HttpTransporte
{
    public function requisitar(string $metodo, string $url, array $cabecalhos = [], ?string $corpo = null, int $timeout = 10): array
    {
        if (!preg_match('#^https://#i', $url) && !preg_match('#^http://(localhost|127\.0\.0\.1)([:/]|$)#i', $url)) {
            return ['status' => 0, 'corpo' => '', 'erro' => 'Somente conexões HTTPS são permitidas.'];
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => strtoupper($metodo),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $cabecalhos,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        // XAMPP no Windows costuma não ter o bundle de certificados configurado.
        $cafile = function_exists('env') ? env('MAIL_CAFILE') : null;
        if ($cafile && is_file($cafile)) {
            curl_setopt($ch, CURLOPT_CAINFO, $cafile);
        }

        if ($corpo !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $corpo);
        }

        $resposta = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro = $resposta === false ? curl_error($ch) : null;
        curl_close($ch);

        return ['status' => $status, 'corpo' => $resposta === false ? '' : (string) $resposta, 'erro' => $erro];
    }
}
