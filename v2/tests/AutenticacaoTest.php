<?php

final class AutenticacaoTest extends Cenario
{
    public function testEncontraPorEmailSemDiferenciarMaiusculas(): void
    {
        $conta = (new Usuario())->porEmail('  ANA@a.test ');

        $this->assertSame(self::ADMIN_A, (int) $conta['id']);
        $this->assertSame('Oficina A', $conta['oficina_nome']);
        $this->assertTrue(password_verify(self::SENHA, $conta['senha_hash']));
    }

    public function testBloqueiaNaQuintaTentativaErradaSemDerrubarSessao(): void
    {
        $u = new Usuario();
        for ($i = 1; $i <= Usuario::MAX_TENTATIVAS - 1; $i++) {
            $u->registrarTentativaFalha(self::ADMIN_A);
        }
        $this->assertFalse($u->estaBloqueado($u->porEmail('ana@a.test')), 'quatro erros ainda não bloqueiam');

        $u->registrarTentativaFalha(self::ADMIN_A);
        $conta = $u->porEmail('ana@a.test');
        $this->assertTrue($u->estaBloqueado($conta));
        $this->assertSame('2026-01-01 10:00:00', $conta['atualizado_em'], 'tentativa errada não pode mudar atualizado_em');

        $u->zerarTentativas(self::ADMIN_A);
        $this->assertFalse($u->estaBloqueado($u->porEmail('ana@a.test')));
    }

    public function testRecuperacaoGuardaSoOHashEValeUmaVez(): void
    {
        $u = new Usuario();
        $token = $u->criarTokenRecuperacao($u->porEmail('ana@a.test'));

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $guardado = $this->db->query('SELECT reset_token_hash FROM usuarios WHERE id = 11')->fetchColumn();
        $this->assertSame(hash('sha256', $token), $guardado, 'só o hash vai para o banco');
        $this->assertNull($u->criarTokenRecuperacao($u->porEmail('ana@a.test')), 'pedido repetido em seguida não gera outro e-mail');

        $this->assertSame(self::ADMIN_A, (int) $u->porTokenRecuperacao($token)['id']);
        $u->redefinirSenha(self::ADMIN_A, 'nova-senha-1');

        $this->assertNull($u->porTokenRecuperacao($token), 'link usado deixa de valer');
        $conta = $u->porEmail('ana@a.test');
        $this->assertTrue(password_verify('nova-senha-1', $conta['senha_hash']));
        $this->assertNotSame('2026-01-01 10:00:00', $conta['atualizado_em'], 'trocar a senha derruba sessões abertas');
    }

    public function testTokenVencidoOuMalFormadoNaoVale(): void
    {
        $u = new Usuario();
        $token = $u->criarTokenRecuperacao($u->porEmail('ana@a.test'));
        $this->db->exec('UPDATE usuarios SET reset_expira_em = NOW() - INTERVAL 1 MINUTE WHERE id = 11');

        $this->assertNull($u->porTokenRecuperacao($token));
        $this->assertNull($u->porTokenRecuperacao("' OR 1=1 --"));
    }

    public function testMotivosParaEncerrarSessao(): void
    {
        $_SESSION = ['atualizado_em' => '2026-01-01 10:00:00'];
        $conta = (new Usuario())->paraSessao(self::ADMIN_A);

        $this->assertNull(Sessao::motivoParaEncerrar($conta));
        $this->assertSame(Sessao::CONTA_DESATIVADA, Sessao::motivoParaEncerrar(null));
        $this->assertSame(Sessao::CONTA_DESATIVADA, Sessao::motivoParaEncerrar(['ativo' => 0] + $conta));
        $this->assertSame(Sessao::OFICINA_CANCELADA, Sessao::motivoParaEncerrar(['oficina_status' => 'CANCELADA'] + $conta));
        $this->assertSame(Sessao::CONTA_ALTERADA, Sessao::motivoParaEncerrar(['atualizado_em' => '2026-02-01 00:00:00'] + $conta));
        $_SESSION = [];
    }
}
