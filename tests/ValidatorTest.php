<?php

use PHPUnit\Framework\TestCase;

/**
 * Cobre as duas correções de Validator feitas na auditoria de 2026-07-10:
 * celularBr() passou a aceitar fixo (não só celular), e cpfCnpj() ganhou o
 * algoritmo real de dígito verificador. Regressão aqui = alguém volta a
 * quebrar cadastro de cliente com telefone fixo ou aceita CPF inválido.
 */
final class ValidatorTest extends TestCase
{
    public function testCelularBrAceitaCelularValido(): void
    {
        $v = new Validator(['telefone' => '(18) 99999-8888']);
        $v->celularBr('telefone', 'Telefone');
        $this->assertFalse($v->fails(), 'celular válido não deveria falhar');
    }

    public function testCelularBrAceitaFixoValido(): void
    {
        $v = new Validator(['telefone' => '(18) 3222-4455']);
        $v->celularBr('telefone', 'Telefone');
        $this->assertFalse($v->fails(), 'telefone fixo válido não deveria falhar (bug corrigido em 2026-07-10)');
    }

    public function testCelularBrRejeitaDddInexistente(): void
    {
        $v = new Validator(['telefone' => '(00) 99999-8888']);
        $v->celularBr('telefone', 'Telefone');
        $this->assertTrue($v->fails(), 'DDD 00 não existe, deveria falhar');
    }

    public function testCelularBrRejeitaDigitosRepetidos(): void
    {
        $v = new Validator(['telefone' => '(18) 99999-9999']);
        $v->celularBr('telefone', 'Telefone');
        $this->assertTrue($v->fails(), 'número com todos os dígitos iguais deveria falhar');
    }

    public function testCelularBrCampoVazioEhOpcional(): void
    {
        $v = new Validator(['telefone' => '']);
        $v->celularBr('telefone', 'Telefone');
        $this->assertFalse($v->fails(), 'campo vazio é opcional, não deveria falhar sozinho');
    }

    public function testCpfCnpjAceitaCpfValido(): void
    {
        // CPF de teste com dígito verificador matematicamente correto
        // (não pertence a uma pessoa real — gerado só pelo algoritmo).
        $v = new Validator(['cpf' => '123.456.789-09']);
        $v->cpfCnpj('cpf', 'CPF');
        $this->assertFalse($v->fails());
    }

    public function testCpfCnpjRejeitaDigitoVerificadorErrado(): void
    {
        $v = new Validator(['cpf' => '123.456.789-00']);
        $v->cpfCnpj('cpf', 'CPF');
        $this->assertTrue($v->fails());
    }

    public function testCpfCnpjRejeitaTodosDigitosIguais(): void
    {
        $v = new Validator(['cpf' => '111.111.111-11']);
        $v->cpfCnpj('cpf', 'CPF');
        $this->assertTrue($v->fails(), 'CPF com todos os dígitos iguais passaria no módulo 11 mas nunca é real');
    }

    public function testCpfCnpjAceitaCnpjValido(): void
    {
        // CNPJ de teste com dígito verificador correto.
        $v = new Validator(['cnpj' => '11.222.333/0001-81']);
        $v->cpfCnpj('cnpj', 'CNPJ');
        $this->assertFalse($v->fails());
    }

    public function testCpfCnpjCampoVazioEhOpcional(): void
    {
        $v = new Validator(['cpf' => '']);
        $v->cpfCnpj('cpf', 'CPF');
        $this->assertFalse($v->fails());
    }
}
