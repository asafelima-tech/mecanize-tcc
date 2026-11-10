# Mecanize v2

A v2 é a versão simplificada do sistema: 17 tabelas e várias oficinas na mesma plataforma, com os dados de cada oficina isolados. Ela roda **ao lado** da v1, sem mexer nela. Usa o banco `mecanize_db_v2`, criado por `database/v2/migrar.php`.

- Endereço: <http://localhost/mecanize-tcc/v2/public/>
- O login é o mesmo da v1: e-mail e senha foram migrados sem alteração.

## Situação (as 6 etapas prontas)

| Etapa | Conteúdo | Situação |
|---|---|---|
| 1 | Login, sessão, isolamento por oficina, licença, permissões, tela inicial | **pronta** |
| 2 | Clientes, veículos, peças e serviços, fornecedores, usuários, perfil | **pronta** |
| 3 | OS com orçamento integrado, impressão e link de acompanhamento | **pronta** |
| 4 | Estoque: entrada de mercadoria, ajuste, estorno e livro de movimentações | **pronta** |
| 5 | Financeiro: contas a receber e a pagar, parcelas, estornos, comissões; caixa | **pronta** |
| 6 | Agenda, anexos, avisos, relatórios, configurações da oficina, gestão de licenças | **pronta** |

## Como o isolamento funciona (duas barreiras)

1. **Código.** Todo model herda de `app/core/Modelo.php`.
   - Uma consulta em tabela de oficina sem `:oficina` no SQL nem chega a ser executada.
   - O valor de `:oficina` vem da conta logada, relida do banco a cada requisição. Nunca vem do formulário nem da URL.
2. **Banco.** As ligações entre tabelas usam chave composta `(oficina_id, id)`. O MySQL recusa, por exemplo, uma OS da oficina A apontando para um cliente da oficina B.

Os arquivos enviados ficam em `storage/oficinas/{id}/`.

## Cadastros (etapa 2)

| Cadastro | Quem acessa | O que tem |
|---|---|---|
| Clientes | equipe toda (excluir: só ADMIN) | PF/PJ com CPF/CNPJ validado, contato com link de WhatsApp, endereço, veículos e histórico de OS |
| Veículos | equipe toda (excluir: só ADMIN) | placa antiga ou Mercosul, marca e modelo do catálogo (ou escritos à mão), histórico de serviços com o que foi feito |
| Peças e serviços | consultar: equipe (sem o custo); cadastrar: ADMIN | um catálogo só. O estoque inicial já entra como movimentação; depois o estoque só muda por entrada, baixa ou ajuste (etapa 4) |
| Fornecedores | ADMIN | dados de contato e observações |
| Usuários | ADMIN | perfil, comissão e limite de usuários do plano |
| Meu perfil | cada um | nome, telefone e troca de senha (pede a senha atual) |

Regras:

- **Excluir** só funciona para um cadastro sem nada ligado a ele. Com OS ou movimentações, a tela pede para **desativar**: o cadastro some das listas, mas o histórico continua.
- **Usuários:** a oficina nunca fica sem administrador ativo, e ninguém desativa nem rebaixa a própria conta.
- **E-mail de login:** é único na plataforma inteira.

## Ordens de serviço (etapa 3)

O orçamento é a própria OS no status `ORCAMENTO`:

```
ORCAMENTO → APROVADA → EM_EXECUCAO ⇄ AGUARDANDO_PECA → PRONTA → ENTREGUE
    ↕ recusar / reabrir                       PRONTA → voltar para execução
cancelar: de qualquer situação antes de ENTREGUE (as peças baixadas voltam ao estoque)
```

- **Valores da época.** Cada item guarda a própria descrição, quantidade, preço e desconto. Mudar o catálogo depois não altera nenhuma OS.
- **Aprovação.** Registra quem aprovou e, se houver, a assinatura feita na tela. O total fica congelado em `valor_aprovado`, e uma cópia dos itens aprovados vai para o histórico.
  - O que entra depois aparece como **adicional**, separado do valor aprovado.
  - Remover um item depois da aprovação pede motivo e fica registrado.
- **Estoque.**
  - A peça sai do estoque ao **iniciar a execução**. Sem saldo, a OS vai para "aguardando peça"; quando a peça chega, o botão "Peças chegaram" faz a baixa.
  - Peça removida ou OS cancelada devolve a peça ao estoque, sempre com registro no livro de movimentações.
- **Comissão.** É calculada ao **concluir**, pela regra do técnico do item (ou do responsável pela OS), e fica gravada no item.
- **Impressão.** Orçamento e OS em folha A4, com espaço para assinatura. Dá para salvar em PDF direto pelo navegador.
- **Link do cliente.** Página pública só com a situação, os itens e o total, sem dados internos. Pode ser enviada pelo WhatsApp quando o cliente aceita contato.
- **Pagamento.** A OS gera as contas a receber (à vista ou parcelado) e recebe direto na própria tela. Entregar não exige pagamento, mas avisa se ainda há saldo.

## Estoque (etapa 4, só ADMIN)

- **Saldo nunca editado à mão.** O saldo de cada peça só muda por movimentação, e cada movimentação fica no livro (`movimentacoes_estoque`). Saldo e livro sempre batem, e os testes conferem isso.
- **Entrada de mercadoria.** Substitui o módulo de compras da v1.
  - Várias peças numa entrada só, com fornecedor e número da nota.
  - Pode atualizar o custo das peças com o valor da nota.
  - Pode lançar a conta a pagar ao fornecedor, com vencimento e forma de pagamento. A conta aparece no financeiro.
- **Ajuste.** Informa a quantidade contada na prateleira. O sistema calcula a diferença e exige um motivo.
- **Estorno.**
  - Desfaz uma entrada ou um ajuste errado com um movimento contrário. O original continua no livro.
  - Não deixa o saldo negativo.
  - Reduz ou cancela a conta a pagar se ela ainda não foi paga. Se já houve pagamento, pede para estornar o pagamento antes.
  - Movimentos de OS só são desfeitos pela própria OS.
- **Livro.** Busca por peça ou nota e filtro por tipo e período. Cada saída mostra a OS de origem.
- **No topo da tela:** quantidade de peças ativas, valor em estoque pelo custo e quantas estão abaixo do mínimo (com link para a lista).

## Financeiro e caixa (etapa 5)

- **Contas (`lancamentos`).** A receber e a pagar no mesmo lugar, com categoria, vencimento, parcelas e vínculo opcional com OS, cliente, fornecedor ou funcionário. Financeiro é só do ADMIN; o recebimento da OS a equipe faz pela própria OS.
- **Parcelas.** O valor é dividido em centavos e a sobra vai para a última parcela, então a soma sempre bate com o total.
- **Pagamento parcial, juros, desconto e taxa.** Cada pagamento é uma movimentação. O valor pago e a situação da conta (aberta, parcial, paga) são recalculados a partir delas, nunca digitados.
- **Estorno.** Cria uma movimentação contrária; a original continua registrada. Um pagamento só pode ser estornado uma vez (o banco garante).
- **Cancelamento.** Só para conta sem pagamento. Cancelar a conta de comissão libera os itens para uma nova geração.
- **Comissões.** Lista o que os técnicos têm a receber das OS concluídas e gera a conta a pagar de cada um. Depois disso a OS não volta para execução sem cancelar essa conta.
- **Caixa.** É a gaveta da oficina: um caixa aberto por vez, aberto e fechado por qualquer pessoa da equipe. Recebimento em dinheiro exige caixa aberto. Suprimento e sangria ficam registrados. No fechamento o sistema compara o contado com o esperado e mostra sobra ou falta. O histórico de caixas é do ADMIN.
- **Clique duplo.** Cada pagamento leva uma chave única; repetir o envio não lança duas vezes.

## Agenda, anexos, avisos, relatórios e configurações (etapa 6)

- **Agenda** (equipe toda): visão por dia ou semana, filtros por situação e técnico.
  - **Conflito de horário** (regra da v1): com técnico, ele não pode ter dois serviços sobrepostos; sem técnico, o horário não pode coincidir com outro agendamento ativo.
  - Situações: agendado, confirmado, atendido, cancelado e não compareceu. Cancelado libera o horário; reativar confere de novo.
  - **Gerar OS** abre o novo orçamento já preenchido. A OS e o vínculo com o agendamento são gravados juntos, e um agendamento vira no máximo uma OS.
  - Excluir é só do ADMIN, e só de agendamento que não virou OS.
- **Fotos e documentos da OS** (equipe) e **comprovantes das contas** (ADMIN).
  - Ficam em `storage/oficinas/{oficina}/`, fora da pasta pública, com nome aleatório. O tipo é conferido pelo conteúdo do arquivo: só JPG, PNG, WEBP e PDF, até 5 MB.
  - São registro, como na v1: não se editam nem se apagam. Cada envio entra no histórico. No máximo 40 por OS ou conta.
  - Só abrem pela rota `anexo`, que confere a oficina e o perfil.
- **Avisos** (sino no topo): quando uma OS para por falta de peça, os administradores recebem um aviso com o link da OS. É a alternativa simples à "solicitação de peça" da v1.
- **Tela inicial:** além das OS, mostra os próximos agendamentos, a situação do caixa e alertas (OS aguardando peça, orçamentos vencidos e, para o ADMIN, contas vencidas e peças no estoque mínimo).
- **Relatórios** (ADMIN), por período:
  - faturamento (OS entregues), ticket médio e comparação com o período anterior;
  - gráfico por dia ou por mês;
  - recebido e pago pelo dinheiro que de fato entrou e saiu (estornos descontados), por forma de pagamento e por categoria;
  - contas e OS em aberto hoje, peças e serviços mais vendidos com margem, produção e comissão por técnico, clientes que mais compraram e estoque (valor e peças no mínimo).
  - Dá para imprimir.
- **Configurações** (ADMIN): nome, CNPJ, contato, endereço, horário e logotipo, que saem na impressão da OS. A licença aparece só para consulta. Toda alteração fica registrada.
- **Plataforma** (equipe do Mecanize):
  - cadastra oficinas com o primeiro administrador (senha provisória);
  - altera plano, limite de usuários, situação e validade, com atalhos de renovação de +1 mês e +1 ano;
  - toda mudança fica no histórico da oficina com o antes e o depois; cancelar exige motivo;
  - não enxerga nada de dentro das oficinas.

## Licença

| Situação | Efeito |
|---|---|
| `TESTE` ou `ATIVA` dentro da validade | uso normal |
| Vencida ou `SUSPENSA` | **somente leitura**: dá para consultar, mas não gravar |
| `CANCELADA` | não entra no sistema |

O aviso aparece no topo da tela a partir de 7 dias antes do vencimento.

## Perfis

| Perfil | Acesso |
|---|---|
| ADMIN | tudo da própria oficina (financeiro, estoque, relatórios, configurações e usuários são só dele) |
| ATENDENTE / TECNICO | a operação do dia a dia (as mesmas permissões entre si) |
| PLATAFORMA | a equipe do Mecanize: cadastra oficinas e cuida das licenças, nunca vê os dados de dentro delas |

Para criar um usuário da plataforma:

```
C:\xampp\php\php.exe v2\bin\criar_usuario_plataforma.php "Seu Nome" voce@exemplo.com
```

## Testes

```
C:\xampp\php\php.exe vendor\bin\phpunit -c v2\phpunit.xml
```

Os testes criam e apagam um banco próprio (`mecanize_db_v2_teste`). Os bancos `mecanize_db` e `mecanize_db_v2` não são tocados.

## O que saiu da v1 nesta etapa (de propósito)

- **Horário fixo do atendente:** num sistema com várias oficinas, um horário escrito no código não serve a nenhuma delas.
- **"Caixa obrigatório" antes de qualquer tela:** o caixa continua exigido onde importa. O banco recusa um recebimento em dinheiro sem caixa aberto.
- **Fechar o caixa ao sair do sistema:** o caixa é da oficina, não da pessoa. Ele é fechado de propósito, com a contagem da gaveta.
- **"URL inerte"** (só navegar por cliques): atrapalhava o uso, por exemplo os favoritos e o voltar do navegador, sem proteger nada. Quem protege é a permissão de cada rota.
