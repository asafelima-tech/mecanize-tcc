<?php
/**
 * Contas a receber / Contas a pagar (abas do Financeiro). A mesma tela para as duas operações; muda o
 * vocabulário e quem é a outra parte (cliente, ou fornecedor/funcionário).
 *   · números do topo: em aberto, em atraso, próximos 7 dias e realizado no mês (não dependem dos filtros);
 *   · situação em abas com contagem; busca, categoria e período de vencimento fora do card;
 *   · a lista com o prazo escrito ("vence em 3 dias", "atrasada há 2 dias") e o pagamento direto na linha.
 * Espera: $contas, $saldo, $f, $p, $ind, $outro, $caixa
 */
$receber = $f['tipo'] === 'RECEBER';
$titulo = $receber ? 'Contas a receber' : 'Contas a pagar';
$verbo = $receber ? 'Receber' : 'Pagar';
$feito = $receber ? 'Recebido' : 'Pago';
$hoje = new DateTimeImmutable('today');
$em7 = $hoje->modify('+7 days');
$n = fn($v) => (int) $v;
$plural = fn(int $q, string $um, string $varios) => $q . ' ' . ($q === 1 ? $um : $varios);

// Link da própria lista trocando alguns filtros (os demais continuam; volta para a página 1).
$base = array_filter(['tipo' => $f['tipo'], 'situacao' => $f['situacao'] !== 'abertos' ? $f['situacao'] : '', 'busca' => $f['busca'],
                      'categoria' => $f['categoria'], 'de' => $f['de'], 'ate' => $f['ate']], fn($v) => $v !== '');
$link = fn(array $troca) => './financeiro?' . http_build_query(array_filter($troca + $base, fn($v) => $v !== '' && $v !== null));
$voltarLista = 'financeiro?' . http_build_query($base + ($p->pagina > 1 ? ['pagina' => $p->pagina] : []));
$filtrado = $f['busca'] !== '' || $f['categoria'] !== '' || $f['de'] !== '' || $f['ate'] !== '';

$iniciais = function (string $nome): string {
    $p = preg_split('/\s+/', trim($nome));
    return mb_strtoupper(mb_substr($p[0], 0, 1) . (count($p) > 1 ? mb_substr(end($p), 0, 1) : ''));
};
/** Situação mostrada: a do banco, mas conta aberta com vencimento passado aparece como "Atrasada". */
$situacao = function (array $c) use ($feito, $hoje): array {
    if (in_array($c['status'], ['ABERTO', 'PARCIAL'], true) && $c['vencimento'] < $hoje->format('Y-m-d')) return ['Atrasada', 'danger'];
    return match ($c['status']) {
        'PARCIAL' => ['Parcial', 'info'], 'PAGO' => [$feito, 'success'], 'CANCELADO' => ['Cancelada', 'muted'], default => ['Em aberto', 'neutral'],
    };
};
/** "vence hoje", "vence em 3 dias", "atrasada há 5 dias" (só para conta em aberto). */
$prazo = function (string $vencimento) use ($hoje): string {
    $d = (int) $hoje->diff(new DateTimeImmutable($vencimento))->format('%r%a');
    return match (true) {
        $d === 0 => 'vence hoje', $d === 1 => 'vence amanhã', $d > 1 => 'vence em ' . $d . ' dias',
        $d === -1 => 'atrasada há 1 dia', default => 'atrasada há ' . abs($d) . ' dias',
    };
};
$abas = ['abertos' => 'Em aberto', 'vencidos' => 'Vencidas', 'pagos' => $receber ? 'Recebidas' : 'Pagas', 'cancelados' => 'Canceladas', 'todos' => 'Todas'];
$contagem = ['abertos' => $ind['n_aberto'], 'vencidos' => $ind['n_atrasado'], 'pagos' => $ind['pagos'], 'cancelados' => $ind['cancelados'], 'todos' => $ind['todos']];
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Financeiro</h1>
    <p class="page-header__subtitle"><?= $receber ? 'O que os clientes devem à oficina, por vencimento.' : 'Despesas e compromissos da oficina, por vencimento.' ?></p>
  </div>
  <div class="page-header__actions">
    <a class="btn btn--contorno" href="./caixas"><?= icone('history') ?> Histórico de caixa</a>
    <a class="btn btn--ghost" href="./comissoes"><?= icone('percent') ?> Comissões</a>
    <a class="btn btn--primary" href="./conta-form?tipo=<?= e($f['tipo']) ?>"><?= icone('plus') ?> <?= $receber ? 'Conta a receber' : 'Conta a pagar' ?></a>
  </div>
</div>

<nav class="abas abas--contagem" aria-label="Financeiro">
  <a href="./financeiro">Visão geral</a>
  <a href="./financeiro?tipo=RECEBER"<?= $receber ? ' class="ativa" aria-current="page"' : '' ?>>A receber<span class="abas__n"><?= $n($receber ? $ind['n_aberto'] : $outro['n_aberto']) ?></span></a>
  <a href="./financeiro?tipo=PAGAR"<?= $receber ? '' : ' class="ativa" aria-current="page"' ?>>A pagar<span class="abas__n"><?= $n($receber ? $outro['n_aberto'] : $ind['n_aberto']) ?></span></a>
</nav>

<div class="stat-grid stat-grid--icone contas-numeros">
  <a class="stat-card" href="<?= e($link(['situacao' => '', 'de' => '', 'ate' => ''])) ?>">
    <div class="stat-card__topo"><span class="stat-card__label"><?= $receber ? 'Total a receber' : 'Total a pagar' ?></span><?= icone($receber ? 'download' : 'upload') ?></div>
    <span class="stat-card__value"><?= dinheiro($ind['aberto']) ?></span>
    <span class="stat-card__pe"><?= $plural($n($ind['n_aberto']), 'conta em aberto', 'contas em aberto') ?></span>
  </a>
  <a class="stat-card" href="<?= e($link(['situacao' => 'vencidos', 'de' => '', 'ate' => ''])) ?>">
    <div class="stat-card__topo"><span class="stat-card__label">Em atraso</span><?= icone('warning') ?></div>
    <span class="stat-card__value"><?= dinheiro($ind['atrasado']) ?></span>
    <span class="stat-card__pe"><?= $ind['n_atrasado'] ? $plural($n($ind['n_atrasado']), 'conta vencida', 'contas vencidas') : 'nada vencido' ?></span>
  </a>
  <a class="stat-card" href="<?= e($link(['situacao' => '', 'de' => $hoje->format('Y-m-d'), 'ate' => $em7->format('Y-m-d')])) ?>">
    <div class="stat-card__topo"><span class="stat-card__label">Próximos 7 dias</span><?= icone('calendar') ?></div>
    <span class="stat-card__value"><?= dinheiro($ind['proximos']) ?></span>
    <span class="stat-card__pe"><?= $ind['n_proximos'] ? $plural($n($ind['n_proximos']), 'vencimento', 'vencimentos') . ' até ' . $em7->format('d/m') : 'nenhum vencimento' ?></span>
  </a>
  <a class="stat-card" href="<?= e($link(['situacao' => 'pagos', 'de' => '', 'ate' => ''])) ?>">
    <div class="stat-card__topo"><span class="stat-card__label"><?= $feito ?> no mês</span><?= icone('check') ?></div>
    <span class="stat-card__value"><?= dinheiro($ind['realizado']) ?></span>
    <span class="stat-card__pe"><?= $ind['n_realizado'] ? $plural($n($ind['n_realizado']), $receber ? 'recebimento' : 'pagamento', $receber ? 'recebimentos' : 'pagamentos') . ' desde o dia 1º' : ($receber ? 'nenhum recebimento' : 'nenhum pagamento') . ' este mês' ?></span>
  </a>
</div>

<div class="contas-lista">
    <nav class="abas abas--contagem abas--secundarias" aria-label="Situação">
      <?php foreach ($abas as $k => $r): ?>
        <a href="<?= e($link(['situacao' => $k === 'abertos' ? '' : $k])) ?>"<?= $f['situacao'] === $k ? ' class="ativa" aria-current="page"' : '' ?>><?= $r ?><span class="abas__n"><?= $n($contagem[$k]) ?></span></a>
      <?php endforeach; ?>
    </nav>

    <form class="filtros filtros--solto" method="get" action="./financeiro">
      <input type="hidden" name="tipo" value="<?= e($f['tipo']) ?>">
      <?php if ($f['situacao'] !== 'abertos'): ?><input type="hidden" name="situacao" value="<?= e($f['situacao']) ?>"><?php endif; ?>
      <div class="filtros__busca filtros__busca--largo"><?= icone('search') ?><input type="search" name="busca" value="<?= e($f['busca']) ?>" placeholder="Descrição, <?= $receber ? 'cliente' : 'fornecedor, funcionário' ?> ou documento" aria-label="Buscar"></div>
      <select name="categoria" aria-label="Categoria">
        <option value="">Todas as categorias</option>
        <?php foreach (Lancamento::CATEGORIAS[$f['tipo']] as $k => $r): ?><option value="<?= $k ?>" <?= $f['categoria'] === $k ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
      </select>
      <span class="contas-periodo" title="Vencimento">
        <input type="date" name="de" value="<?= e($f['de']) ?>" class="filtros__data" aria-label="Vencimento de" onchange="this.form.submit()">
        <span aria-hidden="true">até</span>
        <input type="date" name="ate" value="<?= e($f['ate']) ?>" class="filtros__data" aria-label="Vencimento até" onchange="this.form.submit()">
      </span>
      <span class="filtros__fim"><?= $p->total ?> <?= $p->total === 1 ? 'conta' : 'contas' ?><?php if ($f['situacao'] !== 'cancelados' && $p->total): ?> · saldo <strong><?= dinheiro($saldo) ?></strong><?php endif; ?></span>
      <?php if ($filtrado): ?><a class="filtro-ativo" href="./financeiro?<?= e(http_build_query(array_filter(['tipo' => $f['tipo'], 'situacao' => $f['situacao'] !== 'abertos' ? $f['situacao'] : '']))) ?>">Limpar filtros <?= icone('close', 12, 2) ?></a><?php endif; ?>
    </form>

    <div class="card card--tabela">
      <?php if (!$contas): ?>
        <div class="vazio"><?= icone('wallet', 32, 1.25) ?><?= $filtrado ? 'Nenhuma conta com esses filtros.' : 'Nenhuma conta nesta situação.' ?></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table tabela-contas">
            <thead><tr>
              <th>Vencimento</th><th>Descrição</th><th><?= $receber ? 'Cliente' : 'Favorecido' ?></th><th>Situação</th>
              <th class="num">Valor</th><th class="num">Saldo</th><th class="num"><span class="sr-only">Ações</span></th>
            </tr></thead>
            <tbody>
              <?php foreach ($contas as $c):
                  [$rotulo, $cor] = $situacao($c);
                  $aberta = in_array($c['status'], ['ABERTO', 'PARCIAL'], true);
                  $atrasada = $cor === 'danger';
                  $saldoConta = round((float) $c['devido'] - (float) $c['valor_pago'], 2);
                  $quem = $c['cliente'] ?? $c['fornecedor'] ?? $c['funcionario'] ?? null;
                  $ver = './conta?id=' . (int) $c['id']; ?>
                <tr data-href="<?= $ver ?>" class="<?= $aberta ? '' : 'inativo' ?><?= $atrasada ? ' is-atrasada' : '' ?>">
                  <td class="tabular">
                    <strong><?= data_br($c['vencimento']) ?></strong>
                    <?php if ($aberta): ?><span class="sub<?= $atrasada ? ' contas-prazo--atraso' : '' ?>"><?= $prazo($c['vencimento']) ?></span><?php endif; ?>
                  </td>
                  <td class="contas-desc">
                    <a href="<?= $ver ?>"><strong><?= e($c['descricao']) ?></strong></a>
                    <span class="sub"><?= e(Lancamento::categoria($c['tipo'], $c['categoria'])) ?><?= $c['parcela_total'] > 1 ? ' · parcela ' . (int) $c['parcela_numero'] . '/' . (int) $c['parcela_total'] : '' ?><?= $c['os_numero'] ? ' · ' . os_numero($c['os_numero']) : '' ?><?= $c['documento'] ? ' · doc. ' . e($c['documento']) : '' ?></span>
                  </td>
                  <td>
                    <?php if ($quem): ?>
                      <span class="celula-pessoa"><span class="avatar"><?= e($iniciais($quem)) ?></span><span><?= e($quem) ?><?php if (!$receber && $c['funcionario'] && !$c['fornecedor']): ?><span class="sub">funcionário</span><?php endif; ?></span></span>
                    <?php else: ?><span class="secundario">—</span><?php endif; ?>
                  </td>
                  <td><span class="badge badge--<?= $cor ?>"><?= $rotulo ?></span><?php if ($atrasada && $c['status'] === 'PARCIAL'): ?><span class="sub">pagamento parcial</span><?php endif; ?></td>
                  <td class="num"><?= dinheiro($c['devido']) ?><?php if ((float) $c['valor_pago'] > 0 && $c['status'] !== 'PAGO'): ?><span class="sub"><?= mb_strtolower($feito) ?> <?= dinheiro($c['valor_pago']) ?></span><?php endif; ?></td>
                  <td class="num"><strong><?= $c['status'] === 'CANCELADO' ? '—' : dinheiro(max(0, $saldoConta)) ?></strong></td>
                  <td class="num">
                    <span class="contas-acoes">
                      <?php if ($aberta): ?>
                        <button type="button" class="btn btn--ghost btn--sm" data-baixar="dlg-baixa" data-acao="./conta-baixar?id=<?= (int) $c['id'] ?>"
                                data-conta="<?= (int) $c['id'] ?>" data-saldo="<?= e(number_format($saldoConta, 2, ',', '.')) ?>" data-forma="<?= e($c['forma_prevista'] ?? '') ?>"
                                data-descricao="<?= e($c['descricao'] . ($quem ? ' · ' . $quem : '')) ?>"
                                data-rotulo="Saldo: <?= e(dinheiro($saldoConta)) ?> · vencimento <?= data_br($c['vencimento']) ?>"><?= icone('cash') ?> <?= $verbo ?></button>
                      <?php endif; ?>
                      <details class="menu-mais">
                        <summary class="btn btn--fantasma btn--icone" aria-label="Ações de <?= e($c['descricao']) ?>"><?= icone('more-h') ?></summary>
                        <div class="menu-mais__lista">
                          <a href="<?= $ver ?>"><?= icone('eye') ?>Abrir conta</a>
                          <?php if ($aberta): ?><a href="<?= $ver ?>#editar"><?= icone('edit') ?>Editar</a><?php endif; ?>
                          <?php if ($c['os_numero']): ?><a href="./os?id=<?= (int) $c['ordem_servico_id'] ?>"><?= icone('work-order') ?>Abrir <?= os_numero($c['os_numero']) ?></a><?php endif; ?>
                          <?php if ($c['status'] === 'ABERTO' && (float) $c['valor_pago'] == 0): ?><a href="<?= $ver ?>#cancelar"><?= icone('cancel') ?>Cancelar conta</a><?php endif; ?>
                        </div>
                      </details>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
      <?php $rotuloItens = $p->total === 1 ? 'conta' : 'contas'; require V2_RAIZ . '/app/views/layout/_rodape.php'; ?>
    </div>
</div>

<?php $dlgId = 'dlg-baixa'; $acao = './conta-baixar'; $extras = []; $tipo = $f['tipo']; require __DIR__ . '/_baixa.php'; ?>
