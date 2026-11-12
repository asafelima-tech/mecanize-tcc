<?php

require_once __DIR__ . '/../core/Armazenamento.php';
require_once __DIR__ . '/../core/Historico.php';
require_once __DIR__ . '/../core/Transacao.php';

/**
 * Evidências fotográficas da OS — o que o técnico encontrou e o que fez (antes, durante, depois, problema).
 *
 * É REGISTRO: não se edita nem se apaga. A foto fica em storage/evidencias/ (fora da área pública) com nome
 * aleatório; o tipo vem do CONTEÚDO do arquivo (só imagem é aceita aqui). Cada foto deixa uma linha na trilha
 * de auditoria da OS (quem, quando, o quê). Equipe inteira pode anexar e ver (técnico = atendente).
 */
class EvidenciaOs
{
    public const TIPOS = ['ANTES' => 'Antes', 'DURANTE' => 'Durante', 'DEPOIS' => 'Depois', 'PROBLEMA' => 'Problema encontrado'];

    /** Teto por OS: uma OS não vira depósito de arquivos. */
    public const MAX_POR_OS = 40;

    private const MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    /**
     * @param array{name:string, tmp_name:string, size:int, error:int} $arquivo um item de $_FILES
     * @return array{ok:bool, erro:?string, id:?int}
     */
    public function anexar(int $osId, string $tipo, ?string $descricao, array $arquivo, ?int $usuarioId, bool $exigirUpload = true): array
    {
        try {
            if (!isset(self::TIPOS[$tipo])) {
                throw new DomainException('Escolha o tipo da foto (antes, durante, depois ou problema encontrado).');
            }

            $descricao = $descricao !== null ? trim(preg_replace('/[\x00-\x1f\x7f]+/u', ' ', $descricao)) : null;

            if ($descricao !== null && mb_strlen($descricao) > 255) {
                throw new DomainException('A descrição passa de 255 caracteres.');
            }

            $os = $this->conn->prepare('SELECT status FROM ordens_servico WHERE id_ordem_servico = ?');
            $os->execute([$osId]);
            $status = $os->fetchColumn();

            if ($status === false) {
                throw new DomainException('Ordem de serviço não encontrada.');
            }

            if ($status === 'CANCELADA') {
                throw new DomainException('A OS foi cancelada: não recebe mais fotos.');
            }

            $total = $this->conn->prepare('SELECT COUNT(*) FROM evidencias_os WHERE ordem_servico_id = ?');
            $total->execute([$osId]);

            if ((int) $total->fetchColumn() >= self::MAX_POR_OS) {
                throw new DomainException('Esta OS já tem ' . self::MAX_POR_OS . ' fotos. Fale com o administrador.');
            }

            // Só imagem. O arquivo é gravado antes da linha; se a linha falhar, o arquivo é removido logo abaixo.
            $meta = Armazenamento::guardar($arquivo, 'evidencias', $exigirUpload, self::MIMES);

            try {
                $idEvidencia = Transacao::executar($this->conn, function () use ($osId, $tipo, $descricao, $meta, $usuarioId, $status): int {
                    $this->conn->prepare('INSERT INTO evidencias_os (ordem_servico_id, tipo, descricao, nome_original, caminho, mime, tamanho, enviado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                        ->execute([$osId, $tipo, $descricao !== '' ? $descricao : null, $meta['nome_original'], $meta['caminho'], $meta['mime'], $meta['tamanho'], $usuarioId]);
                    // o id da foto tem de ser lido AGORA: o registro na trilha logo abaixo é outro INSERT e mudaria o lastInsertId
                    $id = (int) $this->conn->lastInsertId();

                    Historico::registrar(
                        $this->conn, 'OS', $osId, (string) $status, (string) $status, $usuarioId,
                        'Foto de evidência anexada (' . mb_strtolower(self::TIPOS[$tipo]) . ')' . ($descricao ? ': ' . mb_substr($descricao, 0, 120) : ''),
                        Historico::ORIGEM_USUARIO
                    );

                    return $id;
                });
            } catch (\Throwable $e) {
                $absoluto = Armazenamento::caminho($meta['caminho']);

                if ($absoluto) {
                    @unlink($absoluto);
                }

                throw $e;
            }

            return ['ok' => true, 'erro' => null, 'id' => $idEvidencia];
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'id' => null];
        } catch (\Throwable $e) {
            error_log('[EvidenciaOs anexar] ' . $e->getMessage());

            return ['ok' => false, 'erro' => 'Não foi possível guardar a foto. Tente novamente.', 'id' => null];
        }
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM evidencias_os WHERE id_evidencia = ?');
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return array<int,array<string,mixed>> da mais recente para a mais antiga */
    public function daOs(int $osId): array
    {
        $stmt = $this->conn->prepare("
            SELECT e.id_evidencia, e.tipo, e.descricao, e.nome_original, e.mime, e.tamanho, e.enviado_em, u.nome AS enviado_por_nome
            FROM evidencias_os e LEFT JOIN usuarios u ON u.id_usuario = e.enviado_por
            WHERE e.ordem_servico_id = ? ORDER BY e.id_evidencia DESC
        ");
        $stmt->execute([$osId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
