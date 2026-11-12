<?php

require_once __DIR__ . '/../app/core/HttpTransporte.php';

/**
 * Transporte HTTP dos testes: não toca a rede. Devolve as respostas combinadas, na ordem, e guarda
 * cada requisição feita para o teste conferir o que SERIA enviado ao WhatsApp / provedor.
 */
final class FakeTransporte implements HttpTransporte
{
    /** @var array<int,array{metodo:string,url:string,cabecalhos:array,corpo:?string}> */
    public array $chamadas = [];

    /** @var array<int,array{status:int,corpo:string,erro:?string}> */
    private array $respostas;

    public function __construct(array $respostas = [])
    {
        $this->respostas = $respostas;
    }

    public function enfileirar(int $status, $corpo, ?string $erro = null): self
    {
        $this->respostas[] = ['status' => $status, 'corpo' => is_array($corpo) ? json_encode($corpo) : (string) $corpo, 'erro' => $erro];

        return $this;
    }

    public function requisitar(string $metodo, string $url, array $cabecalhos = [], ?string $corpo = null, int $timeout = 10): array
    {
        $this->chamadas[] = ['metodo' => $metodo, 'url' => $url, 'cabecalhos' => $cabecalhos, 'corpo' => $corpo];

        return array_shift($this->respostas) ?? ['status' => 0, 'corpo' => '', 'erro' => 'sem resposta combinada no teste'];
    }

    public function ultimoCorpo(): array
    {
        $ultima = end($this->chamadas);

        return $ultima ? (array) json_decode((string) $ultima['corpo'], true) : [];
    }
}
