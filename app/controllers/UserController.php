<?php

class UserController extends Controller
{
    const POR_PAGINA = 10;

    /**
     * Traduz o ?retorno= da tela de login numa rota interna segura.
     *
     * Só aceita rota registrada em config/routes.php: sem essa checagem, o
     * parâmetro viraria um redirecionamento aberto — bastaria mandar pra
     * alguém um link de login com retorno pra um site externo, e a pessoa
     * sairia do sistema achando que continuava dentro dele.
     */
    private function destinoAposLogin(string $retorno): string
    {
        $retorno = trim($retorno);

        if ($retorno === '') {
            return './home';
        }

        // Separa a rota dos parâmetros e valida só a rota.
        $partes = explode('&', $retorno, 2);
        $rota   = $partes[0];

        require __DIR__ . '/../../config/routes.php';

        if (!isset($routes[$rota])) {
            return './home';
        }

        return './' . $retorno;
    }

    public function login()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userModel = $this->model('User');

            $email = trim($_POST['email'] ?? '');
            $senha = $_POST['senha'] ?? '';

            $userData = ($email !== '' && $senha !== '') ? $userModel->getUserByEmail($email) : null;

            $senhaValida = $userData && password_verify($senha, $userData['senha']);

            /*
             * A senha é conferida ANTES do bloqueio de propósito.
             *
             * A ordem anterior era a inversa, e isso entregava informação a
             * quem estivesse só sondando endereços: digitando qualquer senha,
             * "Conta temporariamente bloqueada" confirmava que aquele e-mail
             * existe no sistema, e "Login inválido" confirmava que não. Agora
             * quem erra a senha recebe sempre a mesma frase, exista a conta ou
             * não; o aviso de bloqueio (que é útil e específico) só aparece
             * para quem provou ser o dono da conta acertando a senha.
             *
             * A proteção contra tentativa e erro continua igual: com a conta
             * bloqueada, nem a senha certa entra.
             */
            if (!$senhaValida) {
                // Só incrementa o contador se o e-mail existir de verdade — não
                // adianta "bloquear" uma conta que não existe.
                if ($userData) {
                    $userModel->registrarTentativaFalha($userData['id_usuario']);
                }

                $this->view('Usuarios/login', [
                    'error' => 'Login inválido.'
                ]);
                return;
            }

            if ($userModel->estaBloqueado($userData)) {
                $this->view('Usuarios/login', [
                    'error' => 'Conta temporariamente bloqueada por excesso de tentativas. Tente novamente em ' .
                        $userModel->minutosBloqueioRestante($userData) . ' minuto(s).'
                ]);
                return;
            }

            $userModel->resetarTentativas($userData['id_usuario']);

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

            // Gera um novo ID de sessão ao autenticar — sem isso, um ID de
            // sessão plantado antes do login (fixação de sessão) continuaria
            // válido depois, deixando quem o plantou herdar a sessão logada.
            session_regenerate_id(true);

            $_SESSION['logged'] = true;

            /*
             * Campos gravados um a um, e não com um foreach sobre o registro
             * inteiro. O foreach copiava TODAS as colunas do usuário para a
             * sessão — inclusive o hash da senha, que ficava guardado em disco
             * no arquivo de sessão do servidor durante todo o expediente, sem
             * nenhuma tela precisar dele. Estes são os únicos campos que as
             * telas realmente leem.
             */
            $_SESSION['user_id_usuario'] = $userData['id_usuario'];
            $_SESSION['user_nome']       = $userData['nome'];
            $_SESSION['user_email']      = $userData['email'];
            $_SESSION['user_tipo']       = $userData['tipo'];

            // Marca do estado da conta no momento do login. O front-controller
            // compara com o banco a cada requisição — ver "Conta alterada
            // durante a sessão" em public/index.php.
            $_SESSION['user_atualizado_em'] = $userData['atualizado_em'] ?? '';

            // Início da contagem de inatividade.
            $_SESSION['ultimo_acesso'] = time();

            // Volta pra onde a pessoa tentou ir antes de cair na tela de login,
            // em vez de largar no Início e obrigar a navegar tudo de novo.
            header('Location: ' . $this->destinoAposLogin($_POST['retorno'] ?? ''));
            exit;
        }

        // Aviso exibido quando o atendente é desconectado por fim do expediente.
        $aviso = !empty($_GET['expirado'])
            ? 'Seu horário de acesso foi encerrado. Atendentes têm acesso de segunda a sexta das 07:00 às 18:00 e aos sábados das 07:00 às 13:00.'
            : null;

        // Carregado no formulário pra sobreviver ao POST — é o que leva a
        // pessoa de volta pra tela que ela tentou abrir antes de logar.
        $this->view('Usuarios/login', [
            'aviso'   => $aviso,
            'retorno' => $_GET['retorno'] ?? '',
        ]);
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
                            : 'Fechado automaticamente ao sair do sistema',
                        $resumo['caixa_atual']
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

        $aba     = $this->abaAtual();
        $filtros = $this->filtrosDaTela();

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
            'aba'           => $aba,

            // Cards de estatística: sempre globais (visão geral do sistema),
            // independentes da aba e do filtro aplicados na tabela abaixo.
            // $totalUsuarios acima conta só a aba atual (é o que alimenta a
            // paginação); o card "Total de usuários" precisa do geral.
            'totalGeral'    => $usuario->countFiltrado([]),
            'totalAtivos'   => $usuario->countAtivos(),
            'totalInativos' => $usuario->countInativos(),
            'totalAdmins'   => $usuario->countAdmins(),
            'totalNovos30d' => $usuario->countNovos30d(),
        ];

        // Troca de aba / paginação / busca chegam por fetch e só precisam do
        // miolo da tela. É a MESMA rota — o que muda é devolver só o pedaço
        // que vai ser trocado, em vez da página inteira com layout.
        if (!empty($_GET['fragmento'])) {
            $this->view('Usuarios/_tabelaUsuarios', $dados);
            return;
        }

        $this->view('Usuarios/listUsuarios', $dados);
    }

    /**
     * Aba visível da tela de usuários: 'ativos' (padrão) ou 'inativos'.
     * Qualquer outro valor cai em 'ativos' — a aba decide o recorte da
     * listagem, então não pode aceitar entrada arbitrária.
     */
    private function abaAtual(): string
    {
        return (($_GET['aba'] ?? '') === 'inativos') ? 'inativos' : 'ativos';
    }

    /**
     * Filtros da tela de usuários. O status NÃO vem de um campo próprio: é a
     * aba que decide se a listagem mostra ativos ou inativos. Compartilhado
     * entre list() e exportar() pra exportação sair sempre igual ao que está
     * na tela.
     */
    private function filtrosDaTela(): array
    {
        return [
            'busca'  => trim($_GET['busca'] ?? ''),
            'tipo'   => trim($_GET['tipo'] ?? ''),
            'status' => $this->abaAtual() === 'inativos' ? 0 : 1,
        ];
    }

    /**
     * Exporta os usuários (respeitando busca/tipo/status da tela, se houver)
     * como CSV — chamado pelo botão "Exportar" do listUsuarios.
     */
    public function exportar()
    {
        $this->requireRole(['ADMIN']);

        $usuario = $this->model('User');

        $usuarios = $usuario->listAll($this->filtrosDaTela());

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

        // POST em vez de GET: um link GET que muda dado pode ser disparado por
        // um <img>/pré-carregamento em outra página só de o ADMIN estar logado,
        // sem nenhum clique consciente. O gate de CSRF só cobre POST.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ./listUsuarios');
            exit;
        }

        $id = $_POST['id'] ?? null;

        if ($id !== null && ctype_digit((string) $id)) {
            $usuario = $this->model('User');
            $usuario->toggleStatus((int) $id);
        }

        header('Location: ' . $this->urlListagemUsuarios());
        exit;
    }

    /**
     * Volta pra listagem preservando a aba/busca/tipo/página em que a pessoa
     * estava, em vez de resetar tudo para a página 1 sem filtro.
     *
     * Os valores vêm de um campo oculto do próprio formulário e só as quatro
     * chaves conhecidas são reaproveitadas. Antes o destino era o cabeçalho
     * Referer, que quem envia a requisição escolhe livremente — bastava
     * apontá-lo para outro site e o sistema mandava o usuário para lá,
     * ainda por cima logo depois de uma ação bem-sucedida, que é justamente
     * quando ninguém desconfia do redirecionamento.
     */
    private function urlListagemUsuarios(): string
    {
        parse_str($_POST['retorno'] ?? '', $parametros);

        $query = [];

        foreach (['aba', 'busca', 'tipo', 'pagina'] as $chave) {
            if (!empty($parametros[$chave])) {
                $query[$chave] = is_array($parametros[$chave]) ? '' : trim((string) $parametros[$chave]);
            }
        }

        return './listUsuarios' . ($query ? '?' . http_build_query($query) : '');
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
            $this->naoEncontrado('Usuário', './listUsuarios');
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
                $this->naoEncontrado('Usuário', './listUsuarios');
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
            $this->naoEncontrado('Usuário', './listUsuarios');
        }

        $this->view('Usuarios/editUsuario', $dados);
    }

    public function delete()
    {
        $this->requireRole(['ADMIN']);

        // POST em vez de GET: um link GET que exclui pode ser disparado por um
        // <img>/pré-carregamento em outra página só de o ADMIN estar logado,
        // sem nenhum clique consciente. O gate de CSRF só cobre POST.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
            header('Location: ./listUsuarios');
            exit;
        }

        $id = (int) $_POST['id'];

        // Excluir a si mesmo deixaria a sessão apontando pra um usuário que
        // não existe mais — todo request seguinte quebraria.
        if ($id === (int) ($_SESSION['user_id_usuario'] ?? 0)) {
            header('Location: ./listUsuarios?erro=proprio');
            exit;
        }

        $usuario = $this->model('User');

        // Usuário com histórico não pode ser apagado: as foreign keys barram
        // (e é o certo — apagá-lo destruiria a autoria de caixas, lançamentos
        // e serviços). Antes isso virava PDOException não tratada e tela 500;
        // agora vira uma mensagem dizendo o que impede, com a desativação
        // como caminho alternativo.
        $vinculos = $usuario->vinculos($id);

        if (!empty($vinculos)) {
            header('Location: ./listUsuarios?erro=vinculos&detalhe=' . urlencode(implode(', ', $vinculos)));
            exit;
        }

        if (!$usuario->delete($id)) {
            header('Location: ./listUsuarios?erro=exclusao');
            exit;
        }

        header('Location: ./listUsuarios?ok=excluido');
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
                'telefone'   => trim($_POST['telefone'] ?? ''),
                // 'tipo' NUNCA vem do próprio formulário de perfil — só o admin,
                // via UserController::edit() (que já exige requireRole(['ADMIN'])),
                // pode mudar o papel de alguém. Ignorar qualquer 'tipo' postado
                // aqui evita que o próprio usuário se promova a ADMIN.
                'tipo'       => $usuarioAtual['tipo'],
                'ativo'      => $usuarioAtual['ativo']
            ];

            if (!empty($_POST['senha'])) {
                $dados['senha'] = password_hash($_POST['senha'], PASSWORD_DEFAULT);
            } else {
                $dados['senha'] = $usuarioAtual['senha'];
            }

            $validator = new Validator($_POST);
            $validator->celularBr('telefone', 'Telefone');

            if ($validator->fails()) {
                $this->view('Usuarios/perfil', [
                    'usuario'      => array_merge($usuarioAtual, $dados),
                    'mensagemErro' => $validator->firstError()
                ]);
                return;
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

                // Editar o próprio perfil também mexe em atualizado_em. Sem
                // reanotar aqui, a checagem do front-controller entenderia
                // "alteraram esta conta" e derrubaria a pessoa logo depois de
                // ela salvar os próprios dados.
                $_SESSION['user_atualizado_em'] = $usuarioAtualizado['atualizado_em'] ?? '';

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
        } elseif (mb_strlen($novaSenha) < 6) {
            // Mesmo mínimo exigido no cadastro de usuário — trocar a própria
            // senha não pode ser a porta de entrada para uma senha de 1 letra.
            $erro = 'A nova senha deve ter pelo menos 6 caracteres.';
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
            $usuarioAtualizado = $userModel->findById($idUser);

            // Trocar a senha mexe em atualizado_em, o mesmo campo que o
            // front-controller usa para detectar "alteraram esta conta".
            // Reanotar aqui mantém logada a pessoa que acabou de trocar a
            // própria senha — e continua derrubando qualquer OUTRA sessão
            // aberta com a senha antiga, que é justamente o que se quer.
            $_SESSION['user_atualizado_em'] = $usuarioAtualizado['atualizado_em'] ?? '';

            // Sessão antiga não deve continuar valendo depois de uma troca de
            // senha: gera um identificador novo, como já se faz no login.
            session_regenerate_id(true);

            $this->view('Usuarios/perfil', [
                'usuario'         => $usuarioAtualizado,
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

                    // Passou do limite de pedidos na hora: não envia mais nada,
                    // mas a tela responde exatamente igual. Quem tenta usar esta
                    // página pra encher a caixa de entrada de alguém não recebe
                    // nenhum sinal de que parou de funcionar.
                    if ($reset->pedidosNaUltimaHora($usuario['id_usuario']) >= PasswordReset::LIMITE_POR_HORA) {
                        $this->view('Usuarios/recuperarSenha', ['mensagem' => $mensagem]);
                        return;
                    }

                    $token = $reset->criar($usuario['id_usuario']);

                    $link = $this->urlAbsoluta('redefinir-senha?token=' . $token);

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
            $novaSenha = $_POST['nova_senha'] ?? '';

            // Mesma exigência de save() e edit(): este era o único caminho de
            // troca de senha que aceitava qualquer coisa, inclusive um único
            // caractere — logo o caminho menos vigiado, que chega por link de
            // e-mail e não pede a senha antiga.
            $validator = (new Validator($_POST))
                ->required('nova_senha', 'Nova senha')
                ->min('nova_senha', 6, 'Nova senha')
                ->matches('nova_senha', 'confirmar_senha', 'A confirmação da senha');

            if ($validator->fails()) {
                $this->view('Usuarios/redefinirSenha', [
                    'token' => $token,
                    'error' => $validator->firstError(),
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
