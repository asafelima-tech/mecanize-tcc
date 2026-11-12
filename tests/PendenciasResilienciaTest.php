<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/models/Pendencias.php';

/**
 * A tela inicial nunca deixa de abrir por causa de uma contagem: cada consulta falha sozinha.
 * Sem banco do Mecanize (usa um SQLite vazio como "conexão quebrada").
 */
final class PendenciasResilienciaTest extends TestCase
{
    public function testConsultasQueFalhamViramZeroENadaEstoura(): void
    {
        $semTabelas = new PDO('sqlite::memory:');
        $semTabelas->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        foreach (['ADMIN', 'ATENDENTE', 'TECNICO'] as $perfil) {
            $this->assertSame([], (new Pendencias($semTabelas))->paraPerfil($perfil), $perfil);
        }
    }
}
