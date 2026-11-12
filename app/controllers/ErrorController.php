<?php

require_once __DIR__ . '/../core/Erro.php';

/**
 * Telas de erro acessíveis por rota.
 *
 * A maioria dos erros é disparada de dentro do fluxo (Erro::exibir), mas
 * algumas situações chegam como navegação de verdade — o link de "página não
 * encontrada" e a página de manutenção, que precisa existir como endereço
 * fixo pra poder ser divulgada.
 */
class ErrorController extends Controller
{
    public function erro404()
    {
        Erro::exibir('nao-encontrado');
    }

    public function semPermissao()
    {
        Erro::exibir('sem-permissao');
    }

    public function indisponivel()
    {
        Erro::exibir('indisponivel');
    }

    /**
     * Usada pelo cliente quando o navegador está offline ou a requisição
     * estourou o tempo — o JavaScript redireciona pra cá pra que a situação
     * tenha uma tela de verdade, e não só um aviso flutuante.
     */
    public function semConexao()
    {
        Erro::exibir('sem-conexao');
    }

    public function tempoEsgotado()
    {
        Erro::exibir('tempo-esgotado');
    }
}
