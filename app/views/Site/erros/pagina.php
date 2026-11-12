<?php
/**
 * Conteúdo de TODAS as telas de erro (Erro::CATALOGO), idêntico em todas:
 *
 *      código HTTP
 *      título
 *      uma linha de explicação
 *      [ botão principal ]
 *      link secundário
 *
 * Muda só o texto e as saídas de cada situação. A lista de saídas é montada
 * aqui, num lugar só, em vez de uma tela por tipo.
 *
 * Nada aqui expõe stack trace, caminho de arquivo ou mensagem do banco: o
 * detalhe técnico vai só para o log, associado ao código de ocorrência.
 *
 * Espera: $erro, $contexto.
 */
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$tipo       = $erro['tipo'];
$publico    = !empty($erro['publico']) || !empty($contexto['publico']);
$ocorrencia = (string) ($contexto['ocorrencia'] ?? '');
$entidade   = trim((string) ($contexto['entidade'] ?? ''));
$voltarPara = $contexto['voltar'] ?? null;

$resumo = ($tipo === 'registro-inexistente' && $entidade !== '')
    // sem flexionar gênero ("Conversa não encontrado" estaria errado)
    ? 'O registro de “' . $entidade . '” que você tentou abrir não está mais no sistema.'
    : $erro['resumo'];

// Saídas. 'seta' = a seta de "voltar" dentro do botão principal, como no modelo.
$inicio   = ['rotulo' => 'Voltar ao início', 'href' => './home', 'seta' => true];
$anterior = ['rotulo' => 'Voltar à página anterior', 'attr' => 'data-erro-voltar'];
$tentar   = ['rotulo' => 'Tentar novamente', 'attr' => 'data-erro-tentar'];

$principal = null;
$link      = null;

switch ($tipo) {
    case 'nao-encontrado':
    case 'sem-permissao':
        $principal = $inicio;
        $link      = $anterior;
        break;

    case 'registro-inexistente':
        $principal = $voltarPara ? ['rotulo' => 'Voltar para a lista', 'href' => $voltarPara, 'seta' => true] : $inicio;
        $link      = $anterior;
        break;

    case 'rastreio-invalido':
        // Cliente da oficina: conferir o link é a única coisa que ele pode fazer sozinho.
        $principal = $tentar;
        break;

    case 'portal-invalido':
        break; // sem saída possível: o link precisa ser reemitido pela oficina

    case 'nao-autenticado':
        $retorno   = (string) ($contexto['retorno'] ?? ($_SERVER['REQUEST_URI'] ?? ''));
        $principal = ['rotulo' => 'Entrar novamente', 'href' => './login' . ($retorno !== '' ? '?retorno=' . urlencode($retorno) : '')];
        break;

    case 'sessao-expirada':
        // Voltar pelo histórico é o que faz o navegador devolver os campos preenchidos.
        $principal = ['rotulo' => 'Voltar e enviar de novo', 'attr' => 'data-erro-voltar', 'seta' => true];
        $link      = ['rotulo' => 'Ir para o início', 'href' => './home'];
        break;

    default: // falha-interna · indisponivel · tempo-esgotado · sem-conexao
        $principal = $tentar;
        $link      = ['rotulo' => 'Voltar ao início', 'href' => './home'];
        break;
}

// Cliente da oficina não tem para onde ir dentro do sistema.
if ($publico && $link && ($link['href'] ?? '') === './home') {
    $link = null;
}
if ($publico && $tipo === 'nao-encontrado') {
    $principal = $link = null;
}

// Manutenção, tempo esgotado e queda de conexão tendem a passar sozinhos: tenta de novo sem avisar na tela.
$autoTentar = in_array($tipo, ['indisponivel', 'tempo-esgotado', 'sem-conexao'], true);
$segundos   = $tipo === 'indisponivel' ? 30 : 15;

$seta = '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>';
?>

<main class="erro-centro">
    <p class="erro-codigo"><?= (int) $erro['http'] ?></p>

    <h1 class="erro-titulo"><?= $h($erro['titulo']) ?></h1>

    <p class="erro-resumo"><?= $h($resumo) ?></p>

    <?php if ($principal): ?>
        <?php if (isset($principal['href'])): ?>
            <a href="<?= $h($principal['href']) ?>" class="erro-btn"><?= !empty($principal['seta']) ? $seta : '' ?><span><?= $h($principal['rotulo']) ?></span></a>
        <?php else: ?>
            <button type="button" class="erro-btn" <?= $principal['attr'] ?>>
                <?= !empty($principal['seta']) ? $seta : '' ?>
                <?php if ($principal['attr'] === 'data-erro-tentar'): ?><span class="erro-btn__spin" aria-hidden="true"></span><?php endif; ?>
                <span><?= $h($principal['rotulo']) ?></span>
            </button>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($link): ?>
        <?php if (isset($link['href'])): ?>
            <a href="<?= $h($link['href']) ?>" class="erro-link"><?= $h($link['rotulo']) ?></a>
        <?php else: ?>
            <button type="button" class="erro-link" <?= $link['attr'] ?>><?= $h($link['rotulo']) ?></button>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($tipo === 'falha-interna' && $ocorrencia !== ''): ?>
        <!-- Único detalhe além do modelo: sem este código ninguém consegue achar a linha certa no log. -->
        <p class="erro-ocorrencia">Código da ocorrência <span id="erro-ocorrencia"><?= $h($ocorrencia) ?></span></p>
    <?php endif; ?>

    <?php if ($autoTentar): ?>
        <span hidden data-erro-recontagem="<?= (int) $segundos ?>"><span data-erro-recontagem-texto></span></span>
    <?php endif; ?>
</main>
