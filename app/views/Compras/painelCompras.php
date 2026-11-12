<?php
/**
 * Painel de compras (administrador): o que precisa ser reposto, consolidado, e os pedidos em andamento.
 *
 * Espera: $necessidades (solicitadas, abaixo_minimo), $pedidos, $fornecedores, $contagem, $pendentes, $sucesso, $erro.
 */
require_once __DIR__ . '/../../models/PedidoCompra.php';

$e = fn($v) => htmlspecialchars((string) $v);
$plain = fn($v) => str_replace('.', ',', rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.'));
$qtd = fn($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',') ?: '0';
$brl = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$badgeUrgencia = ['URGENTE' => 'badge--danger', 'ALTA' => 'badge--warning', 'NORMAL' => 'badge--neutral', 'BAIXA' => 'badge--neutral'];

$solicitadas = $necessidades['solicitadas'];
$abaixo      = $necessidades['abaixo_minimo'];
$pend        = fn(string $s) => (int) ($pendentes[$s] ?? 0);

$mensagens = ['rascunhocriado' => 'Rascunho criado. Confira os preços e realize o pedido.'];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Compras</h1>
        <p class="page-header__subtitle">Reposição de estoque da oficina. Comprar não altera o estoque — só o recebimento confirmado.</p>
    </div>
    <div class="page-header__actions">
        <a href="./listFornecedores" class="btn btn--ghost">Fornecedores</a>
        <a href="./listPedidosCompra" class="btn btn--ghost">Pedidos</a>
        <a href="./listSolicitacoes" class="btn btn--ghost">Solicitações</a>
    </div>
</div>

<?php if (!empty($mensagens[$sucesso ?? ''])): ?>
    <?php $alertaTipo = 'sucesso'; $alertaMensagem = $mensagens[$sucesso]; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>
<?php if (!empty($erro)): ?>
    <?php $alertaTipo = 'erro'; $alertaMensagem = $erro; require __DIR__ . '/../layout/_alerta.php'; ?>
<?php endif; ?>

<section class="stat-grid">
    <?php foreach ([
        ['Solicitações novas', $pend('SOLICITADA'), 'b3'],
        ['Em análise / aprovadas', $pend('EM_ANALISE') + $pend('APROVADA_COMPRA'), 'b2'],
        ['Pedidos aguardando mercadoria', (int) ($contagem['REALIZADO'] ?? 0) + (int) ($contagem['PARCIALMENTE_RECEBIDO'] ?? 0), 'b1'],
        ['Peças no mínimo', count($abaixo), 'b4'],
    ] as [$rotulo, $valor, $cor]): ?>
        <div class="stat-card">
            <div class="stat-card__head"><div class="stat-card__label"><?= $e($rotulo) ?></div><div class="stat-card__icon <?= $cor ?>"><i class="bi bi-cart3"></i></div></div>
            <div class="stat-card__value"><?= (int) $valor ?></div>
        </div>
    <?php endforeach; ?>
</section>

<form action="./criarPedidoDeNecessidades" method="POST">
    <?= csrf_field() ?>

    <section class="card" style="margin-bottom:16px;">
        <div class="card__header">
            <div class="card__title">Necessidades de reposição</div>
            <span class="text-muted fs-12">Marque o que vai entrar no pedido e escolha o fornecedor.</span>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th style="width:36px;"></th><th>Peça</th><th>Solicitações</th><th style="text-align:right;">Estoque</th><th>Urgência</th><th style="width:110px;">Quantidade</th><th style="width:120px;">Preço un. (R$)</th></tr>
                </thead>
                <tbody>
                <?php $i = 0; ?>
                <?php if (empty($solicitadas) && empty($abaixo)): ?>
                    <tr><td colspan="7" style="text-align:center;padding:40px 20px;color:var(--color-text-muted);">Nada a repor no momento: sem solicitações pendentes e nenhuma peça no mínimo.</td></tr>
                <?php endif; ?>

                <?php foreach ($solicitadas as $n): $cadastrada = !empty($n['produto_id']); ?>
                    <tr>
                        <td>
                            <?php if ($cadastrada): ?><input type="checkbox" name="sel[]" value="<?= $i ?>" aria-label="Incluir no pedido">
                            <?php else: ?><span title="Cadastre o produto para poder comprá-lo">—</span><?php endif; ?>
                        </td>
                        <td>
                            <strong><?= $e($n['nome']) ?></strong>
                            <?php if (!$cadastrada): ?><span class="badge badge--warning">Sem cadastro</span> <a class="fs-12" href="./saveProduto">cadastrar</a><?php endif; ?>
                            <?php if (!empty($n['codigo_interno'])): ?><div class="text-muted fs-12"><?= $e($n['codigo_interno']) ?></div><?php endif; ?>
                        </td>
                        <td class="fs-12">
                            <?php foreach (explode(',', (string) $n['ids']) as $k => $sid): ?>
                                <a href="./viewSolicitacao?id=<?= (int) $sid ?>"><?= $e(explode(', ', (string) $n['numeros'])[$k] ?? '#' . $sid) ?></a><?= $k < $n['qtd_solicitacoes'] - 1 ? ', ' : '' ?>
                            <?php endforeach; ?>
                        </td>
                        <td style="text-align:right;">
                            <?= $n['estoque_atual'] !== null ? $qtd($n['estoque_atual']) : '—' ?>
                            <?php if ((float) $n['reservado'] > 0): ?><div class="text-muted fs-12">reservado <?= $qtd($n['reservado']) ?></div><?php endif; ?>
                        </td>
                        <td><span class="badge <?= $badgeUrgencia[$n['urgencia']] ?? 'badge--neutral' ?>"><?= $e(SolicitacaoPeca::URGENCIAS[$n['urgencia']] ?? $n['urgencia']) ?></span></td>
                        <td>
                            <input type="hidden" name="linha[<?= $i ?>][produto_id]" value="<?= (int) ($n['produto_id'] ?? 0) ?>">
                            <input type="hidden" name="linha[<?= $i ?>][solicitacoes]" value="<?= $e($n['ids']) ?>">
                            <input type="text" inputmode="decimal" name="linha[<?= $i ?>][quantidade]" value="<?= $e($plain($n['quantidade'])) ?>" <?= $cadastrada ? '' : 'disabled' ?> style="width:100%;">
                        </td>
                        <td><input type="text" inputmode="decimal" name="linha[<?= $i ?>][preco]" placeholder="último custo" <?= $cadastrada ? '' : 'disabled' ?> style="width:100%;"></td>
                    </tr>
                    <?php $i++; ?>
                <?php endforeach; ?>

                <?php if (!empty($abaixo)): ?>
                    <tr><td colspan="7" style="background:var(--color-bg-alt);font-size:12px;font-weight:600;color:var(--color-text-muted);">No estoque mínimo, sem solicitação</td></tr>
                    <?php foreach ($abaixo as $a): $sugerida = max(1, (float) $a['estoque_minimo'] - ((float) $a['estoque_atual'] - (float) $a['reservado'])); ?>
                        <tr>
                            <td><input type="checkbox" name="sel[]" value="<?= $i ?>" aria-label="Incluir no pedido"></td>
                            <td><strong><?= $e($a['nome']) ?></strong><div class="text-muted fs-12"><?= $e($a['codigo_interno']) ?></div></td>
                            <td class="text-muted fs-12">—</td>
                            <td style="text-align:right;"><?= $qtd($a['estoque_atual']) ?><div class="text-muted fs-12">mínimo <?= $qtd($a['estoque_minimo']) ?><?= (float) $a['reservado'] > 0 ? ' · reservado ' . $qtd($a['reservado']) : '' ?></div></td>
                            <td><span class="badge badge--warning">Acabando</span></td>
                            <td>
                                <input type="hidden" name="linha[<?= $i ?>][produto_id]" value="<?= (int) $a['produto_id'] ?>">
                                <input type="hidden" name="linha[<?= $i ?>][solicitacoes]" value="">
                                <input type="text" inputmode="decimal" name="linha[<?= $i ?>][quantidade]" value="<?= $e($plain($sugerida)) ?>" style="width:100%;">
                            </td>
                            <td><input type="text" inputmode="decimal" name="linha[<?= $i ?>][preco]" placeholder="último custo" style="width:100%;"></td>
                        </tr>
                        <?php $i++; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($i > 0): ?>
            <div class="card__body" style="padding:14px 20px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;border-top:1px solid var(--color-border);">
                <div class="form-field" style="min-width:260px;margin:0;">
                    <label for="fornecedor_id">Fornecedor do pedido</label>
                    <select name="fornecedor_id" id="fornecedor_id" data-no-ts required>
                        <option value="">Escolha...</option>
                        <?php foreach ($fornecedores as $f): if (!(int) $f['ativo']) continue; ?>
                            <option value="<?= (int) $f['id_fornecedor'] ?>"><?= $e($f['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn--primary">Montar pedido com o marcado</button>
                <?php if (empty($fornecedores)): ?><span class="text-muted fs-12">Cadastre um fornecedor primeiro: <a href="./saveFornecedor">novo fornecedor</a>.</span><?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</form>

<section class="card" style="margin-bottom:16px;">
    <div class="card__header"><div class="card__title">Pedidos em andamento</div><a class="fs-12" href="./listPedidosCompra">ver todos</a></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Nº</th><th>Fornecedor</th><th>Itens</th><th style="text-align:right;">Total</th><th>Situação</th><th>Recebido</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($pedidos)): ?>
                <tr><td colspan="7" style="text-align:center;padding:30px 20px;color:var(--color-text-muted);">Nenhum pedido em andamento.</td></tr>
            <?php else: foreach ($pedidos as $p): ?>
                <tr>
                    <td class="id-col"><?= $e($p['numero']) ?></td>
                    <td><?= $e($p['fornecedor_nome']) ?></td>
                    <td><?= (int) $p['qtd_itens'] ?></td>
                    <td style="text-align:right;"><?= $brl($p['total']) ?></td>
                    <td><span class="badge <?= PedidoCompra::badge($p['status']) ?>"><?= $e(PedidoCompra::rotulo($p['status'])) ?></span></td>
                    <td class="text-muted"><?= $qtd($p['qtd_recebida']) ?> / <?= $qtd($p['qtd_pedida']) ?></td>
                    <td><a class="btn btn--ghost btn--sm" href="./viewPedidoCompra?id=<?= (int) $p['id_pedido'] ?>">Abrir</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <div class="card__header"><div class="card__title">Como comprar</div></div>
    <div class="card__body" style="padding:16px 20px;font-size:13px;">
        <p style="margin:0 0 8px;">
            O Mecanize <strong>não faz a compra automaticamente</strong> em nenhum fornecedor. As distribuidoras que a oficina usa
            (por exemplo DPK, DPL) e o Mercado Livre não têm API pública de compra que a oficina possa usar — a verificação está
            registrada em <em>MELHORIAS_2026-10-08.md</em>. O fluxo é:
        </p>
        <ol style="margin:0;padding-left:20px;display:flex;flex-direction:column;gap:4px;">
            <li>Pesquise o preço no site/telefone do fornecedor e registre a <strong>cotação</strong> na solicitação (ou monte o pedido direto).</li>
            <li>Faça o pedido no site do fornecedor e anote aqui o <strong>número do pedido</strong> dele no campo de referência.</li>
            <li>Quando a mercadoria chegar, registre o <strong>recebimento</strong> (parcial ou total): só então o estoque sobe.</li>
        </ol>
        <?php $comSite = array_filter($fornecedores, fn($f) => !empty($f['site_url']) && (int) $f['ativo']); ?>
        <?php if ($comSite): ?>
            <p style="margin:12px 0 0;">Abrir o site de:
                <?php foreach ($comSite as $f): ?>
                    <a class="btn btn--ghost btn--sm" href="<?= $e($f['site_url']) ?>" target="_blank" rel="noopener noreferrer"><?= $e($f['nome']) ?></a>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
    </div>
</section>
