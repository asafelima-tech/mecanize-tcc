# Melhorias — 09/07/2026

Documentado no mesmo espírito de `comentarios.md` e `MELHORIAS_2026-07-08.md`.

## 1. Redesign dos documentos em PDF (Ordem de Serviço e Orçamento)

Pedido do dono do sistema: os PDFs de OS concluída e de Orçamento deveriam
conter todos os campos de um documento de referência (foto de um sistema
concorrente — OS N°, atendente, CPF/CNPJ e endereço do cliente, marca/
modelo/ano/cor/placa/km do veículo, desconto por item, subtotal, desconto
geral, total geral, termos de garantia), mas com a identidade visual do
Mecanize — não uma cópia do visual do concorrente.

- **Models (`app/models/OrdemServico.php` e `app/models/Orcamento.php`):**
  `getById()` de ambos passou a trazer, além do que já existia, `cpf`/`cnpj`/
  `tipo_pessoa` e endereço completo (`endereco`, `bairro`, `cidade`, `uf`,
  `cep`) do cliente, e `ano`/`cor`/marca/modelo do veículo separados (antes
  só existia um campo `modelo`/`veiculo_modelo` já concatenado). Todos esses
  campos já existiam nas tabelas `clientes`/`veiculos` — só não eram
  selecionados nas queries.
- **Templates redesenhados** (`app/views/Ordens_Servico/pdfOrdemServico.php` e
  `app/views/Orcamento/pdfOrcamento.php`): visual novo usando os tokens de cor
  do design system (`#003BDD`, `#F8FAFC`, `#E2E8F0`, raios de borda — os
  mesmos de `public/assets/css/global.css`), com faixa de gradiente no topo,
  marca "M" em quadrado com gradiente (mesmo estilo do logo da sidebar),
  cards de Cliente/Veículo com cantos arredondados, tabela de itens com
  cabeçalho tintado, e o Total Geral em destaque num bloco azul sólido.
  Continua tudo em tabelas/`display:table` (Dompdf não suporta flexbox/grid)
  e cores em hexadecimal literal (Dompdf não suporta `var(--cor)`) — mesma
  limitação já documentada no template anterior.
- **Campos novos exibidos:** Atendente/Responsável (mapeado pro técnico já
  associado à OS/orçamento — o sistema não tem um campo "atendente"
  separado), CPF/CNPJ e endereço completo do cliente, Marca/Modelo/Ano/Cor
  do veículo em campos próprios (antes só apareciam concatenados), coluna
  "Desconto Unit." na tabela de itens, e linhas "Subtotal"/"Desconto" nos
  totais, além de "Total Geral" em destaque.
- **Desconto sempre R$ 0,00 — decisão consciente:** o sistema não tem
  nenhuma coluna de desconto em lugar nenhum do banco (nem por item, nem
  geral, em nenhuma das tabelas de OS/orçamento). Em vez de inventar um
  cálculo, o template já lê `$item['desconto']` (com fallback pra `0`), então
  a estrutura visual já está pronta — quando/se o sistema ganhar um campo de
  desconto de verdade, os PDFs já vão exibir o valor certo sem precisar mexer
  no template de novo.
- **Termos de Garantia:** texto novo, fixo no template (o sistema não tinha
  nenhum texto de garantia em lugar nenhum) — 90 dias corridos, restrito a
  mão de obra e peças listadas, com as exclusões usuais (intervenção de
  terceiros, uso indevido, líquidos, picos de energia). No orçamento, o texto
  deixa claro que a garantia só passa a valer depois de aprovado e executado.
- **Testado:** renderização via PHP CLI + Dompdf com dados simulados
  (baseados na OS de referência da imagem, incluindo os mesmos valores —
  Total Geral bateu R$ 3.451,50 igual ao documento de referência). Nenhum
  warning/notice/erro no HTML gerado, PDFs de ambos os templates gerados com
  sucesso.

## 2. Correção: tela "Documentos" (Serviços Finalizados) não usava o PDF novo

O dono do sistema reportou que, ao clicar em "Baixar PDF" depois de concluir
uma OS, aparecia uma tela de impressão do navegador com um recibo simples e
antigo ("SuperSpeed Oficina Mecânica", colunas Item/Tipo/Qtd/Unit./Subtotal),
em vez do PDF redesenhado na seção 1.

- **Causa raiz:** a tela `./listDocumentos` (menu "Documentos", ADMIN-only —
  `DocumentoController` + `app/views/Documento/listDocumentos.php`) é um
  fluxo **completamente separado e 100% client-side**, que nunca chamava o
  Dompdf. O botão "Baixar PDF"/"Imprimir" só fazia `window.open('', '_blank')`
  + `document.write()` de um HTML hardcoded (cabeçalho "SuperSpeed" fixo,
  sem usar `EMPRESA_NOME`) + `window.print()` — por isso o título da aba
  virava "Orçamento OS-0003" (a palavra "Orçamento" vinha fixa no template,
  concatenada com o número da OS).
- **Correção:** `baixarPDF()` e `imprimirDocumento()` (em `listDocumentos.php`)
  agora apontam pra rota real `./exportOrdemServicoPdf?id=X` (a mesma que já
  gera o PDF redesenhado com Dompdf) — "Baixar PDF" força download, "Imprimir"
  abre o PDF numa aba nova pro usuário imprimir pelo visualizador nativo do
  navegador. O bloco de "Prévia do Documento" (que também era um HTML
  hand-rolled, sujeito a ficar desatualizado) virou um `<iframe>` carregando
  o mesmo PDF real — não existe mais um segundo template pra manter em
  sincronia com o de verdade.
  - **`app/core/Pdf.php`:** `Pdf::baixar()` ganhou um 3º parâmetro opcional
    `bool $attachment = true` — `false` faz o PDF abrir inline no navegador
    em vez de forçar download (usado pelo "Imprimir" e pelo iframe de
    prévia). `OrdemServicoController::exportarPdf()` repassa isso via
    `?inline=1` na URL.
- **Bug real encontrado durante o teste (bloqueava o "Baixar PDF" de verdade,
  não só o antigo):** ao testar a rota nova com uma OS de verdade que tinha
  assinatura do cliente salva (ex.: a própria OS-0003 do exemplo acima), o
  Dompdf **quebrava com erro fatal** — "The PHP GD extension is required,
  but is not installed" — porque a extensão `gd` estava comentada
  (`;extension=gd`) no `php.ini` do XAMPP. Ou seja, mesmo se o botão já
  estivesse apontando pro PDF certo, o download quebraria pra qualquer OS/
  orçamento com assinatura salva (a imagem PNG embutida no PDF depende do
  GD). Corrigido: `extension=gd` descomentada em
  `C:\xampp\php\php.ini` e Apache reiniciado — confirmado com um script de
  teste (`extension_loaded('gd')`) rodando através do próprio Apache antes
  de remover.
- **Testado de ponta a ponta com dado real do banco:** renderizei
  `pdfOrdemServico.php` com a OS-0003 de verdade (cliente Roberto, CPF, Toyota
  Hilux 2025 branca, assinatura salva, 6 itens) direto pelos models — PDF de
  20KB gerado com sucesso, sem erro, com a assinatura embutida corretamente.

## 3. Validação de celular no cadastro de cliente (protege o envio por WhatsApp)

Pedido do dono do sistema: telefone de cliente precisava de máscara + uma
validação que bloqueie número "que não existe", pra não quebrar o botão
"Enviar WhatsApp" (que já existia — manda link `wa.me` pro cliente quando a
OS é concluída ou o orçamento é aprovado, ver `app/core/WhatsApp.php`).

- **Decisão alinhada com o dono antes de implementar:** ele cogitou usar uma
  lib não-oficial (Baileys/whatsapp-web.js) pra confirmar de verdade se o
  número tem WhatsApp ativo. Expliquei que isso exigiria rodar um serviço
  Node.js 24/7 pareado via QR code com o WhatsApp real da oficina, e que
  checar em lote todos os clientes já cadastrados (como ele também pediu) é
  exatamente o padrão de uso que mais derruba números comerciais — o que
  reverteria a decisão já tomada antes (seção 7 de `MELHORIAS_2026-07-08.md`)
  de manter o WhatsApp só como link assistido, sem automação, pra não
  arriscar o número real da oficina. Ele concordou em seguir só com
  **validação de formato/DDD** (sem custo, sem risco, 100% local).
- **Máscara:** já existia! `saveCliente.php` e `editCliente.php` usam
  `id="telefone"`, que já é pego automaticamente por
  `public/assets/js/masks.js` (IMask, carregado globalmente) — nada a fazer
  aí.
- **Validação nova — `Validator::celularBr()`** (`app/core/Validator.php`):
  método encadeável novo, mesmo padrão dos outros (`email()`, `min()` etc.).
  Campo opcional (só valida se algo foi digitado — não obriga todo cliente a
  ter celular, como o "Cliente Balcão" genérico). Quando preenchido, exige:
  - Exatamente 11 dígitos (DDD + celular com o 9º dígito) — rejeita telefone
    fixo (8 dígitos) porque fixo não tem WhatsApp.
  - DDD dentro da lista real de DDDs atribuídos pela Anatel (rejeita `(00)`,
    `(20)`, `(90)` etc., que não existem).
  - Terceiro dígito (logo após o DDD) tem que ser `9` — é a marca de celular
    no plano de numeração brasileiro.
  - Rejeita número com todos os dígitos iguais (ex.: `(18) 99999-9999`),
    padrão clássico de cadastro "preenchido só pra passar".
  - **Não confirma que o número tem WhatsApp de verdade** (só uma API
    paga/oficial faria isso) — barra só o que é format­almente impossível ou
    obviamente falso.
  - Testado com 11 casos (números reais de várias regiões, fixo, DDD
    inválido, repetido, incompleto, lixo) — todos passaram como esperado.
- **Ligado em `ClienteController::save()` e `edit()`**, encadeado antes da
  checagem de duplicidade já existente (`telefoneExisteAtivo`) — se o formato
  for inválido, essa mensagem aparece primeiro (mais útil que "já existe um
  cliente com este telefone" quando o problema real é o formato).
- **Fora do escopo, mas documentado:** o campo "Telefone" da tela de perfil
  do usuário (`app/views/Usuarios/perfil.php`, id `campoTelefone`) já tem
  máscara própria, mas é **decorativo** — `UserController::perfil()` nunca lê
  `$_POST['telefone']` e a tabela `usuarios` não tem essa coluna. Não mexi
  nisso porque não é o campo usado pro envio de WhatsApp ao cliente (é
  perfil do funcionário) e implicaria criar coluna nova + fluxo de
  persistência, fora do que foi pedido.
- **Auditoria dos clientes já cadastrados** (rodada com um script direto no
  banco, reaproveitando o `Validator` novo): dos 4 clientes cadastrados hoje,
  **1 tem telefone inválido** — cliente **#3, Roberto**, telefone salvo como
  `(12) 31231-2312` (começa com `3`, não `9` — não é celular, formato errado
  pra WhatsApp funcionar). Precisa ser corrigido manualmente com o número
  certo do cliente, já que não tem como eu adivinhar o valor correto. Os
  outros 3 estão OK: 2 com celular válido, 1 (Cliente Balcão, o cliente
  genérico de balcão) sem telefone — o que é esperado, não é uma pessoa
  real.

## 4. Corrigido: campo "Telefone" do perfil do usuário era decorativo

Puxando o fio da seção 3 — perguntei se valia a pena consertar o campo
"Telefone" da tela de perfil (`app/views/Usuarios/perfil.php`), que existia
na tela mas nunca era salvo (a tabela `usuarios` não tinha essa coluna e
`UserController::perfil()` nunca lia `$_POST['telefone']`). Confirmado que
sim.

- **`database/add_telefone_usuario.php`** (mesmo padrão de
  `add_assinatura_cliente.php`/`add_token_rastreio.php`): adiciona
  `telefone VARCHAR(20) NULL` em `usuarios`. Rodado com sucesso.
- **`User::update()`** (`app/models/User.php`): passou a gravar `telefone`
  usando `COALESCE(?, telefone)` — o mesmo idioma já usado em
  `OrdemServico::concluir()` pra `assinatura_cliente`. Isso importa porque
  `update()` é chamado por **4 fluxos diferentes** (perfil, admin editando
  outro usuário, troca de senha, redefinição por token) e só o de perfil
  manda telefone; com `COALESCE`, os outros 3 continuam funcionando
  exatamente como antes, sem apagar um telefone já salvo.
- **`UserController::perfil()`**: passou a ler `$_POST['telefone']` e validar
  com o mesmo `Validator::celularBr()` criado na seção 3 (opcional — só
  bloqueia se tiver algo digitado e for inválido).
- **Testado diretamente no banco:** `update()` sem a chave `telefone`
  preserva o valor existente; `update()` com telefone novo grava
  corretamente; usuário de teste restaurado ao estado original (`NULL`)
  depois do teste.

## 5. Melhorias no cadastro/listagem de placa (drag-and-drop + visual Mercosul)

Pedido do dono do sistema, com 3 partes:

1. **Arrastar a foto da placa, não só clicar** — `app/views/Veiculos/saveVeiculo.php`
   (área "Foto da placa", só existe na tela de criação — `editVeiculo.php` não
   tem upload/OCR, só o campo de texto). Adicionado `dragenter`/`dragover`/
   `dragleave`/`drop` na mesma área tracejada que já era clicável (`label`
   com `for="fotoPlaca"`), reaproveitando a função `aoSelecionarFotoPlaca()`
   que já existia (mesmo caminho de preview + botão "Ler placa pela foto" via
   OCR do PlateRecognizer, `PlacaController::ler`). Solta um arquivo que não
   é imagem → alerta, não tenta processar. Também travei o `dragover`/`drop`
   no `document` fora da área, pra evitar que o navegador abra a imagem numa
   aba nova se o usuário errar o alvo. Texto do rótulo atualizado de "clique
   para anexar ou fotografar" pra "arraste a imagem, clique para anexar ou
   fotografe".
2. **Máscara antiga vs Mercosul ao digitar** — **já existia e já funcionava**
   (não precisei mexer): `public/assets/js/masks.js` usa duas máscaras IMask
   (`aaa-0000` / `aaa0a00`) que alternam sozinhas conforme o 5º caractere
   digitado (dígito → antiga, letra → Mercosul), e `saveVeiculo.php` já tinha
   a mesma lógica (`formatarPlaca()`) pro preenchimento automático via OCR.
   Confirmei isso lendo o código e também vendo o HTML renderizado (`id=
   "placa"` + `IMask` + `masks.js` presentes na página).
3. **Desenho da placa diferente na listagem (antiga vs Mercosul)** —
   `app/views/Veiculos/listVeiculos.php` + `public/assets/css/global.css`.
   Primeira tentativa só trocava um ícone pequeno (bandeira ↔ emblema
   Mercosul) dentro da mesma faixa azul — o dono do sistema apontou que
   ficou parecido demais entre os dois tipos e mandou duas fotos reais de
   referência (placa cinza antiga e placa Mercosul de verdade). Redesenhei
   pra ficar bem mais parecido com a realidade:
   - **Modelo antigo** (`.plate-br--antiga`): virou só a chapa cinza com
     efeito metálico e o texto em relevo (`text-shadow` simulando o baixo-
     relevo de placa de metal) — **sem faixa nenhuma no topo**, igual à
     foto de referência.
   - **Mercosul** (`.plate-br--mercosul`): manteve a faixa azul "BRASIL" +
     emblema de 4 estrelas + bandeira do Brasil no canto (isso já existia),
     e ganhou uma **linha nova embaixo do número** com um selo estilo QR
     code (padrão xadrez em CSS puro) e a sigla "BR" — os dois detalhes que
     mais saltam aos olhos na placa Mercosul real e que a versão antiga não
     tem.
   - Detecção de formato continua em PHP (regex
     `^[A-Z]{3}[0-9][A-Z][0-9]{2}$` = Mercosul, resto = antiga). Testado com
     os 4 veículos reais do banco: `ABC-1234`, `ABC-1237` e `CMG-3164`
     (antiga, chapa cinza sem faixa) e `LND9A71` (Mercosul, faixa completa +
     QR) — confirmado via HTML renderizado.
   - **Segunda rodada — trocado CSS puro por SVG inline:** o dono do sistema
     viu essa primeira versão (print real da tela) e achou "muito básico,
     parece que foi feito no Paint". Reescrevi `renderPlacaSvg()` em
     `app/views/Veiculos/listVeiculos.php` pra desenhar cada placa como um
     `<svg>` gerado no PHP (proporção 300x100 ≈ à real placa brasileira,
     400x130mm) em vez de `<span>`s com CSS. Isso permite:
     - **Relevo metálico de verdade na placa antiga**, via filtro SVG
       `feSpecularLighting` (a técnica padrão de "texto gravado em metal" —
       luz vindo de um ângulo, gerando destaque/sombra reais no relevo das
       letras), não um `text-shadow` chapado.
     - **Detalhes nítidos na Mercosul** (emblema de estrelas, bandeira,
       selo de QR code) como formas vetoriais, sem gradiente CSS borrado.
     - `public/assets/css/global.css`: o CSS da placa encolheu pra só
       cuidar do tamanho e da sombra de "objeto físico" (`filter:
       drop-shadow(...)` no wrapper, que acompanha o contorno real da
       placa — diferente de `box-shadow`, que seria sempre retangular).
     - **Testado renderizando de verdade:** como esta máquina não tem
       Playwright/`chromium-cli`, usei o Chrome já instalado em modo
       `--headless --screenshot` (sem precisar de Node) pra tirar um
       screenshot real da prévia isolada, e ajustei a espessura da borda
       (estava fraca demais) depois de ver o resultado. Publiquei essa
       prévia como um artifact
       (https://claude.ai/code/artifact/0fa3fdeb-7635-4900-a6e9-fe3ebe65d4b1)
       pra confirmar com o dono do sistema antes de fechar.

**Sobre o teste:** esta máquina não tem Node.js/`chromium-cli` instalado, então
não deu pra abrir um navegador de verdade (nem simular o gesto de arrastar).
Testei com um usuário `ADMIN` temporário criado só pra isso (autorizado antes
de criar, removido do banco no final) e `curl` com sessão logada — confirmei
que as 3 páginas renderizam sem erro/warning de PHP, que os cartões de placa
saem com a classe e o emblema certos pros 3 veículos reais, e que o código de
drag-and-drop e a máscara estão presentes no HTML/JS entregue ao navegador.
Não é o mesmo que ver o arrastar-e-soltar funcionando de verdade na tela —
se quiser, posso testar ao vivo com você ou vale a pena instalar as
ferramentas de automação de navegador pra próximas vezes.

## 6. Tema do Painel (modo TV) independente do resto do sistema

Bug relatado pelo dono do sistema: trocar o tema claro/escuro na tela
`./painel` (pensada pra ficar aberta numa TV/monitor dedicado, ver seção 11
de `MELHORIAS_2026-07-08.md`) também trocava o tema do resto do sistema em
qualquer outra aba/computador que atualizasse a tela depois — e vice-versa.
Ele queria os dois desacoplados, pra poder deixar a TV num tema e o resto
do sistema no outro, conforme a preferência de quem está usando cada um.

- **Causa raiz:** os dois mecanismos de tema (script "aplica antes de
  renderizar" pra evitar flash + o toggle) são independentes em código —
  `painel.php` é um documento HTML próprio, não inclui `header.php`/
  `footer.php` — mas por coincidência os dois liam/escreviam a **mesma
  chave de `localStorage`** (`mecanize-theme`). Como `localStorage` é por
  origem/domínio (não por página), qualquer tela que escrevesse nessa chave
  sobrescrevia o valor que a outra também lia. O próprio código já tinha um
  comentário confirmando isso: `// Dark mode toggle (mesmo localStorage do
  resto do sistema)`.
- **Correção:** `app/views/Site/painel.php` passou a usar uma chave própria,
  `mecanize-theme-painel`, tanto no script anti-flash (`<head>`) quanto no
  toggle (`#temaToggle`). `app/views/layout/header.php` e `footer.php` (o
  mecanismo do resto do sistema) **não foram alterados** — continuam com
  `mecanize-theme`. Nenhuma mudança de CSS foi necessária: o atributo
  `data-theme` no `<html>` e as regras `[data-theme="dark"]` de
  `global.css`/`sidebar.css` já reagiam só à presença do atributo em cada
  documento, isolado por natureza — o problema era 100% a chave de
  armazenamento compartilhada, não o CSS.
- **Testado:** confirmado via `curl` com sessão logada que `./painel`
  renderiza sem erro e já lê/escreve `mecanize-theme-painel` (não mais
  `mecanize-theme`); grep em todo o projeto confirma que só existiam esses
  3 pontos de leitura/escrita da chave de tema (`header.php`, `footer.php`
  e `painel.php`) — as telas de rastreio público (`rastreio.php`/
  `rastreioNaoEncontrado.php`) só seguem `prefers-color-scheme` do
  navegador, não usam `localStorage`, e não precisaram de ajuste.

## 7. Assinatura de marca nas páginas de Termos e Privacidade

Pedido do dono do sistema: no bloco de assinatura no fim de `Termos de Uso`
e `Política de Privacidade` (`.legal-content__signature`), trocar o texto
"Mecanize" em negrito pela logo de verdade (`logo-mecanize.png`, a mesma já
usada no topo da própria página), e trocar o slogan abaixo dela.

- `app/views/Site/termos.php` e `app/views/Site/privacidade.php` (os dois
  têm o mesmo bloco, ajustados por consistência): `<b>Mecanize</b>` virou
  `<img src="./assets/img/logo-mecanize.png" alt="Mecanize">`, e o texto do
  `<em>` mudou de "Tecnologia para transformar a gestão de oficinas
  mecânicas." pra "a automatização que sua oficina pede."
- `public/assets/css/global.css`: `.legal-content__signature b` virou
  `.legal-content__signature img` (altura fixa de 26px, mesmo espaçamento
  de antes).
- **Testado com screenshot real:** como `termos`/`privacidade` são páginas
  públicas (não exigem login), rodei o Chrome headless direto na página ao
  vivo — sem precisar de usuário de teste dessa vez. A página tem 21 seções
  então usei uma janela bem alta (`--window-size=1000,9000`) e recortei só
  a região da assinatura com GD (a extensão que foi habilitada na seção 1
  de hoje) pra conferir o resultado antes de reportar. Ficou correto: logo
  renderizando (mesmo arquivo do topo da página) e o novo texto no lugar
  certo.
  - Ajuste posterior a pedido do dono: a logo da assinatura (`.legal-
    content__signature img`) foi reduzida (ele mesmo editou o valor
    diretamente no `global.css` depois — deixei como ele configurou).

## 8. Redesign completo de Termos de Uso e Política de Privacidade

Pedido do dono do sistema depois de ver o resultado da seção 7: "amei as
telas, mas quero mais profissionalismo, complexidade e credibilidade — sem
mudar uma vírgula do conteúdo, só o design". Reescrevi `.legal-*` inteiro em
`public/assets/css/global.css` e ajustei a estrutura de
`app/views/Site/termos.php` e `app/views/Site/privacidade.php` (as duas
têm o mesmo layout) — **nenhum texto do documento legal em si foi tocado**,
só marcação estrutural nova (wrappers, ícones decorativos, JS) e CSS.

- **Barra de progresso de leitura** (`.legal-progress`, fixa no topo,
  gradiente da marca) — preenche conforme o usuário rola a página.
- **Nav com profundidade ao rolar**: vira `position: sticky`, ganha fundo
  branco translúcido + `backdrop-filter: blur` + sombra sutil só depois de
  rolar (`.is-scrolled`, controlado por JS). Precisou de um wrapper novo
  (`.legal-nav__inner`) pra virar uma barra full-bleed com o conteúdo
  ainda centralizado em 1040px.
- **Textura de marca sutil** atrás do nav + header: dois gradientes radiais
  bem fracos (~7% de opacidade) na cor primária, via `.legal-page::before`
  — não precisou de nenhum elemento novo no HTML.
- **Sumário virou um card** (borda, sombra, cantos arredondados) com
  **destaque de seção ativa via scroll-spy**: a seção que está sendo lida
  fica com fundo e borda esquerda azul + negrito, atualizado em tempo real
  conforme o scroll (JS com `getBoundingClientRect`, sem depender de
  IntersectionObserver pra manter compatível com o padrão já usado no
  resto do sistema).
- **Assinatura final** (logo + slogan da seção 7) ganhou um card próprio
  com friso superior em gradiente da marca — mais "selo de documento
  encerrado" do que texto solto.
- **Rodapé** virou uma barra full-bleed com fundo e borda superior, em vez
  de só texto flutuando no fundo da página.
- **Pequenos toques decorativos sem texto novo**: barrinha azul acima do
  H1, ícone de calendário no badge de "última atualização", ícone de lista
  no título "Sumário" — todos SVG puramente decorativos, nenhuma palavra
  nova adicionada em lugar nenhum.
- **Testado exaustivamente com Chrome headless**, incluindo um desvio de
  rota: a princípio pareceu que "Voltar ao login" cortava e o card do
  sumário estourava a tela em telas estreitas — mas depois de instrumentar
  a página com um script de diagnóstico (`getBoundingClientRect` de cada
  elemento), descobri que era uma limitação do **Chrome headless desta
  máquina** (ele força uma largura mínima de janela de ~500px e ignora
  pedidos menores, mas ainda salva o screenshot recortado no tamanho
  pedido) — não um bug real de layout. Confirmado com um teste calibrado
  (`window.innerWidth` impresso na tela) e depois validado em 600px de
  largura (a menor largura que esse Chrome realmente respeita): tudo se
  encaixa corretamente. Mesmo assim, mantive os reforços defensivos que já
  tinha adicionado (`overflow-x: hidden`, `min-width: 0` nos itens flex do
  nav, versões reduzidas de logo/botão a partir de 480px) — não custam nada
  e blindam telas realmente pequenas (celulares finos) que esse Chrome não
  consegue simular aqui.
