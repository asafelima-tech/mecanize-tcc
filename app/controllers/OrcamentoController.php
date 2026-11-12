<?php

require_once __DIR__ . '/../models/AutorizacaoCliente.php';

class OrcamentoController extends Controller
{
    private function usuarioId(): ?int
    {
        $id = $_SESSION['user_id_usuario'] ?? null;

        return $id ? (int) $id : null;
    }

    public function list()
    {
        $orcamento = $this->model('Orcamento');

        $dados = $orcamento->getResumo();

        $this->view('Orcamento/listOrcamentos', $dados);
    }

    // Exibe o formulário (GET)
    public function save()
    {
        $orcamento = $this->model('Orcamento');
        $dados = $orcamento->getDadosFormulario();
        $this->view('Orcamento/saveOrcamento', $dados);
    }

    // Processa o POST e salva (chamado pelo action="./saveOrcamento")
    public function saveOrcamento()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ./listOrcamentos');
            exit;
        }

        $orcamento = $this->model('Orcamento');

        $validator = (new Validator($_POST))
            ->required('cliente_id', 'Cliente')
            ->required('veiculo_id', 'Veículo');

        if ($validator->fails()) {
            $dadosForm          = $orcamento->getDadosFormulario();
            $dadosForm['error'] = $validator->firstError();
            $dadosForm['old']   = $_POST;
            $this->view('Orcamento/saveOrcamento', $dadosForm);
            exit;
        }

        // O número do orçamento é do servidor (Orcamento::salvar); o que o
        // formulário mostra é só uma prévia. Totais também são recalculados lá.
        $resultado = $orcamento->salvar($_POST, ['usuario_id' => $this->usuarioId()]);

        if ($resultado) {
            header('Location: ./listOrcamentos?sucesso=1');
            exit;
        }

        $dadosForm          = $orcamento->getDadosFormulario();
        $erro               = $orcamento->getLastError();
        $dadosForm['error'] = $erro !== '' ? $erro : 'Erro ao salvar o orçamento. Tente novamente.';
        $dadosForm['old']   = $_POST;
        $this->view('Orcamento/saveOrcamento', $dadosForm);
        exit;
    }

    public function aprovarOrcamento()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ./listOrcamentos');
            exit;
        }

        $id = (int) ($_POST['id_orcamento'] ?? 0);

        if (!$id) {
            header('Location: ./listOrcamentos');
            exit;
        }

        // Assinatura capturada no canvas do modal de aprovação (base64 PNG).
        // Opcional — string vazia/ausente vira null e não sobrescreve nada.
        // Exige PNG especificamente (é o único formato que o canvas de
        // assinatura emite) em vez de qualquer "data:image/*" — o valor vai
        // direto pra uma <img> num PDF renderizado pelo Dompdf, então aceitar
        // outros tipos (ex.: SVG) seria superfície de ataque sem ganho nenhum.
        $assinatura = trim($_POST['assinatura_cliente'] ?? '');
        $assinatura = ($assinatura !== '' && str_starts_with($assinatura, 'data:image/png;base64,')) ? $assinatura : null;

        // Canal e evidência de quem registra a aprovação do cliente (balcão, telefone, WhatsApp...).
        $canal = (string) ($_POST['canal'] ?? '');
        $ctx   = ['usuario_id' => $this->usuarioId()];
        if (isset(AutorizacaoCliente::CANAIS[$canal])) {
            $ctx['canal']     = $canal;
            $ctx['evidencia'] = trim((string) ($_POST['evidencia'] ?? '')) ?: null;
        }

        $orcamento = $this->model('Orcamento');
        $r         = $orcamento->aprovar($id, $assinatura, $ctx);

        // Veio da ficha da ocorrência? Volta para ela, que mostra o próximo passo (a OS).
        $ocorrenciaId = (int) ($_POST['ocorrencia_id'] ?? 0);

        if ($r['ok']) {
            // O aviso ao cliente nasce dentro da transação de aprovação (Comunicacao::evento), com
            // chave do fato: clique repetido não reenvia e o envio é feito pela fila de mensagens.
            header('Location: ' . ($ocorrenciaId
                ? './viewOcorrencia?id=' . $ocorrenciaId . '&ok=' . ($r['repetido'] ? 'jaaprovado' : 'orcamentoaprovado')
                : './listOrcamentos?sucesso=' . ($r['repetido'] ? 'jaaprovado' : 'aprovado')));
        } else {
            header('Location: ' . ($ocorrenciaId
                ? './viewOcorrencia?id=' . $ocorrenciaId . '&erro=fluxo&detalhe=' . urlencode((string) $r['erro'])
                : './listOrcamentos?erro=aprovacao&detalhe=' . urlencode((string) $r['erro'])));
        }
        exit;
    }

    public function recusarOrcamento()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ./listOrcamentos');
            exit;
        }

        $id = (int) ($_POST['id_orcamento'] ?? 0);

        if (!$id) {
            header('Location: ./listOrcamentos');
            exit;
        }

        $r = $this->model('Orcamento')->recusar($id, $_POST['motivo_recusa'] ?? null, ['usuario_id' => $this->usuarioId()]);

        $ocorrenciaId = (int) ($_POST['ocorrencia_id'] ?? 0);

        if ($r['ok']) {
            header('Location: ' . ($ocorrenciaId
                ? './viewOcorrencia?id=' . $ocorrenciaId . '&ok=' . ($r['repetido'] ? 'jarecusado' : 'orcamentorecusado')
                : './listOrcamentos?sucesso=' . ($r['repetido'] ? 'jarecusado' : 'recusado')));
        } else {
            header('Location: ' . ($ocorrenciaId
                ? './viewOcorrencia?id=' . $ocorrenciaId . '&erro=fluxo&detalhe=' . urlencode((string) $r['erro'])
                : './listOrcamentos?erro=recusa&detalhe=' . urlencode((string) $r['erro'])));
        }
        exit;
    }

    /**
     * Marca o orçamento como ENVIADO ao cliente (a ocorrência passa a aguardar a aprovação).
     * Devolve para quem pediu: a ficha da ocorrência, se veio dela, ou a lista.
     */
    public function enviarOrcamento()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ./listOrcamentos');
            exit;
        }

        $id = (int) ($_POST['id_orcamento'] ?? 0);
        $r  = $id ? $this->model('Orcamento')->enviar($id, ['usuario_id' => $this->usuarioId()]) : ['ok' => false, 'erro' => 'Orçamento inválido.', 'repetido' => false];

        $orc = $id ? $this->model('Orcamento')->getById($id) : null;

        if ($orc && !empty($_POST['ocorrencia_id'])) {
            header('Location: ./viewOcorrencia?id=' . (int) $_POST['ocorrencia_id'] . ($r['ok']
                ? '&ok=' . ($r['repetido'] ? 'jaenviado' : 'orcamentoenviado')
                : '&erro=fluxo&detalhe=' . urlencode((string) $r['erro'])));
            exit;
        }

        header('Location: ./listOrcamentos?' . ($r['ok']
            ? 'sucesso=' . ($r['repetido'] ? 'jaenviado' : 'enviado')
            : 'erro=envio&detalhe=' . urlencode((string) $r['erro'])));
        exit;
    }

    /** Reabre um orçamento recusado para negociar de novo (volta a "pendente"). */
    public function reabrirOrcamento()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ./listOrcamentos');
            exit;
        }

        $id = (int) ($_POST['id_orcamento'] ?? 0);
        $r  = $id ? $this->model('Orcamento')->reabrir($id, ['usuario_id' => $this->usuarioId()]) : ['ok' => false, 'erro' => 'Orçamento inválido.'];

        header('Location: ./listOrcamentos?' . ($r['ok']
            ? 'sucesso=reaberto'
            : 'erro=reabertura&detalhe=' . urlencode((string) $r['erro'])));
        exit;
    }

    public function visualizar()
    {
        if (empty($_GET['id'])) {
            header('Location: ./listOrcamentos');
            exit;
        }

        $orcamento = $this->model('Orcamento');

        $dados = [
            'orcamento' => $orcamento->getById($_GET['id']),
            'itens'     => $orcamento->getItensByOrcamento($_GET['id'])
        ];

        if (!$dados['orcamento']) {
            $this->naoEncontrado('Orçamento', './listOrcamentos');
        }

        $tokenRastreio = $orcamento->getOuCriarToken((int) $_GET['id']);
        $dados['linkRastreio'] = $this->urlAbsoluta('rastrear?token=' . $tokenRastreio);

        $this->view('Orcamento/viewOrcamento', $dados);
    }

    public function edit()
    {
        $orcamento = $this->model('Orcamento');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id_orcamento'] ?? null;

            if (!$id) {
                header('Location: ./listOrcamentos');
                exit;
            }

            $validator = (new Validator($_POST))
                ->required('cliente_id', 'Cliente')
                ->required('veiculo_id', 'Veículo');

            if ($validator->fails()) {
                $dados              = $orcamento->getDadosFormulario();
                $dados['orcamento'] = array_merge($orcamento->getById($id) ?: [], $_POST);
                $dados['itens']     = $orcamento->getItensByOrcamento($id);
                $dados['error']     = $validator->firstError();
                $this->view('Orcamento/editOrcamento', $dados);
                return;
            }

            if ($orcamento->atualizar($id, $_POST, ['usuario_id' => $this->usuarioId()])) {
                header('Location: ./listOrcamentos');
                exit;
            }

            $erro               = $orcamento->getLastError();
            $dados              = $orcamento->getDadosFormulario();
            $dados['orcamento'] = array_merge($orcamento->getById($id) ?: [], $_POST);
            $dados['itens']     = $orcamento->getItensByOrcamento($id);
            $dados['error']     = $erro !== '' ? $erro : 'Erro ao atualizar o orçamento.';

            $this->view('Orcamento/editOrcamento', $dados);
            return;
        }

        if (empty($_GET['id'])) {
            header('Location: ./listOrcamentos');
            exit;
        }

        $dados              = $orcamento->getDadosFormulario();
        $dados['orcamento'] = $orcamento->getById($_GET['id']);
        $dados['itens']     = $orcamento->getItensByOrcamento($_GET['id']);

        if (!$dados['orcamento']) {
            $this->naoEncontrado('Orçamento', './listOrcamentos');
        }

        $this->view('Orcamento/editOrcamento', $dados);
    }

    /**
     * Gera e baixa o orçamento em PDF de verdade (via Dompdf), ao lado do
     * botão "Imprimir" que hoje só abre a tela pronta para Ctrl+P.
     */
    public function exportarPdf()
    {
        if (empty($_GET['id'])) {
            header('Location: ./listOrcamentos');
            exit;
        }

        $orcamento = $this->model('Orcamento');

        $orc = $orcamento->getById($_GET['id']);
        if (!$orc) {
            $this->naoEncontrado('Orçamento', './listOrcamentos');
        }

        $itens = $orcamento->getItensByOrcamento($_GET['id']);

        require_once __DIR__ . '/../core/Pdf.php';

        ob_start();
        require __DIR__ . '/../views/Orcamento/pdfOrcamento.php';
        $html = ob_get_clean();

        $numero = preg_replace('/[^A-Za-z0-9_-]/', '_', $orc['numero'] ?? (string) $orc['id_orcamento']);

        Pdf::baixar($html, "orcamento_{$numero}.pdf");
    }

    public function imprimir()
    {
        if (empty($_GET['id'])) {
            header('Location: ./listOrcamentos');
            exit;
        }

        $orcamento = $this->model('Orcamento');

        $orc = $orcamento->getById($_GET['id']);
        if (!$orc) {
            $this->naoEncontrado('Orçamento', './listOrcamentos');
        }

        $itens = $orcamento->getItensByOrcamento($_GET['id']);

        // Renderizado SEM o layout principal (sem sidebar/topbar do sistema).
        require __DIR__ . '/../views/Orcamento/printOrcamento.php';
        exit;
    }
}
