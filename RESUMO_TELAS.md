# Mecanize — Resumo de todas as telas

> Gerado a partir do código (HEAD do git). Cobre layout, site público, usuários, clientes, veículos, agendamentos, orçamentos, ordens de serviço, caixa, produtos, categorias, estoque e relatórios.

# Mecanize — Documentação de telas (Parte 1)

Escopo: `app/views/layout/*`, `app/views/Site/*` (incluindo `erros/`) e `app/views/Usuarios/*`.
Fonte: `git show HEAD:...` (controllers `SiteController`, `UserController`, `ErrorController`, `RastreioController`, `config/routes.php`, `public/index.php`, `app/core/Controller.php`, `app/core/Erro.php`).

## Convenções gerais do sistema (válidas para todas as telas abaixo)

**Cargos (campo `usuarios.tipo`)**: `ADMIN` (Administrador), `TECNICO` (Técnico) e `ATENDENTE` (Atendente).

**Roteamento**: toda URL é `./<rota>` (rewrite via `.htaccess`, parâmetro interno `url`). Parâmetros extras vão por `&` (ex.: `./viewUsuario&id=5`). Rotas ficam em `config/routes.php`.

**Regras do front-controller (`public/index.php`) que afetam todas as telas logadas**
- **Rotas públicas (sem login)**: `login`, `logout`, `recuperar-senha`, `redefinir-senha`, `termos`, `privacidade`, `rastrear` e as telas de erro (`erro404`, `erroPermissao`, `erroIndisponivel`, `erroConexao`, `erroTempo`). Qualquer outra rota sem sessão redireciona para `./login?retorno=<rota&params>` (GET, exceto `home`). Se a requisição for fetch/XHR, devolve a tela de erro 401 "nao-autenticado" em vez de redirecionar.
- **Inatividade**: após 30 minutos sem requisição, a sessão é encerrada via `./logout?expirado=1`.
- **Conta alterada durante a sessão**: a cada requisição compara `ativo`/`atualizado_em` do usuário no banco com o anotado no login; se a conta sumiu, foi desativada ou foi alterada (nome, e-mail, tipo, status ou senha), faz logout com `?expirado=1`.
- **Janela de atendimento do ATENDENTE**: só usa o sistema de segunda a sexta 07:00–18:00 e sábado 07:00–13:00; fora disso é desconectado (`./logout?expirado=1`).
- **URL inerte**: GET digitado/colado na barra de endereço (sem navegação interna, `navegacaoInterna()`) redireciona para `./home`, exceto as rotas liberadas: `home`, `login`, `logout`, `abrirCaixa`, `fecharCaixa`, `lerPlaca`, `modelosPorMarca`, `criarModelo`, `recuperar-senha`, `redefinir-senha`, `painel` e as telas de erro.
- **Caixa obrigatório**: usuários que não são `TECNICO` são redirecionados para `./abrirCaixa` enquanto não houver caixa aberto, exceto nas rotas: telas de erro, `login`, `logout`, `abrirCaixa`, `fecharCaixa`, `listCaixa`, `recuperar-senha`, `redefinir-senha`, `lerPlaca`, `modelosPorMarca`, `criarModelo`, `marcarFechamentoAuto`, `cancelarFechamentoAuto`, `painel`.
- **CSRF**: todo POST precisa de token (`csrf_field()` / meta `csrf-token` / `public/assets/js/csrf.js`, que injeta o token em todo form e fetch POST na fase de captura do `submit`). Token inválido gera a tela de erro `sessao-expirada`.

**Controle de acesso por cargo (`Controller::requireRole`)**: telas sem `requireRole` são acessíveis a todos os cargos logados (ver, todos veem). Excluir exige ADMIN em todas as telas.

**Renderização de views (`Controller::view`)**: telas normais são envolvidas por `layout/header.php` + `layout/footer.php`. Ficam SEM layout (sem sidebar/topbar): `Usuarios/login`, `Site/termos`, `Site/privacidade`, `Site/painel`, `Site/rastreio`. Views cujo nome de arquivo começa com `_` (fragmentos, ex.: `_tabelaUsuarios`) nunca levam layout. Telas de erro têm tratamento próprio (ver módulo Erros).

---

## Layout

### layout/header.php (cabeçalho, sidebar e topbar)

**Finalidade**: abre o HTML, carrega CSS/JS e desenha a sidebar (menu lateral) e a topbar; o conteúdo da tela fica entre ele e o `footer.php`. Não é uma rota.

**Head / recursos**
- Meta `csrf-token`; título "Mecanize"; favicon `logo-branco.ico`.
- CSS: `inter.css`, `global.css`, `sidebar.css`, `erros.css`, `bootstrap-icons.css`, `tom-select.css`. JS: `chart.umd.min.js`, `imask.min.js`, `tom-select.complete.min.js`, `escape.js`, `cor-select.js`, `select-enhance.js`. Todas as bibliotecas são servidas localmente (`public/assets/vendor/`), sem CDN. Parâmetro `?v=time()` evita cache.
- Script inline aplica o tema escuro antes da renderização: lê `localStorage['mecanize-theme']` (`dark`/`light`); sem preferência salva, segue `prefers-color-scheme` do navegador.

**Sidebar (menu lateral)** — o item ativo recebe a classe `is-active` (comparação com `?url`). Logo no topo leva a `./home`.

| Item | Rota | Quem vê |
|---|---|---|
| Início | `./home` | Todos |
| Clientes | `./listClientes` | Todos |
| Veículos | `./listVeiculos` | Todos |
| Orçamentos | `./listOrcamentos` | Todos |
| Ordens de Serviço | `./listOrdemServico` | Todos |
| Produtos | `./listProduto` | Todos |
| Movimentações (estoque) | `./listMovimentacaoEstoque` | Todos |
| Agenda | `./listAgendamentos` | Todos |
| Caixa | `./listCaixa` | Somente ADMIN e ATENDENTE (`$operaCaixa`); TECNICO não vê |
| Painel (TV) | `./painel` (abre em nova aba, `target="_blank"`) | Todos |
| Relatórios | `./listRel` | Somente ADMIN |
| Relatório de Caixa | `./relatorioCaixa` | Somente ADMIN |
| Usuários | `./listUsuarios` | Somente ADMIN |
| Documentos | `./listDocumentos` | Somente ADMIN |

Observações: itens Início, Relatórios, Relatório de Caixa e Documentos têm a classe `mob-nav` (marcação específica para o menu mobile). Todos os itens têm ícone SVG + rótulo (`.label`).

**Rodapé da sidebar**
- Botão "Tema escuro" (`#darkToggleMobile`, só no mobile/gaveta) que aciona o toggle da topbar.
- Bloco de perfil (link para `./perfil`): avatar com iniciais (primeira e última letra do nome da sessão, maiúsculas), nome e cargo (`user_tipo`).
- Link "Sair" (`./logout`).
- Botão flutuante `#sidebarCollapseBtn` ("Recolher menu"): recolhe/expande a sidebar (classe `sidebar-collapsed` no body, persistida em `localStorage['sidebar-collapsed']`).
- Fundo escuro `#sidebarBackdrop` (mobile): fecha a gaveta ao clicar.

**Topbar**
- Botão hambúrguer (`#mobileNavToggle`, só mobile): abre/fecha a gaveta do menu (classe `mobile-nav-open`); a gaveta fecha ao tocar em item do menu, no backdrop ou se a janela passar de 768px.
- Menu "Criar" (`<details class="topbar-create">`, fecha ao clicar fora) com atalhos: **Orçamento** (`./novoOrcamento`, "Criar novo orçamento"), **Ordem de Serviço** (`./saveOrdemServico`, "Abrir nova OS"), **Cliente** (`./saveCliente`), **Veículo** (`./saveVeiculo`), **Produto** (`./saveProduto`, "Cadastrar item no estoque"). Visível a todos os cargos (a permissão real é checada em cada rota).
- Botão de tema escuro (`#darkToggle`): alterna `data-theme` light/dark no `<html>`, salva em `localStorage['mecanize-theme']` e atualiza os gráficos Chart.js abertos.
- Indicador de caixa (somente ADMIN/ATENDENTE; TECNICO não vê nada):
  - Caixa aberto: link verde "Caixa aberto · <nome de quem abriu>" para `./caixaMovimentacoes` (tooltip "Caixa aberto por ... — clique para ver os lançamentos").
  - Caixa fechado: link cinza "Caixa fechado" para `./abrirCaixa`.
  - O estado vem de `Caixa::getCaixaAberto()`; falha na consulta é ignorada.
- Atalho do usuário (`./perfil`): avatar com iniciais, nome e cargo.

### layout/footer.php (rodapé e scripts globais)

**Finalidade**: fecha o conteúdo e os contêineres `.main`/`.app` e carrega os scripts globais. Não é uma rota.

**JS embutido**
- Toggle de modo escuro (descrito acima), com `Chart.instances.update()` para redesenhar gráficos.
- Favicon dinâmico: segue `prefers-color-scheme` do navegador/SO (não o tema do sistema) — `m-logo-branco.png` em navegador escuro, `m-logo-escuro.png` em claro; reage à mudança de preferência.
- Recolher sidebar (restaura estado salvo), fechar o menu "Criar" ao clicar fora, gaveta mobile (hambúrguer, backdrop, fechar ao navegar, fechar no resize > 768px).
- Scripts carregados no fim: `masks.js` (máscaras de campos), `text-format.js` (capitalização de palavras em campos `.js-capitalize-words`: maiúscula no início e após espaço), `csrf.js` (token CSRF em todo POST) e `erros.js` (rede de segurança do cliente: aviso discreto não bloqueante ao cair/voltar a conexão, captura de erros JS não tratados e helper `Mecanize.buscar()` com timeout; também alimenta as ações das telas de erro).

### layout/_alerta.php (componente de alerta inline)

**Finalidade**: parcial reutilizável para mensagens de erro de validação, aviso, sucesso e informação dentro dos formulários (mantém o usuário na tela e preserva o que ele digitou).

**Variáveis**: `$alertaTipo` (`erro` padrão | `aviso` | `sucesso` | `info`; valor inválido vira `erro`), `$alertaMensagem` (nada é renderizado se vazio), `$alertaTitulo` (opcional, em negrito). Todas são limpas ao final (`unset`) para não vazar ao próximo uso.
**Aparência**: `<div class="alerta alerta--<tipo>">` com ícone SVG próprio por tipo (para não depender só da cor); `role="alert"` para erro e `role="status"` para os demais. Texto escapado com `htmlspecialchars`.
**Usado em**: login, editUsuario, listUsuarios (avisos de exclusão) e outras telas.

---

## Site

### Início / Dashboard (`Site/home.php`)

- **URL**: `./home` (rota `home` → `SiteController::index`). Também é o destino padrão do login e das URLs inertes.
- **Quem acessa**: qualquer usuário logado (sem `requireRole`); `SiteController::index` ainda exige sessão via `core/auth.php`. Exige caixa aberto, exceto TECNICO.
- **Finalidade**: visão geral da oficina e das ordens de serviço. Dados de `Home::getResumo()`.
- **Cabeçalho**: título "Dashboard", subtítulo "Visão geral da oficina e das ordens de serviço."; botão **Nova OS** (`./saveOrdemServico`).

**Cards de indicadores (stat-cards)**
- **OS Abertas** — quantidade de OS com status `ABERTA` ("Aguardando início").
- **Em Andamento** — status `EM_ANDAMENTO` ("Serviços em execução").
- **Aguardando Peça** — status `AGUARDANDO_PECA` ("OS paradas").
- **Faturamento Mensal** (somente ADMIN) — soma de `valor_total_os` das OS `CONCLUIDA` com `data_conclusao` no mês/ano corrente ("OS concluídas no mês"), formato `R$ 0,00`.

**Gráficos (Chart.js, inicializados no `DOMContentLoaded` com atraso de 50 ms)**
- **Fluxo de OS × Receita** (badge "Últimos 7 dias"): gráfico de linha/área com rótulos fixos `Seg…Dom`. Série "Ordens de Serviço" (azul `#003BDD`, OS abertas por dia nos últimos 7 dias, contados por `data_abertura`) para todos; série "Receita (R$)" (tracejada, azul claro; soma de OS concluídas por dia por `data_conclusao`) **somente ADMIN** — para os demais os valores nem são enviados ao HTML. Legenda manual acima do gráfico.
- **Status das OS**: donut com texto central (total de OS e "Total de OS") e legenda lateral com contagem e percentual. Fatias: Concluídas, Em andamento, Pendentes (= `AGUARDANDO_PECA`), Canceladas, Abertas. Tooltip mostra `rótulo: valor (pct%)`.
- **Faturamento por Mês** (somente ADMIN; badge "Últimos 6 meses"): gráfico de barras com faturamento (OS concluídas) dos últimos 6 meses incluindo o atual; eixo Y e tooltip em `R$`.
- **Ticket Médio & Peças Mais Vendidas** (somente ADMIN): dois mini-cards "Ticket médio (mês)" e "Ticket médio (geral)" (média de `valor_total_os` das OS concluídas) e lista com barras de progresso das 6 peças mais vendidas (soma de `quantidade` em `ordem_servico_produtos`, formato "N un."); vazio: "Nenhuma peça vendida ainda.".

**Tabela "Últimas Ordens de Serviço"** (5 OS mais recentes por `data_abertura`; link "Ver todas" → `./listOrdemServico`)
- Colunas: **ID** (`#id`), **Cliente**, **Veículo** (marca + modelo + ano, com placa embaixo), **Valor Total** (R$), **Status** (badge: Concluída=sucesso, Em andamento=info, Aguardando peça=aviso, Cancelada=perigo, Aberta=secundário), **Data** (`d/m/Y`), **Ações**.
- Ações por linha: **Visualizar** (`./viewOrdemServico&id=`), **Editar** (`./editOrdemServico&id=`).
- Vazio: "Nenhuma ordem de serviço encontrada.".

**Regras visíveis**: dados financeiros só são impressos para ADMIN (nem no código-fonte da página para outros cargos). Tema dos gráficos acompanha o modo escuro (cor de grade e textos lidos de variáveis CSS).

### Painel TV (`Site/painel.php`)

- **URL**: `./painel` (rota `painel` → `SiteController::painel`). Abre em nova aba pelo item "Painel (TV)" do menu.
- **Quem acessa**: exige login (não está na lista de rotas públicas), mas qualquer cargo; liberada da regra de "caixa obrigatório" e da "URL inerte" (pode ser aberta/favoritada).
- **Finalidade**: tela "modo TV" para monitor da sala de espera. Sem layout padrão. Mostra apenas placa e primeiro nome do cliente (sem dado sensível).
- **Atualização**: `<meta http-equiv="refresh" content="30">` — recarrega sozinho a cada 30 segundos. Relógio (HH:MM, `pt-BR`) atualizado a cada segundo via JS.
- **Cabeçalho**: marca "Mecanize", relógio e botão de tema claro/escuro (`#temaToggle`; salva em `localStorage['mecanize-theme-painel']`, chave independente do sistema; padrão segue `prefers-color-scheme`). Favicon dinâmico por preferência do SO.
- **Colunas**
  - **Em Andamento**: OS com status `EM_ANDAMENTO` ou `AGUARDANDO_PECA` (`OrdemServico::listarAtivas`). Vazio: "Nenhum veículo em andamento.".
  - **Pronto para Retirada**: OS `CONCLUIDA` com `data_conclusao` nas últimas 48 horas (`listarConcluidasRecentes(48)`), mais recentes primeiro. Vazio: "Nenhum veículo pronto no momento.".
  - Cada card: placa (maiúscula, fonte monoespaçada, selo azul) e primeiro nome do cliente.
- Sem formulários, filtros ou ações.

### Rastreamento público (`Site/rastreio.php`)

- **URL**: `./rastrear?token=<token>` (rota `rastrear` → `RastreioController::ver`).
- **Quem acessa**: público, sem login (rota em `$rotasPublicas`) — pensada para o cliente da oficina. Sem layout.
- **Lógica**: sem `token` ou token que não casa com nenhuma OS nem orçamento → tela de erro `rastreio-invalido` (HTTP 404, versão autônoma). Procura primeiro em OS (`OrdemServico::getPorTokenRastreio`), depois em Orçamento (`Orcamento::getPorTokenRastreio`).
- **Conteúdo** (dados mínimos e públicos): marca "Mecanize"; saudação "Olá, <primeiro nome>. Acompanhe abaixo sua Ordem de Serviço / seu orçamento #número."; veículo (marca + modelo) com a placa em maiúsculas.
- **Linha do tempo (stepper)** com círculos numerados (✓ nas etapas concluídas, destaque na etapa atual) e linhas de ligação:
  - **OS**: Aberta → Em Andamento → Aguardando Peça → Concluída (status `ABERTA`, `EM_ANDAMENTO`, `AGUARDANDO_PECA`, `CONCLUIDA`).
  - **Orçamento**: Criado → Aguardando aprovação → Aprovação (o último rótulo vira "Aprovado" ou "Recusado" conforme status `aprovado`/`reprovado`; `pendente` marca "Aguardando aprovação").
  - **Cancelado** (OS `CANCELADA` / orçamento `cancelado`): no lugar do stepper aparece faixa vermelha "Esta Ordem de Serviço foi cancelada." / "Este orçamento foi cancelado.".
- Rodapé: "Esta página é atualizada automaticamente conforme o andamento avança." (o texto é só informativo; não há `refresh` automático no código desta view). Tema escuro segue o navegador (`prefers-color-scheme`).
- Sem formulários nem botões.

### Termos de Uso (`Site/termos.php`)

- **URL**: `./termos` (rota `termos` → `SiteController::termos`). **Público** (sem login); sem layout. Link no rodapé do login.
- **Estrutura**: barra de progresso de leitura no topo (`#legalProgressFill`), cabeçalho fixo com logo (link `./login`) e botão "Voltar ao login" (`./login`), título "Termos de Uso", "Última atualização: Julho de 2026", sumário lateral (`.legal-toc`) com âncoras e corpo com 21 seções.
- **Seções**: 1 Introdução; 2 Sobre a Mecanize; 3 Aceitação dos Termos; 4 Descrição dos Serviços; 5 Cadastro e Conta; 6 Planos, Ativação e Assinaturas; 7 Cancelamento; 8 Pagamentos e Inadimplência; 9 Dados do Cliente; 10 Responsabilidades do Cliente; 11 Responsabilidades da Mecanize; 12 Disponibilidade da Plataforma; 13 Suporte Técnico; 14 White Label; 15 Propriedade Intelectual; 16 Privacidade e LGPD (link para `./privacidade`); 17 Suspensão e Encerramento; 18 Alterações destes Termos; 19 Legislação Aplicável; 20 Contato; 21 Disposições Finais.
- **Pontos de conteúdo relevantes**: plataforma SaaS com taxa de ativação + assinatura mensal; cancelamento recomendado com 24 h de antecedência da renovação, sem reembolso do período vigente; inadimplência: 48 h de tolerância, depois acesso suspenso mas dados mantidos; dados pertencem ao cliente; marca/identidade do cliente (white label) permanecem dele, código e tecnologia da Mecanize.
- **JS**: o rolo da página é o contêiner `.legal-page`; no evento `scroll` atualiza a largura da barra de progresso, adiciona `is-scrolled` ao cabeçalho (após 8 px) e destaca no sumário a seção visível.
- **Rodapé**: links "Termos" (`./termos`) e "Privacidade" (`./privacidade`).

### Política de Privacidade (`Site/privacidade.php`)

- **URL**: `./privacidade` (rota `privacidade` → `SiteController::privacidade`). **Público**; sem layout; mesmo modelo de página legal dos Termos (barra de progresso, cabeçalho com "Voltar ao login", sumário, mesmo JS de scroll).
- **Seções (15)**: 1 Introdução; 2 Quem somos; 3 Quais dados coletamos (dados cadastrais, operacionais e técnicos como IP, navegador, logs); 4 Finalidade do tratamento; 5 Base legal (execução de contrato, obrigação legal, exercício de direitos, legítimo interesse, consentimento); 6 Compartilhamento (não vende dados); 7 Armazenamento dos dados; 8 Tempo de retenção; 9 Suspensão por inadimplência; 10 Direitos do titular (LGPD); 11 Cookies; 12 Responsabilidades do cliente; 13 Atualizações desta Política; 14 Contato; 15 Disposições Finais.
- Rodapé com links para Termos e Privacidade.

### Rotas do `SiteController` sem rota registrada
`sobre()`, `contato()`, `recuperarLogin()`, `tabela()` e `home()` existem no controller, mas só `home` (via `index`), `painel`, `termos` e `privacidade` estão em `routes.php`; `erro404()` do SiteController é substituída pela rota `erro404` do `ErrorController`. As views `site/sobre`, `site/contato`, `site/recuperarLogin` e `site/tabela` não existem no repositório.

---

## Erros

Todas as telas de erro são geradas por `Erro::exibir(tipo, contexto)` (`app/core/Erro.php`), que define o status HTTP, gera um código de ocorrência (8 caracteres hexadecimais maiúsculos) e escolhe o modo de exibição:
- **App**: dentro do layout normal (header + footer, com menu), usado quando há sessão e o erro não é de infraestrutura.
- **Autônomo** (`Site/erros/_autonomo.php`): página fechada em si mesma (CSS e JS embutidos, sem header/footer/sidebar, marca "M Mecanize" no topo), usada quando a própria infraestrutura falhou (`autonomo => true`) ou não há sessão ativa.

Cada tipo do catálogo usa um "arquétipo" (view): `bussola`, `acesso` ou `sistema`.

**Rotas com endereço próprio (`ErrorController`, públicas e liberadas da regra de URL inerte e de caixa obrigatório)**: `./erro404` (nao-encontrado), `./erroPermissao` (sem-permissao), `./erroIndisponivel` (indisponivel — página de manutenção), `./erroConexao` (sem-conexao; o JS redireciona para cá quando o navegador está offline), `./erroTempo` (tempo-esgotado). Os demais tipos são disparados pelo fluxo.

**Catálogo de tipos**

| Tipo | HTTP | Arquétipo | Tom | Título | Autônoma |
|---|---|---|---|---|---|
| nao-encontrado | 404 | bussola | info | Esta página não existe | Não |
| registro-inexistente | 404 | bussola | info | Registro não encontrado | Não |
| rastreio-invalido | 404 | bussola | info | Link de acompanhamento inválido | Sim |
| sem-permissao | 403 | acesso | danger | Você não tem acesso a esta área | Não |
| nao-autenticado | 401 | acesso | info | Você precisa entrar de novo | Sim |
| sessao-expirada | 403 | acesso | warning | Esta página ficou aberta tempo demais | Não |
| falha-interna | 500 | sistema | danger | Algo falhou do nosso lado | Sim |
| indisponivel | 503 | sistema | warning | Sistema temporariamente indisponível | Sim |
| sem-conexao | 503 | sistema | warning | Sem conexão com a internet | Sim |
| tempo-esgotado | 504 | sistema | warning | A operação demorou demais | Sim |

Tipo desconhecido cai em `falha-interna`. Cada entrada traz também `resumo` (o que aconteceu) e `porque` (explicação mostrada na caixa de informação). Nunca expõe stack trace, caminho de arquivo ou mensagem do banco (detalhe técnico vai só para o log com `[MECANIZE:<ocorrência>]`).

### Erros — casca autônoma (`Site/erros/_autonomo.php`)
- Página HTML completa (`lang="pt-BR"`, tema claro fixo, título "Mecanize — <título do erro>"), CSS do `erros.css` e JS do `erros.js` embutidos inline; fonte do sistema como fallback. Marca "M Mecanize" no topo central. Inclui a view do arquétipo.

### Erros — Arquétipo Bússola (`Site/erros/bussola.php`)
- **Tipos**: nao-encontrado, registro-inexistente, rastreio-invalido. Para o usuário que "se perdeu".
- **Arte**: bússola animada (agulha) para 404 de endereço; caixa vazia para registro removido.
- **Selo/código**: "Erro 404", "Registro removido" ou "Link não reconhecido".
- **Título**: para registro-inexistente com `entidade` informada, "<Entidade> não encontrado" (ex.: "Cliente não encontrado"); senão o título do catálogo. Mostra `resumo`, `detalhe` (opcional) e caixa "porque".
- **Botões**
  - rastreio-invalido (cliente final): "Tentar de novo" (`data-erro-tentar`, recarrega via GET) e texto "Confira se o endereço foi copiado por inteiro. Se o problema continuar, entre em contato com a oficina informando a placa do veículo."; sem "início" nem "voltar".
  - Se o contexto trouxe `voltar`: "Voltar para a lista" (link para a listagem de origem).
  - Caso contrário: "Ir para o início" (`./home`).
  - Demais tipos: "Voltar à página anterior" (`data-erro-voltar`, usa histórico do navegador).
- **Atalhos "Ir direto para"** (somente se logado, não autônoma e não for registro-inexistente): Início, Clientes (`./listClientes`), Ordens (`./listOrdemServico`), Agenda (`./listAgendamentos`) e, só para ADMIN, Relatórios (`./listRel`).

### Erros — Arquétipo Acesso (`Site/erros/acesso.php`)
- **Tipos**: sem-permissao (403), nao-autenticado (401), sessao-expirada (403 "selo vencido").
- **Arte/selo**: cadeado (sem-permissao, selo "403 — Acesso restrito"), crachá (nao-autenticado, selo "401 — Não autenticado"), relógio (sessao-expirada, texto "Sessão expirada").
- **Identidade**: em sem-permissao com sessão, exibe cartão com iniciais, nome e "Conectado como <Administrador/Técnico/Atendente>".
- **Botões**
  - sessao-expirada: "Voltar e enviar de novo" (histórico do navegador, que devolve os campos preenchidos) e "Ir para o início".
  - nao-autenticado: "Entrar novamente" (`./login?retorno=<URL pedida>`, para voltar à tela depois do login).
  - sem-permissao: "Voltar", "Ir para o início" e "Ver meu perfil" (`./perfil`); texto "Precisa deste acesso? Peça a um administrador para ajustar o seu perfil em Usuários.".
- Disparada por `requireRole()` quando o cargo não está na lista da rota.

### Erros — Arquétipo Sistema (`Site/erros/sistema.php`)
- **Tipos**: falha-interna, indisponivel, sem-conexao, tempo-esgotado. Falha do lado do sistema.
- **Ícones**: triângulo de atenção (falha-interna), chave de boca (indisponivel/manutenção), sinal cortado com ondas piscando (sem-conexao), ampulheta (tempo-esgotado). Barra colorida superior.
- **Código da ocorrência** (somente falha-interna): rótulo "Código da ocorrência", código em destaque e botão de copiar (`data-erro-copiar`, "Copiar código").
- **Botões**: "Tentar novamente" (`data-erro-tentar`, com spinner; refaz como GET para não reenviar POST); "Ir para o início" (`./home`) se logado e não autônoma, senão "Ir para o login"; em falha-interna também "Falar com o suporte" (mailto `suporte@mecanize.com.br` com assunto e corpo pré-preenchidos com o código da ocorrência).
- **Nova tentativa automática** (indisponivel, tempo-esgotado, sem-conexao): contagem regressiva "Nova tentativa automática em Ns" — 30 s para `indisponivel`, 15 s para os outros; congela com a aba em segundo plano; em sem-conexao dispara assim que a rede volta. Falha-interna não repete sozinha.

---

## Usuários

Todas as rotas de gestão de usuários exigem `requireRole(['ADMIN'])` (os demais cargos recebem a tela de erro sem-permissão). Exceções: `login`, `logout`, `recuperar-senha`, `redefinir-senha` (públicas), `perfil` e `updateSenha` (qualquer usuário logado, editando só a si mesmo).

### Login (`Usuarios/login.php`)

- **URL**: `./login` (GET exibe; POST autentica). Pública; sem layout (tela dividida: imagem `fundo.png` com logo à esquerda, formulário à direita com fundo decorativo).
- **Formulário** (`POST ./login`, `novalidate`):
  - `email` — `type="email"`, obrigatório, placeholder "seu@email.com", `autocomplete="username"`.
  - `senha` — `type="password"`, obrigatório, placeholder "Digite sua senha", `autocomplete="current-password"`; botão "olho" (`#togglesenha`) alterna entre mostrar/ocultar.
  - `retorno` (hidden, só se houver) — rota que a pessoa tentou abrir antes do login.
- **Botões/links**: "Entrar" (submit); "Esqueceu a senha?" → `./recuperar-senha`; rodapé "© <ano> Mecanize", links "Termos" (`./termos`) e "Privacidade" (`./privacidade`).
- **Mensagens**: `$error` via `_alerta` (tipo erro); `$aviso` em faixa amarela (usado com `?expirado=1`: "Seu horário de acesso foi encerrado. Atendentes têm acesso de segunda a sexta das 07:00 às 18:00 e aos sábados das 07:00 às 13:00." — o mesmo parâmetro também é usado no logout por inatividade/conta alterada).
- **Regras de negócio (`UserController::login`)**:
  - Senha conferida (`password_verify`) ANTES do bloqueio, para não revelar se o e-mail existe: e-mail inexistente ou senha errada dão sempre "Login inválido."; se o e-mail existe, incrementa o contador de tentativas.
  - Bloqueio temporário: 5 tentativas erradas seguidas bloqueiam a conta por 15 minutos (`MAX_TENTATIVAS_LOGIN`/`BLOQUEIO_MINUTOS` em `User`). Quem acerta a senha durante o bloqueio vê "Conta temporariamente bloqueada por excesso de tentativas. Tente novamente em N minuto(s).". Login correto zera o contador.
  - Usuário inativo (`ativo != 1`): "Usuário bloqueado.".
  - ATENDENTE fora da janela (seg–sex 07:00–18:00, sáb 07:00–13:00, fuso America/Sao_Paulo): mensagem com o horário permitido.
  - Sucesso: `session_regenerate_id(true)`; guarda na sessão apenas `user_id_usuario`, `user_nome`, `user_email`, `user_tipo`, `user_atualizado_em` e `ultimo_acesso` (nunca o hash da senha); redireciona para `retorno` se for rota registrada em `routes.php` (evita redirecionamento aberto), senão `./home`.
- **JS**: toggle de senha; `csrf.js`.
- **Observação**: o redirecionamento após redefinir senha usa `./login?senhaRedefinida=1`, mas a view não exibe mensagem para esse parâmetro.

### Logout (`UserController::logout`, sem view)
- **URL**: `./logout` (pública). Se há usuário logado, caixa aberto e logout voluntário sem `confirmado`, redireciona para `./fecharCaixa?logout=1`. Em logout forçado (`?expirado=1`) nunca passa por essa trava. Se houver caixa aberto, fecha automaticamente com o valor apurado em dinheiro (observação "Fechado automaticamente ao sair do sistema" ou "...ao expirar o horário de acesso"); falha no caixa não impede o logout. Destrói a sessão e redireciona para `./login` (ou `./login?expirado=1`).

### Recuperar senha (`Usuarios/recuperarSenha.php`)

- **URL**: `./recuperar-senha`. Pública (liberada de URL inerte e caixa). Renderizada com layout padrão (header/footer), cartão central de 420 px.
- **Formulário** (`POST ./recuperar-senha`): `email` — `type="email"`, obrigatório, placeholder "seu@email.com". Botão "Enviar link de recuperação". Link "Voltar para o login".
- **Comportamento**: sempre mostra a mesma mensagem de sucesso, exista o e-mail ou não: "Se o e-mail informado estiver cadastrado, você receberá um link para redefinir sua senha em instantes."; após a mensagem o formulário some. Só envia e-mail se o usuário existe e está ativo (`ativo == 1`), com link `redefinir-senha?token=...` válido por 30 minutos (assunto "Recuperação de senha - Mecanize", via `Mailer`). Limite de 3 pedidos por hora por usuário (`PasswordReset::LIMITE_POR_HORA`); acima disso responde igual, mas não envia. Pedir novo link invalida os anteriores ainda válidos. Erros de envio só vão para o log.
- Também exibe `$error` (faixa vermelha) quando presente.

### Redefinir senha (`Usuarios/redefinirSenha.php`)

- **URL**: `./redefinir-senha?token=<token>`. Pública, com layout padrão, cartão central. Sem `token` redireciona para `./login`.
- **Token inválido/expirado/usado**: faixa vermelha "Este link é inválido ou já expirou. Solicite a recuperação novamente." e botão "Solicitar novo link" (`./recuperar-senha`).
- **Formulário** (`POST ./redefinir-senha`): `token` (hidden); `nova_senha` — password, obrigatório, `minlength=6`; `confirmar_senha` — password, obrigatório, `minlength=6`. Botão "Redefinir senha"; link "Voltar para o login".
- **Validação servidor**: nova senha obrigatória, mínimo 6 caracteres e igual à confirmação (`matches`). Em erro, reexibe com mensagem.
- **Sucesso**: grava `password_hash`, marca o token como usado e redireciona para `./login?senhaRedefinida=1`. O `update` altera `atualizado_em`, derrubando sessões abertas daquele usuário.

### Meu Perfil (`Usuarios/perfil.php`)

- **URL**: `./perfil` (GET exibe; POST salva dados pessoais). Qualquer cargo logado (verifica sessão; sem sessão vai a `./login`; sem `user_id_usuario` vai a `./logout`). Link no topo e na sidebar (avatar/nome).
- **Cabeçalho**: "Meu Perfil" — "Gerencie suas informações pessoais, senha e preferências."; alertas de sucesso/erro (`mensagemSucesso`/`mensagemErro`); cartão com avatar (iniciais), nome, e-mail e selo do cargo.
- **Card "Dados Pessoais"** (`POST ./perfil`, sem `action`):
  - `nome` — texto, obrigatório, placeholder "Seu nome completo".
  - `email` — e-mail, obrigatório, placeholder "seu@email.com".
  - `telefone` — texto, opcional, id `campoTelefone`, placeholder "(00) 00000-0000"; máscara IMask com duas variações `(00) 0000-0000` / `(00) 00000-0000`.
  - Tipo de usuário — somente leitura (selo + "Definido pelo administrador").
  - Botão "Salvar Alterações".
  - **Regras (servidor)**: telefone validado como celular BR (`Validator::celularBr`: DDD válido, número não repetido etc.; vazio é aceito); e-mail não pode pertencer a outro usuário ativo ("Já existe outro usuário ativo com este e-mail."); o cargo (`tipo`) e o status (`ativo`) nunca vêm do formulário (evita autopromoção a ADMIN); o campo `senha` do POST, se enviado, seria aplicado, mas a tela não o oferece. Ao salvar, atualiza nome/e-mail/tipo na sessão e reanota `user_atualizado_em` (para não ser deslogado pela checagem de conta alterada). Mensagem "Perfil atualizado com sucesso." ou "Erro ao atualizar perfil.".
- **Card "Segurança"** (`POST ./updateSenha`, id `formSenha`):
  - `senha_atual` — password, obrigatório.
  - `nova_senha` — password, obrigatório, `minlength=6`.
  - `confirmar_senha` — password, obrigatório, `minlength=6`.
  - Cada campo tem botão "olho" (`.password-field__toggle`, `data-target`) para mostrar/ocultar.
  - Botão "Atualizar Senha".
  - **Validação cliente**: se nova e confirmação diferem, cancela o envio e mostra "A nova senha e a confirmação não coincidem." em `#senhaAlertPlaceholder`, rolando ao topo.
  - **Validação servidor (`updateSenha`, só POST)**: todos preenchidos ("Preencha todos os campos de senha."); senha atual correta ("Senha atual incorreta."); confirmação igual ("A nova senha e a confirmação não coincidem."); mínimo 6 caracteres ("A nova senha deve ter pelo menos 6 caracteres."). Sucesso: "Senha atualizada com sucesso."; gera novo ID de sessão e reanota `user_atualizado_em`, mantendo logado quem trocou e derrubando outras sessões com a senha antiga.
- **Observação**: o CSS contém estilos de "notificações" (`notif-row`, `toggle-switch`), mas a view não renderiza essa seção.

### Lista de Usuários (`Usuarios/listUsuarios.php` + fragmento `_tabelaUsuarios.php`)

- **URL**: `./listUsuarios` (parâmetros: `aba` = `ativos` (padrão) | `inativos`, `busca`, `tipo`, `pagina`, `fragmento=1`, e avisos `erro`/`ok`/`detalhe`). **Somente ADMIN**.
- **Finalidade**: gerenciar contas, permissões e status. Paginação de 10 por página, ordenada por `tipo, nome`.
- **Cabeçalho**: "Usuários" — "Gerencie contas, permissões e status de acesso do sistema."; botões **Exportar** (`./exportUsuarios?aba=&busca=&tipo=`) e **Novo Usuário** (`./saveUsuario`).
- **Avisos** (via `_alerta`): erro `vinculos` ("Este usuário não pode ser excluído porque tem histórico na oficina" + detalhe, ex.: "2 aberturas de caixa, 1 orçamento"; sugere desativar); `proprio` ("Você não pode excluir a sua própria conta."); `exclusao` ("Não foi possível excluir este usuário."); sucesso `?ok=excluido` ("Usuário excluído.").
- **Cards de resumo** (sempre globais, independem de aba/filtro): **Total de usuários** (ativos e inativos), **Ativos** ("Com acesso liberado"), **Administradores** ("Perfil com acesso total") e **Novos (30 dias)** (clicável; "Clique para ver os novos usuários").
- **Painel flutuante "Novos Usuários (30 dias)"** (`#panelUsuariosNovos30d`): abre ao clicar no card, posicionado por JS logo abaixo do card (sem vazar pela borda direita); fecha por botão ×, clique fora ou tecla Esc. Duas telas deslizantes:
  1. Lista: `fetch('./listUsuariosNovos30d')` (JSON `{sucesso, usuarios}`, ADMIN) — linhas com avatar (cor por cargo), nome, "<Cargo> · desde <data>" e selo "Novo"; vazio "Nenhum usuário novo nos últimos 30 dias."; erro "Erro ao carregar novos usuários.".
  2. Perfil: ao clicar numa linha, `fetch('./usuarioPerfilJson?id=')` (JSON; nunca inclui hash da senha; 400 sem id, 404 se não existe) mostrando avatar, nome, tag "Novo", Tipo, Status, E-mail, Cadastrado em, Última atualização; botão "Voltar" volta à lista.
- **Abas**: "Usuários Ativos" e "Usuários Inativos", cada uma com contador. A aba decide o recorte por status (ativos = 1, inativos = 0). Troca via JS sem recarregar a página (fetch da mesma rota com `fragmento=1`), usando `history.pushState` e tratando `popstate`; se o fetch falhar ou a resposta for redirecionada (sessão caída / caixa fechado), navega normalmente. Sem JS funciona como links comuns. Respostas antigas não sobrescrevem as novas.
- **Toolbar de filtros** (form GET `data-usuarios-filtro`, dentro do fragmento):
  - `aba` (hidden).
  - `busca` — texto "Buscar por nome ou e-mail..." (LIKE em nome e e-mail).
  - `tipo` — select "Tipo: Todos" / Admin / Técnico / Atendente (envia ao mudar).
  - Botão **Filtrar**; botão **Limpar** (aparece só com filtro ativo); contador "N resultado(s)". Novo filtro recomeça na página 1. O link Exportar é sincronizado com a aba/filtros atuais.
- **Colunas da tabela**: **Usuário** (avatar com iniciais, cor por cargo — Admin escuro, Técnico azul, Atendente laranja —, nome e `#id`), **Contato** (e-mail), **Tipo** (badge: Admin=info, Técnico=secundário, Atendente=aviso), **Status** (badge clicável), **Criado em** (`d/m/Y`), **Ações**.
- **Ações por linha**
  - **Status (badge Ativo/Inativo)**: form POST `./toggleStatusUsuario` com `id` e `retorno` (query atual); confirmação JS "Desativar/Ativar este usuário? Ele passa para a aba Inativos / volta para a aba Ativos." Volta para a mesma aba/busca/tipo/página (só essas 4 chaves, nunca `Referer`).
  - **Visualizar** → `./viewUsuario&id=`.
  - **Editar** → `./editUsuario&id=`.
  - **Excluir**: form POST `./deleteUsuario` com `id`; confirmação "Deseja excluir este usuário? Esta ação não pode ser desfeita.".
- **Estados vazios**: "Nenhum usuário encontrado — Tente ajustar os filtros de busca." (com "Limpar filtros"), "Nenhum usuário inativo — Usuários desativados aparecem aqui." ou "Nenhum usuário ativo — Comece cadastrando o primeiro usuário do sistema." (com botão "Novo Usuário").
- **Paginação**: "Mostrando X de Y resultados"; botões ‹ › e números compactos (1ª, última, atual ±1, reticências); links `data-usuarios-link` interceptados por JS (fragmento) e válidos sem JS. Página fora do intervalo é reposicionada para a última válida.
- **Regras de exclusão (`delete`, só POST, ADMIN)**: não pode excluir a si mesmo (`?erro=proprio`); usuário com histórico não é excluído — bloqueio por vínculos em: aberturas de caixa, fechamentos de caixa, lançamentos de caixa (`movimentacoes_caixa`), orçamentos (`mecanico_id`), ordens de serviço (`ordem_servico_mao_obra.tecnico_id`) e agendamentos (`mecanico_id`) (`?erro=vinculos&detalhe=...`); falha de banco → `?erro=exclusao`; sucesso → `?ok=excluido`. GET nessas rotas só redireciona para a lista.
- **Exportar CSV (`exportUsuarios`, ADMIN)**: `usuarios_<Y-m-d_His>.csv`, UTF-8 com BOM, separador `;`, colunas ID, Nome, E-mail, Tipo (Admin/Técnico/Atendente), Status (Ativo/Inativo), Criado em, Atualizado em (`d/m/Y H:i`); respeita aba/busca/tipo da tela; valores iniciados por `=`, `+`, `-` ou `@` recebem apóstrofo (proteção contra CSV injection).

### Novo Usuário (`Usuarios/saveUsuario.php`)

- **URL**: `./saveUsuario` (GET exibe; POST grava). **Somente ADMIN**. Também sem rota de menu direto: acessado por "Novo Usuário" da lista.
- **Layout**: modal (overlay `#modalCriarUsuario`, 760 px) "Novo Usuário — Cadastre um novo usuário no sistema." sobre a página; fechar pelo "×" (`./listUsuarios`) ou clicando fora (JS redireciona a `./listUsuarios`). Faixa de erro (`$error`) no topo do corpo.
- **Formulário** (`POST ./saveUsuario`, id `formCriarUsuario`)
  - ① Dados do Usuário:
    - `nome` — texto, **obrigatório**, "Nome Completo", classe `js-capitalize-words` (capitaliza palavras), placeholder "Digite o nome completo".
    - `email` — e-mail, **obrigatório**, placeholder "usuario@email.com".
    - `senha` — password, **obrigatório**, placeholder "Senha de acesso", botão olho (`#togglePassword`, troca ícone `bi-eye`/`bi-eye-slash`); mínimo de 6 caracteres validado no servidor.
    - `tipo` — select **obrigatório**: Administrador (`ADMIN`), Técnico (`TECNICO`), Atendente (`ATENDENTE`, selecionado por padrão).
  - ② Configurações: `ativo` — radio "Ativo" (1, padrão) / "Inativo" (0).
- **Rodapé do modal**: "Criado em: <hoje>", "Usuário: <nome logado> (<cargo>)", botões **Limpar** (reseta o form, `form.reset()`), **Cancelar** (`./listUsuarios`) e **Gravar** (submit).
- **Validação servidor** (`Validator`): nome, e-mail, senha e tipo obrigatórios; e-mail válido; senha mínimo 6; tipo ∈ {ADMIN, TECNICO, ATENDENTE}; e-mail único ("Este usuário já está cadastrado."). Em erro reexibe o modal com a mensagem do primeiro erro (a view recebe `old`, mas os campos não são repreenchidos). Falha ao gravar: "Erro ao cadastrar usuário.". Sucesso: redireciona para `./listUsuarios`.

### Editar Usuário (`Usuarios/editUsuario.php`)

- **URL**: `./editUsuario&id=<id>` (GET exibe; POST salva). **Somente ADMIN**. Id ausente volta à lista; id inexistente exibe a tela "Usuário não encontrado" (registro-inexistente, com botão de voltar para `./listUsuarios`).
- **Cabeçalho**: "Editar Usuário · <nome>", subtítulo "Atualize as informações e permissões do usuário.", selo de status (Ativo verde / Inativo vermelho) e botão **Voltar** (`./listUsuarios`). Alerta de erro via `_alerta`.
- **Formulário** (`POST ./editUsuario`; hidden `id_usuario`):
  - **1. Informações Pessoais**: `nome` (texto, obrigatório, `js-capitalize-words`); `email` (e-mail, obrigatório); `senha` "Nova Senha" (password, opcional, placeholder "Deixe em branco para manter a senha atual"; se preenchida, mínimo 6 caracteres).
  - **2. Configurações do Usuário**: `tipo` (select obrigatório: Admin/Técnico/Atendente); `ativo` "Status" (select obrigatório: Ativo=1 / Inativo=0). Aviso "Informação de Segurança: Ao alterar a senha, o usuário precisará realizar login novamente no sistema." (consequência da checagem de `atualizado_em` no front-controller).
  - **3. Resumo da Conta** (somente leitura): ID (`#id`), Tipo, Última Atualização (`d/m/Y H:i`), Criado em (`d/m/Y H:i`).
- **Rodapé de ações**: **Excluir Usuário** (botão ligado por `form="form-excluir-usuario"` a um segundo form POST `./deleteUsuario` com `id`, confirmação "Deseja realmente excluir este usuário? Esta ação não pode ser desfeita." — mesmas regras de exclusão da lista), **Cancelar** (`./listUsuarios`) e **Salvar Alterações**.
- **Validação servidor**: nome, e-mail e tipo obrigatórios; e-mail válido; tipo ∈ lista; e-mail único entre outros usuários ativos ("Já existe outro usuário ativo com este e-mail."); senha só validada (mín. 6) se informada; sem senha mantém o hash atual, com senha grava novo `password_hash`. `ativo` não enviado mantém o valor atual. Erro reexibe a tela com os dados digitados; falha de gravação: "Erro ao atualizar usuário."; sucesso: redireciona para `./listUsuarios`.

### Visualizar Usuário (`Usuarios/viewUsuario.php`)

- **URL**: `./viewUsuario&id=<id>`. **Somente ADMIN**. Id ausente volta à lista; inexistente → tela "Usuário não encontrado".
- **Layout**: modal grande (`modal--xl`, `#modalViewUsuario`) "Usuário — <nome> / Visualização detalhada do usuário", com selo de status (Ativo/Inativo) e botão fechar (`javascript:history.back()`).
- **Seções (somente leitura)**
  - ① Identificação: ID do Usuário (`#id`), Data de Cadastro e Última Atualização (`d/m/Y H:i`).
  - ② Dados do Usuário: cartão com avatar (iniciais), nome, e-mail e selo do cargo (Administrador roxo, Técnico azul, Atendente amarelo); campos Nome Completo e Email.
  - ③ Configurações: Tipo de Usuário (selo) e Status do Usuário (bolinha + Ativo/Inativo).
- **Rodapé**: "Cadastro: <data>", "Usuário: <nome logado> (<cargo>)"; botões **Editar** (`./editUsuario&id=`) e **Voltar** (`history.back()`).

### Fragmento da tabela (`Usuarios/_tabelaUsuarios.php`)
- Não é uma tela: é o "miolo" (toolbar de filtros + tabela + paginação) renderizado embutido em `listUsuarios.php` e, sozinho e sem layout, quando `UserController::list` recebe `?fragmento=1` (troca de aba, paginação e busca via fetch). Descrito em detalhe em "Lista de Usuários". Gera links de paginação/limpar preservando `aba`, `busca` e `tipo` (e deixando de fora `fragmento` e os avisos de exclusão).

---

## Resumo de permissões por cargo (telas desta parte)

| Tela / rota | ADMIN | ATENDENTE | TECNICO | Sem login |
|---|---|---|---|---|
| `login`, `logout`, `recuperar-senha`, `redefinir-senha`, `termos`, `privacidade`, `rastrear`, telas de erro | Sim | Sim | Sim | Sim |
| `home` (dashboard) | Sim, com indicadores/gráficos financeiros | Sim, sem dados financeiros | Sim, sem dados financeiros | Não |
| `painel` (TV) | Sim | Sim | Sim | Não (exige login) |
| `perfil`, `updateSenha` | Sim | Sim | Sim | Não |
| `listUsuarios`, `exportUsuarios`, `saveUsuario`, `editUsuario`, `viewUsuario`, `deleteUsuario`, `toggleStatusUsuario`, `listUsuariosNovos30d`, `usuarioPerfilJson` | Sim | Não (erro sem-permissão) | Não (erro sem-permissão) | Não |
| Itens de menu Relatórios, Relatório de Caixa, Usuários, Documentos | Sim | Não | Não | — |
| Item de menu Caixa e indicador de caixa na topbar | Sim | Sim | Não | — |

---

## Clientes

Convenções gerais válidas para todos os módulos desta parte (vêm de `public/index.php`, `app/core/Controller.php` e `config/routes.php`):

- Todas as URLs são servidas pelo front-controller (`public/.htaccess` reescreve para `index.php?url=<rota>`). A rota é o nome (ex.: `./listClientes`) e os parâmetros vão em query string normal (`?id=5`).
- Todas as rotas exigem login. Cargos existentes: `ADMIN`, `ATENDENTE`, `TECNICO`. O que não tem `requireRole` é acessível aos três cargos (listagens, visualizações). Quem não tem o cargo vê a tela de erro "sem permissão".
- Para `ADMIN` e `ATENDENTE`, o sistema redireciona para `./abrirCaixa` enquanto não houver caixa aberto (o `TECNICO` não é obrigado a ter caixa). Há também expiração por inatividade e, para `ATENDENTE`, encerramento fora do horário (seg-sex 07:00-18:00, sáb 07:00-13:00).
- Todo POST passa por verificação de CSRF (`csrf_field()` nos formulários e `csrf.js` para fetch).
- Máscaras de campo vêm de `public/assets/js/masks.js` (IMask): CPF `000.000.000-00` (id `cpf`), CNPJ `00.000.000/0000-00` (id `cnpj`), telefone `(00) 0000-0000` ou `(00) 00000-0000` (id `telefone`), CEP `00000-000` (id `cep`), placa (id `placa`, ver módulo Veículos). `text-format.js` coloca em maiúscula a 1ª letra de cada palavra nos inputs com classe `js-capitalize-words`. `select-enhance.js` transforma todo `<select>` de formulário em Tom Select (com busca), exceto os com classe `toolbar-select`, `#corSelect` (init próprio) ou atributo `data-no-ts`.
- Validações de servidor usadas (classe `Validator`): `required`, `email`, `celularBr` (telefone com DDD; aceita fixo de 10 dígitos ou celular de 11 dígitos começando em 9; rejeita número repetido; mensagem: "Telefone precisa ser um telefone válido, com DDD (ex: (18) 99999-8888 ou (18) 3222-4455)."), `cpfCnpj` (valida dígitos verificadores; mensagem "CPF inválido." / "CNPJ inválido.").

### Listagem de Clientes (Gestão de Clientes)

- **Arquivos:** `app/views/Clientes/listClientes.php` (página) e `app/views/Clientes/_tabelaClientes.php` (miolo: busca, tabela e paginação).
- **URL / rota:** `./listClientes` (`ClienteController::list`). Parâmetros GET: `aba` (`ativos` padrão | `inativos`), `busca`, `pagina`, `fragmento=1`.
- **Quem acessa:** todos os cargos (sem `requireRole`). Os botões de desativar e reativar só aparecem para `ADMIN`; "Editar", "+ Novo Cliente" e "+ Cliente Balcão" aparecem para todos, mas as rotas de destino exigem `ADMIN` ou `ATENDENTE` (o `TECNICO` cai em "sem permissão").
- **Finalidade:** listar, buscar e acompanhar clientes da oficina, separados em abas Ativos / Inativos, com indicadores no topo.
- **Cabeçalho:** título "Gestão de Clientes", subtítulo "Gerencie e acompanhe os clientes da oficina." Botões:
  - "+ Cliente Balcão" -> `./saveCliente?balcao=1` (cadastro rápido de cliente genérico, sem nome).
  - "+ Novo Cliente" -> `./saveCliente`.
- **Cards / indicadores:**
  - "Total de Clientes": número de clientes ativos (`ativo = 1`). Legenda "Total de clientes cadastrados". Não é clicável.
  - "Novos esse Mês": clientes ativos criados no mês/ano corrente. Clicável (também por teclado): abre painel flutuante "Novos Clientes do Mês".
  - "Clientes VIP / Recorrentes": quantidade de clientes ativos com 2 ou mais Ordens de Serviço. Clicável: abre painel flutuante "Clientes VIP & Recorrentes".
- **Painéis flutuantes (VIP e Novos do mês):** `position: fixed`, ancorados abaixo do card pelo JS (`getBoundingClientRect`), reposicionados em scroll/resize. Têm duas telas deslizantes:
  1. Lista (carregada por `fetch`): VIP busca `./vipList` (JSON; ordenado por total de visitas desc., depois nome) e mostra avatar com iniciais, nome, "veículo principal · última visita" e um selo `Nx` (nº de OS). Novos do mês busca `./novosMesList` (JSON; ordenado por `criado_em` desc.) e mostra "veículo principal · desde <data>" com selo "Novo". Mensagens vazias: "Nenhum cliente VIP encontrado ainda." / "Nenhum cliente novo este mês ainda."; erro: "Erro ao carregar clientes VIP." / "Erro ao carregar novos clientes."
  2. Perfil (ao clicar numa linha; `fetch ./vipPerfil?id=<id>`, JSON): avatar, nome, tag ("★ VIP · N visitas" ou "✦ Novo cliente · desde <data>"), grade com Telefone, E-mail, Documento (CPF ou CNPJ), Cliente desde, Endereço (endereço, bairro, cidade, UF), Primeira visita, Última visita, e lista "Veículos" (marca modelo · ano) ou "Nenhum veículo cadastrado." Campos vazios mostram "Não informado" / "Sem registro".
  - Botões do painel: "Voltar" (volta à lista) e "X" (fecha). Também fecha ao clicar fora e com a tecla Esc. Clicar de novo no card alterna abrir/fechar.
  - `vipPerfil` responde 400 se faltar `id` e 404 se o cliente não existir (JSON com `erro`).
- **Abas:** "Clientes Ativos" e "Clientes Inativos", cada uma com contador global (não muda com a busca). A aba define o recorte (`ativo = 1` ou `ativo = 0`); qualquer valor diferente de `inativos` cai em `ativos`.
- **Toolbar de busca (dentro de `_tabelaClientes.php`):**
  - Campo de texto `busca` (placeholder "Buscar por nome, telefone ou e-mail..."); no servidor a busca é `LIKE %termo%` em nome, telefone, e-mail, CPF e CNPJ.
  - Botão "Filtrar" (submit GET). Botão "Limpar" (vermelho; só aparece com busca ativa) volta à aba sem busca.
  - Contador "N resultado(s)" (quantidade de linhas da página atual).
  - Campo oculto `aba` preserva a aba.
- **Tabela (10 registros por página):** colunas Cliente (avatar com 2 iniciais, nome, CPF/CNPJ ou "Sem documento"), Contato (telefone ou "Sem telefone"; e-mail ou "Sem e-mail"), Veículos Cadastrados (primeiro veículo "Marca Modelo Ano" e selo `+N` se houver mais de um; ou "Nenhum veículo"), Última Visita (maior data de abertura de OS, ou "Sem visita"), Status (selo verde "Ativo") ou, na aba inativos, "Desativado em" (data ou "—"), Ações. Na aba inativos as linhas ficam esmaecidas com nome riscado.
  - Ordenação: ativos em ordem alfabética com "Cliente Balcão" fixo no topo; inativos por `desativado_em` mais recente primeiro.
  - Estado vazio: "Nenhum cliente encontrado" (com busca; link "Limpar busca"), "Nenhum cliente desativado" (inativos) ou "Nenhum cliente ativo" (com botão "Novo Cliente").
- **Botões de ação por linha:**
  - Reativar (ícone de setas; só `ADMIN`, só na aba Inativos): POST `./reativarCliente` com `id`; confirmação "Deseja reativar este cliente?". Volta à aba inativos preservando a busca.
  - Visualizar (olho): `./viewCliente?id=<id>` (ativos e inativos).
  - Editar (lápis; só na aba Ativos): `./editCliente?id=<id>`.
  - Desativar (lixeira; só `ADMIN`, só na aba Ativos): POST `./deleteCliente` com `id`; confirmação "Deseja desativar este cliente? Ele não será excluído permanentemente." (é exclusão lógica: `ativo = 0`, `desativado_em = NOW()`).
- **Paginação:** "Mostrando X de Y resultados"; botões `‹`, página anterior/atual/próxima e `›` (janela de 3 páginas). Se a página pedida ficar fora do intervalo (ex.: após filtrar), o servidor reposiciona na última página válida.
- **JS relevante:**
  - Troca de aba, paginação, "Limpar" e busca são feitas por `fetch` na própria rota com `?fragmento=1` (o controller devolve só `_tabelaClientes.php` sem layout) e substituem `#clientes-conteudo`; atualiza a URL via `history.pushState` (sem o parâmetro `fragmento`) e responde a voltar/avançar do navegador (`popstate`). Respostas antigas são descartadas (cliques rápidos). Se a sessão cair (redirect) ou o fetch falhar, faz navegação normal. Sem JS tudo funciona recarregando a página (links e formulário GET reais). Nova busca zera a página.
  - Painéis VIP/Novos descritos acima.
- **Regras de negócio visíveis:** desativar não apaga o cliente; reativar e desativar são exclusivos de `ADMIN` e feitos por POST; rota antiga `./listClientesDesativados` apenas redireciona para `./listClientes?aba=inativos`.

### Novo Cliente (cadastro)

- **Arquivo:** `app/views/Clientes/saveCliente.php` (exibido como modal em tela cheia).
- **URL / rota:** `./saveCliente` (`ClienteController::save`; GET abre o formulário, POST grava). `./saveCliente?balcao=1` abre pré-preenchido como "Cliente Balcão".
- **Quem acessa:** `ADMIN` e `ATENDENTE`.
- **Finalidade:** cadastrar um novo cliente (pessoa física ou jurídica).
- **Cabeçalho:** "Novo Cliente — Preencha os dados abaixo para cadastrar um novo cliente." Com `?balcao=1` o JS troca para "Novo Cliente Balcão — Cliente genérico de balcão. Ajuste o nome se tiver mais detalhes e grave." Botão "X" volta para `./listClientes`; clicar fora do modal também volta.
- **Mensagem de erro:** caixa vermelha no topo do corpo com a primeira mensagem de validação ou "Erro ao cadastrar cliente[: detalhe]". Observação: a view não reaproveita `$old`, então os campos voltam em branco após um erro.
- **Campos do formulário** (POST `./saveCliente`, form `formCriarCliente`):
  - Seção 1 - Identificação:
    - `tipo_pessoa` (select, obrigatório): Selecione / `PF` Pessoa Física / `PJ` Pessoa Jurídica. Ao mudar, alterna CPF/CNPJ.
    - `nome` (texto, obrigatório; placeholder "Nome completo ou razão social"; capitaliza a 1ª letra de cada palavra). Abaixo, link "Não pegou o nome? Usar 'Cliente Balcão'" que seleciona PF e preenche o nome "Cliente Balcão".
    - `cpf` (texto, `maxlength 14`, máscara `000.000.000-00`, visível quando o tipo não é PJ; opcional, mas se preenchido é validado; único entre ativos).
    - `cnpj` (texto, `maxlength 18`, máscara `00.000.000/0000-00`, oculto até escolher PJ; opcional, validado; único entre ativos). Ao alternar o tipo, o campo escondido é limpo.
    - `email` (`type=email`, opcional; formato validado; único entre clientes ativos, sem diferenciar maiúsculas).
    - `telefone` (texto, `maxlength 15`, máscara de fixo/celular, opcional; validado como telefone brasileiro com DDD; único entre ativos comparando só os dígitos).
  - Seção 2 - Endereço:
    - `cep` (máscara `00000-000`; ao sair do campo (`onblur`) ou clicar no ícone de pin dispara a busca de CEP).
    - `endereco` (texto, "Rua, número, complemento"), `bairro`, `cidade`.
    - `uf` (select com as 27 UFs; padrão SP).
  - Seção 3 - Configurações:
    - `ativo` (rádio Sim/Não, padrão Sim). Atenção: a gravação de novo cliente (`Cliente::create`) não usa esse campo; o cliente é criado ativo.
    - `observacoes` (textarea, 3 linhas).
  - Obrigatórios no servidor: tipo de pessoa e nome. E-mail, telefone, CPF e CNPJ são opcionais, mas validados e checados contra duplicidade entre ativos (mensagens "Já existe um cliente ativo com este e-mail / telefone / CPF / CNPJ."). Há também proteção por índice único no banco contra duplo clique.
- **Rodapé:** informações "Criado em" (data de hoje), "Atualizado em" e "Usuário: nome (cargo)". Botões: "Limpar" (reseta o formulário e reaplica CPF/CNPJ), "Cancelar" (volta à lista), "Gravar" (submit).
- **JS:** `alternarTipoPessoa()`; `buscarCep()` consulta `https://viacep.com.br/ws/<cep>/json/` (só com 8 dígitos) e preenche endereço (logradouro), bairro, cidade e UF; falhas são silenciosas; `preencherBalcao()`.
- **Após gravar:** redireciona para `./listClientes`.

### Editar Cliente

- **Arquivo:** `app/views/Clientes/editCliente.php`.
- **URL / rota:** `./editCliente?id=<id>` (`ClienteController::edit`; GET mostra, POST grava). Sem `id` redireciona para a lista; cliente inexistente mostra a tela "Cliente não encontrado".
- **Quem acessa:** `ADMIN` e `ATENDENTE`. O botão "Desativar Cliente" só aparece para `ADMIN`.
- **Finalidade:** alterar dados cadastrais, ver os veículos do cliente e (ADMIN) desativá-lo.
- **Layout:** cabeçalho "Editar Cliente" com botão "Voltar" (`./listClientes`); caixa de erro vermelha se houver; cinco cards:
  1. Identificação: mesmos campos do cadastro (`tipo_pessoa`, `nome`, `cpf`, `cnpj`, `email`, `telefone`), preenchidos, com as mesmas máscaras, o mesmo alternador CPF/CNPJ (mostra o campo conforme o tipo salvo) e a mesma capitalização do nome. Campo oculto `id_cliente`.
  2. Endereço: `cep` (com busca ViaCEP), `endereco`, `bairro`, `cidade`, `uf` (select das 27 UFs).
  3. Veículos Associados: botão "Adicionar Veículo" e lista de cartões com modelo (marca + modelo), placa, ano e cor, cada um com ícone de "três pontos" que leva à edição do veículo; sem veículos mostra "Nenhum veículo cadastrado." Observação: os links são gerados no código como `./saveVeiculo&id_cliente=<id>` e `./editVeiculo&id=<id>` (com `&` no lugar de `?`); como a rota é lida de `?url=`, esses endereços não correspondem a uma rota registrada, e o parâmetro `id_cliente` também não é lido pela tela de novo veículo.
  4. Configurações: `ativo` (rádio Sim/Não) e `observacoes` (textarea 4 linhas). Aqui `ativo` é gravado: marcar "Não" desativa o cliente e grava `desativado_em = NOW()`; marcar "Sim" limpa `desativado_em`.
  5. Resumo do Cadastro: ID do Cliente (#id), Criado em, Última Atualização (data de hoje, apenas exibição) e Usuário logado (cargo).
- **Rodapé de ações:** "Desativar Cliente" (só ADMIN; POST `./deleteCliente` com `id`, confirmação "Deseja desativar este cliente?"; o formulário fica fora do principal e é ligado pelo atributo `form=`), "Cancelar" e "Salvar Alterações".
- **Validações:** as mesmas do cadastro, com unicidade ignorando o próprio cliente (mensagens "Já existe outro cliente ativo com este e-mail / telefone / CPF / CNPJ."). Em erro, a tela reaparece com os dados digitados mesclados ao registro.
- **Após salvar:** redireciona para `./listClientes`.

### Visualizar Cliente

- **Arquivo:** `app/views/Clientes/viewCliente.php` (modal somente leitura).
- **URL / rota:** `./viewCliente?id=<id>` (`ClienteController::visualizar`). Sem `id` volta à lista; inexistente mostra "Cliente não encontrado".
- **Quem acessa:** todos os cargos.
- **Conteúdo:** cabeçalho "Cliente — <nome>" / "Visualização detalhada do cliente", selo de status (Ativo verde / Inativo vermelho) e botão "X" (`history.back()`). Corpo em quatro seções:
  1. Identificação: ID do Cliente (#id), Data de Cadastro e Última Atualização (formato d/m/Y H:i).
  2. Dados do Cliente: cartão de destaque com avatar de iniciais, nome, e-mail · telefone e selo "Pessoa Física" (azul) / "Pessoa Jurídica" (roxo); grade com Nome, E-mail, Telefone, CPF ou CNPJ (conforme o tipo) e Tipo de Pessoa. Vazios aparecem como "—".
  3. Endereço: CEP, Endereço, Bairro, Cidade, UF.
  4. Configurações: Status (bolinha + texto) e Observações.
- **Rodapé:** "Cadastro: <data>", usuário logado, botão "Editar" (link montado como `./editCliente&id=<id>` no código; ver observação acima sobre o `&`) e "Voltar" (`history.back()`). O botão Editar aparece para todos os cargos, mas a edição exige ADMIN ou ATENDENTE.
- Não há veículos nem histórico de OS nesta tela.

---

## Veículos

### Listagem de Veículos (Gestão de Veículos)

- **Arquivo:** `app/views/Veiculos/listVeiculos.php` (usa os partials de dados `_coresCarro.php` e `_combustiveis.php`).
- **URL / rota:** `./listVeiculos` (`VeiculoController::list`; recebe `$_GET`: `pagina`, e os filtros de servidor `busca`, `marca`, `modelo`, `cliente`).
- **Quem acessa:** todos os cargos. O botão de excluir só aparece para `ADMIN`; "Novo Veículo" e "Editar" levam a rotas que exigem `ADMIN` ou `ATENDENTE`.
- **Finalidade:** listar todos os veículos cadastrados, com busca/filtros rápidos e indicadores.
- **Cabeçalho:** "Gestão de Veículos" / "Visualize e gerencie todos os veículos cadastrados na oficina." Botão "Novo Veículo" -> `./saveVeiculo`.
- **Cards:**
  - "Total de Veículos": total de registros em `veiculos`.
  - "Novos este Mês": veículos cadastrados no mês corrente; clicável (também por teclado), abre painel flutuante.
  - "Marca mais Recorrente": nome da marca com mais veículos (ou "—").
- **Painel "Novos Veículos do Mês":** mesmo padrão dos painéis de Clientes. Tela 1 (lista via `fetch ./listVeiculosNovosMes`, JSON, mais recentes primeiro): ícone de carro, "Marca Modelo", "placa · proprietário" e selo "Novo". Tela 2 (perfil via `fetch ./veiculoPerfil?id=<id>`): nome do veículo, tag "✦ Novo · cadastrado em <data>", grade com Placa, Ano, Cor, Combustível, Proprietário, Observações. Botões "Voltar", "X"; fecha com clique fora ou Esc. Mensagens: "Nenhum veículo novo este mês ainda.", "Erro ao carregar novos veículos.", "Não foi possível carregar o perfil.". `veiculoPerfil` devolve 400 sem `id` e 404 se não existir.
- **Toolbar:**
  - Campo "Buscar por placa, modelo ou cliente..." (`#busca-veiculo`).
  - Select "Marca: Todas" (marcas presentes na página atual, em ordem alfabética).
  - Select "Combustível: Todos" (combustíveis presentes na página atual).
  - Contador "N resultado(s)".
  - Importante: esses três controles filtram por JavaScript (`filtrarVeiculos()`), escondendo linhas da tabela já carregada (apenas os 10 veículos da página atual); a busca é por texto contido em qualquer coluna, a marca é comparada com a coluna Veículo e o combustível por igualdade com a coluna Combustível. Não enviam nada ao servidor. O servidor aceita `busca`, `marca`, `modelo` e `cliente` pela URL (busca com `LIKE` em placa, placa normalizada, nome do cliente, marca e modelo), mas a tela não tem campos que os preencham; a paginação preserva esses parâmetros se existirem na URL.
- **Tabela (10 por página, ordenada por nome do cliente e placa):** Placa, Veículo, Cor, Ano, Combustível, Proprietário, Observações, Ações.
  - Placa: desenhada como SVG no formato brasileiro, detectando o padrão: `AAA9A99` = Mercosul (faixa azul, "BRASIL", bandeira, selo de QR, "BR"); caso contrário, modelo antigo (chapa cinza com relevo). O tooltip indica "(Mercosul)" ou "(Modelo antigo)".
  - Veículo: logo da marca (imagens de `carlogos.org` para uma lista fixa de marcas: Chevrolet/GM, Fiat, VW, Ford, Toyota, Honda, Hyundai, Renault, Peugeot, Citroën, Nissan, Mitsubishi, Jeep, BMW, Mercedes, Audi, Kia, Chery/Caoa, Volvo, Suzuki, Subaru, Dodge, Ram, Land Rover; se não houver logo ou a imagem falhar, mostra um ícone de reserva) e texto "Marca Modelo". Observação: a política de segurança enviada pelo servidor (`img-src 'self' data:`) bloqueia imagens externas, então na prática o ícone de reserva tende a ser exibido.
  - Cor: bolinha colorida (hex da lista `_coresCarro.php`; cinza `#CBD5E1` se a cor não estiver na lista) + nome ou "—".
  - Ano, Combustível, Proprietário (nome do cliente), Observações ("—" se vazio).
- **Ações por linha:** Visualizar (olho) -> link montado como `./viewVeiculo&id=<id>`; Editar (lápis) -> `./editVeiculo&id=<id>` (mesma observação sobre o `&` feita em Clientes); Excluir (lixeira, só ADMIN): POST `./deleteVeiculo` com `id`, confirmação "Excluir este veículo? Todos os orçamentos e ordens de serviço vinculados a ele também serão APAGADOS. Esta ação é irreversível."
- **Estado vazio:** "Nenhum veículo cadastrado."
- **Paginação:** "Mostrando X de Y resultados"; botões `‹`, anterior/atual/próxima, `›`; navegação por `window.location` (recarrega a página, mantendo os filtros da URL).
- **Regra de negócio importante:** a exclusão é física e em cascata, dentro de transação: apaga itens de OS (produtos, mão de obra, serviços), as OS ligadas ao veículo (ou geradas por seus orçamentos), itens e orçamentos do veículo e, por fim, o veículo.

### Novo Veículo (cadastro)

- **Arquivo:** `app/views/Veiculos/saveVeiculo.php` (modal em tela cheia).
- **URL / rota:** `./saveVeiculo` (`VeiculoController::save`; GET abre, POST grava).
- **Quem acessa:** `ADMIN` e `ATENDENTE`.
- **Finalidade:** cadastrar veículo vinculado a um cliente ativo, com apoio de leitura de placa por foto.
- **Cabeçalho:** "Novo Veículo — Preencha os dados abaixo para cadastrar um novo veículo." Botão "X" e clique fora voltam a `./listVeiculos`. Erro: caixa vermelha ("Erro ao cadastrar veículo: <detalhe>"); a view não repopula os campos com `$old`.
- **Campos (POST `./saveVeiculo`, form `formCriarVeiculo`):**
  - Seção 1 - Identificação:
    - `cliente_id` (select, obrigatório): lista de clientes ativos, "Cliente Balcão" fixo no topo, depois ordem alfabética.
    - `marca_id` (select, obrigatório): todas as marcas em ordem alfabética.
    - `modelo_id` (select, obrigatório): começa com "Selecione primeiro a marca"; ao escolher a marca é preenchido por `fetch` em `/mecanize/public/?url=modelosPorMarca&marca_id=<id>` (JSON com `id_modelo`, `nome`, `combustiveis`). Estados: "Carregando...", "Selecione o modelo", "Nenhum modelo encontrado", "Erro ao carregar modelos". Observação: o endereço do fetch está fixo em `/mecanize/public/`, dependendo do nome da pasta no servidor.
  - Seção 2 - Dados do Veículo:
    - Placa (obrigatória). A tela começa com uma área tracejada "Foto da placa - arraste a imagem, clique para anexar ou fotografe" (`<input type=file id=fotoPlaca accept="image/*" capture="environment">`, aceita arrastar e soltar; arquivos que não sejam imagem geram o alerta "Solte um arquivo de imagem (foto da placa)."). Ao escolher a foto, aparece o preview (com botão "X" para remover) e o botão "Ler placa pela foto". Link "Inserir placa manualmente" mostra/oculta (alterna para "Ocultar entrada manual") o campo de texto `placa` (`maxlength 8`, `required`, placeholder "ABC1D23 ou ABC-1234") e há o campo oculto `placa_normalizada` (7 caracteres A-Z/0-9, mantido pelo JS). O campo `placa` fica escondido por padrão; ele só é preenchido pela leitura da foto ou ao abrir a entrada manual.
    - Máscara da placa (IMask, `masks.js`): alterna entre `aaa-0000` (antiga) e `aaa0a00` (Mercosul), sempre em maiúsculas.
    - `ano` (número, opcional, `min 1950`, `max` = ano atual + 1, placeholder "Ex.: 2021").
    - `cor` (select com Tom Select e bolinha de cor; 21 opções: Branco, Branco Pérola, Preto, Prata, Cinza, Grafite, Chumbo, Vermelho, Vinho, Azul, Azul Marinho, Verde, Verde Escuro, Amarelo, Laranja, Marrom, Bege, Dourado, Bronze, Rosa, Roxo). Grava o nome da cor.
    - `combustivel` (select: Gasolina, Etanol, Flex (Álcool/Gasolina), Diesel, GNV, Híbrido, Elétrico). Ao escolher o modelo, as opções são restringidas aos combustíveis cadastrados para aquele modelo (coluna `combustiveis`, separada por vírgula); sem restrição, mostra a lista completa. Trocar a marca restaura a lista completa.
  - Seção 3 - Observações: `observacoes` (textarea, 3 linhas).
  - Obrigatórios validados no servidor: cliente, marca, modelo e placa. Placa duplicada (comparando a forma normalizada, sem traço) é recusada: "Já existe um veículo cadastrado com esta placa."; também há proteção por índice único no banco.
- **Leitura de placa (OCR):** `lerPlaca()` envia a imagem (campo `imagem`) por POST para `./lerPlaca`, mostrando "Enviando imagem e lendo placa..." e o botão "Lendo...". Em caso de sucesso, preenche o campo placa pela máscara, mostra "Placa reconhecida: XXXXXXX" e abre um alerta; em falha mostra a mensagem do servidor (e alerta).
- **Rodapé:** "Criado em / Atualizado em" (data de hoje), "Usuário: nome (cargo)", botões "Limpar" (reseta formulário, máscara, modelo, foto, entrada manual, combustível e o Tom Select de cor), "Cancelar" e "Gravar".
- **Após gravar:** redireciona para `./listVeiculos`.

### Editar Veículo

- **Arquivo:** `app/views/Veiculos/editVeiculo.php`.
- **URL / rota:** `./editVeiculo?id=<id>` (`VeiculoController::edit`; POST grava). Sem `id` volta à lista; inexistente mostra "Veículo não encontrado".
- **Quem acessa:** `ADMIN` e `ATENDENTE`; o botão "Excluir Veículo" só aparece para `ADMIN`.
- **Layout:** cabeçalho "Editar Veículo" com botão "Voltar"; campo oculto `id_veiculo`; quatro cards:
  1. Informações do Veículo: `cliente_id` (select de clientes ativos; obrigatório), `marca_id` (obrigatório), `modelo_id` (obrigatório; carregado por `fetch` com os modelos da marca atual e já selecionando o modelo gravado, via `data-modelo-atual`).
  2. Identificação: `placa` (preenchida, `maxlength 8`, obrigatória, máscara de placa antiga/Mercosul), `ano` (`min 1900`, `max` ano atual + 1), `cor` (Tom Select com bolinha; se a cor gravada não estiver na lista, ela é adicionada como opção selecionada), `combustivel` (idem: valor legado fora da lista é preservado; as opções são restringidas ao modelo escolhido, mantendo o valor atual na abertura).
  3. Observações: `observacoes` (textarea 6 linhas).
  4. Resumo do Cadastro: ID do Veículo (#id) e Última Atualização (data de hoje).
- Nesta tela não há leitura de placa por foto.
- **Rodapé de ações:** "Excluir Veículo" (só ADMIN; POST `./deleteVeiculo`, mesma confirmação de exclusão em cascata da listagem), "Cancelar" (`./listVeiculos`) e "Salvar Alterações".
- **Validação:** cliente, marca, modelo e placa obrigatórios; placa não pode pertencer a outro veículo ("Já existe outro veículo cadastrado com esta placa."). Em erro, reexibe a tela com o POST mesclado e a mensagem "Erro ao atualizar veículo: ...".
- **Após salvar:** redireciona para `./listVeiculos`.

### Visualizar Veículo

- **Arquivo:** `app/views/Veiculos/viewVeiculo.php` (modal somente leitura).
- **URL / rota:** `./viewVeiculo?id=<id>` (`VeiculoController::visualizar`). Sem `id` volta à lista; inexistente mostra "Veículo não encontrado".
- **Quem acessa:** todos os cargos.
- **Conteúdo:** cabeçalho "Veículo — Marca Modelo" / "Visualização detalhada do veículo", selo com a placa e botão "X" (`history.back()`). Seções:
  1. Identificação: ID do Veículo e Data de Cadastro.
  2. Dados do Veículo: cartão de destaque (avatar com as 3 primeiras letras/números da placa, "Marca Modelo", "ano · cor", placa em azul); grade com Marca, Modelo, Placa, Ano, Cor, Combustível.
  3. Cliente Vinculado: iniciais, nome, "Proprietário do veículo" e botão "Ver cliente" (`./viewCliente?id=<id>`; o botão só aparece se o registro trouxer `id_cliente`, mas a consulta do veículo devolve o campo `cliente_id`, então na prática o botão não é exibido). Sem cliente: "Nenhum cliente vinculado."
  4. Observações.
  5. Histórico do Veículo: linha do tempo que mistura orçamentos ("Orçamento #número — Status", ícone de documento, link `./viewOrcamento?id=<id>`, valor total) e ordens de serviço ("OS #número — Concluída/Cancelada/Aberta", ícone de chave, link `./viewOrdemServico?id=<id>`, valor total; data de referência = conclusão ou, se não houver, abertura), do mais recente para o mais antigo, terminando em "Veículo cadastrado" (ícone de carro, sem link nem valor). Cada item mostra data "d/m/Y às H:i" e valor "R$ 0,00" quando existe. Vazio: "Nenhum evento registrado ainda."
- **Rodapé:** "Cadastro: <data>", usuário logado, "Editar" (`./editVeiculo&id=<id>`, mesma observação sobre o `&`) e "Voltar" (`history.back()`).

### Endpoints auxiliares de Veículos (sem tela própria)

- `./modelosPorMarca?marca_id=<id>` (GET, JSON, `ModeloVeiculoController`): lista `id_modelo`, `nome`, `combustiveis` dos modelos da marca; `[]` se não houver `marca_id`. Usado por Novo e Editar Veículo.
- `./criarModelo` (POST `marca_id`, `nome`; JSON `{sucesso, modelo}` ou `{sucesso:false, erro}`): cria um modelo "na hora" para a marca (reaproveita se já existir com o mesmo nome, sem diferenciar maiúsculas). Está roteado, mas as telas de veículo atuais não o chamam. Mensagens: "Método inválido.", "Informe a marca e o nome do modelo.", "Não foi possível criar o modelo.". Não exige cargo específico.
- `./lerPlaca` (POST multipart, campo `imagem`; `PlacaController::ler`): reconhecimento de placa pela API Plate Recognizer (token em `PLATE_RECOGNIZER_TOKEN`). Validações: método POST; arquivo recebido ("Nenhuma imagem foi recebida. Tente tirar a foto novamente."); máximo 2 MB ("Imagem muito grande. O limite é 2 MB."); tipo real verificado pelo cabeçalho da imagem, só JPG, PNG ou WebP ("O arquivo enviado não é uma imagem válida (aceitos: JPG, PNG, WebP)."). Erros de conexão ou de resposta da API devolvem mensagens genéricas pedindo para digitar a placa manualmente ("Não foi possível falar com o leitor de placas. Digite a placa manualmente." / "O leitor de placas não respondeu como esperado. Digite a placa manualmente."); sem placa detectada: "Nenhuma placa encontrada na imagem. Tente outra foto, mais próxima e sem reflexo." Sucesso: `{sucesso:true, placa:"ABC1D23"}` em maiúsculas. Esta rota não exige caixa aberto.

---

## Agendamentos

### Agenda (listagem de agendamentos)

- **Arquivo:** `app/views/Agendamentos/listAgendamentos.php`.
- **URL / rota:** `./listAgendamentos` (`AgendamentoController::list`). Parâmetros GET: `periodo` (`dia` | `semana` padrão | `mes`), `data` (`YYYY-MM-DD`, data de referência; inválida cai em hoje), `status`, `mecanico_id`, e os avisos de retorno `ok` / `erro`.
- **Quem acessa:** todos os cargos podem ver a agenda. Botões de criar/editar/confirmar/cancelar/gerar OS levam a ações que exigem `ADMIN` ou `ATENDENTE`; o botão de excluir só aparece para `ADMIN`.
- **Finalidade:** visão da agenda de serviços por dia, semana ou mês, agrupada por dia, com contadores e ações rápidas.
- **Cabeçalho:** "Agenda" / "Horários marcados — <rótulo do período>". Rótulos: dia = "Segunda-feira, dd/mm/aaaa"; semana (segunda a domingo) = "Semana de dd/mm a dd/mm/aaaa"; mês = "Janeiro de 2026". Botão "Novo agendamento" -> `./saveAgendamento?data=<início do período>`.
- **Avisos (alertas após ações):** sucesso: "Agendamento criado com sucesso.", "Agendamento atualizado.", "Status atualizado.", "Agendamento excluído."; erro: "Não foi possível atualizar o status.", "Não foi possível excluir o agendamento.", "Agendamento não encontrado." (este último, ao tentar gerar OS de um agendamento inexistente).
- **Navegação e filtros (card superior):**
  - Botões "‹" (período anterior), "Hoje", "›" (próximo). Os passos são de 1 dia, 1 semana ou 1 mês conforme o período.
  - Abas "Dia", "Semana", "Mês" (mantêm a data de referência).
  - Formulário GET com selects "Todos os status" (Agendado, Confirmado, Concluído, Cancelado, Não compareceu) e "Todos os mecânicos" (usuários ativos com cargo `TECNICO` ou `ADMIN`), mais o botão "Filtrar"; mantém `periodo` e `data` em campos ocultos. Os links de navegação preservam os filtros ativos. Status inválido na URL é ignorado.
- **Cards de contadores (do período, ignorando os filtros):** "Total no período", "Confirmados", "Aguardando confirmação" (status Agendado) e "Cancelados / faltas" (Cancelado + Não compareceu).
- **Agenda:** um cartão por dia que tenha agendamentos, com título "Dia-da-semana, dd/mm/aaaa" (selo verde "Hoje" no dia atual) e "N agendamento(s)". Cada item (ordenado por horário) mostra:
  - Horário "HH:MM" e "até HH:MM" (início + duração).
  - Serviço, e linha com cliente · placa (marca modelo) · mecânico (quando existem), e observações.
  - Selo de status: Agendado (azul), Confirmado (verde), Concluído (neutro), Cancelado (vermelho), Não compareceu (amarelo). Se já virou OS, link "OS <número>" -> `./viewOrdemServico?id=<id>`.
  - Botões:
    - "Confirmar" (só quando AGENDADO): POST `./statusAgendamento` com status `CONFIRMADO`.
    - "Gerar OS" (só em AGENDADO/CONFIRMADO e sem OS): POST `./gerarOsAgendamento`.
    - "Editar" (sempre): `./editAgendamento?id=<id>`.
    - "Cancelar" (só em AGENDADO/CONFIRMADO; vermelho): POST `./statusAgendamento` com status `CANCELADO`, confirmação "Cancelar este agendamento?".
    - "✕" Excluir (só `ADMIN`): POST `./deleteAgendamento`, confirmação "Excluir este agendamento em definitivo?" (exclusão física).
    - Os formulários de status/exclusão enviam `periodo` e `data_ref` para voltar à mesma visão.
  - Estado vazio: "Nenhum agendamento neste período." com botão "Criar o primeiro".
- **Regras de negócio:** ciclo AGENDADO -> CONFIRMADO -> CONCLUIDO (ao gerar a OS), com CANCELADO e FALTOU como saídas; só AGENDADO e CONFIRMADO ocupam a agenda (geram conflito de horário). Status "Não compareceu" não tem botão rápido na listagem; só se define pela tela de edição. Rota `./statusAgendamento` exige `ADMIN`/`ATENDENTE`; aceita só POST e status conhecido. Rota `./deleteAgendamento` aceita só POST e exige `ADMIN`.
- **"Gerar OS" (`./gerarOsAgendamento`, `ADMIN`/`ATENDENTE`, só POST):** não cria a OS ali; se o agendamento já tem OS, redireciona para `./viewOrdemServico?id=<OS>`; senão redireciona para `./saveOrdemServico` com `cliente_id`, `veiculo_id`, `agendamento_id` e `defeito` (= texto do serviço) na query, para o formulário de nova OS vir preenchido. O vínculo (e o status CONCLUIDO) é gravado depois pela criação da OS (`Agendamento::vincularOrdemServico`, que só vincula se ainda não houver OS).

### Novo Agendamento

- **Arquivos:** `app/views/Agendamentos/saveAgendamento.php` + partial `_formAgendamento.php`.
- **URL / rota:** `./saveAgendamento` (`AgendamentoController::save`; GET abre, POST grava). `?data=YYYY-MM-DD` pré-preenche a data (padrão: hoje).
- **Quem acessa:** `ADMIN` e `ATENDENTE`.
- **Finalidade:** marcar um horário para o cliente antes de abrir a OS.
- **Cabeçalho:** "Novo Agendamento" / "Marque um horário para o cliente antes de abrir a Ordem de Serviço." Botão "Voltar para a agenda". Alerta de erro (vermelho) em caso de falha, com os valores digitados mantidos.
- **Campos (POST `./saveAgendamento`):**
  - `cliente_id` (select, obrigatório): clientes ativos em ordem alfabética.
  - `veiculo_id` (select, opcional; "Sem veículo definido"): rótulo "PLACA — Marca Modelo". Texto de ajuda: "A lista filtra pelos veículos do cliente escolhido." O JS mostra só os veículos do cliente selecionado (todos, se nenhum cliente escolhido) e só mantém a seleção anterior se o veículo pertencer ao cliente; na abertura preserva o veículo já gravado.
  - `data` (`type=date`, obrigatório; padrão hoje; validada como data real `YYYY-MM-DD`).
  - `hora` (`type=time`, obrigatório; padrão 08:00; formato `HH:MM` 00:00-23:59).
  - `duracao_min` (select, obrigatório): 30 minutos, 1 hora, 1h30, 2 horas, 3 horas, 4 horas, Dia inteiro (8h) (valores 30, 60, 90, 120, 180, 240, 480; padrão 1 hora). Ajuda: "Usada para avisar quando dois serviços se sobrepõem."
  - `mecanico_id` (select, opcional; "A definir"): usuários ativos `TECNICO` ou `ADMIN`.
  - `servico` (texto, obrigatório, `maxlength 160`; placeholder "Ex.: Revisão de 10.000 km, troca de pastilhas...").
  - `observacoes` (texto, opcional, `maxlength 500`; placeholder "Opcional").
  - O campo `status` não existe no cadastro: todo agendamento nasce `AGENDADO`.
- **Validações do servidor (mensagens):** "Cliente é obrigatório.", "Data é obrigatório.", "Hora é obrigatório.", "Serviço é obrigatório.", "Data inválida.", "Hora inválida.", "Duração inválida.", "Status inválido." (não aplicável aqui).
- **Regra de conflito de horário:** compara intervalos [início, início + duração); encostar (um termina às 10:00 e outro começa às 10:00) não conflita. Considera só agendamentos AGENDADO/CONFIRMADO. Com mecânico informado, o conflito é só na agenda dele ("Esse mecânico já tem outro serviço marcado nesse horário."); sem mecânico, qualquer outro agendamento ativo no horário bloqueia ("Já existe outro agendamento nesse horário."). Falha geral de gravação: "Não foi possível salvar o agendamento."
- **Botões:** "Cancelar" (`./listAgendamentos`) e "Salvar agendamento".
- **Após gravar:** o sistema tenta enviar e-mail ao cliente (se tiver e-mail) com assunto "Agendamento confirmado - Mecanize" contendo data/hora, serviço e veículo; falha de envio é registrada em log e não impede o agendamento. Redireciona para `./listAgendamentos?data=<data>&periodo=dia&ok=criado`.

### Editar Agendamento

- **Arquivos:** `app/views/Agendamentos/editAgendamento.php` + partial `_formAgendamento.php`.
- **URL / rota:** `./editAgendamento?id=<id>` (`AgendamentoController::edit`; POST grava com `id_agendamento` oculto). Sem `id` válido ou agendamento inexistente: redireciona para `./listAgendamentos`.
- **Quem acessa:** `ADMIN` e `ATENDENTE`.
- **Finalidade:** alterar dados do agendamento, inclusive o status.
- **Cabeçalho:** "Editar Agendamento" e subtítulo com nome do cliente e, se houver, a placa. Botão "Voltar para a agenda". Alerta de erro quando houver. Se o agendamento já virou OS, faixa verde "Este agendamento já virou a OS <número>." com link para `./viewOrdemServico?id=<id>`.
- **Campos:** os mesmos do cadastro (`cliente_id`, `veiculo_id` com filtro por cliente, `data`, `hora` separadas a partir de `data_hora`, `duracao_min`, `mecanico_id`, `servico`, `observacoes`), preenchidos com os valores atuais, mais:
  - `status` (select, obrigatório, só na edição): Agendado, Confirmado, Concluído, Cancelado, Não compareceu (únicos valores aceitos: `AGENDADO`, `CONFIRMADO`, `CONCLUIDO`, `CANCELADO`, `FALTOU`; outro valor gera "Status inválido."). Aqui o campo de observações ocupa 1 coluna.
- **Validações e conflito:** as mesmas do cadastro; na checagem de conflito o próprio agendamento é ignorado, e na edição a mensagem é sempre "Já existe outro agendamento nesse horário." (mesmo com mecânico). Atenção: se o status escolhido for Cancelado, Concluído ou Não compareceu, a checagem de conflito ainda é executada sobre o novo horário.
- **Botões:** "Cancelar" e "Salvar alterações".
- **Após salvar:** não reenvia e-mail; redireciona para `./listAgendamentos?data=<data>&periodo=dia&ok=editado`.

### Partial do formulário (`_formAgendamento.php`)

Não é tela própria: é o bloco de campos compartilhado por Novo e Editar (grade de formulário com os campos acima). Recebe `$clientes`, `$veiculos`, `$mecanicos` (de `Agendamento::getDadosFormulario`: clientes ativos, todos os veículos com placa/marca/modelo e mecânicos ativos), `$valores` e `$ehEdicao` (que habilita o campo Status). Contém o JS de filtro de veículos por cliente descrito acima.

---

# Parte 3 — Orçamentos, Ordens de Serviço e Documentos

Observações gerais (valem para as três seções):

- Todas as telas ficam atrás de login (as únicas rotas públicas são login/recuperação de senha, telas de erro e `rastrear`). Dentro do sistema, o roteador (`public/index.php`) ainda aplica: sessão revalidada contra o banco (usuário ativo, cargo atual), janela de horário para ATENDENTE (seg–sex 07:00–18:00, sáb 07:00–13:00; fora disso é deslogado) e "caixa obrigatório" (ADMIN e ATENDENTE são redirecionados para `./abrirCaixa` enquanto não houver caixa aberto; TECNICO é isento).
- CSRF: todo POST é validado; o token é injetado automaticamente nos formulários e nos `fetch` por `public/assets/js/csrf.js`.
- Cargos existentes: `ADMIN`, `ATENDENTE`, `TECNICO`. Quando o texto diz "qualquer logado", a ação não tem `requireRole()` no controller.
- Links nas listagens usam o formato `./viewOrcamento&id=N` (com `&`), enquanto as telas internas usam `./viewOrcamento?id=N`; o código usa os dois.
- Menu lateral: "Orçamentos" (`./listOrcamentos`) e "Ordens de Serviço" (`./listOrdemServico`) aparecem para todos; "Documentos" (`./listDocumentos`) só aparece no menu para ADMIN. O botão "Criar" da barra superior tem atalhos para `./novoOrcamento` e `./saveOrdemServico`.
- Formato de números/datas: valores em `R$ 1.234,56`; datas `dd/mm/aaaa` ou `dd/mm/aaaa HH:MM`.
- Assinatura do cliente: componente `public/assets/js/assinatura.js` (`initAssinatura(canvasId)`), desenho em `<canvas>` com mouse/toque, `limpar()` e `dataUrl()` (PNG base64). O servidor só aceita a assinatura se começar com `data:image/png;base64,`; caso contrário ignora (grava `null`, sem sobrescrever a existente).

---

## Orçamentos

### Fluxo e status do orçamento

- Status gravados no banco (minúsculos): `pendente`, `aprovado`, `reprovado` (exibido como "Recusado") e `cancelado` (só existe no enum/visualização; nenhuma tela do sistema o cria).
- Criação: sempre nasce `pendente` (o model grava `'pendente'` fixo, ignorando o `status` do POST). Número gerado automaticamente `ORC-0001`, `ORC-0002`… (último + 1).
- **Aprovar** (`POST ./aprovarOrcamento`, ADMIN/ATENDENTE): em uma operação, cria a **Ordem de Serviço** a partir do orçamento (copiando cliente, veículo, km, defeito, diagnóstico, observações, totais de peças/serviços, valor total, mecânico, mão de obra/comissão, base de comissão e todos os itens) com `orcamento_id` preenchido e **status inicial `EM_ANDAMENTO`**, e marca o orçamento como `aprovado` (`data_aprovacao = NOW()`, grava a assinatura se enviada). Se já existir OS para o orçamento (duplo clique/corrida; há índice único `orcamento_id`), não cria outra, só marca aprovado. Se a criação da OS falhar, não aprova (redireciona com `?erro=aprovacao`). Após aprovar, tenta enviar e-mail ao cliente ("Orçamento aprovado - Mecanize", com número, valor e link de rastreio público `rastrear?token=...`); falha de envio não bloqueia. Redireciona para `./listOrcamentos?sucesso=aprovado`.
- **Recusar** (`POST ./recusarOrcamento`, ADMIN/ATENDENTE): só funciona se o status atual for `pendente`; grava `status = 'reprovado'`, `data_recusa = NOW()` e `motivo_recusa`. Redireciona com `?sucesso=recusado` ou `?erro=recusa`.
- Aprovados e recusados não podem ser aprovados/recusados de novo pela interface (os botões só existem na aba Pendentes).
- Estoque: a baixa de produtos ocorre quando a OS é criada (inclusive a OS gerada na aprovação), não na criação do orçamento.

### Listagem de Orçamentos (`listOrcamentos`)

- **URL/rota:** `./listOrcamentos` → `OrcamentoController::list`. Sem `requireRole`: qualquer usuário logado vê a listagem (os botões de criar/editar/aprovar/recusar levam a ações que exigem ADMIN/ATENDENTE; TECNICO recebe a tela de "sem permissão" ao tentar).
- **Finalidade:** acompanhar orçamentos por situação e aprovar/recusar. Subtítulo: "Crie orçamentos para clientes. Ao aprovar, uma OS é gerada automaticamente."
- **Botão do cabeçalho:** "Novo Orçamento" → `./novoOrcamento`.
- **Mensagem de erro:** se a view receber `$error`, mostra faixa vermelha no topo da aba Pendentes. (Os parâmetros `?sucesso=`/`?erro=` na URL não são exibidos por esta view.)
- **Cartões de estatística (4):**
  - Pendentes — quantidade de orçamentos `pendente` ("Aguardando decisão");
  - Aprovados — quantidade `aprovado` ("OS geradas");
  - Recusados — quantidade `reprovado` ("Não convertidos");
  - Total Pendente — soma de `valor_total_orcamento` dos pendentes ("Soma dos orçamentos pendentes").
  - (O controller também calcula `ticketMedio`, mas a tela não o exibe.)
- **Abas (tabs):** Pendentes (com selo azul de contagem se > 0), Aprovados, Recusados. Troca de aba por JS sem recarregar.
- **Filtros/busca (cada aba tem os seus, filtragem client-side, a cada digitação/alteração):**
  - texto "Buscar por cliente, veículo ou orçamento..." (compara com cliente, veículo e número, em minúsculas);
  - campo de data (`input type=date`) — compara com a data de criação (Pendentes), de aprovação (Aprovados) ou de recusa (Recusados);
  - contador "N resultado(s)" atualizado.
- **Aba Pendentes** — banner: "Aprovar um orçamento cria automaticamente uma OS com status Em Andamento." Colunas: Orçamento (número, em destaque), Cliente, Veículo / Placa, Mecânico, Criado em (`dd/mm/aaaa HH:MM`), Valor Total, Ações. Paginação apenas visual (‹ 1 ›, mostra "N resultado(s)"); sem paginação real. Estado vazio: "Nenhum orçamento pendente."
  - Ações por linha: Visualizar (`./viewOrcamento&id=`), Editar (`./editOrcamento&id=`), Imprimir (`./imprimirOrcamento&id=`, nova aba), Baixar PDF (`./exportOrcamentoPdf&id=`), Aprovar e gerar OS (abre modal), Recusar (abre modal).
- **Aba Aprovados** — colunas: Orçamento, Cliente, Veículo / Placa, Mecânico, Aprovado em, OS Gerada (link verde para `./viewOrdemServico&id=` com o número da OS, ou "—"), Valor Total, Ações (Visualizar; "Ver OS" se houver OS). Vazio: "Nenhum orçamento aprovado ainda."
- **Aba Recusados** — colunas: Orçamento (número riscado), Cliente, Veículo / Placa, Mecânico, Recusado em, Motivo (truncado, tooltip com texto completo, "—" se vazio), Valor Total, Ações (Visualizar). Vazio: "Nenhum orçamento recusado."
- **Modal "Aprovar Orçamento"** (função `confirmarAprovar(id, numero, cliente)`): texto "o orçamento X do cliente Y será aprovado e uma nova Ordem de Serviço será criada com status Em Andamento"; aviso "Esta ação não poderá ser desfeita."; canvas "Assinatura do cliente (opcional)" 380x130 com botão "Limpar assinatura"; botões "Cancelar" e "Confirmar e Gerar OS". Formulário `POST ./aprovarOrcamento` com campos ocultos `id_orcamento` e `assinatura_cliente` (PNG base64, preenchido no `onsubmit`).
- **Modal "Recusar Orçamento"** (`confirmarRecusar(id, numero)`): `textarea` "Motivo da recusa (opcional)" (placeholder "Ex.: Cliente não aceitou o valor, optou por outra oficina..."); botões "Cancelar" e "Recusar Orçamento". Formulário `POST ./recusarOrcamento` com `id_orcamento` e `motivo_recusa` (copiado do textarea ao clicar).
- Ambos os modais fecham ao clicar fora.
- **JS:** `assinatura.js`; abas; `filtrarOrc(painel)`; modais.

### Novo Orçamento (`saveOrcamento`)

- **URL/rota:** GET `./novoOrcamento` (`OrcamentoController::save`) exibe o formulário; POST para `./saveOrcamento` (`OrcamentoController::saveOrcamento`) grava. Ambos `requireRole(['ADMIN','ATENDENTE'])`. POST por GET redireciona a `./listOrcamentos`.
- **Aparência:** modal grande em tela cheia ("Novo Orçamento — Preencha os dados abaixo para criar um orçamento. Após aprovação, uma OS será gerada automaticamente."). O "X" e clicar fora fazem `history.back()`.
- **Validação servidor:** `cliente_id` e `veiculo_id` obrigatórios (mensagens do `Validator`, exibidas em faixa vermelha, mantendo o formulário). Falha de gravação: "Erro ao salvar o orçamento. Tente novamente." Sucesso: redireciona a `./listOrcamentos?sucesso=1`.
- **Campos do formulário (`POST ./saveOrcamento`):**
  - ① Identificação
    - `numero_orcamento` — texto, somente leitura, preenchido com o próximo número (`ORC-000N`).
    - `status` — oculto, valor `PENDENTE` (exibido como etiqueta "Pendente"); o servidor ignora e grava `pendente`.
    - `data_criacao` — `datetime-local`, somente leitura, valor = agora (marcado como obrigatório no rótulo).
  - ② Cliente e Veículo
    - `cliente_id` — select, obrigatório (HTML `required`); lista de clientes (o "Cliente Balcão" fica sempre no topo).
    - `veiculo_id` — select, obrigatório; começa desabilitado ("Selecione primeiro o cliente") e é preenchido só com os veículos do cliente escolhido (opção "PLACA — modelo"); se o cliente não tem veículos, mostra "Nenhum veículo cadastrado para este cliente". No envio, o JS (`prepararSubmitOrc`) reabilita o campo e bloqueia o submit com borda vermelha se estiver vazio. Suporta Tom Select, se inicializado.
    - `km_veiculo` — `number`, `min=0`, opcional, placeholder "Ex.: 85000", sufixo "km".
  - ③ Mecânico Responsável
    - `mecanico_id` — select opcional (lista de mecânicos). Ao escolher, aparecem: campo percentual de comissão (`#orc-mec-comissao-pct`, number 0–100, passo 0,1, placeholder "Ex.: 30"; não é enviado diretamente), seletor "Base de Cálculo" (botões **Somente Serviços** [padrão], **Somente Peças**, **Peças + Serviços**) e um cartão de prévia (avatar com iniciais, nome, "Comissão: X% → R$ valor", selo "Incluído"). Ao remover o mecânico, zera tudo.
    - Ocultos enviados: `valor_mao_obra` (valor da comissão calculada), `comissao_pct`, `comissao_base` (`servicos` | `pecas` | `ambos`).
  - ④ Produtos & Serviços (itens dinâmicos) — ver "Itens dinâmicos" abaixo. Enviados como `itens[i][id|nome|tipo|categoria|preco|qty|subtotal|custom|pct]`.
  - ⑤ Diagnóstico — `defeito_relatado` (textarea, 4 linhas), `diagnostico` (textarea, 4 linhas), `observacoes` (textarea, 3 linhas); todos opcionais.
  - ⑥ Resumo Financeiro — cartões calculados em tempo real: Total em Peças, Total em Serviços, Comissão Técnico, Lucro Estimado, Total do Orçamento. Ocultos enviados: `valor_total_pecas`, `valor_total_servicos`, `valor_total_orcamento`.
  - ⑦ Validade & Prazo — `data_validade` (`date`, padrão hoje + 14 dias), `prazo_execucao` (texto livre, placeholder "Ex.: 2 dias úteis").
- **Cálculos (JS):** Total peças = Σ preço×qtd dos itens tipo produto; Total serviços = Σ preço×qtd dos itens tipo serviço; Comissão = base escolhida × % / 100 (base = serviços, peças ou ambos); **Total do orçamento = peças + serviços** (a comissão não é somada: é custo interno); **Lucro estimado = total − comissão**. Não existe campo de desconto nesta tela.
- **Itens dinâmicos:**
  - Tabela (aparece quando há itens; senão mostra "Nenhum item adicionado. Use os botões abaixo."): colunas Item (nome, selo "Personalizado" e "(X% s/ peças)" quando aplicável, categoria), Tipo (Peça/Serviço), Qtd (botões − e +; mínimo 1; itens personalizados mostram "—" e não têm quantidade), Unit., Subtotal e botão remover.
  - Botões "Adicionar Produto" e "Adicionar Serviço" abrem o modal de catálogo.
  - **Modal de catálogo** (`#orc-modal-catalogo`): título "Adicionar Produto/Serviço"; busca "Buscar por nome ou categoria..."; abas "Todos" + uma por categoria (+ aba "Personalizado" só para serviços); lista de itens com nome, categoria, preço e marcador de seleção (multiseleção por clique); contador "N selecionado(s)"; botões "Cancelar" e "Confirmar". Ao reabrir, itens já adicionados vêm marcados; desmarcar remove.
  - **Aba "Personalizado"** (serviço fora do catálogo): "Nome do Serviço" (obrigatório), "Tipo de Custo" — **Valor Fixo (R$)** (campo "Valor (R$)", number min 0 passo 0,01) ou **Porcentagem (%)** (campo "Percentual (%)", 0–100, passo 0,1; calcula o valor como % do total de peças com prévia "Valor calculado sobre peças", avisando "Nenhuma peça adicionada ainda" se não há peças) —, "Categoria" (opcional; padrão "Personalizado"). Botão vira "Adicionar Serviço"; valida nome e valor > 0 (borda vermelha). Itens por % são recalculados quando peças são removidas.
  - IDs: itens do catálogo usam o id do produto/serviço; personalizados usam `custom_<timestamp>` e `custom=1`.
- **Rodapé do modal:** "Criado em: dd/mm/aaaa"; "Usuário: nome (cargo)"; botões "Imprimir" (`window.print()`), "Limpar" (confirma "Deseja realmente limpar todos os dados?" e reseta tudo), "Cancelar" (voltar) e "Salvar Orçamento" (submit).

### Editar Orçamento (`editOrcamento`)

- **URL/rota:** GET `./editOrcamento?id=N` exibe; POST `./editOrcamento` salva. `OrcamentoController::edit`, `requireRole(['ADMIN','ATENDENTE'])`. Sem `id` volta à lista; id inexistente mostra a tela "registro não encontrado" (Orçamento).
- **Finalidade:** corrigir dados do orçamento. Texto: "Os itens só podem ser alterados refazendo o orçamento." Botão "Voltar" → `./viewOrcamento?id=`.
- **Validação servidor:** `cliente_id` e `veiculo_id` obrigatórios (erro em faixa vermelha, mantendo os valores digitados); falha de gravação: "Erro ao atualizar o orçamento." Sucesso: redireciona a `./listOrcamentos`.
- **Campos (`POST ./editOrcamento`):**
  - oculto `id_orcamento`;
  - 1. Identificação: número (somente leitura, não enviado); `status` (select: Pendente, Aprovado, Recusado — valores `pendente`/`aprovado`/`reprovado`; o servidor normaliza para um dos 4 válidos, padrão `pendente`); `data_validade` (date); `prazo_execucao` (texto, "Ex.: 2 dias úteis");
  - 2. Cliente e Veículo: `cliente_id` (select), `veiculo_id` (select com todos os veículos, "PLACA · modelo"; não filtra por cliente nesta tela), `km_veiculo` (number min 0), `mecanico_id` (select);
  - 3. `defeito_relatado`, `diagnostico`, `observacoes` (textareas de 3 linhas);
  - 4. Itens (somente leitura): tabela Nome, Tipo, Categoria, Quantidade, Preço Unit., Subtotal; vazio: "Nenhum item neste orçamento.";
  - 5. Valores: `valor_total_pecas` e `valor_total_servicos` (texto, editáveis, aceitam vírgula ou ponto), `valor_mao_obra` (somente leitura, calculado), `comissao_pct` (texto, editável), `valor_total_orcamento` (somente leitura, calculado).
- **Cálculos (JS, a cada digitação e ao abrir):** mão de obra = total serviços × comissão% / 100; total = peças + serviços.
- **Atenção (comportamento do código):** esta tela não envia `comissao_base`, então ao salvar a base de comissão volta para `servicos`. Editar não altera os itens nem a OS já gerada.
- **Botões:** "Cancelar" (→ `./listOrcamentos`) e "Salvar Alterações".

### Visualizar Orçamento (`viewOrcamento`)

- **URL/rota:** `./viewOrcamento?id=N` → `OrcamentoController::visualizar`. Sem `requireRole` (qualquer logado). Sem id volta à lista; inexistente mostra "registro não encontrado".
- **Finalidade:** leitura completa do orçamento. Título "Visualizar Orçamento · ORC-000N", subtítulo "Detalhes do orçamento e seus itens." e selo de status colorido (Pendente = amarelo, Aprovado = verde, Recusado = vermelho, Cancelado = cinza).
- **Botões do cabeçalho:**
  - Voltar (`./listOrcamentos`);
  - Baixar PDF (`./exportOrcamentoPdf?id=`);
  - Link de Rastreio — copia para a área de transferência a URL pública `rastrear?token=...` (o token é criado na hora se não existir) e troca o texto para "Copiado!" por instantes;
  - Enviar WhatsApp — só aparece se o status é `aprovado` **e** o cliente tem telefone; abre `wa.me` (via `WhatsApp::linkParaCliente`) com a mensagem "Olá, {cliente}. Seu orçamento nº {número} foi aprovado. Em breve entraremos em contato para dar andamento ao serviço." + " Acompanhe pelo link: {link rastreio}";
  - Editar (`./editOrcamento?id=`).
  - (Não há botão de aprovar/recusar/imprimir nesta tela; isso é feito na listagem.)
- **Stepper de acompanhamento:** Criado → Aguardando aprovação → (Aprovação | Aprovado | Recusado conforme o status). Para `cancelado`, mostra em vez disso a faixa "Este orçamento foi cancelado."
- **Seções (todas somente leitura):** 1. Identificação (número, status, data de criação `dd/mm/aaaa HH:MM`, validade, prazo de execução); 2. Cliente e Veículo (cliente, "modelo · placa", KM, mecânico); 3. Defeito, Diagnóstico e Observações; 4. Itens do Orçamento (Nome, Tipo, Categoria, Quantidade, Preço Unit., Subtotal; vazio "Nenhum item neste orçamento."); 5. Valores (Total em Peças, Total em Serviços, Mão de Obra, Comissão %, Valor Total); 6. Assinatura do Cliente (imagem; só aparece se houver assinatura gravada na aprovação).

### Impressão do Orçamento (`printOrcamento`)

- **URL/rota:** `./imprimirOrcamento?id=N` → `OrcamentoController::imprimir` (abre em nova aba a partir da lista). Sem `requireRole`. Renderiza a view sem o layout principal (sem menu/topo).
- **Barra de ferramentas (não impressa):** marca "Mecanize"; botões "Editar"/"Concluir edição" (alterna modo de edição), "Fechar" (`window.close()`), "Imprimir / Salvar PDF" (`window.print()`).
- **Aviso (só se status `pendente`):** "Este documento é o orçamento para aprovação do cliente. Assim que ele aprovar (verbalmente, por assinatura ou por escrito), retorne ao sistema e clique em 'Aprovar e Gerar OS'."
- **Modo edição:** `toggleEdicao()` liga `contenteditable` nos elementos marcados `data-editable` (nome da empresa, endereço, cliente, veículo/placa, defeito, diagnóstico, nomes dos itens, observações, prazo) e mostra o aviso "Modo edição ativado — ... essa alteração vale só para esta impressão/PDF, não é salva no sistema."
- **Conteúdo do documento (`#doc`):** cabeçalho com logotipo "M", nome/endereço/telefone/CNPJ da empresa (constantes `EMPRESA_NOME`, `EMPRESA_ENDERECO`, `EMPRESA_TELEFONE`, `EMPRESA_CNPJ`, se definidas), título "Orçamento", número, selo de status ("Aguardando Aprovação", "Aprovado", "Recusado"), "Emitido em"; blocos Cliente (nome) e Veículo (modelo, placa e km); Defeito Relatado e Diagnóstico (se houver); tabela "Peças / Produtos" e tabela "Mão de Obra / Serviços" (Item, Qtd, Unitário, Subtotal; só aparecem se houver itens do tipo); totais Peças, Serviços, **Mão de obra** (valor_mao_obra) e Total; Observações; "Válido até" e "Prazo estimado"; linhas de assinatura "Assinatura do Cliente" e nome do mecânico (ou "Responsável Técnico"); rodapé "Documento gerado eletronicamente pelo Mecanize em dd/mm/aaaa às HH:MM". Esta versão não mostra a assinatura desenhada nem a garantia.

### PDF do Orçamento (`pdfOrcamento`)

- **URL/rota:** `./exportOrcamentoPdf?id=N` → `OrcamentoController::exportarPdf`; gera o PDF com Dompdf (`Pdf::baixar`) e força download de `orcamento_<numero>.pdf`. Sem `requireRole`. Usa template próprio (cores literais e tabelas, sem JS).
- **Conteúdo:** cabeçalho (logo "M", dados da empresa pelas constantes `EMPRESA_*`, título "Orçamento", número e selo de status: Aguardando Aprovação / Aprovado / Recusado); faixa com "Emitido em", "Válido até", "Responsável" (mecânico); bloco Cliente (nome, CNPJ ou CPF, telefone, endereço · bairro · cidade/UF · CEP) e bloco Veículo (marca/modelo, placa, Ano, Cor, KM); "Problema Relatado" e "Diagnóstico" (se houver); tabela "Peças / Produtos" e tabela "Mão de Obra / Serviços" com colunas Descrição, Qtd, Valor Unit., Desconto Unit., Total do Item; totais (Total Peças, Total Serviços, Subtotal, Desconto, **Total Geral**); Observações; "Prazo estimado de execução"; "Termos da Garantia" (texto fixo: orçamento válido até a data indicada; após aprovação e execução, garantia de 90 dias corridos a partir da conclusão/retirada, restrita à mão de obra e às peças listadas, com as causas de perda de garantia); campos de assinatura (imagem da assinatura do cliente se existir, "Assinatura do Cliente") e responsável técnico; rodapé de geração eletrônica.
- **Notas:** a comissão/mão de obra do técnico **não** aparece nem entra no total (é custo interno). As colunas de desconto existem no layout, mas hoje o sistema não grava desconto, então saem `R$ 0,00` (`Desconto` do resumo também `- R$ 0,00`).

---

## Ordens de Serviço

### Status e fluxos da OS

- Status: `ABERTA`, `EM_ANDAMENTO`, `AGUARDANDO_PECA`, `CONCLUIDA`, `CANCELADA`.
- Origens: (1) **manual** (`./saveOrdemServico`, nasce `ABERTA`); (2) **aprovação de orçamento** (nasce `EM_ANDAMENTO`, vinculada ao orçamento); (3) **agendamento** (botão "Gerar OS" na agenda, `POST ./gerarOsAgendamento`, ADMIN/ATENDENTE, abre `./saveOrdemServico` pré-preenchido com `cliente_id`, `veiculo_id`, `defeito` e `agendamento_id`; ao salvar, o agendamento é vinculado à OS; se o agendamento já tem OS, vai direto para `viewOrdemServico`).
- Número: `OS-0001` (id com 4 dígitos) definido após a inserção.
- Estoque: cada produto da OS dá baixa em `produtos.estoque_atual` ao salvar; ao editar, o estoque dos itens antigos é devolvido e os novos são baixados (tudo em transação).
- **Concluir** (`POST ./concluirOrdemServico`, ADMIN/ATENDENTE): status `CONCLUIDA`, `data_conclusao` = agora, grava assinatura (se enviada). Se a forma de pagamento for válida, existir caixa aberto e o valor da OS for > 0, lança uma **ENTRADA** no caixa (origem/categoria "OS", descrição "OS OS-000N", usuário da sessão). Sempre tenta enviar e-mail ao cliente "Seu veículo está pronto - Mecanize" (placa, número da OS e link de rastreio) — falhas de caixa/e-mail são só registradas em log e não desfazem a conclusão.
- **Kanban** (arrastar e soltar) muda para ABERTA, EM_ANDAMENTO, AGUARDANDO_PECA ou CONCLUIDA; ao ir para CONCLUIDA preenche `data_conclusao` (se vazia) e envia o e-mail "veículo pronto" — mas **não** lança no caixa nem pede assinatura/forma de pagamento. Ao ir para outra coluna, `data_conclusao` é limpa.
- **Editar** pode colocar qualquer um dos 5 status (inclusive CANCELADA); status diferente de CONCLUIDA zera `data_conclusao`; CONCLUIDA sem data usa agora. Também não lança caixa nem envia e-mail.
- **Excluir** (`POST ./deleteOrdemServico`, somente ADMIN): bloqueia se houver agendamento vinculado (`?erro=vinculos&detalhe=...`); senão apaga a OS e seus produtos, serviços, mão de obra e anexos em transação (`?ok=excluido`); falha: `?erro=exclusao`. Não devolve estoque.
- Cancelada aparece na aba "OS Arquivadas".

### Listagem de Ordens de Serviço (`listOrdemServico`)

- **URL/rota:** `./listOrdemServico` → `OrdemServicoController::list`. Sem `requireRole` na listagem (qualquer logado; todos os cargos têm o item no menu). Usa `getResumo($_GET)`; o filtro `busca` por GET (número, cliente ou placa) é aceito pelo model, mas a tela filtra no navegador. Máximo de 50 OS por aba (ordenadas por data de abertura, mais recentes primeiro).
- **Mensagens (flash):** `?erro=vinculos` ("Esta ordem de serviço não pode ser excluída porque está vinculada a outros registros (detalhe). Remova o vínculo antes de excluí-la."), `?erro=exclusao` ("Não foi possível excluir esta ordem de serviço.") e `?ok=excluido` ("Ordem de serviço excluída.").
- **Cabeçalho:** "Ordens de Serviço — Gerencie todas as ordens de serviço da oficina." Botões "Ver como Kanban" (`./kanbanOrdemServico`) e "Nova Ordem de Serviço" (`./saveOrdemServico`).
- **Abas:**
  - **OS Abertas** (status ABERTA) — colunas: ID da OS (`#OS-000N`), Cliente, Veículo (modelo, ano e placa embaixo), Data de Entrada (`dd/mm/aaaa · HH:MM`), Valor, Ações. Busca "Buscar por cliente, OS ou placa..." e filtro de data. Vazio: "Nenhuma ordem aberta encontrada." Ações: Visualizar, Concluir OS (abre modal), Editar, Excluir (só ADMIN; formulário POST com confirmação "Excluir esta Ordem de Serviço? Os produtos, serviços e mão de obra lançados nela são apagados junto, em definitivo.").
  - **OS Em Andamento** (EM_ANDAMENTO e AGUARDANDO_PECA) — colunas: ID da OS, Cliente, Veículo, Status (selo "Em andamento" azul ou "Aguardando peça" amarelo), Data de Entrada, Valor, Ações. Filtros: busca, select "Status: Todos / Em andamento / Aguardando peça", data. Mesmas ações da aba Abertas. Vazio: "Nenhuma ordem em andamento encontrada."
  - **OS Concluídas** (CONCLUIDA) — colunas: ID da OS, Cliente, Veículo, Data de Entrada, Data de Conclusão (`dd/mm/aaaa`), Valor, Ações (Visualizar, Editar). Busca e data. Vazio: "Nenhuma ordem concluída encontrada."
  - **OS Arquivadas** (CANCELADA) — mesmas colunas das concluídas; ação só Visualizar. Vazio: "Nenhuma ordem arquivada encontrada."
- **Contador:** "N resultado(s)" em cada aba, atualizado ao filtrar. Os filtros são client-side (`filtrarTabela`): a busca procura em todo o texto da linha; o filtro de data lê a **5ª coluna** da tabela (em Em Andamento e nas Concluídas/Arquivadas isso corresponde à data de entrada/conclusão; na aba Abertas a 5ª coluna é o Valor, então o filtro de data não funciona ali).
- **Modal "Concluir Ordem de Serviço"** (`abrirModalConcluir(id, numero)`): "Confirma a conclusão desta OS?" com a referência "OS #número"; aviso "Esta ação não poderá ser desfeita."; select **Forma de pagamento** (obrigatório): Dinheiro (`DINHEIRO`), Pix (`PIX`), Cartão (`CARTAO`), Boleto (`BOLETO`) — nota "O valor da OS é lançado automaticamente no caixa ao concluir."; canvas "Assinatura do cliente (retirada do veículo, opcional)" com "Limpar assinatura"; botões "Cancelar" e "Confirmar Conclusão". Formulário `POST ./concluirOrdemServico` com `id_ordem_servico`, `assinatura_cliente` e `forma_pagamento`.

### Kanban de Ordens de Serviço (`kanbanOrdemServico`)

- **URL/rota:** `./kanbanOrdemServico` → `OrdemServicoController::kanban`. Sem `requireRole`. Título "Ordens de Serviço — Kanban", subtítulo "Arraste os cards entre as colunas para mudar o status." Botões "Ver como lista" (`./listOrdemServico`) e "Nova Ordem de Serviço" (`./saveOrdemServico`). A tela não tem campo de busca (embora o controller repasse `$_GET['busca']` aos filtros).
- **Colunas (nesta ordem):** Aberta (`ABERTA`), Em Andamento (`EM_ANDAMENTO`), Aguardando Peça (`AGUARDANDO_PECA`), Concluída (`CONCLUIDA`). Cada coluna mostra um contador com o número de cards e, se vazia, "Nenhuma OS aqui." Cada coluna lista até 50 OS (mais recentes primeiro). OS canceladas não aparecem.
- **Card:** "OS #número", cliente, "veículo · PLACA", valor (`R$`) e link "Detalhes →" (`./viewOrdemServico&id=`).
- **Regras de mover:** qualquer card pode ser arrastado para qualquer outra coluna (sem restrição de ordem ou sentido). Ao soltar, o card muda de coluna imediatamente (otimista), os contadores são atualizados e é enviado `fetch POST ./moverStatusOrdemServico` com `id_ordem_servico` e `status`. O endpoint responde JSON `{sucesso: bool}`: 405 se não for POST; 400 ("Dados inválidos.") se id/status inválido; status só aceita os 4 do Kanban. Se falhar (resposta `sucesso:false` ou erro de rede), o card volta e aparece `alert('Não foi possível mover a OS. Tente novamente.')`. Soltar na mesma coluna não faz nada. Mover para Concluída preenche a data de conclusão e envia o e-mail "veículo pronto" ao cliente (sem lançar no caixa); mover para outras colunas limpa a data de conclusão. O endpoint não tem `requireRole` (qualquer logado).
- **JS:** `public/assets/js/kanban.js` (HTML5 drag-and-drop nativo, classes `is-dragging`/`is-dragover`).

### Nova Ordem de Serviço (`saveOrdemServico`)

- **URL/rota:** GET/POST `./saveOrdemServico` → `OrdemServicoController::save`, `requireRole(['ADMIN','ATENDENTE'])`. Modal em tela cheia: "Nova Ordem de Serviço — Preencha os dados abaixo para abrir uma nova OS." O "X" volta a `./listOrdemServico`.
- **Pré-preenchimento (vindo da agenda):** parâmetros GET `cliente_id`, `veiculo_id`, `defeito`, `agendamento_id` pré-selecionam cliente, veículo (após filtrar os veículos do cliente), preenchem "Defeito Relatado" e geram o oculto `agendamento_id`.
- **Validação servidor:** `cliente_id` e `veiculo_id` obrigatórios; em falha mostra o erro e mantém `old`. Falha de gravação: "Erro ao cadastrar ordem de serviço[: detalhe]". Sucesso: se veio `agendamento_id`, vincula o agendamento à OS; redireciona a `./listOrdemServico`.
- **Campos:**
  - ① Identificação: `numero_os` (somente leitura, próximo número; o servidor gera o número real), `status` (oculto `ABERTA`, exibido "Aberta"), `data_abertura` (`datetime-local`, somente leitura, agora).
  - ② `cliente_id` (select, obrigatório), `veiculo_id` (select, obrigatório, habilita ao escolher o cliente e lista só os veículos dele; validação manual no envio com borda vermelha), `km_veiculo` (number min 0, "Ex.: 85000", sufixo km).
  - ③ Mecânico: `mecanico_id` (select) e, ao escolher, % de comissão (0–100, passo 0,1), base de cálculo (Somente Serviços / Somente Peças / Peças + Serviços) e prévia; ocultos `valor_mao_obra`, `comissao_pct`, `comissao_base`. Servidor: se há `mecanico_id`, grava uma linha em mão de obra com descrição "Mão de obra (X%)" e o valor da comissão.
  - ④ Produtos & Serviços: igual ao orçamento (tabela de itens com Qtd −/+, remover, modal de catálogo com busca/abas por categoria e aba "Personalizado" para serviço com valor fixo ou % sobre peças). Enviados como `itens[i][...]`. Produtos personalizados (`custom_`) são ignorados no servidor; serviços personalizados são gravados com `personalizado=1` e `percentual_base`.
  - ⑤ Diagnóstico: `defeito_relatado`, `diagnostico`, `observacoes` (textareas).
  - ⑥ Resumo financeiro: Total em Peças, Total em Serviços, Comissão Técnico, Lucro Líquido (total − comissão), Total da OS (= peças + serviços); ocultos `valor_total_pecas`, `valor_total_servicos`, `valor_total_os`.
  - ⑦ Conclusão: `data_conclusao` (`datetime-local`, opcional).
- **Rodapé:** "Criado em", "Atualizado em", "Usuário: nome (cargo)"; botões "Imprimir OS" (`window.print()`), "Limpar" (confirma e reseta), "Cancelar" e "Gravar OS". Não há campo de desconto.

### Editar Ordem de Serviço (`editOrdemServico`)

- **URL/rota:** GET `./editOrdemServico?id=N` e POST `./editOrdemServico` → `OrdemServicoController::edit`. **Sem `requireRole`** no controller (qualquer logado; a listagem só oferece o botão em Abertas, Em Andamento e Concluídas). Sem id volta à lista; inexistente mostra "registro não encontrado" (Ordem de serviço).
- **Cabeçalho:** "Editar Ordem de Serviço · OS-000N", subtítulo "Atualize as informações da ordem de serviço.", selo com o status atual e botão "Voltar".
- **Validação servidor:** `numero` obrigatório, `status` obrigatório e dentro de ABERTA/EM_ANDAMENTO/AGUARDANDO_PECA/CONCLUIDA/CANCELADA, `cliente_id` e `veiculo_id` obrigatórios. Erro de gravação: "Erro ao salvar alterações[: detalhe]". Sucesso: `./listOrdemServico`.
- **Campos (`POST ./editOrdemServico`):** oculto `id_ordem_servico`; 1. Identificação: `numero` (texto, obrigatório, "Ex: OS-0001"), `status` (select obrigatório com os 5 status), `data_abertura` e `data_conclusao` (`datetime-local`); 2. `cliente_id` (select obrigatório), `veiculo_id` (select obrigatório com todos os veículos, sem filtro por cliente), `km_veiculo` (number, "Ex: 45000"); 3. Mecânico: `mecanico_id` (select; pré-selecionado pelo técnico da mão de obra existente), `% de comissão` (calculada **somente sobre serviços**; esta tela não tem seletor de base de cálculo e não envia `comissao_base`) e prévia; ocultos `valor_mao_obra`, `comissao_pct`; 4. Diagnóstico: `defeito_relatado`, `diagnostico`, `observacoes`; 5. Produtos & Serviços: mesma tabela/modal de catálogo do cadastro, já preenchida com os itens da OS (permite adicionar, remover, mudar quantidades); 6. Resumo Financeiro (Total em Peças, Total em Serviços, Comissão Técnico, Lucro Líquido, Total da OS) e ocultos `valor_total_pecas`, `valor_total_servicos`, `valor_total_os`.
- **Efeito de salvar:** reescreve produtos, serviços e mão de obra da OS (devolve ao estoque os produtos antigos e baixa os novos).
- **Rodapé:** "Excluir Ordem" (somente ADMIN; botão que envia o formulário oculto `POST ./deleteOrdemServico` com confirmação), "Cancelar" e "Salvar Alterações".

### Visualizar Ordem de Serviço (`viewOrdemServico`)

- **URL/rota:** `./viewOrdemServico?id=N` → `OrdemServicoController::visualizar`. Sem `requireRole` (qualquer logado). Sem id volta à lista; inexistente mostra "registro não encontrado".
- **Cabeçalho (modal):** "Ordem de Serviço — OS-000N", "Visualização detalhada da OS", selo do status (cores específicas para ABERTA, CONCLUIDA e CANCELADA; demais em cinza, mostrando o código do status) e botão fechar (`history.back()`).
- **Stepper:** Aberta → Em Andamento → Aguardando Peça → Concluída (marca as etapas anteriores à atual); se CANCELADA, mostra "Esta Ordem de Serviço foi cancelada."
- **Seções:** ① Identificação (Número da OS, Status, Data de Abertura, Data Conclusão); ② Cliente e Veículo (cliente, "modelo — PLACA", KM formatado); ③ Mecânico Responsável (avatar, nome, "Comissão: X% → R$ valor" e selo "Responsável"; ou "Nenhum mecânico atribuído."); ④ Produtos & Serviços (Item/categoria, Tipo, Qtd, Unit., Subtotal; vazio "Nenhum item registrado nesta OS."); ⑤ Diagnóstico (Defeito Relatado, Diagnóstico Técnico, Observações); ⑥ Resumo Financeiro (Total em Peças, Total em Serviços, Comissão Técnico, Lucro Líquido = total − comissão, Total da OS); ⑦ Assinatura do Cliente (só se houver, gravada ao concluir).
- **Rodapé / botões:** "Abertura: data"; "Usuário: nome (cargo)"; **Enviar WhatsApp** (só se status CONCLUIDA e cliente com telefone; mensagem "Olá, {cliente}. Seu veículo (placa X), referente à OS nº Y, está pronto para retirada." + link de rastreio); **Baixar PDF** (`./exportOrdemServicoPdf?id=`); **Link de Rastreio** (copia a URL pública `rastrear?token=...`, mostra "Copiado!"); **Imprimir** (`window.print()` da própria tela); **Editar** (`./editOrdemServico?id=`); **Voltar**.

### PDF da Ordem de Serviço (`pdfOrdemServico`)

- **URL/rota:** `./exportOrdemServicoPdf?id=N` → `OrdemServicoController::exportarPdf` (Dompdf; arquivo `os_<numero>.pdf`). Sem `requireRole`. Com `&inline=1` o PDF abre no visualizador do navegador em vez de baixar (usado na prévia/impressão de Documentos).
- **Conteúdo:** cabeçalho com logo "M" e dados da empresa (`EMPRESA_*`), "Ordem de Serviço", número e selo de status (Aberta, Concluída, Cancelada; outros status mostram o código); faixa "Aberta em", "Concluída em", "Responsável" (mecânico); blocos Cliente (nome, CNPJ/CPF, telefone, endereço completo) e Veículo (marca/modelo, placa, Ano, Cor, KM); "Problema Relatado" e "Diagnóstico" (se houver); tabela única "Peças & Serviços" (Descrição, Qtd, Valor Unit., Desconto Unit., Total do Item); totais (Total Peças, Total Serviços, Subtotal, Desconto, **Total Geral** = valor total da OS); Observações; "Termos da Garantia" (90 dias corridos a partir da conclusão/retirada, restrita à mão de obra e às peças listadas, com as causas de perda); assinatura do cliente (imagem, se houver) e responsável técnico; rodapé de geração eletrônica.
- **Notas:** comissão e lucro do técnico não aparecem (documento para o cliente). As colunas/linha de desconto saem sempre `R$ 0,00`, pois o sistema não grava desconto.

---

## Documentos

Módulo exclusivo de **ADMIN** (`requireRole(['ADMIN'])` em todas as ações; item "Documentos" só aparece no menu para ADMIN). Serve para gerar o documento final (PDF/CSV/impressão) de uma OS já concluída.

### Listagem de Documentos — "Serviços Finalizados" (`listDocumentos`)

- **URL/rota:** `./listDocumentos` → `DocumentoController::list`.
- **Finalidade:** "Selecione uma OS concluída para gerar o documento." Lista as **20** últimas OS com status `CONCLUIDA` (ordenadas por data de conclusão, mais recente primeiro), já com seus itens (peças e mão de obra).
- **Cartões:** OS Disponíveis (quantidade, "OS concluídas disponíveis"), Valor Total (soma de `valor_total_os`, "Soma das OS do período"), Ticket Médio (valor total ÷ quantidade, "Por ordem concluída").
- **Filtros (client-side):** busca "Buscar cliente, veículo ou OS..."; select "Mecânico: Todos" (mecânicos presentes na lista); data (compara com a data de conclusão); contador "N resultado(s)".
- **Banner:** "Clique em qualquer OS para gerar o documento final automaticamente."
- **Tabela:** OS, Cliente, Veículo / Placa, Mecânico, Concluída em (`dd/mm/aaaa HH:MM`), Valor Total, e um selo "Gerar documento". A linha inteira é clicável (`selecionarOS`). Vazio: "Nenhuma OS concluída disponível para documento." Paginação apenas visual.
- **Modal "Gerar Documento" (conferência):** "Confira os dados antes de finalizar." (Referência: número). Seções somente leitura: ① Dados da OS (número, cliente, veículo/placa, mecânico, data de abertura, data de conclusão); ② Diagnóstico (defeito relatado, diagnóstico técnico); ③ Produtos & Serviços (Item, Tipo, Qtd, Unit., Subtotal; vazio "Nenhum item registrado."); ④ Resumo Financeiro (Total em Peças, Total em Serviços, Total da OS = peças + serviços). Rodapé "Gerado em: data/hora", botões "Cancelar" e "Finalizar".
- **Modal "Documento Gerado" (tela final):** "Pronto para baixar, imprimir ou compartilhar." Opções: **Baixar PDF** (`./exportOrdemServicoPdf?id=`), **Baixar Excel** (gera no navegador um CSV `documento-<numero>.csv`, separador `;`, UTF-8 com BOM, colunas Item;Tipo;Qtd;Preco Unit.;Subtotal e linha de Total do `valor_total_os`) e **Imprimir** (abre `./exportOrdemServicoPdf?id=...&inline=1` em nova aba). Inclui uma prévia (iframe `previa-documento-frame` com o PDF da OS, `inline=1`) e botões "Voltar" (retorna à conferência), "Fechar", "Imprimir" e "Baixar PDF". Os dois modais fecham ao clicar fora.
- **Observação:** o botão "Finalizar" do modal apenas abre a tela final no navegador; não chama `finalizarDocumento` nem grava nada.

### Gerar Orçamento / Finalizar Documento (`saveDocumento`)

- **URL/rota:** GET `./saveDocumento?os_id=N` → `DocumentoController::save` (ADMIN). A rota `saveDocumento` existe, mas a listagem atual não aponta para ela (o fluxo da lista é feito por modais). Sem `os_id` ou com OS inexistente redireciona a `./listDocumentos`.
- **Finalidade:** tela modal "Gerar Orçamento — Referência: OS-000N" com os dados da OS para conferência. Fechar ("X" ou clicar fora) volta a `./listDocumentos`.
- **Conteúdo (somente leitura):** ① Dados da OS (número, cliente, veículo/placa, datas de abertura e conclusão); ② Diagnóstico (defeito, diagnóstico); ③ Produtos & Serviços (Item, Tipo Peça/Serviço, Qtd, Unit., Subtotal; vazio "Nenhum item registrado nesta OS."); ④ Resumo Financeiro (Total em Peças, Total em Serviços, Total da OS). Rodapé: "Gerado em", "usuário (cargo)", botões "Voltar" (`./listDocumentos`) e "Finalizar Documento".
- **Formulário:** `POST ./finalizarDocumento` com o campo oculto `os_id`.
- **Ação POST `finalizarDocumento`** (`DocumentoController::finalizar`, ADMIN): exige POST e `os_id`; chama `Documento::finalizar`, que só confere se a OS existe e está `CONCLUIDA` (não altera nada no banco). Sucesso: redireciona a `./listDocumentos`; falha: reexibe a listagem com o erro "Erro ao finalizar o orçamento." (a listagem de Documentos não renderiza `$error`, então a mensagem não aparece na prática).

---

# Parte 4 — Caixa, Produtos, Categorias, Movimentação de Estoque e Relatórios

Convenções gerais (valem para todos os módulos abaixo):

- As URLs são relativas à pasta `public/` e usam o nome da rota diretamente (ex.: `./listCaixa`), definido em `config/routes.php`.
- Todas as rotas exigem login (`public/index.php` garante a sessão). Todo POST é validado por token CSRF (`Csrf`).
- Cargos: `ADMIN` (dono/gerente, acesso total), `ATENDENTE` (recepção/balcão, opera o caixa) e `TECNICO` (mecânico, enxerga produtos, dá baixa de peça no estoque, não mexe em dinheiro). O bloqueio real é feito por `Controller::requireRole()` (cargo não permitido recebe a tela de erro "sem-permissão"); esconder item de menu não é o controle. Rota sem `requireRole` fica acessível aos três cargos.
- Campos de dinheiro usam a máscara **IMask** (`mask: Number`, 2 casas, separador de milhar `.`, decimal `,`) e, no submit, o JavaScript troca o valor mascarado pelo valor cru (ex.: `1234.56`). O back-end ainda tolera o formato pt-BR (`parseMoney`).
- Valores são exibidos no padrão brasileiro (`R$ 1.234,56`); datas em `d/m/Y H:i`.

---

## Caixa

Regra central do módulo: só pode existir **uma sessão de caixa ABERTA por vez** (`Caixa::getCaixaAberto()` devolve a sessão com `status = 'ABERTO'`; `abrir()` recusa abrir outra). O módulo inteiro (exceto o relatório por período) é restrito a `ADMIN` e `ATENDENTE`; o `TECNICO` não vê o item "Caixa" no menu nem o indicador na topbar e é barrado nas rotas.

Tabelas envolvidas: `caixa` (sessões) e `movimentacoes_caixa` (livro-caixa: lançamentos de entrada/saída por sessão).

Regras de negócio do caixa:

- **Abertura**: registra usuário que abriu, valor de abertura (troco inicial), observação opcional e `status = 'ABERTO'`. O valor sugerido é o **saldo do caixa anterior** (`valor_fechamento` da última sessão `FECHADO`; 0,00 se nunca houve fechamento).
- **Fechamento**: registra usuário que fechou, `data_fechamento = NOW()`, `valor_fechamento` (dinheiro contado), `valor_esperado` (o "caixa atual" calculado no momento do fechamento, guardado para auditoria), observação e `status = 'FECHADO'`.
- **Caixa atual (dinheiro na gaveta)** = `valor_abertura + Σ entradas em DINHEIRO − Σ todas as saídas`. PIX, cartão e boleto são registrados e somam nos totais de entradas, mas **não** entram no dinheiro da gaveta; toda saída é considerada paga em dinheiro.
- **Diferença de caixa** = `valor contado − valor esperado` (sobra se positivo, falta se negativo).
- **Formas de pagamento (entrada)**: `DINHEIRO`, `PIX`, `CARTAO`, `BOLETO`.
- **Categorias de saída manual**: `PECAS` (Peças), `BANCO` (Retirada para banco), `OUTRO` (Outro). Lançamentos automáticos podem ter origem `OS` (entrada vinda da conclusão de uma ordem de serviço) e categorias `OS`/`VENDA` aparecem só como rótulo.
- Valor do lançamento deve ser maior que zero (`registrar()` retorna false se `<= 0`).
- Existe também `Caixa::fecharSePendenteExpirado()` (fechamento automático com a observação "Fechado automaticamente — aba do navegador foi fechada", usando o saldo calculado como valor de fechamento), disparado fora destas telas quando `pendente_fechamento_em` expira (12 s por padrão).

### Abrir Caixa

- **Rota/URL**: `./abrirCaixa` (`CaixaController::abrir`), GET exibe o formulário, POST registra a abertura.
- **Acesso**: ADMIN e ATENDENTE.
- **Finalidade**: iniciar a sessão de caixa do dia. Subtítulo da tela: "É obrigatório abrir o caixa para começar a utilizar o sistema."
- **Comportamento**: se já houver caixa aberto, GET e POST redirecionam para `./home`. Sem usuário na sessão, redireciona para `./logout`. Sucesso redireciona para `./home`.
- **Elementos da tela**:
  - Mensagem de erro (caixa vermelha) quando a validação falha ou a abertura não é possível ("Não foi possível abrir o caixa.").
  - Cartão informativo "quem está abrindo": avatar com iniciais (primeira e última letra do nome), nome do usuário logado e "Abrindo o caixa em dd/mm/aaaa às HH:MM".
  - Faixa "Saldo do caixa anterior (último fechamento)" com o valor em `R$`.
- **Formulário** (`POST ./abrirCaixa`, id `formAbrirCaixa`):

| Campo | Nome | Tipo | Obrigatório | Observações |
|---|---|---|---|---|
| Valor de abertura (troco inicial) | `valor_abertura` | text (`inputmode="numeric"`) | Sim (HTML `required` + `Validator::required`) | Máscara de dinheiro IMask, `min: 0`; pré-preenchido com o saldo anterior (ou com o valor antigo em caso de erro); texto de ajuda: "Sugerido a partir do fechamento anterior — ajuste se o troco inicial for diferente." |
| Observação | `observacao` | textarea (2 linhas) | Não | Placeholder "Ex.: caixa aberto no início do expediente"; salva `NULL` se vazio |

- **Botões**: "Abrir Caixa" (submit, largura total).
- **JS**: IMask no campo de valor; na carga define `typedValue` a partir do valor inicial; no submit grava o valor cru (`String(m.typedValue || 0)`).

### Fechar Caixa

- **Rota/URL**: `./fecharCaixa` (`CaixaController::fechar`), GET exibe confirmação, POST fecha.
- **Acesso**: ADMIN e ATENDENTE.
- **Finalidade**: encerrar o caixa do dia informando o dinheiro contado. Subtítulo: "Encerre o caixa do dia informando o valor final."
- **Comportamento**: se não há caixa aberto, redireciona para `./home`. Após fechar com sucesso, redireciona para `./listCaixa`. Falha de validação/execução reexibe a tela com erro ("Não foi possível fechar o caixa."). O controller passa à view a sessão (`caixa`) e o `resumoSessao` (`resumo`).
- **Elementos da tela**:
  - Botão "Cancelar" no cabeçalho (volta a `./home`).
  - Alerta de erro (componente `layout/_alerta.php`) quando houver.
  - Cartão de contexto: avatar/nome de quem está fechando e "Fechando o caixa em dd/mm/aaaa às HH:MM"; linhas "Aberto por", "Aberto em", "Valor de abertura".
  - Cartão "Contagem final" com o formulário.
  - Bloco "Diferença (contado − esperado)" calculado ao vivo, com cor: vermelho se negativa, verde se positiva, neutra se zero; formato `+ R$ x`, `− R$ x`.
- **Formulário** (`POST ./fecharCaixa`, id `formFecharCaixa`):

| Campo | Nome | Tipo | Obrigatório | Observações |
|---|---|---|---|---|
| Valor de fechamento (dinheiro contado) | `valor_fechamento` | text (`inputmode="numeric"`) | Sim (`required` + `Validator::required`) | IMask de dinheiro, `min: 0`; pré-preenchido com o dinheiro esperado na gaveta (`caixa_atual`); ajuda: "Sugerido com o dinheiro esperado na gaveta — ajuste com o valor realmente contado." |
| Observação | `observacao` | textarea (2 linhas) | Não | Placeholder "Ex.: fechamento do expediente" |

- **Botões**: "Fechar Caixa" (botão vermelho `btn--danger`, com `confirm('Confirma o fechamento do caixa?')`).
- **JS**: IMask no valor; `esperado` vem do PHP (`caixa_atual`); evento `accept` do IMask recalcula a diferença (`fechamento − esperado`, arredondada a 2 casas); no submit grava valor cru.
- **Regra**: o `valor_esperado` gravado no fechamento é o `caixa_atual` do `resumoSessao` calculado no momento do POST.

### Caixa (histórico de sessões)

- **Rota/URL**: `./listCaixa` (`CaixaController::list`).
- **Acesso**: ADMIN e ATENDENTE. A regra de visibilidade é aplicada no controller: **ATENDENTE só vê sessões em que abriu ou fechou** (`usuario_abertura_id` ou `usuario_fechamento_id` igual ao seu id); ADMIN vê todo o histórico (até 100 sessões, `historico($limite = 100)`, ordenado da mais recente).
- **Finalidade**: ver o estado do caixa atual e o histórico de aberturas/fechamentos. Subtítulo: "Histórico de aberturas e fechamentos de caixa."
- **Botões do cabeçalho** (dependem do estado):
  - Com caixa aberto: "Lançamentos" (`./caixaMovimentacoes`) e "Fechar Caixa" (`./fecharCaixa`).
  - Sem caixa aberto: "Abrir Caixa" (`./abrirCaixa`).
- **Cards/indicadores (só com caixa aberto)** — grade de 4 cards:
  1. **Status**: "Aberto", "Por <nome> às HH:MM".
  2. **Caixa atual (dinheiro)**: `caixa_atual` do resumo (em vermelho se negativo) + "Abertura R$ ...".
  3. **Entradas**: `total_entradas` da sessão ("Nesta sessão").
  4. **Saídas**: `total_saidas` da sessão ("Nesta sessão").
- **Cartão "Composição da sessão"** (só com caixa aberto; mostra "Aberta em dd/mm/aaaa HH:MM"): duas colunas — "Entradas por forma" (Dinheiro, Pix, Cartão, Boleto) e "Saídas por categoria" (Peças, Banco, Outro), cada linha com o total em `R$`.
- **Sem caixa aberto**: cartão central "Nenhum caixa aberto — Abra o caixa para começar a registrar entradas e saídas do dia." com botão "Abrir Caixa".
- **Tabela "Histórico de sessões"** (badge com "N sessõe(s)"; sem filtros, busca nem paginação):

| Coluna | Conteúdo |
|---|---|
| # | `#id_caixa` |
| Status | badge "Aberto" (verde) ou "Fechado" (cinza) |
| Aberto por | nome de quem abriu |
| Abertura | data/hora de abertura |
| Valor abertura | `R$` |
| Fechado por | nome ou `—` |
| Fechamento | data/hora ou `—` |
| Valor fechamento | `R$` ou `—` |
| Diferença | `valor_fechamento − valor_esperado` (verde se sobra, vermelho se falta, neutro se zero); `—` quando a sessão não está fechada ou foi fechada antes de existir a coluna `valor_esperado` |

- Linha vazia: "Nenhuma sessão de caixa registrada ainda."

### Caixa — Lançamentos (livro-caixa)

- **Rota/URL**: `./caixaMovimentacoes` (`CaixaController::movimentacoes`, GET). O formulário envia para `./registrarMovimentacaoCaixa` (`CaixaController::registrarMovimentacao`, somente POST; GET redireciona para `./caixaMovimentacoes`).
- **Acesso**: ADMIN e ATENDENTE. Exige caixa **aberto**; sem caixa aberto redireciona para `./abrirCaixa`. É a tela para a qual o indicador "Caixa aberto · <nome>" da topbar aponta.
- **Finalidade**: registrar entradas e saídas manuais da sessão aberta e acompanhar o resumo. Subtítulo: "Registre as entradas e saídas da sessão de caixa aberta."
- **Botões do cabeçalho**: "Histórico" (`./listCaixa`) e "Fechar Caixa" (`./fecharCaixa`).
- **Alertas**: sucesso "Movimentação registrada com sucesso." (`?ok=1`); erro (`?erro=1`) "Não foi possível registrar a movimentação. Confira o tipo, a forma/categoria e um valor maior que zero."
- **Cartão de sessão**: badge "Caixa aberto", "Aberto por <nome> em <data> — abertura R$ ...", e destaque grande "Caixa atual (dinheiro na gaveta)".
- **Formulário "Nova movimentação"** (`POST ./registrarMovimentacaoCaixa`, id `formMov`):

| Campo | Nome | Tipo | Obrigatório | Observações |
|---|---|---|---|---|
| Tipo | `tipo` | botões segmentados (radio) | Sim | `ENTRADA` (padrão, "↑ Entrada") ou `SAIDA` ("↓ Saída") |
| Forma de pagamento | `forma_pagamento` | select | Sim, quando Entrada | Opções: Dinheiro, PIX, Cartão, Boleto (valores `DINHEIRO`, `PIX`, `CARTAO`, `BOLETO`). Ajuda: "Só Dinheiro soma no caixa atual; PIX, cartão e boleto entram só no resumo/relatórios." Visível só em Entrada |
| Categoria da saída | `categoria` | select | Sim, quando Saída | Opções: Peças (`PECAS`), Retirada para banco (`BANCO`), Outro (`OUTRO`). Visível só em Saída |
| Valor | `valor` | text (`inputmode="numeric"`) | Sim (`required`) | IMask de dinheiro, `min: 0`; deve ser > 0 no servidor |
| Descrição | `descricao` | text, `maxlength=255` | Não | Placeholder "Ex.: troca de óleo, compra de pastilhas..." |

- **Validação no servidor**: o tipo é normalizado em maiúsculas; em ENTRADA a categoria é descartada e a forma deve estar em `FORMAS`; em SAÍDA a forma é descartada e a categoria deve estar em `CATEGORIAS_SAIDA`; tipo inválido ou valor `<= 0` leva a `?erro=1`. O lançamento é gravado com `origem = 'MANUAL'`, sem OS, e o usuário logado. Padrão post/redirect/get.
- **Botão**: "Registrar movimentação".
- **Cartão "Resumo da sessão"**: entradas por forma (Dinheiro, PIX, Cartão, Boleto) + "Total de entradas" (verde); saídas por categoria (Peças, Retirada p/ banco, Outro) + "Total de saídas" (vermelho).
- **Tabela de lançamentos da sessão** (mais recentes primeiro; sem filtro/paginação):

| Coluna | Conteúdo |
|---|---|
| Data/hora | `data_movimentacao` |
| Tipo | badge Entrada (verde) / Saída (vermelho) |
| Forma / Categoria | forma (entrada) ou categoria (saída: Peças, Retirada p/ banco, Outro, Ordem de Serviço, Venda); badge "OS" quando a origem é `OS` |
| Descrição | texto ou `—` |
| Registrado por | nome do usuário |
| Valor | `+ R$` (verde) para entrada, `− R$` (vermelho) para saída |

- Linha vazia: "Nenhuma movimentação registrada nesta sessão ainda."
- **JS**: alterna entre os campos de forma (entrada) e categoria (saída) conforme o rádio de tipo, desabilitando/`required` o select escondido para não ser enviado; IMask no valor com envio do valor cru. Layout de duas colunas vira uma coluna abaixo de 820 px.

### Relatório de Caixa (por período)

- **Rota/URL**: `./relatorioCaixa` (`CaixaController::relatorio`, GET). Item "Relatório de Caixa" do menu (visível para ADMIN).
- **Acesso**: **somente ADMIN** (visão financeira consolidada).
- **Finalidade**: ver entradas por forma de pagamento e saídas por categoria em um período (pedido do cliente: diário, semanal, mensal e anual). Subtítulo mostra o rótulo do período.
- **Botões do cabeçalho**: "Histórico" (`./listCaixa`) e "Baixar PDF" (`./relatorioCaixaPdf?...` mantendo o período escolhido).
- **Seletor de período** (parâmetro GET `periodo`):
  - Abas: **Diário** (`dia`, hoje), **Semanal** (`semana`, segunda a domingo da semana corrente), **Mensal** (`mes`, mês corrente — padrão) e **Anual** (`ano`, 01/01 a 31/12 do ano corrente).
  - Formulário "De ... até ..." (GET, `periodo=custom`): campos `inicio` e `fim` (`type="date"`, ambos `required`) e botão "Aplicar". As datas são validadas (formato `YYYY-MM-DD` e existência no calendário) e ordenadas (menor = início); inválidas voltam ao mês corrente. Qualquer valor de `periodo` fora da lista cai em `mes`.
  - Granularidade do gráfico: `dia` para dia/semana/mês e personalizado até 92 dias; `mes` para ano e personalizado com mais de 92 dias.
- **Cards (4)**: Total de entradas (com "N lançamentos no período"), Total de saídas ("Peças, banco e outras retiradas"), Resultado do período (entradas − saídas, verde se >= 0, vermelho se negativo) e Sessões de caixa ("Com movimentação no período", contagem de sessões distintas).
- **Gráfico "Evolução diária/mensal"** (Chart.js, barras agrupadas, canvas `caixaChart`): série Entradas (verde `#16A34A`) e Saídas (vermelho `#DC2626`) por dia (`dd/mm`) ou mês (`jan`...`dez`); tooltip `Entradas: R$ x`; eixo Y a partir de zero com prefixo `R$`. Sem dados mostra "Nenhuma movimentação registrada neste período."
- **Cartões "Entradas por forma de pagamento" e "Saídas por categoria"**: barras horizontais com valor e percentual do total (Dinheiro, PIX, Cartão, Boleto; Peças, Retirada para banco, Outro) e linha "Total". Saídas com categoria nula/desconhecida são somadas em "Outro".
- **Tabela "Lançamentos do período"** (até 500 registros, mais recentes primeiro, contador "N registro(s)"): Data/hora, Tipo, Forma/Categoria (com badge "OS <número>" quando a entrada vem de ordem de serviço), Descrição, Registrado por, Valor (`+`/`−`). Linha vazia: "Nenhuma movimentação registrada neste período."

### Relatório de Caixa em PDF

- **Rota/URL**: `./relatorioCaixaPdf` (`CaixaController::exportarRelatorioPdf`), mesmos parâmetros GET do relatório (`periodo`, `inicio`, `fim`).
- **Acesso**: somente ADMIN.
- **Geração**: a view `Caixa/pdfRelatorioCaixa.php` é renderizada em buffer e entregue ao Dompdf (`Pdf::baixar`) como download `caixa_<inicio>_a_<fim>.pdf`. Usa cores hexadecimais literais e layout em tabelas (limitações do Dompdf).
- **Conteúdo**:
  1. Faixa azul no topo e cabeçalho com marca "Mecanize — Relatório financeiro de caixa", título "Relatório de Caixa", rótulo do período, intervalo de datas e "emitido em dd/mm/aaaa HH:MM".
  2. Três totais: Total de entradas (verde), Total de saídas (vermelho), Resultado (entradas − saídas; verde/vermelho).
  3. Tabela "Entradas por forma de pagamento" (forma, valor, % do total) com linha de total (100%).
  4. Tabela "Saídas por categoria" (Peças, Retirada para banco, Outro; valor e %) com linha de total.
  5. Tabela "Lançamentos do período (N)": Data/hora, Tipo, Forma/Categoria, Descrição (quando vazia e há OS, mostra "OS <número>"), Valor (`+`/`-`). Sem lançamentos: "Nenhuma movimentação registrada neste período."
  6. Rodapé: "Mecanize · relatório gerado automaticamente pelo sistema em ...".

---

## Produtos

Tabela `produtos` (com `categorias_produtos` para a categoria). Regras:

- **Código interno** é gerado automaticamente no formato `PRD-0001` (`PRD-` + id com 4 dígitos); na criação o produto é gravado com código temporário `TMP-<uniqid>` e, em seguida, atualizado com `PRD-<id>`. A tela de cadastro mostra o próximo código previsto (`MAX(id_produto)+1`).
- **Status exibido** (lista): `Inativo` (ativo != 1) → `Esgotado` (estoque <= 0) → `Estoque Baixo` (estoque <= estoque mínimo) → `Em Estoque`.
- **Estoque mínimo**: serve como limite do alerta. "Estoque baixo" = ativo, `estoque_atual > 0` e `estoque_atual <= estoque_minimo`; "esgotado" = ativo e `estoque_atual <= 0`; "em estoque" = ativo e `estoque_atual > estoque_minimo`.
- **Excluir = arquivar**: a rota `deleteProduto` não apaga; faz `ativo = 0` (`Produtos::arquiva`). Só por POST e só ADMIN.
- **Ajuste automático**: ao editar o produto e alterar o campo "Estoque Atual", o sistema grava uma linha de tipo `AJUSTE` em `movimentacoes_estoque` (quantidade com sinal, custo = preço de custo informado) para auditoria.
- Preço de custo e margem de lucro só aparecem para **ADMIN**.
- Padrões na criação: `estoque_atual`, `estoque_minimo`, `preco_custo` e `preco_venda` vazios viram 0; `ativo` padrão 1; vírgula decimal é trocada por ponto.

### Gestão de Produtos (lista)

- **Rota/URL**: `./listProduto` (`ProdutoController::list`).
- **Acesso**: todos os cargos (sem `requireRole`).
- **Finalidade**: listar produtos, acompanhar estoque e buscar/filtrar. Subtítulo: "Gerencie seus produtos e acompanhe os níveis de inventário."
- **Botões do cabeçalho**: "Categorias" (`./listCategoria`) e "+ Novo Produto" (`./saveProduto`). (O botão é mostrado a todos, mas `saveProduto` exige ADMIN.)
- **Cards (stat-grid)**:
  1. **Total em Estoque**: soma de `estoque_atual` dos produtos ativos ("unidades em estoque").
  2. **Estoque Baixo**: quantidade de produtos ativos com `0 < estoque_atual <= estoque_minimo` ("abaixo do mínimo").
  3. **Valor em Inventário** (somente ADMIN): soma de `estoque_atual * preco_custo` dos ativos ("baseado no preço de custo").
- **Filtros/busca** (formulário GET `./listProduto`):

| Controle | Nome | Comportamento |
|---|---|---|
| Busca textual | `busca` | `LIKE` em nome, código interno, código de barras, marca, localização e nome da categoria. Placeholder: "Buscar por nome do produto, SKU ou código..." |
| Aplicação | `aplicacao` | `LIKE` em `produtos.aplicacao`. Placeholder "Aplicação (ex.: Gol, flex, passeio)..." |
| Categoria | `categoria` | select "Categoria: Todas" + categorias; filtra por `categorias_produtos_id`; envia ao mudar |
| Status | `status` | select "Status: Todos", Ativo, Inativo, Em Estoque, Baixo Estoque, Esgotado; envia ao mudar. Observação: quando o status vem vazio ou desconhecido, a consulta **mostra só produtos ativos** (`ativo = 1`) |

  - Botão "Filtrar" e contador "N resultado(s)".
- **Tabela**: colunas Código / SKU (`codigo_interno`), Nome do Produto, Categoria ("Sem categoria" se nula), Quantidade (inteiro formatado), Preço de Venda, Status (badge: Inativo cinza, Esgotado vermelho, Estoque Baixo amarelo, Em Estoque verde), Ações. Ordenação por nome ascendente. Linha vazia: "Nenhum produto encontrado."
- **Ações por linha**: Visualizar (`./viewProduto?id=`), Editar (`./editProduto?id=`) e, só para ADMIN, Excluir (formulário POST para `./deleteProduto` com `id_produto`, `confirm('Deseja realmente arquivar este produto?')`).
- **Rodapé**: "Mostrando N produto(s)". A view tem botões de paginação, mas o controller não envia `totalPaginas`/`paginaAtual`, portanto **a lista de produtos não é paginada** (mostra todos os resultados do filtro).
- Existe `ProdutoController::listRelatorio` (view `Produtos/relProduto`) e `gerarPDF` ("PDF de produtos ainda não implementado."), mas **não há rota** apontando para eles em `routes.php`.

### Novo Produto

- **Rota/URL**: `./saveProduto` (`ProdutoController::save`), GET abre o formulário, POST grava.
- **Acesso**: somente ADMIN.
- **Finalidade**: cadastrar produto. A tela é um **modal** (`modal--xl`) sobre a página; fechar pelo "X", por "Cancelar" ou clicando fora volta a `./listProduto`.
- **Validação no servidor**: obrigatórios `categorias_produtos_id` (Categoria), `nome` (Nome) e `localizacao` (Localização); em caso de erro reexibe o modal com a mensagem e os valores antigos. Falha de gravação mostra "Erro ao cadastrar produto: <detalhe>". Sucesso redireciona para `./listProduto`.
- **Formulário** (`POST ./saveProduto`, id `formCriarProduto`), organizado em 4 seções:

**① Identificação**

| Campo | Nome | Tipo | Obrigatório | Observações |
|---|---|---|---|---|
| Código Interno | `codigo_interno` | text, somente leitura | — | Mostra o próximo `PRD-000N`; ignorado na gravação |
| Categoria | `categorias_produtos_id` | select | Sim | "Selecione a categoria" + categorias |
| Código de Barras | `codigo_barras` | text | Não | Placeholder "Ex.: 7891234567890" |
| Nome do Produto | `nome` | text | Sim | |
| Marca | `marca` | text | Não | |

**② Detalhes Técnicos**

| Campo | Nome | Tipo | Obrigatório |
|---|---|---|---|
| Descrição | `descricao` | textarea (4 linhas) | Não |
| Aplicação | `aplicacao` | textarea (4 linhas) | Não |
| Volume / Embalagem | `volume_embalagem` | text (ex.: 500ml, 1L, 20kg) | Não |

**③ Gestão de Estoque**

| Campo | Nome | Tipo | Obrigatório | Observações |
|---|---|---|---|---|
| Estoque Atual | `estoque_atual` | number | Não | Padrão 0 |
| Estoque Mínimo | `estoque_minimo` | number | Não | Padrão 0 |
| Localização no Estoque | `localizacao` | text | Sim | Ex.: "Prateleira A1"; ícone de pino |
| Ativo | `ativo` | radio Sim (1) / Não (0) | Não | Padrão Sim |

**④ Financeiro**

| Campo | Nome | Tipo | Visível para | Observações |
|---|---|---|---|---|
| Preço de Custo (R$) | `preco_custo` | text | ADMIN | IMask de dinheiro; padrão `0.00` |
| Preço de Venda (R$) | `preco_venda` | text | todos que acessam | IMask de dinheiro; padrão `0.00` |
| Margem de Lucro (%) | `margem_lucro` (id; sem `name`, não é enviado) | text numérico | ADMIN | Calculada/editável, não persiste |

- **JS (cálculo de margem)**: `margem = round((venda − custo) / custo × 100)` quando o custo > 0, senão 0; ao editar a margem manualmente (só dígitos) recalcula o preço de venda = `custo + custo × margem / 100` (2 casas), sem entrar em laço (flag `travaVenda`). Campos selecionam o conteúdo ao focar/clicar. No submit grava valores crus de custo e venda.
- **Rodapé do modal**: "Criado em" e "Atualizado em" (data de hoje), "Usuário: <nome> (<cargo>)".
- **Botões**: "Imprimir Etiqueta" (botão sem ação definida), "Excluir" (na prática **limpa o formulário**: `reset()` + recalcula margem), "Cancelar" (volta à lista) e "Gravar" (submit).

### Editar Produto

- **Rota/URL**: `./editProduto?id=<id_produto>` (`ProdutoController::edit`), GET carrega, POST (`./editProduto`) atualiza.
- **Acesso**: somente ADMIN.
- **Comportamento**: sem `id` volta a `./listProduto`; produto inexistente mostra a tela "registro não encontrado". POST exige `id_produto` (campo oculto) e valida `categorias_produtos_id`, `nome`, `localizacao` (erro reexibe a tela com os dados mesclados). Sucesso volta à lista.
- **Cabeçalho**: "Editar Produto · <código interno>", pílula "Ativo"/"Inativo" e botão "Voltar".
- **Formulário** (4 cartões numerados):

**1. Identificação**

| Campo | Nome | Tipo | Obrigatório |
|---|---|---|---|
| Código Interno | `codigo_interno` | text, somente leitura (não é atualizado) | — |
| Código de Barras | `codigo_barras` | text | Não |
| Nome do Produto | `nome` | text | Sim |
| Categoria | `categorias_produtos_id` | select | Sim |
| Marca | `marca` | text | Não |

**2. Especificações**

| Campo | Nome | Tipo | Obrigatório |
|---|---|---|---|
| Volume / Embalagem | `volume_embalagem` | text | Não |
| Aplicação | `aplicacao` | text | Não |
| Localização no Estoque | `localizacao` | text | Sim |

**3. Descrição**: `descricao` (textarea 5 linhas, opcional).

**4. Estoque e Financeiro**

| Campo | Nome | Tipo | Observações |
|---|---|---|---|
| Estoque Atual | `estoque_atual` | number, `step="0.001"`, `min="0"` | Aceita fração (litros/kg). Alterar gera linha `AJUSTE` nas movimentações |
| Estoque Mínimo | `estoque_minimo` | number, `step="0.001"`, `min="0"` | |
| Preço de Custo | `preco_custo` | text com prefixo "R$", IMask | Somente ADMIN; para outros cargos vai um campo oculto com o valor atual (evita zerar o custo) |
| Preço de Venda | `preco_venda` | text com prefixo "R$", IMask | |
| Margem de Lucro | `margem_lucro` | text com prefixo "%" | Somente ADMIN; calculada a partir de custo/venda, não persistida |
| Produto ativo | `ativo` | interruptor (checkbox `1`, com oculto `0`) | |

- **Botões**: "Excluir Produto" (ADMIN; envia o mesmo formulário via `formaction="./deleteProduto"` por POST, `formnovalidate`, com confirmação "Deseja excluir este produto? Esta ação não pode ser desfeita." — na prática arquiva), "Cancelar" e "Salvar Alterações".
- **JS**: mesma lógica de margem/IMask da tela de cadastro; no campo de margem, ao perder o foco vazio assume 0 e recalcula o preço de venda.

### Visualizar Produto

- **Rota/URL**: `./viewProduto?id=<id_produto>` (`ProdutoController::visualizar`).
- **Acesso**: todos os cargos.
- **Finalidade**: consulta somente leitura. Sem `id` volta à lista; inexistente mostra "registro não encontrado".
- **Cabeçalho**: "Visualizar Produto · <código>", pílula Ativo/Inativo, botões "Voltar" e "Editar" (`./editProduto?id=`).
- **Conteúdo (campos somente leitura)**: 1. Identificação (Código Interno, Código de Barras, Nome, Categoria — "Sem categoria" se não achada, Marca); 2. Especificações (Volume/Embalagem, Aplicação, Localização no Estoque); 3. Descrição; 4. Estoque e Financeiro (Estoque Atual e Estoque Mínimo como inteiros formatados, Preço de Venda e, **só para ADMIN**, Preço de Custo e Margem de Lucro = `(venda − custo) / custo × 100`, com 2 casas, 0 se custo for 0).

### Excluir/Arquivar Produto

- **Rota/URL**: `./deleteProduto` (`ProdutoController::arquivar`), somente POST com `id_produto`; qualquer outro método volta a `./listProduto`. Acesso: ADMIN. O produto é arquivado (`ativo = 0`), não removido. O arquivo `Produtos/deleteProdutos.php` existe vazio, sem conteúdo.

---

## Categoria

Tabela `categorias_produtos` (`id_categoria`, `nome`). Cargo: a listagem é aberta a todos; criar, editar e excluir exigem ADMIN. O controller carrega as views pelo caminho `categoria/...` (minúsculo) sobre a pasta `Categoria`.

Regra de exclusão: **não é possível excluir uma categoria com produtos vinculados** (conta qualquer produto em `produtos.categorias_produtos_id`, ativo ou arquivado); a exclusão é bloqueada no model (`Categoria::delete`) e a lista volta com `?erro=emUso` mostrando: "Não é possível excluir: ainda há produtos cadastrados nesta categoria. Mova-os para outra categoria antes de excluir."

### Categorias de Produtos (lista)

- **Rota/URL**: `./listCategoria` (`CategoriaController::list`).
- **Acesso**: todos os cargos.
- **Finalidade**: gerenciar as categorias usadas no cadastro de produtos.
- **Botões do cabeçalho**: "Voltar" (`./listProduto`) e "Nova Categoria" (`./saveCategoria`).
- **Alerta de erro** (categoria em uso) quando `erro=emUso`.
- **Busca**: campo "Buscar por nome..." filtra no navegador (JavaScript, sem recarregar) pelo nome em minúsculas (`data-nome`), mostrando o contador "N resultado(s)".
- **Tabela**: colunas Categoria (ícone, nome e "ID #n") e Ações. Ordenada por nome. Linha vazia: "Nenhuma categoria encontrada."
- **Ações por linha**: Editar (link `./editCategoria&id=<id>`; observação: o link usa `&` em vez de `?`) e, só para ADMIN, Excluir (POST para `./deleteCategoria` com campo `id`, `confirm('Deseja realmente excluir esta categoria?')`).
- **Paginação (client-side)**: 8 itens por página (`POR_PAGINA = 8`); rodapé "Exibindo X–Y de Z" e botões ← / números / → com reticências (mostra primeira, última e vizinhas da página atual); some quando não há resultados.

### Nova Categoria

- **Rota/URL**: `./saveCategoria` (`CategoriaController::save`), GET abre, POST grava.
- **Acesso**: somente ADMIN.
- **Finalidade**: cadastrar categoria, em **modal** (fecha pelo "X", "Cancelar" ou clique fora; volta a `./listCategoria`).
- **Formulário** (`POST ./saveCategoria`, id `formCriarCategoria`):

| Campo | Nome | Tipo | Obrigatório | Observações |
|---|---|---|---|---|
| Nome da Categoria | `nome` | text | Sim (`required` + `Validator::required`) | Placeholder "Ex.: Óleos, Filtros, Pneus..."; gravado com `trim` |

- **Erros**: validação reexibe o modal com a mensagem; falha de gravação mostra "Erro ao cadastrar categoria.". Sucesso redireciona para `./listCategoria`.
- **Rodapé**: "Criado em" (data de hoje), usuário e cargo. **Botões**: "Limpar" (reseta o formulário), "Cancelar", "Salvar".

### Editar Categoria

- **Rota/URL**: `./editCategoria?id=<id_categoria>` (`CategoriaController::edit`), POST em `./editCategoria`.
- **Acesso**: somente ADMIN.
- **Comportamento**: sem `id` ou categoria inexistente volta a `./listCategoria`. POST exige `id_categoria` (oculto) e `nome`; erros reexibem a tela ("Erro ao atualizar categoria." em falha de gravação).
- **Cabeçalho**: "Editar Categoria · #<id>", pílula "Ativa" e botão "Voltar".
- **Formulário** (`POST ./editCategoria`, id `formEditCategoria`):

| Campo | Nome | Tipo | Obrigatório | Observações |
|---|---|---|---|---|
| (oculto) | `id_categoria` | hidden | — | |
| Nome da Categoria | `nome` | text | Sim | Placeholder "Ex.: Óleos, Pneus, Filtros..." |
| Identificação | — | texto informativo "Categoria #id" | — | Somente exibição |
| Status | — | badge "Ativa" — "Disponível para uso nos produtos" | — | Somente exibição fixa (a tabela não possui status) |

- **Cartão "Informações do Sistema"**: "Criado em" e "Última atualização", ambos exibem a data de hoje (não vêm do banco).
- **Rodapé de ações**: "Excluir Categoria" (ADMIN; formulário POST para `./deleteCategoria` com `id`, com confirmação; o HTML coloca esse formulário dentro do formulário principal, o que é inválido em HTML), "Cancelar" e "Salvar Alterações".

### Excluir Categoria

- **Rota/URL**: `./deleteCategoria` (`CategoriaController::delete`), somente POST com `id`; GET volta à lista (por proteção CSRF/pré-carregamento). Acesso: ADMIN. Com produtos vinculados redireciona para `./listCategoria?erro=emUso`.

---

## Movimentação de Estoque

Tabela `movimentacoes_estoque` (`id_movimentacao`, `produto_id`, `tipo`, `quantidade` DECIMAL(10,3), `custo_unitario`, `criado_em`).

Regras de negócio:

- **Tipos**: `ENTRADA` (soma ao estoque), `SAIDA` (subtrai) e `AJUSTE` (gerado automaticamente ao editar o estoque na tela do produto; quantidade com sinal; só aparece na lista, não pode ser criado nas telas de cadastro/edição, que aceitam apenas ENTRADA e SAIDA).
- Cada gravação (`save`, `update`, `delete`) roda em **transação** e **trava a linha do produto** (`SELECT ... FOR UPDATE`) antes de validar, evitando saídas simultâneas que deixem o estoque negativo.
- **Saída não pode exceder o estoque atual** (mensagem: "Saída de X unidade(s) maior que o estoque atual (Y)."). Na edição o estoque-base desconsidera o efeito da movimentação antiga.
- **Custo unitário**: copiado do `preco_custo` atual do produto no momento da gravação/edição (não é digitado).
- **Quantidade** aceita fração (3 casas) e usa valor absoluto; deve ser > 0.
- **Editar** reverte o efeito antigo no estoque e aplica o novo (inclusive se mudou o produto). **Excluir** reverte o efeito no estoque antes de apagar.
- Apenas produtos **ativos** aparecem no seletor de produto.

### Movimentações (lista)

- **Rota/URL**: `./listMovimentacaoEstoque` (`MovimentacaoEstoqueController::list`).
- **Acesso**: todos os cargos.
- **Finalidade**: histórico de movimentações de estoque. Subtítulo: "Gerencie as movimentações de estoque do sistema."
- **Botão do cabeçalho**: "Nova Movimentação" (`./saveMovimentacaoEstoque`; exige ADMIN ou TECNICO).
- **Filtros** (formulário GET): "Buscar por produto..." (`busca`, `LIKE` no nome do produto); "Tipo" (`tipo`: Todos, Entrada, Saída, Ajuste; envia ao mudar); botão "Filtrar"; contador "N resultado(s)".
- **Tabela**: colunas Produto (ícone colorido por tipo — verde Entrada, azul Ajuste, amarelo Saída; nome do produto e `#id`), Tipo (badge Entrada verde / Ajuste azul / Saída amarelo), Quantidade (até 3 casas sem zeros à direita; Ajuste positivo exibe prefixo `+`), Custo Unitário (**coluna só para ADMIN**, `R$` ou `-`), Criado em, Ações. Ordenação por `id_movimentacao` decrescente. Linha vazia: "Nenhuma movimentação encontrada."
- **Ações por linha**: Editar (`./editMovimentacaoEstoque&id=<id>`) e, só ADMIN, Excluir (POST `./deleteMovimentacaoEstoque`, campo `id`, com confirmação "Deseja excluir esta movimentação? O estoque do produto será revertido.").
- **Paginação (servidor)**: 8 por página (`itensPorPagina = 8`), parâmetro `pagina`; rodapé "Mostrando X de Y resultados" e botões ‹ números ›, mantendo os filtros na URL. Só aparece com mais de uma página.

### Nova Movimentação

- **Rota/URL**: `./saveMovimentacaoEstoque` (`MovimentacaoEstoqueController::save`), GET abre, POST grava.
- **Acesso**: ADMIN e TECNICO (o ATENDENTE é barrado).
- **Finalidade**: registrar entrada ou saída de estoque, em **modal** (`modal--md`; fecha pelo "X", "Cancelar" ou clique fora).
- **Validação no servidor**: `produto_id` e `tipo` obrigatórios, `tipo` em `['ENTRADA','SAIDA']`, `quantidade` obrigatória e numérica; erros reexibem o modal com os dados antigos. Falhas de gravação mostram "Erro ao registrar movimentação: <detalhe>". Sucesso redireciona para `./listMovimentacaoEstoque`.
- **Formulário** (`POST ./saveMovimentacaoEstoque`, id `formNovaMovimentacao`):

| Campo | Nome | Tipo | Obrigatório | Observações |
|---|---|---|---|---|
| Produto | `produto_id` | select | Sim | "Selecione um produto..." + produtos ativos como "Nome (Estoque: n)"; cada opção traz `data-estoque` |
| Tipo | `tipo` | botões de alternância (radio) | Sim | Entrada (padrão) ou Saída |
| Quantidade | `quantidade` | number, `min="0.001"`, `step="0.001"` | Sim | Placeholder "0" |

- **Indicador**: ao escolher o produto aparece o selo "Estoque atual: N unidade(s)".
- **JS**: atualiza o selo ao trocar o produto; no submit, se tipo = SAÍDA e quantidade > estoque do produto selecionado, cancela o envio com `alert` ("Não é possível registrar uma saída de X unidade(s): o estoque atual é de apenas Y."), validação duplicada no servidor; realça o botão de tipo ativo; fechar clicando fora volta à lista.
- **Rodapé**: "Registrado em" (hoje), usuário e cargo. **Botões**: "Limpar" (reseta e atualiza o selo), "Cancelar", "Registrar".

### Editar Movimentação

- **Rota/URL**: `./editMovimentacaoEstoque?id=<id_movimentacao>` (`MovimentacaoEstoqueController::edit`), POST em `./editMovimentacaoEstoque`.
- **Acesso**: ADMIN e TECNICO.
- **Comportamento**: sem `id` ou movimentação inexistente volta à lista. POST exige `id_movimentacao` (oculto) e as mesmas validações do cadastro. Erros mostram caixa vermelha no topo ("Erro ao atualizar movimentação: <detalhe>").
- **Cabeçalho**: "Editar Movimentação", botão "Voltar".
- **Formulário** (`POST ./editMovimentacaoEstoque`), três cartões:

| Cartão | Campo | Nome | Tipo | Observações |
|---|---|---|---|---|
| 1. Produto | Produto | `produto_id` | select (produtos ativos) | Obrigatório; selo "Estoque atual: N unidade(s)" |
| 2. Movimentação | Tipo | `tipo` | botões Entrada / Saída | Obrigatório |
| 2. Movimentação | Quantidade | `quantidade` | number, `min="0.001"`, `step="0.001"` | Obrigatório |
| 2. Movimentação | Custo unitário registrado | — | text desabilitado, só ADMIN | "Atualizado automaticamente com o preço de custo atual do produto." |
| 3. Resumo do Cadastro | ID da Movimentação, Registrado em, Última Atualização (hoje), Usuário (nome e cargo) | — | somente exibição | |

- **Rodapé de ações**: "Excluir Movimentação" (ADMIN; formulário POST para `./deleteMovimentacaoEstoque`, confirmação "Deseja excluir esta movimentação?"; o formulário fica dentro do formulário principal), "Cancelar" e "Salvar Alterações".
- **JS**: selo de estoque e realce do tipo. Diferentemente do cadastro, a tela de edição **não** tem a trava de saída maior que o estoque no navegador (a validação fica só no servidor).
- Movimentações do tipo `AJUSTE` abertas nesta tela não têm botão de rádio correspondente (somente Entrada/Saída).

### Excluir Movimentação

- **Rota/URL**: `./deleteMovimentacaoEstoque` (`MovimentacaoEstoqueController::delete`), somente POST com `id`; GET volta à lista. Acesso: ADMIN. Reverte o efeito no estoque do produto e apaga a linha, em transação.

---

## Relatórios

### Relatórios e Insights

- **Rota/URL**: `./listRel` (`RelatorioController::listRelatorio`; modelo `Relatorio::getResumo()`). Item "Relatórios" do menu, visível para ADMIN.
- **Acesso**: somente ADMIN.
- **Finalidade**: visão gerencial de desempenho financeiro, operacional e de estoque. Subtítulo: "Acompanhe o desempenho financeiro, operacional e de estoque."
- **Botões do cabeçalho**: "Últimos 30 dias" (botão visual, sem ação nem filtro implementado) e "Imprimir / Salvar PDF" (`window.print()`, usa a impressão do navegador; não há PDF gerado no servidor).
- **Cards (4)**:
  1. **Faturamento Total**: soma de `valor_total_os` das OS `CONCLUIDA` com `data_conclusao` no **mês corrente**; abaixo, variação percentual em relação ao mês anterior (`(atual − anterior) / anterior × 100`, 1 casa; se o anterior for 0 mostra 100% quando há faturamento, senão 0%); seta e cor negativas quando < 0, sinal `+` quando > 0.
  2. **OS Concluídas**: quantidade de OS com status `CONCLUIDA` (todas, sem filtro de data) — "Serviços finalizados".
  3. **Ticket Médio**: média de `valor_total_os` das OS concluídas (todas) — "Por ordem concluída".
  4. **Produtos em Baixa**: contagem de produtos ativos com `estoque_atual <= estoque_minimo` (inclui esgotados, diferente do card da tela de Produtos) — "Atenção ao estoque".
- **Gráfico "Receita Mensal"** (Chart.js, barras, canvas `barChart`, gradiente azul): receita (OS concluídas por `data_conclusao`) de cada um dos **últimos 7 dias**, com rótulos fixos `Seg, Ter, Qua, Qui, Sex, Sáb, Dom` (observação: o código rotula sempre nessa ordem, independentemente do dia da semana atual, e o título e o seletor "Mensal/Semanal" são apenas visuais, sem efeito). Tooltip em `R$` (valores >= 1000 em `R$ x.xk`). Legenda "Receita (R$)". Comentário no código: a série "Despesas" foi removida porque o sistema não controla despesas.
- **Cartão "OS por Status (Mês)"**: barras horizontais proporcionais ao maior valor, para Concluídas, Em Manutenção (`EM_ANDAMENTO`), Aguardando Peça (`AGUARDANDO_PECA`), Diagnóstico (`DIAGNOSTICO`) e Arquivadas (`CANCELADA`). Observação: apesar do "(Mês)" no título, a consulta conta todas as OS por status, sem filtro de mês.
- **Cartão "Clientes Atendidos"**: top 5 clientes por número de OS (nome, veículo — marca/modelo/ano — e "N OS", com barra proporcional e avatar de iniciais) e link "Ver clientes" (`./listClientes`). Vazio: "Nenhum cliente encontrado."
- **Dados também calculados pelo model e não exibidos nesta view**: `graficoOS` (OS abertas por dia nos últimos 7 dias) e `ordensConcluidas` (últimas 8 OS concluídas com número, cliente, veículo, data e valor).
- **Filtros/busca/paginação**: não há.

---

