<?php

/** Dados da oficina (ADMIN). A licença aparece aqui só para consulta: quem muda é a plataforma. */
final class ConfiguracoesController extends Controller
{
    public function form(): void
    {
        $this->tela('configuracoes/form', ['of' => (new Oficina())->atual(), 'historico' => (new Oficina())->historico(), 'vagas' => (new Usuario())->vagas()]);
    }

    public function salvar(): void
    {
        $m = new Oficina();
        try {
            $m->salvarConfiguracoes($_POST);
            if (($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $m->trocarLogo($_FILES['logo']);
            } elseif (($_POST['remover_logo'] ?? '') === '1') {
                $m->removerLogo();
            }
        } catch (DomainException $e) {
            $this->tela('configuracoes/form', ['of' => $_POST + ($m->atual() ?? []), 'historico' => $m->historico(), 'vagas' => (new Usuario())->vagas(), 'erro' => $e->getMessage()]);
            return;
        }
        $this->avisar('Configurações salvas.');
        $this->redirecionar('configuracoes');
    }

    /** Logotipo da oficina logada (usado na impressão e nas configurações). */
    public function logo(): void
    {
        $c = (new Oficina())->atual()['logo_caminho'] ?? null;
        $arquivo = $c ? Armazenamento::caminho($c) : null;
        if (!$arquivo) {
            http_response_code(404);
            exit;
        }
        Arquivo::enviar($arquivo, (new finfo(FILEINFO_MIME_TYPE))->file($arquivo) ?: 'application/octet-stream', 'logo');
    }
}
