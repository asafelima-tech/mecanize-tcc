<?php
/**
 * Comissões da equipe. A regra de cada pessoa vem do cadastro de usuários (% sobre serviços, peças ou o total);
 * o valor de cada item é calculado e gravado quando a OS é concluída (OrdemServico::calcularComissoes).
 * Aqui só se consulta e se lança a conta a pagar (Lancamento::gerarComissao, com todos os itens pendentes da pessoa).
 *   · números: a lançar e a pagar (hoje); lançado e pago (no período);
 *   · equipe: regra atual, o que está pendente e o que já foi lançado e não foi pago;
 *   · detalhamento: os itens pendentes de uma pessoa, por OS, com base, percentual e comissão;
 *   · histórico: as contas de comissão do período, com as OS que cada uma cobre e o pagamento.
 * Espera: $f, $p, $equipe, $resumo, $itens, $historico, $funcionarios, $caixa
 */
$titulo = 'Comissões';
$porId = [];
foreach ($equipe as $pessoa) $porId[(int) $pessoa['id']] = $pessoa;
$opcoes = $porId;
foreach ($funcionarios as $fu) $opcoes[(int) $fu['id']] ??= $fu;
uasort($opcoes, fn($a, $b) => strcmp($a['nome'], $b['nome']));
$sel = $f['tecnico'] && isset($opcoes[$f['tecnico']]) ? $opcoes[$f['tecnico']] : null;
$selEquipe = $sel ? ($porId[(int) $sel['id']] ?? null) : null;

$de = new DateTimeImmutable($f['de']);
$ate = new DateTimeImmutable($f['ate']);
$periodo = $de->format('d/m') . ' a ' . $ate->format($de->format('Y') === $ate->format('Y') ? 'd/m' : 'd/m/Y');
$base = array_filter(['tecnico' => $f['tecnico'] ?: '', 'de' => $f['de'], 'ate' => $f['ate'], 'situacao' => $f['situacao'] !== 'todos' ? $f['situacao'] : ''], fn($v) => $v !== '');
$link = fn(array $troca) => './comissoes?' . http_build_query(array_filter($troca + $base, fn($v) => $v !== '' && $v !== null));
$voltarLista = 'comissoes?' . http_build_query($base + ($p->pagina > 1 ? ['pagina' => $p->pagina] : []));

$regra = fn(array $u) => ($u['comissao_tipo'] ?? 'NENHUMA') === 'NENHUMA'
    ? 'Sem regra de comissão'
    : rtrim(rtrim(number_format((float) $u['comissao_percentual'], 2, ',', ''), '0'), ',') . '% ' . mb_substr(Usuario::COMISSOES[$u['comissao_tipo']] ?? '', 2);
$pct = fn($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',') . '%';
$iniciais = function (string $nome): string {
    $p = preg_split('/\s+/', trim($nome));
    return mb_strtoupper(mb_substr($p[0], 0, 1) . (count($p) > 1 ? mb_substr(end($p), 0, 1) : ''));
};
$plural = fn(int $n, string $um, string $varios) => $n . ' ' . ($n === 1 ? $um : $varios);
$hoje = date('Y-m-d');
$situacaoConta = function (array $c) use ($hoje): array {
    if (in_array($c['status'], ['ABERTO', 'PARCIAL'], true) && $c['vencimento'] < $hoje) return ['Atrasada', 'danger'];
    return match ($c['status']) { 'PARCIAL' => ['Parcial', 'info'], 'PAGO' => ['Paga', 'success'], 'CANCELADO' => ['Cancelada', 'muted'], default => ['A pagar', 'neutral'] };
};

// Itens pendentes do selecionado, agrupados por OS.
$porOs = [];
foreach ($itens as $i) {
    $porOs[(int) $i['os_id']] ??= ['numero' => $i['numero'], 'concluido_em' => $i['concluido_em'], 'itens' => [], 'base' => 0.0, 'comissao' => 0.0];
    $porOs[(int) $i['os_id']]['itens'][] = $i;
    $porOs[(int) $i['os_id']]['base'] += (float) $i['valor_total'];
    $porOs[(int) $i['os_id']]['comissao'] += (float) $i['comissao_valor'];
}
$totalItens = round(array_sum(array_column($itens, 'comissao_valor')), 2);
$baseItens = round(array_sum(array_column($itens, 'valor_total')), 2);
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Comissões</h1>
    <p class="page-header__subtitle"><a href="./financeiro?tipo=PAGAR">Financeiro</a> · o que a equipe tem a receber pelas OS concluídas</p>
  </div>
  <div class="page-header__actions">
    <a class="btn btn--contorno" href="./usuarios"><?= icone('users') ?> Regras da equipe</a>
  </div>
</div>

<div class="ver-numeros comissoes-numeros" aria-label="Resumo das comissões<?= $sel ? ' de ' . e($sel['nome']) : '' ?>">
  <div>
    <span>A lançar<?= $sel ? ' · ' . e(strtok($sel['nome'], ' ')) : '' ?></span>
    <strong><?= dinheiro($resumo['pendente']) ?></strong>
    <small><?= $resumo['pend_os'] ? $plural($resumo['pend_os'], 'OS concluída', 'OS concluídas') . ($sel ? '' : ' · ' . $plural($resumo['pend_pessoas'], 'pessoa', 'pessoas')) : 'nada pendente' ?></small>
  </div>
  <div>
    <span>Lançado a pagar</span>
    <strong><?= dinheiro($resumo['aberto']) ?></strong>
    <small><?= $resumo['n_aberto'] ? $plural($resumo['n_aberto'], 'conta em aberto', 'contas em aberto') : 'nenhuma conta em aberto' ?></small>
  </div>
  <div>
    <span>Pago no período</span>
    <strong><?= dinheiro($resumo['pago']) ?></strong>
    <small><?= $periodo ?> · <?= $resumo['n_pago'] ? $plural($resumo['n_pago'], 'pagamento', 'pagamentos') : 'nenhum pagamento' ?></small>
  </div>
  <div>
    <span>Lançado no período</span>
    <strong><?= dinheiro($resumo['lancado']) ?></strong>
    <small><?= $periodo ?> · <?= $plural($resumo['n_lancado'], 'conta', 'contas') ?></small>
  </div>
</div>

<form class="filtros filtros--solto comissoes-filtros" method="get" action="./comissoes">
  <select name="tecnico" aria-label="Funcionário" onchange="this.form.submit()">
    <option value="">Toda a equipe</option>
    <?php foreach ($opcoes as $o): ?><option value="<?= (int) $o['id'] ?>" <?= $f['tecnico'] === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['nome']) ?></option><?php endforeach; ?>
  </select>
  <label class="campo-periodo comissoes-periodo" title="Período: vale para lançado, pago e o histórico"><?= icone('calendar') ?>
    <input type="date" name="de" value="<?= e($f['de']) ?>" aria-label="De" onchange="this.form.submit()">
    <span>–</span>
    <input type="date" name="ate" value="<?= e($f['ate']) ?>" aria-label="Até" onchange="this.form.submit()">
  </label>
  <select name="situacao" aria-label="Situação do pagamento" onchange="this.form.submit()">
    <?php foreach (['todos' => 'Todas as situações', 'abertos' => 'A pagar', 'pagos' => 'Pagas', 'cancelados' => 'Canceladas'] as $k => $r): ?>
      <option value="<?= $k ?>" <?= $f['situacao'] === $k ? 'selected' : '' ?>><?= $r ?></option>
    <?php endforeach; ?>
  </select>
  <?php $periodoPadrao = $f['de'] === (new DateTimeImmutable('first day of this month'))->modify('-2 months')->format('Y-m-d') && $f['ate'] === date('Y-m-d');
        if ($f['tecnico'] || $f['situacao'] !== 'todos' || !$periodoPadrao): ?>
    <a class="btn btn--ghost btn--sm comissoes-limpar" href="./comissoes"><?= icone('close', 12, 2) ?> Limpar</a>
  <?php endif; ?>
  <span class="filtros__fim">O período vale para lançado, pago e o histórico. O pendente é sempre o total.</span>
</form>

<!-- Equipe -->
<section class="card card--tabela comissoes-bloco" aria-labelledby="t-equipe">
  <header class="ver-historico__cabeca">
    <div><h2 id="t-equipe">Equipe</h2><p>Regra de cada pessoa, o que falta lançar e o que já foi lançado e ainda não foi pago.</p></div>
  </header>
  <?php if (!$equipe): ?>
    <div class="vazio"><?= icone('percent', 28, 1.25) ?>Ninguém da equipe tem regra de comissão. <a href="./usuarios">Defina no cadastro de usuários</a>.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table tabela-comissoes">
        <thead><tr>
          <th>Funcionário</th><th>Pendente desde</th><th class="num">OS</th><th class="num">Base</th><th class="num">A lançar</th><th class="num">Lançado a pagar</th><th class="num"><span class="sr-only">Ações</span></th>
        </tr></thead>
        <tbody>
          <?php foreach ($equipe as $pessoa):
              $id = (int) $pessoa['id'];
              $pend = (float) $pessoa['pend_total'] > 0;
              $ativa = $f['tecnico'] === $id; ?>
            <tr data-href="<?= e($link(['tecnico' => $ativa ? '' : $id, 'pagina' => null])) ?>#detalhe" class="<?= $ativa ? 'selecionada' : '' ?><?= $pessoa['ativo'] ? '' : ' inativo' ?>">
              <td>
                <span class="celula-pessoa"><span class="avatar<?= $ativa ? ' avatar--eu' : '' ?>"><?= e($iniciais($pessoa['nome'])) ?></span>
                  <span><strong><?= e($pessoa['nome']) ?></strong><span class="sub"><?= e($regra($pessoa)) ?><?= $pessoa['ativo'] ? '' : ' · inativo' ?></span></span></span>
              </td>
              <td class="tabular"><?= $pend ? data_br($pessoa['pend_desde']) : '<span class="secundario">—</span>' ?></td>
              <td class="num"><?= $pend ? (int) $pessoa['pend_os'] : '<span class="secundario">—</span>' ?></td>
              <td class="num"><?= $pend ? dinheiro($pessoa['pend_base']) : '<span class="secundario">—</span>' ?></td>
              <td class="num"><strong><?= $pend ? dinheiro($pessoa['pend_total']) : '<span class="secundario">' . dinheiro(0) . '</span>' ?></strong></td>
              <td class="num"><?= (float) $pessoa['aberto'] > 0 ? dinheiro($pessoa['aberto']) . '<span class="sub">' . $plural((int) $pessoa['n_aberto'], 'conta', 'contas') . '</span>' : '<span class="secundario">—</span>' ?></td>
              <td class="num">
                <span class="comissoes-acoes">
                  <?php if ($pend): ?>
                    <button type="button" class="btn btn--primary btn--sm" data-lancar="<?= $id ?>" data-nome="<?= e($pessoa['nome']) ?>" data-total="<?= e(dinheiro($pessoa['pend_total'])) ?>" data-os="<?= (int) $pessoa['pend_os'] ?>">Lançar</button>
                  <?php endif; ?>
                  <a class="btn btn--fantasma btn--icone" href="<?= e($link(['tecnico' => $ativa ? '' : $id, 'pagina' => null])) ?>#detalhe" aria-label="<?= $ativa ? 'Fechar detalhamento' : 'Detalhar ' . e($pessoa['nome']) ?>" title="<?= $ativa ? 'Fechar detalhamento' : 'Detalhar' ?>"><?= icone($ativa ? 'chevron-up' : 'chevron-down') ?></a>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<!-- Detalhamento -->
<?php if ($sel): ?>
  <section class="card card--tabela comissoes-bloco" id="detalhe" aria-labelledby="t-detalhe">
    <header class="ver-historico__cabeca">
      <div>
        <h2 id="t-detalhe">Detalhamento · <?= e($sel['nome']) ?></h2>
        <p>Regra atual: <?= e($regra($sel)) ?>. O percentual de cada item foi gravado quando a OS foi concluída.</p>
      </div>
      <span class="comissoes-acoes">
        <a class="btn btn--ghost btn--sm" href="./usuario-form?id=<?= (int) $sel['id'] ?>"><?= icone('edit') ?> Editar regra</a>
        <?php if ($itens): ?>
          <button type="button" class="btn btn--primary btn--sm" data-lancar="<?= (int) $sel['id'] ?>" data-nome="<?= e($sel['nome']) ?>" data-total="<?= e(dinheiro($totalItens)) ?>" data-os="<?= count($porOs) ?>">Lançar <?= dinheiro($totalItens) ?></button>
        <?php endif; ?>
      </span>
    </header>
    <?php if (!$itens): ?>
      <div class="vazio"><?= icone('check', 28, 1.25) ?>Nada pendente para <?= e(strtok($sel['nome'], ' ')) ?>. O que já foi lançado está no histórico abaixo.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table tabela-comissoes-itens">
          <thead><tr><th>Item</th><th>Tipo</th><th class="num">Valor do item</th><th class="num">Percentual</th><th class="num">Comissão</th></tr></thead>
          <?php foreach ($porOs as $osId => $os): ?>
            <tbody>
              <tr class="comissoes-os">
                <td colspan="2"><a href="./os?id=<?= $osId ?>"><strong><?= os_numero($os['numero']) ?></strong></a><span class="secundario"> · concluída em <?= data_br($os['concluido_em']) ?></span></td>
                <td class="num secundario"><?= dinheiro($os['base']) ?></td><td></td>
                <td class="num"><strong><?= dinheiro($os['comissao']) ?></strong></td>
              </tr>
              <?php foreach ($os['itens'] as $i): ?>
                <tr>
                  <td class="comissoes-item"><?= e($i['descricao']) ?></td>
                  <td><span class="badge badge--plain"><?= $i['tipo'] === 'PECA' ? 'Peça' : 'Serviço' ?></span></td>
                  <td class="num"><?= dinheiro($i['valor_total']) ?></td>
                  <td class="num tabular"><?= $pct($i['comissao_percentual']) ?></td>
                  <td class="num"><?= dinheiro($i['comissao_valor']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          <?php endforeach; ?>
          <tfoot><tr><td colspan="2"><?= $plural(count($itens), 'item', 'itens') ?> em <?= $plural(count($porOs), 'OS', 'OS') ?></td><td class="num"><?= dinheiro($baseItens) ?></td><td></td><td class="num"><strong><?= dinheiro($totalItens) ?></strong></td></tr></tfoot>
        </table>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<!-- Histórico de pagamentos -->
<section class="card card--tabela comissoes-bloco" aria-labelledby="t-historico">
  <header class="ver-historico__cabeca">
    <div><h2 id="t-historico">Histórico de pagamentos</h2><p>Contas de comissão lançadas de <?= $periodo ?><?= $sel ? ' para ' . e($sel['nome']) : '' ?>. Cancelar a conta libera os itens para um novo lançamento.</p></div>
  </header>
  <?php if (!$historico): ?>
    <div class="vazio"><?= icone('history', 28, 1.25) ?>Nenhuma conta de comissão neste período<?= $f['situacao'] !== 'todos' || $sel ? ' com esses filtros' : '' ?>.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table tabela-comissoes">
        <thead><tr><th>Lançada em</th><th>Funcionário</th><th>Ordens de serviço</th><th>Vencimento</th><th>Situação</th><th class="num">Valor</th><th class="num">Pago</th><th class="num"><span class="sr-only">Ações</span></th></tr></thead>
        <tbody>
          <?php foreach ($historico as $c):
              [$rotulo, $cor] = $situacaoConta($c);
              $aberta = in_array($c['status'], ['ABERTO', 'PARCIAL'], true);
              $saldo = round((float) $c['devido'] - (float) $c['valor_pago'], 2);
              $numeros = $c['ordens'] ? array_map(fn($n) => os_numero($n), explode(',', $c['ordens'])) : [];
              $ver = './conta?id=' . (int) $c['id']; ?>
            <tr data-href="<?= $ver ?>" class="<?= $c['status'] === 'CANCELADO' ? 'inativo' : '' ?>">
              <td class="tabular"><?= data_br($c['criado_em']) ?></td>
              <td><?= e($c['funcionario'] ?? '—') ?></td>
              <td class="comissoes-os-lista">
                <?php if ($numeros): ?><?= e(implode(', ', array_slice($numeros, 0, 3))) ?><?= count($numeros) > 3 ? ' <span class="secundario">+' . (count($numeros) - 3) . '</span>' : '' ?>
                <?php else: ?><span class="secundario"><?= e(preg_replace('/^OS:\s*/', '', (string) $c['observacao']) ?: '—') ?></span><?php endif; ?>
              </td>
              <td class="tabular"><?= data_br($c['vencimento']) ?></td>
              <td><span class="badge badge--<?= $cor ?>"><?= $rotulo ?></span></td>
              <td class="num"><strong><?= dinheiro($c['devido']) ?></strong></td>
              <td class="num"><?= (float) $c['valor_pago'] > 0 ? dinheiro($c['valor_pago']) . ($c['pago_em'] ? '<span class="sub">em ' . data_br($c['pago_em']) . '</span>' : '') : '<span class="secundario">—</span>' ?></td>
              <td class="num">
                <span class="comissoes-acoes">
                  <?php if ($aberta): ?>
                    <button type="button" class="btn btn--ghost btn--sm" data-baixar="dlg-baixa" data-acao="./conta-baixar?id=<?= (int) $c['id'] ?>" data-conta="<?= (int) $c['id'] ?>"
                            data-saldo="<?= e(number_format($saldo, 2, ',', '.')) ?>" data-forma="<?= e($c['forma_prevista'] ?? '') ?>" data-descricao="<?= e($c['descricao']) ?>"
                            data-rotulo="Saldo: <?= e(dinheiro($saldo)) ?> · vencimento <?= data_br($c['vencimento']) ?>"><?= icone('cash') ?> Pagar</button>
                  <?php endif; ?>
                  <a class="btn btn--fantasma btn--icone" href="<?= $ver ?>" aria-label="Abrir conta" title="Abrir conta"><?= icone('eye') ?></a>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?php $rotuloItens = $p->total === 1 ? 'conta' : 'contas'; require V2_RAIZ . '/app/views/layout/_rodape.php'; ?>
</section>

<dialog id="dlg-lancar" class="dlg">
  <form method="post" action="./comissao-gerar">
    <?= csrf_field() ?><input type="hidden" name="tecnico_id">
    <h3>Lançar comissão</h3>
    <p class="dlg__conta" data-lancar-nome></p>
    <p class="form-dica" data-lancar-resumo></p>
    <div class="form-field"><label for="lancar-vencimento">Vencimento da conta a pagar</label><input type="date" id="lancar-vencimento" name="vencimento" value="<?= date('Y-m-d') ?>" required></div>
    <p class="form-dica" style="margin-top:10px">Entram todos os itens pendentes da pessoa. A conta aparece em Contas a pagar; o pagamento é registrado nela ou no histórico abaixo.</p>
    <div class="form-acoes"><button type="button" class="btn btn--ghost" data-fechar>Cancelar</button><button class="btn btn--primary">Lançar</button></div>
  </form>
</dialog>

<?php $dlgId = 'dlg-baixa'; $acao = './conta-baixar'; $extras = []; $tipo = 'PAGAR'; require __DIR__ . '/_baixa.php'; ?>

<script>
  (function () {
    var d = document.getElementById('dlg-lancar'), f = d.querySelector('form');
    document.querySelectorAll('[data-lancar]').forEach(function (b) {
      b.addEventListener('click', function (e) {
        e.stopPropagation();
        f.tecnico_id.value = b.dataset.lancar;
        d.querySelector('[data-lancar-nome]').textContent = b.dataset.nome;
        d.querySelector('[data-lancar-resumo]').textContent = b.dataset.total + ' de ' + b.dataset.os + (b.dataset.os === '1' ? ' OS concluída.' : ' OS concluídas.');
        d.showModal();
      });
    });
    d.querySelectorAll('[data-fechar]').forEach(function (b) { b.addEventListener('click', function () { d.close(); }); });
  })();
</script>
