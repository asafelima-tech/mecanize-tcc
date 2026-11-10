<?php

/**
 * Executa um trecho dentro de UMA transação — abrindo uma só se ainda não
 * houver, e deixando para quem abriu o commit/rollback.
 *
 * Por que existe: as operações críticas (conferir OS, consumir peça, pagar
 * parcela) precisam alterar várias tabelas ou tudo ou nada, e são chamadas tanto
 * isoladamente quanto de dentro de outra operação maior. PDO::beginTransaction()
 * dentro de uma transação já aberta lança exceção; este helper resolve os dois
 * casos com o mesmo código.
 *
 * ATENÇÃO — duas conexões, dois mundos: cada model que faz `new Database()` abre
 * a SUA conexão. Uma conexão não enxerga o que a outra ainda não gravou, e uma
 * transação que travou uma linha (SELECT … FOR UPDATE) faz a outra esperar até o
 * timeout. Por isso os serviços de dinheiro e estoque recebem o PDO por parâmetro
 * e trabalham todos na mesma conexão.
 */
final class Transacao
{
    /**
     * @template T
     * @param callable():T $trabalho
     * @return T
     * @throws \Throwable repassa a falha depois de desfazer o que esta chamada abriu
     */
    public static function executar(PDO $pdo, callable $trabalho)
    {
        $dona = !$pdo->inTransaction();

        if ($dona) {
            $pdo->beginTransaction();
        }

        try {
            $resultado = $trabalho();

            if ($dona) {
                $pdo->commit();
            }

            return $resultado;
        } catch (\Throwable $e) {
            if ($dona && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
