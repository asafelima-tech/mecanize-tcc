# Melhorias — 20/08/2026

Documentado no mesmo espírito de `comentarios.md` e das `MELHORIAS_*.md`
anteriores (a mais recente sendo `MELHORIAS_2026-08-18.md`, que cobre a
Área do Técnico, o sistema de impressão/PDF e o plano de segurança da
sessão passada). Hoje foram duas frentes, cada uma pedida separadamente:
deixar o sistema inteiro responsivo pra celular, e construir a gestão
completa de remuneração de técnicos/funcionários.

## 1. Sistema responsivo pra celular

Pedido em duas etapas: primeiro polir a Área do Técnico (já construída
mobile-first na sessão passada, mas sem quase nenhum `@media` próprio),
depois dar uma geral no sistema inteiro — "se o ADM quiser acessar no
celular ele deve ter uma boa experiência".

Antes de mexer em qualquer CSS, um levantamento (leitura direta de
`global.css`/`sidebar.css`/`kanban.js`/`assinatura.js`, mais um agente de
exploração pelas listagens/formulários/modais) mostrou que a base já era
bem mais responsiva do que parecia: `.table-wrap` já rolava na horizontal,
`.modal` já encolhia (`width:100%` com teto em `max-width`), `.stat-grid`
já era fluido, os gráficos Chart.js já eram `responsive:true`. Os problemas
reais eram pontuais:

### 1.1 — Área do Técnico polida

`app/views/Tecnico/painelTecnico.php` e `osTecnico.php` ganharam `@media`
próprios (768px = mesmo limiar da gaveta da sidebar; 480px pra telefone
estreito): cards de estatística empilhando em telas pequenas, botões de
status e o formulário de "Adicionar peça" em coluna única no celular,
barra fixa de "Finalizar serviço" com `env(safe-area-inset-bottom)` pra não
ficar colada na barra de gestos do iPhone, modal de finalização com padding
reduzido.

### 1.2 — Sidebar escondia quase tudo no celular, de propósito

`public/assets/css/sidebar.css:857-864` fazia `.sidebar__nav > li {
display:none }` e só reexibia os itens com a classe `mob-nav` (Início,
Relatórios, Documentos) — decisão de uma sessão anterior, "no celular só
dá pra consultar". Removida: agora a gaveta mostra tudo que o PHP já
renderiza pro papel do usuário (a checagem por papel continua no
`header.php`, isso aqui só liberava a visibilidade). Confirmado por teste
HTTP com os três papéis: ADMIN vê os 14 itens, ATENDENTE vê 10 (sem os
administrativos), TÉCNICO só o próprio painel.

### 1.3 — Grids de formulário sem classe pra `@media` prender

Cerca de 30 ocorrências de `style="display:grid;grid-template-columns:1fr
1fr 1fr"` direto no HTML, em 9 arquivos (`saveOrdemServico.php`,
`saveProduto.php`, `saveCliente.php`, `saveVeiculo.php`, `viewCliente.php`,
`viewOrdemServico.php`, `editOrdemServico.php`, `Documento/saveDocumento.php`,
`Orcamento/saveOrcamento.php`) — sem classe nenhuma, o `@media` não tinha
onde prender. Viraram `.form-row-2/3/4/5` e `.form-row-1-3` (proporção
assimétrica, tipo "rótulo estreito + campo largo") em `global.css`, cada
uma colapsando pra 1 coluna abaixo de 720px — mesmo padrão que o arquivo já
usa em outros lugares (classe + `@media` logo abaixo). Um caso fora do
padrão (`saveProduto.php`, 5 colunas com proporção `1fr 1fr 1.5fr 1fr 1fr`)
ficou como classe local (`produto-row-5`) por ser proporção única daquela
tela.

### 1.4 — Outros primitivos compartilhados

- `.tabs` não tinha `overflow-x`/quebra — uma tela com 4-5 abas (a
  listagem de OS tem 5) estourava a largura no celular sem rolar nem
  quebrar linha. Ganhou `overflow-x:auto`.
- `.page-header__title` e `.modal-overlay`/`.modal__header/body/footer`
  ganharam ajuste de tamanho/padding abaixo de 480px.
- Duas telas (`Documento/listDocumentos.php`, `Orcamento/listOrcamentos.php`)
  travavam `.stat-grid` num número fixo de colunas via `style` inline,
  anulando o comportamento fluido que a classe já tinha — removido.

### 1.5 — Assinatura em canvas distorcia em tela estreita

`assinatura.js` fixava a resolução do canvas em `width="380" height="130"`
no HTML, mas o CSS forçava `width:100%` — em qualquer modal mais estreito
que 380px (ou seja, qualquer celular), o traço saía esticado. Ganhou um
método `redimensionar()` que ajusta `canvas.width`/`height` pro tamanho
real na tela (× `devicePixelRatio`, pra ficar nítido em retina), chamado ao
abrir cada modal que usa assinatura — nunca no carregamento da página,
porque o modal começa `display:none` e `getBoundingClientRect()` voltaria
zero. Afeta os 3 lugares que usam assinatura: concluir OS
(`listOrdemServico.php`), aprovar orçamento (`listOrcamentos.php`) e
finalizar serviço (`Tecnico/osTecnico.php`).

**Autocorreção durante a implementação**: a primeira versão chamava
`ctx.scale(dpr, dpr)` além de redimensionar o canvas — só que `pos()` (a
função que converte a posição do dedo/mouse em coordenada do canvas) já
fazia essa mesma conversão via `canvas.width / rect.width`. Aplicar a
escala nos dois lugares dobrava o fator e desenharia o traço fora do
lugar. Corrigido removendo o `ctx.scale()` e deixando só `pos()` cuidar da
conversão; a espessura do traço (que dependia do `ctx.scale` pra continuar
proporcional) passou a ser multiplicada manualmente por `dprAtual`.

### 1.6 — Kanban não arrastava no touch

`kanban.js` usava só Drag-and-Drop HTML5 nativo
(`dragstart`/`dragover`/`drop`), que não dispara em touch — quem usasse
celular/tablet simplesmente não conseguia mover a OS entre colunas.
Reescrito com Pointer Events (`pointerdown`/`pointermove`/`pointerup` +
`setPointerCapture`), que cobre mouse e touch com o mesmo código. Detecta
arraste por deslocamento mínimo (8px) a partir do toque, pra um toque
simples no card (ou no link "Detalhes →" que já existe dentro dele)
continuar funcionando como toque, não como arraste. `touch-action:none`
nos cards evita que o navegador tente rolar a página ao tocar num card —
o quadro continua rolável na horizontal tocando fora dos cards.

## 2. Remuneração de técnicos/funcionários

Pedido grande, em 6 partes: cadastro de regra de remuneração por técnico,
integração automática com a OS, cálculo travado na finalização, uma área
de Financeiro/Comissões com relatório por período, fechamento de
pagamento, e a regra de que mudar a remuneração de um técnico não pode
recalcular OS antigas.

Antes de implementar, o levantamento (leitura de `OrdemServico.php`,
`UserController.php`, `saveUsuario.php`/`editUsuario.php`, o JS de
comissão em `saveOrdemServico.php`) mostrou que já existia uma comissão
manual, mas rudimentar: um único "Mecânico responsável" por OS, percentual
digitado à mão a cada OS, base ("serviços/peças/ambos") escolhida por
botão — e a % só sobrevivia embutida como texto dentro de
`ordem_servico_mao_obra.descricao` ("Mão de obra (30%)"), recuperada por
regex. A base nem chegava a ser salva. E editar uma OS calculava a
comissão sempre sobre serviços, ignorando a base escolhida na criação — um
bug pré-existente.

Três decisões confirmadas com o usuário antes de começar: (1) cálculo
automático pela regra do técnico, com ajuste manual pontual permitido
depois; (2) incluir também o tipo "percentual por categoria de serviço"
nesta entrega, não deixar pra depois; (3) suportar múltiplos técnicos por
OS desde já, não só um.

### 2.1 — Sete tipos de remuneração

`PERCENTUAL_TOTAL`, `PERCENTUAL_SERVICOS` (cobre tanto "mão de obra"
quanto "serviços realizados" do pedido — o sistema só tem um campo de
valor de serviço na OS, não uma "mão de obra" separada; inventar uma
segunda grandeza sem dado real por trás seria pior que unificar),
`PERCENTUAL_PECAS`, `PERCENTUAL_TIPO_SERVICO` (percentual próprio por
categoria, casado por texto contra `ordem_servico_servicos.categoria` —
não existe catálogo formal de serviços no sistema, então é correspondência
de texto, não chave estrangeira), `VALOR_FIXO_SERVICO` (multiplicado pela
quantidade de itens marcados `executado=1`, reaproveitando a coluna que a
Área do Técnico já preenche), `SALARIO_FIXO` (R$0 por OS, só entra no
fechamento mensal) e `SALARIO_FIXO_MAIS_COMISSAO` (os dois combinados).

### 2.2 — Banco de dados

`database/add_remuneracao_funcionarios.php` (idempotente, rodado nas duas
bases — `mecanize_db` e `mecanize_db_test`, lição da sessão passada):
tabela nova `tecnico_remuneracao_regra` (uma regra ATIVA por técnico —
`UNIQUE` em `tecnico_id`; editar sobrescreve, o histórico não mora aqui) +
`tecnico_remuneracao_regra_categoria` (sub-tabela só do tipo por
categoria); `ordem_servico_mao_obra` ganhou as colunas que faltavam pra um
snapshot de verdade (`regra_tipo`, `regra_percentual`, `base_valor`,
`status_pagamento`, `ajustado_manualmente`, `observacao_ajuste`,
`calculado_em`, `fechamento_pagamento_id`) — substituindo o regex em
`descricao`; tabela nova `funcionario_fechamento_pagamento` (um registro
por lote de pagamento).

Decisão de arquitetura: estender `ordem_servico_mao_obra` em vez de criar
uma tabela paralela pra comissão. Essa tabela já era o vínculo técnico↔OS
(usada por `OrdemServico::pertenceAoTecnico()` da Área do Técnico) — criar
uma segunda tabela só pra comissão exigiria manter as duas em lockstep
pelo mesmo par (OS, técnico) sem ganho nenhum.

### 2.3 — O cálculo mora na finalização, não na criação/edição

Esse foi o desenho central, e resolve praticamente todos os requisitos de
uma vez só:

- Criar/editar a OS só **atribui** técnicos — grava a linha em
  `ordem_servico_mao_obra` sem valor nenhum ainda
  (`OrdemServico::salvarTecnicosResponsaveis()`). O formulário mostra uma
  prévia calculada em JavaScript, só de referência pra quem está montando
  a OS — nunca é isso que é gravado.
- `OrdemServicoController::concluir()` (o mesmo ponto que já lança o
  caixa e manda o e-mail) chama `OrdemServico::calcularComissoesNaConclusao()`:
  pra cada técnico atribuído ainda sem `calculado_em`, busca a regra
  ATUAL dele e calcula em cima dos valores FINAIS da OS.
- **Duplicidade**: a guarda é `calculado_em IS NULL` — reprocessar uma OS
  já concluída não recalcula nada.
- **Histórico preservado mesmo com regra alterada depois**: o resultado é
  gravado (não uma referência à regra). Testado explicitamente — mudei um
  técnico de 20% pra 50% depois de uma OS concluída, reprocessei, e a OS
  antiga manteve os 20% originais.
- **Editar uma OS já concluída**: `OrdemServico::update()` passa a apagar
  só linhas de técnico com `calculado_em IS NULL` — uma vez calculada
  (ainda mais se já paga), a linha fica intocada mesmo que a OS seja
  reeditada. Mesmo padrão que `OrdemServicoPagamento::salvarPlano()` já
  usa pras parcelas ("apaga só a parcela ainda não paga").
- **Ajuste manual pontual**: fica na tela de Financeiro, não no formulário
  da OS — só em linhas ainda não pagas.

### 2.4 — Múltiplos técnicos por OS

O `<select>` único de "Mecânico responsável" virou uma lista de checkboxes
(`saveOrdemServico.php`, `editOrdemServico.php`) — cada técnico marcado
recebe sua própria linha e sua própria comissão, calculada pela própria
regra. `viewOrdemServico.php` passou a listar todos os técnicos da OS e a
comissão de cada um, em vez de só um. `OrdemServico::getTecnicosDaOS()`
substitui `getMaoObraByOS()` (que fazia `LIMIT 1`) nos lugares que
precisam ver todos.

### 2.5 — Cadastro de funcionário

`app/views/Usuarios/saveUsuario.php` e `editUsuario.php` ganharam uma
seção "Regra de Remuneração", escondida/mostrada por JavaScript a partir
do tipo de usuário selecionado (a primeira lógica condicional por tipo que
essas telas ganharam) — os campos mudam conforme o tipo de regra
escolhido (percentual único, lista de categoria+percentual, valor fixo, ou
a combinação). Extraído pra um parcial compartilhado
(`_camposRegraRemuneracao.php`) pra não duplicar a mesma UI nas duas
telas.

### 2.6 — Financeiro/Comissões dos Funcionários

Tela nova (`/comissoesFuncionarios`, restrita a ADMIN, com item próprio na
sidebar): seletor de técnico + mês/ano, cards de resumo (OS realizadas,
total do período, já pago, pendente), tabela por OS com as colunas do
pedido (valor da OS, base da comissão, regra aplicada, comissão, status),
histórico de pagamentos, ação de pagar (fecha o período, soma tudo que
está pendente — inclusive salário fixo, se for o caso — e carimba as
linhas incluídas pra não entrarem de novo num fechamento futuro) e ajuste
manual pontual.

### 2.7 — Bug real encontrado durante o teste (não introduzido hoje)

O teste de ponta a ponta (criar OS com 2 técnicos → concluir → editar →
verificar preservação) revelou que `OrdemServico::update()` gravava item
do tipo "serviço" na tabela errada: `ordem_servico_mao_obra`, como linha
avulsa sem categoria/percentual, em vez de `ordem_servico_servicos` — ao
contrário de `create()`, que sempre usou a tabela certa. O formulário de
edição já enviava `categoria`/`custom`/`pct` por item (idêntico ao de
criação); só o `update()` jogava esses dados fora. Isso quebrava
silenciosamente dois pedaços já construídos: a confirmação de "executado"
da Área do Técnico e o cálculo de comissão por categoria/valor-fixo — para
qualquer OS que passasse por uma edição, os serviços simplesmente
desapareciam de `ordem_servico_servicos`. Corrigido trocando a chamada
pra usar o mesmo método que `create()` já usa
(`salvarItemServico()`), e removido o método antigo, que ficou sem
nenhum outro chamador.

## Testes

- **Cálculo de comissão** (`TecnicoRemuneracao::calcularComissao()`): 12
  verificações cobrindo os 7 tipos, incluindo os dois exemplos numéricos
  do pedido (Jefferson, 20% sobre total de R$2.000 = R$400; João, 30%
  sobre R$600 de serviços, ignorando R$1.400 de peças = R$180).
- **Ciclo completo** (regra → OS com 2 técnicos → finalização → edição
  pós-conclusão → pagamento → bloqueio de exclusão → ajuste manual): 34
  verificações, todas passando após a correção do bug em `update()`.
- Suíte `vendor/bin/phpunit` completa (75 testes, 180 assertions) rodada
  várias vezes ao longo da sessão, sempre verde.
- HTTP real, logado como cada papel (ADMIN/ATENDENTE/TÉCNICO): sidebar
  completa por papel, cadastro de técnico com regra (POST real, conferido
  direto no banco), tela de edição pré-preenchendo a regra salva, tela de
  Financeiro listando o técnico recém-criado, `viewOrdemServico`/
  `editOrdemServico` numa OS real sem nenhum técnico atribuído (estado
  vazio).
- Todo usuário/caixa/OS de teste criado durante a sessão foi removido ao
  final — banco de produção (`mecanize_db`) conferido limpo.

## O que fica pendente

- **Sem navegador**, o teste de arrastar no Kanban por toque e o preview
  de comissão em JavaScript (que espelha a fórmula do servidor) só foram
  conferidos por leitura de código e ausência de erro — não visualmente.
  Vale abrir num celular de verdade.
- **Reconhecimento de comissão em OS parcelada**: hoje a comissão nasce
  inteira na finalização, igual pra à vista e pra parcelado — não
  espelha como o caixa trata parcela (que só lança quando cada parcela é
  paga). O pedido não mencionava parcelamento; ficou registrado como
  possível refinamento futuro.
- **Catálogo formal de serviços**: a regra por categoria casa por texto
  (sem diferenciar maiúsculas/acentuação) contra o campo livre `categoria`
  de cada item da OS — não existe uma tabela de categorias fixas no
  sistema. Se um dia existir, essa parte troca de fonte sem mudar o resto.
- **Prorrateamento de salário fixo** (por dias trabalhados, férias,
  afastamento) não foi implementado — o fechamento lança o valor
  configurado inteiro a cada período.
- Nenhuma dessas mudanças foi commitada — tudo segue como alteração local
  na branch `melhorias-caixa-agenda`.
