<?php

use PHPUnit\Framework\TestCase;

/**
 * Cobre app/core/Csrf.php, adicionado na auditoria de 2026-07-10 — antes
 * disso não existia NENHUMA proteção contra CSRF no sistema.
 */
final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        // Sessão já foi iniciada em tests/bootstrap.php (precisa ser antes
        // do PHPUnit imprimir qualquer coisa) — aqui só reseta o token.
        unset($_SESSION['csrf_token']);
    }

    public function testTokenEGeradoNaPrimeiraChamada(): void
    {
        $token = Csrf::token();
        $this->assertNotEmpty($token);
        $this->assertSame(64, strlen($token), 'token deve ser 32 bytes em hex = 64 caracteres');
    }

    public function testTokenEEstavelDentroDaMesmaSessao(): void
    {
        $primeiro = Csrf::token();
        $segundo  = Csrf::token();
        $this->assertSame($primeiro, $segundo, 'não deve gerar um token novo a cada chamada');
    }

    public function testValidoAceitaTokenCorreto(): void
    {
        $token = Csrf::token();
        $this->assertTrue(Csrf::valido($token));
    }

    public function testValidoRejeitaTokenErrado(): void
    {
        Csrf::token();
        $this->assertFalse(Csrf::valido('token-forjado-qualquer'));
    }

    public function testValidoRejeitaTokenNuloOuVazio(): void
    {
        Csrf::token();
        $this->assertFalse(Csrf::valido(null));
        $this->assertFalse(Csrf::valido(''));
    }

    public function testCsrfFieldEscapaHtml(): void
    {
        $html = csrf_field();
        $this->assertStringContainsString('name="csrf_token"', $html);
        $this->assertStringContainsString('type="hidden"', $html);
    }
}
