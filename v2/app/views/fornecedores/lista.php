<?php
/**
 * Fornecedores (Design System, tela 21): números de compras, busca e situação, a lista com contato e
 * última compra, e as últimas entradas de mercadoria.
 * Espera: $fornecedores, $f, $p, $resumo, $entradas
 */
$titulo = 'Fornecedores';
$numero = fn (int $n): string => number_format($n, 0, ',', '.');
$iniciais = function (string $nome): string {
    $p = preg_split('/\s+/', trim($nome));
    return mb_strtoupper(mb_substr($p[0], 0, 1) . (count($p) > 1 ? mb_substr(end($p), 0, 1) : ''));
};
$variacao = $resumo['compras_anterior'] > 0 ? ($resumo['compras_mes'] - $resumo['compras_anterior']) / $resumo['compras_anterior'] * 100 : null;
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Fornecedores</h1>
    <p class="page-header__subtitle">Quem abastece a oficina de peças, lubrificantes e serviços.</p>
  </div>
  <div class="page-header__actions">
    <a class="btn btn--contorno" href="./estoque-entrada"><?= icone('download') ?> Entrada de mercadoria</a>
    <a class="btn btn--primary" href="./fornecedor-form"><?= icone('plus') ?> Novo fornecedor</a>
  </div>
</div>

<div class="stat-grid stat-grid--3 stat-grid--icone">
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Fornecedores ativos</span><?= icone('transfer') ?></div>
    <span class="stat-card__value"><?= $numero($resumo['ativos']) ?></span>
    <span class="stat-card__pe"><?= $numero($resumo['inativos']) ?> inativo<?= $resumo['inativos'] === 1 ? '' : 's' ?></span>
  </div>
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Comprado no mês</span><?php if ($variacao !== null): ?><span class="stat-card__chip" title="Comparado ao mesmo período do mês anterior"><?= $variacao >= 0 ? '+' : '−' ?><?= number_format(abs($variacao), 0) ?>%</span><?php endif; ?></div>
    <span class="stat-card__value"><?= dinheiro($resumo['compras_mes']) ?></span>
    <span class="stat-card__pe">em <?= $numero($resumo['notas_mes']) ?> entrada<?= $resumo['notas_mes'] === 1 ? '' : 's' ?> de mercadoria</span>
  </div>
  <a class="stat-card" href="./financeiro?tipo=PAGAR">
    <div class="stat-card__topo"><span class="stat-card__label">A pagar a fornecedores</span><?= icone('clock') ?></div>
    <span class="stat-card__value"><?= dinheiro($resumo['pagar_saldo']) ?></span>
    <span class="stat-card__pe"><?= $numero($resumo['pagar_n']) ?> conta<?= $resumo['pagar_n'] === 1 ? '' : 's' ?> em aberto</span>
  </a>
</div>

<form class="filtros filtros--solto card--espaco" method="get" action="./fornecedores">
  <div class="filtros__busca filtros__busca--largo"><?= icone('search') ?><input type="search" name="busca" value="<?= e($f['busca']) ?>" placeholder="Buscar por nome, CNPJ ou contato" aria-label="Buscar"></div>
  <?php require V2_RAIZ . '/app/views/layout/_status.php'; ?>
</form>

<div class="card card--tabela">
  <?php if (!$fornecedores): ?>
    <div class="vazio"><?= icone('transfer', 32, 1.25) ?>Nenhum fornecedor <?= $f['busca'] !== '' ? 'encontrado' : 'cadastrado ainda' ?>.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Fornecedor</th><th>CNPJ / CPF</th><th>Contato</th><th>E-mail</th><th>Última compra</th><th>Situação</th><th class="num"><span class="sr-only">Ações</span></th></tr></thead>
        <tbody>
          <?php foreach ($fornecedores as $fo): $link = './fornecedor?id=' . (int) $fo['id']; $fone = so_digitos($fo['telefone'] ?? ''); ?>
            <tr data-href="<?= e($link) ?>" class="<?= $fo['ativo'] ? '' : 'inativo' ?>">
              <td><span class="celula-pessoa"><span class="avatar avatar--quadrado"><?= e($iniciais($fo['nome'])) ?></span><a href="<?= e($link) ?>"><?= e($fo['nome']) ?></a></span></td>
              <td class="secundario tabular"><?= $fo['documento'] ? e(documento_fmt($fo['documento'])) : '—' ?></td>
              <td><?= e($fo['contato'] ?: '—') ?><?php if ($fone): ?><span class="sub"><?= e(telefone_fmt($fo['telefone'])) ?></span><?php endif; ?></td>
              <td><?= $fo['email'] ? '<a class="link-discreto" href="mailto:' . e($fo['email']) . '">' . e($fo['email']) . '</a>' : '—' ?></td>
              <td class="secundario tabular"><?= $fo['ultima_compra'] ? data_br($fo['ultima_compra']) : '—' ?></td>
              <td><span class="badge badge--<?= $fo['ativo'] ? 'neutral' : 'muted' ?>"><?= $fo['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
              <td class="num"><span class="acoes-linha">
                <?php if (strlen($fone) >= 10): ?><a class="btn btn--fantasma btn--icone" href="https://wa.me/55<?= e($fone) ?>" target="_blank" rel="noopener" aria-label="Conversar no WhatsApp" title="WhatsApp"><?= icone('message') ?></a><?php endif; ?>
                <details class="menu-mais"><summary class="btn btn--fantasma btn--icone" aria-label="Mais ações"><?= icone('more-h') ?></summary>
                  <div class="menu-mais__lista">
                    <a href="<?= e($link) ?>"><?= icone('eye') ?>Abrir cadastro</a>
                    <a href="./fornecedor-form?id=<?= (int) $fo['id'] ?>"><?= icone('edit') ?>Editar</a>
                    <a href="./financeiro?tipo=PAGAR&amp;busca=<?= e(rawurlencode($fo['nome'])) ?>"><?= icone('wallet') ?>Contas a pagar</a>
                  </div>
                </details>
              </span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?php $rotuloItens = 'fornecedores'; require V2_RAIZ . '/app/views/layout/_rodape.php'; ?>
</div>

<?php if ($entradas): ?>
  <div class="secao-titulo"><h2>Últimas entradas de mercadoria</h2><a href="./estoque?motivo=COMPRA">Ver todas<?= icone('chevron-right', 12, 2) ?></a></div>
  <div class="grade-3 grade-3--sem-margem">
    <?php foreach ($entradas as $en): ?>
      <a class="card cartao-entrada" href="./estoque?motivo=COMPRA&amp;busca=<?= e(rawurlencode((string) ($en['documento'] ?? ''))) ?>">
        <span class="cartao-entrada__topo"><strong><?= e($en['documento'] ?: 'Sem nota') ?></strong><span class="badge badge--success">Recebida</span></span>
        <span><?= e($en['fornecedor'] ?? 'Sem fornecedor') ?></span>
        <small><?= (int) $en['itens'] ?> ite<?= (int) $en['itens'] === 1 ? 'm' : 'ns' ?> · <?= dinheiro($en['total']) ?> · <?= data_br($en['criado_em']) ?><?= $en['usuario'] ? ' por ' . e(strtok($en['usuario'], ' ')) : '' ?></small>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
