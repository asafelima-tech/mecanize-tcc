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
            // Técnico não usa o dashboard financeiro — cai direto na própria
            // central de trabalho.
            return (($_SESSION['user_tipo'] ?? '') === 'TECNICO') ? './painelTecnico' : './home';
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

            // A SENHA É VALIDADA PRIMEIRO, de propósito.
            //
            // Antes o bloqueio e a conta inativa eram checados na frente, e a
            // tela respondia coisas diferentes para "e-mail existe e está
            // bloqueado" e "e-mail não existe" — quem estivesse sondando
            // descobria quais e-mails têm conta aqui sem saber nenhuma senha.
            // Agora quem erra a senha recebe sempre a mesma frase; as mensagens
            // específicas só aparecem para quem provou ser dono da conta.
            // Custo: um password_verify() a mais quando a conta está bloqueada.
            $senhaValida = $userData && password_verify($senha, $userData['senha']);

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

            // Senha certa: daqui para baixo quem está do outro lado é o dono da
            // conta, então as mensagens podem ser específicas.
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

            // Horário de acesso: regra SÓ do atendente (Sessao::foraDoHorario
            // devolve false para ADMIN e TÉCNICO). A mesma função é reforçada a
            // cada requisição no public/index.php.
            if (Sessao::foraDoHorario($userData['tipo'] ?? null)) {
                $this->view('Usuarios/login', [
                    'error' => 'Acesso fora do horário permitido. ' . Sessao::MENSAGEM_JANELA
                ]);
                return;
            }

            // Gera um novo ID de sessão ao autenticar — sem isso, um ID de
            // sessão plantado antes do login (fixação de sessão) continuaria
            // válido depois, deixando quem o plantou herdar a sessão logada.
            session_regenerate_id(true);

            $_SESSION['logged'] = true;

            // Campo a campo, e não um foreach sobre o SELECT *.
            //
            // Do jeito anterior, o HASH DA SENHA e o controle de bloqueio
            // iam parar no arquivo de sessão do servidor, onde não têm
            // nenhuma utilidade: qualquer leitura desses arquivos (backup,
            // pasta temporária compartilhada, outro site no mesmo host)
            // entregava material para quebrar a senha offline.
            $_SESSION['user_id_usuario']    = $userData['id_usuario'];
            $_SESSION['user_nome']          = $userData['nome'];
            $_SESSION['user_email']         = $userData['email'];
            $_SESSION['user_telefone']      = $userData['telefone'] ?? null;
            $_SESSION['user_tipo']          = $userData['tipo'];
            $_SESSION['user_ativo']         = $userData['ativo'];

            // Carimbo usado para revalidar a conta a cada requisição — ver o
            // bloco de sessão em public/index.php.
            $_SESSION['user_atualizado_em'] = $userData['atualizado_em'] ?? null;
            $_SESSION['ultimo_acesso']      = time();

            // Volta pra onde a pessoa tentou ir antes de cair na tela de login,
            // em vez de largar no Início e obrigar a navegar tudo de novo.
            header('Location: ' . $this->destinoAposLogin($_POST['retorno'] ?? ''));
            exit;
        }

        // Cada saída forçada chega com o SEU motivo (?motivo=...) e a mensagem
        // vem de Sessao::mensagem() — aqui ninguém deduz um motivo a partir de
        // outro. "Sair" não traz motivo: login limpo, sem aviso. O parâmetro
        // antigo ?expirado= era usado para tudo e a tela o lia como "horário":
        // links antigos agora viram "sessão inválida", nunca "horário".
        $aviso = Sessao::mensagem($_GET['motivo'] ?? null)
            ?? (!empty($_GET['expirado']) ? Sessao::mensagem(Sessao::SESSAO_INVALIDA) : null);

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
        $tipo       = $_SESSION['user_tipo'] ?? null;
        $confirmado = !empty($_GET['confirmado']);

        // Logout FORÇADO por uma regra do sistema (inatividade, horário, conta…)
        // traz o motivo guardado na SESSÃO por Sessao::encerrar() — nunca da URL,
        // onde qualquer um digitaria `?expirado=1` para pular o fechamento do
        // caixa. Sem motivo na sessão, é o logout manual ("Sair").
        $motivo  = Sessao::motivoPendente();
        $forcado = $motivo !== null;

        // O caixa é uma gaveta só para a oficina, mas responde por ela quem a
        // abriu. Sair do sistema só mexe no caixa de QUEM É O DONO dele: antes,
        // o logout de qualquer usuário fechava a gaveta de outro — e o técnico,
        // empurrado para a tela de fechamento, levava um 403 e não conseguia sair.
        $ehDono = false;

        if ($usuarioId) {
            try {
                $ehDono = $this->model('Caixa')->ehDonoDoCaixaAberto($usuarioId);
            } catch (\Throwable $e) {
                // Falha na checagem não pode impedir o logout.
            }
        }

        // Logout voluntário do dono, sem confirmação: manda para a tela de
        // fechamento em vez de sair direto. O logout forçado NUNCA passa por
        // essa trava — o usuário já não está mais ali para confirmar nada.
        if ($ehDono && !$forcado && !$confirmado) {
            header('Location: ./fecharCaixa?logout=1');
            exit;
        }

        // O caixa do dono é fechado automaticamente ao sair, registrando o
        // dinheiro apurado na gaveta (abertura + entradas em dinheiro −
        // saídas), calculado a partir das movimentações da sessão. Uma queda
        // forçada do ADMIN NÃO fecha o caixa (o ADMIN só visita). Uma eventual
        // falha aqui não pode impedir o logout.
        $fechaCaixa = $ehDono && !($forcado && $tipo === 'ADMIN');

        if ($fechaCaixa) {
            try {
                $caixa  = $this->model('Caixa');
                $aberto = $caixa->getCaixaAberto();

                if ($aberto) {
                    $resumo = $this->model('MovimentacaoCaixa')->resumoSessao($aberto['id_caixa']);

                    $observacoes = [
                        Sessao::INATIVIDADE      => 'Fechado automaticamente — sessão expirada por inatividade',
                        Sessao::HORARIO          => 'Fechado automaticamente ao expirar o horário de acesso',
                        Sessao::CONTA_ALTERADA   => 'Fechado automaticamente — sessão encerrada por alteração na conta',
                        Sessao::CONTA_DESATIVADA => 'Fechado automaticamente — conta desativada',
                        Sessao::SESSAO_INVALIDA  => 'Fechado automaticamente — sessão inválida',
                    ];

                    $caixa->fechar(
                        $usuarioId,
                        $resumo['caixa_atual'],
                        $observacoes[$motivo] ?? 'Fechado automaticamente ao sair do sistema',
                        $resumo['caixa_atual']
                    );
                }
            } catch (\Throwable $e) {
                // Ignora: o logout deve concluir mesmo que o caixa falhe ao fechar.
            }
        }

        session_unset();
        session_destroy();

        header('Location: ' . ($forcado ? './login?motivo=' . urlencode($motivo) : './login'));
        exit;
    }

    public function novos30dList()
    {
        $this->requireRole(Permissao::PAPEIS_ADMIN);

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
        $this->requireRole(Permissao::PAPEIS_ADMIN);

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
        $this->requireRole(Permissao::PAPEIS_ADMIN);

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
        $this->requireRole(Permissao::PAPEIS_ADMIN);

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
     * Ativa/desativa um usuário (clique no badge de status da listagem, que
     * agora é um formulário POST).
     */
    public function toggleStatus()
    {
        $this->requireRole(Permissao::PAPEIS_ADMIN);

        // POST em vez de GET: como link, bastava a URL ser carregada de
        // qualquer lugar — um <img> numa página externa, por exemplo — para
        // desativar um usuário na sessão de um ADMIN logado. O gate de CSRF
        // do sistema só cobre POST.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
            header('Location: ./listUsuarios');
            exit;
        }

        if (ctype_digit((string) $_POST['id'])) {
            $usuario = $this->model('User');
            $usuario->toggleStatus((int) $_POST['id']);
        }

        header('Location: ' . $this->urlListaUsuarios());
        exit;
    }

    /**
     * Volta para a listagem preservando aba/busca/tipo/página.
     *
     * Os valores vêm do campo `retorno` do próprio formulário, e só as chaves
     * conhecidas são remontadas. Antes isso era feito com $_SERVER['HTTP_REFERER'],
     * que é enviado pelo cliente: bastava forjar o cabeçalho para o sistema
     * redirecionar a pessoa para qualquer endereço externo depois da ação.
     */
    private function urlListaUsuarios(): string
    {
        parse_str($_POST['retorno'] ?? '', $params);

        $query = array_filter(
            [
                'aba'    => $params['aba']    ?? '',
                'busca'  => $params['busca']  ?? '',
                'tipo'   => $params['tipo']   ?? '',
                'pagina' => $params['pagina'] ?? '',
            ],
            fn($v) => is_string($v) && trim($v) !== ''
        );

        return './listUsuarios' . ($query ? '?' . http_build_query($query) : '');
    }

    public function visualizar()
    {
        $this->requireRole(Permissao::PAPEIS_ADMIN);

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
        $this->requireRole(Permissao::PAPEIS_ADMIN);

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

            $novoId = $usuario->create($_POST);

            if ($novoId) {
                // Regra de remuneração é um cadastro à parte (tabela própria) —
                // uma falha aqui não pode fazer parecer que o usuário não foi
                // criado. Manda pra edição do técnico recém-criado com o erro,
                // em vez de perder os dados básicos já salvos.
                if (($_POST['tipo'] ?? '') === 'TECNICO' && !empty($_POST['remuneracao_tipo'])) {
                    $erroRegra = $this->salvarRegraRemuneracao($novoId, $_POST);
                    if ($erroRegra !== true) {
                        header('Location: ./editUsuario?id=' . $novoId . '&erro=' . urlencode($erroRegra));
                        exit;
                    }
                }

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
        $this->requireRole(Permissao::PAPEIS_ADMIN);

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
                if ($dados['tipo'] === 'TECNICO') {
                    $erroRegra = $this->salvarRegraRemuneracao((int) $id, $_POST);
                    if ($erroRegra !== true) {
                        $this->view('Usuarios/editUsuario', [
                            'usuario' => $dados,
                            'regra'   => array_merge(['tipo' => $_POST['remuneracao_tipo'] ?? ''], $_POST),
                            'error'   => $erroRegra,
                        ]);
                        return;
                    }
                }

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

        if ($dados['usuario']['tipo'] === 'TECNICO') {
            $dados['regra'] = $this->model('TecnicoRemuneracao')->buscarRegraAtiva((int) $_GET['id']);
        }

        if (!empty($_GET['erro'])) {
            $dados['error'] = $_GET['erro'];
        }

        $this->view('Usuarios/editUsuario', $dados);
    }

    /**
     * Grava a regra de remuneração de um técnico a partir dos campos do
     * formulário de usuário (prefixo `remuneracao_`, ver
     * Usuarios/saveUsuario.php e editUsuario.php). Devolve true, ou a
     * mensagem de erro do model pra mostrar de volta no formulário.
     */
    private function salvarRegraRemuneracao(int $tecnicoId, array $post)
    {
        $categorias = [];
        foreach (($post['remuneracao_categoria'] ?? []) as $i => $categoria) {
            $categorias[] = [
                'categoria'  => $categoria,
                'percentual' => $post['remuneracao_categoria_pct'][$i] ?? null,
            ];
        }

        return $this->model('TecnicoRemuneracao')->salvar($tecnicoId, [
            'tipo'                 => $post['remuneracao_tipo'] ?? '',
            'percentual'           => $post['remuneracao_percentual'] ?? null,
            'valor_fixo'           => $post['remuneracao_valor_fixo'] ?? null,
            'comissao_base_extra'  => $post['remuneracao_base_extra'] ?? null,
            'observacoes'          => $post['remuneracao_observacoes'] ?? null,
            'categorias'           => $categorias,
        ]);
    }

    public function delete()
    {
        $this->requireRole(Permissao::PAPEIS_ADMIN);

        // Exclusão só por POST — ver o comentário em toggleStatus().
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
                // via UserController::edit() (que já exige requireRole(Permissao::PAPEIS_ADMIN)),
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

        // A mesma régua da redefinição por token e do cadastro de usuário:
        // trocar a própria senha também aceitava senha de um caractere.
        $validator = (new Validator($_POST))
            ->required('nova_senha', 'Nova senha')
            ->min('nova_senha', 6, 'Nova senha')
            ->matches('nova_senha', 'confirmar_senha', 'Confirmação de senha');

        if ($senhaAtual === '' || $novaSenha === '' || $confirmar === '') {
            $erro = 'Preencha todos os campos de senha.';
        } elseif (!password_verify($senhaAtual, $usuario['senha'])) {
            $erro = 'Senha atual incorreta.';
        } elseif ($validator->fails()) {
            $erro = $validator->firstError();
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

                    // Teto de 3 pedidos por hora e por conta: o formulário é
                    // público, então sem isso qualquer um dispara e-mail sem
                    // limite na caixa de um usuário cadastrado. Passando do
                    // teto, o envio é pulado em silêncio — a tela responde a
                    // mesma mensagem de sempre, que já é idêntica para e-mail
                    // existente e inexistente, então nada é revelado.
                    if ($reset->pedidosNaUltimaHora((int) $usuario['id_usuario']) >= 3) {
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

            // Mesma régua de save() e edit(): sem isto, a redefinição por link
            // de e-mail era o caminho para deixar a conta com uma senha de um
            // caractere — justamente o fluxo usado por quem já perdeu o acesso.
            $validator = (new Validator($_POST))
                ->required('nova_senha', 'Nova senha')
                ->min('nova_senha', 6, 'Nova senha')
                ->matches('nova_senha', 'confirmar_senha', 'Confirmação de senha');

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
