<?php /** Espera: $v, $historico */ $titulo = $v['placa']; ?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= e($v['placa']) ?> <?php if (!$v['ativo']): ?><span class="badge badge--muted">Inativo</span><?php endif; ?></h1>
    <p class="page-header__subtitle"><a href="./veiculos">Veículos</a> · <?= e(trim($v['modelo'] . ' ' . ($v['ano'] ?? ''))) ?></p>
  </div>
  <div class="page-header__actions acoes">
    <?php $rotaAtivo = 'veiculo-ativo'; $rotaExcluir = 'veiculo-excluir'; $registro = $v; $nome = 'este veículo'; require V2_RAIZ . '/app/views/layout/_ativo.php'; ?>
    <a class="btn btn--primary btn--sm" href="./veiculo-form?id=<?= (int) $v['id'] ?>"><?= icone('edit') ?> Editar</a>
  </div>
</div>

<div class="ficha">
  <div class="card">
    <h2 class="card__titulo">Histórico do veículo</h2>
    <?php if (!$historico): ?>
      <div class="vazio">Nenhum serviço registrado para este veículo.</div>
    <?php endif; ?>
    <?php foreach ($historico as $os): [$r, $cor] = os_status($os['status']); ?>
      <div class="linha-os">
        <div class="linha-os__topo">
          <span><strong><?= os_numero($os['numero']) ?></strong> · <?= data_br($os['criado_em']) ?><?= $os['km_veiculo'] ? ' · ' . number_format((int) $os['km_veiculo'], 0, ',', '.') . ' km' : '' ?></span>
          <span><span class="badge badge--<?= $cor ?>"><?= $r ?></span> <?= dinheiro($os['valor_total']) ?></span>
        </div>
        <?php if ($os['defeito_relatado']): ?><div class="form-dica"><?= e($os['defeito_relatado']) ?></div><?php endif; ?>
        <?php if ($os['itens']): ?>
          <ul><?php foreach ($os['itens'] as $i): ?><li><?= e($i['descricao']) ?><?= (float) $i['quantidade'] != 1 ? ' × ' . e(rtrim(rtrim($i['quantidade'], '0'), '.')) : '' ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <dl class="dados" style="grid-template-columns:1fr">
      <div><dt>Cliente</dt><dd><a href="./cliente?id=<?= (int) $v['cliente_id'] ?>"><?= e($v['cliente']) ?></a></dd></div>
      <div><dt>Modelo</dt><dd><?= e($v['modelo']) ?></dd></div>
      <div><dt>Ano</dt><dd><?= e($v['ano'] ?? '—') ?></dd></div>
      <div><dt>Cor</dt><dd><?= e($v['cor'] ?? '—') ?></dd></div>
      <div><dt>Combustível</dt><dd><?= e($v['combustivel'] ?? '—') ?></dd></div>
      <?php if ($v['observacoes']): ?><div><dt>Observações</dt><dd style="white-space:pre-line"><?= e($v['observacoes']) ?></dd></div><?php endif; ?>
    </dl>
  </div>
</div>
