<?php

require_once __DIR__ . '/../core/LeituraPlaca.php';

/**
 * Leitura de placa pela foto (JSON). A resposta é uma SUGESTÃO: a tela mostra para conferência e só
 * preenche o campo depois que a pessoa confirma — uma placa duvidosa nunca é registrada sozinha.
 */
class PlacaController extends Controller
{
    private function responder(array $dados, int $http = 200): void
    {
        http_response_code($http);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        // `sucesso`/`erro` continuam existindo (as telas antigas liam esses nomes).
        echo json_encode(['sucesso' => $dados['ok'] ?? false] + $dados, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function ler()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->responder(['ok' => false, 'codigo' => 'METODO', 'erro' => 'Método inválido. Use POST.'], 405);
        }

        /*
         * O arquivo vem do navegador: confere o upload de verdade (error, is_uploaded_file) em vez de confiar
         * em $_FILES['type'] (campo do cliente) — o tipo real sai do CONTEÚDO, em LeituraPlaca. A resposta
         * nunca devolve $_FILES nem caminhos do servidor.
         */
        $arquivo = $_FILES['imagem'] ?? null;

        if (!$arquivo || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || empty($arquivo['tmp_name']) || !is_uploaded_file($arquivo['tmp_name'])) {
            $grande = ($arquivo['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($arquivo['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE;

            $this->responder(['ok' => false, 'codigo' => $grande ? 'TAMANHO' : 'IMAGEM_INVALIDA',
                'erro' => $grande ? 'A foto é grande demais. Tire de novo, mais perto da placa.' : 'Nenhuma imagem foi recebida. Tire a foto novamente.',
                'candidatos' => [], 'requer_conferencia' => true]);
        }

        $leitor = new LeituraPlaca();

        if (!$leitor->configurada()) {
            $this->responder(['ok' => false, 'codigo' => 'NAO_CONFIGURADO', 'erro' => 'A leitura automática de placa não está configurada neste sistema. Digite a placa.',
                'candidatos' => [], 'requer_conferencia' => true]);
        }

        // Cada leitura custa no plano contratado: limita por sessão.
        $historico = (array) ($_SESSION['placa_leituras'] ?? []);

        if (!LeituraPlaca::dentroDoLimite($historico, time())) {
            $_SESSION['placa_leituras'] = $historico;
            $this->responder(['ok' => false, 'codigo' => 'LIMITE_SESSAO',
                'erro' => 'Muitas leituras seguidas. Aguarde alguns minutos ou digite a placa.', 'candidatos' => [], 'requer_conferencia' => true], 429);
        }

        $_SESSION['placa_leituras'] = $historico;

        $this->responder($leitor->ler($arquivo['tmp_name']));
    }
}
