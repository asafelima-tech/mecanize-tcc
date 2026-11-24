<?php

/**
 * Quem pode o quê — uma tabela só. Cada rota de config/rotas.php declara um nível; o front
 * controller confere ANTES de chamar o controller. Rota sem nível é negada.
 *
 *   PUBLICO      sem login (entrar, recuperar senha, link de acompanhamento)
 *   LOGADO       qualquer pessoa logada, inclusive a plataforma (sair, o próprio perfil)
 *   EQUIPE       ADMIN, ATENDENTE e TECNICO da oficina — só o que é de todos: início, perfil, avisos, anexos
 *                (anexos conferem, no controller, se o técnico está na OS)
 *   ATENDIMENTO  ADMIN e ATENDENTE — a operação do escritório: OS, agenda, clientes, veículos, catálogo
 *   BALCAO       ADMIN e ATENDENTE — dinheiro: caixa e recebimento pela OS
 *   TECNICO      só o técnico — a área de execução dele (as próprias OS, materiais e solicitações)
 *   ADMIN        só o administrador da oficina (usuários, financeiro, estoque, relatórios, validação)
 *   PLATAFORMA   só a equipe do Mecanize (cadastro de oficinas e licenças); não vê dados de oficina
 *
 * O técnico (24/11/2026) não usa mais as telas do escritório: ele vê só as OS em que está, e cada
 * consulta da área dele é filtrada pelo id do técnico logado (ver ServicoTecnico).
 */
final class Permissao
{
    public const PUBLICO     = 'PUBLICO';
    public const LOGADO      = 'LOGADO';
    public const EQUIPE      = 'EQUIPE';
    public const ATENDIMENTO = 'ATENDIMENTO';
    public const BALCAO      = 'BALCAO';
    public const TECNICO     = 'TECNICO';
    public const ADMIN       = 'ADMIN';
    public const PLATAFORMA  = 'PLATAFORMA';

    public const NIVEIS = [self::PUBLICO, self::LOGADO, self::EQUIPE, self::ATENDIMENTO, self::BALCAO, self::TECNICO, self::ADMIN, self::PLATAFORMA];

    public const PERFIS_OFICINA = ['ADMIN', 'ATENDENTE', 'TECNICO'];

    public static function permite(?string $perfil, ?string $nivel): bool
    {
        switch ($nivel) {
            case self::PUBLICO:
                return true;
            case self::LOGADO:
                return $perfil !== null;
            case self::EQUIPE:
                return in_array($perfil, self::PERFIS_OFICINA, true);
            case self::ATENDIMENTO:
            case self::BALCAO:
                return in_array($perfil, ['ADMIN', 'ATENDENTE'], true);
            case self::TECNICO:
                return $perfil === 'TECNICO';
            case self::ADMIN:
                return $perfil === 'ADMIN';
            case self::PLATAFORMA:
                return $perfil === 'PLATAFORMA';
            default:
                return false; // nível ausente ou desconhecido: nega
        }
    }

    /** Tela inicial de cada perfil depois de entrar. */
    public static function inicio(?string $perfil): string
    {
        return match ($perfil) {
            'PLATAFORMA' => 'plataforma',
            'TECNICO'    => 'servicos',
            default      => 'inicio',
        };
    }
}
