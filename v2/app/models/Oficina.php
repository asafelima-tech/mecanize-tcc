<?php

/**
 * A oficina: dados de cadastro (o ADMIN edita em "Configurações") e licença (só a PLATAFORMA mexe).
 *
 * A tabela `oficinas` não tem oficina_id — o próprio id é a oficina. Por isso o filtro aqui é
 * "WHERE id = :oficina", e as telas da plataforma usam os métodos *Global depois de conferir o perfil.
 */
final class Oficina extends Modelo
{
    /** Planos de referência e o limite de usuários sugerido. O limite gravado na oficina é o que vale. */
    public const PLANOS = ['BASICO' => ['Básico', 3], 'PROFISSIONAL' => ['Profissional', 10], 'PREMIUM' => ['Premium', 30]];

    public const SITUACOES = ['TESTE' => 'Teste', 'ATIVA' => 'Ativa', 'SUSPENSA' => 'Suspensa', 'CANCELADA' => 'Cancelada'];

    /** Campos que o ADMIN pode editar. */
    private const CADASTRO = ['nome', 'cnpj', 'email', 'telefone', 'endereco', 'cidade', 'uf', 'cep', 'horario'];

    protected array $mensagensUnicas = [
        'uq_oficinas_cnpj'   => 'Já existe uma oficina com este CNPJ.',
        'uq_usuarios_email'  => 'Este e-mail já é usado por outro usuário.',
    ];

    /* ── A oficina de quem está logado ───────────────────────────────── */

    public function atual(): ?array
    {
        return $this->linha('SELECT * FROM oficinas WHERE id = :oficina');
    }

    public function salvarConfiguracoes(array $d): void
    {
        $novos = self::limparCadastro($d);
        $atual = $this->atual();

        $mudou = [];
        foreach ($novos as $k => $v) {
            if ((string) ($atual[$k] ?? '') !== (string) ($v ?? '')) {
                $mudou[$k] = ['de' => $atual[$k], 'para' => $v];
            }
        }
        if (!$mudou) {
            return;
        }

        Transacao::executar($this->db, function () use ($novos, $mudou) {
            $sets = implode(', ', array_map(fn($c) => "{$c} = :{$c}", array_keys($novos)));
            $this->gravar("UPDATE oficinas SET {$sets} WHERE id = :oficina", $novos);
            (new Historico($this->db))->registrar([], 'CONFIGURACOES', null, null, 'Dados da oficina', $mudou);
        });
    }

    /** Troca o logotipo (só imagem). O arquivo antigo é apagado depois que o novo está gravado. */
    public function trocarLogo(array $arquivo, bool $exigirUpload = true): void
    {
        Contexto::exigirEscrita();
        $antigo = $this->atual()['logo_caminho'] ?? null;
        $meta = Armazenamento::guardar($arquivo, 'logo', $exigirUpload, ['image/jpeg', 'image/png', 'image/webp']);

        try {
            Transacao::executar($this->db, function () use ($meta) {
                $this->gravar('UPDATE oficinas SET logo_caminho = :c WHERE id = :oficina', ['c' => $meta['caminho']]);
                (new Historico($this->db))->registrar([], 'CONFIGURACOES', null, null, 'Logotipo alterado');
            });
        } catch (Throwable $e) {
            if ($f = Armazenamento::caminho($meta['caminho'])) {
                @unlink($f);
            }
            throw $e;
        }

        if ($antigo && ($f = Armazenamento::caminho($antigo))) {
            @unlink($f);
        }
    }

    public function removerLogo(): void
    {
        $antigo = $this->atual()['logo_caminho'] ?? null;
        if (!$antigo) {
            return;
        }
        $this->gravar('UPDATE oficinas SET logo_caminho = NULL WHERE id = :oficina');
        (new Historico($this->db))->registrar([], 'CONFIGURACOES', null, null, 'Logotipo removido');
        if ($f = Armazenamento::caminho($antigo)) {
            @unlink($f);
        }
    }

    /** Eventos da própria oficina (configurações e licença), mais recentes primeiro. */
    public function historico(int $limite = 30): array
    {
        $limite = max(1, min(200, $limite));

        return $this->lista(
            "SELECT h.*, u.nome AS autor FROM historico h LEFT JOIN usuarios u ON u.oficina_id = h.oficina_id AND u.id = h.autor_id
             WHERE h.oficina_id = :oficina AND h.acao IN ('CONFIGURACOES','LICENCA','OFICINA_CRIADA')
             ORDER BY h.criado_em DESC, h.id DESC LIMIT {$limite}"
        );
    }

    /* ── Plataforma (equipe do Mecanize) ─────────────────────────────── */

    private function exigirPlataforma(): void
    {
        if (Contexto::perfil() !== 'PLATAFORMA') {
            throw new ErroHttp('sem-permissao');
        }
    }

    /** Todas as oficinas — só cadastro e licença, nada de dentro delas. */
    public function listarTodas(): array
    {
        $this->exigirPlataforma();

        return $this->listaGlobal(
            'SELECT o.id, o.nome, o.cnpj, o.email, o.plano, o.status, o.licenca_valida_ate, o.limite_usuarios, o.criado_em,
                    (SELECT COUNT(*) FROM usuarios u WHERE u.oficina_id = o.id AND u.ativo = 1) AS usuarios_ativos
             FROM oficinas o ORDER BY o.nome'
        );
    }

    public function buscarGlobal(int $id): ?array
    {
        $this->exigirPlataforma();

        return $this->linhaGlobal(
            'SELECT o.*, (SELECT COUNT(*) FROM usuarios u WHERE u.oficina_id = o.id AND u.ativo = 1) AS usuarios_ativos
             FROM oficinas o WHERE o.id = :id', ['id' => $id]);
    }

    /** Administradores da oficina (contato do suporte). */
    public function administradores(int $id): array
    {
        $this->exigirPlataforma();

        return $this->listaGlobal("SELECT id, nome, email, telefone, ativo FROM usuarios WHERE oficina_id = :id AND perfil = 'ADMIN' ORDER BY ativo DESC, nome", ['id' => $id]);
    }

    public function historicoLicenca(int $id): array
    {
        $this->exigirPlataforma();

        return $this->listaGlobal(
            "SELECT h.*, u.nome AS autor FROM historico h LEFT JOIN usuarios u ON u.id = h.autor_id
             WHERE h.oficina_id = :id AND h.acao IN ('LICENCA','OFICINA_CRIADA') ORDER BY h.criado_em DESC, h.id DESC", ['id' => $id]);
    }

    /**
     * Cadastra uma oficina nova com o primeiro administrador (senha provisória, que ele troca no perfil).
     * Devolve o id da oficina.
     */
    public function criar(array $d): int
    {
        $this->exigirPlataforma();
        $cadastro = self::limparCadastro($d);
        $licenca = $this->limparLicenca($d);

        $admNome = trim((string) ($d['admin_nome'] ?? ''));
        $admEmail = mb_strtolower(trim((string) ($d['admin_email'] ?? '')));
        $admSenha = (string) ($d['admin_senha'] ?? '');
        if ($admNome === '' || !filter_var($admEmail, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Informe o nome e um e-mail válido do administrador da oficina.');
        }
        if (mb_strlen($admSenha) < 8) {
            throw new DomainException('A senha provisória do administrador precisa de pelo menos 8 caracteres.');
        }

        return Transacao::executar($this->db, function () use ($cadastro, $licenca, $admNome, $admEmail, $admSenha) {
            $dados = $cadastro + $licenca;
            $cols = array_keys($dados);
            $this->executarGlobal('INSERT INTO oficinas (' . implode(', ', $cols) . ') VALUES (:' . implode(', :', $cols) . ')', $dados);
            $id = (int) $this->db->lastInsertId();

            $this->executarGlobal("INSERT INTO usuarios (oficina_id, nome, email, senha_hash, perfil) VALUES (:o, :n, :e, :s, 'ADMIN')",
                ['o' => $id, 'n' => mb_substr($admNome, 0, 120), 'e' => $admEmail, 's' => password_hash($admSenha, PASSWORD_DEFAULT)]);

            $this->registrarGlobal($id, 'OFICINA_CRIADA', null, $licenca['status'], null, $licenca + ['administrador' => $admEmail]);

            return $id;
        });
    }

    /** Plano, limite, situação e validade. Toda mudança fica no histórico da oficina com o antes e o depois. */
    public function alterarLicenca(int $id, array $d): array
    {
        $this->exigirPlataforma();
        $novos = $this->limparLicenca($d);
        $motivo = trim((string) ($d['motivo'] ?? ''));

        return Transacao::executar($this->db, function () use ($id, $novos, $motivo) {
            $atual = $this->linhaGlobal('SELECT * FROM oficinas WHERE id = :id FOR UPDATE', ['id' => $id]) ?? throw new ErroHttp('registro-inexistente', ['voltar' => './plataforma']);

            $mudou = [];
            foreach ($novos as $k => $v) {
                if ((string) $atual[$k] !== (string) $v) {
                    $mudou[$k] = ['de' => $atual[$k], 'para' => $v];
                }
            }
            if (!$mudou) {
                return [];
            }
            if ($novos['status'] === 'CANCELADA' && $motivo === '') {
                throw new DomainException('Informe o motivo do cancelamento.');
            }

            $sets = implode(', ', array_map(fn($c) => "{$c} = :{$c}", array_keys($novos)));
            $this->executarGlobal("UPDATE oficinas SET {$sets} WHERE id = :id", $novos + ['id' => $id]);
            $this->registrarGlobal($id, 'LICENCA', $atual['status'], $novos['status'], $motivo ?: null, $mudou);

            return $mudou;
        });
    }

    private function registrarGlobal(int $oficinaId, string $acao, ?string $de, ?string $para, ?string $motivo, array $dados): void
    {
        // Autor NULL: a equipe da plataforma não pertence à oficina (a chave estrangeira do autor é da oficina).
        $this->executarGlobal(
            'INSERT INTO historico (oficina_id, acao, estado_anterior, estado_novo, motivo, dados, autor_id)
             VALUES (:o, :a, :de, :para, :m, :d, NULL)',
            ['o' => $oficinaId, 'a' => $acao, 'de' => $de, 'para' => $para,
             'm' => $motivo !== null ? mb_substr($motivo, 0, 255) : null,
             'd' => json_encode($dados + ['por' => Contexto::usuario()['nome'] ?? null], JSON_UNESCAPED_UNICODE)]
        );
    }

    /* ── Validação ───────────────────────────────────────────────────── */

    private static function limparCadastro(array $d): array
    {
        $nome = trim((string) ($d['nome'] ?? ''));
        if ($nome === '') {
            throw new DomainException('Informe o nome da oficina.');
        }
        $uf = mb_strtoupper(trim((string) ($d['uf'] ?? '')));
        if ($uf !== '' && !preg_match('/^[A-Z]{2}$/', $uf)) {
            throw new DomainException('UF inválida.');
        }
        $cep = so_digitos($d['cep'] ?? '');
        if ($cep !== '' && strlen($cep) !== 8) {
            throw new DomainException('CEP inválido.');
        }
        $cnpj = so_digitos($d['cnpj'] ?? '');
        if ($cnpj !== '' && strlen($cnpj) !== 14) {
            throw new DomainException('CNPJ inválido.');
        }
        $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('E-mail da oficina inválido.');
        }
        $txt = fn($k, $max) => ($v = trim((string) ($d[$k] ?? ''))) === '' ? null : mb_substr($v, 0, $max);

        $r = [
            'nome'     => mb_substr($nome, 0, 150),
            'cnpj'     => $cnpj ?: null,
            'email'    => $email ?: null,
            'telefone' => so_digitos($d['telefone'] ?? '') ?: null,
            'endereco' => $txt('endereco', 255),
            'cidade'   => $txt('cidade', 100),
            'uf'       => $uf ?: null,
            'cep'      => $cep ?: null,
            'horario'  => $txt('horario', 150),
        ];

        return array_intersect_key($r, array_flip(self::CADASTRO));
    }

    private function limparLicenca(array $d): array
    {
        $plano = (string) ($d['plano'] ?? '');
        $status = (string) ($d['status'] ?? '');
        $limite = (int) ($d['limite_usuarios'] ?? 0);
        $validade = (string) ($d['licenca_valida_ate'] ?? '');

        if (!isset(self::PLANOS[$plano])) {
            throw new DomainException('Escolha o plano.');
        }
        if (!isset(self::SITUACOES[$status])) {
            throw new DomainException('Escolha a situação da licença.');
        }
        if ($limite < 1 || $limite > 500) {
            throw new DomainException('O limite de usuários deve ficar entre 1 e 500.');
        }
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $validade);
        if (!$dt || $dt->format('Y-m-d') !== $validade) {
            throw new DomainException('Informe a data de validade da licença.');
        }

        return ['plano' => $plano, 'limite_usuarios' => $limite, 'status' => $status, 'licenca_valida_ate' => $validade];
    }
}
