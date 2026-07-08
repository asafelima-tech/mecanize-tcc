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

            $sucesso = $caixa->fechar(
                $usuarioId,
                $_POST['valor_fechamento'] ?? 0,
                $_POST['observacao'] ?? null
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
