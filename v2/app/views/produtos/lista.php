<?php
/** Espera: $produtos, $f, $p, $categorias */
$titulo = 'Peças e serviços';
$admin = Contexto::perfil() === 'ADMIN';
$qtd = fn($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Peças e serviços</h1>
    <p class="page-header__subtitle">Catálogo usado nos orçamentos e ordens de serviço.</p>
  </div>
  <?php if ($admin): ?>
    <div class="page-header__actions acoes">
      <a class="btn btn--ghost" href="./produto-form?tipo=SERVICO"><i class="bi bi-plus-lg"></i> Serviço</a>
      <a class="btn btn--primary" href="./produto-form"><i class="bi bi-plus-lg"></i> Peça</a>
    </div>
  <?php endif; ?>
</div>

<div class="card card--tabela">
  <form class="filtros" method="get" action="./produtos">
    <div class="filtros__busca"><i class="bi bi-search"></i><input type="search" name="busca" value="<?= e($f['busca']) ?>" placeholder="Nome, código ou marca" aria-label="Buscar"></div>
    <select name="tipo" aria-label="Tipo">
      <option value="">Peças e serviços</option>
      <option value="PECA" <?= $f['tipo'] === 'PECA' ? 'selected' : '' ?>>Só peças</option>
      <option value="SERVICO" <?= $f['tipo'] === 'SERVICO' ? 'selected' : '' ?>>Só serviços</option>
    </select>
    <?php if ($categorias): ?>
      <select name="categoria" aria-label="Categoria">
        <option value="">Todas as categorias</option>
        <?php foreach ($categorias as $c): ?><option <?= $f['categoria'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
      </select>
    <?php endif; ?>
    <select name="baixo" aria-label="Estoque">
      <option value="">Qualquer estoque</option>
      <option value="1" <?= $f['baixo'] ? 'selected' : '' ?>>Estoque baixo</option>
    </select>
    <?php require V2_RAIZ . '/app/views/layout/_status.php'; ?>
  </form>

  <?php if (!$produtos): ?>
    <div class="vazio"><i class="bi bi-box-seam"></i>Nenhum item encontrado.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Item</th><th>Categoria</th><th class="num">Estoque</th><?php if ($admin): ?><th class="num">Custo</th><?php endif; ?><th class="num">Preço</th></tr></thead>
        <tbody>
          <?php foreach ($produtos as $pr): $peca = $pr['tipo'] === 'PECA'; $baixo = $peca && (float) $pr['estoque_minimo'] > 0 && (float) $pr['estoque_atual'] <= (float) $pr['estoque_minimo']; ?>
            <tr data-href="./produto?id=<?= (int) $pr['id'] ?>" class="<?= $pr['ativo'] ? '' : 'inativo' ?>">
              <td><a href="./produto?id=<?= (int) $pr['id'] ?>"><?= e($pr['nome']) ?></a>
                <span class="sub"><?= $peca ? 'Peça' : 'Serviço' ?><?= $pr['codigo_interno'] ? ' · ' . e($pr['codigo_interno']) : '' ?><?= $pr['marca'] ? ' · ' . e($pr['marca']) : '' ?></span></td>
              <td><?= e($pr['categoria'] ?? '—') ?></td>
              <td class="num"><?php if ($peca): ?><?= $qtd($pr['estoque_atual']) ?><?php if ($baixo): ?> <span class="badge badge--warning">baixo</span><?php endif; ?><?php else: ?>—<?php endif; ?></td>
              <?php if ($admin): ?><td class="num"><?= $peca ? dinheiro($pr['preco_custo']) : '—' ?></td><?php endif; ?>
              <td class="num"><?= dinheiro($pr['preco_venda']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?php require V2_RAIZ . '/app/views/layout/_paginacao.php'; ?>
</div>
