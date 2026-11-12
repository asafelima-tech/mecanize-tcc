# Testes em navegador (celular)

Ferramentas para conferir **o que o PHPUnit não enxerga**: como a tela fica num celular, se algo estoura a largura,
se o botão tem tamanho de toque e se o JavaScript das telas faz o que promete. Usam o Chrome de verdade, com
**emulação de celular** (390 × 844) pelo protocolo DevTools — não há Selenium nem dependência para instalar.

Requisitos: Node 22 ou mais novo (tem `WebSocket` nativo) e o Google Chrome. Se o Chrome não estiver em
`C:\Program Files\Google\Chrome\Application\chrome.exe`, aponte a variável `CHROME_PATH`.

## O que tem aqui

| Arquivo | Para quê |
|---|---|
| `renderizar_view.php` | Renderiza uma view **com o layout** e dados sintéticos, **sem banco**: `php tests/browser/renderizar_view.php Tecnico/painelTecnico painelTecnico TECNICO dados.php saida.html` |
| `fotografar.mjs` | Fotografa um `.html` em celular ou desktop e mede o estouro horizontal e os alvos de toque pequenos: `node tests/browser/fotografar.mjs saida.html foto.png 390 900` |
| `executar_roteiro.mjs` | Roda um roteiro de verificações (`.js` que devolve `JSON.stringify(log)`) dentro da página: `node tests/browser/executar_roteiro.mjs saida.html roteiro.js` |
| `componente_placa_ocr.mjs` | Testa `public/assets/js/placa-ocr.js` e `foto-upload.js` (leitura de placa por foto: conferência antes de preencher, falhas, cancelar, redução da foto). 32 verificações; **não precisa de servidor nem de banco**: `node tests/browser/componente_placa_ocr.mjs` |
| `roteiro_evidencias_os.js` | Roteiro da seção de fotos da tela do técnico (`Tecnico/osTecnico`) |

## Como usar numa tela nova

1. Escreva um arquivo PHP que **devolva o array** que o controller passaria para a view (veja os exemplos citados
   nos testes: `$minhas`, `$avaliacoes`, … para o painel do técnico).
2. Renderize com `renderizar_view.php` e fotografe com `fotografar.mjs`.
3. A saída JSON do `fotografar.mjs` diz `estouros` (elementos que passam da largura da janela) e `alvosPequenos`
   (botões/links/campos com menos de 32 px). Abra o `.png` e olhe: foi assim que apareceu, por exemplo, que a classe
   CSS `.erro` (das telas de erro) esticava qualquer elemento com esse nome — a bolha de uma mensagem com falha na
   central de conversas ocupava 900 px de altura.

As imagens carregam CSS e fontes do servidor apontado por `AUD_BASE` (padrão `http://127.0.0.1:8099/`); sem servidor
a tela sai sem estilo. Subir um servidor só para os arquivos estáticos:
`php -S 127.0.0.1:8099 -t public`.

## Limites (para ninguém se enganar)

- A renderização usa **dados inventados** e as chamadas ao servidor (`fetch`) são simuladas: isto confere
  **layout e JavaScript**, não regra de negócio. Regra de negócio é com o PHPUnit e com os roteiros HTTP.
- Não substitui abrir o sistema num celular de verdade: câmera, teclado e rede real só se testam no aparelho.
