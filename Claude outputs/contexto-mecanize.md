# Contexto do projeto Mecanize v2 (para continuar em outro chat)

Este documento resume tudo o que sei do projeto e tudo o que foi feito até agora, para que outro chat continue o trabalho sem perder nada.

---

## 1. Quem é o usuário e como trabalhar com ele

- **Asafe** (Asafe Vinícius Lima), dono do projeto. Escreve em português do Brasil. As respostas devem ser em português.
- **Nunca usar travessão (o traço longo)** em nenhum texto: respostas, textos de interface, comentários. Usar ponto, vírgula ou reescrever a frase.
- Prefere respostas **diretas e curtas**. Quando pede uma coisa específica, entregar só aquilo, sem extras.
- É exigente com visual: quer interface **minimalista, sofisticada, intuitiva, com sensação premium**, no estilo Apple. Não gosta de soluções genéricas (ex.: "card à direita + listinha à esquerda").
- Costuma mandar print da tela com a crítica. Quando diz "não altere outros elementos", mexer **só** no que foi pedido.
- Quando ele pede um ajuste pequeno (ex.: "só trazer as margens"), **não reorganizar a estrutura**. Isso já gerou retrabalho uma vez (ver item 9).
- Ele às vezes chama o sistema de "ePipe" por engano (é outro produto dele). Aqui o sistema é o **Mecanize v2**.

---

## 2. O que é o sistema

**Mecanize**: SaaS de gestão de oficina mecânica (TCC/produto). Multi-oficina. Módulos: Início (dashboard), Agenda, Ordens de serviço (orçamento + OS no mesmo registro), Caixa, Financeiro (contas a receber/pagar, comissões), Clientes, Veículos, Peças e serviços, Estoque, Fornecedores, Relatórios, Usuários, Configurações, Plataforma (gestão do SaaS).

Existem duas versões na pasta:
- **v1** (antiga): `mecanize-tcc/app`, `mecanize-tcc/public`, banco `mecanize_db`. **Não mexer.**
- **v2** (atual, onde todo o trabalho acontece): `mecanize-tcc/v2`, banco `mecanize_db_v2`.

---

## 3. Ambiente

- Computador do usuário: Windows, XAMPP em `C:\Users\SENAI_2025\Progs\xampp\htdocs\mecanize-tcc`.
- URL: `http://localhost/mecanize-tcc/v2/public/` (login em `/login`).
- phpMyAdmin: `http://localhost/phpmyadmin` (servidor MySQL 8.0.33).
- `.env` fica na raiz do projeto (`mecanize-tcc/.env`). A v2 usa `DB_NAME_V2` ou, se não houver, `DB_NAME` + `_v2` → `mecanize_db_v2`.
- A raiz do projeto tem `.htaccess` com `Require all denied`; só `v2/public` (e `public` da v1) são servidos pela web.
- CSP da v2: `default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:`. Ou seja: sem fetch para domínios externos (ex.: ViaCEP não funciona).
- Algumas libs da v2 vêm da v1 via `asset()` (`../../public/assets/vendor/imask.min.js` etc.).

---

## 4. Arquitetura da v2

PHP puro (8.x), MVC simples, sem framework.

```
v2/
  app/
    bootstrap.php          carrega .env, autoload (core, models, controllers), Html.php, Erro.php, Contexto.php
    controllers/           AgendaController, CaixaController, ClientesController, ConfiguracoesController,
                           EstoqueController, FinanceiroController, FornecedoresController, InicioController,
                           NotificacoesController, OrdensController, PlataformaController, ProdutosController,
                           RelatoriosController, UsuariosController, VeiculosController, AuthController, AnexosController
    core/                  Banco (1 conexão PDO por requisição), Contexto (usuário/oficina logados), Csrf, Env,
                           Erro, Html (helpers: e(), dinheiro(), data_br(), os_numero(), icone(), asset(),
                           telefone_fmt(), documento_fmt(), so_digitos()), Icones (TRACOS: nomes dos ícones),
                           Licenca, Modelo (base dos models), Paginacao, Permissao, Sessao, Transacao, Validator
    models/                Agendamento, Anexo, Caixa, Cliente, Estoque, Fornecedor, Historico, Lancamento,
                           Notificacao, Oficina, OrdemServico, Painel, Produto, Relatorio, Usuario, Veiculo
    views/                 uma pasta por módulo + layout/ (app.php = layout logado, _avisos.php, _criar.php,
                           _alerta.php, _favicon.php etc.)
  config/rotas.php         'rota' => [Controller, método, nível de acesso]
  config/menu.php          itens da barra lateral
  public/
    index.php              front controller (rota → CSRF → sessão → login → permissão → licença → controller)
    css/mecanize.css       CSS base grande (~246 KB), com camadas antigas (inclusive regras "body.ap ..." não usadas)
    css/identidade.css     camada visual atual (estilo Apple), carregada depois do mecanize.css, usa muito !important
    css/responsivo.css     camada responsiva criada neste trabalho (carregada por último)
    js/campos.js           máscaras e comportamentos de campos
```

Ícones: `icone('nome', tamanho, traço)` gera SVG inline. Nomes disponíveis: home dashboard calendar quote work-order kanban users user car box transfer cash chart tv settings plus edit trash search filter sort download upload print copy external link refresh check close minus more-h more-v chevron-left chevron-right chevron-up chevron-down arrow-left arrow-right menu sidebar-collapse sidebar-expand maximize logout eye eye-off lock plate scan-plate oil tire battery gauge key part checklist clock history location mechanic credit-card qr-code barcode receipt wallet bank trend-up trend-down percent coins info warning error success cancel help bell phone message mail send attach image file folder.

---

## 5. Banco de dados v2 (`mecanize_db_v2`)

Esquema em `database/v2/schema.sql` (17 tabelas, multi-oficina). Toda tabela de dados tem `oficina_id` e FKs compostas `(oficina_id, id)`. Muitos CHECK constraints (status de lançamento coerente com valor pago, movimentos de estoque coerentes com o motivo, dinheiro sempre com caixa, etc.).

Tabelas: oficinas, usuarios, clientes, modelos_veiculos (catálogo global), veiculos, fornecedores, produtos (PECA/SERVICO), ordens_servico, ordem_servico_itens (valor_total gerado), agendamentos, caixas (aberto_unico gerado: 1 caixa aberto por oficina), lancamentos (RECEBER/PAGAR, uma linha por parcela), movimentacoes_financeiras (BAIXA/ESTORNO/SUPRIMENTO/SANGRIA), movimentacoes_estoque (COMPRA/SAIDA_OS/DEVOLUCAO_OS/AJUSTE/ESTORNO), anexos, historico, notificacoes.

Regras de negócio relevantes:
- Status da OS: ORCAMENTO → APROVADA → EM_EXECUCAO ⇄ AGUARDANDO_PECA → PRONTA → ENTREGUE (ou RECUSADA / CANCELADA).
- Faturamento = OS ENTREGUE pelo `entregue_em` (`valor_total`).
- Peça na OS baixa estoque ao iniciar execução; sem saldo, a OS vai para AGUARDANDO_PECA e os admins recebem notificação.
- Item avulso só pode ser serviço; peça tem que vir do catálogo.
- `OrdemServico::atrasada($os)`: tem previsão, não está PRONTA/ENTREGUE/RECUSADA/CANCELADA e a previsão já passou.
- Categorias de lançamento: RECEBER: SERVICOS, OUTRAS_RECEITAS. PAGAR: PECAS, COMISSAO, SALARIO, ALUGUEL, CONTAS_CONSUMO, IMPOSTOS, FERRAMENTAS, OUTRAS_DESPESAS.
- Nenhum usuário tem regra de comissão configurada atualmente.

### Dados de teste (carregados em 17/11/2026)
- Os dados antigos foram apagados e substituídos por dados de teste. **Os 15 logins foram mantidos intactos** (oficina 1, "Oficina Demonstração", plano PRO, ativa até 10/11/2027).
- Usuários: ADMIN 5 Miguel, 6 Pedro Yuri, 8 Asafe Vinícius, 13 Ronald, 42 zz_audit_admin; ATENDENTE 3 Enzo, 11 Laura (inativa), 45, 46, 47; TECNICO 9 Anthony, 10 Adson, 12 Gabriel, 43, 44.
- Carga: 25 clientes, 30 veículos, 6 fornecedores, 42 produtos (28 peças, 14 serviços), 99 OS de maio a novembro/2026, 144 lançamentos, 140 movimentos financeiros, 260 movimentos de estoque, 28 caixas semanais (o da semana atual aberto), 26 agendamentos, histórico completo das OS, notificações de peça faltando.
- **Backup do banco anterior**: `mecanize_db_v2_backup_20261117` (cópia completa das 17 tabelas, no mesmo MySQL).
- Script que gerou os dados: `database/v2/gerar_dados_teste.py` (determinístico) e o SQL `database/v2/dados_teste.sql`.

---

## 6. Identidade visual (seguir sempre)

Estilo Apple: claro e discreto, cápsulas, cantos arredondados, sem sombras pesadas.

Tokens principais (em `identidade.css`, `:root` e `[data-theme="dark"]`):
- Fundo `--bg/--surface #F5F5F7`, cartões `--card #FFFFFF`, `--fill #E8E8ED`, `--fill-forte #D2D2D7`, bordas `--border #D2D2D7`, `--border-subtle #E8E8ED`, `--hairline`.
- Texto `--text #1D1D1F`, `--text-secondary #6E6E73`, `--text-tertiary #86868B`.
- Acento `--acento #0071E3` (botões primários), `--link #0066CC` (#2997FF no escuro).
- Estados: `--ok`, `--perigo`, `--atencao` (+ versões `-fundo`).
- Raios: `--r-card 20px`, `--r-controle 12px`, `--r-chip 980px` (cápsula).
- Curvas: `--ease`, `--ease-saida`. Foco: `--focus-ring`.
- Tema escuro: fundo #191919, navegação #1F1F1F, cartões #303030.
- **Gráficos: cor principal `#1D1D1F`** via `--grafico-1` (no escuro vira #F5F5F7 para aparecer). Paleta de cinzas `--grafico-1..5`, `--grafico-vazio`. Nada de azul em gráficos.
- Fonte Inter (variável). Títulos com `--font-titulo`.

Padrões de componentes:
- Botões de ação do cabeçalho das telas: cápsula só com contorno azul, 40 px de altura; hover preenche de azul com texto branco (estilo "Buy" da Apple).
- Controles segmentados (cápsula cinza com a opção ativa em branco) para alternâncias: Dia/Semana/Mês, Serviço/Peça, OS/Receita, Claro/Escuro/Automático.
- Listas agrupadas estilo Ajustes (rótulo à esquerda, valor à direita) na tela de Configurações.

---

## 7. Camada responsiva (`v2/public/css/responsivo.css`)

Criada para tornar o sistema responsivo sem CSS solto por tela. Pontos de quebra únicos:
- **Celular até 599 px**: menu lateral sobreposto; tabelas viram cartões; janelas (`.dlg`) viram folhas que sobem da base; menus "⋯" viram folha na base.
- **Tablet 600 a 899 px**: menu sobreposto; tabelas em cartões, 2 por fileira; janelas centralizadas.
- **Desktop a partir de 900 px**: barra lateral fixa recolhível.
- Toque (`pointer: coarse`): alvos de 44 px. Campos com 16 px até 899 px (evita zoom do iPhone).

Tabelas em cartões: o layout (`layout/app.php`) tem um JS que lê o cabeçalho e coloca `data-rotulo` em cada célula, marca a coluna principal (`td-principal`, por nome: OS, Nome, Cliente, Descrição…) e a de ações (`td-acoes`), e adiciona `table--cartoes`. Para não virar cartão: `data-sem-cartao` na tabela. Observa mudanças no DOM (MutationObserver).

Validação feita: nenhuma das 36 telas tem rolagem lateral em 375, 414 e 768 px; desktop ficou pixel a pixel igual ao anterior (exceto correções pedidas).

---

## 8. Tudo o que foi feito (em ordem)

1. **Botões de criar** (cabeçalho das telas): altura 32 → 40 px; hover preenche de azul com texto branco.
2. **Gráfico de linha do Início** (depois substituído, ver 18): curvas com degradê, cursor que acompanha a curva.
3. **Dados de teste** no `mecanize_db_v2` (ver seção 5), com backup.
4. **Cores dos gráficos** padronizadas em `#1D1D1F`.
5. **Sidebar**: recolher/expandir suave. O fundo e a linha divisória passaram para a própria barra (o degradê do `.app` não animava). Recolher: a coluna encolhe e os textos esmaecem, depois os ícones se acomodam (técnica FLIP). Expandir: a coluna cresce e os textos aparecem. Classes `sidebar-fechando`, `sidebar-medindo`. JS em `layout/app.php`.
6. **Responsividade de todo o sistema** (seção 7), incluindo a correção da causa do problema da janela de avisos (o `identidade.css` impunha padding 32 px e título 24 px a toda `.dlg`).
7. **Barra da Agenda** (`agenda/lista.php`): uma linha só, tudo com 36 px. Título do período + setas discretas à esquerda; "Hoje" só aparece fora do período atual; Dia/Semana/Mês e um botão "Filtros" (painel com Situação e Técnico, aplica ao escolher, mostra quantos filtros ativos, "Limpar filtros"). No celular usa título curto ("16–22 nov. 2026").
8. **"Atrasada" na lista de OS** (`ordens/lista.php`): na mesma linha da cápsula de situação, com 10 px de respiro, relógio + texto vermelho, tooltip com a previsão. Classes `os-situacao` / `os-situacao__atraso` (já existia `.situacao` no CSS base, não reusar esse nome).
9. **Incluir serviço ou peça na OS** (`ordens/ver.php`): integrado ao cartão de itens. Fechado: linha "+ Incluir serviço ou peça". Aberto: painel com Serviço|Peça (filtra o catálogo; avulso só para serviço; peça mostra estoque), descrição só no avulso, técnico só em serviço, Qtd/Unitário/Desconto/Técnico numa linha, total do item calculado ao vivo, botão Incluir. Layout por container query (largura do próprio painel).
10. **Configurações** (`configuracoes/form.php`) reescrita: uma coluna, **na largura toda do conteúdo** (mesmas margens das outras telas, não estreita e **sem colunas lado a lado**: o usuário pediu explicitamente para ficar tudo um embaixo do outro). Cartão de visita no topo (logo clicável, nome e linha "CNPJ · telefone · cidade/UF" com prévia ao vivo). Listas agrupadas: Oficina, Endereço, Aparência (tema na hora), Assinatura (plano, situação, validade com dias restantes, usuários com barrinha e link). Histórico recolhido. Barra "Alterações não salvas · Descartar · Salvar" só aparece quando algo muda; aviso ao sair sem salvar; validação na própria linha.
11. **Navegação maior**: sidebar com texto 15 px e ícones 20 px, itens 40 px; topo 64 px com Criar/Caixa/sino em 38 px e texto 14,5 px. Em telas baixas (até 940 px de altura) o espaçamento vertical encolhe para o menu caber sem rolar.
12. **Número da OS** não quebra mais em duas linhas na lista.
13. **Avisos (sininho)**: painel não modal (`dialog.show()`), sem véu nem desfoque, página continua ativa. Abre/fecha no sininho, fecha com Esc, X ou clique fora. No celular sobe da base, sem véu.
14. **Gráfico "Fluxo de OS × Receita"** (Início): agora é **matriz de pontos** (inspirada em referência), 30 dias, 10 pontos por coluna, pontos cheios em `--grafico-1`, vazios em `--grafico-vazio`. Alterna "OS abertas | Receita". O número grande do topo mostra o total do período e, ao passar o mouse/tocar numa coluna, o valor daquele dia. `models/Painel.php` passou a buscar 30 dias.
15. **Rosca "Status das OS"**: anel mais grosso (raio 58, espessura 24).
16. **Avisos do Início** (cápsulas): textos curtos com plural correto, uma linha só, rola de lado quando não cabe, borda direita esmaecida só quando há mais.
17. **Barras "Faturamento por mês"** (Início): mesmo estilo de Relatórios (`.barras--pico`): barras de 40 px, maior mês cheio com o valor numa cápsula escura em cima, demais mais claras e cheias no hover.

Seções adicionadas ao `identidade.css` (numeração interna): 19 matriz de pontos, 20 sidebar animada, 21 barra da agenda, 22 situação com atraso, 23 incluir item na OS, 24 configurações, 25 navegação maior, 26 avisos sem véu, 27 barras com destaque.

---

## 9. Lições e cuidados

- **Ao salvar arquivos no computador do usuário**, a ferramenta de envio às vezes reaproveitava uma cópia antiga quando o arquivo de saída tinha o **mesmo nome** de um envio anterior. Isso fez a tela da OS chegar sem estilo uma vez. Solução adotada: copiar para um **nome novo a cada envio** (ex.: `identidade-v13.css`) e, depois de salvar, **baixar de volta e comparar byte a byte** com a versão testada.
- Não executar a cópia e o envio na mesma leva de comandos paralelos (o envio pode pegar o arquivo antes da cópia terminar).
- `identidade.css` usa muito `!important`; para sobrescrever, usar especificidade maior (ex.: `:root input…`) ou `!important` com seletor mais específico.
- Regra global de foco desenha caixa azul em inputs; em componentes "sem caixa" (como as linhas das Configurações) é preciso anular com `!important`.
- Classe `.situacao` já existe no CSS base (cápsula com ponto): não reutilizar.
- `.dlg` recebe padding/título grandes do `identidade.css`; janelas especiais precisam de exceção.
- Testes foram feitos numa cópia local (MariaDB + `php -S` + Playwright) com o mesmo esquema e os mesmos dados de teste; o acento quebrado "VinÃcius" visto em prints é só dessa cópia local, não do banco real.
- O usuário pede para o resultado ser conferido de verdade (interações, não só visual).

---

## 10. Arquivos alterados neste trabalho (todos dentro de `v2/`)

- `public/css/identidade.css` (várias seções novas, ver item 8)
- `public/css/responsivo.css` (novo)
- `app/views/layout/app.php` (link do responsivo.css, JS da sidebar animada, menu móvel, rotulagem das tabelas, menus "⋯" na borda, avisos não modais)
- `app/views/inicio/painel.php` (matriz de pontos, rosca, avisos, barras)
- `app/models/Painel.php` (fluxo de 30 dias)
- `app/views/agenda/lista.php` (barra do período)
- `app/views/ordens/lista.php` (atraso ao lado da situação)
- `app/views/ordens/ver.php` (incluir serviço ou peça)
- `app/views/configuracoes/form.php` (tela reescrita; a antiga não foi guardada no projeto)

Também na pasta:
- `database/v2/dados_teste.sql` e `database/v2/gerar_dados_teste.py` (dados de teste).

---

## 11. Pendências e pontos de atenção

- **Arquivos temporários para o usuário apagar** (não consegui excluir pelo acesso que tinha):
  - `v2/public/_seed_tmp_8f3a.sql` (já esvaziado)
  - `database/v2/_ensaio_dados_teste.sql`
- O backup `mecanize_db_v2_backup_20261117` pode ser apagado quando o usuário tiver certeza de que não precisa dos dados antigos.
- Depois de qualquer mudança de CSS, o usuário precisa dar Ctrl+F5 (os arquivos têm `?v=filemtime`, mas o cache às vezes segura).
- `mecanize.css` tem muitas camadas antigas e pontos de quebra espalhados (520, 560, 640, 700, 720, 860, 900, 960, 1100). A referência agora é `responsivo.css`; uma limpeza do `mecanize.css` seria um trabalho futuro.
- Telas que ainda não passaram por redesenho dedicado (só pela camada responsiva): Caixa, Financeiro, Comissões, Estoque, Fornecedores, Produtos, Veículos, Clientes, Usuários, Relatórios, Plataforma, telas de login.
