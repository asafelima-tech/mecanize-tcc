<?php
/**
 * Papel timbrado dos documentos em PDF — par do _docPdfEstilos.php.
 *
 * Variáveis esperadas (todas opcionais menos $docTipo):
 *   $docTipo      string  rótulo do documento ("Ordem de Serviço", "Orçamento")
 *   $docNumero    string  número/identificação em destaque
 *   $docSubtitulo string  linha de apoio (período do relatório, por exemplo)
 *   $docStatus    array   ['label' => '', 'bg' => '#RRGGBB', 'cor' => '#RRGGBB']
 */

$docTipo      = $docTipo      ?? 'Documento';
$docNumero    = $docNumero    ?? '';
$docSubtitulo = $docSubtitulo ?? '';
$docStatus    = $docStatus    ?? null;

// Só entra no timbre o que a oficina preencheu no .env — ver config/empresa.php.
$docContato = array_filter([
    EMPRESA_ENDERECO,
    EMPRESA_TELEFONE,
    EMPRESA_EMAIL,
    EMPRESA_CNPJ !== '' ? 'CNPJ ' . EMPRESA_CNPJ : '',
]);

/*
 * Logotipo do timbre, carregado do disco por caminho absoluto (o Dompdf não
 * busca por URL) e com as barras normalizadas — no Windows o realpath volta
 * com barra invertida.
 *
 * É a versão JPEG de propósito: o Dompdf embute PNG através da extensão GD e
 * estoura o documento inteiro quando ela não está habilitada, enquanto JPEG
 * ele embute sozinho. Como a folha é branca, a transparência do PNG não faz
 * falta — e o documento deixa de depender de configuração do servidor.
 */
$docLogo = realpath(__DIR__ . '/../../../public/assets/img/logo-mecanize-doc.jpg');
$docTemLogo = $docLogo !== false;

if ($docTemLogo) {
    $docLogo = str_replace('\\', '/', $docLogo);
}

// Oficina que personalizou o nome continua identificada por escrito: a
// logomarca do sistema não pode falar pelo nome de outra empresa.
$docNomeProprio = EMPRESA_NOME !== 'Mecanize' ? EMPRESA_NOME : '';
?>

<div class="doc-faixa"></div>

<table class="doc-head">
    <tr>
        <td style="width:55%;">
            <?php if ($docTemLogo): ?>
                <img src="<?= htmlspecialchars($docLogo) ?>" alt="<?= htmlspecialchars(EMPRESA_NOME) ?>" style="height:26px;">

                <?php if ($docNomeProprio !== ''): ?>
                    <div class="doc-empresa" style="margin-top:4px;"><?= htmlspecialchars($docNomeProprio) ?></div>
                <?php endif; ?>

                <?php if ($docContato): ?>
                    <div class="doc-contato" style="margin-top:4px;"><?= htmlspecialchars(implode(' · ', $docContato)) ?></div>
                <?php endif; ?>
            <?php else: ?>
                <table>
                    <tr>
                        <td style="width:34px;">
                            <div class="doc-selo">M</div>
                        </td>
                        <td style="padding-left:10px;">
                            <div class="doc-empresa"><?= htmlspecialchars(EMPRESA_NOME) ?></div>
                            <?php if ($docContato): ?>
                                <div class="doc-contato"><?= htmlspecialchars(implode(' · ', $docContato)) ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            <?php endif; ?>
        </td>

        <td class="doc-ref" style="width:45%;">
            <div class="doc-tipo"><?= htmlspecialchars($docTipo) ?></div>

            <?php if ($docNumero !== ''): ?>
                <div class="doc-numero"><?= htmlspecialchars($docNumero) ?></div>
            <?php endif; ?>

            <?php if ($docSubtitulo !== ''): ?>
                <div class="doc-sub"><?= htmlspecialchars($docSubtitulo) ?></div>
            <?php endif; ?>

            <?php if ($docStatus): ?>
                <span class="doc-status" style="background: <?= $docStatus['bg'] ?>; color: <?= $docStatus['cor'] ?>;">
                    <?= htmlspecialchars($docStatus['label']) ?>
                </span>
            <?php endif; ?>

            <div class="doc-emissao">Emitido em <?= date('d/m/Y \à\s H:i') ?></div>
        </td>
    </tr>
</table>
