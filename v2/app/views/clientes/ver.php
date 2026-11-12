<?php
/**
 * Ficha de consulta do cliente: cabeçalho com a identificação (tipo, documento, desde quando, situação) e os números do relacionamento (as mesmas
 * regras da lista: total gasto = OS entregues; última visita = OS mais recente), os dados agrupados
 * (contato, endereço e observações), o histórico de OS e os veículos ao lado.
 * Ações: editar; novo orçamento e agendar (os mesmos atalhos da lista); desativar e excluir no menu.
 * Espera: $c, $veiculos, $historico
 */
$titulo = $c['nome'];
$pj = $c['tipo_pessoa'] === 'PJ';
$partes = preg_split('/\s+/', trim($c['nome']));
$iniciais = mb_strtoupper(mb_substr($partes[0], 0, 1) . (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : ''));
$entregues = array_filter($historico, fn($o) => $o['status'] === 'ENTREGUE');
$totalGasto = array_sum(array_map(fn($o) => (float) $o['valor_total'], $entregues));
$ultima = $historico ? max(array_column($historico, 'criado_em')) : null;
$cep = $c['cep'] ? substr($c['cep'], 0, 5) . '-' . substr($c['cep'], 5) : '';
$cidadeUf = trim(($c['cidade'] ?? '') . ($c['uf'] ? '/' . $c['uf'] : ''));
$temEndereco = $c['endereco'] || $c['bairro'] || $cidadeUf || $cep;
$id = (int) $c['id'];
$admin = Permissao::permite(Contexto::perfil(), Permissao::ADMIN);
?>
<header class="ver-topo">
  <div class="ver-topo__id">
    <span class="ver-topo__avatar" aria-hidden="true"><?= e($iniciais) ?></span>
    <div class="ver-topo__texto">
      <p class="ver-topo__voltar"><a href="./clientes"><?= icone('chevron-left', 14, 2) ?> Clientes</a></p>
      <h1><?= e($c['nome']) ?></h1>
      <p class="ver-topo__meta">
        <span class="ver-chip"><?= icone($pj ? 'folder' : 'user', 13) ?> <?= $pj ? 'Pessoa jurídica' : 'Pessoa física' ?></span>
        <?php if ($c['documento']): ?><span class="ver-chip ver-chip--mono"><?= $pj ? 'CNPJ' : 'CPF' ?> <?= e(documento_fmt($c['documento'])) ?></span><?php endif; ?>
        <span class="ver-chip"><?= icone('calendar', 13) ?> Cliente desde <?= data_br($c['criado_em']) ?></span>
        <?php if (!$c['ativo']): ?><span class="ver-chip ver-chip--escuro">Inativo</span><?php endif; ?>
      </p>
    </div>
  </div>
  <div class="ver-topo__acoes">
    <a class="btn btn--ghost btn--sm" href="./os-nova?cliente=<?= $id ?>"><?= icone('work-order') ?> Novo orçamento</a>
    <a class="btn btn--primary btn--sm" href="./cliente-form?id=<?= $id ?>"><?= icone('edit') ?> Editar</a>
    <details class="menu-mais">
      <summary class="btn btn--ghost btn--sm btn--icone" aria-label="Mais ações"><?= icone('more-h') ?></summary>
      <div class="menu-mais__lista">
        <a href="./agenda-form?cliente=<?= $id ?>"><?= icone('calendar') ?>Agendar</a>
        <form method="post" action="./cliente-ativo?id=<?= $id ?>" <?= (int) $c['ativo'] ? 'data-confirmar="Desativar este cliente? Ele deixa de aparecer nas listas e nas escolhas, mas o histórico continua."' : '' ?>>
          <?= csrf_field() ?><input type="hidden" name="ativo" value="<?= (int) $c['ativo'] ? 0 : 1 ?>">
          <button><?= icone($c['ativo'] ? 'lock' : 'check') ?><?= (int) $c['ativo'] ? 'Desativar' : 'Reativar' ?></button>
        </form>
        <?php if ($admin): ?>
          <form method="post" action="./cliente-excluir?id=<?= $id ?>" data-confirmar="Excluir este cliente definitivamente? Só é possível se não houver nada ligado a ele.">
            <?= csrf_field() ?><button><?= icone('trash') ?>Excluir</button>
          </form>
        <?php endif; ?>
      </div>
    </details>
  </div>
</header>

<div class="ver-numeros" aria-label="Relacionamento">
  <div><span>Total gasto</span><strong><?= dinheiro($totalGasto) ?></strong><small><?= count($entregues) ?> OS entregue<?= count($entregues) === 1 ? '' : 's' ?></small></div>
  <div><span>Ordens de serviço</span><strong><?= count($historico) ?></strong><small>no histórico</small></div>
  <div><span>Última visita</span><strong><?= $ultima ? data_br($ultima) : '—' ?></strong><small><?= $ultima ? 'abertura da OS mais recente' : 'nenhuma OS ainda' ?></small></div>
  <div><span>Veículos</span><strong><?= count($veiculos) ?></strong><small><?= count(array_filter($veiculos, fn($v) => $v['ativo'])) ?> ativo(s)</small></div>
</div>

<div class="ver-grade">
  <div class="ver-principal">
    <section class="card ver-dados" aria-label="Dados do cliente">
      <div class="ver-grupo">
        <h2 class="ver-grupo__titulo"><?= icone('phone', 15) ?> Contato</h2>
        <dl class="ver-lista">
          <div><dt>Telefone</dt><dd><?= $c['telefone'] ? e(telefone_fmt($c['telefone'])) : '<span class="secundario">Não informado</span>' ?></dd></div>
          <div><dt>E-mail</dt><dd><?= $c['email'] ? '<a href="mailto:' . e($c['email']) . '" class="ver-link">' . e($c['email']) . '</a>' : '<span class="secundario">Não informado</span>' ?></dd></div>
          <div><dt>Aceita contato</dt><dd><span class="ver-estado<?= $c['aceita_contato'] ? ' is-sim' : '' ?>"><?= icone($c['aceita_contato'] ? 'check' : 'close', 12, 2.25) ?><?= $c['aceita_contato'] ? 'Sim, por WhatsApp e e-mail' : 'Não' ?></span></dd></div>
        </dl>
        <?php if ($c['telefone'] && $c['aceita_contato']): ?>
          <a class="btn btn--contorno btn--sm ver-whats" href="https://wa.me/55<?= e(so_digitos($c['telefone'])) ?>" target="_blank" rel="noopener"><?= icone('message') ?> Conversar no WhatsApp</a>
        <?php endif; ?>
      </div>

      <div class="ver-grupo">
        <h2 class="ver-grupo__titulo"><?= icone('location', 15) ?> Endereço</h2>
        <?php if (!$temEndereco): ?><p class="secundario ver-vazio">Não informado</p><?php else: ?>
          <address class="ver-endereco">
            <?php if ($c['endereco']): ?><strong><?= e($c['endereco']) ?></strong><?php endif; ?>
            <?php if ($c['bairro']): ?><span><?= e($c['bairro']) ?></span><?php endif; ?>
            <?php if ($cidadeUf): ?><span><?= e($cidadeUf) ?></span><?php endif; ?>
            <?php if ($cep): ?><span class="ver-endereco__cep">CEP <?= e($cep) ?></span><?php endif; ?>
          </address>
        <?php endif; ?>
      </div>

      <div class="ver-obs">
        <h2 class="ver-grupo__titulo"><?= icone('file', 15) ?> Observações</h2>
        <?php if ($c['observacoes']): ?><p><?= e($c['observacoes']) ?></p><?php else: ?><p class="secundario">Nenhuma observação.</p><?php endif; ?>
      </div>
    </section>

    <section class="card card--tabela ver-historico" aria-labelledby="ver-hist">
      <header class="ver-historico__cabeca">
        <div><h2 id="ver-hist">Histórico de serviços</h2><p><?= count($historico) ? count($historico) . ' ordem(ns) de serviço, de todos os veículos.' : 'Todas as OS do cliente aparecem aqui.' ?></p></div>
      </header>
      <?php if (!$historico): ?>
        <div class="vazio"><?= icone('work-order', 28, 1.25) ?>Nenhuma ordem de serviço para este cliente.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>OS</th><th>Veículo</th><th>Aberta em</th><th>Entregue em</th><th>Situação</th><th class="num">Total</th></tr></thead>
            <tbody>
              <?php foreach ($historico as $os): [$r, $cor] = os_status($os['status']); $link = './os?id=' . (int) $os['id']; ?>
                <tr data-href="<?= $link ?>">
                  <td><a href="<?= $link ?>"><strong><?= os_numero($os['numero']) ?></strong></a></td>
                  <td><span class="placa-caixa placa-caixa--p"><?= e($os['placa']) ?></span></td>
                  <td class="tabular"><?= data_br($os['criado_em']) ?></td>
                  <td class="tabular secundario"><?= $os['entregue_em'] ? data_br($os['entregue_em']) : '—' ?></td>
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

  <aside class="card ver-lado" aria-labelledby="ver-veiculos">
    <header class="ver-lado__cabeca"><h2 id="ver-veiculos">Veículos</h2><a class="link-forte" href="./veiculo-form?cliente=<?= $id ?>"><?= icone('plus', 14, 2) ?> Adicionar</a></header>
    <?php if (!$veiculos): ?>
      <div class="ver-lado__vazio"><?= icone('car', 24, 1.4) ?><span>Nenhum veículo cadastrado.</span></div>
    <?php endif; ?>
    <ul class="ver-veiculos">
      <?php foreach ($veiculos as $v): $det = array_filter([$v['ano'] ?? '', $v['cor'] ?? '', $v['combustivel'] ?? ''], fn($x) => (string) $x !== ''); ?>
        <li><a href="./veiculo?id=<?= (int) $v['id'] ?>" class="ver-veiculo<?= $v['ativo'] ? '' : ' is-inativo' ?>">
          <span class="placa-caixa placa-caixa--p"><?= e($v['placa']) ?></span>
          <span class="ver-veiculo__texto"><strong><?= e($v['modelo'] ?: 'Modelo não informado') ?></strong><small><?= e(implode(' · ', $det)) ?><?= $v['ativo'] ? '' : ($det ? ' · ' : '') . 'Inativo' ?></small></span>
          <?= icone('chevron-right', 14, 2) ?>
        </a></li>
      <?php endforeach; ?>
    </ul>
  </aside>
</div>
