<?php

/**
 * Matriz central de autorização — a única fonte de verdade de "quem pode o quê".
 *
 * Por que existe: a checagem de papel estava espalhada, método a método, nos
 * controllers (`requireRole(['ADMIN','ATENDENTE'])` aqui, nada ali). A auditoria
 * de 08/10/2026 achou ~25 rotas sem checagem nenhuma — lista de OS, PDF, clientes,
 * `criarModelo`, `lerPlaca` — e dois perfis (técnico e atendente) com conjuntos de
 * permissão diferentes por acidente, não por decisão.
 *
 * Como funciona agora: cada rota em config/routes.php declara um NÍVEL de
 * acesso. public/index.php exige esse nível ANTES de despachar para o controller,
 * então esquecer a checagem dentro do método deixou de abrir uma brecha. Rota sem
 * nível declarado é tratada como ADMIN (negar por padrão) e vira falha no teste.
 *
 * Níveis:
 *   PUBLICO  sem login (login, recuperação de senha, páginas legais, rastreio)
 *   LOGADO   qualquer usuário autenticado (início, o próprio perfil)
 *   EQUIPE   ADMIN, ATENDENTE e TECNICO — a operação da oficina
 *   ADMIN    só o administrador (usuários, produtos, financeiro, relatórios…)
 *
 * ATENDENTE e TECNICO têm EXATAMENTE as mesmas permissões: nenhum nível os
 * distingue. A diferença entre os dois perfis é de tela (o que aparece primeiro),
 * nunca de capacidade — Permissao::mesmaCapacidade() e o PermissaoTest garantem.
 */
final class Permissao
{
    public const PUBLICO = 'PUBLICO';
    public const LOGADO  = 'LOGADO';
    public const EQUIPE  = 'EQUIPE';
    public const ADMIN   = 'ADMIN';

    public const NIVEIS = [self::PUBLICO, self::LOGADO, self::EQUIPE, self::ADMIN];

    /** Papéis aceitos por nível, para usar em Controller::requireRole(). */
    public const PAPEIS_EQUIPE = ['ADMIN', 'ATENDENTE', 'TECNICO'];
    public const PAPEIS_ADMIN  = ['ADMIN'];

    /** Todos os papéis que existem hoje. */
    public const PAPEIS = ['ADMIN', 'ATENDENTE', 'TECNICO'];

    public static function permite(?string $papel, string $nivel): bool
    {
        switch ($nivel) {
            case self::PUBLICO:
                return true;
            case self::LOGADO:
            case self::EQUIPE:
                return in_array($papel, self::PAPEIS_EQUIPE, true);
            case self::ADMIN:
                return $papel === 'ADMIN';
            default:
                // Nível desconhecido ou ausente: nega.
                return false;
        }
    }

    /** Nível declarado para a rota, ou null se ela não declarou (→ negar). */
    public static function nivelDaRota(array $rotas, string $rota): ?string
    {
        $nivel = $rotas[$rota]['acesso'] ?? null;

        return in_array($nivel, self::NIVEIS, true) ? $nivel : null;
    }

    public static function podeAcessarRota(?string $papel, array $rotas, string $rota): bool
    {
        $nivel = self::nivelDaRota($rotas, $rota);

        // Sem nível declarado: só o ADMIN passa (negar por padrão).
        return $nivel === null ? $papel === 'ADMIN' : self::permite($papel, $nivel);
    }

    /**
     * ATENDENTE e TECNICO enxergam as mesmas rotas? Devolve a lista das que
     * diferem (vazia = paridade total). Usado pelo teste e pelo diagnóstico.
     *
     * @return string[]
     */
    public static function rotasSemParidade(array $rotas): array
    {
        $diferentes = [];

        foreach (array_keys($rotas) as $rota) {
            if (self::podeAcessarRota('ATENDENTE', $rotas, $rota) !== self::podeAcessarRota('TECNICO', $rotas, $rota)) {
                $diferentes[] = $rota;
            }
        }

        return $diferentes;
    }
}
