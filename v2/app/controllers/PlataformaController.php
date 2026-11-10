<?php

/** Equipe do Mecanize: cadastro de oficinas e licenças. Não vê nada de dentro das oficinas. */
final class PlataformaController extends Controller
{
    public function lista(): void
    {
        $this->tela('plataforma/oficinas', ['oficinas' => (new Oficina())->listarTodas()]);
    }

    public function ver(): void
    {
        $m = new Oficina();
        $id = $this->idDaUrl();
        $of = $m->buscarGlobal($id) ?? throw new ErroHttp('registro-inexistente', ['voltar' => './plataforma']);
        $this->tela('plataforma/oficina', ['of' => $of, 'admins' => $m->administradores($id), 'historico' => $m->historicoLicenca($id)]);
    }

    public function nova(): void
    {
        $this->tela('plataforma/nova', ['d' => ['plano' => 'BASICO', 'limite_usuarios' => Oficina::PLANOS['BASICO'][1], 'status' => 'TESTE',
            'licenca_valida_ate' => date('Y-m-d', strtotime('+30 days'))]]);
    }

    public function criar(): void
    {
        try {
            $id = (new Oficina())->criar($_POST);
        } catch (DomainException $e) {
            $this->tela('plataforma/nova', ['d' => $_POST, 'erro' => $e->getMessage()]);
            return;
        }
        $this->avisar('Oficina cadastrada. Passe ao administrador o e-mail e a senha provisória; ele troca a senha no perfil.');
        $this->redirecionar('plataforma-oficina', ['id' => $id]);
    }

    public function licenca(): void
    {
        $id = $this->idDaUrl();
        try {
            $mudou = (new Oficina())->alterarLicenca($id, $_POST);
            $this->avisar($mudou ? 'Licença atualizada.' : 'Nada mudou.', $mudou ? 'sucesso' : 'aviso');
        } catch (DomainException $e) {
            $this->avisar($e->getMessage(), 'erro');
        }
        $this->redirecionar('plataforma-oficina', ['id' => $id]);
    }
}
