<?php
/**
 * Peças e serviços (Design System, tela 19): números do catálogo, tipo, busca, categoria e situação,
 * e a lista com código, tipo, quantidade, preço e situação do estoque.
 * Espera: $produtos, $f, $p, $categorias, $resumo
 */
$titulo = 'Peças e serviços';
$admin = Contexto::perfil() === 'ADMIN';
$qtd = fn($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
$numero = fn (int $n): string => number_format($n, 0, ',', '.');
$ocultos = array_filter(['tipo' => $f['tipo']]);
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Peças e serviços</h1>
    <p class="page-header__subtitle">Catálogo de peças em estoque e serviços de mão de obra.</p>
  </div>
  <?php if ($admin): ?>
    <div class="page-header__actions">
      <a class="btn btn--contorno" href="./estoque"><?= icone('part') ?> Estoque</a>
      <details class="menu-mais"><summary class="btn btn--primary"><?= icone('plus') ?> Novo item<?= icone('chevron-down', 14) ?></summary>
        <div class="menu-mais__lista">
          <a href="./produto-form"><?= icone('part') ?>Peça</a>
          <a href="./produto-form?tipo=SERVICO"><?= icone('key') ?>Serviço</a>
        </div>
      </details>
    </div>
  <?php endif; ?>
</div>

<div class="stat-grid stat-grid--icone<?= $admin ? '' : ' stat-grid--3' ?>">
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Itens cadastrados</span><?= icone('part') ?></div>
    <span class="stat-card__value"><?= $numero($resumo['pecas'] + $resumo['servicos']) ?></span>
    <span class="stat-card__pe"><?= $numero($resumo['pecas']) ?> peças · <?= $numero($resumo['servicos']) ?> serviços</span>
  </div>
  <a class="stat-card" href="./produtos?situacao=baixo">
    <div class="stat-card__topo"><span class="stat-card__label">Estoque baixo</span><?= icone('warning') ?></div>
    <span class="stat-card__value"><?= $numero($resumo['baixo']) ?></span>
    <span class="stat-card__pe">no mínimo ou abaixo dele</span>
  </a>
  <a class="stat-card" href="./produtos?situacao=esgotado">
    <div class="stat-card__topo"><span class="stat-card__label">Esgotados</span><?= icone('close') ?></div>
    <span class="stat-card__value"><?= $numero($resumo['esgotados']) ?></span>
    <span class="stat-card__pe">sem saldo em estoque</span>
  </a>
  <?php if ($admin): ?>
    <div class="stat-card stat-card--destaque">
      <div class="stat-card__topo"><span class="stat-card__label">Valor em inventário</span></div>
      <span class="stat-card__value"><?= dinheiro($resumo['inventario']) ?></span>
      <span class="stat-card__pe">custo das peças em estoque</span>
    </div>
  <?php endif; ?>
</div>

<form class="filtros filtros--solto card--espaco" method="get" action="./produtos">
  <nav class="abas abas--compactas" aria-label="Tipo">
    <?php foreach (['' => 'Todos', 'PECA' => 'Peças', 'SERVICO' => 'Serviços'] as $k => $r): ?>
      <a href="<?= e(url_com(['tipo' => $k ?: null, 'pagina' => null])) ?>"<?= $f['tipo'] === $k ? ' class="ativa" aria-current="page"' : '' ?>><?= $r ?></a>
    <?php endforeach; ?>
  </nav>
  <?php foreach ($ocultos as $k => $v): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endforeach; ?>
  <div class="filtros__busca filtros__busca--largo"><?= icone('search') ?><input type="search" name="busca" value="<?= e($f['busca']) ?>" placeholder="Buscar por código, nome ou marca" aria-label="Buscar"></div>
  <?php if ($categorias): ?>
    <select name="categoria" aria-label="Categoria">
      <option value="">Todas as categorias</option>
      <?php foreach ($categorias as $c): ?><option <?= $f['categoria'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
    </select>
  <?php endif; ?>
  <select name="situacao" aria-label="Situação">
    <?php foreach (['ativos' => 'Ativos', 'baixo' => 'Estoque baixo', 'esgotado' => 'Esgotados', 'inativos' => 'Inativos', 'todos' => 'Todos os status'] as $k => $r): ?>
      <option value="<?= $k ?>" <?= $f['situacao'] === $k ? 'selected' : '' ?>><?= $r ?></option>
    <?php endforeach; ?>
  </select>
</form>

<div class="card card--tabela">
  <?php if (!$produtos): ?>
    <div class="vazio"><?= icone('part', 32, 1.25) ?>Nenhum item encontrado.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Código</th><th>Nome</th><th>Tipo</th><th>Categoria</th><th class="num">Qtd.</th><?php if ($admin): ?><th class="num">Custo</th><?php endif; ?><th class="num">Preço</th><th>Situação</th><th class="num"><span class="sr-only">Ações</span></th></tr></thead>
        <tbody>
          <?php foreach ($produtos as $pr):
                $peca = $pr['tipo'] === 'PECA';
                $esgotado = $peca && (float) $pr['estoque_atual'] <= 0;
                $baixo = $peca && !$esgotado && (float) $pr['estoque_minimo'] > 0 && (float) $pr['estoque_atual'] <= (float) $pr['estoque_minimo'];
                [$sitRotulo, $sitCor] = match (true) {
                    !$pr['ativo'] => ['Inativo', 'muted'], $esgotado => ['Esgotado', 'danger'], $baixo => ['Estoque baixo', 'warning'],
                    $peca => ['Em estoque', 'neutral'], default => ['Ativo', 'neutral'],
                };
                $link = './produto?id=' . (int) $pr['id']; ?>
            <tr data-href="<?= e($link) ?>" class="<?= $pr['ativo'] ? '' : 'inativo' ?>">
              <td class="codigo"><?= $pr['codigo_interno'] ? e($pr['codigo_interno']) : '—' ?></td>
              <td><a href="<?= e($link) ?>" class="link-discreto"><strong><?= e($pr['nome']) ?></strong></a><?php $sub = trim(($pr['marca'] ?? '') . (($pr['marca'] ?? '') && ($pr['descricao'] ?? '') ? ' · ' : '') . mb_strimwidth((string) ($pr['descricao'] ?? ''), 0, 60, '…')); if ($sub !== ''): ?><span class="sub"><?= e($sub) ?></span><?php endif; ?></td>
              <td><span class="etiqueta<?= $peca ? '' : ' etiqueta--contorno' ?>"><?= $peca ? 'Peça' : 'Serviço' ?></span></td>
              <td><?= e($pr['categoria'] ?: '—') ?></td>
              <td class="num"><strong><?= $peca ? $qtd($pr['estoque_atual']) : '—' ?></strong></td>
              <?php if ($admin): ?><td class="num secundario"><?= $peca ? dinheiro($pr['preco_custo']) : '—' ?></td><?php endif; ?>
              <td class="num"><?= dinheiro($pr['preco_venda']) ?></td>
              <td><span class="badge badge--<?= $sitCor ?>"><?= $sitRotulo ?></span></td>
              <td class="num"><span class="acoes-linha">
                <a class="btn btn--fantasma btn--icone" href="<?= e($link) ?>" aria-label="Abrir <?= e($pr['nome']) ?>" title="Abrir"><?= icone('eye') ?></a>
                <?php if ($admin): ?>
                  <details class="menu-mais"><summary class="btn btn--fantasma btn--icone" aria-label="Mais ações"><?= icone('more-h') ?></summary>
                    <div class="menu-mais__lista">
                      <a href="./produto-form?id=<?= (int) $pr['id'] ?>"><?= icone('edit') ?>Editar</a>
                      <?php if ($peca && $pr['ativo']): ?>
                        <a href="./estoque-entrada?produto=<?= (int) $pr['id'] ?>"><?= icone('download') ?>Entrada de mercadoria</a>
                        <a href="./estoque-ajuste?produto=<?= (int) $pr['id'] ?>"><?= icone('refresh') ?>Ajustar estoque</a>
                      <?php endif; ?>
                    </div>
                  </details>
                <?php endif; ?>
              </span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?php $rotuloItens = 'itens'; require V2_RAIZ . '/app/views/layout/_rodape.php'; ?>
</div>
