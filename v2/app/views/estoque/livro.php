<?php
/** Espera: $movs, $f, $p, $resumo */
$titulo = 'Estoque';
$qtd = fn($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Estoque</h1>
    <p class="page-header__subtitle"><?= (int) $resumo['pecas'] ?> peça(s) ativas · <?= dinheiro($resumo['valor_custo']) ?> em estoque (pelo custo)
      <?php if ((int) $resumo['abaixo_minimo'] > 0): ?> · <a href="./produtos?tipo=PECA&baixo=1" style="color:var(--color-warning-strong, #B45309)"><?= (int) $resumo['abaixo_minimo'] ?> abaixo do mínimo</a><?php endif; ?></p>
  </div>
  <div class="page-header__actions acoes">
    <a class="btn btn--ghost" href="./estoque-ajuste"><i class="bi bi-sliders"></i> Ajuste</a>
    <a class="btn btn--primary" href="./estoque-entrada"><i class="bi bi-box-arrow-in-down"></i> Entrada de mercadoria</a>
  </div>
</div>

<div class="card card--tabela">
  <form class="filtros" method="get" action="./estoque">
    <div class="filtros__busca"><i class="bi bi-search"></i><input type="search" name="busca" value="<?= e($f['busca']) ?>" placeholder="Peça, código ou nº da nota" aria-label="Buscar"></div>
    <select name="motivo" aria-label="Tipo de movimento">
      <option value="">Todos os movimentos</option>
      <?php foreach (Estoque::MOTIVOS as $k => [$r]): ?><option value="<?= $k ?>" <?= $f['motivo'] === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?>
    </select>
    <input type="date" name="de" value="<?= e($f['de']) ?>" aria-label="De" class="filtros__data" onchange="this.form.submit()">
    <input type="date" name="ate" value="<?= e($f['ate']) ?>" aria-label="Até" class="filtros__data" onchange="this.form.submit()">
    <?php if ($f['produto']): ?><input type="hidden" name="produto" value="<?= (int) $f['produto'] ?>"><a href="./estoque" class="form-dica">Ver todas as peças</a><?php endif; ?>
  </form>

  <?php if (!$movs): ?>
    <div class="vazio"><i class="bi bi-arrow-left-right"></i>Nenhuma movimentação encontrada.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Data</th><th>Peça</th><th>Movimento</th><th class="num">Qtd</th><th class="num">Custo un.</th><th>Origem</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($movs as $m): [$r, $cor] = Estoque::MOTIVOS[$m['motivo']] ?? [$m['motivo'], 'neutral']; ?>
            <tr class="<?= $m['estornado'] ? 'inativo' : '' ?>">
              <td><?= data_br($m['criado_em'], true) ?><span class="sub"><?= e($m['usuario'] ?? 'sistema') ?></span></td>
              <td><a href="./produto?id=<?= (int) $m['produto_id'] ?>"><?= e($m['produto']) ?></a></td>
              <td><span class="badge badge--<?= $cor ?>"><?= $r ?></span><?= $m['estornado'] ? '<span class="sub">estornado</span>' : '' ?></td>
              <td class="num"><strong><?= (float) $m['delta'] > 0 ? '+' : '' ?><?= $qtd($m['delta']) ?></strong></td>
              <td class="num"><?= $m['custo_unitario'] !== null ? dinheiro($m['custo_unitario']) : '—' ?></td>
              <td>
                <?php if ($m['os_id']): ?><a href="./os?id=<?= (int) $m['os_id'] ?>"><?= os_numero($m['os_numero']) ?></a><?php endif; ?>
                <?= e(implode(' · ', array_filter([$m['fornecedor'], $m['documento'] ? 'NF ' . $m['documento'] : null]))) ?>
                <?php if ($m['observacao'] && !$m['os_id']): ?><span class="sub"><?= e($m['observacao']) ?></span><?php endif; ?>
              </td>
              <td class="num">
                <?php if (in_array($m['motivo'], ['COMPRA', 'AJUSTE'], true) && !$m['estornado']): ?>
                  <button type="button" class="btn btn--ghost btn--sm" data-estornar="<?= (int) $m['id'] ?>" data-rotulo="<?= e($r . ' de ' . $qtd($m['delta']) . ' × ' . $m['produto']) ?>">Estornar</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?php require V2_RAIZ . '/app/views/layout/_paginacao.php'; ?>
</div>

<dialog id="dlg-estornar" class="dlg">
  <form method="post" action="./estoque-estornar">
    <?= csrf_field() ?>
    <h3>Estornar movimento</h3>
    <p class="form-dica" id="estorno-rotulo"></p>
    <p class="form-dica">O movimento original continua no livro; o estorno lança o contrário. Se a entrada gerou conta a pagar ainda não paga, a conta é reduzida ou cancelada.</p>
    <div class="form-field"><label>Motivo</label><textarea name="motivo" rows="2" required></textarea></div>
    <div class="form-acoes"><button type="button" class="btn btn--ghost" onclick="this.closest('dialog').close()">Voltar</button><button class="btn btn--danger">Estornar</button></div>
  </form>
</dialog>
<script>
  document.querySelectorAll('[data-estornar]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = document.getElementById('dlg-estornar');
      d.querySelector('form').action = './estoque-estornar?id=' + b.dataset.estornar;
      document.getElementById('estorno-rotulo').textContent = b.dataset.rotulo;
      d.showModal();
    });
  });
</script>
