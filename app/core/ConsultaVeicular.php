<?php

require_once __DIR__ . '/HttpTransporte.php';
require_once __DIR__ . '/LeituraPlaca.php';

/**
 * Consulta de dados do VEÍCULO a partir da placa (marca, modelo, ano, cor, combustível, versão).
 *
 * NENHUM provedor é presumido: o endereço, a credencial e o caminho de cada campo na resposta vêm do
 * .env, escritos conforme a documentação do serviço que a oficina contratar. O sistema só entrega ao
 * formulário o que o serviço de fato devolveu nos campos configurados; o que faltou vira "preencha
 * manualmente". Sem configuração, o erro é compreensível e o cadastro manual segue funcionando.
 *
 * Dados pessoais: a consulta é de VEÍCULO. Só os campos da lista branca abaixo atravessam esta classe —
 * nome, documento ou endereço de proprietário que o serviço eventualmente envie são descartados na
 * leitura e nunca chegam à tela nem ao banco.
 *
 *   CONSULTA_PLACA_URL            https://…/{placa}…            (obrigatória; {placa} vira a placa sem hífen)
 *   CONSULTA_PLACA_AUTH_TIPO      nenhum | bearer | cabecalho | consulta     (padrão: bearer)
 *   CONSULTA_PLACA_AUTH_NOME      nome do cabeçalho ou do parâmetro (padrão: Authorization / token)
 *   CONSULTA_PLACA_TOKEN          credencial do serviço
 *   CONSULTA_PLACA_CAMPO_SUCESSO  caminho que precisa ser verdadeiro na resposta (opcional)
 *   CONSULTA_PLACA_CAMPO_<X>      caminho do campo na resposta JSON (ex.: dados.marca); X = MARCA, MODELO,
 *                                 MARCA_MODELO, ANO, ANO_MODELO, COR, COMBUSTIVEL, VERSAO
 */
final class ConsultaVeicular
{
    public const CAMPOS = ['marca', 'modelo', 'marca_modelo', 'ano', 'ano_modelo', 'cor', 'combustivel', 'versao'];

    public const TIMEOUT_SEGUNDOS = 12;
    public const MAX_POR_JANELA = 30;
    public const JANELA_SEGUNDOS = 600;

    /** @var array<string,mixed> */
    private array $cfg;
    private HttpTransporte $http;

    /** @param array<string,mixed>|null $cfg usa o .env quando nulo */
    public function __construct(?array $cfg = null, ?HttpTransporte $http = null)
    {
        $this->cfg = $cfg ?? self::configuracao();
        $this->http = $http ?? new CurlTransporte();
    }

    /** @return array<string,mixed> */
    public static function configuracao(): array
    {
        $v = fn(string $chave, $padrao = '') => function_exists('env') ? trim((string) env($chave, $padrao)) : (string) $padrao;
        $campos = [];

        foreach (self::CAMPOS as $c) {
            $caminho = $v('CONSULTA_PLACA_CAMPO_' . strtoupper($c));

            if ($caminho !== '') {
                $campos[$c] = $caminho;
            }
        }

        $tipo = strtolower($v('CONSULTA_PLACA_AUTH_TIPO', 'bearer'));

        return [
            'url'       => $v('CONSULTA_PLACA_URL'),
            'auth_tipo' => in_array($tipo, ['nenhum', 'bearer', 'cabecalho', 'consulta'], true) ? $tipo : 'bearer',
            'auth_nome' => $v('CONSULTA_PLACA_AUTH_NOME'),
            'token'     => $v('CONSULTA_PLACA_TOKEN'),
            'sucesso'   => $v('CONSULTA_PLACA_CAMPO_SUCESSO'),
            'campos'    => $campos,
        ];
    }

    private static function ehExemplo(string $valor): bool
    {
        return $valor === '' || (bool) preg_match('/^(seu-|sua-|troque|exemplo|xxx|\.\.\.)/i', $valor);
    }

    /** @return string[] nomes das variáveis que faltam (nunca valores) — para a tela de Integrações */
    public function faltando(): array
    {
        $f = [];

        // HTTPS sempre; HTTP só para a própria máquina (mesma regra do transporte), que permite testar contra um serviço local.
        if (self::ehExemplo((string) $this->cfg['url']) || !str_contains((string) $this->cfg['url'], '{placa}') || !preg_match('#^(https://|http://(localhost|127\.0\.0\.1)[:/])#i', (string) $this->cfg['url'])) {
            $f[] = 'CONSULTA_PLACA_URL (HTTPS, com {placa})';
        }

        if ($this->cfg['auth_tipo'] !== 'nenhum' && self::ehExemplo((string) $this->cfg['token'])) {
            $f[] = 'CONSULTA_PLACA_TOKEN';
        }

        $c = $this->cfg['campos'];

        if (!isset($c['marca']) && !isset($c['marca_modelo']) && !isset($c['modelo'])) {
            $f[] = 'CONSULTA_PLACA_CAMPO_MARCA / _MODELO (ou _MARCA_MODELO)';
        }

        return $f;
    }

    public function configurada(): bool
    {
        return $this->faltando() === [];
    }

    /** Cada consulta pode ter custo: limita por sessão (mesmo mecanismo da leitura de placa). */
    public static function dentroDoLimite(array &$historico, int $agora): bool
    {
        return LeituraPlaca::dentroDoLimite($historico, $agora, self::MAX_POR_JANELA, self::JANELA_SEGUNDOS);
    }

    /**
     * @return array{ok:bool, codigo:string, erro:?string, dados:array<string,?string>, ausentes:string[]}
     */
    public function consultar(string $placa): array
    {
        $placa = LeituraPlaca::normalizar($placa);

        if (LeituraPlaca::formato($placa) === null) {
            return $this->falha('PLACA_INVALIDA', 'Informe uma placa válida (ABC1D23 ou ABC-1234) para consultar.');
        }

        if (!$this->configurada()) {
            return $this->falha('NAO_CONFIGURADO', 'A consulta de dados do veículo pela placa não está configurada neste sistema. Preencha os dados manualmente.');
        }

        $url = str_replace('{placa}', rawurlencode($placa), (string) $this->cfg['url']);
        $cabecalhos = ['Accept: application/json'];
        $tipo = (string) $this->cfg['auth_tipo'];

        if ($tipo === 'bearer') {
            $cabecalhos[] = ($this->cfg['auth_nome'] ?: 'Authorization') . ': Bearer ' . $this->cfg['token'];
        } elseif ($tipo === 'cabecalho') {
            $cabecalhos[] = ($this->cfg['auth_nome'] ?: 'X-Api-Key') . ': ' . $this->cfg['token'];
        } elseif ($tipo === 'consulta') {
            $url .= (str_contains($url, '?') ? '&' : '?') . rawurlencode($this->cfg['auth_nome'] ?: 'token') . '=' . rawurlencode((string) $this->cfg['token']);
        }

        $r = $this->http->requisitar('GET', $url, $cabecalhos, null, self::TIMEOUT_SEGUNDOS);

        if ($r['status'] === 0) {
            error_log('[consulta veicular] sem resposta: ' . ($r['erro'] ?? '?'));
            $tempo = stripos((string) $r['erro'], 'time') !== false;

            return $this->falha($tempo ? 'TEMPO_ESGOTADO' : 'INDISPONIVEL', $tempo
                ? 'O serviço de consulta demorou demais. Tente de novo ou preencha manualmente.'
                : 'Não foi possível falar com o serviço de consulta agora. Preencha os dados manualmente.');
        }

        if ($r['status'] === 404) {
            return $this->falha('NAO_ENCONTRADA', 'O serviço não encontrou dados para esta placa. Preencha manualmente.');
        }

        if ($r['status'] === 429) {
            error_log('[consulta veicular] limite do plano (HTTP 429)');

            return $this->falha('LIMITE', 'O limite de consultas do plano foi atingido. Preencha manualmente.');
        }

        if ($r['status'] === 401 || $r['status'] === 403) {
            error_log("[consulta veicular] credencial recusada (HTTP {$r['status']})");

            return $this->falha('NAO_CONFIGURADO', 'O serviço de consulta recusou a credencial configurada. Avise o administrador e preencha manualmente.');
        }

        if ($r['status'] < 200 || $r['status'] >= 300) {
            error_log("[consulta veicular] HTTP {$r['status']}: " . substr($r['corpo'], 0, 300));

            return $this->falha('INDISPONIVEL', 'O serviço de consulta não respondeu. Preencha os dados manualmente.');
        }

        $json = json_decode($r['corpo'], true);

        if (!is_array($json)) {
            return $this->falha('INDISPONIVEL', 'O serviço de consulta devolveu uma resposta ilegível. Preencha os dados manualmente.');
        }

        if ($this->cfg['sucesso'] !== '' && !self::extrair($json, (string) $this->cfg['sucesso'])) {
            return $this->falha('NAO_ENCONTRADA', 'O serviço não encontrou dados para esta placa. Preencha manualmente.');
        }

        // Só os campos configurados atravessam: o resto da resposta (inclusive qualquer dado de proprietário) é descartado.
        $dados = array_fill_keys(self::CAMPOS, null);

        foreach ($this->cfg['campos'] as $campo => $caminho) {
            $valor = self::extrair($json, $caminho);

            if (is_scalar($valor) && trim((string) $valor) !== '') {
                $dados[$campo] = mb_substr(trim((string) $valor), 0, 80);
            }
        }

        if (!array_filter($dados)) {
            return $this->falha('NAO_ENCONTRADA', 'O serviço não devolveu dados do veículo para esta placa. Preencha manualmente.');
        }

        return [
            'ok' => true, 'codigo' => 'OK', 'erro' => null, 'dados' => $dados,
            'ausentes' => array_keys(array_filter($dados, fn($v) => $v === null)),
        ];
    }

    private function falha(string $codigo, string $erro): array
    {
        return ['ok' => false, 'codigo' => $codigo, 'erro' => $erro, 'dados' => array_fill_keys(self::CAMPOS, null), 'ausentes' => self::CAMPOS];
    }

    /* ── Leitura da resposta e ligação com o cadastro do sistema (puras, testáveis sem banco) ── */

    /** Caminho com pontos (dados.veiculo.marca, itens.0.cor). Ausente → null. */
    public static function extrair(array $json, string $caminho)
    {
        $atual = $json;

        foreach (explode('.', $caminho) as $parte) {
            if (!is_array($atual) || !array_key_exists($parte, $atual)) {
                return null;
            }

            $atual = $atual[$parte];
        }

        return $atual;
    }

    public static function chave(string $texto): string
    {
        $t = mb_strtoupper(trim($texto));
        $t = strtr($t, ['Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'É' => 'E', 'Ê' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ú' => 'U', 'Ç' => 'C']);

        return preg_replace('/[^A-Z0-9]+/', '', $t);
    }

    /**
     * @param array<int,string> $marcas id => nome cadastrado
     * @return int|null só quando bate de forma inequívoca (igual, ou o texto começa/termina pelo nome)
     */
    public static function escolherMarca(array $marcas, string $texto): ?int
    {
        $alvo = self::chave($texto);

        if ($alvo === '') {
            return null;
        }

        foreach ($marcas as $id => $nome) {
            if (self::chave($nome) === $alvo) {
                return (int) $id;
            }
        }

        // "VW", "VOLKSWAGEN/GOL": a marca vem antes da barra ou como prefixo.
        $candidatos = [];
        foreach ($marcas as $id => $nome) {
            $k = self::chave($nome);

            if (strlen($k) >= 3 && str_starts_with($alvo, $k)) {
                $candidatos[$id] = strlen($k);
            }
        }

        if ($candidatos) {
            arsort($candidatos);

            return (int) array_key_first($candidatos);
        }

        $abreviacoes = ['VW' => 'VOLKSWAGEN', 'GM' => 'CHEVROLET', 'CHEV' => 'CHEVROLET', 'MB' => 'MERCEDESBENZ', 'MERCEDES' => 'MERCEDESBENZ'];
        if (isset($abreviacoes[$alvo])) {
            foreach ($marcas as $id => $nome) {
                if (self::chave($nome) === $abreviacoes[$alvo]) {
                    return (int) $id;
                }
            }
        }

        return null;
    }

    /**
     * "VW/GOL 1.0 MI" → marca Volkswagen e o resto ("GOL 1.0 MI") para casar o modelo.
     *
     * @param array<int,string> $marcas
     * @return array{0:?int, 1:string}
     */
    public static function separarMarcaModelo(array $marcas, string $texto): array
    {
        $partes = preg_split('#\s*[/\-]\s*|\s+#', trim($texto), 2);
        $primeira = $partes[0] ?? '';
        $marca = self::escolherMarca($marcas, $primeira);

        // Marca de duas palavras ("LAND ROVER", "ALFA ROMEO").
        if ($marca === null && preg_match('/^(\S+\s+\S+)\s*[\/\- ]\s*(.*)$/u', trim($texto), $m)) {
            $dupla = self::escolherMarca($marcas, $m[1]);

            if ($dupla !== null) {
                return [$dupla, trim($m[2])];
            }
        }

        return [$marca, $marca !== null ? trim((string) ($partes[1] ?? '')) : trim($texto)];
    }

    /**
     * @param array<int,string> $modelos id => nome (os da marca)
     * @return int|null o modelo cadastrado mais específico que aparece no texto do serviço ("GOL 1.0 MI" → Gol)
     */
    public static function escolherModelo(array $modelos, string $texto): ?int
    {
        $alvo = self::chave($texto);

        if ($alvo === '') {
            return null;
        }

        $melhor = null;
        $tamanho = 0;

        foreach ($modelos as $id => $nome) {
            $k = self::chave($nome);

            if ($k === '') {
                continue;
            }

            if ($k === $alvo) {
                return (int) $id;
            }

            // O nome cadastrado precisa ser um PREFIXO do texto: "UNO" casa "UNO MILLE", mas "UNO" não casa "FIORINO UNO".
            if (str_starts_with($alvo, $k) && strlen($k) > $tamanho) {
                $melhor = (int) $id;
                $tamanho = strlen($k);
            }
        }

        return $melhor;
    }

    /** @param string[] $lista combustíveis aceitos pelo sistema */
    public static function combustivel(string $texto, array $lista): ?string
    {
        $k = self::chave($texto);
        $sinonimos = [
            'ALCOOL' => ['ALCOOL', 'ETANOL'], 'GASOLINA' => ['GASOLINA'], 'DIESEL' => ['DIESEL', 'DIESELS10', 'DIESELS500'],
            'FLEX' => ['FLEX', 'GASOLINAALCOOL', 'ALCOOLGASOLINA', 'GASOLINAETANOL', 'ETANOLGASOLINA', 'TOTALFLEX'],
            'ELETRICO' => ['ELETRICO', 'ELETRICA', 'ELECTRIC'], 'HIBRIDO' => ['HIBRIDO', 'HIBRIDA', 'GASOLINAELETRICO', 'GASOLINAELETRICA'],
            'GNV' => ['GNV', 'GASNATURAL'],
        ];

        $familia = null;
        foreach ($sinonimos as $nome => $variantes) {
            if (in_array($k, $variantes, true)) {
                $familia = $nome;
                break;
            }
        }

        foreach ($lista as $item) {
            $ki = self::chave($item);

            // "Flex (Álcool/Gasolina)" é o rótulo do sistema para a família FLEX; as demais famílias só casam por igualdade/sinônimo.
            if ($ki === $k || ($familia !== null && ($ki === $familia || in_array($ki, $sinonimos[$familia], true) || ($familia === 'FLEX' && str_starts_with($ki, 'FLEX'))))) {
                return $item;
            }
        }

        return null;
    }

    /** @param string[] $lista cores do sistema */
    public static function cor(string $texto, array $lista): ?string
    {
        $k = self::chave($texto);

        foreach ($lista as $item) {
            if (self::chave($item) === $k) {
                return $item;
            }
        }

        return null;
    }
}
