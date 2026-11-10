<?php

/**
 * Menu lateral. Cada item aponta para uma rota de config/rotas.php; o menu só mostra o que o
 * perfil pode abrir (o nível vem da própria rota — não há uma segunda lista de permissões aqui).
 *
 * 'rotas' = telas "filhas" que mantêm o item marcado (cadastro, edição, detalhe).
 */
return [
    ['rota' => 'inicio',       'rotulo' => 'Início',          'icone' => 'bi-house'],
    ['rota' => 'agenda',       'rotulo' => 'Agenda',          'icone' => 'bi-calendar3',    'rotas' => ['agenda-form', 'agenda-salvar']],
    ['rota' => 'ordens',       'rotulo' => 'Ordens de serviço','icone' => 'bi-clipboard-check', 'rotas' => ['os', 'os-nova', 'os-criar']],
    ['rota' => 'caixa',        'rotulo' => 'Caixa',           'icone' => 'bi-cash-stack',   'rotas' => ['caixas', 'caixa-ver']],
    ['rota' => 'financeiro',   'rotulo' => 'Financeiro',      'icone' => 'bi-wallet2',      'rotas' => ['conta', 'conta-form', 'conta-salvar', 'comissoes']],
    ['rota' => 'clientes',     'rotulo' => 'Clientes',        'icone' => 'bi-people',       'rotas' => ['cliente', 'cliente-form', 'cliente-salvar']],
    ['rota' => 'veiculos',     'rotulo' => 'Veículos',        'icone' => 'bi-car-front',    'rotas' => ['veiculo', 'veiculo-form', 'veiculo-salvar']],
    ['rota' => 'produtos',     'rotulo' => 'Peças e serviços','icone' => 'bi-box-seam',     'rotas' => ['produto', 'produto-form', 'produto-salvar']],
    ['rota' => 'estoque',      'rotulo' => 'Estoque',         'icone' => 'bi-boxes',        'rotas' => ['estoque-entrada', 'estoque-ajuste']],
    ['rota' => 'fornecedores', 'rotulo' => 'Fornecedores',    'icone' => 'bi-truck',        'rotas' => ['fornecedor', 'fornecedor-form', 'fornecedor-salvar']],
    ['rota' => 'relatorios',   'rotulo' => 'Relatórios',      'icone' => 'bi-bar-chart-line'],
    ['rota' => 'usuarios',     'rotulo' => 'Usuários',        'icone' => 'bi-person-gear',  'rotas' => ['usuario-form', 'usuario-salvar']],
    ['rota' => 'configuracoes','rotulo' => 'Configurações',   'icone' => 'bi-gear',         'rotas' => ['configuracoes-salvar']],
    ['rota' => 'plataforma',   'rotulo' => 'Oficinas',        'icone' => 'bi-buildings',    'rotas' => ['plataforma-oficina', 'plataforma-nova', 'plataforma-criar']],
];
