<?php

use PHPUnit\Framework\TestCase;

/**
 * Checagem estática da tabela de rotas, nos dois sentidos:
 *
 *   rota  -> controller/método   (rota registrada que não executa)
 *   link  -> rota                (botão na tela que cai em 404)
 *
 * O segundo sentido existe por causa de um bug real: `toggleStatusUsuario`
 * tinha o método no controller, o método no model e o link na listagem de
 * usuários — só a rota nunca foi registrada. Nada quebrava até alguém
 * clicar no botão de ativar/desativar e receber 404.
 *
 * Não toca no banco: é só leitura de arquivo.
 */
final class RotasTest extends TestCase
{
    private const RAIZ = __DIR__ . '/..';

    /** Destinos "./x" que são arquivo/pasta real, não rota. */
    private const NAO_SAO_ROTA = ['assets', 'index.php'];

    private function rotas(): array
    {
        require self::RAIZ . '/config/routes.php';

        return $routes;
    }

    /** Todos os .php de views e .js de assets. */
    private function arquivosDeTela(): array
    {
        $arquivos = [];

        foreach (['/app/views' => 'php', '/public/assets/js' => 'js'] as $dir => $ext) {
            $iterador = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(self::RAIZ . $dir)
            );

            foreach ($iterador as $arquivo) {
                if ($arquivo->getExtension() === $ext) {
                    $arquivos[] = $arquivo->getPathname();
                }
            }
        }

        return $arquivos;
    }

    public function testTodaRotaApontaParaUmMetodoQueExiste(): void
    {
        $problemas = [];

        foreach ($this->rotas() as $uri => $destino) {
            $arquivo = self::RAIZ . "/app/controllers/{$destino['controller']}.php";

            if (!file_exists($arquivo)) {
                $problemas[] = "{$uri} -> {$destino['controller']}.php não existe";
                continue;
            }

            $codigo = file_get_contents($arquivo);

            if (!preg_match('/function\s+' . preg_quote($destino['method'], '/') . '\s*\(/', $codigo)) {
                $problemas[] = "{$uri} -> {$destino['controller']}::{$destino['method']}() não existe";
            }
        }

        $this->assertSame([], $problemas, "Rotas quebradas:\n" . implode("\n", $problemas));
    }

    public function testTodoLinkDeTelaApontaParaUmaRotaRegistrada(): void
    {
        $rotas     = $this->rotas();
        $problemas = [];

        foreach ($this->arquivosDeTela() as $arquivo) {
            $conteudo = file_get_contents($arquivo);

            // href="./rota", action="./rota", .src = './rota', location='./rota'
            if (!preg_match_all('#[\'"]\./([A-Za-z0-9_\-]+)#', $conteudo, $achados, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($achados[1] as [$rota, $posicao]) {
                if (in_array($rota, self::NAO_SAO_ROTA, true) || isset($rotas[$rota])) {
                    continue;
                }

                $linha = substr_count(substr($conteudo, 0, $posicao), "\n") + 1;
                $nome  = basename(dirname($arquivo)) . '/' . basename($arquivo);

                $problemas[] = "{$nome}:{$linha} aponta para ./{$rota}, que não está em routes.php";
            }
        }

        $problemas = array_values(array_unique($problemas));

        $this->assertSame([], $problemas, "Links quebrados:\n" . implode("\n", $problemas));
    }

    public function testRotasNaoTemDestinoDuplicadoConflitante(): void
    {
        // Duas rotas podem legitimamente cair no mesmo método (ex.: alias),
        // mas a chave da rota precisa ser única — chave repetida no array
        // literal seria silenciosamente sobrescrita pelo PHP.
        $conteudo = file_get_contents(self::RAIZ . '/config/routes.php');

        preg_match_all("/^\s*'([A-Za-z0-9_\-]+)'\s*=>/m", $conteudo, $achados);

        $chaves     = $achados[1];
        $duplicadas = array_keys(array_filter(array_count_values($chaves), fn($n) => $n > 1));

        $this->assertSame([], $duplicadas, 'Rotas declaradas duas vezes: ' . implode(', ', $duplicadas));
    }
}
