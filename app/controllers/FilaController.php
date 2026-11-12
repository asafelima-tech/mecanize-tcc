<?php

require_once __DIR__ . '/../models/FilaOperacional.php';

/**
 * Fila operacional: o que atacar agora, em que ordem, e quem está livre.
 * Qualquer membro da equipe consulta e começa um item; só o administrador reorganiza.
 */
class FilaController extends Controller
{
    public function index()
    {
        $fila  = $this->model('FilaOperacional');
        $itens = $fila->itens();

        $acionaveis = array_values(array_filter($itens, fn($i) => $i['acionavel']));
        $andamento  = array_values(array_filter($itens, fn($i) => in_array($i['etapa'], ['EM_AVALIACAO', 'EM_EXECUCAO'], true)));
        $esperando  = array_values(array_filter($itens, fn($i) => !$i['acionavel'] && !in_array($i['etapa'], ['EM_AVALIACAO', 'EM_EXECUCAO'], true)));

        $this->view('Fila/filaOperacional', [
            'acionaveis' => $acionaveis,
            'andamento'  => $andamento,
            'esperando'  => $esperando,
            'tecnicos'   => $fila->tecnicos(),
            'capacidade' => FilaOperacional::CAPACIDADE_TECNICO,
            'ehAdmin'    => ($_SESSION['user_tipo'] ?? '') === 'ADMIN',
            'sucesso'    => $_GET['ok'] ?? null,
            'erro'       => $_GET['erro'] ?? null,
        ]);
    }

    /** Põe a ocorrência no topo (ou devolve à ordem automática). Só ADMIN; exige justificativa. */
    public function reordenar()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ./filaOperacional');
            exit;
        }

        $r = $this->model('FilaOperacional')->reordenar(
            (int) ($_POST['id_ocorrencia'] ?? 0),
            ($_POST['acao'] ?? 'topo') === 'topo',
            (string) ($_POST['justificativa'] ?? ''),
            (int) ($_SESSION['user_id_usuario'] ?? 0)
        );

        header('Location: ./filaOperacional?' . ($r['ok'] ? 'ok=reordenada' : 'erro=' . urlencode((string) $r['erro'])));
        exit;
    }
}