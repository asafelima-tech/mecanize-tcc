<?php
/** Espera: $tipo, $http, $titulo, $resumo, $ocorrencia, $voltar */
$inicio = Contexto::logado() ? './' . Permissao::inicio(Contexto::perfil()) : './login';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mecanize · <?= e($titulo) ?></title>
    <style><?= Erro::css() ?></style>
</head>

<body class="erro-pagina">
    <span class="erro-logo-texto">Mecanize</span>

    <main class="erro-centro">
        <p class="erro-codigo"><?= (int) $http ?></p>
        <h1 class="erro-titulo"><?= e($titulo) ?></h1>
        <p class="erro-resumo"><?= e($resumo) ?></p>

        <?php if ($tipo === 'nao-autenticado'): ?>
            <a class="erro-btn" href="./login"><span>Entrar novamente</span></a>
        <?php elseif ($tipo === 'sessao-expirada'): ?>
            <button type="button" class="erro-btn" onclick="history.back()"><span>Voltar e enviar de novo</span></button>
        <?php elseif ($tipo !== 'rastreio-invalido'): ?>
            <a class="erro-btn" href="<?= e($voltar ?: $inicio) ?>"><span><?= $voltar ? 'Voltar para a lista' : 'Voltar ao início' ?></span></a>
            <button type="button" class="erro-link" onclick="history.back()">Voltar à página anterior</button>
        <?php endif; ?>

        <?php if ($tipo === 'falha-interna' && $ocorrencia): ?>
            <p class="erro-ocorrencia">Código da ocorrência <span><?= e($ocorrencia) ?></span></p>
        <?php endif; ?>
    </main>
</body>

</html>
