<?php
/**
 * Ordens de serviço (tela 14 do Design System): abas por situação com contagem, filtros e tabela;
 * ou o Kanban das OS em aberto (?modo=kanban).
 * Espera: $f, $p, $tecnicos, $contagens, $ordens (lista) e $colunas (Kanban, senão null).
 */
$titulo = 'Ordens de serviço';
$kanban = $f['modo'] === 'kanban';
$rotulos = [
    'abertas' => 'ordens em aberto', 'orcamento' => 'orçamentos', 'execucao' => 'ordens em andamento', 'prontas' => 'ordens prontas',
    'entregues' => 'ordens entregues', 'encerradas' => 'ordens recusadas ou canceladas', 'todas' => 'ordens',
];
$totalTela = $kanban ? array_sum(array_map('count', $colunas)) : (int) $p->total;
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Ordens de serviço</h1>
    <p class="page-header__subtitle">Acompanhe as OS da entrada à entrega.</p>
  </div>
  <div class="page-header__actions">
    <?php if ($kanban): ?>
      <a class="btn btn--contorno" href="<?= e(url_com(['modo' => null, 'pagina' => null])) ?>"><?= icone('menu') ?> Ver como lista</a>
    <?php else: ?>
      <a class="btn btn--contorno" href="<?= e(url_com(['modo' => 'kanban', 'situacao' => null, 'pagina' => null])) ?>"><?= icone('kanban') ?> Ver como Kanban</a>
    <?php endif; ?>
    <a class="btn btn--primary" href="./os-nova"><?= icone('plus') ?> Nova ordem de serviço</a>
  </div>
</div>

<?php if (!$kanban): ?>
  <nav class="abas abas--contagem" aria-label="Situação das ordens">
    <?php foreach (OrdemServico::FILTROS as $k => [$rotulo]): $ativa = $f['situacao'] === $k; ?>
      <a href="<?= e(url_com(['situacao' => $k === 'abertas' ? null : $k, 'pagina' => null])) ?>"<?= $ativa ? ' class="ativa" aria-current="page"' : '' ?>>
        <?= e($rotulo) ?><span class="abas__n"><?= (int) $contagens[$k] ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<form class="filtros filtros--solto" method="get" action="./ordens">
  <?php if ($kanban): ?><input type="hidden" name="modo" value="kanban"><?php endif; ?>
  <?php if (!$kanban && $f['situacao'] !== 'abertas'): ?><input type="hidden" name="situacao" value="<?= e($f['situacao']) ?>"><?php endif; ?>
  <div class="filtros__busca"><?= icone('search') ?><input type="search" name="busca" value="<?= e($f['busca']) ?>" placeholder="Buscar por OS, cliente ou placa" aria-label="Buscar por OS, cliente ou placa"></div>
  <select name="tecnico" aria-label="Técnico">
    <option value="">Todos os técnicos</option>
    <?php foreach ($tecnicos as $t): ?><option value="<?= (int) $t['id'] ?>" <?= $f['tecnico'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?></option><?php endforeach; ?>
  </select>
  <span class="filtros__fim"><?= $totalTela ?> <?= $totalTela === 1 ? 'ordem' : 'ordens' ?></span>
</form>

<?php if ($kanban): ?>
  <?php require __DIR__ . '/_kanban.php'; ?>
<?php else: ?>
  <div class="card card--tabela">
    <?php if (!$ordens): ?>
      <div class="vazio"><?= icone('work-order', 32, 1.25) ?>Nenhuma ordem de serviço nesta situação.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>OS</th><th>Cliente</th><th>Veículo</th><th>Situação</th><th>Entrada</th><th>Técnico</th><th class="num">Valor</th><th class="num">Ações</th></tr></thead>
          <tbody>
            <?php foreach ($ordens as $o): [$r, $cor] = os_status($o['status']); $link = './os?id=' . (int) $o['id']; ?>
              <tr data-href="<?= e($link) ?>">
                <td><a href="<?= e($link) ?>"><?= os_numero($o['numero']) ?></a></td>
                <td><?= e($o['cliente']) ?><?php if ($o['cliente_telefone']): ?><span class="sub"><?= e(telefone_fmt($o['cliente_telefone'])) ?></span><?php endif; ?></td>
                <td><?= e($o['modelo'] ?: 'Veículo') ?><span class="sub placa"><?= e($o['placa']) ?></span></td>
                <td><span class="badge badge--<?= $cor ?>"><?= $r ?></span>
                  <?php if (OrdemServico::atrasada($o)): ?><span class="sub"><strong>Atrasada</strong></span><?php endif; ?></td>
                <td class="secundario tabular"><?= data_br($o['criado_em']) ?></td>
                <td><?= e($o['tecnico'] ?? '—') ?></td>
                <td class="num"><strong><?= dinheiro($o['valor_total']) ?></strong></td>
                <td class="num">
                  <span class="acoes-linha">
                    <a class="btn btn--fantasma btn--icone" href="<?= e($link) ?>" aria-label="Abrir <?= os_numero($o['numero']) ?>" title="Abrir"><?= icone('eye') ?></a>
                    <a class="btn btn--fantasma btn--icone" href="./os-imprimir?id=<?= (int) $o['id'] ?>" target="_blank" rel="noopener" aria-label="Imprimir <?= os_numero($o['numero']) ?>" title="Imprimir"><?= icone('print') ?></a>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
    <?php $rotuloItens = $rotulos[$f['situacao']] ?? 'ordens'; require V2_RAIZ . '/app/views/layout/_rodape.php'; ?>
  </div>
<?php endif; ?>
