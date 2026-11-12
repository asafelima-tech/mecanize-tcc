<?php

require_once __DIR__ . '/../models/EstoqueConsulta.php';

/**
 * Consulta de estoque para a equipe: saldo, reservas e disponível — sem custo nem valor de
 * inventário (isso é o cadastro de produtos, só do administrador).
 */
class EstoqueController extends Controller
{
    public function consulta()
    {
        $model = new EstoqueConsulta();

        $busca     = trim((string) ($_GET['busca'] ?? ''));
        $categoria = (int) ($_GET['categoria'] ?? 0);
        $situacao  = (string) ($_GET['situacao'] ?? '');

        $todas = $model->listar(['busca' => $busca, 'categoria_id' => $categoria]);

        $contagem = ['OK' => 0, 'BAIXO' => 0, 'SEM_ESTOQUE' => 0];
        foreach ($todas as $l) {
            $contagem[$l['situacao']]++;
        }

        $linhas = in_array($situacao, ['SEM_ESTOQUE', 'BAIXO', 'OK'], true)
            ? array_values(array_filter($todas, fn($l) => $l['situacao'] === $situacao))
            : $todas;

        $this->view('Estoque/consultaEstoque', [
            'linhas'     => $linhas,
            'contagem'   => $contagem,
            'categorias' => $model->categorias(),
            'busca'      => $busca,
            'categoria'  => $categoria,
            'situacao'   => $situacao,
        ]);
    }
}
