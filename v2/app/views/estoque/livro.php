<?php
/**
 * Estoque (Design System, tela 20): movimento do mês, o livro de movimentações e as peças no mínimo.
 * Espera: $movs, $f, $p, $resumo, $mes, $minimo
 */
$titulo = 'Estoque';
$qtd = fn($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
$numero = fn (int $n): string => number_format($n, 0, ',', '.');
$iconeMotivo = ['COMPRA' => 'download', 'SAIDA_OS' => 'upload', 'DEVOLUCAO_OS' => 'download', 'AJUSTE' => 'settings', 'ESTORNO' => 'refresh'];
$ocultos = array_filter(['motivo' => $f['motivo'], 'produto' => $f['produto']]);
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Estoque</h1>
    <p class="page-header__subtitle">Entradas, saídas e ajustes das peças · <?= dinheiro($resumo['valor_custo']) ?> em estoque pelo custo.</p>
  </div>
  <div class="page-header__actions">
    <a class="btn btn--contorno" href="./estoque-ajuste"><?= icone('checklist') ?> Ajuste por contagem</a>
    <a class="btn btn--primary" href="./estoque-entrada"><?= icone('plus') ?> Entrada de mercadoria</a>
  </div>
</div>

<div class="stat-grid stat-grid--icone">
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Entradas no mês</span><?= icone('download') ?></div>
    <span class="stat-card__value"><?= $qtd($mes['entradas']) ?> un.</span>
    <span class="stat-card__pe"><?= $numero($mes['notas']) ?> nota<?= $mes['notas'] === 1 ? '' : 's' ?> de compra</span>
  </div>
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Saídas no mês</span><?= icone('upload') ?></div>
    <span class="stat-card__value"><?= $qtd($mes['saidas']) ?> un.</span>
    <span class="stat-card__pe">aplicadas em <?= $numero($mes['os']) ?> OS</span>
  </div>
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Ajustes</span><?= icone('settings') ?></div>
    <span class="stat-card__value"><?= $numero($mes['ajustes']) ?></span>
    <span class="stat-card__pe">correções de inventário no mês</span>
  </div>
  <a class="stat-card" href="./produtos?situacao=baixo">
    <div class="stat-card__topo"><span class="stat-card__label">Abaixo do mínimo</span><?= icone('warning') ?></div>
    <span class="stat-card__value"><?= $numero((int) $resumo['abaixo_minimo']) ?></span>
    <span class="stat-card__pe">precisam de reposição</span>
  </a>
</div>

<?php if ($minimo): $nMinimo = (int) $resumo['abaixo_minimo']; ?>
  <!-- Peças abaixo do mínimo: uma faixa de cartões logo abaixo dos números (o livro fica com a largura toda). -->
  <section class="cu-faixa" aria-labelledby="es-h-minimo">
    <div class="cu-faixa__topo">
      <h2 id="es-h-minimo">Abaixo do mínimo</h2>
      <span class="cu-faixa__info"><?= $numero($nMinimo) ?> ite<?= $nMinimo === 1 ? 'm' : 'ns' ?> para repor</span>
      <span class="cu-faixa__acoes">
        <?php if ($nMinimo > count($minimo)): ?><a class="cu-faixa__link" href="./produtos?situacao=baixo">Ver todas</a><?php endif; ?>
        <a class="cu-faixa__link" href="./estoque-entrada?produto=<?= (int) $minimo[0]['id'] ?>"><?= icone('download', 16) ?>Registrar entrada</a>
      </span>
    </div>
    <div class="cu-faixa__lista">
      <?php foreach ($minimo as $pc): $atual = max(0, (float) $pc['estoque_atual']); $min = (float) $pc['estoque_minimo']; $cheios = $min > 0 ? (int) round(min(1, $atual / $min) * 5) : 0; ?>
        <a class="card cu-nivel" href="./produto?id=<?= (int) $pc['id'] ?>">
          <strong class="cu-nivel__nome"><?= e($pc['nome']) ?></strong>
          <span class="cu-nivel__qtd"><b<?= $atual <= 0 ? ' class="is-zerado"' : '' ?>><?= $qtd($atual) ?></b><small>de <?= $qtd($min) ?> no mínimo</small></span>
          <span class="niveis__barra cu-nivel__barra" aria-hidden="true"><?php for ($i = 0; $i < 5; $i++): ?><span<?= $i < $cheios ? ' class="cheio"' : '' ?>></span><?php endfor; ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<div class="card--espaco">
  <div>
    <form class="filtros filtros--solto" method="get" action="./estoque">
      <?php foreach ($ocultos as $k => $v): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endforeach; ?>
      <div class="filtros__busca filtros__busca--largo"><?= icone('search') ?><input type="search" name="busca" value="<?= e($f['busca']) ?>" placeholder="Buscar peça, código ou nº da nota" aria-label="Buscar"></div>
      <nav class="abas abas--compactas" aria-label="Tipo de movimento">
        <?php foreach (['' => 'Todos', 'COMPRA' => 'Entrada', 'SAIDA_OS' => 'Saída', 'AJUSTE' => 'Ajuste', 'ESTORNO' => 'Estorno'] as $k => $r): ?>
          <a href="<?= e(url_com(['motivo' => $k ?: null, 'pagina' => null])) ?>"<?= $f['motivo'] === $k ? ' class="ativa" aria-current="page"' : '' ?>><?= $r ?></a>
        <?php endforeach; ?>
      </nav>
      <label class="campo-periodo campo-periodo--compacto"><?= icone('calendar') ?>
        <input type="date" name="de" value="<?= e($f['de']) ?>" aria-label="De" onchange="this.form.submit()"><span>–</span>
        <input type="date" name="ate" value="<?= e($f['ate']) ?>" aria-label="Até" onchange="this.form.submit()">
      </label>
      <?php if ($f['produto']): ?><a class="filtro-ativo" href="<?= e(url_com(['produto' => null, 'pagina' => null])) ?>">Só uma peça <?= icone('close', 12, 2) ?></a><?php endif; ?>
    </form>

    <div class="card card--tabela">
      <?php if (!$movs): ?>
        <div class="vazio"><?= icone('transfer', 32, 1.25) ?>Nenhuma movimentação encontrada.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Produto</th><th>Tipo</th><th class="num">Quantidade</th><th class="num">Custo unit.</th><th>Registrado em</th><th class="num"><span class="sr-only">Ações</span></th></tr></thead>
            <tbody>
              <?php foreach ($movs as $m): [$r] = Estoque::MOTIVOS[$m['motivo']] ?? [$m['motivo']]; $positivo = (float) $m['delta'] > 0;
                    $origem = implode(' · ', array_filter([$m['fornecedor'], $m['documento'] ? (stripos($m['documento'], 'NF') === 0 ? $m['documento'] : 'NF ' . $m['documento']) : null])); ?>
                <tr class="<?= $m['estornado'] ? 'inativo' : '' ?>">
                  <td><span class="celula-pessoa"><span class="icone-caixa"><?= icone($iconeMotivo[$m['motivo']] ?? 'transfer') ?></span>
                    <span><a href="./produto?id=<?= (int) $m['produto_id'] ?>" class="link-discreto"><strong><?= e($m['produto']) ?></strong></a>
                      <span class="sub"><?php if ($m['os_id']): ?><a href="./os?id=<?= (int) $m['os_id'] ?>"><?= os_numero($m['os_numero']) ?></a><?php else: ?><?= e($origem ?: ($m['observacao'] ?: '—')) ?><?php endif; ?></span></span></span></td>
                  <td><span class="badge badge--plain badge--sinal<?= $m['motivo'] === 'AJUSTE' || $m['motivo'] === 'ESTORNO' ? ' badge--tracejado' : '' ?>"><?= e($r) ?></span><?= $m['estornado'] ? '<span class="sub">estornado</span>' : '' ?></td>
                  <td class="num"><strong><?= $positivo ? '+ ' : '− ' ?><?= $qtd(abs((float) $m['delta'])) ?></strong></td>
                  <td class="num"><?= $m['custo_unitario'] !== null ? dinheiro($m['custo_unitario']) : '—' ?></td>
                  <td class="tabular"><?= date('d/m H:i', strtotime($m['criado_em'])) ?><span class="sub"><?= e($m['usuario'] ?? 'sistema') ?></span></td>
                  <td class="num">
                    <?php if (in_array($m['motivo'], ['COMPRA', 'AJUSTE'], true) && !$m['estornado']): ?>
                      <details class="menu-mais"><summary class="btn btn--fantasma btn--icone" aria-label="Mais ações"><?= icone('more-h') ?></summary>
                        <div class="menu-mais__lista">
                          <button type="button" data-estornar="<?= (int) $m['id'] ?>" data-rotulo="<?= e($r . ' de ' . $qtd($m['delta']) . ' × ' . $m['produto']) ?>"><?= icone('refresh') ?>Estornar</button>
                          <a href="./estoque?produto=<?= (int) $m['produto_id'] ?>"><?= icone('history') ?>Movimentos desta peça</a>
                        </div>
                      </details>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
      <?php $rotuloItens = 'movimentações'; require V2_RAIZ . '/app/views/layout/_rodape.php'; ?>
    </div>
  </div>
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
      b.closest('details') && b.closest('details').removeAttribute('open');
      d.showModal();
    });
  });
</script>
