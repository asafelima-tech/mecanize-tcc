<?php

final class InicioController extends Controller
{
    public function inicio(): void
    {
        $painel = new Painel();
        $admin = Contexto::perfil() === 'ADMIN';

        $this->tela('inicio/painel', [
            'resumo'  => $painel->resumo(),
            'ultimas' => $painel->ultimasOs(),
            'agenda'  => (new Agendamento())->proximos(),
            'alertas' => $painel->alertas($admin),
            'admin'   => $admin,
        ]);
    }
}
