# Melhorias — 18/08/2026

Documentado no mesmo espírito de `comentarios.md` e das `MELHORIAS_*.md`
anteriores. Esta sessão foi mais longa que as anteriores e cobriu sete
frentes diferentes, pedidas em sequência: recuperação de senha, sistema de
impressão/PDF, paleta de gráficos, logo em PDF, paginação de PDF, um plano
de segurança de 19 itens e, por fim, a integração entre Produtos, Estoque,
Ordens de Serviço e Caixa. As seções abaixo seguem essa ordem.

## 1. Redesenho da tela de recuperação de senha

A tela de "esqueci minha senha" (`Usuarios/recuperarSenha.php`) e a de
redefinição (`Usuarios/redefinirSenha.php`) saíram do layout genérico do
app (que pressupõe sidebar/topbar de quem já está logado) para uma casca
própria: `app/views/layout/auth.php` + `public/assets/css/auth.css`.

Faz sentido como tela separada pelo mesmo motivo do login: quem chega aqui
pode estar vindo de um link de e-mail, fora de qualquer sessão — não é o
"app com uma tela a menos", é uma porta de entrada diferente. O CSS novo
documenta isso no próprio cabeçalho do arquivo. `Usuarios/login.php`
também recebeu ajustes pontuais nesta mesma passada (campo `retorno`
oculto, ver seção 6.6).

## 2. Sistema de impressão e PDF deixou de ser "captura de tela"

Antes, os PDFs de Ordem de Serviço, Orçamento e Relatório de Caixa eram
essencialmente a mesma tela do sistema — com sidebar, botões e cores de
UI — jogada para dentro do Dompdf. O pedido era transformar isso em
documentos de verdade, com cara de documento impresso.

O que mudou:

- **Partials de documento reutilizáveis**, novos, em `app/views/layout/`:
  `_docPdfCabecalho.php`, `_docPdfRodape.php`, `_docPdfEstilos.php`,
  `_docPdfAssinatura.php` (para PDF/Dompdf) e `_docImpressoCabecalho.php` /
  `_docImpressoRodape.php` (para impressão direta do navegador, via
  `Ctrl+P`). Cada documento monta seu corpo específico e importa essas
  peças em vez de reinventar cabeçalho/rodapé/timbre a cada tela.
- `public/assets/css/print.css` (novo) — carregado com `media="print"`
  (`app/views/layout/header.php`), então só existe no papel: desmonta a
  sidebar/topbar e transforma a tela em algo parecido com A4 quando a
  pessoa aperta imprimir direto do navegador.
- `config/empresa.php` (novo) — dados fixos da oficina (nome, CNPJ,
  endereço, contato) usados no timbre de todos os documentos, num único
  lugar em vez de espalhados por view.
- `app/views/Ordens_Servico/pdfOrdemServico.php`,
  `app/views/Orcamento/pdfOrcamento.php` e
  `app/views/Caixa/pdfRelatorioCaixa.php` encolheram drasticamente
  (254, 232 e 146 linhas a menos, respectivamente) porque passaram a
  delegar cabeçalho/rodapé/assinatura aos partials em vez de duplicar
  marcação.
- `app/core/Pdf.php` ganhou um método `gerar()` (devolve o binário como
  string, para quem precisa dele sem mandar direto ao navegador) além do
  `baixar()` existente, e um `montar()` privado compartilhado pelos dois.

## 3. Gráficos: paleta 100% azul, sem verde/vermelho

Aplicado seguindo a metodologia da skill `dataviz` do projeto (regras de
cor categórica/sequencial/divergente, paleta validada em OKLCH). Em
`public/assets/css/global.css` entraram tokens dedicados a gráfico —
`--chart-1`, `--chart-2`, `--chart-ordinal-1` a `--chart-ordinal-5` — com
uma escala clara→escuro totalmente dentro do azul da marca, e uma segunda
definição desses mesmos tokens sob o tema escuro (o `#003BDD` sozinho
"some" contra fundo escuro; a escala do modo escuro foi recalibrada e
validada à parte). Nenhum `#`-hexadecimal verde ou vermelho ficou solto em
CSS de gráfico depois da varredura.

## 4. Gradiente de faturamento aplicado a todas as marcas de dados

O gráfico de faturamento já usava um gradiente azul (claro na base, cheio
no topo da barra). O pedido foi generalizar esse efeito para toda marca de
dado do sistema (barras, fatias de rosca), em vez de deixá-lo exclusivo de
uma tela.

Como gráfico é `<canvas>`, ele não herda CSS — cada tela que desenhava
gradiente à mão acabava com um hexadecimal solto no JavaScript (foi
inclusive como verde/vermelho tinham entrado na paleta, seção 3). A
solução ficou centralizada em `app/views/layout/header.php`:
`window.corDoTema(token)` lê o valor computado de um token do design
system (ex.: `--chart-1`), e `window.gradienteDoTema(ctx, token, altura)`
monta o `CanvasGradient` a partir dele — clareando a cor-base em 45% para
o começo do gradiente. Toda tela que desenha gráfico chama essa função em
vez de montar o próprio gradiente, então o resultado casa com o token do
tema (inclusive claro/escuro) automaticamente. Em `global.css`, o mesmo
padrão foi replicado para barras de progresso em HTML/CSS puro via
`color-mix()`, sem depender de canvas.

## 5. Logo real no lugar do "M" de placeholder nos PDFs

Os PDFs estavam saindo com um "M" genérico onde deveria aparecer o
logotipo da oficina. A causa era o chroot do Dompdf: por segurança, a
biblioteca só lê arquivo de dentro de uma pasta liberada explicitamente, e
essa liberação nunca tinha sido configurada — a falha era silenciosa (sem
erro, só sem logo). Corrigido em `app/core/Pdf.php`:

```php
$options->setChroot([realpath(__DIR__ . '/../../public/assets/img')]);
```

Um novo arquivo `public/assets/img/logo-mecanize-doc.jpg` foi adicionado
como versão do logo pensada para papel (a original tinha fundo/proporção
pensados para tela). Testado gerando os três documentos (OS, orçamento,
relatório de caixa): o logo sai idêntico nos três, e a assinatura do
cliente — que é um `data:` URI de canvas, não um arquivo — continua
funcionando, já que nunca dependeu de disco.

## 6. OS curta não quebra mais em 2 páginas

Uma Ordem de Serviço com poucos itens estava saindo em 2 páginas de PDF
por pouca margem — o conteúdo cabia, mas o espaçamento entre blocos
(cabeçalho, itens, totais, assinatura) empurrava as últimas linhas para
uma segunda folha quase vazia. O pedido explícito era resolver por
espaçamento mais compacto, **sem** diminuir fonte, e deixar OS realmente
longas continuarem paginando normalmente (não forçar tudo em 1 página a
qualquer custo).

Resolvido ajustando os espaçamentos em `_docPdfEstilos.php` e no corpo de
`pdfOrdemServico.php` (margens entre seções, altura de linha de tabela,
padding de blocos), mantendo o tamanho de fonte intacto. De quebra,
`app/core/Pdf.php` ganhou uma numeração de página automática
(`numerarPaginas()`, via `Canvas::page_text`), porque o contador de
página do Dompdf (`counter(pages)` em CSS) não funciona nesse motor — ele
lê 0 antes do documento terminar de paginar. Ficou "Página X de Y" no
rodapé de todo documento gerado pelo `Pdf`, não só da OS, já que a solução
mora no núcleo compartilhado.

## 7. Plano de ação de segurança (19 itens)

Aplicado a partir de um documento de plano de ação completo fornecido
pelo usuário (`agora-com-tudo-isso-idempotent-russell.md`), com pedido de
"aplique esse plano de ação completo no sistema". Descrito abaixo por
tema, não item a item na ordem original do documento.

### 7.1 — Cabeçalhos de segurança e fim das bibliotecas via CDN

`public/index.php` passou a mandar `X-Frame-Options: SAMEORIGIN`,
`X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin` e uma
`Content-Security-Policy` (`default-src 'self'`, com `img-src` liberando
`data:` para a assinatura do cliente). O sistema não mandava nenhum desses
cabeçalhos antes.

Como a CSP não libera nenhuma origem externa em `script-src`/`style-src`,
as bibliotecas que vinham de CDN (Google Fonts, Font Awesome, Bootstrap
Icons, Tom Select, Chart.js, IMask) foram baixadas e passaram a ser
servidas pelo próprio projeto, em `public/assets/vendor/` — ver o diff de
`app/views/layout/header.php`. Font Awesome acabou removido de vez (busca
em `app/views` não achou nenhuma classe `fa-`/`fas`/`far`; o projeto usa
SVG inline e Bootstrap Icons).

### 7.2 — Telas de erro unificadas, com código de ocorrência

As telas `Site/403.php`, `404.php`, `419.php`, `500.php` e
`rastreioNaoEncontrado.php` foram removidas e substituídas por um sistema
central: `app/core/Erro.php` (gera um código de ocorrência, registra o
detalhe técnico no log associado a esse código, e escolhe qual
"arquétipo" visual mostrar) e quatro arquétipos novos em
`app/views/Site/erros/` (`acesso.php`, `bussola.php`, `sistema.php`,
`_autonomo.php`). A tela de `500` genérica virou "falha-interna" com
código de ocorrência rastreável no log; a de CSRF vencido (`419`, que nem
é um código HTTP padrão) virou "sessão expirada", com explicação e sem
acusar a pessoa de ataque. `set_exception_handler` e
`register_shutdown_function` em `public/index.php` foram reescritos para
passar por esse mesmo caminho.

### 7.3 — Sessão: expira por inatividade e se revalida a cada requisição

Duas regras novas em `public/index.php`:

1. **Timeout de 30 minutos de inatividade** (`SESSAO_INATIVIDADE_MINUTOS`)
   — relevante porque o computador da oficina é compartilhado e fica
   ligado o dia todo; sem isso, quem sentasse depois herdava a sessão de
   quem saiu sem deslogar.
2. **Revalidação por `atualizado_em`**: a cada requisição, compara o
   carimbo de `usuarios.atualizado_em` com o valor salvo na sessão no
   momento do login. Se mudou (ou a conta foi desativada), a sessão cai
   na hora — o que faz trocar senha, mudar papel ou desativar um usuário
   derrubar a sessão dele imediatamente, em vez de esperar ele fechar o
   navegador.

### 7.4 — Login: sem enumeração de e-mail, sem hash de senha na sessão

Em `UserController::login()` (`app/controllers/UserController.php`), a
ordem de checagem foi invertida: **antes**, conta bloqueada era checada
antes da senha, e a mensagem de erro variava conforme o e-mail existisse
ou não — dava para sondar quais e-mails têm conta só olhando a resposta.
Agora a senha é validada primeiro; só depois de provar ser dono da conta
(senha certa) é que mensagens específicas (bloqueio, conta inativa)
aparecem.

Também: a sessão deixou de gravar `$userData` inteiro por `foreach`
(`$_SESSION["user_$key"] = $value`) e passou a listar campo a campo — o
hash da senha e os contadores de tentativa de bloqueio não têm nenhuma
utilidade guardados em sessão e, se um backup ou pasta temporária
compartilhada expusesse o arquivo de sessão, entregariam material para
quebrar a senha offline.

De quebra, o login ganhou um `?retorno=` que leva de volta à página que a
pessoa tentava abrir antes de cair no login — com validação contra a
tabela de rotas (`destinoAposLogin()`) para não virar um redirecionamento
aberto para site externo.

### 7.5 — `auth.php` redirecionava para instalação errada

`app/core/auth.php` mandava usuário deslogado para
`/mecanize/public/login` — nome de outra instalação. Numa pasta chamada
`mecanize-tcc`, isso caía em 404, não no login: a "camada de segurança"
levava a lugar nenhum. Trocado para caminho relativo (`./login`), que
funciona em qualquer pasta onde o sistema for instalado.

### 7.6 — Ações destrutivas por GET viraram POST + CSRF

`toggleStatusUsuario` (ativar/desativar usuário) aceitava `GET` — um link
carregado de qualquer lugar (um `<img>` em página externa, por exemplo)
bastava para desativar um usuário na sessão de um ADMIN logado, já que o
gate de CSRF do sistema só cobre POST. Convertido para POST com
`csrf_field()`. O mesmo padrão de risco foi identificado em outros botões
de excluir que ainda usam link `GET` — ver nota na seção "O que fica
pendente".

### 7.7 — Upload de imagem de placa sem validação nenhuma

`PlacaController::ler()` confiava cegamente no que chegava em `$_FILES`:
não checava `$_FILES['error']` nem `is_uploaded_file()`, não tinha limite
de tamanho, usava o `type` informado pelo navegador (que não prova nada
sobre o conteúdo real do arquivo) e, em caso de erro, devolvia `$_FILES`
inteiro — expondo caminho absoluto de pasta do servidor. Reescrito para
validar `error`/`is_uploaded_file()`, limitar a 2 MB, e usar
`getimagesize()` (lê o conteúdo de fato) para confirmar que é imagem e
extrair o MIME/extensão reais antes de repassar à API.

### 7.8 — Dompdf com acesso remoto habilitado (risco de SSRF)

`isRemoteEnabled` estava `true` em `app/core/Pdf.php`. Com isso ligado,
qualquer URL que aparecesse dentro do HTML do documento faria o **servidor**
buscá-la sozinho — inclusive endereços internos da rede, com o PDF
servindo de intermediário (SSRF). Desligado (`false`); testado que
logotipo (arquivo local, seção 5) e assinatura (`data:` URI) continuam
saindo idênticos, já que nenhum dos dois depende de rede.

### 7.9 — Injeção de cabeçalho de e-mail no Mailer

`Mailer::enviar()` escrevia o destinatário direto no comando SMTP
`RCPT TO:`. Um valor com `\r`/`\n` permitiria emendar outro comando ou
cabeçalho na mensagem — por exemplo, um `Bcc` para terceiro num e-mail de
recuperação de senha. Corrigido validando o destinatário com
`FILTER_VALIDATE_EMAIL` antes de montar o comando (qualquer quebra de
linha invalida o e-mail).

### 7.10 — `extract()` podendo sobrescrever variável interna do Controller

`Controller::view()` usava `extract($data)` para expor os dados da view
como variáveis locais. Uma chave chamada `view` ou `data` dentro do array
sobrescreveria a própria variável que controla qual arquivo é incluído.
Corrigido com `extract($data, EXTR_SKIP)`, que ignora chaves que colidem
com variável já existente no escopo.

### 7.11 — XSS na fronteira PHP → JavaScript

`htmlspecialchars()` já escapava tudo impresso como HTML, mas parava na
borda de blocos `<script>`: um produto cadastrado com nome
`</script><script>...` fechava o bloco e o restante virava JavaScript
executável (XSS armazenado). Novo `app/core/Html.php` com
`json_para_script()` — `json_encode()` com as flags `JSON_HEX_TAG` e
companhia, que transformam `< > & ' "` em escapes hex que o JavaScript lê
igual mas o parser de HTML não interpreta como tag. Usado, por exemplo,
para embutir o valor-base do plano de pagamento na tela de parcelas
(seção 8). Um `escape.js` novo também entrou como script global,
carregado antes de qualquer script inline que monte HTML via
`innerHTML`.

### 7.12 — Matriz de permissões por papel

`Controller::requireRole()` já existia; nesta passada, a cobertura foi
revisada e completada nos controllers — hoje há mais de 50 chamadas de
`requireRole()` distribuídas pelos controllers, tipicamente
`['ADMIN', 'ATENDENTE']` para operações administrativas do dia a dia
(cadastros, caixa, OS), `['ADMIN', 'TECNICO']` para o que é operação de
oficina, e `['ADMIN']` isolado para o que só o dono deve poder fazer
(usuários, categorias, exclusões). A regra vale sempre notada nos
comentários do próprio código: **esconder o link no menu não é controle
de acesso** — quem de fato barra é o `requireRole()` do método.

## 8. Controle financeiro, estoque e movimentações integrados

Este foi o pedido mais extenso da sessão, verbatim: ampliar formas de
pagamento (Pix, dinheiro, cartão) tanto no caixa quanto nas movimentações;
adicionar pagamento parcelado à OS, com parcelas, juros, taxa, total,
pagas/pendentes visíveis; criar movimentação de ENTRADA automática ao
cadastrar produto; criar movimentação de SAÍDA automática (com baixa de
estoque) quando um produto é usado numa OS; e manter tudo consistente
entre Produtos, Estoque, OS, Caixa e Movimentações, sem lançamento
duplicado.

Antes de implementar, três decisões foram confirmadas com o usuário:

- Numa OS parcelada, só a **parcela paga** entra no caixa (não o valor
  cheio na conclusão da OS).
- Lançar uma entrada manual de estoque no caixa é **opcional, com opção de
  desmarcar** (não automático incondicional).
- Formas de pagamento: cartão de débito e crédito **separados**, mais
  transferência bancária como forma própria (não empilhados num "CARTAO"
  genérico).

### 8.1 — Migração

`database/add_financeiro_estoque.php` (novo, idempotente via
`SHOW COLUMNS ... LIKE` antes de cada `ALTER TABLE`): amplia o enum
`movimentacoes_caixa.forma_pagamento`; acrescenta a `movimentacoes_estoque`
as colunas `origem`, `ordem_servico_id`, `forma_pagamento`, `usuario_id`,
`observacao`, `movimentacao_caixa_id` (+ 3 FKs); acrescenta a
`ordens_servico` as colunas `pagamento_tipo`, `pagamento_forma`,
`pagamento_juros`, `pagamento_taxa`, `pagamento_total`; cria a tabela
`ordem_servico_parcelas`. Executada com sucesso no banco de
desenvolvimento.

### 8.2 — Formas de pagamento centralizadas

`app/models/MovimentacaoCaixa.php` ganhou as constantes `FORMAS`
(`DINHEIRO`, `PIX`, `CARTAO_DEBITO`, `CARTAO_CREDITO`, `BOLETO`,
`TRANSFERENCIA`), `FORMAS_LEGADAS` (`CARTAO`, para lançamentos antigos
que ainda usam o valor genérico) e `FORMA_LABEL`, além de
`rotuloForma()`. Todas as telas que antes tinham um array de rótulos
duplicado localmente (`Caixa/listCaixa.php`, `movimentacoesCaixa.php`,
`relatorioCaixa.php`, `pdfRelatorioCaixa.php`) passaram a importar dessa
única fonte, e os `<select>` de forma de pagamento
(`movimentacoesCaixa.php`, `Ordens_Servico/listOrdemServico.php`) passaram
a montar as opções em loop sobre `MovimentacaoCaixa::FORMAS`.
`MovimentacaoCaixa::registrar()` também passou a devolver o id do
lançamento inserido (em vez de só `bool`), necessário para vincular
movimentação de estoque e parcela ao lançamento de caixa que as gerou.

### 8.3 — Movimentação de estoque como ponto de integração

`app/models/MovimentacaoEstoque.php` ganhou:

- `registrarSemAjustarEstoque()` — insere o registro de auditoria sem
  tocar em `produtos.estoque_atual` (usado quando o estoque já foi
  ajustado por outro caminho, evitando dupla contagem).
- `usuarioValido()` — confirma que o `usuario_id` existe antes de gravá-lo
  como autor da movimentação (ver bug real na seção 8.7).
- `limparDaOrdem()` — remove movimentações de `origem = 'OS'` de uma
  ordem específica (usado ao editar/excluir OS, para não deixar rastro
  duplicado quando os itens mudam).
- `vincularAoCaixa()` — grava `movimentacao_caixa_id` depois que o
  lançamento de caixa correspondente é criado.

A listagem (`MovimentacaoEstoque/listMovimentacaoEstoque.php`) ganhou uma
coluna "Origem": link para a OS quando `origem = OS`, "Cadastro do
produto" quando `origem = CADASTRO`, ou "Lançamento manual", com uma nota
extra quando a movimentação já está vinculada a um lançamento de caixa.

### 8.4 — Cadastro de produto gera entrada automática

`Produtos::create()` (`app/models/Produtos.php`) passou a chamar
`registrarEstoqueInicial()` depois de inserir o produto: se
`estoque_atual` veio maior que zero no cadastro, gera uma movimentação
`ENTRADA`/`origem=CADASTRO` com o custo unitário informado e o usuário
logado como autor. Envolvido em `try/catch` com log — falha aqui não pode
derrubar o cadastro do produto em si.

### 8.5 — Item usado em OS gera saída automática (com estorno correto)

Em `app/models/OrdemServico.php`:

- `salvarItemProduto()` — depois da baixa de estoque já existente, chama
  `MovimentacaoEstoque::registrarSemAjustarEstoque()` com
  `origem='OS'`, `ordem_servico_id`, usando o `preco_custo` do produto
  como custo unitário.
- `update()` — antes de apagar `ordem_servico_produtos` para recriar a
  lista de itens, chama `limparDaOrdem()`, para não deixar movimentações
  de itens que foram removidos da OS na edição.
- `delete()` — **bug pré-existente corrigido**: excluir uma OS nunca
  devolvia as peças ao estoque. Nesta passada, a exclusão passou a
  devolver `estoque_atual` de cada item consumido pela OS e a limpar as
  movimentações de origem OS correspondentes, tudo dentro da mesma
  transação já existente.
- `getById()` — passou a trazer também `pagamento_tipo`, `pagamento_forma`,
  `pagamento_juros`, `pagamento_taxa`, `pagamento_total`.

### 8.6 — Pagamento parcelado

Novo model `app/models/OrdemServicoPagamento.php`:

- `calcularTotal(base, jurosPercentual, taxa)` — `base + base*juros/100 + taxa`.
- `plano()` / `parcelas()` / `resumo()` — leitura do plano de pagamento e
  das parcelas, e um resumo agregado (total, pagas, pendentes, atrasadas,
  valor pago, valor pendente, próximo vencimento).
- `salvarPlano()` — valida tipo (`AVISTA`/`PARCELADO`), forma (precisa
  estar em `MovimentacaoCaixa::FORMAS`), juros/taxa ≥ 0; numa transação,
  atualiza as colunas de plano em `ordens_servico` e regenera as parcelas
  — **apagando só as ainda não pagas** (`pago = 0`), preservando o
  histórico do que já foi recebido se o plano for editado depois.
- `gerarParcelas()` — quantidade limitada a 1–48; distribui
  `(total − valor já pago)` entre as parcelas restantes, valor por parcela
  arredondado para baixo, com a **última parcela absorvendo o resto do
  arredondamento** (para o somatório das parcelas bater exatamente com o
  total, centavo a centavo); vencimentos mensais a partir da data
  informada (padrão: hoje + 30 dias).
- `pagarParcela()` — recebe forma de pagamento e usuário; recusa se a
  parcela não existe, já está paga (`pago=1` OU já tem
  `movimentacao_caixa_id`), a forma é inválida, ou não há caixa aberto.
  Registra a entrada no caixa e só então marca a parcela como paga,
  vinculando o `movimentacao_caixa_id` — é essa checagem dupla de "já
  paga" que impede lançar a mesma parcela duas vezes em caixa.

Rotas novas em `config/routes.php`: `pagamentoOrdemServico`,
`salvarPagamentoOrdemServico`, `pagarParcelaOrdemServico` — três métodos
novos em `OrdemServicoController` (`app/controllers/OrdemServicoController.php`),
todos com `requireRole(['ADMIN', 'ATENDENTE'])`.

Tela nova `app/views/Ordens_Servico/pagamentoOrdemServico.php`: cartões de
resumo (valor da OS, total a receber, já recebido, em aberto — com
destaque para parcela atrasada), formulário do plano, tabela de parcelas
com botão "Receber" por linha (forma de pagamento + confirmação), e uma
prévia em JavaScript do total/valor de parcela espelhando a mesma fórmula
do backend — usando `json_para_script()` (seção 7.11) para embutir o
valor-base com segurança. Acesso a partir de um botão "Pagamento" novo em
`Ordens_Servico/viewOrdemServico.php`, ao lado de "Baixar PDF".

`OrdemServicoController::registrarRecebimentoNoCaixa()` (chamado na
conclusão da OS) passou a checar `pagamento_tipo`: se é `PARCELADO`, não
lança nada no caixa na conclusão — o lançamento acontece parcela a
parcela, via `pagarParcela()`. Se é à vista, usa `pagamento_total` quando
preenchido, com `valor_total_os` como alternativa.

### 8.7 — Compra manual de estoque pode virar saída de caixa (opcional)

`MovimentacaoEstoqueController::save()`
(`app/controllers/MovimentacaoEstoqueController.php`) passou a montar
`origem='MANUAL'`, anexar o usuário logado e, quando a movimentação é
`ENTRADA` com forma de pagamento informada **e** a caixa "lançar no
caixa" está marcada, chamar `lancarCompraNoCaixa()` — um método privado
novo que registra uma saída `PECAS` no caixa aberto e vincula o id de
volta na movimentação via `vincularAoCaixa()`. A tela
`saveMovimentacaoEstoque.php` ganhou o `<select>` de forma de pagamento e
o checkbox "lançar no caixa", com JavaScript mostrando/escondendo os dois
conforme o tipo (`ENTRADA`/`SAÍDA`) selecionado.

### 8.8 — Tabela de consistência

| Ação                                   | Produtos (estoque) | Movimentações de Estoque      | Caixa                          |
|-----------------------------------------|---------------------|--------------------------------|----------------------------------|
| Cadastrar produto com estoque inicial   | grava direto        | ENTRADA / CADASTRO             | —                                 |
| Lançar entrada manual (com "lançar no caixa") | incrementa      | ENTRADA / MANUAL               | SAÍDA / PEÇAS, vinculada          |
| Usar produto numa OS                    | decrementa           | SAÍDA / OS, vinculada à OS      | (na conclusão ou por parcela)     |
| Editar OS (troca de itens)              | restaura + decrementa| remove as antigas de origem OS, recria | —                          |
| Excluir OS                              | restaura              | remove as de origem OS         | —                                 |
| Concluir OS à vista                     | —                     | —                               | ENTRADA pelo valor total          |
| Concluir OS parcelada                   | —                     | —                               | nada (espera pagamento de parcela)|
| Pagar parcela                           | —                     | —                               | ENTRADA pelo valor da parcela, vinculada |

### 8.9 — Testes

Executados via scripts PHP ad-hoc (sem framework de teste para este
trabalho específico), rodando contra o banco de desenvolvimento e com
limpeza manual dos dados de teste ao final de cada rodada:

- `teste_integracao.php` — consistência Produtos → Estoque → OS (criar
  produto com estoque inicial, usar em OS, editar OS trocando item,
  excluir OS): todos os saldos batem em cada etapa.
- `teste_parcelas.php` — geração de parcelas (arredondamento, última
  parcela absorve resto), pagamento de parcela, tentativa de pagar
  parcela já paga (rejeitada), integração com caixa: passou.
- `teste_conclusao.php` — conclusão de OS à vista lança no caixa;
  conclusão de OS parcelada não lança nada (correto: só a parcela paga
  lança).

Dois bugs reais foram encontrados e corrigidos durante esses testes (não
só no script, no código de produção):

1. **FK de `usuario_id` podia derrubar a OS inteira.** Uma sessão com
   `user_id_usuario` de um usuário já excluído do banco fazia o `INSERT`
   em `movimentacoes_estoque` falhar por violação de chave estrangeira —
   e, como estava dentro da mesma transação da OS, derrubava a operação
   inteira por causa de um campo de auditoria, não do dado principal.
   Corrigido com `MovimentacaoEstoque::usuarioValido()` (seção 8.3):
   confirma a existência do usuário antes de usá-lo, e cai para `null`
   silenciosamente (sem autor) em vez de estourar a transação toda.
2. **`Class "MovimentacaoCaixa" not found`, HTTP 500 em produção.**
   `MovimentacaoEstoqueController::save()` lia `MovimentacaoCaixa::FORMAS`
   sem nunca ter chamado `$this->model('MovimentacaoCaixa')` — o único
   mecanismo que normalmente faz o `require_once` da classe. Passava
   despercebido em teste unitário do model, mas quebrava a rota de
   verdade. Corrigido com um `require_once` explícito no topo do método;
   reproduzido via POST HTTP real antes e depois da correção
   (500 → 302/sucesso).

## O que fica pendente

- **Nada das seções 1 a 6 foi conferido no navegador de verdade.** A
  paleta de gráfico, o gradiente, a numeração de página do PDF e o layout
  compacto da OS de 1 página foram validados por leitura do CSS/PHP
  gerado e, no caso do PDF, pela geração do arquivo — não por olho
  humano na tela nem por captura visual.
- **A seção 7 (segurança) não tem cobertura de teste automatizado nova**
  — as correções foram revisadas por leitura e, em alguns casos
  (`toggleStatusUsuario`, upload de placa, movimentação de caixa),
  exercitadas manualmente via requisição HTTP direta, mas não há teste
  no `tests/` do projeto travando esses comportamentos contra regressão.
- **Outros botões de excluir ainda usam link `GET`**, mesmo padrão do
  problema corrigido na seção 7.6 para `toggleStatusUsuario`. Hoje não são
  exploráveis porque o guarda de "só navegação same-origin em GET" do
  `public/index.php` barra requisição de fora — mas é uma proteção
  indireta: se esse guarda mudar, viram CSRF destrutivo de novo. Converter
  todos para POST de uma vez, em vez de pela metade, fica para uma
  próxima sessão.
- **A seção 8 (financeiro/estoque) foi validada por script PHP e por uma
  requisição HTTP pontual**, não por um fluxo completo clicado no
  navegador (cadastrar produto → usar em OS → parcelar → receber parcela
  → conferir caixa, tudo pela UI).
- A suíte `vendor/bin/phpunit` já tinha, antes desta sessão, 46 falhas por
  `Unknown database 'mecanize_db_test'` (banco de teste não existe neste
  ambiente) — não relacionadas a nada feito aqui, e não investigadas nesta
  passada.
- Nenhuma dessas mudanças foi commitada; tudo segue como alteração local
  na branch `melhorias-caixa-agenda`.
