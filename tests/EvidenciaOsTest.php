<?php

require_once __DIR__ . '/OsTestCase.php';
require_once __DIR__ . '/../app/models/EvidenciaOs.php';

/**
 * Evidências fotográficas da OS.
 *
 * Precisa da migração database/etapa6_evidencias.php aplicada no banco de teste
 * (DB_NAME=mecanize_db_test php database/etapa6_evidencias.php).
 */
final class EvidenciaOsTest extends OsTestCase
{
    private EvidenciaOs $evid;
    /** @var string[] */
    private array $temporarios = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->evid = new EvidenciaOs($this->pdo);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporarios as $f) {
            @unlink($f);
        }

        parent::tearDown(); // Fixtures::limpar apaga as fotos (arquivo e linha) das OS de teste
    }

    private function arquivo(?string $conteudo = null, string $nome = 'foto.png'): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'evid');
        $conteudo ??= base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        file_put_contents($tmp, $conteudo);
        $this->temporarios[] = $tmp;

        return ['name' => $nome, 'tmp_name' => $tmp, 'size' => strlen($conteudo), 'error' => UPLOAD_ERR_OK];
    }

    private function anexar(int $os, string $tipo = 'ANTES', ?string $descricao = 'Disco empenado', ?array $arquivo = null)
    {
        return $this->evid->anexar($os, $tipo, $descricao, $arquivo ?? $this->arquivo(), $this->tec1, false);
    }

    public function testAnexaFotoGuardaForaDaAreaPublicaERegistraNaTrilha(): void
    {
        $os = $this->novaOs();

        $r = $this->anexar($os);

        $this->assertTrue($r['ok'], (string) $r['erro']);
        $e = $this->evid->getById($r['id']);
        $this->assertSame(['ANTES', 'Disco empenado', 'image/png', $this->tec1], [$e['tipo'], $e['descricao'], $e['mime'], (int) $e['enviado_por']]);
        $this->assertMatchesRegularExpression('#^evidencias/\d{4}/\d{2}/[0-9a-f]{32}\.png$#', $e['caminho']);
        $this->assertFileExists(Armazenamento::caminho($e['caminho']));

        $h = $this->pdo->query("SELECT estado_anterior, estado_novo, usuario_id, motivo FROM historico_estados WHERE entidade = 'OS' AND entidade_id = {$os} ORDER BY id_historico DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame($h['estado_anterior'], $h['estado_novo'], 'não muda o estado da OS');
        $this->assertSame($this->tec1, (int) $h['usuario_id']);
        $this->assertStringContainsString('Foto de evidência anexada (antes)', $h['motivo']);
    }

    public function testListaDaMaisRecenteParaAMaisAntigaComQuemEnviou(): void
    {
        $os = $this->novaOs();
        $a = $this->anexar($os, 'ANTES', 'primeira')['id'];
        $b = $this->anexar($os, 'DEPOIS', 'segunda')['id'];

        $lista = $this->evid->daOs($os);

        $this->assertSame([$b, $a], array_map(fn($x) => (int) $x['id_evidencia'], $lista));
        $this->assertNotEmpty($lista[0]['enviado_por_nome']);
    }

    public function testSoImagemEntra(): void
    {
        $os = $this->novaOs();

        $pdf = $this->anexar($os, 'ANTES', null, $this->arquivo("%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF", 'laudo.pdf'));
        $this->assertFalse($pdf['ok']);
        $this->assertStringContainsString('foto', (string) $pdf['erro']);

        $script = $this->anexar($os, 'ANTES', null, $this->arquivo("<?php echo 'x'; ?>", 'foto.jpg'));
        $this->assertFalse($script['ok'], 'script renomeado para .jpg');

        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM evidencias_os WHERE ordem_servico_id = {$os}"));
    }

    public function testValidacoes(): void
    {
        $os = $this->novaOs();

        $this->assertFalse($this->anexar($os, 'QUALQUER')['ok'], 'tipo inválido');
        $this->assertFalse($this->anexar($os, 'ANTES', str_repeat('x', 256))['ok'], 'descrição longa demais');
        $this->assertFalse($this->anexar(99999999)['ok'], 'OS inexistente');
        $this->assertFalse($this->evid->anexar($os, 'ANTES', null, ['name' => 'a.png', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE], $this->tec1, false)['ok'], 'sem arquivo');
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM evidencias_os WHERE ordem_servico_id = {$os}"));
    }

    public function testOsCanceladaNaoRecebeFoto(): void
    {
        $os = $this->novaOs();
        $this->assertTrue($this->mover($os, 'CANCELADA', 'ADMIN', ['motivo' => 'Cliente desistiu do serviço'])['ok']);

        $r = $this->anexar($os);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('cancelada', (string) $r['erro']);
    }

    public function testTetoPorOs(): void
    {
        $os = $this->novaOs();
        $ins = $this->pdo->prepare("INSERT INTO evidencias_os (ordem_servico_id, tipo, nome_original, caminho, mime, tamanho) VALUES (?, 'DURANTE', 'x.png', ?, 'image/png', 10)");

        for ($i = 0; $i < EvidenciaOs::MAX_POR_OS; $i++) {
            $ins->execute([$os, 'evidencias/teste/' . bin2hex(random_bytes(8)) . '.png']);
        }

        $r = $this->anexar($os);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString((string) EvidenciaOs::MAX_POR_OS, (string) $r['erro']);
    }

    public function testOsComFotoNaoPodeSerExcluida(): void
    {
        $os = $this->novaOs();
        $this->assertTrue($this->anexar($os)['ok']);

        $vinculos = $this->os->vinculos($os);

        $this->assertNotEmpty(array_filter($vinculos, fn($v) => str_contains($v, 'foto(s) de evidência')), 'a foto é registro do que foi feito');
        $this->assertFalse($this->os->delete($os));
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM ordens_servico WHERE id_ordem_servico = {$os}"), 'a OS continua existindo');
    }

    public function testQualquerMembroDaEquipeAnexaNaMesmaOs(): void
    {
        $os = $this->novaOs();

        // técnico e administrador anexam à mesma OS (mesma permissão da equipe); a autoria fica registrada
        $this->assertTrue($this->evid->anexar($os, 'ANTES', null, $this->arquivo(), $this->tec1, false)['ok']);
        $this->assertTrue($this->evid->anexar($os, 'DEPOIS', null, $this->arquivo(), $this->adminId, false)['ok']);

        $autores = array_map(fn($x) => (int) $x['enviado_por'], $this->pdo->query("SELECT enviado_por FROM evidencias_os WHERE ordem_servico_id = {$os} ORDER BY id_evidencia")->fetchAll(PDO::FETCH_ASSOC));
        $this->assertSame([$this->tec1, $this->adminId], $autores);
    }
}
