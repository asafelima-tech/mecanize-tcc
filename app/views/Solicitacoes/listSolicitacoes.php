<?php
/**
 * Solicitações de peça. Espera: $solicitacoes, $contagem, $aba, $busca, $urgencia, $minhas, $ehAdmin, $sucesso.
 */
require_once __DIR__ . '/../../models/SolicitacaoPeca.php';

$e = fn($v) => htmlspecialchars((string) $v);
$qtd = fn($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',') ?: '0';
$n = fn(string $s) => (int) ($contagem[$s] ?? 0);
$abertas = array_sum(array_map($n, SolicitacaoPeca::ABERTAS));

$abas = ['abertas' => ['Em aberto', $abertas]];
foreach (SolicitacaoPeca::STATUS_LABEL as $chave => $rotulo) {
    $abas[$chave] = [$rotulo, $n($chave)];
}
$abas['todas'] = ['Todas', array_sum($contagem)];

$badgeUrgencia = ['URGENTE' => 'badge--danger', 'ALTA' => 'badge--warning', 'NORMAL' => 'badge--neutral', 'BAIXA' => 'badge--neutral'];

$mensagens = ['registrada' => 'Solicitação registrada. O administrador foi avisado.', 'cancelada' => 'Solicitação cancelada.'];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Solicitação de peças</h1>
        <p class="page-header__subtitle">Peças e materiais acabando ou indisponíveis. Isto é o pedido de reposição — a compra e a entrada no estoque são etapas do administrador.</p>
    </div>
    <div class="page-header__actions">
        <a href="./consultaEstoque" class="btn btn--ghost">Consulta de estoque</a>
        <a href="./saveSolicitacao" class="btn btn--primary">Solicitar peça</a>
    </div>
</div>

<?php if (!empty($mensagens[$sucesso ?? ''])): ?>
    <?php $alertaTipo = 'sucesso'; $alertaMensagem = $mensagens[$sucesso]; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<section class="card">
    <div class="tabs" style="overflow-x:auto;">
        <?php foreach ($abas as $chave => [$rotulo, $q]): ?>
            <a class="tabs__item <?= $aba === $chave ? 'is-active' : '' ?>" href="./listSolicitacoes?aba=<?= urlencode($chave) ?><?= $minhas ? '&minhas=1' : '' ?>" style="text-decoration:none;white-space:nowrap;">
                <?= $e($rotulo) ?><?php if ($q > 0): ?> <span class="text-muted fs-12">(<?= (int) $q ?>)</span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <form method="GET" action="./listSolicitacoes" class="table-toolbar">
        <input type="hidden" name="aba" value="<?= $e($aba) ?>">
        <div class="search-wrap">
            <input type="text" name="busca" value="<?= $e($busca) ?>" placeholder="Buscar por número, peça ou OS...">
        </div>
        <select name="urgencia" class="toolbar-select" onchange="this.form.submit()">
            <option value="">Urgência: todas</option>
            <?php foreach (SolicitacaoPeca::URGENCIAS as $chave => $rotulo): ?>
                <option value="<?= $chave ?>" <?= $urgencia === $chave ? 'selected' : '' ?>><?= $e($rotulo) ?></option>
            <?php endforeach; ?>
        </select>
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
            <input type="checkbox" name="minhas" value="1" <?= $minhas ? 'checked' : '' ?> onchange="this.form.submit()"> Só as minhas
        </label>
        <span class="text-muted fs-12"><?= count($solicitacoes) ?> resultado(s)</span>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Nº</th><th>Peça</th><th style="text-align:right;">Quantidade</th><th>Urgência</th><th>OS</th><th>Solicitante</th><th>Pedida em</th><th>Situação</th><th></th></tr>
            </thead>
            <tbody>
                <?php if (empty($solicitacoes)): ?>
                    <tr><td colspan="9" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">Nenhuma solicitação neste filtro.</td></tr>
                <?php else: foreach ($solicitacoes as $s): ?>
                    <tr>
                        <td class="id-col"><?= $e($s['numero']) ?></td>
                        <td>
                            <?= $e($s['descricao']) ?>
                            <?php if (empty($s['produto_id'])): ?><span class="badge badge--warning" title="Peça fora do cadastro de produtos">Sem cadastro</span><?php endif; ?>
                            <div class="text-muted fs-12" style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= $e($s['motivo']) ?></div>
                        </td>
                        <td style="text-align:right;">
                            <?= $qtd($s['quantidade']) ?>
                            <?php if ((float) $s['quantidade_recebida'] > 0): ?><div class="text-muted fs-12">recebido <?= $qtd($s['quantidade_recebida']) ?></div><?php endif; ?>
                        </td>
                        <td><span class="badge <?= $badgeUrgencia[$s['urgencia']] ?? 'badge--neutral' ?>"><?= $e(SolicitacaoPeca::URGENCIAS[$s['urgencia']] ?? $s['urgencia']) ?></span></td>
                        <td class="text-muted"><?= $s['os_numero'] ? $e($s['os_numero']) : '—' ?></td>
                        <td class="text-muted"><?= $e($s['solicitante_nome']) ?></td>
                        <td class="text-muted"><?= date('d/m H:i', strtotime($s['criada_em'])) ?></td>
                        <td><span class="badge <?= SolicitacaoPeca::badge($s['status']) ?>"><span class="badge__dot"></span><?= $e(SolicitacaoPeca::rotulo($s['status'])) ?></span></td>
                        <td><a class="btn btn--ghost btn--sm" href="./viewSolicitacao?id=<?= (int) $s['id_solicitacao'] ?>">Abrir</a></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
