<?php

/** Paginação simples das listas: ?pagina=N, 20 por página (cada tela pode pedir outro tamanho, como a Agenda). */
final class Paginacao
{
    public const POR_PAGINA = 20;

    public readonly int $pagina;
    public readonly int $porPagina;
    public int $total = 0;

    public function __construct(?int $pagina = null, int $porPagina = self::POR_PAGINA)
    {
        $p = $pagina ?? (int) ($_GET['pagina'] ?? 1);
        $this->pagina = max(1, $p);
        $this->porPagina = max(1, $porPagina);
    }

    public function limite(): int
    {
        return $this->porPagina;
    }

    public function deslocamento(): int
    {
        return ($this->pagina - 1) * $this->porPagina;
    }

    /** Trecho SQL "LIMIT x OFFSET y" (inteiros calculados aqui, nunca vindos do usuário). */
    public function sql(): string
    {
        return ' LIMIT ' . $this->limite() . ' OFFSET ' . $this->deslocamento();
    }

    public function paginas(): int
    {
        return max(1, (int) ceil($this->total / $this->porPagina));
    }
}
