<?php

/**
 * Tabela de rotas: 'endereco' => [Controller, método, nível de acesso].
 *
 * O nível é obrigatório (ver Permissao). O front controller confere o nível ANTES de chamar o
 * controller; esconder o link no menu nunca é a proteção. O teste RotasTest verifica que toda rota
 * aponta para um método que existe e tem um nível válido.
 */
return [
    // Sem login
    'login'           => ['AuthController', 'login', Permissao::PUBLICO],
    'recuperar-senha' => ['AuthController', 'recuperarSenha', Permissao::PUBLICO],
    'redefinir-senha' => ['AuthController', 'redefinirSenha', Permissao::PUBLICO],
    'acompanhar'      => ['OrdensController', 'acompanhar', Permissao::PUBLICO],   // link de acompanhamento do cliente

    // Qualquer pessoa logada
    'sair'            => ['AuthController', 'sair', Permissao::LOGADO],

    // Oficina
    'inicio'          => ['InicioController', 'inicio', Permissao::EQUIPE],
    'perfil'          => ['UsuariosController', 'perfil', Permissao::EQUIPE],
    'perfil-salvar'   => ['UsuariosController', 'salvarPerfil', Permissao::EQUIPE],
    'notificacoes'    => ['NotificacoesController', 'lista', Permissao::EQUIPE],
    'notificacao'     => ['NotificacoesController', 'abrir', Permissao::EQUIPE],
    'notificacoes-ler-todas' => ['NotificacoesController', 'lerTodas', Permissao::EQUIPE],

    // Área do técnico (celular): só as OS em que ele está, conferidas a cada requisição (ServicoTecnico)
    'servicos'        => ['TecnicoController', 'painel', Permissao::TECNICO],
    'servico'         => ['TecnicoController', 'ver', Permissao::TECNICO],
    'servico-acao'    => ['TecnicoController', 'acao', Permissao::TECNICO],
    'servico-material'=> ['TecnicoController', 'material', Permissao::TECNICO],
    'pedidos'         => ['TecnicoController', 'pedidos', Permissao::TECNICO],
    'pedido-criar'    => ['TecnicoController', 'criarPedido', Permissao::TECNICO],

    // Solicitações de material dos técnicos — o ADMIN analisa e responde
    'solicitacoes'         => ['SolicitacoesController', 'lista', Permissao::ADMIN],
    'solicitacao-responder'=> ['SolicitacoesController', 'responder', Permissao::ADMIN],

    // Ordens de serviço (com orçamento) — escritório (ADMIN e ATENDENTE); validar a execução do técnico: só ADMIN (conferido no controller)
    'ordens'          => ['OrdensController', 'lista', Permissao::ATENDIMENTO],
    'os-nova'         => ['OrdensController', 'nova', Permissao::ATENDIMENTO],
    'os-criar'        => ['OrdensController', 'criar', Permissao::ATENDIMENTO],
    'os'              => ['OrdensController', 'ver', Permissao::ATENDIMENTO],
    'os-dados'        => ['OrdensController', 'dados', Permissao::ATENDIMENTO],
    'os-item'         => ['OrdensController', 'item', Permissao::ATENDIMENTO],
    'os-acao'         => ['OrdensController', 'acao', Permissao::ATENDIMENTO],
    'os-mover'        => ['OrdensController', 'mover', Permissao::ATENDIMENTO],   // Kanban: arrastar o cartão
    'os-imprimir'     => ['OrdensController', 'imprimir', Permissao::ATENDIMENTO],
    'os-veiculos'     => ['OrdensController', 'veiculosDoCliente', Permissao::ATENDIMENTO],

    // Anexos — fotos e documentos da OS: equipe (o técnico, só nas OS em que está); comprovante de conta: ADMIN (conferido também ao abrir)
    'os-anexar'       => ['AnexosController', 'enviarOs', Permissao::EQUIPE],
    'conta-anexar'    => ['AnexosController', 'enviarConta', Permissao::ADMIN],
    'anexo'           => ['AnexosController', 'abrir', Permissao::EQUIPE],

    // Agenda — escritório; excluir só ADMIN
    'agenda'          => ['AgendaController', 'lista', Permissao::ATENDIMENTO],
    'agenda-form'     => ['AgendaController', 'form', Permissao::ATENDIMENTO],
    'agenda-salvar'   => ['AgendaController', 'salvar', Permissao::ATENDIMENTO],
    'agenda-marcar'   => ['AgendaController', 'marcar', Permissao::ATENDIMENTO],
    'agenda-os'       => ['AgendaController', 'gerarOs', Permissao::ATENDIMENTO],
    'agenda-excluir'  => ['AgendaController', 'excluir', Permissao::ADMIN],

    // Cadastros — clientes e veículos: escritório; excluir só ADMIN
    'clientes'        => ['ClientesController', 'lista', Permissao::ATENDIMENTO],
    'cliente'         => ['ClientesController', 'ver', Permissao::ATENDIMENTO],
    'cliente-form'    => ['ClientesController', 'form', Permissao::ATENDIMENTO],
    'cliente-salvar'  => ['ClientesController', 'salvar', Permissao::ATENDIMENTO],
    'cliente-ativo'   => ['ClientesController', 'ativo', Permissao::ATENDIMENTO],
    'cliente-excluir' => ['ClientesController', 'excluir', Permissao::ADMIN],

    'veiculos'        => ['VeiculosController', 'lista', Permissao::ATENDIMENTO],
    'veiculo'         => ['VeiculosController', 'ver', Permissao::ATENDIMENTO],
    'veiculo-form'    => ['VeiculosController', 'form', Permissao::ATENDIMENTO],
    'veiculo-salvar'  => ['VeiculosController', 'salvar', Permissao::ATENDIMENTO],
    'veiculo-ativo'   => ['VeiculosController', 'ativo', Permissao::ATENDIMENTO],
    'veiculo-excluir' => ['VeiculosController', 'excluir', Permissao::ADMIN],
    'veiculo-modelos' => ['VeiculosController', 'modelos', Permissao::ATENDIMENTO],

    // Peças e serviços — consultar: escritório (sem custo); cadastrar/editar: ADMIN
    'produtos'        => ['ProdutosController', 'lista', Permissao::ATENDIMENTO],
    'produto'         => ['ProdutosController', 'ver', Permissao::ATENDIMENTO],
    'produto-form'    => ['ProdutosController', 'form', Permissao::ADMIN],
    'produto-salvar'  => ['ProdutosController', 'salvar', Permissao::ADMIN],
    'produto-ativo'   => ['ProdutosController', 'ativo', Permissao::ADMIN],
    'produto-excluir' => ['ProdutosController', 'excluir', Permissao::ADMIN],

    // Caixa — o balcão (ADMIN e ATENDENTE) opera; o técnico não mexe em dinheiro; o histórico é do ADMIN
    'caixa'           => ['CaixaController', 'atual', Permissao::BALCAO],
    'caixa-abrir'     => ['CaixaController', 'abrir', Permissao::BALCAO],
    'caixa-movimento' => ['CaixaController', 'movimentar', Permissao::BALCAO],
    'caixa-fechar'    => ['CaixaController', 'fechar', Permissao::BALCAO],
    'caixas'          => ['CaixaController', 'historico', Permissao::ADMIN],
    'caixa-ver'       => ['CaixaController', 'ver', Permissao::ADMIN],

    // Financeiro — só ADMIN (o recebimento de OS é feito na própria OS, pelo balcão)
    'financeiro'      => ['FinanceiroController', 'lista', Permissao::ADMIN],
    'conta'           => ['FinanceiroController', 'ver', Permissao::ADMIN],
    'conta-form'      => ['FinanceiroController', 'form', Permissao::ADMIN],
    'conta-salvar'    => ['FinanceiroController', 'salvar', Permissao::ADMIN],
    'conta-editar'    => ['FinanceiroController', 'editar', Permissao::ADMIN],
    'conta-baixar'    => ['FinanceiroController', 'baixar', Permissao::ADMIN],
    'conta-estornar'  => ['FinanceiroController', 'estornar', Permissao::ADMIN],
    'conta-cancelar'  => ['FinanceiroController', 'cancelar', Permissao::ADMIN],
    'comissoes'       => ['FinanceiroController', 'comissoes', Permissao::ADMIN],
    'comissao-gerar'  => ['FinanceiroController', 'gerarComissao', Permissao::ADMIN],

    // Estoque — só ADMIN (envolve custo)
    'estoque'          => ['EstoqueController', 'livro', Permissao::ADMIN],
    'estoque-entrada'  => ['EstoqueController', 'entrada', Permissao::ADMIN],
    'estoque-ajuste'   => ['EstoqueController', 'ajuste', Permissao::ADMIN],
    'estoque-estornar' => ['EstoqueController', 'estornar', Permissao::ADMIN],

    'fornecedores'       => ['FornecedoresController', 'lista', Permissao::ADMIN],
    'fornecedor'         => ['FornecedoresController', 'ver', Permissao::ADMIN],
    'fornecedor-form'    => ['FornecedoresController', 'form', Permissao::ADMIN],
    'fornecedor-salvar'  => ['FornecedoresController', 'salvar', Permissao::ADMIN],
    'fornecedor-ativo'   => ['FornecedoresController', 'ativo', Permissao::ADMIN],
    'fornecedor-excluir' => ['FornecedoresController', 'excluir', Permissao::ADMIN],

    // Relatórios e configurações da oficina — só ADMIN (o logotipo sai na impressão, que é da equipe)
    'relatorios'      => ['RelatoriosController', 'painel', Permissao::ADMIN],
    'configuracoes'   => ['ConfiguracoesController', 'form', Permissao::ADMIN],
    'configuracoes-salvar' => ['ConfiguracoesController', 'salvar', Permissao::ADMIN],
    'oficina-logo'    => ['ConfiguracoesController', 'logo', Permissao::EQUIPE],

    'usuarios'        => ['UsuariosController', 'lista', Permissao::ADMIN],
    'usuario-form'    => ['UsuariosController', 'form', Permissao::ADMIN],
    'usuario-salvar'  => ['UsuariosController', 'salvar', Permissao::ADMIN],
    'usuario-ativo'   => ['UsuariosController', 'ativo', Permissao::ADMIN],
    'usuario-excluir' => ['UsuariosController', 'excluir', Permissao::ADMIN],

    // Plataforma (equipe do Mecanize)
    'plataforma'         => ['PlataformaController', 'lista', Permissao::PLATAFORMA],
    'plataforma-oficina' => ['PlataformaController', 'ver', Permissao::PLATAFORMA],
    'plataforma-nova'    => ['PlataformaController', 'nova', Permissao::PLATAFORMA],
    'plataforma-criar'   => ['PlataformaController', 'criar', Permissao::PLATAFORMA],
    'plataforma-licenca' => ['PlataformaController', 'licenca', Permissao::PLATAFORMA],
];
