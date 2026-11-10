<?php

/**
 * Arquivos da OS (fotos e documentos) e das contas (comprovantes).
 *
 * É REGISTRO, como na v1: não se edita nem se apaga. Cada envio deixa uma linha no histórico do
 * dono. O arquivo fica em storage/oficinas/{oficina}/ (fora da pasta pública) e só sai pela rota
 * "anexo", que confere a oficina e o perfil de quem pede.
 */
final class Anexo extends Modelo
{
    public const CATEGORIAS_OS = [
        'FOTO_ANTES'   => 'Foto · antes',
        'FOTO_DURANTE' => 'Foto · durante',
        'FOTO_DEPOIS'  => 'Foto · depois',
        'PROBLEMA'     => 'Problema encontrado',
        'DOCUMENTO'    => 'Documento',
    ];

    /** Teto por OS (v1): uma OS não vira depósito de arquivos. */
    public const MAX_POR_DONO = 40;

    private const SO_IMAGEM = ['image/jpeg', 'image/png', 'image/webp'];

    public function daOs(int $osId): array
    {
        return $this->lista(
            'SELECT a.*, u.nome AS autor FROM anexos a LEFT JOIN usuarios u ON u.oficina_id = a.oficina_id AND u.id = a.enviado_por
             WHERE a.oficina_id = :oficina AND a.ordem_servico_id = :os ORDER BY a.enviado_em, a.id', ['os' => $osId]
        );
    }

    public function daConta(int $lancamentoId): array
    {
        return $this->lista(
            'SELECT a.*, u.nome AS autor FROM anexos a LEFT JOIN usuarios u ON u.oficina_id = a.oficina_id AND u.id = a.enviado_por
             WHERE a.oficina_id = :oficina AND a.lancamento_id = :l ORDER BY a.enviado_em, a.id', ['l' => $lancamentoId]
        );
    }

    public function buscar(int $id): ?array
    {
        return $this->linha('SELECT * FROM anexos WHERE id = :id AND oficina_id = :oficina', ['id' => $id]);
    }

    /** @param array $arquivo item de $_FILES */
    public function anexarNaOs(int $osId, string $categoria, ?string $descricao, array $arquivo, bool $exigirUpload = true): int
    {
        if (!isset(self::CATEGORIAS_OS[$categoria])) {
            throw new DomainException('Escolha o tipo do arquivo.');
        }
        $status = $this->valor('SELECT status FROM ordens_servico WHERE id = :id AND oficina_id = :oficina', ['id' => $osId]);
        if ($status === false) {
            throw new ErroHttp('registro-inexistente', ['voltar' => './ordens']);
        }
        if ($status === 'CANCELADA') {
            throw new DomainException('A OS foi cancelada: não recebe mais arquivos.');
        }

        // Fotos só imagem; "documento" aceita PDF também.
        return $this->anexar(['ordem_servico_id' => $osId], $categoria, $descricao, $arquivo, $exigirUpload,
            $categoria === 'DOCUMENTO' ? [] : self::SO_IMAGEM, 'os');
    }

    public function anexarNaConta(int $lancamentoId, ?string $descricao, array $arquivo, bool $exigirUpload = true): int
    {
        if (!$this->valor('SELECT COUNT(*) FROM lancamentos WHERE id = :id AND oficina_id = :oficina', ['id' => $lancamentoId])) {
            throw new ErroHttp('registro-inexistente', ['voltar' => './financeiro']);
        }

        return $this->anexar(['lancamento_id' => $lancamentoId], 'COMPROVANTE', $descricao, $arquivo, $exigirUpload, [], 'comprovantes');
    }

    private function anexar(array $dono, string $categoria, ?string $descricao, array $arquivo, bool $exigirUpload, array $mimes, string $pasta): int
    {
        Contexto::exigirEscrita();
        $col = array_key_first($dono);
        if ((int) $this->valor("SELECT COUNT(*) FROM anexos WHERE oficina_id = :oficina AND {$col} = :dono", ['dono' => $dono[$col]]) >= self::MAX_POR_DONO) {
            throw new DomainException('Este registro já tem ' . self::MAX_POR_DONO . ' arquivos.');
        }
        $descricao = $descricao !== null ? trim(preg_replace('/[\x00-\x1f\x7f]+/u', ' ', $descricao)) : '';

        // O arquivo é gravado antes da linha; se a linha falhar, o arquivo é apagado.
        $meta = Armazenamento::guardar($arquivo, $pasta, $exigirUpload, $mimes);
        try {
            return Transacao::executar($this->db, function () use ($dono, $categoria, $descricao, $meta) {
                $id = $this->inserir('anexos', $dono + [
                    'categoria'     => $categoria,
                    'descricao'     => $descricao !== '' ? mb_substr($descricao, 0, 200) : null,
                    'nome_original' => $meta['nome_original'],
                    'caminho'       => $meta['caminho'],
                    'mime'          => $meta['mime'],
                    'tamanho'       => $meta['tamanho'],
                    'enviado_por'   => Contexto::usuarioId(),
                ]);
                (new Historico($this->db))->registrar($dono, 'ANEXO', null, null, $meta['nome_original'],
                    ['anexo_id' => $id, 'categoria' => $categoria]);

                return $id;
            });
        } catch (Throwable $e) {
            if ($f = Armazenamento::caminho($meta['caminho'])) {
                @unlink($f);
            }
            throw $e;
        }
    }

    public static function ehImagem(array $a): bool
    {
        return str_starts_with((string) $a['mime'], 'image/');
    }
}
