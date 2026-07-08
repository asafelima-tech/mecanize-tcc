<?php
/*
|--------------------------------------------------------------------------
| Cores de carro (nome PT-BR => hex)
|--------------------------------------------------------------------------
| Lista canônica compartilhada por saveVeiculo, editVeiculo e listVeiculos.
| - save/edit: opções do <select> de cor (Tom Select).
| - list: define a cor da bolinha (color-chip__dot) a partir do nome salvo.
|
| O valor gravado no banco continua sendo o NOME da cor (coluna `cor`,
| string) — nenhuma mudança de back-end é necessária.
*/

return [
    'Branco'        => '#FFFFFF',
    'Branco Pérola' => '#ECE9E0',
    'Preto'         => '#1A1A1A',
    'Prata'         => '#C8CDD2',
    'Cinza'         => '#8B9096',
    'Grafite'       => '#3C4043',
    'Chumbo'        => '#4D5258',
    'Vermelho'      => '#D21F26',
    'Vinho'         => '#5E1622',
    'Azul'          => '#1561B0',
    'Azul Marinho'  => '#17284C',
    'Verde'         => '#2E7D32',
    'Verde Escuro'  => '#1B3A2B',
    'Amarelo'       => '#F2C200',
    'Laranja'       => '#E8650E',
    'Marrom'        => '#5A3E2B',
    'Bege'          => '#D9C7A7',
    'Dourado'       => '#C8A028',
    'Bronze'        => '#8A6A45',
    'Rosa'          => '#E64A8B',
    'Roxo'          => '#6B2FA0',
];
