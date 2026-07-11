<?php

use PHPUnit\Framework\TestCase;

/**
 * Cobre os índices únicos adicionados em 2026-07-10 (database/add_indices_unicos.php)
 * como backstop das checagens de duplicidade que só existiam a nível de
 * aplicação — e confirma que uma violação vira erro amigável (Cliente::lastError),
 * não uma exceção não tratada (tela 500).
 */
final class ClienteTest extends TestCase
{
    private PDO $pdo;
    private array $idsCriados = [];

    protected function setUp(): void
    {
        $this->pdo = new Database();
        require_once __DIR__ . '/../app/models/Cliente.php';
    }

    protected function tearDown(): void
    {
        foreach ($this->idsCriados as $id) {
            $this->pdo->exec("DELETE FROM clientes WHERE id_cliente = {$id}");
        }
        $this->idsCriados = [];
    }

    /**
     * Cliente::create() usa sua própria conexão interna (Database própria),
     * separada da $this->pdo deste teste — por isso lastInsertId() não pode
     * ser lido daqui. Busca o ID de volta pelo nome (único em cada teste).
     */
    private function idPorNome(string $nome): int
    {
        $stmt = $this->pdo->prepare('SELECT id_cliente FROM clientes WHERE nome = ? ORDER BY id_cliente DESC LIMIT 1');
        $stmt->execute([$nome]);
        return (int) $stmt->fetchColumn();
    }

    private function registrar(int $id): void
    {
        $this->idsCriados[] = $id;
    }

    public function testNaoPermiteEmailDuplicadoEntreDoisClientesAtivos(): void
    {
        $cliente = new Cliente();

        $ok1 = $cliente->create(['tipo_pessoa' => 'FISICA', 'nome' => 'PHPUnit Dup A', 'email' => 'phpunit.dup@teste.local', 'ativo' => 1]);
        $this->assertTrue($ok1);
        $this->registrar($this->idPorNome('PHPUnit Dup A'));

        $ok2 = $cliente->create(['tipo_pessoa' => 'FISICA', 'nome' => 'PHPUnit Dup B', 'email' => 'phpunit.dup@teste.local', 'ativo' => 1]);

        $this->assertFalse($ok2, 'segundo cliente ativo com o mesmo e-mail deveria ser bloqueado');
        $this->assertNotEmpty($cliente->getLastError(), 'deve setar uma mensagem amigável, não estourar exceção');
    }

    public function testPermiteEmailDuplicadoEntreClienteAtivoEInativo(): void
    {
        $cliente = new Cliente();

        // Fluxo real: cliente A fica inativo (soft-delete) e um cliente B
        // novo reusa o mesmo e-mail — precisa continuar permitido. Criar B
        // já como inativo com o e-mail de A ativo é bloqueado de propósito
        // (emailExisteAtivo() não olha o ativo do registro sendo criado,
        // só se ALGUÉM ativo já usa o e-mail) — não é esse o cenário real.
        $ok1 = $cliente->create(['tipo_pessoa' => 'FISICA', 'nome' => 'PHPUnit AtivoInativo A', 'email' => 'phpunit.ativoinativo@teste.local', 'ativo' => 1]);
        $this->assertTrue($ok1);
        $idA = $this->idPorNome('PHPUnit AtivoInativo A');
        $this->registrar($idA);

        $this->assertTrue($cliente->delete($idA), 'desativação do cliente A deveria funcionar');

        $ok2 = $cliente->create(['tipo_pessoa' => 'FISICA', 'nome' => 'PHPUnit AtivoInativo B', 'email' => 'phpunit.ativoinativo@teste.local', 'ativo' => 1]);
        $this->assertTrue($ok2, 'cliente novo pode reusar e-mail de um cliente já desativado: ' . $cliente->getLastError());
        $this->registrar($this->idPorNome('PHPUnit AtivoInativo B'));
    }

    public function testPermiteMultiplosClientesSemEmail(): void
    {
        $cliente = new Cliente();

        $ok1 = $cliente->create(['tipo_pessoa' => 'FISICA', 'nome' => 'PHPUnit Sem Email 1', 'ativo' => 1]);
        $this->assertTrue($ok1);
        $this->registrar($this->idPorNome('PHPUnit Sem Email 1'));

        $ok2 = $cliente->create(['tipo_pessoa' => 'FISICA', 'nome' => 'PHPUnit Sem Email 2', 'ativo' => 1]);
        $this->assertTrue($ok2, 'dois clientes sem e-mail não podem conflitar entre si (NULL não é duplicata)');
        $this->registrar($this->idPorNome('PHPUnit Sem Email 2'));
    }

    public function testNaoPermiteCpfDuplicadoEntreDoisClientesAtivos(): void
    {
        $cliente = new Cliente();

        $ok1 = $cliente->create(['tipo_pessoa' => 'FISICA', 'nome' => 'PHPUnit CPF A', 'cpf' => '123.456.789-09', 'ativo' => 1]);
        $this->assertTrue($ok1);
        $this->registrar($this->idPorNome('PHPUnit CPF A'));

        $ok2 = $cliente->create(['tipo_pessoa' => 'FISICA', 'nome' => 'PHPUnit CPF B', 'cpf' => '123.456.789-09', 'ativo' => 1]);
        $this->assertFalse($ok2, 'CPF duplicado entre dois clientes ativos deveria ser bloqueado pelo backstop do banco');
        $this->registrar($this->idPorNome('PHPUnit CPF B'));
    }
}
