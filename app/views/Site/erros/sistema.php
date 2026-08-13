<?php
/**
 * Arquétipo SISTEMA — a falha é nossa, não do usuário.
 *
 * Cobre 'falha-interna', 'indisponivel', 'sem-conexao' e 'tempo-esgotado'.
 * O que muda entre eles é a expectativa: erro interno precisa de código de
 * ocorrência pra rastrear; manutenção e timeout resolvem sozinhos com o
 * tempo (daí a nova tentativa automática); falta de conexão depende do lado
 * do usuário (daí reagir ao evento de rede voltar).
 *
 * Nada aqui expõe stack trace, caminho de arquivo ou mensagem do banco: o
 * detalhe técnico vai só pro log, associado ao mesmo código de ocorrência
 * que aparece na tela.
 *
 * Espera: $erro, $contexto.
 */
$tipo     = $erro['tipo'];
$autonomo = empty($_SESSION['logged']) || $erro['autonomo'];

$ocorrencia = $contexto['ocorrencia'] ?? '';

// Só faz sentido tentar sozinho quando o problema tende a passar: manutenção,
// timeout e queda de conexão. Erro interno repetido em laço só empilha log.
$autoTentar = in_array($tipo, ['indisponivel', 'tempo-esgotado', 'sem-conexao'], true);
$segundos   = $tipo === 'indisponivel' ? 30 : 15;
?>

<div class="erro erro--sistema erro--<?= htmlspecialchars($erro['tom']) ?><?= $autonomo ? ' erro--autonomo' : '' ?>">
    <div class="erro__cartao">

        <div class="erro__barra"></div>

        <div class="erro__sistema-corpo">

            <div class="erro__sistema-topo">
                <div class="erro__sistema-icone">
                    <?php if ($tipo === 'sem-conexao'): ?>
                        <!-- Sinal cortado, com as ondas piscando -->
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path class="erro__onda erro__onda--2" d="M1.4 8.5a16 16 0 0 1 21.2 0" />
                            <path class="erro__onda erro__onda--1" d="M5 12a11 11 0 0 1 14 0" />
                            <path d="M8.5 15.5a6 6 0 0 1 7 0" />
                            <line x1="12" y1="19" x2="12.01" y2="19" />
                            <line x1="2" y1="2" x2="22" y2="22" />
                        </svg>
                    <?php elseif ($tipo === 'tempo-esgotado'): ?>
                        <!-- Ampulheta -->
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 2h12" />
                            <path d="M6 22h12" />
                            <path d="M6 2c0 5 6 6 6 10S6 17 6 22" />
                            <path d="M18 2c0 5-6 6-6 10s6 5 6 10" />
                        </svg>
                    <?php elseif ($tipo === 'indisponivel'): ?>
                        <!-- Chave de boca: manutenção programada -->
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14.7 6.3a4 4 0 0 0 5 5l-9.4 9.4a2.8 2.8 0 0 1-4-4z" />
                            <path d="M14.7 6.3l3-3a5.5 5.5 0 0 1 3 3l-3 3" />
                        </svg>
                    <?php else: ?>
                        <!-- Triângulo de atenção -->
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" />
                            <line x1="12" y1="9" x2="12" y2="13" />
                            <line x1="12" y1="17" x2="12.01" y2="17" />
                        </svg>
                    <?php endif; ?>
                </div>

                <div>
                    <h1 class="erro__titulo" style="margin-bottom:4px;"><?= htmlspecialchars($erro['titulo']) ?></h1>
                    <p class="erro__resumo" style="margin:0;"><?= htmlspecialchars($erro['resumo']) ?></p>
                </div>
            </div>

            <?php if (!empty($contexto['detalhe'])): ?>
                <p class="erro__detalhe" style="margin-top:0;"><?= htmlspecialchars($contexto['detalhe']) ?></p>
            <?php endif; ?>

            <div class="erro__porque">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="16" x2="12" y2="12" />
                    <line x1="12" y1="8" x2="12.01" y2="8" />
                </svg>
                <span><?= htmlspecialchars($erro['porque']) ?></span>
            </div>

            <?php if ($tipo === 'falha-interna' && $ocorrencia !== ''): ?>
                <div class="erro__ocorrencia">
                    <span class="erro__ocorrencia-rotulo">Código da ocorrência</span>
                    <span style="display:flex;align-items:center;gap:6px;">
                        <span class="erro__ocorrencia-codigo" id="erro-ocorrencia"><?= htmlspecialchars($ocorrencia) ?></span>
                        <button type="button" class="erro__copiar" data-erro-copiar="<?= htmlspecialchars($ocorrencia) ?>"
                                title="Copiar código" aria-label="Copiar código da ocorrência">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="9" y="9" width="13" height="13" rx="2" />
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                            </svg>
                        </button>
                    </span>
                </div>
            <?php endif; ?>

            <div class="erro__acoes">
                <button type="button" class="erro-btn erro-btn--principal" data-erro-tentar>
                    <span class="erro-btn__spin" aria-hidden="true"></span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" data-erro-icone>
                        <polyline points="23 4 23 10 17 10" />
                        <path d="M20.5 15a9 9 0 1 1-2.1-9.4L23 10" />
                    </svg>
                    Tentar novamente
                </button>

                <?php if (!$autonomo): ?>
                    <a href="./home" class="erro-btn erro-btn--secundario">Ir para o início</a>
                <?php else: ?>
                    <a href="./login" class="erro-btn erro-btn--secundario">Ir para o login</a>
                <?php endif; ?>

                <?php if ($tipo === 'falha-interna'): ?>
                    <a href="mailto:suporte@mecanize.com.br?subject=<?= rawurlencode('Erro no sistema — ocorrência ' . $ocorrencia) ?>&body=<?= rawurlencode("Código da ocorrência: {$ocorrencia}\n\nO que eu estava tentando fazer:\n") ?>"
                       class="erro-btn erro-btn--discreto">
                        Falar com o suporte
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($autoTentar): ?>
                <div class="erro__recontagem" data-erro-recontagem="<?= $segundos ?>">
                    <span class="erro__recontagem-ponto"></span>
                    <span data-erro-recontagem-texto>
                        Nova tentativa automática em <?= $segundos ?>s
                    </span>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
