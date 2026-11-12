<?php
/**
 * Veículos (Design System, tela 18): números da frota, busca, marca, combustível e situação, e a
 * lista com placa, modelo, cor, ano, combustível e proprietário.
 * Espera: $veiculos, $f, $p, $resumo, $marcas
 */
$titulo = 'Veículos';
$numero = fn (int $n): string => number_format($n, 0, ',', '.');
$variacao = $resumo['novos_anterior'] > 0 ? ($resumo['novos_mes'] - $resumo['novos_anterior']) / $resumo['novos_anterior'] * 100 : null;
// Cor do veículo é dado (não é situação), então o ponto mostra a cor de verdade.
$cores = ['Branco' => '#FFFFFF', 'Branco Pérola' => '#F4F1EA', 'Preto' => '#1D1D1F', 'Prata' => '#C7C7CC', 'Cinza' => '#8E8E93', 'Grafite' => '#48484A',
          'Chumbo' => '#545458', 'Vermelho' => '#C62828', 'Vinho' => '#6D1F2B', 'Azul' => '#1E5AA8', 'Azul Marinho' => '#1B2A4A', 'Verde' => '#2E7D32',
          'Verde Escuro' => '#1B4D2B', 'Amarelo' => '#F2C230', 'Laranja' => '#E8772E', 'Marrom' => '#6B4226', 'Bege' => '#D8C7A6', 'Dourado' => '#C9A43A',
          'Bronze' => '#9C6B3C', 'Rosa' => '#E58FB0', 'Roxo' => '#6A3FA0'];
$filtrado = $f['busca'] !== '' || $f['marca'] !== '' || $f['combustivel'] !== '' || $f['novos'] !== '';
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Gestão de veículos</h1>
    <p class="page-header__subtitle">Frota atendida, com placa, cor, combustível e proprietário.</p>
  </div>
  <div class="page-header__actions"><a class="btn btn--primary" href="./veiculo-form"><?= icone('plus') ?> Novo veículo</a></div>
</div>

<div class="stat-grid stat-grid--3 stat-grid--icone">
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Total de veículos</span><?= icone('car') ?></div>
    <span class="stat-card__value"><?= $numero($resumo['ativos']) ?></span>
    <span class="stat-card__pe">cadastrados<?= $resumo['inativos'] ? ' · ' . $numero($resumo['inativos']) . ' inativo(s)' : '' ?></span>
  </div>
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Novos este mês</span><?php if ($variacao !== null): ?><span class="stat-card__chip" title="Comparado ao mesmo período do mês anterior"><?= $variacao >= 0 ? '+' : '−' ?><?= number_format(abs($variacao), 0) ?>%</span><?php endif; ?></div>
    <span class="stat-card__value"><?= $numero($resumo['novos_mes']) ?></span>
    <a class="stat-card__pe" href="./veiculos?novos=mes&amp;status=todos">ver lista do mês →</a>
  </div>
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Marca mais recorrente</span><?= icone('chart') ?></div>
    <span class="stat-card__value"><?= $resumo['marca'] ? e($resumo['marca']) : '—' ?></span>
    <span class="stat-card__pe"><?= $resumo['marca'] && $resumo['ativos'] ? number_format($resumo['marca_n'] / $resumo['ativos'] * 100, 0) . '% da frota atendida' : 'escolha o modelo do catálogo no cadastro' ?></span>
  </div>
</div>

<form class="filtros filtros--solto card--espaco" method="get" action="./veiculos">
  <?php if ($f['novos'] !== ''): ?><input type="hidden" name="novos" value="<?= e($f['novos']) ?>"><?php endif; ?>
  <div class="filtros__busca filtros__busca--largo"><?= icone('search') ?><input type="search" name="busca" value="<?= e($f['busca']) ?>" placeholder="Buscar por placa, modelo ou proprietário" aria-label="Buscar"></div>
  <select name="marca" aria-label="Marca">
    <option value="">Todas as marcas</option>
    <?php foreach ($marcas as $mc): ?><option <?= $f['marca'] === $mc ? 'selected' : '' ?>><?= e($mc) ?></option><?php endforeach; ?>
  </select>
  <select name="combustivel" aria-label="Combustível">
    <option value="">Todos os combustíveis</option>
    <?php foreach (Veiculo::COMBUSTIVEIS as $cb): ?><option <?= $f['combustivel'] === $cb ? 'selected' : '' ?>><?= e($cb) ?></option><?php endforeach; ?>
  </select>
  <?php require V2_RAIZ . '/app/views/layout/_status.php'; ?>
  <?php if ($f['novos'] === 'mes'): ?><a class="filtro-ativo" href="<?= e(url_com(['novos' => null, 'status' => null, 'pagina' => null])) ?>">Novos este mês <?= icone('close', 12, 2) ?></a><?php endif; ?>
  <span class="filtros__fim"><?= $numero((int) $p->total) ?> veículo<?= (int) $p->total === 1 ? '' : 's' ?></span>
</form>

<div class="card card--tabela">
  <?php if (!$veiculos): ?>
    <div class="vazio"><?= icone('car', 32, 1.25) ?><?= $filtrado ? 'Nenhum veículo encontrado com esses filtros.' : 'Nenhum veículo cadastrado ainda.' ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Placa</th><th>Veículo</th><th>Cor</th><th>Ano</th><th>Combustível</th><th>Proprietário</th><th class="num">Ações</th></tr></thead>
        <tbody>
          <?php foreach ($veiculos as $v): $link = './veiculo?id=' . (int) $v['id']; ?>
            <tr data-href="<?= e($link) ?>" class="<?= $v['ativo'] ? '' : 'inativo' ?>">
              <td><a class="placa-caixa placa-caixa--p" href="<?= e($link) ?>"><?= e($v['placa']) ?></a></td>
              <td><strong><?= e($v['modelo_nome'] ?: 'Veículo') ?></strong><?php if ($v['marca']): ?><span class="sub"><?= e($v['marca']) ?></span><?php endif; ?></td>
              <td><?php if ($v['cor']): ?><span class="cor-veiculo"><span class="cor-veiculo__ponto" style="background:<?= e($cores[$v['cor']] ?? '#A1A1A6') ?>"></span><?= e($v['cor']) ?></span><?php else: ?><span class="secundario">—</span><?php endif; ?></td>
              <td class="tabular"><?= e($v['ano'] ?? '—') ?></td>
              <td><?= e($v['combustivel'] ?? '—') ?></td>
              <td><a href="./cliente?id=<?= (int) $v['cliente_id'] ?>" class="link-discreto"><?= e($v['cliente']) ?></a></td>
              <td class="num"><span class="acoes-linha">
                <a class="btn btn--fantasma btn--icone" href="<?= e($link) ?>" aria-label="Abrir <?= e($v['placa']) ?>" title="Abrir"><?= icone('eye') ?></a>
                <a class="btn btn--fantasma btn--icone" href="./veiculo-form?id=<?= (int) $v['id'] ?>" aria-label="Editar <?= e($v['placa']) ?>" title="Editar"><?= icone('edit') ?></a>
                <?php if ($v['ativo']): ?>
                  <details class="menu-mais"><summary class="btn btn--fantasma btn--icone" aria-label="Mais ações"><?= icone('more-h') ?></summary>
                    <div class="menu-mais__lista">
                      <a href="./os-nova?cliente=<?= (int) $v['cliente_id'] ?>&amp;veiculo=<?= (int) $v['id'] ?>"><?= icone('work-order') ?>Novo orçamento</a>
                      <a href="./agenda-form?cliente=<?= (int) $v['cliente_id'] ?>"><?= icone('calendar') ?>Agendar</a>
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
  <?php $rotuloItens = 'veículos'; require V2_RAIZ . '/app/views/layout/_rodape.php'; ?>
</div>
