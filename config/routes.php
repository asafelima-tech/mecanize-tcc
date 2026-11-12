<?php

/*
 * Tabela de rotas.
 *
 * 'acesso' é OBRIGATÓRIO em toda rota e é exigido por public/index.php antes de
 * o controller rodar (ver app/core/Permissao.php):
 *
 *   PUBLICO  sem login
 *   LOGADO   qualquer usuário autenticado
 *   EQUIPE   ADMIN + ATENDENTE + TECNICO (mesmas permissões para os três)
 *   ADMIN    somente o administrador
 *
 * Os níveis são strings (e não Permissao::X) de propósito: este arquivo é
 * incluído por testes e por UserController sem as classes do núcleo carregadas.
 */
$routes = [

    /* Site */
    'home'      => ['controller' => 'SiteController', 'method' => 'index', 'acesso' => 'LOGADO'],
    'painel'    => ['controller' => 'SiteController', 'method' => 'painel', 'acesso' => 'LOGADO'],

    /* Telas de erro com endereço próprio (as demais são disparadas pelo fluxo) */
    'erro404'          => ['controller' => 'ErrorController', 'method' => 'erro404', 'acesso' => 'PUBLICO'],
    'erroPermissao'    => ['controller' => 'ErrorController', 'method' => 'semPermissao', 'acesso' => 'PUBLICO'],
    'erroIndisponivel' => ['controller' => 'ErrorController', 'method' => 'indisponivel', 'acesso' => 'PUBLICO'],
    'erroConexao'      => ['controller' => 'ErrorController', 'method' => 'semConexao', 'acesso' => 'PUBLICO'],
    'erroTempo'        => ['controller' => 'ErrorController', 'method' => 'tempoEsgotado', 'acesso' => 'PUBLICO'],

    /* Páginas públicas (rodapé do login) */
    'termos'      => ['controller' => 'SiteController', 'method' => 'termos', 'acesso' => 'PUBLICO'],
    'privacidade' => ['controller' => 'SiteController', 'method' => 'privacidade', 'acesso' => 'PUBLICO'],

    /* Rastreamento público de OS/Orçamento (sem login) */
    'rastrear' => ['controller' => 'RastreioController', 'method' => 'ver', 'acesso' => 'PUBLICO'],

    /* Portal do cliente (link com token, sem login) */
    'portal'                  => ['controller' => 'PortalController', 'method' => 'ver', 'acesso' => 'PUBLICO'],
    'portalOrcamento'         => ['controller' => 'PortalController', 'method' => 'decidirOrcamento', 'acesso' => 'PUBLICO'],
    'portalAutorizacao'       => ['controller' => 'PortalController', 'method' => 'decidirAutorizacao', 'acesso' => 'PUBLICO'],
    'portalPagar'             => ['controller' => 'PortalController', 'method' => 'pagar', 'acesso' => 'PUBLICO'],
    'portalConsentimento'     => ['controller' => 'PortalController', 'method' => 'consentimento', 'acesso' => 'PUBLICO'],
    'portalPagamentoSimulado' => ['controller' => 'PortalController', 'method' => 'pagamentoSimulado', 'acesso' => 'PUBLICO'],

    /*
     * Webhooks (servidor externo chamando): sem login e SEM CSRF — não há sessão nem formulário. 'csrf' => false só
     * vale para rota PUBLICO e o PermissaoTest só admite as rotas abaixo. A autenticação é a assinatura do corpo.
     */
    'webhookWhatsApp'  => ['controller' => 'WebhookController', 'method' => 'whatsapp', 'acesso' => 'PUBLICO', 'csrf' => false],
    'webhookPagamento' => ['controller' => 'WebhookController', 'method' => 'pagamento', 'acesso' => 'PUBLICO', 'csrf' => false],

    /* Conversas com clientes (central) — técnico e atendente têm as mesmas permissões */
    'listConversas'           => ['controller' => 'ConversaController', 'method' => 'list', 'acesso' => 'EQUIPE'],
    'viewConversa'            => ['controller' => 'ConversaController', 'method' => 'visualizar', 'acesso' => 'EQUIPE'],
    'responderConversa'       => ['controller' => 'ConversaController', 'method' => 'responder', 'acesso' => 'EQUIPE'],
    'registrarContatoConversa' => ['controller' => 'ConversaController', 'method' => 'registrarContato', 'acesso' => 'EQUIPE'],
    'atribuirConversa'        => ['controller' => 'ConversaController', 'method' => 'atribuir', 'acesso' => 'EQUIPE'],
    'statusConversa'          => ['controller' => 'ConversaController', 'method' => 'mudarStatus', 'acesso' => 'EQUIPE'],
    'vincularConversa'        => ['controller' => 'ConversaController', 'method' => 'vincular', 'acesso' => 'EQUIPE'],
    'associarConversa'        => ['controller' => 'ConversaController', 'method' => 'associarCliente', 'acesso' => 'EQUIPE'],
    'iniciarConversa'         => ['controller' => 'ConversaController', 'method' => 'iniciar', 'acesso' => 'EQUIPE'],
    'registrarConsentimento'  => ['controller' => 'ConversaController', 'method' => 'consentimento', 'acesso' => 'EQUIPE'],

    /* Link do cliente (portal) e previsão de conclusão */
    'gerarLinkPortal'   => ['controller' => 'PortalEquipeController', 'method' => 'gerarLink', 'acesso' => 'EQUIPE'],
    'enviarLinkPortal'  => ['controller' => 'PortalEquipeController', 'method' => 'enviarLink', 'acesso' => 'EQUIPE'],
    'revogarLinksPortal' => ['controller' => 'PortalEquipeController', 'method' => 'revogarLinks', 'acesso' => 'EQUIPE'],
    'salvarPrevisaoOS'  => ['controller' => 'PortalEquipeController', 'method' => 'salvarPrevisao', 'acesso' => 'EQUIPE'],

    /* Integrações (estado das credenciais e webhooks) — só o administrador */
    'integracoes'        => ['controller' => 'IntegracaoController', 'method' => 'index', 'acesso' => 'ADMIN'],
    'despacharMensagens' => ['controller' => 'IntegracaoController', 'method' => 'despachar', 'acesso' => 'ADMIN'],

    /* Login */
    'login'        => ['controller' => 'UserController', 'method' => 'login', 'acesso' => 'PUBLICO'],
    'logout'       => ['controller' => 'UserController', 'method' => 'logout', 'acesso' => 'PUBLICO'],
    'perfil'       => ['controller' => 'UserController', 'method' => 'perfil', 'acesso' => 'LOGADO'],
    'updateSenha'  => ['controller' => 'UserController', 'method' => 'updateSenha', 'acesso' => 'LOGADO'],

    /* Usuários */
    'viewUsuario'   => ['controller' => 'UserController', 'method' => 'visualizar', 'acesso' => 'ADMIN'],
    'listUsuarios'  => ['controller' => 'UserController', 'method' => 'list', 'acesso' => 'ADMIN'],
    'exportUsuarios' => ['controller' => 'UserController', 'method' => 'exportar', 'acesso' => 'ADMIN'],
    'saveUsuario'   => ['controller' => 'UserController', 'method' => 'save', 'acesso' => 'ADMIN'],
    'editUsuario'   => ['controller' => 'UserController', 'method' => 'edit', 'acesso' => 'ADMIN'],
    'deleteUsuario' => ['controller' => 'UserController', 'method' => 'delete', 'acesso' => 'ADMIN'],
    'toggleStatusUsuario' => ['controller' => 'UserController', 'method' => 'toggleStatus', 'acesso' => 'ADMIN'],
    'listUsuariosNovos30d' => ['controller' => 'UserController', 'method' => 'novos30dList', 'acesso' => 'ADMIN'],
    'usuarioPerfilJson'    => ['controller' => 'UserController', 'method' => 'perfilJson', 'acesso' => 'ADMIN'],

    /* Documentos */
    'listDocumentos'      => ['controller' => 'DocumentoController', 'method' => 'list', 'acesso' => 'ADMIN'],
    'saveDocumento'       => ['controller' => 'DocumentoController', 'method' => 'save', 'acesso' => 'ADMIN'],
    'finalizarDocumento'  => ['controller' => 'DocumentoController', 'method' => 'finalizar', 'acesso' => 'ADMIN'],

    /* Orçamentos */
    'listOrcamentos'    => ['controller' => 'OrcamentoController', 'method' => 'list', 'acesso' => 'EQUIPE'],
    'saveOrcamento'     => ['controller' => 'OrcamentoController', 'method' => 'saveOrcamento', 'acesso' => 'EQUIPE'],
    'novoOrcamento'     => ['controller' => 'OrcamentoController', 'method' => 'save', 'acesso' => 'EQUIPE'],
    'viewOrcamento'     => ['controller' => 'OrcamentoController', 'method' => 'visualizar', 'acesso' => 'EQUIPE'],
    'editOrcamento'     => ['controller' => 'OrcamentoController', 'method' => 'edit', 'acesso' => 'EQUIPE'],
    'aprovarOrcamento'  => ['controller' => 'OrcamentoController', 'method' => 'aprovarOrcamento', 'acesso' => 'EQUIPE'],
    'recusarOrcamento'  => ['controller' => 'OrcamentoController', 'method' => 'recusarOrcamento', 'acesso' => 'EQUIPE'],
    'reabrirOrcamento'  => ['controller' => 'OrcamentoController', 'method' => 'reabrirOrcamento', 'acesso' => 'EQUIPE'],
    'enviarOrcamento'   => ['controller' => 'OrcamentoController', 'method' => 'enviarOrcamento', 'acesso' => 'EQUIPE'],
    'imprimirOrcamento' => ['controller' => 'OrcamentoController', 'method' => 'imprimir', 'acesso' => 'EQUIPE'],
    'exportOrcamentoPdf' => ['controller' => 'OrcamentoController', 'method' => 'exportarPdf', 'acesso' => 'EQUIPE'],

    /* Atendimento: ocorrências (chegada → avaliação → orçamento → OS) */
    'listOcorrencias'              => ['controller' => 'OcorrenciaController', 'method' => 'list', 'acesso' => 'EQUIPE'],
    'saveOcorrencia'               => ['controller' => 'OcorrenciaController', 'method' => 'save', 'acesso' => 'EQUIPE'],
    'viewOcorrencia'               => ['controller' => 'OcorrenciaController', 'method' => 'visualizar', 'acesso' => 'EQUIPE'],
    'buscarClientes'               => ['controller' => 'OcorrenciaController', 'method' => 'buscarClientes', 'acesso' => 'EQUIPE'],
    'iniciarAvaliacaoOcorrencia'   => ['controller' => 'OcorrenciaController', 'method' => 'iniciarAvaliacao', 'acesso' => 'EQUIPE'],
    'salvarAvaliacaoOcorrencia'    => ['controller' => 'OcorrenciaController', 'method' => 'salvarAvaliacao', 'acesso' => 'EQUIPE'],
    'gerarOrcamentoOcorrencia'     => ['controller' => 'OcorrenciaController', 'method' => 'gerarOrcamento', 'acesso' => 'EQUIPE'],
    'registrarAutorizacaoOcorrencia' => ['controller' => 'OcorrenciaController', 'method' => 'registrarAutorizacao', 'acesso' => 'EQUIPE'],
    'abrirOsOcorrencia'            => ['controller' => 'OcorrenciaController', 'method' => 'abrirOs', 'acesso' => 'EQUIPE'],
    'validarUrgenciaOcorrencia'    => ['controller' => 'OcorrenciaController', 'method' => 'validarUrgencia', 'acesso' => 'ADMIN'],
    'atribuirTecnicoOcorrencia'    => ['controller' => 'OcorrenciaController', 'method' => 'atribuirTecnico', 'acesso' => 'EQUIPE'],
    'cancelarOcorrencia'           => ['controller' => 'OcorrenciaController', 'method' => 'cancelar', 'acesso' => 'EQUIPE'],

    /* Fila operacional */
    'filaOperacional' => ['controller' => 'FilaController', 'method' => 'index', 'acesso' => 'EQUIPE'],
    'reordenarFila'   => ['controller' => 'FilaController', 'method' => 'reordenar', 'acesso' => 'ADMIN'],

    /* Estoque: consulta da equipe e solicitação de peças */
    'consultaEstoque'        => ['controller' => 'EstoqueController', 'method' => 'consulta', 'acesso' => 'EQUIPE'],
    'listSolicitacoes'       => ['controller' => 'SolicitacaoPecaController', 'method' => 'list', 'acesso' => 'EQUIPE'],
    'saveSolicitacao'        => ['controller' => 'SolicitacaoPecaController', 'method' => 'save', 'acesso' => 'EQUIPE'],
    'viewSolicitacao'        => ['controller' => 'SolicitacaoPecaController', 'method' => 'visualizar', 'acesso' => 'EQUIPE'],
    'cancelarSolicitacao'    => ['controller' => 'SolicitacaoPecaController', 'method' => 'cancelar', 'acesso' => 'EQUIPE'],
    'analisarSolicitacao'    => ['controller' => 'SolicitacaoPecaController', 'method' => 'analisar', 'acesso' => 'ADMIN'],
    'aprovarSolicitacao'     => ['controller' => 'SolicitacaoPecaController', 'method' => 'aprovar', 'acesso' => 'ADMIN'],
    'registrarCotacao'       => ['controller' => 'SolicitacaoPecaController', 'method' => 'registrarCotacao', 'acesso' => 'ADMIN'],
    'excluirCotacao'         => ['controller' => 'SolicitacaoPecaController', 'method' => 'excluirCotacao', 'acesso' => 'ADMIN'],
    'usarCotacao'            => ['controller' => 'SolicitacaoPecaController', 'method' => 'usarCotacao', 'acesso' => 'ADMIN'],

    /* Compras e fornecedores (administrador) */
    'painelCompras'          => ['controller' => 'CompraController', 'method' => 'painel', 'acesso' => 'ADMIN'],
    'criarPedidoDeNecessidades' => ['controller' => 'CompraController', 'method' => 'criarPedidoDeNecessidades', 'acesso' => 'ADMIN'],
    'listPedidosCompra'      => ['controller' => 'CompraController', 'method' => 'listarPedidos', 'acesso' => 'ADMIN'],
    'criarPedidoCompra'      => ['controller' => 'CompraController', 'method' => 'criarPedido', 'acesso' => 'ADMIN'],
    'viewPedidoCompra'       => ['controller' => 'CompraController', 'method' => 'visualizarPedido', 'acesso' => 'ADMIN'],
    'atualizarPedidoCompra'  => ['controller' => 'CompraController', 'method' => 'atualizarPedido', 'acesso' => 'ADMIN'],
    'adicionarItemPedidoCompra' => ['controller' => 'CompraController', 'method' => 'adicionarItem', 'acesso' => 'ADMIN'],
    'atualizarItemPedidoCompra' => ['controller' => 'CompraController', 'method' => 'atualizarItem', 'acesso' => 'ADMIN'],
    'removerItemPedidoCompra'   => ['controller' => 'CompraController', 'method' => 'removerItem', 'acesso' => 'ADMIN'],
    'realizarPedidoCompra'   => ['controller' => 'CompraController', 'method' => 'realizar', 'acesso' => 'ADMIN'],
    'cancelarPedidoCompra'   => ['controller' => 'CompraController', 'method' => 'cancelar', 'acesso' => 'ADMIN'],
    'encerrarPedidoCompra'   => ['controller' => 'CompraController', 'method' => 'encerrar', 'acesso' => 'ADMIN'],
    'receberPedidoCompra'    => ['controller' => 'CompraController', 'method' => 'receber', 'acesso' => 'ADMIN'],
    'estornarRecebimentoCompra' => ['controller' => 'CompraController', 'method' => 'estornarRecebimento', 'acesso' => 'ADMIN'],
    'listFornecedores'       => ['controller' => 'CompraController', 'method' => 'listarFornecedores', 'acesso' => 'ADMIN'],
    'saveFornecedor'         => ['controller' => 'CompraController', 'method' => 'salvarFornecedor', 'acesso' => 'ADMIN'],
    'editFornecedor'         => ['controller' => 'CompraController', 'method' => 'editarFornecedor', 'acesso' => 'ADMIN'],
    'alternarFornecedor'     => ['controller' => 'CompraController', 'method' => 'alternarFornecedor', 'acesso' => 'ADMIN'],

    /* Financeiro (administrador) */
    'painelFinanceiro'       => ['controller' => 'FinanceiroController', 'method' => 'painel', 'acesso' => 'ADMIN'],
    'listContasReceber'      => ['controller' => 'FinanceiroController', 'method' => 'contasReceber', 'acesso' => 'ADMIN'],
    'listContasPagar'        => ['controller' => 'FinanceiroController', 'method' => 'contasPagar', 'acesso' => 'ADMIN'],
    'saveDespesa'            => ['controller' => 'FinanceiroController', 'method' => 'salvarDespesa', 'acesso' => 'ADMIN'],
    'viewDespesa'            => ['controller' => 'FinanceiroController', 'method' => 'visualizarDespesa', 'acesso' => 'ADMIN'],
    'cancelarDespesa'        => ['controller' => 'FinanceiroController', 'method' => 'cancelarDespesa', 'acesso' => 'ADMIN'],
    'lancarContaPedido'      => ['controller' => 'FinanceiroController', 'method' => 'lancarContaPedido', 'acesso' => 'ADMIN'],
    'pagarParcelaConta'      => ['controller' => 'FinanceiroController', 'method' => 'pagarParcela', 'acesso' => 'ADMIN'],
    'estornarPagamentoConta' => ['controller' => 'FinanceiroController', 'method' => 'estornarPagamento', 'acesso' => 'ADMIN'],
    'listCartoes'            => ['controller' => 'FinanceiroController', 'method' => 'listarCartoes', 'acesso' => 'ADMIN'],
    'saveCartao'             => ['controller' => 'FinanceiroController', 'method' => 'salvarCartao', 'acesso' => 'ADMIN'],
    'editCartao'             => ['controller' => 'FinanceiroController', 'method' => 'editarCartao', 'acesso' => 'ADMIN'],
    'alternarCartao'         => ['controller' => 'FinanceiroController', 'method' => 'alternarCartao', 'acesso' => 'ADMIN'],
    'viewCartao'             => ['controller' => 'FinanceiroController', 'method' => 'visualizarCartao', 'acesso' => 'ADMIN'],
    'viewFatura'             => ['controller' => 'FinanceiroController', 'method' => 'visualizarFatura', 'acesso' => 'ADMIN'],
    'pagarFaturaCartao'      => ['controller' => 'FinanceiroController', 'method' => 'pagarFatura', 'acesso' => 'ADMIN'],
    'enviarComprovante'      => ['controller' => 'FinanceiroController', 'method' => 'enviarComprovante', 'acesso' => 'ADMIN'],
    'baixarComprovante'      => ['controller' => 'FinanceiroController', 'method' => 'baixarComprovante', 'acesso' => 'ADMIN'],

    /* Notificações internas (o sino) */
    'listNotificacoes'       => ['controller' => 'NotificacaoController', 'method' => 'list', 'acesso' => 'LOGADO'],
    'lerNotificacao'         => ['controller' => 'NotificacaoController', 'method' => 'ler', 'acesso' => 'LOGADO'],
    'lerTodasNotificacoes'   => ['controller' => 'NotificacaoController', 'method' => 'lerTodas', 'acesso' => 'LOGADO'],

    /* Clientes */
    'viewCliente'             => ['controller' => 'ClienteController', 'method' => 'visualizar', 'acesso' => 'EQUIPE'],
    'listClientes'            => ['controller' => 'ClienteController', 'method' => 'list', 'acesso' => 'EQUIPE'],
    'saveCliente'             => ['controller' => 'ClienteController', 'method' => 'save', 'acesso' => 'EQUIPE'],
    'editCliente'             => ['controller' => 'ClienteController', 'method' => 'edit', 'acesso' => 'EQUIPE'],
    'deleteCliente'           => ['controller' => 'ClienteController', 'method' => 'delete', 'acesso' => 'ADMIN'],
    'listClientesDesativados' => ['controller' => 'ClienteController', 'method' => 'listDesativados', 'acesso' => 'EQUIPE'],
    'reativarCliente'         => ['controller' => 'ClienteController', 'method' => 'reativar', 'acesso' => 'ADMIN'],
    'vipList'                 => ['controller' => 'ClienteController', 'method' => 'vipList', 'acesso' => 'EQUIPE'],
    'vipPerfil'               => ['controller' => 'ClienteController', 'method' => 'vipPerfil', 'acesso' => 'EQUIPE'],
    'novosMesList'            => ['controller' => 'ClienteController', 'method' => 'novosMesList', 'acesso' => 'EQUIPE'],
    'unificarCliente'         => ['controller' => 'ClienteController', 'method' => 'unificar', 'acesso' => 'ADMIN'],

    /* Veículos */
    'viewVeiculo'   => ['controller' => 'VeiculoController', 'method' => 'visualizar', 'acesso' => 'EQUIPE'],
    'listVeiculos'  => ['controller' => 'VeiculoController', 'method' => 'list', 'acesso' => 'EQUIPE'],
    'saveVeiculo'   => ['controller' => 'VeiculoController', 'method' => 'save', 'acesso' => 'EQUIPE'],
    'editVeiculo'   => ['controller' => 'VeiculoController', 'method' => 'edit', 'acesso' => 'EQUIPE'],
    'deleteVeiculo' => ['controller' => 'VeiculoController', 'method' => 'delete', 'acesso' => 'ADMIN'],
    'listVeiculosNovosMes' => ['controller' => 'VeiculoController', 'method' => 'novosMesList', 'acesso' => 'EQUIPE'],
    'veiculoPerfil'        => ['controller' => 'VeiculoController', 'method' => 'perfilJson', 'acesso' => 'EQUIPE'],

    /* Modelos de Veículos (endpoints AJAX usados em saveVeiculo/editVeiculo) */
    'modelosPorMarca'     => ['controller' => 'ModeloVeiculoController', 'method' => 'modelosPorMarca', 'acesso' => 'EQUIPE'],
    'criarModelo'         => ['controller' => 'ModeloVeiculoController', 'method' => 'criarModelo', 'acesso' => 'EQUIPE'],

    /* Categorias de Produtos */
    'listCategoria'   => ['controller' => 'CategoriaController', 'method' => 'list', 'acesso' => 'EQUIPE'],
    'saveCategoria'   => ['controller' => 'CategoriaController', 'method' => 'save', 'acesso' => 'ADMIN'],
    'editCategoria'   => ['controller' => 'CategoriaController', 'method' => 'edit', 'acesso' => 'ADMIN'],
    'deleteCategoria' => ['controller' => 'CategoriaController', 'method' => 'delete', 'acesso' => 'ADMIN'],

    /* Produtos */
    'listProduto'   => ['controller' => 'ProdutoController', 'method' => 'list', 'acesso' => 'ADMIN'],
    'saveProduto'   => ['controller' => 'ProdutoController', 'method' => 'save', 'acesso' => 'ADMIN'],
    'editProduto'   => ['controller' => 'ProdutoController', 'method' => 'edit', 'acesso' => 'ADMIN'],
    'viewProduto'   => ['controller' => 'ProdutoController', 'method' => 'visualizar', 'acesso' => 'ADMIN'],
    'deleteProduto' => ['controller' => 'ProdutoController', 'method' => 'arquivar', 'acesso' => 'ADMIN'],
    'etiquetaProduto' => ['controller' => 'ProdutoController', 'method' => 'imprimirEtiqueta', 'acesso' => 'ADMIN'],

    /* Ordem de Serviço */
    'listOrdemServico'   => ['controller' => 'OrdemServicoController', 'method' => 'list', 'acesso' => 'EQUIPE'],
    'saveOrdemServico'   => ['controller' => 'OrdemServicoController', 'method' => 'save', 'acesso' => 'EQUIPE'],
    'editOrdemServico'   => ['controller' => 'OrdemServicoController', 'method' => 'edit', 'acesso' => 'EQUIPE'],
    'deleteOrdemServico' => ['controller' => 'OrdemServicoController', 'method' => 'delete', 'acesso' => 'ADMIN'],
    'viewOrdemServico'   => ['controller' => 'OrdemServicoController', 'method' => 'visualizar', 'acesso' => 'EQUIPE'],
    'exportOrdemServicoPdf' => ['controller' => 'OrdemServicoController', 'method' => 'exportarPdf', 'acesso' => 'EQUIPE'],
    'kanbanOrdemServico' => ['controller' => 'OrdemServicoController', 'method' => 'kanban', 'acesso' => 'EQUIPE'],
    'moverStatusOrdemServico' => ['controller' => 'OrdemServicoController', 'method' => 'moverStatus', 'acesso' => 'EQUIPE'],

    /* Ciclo de vida da OS: cada passo é uma transição validada (OrdemServicoFluxo) */
    'conferirOrdemServico'           => ['controller' => 'OrdemServicoController', 'method' => 'conferir', 'acesso' => 'ADMIN'],
    'reprovarConferenciaOrdemServico' => ['controller' => 'OrdemServicoController', 'method' => 'reprovarConferencia', 'acesso' => 'ADMIN'],
    'entregarOrdemServico'           => ['controller' => 'OrdemServicoController', 'method' => 'entregar', 'acesso' => 'EQUIPE'],
    'cancelarOrdemServico'           => ['controller' => 'OrdemServicoController', 'method' => 'cancelar', 'acesso' => 'ADMIN'],
    'consumirPecaOrdemServico'       => ['controller' => 'OrdemServicoController', 'method' => 'consumirPeca', 'acesso' => 'EQUIPE'],
    'devolverPecaOrdemServico'       => ['controller' => 'OrdemServicoController', 'method' => 'devolverPeca', 'acesso' => 'EQUIPE'],
    'estornarParcelaOrdemServico'    => ['controller' => 'OrdemServicoController', 'method' => 'estornarParcela', 'acesso' => 'ADMIN'],
    'reservarPecaOrdemServico'       => ['controller' => 'OrdemServicoController', 'method' => 'reservarPeca', 'acesso' => 'EQUIPE'],
    'liberarReservaOrdemServico'     => ['controller' => 'OrdemServicoController', 'method' => 'liberarReserva', 'acesso' => 'EQUIPE'],
    'solicitarComplementoOrdemServico' => ['controller' => 'OrdemServicoController', 'method' => 'solicitarComplemento', 'acesso' => 'EQUIPE'],
    'decidirAutorizacaoOrdemServico' => ['controller' => 'OrdemServicoController', 'method' => 'decidirAutorizacao', 'acesso' => 'EQUIPE'],

    /* Pagamento da OS (plano à vista/parcelado e baixa de parcela) */
    'pagamentoOrdemServico'  => ['controller' => 'OrdemServicoController', 'method' => 'pagamento', 'acesso' => 'EQUIPE'],
    'salvarPagamentoOrdemServico' => ['controller' => 'OrdemServicoController', 'method' => 'salvarPagamento', 'acesso' => 'EQUIPE'],
    'pagarParcelaOrdemServico'    => ['controller' => 'OrdemServicoController', 'method' => 'pagarParcela', 'acesso' => 'EQUIPE'],

    /* Financeiro/Comissões dos Funcionários */
    'comissoesFuncionarios'      => ['controller' => 'FinanceiroFuncionarioController', 'method' => 'comissoes', 'acesso' => 'ADMIN'],
    'pagarComissoesFuncionario'  => ['controller' => 'FinanceiroFuncionarioController', 'method' => 'pagar', 'acesso' => 'ADMIN'],
    'ajustarComissaoFuncionario' => ['controller' => 'FinanceiroFuncionarioController', 'method' => 'ajustar', 'acesso' => 'ADMIN'],

    /* Área do Técnico — mesmas permissões do atendente; muda só a tela inicial */
    'painelTecnico'              => ['controller' => 'TecnicoController', 'method' => 'painel', 'acesso' => 'EQUIPE'],
    'viewOSTecnico'              => ['controller' => 'TecnicoController', 'method' => 'visualizar', 'acesso' => 'EQUIPE'],
    'atualizarStatusOSTecnico'   => ['controller' => 'TecnicoController', 'method' => 'atualizarStatus', 'acesso' => 'EQUIPE'],
    'enviarEvidenciaOS'          => ['controller' => 'TecnicoController', 'method' => 'enviarEvidencia', 'acesso' => 'EQUIPE'],
    'verEvidenciaOS'             => ['controller' => 'TecnicoController', 'method' => 'verEvidencia', 'acesso' => 'EQUIPE'],
    'finalizarOSTecnico'         => ['controller' => 'TecnicoController', 'method' => 'finalizar', 'acesso' => 'EQUIPE'],
    'adicionarPecaOSTecnico'     => ['controller' => 'TecnicoController', 'method' => 'adicionarPeca', 'acesso' => 'EQUIPE'],
    'confirmarServicoOSTecnico'  => ['controller' => 'TecnicoController', 'method' => 'confirmarServico', 'acesso' => 'EQUIPE'],
    'salvarObservacoesOSTecnico' => ['controller' => 'TecnicoController', 'method' => 'salvarObservacoes', 'acesso' => 'EQUIPE'],

    /* Relatórios */
    'listRel' => ['controller' => 'RelatorioController', 'method' => 'listRelatorio', 'acesso' => 'ADMIN'],

    /* Agenda de serviços */
    'listAgendamentos'   => ['controller' => 'AgendamentoController', 'method' => 'list', 'acesso' => 'EQUIPE'],
    'saveAgendamento'    => ['controller' => 'AgendamentoController', 'method' => 'save', 'acesso' => 'EQUIPE'],
    'editAgendamento'    => ['controller' => 'AgendamentoController', 'method' => 'edit', 'acesso' => 'EQUIPE'],
    'statusAgendamento'  => ['controller' => 'AgendamentoController', 'method' => 'status', 'acesso' => 'EQUIPE'],
    'deleteAgendamento'  => ['controller' => 'AgendamentoController', 'method' => 'delete', 'acesso' => 'ADMIN'],
    'gerarOsAgendamento' => ['controller' => 'AgendamentoController', 'method' => 'gerarOrdemServico', 'acesso' => 'EQUIPE'],

    /* Senha */
    'recuperar-senha' => ['controller' => 'UserController', 'method' => 'recuperarSenha', 'acesso' => 'PUBLICO'],
    'redefinir-senha' => ['controller' => 'UserController', 'method' => 'redefinirSenha', 'acesso' => 'PUBLICO'],

    /* Movimentações de Estoque */
    'listMovimentacaoEstoque'   => ['controller' => 'MovimentacaoEstoqueController', 'method' => 'list', 'acesso' => 'EQUIPE'],
    'saveMovimentacaoEstoque'   => ['controller' => 'MovimentacaoEstoqueController', 'method' => 'save', 'acesso' => 'EQUIPE'],
    'estornarMovimentacaoEstoque' => ['controller' => 'MovimentacaoEstoqueController', 'method' => 'estornar', 'acesso' => 'ADMIN'],

    /* Ler placa */
    'lerPlaca' => ['controller' => 'PlacaController', 'method' => 'ler', 'acesso' => 'EQUIPE'],
    'consultarPlacaVeiculo' => ['controller' => 'ConsultaVeiculoController', 'method' => 'consultar', 'acesso' => 'EQUIPE'],

    /* Caixa (abertura/fechamento) */
    'abrirCaixa'  => ['controller' => 'CaixaController', 'method' => 'abrir', 'acesso' => 'EQUIPE'],
    'fecharCaixa' => ['controller' => 'CaixaController', 'method' => 'fechar', 'acesso' => 'EQUIPE'],
    'listCaixa'   => ['controller' => 'CaixaController', 'method' => 'list', 'acesso' => 'EQUIPE'],

    /* Caixa (livro-caixa: lançamentos de entrada/saída) */
    'caixaMovimentacoes'         => ['controller' => 'CaixaController', 'method' => 'movimentacoes', 'acesso' => 'EQUIPE'],
    'registrarMovimentacaoCaixa' => ['controller' => 'CaixaController', 'method' => 'registrarMovimentacao', 'acesso' => 'EQUIPE'],

    /* Caixa (relatório por período: diário/semanal/mensal/anual) */
    'relatorioCaixa'    => ['controller' => 'CaixaController', 'method' => 'relatorio', 'acesso' => 'ADMIN'],
    'relatorioCaixaPdf' => ['controller' => 'CaixaController', 'method' => 'exportarRelatorioPdf', 'acesso' => 'ADMIN'],

];
