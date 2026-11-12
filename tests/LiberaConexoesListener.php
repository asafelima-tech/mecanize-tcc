<?php

use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;

/**
 * Libera as conexões de banco que cada teste deixou em suas propriedades.
 *
 * Por que existe: o PHPUnit mantém TODAS as instâncias de teste vivas até o fim da
 * execução, e cada `new Database()` (models, fixtures) abre uma conexão própria.
 * Com ~150 testes isso estoura o max_connections do MySQL do XAMPP ("Too many
 * connections") e os últimos testes falham sem culpa nenhuma. Ao fim de cada teste
 * as propriedades dele são zeradas: os PDO perdem a última referência e a conexão
 * fecha na hora.
 *
 * Registrado em phpunit.xml. Vale para todos os testes, sem cada um lembrar.
 */
final class LiberaConexoesListener implements TestListener
{
    use TestListenerDefaultImplementation;

    public function endTest(Test $test, float $time): void
    {
        foreach ((new ReflectionObject($test))->getProperties() as $propriedade) {
            $dono = $propriedade->getDeclaringClass()->getName();

            // Propriedades do próprio PHPUnit (nome do teste, dependências…) não se tocam.
            if (str_starts_with($dono, 'PHPUnit\\') || $propriedade->isStatic()) {
                continue;
            }

            $nome = $propriedade->getName();

            // unset precisa rodar no escopo da classe que declarou a propriedade
            // (cobre as privadas), senão o PHP recusa.
            Closure::bind(function () use ($nome) {
                unset($this->$nome);
            }, $test, $dono)();
        }
    }
}
