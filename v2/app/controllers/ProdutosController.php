<?php

/** Peças e serviços. Consulta: toda a equipe (sem preço de custo). Cadastro e edição: só ADMIN. */
final class ProdutosController extends Cadastro
{
    public function lista(): void
    {
        $p = new Paginacao();
        $f = $this->filtros(['tipo', 'categoria', 'baixo']);
        $m = new Produto();
        $this->tela('produtos/lista', ['produtos' => $m->listar($f, $p), 'f' => $f, 'p' => $p, 'categorias' => $m->categorias()]);
    }

    public function ver(): void
    {
        $m = new Produto();
        $id = $this->idDaUrl();
        $produto = $m->buscar($id) ?? throw new ErroHttp('registro-inexistente', ['voltar' => './produtos']);

        $this->tela('produtos/ver', ['pr' => $produto, 'movimentos' => $produto['tipo'] === 'PECA' ? $m->movimentacoes($id) : []]);
    }

    public function form(): void
    {
        $id = $this->idOpcional();
        $m = new Produto();
        $produto = $id ? ($m->buscar($id) ?? throw new ErroHttp('registro-inexistente')) : ['tipo' => ($_GET['tipo'] ?? '') === 'SERVICO' ? 'SERVICO' : 'PECA'];
        $this->tela('produtos/form', ['pr' => $produto, 'id' => $id, 'categorias' => $m->categorias()]);
    }

    public function salvar(): void
    {
        $id = $this->idOpcional();
        $m = new Produto();
        // Número não negativo, com vírgula ou ponto (ex.: 1.234,56 ou 1234.56).
        $numero = fn($c) => fn($v) => $v === null || $v === '' || (bool) preg_match('/^\d{1,9}([.,]\d{1,3})?$/', str_replace('.', '', (string) $v)) || (is_numeric($v) && $v >= 0);

        $v = (new Validator($_POST))
            ->required('nome', 'Nome')->max('nome', 150, 'Nome')
            ->required('preco_venda', 'Preço de venda')
            ->custom('preco_venda', $numero('preco_venda'), 'Preço de venda inválido.')
            ->custom('preco_custo', $numero('preco_custo'), 'Preço de custo inválido.')
            ->custom('estoque_minimo', $numero('estoque_minimo'), 'Estoque mínimo inválido.')
            ->custom('estoque_inicial', $numero('estoque_inicial'), 'Estoque inicial inválido.');

        if ($v->fails()) {
            $this->tela('produtos/form', ['pr' => $_POST + ['tipo' => $id ? $m->buscar($id)['tipo'] : 'PECA'], 'id' => $id, 'categorias' => $m->categorias(), 'erro' => $v->firstError()]);
            return;
        }

        try {
            $id = $m->salvar($_POST, $id);
        } catch (DomainException $e) {
            $this->tela('produtos/form', ['pr' => $_POST, 'id' => $id, 'categorias' => $m->categorias(), 'erro' => $e->getMessage()]);
            return;
        }

        $this->avisar('Item salvo.');
        $this->redirecionar('produto', ['id' => $id]);
    }

    public function ativo(): void
    {
        $this->alternarAtivo(new Produto(), 'produto', 'Item');
    }

    public function excluir(): void
    {
        $this->removerRegistro(new Produto(), 'produto', 'produtos', 'Item');
    }
}
