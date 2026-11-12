<?php
/**
 * Ficha de consulta do veículo, no mesmo formato da ficha do cliente: identificação no topo (placa, ano,
 * cor, combustível), os números do histórico (OS, total das entregues, última visita e último km),
 * o histórico de OS e, ao lado, o proprietário e as observações.
 * Ações: editar e novo orçamento; desativar e excluir no menu (as mesmas rotas de antes).
 * Espera: $v, $historico
 */
$titulo = $v['placa'];
$id = (int) $v['id'];
$entregues = array_filter($historico, fn($o) => $o['status'] === 'ENTREGUE');
$totalServicos = array_sum(array_map(fn($o) => (float) $o['valor_total'], $entregues));
$ultima = $historico[0] ?? null;
$kms = array_filter(array_map(fn($o) => (int) $o['km_veiculo'], $historico));
$ultimoKm = $kms ? max($kms) : null;
$admin = Permissao::permite(Contexto::perfil(), Permissao::ADMIN);
$qtd = fn($q) => rtrim(rtrim((string) $q, '0'), '.');
?>
<header class="ver-topo">
  <div class="ver-topo__id">
    <span class="ver-topo__avatar ver-topo__avatar--quadrado" aria-hidden="true"><?= icone('car', 30, 1.5) ?></span>
    <div class="ver-topo__texto">
      <p class="ver-topo__voltar"><a href="./veiculos"><?= icone('chevron-left', 14, 2) ?> Veículos</a></p>
      <h1><?= e($v['modelo'] ?: 'Modelo não informado') ?></h1>
      <p class="ver-topo__meta">
        <span class="placa-caixa"><?= e($v['placa']) ?></span>
        <?php if (!empty($v['ano'])): ?><span class="ver-chip ver-chip--mono"><?= icone('calendar', 13) ?> <?= e($v['ano']) ?></span><?php endif; ?>
        <?php if (!empty($v['cor'])): ?><span class="ver-chip"><?= e($v['cor']) ?></span><?php endif; ?>
        <?php if (!empty($v['combustivel'])): ?><span class="ver-chip"><?= icone('oil', 13) ?> <?= e($v['combustivel']) ?></span><?php endif; ?>
        <?php if (!$v['ativo']): ?><span class="ver-chip ver-chip--escuro">Inativo</span><?php endif; ?>
      </p>
    </div>
  </div>
  <div class="ver-topo__acoes">
    <?php if ($v['ativo']): ?><a class="btn btn--ghost btn--sm" href="./os-nova?cliente=<?= (int) $v['cliente_id'] ?>&amp;veiculo=<?= $id ?>"><?= icone('work-order') ?> Novo orçamento</a><?php endif; ?>
    <a class="btn btn--primary btn--sm" href="./veiculo-form?id=<?= $id ?>"><?= icone('edit') ?> Editar</a>
    <details class="menu-mais">
      <summary class="btn btn--ghost btn--sm btn--icone" aria-label="Mais ações"><?= icone('more-h') ?></summary>
      <div class="menu-mais__lista">
        <form method="post" action="./veiculo-ativo?id=<?= $id ?>" <?= (int) $v['ativo'] ? 'data-confirmar="Desativar este veículo? Ele deixa de aparecer nas listas e nas escolhas, mas o histórico continua."' : '' ?>>
          <?= csrf_field() ?><input type="hidden" name="ativo" value="<?= (int) $v['ativo'] ? 0 : 1 ?>">
          <button><?= icone($v['ativo'] ? 'lock' : 'check') ?><?= (int) $v['ativo'] ? 'Desativar' : 'Reativar' ?></button>
        </form>
        <?php if ($admin): ?>
          <form method="post" action="./veiculo-excluir?id=<?= $id ?>" data-confirmar="Excluir este veículo definitivamente? Só é possível se não houver nada ligado a ele.">
            <?= csrf_field() ?><button><?= icone('trash') ?>Excluir</button>
          </form>
        <?php endif; ?>
      </div>
    </details>
  </div>
</header>

<div class="ver-numeros" aria-label="Histórico do veículo">
  <div><span>Total em serviços</span><strong><?= dinheiro($totalServicos) ?></strong><small><?= count($entregues) ?> OS entregue<?= count($entregues) === 1 ? '' : 's' ?></small></div>
  <div><span>Ordens de serviço</span><strong><?= count($historico) ?></strong><small>no histórico</small></div>
  <div><span>Última visita</span><strong><?= $ultima ? data_br($ultima['criado_em']) : '—' ?></strong><small><?= $ultima ? os_numero($ultima['numero']) : 'nenhuma OS ainda' ?></small></div>
  <div><span>Maior quilometragem</span><strong><?= $ultimoKm ? number_format($ultimoKm, 0, ',', '.') . ' km' : '—' ?></strong><small><?= $ultimoKm ? 'registrada nas OS' : 'não registrada' ?></small></div>
</div>

<div class="ver-grade">
  <div class="ver-principal">
    <section class="card card--tabela ver-historico" aria-labelledby="ver-hist">
      <header class="ver-historico__cabeca">
        <div><h2 id="ver-hist">Histórico do veículo</h2><p><?= $historico ? count($historico) . ' ordem(ns) de serviço, da mais recente para a mais antiga.' : 'As OS deste veículo aparecem aqui.' ?></p></div>
      </header>
      <?php if (!$historico): ?>
        <div class="vazio"><?= icone('work-order', 28, 1.25) ?>Nenhum serviço registrado para este veículo.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table ver-os">
            <thead><tr><th>OS</th><th>Data</th><th class="num">Km</th><th>Serviços e peças</th><th>Situação</th><th class="num">Total</th></tr></thead>
            <tbody>
              <?php foreach ($historico as $os): [$r, $cor] = os_status($os['status']); $link = './os?id=' . (int) $os['id']; $itens = $os['itens']; ?>
                <tr data-href="<?= $link ?>">
                  <td><a href="<?= $link ?>"><strong><?= os_numero($os['numero']) ?></strong></a></td>
                  <td class="tabular"><?= data_br($os['criado_em']) ?></td>
                  <td class="num secundario"><?= $os['km_veiculo'] ? number_format((int) $os['km_veiculo'], 0, ',', '.') : '—' ?></td>
                  <td class="ver-os__itens">
                    <?php if ($os['defeito_relatado']): ?><strong><?= e($os['defeito_relatado']) ?></strong><?php endif; ?>
                    <?php if ($itens): ?>
                      <small><?= e(implode(' · ', array_map(fn($i) => $i['descricao'] . ((float) $i['quantidade'] != 1 ? ' × ' . $qtd($i['quantidade']) : ''), array_slice($itens, 0, 3)))) ?><?= count($itens) > 3 ? ' · +' . (count($itens) - 3) : '' ?></small>
                    <?php elseif (!$os['defeito_relatado']): ?><small>—</small><?php endif; ?>
                  </td>
                  <td><span class="badge badge--<?= $cor ?>"><?= $r ?></span></td>
                  <td class="num"><strong><?= dinheiro($os['valor_total']) ?></strong></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <aside class="card ver-lado" aria-label="Proprietário e observações">
    <header class="ver-lado__cabeca"><h2>Proprietário</h2></header>
    <a class="ver-veiculo" href="./cliente?id=<?= (int) $v['cliente_id'] ?>">
      <span class="avatar avatar--g" aria-hidden="true"><?php $pc = preg_split('/\s+/', trim($v['cliente'])); echo e(mb_strtoupper(mb_substr($pc[0], 0, 1) . (count($pc) > 1 ? mb_substr(end($pc), 0, 1) : ''))); ?></span>
      <span class="ver-veiculo__texto"><strong><?= e($v['cliente']) ?></strong><small>Ver ficha do cliente</small></span>
      <?= icone('chevron-right', 14, 2) ?>
    </a>
    <div class="ver-lado__bloco">
      <h3 class="ver-grupo__titulo"><?= icone('file', 15) ?> Observações</h3>
      <?php if ($v['observacoes']): ?><p class="ver-lado__texto"><?= e($v['observacoes']) ?></p><?php else: ?><p class="secundario ver-vazio">Nenhuma observação.</p><?php endif; ?>
    </div>
  </aside>
</div>
