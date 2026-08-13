<?php
/**
 * Miolo da tela de clientes: toolbar de busca, tabela e paginação.
 *
 * Renderizado de dois jeitos, com os mesmos dados:
 *   - embutido em listClientes.php (carga normal da página);
 *   - sozinho, sem layout, quando ClienteController::list() recebe
 *     ?fragmento=1 (troca de aba / paginação / busca via fetch).
 *
 * Por isso ele não depende de nada calculado na página que o contém.
 *
 * Espera: $clientes, $totalResultados, $paginaAtual, $totalPaginas, $aba
 */
$clientes        = $clientes        ?? [];
$totalResultados = (int) ($totalResultados ?? 0);
$totalPaginas    = max(1, (int) ($totalPaginas ?? 1));
$paginaAtual     = max(1, (int) ($paginaAtual  ?? 1));
$aba             = $aba             ?? 'ativos';

$ehInativos = ($aba === 'inativos');

$buscaAtual = $_GET['busca'] ?? '';

// O status virou aba, então a busca é o único filtro da toolbar.
$temFiltro = ($buscaAtual !== '');

/**
 * Monta uma URL da própria tela preservando aba/busca.
 * `fragmento` fica de fora de propósito: se um destes links for seguido como
 * navegação normal (JS desligado, abrir em nova aba), tem que devolver a
 * página inteira, não o pedaço.
 */
$link = function (array $extra = []) use ($aba, $buscaAtual) {
    $query = array_filter([
        'aba'   => $aba,
        'busca' => $buscaAtual,
    ] + $extra, fn($v) => $v !== '' && $v !== null);

    return './listClientes?' . http_build_query($query);
};
?>

<!-- Toolbar / Filtros -->
<form method="GET" action="./listClientes" class="table-toolbar" data-clientes-filtro>
    <input type="hidden" name="aba" value="<?= htmlspecialchars($aba) ?>">

    <div class="search-wrap">
        <input
            type="text"
            name="busca"
            value="<?= htmlspecialchars($buscaAtual) ?>"
            placeholder="Buscar por nome, telefone ou e-mail..." />
    </div>

    <button type="submit" class="btn btn--ghost">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
        </svg>
        Filtrar
    </button>

    <?php if ($temFiltro): ?>
        <a href="<?= htmlspecialchars('./listClientes?aba=' . $aba) ?>" class="btn btn--ghost" data-clientes-link style="color:var(--color-danger);">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
            Limpar
        </a>
    <?php endif; ?>

    <span style="margin-left:auto;font-size:12px;color:var(--color-text-muted);white-space:nowrap;">
        <?= count($clientes) ?> resultado(s)
    </span>
</form>

<!-- Tabela de clientes -->
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Contato</th>
                <th>Veículos Cadastrados</th>
                <th>Última Visita</th>
                <th><?= $ehInativos ? 'Desativado em' : 'Status' ?></th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($clientes)): ?>
                <?php foreach ($clientes as $cliente): ?>
                    <tr<?= $ehInativos ? ' style="opacity:.75;"' : '' ?>>
                        <!-- Cliente -->
                        <td>
                            <div class="user-cell">
                                <div class="avatar"<?= $ehInativos ? ' style="background:var(--color-border);color:var(--color-text-muted);"' : '' ?>>
                                    <?= htmlspecialchars(mb_strtoupper(mb_substr($cliente['nome'] ?? '', 0, 2))) ?>
                                </div>
                                <div>
                                    <div class="user-cell__name"<?= $ehInativos ? ' style="color:var(--color-text-muted);text-decoration:line-through;"' : '' ?>>
                                        <?= htmlspecialchars($cliente['nome'] ?? '-') ?>
                                    </div>
                                    <div class="user-cell__sub">
                                        <?= !empty($cliente['cpf_cnpj']) ? htmlspecialchars($cliente['cpf_cnpj']) : 'Sem documento' ?>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Contato -->
                        <td>
                            <div style="font-size:13px;line-height:1.8;">
                                <div class="flex items-center gap-8">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.44C2.11 2.09 3.32 1 4.7 1h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L9.91 8.09a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 24 14.91v3.01z" />
                                    </svg>
                                    <?= htmlspecialchars($cliente['telefone'] ?: 'Sem telefone') ?>
                                </div>
                                <div class="text-muted fs-12">
                                    <?= htmlspecialchars($cliente['email'] ?: 'Sem e-mail') ?>
                                </div>
                            </div>
                        </td>

                        <!-- Veículos -->
                        <td>
                            <?php if (!empty($cliente['veiculo_principal'])): ?>
                                <?= htmlspecialchars($cliente['veiculo_principal']) ?>
                                <?php if ((int) $cliente['total_veiculos'] > 1): ?>
                                    <span class="badge badge--neutral">+<?= ((int) $cliente['total_veiculos']) - 1 ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">Nenhum veículo</span>
                            <?php endif; ?>
                        </td>

                        <!-- Última Visita -->
                        <td>
                            <?php if (!empty($cliente['ultima_visita'])): ?>
                                <?= date('d/m/Y', strtotime($cliente['ultima_visita'])) ?>
                            <?php else: ?>
                                <span class="text-muted">Sem visita</span>
                            <?php endif; ?>
                        </td>

                        <!-- Status (ativos) / Desativado em (inativos) -->
                        <td>
                            <?php if ($ehInativos): ?>
                                <?php if (!empty($cliente['desativado_em'])): ?>
                                    <span style="font-size:13px;color:var(--color-text-muted);">
                                        <?= date('d/m/Y', strtotime($cliente['desativado_em'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge badge--success">
                                    <span class="badge__dot"></span>
                                    Ativo
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Ações -->
                        <td>
                            <div class="row-actions">
                                <?php if ($ehInativos && ($_SESSION['user_tipo'] ?? '') === 'ADMIN'): ?>
                                    <form method="post" action="./reativarCliente" style="display:inline;"
                                        onsubmit="return confirm('Deseja reativar este cliente?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $cliente['id_cliente'] ?>">
                                        <button type="submit" class="row-action-btn primary" title="Reativar cliente">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="17 1 21 5 17 9" />
                                                <path d="M3 11V9a4 4 0 0 1 4-4h14" />
                                                <polyline points="7 23 3 19 7 15" />
                                                <path d="M21 13v2a4 4 0 0 1-4 4H3" />
                                            </svg>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <a href="./viewCliente?id=<?= (int) $cliente['id_cliente'] ?>" class="row-action-btn primary" title="Visualizar">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </a>

                                <?php if (!$ehInativos): ?>
                                    <a href="./editCliente?id=<?= (int) $cliente['id_cliente'] ?>" class="row-action-btn" title="Editar">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                        </svg>
                                    </a>

                                    <?php if (($_SESSION['user_tipo'] ?? '') === 'ADMIN'): ?>
                                        <form method="post" action="./deleteCliente" style="display:inline;"
                                            onsubmit="return confirm('Deseja desativar este cliente? Ele não será excluído permanentemente.')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $cliente['id_cliente'] ?>">
                                            <button type="submit" class="row-action-btn danger" title="Desativar">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="3 6 5 6 21 6" />
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                                </svg>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align:center;padding:56px 20px;color:var(--color-text-muted);">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 12px;display:block;opacity:.4;">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                        <div style="font-weight:600;color:var(--color-text-secondary);margin-bottom:4px;">
                            <?= $temFiltro
                                ? 'Nenhum cliente encontrado'
                                : ($ehInativos ? 'Nenhum cliente desativado' : 'Nenhum cliente ativo') ?>
                        </div>
                        <div style="font-size:12px;margin-bottom:16px;">
                            <?php if ($temFiltro): ?>
                                Tente ajustar a busca.
                            <?php elseif ($ehInativos): ?>
                                Clientes desativados aparecem aqui.
                            <?php else: ?>
                                Comece cadastrando o primeiro cliente da oficina.
                            <?php endif; ?>
                        </div>
                        <?php if ($temFiltro): ?>
                            <a href="<?= htmlspecialchars('./listClientes?aba=' . $aba) ?>" class="btn btn--ghost btn--sm" data-clientes-link>Limpar busca</a>
                        <?php elseif (!$ehInativos): ?>
                            <a href="./saveCliente" class="btn btn--primary btn--sm">Novo Cliente</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Paginação -->
<div class="pagination table-footer">
    <span>
        Mostrando <?= count($clientes) ?> de <?= $totalResultados ?> resultados
    </span>

    <?php if ($totalPaginas > 1): ?>
        <div class="pagination__pages">
            <?php
            // <a> em vez de button+window.location: o JS intercepta pra trocar
            // só a tabela, e sem JS o link continua navegando normalmente.
            $botao = function (int $pagina, string $rotulo, bool $desabilitado = false, bool $ativoBtn = false) use ($link) {
                if ($desabilitado) {
                    return '<span class="pagination__btn" style="opacity:.45;cursor:default;">' . $rotulo . '</span>';
                }

                return '<a class="pagination__btn' . ($ativoBtn ? ' is-active' : '') . '"'
                    . ' href="' . htmlspecialchars($link(['pagina' => $pagina])) . '" data-clientes-link>'
                    . $rotulo . '</a>';
            };

            $inicio = max(1, $paginaAtual - 1);
            $fim    = min($totalPaginas, $paginaAtual + 1);

            echo $botao($paginaAtual - 1, '‹', $paginaAtual <= 1);

            for ($p = $inicio; $p <= $fim; $p++) {
                echo $botao($p, (string) $p, false, $p === $paginaAtual);
            }

            echo $botao($paginaAtual + 1, '›', $paginaAtual >= $totalPaginas);
            ?>
        </div>
    <?php endif; ?>
</div>
