<?php

use PHPUnit\Framework\TestCase;

final class RotasEPermissoesTest extends TestCase
{
    private function rotas(): array
    {
        return require V2_RAIZ . '/config/rotas.php';
    }

    public function testTodaRotaTemNivelValidoEMetodoExistente(): void
    {
        foreach ($this->rotas() as $rota => [$controller, $metodo, $nivel]) {
            $this->assertContains($nivel, Permissao::NIVEIS, "Rota '{$rota}' sem nível válido");
            $this->assertTrue(method_exists($controller, $metodo), "Rota '{$rota}' aponta para {$controller}::{$metodo}, que não existe");
        }
    }

    public function testSoAsTelasDeAcessoEOAcompanhamentoSaoPublicos(): void
    {
        $publicas = array_keys(array_filter($this->rotas(), fn($r) => $r[2] === Permissao::PUBLICO));
        sort($publicas);

        $this->assertSame(['acompanhar', 'login', 'recuperar-senha', 'redefinir-senha'], $publicas);
    }

    public function testMatriz(): void
    {
        $esperado = [
            //             LOGADO EQUIPE ADMIN PLATAFORMA
            'ADMIN'      => [true,  true,  true,  false],
            'ATENDENTE'  => [true,  true,  false, false],
            'TECNICO'    => [true,  true,  false, false],
            'PLATAFORMA' => [true,  false, false, true],
        ];
        $niveis = [Permissao::LOGADO, Permissao::EQUIPE, Permissao::ADMIN, Permissao::PLATAFORMA];

        foreach ($esperado as $perfil => $linha) {
            foreach ($niveis as $i => $nivel) {
                $this->assertSame($linha[$i], Permissao::permite($perfil, $nivel), "{$perfil} em {$nivel}");
            }
        }

        $this->assertFalse(Permissao::permite(null, Permissao::LOGADO), 'sem login não passa');
        $this->assertFalse(Permissao::permite('ADMIN', null), 'rota sem nível é negada');
        $this->assertFalse(Permissao::permite('ADMIN', 'QUALQUER'), 'nível desconhecido é negado');
    }

    public function testAtendenteETecnicoTemAsMesmasPermissoes(): void
    {
        foreach (Permissao::NIVEIS as $nivel) {
            $this->assertSame(Permissao::permite('ATENDENTE', $nivel), Permissao::permite('TECNICO', $nivel), $nivel);
        }
    }
}
