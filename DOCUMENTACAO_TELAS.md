# Documentação Completa de Telas — Mecanize

> Sistema de gestão de oficina mecânica (projeto de TCC).
> Documento gerado em **10/11/2026** a partir da leitura integral das **68 views** em `app/views/` e das rotas em `config/routes.php`.

---

## Sumário

1. [Visão geral do sistema](#1-visão-geral-do-sistema)
2. [Arquitetura e convenções](#2-arquitetura-e-convenções)
3. [Perfis de acesso e permissões](#3-perfis-de-acesso-e-permissões)
4. [Índice de telas por módulo](#4-índice-de-telas-por-módulo)
5. [Mapa completo de rotas](#5-mapa-completo-de-rotas)
6. [Inventário de modais e painéis](#6-inventário-de-modais-e-painéis)
7. [Componentes e parciais reutilizáveis](#7-componentes-e-parciais-reutilizáveis)
8. Catálogo detalhado por módulo:
   [Site](#site) · [Usuários](#usuários) · [Clientes](#clientes) · [Veículos](#veículos) · [Agendamentos](#agendamentos) · [Orçamentos](#orçamentos) · [Ordens de Serviço](#ordens-de-serviço) · [Produtos](#produtos) · [Categorias](#categorias) · [Movimentação de Estoque](#movimentação-de-estoque) · [Documentos](#documentos) · [Caixa](#caixa) · [Relatórios](#relatórios) · [Layout](#layout)

---

## 1. Visão geral do sistema

O **Mecanize** é um sistema web de gestão para oficinas mecânicas. Ele cobre todo o fluxo de atendimento:

- **Cadastros base:** clientes (PF/PJ), veículos (com leitura de placa por foto), produtos, categorias e usuários.
- **Operação:** agenda de serviços, orçamentos (que ao serem aprovados viram OS), ordens de serviço (lista + Kanban com arrastar-e-soltar), movimentação de estoque e documentos de serviços finalizados.
- **Financeiro:** caixa (abertura/fechamento, livro-caixa de lançamentos) e relatórios de caixa por período.
- **Gestão:** dashboard com gráficos, relatórios e insights, painel modo TV para o salão, e rastreamento público de OS/orçamento por link.

**Tecnologias de front-end usadas nas telas:** Chart.js (gráficos), IMask (máscaras de CPF/CNPJ/telefone/placa/moeda), Tom Select (selects avançados), Bootstrap Icons, Dompdf (geração de PDF), além de integrações como link de WhatsApp, leitura de placa por foto (OCR/IA) e busca de endereço por CEP (ViaCEP).

---

## 2. Arquitetura e convenções

**Padrão MVC artesanal** organizado em `app/controllers`, `app/models`, `app/views` e `app/core`.

- **Roteador:** `public/index.php` + `config/routes.php`. As URLs têm o formato `?url=<rota>` (os links nas telas usam a forma relativa `./<rota>`, ex.: `./listClientes`).
- **Layout:** o método `Controller::view()` injeta `layout/header.php` + a view + `layout/footer.php`, **exceto**:
  - Views cujo nome começa com **`_`** (fragmentos carregados por `fetch`/AJAX) — renderizadas **sem** layout.
  - Views da lista *noLayout* (`Usuarios/login`, `Site/termos`, `Site/privacidade`, `Site/painel`, `Site/rastreio`) — páginas HTML completas e próprias.
- **Modais como página:** muitas telas de **criar** e **visualizar** são renderizadas como um **modal overlay** que ocupa a tela inteira sobre um fundo escurecido (ex.: `saveCliente`, `viewCliente`, `saveVeiculo`, `saveProduto`, `saveOrcamento`, `saveOrdemServico`, `viewOrdemServico`). As telas de **editar**, em geral, são páginas comuns em grid de cards.
- **CSRF:** POSTs usam `csrf_field()`; o token também vai em `<meta name="csrf-token">` (`Csrf::token()`) e o `csrf.js` injeta o token em formulários que não têm o campo.
- **Convenção de links:** listagens costumam usar `./rota&id=X` (com `&`), enquanto telas de detalhe/edição usam `./rota?id=X`.
- **Tema claro/escuro:** aplicado antes da renderização via `localStorage` (`mecanize-theme`) + `prefers-color-scheme` (evita flash). Os gráficos Chart.js leem as cores do tema em tempo real.
- **Assets locais:** servidos de `vendor/`/`public/assets` com `?v=time()` (decisão de segurança — não usa CDN).

---

## 3. Perfis de acesso e permissões

Definidos em `app/core/Controller.php` (`requireRole()`), com o gate de login em `app/core/auth.php`. São **três perfis**:

| Perfil | Quem é | O que faz |
|---|---|---|
| **ADMIN** | Dono/gerente da oficina | Acesso a tudo. |
| **ATENDENTE** | Recepção/balcão | Atende o cliente, cadastra cliente e veículo, monta e aprova orçamento, marca na agenda, opera o caixa e fecha a OS recebendo o pagamento. |
| **TÉCNICO** | Mecânico | Vê cliente, veículo, produto e agenda; edita a OS (diagnóstico, peças aplicadas) e move o Kanban; dá baixa de peça no estoque. **Não** mexe em dinheiro, **não** aprova orçamento e **não** cadastra cliente. |

**Regra geral:** *ver, todos veem; quem pode alterar é que muda de cargo para cargo.* Listagens, telas de visualização e PDFs ficam sem `requireRole()` (abertos aos três). **Excluir** exige **ADMIN** em todas as telas. O controle efetivo é feito por `requireRole()` nos controllers — esconder o link no menu não é controle de acesso.

Itens exclusivos de **ADMIN** no menu: Relatórios, Relatório de Caixa, Usuários, Documentos. O menu **Caixa** (e o indicador de caixa na topbar) aparece só para ADMIN/ATENDENTE (técnico não vê).

---

## 4. Índice de telas por módulo

| Módulo | Telas | Rota principal |
|---|---|---|
| **Site** | 9 (Dashboard, Painel TV, Rastreio público, Termos, Privacidade + 4 telas de erro) | `./home` |
| **Usuários** | 9 (Login, Lista, tabela parcial, Criar, Editar, Visualizar, Perfil, Recuperar/Redefinir senha) | `./listUsuarios` |
| **Clientes** | 5 (Lista, tabela parcial, Criar, Editar, Visualizar) | `./listClientes` |
| **Veículos** | 6 (Lista, Criar, Editar, Visualizar + 2 parciais de dados) | `./listVeiculos` |
| **Agendamentos** | 4 (Agenda, form parcial, Criar, Editar) | `./listAgendamentos` |
| **Orçamentos** | 6 (Lista, Criar, Editar, Visualizar, Imprimir, PDF) | `./listOrcamentos` |
| **Ordens de Serviço** | 6 (Lista, Kanban, Criar, Editar, Visualizar, PDF) | `./listOrdemServico` |
| **Produtos** | 5 (Lista, Criar, Editar, Visualizar, delete vazio) | `./listProduto` |
| **Categorias** | 3 (Lista, Criar, Editar) | `./listCategoria` |
| **Movimentação de Estoque** | 3 (Lista, Criar, Editar) | `./listMovimentacaoEstoque` |
| **Documentos** | 2 (Lista de OS finalizadas, Gerar documento) | `./listDocumentos` |
| **Caixa** | 6 (Histórico, Abrir, Fechar, Lançamentos, Relatório, PDF) | `./listCaixa` |
| **Relatórios** | 1 (Relatórios e Insights) | `./listRel` |
| **Layout** | 3 (Header/Sidebar, Footer, Alerta parcial) | — |
| **Total** | **68** | |

---

## 5. Mapa completo de rotas

Todas as rotas são acessadas via `?url=<rota>` (`config/routes.php`).

| Rota | Controller@método | Observação |
|---|---|---|
| `home` | SiteController@index | Dashboard |
| `painel` | SiteController@painel | Painel modo TV |
| `termos` | SiteController@termos | Pública |
| `privacidade` | SiteController@privacidade | Pública |
| `rastrear` | RastreioController@ver | Pública (token) |
| `erro404` | ErrorController@erro404 | Tela de erro |
| `erroPermissao` | ErrorController@semPermissao | Tela de erro |
| `erroIndisponivel` | ErrorController@indisponivel | Tela de erro |
| `erroConexao` | ErrorController@semConexao | Tela de erro |
| `erroTempo` | ErrorController@tempoEsgotado | Tela de erro |
| `login` / `logout` | UserController@login / @logout | Autenticação |
| `perfil` / `updateSenha` | UserController@perfil / @updateSenha | Perfil próprio |
| `recuperar-senha` / `redefinir-senha` | UserController@recuperarSenha / @redefinirSenha | Senha |
| `listUsuarios` | UserController@list | Lista de usuários |
| `saveUsuario` / `editUsuario` | UserController@save / @edit | Criar/editar usuário |
| `viewUsuario` / `deleteUsuario` | UserController@visualizar / @delete | Ver/excluir usuário |
| `toggleStatusUsuario` | UserController@toggleStatus | Ativar/desativar |
| `exportUsuarios` | UserController@exportar | Exportação |
| `listUsuariosNovos30d` / `usuarioPerfilJson` | UserController@novos30dList / @perfilJson | Painel novos (JSON) |
| `listClientes` | ClienteController@list | Lista de clientes |
| `saveCliente` / `editCliente` | ClienteController@save / @edit | Criar/editar cliente |
| `viewCliente` / `deleteCliente` | ClienteController@visualizar / @delete | Ver/desativar cliente |
| `listClientesDesativados` / `reativarCliente` | ClienteController@listDesativados / @reativar | Inativos |
| `vipList` / `vipPerfil` / `novosMesList` | ClienteController@vipList / @vipPerfil / @novosMesList | Painéis (JSON) |
| `listVeiculos` | VeiculoController@list | Lista de veículos |
| `saveVeiculo` / `editVeiculo` | VeiculoController@save / @edit | Criar/editar veículo |
| `viewVeiculo` / `deleteVeiculo` | VeiculoController@visualizar / @delete | Ver/excluir veículo |
| `listVeiculosNovosMes` / `veiculoPerfil` | VeiculoController@novosMesList / @perfilJson | Painel (JSON) |
| `modelosPorMarca` / `criarModelo` | ModeloVeiculoController@modelosPorMarca / @criarModelo | AJAX (modelos) |
| `listCategoria` | CategoriaController@list | Lista de categorias |
| `saveCategoria` / `editCategoria` / `deleteCategoria` | CategoriaController@save / @edit / @delete | CRUD categoria |
| `listProduto` | ProdutoController@list | Lista de produtos |
| `saveProduto` / `editProduto` | ProdutoController@save / @edit | Criar/editar produto |
| `viewProduto` / `deleteProduto` | ProdutoController@visualizar / @arquivar | Ver/arquivar produto |
| `listOrcamentos` | OrcamentoController@list | Lista de orçamentos |
| `novoOrcamento` / `saveOrcamento` | OrcamentoController@save / @saveOrcamento | Exibir/gravar orçamento |
| `editOrcamento` / `viewOrcamento` | OrcamentoController@edit / @visualizar | Editar/ver orçamento |
| `aprovarOrcamento` / `recusarOrcamento` | OrcamentoController@aprovarOrcamento / @recusarOrcamento | Decisão |
| `imprimirOrcamento` / `exportOrcamentoPdf` | OrcamentoController@imprimir / @exportarPdf | Impressão/PDF |
| `listOrdemServico` | OrdemServicoController@list | Lista de OS |
| `saveOrdemServico` / `editOrdemServico` | OrdemServicoController@save / @edit | Criar/editar OS |
| `viewOrdemServico` / `deleteOrdemServico` | OrdemServicoController@visualizar / @delete | Ver/excluir OS |
| `concluirOrdemServico` | OrdemServicoController@concluir | Conclusão + caixa |
| `exportOrdemServicoPdf` | OrdemServicoController@exportarPdf | PDF |
| `kanbanOrdemServico` / `moverStatusOrdemServico` | OrdemServicoController@kanban / @moverStatus | Kanban + drag |
| `listAgendamentos` | AgendamentoController@list | Agenda |
| `saveAgendamento` / `editAgendamento` | AgendamentoController@save / @edit | Criar/editar |
| `statusAgendamento` / `deleteAgendamento` | AgendamentoController@status / @delete | Status/excluir |
| `gerarOsAgendamento` | AgendamentoController@gerarOrdemServico | Gerar OS |
| `listMovimentacaoEstoque` | MovimentacaoEstoqueController@list | Lista |
| `saveMovimentacaoEstoque` / `editMovimentacaoEstoque` / `deleteMovimentacaoEstoque` | MovimentacaoEstoqueController@save / @edit / @delete | CRUD |
| `listDocumentos` | DocumentoController@list | OS finalizadas |
| `saveDocumento` / `finalizarDocumento` | DocumentoController@save / @finalizar | Gerar documento |
| `lerPlaca` | PlacaController@ler | Leitura de placa (foto) |
| `listCaixa` | CaixaController@list | Histórico de caixa |
| `abrirCaixa` / `fecharCaixa` | CaixaController@abrir / @fechar | Abertura/fechamento |
| `caixaMovimentacoes` / `registrarMovimentacaoCaixa` | CaixaController@movimentacoes / @registrarMovimentacao | Livro-caixa |
| `relatorioCaixa` / `relatorioCaixaPdf` | CaixaController@relatorio / @exportarRelatorioPdf | Relatório/PDF |
| `listRel` | RelatorioController@listRelatorio | Relatórios e insights |

---

## 6. Inventário de modais e painéis

### Modais (overlay)

| Modal (`#id`) | Tela | Título | Função |
|---|---|---|---|
| `#modalCriarCliente` | saveCliente | Novo Cliente | Cadastro de cliente (PF/PJ, CEP, balcão) |
| `#modalViewCliente` | viewCliente | Cliente — <nome> | Visualização somente leitura |
| `#modalCriarVeiculo` | saveVeiculo | Novo Veículo | Cadastro de veículo (leitura de placa por foto) |
| `#modalViewVeiculo` | viewVeiculo | Veículo — <marca modelo> | Visualização + histórico (timeline) |
| `#modalCriarUsuario` | saveUsuario | Novo Usuário | Cadastro de usuário |
| `#modalViewUsuario` | viewUsuario | Usuário — <nome> | Visualização somente leitura |
| `#modalCriarOrcamento` | saveOrcamento | Novo Orçamento | Criação de orçamento (itens + financeiro) |
| `#orc-modal-catalogo` | saveOrcamento | Adicionar Produto/Serviço | Seleção de itens do catálogo |
| `#modal-aprovar` | listOrcamentos | Aprovar Orçamento | Aprova e **gera OS** (canvas de assinatura) |
| `#modal-recusar` | listOrcamentos | Recusar Orçamento | Registra recusa com motivo |
| `#modalCriarOrdemServico` | saveOrdemServico | Nova OS | Abertura de OS (itens + financeiro) |
| `#modal-catalogo` | saveOrdemServico / editOrdemServico | Adicionar Produto/Serviço | Seleção de itens do catálogo |
| `#modal-concluir` | listOrdemServico | Concluir Ordem de Serviço | Conclui OS e **lança no caixa** (forma de pagamento + assinatura) |
| `#modalViewOrdemServico` | viewOrdemServico | Ordem de Serviço — <nº> | Visualização somente leitura |
| `#modalCriarProduto` | saveProduto | Novo Produto | Cadastro de produto |
| `#modalCriarCategoria` | saveCategoria | Nova Categoria | Cadastro de categoria |
| `#modalNovaMovimentacao` | saveMovimentacaoEstoque | Nova Movimentação | Entrada/saída de estoque |
| `#modalDocumento` | saveDocumento | Gerar Orçamento *(sic)* | Conferência da OS antes de finalizar |
| `#modal-documento` | listDocumentos | Gerar Documento | Confere dados da OS selecionada |
| `#modal-final` | listDocumentos | Documento Gerado | Baixar PDF/Excel, imprimir, prévia |

### Painéis flutuantes (pop-overs, não são modais)

| Painel (`#id`) | Tela | Conteúdo |
|---|---|---|
| `#vipPanel` | listClientes | Clientes VIP & Recorrentes (lista + perfil) |
| `#panelNovosMes` | listClientes | Novos Clientes do Mês (lista + perfil) |
| `#panelVeiculosNovosMes` | listVeiculos | Novos Veículos do Mês (lista + perfil) |
| `#panelUsuariosNovos30d` | listUsuarios | Novos Usuários (30 dias) (lista + perfil) |

---

## 7. Componentes e parciais reutilizáveis

Arquivos com prefixo `_` (renderizados sem layout, incluídos por outras telas):

| Parcial | Papel |
|---|---|
| `layout/_alerta.php` | Alerta inline padronizado (erro/aviso/sucesso/info) usado em ~25 telas |
| `Clientes/_tabelaClientes.php` | Miolo da listagem de clientes (toolbar + tabela + paginação), servido também por fetch |
| `Usuarios/_tabelaUsuarios.php` | Miolo da listagem de usuários (toolbar + tabela + paginação) |
| `Agendamentos/_formAgendamento.php` | Campos compartilhados dos formulários de criar/editar agendamento |
| `Veiculos/_combustiveis.php` | Lista canônica de tipos de combustível |
| `Veiculos/_coresCarro.php` | Mapa canônico de cores (nome → hex) |
| `Site/erros/_autonomo.php` | Casca HTML autossuficiente das telas de erro (CSS/JS embutidos) |

---

# Catálogo detalhado por módulo

> Para cada tela: rota, tipo, objetivo, acesso/permissão, principais elementos (campos, colunas, botões), modais e observações técnicas.

---

## Site

### Dashboard — `app/views/Site/home.php`
- **Rota:** `?url=home` → `SiteController@index`
- **Tipo:** Visualização/Detalhe (dashboard com gráficos e tabela)
- **Objetivo:** Mostrar a visão geral da oficina: contadores de status das OS, gráficos de fluxo/receita/status, blocos financeiros e as últimas ordens de serviço.
- **Acesso/Permissão:** Verifica `$_SESSION['user_tipo'] === 'ADMIN'` para exibir (e só então enviar ao HTML os dados de) o card "Faturamento Mensal", a legenda/série de "Receita (R$)", a seção financeira (Faturamento por Mês, Ticket Médio & Peças Mais Vendidas) e a série de receita do gráfico de fluxo. Usa `$ehAdminDashboard` para não vazar valores financeiros no código-fonte para não-admins.
- **Principais elementos:**
  - Cabeçalho: título "Dashboard", subtítulo "Visão geral da oficina e das ordens de serviço." e botão "Nova OS" (→ `./saveOrdemServico`).
  - Stat cards: "OS Abertas" (delta "Aguardando início"), "Em Andamento" ("Serviços em execução"), "Aguardando Peça" ("OS paradas"), e só p/ ADMIN "Faturamento Mensal" (R$, "OS concluídas no mês").
  - Gráficos: card "Fluxo de OS × Receita" (badge "Últimos 7 dias", canvas `#graficoFluxo`, legenda "Ordens de Serviço" e, p/ ADMIN, "Receita (R$)"); card "Status das OS" (canvas `#graficoStatus`, donut com legenda: Concluídas, Em andamento, Pendentes, Canceladas, Abertas, cada uma com valor e %).
  - Seção ADMIN: card "Faturamento por Mês" (badge "Últimos 6 meses", canvas `#graficoFaturamentoMes`); card "Ticket Médio & Peças Mais Vendidas" (blocos "Ticket médio (mês)" e "Ticket médio (geral)"; lista de produtos mais vendidos com barra de progresso e "un.", ou "Nenhuma peça vendida ainda.").
  - Tabela "Últimas Ordens de Serviço" com link "Ver todas" (→ `./listOrdemServico`). Colunas: ID, Cliente, Veículo (com placa), Valor Total, Status (badge traduzido: Concluída/Em andamento/Aguardando peça/Cancelada/Aberta), Data, Ações. Ações por linha: "Visualizar" (→ `./viewOrdemServico&id=`) e "Editar" (→ `./editOrdemServico&id=`). Vazio: "Nenhuma ordem de serviço encontrada."
- **Modais:** Nenhum.
- **Observações:** Usa Chart.js (linha, donut com plugin de texto central `centerText`, e barras); lê CSS variables em tempo real para cores conforme tema (`data-theme`); `json_para_script()` para serializar dados ao JS; `number_format(...,2,',','.')` para moeda.

### Painel (TV) — `app/views/Site/painel.php`
- **Rota:** `?url=painel` → `SiteController@painel`
- **Tipo:** Visualização/Detalhe (tela cheia, modo TV — página HTML completa própria, fora do layout principal)
- **Objetivo:** Exibir em telão duas colunas de veículos — "Em Andamento" e "Pronto para Retirada" — para acompanhamento no salão da oficina.
- **Acesso/Permissão:** — (nenhuma checagem de cargo/login no arquivo).
- **Principais elementos:**
  - Header: marca "Mecanize", relógio `#relogio`, botão de tema `#temaToggle` (sol/lua).
  - Coluna "Em Andamento": cards com placa (maiúscula) e primeiro nome do cliente; vazio "Nenhum veículo em andamento."
  - Coluna "Pronto para Retirada": mesma estrutura; vazio "Nenhum veículo pronto no momento."
- **Modais:** Nenhum.
- **Observações:** `<meta http-equiv="refresh" content="30">` (auto-recarrega a cada 30s); tema com chave de localStorage própria `mecanize-theme-painel` (independente do resto do sistema); favicon dinâmico por `prefers-color-scheme`; relógio em JS a cada segundo. Aberto via sidebar em nova aba (`target="_blank"`).

### Política de Privacidade — `app/views/Site/privacidade.php`
- **Rota:** `?url=privacidade` → `SiteController@privacidade`
- **Tipo:** Público (sem login) — página legal HTML completa própria
- **Objetivo:** Apresentar a Política de Privacidade (LGPD) da plataforma Mecanize.
- **Acesso/Permissão:** — (página pública, linkada no rodapé do login).
- **Principais elementos:**
  - Barra de progresso de leitura (`#legalProgressFill`), nav fixo com logo (→ `./login`) e link "Voltar ao login".
  - Cabeçalho: "Política de Privacidade", "Última atualização: Julho de 2026".
  - Sumário (TOC) com 15 âncoras: 1. Introdução; 2. Quem somos; 3. Quais dados coletamos; 4. Finalidade do tratamento; 5. Base legal; 6. Compartilhamento; 7. Armazenamento dos dados; 8. Tempo de retenção; 9. Suspensão por inadimplência; 10. Direitos do titular; 11. Cookies; 12. Responsabilidades do cliente; 13. Atualizações desta Política; 14. Contato; 15. Disposições Finais.
  - Rodapé: "© <ano> Mecanize", links "Termos" (→ `./termos`) e "Privacidade".
- **Modais:** Nenhum.
- **Observações:** `<script>` de scroll-spy (destaca item ativo do TOC e preenche a barra de progresso); `date('Y')` no rodapé.

### Rastreamento público (OS/Orçamento) — `app/views/Site/rastreio.php`
- **Rota:** `?url=rastrear` (ex.: `./rastrear?token=...`) → `RastreioController@ver`
- **Tipo:** Público (sem login) — página HTML própria
- **Objetivo:** Permitir que o cliente acompanhe o andamento da sua Ordem de Serviço ou do seu orçamento por meio de um link com token, sem precisar logar.
- **Acesso/Permissão:** — (público via token). Espera `$tipo` ('os'|'orcamento') e `$dados`.
- **Principais elementos:**
  - Marca "Mecanize"; saudação "Olá, <primeiro nome>…"; veículo + placa.
  - Stepper de status. OS: "Aberta", "Em Andamento", "Aguardando Peça", "Concluída". Orçamento: "Criado", "Aguardando aprovação", decisão "Aprovado"/"Recusado"/"Aprovação".
  - Se cancelado, aviso vermelho: "Esta Ordem de Serviço foi cancelada." / "Este orçamento foi cancelado."
  - Rodapé: "Esta página é atualizada automaticamente conforme o andamento avança."
- **Modais:** Nenhum.
- **Observações:** Índice da etapa calculado em PHP; `htmlspecialchars` em todos os campos; tema segue `prefers-color-scheme`. Tela só de leitura.

### Termos de Uso — `app/views/Site/termos.php`
- **Rota:** `?url=termos` → `SiteController@termos`
- **Tipo:** Público (sem login) — página legal HTML completa própria
- **Objetivo:** Apresentar os Termos de Uso da plataforma SaaS Mecanize.
- **Acesso/Permissão:** — (página pública, linkada no rodapé do login).
- **Principais elementos:**
  - Mesma casca das páginas legais (barra de progresso, nav, "Voltar ao login"); cabeçalho "Termos de Uso" / "Última atualização: Julho de 2026".
  - Sumário (TOC) com 21 âncoras: 1. Introdução; 2. Sobre a Mecanize; 3. Aceitação dos Termos; 4. Descrição dos Serviços; 5. Cadastro e Conta; 6. Planos, Ativação e Assinaturas; 7. Cancelamento; 8. Pagamentos e Inadimplência; 9. Dados do Cliente; 10. Responsabilidades do Cliente; 11. Responsabilidades da Mecanize; 12. Disponibilidade da Plataforma; 13. Suporte Técnico; 14. White Label; 15. Propriedade Intelectual; 16. Privacidade e LGPD; 17. Suspensão e Encerramento; 18. Alterações destes Termos; 19. Legislação Aplicável; 20. Contato; 21. Disposições Finais.
  - Rodapé igual ao da privacidade.
- **Modais:** Nenhum.
- **Observações:** Mesmo `<script>` de scroll-spy/progresso; seção 16 linka a Política de Privacidade.

### Casca das telas de erro (autônoma) — `app/views/Site/erros/_autonomo.php`
- **Rota:** Parcial/include (sem rota direta). Casca (layout HTML completo) usada pelo `ErrorController`.
- **Tipo:** Layout / Tela de erro (casca)
- **Objetivo:** Prover um documento HTML autossuficiente (CSS e JS embutidos, sem dependências HTTP) para as telas de erro, que precisam funcionar mesmo com o servidor em problema. Inclui o arquétipo específico via `require $view`.
- **Acesso/Permissão:** — (exibida pelo fluxo de erro). Espera `$erro`, `$contexto`, `$view`.
- **Principais elementos:** `<title>` "Mecanize — <título do erro>"; marca "M / Mecanize"; inclui o arquétipo escolhido; injeta CSS via `Erro::css()` e JS via `Erro::js()`.
- **Modais:** Nenhum.
- **Observações:** `data-theme="light"` fixo; nada é buscado por HTTP (resiliência durante falhas do servidor).

### Erro de Acesso (arquétipo) — `app/views/Site/erros/acesso.php`
- **Rota:** Arquétipo renderizado por `_autonomo.php`. Disparado por `?url=erroPermissao` → `ErrorController@semPermissao` (e fluxos de não-autenticado / sessão expirada).
- **Tipo:** Tela de erro (barreira de identidade)
- **Objetivo:** Comunicar barreira de acesso em três variações: `sem-permissao` (403), `nao-autenticado` (401) e `sessao-expirada`.
- **Acesso/Permissão:** Lê `$_SESSION['logged']` (define `$autonomo`), `$_SESSION['user_nome']`/`user_tipo` (mostra identidade em `sem-permissao`). Rótulos: ADMIN→Administrador, TECNICO→Técnico, ATENDENTE→Atendente.
- **Principais elementos:**
  - Ilustração SVG por tipo (relógio = sessão expirada; crachá = não-autenticado; cadeado = sem-permissão) e selo ("403"/"401"/"Sessão expirada").
  - Ações: sessão-expirada → "Voltar e enviar de novo" + "Ir para o início"; não-autenticado → "Entrar novamente" (→ `./login?retorno=`); sem-permissao → "Voltar" + "Ir para o início" + "Ver meu perfil" (→ `./perfil`) + nota "Peça a um administrador para ajustar o seu perfil em Usuários."
- **Modais:** Nenhum.
- **Observações:** `$retorno` a partir de `$contexto['retorno']` ou `REQUEST_URI`; botões "voltar" acionados pelo `erros.js`.

### Erro Bússola / 404 (arquétipo) — `app/views/Site/erros/bussola.php`
- **Rota:** Arquétipo renderizado por `_autonomo.php`. Disparado por `?url=erro404` → `ErrorController@erro404` (404/registro inexistente/rastreio inválido).
- **Tipo:** Tela de erro (navegação)
- **Objetivo:** Tratar `nao-encontrado` (404), `registro-inexistente` e `rastreio-invalido`.
- **Acesso/Permissão:** Lê `$_SESSION['logged']` e `user_tipo`. Atalhos só para quem está logado; atalho "Relatórios" só ADMIN.
- **Principais elementos:**
  - Arte SVG: caixa vazia (registro removido) ou bússola (404).
  - Ações: rastreio-invalido → "Tentar de novo" + nota de contato; com `$voltar` → "Voltar para a lista"; padrão → "Ir para o início" + "Voltar à página anterior".
  - Bloco "Ir direto para" (logado): "Início", "Clientes", "Ordens", "Agenda" e, p/ ADMIN, "Relatórios".
- **Modais:** Nenhum.
- **Observações:** A tela de rastreio-inválido é vista pelo cliente (não oferece login/início).

### Erro de Sistema (arquétipo) — `app/views/Site/erros/sistema.php`
- **Rota:** Arquétipo renderizado por `_autonomo.php`. Disparado por `?url=erroIndisponivel` / `erroConexao` / `erroTempo` → `ErrorController@indisponivel` / `@semConexao` / `@tempoEsgotado`.
- **Tipo:** Tela de erro (falha do sistema)
- **Objetivo:** Tratar `falha-interna`, `indisponivel`, `sem-conexao` e `tempo-esgotado`.
- **Acesso/Permissão:** Lê `$_SESSION['logged']` e `$erro['autonomo']` (muda o botão secundário entre "Ir para o início" e "Ir para o login").
- **Principais elementos:**
  - Ícone SVG por tipo (sinal cortado, ampulheta, chave de boca, triângulo de atenção).
  - Em `falha-interna`: bloco "Código da ocorrência" + botão copiar + link "Falar com o suporte" (`mailto:suporte@mecanize.com.br`).
  - Recontagem automática (indisponível 30s; demais 15s): "Nova tentativa automática em <n>s".
- **Modais:** Nenhum.
- **Observações:** Nada expõe stack trace/caminho/mensagem de banco — detalhe técnico vai só pro log, associado ao código de ocorrência.

---

## Usuários

### Login — `app/views/Usuarios/login.php`
- **Rota:** `?url=login` → `UserController@login`
- **Tipo:** Público (sem login) — página HTML completa própria
- **Objetivo:** Autenticar o usuário no sistema.
- **Acesso/Permissão:** — (tela pública de entrada). Usa `Csrf::token()` em `<meta>`. Campo oculto `retorno` (validado contra rotas registradas).
- **Principais elementos:**
  - Lado esquerdo: logo + imagem de fundo.
  - Form `method="POST" action="./login"`: "E-mail" (`email`), "Senha" (`senha`, com botão olho `#togglesenha`), link "Esqueceu a senha?" (→ `./recuperar-senha`), botão "Entrar".
  - Alertas: `$error` via `_alerta.php`; `$aviso` em bloco amarelo.
  - Rodapé: "© <ano> Mecanize", "Termos", "Privacidade".
- **Modais:** Nenhum.
- **Observações:** Carrega `erros.css`; favicon dinâmico; `<script>` toggle de senha; carrega `csrf.js`.

### Lista de Usuários — `app/views/Usuarios/listUsuarios.php`
- **Rota:** `?url=listUsuarios` → `UserController@list` (exportar: `exportUsuarios`; novos 30d: `listUsuariosNovos30d`; perfil JSON: `usuarioPerfilJson`)
- **Tipo:** Listagem
- **Objetivo:** Listar, filtrar, paginar e gerenciar usuários (com cards de resumo e abas Ativos/Inativos).
- **Acesso/Permissão:** — no arquivo (restrição de ADMIN no controller/sidebar). Flashes de exclusão via `$_GET`.
- **Principais elementos:**
  - Cabeçalho "Usuários"; botões "Exportar" (→ `./exportUsuarios`) e "Novo Usuário" (→ `./saveUsuario`).
  - Cards: "Total de usuários", "Ativos", "Administradores", "Novos (30 dias)" (card clicável `#cardUsuariosNovos30d`).
  - Abas "Usuários Ativos"/"Usuários Inativos" com contador; tabela incluída de `_tabelaUsuarios.php` dentro de `#usuarios-conteudo`.
  - Mensagens de exclusão: vínculos, próprio ("Você não pode excluir a sua própria conta."), exclusão, sucesso.
- **Modais:** Nenhum modal clássico; painel deslizante `#panelUsuariosNovos30d` (lista + perfil).
- **Observações:** JS: painel de novos usuários (fetch `./listUsuariosNovos30d` e `./usuarioPerfilJson?id=`); troca de abas/paginação/busca sem recarregar (fragmento `?fragmento=1`, substitui só `#usuarios-conteudo`, trata redirect 302). Progressivo (funciona sem JS).

### Tabela de Usuários (parcial) — `app/views/Usuarios/_tabelaUsuarios.php`
- **Rota:** Parcial/include. Servido sozinho por `UserController@list` quando `?fragmento=1`.
- **Tipo:** Parcial/Componente (toolbar + tabela + paginação)
- **Objetivo:** Renderizar o miolo da listagem de usuários de forma independente.
- **Acesso/Permissão:** — (POSTs de status/exclusão incluem `csrf_field()`).
- **Principais elementos:**
  - Toolbar (GET `./listUsuarios`): busca `busca`, select `tipo` (Todos/Admin/Técnico/Atendente), "Filtrar", "Limpar", contador. Hidden `aba`.
  - Colunas: "Usuário" (avatar + nome + #id), "Contato" (e-mail), "Tipo" (badge), "Status" (badge clicável → POST `./toggleStatusUsuario`), "Criado em", "Ações".
  - Ações: "Visualizar" (→ `./viewUsuario&id=`), "Editar" (→ `./editUsuario&id=`), "Excluir" (POST `./deleteUsuario`, confirm "Deseja excluir este usuário? Esta ação não pode ser desfeita.").
  - Paginação ("Mostrando X de Y resultados") com janela compacta.
- **Modais:** Nenhum.
- **Observações:** Status e exclusão via POST (CSRF); avatar colorido por tipo; links de paginação com `data-usuarios-link`.

### Novo Usuário — `app/views/Usuarios/saveUsuario.php`
- **Rota:** `?url=saveUsuario` → `UserController@save`
- **Tipo:** Formulário (criar) — modal overlay
- **Objetivo:** Cadastrar um novo usuário.
- **Acesso/Permissão:** — no arquivo. Exibe `$error` se houver.
- **Principais elementos:**
  - Header "Novo Usuário"; fechar → `./listUsuarios`.
  - Form `#formCriarUsuario` (`./saveUsuario` POST). ① Dados do Usuário: "Nome Completo" * (`nome`), "Email" * (`email`), "Senha" * (`senha`, botão olho `#togglePassword`), "Tipo de Usuário" * (select: Administrador/Técnico/Atendente [padrão]). ② Configurações: "Status do Usuário" (radios Ativo=1 [marcado]/Inativo=0).
  - Footer: "Criado em"/"Usuário"; botões "Limpar", "Cancelar" (→ `./listUsuarios`), "Gravar".
- **Modais:** `#modalCriarUsuario` — "Novo Usuário" — cadastro (a tela inteira é o modal).
- **Observações:** JS mostra/oculta senha e fecha ao clicar fora. Não há `csrf_field()` visível neste form.

### Editar Usuário — `app/views/Usuarios/editUsuario.php`
- **Rota:** `?url=editUsuario` → `UserController@edit`
- **Tipo:** Formulário (editar)
- **Objetivo:** Atualizar informações/permissões de um usuário, ou excluí-lo.
- **Acesso/Permissão:** — no arquivo. Espera `$usuario`. `$error` via `_alerta.php`.
- **Principais elementos:**
  - Cabeçalho "Editar Usuário · <nome>"; pill de status; "Voltar" (→ `./listUsuarios`).
  - Form `./editUsuario` POST + hidden `id_usuario`. Card 1 Informações Pessoais: "Nome Completo", "E-mail", "Nova Senha" ("Deixe em branco para manter"). Card 2 Configurações: "Tipo de Usuário", "Status"; aviso de segurança sobre re-login ao trocar senha. Card 3 Resumo da Conta.
  - Footer: "Excluir Usuário" (submete `#form-excluir-usuario`), "Cancelar", "Salvar Alterações".
- **Modais:** Nenhum.
- **Observações:** Exclusão em `#form-excluir-usuario` (POST `./deleteUsuario`, `csrf_field()`, confirm "Deseja realmente excluir este usuário?...").

### Visualizar Usuário — `app/views/Usuarios/viewUsuario.php`
- **Rota:** `?url=viewUsuario` → `UserController@visualizar`
- **Tipo:** Visualização/Detalhe — modal overlay
- **Objetivo:** Exibir os dados completos de um usuário (somente leitura).
- **Acesso/Permissão:** — no arquivo. Espera `$dados_usuario`.
- **Principais elementos:** Header "Usuário — <nome>" + badge status; ① Identificação (ID, datas); ② Dados (avatar, nome, e-mail, badge de tipo); ③ Configurações (tipo, status). Footer: "Editar" (→ `./editUsuario&id=`), "Voltar".
- **Modais:** `#modalViewUsuario` (a tela inteira é o modal).
- **Observações:** Mapas de rótulos/cores por tipo/status; iniciais do avatar em PHP.

### Meu Perfil — `app/views/Usuarios/perfil.php`
- **Rota:** `?url=perfil` → `UserController@perfil` (senha: `updateSenha`)
- **Tipo:** Formulário (editar) — perfil próprio
- **Objetivo:** Atualizar dados pessoais e trocar a senha.
- **Acesso/Permissão:** — no arquivo. Espera `$usuario`, `$mensagemSucesso`/`$mensagemErro`.
- **Principais elementos:**
  - Cabeçalho "Meu Perfil"; alertas; cabeçalho de perfil (avatar, nome, e-mail, badge de tipo).
  - Card "Dados Pessoais" (POST mesma rota): "Nome Completo", "E-mail", "Telefone" (máscara), "Tipo de Usuário" (read-only); botão "Salvar Alterações".
  - Card "Segurança" (POST `./updateSenha`): "Senha Atual", "Nova Senha" (min 6), "Confirmar Nova Senha" (min 6); botão "Atualizar Senha".
- **Modais:** Nenhum.
- **Observações:** Toggle de senha; máscara de telefone (IMask); validação JS de coincidência de senha.

### Recuperar Senha — `app/views/Usuarios/recuperarSenha.php`
- **Rota:** `?url=recuperar-senha` → `UserController@recuperarSenha`
- **Tipo:** Formulário (público, sem login)
- **Objetivo:** Solicitar envio de link de redefinição por e-mail.
- **Acesso/Permissão:** —. Espera `$mensagem`/`$error`.
- **Principais elementos:** Card "Recuperar senha"; bloco de sucesso/erro; form `./recuperar-senha` POST com "E-mail"; botão "Enviar link de recuperação"; link "Voltar para o login".
- **Modais:** Nenhum.
- **Observações:** Após envio, oculta o form e mostra só a mensagem (confirmação genérica).

### Redefinir Senha — `app/views/Usuarios/redefinirSenha.php`
- **Rota:** `?url=redefinir-senha` → `UserController@redefinirSenha`
- **Tipo:** Formulário (público, via token)
- **Objetivo:** Definir nova senha usando o token recebido por e-mail.
- **Acesso/Permissão:** —. Espera `$token`, `$error`, `$tokenInvalido`.
- **Principais elementos:** Card "Redefinir senha"; se `$tokenInvalido` → "Solicitar novo link"; senão form `./redefinir-senha` POST com hidden `token`, "Nova senha" (min 6), "Confirmar nova senha" (min 6); botão "Redefinir senha"; link "Voltar para o login".
- **Modais:** Nenhum.
- **Observações:** Validação de coincidência no servidor.

---

## Clientes

### Lista de Clientes — `app/views/Clientes/listClientes.php`
- **Rota:** `?url=listClientes` → `ClienteController@list`
- **Tipo:** Listagem
- **Objetivo:** Tela principal de gestão de clientes, com cards de resumo, painéis laterais de VIP e novos do mês, abas Ativos/Inativos e a tabela (via `_tabelaClientes.php`).
- **Acesso/Permissão:** Sem checagem no topo. Ações de reativar/desativar dependem de `$_SESSION['user_tipo'] === 'ADMIN'` (ver parcial).
- **Principais elementos:**
  - Cabeçalho "Gestão de Clientes"; botões "+ Cliente Balcão" (→ `./saveCliente?balcao=1`) e "+ Novo Cliente" (→ `./saveCliente`).
  - Stat cards: "Total de Clientes"; "Novos esse Mês" (card clicável `#cardNovosMes`); "Clientes VIP / Recorrentes" (card clicável `#cardClientesVip`).
  - Abas: "Clientes Ativos" (`?aba=ativos`) e "Clientes Inativos" (`?aba=inativos`) com contadores.
  - Tabela renderizada dentro de `#clientes-conteudo`.
- **Modais:** Nenhum modal de formulário. Dois painéis flutuantes: `#vipPanel` ("Clientes VIP & Recorrentes", lista `./vipList` + perfil `./vipPerfil?id=`) e `#panelNovosMes` ("Novos Clientes do Mês", lista `./novosMesList` + perfil `./vipPerfil?id=`).
- **Observações:** Painéis via fetch JSON; perfil mostra Telefone, E-mail, Documento, Cliente desde, Endereço, Primeira/Última visita e Veículos; fecham com Escape/clique fora; `escaparHtml`. Abas/paginação/busca por fetch (`?fragmento=1`), com `history.pushState`/`popstate`, progressivo.

### Tabela de Clientes (parcial) — `app/views/Clientes/_tabelaClientes.php`
- **Rota:** Parcial/include. Renderizada sozinha quando `ClienteController::list()` recebe `?fragmento=1`.
- **Tipo:** Parcial/Componente (toolbar de busca + tabela + paginação)
- **Objetivo:** Renderizar o miolo da listagem de clientes de forma independente.
- **Acesso/Permissão:** Reativar/desativar verificam `$_SESSION['user_tipo'] === 'ADMIN'`.
- **Principais elementos:**
  - Toolbar (GET `./listClientes`): hidden `aba`; busca `busca` ("Buscar por nome, telefone ou e-mail..."); "Filtrar"; "Limpar"; contador.
  - Colunas: "Cliente" (avatar + nome + documento), "Contato" (telefone/e-mail), "Veículos Cadastrados", "Última Visita", "Status" (vira "Desativado em" nos inativos), "Ações".
  - Ações: "Reativar cliente" (inativos + ADMIN, POST `./reativarCliente`), "Visualizar" (→ `./viewCliente?id=`), "Editar" (ativos), "Desativar" (ativos + ADMIN, POST `./deleteCliente`, "...não será excluído permanentemente.").
  - Estado vazio contextual + paginação ("Mostrando X de Y resultados").
- **Modais:** Nenhum.
- **Observações:** Todos os POST usam `csrf_field()`. Busca é o único filtro (status vem da aba).

### Novo Cliente — `app/views/Clientes/saveCliente.php`
- **Rota:** `?url=saveCliente` → `ClienteController@save`
- **Tipo:** Formulário (criar) — modal overlay
- **Objetivo:** Cadastrar cliente (PF/PJ), com "Cliente Balcão" e busca de endereço por CEP.
- **Acesso/Permissão:** `session_start()`; sem checagem de cargo.
- **Principais elementos:**
  - Modal `#modalCriarCliente` ("Novo Cliente"); fechar → `./listClientes`. Form `#formCriarCliente` (`./saveCliente` POST).
  - ① Identificação: "Tipo de Pessoa" * (PF/PJ, `onchange="alternarTipoPessoa()"`), "Nome" * (+ "Usar Cliente Balcão"), "CPF" (`#campo_cpf`), "CNPJ" (`#campo_cnpj`, oculto por padrão), "E-mail", "Telefone".
  - ② Endereço: "CEP" (`onblur="buscarCep()"`), "Endereço", "Bairro", "Cidade", "UF" (SP selected).
  - ③ Configurações: "Ativo" (radio Sim/Não), "Observações".
  - Rodapé: datas + usuário; botões "Limpar", "Cancelar", "Gravar".
- **Modais:** `#modalCriarCliente`.
- **Observações:** Máscaras CPF/CNPJ/telefone/CEP (IMask); `alternarTipoPessoa()`; busca CEP via ViaCEP; `preencherBalcao()` (e `?balcao=1`); fecha clicando fora.

### Editar Cliente — `app/views/Clientes/editCliente.php`
- **Rota:** `?url=editCliente` → `ClienteController@edit`
- **Tipo:** Formulário (editar) — página em grid de cards
- **Objetivo:** Atualizar dados do cliente, ver veículos associados e (ADMIN) desativar.
- **Acesso/Permissão:** `session_start()`. "Desativar Cliente" e `#form-desativar-cliente` só se ADMIN.
- **Principais elementos:**
  - Cabeçalho "Editar Cliente" + "Voltar". Form `./editCliente` POST + hidden `id_cliente`.
  - Card 1 Identificação (Tipo, Nome, CPF/CNPJ, E-mail, Telefone); Card 2 Endereço; Card 3 Veículos Associados ("Adicionar Veículo" → `./saveVeiculo&id_cliente=`, lista com ação → `./editVeiculo&id=`); Card 4 Configurações; Card 5 Resumo.
  - Footer: "Desativar Cliente" (ADMIN), "Cancelar", "Salvar Alterações". Form externo `#form-desativar-cliente` (POST `./deleteCliente`).
- **Modais:** Nenhum.
- **Observações:** IMask; ViaCEP; botão desativar usa `form=` HTML5; POST com `csrf_field()`.

### Visualizar Cliente — `app/views/Clientes/viewCliente.php`
- **Rota:** `?url=viewCliente` → `ClienteController@visualizar`
- **Tipo:** Visualização/Detalhe — modal overlay (leitura)
- **Objetivo:** Exibir todos os dados do cliente, com status e tipo de pessoa destacados.
- **Acesso/Permissão:** `session_start()`; sem checagem de cargo.
- **Principais elementos:** Modal `#modalViewCliente` ("Cliente — <nome>") + badge status. ① Identificação (ID, datas); ② Dados (avatar, nome, contatos, rótulo CPF/CNPJ, tipo); ③ Endereço; ④ Configurações (status, observações). Rodapé: "Editar" (→ `./editCliente&id=`), "Voltar".
- **Modais:** `#modalViewCliente`.
- **Observações:** Espera `$dados_cliente`; cores de badge por tipo/status; sem inputs.

---

## Veículos

### Lista de Veículos — `app/views/Veiculos/listVeiculos.php`
- **Rota:** `?url=listVeiculos` → `VeiculoController@list`
- **Tipo:** Listagem
- **Objetivo:** Listar veículos com cards de resumo, painel de novos do mês, filtros no cliente (JS) e renderização da placa (SVG) e da cor.
- **Acesso/Permissão:** Sem checagem no topo. Excluir por linha só ADMIN.
- **Principais elementos:**
  - Cabeçalho "Gestão de Veículos" + "Novo Veículo" (→ `./saveVeiculo`).
  - Stat cards: "Total de Veículos"; "Novos este Mês" (card clicável `#cardVeiculosNovosMes`); "Marca mais Recorrente".
  - Filtros client-side: busca `#busca-veiculo`, `#filtro-marca`, `#filtro-combustivel`, contador `#contador-veiculos`.
  - Colunas: "Placa" (SVG), "Veículo" (logo marca + modelo), "Cor" (bolinha + nome), "Ano", "Combustível", "Proprietário", "Observações", "Ações".
  - Ações: "Visualizar" (→ `./viewVeiculo&id=`), "Editar" (→ `./editVeiculo&id=`), "Excluir" (ADMIN, POST `./deleteVeiculo`, confirm "...orçamentos e ordens de serviço vinculados... também serão APAGADOS. Esta ação é irreversível.").
- **Modais:** Nenhum modal. Painel `#panelVeiculosNovosMes` ("Novos Veículos do Mês", lista `./listVeiculosNovosMes` + perfil `./veiculoPerfil?id=`).
- **Observações:** Placa SVG (modelo antigo com relevo; Mercosul com faixa azul/bandeira/QR, detecção por regex). `filtrarVeiculos()` client-side. Logos de marca externos (carlogos.org) com fallback. Paginação server-side preservando filtros.

### Novo Veículo — `app/views/Veiculos/saveVeiculo.php`
- **Rota:** `?url=saveVeiculo` → `VeiculoController@save`
- **Tipo:** Formulário (criar) — modal overlay
- **Objetivo:** Cadastrar veículo vinculado a cliente, com leitura de placa por foto (OCR/IA), modelo dependente da marca e combustível restrito por modelo.
- **Acesso/Permissão:** `session_start()`; sem checagem de cargo.
- **Principais elementos:**
  - Modal `#modalCriarVeiculo` ("Novo Veículo"); form `#formCriarVeiculo` (`./saveVeiculo` POST).
  - ① Identificação: "Cliente" * (`cliente_id`), "Marca" * (`#marca_id`), "Modelo" * (`#modelo_id`, via AJAX).
  - ② Dados do Veículo: "Placa" * — área de upload `#areaUploadPlaca`, input `#fotoPlaca` (câmera), "Ler placa pela foto" `#btnLerPlaca`, status, link "Inserir placa manualmente" (`#entradaManualPlaca`, `#placa`), hidden `#placa_normalizada`; "Ano"; "Cor" (`#corSelect`); "Combustível" (`#combustivelSelect`).
  - ③ Observações. Rodapé: datas + usuário; "Limpar", "Cancelar", "Gravar".
- **Modais:** `#modalCriarVeiculo`.
- **Observações:** Leitura de placa via POST `./lerPlaca` (FormData `imagem`, JSON `{sucesso, placa, ...}`). Modelos por marca via AJAX (`modelosPorMarca`, `data-combustiveis`); `aplicarCombustiveis()`. Máscara de placa (IMask). Upload por clique/câmera/drag&drop. Cor via Tom Select; `window.CORES_CARRO`/`COMBUSTIVEIS` via `json_para_script()`.

### Editar Veículo — `app/views/Veiculos/editVeiculo.php`
- **Rota:** `?url=editVeiculo` → `VeiculoController@edit`
- **Tipo:** Formulário (editar) — página em grid de cards
- **Objetivo:** Atualizar dados do veículo, com modelo dependente da marca e (ADMIN) exclusão.
- **Acesso/Permissão:** "Excluir Veículo" e `#form-excluir-veiculo` só ADMIN.
- **Principais elementos:**
  - Cabeçalho "Editar Veículo" + "Voltar". Form `./editVeiculo` POST + hidden `id_veiculo`.
  - Card 1 (Cliente, Marca `#marca_id`, Modelo `#modelo_id` com `data-modelo-atual`); Card 2 Identificação (Placa `#placa`, Ano, Cor `#corSelect`, Combustível `#combustivelSelect`); Card 3 Observações; Card 4 Resumo.
  - Footer: "Excluir Veículo" (ADMIN), "Cancelar", "Salvar Alterações". Form externo `#form-excluir-veiculo` (POST `./deleteVeiculo`).
- **Modais:** Nenhum.
- **Observações:** `carregarModelosPorMarca()` pré-seleciona; `aplicarCombustiveis()`; preserva valores legados fora da lista canônica; `csrf_field()`.

### Visualizar Veículo — `app/views/Veiculos/viewVeiculo.php`
- **Rota:** `?url=viewVeiculo` → `VeiculoController@visualizar`
- **Tipo:** Visualização/Detalhe — modal overlay (leitura) + timeline
- **Objetivo:** Exibir dados do veículo, cliente vinculado e histórico (timeline).
- **Acesso/Permissão:** `session_start()`; sem checagem de cargo.
- **Principais elementos:** Modal `#modalViewVeiculo` ("Veículo — <marca modelo>") + badge placa. ① Identificação; ② Dados (placa estilizada, marca/modelo, ano·cor, combustível); ③ Cliente Vinculado (+ "Ver cliente" → `./viewCliente?id=`); ④ Observações; ⑤ Histórico (timeline com ícone, texto, data, valor R$). Rodapé: "Editar" (→ `./editVeiculo&id=`), "Voltar".
- **Modais:** `#modalViewVeiculo`.
- **Observações:** Espera `$dados_veiculo` e `$timeline`; valores formatados com `number_format`.

### Tipos de Combustível (lista) — `app/views/Veiculos/_combustiveis.php`
- **Rota:** Parcial/include. Incluído em save/edit/view/list de Veículos.
- **Tipo:** Parcial/Componente (array PHP)
- **Objetivo:** Lista canônica de combustíveis para selects e filtro.
- **Principais elementos:** "Gasolina", "Etanol", "Flex (Álcool/Gasolina)", "Diesel", "GNV", "Híbrido", "Elétrico".
- **Modais:** Nenhum.
- **Observações:** O valor gravado no banco é o próprio NOME do combustível (coluna `combustivel`).

### Cores de Carro (lista) — `app/views/Veiculos/_coresCarro.php`
- **Rota:** Parcial/include. Incluído em save/edit/list de Veículos.
- **Tipo:** Parcial/Componente (array PHP nome→hex)
- **Objetivo:** Lista canônica de cores (nome PT-BR → hex) para selects (Tom Select) e para a bolinha colorida na listagem.
- **Principais elementos:** Branco `#FFFFFF`, Branco Pérola `#ECE9E0`, Preto `#1A1A1A`, Prata `#C8CDD2`, Cinza `#8B9096`, Grafite `#3C4043`, Chumbo `#4D5258`, Vermelho `#D21F26`, Vinho `#5E1622`, Azul `#1561B0`, Azul Marinho `#17284C`, Verde `#2E7D32`, Verde Escuro `#1B3A2B`, Amarelo `#F2C200`, Laranja `#E8650E`, Marrom `#5A3E2B`, Bege `#D9C7A7`, Dourado `#C8A028`, Bronze `#8A6A45`, Rosa `#E64A8B`, Roxo `#6B2FA0`.
- **Modais:** Nenhum.
- **Observações:** O valor gravado no banco é o NOME da cor; o hex é só para exibição.

---

## Agendamentos

### Agenda (Lista de Agendamentos) — `app/views/Agendamentos/listAgendamentos.php`
- **Rota:** `?url=listAgendamentos` → `AgendamentoController@list`
- **Tipo:** Listagem (agenda por dia, navegação por período)
- **Objetivo:** Exibir agendamentos de um período (dia/semana/mês), agrupados por dia, com contadores por status, filtros e ações rápidas.
- **Acesso/Permissão:** `$ehAdmin`; excluir em definitivo só ADMIN.
- **Principais elementos:**
  - Cabeçalho "Agenda" + "Novo agendamento" (→ `./saveAgendamento?data=`); flash via `_alerta.php`.
  - Navegação: "‹"/"Hoje"/"›"; abas "Dia"/"Semana"/"Mês".
  - Filtro (GET): select "status" (Todos + Agendado/Confirmado/Concluído/Cancelado/Não compareceu), select "mecanico_id", "Filtrar".
  - Cards: "Total no período", "Confirmados", "Aguardando confirmação", "Cancelados / faltas".
  - Itens por dia: hora início–fim; serviço; cliente · placa · mecânico; observações; badge status; link "OS <nº>" se houver.
  - Ações: "Confirmar" (AGENDADO), "Gerar OS" (ativo sem OS), "Editar", "Cancelar" (ativo), "✕" Excluir (ADMIN). Vazio: "Nenhum agendamento neste período."
- **Modais:** Nenhum.
- **Observações:** POSTs com `csrf_field()` + hidden `periodo`/`data_ref`. `$statusInfo` define label/badge. Sem busca textual.

### Formulário de Agendamento (parcial) — `app/views/Agendamentos/_formAgendamento.php`
- **Rota:** Parcial/include. Incluído por save/edit.
- **Tipo:** Parcial/Componente (campos compartilhados)
- **Objetivo:** Campos comuns de criar/editar agendamento, com filtragem de veículos pelo cliente e campo de status só na edição.
- **Principais elementos:** "Cliente" * (`#cliente_id`), "Veículo" (`#veiculo_id`, `data-cliente-id`), "Data" *, "Hora" *, "Duração prevista" * (30min/1h[padrão]/1h30/2h/3h/4h/Dia inteiro), "Mecânico responsável", "Serviço" * (max 160), "Status" * (só edição), "Observações" (max 500).
- **Modais:** Nenhum.
- **Observações:** JS filtra veículos pelo cliente (`data-cliente-id`); selects via Tom Select. Espera `$clientes`, `$veiculos`, `$mecanicos`, `$valores`, `$ehEdicao`.

### Novo Agendamento — `app/views/Agendamentos/saveAgendamento.php`
- **Rota:** `?url=saveAgendamento` → `AgendamentoController@save`
- **Tipo:** Formulário (criar) — página com card
- **Objetivo:** Criar um novo agendamento (antes de abrir a OS).
- **Acesso/Permissão:** —.
- **Principais elementos:** Cabeçalho "Novo Agendamento" + "Voltar para a agenda"; alerta de erro; form `./saveAgendamento` POST (`csrf_field()`) incluindo `_formAgendamento.php` (sem status); botões "Cancelar", "Salvar agendamento".
- **Modais:** Nenhum.
- **Observações:** `$valores = $old ?? []` (repovoa ao falhar validação).

### Editar Agendamento — `app/views/Agendamentos/editAgendamento.php`
- **Rota:** `?url=editAgendamento` → `AgendamentoController@edit`
- **Tipo:** Formulário (editar) — página com card
- **Objetivo:** Atualizar um agendamento; sinaliza quando já virou OS.
- **Acesso/Permissão:** —.
- **Principais elementos:** Cabeçalho "Editar Agendamento" + "Voltar para a agenda"; alerta de erro; aviso (quando `$jaVirouOS`): "Este agendamento já virou a OS <nº>." (→ `./viewOrdemServico?id=`); form `./editAgendamento` POST (`csrf_field()`) + hidden `id_agendamento` incluindo `_formAgendamento.php` (com status); botões "Cancelar", "Salvar alterações".
- **Modais:** Nenhum.
- **Observações:** `data_hora` separado em `data`/`hora`; `$jaVirouOS` baseado em `ordem_servico_id`.

---

## Orçamentos

### Lista de Orçamentos — `app/views/Orcamento/listOrcamentos.php`
- **Rota:** `?url=listOrcamentos` → `OrcamentoController@list`
- **Tipo:** Listagem
- **Objetivo:** Listar orçamentos por situação (pendentes/aprovados/recusados) e permitir aprovar (gerando OS), recusar, editar, visualizar, imprimir e exportar PDF.
- **Acesso/Permissão:** —.
- **Principais elementos:**
  - Cabeçalho "Orçamentos" ("Ao aprovar, uma OS é gerada automaticamente.") + "Novo Orçamento" (→ `./novoOrcamento`).
  - 4 stat cards: "Pendentes", "Aprovados", "Recusados", "Total Pendente" (R$).
  - Abas: "Pendentes" (com badge), "Aprovados", "Recusados". Filtros por painel (busca + data + contador).
  - Banner (pendentes): "Aprovar um orçamento cria automaticamente uma OS com status **Em Andamento**."
  - Tabela Pendentes: `Orçamento | Cliente | Veículo/Placa | Mecânico | Criado em | Valor Total | Ações`. Aprovados: + "Aprovado em", "OS Gerada" (link `./viewOrdemServico&id=`). Recusados: + "Recusado em", "Motivo" (número com `line-through`).
  - Ações (pendentes): Visualizar, Editar, Imprimir (`./imprimirOrcamento&id=`, _blank), Baixar PDF (`./exportOrcamentoPdf&id=`), Aprovar (`confirmarAprovar`), Recusar (`confirmarRecusar`). Aprovados: Visualizar + "Ver OS". Recusados: só Visualizar.
- **Modais:**
  - `#modal-aprovar` — "Aprovar Orçamento" ("Uma OS será criada automaticamente.") — confirma e gera OS; **canvas de assinatura** `#canvas-assinatura-aprovar` ("Assinatura do cliente (opcional)") + "Limpar assinatura"; `form#form-aprovar` (`./aprovarOrcamento` POST) com hidden `id_orcamento`, `assinatura_cliente`; "Confirmar e Gerar OS" / "Cancelar".
  - `#modal-recusar` — "Recusar Orçamento" — registra recusa com motivo; `textarea#recusar-motivo` ("Motivo da recusa (opcional)"); `form#form-recusar` (`./recusarOrcamento` POST) com hidden `id_orcamento`, `motivo_recusa`; "Recusar Orçamento" / "Cancelar".
- **Observações:** Tabs via JS; `filtrarOrc(painel)`; assinatura via `assinatura.js` (`initAssinatura()`), dataURL injetado no hidden.

### Novo Orçamento — `app/views/Orcamento/saveOrcamento.php`
- **Rota (exibição):** `?url=novoOrcamento` → `OrcamentoController@save`; **submete** `?url=saveOrcamento` → `@saveOrcamento`.
- **Tipo:** Formulário (criar) — modal overlay (`#modalCriarOrcamento`, `.modal--xl`)
- **Objetivo:** Criar orçamento com cliente/veículo, mecânico/comissão, itens e resumo financeiro calculado no front.
- **Acesso/Permissão:** — (lê `$_SESSION` no rodapé).
- **Principais elementos (seções ① a ⑦):**
  - ① Identificação: "Número do Orçamento" (readonly), "Status" (hidden PENDENTE + badge), "Data de Criação *".
  - ② Cliente e Veículo: "Cliente *" (`#orc-cliente_id`), "Veículo *" (`#orc-veiculo_id`, `data-cliente-id`), "KM do Veículo".
  - ③ Mecânico Responsável: "Mecânico", "Comissão do Técnico" (%), "Base de Cálculo" (Somente Serviços/Somente Peças/Peças + Serviços), preview do mecânico. Hidden `valor_mao_obra`, `comissao_pct`, `comissao_base`.
  - ④ Produtos & Serviços: tabela dinâmica `Item | Tipo | Qtd | Unit. | Subtotal | (remover)`; botões "Adicionar Produto"/"Adicionar Serviço".
  - ⑤ Diagnóstico: "Defeito Relatado", "Diagnóstico Técnico", "Observações".
  - ⑥ Resumo Financeiro: "Total em Peças", "Total em Serviços", "Comissão Técnico", "Lucro Estimado", "Total do Orçamento".
  - ⑦ Validade & Prazo: "Válido até" (+14 dias), "Prazo de Execução Estimado".
  - Rodapé: "Imprimir", "Limpar", "Cancelar", "Salvar Orçamento".
- **Modais:** `#orc-modal-catalogo` — "Adicionar Produto/Serviço" — seleção do catálogo com busca e abas por categoria; aba "Personalizado" (serviços): "Nome do Serviço *", "Tipo de Custo *" (Valor Fixo/Porcentagem), "Valor (R$) *"/"Percentual (%) *", "Categoria (opcional)"; rodapé "N selecionado(s)" + "Cancelar"/"Confirmar".
- **Observações:** JS `orc*`; catálogo via `json_para_script()` (`ORC_CATALOGO`); comissão = base × pct/100; "Lucro Estimado" = total − comissão; `prepararSubmitOrc()` monta `itens[i][...]`.

### Editar Orçamento — `app/views/Orcamento/editOrcamento.php`
- **Rota:** `?url=editOrcamento` → `OrcamentoController@edit`
- **Tipo:** Formulário (editar)
- **Objetivo:** Atualizar dados do orçamento; itens ficam somente leitura.
- **Acesso/Permissão:** —.
- **Principais elementos (cards 1 a 5):** Cabeçalho "Editar Orçamento · <nº>" + "Voltar" (→ `./viewOrcamento?id=`); hidden `id_orcamento`. 1. Identificação (Número, Status [Pendente/Aprovado/Recusado], Data de Validade, Prazo); 2. Cliente e Veículo (+ KM, Mecânico); 3. Defeito/Diagnóstico/Observações; 4. Itens (somente leitura); 5. Valores (Total Peças, Total Serviços, Mão de Obra [readonly], Comissão %, Valor Total [readonly]). Rodapé: "Cancelar" (→ `./listOrcamentos`), "Salvar Alterações".
- **Modais:** Nenhum.
- **Observações:** JS: Mão de Obra = Serviços × Comissão%/100; Total = Peças + Serviços; `parseMoney()` aceita vírgula/ponto.

### Visualizar Orçamento — `app/views/Orcamento/viewOrcamento.php`
- **Rota:** `?url=viewOrcamento` → `OrcamentoController@visualizar`
- **Tipo:** Visualização/Detalhe
- **Objetivo:** Exibir o orçamento em leitura, com stepper, PDF/impressão, link de rastreio, WhatsApp e edição.
- **Acesso/Permissão:** —.
- **Principais elementos:** Cabeçalho "Visualizar Orçamento · <nº>" + badge status (match pendente/aprovado/reprovado/cancelado). Botões: "Voltar", "Baixar PDF" (`./exportOrcamentoPdf?id=`), "Link de Rastreio" (copia `$linkRastreio`), "Enviar WhatsApp" (aprovado + telefone, `wa.me`), "Editar" (`./editOrcamento?id=`). Stepper ("Criado"→"Aguardando aprovação"→decisão). Cards 1–5 (readonly) + card 6 "Assinatura do Cliente" (se houver).
- **Modais:** Nenhum.
- **Observações:** Mensagem de WhatsApp pré-montada muda conforme link de rastreio.

### Imprimir Orçamento — `app/views/Orcamento/printOrcamento.php`
- **Rota:** `?url=imprimirOrcamento` → `OrcamentoController@imprimir`
- **Tipo:** PDF/Impressão (documento HTML standalone)
- **Objetivo:** Gerar documento imprimível do orçamento, com modo de edição em tela e botão imprimir/salvar PDF.
- **Acesso/Permissão:** —.
- **Principais elementos:** Página HTML própria. Toolbar (some na impressão): "Editar" (`toggleEdicao()`), "Fechar", "Imprimir / Salvar PDF". Documento: cabeçalho com logo/empresa, número, badge status, data; blocos Cliente/Veículo/Defeito/Diagnóstico; tabelas "Peças / Produtos" e "Mão de Obra / Serviços"; Totais; Observações; assinaturas; rodapé.
- **Modais:** Nenhum.
- **Observações:** `toggleEdicao()` liga `contenteditable` nos `[data-editable]` (não persiste); `@media print` esconde toolbar; `@page { margin: 14mm }`.

### PDF do Orçamento (Dompdf) — `app/views/Orcamento/pdfOrcamento.php`
- **Rota:** `?url=exportOrcamentoPdf` → `OrcamentoController@exportarPdf`
- **Tipo:** PDF/Impressão (template Dompdf)
- **Objetivo:** Renderizar o orçamento como PDF para o cliente (cores hex, tabelas, sem JS).
- **Acesso/Permissão:** —.
- **Principais elementos:** Cabeçalho (marca, empresa, número, badge status); faixa meta (Emitido em/Válido até/Responsável); cards Cliente/Veículo; Problema/Diagnóstico; tabelas "Peças / Produtos" e "Mão de Obra / Serviços" (`Descrição | Qtd | Valor Unit. | Desconto Unit. | Total do Item`); Totais; Observações; Termos da Garantia (90 dias); assinaturas; rodapé.
- **Modais:** Nenhum.
- **Observações:** `valor_mao_obra` (comissão) é carve-out interno e **não** aparece no documento do cliente.

---

## Ordens de Serviço

### Lista de Ordens de Serviço — `app/views/Ordens_Servico/listOrdemServico.php`
- **Rota:** `?url=listOrdemServico` → `OrdemServicoController@list`
- **Tipo:** Listagem
- **Objetivo:** Listar OS em 4 abas (Abertas, Em Andamento, Concluídas, Arquivadas) com busca/filtros e ações.
- **Acesso/Permissão:** `$podeExcluir = user_tipo === 'ADMIN'` controla o botão Excluir (DELETE definitivo restrito a ADMIN via `requireRole()`).
- **Principais elementos:**
  - Flash via `_alerta.php` (`$_GET['erro']` vínculos/exclusao; `ok=excluido`).
  - Cabeçalho + "Ver como Kanban" (→ `./kanbanOrdemServico`) + "Nova Ordem de Serviço".
  - Abas: "OS Abertas", "OS Em Andamento", "OS Concluídas", "OS Arquivadas". Filtros por painel (busca, data; "ativas" tem `#filtro-status-ativas`).
  - Tabelas por aba: colunas variam (ID, Cliente, Veículo, Status, Data de Entrada/Conclusão, Valor, Ações). Badge: AGUARDANDO_PECA→"Aguardando peça", senão "Em andamento".
  - Ações: Visualizar (→ `./viewOrdemServico&id=`), "Concluir OS" (`abrirModalConcluir`), Editar (→ `./editOrdemServico&id=`), Excluir (ADMIN, POST `./deleteOrdemServico`, confirm "...são apagados junto, em definitivo."). Arquivadas: só Visualizar.
- **Modais:** `#modal-concluir` — "Concluir Ordem de Serviço" ("OS #<nº>") — conclui a OS lançando no caixa — "Forma de pagamento *" (Dinheiro/Pix/Cartão/Boleto; "...lançado automaticamente no caixa ao concluir."), canvas `#canvas-assinatura-concluir`; `form#form-concluir` (`./concluirOrdemServico` POST) com hidden `id_ordem_servico`, `assinatura_cliente`; "Confirmar Conclusão" / "Cancelar".
- **Observações:** Tabs via JS; `filtrarTabela(painel)`; assinatura via `assinatura.js`; o select de forma de pagamento usa `form=`.

### Kanban de Ordens de Serviço — `app/views/Ordens_Servico/kanbanOrdemServico.php`
- **Rota:** `?url=kanbanOrdemServico` → `OrdemServicoController@kanban`
- **Tipo:** Kanban
- **Objetivo:** Exibir OS em colunas por status e mudar o status arrastando os cards (drag-and-drop).
- **Acesso/Permissão:** —.
- **Principais elementos:** Cabeçalho + "Ver como lista" + "Nova Ordem de Serviço". Colunas por status (ABERTA→info, EM_ANDAMENTO→warning, AGUARDANDO_PECA→purple, CONCLUIDA→success) com contador. Cards draggable: "OS #<nº>", cliente, veículo·placa, valor R$, "Detalhes →" (→ `./viewOrdemServico&id=`). Vazio: "Nenhuma OS aqui."
- **Modais:** Nenhum.
- **Observações:** Drag-and-drop em `kanban.js` (persiste via `moverStatusOrdemServico`).

### Nova Ordem de Serviço — `app/views/Ordens_Servico/saveOrdemServico.php`
- **Rota:** `?url=saveOrdemServico` → `OrdemServicoController@save`
- **Tipo:** Formulário (criar) — modal overlay (`#modalCriarOrdemServico`, `.modal--xl`)
- **Objetivo:** Abrir OS com cliente/veículo, mecânico/comissão, itens e resumo financeiro. Suporta pré-preenchimento vindo da agenda ("Gerar OS").
- **Acesso/Permissão:** —.
- **Principais elementos (seções ① a ⑦):** ① Identificação (Número readonly, Status hidden ABERTA, Data de Abertura); ② Cliente e Veículo (pré-seleção `$preCliente`/`$preVeiculo`); ③ Mecânico (comissão %, base Serviços/Peças/Ambos); ④ Produtos & Serviços (tabela + "Adicionar Produto/Serviço"); ⑤ Diagnóstico (`$preDefeito`); ⑥ Resumo Financeiro (Peças, Serviços, Comissão, "Lucro Líquido", "Total da OS"); ⑦ Conclusão (Data de Conclusão). Rodapé: "Imprimir OS", "Limpar", "Cancelar", "Gravar OS". Hidden `agendamento_id` quando vem da agenda.
- **Modais:** `#modal-catalogo` — idêntico ao do orçamento (busca, abas por categoria, aba "Personalizado" para serviços).
- **Observações:** Catálogo/mecânicos via `json_para_script()` (`CATALOGO`, `MECANICOS_DATA`); comissão = base × pct/100; `prepararSubmit()` monta `itens[i][...]`.

### Editar Ordem de Serviço — `app/views/Ordens_Servico/editOrdemServico.php`
- **Rota:** `?url=editOrdemServico` → `OrdemServicoController@edit`
- **Tipo:** Formulário (editar)
- **Objetivo:** Atualizar a OS incluindo status, cliente/veículo, mecânico/comissão, diagnóstico e itens (editáveis aqui); permite excluir (ADMIN).
- **Acesso/Permissão:** `$podeExcluir = user_tipo === 'ADMIN'` controla "Excluir Ordem".
- **Principais elementos (cards 1 a 6):** Cabeçalho "Editar Ordem de Serviço · <nº>" + status-pill + "Voltar"; hidden `id_ordem_servico`. 1. Identificação (Número, Status [Aberta/Em Andamento/Aguardando Peça/Concluída/Cancelada], Datas); 2. Cliente e Veículo; 3. Mecânico; 4. Diagnóstico; 5. Produtos & Serviços (editável, +/- qtd); 6. Resumo Financeiro. Rodapé: "Excluir Ordem" (ADMIN, `#formExcluirOS` → `./deleteOrdemServico`), "Cancelar", "Salvar Alterações".
- **Modais:** `#modal-catalogo` (mesmo do save).
- **Observações:** `osItens` pré-populado com `$itens_os` via `json_para_script()`; aqui a comissão usa **somente serviços** (sem seletor de base); CSRF via `csrf.js`.

### Visualizar Ordem de Serviço — `app/views/Ordens_Servico/viewOrdemServico.php`
- **Rota:** `?url=viewOrdemServico` → `OrdemServicoController@visualizar`
- **Tipo:** Visualização/Detalhe — modal overlay (`#modalViewOrdemServico`, `.modal--xl`)
- **Objetivo:** Exibir a OS em leitura com stepper, resumo financeiro e ações (PDF, WhatsApp, rastreio, imprimir, editar).
- **Acesso/Permissão:** —.
- **Principais elementos (① a ⑦):** Cabeçalho + badge status + fechar. Stepper ("Aberta"→"Em Andamento"→"Aguardando Peça"→"Concluída"); banner se CANCELADA. ① Identificação; ② Cliente e Veículo; ③ Mecânico (comissão, badge "Responsável"); ④ Produtos & Serviços; ⑤ Diagnóstico; ⑥ Resumo Financeiro ("Lucro Líquido" = total − comissão); ⑦ Assinatura (se houver). Rodapé: "Enviar WhatsApp" (CONCLUIDA + telefone), "Baixar PDF" (`./exportOrdemServicoPdf?id=`), "Link de Rastreio", "Imprimir", "Editar", "Voltar".
- **Modais:** `#modalViewOrdemServico` (a própria tela).
- **Observações:** Mensagem de WhatsApp pré-montada ("...pronto para retirada.").

### PDF da Ordem de Serviço (Dompdf) — `app/views/Ordens_Servico/pdfOrdemServico.php`
- **Rota:** `?url=exportOrdemServicoPdf` → `OrdemServicoController@exportarPdf`
- **Tipo:** PDF/Impressão (template Dompdf)
- **Objetivo:** Renderizar a OS como PDF para o cliente (não expõe comissão/lucro).
- **Acesso/Permissão:** —.
- **Principais elementos:** Cabeçalho (marca, empresa, número, badge status); faixa meta (Aberta em/Concluída em/Responsável); cards Cliente/Veículo; Problema/Diagnóstico; tabela única "Peças & Serviços" (`Descrição | Qtd | Valor Unit. | Desconto Unit. | Total do Item`); Totais; Observações; Termos da Garantia (90 dias); assinaturas; rodapé.
- **Modais:** Nenhum.
- **Observações:** Mesmo padrão do `pdfOrcamento.php`; sem comissão/lucro (documento do cliente).

---

## Produtos

### Listagem de Produtos — `app/views/Produtos/listProdutos.php`
- **Rota:** `?url=listProduto` → `ProdutoController@list`
- **Tipo:** Listagem
- **Objetivo:** Listar produtos com indicadores de inventário e níveis de estoque.
- **Acesso/Permissão:** `user_tipo === 'ADMIN'` — card "Valor em Inventário" e botão "Excluir" só ADMIN.
- **Principais elementos:**
  - Cabeçalho "Gestão de Produtos" + "Categorias" (→ `./listCategoria`) + "+ Novo Produto".
  - Cards: "Total em Estoque", "Estoque Baixo", "Valor em Inventário" (R$, só ADMIN).
  - Filtros (GET): `busca`, `aplicacao`, `categoria`, `status` (Todos/Ativo/Inativo/Em Estoque/Baixo Estoque/Esgotado), "Filtrar", contador.
  - Colunas: Código/SKU, Nome, Categoria, Quantidade, Preço de Venda, Status (badges Inativo/Esgotado/Estoque Baixo/Em Estoque), Ações.
  - Ações: Visualizar (→ `./viewProduto?id=`), Editar (→ `./editProduto?id=`), Excluir (ADMIN, POST `./deleteProduto`, confirm "Deseja realmente arquivar este produto?").
- **Modais:** Nenhum.
- **Observações:** A "exclusão" na verdade **arquiva** (`deleteProduto` → `arquivar`).

### Novo Produto — `app/views/Produtos/saveProduto.php`
- **Rota:** `?url=saveProduto` → `ProdutoController@save`
- **Tipo:** Formulário (criar) — modal overlay
- **Objetivo:** Cadastrar um novo produto no estoque.
- **Acesso/Permissão:** `user_tipo === 'ADMIN'` — "Preço de Custo" e "Margem de Lucro" só ADMIN.
- **Principais elementos (form `#formCriarProduto`):** ① Identificação (Código Interno readonly, Categoria *, Código de Barras, Nome *, Marca); ② Detalhes Técnicos (Descrição, Aplicação, Volume/Embalagem); ③ Gestão de Estoque (Estoque Atual, Estoque Mínimo, Localização *, Ativo Sim/Não); ④ Financeiro (Preço de Custo [ADMIN], Preço de Venda, Margem de Lucro [ADMIN]). Footer: "Imprimir Etiqueta", "Excluir" (na prática `reset()`), "Cancelar", "Gravar".
- **Modais:** `#modalCriarProduto` ("Novo Produto").
- **Observações:** Máscara de moeda (IMask); JS calcula Custo × Venda × Margem automaticamente.

### Editar Produto — `app/views/Produtos/editProduto.php`
- **Rota:** `?url=editProduto` → `ProdutoController@edit`
- **Tipo:** Formulário (editar)
- **Objetivo:** Atualizar as informações de um produto.
- **Acesso/Permissão:** `user_tipo === 'ADMIN'` — "Preço de Custo", "Margem de Lucro" e "Excluir Produto" só ADMIN (para não-ADMIN, `preco_custo` vai como hidden).
- **Principais elementos:** Header "Editar Produto · <código>" + status-pill + "Voltar". Card 1 Identificação; Card 2 Especificações; Card 3 Descrição; Card 4 Estoque e Financeiro (Estoque Atual/Mínimo [step 0.001], Preço de Custo/Venda, Margem, toggle "Produto ativo"). Footer: "Excluir Produto" (ADMIN, `formaction="./deleteProduto"`), "Cancelar", "Salvar Alterações".
- **Modais:** Nenhum.
- **Observações:** IMask de moeda + cálculo de margem.

### Visualizar Produto — `app/views/Produtos/viewProduto.php`
- **Rota:** `?url=viewProduto` → `ProdutoController@visualizar`
- **Tipo:** Visualização/Detalhe (inputs readonly)
- **Objetivo:** Exibir os detalhes do produto (somente leitura).
- **Acesso/Permissão:** `user_tipo === 'ADMIN'` — "Preço de Custo" e "Margem de Lucro" só ADMIN.
- **Principais elementos:** Header + status-pill + "Voltar" + "Editar" (→ `./editProduto?id=`). Card 1 Identificação; Card 2 Especificações; Card 3 Descrição; Card 4 Estoque e Financeiro.
- **Modais:** Nenhum.
- **Observações:** Margem calculada em PHP.

### (Arquivo vazio) — `app/views/Produtos/deleteProdutos.php`
- **Rota:** `?url=deleteProduto` → `ProdutoController@arquivar`
- **Tipo:** — (arquivo sem conteúdo / 0 bytes)
- **Objetivo:** —. A exclusão/arquivamento é processada no controller via POST (sem view própria); os forms de excluir ficam em `listProdutos.php` e `editProduto.php`.
- **Modais:** Nenhum.

---

## Categorias

### Listagem de Categorias — `app/views/Categoria/listCategoria.php`
- **Rota:** `?url=listCategoria` → `CategoriaController@list`
- **Tipo:** Listagem
- **Objetivo:** Gerenciar as categorias usadas no cadastro de produtos.
- **Acesso/Permissão:** `user_tipo === 'ADMIN'` — botão "Excluir" só ADMIN.
- **Principais elementos:** Header "Categorias de Produtos" + "Voltar" (→ `./listProduto`) + "Nova Categoria". Busca `#busca-categorias` (client-side) + contador. Colunas: Categoria (nome + "ID #<id>"), Ações. Ações: Editar (→ `./editCategoria&id=`), Excluir (ADMIN, POST `./deleteCategoria`, confirm "Deseja realmente excluir esta categoria?").
- **Modais:** Nenhum.
- **Observações:** Busca e paginação 100% client-side (`POR_PAGINA = 8`).

### Nova Categoria — `app/views/Categoria/saveCategoria.php`
- **Rota:** `?url=saveCategoria` → `CategoriaController@save`
- **Tipo:** Formulário (criar) — modal overlay
- **Objetivo:** Cadastrar uma nova categoria de produto.
- **Acesso/Permissão:** —.
- **Principais elementos:** Form `#formCriarCategoria`: ① "Nome da Categoria *" (ex.: Óleos, Filtros, Pneus...). Footer: "Limpar", "Cancelar", "Salvar".
- **Modais:** `#modalCriarCategoria` ("Nova Categoria").
- **Observações:** Único campo é o nome.

### Editar Categoria — `app/views/Categoria/editCategoria.php`
- **Rota:** `?url=editCategoria` → `CategoriaController@edit`
- **Tipo:** Formulário (editar)
- **Objetivo:** Atualizar a categoria de produto.
- **Acesso/Permissão:** `user_tipo === 'ADMIN'` — "Excluir Categoria" só ADMIN.
- **Principais elementos:** Header "Editar Categoria · #<id>" + status-pill "Ativa" + "Voltar". Card 1 (Nome *, Identificação, Status "Ativa"); Card 2 Informações do Sistema (Criado/Atualização via `date()`). Footer: "Excluir Categoria" (ADMIN, POST `./deleteCategoria`), "Cancelar", "Salvar Alterações".
- **Modais:** Nenhum.
- **Observações:** Status "Ativa" e datas são fixos na view (usam `date()` do momento).

---

## Movimentação de Estoque

### Listagem de Movimentações — `app/views/MovimentacaoEstoque/listMovimentacaoEstoque.php`
- **Rota:** `?url=listMovimentacaoEstoque` → `MovimentacaoEstoqueController@list`
- **Tipo:** Listagem
- **Objetivo:** Gerenciar as movimentações de estoque (entradas, saídas e ajustes).
- **Acesso/Permissão:** `user_tipo === 'ADMIN'` — coluna "Custo Unitário" e "Excluir" só ADMIN.
- **Principais elementos:** Header "Movimentações" + "Nova Movimentação". Filtros (GET): `busca`, `tipo` (Todos/Entrada/Saída/Ajuste), "Filtrar". Colunas: Produto (ícone por tipo + nome + #id), Tipo (badges Entrada/Ajuste/Saída), Quantidade (3 casas, "+" em AJUSTE positivo), Custo Unitário (ADMIN), Criado em, Ações. Ações: Editar (→ `./editMovimentacaoEstoque&id=`), Excluir (ADMIN, POST `./deleteMovimentacaoEstoque`, confirm "...O estoque do produto será revertido.").
- **Modais:** Nenhum.
- **Observações:** AJUSTE = correção manual gerada automaticamente pela edição de produto (auditoria em `Produtos::update()`).

### Nova Movimentação — `app/views/MovimentacaoEstoque/saveMovimentacaoEstoque.php`
- **Rota:** `?url=saveMovimentacaoEstoque` → `MovimentacaoEstoqueController@save`
- **Tipo:** Formulário (criar) — modal overlay
- **Objetivo:** Registrar uma entrada ou saída de estoque.
- **Acesso/Permissão:** —.
- **Principais elementos:** Form `#formNovaMovimentacao`: ① Produto * (select com `data-estoque` + "(Estoque: X)"); badge "Estoque atual: X unidade(s)". ② Movimentação: Tipo * (Entrada/Saída), Quantidade * (min 0.001 step 0.001). Footer: "Limpar", "Cancelar", "Registrar".
- **Modais:** `#modalNovaMovimentacao` ("Nova Movimentação").
- **Observações:** Só ENTRADA/SAÍDA (AJUSTE não é opção manual). Validação JS impede SAÍDA maior que o estoque.

### Editar Movimentação — `app/views/MovimentacaoEstoque/editMovimentacaoEstoque.php`
- **Rota:** `?url=editMovimentacaoEstoque` → `MovimentacaoEstoqueController@edit`
- **Tipo:** Formulário (editar)
- **Objetivo:** Atualizar uma movimentação de estoque.
- **Acesso/Permissão:** `user_tipo === 'ADMIN'` — "Custo unitário registrado" e "Excluir Movimentação" só ADMIN.
- **Principais elementos:** Card 1 Produto (select + badge estoque); Card 2 Movimentação (Tipo Entrada/Saída, Quantidade, Custo unitário disabled [ADMIN]); Card 3 Resumo. Footer: "Excluir Movimentação" (ADMIN), "Cancelar", "Salvar Alterações".
- **Modais:** Nenhum.
- **Observações:** Badge de estoque via JS; quantidade é input number (sem IMask).

---

## Documentos

### Serviços Finalizados (seleção de OS) — `app/views/Documento/listDocumentos.php`
- **Rota:** `?url=listDocumentos` → `DocumentoController@list`
- **Tipo:** Listagem (com dois modais de geração)
- **Objetivo:** Listar OS concluídas/finalizadas para gerar o documento final.
- **Acesso/Permissão:** —.
- **Principais elementos:** Header "Serviços Finalizados". Cards: "OS Disponíveis", "Valor Total", "Ticket Médio". Filtros (client-side): `#busca-os`, `#filtro-mecanico`, `#filtro-data`, contador. Banner: "Clique em qualquer OS para gerar o documento final automaticamente." Colunas: OS, Cliente, Veículo/Placa, Mecânico, Concluída em, Valor Total, (badge "Gerar documento"). Linha clicável (`selecionarOS(this)`, `data-os` JSON).
- **Modais:**
  - `#modal-documento` — "Gerar Documento" — exibe dados da OS: ① Dados da OS, ② Diagnóstico, ③ Produtos & Serviços, ④ Resumo Financeiro; botões "Cancelar", "Finalizar".
  - `#modal-final` — "Documento Gerado" — "Baixar PDF", "Baixar Excel", "Imprimir" + iframe `#previa-documento-frame` (`./exportOrdemServicoPdf?id=&inline=1`); "Voltar", "Fechar".
- **Observações:** O fluxo de geração usa a rota de **PDF de OS** (`exportOrdemServicoPdf`); "Baixar Excel" gera CSV client-side. Contém dados **mock** de OS.

### Gerar Documento (conferência da OS) — `app/views/Documento/saveDocumento.php`
- **Rota:** `?url=saveDocumento` → `DocumentoController@save` (submete `?url=finalizarDocumento` → `@finalizar`)
- **Tipo:** Formulário (criar) / Visualização — modal overlay
- **Objetivo:** Conferir os dados de uma OS e finalizar o documento referente a ela.
- **Acesso/Permissão:** —.
- **Principais elementos:** Form `#formDocumento` (`./finalizarDocumento` POST) + hidden `os_id`: ① Dados da OS; ② Diagnóstico; ③ Produtos & Serviços (badge Peça/Serviço); ④ Resumo Financeiro. Footer: "Voltar" (→ `./listDocumentos`), "Finalizar Documento".
- **Modais:** `#modalDocumento` — título "Gerar Orçamento" *(sic)* — "Referência: <nº>".
- **Observações:** Totais calculados em PHP a partir de `$os['itens']`.

---

## Caixa

### Caixa (histórico e status) — `app/views/Caixa/listCaixa.php`
- **Rota:** `?url=listCaixa` → `CaixaController@list`
- **Tipo:** Listagem (+ painel da sessão aberta)
- **Objetivo:** Mostrar o histórico de aberturas/fechamentos e o estado da sessão aberta.
- **Acesso/Permissão:** Filtro por papel no controller (atendente só vê as próprias sessões); na view, sem checagem.
- **Principais elementos:** Header "Caixa"; botões condicionais: aberto → "Lançamentos" (→ `./caixaMovimentacoes`) + "Fechar Caixa"; fechado → "Abrir Caixa". Se há sessão aberta: cards "Status", "Caixa atual (dinheiro)", "Entradas", "Saídas" + "Composição da sessão" (entradas por forma; saídas por categoria). Histórico (tabela): #, Status, Aberto por, Abertura, Valor abertura, Fechado por, Fechamento, Valor fechamento, Diferença.
- **Modais:** Nenhum.
- **Observações:** "Diferença" = contado − esperado (colorida vermelho/verde). Mapas `$labelForma`/`$labelCategoria`.

### Abrir Caixa — `app/views/Caixa/abrirCaixa.php`
- **Rota:** `?url=abrirCaixa` → `CaixaController@abrir`
- **Tipo:** Formulário (criar)
- **Objetivo:** Abrir o caixa informando o troco inicial (obrigatório para usar o sistema).
- **Acesso/Permissão:** —.
- **Principais elementos:** Bloco "quem está abrindo"; "Saldo do caixa anterior"; "Valor de abertura (troco inicial) *" (value sugerido = saldo anterior); "Observação (opcional)"; botão "Abrir Caixa".
- **Modais:** Nenhum.
- **Observações:** Máscara de moeda (IMask); valor inicial sugerido = fechamento anterior.

### Fechar Caixa — `app/views/Caixa/fecharCaixa.php`
- **Rota:** `?url=fecharCaixa` → `CaixaController@fechar`
- **Tipo:** Formulário
- **Objetivo:** Encerrar o caixa informando o valor final (dinheiro contado).
- **Acesso/Permissão:** —.
- **Principais elementos:** Header + "Cancelar" (→ `./home`). Card de contexto (quem fecha, aberto por/em, valor de abertura). Form `#formFecharCaixa`: "Valor de fechamento (dinheiro contado) *" (sugerido = esperado na gaveta), bloco "Diferença (contado − esperado)" (ao vivo), "Observação". Botão "Fechar Caixa" (confirm "Confirma o fechamento do caixa?").
- **Modais:** Nenhum.
- **Observações:** IMask; JS calcula diferença em tempo real (vermelho falta / verde sobra). `$caixaAtual` = abertura + entradas em dinheiro − saídas.

### Caixa — Lançamentos (livro-caixa) — `app/views/Caixa/movimentacoesCaixa.php`
- **Rota:** `?url=caixaMovimentacoes` → `CaixaController@movimentacoes` (submete `registrarMovimentacaoCaixa`)
- **Tipo:** Formulário (criar) + Listagem
- **Objetivo:** Registrar entradas/saídas da sessão aberta e exibir resumo e extrato.
- **Acesso/Permissão:** —.
- **Principais elementos:** Header + "Histórico" + "Fechar Caixa". Barra da sessão (aberto por/em, "Caixa atual"). Form `#formMov`: Tipo * (Entrada/Saída), "Forma de pagamento *" (Dinheiro/PIX/Cartão/Boleto; "Só Dinheiro soma no caixa atual..."), "Categoria da saída *" (Peças/Retirada para banco/Outro, só em Saída), "Valor *", "Descrição". Card "Resumo da sessão". Tabela de lançamentos: Data/hora, Tipo, Forma/Categoria (badge "OS" quando origem OS), Descrição, Registrado por, Valor.
- **Modais:** Nenhum.
- **Observações:** JS alterna Forma × Categoria; IMask no Valor.

### Relatório de Caixa (por período) — `app/views/Caixa/relatorioCaixa.php`
- **Rota:** `?url=relatorioCaixa` → `CaixaController@relatorio`
- **Tipo:** Listagem/Relatório (dashboard analítico)
- **Objetivo:** Entradas por forma e saídas por categoria em um período, com totais, gráfico e extrato.
- **Acesso/Permissão:** —.
- **Principais elementos:** Header + "Histórico" + "Baixar PDF" (`./relatorioCaixaPdf?...`). Seletor de período (Diário/Semanal/Mensal/Anual + custom "De...até"). Cards: "Total de entradas", "Total de saídas", "Resultado do período", "Sessões de caixa". Gráfico "Evolução" (`#caixaChart`, barras Entradas verde/Saídas vermelho). Quebra (grid-2): "Entradas por forma" e "Saídas por categoria". Tabela "Lançamentos do período".
- **Modais:** Nenhum.
- **Observações:** Chart.js via `json_para_script($serie)`; `granularidade` define evolução mensal/diária.

### PDF — Relatório de Caixa — `app/views/Caixa/pdfRelatorioCaixa.php`
- **Rota:** `?url=relatorioCaixaPdf` → `CaixaController@exportarRelatorioPdf`
- **Tipo:** PDF/Impressão (template Dompdf)
- **Objetivo:** Gerar o PDF do relatório de caixa por período.
- **Acesso/Permissão:** —.
- **Principais elementos:** Cabeçalho (marca, "Relatório de Caixa", período, intervalo, emitido em); Totais (entradas/saídas/resultado); tabela "Entradas por forma de pagamento"; tabela "Saídas por categoria"; tabela "Lançamentos do período"; rodapé.
- **Modais:** Nenhum.
- **Observações:** Cores em hex literal (Dompdf não resolve `var(--cor)`); layout em tabelas; topbar `#003BDD`.

---

## Relatórios

### Relatórios e Insights — `app/views/Relatorios/listRel.php`
- **Rota:** `?url=listRel` → `RelatorioController@listRelatorio`
- **Tipo:** Listagem/Relatório (dashboard geral)
- **Objetivo:** Acompanhar o desempenho financeiro, operacional e de estoque da oficina.
- **Acesso/Permissão:** — na view (item de menu restrito a ADMIN).
- **Principais elementos:** Header + "Últimos 30 dias" + "Imprimir / Salvar PDF" (`window.print()`). Cards: "Faturamento Total" (delta vs mês anterior), "OS Concluídas", "Ticket Médio", "Produtos em Baixa". Gráfico "Receita Mensal" (`#barChart`, barras com gradiente) + select Mensal/Semanal. Card "OS por Status (Mês)" (Concluídas, Em Manutenção, Aguardando Peça, Diagnóstico, Arquivadas). Card "Clientes Atendidos" (lista com barra proporcional + "Ver clientes").
- **Modais:** Nenhum.
- **Observações:** Chart.js via `json_para_script`; a série "vs Despesas" foi removida (não há controle de despesas).

---

## Layout

### Header / Sidebar + Topbar — `app/views/layout/header.php`
- **Rota:** Parcial/include. Abre o HTML (`<head>` + `<body>`) e o shell (sidebar + topbar + início do `.content`), incluído em todas as telas internas.
- **Tipo:** Layout
- **Objetivo:** Estrutura comum: `<head>` com assets/tema, sidebar, topbar e abertura da área de conteúdo.
- **Acesso/Permissão:** Inicia sessão. `$operaCaixa = user_tipo ∈ {ADMIN, ATENDENTE}` controla "Caixa" e o indicador na topbar. Exclusivos ADMIN: "Relatórios", "Relatório de Caixa", "Usuários", "Documentos".
- **Menu / sidebar (todos os itens e destinos):**
  - "Início" → `./home`
  - "Clientes" → `./listClientes`
  - "Veículos" → `./listVeiculos`
  - "Orçamentos" → `./listOrcamentos`
  - "Ordens de Serviço" → `./listOrdemServico`
  - "Produtos" → `./listProduto`
  - "Movimentações" → `./listMovimentacaoEstoque`
  - "Agenda" → `./listAgendamentos`
  - "Caixa" → `./listCaixa` — **só ADMIN/ATENDENTE**
  - "Painel (TV)" → `./painel` (nova aba)
  - "Relatórios" → `./listRel` — **só ADMIN**
  - "Relatório de Caixa" → `./relatorioCaixa` — **só ADMIN**
  - "Usuários" → `./listUsuarios` — **só ADMIN**
  - "Documentos" → `./listDocumentos` — **só ADMIN**
  - Rodapé da sidebar: toggle de tema mobile, perfil (→ `./perfil`), "Sair" (→ `./logout`); botão recolher (`#sidebarCollapseBtn`).
  - Topbar: hambúrguer mobile; menu "Criar" (`<details>`): "Orçamento" (→ `./novoOrcamento`), "Ordem de Serviço" (→ `./saveOrdemServico`), "Cliente" (→ `./saveCliente`), "Veículo" (→ `./saveVeiculo`), "Produto" (→ `./saveProduto`); toggle de tema (`#darkToggle`); indicador de caixa (`$operaCaixa`): aberto → "Caixa aberto · <nome>" (→ `./caixaMovimentacoes`), fechado → "Caixa fechado" (→ `./abrirCaixa`); bloco de usuário (→ `./perfil`).
- **Modais:** Nenhum.
- **Observações:** `menuAtivo()` marca `is-active` pela rota (`$_GET['url']`); `Csrf::token()` em `<meta>`; assets locais (`vendor/`) com `?v=time()`; carrega Chart.js, IMask, Tom Select, Bootstrap Icons e scripts próprios; aplica tema antes de renderizar.

### Footer — `app/views/layout/footer.php`
- **Rota:** Parcial/include. Fecha `.content/.main/.app` e o HTML, carregando scripts globais.
- **Tipo:** Layout
- **Objetivo:** Encerrar o layout e inicializar comportamentos globais (tema, favicon, sidebar, menu "Criar", gaveta mobile) + máscaras/formatação/CSRF/erros.
- **Modais:** Nenhum.
- **Observações:** JS: dark mode (`#darkToggle`, redesenha gráficos), favicon dinâmico, recolher/expandir sidebar (persistido), fechar menu "Criar" ao clicar fora, gaveta mobile. Carrega `masks.js`, `text-format.js`, `csrf.js`, `erros.js` (rede de segurança do cliente para quedas de conexão/erros de JS).

### Alerta inline (parcial) — `app/views/layout/_alerta.php`
- **Rota:** Parcial/include. Incluído por várias telas (login, listUsuarios, editUsuario, agendamentos etc.).
- **Tipo:** Parcial/Componente
- **Objetivo:** Alerta inline padronizado (erro/aviso/sucesso/info) no lugar de blocos copiados em ~25 telas.
- **Entrada:** `$alertaTipo` (padrão 'erro'), `$alertaMensagem` (nada renderiza se vazio), `$alertaTitulo` (opcional).
- **Principais elementos:** `<div class="alerta alerta--<tipo>">` com `role` "alert"/"status", ícone SVG por tipo, título opcional + mensagem (ambos com `htmlspecialchars`).
- **Modais:** Nenhum.
- **Observações:** Ícone próprio por tom (acessibilidade); valida o tipo; faz `unset()` das variáveis ao final (evita vazamento para o próximo `require`).

---

*Documento gerado por leitura direta do código-fonte em `app/views/` e `config/routes.php`. Convenções: `?url=<rota>` no roteador; `_arquivo.php` = parcial sem layout; muitas telas de criar/visualizar são modais overlay; permissões reais via `requireRole()` nos controllers.*
