<?php

class PlacaController extends Controller
{
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

        if (!isset($_FILES['imagem']) || empty($_FILES['imagem']['tmp_name'])) {
            echo json_encode([
                'sucesso' => false,
                'erro' => 'Nenhuma imagem chegou no PHP.',
                'files' => $_FILES
            ]);
            return;
        }

        $apiToken = env('PLATE_RECOGNIZER_TOKEN');

        $imagem = $_FILES['imagem']['tmp_name'];

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.platerecognizer.com/v1/plate-reader/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Token ' . $apiToken
            ],
            CURLOPT_POSTFIELDS => [
                'upload' => new CURLFile($imagem, $_FILES['imagem']['type'], $_FILES['imagem']['name'])
            ]
        ]);

        $resposta = curl_exec($curl);
        $erroCurl = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($erroCurl) {
            echo json_encode([
                'sucesso' => false,
                'erro' => 'Erro CURL: ' . $erroCurl
            ]);
            return;
        }

        $dados = json_decode($resposta, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            echo json_encode([
                'sucesso' => false,
                'erro' => 'Erro na API. HTTP ' . $httpCode,
                'resposta' => $dados ?: $resposta
            ]);
            return;
        }

        if (empty($dados['results'][0]['plate'])) {
            echo json_encode([
                'sucesso' => false,
                'erro' => 'Nenhuma placa encontrada na imagem.',
                'resposta' => $dados
            ]);
            return;
        }

        echo json_encode([
            'sucesso' => true,
            'placa' => strtoupper($dados['results'][0]['plate']),
            'resposta' => $dados
        ]);
    }
}
