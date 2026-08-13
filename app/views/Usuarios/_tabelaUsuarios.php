<?php
/**
 * Miolo da tela de usuários: toolbar de filtros, tabela e paginação.
 *
 * Renderizado de dois jeitos, com os mesmos dados:
 *   - embutido em listUsuarios.php (carga normal da página);
 *   - sozinho, sem layout, quando UserController::list() recebe ?fragmento=1
 *     (troca de aba / paginação / busca via fetch).
 *
 * Por isso ele não depende de nada calculado na página que o contém.
 *
 * Espera: $usuarios, $totalUsuarios, $paginaAtual, $totalPaginas, $aba
 */
$usuarios     = $usuarios     ?? [];
$totalPaginas = $totalPaginas ?? 1;
$paginaAtual  = $paginaAtual  ?? 1;
$aba          = $aba          ?? 'ativos';

$buscaAtual = $_GET['busca'] ?? '';
$tipoAtual  = $_GET['tipo']  ?? '';

// O status virou aba, então não conta como filtro da toolbar.
$temFiltro = ($buscaAtual !== '' || $tipoAtual !== '');

/**
 * Monta uma URL da própria tela preservando aba/busca/tipo.
 * `fragmento` fica de fora de propósito: se um destes links for seguido como
 * navegação normal (JS desligado, abrir em nova aba), tem que devolver a
 * página inteira, não o pedaço. Os avisos de erro/sucesso da exclusão também
 * saem, pra não reaparecerem ao paginar.
 */
$link = function (array $extra = []) use ($aba, $buscaAtual, $tipoAtual) {
    $query = array_filter([
        'aba'   => $aba,
        'busca' => $buscaAtual,
        'tipo'  => $tipoAtual,
    ] + $extra, fn($v) => $v !== '' && $v !== null);

    return './listUsuarios?' . http_build_query($query);
};
?>

<!-- Toolbar / Filtros -->
<form method="GET" action="./listUsuarios" class="table-toolbar" data-usuarios-filtro>
    <input type="hidden" name="aba" value="<?= htmlspecialchars($aba) ?>">

    <div class="search-wrap">
        <input
            type="text"
            name="busca"
            value="<?= htmlspecialchars($buscaAtual) ?>"
            placeholder="Buscar por nome ou e-mail..." />
    </div>

    <select name="tipo" class="toolbar-select" data-no-ts onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
        <option value="">Tipo: Todos</option>
        <option value="ADMIN" <?= ($tipoAtual === 'ADMIN') ? 'selected' : '' ?>>Admin</option>
        <option value="TECNICO" <?= ($tipoAtual === 'TECNICO') ? 'selected' : '' ?>>Técnico</option>
        <option value="ATENDENTE" <?= ($tipoAtual === 'ATENDENTE') ? 'selected' : '' ?>>Atendente</option>
    </select>

    <button type="submit" class="btn btn--ghost">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
        </svg>
        Filtrar
    </button>

    <?php if ($temFiltro): ?>
        <a href="./listUsuarios?aba=<?= htmlspecialchars($aba) ?>" class="btn btn--ghost" data-usuarios-link style="color:var(--color-danger);">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
            Limpar
        </a>
    <?php endif; ?>

    <span style="margin-left:auto;font-size:12px;color:var(--color-text-muted);white-space:nowrap;">
        <?= count($usuarios) ?> resultado(s)
    </span>

</form>

<!-- Tabela de usuários -->
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Usuário</th>
                <th>Contato</th>
                <th>Tipo</th>
                <th>Status</th>
                <th>Criado em</th>
                <th class="text-right">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($usuarios)): ?>
                <?php foreach ($usuarios as $usuario): ?>
                    <?php
                    $iniciais = strtoupper(implode('', array_map(
                        fn($p) => $p[0],
                        array_slice(explode(' ', trim($usuario['nome'] ?? '')), 0, 2)
                    )));
                    $ativo = (int)($usuario['ativo'] ?? 0);

                    // Cor de avatar por tipo — reforça a hierarquia visual sem
                    // sair da paleta azul monocromática do design system.
                    $avatarStyle = match ($usuario['tipo'] ?? '') {
                        'ADMIN'   => 'background:linear-gradient(135deg,#111826 0%,#1E293B 100%);',
                        'TECNICO' => '',
                        default   => 'background:linear-gradient(135deg,#F59E0B 0%,#D97706 100%);',
                    };
                    ?>
                    <tr>
                        <!-- Usuário -->
                        <td>
                            <div class="user-cell">
                                <div class="avatar avatar--sm" style="<?= $avatarStyle ?>"><?= htmlspecialchars($iniciais) ?></div>
                                <div>
                                    <div class="user-cell__name"><?= htmlspecialchars($usuario['nome'] ?? '-') ?></div>
                                    <div class="user-cell__sub">#<?= htmlspecialchars($usuario['id_usuario'] ?? '') ?></div>
                                </div>
                            </div>
                        </td>

                        <!-- Contato -->
                        <td>
                            <div style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--color-text-muted);">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                    <polyline points="22,6 12,13 2,6" />
                                </svg>
                                <?= htmlspecialchars($usuario['email'] ?? '-') ?>
                            </div>
                        </td>

                        <!-- Tipo -->
                        <td>
                            <?php if (($usuario['tipo'] ?? '') === 'ADMIN'): ?>
                                <span class="badge badge--info">
                                    <span class="badge__dot"></span>Admin
                                </span>
                            <?php elseif (($usuario['tipo'] ?? '') === 'TECNICO'): ?>
                                <span class="badge badge--secondary">
                                    <span class="badge__dot"></span>Técnico
                                </span>
                            <?php else: ?>
                                <span class="badge badge--warning">
                                    <span class="badge__dot"></span>Atendente
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Status (clicável: ativa/desativa) -->
                        <td>
                            <!--
                              Continua sendo o mesmo badge clicável de sempre; o que
                              mudou é que agora envia um POST em vez de abrir um link.
                              Ativar/desativar muda dado, e só o POST passa pelo
                              controle de CSRF do front-controller.
                            -->
                            <form method="post" action="./toggleStatusUsuario" style="display:inline;"
                                onsubmit="return confirm('<?= $ativo === 1 ? 'Desativar' : 'Ativar' ?> este usuário?<?= $ativo === 1 ? ' Ele passa para a aba Inativos.' : ' Ele volta para a aba Ativos.' ?>')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $usuario['id_usuario'] ?>">
                                <!-- Devolve a pessoa pra mesma aba/busca/página em que ela estava. -->
                                <input type="hidden" name="retorno" value="<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? '', ENT_QUOTES) ?>">
                                <button type="submit"
                                    class="badge-botao"
                                    title="Clique para <?= $ativo === 1 ? 'desativar' : 'ativar' ?>">
                                    <?php if ($ativo === 1): ?>
                                        <span class="badge badge--success">
                                            <span class="badge__dot"></span>Ativo
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge--danger">
                                            <span class="badge__dot"></span>Inativo
                                        </span>
                                    <?php endif; ?>
                                </button>
                            </form>
                        </td>

                        <!-- Criado em -->
                        <td class="text-muted">
                            <?= !empty($usuario['criado_em'])
                                ? date('d/m/Y', strtotime($usuario['criado_em']))
                                : '--' ?>
                        </td>

                        <!-- Ações -->
                        <td>
                            <div class="row-actions" style="justify-content:flex-end;">
                                <a href="./viewUsuario&id=<?= $usuario['id_usuario'] ?>"
                                    class="row-action-btn primary"
                                    title="Visualizar">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </a>

                                <a href="./editUsuario&id=<?= $usuario['id_usuario'] ?>"
                                    class="row-action-btn"
                                    title="Editar">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                    </svg>
                                </a>

                                <form method="post" action="./deleteUsuario" style="display:inline;"
                                    onsubmit="return confirm('Deseja excluir este usuário? Esta ação não pode ser desfeita.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $usuario['id_usuario'] ?>">
                                    <button type="submit" class="row-action-btn danger" title="Excluir">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="3 6 5 6 21 6" />
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                        </svg>
                                    </button>
                                </form>
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
                                ? 'Nenhum usuário encontrado'
                                : ($aba === 'inativos' ? 'Nenhum usuário inativo' : 'Nenhum usuário ativo') ?>
                        </div>
                        <div style="font-size:12px;margin-bottom:16px;">
                            <?php if ($temFiltro): ?>
                                Tente ajustar os filtros de busca.
                            <?php elseif ($aba === 'inativos'): ?>
                                Usuários desativados aparecem aqui.
                            <?php else: ?>
                                Comece cadastrando o primeiro usuário do sistema.
                            <?php endif; ?>
                        </div>
                        <?php if ($temFiltro): ?>
                            <a href="./listUsuarios?aba=<?= htmlspecialchars($aba) ?>" class="btn btn--ghost btn--sm" data-usuarios-link>Limpar filtros</a>
                        <?php elseif ($aba === 'ativos'): ?>
                            <a href="./saveUsuario" class="btn btn--primary btn--sm">Novo Usuário</a>
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
        Mostrando <?= count($usuarios) ?> de <?= $totalUsuarios ?? 0 ?> resultados
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
                    . ' data-usuarios-link href="' . htmlspecialchars($link(['pagina' => $pagina])) . '">'
                    . $rotulo . '</a>';
            };

            echo $botao($paginaAtual - 1, '‹', $paginaAtual <= 1);

            // Paginação compacta: mostra sempre a 1ª, a última, a atual e
            // vizinhas próximas — evita uma lista infinita de botões
            // quando há muitas páginas.
            $janela = 1;
            $ultimaImpressa = 0;

            for ($p = 1; $p <= $totalPaginas; $p++) {
                if (!($p === 1 || $p === $totalPaginas || abs($p - $paginaAtual) <= $janela)) {
                    continue;
                }

                if ($ultimaImpressa && $p - $ultimaImpressa > 1) {
                    echo '<span class="pagination__btn" style="border:none;cursor:default;">…</span>';
                }

                echo $botao($p, (string) $p, false, $p === $paginaAtual);
                $ultimaImpressa = $p;
            }

            echo $botao($paginaAtual + 1, '›', $paginaAtual >= $totalPaginas);
            ?>
        </div>
    <?php endif; ?>
</div>
