<?php

/** Paginação simples das listas: ?pagina=N, 20 por página. */
final class Paginacao
{
    public const POR_PAGINA = 20;

    public readonly int $pagina;
    public int $total = 0;

    public function __construct(?int $pagina = null)
    {
        $p = $pagina ?? (int) ($_GET['pagina'] ?? 1);
        $this->pagina = max(1, $p);
    }

    public function limite(): int
    {
        return self::POR_PAGINA;
    }

    public function deslocamento(): int
    {
        return ($this->pagina - 1) * self::POR_PAGINA;
    }

    /** Trecho SQL "LIMIT x OFFSET y" (inteiros calculados aqui, nunca vindos do usuário). */
    public function sql(): string
    {
        return ' LIMIT ' . $this->limite() . ' OFFSET ' . $this->deslocamento();
    }

    public function paginas(): int
    {
        return max(1, (int) ceil($this->total / self::POR_PAGINA));
    }
}
