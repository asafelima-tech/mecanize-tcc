<?php

/**
 * Sessão de login: início, regras de validade e encerramento — cada encerramento forçado com o SEU
 * motivo (a v1 aprendeu isso do jeito difícil: um único "expirou" para causas diferentes).
 *
 * Regras que derrubam a sessão (conferidas a cada requisição pelo front controller):
 *   inatividade       parado além do limite (SESSAO_INATIVIDADE_MINUTOS no .env; padrão 60)
 *   conta_alterada    um administrador mudou a conta (senha, perfil...) — `atualizado_em` mudou
 *   conta_desativada  conta desativada ou apagada
 *   oficina_cancelada a assinatura da oficina foi cancelada
 *
 * Saiu da v1: a janela de horário fixa do atendente. Num sistema com várias oficinas um horário
 * escrito no código não serve a nenhuma delas; se voltar a ser preciso, vira configuração da oficina.
 */
final class Sessao
{
    public const INATIVIDADE       = 'inatividade';
    public const CONTA_ALTERADA    = 'conta_alterada';
    public const CONTA_DESATIVADA  = 'conta_desativada';
    public const OFICINA_CANCELADA = 'oficina_cancelada';

    /** Nome próprio do cookie: a v2 roda ao lado da v1 no mesmo endereço e não pode herdar a sessão dela. */
    public const NOME_COOKIE = 'MECANIZE_V2';

    public static function iniciar(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_name(self::NOME_COOKIE);
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
        // O relógio de limpeza do PHP acompanha o nosso (senão a sessão some antes da regra poder dar o motivo).
        ini_set('session.gc_maxlifetime', (string) (self::inatividadeMinutos() * 60 + 600));
        session_start();
    }

    public static function inatividadeMinutos(): int
    {
        $min = (int) env('SESSAO_INATIVIDADE_MINUTOS', 0);

        return $min >= 1 ? $min : 60;
    }

    /** Grava na sessão só o necessário (nunca o hash da senha). */
    public static function entrar(array $usuario): void
    {
        session_regenerate_id(true); // evita fixação de sessão

        $_SESSION = [
            'usuario_id'    => (int) $usuario['id'],
            'atualizado_em' => $usuario['atualizado_em'],
            'ultimo_acesso' => time(),
        ];
    }

    /** Depois que a PRÓPRIA conta muda (perfil, senha), atualiza o carimbo para a sessão não cair. */
    public static function renovarCarimbo(): void
    {
        $conta = (new Usuario())->paraSessao((int) self::usuarioId());
        $_SESSION['atualizado_em'] = $conta['atualizado_em'] ?? null;
    }

    public static function usuarioId(): ?int
    {
        return isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
    }

    public static function inativa(): bool
    {
        $ultimo = (int) ($_SESSION['ultimo_acesso'] ?? 0);

        return $ultimo > 0 && (time() - $ultimo) > self::inatividadeMinutos() * 60;
    }

    public static function registrarAtividade(): void
    {
        $_SESSION['ultimo_acesso'] = time();
    }

    /**
     * Compara a sessão com a conta relida do banco. Devolve o motivo para derrubar, ou null.
     *
     * @param array|null $conta linha de usuarios + status da oficina (Usuario::paraSessao)
     */
    public static function motivoParaEncerrar(?array $conta): ?string
    {
        if ($conta === null || (int) $conta['ativo'] !== 1) {
            return self::CONTA_DESATIVADA;
        }
        if (($conta['oficina_status'] ?? null) === 'CANCELADA') {
            return self::OFICINA_CANCELADA;
        }
        if (($conta['atualizado_em'] ?? null) !== ($_SESSION['atualizado_em'] ?? null)) {
            return self::CONTA_ALTERADA;
        }

        return null;
    }

    public static function sair(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function mensagem(?string $motivo): ?string
    {
        switch ($motivo) {
            case self::INATIVIDADE:
                return 'Sua sessão expirou por inatividade (' . self::inatividadeMinutos() . ' minutos sem uso). Entre de novo para continuar.';
            case self::CONTA_ALTERADA:
                return 'Seu acesso foi alterado por um administrador. Entre de novo para continuar.';
            case self::CONTA_DESATIVADA:
                return 'Seu acesso foi desativado. Fale com o administrador da oficina.';
            case self::OFICINA_CANCELADA:
                return 'A assinatura desta oficina foi cancelada. Fale com o suporte do Mecanize.';
            default:
                return null;
        }
    }
}
