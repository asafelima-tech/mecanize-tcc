<?php
/**
 * Clientes (Design System, tela 17): números do cadastro, abas ativos/inativos, busca e tipo, a
 * lista com veículos, última visita e total gasto, e os clientes que mais voltam.
 * Espera: $clientes, $f, $p, $resumo, $recorrentes
 */
$titulo = 'Clientes';
$iniciais = function (string $nome): string {
    $p = preg_split('/\s+/', trim($nome));
    return mb_strtoupper(mb_substr($p[0], 0, 1) . (count($p) > 1 ? mb_substr(end($p), 0, 1) : ''));
};
$numero = fn (int $n): string => number_format($n, 0, ',', '.');
$variacao = $resumo['novos_anterior'] > 0 ? ($resumo['novos_mes'] - $resumo['novos_anterior']) / $resumo['novos_anterior'] * 100 : null;
$moeda = fn (float $v): string => 'R$ ' . number_format($v, $v == floor($v) ? 0 : 2, ',', '.');
$acoes = function (array $c): string {
    $id = (int) $c['id'];
    $h = '<details class="menu-mais"><summary class="btn btn--fantasma btn--icone" aria-label="Ações de ' . e($c['nome']) . '">' . icone('more-h') . '</summary><div class="menu-mais__lista">'
       . '<a href="./cliente?id=' . $id . '">' . icone('eye') . 'Abrir cadastro</a><a href="./cliente-form?id=' . $id . '">' . icone('edit') . 'Editar</a>';
    if ($c['ativo']) {
        $h .= '<a href="./os-nova?cliente=' . $id . '">' . icone('work-order') . 'Novo orçamento</a><a href="./agenda-form?cliente=' . $id . '">' . icone('calendar') . 'Agendar</a>';
    }

    return $h . '</div></details>';
};
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Gestão de clientes</h1>
    <p class="page-header__subtitle">Pessoas físicas e empresas atendidas pela oficina.</p>
  </div>
  <div class="page-header__actions"><a class="btn btn--primary" href="./cliente-form"><?= icone('plus') ?> Novo cliente</a></div>
</div>

<div class="stat-grid stat-grid--3 stat-grid--icone">
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Total de clientes</span><?= icone('users') ?></div>
    <span class="stat-card__value"><?= $numero($resumo['ativos'] + $resumo['inativos']) ?></span>
    <span class="stat-card__pe"><?= $numero($resumo['ativos']) ?> ativos · <?= $numero($resumo['inativos']) ?> inativos</span>
  </div>
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Novos este mês</span><?php if ($variacao !== null): ?><span class="stat-card__chip" title="Comparado ao mesmo período do mês anterior"><?= $variacao >= 0 ? '+' : '−' ?><?= number_format(abs($variacao), 0) ?>%</span><?php endif; ?></div>
    <span class="stat-card__value"><?= $numero($resumo['novos_mes']) ?></span>
    <a class="stat-card__pe" href="./clientes?novos=mes&amp;status=todos">ver lista do mês →</a>
  </div>
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Recorrentes</span><?= icone('check') ?></div>
    <span class="stat-card__value"><?= $numero($resumo['recorrentes']) ?></span>
    <span class="stat-card__pe"><?= Cliente::VISITAS_RECORRENTE ?> ou mais OS nos últimos 12 meses</span>
  </div>
</div>

<div class="lista-lateral card--espaco">
  <div>
    <nav class="abas abas--contagem" aria-label="Situação">
      <a href="<?= e(url_com(['status' => null, 'pagina' => null])) ?>"<?= $f['status'] === 'ativos' ? ' class="ativa" aria-current="page"' : '' ?>>Clientes ativos<span class="abas__n"><?= $numero($resumo['ativos']) ?></span></a>
      <a href="<?= e(url_com(['status' => 'inativos', 'pagina' => null])) ?>"<?= $f['status'] === 'inativos' ? ' class="ativa" aria-current="page"' : '' ?>>Clientes inativos<span class="abas__n"><?= $numero($resumo['inativos']) ?></span></a>
      <?php if ($f['status'] === 'todos'): ?><a href="#" class="ativa" aria-current="page">Todos</a><?php endif; ?>
    </nav>

    <form class="filtros filtros--solto" method="get" action="./clientes">
      <?php foreach (['status' => $f['status'] !== 'ativos' ? $f['status'] : '', 'novos' => $f['novos']] as $k => $v): if ($v === '') continue; ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endforeach; ?>
      <div class="filtros__busca filtros__busca--largo"><?= icone('search') ?><input type="search" name="busca" value="<?= e($f['busca']) ?>" placeholder="Buscar por nome, CPF/CNPJ, telefone ou e-mail" aria-label="Buscar"></div>
      <select name="tipo" aria-label="Tipo de pessoa">
        <option value="">Todos os tipos</option>
        <option value="PF" <?= $f['tipo'] === 'PF' ? 'selected' : '' ?>>Pessoa física</option>
        <option value="PJ" <?= $f['tipo'] === 'PJ' ? 'selected' : '' ?>>Pessoa jurídica</option>
      </select>
      <?php if ($f['novos'] === 'mes'): ?><a class="filtro-ativo" href="<?= e(url_com(['novos' => null, 'status' => null, 'pagina' => null])) ?>">Novos este mês <?= icone('close', 12, 2) ?></a><?php endif; ?>
    </form>

    <div class="card card--tabela">
      <?php if (!$clientes): ?>
        <div class="vazio"><?= icone('users', 32, 1.25) ?><?= $f['busca'] !== '' || $f['tipo'] !== '' || $f['novos'] !== '' ? 'Nenhum cliente encontrado com esses filtros.' : 'Nenhum cliente cadastrado ainda.' ?></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Cliente</th><th>Contato</th><th>Veículos</th><th>Última visita</th><th class="num">Total gasto</th><th class="num"><span class="sr-only">Ações</span></th></tr></thead>
            <tbody>
              <?php foreach ($clientes as $c): $placas = $c['placas'] ? explode(',', $c['placas']) : []; ?>
                <tr data-href="./cliente?id=<?= (int) $c['id'] ?>" class="<?= $c['ativo'] ? '' : 'inativo' ?>">
                  <td><span class="celula-pessoa"><span class="avatar"><?= e($iniciais($c['nome'])) ?></span>
                    <span><a href="./cliente?id=<?= (int) $c['id'] ?>"><?= e($c['nome']) ?></a><span class="sub"><?= $c['documento'] ? ($c['tipo_pessoa'] === 'PJ' ? 'CNPJ ' : 'CPF ') . e(documento_fmt($c['documento'])) : ($c['tipo_pessoa'] === 'PJ' ? 'Pessoa jurídica' : 'Pessoa física') ?></span></span></span></td>
                  <td><?= $c['telefone'] ? e(telefone_fmt($c['telefone'])) : '—' ?><?php if ($c['email']): ?><span class="sub"><?= e($c['email']) ?></span><?php endif; ?></td>
                  <td><?php if (!$placas): ?><span class="secundario">—</span><?php else: ?>
                    <?php foreach (array_slice($placas, 0, 2) as $pl): ?><span class="placa placa--bloco"><?= e($pl) ?></span><?php endforeach; ?>
                    <?php if (count($placas) > 2): ?><span class="secundario">+<?= count($placas) - 2 ?></span><?php endif; ?><?php endif; ?></td>
                  <td class="secundario tabular"><?= $c['ultima_os'] ? data_br($c['ultima_os']) : '—' ?></td>
                  <td class="num"><strong><?= $moeda((float) $c['total_gasto']) ?></strong></td>
                  <td class="num"><?= $acoes($c) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
      <?php $rotuloItens = 'clientes'; require V2_RAIZ . '/app/views/layout/_rodape.php'; ?>
    </div>
  </div>

  <aside class="card painel-lateral" aria-label="Clientes recorrentes">
    <h2 class="card__titulo">Clientes recorrentes</h2>
    <?php if (!$recorrentes): ?>
      <p class="form-dica">Quem voltar duas vezes ou mais em 12 meses aparece aqui.</p>
    <?php endif; ?>
    <ul class="lista-links">
      <?php foreach ($recorrentes as $r): ?>
        <li><a href="./cliente?id=<?= (int) $r['id'] ?>">
          <span class="avatar avatar--g"><?= e($iniciais($r['nome'])) ?></span>
          <span class="lista-links__texto"><strong><?= e($r['nome']) ?></strong><small><?= (int) $r['visitas'] ?> visitas · <?= $moeda((float) $r['total']) ?></small></span>
          <?= icone('chevron-right') ?>
        </a></li>
      <?php endforeach; ?>
    </ul>
  </aside>
</div>
