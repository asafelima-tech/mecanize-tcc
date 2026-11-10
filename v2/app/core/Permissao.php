<?php

/**
 * Quem pode o quê — uma tabela só. Cada rota de config/rotas.php declara um nível; o front
 * controller confere ANTES de chamar o controller. Rota sem nível é negada.
 *
 *   PUBLICO     sem login (entrar, recuperar senha, link de acompanhamento)
 *   LOGADO      qualquer pessoa logada, inclusive a plataforma (sair, o próprio perfil)
 *   EQUIPE      ADMIN, ATENDENTE e TECNICO da oficina — a operação do dia a dia
 *   ADMIN       só o administrador da oficina (usuários, financeiro, relatórios, configurações)
 *   PLATAFORMA  só a equipe do Mecanize (cadastro de oficinas e licenças); não vê dados de oficina
 *
 * ATENDENTE e TECNICO têm as mesmas permissões (decisão da v1, mantida): muda a tela inicial, não o acesso.
 */
final class Permissao
{
    public const PUBLICO    = 'PUBLICO';
    public const LOGADO     = 'LOGADO';
    public const EQUIPE     = 'EQUIPE';
    public const ADMIN      = 'ADMIN';
    public const PLATAFORMA = 'PLATAFORMA';

    public const NIVEIS = [self::PUBLICO, self::LOGADO, self::EQUIPE, self::ADMIN, self::PLATAFORMA];

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
        return $perfil === 'PLATAFORMA' ? 'plataforma' : 'inicio';
    }
}
