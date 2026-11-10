<?php

/**
 * Guarda arquivos enviados (fotos de OS, comprovantes) FORA da pasta pública, SEPARADOS POR OFICINA:
 * storage/oficinas/{oficina_id}/{pasta}/AAAA/MM/arquivo. O caminho gravado no banco é relativo à pasta
 * da oficina; caminho() só resolve dentro da pasta da oficina logada — um caminho de outra oficina
 * (ou com ../) simplesmente não é encontrado.
 *
 *
 * Os arquivos ficam em storage/ na raiz do projeto — que o Apache nega por padrão (ver o
 * .htaccess da raiz) — e só saem por uma rota autorizada que confere o usuário. O nome gravado é
 * aleatório (o original só é exibido), e o tipo é decidido pelo CONTEÚDO (finfo), nunca pelo nome
 * nem pelo que o navegador declarou: um .php renomeado para .png é recusado.
 */
final class Armazenamento
{
    public const LIMITE_BYTES = 5 * 1024 * 1024;

    /** mime => extensão */
    public const TIPOS = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
    ];

    public static function raiz(): string
    {
        return PROJETO_RAIZ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'oficinas'
            . DIRECTORY_SEPARATOR . Contexto::oficinaId();
    }

    /**
     * Valida e grava o arquivo. Devolve os metadados para o banco; lança DomainException com a
     * mensagem para a tela quando algo está errado.
     *
     * @param array{name:string, tmp_name:string, size:int, error:int} $arquivo um item de $_FILES
     * @param string[] $apenasMimes restringe ainda mais os tipos aceitos (vazio = todos os de TIPOS)
     * @return array{nome_original:string, caminho:string, mime:string, tamanho:int}
     */
    public static function guardar(array $arquivo, string $pasta = 'comprovantes', bool $exigirUpload = true, array $apenasMimes = []): array
    {
        $erro = (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($erro === UPLOAD_ERR_NO_FILE) {
            throw new DomainException('Escolha o arquivo.');
        }

        if ($erro === UPLOAD_ERR_INI_SIZE || $erro === UPLOAD_ERR_FORM_SIZE) {
            throw new DomainException('O arquivo é maior que o permitido (5 MB).');
        }

        if ($erro !== UPLOAD_ERR_OK) {
            throw new DomainException('Não foi possível receber o arquivo. Tente novamente.');
        }

        $tmp = (string) ($arquivo['tmp_name'] ?? '');

        if ($exigirUpload && !is_uploaded_file($tmp)) {
            throw new DomainException('Envio inválido.');
        }

        $tamanho = (int) filesize($tmp);

        if ($tamanho <= 0) {
            throw new DomainException('O arquivo está vazio.');
        }

        if ($tamanho > self::LIMITE_BYTES) {
            throw new DomainException('O arquivo é maior que o permitido (5 MB).');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';

        if (!isset(self::TIPOS[$mime])) {
            throw new DomainException('Tipo de arquivo não aceito. Envie PDF, JPG, PNG ou WEBP.');
        }

        if ($apenasMimes && !in_array($mime, $apenasMimes, true)) {
            throw new DomainException('Tipo de arquivo não aceito aqui. Envie uma foto (JPG, PNG ou WEBP).');
        }

        $pasta = preg_replace('/[^a-z0-9_\-]/i', '', $pasta) ?: 'arquivos';
        $relativo = $pasta . '/' . date('Y') . '/' . date('m') . '/' . bin2hex(random_bytes(16)) . '.' . self::TIPOS[$mime];
        $absoluto = self::raiz() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativo);

        if (!is_dir(dirname($absoluto)) && !mkdir(dirname($absoluto), 0750, true) && !is_dir(dirname($absoluto))) {
            throw new DomainException('Não foi possível preparar o armazenamento.');
        }

        $movido = $exigirUpload ? move_uploaded_file($tmp, $absoluto) : copy($tmp, $absoluto);

        if (!$movido) {
            throw new DomainException('Não foi possível gravar o arquivo.');
        }

        // Nome original só para exibir: sem caminho, sem caracteres de controle.
        $nome = preg_replace('/[\x00-\x1f\x7f]+/u', '', basename(str_replace('\\', '/', (string) ($arquivo['name'] ?? 'arquivo'))));

        return ['nome_original' => mb_substr($nome !== '' ? $nome : 'arquivo', 0, 150), 'caminho' => $relativo, 'mime' => $mime, 'tamanho' => $tamanho];
    }

    /** Caminho absoluto de um arquivo guardado, ou null se não existir ou tentar sair de storage/. */
    public static function caminho(string $relativo): ?string
    {
        $raiz = realpath(self::raiz());
        $alvo = realpath(self::raiz() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativo));

        if ($raiz === false || $alvo === false || !str_starts_with($alvo, $raiz . DIRECTORY_SEPARATOR) || !is_file($alvo)) {
            return null;
        }

        return $alvo;
    }
}
