<?php

require_once __DIR__ . '/../models/Ocorrencia.php';
require_once __DIR__ . '/../models/Avaliacao.php';
require_once __DIR__ . '/../models/AutorizacaoCliente.php';

/**
 * Atendimento: chegada do veículo, avaliação técnica, orçamento e abertura da OS.
 *
 * Toda decisão (quem pode, de qual etapa para qual) está nos models; aqui só se traduz o
 * formulário e se devolve a mensagem. Técnico e atendente têm as mesmas permissões — o
 * técnico chega aqui pelo "Painel de trabalho", o atendente pelo menu.
 */
class OcorrenciaController extends Controller
{
    private function usuarioId(): int
    {
        return (int) ($_SESSION['user_id_usuario'] ?? 0);
    }

    private function papel(): string
    {
        return (string) ($_SESSION['user_tipo'] ?? '');
    }

    private function exigirPost(string $voltar = './listOcorrencias'): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ' . $voltar);
            exit;
        }
    }

    /** Volta para a ocorrência com ok=<código> ou erro=fluxo&detalhe=<texto>. */
    private function voltar(int $id, array $r, string $codigoOk): void
    {
        $query = !empty($r['ok'])
            ? 'ok=' . $codigoOk
            : 'erro=fluxo&detalhe=' . urlencode((string) ($r['erro'] ?? 'Não foi possível concluir a ação.'));

        header('Location: ./viewOcorrencia?id=' . $id . '&' . $query);
        exit;
    }

    /* ── Lista ───────────────────────────────────────────────────────────── */

    public function list()
    {
        $model = $this->model('Ocorrencia');

        $aba = (string) ($_GET['aba'] ?? 'abertas');

        $filtros = ['busca' => $_GET['busca'] ?? ''];
        if ($aba === 'abertas') {
            $filtros['abertas'] = true;
        } elseif (isset(OcorrenciaFluxo::LABEL[$aba])) {
            $filtros['status'] = $aba;
        } elseif ($aba === 'todas') {
            // sem filtro de estado
        } else {
            $filtros['abertas'] = true;
            $aba = 'abertas';
        }

        if (!empty($_GET['prioridade']) && isset(Ocorrencia::PRIORIDADES[$_GET['prioridade']])) {
            $filtros['prioridade'] = $_GET['prioridade'];
        }

        $this->view('Ocorrencias/listOcorrencias', [
            'ocorrencias' => $model->listar($filtros),
            'contagem'    => $model->contagemPorStatus(),
            'aba'         => $aba,
            'busca'       => $filtros['busca'],
            'prioridade'  => $filtros['prioridade'] ?? '',
        ]);
    }

    /* ── Chegada ─────────────────────────────────────────────────────────── */

    public function save()
    {
        $model = $this->model('Ocorrencia');
        $dados = $this->dadosDoFormulario($model);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $dados['modo'] = ($_GET['modo'] ?? '') === 'rapido' ? 'rapido' : 'existente';
            $dados['old']  = array_filter([
                'cliente_id' => $_GET['cliente_id'] ?? null,
                'veiculo_id' => $_GET['veiculo_id'] ?? null,
                'relato'     => $_GET['relato'] ?? null,
            ]);

            // Pré-seleciona o cliente quando se chega da ficha dele.
            if (!empty($_GET['cliente_id'])) {
                $dados['clientePreSelecionado'] = $this->model('Cliente')->findById((int) $_GET['cliente_id']);
                $dados['veiculosDoCliente']     = $this->model('Veiculo')->getByCliente((int) $_GET['cliente_id']);
            }

            $this->view('Ocorrencias/saveOcorrencia', $dados);
            return;
        }

        $modoRapido = ($_POST['modo_cliente'] ?? '') === 'rapido';

        $entrada = [
            'cliente' => $modoRapido
                ? ['nome' => $_POST['cliente_nome'] ?? '', 'telefone' => $_POST['cliente_telefone'] ?? '']
                : ['id' => $_POST['cliente_id'] ?? 0],
            'veiculo' => ($_POST['modo_veiculo'] ?? '') === 'novo' || $modoRapido
                ? [
                    'placa' => $_POST['placa'] ?? '', 'marca_id' => $_POST['marca_id'] ?? 0, 'modelo_id' => $_POST['modelo_id'] ?? 0,
                    'ano' => $_POST['ano'] ?? null, 'cor' => $_POST['cor'] ?? null,
                ]
                : ['id' => $_POST['veiculo_id'] ?? 0],
            'relato'          => $_POST['relato'] ?? '',
            'observacoes'     => $_POST['observacoes'] ?? '',
            'prioridade'      => $_POST['prioridade'] ?? 'NORMAL',
            'urgencia'        => !empty($_POST['urgencia']),
            'urgencia_motivo' => $_POST['urgencia_motivo'] ?? '',
            'tecnico_id'      => $_POST['tecnico_id'] ?? null,
            'consentimento_whatsapp' => !empty($_POST['consentimento_whatsapp']),
            'origem'          => in_array($_POST['origem'] ?? '', ['PRESENCIAL', 'RAPIDO', 'REMOTO'], true) ? $_POST['origem'] : null,
        ];

        // O atendimento rápido é identificado como tal; o formulário não precisa mandar.
        if ($modoRapido && $entrada['origem'] === null) {
            $entrada['origem'] = 'RAPIDO';
        }
        if ($entrada['origem'] === null) {
            unset($entrada['origem']);
        }

        $r = $model->registrar($entrada, $this->usuarioId(), $this->papel());

        if ($r['ok']) {
            header('Location: ./viewOcorrencia?id=' . $r['id'] . '&ok=registrada');
            exit;
        }

        $dados['modo']        = $modoRapido ? 'rapido' : 'existente';
        $dados['error']       = $r['erro'];
        $dados['duplicidade'] = $r['duplicidade'];
        $dados['old']         = $_POST;

        if (!empty($_POST['cliente_id'])) {
            $dados['clientePreSelecionado'] = $this->model('Cliente')->findById((int) $_POST['cliente_id']);
            $dados['veiculosDoCliente']     = $this->model('Veiculo')->getByCliente((int) $_POST['cliente_id']);
        }

        $this->view('Ocorrencias/saveOcorrencia', $dados);
    }

    private function dadosDoFormulario($model): array
    {
        return [
            'tecnicos'    => $model->tecnicos(),
            'marcas'      => array_values(array_filter($this->model('Veiculo')->listarMarcas(), fn($m) => $m['nome'] !== 'Não informado')),
            'prioridades' => Ocorrencia::PRIORIDADES,
        ];
    }

    /** JSON para a busca do balcão: nome, telefone ou placa. */
    public function buscarClientes()
    {
        header('Content-Type: application/json; charset=utf-8');

        $termo = trim((string) ($_GET['termo'] ?? ''));

        echo json_encode(['sucesso' => true, 'clientes' => $this->model('Cliente')->buscar($termo)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── Painel da ocorrência ────────────────────────────────────────────── */

    public function visualizar()
    {
        $id = (int) ($_GET['id'] ?? 0);

        $model = $this->model('Ocorrencia');
        $oc    = $id > 0 ? $model->getById($id) : null;

        if (!$oc) {
            $this->naoEncontrado('Ocorrência', './listOcorrencias');
        }

        $avaliacoes = $this->model('Avaliacao');
        $avaliacao  = $avaliacoes->ultima($id);

        $autorizacoes = $this->model('AutorizacaoCliente');

        $orcamentos = $this->model('Orcamento');
        $stmt = $model->conexao()->prepare('SELECT id_orcamento, numero, status, valor_total_orcamento, enviado_em, versao, data_validade FROM orcamentos WHERE ocorrencia_id = ? ORDER BY id_orcamento DESC');
        $stmt->execute([$id]);

        $this->view('Ocorrencias/viewOcorrencia', [
            'ocorrencia'    => $oc,
            'linhaDoTempo'  => $model->linhaDoTempo($id),
            'avaliacao'     => $avaliacao,
            'avaliacaoItens' => $avaliacao ? $avaliacoes->itens((int) $avaliacao['id_avaliacao']) : [],
            'avaliacaoTotal' => $avaliacao ? $avaliacoes->total((int) $avaliacao['id_avaliacao']) : 0.0,
            'versoes'       => $avaliacoes->versoes($id),
            'orcamentos'    => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'autorizacao'   => $autorizacoes->ativaDaOcorrencia($id),
            'autorizacoes'  => $autorizacoes->listar($id, $oc['os_id'] ? (int) $oc['os_id'] : null),
            'produtos'      => $this->model('OrdemServico')->getProdutos(),
            'tecnicos'      => $model->tecnicos(),
            'canais'        => AutorizacaoCliente::CANAIS,
            'ehAdmin'       => $this->papel() === 'ADMIN',
            'sucesso'       => $_GET['ok'] ?? null,
            'erro'          => ($_GET['erro'] ?? '') === 'fluxo' ? ($_GET['detalhe'] ?? 'Não foi possível concluir a ação.') : null,
        ]);
    }

    /* ── Avaliação técnica ───────────────────────────────────────────────── */

    public function iniciarAvaliacao()
    {
        $this->exigirPost();

        $id = (int) ($_POST['id_ocorrencia'] ?? 0);
        $r  = $this->model('Avaliacao')->iniciar($id, $this->usuarioId());

        $this->voltar($id, $r, $r['repetido'] ?? false ? 'avaliacaoemandamento' : 'avaliacaoiniciada');
    }

    /** Salva o rascunho; com acao=concluir (ou concluir_sem_reparo) também conclui. */
    public function salvarAvaliacao()
    {
        $this->exigirPost();

        $ocId = (int) ($_POST['id_ocorrencia'] ?? 0);
        $avId = (int) ($_POST['id_avaliacao'] ?? 0);
        $acao = (string) ($_POST['acao'] ?? 'salvar');

        $avaliacoes = $this->model('Avaliacao');

        $r = $avaliacoes->salvar($avId, [
            'defeitos'             => $_POST['defeitos'] ?? '',
            'observacoes'          => $_POST['observacoes'] ?? '',
            'duracao_estimada_min' => $_POST['duracao_estimada_min'] ?? null,
            'itens'                => array_values((array) ($_POST['itens'] ?? [])),
        ]);

        if ($r['ok'] && in_array($acao, ['concluir', 'concluir_sem_reparo'], true)) {
            $r = $avaliacoes->concluir($avId, $this->usuarioId(), $acao === 'concluir_sem_reparo');
            $this->voltar($ocId, $r, $acao === 'concluir_sem_reparo' ? 'semreparo' : 'avaliacaoconcluida');
        }

        $this->voltar($ocId, $r, 'avaliacaosalva');
    }

    /** Gera o orçamento a partir da avaliação e já abre a edição para conferir os preços. */
    public function gerarOrcamento()
    {
        $this->exigirPost();

        $ocId = (int) ($_POST['id_ocorrencia'] ?? 0);
        $avId = (int) ($_POST['id_avaliacao'] ?? 0);

        $r = $this->model('Avaliacao')->gerarOrcamento($avId, $this->usuarioId());

        if ($r['ok']) {
            header('Location: ./editOrcamento?id=' . (int) $r['id']);
            exit;
        }

        $this->voltar($ocId, $r, '');
    }

    /* ── Autorização e OS direta ─────────────────────────────────────────── */

    public function registrarAutorizacao()
    {
        $this->exigirPost();

        $id = (int) ($_POST['id_ocorrencia'] ?? 0);

        $r = $this->model('AutorizacaoCliente')->registrarValorAberto($id, [
            'escopo'       => $_POST['escopo'] ?? '',
            'evidencia'    => $_POST['evidencia'] ?? '',
            'canal'        => $_POST['canal'] ?? '',
            'limite_valor' => $_POST['limite_valor'] ?? null,
        ], $this->usuarioId());

        $this->voltar($id, $r, 'autorizacaoregistrada');
    }

    public function abrirOs()
    {
        $this->exigirPost();

        $id = (int) ($_POST['id_ocorrencia'] ?? 0);
        $r  = $this->model('Ocorrencia')->abrirOsDireta($id, $this->usuarioId());

        if ($r['ok']) {
            header('Location: ./viewOrdemServico?id=' . (int) $r['os_id']);
            exit;
        }

        $this->voltar($id, $r, '');
    }

    /* ── Ajustes ─────────────────────────────────────────────────────────── */

    public function validarUrgencia()
    {
        $this->exigirPost();

        $id = (int) ($_POST['id_ocorrencia'] ?? 0);
        $this->voltar($id, $this->model('Ocorrencia')->validarUrgencia($id, $this->usuarioId()), 'urgenciavalidada');
    }

    public function atribuirTecnico()
    {
        $this->exigirPost();

        $id        = (int) ($_POST['id_ocorrencia'] ?? 0);
        $tecnicoId = !empty($_POST['tecnico_id']) ? (int) $_POST['tecnico_id'] : null;

        $this->voltar($id, $this->model('Ocorrencia')->atribuirTecnico($id, $tecnicoId, $this->usuarioId()), 'tecnicoatribuido');
    }

    public function cancelar()
    {
        $this->exigirPost();

        $id = (int) ($_POST['id_ocorrencia'] ?? 0);
        $this->voltar($id, $this->model('Ocorrencia')->cancelar($id, $this->usuarioId(), (string) ($_POST['motivo'] ?? '')), 'cancelada');
    }
}
