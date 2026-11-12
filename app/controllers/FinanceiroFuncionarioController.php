<?php

/**
 * Financeiro/Comissões dos Funcionários — relatório por período (mês/ano)
 * e fechamento de pagamento. ADMIN só: é a mesma informação financeira que
 * já é restrita em Caixa/Relatórios.
 */
class FinanceiroFuncionarioController extends Controller
{
    private function periodo(): array
    {
        $mes = (int) ($_GET['mes'] ?? $_POST['mes'] ?? date('n'));
        $ano = (int) ($_GET['ano'] ?? $_POST['ano'] ?? date('Y'));

        if ($mes < 1 || $mes > 12) {
            $mes = (int) date('n');
        }

        $inicio = sprintf('%04d-%02d-01', $ano, $mes);
        $fim    = date('Y-m-t', strtotime($inicio));

        return [$mes, $ano, $inicio, $fim];
    }

    public function comissoes()
    {
        $this->requireRole(Permissao::PAPEIS_ADMIN);

        $remuneracao = $this->model('TecnicoRemuneracao');
        $tecnicos    = $remuneracao->listarTecnicos();

        [$mes, $ano, $inicio, $fim] = $this->periodo();

        $tecnicoId = (int) ($_GET['tecnico_id'] ?? 0);
        if ($tecnicoId <= 0 && !empty($tecnicos)) {
            $tecnicoId = (int) $tecnicos[0]['id_usuario'];
        }

        $resumo       = $tecnicoId > 0 ? $remuneracao->resumoTecnico($tecnicoId, $inicio, $fim) : null;
        $fechamentos  = $tecnicoId > 0 ? $remuneracao->listarFechamentos($tecnicoId) : [];

        $this->view('Financeiro/comissoesFuncionarios', [
            'tecnicos'           => $tecnicos,
            'tecnicoSelecionado' => $tecnicoId,
            'mes'                => $mes,
            'ano'                => $ano,
            'periodoInicio'      => $inicio,
            'periodoFim'         => $fim,
            'resumo'             => $resumo,
            'fechamentos'        => $fechamentos,
            'erro'               => $_GET['erro'] ?? null,
            'sucesso'            => $_GET['ok'] ?? null,
        ]);
    }

    public function pagar()
    {
        $this->requireRole(Permissao::PAPEIS_ADMIN);

        $tecnicoId = (int) ($_POST['tecnico_id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $tecnicoId <= 0) {
            header('Location: ./comissoesFuncionarios');
            exit;
        }

        [$mes, $ano, $inicio, $fim] = $this->periodo();

        $remuneracao = $this->model('TecnicoRemuneracao');
        $resultado   = $remuneracao->pagar(
            $tecnicoId,
            $inicio,
            $fim,
            (string) ($_POST['forma_pagamento'] ?? ''),
            $_POST['observacoes'] ?? null,
            (int) ($_SESSION['user_id_usuario'] ?? 0)
        );

        $destino = './comissoesFuncionarios?tecnico_id=' . $tecnicoId . '&mes=' . $mes . '&ano=' . $ano
            . ($resultado === true ? '&ok=pago' : '&erro=' . urlencode((string) $resultado));

        header('Location: ' . $destino);
        exit;
    }

    public function ajustar()
    {
        $this->requireRole(Permissao::PAPEIS_ADMIN);

        $maoObraId = (int) ($_POST['id_mao_obra'] ?? 0);
        $tecnicoId = (int) ($_POST['tecnico_id'] ?? 0);
        $mes       = (int) ($_POST['mes'] ?? date('n'));
        $ano       = (int) ($_POST['ano'] ?? date('Y'));

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $maoObraId <= 0) {
            header('Location: ./comissoesFuncionarios');
            exit;
        }

        $novoValor = (float) str_replace(',', '.', (string) ($_POST['novo_valor'] ?? ''));

        $remuneracao = $this->model('TecnicoRemuneracao');
        $resultado   = $remuneracao->ajustarValor($maoObraId, $novoValor, (string) ($_POST['observacao_ajuste'] ?? ''));

        $destino = './comissoesFuncionarios?tecnico_id=' . $tecnicoId . '&mes=' . $mes . '&ano=' . $ano
            . ($resultado === true ? '&ok=ajustado' : '&erro=' . urlencode((string) $resultado));

        header('Location: ' . $destino);
        exit;
    }
}
