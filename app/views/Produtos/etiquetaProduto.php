<?php
/*
|--------------------------------------------------------------------------
| VIEW: etiquetaProduto — folha de etiquetas de prateleira
|--------------------------------------------------------------------------
| Renderizada direto pelo ProdutoController (sem o layout do sistema): é uma
| página de impressão, sidebar e topbar só sujariam o papel.
|
| Variáveis esperadas:
|
| $produto        — array do produto (Produtos::getById)
| $categoria      — nome da categoria (string, pode vir vazia)
| $codigoImpresso — código de barras cadastrado ou, na falta dele, o interno
| $barcodeSvg     — SVG do código de barras já pronto ('' se não deu para gerar)
|--------------------------------------------------------------------------
*/

$preco = number_format((float) ($produto['preco_venda'] ?? 0), 2, ',', '.');

// Linha de contexto abaixo do nome: só o que estiver preenchido, senão
// sobrariam separadores soltos ("· ·") na etiqueta.
$detalhes = array_filter([
    $produto['volume_embalagem'] ?? '',
    $categoria,
], fn($v) => trim((string) $v) !== '');
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Etiqueta · <?= htmlspecialchars($produto['nome'] ?? 'Produto') ?></title>
    <link rel="stylesheet" href="./assets/vendor/inter.css">
    <style>
        :root {
            --color-surface: #FFFFFF;
            --color-border: #E2E8F0;
            --color-border-strong: #CBD5E1;
            --color-text: #111826;
            --color-text-secondary: #1E293B;
            --color-text-muted: #94A3B8;
            --color-primary: #003BDD;
            --color-primary-gradient: linear-gradient(135deg, #003BDD 0%, #0030C4 100%);
            --radius-md: 8px;
            --font-sans: "Inter", "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-sans);
            font-size: 14px;
            color: var(--color-text);
            background: #F1F5F9;
            padding: 28px 20px 60px;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Barra de controle (some na impressão) ── */
        .barra {
            max-width: 210mm;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }

        .barra__marca {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .barra__marca .ponto {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--color-primary-gradient);
        }

        .barra__acoes {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .campo-qtd {
            display: flex;
            align-items: center;
            gap: 8px;
            height: 38px;
            padding: 0 12px;
            background: #FFF;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 600;
            color: var(--color-text-secondary);
        }

        .campo-qtd input {
            width: 52px;
            border: 0;
            outline: none;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            color: var(--color-text);
            text-align: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            height: 38px;
            padding: 0 16px;
            border: none;
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: -0.01em;
            cursor: pointer;
            transition: all .15s ease;
        }

        .btn--primary {
            background: var(--color-primary-gradient);
            color: #FFF;
            box-shadow: 0 1px 2px rgba(37, 99, 235, .22), 0 3px 10px rgba(37, 99, 235, .18);
        }

        .btn--primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(37, 99, 235, .38);
        }

        .btn--ghost {
            background: #FFF;
            color: var(--color-text-secondary);
            border: 1.5px solid var(--color-border);
            text-decoration: none;
        }

        .btn--ghost:hover {
            border-color: var(--color-border-strong);
        }

        /* ── Folha ── */
        .folha {
            max-width: 210mm;
            margin: 0 auto;
            padding: 10mm 8mm;
            background: var(--color-surface);
            border-radius: 4px;
            box-shadow: 0 4px 10px rgba(17, 24, 38, .07), 0 10px 28px rgba(17, 24, 38, .07);
        }

        .etiquetas {
            display: flex;
            flex-wrap: wrap;
            gap: 4mm;
        }

        /* 60x40mm: tamanho comum de etiqueta de prateleira e o que cabe em
           3 colunas no A4 com a margem de impressão abaixo. */
        .etiqueta {
            width: 60mm;
            height: 40mm;
            padding: 3mm 3.5mm;
            display: flex;
            flex-direction: column;
            border: 1px dashed var(--color-border-strong);
            border-radius: 2mm;
            /* Etiqueta cortada ao meio pela quebra de página é papel perdido. */
            break-inside: avoid;
            page-break-inside: avoid;
            overflow: hidden;
        }

        .etiqueta__topo {
            font-size: 6.5pt;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #64748B;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .etiqueta__nome {
            margin-top: 1mm;
            font-size: 10pt;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -0.01em;
            /* Nome comprido ocupa no máximo duas linhas: a terceira empurraria
               o preço e o código para fora da etiqueta. */
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .etiqueta__detalhes {
            margin-top: 0.8mm;
            font-size: 7pt;
            color: #64748B;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .etiqueta__preco {
            margin-top: auto;
            display: flex;
            align-items: baseline;
            gap: 1.5mm;
            font-size: 15pt;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .etiqueta__preco small {
            font-size: 8pt;
            font-weight: 700;
            color: #64748B;
        }

        .etiqueta__rodape {
            margin-top: 1mm;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 2mm;
        }

        .etiqueta__barras {
            flex: 1;
            min-width: 0;
        }

        .etiqueta__barras svg {
            display: block;
            width: 100%;
            height: 9mm;
        }

        .etiqueta__codigo {
            margin-top: 0.5mm;
            font-family: "Consolas", "Courier New", monospace;
            font-size: 6.5pt;
            letter-spacing: .04em;
            color: #334155;
        }

        .etiqueta__local {
            flex-shrink: 0;
            max-width: 22mm;
            text-align: right;
            font-size: 6.5pt;
            font-weight: 700;
            line-height: 1.3;
            color: #334155;
            overflow: hidden;
        }

        .etiqueta__local span {
            display: block;
            font-weight: 600;
            color: #94A3B8;
        }

        /* ── Impressão ── */
        @page {
            size: A4;
            margin: 8mm;
        }

        @media print {

            body {
                background: #FFF;
                padding: 0;
            }

            .barra {
                display: none;
            }

            .folha {
                max-width: none;
                margin: 0;
                padding: 0;
                border-radius: 0;
                box-shadow: none;
            }
        }
    </style>
</head>

<body>

    <div class="barra">
        <div class="barra__marca">
            <span class="ponto"></span> Mecanize · Etiqueta de produto
        </div>

        <div class="barra__acoes">
            <label class="campo-qtd">
                Cópias
                <input type="number" id="qtd" value="1" min="1" max="60" step="1">
            </label>

            <a href="./editProduto?id=<?= (int) $produto['id_produto'] ?>" class="btn btn--ghost">Voltar</a>

            <button type="button" class="btn btn--primary" onclick="window.print()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9" />
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                    <rect x="6" y="14" width="12" height="8" />
                </svg>
                Imprimir
            </button>
        </div>
    </div>

    <div class="folha">
        <div class="etiquetas" id="etiquetas">

            <div class="etiqueta" id="modelo-etiqueta">
                <div class="etiqueta__topo">
                    <?= htmlspecialchars(trim((string) ($produto['marca'] ?? '')) ?: 'Mecanize') ?>
                </div>

                <div class="etiqueta__nome"><?= htmlspecialchars($produto['nome'] ?? '') ?></div>

                <?php if ($detalhes): ?>
                    <div class="etiqueta__detalhes"><?= htmlspecialchars(implode(' · ', $detalhes)) ?></div>
                <?php endif; ?>

                <div class="etiqueta__preco">
                    <small>R$</small><?= $preco ?>
                </div>

                <div class="etiqueta__rodape">
                    <div class="etiqueta__barras">
                        <?= $barcodeSvg /* SVG gerado pelo controller, não é entrada do usuário */ ?>
                        <div class="etiqueta__codigo"><?= htmlspecialchars($codigoImpresso) ?></div>
                    </div>

                    <?php if (trim((string) ($produto['localizacao'] ?? '')) !== ''): ?>
                        <div class="etiqueta__local">
                            <span>Local</span>
                            <?= htmlspecialchars($produto['localizacao']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Cópias da mesma etiqueta: quem repõe estoque quase nunca quer uma só.
        const campoQtd = document.getElementById('qtd');
        const container = document.getElementById('etiquetas');
        const modelo = document.getElementById('modelo-etiqueta');

        function aplicarQuantidade() {
            const quantidade = Math.min(60, Math.max(1, parseInt(campoQtd.value, 10) || 1));
            campoQtd.value = quantidade;

            container.replaceChildren();

            for (let i = 0; i < quantidade; i++) {
                const copia = modelo.cloneNode(true);
                copia.removeAttribute('id');
                container.appendChild(copia);
            }
        }

        campoQtd.addEventListener('input', aplicarQuantidade);
        campoQtd.addEventListener('change', aplicarQuantidade);
    </script>

</body>

</html>
