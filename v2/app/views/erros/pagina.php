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
    <?php require V2_RAIZ . '/app/views/layout/_favicon.php'; ?>
    <style><?= Erro::css() ?></style>
    <script>try { if (localStorage.getItem('mecanize-theme') === 'dark') document.documentElement.setAttribute('data-theme', 'dark'); } catch (e) {}</script>
</head>

<body class="erro-pagina">
    <img class="erro-logo logo-claro" src="./img/logo.png" alt="Mecanize">

    <main class="erro-centro">
        <p class="erro-codigo"><?= (int) $http ?></p>
        <h1 class="erro-titulo"><?= e($titulo) ?></h1>
        <p class="erro-resumo"><?= e($resumo) ?></p>

        <?php if ($tipo === 'nao-autenticado'): ?>
            <a class="btn btn--primary btn--lg" href="./login">Entrar novamente</a>
        <?php elseif ($tipo === 'sessao-expirada'): ?>
            <button type="button" class="btn btn--primary btn--lg" onclick="history.back()">Voltar e enviar de novo</button>
        <?php elseif ($tipo !== 'rastreio-invalido'): ?>
            <div class="acoes" style="justify-content:center">
                <a class="btn btn--primary btn--lg" href="<?= e($voltar ?: $inicio) ?>"><?= $voltar ? 'Voltar para a lista' : 'Voltar ao início' ?></a>
                <button type="button" class="btn btn--ghost btn--lg" onclick="history.back()">Página anterior</button>
            </div>
        <?php endif; ?>

        <?php if ($tipo === 'falha-interna' && $ocorrencia): ?>
            <p class="erro-ocorrencia">Código da ocorrência <span><?= e($ocorrencia) ?></span></p>
        <?php endif; ?>
    </main>
</body>

</html>
