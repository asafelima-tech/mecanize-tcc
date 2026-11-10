# Mecanize v2 — banco simplificado (17 tabelas, multi-oficina)

| Arquivo | Para quê |
|---|---|
| `schema.sql` | Estrutura da v2. Toda tabela de dados tem `oficina_id` e as ligações usam chave composta `(oficina_id, id)`: o banco recusa misturar dados de oficinas diferentes. |
| `migrar.php` | Copia o `mecanize_db` (só leitura) para um banco **novo**, `mecanize_db_v2`, e confere os números. Se algo não bater, nada é gravado. |
| `desfazer.php` | Apaga só o `mecanize_db_v2`. O banco antigo não é tocado. |

## Passo a passo (XAMPP)

1. Faça um backup: `C:\xampp\php\php.exe database\backup_pdo.php`
2. Migre: `C:\xampp\php\php.exe database\v2\migrar.php`
3. Leia a conferência: todas as linhas devem terminar em `OK`.
4. Para refazer do zero: `...\migrar.php --recriar`. Para desistir: `...\desfazer.php --confirmar`.

O sistema continua usando o `mecanize_db` até o `.env` ser trocado para `DB_NAME=mecanize_db_v2`. Isso só acontece quando o código da v2 estiver pronto.

## O que a migração faz com os dados

- Todos os dados vão para a oficina 1, **Oficina Demonstração**.
- Orçamento que virou OS: a aprovação, a validade e o total aprovado vão para a OS. O orçamento original, com os itens e as versões, fica guardado no `historico`.
- Orçamento sem OS vira OS no status `ORCAMENTO`, `APROVADA`, `RECUSADA` ou `CANCELADA`.
- Os itens da OS guardam a própria descrição, a categoria e o preço da época.
- A mão de obra com técnico era a comissão: vai para o serviço da OS, com o mesmo valor.
- Estoque: os movimentos da v1 e as baixas por OS são copiados. Um "saldo inicial" faz o livro de movimentações fechar exatamente com o estoque atual de cada produto.
- **Não migrados (decisão de 10/11/2026):**
  - os 4 lançamentos de teste do caixa (ids 17–20);
  - os tokens vencidos de recuperação de senha;
  - as notificações de módulos removidos;
  - as subcategorias, que não eram usadas.
- A migração **para** se alguma tabela removida tiver dados, para não descartar nada em silêncio.
