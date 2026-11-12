<?php

/** Contas da equipe da oficina (só ADMIN) e o perfil de cada um (qualquer usuário logado). */
final class UsuariosController extends Cadastro
{
    public function lista(): void
    {
        $p = new Paginacao();
        $f = $this->filtros(['perfil']);
        $m = new Usuario();
        $this->tela('usuarios/lista', ['usuarios' => $m->listarDaOficina($f, $p), 'f' => $f, 'p' => $p, 'vagas' => $m->vagas(), 'resumo' => $m->resumo()]);
    }

    public function form(): void
    {
        $id = $this->idOpcional();
        $usuario = $id ? ((new Usuario())->buscarDaOficina($id) ?? throw new ErroHttp('registro-inexistente', ['voltar' => './usuarios']))
                       : ['perfil' => 'ATENDENTE', 'comissao_tipo' => 'NENHUMA'];
        $this->tela('usuarios/form', ['u' => $usuario, 'id' => $id]);
    }

    public function salvar(): void
    {
        $id = $this->idOpcional();
        $v = (new Validator($_POST))
            ->required('nome', 'Nome')->max('nome', 120, 'Nome')
            ->required('email', 'E-mail')->email('email', 'E-mail')
            ->celularBr('telefone', 'Telefone')
            ->in('perfil', array_keys(Usuario::PERFIS), 'Perfil');
        if ($id === null || ($_POST['senha'] ?? '') !== '') {
            $v->required('senha', 'Senha')->min('senha', 6, 'Senha');
        }

        if ($v->fails()) {
            $this->tela('usuarios/form', ['u' => $_POST, 'id' => $id, 'erro' => $v->firstError()]);
            return;
        }

        try {
            $id = (new Usuario())->salvarDaOficina($_POST, $id);
        } catch (DomainException $e) {
            $this->tela('usuarios/form', ['u' => $_POST, 'id' => $id, 'erro' => $e->getMessage()]);
            return;
        }

        if ($id === Contexto::usuarioId()) {
            Sessao::renovarCarimbo();
        }
        $this->avisar('Usuário salvo.');
        $this->redirecionar('usuarios');
    }

    public function ativo(): void
    {
        $id = $this->idDaUrl();
        try {
            (new Usuario())->definirAtivo($id, ($_POST['ativo'] ?? '') === '1');
            $this->avisar(($_POST['ativo'] ?? '') === '1' ? 'Usuário reativado.' : 'Usuário desativado. A sessão dele foi encerrada.');
        } catch (DomainException $e) {
            $this->avisar($e->getMessage(), 'erro');
        }
        $this->redirecionar('usuarios');
    }

    public function excluir(): void
    {
        try {
            (new Usuario())->remover($this->idDaUrl()) ?: throw new ErroHttp('registro-inexistente');
            $this->avisar('Usuário excluído.');
        } catch (DomainException $e) {
            $this->avisar($e->getMessage(), 'erro');
        }
        $this->redirecionar('usuarios');
    }

    /* ── Perfil: a própria conta ─────────────────────────────────────────── */

    public function perfil(): void
    {
        $this->tela('usuarios/perfil', ['u' => Contexto::usuario()]);
    }

    public function salvarPerfil(): void
    {
        $m = new Usuario();
        try {
            if (($_POST['acao'] ?? '') === 'senha') {
                $v = (new Validator($_POST))
                    ->required('senha_atual', 'Senha atual')
                    ->required('nova_senha', 'Nova senha')->min('nova_senha', 6, 'Nova senha')
                    ->matches('nova_senha', 'confirmar_senha', 'Confirmação');
                if ($v->fails()) {
                    throw new DomainException($v->firstError());
                }
                $m->trocarPropriaSenha((string) $_POST['senha_atual'], (string) $_POST['nova_senha']);
                $this->avisar('Senha alterada.');
            } else {
                $v = (new Validator($_POST))->required('nome', 'Nome')->max('nome', 120, 'Nome')->celularBr('telefone', 'Telefone');
                if ($v->fails()) {
                    throw new DomainException($v->firstError());
                }
                $m->atualizarProprioPerfil($this->post('nome'), $this->post('telefone'));
                $this->avisar('Dados atualizados.');
            }
            Sessao::renovarCarimbo();
        } catch (DomainException $e) {
            $this->avisar($e->getMessage(), 'erro');
        }
        $this->redirecionar('perfil');
    }
}
