<?php

require_once __DIR__ . '/../core/auth.php';

class CaixaController extends Controller
{
    /**
     * Abertura de caixa.
     * GET  → exibe o formulário (se já houver caixa aberto, volta ao início).
     * POST → registra a abertura com o usuário logado.
     */
    public function abrir()
    {
        $caixa = $this->model('Caixa');

        $caixaAberto = $caixa->getCaixaAberto();

        // Saldo do caixa anterior (fechamento da última sessão) — sugerido
        // como valor de abertura desta sessão.
        $saldoAnterior = $caixa->getUltimoFechamento();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Já existe caixa aberto: não abre outro
            if ($caixaAberto) {
                header('Location: ./home');
                exit;
            }

            $usuarioId = $_SESSION['user_id_usuario'] ?? null;

            if (!$usuarioId) {
                header('Location: ./logout');
                exit;
            }

            $validator = (new Validator($_POST))->required('valor_abertura', 'Valor de abertura');

            if ($validator->fails()) {
                $this->view('Caixa/abrirCaixa', [
                    'error'         => $validator->firstError(),
                    'old'           => $_POST,
                    'saldoAnterior' => $saldoAnterior,
                ]);
                return;
            }

            $sucesso = $caixa->abrir(
                $usuarioId,
                $_POST['valor_abertura'] ?? 0,
                $_POST['observacao'] ?? null
            );

            if ($sucesso) {
                header('Location: ./home');
                exit;
            }

            $this->view('Caixa/abrirCaixa', [
                'error'         => 'Não foi possível abrir o caixa.',
                'old'           => $_POST,
                'saldoAnterior' => $saldoAnterior,
            ]);
            return;
        }

        // Caixa já aberto: não há o que abrir
        if ($caixaAberto) {
            header('Location: ./home');
            exit;
        }

        $this->view('Caixa/abrirCaixa', [
            'saldoAnterior' => $saldoAnterior,
        ]);
    }

    /**
     * Fechamento de caixa.
     * GET  → exibe a confirmação com os dados do caixa aberto.
     * POST → registra o fechamento com o usuário logado.
     */
    public function fechar()
    {
        $caixa = $this->model('Caixa');

        $caixaAberto = $caixa->getCaixaAberto();

        // Não há caixa aberto: nada a fechar
        if (!$caixaAberto) {
            header('Location: ./home');
            exit;
        }

        // Resumo da sessão (entradas/saídas e dinheiro apurado na gaveta).
        $resumo = $this->model('MovimentacaoCaixa')->resumoSessao($caixaAberto['id_caixa']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuarioId = $_SESSION['user_id_usuario'] ?? null;

            if (!$usuarioId) {
                header('Location: ./logout');
                exit;
            }

            $validator = (new Validator($_POST))->required('valor_fechamento', 'Valor de fechamento');

            if ($validator->fails()) {
                $this->view('Caixa/fecharCaixa', [
                    'caixa'  => $caixaAberto,
                    'resumo' => $resumo,
                    'error'  => $validator->firstError(),
                    'old'    => $_POST,
                ]);
                return;
            }

            $sucesso = $caixa->fechar(
                $usuarioId,
                $_POST['valor_fechamento'] ?? 0,
                $_POST['observacao'] ?? null,
                $resumo['caixa_atual'] ?? null
            );

            if ($sucesso) {
                // Sem caixa aberto, o usuário cai na tela de abertura (encerrar o dia)
                header('Location: ./listCaixa');
                exit;
            }

            $this->view('Caixa/fecharCaixa', [
                'caixa'  => $caixaAberto,
                'resumo' => $resumo,
                'error'  => 'Não foi possível fechar o caixa.',
                'old'    => $_POST,
            ]);
            return;
        }

        $this->view('Caixa/fecharCaixa', [
            'caixa'  => $caixaAberto,
            'resumo' => $resumo,
        ]);
    }

    /**
     * Histórico de aberturas/fechamentos de caixa.
     */
    public function list()
    {
        $caixa = $this->model('Caixa');

        $caixaAberto = $caixa->getCaixaAberto();

        // Resumo (com o caixa atual) só faz sentido quando há sessão aberta.
        $resumoAberto = null;
        if ($caixaAberto) {
            $resumoAberto = $this->model('MovimentacaoCaixa')->resumoSessao($caixaAberto['id_caixa']);
        }

        $caixas = $caixa->historico();

        // Atendentes só podem ver as sessões de caixa em que participaram —
        // ou seja, que eles mesmos abriram ou fecharam. Administradores e
        // técnicos veem o histórico todo. Enforced aqui (não na view) para
        // não depender da tela respeitar a regra.
        if (($_SESSION['user_tipo'] ?? null) === 'ATENDENTE') {
            $idUsuario = (int) ($_SESSION['user_id_usuario'] ?? 0);

            $caixas = array_values(array_filter($caixas, function ($c) use ($idUsuario) {
                return (int) ($c['usuario_abertura_id'] ?? 0) === $idUsuario
                    || (int) ($c['usuario_fechamento_id'] ?? 0) === $idUsuario;
            }));
        }

        $this->view('Caixa/listCaixa', [
            'caixas'       => $caixas,
            'caixaAberto'  => $caixaAberto,
            'resumoAberto' => $resumoAberto,
        ]);
    }

    /**
     * Tela de lançamentos do livro-caixa (entradas/saídas da sessão aberta).
     * Exige um caixa ABERTO — sem ele, não há o que lançar.
     */
    public function movimentacoes()
    {
        $caixa = $this->model('Caixa');

        $caixaAberto = $caixa->getCaixaAberto();

        if (!$caixaAberto) {
            header('Location: ./abrirCaixa');
            exit;
        }

        $mov = $this->model('MovimentacaoCaixa');

        $this->view('Caixa/movimentacoesCaixa', [
            'caixa'         => $caixaAberto,
            'resumo'        => $mov->resumoSessao($caixaAberto['id_caixa']),
            'movimentacoes' => $mov->porSessao($caixaAberto['id_caixa']),
            'ok'            => isset($_GET['ok']),
            'error'         => isset($_GET['erro'])
                ? 'Não foi possível registrar a movimentação. Confira o tipo, a forma/categoria e um valor maior que zero.'
                : null,
        ]);
    }

    /**
     * Relatório do livro-caixa por período (diário/semanal/mensal/anual).
     *
     * Pedido do cliente registrado em ERROS.MD: "quero saber o caixa de entrada
     * e saída diário, semanal, mensal e anual. entradas e suas formas de
     * pagamento... a saída, retirada para banco, retirada para peças etc".
     *
     * Restrito a ADMIN: é a visão financeira consolidada da oficina, não a
     * operação do dia (essa é a tela de lançamentos, aberta a quem opera o caixa).
     */
    public function relatorio()
    {
        $this->requireRole(['ADMIN']);

        $periodo = $this->resolverPeriodo();
        $mov     = $this->model('MovimentacaoCaixa');

        $this->view('Caixa/relatorioCaixa', [
            'periodo'      => $periodo,
            'resumo'       => $mov->resumoPeriodo($periodo['inicio'], $periodo['fim']),
            'serie'        => $mov->seriePeriodo($periodo['inicio'], $periodo['fim'], $periodo['granularidade']),
            'lancamentos'  => $mov->lancamentosPeriodo($periodo['inicio'], $periodo['fim']),
        ]);
    }

    /**
     * Mesmo relatório em PDF (para arquivar/enviar ao contador).
     */
    public function exportarRelatorioPdf()
    {
        $this->requireRole(['ADMIN']);

        $periodo = $this->resolverPeriodo();
        $mov     = $this->model('MovimentacaoCaixa');

        $dados = [
            'periodo'     => $periodo,
            'resumo'      => $mov->resumoPeriodo($periodo['inicio'], $periodo['fim']),
            'lancamentos' => $mov->lancamentosPeriodo($periodo['inicio'], $periodo['fim']),
        ];

        // Renderiza a view de PDF em buffer e entrega ao Dompdf.
        extract($dados);
        ob_start();
        require __DIR__ . '/../views/Caixa/pdfRelatorioCaixa.php';
        $html = ob_get_clean();

        require_once __DIR__ . '/../core/Pdf.php';
        Pdf::baixar($html, 'caixa_' . $periodo['inicio'] . '_a_' . $periodo['fim'] . '.pdf');
    }

    /**
     * Traduz ?periodo=dia|semana|mes|ano|custom (+ ?inicio/&fim) num intervalo
     * concreto. Qualquer valor fora da lista cai em 'mes' — o intervalo nunca
     * chega ao SQL vindo direto do usuário, sempre reconstruído por date().
     */
    private function resolverPeriodo(): array
    {
        $tipo = $_GET['periodo'] ?? 'mes';
        $hoje = date('Y-m-d');

        switch ($tipo) {
            case 'dia':
                $inicio = $fim = $hoje;
                $rotulo = 'Hoje — ' . date('d/m/Y');
                $gran   = 'dia';
                break;

            case 'semana':
                // Semana corrente começando na segunda-feira (date('N'): 1=seg).
                $diaSemana = (int) date('N');
                $inicio    = date('Y-m-d', strtotime("-" . ($diaSemana - 1) . " days"));
                $fim       = date('Y-m-d', strtotime($inicio . ' +6 days'));
                $rotulo    = 'Semana de ' . date('d/m', strtotime($inicio)) . ' a ' . date('d/m/Y', strtotime($fim));
                $gran      = 'dia';
                break;

            case 'ano':
                $inicio = date('Y-01-01');
                $fim    = date('Y-12-31');
                $rotulo = 'Ano de ' . date('Y');
                $gran   = 'mes';
                break;

            case 'custom':
                // Datas livres: valida o formato e ordena; formato inválido volta pro mês.
                $ini = $_GET['inicio'] ?? '';
                $f   = $_GET['fim'] ?? '';

                if ($this->dataValida($ini) && $this->dataValida($f)) {
                    $inicio = min($ini, $f);
                    $fim    = max($ini, $f);
                    $rotulo = date('d/m/Y', strtotime($inicio)) . ' a ' . date('d/m/Y', strtotime($fim));
                    // Intervalo longo (> 92 dias) fica ilegível dia a dia no gráfico.
                    $gran   = (strtotime($fim) - strtotime($inicio)) > (92 * 86400) ? 'mes' : 'dia';
                    break;
                }
                $tipo = 'mes';
                // sem break: cai no mês corrente

            case 'mes':
            default:
                $tipo   = 'mes';
                $inicio = date('Y-m-01');
                $fim    = date('Y-m-t');
                $rotulo = 'Mês de ' . $this->nomeMes((int) date('m')) . '/' . date('Y');
                $gran   = 'dia';
                break;
        }

        return [
            'tipo'          => $tipo,
            'inicio'        => $inicio,
            'fim'           => $fim,
            'rotulo'        => $rotulo,
            'granularidade' => $gran,
        ];
    }

    /** Aceita apenas 'YYYY-MM-DD' que exista de fato no calendário. */
    private function dataValida($data): bool
    {
        if (!is_string($data) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            return false;
        }

        [$a, $m, $d] = explode('-', $data);

        return checkdate((int) $m, (int) $d, (int) $a);
    }

    private function nomeMes(int $mes): string
    {
        $meses = [
            1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
            'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
        ];

        return $meses[$mes] ?? '';
    }

    /**
     * Registra uma entrada/saída manual na sessão de caixa aberta (POST).
     * Padrão post/redirect/get: volta para a tela de lançamentos.
     */
    public function registrarMovimentacao()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ./caixaMovimentacoes');
            exit;
        }

        $caixa = $this->model('Caixa');

        $caixaAberto = $caixa->getCaixaAberto();

        if (!$caixaAberto) {
            header('Location: ./abrirCaixa');
            exit;
        }

        $usuarioId = $_SESSION['user_id_usuario'] ?? null;

        if (!$usuarioId) {
            header('Location: ./logout');
            exit;
        }

        // Carrega o model antes de usar suas constantes (não há autoloader).
        $mov = $this->model('MovimentacaoCaixa');

        $tipo  = strtoupper(trim($_POST['tipo'] ?? ''));
        $forma = strtoupper(trim($_POST['forma_pagamento'] ?? ''));
        $cat   = strtoupper(trim($_POST['categoria'] ?? ''));
        $valor = $_POST['valor'] ?? 0;
        $desc  = $_POST['descricao'] ?? null;

        // Monta/valida os campos conforme o tipo (case exato dos enums).
        if ($tipo === 'ENTRADA') {
            $cat = null;
            $valido = in_array($forma, MovimentacaoCaixa::FORMAS, true);
        } elseif ($tipo === 'SAIDA') {
            $forma = null;
            $valido = in_array($cat, MovimentacaoCaixa::CATEGORIAS_SAIDA, true);
        } else {
            $valido = false;
        }

        if (!$valido) {
            header('Location: ./caixaMovimentacoes?erro=1');
            exit;
        }

        // registrar() rejeita valor <= 0 (retorna false).
        $ok = $mov->registrar(
            $caixaAberto['id_caixa'],
            $tipo,
            $valor,
            $forma,
            $cat,
            $desc,
            $usuarioId,
            'MANUAL',
            null
        );

        header('Location: ./caixaMovimentacoes?' . ($ok ? 'ok=1' : 'erro=1'));
        exit;
    }
}
