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

    // Ordens de serviço (com orçamento) — equipe toda
    'ordens'          => ['OrdensController', 'lista', Permissao::EQUIPE],
    'os-nova'         => ['OrdensController', 'nova', Permissao::EQUIPE],
    'os-criar'        => ['OrdensController', 'criar', Permissao::EQUIPE],
    'os'              => ['OrdensController', 'ver', Permissao::EQUIPE],
    'os-dados'        => ['OrdensController', 'dados', Permissao::EQUIPE],
    'os-item'         => ['OrdensController', 'item', Permissao::EQUIPE],
    'os-acao'         => ['OrdensController', 'acao', Permissao::EQUIPE],
    'os-imprimir'     => ['OrdensController', 'imprimir', Permissao::EQUIPE],
    'os-veiculos'     => ['OrdensController', 'veiculosDoCliente', Permissao::EQUIPE],

    // Anexos — fotos e documentos da OS: equipe; comprovante de conta: ADMIN (conferido também ao abrir)
    'os-anexar'       => ['AnexosController', 'enviarOs', Permissao::EQUIPE],
    'conta-anexar'    => ['AnexosController', 'enviarConta', Permissao::ADMIN],
    'anexo'           => ['AnexosController', 'abrir', Permissao::EQUIPE],

    // Agenda — equipe toda; excluir só ADMIN
    'agenda'          => ['AgendaController', 'lista', Permissao::EQUIPE],
    'agenda-form'     => ['AgendaController', 'form', Permissao::EQUIPE],
    'agenda-salvar'   => ['AgendaController', 'salvar', Permissao::EQUIPE],
    'agenda-marcar'   => ['AgendaController', 'marcar', Permissao::EQUIPE],
    'agenda-os'       => ['AgendaController', 'gerarOs', Permissao::EQUIPE],
    'agenda-excluir'  => ['AgendaController', 'excluir', Permissao::ADMIN],

    // Cadastros — clientes e veículos: equipe toda; excluir só ADMIN
    'clientes'        => ['ClientesController', 'lista', Permissao::EQUIPE],
    'cliente'         => ['ClientesController', 'ver', Permissao::EQUIPE],
    'cliente-form'    => ['ClientesController', 'form', Permissao::EQUIPE],
    'cliente-salvar'  => ['ClientesController', 'salvar', Permissao::EQUIPE],
    'cliente-ativo'   => ['ClientesController', 'ativo', Permissao::EQUIPE],
    'cliente-excluir' => ['ClientesController', 'excluir', Permissao::ADMIN],

    'veiculos'        => ['VeiculosController', 'lista', Permissao::EQUIPE],
    'veiculo'         => ['VeiculosController', 'ver', Permissao::EQUIPE],
    'veiculo-form'    => ['VeiculosController', 'form', Permissao::EQUIPE],
    'veiculo-salvar'  => ['VeiculosController', 'salvar', Permissao::EQUIPE],
    'veiculo-ativo'   => ['VeiculosController', 'ativo', Permissao::EQUIPE],
    'veiculo-excluir' => ['VeiculosController', 'excluir', Permissao::ADMIN],
    'veiculo-modelos' => ['VeiculosController', 'modelos', Permissao::EQUIPE],

    // Peças e serviços — consultar: equipe (sem custo); cadastrar/editar: ADMIN
    'produtos'        => ['ProdutosController', 'lista', Permissao::EQUIPE],
    'produto'         => ['ProdutosController', 'ver', Permissao::EQUIPE],
    'produto-form'    => ['ProdutosController', 'form', Permissao::ADMIN],
    'produto-salvar'  => ['ProdutosController', 'salvar', Permissao::ADMIN],
    'produto-ativo'   => ['ProdutosController', 'ativo', Permissao::ADMIN],
    'produto-excluir' => ['ProdutosController', 'excluir', Permissao::ADMIN],

    // Caixa — a equipe opera; o histórico é do ADMIN
    'caixa'           => ['CaixaController', 'atual', Permissao::EQUIPE],
    'caixa-abrir'     => ['CaixaController', 'abrir', Permissao::EQUIPE],
    'caixa-movimento' => ['CaixaController', 'movimentar', Permissao::EQUIPE],
    'caixa-fechar'    => ['CaixaController', 'fechar', Permissao::EQUIPE],
    'caixas'          => ['CaixaController', 'historico', Permissao::ADMIN],
    'caixa-ver'       => ['CaixaController', 'ver', Permissao::ADMIN],

    // Financeiro — só ADMIN (o recebimento de OS é feito na própria OS, pela equipe)
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
