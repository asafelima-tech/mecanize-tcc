<?php

require_once __DIR__ . '/../core/Transacao.php';
require_once __DIR__ . '/../core/Historico.php';
require_once __DIR__ . '/../models/PortalToken.php';
require_once __DIR__ . '/../models/Comunicacao.php';

/**
 * O lado da equipe do portal do cliente: gerar, enviar e revogar o link, e informar a previsão
 * de conclusão que o cliente vê. Técnico e atendente têm as mesmas permissões (EQUIPE).
 *
 * O link bruto só existe no momento em que é gerado (no banco fica o hash): a tela mostra uma
 * vez, para copiar. Perdeu? Gere outro — os anteriores continuam valendo até expirar ou serem revogados.
 */
class PortalEquipeController extends Controller
{
    /** escopo → [tabela, chave, rota de volta] */
    private const ENTIDADES = [
        'OCORRENCIA' => ['ocorrencias', 'id_ocorrencia', 'viewOcorrencia'],
        'ORCAMENTO'  => ['orcamentos', 'id_orcamento', 'viewOrcamento'],
        'OS'         => ['ordens_servico', 'id_ordem_servico', 'viewOrdemServico'],
    ];

    private function usuarioId(): int
    {
        return (int) ($_SESSION['user_id_usuario'] ?? 0);
    }

    private function exigirPost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ./home');
            exit;
        }
    }

    /** @return array{escopo:string, id:int, cliente_id:int, veiculo_id:?int, volta:string} */
    private function entidade(): array
    {
        $escopo = (string) ($_POST['escopo'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);

        if (!isset(self::ENTIDADES[$escopo])) {
            $this->naoEncontrado('Registro', './home');
        }

        [$tabela, $pk, $rota] = self::ENTIDADES[$escopo];
        $stmt = (new Database())->prepare("SELECT cliente_id, veiculo_id FROM {$tabela} WHERE {$pk} = ?");
        $stmt->execute([$id]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$linha || !$linha['cliente_id']) {
            $this->naoEncontrado('Registro', './home');
        }

        return ['escopo' => $escopo, 'id' => $id, 'cliente_id' => (int) $linha['cliente_id'], 'veiculo_id' => $linha['veiculo_id'] ? (int) $linha['veiculo_id'] : null, 'volta' => './' . $rota . '?id=' . $id];
    }

    private function voltar(array $ent, string $query): void
    {
        header('Location: ' . $ent['volta'] . '&' . $query);
        exit;
    }

    /** Gera um link novo e mostra uma única vez (para copiar e colar onde o cliente já conversa com a oficina). */
    public function gerarLink()
    {
        $this->exigirPost();
        $ent = $this->entidade();

        $bruto = PortalToken::emitir(new Database(), $ent['cliente_id'], $ent['escopo'], $ent['id'], $this->usuarioId());
        $_SESSION['link_portal_gerado'] = ['escopo' => $ent['escopo'], 'id' => $ent['id'], 'url' => PortalToken::url($bruto)];

        $this->voltar($ent, 'ok=linkgerado');
    }

    /** Gera o link e já o manda pelo canal do cliente (respeitando consentimento e janela). O resultado fica registrado na conversa. */
    public function enviarLink()
    {
        $this->exigirPost();
        $ent = $this->entidade();
        $pdo = new Database();

        $comunicacao = new Comunicacao($pdo);
        $mensagemId = $comunicacao->registrarEvento(
            'LINK_ACOMPANHAMENTO', $ent['cliente_id'], Comunicacao::dadosDoVeiculo($pdo, $ent['veiculo_id']),
            'LINK:' . $ent['escopo'] . ':' . $ent['id'] . ':' . bin2hex(random_bytes(6)), $ent['escopo'] === 'OS' ? 'OS' : ($ent['escopo'] === 'ORCAMENTO' ? 'ORCAMENTO' : 'OCORRENCIA'), $ent['id'],
            ['escopo' => $ent['escopo'], 'entidade' => $ent['id']]
        );

        if ($mensagemId === null) {
            $this->voltar($ent, 'erro=fluxo&detalhe=' . urlencode('Não foi possível preparar a mensagem ao cliente.'));
        }

        $status = $comunicacao->enviarMensagem($mensagemId) ?? 'PENDENTE';
        $_SESSION['link_portal_resultado'] = ['status' => $status, 'mensagem_id' => $mensagemId];

        $this->voltar($ent, 'ok=linkenviado');
    }

    public function revogarLinks()
    {
        $this->exigirPost();
        $ent = $this->entidade();

        $n = PortalToken::revogarDaEntidade(new Database(), $ent['escopo'], $ent['id']);
        unset($_SESSION['link_portal_gerado']);

        $this->voltar($ent, 'ok=linksrevogados&n=' . $n);
    }

    /** A previsão que o cliente vê no portal. Estimativa: some da tela quando a OS é finalizada. */
    public function salvarPrevisao()
    {
        $this->exigirPost();
        $osId = (int) ($_POST['id_ordem_servico'] ?? 0);
        $volta = './viewOrdemServico?id=' . $osId;
        $texto = trim((string) ($_POST['previsao'] ?? ''));
        $pdo = new Database();

        $previsao = null;
        if ($texto !== '') {
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $texto) ?: DateTime::createFromFormat('Y-m-d H:i', $texto);

            if (!$dt) {
                header('Location: ' . $volta . '&erro=fluxo&detalhe=' . urlencode('Data e hora da previsão inválidas.'));
                exit;
            }

            if ($dt->getTimestamp() < time() - 3600) {
                header('Location: ' . $volta . '&erro=fluxo&detalhe=' . urlencode('A previsão de conclusão não pode estar no passado.'));
                exit;
            }

            $previsao = $dt->format('Y-m-d H:i:s');
        }

        try {
            Transacao::executar($pdo, function () use ($pdo, $osId, $previsao) {
                $stmt = $pdo->prepare('SELECT status, previsao_conclusao FROM ordens_servico WHERE id_ordem_servico = ? FOR UPDATE');
                $stmt->execute([$osId]);
                $os = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$os) {
                    throw new DomainException('Ordem de serviço não encontrada.');
                }

                if (in_array($os['status'], ['CONCLUIDA', 'CANCELADA'], true)) {
                    throw new DomainException('A OS já foi encerrada.');
                }

                if ($os['previsao_conclusao'] === $previsao) {
                    return;
                }

                $pdo->prepare('UPDATE ordens_servico SET previsao_conclusao = ? WHERE id_ordem_servico = ?')->execute([$previsao, $osId]);

                Historico::registrar(
                    $pdo, 'OS', $osId, $os['status'], $os['status'], $this->usuarioId(),
                    $previsao ? 'Previsão de conclusão: ' . date('d/m/Y H:i', strtotime($previsao)) : 'Previsão de conclusão removida',
                    Historico::ORIGEM_USUARIO
                );
            });
        } catch (DomainException $e) {
            header('Location: ' . $volta . '&erro=fluxo&detalhe=' . urlencode($e->getMessage()));
            exit;
        }

        header('Location: ' . $volta . '&ok=previsao');
        exit;
    }
}
