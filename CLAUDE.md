# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Visão geral

Mecanize: sistema de gestão de oficina mecânica (TCC), em PHP puro, MVC simples, sem framework, rodando no XAMPP (Apache + MySQL). A pasta contém **duas versões lado a lado**:

- **v1** (`app/`, `public/`, `config/`, `tests/`, banco `mecanize_db`): versão antiga. **Não mexer**, salvo pedido explícito.
- **v2** (`v2/`, banco `mecanize_db_v2`): versão atual e onde todo o trabalho acontece (branch `v2-simplificacao`). Multi-oficina (SaaS), 17+ tabelas.

Salvo indicação em contrário, tudo abaixo trata da v2. A raiz tem `.htaccess` com `Require all denied`; só `v2/public` (e `public` da v1) são servidos pela web. URL local: `http://localhost/mecanize-tcc/v2/public/`.

## Comandos

PHP do XAMPP fica em `C:\Users\SENAI_2025\Progs\xampp\php\php.exe` (não está no PATH). Rodar da raiz do projeto.

```
# Testes da v2 (cria/recria o banco mecanize_db_v2_teste do zero a cada execução; nunca toca no banco real)
php.exe vendor\bin\phpunit -c v2\phpunit.xml
php.exe vendor\bin\phpunit -c v2\phpunit.xml v2\tests\OrdemServicoTest.php            # um arquivo
php.exe vendor\bin\phpunit -c v2\phpunit.xml --filter nomeDoMetodo                   # um teste

# Testes da v1 (usam .env.testing, abortam se DB_NAME != mecanize_db_test)
php.exe vendor\bin\phpunit

# Banco v2
php.exe database\backup_pdo.php             # backup antes de qualquer mudança
php.exe database\v2\migrar.php [--recriar]  # copia mecanize_db (somente leitura) para mecanize_db_v2 e confere os números
php.exe database\v2\desfazer.php --confirmar
php.exe v2\bin\criar_usuario_plataforma.php
```

Não há build, linter nem bundler: CSS e JS são servidos como estão. Dependências via Composer (`dompdf`, `picqer/php-barcode-generator`, `phpunit` 9).

Configuração em `.env` na raiz (modelo em `.env.example`). A v2 usa `DB_NAME_V2` ou, na falta, `DB_NAME` + `_v2`. `APP_DEBUG=true` só em desenvolvimento.

## Arquitetura da v2

**Front controller** `v2/public/index.php`: toda requisição passa, nesta ordem, por rota existe → CSRF (POST) → sessão revalidada no banco → login → nível de acesso → licença → controller. Rotas ficam em `v2/config/rotas.php` no formato `'rota' => [Controller, método, nível]`; o nível (`Permissao::PUBLICO | LOGADO | EQUIPE | TECNICO | ...`) é obrigatório e conferido **antes** do controller. Esconder link no menu (`v2/config/menu.php`) nunca é proteção. Rota nova precisa de método existente e nível válido (o `RotasEPermissoesTest` verifica).

**Estrutura**: `v2/app/{core,models,controllers,views}`, autoload feito em `v2/app/bootstrap.php`. Views são uma pasta por módulo, mais `views/layout/` (`app.php` é o layout logado, com muito JS embutido).

**Isolamento multi-oficina (duas barreiras)**:
1. Todo model herda de `core/Modelo.php`. Consulta em tabela de oficina sem `:oficina` no SQL nem é executada. O valor vem da conta logada (relida do banco a cada requisição), nunca de formulário ou URL.
2. O esquema (`database/v2/schema.sql`) usa chave composta `(oficina_id, id)` nas FKs, então o MySQL recusa misturar oficinas. Há muitos CHECK constraints (status de lançamento coerente com valor pago, movimento de estoque coerente com o motivo, dinheiro sempre com caixa, 1 caixa aberto por oficina).

Arquivos enviados ficam em `storage/oficinas/{id}/`. `core/Banco.php` mantém 1 conexão PDO por requisição; escritas multi-passo usam `core/Transacao.php`.

**Regras de negócio que atravessam vários arquivos**:
- A OS é também o orçamento: `ORCAMENTO → APROVADA → EM_EXECUCAO ⇄ AGUARDANDO_PECA → PRONTA → ENTREGUE` (ou `RECUSADA`/`CANCELADA`). Peça baixa estoque ao iniciar execução; sem saldo a OS vai para `AGUARDANDO_PECA` e os admins são notificados. Cancelar devolve as peças. Faturamento = OS `ENTREGUE` pelo `entregue_em`.
- Estoque só muda por movimentação (`movimentacoes_estoque`); item avulso na OS só pode ser serviço, peça vem do catálogo. Itens guardam descrição e preço da época.
- Financeiro: `lancamentos` (RECEBER/PAGAR, uma linha por parcela) + `movimentacoes_financeiras` (BAIXA/ESTORNO/SUPRIMENTO/SANGRIA).
- Licença vencida/suspensa deixa a oficina em modo somente leitura. Papéis: ADMIN, ATENDENTE, TECNICO (área do técnico no celular, só as OS dele) e usuário de plataforma (gestão de licenças).
- Excluir só funciona sem vínculos; senão a tela pede para desativar. A oficina nunca fica sem ADMIN ativo.
- CSP é `default-src 'self'`: **sem fetch para domínios externos** (ex.: ViaCEP não funciona). Libs JS vêm de `public/` da v1 via `asset()`.

**Testes v2** (`v2/tests/`): `bootstrap.php` recria o banco `*_teste` a partir de `schema.sql` e `Cenario.php` monta duas oficinas (A e B) para os testes de isolamento. A migração `database/v2/migracao_2026_11_24_tecnico.php` e o `schema.sql` precisam andar juntos quando o esquema muda.

## Front-end (v2/public/css)

Três camadas carregadas em ordem: `mecanize.css` (base grande, com regras antigas não usadas), `identidade.css` (visual atual estilo Apple, usa muito `!important`; para sobrescrever use seletor mais específico) e `responsivo.css` (pontos de quebra: até 599 px celular, 600-899 tablet, 900+ desktop com sidebar fixa). Tokens (`--acento`, `--r-card`, `--grafico-1..5` etc.) ficam em `:root` e `[data-theme="dark"]` do `identidade.css`; gráficos usam cinzas, nunca azul. Tabelas viram cartões no celular por JS do layout (`data-sem-cartao` desativa). Ícones: `icone('nome', tamanho)` (nomes em `core/Icones.php`). Evite reutilizar a classe `.situacao` (já existe no CSS base) e lembre que `.dlg` recebe padding/título grandes do `identidade.css`. A diretriz de design completa está em `v2/DIRETRIZ_APPLE_DESIGN_CLAUDE_CODE.md`; o histórico de decisões visuais em `Claude outputs/contexto-mecanize.md`.

## Convenções de interface (revisão visual de 26/11/2026)

- Valores: sempre `dinheiro()` (sinal colado, `-R$ 1,00`, espaço não separável após o `R# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Visão geral

Mecanize: sistema de gestão de oficina mecânica (TCC), em PHP puro, MVC simples, sem framework, rodando no XAMPP (Apache + MySQL). A pasta contém **duas versões lado a lado**:

- **v1** (`app/`, `public/`, `config/`, `tests/`, banco `mecanize_db`): versão antiga. **Não mexer**, salvo pedido explícito.
- **v2** (`v2/`, banco `mecanize_db_v2`): versão atual e onde todo o trabalho acontece (branch `v2-simplificacao`). Multi-oficina (SaaS), 17+ tabelas.

Salvo indicação em contrário, tudo abaixo trata da v2. A raiz tem `.htaccess` com `Require all denied`; só `v2/public` (e `public` da v1) são servidos pela web. URL local: `http://localhost/mecanize-tcc/v2/public/`.

## Comandos

PHP do XAMPP fica em `C:\Users\SENAI_2025\Progs\xampp\php\php.exe` (não está no PATH). Rodar da raiz do projeto.

```
# Testes da v2 (cria/recria o banco mecanize_db_v2_teste do zero a cada execução; nunca toca no banco real)
php.exe vendor\bin\phpunit -c v2\phpunit.xml
php.exe vendor\bin\phpunit -c v2\phpunit.xml v2\tests\OrdemServicoTest.php            # um arquivo
php.exe vendor\bin\phpunit -c v2\phpunit.xml --filter nomeDoMetodo                   # um teste

# Testes da v1 (usam .env.testing, abortam se DB_NAME != mecanize_db_test)
php.exe vendor\bin\phpunit

# Banco v2
php.exe database\backup_pdo.php             # backup antes de qualquer mudança
php.exe database\v2\migrar.php [--recriar]  # copia mecanize_db (somente leitura) para mecanize_db_v2 e confere os números
php.exe database\v2\desfazer.php --confirmar
php.exe v2\bin\criar_usuario_plataforma.php
```

Não há build, linter nem bundler: CSS e JS são servidos como estão. Dependências via Composer (`dompdf`, `picqer/php-barcode-generator`, `phpunit` 9).

Configuração em `.env` na raiz (modelo em `.env.example`). A v2 usa `DB_NAME_V2` ou, na falta, `DB_NAME` + `_v2`. `APP_DEBUG=true` só em desenvolvimento.

## Arquitetura da v2

**Front controller** `v2/public/index.php`: toda requisição passa, nesta ordem, por rota existe → CSRF (POST) → sessão revalidada no banco → login → nível de acesso → licença → controller. Rotas ficam em `v2/config/rotas.php` no formato `'rota' => [Controller, método, nível]`; o nível (`Permissao::PUBLICO | LOGADO | EQUIPE | TECNICO | ...`) é obrigatório e conferido **antes** do controller. Esconder link no menu (`v2/config/menu.php`) nunca é proteção. Rota nova precisa de método existente e nível válido (o `RotasEPermissoesTest` verifica).

**Estrutura**: `v2/app/{core,models,controllers,views}`, autoload feito em `v2/app/bootstrap.php`. Views são uma pasta por módulo, mais `views/layout/` (`app.php` é o layout logado, com muito JS embutido).

**Isolamento multi-oficina (duas barreiras)**:
1. Todo model herda de `core/Modelo.php`. Consulta em tabela de oficina sem `:oficina` no SQL nem é executada. O valor vem da conta logada (relida do banco a cada requisição), nunca de formulário ou URL.
2. O esquema (`database/v2/schema.sql`) usa chave composta `(oficina_id, id)` nas FKs, então o MySQL recusa misturar oficinas. Há muitos CHECK constraints (status de lançamento coerente com valor pago, movimento de estoque coerente com o motivo, dinheiro sempre com caixa, 1 caixa aberto por oficina).

Arquivos enviados ficam em `storage/oficinas/{id}/`. `core/Banco.php` mantém 1 conexão PDO por requisição; escritas multi-passo usam `core/Transacao.php`.

**Regras de negócio que atravessam vários arquivos**:
- A OS é também o orçamento: `ORCAMENTO → APROVADA → EM_EXECUCAO ⇄ AGUARDANDO_PECA → PRONTA → ENTREGUE` (ou `RECUSADA`/`CANCELADA`). Peça baixa estoque ao iniciar execução; sem saldo a OS vai para `AGUARDANDO_PECA` e os admins são notificados. Cancelar devolve as peças. Faturamento = OS `ENTREGUE` pelo `entregue_em`.
- Estoque só muda por movimentação (`movimentacoes_estoque`); item avulso na OS só pode ser serviço, peça vem do catálogo. Itens guardam descrição e preço da época.
- Financeiro: `lancamentos` (RECEBER/PAGAR, uma linha por parcela) + `movimentacoes_financeiras` (BAIXA/ESTORNO/SUPRIMENTO/SANGRIA).
- Licença vencida/suspensa deixa a oficina em modo somente leitura. Papéis: ADMIN, ATENDENTE, TECNICO (área do técnico no celular, só as OS dele) e usuário de plataforma (gestão de licenças).
- Excluir só funciona sem vínculos; senão a tela pede para desativar. A oficina nunca fica sem ADMIN ativo.
- CSP é `default-src 'self'`: **sem fetch para domínios externos** (ex.: ViaCEP não funciona). Libs JS vêm de `public/` da v1 via `asset()`.

**Testes v2** (`v2/tests/`): `bootstrap.php` recria o banco `*_teste` a partir de `schema.sql` e `Cenario.php` monta duas oficinas (A e B) para os testes de isolamento. A migração `database/v2/migracao_2026_11_24_tecnico.php` e o `schema.sql` precisam andar juntos quando o esquema muda.

## Front-end (v2/public/css)

Três camadas carregadas em ordem: `mecanize.css` (base grande, com regras antigas não usadas), `identidade.css` (visual atual estilo Apple, usa muito `!important`; para sobrescrever use seletor mais específico) e `responsivo.css` (pontos de quebra: até 599 px celular, 600-899 tablet, 900+ desktop com sidebar fixa). Tokens (`--acento`, `--r-card`, `--grafico-1..5` etc.) ficam em `:root` e `[data-theme="dark"]` do `identidade.css`; gráficos usam cinzas, nunca azul. Tabelas viram cartões no celular por JS do layout (`data-sem-cartao` desativa). Ícones: `icone('nome', tamanho)` (nomes em `core/Icones.php`). Evite reutilizar a classe `.situacao` (já existe no CSS base) e lembre que `.dlg` recebe padding/título grandes do `identidade.css`. A diretriz de design completa está em `v2/DIRETRIZ_APPLE_DESIGN_CLAUDE_CODE.md`; o histórico de decisões visuais em `Claude outputs/contexto-mecanize.md`.
). Nunca formate dinheiro à mão. Quantidades com palavra: `plural($n, 'conta', 'contas')`; nunca "(s)".
- Sem travessão em texto de interface: vazio é `-`, intervalo é "a" (`01/06 a 26/11`), separador de partes é `·`.
- Gráficos só em tons de cinza (`--grafico-1..5`), nunca azul. Série diária de valor usa `linha_reta()`; `curva_suave()` só para médias móveis.
- Botão do cabeçalho: um primário (contorno azul) e as ações secundárias em `btn--ghost` (cinza). `btn--contorno` e `btn--primary` ficam iguais no cabeçalho.
- Todo `<label>` tem `for` ligado ao `id` do campo. Variação percentual acima de 999% não é exibida (base de comparação vazia).

## Preferências do usuário

- Responder em português do Brasil, de forma direta e curta.
- **Nunca usar travessão longo** em respostas, textos de interface ou comentários; usar ponto, vírgula ou reescrever.
- Mexer só no que foi pedido (ex.: "só trazer as margens" não autoriza reorganizar a estrutura). Interface minimalista, premium, estilo Apple.
- Conferir o resultado de verdade (interações, não só visual).
