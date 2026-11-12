<?php

use PHPUnit\Framework\TestCase;

/**
 * O sistema roda no Windows (XAMPP), onde "site/home" e "Site/home" são a mesma pasta — e vai rodar em
 * servidor Linux, onde não são. Estes testes conferem, COM diferenciação de maiúsculas, que todo arquivo
 * citado por view(), model() e require existe com a grafia exata. Sem banco.
 */
final class PortabilidadeTest extends TestCase
{
    private const RAIZ = __DIR__ . '/..';

    private function existeComGrafiaExata(string $base, string $relativo): bool
    {
        $partes = array_filter(explode('/', str_replace('\\', '/', $base . '/' . $relativo)), fn($p) => $p !== '');
        $pilha = [];

        foreach ($partes as $p) {
            if ($p === '.') {
                continue;
            }

            if ($p === '..') {
                array_pop($pilha);
                continue;
            }

            $pilha[] = $p;
        }

        $caminho = array_shift($pilha) . '/';

        foreach ($pilha as $i => $p) {
            $lista = @scandir($caminho);

            if ($lista === false || !in_array($p, $lista, true)) {
                return false;
            }

            $caminho .= $p . ($i < count($pilha) - 1 ? '/' : '');
        }

        return true;
    }

    /** @return string[] caminhos .php do projeto (sem vendor, storage, testes e backups) */
    private function arquivosDoProjeto(): array
    {
        $achados = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::RAIZ, FilesystemIterator::SKIP_DOTS));

        foreach ($it as $f) {
            $p = str_replace('\\', '/', $f->getPathname());

            if (str_ends_with($p, '.php') && !preg_match('#/(vendor|storage|\.git|\.phpunit\.cache|tests|database/backups)/#', $p)) {
                $achados[] = $p;
            }
        }

        return $achados;
    }

    public function testTodaViewCitadaPelosControllersExisteComAGrafiaExata(): void
    {
        $ruins = [];
        $views = realpath(self::RAIZ . '/app/views');

        foreach (glob(self::RAIZ . '/app/controllers/*.php') as $f) {
            if (!preg_match_all("/->view\(\s*['\"]([^'\"]+)['\"]/", (string) file_get_contents($f), $m)) {
                continue;
            }

            foreach ($m[1] as $view) {
                if (!$this->existeComGrafiaExata(str_replace('\\', '/', $views), $view . '.php')) {
                    $ruins[] = basename($f) . " → view('{$view}')";
                }
            }
        }

        $this->assertSame([], $ruins, "Views com grafia que só funciona em Windows (ou que não existem):\n" . implode("\n", $ruins));
    }

    public function testTodaChamadaModelExisteComAGrafiaExata(): void
    {
        $modelos = scandir(self::RAIZ . '/app/models');
        $ruins = [];

        foreach ($this->arquivosDoProjeto() as $p) {
            if (!preg_match_all("/->model\(\s*['\"]([A-Za-z0-9_]+)['\"]/", (string) file_get_contents($p), $m)) {
                continue;
            }

            foreach ($m[1] as $nome) {
                if (!in_array($nome . '.php', $modelos, true)) {
                    $ruins[] = basename($p) . " → model('{$nome}')";
                }
            }
        }

        $this->assertSame([], $ruins, "Models com grafia incorreta:\n" . implode("\n", $ruins));
    }

    public function testTodoRequireComDirExisteComAGrafiaExata(): void
    {
        $ruins = [];

        foreach ($this->arquivosDoProjeto() as $p) {
            if (!preg_match_all("/(?:require|include)(?:_once)?\s*\(?\s*__DIR__\s*\.\s*(['\"])([^'\"\$]+)\\1/", (string) file_get_contents($p), $m)) {
                continue;
            }

            foreach ($m[2] as $relativo) {
                if (!$this->existeComGrafiaExata(dirname($p), $relativo)) {
                    $ruins[] = substr($p, strlen(str_replace('\\', '/', (string) realpath(self::RAIZ))) + 1) . " → {$relativo}";
                }
            }
        }

        $this->assertSame([], $ruins, "require com grafia que só funciona em Windows:\n" . implode("\n", $ruins));
    }
}
