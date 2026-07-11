<?php

class Caixa
{
    public $conn;

    public function __construct()
    {
        $this->conn = new Database();
    }

    /**
     * Devolve a sessão de caixa atualmente ABERTA (com o nome de quem abriu),
     * ou false se não houver nenhuma. Só pode existir uma aberta por vez.
     */
    public function getCaixaAberto()
    {
        $sql = "SELECT c.*, ua.nome AS abertura_nome
                FROM caixa c
                INNER JOIN usuarios ua ON ua.id_usuario = c.usuario_abertura_id
                WHERE c.status = 'ABERTO'
                ORDER BY c.id_caixa DESC
                LIMIT 1";

        $stmt = $this->conn->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Abre uma nova sessão de caixa. Não permite abrir se já houver uma aberta.
     * Retorna true em sucesso, false caso já exista caixa aberto.
     */
    public function abrir($usuarioId, $valorAbertura, $observacao = null)
    {
        if ($this->getCaixaAberto()) {
            return false;
        }

        $sql = "INSERT INTO caixa (
                    usuario_abertura_id,
                    valor_abertura,
                    observacao_abertura,
                    status
                ) VALUES (
                    :usuario_id,
                    :valor,
                    :obs,
                    'ABERTO'
                )";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':valor'      => $this->parseMoney($valorAbertura),
            ':obs'        => ($observacao !== null && trim($observacao) !== '') ? trim($observacao) : null,
        ]);
    }

    /**
     * Fecha a sessão de caixa aberta, registrando quem fechou, quando e o valor.
     * Retorna true em sucesso, false se não houver caixa aberto.
     */
    /**
     * $valorEsperado: o que o resumoSessao() calculava (abertura + entradas em
     * dinheiro − saídas) NO MOMENTO do fechamento — guardado pra o histórico
     * poder mostrar "contado − esperado" depois (a real diferença de caixa),
     * em vez de só "fechamento − abertura" (variação líquida do dia).
     */
    public function fechar($usuarioId, $valorFechamento, $observacao = null, $valorEsperado = null)
    {
        $aberto = $this->getCaixaAberto();

        if (!$aberto) {
            return false;
        }

        $sql = "UPDATE caixa
                SET usuario_fechamento_id = :usuario_id,
                    data_fechamento       = NOW(),
                    valor_fechamento      = :valor,
                    valor_esperado        = :esperado,
                    observacao_fechamento = :obs,
                    status                = 'FECHADO'
                WHERE id_caixa = :id
                  AND status = 'ABERTO'";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':valor'      => $this->parseMoney($valorFechamento),
            ':esperado'   => $valorEsperado !== null ? $this->parseMoney($valorEsperado) : null,
            ':obs'        => ($observacao !== null && trim($observacao) !== '') ? trim($observacao) : null,
            ':id'         => $aberto['id_caixa'],
        ]);
    }

    /**
     * Valor de fechamento da última sessão já FECHADA — é o "saldo do caixa
     * anterior", sugerido como valor de abertura da próxima sessão.
     * Retorna 0.0 quando ainda não houve nenhum fechamento.
     */
    public function getUltimoFechamento()
    {
        $sql = "SELECT valor_fechamento
                FROM caixa
                WHERE status = 'FECHADO' AND valor_fechamento IS NOT NULL
                ORDER BY id_caixa DESC
                LIMIT 1";

        $valor = $this->conn->query($sql)->fetchColumn();

        return $valor !== false ? (float) $valor : 0.0;
    }

    /**
     * Histórico de sessões de caixa, com nomes de quem abriu e fechou.
     * A diferença (fechamento - abertura) é calculada em PHP na view.
     */
    public function historico($limite = 100)
    {
        $limite = (int) $limite;

        $sql = "SELECT
                    c.*,
                    ua.nome AS abertura_nome,
                    uf.nome AS fechamento_nome
                FROM caixa c
                INNER JOIN usuarios ua ON ua.id_usuario = c.usuario_abertura_id
                LEFT  JOIN usuarios uf ON uf.id_usuario = c.usuario_fechamento_id
                ORDER BY c.id_caixa DESC
                LIMIT {$limite}";

        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Normaliza o valor monetário recebido do formulário para float.
     * O front-end envia o valor cru (ex.: "1234.56") via IMask; este parser
     * também tolera o formato pt-BR ("1.234,56") caso o JS esteja desativado,
     * evitando perda silenciosa de dados.
     */
    private function parseMoney($valor)
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return 0.0;
        }

        // Mantém apenas dígitos, vírgula, ponto e sinal de menos
        $valor = preg_replace('/[^\d,.\-]/', '', $valor);

        if (strpos($valor, ',') !== false && strpos($valor, '.') !== false) {
            // Formato pt-BR "1.234,56" → "1234.56"
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        } else {
            // "1234,56" → "1234.56" | "1234.56" permanece
            $valor = str_replace(',', '.', $valor);
        }

        return (float) $valor;
    }

    public function fecharSePendenteExpirado($segundosLimite = 12)
    {
        $sql = "SELECT * FROM caixa
            WHERE status = 'ABERTO'
              AND pendente_fechamento_em IS NOT NULL
              AND pendente_fechamento_em <= (NOW() - INTERVAL :seg SECOND)
            LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':seg', (int) $segundosLimite, PDO::PARAM_INT);
        $stmt->execute();
        $aberto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$aberto) {
            return false;
        }

        require_once __DIR__ . '/MovimentacaoCaixa.php';
        $mov    = new MovimentacaoCaixa();
        $resumo = $mov->resumoSessao($aberto['id_caixa']);

        return $this->fechar(
            $aberto['usuario_abertura_id'],
            $resumo['caixa_atual'],
            'Fechado automaticamente — aba do navegador foi fechada',
            $resumo['caixa_atual']
        );
    }
}
