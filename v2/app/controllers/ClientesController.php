<?php

final class ClientesController extends Cadastro
{
    public function lista(): void
    {
        $p = new Paginacao();
        $f = $this->filtros(['tipo', 'novos']);
        $m = new Cliente();
        $this->tela('clientes/lista', ['clientes' => $m->listar($f, $p), 'f' => $f, 'p' => $p, 'resumo' => $m->resumo(), 'recorrentes' => $m->recorrentes()]);
    }

    public function ver(): void
    {
        $m = new Cliente();
        $id = $this->idDaUrl();
        $cliente = $m->buscar($id) ?? throw new ErroHttp('registro-inexistente', ['voltar' => './clientes']);

        $this->tela('clientes/ver', ['c' => $cliente, 'veiculos' => $m->veiculos($id), 'historico' => $m->historico($id)]);
    }

    public function form(): void
    {
        $id = $this->idOpcional();
        $cliente = $id ? ((new Cliente())->buscar($id) ?? throw new ErroHttp('registro-inexistente')) : ['tipo_pessoa' => 'PF', 'aceita_contato' => 1];
        $this->tela('clientes/form', ['c' => $cliente, 'id' => $id]);
    }

    public function salvar(): void
    {
        $id = $this->idOpcional();
        $_POST['documento'] = trim((string) ($_POST['documento'] ?? ''));
        $v = (new Validator($_POST))
            ->required('nome', 'Nome')->max('nome', 150, 'Nome')
            ->in('tipo_pessoa', ['PF', 'PJ'], 'Tipo de pessoa')
            ->cpfCnpj('documento', ($_POST['tipo_pessoa'] ?? '') === 'PJ' ? 'CNPJ' : 'CPF')
            ->email('email', 'E-mail')
            ->celularBr('telefone', 'Telefone');
        $digitos = so_digitos($_POST['documento']);
        if ($digitos !== '' && strlen($digitos) !== (($_POST['tipo_pessoa'] ?? 'PF') === 'PJ' ? 14 : 11)) {
            $v->custom('documento', fn() => false, ($_POST['tipo_pessoa'] ?? '') === 'PJ' ? 'Para pessoa jurídica informe um CNPJ.' : 'Para pessoa física informe um CPF.');
        }

        if ($v->fails()) {
            $this->tela('clientes/form', ['c' => $_POST, 'id' => $id, 'erro' => $v->firstError()]);
            return;
        }

        try {
            $id = (new Cliente())->salvar($_POST, $id);
        } catch (DomainException $e) {
            $this->tela('clientes/form', ['c' => $_POST, 'id' => $id, 'erro' => $e->getMessage()]);
            return;
        }

        $this->avisar('Cliente salvo.');
        $this->redirecionar('cliente', ['id' => $id]);
    }

    public function ativo(): void
    {
        $this->alternarAtivo(new Cliente(), 'cliente', 'Cliente');
    }

    public function excluir(): void
    {
        $this->removerRegistro(new Cliente(), 'cliente', 'clientes', 'Cliente');
    }
}
