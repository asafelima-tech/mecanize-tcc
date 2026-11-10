<?php

/** Envio e abertura de anexos. Fotos e documentos da OS: equipe; comprovantes de contas: ADMIN. */
final class AnexosController extends Controller
{
    public function enviarOs(): void
    {
        $osId = $this->idDaUrl('os');
        $arquivos = $this->arquivos('arquivos');
        try {
            if (!$arquivos) {
                throw new DomainException('Escolha ao menos um arquivo.');
            }
            $m = new Anexo();
            foreach ($arquivos as $a) {
                $m->anexarNaOs($osId, (string) ($_POST['categoria'] ?? ''), $this->post('descricao'), $a);
            }
            $this->avisar(count($arquivos) === 1 ? 'Arquivo anexado.' : count($arquivos) . ' arquivos anexados.');
        } catch (DomainException $e) {
            $this->avisar($e->getMessage(), 'erro');
        }
        $this->redirecionar('os', ['id' => $osId, 'aba' => 'arquivos']);
    }

    public function enviarConta(): void
    {
        $id = $this->idDaUrl('conta');
        try {
            (new Anexo())->anexarNaConta($id, $this->post('descricao'), $_FILES['arquivo'] ?? []);
            $this->avisar('Comprovante anexado.');
        } catch (DomainException $e) {
            $this->avisar($e->getMessage(), 'erro');
        }
        $this->redirecionar('conta', ['id' => $id]);
    }

    /** Abre o arquivo. Comprovante de conta só para o ADMIN (o financeiro é dele). */
    public function abrir(): void
    {
        $a = (new Anexo())->buscar($this->idDaUrl()) ?? throw new ErroHttp('registro-inexistente');
        if ($a['lancamento_id'] && Contexto::perfil() !== 'ADMIN') {
            throw new ErroHttp('sem-permissao');
        }
        $arquivo = Armazenamento::caminho($a['caminho']) ?? throw new ErroHttp('registro-inexistente');
        Arquivo::enviar($arquivo, $a['mime'], $a['nome_original'], ($_GET['baixar'] ?? '') === '1');
    }

    /** $_FILES de um input "multiple" em uma lista de arquivos (ignora campos vazios). */
    private function arquivos(string $campo): array
    {
        $f = $_FILES[$campo] ?? null;
        if (!$f || !is_array($f['name'])) {
            return $f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? [$f] : [];
        }
        $lista = [];
        foreach ($f['name'] as $i => $nome) {
            if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $lista[] = ['name' => $nome, 'tmp_name' => $f['tmp_name'][$i], 'size' => $f['size'][$i], 'error' => $f['error'][$i]];
        }

        return array_slice($lista, 0, 10);
    }
}
