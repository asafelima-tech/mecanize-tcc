<?php

final class InicioController extends Controller
{
    public function inicio(): void
    {
        // O início do técnico é a lista dos serviços dele (o painel da oficina tem números e dinheiro).
        if (Contexto::perfil() === 'TECNICO') {
            $this->redirecionar('servicos');
        }

        $painel = new Painel();
        $admin = Contexto::perfil() === 'ADMIN';

        $this->tela('inicio/painel', [
            'resumo'    => $painel->resumo(),
            'graficos'  => $painel->graficos(),
            'ultimas'   => $painel->ultimasOs(5),
            'alertas'   => $painel->alertas($admin),
            'admin'     => $admin,
        ]);
    }
}
