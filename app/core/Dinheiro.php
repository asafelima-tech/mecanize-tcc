<?php

/**
 * Aritmética de dinheiro em um lugar só: valores digitados em formato brasileiro, divisão em
 * parcelas que fecha centavo por centavo, e soma de meses sem estourar o fim do mês.
 *
 * Existe para que tela, gravação e relatório cheguem exatamente ao mesmo número — três
 * arredondamentos soltos divergem em um centavo e a soma das parcelas deixa de bater com o total.
 */
final class Dinheiro
{
    /**
     * "1.500,00" → 1500.00 · "1500,5" → 1500.50 · "1500.50" → 1500.50 · "" → 0.
     * Com vírgula, o ponto é separador de milhar; sem vírgula, o ponto é decimal.
     */
    public static function parse($valor): float
    {
        $v = trim((string) $valor);

        if ($v === '') {
            return 0.0;
        }

        if (str_contains($v, ',')) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        }

        return is_numeric($v) ? round((float) $v, 2) : 0.0;
    }

    /**
     * Divide $total em $n parcelas de centavos inteiros; a última leva a sobra, para a soma fechar
     * com o total (100,00 em 3x → 33,33 + 33,33 + 33,34).
     *
     * @return float[]
     */
    public static function dividir(float $total, int $n): array
    {
        $n = max(1, $n);
        $centavos = (int) round($total * 100);
        $base = intdiv($centavos, $n);
        $partes = array_fill(0, $n, $base);
        $partes[$n - 1] += $centavos - ($base * $n);

        return array_map(fn($c) => $c / 100, $partes);
    }

    /** Soma $meses a uma data (Y-m-d); 31/01 + 1 mês = 28/02 (ou 29), nunca 03/03. */
    public static function somarMeses(string $data, int $meses): string
    {
        $d = new DateTimeImmutable($data);
        $dia = (int) $d->format('j');
        $alvo = $d->modify('first day of this month')->modify(($meses >= 0 ? '+' : '') . $meses . ' months');
        $ultimo = (int) $alvo->format('t');

        return $alvo->setDate((int) $alvo->format('Y'), (int) $alvo->format('n'), min($dia, $ultimo))->format('Y-m-d');
    }

    public static function formatar(float $v): string
    {
        return 'R$ ' . number_format($v, 2, ',', '.');
    }
}
