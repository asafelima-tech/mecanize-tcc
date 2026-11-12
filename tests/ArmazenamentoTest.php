<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/core/Armazenamento.php';

/**
 * Armazenamento de arquivos enviados (storage/, fora da área pública) — sem banco.
 *
 * O tipo é decidido pelo CONTEÚDO, o nome gravado é aleatório e ninguém sai de storage/ pelo caminho.
 */
final class ArmazenamentoTest extends TestCase
{
    private const PASTA = 'testes_phpunit';

    /** @var string[] */
    private array $temporarios = [];

    protected function tearDown(): void
    {
        foreach ($this->temporarios as $f) {
            @unlink($f);
        }

        $pasta = Armazenamento::raiz() . DIRECTORY_SEPARATOR . self::PASTA;

        if (is_dir($pasta)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pasta, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }

            @rmdir($pasta);
        }
    }

    private function arquivo(string $conteudo, string $nome = 'foto.png'): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'arm');
        file_put_contents($tmp, $conteudo);
        $this->temporarios[] = $tmp;

        return ['name' => $nome, 'tmp_name' => $tmp, 'size' => strlen($conteudo), 'error' => UPLOAD_ERR_OK];
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    }

    private function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
    }

    public function testGuardaComNomeAleatorioETipoPeloConteudo(): void
    {
        $r = Armazenamento::guardar($this->arquivo($this->png(), 'minha foto ../../x.php.png'), self::PASTA, false);

        $this->assertSame('image/png', $r['mime']);
        $this->assertMatchesRegularExpression('#^' . self::PASTA . '/\d{4}/\d{2}/[0-9a-f]{32}\.png$#', $r['caminho'], 'nome aleatório, extensão do tipo REAL');
        $this->assertStringNotContainsString('..', $r['nome_original'], 'o nome de exibição não carrega caminho');
        $this->assertNotNull(Armazenamento::caminho($r['caminho']));
        $this->assertFileExists(Armazenamento::caminho($r['caminho']));
    }

    public function testScriptRenomeadoParaImagemNaoPassa(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Tipo de arquivo não aceito');

        Armazenamento::guardar($this->arquivo("<?php echo 'invadido'; ?>", 'foto.png'), self::PASTA, false);
    }

    public function testRestricaoDeTiposPermiteSoOQueATelaPediu(): void
    {
        // Sem restrição o PDF é aceito (comprovantes)...
        $this->assertSame('application/pdf', Armazenamento::guardar($this->arquivo($this->pdf(), 'nota.pdf'), self::PASTA, false)['mime']);

        // ...mas evidência é só imagem.
        try {
            Armazenamento::guardar($this->arquivo($this->pdf(), 'nota.pdf'), self::PASTA, false, ['image/jpeg', 'image/png', 'image/webp']);
            $this->fail('PDF não deveria passar como foto');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Envie uma foto', $e->getMessage());
        }

        $this->assertSame('image/png', Armazenamento::guardar($this->arquivo($this->png()), self::PASTA, false, ['image/png'])['mime']);
    }

    public function testRecusaVazioGrandeEErrosDeUpload(): void
    {
        foreach ([
            [$this->arquivo(''), 'vazio'],
            [$this->arquivo($this->png() . str_repeat('0', Armazenamento::LIMITE_BYTES)), 'maior que o permitido'],
            [['name' => 'a.png', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE], 'Escolha o arquivo'],
            [['name' => 'a.png', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_INI_SIZE], 'maior que o permitido'],
            [['name' => 'a.png', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_PARTIAL], 'Não foi possível receber'],
        ] as [$arquivo, $trecho]) {
            try {
                Armazenamento::guardar($arquivo, self::PASTA, false);
                $this->fail("deveria recusar ({$trecho})");
            } catch (DomainException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage());
            }
        }
    }

    public function testUploadQueNaoVeioDoPhpEhRecusado(): void
    {
        // Com exigirUpload (o padrão em produção), um caminho qualquer do disco não vale: só arquivo recebido por POST.
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Envio inválido');

        Armazenamento::guardar($this->arquivo($this->png()), self::PASTA, true);
    }

    public function testCaminhoNaoSaiDeStorage(): void
    {
        $this->assertNull(Armazenamento::caminho('../app/core/Env.php'));
        $this->assertNull(Armazenamento::caminho('../../.env'));
        $this->assertNull(Armazenamento::caminho('nao/existe.png'));
        $this->assertNull(Armazenamento::caminho(''));
    }
}
