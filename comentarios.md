# Comentários

Última atualização em 23/06/2026

## O que foi feito

### 1. Livro-caixa — fluxo de entradas e saídas (Fase 1)

Controle de caixa pedido em `ERROS.MD`: lançamentos de entrada/saída dentro de uma
sessão de caixa, com forma de pagamento, categoria de saída, saldo do caixa anterior
e cálculo do caixa atual.

**Regra central:** _caixa atual = abertura + entradas em DINHEIRO − todas as saídas_.
PIX, cartão e boleto são registrados e entram nos totais (e nos relatórios da Fase 3),
mas **não** somam no dinheiro físico da gaveta.

#### Banco de dados

- Nova tabela `movimentacoes_caixa` — `database/create_movimentacoes_caixa.php`
  (criada via PDO; o `mysql.exe` do XAMPP está quebrado na máquina do Miguel). Colunas:
  `caixa_id`, `tipo` (ENTRADA/SAIDA), `forma_pagamento`, `categoria`, `valor`,
  `descricao`, `usuario_id`, `origem` (MANUAL/OS), `ordem_servico_id`, `data_movimentacao`.

#### Back-end (aditivo — sem alterar a lógica existente)

- `app/models/MovimentacaoCaixa.php` — `registrar()`, `porSessao()`, `resumoSessao()`
  (entradas por forma, saídas por categoria e o caixa atual).
- `app/models/Caixa.php` — `getUltimoFechamento()` (saldo do caixa anterior).
- `app/controllers/CaixaController.php` — `movimentacoes()` e `registrarMovimentacao()`;
  `abrir/fechar/list` passam saldo anterior e resumo.
- `config/routes.php` — rotas `caixaMovimentacoes` e `registrarMovimentacaoCaixa`.

#### Front-end

- Nova tela `app/views/Caixa/movimentacoesCaixa.php` — caixa atual em destaque,
  formulário entrada/saída (alterna forma de pagamento × categoria), resumo por
  forma/categoria e a lista de lançamentos da sessão.
- `app/views/Caixa/abrirCaixa.php` — mostra o saldo anterior e prefilla a abertura.
- `app/views/Caixa/fecharCaixa.php` — mostra o resumo da sessão e sugere o dinheiro esperado.
- `app/views/Caixa/listCaixa.php` — botão "Lançamentos" e caixa atual quando há sessão aberta.
- `app/views/layout/header.php` — a pill de "caixa aberto" leva aos lançamentos.

**Pendente:** Fase 2 (concluir OS escolhendo a forma de pagamento → entrada automática)
e Fase 3 (relatórios ADMIN diário/semanal/mensal/anual).

### 2. Logout fecha o caixa aberto — `app/controllers/UserController.php` (logout)

Antes de destruir a sessão, se houver caixa ABERTO, ele é fechado automaticamente com o
**dinheiro apurado na gaveta** (caixa atual = abertura + entradas em dinheiro − saídas),
calculado a partir das movimentações da sessão, com a observação "Fechado automaticamente
ao sair do sistema". Uma falha ao fechar não impede o logout.

### 3. Atendente expulso fora do expediente — `public/index.php:70`

Qualquer ATENDENTE (a Laura é uma) é desconectado na hora ao sair da janela seg–sex
07:00–18:00 / sáb 07:00–13:00 — a mesma regra que já existia no login, agora aplicada
também durante a sessão ativa. O chute passa pelo `./logout`, então o caixa também é
fechado quando ela é expulsa (coerente com o fechamento automático do item 2). Na volta,
a tela de login mostra o aviso explicando o horário.

### 4. URL inerte (vale para qualquer tipo de acesso) — `public/index.php:88`

Digitar/colar/editar o endereço ou abrir um favorito não navega — cai no Início. Só
links/botões clicados dentro do sistema funcionam. Detecção por Sec-Fetch-Site (que a
barra de endereço não consegue forjar) com fallback no host do Referer. Aplica-se só a
GET; POST de formulários e os redirecionamentos internos do próprio sistema são preservados.

### 5. Favicon segue o navegador, não o tema do app — `app/views/layout/footer.php`

O ícone da aba estava se confundindo com o tema do sistema: ao alternar o tema pelo botão,
o favicon mudava junto. Agora ele segue só a preferência de cor do **navegador/SO**
(`prefers-color-scheme`): logo **preta** em navegador claro e logo **branca** em navegador
escuro — independente do tema escolhido dentro do app.

- `atualizarFavicon()` passou a ler `window.matchMedia('(prefers-color-scheme: dark)')`
  em vez de `data-theme`.
- Removida a chamada de `atualizarFavicon()` de dentro de `applyTheme()` (o botão de tema
  não mexe mais no ícone). O listener de `change` do `matchMedia` continua reagindo quando
  o usuário troca o tema do próprio navegador.

### 6. Atendente só vê os próprios caixas — `app/views/Caixa/listCaixa.php`

No histórico de caixa, um ATENDENTE agora só enxerga as sessões em que participou — ou
seja, que ele mesmo **abriu ou fechou**. ADMIN continua vendo o histórico
completo.

- Filtro feito **só na view** (sem tocar em controller/model/core): lê `user_tipo` e
  `user_id_usuario` da sessão e, quando o tipo é `ATENDENTE`, mantém em `$caixas` apenas
  as linhas cujo `usuario_abertura_id` **ou** `usuario_fechamento_id` é o próprio usuário.
- Usado `OR` (abriu **ou** fechou) em vez de exigir ambos, porque um caixa ainda aberto
  não tem fechamento — assim o atendente também vê a sessão que abriu e segue aberta.
- O card de status no topo ("Caixa aberto · Fulano") **não** foi filtrado, por refletir o
  caixa aberto global do sistema (informação operacional do dia).

**Observação:** o filtro é apenas de exibição (front-end); o controller ainda consulta o
histórico inteiro do banco. Se precisar reforçar, mover o filtro para a query.

### 7. Máscara de placa (antiga + Mercosul) e selects nativos padronizados

Duas melhorias de front-end nos formulários.

**7.1 — Máscara de placa** (`public/assets/js/masks.js`, `app/views/Veiculos/saveVeiculo.php`,
`app/views/Veiculos/editVeiculo.php`)

- Máscara IMask **dinâmica** no campo `#placa`, alternando entre os dois formatos
  brasileiros conforme o 5º caractere: letra → **Mercosul** (`ABC1D23`), dígito →
  **antiga** (`ABC-1234`). Sempre em maiúsculas.
- Centralizada em `masks.js` (vale para cadastrar **e** editar). Removidos os
  formatadores manuais antigos das duas views (o do editVeiculo inseria traço fixo
  após 3 caracteres, quebrando o padrão Mercosul).
- No cadastro, a máscara sincroniza o campo oculto `placa_normalizada` (placa crua, sem
  traço) e é exposta como `window.placaMask` para o preenchimento via OCR (`lerPlaca`).

**7.2 — Selects de formulário com o painel do Tom Select** (`public/assets/js/select-enhance.js`
novo, `app/views/layout/header.php`, `public/assets/css/global.css`, `public/assets/css/darkmode.css`)

- Todo `<select>` de formulário agora vira **Tom Select** — incluindo o _painel aberto_
  (a lista de opções), que era o ponto da reclamação: o dropdown nativo era quadrado/feio,
  agora é o mesmo painel branco arredondado, com hover e busca, do select de **Cor**.
- `select-enhance.js` inicializa o Tom Select em todos os selects, **pulando** `#corSelect`
  (tem init próprio com bolinha de cor), `.toolbar-select` (filtros compactos ficam nativos)
  e quem tiver `data-no-ts`.
- A cascata dinâmica marca→modelo→combustível (e qualquer select repovoado via `innerHTML`)
  continua funcionando: um `MutationObserver` por select detecta a troca de `<option>`s e
  reconstrói o Tom Select (clear → addOptions → setValue), restaurando o valor. O observer é
  desligado durante a reconstrução para não entrar em laço. Não foi preciso tocar no JS das
  telas (saveVeiculo/editVeiculo etc.).
- O CSS de selects nativos (seta custom + cantos + foco em `global.css`/`darkmode.css`)
  permanece como _fallback_ para os selects que não viram Tom Select (toolbar/opt-out) e
  para o instante antes do JS carregar.

**Observação:** filtros de toolbar (`.toolbar-select`) ficaram de fora de propósito, para
não alterar a altura compacta deles. Se quiser, dá pra incluí-los também.

### 8. Radio "Sim/Não" (Ativo) recriado como toggle segmentado

(`public/assets/css/global.css`, `public/assets/css/darkmode.css`, e os 4 forms que usam
`.radio-row`: `Produtos/saveProduto`, `Usuarios/saveUsuario`, `Clientes/saveCliente`,
`Clientes/editCliente`)

- O campo Sim/Não usava radios nativos com só `accent-color`. Problemas: o outline de foco
  nativo ficava grudado depois do clique (a "caixa azul") e dava um glitch ao trocar de opção.
- Recriado como **toggle segmentado** (pill): o `<input type=radio>` nativo fica escondido
  (`opacity:0`, mas focável/acessível e ainda é o que o form envia) e o segmento selecionado
  é pintado de azul via `label:has(input:checked)`. Troca = transição suave de fundo, sem
  caixa de foco; anel de foco só aparece via teclado (`:focus-visible`).
- Markup das telas intacto (só removido um `style="padding-top:8px;"` inline que
  desalinhava o pill); o estilo é todo no `.radio-row`, então vale para os 4 forms de uma vez.
- Verificado em Chrome headless (screenshot): estados "Sim"/"Não" estáticos e a troca
  dinâmica via JS destacam o segmento correto.

**Nota técnica:** usa o seletor CSS `:has()` (suportado em todos os navegadores atuais). Em
navegador muito antigo sem `:has()`, o destaque do selecionado não apareceria — não é o caso
do Chrome/Edge usados aqui.

### 9. Stat cards de "Clientes Desativados" padronizados (`app/views/Clientes/listClientesDesativados.php`)

Os 4 cards de estatística dessa tela estavam fora do padrão das demais páginas: o grid tinha
um `style` inline (`grid-template-columns: repeat(auto-fill, minmax(220px,1fr)); max-width:960px`)
que esticava/desalinhava os cards, e cada ícone tinha cor inline fora da paleta (vermelho,
laranja, azul, roxo).

- Removido o `style` inline do `<section class="stat-grid">` — volta a usar o grid padrão
  (`repeat(auto-fit, minmax(200px,1fr))`), igual a `listClientes` e às outras listagens.
- Trocadas as cores inline dos ícones pelas classes da paleta unificada azul: `b1`, `b2`,
  `b3`, `b4` (mesmo esquema do resto do sistema).
- Markup/PHP dos valores intacto; mudança puramente visual. Conferido em Chrome headless
  (screenshot) com o `global.css` real: 4 cards distribuídos por igual e ícones azuis.
