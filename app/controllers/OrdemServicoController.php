<?php

require_once __DIR__ . '/../core/Dinheiro.php';

require_once __DIR__ . '/../models/OrdemServicoFluxo.php';
require_once __DIR__ . '/../core/Historico.php';
require_once __DIR__ . '/../models/AutorizacaoCliente.php';

class OrdemServicoController extends Controller
{
    private function fluxo(): OrdemServicoFluxo
    {
        return new OrdemServicoFluxo();
    }

    private function usuarioId(): int
    {
        return (int) ($_SESSION['user_id_usuario'] ?? 0);
    }

    private function papel(): string
    {
        return (string) ($_SESSION['user_tipo'] ?? '');
    }

    /** Só POST: ação que muda dado nunca roda por GET (link, prefetch, favorito). */
    private function exigirPost(string $voltar = './listOrdemServico'): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ' . $voltar);
            exit;
        }
    }

    /**
     * Para onde voltar depois de uma ação. A tela de origem manda `voltar`
     * (lista | view | tecnico); qualquer outro valor cai na lista — nunca um
     * endereço vindo da requisição.
     */
    private function destino(int $id, string $query = ''): string
    {
        switch ($_POST['voltar'] ?? 'lista') {
            case 'view':
                $base = './viewOrdemServico?id=' . $id;
                break;
            case 'tecnico':
                $base = './viewOSTecnico?id=' . $id;
                break;
            default:
                return './listOrdemServico' . ($query !== '' ? '?' . $query : '');
        }

        return $base . ($query !== '' ? '&' . $query : '');
    }

    /** ok=<codigo> ou erro=fluxo&detalhe=<texto> — as telas mostram a mensagem (escapada). */
    private function resultado(array $r, string $codigoOk): string
    {
        return $r['ok']
            ? 'ok=' . $codigoOk
            : 'erro=fluxo&detalhe=' . urlencode((string) $r['erro']);
    }

    public function list()
    {
        $ordem = $this->model('OrdemServico');

        // getResumo() já devolve 'ordens', 'ordensConcluidas' e 'ordensArquivadas'
        $dados = $ordem->getResumo($_GET);

        $this->view('Ordens_Servico/listOrdemServico', $dados);
    }

    /**
     * Quadro Kanban, uma coluna por etapa do fluxo, alternativa visual à
     * listagem em tabela de ./listOrdemServico.
     */
    public function kanban()
    {
        $ordem = $this->model('OrdemServico');

        $colunas = [];
        $listar  = [
            'ABERTA'                 => fn() => $ordem->listarAbertas($_GET),
            'EM_ANDAMENTO'           => fn() => $ordem->listarEmAndamento($_GET),
            'AGUARDANDO_PECA'        => fn() => $ordem->listarAguardandoPeca($_GET),
            'AGUARDANDO_CONFERENCIA' => fn() => $ordem->listarAguardandoConferencia($_GET),
            'AGUARDANDO_RETIRADA'    => fn() => $ordem->listarAguardandoRetirada($_GET),
            'CONCLUIDA'              => fn() => $ordem->listarConcluidas($_GET),
        ];

        foreach ($listar as $status => $buscar) {
            $colunas[$status] = ['label' => OrdemServico::rotuloStatus($status), 'ordens' => $buscar()];
        }

        $this->view('Ordens_Servico/kanbanOrdemServico', ['colunas' => $colunas]);
    }

    /**
     * Move a OS pra outra coluna do Kanban (drag-and-drop). Chamado via
     * fetch() — responde JSON, não redireciona.
     *
     * Arrastar é só outro jeito de pedir uma transição: passa pelo mesmo
     * OrdemServicoFluxo dos botões, com as mesmas regras, papéis e efeitos.
     * Pular etapa (ABERTA → entregue), reabrir OS já recebida ou levar uma OS
     * direto a "concluída" sem os efeitos dela deixou de ser possível.
     */
    public function moverStatus()
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
            exit;
        }

        $id     = (int) ($_POST['id_ordem_servico'] ?? 0);
        $status = strtoupper(trim($_POST['status'] ?? ''));

        if (!$id || !in_array($status, OrdemServico::STATUS_KANBAN, true)) {
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos.']);
            exit;
        }

        // Cancelar e devolver à execução exigem um motivo: têm tela própria.
        $r = $this->fluxo()->transicionar($id, $status, $this->usuarioId(), $this->papel());

        echo json_encode(['sucesso' => $r['ok'], 'erro' => $r['erro']]);
        exit;
    }

    public function visualizar()
    {
        $id = (int) ($_GET['id'] ?? 0);

        if (!$id) {
            header('Location: ./listOrdemServico');
            exit;
        }

        $ordemServico = $this->model('OrdemServico');
        $ordem        = $ordemServico->getById($id);

        if (!$ordem) {
            $this->naoEncontrado('Ordem de serviço', './listOrdemServico');
        }

        $tokenRastreio = $ordemServico->getOuCriarToken($id);
        $pagamento     = $this->model('OrdemServicoPagamento');

        $this->view('Ordens_Servico/viewOrdemServico', [
            'ordem_servico' => $ordem,
            'itens_os'      => $ordemServico->getItensByOS($id, true),
            'tecnicos_os'   => $ordemServico->getTecnicosDaOS($id),
            'linkRastreio'  => $this->urlAbsoluta('rastrear?token=' . $tokenRastreio),
            'historico'     => Historico::da($ordemServico->conn, 'OS', $id),
            'autorizacoes'  => $this->model('AutorizacaoCliente')->listar($ordem['ocorrencia_id'] ?? null, $id),
            'canaisAutorizacao' => AutorizacaoCliente::CANAIS,
            'bloqueiosAutorizacao' => $this->model('AutorizacaoCliente')->bloqueiosDaOs($id),
            'parcelas'      => $pagamento->parcelas($id),
            'resumoCobranca' => $pagamento->resumo($id),
            'destinosPermitidos' => OrdemServicoFluxo::destinosPermitidos((string) $ordem['status'], $this->papel()),
            'erro'          => $_GET['erro'] ?? null,
            'detalhe'       => $_GET['detalhe'] ?? null,
            'sucesso'       => $_GET['ok'] ?? null,
        ]);
    }

    /**
     * Gera e baixa a Ordem de Serviço em PDF de verdade (via Dompdf).
     */
    public function exportarPdf()
    {
        $id = $_GET['id'] ?? null;

        if (!$id) {
            header('Location: ./listOrdemServico');
            exit;
        }

        $ordemServico = $this->model('OrdemServico');
        $os           = $ordemServico->getById($id);

        if (!$os) {
            $this->naoEncontrado('Ordem de serviço', './listOrdemServico');
        }

        $itens = $ordemServico->getItensByOS($id);

        require_once __DIR__ . '/../core/Pdf.php';

        ob_start();
        require __DIR__ . '/../views/Ordens_Servico/pdfOrdemServico.php';
        $html = ob_get_clean();

        $numero = preg_replace('/[^A-Za-z0-9_-]/', '_', $os['numero_os'] ?? (string) $os['id_ordem_servico']);

        Pdf::baixar($html, "os_{$numero}.pdf", empty($_GET['inline']));
    }

    public function save()
    {
        $ordemServico = $this->model('OrdemServico');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $validator = (new Validator($_POST))
                ->required('cliente_id', 'Cliente')
                ->required('veiculo_id', 'Veículo');

            if ($validator->fails()) {
                $dados          = $this->dadosFormularioOS($ordemServico);
                $dados['error'] = $validator->firstError();
                $dados['old']   = $_POST;
                $this->view('Ordens_Servico/saveOrdemServico', $dados);
                return;
            }

            $sucesso = $ordemServico->create($_POST);

            if ($sucesso) {
                // OS aberta a partir da agenda: marca o agendamento como
                // convertido. create() devolve o id da OS criada.
                if (!empty($_POST['agendamento_id'])) {
                    $this->model('Agendamento')
                        ->vincularOrdemServico((int) $_POST['agendamento_id'], (int) $sucesso);
                }

                header('Location: ./listOrdemServico');
                exit;
            }

            $erroCreate = $ordemServico->getLastError();

            $dados          = $this->dadosFormularioOS($ordemServico);
            $dados['error'] = $erroCreate !== ''
                ? 'Erro ao cadastrar ordem de serviço: ' . $erroCreate
                : 'Erro ao cadastrar ordem de serviço.';
            $dados['old']   = $_POST;

            $this->view('Ordens_Servico/saveOrdemServico', $dados);
            return;
        }

        $dados = $this->dadosFormularioOS($ordemServico);

        // Pré-preenchimento quando a OS vem de um agendamento (botão "Gerar OS").
        $dados['preCliente']     = $_GET['cliente_id']     ?? '';
        $dados['preVeiculo']     = $_GET['veiculo_id']     ?? '';
        $dados['preDefeito']     = $_GET['defeito']        ?? '';
        $dados['preAgendamento'] = $_GET['agendamento_id'] ?? '';

        $this->view('Ordens_Servico/saveOrdemServico', $dados);
    }

    public function edit()
    {
        $ordemServico = $this->model('OrdemServico');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id_ordem_servico'] ?? null;

            if (!$id) {
                header('Location: ./listOrdemServico');
                exit;
            }

            $validator = (new Validator($_POST))
                ->required('numero', 'Número da OS')
                ->required('cliente_id', 'Cliente')
                ->required('veiculo_id', 'Veículo');

            if ($validator->fails()) {
                $this->view('Ordens_Servico/editOrdemServico', $this->dadosEdicao($ordemServico, (int) $id, $validator->firstError()));
                return;
            }

            if (!$ordemServico->update($_POST)) {
                $erro = $ordemServico->getLastError();

                $this->view('Ordens_Servico/editOrdemServico', $this->dadosEdicao(
                    $ordemServico,
                    (int) $id,
                    $erro !== '' ? 'Erro ao salvar alterações: ' . $erro : 'Erro ao salvar alterações.'
                ));
                return;
            }

            // O formulário também permite alternar entre as etapas de execução.
            // Os dados já foram salvos; a mudança de etapa passa pelo fluxo e,
            // se for recusada, a tela avisa em vez de fingir que mudou.
            $novo  = strtoupper(trim((string) ($_POST['status'] ?? '')));
            $atual = $ordemServico->statusAtual((int) $id);

            if ($novo !== '' && $atual !== null && $novo !== $atual && in_array($novo, OrdemServico::STATUS_TECNICO, true)) {
                $r = $this->fluxo()->transicionar((int) $id, $novo, $this->usuarioId(), $this->papel());

                if (!$r['ok']) {
                    header('Location: ./listOrdemServico?erro=fluxo&detalhe=' . urlencode('Dados salvos, mas a etapa não mudou: ' . $r['erro']));
                    exit;
                }
            }

            header('Location: ./listOrdemServico');
            exit;
        }

        $id = $_GET['id'] ?? null;

        if (!$id) {
            header('Location: ./listOrdemServico');
            exit;
        }

        $ordem = $ordemServico->getById($id);

        if (!$ordem) {
            $this->naoEncontrado('Ordem de serviço', './listOrdemServico');
        }

        $this->view('Ordens_Servico/editOrdemServico', $this->dadosEdicao($ordemServico, (int) $id));
    }

    private function dadosEdicao($ordemServico, int $id, ?string $erro = null): array
    {
        $dados = $this->dadosFormularioOS($ordemServico);

        $dados['ordem_servico'] = array_merge($ordemServico->getById($id) ?: [], $erro !== null ? $_POST : []);
        $dados['itens_os']      = $ordemServico->getItensByOS($id);
        $dados['tecnicos_os']   = $ordemServico->getTecnicosDaOS($id);

        if ($erro !== null) {
            $dados['error'] = $erro;
            $dados['old']   = $_POST;
        }

        return $dados;
    }

    private function dadosFormularioOS($ordemServico)
    {
        return [
            'clientes'        => $ordemServico->getClientes(),
            'veiculos'        => $ordemServico->getVeiculos(),
            'mecanicos'       => $ordemServico->getMecanicos(),
            'produtos'        => $ordemServico->getProdutos(),
            'servicos'        => $ordemServico->getServicos(),
            'proximoNumeroOS' => $ordemServico->gerarNumeroOS(),
        ];
    }

    public function delete()
    {
        $this->exigirPost();

        $id = $_POST['id_ordem_servico'] ?? null;

        if (!$id) {
            header('Location: ./listOrdemServico');
            exit;
        }

        $ordemServico = $this->model('OrdemServico');

        // OS com agendamento, movimentação de estoque, lançamento de caixa ou
        // recebimento é história: não se apaga, se cancela. Antes isso virava
        // PDOException e tela 500; agora diz o que impede.
        $vinculos = $ordemServico->vinculos($id);

        if (!empty($vinculos)) {
            header('Location: ./listOrdemServico?erro=vinculos&detalhe=' . urlencode(implode(', ', $vinculos)));
            exit;
        }

        if (!$ordemServico->delete($id)) {
            header('Location: ./listOrdemServico?erro=exclusao');
            exit;
        }

        header('Location: ./listOrdemServico?ok=excluido');
        exit;
    }

    /* ── Etapas do ciclo de vida ──────────────────────────────────────────
     * Cada uma é uma transição do OrdemServicoFluxo: o controller só traduz o
     * formulário e a resposta. As regras (quem pode, de onde para onde, efeitos,
     * idempotência) moram lá, e valem igual para o botão, o Kanban e o técnico.
     */

    /** ADMIN confere o serviço do técnico e libera o veículo para retirada. */
    public function conferir()
    {
        $this->exigirPost();

        $id = (int) ($_POST['id_ordem_servico'] ?? 0);
        $r  = $this->fluxo()->transicionar($id, 'AGUARDANDO_RETIRADA', $this->usuarioId(), $this->papel());

        // O aviso de "veículo pronto" nasce na própria transição (OrdemServicoFluxo → Comunicacao).
        header('Location: ' . $this->destino($id, $this->resultado($r, $r['repetido'] ? 'jaconferida' : 'conferida')));
        exit;
    }

    /** ADMIN devolve a OS à execução, com o motivo, para o técnico corrigir. */
    public function reprovarConferencia()
    {
        $this->exigirPost();

        $id = (int) ($_POST['id_ordem_servico'] ?? 0);
        $r  = $this->fluxo()->transicionar($id, 'EM_ANDAMENTO', $this->usuarioId(), $this->papel(), [
            'motivo' => $_POST['motivo'] ?? '',
        ]);

        header('Location: ' . $this->destino($id, $this->resultado($r, 'devolvida')));
        exit;
    }

    /** Entrega do veículo ao cliente (assinatura e, se for o caso, recebimento no balcão). */
    public function entregar()
    {
        $this->exigirPost();

        $id = (int) ($_POST['id_ordem_servico'] ?? 0);
        $r  = $this->fluxo()->transicionar($id, 'CONCLUIDA', $this->usuarioId(), $this->papel(), [
            'assinatura_cliente' => $_POST['assinatura_cliente'] ?? null,
            'receber_forma'      => !empty($_POST['receber_agora']) ? (string) ($_POST['forma_pagamento'] ?? '') : '',
        ]);

        header('Location: ' . $this->destino($id, $this->resultado($r, $r['repetido'] ? 'jaentregue' : 'entregue')));
        exit;
    }

    public function cancelar()
    {
        $this->exigirPost();

        $id = (int) ($_POST['id_ordem_servico'] ?? 0);
        $r  = $this->fluxo()->transicionar($id, 'CANCELADA', $this->usuarioId(), $this->papel(), [
            'motivo' => $_POST['motivo'] ?? '',
        ]);

        header('Location: ' . $this->destino($id, $this->resultado($r, 'cancelada')));
        exit;
    }

    /* ── Autorização do cliente durante a execução ──────────────────────────
     * Quando o serviço cresce além do que o cliente autorizou, a equipe pede uma
     * autorização complementar; a OS não segue para conferência até a resposta.
     */

    public function solicitarComplemento()
    {
        $this->exigirPost();

        $osId = (int) ($_POST['id_ordem_servico'] ?? 0);
        $r = $this->model('AutorizacaoCliente')->solicitarComplemento(
            $osId,
            (float) str_replace(',', '.', (string) ($_POST['valor_adicional'] ?? 0)),
            (string) ($_POST['motivo'] ?? ''),
            $this->usuarioId()
        );

        header('Location: ' . $this->destino($osId, $this->resultado($r, 'complementosolicitado')));
        exit;
    }

    public function decidirAutorizacao()
    {
        $this->exigirPost();

        $osId = (int) ($_POST['id_ordem_servico'] ?? 0);
        $r = $this->model('AutorizacaoCliente')->decidir(
            (int) ($_POST['id_autorizacao'] ?? 0),
            ($_POST['decisao'] ?? '') === 'aprovar',
            (string) ($_POST['canal'] ?? ''),
            (string) ($_POST['evidencia'] ?? ''),
            $this->usuarioId()
        );

        header('Location: ' . $this->destino($osId, $this->resultado($r, 'complementodecidido')));
        exit;
    }

    /* ── Peças: consumo e devolução ───────────────────────────────────────── */

    /** Confirma que a peça lançada foi usada — é aqui, e só aqui, que o estoque baixa. */
    public function consumirPeca()
    {
        $this->exigirPost();

        $itemId = (int) ($_POST['id_item'] ?? 0);
        $osId   = (int) ($_POST['id_ordem_servico'] ?? 0);

        $r = ConsumoEstoque::consumir(new Database(), $itemId, $this->usuarioId());

        header('Location: ' . $this->destino($osId, $this->resultado($r, $r['repetido'] ? 'pecajaconsumida' : 'pecaconsumida')));
        exit;
    }

    /** Reserva a peça para a OS: tira a quantidade do disponível das outras OS, sem baixar o estoque. */
    public function reservarPeca()
    {
        $this->exigirPost();

        $itemId = (int) ($_POST['id_item'] ?? 0);
        $osId   = (int) ($_POST['id_ordem_servico'] ?? 0);

        $r = ConsumoEstoque::reservar(new Database(), $itemId, $this->usuarioId());

        header('Location: ' . $this->destino($osId, $this->resultado($r, $r['repetido'] ? 'pecajareservada' : 'pecareservada')));
        exit;
    }

    public function liberarReserva()
    {
        $this->exigirPost();

        $itemId = (int) ($_POST['id_item'] ?? 0);
        $osId   = (int) ($_POST['id_ordem_servico'] ?? 0);

        $r = ConsumoEstoque::liberarReserva(new Database(), $itemId, $this->usuarioId());

        header('Location: ' . $this->destino($osId, $this->resultado($r, 'reservaliberada')));
        exit;
    }

    public function devolverPeca()
    {
        $this->exigirPost();

        $itemId = (int) ($_POST['id_item'] ?? 0);
        $osId   = (int) ($_POST['id_ordem_servico'] ?? 0);

        $r = ConsumoEstoque::devolver(new Database(), $itemId, $this->usuarioId(), (string) ($_POST['justificativa'] ?? ''));

        if ($r['ok']) {
            $this->model('OrdemServico')->recalcularTotais($osId);
        }

        header('Location: ' . $this->destino($osId, $this->resultado($r, 'pecadevolvida')));
        exit;
    }

    /**
     * Tela do pagamento da OS: plano (à vista/parcelado) e situação das parcelas.
     */
    public function pagamento()
    {
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            header('Location: ./listOrdemServico');
            exit;
        }

        $ordemServico = $this->model('OrdemServico');
        $ordem        = $ordemServico->getById($id);

        if (!$ordem) {
            $this->naoEncontrado('Ordem de serviço', './listOrdemServico');
        }

        $pagamento = $this->model('OrdemServicoPagamento');

        $this->view('Ordens_Servico/pagamentoOrdemServico', [
            'os'       => $ordem,
            'plano'    => $pagamento->plano($id),
            'parcelas' => $pagamento->parcelas($id),
            'resumo'   => $pagamento->resumo($id),
            'erro'     => $_GET['erro'] ?? null,
            'sucesso'  => $_GET['ok'] ?? null,
        ]);
    }

    /** Grava o plano de pagamento e gera as parcelas. */
    public function salvarPagamento()
    {
        $id = (int) ($_POST['id_ordem_servico'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ./listOrdemServico');
            exit;
        }

        $pagamento = $this->model('OrdemServicoPagamento');
        $ok        = $pagamento->salvarPlano($id, $_POST);

        header('Location: ./pagamentoOrdemServico?id=' . $id . ($ok ? '&ok=plano' : '&erro=plano'));
        exit;
    }

    /** Estorno do recebimento de uma parcela (só administrador, com justificativa). */
    public function estornarParcela()
    {
        $id        = (int) ($_POST['id_ordem_servico'] ?? 0);
        $parcelaId = (int) ($_POST['id_parcela'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0 || $parcelaId <= 0) {
            header('Location: ./listOrdemServico');
            exit;
        }

        $r = $this->model('OrdemServicoPagamento')->estornarParcela($parcelaId, (string) ($_POST['justificativa'] ?? ''), $_SESSION['user_id_usuario'] ?? null);

        header('Location: ./pagamentoOrdemServico?id=' . $id . ($r['ok'] ? '&ok=estorno' : '&erro=' . urlencode((string) $r['erro'])));
        exit;
    }

    /**
     * Baixa de uma parcela: marca como paga e lança a entrada no caixa.
     *
     * O lançamento e a baixa vivem no model, na mesma transação e com a parcela
     * travada — é o que impede o mesmo recebimento de entrar duas vezes se a tela
     * for reenviada.
     */
    public function pagarParcela()
    {
        $id        = (int) ($_POST['id_ordem_servico'] ?? 0);
        $parcelaId = (int) ($_POST['id_parcela'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0 || $parcelaId <= 0) {
            header('Location: ./listOrdemServico');
            exit;
        }

        $pagamento = $this->model('OrdemServicoPagamento');

        // Taxa do meio de pagamento (maquininha/operadora), registrada À PARTE do valor da parcela.
        $taxa = trim((string) ($_POST['taxa'] ?? '')) !== '' ? Dinheiro::parse($_POST['taxa']) : null;

        $erro = $pagamento->pagarParcela(
            $parcelaId,
            (string) ($_POST['forma_pagamento'] ?? ''),
            $_SESSION['user_id_usuario'] ?? null,
            $taxa
        );

        $destino = './pagamentoOrdemServico?id=' . $id
            . ($erro ? '&erro=' . urlencode($erro) : '&ok=parcela');

        header('Location: ' . $destino);
        exit;
    }

}
