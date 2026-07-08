<?php
/*
|--------------------------------------------------------------------------
| Tipos de combustível (lista canônica)
|--------------------------------------------------------------------------
| Lista compartilhada por saveVeiculo, editVeiculo, viewVeiculo e
| listVeiculos.
| - save/edit: opções do <select> de combustível.
| - list: opções do filtro de combustível.
|
| O valor gravado no banco é o próprio NOME do combustível (coluna
| `combustivel`, VARCHAR). Mantenha os rótulos estáveis para que a
| filtragem por valor exato continue funcionando.
*/

return [
    'Gasolina',
    'Etanol',
    'Flex (Álcool/Gasolina)',
    'Diesel',
    'GNV',
    'Híbrido',
    'Elétrico',
];
