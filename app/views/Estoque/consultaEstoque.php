<?php
/**
 * Consulta de estoque (equipe): existe? onde fica? quanto está prometido a outras OS?
 * Sem custo nem valor de inventário — isso é do cadastro de produtos (administrador).
 *
 * Espera: $linhas, $contagem (OK/BAIXO/SEM_ESTOQUE), $categorias, $busca, $categoria, $situacao.
 */
$esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$qtd = fn($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',') ?: '0';

if (!isset($busca)) {
    $busca = '';
}

if (!isset($categoria)) {
    $categoria = '';
}

if (!isset($situacao)) {
    $situacao = null;
}

if (!isset($categorias) || !is_array($categorias)) {
    $categorias = [];
}

if (!isset($contagem) || !is_array($contagem)) {
    $contagem = ['OK' => 0, 'BAIXO' => 0, 'SEM_ESTOQUE' => 0];
}

$contagem = array_merge(['OK' => 0, 'BAIXO' => 0, 'SEM_ESTOQUE' => 0], $contagem);

$rotuloSituacao = ['OK' => ['Disponível', 'badge--success'], 'BAIXO' => ['Acabando', 'badge--warning'], 'SEM_ESTOQUE' => ['Sem estoque', 'badge--danger']];

$filtroUrl = fn(array $extra) => './consultaEstoque?' . http_build_query(array_filter(
    array_merge(['busca' => $busca, 'categoria' => $categoria ?: null, 'situacao' => $situacao], $extra),
    fn($v) => $v !== null && $v !== ''
));
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Consulta de estoque</h1>
        <p class="page-header__subtitle">O que há na prateleira e o que já está prometido a ordens de serviço.</p>
    </div>
    <div class="page-header__actions">
        <a href="./listSolicitacoes?minhas=1" class="btn btn--ghost">Minhas solicitações</a>
        <a href="./saveSolicitacao" class="btn btn--primary">Solicitar peça</a>
    </div>
</div>

<section class="stat-grid">
    <?php foreach ([
        ['Disponíveis', $contagem['OK'], 'b1', 'OK'],
        ['Acabando', $contagem['BAIXO'], 'b3', 'BAIXO'],
        ['Sem estoque', $contagem['SEM_ESTOQUE'], 'b4', 'SEM_ESTOQUE'],
    ] as [$rotulo, $valor, $cor, $chave]): ?>
        <?php
            $iconeClasse = match ($cor) {
                'b1' => 'b1',
                'b3' => 'b3',
                'b4' => 'b4',
                default => 'b1',
            };
        ?>
        <a class="stat-card" href="<?= $esc($filtroUrl(['situacao' => $situacao === $chave ? null : $chave])) ?>" style="text-decoration:none;color:inherit;<?= $situacao === $chave ? 'outline:2px solid var(--color-primary);' : '' ?>">
            <div class="stat-card__head">
                <div class="stat-card__label"><?= $esc($rotulo) ?></div>
                <div class="stat-card__icon <?= $esc($iconeClasse) ?>"><i class="bi bi-box-seam"></i></div>
            </div>
            <div class="stat-card__value"><?= (int) $valor ?></div>
        </a>
    <?php endforeach; ?>
</section>

<section class="card">
    <form method="GET" action="./consultaEstoque" class="table-toolbar">
        <div class="search-wrap">
            <input type="text" name="busca" value="<?= $esc($busca) ?>" placeholder="Nome, código, marca, aplicação ou local...">
        </div>
        <select name="categoria" class="toolbar-select" onchange="this.form.submit()">
            <option value="">Todas as categorias</option>
            <?php foreach ($categorias as $c): ?>
                <option value="<?= (int) $c['id_categoria'] ?>" <?= (int) $categoria === (int) $c['id_categoria'] ? 'selected' : '' ?>><?= $esc($c['nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="situacao" class="toolbar-select" onchange="this.form.submit()">
            <option value="">Qualquer situação</option>
            <?php foreach ($rotuloSituacao as $chave => [$rotulo]): ?>
                <option value="<?= $chave ?>" <?= $situacao === $chave ? 'selected' : '' ?>><?= $esc($rotulo) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="text-muted fs-12"><?= count(is_array($linhas) ? $linhas : []) ?> peça(s)</span>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Peça</th>
                    <th>Local</th>
                    <th style="text-align:right;">Em estoque</th>
                    <th style="text-align:right;">Reservado</th>
                    <th style="text-align:right;">Disponível</th>
                    <th>Situação</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($linhas)): ?>
                    <tr><td colspan="7" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">Nenhuma peça encontrada.</td></tr>
                <?php else: foreach (is_array($linhas) ? $linhas : [] as $l): [$rotuloSit, $badgeSit] = $rotuloSituacao[$l['situacao']]; ?>
                    <tr>
                        <td>
                            <strong><?= $esc($l['nome']) ?></strong>
                            <div class="text-muted fs-12">
                                <?= $esc($l['codigo_interno']) ?>
                                <?php if (!empty($l['marca'])): ?> · <?= $esc($l['marca']) ?><?php endif; ?>
                                <?php if (!empty($l['categoria'])): ?> · <?= $esc($l['categoria']) ?><?php endif; ?>
                            </div>
                            <?php if (!empty($l['aplicacao'])): ?><div class="text-muted fs-12"><?= $esc($l['aplicacao']) ?></div><?php endif; ?>
                        </td>
                        <td class="text-muted"><?= $esc($l['localizacao']) ?></td>
                        <td style="text-align:right;"><?= $qtd($l['estoque']) ?></td>
                        <td style="text-align:right;"<?= (float) $l['reservado'] > 0 ? '' : ' class="text-muted"' ?>><?= $qtd($l['reservado']) ?></td>
                        <td style="text-align:right;"><strong><?= $qtd($l['disponivel']) ?></strong>
                            <?php if ((float) $l['estoque_minimo'] > 0): ?><div class="text-muted fs-12">mín. <?= $qtd($l['estoque_minimo']) ?></div><?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $esc($badgeSit) ?>">
                                <span class="badge__dot"></span>
                                <?= $esc($rotuloSit) ?>
                            </span>
                            <?php if ((int) $l['solicitacoes_abertas'] > 0): ?>
                                <div class="fs-12"><a href="./listSolicitacoes?busca=<?= urlencode($l['nome']) ?>"><?= (int) $l['solicitacoes_abertas'] ?> solicitação(ões) em aberto</a></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="btn btn--ghost btn--sm" href="./saveSolicitacao?produto_id=<?= (int) $l['id_produto'] ?>">Solicitar</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
