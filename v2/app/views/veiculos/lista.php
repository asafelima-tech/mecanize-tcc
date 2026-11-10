<?php /** Espera: $veiculos, $f, $p */ $titulo = 'Veículos'; ?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Veículos</h1>
    <p class="page-header__subtitle">Veículos dos clientes e o histórico de cada um.</p>
  </div>
  <div class="page-header__actions"><a class="btn btn--primary" href="./veiculo-form"><i class="bi bi-plus-lg"></i> Novo veículo</a></div>
</div>

<div class="card card--tabela">
  <form class="filtros" method="get" action="./veiculos">
    <div class="filtros__busca"><i class="bi bi-search"></i><input type="search" name="busca" value="<?= e($f['busca']) ?>" placeholder="Placa, modelo ou cliente" aria-label="Buscar"></div>
    <?php require V2_RAIZ . '/app/views/layout/_status.php'; ?>
  </form>

  <?php if (!$veiculos): ?>
    <div class="vazio"><i class="bi bi-car-front"></i><?= $f['busca'] !== '' ? 'Nenhum veículo encontrado para essa busca.' : 'Nenhum veículo cadastrado ainda.' ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Placa</th><th>Veículo</th><th>Cliente</th><th>Cor</th><th>Combustível</th></tr></thead>
        <tbody>
          <?php foreach ($veiculos as $v): ?>
            <tr data-href="./veiculo?id=<?= (int) $v['id'] ?>" class="<?= $v['ativo'] ? '' : 'inativo' ?>">
              <td><a href="./veiculo?id=<?= (int) $v['id'] ?>"><?= e($v['placa']) ?></a></td>
              <td><?= e($v['modelo']) ?><span class="sub"><?= e($v['ano'] ?? '') ?></span></td>
              <td><?= e($v['cliente']) ?></td>
              <td><?= e($v['cor'] ?? '—') ?></td>
              <td><?= e($v['combustivel'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?php require V2_RAIZ . '/app/views/layout/_paginacao.php'; ?>
</div>
