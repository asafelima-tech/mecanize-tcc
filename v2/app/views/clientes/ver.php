<?php /** Espera: $c, $veiculos, $historico */ $titulo = $c['nome']; ?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= e($c['nome']) ?> <?php if (!$c['ativo']): ?><span class="badge badge--neutral">Inativo</span><?php endif; ?></h1>
    <p class="page-header__subtitle"><a href="./clientes">Clientes</a> · <?= $c['tipo_pessoa'] === 'PJ' ? 'Pessoa jurídica' : 'Pessoa física' ?> · cliente desde <?= data_br($c['criado_em']) ?></p>
  </div>
  <div class="page-header__actions acoes">
    <?php $rotaAtivo = 'cliente-ativo'; $rotaExcluir = 'cliente-excluir'; $registro = $c; $nome = 'este cliente'; require V2_RAIZ . '/app/views/layout/_ativo.php'; ?>
    <a class="btn btn--primary btn--sm" href="./cliente-form?id=<?= (int) $c['id'] ?>"><i class="bi bi-pencil"></i> Editar</a>
  </div>
</div>

<div class="ficha">
  <div>
    <div class="card">
      <dl class="dados">
        <div><dt><?= $c['tipo_pessoa'] === 'PJ' ? 'CNPJ' : 'CPF' ?></dt><dd><?= $c['documento'] ? e(documento_fmt($c['documento'])) : '—' ?></dd></div>
        <div><dt>Telefone</dt><dd><?= $c['telefone'] ? e(telefone_fmt($c['telefone'])) : '—' ?>
          <?php if ($c['telefone'] && $c['aceita_contato']): ?> · <a href="https://wa.me/55<?= e(so_digitos($c['telefone'])) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></dd></div>
        <div><dt>E-mail</dt><dd><?= e($c['email'] ?: '—') ?></dd></div>
        <div><dt>Endereço</dt><dd><?= e(implode(', ', array_filter([$c['endereco'], $c['bairro'], trim(($c['cidade'] ?? '') . ($c['uf'] ? '/' . $c['uf'] : ''))]))) ?: '—' ?></dd></div>
        <div><dt>CEP</dt><dd><?= $c['cep'] ? e(substr($c['cep'], 0, 5) . '-' . substr($c['cep'], 5)) : '—' ?></dd></div>
        <div><dt>Aceita contato</dt><dd><?= $c['aceita_contato'] ? 'Sim' : 'Não' ?></dd></div>
      </dl>
      <?php if ($c['observacoes']): ?><p style="margin-top:14px;font-size:13px;white-space:pre-line"><?= e($c['observacoes']) ?></p><?php endif; ?>
    </div>

    <div class="card">
      <h2 class="card__titulo">Histórico de serviços</h2>
      <?php if (!$historico): ?>
        <div class="vazio" style="padding:16px">Nenhuma ordem de serviço para este cliente.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>OS</th><th>Veículo</th><th>Aberta em</th><th>Situação</th><th class="num">Total</th></tr></thead>
            <tbody>
              <?php foreach ($historico as $os): [$r, $cor] = os_status($os['status']); ?>
                <tr><td><?= os_numero($os['numero']) ?></td><td><?= e($os['placa']) ?></td><td><?= data_br($os['criado_em']) ?></td>
                  <td><span class="badge badge--<?= $cor ?>"><?= $r ?></span></td><td class="num"><?= dinheiro($os['valor_total']) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h2 class="card__titulo">Veículos <a href="./veiculo-form?cliente=<?= (int) $c['id'] ?>">+ Adicionar</a></h2>
    <?php if (!$veiculos): ?>
      <p class="form-dica">Nenhum veículo cadastrado.</p>
    <?php endif; ?>
    <?php foreach ($veiculos as $v): ?>
      <div class="linha-os">
        <div class="linha-os__topo"><a href="./veiculo?id=<?= (int) $v['id'] ?>"><strong><?= e($v['placa']) ?></strong></a><?php if (!$v['ativo']): ?><span class="badge badge--neutral">Inativo</span><?php endif; ?></div>
        <div class="form-dica"><?= e(trim($v['modelo'] . ' ' . ($v['ano'] ?? ''))) ?><?= $v['cor'] ? ' · ' . e($v['cor']) : '' ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
