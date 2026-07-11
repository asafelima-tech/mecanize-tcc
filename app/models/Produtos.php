<?php

class Produtos
{
    public $conn;
    private $lastError = '';

    public function getLastError()
    {
        return $this->lastError;
    }

    public function __construct()
    {
        $this->conn = new Database();
    }

    public function getResumo($filtros = [])
    {
        return [
            'totalEstoque' => $this->totalEstoque(),
            'estoqueBaixo' => $this->estoqueBaixo(),
            'valorInventario' => $this->valorInventario(),
            'produtos' => $this->listarProdutos($filtros),
            'categorias' => $this->getCategorias()
        ];
    }

    private function totalEstoque()
    {
        $sql = "SELECT COALESCE(SUM(estoque_atual), 0)
                FROM produtos
                WHERE ativo = 1";

        return (float) $this->conn->query($sql)->fetchColumn();
    }

    private function estoqueBaixo()
    {
        $sql = "
            SELECT COUNT(*)
            FROM produtos
            WHERE ativo = 1
            AND estoque_atual > 0
            AND estoque_atual <= estoque_minimo
        ";

        return (int) $this->conn->query($sql)->fetchColumn();
    }

    private function valorInventario()
    {
        $sql = "
            SELECT COALESCE(SUM(estoque_atual * preco_custo), 0)
            FROM produtos
            WHERE ativo = 1
        ";

        return (float) $this->conn->query($sql)->fetchColumn();
    }

    public function getCategorias()
    {
        $sql = "SELECT id_categoria, nome
                FROM categorias_produtos
                ORDER BY nome ASC";

        $stmt = $this->conn->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

  public function listarProdutos($filtros = [])
{
    $busca     = trim($filtros['busca'] ?? '');
    $categoria = $filtros['categoria'] ?? '';
    $status    = $filtros['status'] ?? '';
    $aplicacao = trim($filtros['aplicacao'] ?? '');

    $sql = "
        SELECT
            p.id_produto,
            p.categorias_produtos_id,
            p.codigo_interno,
            p.codigo_barras,
            p.nome,
            p.marca,
            p.descricao,
            p.aplicacao,
            p.volume_embalagem,
            p.estoque_atual,
            p.estoque_minimo,
            p.preco_custo,
            p.preco_venda,
            p.ativo,
            p.localizacao,
            p.criado_em,
            p.atualizado_em,
            cp.nome AS categoria
        FROM produtos p
        LEFT JOIN categorias_produtos cp
            ON cp.id_categoria = p.categorias_produtos_id
        WHERE 1 = 1
    ";

    $params = [];

    if ($busca !== '') {
        $sql .= " AND (
            p.nome LIKE ?
            OR p.codigo_interno LIKE ?
            OR p.codigo_barras LIKE ?
            OR p.marca LIKE ?
            OR p.localizacao LIKE ?
            OR cp.nome LIKE ?
        )";

        $termo = '%' . $busca . '%';

        $params[] = $termo;
        $params[] = $termo;
        $params[] = $termo;
        $params[] = $termo;
        $params[] = $termo;
        $params[] = $termo;
    }

    // Busca independente por aplicação (texto livre em produtos.aplicacao)
    if ($aplicacao !== '') {
        $sql .= " AND p.aplicacao LIKE ?";
        $params[] = '%' . $aplicacao . '%';
    }

    if ($categoria !== '') {
        $sql .= " AND p.categorias_produtos_id = ?";
        $params[] = $categoria;
    }

    switch ($status) {
        case 'ativo':
            $sql .= " AND p.ativo = 1";
            break;

        case 'inativo':
            $sql .= " AND p.ativo = 0";
            break;

        case 'em_estoque':
            $sql .= " AND p.ativo = 1
                      AND p.estoque_atual > p.estoque_minimo";
            break;

        case 'baixo_estoque':
            $sql .= " AND p.ativo = 1
                      AND p.estoque_atual > 0
                      AND p.estoque_atual <= p.estoque_minimo";
            break;

        case 'esgotado':
            $sql .= " AND p.ativo = 1
                      AND p.estoque_atual <= 0";
            break;

        default:
            // Por padrão, mostra somente produtos ativos
            $sql .= " AND p.ativo = 1";
            break;
    }

    $sql .= " ORDER BY p.nome ASC";

    $stmt = $this->conn->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    public function getById($id)
    {
        $sql = "SELECT *
                FROM produtos
                WHERE id_produto = ?
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

 public function create($data)
{
    $this->lastError = '';

    try {
        if (empty($data['categorias_produtos_id'])) {
            $this->lastError = 'Categoria não informada.';
            return false;
        }

        if (empty(trim($data['nome'] ?? ''))) {
            $this->lastError = 'Nome do produto não informado.';
            return false;
        }

        if (empty(trim($data['localizacao'] ?? ''))) {
            $this->lastError = 'Localização não informada.';
            return false;
        }

        $codigoTemporario = 'TMP-' . uniqid();

        $sql = "INSERT INTO produtos (
            categorias_produtos_id,
            codigo_interno,
            codigo_barras,
            nome,
            marca,
            descricao,
            aplicacao,
            volume_embalagem,
            estoque_atual,
            estoque_minimo,
            preco_custo,
            preco_venda,
            ativo,
            localizacao
        ) VALUES (
            :categorias_produtos_id,
            :codigo_interno,
            :codigo_barras,
            :nome,
            :marca,
            :descricao,
            :aplicacao,
            :volume_embalagem,
            :estoque_atual,
            :estoque_minimo,
            :preco_custo,
            :preco_venda,
            :ativo,
            :localizacao
        )";

        $stmt = $this->conn->prepare($sql);

        $sucesso = $stmt->execute([
            ':categorias_produtos_id' => (int) $data['categorias_produtos_id'],
            ':codigo_interno'         => $codigoTemporario,
            ':codigo_barras'          => !empty($data['codigo_barras']) ? trim($data['codigo_barras']) : null,
            ':nome'                   => trim($data['nome']),
            ':marca'                  => !empty($data['marca']) ? trim($data['marca']) : null,
            ':descricao'              => !empty($data['descricao']) ? trim($data['descricao']) : null,
            ':aplicacao'              => !empty($data['aplicacao']) ? trim($data['aplicacao']) : null,
            ':volume_embalagem'       => !empty($data['volume_embalagem']) ? trim($data['volume_embalagem']) : null,
            ':estoque_atual'          => isset($data['estoque_atual']) && $data['estoque_atual'] !== '' ? str_replace(',', '.', $data['estoque_atual']) : 0,
            ':estoque_minimo'         => isset($data['estoque_minimo']) && $data['estoque_minimo'] !== '' ? str_replace(',', '.', $data['estoque_minimo']) : 0,
            ':preco_custo'            => isset($data['preco_custo']) && $data['preco_custo'] !== '' ? str_replace(',', '.', $data['preco_custo']) : 0,
            ':preco_venda'            => isset($data['preco_venda']) && $data['preco_venda'] !== '' ? str_replace(',', '.', $data['preco_venda']) : 0,
            ':ativo'                  => isset($data['ativo']) ? (int) $data['ativo'] : 1,
            ':localizacao'            => trim($data['localizacao'])
        ]);

        if (!$sucesso) {
            $erro = $stmt->errorInfo();
            $this->lastError = $erro[2] ?? 'Falha ao executar INSERT.';
            return false;
        }

        $idProduto = $this->conn->lastInsertId();

        if (!$idProduto) {
            $this->lastError = 'Produto inserido, mas não foi possível obter o ID gerado.';
            return false;
        }

        $codigoInterno = $this->gerarCodigoInterno($idProduto);

        $sqlCodigo = "UPDATE produtos
                      SET codigo_interno = :codigo_interno
                      WHERE id_produto = :id_produto";

        $stmtCodigo = $this->conn->prepare($sqlCodigo);

        $sucessoCodigo = $stmtCodigo->execute([
            ':codigo_interno' => $codigoInterno,
            ':id_produto'    => $idProduto
        ]);

        if (!$sucessoCodigo) {
            $erro = $stmtCodigo->errorInfo();
            $this->lastError = $erro[2] ?? 'Produto criado, mas erro ao atualizar código interno.';
            return false;
        }

        return true;

    } catch (Throwable $e) {
        $this->lastError = $e->getMessage();
        return false;
    }
}

   public function update($id, $data)
{
    try {
        // Estoque antes da edição — pra registrar um AJUSTE de auditoria se
        // essa tela mudar o valor diretamente (sem isso, uma correção de
        // estoque feita aqui era invisível no histórico de movimentações,
        // impossível de reconciliar depois com a soma dos registros).
        $stmtAntes = $this->conn->prepare("SELECT estoque_atual, preco_custo FROM produtos WHERE id_produto = ?");
        $stmtAntes->execute([(int) $id]);
        $antes = $stmtAntes->fetch(PDO::FETCH_ASSOC);

        $novoEstoque = isset($data['estoque_atual']) && $data['estoque_atual'] !== ''
            ? (float) str_replace(',', '.', $data['estoque_atual'])
            : 0.0;
        $novoCusto = isset($data['preco_custo']) && $data['preco_custo'] !== ''
            ? (float) str_replace(',', '.', $data['preco_custo'])
            : 0.0;

        $sql = "UPDATE produtos SET
            categorias_produtos_id = :categorias_produtos_id,
            codigo_barras = :codigo_barras,
            nome = :nome,
            marca = :marca,
            descricao = :descricao,
            aplicacao = :aplicacao,
            volume_embalagem = :volume_embalagem,
            estoque_atual = :estoque_atual,
            estoque_minimo = :estoque_minimo,
            preco_custo = :preco_custo,
            preco_venda = :preco_venda,
            ativo = :ativo,
            localizacao = :localizacao,
            atualizado_em = NOW()
        WHERE id_produto = :id_produto";

        $stmt = $this->conn->prepare($sql);

        $ok = $stmt->execute([
            ':categorias_produtos_id' => !empty($data['categorias_produtos_id']) ? (int) $data['categorias_produtos_id'] : null,
            ':codigo_barras'          => !empty($data['codigo_barras']) ? trim($data['codigo_barras']) : null,
            ':nome'                   => trim($data['nome'] ?? ''),
            ':marca'                  => !empty($data['marca']) ? trim($data['marca']) : null,
            ':descricao'              => !empty($data['descricao']) ? trim($data['descricao']) : null,
            ':aplicacao'              => !empty($data['aplicacao']) ? trim($data['aplicacao']) : null,
            ':volume_embalagem'       => !empty($data['volume_embalagem']) ? trim($data['volume_embalagem']) : null,
            ':estoque_atual'          => $novoEstoque,
            ':estoque_minimo'         => isset($data['estoque_minimo']) && $data['estoque_minimo'] !== '' ? str_replace(',', '.', $data['estoque_minimo']) : 0,
            ':preco_custo'            => $novoCusto,
            ':preco_venda'            => isset($data['preco_venda']) && $data['preco_venda'] !== '' ? str_replace(',', '.', $data['preco_venda']) : 0,
            ':ativo'                  => isset($data['ativo']) ? (int) $data['ativo'] : 1,
            ':localizacao'            => !empty($data['localizacao']) ? trim($data['localizacao']) : null,
            ':id_produto'             => (int) $id
        ]);

        // Registra o AJUSTE só se o estoque realmente mudou por esta tela —
        // quantidade guardada com sinal (positivo = subiu, negativo = desceu)
        // porque 'AJUSTE' não segue a convenção +/- de ENTRADA/SAIDA (nada
        // mais soma/interpreta essas linhas pra recalcular estoque; são só
        // um registro de auditoria pra humano ler depois).
        if ($ok && $antes) {
            $diferenca = round($novoEstoque - (float) $antes['estoque_atual'], 3);

            if ($diferenca !== 0.0) {
                $this->conn->prepare("
                    INSERT INTO movimentacoes_estoque (produto_id, tipo, quantidade, custo_unitario)
                    VALUES (?, 'AJUSTE', ?, ?)
                ")->execute([(int) $id, $diferenca, $novoCusto]);
            }
        }

        return $ok;

    } catch (Throwable $e) {
        $this->lastError = $e->getMessage();
        return false;
    }
}

    public function arquiva($id)
    {
        $sql = "UPDATE produtos
            SET ativo = 0,
                atualizado_em = NOW()
            WHERE id_produto = ?";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([$id]);
    }

 private function gerarCodigoInterno($id)
{
    return 'PRD-' . str_pad($id, 4, '0', STR_PAD_LEFT);
}

public function getProximoCodigoInterno()
{
    try {
        $sql = "SELECT COALESCE(MAX(id_produto), 0) + 1 AS proximo_id
                FROM produtos";

        $stmt = $this->conn->query($sql);
        $proximoId = (int) $stmt->fetchColumn();

        if ($proximoId <= 0) {
            $proximoId = 1;
        }

        return $this->gerarCodigoInterno($proximoId);

    } catch (Exception $e) {
        return 'Gerado automaticamente';
    }
}
}
