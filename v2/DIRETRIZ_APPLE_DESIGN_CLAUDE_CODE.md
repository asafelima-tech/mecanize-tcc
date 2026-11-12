# DIRETRIZ MESTRA --- REDESIGN APPLE-LEVEL DO SISTEMA

> **Missão para o Claude Code:** transforme a interface do sistema em um
> produto digital de alto padrão, com a clareza, precisão, consistência
> e qualidade de acabamento associadas aos melhores produtos da Apple.
> Não faça apenas uma troca de cores ou uma maquiagem superficial. Faça
> uma auditoria real da experiência e implemente uma linguagem visual
> coesa em todo o sistema, preservando integralmente sua identidade
> funcional e suas regras de negócio.

## 1. Referências oficiais obrigatórias

Use como referência principal a documentação oficial da Apple:

-   Apple Developer Documentation:
    https://developer.apple.com/documentation/
-   Apple Human Interface Guidelines (HIG):
    https://developer.apple.com/design/human-interface-guidelines/
-   Design principles:
    https://developer.apple.com/design/human-interface-guidelines/design-principles
-   Foundations:
    https://developer.apple.com/design/human-interface-guidelines/foundations
-   Layout:
    https://developer.apple.com/design/human-interface-guidelines/layout
-   Materials:
    https://developer.apple.com/design/human-interface-guidelines/materials
-   Typography:
    https://developer.apple.com/design/human-interface-guidelines/typography
-   Color:
    https://developer.apple.com/design/human-interface-guidelines/color
-   Components:
    https://developer.apple.com/design/human-interface-guidelines/components

Leia e consulte essas referências antes e durante a implementação. Não
se limite à página inicial da documentação. Investigue especialmente os
princípios de hierarquia visual, simplicidade, consistência, tipografia,
layout, materiais, acessibilidade, feedback e comportamento dos
componentes.

**Importante:** o objetivo não é copiar literalmente o macOS, iOS, os
aplicativos da Apple ou seus elementos proprietários. O objetivo é
aplicar os princípios de design da Apple a um sistema web de gestão,
respeitando as necessidades de um software empresarial. Não use marcas,
logotipos, ícones proprietários ou elementos que façam o sistema parecer
um produto oficial da Apple.

------------------------------------------------------------------------

## 2. Objetivo visual

Quero que o sistema pareça ter sido projetado por uma equipe de design
de produto extremamente criteriosa.

A interface deve transmitir:

-   Sofisticação sem ostentação.
-   Minimalismo sem parecer vazia.
-   Modernidade sem depender de tendências passageiras.
-   Profundidade sem excesso de sombras ou efeitos.
-   Clareza sem simplificar demais informações importantes.
-   Consistência entre todas as telas.
-   Precisão nos alinhamentos, espaçamentos, tipografia e proporções.
-   Fluidez e previsibilidade nas interações.

A referência estética é a qualidade de acabamento dos produtos Apple:
cada elemento deve parecer intencional, bem dimensionado e colocado
exatamente onde deveria estar.

**Não quero um dashboard genérico com aparência de template.** Quero uma
interface com identidade própria, refinada, funcional e reconhecível.

## 3. Regra principal: primeiro entenda o sistema

Antes de alterar qualquer arquivo:

1.  Inspecione a estrutura completa do projeto.
2.  Identifique a stack, arquitetura, sistema de rotas, layouts,
    componentes compartilhados, folhas de estilo e bibliotecas
    utilizadas.
3.  Mapeie todas as telas e seus respectivos fluxos.
4.  Identifique os componentes repetidos e as inconsistências entre
    páginas.
5.  Descubra quais estilos são globais e quais são específicos de cada
    tela.
6.  Identifique os temas disponíveis e como são aplicados.
7.  Analise o comportamento em desktop, tablet e dispositivos móveis.
8.  Identifique componentes antigos, estilos duplicados, sobrescritas
    conflitantes e soluções improvisadas.

Não presuma que uma tela funciona de determinada maneira apenas pelo
nome do arquivo. Leia o código e entenda o fluxo real.

Crie um inventário das telas, classificando-as por módulo, função e
nível de necessidade de reformulação. Use esse inventário para garantir
cobertura completa.

## 4. Preserve a identidade cromática existente

As cores atuais já foram definidas e devem ser preservadas. Não
substitua a identidade cromática por uma paleta genérica inspirada na
Apple.

No tema escuro, respeite especialmente esta hierarquia:

  Aplicação                                      Cor
  ---------------------------------------------- -----------
  Fundo principal                                `#191919`
  Sidebar, navegação e superfícies secundárias   `#1f1f1f`
  Cards, painéis e superfícies de conteúdo       `#303030`

Mantenha as demais cores de destaque, estados, textos e bordas que já
fazem parte do sistema, salvo quando houver uma inconsistência técnica
real. Não invente uma nova cor de destaque por preferência pessoal.

Aplique as cores por meio dos tokens ou variáveis já existentes, ou
consolide-os em um sistema centralizado caso estejam espalhados. Evite
valores repetidos e divergentes em diferentes arquivos.

O tema claro também deve continuar funcional e coerente. Não o quebre
para melhorar o tema escuro.

## 5. Crie um verdadeiro sistema de design

Antes de reformular todas as telas, estabeleça uma base visual
compartilhada. Não implemente cada página como se fosse um projeto
independente.

### 5.1 Tokens e fundamentos

Centralize e padronize:

-   Cores de fundo, superfície, texto, borda, destaque e estados.
-   Tipografia, pesos, tamanhos e alturas de linha.
-   Escala de espaçamento.
-   Raios de borda.
-   Alturas de controles.
-   Larguras e limites de conteúdo.
-   Elevação, sombras e camadas.
-   Transições e duração de animações.
-   Breakpoints responsivos.
-   Estados de foco, hover, pressionado, desabilitado, carregamento e
    erro.

Use uma escala consistente. Não escolha medidas aleatórias para cada
componente.

### 5.2 Tipografia

A tipografia deve ter personalidade, excelente legibilidade e hierarquia
clara.

-   Escolha uma família tipográfica adequada ao ambiente web e à
    identidade existente.
-   Avalie uma fonte sans-serif contemporânea, com boa leitura em
    interfaces densas.
-   Defina pesos com intenção: títulos, subtítulos, corpo, rótulos,
    metadados e números.
-   Evite textos pequenos demais, finos demais ou com aparência de fonte
    padrão sem tratamento.
-   Não use negrito em tudo.
-   Dê tratamento especial a valores financeiros, números e indicadores.
-   Mantenha consistência entre títulos de páginas equivalentes.

Não use tamanhos ou pesos diferentes sem uma função hierárquica clara.

### 5.3 Espaçamento e alinhamento

-   Estabeleça uma escala de espaçamento.
-   Alinhe títulos, filtros, tabelas, cards e ações seguindo uma grade
    coerente.
-   Evite espaços vazios acidentais e componentes comprimidos.
-   Use espaço negativo para separar grupos e orientar a leitura.
-   Evite padding excessivo em telas de trabalho com alta densidade de
    informação.
-   Não tente preencher todo espaço disponível com cards.

### 5.4 Superfícies e profundidade

A interface não deve parecer chapada, mas também não deve depender de
sombras pesadas.

Use profundidade com moderação por meio de:

-   Diferenças sutis entre superfícies.
-   Bordas discretas.
-   Camadas bem definidas.
-   Contraste de luminosidade.
-   Sobreposições e materiais somente quando fizerem sentido.
-   Sombras leves e funcionais, quando necessárias para indicar
    elevação.

Não aplique blur, transparência ou efeito de vidro em todos os
elementos. Materiais são recursos de hierarquia, não decoração
universal.

### 5.5 Ícones

-   Padronize a biblioteca de ícones já utilizada no projeto.
-   Use ícones consistentes em estilo, espessura e proporção.
-   Evite misturar emojis, ícones de bibliotecas diferentes e símbolos
    improvisados.
-   Ajuste cada ícone conforme sua finalidade e contexto.
-   Não use ícones apenas para preencher espaços.
-   Garanta boa leitura em tamanhos pequenos.

------------------------------------------------------------------------

## 6. Estrutura global do sistema

Revise os elementos compartilhados antes de avançar pelas páginas.

### 6.1 Sidebar

A sidebar deve ser discreta, refinada e funcional.

-   Organize os módulos em grupos lógicos.
-   Garanta alinhamento preciso entre ícones e textos.
-   Diferencie claramente o item ativo.
-   Padronize estados de hover e foco.
-   Revise a versão recolhida: ícones devem ter tamanho proporcional e
    centralização precisa.
-   Evite excesso de separadores, fundos e destaques.
-   Preserve o comportamento de navegação e as permissões atuais.

### 6.2 Topbar

A topbar deve ter composição limpa e equilibrada.

-   Revise alinhamentos, alturas, espaçamentos e proporções.
-   Mantenha ações importantes fáceis de localizar.
-   Preserve o botão **Criar** já aprovado e seu modal compacto.
-   O modal Criar deve permanecer minimalista, pequeno, organizado por
    categorias e apresentar essencialmente ícones discretos e nomes das
    ações.
-   Não volte a inserir descrições longas, cards grandes ou elementos
    desnecessários nesse modal.
-   Não introduza elementos que disputem atenção com o conteúdo
    principal.

### 6.3 Conteúdo das páginas

Defina um padrão reutilizável para:

-   Cabeçalho da página.
-   Título e descrição.
-   Breadcrumbs, quando realmente úteis.
-   Ações principais e secundárias.
-   Filtros e pesquisa.
-   Indicadores.
-   Tabelas e listas.
-   Formulários.
-   Modais e drawers.
-   Estados vazios.
-   Paginação.
-   Mensagens de sucesso, erro e confirmação.

Não force todas as páginas a terem a mesma estrutura. Padronize a
linguagem visual, não a finalidade de cada tela.

------------------------------------------------------------------------

## 7. Padrões por tipo de tela

### 7.1 Dashboards

-   Priorize os indicadores realmente úteis.
-   Evite excesso de cards.
-   Organize gráficos de acordo com sua importância e relação entre
    dados.
-   Elimine espaços vazios causados por alturas fixas ou grids mal
    dimensionados.
-   Não duplique gráficos ou métricas.
-   Garanta que os dados sejam legíveis e que os gráficos tenham
    títulos, escalas e legendas compreensíveis.

### 7.2 Listagens e tabelas

-   Priorize legibilidade e comparação entre registros.
-   Revise altura de linhas, densidade, alinhamento e espaçamento.
-   Alinhe números e valores monetários de forma consistente.
-   Organize filtros sem ocupar uma área desproporcional.
-   Mantenha ações por registro discretas e fáceis de encontrar.
-   Diferencie estados por rótulos claros e cores semânticas.
-   Implemente estados vazios, carregamento e erro com acabamento
    adequado.
-   Não substitua tabelas úteis por cards apenas para deixar a tela
    visualmente diferente.

### 7.3 Formulários de criação e edição

-   Agrupe campos por contexto.
-   Crie hierarquia entre seções.
-   Use larguras proporcionais ao conteúdo esperado.
-   Evite campos gigantes para informações curtas.
-   Mantenha labels visíveis e consistentes.
-   Organize campos especiais, uploads, selects e datas.
-   Posicione ações de salvar e cancelar de forma previsível.
-   Preserve validações e mensagens de erro.
-   Não transforme formulários simples em sequências desnecessárias de
    cards.

### 7.4 Telas de visualização

-   Não apresente dados como se fossem inputs desabilitados.
-   Priorize a identificação e os dados mais relevantes.
-   Agrupe informações relacionadas.
-   Use hierarquia, tipografia e espaço negativo para facilitar a
    consulta.
-   Apresente histórico e relacionamentos somente quando já existirem no
    sistema.

### 7.5 Telas operacionais e transacionais

Telas como ordens de serviço, orçamentos, agendamentos e entrada de
mercadorias precisam priorizar o fluxo de trabalho.

-   Destaque a tarefa principal.
-   Organize etapas e informações na ordem natural de utilização.
-   Torne inclusão, edição e remoção de itens claras.
-   Apresente totais e resumos financeiros com precisão.
-   Evite que controles importantes fiquem escondidos.
-   Não acione edição inline apenas porque o cursor passou sobre um
    campo.
-   A edição deve ocorrer por ação explícita, quando esse for o padrão
    funcional definido.

### 7.6 Telas financeiras

Contas a pagar, contas a receber e comissões devem priorizar precisão e
leitura.

-   Valores monetários precisam ter destaque adequado.
-   Diferencie valores pendentes, pagos, recebidos e atrasados.
-   Organize período, filtros e listagens.
-   Evite excesso de indicadores redundantes.
-   Preserve cálculos, regras de comissão, baixas e históricos.

### 7.7 Modais

-   Use dimensões proporcionais à tarefa.
-   Evite modais enormes para ações simples.
-   Mantenha hierarquia clara entre título, conteúdo e ações.
-   Garanta fechamento previsível e acessibilidade por teclado.
-   Não coloque cards dentro de cards sem necessidade.
-   Use scroll interno somente quando necessário.
-   Preserve o contexto da página de origem.

------------------------------------------------------------------------

## 8. Interações e microdetalhes

A qualidade percebida depende também do comportamento.

Revise:

-   Hover discreto e consistente.
-   Foco visível para teclado.
-   Estados pressionados.
-   Transições rápidas e naturais.
-   Feedback imediato após ações.
-   Confirmações para operações destrutivas.
-   Prevenção de cliques duplicados durante salvamentos.
-   Estados de carregamento.
-   Mensagens de erro úteis e específicas.
-   Tooltips apenas quando agregarem informação.
-   Cursor padrão do sistema, sem sprites ou cursores personalizados com
    aparência infantil.
-   Navegação por teclado e ordem de foco lógica.

Não adicione animações apenas para parecer moderno. Cada movimento
precisa comunicar uma mudança de estado ou ajudar na compreensão.

------------------------------------------------------------------------

## 9. Responsividade e acessibilidade

O sistema é uma aplicação de gestão, portanto precisa funcionar bem em
diferentes contextos.

Teste no mínimo:

-   Desktop amplo.
-   Notebook.
-   Tablet.
-   Smartphone.

Verifique:

-   Sidebar expandida e recolhida.
-   Topbar em larguras menores.
-   Tabelas extensas.
-   Modais.
-   Formulários com múltiplas colunas.
-   Gráficos.
-   Botões e áreas de toque.
-   Textos longos.
-   Campos de pesquisa e filtros.

Não resolva responsividade simplesmente reduzindo tudo. Reorganize a
interface conforme a largura disponível.

Garanta contraste adequado, foco visível, labels associados aos campos,
mensagens compreensíveis e uso por teclado. Não dependa exclusivamente
de cores para comunicar status.

------------------------------------------------------------------------

## 10. Regras invioláveis: não quebre o sistema

Esta tarefa é principalmente visual e de experiência. A integridade
funcional é obrigatória.

**Não altere sem necessidade:**

-   Banco de dados, tabelas ou estrutura de dados.
-   Regras de negócio.
-   Cálculos financeiros.
-   Cálculos de comissão.
-   Fluxos de autenticação.
-   Papéis, permissões e autorização.
-   Rotas e contratos de endpoints.
-   Validações.
-   Integrações.
-   Processos de entrada e saída de estoque.
-   Geração e impressão de documentos.
-   Exportações.
-   Assinaturas, pagamentos ou registros históricos.

Não remova funcionalidades para simplificar a interface. Não substitua
dados reais por valores fictícios. Não crie botões que não executem
ações reais.

Se uma alteração visual exigir mudança estrutural, primeiro entenda as
dependências e faça a menor alteração segura possível.

Não reescreva módulos inteiros sem necessidade. Preserve a arquitetura
existente e respeite os padrões do projeto.

------------------------------------------------------------------------

## 11. Método de execução

Trabalhe em etapas, mas execute o trabalho até a conclusão. Não pare
depois de apresentar uma análise ou uma lista de sugestões.

### Etapa 1 --- Auditoria

-   Percorra o projeto.
-   Mapeie as telas.
-   Identifique os componentes globais.
-   Identifique inconsistências.
-   Registre problemas funcionais encontrados, mas não misture correções
    de negócio com o redesign sem necessidade.

### Etapa 2 --- Fundação visual

-   Defina tokens.
-   Padronize tipografia.
-   Padronize espaçamentos.
-   Padronize botões, inputs, selects, tabelas, badges e modais.
-   Revise sidebar e topbar.
-   Garanta suporte aos temas existentes.

### Etapa 3 --- Implementação global

-   Corrija primeiro os componentes compartilhados.
-   Depois percorra todos os módulos.
-   Reformule cada tela conforme sua finalidade.
-   Reutilize os componentes padronizados.
-   Elimine estilos duplicados e conflitos quando for seguro.
-   Não deixe telas antigas esquecidas.

### Etapa 4 --- Validação

Após cada conjunto de alterações:

-   Verifique erros de console.
-   Verifique erros de PHP/JavaScript e requisições.
-   Teste rotas e navegação.
-   Teste formulários.
-   Teste modais.
-   Teste filtros e tabelas.
-   Teste permissões.
-   Teste os temas.
-   Teste responsividade.
-   Compare o comportamento antes e depois.

Execute os testes automatizados existentes. Se não houver testes para
determinada área, faça verificações manuais objetivas.

### Etapa 5 --- Revisão de acabamento

Faça uma segunda passagem visual procurando:

-   Textos desalinhados.
-   Ícones pequenos ou desproporcionais.
-   Botões com alturas diferentes sem justificativa.
-   Campos inconsistentes.
-   Espaçamentos aleatórios.
-   Cards redundantes.
-   Sombras exageradas.
-   Contraste insuficiente.
-   Tabelas difíceis de ler.
-   Modais grandes demais.
-   Telas que ainda pareçam pertencer a outro sistema.

Não considere uma tela concluída apenas porque o CSS foi alterado. Ela
precisa estar visualmente coerente e funcionalmente validada.

------------------------------------------------------------------------

## 12. Critérios de qualidade

Considere o trabalho concluído somente quando:

-   Todas as telas existentes tiverem sido auditadas.
-   Todas as telas fora do padrão tiverem sido corrigidas.
-   Componentes equivalentes apresentarem comportamento e aparência
    consistentes.
-   Os temas continuarem funcionando.
-   A responsividade estiver validada.
-   Não houver regressões funcionais introduzidas pelo redesign.
-   Não houver elementos visuais improvisados ou fora da identidade.
-   A interface apresentar hierarquia, precisão e acabamento
    consistentes em todo o sistema.

**Não esconda telas difíceis ou módulos pouco utilizados.** Eles também
fazem parte do produto e precisam seguir o padrão.

------------------------------------------------------------------------

## 13. Relatório final obrigatório

Ao terminar, apresente um resumo objetivo contendo:

1.  Telas auditadas.
2.  Telas reformuladas.
3.  Componentes globais criados ou padronizados.
4.  Arquivos principais alterados.
5.  Problemas encontrados e corrigidos.
6.  Testes executados e seus resultados.
7.  Pontos que não puderam ser concluídos, com justificativa.
8.  Eventuais riscos ou pendências restantes.

Não declare que tudo foi concluído se alguma tela não foi verificada.

------------------------------------------------------------------------

## 14. Direção final

Quero que você tenha rigor de uma equipe de produto da Apple, mas
discernimento para aplicá-lo a um sistema empresarial real.

Não confunda design Apple com: - Colocar blur em tudo. - Arredondar
todos os elementos exageradamente. - Usar gradientes aleatórios. -
Encher a interface de animações. - Deixar textos pequenos e finos. -
Transformar tudo em cards. - Copiar visualmente o macOS. - Remover
informação útil em nome do minimalismo.

O verdadeiro objetivo é alcançar **clareza, consistência, precisão,
simplicidade, acessibilidade e excelência de execução**.

Cada tela deve parecer parte do mesmo produto. Cada botão deve ter uma
razão para estar onde está. Cada espaçamento deve ser intencional. Cada
interação deve ser previsível. Cada informação deve ter a importância
visual adequada.

**Faça uma transformação real, completa e cuidadosa. Analise,
implemente, teste e refine. Não entregue apenas um plano: execute o
redesign no código do projeto.**
