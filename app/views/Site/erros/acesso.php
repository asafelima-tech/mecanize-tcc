<?php
/**
 * Arquétipo ACESSO — barreira de identidade.
 *
 * Cobre 'sem-permissao' (é você mesmo, mas seu perfil não alcança),
 * 'nao-autenticado' (não sabemos quem é você) e 'sessao-expirada' (sabíamos,
 * mas o selo do formulário venceu). São três problemas diferentes e três
 * saídas diferentes — por isso a arte, o selo e os botões mudam entre eles.
 *
 * Mostrar quem está logado é proposital: sem isso, "acesso negado" vira
 * discussão. Vendo o próprio perfil na tela, a pessoa entende na hora que
 * precisa de outra conta ou de liberação.
 *
 * Espera: $erro, $contexto.
 */
$tipo     = $erro['tipo'];
$autonomo = empty($_SESSION['logged']);

$nome   = $_SESSION['user_nome'] ?? '';
$perfil = $_SESSION['user_tipo'] ?? '';

$iniciais = '';
if ($nome !== '') {
    $partes   = preg_split('/\s+/', trim($nome));
    $iniciais = strtoupper(mb_substr($partes[0], 0, 1) . (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : ''));
}

$perfilLabel = ['ADMIN' => 'Administrador', 'TECNICO' => 'Técnico', 'ATENDENTE' => 'Atendente'][$perfil] ?? $perfil;

// Para onde mandar de volta depois de entrar de novo: sem isso, o usuário
// perde o que estava tentando abrir e precisa navegar tudo outra vez.
$retorno = $contexto['retorno'] ?? ($_SERVER['REQUEST_URI'] ?? '');

$selo = ['sem-permissao' => '403', 'nao-autenticado' => '401', 'sessao-expirada' => ''][$tipo] ?? '';
?>

<div class="erro erro--acesso erro--<?= htmlspecialchars($erro['tom']) ?><?= $autonomo ? ' erro--autonomo' : '' ?>">
    <div class="erro__cartao">

        <!-- Painel da esquerda: a barreira -->
        <div class="erro__painel">
            <div class="erro__cadeado">
                <?php if ($tipo === 'sessao-expirada'): ?>
                    <!-- Relógio: é uma questão de tempo, não de permissão -->
                    <svg viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="3"
                         stroke-linecap="round" stroke-linejoin="round" style="color:var(--erro-acento)">
                        <circle cx="50" cy="52" r="30" opacity=".25" />
                        <circle cx="50" cy="52" r="23" />
                        <path class="erro__cadeado-arco" d="M50 40v13l9 6" />
                        <path d="M38 18h24" />
                    </svg>
                <?php elseif ($tipo === 'nao-autenticado'): ?>
                    <!-- Crachá: falta identificar quem é -->
                    <svg viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="3"
                         stroke-linecap="round" stroke-linejoin="round" style="color:var(--erro-acento)">
                        <rect x="24" y="30" width="52" height="52" rx="6" opacity=".25" />
                        <rect x="24" y="30" width="52" height="52" rx="6" />
                        <circle class="erro__cadeado-arco" cx="50" cy="50" r="8" />
                        <path d="M37 70a13 13 0 0 1 26 0" />
                        <path d="M44 30v-8h12v8" />
                    </svg>
                <?php else: ?>
                    <!-- Cadeado: a porta existe, a chave é que não é sua -->
                    <svg viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="3"
                         stroke-linecap="round" stroke-linejoin="round" style="color:var(--erro-acento)">
                        <path class="erro__cadeado-arco" d="M34 44V34a16 16 0 0 1 32 0v10" />
                        <rect x="24" y="44" width="52" height="38" rx="7" opacity=".25" />
                        <rect x="24" y="44" width="52" height="38" rx="7" />
                        <circle cx="50" cy="60" r="4.5" fill="currentColor" stroke="none" />
                        <path d="M50 64.5V70" />
                    </svg>
                <?php endif; ?>
            </div>

            <?php if ($selo !== ''): ?>
                <div>
                    <div class="erro__selo"><?= $selo ?></div>
                    <div class="erro__selo-texto">
                        <?= $tipo === 'sem-permissao' ? 'Acesso restrito' : 'Não autenticado' ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="erro__selo-texto">Sessão expirada</div>
            <?php endif; ?>
        </div>

        <!-- Conteúdo da direita: explicação e saída -->
        <div class="erro__conteudo">
            <h1 class="erro__titulo"><?= htmlspecialchars($erro['titulo']) ?></h1>
            <p class="erro__resumo"><?= htmlspecialchars($erro['resumo']) ?></p>

            <?php if ($nome !== '' && $tipo === 'sem-permissao'): ?>
                <div class="erro__identidade">
                    <span class="erro__identidade-avatar"><?= htmlspecialchars($iniciais) ?></span>
                    <div>
                        <div class="erro__identidade-nome"><?= htmlspecialchars($nome) ?></div>
                        <div class="erro__identidade-perfil">
                            Conectado como <?= htmlspecialchars($perfilLabel) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($contexto['detalhe'])): ?>
                <p class="erro__detalhe"><?= htmlspecialchars($contexto['detalhe']) ?></p>
            <?php endif; ?>

            <div class="erro__porque">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="16" x2="12" y2="12" />
                    <line x1="12" y1="8" x2="12.01" y2="8" />
                </svg>
                <span><?= htmlspecialchars($erro['porque']) ?></span>
            </div>

            <div class="erro__acoes">
                <?php if ($tipo === 'sessao-expirada'): ?>
                    <!-- Volta pro formulário: o navegador devolve os campos
                         preenchidos, então não se perde o que foi digitado. -->
                    <button type="button" class="erro-btn erro-btn--principal" data-erro-voltar>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 14 4 9 9 4" />
                            <path d="M20 20v-7a4 4 0 0 0-4-4H4" />
                        </svg>
                        Voltar e enviar de novo
                    </button>
                    <a href="./home" class="erro-btn erro-btn--secundario">Ir para o início</a>

                <?php elseif ($tipo === 'nao-autenticado'): ?>
                    <a href="./login<?= $retorno ? '?retorno=' . urlencode($retorno) : '' ?>" class="erro-btn erro-btn--principal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                            <polyline points="10 17 15 12 10 7" />
                            <line x1="15" y1="12" x2="3" y2="12" />
                        </svg>
                        Entrar novamente
                    </a>

                <?php else: ?>
                    <button type="button" class="erro-btn erro-btn--principal" data-erro-voltar>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 14 4 9 9 4" />
                            <path d="M20 20v-7a4 4 0 0 0-4-4H4" />
                        </svg>
                        Voltar
                    </button>
                    <a href="./home" class="erro-btn erro-btn--secundario">Ir para o início</a>
                    <a href="./perfil" class="erro-btn erro-btn--discreto">Ver meu perfil</a>
                <?php endif; ?>
            </div>

            <?php if ($tipo === 'sem-permissao'): ?>
                <p class="erro__detalhe" style="margin:16px 0 0;">
                    Precisa deste acesso? Peça a um administrador para ajustar o seu perfil em Usuários.
                </p>
            <?php endif; ?>
        </div>

    </div>
</div>
