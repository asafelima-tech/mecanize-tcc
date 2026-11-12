<?php

final class InicioController extends Controller
{
    public function inicio(): void
    {
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
