<?php

/**
 * Menu lateral. Cada item aponta para uma rota de config/rotas.php; o menu só mostra o que o
 * perfil pode abrir (o nível vem da própria rota — não há uma segunda lista de permissões aqui).
 *
 * 'grupo' = título da seção na barra lateral, na ordem do Design System (vazio = item solto no topo, como o Início).
 * 'icone' = nome de um ícone do Design System (app/core/Icones.php).
 * 'rotas' = telas "filhas" que mantêm o item marcado (cadastro, edição, detalhe).
 */
return [
    ['grupo' => '',           'rota' => 'inicio',       'rotulo' => 'Início',           'icone' => 'home'],
    ['grupo' => 'Operação',   'rota' => 'agenda',       'rotulo' => 'Agenda',           'icone' => 'calendar',   'rotas' => ['agenda-form', 'agenda-salvar']],
    ['grupo' => 'Operação',   'rota' => 'ordens',       'rotulo' => 'Ordens de serviço','icone' => 'work-order', 'rotas' => ['os', 'os-nova', 'os-criar']],
    ['grupo' => 'Financeiro', 'rota' => 'caixa',        'rotulo' => 'Caixa',            'icone' => 'cash',       'rotas' => ['caixas', 'caixa-ver']],
    ['grupo' => 'Financeiro', 'rota' => 'financeiro',   'rotulo' => 'Financeiro',       'icone' => 'wallet',     'rotas' => ['conta', 'conta-form', 'conta-salvar', 'comissoes']],
    ['grupo' => 'Cadastros',  'rota' => 'clientes',     'rotulo' => 'Clientes',         'icone' => 'users',      'rotas' => ['cliente', 'cliente-form', 'cliente-salvar']],
    ['grupo' => 'Cadastros',  'rota' => 'veiculos',     'rotulo' => 'Veículos',         'icone' => 'car',        'rotas' => ['veiculo', 'veiculo-form', 'veiculo-salvar']],
    ['grupo' => 'Cadastros',  'rota' => 'produtos',     'rotulo' => 'Peças e serviços', 'icone' => 'box',        'rotas' => ['produto', 'produto-form', 'produto-salvar']],
    ['grupo' => 'Cadastros',  'rota' => 'estoque',      'rotulo' => 'Estoque',          'icone' => 'part',       'rotas' => ['estoque-entrada', 'estoque-ajuste']],
    ['grupo' => 'Cadastros',  'rota' => 'fornecedores', 'rotulo' => 'Fornecedores',     'icone' => 'transfer',   'rotas' => ['fornecedor', 'fornecedor-form', 'fornecedor-salvar']],
    ['grupo' => 'Gestão',     'rota' => 'relatorios',   'rotulo' => 'Relatórios',       'icone' => 'chart'],
    ['grupo' => 'Gestão',     'rota' => 'usuarios',     'rotulo' => 'Usuários',         'icone' => 'user',       'rotas' => ['usuario-form', 'usuario-salvar']],
    ['grupo' => 'Gestão',     'rota' => 'configuracoes','rotulo' => 'Configurações',    'icone' => 'settings',   'rotas' => ['configuracoes-salvar']],
    ['grupo' => 'Plataforma', 'rota' => 'plataforma',   'rotulo' => 'Oficinas',         'icone' => 'dashboard',  'rotas' => ['plataforma-oficina', 'plataforma-nova', 'plataforma-criar']],
];
