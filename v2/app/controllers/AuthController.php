<?php

/** Entrar, sair e recuperar a senha. */
final class AuthController extends Controller
{
    public function login(): void
    {
        if (Sessao::usuarioId() !== null && !$this->ehPost()) {
            $this->redirecionar(Permissao::inicio(Contexto::perfil()));
        }

        if (!$this->ehPost()) {
            $this->tela('auth/login', [
                'aviso'   => Sessao::mensagem($_GET['motivo'] ?? null)
                             ?? (!empty($_GET['senhaRedefinida']) ? 'Senha redefinida. Entre com a nova senha.' : null),
                'retorno' => (string) ($_GET['retorno'] ?? ''),
            ], null);
            return;
        }

        $usuarios = new Usuario();
        $email    = $this->post('email');
        $senha    = (string) ($_POST['senha'] ?? '');
        $conta    = ($email !== '' && $senha !== '') ? $usuarios->porEmail($email) : null;

        // A senha é conferida PRIMEIRO: quem não sabe a senha recebe sempre a mesma frase, então não
        // dá para descobrir quais e-mails têm conta, nem quais estão bloqueados.
        if (!$conta || !password_verify($senha, $conta['senha_hash'])) {
            if ($conta) {
                $usuarios->registrarTentativaFalha((int) $conta['id']);
            }
            $this->telaLogin('E-mail ou senha inválidos.', $email);
            return;
        }

        if ($usuarios->estaBloqueado($conta)) {
            $this->telaLogin('Conta bloqueada por excesso de tentativas. Tente de novo em ' . $usuarios->minutosDeBloqueio($conta) . ' minuto(s).', $email);
            return;
        }
        if ((int) $conta['ativo'] !== 1) {
            $this->telaLogin('Seu acesso está desativado. Fale com o administrador da oficina.', $email);
            return;
        }
        if ($conta['perfil'] !== 'PLATAFORMA' && $conta['oficina_status'] === 'CANCELADA') {
            $this->telaLogin(Sessao::mensagem(Sessao::OFICINA_CANCELADA), $email);
            return;
        }

        $usuarios->zerarTentativas((int) $conta['id']);
        Sessao::entrar($conta);

        $this->redirecionar($this->destinoSeguro((string) ($_POST['retorno'] ?? ''), $conta['perfil']));
    }

    public function sair(): void
    {
        $motivo = $_GET['motivo'] ?? null;
        Sessao::sair();
        $this->redirecionar('login', Sessao::mensagem($motivo) ? ['motivo' => $motivo] : []);
    }

    public function recuperarSenha(): void
    {
        if (!$this->ehPost()) {
            $this->tela('auth/recuperar', [], 'auth');
            return;
        }

        $usuarios = new Usuario();
        $conta    = $usuarios->porEmail($this->post('email'));

        if ($conta && (int) $conta['ativo'] === 1) {
            try {
                $token = $usuarios->criarTokenRecuperacao($conta);
                if ($token !== null) {
                    $link = $this->urlAbsoluta('redefinir-senha?token=' . $token);
                    (new Mailer())->enviar(
                        $conta['email'],
                        'Recuperação de senha - Mecanize',
                        '<p>Olá, ' . e($conta['nome']) . '.</p>'
                        . '<p>Use o link abaixo para criar uma nova senha. Ele vale por ' . Usuario::RESET_MINUTOS . ' minutos:</p>'
                        . '<p><a href="' . e($link) . '">' . e($link) . '</a></p>'
                        . '<p>Se você não pediu isso, ignore este e-mail.</p>'
                    );
                }
            } catch (Throwable $e) {
                error_log('[recuperar senha] ' . $e->getMessage());
            }
        }

        // Mesma resposta exista o e-mail ou não.
        $this->tela('auth/recuperar', [
            'mensagem' => 'Se o e-mail estiver cadastrado, você vai receber um link para criar uma nova senha.',
        ], 'auth');
    }

    public function redefinirSenha(): void
    {
        $token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
        $conta = (new Usuario())->porTokenRecuperacao($token);

        if (!$conta) {
            $this->tela('auth/redefinir', ['invalido' => true], 'auth');
            return;
        }

        if (!$this->ehPost()) {
            $this->tela('auth/redefinir', ['token' => $token], 'auth');
            return;
        }

        $v = (new Validator($_POST))
            ->required('nova_senha', 'Nova senha')
            ->min('nova_senha', 6, 'Nova senha')
            ->matches('nova_senha', 'confirmar_senha', 'Confirmação de senha');

        if ($v->fails()) {
            $this->tela('auth/redefinir', ['token' => $token, 'erro' => $v->firstError()], 'auth');
            return;
        }

        (new Usuario())->redefinirSenha((int) $conta['id'], (string) $_POST['nova_senha']);
        $this->redirecionar('login', ['senhaRedefinida' => 1]);
    }

    private function telaLogin(string $erro, string $email): void
    {
        $this->tela('auth/login', ['erro' => $erro, 'email' => $email, 'retorno' => (string) ($_POST['retorno'] ?? '')], null);
    }

    /**
     * Só volta para uma rota registrada e permitida ao perfil — sem isso o ?retorno= viraria um
     * redirecionamento para qualquer site.
     */
    private function destinoSeguro(string $retorno, string $perfil): string
    {
        $rotas = require V2_RAIZ . '/config/rotas.php';
        $rota  = explode('?', trim($retorno, '/'), 2)[0];

        if ($rota !== '' && isset($rotas[$rota]) && Permissao::permite($perfil, $rotas[$rota][2]) && $rotas[$rota][2] !== Permissao::PUBLICO) {
            return $retorno;
        }

        return Permissao::inicio($perfil);
    }

    private function urlAbsoluta(string $caminho): string
    {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $base  = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');

        return $base . '/' . ltrim($caminho, '/');
    }
}
