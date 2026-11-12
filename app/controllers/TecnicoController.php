<?php

require_once __DIR__ . '/../models/OrdemServicoFluxo.php';
require_once __DIR__ . '/../models/EvidenciaOs.php';

/**
 * Área do Técnico — painel de trabalho mobile-first, sem nada financeiro ou
 * administrativo (isso continua em OrdemServicoController).
 *
 * PERMISSÕES: iguais às do atendente. Esta tela é uma VISÃO diferente da mesma
 * capacidade — o técnico cai aqui ao entrar e vê primeiro as OS atribuídas a ele,
 * mas qualquer membro da equipe (ADMIN, ATENDENTE, TECNICO) pode abri-la e atuar
 * em qualquer OS. Antes, só o primeiro técnico atribuído enxergava a OS (os demais
 * recebiam 404) e o atendente não entrava aqui; a trava de "dono" era uma regra de
 * tela disfarçada de segurança. O que protege a operação é a máquina de estados
 * (OrdemServicoFluxo) e a trilha de quem fez o quê, não esconder a OS dos colegas.
 */
class TecnicoController extends Controller
{
    private function usuarioId(): int
    {
        return (int) ($_SESSION['user_id_usuario'] ?? 0);
    }

    private function papel(): string
    {
        return (string) ($_SESSION['user_tipo'] ?? '');
    }

    private function fluxo(): OrdemServicoFluxo
    {
        return new OrdemServicoFluxo();
    }

    /** Painel: cards de contagem + abas "Minhas OS" / "Prontas" / "Histórico". */
    public function painel()
    {
        $this->requireRole(Permissao::PAPEIS_EQUIPE);

        $ordem = $this->model('OrdemServico');

        // ADMIN vê o trabalho de todos; técnico e atendente começam pelas OS
        // atribuídas a eles e podem alternar para "todas" (?escopo=todas).
        $todas = $this->papel() === 'ADMIN' || ($_GET['escopo'] ?? '') === 'todas';

        if ($todas) {
            $minhas = [
                'ABERTA'          => $ordem->listarAbertas(),
                'EM_ANDAMENTO'    => $ordem->listarEmAndamento(),
                'AGUARDANDO_PECA' => $ordem->listarAguardandoPeca(),
            ];
            $prontas   = array_merge($ordem->listarAguardandoConferencia(), $ordem->listarAguardandoRetirada());
            $historico = array_merge($ordem->listarConcluidas(), $ordem->listarArquivadas());
        } else {
            $id = $this->usuarioId();
            $minhas = [
                'ABERTA'          => $ordem->listarPorTecnico($id, ['ABERTA']),
                'EM_ANDAMENTO'    => $ordem->listarPorTecnico($id, ['EM_ANDAMENTO']),
                'AGUARDANDO_PECA' => $ordem->listarPorTecnico($id, ['AGUARDANDO_PECA']),
            ];
            $prontas   = $ordem->listarPorTecnico($id, ['AGUARDANDO_CONFERENCIA', 'AGUARDANDO_RETIRADA']);
            $historico = $ordem->listarPorTecnico($id, ['CONCLUIDA', 'CANCELADA']);
        }

        // Veículos esperando avaliação técnica: o começo do atendimento. Técnico e atendente
        // veem os seus (ou sem responsável); "todas" mostra os de todos.
        $uid = $this->usuarioId();
        $peso = ['URGENTE' => 3, 'ALTA' => 2, 'NORMAL' => 1, 'BAIXA' => 0];

        $avaliacoes = array_values(array_filter(
            $this->model('Ocorrencia')->listar(['abertas' => true]),
            fn($o) => in_array($o['status'], ['AGUARDANDO_AVALIACAO', 'EM_AVALIACAO'], true)
                && ($todas || $o['tecnico_id'] === null || (int) $o['tecnico_id'] === $uid)
        ));
        usort($avaliacoes, fn($a, $b) => ($peso[$b['prioridade']] ?? 1) <=> ($peso[$a['prioridade']] ?? 1)
            ?: strcmp((string) $a['chegada_em'], (string) $b['chegada_em']));

        $this->view('Tecnico/painelTecnico', [
            'minhas'    => $minhas,
            'prontas'   => $prontas,
            'historico' => $historico,
            'escopoTodas' => $todas,
            'avaliacoes' => $avaliacoes,
            'sugestao'   => $this->model('FilaOperacional')->sugestaoPara($uid),
        ]);
    }

    /** Tela de trabalho de uma OS: detalhe + todas as ações da equipe. */
    public function visualizar()
    {
        $this->requireRole(Permissao::PAPEIS_EQUIPE);

        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            header('Location: ./painelTecnico');
            exit;
        }

        $ordemServico = $this->model('OrdemServico');
        $ordem        = $ordemServico->getById($id);

        if (!$ordem) {
            $this->naoEncontrado('Ordem de serviço', './painelTecnico');
        }

        $checklistSalvo = json_decode((string) ($ordem['checklist_entrega'] ?? ''), true);

        // O que o técnico precisa enxergar além da OS: o que trava o serviço, as peças pedidas e o histórico do carro.
        // Cada consulta é independente — se uma falhar, a tela de trabalho continua abrindo.
        $bloqueios = [];
        $solicitacoes = [];
        $historicoVeiculo = [];

        $evidencias = [];

        try {
            $evidencias = (new EvidenciaOs())->daOs($id);
        } catch (\Throwable $e) {
            error_log('[painel técnico] evidências: ' . $e->getMessage());
        }

        try {
            $bloqueios = $this->model('AutorizacaoCliente')->bloqueiosDaOs($id);
        } catch (\Throwable $e) {
            error_log('[painel técnico] bloqueios: ' . $e->getMessage());
        }

        try {
            $solicitacoes = $this->model('SolicitacaoPeca')->listar(['grupo' => 'todas', 'ordem_servico_id' => $id], 20);
        } catch (\Throwable $e) {
            error_log('[painel técnico] solicitações: ' . $e->getMessage());
        }

        try {
            $historicoVeiculo = array_values(array_filter(
                $ordemServico->listarPorVeiculo((int) $ordem['veiculo_id']),
                fn($o) => (int) $o['id_ordem_servico'] !== $id
            ));
        } catch (\Throwable $e) {
            error_log('[painel técnico] histórico do veículo: ' . $e->getMessage());
        }

        $this->view('Tecnico/osTecnico', [
            'evidencias'       => $evidencias,
            'bloqueios'        => $bloqueios,
            'solicitacoes'     => $solicitacoes,
            'historico_veiculo' => array_slice($historicoVeiculo, 0, 8),
            'ordem_servico' => $ordem,
            'itens_os'      => $ordemServico->getItensByOS($id),
            'tecnicos_os'   => $ordemServico->getTecnicosDaOS($id),
            'produtos'      => $ordemServico->getProdutos(),
            'checklist'     => is_array($checklistSalvo) ? $checklistSalvo : [],
            // As ações compartilhadas com a tela de OS devolvem erro=fluxo&detalhe=<motivo>: mostra o motivo, não a palavra "fluxo".
            'erro'          => ($_GET['erro'] ?? null) === 'fluxo' ? ($_GET['detalhe'] ?? 'Não foi possível concluir a ação.') : ($_GET['erro'] ?? null),
            'sucesso'       => $_GET['ok'] ?? null,
        ]);
    }

    /** Botão simples de status (Aguardando execução / Em execução / Aguardando peças). */
    public function atualizarStatus()
    {
        $this->requireRole(Permissao::PAPEIS_EQUIPE);

        $id     = (int) ($_POST['id_ordem_servico'] ?? 0);
        $status = strtoupper(trim($_POST['status'] ?? ''));

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ./painelTecnico');
            exit;
        }

        // Este botão só alterna entre as etapas de execução. Conferência, entrega e
        // cancelamento têm tela e regra próprias.
        if (!in_array($status, OrdemServico::STATUS_TECNICO, true)) {
            header('Location: ./viewOSTecnico?id=' . $id . '&erro=' . urlencode('Etapa inválida para este botão.'));
            exit;
        }

        $r = $this->fluxo()->transicionar($id, $status, $this->usuarioId(), $this->papel());

        header('Location: ./viewOSTecnico?id=' . $id . ($r['ok'] ? '&ok=status' : '&erro=' . urlencode((string) $r['erro'])));
        exit;
    }

    /**
     * "Confirmação de conclusão do serviço": checklist + observações +
     * assinatura do técnico, tudo de uma vez, avançando para AGUARDANDO_CONFERENCIA.
     * O técnico NÃO encerra a OS: a decisão final é do administrador.
     */
    public function finalizar()
    {
        $this->requireRole(Permissao::PAPEIS_EQUIPE);

        $id = (int) ($_POST['id_ordem_servico'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ./painelTecnico');
            exit;
        }

        $r = $this->fluxo()->transicionar($id, 'AGUARDANDO_CONFERENCIA', $this->usuarioId(), $this->papel(), [
            'assinatura_tecnico'   => $_POST['assinatura_tecnico'] ?? null,
            'observacoes_tecnicas' => $_POST['observacoes_tecnicas'] ?? null,
            'checklist_entrega'    => OrdemServico::montarChecklist($_POST),
        ]);

        header('Location: ./viewOSTecnico?id=' . $id . ($r['ok'] ? '&ok=finalizado' : '&erro=' . urlencode((string) $r['erro'])));
        exit;
    }

    /**
     * Peça usada, registrada durante a execução — o preço vem do cadastro, não de
     * quem lança. Entra planejada; com "já utilizei" marcado, o consumo é
     * confirmado na mesma hora (e o estoque baixa, se houver saldo).
     */
    public function adicionarPeca()
    {
        $this->requireRole(Permissao::PAPEIS_EQUIPE);

        $id        = (int) ($_POST['id_ordem_servico'] ?? 0);
        $produtoId = (int) ($_POST['produto_id'] ?? 0);
        $qty       = (float) str_replace(',', '.', $_POST['quantidade'] ?? '0');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ./painelTecnico');
            exit;
        }

        $r = $this->model('OrdemServico')->adicionarPeca($id, $produtoId, $qty, $this->usuarioId(), !empty($_POST['consumir']));

        if (!$r['ok']) {
            $destino = '&erro=' . urlencode((string) $r['erro']);
        } elseif ($r['aviso']) {
            $destino = '&erro=' . urlencode((string) $r['aviso']);
        } else {
            $destino = '&ok=peca';
        }

        header('Location: ./viewOSTecnico?id=' . $id . $destino);
        exit;
    }

    /** Confirma (ou desmarca) um serviço planejado como executado. */
    public function confirmarServico()
    {
        $this->requireRole(Permissao::PAPEIS_EQUIPE);

        $id        = (int) ($_POST['id_ordem_servico'] ?? 0);
        $servicoId = (int) ($_POST['servico_row_id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0 || $servicoId <= 0) {
            header('Location: ./painelTecnico');
            exit;
        }

        $this->model('OrdemServico')->alternarServicoExecutado($servicoId, $id);

        header('Location: ./viewOSTecnico?id=' . $id);
        exit;
    }

    /** Foto de evidência (antes/durante/depois/problema). Imagem apenas; reduzida no aparelho antes de subir. */
    public function enviarEvidencia()
    {
        $this->requireRole(Permissao::PAPEIS_EQUIPE);

        $id = (int) ($_POST['id_ordem_servico'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ./painelTecnico');
            exit;
        }

        $r = (new EvidenciaOs())->anexar($id, (string) ($_POST['tipo'] ?? ''), $_POST['descricao'] ?? null, $_FILES['foto'] ?? [], $this->usuarioId());

        header('Location: ./viewOSTecnico?id=' . $id . ($r['ok'] ? '&ok=evidencia' : '&erro=' . urlencode((string) $r['erro'])) . '#evidencias');
        exit;
    }

    /** Entrega a foto à equipe logada: tipo e tamanho vêm do registro, o arquivo vem de storage/ (fora da área pública). */
    public function verEvidencia()
    {
        $this->requireRole(Permissao::PAPEIS_EQUIPE);

        $e = (new EvidenciaOs())->getById((int) ($_GET['id'] ?? 0));
        $arquivo = $e ? Armazenamento::caminho($e['caminho']) : null;

        if (!$e || !$arquivo) {
            http_response_code(404);
            exit;
        }

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: ' . $e['mime']);
        header('Content-Length: ' . filesize($arquivo));
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; sandbox');
        header('Cache-Control: private, max-age=3600');
        header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._\- ]/', '_', $e['nome_original']) . '"');
        readfile($arquivo);
        exit;
    }

    /** Observação técnica avulsa — não precisa esperar a finalização pra salvar. */
    public function salvarObservacoes()
    {
        $this->requireRole(Permissao::PAPEIS_EQUIPE);

        $id = (int) ($_POST['id_ordem_servico'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ./painelTecnico');
            exit;
        }

        $this->model('OrdemServico')->salvarObservacoesTecnicas($id, (string) ($_POST['observacoes_tecnicas'] ?? ''));

        header('Location: ./viewOSTecnico?id=' . $id . '&ok=obs');
        exit;
    }
}
