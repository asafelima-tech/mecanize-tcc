<?php

/**
 * Migração Mecanize v1 (53 tabelas) → v2 (17 tabelas, multi-oficina).
 *
 * SEGURANÇA
 * - O banco de ORIGEM (DB_NAME do .env, normalmente mecanize_db) é SOMENTE LIDO:
 *   este script não executa nenhum INSERT/UPDATE/DELETE/DROP nele.
 * - Tudo é gravado num banco NOVO (padrão: <origem>_v2). Desfazer = apagar esse
 *   banco (database/v2/desfazer.php) e o sistema antigo continua intacto.
 * - Antes de copiar, confere que as tabelas sem destino na v2 estão vazias.
 *   Se alguma tiver dados, ABORTA em vez de descartar em silêncio.
 * - Os dados são copiados numa transação. Depois a conferência compara origem x
 *   destino (contagens, totais de OS, estoque, caixas, comissões, senhas). Se algo
 *   não bater, faz ROLLBACK e nada fica gravado.
 *
 * USO (no XAMPP):
 *   C:\xampp\php\php.exe database\v2\migrar.php            cria mecanize_db_v2
 *   C:\xampp\php\php.exe database\v2\migrar.php --recriar  apaga e recria SÓ o _v2
 *
 * Decisões aprovadas em 10/11/2026: oficina inicial "Oficina Demonstração";
 * descartar os 4 lançamentos manuais de teste do caixa (ids 17–20); descartar os
 * tokens antigos de recuperação de senha (as senhas não mudam).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/core/Env.php';
Env::carregar(__DIR__ . '/../../.env');

const OFICINA_ID   = 1;
const OFICINA_NOME = 'Oficina Demonstração';

/** Lançamentos manuais de teste do caixa que o dono do projeto mandou descartar. */
const CAIXA_MOV_DESCARTAR = [17, 18, 19, 20];

/** Tabelas da v1 que deixam de existir sem destino de dados: precisam estar vazias. */
const TABELAS_DEVEM_ESTAR_VAZIAS = [
    'agendamentos', 'anexos_ordem_servico', 'autorizacoes_cliente', 'avaliacao_itens', 'avaliacoes_tecnicas',
    'cartoes_corporativos', 'cobranca_parcelas', 'cobrancas_online', 'comprovantes_financeiros',
    'consentimentos_comunicacao', 'contas_pagar_parcelas', 'conversas', 'cotacoes_compra', 'decisoes_portal',
    'despesas', 'evidencias_os', 'faturas_cartao', 'fornecedores', 'funcionario_fechamento_pagamento',
    'historico_estados', 'mensagens', 'ocorrencias', 'ordem_servico_parcelas', 'pagamentos_pagar',
    'pedido_compra_itens', 'pedidos_compra', 'portal_tokens', 'recebimento_itens', 'recebimentos_compra',
    'solicitacoes_peca', 'tecnico_remuneracao_regra', 'tecnico_remuneracao_regra_categoria', 'webhook_eventos',
];
// Observação: agendamentos e fornecedores TÊM destino na v2 (são copiados abaixo); estão na lista
// apenas porque hoje estão vazios e a cópia deles não foi exercitada com dados reais. Se tiverem
// dados, o script para e a cópia é revisada antes — melhor do que confiar num caminho não testado.

// ---------------------------------------------------------------------------

function sair(string $msg, int $codigo = 1): never
{
    fwrite(STDERR, "\nABORTADO: {$msg}\n");
    exit($codigo);
}

function linha(string $msg): void
{
    echo $msg, "\n";
}

$recriar = in_array('--recriar', $argv, true);

$host    = env('DB_HOST') ?? sair('DB_HOST ausente no .env');
$usuario = env('DB_USER') ?? sair('DB_USER ausente no .env');
$senha   = env('DB_PASS', '');
$origem  = env('DB_NAME') ?? sair('DB_NAME ausente no .env');
$destino = env('DB_DESTINO', $origem . '_v2');

if (!preg_match('/^\w+$/', $origem) || !preg_match('/^\w+_v2$/', $destino) || $destino === $origem) {
    sair("nomes de banco inválidos (origem '{$origem}', destino '{$destino}'). O destino precisa terminar em _v2.");
}

$pdo = new PDO("mysql:host={$host};charset=utf8mb4", $usuario, $senha, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

linha("Origem (somente leitura): {$origem}");
linha("Destino (novo):           {$destino}");

// 1. Pré-condições --------------------------------------------------------------
$tabelasOrigem = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = " . $pdo->quote($origem))
    ->fetchAll(PDO::FETCH_COLUMN);
if (count($tabelasOrigem) === 0) {
    sair("o banco de origem '{$origem}' não existe ou está vazio.");
}
if (in_array('oficinas', $tabelasOrigem, true)) {
    sair("o banco de origem já parece ser da v2 (tem a tabela 'oficinas').");
}

$O = "`{$origem}`";
$D = "`{$destino}`";
$q = fn(string $sql) => $pdo->query($sql)->fetchAll();
$v = fn(string $sql) => $pdo->query($sql)->fetchColumn();

$naoVazias = [];
foreach (TABELAS_DEVEM_ESTAR_VAZIAS as $t) {
    if (in_array($t, $tabelasOrigem, true) && (int) $v("SELECT COUNT(*) FROM {$O}.`{$t}`") > 0) {
        $naoVazias[] = $t;
    }
}
if ($naoVazias) {
    sair("estas tabelas têm dados e a migração não prevê destino para eles: " . implode(', ', $naoVazias));
}

$movCaixaInesperadas = $v("SELECT COUNT(*) FROM {$O}.movimentacoes_caixa WHERE id_movimentacao NOT IN (" . implode(',', CAIXA_MOV_DESCARTAR) . ")");
if ((int) $movCaixaInesperadas > 0) {
    sair("há lançamentos de caixa além dos 4 de teste aprovados para descarte ({$movCaixaInesperadas}). Revise antes de migrar.");
}

$itensForaDoPadrao = (int) $v("SELECT COUNT(*) FROM {$O}.ordem_servico_produtos WHERE status NOT IN ('PLANEJADO','RESERVADO','CONSUMIDO','DEVOLVIDO','CANCELADO')");
if ($itensForaDoPadrao > 0) {
    sair("há itens de OS com status desconhecido.");
}

$osComJuros = (int) $v("SELECT COUNT(*) FROM {$O}.ordens_servico WHERE COALESCE(pagamento_juros,0) <> 0 OR COALESCE(pagamento_taxa,0) <> 0");
if ($osComJuros > 0) {
    sair("há OS com juros/taxa de pagamento; elas precisariam de lançamentos financeiros (não previsto).");
}

// 2. Banco de destino ------------------------------------------------------------
$existe = (bool) $v("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = " . $pdo->quote($destino));
if ($existe && !$recriar) {
    sair("o banco '{$destino}' já existe. Use --recriar para apagá-lo e criar de novo (a origem não é tocada).");
}
if ($existe) {
    $pdo->exec("DROP DATABASE {$D}");
    linha("Banco {$destino} anterior apagado (--recriar).");
}
$pdo->exec("CREATE DATABASE {$D} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE {$D}");

$schema = file_get_contents(__DIR__ . '/schema.sql');
$schema = preg_replace('/^\s*--.*$/m', '', $schema);          // linhas de comentário
$schema = preg_replace('/\s--\s.*$/m', '', $schema);           // comentários no fim da linha
foreach (array_filter(array_map('trim', explode(";\n", $schema))) as $stmt) {
    $pdo->exec($stmt);
}
linha("Estrutura criada: " . $v("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $pdo->quote($destino)) . " tabelas.");

// 3. Cópia dos dados (transação) ---------------------------------------------------
$pdo->beginTransaction();

try {
    $emp = fn(string $k) => ($x = env($k)) === null || $x === '' ? null : $x;

    $pdo->prepare("INSERT INTO oficinas (id, nome, cnpj, email, telefone, endereco, horario, plano, limite_usuarios, status, licenca_valida_ate)
                   VALUES (?, ?, ?, ?, ?, ?, ?, 'BASICO', 20, 'ATIVA', DATE_ADD(CURDATE(), INTERVAL 1 YEAR))")
        ->execute([OFICINA_ID, OFICINA_NOME, $emp('EMPRESA_CNPJ') ? preg_replace('/\D/', '', $emp('EMPRESA_CNPJ')) : null,
                   $emp('EMPRESA_EMAIL'), $emp('EMPRESA_TELEFONE'), $emp('EMPRESA_ENDERECO'), $emp('EMPRESA_HORARIO')]);

    $pdo->exec("INSERT INTO modelos_veiculos (id, marca, nome, combustiveis)
                SELECT mo.id_modelo, ma.nome, mo.nome, mo.combustiveis
                FROM {$O}.modelos_veiculos mo JOIN {$O}.marcas_veiculos ma ON ma.id_marca = mo.marca_id");

    // Senha copiada byte a byte; tokens de recuperação antigos não são copiados.
    $pdo->exec("INSERT INTO usuarios (id, oficina_id, nome, email, telefone, senha_hash, perfil, ativo, tentativas_login, bloqueado_ate, criado_em, atualizado_em)
                SELECT id_usuario, " . OFICINA_ID . ", nome, email, NULLIF(telefone,''), senha, tipo, ativo,
                       COALESCE(tentativas_login,0), bloqueado_ate, criado_em, atualizado_em
                FROM {$O}.usuarios");

    // Sem registro de consentimento na v1 = sem autorização de contato (mesma regra da v1).
    $pdo->exec("INSERT INTO clientes (id, oficina_id, tipo_pessoa, nome, documento, email, telefone, endereco, bairro, cidade, uf, cep,
                                      aceita_contato, observacoes, ativo, criado_em, atualizado_em)
                SELECT id_cliente, " . OFICINA_ID . ", tipo_pessoa, nome,
                       NULLIF(REGEXP_REPLACE(COALESCE(IF(tipo_pessoa='PJ', cnpj, cpf), ''), '[^0-9]', ''), ''),
                       NULLIF(email,''), NULLIF(telefone,''), NULLIF(endereco,''), NULLIF(bairro,''), NULLIF(cidade,''),
                       NULLIF(uf,''), NULLIF(REGEXP_REPLACE(COALESCE(cep,''), '[^0-9]', ''), ''),
                       0, observacoes, ativo, criado_em, atualizado_em
                FROM {$O}.clientes");

    $pdo->exec("INSERT INTO veiculos (id, oficina_id, cliente_id, modelo_id, modelo_texto, placa, placa_normalizada, ano, cor, combustivel, observacoes, criado_em)
                SELECT v.id_veiculo, " . OFICINA_ID . ", v.cliente_id, mo.id_modelo,
                       IF(mo.id_modelo IS NULL, COALESCE(ma.nome, 'Modelo não informado'), NULL),
                       v.placa, v.placa_normalizada, v.ano, NULLIF(v.cor,''), NULLIF(v.combustivel,''), v.observacoes, v.criado_em
                FROM {$O}.veiculos v
                LEFT JOIN {$O}.modelos_veiculos mo ON mo.id_modelo = v.modelo_id
                LEFT JOIN {$O}.marcas_veiculos ma ON ma.id_marca = v.marca_id");

    $pdo->exec("INSERT INTO fornecedores (id, oficina_id, nome, documento, contato, telefone, email, observacoes, ativo, criado_em)
                SELECT id_fornecedor, " . OFICINA_ID . ", nome, NULLIF(documento_normalizado,''), contato, telefone, email, observacoes, ativo, criado_em
                FROM {$O}.fornecedores");

    $pdo->exec("INSERT INTO produtos (id, oficina_id, tipo, nome, categoria, codigo_interno, codigo_barras, marca, descricao, localizacao,
                                      estoque_atual, estoque_minimo, preco_custo, preco_venda, ativo, criado_em, atualizado_em)
                SELECT p.id_produto, " . OFICINA_ID . ", 'PECA', p.nome, c.nome, NULLIF(p.codigo_interno,''), NULLIF(p.codigo_barras,''),
                       p.marca, p.descricao, p.localizacao, p.estoque_atual, p.estoque_minimo, p.preco_custo, p.preco_venda,
                       p.ativo, p.criado_em, p.atualizado_em
                FROM {$O}.produtos p LEFT JOIN {$O}.categorias_produtos c ON c.id_categoria = p.categorias_produtos_id");

    // --- Ordens de serviço (OS da v1 + orçamento vinculado) ---
    $mapaStatus = [
        'ABERTA' => 'APROVADA', 'EM_ANDAMENTO' => 'EM_EXECUCAO', 'AGUARDANDO_PECA' => 'AGUARDANDO_PECA',
        'AGUARDANDO_CONFERENCIA' => 'PRONTA', 'AGUARDANDO_RETIRADA' => 'PRONTA', 'CONCLUIDA' => 'ENTREGUE', 'CANCELADA' => 'CANCELADA',
    ];
    $insOs = $pdo->prepare("INSERT INTO ordens_servico
        (id, oficina_id, numero, cliente_id, veiculo_id, tecnico_id, status, km_veiculo, defeito_relatado, diagnostico, observacoes,
         observacoes_internas, checklist_entrega, validade_orcamento, previsao_conclusao, aprovado_em, assinatura_cliente, valor_aprovado,
         recusado_em, motivo_recusa, concluido_em, conferido_por, conferido_em, entregue_em, cancelado_em, token_rastreio, criado_por, criado_em)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $insHist = $pdo->prepare("INSERT INTO historico (oficina_id, ordem_servico_id, acao, estado_anterior, estado_novo, motivo, dados, criado_em)
                              VALUES (" . OFICINA_ID . ", ?, ?, ?, ?, ?, ?, ?)");

    $osV1 = $q("SELECT * FROM {$O}.ordens_servico ORDER BY id_ordem_servico");
    $orcPorId = [];
    foreach ($q("SELECT * FROM {$O}.orcamentos") as $o) {
        $orcPorId[(int) $o['id_orcamento']] = $o;
    }
    $usados = [];
    $maiorNumero = 0;

    foreach ($osV1 as $os) {
        $id  = (int) $os['id_ordem_servico'];
        $num = preg_match('/(\d+)/', (string) $os['numero'], $m) ? (int) $m[1] : $id;
        if (isset($usados[$num])) {
            sair("número de OS repetido após conversão: {$os['numero']}");
        }
        $usados[$num] = true;
        $maiorNumero  = max($maiorNumero, $num);

        $orc    = $os['orcamento_id'] !== null ? ($orcPorId[(int) $os['orcamento_id']] ?? null) : null;
        $status = $mapaStatus[$os['status']] ?? sair("status de OS desconhecido: {$os['status']}");
        $tec    = $v("SELECT MIN(tecnico_id) FROM {$O}.ordem_servico_mao_obra WHERE ordem_servico_id = {$id} AND tecnico_id IS NOT NULL");
        $tec    = $tec ?: ($os['tecnico_finalizacao_id'] ?? ($orc['mecanico_id'] ?? null));
        $fim    = in_array($os['status'], ['AGUARDANDO_CONFERENCIA', 'AGUARDANDO_RETIRADA', 'CONCLUIDA'], true)
                  ? ($os['data_finalizacao_tecnica'] ?? $os['data_conclusao']) : null;

        $insOs->execute([
            $id, OFICINA_ID, $num, $os['cliente_id'], $os['veiculo_id'], $tec, $status, $os['km_veiculo'],
            $os['defeito_relatado'], $os['diagnostico'], $os['observacoes'], $os['observacoes_tecnicas'], $os['checklist_entrega'],
            $orc['data_validade'] ?? null, $os['previsao_conclusao'],
            $status === 'CANCELADA' ? null : ($orc['data_aprovacao'] ?? $os['data_abertura']),
            $os['assinatura_cliente'] ?? ($orc['assinatura_cliente'] ?? null),
            $orc['valor_total_orcamento'] ?? null,
            null, null, $fim, $os['conferido_por'], $os['data_conferencia'],
            $os['status'] === 'CONCLUIDA' ? $os['data_conclusao'] : null,
            $os['status'] === 'CANCELADA' ? ($os['data_conclusao'] ?? $os['data_abertura']) : null,
            $os['token_rastreio'], null, $os['data_abertura'],
        ]);

        $insHist->execute([$id, 'MIGRACAO_V1', $os['status'], $status,
            $orc ? 'OS da v1 com orçamento ' . $orc['numero'] . '; data de aprovação vinda do orçamento.'
                 : 'OS da v1 sem orçamento; data de abertura usada como data de aprovação.',
            json_encode(['numero_v1' => $os['numero'], 'status_v1' => $os['status'], 'orcamento_v1' => $orc['numero'] ?? null,
                         'valor_total_os_v1' => $os['valor_total_os'], 'valor_autorizado_v1' => $os['valor_autorizado']], JSON_UNESCAPED_UNICODE),
            $os['data_abertura']]);

        if ($orc) {
            $orcSemAssinatura = $orc;
            unset($orcSemAssinatura['assinatura_cliente']);
            $insHist->execute([$id, 'ORCAMENTO_VERSAO', null, 'APROVADA', 'Orçamento original preservado na migração.',
                json_encode([
                    'orcamento' => $orcSemAssinatura,
                    'itens'     => $q("SELECT * FROM {$O}.orcamento_itens WHERE orcamento_id = " . (int) $orc['id_orcamento'] . " ORDER BY id_orcamento_item"),
                    'versoes'   => $q("SELECT * FROM {$O}.orcamento_versoes WHERE orcamento_id = " . (int) $orc['id_orcamento'] . " ORDER BY versao"),
                ], JSON_UNESCAPED_UNICODE),
                $orc['data_aprovacao'] ?? $orc['data_criacao']]);
            unset($orcPorId[(int) $orc['id_orcamento']]);
        }
    }

    // Orçamentos que nunca viraram OS → OS no status ORCAMENTO / RECUSADA / CANCELADA / APROVADA.
    $novoId = (int) $v("SELECT COALESCE(MAX(id_ordem_servico),0) FROM {$O}.ordens_servico");
    $mapaOrc = ['pendente' => 'ORCAMENTO', 'aprovado' => 'APROVADA', 'reprovado' => 'RECUSADA', 'cancelado' => 'CANCELADA'];
    $insItem = $pdo->prepare("INSERT INTO ordem_servico_itens (oficina_id, ordem_servico_id, tipo, origem, produto_id, descricao, categoria,
                              quantidade, valor_unitario, custo_unitario, estoque_status, executado, criado_em)
                              VALUES (" . OFICINA_ID . ", ?, ?, 'ORCAMENTO', ?, ?, ?, ?, ?, ?, ?, 0, ?)");
    foreach ($orcPorId as $orc) {
        $id = ++$novoId;
        $num = ++$maiorNumero;
        $status = $mapaOrc[$orc['status']] ?? sair("status de orçamento desconhecido: {$orc['status']}");
        $insOs->execute([
            $id, OFICINA_ID, $num, $orc['cliente_id'], $orc['veiculo_id'], $orc['mecanico_id'], $status, $orc['km_veiculo'],
            $orc['defeito_relatado'], $orc['diagnostico'], $orc['observacoes'], null, null, $orc['data_validade'], null,
            $status === 'APROVADA' ? ($orc['data_aprovacao'] ?? $orc['data_criacao']) : null,
            $orc['assinatura_cliente'], $status === 'APROVADA' ? $orc['valor_total_orcamento'] : null,
            $status === 'RECUSADA' ? ($orc['data_recusa'] ?? $orc['data_criacao']) : null, $orc['motivo_recusa'],
            null, null, null, null, $status === 'CANCELADA' ? $orc['data_criacao'] : null,
            $orc['token_rastreio'], null, $orc['data_criacao'],
        ]);
        foreach ($q("SELECT i.*, p.preco_custo FROM {$O}.orcamento_itens i LEFT JOIN {$O}.produtos p ON i.tipo='produto' AND p.id_produto = i.item_id
                     WHERE i.orcamento_id = " . (int) $orc['id_orcamento']) as $it) {
            $peca = $it['tipo'] === 'produto';
            $insItem->execute([$id, $peca ? 'PECA' : 'SERVICO', $peca ? $it['item_id'] : null, $it['nome'], $it['categoria'],
                $it['quantidade'], $it['preco_unitario'], $peca ? $it['preco_custo'] : null, $peca ? 'PENDENTE' : 'NAO_SE_APLICA', $orc['data_criacao']]);
        }
        $insHist->execute([$id, 'ORCAMENTO_VERSAO', null, $status, 'Orçamento da v1 sem OS; migrado como OS em ' . $status . '.',
            json_encode(['numero_v1' => $orc['numero'], 'orcamento' => array_diff_key($orc, ['assinatura_cliente' => 1]),
                         'versoes' => $q("SELECT * FROM {$O}.orcamento_versoes WHERE orcamento_id = " . (int) $orc['id_orcamento'])], JSON_UNESCAPED_UNICODE),
            $orc['data_criacao']]);
    }
    $pdo->exec("UPDATE oficinas SET proximo_numero_os = " . ($maiorNumero + 1) . " WHERE id = " . OFICINA_ID);

    // --- Itens das OS da v1 (descrição, categoria e preço congelados) ---
    $statusEstoque = "CASE op.status WHEN 'CONSUMIDO' THEN 'BAIXADO' WHEN 'DEVOLVIDO' THEN 'DEVOLVIDO' ELSE 'PENDENTE' END";
    $pdo->exec("INSERT INTO ordem_servico_itens (oficina_id, ordem_servico_id, tipo, origem, produto_id, descricao, categoria, quantidade,
                                                 valor_unitario, custo_unitario, estoque_status, executado, criado_em)
                SELECT " . OFICINA_ID . ", op.ordem_servico_id, 'PECA', 'ORCAMENTO', op.produto_id, p.nome, c.nome, op.quantidade,
                       op.valor_unitario, p.preco_custo, {$statusEstoque}, op.status = 'CONSUMIDO',
                       COALESCE(op.reservado_em, op.consumido_em, os.data_abertura)
                FROM {$O}.ordem_servico_produtos op
                JOIN {$O}.produtos p ON p.id_produto = op.produto_id
                JOIN {$O}.ordens_servico os ON os.id_ordem_servico = op.ordem_servico_id
                LEFT JOIN {$O}.categorias_produtos c ON c.id_categoria = p.categorias_produtos_id
                WHERE op.status <> 'CANCELADO'
                ORDER BY op.id_OS_produto");
    // Itens CANCELADOS da v1 (nunca entraram no total) ficam registrados no histórico, não como item.
    foreach ($q("SELECT * FROM {$O}.ordem_servico_produtos WHERE status = 'CANCELADO'") as $c) {
        $insHist->execute([$c['ordem_servico_id'], 'ITEM_CANCELADO_V1', null, null, 'Item cancelado na v1 (fora do total).',
            json_encode($c, JSON_UNESCAPED_UNICODE), $c['reservado_em'] ?? date('Y-m-d H:i:s')]);
    }

    $pdo->exec("INSERT INTO ordem_servico_itens (oficina_id, ordem_servico_id, tipo, origem, produto_id, descricao, categoria, quantidade,
                                                 valor_unitario, estoque_status, executado, criado_em)
                SELECT " . OFICINA_ID . ", s.ordem_servico_id, 'SERVICO', 'ORCAMENTO', NULL, s.nome, NULLIF(s.categoria,''), s.quantidade,
                       s.valor_unitario, 'NAO_SE_APLICA', s.executado, os.data_abertura
                FROM {$O}.ordem_servico_servicos s JOIN {$O}.ordens_servico os ON os.id_ordem_servico = s.ordem_servico_id
                ORDER BY s.id");

    // Mão de obra SEM técnico era cobrança ao cliente → vira serviço.
    $pdo->exec("INSERT INTO ordem_servico_itens (oficina_id, ordem_servico_id, tipo, origem, descricao, quantidade, valor_unitario, estoque_status, criado_em)
                SELECT " . OFICINA_ID . ", m.ordem_servico_id, 'SERVICO', 'ORCAMENTO', COALESCE(NULLIF(m.descricao,''), 'Mão de obra'), 1,
                       m.valor_mao_obra, 'NAO_SE_APLICA', os.data_abertura
                FROM {$O}.ordem_servico_mao_obra m JOIN {$O}.ordens_servico os ON os.id_ordem_servico = m.ordem_servico_id
                WHERE m.tecnico_id IS NULL");

    // Mão de obra COM técnico era a comissão → vai para o serviço de maior valor da OS (valor preservado).
    $comissoes = $q("SELECT * FROM {$O}.ordem_servico_mao_obra WHERE tecnico_id IS NOT NULL ORDER BY id_OS_mao");
    $updCom = $pdo->prepare("UPDATE ordem_servico_itens SET tecnico_id = ?, comissao_percentual = ?, comissao_valor = comissao_valor + ? WHERE id = ?");
    foreach ($comissoes as $c) {
        $alvo = $v("SELECT id FROM ordem_servico_itens WHERE ordem_servico_id = {$c['ordem_servico_id']} AND tipo = 'SERVICO'
                    ORDER BY valor_total DESC, id LIMIT 1")
             ?: $v("SELECT id FROM ordem_servico_itens WHERE ordem_servico_id = {$c['ordem_servico_id']} ORDER BY valor_total DESC, id LIMIT 1");
        if (!$alvo) {
            sair("comissão da OS {$c['ordem_servico_id']} sem item onde registrá-la.");
        }
        $pct = $c['regra_percentual'] ?? (preg_match('/(\d+(?:[.,]\d+)?)\s*%/', (string) $c['descricao'], $m) ? str_replace(',', '.', $m[1]) : 0);
        $updCom->execute([$c['tecnico_id'], $pct, $c['valor_mao_obra'], $alvo]);
    }

    // Totais das OS a partir dos itens (item DEVOLVIDO não entra no total, como na v1).
    $pdo->exec("UPDATE ordens_servico os JOIN (
                    SELECT ordem_servico_id,
                           SUM(IF(tipo='PECA',    ROUND(quantidade*valor_unitario,2), 0)) pecas,
                           SUM(IF(tipo='SERVICO', ROUND(quantidade*valor_unitario,2), 0)) servicos,
                           SUM(desconto) descontos, SUM(valor_total) total
                    FROM ordem_servico_itens WHERE estoque_status <> 'DEVOLVIDO' GROUP BY ordem_servico_id) t
                  ON t.ordem_servico_id = os.id
                SET os.valor_pecas = t.pecas, os.valor_servicos = t.servicos, os.valor_desconto = t.descontos, os.valor_total = t.total");

    // --- Caixas (sessões). Os 4 lançamentos de teste não são copiados. ---
    $pdo->exec("INSERT INTO caixas (id, oficina_id, status, aberto_por, aberto_em, valor_abertura, observacao_abertura,
                                    fechado_por, fechado_em, valor_esperado, valor_fechamento, observacao_fechamento)
                SELECT id_caixa, " . OFICINA_ID . ", status, usuario_abertura_id, data_abertura, valor_abertura, observacao_abertura,
                       usuario_fechamento_id, data_fechamento, valor_esperado, valor_fechamento, observacao_fechamento
                FROM {$O}.caixa");

    // --- Estoque: livro imutável. Movimentos da v1 + saídas por OS + saldo inicial que fecha com o estoque atual. ---
    $pdo->exec("INSERT INTO movimentacoes_estoque (oficina_id, produto_id, motivo, delta, custo_unitario, usuario_id, observacao, criado_em)
                SELECT " . OFICINA_ID . ", produto_id, 'AJUSTE',
                       COALESCE(delta, IF(tipo='SAIDA', -quantidade, quantidade)), custo_unitario, usuario_id,
                       LEFT(CONCAT('Movimento manual da v1 (', tipo, ')', COALESCE(CONCAT(': ', NULLIF(justificativa,'')), ''),
                                   COALESCE(CONCAT(' — ', NULLIF(observacao,'')), '')), 255),
                       criado_em
                FROM {$O}.movimentacoes_estoque
                WHERE COALESCE(delta, IF(tipo='SAIDA', -quantidade, quantidade)) <> 0
                ORDER BY id_movimentacao");
    $pdo->exec("INSERT INTO movimentacoes_estoque (oficina_id, produto_id, motivo, delta, custo_unitario, ordem_servico_item_id, observacao, criado_em)
                SELECT " . OFICINA_ID . ", i.produto_id, 'SAIDA_OS', -i.quantidade, i.custo_unitario, i.id,
                       'Baixa por OS (migrada da v1)', i.criado_em
                FROM ordem_servico_itens i WHERE i.tipo = 'PECA' AND i.estoque_status = 'BAIXADO' AND i.produto_id IS NOT NULL");
    $pdo->exec("INSERT INTO movimentacoes_estoque (oficina_id, produto_id, motivo, delta, observacao, criado_em)
                SELECT p.oficina_id, p.id, 'AJUSTE', p.estoque_atual - COALESCE(m.soma, 0),
                       'Saldo inicial da migração v1 → v2 (fecha o livro com o estoque da v1)',
                       COALESCE(LEAST(m.primeiro, p.criado_em), p.criado_em) - INTERVAL 1 SECOND
                FROM produtos p
                LEFT JOIN (SELECT produto_id, SUM(delta) soma, MIN(criado_em) primeiro FROM movimentacoes_estoque GROUP BY produto_id) m
                       ON m.produto_id = p.id
                WHERE p.tipo = 'PECA' AND p.estoque_atual - COALESCE(m.soma, 0) <> 0");

    // --- Notificações: só as de módulos que continuam existindo. ---
    $tiposRemovidos = "'SOLICITACAO_PECA','PEDIDO_COMPRA','COTACAO','CONVERSA','MENSAGEM','OCORRENCIA','PORTAL','COBRANCA'";
    $pdo->exec("INSERT INTO notificacoes (oficina_id, usuario_id, tipo, titulo, mensagem, link, chave_idempotencia, lida_em, criada_em)
                SELECT " . OFICINA_ID . ", usuario_id, tipo, titulo, LEFT(mensagem, 500), link, chave_idempotencia, lida_em, criada_em
                FROM {$O}.notificacoes WHERE tipo NOT IN ({$tiposRemovidos})");
    $notifDescartadas = (int) $v("SELECT COUNT(*) FROM {$O}.notificacoes WHERE tipo IN ({$tiposRemovidos})");

    // 4. Conferência ------------------------------------------------------------------
    $erros = [];
    $conferir = function (string $nome, $a, $b) use (&$erros) {
        $ok = (string) $a === (string) $b;
        linha(sprintf("  %-52s v1=%-14s v2=%-14s %s", $nome, $a, $b, $ok ? 'OK' : 'DIVERGENTE'));
        if (!$ok) {
            $erros[] = $nome;
        }
    };

    linha("\nConferência v1 x v2:");
    foreach (['usuarios' => 'usuarios', 'clientes' => 'clientes', 'veiculos' => 'veiculos', 'produtos' => 'produtos',
              'fornecedores' => 'fornecedores', 'modelos_veiculos' => 'modelos_veiculos', 'caixa' => 'caixas'] as $t1 => $t2) {
        $conferir("registros em {$t2}", $v("SELECT COUNT(*) FROM {$O}.`{$t1}`"), $v("SELECT COUNT(*) FROM {$t2}"));
    }
    $conferir('OS (v1) + orçamentos sem OS', (int) $v("SELECT COUNT(*) FROM {$O}.ordens_servico") + count($orcPorId), $v("SELECT COUNT(*) FROM ordens_servico"));
    $conferir('senhas idênticas', $v("SELECT COUNT(*) FROM {$O}.usuarios"),
        $v("SELECT COUNT(*) FROM usuarios u JOIN {$O}.usuarios o ON o.id_usuario = u.id AND BINARY o.senha = BINARY u.senha_hash"));

    foreach ($osV1 as $os) {
        $n = $os['numero'];
        $id = (int) $os['id_ordem_servico'];
        $novo = $q("SELECT valor_pecas, valor_servicos, valor_total FROM ordens_servico WHERE id = {$id}")[0];
        $conferir("{$n} valor total", $os['valor_total_os'], $novo['valor_total']);
        $conferir("{$n} peças", $os['valor_total_pecas'], $novo['valor_pecas']);
        $conferir("{$n} serviços", $os['valor_total_servicos'], $novo['valor_servicos']);
    }
    $conferir('soma das comissões', $v("SELECT COALESCE(SUM(valor_mao_obra),0) FROM {$O}.ordem_servico_mao_obra WHERE tecnico_id IS NOT NULL"),
        $v("SELECT COALESCE(SUM(comissao_valor),0) FROM ordem_servico_itens"));
    $conferir('estoque: produtos com saldo igual à v1', $v("SELECT COUNT(*) FROM {$O}.produtos"),
        $v("SELECT COUNT(*) FROM produtos p JOIN {$O}.produtos o ON o.id_produto = p.id AND o.estoque_atual = p.estoque_atual"));
    $conferir('estoque: produtos com livro = saldo', $v("SELECT COUNT(*) FROM produtos WHERE tipo='PECA'"),
        $v("SELECT COUNT(*) FROM produtos p WHERE p.tipo='PECA' AND p.estoque_atual =
             COALESCE((SELECT SUM(delta) FROM movimentacoes_estoque m WHERE m.produto_id = p.id), 0)"));
    $conferir('caixas: abertura/esperado/fechamento iguais', $v("SELECT COUNT(*) FROM {$O}.caixa"),
        $v("SELECT COUNT(*) FROM caixas c JOIN {$O}.caixa o ON o.id_caixa = c.id
             AND o.valor_abertura = c.valor_abertura AND o.valor_esperado <=> c.valor_esperado AND o.valor_fechamento <=> c.valor_fechamento
             AND o.status = c.status"));
    $conferir('caixas abertos', $v("SELECT COUNT(*) FROM {$O}.caixa WHERE status='ABERTO'"), $v("SELECT COUNT(*) FROM caixas WHERE status='ABERTO'"));
    $conferir('orçamentos preservados no histórico', $v("SELECT COUNT(*) FROM {$O}.orcamentos"),
        $v("SELECT COUNT(DISTINCT ordem_servico_id) FROM historico WHERE acao = 'ORCAMENTO_VERSAO'"));
    $conferir('notificações (migradas + descartadas)', $v("SELECT COUNT(*) FROM {$O}.notificacoes"),
        (int) $v("SELECT COUNT(*) FROM notificacoes") + $notifDescartadas);
    $conferir('registros fora da oficina inicial', 0, $v("SELECT
        (SELECT COUNT(*) FROM usuarios WHERE oficina_id <> 1) + (SELECT COUNT(*) FROM clientes WHERE oficina_id <> 1)
      + (SELECT COUNT(*) FROM ordens_servico WHERE oficina_id <> 1) + (SELECT COUNT(*) FROM produtos WHERE oficina_id <> 1)"));

    if ($erros) {
        throw new RuntimeException('conferência divergente: ' . implode('; ', $erros));
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sair("{$e->getMessage()}\nNada foi gravado em {$destino} (a estrutura vazia ficou; rode de novo com --recriar). A origem não foi alterada.");
}

linha("\nNão migrados por decisão aprovada:");
linha("  - " . count(CAIXA_MOV_DESCARTAR) . " lançamentos manuais de teste do caixa (ids " . implode(', ', CAIXA_MOV_DESCARTAR) . ")");
linha("  - " . $v("SELECT COUNT(*) FROM {$O}.password_resets") . " tokens antigos de recuperação de senha (as senhas foram copiadas sem alteração)");
linha("  - {$notifDescartadas} notificações de módulos removidos");
linha("  - " . $v("SELECT COUNT(*) FROM {$O}.subcategorias_produtos") . " subcategorias de produto (não usadas pelo sistema)");
linha("\nMigração concluída. {$origem} continua intacto; os dados novos estão em {$destino}.");
