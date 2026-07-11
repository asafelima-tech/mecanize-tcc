# Melhorias — 10/07/2026

Documentado no mesmo espírito de `comentarios.md` e das `MELHORIAS_*.md` anteriores.

## 0. Gradiente nas colunas do gráfico "Faturamento por Mês"

Pedido rápido do dono do sistema: o gráfico de barras do dashboard (`home.php`)
usava uma cor sólida (`#003BDD`), diferente do gradiente já usado no gráfico
de barras da tela de Relatórios. Troquei pro mesmo gradiente vertical
(`#60A5FA` no topo → `#003BDD` na base), reaproveitando a técnica que
`listRel.php` já usava (`ctx.createLinearGradient`).

## 1. Auditoria completa do sistema

Pedido do dono do sistema, junto com a equipe: "leia tudo e detecte bugs ou
inconsistências". Rodei uma auditoria dividida em 4 frentes em paralelo
(clientes/veículos/placas, orçamento/OS/PDF/WhatsApp, estoque/caixa/relatórios,
autenticação/segurança), lendo controllers, models e views de todos os
módulos (~30 mil linhas). Resultado: **39 achados** (5 críticos, 10 altos, 17
médios, 8 baixos), publicados num relatório visual
(`https://claude.ai/code/artifact/bacbaef8-e134-4398-a75d-3fbd5d3294d3`).

Depois disso, o dono do sistema pediu pra corrigir **tudo** — as seções
abaixo documentam cada correção efetivamente aplicada nesse mesmo dia.
Tentei delegar boa parte do trabalho pra 3 agentes em paralelo (cada um já
tinha lido a fundo sua área durante a auditoria), mas os 3 esbarraram num
limite de sessão da API e pararam no meio do trabalho — o que cada um já
tinha aplicado antes de parar foi conferido e testado; o resto foi
implementado diretamente.

## 2. Segurança — Autenticação e infraestrutura central

- **Escalação de privilégio corrigida**: `UserController::perfil()` gravava
  `tipo` (o papel do usuário — ADMIN/TECNICO/ATENDENTE) direto do que vinha
  no POST, sem checar permissão nenhuma — qualquer usuário logado podia se
  promover a ADMIN só enviando `tipo=ADMIN` no formulário de "Meu Perfil".
  Agora esse formulário **ignora** qualquer `tipo` postado e sempre preserva
  o valor já salvo — só `UserController::edit()` (ADMIN-only) pode mudar o
  papel de alguém.
- **`.env` e a raiz do projeto ficavam acessíveis por HTTP**: o Apache serve
  `htdocs` inteiro sem vhost dedicado, e não havia `.htaccess` na raiz do
  projeto (só `database/`, `app/` e `config/` tinham). Isso significava que
  `.env` (senha do banco, senha de app do Gmail, token do PlateRecognizer),
  `.git/`, `composer.*` e os `.md` internos podiam ser baixados direto pela
  URL. Criado `.htaccess` na raiz com `Require all denied`, e
  `public/.htaccess` ganhou um `Require all granted` explícito pra continuar
  sendo servido normalmente.
- **Fixação de sessão (session fixation)**: o login nunca trocava o ID de
  sessão depois de autenticar — combinado com `session.use_strict_mode=0` no
  php.ini, um ID de sessão plantado antes do login continuava válido depois.
  Adicionado `session_regenerate_id(true)` logo após a senha ser validada.
- **Sem limite de tentativas de login**: era possível tentar senha
  ilimitadamente contra um e-mail conhecido. Adicionado bloqueio temporário
  (15 minutos) após 5 tentativas erradas seguidas — novas colunas
  `tentativas_login`/`bloqueado_ate` em `usuarios`
  (`database/add_bloqueio_login_usuario.php`), zeradas a cada login bem-sucedido.
- **Cookie de sessão sem proteção explícita**: adicionado
  `session_set_cookie_params()` com `HttpOnly` + `SameSite=Lax` sempre, e
  `Secure` condicional a já estar em HTTPS (senão o cookie seria descartado
  em HTTP local).
- **Credenciais de banco com fallback fixo**: `Database.php` caía
  silenciosamente pra `root`/sem senha/`localhost`/`mecanize_db` se o `.env`
  estivesse incompleto. Agora lança erro explícito se `DB_HOST`/`DB_NAME`/
  `DB_USER` não estiverem definidos, e liga `PDO::ATTR_ERRMODE` explicitamente
  (antes dependia do padrão do PHP/PDO instalado).
- **Nenhuma proteção contra CSRF em lugar nenhum do sistema** (achado mais
  trabalhoso): criado `app/core/Csrf.php` (token por sessão + validação) e
  `public/assets/js/csrf.js`, que injeta automaticamente o token em **todo**
  form (`<?= csrf_field() ?>`/injeção via JS no evento `submit`, fase de
  captura) e em toda chamada `fetch(..., {method:'POST'})` (header
  `X-CSRF-Token`). `public/index.php` agora valida esse token em **toda**
  requisição POST antes de rotear — sem exceção. Página nova `Site/419.php`
  ("A página expirou") pra quando o token estiver ausente/errado.
  - Achado um bug real ao testar: `http_response_code(419)` (convenção do
    Laravel, não um código HTTP registrado) derrubava a resposta pra 500
    nesse Apache/mod_php — trocado pra 403.
  - Achado outro bug real ao testar: `error_get_last()` num
    `register_shutdown_function` podia "herdar" um erro fatal de uma
    requisição ANTERIOR atendida pelo mesmo worker do Apache (mod_php reusa
    threads) — `error_clear_last()` adicionado no topo do `index.php`
    resolve isso.
- **Handler global de erro**: antes, uma falha de banco/config imprevista
  imprimia um stack trace cru (caminho absoluto, DSN) pra qualquer visitante.
  Agora, com `APP_DEBUG=false` (produção), o erro vai só pro log do PHP e a
  tela mostra uma página genérica (`Site/500.php`); `APP_DEBUG=true` (local,
  já ligado no `.env` desta máquina) continua mostrando o erro real.
- **Detecção de HTTPS unificada**: `recuperarSenha()` usava uma checagem
  diferente de `Controller::urlAbsoluta()` — unificado, ambos usam a mesma
  lógica agora.
- **Formulário morto de "Notificações"** no perfil (postava pra uma rota que
  não existe, sempre dava 404) — removido.
- **Exclusões restritas a ADMIN**: cliente, veículo, produto, categoria e
  movimentação de estoque não tinham NENHUMA checagem de papel — qualquer
  ATENDENTE/TECNICO conseguia excluir esses registros direto pela URL.
  Alinhado com o dono do sistema, restringido a ADMIN (`requireRole`) tanto
  no servidor quanto escondendo os botões na tela pra quem não é ADMIN — e
  os links de exclusão de categoria/movimentação que eram GET (vulneráveis a
  CSRF só de estar logado, nem clique consciente precisava) viraram POST.

**Testado em cada caso** com usuários ADMIN/TECNICO temporários (criados e
removidos ao final): tentativa de auto-promoção bloqueada mesmo enviando
`tipo=ADMIN`; `.env`/`composer.json` retornando 403; login com 5 senhas
erradas bloqueando a 6ª tentativa mesmo com a senha certa; POST sem token
CSRF barrado (403) e com token passando normalmente; TECNICO recebendo 403
ao tentar excluir categoria direto pela URL enquanto ADMIN consegue
normalmente.

## 3. Estoque, Caixa e Relatórios

- **Estoque podia ficar negativo** com duas saídas simultâneas do mesmo
  produto (a checagem "cabe no estoque?" era um SELECT solto antes da
  transação, não travava a linha). Corrigido com `SELECT ... FOR UPDATE`
  travando a linha do produto antes de validar E de descontar, em
  `MovimentacaoEstoque::save()`/`update()`.
- **Quantidade fracionada era truncada**: a coluna é `DECIMAL(10,3)` (litros/
  kg), mas o código fazia `(int)` na quantidade em vários pontos, além dos
  formulários não aceitarem decimal. Corrigido (model + `step="0.001"` nos
  forms + JS ajustado) — testado com saída de 2,5 unidades preservando a
  fração.
- **Faturamento de OS concluída nunca caía no Caixa**: o banco já tinha as
  colunas prontas (`origem='OS'`, `categoria='OS'`, `ordem_servico_id`), mas
  nada as preenchia. Adicionado um campo "Forma de pagamento" no modal de
  conclusão de OS (`listOrdemServico.php`), e
  `OrdemServicoController::concluir()` agora lança automaticamente o valor
  da OS como ENTRADA no caixa da sessão aberta ao concluir — testado
  criando uma OS de teste, concluindo via HTTP com forma PIX e confirmando
  a movimentação de caixa gerada corretamente (depois removida).
- **Preço de custo e margem de lucro apareciam pra qualquer papel**: só a
  listagem de produtos escondia isso de quem não é ADMIN; as telas de ver/
  criar/editar produto e o histórico de movimentação mostravam pra todo
  mundo. Escondido nos 5 arquivos envolvidos (com cuidado extra em
  `editProduto.php`/`saveProduto.php`: o campo vira um `<input type="hidden">`
  quando esconde, senão `Produtos::update()` zerava o custo ao salvar sem
  ele) — testado como TECNICO editando o preço de venda de um produto de
  teste e confirmando que o custo permaneceu intacto.
- **Gráfico "Fluxo de OS × Receita" vazava faturamento diário**: o gráfico
  "Faturamento Mensal" já era escondido de quem não é ADMIN desde
  08/07, mas esse outro gráfico (dashboard, dados dos últimos 7 dias) tinha
  ficado de fora dessa proteção. Corrigido (série de Receita nem é montada
  no JS pra quem não é ADMIN) — testado confirmando `dadosReceita = []` e
  ausência da legenda "Receita (R$)" pra TECNICO.
- **Exclusão de categoria em uso deixava produtos órfãos**: adicionado
  `Categoria::contarProdutosVinculados()`, bloqueando a exclusão com uma
  mensagem clara ("ainda há produtos cadastrados nesta categoria") em vez de
  silenciosamente quebrar a referência.
- **Paginação de clientes e veículos**: os botões ‹/›/número de página não
  tinham `onclick`/`href` nenhum (decorativos) — e a paginação de veículos
  também estava quebrada no back-end (`LIMIT 10` fixo, sem `OFFSET`, sem
  contagem filtrada). Corrigido nos dois lados: `Veiculo::listarVeiculos()`
  ganhou paginação de verdade (mesmo padrão já usado em `Cliente.php`), e os
  botões nas duas telas agora navegam preservando busca/filtro ativo —
  testado direto no model (`totalResultados`/`totalPaginas` batendo com uma
  busca filtrada) e via HTTP.
- **"Diferença" no histórico do Caixa media a coisa errada**: mostrava
  "fechamento − abertura" (variação líquida do dia) em vez de "contado −
  esperado" (sobra/falta de verdade), que é o que a tela de fechamento ao
  vivo já calculava certo. Adicionada coluna `valor_esperado` em `caixa`
  (`database/add_valor_esperado_caixa.php`), preenchida no momento do
  fechamento (manual, automático por expediente e automático por aba
  fechada) — o histórico agora reaproveita esse valor gravado. Sessões
  fechadas antes dessa coluna existir mostram "—" (não dá pra recalcular
  retroativamente).
- **"Estoque Baixo" com números diferentes em Produtos vs. Relatórios**:
  investigado a fundo — na verdade **não é um bug**. A tela de Produtos tem
  3 categorias de filtro separadas (em estoque / baixo estoque / esgotado),
  então "baixo estoque" ali exclui de propósito o que já zerou (categoria
  própria). Relatórios só tem UM card combinado ("tudo que está em risco"),
  por isso inclui zerado também. Mantido como está.
- **"% de faturamento" e gráfico de despesas mostravam valores fixos como
  se fossem reais**: `faturamentoPorcentagem` era hardcoded em `0`, embora
  os dados pra calcular de verdade já existissem — agora calcula a variação
  real contra o mês anterior (com seta e cor mudando de direção quando
  negativo). O gráfico de despesas sempre retornava zero (não existe
  nenhuma tabela de despesas no sistema) — em vez de mostrar uma linha
  "zerada" ao lado da receita real (o que parecia dizer "a oficina não tem
  despesa nenhuma"), removi a série/legenda de despesa do gráfico
  (`Relatorio::graficoDespesasSemana()` também removido, sem mais uso).
- **Edição direta de estoque no produto não deixava rastro**: corrigir o
  estoque pela tela de editar produto não gerava nenhum registro em
  movimentações — impossível reconciliar depois. Agora, se o `estoque_atual`
  mudar por ali, `Produtos::update()` grava automaticamente uma movimentação
  `tipo=AJUSTE` (valor já existia no ENUM do banco, nunca usado) com a
  diferença (com sinal). Tela de movimentações ganhou suporte visual pra
  esse terceiro tipo (ícone/badge próprios, "Ajuste" no filtro) — antes
  qualquer coisa que não fosse ENTRADA virava badge de "Saída" vermelha, o
  que teria classificado um ajuste positivo errado. Também aproveitei pra
  corrigir a coluna Quantidade da mesma tela, que arredondava pra 0 casas
  decimais (escondia a fração mesmo depois do fix de quantidade fracionada
  acima).

## 4. Clientes e Veículos

- **Sem validação nem checagem de duplicidade de CPF/CNPJ**: adicionado
  `Validator::cpfCnpj()` com o algoritmo oficial de dígito verificador
  (módulo 11) pros dois documentos, mais `Cliente::cpfExisteAtivo()`/
  `cnpjExisteAtivo()` (mesmo padrão de `emailExisteAtivo()`), ligados no
  `ClienteController::save()`/`edit()` — testado com CPF repetido (rejeitado),
  CPF válido conhecido (aceito) e CPF com dígito verificador errado
  (rejeitado).
- **`Validator::celularBr()` rejeitava telefone fixo**: a máscara do sistema
  (`masks.js`) já oferece fixo (8 dígitos) e celular (9 dígitos), mas a
  validação só aceitava celular. Relaxado pra aceitar os dois formatos,
  mantendo as outras checagens (DDD real, não pode ser tudo dígito igual).
- **ID não numérico derrubava a tela com erro fatal**: `reativar()` e a
  edição de cliente não convertiam `$_GET`/`$_POST['id']` pra `int` antes de
  passar pra métodos tipados `int` — corrigido com `ctype_digit()` + cast.
- **Cadastro de veículo sem validação nenhuma no servidor**: adicionado
  `Validator` exigindo cliente/marca/modelo/placa preenchidos, tanto no
  cadastro quanto na edição.
- **Editar cliente não sincronizava `desativado_em`**: só os fluxos
  dedicados de excluir/reativar atualizavam essa data — alternar "Ativo" pela
  tela de Editar deixava a data desatualizada, bagunçando a ordenação da
  lista de desativados. Corrigido com um `CASE` no próprio UPDATE.
- **Iniciais do avatar quebravam em nomes acentuados**: `substr()`/acesso a
  `$string[0]` são por byte, não por caractere — "Álvaro" virava um símbolo
  quebrado. Trocado por `mb_substr()`/`mb_strtoupper()`.
- **`ModeloVeiculoController` tinha código morto**: `list()`/`save()`/
  `edit()`/`delete()` referenciavam models (`ModeloVeiculo`/`MarcaVeiculo`)
  que nunca existiram — nenhuma rota apontava pra eles (só
  `modelosPorMarca()`/`criarModelo()`, que usam `Veiculo` de verdade, são
  reais). Removido o código morto em vez de deixar uma bomba-relógio pra
  explodir no dia que alguém ligasse uma rota esperando que funcionasse.

## 5. Orçamento, Ordem de Serviço e PDF

- **PDF do Orçamento somava a comissão do técnico como se fosse receita
  extra**: `valor_mao_obra` é a comissão já embutida dentro de peças/
  serviços, não um valor adicional — e o PDF (documento entregue ao
  cliente) não deveria expor isso de qualquer forma, mesmo tratamento que o
  PDF de OS já dava. Removido do subtotal e da tabela de totais; Subtotal
  agora sempre bate com Total Geral.
- **Orçamento já aprovado podia ser marcado como "recusado"**: não havia
  guarda de transição de status nem no model nem no controller — um duplo
  clique ou reenvio de formulário podia recusar um orçamento cuja OS já
  existia e já tinha baixado estoque. Adicionada checagem: só aceita a
  transição `pendente → reprovado`.
- **Aprovar o mesmo orçamento duas vezes rápido podia criar duas OS**: a
  checagem "já existe OS?" era um SELECT solto sem transação. Reescrito com
  `SELECT ... FOR UPDATE` travando a linha do orçamento (e da checagem de OS
  existente) do início ao fim da aprovação, mais um índice único
  (`uq_ordens_servico_orcamento_id`, `database/add_unique_orcamento_os.php`)
  como backstop no banco caso o lock falhe por algum motivo.
- **Botão "Excluir" de Ordem de Serviço não funcionava**: era um link GET,
  mas o controller (corretamente) exige POST — clicar só recarregava a
  lista, dando falsa impressão de sucesso. Convertido pra formulário POST.
- **Assinatura aceitava qualquer `data:image/*`, não só PNG**: o valor vai
  direto pra uma `<img>` num PDF renderizado com acesso remoto habilitado
  (Dompdf) — apertado pra exigir `data:image/png;base64,` especificamente
  (único formato que o canvas de assinatura realmente emite).
- **`status` do orçamento gravado sem lista de valores permitidos**: um POST
  adulterado podia gravar qualquer string na coluna. Adicionada validação
  contra o ENUM real antes do UPDATE (mesmo tratamento que `comissao_base`
  já tinha).

## Testes

Lint (`php -l`) rodado em **todos os 55 arquivos** modificados/criados nesta
sessão — nenhum erro de sintaxe. Smoke test HTTP das principais telas
(`home`, clientes, veículos, produtos, categorias, movimentação de estoque,
OS, orçamentos, caixa, relatórios, usuários, perfil) como ADMIN e como
TECNICO — todas respondendo `200` (exceto as ADMIN-only, corretamente `403`
pra TECNICO). Todo teste que envolveu dado real usou usuários/produtos/
categorias/OS **criados especificamente pro teste e removidos ao final** —
nenhum dado de produção foi alterado.

## Fora do escopo desta rodada

- ~~Checagem de duplicidade de e-mail/telefone/placa/CPF/CNPJ continua sendo
  só a nível de aplicação~~ — **feito depois, ver seção 6 abaixo.**
- `requireRole()` continua ausente na maioria dos controllers de
  cliente/veículo/produto/categoria/movimentação para as ações que **não**
  são exclusão (criar/editar seguem liberados pra todo mundo) — isso foi
  confirmado como intencional com o dono do sistema.

## 6. Fechando os últimos gaps: índices únicos no banco + suíte de testes automatizados

Pedido do dono do sistema depois de perguntar "podemos dizer que está
pronto?" — respondi que pra uso de uma oficina só, sim, mas com duas
ressalvas da seção anterior em aberto. Ele pediu pra fechar as duas.

### 6.1. Índices únicos como backstop no banco

A checagem de duplicidade de e-mail/telefone/CPF/CNPJ (`clientes`) e placa
(`veiculos`) só existia a nível de aplicação (SELECT antes do INSERT) —
uma corrida de verdade (duplo clique, retry de rede) ainda podia criar
duplicata. Adicionar um índice único direto nas colunas quebraria uma
regra já existente e intencional (duplicidade É permitida entre um
cliente ativo e um inativo — reativar um cadastro antigo não pode ser
bloqueado por um cliente novo que reusou o mesmo contato). Solução:
`database/add_indices_unicos.php` adiciona 4 **colunas geradas** (STORED)
em `clientes` — `uniq_email`/`uniq_telefone`/`uniq_cpf`/`uniq_cnpj` — que só
reproduzem o valor normalizado quando `ativo = 1` e o campo não está vazio
(senão viram `NULL`, e o InnoDB não conta múltiplos `NULL` como duplicata
num índice único). O índice único é criado em cima dessas colunas geradas,
nunca nas colunas reais. `veiculos.placa_normalizada` não tem conceito de
"inativo", então ganhou um índice único direto, sem a complicação.

- **Achada uma duplicata real nos dados** antes de rodar a migração: dois
  clientes ativos com o mesmo e-mail (`ronald_speed@gmail.com`) — o cliente
  #3 "Roberto", que a auditoria de ontem já tinha sinalizado com telefone
  inválido. Perguntei ao dono do sistema; ele confirmou que era dado de
  teste do início do projeto e pediu pra desativar (soft-delete,
  `Cliente::delete()`, histórico preservado) — feito antes da migração.
- **Confirmação explícita do dono do sistema antes de rodar**: como isso é
  uma alteração de schema em tabelas de produção (`clientes`/`veiculos`),
  o próprio ambiente bloqueou a execução automática até eu descrever
  exatamente o que ia mudar e pedir autorização direta — autorizado, e a
  migração rodou sem problema (nenhuma outra duplicata encontrada).
- **`Cliente::create()`/`update()` e `Veiculo::create()`/`update()`** ganharam
  `try/catch` em volta do `execute()`: uma violação do índice único (SQLSTATE
  `23000`) agora vira a mesma mensagem amigável de sempre
  ("Já existe outro cliente ativo com..."), em vez de estourar como exceção
  não tratada (a tela de erro 500 genérica adicionada mais cedo hoje).
- **Testado** com dado descartável: e-mail duplicado entre dois clientes
  ativos bloqueado; mesmo e-mail entre um cliente ativo e um inativo
  permitido; dois clientes sem e-mail (`NULL`) não conflitam entre si; CPF
  duplicado bloqueado pelo backstop do banco mesmo sem checagem prévia na
  aplicação (`Cliente::create()` só pré-checa e-mail/telefone, não CPF/CNPJ
  — exatamente o cenário que o índice único existe pra cobrir).

### 6.2. Suíte de testes automatizados (PHPUnit)

Projeto não tinha nenhum teste automatizado. Instalado PHPUnit 9.6 (via
`composer.phar` — PHPUnit 10+ exige PHP 8.1, esta máquina tem PHP 8.0.30)
como dependência de desenvolvimento. Criado um **banco de teste dedicado**
(`mecanize_db_test`, cópia do schema de `mecanize_db` via `mysqldump
--no-data`) — os testes nunca encostam no banco de produção;
`tests/bootstrap.php` carrega `.env.testing` (comitado, sem segredo nenhum)
antes de qualquer outra coisa e aborta se `DB_NAME` não for
`mecanize_db_test`, como trava de segurança.

28 testes cobrindo a lógica mais crítica corrigida hoje: `Validator`
(celularBr aceitando fixo, cpfCnpj com dígito verificador), `Csrf` (geração/
validação de token), `Cliente` (os índices únicos da seção 6.1),
`MovimentacaoEstoque` (bloqueio de saída maior que o estoque, quantidade
fracionada preservada, auditoria automática de ajuste manual) e `Orcamento`
(aprovar/recusar). Cada teste cria e remove seu próprio dado de teste —
nunca reaproveita nem deixa lixo (confirmado rodando a suíte duas vezes
seguidas e conferindo as tabelas zeradas depois).

**Achado real ao escrever os testes de `Orcamento`** (não um problema do
teste, um bug de verdade na correção da seção 3 de hoje): `aprovar()` fazia
`SELECT ... FOR UPDATE` na linha do orçamento e mantinha essa trava aberta
enquanto chamava `OrdemServico::create()` — que roda numa conexão PDO
separada e precisa, pra validar a `FOREIGN KEY` de `ordens_servico.orcamento_id`,
de um lock de leitura na MESMA linha do orçamento que a primeira conexão já
travava em modo exclusivo. Resultado: **toda aprovação de orçamento
travava por ~50 segundos (`innodb_lock_wait_timeout`) até falhar** — não
uma corrida rara, sempre, em qualquer aprovação, inclusive em produção
(confirmado que `mecanize_db` tem essa mesma FK). Só apareceu porque o
teste automatizado realmente executa o código; a revisão manual de código
mais cedo hoje não teria pego isso. Corrigido: a checagem "já existe OS?"
continua com `FOR UPDATE`, mas a transação é **commitada antes** de chamar
`OrdemServico::create()` (conexão separada) — a proteção contra dupla-OS
por corrida passa a ser só o índice único `uq_ordens_servico_orcamento_id`
(que já existia desde a seção 3), tratando uma eventual rejeição do INSERT
por essa constraint como sucesso (a outra requisição já criou a OS).
Testado: as 3 aprovações/recusas relevantes passam em ~0,2s (era >50s antes
do fix), e a suíte inteira roda em menos de 1 segundo.

**Como rodar:** `/c/xampp/php/php.exe vendor/bin/phpunit` (usa
`phpunit.xml` na raiz, que aponta pro bootstrap acima). Pra recriar o banco
de teste do zero: `mysqldump -u root --no-data mecanize_db | mysql -u root
mecanize_db_test` (depois de criar o banco `mecanize_db_test`).
