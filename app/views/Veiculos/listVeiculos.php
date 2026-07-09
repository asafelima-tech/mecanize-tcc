<?php
// Variáveis esperadas do controller:
// $veiculos, $totalVeiculos, $novosMes, $marcasfrequentes, $paginaAtual

$logos = [
    'chevrolet'  => 'https://www.carlogos.org/car-logos/chevrolet-logo.png',
    'gm'         => 'https://www.carlogos.org/car-logos/chevrolet-logo.png',
    'fiat'       => 'https://www.carlogos.org/car-logos/fiat-logo.png',
    'volkswagen' => 'https://www.carlogos.org/car-logos/volkswagen-logo.png',
    'vw'         => 'https://www.carlogos.org/car-logos/volkswagen-logo.png',
    'ford'       => 'https://www.carlogos.org/car-logos/ford-logo.png',
    'toyota'     => 'https://www.carlogos.org/car-logos/toyota-logo.png',
    'honda'      => 'https://www.carlogos.org/car-logos/honda-logo.png',
    'hyundai'    => 'https://www.carlogos.org/car-logos/hyundai-logo.png',
    'renault'    => 'https://www.carlogos.org/car-logos/renault-logo.png',
    'peugeot'    => 'https://www.carlogos.org/car-logos/peugeot-logo.png',
    'citroën'    => 'https://www.carlogos.org/car-logos/citroen-logo.png',
    'nissan'     => 'https://www.carlogos.org/car-logos/nissan-logo.png',
    'mitsubishi' => 'https://www.carlogos.org/car-logos/mitsubishi-logo.png',
    'jeep'       => 'https://www.carlogos.org/car-logos/jeep-logo.png',
    'bmw'        => 'https://www.carlogos.org/car-logos/bmw-logo.png',
    'mercedes'   => 'https://www.carlogos.org/car-logos/mercedes-benz-logo.png',
    'audi'       => 'https://www.carlogos.org/car-logos/audi-logo.png',
    'kia'        => 'https://www.carlogos.org/car-logos/kia-logo.png',
    'chery'      => 'https://www.carlogos.org/car-logos/chery-logo.png',
    'caoa'       => 'https://www.carlogos.org/car-logos/chery-logo.png',
    'volvo'      => 'https://www.carlogos.org/car-logos/volvo-logo.png',
    'suzuki'     => 'https://www.carlogos.org/car-logos/suzuki-logo.png',
    'subaru'     => 'https://www.carlogos.org/car-logos/subaru-logo.png',
    'dodge'      => 'https://www.carlogos.org/car-logos/dodge-logo.png',
    'ram'        => 'https://www.carlogos.org/car-logos/ram-logo.png',
    'land rover' => 'https://www.carlogos.org/car-logos/land-rover-logo.png',
];

// Mapa cor (nome) => hex para colorir a bolinha. Lookup case-insensitive.
$coresCarro  = require __DIR__ . '/_coresCarro.php';
$coresLookup = [];
foreach ($coresCarro as $nomeCor => $hexCor) {
    $coresLookup[strtolower($nomeCor)] = $hexCor;
}

/**
 * Desenha a placa como SVG inline (em vez de CSS puro) — dá pra usar filtro
 * de iluminação especular (feSpecularLighting) pra simular o relevo
 * metálico de verdade da placa antiga, e formas nítidas (sem gradiente
 * borrado) pros detalhes da Mercosul. Proporção 300x100 ≈ a real (400x130mm).
 * $uid precisa ser único por placa na página, pra cada <svg> ter seu próprio
 * filtro (IDs de filtro duplicados no mesmo documento confundem o navegador).
 */
function renderPlacaSvg(string $placaTxt, bool $isMercosul, $uid): string
{
    $texto = htmlspecialchars($placaTxt);
    $fEmboss = "emboss-{$uid}";

    if (!$isMercosul) {
        // ===== Modelo antigo: chapa cinza, número em relevo metálico =====
        return <<<SVG
        <svg viewBox="0 0 300 100" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Placa {$texto}, modelo antigo">
            <defs>
                <linearGradient id="bgAntiga-{$uid}" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#f3f4f5"/>
                    <stop offset="0.55" stop-color="#dcdde0"/>
                    <stop offset="1" stop-color="#c9cacd"/>
                </linearGradient>
                <filter id="{$fEmboss}" x="-30%" y="-30%" width="160%" height="160%">
                    <feGaussianBlur in="SourceAlpha" stdDeviation="1.4" result="blur"/>
                    <feSpecularLighting in="blur" surfaceScale="3.2" specularConstant="0.85" specularExponent="14" lighting-color="#ffffff" result="spec">
                        <feDistantLight azimuth="235" elevation="55"/>
                    </feSpecularLighting>
                    <feComposite in="spec" in2="SourceAlpha" operator="in" result="specClip"/>
                    <feComposite in="SourceGraphic" in2="specClip" operator="arithmetic" k1="0" k2="1" k3="1" k4="0"/>
                </filter>
            </defs>
            <rect x="2.5" y="2.5" width="295" height="95" rx="9" fill="url(#bgAntiga-{$uid})" stroke="#000000" stroke-width="5"/>
            <rect x="8" y="8" width="284" height="84" rx="5" fill="none" stroke="#ffffff" stroke-opacity="0.5" stroke-width="1"/>
            <text x="150" y="66" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-weight="800" font-size="42" letter-spacing="3" fill="#1b1c1f" filter="url(#{$fEmboss})">{$texto}</text>
        </svg>
        SVG;
    }

    // ===== Mercosul: faixa azul, emblema, bandeira, selo de QR =====
    return <<<SVG
    <svg viewBox="0 0 300 100" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Placa {$texto}, Mercosul">
        <defs>
            <linearGradient id="bgTop-{$uid}" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#2166e0"/>
                <stop offset="1" stop-color="#0c3c96"/>
            </linearGradient>
        </defs>
        <rect x="2.5" y="2.5" width="295" height="95" rx="9" fill="#fcfcfd" stroke="#000000" stroke-width="5"/>
        <path d="M8 8 h284 v18 a5 5 0 0 1 -5 5 h-274 a5 5 0 0 1 -5 -5 Z" fill="url(#bgTop-{$uid})"/>

        <!-- Emblema Mercosul: arco de estrelas + faixa verde -->
        <g transform="translate(18,10)">
            <circle cx="0" cy="9" r="1.3" fill="#ffd60a"/>
            <circle cx="6" cy="3" r="1.3" fill="#ffd60a"/>
            <circle cx="13" cy="1.5" r="1.3" fill="#ffd60a"/>
            <circle cx="20" cy="3" r="1.3" fill="#ffd60a"/>
            <path d="M-2 13 Q 10 19 22 13" stroke="#3ddc70" stroke-width="1.6" fill="none" stroke-linecap="round"/>
        </g>

        <text x="150" y="21" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-weight="800" font-size="13" letter-spacing="3.5" fill="#ffffff">BRASIL</text>

        <!-- Bandeira do Brasil, canto superior direito -->
        <g transform="translate(262,9)">
            <rect width="24" height="16" rx="2" fill="#1e9e3e"/>
            <polygon points="12,2 22,8 12,14 2,8" fill="#ffdf00"/>
            <circle cx="12" cy="8" r="3.4" fill="#1c4fb0"/>
        </g>

        <text x="150" y="72" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-weight="800" font-size="38" letter-spacing="1.5" fill="#131417">{$texto}</text>

        <!-- Selo de QR code (estilizado) -->
        <g transform="translate(14,78)">
            <rect width="18" height="18" fill="#ffffff" stroke="#101114" stroke-width="1"/>
            <rect x="2" y="2" width="4" height="4" fill="#101114"/>
            <rect x="8" y="2" width="2" height="2" fill="#101114"/>
            <rect x="12" y="2" width="4" height="4" fill="#101114"/>
            <rect x="2" y="8" width="2" height="2" fill="#101114"/>
            <rect x="8" y="8" width="2" height="2" fill="#101114"/>
            <rect x="12" y="8" width="2" height="2" fill="#101114"/>
            <rect x="2" y="12" width="4" height="4" fill="#101114"/>
            <rect x="8" y="12" width="2" height="2" fill="#101114"/>
            <rect x="12" y="12" width="4" height="4" fill="#101114"/>
        </g>
        <text x="36" y="93" font-family="Arial, Helvetica, sans-serif" font-weight="800" font-size="10" fill="#131417">BR</text>
    </svg>
    SVG;
}
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Gestão de Veículos</h1>
        <p class="page-header__subtitle">Visualize e gerencie todos os veículos cadastrados na oficina.</p>
    </div>
    <div class="page-header__actions">
        <a href="./saveVeiculo" class="btn btn--primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Novo Veículo
        </a>
    </div>
</div>

<!-- Stat Cards -->
<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Total de Veículos</div>
            <div class="stat-card__icon b1">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="3" width="15" height="13" rx="2" />
                    <path d="M16 8h4l3 5v4h-7V8z" />
                    <circle cx="5.5" cy="18.5" r="2.5" />
                    <circle cx="18.5" cy="18.5" r="2.5" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $totalVeiculos ?? 0 ?></div>
        <div class="stat-card__delta">Total de veículos cadastrados</div>
    </div>

    <!-- ====== CARD "Novos este Mês" — agora clicável ====== -->
    <div class="stat-card stat-card--clickable" id="cardVeiculosNovosMes" role="button" tabindex="0">
        <div class="stat-card__head">
            <div class="stat-card__label">Novos este Mês</div>
            <div class="stat-card__icon b2">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="16" />
                    <line x1="8" y1="12" x2="16" y2="12" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= $novosMes ?? 0 ?></div>
        <div class="stat-card__delta neutral">Clique para ver os novos veículos</div>
    </div>
    <!-- ====== /CARD ====== -->

    <div class="stat-card">
        <div class="stat-card__head">
            <div class="stat-card__label">Marca mais Recorrente</div>
            <div class="stat-card__icon b3">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" />
                    <line x1="7" y1="7" x2="7.01" y2="7" />
                </svg>
            </div>
        </div>
        <div class="stat-card__value"><?= htmlspecialchars($marcasfrequentes ?? '—') ?></div>
        <div class="stat-card__delta neutral">Marca mais registrada no sistema</div>
    </div>
</section>

<!-- ============================================================
     PAINEL "Novos Veículos" — fixed + posicionado via JS, mesmo
     padrão usado no card VIP/Novos Clientes da tela de Clientes.
     ============================================================ -->
<div class="veic-panel" id="panelVeiculosNovosMes">
    <div class="veic-panel__track">

        <!-- Tela 1: Lista -->
        <div class="veic-screen">
            <div class="veic-panel__header">
                <div class="veic-panel__title">
                    <span class="veic-panel__icon">✦</span>
                    Novos Veículos do Mês
                </div>
                <button class="veic-panel__close" id="veicNovosClose" type="button" aria-label="Fechar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="veic-panel__body" id="veicNovosListBody">
                <div class="veic-loading">Carregando...</div>
            </div>
        </div>

        <!-- Tela 2: Perfil do veículo -->
        <div class="veic-screen">
            <div class="veic-panel__header">
                <button class="veic-panel__back" id="veicNovosBack" type="button">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7" />
                    </svg>
                    Voltar
                </button>
                <button class="veic-panel__close" id="veicNovosCloseFromPerfil" type="button" aria-label="Fechar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="veic-panel__body" id="veicNovosPerfilBody">
                <div class="veic-loading">Carregando perfil...</div>
            </div>
        </div>

    </div>
</div>

<!-- Tabela de Veículos -->
<section class="card">

    <!-- Toolbar / Filtros -->
    <div class="table-toolbar">
        <div class="search-wrap">
            <input
                type="text"
                id="busca-veiculo"
                placeholder="Buscar por placa, modelo ou cliente..."
                oninput="filtrarVeiculos()" />
        </div>

        <select id="filtro-marca" class="toolbar-select" onchange="filtrarVeiculos()">
            <option value="">Marca: Todas</option>
            <?php
            $marcasUnicas = array_unique(array_column($veiculos ?? [], 'marca_nome'));
            sort($marcasUnicas);
            foreach ($marcasUnicas as $marca):
                if (empty($marca)) continue;
            ?>
                <option value="<?= htmlspecialchars($marca) ?>"><?= htmlspecialchars($marca) ?></option>
            <?php endforeach; ?>
        </select>

        <select id="filtro-combustivel" class="toolbar-select" onchange="filtrarVeiculos()">
            <option value="">Combustível: Todos</option>
            <?php
            $combustiveisUnicos = array_unique(array_column($veiculos ?? [], 'combustivel'));
            sort($combustiveisUnicos);
            foreach ($combustiveisUnicos as $combustivelOpcao):
                if (empty($combustivelOpcao)) continue;
            ?>
                <option value="<?= htmlspecialchars($combustivelOpcao) ?>"><?= htmlspecialchars($combustivelOpcao) ?></option>
            <?php endforeach; ?>
        </select>

        <span id="contador-veiculos" style="margin-left:auto;font-size:12px;color:var(--color-text-muted);">
            <?= !empty($veiculos) ? count($veiculos) : 0 ?> resultado(s)
        </span>
    </div>

    <!-- Tabela -->
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Placa</th>
                    <th>Veículo</th>
                    <th>Cor</th>
                    <th>Ano</th>
                    <th>Combustível</th>
                    <th>Proprietário</th>
                    <th>Observações</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($veiculos)): ?>
                    <?php foreach ($veiculos as $veiculo): ?>
                        <tr>
                            <!-- Placa -->
                            <td>
                                <?php
                                $placaTxt   = $veiculo['placa'] ?? $veiculo['placa_normalizada'] ?? '—';
                                $placaLimpa = strtoupper(preg_replace('/[^A-Z0-9]/', '', $placaTxt));
                                $isMercosul = (bool) preg_match('/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/', $placaLimpa);
                                ?>
                                <span class="plate-br" title="<?= htmlspecialchars($placaTxt) ?> (<?= $isMercosul ? 'Mercosul' : 'Modelo antigo' ?>)">
                                    <?= renderPlacaSvg($placaTxt, $isMercosul, $veiculo['id_veiculo'] ?? $placaLimpa) ?>
                                </span>
                            </td>

                            <!-- Veículo (Marca + Modelo) -->
                            <td>
                                <div class="vehicle-cell">
                                    <div class="vehicle-cell__icon">
                                        <?php
                                        $marcaKey = strtolower(trim($veiculo['marca_nome'] ?? ''));
                                        $logoUrl  = $logos[$marcaKey] ?? null;
                                        ?>
                                        <?php if ($logoUrl): ?>
                                            <img src="<?= $logoUrl ?>"
                                                alt="<?= htmlspecialchars($veiculo['marca_nome']) ?>"
                                                style="width:28px;height:28px;object-fit:contain;"
                                                onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                                            <svg style="display:none" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="1" y="3" width="15" height="13" rx="2" />
                                                <path d="M16 8h4l3 5v4h-7V8z" />
                                                <circle cx="5.5" cy="18.5" r="2.5" />
                                                <circle cx="18.5" cy="18.5" r="2.5" />
                                            </svg>
                                        <?php else: ?>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="1" y="3" width="15" height="13" rx="2" />
                                                <path d="M16 8h4l3 5v4h-7V8z" />
                                                <circle cx="5.5" cy="18.5" r="2.5" />
                                                <circle cx="18.5" cy="18.5" r="2.5" />
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <div class="user-cell__name">
                                        <?= htmlspecialchars(trim(($veiculo['marca_nome'] ?? '') . ' ' . ($veiculo['modelo_nome'] ?? ''))) ?: '—' ?>
                                    </div>
                                </div>
                            </td>

                            <!-- Cor -->
                            <td>
                                <?php
                                $corNome = $veiculo['cor'] ?? '';
                                $corHex  = $coresLookup[strtolower($corNome)] ?? '#CBD5E1';
                                ?>
                                <span class="color-chip">
                                    <span
                                        class="color-chip__dot"
                                        style="background:<?= htmlspecialchars($corHex) ?>;<?= strtoupper($corHex) === '#FFFFFF' ? 'border:1px solid #CBD5E1;' : '' ?>"></span>
                                    <?= htmlspecialchars($corNome !== '' ? $corNome : '—') ?>
                                </span>
                            </td>

                            <!-- Ano -->
                            <td><?= htmlspecialchars($veiculo['ano'] ?? '—') ?></td>

                            <!-- Combustível -->
                            <td><?= !empty($veiculo['combustivel']) ? htmlspecialchars($veiculo['combustivel']) : '—' ?></td>

                            <!-- Proprietário -->
                            <td><?= htmlspecialchars($veiculo['cliente_nome'] ?? '—') ?></td>

                            <!-- Observações -->
                            <td class="obs-cell">
                                <?= !empty($veiculo['observacoes']) ? htmlspecialchars($veiculo['observacoes']) : '—' ?>
                            </td>

                            <!-- Ações -->
                            <td>
                                <div class="row-actions">
                                    <a href="./viewVeiculo&id=<?= $veiculo['id_veiculo'] ?>" class="row-action-btn primary" title="Visualizar">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </a>
                                    <a href="./editVeiculo&id=<?= $veiculo['id_veiculo'] ?>" class="row-action-btn" title="Editar">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                        </svg>
                                    </a>
                                    <a
                                        href="./deleteVeiculo&id=<?= $veiculo['id_veiculo'] ?>"
                                        class="row-action-btn danger"
                                        title="Excluir"
                                        onclick="return confirm('Excluir este veículo? Todos os orçamentos e ordens de serviço vinculados a ele também serão APAGADOS. Esta ação é irreversível.')">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6" />
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:48px 20px;color:var(--color-text-muted);">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 12px;display:block;opacity:.4;">
                                <rect x="1" y="3" width="15" height="13" rx="2" />
                                <path d="M16 8h4l3 5v4h-7V8z" />
                                <circle cx="5.5" cy="18.5" r="2.5" />
                                <circle cx="18.5" cy="18.5" r="2.5" />
                            </svg>
                            Nenhum veículo cadastrado.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginação -->
    <div class="pagination">
        <span>
            Mostrando <?= !empty($veiculos) ? count($veiculos) : 0 ?> de <?= $totalVeiculos ?? 0 ?> resultados
        </span>
        <div class="pagination__pages">
            <?php
            $pagAtual  = (int)($paginaAtual ?? 1);
            $totalPags = (int)ceil(($totalVeiculos ?? 0) / 10);
            $totalPags = max($totalPags, 1);
            $inicio    = max(1, $pagAtual - 1);
            $fim       = min($totalPags, $pagAtual + 1);
            ?>
            <button class="pagination__btn" <?= $pagAtual <= 1 ? 'disabled' : '' ?>>‹</button>
            <?php for ($p = $inicio; $p <= $fim; $p++): ?>
                <button class="pagination__btn <?= $p === $pagAtual ? 'is-active' : '' ?>"><?= $p ?></button>
            <?php endfor; ?>
            <button class="pagination__btn" <?= $pagAtual >= $totalPags ? 'disabled' : '' ?>>›</button>
        </div>
    </div>

</section>

<style>
    .stat-card--clickable {
        position: relative;
        cursor: pointer;
        transition: box-shadow .2s ease, transform .2s ease;
    }

    .stat-card--clickable:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }

    .stat-card--clickable.is-active {
        box-shadow: 0 0 0 2px var(--color-primary), var(--shadow-md);
    }

    /* ===== Painel — fixed, posição calculada via JS (getBoundingClientRect) =====
       Mesma técnica usada na tela de Clientes: position:fixed evita disputa
       de stacking context com elementos position:relative da página
       (ex.: .search-wrap), que "roubam" a pintura por ordem de DOM. */
    .veic-panel {
        position: fixed;
        width: 380px;
        max-width: calc(100vw - 48px);
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        z-index: 9999;
        overflow: hidden;
        opacity: 0;
        transform: translateY(-6px) scale(.98);
        pointer-events: none;
        transition: opacity .18s ease, transform .18s ease;
        transform-origin: top left;
    }

    .veic-panel.is-open {
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: auto;
    }

    .veic-panel__track {
        display: flex;
        width: 200%;
        transition: transform .28s cubic-bezier(.2, .8, .2, 1);
    }

    .veic-panel.is-perfil .veic-panel__track {
        transform: translateX(-50%);
    }

    .veic-screen {
        width: 50%;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        max-height: 440px;
    }

    .veic-panel__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px;
        border-bottom: 1px solid var(--color-border);
        background: var(--color-bg-alt);
        flex-shrink: 0;
    }

    .veic-panel__title {
        font-size: 13px;
        font-weight: 700;
        color: var(--color-text);
        display: flex;
        align-items: center;
        gap: 7px;
    }

    .veic-panel__icon {
        color: var(--color-primary);
        font-size: 14px;
    }

    .veic-panel__close {
        width: 26px;
        height: 26px;
        border-radius: var(--radius-sm);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--color-text-muted);
        transition: background .15s, color .15s;
        flex-shrink: 0;
    }

    .veic-panel__close:hover {
        background: var(--color-border);
        color: var(--color-text);
    }

    .veic-panel__back {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 12.5px;
        font-weight: 600;
        color: var(--color-primary);
    }

    .veic-panel__back:hover {
        color: var(--color-primary-hover);
    }

    .veic-panel__body {
        padding: 8px;
        overflow-y: auto;
        flex: 1;
    }

    .veic-loading {
        text-align: center;
        padding: 32px 0;
        color: var(--color-text-muted);
        font-size: 12.5px;
    }

    .veic-empty {
        text-align: center;
        padding: 28px 16px;
        color: var(--color-text-muted);
        font-size: 12.5px;
    }

    /* --- Linha da lista --- */
    .veic-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        border-radius: var(--radius-md);
        cursor: pointer;
        transition: background .15s ease;
    }

    .veic-row:hover {
        background: var(--color-primary-soft);
    }

    .veic-row__icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--color-primary-soft);
        color: var(--color-primary);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .veic-row__info {
        min-width: 0;
        flex: 1;
    }

    .veic-row__name {
        font-weight: 600;
        font-size: 13px;
        color: var(--color-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .veic-row__sub {
        font-size: 11.5px;
        color: var(--color-text-muted);
        margin-top: 1px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .veic-row__badge {
        font-size: 11px;
        font-weight: 700;
        color: var(--color-primary);
        background: var(--color-primary-soft);
        padding: 3px 9px;
        border-radius: var(--radius-pill);
        flex-shrink: 0;
    }

    /* --- Perfil --- */
    .veic-perfil-head {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 4px 4px 16px;
        margin-bottom: 12px;
        border-bottom: 1px solid var(--color-border);
    }

    .veic-perfil-icon {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: var(--color-primary-soft);
        color: var(--color-primary);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .veic-perfil-name {
        font-size: 14.5px;
        font-weight: 700;
        color: var(--color-text);
    }

    .veic-perfil-tag {
        margin-top: 4px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        font-weight: 700;
        color: var(--color-primary);
        background: var(--color-primary-soft);
        padding: 3px 9px;
        border-radius: var(--radius-pill);
    }

    .veic-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        padding: 0 4px 4px;
    }

    .veic-info-item {
        background: var(--color-bg-alt);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 8px 10px;
    }

    .veic-info-item--full {
        grid-column: 1 / -1;
    }

    .veic-info-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--color-text-muted);
        font-weight: 700;
        margin-bottom: 2px;
    }

    .veic-info-value {
        font-size: 12.5px;
        color: var(--color-text);
        font-weight: 600;
        word-break: break-word;
    }
</style>

<script>
    function filtrarVeiculos() {
        const busca = document.getElementById('busca-veiculo').value.toLowerCase();
        const marca = document.getElementById('filtro-marca').value.toLowerCase();
        const combustivel = document.getElementById('filtro-combustivel').value.toLowerCase();
        const linhas = document.querySelectorAll('.table tbody tr');
        let visiveis = 0;

        linhas.forEach(tr => {
            const texto = tr.innerText.toLowerCase();
            const tdMarca = tr.querySelector('td:nth-child(2)')?.innerText.toLowerCase() ?? '';
            const tdCombustivel = tr.querySelector('td:nth-child(5)')?.innerText.trim().toLowerCase() ?? '';

            const okBusca = !busca || texto.includes(busca);
            const okMarca = !marca || tdMarca.includes(marca);
            const okCombustivel = !combustivel || tdCombustivel === combustivel;
            const visivel = okBusca && okMarca && okCombustivel;

            tr.style.display = visivel ? '' : 'none';
            if (visivel) visiveis++;
        });

        const contador = document.getElementById('contador-veiculos');
        if (contador) contador.textContent = visiveis + ' resultado(s)';
    }

    (function() {
        function formatarData(d) {
            if (!d) return 'Sem registro';
            return new Date(d).toLocaleDateString('pt-BR');
        }

        const iconeCarroSVG = `
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="1" y="3" width="15" height="13" rx="2" />
                <path d="M16 8h4l3 5v4h-7V8z" />
                <circle cx="5.5" cy="18.5" r="2.5" />
                <circle cx="18.5" cy="18.5" r="2.5" />
            </svg>
        `;

        function criarLinhaVeiculo(v) {
            const nomeVeiculo = `${v.marca_nome || ''} ${v.modelo_nome || ''}`.trim() || 'Veículo sem modelo';
            return `
                <div class="veic-row" data-id="${v.id_veiculo}">
                    <div class="veic-row__icon">${iconeCarroSVG}</div>
                    <div class="veic-row__info">
                        <div class="veic-row__name">${nomeVeiculo}</div>
                        <div class="veic-row__sub">${v.placa || 'Sem placa'} · ${v.cliente_nome || 'Sem proprietário'}</div>
                    </div>
                    <div class="veic-row__badge">Novo</div>
                </div>
            `;
        }

        function renderPerfilVeiculo(v) {
            const nomeVeiculo = `${v.marca_nome || ''} ${v.modelo_nome || ''}`.trim() || 'Veículo sem modelo';

            return `
                <div class="veic-perfil-head">
                    <div class="veic-perfil-icon">${iconeCarroSVG}</div>
                    <div>
                        <div class="veic-perfil-name">${nomeVeiculo}</div>
                        <div class="veic-perfil-tag">✦ Novo · cadastrado em ${formatarData(v.criado_em)}</div>
                    </div>
                </div>

                <div class="veic-info-grid">
                    <div class="veic-info-item">
                        <div class="veic-info-label">Placa</div>
                        <div class="veic-info-value">${v.placa || 'Não informada'}</div>
                    </div>
                    <div class="veic-info-item">
                        <div class="veic-info-label">Ano</div>
                        <div class="veic-info-value">${v.ano || 'Não informado'}</div>
                    </div>
                    <div class="veic-info-item">
                        <div class="veic-info-label">Cor</div>
                        <div class="veic-info-value">${v.cor || 'Não informada'}</div>
                    </div>
                    <div class="veic-info-item">
                        <div class="veic-info-label">Combustível</div>
                        <div class="veic-info-value">${v.combustivel || 'Não informado'}</div>
                    </div>
                    <div class="veic-info-item veic-info-item--full">
                        <div class="veic-info-label">Proprietário</div>
                        <div class="veic-info-value">${v.cliente_nome || 'Não informado'}</div>
                    </div>
                    <div class="veic-info-item veic-info-item--full">
                        <div class="veic-info-label">Observações</div>
                        <div class="veic-info-value">${v.observacoes || 'Nenhuma'}</div>
                    </div>
                </div>
            `;
        }

        const card = document.getElementById('cardVeiculosNovosMes');
        const panel = document.getElementById('panelVeiculosNovosMes');
        const listBody = document.getElementById('veicNovosListBody');
        const perfilBody = document.getElementById('veicNovosPerfilBody');

        function posicionarPainel() {
            const rect = card.getBoundingClientRect();
            const margem = 16;
            let left = rect.left;
            const larguraPainel = panel.offsetWidth || 380;

            if (left + larguraPainel > window.innerWidth - margem) {
                left = Math.max(margem, window.innerWidth - larguraPainel - margem);
            }

            panel.style.top = (rect.bottom + 10) + 'px';
            panel.style.left = left + 'px';
        }

        function abrirPainel() {
            posicionarPainel();
            panel.classList.add('is-open');
            card.classList.add('is-active');
            carregarLista();

            window.addEventListener('scroll', posicionarPainel, true);
            window.addEventListener('resize', posicionarPainel);
        }

        function fecharPainel() {
            panel.classList.remove('is-open', 'is-perfil');
            card.classList.remove('is-active');

            window.removeEventListener('scroll', posicionarPainel, true);
            window.removeEventListener('resize', posicionarPainel);
        }

        function togglePainel(e) {
            e.stopPropagation();
            panel.classList.contains('is-open') ? fecharPainel() : abrirPainel();
        }

        function carregarLista() {
            listBody.innerHTML = '<div class="veic-loading">Carregando...</div>';

            fetch('./listVeiculosNovosMes')
                .then(r => r.json())
                .then(data => {
                    if (!data.sucesso || !data.veiculos.length) {
                        listBody.innerHTML = '<div class="veic-empty">Nenhum veículo novo este mês ainda.</div>';
                        return;
                    }
                    listBody.innerHTML = data.veiculos.map(criarLinhaVeiculo).join('');

                    listBody.querySelectorAll('.veic-row').forEach(row => {
                        row.addEventListener('click', () => abrirPerfil(row.dataset.id));
                    });
                })
                .catch(() => {
                    listBody.innerHTML = '<div class="veic-empty">Erro ao carregar novos veículos.</div>';
                });
        }

        function abrirPerfil(id) {
            panel.classList.add('is-perfil');
            perfilBody.innerHTML = '<div class="veic-loading">Carregando perfil...</div>';

            fetch('./veiculoPerfil?id=' + encodeURIComponent(id))
                .then(r => r.json())
                .then(data => {
                    if (!data.sucesso) {
                        perfilBody.innerHTML = '<div class="veic-empty">Não foi possível carregar o perfil.</div>';
                        return;
                    }
                    perfilBody.innerHTML = renderPerfilVeiculo(data.veiculo);
                })
                .catch(() => {
                    perfilBody.innerHTML = '<div class="veic-empty">Erro ao carregar perfil.</div>';
                });
        }

        function voltarLista(e) {
            e.stopPropagation();
            panel.classList.remove('is-perfil');
        }

        card.addEventListener('click', togglePainel);
        document.getElementById('veicNovosClose').addEventListener('click', (e) => {
            e.stopPropagation();
            fecharPainel();
        });
        document.getElementById('veicNovosCloseFromPerfil').addEventListener('click', (e) => {
            e.stopPropagation();
            fecharPainel();
        });
        document.getElementById('veicNovosBack').addEventListener('click', voltarLista);

        panel.addEventListener('click', (e) => e.stopPropagation());

        document.addEventListener('click', (e) => {
            if (!panel.contains(e.target) && !card.contains(e.target)) {
                fecharPainel();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') fecharPainel();
        });
    })();
</script>