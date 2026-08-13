<?php
/**
 * Arquétipo BÚSSOLA — o usuário se perdeu, não fez nada errado.
 *
 * Cobre 'nao-encontrado' (endereço que não existe) e 'registro-inexistente'
 * (o item existia e não está mais lá). São 404 os dois, mas a saída é
 * diferente: um precisa de um mapa do sistema, o outro precisa voltar pra
 * lista de onde veio — por isso a ilustração e as ações mudam.
 *
 * Espera: $erro, $contexto.
 */
$ehRegistro = $erro['tipo'] === 'registro-inexistente';
$ehRastreio = $erro['tipo'] === 'rastreio-invalido';
$autonomo   = empty($_SESSION['logged']) || !empty($erro['autonomo']);

$entidade = trim((string) ($contexto['entidade'] ?? ''));
$voltar   = $contexto['voltar'] ?? null;

// Atalhos só fazem sentido pra quem está logado (e só pro que a pessoa
// realmente pode abrir — oferecer link pra área restrita seria oferecer um 403).
$ehAdmin = ($_SESSION['user_tipo'] ?? '') === 'ADMIN';

$atalhos = [
    ['rota' => './home',             'texto' => 'Início',    'icone' => '<path d="M3 10l9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>'],
    ['rota' => './listClientes',     'texto' => 'Clientes',  'icone' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>'],
    ['rota' => './listOrdemServico', 'texto' => 'Ordens',    'icone' => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/>'],
    ['rota' => './listAgendamentos', 'texto' => 'Agenda',    'icone' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="2" x2="8" y2="6"/>'],
];

if ($ehAdmin) {
    $atalhos[] = ['rota' => './listRel', 'texto' => 'Relatórios', 'icone' => '<line x1="3" y1="20" x2="21" y2="20"/><rect x="5" y="12" width="3" height="8" rx="1"/><rect x="10" y="7" width="3" height="13" rx="1"/><rect x="16" y="15" width="3" height="5" rx="1"/>'];
}
?>

<div class="erro erro--bussola erro--<?= htmlspecialchars($erro['tom']) ?><?= $autonomo ? ' erro--autonomo' : '' ?>">
    <div class="erro__cartao">

        <div class="erro__bussola-arte">
            <?php if ($ehRegistro): ?>
                <!-- Caixa aberta e vazia: o item saiu daqui -->
                <svg viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="2.4"
                     stroke-linecap="round" stroke-linejoin="round" style="color:var(--erro-acento)">
                    <path d="M18 40h64v34a4 4 0 0 1-4 4H22a4 4 0 0 1-4-4z" opacity=".22" />
                    <path d="M18 40h64v34a4 4 0 0 1-4 4H22a4 4 0 0 1-4-4z" />
                    <path d="M14 28h72v12H14z" />
                    <line x1="42" y1="54" x2="58" y2="54" />
                    <!-- tampa levantada -->
                    <g class="erro__agulha" style="transform-origin:50px 28px;animation-duration:0s">
                        <path d="M28 28l6-12h32l6 12" opacity=".45" />
                    </g>
                </svg>
            <?php else: ?>
                <!-- Bússola: o endereço não leva a lugar nenhum -->
                <svg viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="2.4"
                     stroke-linecap="round" stroke-linejoin="round" style="color:var(--erro-acento)">
                    <circle cx="50" cy="50" r="34" opacity=".25" />
                    <circle cx="50" cy="50" r="26" />
                    <g class="erro__agulha">
                        <path d="M50 32l6 16-6 4-6-4z" fill="currentColor" stroke="none" />
                        <path d="M50 68l-6-16 6-4 6 4z" fill="currentColor" stroke="none" opacity=".3" />
                    </g>
                    <circle cx="50" cy="50" r="2.6" fill="currentColor" stroke="none" />
                </svg>
            <?php endif; ?>
        </div>

        <div class="erro__codigo">
            <?php if ($ehRegistro): ?>
                Registro removido
            <?php elseif ($ehRastreio): ?>
                Link não reconhecido
            <?php else: ?>
                Erro 404
            <?php endif; ?>
        </div>

        <h1 class="erro__titulo">
            <?php if ($ehRegistro && $entidade !== ''): ?>
                <?= htmlspecialchars($entidade) ?> não encontrado
            <?php else: ?>
                <?= htmlspecialchars($erro['titulo']) ?>
            <?php endif; ?>
        </h1>

        <p class="erro__resumo"><?= htmlspecialchars($erro['resumo']) ?></p>

        <?php if (!empty($contexto['detalhe'])): ?>
            <p class="erro__detalhe"><?= htmlspecialchars($contexto['detalhe']) ?></p>
        <?php endif; ?>

        <div class="erro__porque" style="text-align:left;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="16" x2="12" y2="12" />
                <line x1="12" y1="8" x2="12.01" y2="8" />
            </svg>
            <span><?= htmlspecialchars($erro['porque']) ?></span>
        </div>

        <div class="erro__acoes">
            <?php if ($ehRastreio): ?>
                <!-- Quem vê esta tela é o cliente da oficina, não um usuário do
                     sistema: não existe "início" pra ele, e mandar pro login
                     seria oferecer uma porta que ele não tem chave. A saída
                     útil é conferir o link ou falar com a oficina. -->
                <button type="button" class="erro-btn erro-btn--principal" data-erro-tentar>
                    <span class="erro-btn__spin" aria-hidden="true"></span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" data-erro-icone>
                        <polyline points="23 4 23 10 17 10" />
                        <path d="M20.5 15a9 9 0 1 1-2.1-9.4L23 10" />
                    </svg>
                    Tentar de novo
                </button>

            <?php elseif ($voltar): ?>
                <a href="<?= htmlspecialchars($voltar) ?>" class="erro-btn erro-btn--principal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12" />
                        <polyline points="12 19 5 12 12 5" />
                    </svg>
                    Voltar para a lista
                </a>
            <?php else: ?>
                <a href="./home" class="erro-btn erro-btn--principal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 10l9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                        <path d="M9 22V12h6v10" />
                    </svg>
                    Ir para o início
                </a>
            <?php endif; ?>

            <?php if (!$ehRastreio): ?>
                <button type="button" class="erro-btn erro-btn--secundario" data-erro-voltar>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 14 4 9 9 4" />
                        <path d="M20 20v-7a4 4 0 0 0-4-4H4" />
                    </svg>
                    Voltar à página anterior
                </button>
            <?php endif; ?>
        </div>

        <?php if ($ehRastreio): ?>
            <p class="erro__detalhe" style="margin:18px 0 0;">
                Confira se o endereço foi copiado por inteiro. Se o problema continuar,
                entre em contato com a oficina informando a placa do veículo.
            </p>
        <?php endif; ?>

        <?php if (!$autonomo && !$ehRegistro): ?>
            <div class="erro__atalhos">
                <div class="erro__atalhos-titulo">Ir direto para</div>
                <div class="erro__atalhos-grade">
                    <?php foreach ($atalhos as $a): ?>
                        <a href="<?= $a['rota'] ?>" class="erro__atalho">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $a['icone'] ?></svg>
                            <?= $a['texto'] ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>
