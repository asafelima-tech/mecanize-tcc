<?php

/**
 * Contas de acesso — parte de autenticação (login, bloqueio por tentativas, recuperação de senha).
 *
 * Os métodos *Global aqui são legítimos: no login ainda não se sabe a oficina (o e-mail é único na
 * plataforma inteira e é ele que identifica a oficina). Depois do login, tudo passa pelo filtro.
 */
final class Usuario extends Modelo
{
    public const MAX_TENTATIVAS   = 5;
    public const BLOQUEIO_MINUTOS = 15;
    public const RESET_MINUTOS    = 30;
    /** Intervalo mínimo entre dois e-mails de recuperação para a mesma conta. */
    public const RESET_INTERVALO_MINUTOS = 5;

    /** Conta + situação da oficina, para o login e para revalidar a sessão a cada requisição. */
    public function paraSessao(int $id): ?array
    {
        return $this->linhaGlobal(self::SQL_CONTA . ' WHERE u.id = :id', ['id' => $id]);
    }

    public function porEmail(string $email): ?array
    {
        return $this->linhaGlobal(self::SQL_CONTA . ' WHERE u.email = :email', ['email' => mb_strtolower(trim($email))]);
    }

    private const SQL_CONTA = "SELECT u.id, u.oficina_id, u.nome, u.email, u.telefone, u.senha_hash, u.perfil, u.ativo,
                                      u.tentativas_login, u.bloqueado_ate, u.atualizado_em, u.reset_expira_em,
                                      o.nome AS oficina_nome, o.status AS oficina_status, o.licenca_valida_ate, o.plano
                               FROM usuarios u LEFT JOIN oficinas o ON o.id = u.oficina_id";

    public function estaBloqueado(array $conta): bool
    {
        return !empty($conta['bloqueado_ate']) && strtotime($conta['bloqueado_ate']) > time();
    }

    public function minutosDeBloqueio(array $conta): int
    {
        return $this->estaBloqueado($conta) ? (int) ceil((strtotime($conta['bloqueado_ate']) - time()) / 60) : 0;
    }

    /** Não mexe em atualizado_em: tentativa de terceiro não pode derrubar a sessão de ninguém. */
    public function registrarTentativaFalha(int $id): void
    {
        $this->executarGlobal(
            'UPDATE usuarios SET tentativas_login = tentativas_login + 1,
                    bloqueado_ate = IF(tentativas_login >= :max, NOW() + INTERVAL ' . self::BLOQUEIO_MINUTOS . ' MINUTE, bloqueado_ate),
                    atualizado_em = atualizado_em
             WHERE id = :id',
            ['max' => self::MAX_TENTATIVAS, 'id' => $id]
        );
    }

    public function zerarTentativas(int $id): void
    {
        $this->executarGlobal('UPDATE usuarios SET tentativas_login = 0, bloqueado_ate = NULL, atualizado_em = atualizado_em WHERE id = :id', ['id' => $id]);
    }

    /**
     * Gera o link de recuperação. Só o HASH do token vai para o banco: quem ler o banco não consegue
     * usar o link. Devolve o token em texto (para o e-mail) ou null se um pedido recente ainda vale.
     */
    public function criarTokenRecuperacao(array $conta): ?string
    {
        $recente = !empty($conta['reset_expira_em'])
            && strtotime($conta['reset_expira_em']) - (self::RESET_MINUTOS - self::RESET_INTERVALO_MINUTOS) * 60 > time();
        if ($recente) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $this->executarGlobal(
            'UPDATE usuarios SET reset_token_hash = :hash, reset_expira_em = NOW() + INTERVAL ' . self::RESET_MINUTOS . ' MINUTE,
                    atualizado_em = atualizado_em
             WHERE id = :id',
            ['hash' => hash('sha256', $token), 'id' => (int) $conta['id']]
        );

        return $token;
    }

    public function porTokenRecuperacao(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        return $this->linhaGlobal(
            self::SQL_CONTA . ' WHERE u.reset_token_hash = :hash AND u.reset_expira_em > NOW() AND u.ativo = 1',
            ['hash' => hash('sha256', $token)]
        );
    }

    /** Troca a senha e invalida o link. Muda atualizado_em: sessões abertas dessa conta caem. */
    public function redefinirSenha(int $id, string $novaSenha): void
    {
        $this->executarGlobal(
            'UPDATE usuarios SET senha_hash = :senha, reset_token_hash = NULL, reset_expira_em = NULL,
                    tentativas_login = 0, bloqueado_ate = NULL, atualizado_em = NOW()
             WHERE id = :id',
            ['senha' => password_hash($novaSenha, PASSWORD_DEFAULT), 'id' => $id]
        );
    }

    /* ── Gestão das contas DA OFICINA (tela de usuários, só ADMIN) ───────── */

    protected array $mensagensUnicas = [
        'uq_usuarios_email' => 'Este e-mail já está em uso no Mecanize. Cada pessoa entra com um e-mail próprio.',
    ];

    public const PERFIS = ['ADMIN' => 'Administrador', 'ATENDENTE' => 'Atendente', 'TECNICO' => 'Técnico'];
    public const COMISSOES = [
        'NENHUMA'             => 'Sem comissão',
        'PERCENTUAL_SERVICOS' => '% sobre serviços',
        'PERCENTUAL_PECAS'    => '% sobre peças',
        'PERCENTUAL_TOTAL'    => '% sobre o total da OS',
    ];

    private const COLUNAS = 'id, nome, email, telefone, perfil, ativo, comissao_tipo, comissao_percentual, bloqueado_ate, criado_em, atualizado_em';

    public function listarDaOficina(array $f, Paginacao $p): array
    {
        $where  = 'oficina_id = :oficina';
        $params = [];
        if (($f['busca'] ?? '') !== '') {
            $where .= ' AND (nome LIKE :b1 OR email LIKE :b2)';
            $params = ['b1' => '%' . $f['busca'] . '%', 'b2' => '%' . $f['busca'] . '%'];
        }
        if (isset(self::PERFIS[$f['perfil'] ?? ''])) {
            $where .= ' AND perfil = :perfil';
            $params['perfil'] = $f['perfil'];
        }
        if (($f['status'] ?? 'ativos') !== 'todos') {
            $where .= ' AND ativo = :ativo';
            $params['ativo'] = ($f['status'] ?? 'ativos') === 'inativos' ? 0 : 1;
        }
        $p->total = (int) $this->valor("SELECT COUNT(*) FROM usuarios WHERE {$where}", $params);

        return $this->lista('SELECT ' . self::COLUNAS . " FROM usuarios WHERE {$where} ORDER BY ativo DESC, nome" . $p->sql(), $params);
    }

    public function buscarDaOficina(int $id): ?array
    {
        return $this->linha('SELECT ' . self::COLUNAS . ' FROM usuarios WHERE id = :id AND oficina_id = :oficina', ['id' => $id]);
    }

    public function tecnicos(): array
    {
        return $this->lista("SELECT id, nome, comissao_tipo, comissao_percentual FROM usuarios
                             WHERE oficina_id = :oficina AND ativo = 1 AND perfil IN ('TECNICO','ADMIN') ORDER BY nome");
    }

    /** Ativos e limite do plano: [ativos, limite]. */
    public function vagas(): array
    {
        $l = $this->linha('SELECT (SELECT COUNT(*) FROM usuarios WHERE oficina_id = :oficina AND ativo = 1) AS ativos,
                                  (SELECT limite_usuarios FROM oficinas WHERE id = :oficina) AS limite');

        return [(int) $l['ativos'], (int) $l['limite']];
    }

    public function salvarDaOficina(array $d, ?int $id = null): int
    {
        $perfil = isset(self::PERFIS[$d['perfil'] ?? '']) ? $d['perfil'] : 'ATENDENTE';
        $tipoComissao = isset(self::COMISSOES[$d['comissao_tipo'] ?? '']) ? $d['comissao_tipo'] : 'NENHUMA';
        $dados = [
            'nome'                => trim($d['nome']),
            'email'               => mb_strtolower(trim($d['email'])),
            'telefone'            => so_digitos($d['telefone'] ?? '') ?: null,
            'perfil'              => $perfil,
            'comissao_tipo'       => $tipoComissao,
            'comissao_percentual' => $tipoComissao === 'NENHUMA' ? 0 : min(100, max(0, Produto::valorDecimal((string) ($d['comissao_percentual'] ?? '0')))),
        ];
        if (($d['senha'] ?? '') !== '') {
            $dados['senha_hash'] = password_hash($d['senha'], PASSWORD_DEFAULT);
        }

        return Transacao::executar($this->db, function () use ($dados, $id) {
            if ($id === null) {
                [$ativos, $limite] = $this->vagas();
                if ($ativos >= $limite) {
                    throw new DomainException("O plano da oficina permite {$limite} usuário(s) ativo(s). Desative alguém ou fale com o suporte para ampliar.");
                }
                return $this->inserir('usuarios', $dados);
            }

            $atual = $this->buscarDaOficina($id) ?? throw new ErroHttp('registro-inexistente');
            if ($atual['perfil'] === 'ADMIN' && $dados['perfil'] !== 'ADMIN') {
                $this->exigirOutroAdmin($id, 'mudar o perfil');
            }
            $this->atualizar('usuarios', $id, $dados);

            return $id;
        });
    }

    public function definirAtivo(int $id, bool $ativo): void
    {
        Transacao::executar($this->db, function () use ($id, $ativo) {
            $u = $this->buscarDaOficina($id) ?? throw new ErroHttp('registro-inexistente');
            if ($ativo && !(int) $u['ativo']) {
                [$ativos, $limite] = $this->vagas();
                if ($ativos >= $limite) {
                    throw new DomainException("O plano da oficina permite {$limite} usuário(s) ativo(s).");
                }
            }
            if (!$ativo && $u['perfil'] === 'ADMIN') {
                $this->exigirOutroAdmin($id, 'desativar');
            }
            $this->atualizar('usuarios', $id, ['ativo' => $ativo ? 1 : 0]);
        });
    }

    public function remover(int $id): bool
    {
        $u = $this->buscarDaOficina($id) ?? throw new ErroHttp('registro-inexistente');
        if ($u['perfil'] === 'ADMIN') {
            $this->exigirOutroAdmin($id, 'excluir');
        }

        return $this->apagar('usuarios', $id);
    }

    /** A oficina nunca fica sem administrador ativo — e ninguém tira o próprio acesso. */
    private function exigirOutroAdmin(int $id, string $acao): void
    {
        if ($id === Contexto::usuarioId()) {
            throw new DomainException("Você não pode {$acao} a sua própria conta de administrador.");
        }
        $outros = (int) $this->valor("SELECT COUNT(*) FROM usuarios WHERE oficina_id = :oficina AND perfil = 'ADMIN' AND ativo = 1 AND id <> :id", ['id' => $id]);
        if ($outros === 0) {
            throw new DomainException('A oficina precisa de pelo menos um administrador ativo.');
        }
    }

    /** Troca da própria senha (tela Perfil): exige a senha atual. */
    public function trocarPropriaSenha(string $atual, string $nova): void
    {
        $conta = $this->paraSessao((int) Contexto::usuarioId());
        if (!$conta || !password_verify($atual, $conta['senha_hash'])) {
            throw new DomainException('A senha atual não confere.');
        }
        $this->redefinirSenha((int) $conta['id'], $nova);
    }

    public function atualizarProprioPerfil(string $nome, string $telefone): void
    {
        $this->atualizar('usuarios', (int) Contexto::usuarioId(), ['nome' => trim($nome), 'telefone' => so_digitos($telefone) ?: null]);
    }
}
