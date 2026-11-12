<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/core/Permissao.php';

/**
 * A matriz de acesso (config/routes.php + Permissao).
 *
 * Origem: a auditoria de 08/10/2026 achou ~25 rotas sem nenhuma checagem de papel
 * no servidor e dois perfis (técnico e atendente) com permissões diferentes por
 * acidente. Estes testes tornam as duas coisas impossíveis de voltar sem alguém
 * perceber: rota nova sem nível falha aqui; técnico e atendente divergindo falha
 * aqui; um controller mais restritivo que a matriz (ou mais frouxo) falha aqui.
 *
 * Só lê arquivos — não toca no banco.
 */
final class PermissaoTest extends TestCase
{
    private const RAIZ = __DIR__ . '/..';

    private function rotas(): array
    {
        require self::RAIZ . '/config/routes.php';

        return $routes;
    }

    public function testTodaRotaDeclaraUmNivelValido(): void
    {
        $sem = [];

        foreach ($this->rotas() as $uri => $rota) {
            if (!in_array($rota['acesso'] ?? null, Permissao::NIVEIS, true)) {
                $sem[] = $uri;
            }
        }

        $this->assertSame([], $sem, "Rotas sem 'acesso' válido em config/routes.php:\n" . implode("\n", $sem));
    }

    public function testAtendenteETecnicoTemExatamenteAsMesmasPermissoes(): void
    {
        $this->assertSame(
            [],
            Permissao::rotasSemParidade($this->rotas()),
            'Técnico e atendente têm a mesma capacidade; só a tela inicial muda.'
        );

        // E nada do que o atendente alcança é negado ao técnico por papel, em nenhum nível.
        foreach (Permissao::NIVEIS as $nivel) {
            $this->assertSame(
                Permissao::permite('ATENDENTE', $nivel),
                Permissao::permite('TECNICO', $nivel),
                "Nível {$nivel}"
            );
        }
    }

    public function testAdminAlcancaTudoQueOsOutrosAlcancam(): void
    {
        foreach (array_keys($this->rotas()) as $uri) {
            foreach (['ATENDENTE', 'TECNICO'] as $papel) {
                if (Permissao::podeAcessarRota($papel, $this->rotas(), $uri)) {
                    $this->assertTrue(Permissao::podeAcessarRota('ADMIN', $this->rotas(), $uri), "ADMIN deveria alcançar {$uri}");
                }
            }
        }
    }

    public function testRotaSemNivelEhNegadaParaTodosMenosOAdmin(): void
    {
        $rotas = ['nova' => ['controller' => 'X', 'method' => 'y']]; // esqueceram de classificar

        $this->assertFalse(Permissao::podeAcessarRota('ATENDENTE', $rotas, 'nova'));
        $this->assertFalse(Permissao::podeAcessarRota('TECNICO', $rotas, 'nova'));
        $this->assertFalse(Permissao::podeAcessarRota(null, $rotas, 'nova'));
        $this->assertTrue(Permissao::podeAcessarRota('ADMIN', $rotas, 'nova'));
    }

    public function testNivelDesconhecidoNaoLiberaNinguem(): void
    {
        $this->assertFalse(Permissao::permite('ADMIN', 'QUALQUER_COISA'));
        $this->assertFalse(Permissao::permite('TECNICO', 'qualquer'));
        $this->assertFalse(Permissao::permite(null, Permissao::EQUIPE));
        $this->assertFalse(Permissao::permite('CLIENTE', Permissao::LOGADO));
    }

    public function testApenasEstasRotasSaoPublicas(): void
    {
        $publicas = array_keys(array_filter($this->rotas(), fn($r) => $r['acesso'] === Permissao::PUBLICO));
        sort($publicas);

        $esperadas = [
            'erro404', 'erroConexao', 'erroIndisponivel', 'erroPermissao', 'erroTempo',
            'login', 'logout', 'privacidade', 'rastrear', 'recuperar-senha', 'redefinir-senha', 'termos',
            // Portal do cliente (link com token) e webhooks (assinatura do corpo).
            'portal', 'portalAutorizacao', 'portalConsentimento', 'portalOrcamento', 'portalPagamentoSimulado', 'portalPagar',
            'webhookPagamento', 'webhookWhatsApp',
        ];
        sort($esperadas);

        $this->assertSame($esperadas, $publicas, 'Uma rota só fica sem login por decisão explícita — e deste teste.');
    }

    public function testSoOsWebhooksFicamForaDoCsrf(): void
    {
        $semCsrf = array_keys(array_filter($this->rotas(), fn($r) => ($r['csrf'] ?? true) === false));
        sort($semCsrf);

        $this->assertSame(['webhookPagamento', 'webhookWhatsApp'], $semCsrf, 'Isentar uma rota do CSRF é decisão explícita — e deste teste.');

        // Sem CSRF só faz sentido onde NÃO há sessão (servidor externo chamando); numa rota logada seria um buraco.
        foreach ($semCsrf as $uri) {
            $this->assertSame(Permissao::PUBLICO, $this->rotas()[$uri]['acesso'], "{$uri} sem CSRF precisa ser PUBLICO e autenticar pela assinatura");
        }
    }

    public function testGestaoEDinheiroSaoSoDoAdministrador(): void
    {
        $soAdmin = [
            // usuários e documentos
            'listUsuarios', 'saveUsuario', 'editUsuario', 'deleteUsuario', 'toggleStatusUsuario', 'viewUsuario', 'exportUsuarios',
            'listDocumentos', 'saveDocumento', 'finalizarDocumento',
            // cadastro de produtos (mostra custo) e categorias
            'listProduto', 'saveProduto', 'editProduto', 'viewProduto', 'deleteProduto', 'etiquetaProduto',
            'saveCategoria', 'editCategoria', 'deleteCategoria',
            // financeiro e relatórios
            'comissoesFuncionarios', 'pagarComissoesFuncionario', 'ajustarComissaoFuncionario',
            'relatorioCaixa', 'relatorioCaixaPdf', 'listRel',
            // decisões finais da OS e exclusões
            'conferirOrdemServico', 'reprovarConferenciaOrdemServico', 'cancelarOrdemServico', 'deleteOrdemServico',
            'estornarMovimentacaoEstoque', 'deleteCliente', 'reativarCliente', 'deleteVeiculo', 'deleteAgendamento',
        ];

        $rotas = $this->rotas();

        foreach ($soAdmin as $uri) {
            $this->assertArrayHasKey($uri, $rotas, "rota {$uri} deveria existir");
            $this->assertSame(Permissao::ADMIN, $rotas[$uri]['acesso'], "{$uri} é só do administrador");
        }
    }

    public function testRotasAntesSemChecagemAgoraExigemLoginEPapel(): void
    {
        $rotas = $this->rotas();

        // As que a auditoria provou abertas a qualquer usuário logado sem decisão — agora declaradas.
        foreach (['listOrdemServico', 'exportOrdemServicoPdf', 'listClientes', 'viewCliente', 'listOrcamentos',
                  'exportOrcamentoPdf', 'listMovimentacaoEstoque', 'criarModelo', 'lerPlaca'] as $uri) {
            $this->assertSame(Permissao::EQUIPE, $rotas[$uri]['acesso'], "{$uri}");
            $this->assertFalse(Permissao::podeAcessarRota(null, $rotas, $uri), "{$uri} não é para visitante");
            $this->assertFalse(Permissao::podeAcessarRota('CLIENTE', $rotas, $uri), "{$uri} não é para papel desconhecido");
        }
    }

    public function testNenhumControllerUsaListaLiteralDePapeis(): void
    {
        $literais = [];

        foreach (glob(self::RAIZ . '/app/controllers/*.php') as $arquivo) {
            if (preg_match_all('/requireRole\(\s*\[/', file_get_contents($arquivo))) {
                $literais[] = basename($arquivo);
            }
        }

        $this->assertSame([], $literais, 'Use Permissao::PAPEIS_EQUIPE / PAPEIS_ADMIN — listas literais divergem da matriz sem ninguém ver.');
    }

    /**
     * O requireRole de dentro do método é uma segunda camada. Se ele for mais
     * restritivo que a matriz (ex.: ADMIN numa rota EQUIPE), o usuário recebe 403
     * numa rota que o menu mostra; se for mais frouxo (EQUIPE numa rota ADMIN), é
     * uma brecha caso a matriz seja afrouxada por engano. Têm que concordar.
     */
    public function testRequireRoleDoControllerConcordaComAMatriz(): void
    {
        $divergencias = [];

        foreach ($this->rotas() as $uri => $rota) {
            $arquivo = self::RAIZ . "/app/controllers/{$rota['controller']}.php";
            $corpo   = $this->corpoDoMetodo($arquivo, $rota['method']);

            if ($corpo === null) {
                continue; // RotasTest cobre rota apontando para método inexistente
            }

            $codigo    = $this->semComentarios($corpo);
            $usaAdmin  = str_contains($codigo, 'requireRole(Permissao::PAPEIS_ADMIN)');
            $usaEquipe = str_contains($codigo, 'requireRole(Permissao::PAPEIS_EQUIPE)');

            if ($usaAdmin && $rota['acesso'] !== Permissao::ADMIN) {
                $divergencias[] = "{$uri}: o método exige ADMIN, mas a rota declara {$rota['acesso']}";
            }

            if ($usaEquipe && !in_array($rota['acesso'], [Permissao::EQUIPE, Permissao::LOGADO], true)) {
                $divergencias[] = "{$uri}: o método aceita a equipe, mas a rota declara {$rota['acesso']}";
            }
        }

        $this->assertSame([], $divergencias, implode("\n", $divergencias));
    }

    public function testMenuELinksNaoApontamParaRotaQueONegaParaOTecnico(): void
    {
        // O menu da equipe (header.php) não pode mostrar ao técnico/atendente um link de rota
        // ADMIN: ele clicaria e levaria 403. Rastreia o aninhamento dos blocos "if (...):" do
        // template e exige que todo link de rota ADMIN esteja dentro de um bloco do ADMIN.
        $rotas  = $this->rotas();
        $linhas = file(self::RAIZ . '/app/views/layout/header.php');
        $pilha  = [];
        $soltos = [];

        foreach ($linhas as $n => $linha) {
            if (preg_match('/<\?php\s+if\s*\((.*)\)\s*:\s*\?>/', $linha, $m)) {
                $pilha[] = $m[1];
            }

            if (preg_match_all('#href="\./([A-Za-z0-9_\-]+)#', $linha, $achados)) {
                foreach ($achados[1] as $uri) {
                    if (!isset($rotas[$uri]) || $rotas[$uri]['acesso'] !== Permissao::ADMIN) {
                        continue;
                    }

                    $protegido = false;
                    foreach ($pilha as $condicao) {
                        if (str_contains($condicao, 'ADMIN')) {
                            $protegido = true;
                        }
                    }

                    if (!$protegido) {
                        $soltos[] = 'header.php:' . ($n + 1) . " → ./{$uri}";
                    }
                }
            }

            if (preg_match('/<\?php\s+endif;/', $linha)) {
                array_pop($pilha);
            }
        }

        $this->assertSame([], $soltos, "Link de rota só-ADMIN no menu sem condição de papel:\n" . implode("\n", $soltos));
    }

    /** Remove comentários de um trecho PHP (suficiente para checar chamadas reais). */
    private function semComentarios(string $codigo): string
    {
        $saida = '';

        foreach (token_get_all('<?php ' . $codigo) as $t) {
            if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $saida .= is_array($t) ? $t[1] : $t;
        }

        return $saida;
    }

    private function corpoDoMetodo(string $arquivo, string $metodo): ?string
    {
        $linhas = file($arquivo);
        $ini    = null;

        foreach ($linhas as $i => $linha) {
            if (preg_match('/function\s+' . preg_quote($metodo, '/') . '\s*\(/', $linha)) {
                $ini = $i;
                break;
            }
        }

        if ($ini === null) {
            return null;
        }

        $corpo = '';
        for ($j = $ini; $j < count($linhas); $j++) {
            if ($j > $ini && preg_match('/^\s{0,4}(public|private|protected)\s+(static\s+)?function/', $linhas[$j])) {
                break;
            }
            $corpo .= $linhas[$j];
        }

        return $corpo;
    }
}
