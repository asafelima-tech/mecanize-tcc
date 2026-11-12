<?php

/**
 * Cria um grafo de dados COERENTE no banco de teste (cliente → veículo → peça →
 * OS) e remove tudo no fim.
 *
 * Existe porque o MariaDB do XAMPP roda sem modo estrito: fixtures escritas
 * "no jeito" (tipo_pessoa 'FISICA', status 'aberta', veiculo_id ausente)
 * gravavam lixo em silêncio e os testes passavam. Com as foreign keys da
 * Etapa 1 esse lixo passa a ser recusado — então os testes precisam de dados
 * de verdade. Tudo que é criado por aqui é registrado e removido por
 * limpar(), na ordem que as chaves estrangeiras exigem.
 */
require_once __DIR__ . '/../app/core/Armazenamento.php';

final class Fixtures
{
    public PDO $pdo;

    /** Tabelas na ordem em que podem ser esvaziadas sem violar FK. */
    private const ORDEM_LIMPEZA = [
        'movimentacoes_caixa', 'movimentacoes_estoque', 'ordem_servico_parcelas',
        'ordem_servico_produtos', 'ordem_servico_servicos', 'ordem_servico_mao_obra',
        'anexos_ordem_servico', 'agendamentos', 'historico_estados', 'autorizacoes_cliente',
        'ordens_servico', 'orcamento_itens', 'orcamentos', 'avaliacao_itens', 'avaliacoes_tecnicas',
        'ocorrencias', 'veiculos', 'produtos', 'fornecedores', 'cartoes_corporativos', 'clientes',
        'caixa', 'modelos_veiculos', 'marcas_veiculos', 'usuarios',
    ];

    private const PK = [
        'movimentacoes_caixa' => 'id_movimentacao', 'movimentacoes_estoque' => 'id_movimentacao',
        'ordem_servico_parcelas' => 'id_parcela', 'ordem_servico_produtos' => 'id_OS_produto',
        'ordem_servico_servicos' => 'id', 'ordem_servico_mao_obra' => 'id_OS_mao',
        'anexos_ordem_servico' => 'id_anexo', 'agendamentos' => 'id_agendamento',
        'historico_estados' => 'id_historico', 'ordens_servico' => 'id_ordem_servico',
        'orcamento_itens' => 'id_orcamento_item', 'orcamentos' => 'id_orcamento',
        'veiculos' => 'id_veiculo', 'produtos' => 'id_produto', 'clientes' => 'id_cliente',
        'caixa' => 'id_caixa', 'modelos_veiculos' => 'id_modelo', 'marcas_veiculos' => 'id_marca',
        'usuarios' => 'id_usuario', 'autorizacoes_cliente' => 'id_autorizacao',
        'avaliacao_itens' => 'id_avaliacao_item', 'avaliacoes_tecnicas' => 'id_avaliacao',
        'ocorrencias' => 'id_ocorrencia', 'fornecedores' => 'id_fornecedor', 'cartoes_corporativos' => 'id_cartao',
    ];

    /** @var array<string,int[]> */
    private array $criados = [];

    private int $seq = 0;

    private ?array $marcaModelo = null;
    private ?int $categoria = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? new Database();
    }

    private function unico(): string
    {
        return substr(bin2hex(random_bytes(4)), 0, 7) . (++$this->seq);
    }

    private function inserir(string $tabela, array $dados): int
    {
        $colunas = implode(',', array_map(fn($c) => "`$c`", array_keys($dados)));
        $marcas  = implode(',', array_fill(0, count($dados), '?'));

        $this->pdo->prepare("INSERT INTO `{$tabela}` ({$colunas}) VALUES ({$marcas})")->execute(array_values($dados));

        $id = (int) $this->pdo->lastInsertId();
        $this->criados[$tabela][] = $id;

        return $id;
    }

    /** Registra uma linha criada por fora (ex.: pelo model) para ser removida. */
    public function registrar(string $tabela, int $id): void
    {
        $this->criados[$tabela][] = $id;
    }

    public function usuario(string $tipo = 'ADMIN'): int
    {
        return $this->inserir('usuarios', [
            'nome'  => 'PHPUnit ' . $tipo . ' ' . $this->unico(),
            'email' => 'phpunit_' . $this->unico() . '@teste.local',
            'senha' => password_hash('x', PASSWORD_DEFAULT),
            'tipo'  => $tipo,
            'ativo' => 1,
        ]);
    }

    public function cliente(array $extra = []): int
    {
        return $this->inserir('clientes', array_merge([
            'tipo_pessoa' => 'PF',
            'nome'        => 'Cliente PHPUnit ' . $this->unico(),
            'ativo'       => 1,
        ], $extra));
    }

    /** Devolve [marcaId, modeloId]; cria o par uma vez por Fixtures. */
    public function marcaModelo(): array
    {
        if ($this->marcaModelo === null) {
            $marca  = $this->inserir('marcas_veiculos', ['nome' => 'Marca PHPUnit ' . $this->unico()]);
            $modelo = $this->inserir('modelos_veiculos', ['marca_id' => $marca, 'nome' => 'Modelo PHPUnit']);
            $this->marcaModelo = [$marca, $modelo];
        }

        return $this->marcaModelo;
    }

    public function veiculo(int $clienteId): int
    {
        [$marca, $modelo] = $this->marcaModelo();
        // Placa Mercosul válida (AAA9A99) e única: o sistema valida o formato.
        do {
            $placa = chr(random_int(65, 90)) . chr(random_int(65, 90)) . chr(random_int(65, 90))
                . random_int(0, 9) . chr(random_int(65, 90)) . sprintf('%02d', random_int(0, 99));
            $existe = $this->pdo->prepare('SELECT 1 FROM veiculos WHERE placa_normalizada = ?');
            $existe->execute([$placa]);
        } while ($existe->fetchColumn());

        return $this->inserir('veiculos', [
            'cliente_id'        => $clienteId,
            'marca_id'          => $marca,
            'modelo_id'         => $modelo,
            'placa'             => $placa,
            'placa_normalizada' => $placa,
        ]);
    }

    public function categoriaProduto(): int
    {
        if ($this->categoria === null) {
            $existente = $this->pdo->query('SELECT id_categoria FROM categorias_produtos LIMIT 1')->fetchColumn();
            $this->categoria = $existente !== false
                ? (int) $existente
                : $this->inserir('categorias_produtos', ['nome' => 'Cat PHPUnit ' . $this->unico()]);
        }

        return $this->categoria;
    }

    public function produto(float $estoque = 10, float $precoVenda = 100, float $precoCusto = 60): int
    {
        return $this->inserir('produtos', [
            'categorias_produtos_id' => $this->categoriaProduto(),
            'codigo_interno'         => 'PH' . $this->unico(),
            'nome'                   => 'Peça PHPUnit ' . $this->unico(),
            'estoque_atual'          => $estoque,
            'estoque_minimo'         => 1,
            'preco_custo'            => $precoCusto,
            'preco_venda'            => $precoVenda,
            'ativo'                  => 1,
            'localizacao'            => 'T1',
        ]);
    }

    public function fornecedor(string $nome = ''): int
    {
        return $this->inserir('fornecedores', [
            'nome'  => $nome !== '' ? $nome : 'Fornecedor PHPUnit ' . $this->unico(),
            'ativo' => 1,
        ]);
    }

    public function cartao(array $extra = []): int
    {
        return $this->inserir('cartoes_corporativos', array_merge([
            'apelido'        => 'Cartão PHPUnit ' . $this->unico(),
            'instituicao'    => 'Banco Teste',
            'limite_total'   => 5000,
            'dia_fechamento' => 10,
            'dia_vencimento' => 20,
            'ativo'          => 1,
        ], $extra));
    }

    public function estoqueDe(int $produtoId): float
    {
        $stmt = $this->pdo->prepare('SELECT estoque_atual FROM produtos WHERE id_produto = ?');
        $stmt->execute([$produtoId]);

        return (float) $stmt->fetchColumn();
    }

    public function os(int $clienteId, int $veiculoId, array $extra = []): int
    {
        return $this->inserir('ordens_servico', array_merge([
            'numero'               => 'PH-' . $this->unico(),
            'cliente_id'           => $clienteId,
            'veiculo_id'           => $veiculoId,
            'status'               => 'ABERTA',
            'valor_total_pecas'    => 0,
            'valor_total_servicos' => 0,
            'valor_total_os'       => 0,
        ], $extra));
    }

    /** Peça lançada na OS por SQL direto (sem passar pelo model). */
    public function itemPeca(int $osId, int $produtoId, float $qtd, float $preco, string $status = 'PLANEJADO'): int
    {
        return $this->inserir('ordem_servico_produtos', [
            'ordem_servico_id' => $osId,
            'produto_id'       => $produtoId,
            'quantidade'       => $qtd,
            'valor_unitario'   => $preco,
            'status'           => $status,
        ]);
    }

    public function caixaAberto(int $usuarioId, float $abertura = 100): int
    {
        // Só pode haver um caixa ABERTO (garantido pelo banco): fecha qualquer resíduo.
        $this->pdo->exec("UPDATE caixa SET status = 'FECHADO', data_fechamento = NOW(), valor_fechamento = 0 WHERE status = 'ABERTO'");

        return $this->inserir('caixa', [
            'usuario_abertura_id' => $usuarioId,
            'valor_abertura'      => $abertura,
            'status'              => 'ABERTO',
        ]);
    }

    /**
     * Solicitações, cotações, pedidos, recebimentos e notificações que os testes criaram pelos
     * models: tudo o que aponta para um usuário, produto ou fornecedor de teste.
     */
    private function limparEstoqueECompras(): void
    {
        $ids = fn(string $t) => array_map('intval', $this->criados[$t] ?? []) ?: [0];
        $lista = fn(array $a) => implode(',', $a);

        $usuarios = $lista($ids('usuarios'));
        $produtos = $lista($ids('produtos'));
        $fornecs  = $lista($ids('fornecedores'));

        $pedidos = $this->pdo->query("
            SELECT id_pedido FROM pedidos_compra WHERE fornecedor_id IN ({$fornecs}) OR criado_por IN ({$usuarios})
            UNION SELECT pedido_id FROM pedido_compra_itens WHERE produto_id IN ({$produtos})
        ")->fetchAll(PDO::FETCH_COLUMN) ?: [0];
        $pedidosIn = $lista(array_map('intval', $pedidos));

        $solicitacoes = $this->pdo->query("
            SELECT id_solicitacao FROM solicitacoes_peca WHERE solicitante_id IN ({$usuarios}) OR produto_id IN ({$produtos})
            UNION SELECT solicitacao_id FROM pedido_compra_itens WHERE pedido_id IN ({$pedidosIn}) AND solicitacao_id IS NOT NULL
        ")->fetchAll(PDO::FETCH_COLUMN) ?: [0];
        $solicIn = $lista(array_map('intval', $solicitacoes));

        $this->pdo->exec("DELETE FROM recebimento_itens WHERE recebimento_id IN (SELECT id_recebimento FROM recebimentos_compra WHERE pedido_id IN ({$pedidosIn}))");
        $this->pdo->exec("DELETE FROM recebimentos_compra WHERE pedido_id IN ({$pedidosIn})");
        $this->pdo->exec("DELETE FROM pedido_compra_itens WHERE pedido_id IN ({$pedidosIn})");
        $this->pdo->exec("DELETE FROM cotacoes_compra WHERE solicitacao_id IN ({$solicIn}) OR fornecedor_id IN ({$fornecs}) OR produto_id IN ({$produtos})");
        $this->pdo->exec("DELETE FROM historico_estados WHERE (entidade = 'PEDIDO_COMPRA' AND entidade_id IN ({$pedidosIn})) OR (entidade = 'SOLICITACAO_PECA' AND entidade_id IN ({$solicIn}))");
        $this->pdo->exec("DELETE FROM pedidos_compra WHERE id_pedido IN ({$pedidosIn})");
        $this->pdo->exec("DELETE FROM solicitacoes_peca WHERE id_solicitacao IN ({$solicIn})");
        $this->pdo->exec("DELETE FROM notificacoes WHERE usuario_id IN ({$usuarios})");
    }

    /** Despesas, parcelas a pagar, pagamentos, faturas, comprovantes e o que gravaram no caixa. */
    private function limparFinanceiro(): void
    {
        $ids = fn(string $t) => array_map('intval', $this->criados[$t] ?? []) ?: [0];
        $lista = fn(array $a) => implode(',', $a);

        $usuarios = $lista($ids('usuarios'));
        $cartoes  = $lista($ids('cartoes_corporativos'));
        $fornecs  = $lista($ids('fornecedores'));
        $caixas   = $lista($ids('caixa'));

        $despesas = $this->pdo->query("SELECT id_despesa FROM despesas WHERE criado_por IN ({$usuarios}) OR cartao_id IN ({$cartoes}) OR fornecedor_id IN ({$fornecs})")->fetchAll(PDO::FETCH_COLUMN) ?: [0];
        $despIn = $lista(array_map('intval', $despesas));

        $pagamentos = $this->pdo->query("
            SELECT id_pagamento FROM pagamentos_pagar WHERE usuario_id IN ({$usuarios})
               OR parcela_id IN (SELECT id_conta_parcela FROM contas_pagar_parcelas WHERE despesa_id IN ({$despIn}))
               OR fatura_id IN (SELECT id_fatura FROM faturas_cartao WHERE cartao_id IN ({$cartoes}))
        ")->fetchAll(PDO::FETCH_COLUMN) ?: [0];
        $pagIn = $lista(array_map('intval', $pagamentos));

        $this->pdo->exec("UPDATE pedidos_compra SET conta_pagar_id = NULL WHERE conta_pagar_id IN ({$despIn})");
        $this->pdo->exec("DELETE FROM comprovantes_financeiros WHERE despesa_id IN ({$despIn}) OR pagamento_id IN ({$pagIn})");
        $this->pdo->exec("UPDATE contas_pagar_parcelas SET pagamento_id = NULL WHERE pagamento_id IN ({$pagIn})");
        $this->pdo->exec("DELETE FROM pagamentos_pagar WHERE id_pagamento IN ({$pagIn})");
        $this->pdo->exec("DELETE FROM contas_pagar_parcelas WHERE despesa_id IN ({$despIn})");
        $this->pdo->exec("DELETE FROM historico_estados WHERE (entidade = 'DESPESA' AND entidade_id IN ({$despIn})) OR (entidade = 'PAGAMENTO_PAGAR' AND entidade_id IN ({$pagIn}))");
        $this->pdo->exec("DELETE FROM despesas WHERE id_despesa IN ({$despIn})");
        $this->pdo->exec("DELETE FROM faturas_cartao WHERE cartao_id IN ({$cartoes})");
        $this->pdo->exec("DELETE FROM movimentacoes_caixa WHERE caixa_id IN ({$caixas}) OR usuario_id IN ({$usuarios})");
        $this->pdo->exec("UPDATE ordem_servico_mao_obra SET fechamento_pagamento_id = NULL WHERE fechamento_pagamento_id IN (SELECT id FROM funcionario_fechamento_pagamento WHERE tecnico_id IN ({$usuarios}) OR criado_por IN ({$usuarios}))");
        $this->pdo->exec("DELETE FROM funcionario_fechamento_pagamento WHERE tecnico_id IN ({$usuarios}) OR criado_por IN ({$usuarios})");
    }

    /** Conversas, mensagens, consentimentos, links do portal, cobranças online e eventos de webhook. */
    private function limparComunicacao(): void
    {
        $ids = fn(string $t) => array_map('intval', $this->criados[$t] ?? []) ?: [0];
        $lista = fn(array $a) => implode(',', $a);

        $clientes = $lista($ids('clientes'));
        $usuarios = $lista($ids('usuarios'));

        // Também os clientes que os models criaram (provisórios do atendimento rápido, etc.)
        $cobrancas = $this->pdo->query("SELECT id_cobranca FROM cobrancas_online WHERE cliente_id IN ({$clientes}) OR ordem_servico_id IN (SELECT id_ordem_servico FROM ordens_servico WHERE cliente_id IN ({$clientes}))")->fetchAll(PDO::FETCH_COLUMN) ?: [0];
        $cobIn = $lista(array_map('intval', $cobrancas));

        $this->pdo->exec("UPDATE ordem_servico_parcelas SET cobranca_online_id = NULL WHERE cobranca_online_id IN ({$cobIn})");
        $this->pdo->exec("DELETE FROM cobranca_parcelas WHERE cobranca_id IN ({$cobIn})");
        $this->pdo->exec("DELETE FROM cobrancas_online WHERE id_cobranca IN ({$cobIn})");
        $this->pdo->exec("DELETE FROM webhook_eventos WHERE provedor IN ('TESTE','SIMULADO','MERCADOPAGO','WHATSAPP')");
        $this->pdo->exec("DELETE FROM decisoes_portal WHERE cliente_id IN ({$clientes})");
        $this->pdo->exec("DELETE FROM portal_tokens WHERE cliente_id IN ({$clientes})");
        $this->pdo->exec("DELETE FROM mensagens WHERE conversa_id IN (SELECT id_conversa FROM conversas WHERE cliente_id IN ({$clientes}) OR identificador LIKE '5599%' OR responsavel_id IN ({$usuarios}))");
        $this->pdo->exec("DELETE FROM conversas WHERE cliente_id IN ({$clientes}) OR identificador LIKE '5599%' OR responsavel_id IN ({$usuarios})");
        $this->pdo->exec("DELETE FROM consentimentos_comunicacao WHERE cliente_id IN ({$clientes})");
    }

    /** Remove tudo o que foi criado, e o que ficou pendurado nas OS criadas. */
    public function limpar(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            // O que os models criaram a partir dos clientes de teste (OS, orçamentos, ocorrências,
            // avaliações…) entra na limpeza sem cada teste precisar registrar item por item.
            foreach ($this->criados['clientes'] ?? [] as $cli) {
                $cli = (int) $cli;
                foreach (['ordens_servico' => 'id_ordem_servico', 'orcamentos' => 'id_orcamento', 'ocorrencias' => 'id_ocorrencia'] as $tabela => $pk) {
                    foreach ($this->pdo->query("SELECT `{$pk}` FROM `{$tabela}` WHERE cliente_id = {$cli}")->fetchAll(PDO::FETCH_COLUMN) as $id) {
                        $this->criados[$tabela][] = (int) $id;
                    }
                }
                foreach ($this->pdo->query("SELECT id_veiculo FROM veiculos WHERE cliente_id = {$cli}")->fetchAll(PDO::FETCH_COLUMN) as $id) {
                    $this->criados['veiculos'][] = (int) $id;
                }
                $this->pdo->exec("DELETE FROM autorizacoes_cliente WHERE cliente_id = {$cli}");
                $this->pdo->exec("DELETE FROM historico_estados WHERE entidade = 'CLIENTE' AND entidade_id = {$cli}");
            }

            foreach ($this->criados['ocorrencias'] ?? [] as $oc) {
                $this->pdo->exec("DELETE FROM historico_estados WHERE entidade = 'OCORRENCIA' AND entidade_id = " . (int) $oc);
                $this->pdo->exec("DELETE FROM avaliacao_itens WHERE avaliacao_id IN (SELECT id_avaliacao FROM avaliacoes_tecnicas WHERE ocorrencia_id = " . (int) $oc . ")");
                $this->pdo->exec("DELETE FROM avaliacoes_tecnicas WHERE ocorrencia_id = " . (int) $oc);
                $this->pdo->exec("UPDATE orcamentos SET avaliacao_id = NULL, ocorrencia_id = NULL WHERE ocorrencia_id = " . (int) $oc);
            }

            foreach ($this->criados['orcamentos'] ?? [] as $orc) {
                $this->pdo->exec("DELETE FROM historico_estados WHERE entidade = 'ORCAMENTO' AND entidade_id = " . (int) $orc);
                // os itens vão junto: sem a FK, item solto de um id reaproveitado se mistura ao orçamento novo e a aprovação falha
                $this->pdo->exec("DELETE FROM orcamento_itens WHERE orcamento_id = " . (int) $orc);
                $this->pdo->exec("DELETE FROM orcamento_versoes WHERE orcamento_id = " . (int) $orc);
                $this->pdo->exec("DELETE FROM autorizacoes_cliente WHERE orcamento_id = " . (int) $orc);
            }

            // Dependentes que os testes criam pelos models, sem registrar.
            foreach ($this->criados['ordens_servico'] ?? [] as $os) {
                // Fotos de evidência: apaga o arquivo de storage/ e a linha.
                try {
                    foreach ($this->pdo->query('SELECT caminho FROM evidencias_os WHERE ordem_servico_id = ' . (int) $os)->fetchAll(PDO::FETCH_COLUMN) as $rel) {
                        if ($abs = Armazenamento::caminho((string) $rel)) {
                            @unlink($abs);
                        }
                    }
                    $this->pdo->exec('DELETE FROM evidencias_os WHERE ordem_servico_id = ' . (int) $os);
                } catch (\PDOException $e) {
                    // migração da Etapa 6 ainda não aplicada neste banco
                }

                foreach (['movimentacoes_caixa', 'ordem_servico_parcelas', 'ordem_servico_produtos',
                          'ordem_servico_servicos', 'ordem_servico_mao_obra', 'anexos_ordem_servico',
                          'agendamentos', 'autorizacoes_cliente'] as $t) {
                    $this->pdo->exec("DELETE FROM `{$t}` WHERE ordem_servico_id = " . (int) $os);
                }
                $this->pdo->exec("DELETE FROM historico_estados WHERE entidade = 'PARCELA_OS' AND entidade_id IN (SELECT id_parcela FROM ordem_servico_parcelas WHERE ordem_servico_id = " . (int) $os . ")");
                $this->pdo->exec("DELETE FROM movimentacoes_estoque WHERE ordem_servico_id = " . (int) $os);
                $this->pdo->exec("DELETE FROM historico_estados WHERE entidade = 'OS' AND entidade_id = " . (int) $os);
            }

            $this->limparEstoqueECompras();
            $this->limparFinanceiro();
            $this->limparComunicacao();

            foreach ($this->criados['produtos'] ?? [] as $p) {
                $this->pdo->exec("DELETE FROM movimentacoes_estoque WHERE produto_id = " . (int) $p);
            }

            foreach (self::ORDEM_LIMPEZA as $tabela) {
                foreach ($this->criados[$tabela] ?? [] as $id) {
                    $this->pdo->exec("DELETE FROM `{$tabela}` WHERE `" . self::PK[$tabela] . '` = ' . (int) $id);
                }
            }
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        $this->criados = [];
    }
}
