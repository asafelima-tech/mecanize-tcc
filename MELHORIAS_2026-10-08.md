# Auditoria completa e evolução — 08/10/2026

Documento vivo da sessão: **diagnóstico** (o que foi encontrado, com prova de
execução), **plano** (ordem e critérios de aceite) e **registro** do que foi
feito e validado, etapa por etapa. Segue a convenção das `MELHORIAS_*.md`.

## Situação em 09/10/2026 (leia primeiro)

| Etapa | Situação |
|---|---|
| 1 Estabilização · 2 Operação · 3 Estoque e compras · 4 Financeiro · 5 Comunicação e portal | ✔ **concluídas e validadas** (PHPUnit **471 testes**; roteiros HTTP 72 / 99 / 102 / 108 / **169**, todos sem falha — reexecutados em 09/10 depois de reconstruir o banco) |
| 6 Perfis, celular, placa, consulta veicular | ✔ **concluída e validada** (roteiro de aceitação HTTP próprio: 51 verificações, 0 falhas; migração, evidências, pendências, placa e consulta veicular provados) |
| 7 Validação final | ✔ **concluída em 09/10/2026**: PHPUnit **471 testes / 3293 asserções** e 6 roteiros HTTP (72 / 99 / 102 / 108 / 169 / 51) sem falha; o que depende de terceiros está listado na Etapa 7 |

**Banco de dados (resolvido em 09/10):** MariaDB reconstruído a partir dos dumps e conferido; foi preciso acrescentar duas linhas ao
`my.ini` porque a gravação padrão do InnoDB travava neste computador; backup diário agendado. Detalhes na seção da Etapa 6.
Para ligar: painel do XAMPP → MySQL → Start. Ferramentas para conferir telas no celular sem banco: `tests/browser/LEIA-ME.md`.

## 0. Ponto de partida

- Stack: PHP 8.2 sem framework (MVC próprio), MySQL/InnoDB via PDO, Dompdf,
  PHPUnit 9. Monolito de uma única oficina (não há separação entre empresas).
- Perfis: `ADMIN`, `ATENDENTE`, `TECNICO`.
- **A árvore de trabalho tem 84 arquivos modificados e ~40 novos que nunca
  foram commitados** (sessões de 18/08 e 20/08). Todo o trabalho recente
  existe só localmente. Foi feito um snapshot fora do repositório antes de
  qualquer mudança desta sessão.
- O banco `mecanize_db` **não existia** neste MySQL. Foi recriado a partir do
  backup completo `mecanize_db_2026-08-04_133929.sql` (dados de
  desenvolvimento) + as 4 migrações posteriores, e o `mecanize_db_test` via
  `database/create_test_db.php`. O `mysql.exe` do XAMPP continua quebrado
  (`caching_sha2_password`), tudo é feito por PDO.
- Linha de base: lint limpo; PHPUnit **75 testes / 180 asserções verdes**.

O que já estava bom e foi preservado: CSRF global, cabeçalhos de segurança/CSP,
sessão endurecida (HttpOnly/SameSite, regeneração, inatividade, revalidação da
conta), bloqueio de login com mensagem uniforme, `password_hash`, SQL 100 %
preparado (varredura sem injeção), escape de saída consistente (varredura
sem hits), segredos fora do git, arquivos sensíveis devolvem 403 por HTTP,
backups por PDO, índices únicos como rede de proteção.

## 1. Diagnóstico

Cada item marcado **[provado]** foi reproduzido por HTTP real contra o sistema
em execução (scripts de auditoria, usuários `zz_audit_*`).

### Críticos — dinheiro, estoque, autorização

| # | Achado | Prova |
|---|---|---|
| C1 | **Estoque baixa ao salvar a OS**, não no consumo (contraria §9.1). Não checa saldo (OS com 50 un. levou o produto a **−42**). Editar a OS devolve tudo e baixa de novo, e **apaga** as movimentações da OS (perde o histórico). Cancelar não devolve. | [provado] 10→8 na criação; −42 |
| C2 | **`concluir()` não é idempotente**: dois envios = dois lançamentos no caixa. Não verifica o status atual (conclui OS aberta/cancelada) nem exige conferência do administrador. | [provado] 2 lançamentos |
| C3 | **Qualquer transição de status é aceita**: o Kanban leva ABERTA→CONCLUIDA sem caixa, sem comissão, sem assinatura; e reabre uma OS já recebida/comissionada. `OrdemServico::update()` também grava qualquer status. | [provado] |
| C4 | **Orçamento recusado pode ser aprovado e gera OS.** A edição do orçamento aceita `status=aprovado` sem criar OS e **não grava os itens** enviados. | [provado] |
| C5 | **Autorização incompleta**: ~25 rotas sem checagem de papel no servidor (lista/PDF de OS, clientes, orçamentos, estoque, `criarModelo`, `lerPlaca` — esta consome API paga). Não há matriz central nem *default-deny*; a "URL inerte" não é controle de segurança (um `fetch` do console passa). | [provado] TÉCNICO acessa tudo isso e cria modelo no catálogo |
| C6 | **Totais vêm do navegador** (`valor_total_*`), não são recalculados dos itens; o plano de parcelas não é refeito quando a OS muda. | leitura de código + 2 OS divergentes no banco |
| C7 | **Receita ≠ recebimento**: a OS à vista lança entrada no caixa só por ser concluída (sem confirmação do pagamento); o dashboard soma `valor_total_os` das OS concluídas e chama de faturamento. Não existe contas a receber/pagar. (§4.3/§4.4) | leitura de código |

### Altos — fluxos essenciais quebrados

| # | Achado | Prova |
|---|---|---|
| A1 | OS criada pela aprovação do orçamento **perde o técnico** (usa `mecanico_id`, `create()` espera `tecnicos[]`), **baixa estoque** e nasce `EM_ANDAMENTO` (pulando "aguardando execução"). | [provado] |
| A2 | **Multi-técnico**: só o 1º técnico atribuído enxerga a OS; o 2º recebe 404 e a OS não aparece no painel dele (`mecanico_id` = `MIN(id)`). | [provado] |
| A3 | **TÉCNICO não consegue sair do sistema com caixa aberto**: o logout manda para `fecharCaixa`, que devolve 403. | [provado] |
| A4 | Movimentações de estoque podem ser **editadas/apagadas** (sem rastro); o tipo `AJUSTE` **sempre subtrai** (ajuste positivo impossível). | leitura de código |
| A5 | `pagarParcela` sem transação/lock: só o lock de sessão do PHP evita duplicidade; um webhook (sem sessão) ou outro usuário contorna. Lançamento no caixa e baixa da parcela não são atômicos. | leitura (teste de corrida passou só por causa do lock de sessão) |
| A6 | **Técnico ≠ atendente**: matriz incoerente (estoque = ADMIN+TECNICO sem ATENDENTE; caixa só ADMIN/ATENDENTE; técnico não opera cadastros). Contraria §7. | tabela de rotas |
| A7 | Caixa global único: logout/expiração de qualquer usuário fecha o caixa de outro; `abrir` tem corrida (checa e insere sem trava). | leitura |
| A8 | **FKs ausentes**: `ordens_servico.cliente_id/veiculo_id`, `veiculos.cliente_id/marca_id/modelo_id`, `ordem_servico_produtos.produto_id`, `mao_obra.tecnico_id`, `movimentacoes_caixa.ordem_servico_id`, `movimentacoes_estoque.produto_id`. | information_schema |
| A9 | Comunicação: WhatsApp é só um link `wa.me`; sem central de conversas; o cliente recebe 2 e-mails (OS pronta, orçamento aprovado), enviados na hora do request e sem controle de duplicidade. | leitura |
| A10 | Placa: foto > 2 MB é recusada (foto de celular costuma passar disso), cURL sem timeout, sem validar formato nem confiança, preenche o campo e dá `alert()` sem conferência; não existe consulta de dados do veículo. | leitura |

### Médios — incompleto / usabilidade

- M1 Gráficos semanais com rótulos fixos "Seg…Dom" sobre janela móvel de 7 dias (a barra de hoje aparece com o dia errado); chave `DIAGNOSTICO` inexistente.
- M2 `Orcamento::salvar` sem transação; numeração `MAX+1` no cliente; `OrdemServico::update` só captura `Exception` (não `Throwable`).
- M3 `Mailer` sem timeout de leitura e envio síncrono dentro da requisição.
- M4 Dashboard com ~25 consultas (loop por dia/mês); listagens com `LIMIT` fixo e sem paginação.
- M5 **Inexistentes**: ocorrência, avaliação técnica, fila priorizada, cadastro provisório, autorização excepcional, conferência do administrador, solicitação de peças, compras/fornecedores, contas a pagar/receber, cartões/faturas, central de conversas, portal do cliente, pagamento online, trilha de auditoria de estados, evidências (upload).
- M6 Vocabulário de status: `PRONTA_ENTREGA` = "técnico terminou" e `CONCLUIDA` = "entregue"; falta "aguardando conferência" e "aguardando retirada".
- M7 Código morto/rotas fantasmas (`marcarFechamentoAuto`, `OrcamentoController::finalizar`, `getServicos` vazio, regex legado de comissão).

### Baixos
Estilos inline residuais, textos de mensagens inconsistentes, `Site/home.php` e
`listRel.php` com blocos de JS grandes embutidos.

## 2. Plano de execução

Ordem das etapas do pedido, ajustada pelas dependências que a auditoria achou:
**a base transversal (autorização, máquina de estados, auditoria, idempotência)
vem antes de qualquer módulo novo**, porque todos dependem dela.

| Etapa | Entrega | Critério de aceite (teste) |
|---|---|---|
| **1 Estabilização** | Matriz central de permissões com *default-deny* e paridade técnico = atendente; máquina de estados da OS e do orçamento com trilha de auditoria; `concluir`/aprovar idempotentes; Kanban sem atalho; totais recalculados no servidor; FKs; logout do técnico; correções A1–A4, A7. | C1–C6, A1–A8 viram testes automatizados verdes; paridade verificada rota a rota |
| **2 Operação** | Ocorrência, avaliação técnica, cadastro provisório, autorização excepcional, fila priorizada, conferência do administrador; os 3 fluxos ponta a ponta. | Cada fluxo roda por HTTP sem duplicar cliente/veículo/orçamento/OS |
| **3 Estoque e compras** | Itens da OS com estados (planejado→reservado→consumido/devolvido); consumo idempotente com saldo; movimentações imutáveis com estorno justificado; solicitações de peças; fornecedores, pedidos, recebimento parcial. | Reprocessar não duplica baixa; compra não mexe no estoque antes do recebimento |
| **4 Financeiro** | Contas a receber (parcelas, taxas, estorno), contas a pagar, cartões e faturas sem dupla contagem, indicadores com definições únicas. | Cenários dos exemplos (R$ 600 em 3×; R$ 1.000 em 5×) |
| **5 Comunicação/portal** | Central de conversas, adaptador WhatsApp Cloud API (webhook assinado), outbox de notificações idempotente, portal do cliente (links com hash, expiração e revogação), aprovação e pagamento via provedor com webhook. | Webhook inválido não altera nada; clique repetido não duplica |
| **6 Perfis** | Home por perfil, interface do técnico para celular, placa (foto reduzida no cliente, conferência), consulta veicular. | Verificação em viewport de celular |
| **7 Validação** | Suíte ponta a ponta, regressões, documentação do que depende de configuração externa. | PHPUnit + roteiros HTTP verdes |

### Dependências externas (não inventadas — precisam de credencial/decisão)

- **WhatsApp**: API oficial (Meta Cloud API) exige conta Business, número, token, *templates* aprovados e webhook público HTTPS. Sem isso a central funciona só com registro interno e o canal fica marcado como "não configurado".
- **Pagamento online**: exige contratar um provedor (Mercado Pago, Asaas, Pagar.me…) e URL pública para o webhook. O sistema terá a interface e a prova com provedor simulado; a ativação real depende da escolha e das chaves.
- **Consulta de placa**: exige serviço pago com token. O reconhecimento por foto já usa Plate Recognizer (`PLATE_RECOGNIZER_TOKEN`).
- **DPL / DPK / Mercado Livre**: não há garantia de API pública de compra. Será verificado por pesquisa; onde não houver, o fluxo é consulta/abertura do site oficial + registro manual do pedido.
- **Regras de horário**: o atendente tem janela 07–18h; o técnico não. Mantido como está (é restrição de acesso, não de capacidade) — decisão a confirmar.

---

## 3. Registro de execução

### Etapa 1 — Estabilização ✔ (validada)

**Validação:** PHPUnit **151 testes / 861 asserções verdes** (eram 75/180) + roteiro de aceitação
por HTTP real contra Apache+MySQL: **72 verificações, 0 falhas** (usuários `zz_audit_*`, reproduzindo
os mesmos cenários que provaram os defeitos do diagnóstico) + 60 requisições simultâneas sem erro +
log do Apache sem nenhum aviso/erro de PHP na rodada.

**Base transversal criada**

- `app/core/Permissao.php` + coluna `acesso` em **toda** rota de `config/routes.php` + checagem em
  `public/index.php` antes do controller. Níveis: `PUBLICO`, `LOGADO`, `EQUIPE`, `ADMIN`. Rota sem
  nível é negada (menos para o ADMIN). **Técnico e atendente têm exatamente as mesmas permissões**
  (o `PermissaoTest` falha se divergirem em qualquer rota). Resolvia C5, A6.
- `app/core/Transacao.php` (transação ciente de transação aberta) e `app/core/Historico.php`
  + tabela `historico_estados` (trilha de toda mudança de estado: quem, quando, de→para, motivo).
- `database/etapa1_estabilizacao.php` (idempotente, rodada nos dois bancos): novos estados da OS,
  trilha, ciclo de vida das peças, livro de estoque imutável, chave de idempotência no caixa,
  **no máximo um caixa aberto garantido pelo banco**, e 12 FKs que faltavam (A8).

**Ordem de serviço (`OrdemServicoFluxo`)** — C2, C3, A1
`ABERTA ⇄ EM_ANDAMENTO ⇄ AGUARDANDO_PECA → AGUARDANDO_CONFERENCIA → AGUARDANDO_RETIRADA → CONCLUIDA`
(+ `CANCELADA`). Toda mudança de etapa passa por `transicionar()`: recusa transição inexistente,
confere o papel (conferir, devolver à execução e cancelar são do **ADMIN**; o técnico termina o
serviço mas **não encerra** a OS), aplica os efeitos na mesma transação, é idempotente e grava a
trilha. O Kanban, a edição e o painel do técnico usam o mesmo caminho. Conferir = calcular comissões
+ gerar a **cobrança** (parcelas); **não** é receber. Entregar pode receber no balcão, e se o
recebimento falhar a entrega não acontece.

**Estoque (`ConsumoEstoque`, `MovimentacaoEstoque`)** — C1, A4
Abrir/editar OS não toca no estoque; a peça nasce `PLANEJADO`. O saldo só baixa no **consumo
confirmado** (OS em execução, com saldo, idempotente por chave única `(item, evento)`); devolução e
cancelamento da OS devolvem. A edição da OS reconcilia **linha a linha** (peça consumida fica
travada; “executado” e percentual do serviço sobrevivem — antes eram zerados a cada edição). O livro
de estoque é **imutável**: sem editar/excluir; correção = **estorno** com motivo (só ADMIN) e
`AJUSTE` com sentido (+/−) e motivo obrigatórios. Saldo = soma dos `delta`.

**Dinheiro** — C6, C7 (parte), A5, A7
Totais da OS e do orçamento são **recalculados no servidor** dos itens. Toda OS tem parcelas
(à vista = 1); baixar parcela é transacional (linha travada + chave `PARCELA:{id}` única no caixa).
O caixa pertence a quem o abriu: sair do sistema só fecha o caixa do **dono**, o que também libertou
o técnico que ficava preso sem conseguir sair.

**Orçamento (`Orcamento`)** — C4, A1
Estados `pendente → aprovado | reprovado | cancelado`, `reprovado → pendente` (reabrir). Só
`pendente` aprova/recusa; aprovar é idempotente, bloqueia orçamento vencido ou sem itens, exige que
os itens somem o total, cria a OS **uma vez**, já `ABERTA`, com o técnico, sem baixar estoque.
A edição agora **grava os itens** e não muda o estado. Numeração ORC-<id> pelo servidor. A lista
passou a mostrar sucesso/erro das ações (antes não mostrava nada).

**Outros**

- `Env.php` sem `putenv()` (não é seguro entre threads do Apache): corrigia 500s intermitentes
  “DB_HOST não definida” sob requisições simultâneas — achado no log do Apache.
- Sidebar única da equipe (técnico = atendente + “Meu painel”); “Produtos” só para ADMIN (o atendente
  via o link e recebia 403); “Criar” disponível ao técnico.
- Painel de trabalho do técnico: qualquer OS atribuída (o 2º técnico recebia 404), alternância
  “minhas/todas”. Gráfico semanal com os dias reais. SMTP com timeout de leitura. Código morto
  removido (`fecharSePendenteExpirado` consultava uma coluna que nunca existiu a cada requisição).
- Testes: `Fixtures` (dados coerentes — as antigas só funcionavam com o MariaDB sem modo estrito),
  `OsTestCase`, e listener que libera conexões (o PHPUnit estourava `max_connections`).

**Decisões tomadas sem bloquear (revisar se discordar)**
- A janela de horário (07–18h) segue só para o atendente — é restrição de acesso, não de capacidade.
- A equipe pode agir em **qualquer** OS (a trava “só o técnico dono” era regra de tela, não segurança);
  quem fez o quê fica na trilha.
- “Conferir” é do ADMIN. Orçamento vencido não se aprova (estende-se a validade na edição).

**Pendências conhecidas desta etapa** (entram nas próximas)
- O dashboard ainda chama de “faturamento” a soma das OS entregues (Etapa 4 separa bruto/recebido).
- A consulta de estoque para a equipe (sem custo) e a solicitação de peças (Etapa 3).
- Fotos de evidência e upload (Etapa 2/3).

### Etapa 2 — Estrutura operacional ✔ (validada)

**Validação:** PHPUnit **186 testes / 1.149 asserções verdes** (eram 151) + roteiro de aceitação por
HTTP real (Apache + MySQL de desenvolvimento, usuários `zz_audit_*`): **99 verificações, 0 falhas**, e o
log do Apache sem nenhum aviso/erro de PHP durante toda a bateria. O roteiro cobre os três fluxos
ponta a ponta, repetindo cada clique crítico (idempotência), tentando burlar pelo servidor (papel,
CSRF, estado errado) e conferindo que nada duplica cliente, veículo, orçamento ou OS.

**Modelo novo** (`database/etapa2_operacao.php`, idempotente, aplicado nos dois bancos)
`ocorrencias` (relato do cliente sobre um veículo, da chegada à OS), `avaliacoes_tecnicas` +
`avaliacao_itens` (versionadas: reavaliar cria a v2 e a v1 fica), `autorizacoes_cliente`,
`clientes.cadastro_status` (`COMPLETO`/`PROVISORIO`), `veiculos.dados_incompletos`,
`ordens_servico.ocorrencia_id` e `valor_autorizado`, `orcamentos.ocorrencia_id/versao/enviado_em`.
Colunas geradas únicas (`uniq_telefone`, placa normalizada) fazem o **banco** recusar duplicidade,
não só a tela.

**Estados e trilha** (`OcorrenciaFluxo`, mesma disciplina da OS: transição inexistente é recusada,
repetir é idempotente, tudo vai para `historico_estados`)
`AGUARDANDO_AVALIACAO → EM_AVALIACAO → AGUARDANDO_ORCAMENTO → AGUARDANDO_APROVACAO → EM_EXECUCAO →
ENCERRADA` (+ `EM_ESPERA`, `CANCELADA`). A OS, ao ser criada, entregue ou cancelada, sincroniza a
ocorrência **na mesma transação**: não há como as duas divergirem.

**Os três fluxos obrigatórios**

1. **Cliente cadastrado, com orçamento** — chegada → avaliação técnica (defeitos + serviços/peças
   recomendados + tempo estimado) → *gerar orçamento a partir da avaliação* (o atendente só confere
   preços) → *marcar como enviado* → registrar a aprovação do cliente **com canal e evidência** → OS
   aberta uma única vez, com `valor_autorizado` = total aprovado.
2. **Atendimento rápido** — só **primeiro nome + telefone + placa**. O cliente nasce `PROVISORIO` e o
   veículo `dados_incompletos`; ambos aparecem sinalizados nas listas. Telefone ou placa já existentes
   **não criam nada**: a tela sugere “usar este cadastro” já preenchendo cliente, veículo e relato (e a
   transação inteira é revertida, sem cliente órfão). Depois: *completar o cadastro* (salvar com nome
   completo regulariza) ou **unificar** com um cadastro existente (só ADMIN; movem-se veículos,
   ocorrências, orçamentos, OS e autorizações; o provisório é desativado, nada é apagado).
   O cliente que “aceita qualquer valor” exige **autorização registrada** (escopo + canal + evidência
   + limite opcional) antes da *OS direta*. **Isso não é cheque em branco:** o teto da OS é o limite
   acordado ou, se não houve, o **total avaliado**; se o avaliado já passa do limite, a OS não abre;
   se o serviço crescer, a OS **não vai para conferência** enquanto o total passar do autorizado ou
   houver pedido sem resposta — a equipe pede uma *autorização complementar* e registra a resposta
   (canal + evidência; aprovar sobe o teto, recusar mantém o bloqueio).
3. **Atendimento remoto** — entra pelo mesmo fluxo com `origem = REMOTO`. **Não há central de conversa
   nem envio de WhatsApp ainda** (Etapa 5): a tela não finge nenhuma mensagem enviada.

**Urgência e fila**

- Quem atende **declara** a urgência (motivo obrigatório → prioridade `ALTA`); só o **administrador
  valida** (`URGENTE`). O técnico não valida (403).
- `FilaOperacional` pontua: prioridade (1000/400/100/0) + tempo de espera (com teto) + serviço rápido
  (≤ 30 min) − peça faltando. **Orienta, não trava**: qualquer técnico livre pode abrir qualquer item.
  Só a **ordem manual do administrador**, com justificativa obrigatória (gravada na trilha), sobrepõe a
  pontuação — e pode ser devolvida à ordem automática. Mostra quem está livre (capacidade 2 serviços).
  O painel do técnico ganhou a aba **Avaliações** e a *sugestão da fila*.

**Permissões** — o técnico faz tudo o que o atendente faz (registrar chegada, avaliar, orçar, registrar
autorização, abrir OS direta, pedir complemento) **e continua sem fechar a OS** nem conferir. Admin-only:
validar urgência, reordenar fila, unificar cadastros. Tudo checado no servidor (`PermissaoTest` segue
exigindo paridade técnico = atendente, rota a rota).

**Correções colaterais**: `OrdemServico::getById` não trazia `ocorrencia_id`/`valor_autorizado`;
aprovar/recusar orçamento agora aceita canal/evidência e volta para a ficha da ocorrência quando veio
dela; editar o veículo com marca/modelo reais tira a marca de “dados incompletos”.

**Decisões tomadas sem bloquear (revisar se discordar)**
- A aprovação de orçamento pelo atendimento continua “registrada por alguém da equipe”; a aprovação
  pelo próprio cliente (link seguro) é a Etapa 5.
- O cadastro provisório só pede nome (primeiro) e telefone celular válido; CPF/endereço ficam para a regularização.
- “Tempo estimado” da avaliação alimenta a fila; sem estimativa, o item não ganha o bônus de serviço rápido.

**Pendências conhecidas desta etapa**
- Fotos/anexos de evidência nas avaliações (Etapa 6: armazenamento seguro fora da pasta pública).
- Solicitação de peças a partir da avaliação (Etapa 3); aviso por mensagem ao cliente (Etapa 5).
- “Enviar” o orçamento hoje **registra** o envio; o envio real por WhatsApp/e-mail depende da Etapa 5.

### Etapa 3 — Estoque e compras ✔ (validada)

**Validação:** PHPUnit **250 testes / 1.521 asserções verdes** (eram 186; +64: reserva 12, solicitação 14,
compras 31, notificações 7) + roteiro de aceitação por HTTP real: **102 verificações, 0 falhas**; as
Etapas 1 (72) e 2 (99) foram reexecutadas depois e seguem sem falha. Log do Apache sem aviso nem exceção.
(Um falso alarme do roteiro da Etapa 1 — o PHP de linha de comando estava em Europe/Berlin e a janela
de horário do atendente usa America/Sao_Paulo — foi corrigido no roteiro, não no sistema.)

**Modelo novo** (`database/etapa3_estoque_compras.php`, idempotente, aplicado nos dois bancos)
`notificacoes`, `solicitacoes_peca`, `fornecedores`, `cotacoes_compra`, `pedidos_compra`,
`pedido_compra_itens`, `recebimentos_compra`, `recebimento_itens`; `ordem_servico_produtos.reservado_em/por`;
evento `RECEBIMENTO` no livro de estoque.

**Regra central, testada:** *comprar não é ter.* Montar, realizar ou cancelar um pedido **nunca** mexe no
estoque. O saldo só sobe no **recebimento confirmado**, e só pelo que chegou (parcial soma só a parte).

- **Movimentação automática (9.1):** já vinha da Etapa 1 (consumo confirmado, devolução, cancelamento,
  sem baixa dupla). Agora também há **reserva** (`RESERVADO`): o saldo físico não muda, mas a quantidade
  sai do *disponível* das outras OS (disponível = estoque − reservado); duas OS não prometem a mesma
  última peça (a trava do produto serializa as reservas simultâneas). Consumir uma peça reservada usa a
  própria reserva; uma só prevista respeita as das outras OS. Liberar, cancelar a OS ou mudar a
  quantidade desfazem a reserva. A tela da OS e a do técnico avisam **“Sem saldo”** antes de confirmar o
  consumo, com o atalho *Solicitar peça* (já preenchendo peça, OS e quantidade).
- **Consulta de estoque da equipe (9.1):** saldo, reservado, disponível, local e situação
  (disponível / acabando / sem estoque) — **sem custo, preço de venda nem valor de inventário**
  (testado por HTTP). O cadastro de produtos continua só do administrador.
- **Solicitação de peças (9.2):** técnico, atendente e administrador criam (peça do cadastro ou fora dele,
  quantidade, motivo, OS, urgência, observações); o saldo conhecido é o **do cadastro**, não o do
  formulário. Estados `SOLICITADA → EM_ANALISE → APROVADA_COMPRA → PEDIDO_REALIZADO →
  PARCIALMENTE_RECEBIDA → RECEBIDA` e `CANCELADA`, com trilha. Duplicata em aberto (mesmo solicitante,
  peça e OS) não se cria — índice único no banco. Ao registrar, **avisa os administradores e o técnico
  responsável pela OS** (nunca quem pediu, nunca duas vezes). A equipe vê o andamento, **não** vê
  fornecedor, cotação nem preço.
- **Compras (9.3, só administrador):** fornecedores (site só http/https, documento validado e único,
  desativa em vez de apagar); painel de **necessidades consolidadas** por peça (somando solicitações e
  mostrando o que está no mínimo sem ninguém ter pedido); **cotações** comparadas por total (itens +
  frete), menor total e menor prazo, com a vencida fora da disputa; pedido de compra com
  condição/forma/vencimento/frete/entrega previstos e **o número do pedido no site do fornecedor**;
  recebimento parcial ou integral **idempotente** (chave por envio do formulário), que recusa mais do que
  falta chegar, grava a entrada no livro (origem `COMPRA`, custo real da nota), recalcula o **custo médio
  ponderado** e reajusta as solicitações atendidas (avisando quem pediu). Recebimento errado se desfaz
  por **estorno justificado** (só se as peças ainda estiverem no estoque), que devolve as quantidades ao
  pedido e às solicitações; o estorno genérico da tela de movimentações *não* desfaz recebimento.
  Pedido que já recebeu não se cancela (estorna-se ou se encerra com saldo, com motivo).
- **Notificações internas:** sino na barra superior com contagem, tela de avisos, marcar como lido leva ao
  assunto; cada pessoa só lê/marca as próprias (testado). É o canal *dentro do sistema*; WhatsApp/e-mail
  saem dos mesmos fatos na Etapa 5.

**Integrações de compra — verificado, não presumido**

| Fornecedor | O que a pesquisa encontrou | O que o sistema faz |
|---|---|---|
| Mercado Livre | A API pública documentada (developers.mercadolivre.com.br) é voltada a **vendedores e integradores** (pedidos recebidos, anúncios, mensagens). Não encontrei API de **compra** programática para quem compra. | Atalho que **abre a busca** no site; cotação e pedido são registrados à mão. |
| DPK | Existe um portal de vendas online para varejo (KDaPeça, com análise de crédito, 2ª via de boleto, XML/DANFE). **Não achei documentação pública de API.** | Cadastro do fornecedor com o link do portal; pedido feito no portal, número anotado no Mecanize. |
| DPL | **Não consegui identificar** uma distribuidora brasileira “DPL” pelo nome (os resultados eram de empresas homônimas). | Precisa da razão social/CNPJ ou do contato comercial; enquanto isso, o mesmo fluxo manual. |

Nada disso é “integração”: não há botão de compra automática e a tela diz isso. Se algum fornecedor
oferecer API/EDI (por exemplo TecCom/portal B2B com credenciais), entra como adaptador novo atrás do mesmo
pedido de compra — a pedido e com a credencial na mão.

**Decisões tomadas sem bloquear (revisar se discordar)**
- Custo da peça = **média ponderada** pelo saldo no momento do recebimento (alternativa: último custo).
  O estorno de recebimento não “desfaz” a média.
- Incluir uma solicitação num pedido já é a aprovação dela. Peça **sem cadastro** não entra em pedido:
  o produto é cadastrado antes (senão não haveria onde dar entrada no estoque).
- “Estoque mínimo” usa o **disponível** (estoque − reservas) para sugerir reposição.
- Técnico e atendente têm as mesmas permissões aqui (consultar, solicitar, reservar/liberar); o botão de
  reservar está na tela completa da OS — a tela mobile do técnico (Etapa 6) ganha o seu.

**Pendências conhecidas desta etapa**
- **Vínculo com contas a pagar** (9.3): o pedido já guarda condição, forma, vencimento e `conta_pagar_id`;
  o título é gerado na Etapa 4, junto do módulo financeiro.
- Anexo da nota fiscal do recebimento (Etapa 6: armazenamento seguro de arquivos).
- Aviso por WhatsApp/e-mail ao técnico quando a peça chega (Etapa 5).
- Paginação da consulta de estoque (hoje até 300 linhas por busca; o catálogo atual tem dezenas).

### Etapa 4 — Financeiro ✔ (validada)

**Validação:** PHPUnit **306 testes / 1.934 asserções verdes** (eram 250; +56: contas a pagar e cartões 30,
contas a receber 12, indicadores 14) + roteiro de aceitação por HTTP real: **108 verificações, 0 falhas**
(inclui envio de arquivo de verdade). As Etapas 1 (72), 2 (99) e 3 (102) foram reexecutadas e seguem
sem falha; log do Apache sem aviso nem exceção.

**Modelo novo** (`database/etapa4_financeiro.php`, idempotente, aplicado nos dois bancos)
`cartoes_corporativos`, `faturas_cartao`, `despesas`, `contas_pagar_parcelas`, `pagamentos_pagar`,
`comprovantes_financeiros`; `ordem_servico_parcelas` ganhou taxa, nº de pagamentos e estorno;
`pedidos_compra.conta_pagar_id` agora tem FK para a despesa.

**A regra que organiza tudo — compra, parcela e fatura são UMA despesa.**
A *despesa* é o evento (registrado uma vez); as *parcelas* são as obrigações; o *pagamento* é o dinheiro
que saiu. Compra de R$ 600 no cartão em 3× vira 1 despesa, 3 parcelas de R$ 200, cada uma na fatura do
seu mês; **pagar a fatura quita as parcelas dela com uma saída só** e não cria despesa nova. Por isso
“a pagar” soma as parcelas pendentes e “despesas pagas” soma o que foi quitado — nenhuma compra aparece
duas vezes em nenhum dos dois (testado: pagar a 1ª fatura de R$ 200 tira R$ 200 do a pagar e conta
R$ 200 de despesa paga, não 400 nem 800). Um índice único no banco impede dois pagamentos ativos do
mesmo alvo; cada pagamento leva chave de idempotência.

- **Cartões e faturas (4.2):** apelido, emissor, bandeira, 4 últimos dígitos (opcional), limite, dias de
  fechamento e vencimento (1–28). **Não há campo para número, validade ou código do cartão** (a
  auditoria confere a estrutura da tabela) **nem conexão bancária simulada**: tudo é registro manual.
  Calendário documentado (compra até o dia do fechamento entra na fatura do mês; a parcela k cai k−1
  meses depois; fatura quitada nunca recebe compra nova). Limite *comprometido* = parcelas ainda não
  pagas, inclusive futuras; compra que passa do limite é registrada (já foi feita) mas o excesso fica
  visível. Fatura só se paga depois de fechada. Situação calculada: aberta · fechada · vencida · paga.
- **Contas a pagar (4.1):** despesa à vista ou parcelada (cartão, boleto, transferência), parcelas
  mensais que fecham centavo por centavo (R$ 100 em 3× = 33,33 + 33,33 + 33,34; 31/01 + 1 mês = 28/02),
  pagamento com data (nunca futura) e, se quiser, **saída lançada no caixa aberto na mesma transação**
  (sem caixa aberto nada é gravado), **estorno** justificado (parcelas voltam a pendentes, fatura volta a
  aberta, devolução entra no caixa como lançamento novo), cancelamento (só se nada foi pago),
  histórico completo e **comprovantes**.
- **Comprovantes:** PDF/JPG/PNG/WEBP até 5 MB, tipo decidido pelo **conteúdo** (um `.php` renomeado para
  `.png` é recusado), nome aleatório, guardados em `storage/` — **fora da pasta pública, negados pelo
  Apache** (testado: acesso direto = 403) e entregues só ao administrador por rota própria
  (`nosniff`, CSP restritiva). A mesma infraestrutura serve às fotos de evidência da Etapa 6.
- **Pedido de compra → conta a pagar (9.3):** “Lançar conta a pagar” no pedido gera **uma** despesa por
  pedido (índice único), categoria estoque, valor padrão = total do pedido (ajustável ao da nota),
  com forma, parcelas e vencimentos; cancelar a despesa libera o pedido para lançar outra. Rascunho e
  pedido cancelado não geram conta.
- **Contas a receber (4.3):** só entram parcelas de OS **já cobradas** (conferidas); OS em execução não
  é “a receber”. Filtros por cliente, OS/placa, período, forma e situação (a vencer · vencida · recebida).
  R$ 1.000 em 5×: com a 1ª liquidada, **restam 4 parcelas / R$ 800** (testado). **Taxa da operadora**
  registrada à parte, sem mudar o valor da parcela. **Estorno do recebimento** (só administrador,
  justificado, exige caixa aberto): a parcela volta a ser devida, a taxa sai, a devolução vira saída no
  caixa; a mesma parcela pode ser paga de novo e cada pagamento tem a sua chave no caixa.
- **Indicadores (4.4)** — uma definição para cada número, repetida na própria tela (“Como lemos cada
  número”): faturamento bruto (OS entregues; não é recebido) · recebido bruto, **taxas** e líquido ·
  a receber (vencido / a vencer) · a pagar (vencido / próximos 7 dias) · despesas pagas (contas +
  comissões/salário + saídas avulsas do caixa, sem repetir as que pertencem a uma conta) · fluxo
  realizado · **fluxo projetado** mês a mês com bloco de atrasados · resultado operacional estimado
  (competência) · **lucro efetivo** (só o que já entrou das OS do período − custos delas − despesas
  operacionais pagas). Compra de **estoque não é despesa de resultado**: o custo da peça entra quando
  ela é consumida na OS, senão contaria em dobro. Parcelamento a receber **não** é tratado como lucro
  (R$ 500 em 5× com R$ 200 de custo: após a 1ª parcela o lucro efetivo é −R$ 100; após a 5ª, +R$ 300).
  O início do administrador passou a mostrar *Faturamento bruto*, *Recebido no mês* e *A receber*
  (antes o “faturamento” sozinho dava a impressão de dinheiro no bolso); o técnico não vê números
  financeiros.

**Decisões tomadas sem bloquear (revisar se discordar)**
- Compras no cartão só pagas por **fatura inteira** (sem pagamento parcial/mínimo).
- O lucro efetivo é **conservador** de propósito (custos já ocorridos × só o que já entrou).
- Comissões e salários continuam sendo pagos pela tela de comissões (fechamento); aqui entram nas
  despesas pagas e no resultado, mas **não** geram saída no caixa automaticamente.
- Taxa de maquininha é **digitada** no recebimento (não calculada por tabela de operadora).

**Pendências conhecidas desta etapa**
- Pagamento online/Pix parcelado por provedor, com confirmação real: Etapa 5 (provedor e webhook).
- Conciliação bancária e importação de fatura/extrato: não há integração bancária e nenhuma foi simulada.
- Gráficos do painel financeiro (hoje tabelas e cartões); DRE detalhada por categoria.
- Compra no cartão por telas de balcão do estoque (fluxo antigo) continua existindo para o pagamento na hora.

---

### Etapa 5 — Comunicação e portal do cliente ✔ (validada)

**Validação:** PHPUnit **424 testes / 2.923 asserções verdes** (eram 306; +118: WhatsApp 10, central e fila 35,
pagamento online 31, ganchos de mensagem 10, portal 17, webhooks 14, rotas 1) + roteiro de aceitação por HTTP
real (`aud_etapa5`): **169 verificações, 0 falhas**, rodado num servidor PHP isolado (e-mail **desligado**,
pagamento **simulado**, segredos de teste — o `.env` do usuário não foi tocado). As Etapas 1 (72), 2 (99),
3 (102) e 4 (108) foram reexecutadas no Apache e seguem sem falha; log sem aviso nem exceção.

**Modelo novo** (`database/etapa5_comunicacao_portal.php`, idempotente, aplicado nos dois bancos)
`consentimentos_comunicacao`, `conversas`, `mensagens`, `webhook_eventos`, `portal_tokens`, `decisoes_portal`,
`cobrancas_online`, `cobranca_parcelas`; `ordem_servico_parcelas` ganhou origem do recebimento e vínculo com a
cobrança; `ordens_servico.previsao_conclusao`.

**Princípio: mudança de estado REAL → mensagem, nunca o contrário.** Os models chamam
`Comunicacao::evento()` **dentro da transação** que muda o estado, com a chave do fato: se a mudança desfaz,
a mensagem some; repetir a ação não gera segunda mensagem; e o **envio pela rede acontece fora** da
transação (fila `PENDENTE`, reserva por *compare-and-swap*, até 3 tentativas). Eventos ligados:
veículo recebido · avaliação iniciada (só a primeira) · orçamento disponível (só quando **enviado**) ·
aprovado · recusado · serviço iniciado · autorização adicional · pronto para retirada (com saldo e link) ·
OS concluída · pagamento confirmado/estornado. Voltar a OS para a execução e reconferir é um **fato novo**
(avisa de novo); clicar duas vezes não. Os e-mails soltos dos controllers (aprovação, veículo pronto) foram
**substituídos** por esses eventos.

**Nada é fingido.** O resultado de cada mensagem fica visível na conversa: *na fila · enviada · entregue ·
lida · falhou (com o erro) · não enviada — canal não configurado · não enviada — sem consentimento · não
enviada — cliente sem contato · registro manual*. Sem canal configurado a mensagem fica registrada com o
motivo, e **nenhuma é marcada como enviada sem passar por um canal real** (verificado por HTTP).

- **Central de conversas (EQUIPE — técnico = atendente):** lista com abas, filtros (responsável, não lidas,
  busca por cliente/telefone/placa/OS), contador de não lidas no menu; conversa com histórico, resposta,
  **registro de contato por fora** (ligação/balcão, marcado como manual), nota interna, atribuição,
  encerrar/reabrir, **ligação a veículo/atendimento/orçamento/OS (só do mesmo cliente)**, e associação de
  “número não identificado” ao cadastro certo (nada é cadastrado sozinho). Resposta por WhatsApp respeita a
  **janela de 24 h**: fora dela só vale template aprovado e a tela explica.
- **Consentimento (LGPD):** livro de decisões (aceitou / pediu para parar, origem, data, quem registrou), nunca
  editado. WhatsApp só com autorização; e-mail até o cliente pedir para parar. Autorização na chegada (caixa
  de seleção), na ficha do cliente, pelo portal, e **“PARAR” no WhatsApp vira descadastro registrado**
  (frase que apenas *contém* a palavra não conta).
- **Portal do cliente (sem login):** link com token de 256 bits (no banco só o **hash**), validade
  configurável (`PORTAL_VALIDADE_DIAS`, padrão 30), revogável. Um link vale para o **caso** (atendimento ↔
  orçamento ↔ OS) **do mesmo cliente** e a cada ação o servidor reconfere o alcance. Link inválido, expirado
  ou revogado dá **a mesma tela genérica** (não diz qual). Sem cache, sem indexação. Mostra só nome, veículo,
  etapas reais do histórico (**sem GPS nem localização inventada**), previsão de conclusão (como estimativa),
  itens e valores do orçamento, orientação de retirada, parcelas e preferências de contato. **Nunca** CPF,
  telefone, e-mail, custos, comissões, técnico nem motivos internos.
  - *Orçamento:* aprovar exige marcar a confirmação; só se decide o que a oficina **enviou** e ainda vale.
    Reaproveita `Orcamento::aprovar` (OS criada uma vez, autorização com canal **PORTAL**, evidência com
    IP e link, trilha de auditoria com origem PORTAL) e registra a decisão (`decisoes_portal`: IP e
    navegador). A equipe é notificada; repetir não duplica nada; falha não deixa decisão fantasma.
  - *Valor adicional:* o cliente autoriza/recusa pelo portal; o teto da OS sobe uma vez só.
  - *Pagamento online:* escolhe as parcelas e vai ao checkout do provedor; **nenhum dado de cartão passa pelo
    Mecanize**. Uma cobrança ativa por parcela (índice único); pedir de novo devolve a mesma.
- **Pagamento por provedor com webhook:** todo evento entra por `CobrancaOnline::receberWebhook` — o real e o
  simulado pelo mesmo caminho. **Assinatura do corpo bruto antes de qualquer leitura** (HMAC; Mercado Pago:
  `x-signature` com `ts` recente e `data.id`); sem ela, 401 e nada é gravado. Evento registrado **uma vez**
  (`webhook_eventos`, único por provedor+evento); reenvio = 200 “duplicado”. O estado verdadeiro do Mercado
  Pago vem da consulta autenticada à API, não do corpo. Valor diferente do cobrado **não baixa nada** e avisa o
  administrador. Recebimento online = parcela `ONLINE` ligada à cobrança, **sem lançar no caixa**; estorno
  do provedor devolve a parcela ao devedor; pagamento online de parcela já paga no balcão não baixa em dobro
  e pede a devolução. Webhooks são as **únicas** rotas sem CSRF (`csrf => false` só em rota pública; teste
  guarda a lista) e rodam sem sessão.
- **WhatsApp Cloud API (oficial):** adaptador de envio (texto e template), verificação do webhook, assinatura
  `X-Hub-Signature-256`, recepção idempotente por id de mensagem, recibos (enviada → entregue → lida, **nunca
  regridem**), telefone normalizado. Recepção só aceita corpo assinado.
- **Equipe:** bloco “Link do cliente” nas fichas do atendimento, orçamento e OS (gerar para copiar — o link
  bruto aparece **uma vez** —, enviar pelo canal do cliente, revogar, histórico de acessos); previsão de
  conclusão na OS; tela **Integrações** (administrador): estado de cada integração com o **nome** das
  variáveis que faltam (nunca valores), URLs de webhook a cadastrar, balanço das mensagens, templates.
- **Envio:** `php bin/enviar_mensagens.php` (agendador, a cada minuto) **e** envio ao fim das requisições da
  equipe quando há canal e fila (depois de entregar a página e soltar a sessão).

**Achados da validação que foram corrigidos**
- Refazer o plano de pagamento (conferência) **falhava** se a parcela já tivesse tido qualquer cobrança online
  (a chave estrangeira impedia apagar as parcelas devidas). Agora a cobrança em andamento é cancelada, o
  vínculo é desfeito e, se o cliente pagar mesmo assim no provedor, o pagamento fica registrado **sem baixar
  às cegas** e o administrador é avisado (`paga_sem_parcela`). Coberto por testes.
- Um *closure* guardado dentro de `Comunicacao` formava ciclo de referência e segurava a conexão do banco até o
  GC, esgotando conexões na suíte de testes; tornou-se `static`.
- A ficha do cliente lia a coluna errada do histórico de autorizações (aviso do PHP) — corrigido.
- Conferida a regra “técnico não fecha a OS”: ele **não** libera a conferência (403, testado); a entrega do
  veículo ao cliente é da equipe inteira (técnico = atendente), como definido nas Etapas 1–2.

**Decisões tomadas sem bloquear (revisar se discordar)**
- E-mail é canal padrão (o cliente pode pedir para parar); WhatsApp exige autorização registrada.
- Um canal por mensagem (WhatsApp se possível e autorizado; senão e-mail) — nunca os dois, para não duplicar.
- Aprovação pelo portal exige confirmação marcada e vale uma vez; mudar de ideia depois é com a oficina.
- Parcelamento no cartão pelo portal limitado por `PAGAMENTOS_MAX_PARCELAS_CARTAO` (padrão 1 = à vista).

**O que depende de configuração/contratação externa (nada foi inventado)**

| Item | O que falta | Estado hoje |
|---|---|---|
| WhatsApp | Conta Business, número, token, App Secret, *verify token*, **webhook público HTTPS**, **templates aprovados** na Meta (nome em `WHATSAPP_TEMPLATE_<EVENTO>`) | Adaptador completo e testado com transporte falso; **nunca falou com a Meta de verdade**. Sem as variáveis, mensagens ficam registradas como “não enviadas”. |
| E-mail | SMTP já usado pelo sistema | Funciona se `MAIL_*` estiver preenchido. |
| Pagamento online | Escolher/contratar provedor, chaves, **webhook público HTTPS** | Provedor **simulado** (só com `PAGAMENTOS_PERMITIR_SIMULADO`/`APP_DEBUG` + segredo) prova o fluxo todo. **Mercado Pago**: implementado pela documentação pública, **não exercitado contra conta real** (testado com respostas descritas na documentação). |
| URL pública | `APP_URL` com HTTPS | Sem ela os links usam o endereço da requisição (serve para teste local, não para o cliente real). |
| Agendador | Tarefa a cada minuto (`bin/enviar_mensagens.php`) | O envio ao fim das requisições cobre o uso diário, mas não garante nova tentativa fora do horário de uso. |

**Pendências conhecidas desta etapa**
- Lembretes automáticos de pagamento/vencimento e de orçamento sem resposta (precisam de agendador e política de
  frequência definida pela oficina) — **não implementados**.
- Anexar fotos/evidências do serviço ao portal: Etapa 6 (infraestrutura de arquivos já existe).
- Pagamento parcial de uma parcela, boleto/Pix copia-e-cola próprios e conciliação automática de taxas reais.
- Envio de mídia (imagem/PDF) pelo WhatsApp e recebimento de anexos do cliente (hoje chega só o texto, com aviso
  do tipo recebido).

---

### Etapa 6 — Perfis, celular, placa e consulta veicular ✔ (validada)

> **INCIDENTE DE AMBIENTE (09/10/2026) — RESOLVIDO.** O MariaDB do XAMPP parou de subir: o log de transações do InnoDB
> (`ib_logfile0`) ficou corrompido (`Missing MLOG_CHECKPOINT …`). Sem tocar nos originais, salvei cópia física
> (`C:\xampp\mysql\data_backup_pre_recovery_20261009`) e dumps lógicos (`C:\xampp\mysql\recovery_dump_20261009\`). Com autorização
> do dono: `data` → `data_corrompida` (preservada), `data` novo a partir de `C:\xampp\mysql\backup` e importação de `mecanize_db`,
> `mecanize_db_test`, `lanchonete` e `phpmyadmin`. **Conferido:** um novo dump de cada banco é idêntico ao dump original
> (ignorando comentários e `AUTO_INCREMENT`). O backup pós-etapa 6 está em `recovery_dump_20261009\pos_etapa6\`.
>
> **Por que "o SQL não fica de pé" (achado com teste, não suposição):** neste computador a gravação **sem buffer** do InnoDB (padrão no
> Windows) trava. Uma importação parou em `CREATE TABLE` (escritas assíncronas pendentes que nunca completam) e o servidor não
> desligava: o processo ficava vivo sem aceitar conexões, até com só `innodb_use_native_aio=0`. Com `innodb_flush_method=normal` +
> `innodb_use_native_aio=0`: importar os 4 bancos leva ~2 s, a suíte inteira roda e o desligamento termina em 0,7 s (vários ciclos).
> Por isso essas duas linhas foram **acrescentadas ao `C:\xampp\mysql\bin\my.ini`** (original salvo ao lado, em
> `my.ini.original_antes_do_ajuste_20261009`). **Não está provado** que isso causou a corrupção original; está provado que sem elas o
> servidor trava aqui. O antivírus (Kaspersky, processo `avp`) está ativo e é o suspeito natural; **não** testei com ele desligado.
> Também **não** apliquei o aumento do log (`innodb_log_file_size`) nem `DB_PORT`: não foram pedidos.
>
> **Backup diário (pedido do dono):** tarefa do Agendador de Tarefas "Mecanize - Backup diario", todo dia às 12:30 (se o PC estiver
> desligado, roda quando ligar), executa `database/backup_diario.php`: dump via PDO, confere que o arquivo está inteiro, guarda 14 dias em
> `database/backups/diario/` e registra em `backup_diario.log` (sai com erro se o banco estiver parado). O MySQL precisa estar ligado
> nesse horário. Backups feitos à mão em `database/backups/` nunca são apagados por ele.
>
> **Lixo achado e corrigido nos testes:** `mecanize_db_test` tinha 172 itens de orçamento órfãos e a suíte deixava ~98 por execução
> (`Fixtures::limpar` apagava o orçamento sem os itens). Com o contador de ids reiniciado pela restauração, orçamentos novos
> reaproveitavam ids com itens soltos e a aprovação falhava de forma intermitente (7, 4 falhas em execuções seguidas). Corrigido em
> `tests/Fixtures.php`; depois da suíte, 0 órfãos.
>
> **Sobras minhas** (podem ser apagadas quando você confirmar que está tudo bem), em `C:\xampp\mysql`: `data_trial_claude`,
> `data_tentativa1_claude`, `data_tentativa2_claude`, `bin\my_recovery_claude.ini`, `bin\my_import_claude.ini`, `bin\my_teste_claude.ini`.
> Usuários/senhas do MySQL criados à mão (se houver) estavam em `data_corrompida\mysql` e não vão nos dumps.

**Validado nesta etapa (sem banco, no Chrome com emulação de celular e no PHPUnit sem banco)**

| Entrega | Como foi validado |
|---|---|
| **Leitura de placa por foto (5.1)** — `LeituraPlaca` + `PlacaController` + `public/assets/js/placa-ocr.js` | PHPUnit **10 testes** (formato Mercosul/antigo, confiança, alternativas, falhas 401/403/429/5xx/timeout/sem rede viram mensagem compreensível sem vazar detalhe, arquivo que não é foto, teto de tamanho, teto de leituras por sessão) + Chrome **32 verificações** do componente + **23 verificações** no formulário REAL de veículo |
| **Consulta de dados do veículo (5.2)** — `ConsultaVeicular` + `ConsultaVeiculoController` + botão "Buscar dados pela placa" | PHPUnit **12 testes** + Chrome (mesmo roteiro do formulário) |
| **Fotos de evidência: redução no aparelho e tela** (`foto-upload.js`, seção na OS do técnico) | Chrome **16 verificações** (foto 4000×3000 → JPEG 1280×960, 238 KB → 41 KB; arquivo não-imagem recusado; botão trava no envio; alvos ≥ 44 px) |
| Armazenamento de arquivos (`Armazenamento`, filtro "só imagem") | PHPUnit **6 testes** |
| Portabilidade Windows → Linux (`PortabilidadeTest`) | PHPUnit **3 testes** — achou e corrigiu **`site/home` e `categoria/*`** (a página inicial e as telas de categoria **não abririam em servidor Linux**) |
| Tela do técnico e demais telas no celular | Renderização no Chrome a 390 px com dados sintéticos; estouro horizontal = 0 em todas |

Rotas e menus: `PermissaoTest` (12) e `RotasTest` (3) seguem verdes com as rotas novas (`lerPlaca` ampliada,
`consultarPlacaVeiculo`, `enviarEvidenciaOS`, `verEvidenciaOS`).

**Leitura de placa (5.1) — o que mudou**
- **A leitura é uma SUGESTÃO.** O campo só é preenchido depois de "Usar esta placa" (ou de a pessoa corrigir e
  confirmar). Antes, preenchia sozinho e mostrava `alert()`. Agora a tela mostra a placa lida em destaque, o
  formato (Mercosul / antiga / fora do padrão), a confiança do serviço e as alternativas; leitura incerta (< 85 %,
  sem nota ou formato impossível) aparece em alerta e **placa de formato impossível não tem botão "usar"** (só
  corrigir/digitar).
- **Celular:** a foto é reduzida no aparelho (lado maior 1280 px, JPEG, respeitando a rotação da câmera) antes de
  subir — fotos de câmera passavam do limite de 2 MB e falhavam. Tempo limite de 25 s no aparelho e 15 s no servidor
  (antes o servidor podia travar indefinidamente), botão **Cancelar**, **Tentar de novo** e **Digitar a placa**
  sempre disponíveis. A mesma leitura entrou também no **atendimento rápido** (chegada), onde o celular mais é usado.
- Servidor: `regions=br` no pedido, credencial de exemplo do `.env.example` não conta como configurada, código de
  falha estável (`NAO_CONFIGURADO`, `SEM_PLACA`, `LIMITE`, `TEMPO_ESGOTADO`, `INDISPONIVEL`, `IMAGEM_INVALIDA`,
  `TAMANHO`), teto de 15 leituras por sessão em 10 min (cada leitura custa no plano). Tipo da imagem decidido pelo
  conteúdo; nunca devolve `$_FILES` nem caminhos.
- Corrigido junto: `saveVeiculo`/`editVeiculo` carregavam os modelos de `/mecanize/public/?url=…` (caminho fixo
  que só funcionava nesta instalação) — agora relativo.

**Consulta de dados pela placa (5.2) — decisão importante.** Pesquisei APIs brasileiras de consulta veicular e
**não consegui confirmar o contrato (endereço, autenticação, campos) de nenhum provedor** em documentação pública.
Em vez de inventar uma integração, `ConsultaVeicular` é um **conector configurável**: o endereço (com `{placa}`),
a credencial e o **caminho de cada campo na resposta** saem do `.env`, escritos pela oficina conforme a documentação
do serviço que contratar. Só os campos da lista branca atravessam (marca, modelo, ano, cor, combustível, versão) —
**dado de proprietário que o serviço envie é descartado** (testado). O que casa com o cadastro do sistema
(marca, modelo, cor, combustível) é preenchido; o que não casa ou não veio volta listado com o motivo ("o serviço
informou 'XPTO': não está no cadastro de marcas — escolha manualmente"). Sem configuração o botão **nem aparece** e
o endpoint responde com mensagem clara; o cadastro manual segue igual. **Nunca foi exercitado contra um serviço real.**

**Perfis**
- **Painel do técnico** (`painelTecnico`) reescrito para o celular: **"Fazer agora"** em ordem de prioridade
  (avaliação urgente → o que está na bancada → o que falta iniciar → demais avaliações), cada cartão com **um botão
  grande de ação** (Avaliar / Continuar / Iniciar) e a seção **"Esperando outra pessoa ou outra coisa"** (peça,
  conferência do administrador, retirada) com o motivo de cada espera. Antes eram três cartões de contagem que
  empurravam a primeira tarefa para fora da tela.
- **Tela da OS do técnico** (`osTecnico`): aviso do que **trava a OS** (autorização pendente / valor acima do
  autorizado) e **pedido de autorização ao cliente** direto da bancada; peças com **Reservar / Liberar reserva /
  Usei / Solicitar peça** (o "Usei" só aparece com saldo); **peças pedidas para esta OS** (com a situação);
  **histórico do veículo**; **fotos de evidência** (antes / durante / depois / problema encontrado, câmera traseira,
  prévia, sem duplo envio). Mensagem de erro agora mostra o motivo (antes aparecia a palavra "fluxo").
- **Início do administrador e do atendente**: bloco **"Precisa de você agora"** (`Pendencias`): conversas com mensagem
  nova, avisos que não chegaram ao cliente, autorizações aguardando o cliente, orçamentos sem resposta há 2 dias ou
  prontos para enviar, veículos esperando avaliação ou prontos para retirar; para o administrador também serviços
  para conferir, urgências para validar, solicitações de peça, parcelas vencidas a receber/pagar e a pagar nesta
  semana. Item sem pendência nem aparece. O técnico tem as mesmas permissões e o painel de trabalho como entrada.
- **Conversas no celular**: lista em cartões (a tabela rolava e escondia o botão "Abrir"); conversa abre na mensagem mais
  recente. Corrigido um defeito real achado ao renderizar: a classe CSS `.erro` (das telas de erro) esticava a bolha
  de qualquer mensagem com falha para ~900 px de altura.
- **Portal do cliente**: alvos de toque maiores (caixas de marcar, links). **Menu lateral no celular**: a sombra da
  gaveta fechada vazava na borda esquerda de todas as telas — corrigido.

**Entregue e validado com o banco (09/10/2026)**
- `database/etapa6_evidencias.php` aplicada nos dois bancos (idempotente: a 2ª execução faz 0 alterações), `EvidenciaOs`, rotas
  `enviarEvidenciaOS`/`verEvidenciaOS` e `OrdemServico::VINCULOS` (OS com foto não se exclui): `EvidenciaOsTest` (8) e `PendenciasTest` (6)
  verdes. **Defeito real achado pelo teste e corrigido:** `EvidenciaOs::anexar` devolvia o id do registro de histórico em vez do id da
  foto (`lastInsertId` lido depois do segundo INSERT, dentro da transação).
- `Pendencias` (12 consultas): falha de uma consulta vira zero e não derruba o início (`PendenciasResilienciaTest`, sem banco).
- Dados extras do `TecnicoController::visualizar` e filtro `ordem_servico_id` em `SolicitacaoPeca::listar`: cobertos pela suíte.
- **Aceitação por HTTP real da Etapa 6 (`aud_etapa6`): 51 verificações, 0 falhas** — envio e entrega de foto, bloqueios, início/pendências por
  perfil, painel do técnico, leitura de placa sem token, consulta veicular contra serviço de teste local e Integrações (detalhes na Etapa 7).
- Regressões reexecutadas depois das mudanças desta etapa: `aud_final` 72/72, `aud_etapa2` 99/99, `aud_etapa3` 102/102, `aud_etapa4` 108/108,
  `aud_etapa5` 169/169 (+2 no expediente do atendente). Um ajuste de expectativa em `aud_etapa2`: o painel do técnico novo mostra cada
  avaliação como cartão "OC-… · avaliação" (o título "Avaliações" deixou de existir); o comportamento é o mesmo.

**Pendências conhecidas desta etapa**
- Listas administrativas em tabela (clientes, OS, orçamentos, movimentações…) ainda rolam na horizontal no celular;
  só conversas, painel do técnico e a tela da OS do técnico foram refeitas em cartões.
- "Orçamentos em preparação" (item da seção 7) não tem bloco próprio no painel do técnico: aparece pelo fluxo da
  avaliação.
- Cache da consulta veicular por placa (para não pagar duas vezes a mesma consulta) — não feito; hoje há só o teto por sessão.
- Paginação/desempenho das listas grandes: **não auditado** nesta etapa.

### Etapa 7 — Validação final ✔ (concluída em 09/10/2026)

**Resultado.** PHPUnit completo: **471 testes / 3293 asserções, 0 falhas**, sem itens órfãos no banco de testes depois da suíte. Roteiros de aceitação por
HTTP real, todos sem falha: Etapas 1–4 no Apache (**72 / 99 / 102 / 108**), Etapa 5 em servidor PHP isolado (**169**; 171 dentro do
expediente do atendente) e Etapa 6 (**51**; 55 dentro do expediente). Os roteiros ficam na pasta temporária da sessão (`scratchpad`),
fora do repositório; o PHPUnit é o que fica versionado. Dados de auditoria (`zz_audit*`) e fotos de teste foram removidos ao final.

**O que o roteiro da Etapa 6 provou (51 verificações, 0 falhas):** tudo exige login · o técnico anexa foto à OS e o registro guarda tipo,
mime **pelo conteúdo**, quem enviou e a descrição, com nome aleatório em `storage/` (fora de `public/`; o Apache nega o endereço direto) ·
a trilha da OS registra a foto sem mudar o estado · a foto só sai por rota autenticada, com `nosniff` e CSP · script `.php` renomeado
para `.jpg`, PDF, tipo inventado, envio sem arquivo, OS inexistente e CSRF inválido são recusados sem gravar nada · início "Precisa de
você agora" abre para cada perfil e o técnico **não** vê as decisões e o dinheiro do administrador · painel do técnico mostra a OS dele ·
leitura de placa sem token responde `NAO_CONFIGURADO` pedindo para digitar, sem vazar token, endereço do serviço nem caminho · consulta
veicular contra um serviço de teste local: marca/modelo ligados ao cadastro, ano/cor/combustível no padrão do formulário, **dados de
proprietário descartados**, credencial enviada pelo cabeçalho configurado, nada gravado (só sugere), placa inválida não chama (nem paga)
o serviço, teto por sessão (429) · Integrações lista nomes, nunca valores.

**Defeitos reais achados e corrigidos na validação final:** `EvidenciaOs::anexar` devolvia o id errado (`lastInsertId` após o INSERT da
trilha) · `Fixtures::limpar` deixava itens de orçamento órfãos (testes intermitentes depois de restaurar o banco) · o transporte HTTP
(`HttpTransporte`) aceitava `http://127.0.0.1.evil.test` como "a própria máquina" (faltava fronteira no padrão; agora `localhost` /
`127.0.0.1` só valem seguidos de `:`, `/` ou fim) · `ConsultaVeicular::faltando()` passou a aceitar HTTP **só** para a própria máquina,
como o transporte já fazia (permite testar contra serviço local; testado em `ConsultaVeicularTest`).

**O que NÃO dá para validar daqui — depende de você ou de terceiros (nada foi inventado):**

| Item | O que falta | Estado hoje |
|---|---|---|
| Celular de verdade | Abrir a ficha do técnico, o painel, as conversas e o portal num aparelho (câmera, toque, teclado) | Validado por emulação no Chrome a 390 px (estouro horizontal = 0 nas telas refeitas). Câmera/foto de verdade só no aparelho. |
| WhatsApp (Meta) | Conta Business, número, token, App Secret, *verify token*, webhook HTTPS público, *templates* aprovados | Adaptador, assinatura e registro honesto provados com transporte falso; **nunca falou com a Meta**. |
| Pagamento online | Provedor contratado (Mercado Pago etc.), chaves, webhook HTTPS público | Fluxo inteiro provado com provedor **simulado**; Mercado Pago implementado pela documentação pública, **sem teste contra a API real**. |
| Leitura de placa por foto | `PLATE_RECOGNIZER_TOKEN` (Plate Recognizer, plano pago) | Sem token o sistema diz que não está configurado e pede para digitar (provado). A leitura real de uma foto **não foi testada**. |
| Consulta de dados pela placa | Serviço contratado + `CONSULTA_PLACA_URL` (HTTPS), `_TOKEN` e os caminhos dos campos da resposta | Conector genérico, **nenhum provedor presumido**; validado contra serviço de teste local. O formato de um provedor real não foi confirmado. |
| E-mail (SMTP) | `MAIL_*` preenchido | Desligado em todos os testes automáticos de propósito (nenhum e-mail real saiu). |
| Atendente fora do expediente | Rodar os roteiros em horário comercial (seg–sex 07–18h, sáb 07–13h) | Fora do horário o sistema barra o atendente (regra existente); as verificações dele aparecem como PULADO. A paridade técnico = atendente é garantida pelo `PermissaoTest`. |
| Antivírus e o MySQL | Testar com o Kaspersky desligado ou com `C:\xampp\mysql\data` na lista de exclusões | Não testado; ver "Incidente de ambiente" na Etapa 6. O `my.ini` ajustado resolveu o travamento. |
| URL pública e agendador | `APP_URL` com HTTPS; tarefa a cada minuto para `bin/enviar_mensagens.php` | Ver tabela da Etapa 5. |

**Como repetir a validação:** subir o MySQL pelo painel · `DB_NAME=mecanize_db_test /c/xampp/php/php.exe vendor/bin/phpunit --no-coverage` ·
para a aceitação por HTTP, recriar os roteiros (a lista do que cada um cobre está nas seções das Etapas 1–6) e rodá-los no Apache ou, quando
criam cliente com e-mail ou usam variáveis de teste, num `php -S` isolado com `MAIL_HOST=seu-desligado`.
---

### Anexo — Telas de erro: um único modelo (09/10/2026)

**Decisão do dono:** todas as telas de erro seguem **um único exemplo visual**, reproduzido fielmente; a única coisa que muda de um erro para outro é o texto. (Uma primeira versão com cartões, etiquetas coloridas e painel do login foi **recusada** e substituída por esta.)

**O modelo:** página inteira em fundo claro (`#FCFBF9`, com granulado quase imperceptível) · logo oficial (`logo-mecanize-doc.jpg`, embutida na página, `mix-blend-mode: multiply`) no canto superior esquerdo · **código HTTP** grande (Inter 700) · **título** (Inter 700, 56 px) · **uma linha** de explicação (cinza, 23 px) · **botão preto** (seta + texto) · **link** cinza sublinhado. Tudo centralizado; medidas tiradas da imagem de referência (1536×1024) e conferidas sobre ela (diferença de 2–4 px). No celular as mesmas peças encolhem (código 112 px, título 32 px).

**Onde está:** `Erro::CATALOGO` (código, título e a linha de cada situação) · `app/views/Site/erros/pagina.php` (marcação e saídas) · `_autonomo.php` (a página) · `public/assets/css/erros.css`. Todas as situações usam a **página inteira**, sem menu lateral, mesmo para quem está logado. Os três modelos antigos (bússola/acesso/sistema), os ícones, o cartão e o modo "dentro do layout" foram removidos.

**Textos e saídas por situação** (título · botão · link): 404 *Página não encontrada* (Voltar ao início · Voltar à página anterior) · registro removido *Registro não encontrado* (Voltar para a lista · Voltar à página anterior) · 403 *Acesso negado* (Voltar ao início · Voltar à página anterior) · 401 *Sessão encerrada* (Entrar novamente, com retorno) · formulário vencido *Formulário expirado* (Voltar e enviar de novo · Ir para o início) · 500 *Algo falhou do nosso lado* · 503 *Sistema em manutenção* · *Sem conexão* · 504 *A operação demorou demais* (Tentar novamente · Voltar ao início). **Cliente da oficina** (link de acompanhamento/portal): sem "início" nem "login"; o link do portal inválido não tem botão algum.

**Preservado:** status HTTP, código de ocorrência e registro em log, `erros.js` (tentar novamente com giro, voltar pelo histórico, nova tentativa **automática silenciosa** em manutenção/sem conexão/tempo esgotado) e os alertas inline `.alerta*` usados pelo login e pelos formulários. **Único detalhe além do modelo:** na falha 500 uma linha pequena e discreta "Código da ocorrência XXXX" (sem ela ninguém acha a linha do log); some se o dono preferir. Saiu o botão "Falar com o suporte" (não existe no modelo).

**Validação:** 11 situações renderizadas no Chrome a partir do mesmo endereço do sistema (a fonte Inter só carrega assim) em 1536×1024 e 390 px: estrutura idêntica (só os elementos do modelo), um único h1, fonte carregada, fundo, botão preto com ≥ 44 px, giro do "tentar novamente", contagem andando por baixo, sem rolagem horizontal; rotas reais por HTTP com 404/403/503/504 corretos, link do portal inválido sem saída e com resposta do mesmo tamanho para qualquer token, "Cliente não encontrado" voltando para a lista, 403 do técnico, formulário vencido. Capturas em `prints-telas-de-erro/`.


---

### Anexo — Menu lateral em grupos (09/10/2026)

**Por quê:** o menu tinha cerca de 25 linhas soltas (administrador) e era difícil achar as coisas. Agora a **primeira camada tem 7 linhas** e as telas ficam em grupos que abrem e fecham. Só a apresentação mudou: telas, rotas, permissões e regras de negócio são as mesmas.

**Os grupos (por finalidade):** *Painel de trabalho* e *Início* ficam soltos · **Oficina** (Atendimento, Fila, Orçamentos, Ordens de Serviço, Agenda, Painel TV) · **Clientes** (Clientes, Veículos, Conversas) · **Estoque** (Produtos*, Movimentações, Consulta de estoque, Solicitação de peças, Compras*, Fornecedores*) · **Financeiro** (Caixa, Financeiro*, A receber*, A pagar*, Cartões*, Comissões*, Relatório de Caixa*) · **Administração*** (Relatórios, Documentos, Usuários, Integrações). ** só administrador.* Um grupo que sobra com **um item só** para o perfil vira item solto: o técnico/atendente vê **Caixa** solto, não um grupo "Financeiro".

**Comportamento:** o grupo da tela atual **abre sozinho** e o item atual fica marcado — também nas telas "filhas" (cadastro, edição, detalhe: antes só a lista marcava) · cada grupo abre/fecha por clique e o que a pessoa deixou aberto é lembrado (`localStorage`, aplicado antes da primeira pintura, sem "pulo") · vários grupos podem ficar abertos · **barra recolhida:** só ícones, o grupo da página atual destacado, nome em dica ao passar o mouse e, ao clicar no ícone de um grupo, a barra abre já mostrando o grupo · o contador de conversas não lidas aparece no cabeçalho de **Clientes** enquanto o grupo está fechado (ponto no canto do ícone na barra recolhida) · **celular:** mesma lista dentro da gaveta, sem perder o fechamento ao tocar num link · teclado: cabeçalho é botão (`aria-expanded`/`aria-controls`) e o item atual tem `aria-current="page"` · tema claro e escuro herdam os tokens da sidebar.

**Onde está:** `app/views/layout/_sidebarMenu.php` (lista de grupos/itens + marcação + script) · bloco "GRUPOS DO MENU" no fim de `public/assets/css/sidebar.css` · `header.php` passou a incluir o menu. As telas filhas de cada item ficam em `rotas => [...]`; `RotasTest::testMenuLateralSoCitaRotasRegistradas` falha se alguma tiver nome de rota inexistente (os links `./x` já eram cobertos pelo teste de links).

**Validação:** 5 cenários no Chrome (administrador, técnico, recolhida, celular, escuro) com abrir/fechar, altura animada, persistência, tooltip, clicar no ícone na barra recolhida, acessibilidade e sem rolagem horizontal; rotas reais por HTTP: 43 telas do administrador marcam o item e o grupo certos (3 rotas que apenas redirecionam ficaram de fora), o técnico vê 3 grupos + Caixa solto e **nenhum** item de administrador (e continua levando 403 nas telas dele). Capturas em `prints-sidebar/`.
