<?php

require_once __DIR__ . '/../core/Validator.php';

/**
 * Fornecedores de quem a oficina compra para o próprio abastecimento.
 *
 * O Mecanize NÃO vende peças: este cadastro só alimenta cotações e pedidos de compra.
 * Fornecedor com histórico não se apaga — desativa-se (some das escolhas, fica no histórico).
 *
 * `site_url` abre o site oficial do fornecedor em nova aba. É um atalho, não uma integração:
 * onde o fornecedor não oferece API de compra, o pedido é feito no site dele e registrado
 * aqui à mão (número do pedido em `referencia_externa`).
 */
class Fornecedor
{
    private PDO $conn;
    private string $ultimoErro = '';

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    public function getLastError(): string
    {
        return $this->ultimoErro;
    }

    public function listar(bool $incluirInativos = false, string $busca = ''): array
    {
        $where  = $incluirInativos ? '1=1' : 'f.ativo = 1';
        $params = [];

        if (trim($busca) !== '') {
            $where .= ' AND (f.nome LIKE ? OR f.documento LIKE ? OR f.contato LIKE ?)';
            $t = '%' . trim($busca) . '%';
            $params = [$t, $t, $t];
        }

        $stmt = $this->conn->prepare("
            SELECT f.*,
                   (SELECT COUNT(*) FROM pedidos_compra pc WHERE pc.fornecedor_id = f.id_fornecedor AND pc.status <> 'CANCELADO') AS total_pedidos
            FROM fornecedores f WHERE {$where} ORDER BY f.ativo DESC, f.nome
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM fornecedores WHERE id_fornecedor = ?');
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return int|false id criado */
    public function create(array $d)
    {
        $campos = $this->validar($d, null);

        if ($campos === null) {
            return false;
        }

        $this->conn->prepare("
            INSERT INTO fornecedores
                (nome, documento, documento_normalizado, contato, telefone, email, site_url, condicoes_pagamento, prazo_medio_dias, observacoes)
            VALUES (:nome, :documento, :doc_norm, :contato, :telefone, :email, :site_url, :condicoes, :prazo, :obs)
        ")->execute($campos);

        return (int) $this->conn->lastInsertId();
    }

    public function update(int $id, array $d): bool
    {
        $campos = $this->validar($d, $id);

        if ($campos === null) {
            return false;
        }

        $campos[':id'] = $id;

        $this->conn->prepare("
            UPDATE fornecedores SET
                nome = :nome, documento = :documento, documento_normalizado = :doc_norm, contato = :contato,
                telefone = :telefone, email = :email, site_url = :site_url, condicoes_pagamento = :condicoes,
                prazo_medio_dias = :prazo, observacoes = :obs
            WHERE id_fornecedor = :id
        ")->execute($campos);

        return true;
    }

    public function definirAtivo(int $id, bool $ativo): bool
    {
        return $this->conn->prepare('UPDATE fornecedores SET ativo = ? WHERE id_fornecedor = ?')->execute([$ativo ? 1 : 0, $id]);
    }

    /** @return array<string,mixed>|null parâmetros prontos, ou null com $ultimoErro preenchido */
    private function validar(array $d, ?int $idAtual): ?array
    {
        $this->ultimoErro = '';

        $nome = trim((string) ($d['nome'] ?? ''));
        if (mb_strlen($nome) < 2) {
            $this->ultimoErro = 'Informe o nome do fornecedor.';
            return null;
        }

        $documento = trim((string) ($d['documento'] ?? ''));
        $email     = trim((string) ($d['email'] ?? ''));

        $validador = (new Validator(['documento' => $documento, 'email' => $email]))
            ->cpfCnpj('documento', 'CNPJ/CPF')
            ->email('email', 'E-mail');

        if ($validador->fails()) {
            $this->ultimoErro = (string) $validador->firstError();
            return null;
        }

        $docNorm = preg_replace('/\D+/', '', $documento);
        $docNorm = $docNorm !== '' ? $docNorm : null;

        if ($docNorm !== null) {
            $dup = $this->conn->prepare('SELECT nome FROM fornecedores WHERE documento_normalizado = ? AND id_fornecedor <> ?');
            $dup->execute([$docNorm, $idAtual ?? 0]);

            if ($nomeDup = $dup->fetchColumn()) {
                $this->ultimoErro = 'Já existe um fornecedor com este documento: ' . $nomeDup . '.';
                return null;
            }
        }

        $site = trim((string) ($d['site_url'] ?? ''));
        if ($site !== '') {
            if (!preg_match('#^https?://#i', $site)) {
                $site = 'https://' . $site;
            }

            // O link é aberto pela equipe: só http(s), nunca javascript: ou data:.
            if (!filter_var($site, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $site)) {
                $this->ultimoErro = 'O endereço do site não parece válido.';
                return null;
            }
        }

        $prazo = trim((string) ($d['prazo_medio_dias'] ?? ''));

        return [
            ':nome'      => mb_substr($nome, 0, 150),
            ':documento' => $documento !== '' ? mb_substr($documento, 0, 20) : null,
            ':doc_norm'  => $docNorm,
            ':contato'   => trim((string) ($d['contato'] ?? '')) ?: null,
            ':telefone'  => trim((string) ($d['telefone'] ?? '')) ?: null,
            ':email'     => $email !== '' ? mb_substr($email, 0, 150) : null,
            ':site_url'  => $site !== '' ? mb_substr($site, 0, 255) : null,
            ':condicoes' => trim((string) ($d['condicoes_pagamento'] ?? '')) ?: null,
            ':prazo'     => $prazo !== '' && ctype_digit($prazo) ? (int) $prazo : null,
            ':obs'       => trim((string) ($d['observacoes'] ?? '')) ?: null,
        ];
    }
}
