<?php

use PHPUnit\Framework\TestCase;

/**
 * Cobre a exclusão de usuário, que estourava PDOException não tratada (tela
 * 500) sempre que o usuário tinha histórico na oficina — as foreign keys de
 * `caixa`, `movimentacoes_caixa`, `orcamentos` e `agendamentos` barram o
 * DELETE, e nada capturava isso.
 *
 * Roda contra mecanize_db_test (ver tests/bootstrap.php).
 */
final class UsuarioExclusaoTest extends TestCase
{
    private PDO $pdo;
    private User $model;
    /** @var int[] ids criados no teste, removidos no tearDown */
    private array $usuarios = [];
    private array $caixas   = [];

    protected function setUp(): void
    {
        $this->pdo = new Database();

        require_once __DIR__ . '/../app/models/User.php';
        $this->model = new User();
    }

    protected function tearDown(): void
    {
        foreach ($this->caixas as $id) {
            $this->pdo->exec("DELETE FROM movimentacoes_caixa WHERE caixa_id = {$id}");
            $this->pdo->exec("DELETE FROM caixa WHERE id_caixa = {$id}");
        }

        foreach ($this->usuarios as $id) {
            $this->pdo->exec("DELETE FROM password_resets WHERE usuario_id = {$id}");
            $this->pdo->exec("DELETE FROM usuarios WHERE id_usuario = {$id}");
        }
    }

    private function criarUsuario(string $sufixo): int
    {
        $this->pdo->exec("
            INSERT INTO usuarios (nome, email, senha, tipo)
            VALUES ('Usuario {$sufixo} PHPUnit', 'phpunit.excl.{$sufixo}@teste.local', 'x', 'ATENDENTE')
        ");
        $id = (int) $this->pdo->lastInsertId();
        $this->usuarios[] = $id;

        return $id;
    }

    private function abrirCaixa(int $usuarioId): int
    {
        $this->pdo->exec("
            INSERT INTO caixa (usuario_abertura_id, valor_abertura, status)
            VALUES ({$usuarioId}, 50.00, 'ABERTO')
        ");
        $id = (int) $this->pdo->lastInsertId();
        $this->caixas[] = $id;

        return $id;
    }

    public function testUsuarioSemHistoricoEExcluidoNormalmente(): void
    {
        $id = $this->criarUsuario('limpo');

        $this->assertSame([], $this->model->vinculos($id), 'usuário novo não tem histórico');
        $this->assertTrue($this->model->delete($id));

        $existe = $this->pdo->query("SELECT COUNT(*) FROM usuarios WHERE id_usuario = {$id}")->fetchColumn();
        $this->assertSame(0, (int) $existe);
    }

    public function testUsuarioComCaixaNaoEExcluidoENaoLancaExcecao(): void
    {
        $id = $this->criarUsuario('comcaixa');
        $this->abrirCaixa($id);

        $vinculos = $this->model->vinculos($id);

        $this->assertNotEmpty($vinculos);
        $this->assertContains('1 aberturas de caixa', $vinculos);

        // O ponto do teste: retorna false em vez de estourar PDOException
        // (que virava a tela de erro 500 relatada pelo usuário).
        $this->assertFalse($this->model->delete($id), 'FK deve barrar a exclusão sem explodir');

        $existe = $this->pdo->query("SELECT COUNT(*) FROM usuarios WHERE id_usuario = {$id}")->fetchColumn();
        $this->assertSame(1, (int) $existe, 'o usuário tem que continuar existindo');
    }

    public function testVinculosDescreveCadaTipoDeHistorico(): void
    {
        $id     = $this->criarUsuario('varios');
        $caixaId = $this->abrirCaixa($id);

        $this->pdo->exec("
            INSERT INTO movimentacoes_caixa (caixa_id, tipo, forma_pagamento, valor, usuario_id)
            VALUES ({$caixaId}, 'ENTRADA', 'PIX', 10.00, {$id})
        ");

        $vinculos = $this->model->vinculos($id);

        $this->assertContains('1 aberturas de caixa', $vinculos);
        $this->assertContains('1 lançamentos de caixa', $vinculos);
    }

    public function testTokenDeSenhaNaoImpedeExclusao(): void
    {
        $id = $this->criarUsuario('comtoken');

        // Token de recuperação é descartável — não é histórico da oficina e
        // não pode ser motivo pra impedir a exclusão de um usuário limpo.
        $this->pdo->exec("
            INSERT INTO password_resets (usuario_id, token, expira_em)
            VALUES ({$id}, 'token-de-teste', DATE_ADD(NOW(), INTERVAL 1 HOUR))
        ");

        $this->assertSame([], $this->model->vinculos($id), 'token não conta como histórico');
        $this->assertTrue($this->model->delete($id), 'a FK do token não pode barrar a exclusão');

        $sobrou = $this->pdo->query("SELECT COUNT(*) FROM password_resets WHERE usuario_id = {$id}")->fetchColumn();
        $this->assertSame(0, (int) $sobrou, 'o token tem que ser apagado junto');
    }

    public function testExcluirIdInexistenteRetornaFalse(): void
    {
        $this->assertFalse($this->model->delete(99999999));
    }
}
