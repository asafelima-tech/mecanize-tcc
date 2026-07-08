# Melhorias — 08/07/2026

Rodada de segurança + features pedidas pelo dono do sistema. Documentado no mesmo
espírito de `comentarios.md`.

## 0. Rede de segurança

- Git não estava instalado na máquina — instalado via `winget install Git.Git`.
- `git init` + commit inicial com todo o estado atual do projeto, antes de qualquer
  mudança abaixo. A partir de agora dá pra reverter qualquer coisa com segurança.
- `.gitignore` criado (`/vendor/`, `/database/backups/`, `composer.phar`).

## 1. Autorização por papel nos controllers (correção de segurança)

**Problema encontrado:** só as *views* escondiam botões/menus de quem não é ADMIN.
Os *controllers* correspondentes não validavam nada — um TECNICO ou ATENDENTE que
digitasse a URL direto (`./listUsuarios`, `./exportUsuarios`, `./listDocumentos`,
`./listRel` etc.) conseguia executar normalmente.

- `Controller::requireRole(array $tiposPermitidos)` — novo método em
  `app/core/Controller.php`. Se o papel da sessão não estiver na lista, responde
  403 com a nova view `app/views/Site/403.php` (mesmo padrão do 404 existente).
- Aplicado `requireRole(['ADMIN'])` em:
  - `UserController`: `list`, `exportar`, `visualizar`, `save`, `edit`, `delete`,
    `toggleStatus`, `novos30dList`, `perfilJson`. (Ficaram de fora, de propósito:
    `login`, `logout`, `perfil`, `updateSenha`, `recuperarSenha`, `redefinirSenha`
    — são autoatendimento do próprio usuário, não administração de terceiros.)
  - `DocumentoController`: `list`, `save`, `finalizar`.
  - `RelatorioController`: `listRelatorio`.
- `CaixaController::list`: a regra "ATENDENTE só vê as próprias sessões de caixa"
  estava implementada em `app/views/Caixa/listCaixa.php` (a view filtrava o array
  depois de já ter todos os dados). Movida para o controller — mesmo comportamento,
  mas agora garantida no lugar certo, não dependente da view.

## 2. Camada de validação reutilizável

- `app/core/Validator.php` — classe `Validator` sem dependências externas.
  Encadeável: `required()`, `email()`, `numeric()`, `min()`, `max()`, `in()`,
  `matches()`, `custom()` (pra reaproveitar regras que já existiam nos models,
  tipo `User::emailExiste` e `Cliente::emailExisteAtivo`).
- Aplicado como padrão de referência em `UserController::save/edit` e
  `ClienteController::save/edit` (que antes só validavam duplicidade de e-mail
  na mão, sem checar nome/tipo/formato de e-mail obrigatórios).
- **Não foi migrado** para os outros 12 models/controllers — risco de regressão
  alto sem testes automatizados. Fica fácil repetir o mesmo padrão quando for
  mexer nos outros formulários.

## 3. Notificações ao cliente

- **E-mail automático (via `Mailer`, já usado na recuperação de senha):**
  - `OrdemServicoController::concluir()` → envia "seu veículo está pronto" pro
    e-mail do cliente (se cadastrado) quando a OS é marcada como concluída.
  - `OrcamentoController::aprovarOrcamento()` → envia confirmação de aprovação
    com número e valor do orçamento.
  - Ambos em `try/catch` — falha no envio não pode travar o fluxo principal
    (mesmo padrão já usado no resto do sistema).
- **WhatsApp — link assistido, não é API automática:** não existe uma "API do
  WhatsApp" que dê pra simplesmente ligar; a oficial (Meta Cloud API) exige conta
  comercial verificada e aprovação prévia, e alternativas (Twilio/Z-API) exigem
  conta paga própria. O que foi implementado: `app/core/WhatsApp.php` gera um
  link `wa.me` com a mensagem pronta; um botão "Enviar WhatsApp" aparece em
  `viewOrdemServico.php` (OS concluída) e `viewOrcamento.php` (orçamento
  aprovado), sempre que o cliente tem telefone cadastrado. O atendente clica e
  confirma o envio pelo WhatsApp Web/app dele — não é automático em segundo
  plano. Se no futuro houver uma conta de API de verdade, dá pra trocar esse
  botão por envio automático sem mexer no resto do fluxo.
  - Para isso, `OrdemServico::getById` e `Orcamento::getById` passaram a trazer
    também `telefone_cliente` (coluna nova no SELECT, sem mudar estrutura).

## 4. Exportação em PDF (Orçamento e Ordem de Serviço)

- Composer configurado no projeto pela primeira vez (`composer.json`/`composer.lock`,
  pasta `vendor/` — ignorada no git). Instalado `dompdf/dompdf`.
- `app/core/Pdf.php` — wrapper simples: `Pdf::baixar($html, $nomeArquivo)`.
- Templates dedicados para o PDF (não reaproveitam a tela de impressão existente
  porque o Dompdf não suporta variáveis CSS `var(--cor)` nem o JS de "modo
  edição" daquela tela): `app/views/Orcamento/pdfOrcamento.php` e
  `app/views/Ordens_Servico/pdfOrdemServico.php`. Não mostram comissão/lucro do
  técnico — são documentos para entregar ao cliente, não relatório interno.
- Novas rotas: `exportOrcamentoPdf` e `exportOrdemServicoPdf`.
- Botão "Baixar PDF" adicionado em `viewOrcamento.php`, `viewOrdemServico.php` e
  na listagem de orçamentos (`listOrcamentos.php`, ao lado do botão Imprimir).
  **Não foi adicionado** em `saveOrcamento.php`/`saveOrdemServico.php` — lá o
  "Imprimir" é do formulário ainda não salvo (`window.print()` da tela ao vivo),
  sem ID de banco pra gerar PDF ainda.

## 5. Backup automático do banco

- `database/backup.ps1` — roda `mysqldump` (`C:\xampp\mysql\bin\mysqldump.exe`)
  para `mecanize_db`, salva em `database/backups/mecanize_AAAA-MM-DD_HHmm.sql` e
  apaga backups com mais de 30 dias. Testado manualmente com sucesso.
- Tarefa agendada criada no Agendador de Tarefas do Windows: **"Mecanize - Backup
  DB"**, todo dia às 23:30. Pra remover: `schtasks /delete /tn "Mecanize - Backup
  DB"` ou pelo próprio Agendador de Tarefas do Windows.
- `database/backups/` está no `.gitignore` — dump com dados reais de cliente não
  deve ir pro histórico do git.

## 6. Variáveis de ambiente (.env) e publicação no GitHub

Depois da rodada acima, o dono do projeto pediu pra publicar o repositório no
GitHub, porque o projeto não é só dele (tem mais gente mexendo). Antes de
enviar qualquer coisa pra fora da máquina, foi preciso resolver um problema
que já existia desde o commit inicial: `config/mail.php` tinha a senha de
app do Gmail **em texto puro, escrita direto no código**.

- **Motivo de não dar só pra "trocar o arquivo e commitar de novo":** o git
  guarda histórico. Mesmo apagando a senha do arquivo atual, ela continuaria
  gravada nos commits antigos — qualquer pessoa com acesso ao repositório
  (ou ao `git log`) conseguiria ver a senha antiga, mesmo em repositório
  **privado**.
- **O que foi feito:**
  - Criado `app/core/Env.php` — um carregador simples de arquivo `.env`
    (sem depender de biblioteca externa), com a função `env('CHAVE', padrao)`
    disponível em qualquer lugar do sistema.
  - Criado `.env` na raiz (com as credenciais reais — **este arquivo não vai
    pro git**, está no `.gitignore`) e `.env.example` (modelo sem segredos,
    esse sim commitado, pra qualquer outro colaborador saber quais variáveis
    precisa preencher no próprio `.env` dele).
  - `config/mail.php` e `app/core/Database.php` passaram a ler as
    credenciais via `env()` em vez de ter os valores escritos direto no
    código. `Database.php` e `Mailer.php` carregam o `.env` sozinhos (não
    dependem só do `public/index.php` já ter carregado antes) — assim os
    scripts de linha de comando em `database/*.php` continuam funcionando
    sem mudança nenhuma.
  - Testado antes de prosseguir: login, conexão com o banco e instanciamento
    do `Mailer` funcionando normalmente com as credenciais vindas do `.env`.
  - **Histórico do git reiniciado do zero** (o repositório só existia local,
    nunca tinha sido enviado pra lugar nenhum — reiniciar historia era seguro
    e não afetou ninguém): apaguei a pasta `.git` antiga e criei um único
    commit novo, já com a senha fora do código. Conferido com
    `git log --all -p | grep <senha-antiga>` retornando vazio, ou seja, a
    senha não existe em nenhum commit que foi ou vai ser publicado.
- **GitHub:** repositório privado criado pelo próprio dono em
  `github.com/omiguelsouza-max/mecanize`; remote `origin` conectado e
  `git push -u origin master` concluído com sucesso.
- **Importante pra quem for clonar o repositório depois:** o `.env` não vem
  junto (é local, de cada máquina). É preciso copiar `.env.example` pra
  `.env` e preencher com as credenciais reais antes de rodar o sistema.

## 7. Decisão: WhatsApp continua como link assistido (não automático)

Foi avaliado se dava pra tornar o envio de WhatsApp 100% automático:

- **WhatsApp Cloud API oficial (Meta):** é o caminho seguro pra automação de
  verdade, mas exige conta Meta Business verificada (documentos da empresa),
  um número de telefone dedicado só pra API, e aprovação de templates de
  mensagem. É um processo do dono do negócio, não dá pra terceirizar. Ficou
  fora de alcance por enquanto.
- **Bibliotecas não-oficiais (ex.: Baileys, whatsapp-web.js):** automatizam
  de verdade usando o número normal da oficina (via QR code, como o WhatsApp
  Web), sem custo. Mas violam os Termos de Uso do WhatsApp e podem fazer a
  Meta **banir o número da oficina** por automação — inaceitável pra um
  número real que atende cliente. Também exigiria um serviço Node.js
  separado rodando 24/7 no servidor só pra manter a sessão logada.
- **Decisão:** manter o link assistido (`wa.me`) que já está implementado —
  zero risco pro número da oficina, zero custo, já funciona. Se no futuro a
  oficina conseguir montar a conta Meta Business verificada, é só avisar que
  a troca pelo envio automático de verdade é rápida de implementar.

## 8. Kanban de Ordens de Serviço

Melhoria visual pedida pelo dono do sistema ("algo bonito/criativo") — um
quadro estilo Trello como alternativa à listagem em tabela de
`./listOrdemServico`, arrastando o card entre colunas pra mudar o status.

- **Rotas novas:** `kanbanOrdemServico` (exibe o quadro) e
  `moverStatusOrdemServico` (POST via `fetch()`, chamado ao soltar o card —
  responde JSON, não redireciona).
- **Model (`app/models/OrdemServico.php`):** `atualizarStatus($id, $status)` —
  método novo, dedicado, que só mexe nas colunas `status`/`data_conclusao`.
  **Importante:** não reaproveita `update()` de propósito — aquele método é o
  salvamento do formulário inteiro e *apaga e recria* itens/mão de
  obra/estoque a cada chamada; usá-lo só pra mudar o status apagaria os itens
  da OS sem recriar. `atualizarStatus()` evita esse problema. Também valida
  o status contra uma lista fechada (`STATUS_KANBAN`) antes de gravar.
  Adicionados também `listarEmAndamento()` e `listarAguardandoPeca()` (o
  `getResumo()` já tinha as outras combinadas, faltavam essas duas
  separadas pra virarem colunas próprias do quadro).
- **4 colunas:** Aberta, Em Andamento, Aguardando Peça, Concluída (mesmos
  status já usados no resto do sistema). Concluída dispara o mesmo e-mail
  "veículo pronto" já usado pelo botão de concluir OS. `CANCELADA`
  (arquivadas) fica de fora do quadro — continua só na aba "Arquivadas" da
  listagem.
- **Front-end:** `app/views/Ordens_Servico/kanbanOrdemServico.php` (view +
  CSS usando as mesmas variáveis de cor/tema do resto do sistema, inclusive
  modo escuro) e `public/assets/js/kanban.js` — drag-and-drop nativo do
  HTML5 (sem biblioteca externa), com movimento otimista (move o card na
  hora e desfaz visualmente se o servidor recusar). Botão "Ver como Kanban"
  adicionado na listagem em tabela, e "Ver como lista" no quadro, pra
  alternar entre as duas visões.
- **Testado de ponta a ponta:** GET do quadro renderizando os cards; POST de
  mudança de status validado direto no banco (status e `data_conclusao`
  mudando corretamente); status inválido rejeitado com 400.

## 9. Linha do tempo do veículo

Na tela `./viewVeiculo`, nova seção "⑤ Histórico do Veículo": uma linha do
tempo vertical juntando orçamentos e OS's daquele veículo (mais recente
primeiro), terminando no cadastro do veículo. Cada item linka pra tela de
detalhe do orçamento/OS correspondente.

- **Models:** `OrdemServico::listarPorVeiculo($veiculoId)` e
  `Orcamento::listarPorVeiculo($veiculoId)` — consultas novas, só por
  `veiculo_id`.
- **Controller:** `VeiculoController::montarTimeline()` — junta as duas
  listas num array só e ordena por data (`usort` com `strtotime`).

## 10. Stepper de status (Ordem de Serviço e Orçamento)

Nas telas `./viewOrdemServico` e `./viewOrcamento`, o badge de status simples
ganhou companhia: um stepper visual estilo rastreio de encomenda, logo abaixo
do cabeçalho.

- **OS:** 4 etapas — Aberta → Em Andamento → Aguardando Peça → Concluída
  (mesma ordem das colunas do Kanban, pra ficar consistente). `CANCELADA`
  fica fora da progressão — mostra um aviso vermelho no lugar do stepper.
- **Orçamento:** 3 etapas — Criado → Aguardando aprovação → Aprovado/Recusado
  (a etapa final muda de cor/ícone conforme o resultado). `cancelado` também
  fica fora da progressão.
- **Limitação conhecida:** o stepper não mostra a data de cada etapa
  individual (só a de abertura/conclusão) porque o banco não guarda um
  histórico de mudança de status — só o status atual. Se um dia isso virar
  necessário, precisaria de uma tabela de log de status.

## 11. Painel modo TV

Nova tela `./painel` — pensada pra ficar aberta numa aba dedicada, num
monitor/TV da sala de espera. Mostra só placa + primeiro nome do cliente
(sem telefone, e-mail, CPF ou qualquer outro dado sensível) dos veículos em
andamento e dos prontos pra retirada.

- **Rota `painel`:** liberada tanto pra navegação direta/favorito
  (`$rotasNavegacaoDireta` em `public/index.php`) quanto sem exigir caixa
  aberto (`$rotasSemCaixa`) — é uma tela passiva, não faz sentido travar por
  essas regras pensadas pro fluxo operacional normal. Continua exigindo
  login, então quem monta o painel loga uma vez no navegador dedicado da TV.
- **"Pronto pra retirada":** o banco não tem uma flag de "retirado", só o
  status `CONCLUIDA`. Pra não acumular OS concluídas há semanas na tela,
  `OrdemServico::listarConcluidasRecentes()` filtra só as concluídas nas
  últimas 48h.
- **Front-end:** view isolada (`Site/painel.php`, adicionada à lista
  `$noLayout` do `Controller::view()` — sem sidebar/topbar),
  `<meta http-equiv="refresh" content="30">` pra atualizar sozinha, e um
  relógio ao vivo via JS. Link "Painel (TV)" adicionado no menu lateral
  (abre em nova aba).
- **Tema claro/escuro:** usa o mesmo mecanismo de base do resto do sistema —
  script "aplica o tema antes de renderizar" (evita flash) e
  `localStorage['mecanize-theme']`, compartilhado com o resto do sistema. Na
  primeira versão o painel usava as variáveis de cor da *sidebar*
  (`--color-sidebar-*`), que são escuras nos dois temas por design — corrigido
  pra usar as variáveis de conteúdo (`--color-bg`, `--color-surface` etc.),
  que realmente mudam entre claro/escuro. Favicon dinâmico também adicionado,
  seguindo `prefers-color-scheme` do navegador — mesmo padrão já usado na
  tela de login.
- **Botão de tema com desenho próprio:** o toggle reaproveitado da topbar
  principal ficava invisível no modo escuro do painel (ele depende do
  contexto visual da topbar, que o painel não tem). Substituído por um botão
  circular desenhado só pra essa tela — ícone de sol/lua com troca animada
  (rotação + fade), cores e contraste fixos que funcionam nos dois temas.

## 12. Segurança adicional: token do PlateRecognizer exposto

Durante a implementação da assinatura digital, ao olhar `PlacaController.php`
(leitura de placa por foto), encontrei um token de API do serviço
platerecognizer.com **hardcoded em texto puro** — igual ao problema da senha
do Gmail, só que dessa vez **já tinha sido enviado ao GitHub** nos commits
anteriores (a senha do Gmail eu peguei antes do primeiro push; esse token
passou despercebido porque só apareceu numa varredura feita depois).

- Movido para `.env` (`PLATE_RECOGNIZER_TOKEN`), mesmo padrão das outras
  credenciais.
- Varredura no resto do código não encontrou mais nada parecido.
- Como o token já estava no histórico remoto, reiniciei o histórico do git
  **de novo** e usei `git push --force` pra sobrescrever o GitHub — combinado
  com o usuário antes de fazer, por ser uma ação mais arriscada que reescrever
  histórico que nunca foi enviado.

## 13. Assinatura digital do cliente

Canvas de assinatura (desenho com mouse/touch, sem biblioteca externa) em
dois pontos do fluxo:

- **Aprovar orçamento** (modal em `listOrcamentos.php`) — assinatura
  opcional antes de confirmar a aprovação.
- **Concluir OS** — o botão de concluir (que antes só tinha um
  `confirm()` do navegador) virou um modal com o mesmo canvas de assinatura,
  pensado pro momento da retirada do veículo.

**Banco:** nova coluna `assinatura_cliente` (MEDIUMTEXT, guarda o PNG em
base64 direto — sem infraestrutura de upload de arquivo, que o projeto não
tinha) em `orcamentos` e `ordens_servico`, via
`database/add_assinatura_cliente.php`.

**Back-end:** `Orcamento::aprovar($id, $assinatura = null)` e
`OrdemServico::concluir($id, $assinatura = null)` passaram a aceitar a
assinatura opcionalmente (usa `COALESCE` no SQL — se não vier assinatura
nova, não apaga uma que já existia). `getById()` de ambos os models passou a
trazer a coluna também.

**Front-end:** `public/assets/js/assinatura.js` — componente reutilizável
(`initAssinatura(canvasId)`), usado nos dois modais. Quando existe assinatura
salva, ela aparece em `viewOrcamento.php`, `viewOrdemServico.php` e nos dois
templates de PDF (substituindo a linha em branco "Assinatura do Cliente" por
uma imagem real, quando disponível).

**Não incluído:** o drag do Kanban (mudança rápida de status) não pede
assinatura — só os fluxos de aprovar orçamento e concluir OS, que são ações
deliberadas com confirmação.

## 14. Dashboard financeiro mais rico

Novos gráficos no `./home`, dentro do mesmo bloco **ADMIN-only** que já
existia pro card "Faturamento Mensal":

- **Faturamento por Mês** (`Home::faturamentoPorMes()`) — gráfico de barras,
  últimos 6 meses, reaproveitando o Chart.js já carregado no sistema.
- **Ticket médio** (`Home::ticketMedio()`) — mês atual vs. geral.
- **Peças mais vendidas** (`Home::produtosMaisVendidos()`) — top 6 por
  quantidade, com barra de proporção visual.

**Bug real encontrado e corrigido durante o teste:** os *dados* dos novos
gráficos (valores de faturamento em R$) estavam sendo escritos no HTML da
página **para qualquer usuário logado**, mesmo com o card visual escondido
pra quem não é ADMIN — ou seja, um TECNICO/ATENDENTE não via o gráfico, mas
conseguia ler os valores de faturamento abrindo o código-fonte da página
(`Ctrl+U`). Corrigido: as variáveis JS com os dados financeiros só são
escritas no HTML quando `$_SESSION['user_tipo'] === 'ADMIN'`; pra qualquer
outro papel, viram array vazio. Testado e confirmado (TECNICO não vê nem o
card nem os dados no código-fonte; ADMIN continua vendo tudo).

## 15. Rastreamento público (sem login) de OS/Orçamento

Pedido do dono do sistema: um link tipo rastreio de encomenda, que o cliente
abre direto (sem senha, sem cadastro) e vê o status do próprio veículo —
pra parar de precisar ligar perguntando "ficou pronto?".

- **Banco:** nova coluna `token_rastreio` (string aleatória de 48
  caracteres, `bin2hex(random_bytes(24))`) em `orcamentos` e
  `ordens_servico`, com índice único. Migração
  `database/add_token_rastreio.php` também preenche o token das OS's e
  orçamentos que já existiam antes dessa feature.
- **Rota pública:** `./rastrear?token=...` — adicionada em `$rotasPublicas`
  de `public/index.php` (a única checagem que se aplica: existe o token ou
  não). Todas as outras regras do sistema (janela de horário do atendente,
  "navegação só por dentro do sistema", caixa obrigatório) só valem pra quem
  está logado, então não afetam essa rota.
- **Dados expostos são propositalmente mínimos:** placa, modelo do veículo,
  primeiro nome do cliente e o status — **sem valor financeiro, CPF,
  telefone ou endereço** — mesmo que o link seja encaminhado adiante sem
  querer, não vaza nada sensível. `OrdemServico::getPorTokenRastreio()` e
  `Orcamento::getPorTokenRastreio()` fazem essa consulta restrita.
- **Front-end:** `Site/rastreio.php` (o mesmo visual do stepper de status já
  usado internamente) e `Site/rastreioNaoEncontrado.php` pra token
  inválido/expirado. Tela isolada (sem sidebar), pensada pra abrir bem no
  celular.
- **Como o cliente recebe o link:** botão "Link de Rastreio" (copia pra área
  de transferência) em `viewOrdemServico.php` e `viewOrcamento.php`, e o link
  passou a vir embutido automaticamente nas mensagens de WhatsApp que já
  existiam (conclusão de OS / aprovação de orçamento).
- **Testado sem login algum** (sessão HTTP nova, sem cookie): token válido
  de OS e de orçamento renderizam a página corretamente; token inválido
  mostra a tela de erro; nenhum valor em R$ aparece na página pública.

## O que NÃO foi feito nesta rodada (fora do escopo pedido)

- CSRF tokens nos formulários.
- Testes automatizados.
- Migração completa da validação pros outros 12 models.
- Envio de WhatsApp automático de verdade — ver decisão na seção 7 acima.
