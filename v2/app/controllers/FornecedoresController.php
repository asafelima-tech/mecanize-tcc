<?php

final class FornecedoresController extends Cadastro
{
    public function lista(): void
    {
        $p = new Paginacao();
        $f = $this->filtros();
        $m = new Fornecedor();
        $this->tela('fornecedores/lista', ['fornecedores' => $m->listar($f, $p), 'f' => $f, 'p' => $p, 'resumo' => $m->resumo(), 'entradas' => $m->ultimasEntradas()]);
    }

    public function ver(): void
    {
        $fornecedor = (new Fornecedor())->buscar($this->idDaUrl()) ?? throw new ErroHttp('registro-inexistente', ['voltar' => './fornecedores']);
        $this->tela('fornecedores/ver', ['fo' => $fornecedor]);
    }

    public function form(): void
    {
        $id = $this->idOpcional();
        $fornecedor = $id ? ((new Fornecedor())->buscar($id) ?? throw new ErroHttp('registro-inexistente')) : [];
        $this->tela('fornecedores/form', ['fo' => $fornecedor, 'id' => $id]);
    }

    public function salvar(): void
    {
        $id = $this->idOpcional();
        $v = (new Validator($_POST))
            ->required('nome', 'Nome')->max('nome', 150, 'Nome')
            ->cpfCnpj('documento', 'CPF/CNPJ')
            ->email('email', 'E-mail')
            ->celularBr('telefone', 'Telefone');

        if ($v->fails()) {
            $this->tela('fornecedores/form', ['fo' => $_POST, 'id' => $id, 'erro' => $v->firstError()]);
            return;
        }

        try {
            $id = (new Fornecedor())->salvar($_POST, $id);
        } catch (DomainException $e) {
            $this->tela('fornecedores/form', ['fo' => $_POST, 'id' => $id, 'erro' => $e->getMessage()]);
            return;
        }

        $this->avisar('Fornecedor salvo.');
        $this->redirecionar('fornecedor', ['id' => $id]);
    }

    public function ativo(): void
    {
        $this->alternarAtivo(new Fornecedor(), 'fornecedor', 'Fornecedor');
    }

    public function excluir(): void
    {
        $this->removerRegistro(new Fornecedor(), 'fornecedor', 'fornecedores', 'Fornecedor');
    }
}
