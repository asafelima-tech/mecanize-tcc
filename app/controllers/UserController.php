<?php

class UserController extends Controller
{
    const POR_PAGINA = 10;

    public function login()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userModel = $this->model('User');

            if ($userModel->login($_POST)) {
                $userData = $userModel->getUserByEmail($_POST['email']);

                if (!$userData) {
                    $this->view('Usuarios/login', [
                        'error' => 'Usuário não encontrado.'
                    ]);
                    return;
                }

                if ($userData['ativo'] != 1) {
                    $this->view('Usuarios/login', [
                        'error' => 'Usuário bloqueado.'
                    ]);
                    return;
                }

                if (($userData['tipo'] ?? '') === 'ATENDENTE') {
                    date_default_timezone_set('America/Sao_Paulo');

                    $diaSemana = (int) date('N');
                    $horaAtual = date('H:i');

                    $podeEntrar = false;

                    if ($diaSemana >= 1 && $diaSemana <= 5 && $horaAtual >= '07:00' && $horaAtual < '18:00') {
                        $podeEntrar = true;
                    }

                    if ($diaSemana === 6 && $horaAtual >= '07:00' && $horaAtual < '13:00') {
                        $podeEntrar = true;
                    }

                    if (!$podeEntrar) {
                        $this->view('Usuarios/login', [
                            'error' => 'Acesso permitido para atendentes de segunda a sexta das 07:00 às 18:00 e aos sábados das 07:00 às 13:00.'
                        ]);
                        return;
                    }
                }

                $_SESSION['logged'] = true;

                foreach ($userData as $key => $value) {
                    $_SESSION["user_$key"] = $value;
                }

                header('Location: ./home');
                exit;
            }

            $this->view('Usuarios/login', [
                'error' => 'Login inválido.'
            ]);
            return;
        }

        // Aviso exibido quando o atendente é desconectado por fim do expediente.
        $aviso = !empty($_GET['expirado'])
            ? 'Seu horário de acesso foi encerrado. Atendentes têm acesso de segunda a sexta das 07:00 às 18:00 e aos sábados das 07:00 às 13:00.'
            : null;

        $this->view('Usuarios/login', ['aviso' => $aviso]);
    }

    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $usuarioId  = $_SESSION['user_id_usuario'] ?? null;
        $expirado   = !empty($_GET['expirado']);
        $confirmado = !empty($_GET['confirmado']);

        // Logout voluntário com caixa aberto e sem confirmação: manda para a
        // tela de fechamento em vez de sair direto. O logout forçado por fim
        // de expediente (expirado=1) NUNCA passa por essa trava — o usuário
        // já não está mais ali para confirmar nada.
        if ($usuarioId && !$expirado && !$confirmado) {
            try {
                $caixa = $this->model('Caixa');
                if ($caixa->getCaixaAberto()) {
                    header('Location: ./fecharCaixa?logout=1');
                    exit;
                }
            } catch (\Throwable $e) {
                // Falha na checagem não pode impedir o logout.
            }
        }

        // Se houver caixa aberto, ele é fechado automaticamente ao sair,
        // registrando o dinheiro apurado na gaveta (abertura + entradas em
        // dinheiro − saídas), calculado a partir das movimentações da sessão.
        // Uma eventual falha aqui não pode impedir o logout.
        if ($usuarioId) {
            try {
                $caixa  = $this->model('Caixa');
                $aberto = $caixa->getCaixaAberto();

                if ($aberto) {
                    $resumo = $this->model('MovimentacaoCaixa')->resumoSessao($aberto['id_caixa']);

                    $caixa->fechar(
                        $usuarioId,
                        $resumo['caixa_atual'],
                        $expirado
                            ? 'Fechado automaticamente ao expirar o horário de acesso'
                            : 'Fechado automaticamente ao sair do sistema'
                    );
                }
            } catch (\Throwable $e) {
                // Ignora: o logout deve concluir mesmo que o caixa falhe ao fechar.
            }
        }

        session_unset();
        session_destroy();

        header('Location: ' . ($expirado ? './login?expirado=1' : './login'));
        exit;
    }

    public function novos30dList()
    {
        $this->requireRole(['ADMIN']);

        header('Content-Type: application/json; charset=utf-8');

        $usuario = $this->model('User');

        echo json_encode([
            'sucesso'   => true,
            'usuarios'  => $usuario->listarNovos30d()
        ]);
        exit;
    }

    /**
     * Perfil de usuário em JSON (para o painel da listagem). Nome deliberadamente
     * diferente de perfil() — aquele é a tela de "meu perfil" do usuário logado.
     * NUNCA inclui o hash da senha na resposta.
     */
    public function perfilJson()
    {
        $this->requireRole(['ADMIN']);

        header('Content-Type: application/json; charset=utf-8');

        if (empty($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'erro' => 'ID não informado.']);
            exit;
        }

        $usuario = $this->model('User');
        $dados   = $usuario->findById((int) $_GET['id']);

        if (!$dados) {
            http_response_code(404);
            echo json_encode(['sucesso' => false, 'erro' => 'Usuário não encontrado.']);
            exit;
        }

        unset($dados['senha']); // nunca expor o hash, mesmo internamente

        echo json_encode(['sucesso' => true, 'usuario' => $dados]);
        exit;
    }

    public function list()
    {
        $this->requireRole(['ADMIN']);

        $usuario = $this->model('User');

        // ── Lê os filtros vindos do formulário da view (name="busca"/"tipo"/"status") ──
        $filtros = [
            'busca'  => trim($_GET['busca'] ?? ''),
            'tipo'   => trim($_GET['tipo'] ?? ''),
            'status' => ($_GET['status'] ?? '') !== '' ? (int) $_GET['status'] : null,
        ];

        // ── Paginação ──
        $paginaAtual = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
        if ($paginaAtual < 1) {
            $paginaAtual = 1;
        }

        // Total de registros que batem com o filtro atual — é essa contagem
        // no banco (não o count() da página atual) que alimenta o card
        // "Total de usuários" e o cálculo de totalPaginas.
        $totalUsuarios = $usuario->countFiltrado($filtros);
        $totalPaginas  = (int) max(1, ceil($totalUsuarios / self::POR_PAGINA));

        // Se o filtro mudou e a página pedida ficou fora do intervalo
        // (ex: estava na página 5 e o filtro só retornou 2 páginas),
        // reposiciona pra última página válida em vez de mostrar lista vazia.
        if ($paginaAtual > $totalPaginas) {
            $paginaAtual = $totalPaginas;
        }

        $offset = ($paginaAtual - 1) * self::POR_PAGINA;

        $dados = [
            'usuarios'      => $usuario->list($filtros, self::POR_PAGINA, $offset),
            'totalUsuarios' => $totalUsuarios,
            'paginaAtual'   => $paginaAtual,
            'totalPaginas'  => $totalPaginas,

            // Cards de estatística: sempre globais (visão geral do sistema),
            // independentes do filtro aplicado na tabela abaixo.
            'totalAtivos'   => $usuario->countAtivos(),
            'totalInativos' => $usuario->countInativos(),
            'totalAdmins'   => $usuario->countAdmins(),
            'totalNovos30d' => $usuario->countNovos30d(),
        ];

        $this->view('Usuarios/listUsuarios', $dados);
    }

    /**
     * Exporta os usuários (respeitando busca/tipo/status da tela, se houver)
     * como CSV — chamado pelo botão "Exportar" do listUsuarios.
     */
    public function exportar()
    {
        $this->requireRole(['ADMIN']);

        $usuario = $this->model('User');

        $filtros = [
            'busca'  => trim($_GET['busca'] ?? ''),
            'tipo'   => trim($_GET['tipo'] ?? ''),
            'status' => ($_GET['status'] ?? '') !== '' ? (int) $_GET['status'] : null,
        ];

        $usuarios = $usuario->listAll($filtros);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="usuarios_' . date('Y-m-d_His') . '.csv"');

        $saida = fopen('php://output', 'w');

        // BOM UTF-8 — sem isso o Excel abre acentos quebrados.
        fwrite($saida, "\xEF\xBB\xBF");

        fputcsv($saida, ['ID', 'Nome', 'E-mail', 'Tipo', 'Status', 'Criado em', 'Atualizado em'], ';');

        foreach ($usuarios as $u) {
            $tipoLabel = match ($u['tipo'] ?? '') {
                'ADMIN'     => 'Admin',
                'TECNICO'   => 'Técnico',
                'ATENDENTE' => 'Atendente',
                default     => $u['tipo'] ?? '',
            };

            fputcsv($saida, [
                $u['id_usuario'] ?? '',
                $this->csvSafe($u['nome'] ?? ''),
                $this->csvSafe($u['email'] ?? ''),
                $tipoLabel,
                ((int) ($u['ativo'] ?? 0) === 1) ? 'Ativo' : 'Inativo',
                !empty($u['criado_em']) ? date('d/m/Y H:i', strtotime($u['criado_em'])) : '',
                !empty($u['atualizado_em']) ? date('d/m/Y H:i', strtotime($u['atualizado_em'])) : '',
            ], ';');
        }

        fclose($saida);
        exit;
    }

    /**
     * Neutraliza valores que começam com =, +, - ou @ antes de escrever no
     * CSV — sem isso, um nome/e-mail cadastrado assim vira fórmula ao abrir
     * a planilha no Excel/Sheets ("CSV injection").
     */
    private function csvSafe(string $valor): string
    {
        if ($valor !== '' && in_array($valor[0], ['=', '+', '-', '@'], true)) {
            return "'" . $valor;
        }

        return $valor;
    }

    /**
     * Ativa/desativa um usuário (chamado pelo clique no badge de status
     * da listagem: href="./toggleStatusUsuario&id=X").
     */
    public function toggleStatus()
    {
        $this->requireRole(['ADMIN']);

        $id = $_GET['id'] ?? null;

        if ($id !== null && ctype_digit((string) $id)) {
            $usuario = $this->model('User');
            $usuario->toggleStatus((int) $id);
        }

        // Volta pra listagem preservando busca/tipo/status/página em que o
        // usuário estava, em vez de sempre resetar pra página 1 sem filtro.
        $destino = $_SERVER['HTTP_REFERER'] ?? './listUsuarios';
        header('Location: ' . $destino);
        exit;
    }

    public function visualizar()
    {
        $this->requireRole(['ADMIN']);

        if (empty($_GET['id'])) {
            header('Location: ./listUsuarios');
            exit;
        }

        $usuario = $this->model('User');

        $dados = [
            'dados_usuario' => $usuario->findById($_GET['id'])
        ];

        if (!$dados['dados_usuario']) {
            header('Location: ./listUsuarios');
            exit;
        }

        $this->view('Usuarios/viewUsuario', $dados);
    }

    public function save()
    {
        $this->requireRole(['ADMIN']);

        $usuario = $this->model('User');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $validator = new Validator($_POST);
            $validator
                ->required('nome', 'Nome')
                ->required('email', 'E-mail')
                ->email('email', 'E-mail')
                ->required('senha', 'Senha')
                ->min('senha', 6, 'Senha')
                ->required('tipo', 'Tipo')
                ->in('tipo', ['ADMIN', 'TECNICO', 'ATENDENTE'], 'Tipo')
                ->custom(
                    'email',
                    fn($v) => !$usuario->emailExiste($v ?? ''),
                    'Este usuário já está cadastrado.'
                );

            if ($validator->fails()) {
                $this->view('Usuarios/saveUsuario', [
                    'error' => $validator->firstError(),
                    'old'   => $_POST,
                ]);
                return;
            }

            if ($usuario->create($_POST)) {
                header('Location: ./listUsuarios');
                exit;
            }

            $this->view('Usuarios/saveUsuario', [
                'error' => 'Erro ao cadastrar usuário.',
                'old' => $_POST
            ]);
            return;
        }

        $this->view('Usuarios/saveUsuario');
    }

    public function edit()
    {
        $this->requireRole(['ADMIN']);

        $usuario = $this->model('User');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id_usuario'] ?? null;

            if (!$id) {
                header('Location: ./listUsuarios');
                exit;
            }

            $usuarioAtual = $usuario->findById($id);

            if (!$usuarioAtual) {
                header('Location: ./listUsuarios');
                exit;
            }

            $validator = new Validator($_POST);
            $validator
                ->required('nome', 'Nome')
                ->required('email', 'E-mail')
                ->email('email', 'E-mail')
                ->required('tipo', 'Tipo')
                ->in('tipo', ['ADMIN', 'TECNICO', 'ATENDENTE'], 'Tipo')
                ->custom(
                    'email',
                    fn($v) => !$usuario->emailExiste($v ?? '', $id),
                    'Já existe outro usuário ativo com este e-mail.'
                );

            if (!empty($_POST['senha'])) {
                $validator->min('senha', 6, 'Senha');
            }

            if ($validator->fails()) {
                $this->view('Usuarios/editUsuario', [
                    'usuario' => array_merge($usuarioAtual, $_POST),
                    'error'   => $validator->firstError(),
                ]);
                return;
            }

            $dados = [
                'id_usuario' => $id,
                'nome'       => trim($_POST['nome'] ?? ''),
                'email'      => trim($_POST['email'] ?? ''),
                'tipo'       => trim($_POST['tipo'] ?? ''),
                'ativo'      => $_POST['ativo'] ?? $usuarioAtual['ativo']
            ];

            if (!empty($_POST['senha'])) {
                $dados['senha'] = password_hash($_POST['senha'], PASSWORD_DEFAULT);
            } else {
                $dados['senha'] = $usuarioAtual['senha'];
            }

            if ($usuario->update($dados)) {
                header('Location: ./listUsuarios');
                exit;
            }

            $this->view('Usuarios/editUsuario', [
                'usuario' => $dados,
                'error'   => 'Erro ao atualizar usuário.'
            ]);
            return;
        }

        if (empty($_GET['id'])) {
            header('Location: ./listUsuarios');
            exit;
        }

        $dados = [
            'usuario' => $usuario->findById($_GET['id'])
        ];

        if (!$dados['usuario']) {
            header('Location: ./listUsuarios');
            exit;
        }

        $this->view('Usuarios/editUsuario', $dados);
    }

    public function delete()
    {
        $this->requireRole(['ADMIN']);

        if (empty($_GET['id'])) {
            header('Location: ./listUsuarios');
            exit;
        }

        $usuario = $this->model('User');
        $usuario->delete($_GET['id']);

        header('Location: ./listUsuarios');
        exit;
    }

    public function perfil()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['logged']) || $_SESSION['logged'] !== true) {
            header('Location: ./login');
            exit;
        }

        $idUser = $_SESSION['user_id_usuario'] ?? null;

        if (!$idUser) {
            header('Location: ./logout');
            exit;
        }

        $userModel = $this->model('User');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuarioAtual = $userModel->findById($idUser);

            $dados = [
                'id_usuario' => $idUser,
                'nome'       => trim($_POST['nome'] ?? ''),
                'email'      => trim($_POST['email'] ?? ''),
                'tipo'       => trim($_POST['tipo'] ?? ''),
                'ativo'      => $usuarioAtual['ativo']
            ];

            if (!empty($_POST['senha'])) {
                $dados['senha'] = password_hash($_POST['senha'], PASSWORD_DEFAULT);
            } else {
                $dados['senha'] = $usuarioAtual['senha'];
            }

            if ($userModel->emailExiste($dados['email'], $idUser)) {
                $this->view('Usuarios/perfil', [
                    'usuario'      => $usuarioAtual,
                    'mensagemErro' => 'Já existe outro usuário ativo com este e-mail.'
                ]);
                return;
            }

            if ($userModel->update($dados)) {
                $_SESSION['user_nome']  = $dados['nome'];
                $_SESSION['user_email'] = $dados['email'];
                $_SESSION['user_tipo']  = $dados['tipo'];

                $usuarioAtualizado = $userModel->findById($idUser);

                $this->view('Usuarios/perfil', [
                    'usuario' => $usuarioAtualizado,
                    'mensagemSucesso' => 'Perfil atualizado com sucesso.'
                ]);
                return;
            }

            $usuario = $userModel->findById($idUser);

            $this->view('Usuarios/perfil', [
                'usuario' => $usuario,
                'mensagemErro' => 'Erro ao atualizar perfil.'
            ]);
            return;
        }

        $usuario = $userModel->findById($idUser);

        $this->view('Usuarios/perfil', [
            'usuario' => $usuario
        ]);
    }

    /**
     * Alteração de senha pelo próprio usuário (formulário dedicado do perfil).
     * Exige a senha atual e a confirmação da nova senha.
     */
    public function updateSenha()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['logged']) || $_SESSION['logged'] !== true) {
            header('Location: ./login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ./perfil');
            exit;
        }

        $idUser = $_SESSION['user_id_usuario'] ?? null;

        if (!$idUser) {
            header('Location: ./logout');
            exit;
        }

        $userModel = $this->model('User');
        $usuario   = $userModel->findById($idUser);

        if (!$usuario) {
            header('Location: ./logout');
            exit;
        }

        $senhaAtual = $_POST['senha_atual']     ?? '';
        $novaSenha  = $_POST['nova_senha']      ?? '';
        $confirmar  = $_POST['confirmar_senha'] ?? '';

        $erro = null;

        if ($senhaAtual === '' || $novaSenha === '' || $confirmar === '') {
            $erro = 'Preencha todos os campos de senha.';
        } elseif (!password_verify($senhaAtual, $usuario['senha'])) {
            $erro = 'Senha atual incorreta.';
        } elseif ($novaSenha !== $confirmar) {
            $erro = 'A nova senha e a confirmação não coincidem.';
        }

        if ($erro) {
            $this->view('Usuarios/perfil', [
                'usuario'      => $usuario,
                'mensagemErro' => $erro,
            ]);
            return;
        }

        $dados = [
            'id_usuario' => $idUser,
            'nome'       => $usuario['nome'],
            'email'      => $usuario['email'],
            'tipo'       => $usuario['tipo'],
            'ativo'      => $usuario['ativo'],
            'senha'      => password_hash($novaSenha, PASSWORD_DEFAULT),
        ];

        if ($userModel->update($dados)) {
            $this->view('Usuarios/perfil', [
                'usuario'         => $userModel->findById($idUser),
                'mensagemSucesso' => 'Senha atualizada com sucesso.',
            ]);
            return;
        }

        $this->view('Usuarios/perfil', [
            'usuario'      => $usuario,
            'mensagemErro' => 'Erro ao atualizar a senha.',
        ]);
    }

    /**
     * Solicitação de recuperação de senha.
     * GET  → exibe o formulário pedindo o e-mail.
     * POST → se o e-mail existir e estiver ativo, gera um token e envia o link.
     * Sempre mostra a mesma mensagem de sucesso, exista o e-mail ou não —
     * evita que alguém descubra quais e-mails estão cadastrados no sistema.
     */
    public function recuperarSenha()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');

            $userModel = $this->model('User');
            $usuario   = $userModel->getUserByEmail($email);

            $mensagem = 'Se o e-mail informado estiver cadastrado, você receberá um link para redefinir sua senha em instantes.';

            if ($usuario && $usuario['ativo'] == 1) {
                try {
                    $reset = $this->model('PasswordReset');
                    $token = $reset->criar($usuario['id_usuario']);

                    $link = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://')
                        . $_SERVER['HTTP_HOST']
                        . dirname($_SERVER['SCRIPT_NAME'])
                        . '/redefinir-senha?token=' . $token;

                    $corpo = "<p>Olá, {$usuario['nome']}.</p>"
                        . "<p>Clique no link abaixo para redefinir sua senha. Esse link expira em 30 minutos:</p>"
                        . "<p><a href=\"{$link}\">{$link}</a></p>"
                        . "<p>Se você não solicitou isso, ignore este e-mail.</p>";

                    require_once __DIR__ . '/../core/Mailer.php';
                    (new Mailer())->enviar($usuario['email'], 'Recuperação de senha - Mecanize', $corpo);
                } catch (\Throwable $e) {
                    error_log('Erro ao enviar e-mail de recuperação: ' . $e->getMessage());
                }
            }

            $this->view('Usuarios/recuperarSenha', ['mensagem' => $mensagem]);
            return;
        }

        $this->view('Usuarios/recuperarSenha');
    }

    /**
     * Redefinição de senha a partir do token recebido por e-mail.
     * GET  → valida o token e exibe o formulário de nova senha.
     * POST → grava a nova senha e invalida o token.
     */
    public function redefinirSenha()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = $_GET['token'] ?? ($_POST['token'] ?? '');

        if ($token === '') {
            header('Location: ./login');
            exit;
        }

        $resetModel = $this->model('PasswordReset');
        $reset      = $resetModel->validar($token);

        if (!$reset) {
            $this->view('Usuarios/redefinirSenha', [
                'error'         => 'Este link é inválido ou já expirou. Solicite a recuperação novamente.',
                'tokenInvalido' => true,
            ]);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $novaSenha = $_POST['nova_senha']      ?? '';
            $confirmar = $_POST['confirmar_senha'] ?? '';

            if ($novaSenha === '' || $novaSenha !== $confirmar) {
                $this->view('Usuarios/redefinirSenha', [
                    'token' => $token,
                    'error' => 'As senhas não coincidem ou estão vazias.',
                ]);
                return;
            }

            $userModel = $this->model('User');
            $usuario   = $userModel->findById($reset['usuario_id']);

            $userModel->update([
                'id_usuario' => $usuario['id_usuario'],
                'nome'       => $usuario['nome'],
                'email'      => $usuario['email'],
                'tipo'       => $usuario['tipo'],
                'ativo'      => $usuario['ativo'],
                'senha'      => password_hash($novaSenha, PASSWORD_DEFAULT),
            ]);

            $resetModel->marcarUsado($token);

            header('Location: ./login?senhaRedefinida=1');
            exit;
        }

        $this->view('Usuarios/redefinirSenha', ['token' => $token]);
    }
}
