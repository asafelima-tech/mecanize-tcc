<?php

use PHPUnit\Framework\TestCase;

/**
 * Base dos testes: duas oficinas com um administrador, um cliente, um veículo e uma OS cada.
 * Cada teste roda dentro de uma transação desfeita no final — o banco volta limpo.
 */
abstract class Cenario extends TestCase
{
    protected const A = 1;
    protected const B = 2;
    protected const ADMIN_A = 11;
    protected const ADMIN_B = 21;
    protected const SENHA = 'segredo123';

    protected PDO $db;

    protected function setUp(): void
    {
        $this->db = Banco::conexao();
        $this->db->beginTransaction();

        $hash = password_hash(self::SENHA, PASSWORD_DEFAULT);
        $this->db->exec("INSERT INTO oficinas (id, nome, status, licenca_valida_ate) VALUES
            (1, 'Oficina A', 'ATIVA', CURDATE() + INTERVAL 30 DAY), (2, 'Oficina B', 'ATIVA', CURDATE() + INTERVAL 30 DAY)");
        $this->db->exec('UPDATE oficinas SET proximo_numero_os = 2'); // cada oficina já tem a OS nº 1 abaixo
        $this->db->exec("INSERT INTO usuarios (id, oficina_id, nome, email, senha_hash, perfil, atualizado_em) VALUES
            (11, 1, 'Ana Admin', 'ana@a.test', '{$hash}', 'ADMIN', '2026-01-01 10:00:00'),
            (12, 1, 'Tiago Técnico', 'tiago@a.test', '{$hash}', 'TECNICO', '2026-01-01 10:00:00'),
            (21, 2, 'Bruno Admin', 'bruno@b.test', '{$hash}', 'ADMIN', '2026-01-01 10:00:00'),
            (99, NULL, 'Equipe Mecanize', 'suporte@mecanize.test', '{$hash}', 'PLATAFORMA', '2026-01-01 10:00:00')");
        $this->db->exec("INSERT INTO clientes (id, oficina_id, tipo_pessoa, nome, documento) VALUES
            (101, 1, 'PF', 'Cliente da A', '11111111111'), (201, 2, 'PF', 'Cliente da B', '11111111111')");
        $this->db->exec("INSERT INTO veiculos (id, oficina_id, cliente_id, modelo_texto, placa, placa_normalizada) VALUES
            (101, 1, 101, 'Gol', 'ABC-1234', 'ABC1234'), (201, 2, 201, 'Uno', 'ABC-1234', 'ABC1234')");
        $this->db->exec("INSERT INTO ordens_servico (id, oficina_id, numero, cliente_id, veiculo_id, status, valor_total, criado_por) VALUES
            (101, 1, 1, 101, 101, 'ORCAMENTO', 100, 11), (201, 2, 1, 201, 201, 'ORCAMENTO', 200, 21)");

        $this->entrarComo(self::ADMIN_A);
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        Contexto::limpar();
    }

    /** Monta o Contexto como o front controller faz depois do login. */
    protected function entrarComo(int $usuarioId): void
    {
        $c = (new Usuario())->paraSessao($usuarioId);
        $oficina = $c['oficina_id'] === null ? null : [
            'id' => (int) $c['oficina_id'], 'nome' => $c['oficina_nome'], 'status' => $c['oficina_status'],
            'licenca_valida_ate' => $c['licenca_valida_ate'], 'plano' => $c['plano'],
        ];
        Contexto::limpar();
        Contexto::definir($c, $oficina);
    }

    /** Model de teste que expõe os métodos protegidos do Modelo. */
    protected function modelo(): Modelo
    {
        return new class extends Modelo {
            public function __call($m, $a) { return $this->$m(...$a); }
        };
    }
}
