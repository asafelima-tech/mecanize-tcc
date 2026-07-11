<?php

/**
 * Só modelosPorMarca()/criarModelo() são de verdade — são os únicos roteados
 * (config/routes.php), chamados via fetch() de dentro de saveVeiculo/editVeiculo.
 * Havia também list()/save()/edit()/delete() aqui, mas referenciavam models
 * ('ModeloVeiculo'/'MarcaVeiculo') que nunca existiram — código morto sem
 * rota nenhuma apontando pra ele, removido em vez de deixado pra explodir
 * com erro fatal no dia que alguém finalmente ligar uma rota esperando que
 * funcionasse.
 */
class ModeloVeiculoController extends Controller
{
    /**
     * Retorna modelos de uma marca em JSON.
     * Chamado via fetch() no saveVeiculo / editVeiculo.
     * GET ./modelosPorMarca?marca_id=X
     */
    public function modelosPorMarca()
    {
        while (ob_get_level()) ob_end_clean();

        $marca_id = $_GET['marca_id'] ?? null;

        header('Content-Type: application/json; charset=utf-8');

        if (!$marca_id) {
            echo json_encode([]);
            exit;
        }

        $veiculo = $this->model('Veiculo');
        $modelos = $veiculo->listarModelosPorMarca((int) $marca_id);

        echo json_encode($modelos);
        exit;
    }

    /**
     * Cria um modelo "na hora" para uma marca e devolve em JSON.
     * Usado no saveVeiculo / editVeiculo quando a marca não tem modelos.
     * POST ./criarModelo  (marca_id, nome)
     */
    public function criarModelo()
    {
        while (ob_get_level()) ob_end_clean();

        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['sucesso' => false, 'erro' => 'Método inválido.']);
            exit;
        }

        $marca_id = $_POST['marca_id'] ?? null;
        $nome     = $_POST['nome'] ?? '';

        if (!$marca_id || trim($nome) === '') {
            echo json_encode(['sucesso' => false, 'erro' => 'Informe a marca e o nome do modelo.']);
            exit;
        }

        $veiculo = $this->model('Veiculo');
        $modelo  = $veiculo->criarModelo((int) $marca_id, $nome);

        if (!$modelo) {
            echo json_encode(['sucesso' => false, 'erro' => 'Não foi possível criar o modelo.']);
            exit;
        }

        echo json_encode(['sucesso' => true, 'modelo' => $modelo]);
        exit;
    }
}
