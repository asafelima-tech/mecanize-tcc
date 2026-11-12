<?php

/**
 * Regras de SESSÃO — cada uma no seu método, cada uma com o seu motivo.
 *
 * Antes, três regras diferentes (inatividade, conta alterada, horário do
 * atendente) terminavam no mesmo `./logout?expirado=1`, e a tela de login
 * traduzia `expirado` como "seu horário de acesso foi encerrado". Resultado:
 * um ADMIN parado por inatividade lia a regra do atendente. O defeito não era a
 * mensagem, era o padrão: um único "deu errado" para causas diferentes.
 *
 * Agora: quem decide a regra devolve o MOTIVO; quem escreve a mensagem só lê o
 * motivo. Os dois só se encontram aqui.
 *
 *   inatividade       parado além do limite              (todos os perfis)
 *   horario           fora do horário de acesso          (SÓ ATENDENTE)
 *   conta_alterada    administrador mudou a conta
 *   conta_desativada  conta desativada ou apagada
 *   sessao_invalida   sessão incoerente / vencida por outro motivo
 *
 * "Sair" (logout manual) não é motivo: é ação normal, sem aviso.
 */
class Sessao
{
    public const INATIVIDADE      = 'inatividade';
    public const HORARIO          = 'horario';
    public const CONTA_ALTERADA   = 'conta_alterada';
    public const CONTA_DESATIVADA = 'conta_desativada';
    public const SESSAO_INVALIDA  = 'sessao_invalida';

    /** Chave da sessão onde o motivo do encerramento forçado espera o logout. */
    private const CHAVE_MOTIVO = 'encerramento_motivo';

    public const MENSAGEM_JANELA = 'Atendentes têm acesso de segunda a sexta das 07:00 às 18:00 e aos sábados das 07:00 às 13:00.';

    /**
     * Minutos parados até a sessão cair — o ÚNICO relógio de inatividade.
     * session.gc_maxlifetime é derivado dele (ver configurarArmazenamento).
     * Configurável por SESSAO_INATIVIDADE_MINUTOS no .env; padrão 60.
     */
    public static function inatividadeMinutos(): int
    {
        $min = (int) env('SESSAO_INATIVIDADE_MINUTOS', 0);

        return $min >= 1 ? $min : 60;
    }

    /**
     * Chamar ANTES do session_start().
     *
     * O PHP apaga o arquivo de sessão parado há mais de session.gc_maxlifetime
     * (padrão 1440s = 24 min, e no XAMPP é isso mesmo). Esse relógio é
     * independente do nosso: com o limite do sistema em 30 min e o do PHP em
     * 24, quem parasse 25 minutos perdia a sessão "em silêncio" — antes de a
     * nossa regra poder dar o motivo. Alinhamos o do PHP ao nosso, com folga.
     */
    public static function configurarArmazenamento(): void
    {
        ini_set('session.gc_maxlifetime', (string) (self::inatividadeMinutos() * 60 + 600));
    }

    /* ── Regra 1: inatividade (todos os perfis) ─────────────────────────── */

    public static function inativa(): bool
    {
        $ultimo = (int) ($_SESSION['ultimo_acesso'] ?? 0);

        return $ultimo > 0 && (time() - $ultimo) > self::inatividadeMinutos() * 60;
    }

    public static function registrarAtividade(): void
    {
        $_SESSION['ultimo_acesso'] = time();
    }

    /* ── Regra 2: conta (alterada ou desativada) ────────────────────────── */

    /**
     * Compara a sessão com o banco. Devolve o motivo ou null se está tudo certo.
     * Desativada/apagada ≠ alterada: são mensagens diferentes.
     *
     * @param array<string,mixed>|null|false $atual linha atual de usuarios
     */
    public static function motivoDaConta($atual): ?string
    {
        if (!$atual || (int) ($atual['ativo'] ?? 0) !== 1) {
            return self::CONTA_DESATIVADA;
        }

        if (($atual['atualizado_em'] ?? null) !== ($_SESSION['user_atualizado_em'] ?? null)) {
            return self::CONTA_ALTERADA;
        }

        return null;
    }

    /* ── Regra 3: horário de acesso (SÓ o ATENDENTE) ────────────────────── */

    public static function perfilTemJanela(?string $tipo): bool
    {
        return $tipo === 'ATENDENTE';
    }

    public static function dentroDaJanela(?\DateTimeInterface $agora = null): bool
    {
        $agora = $agora ?? new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo'));
        $dia   = (int) $agora->format('N');
        $hora  = $agora->format('H:i');

        return ($dia >= 1 && $dia <= 5 && $hora >= '07:00' && $hora < '18:00')
            || ($dia === 6 && $hora >= '07:00' && $hora < '13:00');
    }

    /** ADMIN e TÉCNICO: sempre false, em qualquer dia e hora. */
    public static function foraDoHorario(?string $tipo, ?\DateTimeInterface $agora = null): bool
    {
        return self::perfilTemJanela($tipo) && !self::dentroDaJanela($agora);
    }

    /* ── Encerramento forçado e mensagens ───────────────────────────────── */

    /**
     * Encerra POR REGRA do sistema: guarda o motivo na sessão e passa pelo
     * ./logout (que fecha o caixa e derruba a sessão). O motivo fica na sessão,
     * não na URL — quem digita `/logout?motivo=...` não ganha nada.
     */
    public static function encerrar(string $motivo): void
    {
        $_SESSION[self::CHAVE_MOTIVO] = $motivo;
        header('Location: ./logout');
        exit;
    }

    /** Motivo pendente de um encerramento forçado, ou null se o logout é manual. */
    public static function motivoPendente(): ?string
    {
        $m = $_SESSION[self::CHAVE_MOTIVO] ?? null;

        return is_string($m) && self::mensagem($m) !== null ? $m : null;
    }

    public static function mensagem(?string $motivo): ?string
    {
        switch ($motivo) {
            case self::INATIVIDADE:
                return 'Sua sessão expirou por inatividade (' . self::inatividadeMinutos() . ' minutos sem uso). Entre de novo para continuar.';
            case self::HORARIO:
                return 'Seu horário de acesso foi encerrado. ' . self::MENSAGEM_JANELA;
            case self::CONTA_ALTERADA:
                return 'Seu acesso foi alterado por um administrador. Entre de novo para continuar.';
            case self::CONTA_DESATIVADA:
                return 'Seu acesso foi desativado. Fale com o administrador da oficina.';
            case self::SESSAO_INVALIDA:
                return 'Sua sessão não é mais válida. Entre de novo para continuar.';
            default:
                return null;
        }
    }
}
