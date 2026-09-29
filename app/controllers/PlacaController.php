<?php

class PlacaController extends Controller
{
    /** Foto de celular cabe folgado nisso; acima disso é abuso do endpoint. */
    private const TAMANHO_MAXIMO = 2 * 1024 * 1024; // 2 MB

    /** Formatos que o leitor de placas aceita. */
    private const TIPOS_ACEITOS = ['image/jpeg', 'image/png', 'image/webp'];

    /** Extensão usada no nome enviado à API, a partir do tipo detectado. */
    private const EXTENSOES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public function ler()
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode([
                'sucesso' => false,
                'erro' => 'Método inválido. Use POST.'
            ]);
            return;
        }

        if (
            !isset($_FILES['imagem'])
            || ($_FILES['imagem']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_uploaded_file($_FILES['imagem']['tmp_name'] ?? '')
        ) {
            // A resposta antiga devolvia $_FILES inteiro para ajudar a depurar,
            // e junto vinha o caminho absoluto das pastas do servidor — mapa
            // grátis da instalação para quem chamasse o endpoint.
            echo json_encode([
                'sucesso' => false,
                'erro' => 'Nenhuma imagem foi recebida. Tente tirar a foto novamente.'
            ]);
            return;
        }

        $imagem = $_FILES['imagem']['tmp_name'];

        if (filesize($imagem) > self::TAMANHO_MAXIMO) {
            echo json_encode([
                'sucesso' => false,
                'erro' => 'Imagem muito grande. O limite é ' . (self::TAMANHO_MAXIMO / 1048576) . ' MB.'
            ]);
            return;
        }

        /*
         * getimagesize() abre o arquivo e lê o cabeçalho real da imagem. É
         * diferente de olhar $_FILES['imagem']['type'], que é só o que o
         * navegador (ou quem estiver chamando o endpoint na mão) afirmou ser —
         * um .exe renomeado para .jpg passaria por lá sem problema. O tipo
         * usado no envio à API também passa a ser o detectado aqui, não o
         * declarado por quem enviou.
         */
        $info = @getimagesize($imagem);

        if ($info === false || !in_array($info['mime'], self::TIPOS_ACEITOS, true)) {
            echo json_encode([
                'sucesso' => false,
                'erro' => 'O arquivo enviado não é uma imagem válida (aceitos: JPG, PNG, WebP).'
            ]);
            return;
        }

        $apiToken = env('PLATE_RECOGNIZER_TOKEN');

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.platerecognizer.com/v1/plate-reader/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Token ' . $apiToken
            ],
            CURLOPT_POSTFIELDS => [
                'upload' => new CURLFile($imagem, $info['mime'], 'placa.' . self::EXTENSOES[$info['mime']])
            ]
        ]);

        $resposta = curl_exec($curl);
        $erroCurl = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        /*
         * O detalhe técnico da falha vai para o log, não para a tela. A
         * resposta crua da API e a mensagem do cURL descrevem a infraestrutura
         * (endereço chamado, motivo da recusa, situação da chave de acesso) e
         * não ajudam em nada quem está no balcão tentando ler uma placa.
         */
        if ($erroCurl) {
            error_log('[lerPlaca] Falha de conexão com a API de placas: ' . $erroCurl);

            echo json_encode([
                'sucesso' => false,
                'erro' => 'Não foi possível falar com o leitor de placas. Digite a placa manualmente.'
            ]);
            return;
        }

        $dados = json_decode($resposta, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('[lerPlaca] API respondeu HTTP ' . $httpCode . ': ' . $resposta);

            echo json_encode([
                'sucesso' => false,
                'erro' => 'O leitor de placas não respondeu como esperado. Digite a placa manualmente.'
            ]);
            return;
        }

        if (empty($dados['results'][0]['plate'])) {
            echo json_encode([
                'sucesso' => false,
                'erro' => 'Nenhuma placa encontrada na imagem. Tente outra foto, mais próxima e sem reflexo.'
            ]);
            return;
        }

        echo json_encode([
            'sucesso' => true,
            'placa' => strtoupper($dados['results'][0]['plate'])
        ]);
    }
}
