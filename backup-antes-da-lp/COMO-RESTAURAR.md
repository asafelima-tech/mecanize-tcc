# Backup: versão antes da mudança para "landing page"

Esta pasta guarda o visual da v2 como estava antes da reformulação em estilo de página de produto (navegação no topo, títulos enormes). É a versão com barra lateral escura e cartões, já com a tela da OS, as sessões de caixa, a Plataforma e o link do cliente refeitos.

Só três arquivos são diferentes da versão atual (estilo dos aplicativos da Apple). Todo o resto da pasta `v2` é igual nas duas versões:

| Arquivo | Diferença |
|---|---|
| `public/css/mecanize.css` | sem a seção 30 (identidade nova) |
| `app/views/layout/app.php` | `<body>` sem a classe `ap` |
| `LEIA-ME.md` | sem a seção da identidade nova |

## Para voltar a esta versão

Copie os três arquivos desta pasta por cima dos mesmos caminhos dentro de `v2`. Para voltar à versão atual, use o commit do git (ou o backup que você fez dela).

O `.htaccess` desta pasta impede que os arquivos sejam abertos pelo navegador.
