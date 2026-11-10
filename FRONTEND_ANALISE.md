# Mecanize — Análise do front-end

Resumo do front-end atual e lista dos pontos que fazem o sistema parecer amador, com o que fazer em cada um.

**Como foi feita:** leitura do código no último commit (HEAD), com contagens nas views, no CSS e no JS. Não foi feita inspeção visual no navegador. Os itens que dependem de ver a tela estão marcados como **a conferir**.

---

## 1. Resumo do front-end atual

### 1.1 Stack

| Camada | O que é usado |
|---|---|
| Renderização | PHP no servidor (MVC próprio), HTML montado nas views |
| CSS | `global.css` (77 KB, ~3.200 linhas), `sidebar.css` (27 KB), `erros.css` (17 KB) |
| JS próprio | `masks.js`, `select-enhance.js`, `cor-select.js`, `text-format.js`, `escape.js`, `csrf.js`, `kanban.js`, `assinatura.js`, `erros.js` |
| Bibliotecas (locais, em `public/assets/vendor/`) | Chart.js (205 KB), IMask (59 KB), Tom Select (51 KB + CSS), Bootstrap Icons (98 KB de CSS), fonte Inter |
| Ícones | SVG inline + Bootstrap Icons (dois sistemas ao mesmo tempo) |
| Framework de front | Nenhum (JS puro) |

### 1.2 O que já está bom

- **Tokens de design** em `:root`: cores, raios, sombras, tipografia, em `global.css`.
- **Modo escuro** completo por `data-theme="dark"`, aplicado antes do primeiro desenho para não piscar.
- **Bibliotecas locais**, sem CDN: o sistema funciona sem internet.
- **Componente de alerta** (`layout/_alerta.php`) com ícone por tipo, para não depender só de cor.
- **Telas de erro** com identidade própria (`Site/erros/*`).
- **CSRF** injetado automaticamente em todo POST (`csrf.js`).
- **Responsividade existe:** 15 media queries (480–1100 px) e as tabelas ficam dentro de `.table-wrap` com `overflow-x: auto`.
- **Componente de modal** (`.modal-overlay`, `.modal__header` etc.) já existe e é usado, por exemplo, em Categoria.
- **Paginação** presente nas listas principais (ex.: clientes).

### 1.3 Estrutura visual

- **Shell:** sidebar recolhível à esquerda (largura 240 px) + topbar de 60 px com indicador do caixa e botão de tema. No celular a sidebar vira navegação inferior (`mob-nav`).
- **Padrão de tela de lista:** cabeçalho com título, subtítulo e botões de ação → cards de métricas (`stat-card`) → barra de busca e filtros → abas (Ativos/Inativos) → tabela → paginação.
- **Padrão de tela de formulário:** seções em cards, campos com máscara (IMask), selects com busca (Tom Select), botão primário e cancelar.
- **Telas especiais:** kanban de OS, painel VIP de clientes (com slides internos), agenda, dashboard com gráficos, impressão/PDF de orçamento, OS e caixa.
- **Telas públicas** (`painel`, `rastreio`, `termos`, `privacidade`) têm seu próprio `<html>` e `<head>`, fora do layout principal.

### 1.4 Números do código (views)

| Medida | Valor |
|---|---|
| Atributos `style="..."` inline | **1.295** |
| Blocos `<script>` inline | 59 |
| Blocos `<style>` inline | 19 |
| `onclick=` inline | 103 |
| `onchange=` / `oninput=` inline | 28 / 21 |
| `alert()` nativos | **10** |
| `confirm()` nativos | **22** |
| `!important` em `global.css` | 114 |
| `<label>` / com `for=` | 281 / **14** |
| `autocomplete=` | 2 |
| `aria-live`, `role="dialog"`, `aria-modal` | **0** |
| `prefers-reduced-motion` | **0** |
| `outline: none` / `:focus-visible` no CSS | 7 / 2 |
| Skeleton / spinner / toast | **0** |

Telas com mais estilo inline (por arquivo): `Orcamento/saveOrcamento.php` 163, `Ordens_Servico/saveOrdemServico.php` 162, `Ordens_Servico/editOrdemServico.php` 130, `Documento/listDocumentos.php` 65, `Ordens_Servico/viewOrdemServico.php` 62, `Orcamento/listOrcamentos.php` 50, `Caixa/movimentacoesCaixa.php` 50, `Documento/saveDocumento.php` 43.

Maiores arquivos de view: `saveOrdemServico` 72 KB, `saveOrcamento` 68 KB, `editOrdemServico` 59 KB, `listVeiculos` 41 KB, `listOrcamentos` 37 KB.

---

## 2. O que dá cara de amador

Prioridade: **P1** o usuário nota na hora · **P2** estrutura que gera inconsistência e bug · **P3** polimento.

### P1 — O usuário percebe imediatamente

#### 2.1 `alert()` e `confirm()` nativos (10 + 22)

- A caixa cinza do navegador mostra o endereço do site no título e não segue o tema escuro nem a identidade visual.
- Exemplos de `alert`: `Veiculos/saveVeiculo.php` (leitura de placa), `Documento/listDocumentos.php`, `MovimentacaoEstoque/saveMovimentacaoEstoque.php`.
- Exemplos de `confirm`: exclusão de categoria, desativar/reativar cliente, cancelar/excluir agendamento, fechar caixa.
- **Contexto:** o sistema já tem componente de modal; os `confirm` simplesmente não o usam.
- **Correção:** criar um `confirmar()` único (modal + promessa) e trocar os 22 `confirm`; trocar os 10 `alert` por toast ou alerta inline.

#### 2.2 Mensagens de desenvolvedor na interface

- `'O PHP não retornou JSON válido. Veja o console do navegador.'`
- `'Erro ao conectar com a rota ./lerPlaca.'`
- `'Campo de imagem não encontrado.'` / `'Campo de placa não encontrado.'`
- **Correção:** mensagem em linguagem do usuário ("Não consegui ler a placa. Tente outra foto.") e o detalhe técnico só no `console.error`.

#### 2.3 Sem toast e sem feedback padronizado de sucesso

- Salvar, excluir, abrir e fechar caixa não confirmam visualmente de forma padronizada.
- **Correção:** componente de toast (sucesso, erro, aviso) lido de `$_SESSION['flash']` no layout e também chamável pelo JS.

#### 2.4 Sem estados de carregamento nem proteção contra clique duplo

- Nenhum spinner, skeleton nem botão desabilitado durante o envio. A busca por `grep` em views e JS não achou nenhum bloqueio de submit; os `disabled` encontrados são de campos de formulário.
- Há 13 chamadas `fetch()` (placa, kanban, buscas) sem indicador de espera.
- **Risco real:** duplo clique em "Salvar" pode gerar orçamento, OS ou movimentação de caixa duplicada.
- **Correção:** handler global no `submit` que desabilita o botão, troca o texto por "Salvando…" e mostra spinner; classe `.is-loading` nos botões que disparam `fetch`.

#### 2.5 Título da aba igual em todas as telas internas

- `layout/header.php` tem `<title>Mecanize</title>` fixo. Com várias abas abertas, tudo se chama "Mecanize".
- As telas públicas e os PDFs já têm título próprio; as internas não.
- **Correção:** `$tituloPagina` definido pelo controller e `<title><?= $tituloPagina ?> · Mecanize</title>`.

#### 2.6 Favicon que troca depois do carregamento

- O header define `logo-branco.ico`; o JS do rodapé remove e cria outro `<link rel="icon">` conforme o tema do navegador. Resultado: a aba pisca, e para quem não tem JS ativo o ícone fica errado.
- A logo da sidebar é o arquivo `mecanize.ico` usado como `<img>` (`header.php:84`), formato pensado para favicon, não para imagem.
- **Correção:** dois `<link rel="icon" media="(prefers-color-scheme: ...)">` no `<head>` (sem JS) e uma logo em SVG/PNG para a sidebar.

#### 2.7 A cor de "sucesso" é azul

- `--color-success: #003BDD` é igual a `--color-primary`. Status "pago", "aprovado" e "concluído" ficam da mesma cor do botão e do link, sem verde.
- No escuro acontece o mesmo (`#4D94FF`).
- **Correção:** criar um verde de sucesso real (com `-soft` e versão escura) e revisar badges de status.

#### 2.8 Ícones misturados e caractere como ícone

- SVG inline copiado em cada tela, Bootstrap Icons (98 KB de CSS) e caracteres como `★` (painel VIP).
- Traços (1.75 e 2) e pesos diferentes entre ícones do mesmo menu.
- **Correção:** escolher um conjunto único (Bootstrap Icons já está carregado) ou um sprite SVG, e padronizar o traço.

### P2 — Estrutura que gera inconsistência e dificulta manutenção

#### 2.9 Estilo inline em massa (1.295 `style=`)

- Cada tela fica com espaçamento, cor e tamanho levemente diferentes. Cores aparecem como `style="color:var(--color-danger)"` em cada asterisco de campo obrigatório.
- **Correção:** classes utilitárias e componentes (`.field__required`, `.stack`, `.row`, `.tabs`) e atacar primeiro `saveOrcamento`, `saveOrdemServico` e `editOrdemServico`.

#### 2.10 Telas gigantes que misturam PHP, HTML, CSS e JS

- Views de 40–70 KB com 59 `<script>` e 19 `<style>` inline. Difícil de ler, de testar e de reaproveitar.
- **Correção:** mover o JS de cada tela para `assets/js/<tela>.js` e o CSS específico para `assets/css/`, quebrando as telas em parciais (`_itens`, `_totais`, `_cabecalho`).

#### 2.11 Comportamento espalhado em atributos inline

- 103 `onclick=`, 28 `onchange=`, 21 `oninput=`. Isso também força CSP permissiva (impede bloquear scripts inline no futuro).
- A confirmação de exclusão é reescrita em cada formulário.
- **Correção:** `data-confirmar="Mensagem"` no formulário/botão + um único listener global.

#### 2.12 CSS com 114 `!important` e arquivo único

- Indica briga de especificidade. O `global.css` mistura design system, componentes e estilos de páginas específicas.
- **Correção:** dividir em `tokens.css`, `components.css` e `pages/<tela>.css`; remover os `!important` à medida que as telas saem do inline.

#### 2.13 Cache de assets desligado

- `header.php` usa `?v=<?= time() ?>` em cada CSS e JS: o navegador baixa tudo de novo a cada carregamento de página, inclusive 205 KB de Chart.js.
- **Correção:** `?v=<?= filemtime($arquivo) ?>` ou uma constante de versão do release.

#### 2.14 Tudo carregado em toda página

- Chart.js, Tom Select, IMask e Bootstrap Icons vêm em todas as telas, inclusive formulários simples e login.
- **Correção:** `$scripts = [...]` por tela, carregando Chart.js só nas telas com gráfico.

#### 2.15 Modais sem acessibilidade e painéis feitos "à mão"

- O componente `.modal` existe, mas não tem `role="dialog"`, `aria-modal`, foco inicial, retorno de foco nem fechar com `Esc`.
- O painel VIP usa `position: fixed` + `getBoundingClientRect` para se ancorar no card (o próprio comentário do código descreve isso como contorno de um bug de empilhamento).
- **Correção:** um único módulo de modal/popover reaproveitado por todas as telas.

#### 2.16 Layouts duplicados nas telas públicas

- `painel`, `rastreio`, `termos` e `privacidade` têm `<head>` próprio. Mudar a identidade visual exige editar cada arquivo.
- **Correção:** um layout "público" separado do layout interno.

### P3 — Acessibilidade e polimento

#### 2.17 Labels sem associação aos campos

- 281 `<label>` e só 14 com `for=`. No `saveCliente.php`, por exemplo, o `<label>` é irmão do campo, sem `for` nem aninhamento. Clicar no texto não foca o campo e leitor de tela não anuncia o rótulo.
- **Correção:** `for`/`id` em todo campo (idealmente por um helper PHP `campo()` que gera os dois).

#### 2.18 Foco de teclado

- 7 `outline: none` contra 2 `:focus-visible` no CSS: parte dos controles fica sem indicação de foco.
- Cards clicáveis (`stat-card--clickable`) têm `role="button"`, mas só 4 pontos no total usam `tabindex`.
- **Correção:** `:focus-visible` global com anel de foco visível; revisar cada `outline: none`.

#### 2.19 Mensagens dinâmicas não anunciadas

- Zero `aria-live`. Erros de validação, resultados de busca e leitura de placa aparecem sem aviso a leitores de tela.
- **Correção:** `role="alert"` no componente de alerta e `aria-live="polite"` na área de toasts.

#### 2.20 Animações sem opção de redução

- Sem `prefers-reduced-motion`. Sidebar, painéis, kanban e transições sempre animam.

#### 2.21 Campos de formulário sem atributos de entrada

- `autocomplete=` em só 2 campos (faltam login, e-mail, telefone, endereço).
- `inputmode` em 9 campos: valores e documentos em geral não abrem o teclado numérico no celular.
- **Correção:** `autocomplete`/`inputmode` nos campos conforme o tipo (o helper `campo()` do item 2.17 também resolve isso).

#### 2.22 Pequenas incoerências de produto

Já mapeadas em `RESUMO_TELAS.md`:

- O rastreio diz "atualizado automaticamente", mas não há atualização automática.
- Depois de redefinir a senha, o login recebe `?senhaRedefinida=1` e não mostra mensagem.
- O formulário de usuário não repreenche os campos depois de erro de validação.
- A lista de veículos filtra só no JS, sobre os itens já carregados.
- O `SiteController` tem métodos sem rota nem view.
- `lang` está como `pt-br` em uns arquivos e `pt-BR` em outros (cosmético).

---

## 3. A conferir com a tela aberta

Itens que o código sugere mas que só se confirmam vendo o sistema:

1. Celular (≈ 400 px): `saveOrcamento`, `saveOrdemServico` e o kanban em tela estreita.
2. Contraste do `--color-text-muted` (`#94A3B8` sobre branco) e do cinza do modo escuro (`#6E7B8E`), que pode ficar abaixo de 4,5:1 em textos pequenos.
3. Tabelas largas (OS, orçamentos, caixa): se o scroll horizontal fica óbvio ou escondido.
4. Piscada de tema ao trocar de página e piscada do favicon.
5. Densidade: se os 1.295 estilos inline geraram diferenças visíveis de espaçamento entre telas parecidas.

---

## 4. Plano sugerido

Ordem por impacto sobre esforço.

| # | Entrega | Itens | Esforço |
|---|---|---|---|
| 1 | Componente de confirmação (modal) e de toast; trocar 32 `alert`/`confirm`; mensagens em linguagem de usuário | 2.1, 2.2, 2.3 | Médio |
| 2 | Bloqueio de duplo envio + estado de carregamento global | 2.4 | Baixo |
| 3 | `<title>` por tela, favicon sem JS, logo em formato correto | 2.5, 2.6 | Baixo |
| 4 | Verde de sucesso + revisão dos badges de status | 2.7 | Baixo |
| 5 | `filemtime` no cache e carregamento condicional de bibliotecas | 2.13, 2.14 | Baixo |
| 6 | Helper de campo (`label for`, `autocomplete`, `inputmode`, obrigatório) | 2.17, 2.21 | Médio |
| 7 | `:focus-visible`, `aria-live`, `role="dialog"` no modal, `prefers-reduced-motion` | 2.15, 2.18–2.20 | Médio |
| 8 | Padronizar ícones | 2.8 | Médio |
| 9 | Tirar `style=` e JS inline das 3 telas maiores (orçamento e OS) e separar em arquivos | 2.9–2.11 | Alto |
| 10 | Dividir o CSS e remover `!important`; layout público único | 2.12, 2.16 | Alto |

Os itens 1 a 5 são os que mais mudam a percepção de "sistema profissional" com menos código.

---

## 5. Observação sobre o estado do repositório

Na hora desta análise a pasta `app/` estava vazia no disco e o git mostrava todos os arquivos dela como deletados. Tudo acima foi lido do commit mais recente (HEAD). Qualquer alteração não commitada que existisse em `app/` não está contemplada.
