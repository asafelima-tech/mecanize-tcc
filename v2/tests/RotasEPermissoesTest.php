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
            //             LOGADO EQUIPE ATEND. BALCAO TECNICO ADMIN PLATAFORMA
            'ADMIN'      => [true,  true,  true,  true,  false,  true,  false],
            'ATENDENTE'  => [true,  true,  true,  true,  false,  false, false],
            'TECNICO'    => [true,  true,  false, false, true,   false, false],
            'PLATAFORMA' => [true,  false, false, false, false,  false, true],
        ];
        $niveis = [Permissao::LOGADO, Permissao::EQUIPE, Permissao::ATENDIMENTO, Permissao::BALCAO, Permissao::TECNICO, Permissao::ADMIN, Permissao::PLATAFORMA];

        foreach ($esperado as $perfil => $linha) {
            foreach ($niveis as $i => $nivel) {
                $this->assertSame($linha[$i], Permissao::permite($perfil, $nivel), "{$perfil} em {$nivel}");
            }
        }

        $this->assertFalse(Permissao::permite(null, Permissao::LOGADO), 'sem login não passa');
        $this->assertFalse(Permissao::permite('ADMIN', null), 'rota sem nível é negada');
        $this->assertFalse(Permissao::permite('ADMIN', 'QUALQUER'), 'nível desconhecido é negado');
    }

    /** O técnico só abre a área dele e o que é de todos (início, perfil, avisos, anexos conferidos). */
    public function testTecnicoNaoAbreAsTelasDoEscritorio(): void
    {
        $doTecnico = array_keys(array_filter($this->rotas(), fn($r) => Permissao::permite('TECNICO', $r[2]) && $r[2] !== Permissao::PUBLICO));
        sort($doTecnico);
        $this->assertSame(['anexo', 'inicio', 'notificacao', 'notificacoes', 'notificacoes-ler-todas', 'oficina-logo', 'os-anexar', 'pedido-criar', 'pedidos',
                           'perfil', 'perfil-salvar', 'sair', 'servico', 'servico-acao', 'servico-material', 'servicos'], $doTecnico);
        foreach (['ordens', 'os', 'os-acao', 'os-item', 'clientes', 'veiculos', 'produtos', 'agenda', 'estoque', 'financeiro', 'relatorios', 'solicitacoes'] as $r) {
            $this->assertFalse(Permissao::permite('TECNICO', $this->rotas()[$r][2]), $r);
        }
        $this->assertSame('servicos', Permissao::inicio('TECNICO'));
    }

    public function testAtendenteContinuaComOEscritorio(): void
    {
        foreach (['ordens', 'os', 'os-acao', 'clientes', 'veiculos', 'produtos', 'agenda', 'caixa'] as $r) {
            $this->assertTrue(Permissao::permite('ATENDENTE', $this->rotas()[$r][2]), $r);
        }
        foreach (['servicos', 'servico', 'pedidos', 'solicitacoes'] as $r) {
            $this->assertFalse(Permissao::permite('ATENDENTE', $this->rotas()[$r][2]), $r);
        }
    }
}
