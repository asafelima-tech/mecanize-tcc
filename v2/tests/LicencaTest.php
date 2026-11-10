<?php

final class LicencaTest extends Cenario
{
    private function oficina(string $status, string $validade): array
    {
        return ['id' => 1, 'status' => $status, 'licenca_valida_ate' => $validade];
    }

    public function testRegras(): void
    {
        $hoje = new DateTimeImmutable('2026-11-10');

        $this->assertTrue(Licenca::permiteEscrita($this->oficina('ATIVA', '2026-11-10'), $hoje), 'vence hoje: ainda vale');
        $this->assertTrue(Licenca::permiteEscrita($this->oficina('TESTE', '2026-12-01'), $hoje));
        $this->assertFalse(Licenca::permiteEscrita($this->oficina('ATIVA', '2026-11-09'), $hoje), 'vencida');
        $this->assertFalse(Licenca::permiteEscrita($this->oficina('SUSPENSA', '2027-01-01'), $hoje));
        $this->assertFalse(Licenca::permiteEntrar($this->oficina('CANCELADA', '2027-01-01')));
        $this->assertTrue(Licenca::permiteEntrar($this->oficina('SUSPENSA', '2027-01-01')), 'suspensa entra em modo leitura');
    }

    public function testMensagens(): void
    {
        $hoje = new DateTimeImmutable('2026-11-10');

        $this->assertNull(Licenca::mensagem($this->oficina('ATIVA', '2026-12-31'), $hoje));
        $this->assertStringContainsString('3 dia', Licenca::mensagem($this->oficina('ATIVA', '2026-11-13'), $hoje));
        $this->assertStringContainsString('somente leitura', Licenca::mensagem($this->oficina('ATIVA', '2026-11-01'), $hoje));
    }

    public function testLicencaVencidaBloqueiaGravacaoMasNaoLeitura(): void
    {
        $this->db->exec('UPDATE oficinas SET licenca_valida_ate = CURDATE() - INTERVAL 1 DAY WHERE id = 1');
        $this->entrarComo(self::ADMIN_A);

        $this->assertSame(1, (new Painel())->resumo()['orcamentos'], 'consultar continua liberado');

        $this->expectException(LicencaBloqueada::class);
        $this->modelo()->inserir('clientes', ['tipo_pessoa' => 'PF', 'nome' => 'Não pode']);
    }

    public function testLicencaSuspensaBloqueiaAtualizacao(): void
    {
        $this->db->exec("UPDATE oficinas SET status = 'SUSPENSA' WHERE id = 1");
        $this->entrarComo(self::ADMIN_A);

        $this->expectException(LicencaBloqueada::class);
        $this->modelo()->atualizar('clientes', 101, ['nome' => 'Não pode']);
    }
}
