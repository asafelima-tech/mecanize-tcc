<?php
/** Orçamento / OS para imprimir (ou salvar em PDF pelo navegador). Espera: $os, $itens, $oficina */
$ativos = array_filter($itens, fn($i) => $i['estoque_status'] !== 'DEVOLVIDO');
$ehOrcamento = in_array($os['status'], ['ORCAMENTO', 'RECUSADA'], true);
$qtd = fn($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title><?= $ehOrcamento ? 'Orçamento' : 'Ordem de serviço' ?> <?= os_numero($os['numero']) ?></title>
    <?php require V2_RAIZ . '/app/views/layout/_favicon.php'; ?>
  <style>
    @font-face { font-family: "Inter var"; src: url("./fonts/inter-variavel.woff2") format("woff2"); font-weight: 100 900; font-style: normal; font-display: swap; }
    * { box-sizing: border-box; }
    body { font: 12px/1.45 Arial, "Helvetica Neue", Helvetica, sans-serif; color: #1D1D1F; margin: 0; padding: 28px; font-variant-numeric: tabular-nums; }
    .folha { max-width: 780px; margin: 0 auto; }
    header { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #1D1D1F; padding-bottom: 12px; }
    h1 { font-size: 18px; margin: 0; } h2 { font-size: 13px; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: .04em; color: #6E6E73; }
    .doc { text-align: right; } .doc strong { font-size: 16px; display: block; }
    .grade { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; }
    table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    th, td { padding: 6px 4px; border-bottom: 1px solid #E8E8ED; text-align: left; vertical-align: top; }
    th { font-size: 10px; text-transform: uppercase; color: #6E6E73; } .n { text-align: right; white-space: nowrap; }
    tfoot td { border: 0; } .total td { font-size: 14px; font-weight: 700; border-top: 2px solid #1D1D1F; }
    .assinaturas { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 48px; text-align: center; }
    .assinaturas div { border-top: 1px solid #1D1D1F; padding-top: 4px; }
    .obs { white-space: pre-line; } .acoes { text-align: center; margin-bottom: 16px; }
    .logo-oficina { max-height: 56px; max-width: 200px; object-fit: contain; display: block; margin-bottom: 6px; }
    @media print { .acoes { display: none; } body { padding: 0; } }

    /* Só na tela: fundo do sistema, a folha como papel e uma barra fina para voltar e imprimir. Não vale para a impressão. */
    @media screen {
      body { padding: 0; background: #F5F5F7; }
      .acoes { position: sticky; top: 0; z-index: 5; height: 56px; margin: 0; padding: 0 24px; display: flex; align-items: center; justify-content: space-between; gap: 12px;
        background: rgba(245, 245, 247, .8); -webkit-backdrop-filter: saturate(180%) blur(20px); backdrop-filter: saturate(180%) blur(20px); box-shadow: inset 0 -1px 0 rgba(0, 0, 0, .08);
        font-family: "SF Pro Text", -apple-system, BlinkMacSystemFont, "Inter var", "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 14px; }
      .acoes__voltar { display: inline-flex; align-items: center; gap: 4px; color: #0066CC; text-decoration: none; border-radius: 980px; }
      .acoes__voltar:hover { text-decoration: underline; }
      .acoes__titulo { position: absolute; left: 50%; transform: translateX(-50%); font-weight: 600; color: #1D1D1F; white-space: nowrap; }
      .acoes__imprimir { height: 36px; padding: 0 18px; border: 0; border-radius: 980px; display: inline-flex; align-items: center; gap: 6px; background: #0071E3; color: #FFFFFF;
        font: inherit; cursor: pointer; transition: background-color .2s cubic-bezier(.25, .1, .25, 1); }
      .acoes__imprimir:hover { background: #0077ED; }
      .acoes__voltar:focus-visible, .acoes__imprimir:focus-visible { outline: none; box-shadow: 0 0 0 4px rgba(0, 113, 227, .3); }
      .folha { max-width: 780px; margin: 32px auto 56px; padding: 48px; background: #FFFFFF; border-radius: 8px; box-shadow: 0 1px 2px rgba(0, 0, 0, .04), 0 12px 40px rgba(0, 0, 0, .07); box-sizing: content-box; }
    }
    @media screen and (max-width: 899.98px) {
      .folha { margin: 16px; padding: 28px 24px; max-width: none; overflow-x: auto; }
    }
    @media screen and (max-width: 599.98px) {
      .acoes { padding: 0 16px; }
      .acoes__titulo, .acoes__extra { display: none; }
      .folha { margin: 0; padding: 24px 16px; border-radius: 0; box-shadow: none; }
    }
  </style>
</head>
<body>
<nav class="acoes" aria-label="Ações">
  <a class="acoes__voltar" href="./os?id=<?= (int) $os['id'] ?>"><?= icone('chevron-left', 16, 2) ?>Voltar<span class="acoes__extra"> para a OS</span></a>
  <span class="acoes__titulo"><?= $ehOrcamento ? 'Orçamento' : 'Ordem de serviço' ?> <?= os_numero($os['numero']) ?></span>
  <button type="button" class="acoes__imprimir" onclick="window.print()"><?= icone('print', 16) ?>Imprimir<span class="acoes__extra"> ou salvar PDF</span></button>
</nav>
<div class="folha">
  <header>
    <div>
      <?php if (!empty($oficina['logo_caminho'])): ?><img class="logo-oficina" src="./oficina-logo" alt=""><?php endif; ?>
      <h1><?= e($oficina['nome']) ?></h1>
      <div><?= e(implode(' · ', array_filter([$oficina['cnpj'] ? 'CNPJ ' . documento_fmt($oficina['cnpj']) : null, $oficina['telefone'] ? telefone_fmt($oficina['telefone']) : null, $oficina['email']]))) ?></div>
      <div><?= e(implode(', ', array_filter([$oficina['endereco'], $oficina['cidade'] ? $oficina['cidade'] . ($oficina['uf'] ? '/' . $oficina['uf'] : '') : null]))) ?></div>
    </div>
    <div class="doc"><?= $ehOrcamento ? 'Orçamento' : 'Ordem de serviço' ?><strong><?= os_numero($os['numero']) ?></strong>
      <?= data_br($os['criado_em']) ?><?= $ehOrcamento && $os['validade_orcamento'] ? '<br>Válido até ' . data_br($os['validade_orcamento']) : '' ?></div>
  </header>

  <h2>Cliente e veículo</h2>
  <div class="grade">
    <div><strong><?= e($os['cliente']) ?></strong><?= $os['cliente_documento'] ? '<br>' . e(documento_fmt($os['cliente_documento'])) : '' ?></div>
    <div><?= $os['cliente_telefone'] ? e(telefone_fmt($os['cliente_telefone'])) : '' ?><?= $os['cliente_email'] ? '<br>' . e($os['cliente_email']) : '' ?></div>
    <div><strong><?= e($os['placa']) ?></strong> · <?= e($os['modelo']) ?><?= $os['ano'] ? ' · ' . e($os['ano']) : '' ?><?= $os['cor'] ? ' · ' . e($os['cor']) : '' ?></div>
    <div><?= $os['km_veiculo'] ? number_format((int) $os['km_veiculo'], 0, ',', '.') . ' km' : '' ?><?= $os['previsao_conclusao'] ? ' · Previsão: ' . data_br($os['previsao_conclusao'], true) : '' ?></div>
  </div>

  <?php if ($os['defeito_relatado'] || $os['diagnostico']): ?>
    <h2>Relato e diagnóstico</h2>
    <?php if ($os['defeito_relatado']): ?><div class="obs"><strong>Relato:</strong> <?= e($os['defeito_relatado']) ?></div><?php endif; ?>
    <?php if ($os['diagnostico']): ?><div class="obs"><strong>Diagnóstico:</strong> <?= e($os['diagnostico']) ?></div><?php endif; ?>
  <?php endif; ?>

  <h2>Serviços e peças</h2>
  <table>
    <thead><tr><th>Descrição</th><th class="n">Qtd</th><th class="n">Unitário</th><th class="n">Desconto</th><th class="n">Total</th></tr></thead>
    <tbody>
      <?php foreach ($ativos as $i): ?>
        <tr><td><?= e($i['descricao']) ?><?= $i['origem'] === 'ADICIONAL' ? ' <em>(adicional)</em>' : '' ?></td><td class="n"><?= $qtd($i['quantidade']) ?></td>
          <td class="n"><?= dinheiro($i['valor_unitario']) ?></td><td class="n"><?= (float) $i['desconto'] > 0 ? '-' . dinheiro($i['desconto']) : '' ?></td><td class="n"><?= dinheiro($i['valor_total']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><td colspan="4" class="n">Peças</td><td class="n"><?= dinheiro($os['valor_pecas']) ?></td></tr>
      <tr><td colspan="4" class="n">Serviços</td><td class="n"><?= dinheiro($os['valor_servicos']) ?></td></tr>
      <?php if ((float) $os['valor_desconto'] > 0): ?><tr><td colspan="4" class="n">Descontos</td><td class="n">-<?= dinheiro($os['valor_desconto']) ?></td></tr><?php endif; ?>
      <tr class="total"><td colspan="4" class="n">Total</td><td class="n"><?= dinheiro($os['valor_total']) ?></td></tr>
    </tfoot>
  </table>

  <?php if ($os['observacoes']): ?><h2>Observações</h2><div class="obs"><?= e($os['observacoes']) ?></div><?php endif; ?>
  <?php if ($os['checklist_entrega'] && $os['status'] === 'ENTREGUE'): ?><h2>Entrega</h2><div class="obs"><?= e($os['checklist_entrega']) ?></div><?php endif; ?>

  <div class="assinaturas">
    <div><?= $os['assinatura_cliente'] ? '<img src="' . e($os['assinatura_cliente']) . '" alt="" style="height:50px;display:block;margin:-54px auto 4px">' : '' ?>
      <?= $ehOrcamento ? 'Aprovação do cliente' : 'Cliente' ?>: <?= e($os['aprovado_por_nome'] ?: $os['cliente']) ?></div>
    <div><?= e($oficina['nome']) ?></div>
  </div>
</div>
</body>
</html>
