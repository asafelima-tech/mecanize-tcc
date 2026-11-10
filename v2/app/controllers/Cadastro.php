<?php

/**
 * Base dos controllers de cadastro (clientes, veículos, produtos, fornecedores, usuários): o mesmo
 * fluxo de ativar/desativar/excluir e o tratamento de erros de formulário em um lugar só.
 */
abstract class Cadastro extends Controller
{
    /** Filtros comuns das listas (?busca=&status=). */
    protected function filtros(array $extras = []): array
    {
        $f = ['busca' => trim((string) ($_GET['busca'] ?? '')), 'status' => (string) ($_GET['status'] ?? 'ativos')];
        foreach ($extras as $k) {
            $f[$k] = trim((string) ($_GET[$k] ?? ''));
        }
        if (!in_array($f['status'], ['ativos', 'inativos', 'todos'], true)) {
            $f['status'] = 'ativos';
        }

        return $f;
    }

    /** Ativa/desativa e volta para a tela de onde veio. */
    protected function alternarAtivo(object $modelo, string $rotaVer, string $nome): void
    {
        $id = $this->idDaUrl();
        $ativar = ($_POST['ativo'] ?? '') === '1';
        try {
            $modelo->definirAtivo($id, $ativar);
            $this->avisar($nome . ($ativar ? ' reativado.' : ' desativado.'));
        } catch (DomainException $e) {
            $this->avisar($e->getMessage(), 'erro');
        }
        $this->redirecionar($rotaVer, ['id' => $id]);
    }

    protected function removerRegistro(object $modelo, string $rotaVer, string $rotaLista, string $nome): void
    {
        $id = $this->idDaUrl();
        try {
            if (!$modelo->remover($id)) {
                throw new ErroHttp('registro-inexistente');
            }
            $this->avisar($nome . ' excluído.');
            $this->redirecionar($rotaLista);
        } catch (DomainException $e) {
            $this->avisar($e->getMessage(), 'erro');
            $this->redirecionar($rotaVer, ['id' => $id]);
        }
    }

    /** id opcional (?id=) para formulários de criar/editar. */
    protected function idOpcional(): ?int
    {
        $v = $_GET['id'] ?? $_POST['id'] ?? '';

        return $v === '' ? null : $this->idDaUrl();
    }
}
