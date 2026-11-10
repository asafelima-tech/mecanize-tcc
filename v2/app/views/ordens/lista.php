<?php /** Espera: $ordens, $f, $p, $tecnicos */ $titulo = 'Ordens de serviço'; ?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Ordens de serviço</h1>
    <p class="page-header__subtitle">Orçamentos e serviços, do primeiro contato até a entrega.</p>
  </div>
  <div class="page-header__actions"><a class="btn btn--primary" href="./os-nova"><i class="bi bi-plus-lg"></i> Novo orçamento</a></div>
</div>

<div class="card card--tabela">
  <form class="filtros" method="get" action="./ordens">
    <div class="filtros__busca"><i class="bi bi-search"></i><input type="search" name="busca" value="<?= e($f['busca']) ?>" placeholder="Nº da OS, placa ou cliente" aria-label="Buscar"></div>
    <select name="situacao" aria-label="Situação">
      <?php foreach (OrdemServico::FILTROS as $k => [$rotulo]): ?><option value="<?= $k ?>" <?= $f['situacao'] === $k ? 'selected' : '' ?>><?= e($rotulo) ?></option><?php endforeach; ?>
    </select>
    <select name="tecnico" aria-label="Técnico">
      <option value="">Todos os técnicos</option>
      <?php foreach ($tecnicos as $t): ?><option value="<?= (int) $t['id'] ?>" <?= $f['tecnico'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?></option><?php endforeach; ?>
    </select>
  </form>

  <?php if (!$ordens): ?>
    <div class="vazio"><i class="bi bi-clipboard"></i>Nenhuma ordem de serviço nesta situação.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>OS</th><th>Cliente</th><th>Veículo</th><th>Técnico</th><th>Situação</th><th>Abertura</th><th class="num">Total</th></tr></thead>
        <tbody>
          <?php foreach ($ordens as $o): [$r, $cor] = os_status($o['status']); ?>
            <tr data-href="./os?id=<?= (int) $o['id'] ?>">
              <td><a href="./os?id=<?= (int) $o['id'] ?>"><?= os_numero($o['numero']) ?></a></td>
              <td><?= e($o['cliente']) ?></td>
              <td><?= e($o['placa']) ?></td>
              <td><?= e($o['tecnico'] ?? '—') ?></td>
              <td><span class="badge badge--<?= $cor ?>"><?= $r ?></span>
                <?php if ($o['previsao_conclusao'] && !in_array($o['status'], ['PRONTA', 'ENTREGUE', 'RECUSADA', 'CANCELADA'], true) && strtotime($o['previsao_conclusao']) < time()): ?>
                  <span class="sub" style="color:var(--color-danger)">atrasada</span>
                <?php endif; ?></td>
              <td><?= data_br($o['criado_em']) ?></td>
              <td class="num"><?= dinheiro($o['valor_total']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?php require V2_RAIZ . '/app/views/layout/_paginacao.php'; ?>
</div>
