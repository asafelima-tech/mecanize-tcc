<?php

require_once __DIR__ . '/ComprasTestCase.php';

/**
 * O sino do sistema: o mesmo fato nunca avisa a mesma pessoa duas vezes, e ninguém lê nem
 * marca como lida a notificação de outro.
 */
final class NotificacaoTest extends ComprasTestCase
{
    private function sino(): Notificacao
    {
        return new Notificacao($this->pdo);
    }

    public function testMesmaChaveParaOMesmoUsuarioCriaUmaSoVez(): void
    {
        $a = Notificacao::criar($this->pdo, $this->tec1, 'TESTE', 'Olá', 'corpo', './home', 'FATO:1');
        $b = Notificacao::criar($this->pdo, $this->tec1, 'TESTE', 'Olá', 'corpo', './home', 'FATO:1');

        $this->assertTrue($a);
        $this->assertFalse($b, 'reprocessar o mesmo fato não cria outra');
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = {$this->tec1}"));
    }

    public function testMesmaChaveParaOutroUsuarioCria(): void
    {
        Notificacao::criar($this->pdo, $this->tec1, 'TESTE', 'Olá', null, null, 'FATO:2');

        $this->assertTrue(Notificacao::criar($this->pdo, $this->tec2, 'TESTE', 'Olá', null, null, 'FATO:2'));
    }

    public function testParaUsuariosIgnoraRepetidosEInvalidosEContaOsNovos(): void
    {
        $n = Notificacao::paraUsuarios($this->pdo, [$this->tec1, $this->tec1, $this->tec2, 0, -3], 'TESTE', 'Aviso', null, null, 'FATO:3');

        $this->assertSame(2, $n);
    }

    public function testListaContaEMarcaComoLida(): void
    {
        Notificacao::criar($this->pdo, $this->tec1, 'TESTE', 'Primeira', null, './a', 'K:1');
        Notificacao::criar($this->pdo, $this->tec1, 'TESTE', 'Segunda', null, './b', 'K:2');

        $this->assertSame(2, $this->sino()->contarNaoLidas($this->tec1));
        $lista = $this->sino()->listar($this->tec1);
        $this->assertSame('Segunda', $lista[0]['titulo'], 'a mais recente primeiro');

        $n = $this->sino()->marcarLida((int) $lista[0]['id_notificacao'], $this->tec1);

        $this->assertSame('./b', $n['link']);
        $this->assertSame(1, $this->sino()->contarNaoLidas($this->tec1));
        $this->assertCount(1, $this->sino()->listar($this->tec1, true));
    }

    public function testNinguemMarcaOuLeANotificacaoDeOutro(): void
    {
        Notificacao::criar($this->pdo, $this->tec1, 'TESTE', 'Só minha', null, './x', 'K:9');
        $id = (int) $this->pdo->query("SELECT id_notificacao FROM notificacoes WHERE usuario_id = {$this->tec1}")->fetchColumn();

        $this->assertNull($this->sino()->marcarLida($id, $this->tec2));
        $this->assertSame(1, $this->sino()->contarNaoLidas($this->tec1), 'continua não lida');
        $this->assertSame(0, $this->sino()->contarNaoLidas($this->tec2));
    }

    public function testMarcarTodasSoMexeNasDoUsuario(): void
    {
        Notificacao::criar($this->pdo, $this->tec1, 'TESTE', 'A', null, null, 'K:a');
        Notificacao::criar($this->pdo, $this->tec1, 'TESTE', 'B', null, null, 'K:b');
        Notificacao::criar($this->pdo, $this->tec2, 'TESTE', 'C', null, null, 'K:c');

        $this->assertSame(2, $this->sino()->marcarTodas($this->tec1));
        $this->assertSame(0, $this->sino()->contarNaoLidas($this->tec1));
        $this->assertSame(1, $this->sino()->contarNaoLidas($this->tec2));
    }

    public function testIdsDosAdminsExcluiQuemOriginouOFato(): void
    {
        $todos = Notificacao::idsDosAdmins($this->pdo);
        $semEu = Notificacao::idsDosAdmins($this->pdo, $this->adminId);

        $this->assertContains($this->adminId, $todos);
        $this->assertNotContains($this->adminId, $semEu);
        $this->assertNotContains($this->tec1, $todos, 'técnico não é administrador');
    }
}
