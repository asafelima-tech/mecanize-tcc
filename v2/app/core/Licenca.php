<?php

/**
 * Regras da licença da oficina (SaaS). Ficam na própria tabela `oficinas`: plano, status e validade.
 *
 *   TESTE / ATIVA dentro da validade → uso normal
 *   validade vencida ou SUSPENSA      → SOMENTE LEITURA (consultar e imprimir continuam; gravar não)
 *   CANCELADA                         → não entra no sistema
 *
 * Somente leitura em vez de bloqueio total: os dados são da oficina, e ela precisa conseguir
 * consultá-los mesmo com a assinatura atrasada.
 */
final class Licenca
{
    /** Avisar na tela quando faltar até este número de dias. */
    public const DIAS_AVISO = 7;

    public static function vencida(array $oficina, ?DateTimeInterface $hoje = null): bool
    {
        $hoje = ($hoje ?? new DateTimeImmutable('today'))->format('Y-m-d');

        return (string) $oficina['licenca_valida_ate'] < $hoje;
    }

    public static function permiteEntrar(array $oficina): bool
    {
        return $oficina['status'] !== 'CANCELADA';
    }

    public static function permiteEscrita(array $oficina, ?DateTimeInterface $hoje = null): bool
    {
        return in_array($oficina['status'], ['TESTE', 'ATIVA'], true) && !self::vencida($oficina, $hoje);
    }

    public static function diasRestantes(array $oficina, ?DateTimeInterface $hoje = null): int
    {
        $hoje = $hoje ?? new DateTimeImmutable('today');

        return (int) $hoje->diff(new DateTimeImmutable((string) $oficina['licenca_valida_ate']))->format('%r%a');
    }

    /** Texto para a faixa de aviso do topo; null quando não há nada a avisar. */
    public static function mensagem(array $oficina, ?DateTimeInterface $hoje = null): ?string
    {
        if ($oficina['status'] === 'CANCELADA') {
            return 'A assinatura desta oficina foi cancelada.';
        }
        if ($oficina['status'] === 'SUSPENSA') {
            return 'A assinatura está suspensa: o sistema está em modo somente leitura. Fale com o suporte do Mecanize.';
        }
        if (self::vencida($oficina, $hoje)) {
            return 'A licença venceu em ' . date('d/m/Y', strtotime((string) $oficina['licenca_valida_ate']))
                . ': o sistema está em modo somente leitura até a renovação.';
        }

        $dias = self::diasRestantes($oficina, $hoje);
        if ($dias <= self::DIAS_AVISO) {
            return $dias === 0 ? 'A licença vence hoje.' : "A licença vence em {$dias} dia(s).";
        }

        return null;
    }
}
