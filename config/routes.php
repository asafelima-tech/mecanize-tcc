<?php

$routes = [

    /* Site */
    'home'      => ['controller' => 'SiteController', 'method' => 'index'],
    'painel'    => ['controller' => 'SiteController', 'method' => 'painel'],

    /* Erro 404 */
    'erro404' => ['controller' => 'ErrorController', 'method' => 'erro404'],

    /* Páginas públicas (rodapé do login) */
    'termos'      => ['controller' => 'SiteController', 'method' => 'termos'],
    'privacidade' => ['controller' => 'SiteController', 'method' => 'privacidade'],

    /* Rastreamento público de OS/Orçamento (sem login) */
    'rastrear' => ['controller' => 'RastreioController', 'method' => 'ver'],

    /* Login */
    'login'        => ['controller' => 'UserController', 'method' => 'login'],
    'logout'       => ['controller' => 'UserController', 'method' => 'logout'],
    'perfil'       => ['controller' => 'UserController', 'method' => 'perfil'],
    'updateSenha'  => ['controller' => 'UserController', 'method' => 'updateSenha'],

    /* Usuários */
    'viewUsuario'   => ['controller' => 'UserController', 'method' => 'visualizar'],
    'listUsuarios'  => ['controller' => 'UserController', 'method' => 'list'],
    'exportUsuarios' => ['controller' => 'UserController', 'method' => 'exportar'],
    'saveUsuario'   => ['controller' => 'UserController', 'method' => 'save'],
    'editUsuario'   => ['controller' => 'UserController', 'method' => 'edit'],
    'deleteUsuario' => ['controller' => 'UserController', 'method' => 'delete'],
    'listUsuariosNovos30d' => ['controller' => 'UserController', 'method' => 'novos30dList'],
    'usuarioPerfilJson'    => ['controller' => 'UserController', 'method' => 'perfilJson'],

    /* Documentos */
    'listDocumentos'      => ['controller' => 'DocumentoController', 'method' => 'list'],
    'saveDocumento'       => ['controller' => 'DocumentoController', 'method' => 'save'],
    'finalizarDocumento'  => ['controller' => 'DocumentoController', 'method' => 'finalizar'],

    /* Orçamentos */
    'listOrcamentos'    => ['controller' => 'OrcamentoController', 'method' => 'list'],
    'saveOrcamento'     => ['controller' => 'OrcamentoController', 'method' => 'saveOrcamento'],
    'novoOrcamento'     => ['controller' => 'OrcamentoController', 'method' => 'save'],
    'viewOrcamento'     => ['controller' => 'OrcamentoController', 'method' => 'visualizar'],
    'editOrcamento'     => ['controller' => 'OrcamentoController', 'method' => 'edit'],
    'aprovarOrcamento'  => ['controller' => 'OrcamentoController', 'method' => 'aprovarOrcamento'],
    'recusarOrcamento'  => ['controller' => 'OrcamentoController', 'method' => 'recusarOrcamento'],
    'imprimirOrcamento' => ['controller' => 'OrcamentoController', 'method' => 'imprimir'],
    'exportOrcamentoPdf' => ['controller' => 'OrcamentoController', 'method' => 'exportarPdf'],

    /* Clientes */
    'viewCliente'             => ['controller' => 'ClienteController', 'method' => 'visualizar'],
    'listClientes'            => ['controller' => 'ClienteController', 'method' => 'list'],
    'saveCliente'             => ['controller' => 'ClienteController', 'method' => 'save'],
    'editCliente'             => ['controller' => 'ClienteController', 'method' => 'edit'],
    'deleteCliente'           => ['controller' => 'ClienteController', 'method' => 'delete'],
    'listClientesDesativados' => ['controller' => 'ClienteController', 'method' => 'listDesativados'],
    'reativarCliente'         => ['controller' => 'ClienteController', 'method' => 'reativar'],
    'vipList'                 => ['controller' => 'ClienteController', 'method' => 'vipList'],
    'vipPerfil'                => ['controller' => 'ClienteController', 'method' => 'vipPerfil'],
    'novosMesList' => ['controller' => 'ClienteController', 'method' => 'novosMesList'],

    /* Veículos */
    'viewVeiculo'   => ['controller' => 'VeiculoController', 'method' => 'visualizar'],
    'listVeiculos'  => ['controller' => 'VeiculoController', 'method' => 'list'],
    'saveVeiculo'   => ['controller' => 'VeiculoController', 'method' => 'save'],
    'editVeiculo'   => ['controller' => 'VeiculoController', 'method' => 'edit'],
    'deleteVeiculo' => ['controller' => 'VeiculoController', 'method' => 'delete'],
    'listVeiculosNovosMes' => ['controller' => 'VeiculoController', 'method' => 'novosMesList'],
    'veiculoPerfil'        => ['controller' => 'VeiculoController', 'method' => 'perfilJson'],

    /* Modelos de Veículos (endpoints AJAX usados em saveVeiculo/editVeiculo) */
    'modelosPorMarca'     => ['controller' => 'ModeloVeiculoController', 'method' => 'modelosPorMarca'],
    'criarModelo'         => ['controller' => 'ModeloVeiculoController', 'method' => 'criarModelo'],

    /* Categorias de Produtos */
    'listCategoria'   => ['controller' => 'CategoriaController', 'method' => 'list'],
    'saveCategoria'   => ['controller' => 'CategoriaController', 'method' => 'save'],
    'editCategoria'   => ['controller' => 'CategoriaController', 'method' => 'edit'],
    'deleteCategoria' => ['controller' => 'CategoriaController', 'method' => 'delete'],

    /* Produtos */
    'listProduto'   => ['controller' => 'ProdutoController', 'method' => 'list'],
    'saveProduto'   => ['controller' => 'ProdutoController', 'method' => 'save'],
    'editProduto'   => ['controller' => 'ProdutoController', 'method' => 'edit'],
    'viewProduto'   => ['controller' => 'ProdutoController', 'method' => 'visualizar'],
    'deleteProduto' => ['controller' => 'ProdutoController', 'method' => 'arquivar'],

    /* Ordem de Serviço */
    'listOrdemServico'   => ['controller' => 'OrdemServicoController', 'method' => 'list'],
    'saveOrdemServico'   => ['controller' => 'OrdemServicoController', 'method' => 'save'],
    'editOrdemServico'   => ['controller' => 'OrdemServicoController', 'method' => 'edit'],
    'deleteOrdemServico' => ['controller' => 'OrdemServicoController', 'method' => 'delete'],
    'viewOrdemServico'   => ['controller' => 'OrdemServicoController', 'method' => 'visualizar'],
    'concluirOrdemServico' => ['controller' => 'OrdemServicoController', 'method' => 'concluir'],
    'exportOrdemServicoPdf' => ['controller' => 'OrdemServicoController', 'method' => 'exportarPdf'],
    'kanbanOrdemServico' => ['controller' => 'OrdemServicoController', 'method' => 'kanban'],
    'moverStatusOrdemServico' => ['controller' => 'OrdemServicoController', 'method' => 'moverStatus'],

    /* Relatórios */
    'listRel' => ['controller' => 'RelatorioController', 'method' => 'listRelatorio'],

    /* Agenda de serviços */
    'listAgendamentos'   => ['controller' => 'AgendamentoController', 'method' => 'list'],
    'saveAgendamento'    => ['controller' => 'AgendamentoController', 'method' => 'save'],
    'editAgendamento'    => ['controller' => 'AgendamentoController', 'method' => 'edit'],
    'statusAgendamento'  => ['controller' => 'AgendamentoController', 'method' => 'status'],
    'deleteAgendamento'  => ['controller' => 'AgendamentoController', 'method' => 'delete'],
    'gerarOsAgendamento' => ['controller' => 'AgendamentoController', 'method' => 'gerarOrdemServico'],

    /* Senha */
    'recuperar-senha' => ['controller' => 'UserController', 'method' => 'recuperarSenha'],
    'redefinir-senha' => ['controller' => 'UserController', 'method' => 'redefinirSenha'],

    /* Movimentações de Estoque */
    'listMovimentacaoEstoque'   => ['controller' => 'MovimentacaoEstoqueController', 'method' => 'list'],
    'saveMovimentacaoEstoque'   => ['controller' => 'MovimentacaoEstoqueController', 'method' => 'save'],
    'editMovimentacaoEstoque'   => ['controller' => 'MovimentacaoEstoqueController', 'method' => 'edit'],
    'deleteMovimentacaoEstoque' => ['controller' => 'MovimentacaoEstoqueController', 'method' => 'delete'],

    /* Ler placa */
    'lerPlaca' => ['controller' => 'PlacaController', 'method' => 'ler'],

    /* Caixa (abertura/fechamento) */
    'abrirCaixa'  => ['controller' => 'CaixaController', 'method' => 'abrir'],
    'fecharCaixa' => ['controller' => 'CaixaController', 'method' => 'fechar'],
    'listCaixa'   => ['controller' => 'CaixaController', 'method' => 'list'],

    /* Caixa (livro-caixa: lançamentos de entrada/saída) */
    'caixaMovimentacoes'         => ['controller' => 'CaixaController', 'method' => 'movimentacoes'],
    'registrarMovimentacaoCaixa' => ['controller' => 'CaixaController', 'method' => 'registrarMovimentacao'],

    /* Caixa (relatório por período: diário/semanal/mensal/anual) */
    'relatorioCaixa'    => ['controller' => 'CaixaController', 'method' => 'relatorio'],
    'relatorioCaixaPdf' => ['controller' => 'CaixaController', 'method' => 'exportarRelatorioPdf'],

];
