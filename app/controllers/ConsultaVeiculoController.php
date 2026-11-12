<?php

require_once __DIR__ . '/../core/ConsultaVeicular.php';

/**
 * "Buscar dados pela placa" (JSON). Devolve SUGESTÕES para o formulário do veículo: o que o serviço
 * contratado informou, ligado ao cadastro de marcas/modelos/cores do sistema SÓ quando a ligação é
 * inequívoca. O que o serviço não informou (ou não casa com o cadastro) volta listado para ser
 * preenchido à mão — nada é inventado nem gravado aqui. Dados de proprietário não atravessam
 * (ver ConsultaVeicular).
 */
class ConsultaVeiculoController extends Controller
{
    private function responder(array $dados, int $http = 200): void
    {
        http_response_code($http);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['sucesso' => $dados['ok'] ?? false] + $dados, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function consultar()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->responder(['ok' => false, 'codigo' => 'METODO', 'erro' => 'Método inválido. Use POST.'], 405);
        }

        $servico = new ConsultaVeicular();

        // Cada consulta pode ter custo: teto por sessão.
        $historico = (array) ($_SESSION['consultas_placa'] ?? []);

        if (!ConsultaVeicular::dentroDoLimite($historico, time())) {
            $_SESSION['consultas_placa'] = $historico;
            $this->responder(['ok' => false, 'codigo' => 'LIMITE_SESSAO', 'erro' => 'Muitas consultas seguidas. Aguarde alguns minutos ou preencha manualmente.'], 429);
        }

        $_SESSION['consultas_placa'] = $historico;

        $r = $servico->consultar((string) ($_POST['placa'] ?? ''));

        if (!$r['ok']) {
            $this->responder(['ok' => false, 'codigo' => $r['codigo'], 'erro' => $r['erro']]);
        }

        $d = $r['dados'];
        $db = new Database();
        $marcas = $db->query("SELECT id_marca, nome FROM marcas_veiculos WHERE nome <> 'Não informado' ORDER BY nome")->fetchAll(PDO::FETCH_KEY_PAIR);

        $marcaId = null;
        $modeloTexto = $d['modelo'];

        if ($d['marca'] !== null) {
            $marcaId = ConsultaVeicular::escolherMarca($marcas, $d['marca']);
        } elseif ($d['marca_modelo'] !== null) {
            [$marcaId, $resto] = ConsultaVeicular::separarMarcaModelo($marcas, $d['marca_modelo']);
            $modeloTexto = $modeloTexto ?? $resto;
        }

        $modeloId = null;

        if ($marcaId !== null && $modeloTexto) {
            $stmt = $db->prepare('SELECT id_modelo, nome FROM modelos_veiculos WHERE marca_id = ? ORDER BY nome');
            $stmt->execute([$marcaId]);
            $modeloId = ConsultaVeicular::escolherModelo($stmt->fetchAll(PDO::FETCH_KEY_PAIR), $modeloTexto);
        }

        $cores = array_keys(require __DIR__ . '/../views/Veiculos/_coresCarro.php');
        $combustiveis = require __DIR__ . '/../views/Veiculos/_combustiveis.php';
        $cor = $d['cor'] !== null ? ConsultaVeicular::cor($d['cor'], $cores) : null;
        $combustivel = $d['combustivel'] !== null ? ConsultaVeicular::combustivel($d['combustivel'], $combustiveis) : null;

        $ano = $d['ano'] ?? $d['ano_modelo'];
        $ano = ($ano !== null && preg_match('/^\d{4}$/', $ano) && (int) $ano >= 1950 && (int) $ano <= (int) date('Y') + 1) ? (int) $ano : null;

        // O que foi preenchido e o que o formulário deixa para a pessoa — com o motivo de cada um.
        $preenchidos = [];
        $manuais = [];
        $registro = function (string $rotulo, $valor, ?string $textoDoServico, string $motivoSemCasar) use (&$preenchidos, &$manuais) {
            if ($valor !== null) {
                $preenchidos[] = $rotulo;
            } else {
                $manuais[] = $textoDoServico !== null && $textoDoServico !== '' ? "{$rotulo} (o serviço informou \"{$textoDoServico}\": {$motivoSemCasar})" : "{$rotulo} (o serviço não informou)";
            }
        };

        $textoMarca = $d['marca'] ?? $d['marca_modelo'];
        $registro('marca', $marcaId, $textoMarca, 'não está no cadastro de marcas — escolha manualmente');
        $registro('modelo', $modeloId, $modeloTexto, $marcaId === null ? 'escolha a marca primeiro' : 'não está no cadastro de modelos — escolha ou cadastre');
        $registro('ano', $ano, $d['ano'] ?? $d['ano_modelo'], 'valor fora do esperado');
        $registro('cor', $cor, $d['cor'], 'cor fora da lista do sistema');
        $registro('combustível', $combustivel, $d['combustivel'], 'tipo fora da lista do sistema');

        $this->responder([
            'ok'          => true,
            'codigo'      => 'OK',
            'erro'        => null,
            'dados'       => ['marca_id' => $marcaId, 'modelo_id' => $modeloId, 'ano' => $ano, 'cor' => $cor, 'combustivel' => $combustivel, 'versao' => $d['versao']],
            'preenchidos' => $preenchidos,
            'manuais'     => $manuais,
        ]);
    }
}
