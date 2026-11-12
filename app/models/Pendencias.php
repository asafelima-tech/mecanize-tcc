<?php

/**
 * "O que precisa de você agora" — a tela inicial de cada perfil começa pelo que exige ação, não por
 * números de vitrine. Cada pendência é uma contagem de coisas REAIS paradas esperando alguém, com o link
 * para resolvê-las; sem nada pendente, o item nem aparece.
 *
 * Perfis: ATENDENTE = comunicação, cadastros e acompanhamento do cliente; ADMIN = isso + decisões e dinheiro.
 * (O técnico tem o próprio painel de trabalho.) Cada consulta é independente: se uma falhar, as outras
 * continuam — a tela inicial nunca deixa de abrir por causa de uma contagem.
 */
class Pendencias
{
    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    private function contar(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[pendências] ' . $e->getMessage());

            return 0;
        }
    }

    private function valor(string $sql): float
    {
        try {
            return round((float) $this->conn->query($sql)->fetchColumn(), 2);
        } catch (\Throwable $e) {
            error_log('[pendências] ' . $e->getMessage());

            return 0.0;
        }
    }

    /**
     * @return array<int,array{chave:string, titulo:string, detalhe:string, qtd:int, link:string, tom:string, grupo:string}>
     *         tom: alerta (atrasado/risco) | acao (precisa de você) | info
     */
    public function paraPerfil(string $tipo): array
    {
        $ehAdmin = $tipo === 'ADMIN';
        $itens = [];

        $add = function (string $chave, string $grupo, string $titulo, string $detalhe, int $qtd, string $link, string $tom = 'acao') use (&$itens) {
            if ($qtd > 0) {
                $itens[] = compact('chave', 'grupo', 'titulo', 'detalhe', 'qtd', 'link', 'tom');
            }
        };

        /* ── Clientes e atendimento (atendente e administrador) ── */
        $add('conversas', 'Clientes', 'Conversas com mensagem nova', 'O cliente escreveu e ainda ninguém leu.',
            $this->contar("SELECT COUNT(*) FROM conversas WHERE status <> 'ENCERRADA' AND nao_lidas > 0"), './listConversas?nao_lidas=1', 'alerta');

        $add('sem_envio', 'Clientes', 'Avisos que não chegaram ao cliente', 'Falha de envio, cliente sem autorização ou sem telefone/e-mail (últimos 3 dias). Veja na conversa e avise por outro meio.',
            $this->contar("SELECT COUNT(*) FROM mensagens WHERE direcao = 'SAIDA' AND tipo = 'AUTOMATICA' AND status IN ('FALHA','SEM_CONSENTIMENTO','SEM_CONTATO') AND criada_em >= DATE_SUB(NOW(), INTERVAL 3 DAY)"), './listConversas', 'alerta');

        $add('autorizacao', 'Clientes', 'Aguardando autorização do cliente', 'Valor adicional pedido durante o serviço — a OS não segue sem a resposta.',
            $this->contar("SELECT COUNT(*) FROM autorizacoes_cliente WHERE tipo = 'COMPLEMENTAR' AND status = 'SOLICITADA'"), './listOrdemServico', 'alerta');

        $add('sem_resposta', 'Clientes', 'Orçamentos sem resposta há mais de 2 dias', 'Enviados ao cliente e ainda sem aprovação nem recusa. Vale um contato.',
            $this->contar("SELECT COUNT(*) FROM orcamentos WHERE status = 'pendente' AND enviado_em IS NOT NULL AND enviado_em < DATE_SUB(NOW(), INTERVAL 2 DAY)"), './listOrcamentos', 'alerta');

        $add('orcamento_enviar', 'Clientes', 'Orçamentos prontos para enviar', 'Criados nos últimos 30 dias e ainda não enviados ao cliente.',
            $this->contar("SELECT COUNT(*) FROM orcamentos WHERE status = 'pendente' AND enviado_em IS NULL AND data_criacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)"), './listOrcamentos');

        $add('avaliar', 'Oficina', 'Veículos esperando avaliação', 'Chegaram e ainda não foram avaliados pelo técnico.',
            $this->contar("SELECT COUNT(*) FROM ocorrencias WHERE status = 'AGUARDANDO_AVALIACAO'"), './listOcorrencias');

        $retirada = $this->contar("SELECT COUNT(*) FROM ordens_servico WHERE status = 'AGUARDANDO_RETIRADA'");
        $saldo = $ehAdmin ? $this->valor("SELECT COALESCE(SUM(p.valor), 0) FROM ordem_servico_parcelas p JOIN ordens_servico o ON o.id_ordem_servico = p.ordem_servico_id WHERE o.status = 'AGUARDANDO_RETIRADA' AND p.pago = 0") : 0.0;
        $add('retirada', 'Oficina', 'Veículos prontos para retirada', $ehAdmin && $saldo > 0 ? 'R$ ' . number_format($saldo, 2, ',', '.') . ' a receber na retirada.' : 'Liberados na conferência; aguardando o cliente.',
            $retirada, './listOrdemServico', 'info');

        /* ── Decisões e dinheiro (só administrador) ── */
        if ($ehAdmin) {
            $add('conferir', 'Decisões', 'Serviços para conferir', 'O técnico finalizou: só você libera para retirada.',
                $this->contar("SELECT COUNT(*) FROM ordens_servico WHERE status = 'AGUARDANDO_CONFERENCIA'"), './listOrdemServico', 'alerta');

            $add('urgencia', 'Decisões', 'Urgências para validar', 'Declaradas no balcão; a validação sobe o veículo na fila.',
                $this->contar("SELECT COUNT(*) FROM ocorrencias WHERE prioridade = 'ALTA' AND urgencia_validada_em IS NULL AND status NOT IN ('ENCERRADA','CANCELADA')"), './filaOperacional', 'alerta');

            $add('pecas', 'Decisões', 'Solicitações de peça para analisar', 'Pedidos da equipe aguardando análise e compra.',
                $this->contar("SELECT COUNT(*) FROM solicitacoes_peca WHERE status IN ('SOLICITADA','EM_ANALISE')"), './listSolicitacoes');

            $add('receber_vencido', 'Dinheiro', 'Parcelas a receber vencidas', 'Serviços já entregues ou liberados com parcela em atraso.',
                $this->contar("SELECT COUNT(*) FROM ordem_servico_parcelas p JOIN ordens_servico o ON o.id_ordem_servico = p.ordem_servico_id WHERE p.pago = 0 AND p.vencimento < CURDATE() AND o.status IN ('AGUARDANDO_RETIRADA','CONCLUIDA')"), './listContasReceber', 'alerta');

            $add('pagar_vencido', 'Dinheiro', 'Contas a pagar vencidas', 'Parcelas de despesas e faturas já vencidas.',
                $this->contar("SELECT COUNT(*) FROM contas_pagar_parcelas WHERE status = 'PENDENTE' AND vencimento < CURDATE()"), './listContasPagar', 'alerta');

            $add('pagar_semana', 'Dinheiro', 'Contas a pagar nos próximos 7 dias', 'Vencem em até uma semana.',
                $this->contar("SELECT COUNT(*) FROM contas_pagar_parcelas WHERE status = 'PENDENTE' AND vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"), './listContasPagar', 'info');
        }

        // Alertas primeiro, depois o que pede ação, depois informação; dentro de cada tom, o maior volume.
        $peso = ['alerta' => 0, 'acao' => 1, 'info' => 2];
        usort($itens, fn($a, $b) => [$peso[$a['tom']], -$a['qtd']] <=> [$peso[$b['tom']], -$b['qtd']]);

        return $itens;
    }
}
